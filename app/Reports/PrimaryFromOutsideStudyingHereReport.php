<?php

namespace App\Reports;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Services\ReportFormatService;
use App\Services\GeoService;
class PrimaryFromOutsideStudyingHereReport extends BaseReport
{
     public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
    public function export(Request $req)
    {
        // ---- 1) Đầu vào
        $year   = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]   = array_map('intval', explode('-', $year));
        $scheme = (string) $req->input('scheme', 'old'); // old|new
        $geo    = (array)  $req->input('geo', []);
        $schoolId = (int) $req->input('education.school_id', 0) ?: null;


        $academic = (string) $req->input('year');
        [$y1, $y2] = $this->parseAcademic($academic);
        $years     = $this->yearsFor($y1, 6, 14); // 9 năm, desc
       
       

        // 2) Tính dataset chính + “trái tuyến theo tuổi”
        
        // [$metaOff, $yearsOff, $offSeries] = $this->offRoutePrimaryByAge($req, $y1, $years, $scheme, $geo);


        // Tuổi -> năm sinh
        $ages = range(6, 14);
        $ageYear = [];
        foreach ($ages as $a) $ageYear[$a] = $y1 - $a;
     

        [$rowsRaw, $meta] = $this->offRoutePrimaryByAge($req, $y1, $years, $scheme, $geo);

        // ===== Pivot theo đơn vị cư trú (unit_name) =====
        $ages = range(6, 14);

        // 1) Lấy tên địa chỉ từ bảng geo_units theo code
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

        // 2) Pivot: gộp theo unit_name và tuổi
        $pivot = [];
        foreach ($rowsColl as $r) {
            $wardCode = $r->{$wardCol} ?? null;
            $provCode = $r->{$provCol} ?? null;
            $distCode = $distCol ? ($r->{$distCol} ?? null) : null;

            $parts = [];
            if ($wardCode && isset($wardNames[$wardCode])) $parts[] = $wardNames[$wardCode];
            if ($useOld && $distCode && isset($distNames[$distCode])) $parts[] = $distNames[$distCode];
            if ($provCode && isset($provNames[$provCode])) $parts[] = $provNames[$provCode];

            $unitName = trim(implode(', ', $parts));
            if ($unitName === '') $unitName = 'Không rõ địa chỉ';

            // khởi tạo dòng nếu chưa có
            if (!isset($pivot[$unitName])) {
                $rowInit = ['unit_name' => $unitName];
                foreach ($ages as $a) {
                    for ($g = 1; $g <= 5; $g++) {
                        $rowInit["a{$a}_g{$g}"] = 0;
                    }
                }
                $pivot[$unitName] = (object) $rowInit;
            }

            // tính tuổi = y1 - y
            $y   = (int)($r->y ?? 0);
            $age = $y1 - $y;
            if (!in_array($age, $ages, true)) continue;

            // cộng dồn off_g{1..5} vào a{age}_g{1..5}
            for ($g = 1; $g <= 5; $g++) {
                $src = "off_g{$g}";
                $dst = "a{$age}_g{$g}";
                $pivot[$unitName]->$dst += (int)($r->$src ?? 0);
            }
        }

        // Collection đã pivot, sort theo tên để ghi ra bảng
        $rows = collect(array_values($pivot))->sortBy('unit_name')->values();
        

        // ---- 3) Nạp template & phần đầu
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        if (!empty($cfg['header']['province'])) $this->setCellValue($sh,$cfg['header']['province'], $meta['province'] ?? '');
        if (!empty($cfg['header']['ward']))     $this->setCellValue($sh,$cfg['header']['ward'],     $meta['ward'] ?? '');
        if (!empty($cfg['header']['school']))   $this->setCellValue($sh,$cfg['header']['school'],   $meta['school'] ?? '');

        // ---- 4) Bảo đảm tiêu đề tuổi + nhãn L1..L5; lấy map cột
        [$colMap, $totalCol] = $this->ensureAgeGradeHeader(
            $sh,
            $cfg['headerRowAge'],     // hàng "6 Tuổi ... 14 Tuổi"
            $cfg['headerRowGrade'],   // hàng "L1..L5"
            $cfg['startCol'],         // cột bắt đầu (L1 của 6 tuổi)
            $ages,
            [1,2,3,4,5],
            $cfg['totalCol']          // nếu template đã có cột Tổng, điền chữ cột ở đây; nếu null sẽ tự sinh
        );

        // ---- 5) Chuẩn bị vùng dữ liệu: copy dòng mẫu (row 7) đủ số lượng
        $count = $rows->count();
        $startRow  = $cfg['startRow'];     // == 7 (dòng mẫu)
        $sumRow    = $cfg['sumRow'];       // == 8 (dòng "Cộng" trong template trống)
        $lastCol   = $cfg['lastCol'];

        $need = max(0, $count - 1); // đã có sẵn 1 dòng mẫu
        if ($need > 0) {
            // chèn $need dòng trước dòng "Cộng"
            $sh->insertNewRowBefore($sumRow, $need);
            // nhân bản style/merge/validation từ dòng mẫu
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
            // cập nhật vị trí dòng "Cộng"
            $sumRow += $need;
        }

        // ---- 6) Ghi dữ liệu từng dòng
       $r = $startRow; $stt = 1;
      
        foreach ($rows as $row) {
            // STT + Đơn vị (merge B..D)
            $this->safeMerge($sh, "B{$r}:D{$r}");
            $this->setCellValue($sh,"A{$r}", $stt);
            $this->setCellValue($sh,"B{$r}", $row->unit_name);

            // tuổi × lớp
            $rowSum = 0;
            foreach ($ages as $a) {
                for ($g = 1; $g <= 5; $g++) {
                    $key = "a{$a}_g{$g}";
                    $val = (int)($row->$key ?? 0);
                    $rowSum += $val;

                    $col = $colMap[$a][$g] ?? null;
                    if ($col && $val !== 0) {
                        $this->setCellValue($sh,$col.$r, $val);
                    }
                }
            }
            if (!empty($totalCol)) {
                $this->setCellValue($sh,$totalCol.$r, $rowSum ?: null);
            }

            $r++; $stt++;
        }
        $firstDataRow = $startRow;
        $lastDataRow  = $r - 1;

 // ---- 7) Hàng "Cộng": cộng THEO CỘT, và ẩn 0
        $this->safeMerge($sh, "B{$sumRow}:D{$sumRow}");
        $this->setCellValue($sh,"B{$sumRow}", 'Cộng');

        $firstDataRow = $startRow;
        $lastDataRow  = max($startRow, $r - 1);

        // Tạo công thức cho từng cột tuổi × lớp
        foreach ($ages as $a) {
            for ($g = 1; $g <= 5; $g++) {
                if (empty($colMap[$a][$g])) continue;

                $col     = $colMap[$a][$g];
                $sumExpr = sprintf('SUM(%s%d:%s%d)', $col, $firstDataRow, $col, $lastDataRow);

                // Nếu tổng = 0 thì để TRỐNG
                $this->setCellValue($sh,
                    $col.$sumRow,
                    sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr)
                );
            }
        }

        // Tổng ngang cột "Tổng"
        if (!empty($totalCol)) {
            $sumExpr = sprintf('SUM(%s%d:%s%d)', $totalCol, $firstDataRow, $totalCol, $lastDataRow);
            $this->setCellValue($sh,
                $totalCol.$sumRow,
                sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr)
            );
        }

        // ẨN 0 bằng định dạng số ở HÀNG CỘNG (phòng khi Excel vẫn hiển thị 0)
        $sumRange = sprintf('%s%d:%s%d', $cfg['startCol'], $sumRow, $lastCol, $sumRow);
        // ví dụ: "#,##0;-#,##0;;@" => 0 sẽ không hiển thị
        $sh->getStyle($sumRange)->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');

        // ---- 8) Viền + footer ngày tháng năm
        $this->drawThinBorders($sh, $firstDataRow, $sumRow, 'A', $lastCol);
        $footerRow = $sumRow + 2;
        // $this->writeDateLine($sh, $scheme, $meta['ward'] ?? '', $footerRow, $lastCol);
        $wardname =  $meta['ward'];
        $this->setCellValue($sh,'AI'.($sumRow + 2),  $this->formatService->end_date( $wardname));
        $this->setCellValue($sh,'E3' ,  $this->formatService->top_date());
        // ---- 9) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * HS cư trú NGOÀI phường/xã nhưng ĐANG HỌC trong phường/xã (hoặc trong một TRƯỜNG cụ thể).
     * - Chỉ đếm bậc tiểu học (L1..L5).
     * - Gom nhóm theo đơn vị cư trú (xã, huyện, tỉnh).
     */
    protected function offRoutePrimaryByAge(
        Request $req, int $y1, array $years, string $scheme, array $geo
    ) {
         // 1) Năm học và dải năm sinh 6..14
        $academic = (string) $req->input('year');
        [$y1, $y2] = array_map('intval', explode('-', $academic));
        $years = [];
        for ($age = 6; $age <= 14; $age++) $years[] = $y1 - $age;
        rsort($years);
        $school    = (array)  $req->input('education', []);
        $schoolId =   $school['school_id'];
        // 2) Đầu vào địa bàn
        $scheme = (string) $req->input('scheme', 'old'); // 'old'|'new'
        $geo    = (array)  $req->input('geo', []);
        $pid = (int)($geo['province_id'] ?? 0);
        $did = (int)($geo['district_id'] ?? 0); // chỉ scheme old
        $wid = (int)($geo['ward_id'] ?? 0);

        // 3) Xác định cấp so sánh & biểu thức "trái tuyến" (ID TRƯỜNG khác ID đang thống kê)
        $od = "";
        if ($scheme === 'old') {
            if ($wid) {
                $offExpr = "s.old_ward_id IS NOT NULL AND s.old_ward_id = {$wid}";
            } elseif ($did) {
                $offExpr = "s.old_district_id IS NOT NULL AND s.old_district_id = {$did}";
            } else {
                $offExpr = "s.old_province_id IS NOT NULL AND s.old_province_id = {$pid}";
            }
             $od = "stay_ward_code_old,stay_district_code_old,stay_province_code_old";
        } else { // new
            if ($wid) {
                $offExpr = "s.new_ward_id IS NOT NULL AND s.new_ward_id = {$wid}";
            } else {
                $offExpr = "s.new_province_id IS NOT NULL AND s.new_province_id = {$pid}";
            }
             $od = "stay_ward_code_new,stay_province_new";
        }

        // 4) Nhận diện lớp 1..5 bằng REGEXP, đồng thời loại tên mầm non
        $notPreschoolName = "LOWER(eb.name) NOT REGEXP '(tháng|tuổi|m[ăa]m\\s*non|mẫu\\s*giáo|nhà\\s*trẻ)'";
        $g = function (int $n) use ($notPreschoolName) {
            // số n đứng độc lập (không dính 10, 11, 12...) + không phải mầm non
            return "(eb.name REGEXP '(^|[^0-9]){$n}([^0-9]|$)' AND {$notPreschoolName})";
        };

        // 5) Base query
        $qb = DB::table('students_off_track as sot')
            ->join('schools as s', 's.id', '=', 'sot.school_id')
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'sot.education_block_id')
             ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'sot.graduation_level_id')
            ->where('sot.is_deleted', false)
            ->where('sot.academic_year', $academic)
            ->whereIn('sot.dob_year', $years)
            ->whereNotNull('sot.school_id')
            ->whereNotNull('sot.education_block_id')
            ->where('s.is_dissolved', false);
        if ($schoolId)
        {
             $qb->where('sot.school_id',$schoolId);
        }

        // 6) Select: đếm trái tuyến theo lớp, group theo năm sinh
        $rows = $qb->selectRaw( "sot.stay_ward_code_new,sot.stay_province_code_new,
            
            sot.dob_year as y,
            SUM(CASE WHEN  {$g(1)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g1,
            SUM(CASE WHEN {$g(2)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g2,
            SUM(CASE WHEN {$g(3)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g3,
            SUM(CASE WHEN {$g(4)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g4,
            SUM(CASE WHEN {$g(5)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g5
           
             ")->groupBy( 'sot.stay_ward_code_new','sot.stay_province_code_new','sot.dob_year')->get();
        // dd($rows);
			  
        // 7) Chuẩn hóa series trả về
       

        $meta = [
            'year1'    => $y1,
            'scheme'   => $scheme,
            'geo'      => $geo,
            'dateLine' => 'Ngày '.date('d').' tháng '.date('m').' năm '.date('Y'),
        ];

     
        // ===== 7) Meta để in header
        $province = $this->geoService->geoNameFromId($geo['province_id'] ?? null) ?? '';
        $ward     = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $school   = $schoolId ? (optional(\App\Models\School::find($schoolId))->name ?? '') : '';

        return [$rows, compact('province','ward','school')];
    }




    /** Tạo header tuổi + L1..L5; trả [$map, $totalCol] */
    protected function ensureAgeGradeHeader(
        Worksheet $sh,
        int $ageRow,
        int $gradeRow,
        string $startCol,
        array $ages,
        array $grades,
        ?string $configuredTotalCol = null
    ): array {
        $map = [];
        $colIdx = Coordinate::columnIndexFromString($startCol);

        foreach ($ages as $age) {
            $firstIdx = $colIdx;
            foreach ($grades as $g) {
                $col = Coordinate::stringFromColumnIndex($colIdx);
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

    /** Merge helper an toàn (tránh lỗi nếu đã merge) */
    

    /** Nhân bản style + merge của 1 dòng (không dùng isMergedCell) */
    

    
    /** Cấu hình vị trí trong template trống (ảnh bạn gửi) */
    protected function templateCfg(): array
    {
        return [
            'path'           => resource_path('excel/2.5.TiH.TK.TraiTuyen.xlsx'),
            'header'         => [
                'province' => 'D1',  // Tỉnh/Thành
                'ward'     => 'D2',  // Xã/Phường
                'school'   => 'D3',  // Trường (nếu lọc theo trường)
            ],
            'headerRowAge'   => 5,   // hàng "6 Tuổi … 14 Tuổi"
            'headerRowGrade' => 6,   // hàng "L1 L2 L3 L4 L5"
            'startCol'       => 'E', // L1 của 6 tuổi
            'startRow'       => 7,   // dòng mẫu đầu tiên để copy
            'sumRow'         => 8,   // dòng "Cộng" ban đầu
            'lastCol'        => 'AX',
            'totalCol'       => 'AX',// nếu template đã có sẵn cột Tổng = AX
        ];
    }

    /** Viền mảnh (nếu BaseReport chưa có) */
    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
}
