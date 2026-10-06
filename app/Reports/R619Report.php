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
class R619Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

 public function export(Request $req)
    {
        // ===== 1) Input
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear()); // vd 2024-2025
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string)$req->input('scheme', 'old'); // old/new
        $geo      = (array)$req->input('geo', []);        // province_id, district_id, ward_id

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template (chỉnh lại địa chỉ ô cho đúng file của bạn)
        $cfg = [
            'path'        => resource_path('excel/6.19.report.xlsx'),
            'header'      => [
                'province' => 'C1',
                'ward' => 'C2',
                
            ],
            'titleCell'   => 'G5', // “DANH SÁCH HỌC SINH LƯU BAN”
            'subTitleCell'=> 'G6', // “Năm 2023-2024 (Tính dữ liệu 2024-2025)”
            'startRow'    => 7,    // dòng đầu dữ liệu theo ảnh
            'lastCol'     => 'K',  // A..K (K = Ghi chú)
         
        ];

        $wb = IOFactory::load($cfg['path']);
        /** @var Worksheet $sh */
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        
        $sh->setCellValue($cfg['header']['ward'], $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['titleCell'],    'DANH SÁCH HỌC SINH LƯU BAN');

        $lastAcademic = ($y1 - 1) . '-' . $y1;
        $sh->setCellValue($cfg['subTitleCell'], "Năm {$lastAcademic} (Tính dữ liệu {$academic})");

        // ===== 3) Data
        $rows = $this->queryRepeaters($academic, $scheme, $geo);

        // Chèn thêm dòng nếu cần
        $r0   = $cfg['startRow'];
        $need = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($r0 + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $r0, $r0 + $i, $cfg['lastCol']);
            }
        }

        // ===== 4) Fill
        $r = $r0; $stt = 1;
        foreach ($rows as $it) {
            $this->setCellValue($sh, "A{$r}", $stt);                 // STT
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name']);
            $this->setCellValue($sh, "D{$r}", $it['dob_text']);      // Ngày sinh dd/mm/yyyy
            $this->setCellValue($sh, "E{$r}", $it['is_female'] ? 'X' : ''); // Nữ (X)
            $this->setCellValue($sh, "F{$r}", $it['ethnicity']);     // Dân tộc
            $this->setCellValue($sh, "G{$r}", $it['so_phieu']);      // Số phiếu
            $this->setCellValue($sh, "H{$r}", $it['block']);         // Khối học (1..12)
            // Lớp: thêm * để đánh dấu lưu ban, đúng như ảnh
            $this->setCellValue($sh, "I{$r}", $it['class_label'].'*');
            $this->setCellValue($sh, "J{$r}", $it['school_name']);   // Tên trường
                          // (cột trống nằm trong nhóm “Đang học”)
            $this->setCellValue($sh, "K{$r}", $it['note'] ?? '');    // Ghi chú (nếu có)
            $r++; $stt++;
        }

        // ===== 5) Format
        $lastRow = max($r0, $r - 1);
        $this->drawThinBorders($sh, $r0, $lastRow, 'A', $cfg['lastCol']);

        // Căn lề
        $sh->getStyle("A{$r0}:{$cfg['lastCol']}{$lastRow}")
           ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$r0}:A{$lastRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("C{$r0}:D{$lastRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("F{$r0}:G{$lastRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("H{$r0}:H{$lastRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Không để viền “lan” xuống footer ký tên
    

        // Footer ngày tháng
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        // ví dụ ô “Cư Huê, ngày ...” ở I29–K29: thay bằng range thật của template bạn
        $footerRow = $lastRow + 1;
        $this->safeMerge($sh, "H{$footerRow}:J{$footerRow}");
        $this->setCellValue($sh, "H{$footerRow}", $this->formatService->end_date($wardName));
        $sh->getStyle("H{$footerRow}:J{$footerRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ===== 6) Output
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * HS lưu ban = có mặt ở cả 2 năm (năm trước & năm hiện tại) với cùng khối (education_block_code).
     * Lấy thông tin hiện tại để hiển thị (số phiếu, lớp, trường...).
     */
    protected function queryRepeaters(string $academic, string $scheme, array $geo): array
    {
        // Cột địa bàn theo scheme
        $colProvince = $scheme === 'old' ? 'v.old_province_id' : 'v.new_province_id';
        $colWard     = $scheme === 'old' ? 'v.old_ward_id'     : 'v.new_ward_id';

        // HS có cờ lưu ban ở person_educations của NĂM HIỆN TẠI
        $q = DB::table('person_educations as pe')
            ->leftJoin('v_survey_detail_flat as v', 'v.person_id', '=', 'pe.person_id')
            ->where('v.academic_year', $academic)
            ->where('pe.academic_year', $academic)
            ->where('pe.is_repeat_grade', 1);
            // chỉ xét các khối hợp lệ 1..12
            // ->whereRaw('CAST(v.education_block_code AS UNSIGNED) BETWEEN 1 AND 12');

        // Lọc địa bàn
        if (!empty($geo['province_id'])) $q->where($colProvince, $geo['province_id']);
        if (!empty($geo['ward_id']))     $q->where($colWard,     $geo['ward_id']);

        // Sắp xếp: họ đệm → tên
        $q->orderBy('v.ho_dem')->orderBy('v.ten');

        // Lấy dữ liệu để đổ Excel
        $rows = $q->get([
            'v.person_id',
            'v.ho_dem','v.ten','v.is_female',
            'v.dob_day','v.dob_month','v.dob_year',
            'v.ethnicity','v.so_phieu',
            'v.education_block_code','v.education_grade_name','v.school_name',
        ]);

        // Map ra mảng kết quả
        $out = [];
        foreach ($rows as $r) {
            $full = trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? ''));
            // dd/mm/yyyy (thiếu phần nào thì rút gọn)
            $d=(int)($r->dob_day??0); $m=(int)($r->dob_month??0); $y=(int)($r->dob_year??0);
            $dob = ($d && $m && $y) ? sprintf('%02d/%02d/%04d',$d,$m,$y)
                : (($m && $y) ? sprintf('%02d/%04d',$m,$y) : ($y ? (string)$y : ''));

            $out[] = [
                'full_name'   => $full,
                'dob_text'    => $dob,
                'is_female'   => (int)$r->is_female === 1,
                'ethnicity'   => $r->ethnicity ?? '',
                'so_phieu'    => $r->so_phieu ?? '',
                'block'       => (string)(int)($r->education_block_code ?? 0), // Khối
                'class_label' => $r->education_grade_name ?? '',               // Lớp
                'school_name' => $r->school_name ?? '',
                'note'        => '',
            ];
        }
        return $out;
    }
}