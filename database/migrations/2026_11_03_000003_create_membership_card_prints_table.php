<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_card_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('print_number');
            $table->timestamp('printed_at');
            $table->timestamps();
            $table->unique(['membership_id', 'print_number'], 'mcard_print_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_card_prints');
    }
};