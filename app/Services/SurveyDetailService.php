<?php

namespace App\Services;

use App\Data\SurveyDetailFilters;
use App\Models\DisabilityCategory;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SurveyDetailService
{
    /** Map id=>code cho khuyết tật, dùng lặp lại nhiều nơi */
    public function disabilityIdToCodeMap()
    {
        // Bạn có thể cache() nếu muốn
        return DisabilityCategory::query()
            ->when(schema()->hasColumn('disability_categories', 'is_active'), fn($q)=>$q->where('is_active', true))
            ->pluck('code', 'id');
    }

    /** Query base: gồm đầy đủ with() dùng cho mọi màn */
    public function baseQuery(): Builder
    {
        return SurveyFormDetail::query()
            ->where('is_deleted', false)
            ->with([
                'surveyForm:id,so_phieu,investigators',
                'person:id,ho_dem,ten,dob_day,dob_month,dob_year,is_female,addressline,phone',
                'residence.type:id,name', 'residence.relationship:id,name',
                'education:block:id,name,code', 'education.grade:id,name,code',
                'education.graduationLevel:id,name', 'education.vocationalLevel:id,name',
                'literacy',
                'personDisability:id,person_id,disability_ids,has_certificate,can_learn',
                'specialCircumstance.specialCircumstance:id,name',
                'contact.relationship:id,name',
            ]);
    }

    /** Áp dụng filter chung */
    public function applyFilters(Builder $q, SurveyDetailFilters $f): Builder
    {
        if ($f->q) {
            $term = '%'.$f->q.'%';
            $q->where(function($qq) use ($term){
                $qq->whereHas('person', function($p) use ($term){
                    $p->where('ten','like',$term)
                      ->orWhere('ho_dem','like',$term)
                      ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", [$term])
                      ->orWhere('phone','like',$term);
                })->orWhereHas('contact', fn($c)=>$c->where('hoten','like',$term)->orWhere('dienthoai','like',$term));
            });
        }
        if ($f->so_phieu) {
            $like = '%'.$f->so_phieu.'%';
            $q->whereHas('surveyForm', fn($sf)=>$sf->where('so_phieu','like',$like));
        }
        if ($f->investigator) {
            // MySQL 8+: JSON_SEARCH
            $like = '%'.$f->investigator.'%';
            $q->whereHas('surveyForm', fn($sf)=>$sf->whereRaw("JSON_SEARCH(investigators, 'one', ?) IS NOT NULL", [$like]));
        }
        if ($f->block) {
            $code = $f->block;
            $q->whereHas('education.block', fn($b)=>$b->where('code',$code)->orWhere('name',$code));
        }
        if ($f->disability) {
            $ids = $this->disabilityIdToCodeMap()->flip(); // [code=>id]
            if (isset($ids[$f->disability])) {
                $id = (int)$ids[$f->disability];
                $q->whereHas('personDisability', fn($pd)=>$pd->whereRaw('JSON_CONTAINS(disability_ids, ?)', [json_encode($id)]));
            }
        }
        return $q;
    }

    /** Paginate cho trang INDEX (mọi phiếu) */
    public function paginateIndex(SurveyDetailFilters $f, int $perPage = 20): LengthAwarePaginator
    {
        $q = $this->applyFilters($this->baseQuery(), $f);
        $page = $q->paginate($perPage);

        $map = $this->disabilityIdToCodeMap(); // [id=>code]
        $page->getCollection()->transform(fn($row) => $this->flattenRow($row, $map, true, null));
        return $page;
    }

    /** Paginate cho 1 phiếu (SHOW) */
    public function paginateForSurvey(SurveyForm $survey, SurveyDetailFilters $f, int $perPage = 20): LengthAwarePaginator
    {
        $q = $this->applyFilters(
            $this->baseQuery()->where('survey_form_id', $survey->id),
            $f
        );
        $page = $q->paginate($perPage);
        $map  = $this->disabilityIdToCodeMap();
        $page->getCollection()->transform(fn($row) => $this->flattenRow($row, $map, false, $survey->so_phieu));
        return $page;
    }

    /**
     * Chuẩn hoá 1 dòng dữ liệu (phẳng hoá cho frontend)
     * @param $includeSoPhieu bool: true cho index, false cho show
     * @param $soPhieuOverride ?string: set giá trị nếu muốn ép (trang show)
     */
    public function flattenRow($row, $disId2Code, bool $includeSoPhieu = true, ?string $soPhieuOverride = null): array
    {
        $p = $row->person;
        $e = $row->education;
        $r = $row->residence;
        $l = $row->literacy;
        $pd= $row->personDisability;
        $sc= $row->specialCircumstance?->specialCircumstance;
        $ct= $row->contact;

        $codes = [];
        if ($pd && is_array($pd->disability_ids)) {
            foreach ($pd->disability_ids as $id) {
                if (isset($disId2Code[$id])) $codes[] = $disId2Code[$id];
            }
        }

        return array_filter([
            'id'               => $row->id,
            'note'             => $row->note,

            // thông tin phiếu cho index
            'survey_id'        => $includeSoPhieu ? ($row->surveyForm->id ?? null) : null,
            'so_phieu'         => $includeSoPhieu ? ($row->surveyForm->so_phieu ?? null) : $soPhieuOverride,

            // person
            'person_full_name' => $p ? trim(($p->ho_dem ? $p->ho_dem.' ' : '').$p->ten) : null,
            'ho_dem'           => $p->ho_dem ?? null,
            'ten'              => $p->ten ?? null,
            'dob_day'          => $p->dob_day, 'dob_month' => $p->dob_month, 'dob_year' => $p->dob_year,
            'is_female'        => (bool)($p->is_female ?? false),
            'addressline'      => $p->addressline,

            // residence (tuỳ bạn đổi hamlet_name -> lấy từ quan hệ)
            'hamlet_name'      => $r?->hamlet_name ?? null,
            'residence_type'   => $r?->type?->name,
            'relationship'     => $r?->relationship?->name,

            // education
            'edu_block'        => $e?->block?->name,
            'edu_class_label'  => $e?->class_label,
            'study_district'   => $e?->studying_district_id,
            'school_name'      => $e?->school_name,
            'school_code'      => $e?->school_code,
            'grad_level'       => $e?->graduationLevel?->name,
            'is_bo_tuc'        => (bool)($e?->is_bo_tuc ?? false),
            'grad_year'        => $e?->graduation_year,
            'voc_level'        => $e?->vocationalLevel?->name,
            'voc_grad_cert_no' => $e?->graduation_certificate_no,
            'voc_grad_year'    => $e?->vocational_grad_year,
            'hoc_xong_lop'     => $e?->hoc_xong_lop,
            'hoc_xong_nam'     => $e?->hoc_xong_nam,
            'bo_hoc_lop'       => $e?->bo_hoc_lop,
            'bo_hoc_nam'       => $e?->bo_hoc_nam,

            // literacy
            'lit_dang_hoc'     => $l?->dang_hoc_lop,
            'lit_hoan_thanh'   => $l?->hoan_thanh_lop,
            'lit_tai_mu_muc'   => $l?->tai_mu_chu_muc,

            // disability
            'disability_codes' => $codes,
            'dis_has_cert'     => $pd?->has_certificate ?? false,
            'dis_can_learn'    => $pd?->can_learn,

            // special circumstance + contact
            'sc_name'          => $sc?->name,
            'sc_detail'        => $row->specialCircumstance?->chi_tiet,
            'contact_name'     => $ct?->hoten,
            'contact_phone'    => $ct?->dienthoai,
            'contact_note'     => $ct?->note,
        ], fn($v) => $v !== null); // loại bỏ null cho JSON gọn
    }
}
