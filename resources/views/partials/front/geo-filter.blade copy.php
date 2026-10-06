{{-- resources/views/partials/front/geo-filter.blade.php --}}
@php
  $namePrefix = $namePrefix ?? 'geo';
  $schemeInit = $scheme     ?? request('scheme','new');
  $showHamlet = $showHamlet ?? true;

  $vals = [
    'province_id' => (string)($provinceId ?? request($namePrefix.'.province_id')),
    'district_id' => (string)($districtId ?? request($namePrefix.'.district_id')),
    'ward_id'     => (string)($wardId     ?? request($namePrefix.'.ward_id')),
    'village_id'  => (string)($villageId  ?? request($namePrefix.'.village_id')),
  ];
  $wrapperClass = $wrapperClass ?? '';
@endphp

<div class="{{ $wrapperClass }}"
     x-data="geoPicker({ scheme: @js($schemeInit), value: @js($vals), showHamlet: @js($showHamlet) })"
     x-init="init()">
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Sơ đồ địa bàn</label>
    <div class="flex items-center gap-4">
      <label class="inline-flex items-center gap-2">
        <input type="radio" name="scheme" value="old" class="accent-cyan-600" x-model="scheme"> Cũ (Tỉnh → Huyện → Xã)
      </label>
      <label class="inline-flex items-center gap-2">
        <input type="radio" name="scheme" value="new" class="accent-cyan-600" x-model="scheme"> Mới (Tỉnh → Xã)
      </label>
    </div>
  </div>

  {{-- OLD --}}
  <template x-if="scheme==='old'">
    <div class="md:col-span-9 grid gap-3 md:grid-cols-12" x-cloak>
      <div class="md:col-span-4">
        <label class="block text-xs text-slate-500 mb-1">Tỉnh (cũ)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[province_id]"
                x-model="selProvOld" @change="onChangeProvOld" x-ref="provOld">
          <option value="">— Chọn tỉnh —</option>
          <template x-for="p in provincesOld" :key="'op'+p.id">
            <option :value="String(p.id)"
                    :selected="String(p.id)===selProvOld"
                    x-text="p.name"></option>
          </template>
        </select>
      </div>

      <div class="md:col-span-4">
        <label class="block text-xs text-slate-500 mb-1">Quận/Huyện (cũ)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[district_id]"
                x-model="selDistOld" @change="onChangeDistOld"
                :disabled="!districtsOld.length" x-ref="distOld">
          <option value="">— Chọn quận/huyện —</option>
          <template x-for="d in districtsOld" :key="'od'+d.id">
            <option :value="String(d.id)"
                    :selected="String(d.id)===selDistOld"
                    x-text="d.name"></option>
          </template>
        </select>
      </div>

      <div class="md:col-span-4">
        <label class="block text-xs text-slate-500 mb-1">Phường/Xã (cũ)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[ward_id]"
                x-model="selWardOld" @change="onChangeWardOld"
                :disabled="!wardsOld.length" x-ref="wardOld">
          <option value="">— Chọn phường/xã —</option>
          <template x-for="w in wardsOld" :key="'ow'+w.id">
            <option :value="String(w.id)"
                    :selected="String(w.id)===selWardOld"
                    x-text="w.name"></option>
          </template>
        </select>
      </div>

      @if ($showHamlet)
      <div class="md:col-span-4">
        <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (cũ)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[village_id]"
                x-model="selHamletOld" @change="onChangeHamletOld"
                :disabled="!hamletsOld.length" x-ref="hamletOld">
          <option value="">— Chọn thôn/ấp —</option>
          <template x-for="h in hamletsOld" :key="'oh'+h.id">
            <option :value="String(h.id)"
                    :selected="String(h.id)===selHamletOld"
                    x-text="h.name"></option>
          </template>
        </select>
      </div>
      @endif
    </div>
  </template>

  {{-- NEW --}}
  <template x-if="scheme==='new'">
    <div class="md:col-span-9 grid gap-3 md:grid-cols-12" x-cloak>
      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Tỉnh (mới)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[province_id]"
                x-model="selProvNew" @change="onChangeProvNew" x-ref="provNew">
          <option value="">— Chọn tỉnh —</option>
          <template x-for="p in provincesNew" :key="'np'+p.id">
            <option :value="String(p.id)"
                    :selected="String(p.id)===selProvNew"
                    x-text="p.name"></option>
          </template>
        </select>
      </div>

      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Phường/Xã (mới)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[ward_id]"
                x-model="selWardNew" @change="onChangeWardNew"
                :disabled="!wardsNew.length" x-ref="wardNew">
          <option value="">— Chọn phường/xã —</option>
          <template x-for="w in wardsNew" :key="'nw'+w.id">
            <option :value="String(w.id)"
                    :selected="String(w.id)===selWardNew"
                    x-text="w.name"></option>
          </template>
        </select>
      </div>

      @if ($showHamlet)
      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (mới)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[village_id]"
                x-model="selHamletNew" @change="onChangeHamletNew"
                :disabled="!hamletsNew.length" x-ref="hamletNew">
          <option value="">— Chọn thôn/ấp —</option>
          <template x-for="h in hamletsNew" :key="'nh'+h.id">
            <option :value="String(h.id)"
                    :selected="String(h.id)===selHamletNew"
                    x-text="h.name"></option>
          </template>
        </select>
      </div>
      @endif
    </div>
  </template>
</div>

@once
@push('scripts')
<script>
  const GEO_BASE = '{{ url('/ajax/geo') }}';
  const GEO_API  = {
    provinces: `${GEO_BASE}/provinces`,
    children : (id) => `${GEO_BASE}/children/${id}`,
  };

  function geoPicker(init = {}){
    return {
      // state
      scheme: init.scheme || 'new',
      showHamlet: !!init.showHamlet,

      provincesOld:[], districtsOld:[], wardsOld:[], hamletsOld:[],
      provincesNew:[], wardsNew:[],    hamletsNew:[],

      selProvOld:   String(init.value?.province_id ?? '') || '',
      selDistOld:   String(init.value?.district_id ?? '') || '',
      selWardOld:   String(init.value?.ward_id     ?? '') || '',
      selHamletOld: String(init.value?.village_id  ?? '') || '',

      selProvNew:   String(init.value?.province_id ?? '') || '',
      selWardNew:   String(init.value?.ward_id     ?? '') || '',
      selHamletNew: String(init.value?.village_id  ?? '') || '',

      async init(){
        await Promise.all([this.loadProvinces('old'), this.loadProvinces('new')]);

        // Prefill theo query
        if (this.selProvOld){
          this.districtsOld = await this.fetchChildren(this.selProvOld);
          if (this.selDistOld){
            this.wardsOld = await this.fetchChildren(this.selDistOld);
            if (this.selWardOld && this.showHamlet){
              this.hamletsOld = await this.fetchChildren(this.selWardOld);
            }
          }
        }
        if (this.selProvNew){
          this.wardsNew = await this.fetchChildren(this.selProvNew);
          if (this.selWardNew && this.showHamlet){
            this.hamletsNew = await this.fetchChildren(this.selWardNew);
          }
        }

        // Force re-sync sau khi options render (trường hợp trình duyệt không bắt kịp)
        this.$nextTick(() => this.refreshSelected());

        // Đổi scheme thì reset bên kia
        this.$watch('scheme', (to) => {
          if (to==='old'){
            this.selProvNew=this.selWardNew=this.selHamletNew='';
            this.wardsNew=this.hamletsNew=[];
          } else {
            this.selProvOld=this.selDistOld=this.selWardOld=this.selHamletOld='';
            this.districtsOld=this.wardsOld=this.hamletsOld=[];
          }
          this.$nextTick(() => this.refreshSelected());
        });
      },

      refreshSelected(){
        // đồng bộ giá trị cho chắc ăn
        if (this.$refs.provOld)  this.$refs.provOld.value  = this.selProvOld;
        if (this.$refs.distOld)  this.$refs.distOld.value  = this.selDistOld;
        if (this.$refs.wardOld)  this.$refs.wardOld.value  = this.selWardOld;
        if (this.$refs.hamletOld)this.$refs.hamletOld.value= this.selHamletOld;

        if (this.$refs.provNew)  this.$refs.provNew.value  = this.selProvNew;
        if (this.$refs.wardNew)  this.$refs.wardNew.value  = this.selWardNew;
        if (this.$refs.hamletNew)this.$refs.hamletNew.value= this.selHamletNew;
      },

      async loadProvinces(scheme){
        try{
          const r = await fetch(`${GEO_API.provinces}?scheme=${scheme}`, {headers:{Accept:'application/json'}});
          const items = await r.json();
          if (scheme==='old') this.provincesOld = items; else this.provincesNew = items;
        }catch(e){ console.error('loadProvinces', e); }
      },
      async fetchChildren(pid){
        if (!pid) return [];
        try{
          const r = await fetch(GEO_API.children(pid), {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.children) ? j.children : [];
        }catch(e){ console.error('fetchChildren', e); return []; }
      },

      async onChangeProvOld(e){
        this.selProvOld = e.target.value || '';
        this.selDistOld = this.selWardOld = this.selHamletOld = '';
        this.districtsOld = await this.fetchChildren(this.selProvOld);
        this.wardsOld = this.hamletsOld = [];
        this.$nextTick(() => this.refreshSelected());
      },
      async onChangeDistOld(e){
        this.selDistOld = e.target.value || '';
        this.selWardOld = this.selHamletOld = '';
        this.wardsOld = await this.fetchChildren(this.selDistOld);
        this.hamletsOld = [];
        this.$nextTick(() => this.refreshSelected());
      },
      async onChangeWardOld(e){
        this.selWardOld = e.target.value || '';
        this.selHamletOld = '';
        this.hamletsOld = this.showHamlet ? await this.fetchChildren(this.selWardOld) : [];
        this.$nextTick(() => this.refreshSelected());
      },
      onChangeHamletOld(e){ this.selHamletOld = e.target.value || ''; },

      async onChangeProvNew(e){
        this.selProvNew = e.target.value || '';
        this.selWardNew = this.selHamletNew = '';
        this.wardsNew = await this.fetchChildren(this.selProvNew);
        this.hamletsNew = [];
        this.$nextTick(() => this.refreshSelected());
      },
      async onChangeWardNew(e){
        this.selWardNew = e.target.value || '';
        this.selHamletNew = '';
        this.hamletsNew = this.showHamlet ? await this.fetchChildren(this.selWardNew) : [];
        this.$nextTick(() => this.refreshSelected());
      },
      onChangeHamletNew(e){ this.selHamletNew = e.target.value || ''; },
    }
  }
</script>
@endpush
@endonce
