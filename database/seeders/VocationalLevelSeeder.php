<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VocationalLevel;

class VocationalLevelSeeder extends Seeder
{
    public function run(): void
    {
        // Tuỳ ý chỉnh sửa danh sách dưới đây cho phù hợp địa phương/quy định
        $levels = [
            'Sơ cấp',
            'Trung cấp',
            'Cao đẳng',
            'Đại học',
            'Chứng chỉ nghề',
            'Đào tạo dưới 3 tháng',
        ];

        foreach ($levels as $name) {
            VocationalLevel::updateOrCreate(
                ['name' => $name], // khóa duy nhất theo name để tránh trùng
                []                 // không có cột khác để cập nhật
            );
        }
    }
}
