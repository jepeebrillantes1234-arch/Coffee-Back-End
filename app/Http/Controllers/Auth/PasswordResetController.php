<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function show(string $token)
    {
        return view('auth.password-reset', ['token' => $token, 'email' => request('email', '')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('password.reset.done');
        }

        return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    public function done()
    {
        return response('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password updated</title><body style="font-family:Arial,sans-serif;max-width:520px;margin:12vh auto;padding:24px;color:#352019"><h1>Password updated</h1><p>Your password has been changed. Return to KJM Coffee and log in with your new password.</p></body></html>');
    }
}
