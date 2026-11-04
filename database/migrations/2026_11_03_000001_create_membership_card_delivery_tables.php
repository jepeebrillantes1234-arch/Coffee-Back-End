<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('membership_card_deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('requested_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $t->string('customer_name_snapshot',120); $t->string('customer_phone_snapshot',40)->nullable();
            $t->text('address_snapshot'); $t->string('street_snapshot')->nullable(); $t->string('barangay_snapshot')->nullable();
            $t->string('city_snapshot')->nullable(); $t->string('province_snapshot')->nullable(); $t->string('postal_code_snapshot',20)->nullable();
            $t->decimal('destination_latitude',10,7)->nullable(); $t->decimal('destination_longitude',10,7)->nullable();
            $t->text('delivery_notes_snapshot')->nullable(); $t->string('status',32)->default('pending')->index();
            $t->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('assigned_at')->nullable();
            $t->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('preparing_at')->nullable();
            $t->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('ready_at')->nullable();
            $t->timestamp('out_for_delivery_at')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->timestamps();
            $t->index(['rider_id','status']); $t->index(['user_id','created_at']); $t->unique('membership_id');
        });
        Schema::create('membership_card_delivery_locations', function (Blueprint $t) {
            $t->id(); $t->foreignId('membership_card_delivery_id');
            $t->foreign('membership_card_delivery_id', 'mcdloc_delivery_fk')->references('id')->on('membership_card_deliveries')->cascadeOnDelete();
            $t->foreignId('rider_id');
            $t->foreign('rider_id', 'mcdloc_rider_fk')->references('id')->on('users')->cascadeOnDelete(); $t->decimal('latitude',10,7);
            $t->decimal('longitude',10,7); $t->decimal('accuracy',8,2)->nullable(); $t->timestamp('recorded_at');
            $t->index(['membership_card_delivery_id','rider_id','recorded_at'],'membership_delivery_location_latest');
        });
    }
    public function down(): void { Schema::dropIfExists('membership_card_delivery_locations'); Schema::dropIfExists('membership_card_deliveries'); }
};
