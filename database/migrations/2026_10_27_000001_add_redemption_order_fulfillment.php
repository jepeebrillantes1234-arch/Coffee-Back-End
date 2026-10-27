<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('reward_id')->constrained()->nullOnDelete();
            $table->enum('fulfillment_type', ['delivery', 'pickup'])->nullable()->after('order_id');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('fulfillment_type');
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['fulfillment_type', 'delivery_fee']);
        });
    }
};
