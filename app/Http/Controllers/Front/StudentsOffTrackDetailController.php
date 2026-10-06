<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\StudentsOffTrack;
use App\Services\AccessGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentsOffTrackDetailController extends Controller
{
    public function __construct(
        private AccessGuardService $guard
    ) {}

    private function checkStudentGeoAccess(StudentsOffTrack $student): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->isSuperadmin() || $user->isAdmin()) return true;

        if ($user->ward_id) {
            $wardCode = \App\Models\GeoUnit::find($user->ward_id)?->code;
            if ($wardCode) {
                return $student->selected_ward_code_new === $wardCode || $student->selected_ward_code_old === $wardCode;
            }
        }

        if ($user->province_id) {
            $provCode = \App\Models\GeoUnit::find($user->province_id)?->code;
            if ($provCode) {
                return $student->selected_province_code_new === $provCode || $student->selected_province_code_old === $provCode;
            }
        }

        return false;
    }

    public function show($id)
    {
        // Kiểm tra quyền xem chi tiết học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'view')) {
            abort(403, 'Bạn không có quyền xem chi tiết học sinh trái tuyến.');
        }

        $student = StudentsOffTrack::with(['ethnicity', 'religion', 'priorityCategory', 'educationBlock', 'school', 'graduationLevel'])
            ->findOrFail($id);

        if (!$this->checkStudentGeoAccess($student)) {
            abort(403, 'Bạn không có quyền xem chi tiết học sinh ở ngoài địa bàn được phân quyền.');
        }
        
        return view('front.students-off-track.detail', compact('student'));
    }

    public function edit($id)
    {
        // Kiểm tra quyền chỉnh sửa học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa học sinh trái tuyến.');
        }

        $student = StudentsOffTrack::with(['ethnicity', 'religion', 'priorityCategory', 'educationBlock', 'school', 'graduationLevel'])
            ->findOrFail($id);

        if (!$this->checkStudentGeoAccess($student)) {
            abort(403, 'Bạn không có quyền chỉnh sửa học sinh ở ngoài địa bàn được phân quyền.');
        }
        
        // Lấy dữ liệu cho các dropdown
        $ethnicities = \App\Models\Ethnicity::orderBy('name')->get();
        $religions = \App\Models\Religion::orderBy('name')->get();
        $priorityCategories = \App\Models\PriorityCategory::orderBy('name')->get();
        $educationBlocks = \App\Models\EducationBlock::orderBy('name')->get();
        $graduationLevels = \App\Models\GraduationLevel::orderBy('name')->get();
        $schools = \App\Models\School::active()->orderBy('code')->get();
        $specialCircumstances = \App\Models\SpecialCircumstance::orderBy('name')->get();
        
        return view('front.students-off-track.edit', compact(
            'student', 'ethnicities', 'religions', 'priorityCategories', 
            'educationBlocks', 'graduationLevels', 'schools', 'specialCircumstances'
        ));
    }

    public function update(Request $request, $id)
    {
        // Kiểm tra quyền cập nhật học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật học sinh trái tuyến.'
            ], 403);
        }

        $student = StudentsOffTrack::findOrFail($id);

        if (!$this->checkStudentGeoAccess($student)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật học sinh ở ngoài địa bàn được phân quyền.'
            ], 403);
        }
        
        $request->validate([
            'ho_dem' => 'required|string|max:255',
            'ten' => 'required|string|max:255',
            'dob_day' => 'nullable|integer|min:1|max:31',
            'dob_month' => 'nullable|integer|min:1|max:12',
            'dob_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'identity_number' => 'nullable|string|max:20',
            'is_female' => 'boolean',
            'ethnicity_id' => 'nullable|exists:ethnicities,id',
            'religion_id' => 'nullable|exists:religions,id',
            'priority_category_id' => 'nullable|exists:priority_categories,id',
            'education_block_id' => 'nullable|exists:education_blocks,id',
            'school_id' => 'nullable|exists:schools,id',
            'graduation_level_id' => 'nullable|exists:graduation_levels,id',
            'academic_year' => 'required|string',
            'class_label' => 'nullable|string|max:255',
            'hoc_hai_buoi' => 'boolean',
            'hoc_9_10_buoi_tuan' => 'boolean',
            'is_bo_tuc' => 'boolean',
            'graduation_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'bo_hoc_lop' => 'nullable|string|max:255',
            'bo_hoc_nam' => 'nullable|integer|min:1900|max:' . date('Y'),
            'disability_van_dong' => 'boolean',
            'disability_nghe_noi' => 'boolean',
            'disability_nhin' => 'boolean',
            'disability_than_kinh' => 'boolean',
            'disability_tri_tue' => 'boolean',
            'disability_hoc_tap' => 'boolean',
            'disability_tu_ky' => 'boolean',
            'disability_khac' => 'boolean',
            'disability_has_certificate' => 'boolean',
            'disability_can_learn' => 'nullable|string|max:255',
            'special_circumstance_name' => 'nullable|string|max:255',
            'special_circumstance_detail' => 'nullable|string|max:255',
        ]);
        
        // Xử lý geo data từ geo-filter (nơi ở)
        $geoData = $request->get('stay_geo', []);
        $stayGeoData = [];
        
        if (!empty($geoData)) {
            $provinceId = $geoData['province_id'] ?? null;
            $wardId = $geoData['ward_id'] ?? null;
            
            $provinceCode = $provinceId ? \App\Models\GeoUnit::find($provinceId)?->code : null;
            $wardCode = $wardId ? \App\Models\GeoUnit::find($wardId)?->code : null;
            
            $stayGeoData = [
                'stay_province_code_new' => $provinceCode,
                'stay_ward_code_new' => $wardCode,
            ];
        }
        
        // Merge geo data với request data
        $updateData = array_merge($request->all(), $stayGeoData);
        
        $student->update($updateData);
        
        $redirectParams = [];
        
        if ($request->has('academic_year')) {
            $redirectParams['academic_year'] = $request->get('academic_year');
        }
        
        if ($request->has('scheme')) {
            $redirectParams['scheme'] = $request->get('scheme');
        }
        
        if ($request->has('geo')) {
            $redirectParams['geo'] = $request->get('geo');
        }
        
        if ($request->has('search')) {
            $redirectParams['search'] = $request->get('search');
        }
        
        return redirect()->route('front.students-off-track.index', $redirectParams)
            ->with('success', 'Thông tin học sinh đã được cập nhật thành công.');
    }

    public function destroy($id)
    {
        // Kiểm tra quyền xóa học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa học sinh trái tuyến.'
            ], 403);
        }

        $student = StudentsOffTrack::findOrFail($id);

        if (!$this->checkStudentGeoAccess($student)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa học sinh ở ngoài địa bàn được phân quyền.'
            ], 403);
        }
        
        // Soft delete: chuyển is_deleted = true thay vì xóa thật
        $student->update([
            'is_deleted' => true,
            'editor_id' => auth()->id() // Lưu người thực hiện xóa
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Học sinh đã được xóa thành công.'
        ]);
    }

    public function data($survey)
    {
        return response()->json(['message' => 'Students off track detail data not implemented yet']);
    }
}

