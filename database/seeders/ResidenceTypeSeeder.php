<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ResidenceType;

class ResidenceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code'=>'KT1','name'=>'Thường trú',          'is_phai_pho_cap'=>true],
            ['code'=>'KT3','name'=>'Tạm trú',             'is_phai_pho_cap'=>true],
            ['code'=>'KT2','name'=>'Tạm trú ngắn hạn',    'is_phai_pho_cap'=>false],
            ['code'=>'KT4','name'=>'Lưu trú (ngắn hạn)',  'is_phai_pho_cap'=>false],
        ];

        foreach ($items as $it) {
            ResidenceType::updateOrCreate(
                ['code' => $it['code']],
                ['name' => $it['name'], 'is_phai_pho_cap' => $it['is_phai_pho_cap']]
            );
        }
    }
}
