<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ResidenceStatus;

class ResidenceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Đang cư trú',     // active / đang ở
            'Tạm vắng',        // tạm thời vắng mặt
            'Chuyển đi',       // đã chuyển nơi khác
            'Chờ xác minh',    // đang xác minh thông tin
            'Ngừng cư trú',    // đã hủy/không còn hiệu lực
        ];

        foreach ($items as $name) {
            ResidenceStatus::updateOrCreate(['name' => $name]);
        }
    }
}
