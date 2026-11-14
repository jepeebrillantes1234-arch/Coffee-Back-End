<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionManagementController extends Controller
{
    public function invalidateAllWebSessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return back()->withErrors([
                'sessions' => 'Global web-session logout is available only when the database session driver is active.',
            ]);
        }

        $connection = config('session.connection') ?: config('database.default');
        $table = config('session.table');

        if (!$table || !Schema::connection($connection)->hasTable($table)) {
            return back()->withErrors([
                'sessions' => 'The configured database session table is unavailable. No sessions were changed.',
            ]);
        }

        DB::connection($connection)->table($table)->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with(
            'status',
            'All active web sessions were signed out. Please log in again.',
        );
    }
}
