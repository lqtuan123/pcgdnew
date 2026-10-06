<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\PersonMamnon;
use App\Models\Person;
use App\Models\SurveyFormDetail;
use App\Models\SurveyForm;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\PersonEducation;
use App\Models\EducationBlock;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PreschoolController extends Controller
{
    private $dropdownRanges = [];
    public function index(Request $request)
    {
        // Kiểm tra quyền xem học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'view')) {
            abort(403, 'Bạn không có quyền xem danh sách học sinh mầm non.');
        }

        $academicYear = $request->get('academic_year', \App\Models\SurveyForm::currentAcademicYear());
        $search = $request->get('search', '');
        $scheme = $request->get('scheme', 'old');
        $perPage = $request->get('per_page', 20);
        $ageFilter = $request->get('age_filter', 'all');

        // Lấy thông tin geo từ request
        $geoData = $request->get('geo', []);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;

        $query = SurveyFormDetail::with([
            'person.ethnicity',
            'person.village',
            'personMamnon',
            'education.block',
            'education.school',
            'surveyForm.headPerson',
            'surveyForm'
        ])->whereNotNull('person_mamnon_id')->notDeleted();

        // Lọc theo năm học - lấy theo academic_year của SurveyFormDetail
        if ($academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        // Lọc theo khu vực - ưu tiên từ cấp nhỏ đến lớn
        if ($wardId) {
            // Ưu tiên cao nhất: lọc theo xã
            $query->whereHas('surveyForm', function($q) use ($wardId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_ward_id', $wardId);
                } else {
                    $q->where('new_ward_id', $wardId);
                }
            });
        } elseif ($districtId) {
            // Ưu tiên thứ 2: lọc theo huyện/quận
            $query->whereHas('surveyForm', function($q) use ($districtId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_district_id', $districtId);
                } else {
                    $q->where('new_district_id', $districtId);
                }
            });
        } elseif ($provinceId) {
            // Ưu tiên thứ 3: lọc theo tỉnh/thành phố
            $query->whereHas('surveyForm', function($q) use ($provinceId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_province_id', $provinceId);
                } else {
                    $q->where('new_province_id', $provinceId);
                }
            });
        }

        if ($villageId) {
            $query->whereHas('surveyForm', function($q) use ($villageId) {
                $q->where('village_id', $villageId);
            });
        }

        // Tìm kiếm
        if ($search) {
            $query->whereHas('person', function($q) use ($search) {
                $q->where('ten', 'like', "%{$search}%")
                  ->orWhere('ho_dem', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$search}%"]);
            });
        }

        // Lọc theo độ tuổi
        if ($ageFilter !== 'all') {
            $query->whereHas('person', function($q) use ($ageFilter, $academicYear) {
                $year = explode('-', $academicYear)[0]; // Lấy năm đầu của năm học
                
                // Tính năm sinh để có tuổi phù hợp: năm học - tuổi
                $targetYear = $year - intval($ageFilter);
                $q->where('dob_year', $targetYear);
            });
        }

        $students = $query->orderBy('id', 'desc')->paginate($perPage);

        return view('front.preschool-students.index', compact(
            'students', 'academicYear', 'search', 'scheme', 'geoData', 'ageFilter'
        ));
    }

    public function create()
    {
        // LƯU Ý: Học sinh mầm non không được tạo trực tiếp mà phải từ phiếu điều tra
        return redirect()->route('front.surveyforms.index')
            ->with('info', 'Học sinh mầm non không được tạo trực tiếp. Vui lòng tạo phiếu điều tra trước.');
    }

    public function store(Request $request)
    {
        // LƯU Ý: Học sinh mầm non không được tạo trực tiếp mà phải từ phiếu điều tra
        return redirect()->route('front.surveyforms.index')
            ->with('info', 'Học sinh mầm non không được tạo trực tiếp. Vui lòng tạo phiếu điều tra trước.');
    }

    public function edit($id)
    {
        // Kiểm tra quyền chỉnh sửa học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa thông tin học sinh mầm non.');
        }

        $student = SurveyFormDetail::with([
            'person',
            'personMamnon',
            'education.block',
            'education.school'
        ])->whereNotNull('person_mamnon_id')->findOrFail($id);
        
        return view('front.preschool-students.edit', compact('student'));
    }

    public function update(Request $request, $id)
    {
        // Kiểm tra quyền cập nhật học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật thông tin học sinh mầm non.'
            ], 403);
        }

        $request->validate([
            'hoc_ban_tru' => 'boolean',
            'hoc_du_ngay_theo_quy_dinh' => 'boolean',
            'chuyen_can' => 'boolean',
            'theo_doi_bd_can_nang' => 'boolean',
            'sdd_nhe_can' => 'boolean',
            'theo_doi_bd_chieu_cao' => 'boolean',
            'sdd_thap_coi' => 'boolean',
            'dan_toc_chuan_bi_tv' => 'boolean',
            'hoan_thanh_chuong_trinh_gdmn' => 'boolean',
            'hoc_2_buoi' => 'boolean',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'addressline' => 'nullable|string|max:500',
            'education_block_id' => 'nullable|integer|exists:education_blocks,id',
            'class_label' => 'nullable|string|max:255',
            'school_id' => 'nullable|integer|exists:schools,id',
            'school_code' => 'nullable|string|max:20',
        ]);

        try {
            DB::beginTransaction();

            $surveyFormDetail = SurveyFormDetail::whereNotNull('person_mamnon_id')->findOrFail($id);
            
            // Cập nhật PersonMamnon
            $surveyFormDetail->personMamnon->update([
                'hoc_ban_tru' => $request->boolean('hoc_ban_tru'),
                'hoc_du_ngay_theo_quy_dinh' => $request->boolean('hoc_du_ngay_theo_quy_dinh'),
                'chuyen_can' => $request->boolean('chuyen_can'),
                'theo_doi_bd_can_nang' => $request->boolean('theo_doi_bd_can_nang'),
                'sdd_nhe_can' => $request->boolean('sdd_nhe_can'),
                'theo_doi_bd_chieu_cao' => $request->boolean('theo_doi_bd_chieu_cao'),
                'sdd_thap_coi' => $request->boolean('sdd_thap_coi'),
                'dan_toc_chuan_bi_tv' => $request->boolean('dan_toc_chuan_bi_tv'),
                'hoan_thanh_chuong_trinh_gdmn' => $request->boolean('hoan_thanh_chuong_trinh_gdmn'),
                'hoc_2_buoi' => $request->boolean('hoc_2_buoi'),
            ]);

            // Cập nhật thông tin liên hệ trong Person
            $surveyFormDetail->person->update([
                'contact_name' => $request->input('contact_name'),
                'phone' => $request->input('phone'),
                'addressline' => $request->input('addressline'),
            ]);

            // Cập nhật thông tin học tập trong PersonEducation
            if ($surveyFormDetail->education) {
                // Kiểm tra mã trường nếu có nhập
                $schoolCode = $request->input('school_code');
                
                $updateData = [
                    'education_block_id' => $request->input('education_block_id'),
                    'class_label' => $request->input('class_label'),
                ];
                
                // Kiểm tra mã trường nếu có nhập
                if ($schoolCode) {
                    $school = \App\Models\School::where('code', $schoolCode)->first();
                    if (!$school) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', "Cập nhật mã trường thất bại: Mã trường '{$schoolCode}' không tồn tại trong hệ thống.")
                            ->withInput($request->all());
                    }
                    $updateData['school_id'] = $school->id;
                }
                
                $surveyFormDetail->education->update($updateData);
            } else {
                // Tạo PersonEducation mới nếu chưa có
                $schoolCode = $request->input('school_code');
                
                $createData = [
                    'person_id' => $surveyFormDetail->person_id,
                    'education_block_id' => $request->input('education_block_id'),
                    'class_label' => $request->input('class_label'),
                    'academic_year' => $surveyFormDetail->surveyForm->academic_year,
                ];
                
                // Kiểm tra mã trường nếu có nhập
                if ($schoolCode) {
                    $school = \App\Models\School::where('code', $schoolCode)->first();
                    if (!$school) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', "Cập nhật mã trường thất bại: Mã trường '{$schoolCode}' không tồn tại trong hệ thống.")
                            ->withInput($request->all());
                    }
                    $createData['school_id'] = $school->id;
                }
                
                $personEducation = \App\Models\PersonEducation::create($createData);
                
                // Cập nhật person_education_id trong SurveyFormDetail
                $surveyFormDetail->update([
                    'person_education_id' => $personEducation->id
                ]);
            }

            // Cập nhật updated_at
            $surveyFormDetail->update([
                'updated_at' => now()
            ]);

            DB::commit();

            return redirect()->route('front.preschool-students.index')
                ->with('success', 'Đã cập nhật thông tin học sinh mầm non thành công.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function export(Request $request)
    {
        // Kiểm tra quyền xuất học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'view')) {
            abort(403, 'Bạn không có quyền xuất danh sách học sinh mầm non.');
        }

        $academicYear = $request->get('academic_year', \App\Models\SurveyForm::currentAcademicYear());
        $search = $request->get('search', '');
        $scheme = $request->get('scheme', 'old');
        $ageFilter = $request->get('age_filter', 'all');

        // Lấy thông tin geo từ request
        $geoData = $request->get('geo', []);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;

        $query = SurveyFormDetail::with([
            'person.ethnicity',
            'person.village',
            'personMamnon',
            'education.block',
            'education.school',
            'surveyForm.headPerson',
            'surveyForm'
        ])->whereNotNull('person_mamnon_id')->notDeleted();

        // Lọc theo năm học - lấy theo academic_year của SurveyFormDetail
        if ($academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        // Lọc theo khu vực - ưu tiên từ cấp nhỏ đến lớn
        if ($wardId) {
            // Ưu tiên cao nhất: lọc theo xã
            $query->whereHas('surveyForm', function($q) use ($wardId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_ward_id', $wardId);
                } else {
                    $q->where('new_ward_id', $wardId);
                }
            });
        } elseif ($districtId) {
            // Ưu tiên thứ 2: lọc theo huyện/quận
            $query->whereHas('surveyForm', function($q) use ($districtId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_district_id', $districtId);
                } else {
                    $q->where('new_district_id', $districtId);
                }
            });
        } elseif ($provinceId) {
            // Ưu tiên thứ 3: lọc theo tỉnh/thành phố
            $query->whereHas('surveyForm', function($q) use ($provinceId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_province_id', $provinceId);
                } else {
                    $q->where('new_province_id', $provinceId);
                }
            });
        }

        if ($villageId) {
            $query->whereHas('surveyForm', function($q) use ($villageId) {
                $q->where('village_id', $villageId);
            });
        }

        // Tìm kiếm
        if ($search) {
            $query->whereHas('person', function($q) use ($search) {
                $q->where('ten', 'like', "%{$search}%")
                  ->orWhere('ho_dem', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$search}%"]);
            });
        }

        // Lọc theo độ tuổi
        if ($ageFilter !== 'all') {
            $query->whereHas('person', function($q) use ($ageFilter, $academicYear) {
                $year = explode('-', $academicYear)[0]; // Lấy năm đầu của năm học
                
                // Tính năm sinh để có tuổi phù hợp: năm học - tuổi
                $targetYear = $year - intval($ageFilter);
                $q->where('dob_year', $targetYear);
            });
        }

        $students = $query->orderBy('id', 'desc')->get();

        // Tạo file Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Tạo sheet ẩn chứa dữ liệu dropdown
        $this->createDropdownDataSheet($spreadsheet);

        // Dòng 1: Nhóm cột với màu nền
        $this->addGroupHeaders($sheet);

        // Dòng 2: Tên cột
        $this->addColumnHeaders($sheet);

        // Dòng 3: Dãy số tăng dần
        $this->addSequenceNumbers($sheet);

        // Dữ liệu
        $row = 4;
        foreach ($students as $index => $student) {
            // Cột TT - căn giữa
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $row)->getFont()->setName('Times New Roman');
            
            $sheet->setCellValue('B' . $row, $student->person->ho_dem ?? '');
            $sheet->getStyle('B' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('C' . $row, $student->person->ten ?? '');
            $sheet->getStyle('C' . $row)->getFont()->setName('Times New Roman');
            
            // Cột ngày - căn giữa và format 2 chữ số
            $day = $student->person->dob_day ?? '';
            if ($day && is_numeric($day)) {
                $day = str_pad($day, 2, '0', STR_PAD_LEFT);
            }
            $sheet->setCellValue('D' . $row, $day);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row)->getFont()->setName('Times New Roman');
            
            // Cột tháng - căn giữa và format 2 chữ số
            $month = $student->person->dob_month ?? '';
            if ($month && is_numeric($month)) {
                $month = str_pad($month, 2, '0', STR_PAD_LEFT);
            }
            $sheet->setCellValue('E' . $row, $month);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $row)->getFont()->setName('Times New Roman');
            
            // Cột năm sinh - căn giữa
            $sheet->setCellValue('F' . $row, $student->person->dob_year ?? '');
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getFont()->setName('Times New Roman');
            
            // Cột Nữ - căn giữa
            $sheet->setCellValue('G' . $row, $student->person->is_female ? 'x' : '');
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $row)->getFont()->setName('Times New Roman');
            
            $sheet->setCellValue('H' . $row, $student->person->ethnicity->name ?? '');
            $sheet->getStyle('H' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('I' . $row, $student->education->block->name ?? '');
            $sheet->getStyle('I' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('J' . $row, $student->education->class_label ?? '');
            $sheet->getStyle('J' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('K' . $row, $student->education->school->code ?? '');
            $sheet->getStyle('K' . $row)->getFont()->setName('Times New Roman');
            
            // Các cột checkbox - căn giữa
            $checkboxColumns = ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
            $checkboxValues = [
                $student->personMamnon->hoc_2_buoi,
                $student->personMamnon->hoc_ban_tru,
                $student->personMamnon->hoc_du_ngay_theo_quy_dinh,
                $student->personMamnon->chuyen_can,
                $student->personMamnon->theo_doi_bd_can_nang,
                $student->personMamnon->sdd_nhe_can,
                $student->personMamnon->theo_doi_bd_chieu_cao,
                $student->personMamnon->sdd_thap_coi,
                $student->personMamnon->dan_toc_chuan_bi_tv,
                $student->personMamnon->hoan_thanh_chuong_trinh_gdmn
            ];
            
            foreach ($checkboxColumns as $i => $col) {
                $sheet->setCellValue($col . $row, $checkboxValues[$i] ? 'x' : '');
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($col . $row)->getFont()->setName('Times New Roman');
            }
            
            $sheet->setCellValue('V' . $row, $student->surveyForm->headPerson->full_name ?? '');
            $sheet->getStyle('V' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('W' . $row, $student->person->addressline ?? '');
            $sheet->getStyle('W' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('X' . $row, $student->person->village->name ?? '');
            $sheet->getStyle('X' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('Y' . $row, $student->surveyForm->so_phieu ?? '');
            $sheet->getStyle('Y' . $row)->getFont()->setName('Times New Roman');
            $sheet->setCellValue('Z' . $row, $student->person->contact_name ?? '');
            $sheet->getStyle('Z' . $row)->getFont()->setName('Times New Roman');
            // Format số điện thoại thành text để giữ nguyên số 0 ở đầu
            $phoneCell = $sheet->getCell('AA' . $row);
            $phoneCell->setValueExplicit($student->person->phone ?? '', DataType::TYPE_STRING);
            $sheet->getStyle('AA' . $row)->getFont()->setName('Times New Roman');
            $row++;
        }

        // Auto size columns
        foreach (range('A', 'AA') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border cho toàn bộ bảng
        $sheet->getStyle('A1:AA' . ($row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Thêm dropdown validation cho các cột
        $this->addDropdownValidation($sheet, $row - 1);

        // Format cột điện thoại (AA) thành text để người dùng có thể chỉnh sửa và import lại
        $sheet->getStyle('AA4:AA' . ($row - 1))->getNumberFormat()->setFormatCode('@');

        // Khóa panes: 3 dòng đầu và cột 1-6 từ dòng 4
        $sheet->freezePane('G4');

        // Đặt chiều cao dòng cho 3 dòng đầu
        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Xuất file
        $filename = 'hoc_sinh_mam_non_' . $academicYear . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        try {
            $writer = new Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
        } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
            return redirect()->back()->with('error', 'Có lỗi xảy ra khi xuất file Excel: ' . $e->getMessage());
        }
    }

    private function createDropdownDataSheet($spreadsheet)
    {
        // Tạo sheet ẩn chứa dữ liệu dropdown
        $dropdownSheet = $spreadsheet->createSheet();
        $dropdownSheet->setTitle('DropdownData');
        $dropdownSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        
        // Dân tộc
        $ethnicities = \App\Models\Ethnicity::pluck('name')->toArray();
        if (count($ethnicities) > 0) {
            foreach ($ethnicities as $i => $name) {
                $dropdownSheet->setCellValue("A" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('EthnicityList', $dropdownSheet, '$A$1:$A$' . count($ethnicities))
            );
            $this->dropdownRanges['ethnicity'] = 'EthnicityList';
        }
        
        // Khối học
        $educationBlocks = \App\Models\EducationBlock::pluck('name')->toArray();
        if (count($educationBlocks) > 0) {
            foreach ($educationBlocks as $i => $name) {
                $dropdownSheet->setCellValue("B" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('EducationBlockList', $dropdownSheet, '$B$1:$B$' . count($educationBlocks))
            );
            $this->dropdownRanges['education_block'] = 'EducationBlockList';
        }
        
        // Dropdown cho checkbox (chỉ có x)
        $dropdownSheet->setCellValue("C1", 'x');
        $spreadsheet->addNamedRange(
            new NamedRange('CheckboxList', $dropdownSheet, '$C$1:$C$1')
        );
        $this->dropdownRanges['checkbox'] = 'CheckboxList';
    }

    private function addDropdownValidation($sheet, $lastRow)
    {
        // Dropdown cho cột Dân tộc (H)
        if (isset($this->dropdownRanges['ethnicity'])) {
            $validation = $sheet->getDataValidation('H4:H' . $lastRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['ethnicity']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn dân tộc từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn dân tộc');
            $validation->setPrompt('Vui lòng chọn dân tộc từ danh sách.');
        }

        // Dropdown cho cột Khối học (I)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('I4:I' . $lastRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn khối học từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn khối học');
            $validation->setPrompt('Vui lòng chọn khối học từ danh sách.');
        }

        // Dropdown cho các cột checkbox
        if (isset($this->dropdownRanges['checkbox'])) {
            $checkboxColumns = ['G', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
            foreach ($checkboxColumns as $col) {
                $validation = $sheet->getDataValidation($col . '4:' . $col . $lastRow);
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setFormula1($this->dropdownRanges['checkbox']);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Lỗi nhập liệu');
                $validation->setError('Vui lòng chọn x nếu có, để trống nếu không.');
                $validation->setPromptTitle('Chọn giá trị');
                $validation->setPrompt('Chọn x nếu có, để trống nếu không.');
            }
        }
    }

    private function addGroupHeaders($sheet)
    {
        // Thông tin học sinh (TT đến Dân tộc) - màu xanh lá, chữ trắng
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'Thông tin học sinh');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'))->setName('Tahoma');
        $sheet->getStyle('A1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4CAF50'); // Xanh lá

        // Thông tin học tập (Khối học đến Hoàn thành CT GDMN) - màu vàng, chữ đen
        $sheet->mergeCells('I1:U1');
        $sheet->setCellValue('I1', 'Thông tin học tập');
        $sheet->getStyle('I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I1')->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('000000'))->setName('Tahoma');
        $sheet->getStyle('I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFEB3B'); // Vàng

        // Thông tin liên hệ (Họ tên chủ hộ đến Điện thoại) - màu xanh lá, chữ trắng
        $sheet->mergeCells('V1:AA1');
        $sheet->setCellValue('V1', 'Thông tin liên hệ');
        $sheet->getStyle('V1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('V1')->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'))->setName('Tahoma');
        $sheet->getStyle('V1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4CAF50'); // Xanh lá
    }

    private function addColumnHeaders($sheet)
    {
        $headers = [
            'A2' => 'TT',
            'B2' => 'Họ đệm',
            'C2' => 'Tên',
            'D2' => 'Ngày',
            'E2' => 'Tháng',
            'F2' => 'Năm sinh',
            'G2' => 'Nữ',
            'H2' => 'Dân tộc',
            'I2' => 'Khối học',
            'J2' => 'Lớp học',
            'K2' => 'Mã trường',
            'L2' => 'Học 2 buổi',
            'M2' => 'Học bán trú',
            'N2' => 'Học đủ ngày',
            'O2' => 'Chuyên cần',
            'P2' => 'Theo dõi cân nặng',
            'Q2' => 'SDD nhẹ cân',
            'R2' => 'Theo dõi chiều cao',
            'S2' => 'SDD thấp còi',
            'T2' => 'Dân tộc chuẩn bị TV',
            'U2' => 'Hoàn thành GDMN',
            'V2' => 'Họ tên chủ hộ',
            'W2' => 'Địa chỉ',
            'X2' => 'Tổ/Thôn/Ấp',
            'Y2' => 'Số phiếu',
            'Z2' => 'Họ tên Cha/Mẹ',
            'AA2' => 'Điện thoại'
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style cho headers với màu theo nhóm và căn giữa
        $sheet->getStyle('A2:AA2')->getFont()->setBold(true)->setSize(10)->setName('Tahoma');
        $sheet->getStyle('A2:AA2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Thông tin học sinh (A2:H2) - màu xanh lá nhạt
        $sheet->getStyle('A2:H2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('C8E6C9'); // Xanh lá nhạt
            
        // Thông tin học tập (I2:U2) - màu vàng nhạt
        $sheet->getStyle('I2:U2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFF9C4'); // Vàng nhạt
            
        // Thông tin liên hệ (V2:AA2) - màu xanh lá nhạt
        $sheet->getStyle('V2:AA2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('C8E6C9'); // Xanh lá nhạt
        
        // Format header cột điện thoại thành text
        $sheet->getStyle('AA2')->getNumberFormat()->setFormatCode('@');
    }

    private function addSequenceNumbers($sheet)
    {
        $sequenceNumbers = [
            'A3' => '1', 'B3' => '2', 'C3' => '3', 'D3' => '4', 'E3' => '5', 'F3' => '6', 'G3' => '7', 'H3' => '8',
            'I3' => '9', 'J3' => '10', 'K3' => '11', 'L3' => '12', 'M3' => '13', 'N3' => '14', 'O3' => '15', 'P3' => '16',
            'Q3' => '17', 'R3' => '18', 'S3' => '19', 'T3' => '20', 'U3' => '21', 'V3' => '22', 'W3' => '23', 'X3' => '24',
            'Y3' => '25', 'Z3' => '26', 'AA3' => '27'
        ];

        foreach ($sequenceNumbers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style cho sequence numbers
        $sheet->getStyle('A3:AA3')->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'))->setName('Tahoma');
        $sheet->getStyle('A3:AA3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3:AA3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('2196F3'); // Xanh dương
    }

    public function bulkDelete(Request $request)
    {
        // Kiểm tra quyền xóa học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa học sinh mầm non.'
            ], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:survey_form_details,id'
        ]);

        try {
            DB::beginTransaction();

                    $deletedCount = SurveyFormDetail::whereIn('id', $request->ids)
                        ->whereNotNull('person_mamnon_id')
                        ->where('is_deleted', false)
                        ->update([
                            'is_deleted' => true,
                            'updated_at' => now()
                        ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$deletedCount} học sinh mầm non thành công."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateStudyInfo(Request $request)
    {
        // Kiểm tra quyền bổ sung thông tin học tập hàng loạt
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền bổ sung thông tin học tập hàng loạt.'
            ], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:survey_form_details,id'
        ]);

        try {
            DB::beginTransaction();

            $updatedCount = 0;
            $ethnicMinorityCount = 0;
            $surveyFormDetails = SurveyFormDetail::whereIn('id', $request->ids)
                ->whereNotNull('person_mamnon_id')
                ->where('is_deleted', false)
                ->with(['personMamnon', 'education', 'person.ethnicity'])
                ->get();

            foreach ($surveyFormDetails as $surveyFormDetail) {
                // Kiểm tra xem học sinh có phải dân tộc thiểu số không (khác dân tộc Kinh)
                $isEthnicMinority = false;
                if ($surveyFormDetail->person && $surveyFormDetail->person->ethnicity) {
                    $ethnicityName = trim($surveyFormDetail->person->ethnicity->name);
                    // So sánh không phân biệt hoa thường để đảm bảo chính xác
                    $isEthnicMinority = strcasecmp($ethnicityName, 'Kinh') !== 0;
                }

                $updateData = [
                    'hoc_ban_tru' => true,
                    'hoc_du_ngay_theo_quy_dinh' => true,
                    'chuyen_can' => true,
                    'hoc_2_buoi' => true,
                ];

                // Nếu là dân tộc thiểu số thì tích thêm cột dan_toc_chuan_bi_tv
                if ($isEthnicMinority) {
                    $updateData['dan_toc_chuan_bi_tv'] = true;
                    $ethnicMinorityCount++;
                }

                $surveyFormDetail->personMamnon->update($updateData);
                $updatedCount++;
            }

            // Cập nhật editor_id cho tất cả survey_mamnon
            SurveyFormDetail::whereIn('id', $request->ids)
                ->whereNotNull('person_mamnon_id')
                ->where('is_deleted', false)
                ->update([
                    'updated_at' => now()
                ]);

            DB::commit();
            
            $message = "Đã bổ sung thông tin học tập cho {$updatedCount} học sinh mầm non thành công.";
            if ($ethnicMinorityCount > 0) {
                $message .= " Trong đó có {$ethnicMinorityCount} học sinh dân tộc thiểu số được tích thêm 'Dân tộc chuẩn bị TV'.";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdateGDMN(Request $request)
    {
        // Kiểm tra quyền bổ sung hoàn thành CT GDMN hàng loạt
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền bổ sung hoàn thành CT GDMN hàng loạt.'
            ], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:survey_form_details,id'
        ]);

        try {
            DB::beginTransaction();

            $updatedCount = 0;
            $surveyFormDetails = SurveyFormDetail::whereIn('id', $request->ids)
                ->whereNotNull('person_mamnon_id')
                ->where('is_deleted', false)
                ->with('personMamnon')
                ->get();

            foreach ($surveyFormDetails as $surveyFormDetail) {
                $surveyFormDetail->personMamnon->update([
                    'hoan_thanh_chuong_trinh_gdmn' => true,
                ]);
                $updatedCount++;
            }

            // Cập nhật editor_id cho tất cả survey_mamnon
            SurveyFormDetail::whereIn('id', $request->ids)
                ->whereNotNull('person_mamnon_id')
                ->where('is_deleted', false)
                ->update([
                    'updated_at' => now()
                ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Đã bổ sung hoàn thành chương trình GDMN cho {$updatedCount} học sinh mầm non thành công."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    public function import(Request $request)
    {
        // Kiểm tra quyền nhập Excel học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'create')) {
            abort(403, 'Bạn không có quyền nhập Excel học sinh mầm non.');
        }

        return view('front.preschool-students.import');
    }

    public function importSave(Request $request)
    {
        // Kiểm tra quyền nhập Excel học sinh mầm non
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_mam_non', 'create')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền nhập Excel học sinh mầm non.'
            ], 403);
        }

        $request->validate([
            'academic_year' => 'required|string',
            'geo' => 'required|array',
            'rows' => 'required|array'
        ]);

        try {
            $academicYear = $request->input('academic_year');
            $geoData = $request->input('geo');
            $rows = $request->input('rows');

            $updatedCount = 0;
            $errors = [];

            foreach ($rows as $index => $data) {
                try {
                    if (empty($data['ho_dem']) && empty($data['ten'])) {
                        continue; // Bỏ qua dòng trống
                    }

                    // Tìm học sinh tương ứng
                    $student = $this->findStudentByData($data, $academicYear, $geoData);
                    
                    if (!$student) {
                        $errors[] = [
                            'row' => $index + 1,
                            'ho_dem' => $data['ho_dem'] ?? '',
                            'ten' => $data['ten'] ?? '',
                            'message' => "Không tìm thấy học sinh: {$data['ho_dem']} {$data['ten']} - Số phiếu: {$data['so_phieu']}"
                        ];
                        continue;
                    }

                    // Cập nhật thông tin person_mamnon
                    $this->updatePersonMamnon($student->personMamnon, $data);
                    
                    // Cập nhật thông tin liên lạc trong bảng person
                    $this->updatePersonContact($student->person, $data);
                    
                    // Cập nhật thông tin học tập trong bảng PersonEducation
                    // Log::info('Bắt đầu cập nhật PersonEducation', ['student_id' => $student->id, 'data' => $data]);
                    $this->updatePersonEducation($student, $data);
                    // Log::info('Hoàn thành cập nhật PersonEducation');
                    
                    // Cập nhật updated_at
                    $student->update(['updated_at' => now()]);
                    
                    $updatedCount++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'ho_dem' => $data['ho_dem'] ?? '',
                        'ten' => $data['ten'] ?? '',
                        'message' => "Lỗi xử lý dòng " . ($index + 1) . ": " . $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'total_rows' => count($rows),
                'updated_count' => $updatedCount,
                'errors' => $errors,
                'message' => "Đã cập nhật thành công {$updatedCount} học sinh mầm non"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi nhập Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    private function readExcelRow($worksheet, $row)
    {
        return [
            'ho_dem' => trim($worksheet->getCell('B' . $row)->getValue() ?? ''),
            'ten' => trim($worksheet->getCell('C' . $row)->getValue() ?? ''),
            'dob_day' => $worksheet->getCell('D' . $row)->getValue(),
            'dob_month' => $worksheet->getCell('E' . $row)->getValue(),
            'dob_year' => $worksheet->getCell('F' . $row)->getValue(),
            'is_female' => $worksheet->getCell('G' . $row)->getValue() === 'x',
            'so_phieu' => trim($worksheet->getCell('Y' . $row)->getValue() ?? ''),
            'hoc_2_buoi' => $worksheet->getCell('L' . $row)->getValue() === 'x',
            'hoc_ban_tru' => $worksheet->getCell('M' . $row)->getValue() === 'x',
            'hoc_du_ngay_theo_quy_dinh' => $worksheet->getCell('N' . $row)->getValue() === 'x',
            'chuyen_can' => $worksheet->getCell('O' . $row)->getValue() === 'x',
            'theo_doi_bd_can_nang' => $worksheet->getCell('P' . $row)->getValue() === 'x',
            'sdd_nhe_can' => $worksheet->getCell('Q' . $row)->getValue() === 'x',
            'theo_doi_bd_chieu_cao' => $worksheet->getCell('R' . $row)->getValue() === 'x',
            'sdd_thap_coi' => $worksheet->getCell('S' . $row)->getValue() === 'x',
            'dan_toc_chuan_bi_tv' => $worksheet->getCell('T' . $row)->getValue() === 'x',
            'hoan_thanh_chuong_trinh_gdmn' => $worksheet->getCell('U' . $row)->getValue() === 'x',
        ];
    }

    private function findStudentByData($data, $academicYear, $geoData)
    {
        $query = SurveyFormDetail::with(['person', 'personMamnon', 'surveyForm'])
            ->whereNotNull('person_mamnon_id')
            ->notDeleted();

        // Lọc theo năm học - lấy theo academic_year của SurveyFormDetail
        $query->where('academic_year', $academicYear);

        // Lọc theo khu vực nếu có - ưu tiên từ cấp nhỏ đến lớn
        $scheme = $geoData['scheme'] ?? 'old';
        
        if (!empty($geoData['ward_id'])) {
            // Ưu tiên cao nhất: lọc theo xã
            $query->whereHas('surveyForm', function($q) use ($geoData, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_ward_id', $geoData['ward_id']);
                } else {
                    $q->where('new_ward_id', $geoData['ward_id']);
                }
            });
        } elseif (!empty($geoData['district_id'])) {
            // Ưu tiên thứ 2: lọc theo huyện/quận
            $query->whereHas('surveyForm', function($q) use ($geoData, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_district_id', $geoData['district_id']);
                } else {
                    $q->where('new_district_id', $geoData['district_id']);
                }
            });
        } elseif (!empty($geoData['province_id'])) {
            // Ưu tiên thứ 3: lọc theo tỉnh/thành phố
            $query->whereHas('surveyForm', function($q) use ($geoData, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_province_id', $geoData['province_id']);
                } else {
                    $q->where('new_province_id', $geoData['province_id']);
                }
            });
        }

        if (!empty($geoData['village_id'])) {
            $query->whereHas('surveyForm', function($q) use ($geoData) {
                $q->where('village_id', $geoData['village_id']);
            });
        }

        // Tìm kiếm theo thông tin cá nhân
        $query->whereHas('person', function($q) use ($data) {
            $q->where('ho_dem', $data['ho_dem'])
              ->where('ten', $data['ten'])
              ->where('dob_day', $data['dob_day'])
              ->where('dob_month', $data['dob_month'])
              ->where('dob_year', $data['dob_year']);
        });

        // Tìm kiếm theo số phiếu nếu có
        if (!empty($data['so_phieu'])) {
            $query->whereHas('surveyForm', function($q) use ($data) {
                $q->where('so_phieu', $data['so_phieu']);
            });
        }

        return $query->first();
    }

    private function updatePersonMamnon($personMamnon, $data)
    {
        $personMamnon->update([
            'hoc_2_buoi' => $data['hoc_2_buoi'] ?? false,
            'hoc_ban_tru' => $data['hoc_ban_tru'] ?? false,
            'hoc_du_ngay_theo_quy_dinh' => $data['hoc_du_ngay_theo_quy_dinh'] ?? false,
            'chuyen_can' => $data['chuyen_can'] ?? false,
            'theo_doi_bd_can_nang' => $data['theo_doi_bd_can_nang'] ?? false,
            'sdd_nhe_can' => $data['sdd_nhe_can'] ?? false,
            'theo_doi_bd_chieu_cao' => $data['theo_doi_bd_chieu_cao'] ?? false,
            'sdd_thap_coi' => $data['sdd_thap_coi'] ?? false,
            'dan_toc_chuan_bi_tv' => $data['dan_toc_chuan_bi_tv'] ?? false,
            'hoan_thanh_chuong_trinh_gdmn' => $data['hoan_thanh_chuong_trinh_gdmn'] ?? false,
        ]);
    }

    private function updatePersonContact($person, $data)
    {
        $updateData = [
            'contact_name' => $data['contact_name'] ?? $person->contact_name,
            'phone' => $data['phone'] ?? $person->phone,
            'addressline' => $data['addressline'] ?? $person->addressline,
            'head_person_name' => $data['head_person_name'] ?? $person->head_person_name,
            'village_name' => $data['village_name'] ?? $person->village_name,
            'ethnicity' => $data['ethnicity'] ?? $person->ethnicity,
            'is_female' => $data['is_female'] ?? $person->is_female,
            'ho_dem' => $data['ho_dem'] ?? $person->ho_dem,
            'ten' => $data['ten'] ?? $person->ten,
            'so_phieu' => $data['so_phieu'] ?? $person->so_phieu,
        ];
        
        // Cập nhật ngày sinh nếu có đầy đủ thông tin
        if (!empty($data['dob_day']) && !empty($data['dob_month']) && !empty($data['dob_year'])) {
            $updateData['dob_day'] = $data['dob_day'];
            $updateData['dob_month'] = $data['dob_month'];
            $updateData['dob_year'] = $data['dob_year'];
        }
        
        $person->update($updateData);
    }

    private function updatePersonEducation($surveyFormDetail, $data)
    {
        // Cập nhật thông tin học tập (khối học, lớp học, mã trường)
        if ($surveyFormDetail->education) {
            // Đã có PersonEducation, cập nhật
            $updateData = [
                'class_label' => $data['education_grade_name'] ?? $surveyFormDetail->education->class_label,
            ];
            
            // Cập nhật khối học nếu có
            if (!empty($data['education_block_name'])) {
                // Log::info('Tìm kiếm khối học', ['name' => $data['education_block_name'], 'type' => gettype($data['education_block_name'])]);
                
                // Debug: Tìm tất cả khối học có tên giống
                $allMatchingBlocks = EducationBlock::where('name', $data['education_block_name'])->get();
                // Log::info('Tất cả khối học có tên giống', ['blocks' => $allMatchingBlocks->pluck('name', 'id')->toArray()]);
                
                // Tìm kiếm theo tên chính xác (không dùng LIKE)
                // Convert về string để đảm bảo tìm kiếm chính xác
                $searchName = (string) $data['education_block_name'];
                
                // Tìm kiếm chính xác - chỉ lấy khối học có tên đúng bằng searchName
                // Sử dụng whereRaw để tránh vấn đề với collation
                $educationBlock = EducationBlock::whereRaw('BINARY name = ?', [$searchName])->first();
                
                // Nếu không tìm thấy với BINARY, thử cách thông thường
                if (!$educationBlock) {
                    $educationBlock = EducationBlock::where('name', $searchName)->first();
                }
                
                if ($educationBlock) {
                    // Log::info('Tìm thấy khối học', ['id' => $educationBlock->id, 'name' => $educationBlock->name]);
                    $updateData['education_block_id'] = $educationBlock->id;
                } else {
                    Log::warning('Không tìm thấy khối học', ['name' => $data['education_block_name']]);
                }
            }
            
            // Cập nhật mã trường nếu có
            if (!empty($data['school_code'])) {
                $school = School::where('code', $data['school_code'])->first();
                if ($school) {
                    $updateData['school_id'] = $school->id;
                }
            }
            
            // Log::info('Cập nhật PersonEducation', ['updateData' => $updateData]);
            $surveyFormDetail->education->update($updateData);
            // Log::info('Cập nhật PersonEducation thành công');
        } else {
            // Chưa có PersonEducation, tạo mới
            $createData = [
                'person_id' => $surveyFormDetail->person_id,
                'academic_year' => $surveyFormDetail->surveyForm->academic_year,
                'class_label' => $data['education_grade_name'] ?? '',
            ];
            
            // Thêm khối học nếu có
            if (!empty($data['education_block_name'])) {
                // Log::info('Tạo mới - Tìm kiếm khối học', ['name' => $data['education_block_name']]);
                
                // Tìm kiếm theo tên chính xác (không dùng LIKE)
                $educationBlock = EducationBlock::where('name', $data['education_block_name'])->first();
                
                if ($educationBlock) {
                    // Log::info('Tạo mới - Tìm thấy khối học', ['id' => $educationBlock->id, 'name' => $educationBlock->name]);
                    $createData['education_block_id'] = $educationBlock->id;
                } else {
                    Log::warning('Tạo mới - Không tìm thấy khối học', ['name' => $data['education_block_name']]);
                }
            }
            
            // Thêm mã trường nếu có
            if (!empty($data['school_code'])) {
                $school = School::where('code', $data['school_code'])->first();
                if ($school) {
                    $createData['school_id'] = $school->id;
                }
            }
            
            // Log::info('Tạo mới PersonEducation', ['createData' => $createData]);
            $personEducation = PersonEducation::create($createData);
            $surveyFormDetail->update(['person_education_id' => $personEducation->id]);
            // Log::info('Tạo mới PersonEducation thành công', ['id' => $personEducation->id]);
        }
    }

    public function searchSchools(Request $request)
    {
        $query = $request->get('q', '');
        $code = $request->get('code', '');
        
        // Log::info('School search request', ['query' => $query, 'code' => $code]);
        
        // Debug: Kiểm tra tổng số trường
        $totalSchools = \App\Models\School::count();
        // Log::info('Total schools in database', ['count' => $totalSchools]);
        
        $schools = \App\Models\School::query();
        
        if ($code) {
            // Tìm kiếm theo mã trường (chính xác hoặc gần đúng)
            $schools->where('code', 'like', "%{$code}%");
            
            // Debug: Kiểm tra query SQL
            $sql = $schools->toSql();
            $bindings = $schools->getBindings();
            // Log::info('SQL Query', ['sql' => $sql, 'bindings' => $bindings]);
            
            // Debug: Thử tìm kiếm trực tiếp
            $directSearch = \App\Models\School::where('code', $code)->first();
            // Log::info('Direct search result', ['found' => $directSearch ? $directSearch->toArray() : 'null']);
            
            // Debug: Thử tìm kiếm với LIKE
            $likeSearch = \App\Models\School::where('code', 'like', "%{$code}%")->get();
            // Log::info('Like search result', ['count' => $likeSearch->count(), 'results' => $likeSearch->toArray()]);
            
        } else if ($query) {
            // Tìm kiếm theo tên trường
            $schools->where('name', 'like', "%{$query}%");
        } else {
            // Nếu không có tham số nào, trả về danh sách rỗng
            return response()->json([]);
        }
        
        $results = $schools->limit(10)->get(['id', 'name', 'code']);
        
        // Log::info('School search results', ['count' => $results->count(), 'results' => $results->toArray()]);
        
        return response()->json($results);
    }

}
