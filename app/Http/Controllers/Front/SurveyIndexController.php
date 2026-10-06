<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\DisabilityCategory;
use App\Models\PersonEducation;
use App\Models\EducationGrade;
use App\Models\EducationBlock;
use App\Models\VocationalLevel;
use App\Models\GraduationLevel;
use App\Models\Residence;
use App\Models\ResidenceType;
use App\Models\ResidenceStatus;
use App\Models\Ethnicity;
use App\Models\Religion;
use App\Models\SpecialCircumstance;
use App\Models\PriorityCategory;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests\StoreSurveyFormRequest;
use App\Services\GeoService;
use App\Services\SurveyImportOrchestrator;
use App\Models\School;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use  Illuminate\Support\Facades\Auth;
use App\Services\SurveyFilterService;
use App\Models\SurveyDetailFlat as SDF;
use App\Services\AccessGuardService;
use App\Services\UpdateGraduationLevelService;
use App\Services\UpdateNineTenSessionService;

class SurveyIndexController extends Controller
{
     public function __construct(
            private AccessGuardService $guard,
        private SurveyFilterService $filterService) {}

    public function create()
    {
        // Kiểm tra quyền tạo đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'create')) {
            abort(403, 'Bạn không có quyền tạo đối tượng điều tra mới.');
        }

        $defaultYear = SurveyForm::currentAcademicYear();
        $blocks      = EducationBlock::orderBy('id')->get(['id','name']);
        $gradLevels  = GraduationLevel::orderBy('id')->get(['id','name']);
        $vocLevels   = VocationalLevel::orderBy('id')->get(['id','name']);
        $grades = EducationGrade::where('is_active', true)
        ->orderBy('id')
        ->get(['id','code','name']);
        $ethnicities = Ethnicity::orderBy('id')->get(['id','code','name']);
        $religions   = Religion::orderBy('id')->get(['id','code','name']);
        $specialCircumstances = SpecialCircumstance::orderBy('id')->get(['id','name']);
        $priorityCategories = PriorityCategory::orderBy('id')->get(['id','name']);
        $residenceTypes = ResidenceType::orderBy('id')->get(['id','name']);
        $residenceStatuses = ResidenceStatus::orderBy('id')->get(['id','name']);

        return view('front.surveys.create', [
            'defaultYear' => $defaultYear,
            'year'        => request('year', $defaultYear),
            // giữ lại input cũ khi back
            'scheme'      => old('scheme', request('scheme','old')),
               // NEW: options for selects
            'blocks'     => $blocks,
            'gradLevels' => $gradLevels,
            'vocLevels'  => $vocLevels,
            'grades'     => $grades,
            'ethnicities' => $ethnicities,
            'religions'  => $religions,
            'specials' => $specialCircumstances,
            'priorities'   => $priorityCategories,
            'residenceTypes' => $residenceTypes,
            'residenceStatuses' => $residenceStatuses,
        ]);
    }

    public function store(Request $req, SurveyImportOrchestrator $importer)
    {
        // Kiểm tra quyền tạo đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'create')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền tạo đối tượng điều tra mới.'
            ], 403);
        }

        // 1) Validate cơ bản cho meta
        $scheme = $req->input('scheme', 'old');
        // dd($req->all());
        $rules = [
            'academic_year'        => ['required','regex:/^\d{4}-\d{4}$/'],
            'scheme'               => ['required', Rule::in(['old','new'])],
            'geo.province_id'      => ['required'],
            'geo.ward_id'          => ['required'],
        ];
        if ($scheme === 'old') {
            $rules['geo.district_id'] = ['required'];
        }

        $validated = $req->validate($rules);

        // 2) Lấy rows JSON (từ <textarea name="rows"> mà Alpine đã nhét 1 dòng vào)
        $rows = $req->input('rows', '[]');
        if (is_string($rows)) {
            $rows = json_decode($rows, true) ?: [];
        }
        if (!is_array($rows) || !count($rows)) {
            return back()->withInput()->withErrors(['rows' => 'Không có dữ liệu dòng để lưu.']);
        }

        // 3) Ghép thêm các trường select ở form (education…, school…) vào ROW
        //    – để pipeline Education đọc được.
        $edu = $req->input('education', []); // ['school_id','school_code','school_name','education_block_id',...]
        $rows = array_map(function(array $r) use ($edu) {

            // GHÉP SCHOOL
            if (!empty($edu['school_id'])) {
                $r['school_id'] = (int) $edu['school_id'];
            }
            // Ưu tiên mirror code/name từ form; nếu thiếu thì tự lấy theo school_id
            $r['school_code'] = $edu['school_code'] ?? ($r['school_code'] ?? null);
            $r['school_name'] = $edu['school_name'] ?? ($r['school_name'] ?? null);
            
            if ($r['school_id'] ?? null) {
                $sch = School::find($r['school_id']);
                
                    
                    if ($sch) {
                        $isOutside = str_starts_with(strtoupper((string)$sch->code), 'NGOAI_TINH');
                        if ($isOutside) {
                            $r['school_code'] = $sch->code;
                            $r['school_name'] = !empty($edu['school_name']) ? trim($edu['school_name']) : $sch->name;
                            $r['studying_district'] = !empty($edu['studying_district']) ? trim($edu['studying_district']) : '';
                        } else {
                            $r['school_code'] = $r['school_code'] ?: $sch->code;
                            $r['school_name'] = $r['school_name'] ?: $sch->name;
                            // 👇 LẤY TÊN XÃ CỦA TRƯỜNG -> GÁN VÀO studying_district
                            $r['studying_district'] = $sch->wardNew?->name
                                         ?? $sch->ward_new?->name      // nếu bạn đặt snake_case
                                         ?? '';
                        }
                    }
                    //   dd($sch,$sch->wardNew);
                   
                
            }
            // dd(  $r['studying_district']);
            // KHỐI HỌC (để khớp import cột 17: 'education_block_code' = tên khối)
            if (!empty($edu['education_block_id'])) {
                $b = EducationBlock::find($edu['education_block_id']);
                if ($b) {
                    $r['education_block_id']   = $b->id;   // để dành nếu service dùng id
                    $r['education_block_code'] = $b->name; // pipeline import hiện dùng tên
                }
            }

            // BẬC TN PHỔ THÔNG (cột 22: 'graduation_level_name')
            if (!empty($edu['graduation_level_id'])) {
                $gl = GraduationLevel::find($edu['graduation_level_id']);
                if ($gl) {
                    $r['graduation_level_id']   = $gl->id;
                    $r['graduation_level_name'] = $gl->name;
                }
            }

            // BẬC TN NGHỀ (cột 24: 'vocational_level_name')
            if (!empty($edu['vocational_level_id'])) {
                $vl = VocationalLevel::find($edu['vocational_level_id']);
                if ($vl) {
                    $r['vocational_level_id']   = $vl->id;
                    $r['vocational_level_name'] = $vl->name;
                }
            }

            // LỚP (bỏ học / học xong): map id -> số lớp (nếu pipeline vẫn đọc số)
            $mapGradeNum = function($gradeId) {
                if (!$gradeId) return null;
                $g = EducationGrade::find($gradeId);
                if (!$g) return null;
                // Ưu tiên số trong code, nếu không parse số trong name
                if (is_numeric($g->code)) return (int) $g->code;
                if (preg_match('/\d+/', (string)$g->name, $m)) return (int)$m[0];
                return null;
            };

            if (!empty($edu['bo_hoc_grade_id'])) {
                $r['bo_hoc_grade_id'] = (int)$edu['bo_hoc_grade_id'];
                $num = $mapGradeNum($edu['bo_hoc_grade_id']);
                if ($num) $r['bo_hoc_lop'] = $num; // để tương thích cột 30 cũ
            }
            if (!empty($edu['hoc_xong_grade_id'])) {
                $r['hoc_xong_grade_id'] = (int)$edu['hoc_xong_grade_id'];
                $num = $mapGradeNum($edu['hoc_xong_grade_id']);
                if ($num) $r['hoc_xong_lop'] = $num; // để tương thích cột 28 cũ
            }

            // Chuẩn hoá boolean (phòng trường hợp browser gửi "on"/"true")
            foreach ([
                'is_female','is_bo_tuc',
                'dis_VAN_DONG','dis_NGHE_NOI','dis_NHIN','dis_THAN_KINH',
                'dis_TRI_TUE','dis_HOC_TAP','dis_TU_KY','dis_KHAC',
                'dis_has_certificate','dis_can_learn'
            ] as $bKey) {
                if (array_key_exists($bKey, $r)) {
                    $r[$bKey] = filter_var($r[$bKey], FILTER_VALIDATE_BOOLEAN);
                }
            }

            return $r;
        }, $rows);

        // 4) Gói payload chuẩn cho Orchestrator
        $payload = [
            'academic_year' => $validated['academic_year'],
            'scheme'        => $validated['scheme'],
            'geo'           => [
                'province_id' => $req->input('geo.province_id'),
                'district_id' => $scheme === 'old' ? $req->input('geo.district_id') : null,
                'ward_id'     => $req->input('geo.ward_id'),
                'village_id'   => $req->input('geo.village_id'),
            ],
            'rows'          => $rows,
        ];
        // dd($payload);
        // 5) Gọi import pipeline
        try {
            $stats = $importer->import($payload);
            // dd($stats);
            // Có lỗi từng dòng?
            if (($stats['fail_count'] ?? 0) > 0) {
                // Với create 1 dòng, gom message cho gọn
                $msg = implode('; ', $stats['message_errors'] ?? []);
                if ($msg === '') $msg = 'Có lỗi khi lưu dòng dữ liệu.';
               
                return back()
                    ->withInput()
                    ->with('import_stats', $stats)
                    ->withErrors(['import' => $msg]);
            }

            return redirect()
                ->route('front.surveys.index', [
                    'year'   => $validated['academic_year'],
                    'scheme' => $validated['scheme'],
                    'geo'    => [
                        'province_id' => $payload['geo']['province_id'],
                        'district_id' => $payload['geo']['district_id'],
                        'ward_id'     => $payload['geo']['ward_id'],
                    ],
                ])
                ->with('success', 'Đã tạo phiếu điều tra thành công.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->withErrors([
                'import' => 'Lỗi lưu dữ liệu: '.$e->getMessage(),
            ]);
        }
    }

//  public function index(Request $request)
// {
//     $user = \Illuminate\Support\Facades\Auth::user();
    

//     // Ép scheme + geo theo user non-admin (như bạn đang làm)
//         $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'view'); // trả về: user, is_admin, scheme, geo
//         // Kiểm tra phiếu có bị khóa không
         
//         $isAdmin = $ctx['isAdmin'];
        

//     // ====== INPUTS ======
//     $minAge      = request()->filled('min_age') ? max(0, (int)request('min_age')) : null;
//     $maxAge      = request()->filled('max_age') ? max(0, (int)request('max_age')) : null;
//     $minority    = request('minority', null); // '1' thiểu số, '0' Kinh, null: tất cả
//     $defaultYear = \App\Models\SurveyForm::currentAcademicYear();
//     $year        = request('year', $defaultYear);
//     $q           = trim((string) request('q'));
//     $soPhieu     = trim((string) request('so_phieu'));
//     $perPage     = (int) request('per_page', 10);
//     $scheme      = request('scheme', 'old');
//     $geoIn       = (array) request()->input('geo', []);

//     $geoIds = [
//         'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
//         'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
//         'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
//         'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
//     ];
//     $applyGeo = collect($geoIds)->filter()->isNotEmpty();

//     // Mốc tham chiếu tuổi (giữ logic cũ)
//     if ($year !== 'all' && preg_match('/^(\d{4})-(\d{4})$/', $year, $m)) {
//         $y2 = (int)$m[2];
//         $refDate = \Carbon\Carbon::create($y2, 9, 30)->startOfDay();
//     } else {
//         $refDate = now();
//     }

//     // ====== SORT an toàn ======
//     $sort = request('sort', 'ten');
//     $dir  = strtolower(request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
//     if (!in_array($sort, \App\Models\SurveyDetailFlat::sortableColumns(), true)) {
//         $sort = 'ten';
//     }

//     // ====== QUERY ======
//     /** @var \Illuminate\Database\Eloquent\Builder $qb */
//     $qb = \App\Models\SurveyDetailFlat::query()
//         ->ofYear($year)
//         ->when($soPhieu !== '', fn($q2) => $q2->where('so_phieu', 'like', "%{$soPhieu}%"))
//         ->search($q)
//         ->ageBetween($minAge, $maxAge, $refDate)
//         // Lọc thiểu số theo tên dân tộc trong view (nếu muốn dựa vào ID thì thêm ethnic_id vào view)
//         ->when($minority !== null && $minority !== '', function ($q2) use ($minority) {
//             if ($minority === '1') {
//                 $q2->whereNotNull('ethnicity')->where('ethnicity', '!=', 'Kinh');
//             } elseif ($minority === '0') {
//                 $q2->where('ethnicity', 'Kinh');
//             }
//         });

//     // (Tuỳ chọn) GEO FILTER — chỉ bật nếu view có các cột *_id tương ứng
    
//     if ($applyGeo) {
//                 if ($scheme === 'old') {
//             if ($geoIds['province']) $qb->where('old_province_id', $geoIds['province']);
//             if ($geoIds['district']) $qb->where('old_district_id', $geoIds['district']);
//             if ($geoIds['ward'])     $qb->where('old_ward_id',     $geoIds['ward']);
//                 } else {
//             if ($geoIds['province']) $qb->where('new_province_id', $geoIds['province']);
//             if ($geoIds['ward'])     $qb->where('new_ward_id',     $geoIds['ward']);
//         }
//         if ($geoIds['village']) $qb->where('village_id', $geoIds['village']);
//     }
    

//     $rows = $qb->orderBy($sort, $dir)
//                ->orderBy('detail_id', 'asc') // fallback ổn định
//                   ->paginate($perPage)
//                   ->withQueryString();

//     // View đã phẳng → truyền trực tiếp
//     $columns      = $this->columns();
//     $groups       = $this->columnGroups();
//     $sortableKeys = \App\Models\SurveyDetailFlat::sortableColumns(); // để Blade biết cột nào sort được

//     return view('front.surveys.index', compact(
//         'rows', 'columns', 'groups', 'year', 'defaultYear', 'q', 'soPhieu',
//         'minAge', 'maxAge', 'minority', 'sortableKeys'
//     ));
// }


public function index(Request $request)
{
    $user = \Illuminate\Support\Facades\Auth::user();

    $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'view');
    $isAdmin = $ctx['isAdmin'];

    // ====== INPUTS ======
    $minAge       = request()->filled('min_age') ? max(0, (int)request('min_age')) : null;
    $maxAge       = request()->filled('max_age') ? max(0, (int)request('max_age')) : null;
    $birthYear    = request()->filled('birth_year') ? (int)request('birth_year') : null;
    $minBirthYear = request()->filled('min_birth_year') ? (int)request('min_birth_year') : $birthYear;
    $maxBirthYear = request()->filled('max_birth_year') ? (int)request('max_birth_year') : $birthYear;
    $minority     = request('minority', null); // '1' thiểu số, '0' Kinh, null: tất cả
    $defaultYear  = \App\Models\SurveyForm::currentAcademicYear();
    $year         = request('year', $defaultYear);
    $q            = trim((string) request('q'));
    $soPhieu      = trim((string) request('so_phieu'));
    $school       = trim((string) request('school'));
    $classLabel   = trim((string) request('class_label'));
    $perPage      = (int) request('per_page', 10);
    $scheme       = request('scheme', 'old');
    $geoIn        = (array) request()->input('geo', []);

    // Advanced inputs
    $hoDem             = trim((string) request('ho_dem'));
    $ten               = trim((string) request('ten'));
    $chuhoHoDem        = trim((string) request('chuho_ho_dem'));
    $chuhoTen          = trim((string) request('chuho_ten'));
    $identityNumber    = trim((string) request('identity_number'));
    $isFemale          = request('is_female', null);
    $ethnicityId       = request('ethnicity_id', null);
    $religionId        = request('religion_id', null);
    $educationBlockId  = request('education_block_id', null);
    $graduationLevelId = request('graduation_level_id', null);
    $graduationYear    = request('graduation_year', null);
    $isBoTuc           = request('is_bo_tuc', null);
    $vocationalLevelId = request('vocational_level_id', null);
    $vocationalGradYear= request('vocational_grad_year', null);
    $isRepeatGrade     = request('is_repeat_grade', null);
    $hocHaiBuoi        = request('hoc_hai_buoi', null);
    $traiTuyen         = request('trai_tuyen', null);
    $boHocTuLop        = request('bo_hoc_tu_lop', null);
    $boHocDenLop       = request('bo_hoc_den_lop', null);
    $boHocNam          = request('bo_hoc_nam', null);
    $hocXongTuLop      = request('hoc_xong_tu_lop', null);
    $hocXongDenLop     = request('hoc_xong_den_lop', null);
    $hocXongNam        = request('hoc_xong_nam', null);
    $litDangHocLop     = request('lit_dang_hoc_lop', null);
    $litHoanThanhLop   = request('lit_hoan_thanh_lop', null);
    $isDisabled        = request('is_disabled', null);
    $disType           = request('dis_type', null);
    $canLearn          = request('can_learn', null);
    $hasCertificate    = request('has_certificate', null);
    $scId              = request('sc_id', null);
    $hasNote           = request('has_note', null);

    $geoIds = [
        'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
        'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
        'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
        'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
    ];
    $applyGeo = collect($geoIds)->filter()->isNotEmpty();

    // ====== Mốc tham chiếu tuổi THEO NĂM ======
    if ($year !== 'all' && preg_match('/^(\d{4})-(\d{4})$/', $year, $m)) {
        $refYear = (int) $m[1];
    } else {
        $refYear = (int) now()->year;
    }

    // ====== SORT an toàn ======
    $sort = request('sort', 'ten');
    $dir  = strtolower(request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    if (!in_array($sort, \App\Models\SurveyDetailFlat::sortableColumns(), true)) {
        $sort = 'ten';
    }

    // ====== QUERY ======
    /** @var \Illuminate\Database\Eloquent\Builder $qb */
    $qb = \App\Models\SurveyDetailFlat::query()
        ->ofYear($year)
        ->when($soPhieu !== '', fn($q2) => $q2->where('so_phieu', 'like', "%{$soPhieu}%"))
        ->search($q)

      // --- Lọc tuổi theo NĂM: age = refYear - dob_year (ép SIGNED để tránh tràn) ---
      ->when($minAge !== null || $maxAge !== null, function ($q2) use ($minAge, $maxAge, $refYear) {
          $q2->whereNotNull('dob_year')
             ->where('dob_year', '>', 0)
             // Chặn các năm sinh > refYear để tránh kết quả âm
             ->where('dob_year', '<=', $refYear);

          // Ép SIGNED để MySQL không nâng kiểu về UNSIGNED gây tràn
          $ageExpr = DB::raw("(CAST($refYear AS SIGNED) - CAST(dob_year AS SIGNED))");

          if ($minAge !== null && $maxAge !== null) {
              $q2->whereBetween($ageExpr, [$minAge, $maxAge]);
          } elseif ($minAge !== null) {
              $q2->where($ageExpr, '>=', $minAge);
          } else { // $maxAge !== null
              $q2->where($ageExpr, '<=', $maxAge);
          }
      })
      // --- Lọc theo NĂM SINH ---
      ->when($minBirthYear !== null || $maxBirthYear !== null, function ($q2) use ($minBirthYear, $maxBirthYear) {
          $q2->whereNotNull('dob_year')
             ->where('dob_year', '>', 0);
          if ($minBirthYear !== null && $maxBirthYear !== null) {
              $q2->whereBetween('dob_year', [$minBirthYear, $maxBirthYear]);
          } elseif ($minBirthYear !== null) {
              $q2->where('dob_year', '>=', $minBirthYear);
          } else {
              $q2->where('dob_year', '<=', $maxBirthYear);
          }
      })
      // Lọc theo trường (tên hoặc mã)
      ->when($school !== '', fn($q2) => $q2->where(function($w) use ($school) {
          $w->where('school_name', 'like', "%{$school}%")
            ->orWhere('school_code', 'like', "%{$school}%");
      }))
      // Lọc theo lớp
      ->when($classLabel !== '', fn($q2) => $q2->where('education_grade_name', 'like', "%{$classLabel}%"))
      // Lọc thiểu số theo tên dân tộc trong view
      ->when($minority !== null && $minority !== '', function ($q2) use ($minority) {
          if ($minority === '1') {
              $q2->whereNotNull('ethnicity')->where('ethnicity', '!=', 'Kinh');
          } elseif ($minority === '0') {
              $q2->where('ethnicity', 'Kinh');
          }
      })
      // Advanced filter fields
      ->when($hoDem !== '', fn($q2) => $q2->where('ho_dem', 'like', "%{$hoDem}%"))
      ->when($ten !== '', fn($q2) => $q2->where('ten', 'like', "%{$ten}%"))
      ->when($chuhoHoDem !== '', fn($q2) => $q2->where('chuho_ho_dem', 'like', "%{$chuhoHoDem}%"))
      ->when($chuhoTen !== '', fn($q2) => $q2->where('chuho_ten', 'like', "%{$chuhoTen}%"))
      ->when($identityNumber !== '', fn($q2) => $q2->where('identity_number', 'like', "%{$identityNumber}%"))
      ->when($isFemale !== null && $isFemale !== '', fn($q2) => $q2->where('is_female', $isFemale == '1' ? 1 : 0))
      ->when($ethnicityId !== null && $ethnicityId !== '', fn($q2) => $q2->where('ethnicity_id', $ethnicityId))
      ->when($religionId !== null && $religionId !== '', fn($q2) => $q2->where('religion_id', $religionId))
      ->when($educationBlockId !== null && $educationBlockId !== '', fn($q2) => $q2->where('education_block_id', $educationBlockId))
      ->when($graduationLevelId !== null && $graduationLevelId !== '', fn($q2) => $q2->where('graduation_level_id', $graduationLevelId))
      ->when($graduationYear !== null && $graduationYear !== '', fn($q2) => $q2->where('graduation_year', $graduationYear))
      ->when($isBoTuc !== null && $isBoTuc !== '', fn($q2) => $q2->where('is_bo_tuc', $isBoTuc == '1' ? 1 : 0))
      ->when($vocationalLevelId !== null && $vocationalLevelId !== '', fn($q2) => $q2->where('vocational_level_id', $vocationalLevelId))
      ->when($vocationalGradYear !== null && $vocationalGradYear !== '', fn($q2) => $q2->where('vocational_grad_year', $vocationalGradYear))
      ->when($isRepeatGrade !== null && $isRepeatGrade !== '', fn($q2) => $q2->where('is_repeat_grade', $isRepeatGrade == '1' ? 1 : 0))
      ->when($hocHaiBuoi !== null && $hocHaiBuoi !== '', fn($q2) => $q2->where('hoc_hai_buoi', $hocHaiBuoi == '1' ? 1 : 0))
      ->when($traiTuyen !== null && $traiTuyen !== '', fn($q2) => $q2->where('trai_tuyen', $traiTuyen == '1' ? 1 : 0))
      ->when($boHocTuLop !== null && $boHocTuLop !== '', fn($q2) => $q2->where('bo_hoc_lop', '>=', (int)$boHocTuLop))
      ->when($boHocDenLop !== null && $boHocDenLop !== '', fn($q2) => $q2->where('bo_hoc_lop', '<=', (int)$boHocDenLop))
      ->when($boHocNam !== null && $boHocNam !== '', fn($q2) => $q2->where('bo_hoc_nam', (int)$boHocNam))
      ->when($hocXongTuLop !== null && $hocXongTuLop !== '', fn($q2) => $q2->where('hoc_xong_lop', '>=', (int)$hocXongTuLop))
      ->when($hocXongDenLop !== null && $hocXongDenLop !== '', fn($q2) => $q2->where('hoc_xong_lop', '<=', (int)$hocXongDenLop))
      ->when($hocXongNam !== null && $hocXongNam !== '', fn($q2) => $q2->where('hoc_xong_nam', (int)$hocXongNam))
      ->when($litDangHocLop !== null && $litDangHocLop !== '', fn($q2) => $q2->where('lit_dang_hoc_lop', 'like', "%{$litDangHocLop}%"))
      ->when($litHoanThanhLop !== null && $litHoanThanhLop !== '', fn($q2) => $q2->where('lit_hoan_thanh_lop', 'like', "%{$litHoanThanhLop}%"))
      ->when($isDisabled === '1', fn($q2) => $q2->where(fn($w) => $w->where('dis_VAN_DONG', 1)->orWhere('dis_NGHE_NOI', 1)->orWhere('dis_NHIN', 1)->orWhere('dis_THAN_KINH', 1)->orWhere('dis_TRI_TUE', 1)->orWhere('dis_HOC_TAP', 1)->orWhere('dis_TU_KY', 1)->orWhere('dis_KHAC', 1)))
      ->when($disType !== null && $disType !== '', function($q2) use ($disType) {
          $col = "dis_{$disType}";
          if (in_array($col, ['dis_VAN_DONG','dis_NGHE_NOI','dis_NHIN','dis_THAN_KINH','dis_TRI_TUE','dis_HOC_TAP','dis_TU_KY','dis_KHAC'])) {
              $q2->where($col, 1);
          }
      })
      ->when($canLearn !== null && $canLearn !== '', fn($q2) => $q2->where('dis_can_learn', $canLearn == '1' ? 1 : 0))
      ->when($hasCertificate !== null && $hasCertificate !== '', fn($q2) => $q2->where('dis_has_certificate', $hasCertificate == '1' ? 1 : 0))
      ->when($scId !== null && $scId !== '', fn($q2) => $q2->where('sc_id', $scId))
      ->when($hasNote === '1', fn($q2) => $q2->whereNotNull('contact_note')->where('contact_note', '!=', ''));

    // GEO FILTER — dùng cột *_id trên view
    if ($applyGeo) {
        if ($scheme === 'old') {
            if ($geoIds['province']) $qb->where('old_province_id', $geoIds['province']);
            if ($geoIds['district']) $qb->where('old_district_id', $geoIds['district']);
            if ($geoIds['ward'])     $qb->where('old_ward_id',     $geoIds['ward']);
        } else {
            if ($geoIds['province']) $qb->where('new_province_id', $geoIds['province']);
            if ($geoIds['ward'])     $qb->where('new_ward_id',     $geoIds['ward']);
        }
        if ($geoIds['village']) $qb->where('village_id', $geoIds['village']);
    }

    try {
        $rows = $qb->orderBy($sort, $dir)
                   ->orderBy('detail_id', 'asc')
                   ->paginate($perPage)
                   ->withQueryString();
    } catch (\Illuminate\Database\QueryException $e) {
        if (str_contains($e->getMessage(), '1615') || str_contains($e->getMessage(), 're-prepared')) {
            \Illuminate\Support\Facades\DB::reconnect();
            $rows = $qb->orderBy($sort, $dir)
                       ->orderBy('detail_id', 'asc')
                       ->paginate($perPage)
                       ->withQueryString();
        } else {
            throw $e;
        }
    }

    $columns      = $this->columns();
    $groups       = $this->columnGroups();
    $sortableKeys = \App\Models\SurveyDetailFlat::sortableColumns();

    // Data options for advanced filter dropdowns
    $blocks      = EducationBlock::orderBy('id')->get(['id','name']);
    $grades      = EducationGrade::where('is_active', true)->orderBy('id')->get(['id','code','name']);
    $gradLevels  = GraduationLevel::orderBy('id')->get(['id','name']);
    $vocLevels   = VocationalLevel::orderBy('id')->get(['id','name']);
    $ethnicities = Ethnicity::orderBy('id')->get(['id','code','name']);
    $religions   = Religion::orderBy('id')->get(['id','code','name']);
    $specials    = SpecialCircumstance::orderBy('id')->get(['id','name']);

    // Check count of active advanced filters to display badge
    $activeFilterCount = collect([
        $hoDem, $ten, $chuhoHoDem, $chuhoTen, $identityNumber, $isFemale,
        $ethnicityId, $religionId, $educationBlockId, $graduationLevelId,
        $graduationYear, $isBoTuc, $vocationalLevelId, $vocationalGradYear,
        $isRepeatGrade, $hocHaiBuoi, $traiTuyen, $boHocTuLop, $boHocDenLop,
        $boHocNam, $hocXongTuLop, $hocXongDenLop, $hocXongNam, $litDangHocLop,
        $litHoanThanhLop, $isDisabled, $disType, $canLearn, $hasCertificate,
        $scId, $hasNote
    ])->filter(fn($v) => $v !== null && $v !== '')->count();

    return view('front.surveys.index', compact(
        'rows', 'columns', 'groups', 'year', 'defaultYear', 'q', 'soPhieu',
        'minAge', 'maxAge', 'birthYear', 'minBirthYear', 'maxBirthYear', 'minority', 'school', 'classLabel', 'sortableKeys',
        'hoDem', 'ten', 'chuhoHoDem', 'chuhoTen', 'identityNumber', 'isFemale', 'ethnicityId', 'religionId',
        'educationBlockId', 'graduationLevelId', 'graduationYear', 'isBoTuc', 'vocationalLevelId', 'vocationalGradYear',
        'isRepeatGrade', 'hocHaiBuoi', 'traiTuyen', 'boHocTuLop', 'boHocDenLop', 'boHocNam', 'hocXongTuLop', 'hocXongDenLop',
        'hocXongNam', 'litDangHocLop', 'litHoanThanhLop', 'isDisabled', 'disType', 'canLearn', 'hasCertificate', 'scId', 'hasNote',
        'blocks', 'grades', 'gradLevels', 'vocLevels', 'ethnicities', 'religions', 'specials', 'activeFilterCount'
    ));
}

    /* ===================== Helpers ===================== */

 

  

  


    /** Năm tham chiếu để tính tuổi (giống logic cũ) */
    private function refDateFromYear(): string
    {
        $year = request('year', SurveyForm::currentAcademicYear());
        if ($year !== 'all' && preg_match('/^(\d{4})-(\d{4})$/', $year, $m)) {
            $y2 = (int)$m[2];
            return Carbon::create($y2, 9, 30)->startOfDay()->toDateString();
        }
        return now()->toDateString();
}


    /** Chuyển Eloquent models sang mảng hiển thị gọn */


/** Nhóm cột để render header gộp (đúng thứ tự file import) */
private function columnGroups(): array
{
    return [
        [
            'title' => 'Thông tin đối tượng',
            'keys'  => [
                'ho_dem','ten','dob_day','dob_month','dob_year', 'identity_number',
                'is_female','ethnicity','religion','priority_name',
            ],
        ],
        [
            'title' => 'Thông tin nơi đang ở',
            'keys'  => [
                'chuho_ho_dem','chuho_ten','addressline','so_phieu',
                'residence_type_name','residence_status_name',
            ],
        ],
        [
            'title' => 'Thông tin học tập',
            'keys'  => [
                'education_block_code','education_grade_name','studying_district',
                'school_name','school_code',
                'graduation_level_name','is_bo_tuc','graduation_year',
                'vocational_level_name','graduation_certificate_no','vocational_grad_year',
                'hoc_xong_lop','hoc_xong_nam','bo_hoc_lop','bo_hoc_nam',
            ],
        ],
        [
            'title' => 'Xóa mù chữ',
            'keys'  => ['lit_dang_hoc_lop','lit_hoan_thanh_lop','lit_tai_mu_muc'],
        ],
        [
            'title' => 'Khuyết tật',
            'keys'  => [
                'dis_VAN_DONG','dis_NGHE_NOI','dis_NHIN','dis_THAN_KINH',
                'dis_TRI_TUE','dis_HOC_TAP','dis_TU_KY','dis_KHAC',
                'dis_has_certificate','dis_can_learn',
            ],
        ],
        [
            'title' => 'Hoàn cảnh',
            'keys'  => ['sc_name','sc_detail'],
        ],
        [
            'title' => 'Thông tin liên lạc',
            'keys'  => ['relationship_name','contact_name','contact_phone','contact_note'],
        ],
    ];
}
    /** Nhãn cột đúng thứ tự file nhập */
    private function columns(): array
    {
        return [
            'ho_dem' => 'Họ đệm',
            'ten' => 'Tên',
            'dob_day' => 'Ngày sinh',
            'dob_month' => 'Tháng sinh',
            'dob_year' => 'Năm sinh',
            'is_female' => 'Giới tính (Nữ)',
            'identity_number' => 'CCCD',
            'ethnicity' => 'Dân tộc',
            'religion' => 'Tôn giáo',
            'priority_name' => 'Diện ưu tiên',
            'chuho_ho_dem' => 'Chủ hộ - Họ đệm',
            'chuho_ten' => 'Chủ hộ - Tên',
            'addressline' => 'Địa chỉ',
            'so_phieu' => 'Số phiếu',
            'residence_type_name' => 'Diện cư trú',
            'residence_status_name' => 'Tình trạng cư trú',
            'education_block_code' => 'Khối học',
            'education_grade_name' => 'Lớp học',
            'studying_district' => 'Quận/Huyện học',
            'school_name' => 'Tên trường',
            'school_code' => 'Mã trường',
            'graduation_level_name' => 'Bậc TN PT',
            'is_bo_tuc' => 'Bổ túc',
            'graduation_year' => 'Năm TN PT',
            'vocational_level_name' => 'Bậc TN nghề',
            'graduation_certificate_no' => 'Số bằng TN',
            'vocational_grad_year' => 'Năm TN nghề',
            'hoc_xong_lop' => 'Học xong lớp',
            'hoc_xong_nam' => 'Học xong năm',
            'bo_hoc_lop' => 'Bỏ học lớp',
            'bo_hoc_nam' => 'Bỏ học năm',
            'lit_dang_hoc_lop' => 'Đang học lớp',
            'lit_hoan_thanh_lop' => 'Hoàn thành lớp',
            'lit_tai_mu_muc' => 'Tái mù mức',
            'dis_VAN_DONG' => 'KT vận động',
            'dis_NGHE_NOI' => 'KT nghe, nói',
            'dis_NHIN' => 'KT nhìn',
            'dis_THAN_KINH' => 'KT thần kinh',
            'dis_TRI_TUE' => 'KT trí tuệ',
            'dis_HOC_TAP' => 'KT học tập',
            'dis_TU_KY' => 'Tự kỷ',
            'dis_KHAC' => 'KT khác',
            'dis_has_certificate' => 'Có chứng nhận',
            'dis_can_learn' => 'Khả năng học',
            'sc_name' => 'Hoàn cảnh ĐB',
            'sc_detail' => 'Chi tiết HC',
            'relationship_name' => 'Quan hệ với chủ hộ',
            'contact_name' => 'Họ tên (Cha/Mẹ)',
            'contact_phone' => 'Điện thoại',
            'contact_note' => 'Ghi chú',
        ];
    }

    /**
     * Xóa tạm đối tượng điều tra (soft delete)
     */
    public function softDelete(Request $request, $detailId)
    {
        // LOG để debug
        // \Illuminate\Support\Facades\Log::info('Soft delete called', [
        //     'detailId' => $detailId,
        //     'request_data' => $request->all(),
        //     'user_id' => auth()->id()
        // ]);

        // Kiểm tra quyền xóa đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
  

         $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete'); // trả về: user, is_admin, scheme, geo
        // Kiểm tra phiếu có bị khóa không
        $isAdmin = $ctx['isAdmin'];

        try {
            // Tìm SurveyFormDetail theo ID
            $detail = SurveyFormDetail::find($detailId);
            $survey = $detail->surveyForm;
            $ctx = $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY'); //kiem tra log có đang bị khóa không

            if (!$detail) {
                \Illuminate\Support\Facades\Log::warning('SurveyFormDetail not found', [
                    'detailId' => $detailId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đối tượng điều tra.'
                ], 404);
            }

            // Kiểm tra xem đã bị xóa chưa
            if ($detail->is_deleted) {
                \Illuminate\Support\Facades\Log::info('Record already deleted', [
                    'detailId' => $detailId,
                    'is_deleted' => $detail->is_deleted
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Đối tượng này đã bị xóa tạm trước đó.'
                ], 400);
            }

            // Thực hiện soft delete
            $detail->update(['is_deleted' => true]);
            
            \Illuminate\Support\Facades\Log::info('Soft delete successful', [
                'detailId' => $detailId,
                'person_id' => $detail->person_id,
                'survey_form_id' => $detail->survey_form_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa tạm đối tượng điều tra thành công.'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Soft delete error: ' . $e->getMessage(), [
                'detailId' => $detailId,
                'exception' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa tạm đối tượng.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Danh sách đối tượng đã xóa tạm
     */
    public function deleted(Request $request)
    {
        // Kiểm tra quyền xem đối tượng điều tra
        // $user = \Illuminate\Support\Facades\Auth::user();
        // if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view')) {
        //     abort(403, 'Bạn không có quyền xem danh sách đối tượng điều tra đã xóa.');
        // }
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'view'); 
        $defaultYear = SurveyForm::currentAcademicYear();
        $year        = request('year', $defaultYear);
        $q           = trim((string) request('q'));
        $soPhieu     = trim((string) request('so_phieu'));
        $perPage     = (int) request('per_page', 10);
        $perPageSurveys = (int) request('per_page_surveys', 10);
        $scheme = request('scheme', 'old');
        $geoIn  = (array) request()->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'village'   => (int) ($geoIn['village_id'] ?? 0) ?: null,
        ];

        $applyGeo = collect($geoIds)->filter()->isNotEmpty();
        // map mã -> id khuyết tật
        $disCatIds = DisabilityCategory::whereIn('code', [
            'VAN_DONG','NGHE_NOI','NHIN','THAN_KINH','TRI_TUE','HOC_TAP','TU_KY','KHAC'
        ])->pluck('id','code')->all();

        // Lấy các đối tượng đã xóa tạm của năm được chọn
        $rows = SurveyFormDetail::query()
            ->where('is_deleted', true) // CHỈ LẤY RECORDS ĐÃ XÓA TẠM
            ->where('academic_year', $year) // CHỈ LẤY CỦA NĂM ĐƯỢC CHỌN
            ->with([ 
                   'surveyForm:id,so_phieu,academic_year,household_id,head_person_id,'.
                'old_ward_id,new_ward_id,old_district_id,old_province_id,new_province_id,village_id',
                'surveyForm.headPerson:id,ho_dem,ten',

                // tên địa bàn từ SurveyForm
                'surveyForm.oldWard:id,name',
                'surveyForm.newWard:id,name',
                'surveyForm.oldDistrict:id,name',
                'surveyForm.oldProvince:id,name',
                'surveyForm.newProvince:id,name',
                'surveyForm.village:id,name',
               
                // người
                 'person.type:id,name',
                'person.status:id,name',
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,is_female,ethnicity_id,religion_id,'.
                        'contact_name,identity_number,phone,addressline,old_ward_id,new_ward_id,old_district_id,contact_name,note,residence_type_id,residence_status_id',
                'person.ethnicity:id,name',
                'person.religion:id,name',
                // học tập
                'person.householdMemberships:id,household_id,person_id,role',
                'education:id,person_id,education_grade_id,class_label,hoc_hai_buoi,hoc_9_10_buoi_tuan,is_repeat_grade,studying_district_id,school_id,school_name_custom,studying_district_custom,graduation_level_id,is_bo_tuc,graduation_year,vocational_level_id,vocational_grad_year,graduation_certificate_no,hoc_xong_lop,hoc_xong_nam,bo_hoc_lop,bo_hoc_nam,academic_year',
                'education.block:id,name',
                'education.graduationLevel:id,name',
                'education.vocationalLevel:id,name',
                'education.school:id,code,name',
                // xoá mù chữ
                'literacy:id,person_id,dang_hoc_lop,hoan_thanh_lop,tai_mu_chu_muc',
                // khuyết tật
                'personDisability',
                // hoàn cảnh
                'specialCircumstance.specialCircumstance:id,name',
                // liên lạc
                'contact.relationship:id,name',
            ])
            ->when($soPhieu !== '', fn($qq) =>
                $qq->whereHas('surveyForm', fn($f) => $f->where('so_phieu','like',"%{$soPhieu}%")))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('person', function ($pp) use ($q) {
                    $pp->where('ten','like',"%{$q}%")
                       ->orWhere('ho_dem','like',"%{$q}%")
                       ->orWhere('identity_number','like',"%{$q}%")
                       ->orWhere('phone','like',"%{$q}%")
                       ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$q}%"]);
                });
            })
            ->when($applyGeo, function ($qq) use ($scheme, $geoIds) {
                $qq->whereHas('surveyForm', function ($sf) use ($scheme, $geoIds) {
                    if ($scheme === 'old') {
                        if ($geoIds['province']) $sf->where('old_province_id', $geoIds['province']);
                        if ($geoIds['district']) $sf->where('old_district_id', $geoIds['district']);
                        if ($geoIds['ward'])     $sf->where('old_ward_id',     $geoIds['ward']);
                    } else { // new
                        if ($geoIds['province']) $sf->where('new_province_id', $geoIds['province']);
                        if ($geoIds['ward'])     $sf->where('new_ward_id',     $geoIds['ward']);
                    }
                    if ($geoIds['village']) $sf->where('village_id', $geoIds['village']);
                });
            })
            ->orderByDesc('survey_form_id')->orderBy('id')
            ->paginate($perPage)->withQueryString();

        // "phẳng hoá" dữ liệu theo đúng 50 key (giống như method index)
        $flat = $rows->getCollection()->map(function ($d) use ($disCatIds) {
            $p   = $d->person;
            $edu = $d->education;
            $lit = $d->literacy;
            $village      =     $d->surveyForm?->village?->name      ?: $p?->village?->name;
            $oldWardName      = $d->surveyForm?->oldWard?->name      ?: $p?->oldWard?->name;
            $newWardName      = $d->surveyForm?->newWard?->name      ?: $p?->newWard?->name;
            $oldDistrictName  = $d->surveyForm?->oldDistrict?->name  ?: $p?->oldDistrict?->name;
            $oldProvinceName  = $d->surveyForm?->oldProvince?->name  ?: $p?->oldProvince?->name;
            $newProvinceName  = $d->surveyForm?->newProvince?->name  ?: $p?->newProvince?->name;
            $residenceStatusName = $p->status->name ?? null;
            $residenceTypeName   = $p->type->name ?? null;
            $note = $d->surveyForm?->note;
            $studyingDistrictName = null;
            $school = School::find($edu?->school_id);
            if ($school) {
                $studyingDistrictName = $school->wardNew?->name
                                     ?? $school->ward_new?->name
                                     ?? $studyingDistrictName;
            }
            $tick = fn($v) => $v ? '✓' : '';
            $hasDis = function ($code) use ($d, $disCatIds) {
                $id  = $disCatIds[$code] ?? null;
                if (!$id) return false;
                $ids = $d->personDisability?->disability_ids ?? [];
                return in_array($id, $ids, true);
            };
            $role = collect($p?->householdMemberships ?? [])
            ->firstWhere('household_id', $d->surveyForm?->household_id)
            ->role ?? null;
            
            return [
                // 1..50 theo file nhập
                'survey_id'              => $d->survey_form_id,
                'detail_id'              => $d->id,
                'old_province_name' => $oldProvinceName,
                'new_province_name' => $newProvinceName,
                'old_district_name' => $oldDistrictName,
                'old_ward_name'     => $oldWardName,
                'new_ward_name'     => $newWardName,
                'village_name'     => $village,
                'row_no'                 => null,
                'ho_dem'                 => $p?->ho_dem,
                'ten'                    => $p?->ten,
                'dob_day'                => $p?->dob_day,
                'dob_month'              => $p?->dob_month,
                'dob_year'               => $p?->dob_year,
                'is_female'              => $tick($p?->is_female),
                'ethnicity'              => $p?->ethnicity?->name,
                'religion'               => $p?->religion?->name,
                'priority_name'          => $d->specialCircumstance?->specialCircumstance?->name,
                'chuho_ho_dem'           => $d->surveyForm?->headPerson?->ho_dem,
                'chuho_ten'              => $d->surveyForm?->headPerson?->ten,
                'addressline'            => $p?->addressline,
                'so_phieu'               => $d->surveyForm?->so_phieu,
                'residence_type_name'    =>  $residenceTypeName ?? null,
                'residence_status_name'  => $residenceStatusName ?? null,
                'education_block_code'   => $edu?->block?->name,
                'education_grade_name'   => $edu?->class_label,
                'studying_district'      =>  $studyingDistrictName??null,
                'school_name'            => $edu?->school?->name,
                'school_code'            => $edu?->school?->code,
                'graduation_level_name'  => $edu?->graduationLevel?->name,
                'is_bo_tuc'              => $tick($edu?->is_bo_tuc),
                'graduation_year'        => $edu?->graduation_year,
                'vocational_level_name'  => $edu?->vocationalLevel?->name,
                'graduation_certificate_no'=> $edu?->graduation_certificate_no,
                'vocational_grad_year'   => $edu?->vocational_grad_year,
                'hoc_xong_lop'           => $edu?->hoc_xong_lop,
                'hoc_xong_nam'           => $edu?->hoc_xong_nam,
                'bo_hoc_lop'             => $edu?->bo_hoc_lop,
                'bo_hoc_nam'             => $edu?->bo_hoc_nam,
                'lit_dang_hoc_lop'       => $lit?->dang_hoc_lop,
                'lit_hoan_thanh_lop'     => $lit?->hoan_thanh_lop,
                'lit_tai_mu_muc'         => $lit?->tai_mu_chu_muc,
                'dis_VAN_DONG'           => $tick($hasDis('VAN_DONG')),
                'dis_NGHE_NOI'           => $tick($hasDis('NGHE_NOI')),
                'dis_NHIN'               => $tick($hasDis('NHIN')),
                'dis_THAN_KINH'          => $tick($hasDis('THAN_KINH')),
                'dis_TRI_TUE'            => $tick($hasDis('TRI_TUE')),
                'dis_HOC_TAP'            => $tick($hasDis('HOC_TAP')),
                'dis_TU_KY'              => $tick($hasDis('TU_KY')),
                'dis_KHAC'               => $tick($hasDis('KHAC')),
                'dis_has_certificate'    => $tick($d->personDisability?->has_certificate),
                'dis_can_learn'          => $tick($d->personDisability?->can_learn),
                'sc_name'                => $d->specialCircumstance?->specialCircumstance?->name,
                'sc_detail'              => $d->specialCircumstance?->chi_tiet,
                'relationship_name'      =>$role,
                'contact_name'           => $p->contact_name,
                'contact_phone'          => $p->phone,
                'contact_note'           => $d->note,
            ];
        });

        $rows->setCollection($flat);

        $columns = $this->columns();
        $groups  = $this->columnGroups();

        // Lấy danh sách phiếu đã xóa tạm
        $deletedSurveys = $this->getDeletedSurveys($year, $scheme, $geoIds, $soPhieu, $q, $perPageSurveys);

        return view('front.surveys.deleted', compact(
            'rows','columns','groups','year','defaultYear','q','soPhieu','deletedSurveys','scheme','geoIds'
        ));
    }

    /**
     * Phục hồi đối tượng đã xóa tạm
     */
    public function restore(Request $request, $detailId)
    {
        // Kiểm tra quyền chỉnh sửa đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        // if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Bạn không có quyền phục hồi đối tượng điều tra.'
        //     ], 403);
        // }
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit'); 
        try {
            // Tìm SurveyFormDetail theo ID
            $detail = SurveyFormDetail::with('surveyForm')->find($detailId);
           
            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đối tượng điều tra.'
                ], 404);
            }
            $ctx = $this->guard->guardRecordGeoAndLock($request, $detail->surveyForm, 'SURVEY');
            // Kiểm tra xem có đang bị xóa không
            if (!$detail->is_deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đối tượng này chưa bị xóa tạm.'
                ], 400);
            }

            // Kiểm tra xem phiếu của đối tượng có đang bị xóa tạm không
            if ($detail->surveyForm && $detail->surveyForm->is_deleted) {
                return response()->json([
                    'success' => false,
                    'message' => "Phiếu điều tra số: {$detail->surveyForm->so_phieu} của đối tượng đang bị xóa tạm. Vui lòng phục hồi phiếu trước khi phục hồi đối tượng.",
                    'survey_id' => $detail->surveyForm->id,
                    'survey_code' => $detail->surveyForm->so_phieu
                ], 400);
            }

            // Thực hiện restore
            $detail->update(['is_deleted' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Đã phục hồi đối tượng điều tra thành công.'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Restore error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể phục hồi đối tượng.'
            ], 500);
        }
    }

    /**
     * Xóa thật đối tượng khỏi database
     */
    public function hardDelete(Request $request, $detailId)
    {
        // Kiểm tra quyền xóa đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        // if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'delete')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Bạn không có quyền xóa đối tượng điều tra.'
        //     ], 403);
        // }
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete'); 
        try {
            // Tìm SurveyFormDetail theo ID
            $detail = SurveyFormDetail::find($detailId);
            
            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đối tượng điều tra.'
                ], 404);
            }
            $ctx = $this->guard->guardRecordGeoAndLock($request, $detail->surveyForm, 'SURVEY'); 
            // Kiểm tra xem có đang bị xóa tạm không
            if (!$detail->is_deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xóa thật các đối tượng đã bị xóa tạm.'
                ], 400);
            }

            // Lưu thông tin để log (truy xuất person ngay cả khi đã xóa tạm)
            $person = Person::where('id', $detail->person_id)->first();
            $personName = $person ? trim(($person->ho_dem ?? '') . ' ' . ($person->ten ?? '')) : 'Unknown';
            $surveyFormId = $detail->survey_form_id;

            // Thực hiện xóa thật khỏi database
            DB::beginTransaction();
            
            // Xóa thật đối tượng điều tra
            DB::table('survey_form_details')->where('id', $detailId)->delete();
            
            // Kiểm tra xem person này có còn được tham chiếu bởi survey_form_details nào khác không
            // (bao gồm cả các năm khác)
            $remainingReferences = DB::table('survey_form_details')
                ->where('person_id', $detail->person_id)
                ->count();
            
            // Chỉ xóa person nếu không còn tham chiếu nào khác
            if ($remainingReferences == 0) {
                // Kiểm tra thêm các bảng liên quan khác trước khi xóa person
                $hasRelatedData = DB::table('person_educations')->where('person_id', $detail->person_id)->exists() ||
                                 DB::table('person_literacy')->where('person_id', $detail->person_id)->exists() ||
                                 DB::table('person_disabilities')->where('person_id', $detail->person_id)->exists() ||
                                 DB::table('person_special_circumstances')->where('person_id', $detail->person_id)->exists() ||
                                 DB::table('person_contacts')->where('person_id', $detail->person_id)->exists() ||
                                 DB::table('person_mamnon')->where('person_id', $detail->person_id)->exists();
                
                if (!$hasRelatedData) {
                    DB::table('people')->where('id', $detail->person_id)->delete();
                } else {
                    \Illuminate\Support\Facades\Log::info('Person has related data, not deleting', [
                        'personId' => $detail->person_id,
                        'detailId' => $detailId
                    ]);
                }
            }
            
            DB::commit();

            \Illuminate\Support\Facades\Log::info('Hard delete successful', [
                'detailId' => $detailId,
                'personName' => $personName,
                'surveyFormId' => $surveyFormId,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa thật đối tượng điều tra thành công.'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Hard delete error: ' . $e->getMessage(), [
                'detailId' => $detailId,
                'exception' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa thật đối tượng.'.$e->getMessage()
            ], 500);
        }
    }

    
    public function bulkSoftDelete(Request $request)
    {
        // 0) Quyền + enforce scheme/new + merge geo cho non-admin
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete');
    
        // 1) Chuẩn hoá ids
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
        }
        $ids = collect($ids)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->unique()->values()->all();
       
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có đối tượng nào được chọn.'
            ], 400);
        }

        try {
            // 2) Lấy details + survey cha để kiểm tra phạm vi/lock (eager load để tránh N+1)
            $details = SurveyFormDetail::query()
                ->with(['surveyForm:id,new_province_id,new_ward_id,village_id,old_province_id,old_ward_id'])
                ->whereIn('id', $ids)
                ->where('is_deleted', false)
                ->get();
            // Những id không tồn tại / đã xóa mềm
            $notFound = array_values(array_diff($ids, $details->pluck('id')->all()));

            $allowedIds = [];
            $skipped    = [];

            foreach ($notFound as $miss) {
                $skipped[] = ['id' => $miss, 'status' => 404, 'reason' => 'Không tìm thấy hoặc đã xóa.'];
            }

            foreach ($details as $detail) {
                // Nếu vì lý do gì đó không có survey cha -> bỏ qua
                if (!$detail->surveyForm) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Thiếu liên kết survey.'];
                    continue;
                }

                try {
                    // 3) Kiểm tra phạm vi + lock theo survey cha (scope SURVEY)
                    //    Hàm này bạn đã có trong AccessGuardService theo đề xuất trước:
                    //    - Kiểm tra record có nằm trong geo của user không
                    //    - Kiểm tra locks (SURVEY) theo geo của survey
                    $this->guard->guardRecordGeoAndLock($request, $detail->surveyForm, 'SURVEY');
                    // dd($ids);
                    // Nếu qua được -> add vào danh sách hợp lệ
                    $allowedIds[] = $detail->id;
                } catch (HttpException $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => 403, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($allowedIds)) {
                $msg = 'Không có đối tượng nào hợp lệ trong phạm vi để xóa.';
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'skipped' => $skipped,
                ], 422);
            }

            // 4) Xóa mềm tất cả detail hợp lệ trong một query
            $count = SurveyFormDetail::whereIn('id', $allowedIds)
                ->where('is_deleted', false)
                ->update(['is_deleted' => true]);

            

            return response()->json([
                'success'     => true,
                'message'     => "Đã xóa tạm thành công {$count} đối tượng điều tra.",
                'count'       => $count,
                'deleted_ids' => $allowedIds,
                'skipped'     => $skipped,
            ]);

        } catch (\Throwable $e) {
           
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống:  .'.$e->getMessage()
            ], 500);
        }
    }
    /**
     * Xóa thật nhiều đối tượng cùng lúc
     */
    // public function bulkHardDelete(Request $request)
    // {
    //     \Illuminate\Support\Facades\Log::info('bulkHardDelete called', [
    //         'request_data' => $request->all(),
    //         'user_id' => auth()->id()
    //     ]);

    //     // Kiểm tra quyền xóa đối tượng điều tra
    //     $user = \Illuminate\Support\Facades\Auth::user();
    //     $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete');

    //     $ids = $request->input('ids', []);
    //     \Illuminate\Support\Facades\Log::info('IDs received', ['ids' => $ids]);
        
    //     if (empty($ids) || !is_array($ids)) {
    //         \Illuminate\Support\Facades\Log::warning('No valid IDs provided', ['ids' => $ids]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Không có đối tượng nào được chọn.'
    //         ], 400);
    //     }

    //     try {
    //         // Lấy thông tin trước khi xóa để log
    //         $details = SurveyFormDetail::whereIn('id', $ids)->with('person')->get();
    //         $personNames = $details->map(function($detail) {
    //             return trim(($detail->person->ho_dem ?? '') . ' ' . ($detail->person->ten ?? ''));
    //         })->toArray();

    //         \Illuminate\Support\Facades\Log::info('Starting bulk hard delete', [
    //             'ids' => $ids,
    //             'personNames' => $personNames,
    //             'user_id' => auth()->id()
    //         ]);

    //         // Thử xóa trực tiếp bằng DB query
    //         $count = DB::table('survey_form_details')
    //             ->whereIn('id', $ids)
    //             ->delete();

    //         \Illuminate\Support\Facades\Log::info('Direct DB delete result', [
    //             'ids' => $ids,
    //             'deleted_count' => $count
    //         ]);

    //         \Illuminate\Support\Facades\Log::info('Bulk hard delete successful', [
    //             'ids' => $ids,
    //             'personNames' => $personNames,
    //             'count' => $count,
    //             'user_id' => auth()->id()
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => "Đã xóa thật thành công {$count} đối tượng điều tra.",
    //             'count' => $count
    //         ]);

    //     } catch (\Exception $e) {
    //         \Illuminate\Support\Facades\Log::error('Bulk hard delete error: ' . $e->getMessage(), [
    //             'ids' => $ids,
    //             'exception' => $e->getTraceAsString()
    //         ]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Lỗi hệ thống: Không thể xóa thật các đối tượng.'
    //         ], 500);
    //     }
    // }

    public function bulkHardDelete(Request $request)
{
     

    // 0) Quyền + enforce scheme/new + merge geo cho non-admin
    $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete');

    // 1) Chuẩn hoá ids (hỗ trợ "1,2,3" hoặc mảng)
    $ids = $request->input('ids', []);
    if (is_string($ids)) {
        $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
    }
    $ids = collect($ids)
        ->filter(fn($v) => is_numeric($v))
        ->map(fn($v) => (int) $v)
        ->unique()->values()->all();

    if (empty($ids)) {
        
        return response()->json([
            'success' => false,
            'message' => 'Không có đối tượng nào được chọn.'
        ], 400);
    }

    try {
        // 2) Lấy details + survey cha để kiểm tra phạm vi/lock (eager load tránh N+1).
        $details = SurveyFormDetail::query()
            ->with([
                'person:id,ho_dem,ten',
                'surveyForm:id,new_province_id,new_ward_id,village_id,old_province_id,old_ward_id'
            ])
            ->whereIn('id', $ids)
            ->get();

        // Id không tồn tại (hoặc đã bị xóa từ trước)
        $notFound = array_values(array_diff($ids, $details->pluck('id')->all()));

        $allowedIds = [];
        $skipped    = [];

        foreach ($notFound as $miss) {
            $skipped[] = ['id' => $miss, 'status' => 404, 'reason' => 'Không tìm thấy hoặc đã xóa.'];
        }

        foreach ($details as $detail) {
            // survey cha bắt buộc
            if (!$detail->surveyForm) {
                $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Thiếu liên kết survey.'];
                continue;
            }

            try {
                // 3) Kiểm tra phạm vi + lock theo survey cha (scope SURVEY)
                //    Hàm guardRecordGeoAndLock() đã đề xuất trước:
                //    - Check record có nằm trong geo user (non-admin) không
                //    - Check lock theo scope SURVEY + geo của survey
                $this->guard->guardRecordGeoAndLock($request, $detail->surveyForm, 'SURVEY');

                $allowedIds[] = $detail->id;
            } catch (HttpException $e) {
                $skipped[] = ['id' => $detail->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
            } catch (\Throwable $e) {
                $skipped[] = ['id' => $detail->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
            }
        }

        if (empty($allowedIds)) {
            $msg = 'Không có đối tượng nào hợp lệ trong phạm vi để xóa.';
            return response()->json([
                'success' => false,
                'message' => $msg,
                'skipped' => $skipped,
            ], 422);
        }

        // 4) (Tuỳ chọn) Log tên người trước khi xóa để audit
        $personNames = $details->whereIn('id', $allowedIds)->map(function ($d) {
            return trim(($d->person->ho_dem ?? '') . ' ' . ($d->person->ten ?? ''));
        })->filter()->values()->all();

        

        // 5) Xóa cứng — dùng transaction; một query là đủ, nhưng có thể chunk nếu danh sách lớn
        $deleted = 0;
        DB::transaction(function () use ($allowedIds, &$deleted) {
            // Nếu có ràng buộc khóa ngoại, hãy cân nhắc xoá phụ thuộc trước.
            $deleted = DB::table('survey_form_details')
                ->whereIn('id', $allowedIds)
                ->delete();
        });

       

        return response()->json([
            'success'      => true,
            'message'      => "Đã xóa thật thành công {$deleted} đối tượng điều tra.",
            'count'        => $deleted,
            'deleted_ids'  => $allowedIds,
            'skipped'      => $skipped,
        ]);

    } catch (\Throwable $e) {
        
        return response()->json([
            'success' => false,
            'message' => 'Lỗi hệ thống: Không thể xóa thật các đối tượng.'.$e->getMessage()
        ], 500);
    }
}

    /**
     * Lấy danh sách phiếu điều tra đã xóa tạm
     */
    public function getDeletedSurveys($year, $scheme, $geoIds, $soPhieu, $q, $perPage = 10)
    {
        
        $query = SurveyForm::query()
            ->where('is_deleted', true)
            ->with([
                'headPerson:id,ho_dem,ten',
                'oldWard:id,name',
                'newWard:id,name', 
                'oldDistrict:id,name',
                'oldProvince:id,name',
                'newProvince:id,name',
                'village:id,name'
            ]);

        // Lọc theo số phiếu
        if ($soPhieu !== '') {
            $query->where('so_phieu', 'like', "%{$soPhieu}%");
        }

        // Lọc theo tên chủ hộ
        if ($q !== '') {
            $query->whereHas('headPerson', function ($pp) use ($q) {
                $pp->where('ten', 'like', "%{$q}%")
                   ->orWhere('ho_dem', 'like', "%{$q}%")
                   ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$q}%"]);
            });
        }

        // Lọc theo địa bàn
        $applyGeo = collect($geoIds)->filter()->isNotEmpty();
        if ($applyGeo) {
            $query->where(function ($qq) use ($scheme, $geoIds) {
                if ($scheme === 'old') {
                    if ($geoIds['province']) $qq->where('old_province_id', $geoIds['province']);
                    if ($geoIds['district']) $qq->where('old_district_id', $geoIds['district']);
                    if ($geoIds['ward']) $qq->where('old_ward_id', $geoIds['ward']);
                } else { // new
                    if ($geoIds['province']) $qq->where('new_province_id', $geoIds['province']);
                    if ($geoIds['ward']) $qq->where('new_ward_id', $geoIds['ward']);
                }
                if ($geoIds['village']) $qq->where('village_id', $geoIds['village']);
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page_surveys')->withQueryString();
    }

    /**
     * Phục hồi phiếu điều tra đã xóa tạm
     */
    public function restoreSurvey(Request $request, $surveyId)
    {
        // \Illuminate\Support\Facades\Log::info('Restore survey called', [
        //     'surveyId' => $surveyId,
        //     'user_id' => auth()->id()
        // ]);

        // // Kiểm tra quyền phục hồi phiếu điều tra
        // $user = \Illuminate\Support\Facades\Auth::user();
        // if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'edit')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Bạn không có quyền phục hồi phiếu điều tra.'
        //     ], 403);
        // }
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit');
        try {
            $survey = SurveyForm::where('is_deleted', true)->find($surveyId);
            
            if (!$survey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phiếu điều tra.'
                ], 404);
            }

            $ctx = $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
            DB::beginTransaction();

            // Phục hồi phiếu
            $survey->update(['is_deleted' => false]);

            // Phục hồi chi tiết
            SurveyFormDetail::where('survey_form_id', $survey->id)
                ->update(['is_deleted' => false]);

            // Phục hồi people liên quan
            Person::where('so_phieu', $survey->so_phieu)
                ->update(['is_deleted' => false]);

            DB::commit();

            \Illuminate\Support\Facades\Log::info('Survey restored successfully', [
                'surveyId' => $surveyId,
                'so_phieu' => $survey->so_phieu
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã phục hồi phiếu điều tra thành công.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Restore survey error: ' . $e->getMessage(), [
                'surveyId' => $surveyId,
                'exception' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể phục hồi phiếu.'
            ], 500);
        }
    }

    /**
     * Xóa vĩnh viễn phiếu điều tra
     */
    public function hardDeleteSurvey(Request $request, $surveyId)
    {
        \Illuminate\Support\Facades\Log::info('Hard delete survey called', [
            'surveyId' => $surveyId,
            'user_id' => auth()->id()
        ]);

        // Kiểm tra quyền xóa phiếu điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa phiếu điều tra.'
            ], 403);
        }

        try {
            $survey = SurveyForm::where('is_deleted', true)->find($surveyId);
            
            if (!$survey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phiếu điều tra.'
                ], 404);
            }

            DB::beginTransaction();

            // Xóa thật chi tiết khỏi database
            DB::table('survey_form_details')->where('survey_form_id', $survey->id)->delete();

            // Xóa thật people liên quan khỏi database
            DB::table('people')->where('so_phieu', $survey->so_phieu)->delete();

            // Xóa thật phiếu khỏi database
            DB::table('survey_forms')->where('id', $survey->id)->delete();

            DB::commit();

            \Illuminate\Support\Facades\Log::info('Survey hard deleted successfully', [
                'surveyId' => $surveyId,
                'so_phieu' => $survey->so_phieu
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa vĩnh viễn phiếu điều tra thành công.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Hard delete survey error: ' . $e->getMessage(), [
                'surveyId' => $surveyId,
                'exception' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa phiếu.'
            ], 500);
        }
    }

    /**
     * Bulk restore đối tượng đã xóa tạm
     */
    // public function bulkRestore(Request $request)
    // {
    //     // Kiểm tra quyền phục hồi đối tượng điều tra
    //     $user = \Illuminate\Support\Facades\Auth::user();
    //     // if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit')) {
    //     //     return response()->json([
    //     //         'success' => false,
    //     //         'message' => 'Bạn không có quyền phục hồi đối tượng điều tra.'
    //     //     ], 403);
    //     // }
    //     $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit');
    //     $ids = $request->input('ids', []);
    //     if (empty($ids) || !is_array($ids)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Không có đối tượng nào được chọn.'
    //         ], 400);
    //     }

    //     try {
    //         $successCount = 0;
    //         $errorCount = 0;
    //         $deletedSurveys = [];

    //         foreach ($ids as $id) {
    //             $detail = SurveyFormDetail::with('surveyForm')->find($id);
                
    //             if (!$detail || !$detail->is_deleted) {
    //                 $errorCount++;
    //                 continue;
    //             }

    //             // Kiểm tra xem phiếu có đang bị xóa tạm không
    //             if ($detail->surveyForm && $detail->surveyForm->is_deleted) {
    //                 $deletedSurveys[] = $detail->surveyForm->so_phieu;
    //                 $errorCount++;
    //                 continue;
    //             }

    //             // Phục hồi đối tượng
    //             $detail->update(['is_deleted' => false]);
    //             $successCount++;
    //         }

    //         // Loại bỏ phiếu trùng lặp
    //         $uniqueDeletedSurveys = array_unique($deletedSurveys);

    //         if ($successCount > 0 && $errorCount === 0) {
    //             // Tất cả đều thành công
    //             return response()->json([
    //                 'success' => true,
    //                 'message' => "Đã phục hồi thành công {$successCount} đối tượng"
    //             ]);
    //         } elseif ($successCount > 0 && $errorCount > 0) {
    //             // Một phần thành công
    //             $message = "Đã phục hồi thành công {$successCount} đối tượng. ";
    //             $message .= "{$errorCount} đối tượng không thể phục hồi do các phiếu: " . implode(', ', $uniqueDeletedSurveys) . " đang bị xóa tạm.";
    //             $message .= " Vui lòng phục hồi các phiếu điều tra trước.";
                
    //             return response()->json([
    //                 'success' => true,
    //                 'message' => $message,
    //                 'success_count' => $successCount,
    //                 'error_count' => $errorCount,
    //                 'deleted_surveys' => $uniqueDeletedSurveys
    //             ]);
    //         } else {
    //             // Tất cả đều thất bại
    //             $message = "Không thể phục hồi {$errorCount} đối tượng do các phiếu: " . implode(', ', $uniqueDeletedSurveys) . " đang bị xóa tạm.";
    //             $message .= " Vui lòng phục hồi các phiếu điều tra trước.";
                
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => $message,
    //                 'error_count' => $errorCount,
    //                 'deleted_surveys' => $uniqueDeletedSurveys
    //             ]);
    //         }

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Lỗi hệ thống: Không thể phục hồi các đối tượng.'
    //         ], 500);
    //     }
    // }
public function bulkRestore(Request $request)
{
    
 
    // 0) Quyền + enforce scheme/new + merge geo cho non-admin
    $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit');

    // 1) Chuẩn hóa danh sách ids: hỗ trợ "1,2,3" hoặc mảng
    $ids = $request->input('ids', []);
    if (is_string($ids)) {
        $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
    }
    $ids = collect($ids)
        ->filter(fn($v) => is_numeric($v))
        ->map(fn($v) => (int) $v)
        ->unique()->values()->all();

    if (empty($ids)) {
        return response()->json([
            'success' => false,
            'message' => 'Không có đối tượng nào được chọn.'
        ], 400);
    }

    try {
        // 2) Lấy details + survey cha để kiểm tra (dùng quan hệ 'survey' — nếu bạn dùng 'surveyForm' thì đổi lại)
        $details = SurveyFormDetail::query()
            ->with([
                'surveyForm:id,so_phieu,is_deleted,new_province_id,new_ward_id,village_id,old_province_id,old_ward_id',
                'person:id,ho_dem,ten',
            ])
            ->whereIn('id', $ids)
            ->get();

        // Những id không tồn tại
        $notFound = array_values(array_diff($ids, $details->pluck('id')->all()));

        $restoreIds = [];
        $skipped    = [];
        $deletedSurveys = []; // gom so_phieu các phiếu cha đang bị xóa

        foreach ($notFound as $miss) {
            $skipped[] = ['id' => $miss, 'status' => 404, 'reason' => 'Không tìm thấy hoặc đã xóa.'];
        }

        foreach ($details as $detail) {
            // 2.1) Phải đang bị xoá tạm mới khôi phục
            if (!$detail->is_deleted) {
                $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Đối tượng chưa bị xóa tạm.'];
                continue;
            }

            // 2.2) Có survey cha?
            $survey = $detail->surveyForm ?? null; // nếu bạn đặt tên là surveyForm, đổi sang $detail->surveyForm
            if (!$survey) {
                $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Thiếu liên kết phiếu (survey).'];
                continue;
            }

            // 2.3) Phiếu cha không được bị xóa
            if ($survey->is_deleted) {
                $deletedSurveys[] = $survey->so_phieu;
                $skipped[] = [
                    'id'     => $detail->id,
                    'status' => 409,
                    'reason' => 'Phiếu cha đang bị xóa tạm. Vui lòng phục hồi phiếu trước.'
                ];
                continue;
            }

            // 2.4) Kiểm tra phạm vi + khóa SURVEY (giống hàm delete)
            try {
                // Hàm này bạn đã có trong AccessGuardService (như mình gợi ý trước đó):
                // - Check record nằm trong geo user (non-admin)
                // - Check lock với scope SURVEY theo geo của survey
                $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');

                $restoreIds[] = $detail->id;
            } catch (HttpException $e) {
                $skipped[] = ['id' => $detail->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
            } catch (\Throwable $e) {
                $skipped[] = ['id' => $detail->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
            }
        }

        if (empty($restoreIds)) {
            $msg = 'Không có đối tượng nào hợp lệ trong phạm vi để phục hồi.';
            if ($deletedSurveys) {
                $msg .= ' Các phiếu cần phục hồi trước: ' . implode(', ', array_unique($deletedSurveys)) . '.';
            }
            return response()->json([
                'success' => false,
                'message' => $msg,
                'skipped' => $skipped,
            ], 422);
        }

        // 3) Thực hiện restore
        $restored = SurveyFormDetail::query()
            ->whereIn('id', $restoreIds)
            ->update(['is_deleted' => false]);

       

        // 4) Tổng kết
        $msg = "Đã phục hồi thành công {$restored} đối tượng.";
        if ($deletedSurveys) {
            $msg .= ' Một số đối tượng không thể phục hồi do phiếu cha đang bị xóa: '
                 . implode(', ', array_unique($deletedSurveys)) . '.';
        }

        return response()->json([
            'success'       => true,
            'message'       => $msg,
            'restored'      => $restored,
            'restored_ids'  => $restoreIds,
            'skipped'       => $skipped,
            'blocked_forms' => array_values(array_unique($deletedSurveys)),
        ]);

    } catch (\Throwable $e) {
        Log::error('Bulk restore error: '.$e->getMessage(), [
            'ids' => $ids,
            'exception' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Lỗi hệ thống: Không thể phục hồi các đối tượng.'.$e->getMessage()
        ], 500);
    }
}
    /**
     * Bulk hard delete đối tượng đã xóa tạm
     */
    public function bulkHardDeleteTargets(Request $request)
    {
        

        // 0) Quyền + enforce scheme/new + merge geo cho non-admin
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete');

        // 1) Chuẩn hóa danh sách ids (hỗ trợ "1,2,3" hoặc mảng)
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
        }
        $ids = collect($ids)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->unique()->values()->all();

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có đối tượng nào được chọn.'
            ], 400);
        }

        try {
            // 2) Lấy details + survey cha + person
            //    (Quan hệ dùng 'survey'. Nếu của bạn là 'surveyForm' thì đổi lại.)
            $details = SurveyFormDetail::query()
                ->with([
                    'surveyForm:id,so_phieu,is_deleted,new_province_id,new_ward_id,village_id,old_province_id,old_ward_id',
                    'person:id',
                ])
                ->whereIn('id', $ids)
                ->get();

            // Những id không tồn tại
            $notFound = array_values(array_diff($ids, $details->pluck('id')->all()));

            $deleteDetailIds  = [];
            $skipped          = [];
            $personIdsTouched = [];   // person liên quan tới những detail hợp lệ (để xét xóa sau)

            foreach ($notFound as $miss) {
                $skipped[] = ['id' => $miss, 'status' => 404, 'reason' => 'Không tìm thấy hoặc đã xóa.'];
            }

            foreach ($details as $detail) {
                // 2.1) Phải đang bị xóa tạm mới cho xóa cứng
                if (!$detail->is_deleted) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Đối tượng chưa bị xóa tạm.'];
                    continue;
                }

                // 2.2) Có survey cha?
                $survey = $detail->surveyForm ?? null;
                if (!$survey) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Thiếu liên kết phiếu (survey).'];
                    continue;
                }

                // 2.3) Kiểm tra phạm vi + lock SURVEY (giống các hàm delete/restore trước)
                try {
                    // Hàm trung tâm: check record nằm trong geo user (non-admin) + check lock scope SURVEY
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');

                    $deleteDetailIds[]  = $detail->id;
                    if ($detail->person_id) {
                        $personIdsTouched[] = (int) $detail->person_id;
                    }
                } catch (HttpException $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($deleteDetailIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có đối tượng nào hợp lệ trong phạm vi để xóa thật.',
                    'skipped' => $skipped,
                ], 422);
            }

            // 3) Xóa trong transaction
            DB::beginTransaction();

            // 3.1) Xóa cứng các detail hợp lệ
            $deletedDetails = DB::table('survey_form_details')
                ->whereIn('id', $deleteDetailIds)
                ->delete();

            // 3.2) Xóa Person không còn tham chiếu & không còn dữ liệu phụ
            $deletedPersonIds = [];
            $personIdsTouched = array_values(array_unique($personIdsTouched));

            if (!empty($personIdsTouched)) {
                // Đếm tham chiếu còn lại sau khi đã xóa detail ở trên
                $remaining = DB::table('survey_form_details')
                    ->select('person_id', DB::raw('COUNT(*) as cnt'))
                    ->whereIn('person_id', $personIdsTouched)
                    ->groupBy('person_id')
                    ->pluck('cnt', 'person_id'); // [person_id => cnt]

                foreach ($personIdsTouched as $pid) {
                    $cnt = (int) ($remaining[$pid] ?? 0);
                    if ($cnt > 0) continue; // vẫn còn detail khác tham chiếu

                    // Kiểm tra dữ liệu ở các bảng phụ
                    $hasRelated = DB::table('person_educations')->where('person_id', $pid)->exists()
                                || DB::table('person_literacy')->where('person_id', $pid)->exists()
                                || DB::table('person_disabilities')->where('person_id', $pid)->exists()
                                || DB::table('person_special_circumstances')->where('person_id', $pid)->exists()
                                || DB::table('person_contacts')->where('person_id', $pid)->exists()
                                || DB::table('person_mamnon')->where('person_id', $pid)->exists();

                    if (!$hasRelated) {
                        DB::table('people')->where('id', $pid)->delete();
                        $deletedPersonIds[] = $pid;
                    }
                }
            }

            DB::commit();

            
            $msg = "Đã xóa thật thành công {$deletedDetails} đối tượng điều tra.";
            return response()->json([
                'success'             => true,
                'message'             => $msg,
                'deleted_details'     => $deletedDetails,
                'deleted_detail_ids'  => $deleteDetailIds,
                'deleted_person_ids'  => $deletedPersonIds,
                'skipped'             => $skipped,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa thật các đối tượng.'.$e->getMessage()
            ], 500);
        }
    }
    /**
     * Bulk restore phiếu đã xóa tạm
     */
    public function bulkRestoreSurveys(Request $request)
    {
        // 0) Quyền + enforce (ép scheme=new, merge geo cho non-admin)
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'edit');

        // 1) Chuẩn hóa danh sách ids (hỗ trợ chuỗi "1,2,3" hoặc mảng)
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
        }
        $ids = collect($ids)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->unique()->values()->all();

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có phiếu nào được chọn.'
            ], 400);
        }

        try {
            // 2) Lấy danh sách phiếu đang xóa tạm
            $surveys = \App\Models\SurveyForm::query()
                ->select(['id','so_phieu','is_deleted' ])
                ->whereIn('id', $ids)
                ->where('is_deleted', true)
                ->get();

            $notFound = array_values(array_diff($ids, $surveys->pluck('id')->all()));

            $restoredSurveyIds  = [];
            $restoredDetails    = 0;
            $restoredPeople     = 0;
            $skipped            = [];

            foreach ($notFound as $miss) {
                $skipped[] = ['id' => $miss, 'status' => 404, 'reason' => 'Không tìm thấy hoặc không ở trạng thái xóa tạm.'];
            }

            if ($surveys->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phiếu nào hợp lệ để phục hồi.',
                    'skipped' => $skipped
                ], 422);
            }

            DB::beginTransaction();

            foreach ($surveys as $survey) {
                try {
                    // 3) Kiểm tra phạm vi + lock SURVEY (ghi/khôi phục cũng là thao tác ghi)
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');

                    // 4) Phục hồi phiếu
                    \App\Models\SurveyForm::where('id', $survey->id)
                        ->update(['is_deleted' => false]);

                    $restoredSurveyIds[] = $survey->id;

                    // 5) Phục hồi mọi chi tiết thuộc phiếu
                    $restoredDetails += \App\Models\SurveyFormDetail::query()
                        ->where('survey_form_id', $survey->id)
                        ->where('is_deleted', true)
                        ->update(['is_deleted' => false]);

                    // 6) Phục hồi People được tham chiếu bởi các chi tiết của phiếu
                    $personIds = \App\Models\SurveyFormDetail::query()
                        ->where('survey_form_id', $survey->id)
                        ->whereNotNull('person_id')
                        ->pluck('person_id')
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($personIds)) {
                        $restoredPeople += \App\Models\Person::query()
                            ->whereIn('id', $personIds)
                            ->where('is_deleted', true)
                            ->update(['is_deleted' => false]);
                    } else {
                        // (Tuỳ schema) fallback theo so_phieu nếu bạn đang lưu so_phieu trong people
                        // $restoredPeople += \App\Models\Person::query()
                        //     ->where('so_phieu', $survey->so_phieu)
                        //     ->where('is_deleted', true)
                        //     ->update(['is_deleted' => false]);
                    }

                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    // out-of-scope hoặc bị lock
                    $skipped[] = ['id' => $survey->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $survey->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            DB::commit();

            $okCount = count($restoredSurveyIds);
            if ($okCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phiếu nào hợp lệ để phục hồi.',
                    'skipped' => $skipped
                ], 422);
            }

            $msg = "Đã phục hồi thành công {$okCount} phiếu"
                . ($restoredDetails ? " · {$restoredDetails} chi tiết" : "")
                . ($restoredPeople ? " · {$restoredPeople} người" : "")
                . ".";

            return response()->json([
                'success'             => true,
                'message'             => $msg,
                'restored_survey_ids' => $restoredSurveyIds,
                'restored_details'    => $restoredDetails,
                'restored_people'     => $restoredPeople,
                'skipped'             => $skipped,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
             
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể phục hồi các phiếu.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk hard delete phiếu đã xóa tạm
     */
    public function bulkHardDeleteSurveys(Request $request)
    {
        // 0) Quyền + enforce (ép scheme=new, merge geo cho non-admin)
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'delete');

        // 1) Chuẩn hóa ids (hỗ trợ "1,2,3" hoặc mảng)
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', trim($ids), -1, PREG_SPLIT_NO_EMPTY);
        }
        $ids = collect($ids)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->unique()->values()->all();

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có phiếu nào được chọn.'
            ], 400);
        }

        try {
            // 2) Lấy danh sách phiếu đang xóa tạm
            $surveys = \App\Models\SurveyForm::query()
                ->select(['id','so_phieu','is_deleted' ])
                ->whereIn('id', $ids)
                ->where('is_deleted', true)
                ->get();

            $notFound = array_values(array_diff($ids, $surveys->pluck('id')->all()));

            $deletedSurveys   = 0;
            $deletedDetails   = 0;
            $deletedPeople    = 0;
            $skipped          = [];

            foreach ($notFound as $miss) {
                $skipped[] = ['id' => $miss, 'status' => 404, 'reason' => 'Không tìm thấy hoặc không ở trạng thái xóa tạm.'];
            }

            if ($surveys->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phiếu nào hợp lệ để xóa thật.',
                    'skipped' => $skipped
                ], 422);
            }

            DB::beginTransaction();

            foreach ($surveys as $survey) {
                try {
                    // 3) Kiểm tra phạm vi + lock SURVEY
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');

                    // 4) Lấy tất cả person_id được tham chiếu bởi chi tiết của phiếu
                    $personIds = DB::table('survey_form_details')
                        ->where('survey_form_id', $survey->id)
                        ->whereNotNull('person_id')
                        ->pluck('person_id')
                        ->unique()
                        ->values()
                        ->all();

                    // 5) Xóa thật mọi chi tiết thuộc phiếu
                    $deletedDetails += DB::table('survey_form_details')
                        ->where('survey_form_id', $survey->id)
                        ->delete();

                    // 6) Xóa thật phiếu
                    DB::table('survey_forms')->where('id', $survey->id)->delete();
                    $deletedSurveys++;

                    // 7) Xóa People mồ côi (không còn chi tiết nào tham chiếu)
                    if (!empty($personIds)) {
                        // Chỉ giữ những person đã mồ côi
                        $orphanIds = collect($personIds)->filter(function ($pid) {
                            $refs = DB::table('survey_form_details')->where('person_id', $pid)->count();
                            return $refs === 0;
                        })->values()->all();

                        if (!empty($orphanIds)) {
                            // (Tuỳ schema) Nếu cần kiểm tra bảng liên quan khác trước khi xóa, có thể mở rộng như dưới:
                            // Giữ lại những person KHÔNG có dữ liệu liên quan
                            $final = collect($orphanIds)->filter(function ($pid) {
                                $hasRelated =
                                    DB::table('person_educations')->where('person_id', $pid)->exists() ||
                                    DB::table('person_literacy')->where('person_id', $pid)->exists() ||
                                    DB::table('person_disabilities')->where('person_id', $pid)->exists() ||
                                    DB::table('person_special_circumstances')->where('person_id', $pid)->exists() ||
                                    DB::table('person_contacts')->where('person_id', $pid)->exists() ||
                                    DB::table('person_mamnon')->where('person_id', $pid)->exists();
                                return !$hasRelated;
                            })->values()->all();

                            if (!empty($final)) {
                                $deletedPeople += DB::table('people')->whereIn('id', $final)->delete();
                            }
                        }
                    }

                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    // out-of-scope hoặc đang bị lock
                    $skipped[] = ['id' => $survey->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $survey->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            DB::commit();

            if ($deletedSurveys === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phiếu nào hợp lệ để xóa thật.',
                    'skipped' => $skipped
                ], 422);
            }

            $msg = "Đã xóa thật {$deletedSurveys} phiếu"
                . ($deletedDetails ? " · {$deletedDetails} chi tiết" : "")
                . ($deletedPeople ? " · {$deletedPeople} người" : "")
                . ".";

            return response()->json([
                'success'          => true,
                'message'          => $msg,
                'deleted_surveys'  => $deletedSurveys,
                'deleted_details'  => $deletedDetails,
                'deleted_people'   => $deletedPeople,
                'skipped'          => $skipped,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
        
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa thật các phiếu.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Phục hồi tất cả đối tượng theo bộ lọc hiện tại
     */
    public function restoreAllTargets(Request $request)
    {
        // Kiểm tra quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit');
        
        $defaultYear = SurveyForm::currentAcademicYear();
        $year        = request('year', $defaultYear);
        $q           = trim((string) request('q'));
        $soPhieu     = trim((string) request('so_phieu'));
        $scheme      = request('scheme', 'old');
        $geoIn       = (array) request()->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
        ];

        $applyGeo = collect($geoIds)->filter()->isNotEmpty();

        try {
            // Xây dựng query giống như method deleted()
            $query = SurveyFormDetail::query()
                ->where('is_deleted', true)
                ->where('academic_year', $year)
                ->with(['surveyForm:id,so_phieu,is_deleted,new_province_id,new_ward_id,village_id,old_province_id,old_ward_id'])
                ->when($soPhieu !== '', fn($qq) =>
                    $qq->whereHas('surveyForm', fn($f) => $f->where('so_phieu','like',"%{$soPhieu}%")))
                ->when($q !== '', function ($qq) use ($q) {
                    $qq->whereHas('person', function ($pp) use ($q) {
                        $pp->where('ten','like',"%{$q}%")
                           ->orWhere('ho_dem','like',"%{$q}%")
                           ->orWhere('identity_number','like',"%{$q}%")
                           ->orWhere('phone','like',"%{$q}%")
                           ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$q}%"]);
                    });
                })
                ->when($applyGeo, function ($qq) use ($scheme, $geoIds) {
                    $qq->whereHas('surveyForm', function ($sf) use ($scheme, $geoIds) {
                        if ($scheme === 'old') {
                            if ($geoIds['province']) $sf->where('old_province_id', $geoIds['province']);
                            if ($geoIds['district']) $sf->where('old_district_id', $geoIds['district']);
                            if ($geoIds['ward'])     $sf->where('old_ward_id',     $geoIds['ward']);
                        } else {
                            if ($geoIds['province']) $sf->where('new_province_id', $geoIds['province']);
                            if ($geoIds['ward'])     $sf->where('new_ward_id',     $geoIds['ward']);
                        }
                        if ($geoIds['village']) $sf->where('village_id', $geoIds['village']);
                    });
                });

            // Lấy tất cả details để kiểm tra
            $details = $query->get();
            
            $restoreIds = [];
            $skipped = [];
            $deletedSurveys = [];

            foreach ($details as $detail) {
                if (!$detail->is_deleted) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Đối tượng chưa bị xóa tạm.'];
                    continue;
                }

                $survey = $detail->surveyForm ?? null;
                if (!$survey) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Thiếu liên kết phiếu.'];
                    continue;
                }

                // Kiểm tra phiếu cha không được bị xóa
                if ($survey->is_deleted) {
                    $deletedSurveys[] = $survey->so_phieu;
                    $skipped[] = [
                        'id' => $detail->id,
                        'status' => 409,
                        'reason' => 'Phiếu cha đang bị xóa tạm. Vui lòng phục hồi phiếu trước.'
                    ];
                    continue;
                }

                // Kiểm tra phạm vi và lock
                try {
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
                    $restoreIds[] = $detail->id;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($restoreIds)) {
                $msg = 'Không có đối tượng nào hợp lệ để phục hồi.';
                if ($deletedSurveys) {
                    $msg .= ' Các phiếu cần phục hồi trước: ' . implode(', ', array_unique($deletedSurveys)) . '.';
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'skipped' => $skipped,
                    'deleted_surveys' => array_values(array_unique($deletedSurveys))
                ], 422);
            }

            // Phục hồi tất cả
            $restored = SurveyFormDetail::whereIn('id', $restoreIds)
                ->update(['is_deleted' => false]);

            $msg = "Đã phục hồi thành công {$restored} đối tượng.";
            if ($deletedSurveys) {
                $msg .= ' Một số đối tượng không thể phục hồi do phiếu cha đang bị xóa: '
                     . implode(', ', array_unique($deletedSurveys)) . '.';
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'restored' => $restored,
                'skipped' => $skipped,
                'deleted_surveys' => array_values(array_unique($deletedSurveys))
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Restore all targets error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể phục hồi các đối tượng.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa thật tất cả đối tượng theo bộ lọc hiện tại
     */
    public function hardDeleteAllTargets(Request $request)
    {
        // Kiểm tra quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'delete');
        
        $defaultYear = SurveyForm::currentAcademicYear();
        $year        = request('year', $defaultYear);
        $q           = trim((string) request('q'));
        $soPhieu     = trim((string) request('so_phieu'));
        $scheme      = request('scheme', 'old');
        $geoIn       = (array) request()->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
        ];

        $applyGeo = collect($geoIds)->filter()->isNotEmpty();

        try {
            DB::beginTransaction();

            // Xây dựng query giống như method deleted()
            $query = SurveyFormDetail::query()
                ->where('is_deleted', true)
                ->where('academic_year', $year)
                ->with(['surveyForm:id,so_phieu,new_province_id,new_ward_id,village_id,old_province_id,old_ward_id'])
                ->when($soPhieu !== '', fn($qq) =>
                    $qq->whereHas('surveyForm', fn($f) => $f->where('so_phieu','like',"%{$soPhieu}%")))
                ->when($q !== '', function ($qq) use ($q) {
                    $qq->whereHas('person', function ($pp) use ($q) {
                        $pp->where('ten','like',"%{$q}%")
                           ->orWhere('ho_dem','like',"%{$q}%")
                           ->orWhere('identity_number','like',"%{$q}%")
                           ->orWhere('phone','like',"%{$q}%")
                           ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$q}%"]);
                    });
                })
                ->when($applyGeo, function ($qq) use ($scheme, $geoIds) {
                    $qq->whereHas('surveyForm', function ($sf) use ($scheme, $geoIds) {
                        if ($scheme === 'old') {
                            if ($geoIds['province']) $sf->where('old_province_id', $geoIds['province']);
                            if ($geoIds['district']) $sf->where('old_district_id', $geoIds['district']);
                            if ($geoIds['ward'])     $sf->where('old_ward_id',     $geoIds['ward']);
                        } else {
                            if ($geoIds['province']) $sf->where('new_province_id', $geoIds['province']);
                            if ($geoIds['ward'])     $sf->where('new_ward_id',     $geoIds['ward']);
                        }
                        if ($geoIds['village']) $sf->where('village_id', $geoIds['village']);
                    });
                });

            // Lấy tất cả details để kiểm tra
            $details = $query->get();
            
            $deleteIds = [];
            $skipped = [];
            $deletedPeople = 0;

            foreach ($details as $detail) {
                if (!$detail->is_deleted) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Đối tượng chưa bị xóa tạm.'];
                    continue;
                }

                $survey = $detail->surveyForm ?? null;
                if (!$survey) {
                    $skipped[] = ['id' => $detail->id, 'status' => 422, 'reason' => 'Thiếu liên kết phiếu.'];
                    continue;
                }

                // Kiểm tra phạm vi và lock
                try {
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
                    $deleteIds[] = $detail->id;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $detail->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($deleteIds)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không có đối tượng nào hợp lệ để xóa thật.',
                    'skipped' => $skipped
                ], 422);
            }

            // Xóa thật tất cả
            $deletedDetails = DB::table('survey_form_details')->whereIn('id', $deleteIds)->delete();

            // Kiểm tra và xóa people không còn tham chiếu
            $personIds = $details->whereIn('id', $deleteIds)->pluck('person_id')->unique();
            foreach ($personIds as $personId) {
                $remainingRefs = DB::table('survey_form_details')
                    ->where('person_id', $personId)
                    ->count();
                
                if ($remainingRefs == 0) {
                    $hasRelatedData = DB::table('person_educations')->where('person_id', $personId)->exists() ||
                                     DB::table('person_literacy')->where('person_id', $personId)->exists() ||
                                     DB::table('person_disabilities')->where('person_id', $personId)->exists() ||
                                     DB::table('person_special_circumstances')->where('person_id', $personId)->exists() ||
                                     DB::table('person_contacts')->where('person_id', $personId)->exists() ||
                                     DB::table('person_mamnon')->where('person_id', $personId)->exists();
                    
                    if (!$hasRelatedData) {
                        DB::table('people')->where('id', $personId)->delete();
                        $deletedPeople++;
                    }
                }
            }

            DB::commit();

            $msg = "Đã xóa thật {$deletedDetails} đối tượng";
            if ($deletedPeople > 0) {
                $msg .= " · {$deletedPeople} người";
            }
            $msg .= ".";

            return response()->json([
                'success' => true,
                'message' => $msg,
                'deleted_details' => $deletedDetails,
                'deleted_people' => $deletedPeople,
                'skipped' => $skipped
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Hard delete all targets error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa thật các đối tượng.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Phục hồi tất cả phiếu theo bộ lọc hiện tại
     */
    public function restoreAllSurveys(Request $request)
    {
        // Kiểm tra quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'edit');
        
        $defaultYear = SurveyForm::currentAcademicYear();
        $year        = request('year', $defaultYear);
        $q           = trim((string) request('q'));
        $soPhieu     = trim((string) request('so_phieu'));
        $scheme      = request('scheme', 'old');
        $geoIn       = (array) request()->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
        ];

        $applyGeo = collect($geoIds)->filter()->isNotEmpty();

        try {
            DB::beginTransaction();

            // Xây dựng query giống như method getDeletedSurveys()
            $query = SurveyForm::query()
                ->where('is_deleted', true);

            if ($soPhieu !== '') {
                $query->where('so_phieu', 'like', "%{$soPhieu}%");
            }

            if ($q !== '') {
                $query->whereHas('headPerson', function ($pp) use ($q) {
                    $pp->where('ten', 'like', "%{$q}%")
                       ->orWhere('ho_dem', 'like', "%{$q}%")
                       ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$q}%"]);
                });
            }

            if ($applyGeo) {
                $query->where(function ($qq) use ($scheme, $geoIds) {
                    if ($scheme === 'old') {
                        if ($geoIds['province']) $qq->where('old_province_id', $geoIds['province']);
                        if ($geoIds['district']) $qq->where('old_district_id', $geoIds['district']);
                        if ($geoIds['ward']) $qq->where('old_ward_id', $geoIds['ward']);
                    } else {
                        if ($geoIds['province']) $qq->where('new_province_id', $geoIds['province']);
                        if ($geoIds['ward']) $qq->where('new_ward_id', $geoIds['ward']);
                    }
                    if ($geoIds['village']) $qq->where('village_id', $geoIds['village']);
                });
            }

            // Lấy tất cả surveys để kiểm tra
            $surveys = $query->get();
            
            $restoreIds = [];
            $skipped = [];

            foreach ($surveys as $survey) {
                if (!$survey->is_deleted) {
                    $skipped[] = ['id' => $survey->id, 'status' => 422, 'reason' => 'Phiếu chưa bị xóa tạm.'];
                    continue;
                }

                // Kiểm tra phạm vi và lock
                try {
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
                    $restoreIds[] = $survey->id;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $skipped[] = ['id' => $survey->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $survey->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($restoreIds)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phiếu nào hợp lệ để phục hồi.',
                    'skipped' => $skipped
                ], 422);
            }

            // Phục hồi tất cả phiếu
            $restoredSurveys = SurveyForm::whereIn('id', $restoreIds)
                ->update(['is_deleted' => false]);

            // Phục hồi tất cả chi tiết
            $restoredDetails = SurveyFormDetail::whereIn('survey_form_id', $restoreIds)
                ->update(['is_deleted' => false]);

            // Phục hồi people liên quan
            $soPhieuList = SurveyForm::whereIn('id', $restoreIds)->pluck('so_phieu')->toArray();
            $restoredPeople = Person::whereIn('so_phieu', $soPhieuList)
                ->update(['is_deleted' => false]);

            DB::commit();

            $msg = "Đã phục hồi thành công {$restoredSurveys} phiếu";
            if ($restoredDetails > 0) {
                $msg .= " · {$restoredDetails} chi tiết";
            }
            if ($restoredPeople > 0) {
                $msg .= " · {$restoredPeople} người";
            }
            $msg .= ".";

            return response()->json([
                'success' => true,
                'message' => $msg,
                'restored_surveys' => $restoredSurveys,
                'restored_details' => $restoredDetails,
                'restored_people' => $restoredPeople,
                'skipped' => $skipped
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Restore all surveys error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể phục hồi các phiếu.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa thật tất cả phiếu theo bộ lọc hiện tại
     */
    public function hardDeleteAllSurveys(Request $request)
    {
        // Kiểm tra quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_phieu_dieu_tra', 'delete');
        
        $defaultYear = SurveyForm::currentAcademicYear();
        $year        = request('year', $defaultYear);
        $q           = trim((string) request('q'));
        $soPhieu     = trim((string) request('so_phieu'));
        $scheme      = request('scheme', 'old');
        $geoIn       = (array) request()->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
        ];

        $applyGeo = collect($geoIds)->filter()->isNotEmpty();

        try {
            DB::beginTransaction();

            // Xây dựng query giống như method getDeletedSurveys()
            $query = SurveyForm::query()
                ->where('is_deleted', true);

            if ($soPhieu !== '') {
                $query->where('so_phieu', 'like', "%{$soPhieu}%");
            }

            if ($q !== '') {
                $query->whereHas('headPerson', function ($pp) use ($q) {
                    $pp->where('ten', 'like', "%{$q}%")
                       ->orWhere('ho_dem', 'like', "%{$q}%")
                       ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$q}%"]);
                });
            }

            if ($applyGeo) {
                $query->where(function ($qq) use ($scheme, $geoIds) {
                    if ($scheme === 'old') {
                        if ($geoIds['province']) $qq->where('old_province_id', $geoIds['province']);
                        if ($geoIds['district']) $qq->where('old_district_id', $geoIds['district']);
                        if ($geoIds['ward']) $qq->where('old_ward_id', $geoIds['ward']);
                    } else {
                        if ($geoIds['province']) $qq->where('new_province_id', $geoIds['province']);
                        if ($geoIds['ward']) $qq->where('new_ward_id', $geoIds['ward']);
                    }
                    if ($geoIds['village']) $qq->where('village_id', $geoIds['village']);
                });
            }

            // Lấy tất cả surveys để kiểm tra
            $surveys = $query->get();
            
            $deleteIds = [];
            $skipped = [];
            $deletedDetails = 0;
            $deletedPeople = 0;

            foreach ($surveys as $survey) {
                if (!$survey->is_deleted) {
                    $skipped[] = ['id' => $survey->id, 'status' => 422, 'reason' => 'Phiếu chưa bị xóa tạm.'];
                    continue;
                }

                // Kiểm tra phạm vi và lock
                try {
                    $this->guard->guardRecordGeoAndLock($request, $survey, 'SURVEY');
                    $deleteIds[] = $survey->id;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $skipped[] = ['id' => $survey->id, 'status' => $e->getStatusCode(), 'reason' => $e->getMessage()];
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $survey->id, 'status' => 500, 'reason' => 'Lỗi: '.$e->getMessage()];
                }
            }

            if (empty($deleteIds)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phiếu nào hợp lệ để xóa thật.',
                    'skipped' => $skipped
                ], 422);
            }

            // Xóa thật tất cả chi tiết
            $deletedDetails = DB::table('survey_form_details')
                ->whereIn('survey_form_id', $deleteIds)
                ->delete();

            // Xóa thật tất cả phiếu
            $deletedSurveys = DB::table('survey_forms')
                ->whereIn('id', $deleteIds)
                ->delete();

            // Kiểm tra và xóa people không còn tham chiếu
            $personIds = DB::table('survey_form_details')
                ->whereIn('survey_form_id', $deleteIds)
                ->pluck('person_id')
                ->unique();

            foreach ($personIds as $personId) {
                $remainingRefs = DB::table('survey_form_details')
                    ->where('person_id', $personId)
                    ->count();
                
                if ($remainingRefs == 0) {
                    $hasRelatedData = DB::table('person_educations')->where('person_id', $personId)->exists() ||
                                     DB::table('person_literacy')->where('person_id', $personId)->exists() ||
                                     DB::table('person_disabilities')->where('person_id', $personId)->exists() ||
                                     DB::table('person_special_circumstances')->where('person_id', $personId)->exists() ||
                                     DB::table('person_contacts')->where('person_id', $personId)->exists() ||
                                     DB::table('person_mamnon')->where('person_id', $personId)->exists();
                    
                    if (!$hasRelatedData) {
                        DB::table('people')->where('id', $personId)->delete();
                        $deletedPeople++;
                    }
                }
            }

            DB::commit();

            $msg = "Đã xóa thật {$deletedSurveys} phiếu";
            if ($deletedDetails > 0) {
                $msg .= " · {$deletedDetails} chi tiết";
            }
            if ($deletedPeople > 0) {
                $msg .= " · {$deletedPeople} người";
            }
            $msg .= ".";

            return response()->json([
                'success' => true,
                'message' => $msg,
                'deleted_surveys' => $deletedSurveys,
                'deleted_details' => $deletedDetails,
                'deleted_people' => $deletedPeople,
                'skipped' => $skipped
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Hard delete all surveys error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: Không thể xóa thật các phiếu.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview số lượng đối tượng sẽ được cập nhật bậc tốt nghiệp
     */
    public function previewUpdateGraduationLevels(Request $request, UpdateGraduationLevelService $service)
    {
        // Kiểm tra quyền
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật đối tượng điều tra.'
            ], 403);
        }

        // Lấy filter từ request
        $filters = [
            'academic_year' => $request->input('year'),
            'scheme'        => $request->input('scheme', 'new'),
            'geo'           => $request->input('geo', []),
        ];

        try {
            $preview = $service->previewCount($filters);

            return response()->json([
                'success' => true,
                'data'    => $preview,
                'message' => "Sẽ cập nhật {$preview['total']} đối tượng"
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật bậc tốt nghiệp tự động dựa vào thông tin học tập
     * 
     * Logic:
     * - Chỉ cập nhật đối tượng CHƯA CÓ bậc TN (tránh ghi đè sai)
     * - Dựa vào: lớp bỏ học, lớp học xong, khối học hiện tại
     * - Lớp 1-5 → TN Mầm non
     * - Lớp 6-9 → TN Tiểu học
     * - Lớp 10-12 → TN THCS
     */
    public function updateGraduationLevels(Request $request, UpdateGraduationLevelService $service)
    {
        // Kiểm tra quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit');

        // Lấy filter từ request
        $filters = [
            'academic_year' => $request->input('year'),
            'scheme'        => $ctx['scheme'],
            'geo'           => $ctx['geo'],
        ];

        try {
            DB::beginTransaction();

            $stats = $service->updateByFilters($filters);

            DB::commit();

            $message = sprintf(
                'Đã cập nhật bậc tốt nghiệp cho %d đối tượng. Bỏ qua %d đối tượng.',
                $stats['updated'],
                $stats['skipped']
            );

            if (!empty($stats['errors'])) {
                $message .= sprintf(' Có %d lỗi.', count($stats['errors']));
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $stats
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Lỗi cập nhật bậc TN', [
                'user_id' => $ctx['user']->id ?? null,
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật học 9-10 buổi/tuần cho trẻ khối 1, 6 tuổi
     * 
     * Logic:
     * - Khối 1 (education_block_id = 7)
     * - Trẻ 6 tuổi (dob_year = năm học - 6)
     * - Cập nhật: hoc_9_10_buoi_tuan = 1, thêm * vào class_label
     */
    public function updateNineTenSessions(Request $request, UpdateNineTenSessionService $service)
    {
        // Kiểm tra quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'edit');

        // Lấy filter từ request
        $filters = [
            'academic_year' => $request->input('year'),
            'scheme'        => $ctx['scheme'],
            'geo'           => $ctx['geo'],
        ];

        try {
            DB::beginTransaction();

            $stats = $service->updateByFilters($filters);

            DB::commit();

            $message = sprintf(
                'Đã cập nhật học 9-10 b/tuần cho %d học sinh khối 1, 6 tuổi. Bỏ qua %d.',
                $stats['updated'],
                $stats['skipped']
            );

            if (!empty($stats['errors'])) {
                $message .= sprintf(' Có %d lỗi.', count($stats['errors']));
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $stats
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Lỗi cập nhật 9-10 b/t', [
                'user_id' => $ctx['user']->id ?? null,
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
}
