<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\SurveyForm;
use Illuminate\Support\Facades\DB;
use App\Models\SurveyFormDetail;
use App\Services\SurveyFormService;
use App\Models\ResidenceType;
use App\Models\ResidenceStatus;
use App\Models\SpecialCircumstance;
use App\Models\PriorityCategory;
use App\Models\Residence;
use App\Models\Person;
use App\Models\Ethnicity;
use App\Models\Religion;
use App\Models\DisabilityCategory;
use Illuminate\Validation\Rule;
use App\Services\GeoService;
use App\Models\SurveyDetailFlat;
use  Illuminate\Support\Facades\Auth;
 use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Services\AccessGuardService;
class SurveyFormIndexController extends Controller
{
    public function __construct(
        private AccessGuardService $guard,
        private SurveyFormService $deleter,
        private GeoService $geo) {}

  
   public function modalMembers(Request $request,SurveyForm $survey)
{
        
        $disCatNames = DisabilityCategory::pluck('name', 'id');

        $year = $request->query('year', SurveyForm::currentAcademicYear());
        // Lấy thẳng từ VIEW
        $details = SurveyDetailFlat::query()
        ->where('survey_id', $survey->id)
        ->where('academic_year',$year)
        ->ofYear($year)                // scope đã có sẵn trong model
       
        // Nếu cần sort từ request: ?sort=school_name&dir=asc
        ->when($sort = request('sort'), function($q) use ($sort){
            $dir = strtolower(request('dir','asc')) === 'desc' ? 'desc' : 'asc';
            if (in_array($sort, SurveyDetailFlat::sortableColumns(), true)) {
                $q->orderBy($sort, $dir);
            } else {
                $q->orderBy('detail_id'); // fallback
            }
        }, function($q){
            $q->orderBy('detail_id'); // mặc định
        })
        ->get( );
 
    // dd($year,$details);
    // Map ra đúng format $rows như trước (nhưng lấy từ VIEW)
    $rows = $details->map(function (SurveyDetailFlat $v) {
        // Ghép tên đầy đủ
        $fullName = trim(($v->ho_dem ? $v->ho_dem.' ' : '').($v->ten ?? ''));

        // Ngày sinh text
        $dobText = collect([$v->dob_day, $v->dob_month, $v->dob_year])->filter()->implode('/');

        // Cờ khuyết tật -> tên
        $disLabels = [
            'dis_VAN_DONG'  => 'Vận động',
            'dis_NGHE_NOI'  => 'Nghe–nói',
            'dis_NHIN'      => 'Nhìn',
            'dis_THAN_KINH' => 'Thần kinh',
            'dis_TRI_TUE'   => 'Trí tuệ',
            'dis_HOC_TAP'   => 'Học tập',
            'dis_TU_KY'     => 'Tự kỷ',
            'dis_KHAC'      => 'Khác',
        ];
        $disabilities = collect($disLabels)
            ->filter(fn($label, $col) => (bool)$v->{$col})
            ->values()
            ->implode(', ');

        // Địa chỉ gộp
        $geoText = collect([
            $v->village_name,
            $v->old_ward_name ?: $v->new_ward_name,
            $v->old_district_name,
            $v->old_province_name ?: $v->new_province_name,
        ])->filter()->implode(', ');

        return [
            'detail_id'       => $v->detail_id,
            'person_id'       => $v->person_id,
            'full_name'       => $fullName,
            'identity'        => $v->identity_number,
            'dob_text'        => $dobText,
            'gender'          => is_null($v->is_female) ? '—' : ($v->is_female ? 'Nữ' : 'Nam'),
            'phone'           => $v->contact_phone,
            'addressline'     => $v->addressline,
            'priority_category' => $v->priority_name,

            'res_type'        => $v->residence_type_name,
            'res_status'      => $v->residence_status_name,

            // Học tập
            'block'           => $v->education_block_code,
            'class_label'     => $v->education_grade_name,
            'school_name'     => $v->school_name,
            'school_code'     => $v->school_code,
            'studying_district' => $v->studying_district, // tên xã/phường của trường (theo VIEW)
            'grad_pt'         => $v->graduation_level_name,
            'grad_year'       => $v->graduation_year,
            'voc_level'       => $v->graduation_certificate_no,
            'voc_year'        => $v->vocational_grad_year,

            // Xoá mù chữ
            'lit_class'       => $v->lit_dang_hoc_lop,
            'lit_done'        => $v->lit_hoan_thanh_lop,
            'lit_level'       => $v->lit_tai_mu_muc,

            // Khuyết tật (1 cột)
            'disabilities'    => $disabilities ?: '—',

            // Chứng nhận / khả năng học (nếu muốn hiển thị riêng)
            'dis_cert'      => $v->dis_has_certificate ? 'Có' : 'Không',
            'dis_canlearn'  => $v->dis_can_learn ? 'Có' : 'Không',

            // Hoàn cảnh
            // Lưu ý: nếu VIEW đã có sẵn special_* thì thêm vào đây; bằng không bỏ qua.
            'special_name'  => $v->sc_name ?? null,
            'special_detail'=> $v->sc_detail ?? null,
 
 
            // Quan hệ & liên lạc
            'role'            => $v->relationship_name,
            'contact_name'    => $v->contact_name,
            'contact_phone'   => $v->contact_phone,
            'contact_note'    => $v->contact_note ?? null,

            // Địa chỉ gộp
            'geo_text'        => $geoText,

            // Tham khảo số phiếu
            'so_phieu'        => $v->so_phieu,
        ];
    });
    // dd($rows);
    return view('front.surveyforms.modals.members.index', [
        'survey' => $survey,
        'rows'   => $rows,
    ]);
}
    public function modalCreate(Request $request)
    {
        $defaults = [
            'academic_year' => \App\Models\SurveyForm::currentAcademicYear(),
            'surveyed_at'   => now()->format('Y-m-d'),
            'scheme'        => 'old', // mặc định geo "cũ"
        ];
        $resTypes = ResidenceType::orderBy('name')->get(['id','name']);
        $resStats = ResidenceStatus::orderBy('name')->get(['id','name']);
        $ethnicities = Ethnicity::orderBy('name')->get(['id','name','is_minority']);
         
        $religions   = Religion::orderBy('name')->get(['id','name']);
        return view('front.surveyforms.modals.create', compact('defaults','resTypes','resStats','ethnicities','religions'));
    }

 

    public function storeAjax(Request $request, SurveyFormService $svc)
    {

        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'create'); // trả về: user, is_admin, scheme, geo
      
        // Kiểm tra phiếu có bị khóa không
         
        $geo = $this->guard->guardRequestGeoAndLock($request, 'SURVEY');
 
        // ---- VALIDATE ----
        $rules = [
            // chung
            'scheme'            => ['required', Rule::in(['old','new'])],
            'so_phieu'          => [
                'required','string','max:50',
                // chỉ chặn trùng với phiếu chưa xóa
                Rule::unique('survey_forms','so_phieu')
                    ->where(fn($q) => $q->where('is_deleted', false)),
            ],
            'academic_year'     => ['nullable','string','max:20'],
            'surveyed_at'       => ['nullable','date'],

            // geo (bắt buộc tỉnh + xã; huyện chỉ bắt buộc khi scheme=old)
            'geo.province_id'   => ['required','integer'],
            'geo.ward_id'       => ['required','integer'],
            'geo.district_id'   => ['nullable','integer','required_if:scheme,old'],
            'geo.village_id'    => ['nullable','integer'],

            // chủ hộ
            'head_ten'          => ['required','string','max:100'],
            'head_ho_dem'       => ['nullable','string','max:150'],
            'head_dob_day'      => ['nullable','integer','between:1,31'],
            'head_dob_month'    => ['nullable','integer','between:1,12'],
            'head_dob_year'     => ['nullable','integer','between:1900,2100'],
            'is_female'         => ['nullable','boolean'],
            'head_phone'        => ['nullable','string','max:30'],
            'head_address'      => ['nullable','string','max:255'],
            'identity_number'   => ['nullable','string','max:50'],

            // cư trú
            'residence_type_id'   => ['nullable','integer','exists:residence_types,id'],
            'residence_status_id' => ['nullable','integer','exists:residence_statuses,id'],

            // dân tộc / tôn giáo
            'ethnicity_id'      => ['nullable','integer','exists:ethnicities,id'],
            'religion_id'       => ['nullable','integer','exists:religions,id'],
        ];

        $attrNames = [
            'scheme'              => 'Sơ đồ địa bàn',
            'so_phieu'            => 'Số phiếu',
            'academic_year'       => 'Năm học',
            'surveyed_at'         => 'Ngày điều tra',
            'geo.province_id'     => 'Tỉnh/Thành',
            'geo.district_id'     => 'Quận/Huyện',
            'geo.ward_id'         => 'Phường/Xã',
            'geo.village_id'      => 'Thôn/Ấp',
            'head_ten'            => 'Tên chủ hộ',
            'head_ho_dem'         => 'Họ đệm chủ hộ',
            'head_dob_day'        => 'Ngày sinh',
            'head_dob_month'      => 'Tháng sinh',
            'head_dob_year'       => 'Năm sinh',
            'is_female'           => 'Giới tính',
            'head_phone'          => 'Điện thoại',
            'head_address'        => 'Địa chỉ',
            'identity_number'     => 'CMND/CCCD',
            'residence_type_id'   => 'Loại cư trú',
            'residence_status_id' => 'Tình trạng cư trú',
            'ethnicity_id'        => 'Dân tộc',
            'religion_id'         => 'Tôn giáo',
        ];
        // Message CHUNG (fallback cho mọi field)
        $messages = [
            'required'      => ':attribute là bắt buộc.',
            'required_if'   => ':attribute là bắt buộc khi :other là :value.',
            'string'        => ':attribute phải là chuỗi.',
            'max.string'    => ':attribute không được dài quá :max ký tự.',
            'min.string'    => ':attribute phải có ít nhất :min ký tự.',
            'date'          => ':attribute không đúng định dạng ngày.',
            'in'            => ':attribute không hợp lệ.',
            'integer'       => ':attribute phải là số nguyên.',
            'boolean'       => ':attribute không hợp lệ.',
            'regex'         => ':attribute không đúng định dạng.',
            'between.numeric' => ':attribute phải từ :min đến :max.',
            'between.string'  => ':attribute phải từ :min đến :max ký tự.',
            'unique'        => ':attribute đã tồn tại.',
            'exists'        => ':attribute không tồn tại hoặc không hợp lệ.',
            'array'         => ':attribute phải là mảng.',
        ];

        // Override THEO TỪNG FIELD (cụ thể và thân thiện hơn)
        $messages += [
            // ---- Chung/đầu mục ----
            'scheme.required' => 'Bạn phải chọn Sơ đồ địa bàn.',
            'scheme.in'       => 'Sơ đồ địa bàn không hợp lệ (chỉ chấp nhận Cũ hoặc Mới).',

            // ---- Số phiếu ----
            'so_phieu.required' => 'Bạn phải nhập Số phiếu.',
            'so_phieu.string'   => 'Số phiếu phải là chuỗi.',
            'so_phieu.max'      => 'Số phiếu không được dài quá :max ký tự.',
            'so_phieu.unique'   => 'Số phiếu đã tồn tại.',

            // ---- Năm học & ngày điều tra ----
            'academic_year.string' => 'Năm học phải là chuỗi.',
            'academic_year.max'    => 'Năm học không được dài quá :max ký tự.',
            'surveyed_at.date'     => 'Ngày điều tra không đúng định dạng ngày.',

            // ---- Địa bàn (geo) ----
            'geo.province_id.required' => 'Bạn phải chọn Tỉnh/Thành.',
            'geo.province_id.integer'  => 'Tỉnh/Thành không hợp lệ.',
            'geo.district_id.required_if' => 'Bạn phải chọn Quận/Huyện khi dùng sơ đồ Cũ.',
            'geo.district_id.integer'  => 'Quận/Huyện không hợp lệ.',
            'geo.ward_id.required'     => 'Bạn phải chọn Phường/Xã.',
            'geo.ward_id.integer'      => 'Phường/Xã không hợp lệ.',
            'geo.village_id.integer'   => 'Thôn/Ấp không hợp lệ.',

            // ---- Chủ hộ: họ tên & ngày sinh ----
            'head_ten.required' => 'Bạn phải nhập Tên chủ hộ.',
            'head_ten.string'   => 'Tên chủ hộ phải là chuỗi.',
            'head_ten.max'      => 'Tên chủ hộ không được dài quá :max ký tự.',

            'head_ho_dem.string' => 'Họ đệm chủ hộ phải là chuỗi.',
            'head_ho_dem.max'    => 'Họ đệm chủ hộ không được dài quá :max ký tự.',

            'head_dob_day.integer'   => 'Ngày sinh phải là số.',
            'head_dob_day.between'   => 'Ngày sinh phải từ :min đến :max.',
            'head_dob_month.integer' => 'Tháng sinh phải là số.',
            'head_dob_month.between' => 'Tháng sinh phải từ :min đến :max.',
            'head_dob_year.integer'  => 'Năm sinh phải là số.',
            'head_dob_year.between'  => 'Năm sinh phải từ :min đến :max.',

            'is_female.boolean' => 'Giá trị Giới tính không hợp lệ.',

            // ---- Liên hệ của chủ hộ ----
            'head_phone.regex'  => 'Số điện thoại phải bắt đầu bằng 0 và gồm 10–11 chữ số.',
            'head_phone.max'    => 'Số điện thoại không được dài quá :max ký tự.',
            'head_address.string' => 'Địa chỉ phải là chuỗi.',
            'head_address.max'    => 'Địa chỉ không được dài quá :max ký tự.',

            // ---- CCCD/CMND ----
            'identity_number.regex' => 'Số CCCD phải gồm 9 hoặc 12 chữ số.',
            'identity_number.max'   => 'Số CCCD không được dài quá :max ký tự.',

            // ---- Cư trú ----
            'residence_type_id.integer' => 'Loại cư trú không hợp lệ.',
            'residence_type_id.exists'  => 'Loại cư trú không tồn tại.',
            'residence_status_id.integer' => 'Tình trạng cư trú không hợp lệ.',
            'residence_status_id.exists'  => 'Tình trạng cư trú không tồn tại.',

            // ---- Dân tộc / Tôn giáo ----
            'ethnicity_id.integer' => 'Dân tộc không hợp lệ.',
            'ethnicity_id.exists'  => 'Dân tộc không tồn tại.',
            'religion_id.integer'  => 'Tôn giáo không hợp lệ.',
            'religion_id.exists'   => 'Tôn giáo không tồn tại.',
        ];

        // Map TÊN ATTR tiếng Việt (hiển thị đẹp trong message CHUNG)
        

        $v = Validator::make($request->all(), $rules, $messages, $attrNames);

        // Cảnh báo nếu mã phiếu tồn tại nhưng đang xóa mềm
        $v->after(function ($validator) use ($request) {
            $ticket = $request->string('so_phieu')->toString();
            if ($ticket !== '') {
                $soft = SurveyForm::where('so_phieu', $ticket)->where('is_deleted', true)->first();
                if ($soft) {
                    $validator->errors()->add(
                        'so_phieu',
                        'Mã phiếu này đã tồn tại nhưng đang ở trạng thái xóa mềm (ID: '.$soft->id.'). '
                        .'Bạn có thể phục hồi phiếu để chỉnh sửa, hoặc xóa hẳn rồi tạo mới.'
                    );
                }
            }
        });

        if ($v->fails()) {
            // Nạp lại dữ liệu danh mục để render form lỗi
            $resTypes    = ResidenceType::orderBy('name')->get(['id','name']);
            $resStats    = ResidenceStatus::orderBy('name')->get(['id','name']);
            $ethnicities = Ethnicity::orderBy('name')->get(['id','name','is_minority']);
            $religions   = Religion::orderBy('name')->get(['id','name']);
            session()->flash('_old_input', $request->all()); // <-- quan trọng
            $html = view('front.surveyforms.modals.create', [
                'defaults'    => [
                    'academic_year' => $request->input('academic_year') ?: \App\Models\SurveyForm::currentAcademicYear(),
                    'surveyed_at'   => $request->input('surveyed_at') ?: now()->format('Y-m-d'),
                    'scheme'        => $request->input('scheme','old'),
                ],
                'resTypes'    => $resTypes,
                'resStats'    => $resStats,
                'ethnicities' => $ethnicities,
                'religions'   => $religions,
            ])->withErrors($v)->render();

            return response()->json(['ok'=>false,'html'=>$html], 422);
        }

        // ---- BUILD PAYLOAD GỌI SERVICE ----
        $scheme  = $request->input('scheme', 'old');
        $geo     = $request->input('geo', []);
        $ticket  = $request->string('so_phieu')->toString();

        $head = [
            'ho_dem'              => $request->input('head_ho_dem'),
            'ten'                 => $request->input('head_ten'),
            'dob_day'             => $request->input('head_dob_day'),
            'dob_month'           => $request->input('head_dob_month'),
            'dob_year'            => $request->input('head_dob_year'),
            'is_female'           => $request->boolean('is_female', null),
            'phone'               => $request->input('head_phone'),
            'addressline'         => $request->input('head_address'),
            'identity_number'     => $request->input('identity_number'),
            'residence_type_id'   => $request->input('residence_type_id'),
            'residence_status_id' => $request->input('residence_status_id'),
            'ethnicity_id'        => $request->input('ethnicity_id'),
            'religion_id'         => $request->input('religion_id'),
        ];

        $form = [
            'academic_year'  => $request->input('academic_year'),
            'surveyed_at'    => $request->input('surveyed_at'),
            'household_name' => $request->input('household_name'),
        ];

        try {
            $svc->createWithHouseholdAndHeadByGeoIds(
                $scheme, $geo, $ticket, $head, $form, addHeadToDetails: true
            );
            return response()->json(['ok' => true, 'message' => 'Đã tạo phiếu + hộ + chủ hộ.']);
        } catch (ValidationException $ve) {
            // (Trường hợp service ném ValidationException)
            $resTypes    = ResidenceType::orderBy('name')->get(['id','name']);
            $resStats    = ResidenceStatus::orderBy('name')->get(['id','name']);
            $ethnicities = Ethnicity::orderBy('name')->get(['id','name','is_minority']);
            $religions   = Religion::orderBy('name')->get(['id','name']);

            $html = view('front.surveyforms.modals.create', [
                'defaults'    => [
                    'academic_year' => $request->input('academic_year') ?: \App\Models\SurveyForm::currentAcademicYear(),
                    'surveyed_at'   => $request->input('surveyed_at') ?: now()->format('Y-m-d'),
                    'scheme'        => $request->input('scheme','old'),
                ],
                'resTypes'    => $resTypes,
                'resStats'    => $resStats,
                'ethnicities' => $ethnicities,
                'religions'   => $religions,
            ])->withErrors($ve->errors())->render();

            return response()->json(['ok'=>false,'html'=>$html], 422);
        } catch (\Throwable $e) {
            report($e);
            if (config('app.debug')) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
            return response()->json(['ok'=>false,'message'=>'Lỗi tạo phiếu.'], 500);
        }
    }

    public function modalEdit(\App\Models\SurveyForm $survey)
    {
        $resTypes    = \App\Models\ResidenceType::orderBy('name')->get(['id','name']);
        $resStats    = \App\Models\ResidenceStatus::orderBy('name')->get(['id','name']);
        $ethnicities = \App\Models\Ethnicity::orderBy('name')->get(['id','name','is_minority']);
        $religions   = \App\Models\Religion::orderBy('name')->get(['id','name']);

        $head = $survey->headPerson; // có thể null

        $defaults = [
            // survey
            'so_phieu'       => $survey->so_phieu,
            'academic_year'  => $survey->academic_year,
            'surveyed_at'    => optional($survey->surveyed_at)->toDateString(),
            'scheme'         => $survey->old_ward_id ? 'old' : 'new',
            'geo' => [
                'province_id' => $survey->old_province_id ?? $survey->new_province_id,
                'district_id' => $survey->old_district_id,
                'ward_id'     => $survey->old_ward_id ?? $survey->new_ward_id,
                'village_id'  => $survey->village_id,
            ],
            // head person
            'identity_number'   => $head?->identity_number,
            'head_ho_dem'       => $head?->ho_dem,
            'head_ten'          => $head?->ten,
            'head_dob_day'      => $head?->dob_day,
            'head_dob_month'    => $head?->dob_month,
            'head_dob_year'     => $head?->dob_year,
            'head_phone'        => $head?->phone,
            'head_address'      => $head?->addressline,
            'residence_type_id' => $head?->residence_type_id,
            'residence_status_id'=> $head?->residence_status_id,
            'ethnicity_id'      => $head?->ethnicity_id,
            'religion_id'       => $head?->religion_id,
            'is_female'         => $head?->is_female,
        ];
        
        return view('front.surveyforms.modals.edit', compact(
            'survey','defaults','resTypes','resStats','ethnicities','religions'
        ));
    }

    

    public function modalMemberCreate(\App\Models\SurveyForm $survey)
    {
        return view('front.surveyforms.modals.member_form', compact('survey'));
    }

    // === POST (AJAX) ===
    public function memberStore(Request $r, \App\Models\SurveyForm $survey)
    {
        // validate + lưu...
        // if ($r->ajax()) ...
        return response()->json(['ok'=>true, 'message'=>'Đã thêm thành viên']);
    }

    public function updateAjax(Request $request, SurveyForm $survey, SurveyFormService $svc)
    {

        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'edit'); // trả về: user, is_admin, scheme, geo

        // Kiểm tra quyền chỉnh sửa phiếu điều tra
        // $user = \Illuminate\Support\Facades\Auth::user();
        // if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'edit')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Bạn không có quyền chỉnh sửa phiếu điều tra.'
        //     ], 403);
        // }

        // Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Phiếu điều tra này đã bị khóa và không thể chỉnh sửa.'
            ], 403);
        }
        $ctx = $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
        $geo = $this->guard->guardRequestGeoAndLock($request, 'SURVEY');
        
        // ---- VALIDATE ----
        $rules = [
            // chung
            'scheme'        => ['required', Rule::in(['old','new'])],
            'so_phieu'      => [
                'required','string','max:50',
                Rule::unique('survey_forms','so_phieu')
                    ->where(fn($q) => $q->where('is_deleted', false))
                    ->ignore($survey->id, 'id'),
            ],
            'academic_year' => ['nullable','string','max:20'],
            'surveyed_at'   => ['nullable','date'],

            // geo
            'geo.province_id' => ['required','integer'],
            'geo.ward_id'     => ['required','integer'],
            'geo.district_id' => ['nullable','integer','required_if:scheme,old'],
            'geo.village_id'  => ['nullable','integer'],

            // chủ hộ
            'head_ten'       => ['required','string','max:100'],
            'head_ho_dem'    => ['nullable','string','max:150'],
            'head_dob_day'   => ['nullable','integer','between:1,31'],
            'head_dob_month' => ['nullable','integer','between:1,12'],
            'head_dob_year'  => ['nullable','integer','between:1900,2100'],
            'is_female'      => ['nullable','boolean'],
            'head_phone'     => ['nullable','string','max:30'], // (nếu muốn ràng regex 0\d{9,10} thì thêm vào)
            'head_address'   => ['nullable','string','max:255'],
            'identity_number'=> ['nullable','string','max:50'],

            // cư trú
            'residence_type_id'   => ['nullable','integer','exists:residence_types,id'],
            'residence_status_id' => ['nullable','integer','exists:residence_statuses,id'],

            // dân tộc / tôn giáo
            'ethnicity_id'  => ['nullable','integer','exists:ethnicities,id'],
            'religion_id'   => ['nullable','integer','exists:religions,id'],
        ];

        $attrNames = [
            'scheme'              => 'Sơ đồ địa bàn',
            'so_phieu'            => 'Số phiếu',
            'academic_year'       => 'Năm học',
            'surveyed_at'         => 'Ngày điều tra',
            'geo.province_id'     => 'Tỉnh/Thành',
            'geo.district_id'     => 'Quận/Huyện',
            'geo.ward_id'         => 'Phường/Xã',
            'geo.village_id'      => 'Thôn/Ấp',
            'head_ten'            => 'Tên chủ hộ',
            'head_ho_dem'         => 'Họ đệm chủ hộ',
            'head_dob_day'        => 'Ngày sinh',
            'head_dob_month'      => 'Tháng sinh',
            'head_dob_year'       => 'Năm sinh',
            'is_female'           => 'Giới tính',
            'head_phone'          => 'Điện thoại',
            'head_address'        => 'Địa chỉ',
            'identity_number'     => 'CMND/CCCD',
            'residence_type_id'   => 'Loại cư trú',
            'residence_status_id' => 'Tình trạng cư trú',
            'ethnicity_id'        => 'Dân tộc',
            'religion_id'         => 'Tôn giáo',
        ];

        $messages = [
            'required'      => ':attribute là bắt buộc.',
            'required_if'   => ':attribute là bắt buộc khi :other là :value.',
            'string'        => ':attribute phải là chuỗi.',
            'max.string'    => ':attribute không được dài quá :max ký tự.',
            'min.string'    => ':attribute phải có ít nhất :min ký tự.',
            'date'          => ':attribute không đúng định dạng ngày.',
            'in'            => ':attribute không hợp lệ.',
            'integer'       => ':attribute phải là số nguyên.',
            'boolean'       => ':attribute không hợp lệ.',
            'regex'         => ':attribute không đúng định dạng.',
            'between.numeric' => ':attribute phải từ :min đến :max.',
            'between.string'  => ':attribute phải từ :min đến :max ký tự.',
            'unique'        => ':attribute đã tồn tại.',
            'exists'        => ':attribute không tồn tại hoặc không hợp lệ.',
            'array'         => ':attribute phải là mảng.',
        ] + [
            'scheme.required' => 'Bạn phải chọn Sơ đồ địa bàn.',
            'scheme.in'       => 'Sơ đồ địa bàn không hợp lệ (chỉ chấp nhận Cũ hoặc Mới).',

            'so_phieu.required' => 'Bạn phải nhập Số phiếu.',
            'so_phieu.string'   => 'Số phiếu phải là chuỗi.',
            'so_phieu.max'      => 'Số phiếu không được dài quá :max ký tự.',
            'so_phieu.unique'   => 'Số phiếu đã tồn tại.',

            'academic_year.string' => 'Năm học phải là chuỗi.',
            'academic_year.max'    => 'Năm học không được dài quá :max ký tự.',
            'surveyed_at.date'     => 'Ngày điều tra không đúng định dạng ngày.',

            'geo.province_id.required' => 'Bạn phải chọn Tỉnh/Thành.',
            'geo.province_id.integer'  => 'Tỉnh/Thành không hợp lệ.',
            'geo.district_id.required_if' => 'Bạn phải chọn Quận/Huyện khi dùng sơ đồ Cũ.',
            'geo.district_id.integer'  => 'Quận/Huyện không hợp lệ.',
            'geo.ward_id.required'     => 'Bạn phải chọn Phường/Xã.',
            'geo.ward_id.integer'      => 'Phường/Xã không hợp lệ.',
            'geo.village_id.integer'   => 'Thôn/Ấp không hợp lệ.',

            'head_ten.required' => 'Bạn phải nhập Tên chủ hộ.',
            'head_ten.string'   => 'Tên chủ hộ phải là chuỗi.',
            'head_ten.max'      => 'Tên chủ hộ không được dài quá :max ký tự.',
            'head_ho_dem.string'=> 'Họ đệm chủ hộ phải là chuỗi.',
            'head_ho_dem.max'   => 'Họ đệm chủ hộ không được dài quá :max ký tự.',
            'head_dob_day.integer'   => 'Ngày sinh phải là số.',
            'head_dob_day.between'   => 'Ngày sinh phải từ :min đến :max.',
            'head_dob_month.integer' => 'Tháng sinh phải là số.',
            'head_dob_month.between' => 'Tháng sinh phải từ :min đến :max.',
            'head_dob_year.integer'  => 'Năm sinh phải là số.',
            'head_dob_year.between'  => 'Năm sinh phải từ :min đến :max.',
            'is_female.boolean'      => 'Giá trị Giới tính không hợp lệ.',

            'head_phone.max'    => 'Số điện thoại không được dài quá :max ký tự.',
            'head_address.string' => 'Địa chỉ phải là chuỗi.',
            'head_address.max'    => 'Địa chỉ không được dài quá :max ký tự.',

            'identity_number.max'   => 'Số CCCD không được dài quá :max ký tự.',

            'residence_type_id.integer' => 'Loại cư trú không hợp lệ.',
            'residence_type_id.exists'  => 'Loại cư trú không tồn tại.',
            'residence_status_id.integer' => 'Tình trạng cư trú không hợp lệ.',
            'residence_status_id.exists'  => 'Tình trạng cư trú không tồn tại.',

            'ethnicity_id.integer' => 'Dân tộc không hợp lệ.',
            'ethnicity_id.exists'  => 'Dân tộc không tồn tại.',
            'religion_id.integer'  => 'Tôn giáo không hợp lệ.',
            'religion_id.exists'   => 'Tôn giáo không tồn tại.',
        ];

        $v = Validator::make($request->all(), $rules, $messages, $attrNames);

        // cảnh báo nếu trùng với 1 phiếu đang xóa mềm khác
        $v->after(function ($validator) use ($request, $survey) {
            $ticket = $request->string('so_phieu')->toString();
            if ($ticket !== '') {
                $soft = SurveyForm::where('so_phieu', $ticket)
                        ->where('is_deleted', true)
                        ->where('id', '<>', $survey->id)
                        ->first();
                if ($soft) {
                    $validator->errors()->add(
                        'so_phieu',
                        'Mã phiếu này đã tồn tại nhưng đang ở trạng thái xóa mềm (ID: '.$soft->id.'). '
                        .'Bạn có thể phục hồi phiếu để chỉnh sửa, hoặc xóa hẳn rồi sử dụng mã này.'
                    );
                }
            }
        });

        if ($v->fails()) {
            // nạp lại danh mục và render form edit kèm lỗi
            $resTypes    = ResidenceType::orderBy('name')->get(['id','name']);
            $resStats    = ResidenceStatus::orderBy('name')->get(['id','name']);
            $ethnicities = Ethnicity::orderBy('name')->get(['id','name','is_minority']);
            $religions   = Religion::orderBy('name')->get(['id','name']);

            session()->flash('_old_input', $request->all());

            $html = view('front.surveyforms.modals.edit', [
                'survey'     => $survey,
                'defaults'   => [
                    'academic_year' => $attrNames['academic_year'],
                    'surveyed_at'   => optional($survey->surveyed_at)->toDateString(),
                    // nếu cần tự động chọn scheme theo dữ liệu hiện tại:
                    'scheme'        => $request->input('scheme', $survey->old_ward_id ? 'old' : 'new'),
                ],
                'resTypes'    => $resTypes,
                'resStats'    => $resStats,
                'ethnicities' => $ethnicities,
                'religions'   => $religions,
            ])->withErrors($v)->render();

            return response()->json(['ok'=>false,'html'=>$html], 422);
        }

        // ---- BUILD PAYLOAD ----
        $scheme = $request->input('scheme', 'old');
        $geo    = $request->input('geo', []);
        $ticket = $request->string('so_phieu')->toString();

        $head = [
            'ho_dem'              => $request->input('head_ho_dem'),
            'ten'                 => $request->input('head_ten'),
            'dob_day'             => $request->input('head_dob_day'),
            'dob_month'           => $request->input('head_dob_month'),
            'dob_year'            => $request->input('head_dob_year'),
            'is_female'           => $request->boolean('is_female', null),
            'phone'               => $request->input('head_phone'),
            'addressline'         => $request->input('head_address'),
            'identity_number'     => $request->input('identity_number'),
            'residence_type_id'   => $request->input('residence_type_id'),
            'residence_status_id' => $request->input('residence_status_id'),
            'ethnicity_id'        => $request->input('ethnicity_id'),
            'religion_id'         => $request->input('religion_id'),
            
        ];

        $form = [
            'academic_year'  => $request->input('academic_year'),
            'surveyed_at'    => $request->input('surveyed_at'),
            'household_name' => $request->input('household_name'),
        ];

        try {
            // Service cần có hàm cập nhật; đổi tên nếu bạn đã đặt khác
            $svc->updateWithHouseholdAndHeadByGeoIds(
                $survey, $scheme, $geo, $ticket, $head, $form
            );

            return response()->json(['ok' => true, 'message' => 'Đã cập nhật phiếu.']);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            $resTypes    = ResidenceType::orderBy('name')->get(['id','name']);
            $resStats    = ResidenceStatus::orderBy('name')->get(['id','name']);
            $ethnicities = Ethnicity::orderBy('name')->get(['id','name','is_minority']);
            $religions   = Religion::orderBy('name')->get(['id','name']);

            $html = view('front.surveyforms.modals.edit', [
                'survey'     => $survey,
                'defaults'   => [
                    'academic_year' => $survey->academic_year,
                    'surveyed_at'   => optional($survey->surveyed_at)->toDateString(),
                    'scheme'        => $request->input('scheme', $survey->old_ward_id ? 'old' : 'new'),
                ],
                'resTypes'    => $resTypes,
                'resStats'    => $resStats,
                'ethnicities' => $ethnicities,
                'religions'   => $religions,
            ])->withErrors($ve->errors())->render();

            return response()->json(['ok'=>false,'html'=>$html], 422);
        } catch (\Throwable $e) {
            report($e);
            if (config('app.debug')) {
                return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
            }
            return response()->json(['ok'=>false,'message'=>'Lỗi cập nhật phiếu.'], 500);
        }
    }
    public function splitAjax(Request $r, \App\Models\SurveyForm $survey)
    {
        // validate + tách hộ...
        return response()->json(['ok'=>true]);
    }

   public function index(Request $r)
    {
         
        $ctx = $this->guard->enforce($r, 'quan_ly_phieu_dieu_tra', 'view');
        // dd($r->all());
        $isAdmin = $ctx['isAdmin'];


        // Năm học
        $year    = $r->exists('year') ? (string)$r->input('year') : \App\Models\SurveyForm::currentAcademicYear();
        $soPhieu = $r->string('so_phieu')->toString();
        $q       = $r->string('q')->toString();
      
        // Scheme + geo (sau khi merge)
        $scheme = $r->input('scheme', 'old');
        $geo    = (array) $r->input('geo', []);
        $gProv  = (int)($geo['province_id'] ?? 0);
        $gDist  = (int)($geo['district_id'] ?? 0);
        $gWard  = (int)($geo['ward_id']     ?? 0);
        $gVill  = (int)($geo['village_id']  ?? 0);

        $sf  = (new \App\Models\SurveyForm)->getTable();
        $p   = (new \App\Models\Person)->getTable();
        $sfd = (new \App\Models\SurveyFormDetail)->getTable();
        $rt  = (new \App\Models\ResidenceType)->getTable();
        $rs  = (new \App\Models\ResidenceStatus)->getTable();

        // Đếm thành viên theo năm (nếu không phải 'all')
        $countsSql = \DB::table($sfd)
            ->selectRaw('survey_form_id, COUNT(*) as member_count')
            ->where('is_deleted', false)
            ->when($year !== '' && $year !== 'all', fn($q) => $q->where('academic_year', $year))
            ->groupBy('survey_form_id');
        
        $query = \DB::table($sf.' as sf')
            ->leftJoin($p.' as hp', 'hp.id', '=', 'sf.head_person_id')
            ->leftJoin($rt.' as rt', 'rt.id', '=', 'hp.residence_type_id')
            ->leftJoin($rs.' as rs', 'rs.id', '=', 'hp.residence_status_id')
            ->leftJoin('geo_units as op','op.id','=','sf.old_province_id')
            ->leftJoin('geo_units as od','od.id','=','sf.old_district_id')
            ->leftJoin('geo_units as ow','ow.id','=','sf.old_ward_id')
            ->leftJoin('geo_units as vil','vil.id','=','sf.village_id')
            ->leftJoin('households as hh', 'hh.id', '=', 'sf.household_id')
            ->leftJoin('geo_units as np','np.id','=','sf.new_province_id')
            ->leftJoin('geo_units as nw','nw.id','=','sf.new_ward_id')
            ->leftJoinSub($countsSql, 'sfc', 'sfc.survey_form_id', '=', 'sf.id')
            ->where('sf.is_deleted', false)

             

            // Lọc số phiếu & tên chủ hộ
            ->when($soPhieu, fn($q) => $q->where('sf.so_phieu', 'like', $soPhieu.'%'))
            ->when($q, function ($w) use ($q) {
                $like = '%'.$q.'%';
                $w->where(function ($x) use ($like) {
                    $x->where('hp.ho_dem', 'like', $like)
                    ->orWhere('hp.ten', 'like', $like)
                    ->orWhereRaw("CONCAT(TRIM(COALESCE(hp.ho_dem, '')), ' ', COALESCE(hp.ten, '')) LIKE ?", [$like]);
                });
            })

            // GEO filter theo scheme hiện tại (đã được ép nếu non-admin)
            ->when($scheme === 'old' && $gProv, fn($qq) => $qq->where('sf.old_province_id', $gProv))
            ->when($scheme === 'old' && $gDist, fn($qq) => $qq->where('sf.old_district_id', $gDist))
            ->when($scheme === 'old' && $gWard, fn($qq) => $qq->where('sf.old_ward_id',     $gWard))
            ->when($scheme === 'new' && $gProv, fn($qq) => $qq->where('sf.new_province_id', $gProv))
            ->when($scheme === 'new' && $gWard, fn($qq) => $qq->where('sf.new_ward_id',     $gWard))
            ->when($gVill, fn($qq) => $qq->where('sf.village_id', $gVill))

            // Chốt chặn an toàn lần nữa cho non-admin (kể cả khi thiếu tham số lọc)
            ->when(!$isAdmin && ($user->province_id ?? null), fn($qq) => $qq->where('sf.new_province_id', (int)$user->province_id))
            ->when(!$isAdmin && ($user->ward_id ?? null),     fn($qq) => $qq->where('sf.new_ward_id',     (int)$user->ward_id))

            ->selectRaw('
                sf.id, sf.so_phieu, sf.academic_year,
                COALESCE(sf.surveyed_at, sf.created_at) as surveyed_at,
                hp.ho_dem as hd, hp.ten as ht,
                hp.phone as head_phone,

                hp.dob_day   as hd_day,
                hp.dob_month as hd_month,
                hp.dob_year  as hd_year,
                hp.is_female as head_is_female,

                rt.name as residence_type_name,
                rs.name as residence_status_name,

                op.name  as old_province_name,
                od.name  as old_district_name,
                ow.name  as old_ward_name,
                vil.name as village_name,

                np.name as new_province_name,
                nw.name as new_ward_name,

                COALESCE(hh.addressline, hp.addressline) as contact_addressline,

                COALESCE(sfc.member_count, 0) as member_count
            ')
            ->orderBy('sf.so_phieu');

        $paginator   = $query->paginate((int)$r->integer('per_page', 30))->withQueryString();
        $totalForms  = $paginator->total();

        $rows = $paginator->through(function ($row) {
            $d = $row->surveyed_at ? \Illuminate\Support\Carbon::parse($row->surveyed_at) : null;

            $dobParts = array_filter([$row->hd_day ?? null, $row->hd_month ?? null, $row->hd_year ?? null], fn($v) => $v !== null && $v !== '');
            $headDob  = $dobParts ? implode('/', $dobParts) : null;

            $addrOld = collect([$row->village_name, $row->old_ward_name, $row->old_district_name, $row->old_province_name])
                        ->filter()->implode(', ');

            $addrNew = collect([$row->village_name, $row->new_ward_name, $row->new_province_name])
                        ->filter()->implode(', ');

            return [
                'id'            => $row->id,
                'so_phieu'      => $row->so_phieu,
                'academic_year' => $row->academic_year,
                'surveyed_at'   => $d?->format('d/m/Y') ?? null,
                'chu_ho'        => trim(($row->hd ?? '').' '.($row->ht ?? '')),
                'head_phone'    => $row->head_phone,
                'head_gender'   => is_null($row->head_is_female ?? null) ? null : ($row->head_is_female ? 'Nữ' : 'Nam'),
                'head_dob'      => $headDob,

                'res_type'      => $row->residence_type_name,
                'res_status'    => $row->residence_status_name,
                'addressline'   => collect([$row->village_name, $row->old_ward_name, $row->old_district_name, $row->old_province_name])
                                    ->filter(fn($x)=>filled($x))->implode(', '),

                'member_count'  => (int)$row->member_count,

                'address_old'   => $addrOld,
                'address_new'   => $addrNew,
                'contact_addr'  => $row->contact_addressline,
            ];
        });

        return view('front.surveyforms.index', [
            'rows'       => $rows,
            'year'       => $year,
            'soPhieu'    => $soPhieu,
            'q'          => $q,
            'totalForms' => $totalForms,
        ]);
    }


    public function destroy(Request $request, SurveyForm $survey)
    {
      
        // 1) Quyền xóa
        $user = Auth::user();
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'delete');
        $isAdmin = $ctx['isAdmin'];

        // 2) Kiểm tra phiếu có bị khóa không
        if (!$survey->is_active) {
            $payload = ['ok' => false, 'message' => 'Phiếu điều tra này đã bị khóa và không thể xóa.'];
            return $request->wantsJson()
                ? response()->json($payload, 403)
                : back()->withErrors(['status' => $payload['message']]);
        }

        // 2) Giới hạn phạm vi cho non-admin (không tin vào request params)

        $ctx = $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
 
        

        // 3) Xóa (soft-delete) trong transaction
        try {
            DB::beginTransaction();
            // deleteOne() nên tự xử lý: đánh dấu sf.is_deleted = true,
            // soft-delete/đánh dấu detail liên quan, cập nhật people liên quan (nếu có).
            $res = $this->deleter->deleteOne($survey);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $payload = ['ok' => false, 'message' => 'Không xóa được phiếu.', 'error' => $e->getMessage()];
            return $request->wantsJson()
                ? response()->json($payload, 500)
                : back()->withErrors(['status' => $payload['message']]);
        }

        $msg = sprintf(
            'Đã xóa mềm 1 phiếu (+%d chi tiết, +%d/+%d People).',
            $res['details'] ?? 0, $res['people_id'] ?? 0, $res['people_code'] ?? 0
        );

        // 4) Trả về theo kỳ vọng
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }
        return back()->with('status', $msg);
    }
    public function export()
    {
        
    }
    // public function bulkDelete(Request $r)
    // {
        
    //     // 0) Quyền xóa
    //     $user = \Illuminate\Support\Facades\Auth::user();
    //     $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'delete');

    //     // 2) Đọc params sau khi merge
    //     $ids = collect($r->input('ids', []))
    //         ->filter(fn($v) => is_numeric($v))
    //         ->map(fn($v) => (int) $v)
    //         ->unique()->values()->all();

    //     $scope   = $r->string('scope')->toString() ?: 'filtered';
    //     $year    = $r->string('year')->toString();
    //     $soPhieu = $r->string('so_phieu')->toString();
    //     $q       = $r->string('q')->toString();

    //     $scheme = $r->input('scheme','old');
    //     $geo    = (array) $r->input('geo',[]);
    //     $gProv  = (int)($geo['province_id'] ?? 0);
    //     $gDist  = (int)($geo['district_id'] ?? 0);
    //     $gWard  = (int)($geo['ward_id']     ?? 0);
    //     $gVill  = (int)($geo['village_id']  ?? 0);

    //     // 3) Xóa theo ids (ưu tiên nếu có)
    //     if (!empty($ids)) {
    //         // Chỉ giữ các id nằm trong phạm vi cho non-admin
    //         // $allowedIds = \App\Models\SurveyForm::query()
    //         //     ->whereIn('id', $ids)
    //         //     ->where('is_deleted', false)
    //         //     ->when(!$isAdmin && ($user->province_id ?? null), fn($q) => $q->where('new_province_id', (int)$user->province_id))
    //         //     ->when(!$isAdmin && ($user->ward_id ?? null),     fn($q) => $q->where('new_ward_id',     (int)$user->ward_id))
    //         //     ->pluck('id')->all();

    //         // $skipped = array_values(array_diff($ids, $allowedIds));
    //         // if (empty($allowedIds)) {
    //         //     $msg = 'Không có phiếu nào hợp lệ trong phạm vi để xóa.';
    //         //     return $r->wantsJson()
    //         //         ? response()->json(['ok' => false, 'message' => $msg], 422)
    //         //         : redirect()->route('front.surveyforms.index')->withErrors(['status' => $msg]);
    //         // }

    //         $res = $this->deleter->deleteByIds($allowedIds);

    //         $msg = sprintf(
    //             'Đã xóa mềm %d phiếu (+%d chi tiết, +%d/+%d People).%s',
    //             $res['forms'] ?? 0, $res['details'] ?? 0, $res['people_id'] ?? 0, $res['people_code'] ?? 0,
    //             $skipped ? (' Bỏ qua ngoài phạm vi: '.count($skipped).' phiếu.') : ''
    //         );

    //         return $r->wantsJson()
    //             ? response()->json(['ok' => true, 'message' => $msg, 'skipped' => $skipped])
    //             : redirect()->route('front.surveyforms.index')->with('status', $msg);
    //     }

    //     // 4) Không có ids -> xóa theo scope lọc
    //     $base = \App\Models\SurveyForm::query()->where('is_deleted', false);

    //     if ($scope === 'filtered') {
    //         // Lọc theo năm học của PHIẾU (nếu bảng có cột academic_year)
    //         if ($year !== '' && $year !== 'all') {
    //             $base->where('academic_year', $year);
    //         }

    //         if ($soPhieu !== '') {
    //             $base->where('so_phieu','like',$soPhieu.'%');
    //         }

    //         if ($q !== '') {
    //             $like = '%'.$q.'%';
    //             $base->whereHas('headPerson', fn($pp) =>
    //                 $pp->where('ho_dem','like',$like)->orWhere('ten','like',$like)
    //             );
    //         }

    //         // Geo filter từ form (đã bị ép nếu non-admin)
    //         $base->when($scheme==='old' && $gProv, fn($qq)=>$qq->where('old_province_id',$gProv))
    //             ->when($scheme==='old' && $gDist, fn($qq)=>$qq->where('old_district_id',$gDist))
    //             ->when($scheme==='old' && $gWard, fn($qq)=>$qq->where('old_ward_id',$gWard))
    //             ->when($scheme==='new' && $gProv, fn($qq)=>$qq->where('new_province_id',$gProv))
    //             ->when($scheme==='new' && $gWard, fn($qq)=>$qq->where('new_ward_id',$gWard))
    //             ->when($gVill, fn($qq)=>$qq->where('village_id',$gVill));
    //     }

    //     // Chốt phạm vi bắt buộc cho non-admin (kể cả scope=all)
    //     $base->when(!$isAdmin && ($user->province_id ?? null), fn($q) => $q->where('new_province_id', (int)$user->province_id))
    //         ->when(!$isAdmin && ($user->ward_id ?? null),     fn($q) => $q->where('new_ward_id',     (int)$user->ward_id));

    //     // (Tuỳ chọn) Nếu muốn chắc chắn không xóa nhầm do filter quá rộng,
    //     // có thể log sơ bộ số phiếu sẽ xóa:
    //     // $toDeleteCount = (clone $base)->count();

    //     // Xóa theo query
    //     $res = $this->deleter->deleteByQuery($base);

    //     $msg = sprintf(
    //         'Đã xóa mềm %d phiếu (+%d chi tiết, +%d/+%d People).',
    //         $res['forms'] ?? 0, $res['details'] ?? 0, $res['people_id'] ?? 0, $res['people_code'] ?? 0
    //     );

    //     return $r->wantsJson()
    //         ? response()->json(['ok' => true, 'message' => $msg])
    //         : redirect()->route('front.surveyforms.index')->with('status', $msg);
    // }
    public function bulkDelete(Request $r)
    {
        // 0) Quyền + ép scheme/new + merge geo cho non-admin
        $ctx = $this->guard->enforce($r, 'quan_ly_phieu_dieu_tra', 'delete'); // trả về: user, is_admin, scheme, geo
        $isAdmin = (bool) $ctx['isAdmin'];
        $geo     = (array) $ctx['geo']; // ['province_id','ward_id','village_id']

        // 1) Đọc tham số
        $ids     = $this->normalizeIds($r->input('ids')); // mảng int (có thể nhận "1,2,3" hoặc ["1","2"])
        $scope   = $r->string('scope')->toString() ?: 'filtered';
        $year    = $r->string('year')->toString();
        $soPhieu = $r->string('so_phieu')->toString();
        $q       = $r->string('q')->toString();
        $scheme  = $r->input('scheme', 'new'); // enforce trong enforce()

        // 2) Nếu có IDs -> xóa theo danh sách (kiểm tra phạm vi + lock từng record)
        if (!empty($ids)) {
            $forms   = SurveyForm::query()
                        ->whereIn('id', $ids)
                        ->where('is_deleted', false)
                        ->get();

            $notFound = array_values(array_diff($ids, $forms->pluck('id')->all()));
            $allowedIds = [];
            $skipped    = []; // [['id'=>..., 'status'=>..., 'reason'=>...]]

            foreach ($notFound as $miss) {
                $skipped[] = ['id'=>$miss,'status'=>404,'reason'=>'Không tìm thấy.'];
            }

            foreach ($forms as $form) {
                try {
                    // Kiểm tra record có thuộc phạm vi của user + không bị khóa scope SURVEY
                    $this->guard->guardRecordGeoAndLock($r, $form, 'SURVEY');
                    $allowedIds[] = $form->id;
                } catch (HttpException $e) {
                    $skipped[] = ['id'=>$form->id,'status'=>$e->getStatusCode(),'reason'=>$e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id'=>$form->id,'status'=>500,'reason'=>'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($allowedIds)) {
                $msg = 'Không có phiếu nào hợp lệ trong phạm vi để xóa.';
                return $r->wantsJson()
                    ? response()->json(['ok'=>false,'message'=>$msg,'skipped'=>$skipped], 422)
                    : redirect()->route('front.surveyforms.index')->withErrors(['status'=>$msg]);
            }

            // 3) Xóa theo danh sách hợp lệ
            $res = $this->deleter->deleteByIds($allowedIds);

            $msg = sprintf(
                'Đã xóa mềm %d phiếu (+%d chi tiết, +%d/+%d People).%s',
                $res['forms'] ?? 0, $res['details'] ?? 0, $res['people_id'] ?? 0, $res['people_code'] ?? 0,
                $skipped ? (' Bỏ qua: '.count($skipped).' phiếu.') : ''
            );

            return $r->wantsJson()
                ? response()->json(['ok'=>true,'message'=>$msg,'deleted_ids'=>$allowedIds,'skipped'=>$skipped])
                : redirect()->route('front.surveyforms.index')->with('status', $msg);
        }

        // 4) Không có IDs -> xóa theo bộ lọc hiện tại
        //    Trước khi xóa theo filter, kiểm tra lock trên geo filter (một lần)
        $this->guard->assertUnlockedOrAbort('SURVEY', $geo, 423, 'Địa bàn đang bị khóa, không thể xóa.');

        $base = SurveyForm::query()->where('is_deleted', false);

        if ($scope === 'village') {
            $villageId = (int)($geo['village_id'] ?? 0);
            if ($villageId <= 0) {
                $msg = 'Vui lòng chọn Thôn/Ấp trước khi thực hiện xóa Thôn/Ấp.';
                return $r->wantsJson()
                    ? response()->json(['ok' => false, 'message' => $msg], 422)
                    : redirect()->route('front.surveyforms.index')->withErrors(['status' => $msg]);
            }
            $base->where('village_id', $villageId);
            if ($year !== '' && $year !== 'all') {
                $base->where('academic_year', $year);
            }
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $base->where('old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $base->where('old_ward_id',     $geo['ward_id']);
            } else { // new
                if (!empty($geo['province_id'])) $base->where('new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $base->where('new_ward_id',     $geo['ward_id']);
            }
        } elseif ($scope === 'filtered') {
            if ($year !== '' && $year !== 'all') {
                $base->where('academic_year', $year);
            }
            if ($soPhieu !== '') {
                $base->where('so_phieu', 'like', $soPhieu.'%');
            }
            if ($q !== '') {
                $like = '%'.$q.'%';
                $base->whereHas('headPerson', fn($pp) =>
                    $pp->where('ho_dem', 'like', $like)
                       ->orWhere('ten', 'like', $like)
                       ->orWhereRaw("CONCAT(TRIM(COALESCE(ho_dem, '')), ' ', COALESCE(ten, '')) LIKE ?", [$like])
                );
            }

            // Áp geo filter (scheme new/old) từ form + đã enforce cho non-admin
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $base->where('old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $base->where('old_ward_id',     $geo['ward_id']);
                if (!empty($geo['village_id']))  $base->where('village_id',      $geo['village_id']);
            } else { // new
                if (!empty($geo['province_id'])) $base->where('new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $base->where('new_ward_id',     $geo['ward_id']);
                if (!empty($geo['village_id']))  $base->where('village_id',      $geo['village_id']);
            }
        }

        // 5) Chốt phạm vi bắt buộc cho non-admin (dù scope=all) – đã enforce geo ở trên,
        //    nhưng để chắc ăn, apply lại:
        $this->guard->applyGeoToQuery($base, $geo);

        // 6) Xóa theo query
        $res = $this->deleter->deleteByQuery($base);

        $msg = sprintf(
            'Đã xóa mềm %d phiếu (+%d chi tiết, +%d/+%d People).',
            $res['forms'] ?? 0, $res['details'] ?? 0, $res['people_id'] ?? 0, $res['people_code'] ?? 0
        );

        return $r->wantsJson()
            ? response()->json(['ok'=>true,'message'=>$msg])
            : redirect()->route('front.surveyforms.index')->with('status', $msg);
    }

    /** Chuẩn hoá ids: "1,2,3" | ["1","2"] -> [1,2,3] */
    private function normalizeIds($ids): array
    {
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($ids)) return [];

        return collect($ids)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }
    /**
     * Tải phiếu điều tra mẫu
     */
    public function downloadTemplate()
    {
        $filePath = resource_path('excel/phieudieutra_1_mat.xls');
        
        if (!file_exists($filePath)) {
            abort(404, 'File mẫu không tồn tại');
        }
        
        return response()->download($filePath, 'phieudieutra_1_mat.xls');
    }

} 