<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use App\Services\GeoService;
use App\Services\ReportFormatService;

class IlliteratePeopleListReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* ========================= Template mapping ========================= */
    private function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/5.4.XMC.DS.MuChu.xlsx'),   // đặt đúng đường dẫn file mẫu của bạn
            'header' => [
                'province' => 'C2',
                'ward' => 'C3',
                'village'     => 'C4',
            ],
            'dateCell'  => 'E4',      // ô tiêu đề ngày (trên mẫu bạn là dòng ngay dưới title)
            'startRow'  => 10,         // hàng đầu của dữ liệu (ngay dưới tiêu đề cột)
            'lastCol'   => 'L',       // cột cuối cùng trên hàng mẫu (để replicate style/merge)
            // cột xuất dữ liệu
            'col' => [
                'stt'       => 'A',
                'name'      => 'B',
                'dob_m'     => 'D',   // Nam
                'dob_f'     => 'E',   // Nữ
                'ethnicity' => 'F',
                'edu'       => 'G',   // Trình độ văn hoá (bo_hoc_lop/12)
                'policy'    => 'H',   // Đối tượng hưởng chính sách
                'residence' => 'I',   // Nơi cư trú
                // cột I trên mẫu là vùng rộng của “Nơi cư trú”, ta để trống để khớp merge sẵn
                'literacy_mmyy' => 'J', // Thời gian được công nhận biết chữ (tháng/năm)
                'ticket'        => 'K', // Số phiếu (từ Household.ticket_no)
                'head_name'     => 'L', // Họ tên chủ hộ
            ],
        ];
    }

    /* =========================== Export main =========================== */
    public function export(Request $req)
    {
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);

        $rows = $this->queryList($academic, $scheme, $geo, $y1 - 60, $y1 - 15);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['village'], $this->geoService->geoNameFromId($geo['village_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $this->setCellValue($sh, $cfg['dateCell'],           $this->formatService->top_date());
        $sh->getStyle('E4:I4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // Nhân bản dòng
        $startRow = $cfg['startRow'];
        $need = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
        }
        // dd($need);
        // Đổ dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($rows as $row) {
            $this->setCellValue($sh, $cfg['col']['stt'].$r,  $stt++);
             $sh->mergeCells('B'.$r.':'.'C'.$r);
            $this->setCellValue($sh, $cfg['col']['name'].$r, $row['full_name']);

            // Nam / Nữ (chỉ ghi 1 cột tuỳ giới)
            $this->setCellValue($sh, $cfg['col']['dob_m'].$r, $row['is_female'] ? '' : $row['dob_fmt']);
            $this->setCellValue($sh, $cfg['col']['dob_f'].$r, $row['is_female'] ? $row['dob_fmt'] : '');

            $this->setCellValue($sh, $cfg['col']['ethnicity'].$r, $row['ethnicity'] ?: '');
            $this->setCellValue($sh, $cfg['col']['edu'].$r,       $row['edu_fmt'] ?: '');
            $this->setCellValue($sh, $cfg['col']['policy'].$r,    $row['policy'] ?: '');
            $this->setCellValue($sh, $cfg['col']['residence'].$r, $row['residence'] ?: '');
            $this->setCellValue($sh, $cfg['col']['literacy_mmyy'].$r, $row['bc_mmyy'] ?: '');
            $this->setCellValue($sh, $cfg['col']['ticket'].$r,        $row['ticket_no'] ?: '');
            $this->setCellValue($sh, $cfg['col']['head_name'].$r,     $row['head_name'] ?: '');
            $r++;
        }

        // Căn giữa hai cột ngày sinh để dễ đọc
        $sh->getStyle($cfg['col']['dob_m'].$startRow.':'.$cfg['col']['dob_f'].$r)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // dd(count($rows),'J'.($stt+10));
        $this->setCellValue($sh, 'J'.($r+1), $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
          $sh->mergeCells('J'.($r+1).':L'.($r+1));
        $sh->getStyle('J'.($r+1).':L'.($r+1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


        // Xuất
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* =========================== Data query ============================ */
    private function queryList(string $academic, string $scheme, array $geo, int $yMin, int $yMax): array
    {
        // latest person_educations của năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        // latest literacy record
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
            ->join('people as p','p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe', fn($j)=>$j->on('pe.person_id','=','p.id')->where('pe.rn',1))
            ->leftJoinSub($xmcLatest,'xmc',fn($j)=>$j->on('xmc.person_id','=','p.id')->where('xmc.rn',1))
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->leftJoin('priority_categories as pc','pc.id','=','p.priority_category_id')
            ->leftJoin('geo_units as v','v.id','=','p.village_id')         // tên thôn/xóm của PERSON
          
            // Hộ khẩu để lấy số phiếu & chủ hộ + địa chỉ hộ
            ->leftJoin('household_members as hm','hm.person_id','=','p.id')
            ->leftJoin('households as h','h.id','=','hm.household_id')
            ->leftJoin('people as hp','hp.id','=','h.head_person_id')      // chủ hộ
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academic)
            ->whereBetween('p.dob_year', [$yMin, $yMax]);

        // áp địa bàn (scheme/new/old)
        $this->applyGeoFilter($qb, $scheme, $geo);

        // ==== Điều kiện mức 1/mức 2 (giống các báo cáo trước) ====
        $isF   = "(p.is_female = 1)";
        $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad  = "(COALESCE(pe.graduation_level_id,0) < 2)";

        $lv1 = "( ({$grad} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";

        if ((int)$this->formatService->cong_thuc_baocao(5,37) === 1) {
            $lv2Drop = "({$ebn} <= 5)";
        } else {
            $lv2Drop = "({$ebn} > 3 AND {$ebn} <= 5)";
        }
        $lv2 = "( ({$grad} AND {$lv2Drop}) OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";

        // Thời gian được công nhận biết chữ (mm/yyyy) – khi hoàn thành lớp 3 hoặc 5 và KHÔNG tái mù mức 1
        $bcDateExpr = "CASE WHEN (xmc.hoan_thanh_lop IN (3,5) AND COALESCE(xmc.tai_mu_chu_muc,0) <> 1)
                            THEN DATE_FORMAT(xmc.updated_at,'%m/%Y') ELSE '' END";

        // Trình độ văn hoá: (bo_hoc_lop/12) nếu graduation_level_id < 2; trống->0
        $classNum = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $eduFmt   = "CASE WHEN {$grad} THEN CONCAT({$classNum}, '/12') ELSE '' END";

        // Nơi cư trú: ưu tiên địa chỉ hộ khẩu, fallback địa chỉ cá nhân; luôn gắn tên thôn/xóm (nếu có)
        $residence = "TRIM(CONCAT(
                        TRIM(COALESCE(NULLIF(h.addressline,''), COALESCE(p.addressline,'')) ),
                        CASE WHEN COALESCE(v.name,'') <> '' THEN CONCAT(' - ', v.name) ELSE '' END
                      ))";

        $rows = $qb->whereRaw("({$lv1} OR {$lv2})")
            ->selectRaw("
                p.id,
                TRIM(CONCAT(COALESCE(p.ho_dem,''), ' ', COALESCE(p.ten,''))) as full_name,
                p.is_female,
                LPAD(COALESCE(NULLIF(p.dob_day,0),1),2,'0') as dd,
                LPAD(COALESCE(NULLIF(p.dob_month,0),1),2,'0') as mm,
                LPAD(COALESCE(p.dob_year,0),4,'0') as yyyy,
                COALESCE(e.name,'') as ethnicity,
                {$eduFmt} as edu_fmt,
                COALESCE(pc.name,'') as policy,
                {$residence} as residence,
                {$bcDateExpr} as bc_mmyy,
                COALESCE(h.ticket_no, p.so_phieu, '') as ticket_no,
                TRIM(CONCAT(COALESCE(hp.ho_dem,''), ' ', COALESCE(hp.ten,''))) as head_name,
                COALESCE(v.name,'') as village_name
            ")
            ->orderBy('v.name')
            ->orderBy('full_name')
            ->get()
            ->map(function($r){
                // format dob dd/mm/yyyy (để ghi vào 1 trong 2 cột Nam/Nữ)
                $dob = sprintf('%s/%s/%s', $r->dd, $r->mm, $r->yyyy);
                return [
                    'full_name'  => (string)$r->full_name,
                    'is_female'  => (bool)$r->is_female,
                    'dob_fmt'    => $dob,
                    'ethnicity'  => (string)$r->ethnicity,
                    'edu_fmt'    => (string)$r->edu_fmt,
                    'policy'     => (string)$r->policy,
                    'residence'  => (string)$r->residence,
                    'bc_mmyy'    => (string)$r->bc_mmyy,
                    'ticket_no'  => (string)$r->ticket_no,
                    'head_name'  => (string)$r->head_name,
                ];
            })
            ->all();

        return $rows;
    }
}
