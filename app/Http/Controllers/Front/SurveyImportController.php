<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\SurveyImportRequest;
 use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;
 use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\SurveyImportOrchestrator;
use App\Services\{ReportFormatService,AccessGuardService,SurveyFormService,GeoService};
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
class SurveyImportController extends Controller
{
    public function __construct(
        private AccessGuardService $guard,
        private SurveyFormService $deleter,
        private GeoService $geo) {}


    public function create(Request $request)
    {
        // Kiểm tra quyền import đối tượng điều tra
        // $user = Auth::user();
        
        // if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'create')) {
        //     abort(403, 'Bạn không có quyền import dữ liệu đối tượng điều tra.');
        // }
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'create'); // trả về: user, is_admin, scheme, geo
        
            // Kiểm tra phiếu có bị khóa không
            
        $geo = $this->guard->guardRequestGeoAndLock($request, 'SURVEY');
    
        return view('front.surveys.import'); // chỉ render view
    }

    
public function store(Request $request, SurveyImportOrchestrator $importer)
{
    // Kiểm tra quyền import đối tượng điều tra

   
    $payload = $request->all();
    
    $payload['geo']['village_id']  = data_get($payload, 'geo.village_id');
    Log::info( $payload['geo']);
    Log::info($payload['geo']['province_id']);
    $user = Auth::user();
    if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'create')) {
        return response()->json([
            'success' => false,
            'message' => 'Bạn không có quyền import dữ liệu đối tượng điều tra.'
        ], 403);
    }
    $user = Auth::user();
    $isAdmin = $user && ($user->role_id === 1 || $user->role_id === 2);
    $geo = $this->guard->guardRequestGeoAndLock($request, 'SURVEY');
    $data = $request->all();
    // Log::info('$request',  $data);
    if (!$isAdmin) {
        $data['scheme'] = 'new';
        $data['geo']['province_id'] = $user->province_id ?? null;
        $data['geo']['district_id'] = null;
        $data['geo']['ward_id']     = $user->ward_id ?? null;
        // $data['geo']['village_id']  = null;
        $request->replace($data); // hoặc merge()
    }

    //  $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'create'); // trả về: user, is_admin, scheme, geo
      
        // Kiểm tra phiếu có bị khóa không
         
    // $geo = $this->guard->guardRequestGeoAndLock($request, 'SURVEY');
 
    @set_time_limit(600);
    @ini_set('memory_limit','512M');
//    Log::info( $data['geo']);
//     Log::info($data['geo']['province_id']);
    // Hỗ trợ cả form-submit (rows là JSON string) lẫn JSON body
    if (is_string($request->input('rows'))) {
        $decoded = json_decode($request->input('rows'), true);
        $request->merge(['rows' => $decoded ?: []]);
    }

    // Chunked nếu có cờ hoặc có chỉ số lô, và client mong muốn JSON
    $isChunked = $request->boolean('chunked', $request->has('batch_index'))
                 || $request->expectsJson() || $request->wantsJson();
  
    // Base rules (meta + lô)
    $rules = [
        'academic_year'   => 'required|string',
        'scheme'          => 'required|in:old,new',
        'geo.province_id' => 'required|integer',
        'geo.ward_id'     => 'required|integer',
        'geo.district_id' => 'nullable|integer',
        'geo.village_id' => 'nullable|integer',
        'rows'            => 'required|array|min:1',
        'rows.*'          => 'array',
        'rows.*.*'        => 'nullable',
        'rows.*.geo'      => 'prohibited',      // không cho mảng geo trong từng row

        // thông tin lô & map dòng excel (tuỳ chọn)
        'batch_index'            => 'nullable|integer|min:1',
        'batch_total'            => 'nullable|integer|min:1',
        'overall_total'          => 'nullable|integer|min:1',
        'chunk_size'             => 'nullable|integer|min:1|max:1000',
        'chunk_offset'           => 'nullable|integer|min:0',
        'excel_data_start_row'   => 'nullable|integer|min:2',
        'file_name'              => 'nullable|string',
        'sheet_name'             => 'nullable|string',
        'chunked'                => 'sometimes|boolean',
    ];

    // Nếu KHÔNG chunked (submit nguyên khối) => ràng buộc bắt buộc trong từng row
    if (!$isChunked) {
        $rules['rows.*.so_phieu'] = 'required|string';
        $rules['rows.*.ten']      = 'required|string';
    }

    // try {
        // $data = $request->validate($rules);
        //   Log::info('$request',  $data);
//  echo '  $academicYear'.  $data['academic_year'];
        // Sanitize: chỉ giữ scalar/null trong từng row (tránh "Array to string conversion")
        $data['rows'] = array_map(function ($r) {
            if (!is_array($r)) return [];
            foreach ($r as $k => $v) {
                if (!(is_scalar($v) || is_null($v))) unset($r[$k]);
            }
            return $r;
        }, $data['rows']);

        // --- Nhánh JSON/Chunked: trả đúng định dạng cho FE ---
        if ($isChunked) {
            // Dùng importChunk nếu có; fallback dùng import rồi map
            if (method_exists($importer, 'importChunk')) {
                $res = $importer->importChunk($data);
            } else {
                $stats = $importer->import($data);
                $res = [
                    'ok'                 => $stats['fail_count'] ?false:true,
                    'ok_count'           => $stats['ok_count'] ?? 0,
                    'fail_count'         => $stats['fail_count'] ?? 0,
                    'row_errors'         => $stats['row_errors'] ?? [],
                    'message_errors'     => $stats['message_errors'] ?? [],
                    'imported_rows'      => (int)($stats['members_upserted']   ?? 0),
                    'households_created' => (int)($stats['households_created'] ?? 0),
                    'households_updated' => (int)($stats['households_updated'] ?? 0),
                    'survey_forms'       => (int)($stats['survey_forms']       ?? 0),
                    'survey_details'     => (int)($stats['survey_details']     ?? 0),
                ];
            }

            // Bổ sung thông tin lô + message gọn gàng
            $batchIndex = (int)($data['batch_index'] ?? 0);
            $batchTotal = (int)($data['batch_total'] ?? 0);
            $res['batch_index'] = $batchIndex;
            $res['batch_total'] = $batchTotal;
            $res['message'] = $res['message']
                ?? ($batchIndex && $batchTotal
                    ? "Lô {$batchIndex}/{$batchTotal} · OK ".((int)($res['ok_count'] ?? 0))." · Lỗi ".((int)($res['fail_count'] ?? 0))
                    : "Đã xử lý · OK ".((int)($res['ok_count'] ?? 0))." · Lỗi ".((int)($res['fail_count'] ?? 0)));

            // Luôn 200 để FE tiếp tục các lô còn lại (lỗi lẻ nằm trong row_errors)
            return response()->json($res, 200);
        }

        // --- Nhánh submit nguyên khối (non-JSON) ---
        $stats = $importer->import($data);
        $hc = (int)($stats['households_created'] ?? 0);
        $hu = (int)($stats['households_updated'] ?? 0);
        $mu = (int)($stats['members_upserted']   ?? 0);

        // return redirect()
        //     ->route('front.surveys.index')
        //     ->with('ok', "Đã nhập {$hc} hộ mới, {$hu} hộ cập nhật, {$mu} thành viên.");
    // }
    // catch (ValidationException $e) {
    //     if ($request->expectsJson() || $request->wantsJson()) {
    //         // 422 cho lỗi meta request (không phải lỗi từng dòng)
    //         return response()->json([
    //             'ok'         => false,
    //             'ok_count'   => 0,
    //             'fail_count' => 0,
    //             'row_errors' => [],
    //             'message'    => 'Dữ liệu không hợp lệ.',
    //             'errors'     => $e->errors(),
    //         ], 422);
    //     }
    //     throw $e;
    // }
    // catch (Throwable $e) {
    //     if ($request->expectsJson() || $request->wantsJson()) {
    //         // 500 cho lỗi xử lý tổng quát
    //         return response()->json([
    //             'ok'         => false,
    //             'ok_count'   => 0,
    //             'fail_count' => 0,
    //             'row_errors' => [],
    //             'message'    => $e->getMessage(),
    //         ], 500);
    //     }
    //     return back()->withErrors(['import' => $e->getMessage()]);
    // }
}
    // public function store(Request $request, SurveyImportOrchestrator $importer)
    // {
    //     @set_time_limit(600);          // 10 phút
    //     @ini_set('memory_limit','512M');
    //     // dd($request->all());
    //     // rows có thể là JSON string -> decode
    //     if (is_string($request->input('rows'))) {
    //         $request->merge(['rows' => json_decode($request->input('rows'), true) ?: []]);
    //     }

    //     $data = $request->validate([
    //         'academic_year'   => 'required|string',
    //         'scheme'          => 'required|in:old,new',
    //         'geo.province_id' => 'required|integer',
    //         'geo.ward_id'     => 'required|integer',
    //         'geo.district_id' => 'nullable|integer',
    //         'rows'            => 'required|array|min:1',
    //         'rows.*'          => 'array',
    //         'rows.*.*'        => 'nullable',       // 👈 giữ mọi key bên trong mỗi row
    //         'rows.*.so_phieu' => 'required|string',
    //         'rows.*.ten'      => 'required|string',
    //     ]);

    //     // Muốn debug dữ liệu vào: bỏ comment dòng dưới
    //     // dd($request->all(),$data);

    //     $stats = $importer->import($data);

    //     // Muốn debug kết quả: 
    //     // dd($stats);
    //     return redirect()
    //         ->route('front.surveys.index')
    //         ->with('ok', "Đã nhập {$stats['households_created']} hộ mới, "
    //                    . "{$stats['households_updated']} hộ cập nhật, "
    //                    . "{$stats['members_upserted']} thành viên.");
    // }
    

    // public function store(SurveyImportRequest $request, SurveyImportService $service)
    // {
    //     $result = $service->import($request->validated());

    //     // Tuỳ ý: flash kết quả
    //     return back()->with('status', "Nhập thành công: {$result['ok']} dòng, lỗi: {$result['failed']}");
    // }

    public function template(): BinaryFileResponse
    {
        // Trả file mẫu .xlsx trong storage/app/templates/survey_import.xlsx
        $path = storage_path('app/templates/survey_import.xlsx');
        return response()->download($path, 'mau_nhap_phieu.xlsx');
    }

    public function downloadTemplate()
    {
        // Tăng thời gian thực thi và bộ nhớ
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        // Kiểm tra quyền
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Bạn cần đăng nhập để tải file mẫu.');
        }

        // Tạo file Excel mẫu giống format của đối tượng điều tra
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mẫu nhập phiếu điều tra');

        // Tạo dropdown data sheet
        $this->createDropdownDataSheet($spreadsheet);
        
        // Tạo header (4 dòng)
        $this->createHeaders($sheet);
        
        // Format sheet (không có dữ liệu, chỉ format cho 10,000 dòng trống)
        $this->formatTemplateSheet($sheet);

        // Thêm dropdown validation cho 10,000 dòng
        try {
            $this->addDropdownValidationForTemplate($sheet);
        } catch (\Exception $e) {
            \Log::warning('Dropdown validation error: ' . $e->getMessage());
        }

        $filename = 'mau_nhap_phieu_dieu_tra_' . date('Y-m-d_H-i-s') . '.xlsx';

        try {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi khi xuất file: ' . $e->getMessage());
        }
    }

    private $dropdownRanges = [];

    private function createDropdownDataSheet($spreadsheet)
    {
        // Tạo sheet ẩn chứa dữ liệu dropdown
        $dropdownSheet = $spreadsheet->createSheet();
        $dropdownSheet->setTitle('DropdownData');
        $dropdownSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        
        $col = 'A';
        
        // Dân tộc
        $ethnicities = \Illuminate\Support\Facades\DB::table('ethnicities')->pluck('name')->toArray();
        if (count($ethnicities) > 0) {
            foreach ($ethnicities as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('EthnicityList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($ethnicities))
            );
            $this->dropdownRanges['ethnicity'] = 'EthnicityList';
        }
        $col++;
        
        // Tôn giáo
        $religions = \Illuminate\Support\Facades\DB::table('religions')->pluck('name')->toArray();
        if (count($religions) > 0) {
            foreach ($religions as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('ReligionList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($religions))
            );
            $this->dropdownRanges['religion'] = 'ReligionList';
        }
        $col++;
        
        // Diện ưu tiên
        $priorityCategories = \Illuminate\Support\Facades\DB::table('priority_categories')->pluck('name')->toArray();
        if (count($priorityCategories) > 0) {
            foreach ($priorityCategories as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('PriorityCategoryList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($priorityCategories))
            );
            $this->dropdownRanges['priority_category'] = 'PriorityCategoryList';
        }
        $col++;
        
        // Hoàn cảnh đặc biệt
        $specialCircumstances = \Illuminate\Support\Facades\DB::table('special_circumstances')->pluck('name')->toArray();
        if (count($specialCircumstances) > 0) {
            foreach ($specialCircumstances as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('SpecialCircumstanceList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($specialCircumstances))
            );
            $this->dropdownRanges['special_circumstance'] = 'SpecialCircumstanceList';
        }
        $col++;
        
        // Diện cư trú
        $residenceTypes = \Illuminate\Support\Facades\DB::table('residence_types')->pluck('name')->toArray();
        if (count($residenceTypes) > 0) {
            foreach ($residenceTypes as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('ResidenceTypeList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($residenceTypes))
            );
            $this->dropdownRanges['residence_type'] = 'ResidenceTypeList';
        }
        $col++;
        
        // Tình trạng cư trú
        $residenceStatuses = \Illuminate\Support\Facades\DB::table('residence_statuses')->pluck('name')->toArray();
        if (count($residenceStatuses) > 0) {
            foreach ($residenceStatuses as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('ResidenceStatusList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($residenceStatuses))
            );
            $this->dropdownRanges['residence_status'] = 'ResidenceStatusList';
        }
        $col++;
        
        // Khối học / Lớp học
        $educationBlocks = \Illuminate\Support\Facades\DB::table('education_blocks')->orderBy('id')->pluck('name')->toArray();
        if (count($educationBlocks) > 0) {
            foreach ($educationBlocks as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('EducationBlockList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($educationBlocks))
            );
            $this->dropdownRanges['education_block'] = 'EducationBlockList';
        }
        $col++;
        
        // Xã/Phường (mới)
        $wards = \Illuminate\Support\Facades\DB::table('geo_units')->where('type_level', 3)->pluck('name')->toArray();
        if (count($wards) > 0) {
            foreach ($wards as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('WardList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($wards))
            );
            $this->dropdownRanges['ward'] = 'WardList';
        }
        $col++;
        
        // Trường học
        $schools = \Illuminate\Support\Facades\DB::table('schools')->pluck('name')->toArray();
        if (count($schools) > 0) {
            foreach ($schools as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('SchoolList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($schools))
            );
            $this->dropdownRanges['school'] = 'SchoolList';
        }
        $col++;
        
        // Bậc tốt nghiệp
        $graduationLevels = \Illuminate\Support\Facades\DB::table('graduation_levels')->pluck('name')->toArray();
        if (count($graduationLevels) > 0) {
            foreach ($graduationLevels as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('GraduationLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($graduationLevels))
            );
            $this->dropdownRanges['graduation_level'] = 'GraduationLevelList';
        }
        $col++;
        
        // Bậc TN Nghề
        $vocationalLevels = \Illuminate\Support\Facades\DB::table('vocational_levels')->pluck('name')->toArray();
        if (count($vocationalLevels) > 0) {
            foreach ($vocationalLevels as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange('VocationalLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($vocationalLevels))
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
            new \PhpOffice\PhpSpreadsheet\NamedRange('CompletionLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($completionLevels))
        );
        $this->dropdownRanges['completion_level'] = 'CompletionLevelList';
        $col++;
        
        // Tái mù chữ mức
        $literacyLevels = ['1', '2'];
        foreach ($literacyLevels as $i => $name) {
            $dropdownSheet->setCellValue($col . ($i + 1), $name);
        }
        $spreadsheet->addNamedRange(
            new \PhpOffice\PhpSpreadsheet\NamedRange('LiteracyLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($literacyLevels))
        );
        $this->dropdownRanges['literacy_level'] = 'LiteracyLevelList';
        $col++;
        
        // Khả năng học tập
        $learningAbilityLevels = ['x'];
        foreach ($learningAbilityLevels as $i => $name) {
            $dropdownSheet->setCellValue($col . ($i + 1), $name);
        }
        $spreadsheet->addNamedRange(
            new \PhpOffice\PhpSpreadsheet\NamedRange('LearningAbilityList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($learningAbilityLevels))
        );
        $this->dropdownRanges['learning_ability'] = 'LearningAbilityList';
        $col++;
        
        // Dropdown cho checkbox (chỉ có x)
        $dropdownSheet->setCellValue($col . '1', 'x');
        $spreadsheet->addNamedRange(
            new \PhpOffice\PhpSpreadsheet\NamedRange('CheckboxList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$1')
        );
        $this->dropdownRanges['checkbox'] = 'CheckboxList';
    }

    private function createHeaders($sheet)
    {
        // Copy logic từ SurveyExportController
        $groupHeaders = [
            'Thông tin đối tượng',    // Cột 1-11
            'Thông tin nơi đang ở',   // Cột 12-17  
            'Thông tin học tập',      // Cột 18-32
            'Xóa mù chữ',            // Cột 33-35
            'Khuyết tật',            // Cột 36-45
            'Hoàn cảnh',             // Cột 46-47
            'Thông tin liên lạc'     // Cột 48-54
        ];
        
        $columnHeaders = [
            'TT', 'Họ đệm', 'Tên', 'Ngày', 'Tháng', 'Năm sinh', 'Số định danh cá nhân', 'Nữ', 'Dân tộc', 'Tôn giáo', 'Diện ưu tiên',
            'Họ đệm', 'Tên', 'Địa chỉ: Số nhà, tên đường, tổ (nếu có)', 'Số phiếu', 'Diện cư trú', 'Tình trạng cư trú',
            'Khối học', 'Lớp học', 'Phường/Xã đang học', 'Tên trường đang học', 'Mã Trường', 'Bậc tốt nghiệp', 'Bổ túc', 'Năm tốt nghiệp',
            'Bậc TN Nghề', 'Số bằng tốt nghiệp', 'Năm TN nghề', 'Lớp', 'Năm', 'Lớp', 'Năm', 'Đang học lớp', 'Hoàn thành lớp', 'Tái mù chữ mức',
            'Khuyết tật vận động', 'Khuyết tật nghe, nói', 'Khuyết tật nhìn', 'Khuyết tật thần kinh, tâm thần', 'Khuyết tật trí tuệ',
            'Khuyết tật học tập', 'Tự kỷ', 'Khuyết tật khác', 'Có chứng nhận khuyết tật', 'Khả năng học tập',
            'Hoàn cảnh đặc biệt', 'Chi tiết hoàn cảnh đặc biệt', 'Quan hệ với chủ hộ', 'Họ tên Cha hoặc Mẹ', 'Điện thoại',
            'Ghi chú', 'Tỉnh TP', 'Phường xã', 'Thôn xóm'
        ];

        $sequenceNumbers = range(1, 54);

        // Tạo nhóm tiêu đề chính (dòng 1)
        $col = 1;
        $currentGroup = 0;
        $groupStartCol = 1;
        
        foreach ($columnHeaders as $index => $header) {
            $colIndex = $index + 1;
            
            if ($colIndex <= 11) {
                $groupIndex = 0;
            } elseif ($colIndex <= 17) {
                $groupIndex = 1;
            } elseif ($colIndex <= 32) {
                $groupIndex = 2;
            } elseif ($colIndex <= 35) {
                $groupIndex = 3;
            } elseif ($colIndex <= 45) {
                $groupIndex = 4;
            } elseif ($colIndex <= 47) {
                $groupIndex = 5;
            } else {
                $groupIndex = 6;
            }
            
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
        
        $sheet->mergeCellsByColumnAndRow($groupStartCol, 1, 54, 1);
        $sheet->setCellValueByColumnAndRow($groupStartCol, 1, $groupHeaders[$currentGroup]);

        // Tạo nhóm tiêu đề phụ và tên cột chi tiết (dòng 2)
        $col = 1;
        foreach ($columnHeaders as $index => $header) {
            $colIndex = $index + 1;
            
            if ($colIndex == 12 || $colIndex == 13) {
                if ($colIndex == 12) {
                    $sheet->mergeCellsByColumnAndRow(12, 2, 13, 2);
                    $sheet->setCellValueByColumnAndRow(12, 2, 'Chủ hộ');
                }
            } elseif ($colIndex == 29 || $colIndex == 30) {
                if ($colIndex == 29) {
                    $sheet->mergeCellsByColumnAndRow(29, 2, 30, 2);
                    $sheet->setCellValueByColumnAndRow(29, 2, 'Học xong');
                }
            } elseif ($colIndex == 31 || $colIndex == 32) {
                if ($colIndex == 31) {
                    $sheet->mergeCellsByColumnAndRow(31, 2, 32, 2);
                    $sheet->setCellValueByColumnAndRow(31, 2, 'Bỏ học');
                }
            } else {
                $sheet->mergeCellsByColumnAndRow($col, 2, $col, 3);
                $sheet->setCellValueByColumnAndRow($col, 2, $header);
            }
            $col++;
        }

        // Tạo tên cột chi tiết cho các cột có nhóm phụ (dòng 3)
        $col = 1;
        foreach ($columnHeaders as $index => $header) {
            $colIndex = $index + 1;
            
            if ($colIndex == 12 || $colIndex == 13 || 
                $colIndex == 29 || $colIndex == 30 || 
                $colIndex == 31 || $colIndex == 32) {
                $sheet->setCellValueByColumnAndRow($col, 3, $header);
            }
            $col++;
        }

        // Tạo số thứ tự (dòng 4)
        $col = 1;
        foreach ($sequenceNumbers as $number) {
            $sheet->setCellValueByColumnAndRow($col++, 4, $number);
        }
    }

    private function formatTemplateSheet($sheet)
    {
        // Format header (4 dòng đầu)
        $headerRange = 'A1:AY4';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Format dữ liệu cho 10,000 dòng trống (từ dòng 5 đến 10004)
        $maxRows = 10004;
        $dataRange = 'A5:AY' . $maxRows;
        $sheet->getStyle($dataRange)->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'size' => 11],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Format nhóm tiêu đề
        $this->formatGroupHeaders($sheet);
        $this->formatColumnHeaders($sheet, 2);
        $this->formatColumnHeaders($sheet, 3);

        // Format dòng 4: Số thứ tự
        $sequenceRange = 'A4:AY4';
        $sheet->getStyle($sequenceRange)->applyFromArray([
            'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2196F3']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ]);

        // Tăng chiều cao dòng cho 3 dòng đầu
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(23);
        $sheet->getRowDimension(3)->setRowHeight(23);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // Format các cột ngày tháng thành 2 chữ số
        $sheet->getStyle('D5:D' . $maxRows)->getNumberFormat()->setFormatCode('00');
        $sheet->getStyle('E5:E' . $maxRows)->getNumberFormat()->setFormatCode('00');
        $sheet->getStyle('F5:F' . $maxRows)->getNumberFormat()->setFormatCode('0000');

        // Căn giữa các cột checkbox và bậc tốt nghiệp
        $checkboxColumns = ['H', 'X', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'];
        foreach ($checkboxColumns as $col) {
            $sheet->getStyle($col . '5:' . $col . $maxRows)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('W5:W' . $maxRows)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('Z5:Z' . $maxRows)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D5:F' . $maxRows)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Khóa panes
        $sheet->freezePane('H5');

        // Auto-fit columns
        foreach (range('A', 'AY') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }

    private function formatGroupHeaders($sheet)
    {
        $groups = [
            ['start' => 1, 'end' => 11, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
            ['start' => 12, 'end' => 17, 'color' => 'FFEB3B', 'textColor' => '000000'],
            ['start' => 18, 'end' => 32, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
            ['start' => 33, 'end' => 35, 'color' => 'FFEB3B', 'textColor' => '000000'],
            ['start' => 36, 'end' => 45, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
            ['start' => 46, 'end' => 47, 'color' => 'FFEB3B', 'textColor' => '000000'],
            ['start' => 48, 'end' => 54, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
        ];

        foreach ($groups as $group) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['start']);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['end']);
            $range = $startCol . '1:' . $endCol . '1';
            
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true, 'color' => ['rgb' => $group['textColor']]],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $group['color']]
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ]);
        }
    }

    private function formatColumnHeaders($sheet, $row = 2)
    {
        $groups = [
            ['start' => 1, 'end' => 11, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
            ['start' => 12, 'end' => 17, 'color' => 'FFEB3B', 'textColor' => '000000'],
            ['start' => 18, 'end' => 32, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
            ['start' => 33, 'end' => 35, 'color' => 'FFEB3B', 'textColor' => '000000'],
            ['start' => 36, 'end' => 45, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
            ['start' => 46, 'end' => 47, 'color' => 'FFEB3B', 'textColor' => '000000'],
            ['start' => 48, 'end' => 54, 'color' => '4CAF50', 'textColor' => 'FFFFFF'],
        ];

        foreach ($groups as $group) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['start']);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($group['end']);
            $range = $startCol . $row . ':' . $endCol . $row;
            
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['name' => 'Tahoma', 'size' => 10, 'bold' => true, 'color' => ['rgb' => $group['textColor']]],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $group['color']]
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ]);
        }
    }

    private function addDropdownValidationForTemplate($sheet)
    {
        $maxRows = 10004; // 10,000 dòng trống từ dòng 5
        
        // Dropdown cho cột Dân tộc (I)
        if (isset($this->dropdownRanges['ethnicity'])) {
            $validation = $sheet->getDataValidation('I5:I' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['ethnicity']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Tôn giáo (J)
        if (isset($this->dropdownRanges['religion'])) {
            $validation = $sheet->getDataValidation('J5:J' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['religion']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Diện ưu tiên (K)
        if (isset($this->dropdownRanges['priority_category'])) {
            $validation = $sheet->getDataValidation('K5:K' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['priority_category']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Diện cư trú (P)
        if (isset($this->dropdownRanges['residence_type'])) {
            $validation = $sheet->getDataValidation('P5:P' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['residence_type']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Tình trạng cư trú (Q)
        if (isset($this->dropdownRanges['residence_status'])) {
            $validation = $sheet->getDataValidation('Q5:Q' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['residence_status']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Khối học (R)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('R5:R' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Phường Xã đang học (T)
        if (isset($this->dropdownRanges['ward'])) {
            $validation = $sheet->getDataValidation('T5:T' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['ward']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Tên trường đang học (U)
        if (isset($this->dropdownRanges['school'])) {
            $validation = $sheet->getDataValidation('U5:U' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['school']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Bậc tốt nghiệp (W)
        if (isset($this->dropdownRanges['graduation_level'])) {
            $validation = $sheet->getDataValidation('W5:W' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['graduation_level']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Bậc TN Nghề (Z)
        if (isset($this->dropdownRanges['vocational_level'])) {
            $validation = $sheet->getDataValidation('Z5:Z' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['vocational_level']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Lớp (Học xong) (AC)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('AC5:AC' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Lớp (Bỏ học) (AE)
        if (isset($this->dropdownRanges['education_block'])) {
            $validation = $sheet->getDataValidation('AE5:AE' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['education_block']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Hoàn thành lớp (AH)
        if (isset($this->dropdownRanges['completion_level'])) {
            $validation = $sheet->getDataValidation('AH5:AH' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['completion_level']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Tái mù chữ mức (AI)
        if (isset($this->dropdownRanges['literacy_level'])) {
            $validation = $sheet->getDataValidation('AI5:AI' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['literacy_level']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Khả năng học tập (AS)
        if (isset($this->dropdownRanges['learning_ability'])) {
            $validation = $sheet->getDataValidation('AS5:AS' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['learning_ability']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho cột Hoàn cảnh đặc biệt (AT)
        if (isset($this->dropdownRanges['special_circumstance'])) {
            $validation = $sheet->getDataValidation('AT5:AT' . $maxRows);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['special_circumstance']);
            $validation->setShowDropDown(true);
        }

        // Dropdown cho các cột checkbox
        if (isset($this->dropdownRanges['checkbox'])) {
            $checkboxColumns = ['H', 'X', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'];
            foreach ($checkboxColumns as $col) {
                $validation = $sheet->getDataValidation($col . '5:' . $col . $maxRows);
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setFormula1($this->dropdownRanges['checkbox']);
                $validation->setShowDropDown(true);
            }
        }
    }
}
