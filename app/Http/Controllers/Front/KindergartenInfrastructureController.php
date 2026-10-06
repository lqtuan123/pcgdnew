<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Front\BaseMultiLevelReportController;
use App\Models\KindergartenInfrastructureReport;
use App\Models\GeoUnit;
use App\Models\GeoType;
use App\Models\EducationalLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KindergartenInfrastructureController extends BaseMultiLevelReportController
{
    protected $modelClass = KindergartenInfrastructureReport::class;
    protected $viewName = 'front.kindergarten-infrastructure.index';
    protected $routePrefix = 'kindergarten-infrastructure';

    /**
     * Lấy ID của cấp học Mầm non
     */
    private function getKindergartenLevelId(): ?int
    {
        $kindergartenLevel = EducationalLevel::where('name', 'Mầm non')->first();
        return $kindergartenLevel ? $kindergartenLevel->id : null;
    }

    /**
     * Override method index để thêm filter theo educational_level_id
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

        // Lấy ID của cấp học Mầm non
        $kindergartenLevelId = $this->getKindergartenLevelId();
        if (!$kindergartenLevelId) {
            $reports = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
            $columns = $this->columns('province');
            $groups = $this->columnGroups('province');
            $stats = collect([]);
            $level = 'province';
            $routePrefix = $this->routePrefix;

            return view($this->viewName, compact(
                'reports', 'year', 'q', 'stats', 'level', 'perPage', 'routePrefix', 'geoIds'
            ));
        }

        // Xác định cấp độ hiển thị dựa trên user role và filter
        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->getReportsByLevelWithFilter($level, $year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId);
        $columns = $this->columns($level);
        $groups = $this->columnGroups($level);
        $stats = $this->getStatistics($year, $geoIds['ward']);
        $routePrefix = $this->routePrefix;

        return view($this->viewName, compact(
            'reports', 'year', 'q', 'stats', 'level', 'perPage', 'routePrefix', 'geoIds'
        ));
    }

    /**
     * Lấy dữ liệu báo cáo theo cấp độ với filter Mầm non
     */
    private function getReportsByLevelWithFilter($level, $year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId)
    {
        switch ($level) {
            case 'school':
                return $this->getSchoolLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId);
            case 'ward':
                return $this->getWardLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId);
            case 'province':
                return $this->getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId);
            default:
                return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Lấy dữ liệu cấp trường với filter Mầm non
     */
    private function getSchoolLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId)
    {
        if (!$geoIds['school']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy thông tin trường và kiểm tra xem có phải trường Mầm non không
        $school = \App\Models\School::active()
            ->where('id', $geoIds['school'])
            ->where('educational_level_id', $kindergartenLevelId)
            ->first();
        if (!$school) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $wardId = $school->getWardId();
        if (!$wardId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Tìm báo cáo hiện có hoặc tạo mới
        $report = $this->modelClass::where('academic_year', $year)
            ->where('ward_id', $wardId)
            ->where('school_id', $geoIds['school'])
            ->first();

        if (!$report) {
            // Tạo báo cáo mới với dữ liệu mặc định
            $report = new $this->modelClass($this->getDefaultReportData($year, $wardId, $geoIds['school'], $school));
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
     * Lấy dữ liệu cấp xã với filter Mầm non
     */
    private function getWardLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId)
    {
        if (!$geoIds['ward']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy ward_code từ ward_id
        $ward = \App\Models\GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả trường Mầm non trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schoolsQuery = \App\Models\School::active()
            ->where('ward_code_new', $ward->code)
            ->where('educational_level_id', $kindergartenLevelId);
        
        if ($q) {
            $schoolsQuery->where('name', 'like', "%{$q}%");
        }
        
        $schools = $schoolsQuery->orderBy('name')->get();
        
        // Lấy báo cáo hiện có cho các trường này
        $existingReports = $this->modelClass::with(['school', 'ward'])
            ->where('academic_year', $year)
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
        
        $totalRow = $this->calculateTotals($reportsWithData);
        $totalRow->school_name = 'TỔNG CỘNG';
        $totalRow->school_code = '';
        $totalRow->school_rank = 0;
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
     * Lấy dữ liệu cấp tỉnh với filter Mầm non
     */
    private function getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $kindergartenLevelId)
    {
        if (!$geoIds['province']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy tất cả xã/phường trong tỉnh
        $wards = \App\Models\GeoUnit::where('parent_id', $geoIds['province'])
            ->where('scheme', $scheme)
            ->where('type_level', 3)
            ->orderBy('name')
            ->get();
        
        // Lấy tất cả báo cáo trong tỉnh chỉ cho trường Mầm non (chỉ trường đang hoạt động)
        $reports = $this->modelClass::with(['school', 'ward'])
            ->where('academic_year', $year)
            ->whereHas('school', function ($schoolQuery) use ($kindergartenLevelId) {
                $schoolQuery->active()->where('educational_level_id', $kindergartenLevelId);
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
                $total = $this->calculateTotals($wardReports);
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
        $provinceTotal = $this->calculateTotals($reports);
        $provinceTotal->ward_name = 'TỔNG CỘNG';
        $provinceTotal->school_rank = 0;
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
            'school_rank' => is_numeric($report->school_rank) ? (int) $report->school_rank : 0,
            'has_data' => $hasData
        ];

        // Thêm tất cả các trường báo cáo
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if ($field === 'school_branch') {
                // Xử lý đặc biệt cho school_branch: lấy từ báo cáo hoặc từ thông tin trường
                $object->$field = $report->$field ?? $school->branch ?? '';
            } else {
                $object->$field = $report->$field ?? 0;
            }
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
            'school_rank' => is_numeric($school->rank) ? (int) $school->rank : 0,
            'has_data' => false
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if ($field === 'school_branch') {
                // Xử lý đặc biệt cho school_branch: lấy từ thông tin trường
                $object->$field = $school->branch ?? '';
            } else {
                $object->$field = 0;
            }
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
            'school_rank' => 0,
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
     * Tính tổng cộng cho cấp xã
     */
    protected function calculateTotals($reports)
    {
        $total = new $this->modelClass;
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = 0;
        
        // Tính tổng cho school_branch (điểm trường)
        $total->school_branch = $reports->sum(function($report) {
            $schoolBranch = $report->school_branch ?? '';
            // Xử lý đặc biệt cho school_branch: trim và convert sang số
            $schoolBranch = trim($schoolBranch);
            return (is_numeric($schoolBranch) && $schoolBranch !== '') ? (int)$schoolBranch : 0;
        });
        
        // Tính tổng cho tất cả các trường số
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if ($field === 'school_branch') {
                // school_branch đã được tính ở trên, bỏ qua
                continue;
            } elseif (in_array($field, ['room_class_ratio'])) {
                // Trường tỷ lệ tính trung bình chỉ của những trường có dữ liệu hợp lệ
                $validValues = $reports->filter(function($report) use ($field) {
                    return isset($report->$field) && 
                           $report->$field !== null && 
                           $report->$field !== '' && 
                           $report->$field > 0;
                });
                
                if ($validValues->isNotEmpty()) {
                    $total->$field = $validValues->avg($field);
                } else {
                    $total->$field = 0;
                }
            } else {
                // Các trường khác tính tổng
                $total->$field = $reports->sum($field);
            }
        }

        return $total;
    }

    /**
     * Kiểm tra báo cáo có dữ liệu không
     */
    protected function hasData($report)
    {
        $importantFields = ['total_classrooms', 'total_k5_rooms', 'solid_rooms_count'];
        
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
            'school_branch', 'total_classrooms', 'total_k5_classes', 'mixed_k5_classes', 'k5_double_shift_classes',
            'under_5_classes', 'total_k5_rooms', 'room_class_ratio', 'solid_rooms_count',
            'solid_rooms_avg_area', 'semi_solid_rooms_count', 'semi_solid_rooms_avg_area',
            'temporary_rooms_count', 'temporary_rooms_avg_area', 'fully_equipped_rooms',
            'toilet_rooms_count', 'clean_water_system', 'kitchen_count', 'playground_count',
            'equipped_playground_count'
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
            'school_rank' => 0,
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $data[$field] = 0;
        }

        return $data;
    }

    /**
     * Lấy tên file export
     */
    protected function getExportFilename($year)
    {
        return 'bao_cao_co_so_vat_chat_mam_non_' . ($year ?: 'all') . '.xlsx';
    }

    /**
     * Lấy prefix cho temp file
     */
    protected function getTempFilePrefix()
    {
        return 'kindergarten_infrastructure_';
    }

    /**
     * Lấy cột hiển thị theo cấp
     */
    public function columns($level = 'school')
    {
        $baseColumns = [
            'tt' => 'TT',
            'school_name' => 'Trường/Nhóm lớp ĐLTT',
            'school_branch' => 'Điểm trường',
            'total_classrooms' => 'Tổng số phòng học',
            'total_k5_classes' => 'Tổng số',
            'mixed_k5_classes' => 'Tr.đó, lớp ghép 5 tuổi',
            'k5_double_shift_classes' => 'Số lớp học 2 buổi/ngày',
            'under_5_classes' => 'Số lớp, nhóm dưới 5 tuổi',
            'total_k5_rooms' => 'Tổng số',
            'room_class_ratio' => 'Tỷ lệ P/L',
            'solid_rooms_count' => 'Tổng số',
            'solid_rooms_avg_area' => 'DTBQ phòng SHC (m2)',
            'semi_solid_rooms_count' => 'Tổng số',
            'semi_solid_rooms_avg_area' => 'DTBQ phòng SHC (m2)',
            'temporary_rooms_count' => 'Tổng số',
            'temporary_rooms_avg_area' => 'DTBQ phòng SHC (m2)',
            'fully_equipped_rooms' => 'Đủ bộ TBị, ĐD, ĐC TT',
            'toilet_rooms_count' => 'Số lượng',
            'clean_water_system' => 'Số lượng',
            'kitchen_count' => 'Số lượng',
            'playground_count' => 'Số lượng',
            'equipped_playground_count' => 'Số lượng',
        ];

        // Điều chỉnh cột theo cấp
        if ($level === 'province') {
            $baseColumns['school_name'] = 'Tên xã/phường';
            unset($baseColumns['school_branch']);
        } elseif ($level === 'ward') {
            // Giữ nguyên tên trường và điểm trường
        } else {
            // Cấp trường - ẩn tên trường và điểm trường
            unset($baseColumns['school_name'], $baseColumns['school_branch']);
        }

        return $baseColumns;
    }

    /**
     * Lấy nhóm cột theo cấp
     */
    public function columnGroups($level = 'school')
    {
        $baseGroups = [
            [
                'title' => 'TT',
                'keys' => ['tt'],
            ],
            [
                'title' => 'Trường/Nhóm lớp ĐLTT',
                'keys' => ['school_name'],
            ],
            [
                'title' => 'Điểm trường',
                'keys' => ['school_branch'],
            ],
            [
                'title' => 'Tổng số phòng học',
                'keys' => ['total_classrooms'],
            ],
            [
                'title' => 'Số lớp, nhóm',
                'subgroups' => [
                    [
                        'title' => 'Số lớp MG 5 tuổi',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['total_k5_classes'],
                            ],
                            [
                                'title' => 'Tr.đó, lớp ghép 5 tuổi',
                                'keys' => ['mixed_k5_classes'],
                            ],
                            [
                                'title' => 'Số lớp học 2 buổi/ngày',
                                'keys' => ['k5_double_shift_classes'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Số lớp, nhóm dưới 5 tuổi',
                        'keys' => ['under_5_classes'],
                    ],
                ],
            ],
            [
                'title' => 'Phòng học cho lớp MG 5 tuổi',
                'subgroups' => [
                    [
                        'title' => 'Tổng số',
                        'keys' => ['total_k5_rooms'],
                    ],
                    [
                        'title' => 'Tỷ lệ P/L',
                        'keys' => ['room_class_ratio'],
                    ],
                    [
                        'title' => 'Kiên cố',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['solid_rooms_count'],
                            ],
                            [
                                'title' => 'DTBQ phòng SHC (m2)',
                                'keys' => ['solid_rooms_avg_area'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Bán kiên cố',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['semi_solid_rooms_count'],
                            ],
                            [
                                'title' => 'DTBQ phòng SHC (m2)',
                                'keys' => ['semi_solid_rooms_avg_area'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Phòng học khác (tạm, nhờ)',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['temporary_rooms_count'],
                            ],
                            [
                                'title' => 'DTBQ phòng SHC (m2)',
                                'keys' => ['temporary_rooms_avg_area'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Đủ bộ TBị, ĐD, ĐC TT',
                        'keys' => ['fully_equipped_rooms'],
                    ],
                ],
            ],
            [
                'title' => 'Phòng hoặc khu vệ sinh',
                'keys' => ['toilet_rooms_count'],
            ],
            [
                'title' => 'CT nước sạch',
                'keys' => ['clean_water_system'],
            ],
            [
                'title' => 'Bếp ăn',
                'keys' => ['kitchen_count'],
            ],
            [
                'title' => 'Sân và đồ chơi',
                'subgroups' => [
                    [
                        'title' => 'Sân chơi',
                        'keys' => ['playground_count'],
                    ],
                    [
                        'title' => 'Tr đồ: Sân có ĐC',
                        'keys' => ['equipped_playground_count'],
                    ],
                ],
            ],
        ];

        // Điều chỉnh nhóm cột theo cấp
        if ($level === 'province') {
            $baseGroups[1]['title'] = 'Tên xã/phường';
            unset($baseGroups[2]); // Bỏ điểm trường
        } elseif ($level === 'ward') {
            // Giữ nguyên
        } else {
            // Cấp trường - ẩn tên trường và điểm trường
            unset($baseGroups[1], $baseGroups[2]);
        }

        return $baseGroups;
    }

    /**
     * Tải file mẫu Excel
     */
    public function template(): BinaryFileResponse
    {
        $path = storage_path('app/templates/kindergarten_infrastructure_template.xlsx');
        return response()->download($path, 'mau_bao_cao_co_so_vat_chat_mam_non.xlsx');
    }

    /**
     * Export Excel theo mẫu 1.3.MN.CSVC.xlsx
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

        $kindergartenLevelId = $this->getKindergartenLevelId();
        if (!$kindergartenLevelId) {
            return response()->json(['error' => 'Không tìm thấy cấp học Mầm non'], 400);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->getReportsByLevelWithFilter($level, $year, '', $geoIds, 'old', 1000, $kindergartenLevelId);
        
        $templatePath = resource_path('excel/1.3.MN.CSVC.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        
        // Ghi năm học vào ô D3
        $worksheet->setCellValue('D3', 'Năm học ' . $year);
        
        // Bỏ phần ký tên cho cấp trường
        // $this->addSignatureSectionForSchool($worksheet, $wardName);
        
        // Format cho các ô được ghi dữ liệu mới
        $cellsToFormat = ['C2', 'C3', 'D3'];
        foreach ($cellsToFormat as $cell) {
            $worksheet->getStyle($cell)->getFont()->setName('Tahoma');
            $worksheet->getStyle($cell)->getFont()->setSize(8);
        }
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('C2')->getFont()->setBold(true);
        $worksheet->getStyle('C3')->getFont()->setBold(true);
        
        // In đậm ngày xuất file (bỏ nghiêng)
        $worksheet->getStyle('D3')->getFont()->setBold(true);
        $worksheet->getStyle('D3')->getFont()->setItalic(false);
        

        $this->fillInfrastructureData($worksheet, $reports, $level);

        $filename = 'bao_cao_co_so_vat_chat_mam_non_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'kindergarten_infrastructure_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Thêm phần ký tên cho báo cáo trường Mầm non (dòng 14-17)
     */
    private function addSignatureSectionForSchool($worksheet, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô R14
        $dateRow = 14;
        $worksheet->setCellValue('R' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô E15, in đậm
        $signatureRow1 = 15;
        $worksheet->setCellValue('E' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('E' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô R15, in đậm
        $worksheet->setCellValue('R' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('R' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô E16, in nghiêng
        $signatureRow2 = 16;
        $worksheet->setCellValue('E' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('E' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô R16, in đậm
        $worksheet->setCellValue('R' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('R' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô R17, in nghiêng
        $signatureRow3 = 17;
        $worksheet->setCellValue('R' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('R' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'E' . $signatureRow1 . ':R' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên (dòng 14-17)
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('E' . $row . ':R' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('E' . $row . ':R' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Export Excel cho báo cáo thống kê xã - xuất tất cả trường mầm non trong xã
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

        $kindergartenLevelId = $this->getKindergartenLevelId();
        if (!$kindergartenLevelId) {
            return response()->json(['error' => 'Không tìm thấy cấp học Mầm non'], 400);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        // Lấy tất cả báo cáo cơ sở vật chất mầm non trong xã
        $reports = $this->getReportsByLevelWithFilter($level, $year, '', $geoIds, 'old', 1000, $kindergartenLevelId);
        
        $templatePath = resource_path('excel/1.3.MN.CSVC.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        
        // Ghi năm học vào ô D3
        $worksheet->setCellValue('D3', 'Năm học ' . $year);
        
        // Thêm phần ký tên từ dòng 14 đến 17
        $this->addSignatureSectionForSchool($worksheet, $wardName);
        
        // Format cho các ô được ghi dữ liệu mới
        $cellsToFormat = ['C2', 'C3', 'D3'];
        foreach ($cellsToFormat as $cell) {
            $worksheet->getStyle($cell)->getFont()->setName('Tahoma');
            $worksheet->getStyle($cell)->getFont()->setSize(8);
        }
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('C2')->getFont()->setBold(true);
        $worksheet->getStyle('C3')->getFont()->setBold(true);
        
        // In đậm ngày xuất file (bỏ nghiêng)
        $worksheet->getStyle('D3')->getFont()->setBold(true);
        $worksheet->getStyle('D3')->getFont()->setItalic(false);
        

        // Format lại vùng A15:T17 về bình thường (cấu trúc ký tên)
        $this->resetSignatureAreaFormat($worksheet);

        // Cài đặt rows to repeat at top cho tiêu đề (dòng 6-9)
        $this->setupPrintTitles($worksheet);

        // Điền dữ liệu cho tất cả trường trong xã
        $this->fillInfrastructureDataForWard($worksheet, $reports, $level, $wardName);

        $filename = 'bao_cao_co_so_vat_chat_mam_non_xa_' . $wardName . '_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'kindergarten_infrastructure_ward_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Điền dữ liệu cơ sở vật chất vào Excel cho báo cáo xã
     */
    private function fillInfrastructureDataForWard($worksheet, $reports, $level, $wardName)
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
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Điểm trường - convert string sang số nếu có thể, để rỗng nếu không có dữ liệu
            $schoolBranch = $report->school_branch ?? '';
            $schoolBranch = trim($schoolBranch);
            $branchValue = (is_numeric($schoolBranch) && $schoolBranch !== '') ? (int)$schoolBranch : '';
            $worksheet->setCellValue('D' . $currentRow, $branchValue);
            
            // Tổng số phòng học
            $worksheet->setCellValue('E' . $currentRow, $report->total_classrooms ?: '');
            
            // Số lớp MG 5 tuổi - Tổng số
            $worksheet->setCellValue('F' . $currentRow, $report->total_k5_classes ?: '');
            
            // Số lớp MG 5 tuổi - Lớp ghép 5 tuổi
            $worksheet->setCellValue('G' . $currentRow, $report->mixed_k5_classes ?: '');
            
            // Số lớp MG 5 tuổi - Lớp học 2 buổi/ngày
            $worksheet->setCellValue('H' . $currentRow, $report->k5_double_shift_classes ?: '');
            
            // Số lớp, nhóm dưới 5 tuổi
            $worksheet->setCellValue('I' . $currentRow, $report->under_5_classes ?: '');
            
            // Tổng số (cột 9)
            $worksheet->setCellValue('J' . $currentRow, $report->total_k5_rooms ?: '');
            
            // Tỷ lệ P/L
            $worksheet->setCellValue('K' . $currentRow, $report->room_class_ratio ?: '');
            
            // Phòng học kiên cố - Tổng số
            $worksheet->setCellValue('L' . $currentRow, $report->solid_rooms_count ?: '');
            
            // Phòng học kiên cố - DTBQ phòng SHC (m2)
            $worksheet->setCellValue('M' . $currentRow, $report->solid_rooms_avg_area ?: '');
            
            // Phòng học bán kiên cố - Tổng số
            $worksheet->setCellValue('N' . $currentRow, $report->semi_solid_rooms_count ?: '');
            
            // Phòng học bán kiên cố - DTBQ phòng SHC (m2)
            $worksheet->setCellValue('O' . $currentRow, $report->semi_solid_rooms_avg_area ?: '');
            
            // Phòng học khác - Tổng số
            $worksheet->setCellValue('P' . $currentRow, $report->temporary_rooms_count ?: '');
            
            // Phòng học khác - DTBQ phòng SHC (m2)
            $worksheet->setCellValue('Q' . $currentRow, $report->temporary_rooms_avg_area ?: '');
            
            // Đủ bộ TBị, ĐD, ĐC TT
            $worksheet->setCellValue('R' . $currentRow, $report->fully_equipped_rooms ?: '');
            
            // Phòng hoặc khu vệ sinh
            $worksheet->setCellValue('S' . $currentRow, $report->toilet_rooms_count ?: '');
            
            // CT nước sạch
            $worksheet->setCellValue('T' . $currentRow, $report->clean_water_system ?: '');
            
            // Bếp ăn
            $worksheet->setCellValue('U' . $currentRow, $report->kitchen_count ?: '');
            
            // Sân chơi
            $worksheet->setCellValue('V' . $currentRow, $report->playground_count ?: '');
            
            // Sân có ĐC
            $worksheet->setCellValue('W' . $currentRow, $report->equipped_playground_count ?: '');

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
        $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
        
        // Căn trái cho chữ "TỔNG CỘNG"
        $worksheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $this->setBoldTotalRowFont($worksheet, $currentRow);

        $totals = $this->calculateTotals(collect($dataRows));

        // Điểm trường - tính tổng riêng, không dùng calculateTotals
        $totalBranches = collect($dataRows)->sum(function($item) {
            $schoolBranch = $item->school_branch ?? '';
            // Xử lý đặc biệt cho school_branch: trim và convert sang số
            $schoolBranch = trim($schoolBranch);
            return (is_numeric($schoolBranch) && $schoolBranch !== '') ? (int)$schoolBranch : 0;
        });
        $worksheet->setCellValue('D' . $currentRow, $totalBranches);
        
        // Tổng số phòng học
        $worksheet->setCellValue('E' . $currentRow, $totals->total_classrooms ?? 0);
        
        // Số lớp MG 5 tuổi - Tổng số
        $worksheet->setCellValue('F' . $currentRow, $totals->total_k5_classes ?? 0);
        
        // Số lớp MG 5 tuổi - Lớp ghép 5 tuổi
        $worksheet->setCellValue('G' . $currentRow, $totals->mixed_k5_classes ?? 0);
        
        // Số lớp MG 5 tuổi - Lớp học 2 buổi/ngày
        $worksheet->setCellValue('H' . $currentRow, $totals->k5_double_shift_classes ?? 0);
        
        // Số lớp, nhóm dưới 5 tuổi
        $worksheet->setCellValue('I' . $currentRow, $totals->under_5_classes ?? 0);
        
        // Tổng số (cột 9)
        $worksheet->setCellValue('J' . $currentRow, $totals->total_k5_rooms ?? 0);
        
        // Tỷ lệ P/L (tính trung bình)
        $worksheet->setCellValue('K' . $currentRow, $totals->room_class_ratio ?? 0);
        
        // Phòng học kiên cố - Tổng số
        $worksheet->setCellValue('L' . $currentRow, $totals->solid_rooms_count ?? 0);
        
        // Phòng học kiên cố - DTBQ phòng SHC (m2) (tính trung bình)
        $worksheet->setCellValue('M' . $currentRow, $totals->solid_rooms_avg_area ?? 0);
        
        // Phòng học bán kiên cố - Tổng số
        $worksheet->setCellValue('N' . $currentRow, $totals->semi_solid_rooms_count ?? 0);
        
        // Phòng học bán kiên cố - DTBQ phòng SHC (m2) (tính trung bình)
        $worksheet->setCellValue('O' . $currentRow, $totals->semi_solid_rooms_avg_area ?? 0);
        
        // Phòng học khác - Tổng số
        $worksheet->setCellValue('P' . $currentRow, $totals->temporary_rooms_count ?? 0);
        
        // Phòng học khác - DTBQ phòng SHC (m2) (tính trung bình)
        $worksheet->setCellValue('Q' . $currentRow, $totals->temporary_rooms_avg_area ?? 0);
        
        // Đủ bộ TBị, ĐD, ĐC TT
        $worksheet->setCellValue('R' . $currentRow, $totals->fully_equipped_rooms ?? 0);
        
        // Phòng hoặc khu vệ sinh
        $worksheet->setCellValue('S' . $currentRow, $totals->toilet_rooms_count ?? 0);
        
        // CT nước sạch
        $worksheet->setCellValue('T' . $currentRow, $totals->clean_water_system ?? 0);
        
        // Bếp ăn
        $worksheet->setCellValue('U' . $currentRow, $totals->kitchen_count ?? 0);
        
        // Sân chơi
        $worksheet->setCellValue('V' . $currentRow, $totals->playground_count ?? 0);
        
        // Sân có ĐC
        $worksheet->setCellValue('W' . $currentRow, $totals->equipped_playground_count ?? 0);
        
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
        // Tên xã, ngày ... tháng ... năm ... vào ô R(x+2)
        $dateRow = $totalRow + 2;
        $worksheet->setCellValue('R' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô E(x+3), in đậm
        $signatureRow1 = $totalRow + 3;
        $worksheet->setCellValue('E' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('E' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô R(x+3), in đậm
        $worksheet->setCellValue('R' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('R' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô E(x+4), in nghiêng
        $signatureRow2 = $totalRow + 4;
        $worksheet->setCellValue('E' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('E' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô R(x+4), in đậm
        $worksheet->setCellValue('R' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('R' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô R(x+5), in nghiêng
        $signatureRow3 = $totalRow + 5;
        $worksheet->setCellValue('R' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('R' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'E' . $signatureRow1 . ':R' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(16.80);
            $worksheet->getStyle('E' . $row . ':R' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('E' . $row . ':R' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Điền dữ liệu cơ sở vật chất vào Excel
     */
    private function fillInfrastructureData($worksheet, $reports, $level)
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
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Điểm trường
            $worksheet->setCellValue('D' . $currentRow, $report->school_branch ?? '');
            
            // Tổng số phòng học
            $worksheet->setCellValue('E' . $currentRow, $report->total_classrooms ?? 0);
            
            // Số lớp MG 5 tuổi - Tổng số
            $worksheet->setCellValue('F' . $currentRow, $report->total_k5_classes ?? 0);
            
            // Số lớp MG 5 tuổi - Lớp ghép 5 tuổi
            $worksheet->setCellValue('G' . $currentRow, $report->mixed_k5_classes ?? 0);
            
            // Số lớp MG 5 tuổi - Lớp học 2 buổi/ngày
            $worksheet->setCellValue('H' . $currentRow, $report->k5_double_shift_classes ?? 0);
            
            // Số lớp, nhóm dưới 5 tuổi
            $worksheet->setCellValue('I' . $currentRow, $report->under_5_classes ?? 0);
            
            // Tổng số (cột 9)
            $worksheet->setCellValue('J' . $currentRow, $report->total_k5_rooms ?? 0);
            
            // Tỷ lệ P/L
            $worksheet->setCellValue('K' . $currentRow, $report->room_class_ratio ?? 0);
            
            // Phòng học kiên cố - Tổng số
            $worksheet->setCellValue('L' . $currentRow, $report->solid_rooms_count ?? 0);
            
            // Phòng học kiên cố - DTBQ phòng SHC (m2)
            $worksheet->setCellValue('M' . $currentRow, $report->solid_rooms_avg_area ?? 0);
            
            // Phòng học bán kiên cố - Tổng số
            $worksheet->setCellValue('N' . $currentRow, $report->semi_solid_rooms_count ?? 0);
            
            // Phòng học bán kiên cố - DTBQ phòng SHC (m2)
            $worksheet->setCellValue('O' . $currentRow, $report->semi_solid_rooms_avg_area ?? 0);
            
            // Phòng học khác - Tổng số
            $worksheet->setCellValue('P' . $currentRow, $report->temporary_rooms_count ?? 0);
            
            // Phòng học khác - DTBQ phòng SHC (m2)
            $worksheet->setCellValue('Q' . $currentRow, $report->temporary_rooms_avg_area ?? 0);
            
            // Đủ bộ TBị, ĐD, ĐC TT
            $worksheet->setCellValue('R' . $currentRow, $report->fully_equipped_rooms ?? 0);
            
            // Phòng hoặc khu vệ sinh
            $worksheet->setCellValue('S' . $currentRow, $report->toilet_rooms_count ?? 0);
            
            // CT nước sạch
            $worksheet->setCellValue('T' . $currentRow, $report->clean_water_system ?? 0);
            
            // Bếp ăn
            $worksheet->setCellValue('U' . $currentRow, $report->kitchen_count ?? 0);
            
            // Sân chơi
            $worksheet->setCellValue('V' . $currentRow, $report->playground_count ?? 0);
            
            // Sân có ĐC
            $worksheet->setCellValue('W' . $currentRow, $report->equipped_playground_count ?? 0);
            
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
        // Cột A để trống
        $worksheet->setCellValue('A' . $row, '');
        
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':C' . $row);
        
        // Căn trái cho chữ "TỔNG CỘNG"
        $worksheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $this->setBoldTotalRowFont($worksheet, $row);

        $totals = $this->calculateTotals(collect($dataRows));

        // Điểm trường
        $worksheet->setCellValue('D' . $row, $totals->school_branch ?? 0);
        
        // Tổng số phòng học
        $worksheet->setCellValue('E' . $row, $totals->total_classrooms ?? 0);
        
        // Số lớp MG 5 tuổi - Tổng số
        $worksheet->setCellValue('F' . $row, $totals->total_k5_classes ?? 0);
        
        // Số lớp MG 5 tuổi - Lớp ghép 5 tuổi
        $worksheet->setCellValue('G' . $row, $totals->mixed_k5_classes ?? 0);
        
        // Số lớp MG 5 tuổi - Lớp học 2 buổi/ngày
        $worksheet->setCellValue('H' . $row, $totals->k5_double_shift_classes ?? 0);
        
        // Số lớp, nhóm dưới 5 tuổi
        $worksheet->setCellValue('I' . $row, $totals->under_5_classes ?? 0);
        
        // Tổng số (cột 9)
        $worksheet->setCellValue('J' . $row, $totals->total_k5_rooms ?? 0);
        
        // Tỷ lệ P/L (tính trung bình)
        $worksheet->setCellValue('K' . $row, $totals->room_class_ratio ?? 0);
        
        // Phòng học kiên cố - Tổng số
        $worksheet->setCellValue('L' . $row, $totals->solid_rooms_count ?? 0);
        
        // Phòng học kiên cố - DTBQ phòng SHC (m2) (tính trung bình)
        $worksheet->setCellValue('M' . $row, $totals->solid_rooms_avg_area ?? 0);
        
        // Phòng học bán kiên cố - Tổng số
        $worksheet->setCellValue('N' . $row, $totals->semi_solid_rooms_count ?? 0);
        
        // Phòng học bán kiên cố - DTBQ phòng SHC (m2) (tính trung bình)
        $worksheet->setCellValue('O' . $row, $totals->semi_solid_rooms_avg_area ?? 0);
        
        // Phòng học khác - Tổng số
        $worksheet->setCellValue('P' . $row, $totals->temporary_rooms_count ?? 0);
        
        // Phòng học khác - DTBQ phòng SHC (m2) (tính trung bình)
        $worksheet->setCellValue('Q' . $row, $totals->temporary_rooms_avg_area ?? 0);
        
        // Đủ bộ TBị, ĐD, ĐC TT
        $worksheet->setCellValue('R' . $row, $totals->fully_equipped_rooms ?? 0);
        
        // Phòng hoặc khu vệ sinh
        $worksheet->setCellValue('S' . $row, $totals->toilet_rooms_count ?? 0);
        
        // CT nước sạch
        $worksheet->setCellValue('T' . $row, $totals->clean_water_system ?? 0);
        
        // Bếp ăn
        $worksheet->setCellValue('U' . $row, $totals->kitchen_count ?? 0);
        
        // Sân chơi
        $worksheet->setCellValue('V' . $row, $totals->playground_count ?? 0);
        
        // Sân có ĐC
        $worksheet->setCellValue('W' . $row, $totals->equipped_playground_count ?? 0);
        
        // In đậm tất cả dữ liệu trong dòng tổng cộng
        $this->setBoldTotalRowFont($worksheet, $row);
    }

    /**
     * Đảm bảo font và căn lề nhất quán cho tất cả các ô trong dòng
     */
    private function setConsistentFont($worksheet, $row)
    {
        // Đặt font mặc định cho toàn bộ dòng (A đến W)
        $range = 'A' . $row . ':W' . $row;
        $style = $worksheet->getStyle($range);
        
        // Đặt chiều cao dòng là 16.80px
        $worksheet->getRowDimension($row)->setRowHeight(16.80);
        
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
            'B' => 'left',      // Tên trường - căn trái
            'C' => 'left',      // Tên trường (merged) - căn trái
            'D' => 'center',    // Điểm trường - căn giữa
            'E' => 'center',    // Số liệu - căn giữa
            'F' => 'center',
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
            'W' => 'center'     // Cột cuối - căn giữa
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
            if ($col === 'K') {
                // Cột K: Tỷ lệ P/L - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['M', 'O', 'Q'])) {
                // Các cột diện tích: DTBQ phòng SHC (m2) - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['E', 'F', 'G', 'H', 'I', 'J', 'L', 'N', 'P', 'R', 'S', 'T', 'U', 'V', 'W'])) {
                // Các cột số khác - format số nguyên
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
            return '';
        }
        
        $province = \App\Models\GeoUnit::find($provinceId);
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
        
        $ward = \App\Models\GeoUnit::find($wardId);
        return $ward ? $ward->name : '';
    }

    /**
     * Lưu dữ liệu cấp trường
     */
    public function saveSchoolData(Request $request)
    {
        try {
            // Validation rules cho KindergartenInfrastructureReport
            $rules = [
                'academic_year' => 'required|string',
                'school_id' => 'required|integer|min:1',
                'ward_id' => 'required|integer|min:1',
                'school_name' => 'nullable|string',
                'school_branch' => 'nullable|string',
                // Tất cả các trường cơ sở vật chất đều nullable
                'total_classrooms' => 'nullable|numeric|min:0',
                'total_k5_classes' => 'nullable|numeric|min:0',
                'mixed_k5_classes' => 'nullable|numeric|min:0',
                'k5_double_shift_classes' => 'nullable|numeric|min:0',
                'under_5_classes' => 'nullable|numeric|min:0',
                'total_k5_rooms' => 'nullable|numeric|min:0',
                'room_class_ratio' => 'nullable|numeric|min:0',
                'solid_rooms_count' => 'nullable|numeric|min:0',
                'solid_rooms_avg_area' => 'nullable|numeric|min:0',
                'semi_solid_rooms_count' => 'nullable|numeric|min:0',
                'semi_solid_rooms_avg_area' => 'nullable|numeric|min:0',
                'temporary_rooms_count' => 'nullable|numeric|min:0',
                'temporary_rooms_avg_area' => 'nullable|numeric|min:0',
                'fully_equipped_rooms' => 'nullable|numeric|min:0',
                'toilet_rooms_count' => 'nullable|numeric|min:0',
                'clean_water_system' => 'nullable|numeric|min:0',
                'kitchen_count' => 'nullable|numeric|min:0',
                'playground_count' => 'nullable|numeric|min:0',
                'equipped_playground_count' => 'nullable|numeric|min:0',
            ];

            $validated = $request->validate($rules);

            // Đảm bảo tất cả các trường numeric có giá trị mặc định là 0
            $numericFields = [
                'total_classrooms', 'total_k5_classes', 'mixed_k5_classes', 'k5_double_shift_classes',
                'under_5_classes', 'total_k5_rooms', 'room_class_ratio', 'solid_rooms_count',
                'solid_rooms_avg_area', 'semi_solid_rooms_count', 'semi_solid_rooms_avg_area',
                'temporary_rooms_count', 'temporary_rooms_avg_area', 'fully_equipped_rooms',
                'toilet_rooms_count', 'clean_water_system', 'kitchen_count', 'playground_count',
                'equipped_playground_count'
            ];

            foreach ($numericFields as $field) {
                $validated[$field] = $validated[$field] ?? 0;
            }

            // Lấy thông tin trường học
            $school = null;
            if (!empty($validated['school_id'])) {
                $school = \App\Models\School::find($validated['school_id']);
            }

            if ($school) {
                $validated['school_name'] = $school->name;
                $validated['school_branch'] = $validated['school_branch'] ?? '';
            } else {
                $validated['school_name'] = $validated['school_name'] ?? 'Tên trường không xác định';
                $validated['school_branch'] = $validated['school_branch'] ?? '';
            }

            // Tạo hoặc cập nhật báo cáo
            $report = KindergartenInfrastructureReport::updateOrCreate(
                [
                    'academic_year' => $validated['academic_year'],
                    'school_id' => $validated['school_id'],
                    'ward_id' => $validated['ward_id'],
                ],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Lưu dữ liệu thành công!',
                'data' => $report
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Excel data
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
                'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
            ]);

            $user = Auth::user();
            $school = $user->school;

            if (!$school) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin trường của tài khoản'], 400);
            }

            $kindergartenLevelIds = $this->getKindergartenLevelIds();
            if (!in_array($school->educational_level_id, $kindergartenLevelIds)) {
                return response()->json(['success' => false, 'message' => 'Trường này không phải là trường Mầm non'], 400);
            }

            $file = $request->file('excel_file');
            $academicYear = $request->input('academic_year');

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();

            $row = 10; // Data starts from row 10
            $data = [];

            $columnMapping = [
                'D' => 'school_branch', 'E' => 'total_classrooms', 'F' => 'total_k5_classes',
                'G' => 'mixed_k5_classes', 'H' => 'k5_double_shift_classes', 'I' => 'under_5_classes',
                'J' => 'total_k5_rooms', 'K' => 'room_class_ratio', 'L' => 'solid_rooms_count',
                'M' => 'solid_rooms_avg_area', 'N' => 'semi_solid_rooms_count', 'O' => 'semi_solid_rooms_avg_area',
                'P' => 'temporary_rooms_count', 'Q' => 'temporary_rooms_avg_area', 'R' => 'fully_equipped_rooms',
                'S' => 'toilet_rooms_count', 'T' => 'clean_water_system', 'U' => 'kitchen_count',
                'V' => 'playground_count', 'W' => 'equipped_playground_count'
            ];

            foreach ($columnMapping as $col => $field) {
                $value = $worksheet->getCell($col . $row)->getCalculatedValue();
                if (in_array($field, ['school_branch', 'total_classrooms', 'total_k5_classes', 'mixed_k5_classes', 'k5_double_shift_classes', 'under_5_classes', 'total_k5_rooms', 'solid_rooms_count', 'semi_solid_rooms_count', 'temporary_rooms_count', 'fully_equipped_rooms', 'toilet_rooms_count', 'clean_water_system', 'kitchen_count', 'playground_count', 'equipped_playground_count'])) {
                    $data[$field] = is_numeric($value) ? (int)$value : 0;
                } elseif (in_array($field, ['room_class_ratio', 'solid_rooms_avg_area', 'semi_solid_rooms_avg_area', 'temporary_rooms_avg_area'])) {
                    $data[$field] = is_numeric($value) ? (float)$value : 0;
                } else {
                    $data[$field] = $value;
                }
            }

            $data['school_id'] = $school->id;
            $data['ward_id'] = $school->getWardId();
            $data['academic_year'] = $academicYear;
            $data['school_name'] = $school->name;

            $report = KindergartenInfrastructureReport::updateOrCreate(
                ['school_id' => $school->id, 'academic_year' => $academicYear],
                $data
            );

            return response()->json(['success' => true, 'message' => 'Nhập dữ liệu thành công!', 'data' => $report]);

        } catch (\Exception $e) {
            Log::error('Import Excel error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra khi nhập dữ liệu: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lấy ID của các cấp học Mầm non
     */
    private function getKindergartenLevelIds(): array
    {
        $kindergartenLevels = EducationalLevel::whereIn('name', [
            'Mầm non',
            'Mầm non + Tiểu học',
            'Mầm non + Tiểu học + THCS',
            'Mầm non + Tiểu học + THCS + THPT'
        ])->pluck('id')->toArray();

        return $kindergartenLevels;
    }

    /**
     * Đặt font in đậm cho dòng tổng cộng
     */
    private function setBoldTotalRowFont($worksheet, $row)
    {
        // Đặt font in đậm cho toàn bộ dòng (A đến W)
        $range = 'A' . $row . ':W' . $row;
        $style = $worksheet->getStyle($range);
        
        // Đặt chiều cao dòng là 16.80px
        $worksheet->getRowDimension($row)->setRowHeight(16.80);
        
        // Font settings - in đậm
        $style->getFont()->setBold(true);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Đặt padding cho các dòng từ dòng 9 trở đi
        if ($row >= 9) {
            $style->getAlignment()->setIndent(0);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Number format cho các cột số trong dòng tổng cộng
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W'];
        foreach ($columns as $col) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Căn lề cho từng cột
            if (in_array($col, ['B', 'C'])) {
                // Cột B, C: "TỔNG CỘNG" - căn trái
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                // Các cột khác - căn giữa
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            
            if ($col === 'K') {
                // Cột K: Tỷ lệ P/L - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['M', 'O', 'Q'])) {
                // Các cột diện tích: DTBQ phòng SHC (m2) - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['E', 'F', 'G', 'H', 'I', 'J', 'L', 'N', 'P', 'R', 'S', 'T', 'U', 'V', 'W'])) {
                // Các cột số khác - format số nguyên
                $cellStyle->getNumberFormat()->setFormatCode('0');
            }
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
}