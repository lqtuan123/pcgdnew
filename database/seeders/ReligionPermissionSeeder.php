<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReligionPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo chức năng quản lý tôn giáo
        $function = [
            'code' => 'quan_ly_ton_giao',
            'name' => 'Quản lý tôn giáo',
            'description' => 'Chức năng quản lý danh mục tôn giáo',
            'category' => 'reference',
            'is_active' => true,
        ];

        DB::table('system_functions')->updateOrInsert(
            ['code' => $function['code']],
            $function
        );

        // Lấy function ID
        $functionRecord = DB::table('system_functions')->where('code', 'quan_ly_ton_giao')->first();
        if (!$functionRecord) {
            $this->command->error('Không thể tạo function quan_ly_ton_giao');
            return;
        }

        // Không cấp quyền cho role nào, chỉ tạo function
        // Quyền sẽ được cấp thủ công sau khi cần
        $this->command->info('Đã tạo function quan_ly_ton_giao thành công!');
        $this->command->info('Chưa cấp quyền cho role nào. Có thể cấp quyền sau qua giao diện quản lý.');
    }
}

