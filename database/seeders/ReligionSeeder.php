<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReligionSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Phật giáo',      'is_official' => true],
            ['name' => 'Công giáo',      'is_official' => true],
            ['name' => 'Tin Lành',       'is_official' => true],
            ['name' => 'Cao Đài',        'is_official' => true],
            ['name' => 'Phật giáo Hòa Hảo','is_official' => true],
            ['name' => 'Hồi giáo',       'is_official' => true],
            ['name' => 'Tịnh độ Cư sĩ',  'is_official' => false],
        ];

        foreach ($items as $row) {
            $row['slug'] = Str::slug($row['name']);
            $row['code'] = $row['code'] ?? null;

            Religion::updateOrCreate(
                ['name' => $row['name']],
                $row
            );
        }
    }
}
