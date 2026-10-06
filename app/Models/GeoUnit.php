<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeoUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'geo_type_id','type_level','parent_id',
        'uuid','code','name','slug',
        'scheme','valid_from','valid_to','meta',
        'is_difficult_area',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'meta'       => 'array',
        'is_difficult_area' => 'boolean',
    ];

    // Quan hệ
    public function type()    { return $this->belongsTo(GeoType::class, 'geo_type_id'); }
    public function parent()  { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(){ return $this->hasMany(self::class, 'parent_id'); }

    // Ánh xạ: cũ -> mới
    public function mappedTo() {
        return $this->belongsToMany(
            self::class, 'geo_unit_mappings', 'old_unit_id', 'new_unit_id'
        )->withPivot(['relation','weight','effective_date','note'])->withTimestamps();
    }

    // Ánh xạ: mới -> cũ
    public function mappedFrom() {
        return $this->belongsToMany(
            self::class, 'geo_unit_mappings', 'new_unit_id', 'old_unit_id'
        )->withPivot(['relation','weight','effective_date','note'])->withTimestamps();
    }

    // Accessor: tên có danh xưng theo geo_type (ví dụ "TP. Hà Nội", "Tỉnh Thanh Hóa")
    public function getDisplayNameAttribute(): string
    {
        if (!$this->type) return $this->name;

        $label = $this->type->short_vi ?: $this->type->title_vi;
        $label = "";
        return $this->type->use_prefix ? ($label.' '.$this->name) : ($this->name.' '.$label);
    }

    // Scopes tiện dụng
    public function scopeScheme($q, string $scheme)    { return $q->where('scheme', $scheme); }
    public function scopeLevel($q, int $level)         { return $q->where('type_level', $level); }
    public function scopeProvinces($q)                 { return $q->level(1); }
    public function scopeDistricts($q)                 { return $q->level(2); }
    public function scopeCommunes($q)                  { return $q->level(3); }
    public function scopeVillages($q)                  { return $q->level(4); }

    /**
     * Lấy province_id từ ward_id (thông qua parent_id)
     */
    public function getProvinceId(): ?int
    {
        if ($this->type_level === 1) {
            // Đã là province
            return $this->id;
        }
        
        if ($this->type_level === 3) {
            // Ward -> Province (trực tiếp, không qua district)
            if ($this->parent && $this->parent->type_level === 1) {
                return $this->parent->id;
            }
            // Fallback: Ward -> District -> Province (nếu có district)
            $district = $this->parent;
            if ($district && $district->parent && $district->parent->type_level === 1) {
                return $district->parent->id;
            }
        }
        
        if ($this->type_level === 2) {
            // District -> Province
            if ($this->parent && $this->parent->type_level === 1) {
                return $this->parent->id;
            }
        }
        
        return null;
    }

    /**
     * Lấy thông tin địa lý đầy đủ từ GeoUnit
     */
    public function getGeoInfo(): array
    {
        return [
            'province_id' => $this->getProvinceId(),
            'ward_id' => $this->type_level === 3 ? $this->id : null,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
