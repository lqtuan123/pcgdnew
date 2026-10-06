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
            // Thêm is_active field nếu chưa tồn tại
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role_id');
            }
            
            // Thêm role_level enum field nếu chưa tồn tại
            if (!Schema::hasColumn('users', 'role_level')) {
                $table->enum('role_level', ['admin', 'province', 'ward', 'school'])->nullable()->after('is_active');
            }
            
            // Thêm các ID fields tương ứng với từng role level nếu chưa tồn tại
            if (!Schema::hasColumn('users', 'province_id')) {
                $table->foreignId('province_id')->nullable()->after('role_level')->constrained('geo_units')->onDelete('set null');
            }
            if (!Schema::hasColumn('users', 'ward_id')) {
                $table->foreignId('ward_id')->nullable()->after('province_id')->constrained('geo_units')->onDelete('set null');
            }
            if (!Schema::hasColumn('users', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('ward_id')->constrained('schools')->onDelete('set null');
            }
            
            // Thêm indexes để tối ưu performance (chỉ thêm nếu chưa tồn tại)
            if (!Schema::hasIndex('users', ['role_id', 'is_active'])) {
                $table->index(['role_id', 'is_active']);
            }
            if (!Schema::hasIndex('users', ['role_level', 'province_id'])) {
                $table->index(['role_level', 'province_id']);
            }
            if (!Schema::hasIndex('users', ['role_level', 'ward_id'])) {
                $table->index(['role_level', 'ward_id']);
            }
            if (!Schema::hasIndex('users', ['role_level', 'school_id'])) {
                $table->index(['role_level', 'school_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['ward_id']);
            $table->dropForeign(['school_id']);
            $table->dropIndex(['role_id', 'is_active']);
            $table->dropIndex(['role_level', 'province_id']);
            $table->dropIndex(['role_level', 'ward_id']);
            $table->dropIndex(['role_level', 'school_id']);
            $table->dropColumn(['is_active', 'role_level', 'province_id', 'ward_id', 'school_id']);
        });
    }
};
