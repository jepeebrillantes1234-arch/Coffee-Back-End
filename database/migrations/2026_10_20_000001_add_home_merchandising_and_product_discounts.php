<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('discount_type', ['percent', 'fixed'])->nullable();
            $table->decimal('discount_value', 8, 2)->default(0);
            $table->timestamp('discount_starts_at')->nullable();
            $table->timestamp('discount_ends_at')->nullable();
            $table->boolean('discount_is_active')->default(false);
        });
        Schema::create('home_promos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
        Schema::create('home_promo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_promo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unique(['home_promo_id', 'product_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('home_promo_id')->nullable()->constrained('home_promos')->nullOnDelete();
            $table->string('home_promo_name')->nullable();
            $table->decimal('original_unit_price', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_promo_id');
            $table->dropColumn(['home_promo_name', 'original_unit_price']);
        });
        Schema::dropIfExists('home_promo_items');
        Schema::dropIfExists('home_promos');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'discount_type', 'discount_value', 'discount_starts_at', 'discount_ends_at', 'discount_is_active',
        ]));
    }
};
