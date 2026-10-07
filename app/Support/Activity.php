<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class Activity
{
    public static function log(string $action, string $module, string $description = ''): void
    {
        DB::table('activity_logs')->insert([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
    }
}
