<?php
// app/Services/People/PersonResidenceService.php

namespace App\Services\People;

use App\Models\Person;
use App\Models\Residence;
use App\Models\ResidenceType;
use App\Models\ResidenceStatus;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Services\Households\HouseRelationshipService;

class PersonResidenceService
{
    public function __construct(protected HouseRelationshipService $rels) {}

    public function resolveType(?string $name): ?int
    {
        $name = trim((string)$name);
        if ($name === '') return null;
        return (int) ResidenceType::firstOrCreate(['name' => $name])->id;
    }

     public function resolveStatus(?string $name): ?int
    {
        $name = trim((string)$name);
        if ($name === '') return null;
        return (int) ResidenceStatus::firstOrCreate(['name' => $name])->id;
    }

     public function resolveRelationship(?string $name): ?int
    {
        $name = trim((string)$name);
        if ($name === '') return null;
        return (int) $this->rels->getOrCreate($name)->id;
    }

    /**
     * Upsert Residence cho 1 người từ dòng import.
     *
     * @param Person $person
     * @param array  $row  (có các key: residence_type_name, residence_status_name, relationship_name, ...)
     * @param bool   $isHead  true nếu dòng này là chủ hộ
     * @param int|null $headPersonId  nếu đã biết id chủ hộ thì set luôn (ưu tiên hơn $isHead)
     */
    public function upsertFromRow(Person $person, array $row, bool $isHead = false, ?int $headPersonId = null): ?Residence
    {
        $typeId   = $this->resolveType($row['residence_type_name'] ?? null);   // ví dụ: "Tạm trú"
        $statusId = $this->resolveStatus($row['residence_status_name'] ?? null); // ví dụ: "Chuyển đi"
        $relId    = $this->resolveRelationship($row['relationship_name'] ?? null);

        // Nếu không có gì để lưu thì bỏ qua
        if (!$typeId && !$statusId && !$relId && !$isHead && !$headPersonId) {
            return null;
        }

        $res = Residence::firstOrNew(['person_id' => $person->id]);

        // chỉ set khi có dữ liệu, tránh overwrite về null
        if ($typeId)   $res->residence_type_id   = $typeId;
        if ($statusId) $res->residence_status_id = $statusId;
        if ($relId)    $res->house_relationship_id = $relId;

        if ($headPersonId) {
            $res->head_person_id = $headPersonId;
        } elseif ($isHead) {
            $res->head_person_id = $person->id;
        }

        $res->save();

        return $res;
    }

    /**
     * Sau khi xác định được chủ hộ của một Household,
     * cập nhật head_person_id cho tất cả residence của các thành viên trong hộ đó.
     */
    public function setHeadForHouseholdMembers(Household $hh, int $headPersonId): int
    {
        $personIds = HouseholdMember::where('household_id', $hh->id)->pluck('person_id');
        if ($personIds->isEmpty()) return 0;

        return Residence::whereIn('person_id', $personIds)->update(['head_person_id' => $headPersonId]);
    }
}
