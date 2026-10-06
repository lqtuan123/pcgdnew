<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cập nhật các user có role_level = 'admin' thành null
        DB::table('users')->where('role_level', 'admin')->update(['role_level' => null]);
        
        // Cập nhật enum role_level để bỏ 'admin'
        DB::statement("ALTER TABLE users MODIFY COLUMN role_level ENUM('province', 'ward', 'school') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Khôi phục enum role_level bao gồm 'admin'
        DB::statement("ALTER TABLE users MODIFY COLUMN role_level ENUM('admin', 'province', 'ward', 'school') NULL");
    }
};