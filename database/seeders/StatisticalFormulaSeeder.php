<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StatisticalFormula;

class StatisticalFormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formulas = [
            [
                'report_detail_id' => 1,
                'criteria' => 'Trẻ 5 tuổi hoàn thành chương trình GDMN',
                'formula' => 'Từ dữ liệu năm trước',
                'description' => '- Dữ liệu cột 6 tuổi sẽ được lấy từ cột 5 tuổi thống kê năm trước
- Đồng thời tiêu chí "Trẻ hoàn thành chương trình GDMN" sẽ đếm những trẻ được tích cột hoàn thành chương trình GDMN',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'report_detail_id' => 2,
                'criteria' => 'Chuẩn trình độ đào tạo của GV',
                'formula' => 'Luật giáo dục 2019',
                'description' => 'Có bằng cử nhân thuộc ngành đào tạo GV trở lên',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'report_detail_id' => 2,
                'criteria' => 'Yêu cầu chuẩn nghề nghiệp',
                'formula' => 'Bao gồm GV hợp đồng',
                'description' => 'Xét yêu cầu chuẩn nghề nghiệp cho tất tả giáo viên, bao gồm giáo viên biên chế và hợp đồng',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'report_detail_id' => 3,
                'criteria' => 'Chuẩn trình độ đào tạo của GV',
                'formula' => 'Luật giáo dục 2019',
                'description' => 'Có bẳng cử nhân thuộc ngành đào tạo GV trở lên',
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($formulas as $formula) {
            StatisticalFormula::updateOrCreate(
                [
                    'report_detail_id' => $formula['report_detail_id'],
                    'criteria' => $formula['criteria'],
                ],
                $formula
            );
        }
    }
}
