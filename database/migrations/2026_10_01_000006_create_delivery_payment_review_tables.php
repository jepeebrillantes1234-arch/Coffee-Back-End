<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['assigned','accepted','picked_up','on_the_way','delivered','declined'])->default('assigned');
            $t->decimal('distance_km', 8, 2)->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('picked_up_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->string('proof_photo')->nullable();
            $t->timestamps();
        });
        Schema::create('rider_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('speed', 6, 2)->nullable();
            $t->timestamp('recorded_at')->useCurrent();
            $t->index(['delivery_id','recorded_at']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('method', 20);
            $t->decimal('amount', 10, 2);
            $t->string('reference_no')->nullable();
            $t->enum('status', ['pending','paid','failed','refunded'])->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('rating'); // 1-5
            $t->text('comment')->nullable();
            $t->boolean('is_visible')->default(true);
            $t->text('admin_reply')->nullable();
            $t->timestamps();
        });
        // Hindi "notifications" para hindi mag-conflict sa Laravel default
        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('message');
            $t->string('type', 30)->nullable();
            $t->json('data')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 50);
            $t->string('module', 50);
            $t->text('description')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        foreach (['activity_logs','app_notifications','reviews','payments','rider_locations','deliveries'] as $x)
            Schema::dropIfExists($x);
    }
};
