<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'phone'    => 'nullable|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create($data + ['role' => 'customer', 'status' => 'active']);
        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        return $this->ok(['user' => $user, 'token' => $token], 'Registered', 201);
    }

    // POST /api/v1/auth/login  (customer, rider, staff, admin)
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',   // email o phone
            'password' => 'required|string',
        ]);

        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->fail('Maling email/phone o password.', 401);
        }
        if ($user->status !== 'active') {
            return $this->fail('Hindi aktibo ang account na ito.', 403);
        }
        if ($user->role === 'rider' && !optional($user->riderProfile)->is_approved) {
            return $this->fail('Hindi pa naaprubahan ang rider account.', 403);
        }

        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        return $this->ok(['user' => $user, 'token' => $token], 'Logged in');
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
        return $this->ok($request->user());
    }

    // PUT /api/v1/profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'phone'    => 'sometimes|nullable|string|max:20|unique:users,phone,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'avatar'   => 'sometimes|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        return $this->ok($user->fresh(), 'Profile updated');
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

