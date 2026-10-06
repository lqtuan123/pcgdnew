<?php
// app/Services/People/PersonLiteracyService.php

namespace App\Services\People;

use App\Models\Person;
use App\Models\PersonLiteracy;

class PersonLiteracyService
{
    private function toIntOrNull($v): ?int
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '') return null;
        return is_numeric($s) ? (int)$s : null;
    }

    /**
     * @param bool $allowClearing  true => nếu cả 3 giá trị rỗng thì xóa record cũ (nếu có)
     *                             false => bỏ qua (không tạo mới, không cập nhật)
     */
    public function upsertFromRow(Person $person, array $row, bool $allowClearing = false): ?PersonLiteracy
    {
        $data = [
            'dang_hoc_lop'   => $this->toIntOrNull($row['lit_dang_hoc_lop']   ?? $row['dang_hoc_lop']   ?? null),
            'hoan_thanh_lop' => $this->toIntOrNull($row['lit_hoan_thanh_lop'] ?? $row['hoan_thanh_lop'] ?? null),
            'tai_mu_chu_muc' => $this->toIntOrNull($row['lit_tai_mu_muc']     ?? $row['tai_mu_chu_muc'] ?? null),
        ];

        $hasAnyValue = array_filter($data, fn($v) => $v !== null);

        $existing = PersonLiteracy::where('person_id', $person->id)->first();

        // ❌ Không có dữ liệu ⇒ không tạo record rỗng
        if (!$hasAnyValue) {
            if ($allowClearing && $existing) {
                $existing->delete();
                return null;
            }
            return $existing; // hoặc return null; tuỳ bạn muốn trả gì
        }

        // ⤵ Có dữ liệu ⇒ tạo mới hoặc cập nhật khi khác
        if (!$existing) {
            return PersonLiteracy::create($data + ['person_id' => $person->id]);
        }

        $changed = false;
        foreach ($data as $k => $v) {
            if ($existing->{$k} !== $v) {
                $existing->{$k} = $v;
                $changed = true;
            }
        }
        if ($changed) $existing->save();

        return $existing;
    }
}
