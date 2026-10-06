<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Services\GeoService;
use App\Services\ReportFormatService;

class AntiIlliteracyLevel2Report extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* -------------------- Helpers: cột cho 1 block -------------------- */
    private function makeCols(string $startCol): array
    {
        // Map cột dùng lại như report Mức 1 của bạn (block 1 ở A, block 2 ở M, block 3 ở Y)
        // Nếu template khác, chỉ cần đổi 3 vị trí gọi makeCols() trong export()
        $start = Coordinate::columnIndexFromString($startCol);
        $at = fn (int $off) => Coordinate::stringFromColumnIndex($start + $off);

        return [
            'first' => $startCol,
            'last'  => $at(15),  // đủ phủ các cột số liệu của 1 block (A..P nếu start=A)

            // A..B: STT, Tên đơn vị (2 cột — chỉ viết ở block trái)
            'stt'   => $at(0),
            'name'  => $at(1),

            // Dân số trong độ tuổi
            'pop_tot'  => $at(3),
            'pop_f'    => $at(4),
            'pop_eth'  => $at(5),
            'pop_feth' => $at(6),

            // Mù chữ MỨC 2 trong độ tuổi (đếm + tỷ lệ % kề bên)
            'lv2_tot'      => $at(7),
            'lv2_tot_rate' => $at(8),

            'lv2_f'        => $at(9),
            'lv2_f_rate'   => $at(10),

            'lv2_eth'      => $at(11),
            'lv2_eth_rate' => $at(12),

            'lv2_feth'     => $at(13),
            'lv2_feth_rate'=> $at(14),
        ];
    }

    /* -------------------- Điều kiện mức 2 (loại 1 / loại 2) -------------------- */
    private function lv2SqlFragment(): string
    {
        // Chuẩn hóa “bỏ học lớp” về số
        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        // Chưa TN PT
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // Lấy loại quy tắc (bạn dùng sẵn cong_thuc_baocao(5,37))
        $type = $this->formatService->cong_thuc_baocao(5,37);
        $drop = ($type == 1) ? "({$ebn} <= 5)" : "({$ebn} > 3 AND {$ebn} <= 5)";

        // Mức 2 theo yêu cầu
        return "( ({$grad} AND {$drop}) OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";
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

        $lv2 = $this->lv2SqlFragment();

        return $qb->selectRaw("
                f.village_id,
                COALESCE(v.name,'') as village_name,

                -- Dân số trong độ tuổi
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                        AS pop_tot,
                SUM(CASE WHEN {$ageY} AND {$isF} THEN 1 ELSE 0 END)             AS pop_f,
                SUM(CASE WHEN {$ageY} AND {$isEth} THEN 1 ELSE 0 END)           AS pop_eth,
                SUM(CASE WHEN {$ageY} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END)AS pop_feth,

                -- Mù chữ mức 2 trong độ tuổi
                SUM(CASE WHEN {$ageY} AND {$lv2} THEN 1 ELSE 0 END)             AS lv2_tot,
                SUM(CASE WHEN {$ageY} AND {$lv2} AND {$isF} THEN 1 ELSE 0 END)  AS lv2_f,
                SUM(CASE WHEN {$ageY} AND {$lv2} AND {$isEth} THEN 1 ELSE 0 END)AS lv2_eth,
                SUM(CASE WHEN {$ageY} AND {$lv2} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS lv2_feth
            ")
            ->groupBy('f.village_id','v.name')
            ->orderBy('v.name')
            ->get()
            ->map(fn($r)=>[
                'village_id'   => (int)$r->village_id,
                'village_name' => (string)$r->village_name,
                'pop_tot'=>(int)$r->pop_tot,'pop_f'=>(int)$r->pop_f,'pop_eth'=>(int)$r->pop_eth,'pop_feth'=>(int)$r->pop_feth,
                'lv2_tot'=>(int)$r->lv2_tot,'lv2_f'=>(int)$r->lv2_f,'lv2_eth'=>(int)$r->lv2_eth,'lv2_feth'=>(int)$r->lv2_feth,
            ])
            ->all();
    }

    /* -------------------- Fill 1 block -------------------- */
    private function fillBlock(
        Worksheet $sh,
        array $cols,
        array $rows,
        int $startRow,
        int $maxRows,
        bool $writeName // chỉ block trái ghi STT/Name
    ): void {
        $sumRow = $startRow + $maxRows;

        for ($i = 0; $i < $maxRows; $i++) {
            $r   = $startRow + $i;
            $stt = $i + 1;
            $row = $rows[$i] ?? null;

            if ($writeName) {
                $this->setCellValue($sh, $cols['stt'].$r,  $stt);
                $this->setCellValue($sh, $cols['name'].$r, $row['village_name'] ?? '—');
            }

            foreach (['pop_tot','pop_f','pop_eth','pop_feth','lv2_tot','lv2_f','lv2_eth','lv2_feth'] as $k) {
                $this->setCellValue($sh, $cols[$k].$r, $row[$k] ?? null);
            }

            // % = đếm / dân số * 100
            $this->setCellValue($sh, $cols['lv2_tot_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_tot'], $r, $cols['lv2_tot']));
            $this->setCellValue($sh, $cols['lv2_f_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_f'],   $r, $cols['lv2_f']));
            $this->setCellValue($sh, $cols['lv2_eth_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_eth'], $r, $cols['lv2_eth']));
            $this->setCellValue($sh, $cols['lv2_feth_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols['pop_feth'],$r, $cols['lv2_feth']));
        }

        // Hàng tổng
        if ($writeName) {
            $this->setCellValue($sh, $cols['name'].$sumRow, 'Tổng cộng');
        }
        foreach (['pop_tot','pop_f','pop_eth','pop_feth','lv2_tot','lv2_f','lv2_eth','lv2_feth'] as $k) {
            $c = $cols[$k];
            $this->setCellValue($sh, $c.$sumRow, sprintf('=SUM(%s%d:%s%d)', $c, $startRow, $c, $sumRow-1));
        }
        foreach ([['lv2_tot_rate','pop_tot','lv2_tot'],
                  ['lv2_f_rate','pop_f','lv2_f'],
                  ['lv2_eth_rate','pop_eth','lv2_eth'],
                  ['lv2_feth_rate','pop_feth','lv2_feth']] as [$rt,$den,$num]) {
            $this->setCellValue($sh, $cols[$rt].$sumRow,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $cols[$den], $sumRow, $cols[$num]));
        }

        // Định dạng số & căn giữa hàng tổng
        foreach (['pop_tot','pop_f','pop_eth','pop_feth','lv2_tot','lv2_f','lv2_eth','lv2_feth'] as $k) {
            $c = $cols[$k];
            $sh->getStyle(sprintf('%s%d:%s%d', $c, $startRow, $c, $sumRow))
               ->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
        }
        foreach (['lv2_tot_rate','lv2_f_rate','lv2_eth_rate','lv2_feth_rate'] as $k) {
            $c = $cols[$k];
            $sh->getStyle(sprintf('%s%d:%s%d', $c, $startRow, $c, $sumRow))
               ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
        }
        $borderFrom = $writeName ? $cols['stt'] : $cols['pop_tot'];
        $sh->getStyle($borderFrom.$sumRow.':'.$cols['last'].$sumRow)
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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

        // Lấy dữ liệu 3 dải tuổi
        $rowsA = $this->queryRange($academic, $scheme, $geo, $y1-25, $y1-15); // 15–25
        $rowsB = $this->queryRange($academic, $scheme, $geo, $y1-35, $y1-15); // 15–35
        $rowsC = $this->queryRange($academic, $scheme, $geo, $y1-60, $y1-15); // 15–60

        // Số dòng lớn nhất → chèn 1 lần (nếu cần)
        $startRow = $cfg['startRow'];              // ví dụ 10
        $maxRows  = max(count($rowsA), count($rowsB), count($rowsC));
        $need     = max(0, $maxRows - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            // Nếu bạn muốn replicate format/merge theo mẫu 1 dòng sẵn có:
            // $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $colsX['last']);
        }

        // Đổ 3 block (chỉ block 1 có STT/Name)
        $this->fillBlock($sh, $this->makeCols('A'),  $rowsA, $startRow, $maxRows, true);
        $this->fillBlock($sh, $this->makeCols('M'),  $rowsB, $startRow, $maxRows, false);
        $this->fillBlock($sh, $this->makeCols('Y'),  $rowsC, $startRow, $maxRows, false);

        // Footer ngày tháng (tùy template)
        $lastRow = $startRow + $maxRows + 2;
        $this->setCellValue($sh, 'Z'.$lastRow, $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
        $sh->mergeCells('Z'.$lastRow.':AD'.$lastRow);
        $sh->getStyle('Z'.$lastRow.':AD'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Xuất file
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
            'path'   => resource_path('excel/5.6.XMC.TK.MuChu.Muc2.xlsx'),
            'header' => [
                'province' => 'C1',
                'ward'     => 'C2',
            ],
            'dateCell'  => 'E4',
            'startRow'  => 10,
        ];
    }
}
