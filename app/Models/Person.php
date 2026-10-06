<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; // nếu bạn dùng cho literacy
/**
 * @property string|null $ho_dem
 * @property string|null $ten
 * @property int|null $dob_day
 * @property int|null $dob_month
 * @property int|null $dob_year
 * @property bool $is_female
 * @property int|null $ethnicity_id
 * @property int|null $religion_id
 * @property int|null $special_circumstance_id
 * @property string|null $identity_number
 * @property string|null $phone
 * @property string|null $addressline
 * @property int|null $old_ward_id
 * @property int|null $new_ward_id
 * @property int|null $old_district_id
 * @property bool $is_deleted
 *
 * @property-read string $full_name
 * @property-read string|null $dob_text
 */
class Person extends Model
{
    protected $table = 'people';

    protected $fillable = [
        'ho_dem','ten',
        'dob_day','dob_month','dob_year',
        'is_female',
        'ethnicity_id','religion_id' ,'priority_category_id',
        'identity_number','phone','addressline',
        'old_ward_id','new_ward_id','old_district_id', 'village_id','old_province_id','new_province_id',
        'so_phieu',
        'is_deleted','contact_name','note','residence_type_id','residence_status_id'
    ];

    protected $casts = [
        'dob_day'          => 'integer',
        'dob_month'        => 'integer',
        'dob_year'         => 'integer',
        'is_female'        => 'boolean',
        'is_deleted'       => 'boolean',
        'old_ward_id'      => 'integer',
        'new_ward_id'      => 'integer',
        'old_district_id'  => 'integer',
    ];

    protected $appends = ['full_name', 'dob_text'];

    /* =========================
     |  Accessors / Mutators
     |=========================*/

    // Họ tên đầy đủ (không cần cột full_name)
    public function priorityCategory() {
        return $this->belongsTo(\App\Models\PriorityCategory::class);
    }

    public function fullName(): Attribute
    {
        return Attribute::get(function () {
            return trim(($this->ho_dem ? $this->ho_dem.' ' : '').($this->ten ?? ''));
        });
    }

    // Ngày sinh ảo: đọc/gán qua 'dob' để map sang 3 cột day/month/year
    public function dob(): Attribute
    {
        return Attribute::get(function () {
            if ($this->dob_year && $this->dob_month && $this->dob_day) {
                try {
                    return Carbon::createFromDate($this->dob_year, $this->dob_month, $this->dob_day);
                } catch (\Throwable $e) {
                    return null;
                }
            }
            return null;
        })->set(function ($value) {
            // Chấp nhận: 'YYYY-MM-DD' | Carbon | ['year'=>..., 'month'=>..., 'day'=>...] | [y,m,d]
            if (is_string($value)) {
                try { $dt = Carbon::parse($value); } catch (\Throwable $e) { $dt = null; }
            } elseif ($value instanceof Carbon) {
                $dt = $value;
            } elseif (is_array($value)) {
                $this->attributes['dob_year']  = (int)($value['year'] ?? $value[0] ?? null);
                $this->attributes['dob_month'] = (int)($value['month'] ?? $value[1] ?? null);
                $this->attributes['dob_day']   = (int)($value['day'] ?? $value[2] ?? null);
                return;
            } else {
                $dt = null;
            }

            if ($dt) {
                $this->attributes['dob_year']  = (int) $dt->year;
                $this->attributes['dob_month'] = (int) $dt->month;
                $this->attributes['dob_day']   = (int) $dt->day;
            }
        });
    }

    // Hiển thị ngày sinh dạng dd/mm/yyyy (có thể khuyết)
    public function dobText(): Attribute
    {
        return Attribute::get(function () {
            if ($this->dob_day || $this->dob_month || $this->dob_year) {
                $d = $this->dob_day   ? str_pad($this->dob_day, 2, '0', STR_PAD_LEFT) : '??';
                $m = $this->dob_month ? str_pad($this->dob_month, 2, '0', STR_PAD_LEFT) : '??';
                $y = $this->dob_year ?: '????';
                return "{$d}/{$m}/{$y}";
            }
            return null;
        });
    }

    /* =============
     |  Relations
     |=============*/
    public function type()
    {
        return $this->belongsTo(ResidenceType::class, 'residence_type_id');
    }

    public function status()
    {
        return $this->belongsTo(ResidenceStatus::class, 'residence_status_id');
    }
    public function ethnicity()
    {
        return $this->belongsTo(\App\Models\Ethnicity::class);
    }

    public function religion()
    {
        return $this->belongsTo(\App\Models\Religion::class);
    }

    public function specialCircumstance()
    {
        return $this->belongsTo(\App\Models\SpecialCircumstance::class);
    }
    public function oldWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_ward_id'); }
    public function newWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_ward_id'); }
    public function oldDistrict()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_district_id'); }
    public function oldProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_province_id'); }
    public function newProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_province_id'); }
    public function village()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'village_id'); }
     
    public function surveyForm()
    {
        return $this->belongsTo(\App\Models\SurveyForm::class, 'so_phieu', 'so_phieu');
    }
    
    /* =========
     |  Scopes
     |========*/

    public function scopeActive($q)
    {
        return $q->where('is_deleted', false);
    }

    public function scopeFemale($q)
    {
        return $q->where('is_female', true);
    }

    public function scopeNameLike($q, string $term)
    {
        $term = trim($term);
        if ($term === '') return $q;

        return $q->where(function ($qq) use ($term) {
            $qq->where('ten', 'like', "%{$term}%")
               ->orWhere('ho_dem', 'like', "%{$term}%")
               ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$term}%"]);
        });
    }

    public function scopeInOldWard($q, $wardId)
    {
        return $q->where('old_ward_id', $wardId);
    }

    public function scopeInNewWard($q, $wardId)
    {
        return $q->where('new_ward_id', $wardId);
    }

    public function scopeInOldDistrict($q, $districtId)
    {
        return $q->where('old_district_id', $districtId);
    }

    public function contacts()
    {
        return $this->hasMany(PersonContact::class);
    }
    public function specialCircumstanceLinks()
    {
        return $this->hasMany(PersonSpecialCircumstance::class);
    }

    public function specialCircumstances()
    {
        return $this->belongsToMany(SpecialCircumstance::class, 'person_special_circumstances', 'person_id', 'sc_id')
                    ->withPivot(['chi_tiet'])
                    ->withTimestamps();
    }
    public function disabilities()
    {
        return $this->hasMany(PersonDisability::class);
    }

    public function disabilityCategories() // nếu cần Many-to-Many ảo
    {
        return $this->belongsToMany(
            DisabilityCategory::class,
            'person_disabilities',
            'person_id',
            'disability_category_id'
        )->withPivot([
            'severity_level','has_certificate','certificate_no','certificate_level',
            'certificate_issue_date','issuing_authority',
            'learning_ability','other_text','assessed_at','assessor_name','note','is_active'
        ])->withTimestamps();
    }
    public function literacy()
    {
        return $this->hasOne(PersonLiteracy::class);
    }

    public function educations()
    {
        return $this->hasMany(PersonEducation::class);
    }
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(\App\Models\HouseholdMember::class);
    }

    // N-n với Household, kèm cột role ở pivot
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Household::class, 'household_members')
                    ->withPivot('role')        // 👈 lấy được $person->households[0]->pivot->role
                    ->withTimestamps();
    }
  
    // Tiện ích: trả về role trong 1 hộ cụ thể
    public function roleInHousehold(int $householdId): ?string
    {
        return $this->householdMemberships()
                    ->where('household_id', $householdId)
                    ->value('role');
    }
    public function latestEducation(): HasOne
    {
        return $this->hasOne(PersonEducation::class)->latestOfMany();
    }
    public function disability() // 1 bản ghi JSON cho người này (đang active)
    {
        return $this->hasOne(PersonDisability::class)->notDeleted();
    }
    
}
