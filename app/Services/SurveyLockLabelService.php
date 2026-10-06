<?php
// app/Services/SurveyLockLabelService.php

namespace App\Services;

use App\Models\SurveyLock;

class SurveyLockLabelService
{
    public function __construct(private GeoService $geo) {}

    /**
     * Trả về chuỗi nhãn hiển thị cho một (geo_level, geo_id).
     * - GLOBAL     => “Toàn hệ thống"
     * - PROVINCE   => "Tỉnh: {province}"
     * - WARD       => "Tỉnh: {province} / Xã: {ward}"  (nếu không suy ra được tỉnh thì chỉ hiện xã)
     * - VILLAGE    => "Tỉnh: {province} / Xã: {ward} / Thôn: {village}"
     */
    public function label(string $level, ?int $id): string
    {
        if ($level === 'GLOBAL' || !$id) return 'Toàn hệ thống';

        [$p, $w, $v] = $this->explodeNames($level, $id);

        return collect([
            $p ? "Tỉnh: {$p}" : null,
            $w ? "Xã: {$w}"   : null,
            $v ? "Thôn: {$v}" : null,
        ])->filter()->implode(' / ');
    }

    /**
     * Trả về mảng [provinceName, wardName, villageName] tối đa có thể suy ra.
     * Chỉ sử dụng GeoService::geoNameFromId(...) và các hàm parent (nếu có).
     * Nếu GeoService KHÔNG có các hàm parent-id thì vẫn hoạt động nhưng sẽ chỉ điền được tên đúng cấp.
     */
    public function explodeNames(string $level, ?int $id): array
    {
        $p = $w = $v = null;
        if (!$id) return [null,null,null];

        switch ($level) {
            case 'PROVINCE':
                $p = $this->name('PROVINCE', $id);
                break;

            case 'WARD':
                $w = $this->name('WARD', $id);
                $pid = $this->provinceIdOfWard($id);       // cố gắng suy luận
                if ($pid) $p = $this->name('PROVINCE', $pid);
                break;

            case 'VILLAGE':
                $v = $this->name('VILLAGE', $id);
                $wid = $this->wardIdOfVillage($id);
                if ($wid) {
                    $w = $this->name('WARD', $wid);
                    $pid = $this->provinceIdOfWard($wid);
                    if ($pid) $p = $this->name('PROVINCE', $pid);
                }
                break;
        }
        return [$p,$w,$v];
    }

    /** Gắn nhãn trực tiếp lên model tiện cho view */
    public function decorate(SurveyLock $lock): SurveyLock
    {
        [$p,$w,$v] = $this->explodeNames($lock->geo_level, $lock->geo_id);
        $lock->province_name = $p;
        $lock->ward_name     = $w;
        $lock->village_name  = $v;
        $lock->geo_label     = $this->label($lock->geo_level, $lock->geo_id);
        return $lock;
    }

    /* ---------------- helpers ---------------- */

    private function name(string $level, int $id): ?string
    {
        // Giả định bạn có: geoNameFromId(string $level, int $id): ?string
        // (Nếu hàm của bạn chỉ nhận $id, hãy sửa lại cho khớp.)
        return $this->geo->geoNameFromId ($id);
    }

    private function provinceIdOfWard(int $wardId): ?int
    {
        // Nếu GeoService có hàm này thì dùng, không có thì bỏ qua.
        return method_exists($this->geo, 'parentIdOf')
            ? $this->geo->parentIdOf($wardId)
            : null;
    }

    private function wardIdOfVillage(int $villageId): ?int
    {
        return method_exists($this->geo, 'wardIdOfVillage')
            ? $this->geo->wardIdOfVillage($villageId)
            : null;
    }
}
