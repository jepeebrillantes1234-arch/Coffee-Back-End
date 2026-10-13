<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function ok($data = null, string $msg = 'OK', int $code = 200)
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data, 'errors' => null], $code);
    }

    private function fail(string $msg, int $code = 422, $errors = null)
    {
        return response()->json(['success' => false, 'message' => $msg, 'data' => null, 'errors' => $errors], $code);
    }

    // POST /api/v1/auth/register  (customer lang)
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'username'              => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
            'email'                 => 'required|email|max:150|unique:users,email',
            'phone'                 => 'nullable|string|max:20|unique:users,phone',
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

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->fail('Invalid username or password.', 401);
        }
        if ($user->status !== 'active') {
            return $this->fail('Hindi aktibo ang account na ito.', 403);
        }
        if ($user->role === 'rider' && !optional($user->riderProfile)->is_approved) {
            return $this->fail('Hindi pa naaprubahan ang rider account.', 403);
        }

        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        return $this->ok(['user' => $this->userPayload($user), 'token' => $token], 'Logged in');
    }

    // POST /api/v1/auth/google: verify Google's ID token on the server.
    public function googleLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string|max:8192']);
        $clientId = config('services.google.client_id');
        if (!$clientId) return $this->fail('Google sign-in is not configured on the server.', 503);

        try {
            $response = Http::asForm()
                ->connectTimeout(8)
                ->timeout(15)
                ->retry(3, 500, function ($exception): bool {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException
                        || ($exception instanceof \Illuminate\Http\Client\RequestException
                            && $exception->response?->serverError());
                })
                ->post('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $request->input('id_token'),
                ]);
        } catch (\Throwable $e) {
            // Log the network/TLS failure without ever recording the submitted ID token.
            Log::warning('Google token verification request failed', [
                'exception_class' => $e::class,
                'exception_message' => mb_substr($e->getMessage(), 0, 300),
            ]);
            return $this->fail('Google sign-in could not reach Google. Please try again.', 503);
        }
        if (!$response->successful()) return $this->fail('Google sign-in token is invalid or expired.', 401);

        $claims = $response->json();
        $audClaim = $claims['aud'] ?? null;
        $audiences = is_array($audClaim) ? $audClaim : [$audClaim];
        $tokenExp = isset($claims['exp']) && is_numeric($claims['exp']) ? (int) $claims['exp'] : null;
        $checks = [
            'audience_matches' => in_array($clientId, $audiences, true),
            'issuer_valid' => in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true),
            'has_subject' => is_string($claims['sub'] ?? null) && $claims['sub'] !== '',
            'has_valid_email' => filter_var($claims['email'] ?? null, FILTER_VALIDATE_EMAIL) !== false,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'token_not_expired' => $tokenExp !== null && $tokenExp > time(),
        ];
        if (in_array(false, $checks, true)) {
            // Diagnostic metadata only. Never log the ID token or user's email.
            Log::warning('Google ID token rejected', $checks + [
                'token_audience' => $audClaim,
                'expected_audience' => $clientId,
                'token_issuer' => $claims['iss'] ?? null,
                'token_exp' => $tokenExp,
                'server_time' => time(),
            ]);
            if ($tokenExp !== null && $tokenExp <= time()) {
                return $this->fail('Google ID token expired. Please retry Google sign-in.', 401);
            }

            return $this->fail('Google account could not be verified for this app.', 401);
        }

        $email = strtolower($claims['email']);
        $googleId = $claims['sub'];
        $user = User::where('google_id', $googleId)->first();

        if (!$user) {
            $user = User::where('email', $email)->first();
            if ($user && $user->google_id && $user->google_id !== $googleId) {
                return $this->fail('This email is linked to another Google account.', 409);
            }

            if (!$user) {
                $user = User::create([
                    'name' => mb_substr($claims['name'] ?? strstr($email, '@', true), 0, 100),
                    'email' => $email,
                    'username' => $this->uniqueUsername($email),
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
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

        $token = $user->createToken($request->input('device_name', 'kjm-google-app'))->plainTextToken;
        return $this->ok(['user' => $this->userPayload($user), 'token' => $token], 'Logged in with Google');
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


