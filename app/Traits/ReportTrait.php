<?php

namespace App\Traits;

use App\Models\GeoUnit;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait ReportTrait
{
    /**
     * Scope: Lọc theo quyền truy cập của user
     */
    public function scopeByUserAccess(Builder $query, $user)
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isProvince()) {
            return $query->byProvince($user->province_id);
        }

        if ($user->isWard()) {
            return $query->ofWard($user->ward_id);
        }

        if ($user->isSchool()) {
            return $query->bySchool($user->school_id);
        }

        return $query->whereRaw('1 = 0'); // Không có quyền truy cập
    }

    /**
     * Scope: Lọc theo tỉnh (thông qua ward)
     */
    public function scopeByProvince(Builder $query, $provinceId)
    {
        return $query->whereHas('ward', function($q) use ($provinceId) {
            $q->whereHas('parent', function($subQ) use ($provinceId) {
                $subQ->where('id', $provinceId);
            });
        });
    }

    /**
     * Scope: Lọc theo trường học
     */
    public function scopeBySchool(Builder $query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope: Lọc theo xã/phường
     */
    public function scopeOfWard(Builder $query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    /**
     * Scope: Lọc theo năm học
     */
    public function scopeByAcademicYear(Builder $query, $year)
    {
        return $query->where('academic_year', $year);
    }

    /**
     * Scope: Tìm kiếm theo tên trường
     */
    public function scopeSearchBySchool(Builder $query, $schoolName)
    {
        return $query->where('school_name', 'like', "%{$schoolName}%");
    }

    /**
     * Lấy thông tin địa lý đầy đủ
     */
    public function getGeoInfo()
    {
        $ward = $this->ward;
        $school = $this->school;
        
        return [
            'ward_id' => $this->ward_id,
            'ward_name' => $ward ? $ward->name : 'Không xác định',
            'school_id' => $this->school_id,
            'school_name' => $this->school_name,
            'school_code' => $school ? $school->code : null,
            'province_id' => $ward ? $ward->getProvinceId() : null,
            'province_name' => $ward ? $ward->getProvinceId() ? GeoUnit::find($ward->getProvinceId())->name : 'Không xác định' : 'Không xác định',
        ];
    }

    /**
     * Lấy danh sách năm học có dữ liệu
     */
    public static function getAvailableAcademicYears($user = null)
    {
        $query = static::query();
        
        if ($user) {
            $query->byUserAccess($user);
        }
        
        return $query->select('academic_year')
            ->distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year')
            ->toArray();
    }

    /**
     * Lấy danh sách xã/phường có dữ liệu
     */
    public static function getAvailableWards($user = null, $academicYear = null)
    {
        $query = static::query();
        
        if ($user) {
            $query->byUserAccess($user);
        }
        
        if ($academicYear) {
            $query->byAcademicYear($academicYear);
        }
        
        return $query->with('ward')
            ->select('ward_id')
            ->distinct()
            ->get()
            ->pluck('ward')
            ->filter()
            ->values();
    }

    /**
     * Lấy danh sách trường học có dữ liệu
     */
    public static function getAvailableSchools($user = null, $academicYear = null, $wardId = null)
    {
        $query = static::query();
        
        if ($user) {
            $query->byUserAccess($user);
        }
        
        if ($academicYear) {
            $query->byAcademicYear($academicYear);
        }
        
        if ($wardId) {
            $query->ofWard($wardId);
        }
        
        return $query->with(['school' => function($q) { $q->active(); }])
            ->select('school_id')
            ->distinct()
            ->get()
            ->pluck('school')
            ->filter(function($school) {
                return $school && !$school->is_dissolved;
            })
            ->values();
    }

    /**
     * Kiểm tra quyền truy cập của user với báo cáo này
     */
    public function canAccess($user)
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isProvince()) {
            $ward = $this->ward;
            return $ward && $ward->getProvinceId() == $user->province_id;
        }

        if ($user->isWard()) {
            return $this->ward_id == $user->ward_id;
        }

        if ($user->isSchool()) {
            return $this->school_id == $user->school_id;
        }

        return false;
    }

    /**
     * Lấy thông tin hiển thị cho báo cáo
     */
    public function getDisplayInfo()
    {
        $geoInfo = $this->getGeoInfo();
        
        return [
            'id' => $this->id,
            'academic_year' => $this->academic_year,
            'school_name' => $this->school_name,
            'school_code' => $geoInfo['school_code'],
            'ward_name' => $geoInfo['ward_name'],
            'province_name' => $geoInfo['province_name'],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

