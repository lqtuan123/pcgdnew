<?php
// app/Services/SurveyLockService.php
namespace App\Services;

use App\Models\SurveyLock;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SurveyLockService
{
    protected int $ttl = 60; // giây (0 = tắt cache)

    /* =========================
     *   API CHÍNH (không DISTRICT)
     * ========================= */

    /** Check 1 scope theo geo: ưu tiên VILLAGE→WARD→PROVINCE→GLOBAL */
    public function isLockedFor(string $scope, ?int $provinceId, ?int $wardId, ?int $villageId): bool
    {
        $levels = [
            ['VILLAGE',  $villageId],
            ['WARD',     $wardId],
            ['PROVINCE', $provinceId],
            ['GLOBAL',   null],
        ];

        foreach ($levels as [$level, $id]) {
            if ($level !== 'GLOBAL' && empty($id)) continue;

            $key = sprintf('survey_lock:%s:%s:%s', $scope, $level, $id ?? 'G');

            $hit = $this->ttl > 0
                ? Cache::remember($key, $this->ttl, fn() => $this->existsActive($scope, $level, $id))
                : $this->existsActive($scope, $level, $id);

            if ($hit) return true;
        }
        return false;
    }

    /** Chỉ cần 1 scope bị khóa => true */
    public function isLockedAny(array $scopes, ?int $provinceId, ?int $wardId, ?int $villageId): bool
    {
        foreach ($scopes as $scope) {
            if ($this->isLockedFor($scope, $provinceId, $wardId, $villageId)) return true;
        }
        return false;
    }

    /** Lấy geo từ request (fallback user) rồi check lock */
    public function isLockedForRequest(string $scope, Request $r): bool
    {
        $u = $r->user();
        $p = $this->toInt($r->input('geo.province_id', $u->province_id ?? null));
        $w = $this->toInt($r->input('geo.ward_id',     $u->ward_id ?? null));
        $v = $this->toInt($r->input('geo.village_id',  $u->village_id ?? null));

        return $this->isLockedFor($scope, $p, $w, $v);
    }

    /** Check theo 1 record (có các cột province_id, ward_id, village_id) */
    public function isLockedForRecord(string $scope, Model $record): bool
    {
        $p = $this->toInt($record->province_id ?? null);
        $w = $this->toInt($record->ward_id ?? null);
        $v = $this->toInt($record->village_id ?? null);

        return $this->isLockedFor($scope, $p, $w, $v);
    }

    /** Ném 423 nếu bị khóa (dùng trong controller) */
    public function guardForRequest(string $scope, Request $r, string $msg = 'Địa bàn đang bị khóa.'): void
    {
        if ($this->isLockedForRequest($scope, $r)) {
            throw new HttpException(423, $msg);
        }
    }
    public function guardForRecord(string $scope, Model $record, string $msg = 'Địa bàn đang bị khóa.'): void
    {
        if ($this->isLockedForRecord($scope, $record)) {
            throw new HttpException(423, $msg);
        }
    }

    /** Tính geo_level & geo_id từ geo truyền vào (dùng cho store) */
    public function detectLevelFromGeo(?int $provinceId, ?int $wardId, ?int $villageId): array
    {
        if ($villageId) return ['geo_level' => 'VILLAGE',  'geo_id' => $villageId];
        if ($wardId)    return ['geo_level' => 'WARD',     'geo_id' => $wardId];
        if ($provinceId)return ['geo_level' => 'PROVINCE', 'geo_id' => $provinceId];
        return ['geo_level' => 'GLOBAL', 'geo_id' => null];
    }

    /** Xóa cache 1 điểm */
    public function forget(string $scope, string $geoLevel, $geoId = null): void
    {
        Cache::forget(sprintf('survey_lock:%s:%s:%s', $scope, $geoLevel, $geoId ?? 'G'));
    }

    /* =========================
     *   PRIVATE
     * ========================= */

    private function existsActive(string $scope, string $geoLevel, $geoId): bool
    {
        $q = SurveyLock::query()
            ->where('scope', $scope)
            ->where('geo_level', $geoLevel)
            ->where('is_active', true);

        $geoLevel === 'GLOBAL'
            ? $q->whereNull('geo_id')
            : $q->where('geo_id', $geoId);

        // Nếu có cột locked_until thì chỉ khóa khi chưa hết hạn
        if (Schema::hasColumn((new SurveyLock)->getTable(), 'locked_until')) {
            $now = Carbon::now();
            $q->where(function ($qq) use ($now) {
                $qq->whereNull('locked_until')->orWhere('locked_until', '>', $now);
            });
        }

        return $q->exists();
    }

    private function toInt($v): ?int
    {
        if ($v === null || $v === '') return null;
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }
}
