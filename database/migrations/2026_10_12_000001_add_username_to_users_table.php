<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
        });

        // Populate usernames for existing accounts without deleting data.
        foreach (DB::table('users')->select('id', 'email')->orderBy('id')->get() as $user) {
            $base = strtolower((string) strstr($user->email ?? '', '@', true));
            $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'customer';
            $base = substr($base, 0, 24);
            $candidate = $base;
            $suffix = 1;
            while (DB::table('users')->where('username', $candidate)->exists()) {
                $tail = (string) $suffix++;
                $candidate = substr($base, 0, 30 - strlen($tail)) . $tail;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
