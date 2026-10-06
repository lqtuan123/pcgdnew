<?php
// app/Services/Schools/SchoolService.php

namespace App\Services\Schools;

use App\Models\School;
use App\Models\GeoUnit;
use Illuminate\Support\Str;

class SchoolService
{
    /**
     * Chuẩn hoá chuỗi để so tên không dấu/không phân biệt hoa thường.
     */
    protected function norm(?string $s): string
    {
        $s = trim((string)$s);
        $s = Str::ascii($s);
        $s = Str::lower(preg_replace('/\s+/', ' ', $s));
        return $s;
    }

    /**
     * Lấy mã phường/quận từ geo ids theo scheme.
     * @return array{ward_old?:string,ward_new?:string,dist_old?:string}
     */
    protected function extractAreaCodes(string $scheme, array $geo): array
    {
        $out = [];

        // ward
        if (!empty($geo['ward_id'])) {
            $ward = GeoUnit::find($geo['ward_id']);
            if ($ward) {
                if ($scheme === 'new')  $out['ward_new'] = (string)$ward->code;
                if ($scheme === 'old')  $out['ward_old'] = (string)$ward->code;
            }
        }

        // district (chỉ có ở sơ đồ cũ)
        if ($scheme === 'old' && !empty($geo['district_id'])) {
            $dist = GeoUnit::find($geo['district_id']);
            if ($dist) $out['dist_old'] = (string)$dist->code;
        }

        return $out;
    }

    /**
     * Thử khớp trường theo CODE trước (chắc ăn nhất), sau đó theo NAME
     * ưu tiên trong cùng địa bàn; nếu không có thì relax bớt điều kiện.
     *
     * @return array{id:int|null, name:string|null, code:string|null, school:School|null}
     */
    public function resolve(
        string $scheme,
        array $geo,
        ?string $schoolName,
        ?string $schoolCode,
        ?int $educationalLevelId = null
    ): array {
        $codes = $this->extractAreaCodes($scheme, $geo);

        // 1) Ưu tiên theo CODE
        if ($schoolCode = trim((string)$schoolCode)) {
            // Ưu tiên đúng ward; nếu không có, fallback theo code toàn hệ thống
            $q = School::query()->active()->where('code', $schoolCode);

            if ($scheme === 'new' && !empty($codes['ward_new'])) {
                $q->inNewWardCode($codes['ward_new']);
            } elseif ($scheme === 'old' && (!empty($codes['ward_old']) || !empty($codes['dist_old']))) {
                $q->when(!empty($codes['ward_old']), fn($qq)=>$qq->inOldWardCode($codes['ward_old']))
                  ->when(!empty($codes['dist_old']), fn($qq)=>$qq->inDistrictCode($codes['dist_old']));
            }

            if ($educationalLevelId) {
                $q->where('educational_level_id', $educationalLevelId);
            }

            $byCodeInArea = $q->first();
            if ($byCodeInArea) {
                return [
                    'id'     => $byCodeInArea->id,
                    'name'   => $byCodeInArea->name,
                    'code'   => $byCodeInArea->code,
                    'school' => $byCodeInArea,
                ];
            }

            // fallback: chỉ theo code
            $byCode = School::query()->active()
                ->where('code', $schoolCode)
                ->when($educationalLevelId, fn($qq)=>$qq->where('educational_level_id', $educationalLevelId))
                ->first();

            if ($byCode) {
                return [
                    'id'     => $byCode->id,
                    'name'   => $byCode->name,
                    'code'   => $byCode->code,
                    'school' => $byCode,
                ];
            }
        }

        // 2) Theo NAME trong địa bàn (chuẩn hoá để so sánh chắc hơn)
        if ($schoolName = trim((string)$schoolName)) {
            $needle = $this->norm($schoolName);

            $q = School::query()->active();

            if ($scheme === 'new' && !empty($codes['ward_new'])) {
                $q->inNewWardCode($codes['ward_new']);
            } elseif ($scheme === 'old' && (!empty($codes['ward_old']) || !empty($codes['dist_old']))) {
                $q->when(!empty($codes['ward_old']), fn($qq)=>$qq->inOldWardCode($codes['ward_old']))
                  ->when(!empty($codes['dist_old']), fn($qq)=>$qq->inDistrictCode($codes['dist_old']));
            }

            if ($educationalLevelId) {
                $q->where('educational_level_id', $educationalLevelId);
            }

            // gợi ý bằng LIKE trước, rồi lọc strict bằng norm()
            $candidates = $q->where('name', 'like', '%'.str_replace('%','\%',$schoolName).'%')
                            ->limit(20)
                            ->get();

            $strict = $candidates->first(function (School $s) use ($needle) {
                return $this->norm($s->name) === $needle;
            }) ?? $candidates->first();

            if ($strict) {
                return [
                    'id'     => $strict->id,
                    'name'   => $strict->name,
                    'code'   => $strict->code,
                    'school' => $strict,
                ];
            }

            // fallback nới lỏng: tìm toàn hệ thống
            $allCandidates = School::query()->active()
                ->when($educationalLevelId, fn($qq)=>$qq->where('educational_level_id', $educationalLevelId))
                ->where('name', 'like', '%'.str_replace('%','\%',$schoolName).'%')
                ->limit(20)
                ->get();

            $strict2 = $allCandidates->first(function (School $s) use ($needle) {
                return $this->norm($s->name) === $needle;
            }) ?? $allCandidates->first();

            if ($strict2) {
                return [
                    'id'     => $strict2->id,
                    'name'   => $strict2->name,
                    'code'   => $strict2->code,
                    'school' => $strict2,
                ];
            }
        }

        // 3) Không tìm được: trả lại “fallback” để lưu raw
        return [
            'id'     => null,
            'name'   => $schoolName ?: null,
            'code'   => $schoolCode ?: null,
            'school' => null,
        ];
    }
}
