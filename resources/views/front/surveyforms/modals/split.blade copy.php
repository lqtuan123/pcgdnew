{{-- expects: $survey, $members (array) --}}
<div x-data="splitHousehold({
  academicYear: '{{ $survey->academic_year }}',
  members: @js($members),
  checkUrl: '{{ route('front.surveyforms.ajax.check_so_phieu') }}',
  postUrl:  '{{ route('front.surveyforms.split.store', $survey->id) }}',
  relations: @js($relationships->pluck('name'))   {{-- ["Chủ hộ","Vợ/Chồng","Con",...] --}}
})">


  <div class="text-sm text-slate-600">
    <div><b>Phiếu gốc:</b> {{ $survey->so_phieu }} (Năm: {{ $survey->academic_year }})</div>
    <div>Chọn số hộ mới, gán chủ hộ và thành viên cho từng hộ; nhập số phiếu mới.</div>
  </div>

  {{-- Chọn số hộ muốn tách --}}
  <div class="flex items-center gap-2">
    <label class="text-sm">Số hộ mới:</label>
    <input type="number" min="1" max="10" class="rounded-lg border px-3 py-2 w-24" x-model.number="n">
    <button type="button" class="rounded-lg border px-3 py-2" @click="applyN()">Áp dụng</button>
  </div>

  {{-- Danh sách thành viên hiện tại để gán --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Thành viên phiếu gốc (không bắt buộc chuyển hết)</div>
    <div class="max-h-56 overflow-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-2 py-1 text-left">Họ tên</th>
            <th class="px-2 py-1">Giới tính</th>
            <th class="px-2 py-1">NS</th>
            <th class="px-2 py-1">Quan hệ hiện tại</th>
            <th class="px-2 py-1">Gán vào</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(m, index) in members" :key="'member-' + index + '-' + (m.person_id || 'unknown')">
            <tr class="border-b">
              <td class="px-2 py-1" x-text="m.name || '—'"></td>
              <td class="px-2 py-1 text-center" x-text="m.gender || '—'"></td>
              <td class="px-2 py-1 text-center" x-text="m.dob || '—'"></td>
              <td class="px-2 py-1 text-center" x-text="m.role || '—'"></td>
              <td class="px-2 py-1">
                <select class="rounded border px-2 py-1"
                        x-model="assign[m.person_id]"
                        @change="onAssignChange(m.person_id)">
                  <option value="">— Ở lại hộ cũ —</option>
                  <template x-for="(f, idx) in forms" :key="'form-option-' + idx">
                    <option :value="idx">Hộ mới #<span x-text="idx+1"></span></option>
                  </template>
                </select>

                <!-- Chọn quan hệ sau khi gán -->
                <template x-if="assign[m.person_id] !== '' && assign[m.person_id] !== undefined">
                  <select class="rounded border px-2 py-1 ml-2"
                          x-model="roles[assign[m.person_id]][m.person_id]">
                    <template x-for="(r, relationIndex) in relations" :key="'relation-' + relationIndex + '-' + (r || 'unknown')">
                      <option :value="r" x-text="r"></option>
                    </template>
                  </select>
                </template>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Cấu hình từng hộ mới --}}
  <template x-for="(f,idx) in forms" :key="'f'+idx">
    <div class="rounded-xl border p-3">
      <div class="flex items-center justify-between">
        <div class="font-medium">Hộ mới #<span x-text="idx+1"></span></div>
        <div class="text-sm" :class="(dupCodes[f.so_phieu]||false)?'text-rose-600':'text-emerald-600'">
          <template x-if="f.so_phieu">
            <span x-text="(dupCodes[f.so_phieu] ? 'Số phiếu đã tồn tại' : 'Số phiếu hợp lệ')"></span>
          </template>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-3 mt-2">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Số phiếu mới <span class="text-red-500">*</span></label>
                <input class="w-full rounded-lg border px-3 py-2"
                    x-model.lazy="forms[idx].so_phieu"
                    @change="debouncedCheck()"
                    placeholder="VD: AC001-N1">
            </div>
            <div class="md:col-span-2">
                <select class="rounded border px-2 py-1 w-full"
                        x-model.number="forms[idx].head_id"
                        @change="onHeadChange(idx)">
                  <option value="">— Chọn chủ hộ —</option>
                  <template x-for="(m, memberIndex) in membersIn(idx)" :key="'head-option-' + idx + '-' + memberIndex + '-' + (m.person_id || 'unknown')">
                    <option :value="m.person_id" x-text="m.full_name || m.name || '—'"></option>
                  </template>
                </select>

                <!-- Danh sách thành viên + chọn role -->
                <div class="mt-2 space-y-2">
                  <template x-for="(m, memberIndex) in membersIn(idx)" :key="'member-role-' + idx + '-' + memberIndex + '-' + (m.person_id || 'unknown')">
                    <div class="flex items-center gap-2">
                      <div class="min-w-[180px]" x-text="m.name || '—'"></div>
                      <select class="rounded border px-2 py-1"
                              x-model="roles[idx][m.person_id]"
                              :disabled="m.person_id === Number(forms[idx].head_id)">
                        <template x-for="(r, relationIndex) in relations" :key="'relation-' + relationIndex + '-' + (r || 'unknown')">
                          <option :value="r" x-text="r"></option>
                        </template>
                      </select>
                    </div>
                  </template>
                </div>

            </div>
      </div>
 <div class="mt-3">
    <div class="flex items-center gap-4 mb-2">
      <div class="text-sm font-medium">Địa chỉ hộ mới</div>
      <!-- Nếu muốn cho phép chọn sơ đồ (old/new), bật 2 radio dưới: -->
      <!--
      <label class="inline-flex items-center gap-1 text-sm">
        <input type="radio" class="accent-cyan-600" value="new" x-model="addrScheme"> Sơ đồ mới
      </label>
      <label class="inline-flex items-center gap-1 text-sm">
        <input type="radio" class="accent-cyan-600" value="old" x-model="addrScheme"> Sơ đồ cũ
      </label>
      -->
    </div>

    <div class="grid md:grid-cols-3 gap-2">
    
      <!-- Province -->
      <div>
        <label class="block text-xs text-slate-500 mb-1">Tỉnh/TP (mới) <span class="text-red-500">*</span></label>
        <select class="rounded border px-2 py-1 w-full"
                x-model.number="forms[idx].geo.province_id"
                @change="onProvinceChange(idx)">
          <option value="">— Chọn tỉnh —</option>
          <template x-for="(p,i) in geoCache.provinces" :key="`prov-${i}`">
            <option :value="Number(p.id)" x-text="p.name"></option>
          </template>
        </select>
      </div>

      <!-- Ward (commune/ward) -->
      <div>
        <label class="block text-xs text-slate-500 mb-1">Xã/Phường (mới) <span class="text-red-500">*</span></label>
        <select class="rounded border px-2 py-1 w-full"
                x-model.number="forms[idx].geo.ward_id"
                @change="onWardChange(idx)">
          <option value="">— Chọn xã/phường —</option>
          <template x-for="(w,i) in wardsOf(idx)" :key="`ward-${forms[idx].geo.province_id}-${i}`">
            <option :value="Number(w.id)" x-text="w.name"></option>
          </template>
        </select>
      </div>

      <!-- Village (hamlet) -->
      <div>
        <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (mới)</label>
        <select class="rounded border px-2 py-1 w-full"
                x-model.number="forms[idx].geo.village_id">
          <option value="">— (không chọn) —</option>
          <template x-for="(v,i) in villagesOf(idx)" :key="`vill-${forms[idx].geo.ward_id}-${i}`">
            <option :value="Number(v.id)" x-text="v.name"></option>
          </template>
        </select>
      </div>
    </div>


    <div class="text-xs text-slate-500 mt-1">
      * Bắt buộc tối thiểu chọn Tỉnh/TP và Xã/Phường (Thôn/Ấp tuỳ chọn).
    </div>
  </div>
            <div class="mt-2">
            <div class="text-sm text-slate-600 mb-1">Thành viên & quan hệ trong Hộ mới #<span x-text="idx+1"></span>:</div>

            <table class="w-full text-sm border">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-2 py-1 text-left">Thành viên</th>
                    <th class="px-2 py-1 text-left">Quan hệ với chủ hộ</th>
                </tr>
                </thead>
                <tbody>
                <template x-for="(m, memberIndex) in membersIn(idx)" :key="'member-table-' + idx + '-' + memberIndex + '-' + (m.person_id || 'unknown')">
                    <tr class="border-t">
                    <td class="px-2 py-1" x-text="m.name || '—'"></td>
                    <td class="px-2 py-1">
                        <template x-if="Number(m.person_id) === Number(forms[idx].head_id)">
                        <span class="text-slate-500">Chủ hộ</span>
                        </template>
                        <template x-if="Number(m.person_id) !== Number(forms[idx].head_id)">
                        <select class="rounded border px-2 py-1"
                                x-model="roles[idx][m.person_id]">
                            <option value="">— Chọn quan hệ —</option>
                            <template x-for="(r, relationIndex) in relations" :key="'relation-table-' + relationIndex + '-' + (r.id || r)">
                            <option :value="r.name || r" x-text="r.name || r"></option>
                            </template>
                        </select>
                        </template>
                    </td>
                    </tr>
                </template>
                </tbody>
            </table>
            </div>

      <div class="mt-2">
        <div class="text-sm text-slate-600 mb-1">Thành viên gán vào Hộ mới #<span x-text="idx+1"></span>:</div>
        <div class="text-sm" x-text="membersIn(idx).map(x=>x.name).join(', ') || '—'"></div>
      </div>
    </div>
  </template>

  {{-- Footer --}}
  <div class="mt-2 flex items-center justify-end gap-2">
    <button type="button" class="rounded-lg border px-3 py-2 hover:bg-slate-50" data-close-modal>Đóng</button>
    <button type="button"
            class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700 disabled:opacity-50"
            :disabled="!canSubmit"
            @click="submit()">
      Tách hộ & Lưu
    </button>
  </div>
</div>


