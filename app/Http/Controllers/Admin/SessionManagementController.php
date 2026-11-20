<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class SessionManagementController extends Controller
{
    public function logoutAllUsers(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return back()->withErrors([
                'global_logout' => 'Global logout requires the database session driver. No sessions or tokens were changed.',
            ]);
        }

        $connection = config('session.connection') ?: config('database.default');
        $sessionTable = config('session.table');
        $tokenModelClass = Sanctum::personalAccessTokenModel();
        $tokenModel = new $tokenModelClass();
        $tokenConnection = $tokenModel->getConnectionName() ?: config('database.default');

        if ($connection !== $tokenConnection) {
            return back()->withErrors([
                'global_logout' => 'Global logout requires sessions and API tokens to use the same database connection. No records were changed.',
            ]);
        }

        if (!$sessionTable || !Schema::connection($connection)->hasTable($sessionTable)
            || !Schema::connection($connection)->hasTable($tokenModel->getTable())) {
            return back()->withErrors([
                'global_logout' => 'A required authentication table is unavailable. No sessions or tokens were changed.',
            ]);
        }

        try {
            DB::connection($connection)->transaction(function () use ($connection, $sessionTable, $tokenModel) {
                $userIds = User::query()->whereIn('role', ['customer', 'rider'])->select('id');

                DB::connection($connection)->table($tokenModel->getTable())
                    ->where('tokenable_type', (new User())->getMorphClass())
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();

                DB::connection($connection)->table($sessionTable)->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'global_logout' => 'Global logout could not be completed. Check authentication status before trying again.',
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with(
            'status',
            'Admin and Staff browser sessions were signed out. Customer and Rider apps must sign in again.',
        );
    }

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
