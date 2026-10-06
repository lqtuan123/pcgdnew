@php
  /** @var array $defaults */
  $d = $defaults ?? [];
  $submitLabel = $submitLabel ?? 'Lưu';
    use Illuminate\Support\Carbon;
@endphp
    @php


  $surveyedVal = old('surveyed_at',
      isset($d['surveyed_at']) && $d['surveyed_at'] !== ''
        ? Carbon::parse($d['surveyed_at'])->format('Y-m-d')
        : ''
  );
 
@endphp
@if ($errors->any())
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
    <div class="font-medium mb-1">Có lỗi khi lưu:</div>
    <ul class="list-disc ml-5">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if (session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
    {{ session('success') }}
  </div>
@endif

<form data-ajax method="POST" action="{{ $action }}" class="grid gap-4">
  @csrf
  @isset($httpMethod) @method($httpMethod) @endisset

  {{-- SỐ PHIẾU --}}
  <div>
    <label class="block text-sm text-slate-600 mb-1">Số phiếu <span class="text-red-500">*</span></label>
    <input name="so_phieu"
           value="{{ old('so_phieu', $d['so_phieu'] ?? '') }}"
           class="w-full rounded-lg border px-3 py-2"
           required>
    @error('so_phieu')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
  </div>

  {{-- CHỦ HỘ --}}
  <div class="rounded-lg border p-3">
    <div class="font-medium mb-2">Chủ hộ</div>

    <div class="grid md:grid-cols-3 gap-3">
      {{-- CCCD --}}
      <div>
        <label class="block text-sm text-slate-600 mb-1">Số CCCD</label>
        <input name="identity_number"
               value="{{ old('identity_number', $d['identity_number'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               type="text" inputmode="numeric"
               pattern="\d{9}(\d{3})?" {{-- 9 hoặc 12 số --}}
               maxlength="12" autocomplete="off"
               placeholder="9 hoặc 12 chữ số">
        @error('identity_number')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="block text-sm text-slate-600 mb-1">Họ đệm</label>
        <input name="head_ho_dem"
               value="{{ old('head_ho_dem', $d['head_ho_dem'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2">
        @error('head_ho_dem')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="block text-sm text-slate-600 mb-1">Tên <span class="text-red-500">*</span></label>
        <input name="head_ten"
               value="{{ old('head_ten', $d['head_ten'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               required>
        @error('head_ten')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-3 mt-2">
      {{-- Ngày / Tháng / Năm sinh --}}
      <div>
        <label class="block text-sm text-slate-600 mb-1">Ngày</label>
        <input name="head_dob_day"
               value="{{ old('head_dob_day', $d['head_dob_day'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               type="number" inputmode="numeric"
               min="1" max="31" step="1"
               oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value>31)this.value=31; if(this.value!==''&&this.value<1)this.value=1;"
               placeholder="1–31">
        @error('head_dob_day')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="block text-sm text-slate-600 mb-1">Tháng</label>
        <input name="head_dob_month"
               value="{{ old('head_dob_month', $d['head_dob_month'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               type="number" inputmode="numeric"
               min="1" max="12" step="1"
               oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value>12)this.value=12; if(this.value!==''&&this.value<1)this.value=1;"
               placeholder="1–12">
        @error('head_dob_month')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="block text-sm text-slate-600 mb-1">Năm</label>
        <input name="head_dob_year"
               value="{{ old('head_dob_year', $d['head_dob_year'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               type="number" inputmode="numeric"
               min="1900" max="{{ now()->year + 1 }}" step="1"
               oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value.length>4)this.value=this.value.slice(0,4);"
               placeholder="VD: 1990">
        @error('head_dob_year')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </div>

  {{-- NGÀY ĐIỀU TRA & NĂM HỌC --}}
  <div class="grid md:grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-slate-600 mb-1">Ngày điều tra</label>
  
      <input type="date" name="surveyed_at" value="{{ $surveyedVal }}" class="w-full rounded-lg border px-3 py-2">
      @error('surveyed_at')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
    </div>
    <div>
      <label class="block text-sm text-slate-600 mb-1">Năm học</label>
      <input name="academic_year"
             value="{{ old('academic_year', $d['academic_year'] ?? '') }}"
             class="w-full rounded-lg border px-3 py-2">
      @error('academic_year')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
    </div>
  </div>

  {{-- ĐỊA BÀN --}}
  <div class="rounded-lg border p-3">
    <div class="font-medium mb-2">Địa bàn</div>
    @include('partials.front.geo-filter', [
      'namePrefix'  => 'geo',
      'schemeMode'      =>'new',
      'provinceId'  => old('geo.province_id', $d['geo']['province_id'] ?? null),
      'districtId'  => old('geo.district_id', $d['geo']['district_id'] ?? null),
      'wardId'      => old('geo.ward_id', $d['geo']['ward_id'] ?? null),
      'villageId'   => old('geo.village_id', $d['geo']['village_id'] ?? null),
      'showHamlet'  => true,
      'showSchool' => false,
      'enableSchool' => false,
      'wrapperClass'=> 'grid md:grid-cols-12 gap-3'
    ])
  </div>

  {{-- THÔNG TIN LIÊN HỆ & CƯ TRÚ CỦA CHỦ HỘ --}}
  <div class="rounded-lg border p-3">
    <div class="font-medium mb-2">Thông tin chủ hộ</div>

    <div class="grid md:grid-cols-2 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Điện thoại</label>
        <input name="head_phone"
               value="{{ old('head_phone', $d['head_phone'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2"
               type="tel" inputmode="numeric"
               pattern="0\d{9,10}" {{-- 10-11 số, bắt đầu bằng 0 --}}
               maxlength="11" autocomplete="tel"
               placeholder="VD: 0912345678">
        @error('head_phone')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Địa chỉ</label>
        <input name="head_address"
               value="{{ old('head_address', $d['head_address'] ?? '') }}"
               class="w-full rounded-lg border px-3 py-2">
        @error('head_address')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="grid md:grid-cols-2 gap-3 mt-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Loại cư trú</label>
        <select name="residence_type_id" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($resTypes as $t)
            <option value="{{ $t->id }}" @selected(old('residence_type_id', $d['residence_type_id'] ?? null)==$t->id)>
              {{ $t->name }}
            </option>
          @endforeach
        </select>
        @error('residence_type_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="block text-sm text-slate-600 mb-1">Tình trạng cư trú</label>
        <select name="residence_status_id" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Chọn —</option>
          @foreach($resStats as $s)
            <option value="{{ $s->id }}" @selected(old('residence_status_id', $d['residence_status_id'] ?? null)==$s->id)>
              {{ $s->name }}
            </option>
          @endforeach
        </select>
        @error('residence_status_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
      </div>

      <div class="grid md:grid-cols-2 gap-3 mt-3">
        {{-- Dân tộc --}}
        <div>
          <label class="block text-sm text-slate-600 mb-1">Dân tộc</label>
          <select name="ethnicity_id" class="w-full rounded-lg border px-3 py-2">
            <option value="">— Chọn dân tộc —</option>
            @foreach($ethnicities as $e)
              <option value="{{ $e->id }}" @selected(old('ethnicity_id', $d['ethnicity_id'] ?? null)==$e->id)>
                {{ $e->name }}
              </option>
            @endforeach
          </select>
          @error('ethnicity_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        {{-- Tôn giáo --}}
        <div>
          <label class="block text-sm text-slate-600 mb-1">Tôn giáo</label>
          <select name="religion_id" class="w-full rounded-lg border px-3 py-2">
            <option value="">— Không / Chưa xác định —</option>
            @foreach($religions as $r)
              <option value="{{ $r->id }}" @selected(old('religion_id', $d['religion_id'] ?? null)==$r->id)>
                {{ $r->name }}
              </option>
            @endforeach
          </select>
          @error('religion_id')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>
  </div>

  <div class="mt-2 flex items-center justify-end gap-2">
    <button type="button" class="rounded-lg border px-3 py-2 hover:bg-slate-50" data-close-modal>Đóng</button>
    <button class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700">{{ $submitLabel }}</button>
  </div>
</form>
