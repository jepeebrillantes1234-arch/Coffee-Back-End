<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('member_id', 24)->unique();
            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');
            $table->unsignedInteger('points_balance')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 24)->default('paymongo');
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable();
            $table->string('payment_reference', 40)->unique();
            $table->string('checkout_url', 2048)->nullable();
            $table->decimal('amount', 10, 2)->default(100);
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['membership_id', 'status']);
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->enum('reward_type', ['free_product', 'discount', 'other'])->default('other');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->unsignedInteger('points_cost');
            $table->unsignedInteger('stock')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'points_cost']);
        });

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reward_name', 120);
            $table->text('reward_description')->nullable();
            $table->enum('reward_type', ['free_product', 'discount', 'other'])->default('other');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->unsignedInteger('points_spent');
            $table->string('code', 48)->unique();
            $table->string('idempotency_key', 64)->nullable();
            $table->enum('status', ['pending', 'claimed', 'cancelled', 'expired'])->default('pending');
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('redemption_id')->nullable()->constrained('reward_redemptions')->nullOnDelete();
            $table->enum('type', ['earned', 'redeemed', 'adjustment']);
            $table->integer('points');
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('membership_payments');
        Schema::dropIfExists('memberships');
    }
};