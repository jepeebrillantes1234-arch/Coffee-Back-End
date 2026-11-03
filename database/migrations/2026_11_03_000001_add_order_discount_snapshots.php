<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_request_id', 64)->nullable();
            $table->string('checkout_payload_hash', 64)->nullable();
            $table->decimal('membership_discount_amount', 10, 2)->default(0)->after('discount');
            $table->decimal('membership_discount_percent', 5, 2)->default(0)->after('membership_discount_amount');
            $table->decimal('promo_discount', 10, 2)->default(0)->after('membership_discount_percent');
            $table->decimal('first_order_discount', 10, 2)->default(0)->after('promo_discount');
            $table->unique(['user_id', 'checkout_request_id'], 'orders_user_checkout_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_user_checkout_request_unique');
            $table->dropColumn(['checkout_request_id', 'checkout_payload_hash', 'membership_discount_amount', 'membership_discount_percent', 'promo_discount', 'first_order_discount']);
        });
    }
};
