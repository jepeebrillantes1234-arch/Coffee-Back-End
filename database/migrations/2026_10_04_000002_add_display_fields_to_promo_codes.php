<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bagong uri: bogo = Buy X Get Y
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE promo_codes MODIFY type ENUM('percent','fixed','bogo') NOT NULL");
        }

        Schema::table('promo_codes', function (Blueprint $t) {
            $t->string('title', 60)->nullable()->after('code');   // maliit na text sa taas
            $t->string('headline', 40)->nullable();                // malaking text (kung blangko: awtomatiko)
            $t->string('description', 100)->nullable();            // text sa ibaba
            $t->string('image')->nullable();                       // larawan na lalabas sa app
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('buy_qty')->default(1);        // Buy X
            $t->unsignedTinyInteger('free_qty')->default(1);       // Get Y (libre)
            $t->boolean('show_in_app')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $t) {
            $t->dropForeign(['product_id']);
            $t->dropColumn(['title', 'headline', 'description', 'image', 'product_id', 'buy_qty', 'free_qty', 'show_in_app', 'sort_order']);
        });
        DB::table('promo_codes')->where('type', 'bogo')->update(['type' => 'fixed']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE promo_codes MODIFY type ENUM('percent','fixed') NOT NULL");
        }
    }
};
