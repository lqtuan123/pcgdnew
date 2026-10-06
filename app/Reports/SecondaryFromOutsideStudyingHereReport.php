<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Services\ReportFormatService;
use App\Services\GeoService;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
class SecondaryFromOutsideStudyingHereReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // ===== 1) Đầu vào
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old'); // old|new
        $geo      = (array)  $req->input('geo', []);
        $schoolId = (int) ($req->input('education.school_id') ?? $req->input('school_id') ?? 0) ?: null;

        // Tuổi 10..18 cho THCS
        $ages = range(10, 18);

        // ===== 2) Lấy raw theo (địa chỉ cư trú, năm sinh) + đếm L6..L9
        [$rowsRaw, $meta] = $this->offRouteSecondaryByAge($req);

        // ===== 3) Pivot về đơn vị cư trú (unit_name) × (tuổi, lớp 6..9)
        $useOld  = ($scheme === 'old');
        $wardCol = $useOld ? 'stay_ward_code_old'     : 'stay_ward_code_new';
        $provCol = $useOld ? 'stay_province_code_old' : 'stay_province_code_new';
        $distCol = $useOld ? 'stay_district_code_old' : null;

        $rowsColl   = collect($rowsRaw);
        $wardCodes  = $rowsColl->pluck($wardCol)->filter()->unique()->values();
        $provCodes  = $rowsColl->pluck($provCol)->filter()->unique()->values();
        $distCodes  = $distCol ? $rowsColl->pluck($distCol)->filter()->unique()->values() : collect();

        $wardNames = DB::table('geo_units')->whereIn('code', $wardCodes)->pluck('name','code');
        $provNames = DB::table('geo_units')->whereIn('code', $provCodes)->pluck('name','code');
        $distNames = $distCol
            ? DB::table('geo_units')->whereIn('code', $distCodes)->pluck('name','code')
            : collect();

        $pivot = [];
        foreach ($rowsColl as $r) {
            $wardCode = $r->{$wardCol} ?? null;
            $provCode = $r->{$provCol} ?? null;
            $distCode = $distCol ? ($r->{$distCol} ?? null) : null;

            $parts = [];
            if ($wardCode && isset($wardNames[$wardCode])) $parts[] = $wardNames[$wardCode];
            if ($useOld && $distCode && isset($distNames[$distCode])) $parts[] = $distNames[$distCode];
            if ($provCode && isset($provNames[$provCode])) $parts[] = $provNames[$provCode];

            $unitName = trim(implode(', ', $parts)) ?: 'Không rõ địa chỉ';

            if (!isset($pivot[$unitName])) {
                $rowInit = ['unit_name' => $unitName];
                foreach ($ages as $a) {
                    for ($g = 6; $g <= 9; $g++) {
                        $rowInit["a{$a}_g{$g}"] = 0;
                    }
                }
                $pivot[$unitName] = (object) $rowInit;
            }

            // tuổi = y1 - năm sinh
            $y   = (int)($r->y ?? 0);
            $age = $y1 - $y;
            if (!in_array($age, $ages, true)) continue;

            for ($g = 6; $g <= 9; $g++) {
                $src = "off_g{$g}";
                $dst = "a{$age}_g{$g}";
                $pivot[$unitName]->$dst += (int)($r->$src ?? 0);
            }
        }

        $rows = collect(array_values($pivot))->sortBy('unit_name')->values();

        // ===== 4) Nạp template & header
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        if (!empty($cfg['header']['province'])) $this->setCellValue($sh,$cfg['header']['province'], $meta['province'] ?? '');
        if (!empty($cfg['header']['ward']))     $this->setCellValue($sh,$cfg['header']['ward'],     $meta['ward'] ?? '');
        if (!empty($cfg['header']['school']))   $this->setCellValue($sh,$cfg['header']['school'],   $meta['school'] ?? '');

        // Header tuổi + L6..L9
        [$colMap, $totalCol] = $this->ensureAgeGradeHeader(
            $sh,
            $cfg['headerRowAge'],
            $cfg['headerRowGrade'],
            $cfg['startCol'],
            $ages,
            [6,7,8,9],
            $cfg['totalCol'] // template đã có cột Tổng (AN), nếu null sẽ sinh động
        );

        // ===== 5) Dàn vùng & copy dòng mẫu
        $count     = $rows->count();
        $startRow  = $cfg['startRow'];   // dòng mẫu
        $sumRow    = $cfg['sumRow'];     // dòng “Cộng”
        $lastCol   = $cfg['lastCol'];

        $need = max(0, $count - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($sumRow, $need);
            for ($i=1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
            $sumRow += $need;
        }

        // ===== 6) Ghi dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($rows as $row) {
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh,"A{$r}", $stt);
            $this->setCellValue($sh,"B{$r}", $row->unit_name);

            $rowSum = 0;
            foreach ($ages as $a) {
                for ($g=6; $g<=9; $g++) {
                    $key = "a{$a}_g{$g}";
                    $val = (int)($row->$key ?? 0);
                    $rowSum += $val;

                    if (!empty($colMap[$a][$g]) && $val !== 0) {
                        $this->setCellValue($sh,$colMap[$a][$g].$r, $val);
                    }
                }
            }
            if (!empty($totalCol)) {
                $this->setCellValue($sh,$totalCol.$r, $rowSum ?: null);
            }
            $r++; $stt++;
        }
        $firstDataRow = $startRow;
        $lastDataRow  = max($startRow, $r - 1);
        $lastCol      = $totalCol ?: $sh->getHighestColumn();

        // 1) Căn giữa tất cả giá trị (ngang + dọc)
        $sh->getStyle("A{$firstDataRow}:{$lastCol}{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

        // 2) (Tuỳ chọn) Giữ cột tên đơn vị B:C căn trái
        //    → nếu muốn cả tên cũng ở giữa thì bỏ block này.
        $sh->getStyle("B{$firstDataRow}:C{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // ===== 7) Dòng “Cộng” (cộng THEO CỘT, ẩn 0)
        $this->safeMerge($sh, "B{$sumRow}:C{$sumRow}");
        $this->setCellValue($sh,"B{$sumRow}", 'Cộng');

        foreach ($ages as $a) {
            for ($g=6; $g<=9; $g++) {
                if (empty($colMap[$a][$g])) continue;
                $col     = $colMap[$a][$g];
                $sumExpr = sprintf('SUM(%s%d:%s%d)', $col, $firstDataRow, $col, $lastDataRow);
                $this->setCellValue($sh,$col.$sumRow, sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr));
            }
        }
        if (!empty($totalCol)) {
            $sumExpr = sprintf('SUM(%s%d:%s%d)', $totalCol, $firstDataRow, $totalCol, $lastDataRow);
            $this->setCellValue($sh,$totalCol.$sumRow, sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr));
        }
        $sumRange = sprintf('%s%d:%s%d', $cfg['startCol'], $sumRow, $lastCol, $sumRow);
        $sh->getStyle($sumRange)->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');

        // ===== 8) Viền + footer ngày tháng năm
        $this->drawThinBorders($sh, $firstDataRow, $sumRow, 'A', $lastCol);
        $wardname = $meta['ward'] ?? '';
        $this->setCellValue($sh,'AC'.($sumRow + 2), $this->formatService->end_date($wardname)); // ô tùy template
        $this->setCellValue($sh,'F3', $this->formatService->top_date());

        // ===== 9) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lấy HS cư trú NGOÀI phường/xã nhưng ĐANG HỌC trong phường/xã (hoặc 1 TRƯỜNG),
     * đếm theo năm sinh và L6..L9 (THCS).
     * Trả về mảng object có:
     *  - stay_ward_code_*, stay_province_code_* (và stay_district_code_old nếu old)
     *  - y (dob_year), off_g6..off_g9
     */
    protected function offRouteSecondaryByAge(Request $req): array
    {
        $academic = (string) $req->input('year');
        [$y1]     = array_map('intval', explode('-', $academic));
        $years    = [];
        for ($age = 10; $age <= 18; $age++) $years[] = $y1 - $age;
        rsort($years);

        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);
        $pid      = (int)($geo['province_id'] ?? 0);
        $did      = (int)($geo['district_id'] ?? 0);
        $wid      = (int)($geo['ward_id'] ?? 0);
        $schoolId = (int) ($req->input('education.school_id') ?? $req->input('school_id') ?? 0);

        // Trường “đang học” (trong phường/xã hiện tại hoặc 1 trường cụ thể)
        if ($scheme === 'old') {
            if     ($wid) $offExpr = "s.old_ward_id IS NOT NULL AND s.old_ward_id = {$wid}";
            elseif ($did) $offExpr = "s.old_district_id IS NOT NULL AND s.old_district_id = {$did}";
            else          $offExpr = "s.old_province_id IS NOT NULL AND s.old_province_id = {$pid}";
        } else {
            if     ($wid) $offExpr = "s.new_ward_id IS NOT NULL AND s.new_ward_id = {$wid}";
            else          $offExpr = "s.new_province_id IS NOT NULL AND s.new_province_id = {$pid}";
        }

        // Nhận diện L6..L9, loại mầm non
        $notPreschoolName = "LOWER(eb.name) NOT REGEXP '(tháng|tuổi|m[ăa]m\\s*non|mẫu\\s*giáo|nhà\\s*trẻ)'";
        $g = function (int $n) use ($notPreschoolName) {
            return "(eb.name REGEXP '(^|[^0-9]){$n}([^0-9]|$)' AND {$notPreschoolName})";
        };

        $qb = DB::table('students_off_track as sot')
            ->join('schools as s', 's.id', '=', 'sot.school_id')
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'sot.education_block_id')
            ->where('sot.is_deleted', false)
            ->where('sot.academic_year', $academic)
            ->whereIn('sot.dob_year', $years)
            ->whereNotNull('sot.school_id')
            ->whereNotNull('sot.education_block_id')
            ->where('s.is_dissolved', false);

        if ($schoolId > 0) {
            $qb->where('s.id', $schoolId);
            $offExpr = "s.id = {$schoolId}"; // CASE trong select chỉ xét học ở đúng trường này
        }

        // JOIN tên địa chỉ cư trú & bắt “ngoài địa bàn học”
        $useOld      = ($scheme === 'old');
        $stayProvCol = $useOld ? 'sot.stay_province_code_old' : 'sot.stay_province_code_new';
        $stayWardCol = $useOld ? 'sot.stay_ward_code_old'     : 'sot.stay_ward_code_new';

        $qb->leftJoin('geo_units as sp', 'sp.code', '=', DB::raw($stayProvCol))
           ->leftJoin('geo_units as sw', 'sw.code', '=', DB::raw($stayWardCol));

        if ($useOld) {
            $qb->leftJoin('geo_units as sd', 'sd.code', '=', 'sot.stay_district_code_old');
            $qb->leftJoin('geo_units as sward', 'sward.id', '=', 's.old_ward_id')
               ->whereNotNull('sw.id')->whereNotNull('sward.id')
               ->whereColumn('sw.id','<>','sward.id');
        } else {
            $qb->leftJoin('geo_units as sward', 'sward.id', '=', 's.new_ward_id')
               ->whereNotNull('sw.id')->whereNotNull('sward.id')
               ->whereColumn('sw.id','<>','sward.id');
        }

        $rows = $qb->selectRaw("
                {$stayWardCol} ,
                {$stayProvCol} ,
                ".($useOld ? "sot.stay_district_code_old," : "")."
                sot.dob_year AS y,
                SUM(CASE WHEN {$g(6)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g6,
                SUM(CASE WHEN {$g(7)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g7,
                SUM(CASE WHEN {$g(8)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g8,
                SUM(CASE WHEN {$g(9)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g9
            ")
            ->groupBy('sot.dob_year', DB::raw($stayWardCol), DB::raw($stayProvCol))
            ->when($useOld, fn($q) => $q->groupBy('sot.stay_district_code_old'))
            ->orderBy('y')
            ->get();

        $province = $this->geoService->geoNameFromId($geo['province_id'] ?? null) ?? '';
        $ward     = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $school   = $schoolId ? (optional(\App\Models\School::find($schoolId))->name ?? '') : '';

        return [$rows, compact('province','ward','school')];
    }

    /** Header tuổi + L6..L9; trả [$map, $totalCol] */
    protected function ensureAgeGradeHeader(
        Worksheet $sh,
        int $ageRow,
        int $gradeRow,
        string $startCol,
        array $ages,
        array $grades,
        ?string $configuredTotalCol = null
    ): array {
        $map    = [];
        $colIdx = Coordinate::columnIndexFromString($startCol);

        foreach ($ages as $age) {
            $firstIdx = $colIdx;

            foreach ($grades as $g) {
                $col  = Coordinate::stringFromColumnIndex($colIdx);
                $cell = $col.$gradeRow;
                if (($sh->getCell($cell)->getValue() ?? '') === '') {
                    $this->setCellValue($sh,$cell, 'L'.$g);
                }
                $map[$age][$g] = $col;
                $colIdx++;
            }
            $firstCol = Coordinate::stringFromColumnIndex($firstIdx);
            $lastCol  = Coordinate::stringFromColumnIndex($colIdx - 1);
            $ageCell  = $firstCol.$ageRow;
            if (($sh->getCell($ageCell)->getValue() ?? '') === '') {
                $sh->mergeCells("{$firstCol}{$ageRow}:{$lastCol}{$ageRow}");
                $this->setCellValue($sh,$ageCell, sprintf('%d Tuổi', $age));
            }
        }

        $totalCol = $configuredTotalCol;
        if (!$totalCol) {
            $totalCol = Coordinate::stringFromColumnIndex($colIdx);
            $this->setCellValue($sh,$totalCol.$ageRow, 'Tổng');
        }

        return [$map, $totalCol];
    }

    /** Cấu hình template THCS */
    protected function templateCfg(): array
    {
        return [
            'path'           => resource_path('excel/3.7.THCS.TK.TraiTuyen.xlsx'),
            'header'         => [
                'province' => 'C1',
                'ward'     => 'C2',
                'school'   => 'C3',
            ],
            'headerRowAge'   => 5,    // hàng “10 Tuổi … 18 Tuổi”
            'headerRowGrade' => 6,    // hàng “L6..L9”
            'startCol'       => 'D',  // L6 của 10 tuổi
            'startRow'       => 7,    // dòng mẫu
            'sumRow'         => 8,    // dòng "Cộng"
            'lastCol'        => 'AN', // cột cuối vùng bảng (theo template)
            'totalCol'       => 'AN', // template có sẵn cột Tổng ở AN; để null nếu muốn tự sinh
        ];
    }

    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
}
