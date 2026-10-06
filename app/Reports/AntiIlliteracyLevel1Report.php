<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Services\GeoService;
use App\Services\ReportFormatService;
 use PhpOffice\PhpSpreadsheet\Style\Alignment;
class AntiIlliteracyLevel1Report extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* -------------------- Helpers: cột cho 1 block -------------------- */
    private function makeCols(string $startCol): array
    {
        $start = Coordinate::columnIndexFromString($startCol);
        $at = fn (int $off) => Coordinate::stringFromColumnIndex($start + $off);

        return [
            'first' => $startCol,
            'last'  => $at(25),  // 26 cột / block (tùy theo template của bạn)

            // A..B: STT, Tên đơn vị (2 cột dùng chung 3 block)
            'stt'   => $at(0),
            'name'  => $at(1),

            // Dân số trong độ tuổi (4 cột)
            'pop_tot'  => $at(3),
            'pop_f'    => $at(4),
            'pop_eth'  => $at(5),
            'pop_feth' => $at(6),

            // Mù chữ MỨC 1 trong độ tuổi (đếm + các tỷ lệ % tương ứng)
            // Tổng số + các nhóm, mỗi nhóm có thêm cột tỷ lệ ngay sau
            'lv1_tot'      => $at(7),
            'lv1_f'        => $at(9),
            'lv1_eth'      => $at(11),
            'lv1_feth'     => $at(13),

            // các cột tỷ lệ đứng cạnh:
            'lv1_tot_rate' => $at(8),
            'lv1_f_rate'   => $at(10),
            'lv1_eth_rate' => $at(12),
            'lv1_feth_rate'=> $at(14),
        ];
    }

    private function fillBlock(
        Worksheet $sh,
        array $cols,
        array $rows,
        int $startRow,
        int $maxRows,
        bool $writeName // NEW
    ): void {
        $sumRow = $startRow + $maxRows;

        for ($i = 0; $i < $maxRows; $i++) {
            $r   = $startRow + $i;
            $stt = $i + 1;
            $row = $rows[$i] ?? null;

            // chỉ block đầu tiên mới ghi STT/Name
            if ($writeName) {
                $this->setCellValue($sh, $cols['stt'].$r,  $stt);
                $this->setCellValue($sh, $cols['name'].$r, $row['village_name'] ?? '—');
            }

            foreach (['pop_tot','pop_f','pop_eth','pop_feth','lv1_tot','lv1_f','lv1_eth','lv1_feth'] as $k) {
                $this->setCellValue($sh, $cols[$k].$r, $row[$k] ?? null);
            }

            // % = đếm / dân số * 100
            $this->setCellValue($sh, $cols['lv1_tot_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_tot'], $r, $cols['lv1_tot']));
            $this->setCellValue($sh, $cols['lv1_f_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_f'], $r, $cols['lv1_f']));
            $this->setCellValue($sh, $cols['lv1_eth_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_eth'], $r, $cols['lv1_eth']));
            $this->setCellValue($sh, $cols['lv1_feth_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_feth'], $r, $cols['lv1_feth']));
        }

        // Hàng tổng
        if ($writeName) {
            $this->setCellValue($sh, $cols['name'].$sumRow, 'Tổng cộng');
        }
        foreach (['pop_tot','pop_f','pop_eth','pop_feth','lv1_tot','lv1_f','lv1_eth','lv1_feth'] as $k) {
            $c = $cols[$k];
            $this->setCellValue($sh, $c.$sumRow, sprintf('=SUM(%s%d:%s%d)', $c, $startRow, $c, $sumRow-1));
        }
        foreach ([['lv1_tot_rate','pop_tot','lv1_tot'],
                ['lv1_f_rate','pop_f','lv1_f'],
                ['lv1_eth_rate','pop_eth','lv1_eth'],
                ['lv1_feth_rate','pop_feth','lv1_feth']] as [$rt,$den,$num]) {
            $this->setCellValue($sh, $cols[$rt].$sumRow,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols[$den], $sumRow, $cols[$num]));
        }

        // Định dạng số & căn giữa dòng tổng
        foreach (['pop_tot','pop_f','pop_eth','pop_feth','lv1_tot','lv1_f','lv1_eth','lv1_feth'] as $k) {
            $c = $cols[$k];
            $sh->getStyle(sprintf('%s%d:%s%d', $c, $startRow, $c, $sumRow))
            ->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
        }
        foreach (['lv1_tot_rate','lv1_f_rate','lv1_eth_rate','lv1_feth_rate'] as $k) {
            $c = $cols[$k];
            $sh->getStyle(sprintf('%s%d:%s%d', $c, $startRow, $c, $sumRow))
            ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
        }
        // căn giữa riêng hàng tổng cho phần cột của block
        $borderFrom = $writeName ? $cols['stt'] : $cols['pop_tot'];
        $sh->getStyle($borderFrom.$sumRow.':'.$cols['last'].$sumRow)
        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Viền block (bỏ cột STT/Name khi không viết)
     
    }

    /* -------------------- Query 1 dải tuổi / village -------------------- */
    private function queryRange(string $academic, string $scheme, array $geo, int $yMin, int $yMax): array
    {
        // latest education (năm học)
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        // latest “xóa mù”
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
            ->join('people as p','p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe',fn($j)=>$j->on('pe.person_id','=','p.id')->where('pe.rn',1))
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc',fn($j)=>$j->on('xmc.person_id','=','p.id')->where('xmc.rn',1))
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->leftJoin('geo_units as v','v.id','=','f.village_id')
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academic);

        $this->applyGeoFilter($qb, $scheme, $geo);

        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
        $isF   = "(p.is_female = 1)";
        $isEth = "(COALESCE(e.is_minority,0) = 1)";

        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // MỨC 1
        $lv1 = "( ({$grad} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";

        return $qb->selectRaw("
                f.village_id,
                COALESCE(v.name,'') as village_name,

                -- Dân số trong độ tuổi
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                        AS pop_tot,
                SUM(CASE WHEN {$ageY} AND {$isF} THEN 1 ELSE 0 END)             AS pop_f,
                SUM(CASE WHEN {$ageY} AND {$isEth} THEN 1 ELSE 0 END)           AS pop_eth,
                SUM(CASE WHEN {$ageY} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END)AS pop_feth,

                -- Mù chữ mức 1 trong độ tuổi
                SUM(CASE WHEN {$ageY} AND {$lv1} THEN 1 ELSE 0 END)             AS lv1_tot,
                SUM(CASE WHEN {$ageY} AND {$lv1} AND {$isF} THEN 1 ELSE 0 END)  AS lv1_f,
                SUM(CASE WHEN {$ageY} AND {$lv1} AND {$isEth} THEN 1 ELSE 0 END)AS lv1_eth,
                SUM(CASE WHEN {$ageY} AND {$lv1} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS lv1_feth
            ")
            ->groupBy('f.village_id','v.name')
            ->orderBy('v.name')
            ->get()
            ->map(fn($r)=>[
                'village_id'   => (int)$r->village_id,
                'village_name' => (string)$r->village_name,
                'pop_tot'=>(int)$r->pop_tot,'pop_f'=>(int)$r->pop_f,'pop_eth'=>(int)$r->pop_eth,'pop_feth'=>(int)$r->pop_feth,
                'lv1_tot'=>(int)$r->lv1_tot,'lv1_f'=>(int)$r->lv1_f,'lv1_eth'=>(int)$r->lv1_eth,'lv1_feth'=>(int)$r->lv1_feth,
            ])
            ->all();
    }

    /* -------------------- Export -------------------- */
    public function export(Request $req)
    {
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1] = array_map('intval', explode('-', $academic));
        $scheme = (string)$req->input('scheme','old');
        $geo    = (array)$req->input('geo', []);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $this->setCellValue($sh, $cfg['dateCell'],           $this->formatService->top_date());
        $sh->getStyle('E4:AJ4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // $sh->mergeCells('Z'. ($maxRows+2).":".'AD'. ($maxRows+2));
        // Lấy dữ liệu 3 dải tuổi
        $rowsA = $this->queryRange($academic, $scheme, $geo, $y1-25, $y1-15); // 15–25
        $rowsB = $this->queryRange($academic, $scheme, $geo, $y1-35, $y1-15); // 15–35
        $rowsC = $this->queryRange($academic, $scheme, $geo, $y1-60, $y1-15); // 15–60

        // Số dòng lớn nhất → chèn 1 lần
        $startRow = $cfg['startRow'];              // ví dụ 10
        $maxRows  = max(count($rowsA), count($rowsB), count($rowsC));
        $need     = max(0, $maxRows - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);

            // Copy format cho 3 block (tùy template của bạn: cột bắt đầu A / …)
            // $cols1 = $this->makeCols('A');
            // $cols2 = $this->makeCols('P');   // sửa nếu block 2 bắt đầu ở cột khác
            // $cols3 = $this->makeCols('AB');  // sửa nếu block 3 bắt đầu ở cột khác
            // for ($i = 1; $i <= $need; $i++) {
            //     $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cols1['last']);
            //     $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cols2['last']);
            //     $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cols3['last']);
            // }
        }

        // Đổ 3 block
       $this->fillBlock($sh, $this->makeCols('A'),  $rowsA, $startRow, $maxRows, true);   // block 1: có STT/Name
        $this->fillBlock($sh, $this->makeCols('M'),  $rowsB, $startRow, $maxRows, false);  // block 2: KHÔNG ghi STT/Name
        $this->fillBlock($sh, $this->makeCols('Y'), $rowsC, $startRow, $maxRows, false);  // block 3: KHÔNG ghi STT/Name
        // $this->drawThinBorders($sh, $startRow, $maxRows, 'A', 'AM');
        $lastrow = $startRow+ $maxRows+2;
        $this->setCellValue($sh, 'Z'.  $lastrow , $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
    
        $sh->getStyle('Z'. ( $lastrow).":".'AD'. ( $lastrow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->mergeCells('Z'. ( $lastrow).":".'AD'. ( $lastrow));
 
        // Xuất
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* -------------------- Template mapping -------------------- */
    private function templateCfg(): array
    {
        return [
            'path'   => resource_path('excel/5.5.XMC.TK.MuChu.Muc1.xlsx'),  // template mới của bạn
            'header' => [
                'province' => 'C1',
                'ward'     => 'C2',
            ],
            'dateCell'  => 'E4',   // sửa theo template
            'startRow'  => 10,     // dòng đầu data
        ];
    }

    
}
