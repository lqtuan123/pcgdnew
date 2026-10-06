<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use App\Models\DisabilityCategory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
class HighSchoolDisabilityReport extends BaseReport
{
     public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // 1) Đầu vào + dải năm sinh cho 6..14 tuổi
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);
        $schoolId = (int) ($req->input('education.school_id') ?? $req->input('school_id') ?? 0);

        $years = [];
        for ($a = 14; $a <= 21; $a++) $years[] = $y1 - $a;
        rsort($years); // 2018..2010 như trong ảnh

        // 2) Lấy dữ liệu đã gộp theo năm sinh
        $rows = $this->queryPrimaryDisabilityByDobYear($academic, $scheme, $geo, $years, $schoolId);
        // dd($rows);
        // 3) Nạp template & header
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

         $this->setCellValue($sh,$cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
         $this->setCellValue($sh,$cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        if (!empty($cfg['header']['school']) && $schoolId) {
            $schoolName = optional(\App\Models\School::find($schoolId))->name ?? '';
             $this->setCellValue($sh,$cfg['header']['school'], $schoolName);
        }
         $this->setCellValue($sh,'E3', $this->formatService->top_date());
        $sh->getStyle("E3:L3")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        // 4) Nhân bản dòng mẫu đủ số lượng
        $startRow = $cfg['startRow']; // 8
        $sumRow   = $cfg['sumRow'];   // 9
        $lastCol  = $cfg['lastCol'];  // O
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($sumRow, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
            $sumRow += $need;
        }

        // 5) Đổ dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($years as $y) {
            $d = $rows[$y] ?? null;
            if (!$d) continue;

             $this->setCellValue($sh,$cfg['col']['stt'].$r,  $stt);
              $sh->setCellValue( $cfg['col']['dob'].$r,  $y); 
             $this->setCellValue($sh,$cfg['col']['age'].$r,  $y1 - $y);

            foreach (['total','van_dong','nghe_noi','nhin','than_kinh','tri_tue','tu_ky','hoc_tap','khac','can_learn','access'] as $k) {
                $val = (int)($d[$k] ?? 0);
                 $this->setCellValue($sh,$cfg['col'][$k].$r, $val ?: null);
            }

            // tỷ lệ = access / total * 100, rỗng nếu 0
            $totalCol = $cfg['col']['total'];
            $accessCol= $cfg['col']['access'];
            $ratio    = sprintf('=IF(%s%d=0,"",ROUND(%s%d/%s%d*100,2))', $totalCol,$r,$accessCol,$r,$totalCol,$r);
             $this->setCellValue($sh,$cfg['col']['ratio'].$r, $ratio);


             
            $r++; $stt++;
        }
        $firstDataRow = $startRow;
        $lastDataRow  = $r - 1;

        // 6) Hàng "Cộng"
        $this->safeMerge($sh, 'A'.$sumRow.':'.'C'.$sumRow);
         $this->setCellValue($sh,'A'.$sumRow, 'Cộng');

        foreach (['total','van_dong','nghe_noi','nhin','than_kinh','tri_tue','tu_ky','hoc_tap','khac','can_learn','access'] as $k) {
            $col = $cfg['col'][$k];
            $sumExpr = sprintf('SUM(%s%d:%s%d)', $col, $firstDataRow, $col, $lastDataRow);
             $this->setCellValue($sh,$col.$sumRow, sprintf('=IF(%s=0,"",%s)', $sumExpr, $sumExpr));
        }
        // $ratioTotal = sprintf(
        //     '=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',
        //     $cfg['col']['can_learn'], $sumRow, $cfg['col']['access']
        // );
        //  $this->setCellValue($sh,$cfg['col']['ratio'].$sumRow, $ratioTotal);
          $sumTotalExpr  = sprintf('SUM(%s%d:%s%d)',  $cfg['col']['total'],  $firstDataRow, $cfg['col']['total'],  $lastDataRow);
            $sumAccessExpr = sprintf('SUM(%s%d:%s%d)',  $cfg['col']['access'], $firstDataRow, $cfg['col']['access'], $lastDataRow);
        $sh->getStyle("A{$sumRow}:O{$sumRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Ẩn số 0 ở hàng Cộng
        $sumRange = sprintf('%s%d:%s%d', $cfg['col']['total'], $sumRow, $lastCol, $sumRow);
        $sh->getStyle($sumRange)->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');

        // 7) Viền + footer date
        $this->drawThinBorders($sh, $firstDataRow, $sumRow, 'A', $lastCol);
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
         $this->setCellValue($sh,'J'.($sumRow + 2), $this->formatService->end_date($wardName)); // ô tùy template // ô tùy template
        $sumRow = $sumRow + 2;
         $sh->getStyle("J{$sumRow}:J{$sumRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        // 8) Xuất
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    
protected function queryPrimaryDisabilityByDobYear(
    string $academic, string $scheme, array $geo, array $years, int $schoolId
): array {
    // 0) Map code -> id cho các nhóm cần thống kê
    $codes = ['VAN_DONG','NGHE_NOI','NHIN','THAN_KINH','TRI_TUE','HOC_TAP','TU_KY','KHAC'];
    $catId = DisabilityCategory::whereIn('code', $codes)->pluck('id','code');  // ['VAN_DONG'=>12, ...]

    // Helper: tạo biểu thức JSON_CONTAINS(pd.disability_ids, <id>)
    $has = function (string $code): string {
        // Nếu thiếu ID (chưa seed), trả về '0' để SUM(CASE WHEN 0 THEN ...)=0
        $id = (int) (app('db')->getQueryGrammar() ? (DisabilityCategory::where('code',$code)->value('id') ?? 0) : 0);
        // Tránh query phụ trên mỗi gọi: dùng closure đặt ngoài nếu thích. Ở đây an toàn vì ít code.
        return $id > 0 ? "JSON_CONTAINS(pd.disability_ids, '".json_encode($id)."')" : "0";
    };

    // 1) Subquery: person_educations mới nhất theo năm học
    $peLatest = DB::table('person_educations as pe1')
        ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
        ->where('pe1.academic_year', $academic);

    // 2) Subquery: person_disabilities mới nhất / người
    $pdLatest = DB::table('person_disabilities as t')
        ->select('t.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY t.person_id ORDER BY t.id DESC) AS rn'))
        ->where('t.is_deleted', false);

    // 3) Base query
    $qb = DB::table('survey_form_details as d')
        ->join('survey_forms as f', function($j){
            $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
        })
        ->join('people as p', 'p.id', '=', 'd.person_id')
        ->leftJoinSub($pdLatest, 'pd', function($j){
            $j->on('pd.person_id','=','p.id')->where('pd.rn', 1);
        })
        ->leftJoinSub($peLatest, 'pe', function($j){
            $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
        })
        ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
        ->where('d.is_deleted', false)
        ->where('d.academic_year', $academic)
        ->whereIn('p.dob_year', $years);

    // 4) Lọc địa bàn cư trú theo scheme (dùng helper sẵn có trong BaseReport)
    $this->applyGeoFilter($qb, $scheme, $geo);

    // 5) Nếu chọn trường, lọc theo school_id
    if ($schoolId > 0) {
        $qb->where('pe.school_id', $schoolId);
    }

    // 6) Điều kiện "là người khuyết tật": mảng disability_ids KHÔNG rỗng
    $isDisabled = "JSON_LENGTH(pd.disability_ids) > 0";

    // 7) “Tiếp cận GD” = có học & bậc TiH (L1..L5)
    $accessEdu  = "(pe.education_block_id IS NOT NULL AND CAST(eb.name AS UNSIGNED) BETWEEN 1 AND 5)";
    $canLearn   = "COALESCE(pd.can_learn,0) = 1";

    // 8) Select gộp theo năm sinh
    $rows = $qb->selectRaw("
            p.dob_year as y,
            SUM(CASE WHEN {$isDisabled} THEN 1 ELSE 0 END) AS total,
            SUM(CASE WHEN {$has('VAN_DONG')}  THEN 1 ELSE 0 END) AS van_dong,
            SUM(CASE WHEN {$has('NGHE_NOI')}  THEN 1 ELSE 0 END) AS nghe_noi,
            SUM(CASE WHEN {$has('NHIN')}      THEN 1 ELSE 0 END) AS nhin,
            SUM(CASE WHEN {$has('THAN_KINH')} THEN 1 ELSE 0 END) AS than_kinh,
            SUM(CASE WHEN {$has('TRI_TUE')}   THEN 1 ELSE 0 END) AS tri_tue,
            SUM(CASE WHEN {$has('TU_KY')}     THEN 1 ELSE 0 END) AS tu_ky,
            SUM(CASE WHEN {$has('HOC_TAP')}   THEN 1 ELSE 0 END) AS hoc_tap,
            SUM(CASE WHEN {$has('KHAC')}      THEN 1 ELSE 0 END) AS khac,
            SUM(CASE WHEN {$isDisabled} AND {$canLearn} THEN 1 ELSE 0 END) AS can_learn,
            SUM(CASE WHEN {$isDisabled} AND {$accessEdu} THEN 1 ELSE 0 END) AS access
        ")
        ->whereRaw($isDisabled)           // chỉ thống kê người khuyết tật
        ->groupBy('p.dob_year')
        ->orderBy('p.dob_year')
        ->get();

   

    // 9) Chuẩn hóa về mảng [year => metrics...] để đổ vào sheet
    $out = [];
    foreach ($years as $y) {
        $out[$y] = [
            'total'=>0,'van_dong'=>0,'nghe_noi'=>0,'nhin'=>0,'than_kinh'=>0,
            'tri_tue'=>0,'tu_ky'=>0,'hoc_tap'=>0,'khac'=>0,'can_learn'=>0,'access'=>0,
        ];
    }
    foreach ($rows as $r) {
        $y = (int)$r->y;
        if (!isset($out[$y])) continue;
        $out[$y] = [
            'total'     => (int)$r->total,
            'van_dong'  => (int)$r->van_dong,
            'nghe_noi'  => (int)$r->nghe_noi,
            'nhin'      => (int)$r->nhin,
            'than_kinh' => (int)$r->than_kinh,
            'tri_tue'   => (int)$r->tri_tue,
            'tu_ky'     => (int)$r->tu_ky,
            'hoc_tap'   => (int)$r->hoc_tap,
            'khac'      => (int)$r->khac,
            'can_learn' => (int)$r->can_learn,
            'access'    => (int)$r->access,
        ];
    }
    // dd($out);
    return $out;
}

    protected function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/2.7.TiH.TKKhuyetTat.xlsx'),

            'header' => [
                'province' => 'C1',
                'ward'     => 'C2',
                'school'   => 'C3',   // có thể trống nếu không lọc theo trường
            ],
            'dateCell'        => 'I4',  // ô ngày phía trên
            'startRow'        => 8,
            'sumRow'          => 9,
            'lastCol'         => 'O',
            'footerDateCell'  => 'J10',

            'col' => [
                'stt'       => 'A',
                'dob'       => 'B',
                'age'       => 'C',
                'total'     => 'D',
                'van_dong'  => 'E',
                'nghe_noi'  => 'F',
                'nhin'      => 'G',
                'than_kinh' => 'H',
                'tri_tue'   => 'I',
                'tu_ky'     => 'J',
                'hoc_tap'   => 'K',
                'khac'      => 'L',
                'can_learn' => 'M',
                'access'    => 'N',
                'ratio'     => 'O',
            ],
        ];
    }

    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
}
