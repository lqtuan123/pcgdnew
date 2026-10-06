{{-- expects: $survey, $members (array), $academicYear --}}
<div x-data="splitHousehold({
  academicYear: '{{ $academicYear }}',
  members: @js($members),                             // [{person_id, name, ...}]
  relations: @js($relationships->map->only(['id','name'])),  // [{id,name}]
  checkUrl: '{{ route('front.surveyforms.ajax.check_so_phieu') }}',
  postUrl:  '{{ route('front.surveyforms.split.store', $survey->id) }}'
})" class="grid gap-4">

  <div class="text-sm text-slate-600">
    <div><b>Phiếu gốc:</b> {{ $survey->so_phieu }} (Năm: {{ $survey->academic_year }})</div>
    <div><b>Năm học tách hộ:</b> {{ $academicYear }}</div>
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
                    <option :value="idx" x-text="'Hộ mới #'+(idx+1)"></option>
                  </template>
                </select>
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
          <label class="block text-xs text-slate-500 mb-1">Chủ hộ <span class="text-red-500">*</span></label>
          <select class="rounded border px-2 py-1 w-full"
                  x-model.number="forms[idx].head_id"
                  @change="onHeadChange(idx)">
            <option value="">— Chọn chủ hộ —</option>
            <template x-for="(m, memberIndex) in membersIn(idx)" :key="'head-option-' + idx + '-' + memberIndex + '-' + (m.person_id || 'unknown')">
              <option :value="m.person_id" x-text="m.name || '—'"></option>
            </template>
          </select>
          <div class="text-xs text-slate-500 mt-1">* Chủ hộ phải là người đã gán vào Hộ mới #<span x-text="idx+1"></span>.</div>
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
            <template x-for="(m, memberIndex) in membersIn(idx)" :key="'member-row-' + idx + '-' + memberIndex + '-' + (m.person_id || 'unknown')">
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
                      <template x-for="(r, relationIndex) in relations" :key="'relation-' + relationIndex + '-' + (r.id || r)">
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

