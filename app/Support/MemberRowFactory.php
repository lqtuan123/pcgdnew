<?php

namespace App\Support;

use Illuminate\Support\Str;
use App\Models\Ethnicity;
use App\Models\Religion;
use App\Models\ResidenceType;
use App\Models\ResidenceStatus;
use App\Models\EducationBlock;
use App\Models\GraduationLevel;
use App\Models\VocationalLevel;
use App\Models\EducationGrade;
use App\Models\School;
use App\Models\SpecialCircumstance;
use App\Models\DisabilityCategory;
use App\Models\PriorityCategory;
class MemberRowFactory
{
    public static function fromRequestArray(array $a): array
    {
        $p        = $a['person']     ?? [];
        $edu      = $a['education']  ?? [];
        $lit      = $a['literacy']   ?? [];
        $dis      = $a['dis']        ?? [];
        $special  = $a['special']    ?? [];
        $member   = $a['membership'] ?? [];
        $contact  = $a['contact']    ?? [];

        $row = [
            // ==== PERSON (giống import) ====
            'identity_number' => trim((string)($p['identity_number'] ?? '')),
            'ho_dem'          => trim((string)($p['ho_dem'] ?? '')),
            'ten'             => trim((string)($p['ten'] ?? '')),
            'dob_day'         => self::toInt($p['dob_day'] ?? null),
            'dob_month'       => self::toInt($p['dob_month'] ?? null),
            'dob_year'        => self::toInt($p['dob_year'] ?? null),
            'is_female'       => self::toBool($p['is_female'] ?? null),

            // các field dưới đây service cũ mong là TEXT:
            'ethnicity'             => '',
            'religion'              => '',
            'residence_type_name'   => '',
            'residence_status_name' => '',
            'residence_status_name' => '',
            'addressline'      => trim((string)($p['addressline'] ?? '')),
            'relationship_name'=> trim((string)($member['role'] ?? '')),

            // Contact
             
            'contact_name' => trim((string)($contact['name'] ?? '')),
            'contact_phone'=> trim((string)($p['phone'] ?? $contact['phone'] ?? '')),
            'note' => trim((string)($contact['note'] ?? '')),

            // ==== EDUCATION (giống import) ====
            'school_code'            => $edu['school_code'] ?? null,
            'school_name'            => $edu['school_name'] ?? null,
            'education_block_code'   => '',
            'education_grade_name'   => trim((string)($edu['class_label'] ?? '')),
            'graduation_level_name'  => '',
            'is_bo_tuc'              => self::toBool($edu['is_bo_tuc'] ?? null),
            'graduation_year'        => self::toInt($edu['graduation_year'] ?? null),
            'vocational_level_name'  => '',
            'vocational_grad_cert_no'=> trim((string)($edu['graduation_certificate_no'] ?? $edu['vocational_grad_cert_no'] ?? '')),
            'vocational_grad_year'   => self::toInt($edu['vocational_grad_year'] ?? null),

            // grade id + số lớp (service cũ dùng số lớp)
            'hoc_xong_grade_id' => self::toInt($edu['hoc_xong_grade_id'] ?? null),
            'hoc_xong_nam'      => self::toInt($edu['hoc_xong_nam'] ?? null),
            'hoc_xong_lop'      => null,
            'bo_hoc_grade_id'   => self::toInt($edu['bo_hoc_grade_id'] ?? null),
            'bo_hoc_nam'        => self::toInt($edu['bo_hoc_nam'] ?? null),
            'bo_hoc_lop'        => null,

            // ==== LITERACY ====
            'lit_dang_hoc_lop'   => trim((string)($lit['dang_hoc_lop'] ?? '')),
            'lit_hoan_thanh_lop' => self::toInt($lit['hoan_thanh_lop'] ?? null),
            'lit_tai_mu_muc'     => self::toInt($lit['tai_mu_chu_muc'] ?? null),

            // ==== SPECIAL ====
            'sc_name'   => '',
            'sc_detail' => trim((string)($special['chi_tiet'] ?? '')),
        ];

        // ====== LOOKUP ID -> TEXT cho service cũ ======
        if (!empty($p['ethnicity_id'])) {
            $row['priority_name'] = optional(PriorityCategory::find($p['priority_category_id']))->name ?? '';
        }
        if (!empty($p['ethnicity_id'])) {
            $row['ethnicity'] = optional(Ethnicity::find($p['ethnicity_id']))->name ?? '';
        }
        if (!empty($p['religion_id'])) {
            $row['religion'] = optional(Religion::find($p['religion_id']))->name ?? '';
        }
        if (!empty($p['residence_type_id'])) {
            $row['residence_type_name'] = optional(ResidenceType::find($p['residence_type_id']))->name ?? '';
        }
        if (!empty($p['residence_status_id'])) {
            $row['residence_status_name'] = optional(ResidenceStatus::find($p['residence_status_id']))->name ?? '';
        }
        if (!empty($edu['education_block_id'])) {
            $row['education_block_code'] = optional(EducationBlock::find($edu['education_block_id']))->name ?? '';
        }
        if (!empty($edu['graduation_level_id'])) {
            $row['graduation_level_name'] = optional(GraduationLevel::find($edu['graduation_level_id']))->name ?? '';
        }
        if (!empty($edu['vocational_level_id'])) {
            $row['vocational_level_name'] = optional(VocationalLevel::find($edu['vocational_level_id']))->name ?? '';
        }

        // School: nếu thiếu code/name thì tra theo ID
        if ((!$row['school_code'] || !$row['school_name']) && !empty($edu['school_id'])) {
            if ($sch = School::find($edu['school_id'])) {
                $row['school_code'] = $sch->code;
                $row['school_name'] = $sch->name;
            }
        }

        // Special circumstance: map id -> name
        if (!empty($special['special_circumstance_id'])) {
            $row['sc_name'] = optional(SpecialCircumstance::find($special['special_circumstance_id']))->name ?? '';
        }

        // Grade number từ code/name (đuôi số)
        $row['hoc_xong_lop'] = self::gradeNumber(EducationGrade::find($row['hoc_xong_grade_id']));
        $row['bo_hoc_lop']   = self::gradeNumber(EducationGrade::find($row['bo_hoc_grade_id']));

        // Disability: ids[] -> cờ boolean cho service cũ
        $row = array_merge($row, self::disabilityFlagsFromIds($dis['ids'] ?? []));
        $row['dis_can_learn']      = self::toBool($dis['can_learn'] ?? null);
        $row['dis_has_certificate']= self::toBool($dis['has_certificate'] ?? null);

        return $row;
    }

    /** Helpers */
    protected static function toInt($v){ $n = filter_var($v, FILTER_VALIDATE_INT); return $n !== false ? (int)$n : null; }
    protected static function toBool($v){ return in_array($v, [1,'1',true,'true','on','yes'], true); }

    protected static function gradeNumber($grade)
    {
        if (!$grade) return null;
        $s = trim((string)($grade->code ?? $grade->name ?? ''));
        return preg_match('/(\d{1,2})\s*$/', $s, $m) ? (int)$m[1] : null;
    }

    protected static function disabilityFlagsFromIds($ids): array
    {
        $flags = [
            'dis_VAN_DONG'=>false,'dis_NGHE_NOI'=>false,'dis_NHIN'=>false,'dis_THAN_KINH'=>false,
            'dis_TRI_TUE'=>false,'dis_HOC_TAP'=>false,'dis_TU_KY'=>false,'dis_KHAC'=>false,
        ];
        if (!is_array($ids) || !$ids) return $flags;

        $cats = DisabilityCategory::whereIn('id', $ids)->get();
        $map = [
            'VAN_DONG' => 'dis_VAN_DONG',
            'NGHE_NOI' => 'dis_NGHE_NOI',
            'NHIN'     => 'dis_NHIN',
            'THAN_KINH'=> 'dis_THAN_KINH',
            'TRI_TUE'  => 'dis_TRI_TUE',
            'HOC_TAP'  => 'dis_HOC_TAP',
            'TU_KY'    => 'dis_TU_KY',
            'KHAC'     => 'dis_KHAC',
        ];
        foreach ($cats as $c) {
            $key = strtoupper(str_replace('-', '_', Str::slug($c->code ?: $c->name, '_')));
            if (isset($map[$key])) $flags[$map[$key]] = true;
        }
        return $flags;
    }
}
