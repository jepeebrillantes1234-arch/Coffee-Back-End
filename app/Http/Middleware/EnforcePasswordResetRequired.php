<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordResetRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        // A locked-out account must still be able to explicitly sign out.
        if ($request->isMethod('POST') && $request->is('api/v1/auth/logout')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || !$user->password_reset_required) {
            return $next($request);
        }

        $message = 'A password reset is required before you can continue.';
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => ['security' => [
                    'status' => 'password_reset_required',
                    'retry_after_seconds' => 0,
                    'locked_until' => null,
                    'password_reset_required' => true,
                ]],
                'errors' => null,
            ], 429)->header('Retry-After', '0');
        }

        return redirect()->route('password.request')
            ->with('password_reset_forced_email', $user->email)
            ->with('password_reset_required', $message);
    }
}
