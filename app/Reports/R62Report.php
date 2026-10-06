<?php

namespace App\Reports;

use Illuminate\Http\Request;
use App\Models\SurveyFormDetail;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class R62Report extends BaseReport
{
    /** 6.2: Danh sách đối tượng 15–18 tuổi tốt nghiệp THCS */
    public function export(Request $req)
    {
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme = (string)$req->input('scheme', 'old');
        $geo    = (array)$req->input('geo', []);

        $rows = SurveyFormDetail::query()
            ->notDeleted()
            ->with([
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,identity_number,is_female,ethnicity_id,religion_id,residence_type_id,addressline,new_ward_id',
                'person.ethnicity:id,name',
                'person.religion:id,name',
                'person.type:id,name',
                'education:id,person_id,school_id,education_block_id,graduation_level_id,graduation_year,is_bo_tuc,class_label,bo_hoc_lop,bo_hoc_nam,hoc_xong_lop,hoc_xong_nam',
                'education.graduationLevel:id,name',
                'education.school:id,code,name',
                'surveyForm:id,old_province_id,old_district_id,old_ward_id,new_province_id,new_ward_id,village_id,academic_year,so_phieu,head_person_id',
                'surveyForm.headPerson:id,ho_dem,ten',
            ])
            // Lọc education: 
            // 1. Tốt nghiệp THCS (graduation_level_id = 3) năm $academic
            // 2. Tốt nghiệp THPT (graduation_level_id = 4) năm $academic
            // 3. Đang học lớp 10-12 (education_block_id từ 16-18)
            // 4. Bỏ học lớp 10-12 (bo_hoc_lop = 10, 11, 12)
            // 5. Học xong lớp 10-12 (hoc_xong_lop = 10, 11, 12)
            ->whereHas('education', function ($q) use ($academic) {
                $q->where(function ($subQ) use ($academic) {
                    // Tốt nghiệp THCS hoặc THPT trong năm học
                    $subQ->whereIn('graduation_level_id', [3, 4])
                         ->whereNotNull('graduation_year')
                         ->where('graduation_year', $academic);
                })
                ->orWhere(function ($subQ) {
                    // Hoặc đang học lớp 10, 11, 12 (education_block_id = 16, 17, 18)
                    $subQ->whereIn('education_block_id', [16, 17, 18]);
                })
                ->orWhere(function ($subQ) {
                    // Hoặc bỏ học lớp 10, 11, 12
                    $subQ->whereIn('bo_hoc_lop', [10, 11, 12]);
                })
                ->orWhere(function ($subQ) {
                    // Hoặc học xong lớp 10, 11, 12
                    $subQ->whereIn('hoc_xong_lop', [10, 11, 12]);
                });
            })
            // Áp geo filter theo scheme
            ->whereHas('surveyForm', function ($sf) use ($scheme, $geo) {
                if ($scheme === 'old') {
                    if (!empty($geo['province_id'])) $sf->where('old_province_id', (int)$geo['province_id']);
                    if (!empty($geo['district_id'])) $sf->where('old_district_id', (int)$geo['district_id']);
                    if (!empty($geo['ward_id']))     $sf->where('old_ward_id',     (int)$geo['ward_id']);
                } else {
                    if (!empty($geo['province_id'])) $sf->where('new_province_id', (int)$geo['province_id']);
                    if (!empty($geo['ward_id']))     $sf->where('new_ward_id',     (int)$geo['ward_id']);
                }
                if (!empty($geo['village_id'])) $sf->where('village_id', (int)$geo['village_id']);
            })
            ->orderBy('id')
            ->get()
            // Lọc độ tuổi 15-18 (tính cả 15 và 18)
            ->filter(function ($row) use ($academic) {
                $person = $row->person;
                if (!$person || !$person->dob_year) {
                    return false;
                }
                // Tính tuổi dựa trên năm học
                // Năm học format: "2023-2024" -> lấy năm đầu
                $yearParts = explode('-', $academic);
                $currentYear = (int)$yearParts[0];
                $age = $currentYear - (int)$person->dob_year;
                
                return $age >= 15 && $age <= 18;
            })
            ->values(); // Reset lại keys sau khi filter

        // Template
        $templatePath = resource_path('excel/6.2.15-18TuoiTNTHCS.xlsx');
        $wb = is_file($templatePath) ? IOFactory::load($templatePath) : new Spreadsheet();
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue('C1', $this->geoName('province_id', $geo));
        $sh->setCellValue('C2', $this->geoName('ward_id', $geo) ?: 'Tất cả xã');
        $sh->setCellValue('C3', $academic);

        $startRow = is_file($templatePath) ? 10 : 4;
        if (!is_file($templatePath)) {
            $sh->setCellValue('A1', 'DANH SÁCH 15-18 TUỔI TỐT NGHIỆP THCS');
            $sh->setCellValue('A3', 'STT');
            $sh->setCellValue('B3', 'Họ và Tên');
            $sh->setCellValue('C3', ''); // bỏ qua
            $sh->setCellValue('D3', 'Ngày sinh');
            $sh->setCellValue('E3', 'Nữ');
            $sh->setCellValue('F3', 'Dân tộc');
            $sh->setCellValue('G3', 'Tôn giáo');
            $sh->setCellValue('H3', 'Địa chỉ');
            $sh->setCellValue('I3', 'Họ tên chủ hộ');
            $sh->setCellValue('J3', 'Bậc tốt nghiệp');
            $sh->setCellValue('K3', 'Năm tốt nghiệp');
            $sh->setCellValue('L3', 'Tốt nghiệp tại trường');
            $sh->setCellValue('M3', 'Tên lớp');
            $sh->setCellValue('N3', 'Lớp');
            $sh->setCellValue('O3', 'Bỏ học năm');
            $sh->setCellValue('P3', 'Diện cư trú');
            $sh->setCellValue('Q3', 'Số phiếu');
        }

        // Ghi dữ liệu
        $r = $startRow; $i = 1;
        $dataStartRow = 10; // Hàng bắt đầu của dữ liệu trong bảng
        $actualRow = $dataStartRow;
        
        foreach ($rows as $row) {
            $p = $row->person; $e = $row->education; $sf = $row->surveyForm;
            $this->setCellValue($sh, "A{$actualRow}", $i);
            $this->setCellValue($sh, "B{$actualRow}", trim(($p->ho_dem ?? '').' '.($p->ten ?? '')));
            $dob = ($p->dob_year ? sprintf('%02d/%02d/%s', (int)($p->dob_day ?? 0), (int)($p->dob_month ?? 0), $p->dob_year) : '');
            $this->setCellValue($sh, "D{$actualRow}", $dob, 0, ['align' => Alignment::HORIZONTAL_LEFT]);
            $this->setCellValue($sh, "E{$actualRow}", (($p->is_female ?? false) ? 'X' : ''));
            $this->setCellValue($sh, "F{$actualRow}", $p->ethnicity->name ?? '');
            $this->setCellValue($sh, "G{$actualRow}", $p->religion->name ?? '');
            $this->setCellValue($sh, "H{$actualRow}", $p->addressline ?? '');
            $this->setCellValue($sh, "I{$actualRow}", trim(($sf?->headPerson?->ho_dem ?? '').' '.($sf?->headPerson?->ten ?? '')));
            $this->setCellValue($sh, "J{$actualRow}", $e->graduationLevel->name ?? '');
            $this->setCellValue($sh, "K{$actualRow}", $e->graduation_year ?? '');
            $this->setCellValue($sh, "L{$actualRow}", $e->school->name ?? '');
            $this->setCellValue($sh, "M{$actualRow}", $e->class_label ?? '');
            $this->setCellValue($sh, "N{$actualRow}", $e->bo_hoc_lop ?? '');
            $this->setCellValue($sh, "O{$actualRow}", $e->bo_hoc_nam ?? '');
            $this->setCellValue($sh, "P{$actualRow}", $p->type->name ?? '');
            $this->setCellValue($sh, "Q{$actualRow}", $sf->so_phieu ?? '');
            $actualRow++; $i++;
        }
        
        // Áp dụng style cho dữ liệu trong bảng (từ hàng 10 trở đi)
        if ($i > 1) { // Có dữ liệu
            $lastDataRow = $actualRow - 1;
            
            // Set font size 8 và chữ thường (không in đậm) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:Q{$lastDataRow}")->getFont()->setSize(8)->setBold(false);
            
            // Set padding (vertical alignment) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:Q{$lastDataRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            
            // Căn lề trái cho các cột B, C, H, I, L
            // Căn giữa cho các cột còn lại
            foreach (range($dataStartRow, $lastDataRow) as $rowNum) {
                // Căn trái: B, C (họ tên), H (địa chỉ), I (họ tên chủ hộ), L (tên trường)
                $sh->getStyle("B{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("H{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("I{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("L{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                
                // Căn giữa: A, D, E, F, G, J, K, M, N, O, P, Q
                $sh->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("E{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("F{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("J{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("K{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("M{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("N{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("O{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("P{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("Q{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        
        $r = $actualRow;

        // Chữ ký dưới: cột G
        $signatureRow = $r + 2;
        $sh->setCellValue("G{$signatureRow}", 'NGƯỜI LẬP BIỂU');
        $sh->setCellValue('G'.($signatureRow + 1), '(Ký và ghi rõ họ tên)');
        $sh->getStyle("G{$signatureRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('G'.($signatureRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("G{$signatureRow}")->getFont()->setBold(true);

        // Chữ ký phải: cột M
        $rightTopRow = $signatureRow - 1;
        $sh->setCellValue("M{$rightTopRow}", '....... , ngày ... tháng ... năm ...');
        $sh->setCellValue("M{$signatureRow}", 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $sh->setCellValue('M'.($signatureRow + 1), 'TRƯỞNG BAN');
        $sh->setCellValue('M'.($signatureRow + 2), '(Ký tên, đóng dấu)');
        $sh->getStyle("M{$rightTopRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("M{$signatureRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('M'.($signatureRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('M'.($signatureRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("M{$signatureRow}")->getFont()->setBold(true);
        $sh->getStyle('M'.($signatureRow + 1))->getFont()->setBold(true);

        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, '6.2.15-18TuoiTNTHCS_'.date('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

