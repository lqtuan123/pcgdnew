@extends('layouts.front')
@section('title','Danh sách phiếu điều tra')

@section('content')
<style>.th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb}.td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}</style>

<div x-data="bulkSelect()" class="mx-auto max-w-7xl px-4 py-6">

  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Danh sách đối tượng</h1>
    <a href="{{ route('front.surveys.export', request()->query()) }}"  
       class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
      Xuất Excel
    </a>
  </div>

  <form method="get" class="mb-3 grid gap-3 md:grid-cols-12">
  @php
    $base = $defaultYear ?? \App\Models\SurveyForm::currentAcademicYear();
    [$y1,$y2] = explode('-', $base);
    $years = [($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
  @endphp
  
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Năm học</label>
    <select name="year" class="w-full rounded-lg border px-3 py-2">
      <option value="all" @selected($year==='all')>— Tất cả —</option> {{-- NEW --}}
      @foreach($years as $yy)
        <option value="{{ $yy }}" @selected($yy==$year)>{{ $yy }}</option>
      @endforeach
    </select>
  </div>
   

  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Số phiếu</label>
    <input name="so_phieu" value="{{ $soPhieu }}" class="w-full rounded-lg border px-3 py-2" placeholder="VD: AC001">
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Tìm người (tên/CMND/điện thoại)</label>
    <input name="q" value="{{ $q }}" class="w-full rounded-lg border px-3 py-2">
  </div>
{{-- Độ tuổi từ–đến --}}
<div class="md:col-span-3">
  <label class="block text-xs text-slate-500 mb-1">Độ tuổi (từ)</label>
  <input type="number" min="0" name="min_age" value="{{ old('min_age', $minAge) }}"
         class="w-full rounded-lg border px-3 py-2" placeholder="VD: 5">
</div>
<div class="md:col-span-3">
  <label class="block text-xs text-slate-500 mb-1">Độ tuổi (đến)</label>
  <input type="number" min="0" name="max_age" value="{{ old('max_age', $maxAge) }}"
         class="w-full rounded-lg border px-3 py-2" placeholder="VD: 18">
</div>

{{-- Dân tộc thiểu số --}}
<div class="md:col-span-3">
  <label class="block text-xs text-slate-500 mb-1">Dân tộc</label>
  <select name="minority" class="w-full rounded-lg border px-3 py-2">
    <option value="" @selected($minority===null || $minority==='')>— Tất cả —</option>
    <option value="0" @selected($minority==='0')>Kinh</option>
    <option value="1" @selected($minority==='1')>Thiểu số</option>
  </select>
</div>
  {{-- Geo filter: cho chiếm trọn một hàng --}}
  <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        'namePrefix' => 'geo',
        'scheme'     => request('scheme','old'),
        'provinceId' => request('geo.province_id'),
        'districtId' => request('geo.district_id'),
        'wardId'     => request('geo.ward_id'),
        'villageId'  => request('geo.village_id'),
        'showHamlet' => true,
        // gợi ý layout: giữ scheme 3 cột + cụm selector 9 cột
        'wrapperClass' => 'md:col-span-8 grid md:grid-cols-12 gap-3'
      ])
     
  </div>

  {{-- Hàng nút (gộp lại) --}}
  <div class="md:col-span-12 flex flex-wrap items-end gap-2">
    <button class="btn-primary">Lọc</button>
    <a href="{{ route('front.surveys.index') }}" class="btn-soft">Xoá lọc</a>
    <a href="{{ route('front.surveys.import') }}" class="btn-soft">Nhập từ excel</a>
   <a href="{{ route('front.surveys.create') }}" class="btn-primary">Thêm phiếu mới</a>
   
  </div>
  </form>

  {{-- THANH TÁC VỤ BULK --}}
  <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between rounded-xl border bg-slate-50 p-3">
    <div class="text-sm">
      <div class="font-medium">Tổng số đối tượng: <span class="tabular-nums">{{ number_format($rows->total()) }}</span></div>
      <div class="text-slate-600">
        Hiển thị: <span class="tabular-nums">{{ $rows->firstItem() ?? 0 }}</span>–<span class="tabular-nums">{{ $rows->lastItem() ?? 0 }}</span>
        / trang {{ $rows->currentPage() }}
        <template x-if="selectedCount>0">
          <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-amber-800">Đã chọn: <b x-text="selectedCount"></b></span>
        </template>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      {{-- Form xóa tạm các đã chọn --}}
      <form x-ref="bulkSelForm" id="bulkSelForm" method="POST" action="{{ route('front.surveys.bulk_soft_delete') }}">
        @csrf
        <button type="button"
                class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="selectedCount===0"
                x-text="selectedCount>0 ? `Xóa tạm (${selectedCount})` : 'Xóa tạm'"
                @click="confirmBulkDelete()">
        </button>
      </form>

      {{-- Form xóa thật các đã chọn --}}
      <form x-ref="bulkHardDeleteForm" id="bulkHardDeleteForm" method="POST" action="{{ route('front.surveys.bulk_hard_delete') }}">
        @csrf
        <button type="button"
                class="rounded-lg bg-red-800 px-3 py-2 text-white hover:bg-red-900 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="selectedCount===0"
                x-text="selectedCount>0 ? `Xóa (${selectedCount})` : 'Xóa'"
                @click="confirmBulkHardDelete()">
        </button>
      </form>
    </div>
  </div>

  {{-- DESKTOP TABLE --}}
  <div class="hidden md:block">
      <div class="-mx-4 overflow-x-auto">
        <table class="min-w-[1600px]">
          <thead>
            {{-- Hàng 1: tiêu đề nhóm --}}
            <tr class="bg-slate-100">
              <th class="th sticky left-0 z-30 bg-slate-100 w-10" rowspan="2">
                <input type="checkbox" @change="toggleAll($event)" aria-label="Chọn tất cả">
              </th>
              <th class="th sticky left-10 z-30 bg-slate-100 w-10" rowspan="2"></th>
              <th class="th sticky left-20 z-20 bg-slate-100 w-12 border-slate-600" rowspan="2">#</th>
              @php
                $i = 1;
              
              // Map màu cho từng nhóm
              $GROUP_BG = [ 'bg-amber-50',
                'bg-sky-50',
                'bg-emerald-50',
                'bg-rose-50',
                'bg-violet-50',
                'bg-orange-50',
                'bg-slate-50','bg-amber-50', 'bg-sky-50','bg-emerald-50', 'bg-slate-50','bg-amber-50', 'bg-sky-50','bg-emerald-50',
                'bg-rose-50',
                'bg-violet-50',
              ];
    
              @endphp
              @foreach($groups as $g)
              @php
                $i += 2;
              @endphp
                <th class="th text-center     z-20  {{$GROUP_BG[$i]}} border-slate-{{$i}}00" colspan="{{ count($g['keys']) }}">
                  {{ $g['title'] }}
                </th>
              @endforeach
            </tr>

            {{-- Hàng 2: tiêu đề từng cột --}}
            @php
              // nếu controller không truyền, fallback rỗng
              $sortableKeys = $sortableKeys ?? [];
            @endphp

            <tr class="bg-slate-50">
              @foreach($groups as $g)
                @foreach($g['keys'] as $k)
                  @php
                    $isSortable = in_array($k, $sortableKeys, true);
                    $isActive   = request('sort') === $k;
                    $dir        = request('dir','asc') === 'desc' ? 'desc' : 'asc';
                    $nextDir    = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
                    $arrow      = $isActive ? ($dir === 'asc' ? '▲' : '▼') : '↕';
                    $label      = $columns[$k] ?? $k;
                  @endphp
                  <th class="th text-left">
                    @if($isSortable)
                      <a href="{{ request()->fullUrlWithQuery(['sort'=>$k,'dir'=>$nextDir]) }}"
                        class="inline-flex items-center gap-1 hover:underline"
                        aria-sort="{{ $isActive ? ($dir==='asc'?'ascending':'descending') : 'none' }}">
                        <span>{{ $label }}</span>
                        <span class="text-slate-400 text-xs">{{ $arrow }}</span>
                      </a>
                    @else
                      {{ $label }}
                    @endif
                  </th>
                @endforeach
              @endforeach
            </tr>


          </thead>

          <tbody>
            @forelse($rows as $i => $r)
             
              <tr class="hover:bg-slate-50">
                {{-- Checkbox --}}
                <td class="td sticky left-0 z-20 bg-white w-10">
                  <input type="checkbox" class="row-checkbox" name="ids[]" value="{{ $r['detail_id'] }}"
                         @change="onRowToggle()" aria-label="Chọn đối tượng {{ $r['ten'] }}" form="bulkSelForm">
                </td>

                {{-- Cột # + nút 3 chấm (sticky trái) --}}
                <td class="td sticky left-10 z-20 bg-white w-10">
                  <div x-data="kebabMenu()" x-init="init()" @keydown.escape.window="close()">
                    <button type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100"
                            @click="openMenu($event)" aria-label="Mở menu">
                      ⋮
                    </button>

                    <template x-teleport="body">
                      <div x-show="open" x-transition.opacity.duration.120ms
                           @click.outside="close()" :style="style" class="fixed z-[9999]">
                        <div x-ref="menu" class="w-44 rounded-xl border bg-white shadow-xl overflow-hidden">

                        </div>
                      </div>
                    </template>
                  </div>
                </td>

                {{-- STT --}}
                <td class="td sticky left-20 z-10 bg-white w-12 tabular-nums">{{ $rows->firstItem() + $i }}</td>

                {{-- Các cột dữ liệu còn lại --}}
                @foreach($groups as $g)
                  @foreach($g['keys'] as $k)
                    @php $v = $r[$k] ?? ''; @endphp

                    <td class="td">
                    @if($k == 'ho_dem' || $k === 'ten')
                    
                      <button type="button" class="text-cyan-700 hover:underline"
                           @click.stop.prevent="openWith({{ Js::from($r) }})">
                    @endif
                      
                        {{ is_bool($v) ? ($v ? '✓' : '') : $v }}
                     @if($k === 'ho_dem' || $k === 'ten')
                        </button>
                    @endif
                    </td>
                  @endforeach
                @endforeach
              </tr>
            @empty
              <tr><td class="td" colspan="{{ 1 + collect($groups)->sum(fn($g)=>count($g['keys'])) }}">
                Chưa có dữ liệu.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
  </div>
 {{-- =============== MOBILE LIST (<md) =============== --}}
  <div class="md:hidden">
    @forelse($rows as $i => $r)
      <div class="rounded-xl border p-3 mb-2">
        <div class="flex items-start justify-between gap-3">
          <div>
            {{-- Họ tên -> mở popup --}}
            <button type="button" class="text-cyan-700 hover:underline"
                           @click.stop.prevent="openWith({{ Js::from($r) }})">
              {{ trim(($r['ho_dem'] ?? '').' '.($r['ten'] ?? '')) }}
            </button>

            <div class="mt-1 text-xs text-slate-500 space-x-2">
              <span>#{{ $rows->firstItem() + $i }}</span>
              @if(!empty($r['so_phieu']))
                <span class="inline-block rounded bg-slate-100 px-1.5 py-0.5">Số phiếu: {{ $r['so_phieu'] }}</span>
              @endif
            </div>

            <div class="mt-2 text-sm text-slate-700">
              <div>
                <span class="text-slate-500">Sinh:</span>
                {{ trim(($r['dob_day']??'').'/'.($r['dob_month']??'').'/'.($r['dob_year']??''), '/ ') }}
                · <span class="text-slate-500">Giới:</span> {{ !empty($r['is_female']) ? 'Nữ' : 'Nam' }}
              </div>
              @if(!empty($r['school_name']) || !empty($r['education_grade_name']))
                <div>
                  <span class="text-slate-500">Học:</span>
                  {{ ($r['education_grade_name'] ?? '') ? 'Lớp '.$r['education_grade_name'].' · ' : '' }}
                  {{ $r['school_name'] ?? '' }}
                </div>
              @endif
              @if(!empty($r['addressline']))
                <div class="truncate"><span class="text-slate-500">Đ/c:</span> {{ $r['addressline'] }}</div>
              @endif
            </div>
          </div>

          {{-- Kebab hành động (tuỳ chọn) --}}
          <div x-data="{ open:false }" class="relative shrink-0">
              <button type="button"
                      class="h-8 w-8 rounded-lg grid place-items-center hover:bg-slate-100"
                      @click="open=!open">⋮</button>

              <div x-cloak x-show="open" @click.outside="open=false"
                  class="absolute right-0 top-full mt-1 w-40 rounded-xl border bg-white shadow-lg overflow-hidden z-10">

                <!-- Xoá -->
                <button type="button"
                        class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50"
                        @click="open=false; $refs.delForm.submit()">
                  Xoá
                </button>

                <!-- Form ẩn cho DELETE -->
                <form x-ref="delForm" method="POST" class="hidden">
                  @csrf
                  @method('DELETE')
                </form>
              </div>
            </div>
        </div>
      </div>
    @empty
      <div class="text-sm text-slate-600">Chưa có dữ liệu.</div>
    @endforelse
  </div>

  {{-- =============== MODAL CHI TIẾT (dùng chung) =============== --}}
  <template x-if="open">
    <div class="fixed inset-0 z-[60] bg-black/40 grid place-items-center" @keydown.escape.window="close()">
      <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl"
           @click.outside="close()">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="text-lg font-semibold" x-text="fullName(item)"></div>
            <div class="text-xs text-slate-500 mt-0.5">
              <span x-show="item.so_phieu">Số phiếu: <b x-text="item.so_phieu"></b></span>
            </div>
            <div class="text-xs text-slate-500 mt-0.5 space-x-4">
              <span x-show="hasOld(item)">
                Địa bàn (cũ): <b x-text="locOld(item)"></b>
              </span>
              <span x-show="hasNew(item)">
                Địa bàn (mới): <b x-text="locNew(item)"></b>
              </span>
            </div>
          </div>
          <button class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100" @click="close()">✕</button>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
          {{-- Thông tin cá nhân --}}
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Thông tin cá nhân</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Ngày sinh</dt>
              <dd class="col-span-2" x-text="dob(item)"></dd>

              <dt class="text-slate-500">Giới tính</dt>
              <dd class="col-span-2" x-text="item.is_female ? 'Nữ' : 'Nam'"></dd>

              <dt class="text-slate-500">Dân tộc</dt>
              <dd class="col-span-2" x-text="item.ethnicity || '—'"></dd>

              <dt class="text-slate-500">Tôn giáo</dt>
              <dd class="col-span-2" x-text="item.religion || '—'"></dd>

              <dt class="text-slate-500">Diện ưu tiên</dt>
              <dd class="col-span-2" x-text="item.priority_name || '—'"></dd>
              <dt class="text-slate-500">Chủ hộ</dt>
              <dd class="col-span-2" x-text="item.chuho_ho_dem + ' ' + item.chuho_ten"></dd>
              <dt class="text-slate-500">Địa chỉ</dt>
              <dd class="col-span-2" x-text="item.addressline || '—'"></dd>
              <dt class="text-slate-500">Diện cư trú</dt>
              <dd class="col-span-2" x-text="item.residence_type_name|| '—'  "></dd>
              <dt class="text-slate-500">Tình trạng cư trú</dt>
              <dd class="col-span-2" x-text="item.residence_status_name|| '—'  "></dd>
            </dl>
          </div>

          {{-- Học tập --}}
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Học tập</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Khối/Lớp</dt>
              <dd class="col-span-2"
                  x-text="[(item.education_block_code||''), (item.education_grade_name?('Lớp '+item.education_grade_name):'')].filter(Boolean).join(' · ') || '—'"></dd>

              <dt class="text-slate-500">Trường</dt>
              <dd class="col-span-2"
                  x-text="[(item.school_name||''), (item.school_code||'')].filter(Boolean).join(' · ') || '—'"></dd>

              <dt class="text-slate-500">TN PT</dt>
              <dd class="col-span-2"
                  x-text="[(item.graduation_level_name||''),(item.graduation_year||'')].filter(Boolean).join(' · ') || '—'"></dd>
              <dt class="text-slate-500">Bổ túc</dt>
              <dd class="col-span-2" x-text="item.is_bo_tuc ? 'Có' : 'Không'"></dd>

              <dt class="text-slate-500">TN nghề</dt>
              <dd class="col-span-2"
                  x-text="[(item.vocational_level_name||''),(item.vocational_grad_year||''),(item.vocational_grad_cert_no||'')].filter(Boolean).join(' · ') || '—'"></dd>
            </dl>
          </div>
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Bỏ học</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Học xong lớp</dt>
              <dd class="col-span-2"
                  x-text="item.hoc_xong_lop || '—'"></dd>

              <dt class="text-slate-500">Học xong năm</dt>
              <dd class="col-span-2"
                  x-text="item.hoc_xong_nam || '—'"></dd>
              
              <dt class="text-slate-500">Bỏ học lớp</dt>
              <dd class="col-span-2"
                  x-text="item.bo_hoc_lop || '—'"></dd>

              <dt class="text-slate-500">Bỏ học năm</dt>
              <dd class="col-span-2"
                  x-text="item.bo_hoc_nam || '—'"></dd>
              </dl>
          </div>
          {{-- Mù chữ --}}
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Mù chữ</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Đang học lớp</dt>
              <dd class="col-span-2"
                  x-text="item.lit_dang_hoc_lop || '—'"></dd>

              <dt class="text-slate-500">Hoàn thành lớp</dt>
              <dd class="col-span-2"
                  x-text="item.lit_hoan_thanh_lop || '—'"></dd>
              
             

              <dt class="text-slate-500">Tái mù mức</dt>
              <dd class="col-span-2"
                  x-text="item.lit_tai_mu_muc || '—'"></dd>
              </dl>
          </div>

          {{-- Hoàn cảnh & Liên lạc --}}
          <div class="rounded-xl border p-3 md:col-span-2">
            <div class="font-medium mb-2">Hoàn cảnh & Liên lạc</div>
            <dl class="grid grid-cols-4 gap-y-1 text-sm">
              <dt class="text-slate-500">Hoàn cảnh đặc biệt</dt>
              <dd class="col-span-3" x-text="[(item.sc_name||''),(item.sc_detail||'')].filter(Boolean).join(' · ') || '—'"></dd>

              <dt class="text-slate-500">Phụ huynh</dt>
              <dd class="col-span-3" x-text="item.contact_name || '—'"></dd>

              <dt class="text-slate-500">Điện thoại</dt>
              <dd class="col-span-3" x-text="item.contact_phone || '—'"></dd>

              <dt class="text-slate-500">Ghi chú</dt>
              <dd class="col-span-3" x-text="item.contact_note || '—'"></dd>
            </dl>
          </div>

          {{-- Khuyết tật --}}
          <div class="rounded-xl border p-3 md:col-span-2">
            <div class="font-medium mb-2">Khuyết tật</div>
            <div class="text-sm text-slate-700 grid grid-cols-2 md:grid-cols-4 gap-1">
              <template x-for="k in disabilityKeys" :key="k.key">
                <div>
                  <span class="text-slate-500" x-text="k.label"></span>: 
                  <b x-text="item[k.key] ? '✓' : ''"></b>
                </div>
              </template>
              <div><span class="text-slate-500">Có chứng nhận</span>: <b x-text="item.dis_has_certificate?'✓':''"></b></div>
              <div><span class="text-slate-500">Có thể học</span>: <b x-text="item.dis_can_learn?'✓':''"></b></div>
            </div>
          </div>
        </div>

        <div class="mt-5 flex items-center justify-end gap-2">
          <button class="btn-primary" @click="close()">Đóng</button>
        </div>
      </div>
    </div>
  </template>
</div>
 
@endsection
@push('scripts')
 <script>
  // VD route: /dieu-tra/edit/{survey}
  const EDIT_URL_BASE = @json(url('dieu-tra/edit'));
</script>
<script>

  function kebabMenu(){
    return {
      open:false, style:'left:0;top:0', anchor:null,

      openMenu(ev){
        this.anchor = ev.currentTarget;
        this.open = true;
        this.$nextTick(() => this.place());
      },
      close(){ this.open = false; },

      place(){
        if (!this.anchor) return;

        const r = this.anchor.getBoundingClientRect();
        const menu = this.$refs.menu;
        // đo kích thước thực (fallback nếu menu chưa render xong)
        const w = (menu?.offsetWidth  ?? 176);
        const h = (menu?.offsetHeight ?? 160);
        const gap = 8;

        // ❌ KHÔNG cộng window.scrollX/scrollY vì là fixed
        let left = r.right + gap;
        let top  = r.top;

        const vw = window.innerWidth, vh = window.innerHeight;

        // lật sang trái nếu tràn phải
        if (left + w > vw - 8) left = r.left - w - gap;
        // chặn tràn dưới
        if (top + h > vh - 8)   top  = Math.max(8, vh - h - 8);
        // chặn tràn trên
        if (top < 8) top = 8;

        this.style = `left:${left}px; top:${top}px`;
      },

      init(){
        this._onScroll = () => { if (this.open) this.place(); };
        this._onResize = () => { if (this.open) this.place(); };
        // capture=true để bắt scroll của mọi container (overflow)
        window.addEventListener('scroll', this._onScroll, true);
        window.addEventListener('resize', this._onResize);
      },
      destroy(){
        window.removeEventListener('scroll', this._onScroll, true);
        window.removeEventListener('resize', this._onResize);
      }
    }
  }
</script>
<script>
function detailUI(){
  return {
    open:false,
    item:null,
     // Có dữ liệu địa bàn cũ/mới?
    hasOld(r){ return !!(r?.old_province_name || r?.old_district_name || r?.old_ward_name || r?.village_name); },
    hasNew(r){ return !!(r?.new_province_name || r?.new_ward_name || r?.village_name_new); /* nếu có thôn mới */ },

    // Chuỗi địa bàn cũ: Tỉnh · Huyện · Xã · Thôn
    locOld(r){
      if(!r) return '';
      const parts = [
        r.old_province_name,
        r.old_district_name,
        r.old_ward_name,
        r.village_name,          // thôn/ấp (cũ)
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    // Chuỗi địa bàn mới: Tỉnh · Xã · (Thôn nếu có)
    locNew(r){
      if(!r) return '';
      const parts = [
        r.new_province_name,
        r.new_ward_name,
        r.village_name_new       // nếu có trường thôn/ấp mới, không có thì bỏ
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    disabilityKeys: [
      {key:'dis_VAN_DONG', label:'Vận động'},
      {key:'dis_NGHE_NOI', label:'Nghe/Nói'},
      {key:'dis_NHIN',     label:'Nhìn'},
      {key:'dis_THAN_KINH',label:'Thần kinh'},
      {key:'dis_TRI_TUE',  label:'Trí tuệ'},
      {key:'dis_HOC_TAP',  label:'Học tập'},
      {key:'dis_TU_KY',    label:'Tự kỷ'},
      {key:'dis_KHAC',     label:'Khác'},
    ],
    boot(){},
    openWith(r){ this.item = r; this.open = true; },
    close(){ this.open = false; this.item = null; },
    fullName(r){ if(!r) return ''; return [r.ho_dem||'', r.ten||''].join(' ').trim(); },
    dob(r){
      if(!r) return '';
      const dd = [r.dob_day, r.dob_month, r.dob_year].filter(Boolean).join('/');
      return dd || '—';
    },

    // Hàm xóa tạm đối tượng
    softDelete(id) {
      console.log('softDelete called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID đối tượng: ' + id);
        return;
      }

      const message = 'Bạn có muốn xóa tạm đối tượng?\nĐể phục hồi đối tượng vào Điều tra -> Danh sách xóa tạm.';
      
      if (confirm(message)) {
        console.log('User confirmed, sending request to:', `/doituong-dieutra/${id}/soft-delete`);
        
        // Gửi request xóa tạm
        fetch(`/doituong-dieutra/${id}/soft-delete`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          console.log('Response data:', data);
          if (data.success) {
            alert('Đã xóa tạm đối tượng thành công');
            // Reload trang để cập nhật danh sách
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa tạm đối tượng'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Lỗi kết nối: Không thể xóa tạm đối tượng');
        });
      }
    },
  }
}

/** ========== BULK SELECT ========== */
function bulkSelect(){
  return {
    selectedCount: 0,
    toggleAll(ev){
      const checked = ev.target.checked;
      document.querySelectorAll('input.row-checkbox').forEach(cb => { cb.checked = checked; });
      this.recount();
    },
    onRowToggle(){ this.recount(); },
    recount(){ this.selectedCount = document.querySelectorAll('input.row-checkbox:checked').length; },
    confirmBulkDelete(){
      if (this.selectedCount === 0) {
        alert('Vui lòng chọn ít nhất một đối tượng để xóa tạm.');
        return;
      }
      
      const message = `Bạn chắc chắn muốn xóa tạm ${this.selectedCount} đối tượng đã chọn?\n\nĐể phục hồi các đối tượng vào Điều tra -> Danh sách xóa tạm.`;
      
      if (confirm(message)) {
        // Thêm IDs vào form trước khi submit
        const form = this.$refs.bulkSelForm;
        const selectedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
        const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        // Xóa các input cũ
        form.querySelectorAll('input[name="ids[]"]').forEach(input => input.remove());
        
        // Thêm các input mới
        ids.forEach(id => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = id;
          form.appendChild(input);
        });
        
        console.log('Submitting bulk soft delete with IDs:', ids);
        form.submit();
      }
    },
    confirmBulkHardDelete(){
      if (this.selectedCount === 0) {
        alert('Vui lòng chọn ít nhất một đối tượng để xóa thật.');
        return;
      }
      
      const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT ${this.selectedCount} đối tượng đã chọn?\n\nHành động này sẽ xóa vĩnh viễn các đối tượng khỏi dữ liệu và KHÔNG THỂ PHỤC HỒI!`;
      
      if (confirm(message)) {
        // Thêm IDs vào form trước khi submit
        const form = this.$refs.bulkHardDeleteForm;
        const selectedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
        const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        // Xóa các input cũ
        form.querySelectorAll('input[name="ids[]"]').forEach(input => input.remove());
        
        // Thêm các input mới
        ids.forEach(id => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = id;
          form.appendChild(input);
        });
        
        console.log('Submitting bulk hard delete with IDs:', ids);
        form.submit();
      }
    }
  }
}
</script>
@endpush