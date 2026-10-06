<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
class LiteracyOutcomeSummaryReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* ===================== Export ===================== */
    public function export(Request $req)
    {
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['village'],  $this->geoService->geoNameFromId($geo['village_id'] ?? null));
        $this->setCellValue($sh, $cfg['dateCell'],           $this->formatService->top_date());
        $sh->getStyle('A6:G6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // 3 dải tuổi
        $bands = [
            ['min'=>$y1-25, 'max'=>$y1-15, 'cells'=>$cfg['cells']['15_25']],
            ['min'=>$y1-35, 'max'=>$y1-15, 'cells'=>$cfg['cells']['15_35']],
            ['min'=>$y1-60, 'max'=>$y1-15, 'cells'=>$cfg['cells']['15_60']],
        ];

        foreach ($bands as $b) {
            $agg = $this->queryBand($academic, $scheme, $geo, $b['min'], $b['max']);

            // Tổng dân số trong độ tuổi
            $this->setCellValue($sh, $b['cells']['pop'], $agg['pop']);

            // Biết chữ đạt chuẩn XMC mức 1 = dân số – mù chữ mức 1
            $bc1_cnt = max(0, $agg['pop'] - $agg['lv1']);
            $bc1_pct = $agg['pop'] ? round($bc1_cnt / $agg['pop'] * 100, 2) : null;
            $this->setCellValue($sh, $b['cells']['bc1_cnt'], $bc1_cnt ?: null);
            $this->setCellValue($sh, $b['cells']['bc1_pct'], $bc1_pct);

            // Biết chữ đạt chuẩn XMC mức 2 = dân số – mù chữ mức 2
            $bc2_cnt = max(0, $agg['pop'] - $agg['lv2']);
            $bc2_pct = $agg['pop'] ? round($bc2_cnt / $agg['pop'] * 100, 2) : null;
            $this->setCellValue($sh, $b['cells']['bc2_cnt'], $bc2_cnt ?: null);
            $this->setCellValue($sh, $b['cells']['bc2_pct'], $bc2_pct);
        }

        // Định dạng số
        foreach (['15_25','15_35','15_60'] as $k) {
            $c = $cfg['cells'][$k];
            // số lượng
            foreach ([$c['pop'],$c['bc1_cnt'],$c['bc2_cnt']] as $addr) {
                $sh->getStyle($addr)->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
            }
            // tỷ lệ
            foreach ([$c['bc1_pct'],$c['bc2_pct']] as $addr) {
                $sh->getStyle($addr)->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
            }
        }

        $this->setCellValue($sh, 'E14', $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
        $sh->mergeCells('E14:G14');
        $sh->getStyle('E14:G14')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);



        // Xuất file
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* ===================== Query 1 dải tuổi ===================== */
    private function queryBand(string $academic, string $scheme, array $geo, int $yMin, int $yMax): array
    {
        // latest education theo năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        // latest literacy
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe', function($j){ $j->on('pe.person_id','=','p.id')->where('pe.rn',1); })
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc', function($j){ $j->on('xmc.person_id','=','p.id')->where('xmc.rn',1); })
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academic);

        // áp địa bàn
        $this->applyGeoFilter($qb, $scheme, $geo);

        // điều kiện
        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
        $isF   = "(p.is_female = 1)";

        // chuẩn hoá “bỏ học lớp”
        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // Mức 1 (y như báo cáo trước)
        $lv1 = "(
            {$ageY}
            AND (
                ( {$grad} AND ({$ebn} <= 3) )
                OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1)
            )
        )";

        // Mức 2 theo cấu hình công thức (5,37)
        if ($this->formatService->cong_thuc_baocao(5,37) == 1) {
            $lv2Drop = "({$ebn} <= 5)";
        } else {
            $lv2Drop = "({$ebn} > 3 AND {$ebn} <= 5)";
        }
        $lv2 = "(
            {$ageY}
            AND (
                ( {$grad} AND {$lv2Drop} )
                OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2)
            )
        )";

        // Tổng dân số & tổng mù chữ 1/2 trong dải
        $row = $qb->selectRaw("
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                 as pop,
                SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END)                   as lv1,
                SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END)                   as lv2
            ")->first();

        return [
            'pop' => (int)($row->pop ?? 0),
            'lv1' => (int)($row->lv1 ?? 0),
            'lv2' => (int)($row->lv2 ?? 0),
        ];
    }

    /* ===================== Template mapping ===================== */
    private function templateCfg(): array
    {
        // mapping ô theo ảnh bạn gửi:
        return [
            'path' => resource_path('excel/5.9.XMC.TK.KetQua.xlsx'),
            'header' => [
                'province' => 'B1',
                'ward'     => 'B2',
                'village'  => 'B3',   // nếu có village_id sẽ hiện ở đây
            ],
            'dateCell' => 'A6',

            // Vị trí từng cột (E/F/G) – sửa nếu file khác
            'cells' => [
                '15_25' => [
                    'pop'     => 'E8',
                    'bc1_cnt' => 'E9', 'bc1_pct' => 'E10',
                    'bc2_cnt' => 'E11', 'bc2_pct' => 'E12',
                ],
                '15_35' => [
                    'pop'     => 'F8',
                    'bc1_cnt' => 'F9', 'bc1_pct' => 'F10',
                    'bc2_cnt' => 'F11', 'bc2_pct' => 'F12',
                ],
                '15_60' => [
                    'pop'     => 'G8',
                    'bc1_cnt' => 'G9', 'bc1_pct' => 'G10',
                    'bc2_cnt' => 'G11', 'bc2_pct' => 'G12',
                ],
            ],
        ];
    }
}
