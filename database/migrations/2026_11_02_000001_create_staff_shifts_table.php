<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->decimal('opening_fund', 10, 2);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('cash_sales', 10, 2)->nullable();
            $table->decimal('online_payments', 10, 2)->nullable();
            $table->decimal('cash_refunds', 10, 2)->nullable();
            $table->decimal('cash_deductions', 10, 2)->nullable();
            $table->string('deduction_reason', 255)->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('actual_cash', 10, 2)->nullable();
            $table->decimal('cash_difference', 10, 2)->nullable();
            $table->string('reconciliation_status', 16)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
    }
};
