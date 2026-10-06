<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SurveyDetailFlat extends Model
{
    protected $table = 'v_survey_detail_flat';

    protected $primaryKey = 'detail_id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    // READ-ONLY
    public function save(array $options = []) { throw new \LogicException(static::class.' is a read-only model (DB view).'); }
    public function delete() { throw new \LogicException(static::class.' is a read-only model (DB view).'); }
    public static function create(array $attributes = []) { throw new \LogicException(static::class.' is a read-only model (DB view).'); }

    protected $casts = [
        'detail_id'     => 'integer',
        'survey_id'     => 'integer',
        'person_id'     => 'integer',
        'academic_year' => 'string',

        'dob_year'      => 'integer',
        'dob_month'     => 'integer',
        'dob_day'       => 'integer',
        'is_female'     => 'boolean',
        'dob_date'      => 'date',

        // === Khuyết tật (từ VIEW) ===
        'dis_VAN_DONG'  => 'boolean',
        'dis_NGHE_NOI'  => 'boolean',
        'dis_NHIN'      => 'boolean',
        'dis_THAN_KINH' => 'boolean',
        'dis_TRI_TUE'   => 'boolean',
        'dis_HOC_TAP'   => 'boolean',
        'dis_TU_KY'     => 'boolean',
        'dis_KHAC'      => 'boolean',
        'dis_has_certificate' => 'boolean',
        'dis_can_learn'       => 'boolean',
    ];

    /** Lọc năm học (bỏ qua nếu $year === 'all') */
    public function scopeOfYear(Builder $q, $year): Builder
    {
        return ($year === 'all' || $year === null) ? $q : $q->where('academic_year', $year);
    }

    /** Search cơ bản */
    public function scopeSearch(Builder $q, ?string $kw): Builder
    {
        $kw = trim((string) $kw);
        if ($kw === '') return $q;

        $kwClean = preg_replace('/\s+/', ' ', $kw);

        return $q->where(function($w) use ($kw, $kwClean){
            $w->where('ten', 'like', "%{$kw}%")
              ->orWhere('ho_dem', 'like', "%{$kw}%")
              ->orWhereRaw("CONCAT(TRIM(COALESCE(ho_dem, '')), ' ', TRIM(COALESCE(ten, ''))) LIKE ?", ["%{$kwClean}%"])
              ->orWhere('identity_number', 'like', "%{$kw}%")
              ->orWhere('contact_phone', 'like', "%{$kw}%")
              ->orWhere('so_phieu', 'like', "%{$kw}%")
              ->orWhere('chuho_ten', 'like', "%{$kw}%")
              ->orWhere('chuho_ho_dem', 'like', "%{$kw}%")
              ->orWhereRaw("CONCAT(TRIM(COALESCE(chuho_ho_dem, '')), ' ', TRIM(COALESCE(chuho_ten, ''))) LIKE ?", ["%{$kwClean}%"]);
        });
    }

    /** Tuổi theo dob_date */
    public function scopeAgeBetween(Builder $q, ?int $minAge, ?int $maxAge, \Carbon\Carbon|string|null $refDate=null): Builder
    {
        if ($minAge === null && $maxAge === null) return $q;
        $ref = $refDate ? \Carbon\Carbon::parse($refDate)->toDateString() : now()->toDateString();

        if ($minAge !== null && $maxAge !== null) {
            return $q->whereRaw("TIMESTAMPDIFF(YEAR, dob_date, ?) BETWEEN ? AND ?", [$ref, $minAge, $maxAge]);
        } elseif ($minAge !== null) {
            return $q->whereRaw("TIMESTAMPDIFF(YEAR, dob_date, ?) >= ?", [$ref, $minAge]);
        } else {
            return $q->whereRaw("TIMESTAMPDIFF(YEAR, dob_date, ?) <= ?", [$ref, $maxAge]);
        }
    }

    /** (Tùy chọn) nếu VIEW có *_id địa bàn, bạn có thể mở rộng hai scope này */
    public function scopeGeoOld(Builder $q, ?int $provinceId=null, ?int $districtId=null, ?int $wardId=null): Builder
    { return $q; }
    public function scopeGeoNew(Builder $q, ?int $provinceId=null, ?int $wardId=null, ?int $villageId=null): Builder
    { return $q; }

    /** Whitelist cột cho phép sort */
    public static function sortableColumns(): array
    {
        return [
            // danh tính
            'ho_dem','ten','dob_day','dob_month','dob_year','is_female',
            'identity_number','ethnicity','religion',

            // ưu tiên / chủ hộ / địa chỉ / số phiếu
            'priority_name','chuho_ho_dem','chuho_ten','addressline','so_phieu',
            'residence_type_name','residence_status_name',

            // học tập
            'education_block_code','education_grade_name',
            'school_name','school_code','graduation_level_name',
            'is_bo_tuc','graduation_year','vocational_level_name',
            'vocational_grad_cert_no','vocational_grad_year',
            'hoc_xong_lop','hoc_xong_nam','bo_hoc_lop','bo_hoc_nam',

            // mù chữ
            'lit_dang_hoc_lop','lit_hoan_thanh_lop','lit_tai_mu_muc',

            // gia đình & liên lạc
            'relationship_name','contact_name','contact_phone','contact_note',

            // địa bàn phiếu
            'old_province_name','old_district_name','old_ward_name',
            'new_province_name','new_ward_name','village_name',

            // xã của trường (đã thêm vào VIEW)
            'school_ward_name',

            // khuyết tật: cho phép sort luôn nếu cần
            'dis_VAN_DONG','dis_NGHE_NOI','dis_NHIN','dis_THAN_KINH',
            'dis_TRI_TUE','dis_HOC_TAP','dis_TU_KY','dis_KHAC',
            'dis_has_certificate','dis_can_learn',

            // tuổi xấp xỉ
            'dob_date',
        ];
    }
}
