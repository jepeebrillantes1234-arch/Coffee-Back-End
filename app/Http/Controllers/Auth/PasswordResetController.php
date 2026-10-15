<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends Controller
{
    private const CODE_TTL_MINUTES = 10;
    private const MAX_CODE_ATTEMPTS = 5;

    public function requestForm()
    {
        return view('auth.forgot-password', [
            'email' => session('password_reset_email', ''),
        ]);
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        $email = strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email);
        $limitKey = 'web-password-reset-send:' . $emailHash;

        if (RateLimiter::tooManyAttempts($limitKey, 3)) {
            return back()->withErrors(['email' => 'Masyadong maraming request. Subukan ulit pagkalipas ng isang minuto.'])->withInput();
        }
        RateLimiter::hit($limitKey, 60);

        session(['password_reset_email' => $email]);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // Keep the response and screen the same whether the account exists or not.
        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $cacheKey = $this->codeCacheKey($email);
            Cache::put($cacheKey, [
                'hash' => Hash::make($code),
                'attempts' => 0,
            ], now()->addMinutes(self::CODE_TTL_MINUTES));

            try {
                Mail::raw(
                    "Your KJM Coffee Shop password reset code is: {$code}\n\nThis code expires in 10 minutes. If you did not request a password reset, ignore this email.",
                    function ($message) use ($email) {
                        $message->to($email)->subject('KJM Coffee Shop password reset code');
                    },
                );
            } catch (Throwable $e) {
                Cache::forget($cacheKey);
                Log::error('Password reset code email could not be sent', [
                    'exception_class' => $e::class,
                ]);

                return back()->withErrors(['email' => 'Hindi naipadala ang code. Suriin ang SMTP settings at subukan muli.'])->withInput();
            }
        }

        return redirect()->route('password.request')->with(
            'status',
            'Kung rehistrado ang email, may ipinadalang 6-digit code. Ilagay ito at ang bago mong password.',
        );
    }

    public function changeEmail(Request $request)
    {
        $email = session('password_reset_email');
        if (is_string($email) && $email !== '') {
            Cache::forget($this->codeCacheKey($email));
        }
        $request->session()->forget('password_reset_email');

        return redirect()->route('password.request');
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($data['email']));
        $cacheKey = $this->codeCacheKey($email);
        $entry = Cache::get($cacheKey);

        if (!is_array($entry) || !isset($entry['hash'])) {
            return back()->withErrors(['code' => 'Wala, mali, o expired na ang code. Humingi ng bagong code.']);
        }

        if (($entry['attempts'] ?? 0) >= self::MAX_CODE_ATTEMPTS) {
            Cache::forget($cacheKey);
            return back()->withErrors(['code' => 'Naubos na ang attempts. Humingi ng bagong verification code.']);
        }

        if (!Hash::check($data['code'], $entry['hash'])) {
            $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $entry, now()->addMinutes(self::CODE_TTL_MINUTES));
            return back()->withErrors(['code' => 'Mali ang verification code. Suriin ang email at subukan muli.']);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            Cache::forget($cacheKey);
            return back()->withErrors(['code' => 'Hindi ma-verify ang request. Humingi ng bagong code.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();
        $user->tokens()->delete();
        event(new PasswordReset($user));

        Cache::forget($cacheKey);
        $request->session()->forget('password_reset_email');

        return redirect()->route('password.reset.done');
    }

    private function codeCacheKey(string $email): string
    {
        return 'web-password-reset-code:' . hash('sha256', strtolower($email));
    }

    // Existing link-based password reset routes remain available.
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
        return response('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password updated</title><body style="font-family:Arial,sans-serif;max-width:520px;margin:12vh auto;padding:24px;color:#352019"><h1>Password updated</h1><p>Your password has been changed. Return to KJM Coffee and log in with your new password.</p><p><a href="/login">Go to login</a></p></body></html>');
    }
}
