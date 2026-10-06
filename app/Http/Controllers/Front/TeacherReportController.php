<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\TeacherReport;
use App\Models\GeoUnit;
use App\Models\EducationalLevel;
use App\Services\GeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeacherReportController extends Controller
{
    protected $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }

    /**
     * Lấy danh sách ID của các cấp học Tiểu học
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
     * Hiển thị danh sách báo cáo đội ngũ giáo viên
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


        // Xác định cấp độ hiển thị dựa trên user role và filter
        $user = Auth::user();
        $level = $this->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->getReportsByLevel($level, $year, $q, $geoIds, $scheme, $perPage);
        $columns = $this->columns($level);
        $groups = $this->columnGroups($level);
        $stats = $this->getStatistics($year, $geoIds['ward']);

        return view('front.teacher-reports.index', compact(
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
            'school_rank' => 'nullable|numeric',
            'sessions_per_day' => 'nullable|numeric',
            'principal' => 'nullable|numeric',
            'vice_principal' => 'nullable|numeric',
            'total_teachers' => 'nullable|numeric',
            'teacher_quota' => 'nullable|numeric',
            'teacher_contract' => 'nullable|numeric',
            'female_teachers' => 'nullable|numeric',
            'ethnic_teachers' => 'nullable|numeric',
            'teacher_student_ratio' => 'nullable|numeric',
            'training_above_uni' => 'nullable|numeric',
            'training_uni' => 'nullable|numeric',
            'training_college' => 'nullable|numeric',
            'training_ped' => 'nullable|numeric',
            'training_below_ped' => 'nullable|numeric',
            'training_primary' => 'nullable|numeric',
            'training_an' => 'nullable|numeric',
            'training_mt' => 'nullable|numeric',
            'training_td' => 'nullable|numeric',
            'training_informatics' => 'nullable|numeric',
            'training_foreign_lang' => 'nullable|numeric',
            'training_other' => 'nullable|numeric',
            'standard_good' => 'nullable|numeric',
            'standard_quite' => 'nullable|numeric',
            'standard_reach' => 'nullable|numeric',
            'standard_not_reach' => 'nullable|numeric',
            'tpt_team' => 'nullable|numeric',
            'office_staff' => 'nullable|numeric',
            'library_other_staff' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                'message' => 'Dữ liệu không hợp lệ: ' . $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
        }

        try {
            // Năm học đã được format từ frontend
            $academicYear = $request->academic_year;
            
            // Lấy thông tin trường
            $school = \App\Models\School::find($request->school_id);
            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy trường học'
                ], 404);
            }

            // Tìm hoặc tạo báo cáo
            $report = TeacherReport::updateOrCreate(
                [
                    'academic_year' => $academicYear,
                    'ward_id' => $request->ward_id,
                    'school_id' => $request->school_id,
                ],
                [
                    'school_name' => $school->name,
                    'school_rank' => $request->school_rank ?? 0,
                    'sessions_per_day' => $request->sessions_per_day ?? 0,
                    'principal' => $request->principal ?? 0,
                    'vice_principal' => $request->vice_principal ?? 0,
                    'total_teachers' => $request->total_teachers ?? 0,
                    'teacher_quota' => $request->teacher_quota ?? 0,
                    'teacher_contract' => $request->teacher_contract ?? 0,
                    'female_teachers' => $request->female_teachers ?? 0,
                    'ethnic_teachers' => $request->ethnic_teachers ?? 0,
                    'teacher_student_ratio' => $request->teacher_student_ratio ?? 0,
                    'training_above_uni' => $request->training_above_uni ?? 0,
                    'training_uni' => $request->training_uni ?? 0,
                    'training_college' => $request->training_college ?? 0,
                    'training_ped' => $request->training_ped ?? 0,
                    'training_below_ped' => $request->training_below_ped ?? 0,
                    'training_primary' => $request->training_primary ?? 0,
                    'training_an' => $request->training_an ?? 0,
                    'training_mt' => $request->training_mt ?? 0,
                    'training_td' => $request->training_td ?? 0,
                    'training_informatics' => $request->training_informatics ?? 0,
                    'training_foreign_lang' => $request->training_foreign_lang ?? 0,
                    'training_other' => $request->training_other ?? 0,
                    'standard_good' => $request->standard_good ?? 0,
                    'standard_quite' => $request->standard_quite ?? 0,
                    'standard_reach' => $request->standard_reach ?? 0,
                    'standard_not_reach' => $request->standard_not_reach ?? 0,
                    'tpt_team' => $request->tpt_team ?? 0,
                    'office_staff' => $request->office_staff ?? 0,
                    'library_other_staff' => $request->library_other_staff ?? 0,
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
    private function getStatistics($year = null, $wardId = null)
    {
        $query = TeacherReport::query()
            ->select([
                DB::raw('SUM(total_teachers) as total_gv'),
                DB::raw('SUM(female_teachers) as total_nu'),
                DB::raw('AVG(teacher_student_ratio) as avg_ty_le'),
                DB::raw('SUM(standard_reach) + SUM(standard_good) + SUM(standard_quite) as total_dat_chuan'),
                'ward_id',
                'school_name',
            ])
            ->when($year, fn($q) => $q->where('academic_year', $year))
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->groupBy('ward_id', 'school_name');

        return $query->get()->groupBy('ward_id');
    }




    /**
     * Nhóm cột với cấp 2 và cấp 3 cho Giáo viên
     */
    private function columnGroups($level = 'province'): array
    {
        $baseGroups = [
            [
                'title' => 'Đơn vị',
                'keys' => ['school_name', 'empty_field', 'school_rank', 'sessions_per_day'],
            ],
            [
                'title' => 'CBQL',
                'keys' => ['principal', 'vice_principal'],
            ],
            [
                'title' => 'Giáo viên',
                'subgroups' => [
                    [
                        'title' => 'Tổng quát',
                        'keys' => ['total_teachers', 'teacher_quota', 'teacher_contract', 'female_teachers', 'ethnic_teachers', 'teacher_student_ratio'],
                    ],
                    [
                        'title' => 'Trình độ đào tạo',
                        'keys' => ['training_above_uni', 'training_uni', 'training_college', 'training_ped', 'training_below_ped'],
                    ],
                    [
                        'title' => 'Loại hình đào tạo',
                        'keys' => ['training_primary', 'training_an', 'training_mt', 'training_td', 'training_informatics', 'training_foreign_lang', 'training_other'],
                    ],
                    [
                        'title' => 'Chuẩn nghề nghiệp',
                        'keys' => ['standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach'],
                    ],
                    [
                        'title' => 'TPT Đội',
                        'keys' => ['tpt_team'],
                    ],
                ],
            ],
            [
                'title' => 'Nhân viên',
                'keys' => ['office_staff', 'library_other_staff'],
            ],
        ];

        // Điều chỉnh nhóm "Đơn vị" theo cấp độ
        switch ($level) {
            case 'school':
                // Cấp trường: ẩn tên trường và hạng trường
                $baseGroups[0]['keys'] = ['empty_field', 'sessions_per_day'];
                break;
            case 'ward':
                // Cấp xã: hiển thị đầy đủ
                break;
            case 'province':
                // Cấp tỉnh: thay tên trường bằng tên xã, ẩn hạng trường
                $baseGroups[0]['keys'] = ['school_name', 'empty_field', 'sessions_per_day'];
                break;
        }

        return $baseGroups;
    }

    /**
     * Xác định cấp độ hiển thị dựa trên user role và filter
     */
    private function determineDisplayLevel($user, $geoIds)
    {

        // Ưu tiên theo filter được chọn (theo thứ tự từ cụ thể đến chung)
        // Nếu có school_id được chọn -> cấp trường (ưu tiên cao nhất)
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

        // Lấy danh sách ID của các cấp học Tiểu học
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
        $school = \App\Models\School::active()
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

        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }
        
        // Tìm báo cáo hiện có hoặc tạo mới
        $report = TeacherReport::where('academic_year', $queryYear)
            ->where('ward_id', $wardId)
            ->where('school_id', $geoIds['school'])
            ->first();

        if (!$report) {
            // Tạo báo cáo mới với dữ liệu mặc định
            $report = new TeacherReport([
                'academic_year' => $queryYear,
                'ward_id' => $wardId,
                'school_id' => $geoIds['school'],
                'school_name' => $school->name,
                'school_rank' => 0,
                'sessions_per_day' => 0,
                'principal' => 0,
                'vice_principal' => 0,
                'total_teachers' => 0,
                'teacher_quota' => 0,
                'teacher_contract' => 0,
                'female_teachers' => 0,
                'ethnic_teachers' => 0,
                'teacher_student_ratio' => 0,
                'training_above_uni' => 0,
                'training_uni' => 0,
                'training_college' => 0,
                'training_ped' => 0,
                'training_below_ped' => 0,
                'training_primary' => 0,
                'training_an' => 0,
                'training_mt' => 0,
                'training_td' => 0,
                'training_informatics' => 0,
                'training_foreign_lang' => 0,
                'training_other' => 0,
                'standard_good' => 0,
                'standard_quite' => 0,
                'standard_reach' => 0,
                'standard_not_reach' => 0,
                'tpt_team' => 0,
                'office_staff' => 0,
                'library_other_staff' => 0,
                'total_classes' => 0,
                'total_students' => 0,
                'total_rooms' => 0,
                'total_equipment' => 0,
                'total_books' => 0,
                'total_computers' => 0,
                'total_internet' => 0,
                'total_laboratories' => 0,
                'total_libraries' => 0,
                'total_playgrounds' => 0,
                'total_cafeterias' => 0,
                'total_dormitories' => 0,
                'total_other_facilities' => 0,
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

        // Lấy ward_code từ ward_id
        $ward = \App\Models\GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả trường Tiểu học trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schoolsQuery = \App\Models\School::active()
            ->where('ward_code_new', $ward->code)
            ->whereIn('educational_level_id', $primaryLevelIds);
        
        if ($q) {
            $schoolsQuery->where('name', 'like', "%{$q}%");
        }
        
        $schools = $schoolsQuery->orderBy('name')->get();
        
        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }
        
        // Lấy báo cáo hiện có cho các trường này
        $existingReports = TeacherReport::with(['school', 'ward'])
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
                    'school_rank' => $report->school_rank,
                    'sessions_per_day' => $report->sessions_per_day,
                    'principal' => $report->principal,
                    'vice_principal' => $report->vice_principal,
                    'total_teachers' => $report->total_teachers,
                    'teacher_quota' => $report->teacher_quota,
                    'teacher_contract' => $report->teacher_contract,
                    'female_teachers' => $report->female_teachers,
                    'ethnic_teachers' => $report->ethnic_teachers,
                    'teacher_student_ratio' => $report->teacher_student_ratio,
                    'training_above_uni' => $report->training_above_uni,
                    'training_uni' => $report->training_uni,
                    'training_college' => $report->training_college,
                    'training_ped' => $report->training_ped,
                    'training_below_ped' => $report->training_below_ped,
                    'training_primary' => $report->training_primary,
                    'training_an' => $report->training_an,
                    'training_mt' => $report->training_mt,
                    'training_td' => $report->training_td,
                    'training_informatics' => $report->training_informatics,
                    'training_foreign_lang' => $report->training_foreign_lang,
                    'training_other' => $report->training_other,
                    'standard_good' => $report->standard_good,
                    'standard_quite' => $report->standard_quite,
                    'standard_reach' => $report->standard_reach,
                    'standard_not_reach' => $report->standard_not_reach,
                    'tpt_team' => $report->tpt_team,
                    'office_staff' => $report->office_staff,
                    'library_other_staff' => $report->library_other_staff,
                    'total_classes' => $report->total_classes,
                    'total_students' => $report->total_students,
                    'total_rooms' => $report->total_rooms,
                    'total_equipment' => $report->total_equipment,
                    'total_books' => $report->total_books,
                    'total_computers' => $report->total_computers,
                    'total_internet' => $report->total_internet,
                    'total_laboratories' => $report->total_laboratories,
                    'total_libraries' => $report->total_libraries,
                    'total_playgrounds' => $report->total_playgrounds,
                    'total_cafeterias' => $report->total_cafeterias,
                    'total_dormitories' => $report->total_dormitories,
                    'total_other_facilities' => $report->total_other_facilities,
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
                    'school_rank' => '',
                    'sessions_per_day' => 0,
                    'principal' => 0,
                    'vice_principal' => 0,
                    'total_teachers' => 0,
                    'teacher_quota' => 0,
                    'teacher_contract' => 0,
                    'female_teachers' => 0,
                    'ethnic_teachers' => 0,
                    'teacher_student_ratio' => 0,
                    'training_above_uni' => 0,
                    'training_uni' => 0,
                    'training_college' => 0,
                    'training_ped' => 0,
                    'training_below_ped' => 0,
                    'training_primary' => 0,
                    'training_an' => 0,
                    'training_mt' => 0,
                    'training_td' => 0,
                    'training_informatics' => 0,
                    'training_foreign_lang' => 0,
                    'training_other' => 0,
                    'standard_good' => 0,
                    'standard_quite' => 0,
                    'standard_reach' => 0,
                    'standard_not_reach' => 0,
                    'tpt_team' => 0,
                    'office_staff' => 0,
                    'library_other_staff' => 0,
                    'total_classes' => 0,
                    'total_students' => 0,
                    'total_rooms' => 0,
                    'total_equipment' => 0,
                    'total_books' => 0,
                    'total_computers' => 0,
                    'total_internet' => 0,
                    'total_laboratories' => 0,
                    'total_libraries' => 0,
                    'total_playgrounds' => 0,
                    'total_cafeterias' => 0,
                    'total_dormitories' => 0,
                    'total_other_facilities' => 0,
                    'has_data' => false
                ];
            }
        });
        
        // Tính tổng cộng từ tất cả các báo cáo
        $totalRow = $this->calculateWardTotals($existingReports);
        $totalRow->school_name = 'TỔNG CỘNG';
        $totalRow->school_code = '';
        $totalRow->school_rank = '';
        $totalRow->sessions_per_day = $existingReports->sum('sessions_per_day');
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

        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy tất cả phường/xã trong tỉnh
        $wardsQuery = \App\Models\GeoUnit::where('parent_id', $geoIds['province'])
            ->where('scheme', $scheme)
            ->where('type_level', 3); // Chỉ lấy cấp phường/xã
        
        if ($q) {
            $wardsQuery->where('name', 'like', "%{$q}%");
        }
        
        $wards = $wardsQuery->orderBy('name')->get();
        
        // Chuyển đổi format năm học từ YYYY thành YYYY-YYYY để query
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }
        
        // Lấy tất cả báo cáo trong tỉnh chỉ cho trường Tiểu học (chỉ trường đang hoạt động)
        $reportsQuery = TeacherReport::with(['ward', 'school'])
            ->where('academic_year', $queryYear)
            ->whereHas('school', function ($schoolQuery) use ($primaryLevelIds) {
                $schoolQuery->active()->whereIn('educational_level_id', $primaryLevelIds);
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
                    'school_rank' => '',
                    'sessions_per_day' => 0,
                    'principal' => 0,
                    'vice_principal' => 0,
                    'total_teachers' => 0,
                    'teacher_quota' => 0,
                    'teacher_contract' => 0,
                    'female_teachers' => 0,
                    'ethnic_teachers' => 0,
                    'teacher_student_ratio' => 0,
                    'training_above_uni' => 0,
                    'training_uni' => 0,
                    'training_college' => 0,
                    'training_ped' => 0,
                    'training_below_ped' => 0,
                    'training_primary' => 0,
                    'training_an' => 0,
                    'training_mt' => 0,
                    'training_td' => 0,
                    'training_informatics' => 0,
                    'training_foreign_lang' => 0,
                    'training_other' => 0,
                    'standard_good' => 0,
                    'standard_quite' => 0,
                    'standard_reach' => 0,
                    'standard_not_reach' => 0,
                    'tpt_team' => 0,
                    'office_staff' => 0,
                    'library_other_staff' => 0,
                    'total_classes' => 0,
                    'total_students' => 0,
                    'total_rooms' => 0,
                    'total_equipment' => 0,
                    'total_books' => 0,
                    'total_computers' => 0,
                    'total_internet' => 0,
                    'total_laboratories' => 0,
                    'total_libraries' => 0,
                    'total_playgrounds' => 0,
                    'total_cafeterias' => 0,
                    'total_dormitories' => 0,
                    'total_other_facilities' => 0,
                    'has_data' => false
                ];
            }
        });
        
        // Tính tổng cộng cho toàn tỉnh
        $provinceTotal = $this->calculateWardTotals($reports);
        $provinceTotal->ward_name = 'TỔNG CỘNG';
        $provinceTotal->school_rank = '';
        $provinceTotal->sessions_per_day = $reports->sum('sessions_per_day');
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
        $total = new TeacherReport();
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = '';
        $total->sessions_per_day = $reports->sum('sessions_per_day');
        $total->principal = $reports->sum('principal');
        $total->vice_principal = $reports->sum('vice_principal');
        $total->total_teachers = $reports->sum('total_teachers');
        $total->teacher_quota = $reports->sum('teacher_quota');
        $total->teacher_contract = $reports->sum('teacher_contract');
        $total->female_teachers = $reports->sum('female_teachers');
        $total->ethnic_teachers = $reports->sum('ethnic_teachers');
        $total->teacher_student_ratio = $reports->avg('teacher_student_ratio');
        $total->training_above_uni = $reports->sum('training_above_uni');
        $total->training_uni = $reports->sum('training_uni');
        $total->training_college = $reports->sum('training_college');
        $total->training_ped = $reports->sum('training_ped');
        $total->training_below_ped = $reports->sum('training_below_ped');
        $total->training_primary = $reports->sum('training_primary');
        $total->training_an = $reports->sum('training_an');
        $total->training_mt = $reports->sum('training_mt');
        $total->training_td = $reports->sum('training_td');
        $total->training_informatics = $reports->sum('training_informatics');
        $total->training_foreign_lang = $reports->sum('training_foreign_lang');
        $total->training_other = $reports->sum('training_other');
        $total->standard_good = $reports->sum('standard_good');
        $total->standard_quite = $reports->sum('standard_quite');
        $total->standard_reach = $reports->sum('standard_reach');
        $total->standard_not_reach = $reports->sum('standard_not_reach');
        $total->tpt_team = $reports->sum('tpt_team');
        $total->office_staff = $reports->sum('office_staff');
        $total->library_other_staff = $reports->sum('library_other_staff');
        
        return $total;
    }

    /**
     * Nhãn cột theo cấu trúc preview (32 cột)
     */
    private function columns($level = 'province'): array
    {
        $baseColumns = [
            // Đơn vị (4 cột)
            'school_name' => 'Tên trường',
            'empty_field' => '-',
            'school_rank' => 'Hạng',
            'sessions_per_day' => '2 buổi',
            // CBQL (2 cột)
            'principal' => 'HT',
            'vice_principal' => 'Phó HT',
            // Giáo viên - Tổng quát (6 cột)
            'total_teachers' => 'Tổng GV',
            'teacher_quota' => 'Biên chế',
            'teacher_contract' => 'Hợp đồng',
            'female_teachers' => 'Nữ',
            'ethnic_teachers' => 'Dân tộc',
            'teacher_student_ratio' => 'Tỷ lệ GV/L',
            // Trình độ đào tạo (5 cột)
            'training_above_uni' => 'Trên ĐH',
            'training_uni' => 'ĐH',
            'training_college' => 'CĐ',
            'training_ped' => 'THSP',
            'training_below_ped' => 'Dưới THSP',
            // Loại hình đào tạo (7 cột)
            'training_primary' => 'Tiểu học',
            'training_an' => 'AN',
            'training_mt' => 'MT',
            'training_td' => 'TD',
            'training_informatics' => 'Tin học',
            'training_foreign_lang' => 'NN',
            'training_other' => 'Khác',
            // Chuẩn nghề nghiệp (4 cột)
            'standard_good' => 'Tốt',
            'standard_quite' => 'Khá',
            'standard_reach' => 'Đạt',
            'standard_not_reach' => 'Chưa đạt',
            // TPT Đội (1 cột)
            'tpt_team' => 'TPT Đội',
            // Nhân viên (2 cột)
            'office_staff' => 'Văn phòng',
            'library_other_staff' => 'T.Viện-TBDH',
        ];

        // Thay đổi cột tên trường theo cấp độ
        switch ($level) {
            case 'school':
                // Cấp trường: ẩn tên trường và hạng trường
                $baseColumns['school_name'] = '';
                $baseColumns['school_rank'] = '';
                break;
            case 'ward':
                // Cấp xã: hiển thị tên trường
                $baseColumns['school_name'] = 'Tên trường';
                $baseColumns['school_rank'] = 'Hạng';
                break;
            case 'province':
                // Cấp tỉnh: thay tên trường bằng tên xã
                $baseColumns['school_name'] = 'Tên xã/phường';
                $baseColumns['school_rank'] = '';
                break;
        }

        return $baseColumns;
    }

    /**
     * Export Excel theo mẫu template Tiểu học Teacher Report
     */
    public function exportExcel(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $year = $request->get('year', date('Y') . '-' . (date('Y') + 1));
        
        // Lấy school_id từ request giống như trong hàm index
        $geoIn = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => (int) ($geoIn['school_id'] ?? $request->input('geo_school_id') ?? 0) ?: null,
        ];
        
        $schoolId = $geoIds['school'];
        
        if (!$schoolId) {
            return response()->json(['error' => 'Không xác định được trường học'], 400);
        }

        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['error' => 'Không tìm thấy cấp học Tiểu học'], 400);
        }

        // Kiểm tra trường có phải Tiểu học không
        $school = \App\Models\School::where('id', $schoolId)
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->first();
            
        if (!$school) {
            return response()->json(['error' => 'Trường này không phải trường Tiểu học'], 400);
        }
        
        $templatePath = resource_path('excel/2.3.TiH.DoiNguGiaoVien.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Lấy thông tin địa lý từ trường (theo cách của infrastructure)
        $wardNew = $school->wardNew; // Lấy từ ward_code_new với scheme = 'new'
        
        // Lấy tên tỉnh từ hành chính mới
        if ($wardNew && $wardNew->parent && $wardNew->parent->parent) {
            $provinceName = $wardNew->parent->parent->name;
        } else {
            // Fallback: lấy từ province_code_new trong bảng school
            if ($school->province_code_new) {
                $province = \App\Models\GeoUnit::where('code', $school->province_code_new)
                    ->where('scheme', 'new')
                    ->first();
                $provinceName = $province ? $province->name : '';
            } else {
                $provinceName = '';
            }
        }
        
        // Lấy tên xã từ hành chính mới
        if ($wardNew) {
            $wardName = $wardNew->name;
        } else {
            // Fallback: lấy từ ward_code_new trong bảng school
            if ($school->ward_code_new) {
                $ward = \App\Models\GeoUnit::where('code', $school->ward_code_new)
                    ->where('scheme', 'new')
                    ->first();
                $wardName = $ward ? $ward->name : '';
            } else {
                $wardName = '';
            }
        }

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('D2', $provinceName);
        $worksheet->setCellValue('D3', $wardName);
        
        // In đậm cho tên tỉnh và xã
        $worksheet->getStyle('D2')->getFont()->setBold(true);
        $worksheet->getStyle('D3')->getFont()->setBold(true);
        
        // Thêm ngày hiện tại vào ô E4
        $worksheet->setCellValue('E4', 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'));
        
        // In đậm và nghiêng cho ô E4
        $worksheet->getStyle('E4')->getFont()->setBold(true);
        $worksheet->getStyle('E4')->getFont()->setItalic(true);
        
        // Bỏ phần ký tên cho cấp trường
        // $this->addSignatureSectionForSchool($worksheet, $wardName);

        // Lấy dữ liệu của trường này
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }
        
        $report = TeacherReport::where('academic_year', $queryYear)
            ->where('school_id', $schoolId)
            ->first();

        // Nếu chưa có dữ liệu thì tạo report với tất cả giá trị là 0
        if (!$report) {
            $report = new \stdClass(); // Tạo object rỗng để không gây lỗi
            $report->school_rank = 0;
            $report->sessions_per_day = 0;
            $report->principal = 0;
            $report->vice_principal = 0;
            $report->total_teachers = 0;
            $report->teacher_quota = 0;
            $report->teacher_contract = 0;
            $report->female_teachers = 0;
            $report->ethnic_teachers = 0;
            $report->teacher_student_ratio = 0;
            
            // Trình độ đào tạo
            $report->training_above_uni = 0;
            $report->training_uni = 0;
            $report->training_college = 0;
            $report->training_ped = 0;
            $report->training_below_ped = 0;
            
            // Loại hình đào tạo
            $report->training_primary = 0;
            $report->training_an = 0;
            $report->training_mt = 0;
            $report->training_td = 0;
            $report->training_informatics = 0;
            $report->training_foreign_lang = 0;
            $report->training_other = 0;
            
            // Chuẩn nghề nghiệp
            $report->standard_good = 0;
            $report->standard_quite = 0;
            $report->standard_reach = 0;
            $report->standard_not_reach = 0;
            
            // TPT Đội và Nhân viên
            $report->tpt_team = 0;
            $report->office_staff = 0;
            $report->library_other_staff = 0;
        }

        // Điền dữ liệu của trường vào Excel
        $this->fillTeacherData($worksheet, collect([$report]), 'school');

        $filename = 'bao_cao_doi_ngu_giao_vien_tieu_hoc_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'teacher_report_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Điền dữ liệu đội ngũ giáo viên Tiểu học vào template
     */
    private function fillTeacherData($worksheet, $reports, $level)
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
                $schoolName = $report->ward_name ?? $report->ward?->name ?? '';
            } else {
                $schoolName = $report->school_name ?? '';
            }
            
            // Cột B,C,D: Đơn vị (tên trường) - merge 3 cột
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':D' . $currentRow);
            
            // Cột E: Hạng trường
            $worksheet->setCellValue('E' . $currentRow, $report->school_rank ?? 0);
            
            // Cột F: 2 buổi/ngày
            $worksheet->setCellValue('F' . $currentRow, $report->sessions_per_day ?? 0);
            
            // CBQL - Hiệu trưởng
            $worksheet->setCellValue('G' . $currentRow, $report->principal ?? 0);
            
            // CBQL - P.Hiệu trưởng
            $worksheet->setCellValue('H' . $currentRow, $report->vice_principal ?? 0);
            
            // Giáo viên - Tổng số
            $worksheet->setCellValue('I' . $currentRow, $report->total_teachers ?? 0);
            
            // Giáo viên - Biên chế
            $worksheet->setCellValue('J' . $currentRow, $report->teacher_quota ?? 0);
            
            // Giáo viên - Hợp đồng
            $worksheet->setCellValue('K' . $currentRow, $report->teacher_contract ?? 0);
            
            // Giáo viên - Nữ
            $worksheet->setCellValue('L' . $currentRow, $report->female_teachers ?? 0);
            
            // Giáo viên - Dân tộc
            $worksheet->setCellValue('M' . $currentRow, $report->ethnic_teachers ?? 0);
            
            // Giáo viên - Tỷ lệ GV/Lớp
            $worksheet->setCellValue('N' . $currentRow, $report->teacher_student_ratio ?? 0);
            
            // Format số với 2 decimal places cho cột tỷ lệ GV/L
            $worksheet->getStyle('N' . $currentRow)->getNumberFormat()->setFormatCode('0.00');
            
            // Trình độ đào tạo - Trên ĐH
            $worksheet->setCellValue('O' . $currentRow, $report->training_above_uni ?? 0);
            
            // Trình độ đào tạo - ĐH
            $worksheet->setCellValue('P' . $currentRow, $report->training_uni ?? 0);
            
            // Trình độ đào tạo - CĐ
            $worksheet->setCellValue('Q' . $currentRow, $report->training_college ?? 0);
            
            // Trình độ đào tạo - THSP
            $worksheet->setCellValue('R' . $currentRow, $report->training_ped ?? 0);
            
            // Trình độ đào tạo - Dưới THSP
            $worksheet->setCellValue('S' . $currentRow, $report->training_below_ped ?? 0);
            
            // Loại hình đào tạo - Tiểu học
            $worksheet->setCellValue('T' . $currentRow, $report->training_primary ?? 0);
            
            // Loại hình đào tạo - AN
            $worksheet->setCellValue('U' . $currentRow, $report->training_an ?? 0);
            
            // Loại hình đào tạo - MT
            $worksheet->setCellValue('V' . $currentRow, $report->training_mt ?? 0);
            
            // Loại hình đào tạo - TD
            $worksheet->setCellValue('W' . $currentRow, $report->training_td ?? 0);
            
            // Loại hình đào tạo - Tin học
            $worksheet->setCellValue('X' . $currentRow, $report->training_informatics ?? 0);
            
            // Loại hình đào tạo - NN
            $worksheet->setCellValue('Y' . $currentRow, $report->training_foreign_lang ?? 0);
            
            // Loại hình đào tạo - Khác
            $worksheet->setCellValue('Z' . $currentRow, $report->training_other ?? 0);
            
            // Chuẩn nghề nghiệp - Tốt
            $worksheet->setCellValue('AA' . $currentRow, $report->standard_good ?? 0);
            
            // Chuẩn nghề nghiệp - Khá
            $worksheet->setCellValue('AB' . $currentRow, $report->standard_quite ?? 0);
            
            // Chuẩn nghề nghiệp - Đạt
            $worksheet->setCellValue('AC' . $currentRow, $report->standard_reach ?? 0);
            
            // Chuẩn nghề nghiệp - Chưa đạt
            $worksheet->setCellValue('AD' . $currentRow, $report->standard_not_reach ?? 0);
            
            // TPT Đội
            $worksheet->setCellValue('AE' . $currentRow, $report->tpt_team ?? 0);
            
            // Nhân viên - Văn phòng
            $worksheet->setCellValue('AF' . $currentRow, $report->office_staff ?? 0);
            
            // Nhân viên - T.Viện - TBDH
            $worksheet->setCellValue('AG' . $currentRow, $report->library_other_staff ?? 0);
            
            $this->setConsistentFont($worksheet, $currentRow);
            
            // Force Tahoma font 8 cho từng ô sau khi ghi dữ liệu
            $this->forceTahomaFont($worksheet, $currentRow);
            
            // Format tỷ lệ GV/L với 2 decimal places (SAU khi set font)
            $worksheet->getStyle('N' . $currentRow)->getNumberFormat()->setFormatCode('0.00');
            
            $dataRows[] = $report;
            $currentRow++;
        }

        // Chỉ thêm dòng tổng cộng cho cấp xã, không thêm cho cấp trường
        if (!empty($dataRows) && $level !== 'school') {
            $this->addTotalRow($worksheet, $currentRow, $dataRows, $level);
            
            // Tính và ghi 3 tiêu chí đạt chuẩn
            $this->addCriteriaCalculations($worksheet, $dataRows, 14); // Cố định bắt đầu từ dòng 14
        }
    }

    /**
     * Thêm dòng tổng cộng
     */
    private function addTotalRow($worksheet, $row, $dataRows, $level)
    {
        // Ô TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':D' . $row);
        
        $this->setConsistentFont($worksheet, $row);

        $totals = $this->calculateTeacherTotals(collect($dataRows));

        // Cột E: Hạng trường
        $worksheet->setCellValue('E' . $row, '0');
        
        // Cột F: 2 buổi/ngày
        $worksheet->setCellValue('F' . $row, $totals->sessions_per_day ?? 0);
        
        // CBQL - Hiệu trưởng
        $worksheet->setCellValue('G' . $row, $totals->principal ?? 0);
        
        // CBQL - P.Hiệu trưởng
        $worksheet->setCellValue('H' . $row, $totals->vice_principal ?? 0);
        
        // Giáo viên - Tổng số
        $worksheet->setCellValue('I' . $row, $totals->total_teachers ?? 0);
        
        // Giáo viên - Biên chế
        $worksheet->setCellValue('J' . $row, $totals->teacher_quota ?? 0);
        
        // Giáo viên - Hợp đồng
        $worksheet->setCellValue('K' . $row, $totals->teacher_contract ?? 0);
        
        // Giáo viên - Nữ
        $worksheet->setCellValue('L' . $row, $totals->female_teachers ?? 0);
        
        // Giáo viên - Dân tộc
        $worksheet->setCellValue('M' . $row, $totals->ethnic_teachers ?? 0);
        
        // Giáo viên - Tỷ lệ GV/Lớp (tính trung bình)
        $worksheet->setCellValue('N' . $row, $totals->teacher_student_ratio ?? 0);
        
        // Trình độ đào tạo - Trên ĐH
        $worksheet->setCellValue('O' . $row, $totals->training_above_uni ?? 0);
        
        // Trình độ đào tạo - ĐH
        $worksheet->setCellValue('P' . $row, $totals->training_uni ?? 0);
        
        // Trình độ đào tạo - CĐ
        $worksheet->setCellValue('Q' . $row, $totals->training_college ?? 0);
        
        // Trình độ đào tạo - THSP
        $worksheet->setCellValue('R' . $row, $totals->training_ped ?? 0);
        
        // Trình độ đào tạo - Dưới THSP
        $worksheet->setCellValue('S' . $row, $totals->training_below_ped ?? 0);
        
        // Loại hình đào tạo - Tiểu học
        $worksheet->setCellValue('T' . $row, $totals->training_primary ?? 0);
        
        // Loại hình đào tạo - AN
        $worksheet->setCellValue('U' . $row, $totals->training_an ?? 0);
        
        // Loại hình đào tạo - MT
        $worksheet->setCellValue('V' . $row, $totals->training_mt ?? 0);
        
        // Loại hình đào tạo - TD
        $worksheet->setCellValue('W' . $row, $totals->training_td ?? 0);
        
        // Loại hình đào tạo - Tin học
        $worksheet->setCellValue('X' . $row, $totals->training_informatics ?? 0);
        
        // Loại hình đào tạo - NN
        $worksheet->setCellValue('Y' . $row, $totals->training_foreign_lang ?? 0);
        
        // Loại hình đào tạo - Khác
        $worksheet->setCellValue('Z' . $row, $totals->training_other ?? 0);
        
        // Chuẩn nghề nghiệp - Tốt
        $worksheet->setCellValue('AA' . $row, $totals->standard_good ?? 0);
        
        // Chuẩn nghề nghiệp - Khá
        $worksheet->setCellValue('AB' . $row, $totals->standard_quite ?? 0);
        
        // Chuẩn nghề nghiệp - Đạt
        $worksheet->setCellValue('AC' . $row, $totals->standard_reach ?? 0);
        
        // Chuẩn nghề nghiệp - Chưa đạt
        $worksheet->setCellValue('AD' . $row, $totals->standard_not_reach ?? 0);
        
        // TPT Đội
        $worksheet->setCellValue('AE' . $row, $totals->tpt_team ?? 0);
        
        // Nhân viên - Văn phòng
        $worksheet->setCellValue('AF' . $row, $totals->office_staff ?? 0);
        
        // Nhân viên - T.Viện - TBDH
        $worksheet->setCellValue('AG' . $row, $totals->library_other_staff ?? 0);
        
        // Format số cho tỷ lệ GV/L (cột N) với 2 decimal places
        $worksheet->getStyle('N' . $row)->getNumberFormat()->setFormatCode('0.00');
        
        // Force Tahoma font 8 cho dòng tổng cộng
        $this->forceTahomaFont($worksheet, $row);
        
        // In đậm toàn bộ dòng tổng cộng (SAU khi set font)
        $range = 'A' . $row . ':AG' . $row;
        $worksheet->getStyle($range)->getFont()->setBold(true);
    }

    /**
     * Đặt font và căn lề nhất quán cho dòng
     */
    private function setConsistentFont($worksheet, $row)
    {
        // Đặt font mặc định cho toàn bộ dòng (A đến AG)
        $range = 'A' . $row . ':AG' . $row;
        $style = $worksheet->getStyle($range);
        
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
        
        // Format số cho cột Tỷ lệ GV/L (cột N) từ dòng 10 trở lên
        if ($row >= 10) {
            $worksheet->getStyle('N' . $row)->getNumberFormat()->setFormatCode('0.00');
        }
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Đơn vị - căn trái (merge B-D)
            'C' => 'left',      // Đơn vị (merged) - căn trái
            'D' => 'left',      // Đơn vị (merged) - căn trái
            'E' => 'center',    // Hạng trường - căn giữa
            'F' => 'center',    // 2 buổi/ngày - căn giữa
            'G' => 'center',    // HT - căn giữa
            'H' => 'center',    // Phó HT - căn giữa
            'I' => 'center',    // Tổng số - căn giữa
            'J' => 'center',    // Biên chế - căn giữa
            'K' => 'center',    // Hợp đồng - căn giữa
            'L' => 'center',    // Nữ - căn giữa
            'M' => 'center',    // Dân tộc - căn giữa
            'N' => 'center',    // Tỷ lệ GV/L - căn giữa
            'O' => 'center',    // Trên ĐH - căn giữa
            'P' => 'center',    // ĐH - căn giữa
            'Q' => 'center',    // CĐ - căn giữa
            'R' => 'center',    // THSP - căn giữa
            'S' => 'center',    // Dưới THSP - căn giữa
            'T' => 'center',    // Tiểu học - căn giữa
            'U' => 'center',    // AN - căn giữa
            'V' => 'center',    // MT - căn giữa
            'W' => 'center',    // TD - căn giữa
            'X' => 'center',    // Tin học - căn giữa
            'Y' => 'center',    // NN - căn giữa
            'Z' => 'center',    // Khác - căn giữa
            'AA' => 'center',   // Tốt - căn giữa
            'AB' => 'center',   // Khá - căn giữa
            'AC' => 'center',   // Đạt - căn giữa
            'AD' => 'center',   // Chưa đạt - căn giữa
            'AE' => 'center',   // TPT Đội - căn giữa
            'AF' => 'center',   // Văn phòng - căn giữa
            'AG' => 'center'    // T.Viện - TBDH - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings
            $cellStyle->getFont()->setBold(false);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Arial');
            $cellStyle->getFont()->setSize(10);
            
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
            if (in_array($col, ['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG'])) {
                $cellStyle->getNumberFormat()->setFormatCode('0');
            }
        }
    }

    /**
     * Lấy tên tỉnh
     */
    private function getProvinceName($provinceId)
    {
        if (!$provinceId) {
            return 'Tỉnh Đắk Lắk';
        }
        
        $province = \App\Models\GeoUnit::find($provinceId);
        return $province ? $province->name : 'Tỉnh Đắk Lắk';
    }

    /**
     * Lấy tên xã/phường
     */
    private function getWardName($wardId)
    {
        if (!$wardId) {
            return 'Xã Cư Huê';
        }
        
        $ward = \App\Models\GeoUnit::find($wardId);
        return $ward ? $ward->name : 'Xã Cư Huê';
    }

    /**
     * Tính tổng cộng cho đội ngũ giáo viên
     */
    private function calculateTeacherTotals($reports)
    {
        $total = new TeacherReport();
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = 0;
        
        // Tính tổng cho tất cả các trường số
        $fields = [
            'sessions_per_day', 'principal', 'vice_principal', 'total_teachers', 'teacher_quota',
            'teacher_contract', 'female_teachers', 'ethnic_teachers', 'training_above_uni',
            'training_uni', 'training_college', 'training_ped', 'training_below_ped',
            'training_primary', 'training_an', 'training_mt', 'training_td', 'training_informatics',
            'training_foreign_lang', 'training_other', 'standard_good', 'standard_quite',
            'standard_reach', 'standard_not_reach', 'tpt_team', 'office_staff', 'library_other_staff'
        ];
        
        foreach ($fields as $field) {
            // Tính tổng cho tất cả các trường
                $total->$field = $reports->sum($field);
            }
        
        // Tỷ lệ GV/L tính đặc biệt: Tổng GV / Tổng Lớp (hoặc lấy giá trị đầu tiên nếu chỉ có 1 trường)
        if ($reports->count() == 1) {
            $total->teacher_student_ratio = $reports->first()->teacher_student_ratio ?? 0;
        } else {
            $total->teacher_student_ratio = $reports->avg('teacher_student_ratio') ?? 0;
        }

        return $total;
    }

    /**
     * Thêm tính toán 3 tiêu chí đạt chuẩn
     */
    private function addCriteriaCalculations($worksheet, $dataRows, $startRow)
    {
        // Thêm phần tiêu chí trước khi ghi số liệu
        $this->addCriteriaHeaders($worksheet, $startRow);
        
        $totals = $this->calculateTeacherTotals(collect($dataRows));
        
        // Tính tổng giáo viên để tính tỷ lệ
        $totalTeachers = $totals->total_teachers ?? 0;
        
        // 1. GV đạt chuẩn trình độ đào tạo (Trên ĐH + ĐH + CĐ + THSP)
        $gvDatChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) + 
                        ($totals->training_college ?? 0) + ($totals->training_ped ?? 0);
        $tyLeDatChuanTD = $totalTeachers > 0 ? ($gvDatChuanTD / $totalTeachers) * 100 : 0;
        
        // 2. GV đạt trên chuẩn trình độ đào tạo (Trên ĐH + ĐH + CĐ)
        $gvTrenChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) + 
                          ($totals->training_college ?? 0);
        $tyLeTrenChuanTD = $totalTeachers > 0 ? ($gvTrenChuanTD / $totalTeachers) * 100 : 0;
        
        // 3. GV đạt yêu cầu chuẩn nghề nghiệp (Tốt + Khá + Đạt)
        $gvDatChuanNN = ($totals->standard_good ?? 0) + ($totals->standard_quite ?? 0) + 
                        ($totals->standard_reach ?? 0);
        $tyLeDatChuanNN = $totalTeachers > 0 ? ($gvDatChuanNN / $totalTeachers) * 100 : 0;
        
        // Ghi số liệu vào cột G (SL)
        $worksheet->setCellValue('G' . ($startRow + 1), $gvDatChuanTD);        // Dòng 14
        $worksheet->setCellValue('G' . ($startRow + 2), $gvTrenChuanTD);        // Dòng 15  
        $worksheet->setCellValue('G' . ($startRow + 3), $gvDatChuanNN);          // Dòng 16
        
        // Ghi tỷ lệ vào cột I (Tỷ lệ)
        $worksheet->setCellValue('I' . ($startRow + 1), $tyLeDatChuanTD);       // Dòng 14
        $worksheet->setCellValue('I' . ($startRow + 2), $tyLeTrenChuanTD);       // Dòng 15
        $worksheet->setCellValue('I' . ($startRow + 3), $tyLeDatChuanNN);        // Dòng 16
        
        // Format font Tahoma 8 cho các ô đã ghi
        for ($i = 1; $i <= 3; $i++) {
            $row = $startRow + $i;
            $worksheet->getStyle('G' . $row)->getFont()->setName('Tahoma');
            $worksheet->getStyle('G' . $row)->getFont()->setSize(8);
            $worksheet->getStyle('I' . $row)->getFont()->setName('Tahoma');
            $worksheet->getStyle('I' . $row)->getFont()->setSize(8);
            
            // Format decimal places 2 cho tỷ lệ
            $worksheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('0.00');
        }
    }

    /**
     * Thêm phần tiêu chí với tiêu đề và border
     */
    private function addCriteriaHeaders($worksheet, $startRow)
    {
        // Dòng tiêu đề (dòng 13)
        // Merge A13:F13 và ghi 'Tiêu chí'
        $worksheet->setCellValue('A' . $startRow, 'Tiêu chí');
        $worksheet->mergeCells('A' . $startRow . ':F' . $startRow);
        
        // Merge G13:H13 và ghi 'SL'
        $worksheet->setCellValue('G' . $startRow, 'SL');
        $worksheet->mergeCells('G' . $startRow . ':H' . $startRow);
        
        // Merge I13:J13 và ghi 'Tỷ lệ'
        $worksheet->setCellValue('I' . $startRow, 'Tỷ lệ');
        $worksheet->mergeCells('I' . $startRow . ':J' . $startRow);
        
        // Căn giữa và in đậm A13:J13
        $headerRange = 'A' . $startRow . ':J' . $startRow;
        $headerStyle = $worksheet->getStyle($headerRange);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFont()->setName('Tahoma');
        $headerStyle->getFont()->setSize(8);
        
        // Dòng 14: GV đạt chuẩn trình độ đào tạo
        $worksheet->setCellValue('A' . ($startRow + 1), 'GV đạt chuẩn trình độ đào tạo');
        $worksheet->mergeCells('A' . ($startRow + 1) . ':F' . ($startRow + 1));
        $worksheet->mergeCells('G' . ($startRow + 1) . ':H' . ($startRow + 1)); // Merge G14:H14
        $worksheet->mergeCells('I' . ($startRow + 1) . ':J' . ($startRow + 1)); // Merge I14:J14
        
        // Dòng 15: GV đạt trên chuẩn trình độ đào tạo
        $worksheet->setCellValue('A' . ($startRow + 2), 'GV đạt trên chuẩn trình độ đào tạo');
        $worksheet->mergeCells('A' . ($startRow + 2) . ':F' . ($startRow + 2));
        $worksheet->mergeCells('G' . ($startRow + 2) . ':H' . ($startRow + 2)); // Merge G15:H15
        $worksheet->mergeCells('I' . ($startRow + 2) . ':J' . ($startRow + 2)); // Merge I15:J15
        
        // Dòng 16: GV đạt yêu cầu chuẩn nghề nghiệp
        $worksheet->setCellValue('A' . ($startRow + 3), 'GV đạt yêu cầu chuẩn nghề nghiệp');
        $worksheet->mergeCells('A' . ($startRow + 3) . ':F' . ($startRow + 3));
        $worksheet->mergeCells('G' . ($startRow + 3) . ':H' . ($startRow + 3)); // Merge G16:H16
        $worksheet->mergeCells('I' . ($startRow + 3) . ':J' . ($startRow + 3)); // Merge I16:J16
        
        // Format font cho các dòng tiêu chí
        for ($i = 1; $i <= 3; $i++) {
            $row = $startRow + $i;
            $criteriaRange = 'A' . $row . ':F' . $row;
            $criteriaStyle = $worksheet->getStyle($criteriaRange);
            $criteriaStyle->getFont()->setName('Tahoma');
            $criteriaStyle->getFont()->setSize(8);
            $criteriaStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $criteriaStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Căn giữa cho các ô SL và Tỷ lệ
            $slRange = 'G' . $row . ':H' . $row;
            $tyLeRange = 'I' . $row . ':J' . $row;
            
            $slStyle = $worksheet->getStyle($slRange);
            $slStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $slStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $slStyle->getFont()->setName('Tahoma');
            $slStyle->getFont()->setSize(8);
            
            $tyLeStyle = $worksheet->getStyle($tyLeRange);
            $tyLeStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $tyLeStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $tyLeStyle->getFont()->setName('Tahoma');
            $tyLeStyle->getFont()->setSize(8);
        }
        
        // Border cho A13:J16
        $borderRange = 'A' . $startRow . ':J' . ($startRow + 3);
        $borderStyle = $worksheet->getStyle($borderRange);
        $borderStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Force Tahoma font 8 cho tất cả ô từ A đến AG
     */
    private function forceTahomaFont($worksheet, $row)
    {
        // Tạo range từ A đến AG để chỉ set font
        $range = 'A' . $row . ':AG' . $row;
        $style = $worksheet->getStyle($range);
        
        // Chỉ set font, giữ nguyên alignment và format khác
        $font = $style->getFont();
        $font->setName('Tahoma');
        $font->setSize(8);
    }

    /**
     * Nhập dữ liệu từ Excel cho giáo viên Tiểu học
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
                'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
            ]);

            $user = Auth::user();
            $school = $user->school;
            
            if (!$school) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin trường của tài khoản'], 400);
            }

            // Kiểm tra trường có phải Tiểu học không
            $primaryLevelIds = $this->getPrimaryLevelIds();
            if (!in_array($school->educational_level_id, $primaryLevelIds)) {
                return response()->json(['success' => false, 'message' => 'Trường này không phải là trường Tiểu học'], 400);
            }

            $file = $request->file('excel_file');
            $academicYear = $request->input('academic_year');
            
            // Validate năm học format
            if (!$academicYear || !preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Năm học không hợp lệ. Vui lòng chọn năm học đúng định dạng.'
                ], 400);
            }
            
            // Đọc file Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Đọc dữ liệu từ dòng 10, cột E đến AG
            $row = 10;
            $data = [];
            
            // E: Hạng trường
            $data['school_rank'] = $worksheet->getCell('E' . $row)->getCalculatedValue() ?? 0;
            
            // F: 2 buổi/ngày
            $data['sessions_per_day'] = $worksheet->getCell('F' . $row)->getCalculatedValue() ?? 0;
            
            // G: Hiệu trường
            $data['principal'] = $worksheet->getCell('G' . $row)->getCalculatedValue() ?? 0;
            
            // H: Phó Hiệu trường
            $data['vice_principal'] = $worksheet->getCell('H' . $row)->getCalculatedValue() ?? 0;
            
            // I: Tổng số giáo viên
            $data['total_teachers'] = $worksheet->getCell('I' . $row)->getCalculatedValue() ?? 0;
            
            // J: Biên chế
            $data['teacher_quota'] = $worksheet->getCell('J' . $row)->getCalculatedValue() ?? 0;
            
            // K: Hợp đồng
            $data['teacher_contract'] = $worksheet->getCell('K' . $row)->getCalculatedValue() ?? 0;
            
            // L: Nữ
            $data['female_teachers'] = $worksheet->getCell('L' . $row)->getCalculatedValue() ?? 0;
            
            // M: Dân tộc
            $data['ethnic_teachers'] = $worksheet->getCell('M' . $row)->getCalculatedValue() ?? 0;
            
            // N: Tỷ lệ GV/Lớp
            $data['teacher_student_ratio'] = $worksheet->getCell('N' . $row)->getCalculatedValue() ?? 0;
            
            // Trình độ đào tạo (O-S)
            $data['training_above_uni'] = $worksheet->getCell('O' . $row)->getCalculatedValue() ?? 0; // Trên ĐH
            $data['training_uni'] = $worksheet->getCell('P' . $row)->getCalculatedValue() ?? 0; // ĐH
            $data['training_college'] = $worksheet->getCell('Q' . $row)->getCalculatedValue() ?? 0; // CĐ
            $data['training_ped'] = $worksheet->getCell('R' . $row)->getCalculatedValue() ?? 0; // THSP
            $data['training_below_ped'] = $worksheet->getCell('S' . $row)->getCalculatedValue() ?? 0; // Dưới THSP
            
            // Loại hình đào tạo (T-Z)
            $data['training_primary'] = $worksheet->getCell('T' . $row)->getCalculatedValue() ?? 0; // Tiểu học
            $data['training_an'] = $worksheet->getCell('U' . $row)->getCalculatedValue() ?? 0; // AN
            $data['training_mt'] = $worksheet->getCell('V' . $row)->getCalculatedValue() ?? 0; // MT
            $data['training_td'] = $worksheet->getCell('W' . $row)->getCalculatedValue() ?? 0; // TD
            $data['training_informatics'] = $worksheet->getCell('X' . $row)->getCalculatedValue() ?? 0; // Tin học
            $data['training_foreign_lang'] = $worksheet->getCell('Y' . $row)->getCalculatedValue() ?? 0; // NN
            $data['training_other'] = $worksheet->getCell('Z' . $row)->getCalculatedValue() ?? 0; // Khác
            
            // Chuẩn nghề nghiệp (AA-AD)
            $data['standard_good'] = $worksheet->getCell('AA' . $row)->getCalculatedValue() ?? 0; // Tốt
            $data['standard_quite'] = $worksheet->getCell('AB' . $row)->getCalculatedValue() ?? 0; // Khá
            $data['standard_reach'] = $worksheet->getCell('AC' . $row)->getCalculatedValue() ?? 0; // Đạt
            $data['standard_not_reach'] = $worksheet->getCell('AD' . $row)->getCalculatedValue() ?? 0; // Chưa đạt
            
            // TPT Đội (AE)
            $data['tpt_team'] = $worksheet->getCell('AE' . $row)->getCalculatedValue() ?? 0;
            
            // Nhân viên (AF-AG)
            $data['office_staff'] = $worksheet->getCell('AF' . $row)->getCalculatedValue() ?? 0; // Văn phòng
            $data['library_other_staff'] = $worksheet->getCell('AG' . $row)->getCalculatedValue() ?? 0; // T.Viện - TBDH
            
            // Thêm thông tin bắt buộc
            $data['school_id'] = $school->id;
            $data['ward_id'] = $school->getWardId();
            $data['academic_year'] = $academicYear;
            $data['school_name'] = $school->name;
            
            // Tìm hoặc tạo bản ghi
            $report = TeacherReport::updateOrCreate(
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
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi nhập dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xuất Excel cho Thống kê báo cáo (theo xã) - Tiểu học
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

        // Lấy ward_code từ ward_id
        $ward = \App\Models\GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return response()->json(['error' => 'Không tìm thấy xã/phường'], 400);
        }
        
        // Lấy tất cả trường Tiểu học trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schools = \App\Models\School::active()
            ->where('ward_code_new', $ward->code)
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->orderBy('name')
            ->get();
        
        if ($schools->isEmpty()) {
            return response()->json(['error' => 'Không tìm thấy trường Tiểu học nào trong xã'], 400);
        }
        
        $templatePath = resource_path('excel/2.3.TiH.DoiNguGiaoVien.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Lấy thông tin địa lý từ ward (hành chính mới)
        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $ward->name;

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('D2', $provinceName);
        $worksheet->setCellValue('D3', $wardName);
        
        // In đậm cho tên tỉnh và xã
        $worksheet->getStyle('D2')->getFont()->setBold(true);
        $worksheet->getStyle('D3')->getFont()->setBold(true);
        
        // Thêm ngày hiện tại vào ô E4
        $worksheet->setCellValue('E4', 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'));
        
        // In đậm và nghiêng cho ô E4
        $worksheet->getStyle('E4')->getFont()->setBold(true);
        $worksheet->getStyle('E4')->getFont()->setItalic(true);
        
        // Thêm thông tin xã vào ô Z13
        $worksheet->setCellValue('Z13', $wardName . ', ngày ... tháng ... năm ...');

        // Reset vùng A13:AG16 trước khi ghi dữ liệu
        $this->resetCriteriaArea($worksheet);

        // Lấy dữ liệu của tất cả trường trong xã
        $queryYear = $year;
        if (preg_match('/^\d{4}$/', $year)) {
            $queryYear = $year . '-' . ($year + 1);
        }
        
        $reports = TeacherReport::where('academic_year', $queryYear)
            ->whereIn('school_id', $schools->pluck('id'))
            ->get();

        // Tạo danh sách kết hợp: trường + báo cáo (nếu có)
        $combinedData = $schools->map(function($school) use ($reports, $queryYear) {
            $report = $reports->where('school_id', $school->id)->first();
            
            if ($report) {
                // Có báo cáo, sử dụng dữ liệu từ báo cáo
                return $report;
            } else {
                // Chưa có báo cáo, tạo object rỗng
                $emptyReport = new \stdClass();
                $emptyReport->school_id = $school->id;
                $emptyReport->school_name = $school->name;
                $emptyReport->school_rank = null;
                $emptyReport->sessions_per_day = null;
                $emptyReport->principal = null;
                $emptyReport->vice_principal = null;
                $emptyReport->total_teachers = null;
                $emptyReport->teacher_quota = null;
                $emptyReport->teacher_contract = null;
                $emptyReport->female_teachers = null;
                $emptyReport->ethnic_teachers = null;
                $emptyReport->teacher_student_ratio = null;
                
                // Trình độ đào tạo
                $emptyReport->training_above_uni = null;
                $emptyReport->training_uni = null;
                $emptyReport->training_college = null;
                $emptyReport->training_ped = null;
                $emptyReport->training_below_ped = null;
                
                // Loại hình đào tạo
                $emptyReport->training_primary = null;
                $emptyReport->training_an = null;
                $emptyReport->training_mt = null;
                $emptyReport->training_td = null;
                $emptyReport->training_informatics = null;
                $emptyReport->training_foreign_lang = null;
                $emptyReport->training_other = null;
                
                // Chuẩn nghề nghiệp
                $emptyReport->standard_good = null;
                $emptyReport->standard_quite = null;
                $emptyReport->standard_reach = null;
                $emptyReport->standard_not_reach = null;
                
                // TPT Đội và Nhân viên
                $emptyReport->tpt_team = null;
                $emptyReport->office_staff = null;
                $emptyReport->library_other_staff = null;
                
                return $emptyReport;
            }
        });

        // Điền dữ liệu của các trường vào Excel
        $this->fillTeacherDataForWard($worksheet, $combinedData, $wardName);

        $filename = 'bao_cao_doi_ngu_giao_vien_tieu_hoc_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'primary_teacher_report_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Reset vùng A13:AG16 trước khi ghi dữ liệu
     */
    private function resetCriteriaArea($worksheet)
    {
        // Reset format cho vùng A13:AG16
        $criteriaRange = 'A13:AG16';
        $style = $worksheet->getStyle($criteriaRange);
        
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
        
        // Xóa nội dung các ô trong vùng này
        for ($row = 13; $row <= 16; $row++) {
            for ($col = 'A'; $col <= 'AG'; $col++) {
                $cell = $col . $row;
                $worksheet->setCellValue($cell, '');
            }
        }
    }

    /**
     * Lấy tên tỉnh từ ward
     */
    private function getProvinceNameFromWard($ward)
    {
        if ($ward && $ward->parent && $ward->parent->parent) {
            return $ward->parent->parent->name;
        }
        return '';
    }

    /**
     * Điền dữ liệu đội ngũ giáo viên Tiểu học vào template (cho ward)
     */
    private function fillTeacherDataForWard($worksheet, $reports, $wardName = '')
    {
        $startRow = 10; // Bắt đầu từ dòng 10
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = [];

        foreach ($reports as $report) {
            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            $schoolName = $report->school_name ?? '';
            
            // Cột B,C,D: Đơn vị (tên trường) - merge 3 cột
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':D' . $currentRow);
            
            // Đặt chiều cao dòng là 18.60
            $worksheet->getRowDimension($currentRow)->setRowHeight(18.60);
            
            // Cột A (TT): Căn giữa ngang và dọc
            $worksheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('A' . $currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Cột B-D (Tên trường): Căn trái ngang và căn giữa dọc
            $worksheet->getStyle('B' . $currentRow . ':D' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $worksheet->getStyle('B' . $currentRow . ':D' . $currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Cột E: Hạng trường
            $worksheet->setCellValue('E' . $currentRow, $report->school_rank ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'E' . $currentRow);
            
            // Cột F: 2 buổi/ngày
            $worksheet->setCellValue('F' . $currentRow, $report->sessions_per_day ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'F' . $currentRow);
            
            // CBQL - Hiệu trưởng
            $worksheet->setCellValue('G' . $currentRow, $report->principal ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'G' . $currentRow);
            
            // CBQL - P.Hiệu trưởng
            $worksheet->setCellValue('H' . $currentRow, $report->vice_principal ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'H' . $currentRow);
            
            // Tổng số GV
            $worksheet->setCellValue('I' . $currentRow, $report->total_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'I' . $currentRow);
            
            // Biên chế
            $worksheet->setCellValue('J' . $currentRow, $report->teacher_quota ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'J' . $currentRow);
            
            // Hợp đồng
            $worksheet->setCellValue('K' . $currentRow, $report->teacher_contract ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'K' . $currentRow);
            
            // Nữ GV
            $worksheet->setCellValue('L' . $currentRow, $report->female_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'L' . $currentRow);
            
            // GV dân tộc
            $worksheet->setCellValue('M' . $currentRow, $report->ethnic_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'M' . $currentRow);
            
            // Tỷ lệ GV/HS
            $worksheet->setCellValue('N' . $currentRow, $report->teacher_student_ratio ?: '');
            // Format tỷ lệ cho cột N (áp dụng cho tất cả ô)
            $worksheet->getStyle('N' . $currentRow)->getNumberFormat()->setFormatCode('0.00');
            // Căn giữa cho cột N (cả ngang và dọc)
            $worksheet->getStyle('N' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('N' . $currentRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Trình độ đào tạo - Trên ĐH
            $worksheet->setCellValue('O' . $currentRow, $report->training_above_uni ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'O' . $currentRow);
            
            // Trình độ đào tạo - ĐH
            $worksheet->setCellValue('P' . $currentRow, $report->training_uni ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'P' . $currentRow);
            
            // Trình độ đào tạo - CĐ
            $worksheet->setCellValue('Q' . $currentRow, $report->training_college ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'Q' . $currentRow);
            
            // Trình độ đào tạo - SPMN
            $worksheet->setCellValue('R' . $currentRow, $report->training_ped ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'R' . $currentRow);
            
            // Trình độ đào tạo - Dưới SPMN
            $worksheet->setCellValue('S' . $currentRow, $report->training_below_ped ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'S' . $currentRow);
            
            // Loại hình đào tạo - Tiểu học
            $worksheet->setCellValue('T' . $currentRow, $report->training_primary ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'T' . $currentRow);
            
            // Loại hình đào tạo - Âm nhạc
            $worksheet->setCellValue('U' . $currentRow, $report->training_an ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'U' . $currentRow);
            
            // Loại hình đào tạo - Mỹ thuật
            $worksheet->setCellValue('V' . $currentRow, $report->training_mt ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'V' . $currentRow);
            
            // Loại hình đào tạo - Thể dục
            $worksheet->setCellValue('W' . $currentRow, $report->training_td ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'W' . $currentRow);
            
            // Loại hình đào tạo - Tin học
            $worksheet->setCellValue('X' . $currentRow, $report->training_informatics ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'X' . $currentRow);
            
            // Loại hình đào tạo - Ngoại ngữ
            $worksheet->setCellValue('Y' . $currentRow, $report->training_foreign_lang ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'Y' . $currentRow);
            
            // Loại hình đào tạo - Khác
            $worksheet->setCellValue('Z' . $currentRow, $report->training_other ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'Z' . $currentRow);
            
            // Chuẩn nghề nghiệp - Tốt
            $worksheet->setCellValue('AA' . $currentRow, $report->standard_good ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AA' . $currentRow);
            
            // Chuẩn nghề nghiệp - Khá
            $worksheet->setCellValue('AB' . $currentRow, $report->standard_quite ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AB' . $currentRow);
            
            // Chuẩn nghề nghiệp - Đạt
            $worksheet->setCellValue('AC' . $currentRow, $report->standard_reach ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AC' . $currentRow);
            
            // Chuẩn nghề nghiệp - Chưa đạt
            $worksheet->setCellValue('AD' . $currentRow, $report->standard_not_reach ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AD' . $currentRow);
            
            // TPT Đội
            $worksheet->setCellValue('AE' . $currentRow, $report->tpt_team ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AE' . $currentRow);
            
            // Nhân viên văn phòng
            $worksheet->setCellValue('AF' . $currentRow, $report->office_staff ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AF' . $currentRow);
            
            // Thư viện và nhân viên khác
            $worksheet->setCellValue('AG' . $currentRow, $report->library_other_staff ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'AG' . $currentRow);

            // Lưu dữ liệu để tính tổng cộng
            $dataRows[] = $report;
            $currentRow++;
        }

        // Thêm dòng tổng cộng nếu có dữ liệu
        if (!empty($dataRows)) {
            $totalRow = $this->addTotalRowForWard($worksheet, $currentRow, $dataRows);
            
            // Thêm border cho tất cả các dòng dữ liệu từ dòng đầu tiên đến dòng tổng cộng
            $this->addBordersToDataRows($worksheet, $startRow, $totalRow);
            
            // Thêm phần tiêu chí SL/TL sau dòng tổng cộng
            $this->addCriteriaCalculationsForWard($worksheet, $dataRows, $totalRow + 2);
            
            // Thêm phần ký tên sau dòng tổng cộng + 2
            $this->addSignatureSectionForWard($worksheet, $totalRow + 2, $wardName);
        }
    }

    /**
     * Lấy tên tỉnh từ ward (hành chính mới)
     */
    private function getProvinceNameFromWardNew($ward)
    {
        // Sử dụng relationship để lấy province trực tiếp
        if ($ward->parent && $ward->parent->parent) {
            return $ward->parent->parent->name;
        }
        
        // Fallback: lấy theo code (hành chính mới)
        $district = \App\Models\GeoUnit::where('code', $ward->parent_code)->first();
        if ($district && $district->parent_code) {
            $province = \App\Models\GeoUnit::where('code', $district->parent_code)->first();
            if ($province) {
                return $province->name;
            }
        }
        
        return '';
    }

    /**
     * Thêm phần ký tên cho school-level report (dòng 13-16)
     */
    private function addSignatureSectionForSchool($worksheet, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô Z13
        $dateRow = 13;
        $worksheet->setCellValue('Z' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô Q14, in đậm
        $signatureRow1 = 14;
        $worksheet->setCellValue('Q' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('Q' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô Z14, in đậm
        $worksheet->setCellValue('Z' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('Z' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô Q15, in nghiêng
        $signatureRow2 = 15;
        $worksheet->setCellValue('Q' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('Q' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô Z15, in đậm
        $worksheet->setCellValue('Z' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('Z' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô Z16, in nghiêng
        $signatureRow3 = 16;
        $worksheet->setCellValue('Z' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('Z' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'Q' . $signatureRow1 . ':Z' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên (dòng 13-16)
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('Q' . $row . ':Z' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('Q' . $row . ':Z' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Thêm phần ký tên cho ward-level report
     */
    private function addSignatureSectionForWard($worksheet, $startRow, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... (ở cột Z, ngay trên TM. BAN CHỈ ĐẠO PCGD, XMC)
        $worksheet->setCellValue('Z' . $startRow, $wardName . ', ngày ... tháng ... năm ...');
        $worksheet->getStyle('Z' . $startRow)->getFont()->setName('Tahoma');
        $worksheet->getStyle('Z' . $startRow)->getFont()->setSize(8);
        $worksheet->getStyle('Z' . $startRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('Z' . $startRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // NGƯỜI LẬP BIỂU
        $worksheet->setCellValue('Q' . ($startRow + 1), 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('Q' . ($startRow + 1))->getFont()->setName('Tahoma');
        $worksheet->getStyle('Q' . ($startRow + 1))->getFont()->setSize(8);
        $worksheet->getStyle('Q' . ($startRow + 1))->getFont()->setBold(true);
        $worksheet->getStyle('Q' . ($startRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('Q' . ($startRow + 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // TM. BAN CHỈ ĐẠO PCGD, XMC
        $worksheet->setCellValue('Z' . ($startRow + 1), 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('Z' . ($startRow + 1))->getFont()->setName('Tahoma');
        $worksheet->getStyle('Z' . ($startRow + 1))->getFont()->setSize(8);
        $worksheet->getStyle('Z' . ($startRow + 1))->getFont()->setBold(true);
        $worksheet->getStyle('Z' . ($startRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('Z' . ($startRow + 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // (Ký và ghi rõ họ tên)
        $worksheet->setCellValue('Q' . ($startRow + 2), '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('Q' . ($startRow + 2))->getFont()->setName('Tahoma');
        $worksheet->getStyle('Q' . ($startRow + 2))->getFont()->setSize(8);
        $worksheet->getStyle('Q' . ($startRow + 2))->getFont()->setItalic(true);
        $worksheet->getStyle('Q' . ($startRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('Q' . ($startRow + 2))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // TRƯỞNG BAN
        $worksheet->setCellValue('Z' . ($startRow + 2), 'TRƯỞNG BAN');
        $worksheet->getStyle('Z' . ($startRow + 2))->getFont()->setName('Tahoma');
        $worksheet->getStyle('Z' . ($startRow + 2))->getFont()->setSize(8);
        $worksheet->getStyle('Z' . ($startRow + 2))->getFont()->setBold(true);
        $worksheet->getStyle('Z' . ($startRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('Z' . ($startRow + 2))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Set format số nguyên và căn giữa cho ô
     */
    private function setNumberFormatAndCenter($worksheet, $cell)
    {
        $style = $worksheet->getStyle($cell);
        $style->getNumberFormat()->setFormatCode('0');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Thêm border cho các dòng dữ liệu từ dòng đầu tiên đến dòng tổng cộng
     */
    private function addBordersToDataRows($worksheet, $startRow, $endRow)
    {
        // Thêm border cho tất cả các dòng từ startRow đến endRow
        for ($row = $startRow; $row <= $endRow; $row++) {
            $range = 'A' . $row . ':AG' . $row;
            $style = $worksheet->getStyle($range);
            $style->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
    }

    /**
     * Thêm phần tiêu chí SL/TL cho ward-level report (sau dòng tổng cộng)
     */
    private function addCriteriaCalculationsForWard($worksheet, $dataRows, $startRow)
    {
        // Thêm phần tiêu chí trước khi ghi số liệu
        $this->addCriteriaHeaders($worksheet, $startRow);

        $totals = $this->calculateTotalsFromObjects(collect($dataRows));

        // Tính tổng giáo viên để tính tỷ lệ
        $totalTeachers = $totals->total_teachers ?? 0;

        // 1. GV đạt chuẩn trình độ đào tạo (Trên ĐH + ĐH + CĐ + THSP)
        $gvDatChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) +
                        ($totals->training_college ?? 0) + ($totals->training_ped ?? 0);
        $tyLeDatChuanTD = $totalTeachers > 0 ? ($gvDatChuanTD / $totalTeachers) * 100 : 0;

        // 2. GV đạt trên chuẩn trình độ đào tạo (Trên ĐH + ĐH + CĐ)
        $gvTrenChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) +
                          ($totals->training_college ?? 0);
        $tyLeTrenChuanTD = $totalTeachers > 0 ? ($gvTrenChuanTD / $totalTeachers) * 100 : 0;

        // 3. GV đạt yêu cầu chuẩn nghề nghiệp (Tốt + Khá + Đạt)
        $gvDatChuanNN = ($totals->standard_good ?? 0) + ($totals->standard_quite ?? 0) +
                        ($totals->standard_reach ?? 0);
        $tyLeDatChuanNN = $totalTeachers > 0 ? ($gvDatChuanNN / $totalTeachers) * 100 : 0;

        // Ghi số liệu vào cột G (SL)
        $worksheet->setCellValue('G' . ($startRow + 1), $gvDatChuanTD);        // Dòng đầu tiên
        $worksheet->setCellValue('G' . ($startRow + 2), $gvTrenChuanTD);        // Dòng thứ hai
        $worksheet->setCellValue('G' . ($startRow + 3), $gvDatChuanNN);          // Dòng thứ ba

        // Ghi tỷ lệ vào cột I (Tỷ lệ)
        $worksheet->setCellValue('I' . ($startRow + 1), $tyLeDatChuanTD);       // Dòng đầu tiên
        $worksheet->setCellValue('I' . ($startRow + 2), $tyLeTrenChuanTD);       // Dòng thứ hai
        $worksheet->setCellValue('I' . ($startRow + 3), $tyLeDatChuanNN);        // Dòng thứ ba

        // Format font Tahoma 8 cho các ô đã ghi
        for ($i = 1; $i <= 3; $i++) {
            $row = $startRow + $i;
            $worksheet->getStyle('G' . $row)->getFont()->setName('Tahoma');
            $worksheet->getStyle('G' . $row)->getFont()->setSize(8);
            $worksheet->getStyle('I' . $row)->getFont()->setName('Tahoma');
            $worksheet->getStyle('I' . $row)->getFont()->setSize(8);

            // Format decimal places 2 cho tỷ lệ
            $worksheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('0.00');
        }
    }

    /**
     * Thêm dòng tổng cộng (cho exportExcelForWard)
     */
    private function addTotalRowForWard($worksheet, $row, $dataRows)
    {
        // Đặt chiều cao dòng tổng cộng là 18.60
        $worksheet->getRowDimension($row)->setRowHeight(18.60);
        
        // TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        // Tên - merge cột B, C, D
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':D' . $row);
        
        // Cột A (TT): Căn giữa ngang và dọc
        $worksheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Cột B-D (Tên): Căn trái ngang và căn giữa dọc
        $worksheet->getStyle('B' . $row . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $worksheet->getStyle('B' . $row . ':D' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // In đậm dòng tổng cộng (chỉ in đậm dòng này, không phải dòng 11)
        $range = 'A' . $row . ':AG' . $row;
        $worksheet->getStyle($range)->getFont()->setBold(true);
        
        // Đảm bảo dòng 11 không bị in đậm (reset về bình thường)
        $row11Range = 'A11:AG11';
        $row11Style = $worksheet->getStyle($row11Range);
        $row11Style->getFont()->setBold(false);
        
        // Reset format cho dòng 11 (dòng tổng cộng của template cấp trường)
        // Cột A (TT): Căn giữa ngang và dọc
        $worksheet->getStyle('A11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('A11')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Cột B-D (Tên): Căn trái ngang và căn giữa dọc
        $worksheet->getStyle('B11:D11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $worksheet->getStyle('B11:D11')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Các cột dữ liệu (E-AG): Căn giữa ngang và dọc
        $dataColumns = ['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG'];
        foreach ($dataColumns as $col) {
            $worksheet->getStyle($col . '11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle($col . '11')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Tính tổng cộng cho các trường số
        $totals = $this->calculateTotalsFromObjects(collect($dataRows));

        // Điền dữ liệu tổng cộng
        // Cột E (Hạng trường): Để là 0, không tính tổng
        $worksheet->setCellValue('E' . $row, 0);
        $this->setNumberFormatAndCenter($worksheet, 'E' . $row);
        
        $worksheet->setCellValue('F' . $row, $totals->sessions_per_day ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'F' . $row);
        
        $worksheet->setCellValue('G' . $row, $totals->principal ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'G' . $row);
        
        $worksheet->setCellValue('H' . $row, $totals->vice_principal ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'H' . $row);
        
        $worksheet->setCellValue('I' . $row, $totals->total_teachers ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'I' . $row);
        
        $worksheet->setCellValue('J' . $row, $totals->teacher_quota ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'J' . $row);
        
        $worksheet->setCellValue('K' . $row, $totals->teacher_contract ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'K' . $row);
        
        $worksheet->setCellValue('L' . $row, $totals->female_teachers ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'L' . $row);
        
        $worksheet->setCellValue('M' . $row, $totals->ethnic_teachers ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'M' . $row);
        
        $worksheet->setCellValue('N' . $row, $totals->teacher_student_ratio ?? 0);
        // Format tỷ lệ cho cột N
        $worksheet->getStyle('N' . $row)->getNumberFormat()->setFormatCode('0.00');
        $worksheet->getStyle('N' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('N' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        $worksheet->setCellValue('O' . $row, $totals->training_above_uni ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'O' . $row);
        
        $worksheet->setCellValue('P' . $row, $totals->training_uni ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'P' . $row);
        
        $worksheet->setCellValue('Q' . $row, $totals->training_college ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'Q' . $row);
        
        $worksheet->setCellValue('R' . $row, $totals->training_ped ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'R' . $row);
        
        $worksheet->setCellValue('S' . $row, $totals->training_below_ped ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'S' . $row);
        
        $worksheet->setCellValue('T' . $row, $totals->training_primary ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'T' . $row);
        
        $worksheet->setCellValue('U' . $row, $totals->training_an ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'U' . $row);
        
        $worksheet->setCellValue('V' . $row, $totals->training_mt ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'V' . $row);
        
        $worksheet->setCellValue('W' . $row, $totals->training_td ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'W' . $row);
        
        $worksheet->setCellValue('X' . $row, $totals->training_informatics ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'X' . $row);
        
        $worksheet->setCellValue('Y' . $row, $totals->training_foreign_lang ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'Y' . $row);
        
        $worksheet->setCellValue('Z' . $row, $totals->training_other ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'Z' . $row);
        
        $worksheet->setCellValue('AA' . $row, $totals->standard_good ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AA' . $row);
        
        $worksheet->setCellValue('AB' . $row, $totals->standard_quite ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AB' . $row);
        
        $worksheet->setCellValue('AC' . $row, $totals->standard_reach ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AC' . $row);
        
        $worksheet->setCellValue('AD' . $row, $totals->standard_not_reach ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AD' . $row);
        
        $worksheet->setCellValue('AE' . $row, $totals->tpt_team ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AE' . $row);
        
        $worksheet->setCellValue('AF' . $row, $totals->office_staff ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AF' . $row);
        
        $worksheet->setCellValue('AG' . $row, $totals->library_other_staff ?? 0);
        $this->setNumberFormatAndCenter($worksheet, 'AG' . $row);
        
        return $row; // Trả về số dòng tổng cộng
    }

    /**
     * Tính tổng cộng từ objects (cho exportExcelForWard)
     */
    protected function calculateTotalsFromObjects($objects)
    {
        $total = new \stdClass();
        
        // Tính tổng cho tất cả các trường số
        $fields = [
            'school_rank', 'sessions_per_day', 'principal', 'vice_principal',
            'total_teachers', 'teacher_quota', 'teacher_contract', 'female_teachers',
            'ethnic_teachers', 'teacher_student_ratio', 'training_above_uni',
            'training_uni', 'training_college', 'training_ped', 'training_below_ped',
            'training_primary', 'training_an', 'training_mt', 'training_td',
            'training_informatics', 'training_foreign_lang', 'training_other',
            'standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach',
            'tpt_team', 'office_staff', 'library_other_staff'
        ];
        
        foreach ($fields as $field) {
            if (in_array($field, ['teacher_student_ratio'])) {
                // Trường tỷ lệ tính trung bình (chỉ tính các giá trị không null)
                $values = $objects->pluck($field)->filter(function($value) {
                    return $value !== null && $value !== '';
                });
                $total->$field = $values->isNotEmpty() ? $values->avg() : null;
            } else {
                // Các trường khác tính tổng (chỉ tính các giá trị không null)
                $values = $objects->pluck($field)->filter(function($value) {
                    return $value !== null && $value !== '';
                });
                $total->$field = $values->isNotEmpty() ? $values->sum() : null;
            }
        }

        return $total;
    }

}
