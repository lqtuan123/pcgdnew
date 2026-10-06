<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SurveyPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng quản lý phiếu điều tra
        $function = [
            'code' => 'quan_ly_phieu_dieu_tra',
            'name' => 'Quản lý phiếu điều tra',
            'description' => 'Chức năng quản lý danh sách phiếu điều tra và các thành viên trong phiếu',
            'category' => 'household',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'quan_ly_phieu_dieu_tra')->first();
        if (!$functionRecord) {
            $this->command->error('Không thể tạo function quan_ly_phieu_dieu_tra');
            return;
        }

        // Tạo chức năng quản lý đối tượng điều tra
        $function2 = [
            'code' => 'quan_ly_doi_tuong_dieu_tra',
            'name' => 'Quản lý đối tượng điều tra',
            'description' => 'Chức năng quản lý danh sách đối tượng điều tra',
            'category' => 'household',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function2['code']],
            $function2
        );

        // Lấy function ID
        $functionRecord2 = DB::table('system_functions')->where('code', 'quan_ly_doi_tuong_dieu_tra')->first();
        if (!$functionRecord2) {
            $this->command->error('Không thể tạo function quan_ly_doi_tuong_dieu_tra');
            return;
        }

        $this->command->info('Đã tạo các chức năng điều tra thành công!');
        $this->command->info('- quan_ly_phieu_dieu_tra: Quản lý phiếu điều tra');
        $this->command->info('- quan_ly_doi_tuong_dieu_tra: Quản lý đối tượng điều tra');
        $this->command->info('Chưa cấp quyền cho role nào. Có thể cấp quyền sau qua giao diện quản lý.');
    }
}
