<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number', 25)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('order_type', ['delivery','pickup','walkin']);
            $t->enum('status', ['pending','confirmed','preparing','ready','rider_assigned',
                'picked_up','on_the_way','delivered','completed','rejected','cancelled'])->default('pending');
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('discount', 10, 2)->default(0);
            $t->decimal('delivery_fee', 10, 2)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->boolean('is_first_order')->default(false);
            $t->enum('payment_method', ['cash','cod','gcash','card'])->default('cash');
            $t->enum('payment_status', ['unpaid','paid','refunded'])->default('unpaid');
            $t->text('delivery_address')->nullable();
            $t->decimal('delivery_lat', 10, 7)->nullable();
            $t->decimal('delivery_lng', 10, 7)->nullable();
            $t->text('notes')->nullable();
            $t->string('reject_reason')->nullable();
            $t->timestamps();
            $t->index(['status','order_type']);
            $t->index('created_at');
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('product_name', 150); // snapshot
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 10, 2);
            $t->decimal('total_price', 10, 2);
            $t->string('notes')->nullable();
        });
        Schema::create('order_item_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->string('option_name', 100);
            $t->decimal('extra_price', 8, 2)->default(0);
        });
        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('status', 30);
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('promo_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('promo_id')->constrained('promo_codes')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->decimal('discount_amount', 10, 2);
            $t->timestamp('used_at')->useCurrent();
        });
    }
    public function down(): void {
        foreach (['promo_usages','order_status_logs','order_item_options','order_items','orders'] as $x)
            Schema::dropIfExists($x);
    }
};
