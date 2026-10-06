<?php

namespace App\Services;

use App\Models\GeoUnit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
class ExcelGeoMappingService
{
    /**
     * Map geo data từ Excel với database và generate các mã tương ứng
     * 
     * @param array $excelGeoData ['province_name' => '...', 'district_name' => '...', 'ward_name' => '...']
     * @return array ['stay_province_code_old' => '...', 'stay_district_code_old' => '...', 'stay_ward_code_old' => '...', 'stay_province_code_new' => '...', 'stay_ward_code_new' => '...']
     */
    public function mapExcelGeoToDbGeo(array $excelGeoData): array
    {
        $result = [
            'stay_province_code_old' => null,
            'stay_district_code_old' => null,
            'stay_ward_code_old' => null,
            'stay_province_code_new' => null,
            'stay_ward_code_new' => null,
        ];

        // Map từng cấp địa chính và generate mã tương ứng
        if (!empty($excelGeoData['province_name'])) {
            $provinceCode = $this->findGeoCodeByName($excelGeoData['province_name'], 1);
            if ($provinceCode) {
                $result['stay_province_code_old'] = $provinceCode;
                // Generate mã mới từ mã cũ
                $result['stay_province_code_new'] = $this->generateNewCodeFromOld($provinceCode, 1);
            }
        }

        if (!empty($excelGeoData['district_name'])) {
            $districtCode = $this->findGeoCodeByName($excelGeoData['district_name'], 2);
            if ($districtCode) {
                $result['stay_district_code_old'] = $districtCode;
                // District không có trong scheme mới, để null
            }
        }

        if (!empty($excelGeoData['ward_name'])) {
            // Tìm trong scheme mới trước (vì file Excel mới chỉ có mã mới)
            $wardUnit = GeoUnit::where('scheme', 'new')
                ->where('type_level', 3)
                ->get()
                ->first(function ($unit) use ($excelGeoData) {
                    $normalizedExcelName = $this->normalizeGeoName($excelGeoData['ward_name']);
                    $normalizedDbName = $this->normalizeGeoName($unit->name);
                    return $normalizedDbName === $normalizedExcelName;
                });
            
            if ($wardUnit) {
                // File Excel mới chỉ có mã mới
                $result['stay_ward_code_new'] = $wardUnit->code;
                // Tìm mã cũ tương ứng (nếu có)
                $result['stay_ward_code_old'] = $this->generateOldCodeFromNew($wardUnit->code, 3);
            } else {
                // Fallback: tìm trong scheme cũ như trước
                $wardCode = $this->findGeoCodeByName($excelGeoData['ward_name'], 3);
                if ($wardCode) {
                    $result['stay_ward_code_old'] = $wardCode;
                    $result['stay_ward_code_new'] = $this->generateNewCodeFromOld($wardCode, 3);
                }
            }
        }

        return $result;
    }

    /**
     * Tìm mã địa chính theo tên và cấp
     * 
     * @param string $name Tên địa chính từ Excel
     * @param int $level Cấp địa chính (1: tỉnh, 2: huyện, 3: xã)
     * @return string|null Mã địa chính hoặc null nếu không tìm thấy
     */
    private function findGeoCodeByName(string $name, int $level): ?string
    {
        // Chuẩn hóa tên từ Excel
        $normalizedExcelName = $this->normalizeGeoName($name);
        
        Log::info('Excel geo mapping:', [
            'original_name' => $name,
            'normalized_name' => $normalizedExcelName,
            'level' => $level
        ]);

        // Tìm trong database (tìm trong cả scheme cũ và mới)
        $geoUnit = GeoUnit::whereIn('scheme', ['old', 'new'])
            ->where('type_level', $level)
            ->get()
            ->first(function ($unit) use ($normalizedExcelName) {
                // Chuẩn hóa cả tên từ database
                $normalizedDbName = $this->normalizeGeoName($unit->name);
                return $normalizedDbName === $normalizedExcelName;
            });

        if ($geoUnit) {
            Log::info('Found geo match:', [
                'excel_name' => $name,
                'db_name' => $geoUnit->name,
                'excel_normalized' => $normalizedExcelName,
                'db_normalized' => $this->normalizeGeoName($geoUnit->name),
                'code' => $geoUnit->code
            ]);
            return $geoUnit->code;
        }

        Log::warning('No geo match found:', [
            'excel_name' => $name,
            'normalized_name' => $normalizedExcelName,
            'level' => $level
        ]);

        return null;
    }

    /**
     * Chuẩn hóa tên địa chính để so sánh (giống như dân tộc)
     * 
     * @param string $name Tên gốc
     * @return string Tên đã chuẩn hóa
     */
    private function normalizeGeoName(string $name): string
    {
        $name = trim($name);
        
        // Bỏ "Thành phố" hoặc "Tỉnh" ở đầu
        if (preg_match('/^Thành phố\s+/u', $name)) {
            $name = preg_replace('/^Thành phố\s+/u', '', $name);
        } elseif (preg_match('/^Tỉnh\s+/u', $name)) {
            $name = preg_replace('/^Tỉnh\s+/u', '', $name);
        } elseif (preg_match('/^Thành_phố+/u', $name)) {
            $name = preg_replace('/^Thành_phố+/u', '', $name);
        }
        
        // Thay "TT" thành "Thị Trấn"
        $name = str_replace('TT', 'Thị Trấn', $name);
        
        // Thay "TP" thành "Thành phố"
        $name = str_replace('TP', '', $name);

        // Thay "X." thành "Xã"
        $name = str_replace('X.', 'Xã', $name);

        // Thay "Đăk" thành "Đắk"
        $name = str_replace('Đăk', 'Đắk', $name);

        // Bỏ hết dấu cách và dấu _ (giống như dân tộc)
        $name = str_replace([' ', '_'], '', $name);
        
        // Đưa về chữ thường
        $name = mb_strtolower($name, 'UTF-8');
        
        return $name;
    }

    /**
     * Generate mã mới từ mã cũ
     * 
     * @param string $oldCode Mã cũ
     * @param int $level Cấp địa chính (1: tỉnh, 3: xã)
     * @return string|null Mã mới hoặc null nếu không tìm thấy
     */
    private function generateNewCodeFromOld(string $oldCode, int $level): ?string
    {
        // Tìm GeoUnit từ mã cũ
        $oldUnit = GeoUnit::where('scheme', 'old')
            ->where('code', $oldCode)
            ->where('type_level', $level)
            ->first();

        if (!$oldUnit) {
            return null;
        }

        // Sử dụng GeoService để map sang mã mới
        $geoService = app(\App\Services\GeoService::class);
        
        if ($level === 1) { // Tỉnh
            $newProvinceId = $geoService->mapOldIdToNewId($oldUnit->id, 1);
            if ($newProvinceId) {
                $newProvince = GeoUnit::find($newProvinceId);
                return $newProvince?->code;
            }
        } elseif ($level === 3) { // Xã
            $newWardId = $geoService->mapOldIdToNewId($oldUnit->id, 3);
            if ($newWardId) {
                $newWard = GeoUnit::find($newWardId);
                return $newWard?->code;
            }
        }

        return null;
    }

    /**
     * Validate geo data từ Excel và trả về lỗi nếu có
     * 
     * @param array $excelGeoData
     * @return array ['valid' => bool, 'errors' => array, 'mapped_codes' => array]
     */
    public function validateExcelGeoData(array $excelGeoData): array
    {
        $errors = [];
        $mappedCodes = $this->mapExcelGeoToDbGeo($excelGeoData);

        // Kiểm tra tỉnh
        if (!empty($excelGeoData['province_name']) && empty($mappedCodes['stay_province_code_old'])) {
            $errors[] = "Không tìm thấy tỉnh/thành phố: " . $excelGeoData['province_name'];
        }

        // Kiểm tra huyện
        if (!empty($excelGeoData['district_name']) && empty($mappedCodes['stay_district_code_old'])) {
            $errors[] = "Không tìm thấy quận/huyện: " . $excelGeoData['district_name'];
        }

        // Kiểm tra xã - chỉ yêu cầu mã mới cho file Excel mới
        if (!empty($excelGeoData['ward_name']) && empty($mappedCodes['stay_ward_code_new'])) {
            $errors[] = "Không tìm thấy phường/xã: " . $excelGeoData['ward_name'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mapped_codes' => $mappedCodes
        ];
    }

    /**
     * Generate mã cũ từ mã mới (tạm thời return null vì chưa có mapping)
     */
    private function generateOldCodeFromNew(string $newCode, int $level): ?string
    {
        // Tạm thời return null vì chưa có logic mapping từ mới sang cũ
        // TODO: Implement mapping từ scheme mới sang scheme cũ
        return null;
    }
}
