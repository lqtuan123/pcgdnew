<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\DisabilityCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
use App\Services\SurveyFilterService; // <-- THÊM
use App\Services\GeoService; // <-- THÊM
class SurveyExportController extends Controller
{
    private $dropdownRanges = [];

    public function __construct(
        private SurveyFilterService $filterService // <-- THÊM: inject service
    ) {}

    public function export(Request $request,GeoService $geo)
    {
        // Tăng thời gian thực thi và bộ nhớ
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        // Quyền
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Bạn cần đăng nhập để xuất danh sách đối tượng điều tra.');
        }

        // ===== DÙNG SERVICE ĐỂ LẤY BỘ LỌC DÙNG CHUNG =====
        $ctx   = $this->filterService->buildContext($request, $user);
        $query = $this->filterService->filteredQuery($ctx);
// dd($ctx);
        // Lấy toàn bộ dữ liệu theo bộ lọc (không phân trang)
        $rows = $query->get();
        // dd($rows);
        // Map mã -> id khuyết tật (phần bên dưới đang cần)
        $disCatIds = DisabilityCategory::whereIn('code', [
            'VAN_DONG','NGHE_NOI','NHIN','THAN_KINH','TRI_TUE','HOC_TAP','TU_KY','KHAC'
        ])->pluck('id','code')->all();

        // ===== Excel =====
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh sách đối tượng điều tra');

        $this->createDropdownDataSheet($spreadsheet);
        $this->createHeaders($sheet);
        $this->addDataRowsOptimized($sheet, $rows, $disCatIds);
        $this->formatSheet($sheet);

        try {
            $this->addDropdownValidation($sheet);
        } catch (\Exception $e) {
            \Log::warning('Dropdown validation error: ' . $e->getMessage());
        }
        $provincename = $ctx['geoIds']['province']?$geo->geoNameFromId($ctx['geoIds']['province']):'';
        $wardname = $ctx['geoIds']['ward']?$geo->geoNameFromId($ctx['geoIds']['ward']):'';
        $geo_name = "";
        if($provincename != '')
        {
            $geo_name .= $provincename;
        }
        if($wardname != '')
        {
            $geo_name .='_'. $wardname;
        }
        // Dùng năm từ context để đồng bộ với index
        $filename = 'ds_dtdt_'.$geo_name.'_' . ($ctx['year'] ?? 'all') . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        try {
            $writer = new Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi khi xuất file: ' . $e->getMessage());
        }
    }

    private function createHeaders($sheet)
    {
        // Dòng 1: Nhóm tiêu đề chính
        $groupHeaders = [
            'Thông tin đối tượng',    // Cột 1-11
            'Thông tin nơi đang ở',   // Cột 12-17  
            'Thông tin học tập',      // Cột 18-32
            'Xóa mù chữ',            // Cột 33-35
            'Khuyết tật',            // Cột 36-45
            'Hoàn cảnh',             // Cột 46-47
            'Thông tin liên lạc'     // Cột 48-51
        ];
        
        // Dòng 2: Nhóm tiêu đề phụ và tên cột chi tiết
        $columnHeaders = [
            'TT',
            'Họ đệm',
            'Tên',
            'Ngày',
            'Tháng',
            'Năm sinh',
            'Số định danh cá nhân',
            'Nữ',
            'Dân tộc',
            'Tôn giáo',
            'Diện ưu tiên',
            'Họ đệm',
            'Tên',
            'Địa chỉ: Số nhà, tên đường, tổ (nếu có)',
            'Số phiếu',
            'Diện cư trú',
            'Tình trạng cư trú',
            'Khối học',
            'Lớp học',
            'Phường/Xã đang học',
            'Tên trường đang học',
            'Mã Trường',
            'Bậc tốt nghiệp',
            'Bổ túc',
            'Năm tốt nghiệp',
            'Bậc TN Nghề',
            'Số bằng tốt nghiệp',
            'Năm TN nghề',
            'Lớp',
            'Năm',
            'Lớp',
            'Năm',
            'Đang học lớp',
            'Hoàn thành lớp',
            'Tái mù chữ mức',
            'Khuyết tật vận động',
            'Khuyết tật nghe, nói',
            'Khuyết tật nhìn',
            'Khuyết tật thần kinh, tâm thần',
            'Khuyết tật trí tuệ',
            'Khuyết tật học tập',
            'Tự kỷ',
            'Khuyết tật khác',
            'Có chứng nhận khuyết tật',
            'Khả năng học tập',
            'Hoàn cảnh đặc biệt',
            'Chi tiết hoàn cảnh đặc biệt',
            'Quan hệ với chủ hộ',
            'Họ tên Cha hoặc Mẹ',
            'Điện thoại',
            'Ghi chú','Tỉnh TP','Phường xã','Thôn xóm'
        ];

        // Dòng 3: Số thứ tự từ 1 đến 51
        $sequenceNumbers = range(1, 54);

        // Tạo nhóm tiêu đề chính (dòng 1)
        $col = 1;
        $currentGroup = 0;
        $groupStartCol = 1;
        
        foreach ($columnHeaders as $index => $header) {
            $colIndex = $index + 1;
            
            // Xác định nhóm hiện tại
            if ($colIndex <= 11) {
                $groupIndex = 0; // Thông tin đối tượng
            } elseif ($colIndex <= 17) {
                $groupIndex = 1; // Thông tin nơi đang ở
            } elseif ($colIndex <= 32) {
                $groupIndex = 2; // Thông tin học tập
            } elseif ($colIndex <= 35) {
                $groupIndex = 3; // Xóa mù chữ
            } elseif ($colIndex <= 45) {
                $groupIndex = 4; // Khuyết tật
            } elseif ($colIndex <= 47) {
                $groupIndex = 5; // Hoàn cảnh
            } else {
                $groupIndex = 6; // Thông tin liên lạc
            }
            
            // Nếu chuyển sang nhóm mới, ghi nhóm cũ
            if ($groupIndex !== $currentGroup) {
                if ($currentGroup >= 0) {
                    $groupEndCol = $colIndex - 1;
                    $sheet->mergeCellsByColumnAndRow($groupStartCol, 1, $groupEndCol, 1);
                    $sheet->setCellValueByColumnAndRow($groupStartCol, 1, $groupHeaders[$currentGroup]);
                }
                $currentGroup = $groupIndex;
                $groupStartCol = $colIndex;
            }
        }
        
        // Ghi nhóm cuối cùng
        $sheet->mergeCellsByColumnAndRow($groupStartCol, 1, 54, 1);
        $sheet->setCellValueByColumnAndRow($groupStartCol, 1, $groupHeaders[$currentGroup]);

        // Tạo nhóm tiêu đề phụ và tên cột chi tiết (dòng 2)
        $col = 1;
        foreach ($columnHeaders as $index => $header) {
            $colIndex = $index + 1;
            
            // Các cột cần nhóm phụ
            if ($colIndex == 12 || $colIndex == 13) {
                // Chủ hộ - Họ đệm và Chủ hộ - Tên
                if ($colIndex == 12) {
                    $sheet->mergeCellsByColumnAndRow(12, 2, 13, 2);
                    $sheet->setCellValueByColumnAndRow(12, 2, 'Chủ hộ');
                }
                // Không ghi gì cho cột 13 vì đã merge
            } elseif ($colIndex == 29 || $colIndex == 30) {
                // Học xong - Lớp và Học xong - Năm
                if ($colIndex == 29) {
                    $sheet->mergeCellsByColumnAndRow(29, 2, 30, 2);
                    $sheet->setCellValueByColumnAndRow(29, 2, 'Học xong');
                }
                // Không ghi gì cho cột 30 vì đã merge
            } elseif ($colIndex == 31 || $colIndex == 32) {
                // Bỏ học - Lớp và Bỏ học - Năm
                if ($colIndex == 31) {
                    $sheet->mergeCellsByColumnAndRow(31, 2, 32, 2);
                    $sheet->setCellValueByColumnAndRow(31, 2, 'Bỏ học');
                }
                // Không ghi gì cho cột 32 vì đã merge
            } else {
                // Các cột khác merge dòng 2 và 3, ghi tên cột
                $sheet->mergeCellsByColumnAndRow($col, 2, $col, 3);
                $sheet->setCellValueByColumnAndRow($col, 2, $header);
            }
            $col++;
        }

        // Tạo tên cột chi tiết cho các cột có nhóm phụ (dòng 3)
        $col = 1;
        foreach ($columnHeaders as $index => $header) {
            $colIndex = $index + 1;
            
            // Các cột có nhóm phụ ghi tên chi tiết ở dòng 3
            if ($colIndex == 12 || $colIndex == 13 || 
                $colIndex == 29 || $colIndex == 30 || 
                $colIndex == 31 || $colIndex == 32) {
                $sheet->setCellValueByColumnAndRow($col, 3, $header);
            }
            // Các cột khác đã merge ở dòng 2-3, không cần ghi gì ở dòng 3
            $col++;
        }

        // Tạo số thứ tự (dòng 4)
        $col = 1;
        foreach ($sequenceNumbers as $number) {
            $sheet->setCellValueByColumnAndRow($col++, 4, $number);
        }
    }

    private function addDataRowsOptimized($sheet, $rows, $disCatIds)
    {
        $row = 5; // Bắt đầu từ dòng 5 vì có 4 dòng header
        $batchSize = 50; // Giảm batch size để tránh lỗi memory với dữ liệu lớn
        
        // Lấy tất cả ID cần thiết
        $detailIds = $rows->pluck('id')->toArray();
        $personIds = $rows->pluck('person_id')->unique()->toArray();
        $surveyFormIds = $rows->pluck('survey_form_id')->unique()->toArray();
        
        // Load dữ liệu liên quan một lần
        $persons = DB::table('people')
            ->whereIn('id', $personIds)
            ->get()
            ->keyBy('id');
            
        $ethnicities = DB::table('ethnicities')->get()->keyBy('id');
        $religions = DB::table('religions')->get()->keyBy('id');
        $residenceTypes = DB::table('residence_types')->get()->keyBy('id');
        $residenceStatuses = DB::table('residence_statuses')->get()->keyBy('id');
        
        $surveyForms = DB::table('survey_forms')
            ->whereIn('id', $surveyFormIds)
            ->get()
            ->keyBy('id');
            
        $headPersons = DB::table('people')
            ->whereIn('id', $surveyForms->pluck('head_person_id')->filter()->unique())
            ->get()
            ->keyBy('id');
            
        $geoUnits = DB::table('geo_units')
            ->whereIn('id', collect([
                $surveyForms->pluck('old_province_id'),
                $surveyForms->pluck('old_district_id'),
                $surveyForms->pluck('old_ward_id'),
                $surveyForms->pluck('new_province_id'),
                $surveyForms->pluck('new_ward_id'),
                $surveyForms->pluck('village_id')
            ])->flatten()->filter()->unique())
            ->get()
            ->keyBy('id');
            
        $educations = DB::table('person_educations')
            ->whereIn('person_id', $personIds)
            ->get()
            ->groupBy('person_id');
            
        $educationBlocks = DB::table('education_blocks')->get()->keyBy('id');
        $graduationLevels = DB::table('graduation_levels')->get()->keyBy('id');
        $vocationalLevels = DB::table('vocational_levels')->get()->keyBy('id');
        $schools = DB::table('schools')->get()->keyBy('id');
        
        // Kiểm tra bảng person_literacies có tồn tại không
        $literacies = collect();
        if (DB::getSchemaBuilder()->hasTable('person_literacies')) {
            $literacies = DB::table('person_literacies')
                ->whereIn('person_id', $personIds)
                ->get()
                ->keyBy('person_id');
        }
            
        // Kiểm tra bảng person_disabilities có tồn tại không
        $personDisabilities = collect();
        if (DB::getSchemaBuilder()->hasTable('person_disabilities')) {
            $personDisabilities = DB::table('person_disabilities')
                ->whereIn('person_id', $personIds)
                ->get()
                ->keyBy('person_id');
        }
            
        // Kiểm tra bảng person_special_circumstances có tồn tại không
        $specialCircumstances = collect();
        $specialCircumstanceTypes = collect();
        if (DB::getSchemaBuilder()->hasTable('person_special_circumstances')) {
            $specialCircumstances = DB::table('person_special_circumstances')
                ->whereIn('person_id', $personIds)
                ->get()
                ->keyBy('person_id');
        }
        if (DB::getSchemaBuilder()->hasTable('special_circumstances')) {
            $specialCircumstanceTypes = DB::table('special_circumstances')->get()->keyBy('id');
        }
        
        // Kiểm tra bảng household_members có tồn tại không
        $householdMembers = collect();
        if (DB::getSchemaBuilder()->hasTable('household_members')) {
            $householdMembers = DB::table('household_members')
                ->whereIn('person_id', $personIds)
                ->get()
                ->groupBy('person_id');
        }
        
        // Function helper
        $tick = fn($v) => $v ? 'x' : '';
        
        foreach ($rows as $index => $d) {
            $p = $persons->get($d->person_id);
            $sf = $surveyForms->get($d->survey_form_id);
            $edu = $educations->get($d->person_id)?->first();
            $lit = $literacies->get($d->person_id);
            $disability = $personDisabilities->get($d->person_id);
            $specialCircumstance = $specialCircumstances->get($d->person_id);
            
            // Xử lý địa chỉ
            $oldProvinceName = $geoUnits->get($sf->old_province_id ?? 0)?->name;
            $oldDistrictName = $geoUnits->get($sf->old_district_id ?? 0)?->name;
            $oldWardName = $geoUnits->get($sf->old_ward_id ?? 0)?->name;
            $newProvinceName = $geoUnits->get($sf->new_province_id ?? 0)?->name;
            $newWardName = $geoUnits->get($sf->new_ward_id ?? 0)?->name;
            $village = $geoUnits->get($sf->village_id ?? 0)?->name;
            
            $residenceStatusName = $residenceStatuses->get($p->residence_status_id ?? 0)?->name;
            $residenceTypeName = $residenceTypes->get($p->residence_type_id ?? 0)?->name;
            
            // Xử lý Phường/xã đang học
            $studyingWardName = null;
            if ($edu && $edu->school_id) {
                $school = $schools->get($edu->school_id);
                if ($school && $school->ward_code_new) {
                    $studyingWardName = $geoUnits->where('code', $school->ward_code_new)->first()?->name;
                }
            }
            
            // Function helper cho khuyết tật
            $hasDis = function ($code) use ($disability, $disCatIds) {
                if (!$disability || !is_object($disability)) return false;
                $id = $disCatIds[$code] ?? null;
                if (!$id) return false;
                $ids = json_decode($disability->disability_ids ?? '[]', true) ?? [];
                return in_array($id, $ids, true);
            };
            
            // Lấy role trong hộ
            $role = null;
            if ($sf && $sf->household_id && $householdMembers->isNotEmpty()) {
                $membership = $householdMembers->get($d->person_id)
                    ?->firstWhere('household_id', $sf->household_id);
                $role = $membership?->role;
            }

            // Dữ liệu theo thứ tự yêu cầu
            $data = [
                $index + 1, // TT
                $p?->ho_dem, // Họ đệm
                $p?->ten, // Tên
                $p?->dob_day, // Ngày
                $p?->dob_month, // Tháng
                $p?->dob_year, // Năm sinh
                $p?->identity_number, // Số định danh cá nhân
                $tick($p?->is_female), // Nữ
                $ethnicities->get($p->ethnicity_id ?? 0)?->name, // Dân tộc
                $religions->get($p->religion_id ?? 0)?->name, // Tôn giáo
                $specialCircumstanceTypes->get($specialCircumstance?->sc_id ?? 0)?->name, // Diện ưu tiên
                $headPersons->get($sf->head_person_id ?? 0)?->ho_dem, // Chủ hộ - Họ đệm
                $headPersons->get($sf->head_person_id ?? 0)?->ten, // Chủ hộ - Tên
                $p?->addressline, // Địa chỉ
                $sf?->so_phieu, // Số phiếu
                $residenceTypeName, // Diện cư trú
                $residenceStatusName, // Tình trạng cư trú
                $educationBlocks->get($edu->education_block_id ?? 0)?->name, // Khối học
                $edu?->class_label, // Lớp học
                $studyingWardName, // Phường/Xã đang học
                $schools->get($edu->school_id ?? 0)?->name, // Tên trường đang học
                $schools->get($edu->school_id ?? 0)?->code, // Mã Trường
                $graduationLevels->get($edu->graduation_level_id ?? 0)?->name, // Bậc tốt nghiệp
                $tick($edu?->is_bo_tuc), // Bổ túc
                $edu?->graduation_year, // Năm tốt nghiệp
                $vocationalLevels->get($edu->vocational_level_id ?? 0)?->name, // Bậc TN Nghề
                $edu?->graduation_certificate_no, // Số bằng tốt nghiệp
                $edu?->vocational_grad_year, // Năm TN nghề
                $edu?->hoc_xong_lop, // Học xong - Lớp
                $edu?->hoc_xong_nam, // Học xong - Năm
                $edu?->bo_hoc_lop, // Bỏ học - Lớp
                $edu?->bo_hoc_nam, // Bỏ học - Năm
                $lit?->dang_hoc_lop, // Đang học lớp
                $lit?->hoan_thanh_lop, // Hoàn thành lớp
                $lit?->tai_mu_chu_muc, // Tái mù chữ mức
                $tick($hasDis('VAN_DONG')), // Khuyết tật vận động
                $tick($hasDis('NGHE_NOI')), // Khuyết tật nghe, nói
                $tick($hasDis('NHIN')), // Khuyết tật nhìn
                $tick($hasDis('THAN_KINH')), // Khuyết tật thần kinh, tâm thần
                $tick($hasDis('TRI_TUE')), // Khuyết tật trí tuệ
                $tick($hasDis('HOC_TAP')), // Khuyết tật học tập
                $tick($hasDis('TU_KY')), // Tự kỷ
                $tick($hasDis('KHAC')), // Khuyết tật khác
                $tick($disability?->has_certificate), // Có chứng nhận khuyết tật
                $tick($disability?->can_learn), // Khả năng học tập
                $specialCircumstanceTypes->get($specialCircumstance?->sc_id ?? 0)?->name, // Hoàn cảnh đặc biệt
                $specialCircumstance?->chi_tiet, // Chi tiết hoàn cảnh đặc biệt
                $role, // Quan hệ với chủ hộ
                $p->contact_name, // Họ tên Cha hoặc Mẹ
                $p->phone, // Điện thoại
                $d->note, // Ghi chú
                 $newProvinceName,
                 $newWardName,
                $village,

            ];

            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col++, $row, $value);
            }
            
            $row++;
            
            // Giải phóng bộ nhớ mỗi 100 dòng
            if ($index % $batchSize === 0) {
                gc_collect_cycles();
            }
        }
    }

    private function formatSheet($sheet)
    {
        // Định dạng font cho 4 dòng đầu: Tahoma, size 10
        $headerRange = 'A1:AY4';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Định dạng font cho dữ liệu từ dòng 5: Times New Roman, size 11
        $lastRow = $sheet->getHighestRow();
        $extraRows = min(100, 15000 - $lastRow);
         $extraRows = 0;
        $maxRows = $lastRow + $extraRows;
        $dataRange = 'A5:AY' . $maxRows;
        $sheet->getStyle($dataRange)->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'size' => 11],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Định dạng dòng 1: Nhóm tiêu đề với màu xen kẽ
        $this->formatGroupHeaders($sheet);

        // Định dạng dòng 2: Tên cột chi tiết với màu xen kẽ
        $this->formatColumnHeaders($sheet);

        // Định dạng dòng 3: Tên cột chi tiết với màu xen kẽ
        $this->formatColumnHeaders($sheet, 3);

        // Định dạng dòng 4: Số thứ tự với màu xanh dương
        $sequenceRange = 'A4:AY4';
        $sheet->getStyle($sequenceRange)->applyFromArray([
            'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2196F3'] // Màu xanh dương
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Tăng chiều cao dòng cho 3 dòng đầu
        $sheet->getRowDimension(1)->setRowHeight(25); // Tăng từ 20 lên 25
        $sheet->getRowDimension(2)->setRowHeight(23); // Tăng từ 18 lên 23
        $sheet->getRowDimension(3)->setRowHeight(23); // Tăng từ 18 lên 23
        $sheet->getRowDimension(4)->setRowHeight(20); // Giữ nguyên dòng số

        // Format các cột ngày tháng thành 2 chữ số
        $this->formatDateColumns($sheet);

        // Căn giữa các cột checkbox và bậc tốt nghiệp
        $this->centerAlignColumns($sheet);

        // Khóa panes: 4 dòng đầu và 7 cột đầu từ dòng 5
        $sheet->freezePane('H5');

        // Auto-fit columns
        foreach (range('A', 'AY') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // // Định dạng borders cho dữ liệu hiện tại + 500 dòng trống để nhập thêm
        // $lastRow = $sheet->getHighestRow();
        // $extraRows = min(500, 15000 - $lastRow); // Giảm từ 1000 xuống 500 để tránh lỗi
        // $maxRows = $lastRow + $extraRows;
        // $lastColumn = 'AY'; // 51 cột từ A đến AY
        // $dataRange = 'A1:' . $lastColumn . $maxRows;
        
        // $sheet->getStyle($dataRange)->applyFromArray([
        //     'borders' => [
        //         'allBorders' => [
        //             'borderStyle' => Border::BORDER_THIN,
        //             'color' => ['rgb' => '000000']
        //         ]
        //     ]
        // ]);
    }

    private function formatDateColumns($sheet)
    {
        // Format cột ngày (D), tháng (E), năm sinh (F) thành 2 chữ số cho dữ liệu + 500 dòng trống
        $lastRow = $sheet->getHighestRow();
        $extraRows = min(500, 15000 - $lastRow);
        $maxRows = $lastRow + $extraRows;
        
        // Format từng cột
        $sheet->getStyle('D5:D' . $maxRows)->getNumberFormat()->setFormatCode('00'); // Ngày
        $sheet->getStyle('E5:E' . $maxRows)->getNumberFormat()->setFormatCode('00'); // Tháng
        $sheet->getStyle('F5:F' . $maxRows)->getNumberFormat()->setFormatCode('0000'); // Năm
    }

    private function centerAlignColumns($sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $extraRows = min(500, 15000 - $lastRow);
        $maxRows = $lastRow + $extraRows;
        
        // Các cột checkbox (H, X, AJ, AK, AL, AM, AN, AO, AP, AQ, AR)
        $checkboxColumns = ['H', 'X', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'];
        foreach ($checkboxColumns as $col) {
            $sheet->getStyle($col . '5:' . $col . $maxRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // Cột bậc tốt nghiệp (W)
        $sheet->getStyle('W5:W' . $maxRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Cột bậc TN Nghề (Z)
        $sheet->getStyle('Z5:Z' . $maxRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Các cột ngày tháng năm sinh (D, E, F)
        $sheet->getStyle('D5:F' . $maxRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function formatGroupHeaders($sheet)
    {
        // Định nghĩa các nhóm cột và màu xen kẽ
        $groups = [
            ['start' => 1, 'end' => 11, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
            ['start' => 12, 'end' => 17, 'color' => 'FFEB3B', 'textColor' => '000000'], // Vàng, chữ đen
            ['start' => 18, 'end' => 32, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
            ['start' => 33, 'end' => 35, 'color' => 'FFEB3B', 'textColor' => '000000'], // Vàng, chữ đen
            ['start' => 36, 'end' => 45, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
            ['start' => 46, 'end' => 47, 'color' => 'FFEB3B', 'textColor' => '000000'], // Vàng, chữ đen
            ['start' => 48, 'end' => 54, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
        ];

        foreach ($groups as $group) {
            $startCol = Coordinate::stringFromColumnIndex($group['start']);
            $endCol = Coordinate::stringFromColumnIndex($group['end']);
            $range = $startCol . '1:' . $endCol . '1';
            
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true, 'color' => ['rgb' => $group['textColor']]],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $group['color']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }
    }

    private function formatColumnHeaders($sheet, $row = 2)
    {
        // Định nghĩa các nhóm cột và màu xen kẽ
        $groups = [
            ['start' => 1, 'end' => 11, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
            ['start' => 12, 'end' => 17, 'color' => 'FFEB3B', 'textColor' => '000000'], // Vàng, chữ đen
            ['start' => 18, 'end' => 32, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
            ['start' => 33, 'end' => 35, 'color' => 'FFEB3B', 'textColor' => '000000'], // Vàng, chữ đen
            ['start' => 36, 'end' => 45, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
            ['start' => 46, 'end' => 47, 'color' => 'FFEB3B', 'textColor' => '000000'], // Vàng, chữ đen
            ['start' => 48, 'end' => 54, 'color' => '4CAF50', 'textColor' => 'FFFFFF'], // Xanh lá, chữ trắng
        ];

        foreach ($groups as $group) {
            $startCol = Coordinate::stringFromColumnIndex($group['start']);
            $endCol = Coordinate::stringFromColumnIndex($group['end']);
            $range = $startCol . $row . ':' . $endCol . $row;
            
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true, 'color' => ['rgb' => $group['textColor']]],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $group['color']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }
    }

    private function createDropdownDataSheet($spreadsheet)
    {
        // Tạo sheet ẩn chứa dữ liệu dropdown
        $dropdownSheet = $spreadsheet->createSheet();
        $dropdownSheet->setTitle('DropdownData');
        $dropdownSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        
        $col = 'A';
        
        // Dân tộc
        $ethnicities = DB::table('ethnicities')->pluck('name')->toArray();
        if (count($ethnicities) > 0) {
            foreach ($ethnicities as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('EthnicityList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($ethnicities))
            );
            $this->dropdownRanges['ethnicity'] = 'EthnicityList';
        }
        $col++;
        
        // Tôn giáo
        $religions = DB::table('religions')->pluck('name')->toArray();
        if (count($religions) > 0) {
            foreach ($religions as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('ReligionList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($religions))
            );
            $this->dropdownRanges['religion'] = 'ReligionList';
        }
        $col++;
        
        // Diện ưu tiên
        $priorityCategories = DB::table('priority_categories')->pluck('name')->toArray();
        if (count($priorityCategories) > 0) {
            foreach ($priorityCategories as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('PriorityCategoryList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($priorityCategories))
            );
            $this->dropdownRanges['priority_category'] = 'PriorityCategoryList';
        }
        $col++;
        
        // Hoàn cảnh đặc biệt
        $specialCircumstances = DB::table('special_circumstances')->pluck('name')->toArray();
        if (count($specialCircumstances) > 0) {
            foreach ($specialCircumstances as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('SpecialCircumstanceList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($specialCircumstances))
            );
            $this->dropdownRanges['special_circumstance'] = 'SpecialCircumstanceList';
        }
        $col++;
        
        // Diện cư trú
        $residenceTypes = DB::table('residence_types')->pluck('name')->toArray();
        if (count($residenceTypes) > 0) {
            foreach ($residenceTypes as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('ResidenceTypeList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($residenceTypes))
            );
            $this->dropdownRanges['residence_type'] = 'ResidenceTypeList';
        }
        $col++;
        
        // Tình trạng cư trú
        $residenceStatuses = DB::table('residence_statuses')->pluck('name')->toArray();
        if (count($residenceStatuses) > 0) {
            foreach ($residenceStatuses as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('ResidenceStatusList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($residenceStatuses))
            );
            $this->dropdownRanges['residence_status'] = 'ResidenceStatusList';
        }
        $col++;
        
        // Khối học / Lớp học
        $educationBlocks = DB::table('education_blocks')->orderBy('id')->pluck('name')->toArray();
        if (count($educationBlocks) > 0) {
            foreach ($educationBlocks as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('EducationBlockList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($educationBlocks))
            );
            $this->dropdownRanges['education_block'] = 'EducationBlockList';
        }
        $col++;
        
        // Xã/Phường (mới)
        $wards = DB::table('geo_units')->where('type_level', 3)->pluck('name')->toArray();
        if (count($wards) > 0) {
            foreach ($wards as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('WardList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($wards))
            );
            $this->dropdownRanges['ward'] = 'WardList';
        }
        $col++;
        
        // Trường học
        $schools = DB::table('schools')->pluck('name')->toArray();
        if (count($schools) > 0) {
            foreach ($schools as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('SchoolList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($schools))
            );
            $this->dropdownRanges['school'] = 'SchoolList';
        }
        $col++;
        
        // Bậc tốt nghiệp
        $graduationLevels = DB::table('graduation_levels')->pluck('name')->toArray();
        if (count($graduationLevels) > 0) {
            foreach ($graduationLevels as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('GraduationLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($graduationLevels))
            );
            $this->dropdownRanges['graduation_level'] = 'GraduationLevelList';
        }
        $col++;
        
        // Bậc TN Nghề
        $vocationalLevels = DB::table('vocational_levels')->pluck('name')->toArray();
        if (count($vocationalLevels) > 0) {
            foreach ($vocationalLevels as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('VocationalLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($vocationalLevels))
            );
            $this->dropdownRanges['vocational_level'] = 'VocationalLevelList';
        }
        $col++;
        
        // Hoàn thành lớp
        $completionLevels = ['3', '5'];
        foreach ($completionLevels as $i => $name) {
            $dropdownSheet->setCellValue($col . ($i + 1), $name);
        }
        $spreadsheet->addNamedRange(
            new NamedRange('CompletionLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($completionLevels))
        );
        $this->dropdownRanges['completion_level'] = 'CompletionLevelList';
        $col++;
        
        // Tái mù chữ mức
        $literacyLevels = ['1', '2'];
        foreach ($literacyLevels as $i => $name) {
            $dropdownSheet->setCellValue($col . ($i + 1), $name);
        }
        $spreadsheet->addNamedRange(
            new NamedRange('LiteracyLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($literacyLevels))
        );
        $this->dropdownRanges['literacy_level'] = 'LiteracyLevelList';
        $col++;
        
        // Khả năng học tập
        $learningAbilityLevels = ['x'];
        foreach ($learningAbilityLevels as $i => $name) {
            $dropdownSheet->setCellValue($col . ($i + 1), $name);
        }
        $spreadsheet->addNamedRange(
            new NamedRange('LearningAbilityList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($learningAbilityLevels))
        );
        $this->dropdownRanges['learning_ability'] = 'LearningAbilityList';
        $col++;
        
        // Dropdown cho checkbox (chỉ có x)
        $dropdownSheet->setCellValue($col . '1', 'x');
        $spreadsheet->addNamedRange(
            new NamedRange('CheckboxList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$1')
        );
        $this->dropdownRanges['checkbox'] = 'CheckboxList';
    }

    private function addDropdownValidation($sheet)
    {
        // Áp dụng dropdown cho dữ liệu hiện tại + 500 dòng trống để nhập thêm (giảm để tránh lỗi)
        $lastRow = $sheet->getHighestRow();
        $extraRows = min(500, 15000 - $lastRow); // Giảm từ 1000 xuống 500 để tránh lỗi
        $maxRows = $lastRow + $extraRows;
        
        // Dropdown cho cột Dân tộc (I)
        if (isset($this->dropdownRanges['ethnicity'])) {
            $validation = $sheet->getDataValidation('I5:I' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['ethnicity']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn dân tộc từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn dân tộc');
            $validation->setPrompt('Vui lòng chọn dân tộc từ danh sách.');
        }

        // Dropdown cho cột Tôn giáo (J)
        if (isset($this->dropdownRanges['religion'])) {
            $validation = $sheet->getDataValidation('J5:J' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['religion']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn tôn giáo từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn tôn giáo');
            $validation->setPrompt('Vui lòng chọn tôn giáo từ danh sách.');
        }

        // Dropdown cho cột Diện ưu tiên (K)
        if (isset($this->dropdownRanges['priority_category'])) {
            $validation = $sheet->getDataValidation('K5:K' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['priority_category']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn diện ưu tiên từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn diện ưu tiên');
            $validation->setPrompt('Vui lòng chọn diện ưu tiên từ danh sách.');
        }

        // Dropdown cho cột Diện cư trú (P)
        if (isset($this->dropdownRanges['residence_type'])) {
            $validation = $sheet->getDataValidation('P5:P' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['residence_type']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn diện cư trú từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn diện cư trú');
            $validation->setPrompt('Vui lòng chọn diện cư trú từ danh sách.');
        }

        // Dropdown cho cột Tình trạng cư trú (Q)
        if (isset($this->dropdownRanges['residence_status'])) {
            $validation = $sheet->getDataValidation('Q5:Q' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['residence_status']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn tình trạng cư trú từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn tình trạng cư trú');
            $validation->setPrompt('Vui lòng chọn tình trạng cư trú từ danh sách.');
        }

        // Dropdown cho cột Khối học (R)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('R5:R' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn khối học từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn khối học');
            $validation->setPrompt('Vui lòng chọn khối học từ danh sách.');
        }

        // Dropdown cho cột Phường Xã đang học (T)
        if (isset($this->dropdownRanges['ward'])) {
            $validation = $sheet->getDataValidation('T5:T' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['ward']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn xã/phường từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn xã/phường');
            $validation->setPrompt('Vui lòng chọn xã/phường từ danh sách.');
        }

        // Dropdown cho cột Tên trường đang học (U)
        if (isset($this->dropdownRanges['school'])) {
            $validation = $sheet->getDataValidation('U5:U' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['school']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn trường học từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn trường học');
            $validation->setPrompt('Vui lòng chọn trường học từ danh sách.');
        }

        // Dropdown cho cột Bậc tốt nghiệp (W)
        if (isset($this->dropdownRanges['graduation_level'])) {
            $validation = $sheet->getDataValidation('W5:W' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['graduation_level']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn bậc tốt nghiệp từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn bậc tốt nghiệp');
            $validation->setPrompt('Vui lòng chọn bậc tốt nghiệp từ danh sách.');
        }

        // Dropdown cho cột Bậc TN Nghề (Z)
        if (isset($this->dropdownRanges['vocational_level'])) {
            $validation = $sheet->getDataValidation('Z5:Z' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['vocational_level']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn bậc tốt nghiệp nghề từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn bậc TN nghề');
            $validation->setPrompt('Vui lòng chọn bậc tốt nghiệp nghề từ danh sách.');
        }

        // Dropdown cho cột Lớp (Học xong) (AC)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('AC5:AC' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn lớp học từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn lớp học');
            $validation->setPrompt('Vui lòng chọn lớp học từ danh sách.');
        }

        // Dropdown cho cột Lớp (Bỏ học) (AE)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('AE5:AE' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn lớp học từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn lớp học');
            $validation->setPrompt('Vui lòng chọn lớp học từ danh sách.');
        }

        // Dropdown cho cột Hoàn thành lớp (AH - Cột 34)
        if (isset($this->dropdownRanges['completion_level'])) {
            $validation = $sheet->getDataValidation('AH5:AH' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['completion_level']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn mức hoàn thành từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn mức hoàn thành');
            $validation->setPrompt('Vui lòng chọn mức hoàn thành từ danh sách.');
        }

        // Dropdown cho cột Tái mù chữ mức (AI - Cột 35)
        if (isset($this->dropdownRanges['literacy_level'])) {
            $validation = $sheet->getDataValidation('AI5:AI' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['literacy_level']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn mức tái mù chữ từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn mức tái mù chữ');
            $validation->setPrompt('Vui lòng chọn mức tái mù chữ từ danh sách.');
        }

        // Dropdown cho cột Khả năng học tập (AS - Cột 45)
        if (isset($this->dropdownRanges['learning_ability'])) {
            $validation = $sheet->getDataValidation('AS5:AS' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['learning_ability']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn khả năng học tập từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn khả năng học tập');
            $validation->setPrompt('Vui lòng chọn khả năng học tập từ danh sách.');
        }

        // Dropdown cho cột Hoàn cảnh đặc biệt (AT)
        if (isset($this->dropdownRanges['special_circumstance'])) {
            $validation = $sheet->getDataValidation('AT5:AT' . $maxRows);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['special_circumstance']);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn hoàn cảnh đặc biệt từ danh sách có sẵn.');
            $validation->setPromptTitle('Chọn hoàn cảnh đặc biệt');
            $validation->setPrompt('Vui lòng chọn hoàn cảnh đặc biệt từ danh sách.');
        }

        // Dropdown cho các cột checkbox
        if (isset($this->dropdownRanges['checkbox'])) {
            $checkboxColumns = ['H', 'X', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'];
            foreach ($checkboxColumns as $col) {
                $validation = $sheet->getDataValidation($col . '5:' . $col . $maxRows);
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
}
