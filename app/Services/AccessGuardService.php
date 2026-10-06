<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccessGuardService
{
    public function __construct(
        protected SurveyLockService $locks // service lock có scope & VILLAGE/WARD/PROVINCE/GLOBAL
    ) {}

    /**
     * 1) Bắt buộc có quyền ($permission, $action)
     * 2) Nếu không phải admin => ép scheme & gò địa phận theo user
     * 3) Trả về context: user, is_admin, scheme, geo (province_id, ward_id, village_id)
     */
    public function enforce(Request $r, string $permission, string $action = 'view', array $opts = []): array
    {
        $opts = array_merge([
            'force_scheme'        => 'new',   // luôn “new” trừ khi bạn set null
            'admin_role_ids'      => [1, 2],  // role admin
            'merge_geo_from_user' => true,    // non-admin: ép geo từ user
            'geo_keys'            => ['province_id', 'ward_id', 'village_id'],
            'abort_on_forbidden'  => true,
        ], $opts);

        $user = Auth::user();

        // Quyền
        if (!$user || !$user->hasPermission($permission, $action)) {
            if ($opts['abort_on_forbidden']) {
                abort(403, "Bạn không có quyền {$action} {$permission}.");
            }
            throw new HttpException(403, 'Forbidden');
        }

        $isAdmin = $this->isAdmin($user, $opts['admin_role_ids']);

        // Ép scheme
        if (!empty($opts['force_scheme'])) {
            $r->merge(['scheme' => $opts['force_scheme']]);
        }

        // Non-admin: ép geo từ user (user ghi đè request)
        if (!$isAdmin && $opts['merge_geo_from_user']) {
            $geoReq  = (array) $r->input('geo', []);
            $geoUser = [];
            foreach ($opts['geo_keys'] as $key) {
                $geoUser[$key] = $user->{$key} ?? null;
            }
            $geoUser = array_filter($geoUser, fn($v) => !is_null($v));
            $r->merge(['geo' => array_merge($geoReq, $geoUser)]);
        }

        return [
            'user'     => $user,
            'isAdmin' => $isAdmin,
            'scheme'   => $r->input('scheme'),
            'geo'      => $this->geoFrom($r, $user, $opts['geo_keys']),
        ];
    }

    /** Kết hợp enforce + check lock (1 scope) */
    public function enforceAndAssertUnlocked(
        Request $r,
        string $permission,
        string $action,
        string $scope,
        array $opts = [],
        int $status = 423,
        ?string $message = null
    ): array {
        $ctx = $this->enforce($r, $permission, $action, $opts);
        $this->assertUnlockedOrAbort($scope, $ctx['geo'], $status, $message);
        return $ctx;
    }

    /** Lấy geo chuẩn từ Request (fallback user) */
    public function geoFrom(Request $r, $user = null, array $keys = ['province_id','ward_id','village_id']): array
    {
        $geo = [];
        $src = (array) $r->input('geo', []);
        foreach ($keys as $k) {
            $geo[$k] = $this->toInt($src[$k] ?? optional($user)->{$k} ?? null);
        }
        return $geo;
    }

    /** Lấy geo từ record (model có cột province_id, ward_id, village_id) */
    // public function geoFromRecord(Model $record): array
    // {
    //     return [
    //         'province_id' => $this->toInt($record->province_id ?? null),
    //         'ward_id'     => $this->toInt($record->ward_id ?? null),
    //         'village_id'  => $this->toInt($record->village_id ?? null),
    //     ];
    // }

    /**
     * Kiểm tra lock theo scope & geo (Request|array geo); nếu bị khóa -> abort 423.
     * scope: SURVEY | STATS_SEND | STATS_INPUT
     */
    public function assertUnlockedOrAbort(string $scope, Request|array $src, int $status = 423, ?string $message = null): void
    {
        $geo = $src instanceof Request ? $this->geoFrom($src) : $src;

        $locked = $this->locks->isLockedFor(
            $scope,
            $geo['province_id'] ?? null,
            $geo['ward_id']     ?? null,
            $geo['village_id']  ?? null,
        );

        if ($locked) {
            abort($status, $message ?: "Địa bàn đang bị khóa cho {$scope}.");
        }
    }

    /** Giống trên nhưng cho nhiều scope: chỉ cần 1 scope bị khóa là abort */
    public function assertUnlockedAnyOrAbort(array $scopes, Request|array $src, int $status = 423, ?string $message = null): void
    {
        $geo = $src instanceof Request ? $this->geoFrom($src) : $src;

        if ($this->locks->isLockedAny($scopes, $geo['province_id'] ?? null, $geo['ward_id'] ?? null, $geo['village_id'] ?? null)) {
            abort($status, $message ?: "Địa bàn đang bị khóa.");
        }
    }

    /** Check lock từ 1 record (update/delete) */
    public function assertUnlockedForRecordOrAbort(string $scope, Model $record, int $status = 423, ?string $message = null): void
    {
        if ($this->locks->isLockedForRecord($scope, $record)) {
            abort($status, $message ?: "Địa bàn đang bị khóa cho {$scope}.");
        }
    }

    /** Hạn chế query theo geo (tuỳ dùng) */
    public function applyGeoToQuery($query, array $geo)
    {
        if (!empty($geo['province_id'])) $query->where('province_id', $geo['province_id']);
        if (!empty($geo['ward_id']))     $query->where('ward_id',     $geo['ward_id']);
        if (!empty($geo['village_id']))  $query->where('village_id',  $geo['village_id']);
        return $query;
    }

    public function isAdmin($user, array $ids = [1,2]): bool
    {
        return $user && in_array((int)($user->role_id ?? 0), $ids, true);
    }

    private function toInt($v): ?int
    {
        if ($v === null || $v === '') return null;
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }

       /* ========= A) Dùng cho UPDATE/DELETE (có record) ========= */
    public function guardRecordGeoAndLock(
        Request $r,
        Model|array $record,
        ?string $scope = null,
        array $opts = []
    ): array {
        $opts = array_merge([
            'admin_role_ids'     => [1,2],
            'record_keys'        => [
                'province' => 'new_province_id',
                'ward'     => 'new_ward_id',
                'village'  => 'new_village_id',
                'province_fallback' => 'province_id',
                'ward_fallback'     => 'ward_id',
                'village_fallback'  => 'village_id',
            ],
            'forbidden_message'  => 'Bạn không được phép thao tác ngoài địa bàn được phân quyền.',
            'no_geo_message'     => 'Tài khoản chưa được gán địa bàn.',
            'locked_message'     => 'Địa bàn đang bị khóa.',
            'admin_bypass_lock'  => false,
        ], $opts);

        $user    = Auth::user();
        $isAdmin = $this->isAdmin($user, $opts['admin_role_ids']);

        $recGeo  = $this->geoFromRecord($record, $opts['record_keys']);
        $usrGeo  = $this->geoFromUser($user); // <-- luôn dùng province_id/ward_id/village_id
       
        // Non-admin: record phải thuộc phạm vi user
        if (!$isAdmin) {
            if (!$usrGeo['province'] && !$usrGeo['ward'] && !$usrGeo['village']) {
                throw new HttpException(403, $opts['no_geo_message']);
            }

            $inScope = true;
            if ($usrGeo['village']) {
                $inScope = ($recGeo['village'] && (int)$recGeo['village'] === (int)$usrGeo['village']);
            } elseif ($usrGeo['ward']) {
                $inScope = ($recGeo['ward'] && (int)$recGeo['ward'] === (int)$usrGeo['ward']);
            } elseif ($usrGeo['province']) {
                $inScope = ($recGeo['province'] && (int)$recGeo['province'] === (int)$usrGeo['province']);
            }

            if (!$inScope) {
                throw new HttpException(403, $opts['forbidden_message']);
            }
        }

        // (Tuỳ chọn) chặn theo lock scope
       

        if ($scope && ($isAdmin ? !$opts['admin_bypass_lock'] : true)) {
            $locked = $this->locks->isLockedFor(
                $scope,
                $recGeo['province'],
                $recGeo['ward'],
                $recGeo['village'],
            );
            
            if ($locked) throw new HttpException(423, $opts['locked_message']." (scope: {$scope})");
        }

        return [
            'user'      => $user,
            'is_admin'  => $isAdmin,
            'recordGeo' => $recGeo,
            'userGeo'   => $usrGeo,
        ];
    }

    /* ========= B) Dùng cho CREATE (không có record) ========= */
    public function guardRequestGeoAndLock(
        Request $r,
        ?string $scope = null,
        array $opts = []
    ): array {
        $opts = array_merge([
            'admin_role_ids'     => [1,2],
            'forbidden_message'  => 'Bạn không được phép thao tác ngoài địa bàn được phân quyền.',
            'no_geo_message'     => 'Tài khoản chưa được gán địa bàn.',
            'locked_message'     => 'Địa bàn đang bị khóa.',
            'admin_bypass_lock'  => false,
        ], $opts);

        $user    = Auth::user();
        $isAdmin = $this->isAdmin($user, $opts['admin_role_ids']);

        // Lấy geo từ request; nếu thiếu thì fallback user
        $geo = [
            'province' => $this->toInt($r->input('geo.province_id', $user->province_id ?? null)),
            'ward'     => $this->toInt($r->input('geo.ward_id',     $user->ward_id ?? null)),
            'village'  => $this->toInt($r->input('geo.village_id',  $user->village_id ?? null)),
        ];

        if (!$isAdmin) {
            $usrGeo = $this->geoFromUser($user); // <-- dùng thẳng 3 field của user
            if (!$usrGeo['province'] && !$usrGeo['ward'] && !$usrGeo['village']) {
                throw new HttpException(403, $opts['no_geo_message']);
            }

            // Ép theo cấp phân quyền hẹp nhất của user
            if ($usrGeo['village']) {
                $geo['province'] = $usrGeo['province'] ?: $geo['province'];
                $geo['ward']     = $usrGeo['ward']     ?: $geo['ward'];
                $geo['village']  = $usrGeo['village'];
            } elseif ($usrGeo['ward']) {
                $geo['province'] = $usrGeo['province'] ?: $geo['province'];
                $geo['ward']     = $usrGeo['ward'];
                // village: để nguyên nếu gửi lên; hoặc comment dòng dưới để luôn xoá
                // $geo['village'] = null;
            } else { // chỉ có province
                $geo['province'] = $usrGeo['province'];
                // $geo['ward'] = $geo['village'] = null; // nếu muốn khóa đúng cấp tỉnh
            }
        }

        if ($scope && ($isAdmin ? !$opts['admin_bypass_lock'] : true)) {
            $locked = $this->locks->isLockedFor(
                $scope,
                $geo['province'],
                $geo['ward'],
                $geo['village'],
            );
            if ($locked) throw new HttpException(423, $opts['locked_message']." (scope: {$scope})");
        }
        
        return $geo;
    }

     public function geoFromUser($user): array
    {
        return [
            'province' => $this->toInt($user?->province_id),
            'ward'     => $this->toInt($user?->ward_id),
            'village'  => $this->toInt($user?->village_id),
        ];
    }
     public function geoFromRecord(Model|array $record, array $keys): array
    {
        $get = fn($k) => is_array($record) ? ($record[$k] ?? null) : ($record->{$k} ?? null);

        $p = $get($keys['province']) ?? $get($keys['province_fallback']);
        $w = $get($keys['ward'])     ?? $get($keys['ward_fallback']);
        $v = $get($keys['village'])  ?? $get($keys['village_fallback']);

        return [
            'province' => $this->toInt($p),
            'ward'     => $this->toInt($w),
            'village'  => $this->toInt($v),
        ];
    }


}
