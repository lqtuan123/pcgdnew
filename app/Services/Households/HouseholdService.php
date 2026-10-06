<?php
// app/Services/Households/HouseholdService.php

namespace App\Services\Households;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Person;
use App\Services\GeoService;
use App\Services\People\PersonService;
use App\Services\People\PersonResidenceService;
use Illuminate\Support\Facades\Log;
class HouseholdService
{
    public function __construct(
        protected GeoService $geoService,
        protected PersonService $people, // để có thể tạo/upssert Person làm chủ hộ
        protected PersonResidenceService $residenceService,
    ) {}

    /**
     * Tìm hộ theo ticket_no + địa bàn (tùy theo scheme).
     */
    public function findByGeoAndTicketNo(string $scheme, array $geo, ?string $ticketNo): ?Household
    {
        if (blank($ticketNo)) return null;

        $q = Household::query()->where('ticket_no', trim($ticketNo));

        // if ($scheme === 'new') {
        //     $q->where('new_province_id', $geo['province_id'] ??null)->where('new_ward_id', $geo['ward_id'] ?? null)->where('village_id', $geo['village_id'] ?? null);
        // } else { // old
        //     $q-> $q->where('old_province_id', $geo['province_id'] ??null)->where('old_district_id', $geo['district_id'] ?? null)
        //       ->where('old_ward_id',     $geo['ward_id'] ?? null) ->where('village_id',$geo['village_id'] ?? null);
        // }

        return $q->first();
    }

    /**
     * Tạo mới Household theo GEO.
     */
    public function updateForGeo(
        Household $household,
        string $scheme,
        array $geo = [],
        array $attrs = [],
        bool $overwriteNulls = false
    ): Household
    {
        $u = [];

        // Các thuộc tính cơ bản
        foreach (['ticket_no','addressline','phone','name','is_deleted'] as $k) {
            if (array_key_exists($k, $attrs) || $overwriteNulls) {
                $u[$k] = $attrs[$k] ?? null;
            }
        }

        // Geo theo scheme
        if ($scheme === 'new') {
            // new_* lấy trực tiếp từ $geo
            if (array_key_exists('ward_id', $geo) || $overwriteNulls) {
                $u['new_ward_id'] = $geo['ward_id'] ?? null;
            }
            if (array_key_exists('province_id', $geo) || $overwriteNulls) {
                $u['new_province_id'] = $geo['province_id'] ?? null;
            }

            // Khi scheme=new: chỉ xoá old_* nếu muốn "replace"
            if ($overwriteNulls) {
                $u['old_province_id'] = null;
                $u['old_district_id'] = null;
                $u['old_ward_id']     = null;
                 $u['village_id']     = null;
            }
        } else { // scheme === 'old'
            // old_* lấy trực tiếp; đồng thời map sang new_*
            if (array_key_exists('ward_id', $geo) || $overwriteNulls) {
                $u['old_ward_id'] = $geo['ward_id'] ?? null;
                $u['new_ward_id'] = !empty($geo['ward_id'])
                    ? $this->geoService->mapOldIdToNewId((int)$geo['ward_id'], 3)
                    : null;
            }
            if (array_key_exists('district_id', $geo) || $overwriteNulls) {
                $u['old_district_id'] = $geo['district_id'] ?? null;
            }
            if (array_key_exists('province_id', $geo) || $overwriteNulls) {
                $u['old_province_id'] = $geo['province_id'] ?? null;
                $u['new_province_id'] = !empty($geo['province_id'])
                    ? $this->geoService->mapOldIdToNewId((int)$geo['province_id'], 1)
                    : null;
            }
        }
        // Log::info(' $village_id',  $geo['village_id']);
        // Thôn/buôn/ấp (village)
        if (array_key_exists('village_id', $geo) || $overwriteNulls) {
            $u['village_id'] = $geo['village_id'] ?? null;
        }
        // Log::info('$u[village_id]', $u['village_id']);
        // Không làm gì nếu không có thay đổi
        if (empty($u)) {
            return $household;
        }
        // Log::info($u);
        $household->fill($u)->save();

        return $household->refresh();
    }
    public function createForGeo(string $scheme, array $geo, array $attrs = []): Household
    {
        // map sang id "mới" khi scheme=old (có thể null -> giữ null)
        $newWardId      = $scheme === 'new'
            ? ($geo['ward_id'] ?? null)
            : (!empty($geo['ward_id']) ? $this->geoService->mapOldIdToNewId((int)$geo['ward_id'], 3) : null);

        $newProvinceId  = $scheme === 'new'
            ? ($geo['province_id'] ?? null)
            : (!empty($geo['province_id']) ? $this->geoService->mapOldIdToNewId((int)$geo['province_id'], 1) : null);

        $base = [
            'ticket_no'       => $attrs['ticket_no'] ?? null,
            'addressline'     => $attrs['addressline'] ?? null,
            'phone'           => $attrs['phone'] ?? null,
            'name'            => $attrs['name'] ?? null,
            'is_deleted'      => false,

            'new_ward_id'     => $newWardId,
            'new_province_id' => $newProvinceId,

            'old_province_id' => $scheme === 'old' ? ($geo['province_id'] ?? null) : null,
            'old_district_id' => $scheme === 'old' ? ($geo['district_id'] ?? null) : null,
            'old_ward_id'     => $scheme === 'old' ? ($geo['ward_id'] ?? null) : null,

            'village_id'      => $geo['village_id'] ?? null,
        ];

        return Household::create(array_merge($attrs, $base));
    }

    /**
     * Upsert hộ theo ticket_no + địa bàn.
     * Trả về [Household $hh, bool $isNew].
     */
    public function upsertByGeoAndTicketNo(
        string $scheme,
        array $geo,
        ?string $ticketNo = null,
        array $attrs = [] // có thể truyền name/phone/addressline
    ): array {
        $existing = $this->findByGeoAndTicketNo($scheme, $geo, $ticketNo);
        // Log::info('upsertByGeoAndTicketNo');
        $residence_status_id = $this->residenceService->resolveStatus($attrs['residence_status_name'] ?? null);
        $residence_type_id = $this->residenceService->resolveType($attrs['residence_type_name'] ?? null);

        if ($existing) {
            // chỉ bổ sung nếu đang trống
            // Log::info('có household có sẳn');
             $hh = $this->updateForGeo($existing,$scheme, $geo, [
            'ticket_no'   => $ticketNo,
            'name'        => $attrs['name']        ?? null,
            'addressline' => $attrs['addressline'] ?? null,
            'phone'       => $attrs['phone']       ?? null,
            'residence_type_id' => ($residence_type_id ?? null),
            'residence_status_id' => ($residence_status_id ?? null),

        ]);
            // Log::info('có household có sẳn');

            return [$existing->fresh(), false];
        }

        $hh = $this->createForGeo($scheme, $geo, [
            'ticket_no'   => $ticketNo,
            'name'        => $attrs['name']        ?? null,
            'addressline' => $attrs['addressline'] ?? null,
            'phone'       => $attrs['phone']       ?? null,
            'residence_type_id' => ($residence_type_id ?? null),
            'residence_status_id' => ($residence_status_id ?? null),

        ]);
        // Log::info('tạo mới createforgeo ');
        return [$hh, true];
    }

    /**
     * Upsert hộ + (tuỳ chọn) tạo/upssert luôn Chủ hộ từ $headRow và set vào hộ.
     * - $headRow là đúng "row" import (keys như ho_dem, ten, dob_day...) để PersonService xử lý.
     * Trả về [Household $hh, bool $isNewHousehold, ?Person $head].
     */
   public function upsertWithHead(
    string $scheme,
    array $geo,
    ?string $ticketNo,
    array $attrs = []    // name/phone/addressline + chuho_ho_dem, chuho_ten (nếu có)
    ): array {
        return \DB::transaction(function () use ($scheme, $geo, $ticketNo, $attrs) {
            // 1) Upsert Household theo ticket + geo
            [$hh, $isNew] = $this->upsertByGeoAndTicketNo($scheme, $geo, $ticketNo, $attrs);

            // 2) Lấy họ đệm, tên chủ hộ từ attrs (ưu tiên chuho_ho_dem/chuho_ten; fallback tách từ name)
            $hoDem = trim((string)($attrs['chuho_ho_dem'] ?? ''));
            $ten   = trim((string)($attrs['chuho_ten'] ?? ''));

            if ($hoDem === '' && $ten === '' && !empty($attrs['name'])) {
                [$hoDem, $ten] = $this->splitFullName((string)$attrs['name']);
            }

            // Nếu vẫn không có thông tin tên => không tạo chủ hộ
            if ($hoDem === '' && $ten === '') {
                return [$hh->fresh(), $isNew, null];
            }

            // 3) Tìm person theo số phiếu + tên; nếu chưa có thì tạo mới
            $person = \App\Models\Person::query()
                ->where('so_phieu', $ticketNo)
                ->when($hoDem !== '', fn($q) => $q->where('ho_dem', $hoDem))
                ->when($ten   !== '', fn($q) => $q->where('ten',    $ten))
                ->first();
            $residence_status_id = $this->residenceService->resolveStatus($attrs['residence_status_name'] ?? null);
            $residence_type_id = $this->residenceService->resolveType($attrs['residence_type_name'] ?? null);
            $village_id = null;
            if (array_key_exists('village_id', $geo) || $overwriteNulls) {
                $village_id = $geo['village_id'] ?? null;
            }

            if (!$person) {
                $person = \App\Models\Person::create([
                    'ho_dem'        => $hoDem,
                    'ten'           => $ten,
                    'phone'         => $attrs['phone']       ?? null,
                    'addressline'   => $attrs['addressline'] ?? null,
                    'so_phieu'      => $ticketNo,
                    // map geo vào person
                    'old_province_id' => $scheme === 'old' ? ($geo['province_id'] ?? null) : null,
                    'old_district_id' => $scheme === 'old' ? ($geo['district_id'] ?? null) : null,
                    'old_ward_id'     => $scheme === 'old' ? ($geo['ward_id']     ?? null) : null,
                    'new_province_id' => $scheme === 'new' ? ($geo['province_id'] ?? ($hh->new_province_id ?? null)) : ($hh->new_province_id ?? null),
                    'new_ward_id'     => $scheme === 'new' ? ($geo['ward_id']     ?? ($hh->new_ward_id     ?? null)) : ($hh->new_ward_id     ?? null),
                    'village_id' =>  $village_id??null,
                    'is_female'       => (bool)($attrs['is_female'] ?? false),
                    'is_deleted'      => false,
                    'residence_type_id' => $residence_type_id,
                    'residence_status_id' => $residence_status_id,
                    'note' => $attrs['note'] ?? null,
                    'contact_name' => $attrs['contact_name'] ?? null,
                  
                ]);
            } else {
                // 3b) Cập nhật person (patch semantics)
                $patch = [];

                // Cập nhật tên nếu bạn truyền rõ (tránh đổi nếu trống)
                if ($hoDem !== '' && $person->ho_dem !== $hoDem) {
                    $patch['ho_dem'] = $hoDem;
                }
                if ($ten !== '' && $person->ten !== $ten) {
                    $patch['ten'] = $ten;
                }

                // Thông tin liên hệ
                if (array_key_exists('phone', $attrs))       $patch['phone']       = $attrs['phone'];
                if (array_key_exists('addressline', $attrs)) $patch['addressline'] = $attrs['addressline'];
                if (array_key_exists('is_female', $attrs))   $patch['is_female']   = (bool)$attrs['is_female'];
                if (array_key_exists('note', $attrs))        $patch['note']        = $attrs['note'];
                if (array_key_exists('contact_name', $attrs))$patch['contact_name']= $attrs['contact_name'];

                // so_phieu: điền nếu đang trống
                if ($ticketNo && empty($person->so_phieu)) {
                    $patch['so_phieu'] = $ticketNo;
                }

                // Danh mục cư trú (chỉ cập nhật khi bạn truyền vào _name)
                if (array_key_exists('residence_type_name', $attrs)) {
                    $patch['residence_type_id'] = $residence_type_id;
                }
                if (array_key_exists('residence_status_name', $attrs)) {
                    $patch['residence_status_id'] = $residence_status_id;
                } 
                 if (array_key_exists('village_id', $geo)) {
                    $patch['village_id'] = $geo['village_id'];
                }

                // Geo patch:
                if ($scheme === 'new') {
                    if (array_key_exists('province_id', $geo)) $patch['new_province_id'] = $geo['province_id'];
                    if (array_key_exists('ward_id', $geo))     $patch['new_ward_id']     = $geo['ward_id'];
                } else { // scheme === 'old'
                    if (array_key_exists('province_id', $geo)) $patch['old_province_id'] = $geo['province_id'];
                    if (array_key_exists('district_id', $geo)) $patch['old_district_id'] = $geo['district_id'];
                    if (array_key_exists('ward_id', $geo))     $patch['old_ward_id']     = $geo['ward_id'];
                    // Đồng bộ new_* theo household nếu person đang thiếu
                    if (is_null($person->new_province_id) && !is_null($hh->new_province_id)) {
                        $patch['new_province_id'] = $hh->new_province_id;
                    }
                    if (is_null($person->new_ward_id) && !is_null($hh->new_ward_id)) {
                        $patch['new_ward_id'] = $hh->new_ward_id;
                    }
                }
               
                if (!empty($patch)) {
                    $person->fill($patch)->save();
                }
            }


            // 4) Gán làm Chủ hộ + đảm bảo membership
            $this->setHead($hh, $person, $this->people);

            return [$hh->fresh(), $isNew, $person];
        });
    }

    /**
     * Helper: tách "Họ đệm Tên" -> [ho_dem, ten]
     */
    private function splitFullName(string $full): array
    {
        $full = trim(preg_replace('~\s+~', ' ', $full));
        if ($full === '') return ['', ''];
        $parts = explode(' ', $full);
        $ten   = array_pop($parts);
        $hoDem = implode(' ', $parts);
        return [$hoDem, $ten];
    }

    /**
     * Đặt Chủ hộ, đồng thời đảm bảo membership "Chủ hộ" cho person.
     */
    public function setHead(Household $hh, Person $head): void
    {
        \DB::transaction(function () use ($hh, $head) {
            if ($hh->head_person_id !== $head->id) {
                $hh->head_person_id = $head->id;
            }
            if (empty($hh->name)) {
                // ưu tiên tên từ PersonService (họ + tên)
                $hh->name = trim(($head->ho_dem ? $head->ho_dem.' ' : '').($head->ten ?? '')) ?: $hh->name;
            }
            $hh->save();

            // membership: đảm bảo person là "Chủ hộ" trong hộ này
            $m = HouseholdMember::firstOrNew([
                'person_id'    => $head->id,
            ]);
            $m->household_id = $hh->id;
            $m->role = 'Chủ hộ';
            $m->save();

            // hạ các Chủ hộ khác trong cùng hộ (nếu có) về "Thành viên"
            HouseholdMember::where('household_id', $hh->id)
                ->where('person_id', '<>', $head->id)
                ->where('role', 'Chủ hộ')
                ->update(['role' => 'Thành viên']);
        });
    }
}
