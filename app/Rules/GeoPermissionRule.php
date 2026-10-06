<?php

namespace App\Rules;

use App\Models\GeoUnit;
use App\Models\School;
use Illuminate\Contracts\Validation\Rule;

class GeoPermissionRule implements Rule
{
    protected $permissionLevel;
    protected $geoUnitId;
    protected $schoolId;

    public function __construct($permissionLevel, $geoUnitId = null, $schoolId = null)
    {
        $this->permissionLevel = $permissionLevel;
        $this->geoUnitId = $geoUnitId;
        $this->schoolId = $schoolId;
    }

    public function passes($attribute, $value)
    {
        switch ($this->permissionLevel) {
            case 'province':
                if (!$this->geoUnitId) {
                    return false;
                }
                $geoUnit = GeoUnit::find($this->geoUnitId);
                return $geoUnit && $geoUnit->type_level === 1;
                
            case 'commune':
                if (!$this->geoUnitId) {
                    return false;
                }
                $geoUnit = GeoUnit::find($this->geoUnitId);
                return $geoUnit && $geoUnit->type_level === 3;
                
            case 'school':
                if (!$this->schoolId || !$this->geoUnitId) {
                    return false;
                }
                $school = School::find($this->schoolId);
                $geoUnit = GeoUnit::find($this->geoUnitId);
                
                if (!$school || !$geoUnit || $geoUnit->type_level !== 3) {
                    return false;
                }
                
                // Kiểm tra trường có thuộc xã được chọn không
                return $school->ward_code_new === $geoUnit->code;
                
            default:
                return false;
        }
    }

    public function message()
    {
        switch ($this->permissionLevel) {
            case 'province':
                return 'Phân quyền tỉnh cần chọn tỉnh hợp lệ.';
            case 'commune':
                return 'Phân quyền xã cần chọn xã hợp lệ.';
            case 'school':
                return 'Phân quyền trường cần chọn trường và xã hợp lệ, trường phải thuộc xã được chọn.';
            default:
                return 'Cấp phân quyền không hợp lệ.';
        }
    }
}
