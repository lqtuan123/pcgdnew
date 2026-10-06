<?php

namespace Database\Seeders;

use App\Models\Issuer;
use Illuminate\Database\Seeder;

class IssuerSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'UBND-TINH',   'name' => 'Ủy ban Nhân dân Tỉnh'],
            ['code' => 'SO-GD',       'name' => 'Sở Giáo dục và Đào tạo'],
            ['code' => 'PHONG-GD',    'name' => 'Phòng Giáo dục và Đào tạo'],
            ['code' => 'TRUONG-THPT', 'name' => 'Trường THPT'],
        ];

        foreach ($items as $row) {
            Issuer::updateOrCreate(
                ['name' => $row['name']],
                $row
            );
        }
    }
}
