<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatisticalFormulaPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng công thức thống kê
        $function = [
            'code' => 'cong_thuc_thong_ke',
            'name' => 'Công thức thống kê',
            'description' => 'Quản lý và cấu hình các công thức thống kê cho báo cáo',
            'category' => 'config',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'cong_thuc_thong_ke')->first();
        if (!$functionRecord) {
            $this->command->error('Không thể tạo function cong_thuc_thong_ke');
            return;
        }

        // Không cấp quyền cho role nào, chỉ tạo function
        // Quyền sẽ được cấp thủ công sau khi cần
        $this->command->info('Đã tạo function cong_thuc_thong_ke thành công!');
        $this->command->info('Chưa cấp quyền cho role nào. Có thể cấp quyền sau qua giao diện quản lý.');
    }
}
