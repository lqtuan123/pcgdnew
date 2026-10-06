<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Front\BaseMultiLevelReportController;
use App\Models\KindergartenReport;
use App\Models\GeoUnit;
use App\Models\GeoType;
use App\Models\EducationalLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KindergartenReportController extends BaseMultiLevelReportController
{
    protected $modelClass = KindergartenReport::class;
    protected $viewName = 'front.kindergarten-reports.index';
    protected $routePrefix = 'kindergarten-reports';

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
            'school_rank' => is_numeric($school->rank) ? (int) $school->rank : 0,
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
        
        // Tính tổng cho tất cả các trường số
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if (in_array($field, ['teacher_ratio', 'teacher_ratio_k5'])) {
                // Trường tỷ lệ tính trung bình
                $total->$field = $reports->avg($field);
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
        $importantFields = ['total_teachers', 'total_all', 'total_cbql'];
        
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
            'total_all', 'contract_work_all', 'contract_labor_all', 'cs_policy',
            'total_cbql', 'vice_principal_cbql', 'total_teachers', 'ethnic_minority',
            'teacher_ratio', 'total_staff', 'total_k5', 'contract_work_k5',
            'contract_labor_k5', 'cs_policy_k5', 'teacher_ratio_k5', 'training_standard',
            'training_above_standard', 'standard_profession'
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
    public function getExportFilename($year)
    {
        return 'bao_cao_doi_ngu_giao_vien_mam_non_' . ($year ?: 'all') . '.xlsx';
    }

    /**
     * Lấy prefix cho temp file
     */
    public function getTempFilePrefix()
    {
        return 'kindergarten_report_';
    }

    /**
     * Lấy cột hiển thị theo cấp
     */
    public function columns($level = 'school')
    {
        $baseColumns = [
            'tt' => 'TT',
            'school_name' => 'Trường/Nhóm lớp ĐLTT',
            'school_rank' => 'Hạng trường',
            // CBQL, giáo viên, nhân viên
            'total_all' => 'Tổng số',
            'contract_work_all' => 'Hợp đồng làm việc',
            'contract_labor_all' => 'Tổng số',
            'cs_policy' => 'Được hưởng CĐ, CS theo quy định',
            'total_cbql' => 'Tổng số',
            'vice_principal_cbql' => 'Phó Hiệu trưởng',
            'total_teachers' => 'Tổng số',
            'ethnic_minority' => 'Dân tộc',
            'teacher_ratio' => 'Tỷ lệ GV/L',
            'total_staff' => 'Tổng số',
            // Giáo viên dạy MG 5 tuổi
            'total_k5' => 'Tổng số',
            'contract_work_k5' => 'Hợp đồng làm việc',
            'contract_labor_k5' => 'Tổng số',
            'cs_policy_k5' => 'Được hưởng CĐ, CS theo quy định',
            'teacher_ratio_k5' => 'Tỷ lệ GV/Lớp',
            'training_standard' => 'Đạt chuẩn',
            'training_above_standard' => 'Trên chuẩn',
            'standard_profession' => 'Đạt chuẩn nghề nghiệp',
        ];

        // Điều chỉnh cột theo cấp
        if ($level === 'province') {
            $baseColumns['school_name'] = 'Tên xã/phường';
            unset($baseColumns['school_rank']);
        } elseif ($level === 'ward') {
            // Giữ nguyên tên trường và hạng trường
        } else {
            // Cấp trường - ẩn tên trường và hạng trường
            unset($baseColumns['school_name'], $baseColumns['school_rank']);
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
                'title' => 'Hạng trường',
                'keys' => ['school_rank'],
            ],
            [
                'title' => 'CBQL, giáo viên, nhân viên',
                'subgroups' => [
                    [
                        'title' => 'Tổng số',
                        'keys' => ['total_all'],
                    ],
                    [
                        'title' => 'Hợp đồng làm việc',
                        'keys' => ['contract_work_all'],
                    ],
                    [
                        'title' => 'Hợp đồng lao động',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['contract_labor_all'],
                            ],
                            [
                                'title' => 'Được hưởng CĐ, CS theo quy định',
                                'keys' => ['cs_policy'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'CBQL',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['total_cbql'],
                            ],
                            [
                                'title' => 'Phó Hiệu trưởng',
                                'keys' => ['vice_principal_cbql'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Giáo viên',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['total_teachers'],
                            ],
                            [
                                'title' => 'Dân tộc',
                                'keys' => ['ethnic_minority'],
                            ],
                            [
                                'title' => 'Tỷ lệ GV/L',
                                'keys' => ['teacher_ratio'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Nhân viên',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['total_staff'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Giáo viên dạy MG 5 tuổi',
                'subgroups' => [
                    [
                        'title' => 'Tổng số',
                        'keys' => ['total_k5'],
                    ],
                    [
                        'title' => 'Hợp đồng làm việc',
                        'keys' => ['contract_work_k5'],
                    ],
                    [
                        'title' => 'Hợp đồng lao động',
                        'subsubgroups' => [
                            [
                                'title' => 'Tổng số',
                                'keys' => ['contract_labor_k5'],
                            ],
                            [
                                'title' => 'Được hưởng CĐ, CS theo quy định',
                                'keys' => ['cs_policy_k5'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Tỷ lệ GV/Lớp',
                        'keys' => ['teacher_ratio_k5'],
                    ],
                    [
                        'title' => 'Trình độ đào tạo',
                        'subsubgroups' => [
                            [
                                'title' => 'Đạt chuẩn',
                                'keys' => ['training_standard'],
                            ],
                            [
                                'title' => 'Trên chuẩn',
                                'keys' => ['training_above_standard'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Đạt chuẩn nghề nghiệp',
                        'keys' => ['standard_profession'],
                    ],
                ],
            ],
        ];

        // Điều chỉnh nhóm cột theo cấp
        if ($level === 'province') {
            $baseGroups[1]['title'] = 'Tên xã/phường';
            unset($baseGroups[2]); // Bỏ hạng trường
        } elseif ($level === 'ward') {
            // Giữ nguyên
        } else {
            // Cấp trường - ẩn tên trường và hạng trường
            unset($baseGroups[1], $baseGroups[2]);
        }

        return $baseGroups;
    }

    /**
     * Export báo cáo ra Excel theo mẫu
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

        // Lấy ID của cấp học Mầm non
        $kindergartenLevelId = $this->getKindergartenLevelId();
        if (!$kindergartenLevelId) {
            return response()->json(['error' => 'Không tìm thấy cấp học Mầm non'], 400);
        }

        // Xác định cấp độ hiển thị dựa trên user role và filter
        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        // Lấy dữ liệu báo cáo
        $reports = $this->getReportsByLevelWithFilter($level, $year, '', $geoIds, 'old', 1000, $kindergartenLevelId);
        
        // Load template Excel
        $templatePath = resource_path('excel/1.4.MN.DoiNguGV.NV.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Lấy thông tin tỉnh/thành
        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        $worksheet->setCellValue('D4', 'Năm học ' . $year);
        
        // Format font cho tỉnh và xã
        $worksheet->getStyle('C2')->getFont()->setBold(true)->setName('Tahoma')->setSize(8);
        $worksheet->getStyle('C3')->getFont()->setBold(true)->setName('Tahoma')->setSize(8);
        $worksheet->getStyle('D4')->getFont()->setName('Tahoma')->setSize(8);
        
        // Bỏ phần ký tên cho cấp trường
        // $this->addSignatureSectionForSchool($worksheet, $wardName);

        // Điền dữ liệu các trường
        $this->fillSchoolData($worksheet, $reports, $level);

        // Tạo file tạm và download
        $filename = 'bao_cao_doi_ngu_giao_vien_mam_non_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'kindergarten_report_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Thêm phần ký tên cho báo cáo trường Mầm non (dòng 14-17)
     */
    private function addSignatureSectionForSchool($worksheet, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô P14
        $dateRow = 14;
        $worksheet->setCellValue('P' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô F15, in đậm
        $signatureRow1 = 15;
        $worksheet->setCellValue('F' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('F' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô P15, in đậm
        $worksheet->setCellValue('P' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('P' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô F16, in nghiêng
        $signatureRow2 = 16;
        $worksheet->setCellValue('F' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('F' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô P16, in đậm
        $worksheet->setCellValue('P' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('P' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô P17, in nghiêng
        $signatureRow3 = 17;
        $worksheet->setCellValue('P' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('P' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'F' . $signatureRow1 . ':P' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên (dòng 14-17)
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('F' . $row . ':P' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('F' . $row . ':P' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Nhập dữ liệu từ Excel cho giáo viên Mầm non
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

            // Kiểm tra trường có phải Mầm non không
            $kindergartenLevelId = $this->getKindergartenLevelId();
            if ($school->educational_level_id != $kindergartenLevelId) {
                return response()->json(['success' => false, 'message' => 'Trường này không phải là trường Mầm non'], 400);
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
            
            // Đọc dữ liệu từ dòng 10, cột D đến U
            $row = 10;
            $data = [];
            
            // D: Tổng số
            $data['total_all'] = $worksheet->getCell('D' . $row)->getCalculatedValue() ?? 0;
            
            // E: Hợp đồng làm việc
            $data['contract_work_all'] = $worksheet->getCell('E' . $row)->getCalculatedValue() ?? 0;
            
            // F: Hợp đồng lao động
            $data['contract_labor_all'] = $worksheet->getCell('F' . $row)->getCalculatedValue() ?? 0;
            
            // G: Được hưởng CĐ, CS
            $data['cs_policy'] = $worksheet->getCell('G' . $row)->getCalculatedValue() ?? 0;
            
            // H: Tổng CBQL
            $data['total_cbql'] = $worksheet->getCell('H' . $row)->getCalculatedValue() ?? 0;
            
            // I: Phó Hiệu trưởng
            $data['vice_principal_cbql'] = $worksheet->getCell('I' . $row)->getCalculatedValue() ?? 0;
            
            // J: Tổng số giáo viên
            $data['total_teachers'] = $worksheet->getCell('J' . $row)->getCalculatedValue() ?? 0;
            
            // K: Dân tộc thiểu số
            $data['ethnic_minority'] = $worksheet->getCell('K' . $row)->getCalculatedValue() ?? 0;
            
            // L: Tỷ lệ GV/Lớp
            $data['teacher_ratio'] = $worksheet->getCell('L' . $row)->getCalculatedValue() ?? 0;
            
            // M: Tổng số nhân viên
            $data['total_staff'] = $worksheet->getCell('M' . $row)->getCalculatedValue() ?? 0;
            
            // N: Tổng số MG 5 tuổi
            $data['total_k5'] = $worksheet->getCell('N' . $row)->getCalculatedValue() ?? 0;
            
            // O: Hợp đồng làm việc MG 5 tuổi
            $data['contract_work_k5'] = $worksheet->getCell('O' . $row)->getCalculatedValue() ?? 0;
            
            // P: Hợp đồng lao động MG 5 tuổi
            $data['contract_labor_k5'] = $worksheet->getCell('P' . $row)->getCalculatedValue() ?? 0;
            
            // Q: Được hưởng CĐ, CS MG 5 tuổi
            $data['cs_policy_k5'] = $worksheet->getCell('Q' . $row)->getCalculatedValue() ?? 0;
            
            // R: Tỷ lệ GV/Lớp MG 5 tuổi
            $data['teacher_ratio_k5'] = $worksheet->getCell('R' . $row)->getCalculatedValue() ?? 0;
            
            // S: Đạt chuẩn
            $data['training_standard'] = $worksheet->getCell('S' . $row)->getCalculatedValue() ?? 0;
            
            // T: Trên chuẩn
            $data['training_above_standard'] = $worksheet->getCell('T' . $row)->getCalculatedValue() ?? 0;
            
            // U: Đạt chuẩn nghề nghiệp
            $data['standard_profession'] = $worksheet->getCell('U' . $row)->getCalculatedValue() ?? 0;
            
            // Thêm thông tin bắt buộc
            $data['school_id'] = $school->id;
            $data['ward_id'] = $school->getWardId();
            $data['academic_year'] = $academicYear;
            $data['school_name'] = $school->name;
            
            // Tìm hoặc tạo bản ghi
            $report = KindergartenReport::updateOrCreate(
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
     * Lấy tên tỉnh/thành
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
     * Điền dữ liệu các trường vào Excel
     */
    private function fillSchoolData($worksheet, $reports, $level)
    {
        // Bắt đầu từ dòng 10 (theo yêu cầu)
        $startRow = 10;
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = []; // Lưu dữ liệu để tính tổng cộng

        foreach ($reports as $report) {
            // Bỏ qua dòng tổng cộng
            if (isset($report->school_name) && $report->school_name === 'TỔNG CỘNG') {
                continue;
            }

            // TT
            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            // Tên trường/xã - merge cột B và C
            $schoolName = '';
            if ($level === 'province') {
                $schoolName = $report->ward_name ?? '';
            } else {
                $schoolName = $report->school_name ?? '';
            }
            
            // Merge cột B và C cho tên trường
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Hạng trường (chỉ hiển thị ở cấp xã) - nếu có
            if ($level === 'ward' && isset($report->school_rank)) {
                $worksheet->setCellValue('C' . $currentRow, $report->school_rank ?? 0);
            }

            // Đảm bảo font nhất quán cho tất cả các ô
            $this->setConsistentFont($worksheet, $currentRow);

            // CBQL, giáo viên, nhân viên - bắt đầu từ cột D
            $colOffset = 4; // Bắt đầu từ cột D (4)
            $worksheet->setCellValue($this->getColumnLetter($colOffset) . $currentRow, $report->total_all ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 1) . $currentRow, $report->contract_work_all ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 2) . $currentRow, $report->contract_labor_all ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 3) . $currentRow, $report->cs_policy ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 4) . $currentRow, $report->total_cbql ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 5) . $currentRow, $report->vice_principal_cbql ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 6) . $currentRow, $report->total_teachers ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 7) . $currentRow, $report->ethnic_minority ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 8) . $currentRow, $report->teacher_ratio ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 9) . $currentRow, $report->total_staff ?? 0);

            // Giáo viên dạy MG 5 tuổi
            $k5Offset = $colOffset + 10;
            $worksheet->setCellValue($this->getColumnLetter($k5Offset) . $currentRow, $report->total_k5 ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 1) . $currentRow, $report->contract_work_k5 ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 2) . $currentRow, $report->contract_labor_k5 ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 3) . $currentRow, $report->cs_policy_k5 ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 4) . $currentRow, $report->teacher_ratio_k5 ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 5) . $currentRow, $report->training_standard ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 6) . $currentRow, $report->training_above_standard ?? 0);
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 7) . $currentRow, $report->standard_profession ?? 0);

            // Format number cho các cột số (có 1000 separator)
            $this->formatNumberColumns($worksheet, $currentRow, $colOffset, $k5Offset);
            
            // Format tỷ lệ (có 1000 separator và 2 decimal places)
            $this->formatRatioColumns($worksheet, $currentRow, $colOffset, $k5Offset);

            // Lưu dữ liệu để tính tổng cộng
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
        // TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        // Tên - merge cột B và C
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':C' . $row);
        
        // Đảm bảo font nhất quán cho dòng tổng cộng
        $this->setConsistentFont($worksheet, $row);
        
        // In đậm dòng tổng cộng
        $range = 'A' . $row . ':U' . $row;
        $worksheet->getStyle($range)->getFont()->setBold(true);

        // Tính tổng cộng cho các trường số
        $totals = $this->calculateTotals(collect($dataRows));

        // CBQL, giáo viên, nhân viên - bắt đầu từ cột D
        $colOffset = 4;
        $worksheet->setCellValue($this->getColumnLetter($colOffset) . $row, $totals->total_all ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 1) . $row, $totals->contract_work_all ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 2) . $row, $totals->contract_labor_all ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 3) . $row, $totals->cs_policy ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 4) . $row, $totals->total_cbql ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 5) . $row, $totals->vice_principal_cbql ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 6) . $row, $totals->total_teachers ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 7) . $row, $totals->ethnic_minority ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 8) . $row, $totals->teacher_ratio ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 9) . $row, $totals->total_staff ?? 0);

        // Giáo viên dạy MG 5 tuổi
        $k5Offset = $colOffset + 10;
        $worksheet->setCellValue($this->getColumnLetter($k5Offset) . $row, $totals->total_k5 ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 1) . $row, $totals->contract_work_k5 ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 2) . $row, $totals->contract_labor_k5 ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 3) . $row, $totals->cs_policy_k5 ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 4) . $row, $totals->teacher_ratio_k5 ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 5) . $row, $totals->training_standard ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 6) . $row, $totals->training_above_standard ?? 0);
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 7) . $row, $totals->standard_profession ?? 0);

        // Format number cho các cột số (có 1000 separator)
        $this->formatNumberColumns($worksheet, $row, $colOffset, $k5Offset);
        
        // Format tỷ lệ (có 1000 separator và 2 decimal places)
        $this->formatRatioColumns($worksheet, $row, $colOffset, $k5Offset);
    }

    /**
     * Format number cho các cột số (có 1000 separator)
     */
    private function formatNumberColumns($worksheet, $row, $colOffset, $k5Offset)
    {
        // Các cột số (không phải tỷ lệ)
        $numberColumns = [
            $colOffset, $colOffset + 1, $colOffset + 2, $colOffset + 3, $colOffset + 4, $colOffset + 5,
            $colOffset + 6, $colOffset + 7, $colOffset + 9, // Bỏ qua cột tỷ lệ
            $k5Offset, $k5Offset + 1, $k5Offset + 2, $k5Offset + 3, $k5Offset + 5, $k5Offset + 6, $k5Offset + 7
        ];
        
        foreach ($numberColumns as $col) {
            $cell = $this->getColumnLetter($col) . $row;
            $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
        }
    }

    /**
     * Format tỷ lệ (có 1000 separator và 2 decimal places)
     */
    private function formatRatioColumns($worksheet, $row, $colOffset, $k5Offset)
    {
        // Các cột tỷ lệ
        $ratioColumns = [
            $colOffset + 8, // teacher_ratio
            $k5Offset + 4   // teacher_ratio_k5
        ];
        
        foreach ($ratioColumns as $col) {
            $cell = $this->getColumnLetter($col) . $row;
            $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    /**
     * Chuyển đổi số cột thành chữ cái Excel
     */
    private function getColumnLetter($columnNumber)
    {
        $columnLetter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $columnLetter = chr(65 + ($columnNumber % 26)) . $columnLetter;
            $columnNumber = intval($columnNumber / 26);
        }
        return $columnLetter;
    }

    /**
     * Đảm bảo font và căn lề nhất quán cho tất cả các ô trong dòng
     */
    private function setConsistentFont($worksheet, $row)
    {
        // Đặt font mặc định cho toàn bộ dòng (A đến T)
        $range = 'A' . $row . ':T' . $row;
        $style = $worksheet->getStyle($range);
        
        // Set font Tahoma size 8
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Tên trường - căn trái
            'C' => 'left',      // Tên trường (merged) - căn trái
            'D' => 'center',    // Số liệu - căn giữa
            'E' => 'center',
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
            'U' => 'center'     // Cột đạt chuẩn nghề nghiệp - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings
            $cellStyle->getFont()->setBold(false);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Tahoma');
            $cellStyle->getFont()->setSize(8);
            
            // Thêm border cho từng ô
            $cellStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            
            // Alignment settings
            if ($alignment === 'left') {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Number format cho các cột số
            if (in_array($col, ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'])) {
                $cellStyle->getNumberFormat()->setFormatCode('0');
            }
        }
    }

    /**
     * Xuất Excel cho Thống kê báo cáo (theo xã)
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

        // Lấy ward_code từ ward_id
        $ward = \App\Models\GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return response()->json(['error' => 'Không tìm thấy xã/phường'], 400);
        }
        
        // Lấy tất cả trường Mầm non trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schools = \App\Models\School::active()
            ->where('ward_code_new', $ward->code)
            ->where('educational_level_id', $kindergartenLevelId)
            ->orderBy('name')
            ->get();
        
        // Lấy báo cáo hiện có cho các trường này
        $existingReports = KindergartenReport::where('academic_year', $year)
            ->whereIn('school_name', $schools->pluck('name'))
            ->get()
            ->keyBy('school_name');
        
        // Tạo danh sách kết hợp: trường + báo cáo (nếu có)
        $combinedData = $schools->map(function($school) use ($existingReports, $year) {
            $report = $existingReports->get($school->name);
            
            if ($report) {
                // Có báo cáo, sử dụng dữ liệu từ báo cáo
                return $this->createReportObjectFromData($report, $school, true);
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return $this->createEmptyReportObjectFromSchool($school, $year);
            }
        });
        
        $templatePath = resource_path('excel/1.4.MN.DoiNguGV.NV.xlsx');
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
        
        // Ghi năm học vào ô D4
        $worksheet->setCellValue('D4', 'Năm học ' . $year);
        
        // Ghi ngày ký vào ô P14 với tên xã thực tế
        $worksheet->setCellValue('P14', $wardName . ', ngày ... tháng ... năm ...');
        
        // Format cho các ô được ghi dữ liệu mới
        $cellsToFormat = ['C2', 'C3', 'D4', 'P14'];
        foreach ($cellsToFormat as $cell) {
            $worksheet->getStyle($cell)->getFont()->setName('Tahoma');
            $worksheet->getStyle($cell)->getFont()->setSize(8);
            if ($cell === 'C2' || $cell === 'C3') {
                $worksheet->getStyle($cell)->getFont()->setBold(true);
            }
        }

        // Reset format cho vùng ký tên
        $this->resetSignatureAreaFormat($worksheet);

        // Cài đặt rows to repeat at top cho tiêu đề (dòng 6-9)
        $this->setupPrintTitles($worksheet);

        // Điền dữ liệu các trường
        $this->fillSchoolDataForWard($worksheet, $combinedData, $wardName);

        // Tạo file tạm và download
        $filename = 'bao_cao_doi_ngu_giao_vien_mam_non_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'kindergarten_report_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Tạo object báo cáo từ dữ liệu có sẵn (cho exportExcelForWard)
     */
    protected function createReportObjectFromData($report, $school, $hasData = true)
    {
        $object = (object) [
            'id' => $report->id,
            'school_id' => $school->id,
            'school' => $school,
            'ward' => null,
            'academic_year' => $report->academic_year,
            'school_name' => $school->name,
            'school_code' => $school->code,
            'school_rank' => is_numeric($school->rank) ? (int) $school->rank : 0,
            'has_data' => $hasData
        ];

        // Thêm tất cả các trường báo cáo
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = $report->$field ?? null; // Để null thay vì 0
        }

        return $object;
    }

    /**
     * Tạo object báo cáo rỗng cho trường (cho exportExcelForWard)
     */
    protected function createEmptyReportObjectFromSchool($school, $year)
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
            $object->$field = null; // Để rỗng thay vì 0
        }

        return $object;
    }

    /**
     * Điền dữ liệu các trường vào Excel (cho exportExcelForWard)
     */
    private function fillSchoolDataForWard($worksheet, $schools, $wardName)
    {
        // Bắt đầu từ dòng 10 (theo yêu cầu)
        $startRow = 10;
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = []; // Lưu dữ liệu để tính tổng cộng

        foreach ($schools as $school) {
            // TT
            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            // Tên trường - merge cột B và C
            $schoolName = $school->school_name ?? '';
            
            // Merge cột B và C cho tên trường
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Hạng trường
            $worksheet->setCellValue('C' . $currentRow, $school->school_rank ?? 0);

            // Đảm bảo font nhất quán cho tất cả các ô
            $this->setConsistentFont($worksheet, $currentRow);

            // CBQL, giáo viên, nhân viên - bắt đầu từ cột D
            $colOffset = 4; // Bắt đầu từ cột D (4)
            $worksheet->setCellValue($this->getColumnLetter($colOffset) . $currentRow, $school->total_all ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 1) . $currentRow, $school->contract_work_all ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 2) . $currentRow, $school->contract_labor_all ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 3) . $currentRow, $school->cs_policy ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 4) . $currentRow, $school->total_cbql ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 5) . $currentRow, $school->vice_principal_cbql ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 6) . $currentRow, $school->total_teachers ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 7) . $currentRow, $school->ethnic_minority ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 8) . $currentRow, $school->teacher_ratio ?: '');
            $worksheet->setCellValue($this->getColumnLetter($colOffset + 9) . $currentRow, $school->total_staff ?: '');

            // Giáo viên dạy MG 5 tuổi
            $k5Offset = $colOffset + 10;
            $worksheet->setCellValue($this->getColumnLetter($k5Offset) . $currentRow, $school->total_k5 ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 1) . $currentRow, $school->contract_work_k5 ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 2) . $currentRow, $school->contract_labor_k5 ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 3) . $currentRow, $school->cs_policy_k5 ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 4) . $currentRow, $school->teacher_ratio_k5 ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 5) . $currentRow, $school->training_standard ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 6) . $currentRow, $school->training_above_standard ?: '');
            $worksheet->setCellValue($this->getColumnLetter($k5Offset + 7) . $currentRow, $school->standard_profession ?: '');

            // Format number cho các cột số (có 1000 separator)
            $this->formatNumberColumns($worksheet, $currentRow, $colOffset, $k5Offset);
            
            // Format tỷ lệ (có 1000 separator và 2 decimal places)
            $this->formatRatioColumns($worksheet, $currentRow, $colOffset, $k5Offset);

            // Lưu dữ liệu để tính tổng cộng
            $dataRows[] = $school;
            $currentRow++;
        }

        // Thêm dòng tổng cộng nếu có dữ liệu
        if (!empty($dataRows)) {
            $totalRow = $this->addTotalRowForWard($worksheet, $currentRow, $dataRows);
            
            // Thêm phần ký tên vào cuối báo cáo
            $this->addSignatureSection($worksheet, $totalRow, $wardName);
        }
    }

    /**
     * Thêm dòng tổng cộng (cho exportExcelForWard)
     */
    private function addTotalRowForWard($worksheet, $row, $dataRows)
    {
        // TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        // Tên - merge cột B và C
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':C' . $row);
        
        // Đảm bảo font nhất quán cho dòng tổng cộng
        $this->setConsistentFont($worksheet, $row);
        
        // In đậm dòng tổng cộng
        $range = 'A' . $row . ':U' . $row;
        $worksheet->getStyle($range)->getFont()->setBold(true);

        // Tính tổng cộng cho các trường số
        $totals = $this->calculateTotalsFromObjects(collect($dataRows));

        // CBQL, giáo viên, nhân viên - bắt đầu từ cột D
        $colOffset = 4;
        $worksheet->setCellValue($this->getColumnLetter($colOffset) . $row, $totals->total_all ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 1) . $row, $totals->contract_work_all ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 2) . $row, $totals->contract_labor_all ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 3) . $row, $totals->cs_policy ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 4) . $row, $totals->total_cbql ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 5) . $row, $totals->vice_principal_cbql ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 6) . $row, $totals->total_teachers ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 7) . $row, $totals->ethnic_minority ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 8) . $row, $totals->teacher_ratio ?: '');
        $worksheet->setCellValue($this->getColumnLetter($colOffset + 9) . $row, $totals->total_staff ?: '');

        // Giáo viên dạy MG 5 tuổi
        $k5Offset = $colOffset + 10;
        $worksheet->setCellValue($this->getColumnLetter($k5Offset) . $row, $totals->total_k5 ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 1) . $row, $totals->contract_work_k5 ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 2) . $row, $totals->contract_labor_k5 ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 3) . $row, $totals->cs_policy_k5 ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 4) . $row, $totals->teacher_ratio_k5 ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 5) . $row, $totals->training_standard ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 6) . $row, $totals->training_above_standard ?: '');
        $worksheet->setCellValue($this->getColumnLetter($k5Offset + 7) . $row, $totals->standard_profession ?: '');

        // Format number cho các cột số (có 1000 separator)
        $this->formatNumberColumns($worksheet, $row, $colOffset, $k5Offset);
        
        // Format tỷ lệ (có 1000 separator và 2 decimal places)
        $this->formatRatioColumns($worksheet, $row, $colOffset, $k5Offset);
        
        return $row; // Trả về số dòng của dòng tổng cộng
    }

    /**
     * Tính tổng cộng từ objects (cho exportExcelForWard)
     */
    protected function calculateTotalsFromObjects($objects)
    {
        $total = new \stdClass();
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = 0;
        
        // Tính tổng cho tất cả các trường số
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if (in_array($field, ['teacher_ratio', 'teacher_ratio_k5'])) {
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

    /**
     * Reset format cho vùng ký tên
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
        $style->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
        
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
        // Tên xã, ngày ... tháng ... năm ... vào ô P(x+2)
        $dateRow = $totalRow + 2;
        $worksheet->setCellValue('P' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô F(x+3), in đậm
        $signatureRow1 = $totalRow + 3;
        $worksheet->setCellValue('F' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('F' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô P(x+3), in đậm
        $worksheet->setCellValue('P' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('P' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô F(x+4), in nghiêng
        $signatureRow2 = $totalRow + 4;
        $worksheet->setCellValue('F' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('F' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô P(x+4), in đậm
        $worksheet->setCellValue('P' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('P' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô P(x+5), in nghiêng
        $signatureRow3 = $totalRow + 5;
        $worksheet->setCellValue('P' . $signatureRow3, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('P' . $signatureRow3)->getFont()->setItalic(true);
        
        // Format font và alignment cho tất cả các ô ký tên
        $signatureCells = [
            'P' . $dateRow, 'F' . $signatureRow1, 'P' . $signatureRow1,
            'F' . $signatureRow2, 'P' . $signatureRow2, 'P' . $signatureRow3
        ];
        
        foreach ($signatureCells as $cell) {
            $style = $worksheet->getStyle($cell);
            $style->getFont()->setName('Tahoma');
            $style->getFont()->setSize(8);
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Lưu dữ liệu cấp trường
     */
    public function saveSchoolData(Request $request)
    {
        try {
            // Validation rules cho KindergartenReport
            $rules = [
                'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
                'school_id' => 'required|integer|exists:schools,id',
                'ward_id' => 'required|integer|exists:geo_units,id',
                'school_name' => 'nullable|string',
                // Tất cả các trường báo cáo đều nullable
                'total_all' => 'nullable|numeric|min:0',
                'contract_work_all' => 'nullable|numeric|min:0',
                'contract_labor_all' => 'nullable|numeric|min:0',
                'cs_policy' => 'nullable|numeric|min:0',
                'total_cbql' => 'nullable|numeric|min:0',
                'vice_principal_cbql' => 'nullable|numeric|min:0',
                'total_teachers' => 'nullable|numeric|min:0',
                'ethnic_minority' => 'nullable|numeric|min:0',
                'teacher_ratio' => 'nullable|numeric|min:0',
                'total_staff' => 'nullable|numeric|min:0',
                'total_k5' => 'nullable|numeric|min:0',
                'contract_work_k5' => 'nullable|numeric|min:0',
                'contract_labor_k5' => 'nullable|numeric|min:0',
                'cs_policy_k5' => 'nullable|numeric|min:0',
                'teacher_ratio_k5' => 'nullable|numeric|min:0',
                'training_standard' => 'nullable|numeric|min:0',
                'training_above_standard' => 'nullable|numeric|min:0',
                'standard_profession' => 'nullable|numeric|min:0',
            ];

            $validated = $request->validate($rules);

            // Kiểm tra school_id và ward_id có hợp lệ không
            if ($request->school_id <= 0 || $request->ward_id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thông tin trường học hoặc xã/phường không hợp lệ'
                ], 400);
            }

            // Lấy thông tin trường học trước
            $school = \App\Models\School::find($request->school_id);
            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy trường học'
                ], 404);
            }

            // Lấy thông tin xã/phường
            $ward = \App\Models\GeoUnit::find($request->ward_id);
            if (!$ward) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy xã/phường'
                ], 404);
            }

            // Tìm hoặc tạo báo cáo
            $report = $this->modelClass::where('academic_year', $request->academic_year)
                ->where('school_id', $request->school_id)
                ->where('ward_id', $request->ward_id)
                ->first();

            if (!$report) {
                // Tạo báo cáo mới
                $report = new $this->modelClass();
                $report->academic_year = $request->academic_year;
                $report->school_id = $request->school_id;
                $report->ward_id = $request->ward_id;
                $report->school_name = $school->name;
            }

            // Cập nhật dữ liệu
            $report->total_all = $validated['total_all'];
            $report->contract_work_all = $validated['contract_work_all'];
            $report->contract_labor_all = $validated['contract_labor_all'];
            $report->cs_policy = $validated['cs_policy'];
            $report->total_cbql = $validated['total_cbql'];
            $report->vice_principal_cbql = $validated['vice_principal_cbql'];
            $report->total_teachers = $validated['total_teachers'];
            $report->ethnic_minority = $validated['ethnic_minority'];
            $report->teacher_ratio = $validated['teacher_ratio'];
            $report->total_staff = $validated['total_staff'];
            $report->total_k5 = $validated['total_k5'];
            $report->contract_work_k5 = $validated['contract_work_k5'];
            $report->contract_labor_k5 = $validated['contract_labor_k5'];
            $report->cs_policy_k5 = $validated['cs_policy_k5'];
            $report->teacher_ratio_k5 = $validated['teacher_ratio_k5'];
            $report->training_standard = $validated['training_standard'];
            $report->training_above_standard = $validated['training_above_standard'];
            $report->standard_profession = $validated['standard_profession'];

            $report->save();

            return response()->json([
                'success' => true,
                'message' => 'Lưu dữ liệu thành công!',
                'data' => $report
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
                'debug' => $request->all()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
