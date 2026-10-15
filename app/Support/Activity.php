<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use App\Events\LiveUpdate;

class Activity
{
    public static function log(string $action, string $module, string $description = ''): void
    {
        $id = DB::table('activity_logs')->insertGetId([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
        LiveUpdate::dispatch('activity', $id);
    }
}
