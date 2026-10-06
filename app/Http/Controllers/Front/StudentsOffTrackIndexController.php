<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\StudentsOffTrack;
use App\Services\AccessGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StudentsOffTrackIndexController extends Controller
{
    public function __construct(
        private AccessGuardService $guard
    ) {}

    public function index(Request $request)
    {
        // Enforce quyền và ép geo filter theo tài khoản nếu là non-admin
        $ctx = $this->guard->enforce($request, 'quan_ly_hoc_sinh_trai_tuyen', 'view', [
            'force_scheme' => null,
        ]);
        $user = $ctx['user'];

        // Tính năm học hiện tại dựa trên thời gian thực
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        if ($currentMonth >= 9) {
            $defaultAcademicYear = $currentYear . '-' . ($currentYear + 1);
        } else {
            $defaultAcademicYear = ($currentYear - 1) . '-' . $currentYear;
        }
        
        $academicYear = $request->get('academic_year', $defaultAcademicYear);
        $search = $request->get('search', '');
        $scheme = $ctx['scheme'] ?? $request->get('scheme', 'old');
        $perPage = $request->get('per_page', 50);

        // Lấy thông tin geo từ request/context đã ép theo tài khoản
        $geoData = array_merge((array) $request->get('geo', []), (array) $ctx['geo']);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;
        
        // Lấy code từ id của GeoUnit
        $provinceCode = $provinceId ? \App\Models\GeoUnit::find($provinceId)?->code : null;
        $districtCode = $districtId ? \App\Models\GeoUnit::find($districtId)?->code : null;
        $wardCode = $wardId ? \App\Models\GeoUnit::find($wardId)?->code : null;
        $villageCode = $villageId ? \App\Models\GeoUnit::find($villageId)?->code : null;

        $query = StudentsOffTrack::query()
            ->with(['ethnicity', 'religion', 'priorityCategory', 'educationBlock', 'school', 'graduationLevel'])
            ->where('academic_year', $academicYear)
            ->where('is_deleted', false); // Chỉ lấy học sinh chưa bị xóa

        // Lọc theo khu vực hành chính dựa trên scheme (hỗ trợ khớp cả new/old code)
        if ($scheme === 'old') {
            if ($villageCode) {
                $query->where(fn($q) => $q->where('selected_village_code_old', $villageCode)->orWhere('selected_village_code_new', $villageCode));
            } elseif ($wardCode) {
                $query->where(fn($q) => $q->where('selected_ward_code_old', $wardCode)->orWhere('selected_ward_code_new', $wardCode));
            } elseif ($districtCode) {
                $query->where('selected_district_code_old', $districtCode);
            } elseif ($provinceCode) {
                $query->where(fn($q) => $q->where('selected_province_code_old', $provinceCode)->orWhere('selected_province_code_new', $provinceCode));
            }
        } else {
            if ($villageCode) {
                $query->where(fn($q) => $q->where('selected_village_code_new', $villageCode)->orWhere('selected_village_code_old', $villageCode));
            } elseif ($wardCode) {
                $query->where(fn($q) => $q->where('selected_ward_code_new', $wardCode)->orWhere('selected_ward_code_old', $wardCode));
            } elseif ($provinceCode) {
                $query->where(fn($q) => $q->where('selected_province_code_new', $provinceCode)->orWhere('selected_province_code_old', $provinceCode));
            }
        }

        // Tìm kiếm theo tên, họ đệm, số định danh
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ten', 'like', "%{$search}%")
                  ->orWhere('ho_dem', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$search}%"]);
            });
        }

        $students = $query->orderBy('ten', 'asc')->orderBy('ho_dem', 'asc')->paginate($perPage);

        // Thống kê (có tính đến bộ lọc khu vực)
        $statsQuery = StudentsOffTrack::where('academic_year', $academicYear)
            ->where('is_deleted', false);
        
        if ($scheme === 'old') {
            if ($villageCode) {
                $statsQuery->where(fn($q) => $q->where('selected_village_code_old', $villageCode)->orWhere('selected_village_code_new', $villageCode));
            } elseif ($wardCode) {
                $statsQuery->where(fn($q) => $q->where('selected_ward_code_old', $wardCode)->orWhere('selected_ward_code_new', $wardCode));
            } elseif ($districtCode) {
                $statsQuery->where('selected_district_code_old', $districtCode);
            } elseif ($provinceCode) {
                $statsQuery->where(fn($q) => $q->where('selected_province_code_old', $provinceCode)->orWhere('selected_province_code_new', $provinceCode));
            }
        } else {
            if ($villageCode) {
                $statsQuery->where(fn($q) => $q->where('selected_village_code_new', $villageCode)->orWhere('selected_village_code_old', $villageCode));
            } elseif ($wardCode) {
                $statsQuery->where(fn($q) => $q->where('selected_ward_code_new', $wardCode)->orWhere('selected_ward_code_old', $wardCode));
            } elseif ($provinceCode) {
                $statsQuery->where(fn($q) => $q->where('selected_province_code_new', $provinceCode)->orWhere('selected_province_code_old', $provinceCode));
            }
        }
        
        $totalStudents = $statsQuery->count();
        $maleCount = (clone $statsQuery)->where('is_female', false)->count();
        $femaleCount = (clone $statsQuery)->where('is_female', true)->count();

        return view('front.students-off-track.index', compact(
            'students', 'academicYear', 'search', 'scheme', 'geoData', 
            'perPage', 'totalStudents', 'maleCount', 'femaleCount'
        ));
    }

    public function store(Request $request)
    {
        // Enforce quyền tạo học sinh trái tuyến
        $ctx = $this->guard->enforce($request, 'quan_ly_hoc_sinh_trai_tuyen', 'create', [
            'force_scheme' => null,
        ]);
        $user = $ctx['user'];

        try {
            // Convert ngày tháng sinh từ string sang integer trước khi validate
            $request->merge([
                'dob_day' => $request->dob_day ? (int)$request->dob_day : null,
                'dob_month' => $request->dob_month ? (int)$request->dob_month : null,
                'dob_year' => $request->dob_year ? (int)$request->dob_year : null,
            ]);

            // Nếu user là non-admin, ép địa bàn hiện tại theo địa bàn phân quyền
            if (!$ctx['isAdmin']) {
                $currentGeo = (array) $request->input('current_geo', []);
                if (!empty($user->province_id)) $currentGeo['province_id'] = $user->province_id;
                if (!empty($user->ward_id)) $currentGeo['ward_id'] = $user->ward_id;
                $request->merge(['current_geo' => $currentGeo]);
            }

            // Validate dữ liệu đầu vào
            $validated = $request->validate([
                'ho_dem' => 'required|string|max:255',
                'ten' => 'required|string|max:255',
                'dob_day' => 'nullable|integer|min:1|max:31',
                'dob_month' => 'nullable|integer|min:1|max:12',
                'dob_year' => 'nullable|integer|min:1900|max:' . date('Y'),
                'identity_number' => 'nullable|string|max:20',
                'is_female' => 'required|boolean',
                'ethnicity_id' => 'nullable|exists:ethnicities,id',
                'religion_id' => 'nullable|exists:religions,id',
                'priority_category_id' => 'nullable|exists:priority_categories,id',
                'education_block_id' => 'nullable|exists:education_blocks,id',
                'class_label' => 'nullable|string|max:50',
                'school_id' => 'nullable|exists:schools,id',
                'graduation_level_id' => 'nullable|exists:graduation_levels,id',
                'academic_year' => 'required|string',
                'hoc_hai_buoi' => 'nullable|boolean',
                'hoc_9_10_buoi_tuan' => 'nullable|boolean',
                'is_bo_tuc' => 'nullable|boolean',
                'graduation_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
                'bo_hoc_lop' => 'nullable|string|max:50',
                'bo_hoc_nam' => 'nullable|integer',
                'disability_van_dong' => 'nullable|boolean',
                'disability_nghe_noi' => 'nullable|boolean',
                'disability_nhin' => 'nullable|boolean',
                'disability_than_kinh' => 'nullable|boolean',
                'disability_tri_tue' => 'nullable|boolean',
                'disability_hoc_tap' => 'nullable|boolean',
                'disability_tu_ky' => 'nullable|boolean',
                'disability_khac' => 'nullable|boolean',
                'disability_has_certificate' => 'nullable|boolean',
                'disability_can_learn' => 'nullable|boolean',
                'special_circumstance_name' => 'nullable|string|max:255',
                'special_circumstance_detail' => 'nullable|string|max:500',
                // Thông tin nơi ở từ geo filter
                'stay_geo.province_id' => 'nullable|exists:geo_units,id',
                'stay_geo.ward_id' => 'nullable|exists:geo_units,id',
                // Geo filter hiện tại để lưu vào selected_*_new
                'current_geo.province_id' => 'required|exists:geo_units,id',
                'current_geo.ward_id' => 'nullable|exists:geo_units,id',
                'current_geo.village_id' => 'nullable|exists:geo_units,id',
            ]);

            // Lấy dữ liệu geo từ geo filter
            $geoData = $request->get('stay_geo', []);
            $provinceId = $geoData['province_id'] ?? null;
            $wardId = $geoData['ward_id'] ?? null;

            // Lấy dữ liệu geo filter hiện tại
            $currentGeoData = $request->get('current_geo', []);
            $currentProvinceId = $currentGeoData['province_id'] ?? null;
            $currentWardId = $currentGeoData['ward_id'] ?? null;
            $currentVillageId = $currentGeoData['village_id'] ?? null;

            // Lấy code từ id của GeoUnit
            $provinceCode = $provinceId ? \App\Models\GeoUnit::find($provinceId)?->code : null;
            $wardCode = $wardId ? \App\Models\GeoUnit::find($wardId)?->code : null;
            
            $currentProvinceCode = $currentProvinceId ? \App\Models\GeoUnit::find($currentProvinceId)?->code : null;
            $currentWardCode = $currentWardId ? \App\Models\GeoUnit::find($currentWardId)?->code : null;
            $currentVillageCode = $currentVillageId ? \App\Models\GeoUnit::find($currentVillageId)?->code : null;

            // Tạo học sinh mới
            $student = StudentsOffTrack::create([
                'ho_dem' => $validated['ho_dem'],
                'ten' => $validated['ten'],
                'dob_day' => $validated['dob_day'],
                'dob_month' => $validated['dob_month'],
                'dob_year' => $validated['dob_year'],
                'identity_number' => $validated['identity_number'],
                'is_female' => $validated['is_female'],
                'ethnicity_id' => $validated['ethnicity_id'],
                'religion_id' => $validated['religion_id'],
                'priority_category_id' => $validated['priority_category_id'],
                'education_block_id' => $validated['education_block_id'],
                'class_label' => $validated['class_label'],
                'school_id' => $validated['school_id'],
                'graduation_level_id' => $validated['graduation_level_id'],
                'academic_year' => $validated['academic_year'],
                'hoc_hai_buoi' => $validated['hoc_hai_buoi'] ?? false,
                'hoc_9_10_buoi_tuan' => $validated['hoc_9_10_buoi_tuan'] ?? false,
                'is_bo_tuc' => $validated['is_bo_tuc'] ?? false,
                'graduation_year' => $validated['graduation_year'],
                'bo_hoc_lop' => $validated['bo_hoc_lop'],
                'bo_hoc_nam' => $validated['bo_hoc_nam'],
                'disability_van_dong' => $validated['disability_van_dong'] ?? false,
                'disability_nghe_noi' => $validated['disability_nghe_noi'] ?? false,
                'disability_nhin' => $validated['disability_nhin'] ?? false,
                'disability_than_kinh' => $validated['disability_than_kinh'] ?? false,
                'disability_tri_tue' => $validated['disability_tri_tue'] ?? false,
                'disability_hoc_tap' => $validated['disability_hoc_tap'] ?? false,
                'disability_tu_ky' => $validated['disability_tu_ky'] ?? false,
                'disability_khac' => $validated['disability_khac'] ?? false,
                'disability_has_certificate' => $validated['disability_has_certificate'] ?? false,
                'disability_can_learn' => $validated['disability_can_learn'] ?? false,
                'special_circumstance_name' => $validated['special_circumstance_name'],
                'special_circumstance_detail' => $validated['special_circumstance_detail'],
                
                // Thông tin nơi ở
                'stay_province_code_new' => $provinceCode,
                'stay_ward_code_new' => $wardCode,
                
                // Geo filter hiện tại
                'selected_province_code_new' => $currentProvinceCode,
                'selected_ward_code_new' => $currentWardCode,
                'selected_village_code_new' => $currentVillageCode,
                
                'creator_id' => Auth::id(),
                'is_deleted' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thêm học sinh thành công!',
                'student_id' => $student->id
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating student: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm học sinh: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        // Enforce quyền xóa học sinh trái tuyến
        $ctx = $this->guard->enforce($request, 'quan_ly_hoc_sinh_trai_tuyen', 'delete', [
            'force_scheme' => null,
        ]);
        $user = $ctx['user'];

        // Lấy danh sách ID từ checkbox
        $ids = collect($request->input('ids', []))
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->unique()->values()->all();

        // Nếu có IDs -> xóa theo IDs (checkbox đã chọn)
        if (!empty($ids)) {
            $deleteQuery = StudentsOffTrack::whereIn('id', $ids)->where('is_deleted', false);
            
            // Nếu không phải admin, đảm bảo học sinh xóa thuộc địa bàn phân quyền
            if (!$ctx['isAdmin']) {
                $userWardCode = $user->ward_id ? \App\Models\GeoUnit::find($user->ward_id)?->code : null;
                $userProvCode = $user->province_id ? \App\Models\GeoUnit::find($user->province_id)?->code : null;
                
                if ($userWardCode) {
                    $deleteQuery->where(fn($q) => $q->where('selected_ward_code_new', $userWardCode)->orWhere('selected_ward_code_old', $userWardCode));
                } elseif ($userProvCode) {
                    $deleteQuery->where(fn($q) => $q->where('selected_province_code_new', $userProvCode)->orWhere('selected_province_code_old', $userProvCode));
                }
            }

            $deletedCount = $deleteQuery->update([
                'is_deleted' => true,
                'editor_id' => Auth::id()
            ]);

            $message = sprintf('Đã xóa thành công %d học sinh.', $deletedCount);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'reload' => true
                ]);
            }
            
            return redirect()->route('front.students-off-track.index', $request->query())
                ->with('success', $message);
        }

        // Nếu không có IDs -> xóa theo scope (bộ lọc)
        $scope = $request->string('scope')->toString() ?: 'filtered';
        $academicYear = $request->string('academic_year')->toString();
        $search = $request->string('search')->toString();
        $scheme = $ctx['scheme'] ?? $request->string('scheme')->toString();

        // Geo data từ context/request
        $geoData = array_merge((array) $request->get('geo', []), (array) $ctx['geo']);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;
        
        $provinceCode = $provinceId ? \App\Models\GeoUnit::find($provinceId)?->code : null;
        $districtCode = $districtId ? \App\Models\GeoUnit::find($districtId)?->code : null;
        $wardCode = $wardId ? \App\Models\GeoUnit::find($wardId)?->code : null;
        $villageCode = $villageId ? \App\Models\GeoUnit::find($villageId)?->code : null;

        $query = StudentsOffTrack::query()
            ->where('academic_year', $academicYear)
            ->where('is_deleted', false);

        if ($scheme === 'old') {
            if ($villageCode) {
                $query->where(fn($q) => $q->where('selected_village_code_old', $villageCode)->orWhere('selected_village_code_new', $villageCode));
            } elseif ($wardCode) {
                $query->where(fn($q) => $q->where('selected_ward_code_old', $wardCode)->orWhere('selected_ward_code_new', $wardCode));
            } elseif ($districtCode) {
                $query->where('selected_district_code_old', $districtCode);
            } elseif ($provinceCode) {
                $query->where(fn($q) => $q->where('selected_province_code_old', $provinceCode)->orWhere('selected_province_code_new', $provinceCode));
            }
        } else {
            if ($villageCode) {
                $query->where(fn($q) => $q->where('selected_village_code_new', $villageCode)->orWhere('selected_village_code_old', $villageCode));
            } elseif ($wardCode) {
                $query->where(fn($q) => $q->where('selected_ward_code_new', $wardCode)->orWhere('selected_ward_code_old', $wardCode));
            } elseif ($provinceCode) {
                $query->where(fn($q) => $q->where('selected_province_code_new', $provinceCode)->orWhere('selected_province_code_old', $provinceCode));
            }
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ten', 'like', "%{$search}%")
                  ->orWhere('ho_dem', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$search}%"]);
            });
        }

        $deletedCount = $query->update([
            'is_deleted' => true,
            'editor_id' => Auth::id()
        ]);

        $message = sprintf('Đã xóa thành công %d học sinh theo bộ lọc.', $deletedCount);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'reload' => true
            ]);
        }
        
        return redirect()->route('front.students-off-track.index', $request->query())
            ->with('success', $message);
    }
}