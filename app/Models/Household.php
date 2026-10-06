<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = [
        'name','addressline','old_ward_id','new_ward_id','old_district_id','village_id', 
        'phone','is_deleted','head_person_id', 'ticket_no', 'old_province_id','new_province_id',// 👈 thêm
        'residence_status_id', 'residence_type_id', // 👈 thêm
    ];

    protected $casts = ['is_deleted' => 'boolean'];
    public function oldWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_ward_id'); }
    public function newWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_ward_id'); }
    public function oldDistrict()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_district_id'); }
    public function oldProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_province_id'); }
    public function newProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_province_id'); }
    public function village()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'village_id'); }
    public function type()
    {
        return $this->belongsTo(ResidenceType::class, 'residence_type_id');
    }
public function people(): BelongsToMany
{
    return $this->belongsToMany(Person::class, 'household_members')
                ->withPivot('role')
                ->withTimestamps();
}
    public function status()
    {
        return $this->belongsTo(ResidenceStatus::class, 'residence_status_id');
    }

    public function memberLinks(): HasMany
    {
        return $this->hasMany(\App\Models\HouseholdMember::class);
    }

    public function headPerson() {
        return $this->belongsTo(Person::class, 'head_person_id');
    }

    public function members() {
        return $this->hasMany(HouseholdMember::class);
    }

    

    // Đảm bảo "tên hộ gia đình = tên chủ hộ" nếu có chủ hộ
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if ($m->head_person_id && empty($m->name)) {
                $head = Person::find($m->head_person_id);
                if ($head) $m->name = $head->full_name;
            }
        });
    }

    // Cho phép getNameAttribute() luôn trả về tên chủ hộ nếu cột 'name' rỗng
    public function getNameAttribute($value) {
        return $value ?: ($this->headPerson?->full_name);
    }
}
