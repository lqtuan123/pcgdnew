<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'code','name','school_type_id','educational_level_id','ward_code','district_code','ward_code_new','is_dissolved','province_code','province_code_new',
        'old_ward_id','new_ward_id','old_district_id','village_id', 'old_province_id','new_province_id','is_conglap','is_datchuan'
    ];
    protected $casts = [
        'is_dissolved' => 'boolean',
         'is_conglap' => 'boolean',
          'is_datchuan' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where(function($w) {
            $w->where('is_dissolved', false)
              ->orWhereNull('is_dissolved');
        })->where(function($w) {
            $w->whereNull('code')
              ->orWhere('code', 'not like', 'NGOAI_TINH%');
        });
    }
    public function oldWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_ward_id'); }
    public function newWard()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_ward_id'); }
    public function oldDistrict()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_district_id'); }
    public function oldProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'old_province_id'); }
    public function newProvince()  { return $this->belongsTo(\App\Models\GeoUnit::class, 'new_province_id'); }
    public function village()      { return $this->belongsTo(\App\Models\GeoUnit::class, 'village_id'); }
   
    public function schoolType(){ return $this->belongsTo(SchoolType::class); }
    public function educationalLevel(){ return $this->belongsTo(EducationalLevel::class); }

    // Liên kết theo CODE tới GeoUnit (không theo id)
    // public function wardUnit(){ return $this->belongsTo(GeoUnit::class, 'ward_code', 'code'); }
    public function districtUnit(){ return $this->belongsTo(GeoUnit::class, 'district_code', 'code'); }

    public function enrollmentAreas(){ return $this->hasMany(SchoolEnrollmentArea::class); }
    public function teachers(){ return $this->hasMany(Teacher::class); }

    /* Scopes tiện dụng */
    public function scopeSearch($q, ?string $term){
        if(!$term) return $q;
        $term = trim($term);
        return $q->where(fn($w)=>$w
            ->where('name','like',"%$term%")
            ->orWhere('code','like',"%$term%")
        );
    }
    public function scopeLevel($q, $levelName){
        return $q->whereHas('educationalLevel', fn($s)=>$s->where('name',$levelName));
    }
    public function wardUnit()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'ward_code',  'code'); }
    public function newWardUnit(){ return $this->belongsTo(\App\Models\GeoUnit::class, 'ward_code_new','code'); }
    public function district(){ return $this->belongsTo(\App\Models\GeoUnit::class, 'district_code','code'); }
    public function provinceNew()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'province_code_new',  'code'); }
    public function province()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'province_code','code'); }
    public function wardNew()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'ward_code_new','code'); }
    public function ward()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'ward_code','code'); }
    public function provinceOld()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'province_code',  'code'); }
    public function wardOld()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'ward_code','code'); }
    public function districtOld()   { return $this->belongsTo(\App\Models\GeoUnit::class, 'district_code','code'); }
   
    public function wardUnitNew()
    {
        // join theo 'code' vì trường lưu mã phường mới là string
        return $this->hasOne(GeoUnit::class, 'code', 'ward_code_new')
            ->where('scheme', 'new')
            ->where('type_level', 3);
    }
    public function scopeInOldWardCode($q, ?string $wardCode) {
        return $wardCode ? $q->where('ward_code', $wardCode) : $q;
    }
    public function scopeInNewWardCode($q, ?string $wardCodeNew) {
        return $wardCodeNew ? $q->where('ward_code_new', $wardCodeNew) : $q;
    }
    public function scopeInDistrictCode($q, ?string $distCode) {
        return $distCode ? $q->where('district_code', $distCode) : $q;
    }

    /**
     * Lấy ward_id từ ward_code_new
     */
    public function getWardId(): ?int
    {
        if (!$this->ward_code_new) {
            return null;
        }
        
        $ward = GeoUnit::where('code', $this->ward_code_new)
            ->where('scheme', 'new')
            ->where('type_level', 3)
            ->first();
            
        return $ward ? $ward->id : null;
    }

    /**
     * Lấy province_id từ ward_code_new (thông qua ward)
     */
    public function getProvinceId(): ?int
    {
        $wardId = $this->getWardId();
        if (!$wardId) {
            return null;
        }
        
        $ward = GeoUnit::find($wardId);
        if (!$ward || !$ward->parent_id) {
            return null;
        }
        
        // Lấy province (cấp 1) từ ward (cấp 3)
        $province = GeoUnit::find($ward->parent_id);
        if (!$province || $province->type_level !== 1) {
            return null;
        }
        
        return $province->id;
    }

    /**
     * Lấy thông tin địa lý đầy đủ từ school
     */
    public function getGeoInfo(): array
    {
        return [
            'ward_id' => $this->getWardId(),
            'province_id' => $this->getProvinceId(),
            'ward_code_new' => $this->ward_code_new,
        ];
    }

}
