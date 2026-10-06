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
/**
 * THỐNG KÊ HỌC SINH ĐANG HỌC: TRƯỜNG NGOÀI PHƯỜNG/XÃ (Tiểu học)
 *
 * - Mỗi dòng = 1 trường
 * - Cột: 6..14 tuổi, trong mỗi tuổi có L1..L5, và 1 cột Tổng
 *
 * Yêu cầu template (ví dụ): resources/excel/TH_NgoaiPhuongXa.xlsx
 *   - B2: "Tỉnh/Thành:"  -> điền tên tỉnh ở ô D2 (hoặc sửa config bên dưới)
 *   - B3: "Phường/Xã:"   -> điền tên phường/xã ở ô D3
 *   - Hàng tiêu đề tuổi nằm ở row 6 (có thể chỉnh config['headerRow'])
 *   - Dòng dữ liệu đầu tiên ở row 8 (có thể chỉnh config['startRow'])
 *   - Cột đầu tiên cho L1 của 6 tuổi là F (có thể chỉnh config['startCol'])
 */
class PrimaryOutsideWardReport extends BaseReport
{
    /**
     * Handler xuất Excel
     */

    
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
   public function export(Request $req)
    {
        // ---- 1) Đầu vào & cấu hình chung
        $year   = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1, $y2] = array_map('intval', explode('-', $year));
        $scheme = (string) $req->input('scheme', 'old');
        $geo    = (array)  $req->input('geo', []);
        $cfg    = $this->templateCfg();

        // ---- 2) Tuổi -> năm sinh
        $ages = range(6, 14);
        $ageYear = [];
        foreach ($ages as $a) $ageYear[$a] = $y1 - $a;

        // ---- 3) Lấy dữ liệu
        [$rows, $meta] = $this->queryBySchoolOutsideWard($year, $scheme, $geo, $ageYear);
        $rowCount = count($rows);

        // ---- 4) Nạp template & header
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        if (!empty($cfg['header']['province'])) $this->setCellValue($sh,$cfg['header']['province'], $meta['province'] ?? '');
        if (!empty($cfg['header']['ward']))     $this->setCellValue($sh,$cfg['header']['ward'],     $meta['ward'] ?? '');

        // Vẽ tiêu đề tuổi + L1..L5 (nếu thiếu) -> trả map cột
        $colMap = $this->ensureAgeGradeHeader(
            $sh,
            $cfg['headerRow'],
            $cfg['startCol'],
            $ages,
            [1,2,3,4,5],
            $cfg['totalColLabel'] ?? null
        );

        // ---- 4.5) CHÈN & COPY DÒNG MẪU (giữ nguyên style/merge)
        // Dòng mẫu đầu tiên của bảng = 7 (yêu cầu của bạn)
        $templateRow = $cfg['templateRow'] ?? 7;
        $startRow    = $templateRow;               // dữ liệu sẽ ghi từ đây
        $need        = max(0, $rowCount - 1);      // đã có sẵn 1 dòng mẫu trong file

        if ($need > 0) {
            // chèn thêm need dòng ngay bên dưới dòng mẫu
            $sh->insertNewRowBefore($templateRow + 1, $need);
            // nhân bản định dạng/merge từ dòng mẫu cho các dòng mới
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $templateRow, $templateRow + $i, $cfg['lastCol']);
            }
        }

        // ---- 5) Ghi dữ liệu theo từng trường
        $r   = $startRow; $stt = 1;
        foreach ($rows as $row) {
            // STT + tên trường
            $this->setCellValue($sh,'A'.$r, $stt);
            $this->setCellValue($sh,'B'.$r, $row->school_name);

            // tuổi*L + tổng hàng
            $rowSum = 0;
            foreach ($ages as $a) {
                for ($g=1; $g<=5; $g++) {
                    $key = "a{$a}_g{$g}";
                    $val = (int)($row->$key ?? 0);
                    $rowSum += $val;
                    if (!empty($colMap[$a][$g])) {
                        $this->setCellValue($sh,$colMap[$a][$g].$r, $val ?: null);
                    }
                }
            }
            if (!empty($cfg['totalCol'])) {
                $this->setCellValue($sh,$cfg['totalCol'].$r, $rowSum ?: null);
            }
            $r++; $stt++;
        }

        // ---- 6) Hàng "Cộng"
        $firstDataRow = $startRow;
        $lastDataRow  = $r - 1;
        $sumRow       = $r;
        $lastCol      = $cfg['totalCol'] ?: $sh->getHighestColumn();
        $this->mergeNameCellsRange($sh, $firstDataRow, $lastDataRow);
        // $this->safeMerge($sh, "B{$sumRow}:D{$sumRow}");
        // $this->setCellValue($sh,'B'.$sumRow, 'Cộng');
        
        foreach ($ages as $a) {
            for ($g=1; $g<=5; $g++) {
                $col = $colMap[$a][$g] ?? null;
                if ($col) {
                    $this->setCellValue($sh,$col.$sumRow, sprintf('=SUM(%s%d:%s%d)', $col, $firstDataRow, $col, $lastDataRow));
                }
            }
        }
        if (!empty($cfg['totalCol'])) {
            $tc = $cfg['totalCol'];
            $this->setCellValue($sh,$tc.$sumRow, sprintf('=SUM(%s%d:%s%d)', $tc, $firstDataRow, $tc, $lastDataRow));
        }

         $sumRange = sprintf('%s%d:%s%d', $cfg['startCol'], $sumRow, $lastCol, $sumRow);
        $sh->getStyle($sumRange)->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');

        // ---- 7) Viền + footer ngày tháng năm
        $this->drawThinBorders($sh, $firstDataRow, $sumRow, 'A', $cfg['lastCol']);

        $wardname =  $meta['ward'];
        $this->setCellValue($sh,'AI'.($sumRow + 2),  $this->formatService->end_date( $wardname));
        $this->setCellValue($sh,'E3' ,  $this->formatService->top_date());
        

        // ---- 8) Xuất
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }


    /**
     * Truy vấn dữ liệu: Chỉ lấy học sinh CƯ TRÚ trong địa bàn đã chọn
     * nhưng TRƯỜNG học KHÁC địa bàn (ngoài phường/xã nếu có ward_id; nếu chỉ có district thì so district; nếu chỉ province thì so province).
     * Chỉ lấy TIỂU HỌC (L1..L5).
     *
     * Kết quả: mỗi hàng là 1 trường, có các cột a{tuoi}_g{lop} cho 6..14,1..5
     */
    protected function queryBySchoolOutsideWard(string $academic, string $scheme, array $geo, array $ageYear)
    {
        // ---- Subquery: person_educations mới nhất trong năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
            ->where('pe1.academic_year', $academic);
          

        // ---- Base
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

        // ---- Chặn mầm non, chỉ nhận L1..L5
        $notPreschool = "LOWER(eb.name) NOT REGEXP '(tháng|tuổi|m[ăa]m\\s*non|mẫu\\s*giáo|nhà\\s*trẻ)'";
        $qb->whereRaw($notPreschool)
           ->whereBetween(DB::raw('CAST(eb.name AS UNSIGNED)'), [1,5]);

        // ---- Lọc địa bàn “cư trú” theo scheme
        $this->applyGeoFilter($qb, $scheme, $geo);

        // ---- Điều kiện "ngoài địa bàn" của TRƯỜNG
        $off = $this->schoolOutsideExpr($scheme, $geo);
        if ($off) $qb->whereRaw($off);

        // ---- Build select pivot theo tuổi * lớp
        $selects = [
            's.id as school_id',
            DB::raw('COALESCE(pe.school_name_custom, s.name) as school_name'),
        ];

        foreach ($ageYear as $age => $year) {
            for ($g=1; $g<=5; $g++) {
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
        
        // ---- Meta hiển thị (tên tỉnh/ward) – lấy từ form đầu tiên
        $province = $ward = '';
        if ($rows->count()) {
            // Không join geo_units ở đây để nhẹ; dùng GeoService trong BaseReport thì tốt hơn.
            // Nhưng vẫn có thể suy ra từ request:
            $province = $this->geoService->geoNameFromId($geo['province_id'] ?? null) ?? '';
            $ward     = $this->geoService->geoNameFromId($geo['ward_id'] ?? null)     ?? '';
        }

        return [$rows, compact('province','ward')];
    }

    /**
     * Tạo biểu thức "trường ngoài địa bàn" theo cấp sâu nhất có trong $geo.
     *  - Nếu có ward_id: so sánh ward_id của trường khác ward cư trú hoặc là trường ngoài tỉnh
     *  - Else nếu có district_id (scheme old): so sánh district
     *  - Ngược lại so sánh province
     */
    protected function schoolOutsideExpr(string $scheme, array $geo): ?string
    {
        if ($scheme === 'old') {
            if (!empty($geo['ward_id'])) {
                return '(s.code LIKE "NGOAI_TINH%" OR (s.old_ward_id IS NOT NULL AND f.old_ward_id IS NOT NULL AND s.old_ward_id <> f.old_ward_id))';
            }
            if (!empty($geo['district_id'])) {
                return '(s.code LIKE "NGOAI_TINH%" OR (s.old_district_id IS NOT NULL AND f.old_district_id IS NOT NULL AND s.old_district_id <> f.old_district_id))';
            }
            if (!empty($geo['province_id'])) {
                return '(s.code LIKE "NGOAI_TINH%" OR (s.old_province_id IS NOT NULL AND f.old_province_id IS NOT NULL AND s.old_province_id <> f.old_province_id))';
            }
        } else { // new
            if (!empty($geo['ward_id'])) {
                return '(s.code LIKE "NGOAI_TINH%" OR (s.new_ward_id IS NOT NULL AND f.new_ward_id IS NOT NULL AND s.new_ward_id <> f.new_ward_id))';
            }
            if (!empty($geo['province_id'])) {
                return '(s.code LIKE "NGOAI_TINH%" OR (s.new_province_id IS NOT NULL AND f.new_province_id IS NOT NULL AND s.new_province_id <> f.new_province_id))';
            }
        }
        return null;
    }

    /**
     * Ghi tiêu đề tuổi (6..14) và nhãn L1..L5 (nếu template trống),
     * đồng thời trả về map [age][grade] => ColLetter để ghi dữ liệu.
     */
    protected function ensureAgeGradeHeader(
        Worksheet $sh,
        int $headerRow,
        string $startCol,
        array $ages,
        array $grades,
        string $totalColLabel = 'Tổng'
    ): array {
        $map = [];
        // Tính cột bắt đầu dạng chỉ số
        $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startCol);

        // Dòng tuổi (ví dụ: row 6), dòng lớp ở ngay dưới (row 7)
        $ageRow   = $headerRow;
        $gradeRow = $headerRow + 1;

        foreach ($ages as $age) {
            // Viết nhãn "X Tuổi" (nếu ô đang trống)
            $colFirstThisAge = $colIndex;
            $labelCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colFirstThisAge).$ageRow;
            if (($sh->getCell($labelCell)->getValue() ?? '') === '') {
                $this->setCellValue($sh,$labelCell, sprintf('%d Tuổi', $age));
            }

            // 5 cột con: L1..L5
            foreach ($grades as $g) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cell = $col.$gradeRow;

                if (($sh->getCell($cell)->getValue() ?? '') === '') {
                    $this->setCellValue($sh,$cell, 'L'.$g);
                }

                $map[$age][$g] = $col;
                $colIndex++;
            }
        }

        // Cột Tổng
        $totalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $this->setCellValue($sh,$totalCol.$ageRow, $totalColLabel);
        // (để dành cho phần ghi dữ liệu) gắn vào config tạm thời
        $this->templateTmpTotalCol = $totalCol;

        return $map;
    }

    /**
     * Cấu hình template — bạn chỉnh nếu vị trí ô trong file khác.
     */
   protected function templateCfg(): array
    {
        return [
            'path'           => resource_path('excel/2.4.TiH.HocNgoaiDiaBan.xlsx'),
            'header'         => ['province' => 'D1', 'ward' => 'D2'],
            'headerRow'      => 6,       // hàng “6 Tuổi .. 14 Tuổi”
            'startCol'       => 'E',     // cột L1 của 6 tuổi (hoặc để 'auto' nếu muốn tự dò)
            'templateRow'    => 7,       // <— DÒNG MẪU CẦN NHÂN BẢN
            'lastCol'        => 'AX',    // cột cuối để kẻ viền/clone style
            'totalCol'       => 'AX',    // nếu có cột Tổng cố định
            'totalColLabel'  => 'Tổng',  // nhãn cột Tổng nếu auto vẽ header
        ];
    }
 

}
