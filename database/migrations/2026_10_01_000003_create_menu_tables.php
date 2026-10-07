<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->decimal('base_price', 10, 2);
            $t->string('image')->nullable();
            $t->boolean('is_available')->default(true);
            $t->decimal('avg_rating', 3, 2)->default(0);
            $t->timestamps();
            $t->index('name');
        });
        Schema::create('product_option_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100); // Size, Sugar, Ice, Milk, Add-ons
            $t->boolean('is_required')->default(false);
            $t->boolean('is_multiple')->default(false);
            $t->unsignedTinyInteger('max_select')->default(1);
            $t->timestamps();
        });
        Schema::create('product_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('product_option_groups')->cascadeOnDelete();
            $t->string('name', 100);
            $t->decimal('extra_price', 8, 2)->default(0);
            $t->boolean('is_available')->default(true);
            $t->timestamps();
        });
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id','product_id']);
        });
    }
    public function down(): void {
        foreach (['favorites','product_options','product_option_groups','products','categories'] as $x)
            Schema::dropIfExists($x);
    }
};
