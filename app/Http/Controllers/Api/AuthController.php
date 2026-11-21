<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Services\FirebaseIdentityVerifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\AccountLoginSecurity;

class AuthController extends Controller
{
    private function ok($data = null, string $msg = 'OK', int $code = 200)
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data, 'errors' => null], $code);
    }

    private function fail(string $msg, int $code = 422, $errors = null, $data = null)
    {
        return response()->json(['success' => false, 'message' => $msg, 'data' => $data, 'errors' => $errors], $code);
    }

    private function loginRestriction(User $user, array $state)
    {
        $requiresReset = $state['status'] === 'password_reset_required';
        $seconds = max(0, (int) ($state['retry_after_seconds'] ?? 0));
        $duration = max(1, (int) ($state['lockout_duration_seconds'] ?? $seconds));
        $minutes = max(1, (int) ceil($duration / 60));
        $wait = $minutes === 1 ? '1 minute' : $minutes.' minutes';
        $message = $requiresReset
            ? 'A password reset is required before you can sign in.'
            : 'Too many failed login attempts. Please wait '.$wait.' before trying again.';
        $metadata = ['security' => [
            'status' => $requiresReset ? 'password_reset_required' : 'locked',
            'retry_after_seconds' => $seconds,
            'remaining_seconds' => $seconds,
            'locked_until' => $user->login_locked_until?->toIso8601String(),
            'lockout_duration_seconds' => (int) ($state['lockout_duration_seconds'] ?? 0),
            'lockout_cycle' => (int) ($state['lockout_cycle'] ?? $user->login_lockout_cycles),
            'password_reset_required' => $requiresReset,
        ]];

        return $this->fail($message, 429, null, $metadata)
            ->header('Retry-After', (string) $seconds);
    }

    /** Public, rate-limited status check. Unknown and unlocked identifiers share identical data. */
    public function loginLockoutStatus(Request $request)
    {
        $validated = $request->validate(['login' => 'required|string|max:255']);
        $identifier = trim($validated['login']);
        $user = User::query()
            ->where('username', strtolower($identifier))
            ->orWhere('email', strtolower($identifier))
            ->orWhere('phone', $identifier)
            ->first();

        $security = $user
            ? app(AccountLoginSecurity::class)->currentLockout($user)
            : [
                'status' => 'available',
                'retry_after_seconds' => 0,
                'remaining_seconds' => 0,
                'locked_until' => null,
                'lockout_duration_seconds' => 0,
                'lockout_cycle' => 0,
            ];

        return $this->ok(['security' => $security]);
    }

    // POST /api/v1/auth/register  (customer lang)
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'username'              => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
            'email'                 => 'required|email|max:150|unique:users,email',
            'phone'                 => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phone'],
            'password'              => 'required|string|min:8|confirmed',
        ]);
        $data['username'] = strtolower($data['username']);

        $user = User::create($data + ['role' => 'customer', 'status' => 'active']);
        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        return $this->ok(['user' => $this->userPayload($user), 'token' => $token], 'Registered', 201);
    }

    // POST /api/v1/auth/login  (customer, rider, staff, admin)
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',   // email o phone
            'password' => 'required|string',
        ]);

        $identifier = trim($request->input('login'));
        $user = User::where('username', strtolower($identifier))
            ->orWhere('email', strtolower($identifier))
            ->orWhere('phone', $identifier)
            ->first();

        if (!$user) {
            return $this->fail('Invalid username or password.', 401);
        }
        $security = app(AccountLoginSecurity::class);
        $attempt = $security->attemptPassword($user, $request->password);
        if (in_array($attempt['status'], ['locked', 'password_reset_required'], true)) {
            $user->refresh();
            return $this->loginRestriction($user, $attempt);
        }
        if ($attempt['status'] !== 'success') {
            return $this->fail('Invalid username or password.', 401);
        }
        if ($user->status !== 'active') {
            return $this->fail('Hindi aktibo ang account na ito.', 403);
        }
        if ($user->role === 'rider' && !optional($user->riderProfile)->is_approved) {
            return $this->fail('Hindi pa naaprubahan ang rider account.', 403);
        }

        $tokenResult = $security->issuePasswordToken(
            $user,
            $request->input('device_name', 'mobile'),
        );
        if (in_array($tokenResult['status'], ['locked', 'password_reset_required'], true)) {
            $user->refresh();
            return $this->loginRestriction($user, $tokenResult);
        }
        $token = $tokenResult['token'];

        return $this->ok(['user' => $this->userPayload($user), 'token' => $token], 'Logged in');
    }

    // POST /api/v1/auth/google: verify a Firebase Authentication ID token.
    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string|max:8192',
            'provider' => 'sometimes|in:facebook',
        ]);
        $requestedProvider = $request->input('provider');

        $credentialsPath = config('services.firebase.credentials');
        if (!$credentialsPath) {
            return $this->fail('Firebase Admin credentials are not configured on the server.', 503);
        }

        $isAbsolutePath = str_starts_with($credentialsPath, '/')
            || str_starts_with($credentialsPath, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $credentialsPath) === 1;
        $resolvedCredentialsPath = $isAbsolutePath
            ? $credentialsPath
            : base_path($credentialsPath);

        if (!is_file($resolvedCredentialsPath) || !is_readable($resolvedCredentialsPath)) {
            return $this->fail('Firebase Admin credentials are not configured on the server.', 503);
        }

        try {
            $firebaseIdToken = $request->input('id_token');
            $identity = app(FirebaseIdentityVerifier::class)->verify(
                $firebaseIdToken,
                $resolvedCredentialsPath,
                (string) config('services.firebase.project_id'),
            );
            $firebaseUid = $identity['uid'];
            $email = $identity['email'];
            $emailVerified = $identity['email_verified'];
            $name = $identity['name'];
            $signInProvider = $identity['sign_in_provider'];

            if ($firebaseUid === '') {
                return $this->fail('Firebase sign-in could not be verified. Please sign in again.', 401);
            }
            if ($requestedProvider === 'facebook' && $signInProvider !== 'facebook.com') {
                Log::warning('Facebook authentication diagnostic', [
                    'reason' => 'provider_claim_mismatch',
                    'provider_claim' => $signInProvider === null || $signInProvider === ''
                        ? 'missing'
                        : 'other',
                    'email_present' => $email !== '',
                    'email_verified' => $emailVerified,
                    'uid_linked' => User::where('firebase_uid', $firebaseUid)->exists(),
                ]);
                return $this->fail('Facebook sign-in could not be verified. Please try again.', 401);
            }
            if ($requestedProvider !== 'facebook'
                && (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified)) {
                return $this->fail('The Firebase account must have a verified email address.', 401);
            }
        } catch (\Throwable $e) {
            // Never log the token, email, or the SDK exception message. The
            // decoded JWT fields below are diagnostic only and never trusted.
            $diagnostics = $this->firebaseTokenDiagnostics($firebaseIdToken ?? $request->input('id_token'));
            Log::warning('Firebase ID token verification failed', $diagnostics + [
                'expected_project_id' => config('services.firebase.project_id'),
                'exception_class' => $e::class,
                'expired_error' => str_contains(strtolower($e->getMessage()), 'expired'),
            ]);
            if ($requestedProvider === 'facebook') {
                Log::warning('Facebook authentication diagnostic', [
                    'reason' => 'firebase_token_verification_failed',
                    'provider_claim' => 'missing',
                    'email_present' => false,
                    'email_verified' => false,
                    'exception_class' => $e::class,
                ]);
            }

            if (!$diagnostics['audience_matches_project'] || !$diagnostics['issuer_matches_project']) {
                return $this->fail(
                    'The app sent a token for a different Firebase project or sign-in provider. Rebuild and reinstall the latest app, then try again.',
                    401,
                );
            }

            if (($diagnostics['expires_in_seconds'] ?? 0) <= 0 || str_contains(strtolower($e->getMessage()), 'expired')) {
                return $this->fail(
                    'Firebase returned an expired ID token. Force stop the app, reopen it, and sign in again. If it repeats, update/reinstall the latest app build.',
                    401,
                );
            }

            return $this->fail('Firebase sign-in could not be verified. Please sign in again.', 401);
        }

        // A Facebook identity with a verified Firebase UID already linked to a
        // Laravel account is authoritative even when Firebase marks its email
        // unverified. Email matching below remains restricted to verified email.
        $user = User::where('firebase_uid', $firebaseUid)->first();
        if (!$user) {
            if ($requestedProvider === 'facebook'
                && (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified)) {
                Log::warning('Facebook authentication diagnostic', [
                    'reason' => 'facebook_email_unverified_or_missing',
                    'provider_claim' => 'facebook.com',
                    'email_present' => $email !== '',
                    'email_verified' => $emailVerified,
                    'uid_linked' => false,
                ]);
                return $this->fail(
                    'Use a verified email or link Facebook from your existing account before signing in.',
                    401,
                    null,
                    ['auth_action' => 'facebook_email_verification_required'],
                );
            }
            $user = User::where('email', $email)->first();
            if ($requestedProvider === 'facebook' && $user && $user->firebase_uid !== $firebaseUid) {
                return $this->fail('This email is already associated with an existing account. Sign in using that account or contact support.', 409);
            }
            if ($user && $user->firebase_uid && $user->firebase_uid !== $firebaseUid) {
                return $this->fail('This email is already linked to a different Firebase account.', 409);
            }

            if (!$user) {
                $user = User::create([
                    'name' => mb_substr($name !== '' ? $name : strstr($email, '@', true), 0, 100),
                    'email' => $email,
                    'username' => $this->uniqueUsername($email),
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }

            $user->forceFill([
                'firebase_uid' => $firebaseUid,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        if ($user->status !== 'active') return $this->fail('This account is not active.', 403);
        if (!in_array($user->role, ['customer', 'rider'], true)) {
            return $this->fail('This app is for customers and riders. Staff and admins should use the web panel.', 403);
        }
        if ($user->role === 'rider' && !optional($user->riderProfile)->is_approved) {
            return $this->fail('The rider account has not been approved yet.', 403);
        }

        $securityState = app(AccountLoginSecurity::class)->issueGoogleToken(
            $user,
            $request->input('device_name', 'kjm-firebase-app'),
        );
        if (in_array($securityState['status'], ['locked', 'password_reset_required'], true)) {
            $user->refresh();
            return $this->loginRestriction($user, $securityState);
        }

        $token = $securityState['token'];
        return $this->ok(['user' => $this->userPayload($user), 'token' => $token], 'Logged in with Firebase');
    }
    // POST /api/v1/auth/forgot-password
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:150']);
        try {
            Password::sendResetLink($data);
        } catch (\Throwable $e) {
            return $this->fail('Password reset email could not be sent. Check the server mail configuration.', 503);
        }
        // Keep the response identical whether or not the account exists.
        return $this->ok(null, 'If the email is registered, a password reset link has been sent.');
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->ok(null, 'Logged out');
    }

    // GET /api/v1/profile
    public function profile(Request $request)
    {
        return $this->ok($this->userPayload($request->user()));
    }

    // PUT /api/v1/profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'                  => 'sometimes|string|max:100',
            'phone'                 => 'sometimes|nullable|string|max:20|unique:users,phone,' . $user->id,
            'bio'                   => 'sometimes|nullable|string|max:500',
            'preferred_language'    => 'sometimes|in:en,fil',
            'current_password'      => 'required_with:password|string',
            'password'              => 'sometimes|string|min:8|confirmed',
            'avatar'                => 'sometimes|image|max:5120',
        ]);

        if (isset($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                return $this->fail('Your current password is incorrect.', 422);
            }
            unset($data['current_password']);
        }

        $oldAvatar = $user->avatar;
        $newAvatar = null;
        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatars', 'public');
            if (!is_string($newAvatar) || $newAvatar === '') {
                return $this->fail('Hindi na-save ang larawan sa XAMPP storage. Suriin ang storage/app/public permissions.', 500);
            }
            $data['avatar'] = $newAvatar;
        }

        $user->update($data);
        $user = $user->fresh();
        if ($newAvatar && $oldAvatar && $oldAvatar !== $newAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return $this->ok($this->userPayload($user), 'Profile updated');
    }
    /**
     * Read only non-sensitive JWT metadata for diagnostics. These claims are
     * deliberately not trusted for authentication; verifyIdToken() remains the gate.
     */
    private function firebaseTokenDiagnostics(?string $token): array
    {
        $parts = is_string($token) ? explode('.', $token) : [];
        $payload = count($parts) === 3
            ? base64_decode(strtr($parts[1], '-_', '+/'), true)
            : false;
        $claims = is_string($payload) ? json_decode($payload, true) : null;
        $claims = is_array($claims) ? $claims : [];
        $projectId = (string) config('services.firebase.project_id');
        $audience = $claims['aud'] ?? null;
        $issuer = $claims['iss'] ?? null;
        $expiresAt = isset($claims['exp']) && is_numeric($claims['exp']) ? (int) $claims['exp'] : null;
        $issuedAt = isset($claims['iat']) && is_numeric($claims['iat']) ? (int) $claims['iat'] : null;

        return [
            'token_format_valid' => count($parts) === 3 && $claims !== [],
            'issuer_matches_project' => $issuer === 'https://securetoken.google.com/' . $projectId,
            'audience_matches_project' => $audience === $projectId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'server_time' => time(),
            'expires_in_seconds' => $expiresAt === null ? null : $expiresAt - time(),
        ];
    }

    private function uniqueUsername(string $email): string
    {
        $base = strtolower((string) strstr($email, '@', true));
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'customer';
        $base = substr($base, 0, 24);
        $candidate = $base;
        $suffix = 1;
        while (User::where('username', $candidate)->exists()) {
            $tail = (string) $suffix++;
            $candidate = substr($base, 0, 30 - strlen($tail)) . $tail;
        }
        return $candidate;
    }

    private function userPayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'bio'        => $user->bio,
            'preferred_language' => $user->preferred_language ?? 'en',
            'role'       => $user->role,
            'avatar'     => $user->avatar,
            'avatar_url' => $user->avatar ? '/storage/' . ltrim($user->avatar, '/') : null,
        ];
    }
    // DELETE /api/v1/profile - account deletion required by app stores
    public function deleteAccount(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (!Hash::check($request->password, $user->password)) return $this->fail('Maling password.', 422);
        if ($user->avatar) Storage::disk('public')->delete($user->avatar);
        $user->tokens()->delete();
        $user->delete();
        return $this->ok(null, 'Nabura na ang account.');
    }
    // POST /api/v1/profile/fcm-token
    public function fcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string|max:255']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return $this->ok(null, 'Token saved');
    }
}


