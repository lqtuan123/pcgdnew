<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
class LiteracyOutcomeReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme','old');
        $geo      = (array)  $req->input('geo', []);

        // Dải năm sinh cho 15..60
        $years = [];
        for ($a = 15; $a <= 60; $a++) $years[] = $y1 - $a;     // 2009..1964 (ví dụ)
        rsort($years);                                        // từ trẻ → già như template

        $rows = $this->queryByDobYear($academic, $scheme, $geo, $years);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $this->setCellValue($sh,$cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh,$cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
         

        // Nhân bản dòng
        $startRow = $cfg['startRow'];        // ví dụ: 9
        $sum15125 = $startRow + 11 + 1;      // sau 11 dòng tuổi 15..25, +1 là hàng "Cộng 15-25"
        $sum1535  = $sum15125 + 10 + 1 + 10; // sau 10 dòng 26..35, có 1 hàng cộng, rồi 10 dòng 36..45, v.v. (ta sẽ tính tổng quát bên dưới)
        // Cách đơn giản: cần thêm (count($years)-1) dòng
        $need = max(0, count($years) -1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
        }
        $lastDataRow = $startRow + count($years) - 1;

        // Đổ dữ liệu từng năm sinh
        $r = $startRow;
        $tplRow = $startRow;              // dùng để copy style/merge khi chèn dòng
        $blockStart = $r;                 // đầu block hiện tại

        foreach ($years as $y) {
            $age = $y1 - $y;              // tuổi = Năm báo cáo - năm sinh
            $d   = $rows[$y] ?? null;
            if (!$d) continue;

            // --- ghi 1 dòng dữ liệu tuổi ---
            $this->setCellValue($sh, $cfg['col']['age'].$r,  $age.' tuổi');
            // Năm sinh: set dạng text để không bị format số
            $sh->setCellValue($cfg['col']['dob'].$r, (string)$y);
            $sh->getStyle($cfg['col']['dob'].$r)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);

            // các cột đếm, bạn đã có sẵn trong $d:
            foreach ([
                'pop_tot','pop_f','pop_eth','pop_feth',
                'lv1_tot','lv1_f','lv1_eth','lv1_feth',
                'lv2_tot','lv2_f','lv2_eth','lv2_feth',
                'lit_tot','lit_f','lit_eth','lit_feth',
            ] as $k) {
                $val = (int)($d[$k] ?? 0);
                $this->setCellValue($sh, $cfg['col'][$k].$r, $val ?: null);
            }
            // --- tính biết chữ theo từng hàng ---
            $rule1 = ($this->formatService->cong_thuc_baocao(5,37) == 1);

            // --- tính biết chữ theo từng hàng ---
            // tổng
            $lit_tot = $rule1
                ? max(0, ($d['pop_tot'] ?? 0) - ($d['lv2_tot'] ?? 0))
                : max(0, ($d['pop_tot'] ?? 0) - ($d['lv1_tot'] ?? 0) - ($d['lv2_tot'] ?? 0));
            // nữ
            $lit_f = $rule1
                ? max(0, ($d['pop_f'] ?? 0) - ($d['lv2_f'] ?? 0))
                : max(0, ($d['pop_f'] ?? 0) - ($d['lv1_f'] ?? 0) - ($d['lv2_f'] ?? 0));
            // dân tộc
            $lit_eth = $rule1
                ? max(0, ($d['pop_eth'] ?? 0) - ($d['lv2_eth'] ?? 0))
                : max(0, ($d['pop_eth'] ?? 0) - ($d['lv1_eth'] ?? 0) - ($d['lv2_eth'] ?? 0));
            // nữ dân tộc
            $lit_feth = $rule1
                ? max(0, ($d['pop_feth'] ?? 0) - ($d['lv2_feth'] ?? 0))
                : max(0, ($d['pop_feth'] ?? 0) - ($d['lv1_feth'] ?? 0) - ($d['lv2_feth'] ?? 0));

           $this->setCellValue($sh, $cfg['col']['lit_tot'].$r,  $lit_tot  ?: null);
            $this->setCellValue($sh, $cfg['col']['lit_f'].$r,    $lit_f    ?: null);
            $this->setCellValue($sh, $cfg['col']['lit_eth'].$r,  $lit_eth  ?: null);
            $this->setCellValue($sh, $cfg['col']['lit_feth'].$r, $lit_feth ?: null);

            // tỷ lệ biết chữ theo hàng (giữ nguyên)
            $this->setCellValue(
                $sh, $cfg['col']['lit_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',
                        $cfg['col']['pop_tot'], $r, $cfg['col']['lit_tot'])
            );
            // tỷ lệ biết chữ theo từng tuổi (nếu cần hiển thị)
            $this->setCellValue(
                $sh, $cfg['col']['lit_rate'].$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',
                        $cfg['col']['pop_tot'], $r, $cfg['col']['lit_tot'])
            );

            // ======= ĐIỂM CHÈN DÒNG CỘNG =======
            // Sau tuổi 25: chèn “Cộng 15 - 25”
            if ($age === 25) {
                $from = $blockStart;   // từ đầu block
                $to   = $r;            // đến dòng hiện tại (tuổi 25)
                $ins  = $r + 1;        // chèn sau tuổi 25

                $sh->insertNewRowBefore($ins, 1);
                // copy style/merge của dòng mẫu sang dòng vừa chèn
                $this->replicateRowFormatMerged($sh, $tplRow, $ins, $cfg['lastCol']);

                $this->writeSubtotalRow($sh, $cfg, $ins, $from, $to, '15 - 25');
                $r = $ins + 1;         // dịch con trỏ xuống dưới dòng cộng
                $blockStart = $r;      // bắt đầu block kế tiếp (26..35)
                continue;              // sang tuổi tiếp theo
            }

            // Sau tuổi 35: chèn “Cộng 15 - 35”
            if ($age === 35) {
                $from = $blockStart-1;   // đầu block (sau 25)
                $to   = $r;            // đến 35
                $ins  = $r + 1;

                $sh->insertNewRowBefore($ins, 1);
                $this->replicateRowFormatMerged($sh, $tplRow, $ins, $cfg['lastCol']);

                $this->writeSubtotalRow($sh, $cfg, $ins, $from, $to, '15 - 35');
                $r = $ins + 1;
                $blockStart = $r;      // bắt đầu block 36..60
                continue;
            }

            $r++;                      // dòng kế tiếp (khi chưa chèn subtotal)
        }

        // Kết thúc tuổi 60: chèn “Cộng 15 - 60”
        {
            $from = $blockStart-1;       // toàn bộ từ 15
            $to   = $r - 1;          // đến tuổi 60
            $ins  = $r;              // chèn ngay sau dòng cuối cùng

            $sh->insertNewRowBefore($ins, 1);
            $this->replicateRowFormatMerged($sh, $tplRow, $ins, $cfg['lastCol']);

            $this->writeSubtotalRow($sh, $cfg, $ins, $from, $to, '15 - 60');

            // format số & viền cho cả vùng mới
            $lastRow = $ins;
            foreach ([
                'pop_tot','pop_f','pop_eth','pop_feth',
                'lv1_tot','lv1_f','lv1_eth','lv1_feth',
                'lv2_tot','lv2_f','lv2_eth','lv2_feth',
                'lit_tot','lit_f','lit_eth','lit_feth',
            ] as $k) {
                $col = $cfg['col'][$k];
                $sh->getStyle(sprintf('%s%d:%s%d', $col, $startRow, $col, $lastRow))
                ->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
            }

            $sh->getStyle(sprintf('%s%d:%s%d', $cfg['col']['lit_rate'], $startRow, $cfg['col']['lit_rate'], $lastRow))
            ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');

            // Format cột năm sinh (B) thành General để không bị format số
            $sh->getStyle(sprintf('%s%d:%s%d', $cfg['col']['dob'], $startRow, $cfg['col']['dob'], $lastRow))
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);

            // viền
            $this->drawThinBorders($sh, $startRow, $lastRow, 'A', $cfg['lastCol']);
        }
        $this->setCellValue($sh,'L59', $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null))); // ô tùy template
        $sh->getStyle('L59:P59')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->mergeCells('L59:P59' );
        // Xuất
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    private function writeSubtotalRow(
        Worksheet $sh, array $cfg, int $rowIdx, int $fromRow, int $toRow, string $label
    ): void {
        // Nhãn “Cộng …” + gộp A:B
        $this->setCellValue($sh, $cfg['col']['age'].$rowIdx, 'Cộng '.$label);
        $this->setCellValue($sh, $cfg['col']['dob'].$rowIdx, '');
        $sh->mergeCells($cfg['col']['age'].$rowIdx.':'.$cfg['col']['dob'].$rowIdx);

        // SUM cho các cột đếm cơ sở
        foreach ([
            'pop_tot','pop_f','pop_eth','pop_feth',
            'lv1_tot','lv1_f','lv1_eth','lv1_feth',
            'lv2_tot','lv2_f','lv2_eth','lv2_feth',
        ] as $k) {
            $col = $cfg['col'][$k];
            $this->setCellValue($sh, $col.$rowIdx, sprintf('=SUM(%s%d:%s%d)', $col, $fromRow, $col, $toRow));
        }

        $rule1 = ($this->formatService->cong_thuc_baocao(5,37) == 1);

        $litFormula = function(string $popKey, string $lv1Key, string $lv2Key) use ($cfg, $rowIdx, $rule1) {
            return $rule1
                ? sprintf('=MAX(0,%1$s%2$d-%3$s%2$d)',
                        $cfg['col'][$popKey], $rowIdx, $cfg['col'][$lv2Key])
                : sprintf('=MAX(0,%1$s%2$d-%3$s%2$d-%4$s%2$d)',
                        $cfg['col'][$popKey], $rowIdx, $cfg['col'][$lv1Key], $cfg['col'][$lv2Key]);
        };

        // biết chữ = pop - lv2  (hoặc)  pop - lv1 - lv2
        $this->setCellValue($sh, $cfg['col']['lit_tot'] .$rowIdx, $litFormula('pop_tot','lv1_tot','lv2_tot'));
        $this->setCellValue($sh, $cfg['col']['lit_f']   .$rowIdx, $litFormula('pop_f'  ,'lv1_f'  ,'lv2_f'));
        $this->setCellValue($sh, $cfg['col']['lit_eth'] .$rowIdx, $litFormula('pop_eth','lv1_eth','lv2_eth'));
        $this->setCellValue($sh, $cfg['col']['lit_feth'].$rowIdx, $litFormula('pop_feth','lv1_feth','lv2_feth'));

        // tỷ lệ biết chữ (giữ nguyên)
        $this->setCellValue(
            $sh, $cfg['col']['lit_rate'].$rowIdx,
            sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))',
                    $cfg['col']['pop_tot'], $rowIdx, $cfg['col']['lit_tot'])
        );

        // gộp A:B + bôi đậm (như bạn yêu cầu)
        $this->setCellValue($sh, $cfg['col']['age'].$rowIdx, 'Cộng '.$label);
        $this->setCellValue($sh, $cfg['col']['dob'].$rowIdx, '');
        $sh->mergeCells($cfg['col']['age'].$rowIdx.':'.$cfg['col']['dob'].$rowIdx);
        $sh->getStyle('A'.$rowIdx.':'.$cfg['lastCol'].$rowIdx)->getFont()->setBold(true);
    }
        
  

    private function getCell(Worksheet $sh, string $addr)
    {
        return $sh->getCell($addr)->getValue();
    }
    protected function queryByDobYear(string $academic, string $scheme, array $geo, array $years): array
    {
        // latest person_educations of selected academic year
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        // latest literacy record
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted',false);
            })
            ->join('people as p','p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe',function($j){ $j->on('pe.person_id','=','p.id')->where('pe.rn',1); })
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc',function($j){ $j->on('xmc.person_id','=','p.id')->where('xmc.rn',1); })
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('d.is_deleted',false)
            ->where('d.academic_year',$academic)
            ->whereIn('p.dob_year',$years);

        $this->applyGeoFilter($qb, $scheme, $geo);

        $isF   = '(p.is_female = 1)';
        $isEth = '(COALESCE(e.is_minority,0) = 1)';

        // bỏ học lớp (chuẩn hoá số)
        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // mức 1, mức 2 (giữ đúng 2 kiểu cấu hình bạn đã dùng)
        if ($this->formatService->cong_thuc_baocao(5,37) == 1) {
            $lv2Drop = "({$ebn} <= 5)";
        } else {
            $lv2Drop = "({$ebn} > 3 AND {$ebn} <= 5)";
        }
        $lv1 = "( ({$grad} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";
        $lv2 = "( ({$grad} AND {$lv2Drop})  OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";

        $rows = $qb->selectRaw("
                p.dob_year as y,
                -- Trong độ tuổi
                COUNT(*)                                                as pop_tot,
                SUM(CASE WHEN {$isF} THEN 1 ELSE 0 END)                 as pop_f,
                SUM(CASE WHEN {$isEth} THEN 1 ELSE 0 END)               as pop_eth,
                SUM(CASE WHEN {$isF} AND {$isEth} THEN 1 ELSE 0 END)    as pop_feth,

                -- Mù chữ mức 1
                SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END)                 as lv1_tot,
                SUM(CASE WHEN {$lv1} AND {$isF} THEN 1 ELSE 0 END)      as lv1_f,
                SUM(CASE WHEN {$lv1} AND {$isEth} THEN 1 ELSE 0 END)    as lv1_eth,
                SUM(CASE WHEN {$lv1} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) as lv1_feth,

                -- Mù chữ mức 2
                SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END)                 as lv2_tot,
                SUM(CASE WHEN {$lv2} AND {$isF} THEN 1 ELSE 0 END)      as lv2_f,
                SUM(CASE WHEN {$lv2} AND {$isEth} THEN 1 ELSE 0 END)    as lv2_eth,
                SUM(CASE WHEN {$lv2} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) as lv2_feth
            ")
            ->groupBy('p.dob_year')
            ->orderBy('p.dob_year','desc')
            ->get();

        // Chuẩn hoá về mảng [year => metrics]
        $out = [];
        foreach ($years as $y) {
            $out[$y] = [
                'pop_tot'=>0,'pop_f'=>0,'pop_eth'=>0,'pop_feth'=>0,
                'lv1_tot'=>0,'lv1_f'=>0,'lv1_eth'=>0,'lv1_feth'=>0,
                'lv2_tot'=>0,'lv2_f'=>0,'lv2_eth'=>0,'lv2_feth'=>0,
            ];
        }
        foreach ($rows as $r) {
            $y = (int)$r->y;
            $out[$y] = [
                'pop_tot'=>(int)$r->pop_tot,'pop_f'=>(int)$r->pop_f,'pop_eth'=>(int)$r->pop_eth,'pop_feth'=>(int)$r->pop_feth,
                'lv1_tot'=>(int)$r->lv1_tot,'lv1_f'=>(int)$r->lv1_f,'lv1_eth'=>(int)$r->lv1_eth,'lv1_feth'=>(int)$r->lv1_feth,
                'lv2_tot'=>(int)$r->lv2_tot,'lv2_f'=>(int)$r->lv2_f,'lv2_eth'=>(int)$r->lv2_eth,'lv2_feth'=>(int)$r->lv2_feth,
            ];
        }
        return $out;
    }

    protected function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/5.2.XMC.KetQua.15.60Tuoi.xlsx'),
            'header' => [
                'province' => 'C1',      // Quận/Huyện
                'ward'     => 'C2',      // Phường/Xã
            ],
            'dateCell'  => 'G5',
            'firstCol'  => 'A',
            'lastCol'   => 'S',         // sửa theo template thật của bạn
            'startRow'  => 9,

            // map cột – đổi theo file của bạn:
            'col' => [
                'age'      => 'A',
                'dob'      => 'B',

                // Trong độ tuổi
                'pop_tot'  => 'C',
                'pop_f'    => 'D',
                'pop_eth'  => 'E',
                'pop_feth' => 'F',

                // Mù chữ mức 1
                'lv1_tot'  => 'G',
                'lv1_f'    => 'H',
                'lv1_eth'  => 'I',
                'lv1_feth' => 'J',

                // Mù chữ mức 2
                'lv2_tot'  => 'K',
                'lv2_f'    => 'L',
                'lv2_eth'  => 'M',
                'lv2_feth' => 'N',

                // Biết chữ
                'lit_tot'  => 'O',
                'lit_f'    => 'P',
                'lit_eth'  => 'Q',
                'lit_feth' => 'R',
                'lit_rate' => 'S',
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
