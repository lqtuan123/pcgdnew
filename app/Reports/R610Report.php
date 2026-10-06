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

class R610Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
 public function export(Request $req)
    {
        // ===== 1) Input
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template
        $cfg = [
            // thay đường dẫn này theo file của bạn
            'path'      => resource_path('excel/6.10.DS.ChuyenDen.xlsx'),
            'header'    => [
                'province' => 'C1',  // ô ghi Tỉnh/Thành
                'district' => 'C2',  // ô ghi Quận/Huyện
                'yearline' => 'D3',  // ô ghi dòng "Năm ...." (tuỳ template, có thể bỏ nếu không dùng)
            ],
            'startRow'  => 7,       // dòng đầu ghi dữ liệu ngay dưới tiêu đề cột
            'lastCol'   => 'N',     // cột "Ghi chú"
        ];
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['district'], $this->geoService->geoNameFromId($geo['district_id'] ?? $geo['ward_id'] ?? null));
        if (!empty($cfg['header']['yearline'])) {
            $sh->setCellValue($cfg['header']['yearline'], 'Năm '.$academic);
        }
        $startRow = $cfg['startRow'];
        $sumRow = $startRow + 2;
        // ===== 3) Dữ liệu
        $rows = $this->queryMovedIn($academic, $scheme, $geo);
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($sumRow, $need);
            for ($i=1; $i<=$need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
            $sumRow += $need;   // rất quan trọng: tránh “thêm 1 dòng rỗng sau Tổng cộng”
        }

        // ===== 4) Ghi dữ liệu
        $r = $cfg['startRow']; $stt = 1;
        foreach ($rows as $it) {
            // A: STT
            $this->setCellValue($sh, 'A'.$r, $stt);

            // B: Họ và tên
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name']);
             
            // C: Ngày sinh (dd/mm/yyyy)
            
            $d = (int)($r->dob_day   ?? 0);
            $m = (int)($r->dob_month ?? 0);
            $y = (int)($r->dob_year  ?? 0);
            $dob = ($d && $m && $y) ? sprintf('%02d/%02d/%04d', $d, $m, $y)
                 : ($m && $y ? sprintf('%02d/%04d', $m, $y)
                 : ($y ? (string)$y : ''));

            
            $this->setCellValue($sh, 'D'.$r, $dob);

            // D: Nữ (đánh 1, để trống nếu không)
            $this->setCellValue($sh, 'E'.$r, !empty($it['is_female']) ? 1 : '');

            // E: Dân tộc
            $this->setCellValue($sh, 'F'.$r, $it['ethnicity'] ?? '');

            // F: Họ tên chủ hộ
            $this->setCellValue($sh, 'G'.$r, $it['householder'] ?? '');

            // G: Quan hệ với chủ hộ
            $this->setCellValue($sh, 'H'.$r, $it['relation_to_householder'] ?? '');

            // H: Địa chỉ
            $this->setCellValue($sh, 'I'.$r, $it['address'] ?? '');

            // I: Thôn xóm
            $this->setCellValue($sh, 'J'.$r, $it['village_name'] ?? '');

            // J: Số phiếu
            $this->setCellValue($sh, 'K'.$r, $it['form_no'] ?? '');

            // K: Khối học (ví dụ: "Lớp 7")
            $this->setCellValue($sh, 'L'.$r, $it['grade_label'] ?? '');

            // L: Tên trường
            $this->setCellValue($sh, 'M'.$r, $it['school_name'] ?? '');

            // M: Ghi chú
            $this->setCellValue($sh, 'N'.$r, $it['note'] ?? '');

            $r++; $stt++;
        }

        // Kẻ khung & căn lề
        if ($r > $cfg['startRow']) {
            $this->drawThinBorders($sh, $cfg['startRow'], $r-1, 'A', $cfg['lastCol']);
        }

          // Footer ngày tháng
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $lastRow  = $sumRow;
        $this->safeMerge($sh, "H{$lastRow}:J{$lastRow}");
        $this->setCellValue($sh, 'H'.$lastRow, $this->formatService->end_date($wardName));
        $sh->getStyle("H{$lastRow}:J{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


        $sh->getStyle("A{$cfg['startRow']}:{$cfg['lastCol']}".($r-1))
           ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$cfg['startRow']}:A".($r-1))
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ẩn số 0 (để trống) cho toàn bảng dữ liệu
        $sh->getStyle("A{$cfg['startRow']}:{$cfg['lastCol']}".($r-1))
           ->getNumberFormat()->setFormatCode('0;-0;;@');

        // ===== 5) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lấy danh sách “đối tượng chuyển đến” theo năm học & địa bàn.
     * Nguồn: v_survey_detail_flat (ghép schools và survey_form_details).
     */
    protected function queryMovedIn(string $academic, string $scheme, array $geo): array
    {
        // Điều kiện “chuyển đến” (bao gồm các biến thể)
        $movedIn = "UPPER(TRIM(v.sc_name)) IN ('CHUYỂN ĐẾN','CHUYEN DEN','MOVED_IN')";

        $q = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', function($j) {
                $j->on('s.code', '=', 'v.school_code')
                  ->where(function($w) {
                      $w->whereNull('s.is_dissolved')
                        ->orWhere('s.is_dissolved', false)
                        ->orWhere('s.is_dissolved', 0);
                  });
            })
            
            ->selectRaw("
                v.person_id,
                v.dob_day, v.dob_month, v.dob_year,
                v.is_female,
                v.ethnicity,
                v.village_name,
                s.name AS school_name,
                v.education_block_code,
                v.so_phieu AS form_no,
                v.contact_note AS note,

                /* các cột có thể có trong view – nếu không có sẽ để trống ở PHP */
                v.chuho_ho_dem ,
                v.chuho_ten  ,
                v.relationship_name AS relation_to_householder,
                v.addressline ,
                v.village_name  ,
                v.ho_dem  ,v.ten
                 
            ")
            ->where('v.academic_year', $academic)
            ->whereRaw($movedIn);

        // Lọc địa bàn
        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null) || ($geo['district_id'] ?? null)) {
            if (($scheme ?? 'old') === 'old') {
                if (!empty($geo['province_id'])) $q->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['district_id'])) $q->where('v.old_district_id', $geo['district_id']);
                if (!empty($geo['ward_id']))     $q->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $q->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $q->where('v.new_ward_id',     $geo['ward_id']);
            }
        }

        $raw = $q ->orderBy('ten')->get();

        // Chuẩn hoá dữ liệu trả ra cho export
        $out = [];
        foreach ($raw as $r) {
            // Họ tên: ưu tiên các field có sẵn trong view; nếu không có ghép từ họ đệm + tên
            $name =    $r->ho_dem.' '.$r->ten;
            $name = trim($name);
            $householder_name = trim(($r->chuho_ho_dem ?? '').' '.($r->chuho_ten ?? ''));
            $address = trim($r->addressline ?? '').$r->village_name ? (' '.$r->village_name) : '';
            // Nhãn khối học
            $gradeLabel = '';
            if (!empty($r->education_block_code)) {
                // nếu education_block_code là số -> "Lớp x", nếu là chuỗi thì giữ nguyên
                $gradeLabel = is_numeric($r->education_block_code)
                    ? ('Lớp '.(int)$r->education_block_code)
                    : (string)$r->education_block_code;
            }

            $out[] = [
                'person_id'              => (int)$r->person_id,
                'full_name'              => $name,
                'dob_day'                => $r->dob_day,
                'dob_month'              => $r->dob_month,
                'dob_year'               => $r->dob_year,
                'is_female'              => (int)($r->is_female ?? 0) === 1,
                'ethnicity'              => $r->ethnicity ?? '',
                'householder'            => $householder_name ?? '',
                'relation_to_householder'=> $r->relation_to_householder ?? '',
                'address'                => $address ?? '',
                'village_name'           => $r->village_name ?? '',
                'form_no'                => $r->form_no ?? '',
                'grade_label'            => $gradeLabel,
                'school_name'            => $r->school_name ?? '',
                'note'                   => $r->note ?? '',
            ];
        }
        // dd($out);
        return $out;
    }
}