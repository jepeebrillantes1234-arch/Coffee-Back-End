<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('label', 50)->default('Home');
            $t->text('full_address');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->boolean('is_default')->default(false);
            $t->string('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('rider_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('vehicle_type', 50)->nullable();
            $t->string('plate_number', 20)->nullable();
            $t->string('license_no', 50)->nullable();
            $t->boolean('is_approved')->default(false);
            $t->boolean('is_available')->default(false);
            $t->decimal('current_lat', 10, 7)->nullable();
            $t->decimal('current_lng', 10, 7)->nullable();
            $t->timestamp('last_location_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('rider_profiles');
        Schema::dropIfExists('addresses');
    }
};
