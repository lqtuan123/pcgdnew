<?php
// app/Services/Geo/StudyAreaResolver.php

namespace App\Services\Schools;

use App\Models\GeoUnit;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class StudyAreaResolver
{
    /** Chuẩn hoá: bỏ dấu, lowercase, gom khoảng trắng */
    protected function norm(?string $s): string
    {
        $s = trim((string)$s);
        $s = Str::ascii($s);
        $s = Str::lower(preg_replace('/\s+/', ' ', $s));
        return $s;
    }

    /** Bỏ tiền tố hành chính (huyen/quan/tp/xa/phuong/thi tran/thi xa/…) trên chuỗi đã norm */
    protected function stripPrefix(string $norm): string
    {
        // ví dụ: "huyen ea kar" -> "ea kar"; "phuong 12" -> "12"
        $norm = preg_replace(
            '/^(huyen|quan|thi xa|thi tran|tp|thanh pho|xa|phuong)\s+/u',
            '',
            $norm
        );
        return trim($norm);
    }

    /** Chọn ứng viên "khớp nhất" theo norm/core (=norm ko tiền tố), ưu tiên exact => startsWith/contains => gần đúng */
    protected function pickBest(Collection $cands, string $needleNorm, string $needleCore): ?GeoUnit
    {
        if ($cands->isEmpty()) return null;

        // 1) exact (norm hoặc core)
        $exact = $cands->first(function (GeoUnit $g) use ($needleNorm, $needleCore) {
            $n = $this->norm($g->name);
            $c = $this->stripPrefix($n);
            return $n === $needleNorm || $c === $needleCore;
        });
        if ($exact) return $exact;

        // 2) startsWith / contains
        $starts = $cands->first(function (GeoUnit $g) use ($needleCore) {
            $c = $this->stripPrefix($this->norm($g->name));
            return Str::startsWith($c, $needleCore);
        });
        if ($starts) return $starts;

        $contains = $cands->first(function (GeoUnit $g) use ($needleCore) {
            $c = $this->stripPrefix($this->norm($g->name));
            return Str::contains($c, $needleCore);
        });
        if ($contains) return $contains;

        // 3) gần đúng: similar_text >= 0.8 (80%)
        $best = null; $bestScore = 0.0;
        foreach ($cands as $g) {
            $c = $this->stripPrefix($this->norm($g->name));
            similar_text($needleCore, $c, $pct);
            if ($pct > $bestScore) { $bestScore = $pct; $best = $g; }
        }
        return $bestScore >= 80 ? $best : null;
    }

    /**
     * Resolve id đơn vị học tập từ tên tự do.
     *
     * - scheme 'new': chỉ tìm cấp 3 (ward) trực tiếp dưới tỉnh.
     * - scheme 'old': thử cấp 2 (district) dưới tỉnh; nếu không khớp thì tìm cấp 3 (ward) dưới tất cả district thuộc tỉnh.
     *
     * @param string      $scheme 'old'|'new'
     * @param array       $geo    ['province_id'=>..., 'district_id'=>...?]
     * @param string|null $raw    tên đơn vị (có thể “Huyện …”, “Phường …”, “Xã …”, hoặc chỉ tên thuần)
     * @return int|null   geo_units.id nếu tìm thấy
     */
    public function resolve(?string $scheme, array $geo, ?string $raw): ?int
    {
        $scheme = $scheme === 'new' ? 'new' : 'old';
        $raw = trim((string)$raw);
        if ($raw === '') return null;

        $provinceId = $geo['province_id'] ?? null;
        if (!$provinceId) return null;

        $needleNorm = $this->norm($raw);
        $needleCore = $this->stripPrefix($needleNorm);

        if ($scheme === 'new') {
            // chỉ cấp 3 trực tiếp dưới tỉnh
            $wards = GeoUnit::where('scheme','new')
                ->where('type_level', 3)
                ->where('parent_id', $provinceId)
                ->get();
            $pick = $this->pickBest($wards, $needleNorm, $needleCore);
            return $pick?->id;
        }

        // OLD: thử cấp 2 trước
        $dists = GeoUnit::where('scheme','old')
            ->where('type_level', 2)
            ->where('parent_id', $provinceId)
            ->get();

        if ($dists->isNotEmpty()) {
            $pickDist = $this->pickBest($dists, $needleNorm, $needleCore);
            if ($pickDist) return $pickDist->id;
        }

        // rồi đến cấp 3 (ward) thuộc các district nói trên
        $distIds = $dists->pluck('id')->all();
        if (empty($distIds)) return null;

        $wards = GeoUnit::where('scheme','old')
            ->where('type_level', 3)
            ->whereIn('parent_id', $distIds)
            ->get();

        $pickWard = $this->pickBest($wards, $needleNorm, $needleCore);
        return $pickWard?->id;
    }
}
