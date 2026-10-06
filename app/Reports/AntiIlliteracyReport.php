<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
 use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AntiIlliteracyReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
private function makeCols(string $startCol): array {
    // offsets theo thứ tự các cột trong 1 block
    $start = Coordinate::columnIndexFromString($startCol); // A=1
    $at = fn(int $off) => Coordinate::stringFromColumnIndex($start + $off);

    return [
        'startCol' => $startCol,
        'lastCol'  => $at(27), // 28 cột / block
        'header'=> $at(3),
        'stt' => $at(0),
        'name'=> $at(1),
        'm_name'=> $at(3),
        'pop_all_tot'  => $at(4),
        'pop_all_f'    => $at(5),
        'pop_all_eth'  => $at(6),
        'pop_all_feth' => $at(7),

        'pop_y_tot'  => $at(8),
        'pop_y_f'    => $at(9),
        'pop_y_eth'  => $at(10),
        'pop_y_feth' => $at(11),

        'lv1_tot'       => $at(12),
        'lv1_tot_rate'  => $at(13),
        'lv1_f'         => $at(14),
        'lv1_f_rate'    => $at(15),
        'lv1_eth'       => $at(16),
        'lv1_eth_rate'  => $at(17),
        'lv1_feth'      => $at(18),
        'lv1_feth_rate' => $at(19),

        'lv2_tot'       => $at(20),
        'lv2_tot_rate'  => $at(21),
        'lv2_f'         => $at(22),
        'lv2_f_rate'    => $at(23),
        'lv2_eth'       => $at(24),
        'lv2_eth_rate'  => $at(25),
        'lv2_feth'      => $at(26),
        'lv2_feth_rate' => $at(27),
    ];
}
private function fillBlockData(Worksheet $sh, array $cols, array $rows, int $startRow, int $maxRows, array $geo): void
{
    // dd($rows);
    $sumRow = $startRow + $maxRows;

    // đổ dữ liệu theo hàng 0..$maxRows-1 (nếu thiếu thì để trống)
     $dataKey = [
        // Tổng dân số (mọi tuổi)
        'pop_all_tot'  => 'all_tot',
        'pop_all_f'    => 'all_f',
        'pop_all_eth'  => 'all_eth',
        'pop_all_feth' => 'all_feth',
        // Dân số theo tuổi
        'pop_y_tot'    => 'y_tot',
        'pop_y_f'      => 'y_f',
        'pop_y_eth'    => 'y_eth',
        'pop_y_feth'   => 'y_feth',
        // Các cột lv1/lv2 trùng tên
        'lv1_tot'      => 'lv1_tot',
        'lv1_f'        => 'lv1_f',
        'lv1_eth'      => 'lv1_eth',
        'lv1_feth'     => 'lv1_feth',
        'lv2_tot'      => 'lv2_tot',
        'lv2_f'        => 'lv2_f',
        'lv2_eth'      => 'lv2_eth',
        'lv2_feth'     => 'lv2_feth',
    ];
 // header chung
    $this->setCellValue($sh, $cols['header'].'1', ':'.$this->geoService->geoNameFromId($geo['province_id'] ?? null));
    $this->setCellValue($sh, $cols['header'].'2',   ':'.  $this->geoService->geoNameFromId($geo['ward_id'] ?? null));

    for ($i=0; $i<$maxRows; $i++) {
        $rnum = $startRow + $i;
        $stt  = $i + 1;
        $row  = $rows[$i] ?? null;

        $this->setCellValue($sh, $cols['stt'].$rnum,  $stt);
        $this->setCellValue($sh, $cols['name'].$rnum, $row['village_name'] ?? '—');

        // ghi các cột số đếm
        foreach ($dataKey as $sheetKey => $rowKey) {
            $this->setCellValue($sh, $cols[$sheetKey].$rnum, $row[$rowKey] ?? null);
        }

        // tỷ lệ theo hàng (đếm / dân số theo tuổi) * 100
        $this->setCellValue($sh, $cols['lv1_tot_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_tot'],$rnum,$cols['lv1_tot']));
        $this->setCellValue($sh, $cols['lv1_f_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_f'],$rnum,$cols['lv1_f']));
        $this->setCellValue($sh, $cols['lv1_eth_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_eth'],$rnum,$cols['lv1_eth']));
        $this->setCellValue($sh, $cols['lv1_feth_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_feth'],$rnum,$cols['lv1_feth']));

        $this->setCellValue($sh, $cols['lv2_tot_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_tot'],$rnum,$cols['lv2_tot']));
        $this->setCellValue($sh, $cols['lv2_f_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_f'],$rnum,$cols['lv2_f']));
        $this->setCellValue($sh, $cols['lv2_eth_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_eth'],$rnum,$cols['lv2_eth']));
        $this->setCellValue($sh, $cols['lv2_feth_rate'].$rnum,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',$cols['pop_y_feth'],$rnum,$cols['lv2_feth']));
    }

    // Hàng tổng (SUM) + tỷ lệ tổng (tính lại từ tổng)
    $this->setCellValue($sh, $cols['name'].$sumRow, 'Tổng cộng');
    $firstDataRow = $startRow; $lastDataRow = $sumRow - 1;

    foreach ([
        'pop_all_tot','pop_all_f','pop_all_eth','pop_all_feth',
        'pop_y_tot','pop_y_f','pop_y_eth','pop_y_feth',
        'lv1_tot','lv1_f','lv1_eth','lv1_feth',
        'lv2_tot','lv2_f','lv2_eth','lv2_feth',
    ] as $k) {
        $col = $cols[$k];
        $this->setCellValue($sh, $col.$sumRow, sprintf('=SUM(%s%d:%s%d)', $col, $firstDataRow, $col, $lastDataRow));
    }
    foreach ([
        ['lv1_tot_rate','pop_y_tot','lv1_tot'],
        ['lv1_f_rate','pop_y_f','lv1_f'],
        ['lv1_eth_rate','pop_y_eth','lv1_eth'],
        ['lv1_feth_rate','pop_y_feth','lv1_feth'],
        ['lv2_tot_rate','pop_y_tot','lv2_tot'],
        ['lv2_f_rate','pop_y_f','lv2_f'],
        ['lv2_eth_rate','pop_y_eth','lv2_eth'],
        ['lv2_feth_rate','pop_y_feth','lv2_feth'],
    ] as [$rateKey,$denKey,$numKey]) {
        $this->setCellValue($sh, $cols[$rateKey].$sumRow,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols[$denKey], $sumRow, $cols[$numKey]));
    }

    // Format
    $first = $startRow; $last = $sumRow;
    foreach ([
        'pop_all_tot','pop_all_f','pop_all_eth','pop_all_feth',
        'pop_y_tot','pop_y_f','pop_y_eth','pop_y_feth',
        'lv1_tot','lv1_f','lv1_eth','lv1_feth',
        'lv2_tot','lv2_f','lv2_eth','lv2_feth',
    ] as $k) {
        $col = $cols[$k];
        $sh->getStyle(sprintf('%s%d:%s%d', $col, $first, $col, $last))
           ->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
    }
    foreach ([
        'lv1_tot_rate','lv1_f_rate','lv1_eth_rate','lv1_feth_rate',
        'lv2_tot_rate','lv2_f_rate','lv2_eth_rate','lv2_feth_rate',
    ] as $k) {
        $col = $cols[$k];
        $sh->getStyle(sprintf('%s%d:%s%d', $col, $first, $col, $last))
           ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
    }
 for ($r = $startRow; $r <= $sumRow; $r++) {
                    // Nếu có helper safeMerge thì dùng: $this->safeMerge($sh, "B{$r}:C{$r}");
                    $sh->mergeCells($cols['name']."{$r}:".$cols['m_name']."{$r}");
                }
    // Viền cho block
    $this->drawThinBorders($sh, $startRow, $sumRow, $cols['stt'], $cols['lastCol']);
}

   
public function export(Request $req)
{
    $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
    [$y1]     = array_map('intval', explode('-', $academic));
    $scheme   = (string) $req->input('scheme', 'old');
    $geo      = (array)  $req->input('geo', []);

    $cfg = $this->templateCfg();
    $wb  = IOFactory::load($cfg['path']);
    $sh  = $wb->getActiveSheet();

   
    // 1) Lấy dữ liệu cả 3 dải tuổi
    $rowsA = $this->queryByVillage($academic, $scheme, $geo, $y1-25, $y1-15); // 15–25
    $rowsB = $this->queryByVillage($academic, $scheme, $geo, $y1-35, $y1-15); // 15–35
    $rowsC = $this->queryByVillage($academic, $scheme, $geo, $y1-60, $y1-15); // 15–60

    // 2) Tính số dòng lớn nhất, chèn 1 lần
    $startRow = $cfg['startRow']; // ví dụ 8
    $maxRows  = max(count($rowsA), count($rowsB), count($rowsC));
    $need     = max(0, $maxRows - 1); // đã có sẵn 1 dòng mẫu
    if ($need > 0) {
        $sh->insertNewRowBefore($startRow + 1, $need);

        // replicate format cho CẢ 3 block trên các dòng mới
        $cols1 = $this->makeCols('A');   // sửa theo cột bắt đầu block 1
        $cols2 = $this->makeCols('AC');  // sửa theo cột bắt đầu block 2
        $cols3 = $this->makeCols('BE');  // sửa theo cột bắt đầu block 3

        for ($i=1; $i <= $need; $i++) {
            $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cols1['lastCol']);
            $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cols2['lastCol']);
            $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cols3['lastCol']);
        }
    }
    $sumRow = $startRow + $maxRows;

    // 3) Đổ từng block (không chèn dòng nữa)
    $common = ['academic'=>$academic,'scheme'=>$scheme,'geo'=>$geo,'startRow'=>$startRow];
    $this->fillBlockData($sh, $this->makeCols('A'),  $rowsA, $startRow, $maxRows,$geo);  // 15–25
    $this->fillBlockData($sh, $this->makeCols('AC'), $rowsB, $startRow, $maxRows,$geo);  // 15–35
    $this->fillBlockData($sh, $this->makeCols('BE'), $rowsC, $startRow, $maxRows,$geo);  // 15–60
    
    // dd('CB'. $sumRow);
    $this->setCellValue($sh, 'BY'. ($sumRow+2) , $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
    
    $sh->getStyle('BY'. ($sumRow+2).":".'CE'. ($sumRow+2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sh->mergeCells('BY'. ($sumRow+2).":".'CE'. ($sumRow+2));
    
    // 4) Xuất
    return response()->streamDownload(function () use ($wb) {
        $w = IOFactory::createWriter($wb, 'Xlsx');
        $w->setPreCalculateFormulas(false);
        $w->save('php://output');
    }, $this->getExportFilename($cfg['path']), [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}


  protected function queryByVillage(string $academic, string $scheme, array $geo, int $yMin, int $yMax): array
{
    // 1) latest education theo năm học
    $peLatest = DB::table('person_educations as pe1')
        ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
        ->where('pe1.academic_year', $academic);

    // 2) latest “xoá mù chữ”
    $xmcLatest = DB::table('person_literacy as x')
        ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

    // 3) base query
    $qb = DB::table('survey_form_details as d')
        ->join('survey_forms as f', function($j){
            $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
        })
        ->join('people as p', 'p.id', '=', 'd.person_id')
        ->leftJoinSub($peLatest, 'pe', function($j){
            $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
        })
        ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
        ->leftJoinSub($xmcLatest, 'xmc', function($j){
            $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
        })
        // ⬇️ thêm JOIN dân tộc để lấy is_minority
        ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
        ->leftJoin('geo_units as v', 'v.id', '=', 'f.village_id')
        ->where('d.is_deleted', false)
        ->where('d.academic_year', $academic);

    // 4) lọc địa bàn
    $this->applyGeoFilter($qb, $scheme, $geo);

    // 5) flags/điều kiện
    // ==== 5) flags/điều kiện ====
// Tuổi theo năm sinh (bạn đã tính $yMin, $yMax)
        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
        $isF   = "(p.is_female = 1)";
        $isEth = "(COALESCE(e.is_minority, 0) = 1)";

        // Bỏ học lớp -> số (rỗng/null = 0)
        $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop), '') AS UNSIGNED), 0)";

        // Tốt nghiệp PT < 2 (đủ ngoặc!)
        $grad  = "(COALESCE(pe.graduation_level_id, 0) < 2)";

        // Mức 1: tuổi phù hợp AND ( (chưa TN PT & bỏ học ≤3) OR (XMC hoàn thành 3 hoặc tái mù 1) )
        $lv1 = "(
        {$ageY}
        AND (
            ( {$grad} AND ({$ebn} <= 3) )
            OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1)
        )
        )";
        if ($this->formatService->cong_thuc_baocao(5,37) == 1)
        {

            // Mức 2: tuổi phù hợp AND ( (chưa TN PT & 3< bỏ học ≤5) OR (XMC hoàn thành 5 hoặc tái mù 2) )
            $lv2 = "(
            {$ageY}
            AND (
                ( {$grad} AND ( {$ebn} <= 5) )
                OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2)
            )
            )";
        }
        else
        if ($this->formatService->cong_thuc_baocao(5,37) == 2)
        {

            // Mức 2: tuổi phù hợp AND ( (chưa TN PT & 3< bỏ học ≤5) OR (XMC hoàn thành 5 hoặc tái mù 2) )
            $lv2 = "(
            {$ageY}
            AND (
                ( {$grad} AND ({$ebn} > 3 AND {$ebn} <= 5) )
                OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2)
            )
            )";
        }

    // 6) select gộp theo thôn/buôn
    $rows = $qb->selectRaw("
            f.village_id,
            COALESCE(v.name, '') as village_name,

            -- Tổng dân số mọi tuổi
            COUNT(*)                                                   as all_tot,
            SUM(CASE WHEN {$isF} THEN 1 ELSE 0 END)                    as all_f,
            SUM(CASE WHEN {$isEth} THEN 1 ELSE 0 END)                  as all_eth,
            SUM(CASE WHEN {$isF} AND {$isEth} THEN 1 ELSE 0 END)       as all_feth,

            -- Dân số 15–25
            SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                   as y_tot,
            SUM(CASE WHEN {$ageY} AND {$isF} THEN 1 ELSE 0 END)        as y_f,
            SUM(CASE WHEN {$ageY} AND {$isEth} THEN 1 ELSE 0 END)      as y_eth,
            SUM(CASE WHEN {$ageY} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) as y_feth,

            
            SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END)                    as lv1_tot,
            SUM(CASE WHEN {$lv1} AND {$isF} THEN 1 ELSE 0 END)         as lv1_f,
            SUM(CASE WHEN {$lv1} AND {$isEth} THEN 1 ELSE 0 END)       as lv1_eth,
            SUM(CASE WHEN {$lv1} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) as lv1_feth,

            
            SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END)                    as lv2_tot,
            SUM(CASE WHEN {$lv2} AND {$isF} THEN 1 ELSE 0 END)         as lv2_f,
            SUM(CASE WHEN {$lv2} AND {$isEth} THEN 1 ELSE 0 END)       as lv2_eth,
            SUM(CASE WHEN {$lv2} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) as lv2_feth
        ")
        ->groupBy('f.village_id','v.name')
        ->orderBy('v.name')
        ->get()
        ->map(function($r){
            return [
                'village_id'   => (int)$r->village_id,
                'village_name' => (string)$r->village_name,
                'all_tot'  => (int)$r->all_tot,
                'all_f'    => (int)$r->all_f,
                'all_eth'  => (int)$r->all_eth,
                'all_feth' => (int)$r->all_feth,
                'y_tot'    => (int)$r->y_tot,
                'y_f'      => (int)$r->y_f,
                'y_eth'    => (int)$r->y_eth,
                'y_feth'   => (int)$r->y_feth,
                'lv1_tot'  => (int)$r->lv1_tot,
                'lv1_f'    => (int)$r->lv1_f,
                'lv1_eth'  => (int)$r->lv1_eth,
                'lv1_feth' => (int)$r->lv1_feth,
                'lv2_tot'  => (int)$r->lv2_tot,
                'lv2_f'    => (int)$r->lv2_f,
                'lv2_eth'  => (int)$r->lv2_eth,
                'lv2_feth' => (int)$r->lv2_feth,
            ];
        })
        ->all();
        // dd($rows);
    return $rows;
}

    protected function templateCfg(): array
    {
        // Map cột khớp layout bạn chụp:  A..AC
        return [
            'path' => resource_path('excel/5.1.XMC.TinhHinh.15.60Tuoi.xlsx'),  // đổi đúng file mẫu
            'header' => [
                'province' => 'D1',   // “Đắk Lắk”
                'ward'     => 'D2',   // “Ea Kar”
            ],
            'dateCell'       => 'P4',
            'startRow'       => 8,
            'sumRow'         => 0,   // tự tính trong code
            'lastCol'        => 'AC',
            'footerDateCell' => 'Z10',

            'col' => [
                'stt' => 'A',
                'name'=> 'B',

                // Tổng dân số (mọi tuổi)
                'pop_all_tot'  => 'E',
                'pop_all_f'    => 'F',
                'pop_all_eth'  => 'G',
                'pop_all_feth' => 'H',

                // Dân số 15–25
                'pop_y_tot'  => 'I',
                'pop_y_f'    => 'J',
                'pop_y_eth'  => 'K',
                'pop_y_feth' => 'L',

                // Mức 1 (đếm + %)
                'lv1_tot'       => 'M',
                'lv1_tot_rate'  => 'N',
                'lv1_f'         => 'O',
                'lv1_f_rate'    => 'P',
                'lv1_eth'       => 'Q',
                'lv1_eth_rate'  => 'R',
                'lv1_feth'      => 'S',
                'lv1_feth_rate' => 'T',

                // Mức 2 (đếm + %)
                'lv2_tot'       => 'U',
                'lv2_tot_rate'  => 'V',
                'lv2_f'         => 'W',
                'lv2_f_rate'    => 'X',
                'lv2_eth'       => 'Y',
                'lv2_eth_rate'  => 'Z',
                'lv2_feth'      => 'AA',
                'lv2_feth_rate' => 'AB',
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
