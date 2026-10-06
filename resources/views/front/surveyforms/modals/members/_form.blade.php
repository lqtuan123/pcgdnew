{{-- expects: $survey, $action, $method = 'POST', $submitLabel = 'Lưu'
     options: $ethnicities,$religions,$resTypes,$resStats,$blocks,$gradLevels,$vocLevels,$grades,$disCats,$specials,$priorities
     data (khi edit): $person, $education, $literacy, $disability, $special, $membership, $initialSchool
--}}
@php
  $method      = $method ?? 'POST';
  $submitLabel = $submitLabel ?? 'Lưu';
@endphp

<style>[x-cloak]{display:none!important}.th{padding:.5rem .75rem;font-weight:600}</style>

@if ($errors->any())
  <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
    <div class="font-medium mb-1">Có lỗi khi lưu:</div>
    <ul class="list-disc ml-5">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
  </div>
@endif

{{-- Ngữ cảnh phiếu --}}
<div class="mb-3 grid gap-1 text-sm text-slate-600">
  <div><span class="font-medium text-slate-800">Số phiếu:</span> {{ $survey->so_phieu }}</div>
  <div><span class="font-medium text-slate-800">Năm học:</span> {{ \App\Models\SurveyForm::currentAcademicYear() }}</div>
</div>

<form data-ajax method="POST" action="{{ $action }}" class="grid gap-4">
  @csrf
  @if (strtoupper($method) !== 'POST') @method($method) @endif

  {{-- bắt buộc để writer xác định bối cảnh --}}
  <input type="hidden" name="survey_form_id" value="{{ $survey->id }}">
  <input type="hidden" name="scheme" value="new">
  <input type="hidden" name="geo[province_id]" value="{{ $survey->new_province_id }}">
  <input type="hidden" name="geo[ward_id]"     value="{{ $survey->new_ward_id }}">

  {{-- ========== NHÓM 1: THÔNG TIN ĐỐI TƯỢNG ========== --}}
  <div class="rounded-xl border p-4">
    <div class="th">Thông tin đối tượng</div>
    <div class="mt-3 grid gap-3 md:grid-cols-4">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Số căn cước</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="person[identity_number]"
               value="{{ old('person.identity_number', $person->identity_number ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Họ đệm</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="person[ho_dem]"
               value="{{ old('person.ho_dem', $person->ho_dem ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Tên <span class="text-red-500">*</span></label>
        <input class="w-full rounded-lg border px-3 py-2" required
               name="person[ten]"
               value="{{ old('person.ten', $person->ten ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Ngày sinh</label>
        <input type="number" min="1" max="31" class="w-full rounded-lg border px-3 py-2"
               name="person[dob_day]"
               value="{{ old('person.dob_day', $person->dob_day ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Tháng sinh</label>
        <input type="number" min="1" max="12" class="w-full rounded-lg border px-3 py-2"
               name="person[dob_month]"
               value="{{ old('person.dob_month', $person->dob_month ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Năm sinh</label>
        <input type="number" min="1900" max="{{ now()->year+1 }}" class="w-full rounded-lg border px-3 py-2"
               name="person[dob_year]"
               value="{{ old('person.dob_year', $person->dob_year ?? '') }}">
      </div>

      <label class="inline-flex items-center gap-2">
        <input type="hidden" name="person[is_female]" value="0">
        <input type="checkbox" class="accent-cyan-600" name="person[is_female]" value="1"
               @checked(old('person.is_female', (bool)($person->is_female ?? false)))>
        <span>Giới tính (Nữ)</span>
      </label>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Dân tộc</label>
        <select class="w-full rounded-lg border px-3 py-2" name="person[ethnicity_id]">
          <option value="">— Chọn dân tộc —</option>
          @foreach($ethnicities as $e)
            <option value="{{ $e->id }}"
              @selected(old('person.ethnicity_id', $person->ethnicity_id ?? null) == $e->id)>{{ $e->name }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Tôn giáo</label>
        <select class="w-full rounded-lg border px-3 py-2" name="person[religion_id]">
          <option value="">— Chọn tôn giáo —</option>
          @foreach($religions as $r)
            <option value="{{ $r->id }}"
              @selected(old('person.religion_id', $person->religion_id ?? null) == $r->id)>{{ $r->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- ✅ dùng đúng field của model: priority_category_id --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Diện ưu tiên</label>
        <select class="w-full rounded-lg border px-3 py-2" name="person[priority_category_id]">
          <option value="">— Chọn —</option>
          @foreach($priorities as $s)
            <option value="{{ $s->id }}"
              @selected(old('person.priority_category_id', $person->priority_category_id ?? null) == $s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- ========== NHÓM 2: THÔNG TIN NƠI ĐANG Ở ========== --}}
  <div class="rounded-xl border p-4">
    <div class="th">Thông tin nơi đang ở</div>
    <div class="mt-3 grid gap-3 md:grid-cols-6">
      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Quan hệ với chủ hộ</label>
          
        <select class="w-full rounded-lg border px-3 py-2" name="membership[role]">
          <option value="">— Chọn —</option>
          @foreach($relationships as $s)
            <option value="{{ $s->name }}"
              @selected(old('membership.role', $membership->role ?? '' ?? null) == $s->name)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
       

      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Địa chỉ</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="person[addressline]"
               value="{{ old('person.addressline', $person->addressline ?? '') }}">
      </div>
      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Diện cư trú</label>
        <select class="w-full rounded-lg border px-3 py-2" name="person[residence_type_id]">
          <option value="">— Chọn diện cư trú —</option>
          @foreach($resTypes as $t)
            <option value="{{ $t->id }}"
              @selected(old('person.residence_type_id', $person->residence_type_id ?? null) == $t->id)>{{ $t->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Tình trạng cư trú</label>
        <select class="w-full rounded-lg border px-3 py-2" name="person[residence_status_id]">
          <option value="">— Chọn tình trạng —</option>
          @foreach($resStats as $s)
            <option value="{{ $s->id }}"
              @selected(old('person.residence_status_id', $person->residence_status_id ?? null) == $s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- ========== NHÓM 3: THÔNG TIN HỌC TẬP ========== --}}
  <div class="rounded-xl border p-4">
    <div class="th">Thông tin học tập</div>

    {{-- picker trường (lọc theo geo của phiếu) --}}
    <div class="mt-3">
      @include('partials.front.school-picker2', [
        'inputName' => 'education[school_id]',
        'withGeo'   => true,
        'scheme'    => 'new',
        'geo'       => [
          'province_id' => $survey->new_province_id ?? null,
          'district_id' => $survey->new_district_id ?? null,
          'ward_id'     => $survey->new_ward_id ?? null,
        ],
        'mirror'    => ['code'=>'education[school_code]', 'name'=>'education[school_name]'],
        'initial'   => $initialSchool ?? null,
      ])
    </div>

    <div class="mt-3 grid gap-3 md:grid-cols-4">
      {{-- Khối học --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Khối học</label>
        <select name="education[education_block_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn khối học —</option>
          @foreach($blocks as $b)
            <option value="{{ $b->id }}" @selected(old('education.education_block_id', $education->education_block_id ?? null) == $b->id)>{{ $b->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Lớp (nhãn) --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Lớp học (nhãn)</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="education[class_label]"
               value="{{ old('education.class_label', $education->class_label ?? '') }}"
               placeholder="VD: 6A1">
      </div>

      {{-- Bậc TN PT --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Bậc TN Phổ thông</label>
        <select name="education[graduation_level_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn bậc TN PT —</option>
          @foreach($gradLevels as $gl)
            <option value="{{ $gl->id }}" @selected(old('education.graduation_level_id', $education->graduation_level_id ?? null) == $gl->id)>{{ $gl->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Năm TN PT --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Năm TN PT</label>
        <input type="text" class="w-full rounded-lg border px-3 py-2" placeholder="2023-2024"
               name="education[graduation_year]"
               value="{{ old('education.graduation_year', $education->graduation_year ?? '') }}">
      </div>

      {{-- Bổ túc --}}
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" class="accent-cyan-600"
               name="education[is_bo_tuc]" value="1"
               @checked(old('education.is_bo_tuc', (bool)($education->is_bo_tuc ?? false)))>
        <span>Bổ túc</span>
      </label>

      {{-- Bậc TN Nghề --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Bậc TN Nghề</label>
        <select name="education[vocational_level_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn bậc TN nghề —</option>
          @foreach($vocLevels as $vl)
            <option value="{{ $vl->id }}" @selected(old('education.vocational_level_id', $education->vocational_level_id ?? null) == $vl->id)>{{ $vl->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Số bằng TN --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Số bằng TN</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="education[graduation_certificate_no]"
               value="{{ old('education.graduation_certificate_no', $education->graduation_certificate_no ?? '') }}">
      </div>

      {{-- Năm TN nghề --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Năm TN nghề</label>
        <input type="text" class="w-full rounded-lg border px-3 py-2" placeholder="2023-2024"
               name="education[vocational_grad_year]"
               value="{{ old('education.vocational_grad_year', $education->vocational_grad_year ?? '') }}">
      </div>

      {{-- Học xong lớp (từ education_grade) --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Học xong lớp</label>
       <input type="number" min="1" max="12" class="w-full rounded-lg border px-3 py-2"
               name="education[hoc_xong_lop]"
               value="{{ old('education.hoc_xong_lop', $education->hoc_xong_lop ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Học xong năm</label>
        <input type="text" class="w-full rounded-lg border px-3 py-2" placeholder="2023-2024"
               name="education[hoc_xong_nam]"
               value="{{ old('education.hoc_xong_nam', $education->hoc_xong_nam ?? '') }}">
      </div>

      {{-- Bỏ học lớp (từ education_grade) --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Bỏ học lớp</label>
         <input type="number" min="1" max="12" class="w-full rounded-lg border px-3 py-2"
               name="education[bo_hoc_lop]"
               value="{{ old('education.bo_hoc_lop', $education->bo_hoc_lop ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Bỏ học năm</label>
        <input type="text" class="w-full rounded-lg border px-3 py-2" placeholder="2023-2024"
               name="education[bo_hoc_nam]"
               value="{{ old('education.bo_hoc_nam', $education->bo_hoc_nam ?? '') }}">
      </div>
    </div>
  </div>

  {{-- ========== NHÓM 4: XÓA MÙ CHỮ ========== --}}
  <div class="rounded-xl border p-4">
    <div class="th">Xóa mù chữ</div>
    <div class="mt-3 grid gap-3 md:grid-cols-4">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Đang học lớp</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="literacy[dang_hoc_lop]"
               value="{{ old('literacy.dang_hoc_lop', $literacy->dang_hoc_lop ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Hoàn thành lớp</label>
        <select class="w-full rounded-lg border px-3 py-2" name="literacy[hoan_thanh_lop]">
          <option value="">— Chọn mức —</option>
          <option value="3" @selected(old('literacy.hoan_thanh_lop', (string)($literacy->hoan_thanh_lop ?? ''))==='3')>3</option>
          <option value="5" @selected(old('literacy.hoan_thanh_lop', (string)($literacy->hoan_thanh_lop ?? ''))==='5')>5</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Tái mù mức</label>
        <select class="w-full rounded-lg border px-3 py-2" name="literacy[tai_mu_chu_muc]">
          <option value="">— Chọn mức —</option>
          <option value="1" @selected(old('literacy.tai_mu_chu_muc', (string)($literacy->tai_mu_chu_muc ?? ''))==='1')>1</option>
          <option value="2" @selected(old('literacy.tai_mu_chu_muc', (string)($literacy->tai_mu_chu_muc ?? ''))==='2')>2</option>
        </select>
      </div>
    </div>
  </div>

  {{-- ========== NHÓM 5: KHUYẾT TẬT ========== --}}
  <div class="rounded-xl border p-4">
    <div class="th">Khuyết tật</div>
    <div class="mt-3 grid gap-3 md:grid-cols-5">
      @php $checkedIds = (array) old('dis.ids', $disability->disability_ids ?? []); @endphp
      @foreach($disCats as $dc)
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" class="accent-cyan-600" name="dis[ids][]" value="{{ $dc->id }}"
                 @checked(in_array($dc->id, $checkedIds, true))>
          <span>{{ $dc->name }}</span>
        </label>
      @endforeach

      <label class="inline-flex items-center gap-2">
        <input type="checkbox" class="accent-cyan-600" name="dis[has_certificate]" value="1"
               @checked(old('dis.has_certificate', (bool)($disability->has_certificate ?? false)))>
        <span>Có chứng nhận</span>
      </label>

      <label class="inline-flex items-center gap-2">
        <input type="checkbox" class="accent-cyan-600" name="dis[can_learn]" value="1"
               @checked(old('dis.can_learn', (bool)($disability->can_learn ?? false)))>
        <span>Có thể học</span>
      </label>
    </div>
  </div>

  {{-- ========== NHÓM 6: HOÀN CẢNH & LIÊN LẠC ========== --}}
  <div class="rounded-xl border p-4">
    <div class="th">Hoàn cảnh & Liên lạc</div>
    <div class="mt-3 grid gap-3 md:grid-cols-4">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Hoàn cảnh ĐB</label>
        <select class="w-full rounded-lg border px-3 py-2" name="special[special_circumstance_id]">
          <option value="">— Chọn —</option>
          @foreach($specials as $s)
            <option value="{{ $s->id }}"
              @selected(old('special.special_circumstance_id', $special->sc_id ?? null) == $s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Chi tiết HC</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="special[chi_tiet]"
               value="{{ old('special.chi_tiet', $special->chi_tiet ?? '') }}">
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs text-slate-500 mb-1">Phụ huynh / Người liên lạc</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="contact[name]"
               value="{{ old('contact.name', $person->contact_name ?? '') }}">
      </div>
      <div>
        <label class="block text-xs text-slate-500 mb-1">Điện thoại</label>
        <input class="w-full rounded-lg border px-3 py-2" type="tel" inputmode="numeric" pattern="0\d{9,10}" maxlength="11"
               name="person[phone]"
               value="{{ old('person.phone', $person->phone ?? '') }}" placeholder="VD: 0912345678">
      </div>
      <div class="md:col-span-1">
        <label class="block text-xs text-slate-500 mb-1">Ghi chú</label>
        <input class="w-full rounded-lg border px-3 py-2"
               name="contact[note]"
               value="{{ old('contact.note', $person->note ?? '') }}">
      </div>
    </div>
  </div>

  <div class="mt-2 flex items-center justify-end gap-2">
    <button type="button" class="rounded-lg border px-3 py-2 hover:bg-slate-50" data-close-modal>Đóng</button>
    <button class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700">{{ $submitLabel }}</button>
  </div>
</form>
