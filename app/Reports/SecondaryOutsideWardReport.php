<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Services\ReportFormatService;
use App\Services\GeoService;

class SecondaryOutsideWardReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // 1) Đầu vào + cấu hình
        $year   = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]   = array_map('intval', explode('-', $year));
        $scheme = (string) $req->input('scheme', 'old');
        $geo    = (array)  $req->input('geo', []);
        $cfg    = $this->templateCfg();

        // 2) Tuổi 10..18 -> năm sinh
        $ages = range(10, 18);
        $ageYear = [];
        foreach ($ages as $a) $ageYear[$a] = $y1 - $a;

        // 3) Dữ liệu (mỗi dòng = 1 trường)
        [$rows, $meta] = $this->queryBySchoolOutsideWard($year, $scheme, $geo, $ageYear);
        $rowCount = count($rows);

        // 4) Nạp template & header
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        if (!empty($cfg['header']['province'])) $this->setCellValue($sh,$cfg['header']['province'], $meta['province'] ?? '');
        if (!empty($cfg['header']['ward']))     $this->setCellValue($sh,$cfg['header']['ward'],     $meta['ward'] ?? '');

        // Header tuổi + L6..L9 (nếu thiếu) -> map cột
        $colMap = $this->ensureAgeGradeHeader(
            $sh,
            $cfg['headerRowAge'],
            $cfg['headerRowGrade'],
            $cfg['startCol'],
            $ages,
            [6,7,8,9],
            $cfg['totalColLabel'] ?? 'Tổng'
        );

        // 5) Chèn & copy dòng mẫu (giữ style/merge)
        $templateRow = $cfg['templateRow'];             // dòng mẫu đầu
        $startRow    = $templateRow;
        $need        = max(0, $rowCount - 1);           // có sẵn 1 dòng mẫu

        if ($need > 0) {
            $sh->insertNewRowBefore($templateRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $templateRow, $templateRow + $i, $cfg['lastCol']);
            }
        }

        // 6) Ghi dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($rows as $row) {
            // STT + tên trường (nếu template đang merge B..D thì merge lại cho chắc)
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh,'A'.$r, $stt);
            $this->setCellValue($sh,'B'.$r, $row->school_name);

            // Ô tuổi × lớp + tổng hàng
            $rowSum = 0;
            foreach ($ages as $a) {
                for ($g = 6; $g <= 9; $g++) {
                    $key = "a{$a}_g{$g}";
                    $val = (int)($row->$key ?? 0);
                    $rowSum += $val;
                    if (!empty($colMap[$a][$g])) {
                        $this->setCellValue($sh, $colMap[$a][$g].$r, $val ?: null);
                    }
                }
            }
            if (!empty($cfg['totalCol'])) {
                $this->setCellValue($sh, $cfg['totalCol'].$r, $rowSum ?: null);
            }
            $r++; $stt++;
        }

        // 7) Dòng "Cộng" (cộng THEO CỘT, ẩn 0)
        $firstDataRow = $startRow;
        $lastDataRow  = $r - 1;
        $sumRow       = $r;
        $this->safeMerge($sh, "B{$sumRow}:C{$sumRow}");
        $this->setCellValue($sh,'B'.$sumRow, 'Cộng');

        foreach ($ages as $a) {
            for ($g=6; $g<=9; $g++) {
                $col = $colMap[$a][$g] ?? null;
                if ($col) {
                    $sumExpr = sprintf('SUM(%s%d:%s%d)', $col, $firstDataRow, $col, $lastDataRow);
                    $this->setCellValue($sh, $col.$sumRow, sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr));
                }
            }
        }
        if (!empty($cfg['totalCol'])) {
            $tc = $cfg['totalCol'];
            $sumExpr = sprintf('SUM(%s%d:%s%d)', $tc, $firstDataRow, $tc, $lastDataRow);
            $this->setCellValue($sh, $tc.$sumRow, sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr));
        }
        $sumRange = sprintf('%s%d:%s%d', $cfg['startCol'], $sumRow, $cfg['lastCol'], $sumRow);
        $sh->getStyle($sumRange)->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');

        // 8) Viền + footer ngày tháng năm
        $this->drawThinBorders($sh, $firstDataRow, $sumRow, 'A', $cfg['lastCol']);
        // vị trí dòng ngày – điều chỉnh cell nếu template khác
        $this->setCellValue($sh, 'AC'.($sumRow + 2), $this->formatService->end_date($meta['ward'] ?? ''));
        $this->setCellValue($sh, 'F3', $this->formatService->top_date());

        // 9) Xuất
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Query: HS cư trú trong địa bàn nhưng học TRƯỜNG NGOÀI địa bàn; chỉ THCS (L6..L9) */
    protected function queryBySchoolOutsideWard(string $academic, string $scheme, array $geo, array $ageYear)
    {
        // person_educations mới nhất trong năm
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
            ->where('pe1.academic_year', $academic);

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatest, 'pe', function($j){
                $j->on('pe.person_id','=','d.person_id')->where('pe.rn', 1);
            })
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('schools as s', 's.id', '=', 'pe.school_id')
            ->where('d.is_deleted', false)
            ->whereNotNull('pe.school_id')
            ->whereNotNull('pe.education_block_id')
            ->where('d.academic_year', $academic)
            ->whereIn('p.dob_year', array_values($ageYear));

        // chỉ THCS: L6..L9, loại mầm non
        $qb->whereRaw("LOWER(eb.name) NOT REGEXP '(tháng|tuổi|m[ăa]m\\s*non|mẫu\\s*giáo|nhà\\s*trẻ)'")
           ->whereBetween(DB::raw('CAST(eb.name AS UNSIGNED)'), [6,9]);

        // lọc cư trú theo geo (từ form khai báo)
        $this->applyGeoFilter($qb, $scheme, $geo);

        // trường “ngoài địa bàn” so với địa bàn cư trú
        $off = $this->schoolOutsideExpr($scheme, $geo);
        if ($off) $qb->whereRaw($off);

        // pivot theo tuổi × lớp
        $selects = ['s.id as school_id', DB::raw('COALESCE(pe.school_name_custom, s.name) as school_name')];
        foreach ($ageYear as $age => $year) {
            for ($g = 6; $g <= 9; $g++) {
                $alias = "a{$age}_g{$g}";
                $selects[] = DB::raw("
                    SUM(CASE WHEN p.dob_year = {$year}
                              AND CAST(eb.name AS UNSIGNED) = {$g}
                         THEN 1 ELSE 0 END) AS {$alias}
                ");
            }
        }

        $rows = $qb->select($selects)
                   ->groupBy('s.id', DB::raw('COALESCE(pe.school_name_custom, s.name)'))
                   ->orderBy(DB::raw('COALESCE(pe.school_name_custom, s.name)'))
                   ->get();

        $province = $this->geoService->geoNameFromId($geo['province_id'] ?? null) ?? '';
        $ward     = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';

        return [$rows, compact('province','ward')];
    }

    /** So sánh trường ngoài địa bàn – giống bản tiểu học */
    protected function schoolOutsideExpr(string $scheme, array $geo): ?string
    {
        if ($scheme === 'old') {
            if (!empty($geo['ward_id']))     return '(s.code LIKE "NGOAI_TINH%" OR (s.old_ward_id IS NOT NULL AND f.old_ward_id IS NOT NULL AND s.old_ward_id <> f.old_ward_id))';
            if (!empty($geo['district_id'])) return '(s.code LIKE "NGOAI_TINH%" OR (s.old_district_id IS NOT NULL AND f.old_district_id IS NOT NULL AND s.old_district_id <> f.old_district_id))';
            if (!empty($geo['province_id'])) return '(s.code LIKE "NGOAI_TINH%" OR (s.old_province_id IS NOT NULL AND f.old_province_id IS NOT NULL AND s.old_province_id <> f.old_province_id))';
        } else {
            if (!empty($geo['ward_id']))     return '(s.code LIKE "NGOAI_TINH%" OR (s.new_ward_id IS NOT NULL AND f.new_ward_id IS NOT NULL AND s.new_ward_id <> f.new_ward_id))';
            if (!empty($geo['province_id'])) return '(s.code LIKE "NGOAI_TINH%" OR (s.new_province_id IS NOT NULL AND f.new_province_id IS NOT NULL AND s.new_province_id <> f.new_province_id))';
        }
        return null;
    }

    /** Vẽ header tuổi + L6..L9 nếu trống, trả map [age][grade] => column */
    protected function ensureAgeGradeHeader(
        Worksheet $sh,
        int $ageRow,
        int $gradeRow,
        string $startCol,
        array $ages,
        array $grades,
        string $totalColLabel = 'Tổng'
    ): array {
        $map = [];
        $colIndex = Coordinate::columnIndexFromString($startCol);

        foreach ($ages as $age) {
            $first = $colIndex;
            foreach ($grades as $g) {
                $col  = Coordinate::stringFromColumnIndex($colIndex);
                $cell = $col.$gradeRow;
                if (($sh->getCell($cell)->getValue() ?? '') === '') {
                    $sh->setCellValue($cell, 'L'.$g);
                }
                $map[$age][$g] = $col;
                $colIndex++;
            }
            $firstCol = Coordinate::stringFromColumnIndex($first);
            $lastCol  = Coordinate::stringFromColumnIndex($colIndex - 1);
            $ageCell  = $firstCol.$ageRow;
            if (($sh->getCell($ageCell)->getValue() ?? '') === '') {
                $sh->mergeCells("{$firstCol}{$ageRow}:{$lastCol}{$ageRow}");

                $sh->setCellValue($ageCell, sprintf('%d Tuổi', $age));
                $sh->getStyle($addr)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                 
            }
        }

        // cột Tổng (nếu template chưa có sẵn)
        $totalCol = Coordinate::stringFromColumnIndex($colIndex);
        if (($sh->getCell($totalCol.$ageRow)->getValue() ?? '') === '') {
            $sh->setCellValue($totalCol.$ageRow, $totalColLabel);
        }
        return $map;
    }

    /** Config đúng theo template bạn gửi (điều chỉnh nếu khác) */
    protected function templateCfg(): array
    {
        return [
            'path'            => resource_path('excel/3.6.THCS.TK.HocNgoaiDiaBan.xlsx'), // đổi đúng tên file của bạn
            'header'          => ['province' => 'C1', 'ward' => 'C2'],
            'headerRowAge'    => 5,     // hàng “10 Tuổi … 18 Tuổi”
            'headerRowGrade'  => 6,     // hàng “L6..L9”
            'startCol'        => 'D',   // L6 của 10 tuổi
            'templateRow'     => 7,     // dòng mẫu đầu
            'lastCol'         => 'AN',  // cột cuối vùng bảng
            'totalCol'        => 'AN',  // cột “Tổng” trong template
            'totalColLabel'   => 'Tổng',
        ];
    }

    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
}
