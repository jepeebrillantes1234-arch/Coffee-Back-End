<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->unsignedTinyInteger('login_lockout_cycles')->default(0);
            $table->timestamp('login_locked_until')->nullable();
            $table->boolean('password_reset_required')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'failed_login_attempts',
                'login_lockout_cycles',
                'login_locked_until',
                'password_reset_required',
            ]);
        });
    }
};
