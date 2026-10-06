<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\LevelTransferService;
use App\Models\School;
use App\Models\GeoUnit;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\PersonEducation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\LevelTransferLock;

class LevelTransferController extends Controller
{
    protected $levelTransferService;

    public function __construct(LevelTransferService $levelTransferService)
    {
        $this->levelTransferService = $levelTransferService;
    }

    /**
     * Hiển thị trang thực hiện lên lớp
     */
    public function index()
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            abort(403, 'Bạn không có quyền thực hiện lên lớp.');
        }

        // Lấy danh sách năm học có sẵn trong hệ thống từ person_education
        $availableAcademicYears = PersonEducation::whereNotNull('academic_year')
            ->where('academic_year', '!=', '')
            ->distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year')
            ->toArray();

        // Nếu không có năm học nào, tạo danh sách từ năm 2000 đến hiện tại + 1
        if (empty($availableAcademicYears)) {
            $currentYear = (int) date('Y');
            $availableAcademicYears = [];
            for ($year = 2000; $year <= $currentYear + 1; $year++) {
                $availableAcademicYears[] = $year . '-' . ($year + 1);
            }
        }

        // Lấy năm học hiện tại (mặc định)
        $currentAcademicYear = SurveyForm::currentAcademicYear();

        // Geo-filter sẽ tự động load provinces
        return view('front.level-transfer.index', compact('availableAcademicYears', 'currentAcademicYear'));
    }

    /**
     * Thực hiện lên lớp (bao gồm cả lên cấp)
     */
    public function execute(Request $request)
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền thực hiện lên lớp.'
            ], 403);
        }

        $request->validate([
            'academic_year_from' => 'required|string',
            'academic_year_to' => 'required|string',
            'province_id' => 'required|integer|exists:geo_units,id',
            'district_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'village_id' => 'nullable|integer|exists:geo_units,id',
            'keep_class_name' => 'boolean',
            'keep_transfer_objects' => 'boolean',
            // Thêm các trường cho việc chọn trường cấp học tiếp theo
            'next_school_mapping' => 'nullable|array',
            'next_school_mapping.*.level' => 'required|string|in:preschool,primary,junior,high',
            'next_school_mapping.*.school_id' => 'required|integer|exists:schools,id',
        ]);

        try {
            // Kiểm tra khóa theo phạm vi và năm học
            $locked = $this->isLockedForScope(
                $request->input('academic_year_to'),
                $request->input('province_id'),
                $request->input('district_id'),
                $request->input('ward_id'),
                $request->input('school_id')
            );
            if ($locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chức năng lên lớp đã bị khóa cho phạm vi/năm học này.'
                ], 423);
            }
            DB::beginTransaction();

            $result = $this->levelTransferService->executeLevelTransfer($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thực hiện lên lớp và lên cấp thành công',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Level transfer error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    private function isLockedForScope(string $academicYear, ?int $provinceId, ?int $districtId, ?int $wardId, ?int $schoolId = null): bool
    {
        // Chỉ kiểm tra khóa bình thường (normal) cho thực hiện lên lớp
        // Ưu tiên khóa theo phạm vi hẹp nhất → rộng hơn (chỉ có tỉnh, xã, trường)
        if ($schoolId && LevelTransferLock::where([
            'scope_type' => 'school', 
            'scope_id' => $schoolId, 
            'academic_year' => $academicYear,
            'lock_type' => 'normal'
        ])->exists()) {
            return true;
        }
        if ($wardId && LevelTransferLock::where([
            'scope_type' => 'ward', 
            'scope_id' => $wardId, 
            'academic_year' => $academicYear,
            'lock_type' => 'normal'
        ])->exists()) {
            return true;
        }
        if ($provinceId && LevelTransferLock::where([
            'scope_type' => 'province', 
            'scope_id' => $provinceId, 
            'academic_year' => $academicYear,
            'lock_type' => 'normal'
        ])->exists()) {
            return true;
        }
        return false;
    }

    public function lock(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập.'], 401);
        }
        
        $data = $request->validate([
            'academic_year' => 'required|string',
            'scope_type' => 'required|string|in:province,ward,school',
            'scope_id' => 'required|integer',
            'lock_type' => 'required|string|in:normal,trai_tuyen'
        ]);

        // Kiểm tra quyền địa giới của user
        if (!$this->hasGeoPermission($user, $data['scope_type'], $data['scope_id'])) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền khóa ở địa bàn này.'], 403);
        }

        // Kiểm tra xem đã có khóa nào cho phạm vi này chưa (cùng loại khóa)
        $existingLock = LevelTransferLock::where([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => $data['lock_type'],
        ])->first();

        if ($existingLock) {
            return response()->json(['success' => false, 'message' => 'Địa bàn này đã bị khóa bởi người khác.'], 400);
        }

        $lock = LevelTransferLock::create([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => $data['lock_type'],
            'locked_by' => $user->id,
            'locked_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $lock]);
    }

    public function unlock(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập.'], 401);
        }
        $data = $request->validate([
            'academic_year' => 'required|string',
            'scope_type' => 'required|string|in:province,ward,school',
            'scope_id' => 'required|integer',
            'lock_type' => 'required|string|in:normal,trai_tuyen'
        ]);

        $lock = LevelTransferLock::where([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => $data['lock_type'],
        ])->first();

        if (!$lock) {
            return response()->json(['success' => true]);
        }
        if ($lock->locked_by !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Chỉ người đã khóa mới mở khóa được.'], 403);
        }

        $lock->delete();
        return response()->json(['success' => true]);
    }

    public function lockStatus(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập.'], 401);
        }
        
        $data = $request->validate([
            'academic_year' => 'required|string',
            'scope_type' => 'required|string|in:province,ward,school',
            'scope_id' => 'required|integer',
            'lock_type' => 'required|string|in:normal,trai_tuyen'
        ]);

        $lock = LevelTransferLock::where([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => $data['lock_type'],
        ])->first();

        return response()->json(['locked' => (bool)$lock, 'locked_by' => $lock?->locked_by, 'locked_at' => $lock?->locked_at]);
    }

    /**
     * Lấy danh sách trường theo cấp học và địa bàn
     */
    public function getSchoolsByLevel(Request $request)
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'province_id' => 'required|integer|exists:geo_units,id',
            'district_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'scheme' => 'required|in:old,new',
            'level' => 'required|in:preschool,primary,junior,high',
        ]);

        $provinceId = $request->province_id;
        $districtId = $request->district_id;
        $wardId = $request->ward_id;
        $scheme = $request->scheme;
        $level = $request->level;

        // Lấy thông tin địa bàn
        $province = GeoUnit::find($provinceId);
        $district = $districtId ? GeoUnit::find($districtId) : null;
        $ward = $wardId ? GeoUnit::find($wardId) : null;

        if (!$province) {
            return response()->json([]);
        }

        // Xây dựng query
        $query = School::active();

        // Mapping level names to educational level IDs - bao gồm tất cả các loại trường liên cấp
        $levelMappings = [
            'preschool' => ['Mầm non'],
            'primary' => ['Tiểu học + THCS', 'Tiểu học + THCS + THPT', 'Tiểu học'],
            'junior' => ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS'],
            'high' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']
        ];

        $possibleNames = $levelMappings[$level] ?? [$level];

        // Lọc theo cấp học - thử tất cả các tên có thể
        $query->whereHas('educationalLevel', function($q) use ($possibleNames) {
            $q->whereIn('name', $possibleNames);
        });

        // Lọc theo địa bàn
        if ($ward && $scheme === 'old') {
            $query->where('ward_code', $ward->code);
        } elseif ($district && $scheme === 'old') {
            $query->where('district_code', $district->code);
        } elseif ($province && $scheme === 'old') {
            $query->where('province_code', $province->code);
        } elseif ($ward && $scheme === 'new') {
            $query->where('ward_code_new', $ward->code);
        } elseif ($province && $scheme === 'new') {
            $query->where('province_code_new', $province->code);
        }

        $schools = $query->orderBy('name')->get(['id', 'code', 'name']);

        return response()->json($schools);
    }

    /**
     * Lấy danh sách trường cho cấp học tiếp theo (dùng cho lên cấp)
     */
    public function getNextLevelSchools(Request $request)
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'province_id' => 'nullable|integer|exists:geo_units,id',
            'district_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'scheme' => 'nullable|string|in:old,new',
            'scope' => 'nullable|string|in:province,district,ward,geo',
            'level' => 'required|string|in:preschool,primary,junior,high',
        ]);

        $provinceId = $request->input('province_id');
        $districtId = $request->input('district_id');
        $wardId = $request->input('ward_id');
        $scheme = $request->input('scheme', 'new');
        $scope = $request->input('scope', 'geo');
        $level = $request->input('level');

        // Mapping cấp học hiện tại sang cấp học tiếp theo
        $nextLevelMapping = [
            'preschool' => 'primary',  // Mầm non → Tiểu học
            'primary' => 'junior',     // Tiểu học → THCS
            'junior' => 'high',        // THCS → THPT
            'high' => null             // THPT → Tốt nghiệp (không có cấp tiếp theo)
        ];

        $nextLevel = $nextLevelMapping[$level] ?? null;
        if (!$nextLevel) {
            return response()->json([]); // Không có cấp tiếp theo
        }

        // Mapping level names to educational level IDs - bao gồm tất cả các loại trường liên cấp
        $levelMappings = [
            'primary' => ['Tiểu học + THCS', 'Tiểu học + THCS + THPT', 'Tiểu học'],
            'junior' => ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS'],
            'high' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']
        ];

        $possibleNames = $levelMappings[$nextLevel] ?? [];
        if (empty($possibleNames)) {
            return response()->json(['error' => 'Cấp học không hợp lệ'], 400);
        }

        // Query cơ bản: lọc theo cấp học và không bị giải thể
        $query = School::where('is_dissolved', false);

        // Lọc theo cấp học - thử tất cả các tên có thể
        $query->whereHas('educationalLevel', function($q) use ($possibleNames) {
            $q->whereIn('name', $possibleNames);
        });

        // === LỌC THEO ĐỊA GIỚI ===
        $matchNewWards = [];
        $matchOldWards = [];
        $matchOldDistricts = [];

        $geo = app(\App\Services\GeoService::class);

        // Xác định phạm vi lọc dựa trên scope
        if ($scope === 'geo') {
            // Scope 'geo': lấy theo thông tin địa giới đã chọn
            if ($wardId) {
                $scope = 'ward';
            } elseif ($districtId) {
                $scope = 'district';
            } elseif ($provinceId) {
                $scope = 'province';
            }
        }

        if ($scheme === 'new') {
            // Hành chính mới: lấy mã ward_code_new
            if ($scope === 'ward' && $wardId) {
                // Chọn xã/phường cụ thể
                $ward = GeoUnit::find($wardId);
                if ($ward) {
                    $matchNewWards[] = $ward->code;
                }
            } elseif ($scope === 'district' && $districtId) {
                // Chọn huyện/quận => lấy tất cả xã/phường trong huyện
                $district = GeoUnit::find($districtId);
                if ($district) {
                    $matchNewWards = GeoUnit::where('parent_id', $district->id)
                        ->where('type_level', 3)
                        ->where('scheme', 'new')
                        ->pluck('code')
                        ->toArray();
                }
            } elseif ($scope === 'province' && $provinceId) {
                // Chọn tỉnh/thành phố => lấy tất cả xã/phường trong tỉnh
                $province = GeoUnit::find($provinceId);
                if ($province) {
                    $matchNewWards = $geo->newWardCodesByProvince($province->code);
                }
            }
        } else {
            // Hành chính cũ: lấy mã ward_code và district_code
            if ($scope === 'ward' && $wardId) {
                // Chọn xã/phường cụ thể
                $ward = GeoUnit::find($wardId);
                if ($ward) {
                    $matchOldWards[] = $ward->code;
                }
            } elseif ($scope === 'district' && $districtId) {
                // Chọn huyện/quận => lấy tất cả xã/phường trong huyện
                $district = GeoUnit::find($districtId);
                if ($district) {
                    $matchOldDistricts[] = $district->code;
                    $matchOldWards = $geo->oldWardCodesByDistrict($district->code);
                }
            } elseif ($scope === 'province' && $provinceId) {
                // Chọn tỉnh/thành phố => lấy tất cả huyện và xã trong tỉnh
                $province = GeoUnit::find($provinceId);
                if ($province) {
                    [$dCodes, $wCodes] = $geo->oldCodesByProvince($province->code);
                    $matchOldDistricts = $dCodes;
                    $matchOldWards = $wCodes;
                }
            }
        }

        // Áp dụng điều kiện lọc địa giới
        if ($matchNewWards || $matchOldWards || $matchOldDistricts) {
            $query->where(function($w) use ($matchNewWards, $matchOldWards, $matchOldDistricts) {
                if ($matchNewWards) {
                    $w->orWhereIn('ward_code_new', $matchNewWards);
                }
                if ($matchOldWards) {
                    $w->orWhereIn('ward_code', $matchOldWards);
                }
                if ($matchOldDistricts) {
                    $w->orWhereIn('district_code', $matchOldDistricts);
                }
            });
        }

        $schools = $query->orderBy('name')->get(['id', 'code', 'name']);

        return response()->json($schools);
    }

    /**
     * Lấy danh sách trường theo cấp học và địa bàn
     */
    public function getSchools(Request $request, $level)
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'province_id' => 'nullable|integer|exists:geo_units,id',
            'district_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'scheme' => 'nullable|string|in:old,new',
            'scope' => 'nullable|string|in:province,district,ward,geo',
            'type' => 'nullable|string|in:old,new',
        ]);

        $provinceId = $request->input('province_id');
        $districtId = $request->input('district_id');
        $wardId = $request->input('ward_id');
        $scheme = $request->input('scheme', 'new'); // Mặc định dùng scheme mới
        $scope = $request->input('scope', 'geo'); // Mặc định dùng geo scope
        $type = $request->input('type', 'new'); // Mặc định là trường mới

        // Mapping level names to educational level IDs - bao gồm tất cả các loại trường liên cấp
        $levelMappings = [
            'preschool' => ['Mầm non'],
            'primary' => ['Tiểu học + THCS', 'Tiểu học + THCS + THPT', 'Tiểu học'],
            'junior' => ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS'],
            'high' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']
        ];

        if ($type === 'old') {
            // Trường cũ: giữ nguyên cấp học hiện tại
            $possibleNames = $levelMappings[$level] ?? [];
        } else {
            // Trường mới: chuyển lên cấp học tiếp theo
            $newLevelMap = [
                'preschool' => 'primary'  // Mầm non → Tiểu học
            ];
            $newLevel = $newLevelMap[$level] ?? $level;
            $possibleNames = $levelMappings[$newLevel] ?? [];
        }

        if (empty($possibleNames)) {
            return response()->json(['error' => 'Cấp học không hợp lệ'], 400);
        }

        // Query cơ bản: lọc theo cấp học và không bị giải thể
        $query = School::where('is_dissolved', false);

        // Lọc theo cấp học - thử tất cả các tên có thể
        $query->whereHas('educationalLevel', function($q) use ($possibleNames) {
            $q->whereIn('name', $possibleNames);
        });

        // === LỌC THEO ĐỊA GIỚI ===
        $matchNewWards = [];
        $matchOldWards = [];
        $matchOldDistricts = [];

        $geo = app(\App\Services\GeoService::class);

        // Xác định phạm vi lọc dựa trên scope
        if ($scope === 'geo') {
            // Scope 'geo': lấy theo thông tin địa giới đã chọn
            if ($wardId) {
                $scope = 'ward';
            } elseif ($districtId) {
                $scope = 'district';
            } elseif ($provinceId) {
                $scope = 'province';
            }
        }

        if ($scheme === 'new') {
            // Hành chính mới: lấy mã ward_code_new
            if ($scope === 'ward' && $wardId) {
                // Chọn xã/phường cụ thể
                $ward = GeoUnit::find($wardId);
                if ($ward) {
                    $matchNewWards[] = $ward->code;
                }
            } elseif ($scope === 'district' && $districtId) {
                // Chọn huyện/quận => lấy tất cả xã/phường trong huyện
                $district = GeoUnit::find($districtId);
                if ($district) {
                    $matchNewWards = GeoUnit::where('parent_id', $district->id)
                        ->where('type_level', 3)
                        ->where('scheme', 'new')
                        ->pluck('code')
                        ->toArray();
                }
            } elseif ($scope === 'province' && $provinceId) {
                // Chọn tỉnh/thành phố => lấy tất cả xã/phường trong tỉnh
                $province = GeoUnit::find($provinceId);
                if ($province) {
                    $matchNewWards = $geo->newWardCodesByProvince($province->code);
                }
            }
        } else {
            // Hành chính cũ: lấy mã ward_code và district_code
            if ($scope === 'ward' && $wardId) {
                // Chọn xã/phường cụ thể
                $ward = GeoUnit::find($wardId);
                if ($ward) {
                    $matchOldWards[] = $ward->code;
                }
            } elseif ($scope === 'district' && $districtId) {
                // Chọn huyện/quận => lấy tất cả xã/phường trong huyện
                $district = GeoUnit::find($districtId);
                if ($district) {
                    $matchOldDistricts[] = $district->code;
                    $matchOldWards = $geo->oldWardCodesByDistrict($district->code);
                }
            } elseif ($scope === 'province' && $provinceId) {
                // Chọn tỉnh/thành phố => lấy tất cả huyện và xã trong tỉnh
                $province = GeoUnit::find($provinceId);
                if ($province) {
                    [$dCodes, $wCodes] = $geo->oldCodesByProvince($province->code);
                    $matchOldDistricts = $dCodes;
                    $matchOldWards = $wCodes;
                }
            }
        }

        // Áp dụng điều kiện lọc địa giới
        if ($matchNewWards || $matchOldWards || $matchOldDistricts) {
            $query->where(function($w) use ($matchNewWards, $matchOldWards, $matchOldDistricts) {
                if ($matchNewWards) {
                    $w->orWhereIn('ward_code_new', $matchNewWards);
                }
                if ($matchOldWards) {
                    $w->orWhereIn('ward_code', $matchOldWards);
                }
                if ($matchOldDistricts) {
                    $w->orWhereIn('district_code', $matchOldDistricts);
                }
            });
        }

        $schools = $query->orderBy('name')->get(['id', 'code', 'name']);


        return response()->json($schools);
    }

    /**
     * Lấy danh sách địa bàn con
     */
    public function getGeoUnits(Request $request)
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'parent_id' => 'required|integer|exists:geo_units,id',
            'type_level' => 'required|integer|in:2,3,4',
            'scheme' => 'nullable|string|in:old,new'
        ]);

        $scheme = $request->input('scheme', 'old');
        
        $units = GeoUnit::where('parent_id', $request->input('parent_id'))
            ->where('type_level', $request->input('type_level'))
            ->where('scheme', $scheme)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($units);
    }

    /**
     * Lấy danh sách học sinh ở khối tốt nghiệp
     */
    public function getGraduationStudents(Request $request)
    {
        // Kiểm tra quyền thực hiện lên lớp
        $user = Auth::user();
        if (!$user || !$user->hasPermission('thuc_hien_len_lop', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'school_id' => 'required',
            'block_name' => 'required|string',
            'all_schools' => 'boolean',
            'province_id' => 'nullable|integer',
            'district_id' => 'nullable|integer',
            'ward_id' => 'nullable|integer',
            'scheme' => 'nullable|string|in:old,new',
            'level' => 'nullable|string',
        ]);

        $schoolId = $request->school_id;
        $blockName = $request->block_name;
        $allSchools = $request->all_schools ?? false;

        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('education.block', function ($q) use ($blockName) {
                $q->where('name', $blockName);
            })
            ->with(['person', 'education.block', 'education.school']);

        if ($allSchools) {
            // Lấy học sinh từ tất cả trường trong cấp học
            $levelType = $request->level;
            $geoInfo = [
                'province_id' => $request->province_id,
                'district_id' => $request->district_id,
                'ward_id' => $request->ward_id,
                'scheme' => $request->scheme ?? 'old'
            ];
            
            $schoolIds = $this->getSchoolsByLevelAndGeo($levelType, $geoInfo);
            
            // Debug: Log thông tin
            // \Log::info('Debug getGraduationStudents', [
            //     'levelType' => $levelType,
            //     'geoInfo' => $geoInfo,
            //     'schoolIds' => $schoolIds,
            //     'schoolIdsCount' => count($schoolIds)
            // ]);
            
            if (empty($schoolIds)) {
                return response()->json([]);
            }
            
            $query->whereHas('education', function ($q) use ($schoolIds) {
                $q->whereIn('school_id', $schoolIds);
            });
        } else {
            // Lấy học sinh từ trường cụ thể
            $query->whereHas('education', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            });
        }

        $students = $query->get()
            ->map(function ($detail) {
                return [
                    'person_id' => $detail->person_id,
                    'person_name' => $detail->person->name ?? 'N/A',
                    'class_label' => $detail->education->class_label ?? 'N/A',
                    'block_name' => $detail->education->block->name ?? 'N/A',
                    'school_name' => $detail->education->school->name ?? 'N/A',
                ];
            });

        return response()->json($students);
    }

    /**
     * Lấy danh sách trường theo cấp học và địa bàn
     */
    private function getSchoolsByLevelAndGeo($levelType, $geoInfo)
    {
        $levelMappings = [
            'preschool' => ['Mầm non'],
            'primary' => ['Tiểu học + THCS', 'Tiểu học + THCS + THPT', 'Tiểu học'],
            'junior' => ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS'],
            'high' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']
        ];

        $possibleNames = $levelMappings[$levelType] ?? [];
        
        $query = School::active()
            ->whereHas('educationalLevel', function($q) use ($possibleNames) {
                $q->whereIn('name', $possibleNames);
            });

        if ($geoInfo) {
            if ($geoInfo['scheme'] === 'old') {
                if (!empty($geoInfo['ward_id'])) {
                    $query->where(function($q) use ($geoInfo) {
                        $q->where('old_ward_id', $geoInfo['ward_id'])
                          ->orWhere('new_ward_id', $geoInfo['ward_id'])
                          ->orWhere('village_id', $geoInfo['ward_id']);
                    });
                } elseif (!empty($geoInfo['district_id'])) {
                    $query->where('old_district_id', $geoInfo['district_id']);
                } elseif (!empty($geoInfo['province_id'])) {
                    $query->where(function($q) use ($geoInfo) {
                        $q->where('old_province_id', $geoInfo['province_id'])
                          ->orWhere('new_province_id', $geoInfo['province_id']);
                    });
                }
            } else {
                if (!empty($geoInfo['ward_id'])) {
                    $query->where('new_ward_id', $geoInfo['ward_id']);
                } elseif (!empty($geoInfo['province_id'])) {
                    $query->where('new_province_id', $geoInfo['province_id']);
                }
            }
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Kiểm tra quyền địa giới của user
     */
    private function hasGeoPermission($user, $scopeType, $scopeId)
    {
        // Lấy thông tin địa giới của user
        $userGeo = $this->getUserGeoInfo($user);
        
        if (!$userGeo) {
            return false; // User không có thông tin địa giới
        }

        // Kiểm tra quyền theo phạm vi (chỉ có tỉnh, xã, trường)
        switch ($scopeType) {
            case 'province':
                return $userGeo['province_id'] == $scopeId;
            case 'ward':
                return $userGeo['ward_id'] == $scopeId;
            case 'school':
                // Kiểm tra quyền trường
                return $userGeo['school_id'] == $scopeId;
            default:
                return false;
        }
    }

    /**
     * Lấy thông tin địa giới của user
     */
    private function getUserGeoInfo($user)
    {
        // Sử dụng method getGeoInfo() của User model
        return $user->getGeoInfo();
    }

}