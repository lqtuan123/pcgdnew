<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Chức năng quản lý học sinh mầm non
        $function1 = [
            'code' => 'quan_ly_hoc_sinh_mam_non',
            'name' => 'Quản lý học sinh mầm non',
            'description' => 'Chức năng quản lý danh sách học sinh mầm non và thông tin học tập',
            'category' => 'education',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(['code' => $function1['code']], $function1);

        // Chức năng quản lý học sinh trái tuyến
        $function2 = [
            'code' => 'quan_ly_hoc_sinh_trai_tuyen',
            'name' => 'Quản lý học sinh trái tuyến',
            'description' => 'Chức năng quản lý danh sách học sinh trái tuyến và chuyển lớp',
            'category' => 'education',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(['code' => $function2['code']], $function2);

        // Chức năng lên lớp học sinh trái tuyến
        $function3 = [
            'code' => 'len_lop_hoc_sinh_trai_tuyen',
            'name' => 'Lên lớp học sinh trái tuyến',
            'description' => 'Chức năng thực hiện lên lớp cho học sinh trái tuyến',
            'category' => 'education',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(['code' => $function3['code']], $function3);

        // Chức năng thực hiện lên lớp
        $function4 = [
            'code' => 'thuc_hien_len_lop',
            'name' => 'Thực hiện lên lớp',
            'description' => 'Chức năng thực hiện lên lớp cho học sinh',
            'category' => 'education',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(['code' => $function4['code']], $function4);
    }
}
