<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Existing home banners promoted offers, so preserve that behavior.
            $table->string('banner_type', 30)->default('promo');
            $table->string('destination', 30)->default('promos');
            $table->text('description')->nullable();
        });

        DB::table('banners')->whereNull('description')->update([
            'description' => DB::raw('subtitle'),
        ]);
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['banner_type', 'destination', 'description']);
        });
    }
};