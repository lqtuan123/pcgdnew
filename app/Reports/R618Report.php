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
class R618Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}


    protected function queryThcsGradLastYear_NowThpt10(string $academic, string $scheme, array $geo): array
    {
        // year strings: "2024-2025" -> last "2023-2024"
        [$y1] = array_map('intval', explode('-', $academic));
        $lastAcademic = ($y1 - 1) . '-' . $y1;

        // Cột địa bàn theo scheme
        $colProvince = $scheme === 'old' ? 'v.old_province_id' : 'v.new_province_id';
        $colWard     = $scheme === 'old' ? 'v.old_ward_id'     : 'v.new_ward_id';

      
        // Hiện tại: đang học KHỐI/LỚP 10 (THPT) trong NĂM NAY
        $q = DB::table('v_survey_detail_flat as v')
            ->where('v.graduation_year',    $lastAcademic)
            ->where('v.academic_year', $academic)
            ->whereRaw("UPPER(v.graduation_level_name) = 'THCS'")
            ->whereRaw('CAST(v.education_block_code AS UNSIGNED) = 10');

        // Lọc địa bàn (nếu có)
        if (!empty($geo['province_id'])) $q->where($colProvince, $geo['province_id']);
        if (!empty($geo['ward_id']))     $q->where($colWard,     $geo['ward_id']);

        // Sắp xếp
        $q->orderBy('v.ho_dem')->orderBy('v.ten');

        // Lấy fields phục vụ đổ Excel theo mẫu ảnh
        $rows = $q->get([
            'v.ho_dem','v.ten','v.is_female',
            'v.dob_day','v.dob_month','v.dob_year',
            'v.ethnicity',
            'v.addressline','v.village_name','v.old_ward_name','v.new_ward_name',
            'v.school_name','v.education_grade_name',
            'v.chuho_ho_dem','v.chuho_ten', 'v.is_bo_tuc',
            'v.so_phieu','v.graduation_year'
           
        ]);

        // Map ra mảng kết quả gọn cho báo cáo
        $out = [];
        foreach ($rows as $r) {
            $full = trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? ''));
            // Ngày sinh dd/mm/yyyy (rút gọn nếu thiếu)
            $d=(int)($r->dob_day??0); $m=(int)($r->dob_month??0); $y=(int)($r->dob_year??0);
            $dob = ($d && $m && $y) ? sprintf('%02d/%02d/%04d',$d,$m,$y)
                : (($m && $y) ? sprintf('%02d/%04d',$m,$y) : ($y ? (string)$y : ''));

            // Địa chỉ: ưu tiên addressline; thiếu thì "thôn/xóm - xã"
            $xa = $r->old_ward_name ?: $r->new_ward_name;
            $addr = $r->addressline ?: trim(($r->village_name ? $r->village_name.' - ' : '').($xa ?? ''));

            $out[] = [
                'full_name'   => $full,                      // B
                'dob_text'    => $dob,                       // C
                'is_female'   => (int)$r->is_female === 1,   // D -> 'X' khi ghi Excel
                'ethnicity'   => $r->ethnicity ?? '',        // E
                'address'     => $addr,                      // F
                'grad_level'  => $r->grad_level ?? 'THCS',   // G (Bậc TN)
                'is_bo_tuc'   => (int)$r->is_bo_tuc === 1,   // H (Bổ túc) -> 'X' nếu true
                'grad_year'   => $r->graduation_year ?? '',  // I (Năm TN)
                'school_name' => $r->school_name ?? '',      // K (Tên trường đang học)
                'class_label' => $r->education_grade_name ?? '', // L (Lớp)
                'chuho'       => trim(($r->chuho_ho_dem ? $r->chuho_ho_dem.' ' : '').($r->chuho_ten ?? '')), // M
                'so_phieu'    => $r->so_phieu ?? '',         // N/O tuỳ mẫu
            ];
        }
        return $out;
    }
    public function export(Request $req)
    {   
        // ===== 1) Input
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear()); // vd: 2024-2025
        $scheme   = (string)$req->input('scheme', 'old');            // old/new
        $geo      = (array)$req->input('geo', []);                   // province_id, ward_id...

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template (chỉnh lại path/ô nếu file của bạn khác)
        $cfg = [
            'path'      => resource_path('excel/6.18.report.xlsx'),
            'header'    => ['province' => 'C1', 'ward' => 'C2', 'year' => 'C3'],
            'startRow'  => 10,      // dòng đầu dữ liệu theo mẫu ảnh
            'lastCol'   => 'N',     // A..N (N = Số phiếu)
            
        ];

        $wb = IOFactory::load($cfg['path']);
        /** @var Worksheet $sh */
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['header']['year'],     $academic);

        // ===== 3) Dữ liệu
        $rows = $this->queryThcsGradLastYear_NowThpt10($academic, $scheme, $geo);

        // Chèn thêm dòng nếu cần (nhân bản định dạng của dòng mẫu đầu)
        $r0   = $cfg['startRow'];
        $need = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($r0 + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $r0, $r0 + $i, $cfg['lastCol']);
            }
        }

        // ===== 4) Ghi dữ liệu vào cột A..N theo bố cục ảnh
        $r = $r0; $stt = 1;
        foreach ($rows as $it) {
            // A: STT
            $this->setCellValue($sh, "A{$r}", $stt);

            // B: Họ và tên
              $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name']);
            

            // C: Ngày sinh (dd/mm/yyyy hoặc rút gọn)
            $this->setCellValue($sh, "D{$r}", $it['dob_text']);

            // D: Nữ (X)
            $this->setCellValue($sh, "E{$r}", $it['is_female'] ? 'X' : '');

            // E: Dân tộc
            $this->setCellValue($sh, "F{$r}", $it['ethnicity']);

            // F: Địa chỉ
            $this->setCellValue($sh, "G{$r}", $it['address']);

            // G: Bậc TN (THCS)
            $this->setCellValue($sh, "H{$r}", $it['grad_level']);

            // H: Bổ túc (X nếu có)
            $this->setCellValue($sh, "I{$r}", $it['is_bo_tuc'] ? 'X' : '');

            // I: Năm TN (vd 2023-2024)
            $this->setCellValue($sh, "J{$r}", $it['grad_year']);

             

            // K: Tên trường đang học (năm nay)
            $this->setCellValue($sh, "K{$r}", $it['school_name']);

            // L: Lớp (năm nay)
            $this->setCellValue($sh, "L{$r}", $it['class_label']);

            // M: Họ và tên chủ hộ
            $this->setCellValue($sh, "M{$r}", $it['chuho']);

            // N: Số phiếu
            $this->setCellValue($sh, "N{$r}", $it['so_phieu']);

            $r++; $stt++;
        }

        // ===== 5) Định dạng: kẻ viền bảng + căn lề
        $lastRow = max($r0, $r - 1);
        $this->drawThinBorders($sh, $r0, $lastRow, 'A', $cfg['lastCol']);

        $sh->getStyle("A{$r0}:{$cfg['lastCol']}{$lastRow}")
        ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$r0}:A{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Căn giữa các cột số/liệt kê
        $sh->getStyle("C{$r0}:D{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("G{$r0}:I{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("L{$r0}:L{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("N{$r0}:N{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Footer ngày tháng
        $footerRow = $lastRow + 2;
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $this->safeMerge($sh, "L{$footerRow}:N{$footerRow}");
        $this->setCellValue($sh, "L{$footerRow}", $this->formatService->end_date($wardName));
        $sh->getStyle("L{$footerRow}:N{$footerRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ===== 6) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

}