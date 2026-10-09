<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebLoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (!Auth::attempt($cred + ['status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Maling email o password.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $role = Auth::user()->role;

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
