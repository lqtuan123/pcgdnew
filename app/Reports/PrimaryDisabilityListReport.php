<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use App\Models\DisabilityCategory;

class PrimaryDisabilityListReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // ===== 1) Đầu vào =====
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);
        $schoolId = (int) ($req->input('education.school_id') ?? $req->input('school_id') ?? 0);

        // dải năm sinh 6..14 tuổi
        $years = [];
        for ($a = 6; $a <= 14; $a++) $years[] = $y1 - $a;

        // ===== 2) Lấy dữ liệu danh sách =====
        $rows = $this->queryDisabledPrimaryList($academic, $scheme, $geo, $years, $schoolId);

        // ===== 3) Nạp template & header =====
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        if (!empty($cfg['header']['school']) && $schoolId) {
            $schoolName = optional(\App\Models\School::find($schoolId))->name ?? '';
            $this->setCellValue($sh, $cfg['header']['school'], $schoolName);
        }
        if (!empty($cfg['dateTop'])) {
            $this->setCellValue($sh, $cfg['dateTop'], $this->formatService->top_date());
        }

        // ===== 4) Chuẩn bị vùng dữ liệu (copy 1 dòng mẫu) =====
        $startRow = $cfg['startRow'];  // 7
        $lastCol  = $cfg['lastCol'];   // 'M'
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
        }

       // ===== 5) Đổ dữ liệu =====
        $r = $startRow; $stt = 1;
        foreach ($rows as $it) {
            // STT
            $this->setCellValue($sh, 'A'.$r, $stt);

            // Họ và tên – gộp B:C cho dòng hiện tại rồi ghi vào cột B
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name']);

            // Ngày sinh
            $dobTxt = $this->fmtDob($it['dob_day'], $it['dob_month'], $it['dob_year']);
            $this->setCellValue($sh, 'D'.$r, $dobTxt ?: null);

            // Giới tính, dân tộc, số phiếu
            $this->setCellValue($sh, 'E'.$r, $it['gender']);
            $this->setCellValue($sh, 'F'.$r, $it['ethnicity'] ?: null);
            $this->setCellValue($sh, 'G'.$r, $it['so_phieu'] ?: null);

            // Chủ hộ, quan hệ, địa chỉ
            $this->setCellValue($sh, 'H'.$r, $it['chu_ho']  ?: null);
            $this->setCellValue($sh, 'I'.$r, $it['quan_he'] ?: null);
            $this->setCellValue($sh, 'J'.$r, $it['dia_chi'] ?: null);

            // Khối, trường, tình trạng KT, ghi chú
            $this->setCellValue($sh, 'K'.$r, $it['grade']  ?: null);
            $this->setCellValue($sh, 'L'.$r, $it['school'] ?: null);
           // ...
            $this->setCellValue($sh, 'M'.$r, $it['disabilities'] ?: null, ['wrap' => true]);
            $this->setCellValue($sh, 'N'.$r, $it['note'] ?? null,          ['wrap' => true]);
            // ...

            $r++; $stt++;
        }
       
        
        // ===== 6) Viền + footer ngày tháng năm =====
        $firstDataRow = $startRow;
        $lastDataRow  = max($startRow, $r - 1);

        //         // Cột cần wrap (M = Tình trạng, N = Ghi chú)
        // foreach (['M','N'] as $col) {
        //     // Cho xuống dòng + canh trái + canh đỉnh
        //     $sh->getStyle(sprintf('%s%d:%s%d', $col, $firstDataRow, $col, $lastDataRow))
        //     ->getAlignment()
        //     ->setWrapText(true)
        //     ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
        //     ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        //     // Nới rộng cột (tuỳ bạn chọn số)
        //     $sh->getColumnDimension($col)->setWidth(45);
        //     // Hoặc dùng autosize (không cần setWidth):
        //     // $sh->getColumnDimension($col)->setAutoSize(true);
        // }

        // // Cho hàng tự giãn theo nội dung (rất quan trọng nếu template đặt chiều cao cố định)
        // for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
        //     $sh->getRowDimension($row)->setRowHeight(-1); // -1 = auto height (default)
        // }
        
        $this->drawThinBorders($sh, $firstDataRow, $lastDataRow, 'A', $lastCol);

        if (!empty($cfg['dateBottom'])) {
            $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
           $this->setCellValue($sh,'D3'.($lastDataRow  + 2),  $this->formatService->end_date( $wardName));
        }

        // ===== 7) Xuất file =====
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Trả về mảng các bản ghi:
     * [
     *   [
     *     full_name, dob_day, dob_month, dob_year, gender, ethnicity,
     *     so_phieu, chu_ho, quan_he, dia_chi,
     *     grade (1..5), school, disabilities (chuỗi), note
     *   ], ...
     * ]
     */
    protected function queryDisabledPrimaryList(
    string $academic, string $scheme, array $geo, array $years, int $schoolId
): array {
    // 0) Map id => name nhóm khuyết tật
    $catNames = DisabilityCategory::pluck('name', 'id');

    // 1) Subquery person_educations mới nhất theo năm học
    $peLatest = DB::table('person_educations as pe1')
        ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
        ->where('pe1.academic_year', $academic);

    // 2) Subquery person_disabilities mới nhất theo người
    $pdLatest = DB::table('person_disabilities as t')
        ->select('t.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY t.person_id ORDER BY t.id DESC) AS rn'))
        ->where('t.is_deleted', false);

    // 3) Subquery household_members — lấy 1 hộ “gần nhất” của người
    $hmLatest = DB::table('household_members as hm1')
        ->select('hm1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY hm1.person_id ORDER BY hm1.id DESC) AS rn'));

    // 4) Base query
    $qb = DB::table('survey_form_details as d')
        ->join('survey_forms as f', function ($j) {
            $j->on('f.id', '=', 'd.survey_form_id')->where('f.is_deleted', false);
        })
        ->join('people as p', 'p.id', '=', 'd.person_id')
        ->leftJoinSub($pdLatest, 'pd', function ($j) {
            $j->on('pd.person_id', '=', 'p.id')->where('pd.rn', 1);
        })
        ->leftJoinSub($peLatest, 'pe', function ($j) {
            $j->on('pe.person_id', '=', 'p.id')->where('pe.rn', 1);
        })
        ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
        ->leftJoin('schools as s', function($j) {
            $j->on('s.id', '=', 'pe.school_id')
              ->where(function($w) {
                  $w->whereNull('s.is_dissolved')
                    ->orWhere('s.is_dissolved', false)
                    ->orWhere('s.is_dissolved', 0);
              });
        })

        // Hộ và chủ hộ
        ->leftJoinSub($hmLatest, 'hm', function ($j) {
            $j->on('hm.person_id', '=', 'p.id')->where('hm.rn', 1);
        })
        ->leftJoin('households as h', 'h.id', '=', 'hm.household_id')
        ->leftJoin('people as hp', 'hp.id', '=', 'h.head_person_id') // head person

        // Danh mục dân tộc
        ->leftJoin('ethnicities as et', 'et.id', '=', 'p.ethnicity_id')

        ->where('d.is_deleted', false)
        ->where('d.academic_year', $academic)
        ->whereIn('p.dob_year', $years);

    // 5) Lọc theo địa bàn cư trú (đang dùng helper có sẵn)
    $this->applyGeoFilter($qb, $scheme, $geo);

    // 6) Lọc theo trường nếu có
    if ($schoolId > 0) {
        $qb->where('pe.school_id', $schoolId);
    }

    // 7) Join tên địa bàn để dựng địa chỉ (theo scheme)
    if ($scheme === 'old') {
        // người
        $qb->leftJoin('geo_units as pw', 'pw.id', '=', 'p.old_ward_id')
           ->leftJoin('geo_units as pp', 'pp.id', '=', 'p.old_province_id');
        // hộ
        $qb->leftJoin('geo_units as hw', 'hw.id', '=', 'h.old_ward_id')
           ->leftJoin('geo_units as hpv', 'hpv.id', '=', 'h.old_province_id');
    } else {
        // người
        $qb->leftJoin('geo_units as pw', 'pw.id', '=', 'p.new_ward_id')
           ->leftJoin('geo_units as pp', 'pp.id', '=', 'p.new_province_id');
        // hộ
        $qb->leftJoin('geo_units as hw', 'hw.id', '=', 'h.new_ward_id')
           ->leftJoin('geo_units as hpv', 'hpv.id', '=', 'h.new_province_id');
    }

    // 8) Điều kiện là người khuyết tật & đang học TiH
    $isDisabled = "JSON_LENGTH(pd.disability_ids) > 0";
    // $isPrimary  = "pe.education_block_id IS NOT NULL AND CAST(eb.name AS UNSIGNED) BETWEEN 1 AND 5";

    // 9) Biểu thức địa chỉ: ưu tiên địa chỉ người, rỗng thì lấy địa chỉ hộ
    $personAddr = "TRIM(CONCAT_WS(', ', NULLIF(p.addressline,''), pw.name, pp.name))";
    $houseAddr  = "TRIM(CONCAT_WS(', ', NULLIF(h.addressline,''), hw.name, hpv.name))";
    $personAddr = "  NULLIF(p.addressline,'')";
    $houseAddr  = " NULLIF(h.addressline,'')";
    $addrExpr   = "CASE WHEN LENGTH($personAddr)>0 THEN $personAddr ELSE $houseAddr END";

    // 10) Select
    $rows = $qb->whereRaw($isDisabled)
        // ->whereRaw($isPrimary)
        ->select([
            'p.ho_dem','p.ten','p.dob_day','p.dob_month','p.dob_year','p.is_female',
            'p.so_phieu',
            'et.name as ethnicity',
            DB::raw('CAST(eb.name AS UNSIGNED) AS grade_num'),
            's.name as school_name',
            'pd.disability_ids',
            'hm.role as member_role',
            DB::raw("TRIM(CONCAT(COALESCE(hp.ho_dem,''), CASE WHEN hp.ho_dem IS NULL OR hp.ho_dem='' THEN '' ELSE ' ' END, COALESCE(hp.ten,''))) as head_fullname"),
            DB::raw("$addrExpr as address_text"),
        ])
        ->orderBy('p.dob_year')->orderBy('p.ten')
        ->get();

    // 11) Chuẩn hoá về mảng cho Excel
    $out = [];
    foreach ($rows as $r) {
        $ids   = json_decode($r->disability_ids ?? '[]', true) ?: [];
        $names = [];
        foreach ($ids as $id) if (isset($catNames[$id])) $names[] = $catNames[$id];

        $out[] = [
            'full_name'   => trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? '')),
            'dob_day'     => (int)($r->dob_day ?? 0),
            'dob_month'   => (int)($r->dob_month ?? 0),
            'dob_year'    => (int)($r->dob_year ?? 0),
            'gender'      => ($r->is_female ? 'Nữ' : 'Nam'),
            'ethnicity'   => $r->ethnicity,
            'so_phieu'    => $r->so_phieu ?: null,

            'chu_ho'      => $r->head_fullname ?: null,   // tên chủ hộ
            'quan_he'     => $r->member_role ?: null,     // quan hệ với chủ hộ
            'dia_chi'     => $r->address_text ?: null,    // địa chỉ đã fallback

            'grade'       => (int)($r->grade_num ?? 0) ?: null,
            'school'      => $r->school_name,
            'disabilities'=> $names ? implode(', ', $names) : null,
            'note'        => null,
        ];
    }

    return $out;
}


    protected function fmtDob(?int $d, ?int $m, ?int $y): ?string
    {
        $d = (int)$d; $m = (int)$m; $y = (int)$y;
        if ($y <= 0) return null;
        if ($d <= 0 || $m <= 0) return sprintf('%04d', $y);
        return sprintf('%02d/%02d/%04d', $d, $m, $y);
    }

    protected function templateCfg(): array
    {
        return [
            // Đổi tên file đúng với template của bạn
            'path' => resource_path('excel/2.8.TiH.DSKhuyetTat.xlsx'),

            'header' => [
                'province' => 'C1',
                'ward'     => 'C2',
                // nếu có chỗ đổ tên trường trên template, điền toạ độ vào đây:
                'school'   => 'A1',
            ],
            'dateTop'     => 'D3',   // ô "Ngày … tháng … năm …" ở phần trên (tuỳ template)
            'dateBottom'  => 'J14',  // ô ngày ở chân trang (tuỳ template)

            'startRow'    => 7,      // dòng dữ liệu đầu tiên
            'lastCol'     => 'N',    // cột cuối cùng (Ghi chú)
        ];
    }

    /** Viền mảnh cho vùng dữ liệu */
    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
}
