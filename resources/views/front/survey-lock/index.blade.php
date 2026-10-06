@extends('layouts.front')
@section('title','Khóa dữ liệu / thống kê')
@section('description','Thiết lập khóa theo địa bàn và phạm vi (scope)')

@section('content')
<div class="min-h-screen bg-gray-50 py-8"
     x-data="lockUI()" x-init="boot()">

  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Khóa dữ liệu / thống kê</h1>
      <p class="mt-2 text-gray-600">
        Chọn địa phận rồi chọn <b>phạm vi khóa</b> để lưu. Bạn có thể khóa ở cấp Tỉnh/Huyện/Xã hoặc khóa toàn hệ thống.
      </p>
    </div>

    <!-- Form tạo khóa -->
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
      <form method="POST" action="{{ route('front.surveylocks.store') }}"
            @submit="beforeSubmit">
        @csrf

        <div class="grid gap-6 md:grid-cols-12">
          <!-- Phạm vi khóa -->
          <div class="md:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Phạm vi khóa</label>
            <select name="scope" x-model="scope"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
              <option value="">— Chọn phạm vi —</option>
              <option value="SURVEY">Khóa phiếu điều tra</option>
              <option value="STATS_SEND">Khóa gửi thống kê</option>
              <option value="STATS_INPUT">Khóa nhập liệu thống kê</option>
            </select>
            <p class="mt-1 text-xs text-gray-500">
              Chọn chính xác thứ bạn muốn khóa.
            </p>
          </div>

          <!-- Cấp khóa -->
          {{-- <div class="md:col-span-8">
            <label class="block text-sm font-medium text-gray-700 mb-2">Cấp địa bàn (geo level)</label>
            <div class="flex flex-wrap gap-4 mt-1">
              <label class="inline-flex items-center gap-2">
                <input type="radio" class="accent-blue-600" value="GLOBAL" x-model="geoLevel">
                Toàn hệ thống
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="radio" class="accent-blue-600" value="PROVINCE" x-model="geoLevel">
                Tỉnh/Thành phố
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="radio" class="accent-blue-600" value="DISTRICT" x-model="geoLevel">
                Quận/Huyện
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="radio" class="accent-blue-600" value="WARD" x-model="geoLevel">
                Phường/Xã
              </label>
            </div>
          </div> --}}
        </div>

        <!-- Bộ chọn địa bàn -->
        <div class="grid gap-6 md:grid-cols-12 mt-6">
           <div class="md:col-span-12">
                   @include('partials.front.geo-filter', [
                        'scheme'      => 'new',
                        'schemeMode'  => 'new',   // hoặc 'old' / 'new'
                        'showHamlet'  => true,
                        'schoolWardParam' => 'ward_id',
                    'schoolUseCode'   => false,
                        // (tùy chọn) trường:
                        'showSchool'  => false,
                        'enableSchool'=> request('enable_school', false),
                        'schoolName'  => 'school_id',
                        'schoolId'    => request('school_id'),
                        'schoolDataUrl' => route('front.schools.datapicker'),
                        'wrapperClass'=> 'md:col-span-8 grid md:grid-cols-12 gap-3'
                    ])
            </div>
               
        </div>

        <!-- Hiệu lực & ghi chú -->
        {{-- <div class="grid gap-6 md:grid-cols-12 mt-6">
          <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-2">Bật hiệu lực</label>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="is_active" class="accent-blue-600" checked>
              <span class="text-sm text-gray-700">Khóa ngay khi lưu</span>
            </label>
          </div>

          <div class="md:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Hiệu lực từ</label>
            <input type="datetime-local" name="start_at"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
          </div>

          <div class="md:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Đến</label>
            <input type="datetime-local" name="end_at"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
          </div>

          <div class="md:col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú</label>
            <input type="text" name="note" placeholder="Ghi chú (tuỳ chọn)"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
          </div>
        </div> --}}

        <!-- Hidden fields để post đúng shape -->
        {{-- <input type="hidden" name="geo_level" :value="geoLevel">
        <input type="hidden" name="geo_id"    :value="computedGeoId"> --}}

        <!-- Tóm tắt & Submit -->
        <div class="mt-6 flex items-center justify-between">
          {{-- <div class="text-sm text-gray-600">
            <b>Tóm tắt:</b>
            <span x-text="summaryText()"></span>
          </div> --}}
          <button type="submit" class="btn"
                  :disabled="!canSubmit()"
                  :class="['px-4 py-2 rounded-lg text-white',
                           canSubmit() ? 'bg-slate-800 hover:bg-slate-900' : 'bg-slate-400 cursor-not-allowed']">
            Lưu khóa
          </button>
        </div>
      </form>
    </div>

    <!-- Danh sách khóa hiện có -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Khóa đã thiết lập</h2>
        {{-- chỗ trống cho bộ lọc nhỏ nếu muốn --}}
      </div>

      @php /** @var \Illuminate\Support\Collection|\App\Models\SurveyLock[] $locks */ @endphp
      @if(!empty($locks) && count($locks))
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-gray-50 text-gray-600">
                <th class="text-left px-3 py-2 border">#</th>
                <th class="text-left px-3 py-2 border">Phạm vi</th>
                <th class="text-left px-3 py-2 border">Địa bàn</th>
                <th class="text-left px-3 py-2 border">Hiệu lực</th>
                <th class="text-left px-3 py-2 border">Người tạo</th>
                <th class="text-right px-3 py-2 border">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              @foreach($locks as $i => $lk)
                <tr class="border-b">
                  <td class="px-3 py-2 border">{{ $i+1 }}</td>
                  <td class="px-3 py-2 border">
                    @switch($lk->scope)
                      @case('SURVEY')      <span class="inline-flex items-center rounded px-2 py-0.5 bg-emerald-50 text-emerald-700">Phiếu điều tra</span> @break
                      @case('STATS_SEND')  <span class="inline-flex items-center rounded px-2 py-0.5 bg-amber-50 text-amber-700">Gửi thống kê</span> @break
                      @case('STATS_INPUT') <span class="inline-flex items-center rounded px-2 py-0.5 bg-sky-50 text-sky-700">Nhập liệu thống kê</span> @break
                      @default             <span class="inline-flex items-center rounded px-2 py-0.5 bg-gray-100 text-gray-700">{{ $lk->scope }}</span>
                    @endswitch
                  </td>
                  <td class="px-3 py-2 border">
                    {{ $lk->geo_label ?? ($lk->geo_level === 'GLOBAL' ? 'Toàn hệ thống' : $lk->geo_level.' #'.$lk->geo_id) }}
                  </td>
                  <td class="px-3 py-2 border">
                    @if($lk->is_active)
                      <span class="inline-flex items-center rounded px-2 py-0.5 bg-green-100 text-green-700">Đang khóa</span>
                    @else
                      <span class="inline-flex items-center rounded px-2 py-0.5 bg-gray-100 text-gray-600">Tắt</span>
                    @endif
                  </td>
                 
                  <td class="px-3 py-2 border text-gray-600">
                    {{ $lk->creator->name }}
                  </td>
                  <td class="px-3 py-2 border">
                    <div class="flex items-center justify-end gap-2">
                    @if(auth()->id() == $lk->locked_by || auth()->user()->role_id == 1 ||  auth()->user()->role_id==2)
                      <form method="POST" action="{{ route('front.surveylocks.toggle', $lk->id) }}">
                        @csrf @method('PUT')
                        <button type="submit"
                                class="inline-flex items-center rounded border px-3 py-1 text-sm hover:bg-gray-50">
                          {{ $lk->is_active ? 'Tắt' : 'Bật' }}
                        </button>
                      </form>
                      <form method="POST" action="{{ route('front.surveylocks.destroy', $lk->id) }}"
                            onsubmit="return confirm('Xóa khóa này?');">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center rounded border px-3 py-1 text-sm text-red-600 hover:bg-red-50">
                          Xóa
                        </button>
                      </form>
                    @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-sm text-gray-600">Chưa có khóa nào.</p>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  
 

  function lockUI(){
    return {
      // form state
      scope: '',
      geoLevel: 'GLOBAL',
      provinces: [], districts: [], wards: [],
      selProv: '', selDist: '', selWard: '',

      get computedGeoId(){
        switch(this.geoLevel){
          case 'GLOBAL':   return null;
          case 'PROVINCE': return this.toInt(this.selProv);
          case 'DISTRICT': return this.toInt(this.selDist);
          case 'WARD':     return this.toInt(this.selWard);
        }
        return null;
      },

      boot(){
        this.loadProvinces();

        // reset cấp dưới khi đổi level
        this.$watch('geoLevel', (v)=>{
          if (v==='GLOBAL'){
            this.selProv = ''; this.selDist = ''; this.selWard = '';
          } else if (v==='PROVINCE'){
            this.selDist = ''; this.selWard = '';
          } else if (v==='DISTRICT'){
            this.selWard = '';
          }
        });
      },

      async loadProvinces(){
        try{
          const r = await fetch(GEO_API.provinces, { headers:{'Accept':'application/json'} });
          this.provinces = await r.json();
        }catch(e){ console.error(e); this.provinces = []; }
      },
      async fetchChildren(pid){
        if(!pid) return [];
        try{
          const r = await fetch(GEO_API.children(pid), { headers:{'Accept':'application/json'} });
          const j = await r.json();
          return Array.isArray(j.children) ? j.children : [];
        }catch(e){ console.error(e); return []; }
      },
      async onChangeProv(){
        this.districts = []; this.selDist = ''; this.wards = []; this.selWard = '';
        if (this.selProv) this.districts = await this.fetchChildren(this.selProv);
      },
      async onChangeDist(){
        this.wards = []; this.selWard = '';
        if (this.selDist) this.wards = await this.fetchChildren(this.selDist);
      },

      toInt(v){ const n = Number(v); return Number.isFinite(n) && n>0 ? n : null; },

      canSubmit(){
        if (!this.scope) return false;
        if (this.geoLevel==='GLOBAL') return true;
        if (this.geoLevel==='PROVINCE') return !!this.selProv;
        if (this.geoLevel==='DISTRICT') return !!this.selProv && !!this.selDist;
        if (this.geoLevel==='WARD')     return !!this.selProv && !!this.selDist && !!this.selWard;
        return false;
      },
      summaryText(){
        if (this.geoLevel==='GLOBAL') return 'Khóa phạm vi: ' + (this.scope||'—') + ' · Địa bàn: Toàn hệ thống';
        const bits = [];
        const p = this.provinces.find(x=> String(x.id)===String(this.selProv));
        const d = this.districts.find(x=> String(x.id)===String(this.selDist));
        const w = this.wards.find(x=> String(x.id)===String(this.selWard));
        if (p) bits.push(p.name);
        if (d) bits.push(d.name);
        if (w) bits.push(w.name);
        return `Khóa phạm vi: ${this.scope||'—'} · Địa bàn: ${bits.join(' / ') || '—'}`;
      },
      beforeSubmit(e){
        if (!this.canSubmit()){
          e.preventDefault();
          alert('Vui lòng chọn đầy đủ phạm vi và địa bàn.');
        }
      }
    }
  }
</script>
@endpush
