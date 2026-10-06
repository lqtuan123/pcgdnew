<?php

namespace App\Services;

use App\Models\GdmnFinance;
use App\Models\GeoUnit;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;

class GdmnFinanceImportService
{
    /**
     * Import dữ liệu từ file Excel - đọc từ template format
     * Chỉ đọc cột E (amount) và F (note), không đọc thông tin tỉnh/xã/năm
     */
    public function import(string $uploadedPath, int $year, ?int $provinceId, int $wardId): array
    {
        // Tránh hết thời gian với file lớn
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);
        DB::disableQueryLog();

        // Đọc Excel
        $spreadsheet = IOFactory::load($uploadedPath);
        $sheet = $spreadsheet->getActiveSheet();

        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => []
        ];

        // Map các dòng dữ liệu với main_item và sub_item
        $rowMapping = [
            9 => ['main' => 1, 'sub' => null],
            10 => ['main' => 1, 'sub' => 1],
            11 => ['main' => 1, 'sub' => 2],
            12 => ['main' => 1, 'sub' => 3],
            13 => ['main' => 1, 'sub' => 4],
            14 => ['main' => 2, 'sub' => null],
            15 => ['main' => 3, 'sub' => null],
            16 => ['main' => 4, 'sub' => null],
            17 => ['main' => 5, 'sub' => null],
            18 => ['main' => 6, 'sub' => null],
            19 => ['main' => 6, 'sub' => 1],
            20 => ['main' => 6, 'sub' => 2],
            21 => ['main' => 6, 'sub' => 3],
            22 => ['main' => 7, 'sub' => null],
            23 => ['main' => 7, 'sub' => 1],
            24 => ['main' => 7, 'sub' => 2],
            25 => ['main' => 7, 'sub' => 3],
        ];

        foreach ($rowMapping as $rowNum => $mapping) {
            $stats['total']++;
            
            try {
                $mainItem = $mapping['main'];
                $subItem = $mapping['sub'];
                
                // Đọc số tiền từ cột E
                $amount = $this->parseAmount($sheet->getCell('E' . $rowNum)->getValue());
                
                // Đọc ghi chú từ cột F
                $note = $sheet->getCell('F' . $rowNum)->getValue();
                
                // Bỏ qua nếu không có số tiền
                if (empty($amount) || $amount === '0') {
                    continue;
                }
                
                // Tự động gán unit theo main_item
                $unit = GdmnFinance::UNITS[$mainItem] ?? 'Tr.đồng';

                // Create or Update
                $record = GdmnFinance::updateOrCreate(
                    [
                        'year' => $year,
                        'ward_id' => $wardId,
                        'main_item' => $mainItem,
                        'sub_item' => $subItem,
                    ],
                    [
                        'province_id' => $provinceId,
                        'amount' => $amount,
                        'unit' => $unit,
                        'note' => $note,
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }

            } catch (\Exception $e) {
                $stats['errors'][] = "Dòng {$rowNum}: {$e->getMessage()}";
            }
        }

        return $stats;
    }

    /**
     * Parse amount - chấp nhận cả dấu . và , cho thập phân
     */
    private function parseAmount($value): string
    {
        if (empty($value)) {
            return '0';
        }

        // Chuyển về string và trim
        $value = trim((string) $value);

        // Loại bỏ tất cả ký tự không phải số, dấu chấm, dấu phẩy
        $value = preg_replace('/[^\d.,]/', '', $value);

        return $value;
    }
}


