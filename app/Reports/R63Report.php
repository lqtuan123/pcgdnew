<?php

namespace App\Reports;

use Illuminate\Http\Request;
use App\Models\SurveyFormDetail;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class R63Report extends BaseReport
{
    /** 6.3: Danh sách đối tượng khuyết tật (lọc theo academic_year của person_educations) */
    public function export(Request $req)
    {
        // Tăng timeout và memory limit cho report lớn
        set_time_limit(300); // 5 phút
        ini_set('memory_limit', '512M');
        
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        // Lấy từ SurveyFormDetail để ánh xạ đủ: phiếu, chủ hộ, thôn xóm, lớp, trường, ...
        $rows = SurveyFormDetail::query()
            ->notDeleted()
            ->with([
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,is_female,ethnicity_id,addressline,new_ward_id,village_id',
                'person.ethnicity:id,name',
                'person.type:id,name',
                'education:id,person_id,academic_year,class_label,school_id',
                'education.school:id,name',
                'personDisability:id,person_id,disability_ids,has_certificate,can_learn,is_deleted',
                'surveyForm:id,so_phieu,head_person_id,household_id,old_province_id,old_district_id,old_ward_id,new_province_id,new_ward_id,village_id',
                'surveyForm.headPerson:id,ho_dem,ten',
                'surveyForm.village:id,name',
            ])
            // Lọc năm học theo person_educations.academic_year
            ->whereHas('education', function ($q) use ($academic) {
                $q->where('academic_year', $academic);
            })
            // Có thông tin khuyết tật
            ->whereHas('personDisability', function ($q) {
                $q->where('is_deleted', false);
            })
            // Áp geo theo chuẩn (nơi cư trú trên phiếu)
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

        // Template đúng cho 6.3
        $templatePath = resource_path('excel/6.3.DS.KhuyetTat.xlsx');
        $wb = is_file($templatePath) ? IOFactory::load($templatePath) : new Spreadsheet();
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue('C1', $this->geoName('province_id', $geo));
        $sh->setCellValue('C2', $this->geoName('ward_id', $geo) ?: 'Tất cả xã');
        $sh->setCellValue('C3', $academic);

        // Bắt đầu từ dòng 7 theo yêu cầu template 6.3
        $startRow = is_file($templatePath) ? 7 : 4;
        if (!is_file($templatePath)) {
            $sh->setCellValue('A1', 'DANH SÁCH ĐỐI TƯỢNG KHUYẾT TẬT');
            $sh->setCellValue('A3', 'STT');
            $sh->setCellValue('B3', 'Họ và Tên');
            // Merge B3:C3 cho tiêu đề họ tên
            $sh->mergeCells('B3:C3');
            $sh->setCellValue('D3', 'Ngày sinh');
            $sh->setCellValue('E3', 'Nữ');
            $sh->setCellValue('F3', 'Dân tộc');
            $sh->setCellValue('G3', 'Số phiếu');
            $sh->setCellValue('H3', 'Lớp học');
            $sh->setCellValue('I3', 'Tên trường');
            $sh->setCellValue('J3', 'Họ tên chủ hộ');
            $sh->setCellValue('K3', 'Quan hệ với chủ hộ');
            $sh->setCellValue('L3', 'Địa chỉ');
            $sh->setCellValue('M3', 'Thôn Xóm');
            $sh->setCellValue('N3', 'Tình trạng khuyết tật');
            $sh->setCellValue('O3', 'Ghi chú');
        }

        $r = $startRow; $i = 1;
        $dataStartRow = 7; // Hàng bắt đầu của dữ liệu trong bảng
        $actualRow = $dataStartRow;
        
        // Cache village names để tránh query nhiều lần
        $villageCache = [];
        
        foreach ($rows as $detail) {
            $p  = $detail->person;
            $e  = $detail->education; // đúng năm academic
            $sf = $detail->surveyForm;
            $d  = $detail->personDisability;

            $dob = ($p->dob_year ? sprintf('%02d/%02d/%s', (int)($p->dob_day ?? 0), (int)($p->dob_month ?? 0), $p->dob_year) : '');
            $disNames = ($d?->category_names ?? []);
            $disText  = $disNames ? implode(', ', $disNames) : '';
            $headName = trim(($sf?->headPerson?->ho_dem ?? '').' '.($sf?->headPerson?->ten ?? ''));
            $relation = ($sf && $sf->household_id) ? ($p->roleInHousehold($sf->household_id) ?? '') : '';
            
            // Sử dụng cache cho village
            $villageId = $sf->village_id ?? null;
            if ($villageId && !isset($villageCache[$villageId])) {
                $villageCache[$villageId] = $this->geoService->geoNameFromId($villageId) ?? '';
            }
            $village = $villageId ? ($villageCache[$villageId] ?? '') : '';

            $this->setCellValue($sh, "A{$actualRow}", $i);
            $this->setCellValue($sh, "B{$actualRow}", trim(($p->ho_dem ?? '').' '.($p->ten ?? '')));
            // Merge cột B và C cho họ tên
            $sh->mergeCells("B{$actualRow}:C{$actualRow}");
            $this->setCellValue($sh, "D{$actualRow}", $dob, 0, ['align' => Alignment::HORIZONTAL_LEFT]);
            $this->setCellValue($sh, "E{$actualRow}", ($p->is_female ? 'X' : ''));
            $this->setCellValue($sh, "F{$actualRow}", $p->ethnicity->name ?? '');
            $this->setCellValue($sh, "G{$actualRow}", $sf->so_phieu ?? '');
            $this->setCellValue($sh, "H{$actualRow}", $e->class_label ?? '');
            $this->setCellValue($sh, "I{$actualRow}", $e?->school?->name ?? '');
            $this->setCellValue($sh, "J{$actualRow}", $headName);
            $this->setCellValue($sh, "K{$actualRow}", $relation);
            $this->setCellValue($sh, "L{$actualRow}", $p->addressline ?? '');
            $this->setCellValue($sh, "M{$actualRow}", $village);
            $this->setCellValue($sh, "N{$actualRow}", $disText);
            $this->setCellValue($sh, "O{$actualRow}", $detail->note ?? '');
            $actualRow++; $i++;
        }
        
        // Áp dụng style cho dữ liệu trong bảng (từ hàng 7 trở đi)
        if ($i > 1) { // Có dữ liệu
            $lastDataRow = $actualRow - 1;
            
            // Set font size 8 và chữ thường (không in đậm, không in nghiêng) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:O{$lastDataRow}")->getFont()
                ->setSize(8)
                ->setBold(false)
                ->setItalic(false);
            
            // Set padding (vertical alignment) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:O{$lastDataRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            
            // Thêm đường kẻ bảng (borders) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:O{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
            
            // Wrap text cho cột N (tình trạng khuyết tật) để chữ xuống dòng nếu tràn
            $sh->getStyle("N{$dataStartRow}:N{$lastDataRow}")->getAlignment()
                ->setWrapText(true);
            
            // Tự động điều chỉnh chiều cao hàng khi cột N xuống dòng
            foreach (range($dataStartRow, $lastDataRow) as $rowNum) {
                $sh->getRowDimension($rowNum)->setRowHeight(-1); // -1 = auto height
            }
            
            // Căn lề trái cho các cột B, C (họ tên), I (tên trường), J (họ tên chủ hộ), L (địa chỉ), M (thôn xóm), N (tình trạng khuyết tật)
            $sh->getStyle("B{$dataStartRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("I{$dataStartRow}:I{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("J{$dataStartRow}:J{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("L{$dataStartRow}:L{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("M{$dataStartRow}:M{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("N{$dataStartRow}:N{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            // Căn giữa: A, D, E, F, G, H, K, O
            $sh->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("D{$dataStartRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("F{$dataStartRow}:F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("G{$dataStartRow}:G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("H{$dataStartRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("K{$dataStartRow}:K{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("O{$dataStartRow}:O{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        $r = $actualRow;

        // Chữ ký dưới: cột G
        $signatureRow = $r + 2;
        $sh->setCellValue("G{$signatureRow}", 'NGƯỜI LẬP BIỂU');
        $sh->setCellValue('G'.($signatureRow + 1), '(Ký và ghi rõ họ tên)');
        $sh->getStyle("G{$signatureRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle('G'.($signatureRow + 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle("G{$signatureRow}")->getFont()->setBold(true);

        // Chữ ký phải: cột M
        $rightTopRow = $signatureRow - 1;
        // Lấy tên xã, ngày tháng năm để dạng dấu chấm
        $wardName = $this->geoName('ward_id', $geo) ?: '.......';
        $dateString = "{$wardName}, ngày ... tháng ... năm ...";
        
        $sh->setCellValue("M{$rightTopRow}", $dateString);
        $sh->setCellValue("M{$signatureRow}", 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $sh->setCellValue('M'.($signatureRow + 1), 'TRƯỞNG BAN');
        $sh->setCellValue('M'.($signatureRow + 2), '(Ký tên, đóng dấu)');
        $sh->getStyle("M{$rightTopRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle("M{$signatureRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle('M'.($signatureRow + 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle('M'.($signatureRow + 2))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle("M{$signatureRow}")->getFont()->setBold(true);
        $sh->getStyle('M'.($signatureRow + 1))->getFont()->setBold(true);

        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, '6.3.DoiTuongKhuyetTat_'.date('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

