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
class R613Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
public function export(Request $req)
    {
        // ===== 1) Input
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear()); // ví dụ 2024-2025
        $scheme   = (string)$req->input('scheme', 'old'); // old/new
        $geo      = (array)$req->input('geo', []);

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template — CHỈNH PATH THEO FILE CỦA BẠN
        $cfg = [
            'path'      => resource_path('excel/6.13.1.report.xlsx'),
            'header'    => [
                'province' => 'C1',  // “Tỉnh/Thành”
                'ward'     => 'C2',  // “Phường/Xã”
                'year'     => 'C3',  // “Năm học”
            ],
            'titleCell' => 'I4',      // nếu cần ghi lại tên báo cáo
            'startRow'  => 10,        // dòng đầu dữ liệu (như ảnh)
            'lastCol'   => 'P',       // A..P (Số phiếu)
        ];

        $wb = IOFactory::load($cfg['path']);
        /** @var Worksheet $sh */
        $sh = $wb->getActiveSheet();

        // Header (điền tỉnh/xã/năm học + ngày tháng phần footer)
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['header']['year'],     $academic);

        // ===== 3) Dữ liệu
        $rows = $this->queryGraduates5Years($academic, $scheme, $geo);

        // Chèn thêm dòng nếu cần
        $r0   = $cfg['startRow'];
        $need = max(0, count($rows) - 1);
        if ($need > 0) {
            // chèn ngay trên dòng “mẫu” đầu tiên
            $sh->insertNewRowBefore($r0 + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $r0, $r0 + $i, $cfg['lastCol']);
            }
        }

        // ===== 4) Ghi dữ liệu
        $r   = $r0;
        $stt = 1;
        foreach ($rows as $it) {
            // A: STT
            $this->setCellValue($sh, "A{$r}", $stt);

            // B: Họ và tên
      
               // B: Họ và tên
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name']);
             

            // C: Ngày tháng năm sinh (dd/mm/yyyy)
            $this->setCellValue($sh, "D{$r}", $it['dob_text']);

            // D: Nữ (đánh X)
            $this->setCellValue($sh, "E{$r}", $it['is_female'] ? 'X' : '');

            // E: Dân tộc
            $this->setCellValue($sh, "F{$r}", $it['ethnicity']);

            // F: Tôn giáo
            $this->setCellValue($sh, "G{$r}", $it['religion']);

            // G: Địa chỉ (ưu tiên addressline; thiếu thì ghép tên thôn/xã)
            $this->setCellValue($sh, "H{$r}", $it['address']);

            // H: Họ và tên chủ hộ (Cha mẹ)
            $this->setCellValue($sh, "I{$r}", $it['chuho']);

            // I: Bậc TN (THCS/THPT/…)
            $this->setCellValue($sh, "J{$r}", $it['grad_level']);

            // J: Bổ túc (X nếu is_bo_tuc = 1)
            $this->setCellValue($sh, "K{$r}", $it['is_bo_tuc'] ? 'X' : '');

            // K: Năm TN (dạng 2020-2021)
            $this->setCellValue($sh, "L{$r}", $it['grad_year']);

            // L: Tên trường đang học (nếu còn học → từ school_name hiện tại; nếu đã TN thì để trống)
            $this->setCellValue($sh, "M{$r}", $it['school_name']);

            // M: Lớp (label hiện tại)
            $this->setCellValue($sh, "N{$r}", $it['class_label']);

            // N: Diện cư trú (status/type; ưu tiên status_name)
            $this->setCellValue($sh, "O{$r}", $it['residence_status'] ?: $it['residence_type']);

            // O: Số phiếu
            $this->setCellValue($sh, "P{$r}", $it['so_phieu']);

            $r++; $stt++;
        }

        // Căn lề & định dạng cơ bản
        $lastRow = max($r0, $r - 1);
        $this->drawThinBorders($sh, $r0, $lastRow, 'A', $cfg['lastCol']);
        $sh->getStyle("A{$r0}:{$cfg['lastCol']}{$lastRow}")
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$r0}:A{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("C{$r0}:D{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("I{$r0}:K{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("N{$r0}:O{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("A".($lastRow+1).":P".($lastRow+5)) // xoá khung footer ngày tháng
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
        // Footer ngày tháng (nếu template có ô ký)
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $footerRow = $lastRow + 2;
        $this->safeMerge($sh, "M{$footerRow}:O{$footerRow}");
        $this->setCellValue($sh, "M{$footerRow}", $this->formatService->end_date($wardName));
        $sh->getStyle("M{$footerRow}:O{$footerRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
     * Lấy danh sách người có TN (THCS/THPT/…) trong 5 năm học gần nhất tính từ $academic,
     * lọc theo địa bàn (old/new) và trả về dữ liệu để đổ vào Excel.
     */
    protected function queryGraduates5Years(string $academic, string $scheme, array $geo): array
    {
        // Tạo 5 năm học lùi lại: ví dụ 2024-2025 -> [2020-2021..2024-2025]
        [$y1] = array_map('intval', explode('-', $academic));
        $years = [];
        for ($k = 1; $k >= 0; $k--) {
            $a = ($y1 - $k - 1) . '-' . ($y1 - $k);
            $years[] = $a;
        }

        // Cột địa bàn theo scheme
        $colProvince = $scheme === 'old' ? 'v.old_province_id' : 'v.new_province_id';
        $colWard     = $scheme === 'old' ? 'v.old_ward_id'     : 'v.new_ward_id';

        $q = DB::table('v_survey_detail_flat as v')
            ->whereIn('v.graduation_year', $years)
            ->whereNotNull('v.graduation_level_name');

        // Lọc địa bàn
        if (!empty($geo['province_id'])) $q->where($colProvince, $geo['province_id']);
        if (!empty($geo['ward_id']))     $q->where($colWard,     $geo['ward_id']);

        // Có thể loại bỏ người “chết/chuyển đi” nếu cần:
        // $q->whereNotIn(DB::raw('UPPER(TRIM(COALESCE(v.sc_name,"")))'), ['CHẾT','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT']);

        // Sắp xếp: theo tên xã → tên người
        $q->orderBy('v.village_name')->orderBy('v.ho_dem')->orderBy('v.ten');

        $rows = $q->get([
            'v.ho_dem','v.ten','v.dob_day','v.dob_month','v.dob_year','v.is_female',
            'v.ethnicity','v.religion',
            'v.addressline','v.village_name','v.old_ward_name','v.new_ward_name',
            'v.chuho_ho_dem','v.chuho_ten',
            'v.graduation_level_name','v.is_bo_tuc','v.graduation_year',
            'v.school_name','v.education_grade_name',
            'v.residence_status_name','v.residence_type_name',
            'v.so_phieu',
        ]);

        $out = [];
        foreach ($rows as $r) {
            // Họ tên
            $full = trim(($r->ho_dem ? $r->ho_dem.' ' : '') . ($r->ten ?? ''));

            // Ngày sinh dd/mm/yyyy (để trống phần nào không có)
            $d = (int)($r->dob_day   ?? 0);
            $m = (int)($r->dob_month ?? 0);
            $y = (int)($r->dob_year  ?? 0);
            $dob = ($d && $m && $y) ? sprintf('%02d/%02d/%04d', $d, $m, $y)
                 : ($m && $y ? sprintf('%02d/%04d', $m, $y)
                 : ($y ? (string)$y : ''));

            // Địa chỉ: ưu tiên addressline; thiếu thì lấy “<thôn/xóm> - <xã>”
            $xaName = $r->old_ward_name ?: $r->new_ward_name;
            $addr = $r->addressline ?: trim(($r->village_name ? $r->village_name.' - ' : '') . ($xaName ?? ''));

            $out[] = [
                'full_name'        => $full,
                'dob_text'         => $dob,
                'is_female'        => (int)$r->is_female === 1,
                'ethnicity'        => $r->ethnicity ?? '',
                'religion'         => $r->religion  ?? '',
                'address'          => $addr,
                'chuho'            => trim(($r->chuho_ho_dem ? $r->chuho_ho_dem.' ' : '') . ($r->chuho_ten ?? '')),
                'grad_level'       => $r->graduation_level_name ?? '',
                'is_bo_tuc'        => (int)($r->is_bo_tuc ?? 0) === 1,
                'grad_year'        => $r->graduation_year ?? '',
                'school_name'      => $r->school_name ?? '',
                'class_label'      => $r->education_grade_name ?? '',
                'residence_status' => $r->residence_status_name ?? '',
                'residence_type'   => $r->residence_type_name  ?? '',
                'so_phieu'         => $r->so_phieu ?? '',
            ];
        }
        return $out;
    }
}