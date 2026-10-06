{{-- resources/views/partials/front/geo-filter.blade.php --}}
@php
  /** ================== LẤY TỪ AUTH USER ================== */
  $auth = auth()->user();
  $isNonAdmin = $auth &&  $auth->role_id != 1 && $auth->role_id != 2  ;
 
    $userProvinceId = $auth?->province_id;
    $userWardId     = $auth?->ward_id;
    $userSchoolId   = $auth?->school_id;
 
    // dd($userProvinceId )
;
    // Tên hiển thị (nếu đã eager-loaded quan hệ; nếu không có thì để null -> frontend sẽ fallback "ID #...")
    $userProvinceName = $auth?->province?->name ?? null;
    $userWardName     = $auth?->ward?->name     ?? null;
    $userSchoolName   = $auth?->school?->name   ?? null;


  /** ================== INPUT / ƯU TIÊN ================== */
  $namePrefix = $namePrefix ?? 'geo';

  $provinceId = $provinceId
    ?? ($geo['province_id'] ?? null)
    ?? request($namePrefix.'.province_id')
    ?? $userProvinceId;

  $districtId = $districtId
    ?? ($geo['district_id'] ?? null)
    ?? request($namePrefix.'.district_id'); // hệ cũ

  $wardId = $wardId
    ?? ($geo['ward_id'] ?? null)
    ?? request($namePrefix.'.ward_id')
    ?? $userWardId;

  $villageId = $villageId
    ?? ($geo['village_id'] ?? null)
    ?? request($namePrefix.'.village_id');
  // dd($provinceId,$districtId, $wardId,$villageId  ) ; // Scheme mặc định
  $schemeInit = $scheme ?? request('scheme','new');

  // Nếu user đã có geo (province/ward/school) và không phải admin → khóa hệ “new”
  $hasAnyUserGeo = $isNonAdmin && ($userProvinceId || $userWardId || $userSchoolId);
  $schemeMode = $schemeMode ?? ($hasAnyUserGeo ? 'new' : 'toggle');

  $showHamlet = $showHamlet ?? true;

  /** =================== SCHOOL OPTIONS =================== */
  $showSchool   = $showSchool ?? true; // cho phép có block trường
  $enableSchool = request()->has('enable_school')
      ? (bool)request('enable_school')
      : (bool)$showSchool;

  $schoolName   = $schoolName ?? 'school_id';
  $schoolId     = $schoolId ?? request($schoolName) ?? $userSchoolId;
  $schoolLabel  = $schoolLabel ?? 'Trường (theo xã đã chọn)';

  // Khóa theo user (role_level ≠ admin) — riêng village nếu đã có giá trị đầu vào cũng khóa
  $lockProvince = $lockProvince ?? ($isNonAdmin && !empty($userProvinceId));
  $lockWard     = $lockWard     ?? ($isNonAdmin && !empty($userWardId));
 $lockVillage  = $lockVillage  ?? false;
  $lockSchool   = $lockSchool   ?? ($isNonAdmin && !empty($userSchoolId));

  // Khi user có province & ward (new) → chế độ readonly new (không render select tỉnh/xã)
  $readOnlyNewGeo = $readOnlyNewGeo ?? ($isNonAdmin && !empty($userProvinceId) && !empty($userWardId));

  /** ====== CẤU HÌNH API TRƯỜNG (nếu dùng) ====== */
  $schoolWardParam   = $schoolWardParam   ?? 'ward_id'; // hoặc 'ward_code_new'
  $schoolUseCode     = $schoolUseCode     ?? false;     // true → gửi code thay id
  $schoolExtraParams = isset($schoolExtraParams) ? (array)$schoolExtraParams : [];

  // Map key object trường nếu backend dùng tên khác
  $schoolIdKey    = $schoolIdKey    ?? 'id';
  $schoolNameKey  = $schoolNameKey  ?? 'name';
  $schoolCodeKey  = $schoolCodeKey  ?? 'code';

  /** =============== INIT VALUE FOR JS =============== */
  $vals = [
    'province_id' => (string)($provinceId ?? ''),
    'district_id' => (string)($districtId ?? ''),
    'ward_id'     => (string)($wardId ?? ''),
    'village_id'  => (string)($villageId ?? ''),
  ];
  // dd($vals);
  $wrapperClass = $wrapperClass ?? '';
@endphp

<style>
  /* Spinner chung */
  .geo-spinner {
    width: 24px; height: 24px;
    border: 3px solid rgba(100,116,139,.25);
    border-top-color: rgba(59,130,246,1);
    border-radius: 50%;
    animation: geo-spin .8s linear infinite;
  }
  @keyframes geo-spin { to { transform: rotate(360deg); } }
  [x-cloak]{display:none!important;}
</style>
<div class="relative {{ $wrapperClass }}"
     x-data="geoPicker({
       scheme: @js($schemeInit),
       schemeMode: @js($schemeMode),
       value:  @js($vals),
       showHamlet: @js($showHamlet),
       readOnlyNewGeo: @js($readOnlyNewGeo),
       lockProvince: @js($lockProvince),
       lockWard:     @js($lockWard),
       lockVillage:  @js($lockVillage),
       lockSchool:   @js($lockSchool),
       showSchool:   @js($showSchool),
       enableSchool: @js($enableSchool),
       schoolName:   @js($schoolName),
       schoolIdInit: @js($schoolId),
       userProvinceName: @js($userProvinceName),
       userWardName:     @js($userWardName),
       userSchoolName:   @js($userSchoolName),
       schoolWardParam:  @js($schoolWardParam),
       schoolUseCode:    @js($schoolUseCode),
       schoolExtraParams:@json((object)$schoolExtraParams),
       schoolIdKey:   @js($schoolIdKey),
       schoolNameKey: @js($schoolNameKey),
       schoolCodeKey: @js($schoolCodeKey)
     })"
     x-init="init()">

  {{-- Overlay spinner --}}
  <div x-cloak x-show="busy"
       class="absolute inset-0 z-10 bg-white/60 backdrop-blur-[1px] grid place-items-center rounded-lg">
    <div class="geo-spinner"></div>
  </div>

  {{-- ============== Chọn sơ đồ ============== --}}
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Sơ đồ địa bàn</label>

    <div class="flex flex-wrap items-center gap-4" x-show="allowToggle" x-cloak>
      <label class="inline-flex items-center gap-2">
        <input type="radio" name="scheme" value="old" class="accent-cyan-600" x-model="scheme">
        Cũ (Tỉnh → Huyện → Xã)
      </label>
      <label class="inline-flex items-center gap-2">
        <input type="radio" name="scheme" value="new" class="accent-cyan-600" x-model="scheme">
        Mới (Tỉnh → Xã)
      </label>
    </div>

    <template x-if="!allowToggle">
      <div>
        <input type="hidden" name="scheme" :value="scheme">
        <div class="text-sm text-slate-600">
          Chế độ: <b x-text="scheme==='old' ? 'Cũ (Tỉnh → Huyện → Xã)' : 'Mới (Tỉnh → Xã)'"></b>
        </div>
      </div>
    </template>
  </div>

  {{-- ============== READONLY (new) ============== --}}
  <template x-if="scheme==='new' && readOnlyNewGeo">
    <div class="md:col-span-9 grid gap-3 md:grid-cols-12" x-cloak>
      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Tỉnh/Thành (mới)</label>
        <div class="px-3 py-2 rounded-lg border bg-slate-50">
          <span x-text="displayProvinceName()"></span>
        </div>
        <input type="hidden" name="{{ $namePrefix }}[province_id]" :value="selProvNew">
      </div>

      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Xã/Phường (mới)</label>
        <div class="px-3 py-2 rounded-lg border bg-slate-50">
          <span x-text="displayWardName()"></span>
        </div>
        <input type="hidden" name="{{ $namePrefix }}[ward_id]" :value="selWardNew">
      </div>

      @if ($showHamlet)
      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (mới)</label>
        <select class="w-full rounded-lg border px-3 py-2"
                name="{{ $namePrefix }}[village_id]"
                x-model="selHamletNew" @change="onChangeHamletNew"
                :disabled="busy || !hamletsNew.length" x-ref="hamletNew">
          <option value="">— Chọn thôn/ấp —</option>
          <template x-for="h in hamletsNew" :key="'nh'+h.id">
            <option :value="String(h.id)"
                    :selected="String(h.id)===String(selHamletNew)"
                    x-text="h.name"></option>
          </template>
        </select>
      </div>
      @endif

      <template x-if="showSchool">
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">{{ $schoolLabel }}</label>

          <template x-if="lockSchool">
            <div>
              <div class="px-3 py-2 rounded-lg border bg-slate-50">
                <span x-text="displaySchoolName()"></span>
              </div>
              <input type="hidden" :name="schoolName" :value="selSchool">
            </div>
          </template>

          <template x-if="!lockSchool">
            <select class="w-full rounded-lg border px-3 py-2"
                    :name="schoolName"
                    x-model="selSchool"
                    :disabled="busy || !schools.length">
              <option value="">— Chọn trường —</option>
              <template x-for="s in schools" :key="'ns'+(s[schoolIdKey] ?? s.id)">
                <option :value="String(s[schoolIdKey] ?? s.id)"
                        x-text="schoolLabelText(s)"></option>
              </template>
            </select>
          </template>
        </div>
      </template>
    </div>
  </template>

  {{-- ============== Giao diện đầy đủ (admin / không khóa) ============== --}}
  <template x-if="!(scheme==='new' && readOnlyNewGeo)">
    <div class="md:col-span-9 grid gap-3 md:grid-cols-12" x-cloak>

      {{-- OLD chain --}}
      <template x-if="scheme==='old'">
        <div class="contents">
          <div class="md:col-span-4">
            <label class="block text-xs text-slate-500 mb-1">Tỉnh (cũ)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[province_id]"
                    x-model="selProvOld" @change="onChangeProvOld"
                    :disabled="busy || lockProvince" x-ref="provOld">
              <option value="">— Chọn tỉnh —</option>
              <template x-for="p in provincesOld" :key="'op'+p.id">
                <option :value="String(p.id)"
                        :selected="String(selProvOld)===String(p.id)"
                        x-text="p.name"></option>
              </template>
            </select>
            <template x-if="lockProvince">
              <input type="hidden" name="{{ $namePrefix }}[province_id]" :value="selProvOld">
            </template>
          </div>

          <div class="md:col-span-4">
            <label class="block text-xs text-slate-500 mb-1">Quận/Huyện (cũ)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[district_id]"
                    x-model="selDistOld" @change="onChangeDistOld"
                    :disabled="busy || !districtsOld.length" x-ref="distOld">
              <option value="">— Chọn quận/huyện —</option>
              <template x-for="d in districtsOld" :key="'od'+d.id">
                <option :value="String(d.id)"
                        :selected="String(selDistOld)===String(d.id)"
                        x-text="d.name"></option>
              </template>
            </select>
          </div>

          <div class="md:col-span-4">
            <label class="block text-xs text-slate-500 mb-1">Phường/Xã (cũ)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[ward_id]"
                    x-model="selWardOld" @change="onChangeWardOld"
                    :disabled="busy || lockWard || !wardsOld.length" x-ref="wardOld">
              <option value="">— Chọn phường/xã —</option>
              <template x-for="w in wardsOld" :key="'ow'+w.id">
                <option :value="String(w.id)"
                        :selected="String(selWardOld)===String(w.id)"
                        x-text="w.name"></option>
              </template>
            </select>
            <template x-if="lockWard">
              <input type="hidden" name="{{ $namePrefix }}[ward_id]" :value="selWardOld">
            </template>
          </div>

          @if ($showHamlet)
          <div class="md:col-span-4">
            <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (cũ)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[village_id]"
                    x-model="selHamletOld" @change="onChangeHamletOld"
                    :disabled="busy || !hamletsOld.length" x-ref="hamletOld">
              <option value="">— Chọn thôn/ấp —</option>
              <template x-for="h in hamletsOld" :key="'oh'+h.id">
                <option :value="String(h.id)"
                        :selected="String(selHamletOld)===String(h.id)"
                        x-text="h.name"></option>
              </template>
            </select>
          </div>
          @endif

          {{-- Trường (OLD) --}}
          <template x-if="showSchool && enableSchool">
            <div class="md:col-span-6">
              <label class="block text-xs text-slate-500 mb-1">{{ $schoolLabel }}</label>

              <div class="relative" @keydown.down.prevent="moveSchool(1)" @keydown.up.prevent="moveSchool(-1)" @keydown.enter.prevent="confirmSchool()">
                <input type="text"
                       class="w-full rounded-lg border px-3 py-2"
                       :placeholder="currentWardId() ? 'Gõ tên hoặc mã trường…' : 'Hãy chọn Xã trước'"
                       x-model="schoolQuery"
                       :disabled="busy || !currentWardId()"
                       @focus="openSchool()"
                       @input="onSchoolInput()">

                <p class="mt-1 text-xs text-slate-500" x-show="selSchool && schoolSelectedLabel">
                  Đã chọn: <b x-text="schoolSelectedLabel"></b>
                </p>

                <div x-cloak x-show="schoolOpen"
                     class="absolute z-20 mt-1 w-full rounded-xl border bg-white shadow-lg max-h-72 overflow-auto">
                  <div class="px-3 py-2 text-xs text-slate-500" x-show="schoolLoading">Đang tìm…</div>

                  <template x-if="!schoolLoading && schoolResults.length===0">
                    <div class="px-3 py-2 text-sm text-slate-500">Không có kết quả</div>
                  </template>

                  <template x-for="(s,idx) in schoolResults" :key="'sug'+(s[schoolIdKey] ?? s.id)">
                    <button type="button"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                            :class="{'bg-slate-100': idx===schoolHighlight}"
                            @mouseenter="schoolHighlight=idx"
                            @click="selectSchool(s)">
                      <span x-text="schoolLabelText(s)"></span>
                    </button>
                  </template>
                </div>
              </div>

              <input type="hidden" :name="schoolName" :value="selSchool">
            </div>
          </template>
        </div>
      </template>

      {{-- NEW chain --}}
      <template x-if="scheme==='new'">
        <div class="contents">
          <div class="md:col-span-6">
            <label class="block text-xs text-slate-500 mb-1">Tỉnh (mới)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[province_id]"
                    x-model="selProvNew" @change="onChangeProvNew"
                    :disabled="busy || lockProvince" x-ref="provNew">
              <option value="">— Chọn tỉnh —</option>
              <template x-for="p in provincesNew" :key="'np'+p.id">
                <option :value="String(p.id)"
                        :selected="String(selProvNew)===String(p.id)"
                        x-text="p.name"></option>
              </template>
            </select>
            <template x-if="lockProvince">
              <input type="hidden" name="{{ $namePrefix }}[province_id]" :value="selProvNew">
            </template>
          </div>

          <div class="md:col-span-6">
            <label class="block text-xs text-slate-500 mb-1">Phường/Xã (mới)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[ward_id]"
                    x-model="selWardNew"
                    @change="onChangeWardNew"
                    x-ref="wardNew"
                    x-effect="$nextTick(() => {
                      if ($refs.wardNew && String($refs.wardNew.value) !== String(selWardNew)) {
                        $refs.wardNew.value = String(selWardNew);
                      }
                    })"
                    :disabled="busy || lockWard || !wardsNew.length">
              <option value="">— Chọn phường/xã —</option>
              <template x-for="w in wardsNew" :key="'nw'+w.id">
                <option :value="String(w.id)"
                        :selected="String(w.id)===String(selWardNew)"
                        x-text="w.name"></option>
              </template>
            </select>
            <template x-if="lockWard">
              <input type="hidden" name="{{ $namePrefix }}[ward_id]" :value="selWardNew">
            </template>
          </div>

          @if ($showHamlet)
          <div class="md:col-span-6">
            <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (mới)</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    name="{{ $namePrefix }}[village_id]"
                    x-model="selHamletNew" @change="onChangeHamletNew"
                    :disabled="busy || !hamletsNew.length" x-ref="hamletNew">
              <option value="">— Chọn thôn/ấp —</option>
              <template x-for="h in hamletsNew" :key="'nh'+h.id">
                <option :value="String(h.id)"
                        :selected="String(h.id)===String(selHamletNew)"
                        x-text="h.name"></option>
              </template>
            </select>
          </div>
          @endif

          {{-- Trường (NEW) --}}
          <template x-if="showSchool && enableSchool">
            <div class="md:col-span-6">
              <label class="block text-xs text-slate-500 mb-1">{{ $schoolLabel }}</label>

              <div class="relative" @keydown.down.prevent="moveSchool(1)" @keydown.up.prevent="moveSchool(-1)" @keydown.enter.prevent="confirmSchool()">
                <input type="text"
                       class="w-full rounded-lg border px-3 py-2"
                       :placeholder="currentWardId() ? 'Gõ tên hoặc mã trường…' : 'Hãy chọn Xã trước'"
                       x-model="schoolQuery"
                       :disabled="busy || !currentWardId()"
                       @focus="openSchool()"
                       @input="onSchoolInput()">

                <p class="mt-1 text-xs text-slate-500" x-show="selSchool && schoolSelectedLabel">
                  Đã chọn: <b x-text="schoolSelectedLabel"></b>
                </p>

                <div x-cloak x-show="schoolOpen"
                     class="absolute z-20 mt-1 w-full rounded-xl border bg-white shadow-lg max-h-72 overflow-auto">
                  <div class="px-3 py-2 text-xs text-slate-500" x-show="schoolLoading">Đang tìm…</div>

                  <template x-if="!schoolLoading && schoolResults.length===0">
                    <div class="px-3 py-2 text-sm text-slate-500">Không có kết quả</div>
                  </template>

                  <template x-for="(s,idx) in schoolResults" :key="'sug'+(s[schoolIdKey] ?? s.id)">
                    <button type="button"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                            :class="{'bg-slate-100': idx===schoolHighlight}"
                            @mouseenter="schoolHighlight=idx"
                            @click="selectSchool(s)">
                      <span x-text="schoolLabelText(s)"></span>
                    </button>
                  </template>
                </div>
              </div>

              <input type="hidden" :name="schoolName" :value="selSchool">
            </div>
          </template>
        </div>
      </template>
    </div>
  </template>
</div>


@once
@push('scripts')
<script>
  // ==== GEO endpoints ====
  const GEO_BASE = '{{ url('/ajax/geo') }}';
  const GEO_API  = {
    provinces: `${GEO_BASE}/provinces`,
    children : (id) => `${GEO_BASE}/children/${id}`,
  };

  // ==== SCHOOL base (nếu cần) ====
  const SCHOOL_BASE = "{{ route('front.schools.schoolpicker') }}";

  // pickArray linh hoạt
  function pickArray(payload){
    if (Array.isArray(payload)) return payload;
    if (payload?.data && Array.isArray(payload.data)) return payload.data;
    if (payload?.items && Array.isArray(payload.items)) return payload.items;
    if (payload?.rows && Array.isArray(payload.rows)) return payload.rows;
    if (payload?.schools && Array.isArray(payload.schools)) return payload.schools;
    return [];
  }
  function toQuery(obj){
    const q = new URLSearchParams();
    Object.entries(obj || {}).forEach(([k,v]) => {
      if (v !== undefined && v !== null && v !== '') q.append(k, v);
    });
    return q.toString();
  }

  function geoPicker(init = {}){
    console.log(init.value);
    return {
      // MODE
      schemeMode: init.schemeMode || 'toggle',
      allowToggle: true,
      scheme: init.scheme || 'new',

      // Readonly new?
      readOnlyNewGeo: !!init.readOnlyNewGeo,

      // Spinner chung
      pending: 0,
      get busy(){ return this.pending > 0; },
      inc(){ this.pending++; },
      dec(){ this.pending = Math.max(0, this.pending - 1); },

      // FLAGS
      showHamlet: !!init.showHamlet,



      // LOCK theo user/đầu vào
      lockProvince: !!init.lockProvince,
      lockWard:     !!init.lockWard,
      lockVillage:  !!init.lockVillage,
      lockSchool:   !!init.lockSchool,

      // GEO state
      provincesOld:[], districtsOld:[], wardsOld:[], hamletsOld:[],
      provincesNew:[], wardsNew:[],    hamletsNew:[],
    
      selProvOld:   String(init.value?.province_id ?? '') || '',
      selDistOld:   String(init.value?.district_id ?? '') || '',
      selWardOld:   String(init.value?.ward_id     ?? '') || '',
      selHamletOld: String(init.value?.village_id  ?? '') || '',

      selProvNew:   String(init.value?.province_id ?? '') || '',
      selWardNew:   String(init.value?.ward_id     ?? '') || '',
      selHamletNew: String(init.value?.village_id  ?? '') || '',

      // SCHOOL state
      showSchool:   !!init.showSchool,
      enableSchool: !!init.enableSchool,
      schoolName:   init.schoolName || 'school_id',
      schoolIdInit: String(init.schoolIdInit ?? '') || '',
      schools:      [],
      selSchool:    '',

      // Hiển thị tên cố định nếu có
      userProvinceName: init.userProvinceName || null,
      userWardName:     init.userWardName     || null,
      userSchoolName:   init.userSchoolName   || null,

      // cấu hình API trường
      schoolWardParam: init.schoolWardParam || 'ward_id',
      schoolUseCode  : !!init.schoolUseCode,
      schoolExtraParams: init.schoolExtraParams || {},

      // map key object trường
      schoolIdKey:   init.schoolIdKey   || 'id',
      schoolNameKey: init.schoolNameKey || 'name',
      schoolCodeKey: init.schoolCodeKey || 'code',
    /* ====== TYPEAHEAD SCHOOL STATE ====== */
    schoolQuery: '',
    schoolOpen: false,
    schoolLoading: false,
    schoolResults: [],
    schoolHighlight: -1,
    schoolSelectedLabel: '',  // để hiển thị "Đã chọn: ..."

    typeaheadDebounceMs: 250,
    _schoolDebounceTimer: null,

    /* ====== TYPEAHEAD HELPERS ====== */
    openSchool(){
      if (!this.currentWardId()) return;
      this.schoolOpen = true;
      // Nếu đã có query => tìm; nếu chưa, vẫn thử load nhanh top N
      this.triggerSchoolSearch();
    },
    closeSchool(){ this.schoolOpen = false; this.schoolHighlight = -1; },

    onSchoolInput(){
      this.selSchool = '';           // khi gõ mới thì bỏ lựa chọn id cũ
      this.schoolSelectedLabel = ''; // bỏ nhãn đã chọn
      this.triggerSchoolSearch();
    },

    moveSchool(step){
      if (!this.schoolOpen || !this.schoolResults.length) return;
      const n = this.schoolResults.length;
      this.schoolHighlight = ( (this.schoolHighlight + step) % n + n ) % n;
    },

    confirmSchool(){
      if (!this.schoolOpen) return;
      if (this.schoolHighlight >= 0 && this.schoolHighlight < this.schoolResults.length){
        this.selectSchool(this.schoolResults[this.schoolHighlight]);
      }
    },

    selectSchool(item){
      const idVal = String(item[this.schoolIdKey] ?? item.id ?? '');
      this.selSchool = idVal;
      this.schoolSelectedLabel = this.schoolLabelText(item);
      this.schoolQuery = this.schoolSelectedLabel;  // đẩy label lên input
      this.closeSchool();
    },

    triggerSchoolSearch(){
      if (this._schoolDebounceTimer) clearTimeout(this._schoolDebounceTimer);
      this._schoolDebounceTimer = setTimeout(() => {
        this.fetchSchoolsTypeahead(this.schoolQuery);
      }, this.typeaheadDebounceMs);
    },

    /* Xây query tìm trường theo geo + scheme hiện tại */
    buildSchoolQuery(query){
      const params = Object.assign({}, this.schoolExtraParams);

      // Scheme gửi rõ ràng (backend có thể dùng)
      params['scheme'] = this.scheme;

      // Gửi theo hệ CURRENT (new/old) — thêm đủ cấp nếu bạn cần
      const provId = (this.scheme==='old' ? this.selProvOld : this.selProvNew);
      const wardId = (this.scheme==='old' ? this.selWardOld : this.selWardNew);
      const distId = (this.scheme==='old' ? this.selDistOld : null);
      const vilId  = (this.scheme==='old' ? this.selHamletOld : this.selHamletNew);

      // Nếu backend nhận geo[...] dạng id/code bạn có thể đổi tại đây
      // Ví dụ: theo endpoint picker bạn vừa viết, có thể chấp nhận geo + scheme
      if (provId) params['geo[province_id]'] = provId;
      if (wardId) params['geo[ward_id]']     = wardId;
      if (this.scheme==='old' && distId) params['geo[district_id]'] = distId;
      if (vilId) params['geo[village_id]']   = vilId;

      // Từ khoá tìm
      if (query) params['q'] = query;

      // giới hạn gợi ý
      params['limit'] = this.schoolSuggestLimit ?? 20;

      return new URLSearchParams(params).toString();
    },

    async fetchSchoolsTypeahead(query){
      if (!this.showSchool || !this.enableSchool) return;
      if (!this.currentWardId() && !this.selProvNew && !this.selProvOld) {
        // bắt buộc cần ít nhất ward hoặc province theo yêu cầu bạn
        this.schoolResults = [];
        return;
      }
      try{
        this.schoolLoading = true;
        this.schoolOpen = true;

        // dùng endpoint “picker” không phân trang
        const qs = this.buildSchoolQuery(query);
        const url = `${SCHOOL_BASE.replace(/\/$/, '')}?${qs}`;

        const r = await fetch(url, { headers: { Accept:'application/json' }});
        const payload = await r.json();

        // lấy mảng kết quả (dùng pickArray nếu bạn đã có)
        let items = Array.isArray(payload) ? payload : (payload?.data || payload?.items || payload?.schools || []);
        this.schoolResults = items;

        // nếu đang có schoolIdInit mà chưa set label thì cố lấy từ kết quả
        if (this.selSchool && !this.schoolSelectedLabel){
          const found = this.schoolResults.find(s => String(s[this.schoolIdKey] ?? s.id) === String(this.selSchool));
          if (found) this.schoolSelectedLabel = this.schoolLabelText(found);
        }

        this.schoolHighlight = this.schoolResults.length ? 0 : -1;
      }catch(e){
        console.error('fetchSchoolsTypeahead', e);
        this.schoolResults = [];
      }finally{
        this.schoolLoading = false;
      }
    },
      async init(){
        // Ép cố định mode khi cần
        if (this.schemeMode === 'old' || this.schemeMode === 'new') {
          this.allowToggle = false;
          this.scheme = this.schemeMode;
        } else { this.allowToggle = true; }

        // Nếu readonly new: không cần load provinces/wards; chỉ cần load hamlets theo ward hiện có
        if (this.scheme === 'new' && this.readOnlyNewGeo) {
          // set select school
          this.selSchool = this.schoolIdInit || '';

          // chỉ fetch hamlets của selWardNew (nếu bật)
          if (this.showHamlet && this.selWardNew) {
            this.inc();
            this.hamletsNew = await this.fetchChildren(this.selWardNew).finally(()=>this.dec());
          }

          // Nếu không khóa school và cho phép hiển thị trường → có thể load danh sách trường theo ward (tùy bạn)
          if (this.showSchool && !this.lockSchool) {
            await this.refreshSchools();
            if (this.schoolIdInit && this.schools.length){
              const ok = this.schools.some(s => String(s[this.schoolIdKey] ?? s.id) === String(this.schoolIdInit));
              if (ok) this.selSchool = String(this.schoolIdInit);
            }
          }

          return; // kết thúc init sớm
        }

        // ---- Trường hợp giao diện đầy đủ (admin hoặc không khóa) ----
        await Promise.all([ this.loadProvinces('old'), this.loadProvinces('new') ]);

        await this.prefillChain();

        // load school nếu cần
        await this.refreshSchools();
        if (this.schoolIdInit && this.schools.length){
          const ok = this.schools.some(s => String(s[this.schoolIdKey] ?? s.id) === String(this.schoolIdInit));
          if (ok) this.selSchool = String(this.schoolIdInit);
        }

        // Watchers
        this.$watch('scheme', async () => {
          if (!this.allowToggle) return;
          if (this.scheme==='old'){
            this.selProvNew=this.selWardNew=this.selHamletNew='';
            this.wardsNew=this.hamletsNew=[];
          } else {
            this.selProvOld=this.selDistOld=this.selWardOld=this.selHamletOld='';
            this.districtsOld=this.wardsOld=this.hamletsOld=[];
          }
          if (!this.lockSchool) this.selSchool = '';
          await this.refreshSchools();
        });

        this.$watch('enableSchool', async (v) => {
          if (v && !this.schools.length) await this.refreshSchools();
          if (!v && !this.lockSchool) this.selSchool = '';
        });
      },

      async prefillChain(){
        // OLD chain
        if (this.selProvOld){
          this.inc(); this.districtsOld = await this.fetchChildren(this.selProvOld).finally(()=>this.dec());
          if (this.selDistOld){
            this.inc(); this.wardsOld = await this.fetchChildren(this.selDistOld).finally(()=>this.dec());
            if (this.selWardOld && this.showHamlet){
              this.inc(); this.hamletsOld = await this.fetchChildren(this.selWardOld).finally(()=>this.dec());
            }
          }
        }

        // NEW chain
        if (this.selProvNew){
          this.inc(); this.wardsNew = await this.fetchChildren(this.selProvNew).finally(()=>this.dec());
          if (this.selWardNew && this.showHamlet){
            this.inc(); this.hamletsNew = await this.fetchChildren(this.selWardNew).finally(()=>this.dec());
          }
        }
      },

      async loadProvinces(scheme){
        try{
          this.inc();
          const r = await fetch(`${GEO_API.provinces}?scheme=${scheme}`, {headers:{Accept:'application/json'}});
          const items = await r.json();
          if (scheme==='old') this.provincesOld = items; else this.provincesNew = items;
        }catch(e){ console.error('loadProvinces', e); }
        finally{ this.dec(); }
      },

      async fetchChildren(pid){
        if (!pid) return [];
        try{
          const r = await fetch(GEO_API.children(pid), {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.children) ? j.children : [];
        }catch(e){ console.error('fetchChildren', e); return []; }
      },

      // ========= GEO handlers =========
      async onChangeProvOld(e){
        this.selProvOld = e.target.value || '';
        this.selDistOld = this.selWardOld = this.selHamletOld = '';
        this.districtsOld = this.wardsOld = this.hamletsOld = [];
        if (this.selProvOld){
          this.inc(); this.districtsOld = await this.fetchChildren(this.selProvOld).finally(()=>this.dec());
        }
        if (!this.lockSchool) this.selSchool = '';
        await this.refreshSchools();
      },

      async onChangeDistOld(e){
        this.selDistOld = e.target.value || '';
        this.selWardOld = this.selHamletOld = '';
        this.wardsOld = this.hamletsOld = [];
        if (this.selDistOld){
          this.inc(); this.wardsOld = await this.fetchChildren(this.selDistOld).finally(()=>this.dec());
        }
        if (!this.lockSchool) this.selSchool = '';
        await this.refreshSchools();
      },

      async onChangeWardOld(e){
        this.selWardOld = e.target.value || '';
        this.selHamletOld = '';
        this.hamletsOld = [];
        if (this.selWardOld && this.showHamlet){
          this.inc(); this.hamletsOld = await this.fetchChildren(this.selWardOld).finally(()=>this.dec());
        }
        if (!this.lockSchool) this.selSchool = '';
        await this.refreshSchools();
      },
      onChangeHamletOld(e){
        this.selHamletOld = e.target.value || '';
      },

      async onChangeProvNew(e){
        this.selProvNew = e.target.value || '';
        this.selWardNew = this.selHamletNew = '';
        this.wardsNew = this.hamletsNew = [];
        if (this.selProvNew){
          this.inc(); this.wardsNew = await this.fetchChildren(this.selProvNew).finally(()=>this.dec());
        }
        if (!this.lockSchool) this.selSchool = '';
        await this.refreshSchools();
      },

      async onChangeWardNew(e){
        this.selWardNew = e.target.value || '';
        this.selHamletNew = '';
        this.hamletsNew = [];
        if (this.selWardNew && this.showHamlet){
          this.inc(); this.hamletsNew = await this.fetchChildren(this.selWardNew).finally(()=>this.dec());
        }
        if (!this.lockSchool) this.selSchool = '';
        await this.refreshSchools();
      },
      onChangeHamletNew(e){
          this.selHamletNew = e.target.value || '';
        },

      // ========= SCHOOL helpers =========
      currentWardId(){
        return this.scheme === 'old' ? this.selWardOld : this.selWardNew;
      },

      selectedWardObj(){
        if (this.scheme === 'old') {
          return this.wardsOld.find(w => String(w.id) === String(this.selWardOld));
        }
        return this.wardsNew.find(w => String(w.id) === String(this.selWardNew));
      },

      schoolUrlForCurrentWard(){
        const ward = this.selectedWardObj();
        if (!ward) return null;

        // giá trị gửi đi: id hoặc code
        const wardValue = this.schoolUseCode
          ? (ward.code ?? ward.ward_code ?? ward.code_new ?? ward.ward_code_new)
          : ward.id;

        if (!wardValue) return null;

        const params = Object.assign({}, this.schoolExtraParams, { [this.schoolWardParam]: wardValue });
        const qs = toQuery(params);
        return `${SCHOOL_BASE}${qs ? ('?' + qs) : ''}`;
      },

      async refreshSchools(){
        // Nếu đã khóa school hoặc không bật block school → thôi
        if (!this.showSchool || this.lockSchool) { this.schools=[]; return; }

        const url = this.schoolUrlForCurrentWard();
        if (!url){ this.schools = []; return; }

        try{
          this.inc();
          const r = await fetch(url, {headers:{Accept:'application/json'}});
          const payload = await r.json();
          this.schools = pickArray(payload);
        }catch(e){
          console.error('refreshSchools', e);
          this.schools = [];
        } finally {
          this.dec();
        }
      },

      // ===== Hiển thị nhãn =====
      displayProvinceName(){
        if (this.userProvinceName) return this.userProvinceName;
        return this.selProvNew ? ('ID #' + this.selProvNew) : '—';
      },
      displayWardName(){
        if (this.userWardName) return this.userWardName;
        return this.selWardNew ? ('ID #' + this.selWardNew) : '—';
      },
      displaySchoolName(){
        if (this.userSchoolName) return this.userSchoolName;
        return this.selSchool ? ('ID #' + this.selSchool) : '—';
      },
      schoolLabelText(s){
        const name = s[this.schoolNameKey] ?? s.name ?? '';
        const code = s[this.schoolCodeKey] ?? s.code ?? '';
        return [name, code].filter(Boolean).join(' · ');
      },
    }
  }
</script>
@endpush
@endonce
