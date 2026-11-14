<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders', 'id', 'refund_req_order_fk')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments', 'id', 'refund_req_payment_fk')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id', 'refund_req_user_fk')->restrictOnDelete();
            $table->string('reason', 50);
            $table->text('explanation');
            $table->string('status', 32)->default('pending')->index('refund_req_status_idx');
            $table->string('source', 32)->default('customer');
            $table->unsignedBigInteger('requested_amount_cents')->nullable();
            $table->unsignedBigInteger('approved_amount_cents')->nullable();
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->text('decision_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'refund_req_reviewer_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status'], 'refund_req_order_status_idx');
            $table->index(['user_id', 'created_at'], 'refund_req_user_date_idx');
        });

        Schema::create('refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->constrained('refund_requests', 'id', 'refund_attempt_request_fk')->restrictOnDelete();
            $table->string('provider_refund_id', 100)->nullable()->unique('refund_attempt_provider_id_uq');
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 24)->default('pending')->index('refund_attempt_status_idx');
            $table->string('idempotency_key', 64)->unique('refund_attempt_idem_key_uq');
            $table->timestamp('idempotency_key_created_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('provider_status', 24)->nullable();
            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'refund_attempt_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['refund_request_id', 'created_at'], 'refund_attempt_request_date_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','expired','refunded','partially_refunded') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('unpaid','paid','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid'");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 32)->default('pending')->change();
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_status', 32)->default('unpaid')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('refund_requests')->exists() || DB::table('refund_attempts')->exists()) {
            throw new \RuntimeException('Refund history is financial audit data and cannot be removed by rolling back this migration.');
        }
        if (DB::table('payments')->where('status', 'partially_refunded')->exists()
            || DB::table('orders')->where('payment_status', 'partially_refunded')->exists()) {
            throw new \RuntimeException('Cannot remove partially_refunded payment status while records use it.');
        }

        Schema::dropIfExists('refund_attempts');
        Schema::dropIfExists('refund_requests');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid'");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_status', 20)->default('unpaid')->change();
            });
        }
    }
};
