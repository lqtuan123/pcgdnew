@php
  // ==== Cấu hình mặc định (có thể override khi @include) ====
  $inputName   = $inputName   ?? 'education[school_id]';   // tên input hidden nhận school_id
  $withGeo     = $withGeo     ?? false;                    // true nếu muốn render geo riêng trong picker
  $schemeInit  = $scheme      ?? request('scheme','new');  // mặc định: mới
  $geoVals     = $geo         ?? [
    'province_id' => request('geo.province_id'),
    'district_id' => request('geo.district_id'),
    'ward_id'     => request('geo.ward_id'),
  ];
  // Mirror: điền thêm code/name vào input bạn muốn (nếu cần)
  $mirror = $mirror ?? [
    // 'code' => 'education[school_code]',
    // 'name' => 'education[school_name]',
  ];
  $initial = $initial ?? null; // {id, code, name} nếu muốn prefill
@endphp

<div
  x-data="schoolPicker(@js([
    'inputName'  => $inputName,
    'withGeo'    => $withGeo,
    'schemeInit' => $schemeInit,
    'geoInit'    => $geoVals,
    'mirror'     => $mirror,
    'initial'    => $initial,
  ]))"
  x-init="init()"
  class="space-y-2"
>
  <label class="block text-sm font-medium text-slate-700">Chọn trường học</label>

  {{-- (Tuỳ chọn) geo-filter riêng bên trong picker --}}
  <template x-if="withGeo">
    <div class="grid gap-3 md:grid-cols-12 rounded-lg border p-3">
      @include('partials.front.geo-filter', [
        'wrapperClass' => 'md:col-span-12 grid gap-3 md:grid-cols-12',
        'namePrefix'   => 'geo_school',        // tránh trùng với geo của form
        'scheme'       => $schemeInit,
        'provinceId'   => $geoVals['province_id'] ?? null,
        'districtId'   => $geoVals['district_id'] ?? null,
        'wardId'       => $geoVals['ward_id'] ?? null,
        'showHamlet'   => false,
      ])
    </div>
  </template>

  {{-- Combobox tìm trường --}}
  <div class="relative">
    <div class="flex items-center gap-2">
      <input
        type="text"
        x-model.trim="q"
        @input.debounce.300ms="search(true)"
        @input.debounce.300ms="search(true)"
        placeholder="Nhập tên hoặc mã trường…"
        class="w-full rounded-lg border px-3 py-2"
        autocomplete="off"
      />
      <button type="button" class="btn-soft" @click="clearPick()">Xoá</button>
    </div>

    {{-- Kết quả gợi ý --}}
    <div
      x-show="open && (loading || results.length)"
      x-transition
      @click.outside="open=false"
      class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border bg-white shadow-xl"
    >
      <div class="max-h-80 overflow-y-auto">
        <template x-for="(r, idx) in results" :key="r._key ?? (r.id ?? r.code ?? ('row-' + idx))">

          <button type="button"
                  class="block w-full px-3 py-2 text-left hover:bg-slate-50"
                  @click="pick(r)">
            <div class="font-medium" x-text="r.name"></div>
            <div class="text-xs text-slate-500">
              <span class="font-mono" x-text="r.code"></span>
              <span class="mx-1">·</span>
              <span x-text="r.educational_level || '—'"></span>
              <span class="mx-1" x-show="r.ward_new?.name">·</span>
              <span x-text="r.ward_new?.name || ''"></span>
              <span class="mx-1" x-show="r.province_new?.name">·</span>
              <span x-text="r.province_new?.name || ''"></span>
            </div>
          </button>
        </template>

        <div class="px-3 py-2 text-sm text-slate-500" x-show="!loading && results.length===0">
          Không tìm thấy trường phù hợp.
        </div>
        <div class="px-3 py-2 text-sm text-slate-600" x-show="loading">Đang tải…</div>
      </div>

      <div class="border-t p-2 text-right" x-show="hasMore">
        <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm"
                @click="loadMore()">Tải thêm…</button>
      </div>
    </div>
  </div>

  {{-- Hiển thị trường đã chọn --}}
  <div class="rounded-lg border bg-slate-50 px-3 py-2" x-show="picked">
    <div class="text-sm">
      <span class="font-medium" x-text="picked?.name || ''"></span>
      <span class="ml-2 rounded bg-white px-1.5 py-0.5 font-mono text-xs" x-text="picked?.code || ''"></span>
    </div>
    <div class="text-xs text-slate-500" x-show="picked?.ward_new || picked?.province_new">
      <span x-text="picked?.ward_new?.name || ''"></span>
      <span class="mx-1" x-show="picked?.ward_new?.name && picked?.province_new?.name">·</span>
      <span x-text="picked?.province_new?.name || ''"></span>
    </div>
  </div>

  {{-- Hidden inputs --}}
  <input type="hidden" name="{{ $inputName }}" :value="picked?.id || ''">
  @if(isset($mirror['code']))
    <input type="hidden" name="{{ $mirror['code'] }}" :value="picked?.code || ''">
  @endif
  @if(isset($mirror['name']))
    <input type="hidden" name="{{ $mirror['name'] }}" :value="picked?.name || ''">
  @endif
</div>

 

@once
 
@endonce
 
