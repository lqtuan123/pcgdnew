<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DisabilityCategory;

class DisabilityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code'=>'VAN_DONG',    'name'=>'Khuyết tật vận động'],
            ['code'=>'NGHE_NOI',    'name'=>'Khuyết tật nghe, nói'],
            ['code'=>'NHIN',        'name'=>'Khuyết tật nhìn'],
            ['code'=>'THAN_KINH',   'name'=>'Khuyết tật thần kinh, tâm thần'],
            ['code'=>'TRI_TUE',     'name'=>'Khuyết tật trí tuệ'],
            ['code'=>'HOC_TAP',     'name'=>'Khuyết tật học tập'],
            ['code'=>'TU_KY',       'name'=>'Tự kỷ'],
            ['code'=>'KHAC',        'name'=>'Khuyết tật khác'],
        ];

        foreach ($items as $i => $it) {
            DisabilityCategory::updateOrCreate(
                ['code' => $it['code']],
                ['name' => $it['name'], 'sort_order' => $i, 'is_active' => true]
            );
        }
    }
}
