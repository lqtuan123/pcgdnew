<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EthnicityPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng quản lý dân tộc
        $function = [
            'code' => 'quan_ly_dan_toc',
            'name' => 'Quản lý dân tộc',
            'description' => 'Chức năng quản lý danh mục dân tộc',
            'category' => 'reference',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'quan_ly_dan_toc')->first();
        if (!$functionRecord) {
            $this->command->error('Không thể tạo function quan_ly_dan_toc');
            return;
        }

        // Không cấp quyền cho role nào, chỉ tạo function
        // Quyền sẽ được cấp thủ công sau khi cần
        $this->command->info('Đã tạo function quan_ly_dan_toc thành công!');
        $this->command->info('Chưa cấp quyền cho role nào. Có thể cấp quyền sau qua giao diện quản lý.');
    }
}

