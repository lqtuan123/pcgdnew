<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Services\MergeHouseholdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AccessGuardService;
class SurveyFormMergeController extends Controller
{
     public function __construct(
            private AccessGuardService $guard,
        ) {}
    public function modal(\App\Models\SurveyForm $survey, Request $request)
    {
        // Kiểm tra quyền ghép hộ phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'create')) {
            abort(403, 'Bạn không có quyền ghép hộ trong phiếu điều tra.');
        }

        // Lấy năm học từ request hoặc sử dụng năm của phiếu gốc
        $academicYear = $request->input('academic_year', $survey->academic_year);

        // members của phiếu hiện tại (để chọn chủ hộ/quan hệ sau ghép) - lọc theo năm học
       $members = SurveyFormDetail::query()
            ->where('survey_form_id', $survey->id)
            ->where('academic_year', $academicYear)
            ->join('people as p', 'p.id', '=', 'survey_form_details.person_id')
            ->leftJoin('household_members as hm', function($j) use ($survey){
                $j->on('hm.person_id', '=', 'survey_form_details.person_id')
                ->where('hm.household_id', '=', $survey->household_id);
            })
            ->get([
                'survey_form_details.person_id',
                DB::raw("CONCAT(IFNULL(p.ho_dem,''),' ',IFNULL(p.ten,'')) as name"),
                DB::raw("CASE WHEN p.is_female=1 THEN 'Nữ' ELSE 'Nam' END as gender"),
                DB::raw("TRIM(CONCAT(
                    IFNULL(p.dob_day,''),'/',IFNULL(p.dob_month,''),'/',IFNULL(p.dob_year,'')
                )) as dob"),
                'hm.role as role',
            ]);

        // danh mục quan hệ
        $relationships = \App\Models\HouseRelationship::orderBy('id')->get(['id','name']);

        return view('front.surveyforms.modals.merge', [
            'survey'        => $survey,
            'members'       => $members,
            'relationships' => $relationships,
            'academicYear'  => $academicYear,
        ]);
    }
    // Tìm phiếu khác để ghép (loại trừ phiếu hiện tại, chỉ lấy chưa xóa)
    public function ajaxSearch(Request $r, SurveyForm $survey)
    {
        // Kiểm tra quyền tìm kiếm phiếu để ghép
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền tìm kiếm phiếu để ghép.'], 403);
        }

        $q = trim((string)$r->get('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        // Lấy năm học từ request hoặc sử dụng năm của phiếu gốc
        $academicYear = $r->input('academic_year', $survey->academic_year);

      // Tìm kiếm tất cả phiếu phù hợp với logic ưu tiên thông minh
      $allSurveys = SurveyForm::query()
            ->where('id','<>',$survey->id)
            ->where('is_deleted', false)
            ->withCount(['details' => function($query) use ($academicYear) {
                $query->where('academic_year', $academicYear)->where('is_deleted', false);
            }])
            ->with(['headPerson:id,ho_dem,ten'])
            ->get(['id','so_phieu','household_id','head_person_id','new_province_id','new_ward_id','village_id']);

      // Lọc và sắp xếp theo độ ưu tiên
      $items = $allSurveys->filter(function($s) use ($q) {
                $soPhieu = $s->so_phieu;
                $headName = trim(($s->headPerson->ho_dem ?? '').' '.($s->headPerson->ten ?? ''));
                
                // Tìm kiếm trong số phiếu hoặc tên chủ hộ
                return stripos($soPhieu, $q) !== false || stripos($headName, $q) !== false;
            })
            ->map(function($s) use ($q) {
                $head = $s->headPerson;
                $soPhieu = $s->so_phieu;
                $headName = trim(($head->ho_dem ?? '').' '.($head->ten ?? ''));
                
                // Tính điểm ưu tiên chi tiết
                $priority = 0;
                
                // Ưu tiên cao nhất: số phiếu bắt đầu bằng từ khóa (chính xác)
                if (stripos($soPhieu, $q) === 0) {
                    $priority = 1000;
                    // Bonus nếu độ dài khớp
                    if (strlen($soPhieu) === strlen($q)) {
                        $priority += 500;
                    }
                }
                // Ưu tiên cao: số phiếu chứa từ khóa ở vị trí gần đầu
                elseif (stripos($soPhieu, $q) !== false) {
                    $position = stripos($soPhieu, $q);
                    $priority = 800 - $position; // Càng gần đầu càng cao điểm
                }
                // Ưu tiên trung bình: tên chủ hộ chứa từ khóa
                elseif (stripos($headName, $q) !== false) {
                    $priority = 200;
                }
                
                return [
                    'id'             => $s->id,
                    'so_phieu'       => $soPhieu,
                    'members_count'  => $s->details_count,
                    'head_person_id' => $s->head_person_id,
                    'head_name'      => $headName,
                    'priority'       => $priority,
                ];
            })
            ->sortByDesc('priority')
            ->take(15) // Tăng số lượng kết quả
            ->values();

        return response()->json(['items' => $items]);
    }

    // Lấy members của phiếu khác để hiển thị chọn Chủ hộ/ quan hệ
    public function ajaxMembers(SurveyForm $survey, SurveyForm $other, Request $request)
    {
        try {
            // Kiểm tra quyền xem thành viên phiếu để ghép
            $user = Auth::user();
            if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'view')) {
                return response()->json(['error' => 'Bạn không có quyền xem thành viên phiếu để ghép.'], 403);
            }

            if ($other->id === $survey->id || $other->is_deleted) {
                return response()->json(['items' => []]);
            }

            // Lấy năm học từ request hoặc sử dụng năm của phiếu gốc
            $academicYear = $request->input('academic_year', $survey->academic_year);

            $members = $other->details()
                ->where('is_deleted', false)
                ->where('academic_year', $academicYear)
                ->with(['person:id,ho_dem,ten,is_female,dob_day,dob_month,dob_year'])
                ->get(['id','survey_form_id','person_id'])
                ->map(function (SurveyFormDetail $d) {
                    $p = $d->person;
                    if (!$p) {
                        return null;
                    }
                    
                    $name = trim(($p->ho_dem ?? '').' '.($p->ten ?? ''));
                    if (empty($name)) {
                        $name = 'Chưa có tên';
                    }
                    
                    return [
                        'person_id' => $d->person_id,
                        'name'      => $name,
                        'gender'    => $p->is_female ? 'Nữ' : 'Nam',
                        'dob'       => trim(implode('/', array_filter([$p->dob_day, $p->dob_month, $p->dob_year]))),
                        'role'      => 'Thành viên', // Default role
                    ];
                })
                ->filter() // Remove null values
                ->values(); // Re-index array

            return response()->json(['items' => $members]);
        } catch (\Exception $e) {
            \Log::error('Error in ajaxMembers: ' . $e->getMessage());
            return response()->json(['error' => 'Có lỗi xảy ra khi lấy danh sách thành viên.'], 500);
        }
    }

    // Thực hiện ghép
    public function store(Request $r, SurveyForm $survey, MergeHouseholdService $svc)
    {
        try {
            // Kiểm tra quyền ghép hộ phiếu điều tra
         
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'create'); // trả về: user, is_admin, scheme, geo
        // Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Phiếu điều tra này đã bị khóa và không thể chỉnh sửa.'
            ], 403);
        }
        $ctx = $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
        $geo = $this->guard->guardRequestGeoAndLock($request, 'SURVEY');
            $payload = $r->validate([
                'other_survey_id'       => ['required','integer','exists:survey_forms,id'],
                'new_head_id'           => ['required','integer'],
                'roles'                 => ['required','array'],         // roles[person_id] = 'Con', 'Vợ', ...
                'roles.*'               => ['nullable','string','max:100'],
                'address.new_province_id' => ['nullable','integer'],
                'address.new_ward_id'     => ['nullable','integer'],
                'address.village_id'      => ['nullable','integer'],
                'address.addressline'     => ['nullable','string','max:255'],
                'academic_year'          => ['nullable','string','max:20'],
            ]);
            
            // Manual validation for different survey
            if ($payload['other_survey_id'] == $survey->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể ghép hộ với chính nó.'
                ], 422);
            }

            $result = $svc->merge($survey->id, (int)$payload['other_survey_id'], [
                'new_head_id' => (int)$payload['new_head_id'],
                'roles'       => $payload['roles'],
                'address'     => $payload['address'],
                'academic_year' => $payload['academic_year'] ?? $survey->academic_year,
            ]);

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in store: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ: ' . implode(', ', array_flatten($e->errors()))
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi ghép hộ: ' . $e->getMessage()
            ], 500);
        }
    }
}
