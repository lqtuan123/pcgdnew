<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SpecialCircumstance;

class SpecialCircumstanceSeeder extends Seeder
{
    public function run(): void
    {
        $list = [
            'Chuyển đi',
            'Chuyển đến',
            'Chết',
        ];

        foreach ($list as $name) {
            SpecialCircumstance::firstOrCreate(['name' => $name]);
        }
    }
}
