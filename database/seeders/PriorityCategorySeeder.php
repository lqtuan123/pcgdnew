<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PriorityCategory;

class PriorityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Con liệt sĩ',
            'Con thương binh nặng 1/4',
            'Con thương binh 2/4',
            'Con thương binh 3/4',
            'Con thương binh 4/4',
            'Mồ côi cha mẹ',
            'Hộ nghèo',
            'Hộ cận nghèo',
            'Kinh vùng cao',
            'Con dân tộc vùng cao',
            'Con dân tộc vùng thấp',
            'Bị chất độc da cam',
            'Con bệnh binh 1/4',
            'Con bệnh binh 2/4',
            'Con bệnh binh 3/4',
            'Con bệnh binh 4/4',
            'Bộ đội xuất ngũ',
        ];

        foreach ($names as $name) {
            PriorityCategory::firstOrCreate(['name' => $name]);
        }
    }
}
