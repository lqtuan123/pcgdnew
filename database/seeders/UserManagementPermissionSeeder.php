<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserManagementPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng quản lý người dùng
        $function = [
            'code' => 'quan_ly_nguoi_dung',
            'name' => 'Quản lý người dùng',
            'description' => 'Chức năng quản lý người dùng trong hệ thống',
            'category' => 'system',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'quan_ly_nguoi_dung')->first();
        if (!$functionRecord) {
            return;
        }

       
    }
}

