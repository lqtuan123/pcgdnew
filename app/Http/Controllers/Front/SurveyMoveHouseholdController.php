<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Person;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\GeoUnit;
use App\Models\User;
use App\Services\MoveHouseholdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SurveyMoveHouseholdController extends Controller
{
    /**
     * Hiển thị modal chuyển hộ
     */
    public function modal(SurveyForm $survey, Request $request)
    {
        // Kiểm tra quyền chuyển hộ
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'edit')) {
            abort(403, 'Bạn không có quyền chuyển hộ.');
        }

        // Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            abort(403, 'Phiếu điều tra này đã bị khóa và không thể chuyển hộ.');
        }

        // Lấy năm học từ request hoặc sử dụng năm của phiếu gốc
        $academicYear = $request->input('academic_year', $survey->academic_year);

        // Lấy danh sách thành viên của năm hiện tại (để gửi lên server)
        $members = SurveyFormDetail::query()
            ->where('survey_form_id', $survey->id)
            ->where('academic_year', $academicYear)
            ->where('is_deleted', false)
            ->with(['person:id,ho_dem,ten'])
            ->orderBy('id')
            ->get()
            ->map(function (SurveyFormDetail $d) use ($survey) {
                $person = $d->person;
                // Lấy relationship từ household_members
                $relationship = \App\Models\HouseholdMember::where('person_id', $d->person_id)
                    ->where('household_id', $survey->household_id)
                    ->value('role') ?? 'Thành viên';
                
                return [
                    'id' => $d->id,
                    'person_id' => $d->person_id,
                    'name' => trim(($person->ho_dem ?? '') . ' ' . ($person->ten ?? '')),
                    'relationship' => $relationship,
                ];
            });

        // Lấy danh sách thôn/xóm trong cùng xã (chỉ theo địa giới mới)
        $currentWard = $survey->newWard;
        $villages = collect();
        
        if ($currentWard) {
            $villages = GeoUnit::query()
                ->where('parent_id', $currentWard->id)
                ->where('type_level', 4) // Thôn/xóm là cấp 4
                ->where('scheme', 'new') // Chỉ lấy địa giới mới
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        // Lấy tên chủ hộ cũ làm mặc định
        $currentHeadName = '';
        if ($survey->headPerson) {
            $currentHeadName = trim(($survey->headPerson->ho_dem ?? '') . ' ' . ($survey->headPerson->ten ?? ''));
        }


        return view('front.surveyforms.modals.move_household', [
            'survey' => $survey,
            'members' => $members,
            'villages' => $villages,
            'academicYear' => $academicYear,
            'currentHeadName' => $currentHeadName,
        ]);
    }

    /**
     * Lấy danh sách thôn/xóm theo xã
     */
    public function ajaxVillages(Request $request)
    {
        $wardId = $request->input('ward_id');
        
        if (!$wardId) {
            return response()->json(['items' => []]);
        }

        // Debug: Kiểm tra xã có tồn tại không
        $ward = GeoUnit::find($wardId);
        if (!$ward) {
            return response()->json(['items' => []]);
        }


        $villages = GeoUnit::query()
            ->where('parent_id', $wardId)
            ->where('type_level', 4) // Thôn/xóm là cấp 4
            ->where('scheme', 'new') // Chỉ lấy địa giới mới
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($village) {
                return [
                    'id' => $village->id,
                    'name' => $village->name,
                ];
            });


        return response()->json(['items' => $villages]);
    }

    /**
     * Thực hiện chuyển hộ
     */
    public function store(Request $request, SurveyForm $survey, MoveHouseholdService $service)
    {
        // Kiểm tra quyền chuyển hộ
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền chuyển hộ.'
            ], 403);
        }

        // Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Phiếu điều tra này đã bị khóa và không thể chuyển hộ.'
            ], 403);
        }

        // Validate dữ liệu
        $payload = $request->validate([
            'new_so_phieu' => ['required', 'string', 'max:50'],
            'new_head_name' => ['required', 'string', 'max:100'],
            'new_village_id' => ['required', 'integer', 'exists:geo_units,id'],
            'academic_year' => ['required', 'string', 'max:20'],
            'members' => ['required', 'array', 'min:1'],
            'members.*.person_id' => ['required', 'integer'],
            'members.*.relationship' => ['required', 'string', 'max:50'],
        ]);

        try {
            $result = $service->move($survey->id, $payload);
            
            // Đảm bảo response có format đúng
            if (isset($result['success']) && $result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'] ?? 'Chuyển hộ thành công',
                    'redirect' => route('front.surveyforms.index')
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Có lỗi xảy ra khi chuyển hộ'
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Move household error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
}
