<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    // Gamit: ->middleware('role:admin,staff')
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        if (!in_array($user->role, $roles, true) || $user->status !== 'active') {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Forbidden'], 403)
                : abort(403, 'Wala kang pahintulot sa pahinang ito.');
        }

        return $next($request);
    }
}
