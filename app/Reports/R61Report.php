<?php

namespace App\Reports;

use Illuminate\Http\Request;
use App\Models\SurveyFormDetail;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class R61Report extends BaseReport
{
    /** 6.1: Danh sách tốt nghiệp năm qua */
    public function export(Request $req)
    {
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);
        

        // Query danh sách học sinh có năm tốt nghiệp = $academic
        $rows = SurveyFormDetail::query()
            ->notDeleted()
            ->with([
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,identity_number,is_female,ethnicity_id,addressline',
                'person.ethnicity:id,name',
                'education:id,person_id,school_id,graduation_level_id,graduation_year,is_bo_tuc,class_label',
                'education.graduationLevel:id,name',
                'education.school:id,code,name',
                'surveyForm:id,old_province_id,old_district_id,old_ward_id,new_province_id,new_ward_id,village_id,academic_year,so_phieu,head_person_id',
                'surveyForm.headPerson:id,ho_dem,ten',
            ])
            ->whereHas('education', function ($e) use ($academic) {
                $e->whereNotNull('graduation_year')->where('graduation_year', $academic);
            })
            // Áp bộ lọc địa bàn theo chuẩn của hệ thống (nơi cư trú trên bảng phiếu f.*)
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
            ->get();

        // Mở template sẵn nếu có
        $templatePath = resource_path('excel/6.1.TotNghiepNamQua.xlsx');
        if (is_file($templatePath)) {
            $wb = IOFactory::load($templatePath);
        } else {
            $wb = new Spreadsheet();
        }
        $sh = $wb->getActiveSheet();

        // Header (C1:C3)
        $sh->setCellValue('C1', $this->geoName('province_id', $geo));
        $sh->setCellValue('C2', $this->geoName('ward_id', $geo) ?: 'Tất cả xã');
        $sh->setCellValue('C3', $academic);

        // Hàng bắt đầu dữ liệu
        $startRow = is_file($templatePath) ? 10 : 4;
        if (!is_file($templatePath)) {
            // Tiêu đề fallback khi không có template
            $sh->setCellValue('A1', 'DANH SÁCH TỐT NGHIỆP NĂM QUA');
            $sh->setCellValue('A3', 'STT');
            $sh->setCellValue('B3', 'Họ và Tên');
            $sh->setCellValue('C3', ''); // bỏ qua theo mẫu
            $sh->setCellValue('D3', 'Ngày sinh');
            $sh->setCellValue('E3', 'Nữ');
            $sh->setCellValue('F3', 'Dân tộc');
            $sh->setCellValue('G3', 'Địa chỉ');
            $sh->setCellValue('H3', 'Họ tên chủ hộ');
            $sh->setCellValue('I3', 'Bậc tốt nghiệp');
            $sh->setCellValue('J3', 'Bổ túc');
            $sh->setCellValue('K3', 'Năm tốt nghiệp');
            $sh->setCellValue('L3', 'Tốt nghiệp tại trường');
            $sh->setCellValue('M3', 'Tên trường');
            $sh->setCellValue('N3', 'Lớp');
            $sh->setCellValue('O3', 'Số phiếu');
        }

        // Đổ dữ liệu
        $r = $startRow; $i = 1;
        $dataStartRow = 10; // Hàng bắt đầu của dữ liệu trong bảng
        $actualRow = $dataStartRow;
        
        foreach ($rows as $row) {
            $p  = $row->person; $e = $row->education; $sf = $row->surveyForm;
            $this->setCellValue($sh, "A{$actualRow}", $i);
            $this->setCellValue($sh, "B{$actualRow}", trim(($p->ho_dem ?? '').' '.($p->ten ?? '')));
            // C cột bỏ qua theo template (C merge với B hoặc D)
            $dob = ($p->dob_year ? sprintf('%02d/%02d/%s', (int)($p->dob_day ?? 0), (int)($p->dob_month ?? 0), $p->dob_year) : '');
            $this->setCellValue($sh, "D{$actualRow}", $dob, 0, ['align' => Alignment::HORIZONTAL_LEFT]);
            $this->setCellValue($sh, "E{$actualRow}", (($p->is_female ?? false) ? 'X' : ''));
            $this->setCellValue($sh, "F{$actualRow}", $p->ethnicity->name ?? '');
            $this->setCellValue($sh, "G{$actualRow}", $p->addressline ?? '');
            $this->setCellValue($sh, "H{$actualRow}", trim(($sf?->headPerson?->ho_dem ?? '').' '.($sf?->headPerson?->ten ?? '')));
            $this->setCellValue($sh, "I{$actualRow}", $e->graduationLevel->name ?? '');
            $this->setCellValue($sh, "J{$actualRow}", ($e->is_bo_tuc ?? false) ? 'X' : '');
            $this->setCellValue($sh, "K{$actualRow}", $e->graduation_year ?? '');
            $this->setCellValue($sh, "L{$actualRow}", $e->school->name ?? '');
            $this->setCellValue($sh, "M{$actualRow}", $e->school->name ?? '');
            $this->setCellValue($sh, "N{$actualRow}", $e->class_label ?? '');
            $this->setCellValue($sh, "O{$actualRow}", $sf->so_phieu ?? '');
            $actualRow++; $i++;
        }
        
        // Áp dụng style cho dữ liệu trong bảng (từ hàng 7 trở đi)
        if ($i > 1) { // Có dữ liệu
            $lastDataRow = $actualRow - 1;
            
            // Set font size 8 cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:O{$lastDataRow}")->getFont()->setSize(8);
            
            // Set padding (vertical alignment) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:O{$lastDataRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            
            // Căn lề trái cho các cột B, C, G, H, L, M
            foreach (range($dataStartRow, $lastDataRow) as $rowNum) {
                $sh->getStyle("B{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("H{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("L{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sh->getStyle("M{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                
                // Căn giữa cho các cột còn lại: A, D, E, F, I, J, K, N, O
                $sh->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("E{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("F{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("I{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("J{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("K{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("N{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("O{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        
        $r = $actualRow;

        // Chữ ký: cột G, cách danh sách 2 dòng
        $signatureRow = $r + 2;
        $sh->setCellValue("G{$signatureRow}", 'NGƯỜI LẬP BIỂU');
        $sh->setCellValue('G'.($signatureRow + 1), '(Ký và ghi rõ họ tên)');
        $sh->getStyle("G{$signatureRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('G'.($signatureRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("G{$signatureRow}")->getFont()->setBold(true);

        // Khối chữ ký bên phải cột M
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
        }, '6.1.TotNghiepNamQua_'.date('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

