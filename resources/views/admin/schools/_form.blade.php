@php
  $isEdit = !empty($school);
@endphp

<style>[x-cloak]{display:none!important}</style>

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="schoolForm" x-init="init()" @submit="submitting = true">
  @csrf
  @if(($method ?? 'POST') !== 'POST')
    @method($method)
  @endif

  <article class="card grid gap-4 md:grid-cols-2">
    {{-- Cột trái: Thông tin trường --}}
    <div class="space-y-3">
      <div>
        <label class="block text-sm">Mã trường</label>
        <input name="code" value="{{ old('code', $school->code ?? '') }}" class="w-full rounded-lg border px-3 py-2">
        @error('code')<div class="text-rose-600 text-xs">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="block text-sm">Tên trường</label>
        <input name="name" value="{{ old('name', $school->name ?? '') }}" class="w-full rounded-lg border px-3 py-2">
        @error('name')<div class="text-rose-600 text-xs">{{ $message }}</div>@enderror
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm">Loại hình</label>
          <select name="school_type_id" class="w-full rounded-lg border px-3 py-2">
            <option value="">— Chọn —</option>
            @foreach($types as $t)
              <option value="{{ $t->id }}" @selected(old('school_type_id', $school->school_type_id ?? null) == $t->id)>{{ $t->name }}</option>
            @endforeach
          </select>
          @error('school_type_id')<div class="text-rose-600 text-xs">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="block text-sm">Bậc học</label>
          <select name="educational_level_id" class="w-full rounded-lg border px-3 py-2">
            <option value="">— Chọn —</option>
            @foreach($levels as $l)
              <option value="{{ $l->id }}" @selected(old('educational_level_id', $school->educational_level_id ?? null) == $l->id)>{{ $l->name }}</option>
            @endforeach
          </select>
          @error('educational_level_id')<div class="text-rose-600 text-xs">{{ $message }}</div>@enderror
        </div>

        <div class="mt-2">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_dissolved" value="1"
              @checked(old('is_dissolved', $school->is_dissolved ?? false))>
            <span>Đã giải thể</span>
          </label>
        </div>
        <div class="mt-2">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_conglap" value="1"
              @checked(old('is_conglap', $school->is_conglap ?? true))>
            <span>Trường công lập</span>
          </label>
        </div>
      </div>
    </div>

    {{-- Cột phải: Địa chỉ (cũ / mới) --}}
    <div class="space-y-3">
      <div class="flex items-center gap-3">
        <label class="text-sm">Chọn theo</label>
        <select class="rounded-lg border px-3 py-2" x-model="mode" @change="syncHidden()">
          <option value="old">Địa giới CŨ (tỉnh→quận→phường)</option>
          <option value="new">Địa giới MỚI (tỉnh→cấp 3)</option>
        </select>
      </div>

      {{-- OLD chain: 1→2→3 --}}
      <div x-show="mode==='old'" x-cloak class="grid md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Tỉnh (cũ)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-ref="oldProv" @change="onOldProvChanged">
            <option value="">— Chọn —</option>
            <template x-for="p in lists.old.provs" :key="'op'+p.id">
              <option :value="p.id" x-text="p.name"></option>
            </template>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Quận/Huyện (cũ)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-ref="oldDist" :disabled="!lists.old.dists.length" @change="onOldDistChanged">
            <option value="">— Chọn —</option>
            <template x-for="d in lists.old.dists" :key="'od'+d.id">
              <option :value="d.id" x-text="d.name"></option>
            </template>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Phường/Xã (cũ)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-ref="oldWard" :disabled="!lists.old.wards.length" @change="onOldWardChanged">
            <option value="">— Chọn —</option>
            <template x-for="w in lists.old.wards" :key="'ow'+w.id">
              <option :value="w.id" x-text="w.name"></option>
            </template>
          </select>
        </div>
      </div>

      {{-- NEW chain: 1→3 --}}
      <div x-show="mode==='new'" x-cloak class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Tỉnh (mới)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-ref="newProv" @change="onNewProvChanged">
            <option value="">— Chọn —</option>
            <template x-for="p in lists.new.provs" :key="'np'+p.id">
              <option :value="p.id" x-text="p.name"></option>
            </template>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Cấp 3 (mới)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-ref="newWard" :disabled="!lists.new.wards.length" @change="onNewWardChanged">
            <option value="">— Chọn —</option>
            <template x-for="w in lists.new.wards" :key="'nw'+w.id">
              <option :value="w.id" x-text="w.name"></option>
            </template>
          </select>
        </div>
      </div>

      {{-- Hidden để post về server --}}
      <input type="hidden" name="ward_code"      :value="hidden.ward_old">
      <input type="hidden" name="district_code"  :value="hidden.dist_old">
      <input type="hidden" name="ward_code_new"  :value="hidden.ward_new">

      {{-- Hiển thị nhanh để người dùng thấy mình sẽ lưu gì --}}
      <div class="rounded-lg border p-3 text-xs text-slate-600">
        <div>ward_code (cũ): <span class="font-mono" x-text="hidden.ward_old || '—'"></span></div>
        <div>district_code (cũ): <span class="font-mono" x-text="hidden.dist_old || '—'"></span></div>
        <div>ward_code_new (mới): <span class="font-mono" x-text="hidden.ward_new || '—'"></span></div>
      </div>
    </div>
  </article>

  <div class="flex items-center gap-2">
    <button class="btn-primary">{{ $isEdit ? 'Lưu thay đổi' : 'Tạo mới' }}</button>
    <a href="{{ route('admin.schools.index') }}" class="btn-soft">Quay lại</a>
  </div>
  <!-- Overlay khóa toàn màn hình khi isBusy/submitting -->
<div
  x-show="isBusy"
  x-cloak
  x-transition.opacity
  class="fixed inset-0 z-[200] grid place-items-center bg-black/40"
  aria-busy="true"
  aria-live="polite"
>
  <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-5 shadow-xl">
    <!-- Spinner -->
    <svg class="h-8 w-8 animate-spin" viewBox="0 0 24 24" fill="none" role="status" aria-label="Đang tải">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
    </svg>
    <div class="text-sm text-slate-700">Đang tải… vui lòng chờ</div>
  </div>
</div>
</form>
@push('scripts')
<script>
(() => {
  const R = {
    provinces    : @json(route('admin.geo.provinces')),          // GET ?scheme=old|new
    childrenBase : @json(url('admin/geo/children')),             // GET /{parentId}
    resolve      : @json(route('admin.geo.resolve')),             // GET ?scheme=old|new&code=...
    mapNew2Old   : @json(route('admin.geo.map.new-ward')),       // GET ?code=xxxxx
    mapOld2New   : @json(route('admin.geo.map.old-ward')),       // GET ?code=cu-xxxxx
  };

  document.addEventListener('alpine:init', () => {
    Alpine.data('schoolForm', () => ({
      // ---- mode & hidden
      mode: @json(old('ward_code_new', $school->ward_code_new ?? null) ? 'new' : 'old'),
      hidden: {
        ward_old: @json(old('ward_code',     $school->ward_code     ?? '')),
        dist_old: @json(old('district_code', $school->district_code ?? '')),
        ward_new: @json(old('ward_code_new', $school->ward_code_new ?? '')),
      },
      submitting: false,
      get isBusy(){ // đã có các cờ loading; gộp submit luôn nếu muốn
        return this.loading.old.provs || this.loading.old.dists || this.loading.old.wards
            || this.loading.new.provs || this.loading.new.wards || this.submitting;
      },
      // ---- lists & selections
      lists: { old: { provs: [], dists: [], wards: [] }, new: { provs: [], wards: [] } },
      sel  : { old: { prov: null, dist: null, ward: null }, 'new': { prov: null, ward: null } },

      // ---- loading & concurrency guards
      loading: {
        old: { provs: false, dists: false, wards: false },
        new: { provs: false, wards: false },
      },
      seq:   { old: { dists: 0, wards: 0 }, new: { wards: 0 } },
      aborters: { old: { dists: null, wards: null }, new: { wards: null } },

      get isBusy(){ // disable Save when any loading
        return this.loading.old.provs || this.loading.old.dists || this.loading.old.wards
            || this.loading.new.provs || this.loading.new.wards;
      },

      // ---------- lifecycle ----------
      async init() {
        // 1) Preselect thật nhanh bằng /resolve (không cần quét toàn bộ)
        await this.fastPreselect();

        // 2) Sau đó mới load danh mục tỉnh đầy đủ (để người dùng đổi)
        this.loadProvinces('old'); // không await — chạy nền
        this.loadProvinces('new'); // không await — chạy nền

        // Khi đổi mode, dọn selections phía kia
        this.$watch('mode', (m) => {
          if (m === 'new') {
            this.sel.old = {prov:null, dist:null, ward:null};
            this.lists.old.dists = []; this.lists.old.wards = [];
            this.hidden.ward_old = ''; this.hidden.dist_old = '';
          } else {
            this.sel.new = {prov:null, ward:null};
            this.lists.new.wards = [];
            this.hidden.ward_new = '';
          }
          this.syncHidden();
        });

        this.syncHidden();
      },

      // ---------- Fast preselect (resolve by code) ----------
      async fastPreselect() {
        // OLD side
        if (this.hidden.ward_old) {
          const r = await this.callResolve('old', this.hidden.ward_old).catch(()=>null);
          if (r?.province) {
            // Gắn select ngay với phần tử đã resolve (danh sách sẽ nạp dần)
            this.sel.old.prov = r.province;
            this.$refs.oldProv.value = String(r.province.id);
            await this.loadDistrictsOld(r.province.id, /*force*/true);
          }
          if (r?.district) {
            this.sel.old.dist = r.district;
            this.$refs.oldDist.value = String(r.district.id);
            await this.loadWardsOld(r.district.id, /*force*/true);
          }
          if (r?.ward) {
            this.sel.old.ward = r.ward;
            this.$refs.oldWard.value = String(r.ward.id);
          }
        } else if (this.hidden.dist_old) {
          const r = await this.callResolve('old', this.hidden.dist_old).catch(()=>null);
          if (r?.province) {
            this.sel.old.prov = r.province;
            this.$refs.oldProv.value = String(r.province.id);
            await this.loadDistrictsOld(r.province.id, /*force*/true);
          }
          if (r?.district) {
            this.sel.old.dist = r.district;
            this.$refs.oldDist.value = String(r.district.id);
            await this.loadWardsOld(r.district.id, /*force*/false); // chưa biết ward
          }
        }

        // NEW side
        if (this.hidden.ward_new) {
          const rN = await this.callResolve('new', this.hidden.ward_new).catch(()=>null);
          if (rN?.province) {
            this.sel.new.prov = rN.province;
            this.$refs.newProv.value = String(rN.province.id);
            await this.loadWardsNew(rN.province.id, /*force*/true);
          }
          if (rN?.ward) {
            this.sel.new.ward = rN.ward;
            this.$refs.newWard.value = String(rN.ward.id);
          }
        }
      },

      async callResolve(scheme, code){
        const url = `${R.resolve}?scheme=${encodeURIComponent(scheme)}&code=${encodeURIComponent(code)}`;
        const r = await fetch(url, { headers:{'Accept':'application/json'} });
        if (!r.ok) throw new Error('resolve failed');
        return await r.json();
      },

      // ---------- Loaders ----------
      async loadProvinces(scheme){
        try {
          if (scheme==='old') this.loading.old.provs = true; else this.loading.new.provs = true;
          const res = await fetch(`${R.provinces}?scheme=${encodeURIComponent(scheme)}`, { headers:{'Accept':'application/json'} });
          const arr = res.ok ? await res.json() : [];
          if (scheme==='old') {
            this.lists.old.provs = arr;
            // giữ value đã có
            if (this.sel.old.prov) this.$refs.oldProv.value = String(this.sel.old.prov.id);
          } else {
            this.lists.new.provs = arr;
            if (this.sel.new.prov) this.$refs.newProv.value = String(this.sel.new.prov.id);
          }
        } finally {
          if (scheme==='old') this.loading.old.provs = false; else this.loading.new.provs = false;
        }
      },

      async loadDistrictsOld(parentId, force=false){
        // force: cho phép load ngay cả khi chưa có danh sách tỉnh đầy đủ
        this.lists.old.dists=[]; this.lists.old.wards=[];
        this.sel.old.dist=null;  this.sel.old.ward=null;
        this.$refs.oldDist.value=''; this.$refs.oldWard.value='';
        if(!parentId) return;

        // sequence guard + abort
        const seq = ++this.seq.old.dists;
        if (this.aborters.old.dists) this.aborters.old.dists.abort();
        const ctrl = new AbortController();
        this.aborters.old.dists = ctrl;

        this.loading.old.dists = true;
        try {
          const r = await fetch(`${R.childrenBase}/${parentId}`, {
            headers:{'Accept':'application/json'},
            signal: ctrl.signal
          });
          if (!r.ok) return;
          const j = await r.json();
          // nếu đã có call mới hơn thì bỏ
          if (seq !== this.seq.old.dists) return;
          this.lists.old.dists = j.children || [];
        } catch(_) { /* aborted */ }
        finally { this.loading.old.dists = false; }
      },

      async loadWardsOld(parentId, force=false){
        this.lists.old.wards=[]; this.sel.old.ward=null;
        this.$refs.oldWard.value='';
        if(!parentId) return;

        const seq = ++this.seq.old.wards;
        if (this.aborters.old.wards) this.aborters.old.wards.abort();
        const ctrl = new AbortController();
        this.aborters.old.wards = ctrl;

        this.loading.old.wards = true;
        try {
          const r = await fetch(`${R.childrenBase}/${parentId}`, {
            headers:{'Accept':'application/json'},
            signal: ctrl.signal
          });
          if (!r.ok) return;
          const j = await r.json();
          if (seq !== this.seq.old.wards) return;
          this.lists.old.wards = j.children || [];
        } catch(_) { /* aborted */ }
        finally { this.loading.old.wards = false; }
      },

      async loadWardsNew(parentId, force=false){
        this.lists.new.wards=[]; this.sel.new.ward=null;
        this.$refs.newWard.value='';
        if(!parentId) return;

        const seq = ++this.seq.new.wards;
        if (this.aborters.new.wards) this.aborters.new.wards.abort();
        const ctrl = new AbortController();
        this.aborters.new.wards = ctrl;

        this.loading.new.wards = true;
        try {
          const r = await fetch(`${R.childrenBase}/${parentId}`, {
            headers:{'Accept':'application/json'},
            signal: ctrl.signal
          });
          if (!r.ok) return;
          const j = await r.json();
          if (seq !== this.seq.new.wards) return;
          this.lists.new.wards = j.children || [];
        } catch(_) { /* aborted */ }
        finally { this.loading.new.wards = false; }
      },

      // ---------- Mapping APIs ----------
      async mapFromNewWard(code) {
        if (!code) return {};
        const r = await fetch(`${R.mapNew2Old}?code=${encodeURIComponent(code)}`, { headers:{'Accept':'application/json'} });
        return r.ok ? (await r.json()) : {};
      },
      async mapFromOldWard(code) {
        if (!code) return {};
        const r = await fetch(`${R.mapOld2New}?code=${encodeURIComponent(code)}`, { headers:{'Accept':'application/json'} });
        return r.ok ? (await r.json()) : {};
      },

      // ---------- Events OLD ----------
      async onOldProvChanged(e){
        const id = Number(e.target.value || 0);
        this.sel.old.prov = this.lists.old.provs.find(x=>x.id===id) || null;
        await this.loadDistrictsOld(id);
        this.syncHidden();
      },
      async onOldDistChanged(e){
        const id = Number(e.target.value || 0);
        this.sel.old.dist = this.lists.old.dists.find(x=>x.id===id) || null;
        await this.loadWardsOld(id);
        this.syncHidden();
      },
      onOldWardChanged: async function(e){
        const id = Number(e.target.value || 0);
        this.sel.old.ward = this.lists.old.wards.find(x=>x.id===id) || null;
        this.syncHidden();

        if (this.hidden.ward_old) {
          const m = await this.mapFromOldWard(this.hidden.ward_old);
          this.hidden.ward_new = m.ward_new_code || '';
        } else {
          this.hidden.ward_new = '';
        }
      },

      // ---------- Events NEW ----------
      async onNewProvChanged(e){
        const id = Number(e.target.value || 0);
        this.sel.new.prov = this.lists.new.provs.find(x=>x.id===id) || null;
        await this.loadWardsNew(id);
        this.syncHidden();
      },
      onNewWardChanged: async function(e){
        const id = Number(e.target.value || 0);
        this.sel.new.ward = this.lists.new.wards.find(x=>x.id===id) || null;
        this.syncHidden();

        if (this.hidden.ward_new) {
          const m = await this.mapFromNewWard(this.hidden.ward_new);
          this.hidden.ward_old = m.ward_old_code || '';
          this.hidden.dist_old = m.district_old_code || '';
        } else {
          this.hidden.ward_old = '';
          this.hidden.dist_old = '';
        }
      },

      // ---------- Sync hidden fields ----------
      syncHidden(){
        this.hidden.ward_new = this.sel.new.ward?.code || '';
        this.hidden.ward_old = this.sel.old.ward?.code || '';
        this.hidden.dist_old = this.sel.old.dist?.code || '';
      },
    }));
  });
})();
</script>
@endpush

