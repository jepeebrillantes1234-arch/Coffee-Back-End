<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use App\Events\LiveUpdate;

class Activity
{
    public static function log(string $action, string $module, string $description = '', ?int $userId = null): void
    {
        $id = DB::table('activity_logs')->insertGetId([
            'user_id'     => $userId ?? auth()->id(),
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
        try {
            LiveUpdate::dispatch('activity', $id);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
