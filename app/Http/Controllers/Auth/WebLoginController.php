<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\AccountLoginSecurity;

class WebLoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($cred['email']))])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Maling email o password.'])->onlyInput('email');
        }

        $result = app(AccountLoginSecurity::class)->attemptPassword($user, $cred['password']);
        if ($result['status'] === 'locked') {
            $until = now()->addSeconds($result['retry_after_seconds'])->toIso8601String();
            return back()->withErrors(['email' => 'Too many failed login attempts. Please wait 5 minutes before trying again.'])
                ->with('lockout_until', $until)->onlyInput('email');
        }
        if ($result['status'] === 'password_reset_required') {
            return redirect()->route('password.request')
                ->with('password_reset_forced_email', $user->email)
                ->with('password_reset_required', 'A password reset is required to secure your account.');
        }
        if ($result['status'] !== 'success') {
            return back()->withErrors(['email' => 'Maling email o password.'])->onlyInput('email');
        }
        if ($user->status !== 'active') {
            return back()->withErrors(['email' => 'Maling email o password.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $role = $user->role;

        if ($role === 'admin') return redirect()->route('admin.dashboard');
        if ($role === 'staff') return redirect('/staff'); // gagawin sa Phase 5

        Auth::logout();
        return back()->withErrors(['email' => 'Para lang sa Admin at Staff ang web panel.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
