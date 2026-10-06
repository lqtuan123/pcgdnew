<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportConfigPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng cấu hình báo cáo
        $function = [
            'code' => 'cau_hinh_bao_cao',
            'name' => 'Cấu hình báo cáo',
            'description' => 'Quản lý cấu hình trạng thái các báo cáo (bắt buộc, không bắt buộc, không sử dụng)',
            'category' => 'config',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'report_config')->first();
        if (!$functionRecord) {
            $this->command->error('Không thể tạo function report_config');
            return;
        }

        // Không cấp quyền cho role nào, chỉ tạo function
        // Quyền sẽ được cấp thủ công sau khi cần
        $this->command->info('Đã tạo function report_config thành công!');
        $this->command->info('Chưa cấp quyền cho role nào. Có thể cấp quyền sau qua giao diện quản lý.');
    }
}
