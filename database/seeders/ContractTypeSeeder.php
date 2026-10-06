<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContractType;

class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        $contractTypes = [
            [
                'name' => 'Hợp đồng lao động',
                'code' => 'LABOR_CONTRACT',
                'description' => 'Hợp đồng lao động theo quy định của Bộ luật Lao động'
            ],
            [
                'name' => 'Hợp đồng làm việc',
                'code' => 'WORKING_CONTRACT',
                'description' => 'Hợp đồng làm việc theo quy định của Luật Viên chức'
            ],
            [
                'name' => 'Biên chế',
                'code' => 'PERMANENT',
                'description' => 'Cán bộ, công chức, viên chức trong biên chế nhà nước'
            ],
            [
                'name' => 'Hợp đồng thời vụ',
                'code' => 'TEMPORARY',
                'description' => 'Hợp đồng thời vụ, hợp đồng ngắn hạn'
            ]
        ];

        foreach ($contractTypes as $contractType) {
            ContractType::create($contractType);
        }
    }
}
