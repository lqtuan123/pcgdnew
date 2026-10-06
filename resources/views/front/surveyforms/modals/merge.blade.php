{{-- Modal: Ghép hộ --}}
<div id="merge-household-modal" 
     class="grid gap-4"
     data-config='{
       "surveyId": {{ $survey->id }},
       "academicYear": "{{ $academicYear }}",
       "searchUrl": "{{ route('front.surveyforms.merge.search', $survey->id) }}",
       "membersUrlTmpl": "{{ str_replace('/0', '/OTHER_ID', route('front.surveyforms.merge.members', [$survey->id, 0])) }}",
       "postUrl": "{{ route('front.surveyforms.merge.store', $survey->id) }}",
       "relations": {{ json_encode($relationships->map->only(['id','name'])) }},
       "defaultAddress": {
         "new_province_id": {{ (int)$survey->new_province_id }},
         "new_ward_id": {{ (int)$survey->new_ward_id }},
         "village_id": {{ (int)($survey->village_id ?? 0) }},
         "addressline": {{ json_encode($survey->addressline ?? '') }}
       },
       "currentMembers": {{ json_encode($members) }}
     }'>

  <div class="text-sm text-slate-600 mb-3">
    <div><b>Phiếu gốc:</b> {{ $survey->so_phieu }} (Năm: {{ $survey->academic_year }})</div>
    <div><b>Năm học ghép hộ:</b> {{ $academicYear }}</div>
  </div>

  <div>
    <label class="text-sm">Chọn phiếu để ghép (tìm theo số phiếu/ tên chủ):</label>
    <div class="mt-1 flex gap-2">
     <input type="text"
       id="merge-search-input"
       class="rounded-lg border px-3 py-2 w-64"
       placeholder="Nhập từ khóa…">
      <select id="merge-survey-select" class="rounded-lg border px-3 py-2">
        <option value="">— Chọn phiếu ↵</option>
      </select>
      <div id="merge-survey-info" class="text-sm text-slate-600 mt-2" style="display: none;">
        <div><b>Số phiếu:</b> <span id="merge-so-phieu">—</span></div>
        <div><b>Chủ hộ:</b> <span id="merge-chu-ho">—</span></div>
        <div><b>Số thành viên:</b> <span id="merge-so-thanh-vien">0</span></div>
      </div>
    </div>
  </div>

  <div id="merge-members-section" class="rounded-xl border p-3" style="display: none;">
    <div class="font-medium mb-2">Thiết lập Chủ hộ & Quan hệ (Tất cả thành viên sẽ được dọn vào hộ ban đầu)</div>

    <div class="grid gap-3 md:grid-cols-2">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Chủ hộ mới <span class="text-red-500">*</span></label>
        <select id="merge-new-head-select" class="rounded border px-2 py-1 w-full">
          <option value="">— Chọn chủ hộ —</option>
        </select>
      </div>
      <div class="text-xs text-slate-500 flex items-end">* "Chủ hộ" tự gán, còn lại chọn quan hệ.</div>
    </div>

    <table class="w-full text-sm border mt-3">
      <thead class="bg-slate-50">
        <tr><th class="px-2 py-1 text-left">Thành viên</th><th class="px-2 py-1">Quan hệ</th></tr>
      </thead>
      <tbody id="merge-members-table">
      </tbody>
    </table>
  </div>


  <div class="mt-2 flex items-center justify-end gap-2">
    <button type="button" class="rounded-lg border px-3 py-2 hover:bg-slate-50" data-close-modal>Đóng</button>
    <button type="button" id="merge-submit-btn" class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700 disabled:opacity-50" disabled>Ghép hộ & Lưu</button>
  </div>
</div>
