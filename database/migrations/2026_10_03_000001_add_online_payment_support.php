<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bagong status: awaiting_payment (hindi pa bayad, hindi pa nakikita ng staff)
        DB::statement("ALTER TABLE orders MODIFY status ENUM('awaiting_payment','pending','confirmed','preparing','ready','rider_assigned','picked_up','on_the_way','delivered','completed','rejected','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('payments', function (Blueprint $t) {
            $t->string('provider', 20)->nullable();
            $t->string('provider_session_id', 100)->nullable()->index();
            $t->string('provider_payment_id', 100)->nullable();
            $t->text('checkout_url')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'awaiting_payment')->update(['status' => 'cancelled']);
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','preparing','ready','rider_assigned','picked_up','on_the_way','delivered','completed','rejected','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('payments', function (Blueprint $t) {
            $t->dropColumn(['provider', 'provider_session_id', 'provider_payment_id', 'checkout_url']);
        });
    }
};
