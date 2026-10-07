<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->unique()->after('email');
            $t->enum('role', ['customer','staff','admin','rider'])->default('customer')->after('password');
            $t->string('avatar')->nullable();
            $t->enum('status', ['active','blocked','inactive'])->default('active');
            $t->string('fcm_token')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['phone','role','avatar','status','fcm_token']);
        });
    }
};
