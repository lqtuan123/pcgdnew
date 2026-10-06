<?php

namespace App\Services;

use App\Models\GeoUnit;
use Illuminate\Support\Str;

class GeoMappingService
{
    /**
     * Ghép địa chính cũ với địa chính hiện tại thông qua tên
     */
    public function mapOldGeoToNew(array $oldGeoData): array
    {
        $result = [
            'province_code_old' => $oldGeoData['province_code_old'] ?? null,
            'district_code_old' => $oldGeoData['district_code_old'] ?? null,
            'ward_code_old' => $oldGeoData['ward_code_old'] ?? null,
            'province_code_new' => null,
            'ward_code_new' => null,
            'mapping_notes' => []
        ];

        // Ghép tỉnh/thành phố
        if (!empty($oldGeoData['province_name_old'])) {
            $provinceMapping = $this->mapProvince($oldGeoData['province_name_old']);
            $result['province_code_new'] = $provinceMapping['code'];
            $result['mapping_notes'][] = $provinceMapping['note'];
        }

        // Ghép phường/xã (chỉ khi đã có tỉnh)
        if (!empty($oldGeoData['ward_name_old']) && $result['province_code_new']) {
            $wardMapping = $this->mapWard($oldGeoData['ward_name_old'], $result['province_code_new']);
            $result['ward_code_new'] = $wardMapping['code'];
            $result['mapping_notes'][] = $wardMapping['note'];
        }

        return $result;
    }

    /**
     * Ghép tỉnh/thành phố từ tên cũ sang mã mới
     */
    private function mapProvince(string $oldProvinceName): array
    {
        // Chuẩn hóa tên tỉnh cũ
        $normalizedOldName = $this->normalizeOldGeoName($oldProvinceName);
        
        // Lấy danh sách tỉnh/thành phố hiện tại
        $provinces = GeoUnit::where('geo_type_id', 1) // Tỉnh/Thành phố
            ->where('is_active', true)
            ->get();

        // 1. Tìm kiếm chính xác
        $exactMatch = $this->findExactMatch($normalizedOldName, $provinces);
        if ($exactMatch) {
            return [
                'code' => $exactMatch->code,
                'note' => "Tỉnh '{$oldProvinceName}' → '{$exactMatch->name}' ({$exactMatch->code}) - Ghép chính xác"
            ];
        }

        // 2. Tìm kiếm tương đối
        $similarMatch = $this->findSimilarMatch($normalizedOldName, $provinces);
        if ($similarMatch) {
            return [
                'code' => $similarMatch->code,
                'note' => "Tỉnh '{$oldProvinceName}' → '{$similarMatch->name}' ({$similarMatch->code}) - Ghép tương đối"
            ];
        }

        return [
            'code' => null,
            'note' => "Không tìm thấy tỉnh phù hợp cho '{$oldProvinceName}'"
        ];
    }

    /**
     * Ghép phường/xã từ tên cũ sang mã mới
     */
    private function mapWard(string $oldWardName, string $provinceCode): array
    {
        // Chuẩn hóa tên phường/xã cũ
        $normalizedOldName = $this->normalizeOldGeoName($oldWardName);
        
        // Lấy danh sách phường/xã trong tỉnh
        $wards = GeoUnit::where('geo_type_id', 3) // Phường/Xã
            ->where('parent_code', $provinceCode)
            ->where('is_active', true)
            ->get();

        // 1. Tìm kiếm chính xác
        $exactMatch = $this->findExactMatch($normalizedOldName, $wards);
        if ($exactMatch) {
            return [
                'code' => $exactMatch->code,
                'note' => "Phường/Xã '{$oldWardName}' → '{$exactMatch->name}' ({$exactMatch->code}) - Ghép chính xác"
            ];
        }

        // 2. Tìm kiếm tương đối
        $similarMatch = $this->findSimilarMatch($normalizedOldName, $wards);
        if ($similarMatch) {
            return [
                'code' => $similarMatch->code,
                'note' => "Phường/Xã '{$oldWardName}' → '{$similarMatch->name}' ({$similarMatch->code}) - Ghép tương đối"
            ];
        }

        return [
            'code' => null,
            'note' => "Không tìm thấy phường/xã phù hợp cho '{$oldWardName}' trong tỉnh {$provinceCode}"
        ];
    }

    /**
     * Chuẩn hóa tên địa chính cũ
     */
    private function normalizeOldGeoName(string $name): string
    {
        // Loại bỏ dấu gạch dưới và thay bằng dấu cách
        $normalized = str_replace('_', ' ', $name);
        
        // Loại bỏ các từ khóa không cần thiết
        $normalized = $this->removeGeoKeywords($normalized);
        
        // Chuẩn hóa khoảng trắng
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        
        // Chuyển về chữ thường để so sánh
        return mb_strtolower($normalized);
    }

    /**
     * Loại bỏ các từ khóa địa chính không cần thiết
     */
    private function removeGeoKeywords(string $name): string
    {
        $keywords = [
            'tỉnh', 'thành phố', 'thành phố', 'tp', 'tỉnh',
            'quận', 'huyện', 'thị xã', 'thị trấn', 'TT',
            'phường', 'xã', 'thị trấn'
        ];

        foreach ($keywords as $keyword) {
            $name = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/i', '', $name);
        }

        return $name;
    }

    /**
     * Tìm kiếm chính xác
     */
    private function findExactMatch(string $normalizedOldName, $geoUnits)
    {
        foreach ($geoUnits as $geoUnit) {
            $normalizedCurrentName = mb_strtolower($this->removeGeoKeywords($geoUnit->name));
            
            if ($normalizedOldName === $normalizedCurrentName) {
                return $geoUnit;
            }
        }

        return null;
    }

    /**
     * Tìm kiếm tương đối với độ chính xác cao
     */
    private function findSimilarMatch(string $normalizedOldName, $geoUnits): ?GeoUnit
    {
        $bestMatch = null;
        $bestScore = 0;
        $minScore = 0.7; // Độ chính xác tối thiểu 70%

        foreach ($geoUnits as $geoUnit) {
            $normalizedCurrentName = mb_strtolower($this->removeGeoKeywords($geoUnit->name));
            
            // Tính điểm tương đồng
            $score = $this->calculateSimilarity($normalizedOldName, $normalizedCurrentName);
            
            if ($score > $bestScore && $score >= $minScore) {
                $bestScore = $score;
                $bestMatch = $geoUnit;
            }
        }

        return $bestMatch;
    }

    /**
     * Tính điểm tương đồng giữa hai chuỗi
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Sử dụng Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $maxLen);
    }

    /**
     * Ghép địa chính cho nhiều dòng dữ liệu
     */
    public function mapMultipleGeoData(array $rows): array
    {
        $results = [];
        $stats = [
            'total_rows' => count($rows),
            'mapped_provinces' => 0,
            'mapped_wards' => 0,
            'unmapped_provinces' => 0,
            'unmapped_wards' => 0,
        ];

        foreach ($rows as $index => $row) {
            $geoData = [
                'province_name_old' => $row['province_name_old'] ?? null,
                'ward_name_old' => $row['ward_name_old'] ?? null,
            ];

            $mapping = $this->mapOldGeoToNew($geoData);
            
            // Cập nhật thống kê
            if ($mapping['province_code_new']) {
                $stats['mapped_provinces']++;
            } else {
                $stats['unmapped_provinces']++;
            }

            if ($mapping['ward_code_new']) {
                $stats['mapped_wards']++;
            } else {
                $stats['unmapped_wards']++;
            }

            $results[] = array_merge($row, $mapping);
        }

        return [
            'mapped_data' => $results,
            'stats' => $stats
        ];
    }

    /**
     * Lấy danh sách tỉnh/thành phố để test
     */
    public function getProvincesForTest(): array
    {
        return GeoUnit::where('geo_type_id', 1)
            ->where('is_active', true)
            ->select('code', 'name')
            ->get()
            ->toArray();
    }

    /**
     * Lấy danh sách phường/xã trong tỉnh để test
     */
    public function getWardsForTest(string $provinceCode): array
    {
        return GeoUnit::where('geo_type_id', 3)
            ->where('parent_code', $provinceCode)
            ->where('is_active', true)
            ->select('code', 'name')
            ->get()
            ->toArray();
    }

    /**
     * Generate mã địa chính từ ID được chọn từ geo filter
     * Trả về tất cả mã địa chính cũ và mới để lưu vào database
     */
    public function generateGeoCodesFromSelectedId(?int $selectedId, string $scheme = 'old'): array
    {
        if (!$selectedId) {
            return [
                'selected_province_code_old' => null,
                'selected_district_code_old' => null,
                'selected_ward_code_old' => null,
                'selected_village_code_old' => null,
                'selected_province_code_new' => null,
                'selected_ward_code_new' => null,
                'selected_village_code_new' => null,
            ];
        }

        $selectedUnit = GeoUnit::with('parent.parent.parent')->find($selectedId);
        if (!$selectedUnit) {
            return [
                'selected_province_code_old' => null,
                'selected_district_code_old' => null,
                'selected_ward_code_old' => null,
                'selected_village_code_old' => null,
                'selected_province_code_new' => null,
                'selected_ward_code_new' => null,
                'selected_village_code_new' => null,
            ];
        }
        

        $result = [
            'selected_province_code_old' => null,
            'selected_district_code_old' => null,
            'selected_ward_code_old' => null,
            'selected_village_code_old' => null,
            'selected_province_code_new' => null,
            'selected_ward_code_new' => null,
            'selected_village_code_new' => null,
        ];

        if ($scheme === 'old') {
            // Nếu chọn từ scheme cũ, lấy mã cũ và map sang mã mới
            // Lấy mã tỉnh, huyện, xã, thôn cũ
            if ($selectedUnit->type_level == 1) { // Tỉnh
                $result['selected_province_code_old'] = $selectedUnit->code;
            } elseif ($selectedUnit->type_level == 2) { // Huyện
                $result['selected_district_code_old'] = $selectedUnit->code;
                $province = GeoUnit::find($selectedUnit->parent_id);
                if ($province) {
                    $result['selected_province_code_old'] = $province->code;
                }
            } elseif ($selectedUnit->type_level == 3) { // Xã
                $result['selected_ward_code_old'] = $selectedUnit->code;
                $district = GeoUnit::find($selectedUnit->parent_id);
                if ($district) {
                    $result['selected_district_code_old'] = $district->code;
                    $province = GeoUnit::find($district->parent_id);
                    if ($province) {
                        $result['selected_province_code_old'] = $province->code;
                    }
                }
            } elseif ($selectedUnit->type_level == 4) { // Thôn
                $result['selected_village_code_old'] = $selectedUnit->code;
                $ward = GeoUnit::find($selectedUnit->parent_id);
                if ($ward) {
                    $result['selected_ward_code_old'] = $ward->code;
                    $district = GeoUnit::find($ward->parent_id);
                    if ($district) {
                        $result['selected_district_code_old'] = $district->code;
                        $province = GeoUnit::find($district->parent_id);
                        if ($province) {
                            $result['selected_province_code_old'] = $province->code;
                        }
                    }
                }
            }

            // Map sang mã mới
            $geoService = app(\App\Services\GeoService::class);
            if ($selectedUnit->type_level == 1) { // Tỉnh cũ -> tỉnh mới
                $newProvinceId = $geoService->mapOldIdToNewId($selectedUnit->id, 1);
                if ($newProvinceId) {
                    $newProvince = GeoUnit::find($newProvinceId);
                    if ($newProvince) {
                        $result['selected_province_code_new'] = $newProvince->code;
                    }
                }
            } elseif ($selectedUnit->type_level == 3) { // Xã cũ -> xã mới
                $newWardId = $geoService->mapOldIdToNewId($selectedUnit->id, 3);
                if ($newWardId) {
                    $newWard = GeoUnit::find($newWardId);
                    if ($newWard) {
                        $result['selected_ward_code_new'] = $newWard->code;
                        // Lấy tỉnh mới từ xã mới
                        $newProvince = GeoUnit::find($newWard->parent_id);
                        if ($newProvince) {
                            $result['selected_province_code_new'] = $newProvince->code;
                        }
                    }
                }
            } elseif ($selectedUnit->type_level == 4) { // Thôn cũ -> xã mới
                $wardId = $selectedUnit->parent_id;
                $newWardId = $geoService->mapOldIdToNewId($wardId, 3);
                if ($newWardId) {
                    $newWard = GeoUnit::find($newWardId);
                    if ($newWard) {
                        $result['selected_ward_code_new'] = $newWard->code;
                        // Lấy tỉnh mới từ xã mới
                        $newProvince = GeoUnit::find($newWard->parent_id);
                        if ($newProvince) {
                            $result['selected_province_code_new'] = $newProvince->code;
                        }
                    }
                }
            }
        } else {
            // Nếu chọn từ scheme mới, lấy mã mới và map sang mã cũ
            // Lấy mã tỉnh, xã, thôn mới
            if ($selectedUnit->type_level == 1) { // Tỉnh mới
                $result['selected_province_code_new'] = $selectedUnit->code;
                \Log::info('GeoMapping - Province selected:', [
                    'id' => $selectedUnit->id,
                    'code' => $selectedUnit->code,
                    'name' => $selectedUnit->name
                ]);
            } elseif ($selectedUnit->type_level == 3) { // Xã mới
                $result['selected_ward_code_new'] = $selectedUnit->code;
                $province = GeoUnit::find($selectedUnit->parent_id);
                if ($province) {
                    $result['selected_province_code_new'] = $province->code;
                }
                \Log::info('GeoMapping - Ward selected:', [
                    'ward_id' => $selectedUnit->id,
                    'ward_code' => $selectedUnit->code,
                    'ward_name' => $selectedUnit->name,
                    'province_id' => $province?->id,
                    'province_code' => $province?->code,
                    'province_name' => $province?->name
                ]);
            } elseif ($selectedUnit->type_level == 4) { // Thôn mới
                $result['selected_village_code_new'] = $selectedUnit->code;
                $ward = GeoUnit::find($selectedUnit->parent_id);
                if ($ward) {
                    $result['selected_ward_code_new'] = $ward->code;
                    $province = GeoUnit::find($ward->parent_id);
                    if ($province) {
                        $result['selected_province_code_new'] = $province->code;
                    }
                }
            }

            // Map sang mã cũ
            $geoService = app(\App\Services\GeoService::class);
            if ($selectedUnit->type_level == 1) { // Tỉnh mới -> tỉnh cũ
                $oldProvinceIds = $geoService->oldCodesMappedFromNewProvince($selectedUnit->code);
                if (!empty($oldProvinceIds['old_district_codes'])) {
                    $result['selected_district_code_old'] = $oldProvinceIds['old_district_codes'][0] ?? null;
                }
                if (!empty($oldProvinceIds['old_ward_codes'])) {
                    $result['selected_ward_code_old'] = $oldProvinceIds['old_ward_codes'][0] ?? null;
                }
            } elseif ($selectedUnit->type_level == 3) { // Xã mới -> xã cũ
                $oldCodes = $geoService->oldCodesMappedFromNewWard($selectedUnit->code);
                if (!empty($oldCodes['l3'])) {
                    $result['selected_ward_code_old'] = $oldCodes['l3'][0] ?? null;
                }
                if (!empty($oldCodes['l2'])) {
                    $result['selected_district_code_old'] = $oldCodes['l2'][0] ?? null;
                }
            }
        }

        return $result;
    }
}
