<?php

namespace App\Services;

use App\Models\SurveyFormDetail;
use App\Models\Ethnicity;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SurveyFilterService
{
    public function buildContext(Request $request, ?Authenticatable $user): array
    {
        // Ép scheme & geo khi user không phải admin
        $isAdmin =($user->role_id == 1 || $user->role_id == 2);
        if (!$isAdmin) {
            $force = ['scheme' => 'new'];
            $geoReq  = (array) $request->input('geo', []);
            $geoUser = array_filter([
                'province_id' => $user->province_id ?: null,
                'ward_id'     => $user->ward_id ?: null,
            ], fn($v) => !is_null($v));
            $force['geo'] = array_merge($geoReq, $geoUser);
            $request->merge($force);
        }

        $defaultYear = \App\Models\SurveyForm::currentAcademicYear();
        $year   = $request->get('year', $defaultYear);
        $scheme = $request->get('scheme', 'old');

        $geoIn  = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'village'  => (int) ($geoIn['village_id'] ?? 0) ?: null,
        ];

        $applyGeo = collect($geoIds)->filter()->isNotEmpty();

        // Mốc năm tham chiếu cho tính tuổi theo NĂM
        if ($year !== 'all' && preg_match('/^(\d{4})-(\d{4})$/', $year, $m)) {
            $refYear = (int)$m[1];
            $refDate = Carbon::create((int)$m[2], 9, 30)->startOfDay();
        } else {
            $refYear = (int)now()->year;
            $refDate = now();
        }

        $ctx = [
            'is_admin'     => $isAdmin,
            'year'         => $year,
            'defaultYear'  => $defaultYear,
            'scheme'       => $scheme,
            'geoIds'       => $geoIds,
            'applyGeo'     => $applyGeo,

            'q'            => trim((string) $request->get('q', '')),
            'soPhieu'      => trim((string) $request->get('so_phieu', '')),
            'school'       => trim((string) $request->get('school', '')),
            'classLabel'   => trim((string) $request->get('class_label', '')),
            'minAge'       => $request->filled('min_age') ? max(0, (int)$request->get('min_age')) : null,
            'maxAge'       => $request->filled('max_age') ? max(0, (int)$request->get('max_age')) : null,
            'birthYear'    => $request->filled('birth_year') ? (int)$request->get('birth_year') : null,
            'minBirthYear' => $request->filled('min_birth_year') ? (int)$request->get('min_birth_year') : ($request->filled('birth_year') ? (int)$request->get('birth_year') : null),
            'maxBirthYear' => $request->filled('max_birth_year') ? (int)$request->get('max_birth_year') : ($request->filled('birth_year') ? (int)$request->get('birth_year') : null),
            'minority'     => $request->get('minority', null),

            'refYear'      => $refYear,
            'refDate'      => $refDate->toDateString(),
            'perPage'      => (int) $request->get('per_page', 10),

            // Advanced inputs
            'hoDem'             => trim((string) $request->get('ho_dem', '')),
            'ten'               => trim((string) $request->get('ten', '')),
            'chuhoHoDem'        => trim((string) $request->get('chuho_ho_dem', '')),
            'chuhoTen'          => trim((string) $request->get('chuho_ten', '')),
            'identityNumber'    => trim((string) $request->get('identity_number', '')),
            'isFemale'          => $request->get('is_female', null),
            'ethnicityId'       => $request->get('ethnicity_id', null),
            'religionId'        => $request->get('religion_id', null),
            'educationBlockId'  => $request->get('education_block_id', null),
            'graduationLevelId' => $request->get('graduation_level_id', null),
            'graduationYear'    => $request->get('graduation_year', null),
            'isBoTuc'           => $request->get('is_bo_tuc', null),
            'vocationalLevelId' => $request->get('vocational_level_id', null),
            'vocationalGradYear'=> $request->get('vocational_grad_year', null),
            'isRepeatGrade'     => $request->get('is_repeat_grade', null),
            'hocHaiBuoi'        => $request->get('hoc_hai_buoi', null),
            'traiTuyen'         => $request->get('trai_tuyen', null),
            'boHocTuLop'        => $request->get('bo_hoc_tu_lop', null),
            'boHocDenLop'       => $request->get('bo_hoc_den_lop', null),
            'boHocNam'          => $request->get('bo_hoc_nam', null),
            'hocXongTuLop'      => $request->get('hoc_xong_tu_lop', null),
            'hocXongDenLop'     => $request->get('hoc_xong_den_lop', null),
            'hocXongNam'        => $request->get('hoc_xong_nam', null),
            'litDangHocLop'     => $request->get('lit_dang_hoc_lop', null),
            'litHoanThanhLop'   => $request->get('lit_hoan_thanh_lop', null),
            'isDisabled'        => $request->get('is_disabled', null),
            'disType'           => $request->get('dis_type', null),
            'canLearn'          => $request->get('can_learn', null),
            'hasCertificate'    => $request->get('has_certificate', null),
            'scId'              => $request->get('sc_id', null),
            'hasNote'           => $request->get('has_note', null),
        ];

        // id dân tộc Kinh (để lọc minority nhanh)
        $ctx['kinhId'] = Ethnicity::query()
            ->where(fn($q) => $q->where('code','KINH')->orWhere('name','Kinh'))
            ->value('id');

        return $ctx;
    }

    /**
     * Tạo Builder đã áp dụng toàn bộ FILTER.
     * Không sắp xếp theo cột; chỉ order mặc định ổn định (desc form, asc id).
     */
    public function filteredQuery(array $ctx): Builder
    {
        $year         = $ctx['year'];
        $scheme       = $ctx['scheme'];
        $geoIds       = $ctx['geoIds'];
        $applyGeo     = $ctx['applyGeo'];
        $q            = $ctx['q'];
        $soPhieu      = $ctx['soPhieu'];
        $school       = $ctx['school'] ?? '';
        $classLabel   = $ctx['classLabel'] ?? '';
        $minAge       = $ctx['minAge'];
        $maxAge       = $ctx['maxAge'];
        $minBirthYear = $ctx['minBirthYear'] ?? null;
        $maxBirthYear = $ctx['maxBirthYear'] ?? null;
        $refYear      = $ctx['refYear'] ?? (int)now()->year;
        $kinhId       = $ctx['kinhId'];
        $minority     = $ctx['minority'];

        $query = SurveyFormDetail::query()
            ->with([
                'surveyForm:id,so_phieu,academic_year,household_id,head_person_id,old_ward_id,new_ward_id,old_district_id,old_province_id,new_province_id,village_id',
                'surveyForm.headPerson:id,ho_dem,ten',
                'surveyForm.oldWard:id,name',
                'surveyForm.newWard:id,name',
                'surveyForm.oldDistrict:id,name',
                'surveyForm.oldProvince:id,name',
                'surveyForm.newProvince:id,name',
                'surveyForm.village:id,name',

                'person.type:id,name',
                'person.status:id,name',
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,is_female,ethnicity_id,religion_id,contact_name,identity_number,phone,addressline,old_ward_id,new_ward_id,old_district_id,note,residence_type_id,residence_status_id',
                'person.ethnicity:id,name,is_minority',
                'person.religion:id,name',

                'person.householdMemberships:id,household_id,person_id,role',
                'education:id,person_id,education_block_id,education_grade_id,class_label,hoc_hai_buoi,hoc_9_10_buoi_tuan,is_repeat_grade,studying_district_id,school_id,school_name_custom,studying_district_custom,graduation_level_id,is_bo_tuc,graduation_year,vocational_level_id,vocational_grad_year,graduation_certificate_no,hoc_xong_lop,hoc_xong_nam,bo_hoc_lop,bo_hoc_nam,academic_year',
                'education.block:id,name',
                'education.graduationLevel:id,name',
                'education.vocationalLevel:id,name',
                'education.school:id,code,name',

                'literacy:id,person_id,dang_hoc_lop,hoan_thanh_lop,tai_mu_chu_muc',
                'personDisability',
                'specialCircumstance.specialCircumstance:id,name',
                'contact.relationship:id,name',
            ])

            // ⬇️ Lọc theo NĂM của survey_form_details (không phải survey_forms)
            ->when($year !== 'all', fn($qq) => $qq->where('academic_year', $year))
            // Form không bị xóa mềm
            ->whereHas('surveyForm', fn($f) => $f->notDeleted())
            // Đối tượng điều tra không bị xóa tạm
            ->notDeleted()

            ->when($soPhieu !== '', fn($qq) =>
                $qq->whereHas('surveyForm', fn($f) => $f->where('so_phieu', 'like', "%{$soPhieu}%"))
            )
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('person', function ($pp) use ($q) {
                    $pp->where('ten', 'like', "%{$q}%")
                    ->orWhere('ho_dem', 'like', "%{$q}%")
                    ->orWhere('identity_number', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
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
            })
            // --- Lọc tuổi theo NĂM: age = refYear - dob_year ---
            ->when(($minAge !== null) || ($maxAge !== null), function ($qq) use ($minAge, $maxAge, $refYear) {
                $qq->whereHas('person', function($pp) use ($minAge, $maxAge, $refYear) {
                    $pp->whereNotNull('dob_year')
                       ->where('dob_year', '>', 0)
                       ->where('dob_year', '<=', $refYear);
                    $ageExpr = \Illuminate\Support\Facades\DB::raw("(CAST($refYear AS SIGNED) - CAST(people.dob_year AS SIGNED))");
                    if ($minAge !== null && $maxAge !== null) {
                        $pp->whereBetween($ageExpr, [$minAge, $maxAge]);
                    } elseif ($minAge !== null) {
                        $pp->where($ageExpr, '>=', $minAge);
                    } else {
                        $pp->where($ageExpr, '<=', $maxAge);
                    }
                });
            })
            // --- Lọc theo NĂM SINH ---
            ->when(($minBirthYear !== null) || ($maxBirthYear !== null), function ($qq) use ($minBirthYear, $maxBirthYear) {
                $qq->whereHas('person', function($pp) use ($minBirthYear, $maxBirthYear) {
                    $pp->whereNotNull('dob_year')->where('dob_year', '>', 0);
                    if ($minBirthYear !== null && $maxBirthYear !== null) {
                        $pp->whereBetween('people.dob_year', [$minBirthYear, $maxBirthYear]);
                    } elseif ($minBirthYear !== null) {
                        $pp->where('people.dob_year', '>=', $minBirthYear);
                    } else {
                        $pp->where('people.dob_year', '<=', $maxBirthYear);
                    }
                });
            })
            // --- Lọc theo TRƯỜNG ---
            ->when($school !== '', function ($qq) use ($school) {
                $qq->whereHas('education', function ($edu) use ($school) {
                    $edu->whereHas('school', function ($sch) use ($school) {
                        $sch->where('name', 'like', "%{$school}%")
                            ->orWhere('code', 'like', "%{$school}%");
                    });
                });
            })
            // --- Lọc theo LỚP ---
            ->when($classLabel !== '', function ($qq) use ($classLabel) {
                $qq->whereHas('education', function ($edu) use ($classLabel) {
                    $edu->where('class_label', 'like', "%{$classLabel}%");
                });
            })
            // --- Lọc DÂN TỘC ---
            ->when($minority !== null && $minority !== '', function ($qq) use ($minority, $kinhId) {
                $qq->whereHas('person', function($pp) use ($minority, $kinhId) {
                    if ($minority === '1') {
                        if ($kinhId) $pp->whereNotNull('ethnicity_id')->where('ethnicity_id', '!=', $kinhId);
                        else $pp->whereHas('ethnicity', fn($e) => $e->where('name', '!=', 'Kinh'));
                    } elseif ($minority === '0') {
                        if ($kinhId) $pp->where('ethnicity_id', $kinhId);
                        else $pp->whereHas('ethnicity', fn($e) => $e->where('name', 'Kinh'));
                    }
                });
            })
            // --- ADVANCED FILTERS ---
            ->when(($ctx['hoDem'] ?? '') !== '', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->where('ho_dem', 'like', "%{$ctx['hoDem']}%")))
            ->when(($ctx['ten'] ?? '') !== '', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->where('ten', 'like', "%{$ctx['ten']}%")))
            ->when(($ctx['chuhoHoDem'] ?? '') !== '', fn($qq) => $qq->whereHas('surveyForm.headPerson', fn($hp) => $hp->where('ho_dem', 'like', "%{$ctx['chuhoHoDem']}%")))
            ->when(($ctx['chuhoTen'] ?? '') !== '', fn($qq) => $qq->whereHas('surveyForm.headPerson', fn($hp) => $hp->where('ten', 'like', "%{$ctx['chuhoTen']}%")))
            ->when(($ctx['identityNumber'] ?? '') !== '', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->where('identity_number', 'like', "%{$ctx['identityNumber']}%")))
            ->when(($ctx['isFemale'] ?? null) !== null && $ctx['isFemale'] !== '', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->where('is_female', $ctx['isFemale'] == '1' ? 1 : 0)))
            ->when(($ctx['ethnicityId'] ?? null) !== null && $ctx['ethnicityId'] !== '', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->where('ethnicity_id', $ctx['ethnicityId'])))
            ->when(($ctx['religionId'] ?? null) !== null && $ctx['religionId'] !== '', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->where('religion_id', $ctx['religionId'])))
            ->when(($ctx['educationBlockId'] ?? null) !== null && $ctx['educationBlockId'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('education_block_id', $ctx['educationBlockId'])))
            ->when(($ctx['graduationLevelId'] ?? null) !== null && $ctx['graduationLevelId'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('graduation_level_id', $ctx['graduationLevelId'])))
            ->when(($ctx['graduationYear'] ?? null) !== null && $ctx['graduationYear'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('graduation_year', $ctx['graduationYear'])))
            ->when(($ctx['isBoTuc'] ?? null) !== null && $ctx['isBoTuc'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('is_bo_tuc', $ctx['isBoTuc'] == '1' ? 1 : 0)))
            ->when(($ctx['vocationalLevelId'] ?? null) !== null && $ctx['vocationalLevelId'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('vocational_level_id', $ctx['vocationalLevelId'])))
            ->when(($ctx['vocationalGradYear'] ?? null) !== null && $ctx['vocationalGradYear'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('vocational_grad_year', $ctx['vocationalGradYear'])))
            ->when(($ctx['isRepeatGrade'] ?? null) !== null && $ctx['isRepeatGrade'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('is_repeat_grade', $ctx['isRepeatGrade'] == '1' ? 1 : 0)))
            ->when(($ctx['hocHaiBuoi'] ?? null) !== null && $ctx['hocHaiBuoi'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('hoc_hai_buoi', $ctx['hocHaiBuoi'] == '1' ? 1 : 0)))
            ->when(($ctx['traiTuyen'] ?? null) !== null && $ctx['traiTuyen'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('trai_tuyen', $ctx['traiTuyen'] == '1' ? 1 : 0)))
            ->when(($ctx['boHocTuLop'] ?? null) !== null && $ctx['boHocTuLop'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('bo_hoc_lop', '>=', (int)$ctx['boHocTuLop'])))
            ->when(($ctx['boHocDenLop'] ?? null) !== null && $ctx['boHocDenLop'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('bo_hoc_lop', '<=', (int)$ctx['boHocDenLop'])))
            ->when(($ctx['boHocNam'] ?? null) !== null && $ctx['boHocNam'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('bo_hoc_nam', (int)$ctx['boHocNam'])))
            ->when(($ctx['hocXongTuLop'] ?? null) !== null && $ctx['hocXongTuLop'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('hoc_xong_lop', '>=', (int)$ctx['hocXongTuLop'])))
            ->when(($ctx['hocXongDenLop'] ?? null) !== null && $ctx['hocXongDenLop'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('hoc_xong_lop', '<=', (int)$ctx['hocXongDenLop'])))
            ->when(($ctx['hocXongNam'] ?? null) !== null && $ctx['hocXongNam'] !== '', fn($qq) => $qq->whereHas('education', fn($edu) => $edu->where('hoc_xong_nam', (int)$ctx['hocXongNam'])))
            ->when(($ctx['litDangHocLop'] ?? null) !== null && $ctx['litDangHocLop'] !== '', fn($qq) => $qq->whereHas('literacy', fn($lit) => $lit->where('dang_hoc_lop', 'like', "%{$ctx['litDangHocLop']}%")))
            ->when(($ctx['litHoanThanhLop'] ?? null) !== null && $ctx['litHoanThanhLop'] !== '', fn($qq) => $qq->whereHas('literacy', fn($lit) => $lit->where('hoan_thanh_lop', 'like', "%{$ctx['litHoanThanhLop']}%")))
            ->when(($ctx['isDisabled'] ?? null) === '1', fn($qq) => $qq->whereHas('personDisability'))
            ->when(($ctx['canLearn'] ?? null) !== null && $ctx['canLearn'] !== '', fn($qq) => $qq->whereHas('personDisability', fn($pd) => $pd->where('can_learn', $ctx['canLearn'] == '1' ? 1 : 0)))
            ->when(($ctx['hasCertificate'] ?? null) !== null && $ctx['hasCertificate'] !== '', fn($qq) => $qq->whereHas('personDisability', fn($pd) => $pd->where('has_certificate', $ctx['hasCertificate'] == '1' ? 1 : 0)))
            ->when(($ctx['scId'] ?? null) !== null && $ctx['scId'] !== '', fn($qq) => $qq->whereHas('specialCircumstance', fn($sc) => $sc->where('sc_id', $ctx['scId'])))
            ->when(($ctx['hasNote'] ?? null) === '1', fn($qq) => $qq->whereHas('person', fn($pp) => $pp->whereNotNull('note')->where('note', '!=', '')));

        // Không sort theo cột ở server
        return $query->orderByDesc('survey_form_id')->orderBy('id');
    }

}
