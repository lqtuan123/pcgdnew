<?php
// app/Services/Households/HouseholdMemberService.php

namespace App\Services\Households;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseRelationship;
use App\Models\Person;

class HouseholdMemberService
{
     protected array $rolePriority = [
        'Thành viên' => 0,
        'Con'        => 1,
        'Vợ/Chồng'   => 2,
        'Chủ hộ'     => 3,
    ];

    protected function roleRank(?string $role): int
    {
        return $this->rolePriority[$role ?: 'Thành viên'] ?? 0;
    }

    /**
     * Gắn person vào hộ:
     * - Nếu đã có membership theo person_id -> cập nhật household_id (chuyển hộ nếu khác)
     * - Cập nhật role nếu role mới "cao" hơn role hiện tại
     * - Không bao giờ tạo bản ghi trùng person_id (tránh lỗi unique)
     */
    public function attachOrUpdate(Household $household, Person $person, HouseRelationship $rel): HouseholdMember
    {
        $newRole = $rel->name ?: 'Thành viên';

        // Tìm membership hiện tại theo person_id (vì DB đang Unique(person_id))
        $m = HouseholdMember::where('person_id', $person->id)->first();

        if ($m) {
            $changed = false;

            // Nếu đang ở hộ khác -> chuyển sang hộ mới
            if ($m->household_id !== $household->id) {
                $m->household_id = $household->id;
                $changed = true;
            }

            // Nâng vai trò nếu role mới ưu tiên cao hơn
            if ($this->roleRank($newRole) > $this->roleRank($m->role)) {
                $m->role = $newRole;
                $changed = true;
            }

            if ($changed) $m->save();
            return $m;
        }

        // Chưa có membership -> tạo mới
        return HouseholdMember::create([
            'household_id' => $household->id,
            'person_id'    => $person->id,
            'role'         => $newRole,
        ]);
    }
}
