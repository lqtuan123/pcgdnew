<?php
// app/Support/SurveyLocking.php
namespace App\Support;

use App\Models\SurveyLock;

class SurveyLocking
{
    public static function isLocked(?int $provinceId, ?int $districtId, ?int $wardId): bool
    {
        $levels = [
            ['geo_level' => 'WARD',     'geo_id' => $wardId],
            ['geo_level' => 'DISTRICT', 'geo_id' => $districtId],
            ['geo_level' => 'PROVINCE', 'geo_id' => $provinceId],
            ['geo_level' => 'GLOBAL',   'geo_id' => null],
        ];

        foreach ($levels as $lv) {
            if ($lv['geo_level'] !== 'GLOBAL' && empty($lv['geo_id'])) continue;

            $q = SurveyLock::where('geo_level', $lv['geo_level'])
                ->where('is_active', true);

            $lv['geo_level'] === 'GLOBAL'
                ? $q->whereNull('geo_id')
                : $q->where('geo_id', $lv['geo_id']);

            if ($q->exists()) return true;
        }
        return false;
    }
}
