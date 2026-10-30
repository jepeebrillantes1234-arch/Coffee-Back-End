<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('button_text', 30)->nullable()->after('description');
            $table->unsignedBigInteger('destination_id')->nullable()->after('destination');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['button_text', 'destination_id']);
        });
    }
};
