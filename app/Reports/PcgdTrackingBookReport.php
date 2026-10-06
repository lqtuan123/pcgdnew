<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

use App\Services\GeoService;
use App\Services\ReportFormatService;

class PcgdTrackingBookReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* ========================= Template mapping ========================= */
    private function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/SoTheoDoiPCGD.xlsx'),
            'header' => [
                'province' => 'C1',  // Tỉnh/Thành - In đậm, Tahoma 8
                'ward'     => 'C2',  // Phường/Xã - In đậm, Tahoma 8
            ],
            'startRow'  => 7,         // Hàng đầu của dữ liệu (ngay dưới tiêu đề cột)
            'lastCol'   => 'M',       // Cột cuối cùng (Ghi chú)
            // Cột xuất dữ liệu
            'col' => [
                'stt'           => 'A',
                'name'          => 'B',  // Merge B:C
                'dob'           => 'D',  // Ngày, tháng, năm sinh
                'gender'        => 'E',  // Giới tính
                'ethnicity'     => 'F',  // Dân tộc
                'parent_name'   => 'G',  // Họ tên cha (hoặc mẹ hoặc người đỡ đầu) - KHÔNG merge
                'residence'     => 'H',  // Chổ ở hiện nay - KHÔNG merge
                'gdmn'          => 'I',  // Hoàn thành chương trình GDMN
                'th'            => 'J',  // Hoàn thành chương trình TH
                'thcs'          => 'K',  // Hoàn thành chương trình THCS
                'hoan_canh'     => 'L',  // Hoàn cảnh đặc biệt
                'ghi_chu'       => 'M',  // Ghi chú
            ],
        ];
    }

    /* =========================== Export main =========================== */
    public function export(Request $req)
    {
        // Tăng timeout lên 360s cho báo cáo lớn
        set_time_limit(360);
        ini_set('max_execution_time', 360);
        
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);

        // Lấy tất cả người trong hệ thống (không giới hạn độ tuổi)
        $rows = $this->queryList($academic, $scheme, $geo);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header - Format Tahoma 8, in đậm
        $provinceName = $this->geoService->geoNameFromId($geo['province_id'] ?? null);
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null);
        
        $this->setCellValue($sh, $cfg['header']['province'], $provinceName);
        $this->setCellValue($sh, $cfg['header']['ward'], $wardName);
        
        // Format header: Tahoma 8, in đậm
        $headerStyle = $sh->getStyle($cfg['header']['province']);
        $headerStyle->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        
        $headerStyle2 = $sh->getStyle($cfg['header']['ward']);
        $headerStyle2->getFont()->setName('Tahoma')->setSize(8)->setBold(true);

        // Chuẩn bị dữ liệu dạng mảng 2D để ghi nhanh bằng fromArray()
        $startRow = $cfg['startRow'];
        $data = [];
        $stt = 1;
        
        foreach ($rows as $row) {
            $data[] = [
                $stt++,                                    // A - STT
                $row['full_name'],                         // B - Họ và tên
                null,                                       // C - (trống - merge với B)
                $row['dob_fmt'],                           // D - Ngày, tháng, năm sinh
                $row['gender'],                            // E - Giới tính
                $row['ethnicity'] ?: '',                   // F - Dân tộc
                $row['parent_name'] ?: '',                 // G - Họ tên cha/mẹ/người đỡ đầu
                $row['residence'] ?: '',                   // H - Chỗ ở hiện nay
                ($row['thcs'] ? '' : ($row['th'] ? '' : ($row['gdmn'] ? 'X' : ''))),  // I - Hoàn thành GDMN (chỉ nếu không có TH/THCS)
                ($row['thcs'] ? '' : ($row['th'] ? 'X' : '')),                        // J - Hoàn thành TH (chỉ nếu không có THCS)
                ($row['thcs'] ? 'X' : ''),                                            // K - Hoàn thành THCS (ưu tiên cao nhất)
                $row['hoan_canh'] ?: '',                  // L - Hoàn cảnh đặc biệt
                $row['ghi_chu'] ?: '',                     // M - Ghi chú
            ];
        }

        // Ghi dữ liệu một lần bằng fromArray() - nhanh hơn nhiều so với setCellValue từng ô
        $lastDataRow = $startRow - 1; // Mặc định nếu không có dữ liệu
        
        if (count($data) > 0) {
            $sh->fromArray($data, null, 'A'.$startRow);
            
            $endRow = $startRow + count($data) - 1;
            $lastDataRow = $endRow; // Dòng dữ liệu cuối cùng
            
            // Merge cột B:C cho tất cả các dòng một lần (thay vì merge từng dòng)
            for ($r = $startRow; $r <= $endRow; $r++) {
                $sh->mergeCells("B{$r}:C{$r}");
            }
            
            // Clone style từ dòng mẫu (nếu có)
            if ($endRow > $startRow) {
                $src = "A{$startRow}:{$cfg['lastCol']}{$startRow}";
                $dst = "A{$startRow}:{$cfg['lastCol']}{$endRow}";
                $sh->duplicateStyle($sh->getStyle($src), $dst);
            }
            
            // Format toàn bộ dữ liệu: Tahoma 8, không in đậm
            $dataRange = 'A'.$startRow.':'.$cfg['lastCol'].$endRow;
            $sh->getStyle($dataRange)->getFont()
                ->setName('Tahoma')
                ->setSize(8)
                ->setBold(false);
            
            // Căn giữa cho các cột: STT, Ngày sinh, Giới tính, 3 cột hoàn thành
            $sh->getStyle('A'.$startRow.':A'.$endRow)
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['dob'].$startRow.':'.$cfg['col']['dob'].$endRow)
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['gender'].$startRow.':'.$cfg['col']['gender'].$endRow)
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['gdmn'].$startRow.':'.$cfg['col']['thcs'].$endRow)
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Thêm border cho các dòng dữ liệu
            $this->drawThinBorders($sh, $startRow, $endRow, 'A', $cfg['lastCol']);
        }
        
        // Thêm phần ký tên
        $signatureRow = $lastDataRow + 2; // Dòng X (cách dòng dữ liệu 1 dòng)
        
        // Dòng X: NGƯỜI LẬP BIỂU và XÁC NHẬN
        $sh->setCellValue('E'.$signatureRow, 'NGƯỜI LẬP BIỂU');
        $sh->getStyle('E'.$signatureRow)->getFont()
            ->setName('Tahoma')
            ->setSize(8)
            ->setBold(true);
        $sh->getStyle('E'.$signatureRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sh->setCellValue('J'.$signatureRow, 'XÁC NHẬN CỦA ỦY BAN NHÂN DÂN XÃ/PHƯỜNG/ĐẶC KHU');
        $sh->getStyle('J'.$signatureRow)->getFont()
            ->setName('Tahoma')
            ->setSize(8)
            ->setBold(true);
        $sh->getStyle('J'.$signatureRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Dòng X+1: (Ký, ghi rõ họ tên, đóng dấu) và (Ký tên, đóng dấu)
        $sh->setCellValue('E'.($signatureRow + 1), '(Ký, ghi rõ họ tên, đóng dấu)');
        $sh->getStyle('E'.($signatureRow + 1))->getFont()
            ->setName('Tahoma')
            ->setSize(8)
            ->setItalic(true)
            ->setBold(false); // Không in đậm
        $sh->getStyle('E'.($signatureRow + 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sh->setCellValue('J'.($signatureRow + 1), '(Ký tên, đóng dấu)');
        $sh->getStyle('J'.($signatureRow + 1))->getFont()
            ->setName('Tahoma')
            ->setSize(8)
            ->setItalic(true);
        $sh->getStyle('J'.($signatureRow + 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Dòng X+2: Ngày …tháng …năm …
        $sh->setCellValue('J'.($signatureRow + 2), 'Ngày …tháng …năm …');
        $sh->getStyle('J'.($signatureRow + 2))->getFont()
            ->setName('Tahoma')
            ->setSize(8)
            ->setItalic(true);
        $sh->getStyle('J'.($signatureRow + 2))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
    private function queryList(string $academic, string $scheme, array $geo): array
    {
        // Sử dụng v_survey_detail_flat để lấy graduation_level_name dễ dàng
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('person_mamnon as pm', 'pm.person_id', '=', 'v.person_id')
            ->leftJoin('people as p', 'p.id', '=', 'v.person_id')
            ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
            ->leftJoin('priority_categories as pc', 'pc.id', '=', 'p.priority_category_id')
            ->leftJoin('household_members as hm', 'hm.person_id', '=', 'p.id')
            ->leftJoin('households as h', 'h.id', '=', 'hm.household_id')
            ->leftJoin('people as hp', 'hp.id', '=', 'h.head_person_id')
            ->leftJoin('geo_units as gv', 'gv.id', '=', 'h.village_id')
            ->where('v.academic_year', $academic);

        // Áp địa bàn (scheme/new/old)
        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) {
                $qb->where('v.old_province_id', (int)$geo['province_id']);
            }
            if (!empty($geo['district_id'])) {
                $qb->where('v.old_district_id', (int)$geo['district_id']);
            }
            if (!empty($geo['ward_id'])) {
                $qb->where('v.old_ward_id', (int)$geo['ward_id']);
            }
        } else {
            if (!empty($geo['province_id'])) {
                $qb->where('v.new_province_id', (int)$geo['province_id']);
            }
            if (!empty($geo['ward_id'])) {
                $qb->where('v.new_ward_id', (int)$geo['ward_id']);
            }
        }
        if (!empty($geo['village_id'])) {
            $qb->where('v.village_id', (int)$geo['village_id']);
        }

        // Logic xác định hoàn thành chương trình (từ các báo cáo PCGD có sẵn)
        // GDMN: pm.hoan_thanh_chuong_trinh_gdmn = 1 OR graduation_level_name = 'MN'
        $completedGDMN = "(COALESCE(pm.hoan_thanh_chuong_trinh_gdmn,0)=1 OR (UPPER(TRIM(v.graduation_level_name))='MN'))";
        
        // TH: graduation_level_name IN ('TH', 'THCS', 'THPT')
        $graduatedTH = "(UPPER(TRIM(v.graduation_level_name)) IN ('TH', 'THCS', 'THPT'))";
        
        // THCS: graduation_level_name IN ('THCS', 'THPT')
        $graduatedTHCS = "(UPPER(TRIM(v.graduation_level_name)) IN ('THCS', 'THPT'))";

        // Nơi cư trú: ưu tiên địa chỉ hộ khẩu, fallback địa chỉ cá nhân; luôn gắn tên thôn/xóm (nếu có)
        $residence = "TRIM(CONCAT(
                        TRIM(COALESCE(NULLIF(h.addressline,''), COALESCE(p.addressline,'')) ),
                        CASE WHEN COALESCE(gv.name,'') <> '' THEN CONCAT(' - ', gv.name) ELSE '' END
                      ))";

        // Họ tên cha/mẹ/người đỡ đầu: ưu tiên contact_name, fallback chủ hộ
        $parentName = "TRIM(COALESCE(
                        NULLIF(p.contact_name,''),
                        TRIM(CONCAT(COALESCE(hp.ho_dem,''), ' ', COALESCE(hp.ten,'')))
                      ))";

        // Lấy dữ liệu - groupBy person_id để tránh duplicate
        // Sử dụng MAX() để lấy giá trị tốt nhất cho các trường có thể có nhiều giá trị
        $rows = $qb->selectRaw("
                p.id,
                MAX(TRIM(CONCAT(COALESCE(p.ho_dem,''), ' ', COALESCE(p.ten,'')))) as full_name,
                MAX(p.is_female) as is_female,
                MAX(LPAD(COALESCE(NULLIF(p.dob_day,0),1),2,'0')) as dd,
                MAX(LPAD(COALESCE(NULLIF(p.dob_month,0),1),2,'0')) as mm,
                MAX(LPAD(COALESCE(p.dob_year,0),4,'0')) as yyyy,
                MAX(COALESCE(e.name,'')) as ethnicity,
                MAX({$parentName}) as parent_name,
                MAX({$residence}) as residence,
                MAX(COALESCE(pc.name,'')) as hoan_canh,
                MAX(COALESCE(p.note,'')) as ghi_chu,
                MAX(CASE WHEN {$completedGDMN} THEN 1 ELSE 0 END) as gdmn,
                MAX(CASE WHEN {$graduatedTH} THEN 1 ELSE 0 END) as th,
                MAX(CASE WHEN {$graduatedTHCS} THEN 1 ELSE 0 END) as thcs,
                MAX(gv.name) as village_name
            ")
            ->groupBy('p.id')
            ->orderBy('village_name')
            ->orderBy('full_name')
            ->get()
            ->map(function($r){
                // Format dob dd/mm/yyyy
                $dob = sprintf('%s/%s/%s', $r->dd, $r->mm, $r->yyyy);
                
                // Giới tính
                $gender = $r->is_female ? 'Nữ' : 'Nam';
                
                return [
                    'full_name'      => (string)$r->full_name,
                    'dob_fmt'        => $dob,
                    'gender'         => $gender,
                    'ethnicity'      => (string)$r->ethnicity,
                    'parent_name'    => (string)$r->parent_name,
                    'residence'      => (string)$r->residence,
                    'gdmn'           => (bool)$r->gdmn,
                    'th'             => (bool)$r->th,
                    'thcs'           => (bool)$r->thcs,
                    'hoan_canh'      => (string)$r->hoan_canh,
                    'ghi_chu'        => (string)$r->ghi_chu,
                ];
            })
            ->all();

        return $rows;
    }
}

