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

class LiteracyTrackingBookReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* ========================= Template mapping ========================= */
    private function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/SoTheoDoiXMC.xlsx'),
            'header' => [
                'province' => 'C1',  // Tỉnh/Thành - In đậm, Tahoma 8
                'ward'     => 'C2',  // Phường/Xã - In đậm, Tahoma 8
            ],
            'startRow'  => 8,         // Hàng đầu của dữ liệu (ngay dưới tiêu đề cột)
            'lastCol'   => 'P',       // Cột cuối cùng (Ghi chú)
            // Cột xuất dữ liệu
            'col' => [
                'stt'           => 'A',
                'name'          => 'B',  // Merge B:C
                'dob'           => 'D',  // Ngày, tháng, năm sinh
                'gender'        => 'E',  // Giới tính
                'ethnicity'     => 'F',  // Dân tộc
                'parent_name'   => 'G',  // Họ tên cha (hoặc mẹ hoặc người đỡ đầu)
                'residence'     => 'H',  // Chổ ở hiện nay
                'ky1'           => 'I',  // Đã học xong kỳ 1 (lớp 1)
                'ky2'           => 'J',  // Đã học xong kỳ 2 (lớp 2)
                'ky3'           => 'K',  // Đã học xong kỳ 3 (lớp 3)
                'ky4'           => 'L',  // Đã học xong kỳ 4 (lớp 4)
                'ky5'           => 'M',  // Đã học xong kỳ 5 (lớp 5)
                'bo_hoc'        => 'N',  // Bỏ học
                'hoan_canh'     => 'O',  // Hoàn cảnh đặc biệt
                'ghi_chu'       => 'P',  // Ghi chú
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

        // Nhân bản dòng
        $startRow = $cfg['startRow'];
        $need = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
        }

        // Đổ dữ liệu
        $r = $startRow;
        $stt = 1;
        foreach ($rows as $row) {
            // STT
            $this->setCellValue($sh, $cfg['col']['stt'].$r, $stt++);
            
            // Họ và tên (Merge B:C) - dùng safeMerge để tránh conflict
            $this->safeMerge($sh, 'B'.$r.':C'.$r);
            $this->setCellValue($sh, $cfg['col']['name'].$r, $row['full_name']);
            
            // Ngày, tháng, năm sinh
            $this->setCellValue($sh, $cfg['col']['dob'].$r, $row['dob_fmt']);
            
            // Giới tính
            $this->setCellValue($sh, $cfg['col']['gender'].$r, $row['gender']);
            
            // Dân tộc
            $this->setCellValue($sh, $cfg['col']['ethnicity'].$r, $row['ethnicity'] ?: '');
            
            // Họ tên cha (hoặc mẹ hoặc người đỡ đầu)
            $this->setCellValue($sh, $cfg['col']['parent_name'].$r, $row['parent_name'] ?: '');
            
            // Chổ ở hiện nay
            $this->setCellValue($sh, $cfg['col']['residence'].$r, $row['residence'] ?: '');
            
            // Đã học xong kỳ 1-5
            $this->setCellValue($sh, $cfg['col']['ky1'].$r, $row['ky1'] ? 'X' : '');
            $this->setCellValue($sh, $cfg['col']['ky2'].$r, $row['ky2'] ? 'X' : '');
            $this->setCellValue($sh, $cfg['col']['ky3'].$r, $row['ky3'] ? 'X' : '');
            $this->setCellValue($sh, $cfg['col']['ky4'].$r, $row['ky4'] ? 'X' : '');
            $this->setCellValue($sh, $cfg['col']['ky5'].$r, $row['ky5'] ? 'X' : '');
            
            // Bỏ học
            $this->setCellValue($sh, $cfg['col']['bo_hoc'].$r, $row['bo_hoc'] ?: '');
            
            // Hoàn cảnh đặc biệt
            $this->setCellValue($sh, $cfg['col']['hoan_canh'].$r, $row['hoan_canh'] ?: '');
            
            // Ghi chú
            $this->setCellValue($sh, $cfg['col']['ghi_chu'].$r, $row['ghi_chu'] ?: '');
            
            $r++;
        }

        // Format toàn bộ dữ liệu: Tahoma 8, không in đậm
        if ($r > $startRow) {
            $dataRange = 'A'.$startRow.':'.$cfg['lastCol'].($r-1);
            $sh->getStyle($dataRange)->getFont()
                ->setName('Tahoma')
                ->setSize(8)
                ->setBold(false); // Đảm bảo không in đậm
            
            // Căn giữa cho các cột: STT, Ngày sinh, Giới tính, 5 cột kỳ
            $sh->getStyle('A'.$startRow.':A'.($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['dob'].$startRow.':'.$cfg['col']['dob'].($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['gender'].$startRow.':'.$cfg['col']['gender'].($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['ky1'].$startRow.':'.$cfg['col']['ky5'].($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle($cfg['col']['bo_hoc'].$startRow.':'.$cfg['col']['bo_hoc'].($r-1))
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Thêm border cho các dòng dữ liệu
            $this->drawThinBorders($sh, $startRow, $r-1, 'A', $cfg['lastCol']);
        }

        // Thêm phần ký tên
        $lastDataRow = $r - 1; // Dòng dữ liệu cuối cùng
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
    private function queryList(string $academic, string $scheme, array $geo, int $yMin, int $yMax): array
    {
        // Latest person_educations của năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        // Latest literacy record
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

        // Áp địa bàn (scheme/new/old)
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

        // Nơi cư trú: ưu tiên địa chỉ hộ khẩu, fallback địa chỉ cá nhân; luôn gắn tên thôn/xóm (nếu có)
        $residence = "TRIM(CONCAT(
                        TRIM(COALESCE(NULLIF(h.addressline,''), COALESCE(p.addressline,'')) ),
                        CASE WHEN COALESCE(v.name,'') <> '' THEN CONCAT(' - ', v.name) ELSE '' END
                      ))";

        // Họ tên cha/mẹ/người đỡ đầu: ưu tiên contact_name, fallback chủ hộ
        $parentName = "TRIM(COALESCE(
                        NULLIF(p.contact_name,''),
                        TRIM(CONCAT(COALESCE(hp.ho_dem,''), ' ', COALESCE(hp.ten,'')))
                      ))";

        // Lọc theo điều kiện XMC mức 1 hoặc mức 2 (giống báo cáo 5.4)
        // Các cột "Trình độ hiện nay" sẽ dựa vào dang_hoc_lop và hoan_thanh_lop từ person_literacy
        // Nếu chưa có dữ liệu trong person_literacy thì các cột kỳ sẽ không được tích
        $rows = $qb->whereRaw("({$lv1} OR {$lv2})")
            ->selectRaw("
                p.id,
                TRIM(CONCAT(COALESCE(p.ho_dem,''), ' ', COALESCE(p.ten,''))) as full_name,
                p.is_female,
                LPAD(COALESCE(NULLIF(p.dob_day,0),1),2,'0') as dd,
                LPAD(COALESCE(NULLIF(p.dob_month,0),1),2,'0') as mm,
                LPAD(COALESCE(p.dob_year,0),4,'0') as yyyy,
                COALESCE(e.name,'') as ethnicity,
                {$parentName} as parent_name,
                {$residence} as residence,
                COALESCE(pe.bo_hoc_lop,'') as bo_hoc,
                COALESCE(pc.name,'') as hoan_canh,
                COALESCE(p.note,'') as ghi_chu,
                COALESCE(xmc.hoan_thanh_lop, 0) as hoan_thanh_lop,
                COALESCE(xmc.dang_hoc_lop, 0) as dang_hoc_lop,
                COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0) as bo_hoc_lop_num
            ")
            ->orderBy('v.name')
            ->orderBy('full_name')
            ->get()
            ->map(function($r){
                // Format dob dd/mm/yyyy
                $dob = sprintf('%s/%s/%s', $r->dd, $r->mm, $r->yyyy);
                
                // Giới tính
                $gender = $r->is_female ? 'Nữ' : 'Nam';
                
                // Xác định đã học xong kỳ nào (1-5)
                // Logic: 
                // 1. Ưu tiên: hoan_thanh_lop (đã hoàn thành lớp X → đã học xong kỳ X)
                // 2. Nếu không có hoan_thanh_lop: dang_hoc_lop (đang học lớp X → đã học xong lớp X-1)
                // 3. Nếu không có cả 2: bo_hoc_lop (bỏ học lớp X → đã học xong lớp X-1)
                $hoanThanh = (int)$r->hoan_thanh_lop;
                $dangHoc = (int)$r->dang_hoc_lop;
                $boHoc = (int)$r->bo_hoc_lop_num;
                
                // Xác định lớp đã học xong (ưu tiên theo thứ tự)
                $lopDaHocXong = 0;
                if ($hoanThanh > 0) {
                    // Có hoàn thành lớp → dùng nó
                    $lopDaHocXong = $hoanThanh;
                } elseif ($dangHoc > 0) {
                    // Đang học lớp X → đã học xong lớp X-1
                    $lopDaHocXong = max(0, $dangHoc - 1);
                } elseif ($boHoc > 0) {
                    // Bỏ học lớp X → đã học xong lớp X-1
                    $lopDaHocXong = max(0, $boHoc - 1);
                }
                
                // Chỉ tích 1 cột tương ứng với lớp đã học xong (không tích các cột nhỏ hơn)
                // Kỳ 1: đã học xong lớp 1
                $ky1 = ($lopDaHocXong == 1);
                // Kỳ 2: đã học xong lớp 2
                $ky2 = ($lopDaHocXong == 2);
                // Kỳ 3: đã học xong lớp 3
                $ky3 = ($lopDaHocXong == 3);
                // Kỳ 4: đã học xong lớp 4
                $ky4 = ($lopDaHocXong == 4);
                // Kỳ 5: đã học xong lớp 5
                $ky5 = ($lopDaHocXong == 5);
                
                return [
                    'full_name'      => (string)$r->full_name,
                    'dob_fmt'        => $dob,
                    'gender'         => $gender,
                    'ethnicity'      => (string)$r->ethnicity,
                    'parent_name'    => (string)$r->parent_name,
                    'residence'      => (string)$r->residence,
                    'ky1'            => $ky1,
                    'ky2'            => $ky2,
                    'ky3'            => $ky3,
                    'ky4'            => $ky4,
                    'ky5'            => $ky5,
                    'bo_hoc'         => (string)$r->bo_hoc,
                    'hoan_canh'      => (string)$r->hoan_canh,
                    'ghi_chu'        => (string)$r->ghi_chu,
                ];
            })
            ->all();

        return $rows;
    }
}

