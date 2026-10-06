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
  x-data="schoolPicker({
    inputName: @js($inputName),
    withGeo: @js($withGeo),
    schemeInit: @js($schemeInit),
    geoInit: @js($geoVals),
    mirror: @js($mirror),
    initial: @js($initial),
  })"
  x-init="boot()"
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
  @push('scripts')
    <script>
      const SCHOOL_DATA_URL = @json(route('front.schools.datapicker')); // API danh sách trường (đã có)
      // helper fetch
      async function fetchJson(url, params = {}) {
        const u = new URL(url, window.location.origin);
        Object.entries(params).forEach(([k, v]) => {
          if (v !== '' && v !== null && v !== undefined) u.searchParams.set(k, v);
        });
        const res = await fetch(u.toString(), { headers: {Accept: 'application/json'} });
        if (!res.ok) throw new Error('API ' + res.status);
        return res.json();
      }

      // Đọc geo hiện có trên form (nếu withGeo=false → dùng geo của form đang tạo survey)
      function readExternalGeo() {
        const pick = (sel) => document.querySelector(sel)?.value || '';
        const scheme = document.querySelector('input[name="scheme"]:checked')?.value
                    || document.querySelector('input[name="scheme"]')?.value
                    || 'new';
        return {
          scheme,
          province_id: pick('select[name="geo[province_id]"]'),
          district_id: pick('select[name="geo[district_id]"]'),
          ward_id    : pick('select[name="geo[ward_id]"]'),
        };
      }

      window.schoolPicker = function(init){
        return {
          // config
          inputName: init.inputName,
          withGeo:   !!init.withGeo,
          schemeInit: init.schemeInit || 'new',
          geoInit:    init.geoInit || { province_id:'', district_id:'', ward_id:'' },
          mirror:     init.mirror || {},
          initial:    init.initial || null,

          // state
          q: '',
          open: false,
          loading: false,
          page: 1,
          perPage: 10,
          hasMore: false,
          results: [],
          picked: null,

          boot(){
            // Prefill trường đã chọn
            if (this.initial && this.initial.id) {
              this.picked = this.initial;
              this.q = this.initial.name || '';
            }
          },

          // Lấy geo hiện tại (ưu tiên geo riêng nếu withGeo=true, ngược lại dùng geo của form)
          currentGeo(){
            if (!this.withGeo) return readExternalGeo();
            // đọc theo namePrefix của geo riêng (geo_school)
            const pick = (n) => document.querySelector(`select[name="geo_school[${n}]"]`)?.value || '';
            const scheme = document.querySelector('input[name="scheme"]:checked')?.value
                        || document.querySelector('input[name="scheme"]')?.value
                        || this.schemeInit;
            return { scheme, province_id: pick('province_id'), district_id: pick('district_id'), ward_id: pick('ward_id') };
          },

          async search(reset = true){
            this.open = true;
            if (reset) { this.page = 1; this.results = []; this.hasMore = false; }

            const geo = this.currentGeo();
            this.loading = true;
            try {
              const params = {
                q: this.q,
                page: this.page,
                per_page: this.perPage,
              };

              // ⚠️ map geo → đúng tên field server đang dùng
              if (geo.scheme === 'new') {
                if (geo.province_id) params.province_code_new = geo.province_id;
                if (geo.ward_id)     params.ward_code_new     = geo.ward_id;
              } else { // old
                if (geo.province_id) params.province_code_old  = geo.province_id;
                if (geo.district_id) params.district_code_old  = geo.district_id;
                if (geo.ward_id)     params.ward_code_old      = geo.ward_id;
              }

              const js  = await fetchJson(SCHOOL_DATA_URL, params);

              const data = Array.isArray(js?.data) ? js.data : [];
              const seen = new Set(this.results.map(x => (x.id ?? x.code)));
              const normalized = data.map((r,i) => ({
                ...r,
                _key: [(r.id ?? ''),(r.code ?? ''),'p'+this.page,'i'+i].join(':')
              })).filter(r => {
                const k = (r.id ?? r.code);
                if (k == null) return true;
                if (seen.has(k)) return false;
                seen.add(k);
                return true;
              });

              this.results = reset ? normalized : this.results.concat(normalized);
              this.hasMore = (js.current_page || 1) < (js.last_page || 1);
            } finally {
              this.loading = false;
            }
          }
          ,

          async loadMore(){
            if (!this.hasMore || this.loading) return;
            this.page += 1;
            await this.search(false);
          },

          pick(item){
            this.picked = item;
            this.q = item.name || '';
            this.open = false;
            // mirror fields (nếu cấu hình)
            if (this.mirror.code) {
              const el = document.querySelector(`input[name='${this.mirror.code}']`);
              if (el) el.value = item.code || '';
            }
            if (this.mirror.name) {
              const el = document.querySelector(`input[name='${this.mirror.name}']`);
              if (el) el.value = item.name || '';
            }
          },

          clearPick(){
            this.picked = null;
            if (this.mirror.code) {
              const el = document.querySelector(`input[name='${this.mirror.code}']`);
              if (el) el.value = '';
            }
            if (this.mirror.name) {
              const el = document.querySelector(`input[name='${this.mirror.name}']`);
              if (el) el.value = '';
            }
          },
        }
      }
    </script>
  @endpush
@endonce
