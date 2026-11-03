<?php

namespace App\Http\Middleware;

use App\Models\StaffShift;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveStaffShift
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->role === 'admin') return $next($request);

        $hasOpenShift = StaffShift::where('user_id', $user->id)->where('status', 'open')->exists();
        if (!$hasOpenShift) {
            return redirect()->route('staff.shift')->with('error', 'Open a shift with a starting cash fund before accessing the staff dashboard.');
        }

        return $next($request);
    }
}
