<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class StudentsOffTrack extends Model
{
    protected $table = 'students_off_track';

    protected $fillable = [
        // Thông tin cá nhân
        'ho_dem',
        'ten',
        'dob_day',
        'dob_month',
        'dob_year',
        'identity_number',
        'is_female',
        
        // Thông tin tham chiếu (ID) - nối sang CSDL hiện có
        'ethnicity_id',
        'religion_id',
        'priority_category_id',
        'education_block_id',
        'school_id',
        'graduation_level_id',
        
        // Mã địa bàn cũ (3 cấp từ Excel) - nơi ở từ Excel
        'stay_province_code_old',
        'stay_district_code_old',
        'stay_ward_code_old',
        
        // Mã địa bàn mới (2 cấp, bỏ quận/huyện) - nơi ở từ Excel
        'stay_province_code_new',
        'stay_ward_code_new',
        
        // Mã địa bàn từ select box (khu vực được chọn)
        'selected_province_code_old',
        'selected_district_code_old',
        'selected_ward_code_old',
        'selected_village_code_old',
        'selected_province_code_new',
        'selected_ward_code_new',
        'selected_village_code_new',
        
        // Thông tin học tập - lưu trực tiếp
        'class_label',
        'hoc_hai_buoi',
        'hoc_9_10_buoi_tuan',
        'is_bo_tuc',
        'graduation_year',
        'bo_hoc_lop',
        'bo_hoc_nam',
        
        // Thông tin khuyết tật - lưu trực tiếp
        'disability_van_dong',
        'disability_nghe_noi',
        'disability_nhin',
        'disability_than_kinh',
        'disability_tri_tue',
        'disability_hoc_tap',
        'disability_tu_ky',
        'disability_khac',
        'disability_has_certificate',
        'disability_can_learn',
        
        // Hoàn cảnh đặc biệt - lưu trực tiếp
        'special_circumstance_name',
        'special_circumstance_detail',
        
        // Thông tin import
        'academic_year',
        'import_batch',
        
        // Thông tin quản lý
        'is_deleted',
        'editor_id',
    ];

    protected $casts = [
        'dob_day' => 'integer',
        'dob_month' => 'integer',
        'dob_year' => 'integer',
        'is_female' => 'boolean',
        'ethnicity_id' => 'integer',
        'religion_id' => 'integer',
        'priority_category_id' => 'integer',
        'education_block_id' => 'integer',
        'school_id' => 'integer',
        'graduation_level_id' => 'integer',
        'hoc_hai_buoi' => 'boolean',
        'hoc_9_10_buoi_tuan' => 'boolean',
        'is_bo_tuc' => 'boolean',
        'bo_hoc_lop' => 'integer',
        'bo_hoc_nam' => 'integer',
        'disability_van_dong' => 'boolean',
        'disability_nghe_noi' => 'boolean',
        'disability_nhin' => 'boolean',
        'disability_than_kinh' => 'boolean',
        'disability_tri_tue' => 'boolean',
        'disability_hoc_tap' => 'boolean',
        'disability_tu_ky' => 'boolean',
        'disability_khac' => 'boolean',
        'disability_has_certificate' => 'boolean',
        'is_deleted' => 'boolean',
        'editor_id' => 'integer',
    ];

    protected $appends = ['full_name'];

    // Accessors
    public function fullName(): Attribute
    {
        return Attribute::get(function () {
            return trim(($this->ho_dem ? $this->ho_dem.' ' : '').($this->ten ?? ''));
        });
    }

    // Relationships
    public function ethnicity()
    {
        return $this->belongsTo(Ethnicity::class);
    }

    public function religion()
    {
        return $this->belongsTo(Religion::class);
    }

    public function priorityCategory()
    {
        return $this->belongsTo(PriorityCategory::class);
    }

    public function educationBlock()
    {
        return $this->belongsTo(EducationBlock::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function graduationLevel()
    {
        return $this->belongsTo(GraduationLevel::class);
    }

    public function boHocLopEducationBlock()
    {
        return $this->belongsTo(EducationBlock::class, 'bo_hoc_lop');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }


    // Scopes
    public function scopeByAcademicYear($query, string $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopeByImportBatch($query, string $batch)
    {
        return $query->where('import_batch', $batch);
    }


    public function scopeFemale($query)
    {
        return $query->where('is_female', true);
    }

    public function scopeMale($query)
    {
        return $query->where('is_female', false);
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    // Methods để lấy tên địa chính theo scheme
    public function getStayProvinceName(string $scheme = 'old'): ?string
    {
        if ($scheme === 'old') {
            $code = $this->stay_province_code_old;
        } else {
            $code = $this->stay_province_code_new;
        }
        
        if (!$code) return null;
        
        $geoUnit = GeoUnit::where('code', $code)->first();
        return $geoUnit?->name;
    }

    public function getStayDistrictName(string $scheme = 'old'): ?string
    {
        if ($scheme === 'old') {
            $code = $this->stay_district_code_old;
        } else {
            return null; // Hành chính mới không có huyện
        }
        
        if (!$code) return null;
        
        $geoUnit = GeoUnit::where('code', $code)->first();
        return $geoUnit?->name;
    }

    public function getStayWardName(string $scheme = 'old'): ?string
    {
        if ($scheme === 'old') {
            $code = $this->stay_ward_code_old;
        } else {
            $code = $this->stay_ward_code_new;
        }
        
        if (!$code) return null;
        
        $geoUnit = GeoUnit::where('code', $code)->first();
        return $geoUnit?->name;
    }
}
