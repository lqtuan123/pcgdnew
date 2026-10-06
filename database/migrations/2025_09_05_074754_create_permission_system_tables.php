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
        // Bảng quyền người dùng
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // admin, so, xa, truong, nhan_vien, khach
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->integer('level')->default(1); // Cấp độ quyền (1-6)
            $table->boolean('can_create_users')->default(false);
            $table->boolean('can_assign_permissions')->default(false);
            $table->boolean('can_manage_geo_permissions')->default(false);
            $table->timestamps();
        });

        // Bảng chức năng hệ thống
        Schema::create('system_functions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ho_gia_dinh, mam_non, tieu_hoc, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // education, household, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bảng phân quyền chức năng cho từng role
        Schema::create('role_function_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('user_roles')->onDelete('cascade');
            $table->foreignId('function_id')->constrained('system_functions')->onDelete('cascade');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            
            $table->unique(['role_id', 'function_id']);
        });

        // Bảng phân quyền chức năng cho user cụ thể (override role permissions)
        Schema::create('user_function_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('function_id')->constrained('system_functions')->onDelete('cascade');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'function_id']);
        });

        // Cập nhật bảng users để thêm role_id, is_active và các fields role-based
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('avatar')->constrained('user_roles')->onDelete('set null');
            $table->boolean('is_active')->default(true)->after('role_id');
            
            // Thêm role_level enum field
            $table->enum('role_level', ['admin', 'province', 'ward', 'school'])->nullable()->after('is_active');
            
            // Thêm các ID fields tương ứng với từng role level
            $table->foreignId('province_id')->nullable()->after('role_level')->constrained('geo_units')->onDelete('set null');
            $table->foreignId('ward_id')->nullable()->after('province_id')->constrained('geo_units')->onDelete('set null');
            $table->foreignId('school_id')->nullable()->after('ward_id')->constrained('schools')->onDelete('set null');
            
            // Thêm indexes để tối ưu performance
            $table->index(['role_id', 'is_active']);
            $table->index(['role_level', 'province_id']);
            $table->index(['role_level', 'ward_id']);
            $table->index(['role_level', 'school_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_function_permissions');
        Schema::dropIfExists('role_function_permissions');
        Schema::dropIfExists('system_functions');
        Schema::dropIfExists('user_roles');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['province_id']);
            $table->dropForeign(['ward_id']);
            $table->dropForeign(['school_id']);
            $table->dropIndex(['role_id', 'is_active']);
            $table->dropIndex(['role_level', 'province_id']);
            $table->dropIndex(['role_level', 'ward_id']);
            $table->dropIndex(['role_level', 'school_id']);
            $table->dropColumn(['role_id', 'is_active', 'role_level', 'province_id', 'ward_id', 'school_id']);
        });
        
    }
};