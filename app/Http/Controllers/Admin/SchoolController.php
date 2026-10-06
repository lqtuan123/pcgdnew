<?php
namespace App\Http\Controllers\Admin;

use App\Services\SchoolService;
use App\Services\GeoService;
use Illuminate\Http\Request;
use App\Models\School;
use App\Models\SchoolType;
use App\Models\EducationalLevel;
use App\Models\GeoUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolStoreRequest;
use App\Http\Requests\SchoolUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class SchoolController extends Controller
{
    protected $service;

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

    public function __construct(SchoolService $service)
    {
        $this->service = $service;   // <-- gán vào property
    }

    public function export(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        // truyền nguyên query string để dùng chung filter với trang index nếu cần
        return $this->service->exportXlsx($request->query());
    }
   
  

// public function index(Request $req, GeoService $geo)
// {
//     $q = \App\Models\School::with(['schoolType','educationalLevel','wardUnit','districtUnit'])
//         ->when($req->filled('q'), fn($x)=>$x->where(fn($w)=>$w
//             ->where('name','like','%'.$req->q.'%')
//             ->orWhere('code','like','%'.$req->q.'%')
//         ))
//         ->when($req->filled('school_type_id'), fn($x)=>$x->where('school_type_id',$req->school_type_id))
//         ->when($req->filled('educational_level_id'), fn($x)=>$x->where('educational_level_id',$req->educational_level_id))
//         // lọc cũ: chọn trực tiếp theo code cấp 2/3 nếu có
//         ->when($req->filled('district_code'), fn($x)=>$x->where('district_code',$req->district_code))
//         ->when($req->filled('ward_code'),     fn($x)=>$x->where('ward_code',$req->ward_code))
//         // lọc theo tỉnh CŨ: gom các code cấp 2 & 3 (old) thuộc tỉnh đó
//         ->when($req->filled('province_code') && !$req->filled('district_code') && !$req->filled('ward_code'),
//             function($x) use ($geo,$req){
//                 [$dCodes, $wCodes] = $geo->oldCodesByProvince($req->province_code);
//                 $x->where(function($w) use ($dCodes,$wCodes){
//                     if ($dCodes) $w->orWhereIn('district_code',$dCodes);
//                     if ($wCodes) $w->orWhereIn('ward_code',$wCodes);
//                 });
//             }
//         )
//         // ====== NEW ======
//         ->when($req->filled('ward_code_new'), fn($x)=>$x->where('ward_code_new',$req->ward_code_new))
//         ->when($req->filled('province_code_new') && !$req->filled('ward_code_new'),
//             function($x) use ($geo,$req){
//                 $newWardCodes = $geo->newWardCodesByProvince($req->province_code_new);
//                 if ($newWardCodes) {
//                     $x->whereIn('ward_code_new', $newWardCodes);
//                 }
//             }
//         );

//     return view('admin.schools.index', [
//         'schools' => $q->paginate(20)->withQueryString(),
//         'types'   => \App\Models\SchoolType::orderBy('name')->get(),
//         'levels'  => \App\Models\EducationalLevel::orderBy('name')->get(),
//     ]);
// }
// public function index(Request $req, GeoService $geo)
// {
//     $q = \App\Models\School::with(['schoolType','educationalLevel','wardUnit','districtUnit'])
//         ->when($req->filled('q'), fn($x)=>$x->where(fn($w)=>$w
//             ->where('name','like','%'.$req->q.'%')
//             ->orWhere('code','like','%'.$req->q.'%')
//         ))
//         ->when($req->filled('school_type_id'), fn($x)=>$x->where('school_type_id',$req->school_type_id))
//         ->when($req->filled('educational_level_id'), fn($x)=>$x->where('educational_level_id',$req->educational_level_id))

//         // ===== BỘ LỌC CŨ =====
//         ->when($req->filled('district_code'), fn($x)=>$x->where('district_code',$req->district_code))
//         ->when($req->filled('ward_code'),     fn($x)=>$x->where('ward_code',$req->ward_code))
//         ->when($req->filled('province_code') && !$req->filled('district_code') && !$req->filled('ward_code'),
//             function($x) use ($geo,$req){
//                 [$dCodes, $wCodes] = $geo->oldCodesByProvince($req->province_code);
//                 $x->where(function($w) use ($dCodes,$wCodes){
//                     if ($dCodes) $w->orWhereIn('district_code',$dCodes);
//                     if ($wCodes) $w->orWhereIn('ward_code',$wCodes);
//                 });
//             }
//         )

//         // ===== BỘ LỌC MỚI + ÁNH XẠ QUA CŨ =====
//         ->when($req->filled('ward_code_new') || $req->filled('province_code_new'),
//             function($x) use ($geo,$req){
//                 $newWardCodes = [];
//                 $mappedOld = ['l2'=>[], 'l3'=>[]];

//                 if ($req->filled('ward_code_new')) {
//                     $newWardCodes = [$req->ward_code_new];
//                     $mappedOld    = $geo->oldCodesMappedFromNewWard($req->ward_code_new);
//                 } else { // only province_code_new
//                     $newWardCodes = $geo->newWardCodesByProvince($req->province_code_new);
//                     $mappedOld    = $geo->oldCodesMappedFromNewProvince($req->province_code_new);
//                 }

//                 $x->where(function($w) use ($newWardCodes,$mappedOld){
//                     if (!empty($newWardCodes)) $w->orWhereIn('ward_code_new', $newWardCodes);
//                     if (!empty($mappedOld['l3'])) $w->orWhereIn('ward_code', $mappedOld['l3']);
//                     if (!empty($mappedOld['l2'])) $w->orWhereIn('district_code', $mappedOld['l2']);
//                 });
//             }
//         );
//         dd($q->toSql(), $q->getBindings());
//     return view('admin.schools.index', [
//         'schools' => $q->paginate(20)->withQueryString(),
//         'types'   => \App\Models\SchoolType::orderBy('name')->get(),
//         'levels'  => \App\Models\EducationalLevel::orderBy('name')->get(),
//     ]);
// }

public function index(Request $req, GeoService $geo)
{
    // Kiểm tra quyền truy cập (sử dụng hệ thống phân quyền 4 cấp mới)
    $currentUser = auth()->user();
    if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
        abort(403, 'Bạn không có quyền xem danh sách trường học.');
    }

    // Lấy danh sách trường học theo phân quyền mới
    $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
    
    // === Gom tập mã ===
    $matchNewWards     = [];
    $matchOldWards     = [];
    $matchOldDistricts = [];

    // ----- TỪ BỘ LỌC CŨ -----
    if ($req->filled('ward_code')) {
        // chọn đúng 1 xã cũ
        $matchOldWards[] = $req->ward_code;
    } elseif ($req->filled('district_code')) {
        // chọn 1 huyện cũ => gom toàn bộ xã cũ dưới huyện đó
        $matchOldDistricts[] = $req->district_code;
        $matchOldWards = array_merge($matchOldWards, $geo->oldWardCodesByDistrict($req->district_code));
    } elseif ($req->filled('province_code')) {
        // chọn 1 tỉnh cũ => gom toàn bộ huyện & xã cũ
        [$dCodes, $wCodes] = $geo->oldCodesByProvince($req->province_code);
        $matchOldDistricts = array_merge($matchOldDistricts, $dCodes);
        $matchOldWards     = array_merge($matchOldWards, $wCodes);
    }

    // ----- TỪ BỘ LỌC MỚI -----
    if ($req->filled('ward_code_new')) {
        $matchNewWards[] = $req->ward_code_new;

        $mapped = $geo->oldCodesMappedFromNewWard($req->ward_code_new);
        $matchOldWards     = array_merge($matchOldWards, $mapped['l3'] ?? []);
        $matchOldDistricts = array_merge($matchOldDistricts, $mapped['l2'] ?? []);

    } elseif ($req->filled('province_code_new')) {
        // gom tất cả xã/phường mới dưới tỉnh mới này
        $matchNewWards = array_merge($matchNewWards, $geo->newWardCodesByProvince($req->province_code_new));

        $mapped = $geo->oldCodesMappedFromNewProvince($req->province_code_new);
        $matchOldWards     = array_merge($matchOldWards, $mapped['old_ward_codes'] ?? []);
        $matchOldDistricts = array_merge($matchOldDistricts, $mapped['old_district_codes'] ?? []);
    }

    // unique + loại bỏ rỗng
    $matchNewWards     = array_values(array_unique(array_filter($matchNewWards)));
    $matchOldWards     = array_values(array_unique(array_filter($matchOldWards)));
    $matchOldDistricts = array_values(array_unique(array_filter($matchOldDistricts)));

    // === Build query ===
    $q = School::with(['schoolType','educationalLevel','wardUnit','districtUnit','wardUnitNew'])
        // Áp dụng phân quyền phân cấp
        ->when(!empty($manageableSchoolIds), function($query) use ($manageableSchoolIds) {
            $query->whereIn('id', $manageableSchoolIds);
        }, function($query) {
            // Nếu không có quyền truy cập trường nào, trả về kết quả rỗng
            $query->whereRaw('1 = 0');
        })
        ->when($req->filled('q'), fn($x)=>$x->where(fn($w)=>$w
            ->where('name','like','%'.$req->q.'%')
            ->orWhere('code','like','%'.$req->q.'%')
        ))
        ->when($req->filled('school_type_id'), fn($x)=>$x->where('school_type_id',$req->school_type_id))
        ->when($req->filled('educational_level_id'), fn($x)=>$x->where('educational_level_id',$req->educational_level_id))
        // ⬇️ NEW: lọc giải thể (tri-state: tất cả / 0 / 1)
            ->when($req->has('is_dissolved') && $req->is_dissolved !== '', function($x) use ($req) {
                $x->where('is_dissolved', (int)$req->is_dissolved);
            })
        // ⭐⭐ CHỈ 1 KHỐI ĐIỀU KIỆN ĐỊA GIỚI (OR) ⭐⭐
        ->when(($matchNewWards || $matchOldWards || $matchOldDistricts), function($x) use ($matchNewWards,$matchOldWards,$matchOldDistricts){
            $x->where(function($w) use ($matchNewWards,$matchOldWards,$matchOldDistricts){
                if ($matchNewWards)     $w->orWhereIn('ward_code_new', $matchNewWards);
                if ($matchOldWards)     $w->orWhereIn('ward_code',     $matchOldWards);
                if ($matchOldDistricts) $w->orWhereIn('district_code', $matchOldDistricts);
            });
        });
        
    return view('admin.schools.index', [
        'schools' => $q->paginate(20)->withQueryString(),
        'types'   => \App\Models\SchoolType::orderBy('name')->get(),
        'levels'  => \App\Models\EducationalLevel::orderBy('name')->get(),
    ]);
}
/**
 * Lấy danh sách mã cấp 2/cấp 3 thuộc tỉnh cũ bằng province_code (ví dụ: cu-66).
 * @return array{0: array<int,string>, 1: array<int,string>} [districtCodes, wardCodes]
 */
private function oldCodesByProvince(string $provinceCode): array
{
    // Tìm tỉnh cũ (level=1, scheme=old)
    $prov = GeoUnit::query()
        ->where('scheme', 'old')
        ->where('type_level', 1)
        ->where('code', $provinceCode)
        ->first();

    if (!$prov) return [[], []];

    // Lấy cấp 2 trực thuộc tỉnh
    $districts = GeoUnit::query()
        ->where('scheme', 'old')
        ->where('type_level', 2)
        ->where('parent_id', $prov->id)
        ->get(['id','code']);

    $districtIds   = $districts->pluck('id')->all();
    $districtCodes = $districts->pluck('code')->all();

    // Lấy cấp 3 trực thuộc các quận/huyện đó
    $wardCodes = [];
    if (!empty($districtIds)) {
        $wardCodes = GeoUnit::query()
            ->where('scheme', 'old')
            ->where('type_level', 3)
            ->whereIn('parent_id', $districtIds)
            ->pluck('code')
            ->all();
    }

    return [$districtCodes, $wardCodes];
}

    public function create()
    {
        // Kiểm tra quyền tạo trường học (sử dụng hệ thống phân quyền phân cấp mới)
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasHierarchicalPermission('quan_ly_truong_hoc', 'create')) {
            abort(403, 'Bạn không có quyền tạo trường học mới.');
        }

        return view('admin.schools.create', [
            'types'  => SchoolType::orderBy('name')->get(),
            'levels' => EducationalLevel::orderBy('name')->get(),
        ]);
    }
    public function store(SchoolStoreRequest $req)
    {
        // Kiểm tra quyền tạo trường học (sử dụng hệ thống phân quyền mới)
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'create')) {
            abort(403, 'Bạn không có quyền tạo trường học mới.');
        }

        $data = $req->validated();
        $data['is_dissolved'] = $req->boolean('is_dissolved');
        $data['is_conglap'] = $req->boolean('is_conglap');
        $this->service->create($data);
        return redirect()->route('admin.schools.index')->with('toast','Đã tạo trường.');
    }

    public function edit(School $school)
    {
        // Kiểm tra quyền chỉnh sửa trường học (sử dụng hệ thống phân quyền mới)
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa trường học.');
        }

        // Kiểm tra user có thể truy cập trường này không
        if (!$currentUser->isAdmin() && !$currentUser->canAccessSchool($school->id)) {
            abort(403, 'Bạn không có quyền truy cập trường học này.');
        }

        $types  = SchoolType::orderBy('name')->get();
        $levels = EducationalLevel::orderBy('name')->get();
        return view('admin.schools.edit', compact('school','types','levels'));
    }

    public function update(SchoolUpdateRequest $req, School $school)
    {
        // Kiểm tra quyền chỉnh sửa trường học (sử dụng hệ thống phân quyền mới)
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa trường học.');
        }

        // Kiểm tra user có thể truy cập trường này không
        if (!$currentUser->isAdmin() && !$currentUser->canAccessSchool($school->id)) {
            abort(403, 'Bạn không có quyền truy cập trường học này.');
        }

        $data = $req->validated();

        // checkbox: chỉ gửi khi tick
        $data['is_dissolved'] = $req->boolean('is_dissolved');
        $data['is_conglap'] = $req->boolean('is_conglap');

        // Convert ward_code_new thành new_ward_id nếu có
        if (!empty($data['ward_code_new'])) {
            $ward = GeoUnit::where('code', $data['ward_code_new'])
                ->where('scheme', 'new')
                ->where('type_level', 3)
                ->first();
            if ($ward) {
                $data['new_ward_id'] = $ward->id;
            }
        }

        // Convert ward_code thành old_ward_id nếu có
        if (!empty($data['ward_code'])) {
            $oldWard = GeoUnit::where('code', $data['ward_code'])
                ->where('scheme', 'old')
                ->where('type_level', 3)
                ->first();
            if ($oldWard) {
                $data['old_ward_id'] = $oldWard->id;
            }
        }

        // Convert district_code thành old_district_id nếu có
        if (!empty($data['district_code'])) {
            $oldDist = GeoUnit::where('code', $data['district_code'])
                ->where('scheme', 'old')
                ->where('type_level', 2)
                ->first();
            if ($oldDist) {
                $data['old_district_id'] = $oldDist->id;
            }
        }

        $school->update($data);

        return redirect()
            ->route('admin.schools.edit', $school)
            ->with('success', 'Đã lưu thay đổi.');
    }

    public function show(School $school)
    {
        // Kiểm tra quyền truy cập
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
            abort(403, 'Bạn không có quyền xem thông tin trường học.');
        }

        // Kiểm tra quyền truy cập trường cụ thể
        if (!$currentUser->isAdmin() && !$currentUser->canAccessSchool($school->id)) {
            abort(403, 'Bạn không có quyền xem thông tin trường học này.');
        }

        $school->load(['schoolType','educationalLevel']);
        // phân trang giáo viên (nếu không có model này, trang vẫn render phần thông tin trường)
        $teachers = method_exists($school, 'teachers')
            ? $school->teachers()->orderBy('name')->paginate(15)
            : collect([]); // fallback

        return view('admin.schools.show', compact('school','teachers'));
    }

   

    public function destroy(School $school)
    {
        // Kiểm tra quyền xóa trường học (sử dụng hệ thống phân quyền mới)
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'delete')) {
            abort(403, 'Bạn không có quyền xóa trường học.');
        }

        // Kiểm tra user có thể truy cập trường này không
        if (!$currentUser->isAdmin() && !$currentUser->canAccessSchool($school->id)) {
            abort(403, 'Bạn không có quyền truy cập trường học này.');
        }

        $this->service->delete($school);
        return back()->with('ok','Đã xoá.');
    }

    /**
     * Xóa hàng loạt trường học
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:schools,id'
        ], [
            'ids.required' => 'Vui lòng chọn ít nhất một trường học để xóa',
            'ids.array' => 'Dữ liệu không hợp lệ',
            'ids.min' => 'Vui lòng chọn ít nhất một trường học để xóa'
        ]);

        // Kiểm tra quyền xóa trường học
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'delete')) {
            abort(403, 'Bạn không có quyền xóa trường học.');
        }

        $schools = School::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errorMessages = [];

        foreach ($schools as $school) {
            // Kiểm tra user có thể truy cập trường này không
            if (!$currentUser->isAdmin() && !$currentUser->canAccessSchool($school->id)) {
                $errorMessages[] = "Không có quyền xóa '{$school->name}'";
                continue;
            }

            try {
                $this->service->delete($school);
                $deletedCount++;
            } catch (\Exception $e) {
                $errorMessages[] = "Không thể xóa '{$school->name}': " . $e->getMessage();
            }
        }

        if ($deletedCount > 0) {
            $message = "Đã xóa thành công {$deletedCount} trường học";
            if (!empty($errorMessages)) {
                $message .= ". " . implode('; ', $errorMessages);
            }
            return redirect()->route('admin.schools.index')
                ->with('ok', $message);
        } else {
            return redirect()->route('admin.schools.index')
                ->with('error', implode('; ', $errorMessages));
        }
    }

    /**
     * Lấy danh sách ID trường học mà user hiện tại có thể quản lý
     */
    private function getManageableSchoolIds($user)
    {
        // Sử dụng role_level thay vì permission_level
        if ($user->isAdmin()) {
            return School::pluck('id')->toArray();
        }
        
        if ($user->isProvince()) {
            // Lấy tất cả trường trong tỉnh
            return School::whereHas('wardUnit', function($query) use ($user) {
                $query->whereHas('parent', function($q) use ($user) {
                    $q->where('id', $user->province_id);
                });
            })->pluck('id')->toArray();
        }
        
        if ($user->isWard()) {
            // Lấy tất cả trường trong xã
            return School::where('ward_id', $user->ward_id)
                ->pluck('id')->toArray();
        }
        
        if ($user->isSchool()) {
            // Chỉ lấy trường được phân quyền
            return [$user->school_id];
        }
        
        return [];
    }

    /**
     * Lấy danh sách trường học theo ward ID (sử dụng ward_code_new)
     */
    public function getByWard($wardId)
    {
        // Kiểm tra quyền truy cập
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh sách trường học.'], 403);
        }

        // Lấy ward code từ ward ID
        $ward = \App\Models\GeoUnit::find($wardId);
        if (!$ward) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách trường học mà user có thể truy cập
        $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
        
        // Tìm trường học theo ward_code_new, chỉ lấy trường Tiểu học và trong phạm vi quyền hạn
        $schools = School::where('ward_code_new', $ward->code)
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->when(!empty($manageableSchoolIds), function($query) use ($manageableSchoolIds) {
                $query->whereIn('id', $manageableSchoolIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
            
        return response()->json(['schools' => $schools]);
    }

    /**
     * Lấy trường học theo ID (cho trường hợp user bị khóa theo school)
     */
    public function getById($schoolId)
    {
        // Kiểm tra quyền truy cập
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh sách trường học.'], 403);
        }

        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách trường học mà user có thể truy cập
        $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
        
        // Tìm trường học theo ID, chỉ lấy trường Tiểu học và trong phạm vi quyền hạn
        $school = School::where('id', $schoolId)
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->when(!empty($manageableSchoolIds), function($query) use ($manageableSchoolIds) {
                $query->whereIn('id', $manageableSchoolIds);
            })
            ->first(['id', 'name', 'code']);
            
        // Trả về dưới dạng array để tương thích với fetchSchools
        $schools = $school ? [$school] : [];
        
        return response()->json(['schools' => $schools]);
    }

    /**
     * Lấy tất cả trường học (cho trường hợp user chưa chọn ward)
     */
    public function getAll()
    {
        // Kiểm tra quyền truy cập
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh sách trường học.'], 403);
        }

        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách trường học mà user có thể truy cập
        $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
        
        // Tìm tất cả trường học Tiểu học trong phạm vi quyền hạn
        $schools = School::whereIn('educational_level_id', $primaryLevelIds)
            ->when(!empty($manageableSchoolIds), function($query) use ($manageableSchoolIds) {
                $query->whereIn('id', $manageableSchoolIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
            
        return response()->json(['schools' => $schools]);
    }

    /**
     * Lấy trường học theo province ID
     */
    public function getByProvince($provinceId)
    {
        // Kiểm tra quyền truy cập
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh sách trường học.'], 403);
        }

        // Lấy province code từ province ID
        $province = \App\Models\GeoUnit::find($provinceId);
        if (!$province) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách trường học mà user có thể truy cập
        $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
        
        // Tìm trường học theo province_code_new, chỉ lấy trường Tiểu học và trong phạm vi quyền hạn
        $schools = School::where('province_code_new', $province->code)
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->when(!empty($manageableSchoolIds), function($query) use ($manageableSchoolIds) {
                $query->whereIn('id', $manageableSchoolIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
            
        return response()->json(['schools' => $schools]);
    }

    /**
     * Lấy danh sách trường học theo ward code
     */
    public function getByWardCode($wardCode)
    {
        // Kiểm tra quyền truy cập
        $currentUser = Auth::user();
        if (!$currentUser || !$currentUser->hasPermission('quan_ly_truong_hoc', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh sách trường học.'], 403);
        }

        // Lấy danh sách ID của các cấp học Tiểu học
        $primaryLevelIds = $this->getPrimaryLevelIds();
        if (empty($primaryLevelIds)) {
            return response()->json(['schools' => []]);
        }
        
        // Lấy danh sách trường học mà user có thể truy cập
        $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
        
        // Tìm trường học theo ward_code_new, chỉ lấy trường Tiểu học và trong phạm vi quyền hạn
        $schools = School::where('ward_code_new', $wardCode)
            ->whereIn('educational_level_id', $primaryLevelIds)
            ->when(!empty($manageableSchoolIds), function($query) use ($manageableSchoolIds) {
                $query->whereIn('id', $manageableSchoolIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
            
        return response()->json(['schools' => $schools]);
    }
}