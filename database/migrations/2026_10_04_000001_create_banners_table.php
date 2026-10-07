<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('title', 60)->nullable();      // maliit na text sa taas
            $t->string('headline', 40);               // malaking text
            $t->string('subtitle', 80)->nullable();   // text sa ibaba
            $t->string('image')->nullable();
            $t->enum('audience', ['all', 'new_customers'])->default('all');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
