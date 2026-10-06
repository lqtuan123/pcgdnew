<?php

namespace App\Reports;

use Illuminate\Http\Request;
use App\Models\SurveyFormDetail;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class R64Report extends BaseReport
{
    /** 6.4: Danh sách đối tượng bỏ học (person_educations, people) */
    public function export(Request $req)
    {
        // Tăng timeout và memory limit cho report lớn
        set_time_limit(300); // 5 phút
        ini_set('memory_limit', '512M');
        
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        $rows = SurveyFormDetail::query()
            ->notDeleted()
            ->with([
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,is_female,ethnicity_id,religion_id,addressline,new_ward_id,village_id',
                'person.ethnicity:id,name',
                'person.religion:id,name',
                'person.type:id,name',
                'education:id,person_id,academic_year,class_label,school_id,bo_hoc_lop,bo_hoc_nam,hoc_xong_lop,hoc_xong_nam,note',
                'education.school:id,name',
                'surveyForm:id,so_phieu,head_person_id,household_id,old_province_id,old_district_id,old_ward_id,new_province_id,new_ward_id,village_id',
                'surveyForm.headPerson:id,ho_dem,ten',
                'surveyForm.village:id,name',
            ])
            // Lọc năm học theo person_educations.academic_year
            ->whereHas('education', function ($q) use ($academic) {
                $q->where('academic_year', $academic)
                  ->where(function($subQ) {
                      $subQ->whereNotNull('bo_hoc_lop')
                           ->orWhereNotNull('hoc_xong_lop');
                  });
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

        $templatePath = resource_path('excel/6.4.DS.BoHoc.xlsx');
        $wb = is_file($templatePath) ? IOFactory::load($templatePath) : new Spreadsheet();
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue('C1', $this->geoName('province_id', $geo));
        $sh->setCellValue('C2', $this->geoName('ward_id', $geo) ?: 'Tất cả xã');
        $sh->setCellValue('C3', $academic);

        // Bắt đầu từ dòng 8 theo yêu cầu
        $startRow = is_file($templatePath) ? 8 : 4;
        if (!is_file($templatePath)) {
            $sh->setCellValue('A1', 'DANH SÁCH ĐỐI TƯỢNG BỎ HỌC');
            $sh->setCellValue('A3', 'STT');
            $sh->setCellValue('B3', 'Họ và Tên');
            // Merge B3:C3 cho tiêu đề họ tên
            $sh->mergeCells('B3:C3');
            $sh->setCellValue('D3', 'Ngày sinh');
            $sh->setCellValue('E3', 'Nữ');
            $sh->setCellValue('F3', 'Dân tộc');
            $sh->setCellValue('G3', 'Tôn giáo');
            $sh->setCellValue('H3', 'Họ tên chủ hộ');
            $sh->setCellValue('I3', 'Địa chỉ');
            $sh->setCellValue('J3', 'Thôn Xóm');
            $sh->setCellValue('K3', 'Số phiếu');
            $sh->setCellValue('L3', 'Bỏ học lớp / Học xong lớp');
            $sh->setCellValue('M3', 'Năm bỏ học');
            $sh->setCellValue('N3', 'Lý do bỏ học');
        }

        $r = $startRow; $i = 1;
        $dataStartRow = 8; // Hàng bắt đầu của dữ liệu trong bảng
        $actualRow = $dataStartRow;
        
        // Cache village names để tránh query nhiều lần
        $villageCache = [];
        
        foreach ($rows as $detail) {
            $p  = $detail->person;
            $e  = $detail->education; // đúng năm academic
            $sf = $detail->surveyForm;

            $dob = ($p->dob_year ? sprintf('%02d/%02d/%s', (int)($p->dob_day ?? 0), (int)($p->dob_month ?? 0), $p->dob_year) : '');
            $headName = trim(($sf?->headPerson?->ho_dem ?? '').' '.($sf?->headPerson?->ten ?? ''));
            
            // Sử dụng cache cho village
            $villageId = $sf->village_id ?? null;
            if ($villageId && !isset($villageCache[$villageId])) {
                $villageCache[$villageId] = $this->geoService->geoNameFromId($villageId) ?? '';
            }
            $village = $villageId ? ($villageCache[$villageId] ?? '') : '';
            
            // Cột L: Bỏ học lớp hoặc học xong lớp
            $classInfo = '';
            if ($e->bo_hoc_lop) {
                $classInfo = $e->bo_hoc_lop;
            } elseif ($e->hoc_xong_lop) {
                $classInfo = $e->hoc_xong_lop;
            }

            $this->setCellValue($sh, "A{$actualRow}", $i);
            $this->setCellValue($sh, "B{$actualRow}", trim(($p->ho_dem ?? '').' '.($p->ten ?? '')));
            // Merge cột B và C cho họ tên
            $sh->mergeCells("B{$actualRow}:C{$actualRow}");
            $this->setCellValue($sh, "D{$actualRow}", $dob, 0, ['align' => Alignment::HORIZONTAL_LEFT]);
            $this->setCellValue($sh, "E{$actualRow}", ($p->is_female ? 'X' : ''));
            $this->setCellValue($sh, "F{$actualRow}", $p->ethnicity->name ?? '');
            $this->setCellValue($sh, "G{$actualRow}", $p->religion->name ?? '');
            $this->setCellValue($sh, "H{$actualRow}", $headName);
            $this->setCellValue($sh, "I{$actualRow}", $p->addressline ?? '');
            $this->setCellValue($sh, "J{$actualRow}", $village);
            $this->setCellValue($sh, "K{$actualRow}", $sf->so_phieu ?? '');
            $this->setCellValue($sh, "L{$actualRow}", $classInfo);
            $this->setCellValue($sh, "M{$actualRow}", $e->bo_hoc_nam ?? $e->hoc_xong_nam ?? '');
            $this->setCellValue($sh, "N{$actualRow}", $e->note ?? '');
            $actualRow++; $i++;
        }
        
        // Áp dụng style cho dữ liệu trong bảng (từ hàng 8 trở đi)
        if ($i > 1) { // Có dữ liệu
            $lastDataRow = $actualRow - 1;
            
            // Set font size 8 và chữ thường (không in đậm, không in nghiêng) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:N{$lastDataRow}")->getFont()
                ->setSize(8)
                ->setBold(false)
                ->setItalic(false);
            
            // Set padding (vertical alignment) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:N{$lastDataRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            
            // Thêm đường kẻ bảng (borders) cho toàn bộ dữ liệu
            $sh->getStyle("A{$dataStartRow}:N{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
            
            // Cột N (lý do bỏ học) không wrap text, để trống nếu không có dữ liệu
            $sh->getStyle("N{$dataStartRow}:N{$lastDataRow}")->getAlignment()
                ->setWrapText(false);
            
            // Căn lề trái cho các cột B, C (họ tên), H (họ tên chủ hộ), I (địa chỉ), J (thôn xóm), N (lý do bỏ học)
            $sh->getStyle("B{$dataStartRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("H{$dataStartRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("I{$dataStartRow}:I{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("J{$dataStartRow}:J{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sh->getStyle("N{$dataStartRow}:N{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            // Căn giữa: A, D, E, F, G, K, L, M
            $sh->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("D{$dataStartRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("F{$dataStartRow}:F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("G{$dataStartRow}:G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("K{$dataStartRow}:K{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("L{$dataStartRow}:L{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sh->getStyle("M{$dataStartRow}:M{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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

        // Chữ ký phải: cột K
        $rightTopRow = $signatureRow - 1;
        // Lấy tên xã, ngày tháng năm để dạng dấu chấm
        $wardName = $this->geoName('ward_id', $geo) ?: '.......';
        $dateString = "{$wardName}, ngày ... tháng ... năm ...";
        
        $sh->setCellValue("K{$rightTopRow}", $dateString);
        $sh->setCellValue("K{$signatureRow}", 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $sh->setCellValue('K'.($signatureRow + 1), 'TRƯỞNG BAN');
        $sh->setCellValue('K'.($signatureRow + 2), '(Ký tên, đóng dấu)');
        $sh->getStyle("K{$rightTopRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle("K{$signatureRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle('K'.($signatureRow + 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle('K'.($signatureRow + 2))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(false);
        $sh->getStyle("K{$signatureRow}")->getFont()->setBold(true);
        $sh->getStyle('K'.($signatureRow + 1))->getFont()->setBold(true);

        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, '6.4.DoiTuongBoHoc_'.date('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

