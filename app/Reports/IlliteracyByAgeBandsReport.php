<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
class IlliteracyByAgeBandsReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);

        // 3 dải tuổi theo năm sinh
        $bands = [
            ['label' => '15 - 25 tuổi', 'ymin' => $y1-25, 'ymax' => $y1-15],
            ['label' => '26 - 35 tuổi', 'ymin' => $y1-35, 'ymax' => $y1-26],
            ['label' => '36 - 60 tuổi', 'ymin' => $y1-60, 'ymax' => $y1-36],
        ];

        // Lấy dữ liệu đã gộp theo dải tuổi
        $rows = $this->queryByBands($academic, $scheme, $geo, $bands);

        // Mở template
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));

        // Số dòng cần in (3 dòng + 1 dòng Cộng). Mặc định template có sẵn 1 dòng mẫu.
        $startRow = $cfg['startRow']; // ví dụ 9
        $need     = max(0, count($rows) - 1);
        // if ($need > 0) {
        //     // $sh->insertNewRowBefore($startRow + 1, $need);
        //     for ($i=1; $i<=$need; $i++) {
        //         // $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
        //     }
        // }
        $sumRow = $startRow + count($rows);

        // Đổ dữ liệu 3 dải
        $r = $startRow;
        foreach ($rows as $row) {
            // Nhãn nhóm tuổi
            $this->setCellValue($sh, $cfg['col']['band'].$r, $row['label']);

            // Dân số trong độ tuổi
            $this->setCellValue($sh, $cfg['col']['pop_tot'].$r,  $row['pop_tot']  ?: null);
            $this->setCellValue($sh, $cfg['col']['pop_f'].$r,    $row['pop_f']    ?: null);
            $this->setCellValue($sh, $cfg['col']['pop_eth'].$r,  $row['pop_eth']  ?: null);
            $this->setCellValue($sh, $cfg['col']['pop_rel'].$r,  $row['pop_rel']  ?: null);

            // Mù chữ mức 1
            $this->setCellValue($sh, $cfg['col']['lv1_tot'].$r,  $row['lv1_tot']  ?: null);
            $this->setCellValue($sh, $cfg['col']['lv1_f'].$r,    $row['lv1_f']    ?: null);
            $this->setCellValue($sh, $cfg['col']['lv1_eth'].$r,  $row['lv1_eth']  ?: null);
            $this->setCellValue($sh, $cfg['col']['lv1_rel'].$r,  $row['lv1_rel']  ?: null);

            // Mù chữ mức 2
            $this->setCellValue($sh, $cfg['col']['lv2_tot'].$r,  $row['lv2_tot']  ?: null);
            $this->setCellValue($sh, $cfg['col']['lv2_f'].$r,    $row['lv2_f']    ?: null);
            $this->setCellValue($sh, $cfg['col']['lv2_eth'].$r,  $row['lv2_eth']  ?: null);
            $this->setCellValue($sh, $cfg['col']['lv2_rel'].$r,  $row['lv2_rel']  ?: null);

            // Tỷ lệ mù chữ = (mù chữ tổng) / dân số * 100
            $this->setCellValue(
                $sh,
                $cfg['col']['rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND((%3$s%2$d+%4$s%2$d)/%1$s%2$d*100,2))',
                    $cfg['col']['pop_tot'], $r, $cfg['col']['lv1_tot'], $cfg['col']['lv2_tot'])
            );

            // Tái mù chữ
            $this->setCellValue($sh, $cfg['col']['re_tot'].$r,   $row['re_tot']   ?: null);
            $this->setCellValue($sh, $cfg['col']['re_f'].$r,     $row['re_f']     ?: null);
            $this->setCellValue($sh, $cfg['col']['re_eth'].$r,   $row['re_eth']   ?: null);
            $this->setCellValue($sh, $cfg['col']['re_rel'].$r,   $row['re_rel']   ?: null);

            $r++;
        }

        // Dòng “Cộng”
        $this->setCellValue($sh, $cfg['col']['band'].$sumRow, 'Cộng');
        foreach ([
            'pop_tot','pop_f','pop_eth','pop_rel',
            'lv1_tot','lv1_f','lv1_eth','lv1_rel',
            'lv2_tot','lv2_f','lv2_eth','lv2_rel',
            're_tot','re_f','re_eth','re_rel',
        ] as $k) {
            $col = $cfg['col'][$k];
            $this->setCellValue(
                $sh,
                $col.$sumRow,
                sprintf('=SUM(%s%d:%s%d)', $col, $startRow, $col, $sumRow-1)
            );
        }
        // Tỷ lệ cộng = (tổng mù 1 + tổng mù 2) / tổng dân số * 100
        $this->setCellValue(
            $sh,
            $cfg['col']['rate'].$sumRow,
            sprintf('=IF(%1$s%2$d=0,"",ROUND((%3$s%2$d+%4$s%2$d)/%1$s%2$d*100,2))',
                $cfg['col']['pop_tot'], $sumRow, $cfg['col']['lv1_tot'], $cfg['col']['lv2_tot'])
        );

        // Định dạng số
        $intCols = [
            'pop_tot','pop_f','pop_eth','pop_rel',
            'lv1_tot','lv1_f','lv1_eth','lv1_rel',
            'lv2_tot','lv2_f','lv2_eth','lv2_rel',
            're_tot','re_f','re_eth','re_rel',
        ];
        foreach ($intCols as $k) {
            $col = $cfg['col'][$k];
            $sh->getStyle(sprintf('%s%d:%s%d', $col, $startRow, $col, $sumRow))
               ->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
        }
        $sh->getStyle(sprintf('%s%d:%s%d', $cfg['col']['rate'], $startRow, $cfg['col']['rate'], $sumRow))
           ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
       $this->setCellValue($sh,'M13', $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null))); // ô tùy template
        $sh->getStyle('M13:Q13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->mergeCells('M13:Q13' );
        $sh->getStyle('A'.$sumRow.':'.$cfg['lastCol'].$sumRow)
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
        // Viền
        $this->drawThinBorders($sh, $startRow, $sumRow, $cfg['firstCol'], $cfg['lastCol']);

        // Xuất
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Trả về 3 dòng (mỗi dải tuổi 1 dòng) đã gộp sẵn.
     * - Dân tộc: ethnicities.is_minority = 1 (như bạn đang dùng)
     * - Tôn giáo: coi là có tôn giáo nếu p.religion_id IS NOT NULL (bạn có thể đổi điều kiện ở đây)
     * - Mức 1 / Mức 2: cùng công thức với các báo cáo trước (cong_thuc_baocao(5,37)).
     * - Tái mù chữ: xmc.tai_mu_chu_muc IN (1,2)
     */
    protected function queryByBands(string $academic, string $scheme, array $geo, array $bands): array
    {
        // Lấy bản ghi giáo dục & xmc mới nhất
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p','p.id','=','d.person_id')
            ->leftJoinSub($peLatest, 'pe', function($j){
                $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
            })
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc', function($j){
                $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
            })
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academic);

        $this->applyGeoFilter($qb, $scheme, $geo);

        $isF     = '(p.is_female = 1)';
        $isEth   = '(COALESCE(e.is_minority,0) = 1)';
        // “Có tôn giáo”: tùy DB của bạn. Đơn giản nhất là religion_id NOT NULL
        $isRel   = '(p.religion_id IS NOT NULL)';

        // Bỏ học lớp (chuẩn hoá số) & điều kiện tốt nghiệp phổ thông
        $ebn     = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $gradLT2 = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // Mức 1 & Mức 2 (giống các báo cáo trước)
        $lv2Drop = ($this->formatService->cong_thuc_baocao(5,37) == 1)
            ? "({$ebn} <= 5)"
            : "({$ebn} > 3 AND {$ebn} <= 5)";
        $lv1 = "( ({$gradLT2} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";
        $lv2 = "( ({$gradLT2} AND {$lv2Drop})   OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";

        // Tái mù chữ
        $relapse = "(COALESCE(xmc.tai_mu_chu_muc,0) IN (1,2))";

        // Chuẩn bị CASE cho từng dải tuổi
        $cases = [];
        foreach ($bands as $i => $b) {
            $i1 = $i+1;
            $cases[] = "
                -- Dải {$i1}: {$b['label']}
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} THEN 1 ELSE 0 END)                                   as pop{$i1}_tot,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$isF}   THEN 1 ELSE 0 END)                       as pop{$i1}_f,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$isEth} THEN 1 ELSE 0 END)                       as pop{$i1}_eth,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$isRel} THEN 1 ELSE 0 END)                       as pop{$i1}_rel,

                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} THEN 1 ELSE 0 END)                         as lv1_{$i1}_tot,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} AND {$isF}   THEN 1 ELSE 0 END)            as lv1_{$i1}_f,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} AND {$isEth} THEN 1 ELSE 0 END)            as lv1_{$i1}_eth,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} AND {$isRel} THEN 1 ELSE 0 END)            as lv1_{$i1}_rel,

                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} THEN 1 ELSE 0 END)                         as lv2_{$i1}_tot,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} AND {$isF}   THEN 1 ELSE 0 END)            as lv2_{$i1}_f,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} AND {$isEth} THEN 1 ELSE 0 END)            as lv2_{$i1}_eth,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} AND {$isRel} THEN 1 ELSE 0 END)            as lv2_{$i1}_rel,

                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} THEN 1 ELSE 0 END)                     as re_{$i1}_tot,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} AND {$isF}   THEN 1 ELSE 0 END)        as re_{$i1}_f,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} AND {$isEth} THEN 1 ELSE 0 END)        as re_{$i1}_eth,
                SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} AND {$isRel} THEN 1 ELSE 0 END)        as re_{$i1}_rel
            ";
        }

        $row = $qb->selectRaw(implode(",\n", $cases))->first();

        // Trả về 3 dòng
        $out = [];
        foreach ($bands as $i => $b) {
            $i1 = $i+1;
            $out[] = [
                'label'   => $b['label'],
                'pop_tot' => (int)($row->{"pop{$i1}_tot"} ?? 0),
                'pop_f'   => (int)($row->{"pop{$i1}_f"}   ?? 0),
                'pop_eth' => (int)($row->{"pop{$i1}_eth"} ?? 0),
                'pop_rel' => (int)($row->{"pop{$i1}_rel"} ?? 0),

                'lv1_tot' => (int)($row->{"lv1_{$i1}_tot"} ?? 0),
                'lv1_f'   => (int)($row->{"lv1_{$i1}_f"}   ?? 0),
                'lv1_eth' => (int)($row->{"lv1_{$i1}_eth"} ?? 0),
                'lv1_rel' => (int)($row->{"lv1_{$i1}_rel"} ?? 0),

                'lv2_tot' => (int)($row->{"lv2_{$i1}_tot"} ?? 0),
                'lv2_f'   => (int)($row->{"lv2_{$i1}_f"}   ?? 0),
                'lv2_eth' => (int)($row->{"lv2_{$i1}_eth"} ?? 0),
                'lv2_rel' => (int)($row->{"lv2_{$i1}_rel"} ?? 0),

                're_tot'  => (int)($row->{"re_{$i1}_tot"}  ?? 0),
                're_f'    => (int)($row->{"re_{$i1}_f"}    ?? 0),
                're_eth'  => (int)($row->{"re_{$i1}_eth"}  ?? 0),
                're_rel'  => (int)($row->{"re_{$i1}_rel"}  ?? 0),
            ];
        }
        return $out;
    }

    protected function templateCfg(): array
    {
        // Map cột theo đúng ảnh bạn gửi (đi từ cột A → T). Nếu template khác, chỉnh lại ở đây.
        return [
            'path'     => resource_path('excel/5.3.XMC.TK.MuChu.xlsx'),
            'header'   => [
                'province' => 'C1',
                'ward'     => 'C2',
            ],
            'firstCol' => 'A',
            'lastCol'  => 'S',
            'startRow' => 8, // hàng đầu tiên của 3 nhóm tuổi

            'col' => [
                'band'     => 'A',

                // Dân số trong độ tuổi
                'pop_tot'  => 'B',
                'pop_f'    => 'C',
                'pop_eth'  => 'D',
                'pop_rel'  => 'E',

                // Mù chữ mức 1
                'lv1_tot'  => 'F',
                'lv1_f'    => 'G',
                'lv1_eth'  => 'H',
                'lv1_rel'  => 'I',

                // Mù chữ mức 2
                'lv2_tot'  => 'J',
                'lv2_f'    => 'K',
                'lv2_eth'  => 'L',
                'lv2_rel'  => 'M',

                // Tỷ lệ mù chữ (%)
                'rate'     => 'N',

                // Tái mù chữ
                're_tot'   => 'O',
                're_f'     => 'P',
                're_eth'   => 'R',
                're_rel'   => 'S',

                // Ghi chú (nếu cần thì dùng 'T')
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
