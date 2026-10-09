<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('cash_collected_amount', 10, 2)->nullable()->after('proof_photo');
            $table->timestamp('cash_collected_at')->nullable()->after('cash_collected_amount');
            $table->timestamp('cash_remitted_at')->nullable()->after('cash_collected_at');
            $table->foreignId('cash_remitted_by')->nullable()->after('cash_remitted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['cash_remitted_by']);
            $table->dropColumn([
                'cash_collected_amount', 'cash_collected_at', 'cash_remitted_at', 'cash_remitted_by',
            ]);
        });
    }
};
