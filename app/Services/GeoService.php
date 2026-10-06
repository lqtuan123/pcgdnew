<?php

namespace App\Services;

use App\Models\GeoUnit;
use App\Models\GeoUnitMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeoService
{
  /** Lấy mã TỈNH CŨ (old province code) từ mã XÃ CŨ */


    public function resolveNewGeoFromNames(string $provName, ?string $wardName, ?string $hamletName): array
    {
        $province = $this->findNewByNameHier(1, null, $provName);
        if (!$province) {
            // Không tìm thấy tỉnh -> trả về rỗng theo format
            return [
                'scheme'      => 'new',
                'province_id' => null,
                'district_id' => null,
                'ward_id'     => null,
                'village_id'  => null,
            ];
        }

        $ward = null;
        if ($wardName !== null && $wardName !== '') {
            $ward = $this->findNewByNameHier(3, (int)$province->id, $wardName);
        }

        $village = null;
        if ($ward && $hamletName !== null && $hamletName !== '') {
            $village = $this->findNewByNameHier(4, (int)$ward->id, $hamletName);
        }

        return [
            'scheme'      => 'new',
            'province_id' => (int)$province->id,
            'district_id' => null,
            'ward_id'     => $ward?->id ? (int)$ward->id : null,
            'village_id'  => $village?->id ? (int)$village->id : null,
        ];
    }

    /**
     * Tìm 1 GeoUnit theo scheme=new + level, có thể giới hạn theo parent_id, dựa trên tên.
     * Ưu tiên khớp chuẩn hoá ==, sau đó đến contains/startsWith. Trả null nếu không có.
     */
    protected function findNewByNameHier(int $level, ?int $parentId, string $name): ?GeoUnit
    {
        $nameNorm = $this->normName($name);

        $q = GeoUnit::query()
            ->where('scheme', 'new')
            ->where('type_level', $level);

        if ($parentId) {
            $q->where('parent_id', $parentId);
        }

        // Lấy tất cả ứng viên trong phạm vi (thường là vài chục)
        $candidates = $q->get(['id','name','parent_id','type_level']);

        if ($candidates->isEmpty()) return null;

        // 1) Khớp tuyệt đối sau chuẩn hoá
        $exact = $candidates->first(function (GeoUnit $u) use ($nameNorm) {
            return $this->normName($u->name) === $nameNorm;
        });
        if ($exact) return $exact;

        // 2) Khớp "bắt đầu bằng" (hữu ích khi Excel bỏ bớt tiền tố)
        $starts = $candidates->first(function (GeoUnit $u) use ($nameNorm) {
            return str_starts_with($this->normName($u->name), $nameNorm)
                || str_starts_with($nameNorm, $this->normName($u->name));
        });
        if ($starts) return $starts;

        // 3) Khớp "chứa"
        $contains = $candidates->first(function (GeoUnit $u) use ($nameNorm) {
            return str_contains($this->normName($u->name), $nameNorm)
                || str_contains($nameNorm, $this->normName($u->name));
        });
        if ($contains) return $contains;

        return null;
    }

    /**
     * Chuẩn hoá tên: bỏ dấu, lowercase, bỏ khoảng thừa, thay '_' thành ' ', bỏ tiền tố hành chính (tỉnh, tp, xã, phường, thị trấn, thôn, ấp, buôn...).
     */
    protected function normName(string $s): string
    {
        $s = strtr($s, ['_' => ' ']);
        $s = Str::of($s)->lower()->squish();

        // bỏ dấu tiếng Việt
        $s = strtr((string)$s, [
            'đ'=>'d','ă'=>'a','â'=>'a','á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'ê'=>'e','é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ô'=>'o','ơ'=>'o','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ư'=>'u','ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
        ]);

        // loại tiền tố/hậu tố hành chính phổ biến
        $s = preg_replace('~\b(tinh|thanh pho|tp|do thi|thi xa|quan|huyen|xa|phuong|thi tran|thon|xom|ap|buon|ban|to dan pho)\b~u', '', (string)$s);
        return trim((string)Str::of($s)->squish());
    }
    public function idFromCode(?string $code): ?int
    {
        if (!$code) return null;
        static $cache = [];
        if (array_key_exists($code, $cache)) return $cache[$code];

        $u = $this->unitFromCode($code); // -> ?GeoUnit
        return $cache[$code] = ($u?->id ?? null);
    }
    
    public function parentIdOf(int|string $idOrCode, ?string $scheme = null): ?int
    {
        // Trường hợp truyền ID
        if (is_int($idOrCode) || ctype_digit((string)$idOrCode)) {
            $unitId = (int) $idOrCode;
            return Cache::remember("geo.parent_id:id:{$unitId}", 600, function () use ($unitId) {
                $u = GeoUnit::query()->select('parent_id')->find($unitId);
                return $u?->parent_id;
            });
        }

        // Trường hợp truyền code -> cần scheme
        if (!$scheme) {
            throw ValidationException::withMessages([
                'scheme' => 'Khi tra cứu theo code, cần chỉ rõ scheme (old|new).',
            ]);
        }

        $code = (string) $idOrCode;

        return Cache::remember("geo.parent_id:code:{$scheme}:{$code}", 600, function () use ($scheme, $code) {
            $u = GeoUnit::query()
                ->select('parent_id')
                ->where('scheme', $scheme)
                ->where('code', $code)
                ->first();
            return $u?->parent_id;
        });
    }
    // Lấy ID theo scheme + code (an toàn hơn nếu có khả năng trùng mã giữa old/new)
    public function idByCode(string $scheme, ?string $code): ?int
    {
        if (!$code) return null;
        static $cache = [];
        $key = $scheme.'|'.$code;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $u = $this->unitByCode($scheme, $code); // -> ?GeoUnit
        return $cache[$key] = ($u?->id ?? null);
    }

   public function geoNameFromId( $id): ?string
    {
        if (!$id) return null;

        // Tìm xã cũ
        $unit = GeoUnit::find( $id);
        if (!$unit) return null;

        // Lên HUYỆN rồi TỈNH
        return $unit?->name;
    }
    public function geoCodeFromId( $id): ?string
    {
        if (!$id) return null;

        // Tìm xã cũ
        $unit = GeoUnit::find( $id);
        if (!$unit) return null;

        // Lên HUYỆN rồi TỈNH
        return $unit?->code;
    }
    public function oldProvinceCodeFromOldWard(?string $oldWardCode): ?string
    {
        if (!$oldWardCode) return null;

        // Tìm xã cũ
        $ward = GeoUnit::where('scheme', 'old')
            ->where('type_level', 3)
            ->where('code', $oldWardCode)
            ->first();

        if (!$ward) return null;

        // Lên HUYỆN rồi TỈNH
        $district = GeoUnit::find($ward->parent_id);
        $province = $district ? GeoUnit::find($district->parent_id) : null;

        return $province?->code;
    }

    /** (tuỳ chọn) Trả về ID tỉnh cũ từ mã xã cũ */
    public function oldProvinceIdFromOldWard(?string $oldWardCode): ?int
    {
        if (!$oldWardCode) return null;

        $ward = GeoUnit::where('scheme', 'old')
            ->where('type_level', 3)
            ->where('code', $oldWardCode)
            ->first();

        if (!$ward) return null;

        $district = GeoUnit::find($ward->parent_id);
        $province = $district ? GeoUnit::find($district->parent_id) : null;

        return $province?->id;
    }

    /** (tuỳ chọn) Nếu đầu vào là mã xã cũ dạng Excel "42_181_2698" */
    public function oldProvinceCodeFromExcelWard(?string $excelWardCode): ?string
    {
        $code = $this->oldCodeFromExcel($excelWardCode, 3); // đã có sẵn trong service
        return $code ? $this->oldProvinceCodeFromOldWard($code) : null;
    }
    public function newUnitFromOldCode(?string $oldCode, ?int $expectedLevel = null, ?array $relations = null): ?\App\Models\GeoUnit
    {
        if (!$oldCode) return null;

        // Tìm đơn vị CŨ theo code
        $old = $this->unitByCode('old', $oldCode);
        if (!$old) return null;

        // Map sang ID đơn vị MỚI (ưu tiên relation theo weight)
        // - $expectedLevel = null  -> lấy mapping "tốt nhất"
        // - $expectedLevel = 1|2|3 -> ép cấp mong muốn bên sơ đồ "mới"
        $newId = $this->mapOldIdToNewId((int)$old->id, $expectedLevel, $relations);

        return $newId ? \App\Models\GeoUnit::find($newId) : null;
    }

    /**
     * Trả về code MỚI từ code CŨ.
     *
     * @param string|null $oldCode        Mã "cũ" (ví dụ: cu_42_123_456)
     * @param int|null    $expectedLevel  Ép cấp ở sơ đồ "mới" (1: tỉnh, 3: xã). Null = lấy mapping tốt nhất.
     * @param array|null  $relations      Tập quan hệ cho phép (mặc định: ['successor','unchanged','merged_into','split_from'])
     */
    public function newCodeFromOldCode(?string $oldCode, ?int $expectedLevel = null, ?array $relations = null): ?string
    {
        $u = $this->newUnitFromOldCode($oldCode, $expectedLevel, $relations);
        return $u?->code;
    }

    // (tuỳ chọn) Helper chuyên biệt: map XÃ cũ -> mã XÃ mới
    public function newWardCodeFromOldWard(?string $oldWardCode): ?string
    {
        $u = $this->newUnitFromOldCode($oldWardCode, 3);
        return $u?->code;
    }

     public function mapOldIdToNewId(int $oldId, ?int $expectedLevel = null, ?array $relations = null): ?int
    {
        $relations ??= ['successor', 'unchanged', 'merged_into', 'split_from'];

        // Lấy tất cả mapping old->new theo weight giảm dần
        $maps = GeoUnitMapping::query()
            ->where('old_unit_id', $oldId)
            ->whereIn('relation', $relations)
            ->orderByDesc('weight')
            ->orderBy('id')
            ->get(['new_unit_id']);

        if ($maps->isEmpty()) {
            return null;
        }

        // Nếu không ép cấp -> lấy mapping "tốt nhất"
        if ($expectedLevel === null) {
            return (int) $maps->first()->new_unit_id;
        }

        // Có ép cấp -> tìm new_unit có type_level mong muốn
        foreach ($maps as $m) {
            $new = GeoUnit::find($m->new_unit_id);
            if ($new && $new->scheme === 'new' && (int)$new->type_level === $expectedLevel) {
                return (int) $new->id;
            }
        }

        // Không có bản đồ phù hợp cấp yêu cầu
        return null;
    }
    public function unitFromCode(string $code): ?GeoUnit
    {
        return GeoUnit::where('code', $code)->first();
    }
    public function unitByCode(string $scheme, string $code): ?GeoUnit
    {
        return GeoUnit::where('scheme', $scheme)->where('code', $code)->first();
    }
    public function mapNewWardToOldCodes(?string $newWardCode): array
    {
        $oldWard = $this->mapNewWardToOldCode($newWardCode);
        $oldDist = $oldWard ? $this->oldDistrictCodeFromOldWard($oldWard) : null;
        return ['ward_old_code' => $oldWard, 'district_old_code' => $oldDist];
    }
    // ==== OLD helpers ====
    public function oldCodesByProvince(string $provCodeOld): array
    {
        $prov = GeoUnit::where('scheme', 'old')->where('type_level', 1)->where('code', $provCodeOld)->first();
        if (!$prov) return [[], []];

        $distIds   = GeoUnit::where('scheme', 'old')->where('type_level', 2)
            ->where('parent_id', $prov->id)->pluck('id')->all();
        $distCodes = $distIds ? GeoUnit::whereIn('id', $distIds)->pluck('code')->all() : [];
        $wardCodes = $distIds
            ? GeoUnit::where('scheme', 'old')->where('type_level', 3)
            ->whereIn('parent_id', $distIds)->pluck('code')->all()
            : [];

        return [$distCodes, $wardCodes]; // [Lv2, Lv3]
    }

    public function oldWardCodesByDistrict(string $distCodeOld): array
    {
        $dist = GeoUnit::where('scheme', 'old')->where('type_level', 2)->where('code', $distCodeOld)->first();
        if (!$dist) return [];
        return GeoUnit::where('scheme', 'old')->where('type_level', 3)
            ->where('parent_id', $dist->id)->pluck('code')->all();
    }

    // ==== NEW helpers ====
    public function newWardCodesByProvince(string $provCodeNew): array
    {
        $prov = GeoUnit::where('scheme', 'new')->where('type_level', 1)->where('code', $provCodeNew)->first();
        if (!$prov) return [];
        $result = GeoUnit::where('scheme','new')->where('type_level',3)
            ->where('parent_id',$prov->id)->pluck('code')->all();
        // dd($prov, $result);
        return $result;
    }

    public function newWardCodesByDistrict(string $distCodeNew): array
    {
        $dist = GeoUnit::where('scheme', 'new')->where('type_level', 2)->where('code', $distCodeNew)->first();
        if (!$dist) return [];
        return GeoUnit::where('scheme', 'new')->where('type_level', 3)
            ->where('parent_id', $dist->id)->pluck('code')->all();
    }

    // ==== Mapping -> OLD from NEW ====
    protected function oldCodesFromMappedOldIds(array $oldIds): array
    {
        if (empty($oldIds)) return ['l2' => [], 'l3' => []];

        $olds = GeoUnit::whereIn('id', $oldIds)->get(['id', 'code', 'type_level', 'parent_id', 'scheme']);

        $l2 = $olds->where('type_level', 2)->pluck('code')->all();
        $l3 = $olds->where('type_level', 3)->pluck('code')->all();

        // nếu map tới tỉnh CŨ (Lv1) => mở rộng toàn bộ cấp 2,3 bên dưới
        $l1Ids = $olds->where('type_level', 1)->pluck('id')->all();
        if ($l1Ids) {
            $distIds   = GeoUnit::where('scheme', 'old')->where('type_level', 2)
                ->whereIn('parent_id', $l1Ids)->pluck('id')->all();
            $distCodes = $distIds ? GeoUnit::whereIn('id', $distIds)->pluck('code')->all() : [];
            $wardCodes = $distIds
                ? GeoUnit::where('scheme', 'old')->where('type_level', 3)
                ->whereIn('parent_id', $distIds)->pluck('code')->all()
                : [];
            $l2 = array_values(array_unique(array_merge($l2, $distCodes)));
            $l3 = array_values(array_unique(array_merge($l3, $wardCodes)));
        }

        return ['l2' => $l2, 'l3' => $l3];
    }

    public function oldCodesMappedFromNewWard(string $newWardCode): array
    {
        $new = $this->unitByCode('new', $newWardCode);
        if (!$new) return ['l2' => [], 'l3' => []];
        $oldIds = GeoUnitMapping::where('new_unit_id', $new->id)->pluck('old_unit_id')->unique()->all();
        return $this->oldCodesFromMappedOldIds($oldIds);
    }

    public function oldCodesMappedFromNewDistrict(string $newDistCode): array
    {
        $dist = GeoUnit::where('scheme', 'new')->where('type_level', 2)->where('code', $newDistCode)->first();
        if (!$dist) return ['l2' => [], 'l3' => []];

        $directOldIds = GeoUnitMapping::where('new_unit_id', $dist->id)->pluck('old_unit_id')->all();

        $newWardIds = GeoUnit::where('scheme', 'new')->where('type_level', 3)
            ->where('parent_id', $dist->id)->pluck('id')->all();
        $indirectOldIds = $newWardIds
            ? GeoUnitMapping::whereIn('new_unit_id', $newWardIds)->pluck('old_unit_id')->all()
            : [];

        $oldIds = array_values(array_unique(array_merge($directOldIds, $indirectOldIds)));
        return $this->oldCodesFromMappedOldIds($oldIds);
    }

    public function oldCodesMappedFromNewProvince(string $newProvCode): array
    {
        $prov = GeoUnit::where('scheme','new')
            ->where('type_level',1)
            ->where('code',$newProvCode)
            ->first();

        if (!$prov) {
            return ['old_district_ids' => [], 'old_district_codes' => [], 'old_ward_ids' => [], 'old_ward_codes' => []];
        }

        // 1) Lấy danh sách đơn vị CŨ được map trực tiếp từ tỉnh mới
        $oldUnitIdsRaw = GeoUnitMapping::where('new_unit_id', $prov->id)
            ->pluck('old_unit_id')->all();

        // 2) Lọc ra đúng "tỉnh cũ" (L1)
        $oldProvinceIds = GeoUnit::where('scheme','old')
            ->whereIn('id', $oldUnitIdsRaw)
            ->where('type_level', 1)
            ->pluck('id')->all();

        // 3) Lấy tất cả HUYỆN cũ (L2) thuộc các tỉnh cũ ở trên
        $oldDistricts = empty($oldProvinceIds) ? collect() :
            GeoUnit::where('scheme','old')
                ->where('type_level', 2)
                ->whereIn('parent_id', $oldProvinceIds)
                ->get(['id','code']);

        $oldDistrictIds   = $oldDistricts->pluck('id')->all();
        $oldDistrictCodes = $oldDistricts->pluck('code')->filter()->values()->all();

        // (Tuỳ chọn) Nếu mapping tỉnh mới có map trực tiếp tới L2 thì cộng gộp thêm:
        if (!empty($oldUnitIdsRaw)) {
            $directL2 = GeoUnit::where('scheme','old')
                ->where('type_level', 2)
                ->whereIn('id', $oldUnitIdsRaw)
                ->get(['id','code']);
            $oldDistrictIds   = array_values(array_unique(array_merge($oldDistrictIds,   $directL2->pluck('id')->all())));
            $oldDistrictCodes = array_values(array_unique(array_merge($oldDistrictCodes, $directL2->pluck('code')->filter()->all())));
        }

        // 4) Lấy toàn bộ XÃ cũ (L3) bên dưới các huyện cũ
        $oldWards = empty($oldDistrictIds) ? collect() :
            GeoUnit::where('scheme','old')
                ->where('type_level', 3)
                ->whereIn('parent_id', $oldDistrictIds)
                ->get(['id','code']);

        $oldWardIds   = $oldWards->pluck('id')->all();
        $oldWardCodes = $oldWards->pluck('code')->filter()->values()->all();

        return [
            'old_district_ids'   => $oldDistrictIds,
            'old_district_codes' => $oldDistrictCodes,
            'old_ward_ids'       => $oldWardIds,
            'old_ward_codes'     => $oldWardCodes,
        ];

    }

    public function codesUnderProvince(string $provinceCode, string $scheme = 'old', array $levels = [2, 3]): array
    {
        $cacheKey = "geo.codesUnderProvince.{$scheme}.{$provinceCode}." . implode('-', $levels);

        return Cache::remember($cacheKey, 600, function () use ($provinceCode, $scheme, $levels) {
            $result = [];

            $prov = $this->unitByCode($scheme, $provinceCode);
            if (!$prov) {
                return [2 => [], 3 => [], 4 => []];
            }

            $level2 = [];
            if (in_array(2, $levels, true)) {
                $level2 = GeoUnit::query()
                    ->where('scheme', $scheme)
                    ->where('type_level', 2)
                    ->where('parent_id', $prov->id)
                    ->pluck('code', 'id');
                $result[2] = array_values($level2->all());
            } else {
                $result[2] = [];
            }

            $level3ParentIds = in_array(2, $levels, true)
                ? $level2->keys()->all()
                : [$prov->id];

            $level3 = [];
            if (in_array(3, $levels, true) && !empty($level3ParentIds)) {
                $level3 = GeoUnit::query()
                    ->where('scheme', $scheme)
                    ->where('type_level', 3)
                    ->whereIn('parent_id', $level3ParentIds)
                    ->pluck('code', 'id');
                $result[3] = array_values($level3->all());
            } else {
                $result[3] = [];
            }

            if (in_array(4, $levels, true) && $level3 instanceof \Illuminate\Support\Collection && $level3->isNotEmpty()) {
                $level4 = GeoUnit::query()
                    ->where('scheme', $scheme)
                    ->where('type_level', 4)
                    ->whereIn('parent_id', $level3->keys()->all())
                    ->pluck('code')
                    ->all();
                $result[4] = $level4;
            } else {
                $result[4] = [];
            }

            return $result;
        });
    }

    public function oldProvinceDistrictWardCodes(string $provinceCode): array
    {
        $map = $this->codesUnderProvince($provinceCode, 'old', [2, 3]);
        return [$map[2] ?? [], $map[3] ?? []];
    }

    public function newProvinceWardVillageCodes(string $provinceCode): array
    {
        $map = $this->codesUnderProvince($provinceCode, 'new', [3, 4]);
        return [$map[3] ?? [], $map[4] ?? []];
    }

    public function oldDistrictCodeFromOldWard(?string $oldWardCode): ?string
    {
        $w = $this->unitByCode('old', $oldWardCode);
        if (!$w) return null;
        $parent = $w->parent;
        return $parent?->code;
    }

    public function mapOldWardToNewCode(?string $oldWardCode): ?string
    {
        $old = $this->unitByCode('old', $oldWardCode);
        if (!$old) return null;

        $rel = ['successor', 'unchanged', 'merged_into', 'split_from'];
        $m = GeoUnitMapping::where('old_unit_id', $old->id)
            ->whereIn('relation', $rel)
            ->orderByDesc('weight')->orderBy('id')
            ->first();

        if (!$m) return null;
        $new = GeoUnit::find($m->new_unit_id);
        return $new?->code;
    }

    public function mapNewWardToOldCode(?string $newWardCode): ?string
    {
        $new = $this->unitByCode('new', $newWardCode);
        if (!$new) return null;

        $rel = ['successor', 'unchanged', 'merged_into', 'split_from'];
        $m = GeoUnitMapping::where('new_unit_id', $new->id)
            ->whereIn('relation', $rel)
            ->orderByDesc('weight')->orderBy('id')
            ->first();

        if (!$m) return null;
        $old = GeoUnit::find($m->old_unit_id);
        return $old?->code;
    }

    public function normalizeComposite(?string $code, ?int $segmentsExpected = null): ?string
    {
        if (!$code) return null;
        $s = preg_replace('~[^0-9]+~', '_', trim((string)$code));
        $s = trim($s, '_');
        if ($s === '') return null;

        if ($segmentsExpected !== null) {
            $pieces = explode('_', $s);
            if (count($pieces) !== $segmentsExpected) {
                return null;
            }
        }
        return $s;
    }

    public function addOldPrefix(string $normalized): string
    {
        return Str::startsWith($normalized, 'cu_') ? $normalized : ('cu_' . $normalized);
    }

    public function oldCodeFromExcel(?string $excelCode, int $segments): ?string
    {
        $norm = $this->normalizeComposite($excelCode, $segments);
        return $norm ? $this->addOldPrefix($norm) : null;
    }

    public function findOldWardByExcelCode(?string $excelCode): ?GeoUnit
    {
        $code = $this->oldCodeFromExcel($excelCode, 3);
        return $code
            ? GeoUnit::where('scheme', 'old')->where('type_level', 3)->where('code', $code)->first()
            : null;
    }

    public function findOldDistrictByExcelCode(?string $excelCode): ?GeoUnit
    {
        $code = $this->oldCodeFromExcel($excelCode, 2);
        return $code
            ? GeoUnit::where('scheme', 'old')->where('type_level', 2)->where('code', $code)->first()
            : null;
    }

    public function fuzzyFindOldByName(string $name, int $level, ?int $parentId = null): ?GeoUnit
    {
        $q = GeoUnit::where('scheme', 'old')->where('type_level', $level);
        if ($parentId) $q->where('parent_id', $parentId);

        $norm = $this->vn($name);
        return $q->get()->first(function (GeoUnit $u) use ($norm) {
            return Str::contains($this->vn($u->name), $norm);
        });
    }

    public function vn(string $s): string
    {
        $s = Str::of($s)->lower()->squish();
        $s = strtr($s, [
            'đ' => 'd', 'ă' => 'a', 'â' => 'a', 'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'ê' => 'e', 'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ô' => 'o', 'ơ' => 'o', 'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ư' => 'u', 'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ]);
        return (string)$s;
    }
}
