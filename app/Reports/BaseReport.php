<?php

namespace App\Reports;
use App\Helpers\NumberFormatHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GeoService;
use App\Reports\Concerns\ExcelSheetHelpers;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
 
abstract class BaseReport
{
    use ExcelSheetHelpers;

    public function __construct(protected GeoService $geoService) {}
protected function setCellValue(Worksheet $sh, string $addr, $value, $num = 0, array $opt = []): void
{
    $decimals         = $opt['decimals']          ?? 0;     // số thập phân
    $blankIfZero      = $opt['blankIfZero']       ?? false; // 0 -> trống
    $wrap             = $opt['wrap']              ?? false; // xuống dòng
    $forceAlign       = $opt['align']             ?? null;  // ép căn lề
    $hideZeroByFormat = $opt['hideZeroByFormat']  ?? false; // ẩn 0 bằng format
    $wrapFormulaIfZero= $opt['wrapFormulaIfZero'] ?? false; // bọc công thức IF(...)=0,"",...
//  $value ="abc";
    // Công thức?
    $isFormula = is_string($value) && $value !== '' && $value[0] === '=';

    // Numeric? (kể cả chuỗi số), nhưng không phải công thức
    $isNumeric = !$isFormula && is_numeric($value);

    // Rỗng thực sự (null hoặc chuỗi rỗng) -> clear cell
    if ($value === null || ($value === '' && !$isNumeric)) {
        $sh->setCellValue($addr, null);
        $sh->getStyle($addr)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText($wrap);
        return;
    }

    if ($isNumeric) {
        $f = (float)$value;
        if ($blankIfZero && abs($f) < 1e-12) {
            // 0 -> để trống
            $sh->setCellValue($addr, null);
        } else {
            // Ghi numeric thật sự
            $sh->setCellValueExplicit($addr, $f, DataType::TYPE_NUMERIC);

            // Định dạng số
            $fmt = ($decimals > 0)
                ? '#,##0.'.str_repeat('0', $decimals)
                : '#,##0';

            if ($hideZeroByFormat) {
                // Ẩn số 0 (kể cả khi giá trị = 0)
                $fmt = ($decimals > 0)
                    ? '#,##0.'.str_repeat('0',$decimals).';-#,##0.'.str_repeat('0',$decimals).';;@'
                    : '#,##0;-#,##0;;@';
            }
            $sh->getStyle($addr)->getNumberFormat()->setFormatCode($fmt);
        }
    } else {
        // Chuỗi / Công thức
        if ($isFormula && $blankIfZero && $wrapFormulaIfZero) {
            // Bọc công thức để 0 -> ""
            $raw = substr($value, 1); // bỏ dấu '=' đầu
            $value = '=IF((' . $raw . ')=0,"",(' . $raw . '))';
        }
        $sh->setCellValue($addr, (string)$value);
    }

    // Căn lề mặc định: số giữa, chữ trái (có thể override bằng 'align')
    $align = $sh->getStyle($addr)->getAlignment();
    $align->setVertical(Alignment::VERTICAL_CENTER)
          ->setHorizontal($forceAlign ?? ($isNumeric ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT))
          ->setWrapText($wrap);
}
    // protected function setCellValue(Worksheet $sh, string $addr, $value, $num = 0,array $opt = []): void
    // {
    //     $decimals    = $opt['decimals']    ?? 0;        // dạng số thập phân (nếu cần)
    //     $blankIfZero = $opt['blankIfZero'] ?? false;    // 0 -> để trống
    //     $wrap        = $opt['wrap']        ?? false;    // xuống dòng trong ô
    //     $forceAlign  = $opt['align']       ?? null;     // ép căn lề (nếu truyền vào)

    //     // Rỗng
    //     if ($value === null || (is_string($value) && trim($value) === '')) {
    //         $sh->setCellValue($addr, null);
    //         $sh->getStyle($addr)->getAlignment()
    //             ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //             ->setVertical(Alignment::VERTICAL_CENTER)
    //             ->setWrapText($wrap);
    //         return;
    //     }

    //     $isNumber = is_int($value) || is_float($value);

    //     if ($isNumber) {
 
    //         if ($blankIfZero && (float)$value == 0.0) {
    //             $sh->setCellValue($addr, null);
    //         } else {
    //               $sh->setCellValue($addr,$value);
    //             //  $sh->setCellValue($addr,NumberFormatHelper::format(  $value,$num) );
    //             // ghi số thật sự để Excel hiểu là numeric
    //             // $sh->setCellValueExplicit($addr, $value, DataType::TYPE_NUMERIC);

    //             // // format số (nếu cần thập phân)
    //             // $fmt = ($decimals > 0) ? '#,##0.'.str_repeat('0',$decimals) : '#,##0';
    //             // $sh->getStyle($addr)->getNumberFormat()->setFormatCode($fmt);
    //         }
    //     } else {
    //         // Chuỗi (kể cả công thức)
    //         $sh->setCellValue($addr, (string)$value);
    //     }

    //     // Căn lề: số ở giữa, chữ bên trái (có thể override bằng align)
    //     $align = $sh->getStyle($addr)->getAlignment();
    //     $align->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setHorizontal($forceAlign ?? ($isNumber
    //                 ? Alignment::HORIZONTAL_CENTER
    //                 : Alignment::HORIZONTAL_LEFT))
    //         ->setWrapText($wrap);
    // }
    protected function hasMergedRange(Worksheet $sh, string $range): bool
    {
        $range = strtoupper($range);
        foreach ($sh->getMergeCells() as $m) {
            if (strtoupper($m) === $range) return true;
        }
        return false;
    }
    protected function safeMerge(Worksheet $sh, string $range): void
    {
        if (!$this->hasMergedRange($sh, $range)) {
            try { $sh->mergeCells($range); } catch (\Throwable $e) {}
        }
    }
    protected function nextCol(string $col): string
    {
        $i = Coordinate::columnIndexFromString($col) + 1;
        return Coordinate::stringFromColumnIndex($i);
    }

    protected function mergeNameCellsRange(Worksheet $sh, int $rowFrom, int $rowTo): void
    {
        for ($r = $rowFrom; $r <= $rowTo; $r++) {
            $this->safeMerge($sh, "B{$r}:D{$r}");
            $sh->getStyle("B{$r}:D{$r}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }
    }
    protected function parseAcademic(string $year): array
    {
        if (preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $year, $m)) {
            return [(int)$m[1], (int)$m[2]];
        }
        $y = (int)date('Y');
        return [$y, $y+1];
    }

    /** Years for ageFrom..ageTo at startYear (desc) */
    protected function yearsFor(int $startYear, int $ageFrom, int $ageTo): array
    {
        $ys = [];
        for ($age=$ageFrom; $age <= $ageTo; $age++) $ys[] = $startYear - $age;
        rsort($ys); return $ys;
    }

    /** Áp bộ lọc geo “nơi cư trú” vào query forms (f.* theo scheme) */
    protected function applyGeoFilter($qb, string $scheme, array $geo): void
    {
        if ($scheme==='old') {
            if (!empty($geo['province_id'])) $qb->where('f.old_province_id', (int)$geo['province_id']);
            if (!empty($geo['district_id'])) $qb->where('f.old_district_id', (int)$geo['district_id']);
            if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     (int)$geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $qb->where('f.new_province_id', (int)$geo['province_id']);
            if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     (int)$geo['ward_id']);
        }
        if (!empty($geo['village_id'])) $qb->where('f.village_id', (int)$geo['village_id']);
    }

    /** Tên geo hiển thị */
    protected function geoName(string $key, array $geo): string
    {
        return $this->geoService->geoNameFromId($geo[$key] ?? null) ?? '';
    }

    /**
     * Lấy tên file xuất từ template path
     * @param string $templatePath Path đến file template (VD: resource_path('excel/3.1.THCS.11.18Tuoi.xlsx'))
     * @param bool $includeTimestamp Có thêm timestamp vào tên file không (mặc định: true)
     * @return string Tên file để xuất (VD: '3.1.THCS.11.18Tuoi_20241017_143022.xlsx')
     */
    protected function getExportFilename(string $templatePath, bool $includeTimestamp = true): string
    {
        // Lấy basename từ path
        $filename = basename($templatePath);
        
        // Nếu cần timestamp, thêm vào trước extension
        if ($includeTimestamp) {
            $pathInfo = pathinfo($filename);
            $name = $pathInfo['filename'];
            $ext = $pathInfo['extension'] ?? 'xlsx';
            $filename = $name . '_' . date('Ymd_His') . '.' . $ext;
        }
        
        return $filename;
    }
}
