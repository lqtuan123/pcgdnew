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

class LiteracyStudentsOutcomeReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* ============================================================
     * 1) Helper: build map cột cho 1 BLOCK (1 dải tuổi)
     *    Mỗi block gồm 2 cụm: HV học XMC  +  Được công nhận biết chữ
     * ============================================================ */
    private function makeCols(string $startCol): array
    {
        $start = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startCol);
        $at = fn(int $off) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start + $off);

        // 12 cột / block
        return [
            'first' => $startCol,

            // Học viên học XMC (4)
            'hv_tot'   => $at(0),
            'hv_f'     => $at(1),
            'hv_eth'   => $at(2),
            'hv_feth'  => $at(3),

            // Được công nhận biết chữ (8)
            'bc_tot'       => $at(4),
            'bc_tot_rate'  => $at(5),
            'bc_f'         => $at(6),
            'bc_f_rate'    => $at(7),
            'bc_eth'       => $at(8),
            'bc_eth_rate'  => $at(9),
            'bc_feth'      => $at(10),
            'bc_feth_rate' => $at(11),

            'last' => $at(11),
        ];
    }
    /* ============================================================
     * 2) Fill 1 block (1 dải tuổi)
     * ============================================================ */
    private function fillBlock(
        Worksheet $sh, array $c, array $rows, int $startRow, int $maxRows,
        bool $writeName, string $sttCol, string $nameCol
    ): void {
        $sumRow = $startRow + $maxRows;

        for ($i = 0; $i < $maxRows; $i++) {
            $r = $startRow + $i;
            $row = $rows[$i] ?? null;

            if ($writeName) {
                $this->setCellValue($sh, $sttCol.$r,  $i+1);
                $sh->mergeCells('B'.$r.':'.'C'.$r);
                $this->setCellValue($sh, $nameCol.$r, $row['village_name'] ?? '—');
            }

            foreach (['hv_tot','hv_f','hv_eth','hv_feth','bc_tot','bc_f','bc_eth','bc_feth'] as $k) {
                $this->setCellValue($sh, $c[$k].$r, $row[$k] ?? null);
            }

            // % “được công nhận biết chữ” trên số “học xoá mù chữ” tương ứng
            $this->setCellValue($sh, $c['bc_tot_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $c['hv_tot'],$r,$c['bc_tot']));
            $this->setCellValue($sh, $c['bc_f_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $c['hv_f'],$r,$c['bc_f']));
            $this->setCellValue($sh, $c['bc_eth_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $c['hv_eth'],$r,$c['bc_eth']));
            $this->setCellValue($sh, $c['bc_feth_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $c['hv_feth'],$r,$c['bc_feth']));
        }

        // Hàng “Tổng cộng”
        if ($writeName) $this->setCellValue($sh, $nameCol.$sumRow, 'Tổng cộng');

        foreach (['hv_tot','hv_f','hv_eth','hv_feth','bc_tot','bc_f','bc_eth','bc_feth'] as $k) {
            $col = $c[$k];
            $this->setCellValue($sh, $col.$sumRow, sprintf('=SUM(%s%d:%s%d)', $col,$startRow,$col,$sumRow-1));
        }
        foreach ([
            ['bc_tot_rate','hv_tot','bc_tot'],
            ['bc_f_rate','hv_f','bc_f'],
            ['bc_eth_rate','hv_eth','bc_eth'],
            ['bc_feth_rate','hv_feth','bc_feth'],
        ] as [$rt,$den,$num]) {
            $this->setCellValue($sh, $c[$rt].$sumRow,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $c[$den], $sumRow, $c[$num]));
        }
         $sh->getStyle('A'.$sumRow.':'.'AM'.$sumRow)
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }


    /* ============================================================
     * 3) Query: gộp theo thôn, trong 1 dải tuổi
     *     - HV học XMC: rule qua ReportFormatService
     *     - Được công nhận biết chữ: rule qua ReportFormatService
     * ============================================================ */
    private function queryRange(string $academic, string $scheme, array $geo, int $yMin, int $yMax): array
    {
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted',false))
            ->join('people as p','p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe', fn($j)=>$j->on('pe.person_id','=','p.id')->where('pe.rn',1))
            ->leftJoinSub($xmcLatest,'xmc',fn($j)=>$j->on('xmc.person_id','=','p.id')->where('xmc.rn',1))
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->leftJoin('geo_units as v','v.id','=','f.village_id')
            ->where('d.is_deleted',false)
            ->where('d.academic_year',$academic);

        $this->applyGeoFilter($qb, $scheme, $geo);

        // --- điều kiện theo dải tuổi
        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
        $isF   = "(p.is_female = 1)";
        $isEth = "(COALESCE(e.is_minority,0) = 1)";

        // --- RULE: học xoá mù chữ (HV)
        $hvRule = (int)($this->formatService->hoc_xmc_rule() ?? 1);
        if ($hvRule === 2) {
            $hv = "xmc.dang_hoc_lop IS NOT NULL";
        } elseif ($hvRule === 3) {
            $hv = "xmc.hoan_thanh_lop IS NOT NULL";
        } else { // 1: đang học hoặc đã hoàn thành
            $hv = "(xmc.dang_hoc_lop IS NOT NULL OR xmc.hoan_thanh_lop IS NOT NULL)";
        }

        // --- RULE: công nhận biết chữ (BC)
        $bcRule = (int)($this->formatService->cong_nhan_bc_rule() ?? 1);
        if ($bcRule === 2) {
            $bc = "(xmc.hoan_thanh_lop = 3)";
        } else { // 1: hoàn thành lớp 3 hoặc tái mù mức 2
            $bc = "(xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 2)";
        }

        return $qb->selectRaw("
                f.village_id,
                COALESCE(v.name,'') as village_name,

                -- DÂN SỐ trong độ tuổi (mẫu số)
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                                AS pop_tot,
                SUM(CASE WHEN {$ageY} AND {$isF} THEN 1 ELSE 0 END)                     AS pop_f,
                SUM(CASE WHEN {$ageY} AND {$isEth} THEN 1 ELSE 0 END)                   AS pop_eth,
                SUM(CASE WHEN {$ageY} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END)        AS pop_feth,

                -- HỌC VIÊN học xoá mù chữ
                SUM(CASE WHEN {$ageY} AND {$hv} THEN 1 ELSE 0 END)                      AS hv_tot,
                SUM(CASE WHEN {$ageY} AND {$hv} AND {$isF} THEN 1 ELSE 0 END)           AS hv_f,
                SUM(CASE WHEN {$ageY} AND {$hv} AND {$isEth} THEN 1 ELSE 0 END)         AS hv_eth,
                SUM(CASE WHEN {$ageY} AND {$hv} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS hv_feth,

                -- ĐƯỢC CÔNG NHẬN BIẾT CHỮ
                SUM(CASE WHEN {$ageY} AND {$bc} THEN 1 ELSE 0 END)                      AS bc_tot,
                SUM(CASE WHEN {$ageY} AND {$bc} AND {$isF} THEN 1 ELSE 0 END)           AS bc_f,
                SUM(CASE WHEN {$ageY} AND {$bc} AND {$isEth} THEN 1 ELSE 0 END)         AS bc_eth,
                SUM(CASE WHEN {$ageY} AND {$bc} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS bc_feth
            ")
            ->groupBy('f.village_id','v.name')
            ->orderBy('v.name')
            ->get()
            ->map(function($r){
                return [
                    'village_id'   => (int)$r->village_id,
                    'village_name' => (string)$r->village_name,
                    'pop_tot'=>(int)$r->pop_tot, 'pop_f'=>(int)$r->pop_f,
                    'pop_eth'=>(int)$r->pop_eth, 'pop_feth'=>(int)$r->pop_feth,
                    'hv_tot'=>(int)$r->hv_tot, 'hv_f'=>(int)$r->hv_f,
                    'hv_eth'=>(int)$r->hv_eth, 'hv_feth'=>(int)$r->hv_feth,
                    'bc_tot'=>(int)$r->bc_tot, 'bc_f'=>(int)$r->bc_f,
                    'bc_eth'=>(int)$r->bc_eth, 'bc_feth'=>(int)$r->bc_feth,
                ];
            })
            ->all();
    }

    /* ============================================================
     * 4) Export
     * ============================================================ */
    public function export(Request $req)
    {
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme','old');
        $geo      = (array)  $req->input('geo', []);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $this->setCellValue($sh, $cfg['dateCell'],           $this->formatService->top_date());
        $sh->getStyle('E4:AJ4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Lấy dữ liệu 3 dải tuổi
        $rowsA = $this->queryRange($academic,$scheme,$geo,$y1-25,$y1-15); // 15–25
        $rowsB = $this->queryRange($academic,$scheme,$geo,$y1-35,$y1-15); // 15–35
        $rowsC = $this->queryRange($academic,$scheme,$geo,$y1-60,$y1-15); // 15–60

        // Số dòng lớn nhất và chèn 1 lần
        $startRow = $cfg['startRow'];                      // ví dụ 9
        $maxRows  = max(count($rowsA),count($rowsB),count($rowsC));
        $need     = max(0,$maxRows - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            // Nếu cần replicate merge + style: copy theo hàng mẫu (tuỳ template)
            for ($i=1; $i<=$need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow+$i, $cfg['lastCol']);
            }
        }

        // Đổ 3 block (STT/Tên chỉ ở block 1)
      // Block 1 (in STT/Đơn vị)
        $this->fillBlock(
            $sh, $this->makeCols($cfg['block_starts']['b1']), $rowsA,
            $startRow, $maxRows, true, $cfg['sttCol'], $cfg['nameCol']
        );
        // Block 2 & 3 (không in STT/Đơn vị)
        $this->fillBlock(
            $sh, $this->makeCols($cfg['block_starts']['b2']), $rowsB,
            $startRow, $maxRows, false, $cfg['sttCol'], $cfg['nameCol']
        );
        $this->fillBlock(
            $sh, $this->makeCols($cfg['block_starts']['b3']), $rowsC,
            $startRow, $maxRows, false, $cfg['sttCol'], $cfg['nameCol']
        );
         
        $lastRow = $startRow + $maxRows + 2;
        $this->setCellValue($sh, 'Y'.$lastRow, $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
        $sh->mergeCells('Y'.$lastRow.':AE'.$lastRow);
        $sh->getStyle('Y'.$lastRow.':AE'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb,'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* ============================================================
     * 5) Template mapping (CHỈNH CỘT BẮT ĐẦU Ở ĐÂY)
     * ============================================================ */
    private function templateCfg(): array
    {
        return [
            'path'   => resource_path('excel/5.7.XMC.KetQua.Muc1.xlsx'), // đổi tên file thật của em
            'header' => [
                'province' => 'C1',
                'ward' => 'C2',
               
            ],
            'sttCol'=>'A',
             'nameCol'=>'B',
            'dateCell'       => 'E4',       // ô “Ngày …”
            'startRow'       => 10,          // dòng đầu danh sách
            'lastCol'        => 'AM',       // cột cuối của hàng mẫu (để replicate style/merge)
            'footerDateCell' => null,       // có thể đặt ô ở cuối nếu muốn

            // 3 block: bắt đầu ở các cột (KHỚP template của em)
            // B = STT, C = Đơn vị, block1 bắt đầu D; block2 ở N; block3 ở Z (ví dụ)
            'block_starts' => [
                'b1' => 'D',   // 15–25 (Học + Công nhận)
                'b2' => 'P',   // 15–35
                'b3' => 'AB',   // 15–60
            ],
        ];
    }
}
