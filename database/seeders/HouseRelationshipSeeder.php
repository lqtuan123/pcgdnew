<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HouseRelationship;

class HouseRelationshipSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Chủ hộ',     'description' => 'Người đứng tên hộ gia đình'],
            ['name' => 'Vợ/chồng',   'description' => 'Quan hệ hôn nhân với chủ hộ'],
            ['name' => 'Con',        'description' => 'Con ruột/nuôi của chủ hộ'],
            ['name' => 'Cha',        'description' => 'Cha của chủ hộ'],
            ['name' => 'Mẹ',        'description' => 'Mẹ của chủ hộ'],
            ['name' => 'Ông/bà',     'description' => 'Ông/bà nội/ngoại'],
            ['name' => 'Anh/chị/em', 'description' => 'Anh/chị/em ruột'],
            ['name' => 'Cháu',       'description' => 'Cháu nội/ngoại'],
            ['name' => 'Khác',       'description' => 'Quan hệ khác'],
        ];

        foreach ($items as $it) {
            HouseRelationship::updateOrCreate(
                ['name' => $it['name']],
                ['description' => $it['description']]
            );
        }
    }
}
