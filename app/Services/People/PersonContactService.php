<?php
// app/Services/People/PersonContactService.php

namespace App\Services\People;

use App\Models\Person;
use App\Models\PersonContact;
use App\Services\Households\HouseRelationshipService;

class PersonContactService
{
    /**
     * Từ 1 dòng excel => upsert người liên lạc:
     * - contact_name  : Họ tên Cha hoặc Mẹ
     * - contact_phone : Điện thoại
     * - contact_note  : Ghi chú
     *
     * Mặc định gán quan hệ "Cha/Mẹ" (tạo nếu chưa có).
     */
    public function upsertFromRow(
        Person $person,
        array $row,
        HouseRelationshipService $rels
    ): ?PersonContact {
        $name  = trim((string)($row['contact_name']  ?? ''));
        $phone = trim((string)($row['contact_phone'] ?? ''));
        $note  = trim((string)($row['contact_note']  ?? ''));

        if ($name === '' && $phone === '' && $note === '') {
            return null; // không có gì để lưu
        }

        // Mặc định coi đây là "Cha/Mẹ"
        $rel = $rels->getOrCreate('Cha/Mẹ');

        // Xác định record cũ (tránh trùng): ưu tiên match theo person + name (+ phone nếu có)
        $q = PersonContact::where('person_id', $person->id)->where('hoten', $name);
        if ($phone !== '') $q->where('dienthoai', $phone);
        $contact = $q->first();

        if (!$contact) {
            $contact = new PersonContact([
                'person_id' => $person->id,
                'hoten'     => $name ?: null,
            ]);
        }

        // Chỉ update khi có thay đổi
        $dirty = false;
        foreach ([
            'house_relationship_id' => $rel->id,
            'dienthoai'             => ($phone ?: null),
            'note'                  => ($note  ?: null),
        ] as $k => $v) {
            if ($contact->{$k} !== $v) { $contact->{$k} = $v; $dirty = true; }
        }

        if ($dirty || !$contact->exists) $contact->save();

        return $contact;
    }
}
