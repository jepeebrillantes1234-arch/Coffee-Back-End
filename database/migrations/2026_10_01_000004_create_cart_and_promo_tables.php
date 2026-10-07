<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('unit_price', 10, 2);
            $t->string('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('cart_item_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('option_id')->constrained('product_options')->cascadeOnDelete();
        });
        Schema::create('promo_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->enum('type', ['percent','fixed']);
            $t->decimal('value', 10, 2);
            $t->decimal('min_order', 10, 2)->default(0);
            $t->decimal('max_discount', 10, 2)->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('per_user_limit')->default(1);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['settings','promo_codes','cart_item_options','cart_items','carts'] as $x)
            Schema::dropIfExists($x);
    }
};
