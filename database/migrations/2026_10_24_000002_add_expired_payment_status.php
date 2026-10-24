<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending'");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('payments')->where('status', 'expired')->exists()) {
            throw new RuntimeException('Cannot remove expired payment status while expired records exist.');
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending'");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('payments', function (Blueprint $table) {
                $table->enum('status', ['pending','paid','failed','refunded'])->default('pending')->change();
            });
        }
    }
};
