<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\{
    SurveyForm, SurveyFormDetail, Person, HouseholdMember,
    EducationBlock, EducationGrade, GraduationLevel, VocationalLevel,
    Ethnicity, Religion, ResidenceType, ResidenceStatus,
    DisabilityCategory, PersonDisability, PersonEducation, Literacy,
    PersonSpecialCircumstance, SpecialCircumstance,PriorityCategory,HouseRelationship,PersonLiteracy,School
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\SurveyMemberWriter;
use App\Support\MemberRowFactory;
use App\Services\Import\EducationCatalogResolver;
use App\Services\Education\EducationGradeService;
use App\Services\Schools\SchoolService;
use App\Services\Schools\StudyAreaResolver;
use App\Services\People\PersonDisabilityService;
class SurveyMemberController extends Controller
{

      public function __construct(
        protected EducationCatalogResolver $catalog, 
      protected EducationGradeService $gradeService,
      protected SchoolService $schoolService, 
      protected StudyAreaResolver $studyAreaResolver) {}

    /** Modal create */
    public function modalCreate($surveyId) {
        // Kiểm tra quyền thêm thành viên phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'create')) {
            abort(403, 'Bạn không có quyền thêm thành viên vào phiếu điều tra.');
        }

        $survey = SurveyForm::with(['oldWard','oldDistrict','oldProvince','newWard','newProvince','village'])->findOrFail($surveyId);

        // Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            abort(403, 'Phiếu điều tra này đã bị khóa và không thể thêm thành viên.');
        }

        return view('front.surveyforms.modals.members.create', [
            'survey'        => $survey,
            'ethnicities'   => Ethnicity::orderBy('name')->get(),
            'religions'     => Religion::orderBy('name')->get(),
            'resTypes'      => ResidenceType::orderBy('name')->get(),
            'resStats'      => ResidenceStatus::orderBy('name')->get(),
            'blocks'        => EducationBlock::orderBy('id')->get(),
            'gradLevels'    => GraduationLevel::orderBy('id')->get(),
            'vocLevels'     => VocationalLevel::orderBy('id')->get(),
            'disCats'       => DisabilityCategory::orderBy('id')->get(),
            'specials'      => SpecialCircumstance::orderBy('id')->get(),
            'grades'  => EducationGrade::orderBy('id')->get(),
            'priorities'  => PriorityCategory::orderBy('id')->get(),
             'relationships'  => HouseRelationship::orderBy('id')->get(),


        ]);
    }
    public function memberDestroy(Request $request, SurveyForm $survey, SurveyFormDetail $detail)
    {
        // Kiểm tra quyền xóa thành viên phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa thành viên khỏi phiếu điều tra.'
            ], 403);
        }

        // Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Phiếu điều tra này đã bị khóa và không thể xóa thành viên.'
            ], 403);
        }

        $person = Person::find($detail->person_id);
        // Bắt buộc có survey_id hợp lệ (route-model binding đã đảm bảo)
        if (!$survey->id) {
            return response()->json(['ok' => false, 'message' => 'Thiếu survey_id.'], 422);
        }

       

        if (!$detail) {
            return response()->json([
                'ok' => false,
                'message' => 'Không tìm thấy thành viên trong phiếu này.'
            ], 404);
        }

        try {
            DB::transaction(function () use ($request, $survey, $person, $detail) {

                // 2) Nếu người này đang là chủ hộ của phiếu -> bỏ liên kết (để tránh hiển thị sai)
                if ((int)$survey->head_person_id === (int)$person->id) {
                      return response()->json([
                            'ok' => false,
                            'message' => 'Là chủ hộ nên không được xóa, hãy xóa luôn phiếu.'
                        ], 404);
                }

                // 1) Xoá mềm bản ghi chi tiết trong phiếu
                if (!$detail->is_deleted) {
                    $detail->is_deleted = true;
                    $detail->save();
                }

              

                // 3) (Tuỳ chọn) Tách khỏi hộ của phiếu nếu bạn muốn
                //    Bật bằng query param ?detach_household=1
                // if ($request->boolean('detach_household') && $survey->household_id) {
                //     HouseholdMember::where('household_id', $survey->household_id)
                //         ->where('person_id', $person->id)
                //         ->delete(); // pivot thường không soft delete
                // }

                // LƯU Ý: Không xoá Person/education/literacy... để giữ lịch sử.
                // Khi cần add lại thành viên này vào phiếu, chỉ cần upsert detail (set is_deleted=false).
            });

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Xoá không thành công: ' . $e->getMessage(),
            ], 500);
        }
    }
  
public function memberStore(Request $request, ?SurveyForm $survey, SurveyMemberWriter $writer)
{
    // Kiểm tra quyền thêm thành viên phiếu điều tra
    $user = Auth::user();
    if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'create')) {
        return response()->json([
            'success' => false,
            'message' => 'Bạn không có quyền thêm thành viên vào phiếu điều tra.'
        ], 403);
    }

    // A) Bắt buộc có survey từ route hoặc body
    if (!$survey) {
        $survey = SurveyForm::find($request->input('survey_form_id'));
    }
    if (!$survey) {
        return response()->json([
            'ok' => false,
            'message' => 'Thiếu hoặc sai survey_id / survey_form_id.'
        ], 422);
    }

    // Kiểm tra phiếu có bị khóa không
    if (!$survey->is_active) {
        return response()->json([
            'success' => false,
            'message' => 'Phiếu điều tra này đã bị khóa và không thể thêm thành viên.'
        ], 403);
    }

    // B) Validate nhẹ (bắt buộc tên)
    $request->validate([
        'person.ten' => ['required','string','max:100'],
    ]);

    // C) Chuẩn hóa row theo schema import (ID -> TEXT)
    $row = MemberRowFactory::fromRequestArray($request->all());

    // C.1) Tự chèn số phiếu (để các service dùng chung logic import)
    if (empty($row['so_phieu'])) {
        $row['so_phieu'] = (string) $survey->so_phieu;
    }

    // D) Bối cảnh giống import: scheme, geo, academic_year
    $scheme = $request->input('scheme') ?: ($survey->scheme ?? 'new');

    // Lấy geo từ request; thiếu thì fallback household của phiếu
    $hh = $survey->household; // quan hệ household
    $geo = [
        'province_id' => $request->input('geo.province_id')
            ?? ($scheme==='new' ? ($hh->new_province_id ?? null) : ($hh->province_id ?? null)),
        'district_id' => $request->input('geo.district_id')
            ?? ($scheme==='new' ? ($hh->new_district_id ?? null) : ($hh->district_id ?? null)),
        'ward_id'     => $request->input('geo.ward_id')
            ?? ($scheme==='new' ? ($hh->new_ward_id ?? null)     : ($hh->ward_id ?? null)),
        // nếu có 2 trường này trong DB
        'village_id'  => $request->input('geo.village_id') ?? ($hh->new_village_id ?? $hh->village_id ?? null),
        'hamlet_id'   => $request->input('geo.hamlet_id')  ?? ($hh->new_hamlet_id  ?? $hh->hamlet_id  ?? null),
    ];
    // dd('ham them');
    try {
        $result = $writer->upsertMemberIntoSurvey($survey, $row, [
            'scheme'        => $scheme,
            'geo'           => $geo,
            'academic_year' => (string) \App\Models\SurveyForm::currentAcademicYear() ,
        ]);

        return response()->json([
            'ok'        => true,
            'survey_id' => $survey->id,
            'person_id' => $result['p']->id    ?? null,
            'detail_id' => $result['detail']->id ?? null,
            'person_mamnon_id' => $result['personMamnon']->id ?? null,
        ]);
    } catch (\Throwable $e) {
        report($e);
        return response()->json([
            'ok'      => false,
            'message' => 'Không lưu được thành viên: '.$e->getMessage(),
        ], 500);
    }
}
    

    public function memberUpdateDetail(Request $request, SurveyFormDetail  $detail )
    {
 

   
       
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'edit')) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền cập nhật.'
            ], 403);
        }

        // Kiểm tra phiếu có bị khóa không
        $survey = $detail->surveyForm;
        if (!$survey->is_active) {
            return response()->json([
                'ok' => false,
                'message' => 'Phiếu điều tra này đã bị khóa và không thể cập nhật thành viên.'
            ], 403);
        }

        if ($detail->is_deleted) {
            return response()->json([
                'ok' => false,
                'message' => 'Dòng chi tiết đã bị xóa tạm.'
            ], 404);
        }

        // Lấy các thực thể liên quan
        $survey = $detail->surveyForm()->firstOrFail();
        $person = $detail->person()->firstOrFail();
        $academicYear = (string)( $detail->academic_year ?? '');

        // Validate cơ bản (tuỳ ý mở rộng rule)
        $request->validate([
            // 'survey_form_id' => 'required|integer|in:'.$survey->id, // khoá theo phiếu hiện tại
            'person.ho_dem'  => 'nullable|string|max:255',
            'person.ten'     => 'required|string|max:255',
            'person.identity_number' => 'nullable|string|max:50',
            'person.dob_day'   => 'nullable|integer|min:1|max:31',
            'person.dob_month' => 'nullable|integer|min:1|max:12',
            'person.dob_year'  => 'nullable|integer|min:1900|max:'.(int)date('Y'),
            'person.ethnicity_id' => 'nullable|integer',
            'person.religion_id'  => 'nullable|integer',
            'person.priority_category_id' => 'nullable|integer',
            'person.addressline'  => 'nullable|string|max:500',
            'person.residence_type_id'   => 'nullable|integer',
            'person.residence_status_id' => 'nullable|integer',
            'person.phone'       => 'nullable|string|max:50',
            'person.is_female'       => 'nullable|boolean',
            'membership.role' => 'nullable|string|max:255',

            'education.school_id'            => 'nullable|integer',
            'education.school_code'          => 'nullable|string|max:50',
            'education.school_name'          => 'nullable|string|max:255',
            'education.education_block_id'   => 'nullable|integer',
            'education.class_label'          => 'nullable|string|max:50',
            'education.graduation_level_id'  => 'nullable|integer',
            'education.graduation_year'      => 'nullable|string|max:9|regex:/^\d{4}-\d{4}$/',
            'education.vocational_level_id'  => 'nullable|integer',
            'education.vocational_grad_year' => 'nullable|string|max:9|regex:/^\d{4}-\d{4}$/',
            'education.graduation_certificate_no' => 'nullable|string|max:100',
            'education.hoc_xong_lop'    => 'nullable|integer',
            'education.hoc_xong_nam'         => 'nullable|string|max:9|regex:/^\d{4}-\d{4}$/',
            'education.bo_hoc_lop'      => 'nullable|integer',
            'education.is_bo_tuc'      => 'nullable|boolean',
            'education.bo_hoc_nam'           => 'nullable|string|max:9|regex:/^\d{4}-\d{4}$/',

            'literacy.dang_hoc_lop'   => 'nullable|string|max:50',
            'literacy.hoan_thanh_lop' => 'nullable|string|max:50',
            'literacy.tai_mu_chu_muc' => 'nullable|string|max:50',

            'special.special_circumstance_id' => 'nullable|integer',
            'special.chi_tiet'                => 'nullable|string|max:500',

            'contact.name' => 'nullable|string|max:255',
            'contact.note' => 'nullable|string|max:1000',

            'redirect' => 'nullable|url'
        ]);
        $data = $request->all();
        $redirect = $data['redirect'] ?? url()->previous();

        try {
            DB::beginTransaction();

            /** -----------------------------
             * 1) Cập nhật PERSON (thông tin cơ bản + liên hệ)
             * ----------------------------- */
            $p = $data['person'] ?? [];
            $scheme = $request->input('scheme') ?: ($survey->scheme ?? 'new');
 
            $geo = [
                'province_id' => $request->input('geo.province_id')
                    ?? ($scheme==='new' ? ($hh->new_province_id ?? null) : ($hh->province_id ?? null)),
                'district_id' => $request->input('geo.district_id')
                    ?? ($scheme==='new' ? ($hh->new_district_id ?? null) : ($hh->district_id ?? null)),
                'ward_id'     => $request->input('geo.ward_id')
                    ?? ($scheme==='new' ? ($hh->new_ward_id ?? null)     : ($hh->ward_id ?? null)),
                // nếu có 2 trường này trong DB
                'village_id'  => $request->input('geo.village_id') ?? ($hh->new_village_id ?? $hh->village_id ?? null),
                'hamlet_id'   => $request->input('geo.hamlet_id')  ?? ($hh->new_hamlet_id  ?? $hh->hamlet_id  ?? null),
            ];
            $out = [];
            if ($scheme === 'old') {
                $out['old_ward_id']     = $geo['ward_id']     ?? null;
                $out['old_district_id'] = $geo['district_id'] ?? null;
                $out['old_province_id'] = $geo['province_id'] ?? null;
            } else {
                $out['new_ward_id']     = $geo['ward_id']     ?? null;
                 $out['new_province_id']     = $geo['province_id']     ?? null;
            }

            $person->fill([
                'identity_number'   => $p['identity_number'] ?? null,
                'ho_dem'            => $p['ho_dem'] ?? null,
                'ten'               => $p['ten'] ?? $person->ten,
                'dob_day'           => $p['dob_day'] ?? null,
                'dob_month'         => $p['dob_month'] ?? null,
                'dob_year'          => $p['dob_year'] ?? null,
                'ethnicity_id'      => $p['ethnicity_id'] ?? null,
                'religion_id'       => $p['religion_id'] ?? null,
                'addressline'       => $p['addressline'] ?? null,
                'residence_type_id' => $p['residence_type_id'] ?? null,
                'residence_status_id'=> $p['residence_status_id'] ?? null,
                'phone'             => $p['phone'] ?? null,
                 'so_phieu'             => $p['so_phieu'] ?? $person->so_phieu,
                'is_female' => $p['is_female'] ?? $person->is_female,
                'old_ward_id'=>$out['old_ward_id']?? $person->old_ward_id,
                'old_district_id'=>$out['old_district_id']?? $person->old_district_id,
                'old_province_id'=>$out['old_province_id']?? $person->old_province_id,
                'new_ward_id'=>$out['new_ward_id']?? $person->new_ward_id,
                'new_province_id'=>$out['new_province_id']?? $person->new_province_id,

                // Nếu bạn dùng priority riêng trên detail thì bỏ dòng dưới;
                'priority_category_id' => $p['priority_category_id'] ?? null,
            ]);

            // Map contact (nếu bạn đang lưu trực tiếp trên person)
            if (!empty($data['contact'])) {
                $person->contact_name = $data['contact']['name'] ?? null;
                $person->note         = $data['contact']['note'] ?? null;
            }

            $person->save();

            /** -----------------------------
             * 2) Quan hệ trong hộ đúng household của phiếu
             * ----------------------------- */
            if (!empty($data['membership']['role'])) {
                HouseholdMember::updateOrCreate(
                    ['household_id' => $survey->household_id, 'person_id' => $person->id],
                    ['role' => $data['membership']['role']]
                );
            }

            /** -----------------------------
             * 3) HỌC TẬP - theo đúng version của detail & academic_year
             *     - Nếu detail đã trỏ tới 1 education có cùng academic_year → update
             *     - Ngược lại → tạo record mới & gán lại FK trên detail
             * ----------------------------- */
          
            $eduInput = $data['education'] ?? [];
            $class_label = $eduInput['class_label']??null;
            $g = $this->catalog->parseEducationGradeName($class_label);

            // dd($class_label,$g);
             $school = null;
            if ($eduInput['school_id']  )
            {
                $school = School::find($eduInput['school_id']);

            }
            if (!empty($eduInput)) {
                $currentEdu = $detail->education; // qua quan hệ
                $shouldUpdate = $currentEdu && (string)$currentEdu->academic_year === $academicYear;

                $isOutside = $school && str_starts_with(strtoupper((string)$school->code), 'NGOAI_TINH');

                $eduPayload = [
                    'person_id'               => $person->id,
                    'education_block_id'      => $eduInput['education_block_id'] ?? null,
                    'class_label'             => $eduInput['class_label'] ?? null,
                    'school_id'               => $eduInput['school_id'] ?? null,
                    'school_name_custom'      => $isOutside ? (!empty($eduInput['school_name']) ? trim($eduInput['school_name']) : $school->name) : null,
                    'studying_district_custom'=> $isOutside ? (!empty($eduInput['studying_district']) ? trim($eduInput['studying_district']) : null) : null,
                    'graduation_level_id'     => $eduInput['graduation_level_id'] ?? null,
                    'graduation_year'         => $eduInput['graduation_year'] ?? null,
                    'vocational_level_id'     => $eduInput['vocational_level_id'] ?? null,
                    'hoc_hai_buoi'            => $this->toBool($g['hoc_hai_buoi'] ?? false),
                    'hoc_9_10_buoi_tuan'      => $this->toBool($g['hoc_9_10_buoi_tuan'] ?? false),
                    'is_repeat_grade'         => $this->toBool($g['is_repeat_grade'] ?? false),
                    'studying_district_id'    => $isOutside ? null : ($school ? $school->new_ward_id : null),
                    'is_bo_tuc'               => $eduInput['is_bo_tuc'] ?? false,
                    'vocational_grad_year'    => $eduInput['vocational_grad_year'] ?? null,
                    'graduation_certificate_no'=> $eduInput['graduation_certificate_no'] ?? null,
                    'hoc_xong_lop'            => $eduInput['hoc_xong_lop'] ?? null,
                    'hoc_xong_nam'            => $eduInput['hoc_xong_nam'] ?? null,
                    'bo_hoc_lop'              => $eduInput['bo_hoc_lop'] ?? null,
                    'bo_hoc_nam'              => $eduInput['bo_hoc_nam'] ?? null,
                    'academic_year'           => $academicYear,
                ];
               
                if ($shouldUpdate) {
                    $currentEdu->fill($eduPayload)->save();
                    $eduId = $currentEdu->id;
                } else {
                    $newEdu = PersonEducation::create($eduPayload);
                    $eduId = $newEdu->id;
                    $detail->person_education_id = $eduId;
                }
                // dd($currentEdu->vocational_level_id);
            }

            /** -----------------------------
             * 4) MÙ CHỮ - version theo detail (logic tương tự education)
             * ----------------------------- */
            $litInput = $data['literacy'] ?? [];
            if (!empty($litInput)) {
                $currentLit = $detail->literacy;
                // Literacy không có academic_year trong payload mẫu, nếu DB của bạn có thì set tương tự như education
                $litPayload = [
                    'person_id'        => $person->id,
                    'dang_hoc_lop'     => $litInput['dang_hoc_lop'] ?? null,
                    'hoan_thanh_lop'   => $litInput['hoan_thanh_lop'] ?? null,
                    'tai_mu_chu_muc'   => $litInput['tai_mu_chu_muc'] ?? null,
                ];

                if ($currentLit) {
                    $currentLit->fill($litPayload)->save();
                    $litId = $currentLit->id;
                } else {
                    $newLit = PersonLiteracy::create($litPayload);
                    $litId = $newLit->id;
                    $detail->literacy_id = $litId;
                }
            }

            /** -----------------------------
             * 5) HOÀN CẢNH ĐẶC BIỆT - version theo detail
             * ----------------------------- */
           $spInput = $data['special'] ?? null;
// special[special_circumstance_id]
        if ($spInput !== null) {
            // lấy sc_id từ payload (có thể null)
            $scId = $spInput['special_circumstance_id'] ?? null;
            $chiTiet = $spInput['chi_tiet'] ?? null;
            
            if ($scId) {
                // Có chọn hoàn cảnh → upsert
                $currentSp = $detail->specialCircumstance; // quan hệ
               
                $spPayload = [
                    'person_id' => $person->id,
                    'sc_id'     => $scId,
                    'chi_tiet'  => $chiTiet,
                ];

                if ($currentSp) {
                    $currentSp->fill($spPayload)->save();
                    $detail->person_special_circumstance_id = $currentSp->id;
                } else {
                    $newSp = \App\Models\PersonSpecialCircumstance::create($spPayload);
                    $detail->person_special_circumstance_id = $newSp->id;
                }
              
            } else {
                // Không chọn sc_id → bỏ liên kết
                // Nếu bạn muốn giữ bản cũ: chỉ cần unlink:
                $detail->person_special_circumstance_id = null;

                // (Tùy chọn) nếu muốn xoá mềm/hẳn bản cũ:
                // if ($detail->specialCircumstance) {
                //     $detail->specialCircumstance->delete(); // hoặc set is_deleted = 1 nếu có cột đó
                // }
            }
        }

            /** -----------------------------
             * 6) (Tuỳ chọn) KHUYẾT TẬT - nếu form có gửi lên
             * ----------------------------- */
           if ($request->has('dis')) {
                $ids = collect($request->input('dis.ids', []))
                    ->filter(fn($v) => $v !== null && $v !== '')
                    ->map(fn($v) => (int) $v)   // 👈 ép số
                    ->values()
                    ->all();

                $dis = $detail->personDisability;

                $disPayload = [
                    'person_id'       => $person->id,
                    'disability_ids'  => $ids,  // 👈 mảng số
                    'has_certificate' => (bool) $request->input('dis.has_certificate', false),
                    'can_learn'       => (bool) $request->input('dis.can_learn', false),
                    'note'            => $request->input('dis.note'),
                    'is_deleted'      => false,
                ];

                if ($dis) {
                    $dis->fill($disPayload)->save();
                    $disId = $dis->id;
                } else {
                    $newDis = PersonDisability::create($disPayload);
                    $disId = $newDis->id;
                    $detail->person_disability_id = $disId;
                    $detail->save();
                }
            }

            /** -----------------------------
             * 7) Lưu detail (nếu có đổi FK)
             * ----------------------------- */
            // $detail->academic_year = $academicYear ?: $detail->academic_year;
              $detail->save();
           
          

            DB::commit();
          
            return response()->json([
                'ok' => true,
                'redirect' => $redirect,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Không lưu được dữ liệu: '.$e->getMessage(),
            ], 500);
        }
            // Danh mục
            
    
    }
    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'x', 'v', 'yes', 'y', 'true', 't', 'co', 'có'], true);
        }
        if (is_numeric($value)) return (int)$value > 0;
        return false;
    }
    public function modalEdit(SurveyForm $survey, SurveyFormDetail $detail)
    {
        // Kiểm tra quyền chỉnh sửa thành viên phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa thành viên trong phiếu điều tra.');
        }

        // lấy đúng detail của person trong phiếu này
        $detail = SurveyFormDetail::query()
            ->where('id', $detail->id)
            // ->where('person_id', $person->id)
            ->with([
                // học tập + danh mục
                'education.school',
                'education.block',
                'education.graduationLevel',
                'education.vocationalLevel',
                // xóa mù chữ
                'literacy',
                // khuyết tật (bản tổng hợp JSON ids)
                'personDisability',
                // hoàn cảnh đặc biệt (nếu bạn có quan hệ này trên detail)
                'specialCircumstance',
            ])
            ->firstOrFail();
            $survey = SurveyForm::find($detail->survey_form_id);
        $person = Person::find($detail->person_id);
        // ===== Prefill các thực thể mà form cần =====
        $education  = $detail->education ?? new \App\Models\PersonEducation([
            'education_block_id'       => null,
            'class_label'              => null,
            'school_id'                => null,
            'graduation_level_id'      => null,
            'graduation_year'          => null,
            'is_bo_tuc'                => false,
            'vocational_level_id'      => null,
            'vocational_grad_year'     => null,
            'graduation_certificate_no'=> null,
            'hoc_xong_lop'        => null,
            'hoc_xong_nam'             => null,
            'bo_hoc_lop'          => null,
            'bo_hoc_nam'               => null,
        
            'academic_year'            => (string)$detail->academic_year,
        ]);
        
        $literacy   = $detail->literacy ?? new \App\Models\PersonLiteracy([
            'person_id'        => $person->id,
            'dang_hoc_lop'     => null,
            'hoan_thanh_lop'   => null,
            'tai_mu_chu_muc'   => null,
        ]);

        $disability = $detail->personDisability ?? new \App\Models\PersonDisability([
            'person_id'       => $person->id,
            'disability_ids'  => [],
            'has_certificate' => false,
            'can_learn'       => false,
            'note'            => null,
            'is_deleted'      => false,
        ]);

        $special    = $detail->specialCircumstance ?? new \App\Models\PersonSpecialCircumstance([
            'person_id'  => $person->id,
            'sc_id'      => null,
            'chi_tiet'   => null,
        ]);
        // dd($special);
        // Quan hệ trong hộ (để điền “Quan hệ với chủ hộ”)
            $membership = \App\Models\HouseholdMember::query()
            ->where('household_id', $survey->household_id)
            ->where('person_id',   $person->id)
            ->first() ?? new \App\Models\HouseholdMember([
                'household_id' => $survey->household_id,
                'person_id'    => $person->id,
                'role'         => null,
            ]);

        // ===== Danh mục chọn =====
        $blocks      = \App\Models\EducationBlock::orderBy('id')->get(['id','name']);
        $gradLevels  = \App\Models\GraduationLevel::orderBy('id')->get(['id','name']);
        $vocLevels   = \App\Models\VocationalLevel::orderBy('id')->get(['id','name']);
        $grades      = \App\Models\EducationGrade::orderBy('id')->get(['id','code','name']);
        $disCats     = \App\Models\DisabilityCategory::orderBy('id')->get(['id','name']);

        $ethnicities = \App\Models\Ethnicity::orderBy('id')->get(['id','name']);
        $religions   = \App\Models\Religion::orderBy('id')->get(['id','name']);
        $resTypes    = \App\Models\ResidenceType::orderBy('id')->get(['id','name']);
        $resStats    = \App\Models\ResidenceStatus::orderBy('id')->get(['id','name']);
        $specials    = \App\Models\SpecialCircumstance::orderBy('id')->get(['id','name']);
        $priorities  = \App\Models\PriorityCategory::orderBy('id')->get(['id','name']);
        $relationships = \App\Models\HouseRelationship::orderBy('id')->get(['id','name']);

        // School-picker: prefill trường nếu có
        $initialSchool = $education->school ? [
            'id'   => $education->school->id,
            'code' => $education->school->code,
            'name' => $education->school->name,
        ] : null;

        // Geo cho school-picker (sơ đồ mới)
        $geoInit = [
            'province_id' => $survey->new_province_id,
            'district_id' => null,
            'ward_id'     => $survey->new_ward_id,
        ];

        // Trả dữ liệu đúng các biến mà form blade đang dùng
        return view('front.surveyforms.modals.members.edit', compact(
            'survey',
            'person',
            'education',
            'literacy',        // 👈 giờ đã có
            'disability',
            'special',
            'membership',
            'blocks',
            'gradLevels',
            'vocLevels',
            'grades',
            'disCats',
            'ethnicities',
            'religions',
            'resTypes',
            'resStats',
            'specials',
            'priorities',
            'relationships',
            'initialSchool',
            'geoInit',
            'detail'
        ));
    }
    /** Nguồn options chung cho select */
    private function formOptions(SurveyForm $survey, array $more = []): array
    {
        return array_merge([
            'survey'      => $survey,
            'blocks'      => EducationBlock::orderBy('id')->get(['id','name']),
            'grades'      => EducationGrade::where('is_active',true)->orderBy('id')->get(['id','name','code']),
            'gradLevels'  => GraduationLevel::orderBy('id')->get(['id','name']),
            'vocLevels'   => VocationalLevel::orderBy('id')->get(['id','name']),
            'ethnicities' => Ethnicity::orderBy('id')->get(['id','name']),
            'religions'   => Religion::orderBy('id')->get(['id','name']),
            'resTypes'    => ResidenceType::orderBy('name')->get(['id','name']),
            'resStats'    => ResidenceStatus::orderBy('name')->get(['id','name']),
            'disCats'     => DisabilityCategory::whereIn('code',[
                                'VAN_DONG','NGHE_NOI','NHIN','THAN_KINH','TRI_TUE','HOC_TAP','TU_KY','KHAC'
                              ])->orderBy('id')->get(['id','code','name']),
            'specials'    => SpecialCircumstance::orderBy('id')->get(['id','name']),
              'priorities'  => PriorityCategory::orderBy('id')->get(['id','name']),
              'relationships'  => HouseRelationship::orderBy('id')->get(),
        ], $more);
    }

    /** Validate chung cho store & update */
    private function rules(Request $r, SurveyForm $survey, ?Person $person = null): array
    {
        return [
            'person.ten'        => ['required','string','max:100'],
            'person.ho_dem'     => ['nullable','string','max:150'],
            'person.identity_number' => ['nullable','regex:/^\d{9}(\d{3})?$/','max:12'],
            'person.dob_day'    => ['nullable','integer','between:1,31'],
            'person.dob_month'  => ['nullable','integer','between:1,12'],
            'person.dob_year'   => ['nullable','integer','between:1900,2100'],
            'person.is_female'  => ['nullable','boolean'],
            'person.ethnicity_id' => ['nullable','exists:ethnicities,id'],
            'person.religion_id'  => ['nullable','exists:religions,id'],
            'person.phone'         => ['nullable','regex:/^0\d{9,10}$/','max:11'],
            'person.addressline'   => ['nullable','string','max:255'],
            'person.residence_type_id'   => ['nullable','exists:residence_types,id'],
            'person.residence_status_id' => ['nullable','exists:residence_statuses,id'],

            'membership.role' => ['nullable','string','max:50'],

            'education.education_block_id' => ['nullable','exists:education_blocks,id'],
            'education.education_grade_id' => ['nullable','exists:education_grades,id'],
            'education.class_label'        => ['nullable','string','max:50'],
            'education.school_id'          => ['nullable','integer'],
            'education.school_code'        => ['nullable','string','max:50'],
            'education.school_name'        => ['nullable','string','max:255'],
            'education.studying_district'  => ['nullable','string','max:255'],
            'education.graduation_level_id' => ['nullable','exists:graduation_levels,id'],
            'education.graduation_year'     => ['nullable','string','max:9','regex:/^\d{4}-\d{4}$/'],
            'education.is_bo_tuc'           => ['nullable','boolean'],
            'education.vocational_level_id' => ['nullable','exists:vocational_levels,id'],
            'education.vocational_grad_year'=> ['nullable','string','max:9','regex:/^\d{4}-\d{4}$/'],
            'education.graduation_certificate_no' => ['nullable','string','max:100'],

            'literacy.dang_hoc_lop'   => ['nullable','string','max:50'],
            'literacy.hoan_thanh_lop' => ['nullable','string','max:50'],
            'literacy.tai_mu_chu_muc' => ['nullable','string','max:50'],

            'dis.ids'            => ['array'],
            'dis.ids.*'          => ['integer','exists:disability_categories,id'],
            'dis.has_certificate'=> ['nullable','boolean'],
            'dis.can_learn'      => ['nullable','boolean'],

            'special.special_circumstance_id' => ['nullable','exists:special_circumstances,id'],
            'special.chi_tiet'                => ['nullable','string','max:500'],

            'contact.name'  => ['nullable','string','max:150'],
            'contact.phone' => ['nullable','regex:/^0\d{9,10}$/','max:11'],
            'contact.note'  => ['nullable','string','max:500'],
        ];
    }

    /** Ajax lưu THÊM mới */
    public function storeAjax(Request $r, SurveyForm $survey)
    {
       $academicYear=  \App\Models\SurveyForm::currentAcademicYear() ;
        $v = validator($r->all(), $this->rules($r, $survey), [], [
            'person.ten' => 'Tên',
            'person.ho_dem' => 'Họ đệm',
            'person.identity_number' => 'CCCD',
            'person.dob_day'   => 'Ngày sinh',
            'person.dob_month' => 'Tháng sinh',
            'person.dob_year'  => 'Năm sinh',
            'person.phone'     => 'Điện thoại',
        ]);
        if ($v->fails()) {
            $html = view('front.surveyforms.modals.members.form',
                $this->formOptions($survey, ['mode'=>'create','person'=>null])
            )->withErrors($v)->render();

            return response()->json(['ok'=>false,'html'=>$html], 422);
        }

        $data = $v->validated();

        DB::beginTransaction();
        try {
            // 1) Person
            $p = new Person();
            $p->fill($data['person'] ?? []);
            // Gắn geo theo survey
            $p->old_province_id = $survey->old_province_id;
            $p->old_district_id = $survey->old_district_id;
            $p->old_ward_id     = $survey->old_ward_id;
            $p->new_province_id = $survey->new_province_id;
            $p->new_ward_id     = $survey->new_ward_id;
            $p->village_id      = $survey->village_id;
            $p->save();

            // 2) Membership vào hộ của survey
            if ($survey->household_id) {
                HouseholdMember::updateOrCreate(
                    ['household_id'=>$survey->household_id,'person_id'=>$p->id],
                    ['role'=>($data['membership']['role'] ?? null) ?: 'Thành viên']
                );
            }

            // 3) Detail (dòng thành viên của phiếu)
            SurveyFormDetail::create([
                'survey_form_id' => $survey->id,
                'person_id'      => $p->id,
                'is_deleted'     => false,
            ]);

            // 4) Giáo dục (1 bản ghi/năm học)
            if (!empty($data['education'])) {
                $eduInput = $data['education'];
                $school = !empty($eduInput['school_id']) ? School::find($eduInput['school_id']) : null;
                $isOutside = $school && str_starts_with(strtoupper((string)$school->code), 'NGOAI_TINH');
                if ($isOutside) {
                    $eduInput['school_name_custom'] = !empty($eduInput['school_name']) ? trim($eduInput['school_name']) : $school->name;
                    $eduInput['studying_district_custom'] = !empty($eduInput['studying_district']) ? trim($eduInput['studying_district']) : null;
                    $eduInput['studying_district_id'] = null;
                } else {
                    $eduInput['school_name_custom'] = null;
                    $eduInput['studying_district_custom'] = null;
                }
                PersonEducation::updateOrCreate(
                    ['person_id'=>$p->id, 'academic_year'=> $academicYear],
                    array_merge($eduInput, ['person_id'=>$p->id,'academic_year'=> $academicYear])
                );
            }

            // 5) Xóa mù chữ
            if (!empty($data['literacy'])) {
                Literacy::updateOrCreate(
                    ['person_id'=>$p->id],
                    array_merge($data['literacy'], ['person_id'=>$p->id])
                );
            }

            // 6) Khuyết tật
            $disIds = $data['dis']['ids'] ?? [];
            PersonDisability::updateOrCreate(
                ['person_id'=>$p->id],
                [
                    'person_id'       => $p->id,
                    'disability_ids'  => $disIds,
                    'has_certificate' => (bool)($data['dis']['has_certificate'] ?? false),
                    'can_learn'       => (bool)($data['dis']['can_learn'] ?? false),
                ]
            );

            // 7) Hoàn cảnh đặc biệt
            if (!empty($data['special'])) {
                PersonSpecialCircumstance::updateOrCreate(
                    ['person_id'=>$p->id],
                    [
                        'person_id'               => $p->id,
                        'special_circumstance_id' => $data['special']['special_circumstance_id'] ?? null,
                        'chi_tiet'                => $data['special']['chi_tiet'] ?? null,
                    ]
                );
            }

            // 8) Liên lạc (lưu ngay trên person để trùng với index bạn đang đọc)
            $p->contact_name = $data['contact']['name']  ?? null;
            $p->phone        = $data['contact']['phone'] ?? ($p->phone ?? null);
            $p->save();

            DB::commit();

            return response()->json([
                'ok'       => true,
                'message'  => 'Đã thêm thành viên.',
                // Mở lại modal danh sách để refresh nhẹ nhàng
                'redirect' => route('front.surveyforms.modal.members.index', $survey->id),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['ok'=>false,'message'=>'Lỗi lưu: '.$e->getMessage()], 500);
        }
    }

    /** Modal danh sách (bạn đã có) – chỉ trả về view danh sách members */
    public function modalIndex(SurveyForm $survey)
    {
        // ...lấy $rows như bạn đang dùng rồi:
        return view('front.surveyforms.modals.members.index', compact('survey','rows'));
    }

    /** Ajax update (tương tự storeAjax, chỉ khác $person = route model) */
    public function updateAjax(Request $r, SurveyForm $survey, Person $person)
    {

         $academicYear = \App\Models\SurveyForm::currentAcademicYear() ;
        $v = validator($r->all(), $this->rules($r, $survey, $person));
        if ($v->fails()) {
            $html = view('front.surveyforms.modals.members.form',
                $this->formOptions($survey, ['mode'=>'edit','person'=>$person])
            )->withErrors($v)->render();

            return response()->json(['ok'=>false,'html'=>$html], 422);
        }
        $data = $v->validated();

        DB::beginTransaction();
        try {
            $person->fill($data['person'] ?? [])->save();

            if ($survey->household_id) {
                HouseholdMember::updateOrCreate(
                    ['household_id'=>$survey->household_id,'person_id'=>$person->id],
                    ['role'=>($data['membership']['role'] ?? null) ?: 'Thành viên']
                );
            }

            SurveyFormDetail::firstOrCreate([
                'survey_form_id'=>$survey->id,
                'person_id'=>$person->id
            ],['is_deleted'=>false]);

            if (!empty($data['education'])) {
                $eduInput = $data['education'];
                $school = !empty($eduInput['school_id']) ? School::find($eduInput['school_id']) : null;
                $isOutside = $school && str_starts_with(strtoupper((string)$school->code), 'NGOAI_TINH');
                if ($isOutside) {
                    $eduInput['school_name_custom'] = !empty($eduInput['school_name']) ? trim($eduInput['school_name']) : $school->name;
                    $eduInput['studying_district_custom'] = !empty($eduInput['studying_district']) ? trim($eduInput['studying_district']) : null;
                    $eduInput['studying_district_id'] = null;
                } else {
                    $eduInput['school_name_custom'] = null;
                    $eduInput['studying_district_custom'] = null;
                }
                PersonEducation::updateOrCreate(
                    ['person_id'=>$person->id, 'academic_year'=> $academicYear],
                    array_merge($eduInput, ['person_id'=>$person->id,'academic_year'=> $academicYear])
                );
            }

            if (!empty($data['literacy'])) {
                Literacy::updateOrCreate(
                    ['person_id'=>$person->id],
                    array_merge($data['literacy'], ['person_id'=>$person->id])
                );
            }

            $disIds = $data['dis']['ids'] ?? [];
            PersonDisability::updateOrCreate(
                ['person_id'=>$person->id],
                [
                    'person_id'=>$person->id,
                    'disability_ids'=>$disIds,
                    'has_certificate'=>(bool)($data['dis']['has_certificate'] ?? false),
                    'can_learn'=>(bool)($data['dis']['can_learn'] ?? false),
                ]
            );

            PersonSpecialCircumstance::updateOrCreate(
                ['person_id'=>$person->id],
                [
                    'person_id'=>$person->id,
                    'special_circumstance_id'=>$data['special']['special_circumstance_id'] ?? null,
                    'chi_tiet'=>$data['special']['chi_tiet'] ?? null,
                ]
            );

            $person->contact_name = $data['contact']['name']  ?? null;
            $person->phone        = $data['contact']['phone'] ?? ($person->phone ?? null);
            $person->save();

            DB::commit();

            return response()->json([
                'ok'=>true,
                'message'=>'Đã cập nhật thành viên.',
                'redirect'=>route('front.surveyforms.modal.members.index', $survey->id),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['ok'=>false,'message'=>'Lỗi cập nhật: '.$e->getMessage()], 500);
        }
    }

    
}
