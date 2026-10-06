<?php
// app/Services/People/PersonSpecialCircumstanceService.php

namespace App\Services\People;

use App\Models\Person;
use App\Models\SpecialCircumstance;
use App\Models\PersonSpecialCircumstance;

class PersonSpecialCircumstanceService
{
    /**
     * Từ 1 dòng excel => upsert bản ghi pivot person_special_circumstances
     * - sc_name:  cột “Hoàn cảnh đặc biệt”
     * - sc_detail: cột “Chi tiết hoàn cảnh đặc biệt”
     */
    public function upsertFromRow(Person $person, array $row): ?PersonSpecialCircumstance
    {
        $name   = trim((string)($row['sc_name'] ?? ''));
        $detail = trim((string)($row['sc_detail'] ?? ''));

        if ($name === '' && $detail === '') {
            return null; // không có gì để lưu
        }

        // Danh mục SC: tạo nếu chưa có
        $sc = SpecialCircumstance::firstOrCreate(['name' => $name]);

        // Pivot (duy nhất theo person + sc)
        $pivot = PersonSpecialCircumstance::firstOrNew([
            'person_id' => $person->id,
            'sc_id'     => $sc->id,
        ]);

        if ($pivot->exists && (string)$pivot->chi_tiet === $detail) {
            return $pivot; // không đổi
        }

        $pivot->chi_tiet = $detail ?: null;
        $pivot->save();

        return $pivot;
    }
}
