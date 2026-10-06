<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyForm extends Model
{
    protected $fillable = [
        'so_phieu',
        'head_person_id',
        'household_id',
        'investigators',
        'is_deleted',
        'is_active',
        'old_province_id','new_province_id','academic_year',
        'old_ward_id','new_ward_id','old_district_id','village_id','surveyed_at','editor_id',
        'province_id','ward_id' // Alias columns
    ];

    protected $casts = [
        'investigators' => 'array',
        'is_deleted'    => 'boolean',
        'is_active'     => 'boolean',
        'surveyed_at'   => 'date',
    ];

    /* Quan hệ */
    public function headPerson()   { return $this->belongsTo(Person::class, 'head_person_id'); }
    public function details()      { return $this->hasMany(SurveyFormDetail::class); }
    public function household()    { return $this->belongsTo(Household::class); }
    public function oldWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_ward_id'); }
    public function newWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_ward_id'); }
    public function oldDistrict()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_district_id'); }
    public function oldProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_province_id'); }
    public function newProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_province_id'); }
    public function village()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'village_id'); }
    public function person() {
        return $this->belongsTo(\App\Models\Person::class);
    }
    /* Helpers */
    public static function currentAcademicYear(): string
    {
        $y = (int) now()->format('Y');
        $m = (int) now()->format('n');
        return $m >= 8 ? "{$y}-".($y+1) : ($y-1)."-{$y}";
    }

    public static function getAcademicYears(): array
    {
        $currentYear = (int) now()->format('Y');
        $years = [];
        
        // Tạo danh sách năm học từ 5 năm trước đến 1 năm sau
        for ($i = $currentYear - 5; $i <= $currentYear + 1; $i++) {
            $years[] = $i . '-' . ($i + 1);
        }
        
        return $years;
    }

    /** Lấy chi tiết theo năm (tiện gọi trên instance) */
    public function detailsOfYear(string $year)
    {
        return $this->details()
            ->where('academic_year', $year)
            ->where('is_deleted', false);
    }
  
    /* Scopes */
    public function scopeBySoPhieu($q, string $so) { return $q->where('so_phieu', $so); }

    /**
     * TRẢ VỀ các phiếu có ÍT NHẤT MỘT chi tiết thuộc năm học $year
     * (lọc trên quan hệ details thay vì cột ở survey_forms)
     */
    public static function startYearOf(?string $y): ?int
    {
        $y = trim((string)$y);
        if ($y === '' || strcasecmp($y, 'all') === 0) return null;

        if (preg_match('/^(\d{4})\s*-\s*\d{4}$/', $y, $m)) {
            return (int) $m[1];                 // lấy năm đầu
        }
        if (preg_match('/^\d{4}$/', $y)) {
            return (int) $y;                    // hỗ trợ 'YYYY'
        }
        return null;
    }

    /**
     * Lọc phiếu theo "đến năm": trả về các phiếu có academic_year_start <= $yearStart
     * (tức: năm truyền vào lớn hơn hoặc bằng academic_year của phiếu)
     */
    public function scopeOfYear($q, string $year)
    {
        $start = self::startYearOf($year);
        if ($start === null) {
            // 'all' hoặc rỗng -> không áp filter
            return $q;
        }

        return $q->whereRaw(
            'CAST(SUBSTRING_INDEX(academic_year, "-", 1) AS UNSIGNED) <= ?',
            [$start]
        );
    }

    // (tuỳ chọn) nếu muốn lọc đúng-bằng chuỗi 'YYYY-YYYY'
    public function scopeOfYearExact($q, string $year)
    {
        return $q->where('academic_year', $year);
    }
    /** Eager-load CHỈ các details của năm $year (giúp tránh over-fetching) */
    public function scopeWithDetailsOfYear($q, string $year)
    {
        return $q->with(['details' => function ($d) use ($year) {
            $d->where('academic_year', $year)
              ->where('is_deleted', false);
        }]);
    }

    public function scopeHandledBy($q, string $name)     { /* giữ nguyên */ }
    public function scopeHandledByLike($q, string $term) { /* giữ nguyên */ }
    public function scopeNotDeleted($q)  { return $q->where('is_deleted', false); }
    public function scopeOnlyDeleted($q) { return $q->where('is_deleted', true); }

    /**
     * (Tuỳ chọn) Bản scope dùng JOIN khi dữ liệu rất lớn (nhanh hơn whereHas),
     * nhớ .distinct() để tránh trùng dòng.
     */
    public function scopeOfYearJoin($q, string $year)
    {
        return $q->select('survey_forms.*')
            ->join('survey_form_details as d', function ($j) use ($year) {
                $j->on('d.survey_form_id', '=', 'survey_forms.id')
                  ->where('d.academic_year', $year)
                  ->where('d.is_deleted', false);
            })
            ->distinct();
    }

    /**
     * Boot method để tự động đồng bộ alias columns
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Tự động đồng bộ province_id với new_province_id
            if ($model->isDirty('new_province_id')) {
                $model->province_id = $model->new_province_id;
            }
            
            // Tự động đồng bộ ward_id với new_ward_id  
            if ($model->isDirty('new_ward_id')) {
                $model->ward_id = $model->new_ward_id;
            }
        });
    }

    /**
     * Mutator cho new_province_id - tự động cập nhật province_id
     */
    public function setNewProvinceIdAttribute($value)
    {
        $this->attributes['new_province_id'] = $value;
        $this->attributes['province_id'] = $value;
    }

    /**
     * Mutator cho new_ward_id - tự động cập nhật ward_id
     */
    public function setNewWardIdAttribute($value)
    {
        $this->attributes['new_ward_id'] = $value;
        $this->attributes['ward_id'] = $value;
    }
}


// $year = '2025-2026';

// $forms = SurveyForm::notDeleted()
//     ->ofYear($year)                 // lọc theo năm trên details
//     ->withDetailsOfYear($year)      // chỉ nạp details của năm đó
//     ->get();
