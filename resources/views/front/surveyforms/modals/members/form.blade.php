@php
  $mode = $mode ?? 'create';
  $submitLabel = $mode === 'edit' ? 'Cập nhật' : 'Lưu';
  $p = $person; // có thể null ở create
@endphp

@if ($errors->any())
  <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
    <div class="font-medium mb-1">Có lỗi khi lưu:</div>
    <ul class="list-disc ml-5">
      @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
    </ul>
  </div>
@endif

<form data-ajax method="POST"
  action="{{ $mode==='edit'
      ? route('front.surveyforms.members.update.ajax', [$survey->id, $p->id])
      : route('front.surveyforms.members.store.ajax', $survey->id) }}"
  class="grid gap-4">
  @csrf
  @if($mode==='edit') @method('PUT') @endif
  <input type="hidden" name="survey_form_id" value="{{ $survey->id }}">

  {{-- NHÓM 1: ĐỐI TƯỢNG --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Thông tin đối tượng</div>
    <div class="grid md:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Họ đệm</label>
        <input name="person[ho_dem]"
               value="{{ old('person.ho_dem', $p->ho_dem ?? '') }}"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Tên <span class="text-red-500">*</span></label>
        <input name="person[ten]" required
               value="{{ old('person.ten', $p->ten ?? '') }}"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">CCCD</label>
        <input name="person[identity_number]"
               value="{{ old('person.identity_number', $p->identity_number ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               type="text" inputmode="numeric" pattern="\d{9}(\d{3})?"
               maxlength="12" placeholder="9 hoặc 12 chữ số">
      </div>
    </div>

    <div class="grid md:grid-cols-5 gap-3 mt-2">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Ngày</label>
        <input name="person[dob_day]" class="w-full rounded-lg border px-3 py-2"
               type="number" inputmode="numeric" min="1" max="31" step="1"
               value="{{ old('person.dob_day', $p->dob_day ?? '') }}"
               oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value>31)this.value=31; if(this.value!==''&&this.value<1)this.value=1;">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Tháng</label>
        <input name="person[dob_month]" class="w-full rounded-lg border px-3 py-2"
               type="number" inputmode="numeric" min="1" max="12" step="1"
               value="{{ old('person.dob_month', $p->dob_month ?? '') }}"
               oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value>12)this.value=12; if(this.value!==''&&this.value<1)this.value=1;">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Năm</label>
        <input name="person[dob_year]" class="w-full rounded-lg border px-3 py-2"
               type="number" inputmode="numeric" min="1900" max="{{ now()->year+1 }}" step="1"
               value="{{ old('person.dob_year', $p->dob_year ?? '') }}"
               oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value.length>4)this.value=this.value.slice(0,4);">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Giới tính</label>
        <select name="person[is_female]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          <option value="0" @selected(old('person.is_female', (isset($p)&&$p->is_female===0)?'0':null)==='0')>Nam</option>
          <option value="1" @selected(old('person.is_female', (isset($p)&&$p->is_female===1)?'1':null)==='1')>Nữ</option>
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Điện thoại</label>
        <input name="person[phone]" class="w-full rounded-lg border px-3 py-2"
               type="tel" inputmode="numeric" pattern="0\d{9,10}" maxlength="11"
               value="{{ old('person.phone', $p->phone ?? '') }}" placeholder="VD: 0912345678">
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-3 mt-2">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Dân tộc</label>
        <select name="person[ethnicity_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn dân tộc —</option>
          @foreach($ethnicities as $e)
            <option value="{{ $e->id }}" @selected(old('person.ethnicity_id', $p->ethnicity_id ?? '')==$e->id)>{{ $e->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Tôn giáo</label>
        <select name="person[religion_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Không / Chưa xác định —</option>
          @foreach($religions as $r)
            <option value="{{ $r->id }}" @selected(old('person.religion_id', $p->religion_id ?? '')==$r->id)>{{ $r->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Địa chỉ</label>
        <input name="person[addressline]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('person.addressline', $p->addressline ?? '') }}">
      </div>
    </div>
  </div>

  {{-- NHÓM 2: NƠI Ở / QUAN HỆ --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Thông tin nơi đang ở</div>
    <div class="grid md:grid-cols-4 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Quan hệ với chủ hộ</label>
        <input name="membership[role]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('membership.role') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Diện cư trú</label>
        <select name="person[residence_type_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($resTypes as $t)
            <option value="{{ $t->id }}" @selected(old('person.residence_type_id', $p->residence_type_id ?? '')==$t->id)>{{ $t->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Tình trạng cư trú</label>
        <select name="person[residence_status_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($resStats as $s)
            <option value="{{ $s->id }}" @selected(old('person.residence_status_id', $p->residence_status_id ?? '')==$s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- NHÓM 3: HỌC TẬP --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Thông tin học tập</div>
    <div class="grid md:grid-cols-4 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Khối</label>
        <select name="education[education_block_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($blocks as $b)
            <option value="{{ $b->id }}" @selected(old('education.education_block_id', $p?->education?->education_block_id ?? '')==$b->id)>{{ $b->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Lớp (tự do)</label>
        <input name="education[class_label]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('education.class_label', $p?->education?->class_label ?? '') }}"
               placeholder="VD: 6A1">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Trường (tên)</label>
        <input name="education[school_name]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('education.school_name', $p?->education?->school_name ?? '') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Mã trường</label>
        <input name="education[school_code]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('education.school_code', $p?->education?->school_code ?? '') }}">
      </div>
    </div>

    <div class="grid md:grid-cols-4 gap-3 mt-2">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Quận/Huyện học</label>
        <input name="education[studying_district]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('education.studying_district', $p?->education?->studying_district ?? '') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Bậc TN PT</label>
        <select name="education[graduation_level_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($gradLevels as $gl)
            <option value="{{ $gl->id }}" @selected(old('education.graduation_level_id', $p?->education?->graduation_level_id ?? '')==$gl->id)>{{ $gl->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Năm TN PT</label>
        <input name="education[graduation_year]" class="w-full rounded-lg border px-3 py-2"
               type="number" min="1900" max="{{ now()->year+1 }}"
               value="{{ old('education.graduation_year', $p?->education?->graduation_year ?? '') }}">
      </div>
      <div class="flex items-center gap-2">
        <input id="is_bo_tuc" type="checkbox" name="education[is_bo_tuc]" value="1"
               @checked(old('education.is_bo_tuc', $p?->education?->is_bo_tuc ?? false))>
        <label for="is_bo_tuc">Bổ túc</label>
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-3 mt-2">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Bậc TN nghề</label>
        <select name="education[vocational_level_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($vocLevels as $vl)
            <option value="{{ $vl->id }}" @selected(old('education.vocational_level_id', $p?->education?->vocational_level_id ?? '')==$vl->id)>{{ $vl->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Năm TN nghề</label>
        <input name="education[vocational_grad_year]" class="w-full rounded-lg border px-3 py-2"
               type="number" min="1900" max="{{ now()->year+1 }}"
               value="{{ old('education.vocational_grad_year', $p?->education?->vocational_grad_year ?? '') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Số bằng TN</label>
        <input name="education[graduation_certificate_no]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('education.graduation_certificate_no', $p?->education?->graduation_certificate_no ?? '') }}">
      </div>
    </div>
  </div>

  {{-- NHÓM 4: XOÁ MÙ CHỮ --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Xoá mù chữ</div>
    <div class="grid md:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Đang học lớp</label>
        <input name="literacy[dang_hoc_lop]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('literacy.dang_hoc_lop', $p?->literacy?->dang_hoc_lop ?? '') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Hoàn thành lớp</label>
        <input name="literacy[hoan_thanh_lop]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('literacy.hoan_thanh_lop', $p?->literacy?->hoan_thanh_lop ?? '') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Tái mù mức</label>
        <input name="literacy[tai_mu_chu_muc]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('literacy.tai_mu_chu_muc', $p?->literacy?->tai_mu_chu_muc ?? '') }}">
      </div>
    </div>
  </div>

  {{-- NHÓM 5: KHUYẾT TẬT --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Khuyết tật</div>
    <div class="grid md:grid-cols-4 gap-2">
      @php $checked = old('dis.ids', $p?->disability?->disability_ids ?? []); @endphp
      @foreach($disCats as $dc)
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="dis[ids][]" value="{{ $dc->id }}"
                 @checked(in_array($dc->id, (array)$checked, true))>
          <span>{{ $dc->name }}</span>
        </label>
      @endforeach
    </div>
    <div class="mt-2 flex items-center gap-6">
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="dis[has_certificate]" value="1"
               @checked(old('dis.has_certificate', $p?->disability?->has_certificate ?? false))>
        <span>Có chứng nhận</span>
      </label>
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="dis[can_learn]" value="1"
               @checked(old('dis.can_learn', $p?->disability?->can_learn ?? false))>
        <span>Khả năng học</span>
      </label>
    </div>
  </div>

  {{-- NHÓM 6: HOÀN CẢNH --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Hoàn cảnh</div>
    <div class="grid md:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Diện ưu tiên / hoàn cảnh</label>
        <select name="special[special_circumstance_id]" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($specials as $sc)
            <option value="{{ $sc->id }}" @selected(old('special.special_circumstance_id', $p?->specialCircumstance?->special_circumstance_id ?? '')==$sc->id)>{{ $sc->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm text-slate-600 mb-1">Chi tiết</label>
        <input name="special[chi_tiet]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('special.chi_tiet', $p?->specialCircumstance?->chi_tiet ?? '') }}">
      </div>
    </div>
  </div>

  {{-- NHÓM 7: LIÊN LẠC --}}
  <div class="rounded-xl border p-3">
    <div class="font-medium mb-2">Thông tin liên lạc</div>
    <div class="grid md:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Họ tên phụ huynh</label>
        <input name="contact[name]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('contact.name', $p->contact_name ?? '') }}">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Điện thoại</label>
        <input name="contact[phone]" class="w-full rounded-lg border px-3 py-2"
               type="tel" inputmode="numeric" pattern="0\d{9,10}" maxlength="11"
               value="{{ old('contact.phone', $p->phone ?? '') }}" placeholder="VD: 0912345678">
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Ghi chú</label>
        <input name="contact[note]" class="w-full rounded-lg border px-3 py-2"
               value="{{ old('contact.note', $p->note ?? '') }}">
      </div>
    </div>
  </div>

  <div class="mt-2 flex items-center justify-end gap-2">
    <button type="button" class="rounded-lg border px-3 py-2 hover:bg-slate-50" data-close-modal>Đóng</button>
    <button class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700">{{ $submitLabel }}</button>
  </div>
</form>
