<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm các cột mới
            $table->string('phone_number', 20)->nullable()->after('password');
            $table->string('google_id')->nullable()->after('phone_number');
            $table->string('avatar')->nullable()->after('google_id');
            $table->enum('role', ['admin', 'user'])->default('user')->after('avatar');
            
            // Cập nhật cột password để nullable
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'google_id', 'avatar', 'role']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
