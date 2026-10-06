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

class R606Report extends BaseReport
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
            'path'      => resource_path('excel/6.6.DS.DoiTuongChet.xlsx'),
            'header'    => [
                'province' => 'C1',  // ô ghi Tỉnh/Thành
                'district' => 'C2',  // ô ghi Phường/Xã
                'yearline' => 'D3',  // ô ghi dòng "Năm ...." (tuỳ template, có thể bỏ nếu không dùng)
            ],
            'startRow'  => 7,       // dòng đầu ghi dữ liệu ngay dưới tiêu đề cột
            'lastCol'   => 'K',     // cột "Ghi chú"
        ];
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['district'], $this->geoService->geoNameFromId($geo['ward_id'] ?? $geo['district_id'] ?? null));
        if (!empty($cfg['header']['yearline'])) {
            $sh->setCellValue($cfg['header']['yearline'], 'Năm '.$academic);
        }

        // Format header cells - Tahoma size 8, bold
        $sh->getStyle($cfg['header']['province'])->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle($cfg['header']['district'])->getFont()->setName('Tahoma')->setSize(8)->setBold(true);

        $startRow = $cfg['startRow'];
        $sumRow = $startRow + 2;
        
        // ===== 3) Dữ liệu
        $rows = $this->queryDeceased($academic, $scheme, $geo);
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($sumRow, $need);
            for ($i=1; $i<=$need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
            $sumRow += $need;   // rất quan trọng: tránh "thêm 1 dòng rỗng sau Tổng cộng"
        }

        // ===== 4) Ghi dữ liệu
        $r = $cfg['startRow']; $stt = 1;
        foreach ($rows as $it) {
            // A: STT
            $this->setCellValue($sh, 'A'.$r, $stt);

            // B-C: Họ và tên (merge)
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name']);
             
            // D: Ngày sinh (dd/mm/yyyy)
            $d = (int)($it['dob_day']   ?? 0);
            $m = (int)($it['dob_month'] ?? 0);
            $y = (int)($it['dob_year']  ?? 0);
            $dob = ($d && $m && $y) ? sprintf('%02d/%02d/%04d', $d, $m, $y)
                 : ($m && $y ? sprintf('%02d/%04d', $m, $y)
                 : ($y ? (string)$y : ''));

            $this->setCellValue($sh, 'D'.$r, $dob);

            // E: Nữ (hiển thị x nếu là nữ, để trống nếu không)
            $this->setCellValue($sh, 'E'.$r, !empty($it['is_female']) ? 'x' : '');

            // F: Dân tộc
            $this->setCellValue($sh, 'F'.$r, $it['ethnicity'] ?? '');

            // G: Số phiếu
            $this->setCellValue($sh, 'G'.$r, $it['form_no'] ?? '');

            // H: Họ tên chủ hộ
            $this->setCellValue($sh, 'H'.$r, $it['householder'] ?? '');

            // I: Quan hệ với chủ hộ
            $this->setCellValue($sh, 'I'.$r, $it['relation_to_householder'] ?? '');

            // J: Địa chỉ
            $this->setCellValue($sh, 'J'.$r, $it['address'] ?? '');

            // K: Ghi chú
            $this->setCellValue($sh, 'K'.$r, $it['note'] ?? '');

            $r++; $stt++;
        }

        // Kẻ khung & căn lề
        if ($r > $cfg['startRow']) {
            $this->drawThinBorders($sh, $cfg['startRow'], $r-1, 'A', $cfg['lastCol']);
        }

        // Thêm phần ký tên
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $lastRow  = $sumRow;
        $this->addSignatureSection($sh, $wardName, $lastRow);

        // Format toàn bộ dữ liệu - Tahoma size 8
        $sh->getStyle("A{$cfg['startRow']}:{$cfg['lastCol']}".($r-1))
           ->getFont()->setName('Tahoma')->setSize(8);

        $sh->getStyle("A{$cfg['startRow']}:{$cfg['lastCol']}".($r-1))
           ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$cfg['startRow']}:A".($r-1))
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Căn giữa cho các cột: Ngày sinh (D), Nữ (E), Dân tộc (F), Số phiếu (G)
        if ($r > $cfg['startRow']) {
            $sh->getStyle("D{$cfg['startRow']}:D".($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("E{$cfg['startRow']}:E".($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("F{$cfg['startRow']}:F".($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("G{$cfg['startRow']}:G".($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

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
     * Lấy danh sách "đối tượng đã chết" theo năm học & địa bàn.
     * Nguồn: v_survey_detail_flat (ghép schools và survey_form_details).
     */
    protected function queryDeceased(string $academic, string $scheme, array $geo): array
    {
        // Điều kiện "đã chết" (bao gồm các biến thể)
        $deceased = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','CHET','DEAD','DECEASED')";

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
            ->whereRaw($deceased);

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
                'note'                   => $r->note ?? '',
            ];
        }
        // dd($out);
        return $out;
    }

    /**
     * Thêm phần ký tên vào cuối báo cáo
     */
    private function addSignatureSection(Worksheet $sh, string $wardName, int $lastRow): void
    {
        // Tính toán vị trí dòng ký tên (bắt đầu từ dòng dữ liệu cuối + 1)
        $signatureStartRow = $lastRow + 1;
        
        // Tên xã, ngày ... tháng ... năm ... vào ô J (trên TM. BAN CHỈ ĐẠO)
        $dateRow = $signatureStartRow;
        $sh->setCellValue('J' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        $sh->getStyle('J' . $dateRow)->getFont()->setName('Tahoma')->setSize(8);
        $sh->getStyle('J' . $dateRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // 'NGƯỜI LẬP BIỂU' vào ô D, in đậm
        $signatureRow1 = $signatureStartRow + 1;
        $sh->setCellValue('D' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $sh->getStyle('D' . $signatureRow1)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('D' . $signatureRow1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô J, in đậm
        $sh->setCellValue('J' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $sh->getStyle('J' . $signatureRow1)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('J' . $signatureRow1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // '(Ký và ghi rõ họ tên)' vào ô D, in nghiêng
        $signatureRow2 = $signatureStartRow + 2;
        $sh->setCellValue('D' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $sh->getStyle('D' . $signatureRow2)->getFont()->setName('Tahoma')->setSize(8)->setItalic(true);
        $sh->getStyle('D' . $signatureRow2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // 'TRƯỞNG BAN' vào ô J, in đậm
        $sh->setCellValue('J' . $signatureRow2, 'TRƯỞNG BAN');
        $sh->getStyle('J' . $signatureRow2)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('J' . $signatureRow2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // '(Ký tên, đóng dấu)' vào ô J, in nghiêng
        $signatureRow3 = $signatureStartRow + 3;
        $sh->setCellValue('J' . $signatureRow3, '(Ký tên, đóng dấu)');
        $sh->getStyle('J' . $signatureRow3)->getFont()->setName('Tahoma')->setSize(8)->setItalic(true);
        $sh->getStyle('J' . $signatureRow3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
    }
}
