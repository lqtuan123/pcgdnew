<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ElementaryInfrastructureReport;
use App\Models\GeoUnit;
use App\Models\School;
use App\Models\EducationalLevel;
use App\Services\GeoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ElementaryInfrastructureController extends Controller
{
    protected $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }

    /**
     * Lấy ID của các cấp học Tiểu học
     */
    private function getPrimaryLevelIds(): array
    {
        $primaryLevels = EducationalLevel::whereIn('name', [
            'Tiểu học',
            'Tiểu học + THCS',
            'Tiểu học + THCS + THPT'
        ])->pluck('id')->toArray();

        return $primaryLevels;
    }

    /**
     * Hiển thị danh sách báo cáo cơ sở vật chất trường tiểu học
     */
    public function index(Request $request)
    {
        $defaultYear = date('Y') . '-' . (date('Y') + 1);
        $year = $request->get('year', $defaultYear);
        $q = trim((string) $request->get('q'));
        $perPage = (int) $request->get('per_page', 50);
        $scheme = $request->get('scheme', 'old');
        $geoIn = (array) $request->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => (int) ($geoIn['school_id'] ?? $request->input('geo_school_id') ?? 0) ?: null,
        ];

        // Lấy ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            $reports = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
            $columns = $this->columns('province');
            $groups = $this->columnGroups('province');
            $stats = [];
            $level = 'province';

            return view('front.elementary-infrastructure.index', compact(
                'reports', 'columns', 'groups', 'year', 'defaultYear', 'q', 'scheme', 'geoIds', 'stats', 'level'
            ));
        }

        // Xác định cấp độ hiển thị dựa trên user role và filter
        $user = Auth::user();
        $level = $this->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->getReportsByLevelWithFilter($level, $year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds);
        $columns = $this->columns($level);
        $groups = $this->columnGroups($level);
        $stats = $this->getStatistics($year, $geoIds['ward'], $primaryLevelIds);

        return view('front.elementary-infrastructure.index', compact(
            'reports', 'columns', 'groups', 'year', 'defaultYear', 'q', 'scheme', 'geoIds', 'stats', 'level'
        ));
    }

    /**
     * Lưu dữ liệu cấp trường
     */
    public function saveSchoolData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
            'school_id' => 'required|integer|exists:schools,id',
            'ward_id' => 'required|integer|exists:geo_units,id',
            'school_name' => 'required|string|max:255',
            'school_branch' => 'nullable|integer|min:0',
            'total_classes' => 'nullable|integer',
            'combined_classes' => 'nullable|integer',
            'permanent_classrooms' => 'nullable|integer',
            'semi_permanent_classrooms' => 'nullable|integer',
            'temporary_classrooms' => 'nullable|integer',
            'rented_classrooms' => 'nullable|integer',
            'room_class_ratio' => 'nullable|numeric',
            'principal_room' => 'nullable|integer',
            'vice_principal_room' => 'nullable|integer',
            'office_room' => 'nullable|integer',
            'medical_room' => 'nullable|integer',
            'youth_union_room' => 'nullable|integer',
            'meeting_room_quantity' => 'nullable|integer',
            'meeting_room_area' => 'nullable|numeric',
            'library_room_quantity' => 'nullable|integer',
            'library_room_area' => 'nullable|numeric',
            'equipment_room_quantity' => 'nullable|integer',
            'equipment_room_area' => 'nullable|numeric',
            'teacher_toilet_quantity' => 'nullable|integer',
            'teacher_toilet_area' => 'nullable|numeric',
            'student_toilet_quantity' => 'nullable|integer',
            'student_toilet_area' => 'nullable|numeric',
            'playground_quantity' => 'nullable|integer',
            'playground_area' => 'nullable|numeric',
            'practice_field_quantity' => 'nullable|integer',
            'practice_field_area' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Sử dụng trực tiếp năm học đã được format đúng
            $academicYear = $request->academic_year;
            
            // Lấy ID của các cấp học Tiểu học
            $primaryLevelIds = $this->getPrimaryLevelIds();
            
            // Lấy thông tin trường và kiểm tra xem có phải trường Tiểu học không
            $school = School::where('id', $request->school_id)
                ->whereIn('educational_level_id', $primaryLevelIds)
                ->first();
            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy trường Tiểu học hoặc trường không thuộc cấp Tiểu học'
                ], 404);
            }

            // Tìm hoặc tạo báo cáo
            $report = ElementaryInfrastructureReport::updateOrCreate(
                [
                    'academic_year' => $academicYear,
                    'ward_id' => $request->ward_id,
                    'school_id' => $request->school_id,
                ],
                [
                    'school_name' => $school->name,
                    'school_branch' => $request->school_branch ?? 0,
                    'total_classes' => $request->total_classes ?? 0,
                    'combined_classes' => $request->combined_classes ?? 0,
                    'permanent_classrooms' => $request->permanent_classrooms ?? 0,
                    'semi_permanent_classrooms' => $request->semi_permanent_classrooms ?? 0,
                    'temporary_classrooms' => $request->temporary_classrooms ?? 0,
                    'rented_classrooms' => $request->rented_classrooms ?? 0,
                    'room_class_ratio' => $request->room_class_ratio ?? 0,
                    'principal_room' => $request->principal_room ?? 0,
                    'vice_principal_room' => $request->vice_principal_room ?? 0,
                    'office_room' => $request->office_room ?? 0,
                    'medical_room' => $request->medical_room ?? 0,
                    'youth_union_room' => $request->youth_union_room ?? 0,
                    'meeting_room_quantity' => $request->meeting_room_quantity ?? 0,
                    'meeting_room_area' => $request->meeting_room_area ?? 0,
                    'library_room_quantity' => $request->library_room_quantity ?? 0,
                    'library_room_area' => $request->library_room_area ?? 0,
                    'equipment_room_quantity' => $request->equipment_room_quantity ?? 0,
                    'equipment_room_area' => $request->equipment_room_area ?? 0,
                    'teacher_toilet_quantity' => $request->teacher_toilet_quantity ?? 0,
                    'teacher_toilet_area' => $request->teacher_toilet_area ?? 0,
                    'student_toilet_quantity' => $request->student_toilet_quantity ?? 0,
                    'student_toilet_area' => $request->student_toilet_area ?? 0,
                    'playground_quantity' => $request->playground_quantity ?? 0,
                    'playground_area' => $request->playground_area ?? 0,
                    'practice_field_quantity' => $request->practice_field_quantity ?? 0,
                    'practice_field_area' => $request->practice_field_area ?? 0,
                ]
            );
                
            return response()->json([
                'success' => true,
                'message' => 'Lưu dữ liệu thành công!',
                'data' => $report
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lưu dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thống kê tổng hợp theo năm, xã, trường
     */
    private function getStatistics($year = null, $wardId = null, $primaryLevelIds = [])
    {
        $query = ElementaryInfrastructureReport::query()
            ->when($year, fn($q) => $q->where('academic_year', $year))
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->when(!empty($primaryLevelIds), function($q) use ($primaryLevelIds) {
                $q->whereHas('school', function($schoolQuery) use ($primaryLevelIds) {
                    $schoolQuery->whereIn('educational_level_id', $primaryLevelIds);
                });
            });

        $statistics = [
            'total_schools' => $query->count(),
            'total_classes' => $query->sum('total_classes'),
            'total_permanent' => $query->sum('permanent_classrooms'),
            'avg_room_ratio' => $query->avg('room_class_ratio'),
        ];

        return $statistics;
    }


    /**
     * Export Excel cho báo cáo thống kê xã - xuất tất cả trường tiểu học trong xã
     */
    public function exportExcelForWard(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $year = $request->get('year', date('Y') . '-' . (date('Y') + 1));
        $geoIn = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => null, // Không lọc theo trường cụ thể
        ];

        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['error' => 'Không tìm thấy cấp học Tiểu học'], 400);
        }

        $user = Auth::user();
        $level = $this->determineDisplayLevel($user, $geoIds);
        
        // Lấy tất cả báo cáo cơ sở vật chất tiểu học trong xã
        $reports = $this->getReportsByLevelWithFilter($level, $year, '', $geoIds, 'old', 1000, $primaryLevelIds);
        
        $templatePath = resource_path('excel/2.2.TiH.CoSoVatChat.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('D2', $provinceName);
        $worksheet->setCellValue('D3', $wardName);
        $worksheet->setCellValue('A7', $year); // Năm học
        
        // Thêm ngày tháng năm hiện tại vào ô G4
        $currentDate = 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y');
        $worksheet->setCellValue('G4', $currentDate);
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('D2')->getFont()->setBold(true);
        $worksheet->getStyle('D3')->getFont()->setBold(true);
        
        // Format cho ô G4: Tahoma, size 8, in đậm, nghiêng
        $g4Style = $worksheet->getStyle('G4');
        $g4Style->getFont()->setName('Tahoma');
        $g4Style->getFont()->setSize(8);
        $g4Style->getFont()->setBold(true);
        $g4Style->getFont()->setItalic(true);

        // Format lại vùng A15:T17 về bình thường (cấu trúc ký tên)
        $this->resetSignatureAreaFormat($worksheet);

        // Cài đặt rows to repeat at top cho tiêu đề (dòng 6-9)
        $this->setupPrintTitles($worksheet);

        // Điền dữ liệu cho tất cả trường trong xã
        $this->fillElementaryInfrastructureDataForWard($worksheet, $reports, $level, $wardName);

        $filename = 'bao_cao_co_so_vat_chat_tieu_hoc_xa_' . $wardName . '_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'elementary_infrastructure_ward_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Export Excel theo mẫu 2.2.TiH.CoSoVatChat.xlsx
     */
    public function exportExcel(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $year = $request->get('year', date('Y') . '-' . (date('Y') + 1));
        $geoIn = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => (int) ($geoIn['school_id'] ?? $request->input('geo_school_id') ?? 0) ?: null,
        ];

        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['error' => 'Không tìm thấy cấp học Tiểu học'], 400);
        }

        $user = Auth::user();
        $level = $this->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->getReportsByLevelWithFilter($level, $year, '', $geoIds, 'old', 1000, $primaryLevelIds);
        
        $templatePath = resource_path('excel/2.2.TiH.CoSoVatChat.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('D2', $provinceName);
        $worksheet->setCellValue('D3', $wardName);
        $worksheet->setCellValue('A7', $year); // Năm học
        
        // Thêm ngày tháng năm hiện tại vào ô G4
        $currentDate = 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y');
        $worksheet->setCellValue('G4', $currentDate);
        
        // Bỏ dòng tên xã và ngày ký cho cấp trường
        // $worksheet->setCellValue('W14', $wardName . ', ngày ... tháng ... năm ...');
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('D2')->getFont()->setBold(true);
        $worksheet->getStyle('D3')->getFont()->setBold(true);
        
        // Format cho ô G4: Tahoma, size 8, in đậm, nghiêng
        $g4Style = $worksheet->getStyle('G4');
        $g4Style->getFont()->setName('Tahoma');
        $g4Style->getFont()->setSize(8);
        $g4Style->getFont()->setBold(true);
        $g4Style->getFont()->setItalic(true);
        
        // Bỏ format cho ô W14 vì không sử dụng nữa
        // $w14Style = $worksheet->getStyle('W14');
        // $w14Style->getFont()->setName('Tahoma');
        // $w14Style->getFont()->setSize(8);

        $this->fillElementaryInfrastructureData($worksheet, $reports, $level);

        $filename = 'bao_cao_co_so_vat_chat_tieu_hoc_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'elementary_infrastructure_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }


    /**
     * Xác định cấp độ hiển thị dựa trên user role và filter
     */
    private function determineDisplayLevel($user, $geoIds)
    {
        // Ưu tiên theo filter được chọn (theo thứ tự từ cụ thể đến chung)
        // Nếu có school_id được chọn -> cấp trường
        if ($geoIds['school']) {
            return 'school';
        }
        
        // Nếu có ward_id được chọn -> cấp xã
        if ($geoIds['ward']) {
            return 'ward';
        }
        
        // Nếu có province_id được chọn -> cấp tỉnh
        if ($geoIds['province']) {
            return 'province';
        }
        
        // Nếu không có filter nào được chọn, dựa trên role của user
        if ($user) {
            switch ($user->role_level) {
                case 'school':
                    return 'school';
                case 'ward':
                    return 'ward';
                case 'province':
                    return 'province';
                default:
                    return 'province'; // Mặc định cấp tỉnh
            }
        }
        
        return 'province'; // Mặc định
    }

    /**
     * Lấy dữ liệu báo cáo theo cấp độ với filter Tiểu học
     */
    private function getReportsByLevelWithFilter($level, $year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds)
    {
        switch ($level) {
            case 'school':
                return $this->getSchoolLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds);
            case 'ward':
                return $this->getWardLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds);
            case 'province':
                return $this->getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds);
            default:
                return $this->getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds);
        }
    }

    /**
     * Lấy dữ liệu báo cáo theo cấp độ
     */
    private function getReportsByLevel($level, $year, $q, $geoIds, $scheme, $perPage)
    {
        switch ($level) {
            case 'school':
                return $this->getSchoolLevelReports($year, $q, $geoIds, $scheme, $perPage);
            case 'ward':
                return $this->getWardLevelReports($year, $q, $geoIds, $scheme, $perPage);
            case 'province':
                return $this->getProvinceLevelReports($year, $q, $geoIds, $scheme, $perPage);
            default:
                return $this->getProvinceLevelReports($year, $q, $geoIds, $scheme, $perPage);
        }
    }

    /**
     * Lấy dữ liệu cấp trường với filter Tiểu học
     */
    private function getSchoolLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds)
    {
        if (!$geoIds['school']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy thông tin trường và kiểm tra xem có phải trường Tiểu học không
        $school = School::active()
            ->where('id', $geoIds['school'])
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->first();
        if (!$school) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $wardId = $school->getWardId();
        if (!$wardId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }

        // Tìm báo cáo hiện có hoặc tạo mới
        $report = ElementaryInfrastructureReport::where('academic_year', $queryYear)
            ->where('ward_id', $wardId)
            ->where('school_id', $geoIds['school'])
            ->first();

        if (!$report) {
            // Tạo báo cáo mới với dữ liệu mặc định
            $report = new ElementaryInfrastructureReport($this->getDefaultReportData($year, $wardId, $geoIds['school'], $school));
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$report]),
            1,
            $perPage,
            1,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp xã với filter Tiểu học
     */
    private function getWardLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds)
    {
        if (!$geoIds['ward']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }

        // Lấy ward_code từ ward_id
        $ward = GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả trường Tiểu học trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schoolsQuery = School::active()
            ->where('ward_code_new', $ward->code)
            ->whereIn('educational_level_id', $primaryLevelIds);
        
        if ($q) {
            $schoolsQuery->where('name', 'like', "%{$q}%");
        }
        
        $schools = $schoolsQuery->orderBy('name')->get();
        
        // Lấy báo cáo hiện có cho các trường này
        $existingReports = ElementaryInfrastructureReport::with(['school', 'ward'])
            ->where('academic_year', $queryYear)
            ->whereIn('school_id', $schools->pluck('id'))
            ->get()
            ->keyBy('school_id');
        
        // Tạo danh sách kết hợp: trường + báo cáo (nếu có)
        $combinedData = $schools->map(function($school) use ($existingReports, $year) {
            $report = $existingReports->get($school->id);
            
            if ($report) {
                // Có báo cáo, sử dụng dữ liệu từ báo cáo
                return $this->createReportObject($report, $school, true);
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return $this->createEmptyReportObject($school, $year);
            }
        });
        
        // Tính tổng cộng từ các báo cáo có dữ liệu
        $reportsWithData = $existingReports->filter(function($report) {
            return $this->hasData($report);
        });
        
        $totalRow = $this->calculateWardTotals($reportsWithData);
        $totalRow->school_name = 'TỔNG CỘNG';
        $totalRow->school_code = '';
        $totalRow->school_branch = '';
        $totalRow->has_data = true;
        
        // Thêm dòng tổng cộng
        $combinedData->push($totalRow);
        
        // Tạo paginator thủ công
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $combinedData->slice($offset, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp tỉnh với filter Tiểu học
     */
    private function getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $primaryLevelIds)
    {
        if (!$geoIds['province']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }

        // Lấy tất cả xã/phường trong tỉnh
        $wards = GeoUnit::where('parent_id', $geoIds['province'])
            ->where('scheme', $scheme)
            ->where('type_level', 3)
            ->orderBy('name')
            ->get();
        
        // Lấy tất cả báo cáo trong tỉnh chỉ cho trường Tiểu học (chỉ trường đang hoạt động)
        $reports = ElementaryInfrastructureReport::with(['school', 'ward'])
            ->where('academic_year', $queryYear)
            ->whereHas('school', function ($schoolQuery) use ($primaryLevelIds) {
                $schoolQuery->active()->whereIn('educational_level_id', $primaryLevelIds);
            })
            ->whereHas('ward', function ($wardQuery) use ($geoIds, $scheme) {
                $wardQuery->where('parent_id', $geoIds['province'])
                         ->where('scheme', $scheme)
                         ->where('type_level', 3);
            })
            ->get();
        
        // Nhóm báo cáo theo ward_id
        $reportsByWard = $reports->groupBy('ward_id');
        
        // Tạo danh sách kết hợp: xã + tổng báo cáo (nếu có)
        $combinedData = $wards->map(function($ward) use ($reportsByWard, $year) {
            $wardReports = $reportsByWard->get($ward->id, collect([]));
            
            if ($wardReports->isNotEmpty()) {
                // Có báo cáo, tính tổng
                $total = $this->calculateWardTotals($wardReports);
                $total->ward = $ward;
                $total->ward_id = $ward->id;
                $total->ward_name = $ward->name;
                $total->has_data = true;
                return $total;
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return $this->createEmptyWardObject($ward, $year);
            }
        });
        
        // Tính tổng cộng cho toàn tỉnh
        $provinceTotal = $this->calculateWardTotals($reports);
        $provinceTotal->ward_name = 'TỔNG CỘNG';
        $provinceTotal->school_name = '';
        $provinceTotal->school_branch = '';
        $provinceTotal->has_data = true;
        
        // Thêm dòng tổng cộng
        $combinedData->push($provinceTotal);
        
        // Tạo paginator thủ công
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $combinedData->slice($offset, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp trường - hiển thị form nhập cho 1 trường
     */
    private function getSchoolLevelReports($year, $q, $geoIds, $scheme, $perPage)
    {
        if (!$geoIds['school']) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage ?: 50,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        // Lấy ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage ?: 50,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        // Lấy thông tin trường và kiểm tra xem có phải trường Tiểu học không
        $school = School::active()
            ->where('id', $geoIds['school'])
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->first();
        if (!$school) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage ?: 50,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        // Lấy ward_id từ school
        $wardId = $school->getWardId();
        if (!$wardId) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage ?: 50,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        // Tìm báo cáo hiện có hoặc tạo mới
        $report = ElementaryInfrastructureReport::where('academic_year', $year)
            ->where('ward_id', $wardId)
            ->where('school_id', $geoIds['school'])
            ->first();

        if (!$report) {
            // Tạo báo cáo mới với dữ liệu mặc định
            $report = new ElementaryInfrastructureReport([
                'academic_year' => $year,
                'ward_id' => $wardId,
                'school_id' => $geoIds['school'],
                'school_name' => $school->name,
                'school_branch' => 0,
                'total_classes' => 0,
                'combined_classes' => 0,
                'permanent_classrooms' => 0,
                'semi_permanent_classrooms' => 0,
                'temporary_classrooms' => 0,
                'rented_classrooms' => 0,
                'room_class_ratio' => 0,
                'principal_room' => 0,
                'vice_principal_room' => 0,
                'office_room' => 0,
                'medical_room' => 0,
                'youth_union_room' => 0,
                'meeting_room_quantity' => 0,
                'meeting_room_area' => 0,
                'library_room_quantity' => 0,
                'library_room_area' => 0,
                'equipment_room_quantity' => 0,
                'equipment_room_area' => 0,
                'teacher_toilet_quantity' => 0,
                'teacher_toilet_area' => 0,
                'student_toilet_quantity' => 0,
                'student_toilet_area' => 0,
                'playground_quantity' => 0,
                'playground_area' => 0,
                'practice_field_quantity' => 0,
                'practice_field_area' => 0,
            ]);
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$report]),
            1,
            $perPage ?: 50,
            1,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp xã - hiển thị tất cả trường trong xã + tổng cộng
     */
    private function getWardLevelReports($year, $q, $geoIds, $scheme, $perPage)
    {
        if (!$geoIds['ward']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }

        // Lấy ward_code từ ward_id
        $ward = GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả trường Tiểu học trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schoolsQuery = School::active()
            ->where('ward_code_new', $ward->code)
            ->whereIn('educational_level_id', $primaryLevelIds);
        
        if ($q) {
            $schoolsQuery->where('name', 'like', "%{$q}%");
        }
        
        $schools = $schoolsQuery->orderBy('name')->get();
        
        // Lấy báo cáo hiện có cho các trường này
        $existingReports = ElementaryInfrastructureReport::with(['school', 'ward'])
            ->where('academic_year', $queryYear)
            ->whereIn('school_id', $schools->pluck('id'))
            ->get()
            ->keyBy('school_id');
        
        // Tạo danh sách kết hợp: trường + báo cáo (nếu có)
        $combinedData = $schools->map(function($school) use ($existingReports, $year) {
            $report = $existingReports->get($school->id);
            
            if ($report) {
                // Có báo cáo, sử dụng dữ liệu từ báo cáo
                return (object) [
                    'id' => $report->id,
                    'school_id' => $school->id,
                    'school' => $school,
                    'ward' => $report->ward,
                    'academic_year' => $report->academic_year,
                    'school_name' => $school->name,
                    'school_code' => $school->code,
                    'school_branch' => $report->school_branch,
                    'total_classes' => $report->total_classes ?? 0,
                    'combined_classes' => $report->combined_classes,
                    'permanent_classrooms' => $report->permanent_classrooms,
                    'semi_permanent_classrooms' => $report->semi_permanent_classrooms,
                    'temporary_classrooms' => $report->temporary_classrooms,
                    'rented_classrooms' => $report->rented_classrooms,
                    'room_class_ratio' => $report->room_class_ratio,
                    'principal_room' => $report->principal_room,
                    'vice_principal_room' => $report->vice_principal_room,
                    'office_room' => $report->office_room,
                    'medical_room' => $report->medical_room,
                    'youth_union_room' => $report->youth_union_room,
                    'meeting_room_quantity' => $report->meeting_room_quantity,
                    'meeting_room_area' => $report->meeting_room_area,
                    'library_room_quantity' => $report->library_room_quantity,
                    'library_room_area' => $report->library_room_area,
                    'equipment_room_quantity' => $report->equipment_room_quantity,
                    'equipment_room_area' => $report->equipment_room_area,
                    'teacher_toilet_quantity' => $report->teacher_toilet_quantity,
                    'teacher_toilet_area' => $report->teacher_toilet_area,
                    'student_toilet_quantity' => $report->student_toilet_quantity,
                    'student_toilet_area' => $report->student_toilet_area,
                    'playground_quantity' => $report->playground_quantity,
                    'playground_area' => $report->playground_area,
                    'practice_field_quantity' => $report->practice_field_quantity,
                    'practice_field_area' => $report->practice_field_area,
                    'has_data' => true
                ];
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return (object) [
                    'id' => null,
                    'school_id' => $school->id,
                    'school' => $school,
                    'ward' => null,
                    'academic_year' => $year,
                    'school_name' => $school->name,
                    'school_code' => $school->code,
                    'school_branch' => 0,
                    'total_classes' => 0,
                    'combined_classes' => 0,
                    'permanent_classrooms' => 0,
                    'semi_permanent_classrooms' => 0,
                    'temporary_classrooms' => 0,
                    'rented_classrooms' => 0,
                    'room_class_ratio' => 0,
                    'principal_room' => 0,
                    'vice_principal_room' => 0,
                    'office_room' => 0,
                    'medical_room' => 0,
                    'youth_union_room' => 0,
                    'meeting_room_quantity' => 0,
                    'meeting_room_area' => 0,
                    'library_room_quantity' => 0,
                    'library_room_area' => 0,
                    'equipment_room_quantity' => 0,
                    'equipment_room_area' => 0,
                    'teacher_toilet_quantity' => 0,
                    'teacher_toilet_area' => 0,
                    'student_toilet_quantity' => 0,
                    'student_toilet_area' => 0,
                    'playground_quantity' => 0,
                    'playground_area' => 0,
                    'practice_field_quantity' => 0,
                    'practice_field_area' => 0,
                    'has_data' => false
                ];
            }
        });
        
        // Tính tổng cộng từ các báo cáo có dữ liệu
        $reportsWithData = $existingReports->filter(function($report) {
            return ($report->total_classes ?? 0) > 0 || ($report->permanent_classrooms ?? 0) > 0;
        });
        
        $totalRow = $this->calculateWardTotals($reportsWithData);
        $totalRow->school_name = 'TỔNG CỘNG';
        $totalRow->school_code = '';
        $totalRow->school_branch = '';
        $totalRow->has_data = true;
        
        // Thêm dòng tổng cộng
        $combinedData->push($totalRow);
        
        // Tạo paginator thủ công
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $combinedData->slice($offset, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp tỉnh - hiển thị theo xã + tổng cộng
     */
    private function getProvinceLevelReports($year, $q, $geoIds, $scheme, $perPage)
    {
        if (!$geoIds['province']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }

        // Lấy tất cả phường/xã trong tỉnh
        $wardsQuery = GeoUnit::where('parent_id', $geoIds['province'])
            ->where('scheme', $scheme)
            ->where('type_level', 3); // Chỉ lấy cấp phường/xã
        
        if ($q) {
            $wardsQuery->where('name', 'like', "%{$q}%");
        }
        
        $wards = $wardsQuery->orderBy('name')->get();
        
        // Lấy ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả báo cáo trong tỉnh chỉ cho trường Tiểu học
        $reportsQuery = ElementaryInfrastructureReport::with(['ward', 'school'])
            ->where('academic_year', $queryYear)
            ->whereHas('school', function ($schoolQuery) use ($primaryLevelIds) {
                $schoolQuery->whereIn('educational_level_id', $primaryLevelIds);
            })
            ->whereHas('ward', function ($wardQuery) use ($geoIds, $scheme) {
                $wardQuery->where('parent_id', $geoIds['province'])
                         ->where('scheme', $scheme)
                         ->where('type_level', 3);
            });
        
        $reports = $reportsQuery->get();
        
        // Nhóm báo cáo theo ward_id
        $reportsByWard = $reports->groupBy('ward_id');
        
        // Tạo danh sách kết hợp: phường/xã + tổng báo cáo (nếu có)
        $combinedData = $wards->map(function($ward) use ($reportsByWard, $year) {
            $wardReports = $reportsByWard->get($ward->id, collect([]));
            
            if ($wardReports->isNotEmpty()) {
                // Có báo cáo, tính tổng
                $total = $this->calculateWardTotals($wardReports);
                $total->ward = $ward;
                $total->ward_id = $ward->id;
                $total->ward_name = $ward->name;
                $total->has_data = true;
                return $total;
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return (object) [
                    'ward' => $ward,
                    'ward_id' => $ward->id,
                    'ward_name' => $ward->name,
                    'academic_year' => $year,
                    'school_name' => '',
                    'school_branch' => 0,
                    'total_classes' => 0,
                    'combined_classes' => 0,
                    'permanent_classrooms' => 0,
                    'semi_permanent_classrooms' => 0,
                    'temporary_classrooms' => 0,
                    'rented_classrooms' => 0,
                    'room_class_ratio' => 0,
                    'principal_room' => 0,
                    'vice_principal_room' => 0,
                    'office_room' => 0,
                    'medical_room' => 0,
                    'youth_union_room' => 0,
                    'meeting_room_quantity' => 0,
                    'meeting_room_area' => 0,
                    'library_room_quantity' => 0,
                    'library_room_area' => 0,
                    'equipment_room_quantity' => 0,
                    'equipment_room_area' => 0,
                    'teacher_toilet_quantity' => 0,
                    'teacher_toilet_area' => 0,
                    'student_toilet_quantity' => 0,
                    'student_toilet_area' => 0,
                    'playground_quantity' => 0,
                    'playground_area' => 0,
                    'practice_field_quantity' => 0,
                    'practice_field_area' => 0,
                    'has_data' => false
                ];
            }
        });
        
        // Tính tổng cộng cho toàn tỉnh
        $provinceTotal = $this->calculateWardTotals($reports);
        $provinceTotal->ward_name = 'TỔNG CỘNG';
        $provinceTotal->school_name = '';
        $provinceTotal->school_branch = '';
        $provinceTotal->has_data = true;
        
        // Thêm dòng tổng cộng
        $combinedData->push($provinceTotal);
        
        // Tạo paginator thủ công
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $combinedData->slice($offset, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Tính tổng cộng cho cấp xã
     */
    private function calculateWardTotals($reports)
    {
        $total = new ElementaryInfrastructureReport();
        $total->school_name = 'TỔNG CỘNG';
        
        // Tính tổng điểm trường - convert sang số trước khi cộng
        $total->school_branch = $reports->sum(function($report) {
            return is_numeric($report->school_branch) ? (int)$report->school_branch : 0;
        });
        
        // Tính tổng an toàn với null check
        $total->total_classes = $reports->sum(function($report) {
            return $report->total_classes ?? 0;
        });
        $total->combined_classes = $reports->sum(function($report) {
            return $report->combined_classes ?? 0;
        });
        $total->permanent_classrooms = $reports->sum(function($report) {
            return $report->permanent_classrooms ?? 0;
        });
        $total->semi_permanent_classrooms = $reports->sum(function($report) {
            return $report->semi_permanent_classrooms ?? 0;
        });
        $total->temporary_classrooms = $reports->sum(function($report) {
            return $report->temporary_classrooms ?? 0;
        });
        $total->rented_classrooms = $reports->sum(function($report) {
            return $report->rented_classrooms ?? 0;
        });
        // Tính trung bình tỷ lệ Ph/L chỉ của những trường có dữ liệu hợp lệ
        $validRatioValues = $reports->filter(function($report) {
            return isset($report->room_class_ratio) && 
                   $report->room_class_ratio !== null && 
                   $report->room_class_ratio !== '' && 
                   $report->room_class_ratio > 0;
        });
        
        if ($validRatioValues->isNotEmpty()) {
            $total->room_class_ratio = $validRatioValues->avg('room_class_ratio');
        } else {
            $total->room_class_ratio = 0;
        }
        $total->principal_room = $reports->sum(function($report) {
            return $report->principal_room ?? 0;
        });
        $total->vice_principal_room = $reports->sum(function($report) {
            return $report->vice_principal_room ?? 0;
        });
        $total->office_room = $reports->sum(function($report) {
            return $report->office_room ?? 0;
        });
        $total->medical_room = $reports->sum(function($report) {
            return $report->medical_room ?? 0;
        });
        $total->youth_union_room = $reports->sum(function($report) {
            return $report->youth_union_room ?? 0;
        });
        $total->meeting_room_quantity = $reports->sum(function($report) {
            return $report->meeting_room_quantity ?? 0;
        });
        $total->meeting_room_area = $reports->sum(function($report) {
            return $report->meeting_room_area ?? 0;
        });
        $total->library_room_quantity = $reports->sum(function($report) {
            return $report->library_room_quantity ?? 0;
        });
        $total->library_room_area = $reports->sum(function($report) {
            return $report->library_room_area ?? 0;
        });
        $total->equipment_room_quantity = $reports->sum(function($report) {
            return $report->equipment_room_quantity ?? 0;
        });
        $total->equipment_room_area = $reports->sum(function($report) {
            return $report->equipment_room_area ?? 0;
        });
        $total->teacher_toilet_quantity = $reports->sum(function($report) {
            return $report->teacher_toilet_quantity ?? 0;
        });
        $total->teacher_toilet_area = $reports->sum(function($report) {
            return $report->teacher_toilet_area ?? 0;
        });
        $total->student_toilet_quantity = $reports->sum(function($report) {
            return $report->student_toilet_quantity ?? 0;
        });
        $total->student_toilet_area = $reports->sum(function($report) {
            return $report->student_toilet_area ?? 0;
        });
        $total->playground_quantity = $reports->sum(function($report) {
            return $report->playground_quantity ?? 0;
        });
        $total->playground_area = $reports->sum(function($report) {
            return $report->playground_area ?? 0;
        });
        $total->practice_field_quantity = $reports->sum(function($report) {
            return $report->practice_field_quantity ?? 0;
        });
        $total->practice_field_area = $reports->sum(function($report) {
            return $report->practice_field_area ?? 0;
        });
        
        return $total;
    }


    /**
     * Nhóm cột với cấp 2 và cấp 3 cho Cơ sở vật chất (29 cột)
     */
    private function columnGroups($level = 'province'): array
    {
        $baseGroups = [
            [
                'title' => 'Trường',
                'keys' => ['school_name', 'school_branch'],
            ],
            [
                'title' => 'Số lớp',
                'keys' => ['total_classes', 'combined_classes'],
            ],
            [
                'title' => 'Số phòng học',
                'keys' => ['permanent_classrooms', 'semi_permanent_classrooms', 'temporary_classrooms', 'rented_classrooms', 'room_class_ratio'],
            ],
            [
                'title' => 'Số phòng chức năng',
                'keys' => ['principal_room', 'vice_principal_room', 'office_room', 'medical_room', 'youth_union_room'],
                'subgroups' => [
                    [
                        'title' => 'Phòng họp',
                        'keys' => ['meeting_room_quantity', 'meeting_room_area'],
                    ],
                    [
                        'title' => 'Thư viện',
                        'keys' => ['library_room_quantity', 'library_room_area'],
                    ],
                    [
                        'title' => 'Thiết bị',
                        'keys' => ['equipment_room_quantity', 'equipment_room_area'],
                    ],
                ],
            ],
            [
                'title' => 'Công trình vệ sinh',
                'subgroups' => [
                    [
                        'title' => 'Giáo viên',
                        'keys' => ['teacher_toilet_quantity', 'teacher_toilet_area'],
                    ],
                    [
                        'title' => 'Học sinh',
                        'keys' => ['student_toilet_quantity', 'student_toilet_area'],
                    ],
                ],
            ],
            [
                'title' => 'Sân chơi',
                'keys' => ['playground_quantity', 'playground_area'],
            ],
            [
                'title' => 'Bãi tập',
                'keys' => ['practice_field_quantity', 'practice_field_area'],
            ],
        ];

        // Điều chỉnh nhóm "Trường" theo cấp độ
        switch ($level) {
            case 'school':
                // Cấp trường: ẩn tên trường
                $baseGroups[0]['keys'] = ['school_branch'];
                break;
            case 'ward':
                // Cấp xã: hiển thị đầy đủ
                break;
            case 'province':
                // Cấp tỉnh: thay tên trường bằng tên xã, ẩn điểm trường
                $baseGroups[0]['keys'] = ['school_name'];
                break;
        }

        return $baseGroups;
    }

    /**
     * Nhãn cột theo cấu trúc mới (29 cột)
     */
    private function columns($level = 'province'): array
    {
        $baseColumns = [
            // Trường (2 cột)
            'school_name' => 'Tên trường',
            'school_branch' => 'Điểm trường',
            // Số lớp (2 cột)
            'total_classes' => 'Tổng số',
            'combined_classes' => 'Lớp ghép',
            // Số phòng học (5 cột)
            'permanent_classrooms' => 'Kiên cố',
            'semi_permanent_classrooms' => 'Bán kiên cố',
            'temporary_classrooms' => 'Tạm',
            'rented_classrooms' => 'Thuê/mượn',
            'room_class_ratio' => 'Tỷ lệ Ph/L',
            // Số phòng chức năng (11 cột)
            'principal_room' => 'HT',
            'vice_principal_room' => 'Phó HT',
            'office_room' => 'VP',
            'medical_room' => 'Y tế',
            'youth_union_room' => 'TT HĐ Đội',
            'meeting_room_quantity' => 'Phòng họp SL',
            'meeting_room_area' => 'Phòng họp DT',
            'library_room_quantity' => 'Thư viện SL',
            'library_room_area' => 'Thư viện DT',
            'equipment_room_quantity' => 'Thiết bị SL',
            'equipment_room_area' => 'Thiết bị DT',
            // Công trình vệ sinh (4 cột)
            'teacher_toilet_quantity' => 'GV SL',
            'teacher_toilet_area' => 'GV DT',
            'student_toilet_quantity' => 'HS SL',
            'student_toilet_area' => 'HS DT',
            // Sân chơi (2 cột)
            'playground_quantity' => 'Số lượng',
            'playground_area' => 'Diện tích',
            // Bãi tập (2 cột)
            'practice_field_quantity' => 'Số lượng',
            'practice_field_area' => 'Diện tích',
        ];

        // Thay đổi cột tên trường theo cấp độ
        switch ($level) {
            case 'school':
                // Cấp trường: ẩn tên trường
                $baseColumns['school_name'] = '';
                break;
            case 'ward':
                // Cấp xã: hiển thị tên trường
                $baseColumns['school_name'] = 'Tên trường';
                break;
            case 'province':
                // Cấp tỉnh: thay tên trường bằng tên xã
                $baseColumns['school_name'] = 'Tên xã/phường';
                break;
        }

        return $baseColumns;
    }

    /**
     * Tạo object báo cáo từ dữ liệu có sẵn
     */
    protected function createReportObject($report, $school, $hasData = true)
    {
        $object = (object) [
            'id' => $report->id,
            'school_id' => $school->id,
            'school' => $school,
            'ward' => $report->ward,
            'academic_year' => $report->academic_year,
            'school_name' => $school->name,
            'school_code' => $school->code,
            'school_branch' => $report->school_branch,
            'has_data' => $hasData
        ];

        // Thêm tất cả các trường báo cáo
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = $report->$field ?? 0;
        }

        return $object;
    }

    /**
     * Tạo object báo cáo rỗng cho trường
     */
    protected function createEmptyReportObject($school, $year)
    {
        $object = (object) [
            'id' => null,
            'school_id' => $school->id,
            'school' => $school,
            'ward' => null,
            'academic_year' => $year,
            'school_name' => $school->name,
            'school_code' => $school->code,
            'school_branch' => 0,
            'has_data' => false
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = 0;
        }

        return $object;
    }

    /**
     * Tạo object báo cáo rỗng cho xã
     */
    protected function createEmptyWardObject($ward, $year)
    {
        $object = (object) [
            'ward' => $ward,
            'ward_id' => $ward->id,
            'ward_name' => $ward->name,
            'academic_year' => $year,
            'school_name' => '',
            'school_branch' => 0,
            'has_data' => false
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = 0;
        }

        return $object;
    }

    /**
     * Kiểm tra báo cáo có dữ liệu không
     */
    protected function hasData($report)
    {
        $importantFields = ['total_classes', 'permanent_classrooms', 'semi_permanent_classrooms'];
        
        foreach ($importantFields as $field) {
            if (isset($report->$field) && $report->$field > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lấy danh sách các trường báo cáo
     */
    protected function getReportFields()
    {
        return [
            'total_classes', 'combined_classes', 'permanent_classrooms', 'semi_permanent_classrooms',
            'temporary_classrooms', 'rented_classrooms', 'room_class_ratio',
            'principal_room', 'vice_principal_room', 'office_room', 'medical_room',
            'youth_union_room', 'meeting_room_quantity', 'meeting_room_area',
            'library_room_quantity', 'library_room_area', 'equipment_room_quantity',
            'equipment_room_area', 'teacher_toilet_quantity', 'teacher_toilet_area',
            'student_toilet_quantity', 'student_toilet_area', 'playground_quantity',
            'playground_area', 'practice_field_quantity', 'practice_field_area'
        ];
    }

    /**
     * Lấy dữ liệu mặc định cho báo cáo mới
     */
    protected function getDefaultReportData($year, $wardId, $schoolId, $school)
    {
        $data = [
            'academic_year' => $year,
            'ward_id' => $wardId,
            'school_id' => $schoolId,
            'school_name' => $school->name,
            'school_branch' => 0,
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $data[$field] = 0;
        }

        return $data;
    }

    /**
     * Điền dữ liệu cơ sở vật chất tiểu học vào Excel cho báo cáo xã
     */
    private function fillElementaryInfrastructureDataForWard($worksheet, $reports, $level, $wardName)
    {
        $startRow = 10; // Bắt đầu từ dòng 10
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = [];

        foreach ($reports as $report) {
            if (isset($report->school_name) && $report->school_name === 'TỔNG CỘNG') {
                continue;
            }

            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            // Tên trường
            $schoolName = $report->school_name ?? '';
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':D' . $currentRow);
            
            // Điểm trường
            $schoolBranch = $report->school_branch ?? '';
            $schoolBranch = trim($schoolBranch);
            $branchValue = (is_numeric($schoolBranch) && $schoolBranch !== '' && $schoolBranch != 0) ? (int)$schoolBranch : '';
            $worksheet->setCellValue('E' . $currentRow, $branchValue);
            
            // Số lớp - Tổng số
            $worksheet->setCellValue('F' . $currentRow, $report->total_classes ?: '');
            
            // Số lớp - Lớp ghép
            $worksheet->setCellValue('G' . $currentRow, $report->combined_classes ?: '');
            
            // Số phòng học - Kiên cố
            $worksheet->setCellValue('H' . $currentRow, $report->permanent_classrooms ?: '');
            
            // Số phòng học - Bán kiên cố
            $worksheet->setCellValue('I' . $currentRow, $report->semi_permanent_classrooms ?: '');
            
            // Số phòng học - Tạm
            $worksheet->setCellValue('J' . $currentRow, $report->temporary_classrooms ?: '');
            
            // Số phòng học - Thuê/mượn
            $worksheet->setCellValue('K' . $currentRow, $report->rented_classrooms ?: '');
            
            // Số phòng học - Tỷ lệ Ph/L
            $worksheet->setCellValue('L' . $currentRow, $report->room_class_ratio ?: '');
            
            // Số phòng chức năng - HT
            $worksheet->setCellValue('M' . $currentRow, $report->principal_room ?: '');
            
            // Số phòng chức năng - Phó HT
            $worksheet->setCellValue('N' . $currentRow, $report->vice_principal_room ?: '');
            
            // Số phòng chức năng - VP
            $worksheet->setCellValue('O' . $currentRow, $report->office_room ?: '');
            
            // Số phòng chức năng - Y tế
            $worksheet->setCellValue('P' . $currentRow, $report->medical_room ?: '');
            
            // Số phòng chức năng - TT HĐ Đội
            $worksheet->setCellValue('Q' . $currentRow, $report->youth_union_room ?: '');
            
            // Phòng họp - SL
            $worksheet->setCellValue('R' . $currentRow, $report->meeting_room_quantity ?: '');
            
            // Phòng họp - DT
            $worksheet->setCellValue('S' . $currentRow, $report->meeting_room_area ?: '');
            
            // Thư viện - SL
            $worksheet->setCellValue('T' . $currentRow, $report->library_room_quantity ?: '');
            
            // Thư viện - DT
            $worksheet->setCellValue('U' . $currentRow, $report->library_room_area ?: '');
            
            // Thiết bị - SL
            $worksheet->setCellValue('V' . $currentRow, $report->equipment_room_quantity ?: '');
            
            // Thiết bị - DT
            $worksheet->setCellValue('W' . $currentRow, $report->equipment_room_area ?: '');
            
            // Công trình vệ sinh - Giáo viên - SL
            $worksheet->setCellValue('X' . $currentRow, $report->teacher_toilet_quantity ?: '');
            
            // Công trình vệ sinh - Giáo viên - DT
            $worksheet->setCellValue('Y' . $currentRow, $report->teacher_toilet_area ?: '');
            
            // Công trình vệ sinh - Học sinh - SL
            $worksheet->setCellValue('Z' . $currentRow, $report->student_toilet_quantity ?: '');
            
            // Công trình vệ sinh - Học sinh - DT
            $worksheet->setCellValue('AA' . $currentRow, $report->student_toilet_area ?: '');
            
            // Sân chơi - Số lượng
            $worksheet->setCellValue('AB' . $currentRow, $report->playground_quantity ?: '');
            
            // Sân chơi - Diện tích
            $worksheet->setCellValue('AC' . $currentRow, $report->playground_area ?: '');
            
            // Bãi tập - Số lượng
            $worksheet->setCellValue('AD' . $currentRow, $report->practice_field_quantity ?: '');
            
            // Bãi tập - Diện tích
            $worksheet->setCellValue('AE' . $currentRow, $report->practice_field_area ?: '');

            $this->setConsistentFont($worksheet, $currentRow);
            $dataRows[] = $report;
            $currentRow++;
        }

        if (!empty($dataRows)) {
            $totalRow = $this->addTotalRowForWard($worksheet, $currentRow, $dataRows, $level);
            // Thêm phần ký tên sau dòng tổng cộng
            $this->addSignatureSection($worksheet, $totalRow, $wardName);
        }
    }

    /**
     * Thêm dòng tổng cộng cho báo cáo xã
     */
    private function addTotalRowForWard($worksheet, $currentRow, $dataRows, $level)
    {
        // Cột A để trống
        $worksheet->setCellValue('A' . $currentRow, '');
        
        $worksheet->setCellValue('B' . $currentRow, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $currentRow . ':D' . $currentRow);
        
        // Căn trái cho chữ "TỔNG CỘNG"
        $worksheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $this->setBoldTotalRowFont($worksheet, $currentRow);

        $totals = $this->calculateWardTotals(collect($dataRows));

        // Điểm trường - tính tổng riêng, không dùng calculateWardTotals
        $totalBranches = collect($dataRows)->sum(function($item) {
            $schoolBranch = $item->school_branch ?? '';
            $schoolBranch = trim($schoolBranch);
            return (is_numeric($schoolBranch) && $schoolBranch !== '' && $schoolBranch != 0) ? (int)$schoolBranch : 0;
        });
        $worksheet->setCellValue('E' . $currentRow, $totalBranches > 0 ? $totalBranches : '');
        
        // Số lớp - Tổng số
        $worksheet->setCellValue('F' . $currentRow, $totals->total_classes ?? 0);
        
        // Số lớp - Lớp ghép
        $worksheet->setCellValue('G' . $currentRow, $totals->combined_classes ?? 0);
        
        // Số phòng học - Kiên cố
        $worksheet->setCellValue('H' . $currentRow, $totals->permanent_classrooms ?? 0);
        
        // Số phòng học - Bán kiên cố
        $worksheet->setCellValue('I' . $currentRow, $totals->semi_permanent_classrooms ?? 0);
        
        // Số phòng học - Tạm
        $worksheet->setCellValue('J' . $currentRow, $totals->temporary_classrooms ?? 0);
        
        // Số phòng học - Thuê/mượn
        $worksheet->setCellValue('K' . $currentRow, $totals->rented_classrooms ?? 0);
        
        // Số phòng học - Tỷ lệ Ph/L (tính trung bình)
        $worksheet->setCellValue('L' . $currentRow, $totals->room_class_ratio ?? 0);
        
        // Số phòng chức năng - HT
        $worksheet->setCellValue('M' . $currentRow, $totals->principal_room ?? 0);
        
        // Số phòng chức năng - Phó HT
        $worksheet->setCellValue('N' . $currentRow, $totals->vice_principal_room ?? 0);
        
        // Số phòng chức năng - VP
        $worksheet->setCellValue('O' . $currentRow, $totals->office_room ?? 0);
        
        // Số phòng chức năng - Y tế
        $worksheet->setCellValue('P' . $currentRow, $totals->medical_room ?? 0);
        
        // Số phòng chức năng - TT HĐ Đội
        $worksheet->setCellValue('Q' . $currentRow, $totals->youth_union_room ?? 0);
        
        // Phòng họp - SL
        $worksheet->setCellValue('R' . $currentRow, $totals->meeting_room_quantity ?? 0);
        
        // Phòng họp - DT
        $worksheet->setCellValue('S' . $currentRow, $totals->meeting_room_area ?? 0);
        
        // Thư viện - SL
        $worksheet->setCellValue('T' . $currentRow, $totals->library_room_quantity ?? 0);
        
        // Thư viện - DT
        $worksheet->setCellValue('U' . $currentRow, $totals->library_room_area ?? 0);
        
        // Thiết bị - SL
        $worksheet->setCellValue('V' . $currentRow, $totals->equipment_room_quantity ?? 0);
        
        // Thiết bị - DT
        $worksheet->setCellValue('W' . $currentRow, $totals->equipment_room_area ?? 0);
        
        // Công trình vệ sinh - Giáo viên - SL
        $worksheet->setCellValue('X' . $currentRow, $totals->teacher_toilet_quantity ?? 0);
        
        // Công trình vệ sinh - Giáo viên - DT
        $worksheet->setCellValue('Y' . $currentRow, $totals->teacher_toilet_area ?? 0);
        
        // Công trình vệ sinh - Học sinh - SL
        $worksheet->setCellValue('Z' . $currentRow, $totals->student_toilet_quantity ?? 0);
        
        // Công trình vệ sinh - Học sinh - DT
        $worksheet->setCellValue('AA' . $currentRow, $totals->student_toilet_area ?? 0);
        
        // Sân chơi - Số lượng
        $worksheet->setCellValue('AB' . $currentRow, $totals->playground_quantity ?? 0);
        
        // Sân chơi - Diện tích
        $worksheet->setCellValue('AC' . $currentRow, $totals->playground_area ?? 0);
        
        // Bãi tập - Số lượng
        $worksheet->setCellValue('AD' . $currentRow, $totals->practice_field_quantity ?? 0);
        
        // Bãi tập - Diện tích
        $worksheet->setCellValue('AE' . $currentRow, $totals->practice_field_area ?? 0);
        
        return $currentRow;
    }

    /**
     * Cài đặt rows to repeat at top cho tiêu đề
     */
    private function setupPrintTitles($worksheet)
    {
        // Cài đặt rows to repeat at top cho dòng 6-9 (tiêu đề)
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 9);
    }

    /**
     * Thêm phần ký tên vào cuối báo cáo
     */
    private function addSignatureSection($worksheet, $totalRow, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô W(x+2)
        $dateRow = $totalRow + 2;
        $worksheet->setCellValue('W' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô I(x+3), in đậm
        $signatureRow1 = $totalRow + 3;
        $worksheet->setCellValue('I' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('I' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô W(x+3), in đậm
        $worksheet->setCellValue('W' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('W' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô I(x+4), in nghiêng
        $signatureRow2 = $totalRow + 4;
        $worksheet->setCellValue('I' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('I' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô W(x+4), in đậm
        $worksheet->setCellValue('W' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('W' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô W(x+5), in nghiêng
        $signatureRow3 = $totalRow + 5;
        $worksheet->setCellValue('W' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('W' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'I' . $signatureRow1 . ':W' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('I' . $row . ':W' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('I' . $row . ':W' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Reset format vùng ký tên (A15:T17) về bình thường
     */
    private function resetSignatureAreaFormat($worksheet)
    {
        // Reset format cho vùng A15:T17
        $signatureRange = 'A15:T17';
        $style = $worksheet->getStyle($signatureRange);
        
        // Reset font về bình thường
        $style->getFont()->setBold(false);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Reset alignment về mặc định
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $style->getAlignment()->setIndent(0);
        
        // Reset borders về mặc định
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
        
        // Reset number format về text
        $style->getNumberFormat()->setFormatCode('@');
        
        // Xóa nội dung các ô trong vùng này để giữ nguyên cấu trúc ký tên
        for ($row = 15; $row <= 17; $row++) {
            for ($col = 'A'; $col <= 'T'; $col++) {
                $cell = $col . $row;
                $worksheet->setCellValue($cell, '');
            }
        }
    }

    /**
     * Điền dữ liệu cơ sở vật chất tiểu học vào Excel
     */
    private function fillElementaryInfrastructureData($worksheet, $reports, $level)
    {
        $startRow = 10; // Bắt đầu từ dòng 10
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = [];

        foreach ($reports as $report) {
            if (isset($report->school_name) && $report->school_name === 'TỔNG CỘNG') {
                continue;
            }

            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            $schoolName = '';
            if ($level === 'province') {
                $schoolName = $report->ward_name ?? '';
            } else {
                $schoolName = $report->school_name ?? '';
            }
            
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':D' . $currentRow);
            
            // Điểm trường
            $worksheet->setCellValue('E' . $currentRow, $report->school_branch ?? 0);
            
            // Số lớp - Tổng số
            $worksheet->setCellValue('F' . $currentRow, $report->total_classes ?? 0);
            
            // Số lớp - Lớp ghép
            $worksheet->setCellValue('G' . $currentRow, $report->combined_classes ?? 0);
            
            // Số phòng học - Kiên cố
            $worksheet->setCellValue('H' . $currentRow, $report->permanent_classrooms ?? 0);
            
            // Số phòng học - Bán kiên cố
            $worksheet->setCellValue('I' . $currentRow, $report->semi_permanent_classrooms ?? 0);
            
            // Số phòng học - Tạm
            $worksheet->setCellValue('J' . $currentRow, $report->temporary_classrooms ?? 0);
            
            // Số phòng học - Thuê/mượn
            $worksheet->setCellValue('K' . $currentRow, $report->rented_classrooms ?? 0);
            
            // Số phòng học - Tỷ lệ Ph/L
            $worksheet->setCellValue('L' . $currentRow, $report->room_class_ratio ?? 0);
            
            // Số phòng chức năng - HT
            $worksheet->setCellValue('M' . $currentRow, $report->principal_room ?? 0);
            
            // Số phòng chức năng - Phó HT
            $worksheet->setCellValue('N' . $currentRow, $report->vice_principal_room ?? 0);
            
            // Số phòng chức năng - VP
            $worksheet->setCellValue('O' . $currentRow, $report->office_room ?? 0);
            
            // Số phòng chức năng - Y tế
            $worksheet->setCellValue('P' . $currentRow, $report->medical_room ?? 0);
            
            // Số phòng chức năng - TT HĐ Đội
            $worksheet->setCellValue('Q' . $currentRow, $report->youth_union_room ?? 0);
            
            // Phòng họp - SL
            $worksheet->setCellValue('R' . $currentRow, $report->meeting_room_quantity ?? 0);
            
            // Phòng họp - DT
            $worksheet->setCellValue('S' . $currentRow, $report->meeting_room_area ?? 0);
            
            // Thư viện - SL
            $worksheet->setCellValue('T' . $currentRow, $report->library_room_quantity ?? 0);
            
            // Thư viện - DT
            $worksheet->setCellValue('U' . $currentRow, $report->library_room_area ?? 0);
            
            // Thiết bị - SL
            $worksheet->setCellValue('V' . $currentRow, $report->equipment_room_quantity ?? 0);
            
            // Thiết bị - DT
            $worksheet->setCellValue('W' . $currentRow, $report->equipment_room_area ?? 0);
            
            // Công trình vệ sinh - Giáo viên - SL
            $worksheet->setCellValue('X' . $currentRow, $report->teacher_toilet_quantity ?? 0);
            
            // Công trình vệ sinh - Giáo viên - DT
            $worksheet->setCellValue('Y' . $currentRow, $report->teacher_toilet_area ?? 0);
            
            // Công trình vệ sinh - Học sinh - SL
            $worksheet->setCellValue('Z' . $currentRow, $report->student_toilet_quantity ?? 0);
            
            // Công trình vệ sinh - Học sinh - DT
            $worksheet->setCellValue('AA' . $currentRow, $report->student_toilet_area ?? 0);
            
            // Sân chơi - Số lượng
            $worksheet->setCellValue('AB' . $currentRow, $report->playground_quantity ?? 0);
            
            // Sân chơi - Diện tích
            $worksheet->setCellValue('AC' . $currentRow, $report->playground_area ?? 0);
            
            // Bãi tập - Số lượng
            $worksheet->setCellValue('AD' . $currentRow, $report->practice_field_quantity ?? 0);
            
            // Bãi tập - Diện tích
            $worksheet->setCellValue('AE' . $currentRow, $report->practice_field_area ?? 0);
            
            $this->setConsistentFont($worksheet, $currentRow);
            $dataRows[] = $report;
            $currentRow++;
        }

        // Chỉ thêm dòng tổng cộng cho cấp xã, không thêm cho cấp trường
        if (!empty($dataRows) && $level !== 'school') {
            $this->addTotalRow($worksheet, $currentRow, $dataRows, $level);
        }
    }

    /**
     * Thêm dòng tổng cộng
     */
    private function addTotalRow($worksheet, $row, $dataRows, $level)
    {
        // Cột TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':D' . $row);
        
        $this->setBoldTotalRowFont($worksheet, $row);

        $totals = $this->calculateWardTotals(collect($dataRows));

        // Điểm trường - tính tổng
        $worksheet->setCellValue('E' . $row, $totals->school_branch ?? 0);
        
        // Số lớp - Tổng số
        $worksheet->setCellValue('F' . $row, $totals->total_classes ?? 0);
        
        // Số lớp - Lớp ghép
        $worksheet->setCellValue('G' . $row, $totals->combined_classes ?? 0);
        
        // Số phòng học - Kiên cố
        $worksheet->setCellValue('H' . $row, $totals->permanent_classrooms ?? 0);
        
        // Số phòng học - Bán kiên cố
        $worksheet->setCellValue('I' . $row, $totals->semi_permanent_classrooms ?? 0);
        
        // Số phòng học - Tạm
        $worksheet->setCellValue('J' . $row, $totals->temporary_classrooms ?? 0);
        
        // Số phòng học - Thuê/mượn
        $worksheet->setCellValue('K' . $row, $totals->rented_classrooms ?? 0);
        
        // Số phòng học - Tỷ lệ Ph/L (tính trung bình)
        $worksheet->setCellValue('L' . $row, $totals->room_class_ratio ?? 0);
        
        // Số phòng chức năng - HT
        $worksheet->setCellValue('M' . $row, $totals->principal_room ?? 0);
        
        // Số phòng chức năng - Phó HT
        $worksheet->setCellValue('N' . $row, $totals->vice_principal_room ?? 0);
        
        // Số phòng chức năng - VP
        $worksheet->setCellValue('O' . $row, $totals->office_room ?? 0);
        
        // Số phòng chức năng - Y tế
        $worksheet->setCellValue('P' . $row, $totals->medical_room ?? 0);
        
        // Số phòng chức năng - TT HĐ Đội
        $worksheet->setCellValue('Q' . $row, $totals->youth_union_room ?? 0);
        
        // Phòng họp - SL
        $worksheet->setCellValue('R' . $row, $totals->meeting_room_quantity ?? 0);
        
        // Phòng họp - DT
        $worksheet->setCellValue('S' . $row, $totals->meeting_room_area ?? 0);
        
        // Thư viện - SL
        $worksheet->setCellValue('T' . $row, $totals->library_room_quantity ?? 0);
        
        // Thư viện - DT
        $worksheet->setCellValue('U' . $row, $totals->library_room_area ?? 0);
        
        // Thiết bị - SL
        $worksheet->setCellValue('V' . $row, $totals->equipment_room_quantity ?? 0);
        
        // Thiết bị - DT
        $worksheet->setCellValue('W' . $row, $totals->equipment_room_area ?? 0);
        
        // Công trình vệ sinh - Giáo viên - SL
        $worksheet->setCellValue('X' . $row, $totals->teacher_toilet_quantity ?? 0);
        
        // Công trình vệ sinh - Giáo viên - DT
        $worksheet->setCellValue('Y' . $row, $totals->teacher_toilet_area ?? 0);
        
        // Công trình vệ sinh - Học sinh - SL
        $worksheet->setCellValue('Z' . $row, $totals->student_toilet_quantity ?? 0);
        
        // Công trình vệ sinh - Học sinh - DT
        $worksheet->setCellValue('AA' . $row, $totals->student_toilet_area ?? 0);
        
        // Sân chơi - Số lượng
        $worksheet->setCellValue('AB' . $row, $totals->playground_quantity ?? 0);
        
        // Sân chơi - Diện tích
        $worksheet->setCellValue('AC' . $row, $totals->playground_area ?? 0);
        
        // Bãi tập - Số lượng
        $worksheet->setCellValue('AD' . $row, $totals->practice_field_quantity ?? 0);
        
        // Bãi tập - Diện tích
        $worksheet->setCellValue('AE' . $row, $totals->practice_field_area ?? 0);
        
        // Thêm phần ký tên từ dòng 14 đến 17
        $this->addSignatureSectionForSchool($worksheet);
    }

    /**
     * Thêm phần ký tên cho báo cáo trường Tiểu học (dòng 14-17)
     */
    private function addSignatureSectionForSchool($worksheet)
    {
        // Lấy tên xã từ ô D3
        $wardName = $worksheet->getCell('D3')->getValue();
        
        // Tên xã, ngày ... tháng ... năm ... vào ô W14
        $dateRow = 14;
        $worksheet->setCellValue('W' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô I15, in đậm
        $signatureRow1 = 15;
        $worksheet->setCellValue('I' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('I' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô W15, in đậm
        $worksheet->setCellValue('W' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('W' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô I16, in nghiêng
        $signatureRow2 = 16;
        $worksheet->setCellValue('I' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('I' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô W16, in đậm
        $worksheet->setCellValue('W' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('W' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô W17, in nghiêng
        $signatureRow3 = 17;
        $worksheet->setCellValue('W' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('W' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'I' . $signatureRow1 . ':W' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên (dòng 14-17)
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('I' . $row . ':W' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('I' . $row . ':W' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Đảm bảo font và căn lề nhất quán cho tất cả các ô trong dòng
     */
    private function setConsistentFont($worksheet, $row)
    {
        // Đặt font mặc định cho toàn bộ dòng (A đến AE)
        $range = 'A' . $row . ':AE' . $row;
        $style = $worksheet->getStyle($range);
        
        // Đặt chiều cao dòng là 18.60px
        $worksheet->getRowDimension($row)->setRowHeight(18.60);
        
        // Reset font về mặc định
        $style->getFont()->setBold(false);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Đặt padding cho các dòng từ dòng 9 trở đi
        if ($row >= 9) {
            $style->getAlignment()->setIndent(0);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Tên trường - căn trái (merge B-D)
            'C' => 'left',      // Tên trường (merged) - căn trái
            'D' => 'left',      // Tên trường (merged) - căn trái
            'E' => 'center',    // Điểm trường - căn giữa
            'F' => 'center',    // Số liệu - căn giữa
            'G' => 'center',
            'H' => 'center',
            'I' => 'center',
            'J' => 'center',
            'K' => 'center',
            'L' => 'center',
            'M' => 'center',
            'N' => 'center',
            'O' => 'center',
            'P' => 'center',
            'Q' => 'center',
            'R' => 'center',
            'S' => 'center',
            'T' => 'center',
            'U' => 'center',
            'V' => 'center',
            'W' => 'center',
            'X' => 'center',
            'Y' => 'center',
            'Z' => 'center',
            'AA' => 'center',
            'AB' => 'center',
            'AC' => 'center',
            'AD' => 'center',
            'AE' => 'center'    // Cột cuối - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings
            $cellStyle->getFont()->setBold(false);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Tahoma');
            $cellStyle->getFont()->setSize(8);
            
            // Alignment settings
            if ($alignment === 'left') {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Padding cho các dòng từ dòng 9 trở đi
            if ($row >= 9) {
                $cellStyle->getAlignment()->setIndent(0);
                $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            
            // Number format cho các cột số
            if ($col === 'L') {
                // Cột L: Tỷ lệ Ph/L - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['F', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'P', 'Q', 'R', 'T', 'V', 'X', 'Z', 'AB', 'AD'])) {
                // Các cột số khác - format số nguyên
                $cellStyle->getNumberFormat()->setFormatCode('0');
            } elseif (in_array($col, ['S', 'U', 'W', 'Y', 'AA', 'AC', 'AE'])) {
                // Các cột diện tích - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
    }

    /**
     * Đặt font in đậm cho dòng tổng cộng
     */
    private function setBoldTotalRowFont($worksheet, $row)
    {
        // Đặt font in đậm cho toàn bộ dòng (A đến AE)
        $range = 'A' . $row . ':AE' . $row;
        $style = $worksheet->getStyle($range);
        
        // Đặt chiều cao dòng là 18.60px
        $worksheet->getRowDimension($row)->setRowHeight(18.60);
        
        // Font settings - in đậm
        $style->getFont()->setBold(true);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Đặt padding cho các dòng từ dòng 9 trở đi
        if ($row >= 9) {
            $style->getAlignment()->setIndent(0);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Tên trường - căn trái (merge B-D)
            'C' => 'left',      // Tên trường (merged) - căn trái
            'D' => 'left',      // Tên trường (merged) - căn trái
            'E' => 'center',    // Điểm trường - căn giữa
            'F' => 'center',    // Số liệu - căn giữa
            'G' => 'center',
            'H' => 'center',
            'I' => 'center',
            'J' => 'center',
            'K' => 'center',
            'L' => 'center',
            'M' => 'center',
            'N' => 'center',
            'O' => 'center',
            'P' => 'center',
            'Q' => 'center',
            'R' => 'center',
            'S' => 'center',
            'T' => 'center',
            'U' => 'center',
            'V' => 'center',
            'W' => 'center',
            'X' => 'center',
            'Y' => 'center',
            'Z' => 'center',
            'AA' => 'center',
            'AB' => 'center',
            'AC' => 'center',
            'AD' => 'center',
            'AE' => 'center'    // Cột cuối - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings - in đậm
            $cellStyle->getFont()->setBold(true);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Tahoma');
            $cellStyle->getFont()->setSize(8);
            
            // Alignment settings
            if ($alignment === 'left') {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Padding cho các dòng từ dòng 9 trở đi
            if ($row >= 9) {
                $cellStyle->getAlignment()->setIndent(0);
                $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            
            // Number format cho các cột số
            if ($col === 'L') {
                // Cột L: Tỷ lệ Ph/L - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['F', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'P', 'Q', 'R', 'T', 'V', 'X', 'Z', 'AB', 'AD'])) {
                // Các cột số khác - format số nguyên
                $cellStyle->getNumberFormat()->setFormatCode('0');
            } elseif (in_array($col, ['S', 'U', 'W', 'Y', 'AA', 'AC', 'AE'])) {
                // Các cột diện tích - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
    }

    /**
     * Lấy tên tỉnh
     */
    private function getProvinceName($provinceId)
    {
        if (!$provinceId) {
            return '';
        }
        
        $province = GeoUnit::find($provinceId);
        return $province ? $province->name : '';
    }

    /**
     * Lấy tên phường/xã
     */
    private function getWardName($wardId)
    {
        if (!$wardId) {
            return '';
        }
        
        $ward = GeoUnit::find($wardId);
        return $ward ? $ward->name : '';
    }

    /**
     * Nhập dữ liệu từ Excel
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            $file = $request->file('excel_file');
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();

            // Đọc dữ liệu từ E10:AE10
            $row = 10;
            $data = [];
            
            // Mapping các cột E đến AE (5 đến 31)
            $columnMapping = [
                'E' => 'school_branch',
                'F' => 'total_classes', 
                'G' => 'combined_classes',
                'H' => 'permanent_classrooms',
                'I' => 'semi_permanent_classrooms',
                'J' => 'temporary_classrooms',
                'K' => 'rented_classrooms',
                'L' => 'classroom_class_ratio',
                'M' => 'principal_room',
                'N' => 'vice_principal_room',
                'O' => 'office_room',
                'P' => 'medical_room',
                'Q' => 'youth_union_room',
                'R' => 'meeting_room_quantity',
                'S' => 'meeting_room_area',
                'T' => 'library_room_quantity',
                'U' => 'library_room_area',
                'V' => 'equipment_room_quantity',
                'W' => 'equipment_room_area',
                'X' => 'teacher_toilet_quantity',
                'Y' => 'teacher_toilet_area',
                'Z' => 'student_toilet_quantity',
                'AA' => 'student_toilet_area',
                'AB' => 'playground_quantity',
                'AC' => 'playground_area',
                'AD' => 'practice_field_quantity',
                'AE' => 'practice_field_area'
            ];

            foreach ($columnMapping as $col => $field) {
                $value = $worksheet->getCell($col . $row)->getCalculatedValue();
                
                // Xử lý kiểu dữ liệu
                if (in_array($field, ['school_branch', 'total_classes', 'combined_classes', 'permanent_classrooms', 
                    'semi_permanent_classrooms', 'temporary_classrooms', 'rented_classrooms', 'principal_room',
                    'vice_principal_room', 'office_room', 'medical_room', 'youth_union_room', 'meeting_room_quantity',
                    'library_room_quantity', 'equipment_room_quantity', 'teacher_toilet_quantity', 'student_toilet_quantity',
                    'playground_quantity', 'practice_field_quantity'])) {
                    // Các trường số nguyên
                    $data[$field] = is_numeric($value) ? (int)$value : 0;
                } elseif (in_array($field, ['classroom_class_ratio', 'meeting_room_area', 'library_room_area',
                    'equipment_room_area', 'teacher_toilet_area', 'student_toilet_area', 'playground_area',
                    'practice_field_area'])) {
                    // Các trường số thập phân
                    $data[$field] = is_numeric($value) ? (float)$value : 0;
                } else {
                    $data[$field] = $value;
                }
            }

            // Lấy thông tin trường từ user đã đăng nhập
            $user = Auth::user();
            $school = $user->school;
            
            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin trường của tài khoản'
                ], 400);
            }

            // Kiểm tra trường có phải Tiểu học không
            $primaryLevelIds = $this->getPrimaryLevelIds();
            if (!in_array($school->educational_level_id, $primaryLevelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trường này không phải là trường Tiểu học'
                ], 400);
            }

            $academicYear = $request->input('year');
            
            // Validate năm học format
            if (!$academicYear || !preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Năm học không hợp lệ. Vui lòng chọn năm học đúng định dạng.'
                ], 400);
            }

            // Thêm thông tin bắt buộc
            $data['school_id'] = $school->id;
            $data['ward_id'] = $school->getWardId();
            $data['academic_year'] = $academicYear;
            $data['school_name'] = $school->name;
            
            // Tìm hoặc tạo bản ghi
            $report = ElementaryInfrastructureReport::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_year' => $academicYear,
                ],
                $data
            );

            return response()->json([
                'success' => true,
                'message' => 'Nhập dữ liệu thành công!',
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Import Excel error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi nhập dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }
}
