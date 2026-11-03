<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_intent_id', 100)->nullable()->unique();
            $table->timestamp('qr_expires_at')->nullable();
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            $table->string('provider_intent_id', 100)->nullable()->unique();
            $table->timestamp('qr_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropUnique(['provider_intent_id']);
            $table->dropColumn(['provider_intent_id', 'qr_expires_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_intent_id']);
            $table->dropColumn(['provider_intent_id', 'qr_expires_at']);
        });
    }
};
