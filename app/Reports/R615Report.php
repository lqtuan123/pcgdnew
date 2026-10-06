<?php
namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Style\Border;
class R615Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

  protected function queryIlliteracyVillageSummary(string $academic, string $scheme, array $geo): array
{
    [$y1] = array_map('intval', explode('-', $academic));

    $yrs_15_25 = range($y1-25, $y1-15);
    $yrs_15_35 = range($y1-35, $y1-15);
    $yrs_15_60 = range($y1-60, $y1-15);
    $y_ge_61   = $y1 - 61;

    // cột địa bàn theo scheme
    $colProvince = $scheme === 'old' ? 'v.old_province_id' : 'v.new_province_id';
    $colWard     = $scheme === 'old' ? 'v.old_ward_id'     : 'v.new_ward_id';

    // DTTS và DTTS nữ
    $dtts   = "UPPER(COALESCE(v.ethnicity,'')) <> 'KINH'";
    $dtts_f = "((UPPER(COALESCE(v.ethnicity,'')) <> 'KINH') AND v.is_female=1)";

    // Mù chữ mức 1
    $ill1 = "("
        . " (v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 3)"
        . " OR (v.bo_hoc_lop is NULL AND v.education_block_code IS NULL AND UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT'))"
        . " OR COALESCE(v.lit_tai_mu_muc,0) = 1"
        . " )";

    // Mù chữ mức 2
    $ill2 = "("
        . " (v.bo_hoc_lop is NULL AND v.education_block_code IS NULL AND UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT')) OR "
        . " (v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 5)"
        . " OR COALESCE(v.lit_tai_mu_muc,0) = 2"
        . " )";

    $q = DB::table('v_survey_detail_flat as v')
        ->where('v.academic_year', $academic);

    // lọc địa bàn
    if (!empty($geo['province_id'])) $q->where($colProvince, $geo['province_id']);
    if (!empty($geo['ward_id']))     $q->where($colWard,     $geo['ward_id']);

    $rows = $q->selectRaw("
        v.village_id,
        COALESCE(v.village_name,'Không rõ') AS village_name,

        -- TỔNG DÂN SỐ (toàn xã)
        COUNT(*)                               AS pop_all,
        SUM(v.is_female=1)                     AS pop_all_f,
        SUM(($dtts))                           AS pop_all_dt,
        SUM(($dtts_f))                         AS pop_all_dt_f,

        -- 15-25: dân số
        SUM(v.dob_year IN (".implode(',', $yrs_15_25)."))                                     AS pop_15_25,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND v.is_female=1)                   AS pop_15_25_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($dtts))                         AS pop_15_25_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($dtts_f))                       AS pop_15_25_dt_f,

        -- 15-25: Mức 1
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($ill1))                         AS ill1_15_25,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND v.is_female=1 AND ($ill1))       AS ill1_15_25_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($dtts)   AND ($ill1))           AS ill1_15_25_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($dtts_f) AND ($ill1))           AS ill1_15_25_ndt,

        -- 15-25: Mức 2
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($ill2))                         AS ill2_15_25,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND v.is_female=1 AND ($ill2))       AS ill2_15_25_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($dtts)   AND ($ill2))           AS ill2_15_25_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_25).") AND ($dtts_f) AND ($ill2))           AS ill2_15_25_ndt,

        -- 15-35: dân số
        SUM(v.dob_year IN (".implode(',', $yrs_15_35)."))                                     AS pop_15_35,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND v.is_female=1)                   AS pop_15_35_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($dtts))                         AS pop_15_35_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($dtts_f))                       AS pop_15_35_dt_f,

        -- 15-35: Mức 1
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($ill1))                         AS ill1_15_35,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND v.is_female=1 AND ($ill1))       AS ill1_15_35_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($dtts)   AND ($ill1))           AS ill1_15_35_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($dtts_f) AND ($ill1))           AS ill1_15_35_ndt,

        -- 15-35: Mức 2
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($ill2))                         AS ill2_15_35,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND v.is_female=1 AND ($ill2))       AS ill2_15_35_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($dtts)   AND ($ill2))           AS ill2_15_35_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_35).") AND ($dtts_f) AND ($ill2))           AS ill2_15_35_ndt,

        -- 15-60: dân số
        SUM(v.dob_year IN (".implode(',', $yrs_15_60)."))                                     AS pop_15_60,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND v.is_female=1)                   AS pop_15_60_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($dtts))                         AS pop_15_60_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($dtts_f))                       AS pop_15_60_dt_f,

        -- 15-60: Mức 1
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($ill1))                         AS ill1_15_60,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND v.is_female=1 AND ($ill1))       AS ill1_15_60_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($dtts)   AND ($ill1))           AS ill1_15_60_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($dtts_f) AND ($ill1))           AS ill1_15_60_ndt,

        -- 15-60: Mức 2
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($ill2))                         AS ill2_15_60,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND v.is_female=1 AND ($ill2))       AS ill2_15_60_f,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($dtts)   AND ($ill2))           AS ill2_15_60_dt,
        SUM(v.dob_year IN (".implode(',', $yrs_15_60).") AND ($dtts_f) AND ($ill2))           AS ill2_15_60_ndt,

        -- >=61: dân số
        SUM(v.dob_year <= {$y_ge_61})                                                         AS pop_ge_61,
        SUM(v.dob_year <= {$y_ge_61} AND v.is_female=1)                                       AS pop_ge_61_f,
        SUM(v.dob_year <= {$y_ge_61} AND ($dtts))                                             AS pop_ge_61_dt,
        SUM(v.dob_year <= {$y_ge_61} AND ($dtts_f))                                           AS pop_ge_61_dt_f,

        -- >=61: Mức 1
        SUM(v.dob_year <= {$y_ge_61} AND ($ill1))                                             AS ill1_ge_61,
        SUM(v.dob_year <= {$y_ge_61} AND v.is_female=1 AND ($ill1))                           AS ill1_ge_61_f,
        SUM(v.dob_year <= {$y_ge_61} AND ($dtts)   AND ($ill1))                               AS ill1_ge_61_dt,
        SUM(v.dob_year <= {$y_ge_61} AND ($dtts_f) AND ($ill1))                               AS ill1_ge_61_ndt,

        -- >=61: Mức 2
        SUM(v.dob_year <= {$y_ge_61} AND ($ill2))                                             AS ill2_ge_61,
        SUM(v.dob_year <= {$y_ge_61} AND v.is_female=1 AND ($ill2))                           AS ill2_ge_61_f,
        SUM(v.dob_year <= {$y_ge_61} AND ($dtts)   AND ($ill2))                               AS ill2_ge_61_dt,
        SUM(v.dob_year <= {$y_ge_61} AND ($dtts_f) AND ($ill2))                               AS ill2_ge_61_ndt
    ")
    ->groupBy('village_id','village_name')
    ->orderBy('village_name')
    ->get();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'unit_id'   => (int)$r->village_id,
            'unit_name' => $r->village_name,

            // Tổng dân số
            'pop_all' => (int)$r->pop_all, 'pop_all_f' => (int)$r->pop_all_f,
            'pop_all_dt' => (int)$r->pop_all_dt, 'pop_all_dt_f' => (int)$r->pop_all_dt_f,

            // 15–25
            'pop_15_25' => (int)$r->pop_15_25, 'pop_15_25_f' => (int)$r->pop_15_25_f,
            'pop_15_25_dt' => (int)$r->pop_15_25_dt, 'pop_15_25_dt_f' => (int)$r->pop_15_25_dt_f,

            'ill1_15_25' => (int)$r->ill1_15_25, 'ill1_15_25_f' => (int)$r->ill1_15_25_f,
            'ill1_15_25_dt' => (int)$r->ill1_15_25_dt, 'ill1_15_25_ndt' => (int)$r->ill1_15_25_ndt,

            'ill2_15_25' => (int)$r->ill2_15_25, 'ill2_15_25_f' => (int)$r->ill2_15_25_f,
            'ill2_15_25_dt' => (int)$r->ill2_15_25_dt, 'ill2_15_25_ndt' => (int)$r->ill2_15_25_ndt,

            // 15–35
            'pop_15_35' => (int)$r->pop_15_35, 'pop_15_35_f' => (int)$r->pop_15_35_f,
            'pop_15_35_dt' => (int)$r->pop_15_35_dt, 'pop_15_35_dt_f' => (int)$r->pop_15_35_dt_f,

            'ill1_15_35' => (int)$r->ill1_15_35, 'ill1_15_35_f' => (int)$r->ill1_15_35_f,
            'ill1_15_35_dt' => (int)$r->ill1_15_35_dt, 'ill1_15_35_ndt' => (int)$r->ill1_15_35_ndt,

            'ill2_15_35' => (int)$r->ill2_15_35, 'ill2_15_35_f' => (int)$r->ill2_15_35_f,
            'ill2_15_35_dt' => (int)$r->ill2_15_35_dt, 'ill2_15_35_ndt' => (int)$r->ill2_15_35_ndt,

            // 15–60
            'pop_15_60' => (int)$r->pop_15_60, 'pop_15_60_f' => (int)$r->pop_15_60_f,
            'pop_15_60_dt' => (int)$r->pop_15_60_dt, 'pop_15_60_dt_f' => (int)$r->pop_15_60_dt_f,

            'ill1_15_60' => (int)$r->ill1_15_60, 'ill1_15_60_f' => (int)$r->ill1_15_60_f,
            'ill1_15_60_dt' => (int)$r->ill1_15_60_dt, 'ill1_15_60_ndt' => (int)$r->ill1_15_60_ndt,

            'ill2_15_60' => (int)$r->ill2_15_60, 'ill2_15_60_f' => (int)$r->ill2_15_60_f,
            'ill2_15_60_dt' => (int)$r->ill2_15_60_dt, 'ill2_15_60_ndt' => (int)$r->ill2_15_60_ndt,

            // ≥61
            'pop_ge_61' => (int)$r->pop_ge_61, 'pop_ge_61_f' => (int)$r->pop_ge_61_f,
            'pop_ge_61_dt' => (int)$r->pop_ge_61_dt, 'pop_ge_61_dt_f' => (int)$r->pop_ge_61_dt_f,

            'ill1_ge_61' => (int)$r->ill1_ge_61, 'ill1_ge_61_f' => (int)$r->ill1_ge_61_f,
            'ill1_ge_61_dt' => (int)$r->ill1_ge_61_dt, 'ill1_ge_61_ndt' => (int)$r->ill1_ge_61_ndt,

            'ill2_ge_61' => (int)$r->ill2_ge_61, 'ill2_ge_61_f' => (int)$r->ill2_ge_61_f,
            'ill2_ge_61_dt' => (int)$r->ill2_ge_61_dt, 'ill2_ge_61_ndt' => (int)$r->ill2_ge_61_ndt,
        ];
    }
    return $out;
}

public function export(Request $req)
{
    $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
    $scheme   = (string)$req->input('scheme', 'old');
    $geo      = (array)$req->input('geo', []);

    if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
        Calculation::getInstance()->setCalculationEnabled(false);
    }

    $cfg = [
        'path'      => resource_path('excel/6.15report.xlsx'),
        'header'    => ['province' => 'D1', 'ward' => 'D2'],
        'startRow'  => 8,
        'sumRowLbl' => 9,
        'lastCol'   => 'CJ',            // kéo tới CJ
        // 'footerDateRange' => 'BY16:CJ16',
    ];

    $wb = IOFactory::load($cfg['path']);
    /** @var Worksheet $sh */
    $sh = $wb->getActiveSheet();

    // header
    $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
    $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));

    // dữ liệu
    $rows = $this->queryIlliteracyVillageSummary($academic, $scheme, $geo);

    // chèn dòng đủ
    $r0   = $cfg['startRow'];
    $sumR = $cfg['sumRowLbl'];
    $need = max(0, count($rows) - 1);
    if ($need > 0) {
        $sh->insertNewRowBefore($sumR, $need);
        for ($i=1; $i<=$need; $i++) {
            $this->replicateRowFormatMerged($sh, $r0, $r0+$i, $cfg['lastCol']);
        }
        $sumR += $need;
    }

    // bản đồ cột (đã có ndt & rndt)
    $cols = [
        'all' => ['tot'=>'E','f'=>'F','dt'=>'G','n_dt'=>'H'],

        // 15–25
        '15_25.pop' => ['tot'=>'I','f'=>'J','dt'=>'K','dtf'=>'L'],
        '15_25.m1'  => ['tot'=>'M','totr'=>'N','f'=>'O','rf'=>'P','dt'=>'Q','rdt'=>'R','ndt'=>'S','rndt'=>'T'],
        '15_25.m2'  => ['tot'=>'U','totr'=>'V','f'=>'W','rf'=>'X','dt'=>'Y','rdt'=>'Z','ndt'=>'AA','rndt'=>'AB'],

        // 15–35
        '15_35.pop' => ['tot'=>'AC','f'=>'AD','dt'=>'AE','dtf'=>'AF'],
        '15_35.m1'  => ['tot'=>'AG','totr'=>'AH','f'=>'AI','rf'=>'AJ','dt'=>'AK','rdt'=>'AL','ndt'=>'AM','rndt'=>'AN'],
        '15_35.m2'  => ['tot'=>'AO','totr'=>'AP','f'=>'AQ','rf'=>'AR','dt'=>'AS','rdt'=>'AT','ndt'=>'AU','rndt'=>'AV'],

        // 15–60
        '15_60.pop' => ['tot'=>'AW','f'=>'AX','dt'=>'AY','dtf'=>'AZ'],
        '15_60.m1'  => ['tot'=>'BA','totr'=>'BB','f'=>'BC','rf'=>'BD','dt'=>'BE','rdt'=>'BF','ndt'=>'BG','rndt'=>'BH'],
        '15_60.m2'  => ['tot'=>'BI','totr'=>'BJ','f'=>'BK','rf'=>'BL','dt'=>'BM','rdt'=>'BN','ndt'=>'BO','rndt'=>'BP'],

        // ≥61
        '61.pop'    => ['tot'=>'BQ','f'=>'BR','dt'=>'BS','dtf'=>'BT'],
        '61.m1'     => ['tot'=>'BU','totr'=>'BV','f'=>'BW','rf'=>'BX','dt'=>'BY','rdt'=>'BZ','ndt'=>'CA','rndt'=>'CB'],
        '61.m2'     => ['tot'=>'CC','totr'=>'CD','f'=>'CE','rf'=>'CF','dt'=>'CG','rdt'=>'CH','ndt'=>'CI','rndt'=>'CJ'],
    ];

    // ghi từng dòng
    $r = $r0; $stt=1;
    foreach ($rows as $it) {
        $this->setCellValue($sh, "A{$r}", $stt);
        $this->setCellValue($sh, "B{$r}", $it['unit_name']);

        // Tổng dân số
        $this->setCellValue($sh, $cols['all']['tot'].$r, $it['pop_all']);
        $this->setCellValue($sh, $cols['all']['f']  .$r, $it['pop_all_f']);
        $this->setCellValue($sh, $cols['all']['dt'] .$r, $it['pop_all_dt']);
        $this->setCellValue($sh, $cols['all']['n_dt'].$r, $it['pop_all_dt_f']);

        // ===== 15–25 =====
        // dân số
        $this->setCellValue($sh, $cols['15_25.pop']['tot'].$r, $it['pop_15_25']);
        $this->setCellValue($sh, $cols['15_25.pop']['f']  .$r, $it['pop_15_25_f']);
        $this->setCellValue($sh, $cols['15_25.pop']['dt'] .$r, $it['pop_15_25_dt']);
        $this->setCellValue($sh, $cols['15_25.pop']['dtf'].$r, $it['pop_15_25_dt_f']);
        // Mức 1: đếm
        $this->setCellValue($sh, $cols['15_25.m1']['tot'].$r, $it['ill1_15_25']);
        $this->setCellValue($sh, $cols['15_25.m1']['f']  .$r, $it['ill1_15_25_f']);
        $this->setCellValue($sh, $cols['15_25.m1']['dt'] .$r, $it['ill1_15_25_dt']);
        $this->setCellValue($sh, $cols['15_25.m1']['ndt'].$r, $it['ill1_15_25_ndt']);
        // Mức 1: %
        $this->setCellValue($sh, $cols['15_25.m1']['totr'].$r, '=IF('.$cols['15_25.pop']['tot'].$r.'=0,"",ROUND('.$cols['15_25.m1']['tot'].$r.'/'.$cols['15_25.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_25.m1']['rf']  .$r, '=IF('.$cols['15_25.pop']['f']  .$r.'=0,"",ROUND('.$cols['15_25.m1']['f']  .$r.'/'.$cols['15_25.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_25.m1']['rdt'] .$r, '=IF('.$cols['15_25.pop']['dt'] .$r.'=0,"",ROUND('.$cols['15_25.m1']['dt'] .$r.'/'.$cols['15_25.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_25.m1']['rndt'].$r, '=IF('.$cols['15_25.pop']['dtf'].$r.'=0,"",ROUND('.$cols['15_25.m1']['ndt'].$r.'/'.$cols['15_25.pop']['dtf'].$r.'*100,2))');
        // Mức 2: đếm
        $this->setCellValue($sh, $cols['15_25.m2']['tot'].$r, $it['ill2_15_25']);
        $this->setCellValue($sh, $cols['15_25.m2']['f']  .$r, $it['ill2_15_25_f']);
        $this->setCellValue($sh, $cols['15_25.m2']['dt'] .$r, $it['ill2_15_25_dt']);
        $this->setCellValue($sh, $cols['15_25.m2']['ndt'].$r, $it['ill2_15_25_ndt']);
        // Mức 2: %
        $this->setCellValue($sh, $cols['15_25.m2']['totr'].$r, '=IF('.$cols['15_25.pop']['tot'].$r.'=0,"",ROUND('.$cols['15_25.m2']['tot'].$r.'/'.$cols['15_25.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_25.m2']['rf']  .$r, '=IF('.$cols['15_25.pop']['f']  .$r.'=0,"",ROUND('.$cols['15_25.m2']['f']  .$r.'/'.$cols['15_25.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_25.m2']['rdt'] .$r, '=IF('.$cols['15_25.pop']['dt'] .$r.'=0,"",ROUND('.$cols['15_25.m2']['dt'] .$r.'/'.$cols['15_25.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_25.m2']['rndt'].$r, '=IF('.$cols['15_25.pop']['dtf'].$r.'=0,"",ROUND('.$cols['15_25.m2']['ndt'].$r.'/'.$cols['15_25.pop']['dtf'].$r.'*100,2))');

        // ===== 15–35 =====
        $this->setCellValue($sh, $cols['15_35.pop']['tot'].$r, $it['pop_15_35']);
        $this->setCellValue($sh, $cols['15_35.pop']['f']  .$r, $it['pop_15_35_f']);
        $this->setCellValue($sh, $cols['15_35.pop']['dt'] .$r, $it['pop_15_35_dt']);
        $this->setCellValue($sh, $cols['15_35.pop']['dtf'].$r, $it['pop_15_35_dt_f']);

        $this->setCellValue($sh, $cols['15_35.m1']['tot'].$r, $it['ill1_15_35']);
        $this->setCellValue($sh, $cols['15_35.m1']['f']  .$r, $it['ill1_15_35_f']);
        $this->setCellValue($sh, $cols['15_35.m1']['dt'] .$r, $it['ill1_15_35_dt']);
        $this->setCellValue($sh, $cols['15_35.m1']['ndt'].$r, $it['ill1_15_35_ndt']);
        $this->setCellValue($sh, $cols['15_35.m1']['totr'].$r, '=IF('.$cols['15_35.pop']['tot'].$r.'=0,"",ROUND('.$cols['15_35.m1']['tot'].$r.'/'.$cols['15_35.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_35.m1']['rf']  .$r, '=IF('.$cols['15_35.pop']['f']  .$r.'=0,"",ROUND('.$cols['15_35.m1']['f']  .$r.'/'.$cols['15_35.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_35.m1']['rdt'] .$r, '=IF('.$cols['15_35.pop']['dt'] .$r.'=0,"",ROUND('.$cols['15_35.m1']['dt'] .$r.'/'.$cols['15_35.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_35.m1']['rndt'].$r, '=IF('.$cols['15_35.pop']['dtf'].$r.'=0,"",ROUND('.$cols['15_35.m1']['ndt'].$r.'/'.$cols['15_35.pop']['dtf'].$r.'*100,2))');

        $this->setCellValue($sh, $cols['15_35.m2']['tot'].$r, $it['ill2_15_35']);
        $this->setCellValue($sh, $cols['15_35.m2']['f']  .$r, $it['ill2_15_35_f']);
        $this->setCellValue($sh, $cols['15_35.m2']['dt'] .$r, $it['ill2_15_35_dt']);
        $this->setCellValue($sh, $cols['15_35.m2']['ndt'].$r, $it['ill2_15_35_ndt']);
        $this->setCellValue($sh, $cols['15_35.m2']['totr'].$r, '=IF('.$cols['15_35.pop']['tot'].$r.'=0,"",ROUND('.$cols['15_35.m2']['tot'].$r.'/'.$cols['15_35.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_35.m2']['rf']  .$r, '=IF('.$cols['15_35.pop']['f']  .$r.'=0,"",ROUND('.$cols['15_35.m2']['f']  .$r.'/'.$cols['15_35.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_35.m2']['rdt'] .$r, '=IF('.$cols['15_35.pop']['dt'] .$r.'=0,"",ROUND('.$cols['15_35.m2']['dt'] .$r.'/'.$cols['15_35.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_35.m2']['rndt'].$r, '=IF('.$cols['15_35.pop']['dtf'].$r.'=0,"",ROUND('.$cols['15_35.m2']['ndt'].$r.'/'.$cols['15_35.pop']['dtf'].$r.'*100,2))');

        // ===== 15–60 =====
        $this->setCellValue($sh, $cols['15_60.pop']['tot'].$r, $it['pop_15_60']);
        $this->setCellValue($sh, $cols['15_60.pop']['f']  .$r, $it['pop_15_60_f']);
        $this->setCellValue($sh, $cols['15_60.pop']['dt'] .$r, $it['pop_15_60_dt']);
        $this->setCellValue($sh, $cols['15_60.pop']['dtf'].$r, $it['pop_15_60_dt_f']);

        $this->setCellValue($sh, $cols['15_60.m1']['tot'].$r, $it['ill1_15_60']);
        $this->setCellValue($sh, $cols['15_60.m1']['f']  .$r, $it['ill1_15_60_f']);
        $this->setCellValue($sh, $cols['15_60.m1']['dt'] .$r, $it['ill1_15_60_dt']);
        $this->setCellValue($sh, $cols['15_60.m1']['ndt'].$r, $it['ill1_15_60_ndt']);
        $this->setCellValue($sh, $cols['15_60.m1']['totr'].$r, '=IF('.$cols['15_60.pop']['tot'].$r.'=0,"",ROUND('.$cols['15_60.m1']['tot'].$r.'/'.$cols['15_60.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_60.m1']['rf']  .$r, '=IF('.$cols['15_60.pop']['f']  .$r.'=0,"",ROUND('.$cols['15_60.m1']['f']  .$r.'/'.$cols['15_60.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_60.m1']['rdt'] .$r, '=IF('.$cols['15_60.pop']['dt'] .$r.'=0,"",ROUND('.$cols['15_60.m1']['dt'] .$r.'/'.$cols['15_60.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_60.m1']['rndt'].$r, '=IF('.$cols['15_60.pop']['dtf'].$r.'=0,"",ROUND('.$cols['15_60.m1']['ndt'].$r.'/'.$cols['15_60.pop']['dtf'].$r.'*100,2))');

        $this->setCellValue($sh, $cols['15_60.m2']['tot'].$r, $it['ill2_15_60']);
        $this->setCellValue($sh, $cols['15_60.m2']['f']  .$r, $it['ill2_15_60_f']);
        $this->setCellValue($sh, $cols['15_60.m2']['dt'] .$r, $it['ill2_15_60_dt']);
        $this->setCellValue($sh, $cols['15_60.m2']['ndt'].$r, $it['ill2_15_60_ndt']);
        $this->setCellValue($sh, $cols['15_60.m2']['totr'].$r, '=IF('.$cols['15_60.pop']['tot'].$r.'=0,"",ROUND('.$cols['15_60.m2']['tot'].$r.'/'.$cols['15_60.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_60.m2']['rf']  .$r, '=IF('.$cols['15_60.pop']['f']  .$r.'=0,"",ROUND('.$cols['15_60.m2']['f']  .$r.'/'.$cols['15_60.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_60.m2']['rdt'] .$r, '=IF('.$cols['15_60.pop']['dt'] .$r.'=0,"",ROUND('.$cols['15_60.m2']['dt'] .$r.'/'.$cols['15_60.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['15_60.m2']['rndt'].$r, '=IF('.$cols['15_60.pop']['dtf'].$r.'=0,"",ROUND('.$cols['15_60.m2']['ndt'].$r.'/'.$cols['15_60.pop']['dtf'].$r.'*100,2))');

        // ===== ≥61 =====
        $this->setCellValue($sh, $cols['61.pop']['tot'].$r, $it['pop_ge_61']);
        $this->setCellValue($sh, $cols['61.pop']['f']  .$r, $it['pop_ge_61_f']);
        $this->setCellValue($sh, $cols['61.pop']['dt'] .$r, $it['pop_ge_61_dt']);
        $this->setCellValue($sh, $cols['61.pop']['dtf'].$r, $it['pop_ge_61_dt_f']);

        $this->setCellValue($sh, $cols['61.m1']['tot'].$r, $it['ill1_ge_61']);
        $this->setCellValue($sh, $cols['61.m1']['f']  .$r, $it['ill1_ge_61_f']);
        $this->setCellValue($sh, $cols['61.m1']['dt'] .$r, $it['ill1_ge_61_dt']);
        $this->setCellValue($sh, $cols['61.m1']['ndt'].$r, $it['ill1_ge_61_ndt']);
        $this->setCellValue($sh, $cols['61.m1']['totr'].$r, '=IF('.$cols['61.pop']['tot'].$r.'=0,"",ROUND('.$cols['61.m1']['tot'].$r.'/'.$cols['61.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['61.m1']['rf']  .$r, '=IF('.$cols['61.pop']['f']  .$r.'=0,"",ROUND('.$cols['61.m1']['f']  .$r.'/'.$cols['61.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['61.m1']['rdt'] .$r, '=IF('.$cols['61.pop']['dt'] .$r.'=0,"",ROUND('.$cols['61.m1']['dt'] .$r.'/'.$cols['61.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['61.m1']['rndt'].$r, '=IF('.$cols['61.pop']['dtf'].$r.'=0,"",ROUND('.$cols['61.m1']['ndt'].$r.'/'.$cols['61.pop']['dtf'].$r.'*100,2))');

        $this->setCellValue($sh, $cols['61.m2']['tot'].$r, $it['ill2_ge_61']);
        $this->setCellValue($sh, $cols['61.m2']['f']  .$r, $it['ill2_ge_61_f']);
        $this->setCellValue($sh, $cols['61.m2']['dt'] .$r, $it['ill2_ge_61_dt']);
        $this->setCellValue($sh, $cols['61.m2']['ndt'].$r, $it['ill2_ge_61_ndt']);
        $this->setCellValue($sh, $cols['61.m2']['totr'].$r, '=IF('.$cols['61.pop']['tot'].$r.'=0,"",ROUND('.$cols['61.m2']['tot'].$r.'/'.$cols['61.pop']['tot'].$r.'*100,2))');
        $this->setCellValue($sh, $cols['61.m2']['rf']  .$r, '=IF('.$cols['61.pop']['f']  .$r.'=0,"",ROUND('.$cols['61.m2']['f']  .$r.'/'.$cols['61.pop']['f']  .$r.'*100,2))');
        $this->setCellValue($sh, $cols['61.m2']['rdt'] .$r, '=IF('.$cols['61.pop']['dt'] .$r.'=0,"",ROUND('.$cols['61.m2']['dt'] .$r.'/'.$cols['61.pop']['dt'] .$r.'*100,2))');
        $this->setCellValue($sh, $cols['61.m2']['rndt'].$r, '=IF('.$cols['61.pop']['dtf'].$r.'=0,"",ROUND('.$cols['61.m2']['ndt'].$r.'/'.$cols['61.pop']['dtf'].$r.'*100,2))');

        $r++; $stt++;
    }

    // Tổng cộng
    $this->setCellValue($sh, "B{$sumR}", 'Tổng cộng');

    // Các cột cần SUM (đếm — có cả ndt)
    $sumCols = [
        // Tổng dân số chung
        'E','F','G','H',
        // 15–25
        'I','J','K','L','M','O','Q','S','U','W','Y','AA',
        // 15–35
        'AC','AD','AE','AF','AG','AI','AK','AM','AO','AQ','AS','AU',
        // 15–60
        'AW','AX','AY','AZ','BA','BC','BE','BG','BI','BK','BM','BO',
        // ≥61
        'BQ','BR','BS','BT','BU','BW','BY','CA','CC','CE','CG','CI',
    ];
    foreach ($sumCols as $col) {
        $this->setCellValue($sh, $col.$sumR, "=SUM({$col}{$r0}:{$col}".($r-1).")");
    }

    // Dòng tổng cộng: % (totr, rf, rdt, rndt) cho M1 & M2 của 4 nhóm
    $pctPairs = [
        // 15–25
        ['num'=>'M','den'=>'I','out'=>'N'], ['num'=>'O','den'=>'J','out'=>'P'],
        ['num'=>'Q','den'=>'K','out'=>'R'], ['num'=>'S','den'=>'L','out'=>'T'],
        ['num'=>'U','den'=>'I','out'=>'V'], ['num'=>'W','den'=>'J','out'=>'X'],
        ['num'=>'Y','den'=>'K','out'=>'Z'], ['num'=>'AA','den'=>'L','out'=>'AB'],
        // 15–35
        ['num'=>'AG','den'=>'AC','out'=>'AH'], ['num'=>'AI','den'=>'AD','out'=>'AJ'],
        ['num'=>'AK','den'=>'AE','out'=>'AL'], ['num'=>'AM','den'=>'AF','out'=>'AN'],
        ['num'=>'AO','den'=>'AC','out'=>'AP'], ['num'=>'AQ','den'=>'AD','out'=>'AR'],
        ['num'=>'AS','den'=>'AE','out'=>'AT'], ['num'=>'AU','den'=>'AF','out'=>'AV'],
        // 15–60
        ['num'=>'BA','den'=>'AW','out'=>'BB'], ['num'=>'BC','den'=>'AX','out'=>'BD'],
        ['num'=>'BE','den'=>'AY','out'=>'BF'], ['num'=>'BG','den'=>'AZ','out'=>'BH'],
        ['num'=>'BI','den'=>'AW','out'=>'BJ'], ['num'=>'BK','den'=>'AX','out'=>'BL'],
        ['num'=>'BM','den'=>'AY','out'=>'BN'], ['num'=>'BO','den'=>'AZ','out'=>'BP'],
        // ≥61
        ['num'=>'BU','den'=>'BQ','out'=>'BV'], ['num'=>'BW','den'=>'BR','out'=>'BX'],
        ['num'=>'BY','den'=>'BS','out'=>'BZ'], ['num'=>'CA','den'=>'BT','out'=>'CB'],
        ['num'=>'CC','den'=>'BQ','out'=>'CD'], ['num'=>'CE','den'=>'BR','out'=>'CF'],
        ['num'=>'CG','den'=>'BS','out'=>'CH'], ['num'=>'CI','den'=>'BT','out'=>'CJ'],
    ];
    foreach ($pctPairs as $m) {
        $this->setCellValue($sh, "{$m['out']}{$sumR}",
            '=IF('.$m['den'].$sumR.'=0,"",ROUND('.$m['num'].$sumR.'/'.$m['den'].$sumR.'*100,2))'
        );
    }

    // format: viền + canh + ẩn số 0 ở cột %
    $this->drawThinBorders($sh, $r0, $sumR, 'A', $cfg['lastCol']);
    $sh->getStyle("A{$r0}:{$cfg['lastCol']}{$sumR}")
       ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sh->getStyle("A{$r0}:A{$sumR}")
       ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sh->getStyle("E8:CJ".($sumR-1))
           ->getNumberFormat()->setFormatCode('0;-0;;@');
    // Cột % (ẩn số 0)
    $pctCols = [
        'N','P','R','T','V','X','Z','AB',
        'AH','AJ','AL','AN','AP','AR','AT','AV',
        'BB','BD','BF','BH','BJ','BL','BN','BP',
        'BV','BX','BZ','CB','CD','CF','CH','CJ'
    ];
    foreach ($pctCols as $col) {
        $sh->getStyle("{$col}{$r0}:{$col}{$sumR}")
           ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
    }

    // footer ngày tháng
  $footerRow = $sumR + 2;
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $this->safeMerge($sh, "BW{$footerRow}:CC{$footerRow}");
        $this->setCellValue($sh, "BW{$footerRow}", $this->formatService->end_date($wardName));
        $sh->getStyle("BW{$footerRow}:CC{$footerRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // xuất file
    return response()->streamDownload(function () use ($wb) {
        $w = IOFactory::createWriter($wb, 'Xlsx');
        $w->setPreCalculateFormulas(false);
        $w->save('php://output');
    }, $this->getExportFilename($cfg['path']), [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}


    // public function export(Request $req)
    // {
    //     $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
    //     $scheme   = (string)$req->input('scheme', 'old');
    //     $geo      = (array)$req->input('geo', []);

    //     if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
    //         Calculation::getInstance()->setCalculationEnabled(false);
    //     }

    //     $cfg = [
    //         'path'      => resource_path('excel/6.15report.xlsx'),
    //         'header'    => ['province' => 'D1', 'ward' => 'D2'],
    //         'startRow'  => 8,               // dòng bắt đầu dữ liệu
    //         'sumRowLbl' => 9,               // hàng nhãn “Tổng cộng” có sẵn, sẽ đẩy xuống nếu chèn thêm
    //         'lastCol'   => 'CD',            // cột cuối cùng trong mẫu
    //         'footerDateRange' => 'BY16:CD16',
    //     ];

    //     $wb = IOFactory::load($cfg['path']);
    //     /** @var Worksheet $sh */
    //     $sh = $wb->getActiveSheet();

    //     // header
    //     $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
    //     $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));

    //     // dữ liệu
    //     $rows = $this->queryIlliteracyVillageSummary($academic, $scheme, $geo);

    //     // chèn dòng đủ
    //     $r0   = $cfg['startRow'];
    //     $sumR = $cfg['sumRowLbl'];
    //     $need = max(0, count($rows) - 1);
    //     if ($need > 0) {
    //         $sh->insertNewRowBefore($sumR, $need);
    //         for ($i=1; $i<=$need; $i++) {
    //             $this->replicateRowFormatMerged($sh, $r0, $r0+$i, $cfg['lastCol']);
    //         }
    //         $sumR += $need;
    //     }

    //     // bản đồ cột (dễ chỉnh)
    //     $cols = [
    //         'all' => ['tot'=>'E','f'=>'F','dt'=>'G','n_dt'=>'H'],
            
    //         // 15–25
    //         '15_25.pop' => ['tot'=>'I','f'=>'J','dt'=>'K','dtf'=>'L'],
    //         '15_25.m1'  => ['tot'=>'M','totr'=>'N','f'=>'O','rf'=>'P','dt'=>'Q','rdt'=>'R','ndt'=>'S','rndt'=>'T'],
    //         '15_25.m2'  => ['tot'=>'U','totr'=>'V','f'=>'W','rf'=>'X', 'dt'=>'Y','rdt'=>'Z','ndt'=>'AA','rndt'=>'AB'],

    //         // 15–35
    //         '15_35.pop' => ['tot'=>'AC','f'=>'AD','dt'=>'AE','dtf'=>'AF'],
    //         '15_35.m1'  => ['tot'=>'AG','totr'=>'AH','f'=>'AI','rf'=>'AJ','dt'=>'AK','rdt'=>'AL','ndt'=>'AM','rndt'=>'AN'],
    //         '15_35.m2'  => ['tot'=>'AO','totr'=>'AP','f'=>'AQ','rf'=>'AR','dt'=>'AS','rdt'=>'AT','ndt'=>'AU','rndt'=>'AV'],

    //         // 15–60
    //         '15_60.pop' => ['tot'=>'AW','f'=>'AX','dt'=>'AY','dtf'=>'AZ'],
    //         '15_60.m1'  => ['tot'=>'BA','totr'=>'BB','f'=>'BC','rf'=>'BD','dt'=>'BE','rdt'=>'BF','ndt'=>'BG','rndt'=>'BH'],
    //         '15_60.m2'  => ['tot'=>'BI','totr'=>'BJ','f'=>'BK','rf'=>'BL','dt'=>'BM','rdt'=>'BN','ndt'=>'BO','rndt'=>'BP'],

    //         // ≥61
    //         '61.pop'    => ['tot'=>'BQ','f'=>'BR','dt'=>'BS','dtf'=>'BT'],
    //         '61.m1'     => ['tot'=>'BU','totr'=>'BV','f'=>'BW','rf'=>'BX','dt'=>'BY','rdt'=>'BZ','ndt'=>'CA','rndt'=>'CB'],
    //         '61.m2'     => ['tot'=>'CC','totr'=>'CD','f'=>'CE','rf'=>'CF','dt'=>'CG','rdt'=>'CH','ndt'=>'CI','rndt'=>'CJ'],
    //     ];
              

    //     // ghi từng dòng
    //     $r = $r0; $stt=1;
    //     foreach ($rows as $it) {
    //         $this->setCellValue($sh, "A{$r}", $stt);
    //         $this->setCellValue($sh, "B{$r}", $it['unit_name']);

    //         // Tổng dân số
    //         $this->setCellValue($sh, $cols['all']['tot'].$r, $it['pop_all']);
    //         $this->setCellValue($sh, $cols['all']['f']  .$r, $it['pop_all_f']);
    //         $this->setCellValue($sh, $cols['all']['dt'] .$r, $it['pop_all_dt']);
    //         $this->setCellValue($sh, $cols['all']['n_dt'].$r, $it['pop_all_dt_f']);
    //         // helper
    //         $w = function($key,$sub,$field) use($sh,$cols,$r,$it) {
    //             $c = $cols[$key.'.'.$sub][$field];
    //             $this->setCellValue($sh, $c.$r, $it[$field === 'tot' ? ($sub==='pop'?'pop_'.$key.'_tot':'') : '' ]); // ko dùng
    //         };

    //         // 15–25
    //         $this->setCellValue($sh, $cols['15_25.pop']['tot'].$r, $it['pop_15_25']);
    //         $this->setCellValue($sh, $cols['15_25.pop']['f']  .$r, $it['pop_15_25_f']);
    //         $this->setCellValue($sh, $cols['15_25.pop']['dt'] .$r, $it['pop_15_25_dt']);
    //         $this->setCellValue($sh, $cols['15_25.pop']['dtf'].$r, $it['pop_15_25_dt_f']);

    //         $this->setCellValue($sh, $cols['15_25.m1']['tot'].$r, $it['ill1_15_25']);
    //         $this->setCellValue($sh, $cols['15_25.m1']['f']  .$r, $it['ill1_15_25_f']);
    //         $this->setCellValue($sh, $cols['15_25.m1']['dt'] .$r, $it['ill1_15_25_dt']);
    //         // tỉ lệ: nữ & dt
    //         $this->setCellValue($sh, $cols['15_25.m1']['rf'] .$r, '=IF('.$cols['15_25.pop']['f'].$r.'=0,"",ROUND('.$cols['15_25.m1']['f'].$r.'/'.$cols['15_25.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['15_25.m1']['rdt'].$r, '=IF('.$cols['15_25.pop']['dt'].$r.'=0,"",ROUND('.$cols['15_25.m1']['dt'].$r.'/'.$cols['15_25.pop']['dt'].$r.'*100,2))');

    //         $this->setCellValue($sh, $cols['15_25.m2']['tot'].$r, $it['ill2_15_25']);
    //         $this->setCellValue($sh, $cols['15_25.m2']['f']  .$r, $it['ill2_15_25_f']);
    //         $this->setCellValue($sh, $cols['15_25.m2']['dt'] .$r, $it['ill2_15_25_dt']);
    //         $this->setCellValue($sh, $cols['15_25.m2']['rf'] .$r, '=IF('.$cols['15_25.pop']['f'].$r.'=0,"",ROUND('.$cols['15_25.m2']['f'].$r.'/'.$cols['15_25.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['15_25.m2']['rdt'].$r, '=IF('.$cols['15_25.pop']['dt'].$r.'=0,"",ROUND('.$cols['15_25.m2']['dt'].$r.'/'.$cols['15_25.pop']['dt'].$r.'*100,2))');

    //         // 15–35
    //         $this->setCellValue($sh, $cols['15_35.pop']['tot'].$r, $it['pop_15_35']);
    //         $this->setCellValue($sh, $cols['15_35.pop']['f']  .$r, $it['pop_15_35_f']);
    //         $this->setCellValue($sh, $cols['15_35.pop']['dt'] .$r, $it['pop_15_35_dt']);
    //         $this->setCellValue($sh, $cols['15_35.pop']['dtf'].$r, $it['pop_15_35_dt_f']);

    //         $this->setCellValue($sh, $cols['15_35.m1']['tot'].$r, $it['ill1_15_35']);
    //         $this->setCellValue($sh, $cols['15_35.m1']['f']  .$r, $it['ill1_15_35_f']);
    //         $this->setCellValue($sh, $cols['15_35.m1']['dt'] .$r, $it['ill1_15_35_dt']);
    //         $this->setCellValue($sh, $cols['15_35.m1']['rf'] .$r, '=IF('.$cols['15_35.pop']['f'].$r.'=0,"",ROUND('.$cols['15_35.m1']['f'].$r.'/'.$cols['15_35.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['15_35.m1']['rdt'].$r, '=IF('.$cols['15_35.pop']['dt'].$r.'=0,"",ROUND('.$cols['15_35.m1']['dt'].$r.'/'.$cols['15_35.pop']['dt'].$r.'*100,2))');

    //         $this->setCellValue($sh, $cols['15_35.m2']['tot'].$r, $it['ill2_15_35']);
    //         $this->setCellValue($sh, $cols['15_35.m2']['f']  .$r, $it['ill2_15_35_f']);
    //         $this->setCellValue($sh, $cols['15_35.m2']['dt'] .$r, $it['ill2_15_35_dt']);
    //         $this->setCellValue($sh, $cols['15_35.m2']['rf'] .$r, '=IF('.$cols['15_35.pop']['f'].$r.'=0,"",ROUND('.$cols['15_35.m2']['f'].$r.'/'.$cols['15_35.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['15_35.m2']['rdt'].$r, '=IF('.$cols['15_35.pop']['dt'].$r.'=0,"",ROUND('.$cols['15_35.m2']['dt'].$r.'/'.$cols['15_35.pop']['dt'].$r.'*100,2))');

    //         // 15–60
    //         $this->setCellValue($sh, $cols['15_60.pop']['tot'].$r, $it['pop_15_60']);
    //         $this->setCellValue($sh, $cols['15_60.pop']['f']  .$r, $it['pop_15_60_f']);
    //         $this->setCellValue($sh, $cols['15_60.pop']['dt'] .$r, $it['pop_15_60_dt']);
    //         $this->setCellValue($sh, $cols['15_60.pop']['dtf'].$r, $it['pop_15_60_dt_f']);

    //         $this->setCellValue($sh, $cols['15_60.m1']['tot'].$r, $it['ill1_15_60']);
    //         $this->setCellValue($sh, $cols['15_60.m1']['f']  .$r, $it['ill1_15_60_f']);
    //         $this->setCellValue($sh, $cols['15_60.m1']['dt'] .$r, $it['ill1_15_60_dt']);
    //         $this->setCellValue($sh, $cols['15_60.m1']['rf'] .$r, '=IF('.$cols['15_60.pop']['f'].$r.'=0,"",ROUND('.$cols['15_60.m1']['f'].$r.'/'.$cols['15_60.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['15_60.m1']['rdt'].$r, '=IF('.$cols['15_60.pop']['dt'].$r.'=0,"",ROUND('.$cols['15_60.m1']['dt'].$r.'/'.$cols['15_60.pop']['dt'].$r.'*100,2))');

    //         $this->setCellValue($sh, $cols['15_60.m2']['tot'].$r, $it['ill2_15_60']);
    //         $this->setCellValue($sh, $cols['15_60.m2']['f']  .$r, $it['ill2_15_60_f']);
    //         $this->setCellValue($sh, $cols['15_60.m2']['dt'] .$r, $it['ill2_15_60_dt']);
    //         $this->setCellValue($sh, $cols['15_60.m2']['rf'] .$r, '=IF('.$cols['15_60.pop']['f'].$r.'=0,"",ROUND('.$cols['15_60.m2']['f'].$r.'/'.$cols['15_60.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['15_60.m2']['rdt'].$r, '=IF('.$cols['15_60.pop']['dt'].$r.'=0,"",ROUND('.$cols['15_60.m2']['dt'].$r.'/'.$cols['15_60.pop']['dt'].$r.'*100,2))');

    //         // ≥61
    //         $this->setCellValue($sh, $cols['61.pop']['tot'].$r, $it['pop_ge_61']);
    //         $this->setCellValue($sh, $cols['61.pop']['f']  .$r, $it['pop_ge_61_f']);
    //         $this->setCellValue($sh, $cols['61.pop']['dt'] .$r, $it['pop_ge_61_dt']);
    //         $this->setCellValue($sh, $cols['61.pop']['dtf'].$r, $it['pop_ge_61_dt_f']);

    //         $this->setCellValue($sh, $cols['61.m1']['tot'].$r, $it['ill1_ge_61']);
    //         $this->setCellValue($sh, $cols['61.m1']['f']  .$r, $it['ill1_ge_61_f']);
    //         $this->setCellValue($sh, $cols['61.m1']['dt'] .$r, $it['ill1_ge_61_dt']);
    //         $this->setCellValue($sh, $cols['61.m1']['rf'] .$r, '=IF('.$cols['61.pop']['f'].$r.'=0,"",ROUND('.$cols['61.m1']['f'].$r.'/'.$cols['61.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['61.m1']['rdt'].$r, '=IF('.$cols['61.pop']['dt'].$r.'=0,"",ROUND('.$cols['61.m1']['dt'].$r.'/'.$cols['61.pop']['dt'].$r.'*100,2))');

    //         $this->setCellValue($sh, $cols['61.m2']['tot'].$r, $it['ill2_ge_61']);
    //         $this->setCellValue($sh, $cols['61.m2']['f']  .$r, $it['ill2_ge_61_f']);
    //         $this->setCellValue($sh, $cols['61.m2']['dt'] .$r, $it['ill2_ge_61_dt']);
    //         $this->setCellValue($sh, $cols['61.m2']['rf'] .$r, '=IF('.$cols['61.pop']['f'].$r.'=0,"",ROUND('.$cols['61.m2']['f'].$r.'/'.$cols['61.pop']['f'].$r.'*100,2))');
    //         $this->setCellValue($sh, $cols['61.m2']['rdt'].$r, '=IF('.$cols['61.pop']['dt'].$r.'=0,"",ROUND('.$cols['61.m2']['dt'].$r.'/'.$cols['61.pop']['dt'].$r.'*100,2))');

    //         $r++; $stt++;
    //     }

    //     // Tổng cộng
    //     $this->setCellValue($sh, "B{$sumR}", 'Tổng cộng');

    //     // Các cột cần SUM (đã dịch từ C sang E)
    //     $sumCols = [
    //         // Tổng dân số chung
    //         'E','F','G','H',
    //         // 15–25
    //         'I','J','K','L','M','O','Q','S','U','W','Y','AA',
    //         // 15–35
    //         'AC','AD','AE','AF','AG','AI','AK','AM','AO','AQ','AS','AU',
    //         // 15–60
    //         'AW','AX','AY','AZ','BA','BC','BE','BG','BI','BK','BM','BO',
    //         // ≥61
    //         'BQ','BR','BS','BT','BU','BW','BY','CA','CC','CE','CG','CI',
    //     ];
    //     foreach ($sumCols as $col) {
    //         $this->setCellValue($sh, $col.$sumR, "=SUM({$col}{$r0}:{$col}".($r-1).")");
    //     }
        

    //         // Dòng tổng cộng: đủ 4 tỉ lệ cho M1 & M2 của 4 nhóm
    //     $pctPairs = [
    //         // 15–25 (m1, m2)
    //         ['num'=>'M','den'=>'I','out'=>'N'], ['num'=>'O','den'=>'J','out'=>'P'],
    //         ['num'=>'Q','den'=>'K','out'=>'R'], ['num'=>'S','den'=>'L','out'=>'T'],
    //         ['num'=>'U','den'=>'I','out'=>'V'], ['num'=>'W','den'=>'J','out'=>'X'],
    //         ['num'=>'Y','den'=>'K','out'=>'Z'], ['num'=>'AA','den'=>'L','out'=>'AB'],

    //         // 15–35
    //         ['num'=>'AG','den'=>'W','out'=>'AH'], ['num'=>'AI','den'=>'X','out'=>'AJ'],
    //         ['num'=>'AK','den'=>'Y','out'=>'AL'], ['num'=>'AM','den'=>'Z','out'=>'AN'],
    //         ['num'=>'AO','den'=>'W','out'=>'AP'], ['num'=>'AQ','den'=>'X','out'=>'AR'],
    //         ['num'=>'AS','den'=>'Y','out'=>'AT'], ['num'=>'AU','den'=>'Z','out'=>'AV'],

    //         // 15–60
    //         ['num'=>'BA','den'=>'AW','out'=>'BB'], ['num'=>'BC','den'=>'AX','out'=>'BD'],
    //         ['num'=>'BE','den'=>'AY','out'=>'BF'], ['num'=>'BG','den'=>'AZ','out'=>'BH'],
    //         ['num'=>'BI','den'=>'AW','out'=>'BJ'], ['num'=>'BK','den'=>'AX','out'=>'BL'],
    //         ['num'=>'BM','den'=>'AY','out'=>'BN'], ['num'=>'BO','den'=>'AZ','out'=>'BP'],

    //         // ≥61
    //         ['num'=>'BU','den'=>'BQ','out'=>'BV'], ['num'=>'BW','den'=>'BR','out'=>'BX'],
    //         ['num'=>'BY','den'=>'BS','out'=>'BZ'], ['num'=>'CA','den'=>'BT','out'=>'CB'],
    //         ['num'=>'CC','den'=>'BQ','out'=>'CD'], ['num'=>'CE','den'=>'BR','out'=>'CF'],
    //         ['num'=>'CG','den'=>'BS','out'=>'CH'], ['num'=>'CI','den'=>'BT','out'=>'CJ'],
    //     ];
    //     foreach ($pctPairs as $m) {
    //         $this->setCellValue($sh, "{$m['out']}{$sumR}",
    //             '=IF('.$m['den'].$sumR.'=0,"",ROUND('.$m['num'].$sumR.'/'.$m['den'].$sumR.'*100,2))'
    //         );
    //     }


    //     // format: viền + canh + ẩn số 0 ở cột %
    //     $this->drawThinBorders($sh, $r0, $sumR, 'A', $cfg['lastCol']);
    //     $sh->getStyle("A{$r0}:{$cfg['lastCol']}{$sumR}")
    //     ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    //     $sh->getStyle("A{$r0}:A{$sumR}")
    //     ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     // foreach (['L','N','Q','S','AI','AK','AN','AP','BC','BE','BH','BJ','BW','BY','CB','CD'] as $col) {
    //     //     $sh->getStyle("{$col}{$r0}:{$col}{$sumR}")
    //     //     ->getNumberFormat()->setFormatCode('0.00;-0.00;;@'); // ẩn số 0
    //     // }
        
    //     // Format các cột % (ẩn số 0)
    //     $pctCols = [
    //         'N','P','R','T','V','X','Z','AB',
    //         'AH','AJ','AL','AN','AP','AR','AT','AV',
    //         'BB','BD','BF','BH','BJ','BL','BN','BP',
    //         'BV','BX','BZ','CB','CD','CF','CH','CJ'
    //     ];
    //     foreach ($pctCols as $col) {
    //         $sh->getStyle("{$col}{$r0}:{$col}{$sumR}")
    //         ->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
    //     }
        
    //     // footer ngày tháng
    //     $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
    //     $this->safeMerge($sh, $cfg['footerDateRange']);
    //     [$c1] = explode(':', $cfg['footerDateRange'], 2);
    //     $this->setCellValue($sh, $c1, $this->formatService->end_date($wardName));
    //     $sh->getStyle($cfg['footerDateRange'])->getAlignment()
    //     ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //     ->setVertical(Alignment::VERTICAL_CENTER);

    //     // xuất file
    //     return response()->streamDownload(function () use ($wb) {
    //         $w = IOFactory::createWriter($wb, 'Xlsx');
    //         $w->setPreCalculateFormulas(false);
    //         $w->save('php://output');
    //     }, 'TongHop_XoaMuChu_Thon_'.date('Ymd_His').'.xlsx', [
    //         'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //     ]);
    // }
}