<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolManagementPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng quản lý trường học
        $function = [
            'code' => 'quan_ly_truong_hoc',
            'name' => 'Quản lý trường học',
            'description' => 'Chức năng quản lý trường học trong hệ thống',
            'category' => 'education',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'quan_ly_truong_hoc')->first();
        if (!$functionRecord) {
            return;
        }

        // Phân quyền cho các role
      
        $this->command->info('Đã tạo chức năng quan_ly_truong_hoc và phân quyền thành công!');
    }
}
