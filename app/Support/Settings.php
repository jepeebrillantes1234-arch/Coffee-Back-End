<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class Settings
{
    public static function get(string $key, $default = null)
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    public static function all(): array
    {
        return DB::table('settings')->pluck('value', 'key')->all();
    }

    public static function set(string $key, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
