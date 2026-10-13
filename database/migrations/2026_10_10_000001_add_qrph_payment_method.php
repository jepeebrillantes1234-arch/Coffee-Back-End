<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cash','cod','gcash','qrph','card') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (DB::table('orders')->where('payment_method', 'qrph')->exists()) {
            throw new \RuntimeException('Cannot remove QRPh while QRPh orders exist.');
        }
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cash','cod','gcash','card') NOT NULL DEFAULT 'cash'");
    }
};