<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_chat_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->string('client_message_id', 36)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unique(['conversation_id', 'client_message_id'], 'delivery_chat_message_client_uq');
        });
    }

    public function down(): void
    {
        if (\DB::table('delivery_chat_messages')->whereNotNull('attachment_path')->exists()) {
            throw new RuntimeException('Delivery chat attachments are retained and must be removed before rollback.');
        }

        Schema::table('delivery_chat_messages', function (Blueprint $table) {
            $table->dropUnique('delivery_chat_message_client_uq');
            $table->dropColumn(['client_message_id', 'attachment_path', 'attachment_name', 'attachment_mime']);
            $table->text('body')->nullable(false)->change();
        });
    }
};
