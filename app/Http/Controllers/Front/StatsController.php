<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\PersonEducation;
use App\Models\Person;
use App\Models\School;
use App\Models\GeoUnit;
use App\Models\SurveyFormDetail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatsController extends Controller
{
    /**
     * Hiển thị trang bảng số liệu
     */
    public function tables(): View
    {
        return view('front.stats.tables');
    }

    /**
     * Hiển thị trang biểu đồ
     */
    public function charts(): View
    {
        return view('front.stats.charts');
    }

    /**
     * Trang xuất danh sách học sinh tốt nghiệp năm qua
     */
    public function graduates(Request $request): View
    {
        $user = auth()->user();
        
        // Dữ liệu filter: danh sách xã thuộc tỉnh của user (nếu là Province)
        $wards = collect();
        if ($user && $user->isProvince()) {
            $wards = GeoUnit::query()
                ->where('type_level', 3)
                ->where(function ($q) use ($user) {
                    // Ward -> District (level 2) -> Province
                    $q->whereHas('parent', function ($d) use ($user) {
                        $d->where('type_level', 2)->where('parent_id', $user->province_id);
                    })
                    // hoặc Ward -> Province trực tiếp
                    ->orWhere('parent_id', $user->province_id);
                })
                ->orderBy('name')
                ->get(['id','name']);
        }

        return view('front.stats.graduates', [
            'wards' => $wards,
        ]);
    }

    public function exportGraduates(Request $request)
    {
        $user = auth()->user();
        // Lấy năm học vừa kết thúc: nếu năm hiện tại là 2025 thì lấy 2023-2024
        $currentYear = now()->year;
        $gradYear = (string) ($currentYear - 2); // 2023 nếu năm hiện tại là 2025
        
        // Debug ngay từ đầu
        // dd([
        //     'user' => $user->toArray(),
        //     'currentYear' => $currentYear,
        //     'gradYear' => $gradYear,
        //     'request_params' => $request->all()
        // ]);

        $query = \App\Models\SurveyFormDetail::query()
            ->where('is_deleted', false)
            ->with([
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,identity_number,is_female,ethnicity_id,addressline',
                'person.ethnicity:id,name',
                'education:id,person_id,school_id,graduation_level_id,graduation_year,is_bo_tuc,class_label',
                'education.graduationLevel:id,name',
                'education.school:id,code,name',
                'surveyForm:id,new_province_id,new_ward_id,academic_year,so_phieu,head_person_id',
                'surveyForm.headPerson:id,ho_dem,ten',
            ])
            ->whereHas('education', function ($e) use ($gradYear) {
                $gradYearString = $gradYear . '-' . ($gradYear + 1); // 2023-2024 (nếu năm hiện tại là 2025)
                $e->whereNotNull('graduation_year')->where('graduation_year', $gradYearString);
            });

        // Giới hạn theo tỉnh của user
        if ($user && $user->isProvince()) {
            $query->whereHas('surveyForm', function ($sf) use ($user) {
                $sf->where('new_province_id', $user->province_id);
            });
        }

        // Nếu có chọn 1 xã thì lọc theo xã đó
        $singleWardId = $request->input('ward_id');
        if ($singleWardId) {
            $query->whereHas('person', function ($p) use ($singleWardId) {
                $p->where('new_ward_id', (int) $singleWardId);
            });
        }

        $byWard = (bool) $request->input('by_ward');
        $templatePath = resource_path('excel/6.1.TotNghiepNamQua.xlsx');


        // Bỏ chức năng xuất theo từng xã
        // if ($byWard && !$singleWardId) {
        //     // Code đã bị xóa
        // }


        // 1 sheet: theo 1 xã cụ thể hoặc tổng toàn tỉnh
        $rows = $query->orderBy('id')->get();

        // Prefer using provided template if present
        $templatePath = resource_path('excel/6.1.TotNghiepNamQua.xlsx');
        if (is_file($templatePath)) {
            $spreadsheet = IOFactory::load($templatePath);
        } else {
            $spreadsheet = new Spreadsheet();
        }
        $sheet = $spreadsheet->getActiveSheet();

        // Thêm thông tin header
        $provinceName = optional(GeoUnit::find($user->province_id))->name ?? 'N/A';
        $wardName = $singleWardId ? (optional(GeoUnit::find($singleWardId))->name ?? 'N/A') : 'Tất cả xã';
        $sheet->setCellValue('C1', $provinceName);
        $sheet->setCellValue('C2', $wardName);
        $sheet->setCellValue('C3', $gradYear . '-' . ($gradYear + 1));

        // If template is used, assume first data row starts at row 10; otherwise build header and start at 4
        $startRow = is_file($templatePath) ? 10 : 4;
        if (!is_file($templatePath)) {
            // Header (fallback)
            $sheet->setCellValue('A1', 'DANH SÁCH TỐT NGHIỆP NĂM QUA');
            $sheet->setCellValue('A3', 'STT');
            $sheet->setCellValue('B3', 'Họ và Tên');
            $sheet->setCellValue('C3', 'Ngày sinh');
            $sheet->setCellValue('D3', 'Nữ');
            $sheet->setCellValue('E3', 'Dân Tộc');
            $sheet->setCellValue('F3', 'Địa chỉ');
            $sheet->setCellValue('G3', 'Họ và tên chủ hộ');
            $sheet->setCellValue('H3', 'Bậc tốt nghiệp');
            $sheet->setCellValue('I3', 'Bổ túc');
            $sheet->setCellValue('J3', 'Năm tốt nghiệp');
            $sheet->setCellValue('K3', 'Tốt nghiệp tại trường');
            $sheet->setCellValue('L3', 'Tên Trường');
            $sheet->setCellValue('M3', 'Lớp');
            $sheet->setCellValue('N3', 'Số phiếu');
        }

        $r = $startRow; $i = 1;
        foreach ($rows as $row) {
            $p = $row->person;
            $e = $row->education;
            $sf= $row->surveyForm;
            $sheet->setCellValue("A{$r}", $i);
            $sheet->setCellValue("B{$r}", trim(($p->ho_dem ?? '').' '.($p->ten ?? '')));
            // Cột C bỏ qua
            $dob = $p->dob_year ? sprintf('%02d/%02d/%s', (int)($p->dob_day ?? 0), (int)($p->dob_month ?? 0), $p->dob_year) : '';
            $sheet->setCellValue("D{$r}", $dob); // Cột D chứa ngày sinh
            $sheet->setCellValue("E{$r}", ($p->is_female ?? false) ? 'X' : '');
            $sheet->setCellValue("F{$r}", $p->ethnicity->name ?? '');
            $sheet->setCellValue("G{$r}", $p->addressline ?? '');
            $sheet->setCellValue("H{$r}", trim(($sf->headPerson->ho_dem ?? '').' '.($sf->headPerson->ten ?? '')));
            $sheet->setCellValue("I{$r}", $e->graduationLevel->name ?? '');
            $sheet->setCellValue("J{$r}", ($e->is_bo_tuc ?? false) ? 'X' : '');
            $sheet->setCellValue("K{$r}", $e->graduation_year ?? '');
            $sheet->setCellValue("L{$r}", $e->school->name ?? '');
            $sheet->setCellValue("M{$r}", $e->school->name ?? '');
            $sheet->setCellValue("N{$r}", $e->class_label ?? '');
            $sheet->setCellValue("O{$r}", $sf->so_phieu ?? '');
            
            // Căn trái và font Times New Roman cho tất cả cột dữ liệu
            $columns = ['A', 'B', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
            foreach ($columns as $col) {
                $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("{$col}{$r}")->getFont()->setName('Times New Roman');
                $sheet->getStyle("{$col}{$r}")->getFont()->setSize(11);
            }
            
            $r++; $i++;
        }

        // Thêm phần NGƯỜI LẬP BIỂU cách 2 dòng
        $signatureRow = $r + 2;
        $sheet->setCellValue("G{$signatureRow}", "NGƯỜI LẬP BIỂU");
        $sheet->setCellValue("G" . ($signatureRow + 1), "(Ký và ghi rõ họ tên)");
        
        // Căn giữa và in đậm cho "NGƯỜI LẬP BIỂU"
        $sheet->getStyle("G{$signatureRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$signatureRow}")->getFont()->setBold(true);
        $sheet->getStyle("G{$signatureRow}")->getFont()->setSize(8);
        
        // Căn giữa cho "(Ký và ghi rõ họ tên)"
        $sheet->getStyle("G" . ($signatureRow + 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G" . ($signatureRow + 1))->getFont()->setSize(8);

        // Thêm phần ký tên Ban chỉ đạo (chỉ cho bảng 1 sheet)
        $currentDate = now();
        $banChiDaoRow = $signatureRow - 1;
        
        // Dòng trên: ngày tháng năm
        $sheet->setCellValue("M{$banChiDaoRow}", "....... , ngày {$currentDate->day} tháng {$currentDate->month} năm {$currentDate->year}");
        
        // Dòng dưới: TM. BAN CHỈ ĐẠO PCGD, XMC cùng dòng với NGƯỜI LẬP BIỂU
        $sheet->setCellValue("M{$signatureRow}", "TM. BAN CHỈ ĐẠO PCGD, XMC");
        
        // Dòng tiếp theo: TRƯỞNG BAN cùng dòng với (Ký và ghi rõ họ tên)
        $sheet->setCellValue("M" . ($signatureRow + 1), "TRƯỞNG BAN");
        
        // Dòng cuối: (Ký tên, đóng dấu)
        $sheet->setCellValue("M" . ($signatureRow + 2), "(Ký tên, đóng dấu)");
        
        // Căn giữa tất cả và set font size
        $sheet->getStyle("M{$banChiDaoRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M{$banChiDaoRow}")->getFont()->setSize(8);
        
        $sheet->getStyle("M{$signatureRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M{$signatureRow}")->getFont()->setBold(true);
        $sheet->getStyle("M{$signatureRow}")->getFont()->setSize(8);
        
        $sheet->getStyle("M" . ($signatureRow + 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M" . ($signatureRow + 1))->getFont()->setBold(true);
        $sheet->getStyle("M" . ($signatureRow + 1))->getFont()->setSize(8);
        
        $sheet->getStyle("M" . ($signatureRow + 2))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M" . ($signatureRow + 2))->getFont()->setSize(8);

        $filename = '6.1.TotNghiepNamQua.xlsx';
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }

    /**
     * Hiển thị trang 6.2: Danh sách đối tượng 15-18 tuổi tốt nghiệp THCS
     */
    public function thcsGraduates(Request $request)
    {
        $user = auth()->user();
        
        // Lấy danh sách xã thuộc tỉnh của user
        $wards = collect();
        if ($user && $user->isProvince()) {
            $wards = GeoUnit::query()
                ->where('type_level', 3)
                ->where(function ($q) use ($user) {
                    // Ward -> District (level 2) -> Province
                    $q->whereHas('parent', function ($d) use ($user) {
                        $d->where('type_level', 2)->where('parent_id', $user->province_id);
                    })
                    // hoặc Ward -> Province trực tiếp
                    ->orWhere('parent_id', $user->province_id);
                })
                ->orderBy('name')
                ->get();
        }

        return view('front.stats.thcs-graduates', compact('wards'));
    }

    /**
     * Xuất Excel 6.2: Danh sách đối tượng 15-18 tuổi tốt nghiệp THCS
     */
    public function exportThcsGraduates(Request $request)
    {
        $user = auth()->user();
        $singleWardId = $request->input('ward_id');
        
        // Tính tuổi hiện tại (15-18 tuổi)
        $currentYear = now()->year;
        $minBirthYear = $currentYear - 18; // 18 tuổi
        $maxBirthYear = $currentYear - 15; // 15 tuổi
        
        // Query cơ bản
        $query = SurveyFormDetail::with([
            'person' => function($q) {
                $q->select('id', 'ho_dem', 'ten', 'dob_day', 'dob_month', 'dob_year', 'identity_number', 'is_female', 'ethnicity_id', 'religion_id', 'residence_type_id', 'addressline', 'new_ward_id');
            },
            'person.ethnicity' => function($q) {
                $q->select('id', 'name');
            },
            'person.religion' => function($q) {
                $q->select('id', 'name');
            },
            'person.type' => function($q) {
                $q->select('id', 'name');
            },
            'education' => function($q) {
                $q->select('id', 'person_id', 'school_id', 'graduation_level_id', 'graduation_year', 'is_bo_tuc', 'class_label', 'bo_hoc_lop', 'bo_hoc_nam');
            },
            'education.graduationLevel' => function($q) {
                $q->select('id', 'name');
            },
            'education.school' => function($q) {
                $q->select('id', 'code', 'name');
            },
            'surveyForm' => function($q) {
                $q->select('id', 'new_province_id', 'new_ward_id', 'academic_year', 'so_phieu', 'head_person_id');
            },
            'surveyForm.headPerson' => function($q) {
                $q->select('id', 'ho_dem', 'ten');
            }
        ])
        ->where('is_deleted', '')
        ->whereHas('surveyForm', function($q) use ($user) {
            $q->where('new_province_id', $user->province_id);
        })
        ->whereHas('person', function($q) use ($minBirthYear, $maxBirthYear) {
            $q->whereBetween('dob_year', [$minBirthYear, $maxBirthYear]);
        })
        ->whereHas('education', function($q) {
            // Tốt nghiệp THCS (graduation_level_id = 2)
            $q->where('graduation_level_id', 2)
              ->whereNotNull('graduation_year');
        });

        // Lọc theo xã nếu được chọn
        if ($singleWardId) {
            $query->whereHas('person', function($q) use ($singleWardId) {
                $q->where('new_ward_id', $singleWardId);
            });
        }

        $rows = $query->orderBy('id')->get();

        // Prefer using provided template if present
        $templatePath = resource_path('excel/6.2.15-18TuoiTNTHCS.xlsx');
        if (is_file($templatePath)) {
            $spreadsheet = IOFactory::load($templatePath);
        } else {
            $spreadsheet = new Spreadsheet();
        }

        $sheet = $spreadsheet->getActiveSheet();

        // Thêm thông tin header
        $provinceName = optional(GeoUnit::find($user->province_id))->name ?? 'N/A';
        $wardName = $singleWardId ? optional(GeoUnit::find($singleWardId))->name : 'Tất cả xã';
        $sheet->setCellValue('C1', $provinceName);
        $sheet->setCellValue('C2', $wardName);
        $sheet->setCellValue('C3', 'Năm ' . $currentYear);

        $startRow = is_file($templatePath) ? 10 : 4;
        $r = $startRow; $i = 1;
        foreach ($rows as $row) {
            $p = $row->person; $e = $row->education; $sf = $row->surveyForm;
            
            $sheet->setCellValue("A{$r}", $i);
            $sheet->setCellValue("B{$r}", trim(($p->ho_dem ?? '') . ' ' . ($p->ten ?? '')));
            // Cột C bỏ qua (merged với D)
            $dob = $p->dob_year ? sprintf('%02d/%02d/%s', (int)($p->dob_day ?? 0), (int)($p->dob_month ?? 0), $p->dob_year) : '';
            $sheet->setCellValue("D{$r}", $dob); // Cột D chứa ngày sinh
            $sheet->setCellValue("E{$r}", ($p->is_female ?? false) ? 'X' : '');
            $sheet->setCellValue("F{$r}", $p->ethnicity->name ?? '');
            $sheet->setCellValue("G{$r}", $p->religion->name ?? ''); // Cột G: Tôn giáo
            $sheet->setCellValue("H{$r}", $p->addressline ?? ''); // Cột H: Địa chỉ
            $sheet->setCellValue("I{$r}", trim(($sf->headPerson->ho_dem ?? '') . ' ' . ($sf->headPerson->ten ?? ''))); // Cột I: Họ tên chủ hộ
            $sheet->setCellValue("J{$r}", $e->graduationLevel->name ?? ''); // Cột J: Bậc tốt nghiệp
            $sheet->setCellValue("K{$r}", $e->graduation_year ?? ''); // Cột K: Năm tốt nghiệp
            $sheet->setCellValue("L{$r}", $e->school->name ?? ''); // Cột L: Tốt nghiệp tại trường
            $sheet->setCellValue("M{$r}", $e->class_label ?? ''); // Cột M: Tên lớp
            $sheet->setCellValue("N{$r}", $e->bo_hoc_lop ?? ''); // Cột N: Lớp (từ bo_hoc_lop)
            $sheet->setCellValue("O{$r}", $e->bo_hoc_nam ?? ''); // Cột O: Bổ học năm (từ bo_hoc_nam)
            $sheet->setCellValue("P{$r}", $p->type->name ?? ''); // Cột P: Diện cư trú
            $sheet->setCellValue("Q{$r}", $sf->so_phieu ?? ''); // Cột Q: Số phiếu
            
            // Căn trái và font Times New Roman cho tất cả cột dữ liệu
            $columns = ['A', 'B', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'];
            foreach ($columns as $col) {
                $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("{$col}{$r}")->getFont()->setName('Times New Roman');
                $sheet->getStyle("{$col}{$r}")->getFont()->setSize(11);
            }
            
            $r++; $i++;
        }

        // Thêm phần NGƯỜI LẬP BIỂU cách 2 dòng
        $signatureRow = $r + 2;
        $sheet->setCellValue("G{$signatureRow}", "NGƯỜI LẬP BIỂU");
        $sheet->setCellValue("G" . ($signatureRow + 1), "(Ký và ghi rõ họ tên)");
        
        // Căn giữa và in đậm cho "NGƯỜI LẬP BIỂU"
        $sheet->getStyle("G{$signatureRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$signatureRow}")->getFont()->setBold(true);
        $sheet->getStyle("G{$signatureRow}")->getFont()->setSize(8);
        
        // Căn giữa cho "(Ký và ghi rõ họ tên)"
        $sheet->getStyle("G" . ($signatureRow + 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G" . ($signatureRow + 1))->getFont()->setSize(8);

        // Thêm phần TM. BAN CHỈ ĐẠO PCGD, XMC (chỉ cho single sheet)
        $dateRow = $signatureRow - 1;
        $sheet->setCellValue("M{$dateRow}", "....... , ngày...tháng...năm...");
        $sheet->setCellValue("M{$signatureRow}", "TM. BAN CHỈ ĐẠO PCGD, XMC");
        $sheet->setCellValue("M" . ($signatureRow + 1), "TRƯỞNG BAN");
        $sheet->setCellValue("M" . ($signatureRow + 2), "(Ký tên, đóng dấu)");
        
        // Căn giữa và in đậm cho các dòng chữ ký
        $signatureColumns = ['M'];
        foreach ($signatureColumns as $col) {
            $sheet->getStyle("{$col}{$dateRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$col}{$dateRow}")->getFont()->setSize(8);
            
            $sheet->getStyle("{$col}{$signatureRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$col}{$signatureRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$signatureRow}")->getFont()->setSize(8);
            
            $sheet->getStyle("{$col}" . ($signatureRow + 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$col}" . ($signatureRow + 1))->getFont()->setBold(true);
            $sheet->getStyle("{$col}" . ($signatureRow + 1))->getFont()->setSize(8);
            
            $sheet->getStyle("{$col}" . ($signatureRow + 2))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$col}" . ($signatureRow + 2))->getFont()->setSize(8);
        }

        $filename = '6.2.DoiTuong15-18TotNghiepTHCS.xlsx';
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }
}
