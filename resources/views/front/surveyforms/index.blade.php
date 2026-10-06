@extends('layouts.front')
@section('title','Danh sách phiếu điều tra')

@section('content')
<style>
  .th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb}
  .td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}
  [x-cloak]{display:none!important}
</style>

<div x-data="bulkSelect()" class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Danh sách phiếu điều tra (chủ hộ)</h1>
    <div class="flex gap-2">
      @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'create'))
        <a href="{{ route('front.surveyforms.import') }}" class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-slate-700">Nhập Excel</a>
        <a href="{{ route('front.surveyforms.download_template') }}" class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">Tải mẫu</a>
      @endif
       

      {{-- NÚT MỞ MODAL AJAX --}}

      @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'create'))
        <button type="button"
                class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700"
                @click="$store.appModal.showFrom('{{ route('front.surveyforms.modal.create') }}',{title:'Thêm phiếu điều tra'})">
          Thêm phiếu
        </button>
      @endif

    </div>
  </div>

  {{-- BỘ LỌC --}}
  <form method="get" class="mb-3 grid gap-3 md:grid-cols-12">
    @php
      $base = \App\Models\SurveyForm::currentAcademicYear();
      [$y1,$y2] = explode('-', $base);
      $years = [($y1-2).'-'.($y1-1), ($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
    @endphp

    <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        'namePrefix' => 'geo',
        'scheme'     => request('scheme','new'),
        'provinceId' => request('geo.province_id'),
        'districtId' => request('geo.district_id'),
        'wardId'     => request('geo.ward_id'),
        'villageId'  => request('geo.village_id'),
        'showHamlet' => true,
        'withGeo'    => true,
         'showSchool' => false,
        'enableSchool' => false,
        'wrapperClass' => 'grid md:grid-cols-12 gap-3'
      ])
    </div>

    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select name="year" class="w-full rounded-lg border px-3 py-2">
        <option value="all" @selected($year==='all')>— Tất cả —</option>
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
      <label class="block text-xs text-slate-500 mb-1">Tìm chủ hộ (họ tên)</label>
      <input name="q" value="{{ $q }}" class="w-full rounded-lg border px-3 py-2" placeholder="VD: Nguyễn Văn A">
    </div>

    <div class="md:col-span-6 flex items-end gap-2">
      <button class="rounded-lg bg-slate-800 px-3 py-2 text-white hover:bg-slate-900">Lọc</button>
      <a href="{{ route('front.surveyforms.index') }}" class="rounded-lg border px-3 py-2 hover:bg-slate-50">Xoá lọc</a>

      {{-- NÚT XUẤT EXCEL THEO BỘ LỌC HIỆN TẠI --}}
       @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'view'))
        <button type="submit"
                formaction="{{ route('front.surveys.export') }}"
                formmethod="get"
                class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">
          Xuất Excel
        </button>
      @endif
    </div>
  </form>

  {{-- THÔNG BÁO --}}
  @if(session('status'))
    <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">
      {{ session('status') }}
    </div>
  @endif

  {{-- THANH TÁC VỤ --}}
  <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between rounded-xl border bg-slate-50 p-3">
    <div class="text-sm">
      <div class="font-medium">Tổng số phiếu: <span class="tabular-nums">{{ number_format($totalForms) }}</span></div>
      <div class="text-slate-600">
        Hiển thị: <span class="tabular-nums">{{ $rows->firstItem() ?? 0 }}</span>–<span class="tabular-nums">{{ $rows->lastItem() ?? 0 }}</span>
        / trang {{ $rows->currentPage() }}
        <template x-if="selectedCount>0">
          <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-amber-800">Đã chọn: <b x-text="selectedCount"></b></span>
        </template>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      {{-- Export các phiếu đã chọn --}}
      @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'view'))
        <form x-ref="exportManyForm" method="POST" action="{{ route('front.surveyforms.exportpdt-many') }}" class="hidden">
          @csrf
        </form>
        <button type="button"
                class="rounded-lg bg-emerald-700 px-3 py-2 text-white hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="selectedCount===0"
                x-text="selectedCount>0 ? `Xuất Excel (${selectedCount})` : 'Xuất Excel (đã chọn)'"
                @click="submitExportMany()">
        </button>
      @endif

      {{-- Form xóa các đã chọn --}}
      @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
        <form x-ref="bulkSelForm" id="bulkSelForm" method="POST" action="{{ route('front.surveyforms.bulk_delete') }}">
          @csrf
          <button type="button"
                  class="rounded-lg bg-amber-700 px-3 py-2 text-white hover:bg-amber-800 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="selectedCount===0"
                  x-text="selectedCount>0 ? `Xóa các phiếu đã chọn (${selectedCount})` : 'Xóa các phiếu đã chọn'"
                  @click="$store.appConfirm.show({
                    title:'Xóa các phiếu đã chọn',
                    message:`Bạn chắc chắn muốn xóa ${selectedCount} phiếu đã chọn?`,
                    okText:'Xóa', cancelText:'Hủy',
                    onOk: () => $refs.bulkSelForm.submit()
                  })">
          </button>
        </form>
      @endif

      {{-- Xóa hết theo bộ lọc --}}
      {{-- @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
        <form method="POST" action="{{ route('front.surveyforms.bulk_delete') }}"
              data-confirm="Xóa MỀM TẤT CẢ các phiếu ĐANG LỌC?"
              data-title="Xóa theo bộ lọc" data-ok="Xóa" data-cancel="Hủy">
          @csrf
          <input type="hidden" name="scope" value="filtered">
          <input type="hidden" name="year" value="{{ $year }}">
          <input type="hidden" name="so_phieu" value="{{ $soPhieu }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="scheme" value="{{ request('scheme','old') }}">
          <input type="hidden" name="geo[province_id]" value="{{ request('geo.province_id') }}">
          <input type="hidden" name="geo[district_id]" value="{{ request('geo.district_id') }}">
          <input type="hidden" name="geo[ward_id]"     value="{{ request('geo.ward_id') }}">
          <input type="hidden" name="geo[village_id]"  value="{{ request('geo.village_id') }}">
          <button class="rounded-lg bg-amber-600 px-3 py-2 text-white hover:bg-amber-700">Xóa hết (theo bộ lọc)</button>
        </form>
      @endif --}}

      {{-- Xóa thôn/ấp đã lọc --}}
      @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete') && request('geo.village_id'))
        <form method="POST" action="{{ route('front.surveyforms.bulk_delete') }}"
              data-confirm="Bạn chắc chắn muốn xóa MỀM TẤT CẢ các phiếu thuộc Thôn/Ấp đã lọc?"
              data-title="Xóa thôn/ấp đã lọc" data-ok="Xóa" data-cancel="Hủy">
          @csrf
          <input type="hidden" name="scope" value="village">
          <input type="hidden" name="year" value="{{ $year }}">
          <input type="hidden" name="so_phieu" value="{{ $soPhieu }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="scheme" value="{{ request('scheme','new') }}">
          <input type="hidden" name="geo[province_id]" value="{{ request('geo.province_id') }}">
          <input type="hidden" name="geo[district_id]" value="{{ request('geo.district_id') }}">
          <input type="hidden" name="geo[ward_id]"     value="{{ request('geo.ward_id') }}">
          <input type="hidden" name="geo[village_id]"  value="{{ request('geo.village_id') }}">
          <button class="rounded-lg bg-orange-600 px-3 py-2 text-white hover:bg-orange-700">Xóa thôn/ấp đã lọc</button>
        </form>
      @endif

      {{-- Xóa toàn bộ --}}
      @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
        <form method="POST" action="{{ route('front.surveyforms.bulk_delete') }}"
              data-confirm="Xóa MỀM TOÀN BỘ phiếu?"
              data-title="Xóa toàn bộ" data-ok="Xóa" data-cancel="Hủy">
          @csrf
          <input type="hidden" name="scope" value="all">
          <button class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700">Xóa toàn bộ</button>
        </form>
      @endif
    </div>
  </div>

  {{-- BẢNG --}}
  <div class="-mx-4 overflow-x-auto" style="min-height:300px">
    <table class="min-w-[1100px]">
      <thead>
        <tr class="bg-slate-100">
          <th class="th sticky left-0 z-30 bg-slate-100 w-10">
            <input type="checkbox" @change="toggleAll($event)" aria-label="Chọn tất cả">
          </th>
          <th class="th sticky left-10 z-30 bg-slate-100 w-10"></th>
          <th class="th sticky left-20 z-20 bg-slate-100 w-12">#</th>
          <th class="th">Số phiếu</th>
          <th class="th">Chủ hộ</th>
          <th class="th text-right">Tổng TV</th>
          <th class="th">Giới tính</th>
          <th class="th">Ngày sinh</th>
          <th class="th">Điện thoại</th>
          {{-- <th class="th">Ngày điều tra</th> --}}
          <th class="th">Loại cư trú</th>
          {{-- <th class="th">Tình trạng cư trú</th> --}}
          {{-- <th class="th">Địa bàn (cũ)</th> --}}
          <th class="th">Địa bàn</th>
          <th class="th">Địa chỉ (liên hệ)</th>
          <th class="th"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $i => $r)
          <tr class="hover:bg-slate-50">
            <td class="td sticky left-0 z-20 bg-white w-10">
              <input type="checkbox" class="row-checkbox" name="ids[]" value="{{ $r['id'] }}"
                     @change="onRowToggle()" aria-label="Chọn phiếu {{ $r['so_phieu'] }}" form="bulkSelForm">
            </td>

            <td class="td sticky left-10 z-20 bg-white w-10">
              <div x-data="{ open:false }" class="relative" >
                <button type="button"
                        class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
                        @click="open=!open" aria-label="Hành động">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor">
                    <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/>
                  </svg>
                </button>

                <div x-cloak x-show="open" @click.outside="open=false"
                     class="absolute left-full top-0 ml-2 w-48 rounded-xl border bg-white shadow-xl z-50 overflow-hidden">



                  {{-- @php
                    $membersUrl = route('front.surveyforms.modal.members.index', ['survey' => $r['id']]);
                    $qs = http_build_query(request()->only([
                      'year','scheme','geo.province_id','geo.district_id','geo.ward_id','geo.village_id'
                    ]));
                    if ($qs) $membersUrl .= '?' . $qs;
                    // dd($qs);
                  @endphp --}}
 

                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'create'))
                    <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                            @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.members.create',$r['id']) }}',{title:'Thêm thành viên'})">
                      Thêm thành viên

                  </button>
                   @endif
                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'view'))
                    <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                        @click="
                          const y = document.querySelector('select[name=year]')?.value || 'all';
                          const url = '{{ route('front.surveyforms.modal.members.index', ['survey' => $r['id']]) }}' + '?year=' + encodeURIComponent(y);
                          $store.appModal.showFrom(url, {title:'Danh sách thành viên'});
                        ">
                  Xem thành viên
                </button>
                  @endif

                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'view'))
                    <a href="{{ route('front.surveyforms.exportpdt', $r['id']) }}"
                       class="block w-full text-left px-3 py-2 hover:bg-slate-50">Xuất Excel</a>
                  @endif

                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'edit'))
                    <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                            @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.edit',$r['id']) }}',{title:'Điều chỉnh phiếu'})">
                      Điều chỉnh phiếu
                    </button>
                  @endif

                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'create'))
                    <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                            @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.split',$r['id']) }}',{title:'Tách hộ'})">
                      Tách hộ
                    </button>
                  @endif
                  
                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'create'))
                    <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                            data-merge-modal
                            @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.merge',$r['id']) }}',{title:'Ghép hộ'})">
                      Ghép hộ
                    </button>
                  @endif

                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'create'))
                    <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                            @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.move',$r['id']) }}',{title:'Chuyển hộ'})">
                      Chuyển hộ
                    </button>
                  @endif

                  @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
                    <form method="POST" action="{{ route('front.surveyforms.destroy',$r['id']) }}"
                          data-confirm="Xóa mềm phiếu này và chi tiết liên quan?"
                          data-title="Xóa phiếu" data-ok="Xóa" data-cancel="Hủy">
                      @csrf @method('DELETE')
                      <button type="submit" class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50">
                        Xóa phiếu
                      </button>
                    </form>
                  @endif
                </div>
              </div>
            </td>

            <td class="td sticky left-20 z-10 bg-white w-12 tabular-nums">{{ $rows->firstItem() + $i }}</td>
            <td class="td">{{ $r['so_phieu'] }}</td>
            <td class="td font-medium">{{ $r['chu_ho'] ?: '—' }}</td>
            <td class="td text-right tabular-nums">{{ $r['member_count'] }}</td>
            <td class="td">{{ $r['head_gender'] ?? '—' }}</td>
            <td class="td tabular-nums">{{ $r['head_dob'] ?? '—' }}</td>
            <td class="td">{{ $r['head_phone'] ?: '—' }}</td>
            {{-- <td class="td">{{ $r['surveyed_at'] ?: '—' }}</td> --}}
            <td class="td">{{ $r['res_type'] ?: '—' }}</td>
            {{-- <td class="td">{{ $r['res_status'] ?: '—' }}</td> --}}
            {{-- <td class="td">{{ $r['address_old'] ?: '—' }}</td> --}}
            <td class="td">{{ $r['address_new'] ?: '—' }}</td>
            <td class="td">{{ $r['contact_addr'] ?: '—' }}</td>
            <td class="td"></td>
          </tr>
        @empty
          <tr><td class="td" colspan="13">Chưa có dữ liệu.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
</div>

{{-- MODAL AJAX DÙNG CHUNG (dùng setHtml để compile Alpine trong HTML tải về) --}}
<div x-data
     class="fixed inset-0 z-[70] grid place-items-center bg-black/40"
     x-show="$store.appModal.open"
     x-cloak
 
    @click.self.stop="$store.appModal.onBackdrop()"
     @keydown.escape.window="$store.appModal.onEsc()"
     role="dialog" aria-modal="true">
  <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white shadow-2xl"
       @click.stop>
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 class="font-medium" x-text="$store.appModal.title"></h3>
      <button class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
              @click="$store.appModal.close()">✕</button>
    </div>
    <div class="p-4">
      <template x-if="$store.appModal.loading">
        <div class="text-sm text-slate-500">Đang tải…</div>
      </template>
      <div id="app-modal-body"></div>
    </div>
  </div>
</div>

{{-- CONFIRM MODAL --}}
<template x-if="$store.appConfirm.open">
  <div class="fixed inset-0 z-[80] grid place-items-center bg-black/40" x-cloak>
    <div class="w-[min(520px,92vw)] rounded-2xl bg-white shadow-2xl">
      <div class="px-4 py-3 border-b">
        <h3 class="font-medium" x-text="$store.appConfirm.title || 'Xác nhận'"></h3>
      </div>
      <div class="p-4 text-slate-700" x-text="$store.appConfirm.message"></div>
      <div class="px-4 pb-4 flex justify-end gap-2">
        <button class="rounded-lg border px-3 py-2 hover:bg-slate-50"
                @click="$store.appConfirm.cancel()"
                x-text="$store.appConfirm.cancelText || 'Hủy'"></button>
        <button class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700"
                @click="$store.appConfirm.ok()"
                x-text="$store.appConfirm.okText || 'Đồng ý'"></button>
      </div>
    </div>
  </div>
</template>
<div x-data
     x-show="$store.dl.open"
     x-cloak
     class="fixed inset-0 z-[90] grid place-items-center bg-black/40">
  <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-xl">
    <svg class="h-6 w-6 animate-spin text-cyan-600" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <span class="text-sm text-slate-700" x-text="$store.dl.message || 'Đang xuất Excel…'"></span>
  </div>
</div>

@endsection

@push('scripts')

<script>
// Vanilla JavaScript cho modal ghép hộ - tránh xung đột Alpine.js
class MergeHouseholdModal {
  constructor(config) {
    this.config = config;
    this.results = [];
      this.otherMembers = [];
      this.mergedMembers = [];
      this.roles = {};
    this.init();
  }

  init() {
    console.log('MergeHouseholdModal initialized');
    
    // Setup event listeners
    const searchInput = document.getElementById('merge-search-input');
    const surveySelect = document.getElementById('merge-survey-select');
    const submitBtn = document.getElementById('merge-submit-btn');

    console.log('Elements found:', {
      searchInput: !!searchInput,
      surveySelect: !!surveySelect,
      submitBtn: !!submitBtn
    });

    if (searchInput) {
      let searchTimeout;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => this.search(e.target.value), 300);
      });
    }

    if (surveySelect) {
      surveySelect.addEventListener('change', (e) => {
        this.loadMembers(e.target.value);
      });
    }

    if (submitBtn) {
      submitBtn.addEventListener('click', () => this.submit());
    }

    // Address section removed - using default address from current survey
  }


  async search(query) {
    if (!query || query.trim().length < 1) {
      this.updateSurveySelect([]);
      return;
    }

    try {
      const url = new URL(this.config.searchUrl, location.origin);
      url.searchParams.set('q', query.trim());
      // Thêm năm học vào URL nếu có
      if (this.config.academicYear) {
        url.searchParams.set('academic_year', this.config.academicYear);
      }
      const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
      const data = await response.json();
      this.results = data.items || [];
      this.updateSurveySelect(this.results);
    } catch (error) {
      console.error('Search error:', error);
      this.updateSurveySelect([]);
    }
  }

  updateSurveySelect(results) {
    const select = document.getElementById('merge-survey-select');
    if (!select) return;

    // Clear existing options except first
    select.innerHTML = '<option value="">— Chọn phiếu ↵</option>';
    
    results.forEach(item => {
      const option = document.createElement('option');
      option.value = item.id;
      option.textContent = this.formatOption(item);
      select.appendChild(option);
    });
  }

  formatOption(item) {
    const so = item.so_phieu || '';
    const name = (item.head_name || '—').trim();
    const tv = Number.isFinite(item.members_count) ? item.members_count : 0;
    return `${so} — ${name} (${tv} TV)`;
  }

  async loadMembers(surveyId) {
    if (!surveyId) {
      this.hideSections();
      return;
    }

    try {
      const url = this.config.membersUrlTmpl.replace('OTHER_ID', surveyId);
      // Thêm năm học vào URL nếu có
      const academicYear = this.config.academicYear || '';
      const urlWithYear = academicYear ? `${url}?academic_year=${encodeURIComponent(academicYear)}` : url;
      console.log('Loading members from:', urlWithYear);
      
      const response = await fetch(urlWithYear, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
      console.log('Response status:', response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      console.log('Members data:', data);
      
      if (data.error) {
        alert('Lỗi: ' + data.error);
        this.hideSections();
        return;
      }
      
      this.otherMembers = data.items || [];
      // Ghép tất cả thành viên: hộ ban đầu + hộ được chọn
      this.mergedMembers = [...(this.config.currentMembers || []), ...this.otherMembers];
      
      this.updateSurveyInfo(surveyId);
      this.updateMembersSection();
      this.updateAddressSection();
      this.updateSubmitButton();
    } catch (error) {
      console.error('Load members error:', error);
      alert('Có lỗi xảy ra khi tải danh sách thành viên: ' + error.message);
      this.hideSections();
    }
  }

  updateSurveyInfo(surveyId) {
    const selectedSurvey = this.results.find(r => r.id == surveyId);
    if (selectedSurvey) {
      document.getElementById('merge-so-phieu').textContent = selectedSurvey.so_phieu || '—';
      document.getElementById('merge-chu-ho').textContent = selectedSurvey.head_name || '—';
      document.getElementById('merge-so-thanh-vien').textContent = selectedSurvey.members_count || 0;
      document.getElementById('merge-survey-info').style.display = 'block';
    }
  }

  updateMembersSection() {
    const section = document.getElementById('merge-members-section');
    const headSelect = document.getElementById('merge-new-head-select');
    const tableBody = document.getElementById('merge-members-table');

    if (!section || !headSelect || !tableBody) return;

    // Update head select - mặc định chọn chủ hộ của hộ ban đầu
    headSelect.innerHTML = '<option value="">— Chọn chủ hộ —</option>';
    
    // Thêm chủ hộ của hộ ban đầu làm option đầu tiên (mặc định)
    const currentHead = this.config.currentMembers?.find(m => m.role === 'Chủ hộ' || m.role === 'chủ hộ');
    if (currentHead) {
      const defaultOption = document.createElement('option');
      defaultOption.value = currentHead.person_id;
      defaultOption.textContent = `${currentHead.name} (Chủ hộ hiện tại)`;
      defaultOption.selected = true; // Mặc định chọn
      headSelect.appendChild(defaultOption);
    }
    
    // Thêm các thành viên khác
    this.mergedMembers.forEach(member => {
      // Bỏ qua chủ hộ hiện tại nếu đã thêm ở trên
      if (currentHead && member.person_id == currentHead.person_id) return;
      
      const option = document.createElement('option');
      option.value = member.person_id;
      option.textContent = member.name;
      headSelect.appendChild(option);
    });

    // Update members table
    tableBody.innerHTML = '';
    
    // Hiển thị thành viên hộ ban đầu
    const currentMembers = this.config.currentMembers || [];
    if (currentMembers.length > 0) {
      const headerRow = document.createElement('tr');
      headerRow.className = 'bg-blue-50 font-medium';
      headerRow.innerHTML = '<td colspan="2" class="px-2 py-2 text-blue-700">Thành viên hộ ban đầu</td>';
      tableBody.appendChild(headerRow);
      
      currentMembers.forEach(member => {
        const row = document.createElement('tr');
        row.className = 'border-t bg-blue-25';
        
        row.innerHTML = `
          <td class="px-2 py-1">${member.name}</td>
          <td class="px-2 py-1 text-center">
            <select class="rounded border px-2 py-1" name="roles[${member.person_id}]">
              <option value="">— Chọn quan hệ —</option>
              ${(this.config.relations || []).map(r => `<option value="${r.name}">${r.name}</option>`).join('')}
            </select>
          </td>
        `;
        
        tableBody.appendChild(row);
      });
    }
    
    // Hiển thị thành viên hộ được chọn
    if (this.otherMembers.length > 0) {
      const headerRow = document.createElement('tr');
      headerRow.className = 'bg-green-50 font-medium';
      headerRow.innerHTML = '<td colspan="2" class="px-2 py-2 text-green-700">Thành viên hộ được chọn (sẽ được dọn vào hộ ban đầu)</td>';
      tableBody.appendChild(headerRow);
      
      this.otherMembers.forEach(member => {
        const row = document.createElement('tr');
        row.className = 'border-t bg-green-25';
        
        row.innerHTML = `
          <td class="px-2 py-1">${member.name}</td>
          <td class="px-2 py-1 text-center">
            <select class="rounded border px-2 py-1" name="roles[${member.person_id}]">
              <option value="">— Chọn quan hệ —</option>
              ${(this.config.relations || []).map(r => `<option value="${r.name}">${r.name}</option>`).join('')}
            </select>
          </td>
        `;
        
        tableBody.appendChild(row);
      });
    }

    // Add event listener for head selection
    headSelect.addEventListener('change', (e) => {
      this.updateMemberRoles(e.target.value);
    });

    // Add event listeners for role selects
    const roleSelects = tableBody.querySelectorAll('select[name^="roles["]');
    roleSelects.forEach(select => {
      select.addEventListener('change', (e) => {
        const personId = e.target.name.match(/roles\[(\d+)\]/)?.[1];
        if (personId) {
          this.roles[personId] = e.target.value;
          this.updateSubmitButton();
        }
      });
    });

    // Tự động cập nhật roles nếu đã có chủ hộ mặc định
    if (currentHead) {
      this.updateMemberRoles(currentHead.person_id);
    }

    section.style.display = 'block';
  }

  updateMemberRoles(newHeadId) {
    const roleSelects = document.querySelectorAll('select[name^="roles["]');
    roleSelects.forEach(select => {
      const personId = select.name.match(/roles\[(\d+)\]/)?.[1];
      if (personId == newHeadId) {
        select.disabled = true;
        select.innerHTML = '<option value="Chủ hộ" selected>Chủ hộ</option>';
        this.roles[personId] = 'Chủ hộ';
      } else {
        select.disabled = false;
        if (!select.querySelector('option[value=""]')) {
          const defaultOption = document.createElement('option');
          defaultOption.value = '';
          defaultOption.textContent = '— Chọn quan hệ —';
          select.insertBefore(defaultOption, select.firstChild);
        }
      }
    });
    
    this.updateSubmitButton();
  }

  createRoleSelect(personId) {
    const select = document.createElement('select');
    select.className = 'rounded border px-2 py-1';
    select.name = `roles[${personId}]`;
    
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = '— Chọn quan hệ —';
    select.appendChild(defaultOption);

    (this.config.relations || []).forEach(relation => {
      const option = document.createElement('option');
      option.value = relation.name;
      option.textContent = relation.name;
      select.appendChild(option);
    });

    select.addEventListener('change', () => {
      this.roles[personId] = select.value;
      this.updateSubmitButton();
    });

    return select.outerHTML;
  }

  updateAddressSection() {
    // Address section removed - using default address from current survey
  }

  hideSections() {
    const sections = ['merge-survey-info', 'merge-members-section'];
    sections.forEach(id => {
      const element = document.getElementById(id);
      if (element) element.style.display = 'none';
    });
  }

  updateSubmitButton() {
    const submitBtn = document.getElementById('merge-submit-btn');
    if (!submitBtn) return;

    const canSubmit = this.canSubmit();
    submitBtn.disabled = !canSubmit;
  }

  canSubmit() {
    const surveySelect = document.getElementById('merge-survey-select');
    const headSelect = document.getElementById('merge-new-head-select');

    console.log('canSubmit check:', {
      surveySelect: surveySelect?.value,
      headSelect: headSelect?.value,
      mergedMembers: this.mergedMembers.length,
      roles: this.roles
    });

    if (!surveySelect?.value || !headSelect?.value) {
      console.log('canSubmit: false - missing required fields');
      return false;
    }

    // Check all non-head members have roles
    for (const member of this.mergedMembers) {
      if (member.person_id != headSelect.value) {
        if (!this.roles[member.person_id] || this.roles[member.person_id].trim() === '') {
          console.log('canSubmit: false - missing role for member', member.person_id);
          return false;
        }
      }
    }

    console.log('canSubmit: true');
    return true;
  }

  async submit() {
    if (!this.canSubmit()) {
      console.log('Cannot submit - validation failed');
      return;
    }

    const surveySelect = document.getElementById('merge-survey-select');
    const headSelect = document.getElementById('merge-new-head-select');

    // Validate required fields
    if (!surveySelect?.value || !headSelect?.value) {
      alert('Vui lòng chọn phiếu và chủ hộ');
      return;
    }

    // Xác nhận trước khi ghép hộ
    const confirmMessage = `Bạn có chắc chắn muốn ghép hộ?\n\n` +
      `- Hộ được chọn sẽ bị xóa\n` +
      `- Tất cả thành viên sẽ được dọn vào hộ ban đầu\n` +
      `- Chủ hộ mới: ${headSelect.options[headSelect.selectedIndex]?.textContent || 'Chưa chọn'}`;
    
    if (!confirm(confirmMessage)) {
      return;
    }

    // Build roles object from form data
    const roles = {};
    const roleSelects = document.querySelectorAll('select[name^="roles["]');
    roleSelects.forEach(select => {
      const personId = select.name.match(/roles\[(\d+)\]/)?.[1];
      if (personId && select.value) {
        roles[personId] = select.value;
      }
    });

    const payload = {
      other_survey_id: Number(surveySelect.value),
      new_head_id: Number(headSelect.value),
      roles: roles,
      address: {
        new_province_id: this.config.defaultAddress?.new_province_id || null,
        new_ward_id: this.config.defaultAddress?.new_ward_id || null,
        village_id: this.config.defaultAddress?.village_id || null,
        addressline: this.config.defaultAddress?.addressline || null
      },
      academic_year: this.config.academicYear || null
    };

    try {
      console.log('Submitting merge with payload:', payload);
      
      const response = await fetch(this.config.postUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      console.log('Response status:', response.status);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error('Response error:', errorText);
        throw new Error(`HTTP ${response.status}: ${response.statusText} - ${errorText}`);
      }

      const data = await response.json();
      console.log('Response data:', data);
      
      if (data.success || data.ok) {
        alert('Ghép hộ thành công! Tất cả thành viên đã được dọn vào hộ ban đầu.');
        if (window.Alpine?.store('appModal')) {
          window.Alpine.store('appModal').close();
        }
        if (data.redirect) {
          window.location.href = data.redirect;
        } else {
          window.location.reload();
        }
      } else {
        alert('Có lỗi xảy ra khi ghép hộ: ' + (data.message || 'Lỗi không xác định'));
      }
    } catch (error) {
      console.error('Submit error:', error);
      console.error('Error details:', {
        message: error.message,
        stack: error.stack,
        payload: payload
      });
      alert('Có lỗi xảy ra khi ghép hộ: ' + error.message);
    }
  }
}

// MoveHouseholdModal class
class MoveHouseholdModal {
    constructor(config) {
        this.config = config;
        this.init();
    }

    init() {
        this.setupEventListeners();
        
        // Load thôn/xóm ngay khi modal mở
        const wardSelect = document.getElementById('move-ward-select');
        if (wardSelect.value) {
            this.loadVillages(wardSelect.value);
        }
        
        // Validation ngay sau khi setup
        setTimeout(() => {
            this.updateSubmitButton();
        }, 100);
    }
    
    setupEventListeners() {
        // Lắng nghe thay đổi xã/phường
        const wardSelect = document.getElementById('move-ward-select');
        if (wardSelect) {
            wardSelect.addEventListener('change', () => {
                this.loadVillages(wardSelect.value);
            });
        }

        // Lắng nghe thay đổi form
        const formInputs = ['move-new-so-phieu', 'move-new-head-name', 'move-ward-select', 'move-village-select'];
        formInputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', () => {
                    this.updateSubmitButton();
                });
                element.addEventListener('change', () => {
                    this.updateSubmitButton();
                });
            }
        });

        // Nút submit
        const submitBtn = document.getElementById('move-submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.submit();
            });
        }
    }

    async loadVillages(wardId) {
        const villageSelect = document.getElementById('move-village-select');
        
        if (!wardId) {
            villageSelect.innerHTML = '<option value="">Chọn thôn/xóm</option>';
            villageSelect.disabled = true;
            this.updateSubmitButton();
            return;
        }

        // Hiển thị loading
        villageSelect.innerHTML = '<option value="">Đang tải...</option>';
        villageSelect.disabled = false;

        try {
            const url = new URL(this.config.villagesUrl, window.location.origin);
            url.searchParams.set('ward_id', wardId);
            
            const response = await fetch(url, {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            villageSelect.innerHTML = '<option value="">Chọn thôn/xóm</option>';
            
            if (data.items && data.items.length > 0) {
                data.items.forEach(village => {
                    const option = document.createElement('option');
                    option.value = village.id;
                    option.textContent = village.name;
                    villageSelect.appendChild(option);
                });
                villageSelect.disabled = false;
            } else {
                villageSelect.innerHTML = '<option value="">Không có thôn/xóm</option>';
                villageSelect.disabled = false;
            }
            
            this.updateSubmitButton();
            
        } catch (error) {
            villageSelect.innerHTML = '<option value="">Lỗi tải danh sách</option>';
            villageSelect.disabled = false;
            this.updateSubmitButton();
            alert('Không thể tải danh sách thôn/xóm: ' + error.message);
        }
    }

    updateSubmitButton() {
        const canSubmit = this.validateForm();
        const submitBtn = document.getElementById('move-submit-btn');
        
        if (submitBtn) {
            submitBtn.disabled = !canSubmit;
            if (canSubmit) {
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('hover:bg-cyan-700');
            } else {
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.remove('hover:bg-cyan-700');
            }
        }
    }

    async submit() {
        const submitBtn = document.getElementById('move-submit-btn');
        if (!submitBtn) return;
        
        const originalText = submitBtn.textContent;
        
        // Kiểm tra validation trước khi submit
        if (!this.validateForm()) {
            alert('Vui lòng điền đầy đủ thông tin bắt buộc');
            return;
        }
        
        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang xử lý...';

            // Lấy danh sách thành viên từ server (tất cả thành viên năm hiện tại)
            let members = [];
            try {
                // Lấy members từ data attribute hoặc từ server
                const membersData = document.querySelector('#move-household-modal')?.dataset?.members;
                if (membersData) {
                    members = JSON.parse(membersData);
                }
            } catch (error) {
                members = [];
            }

            const payload = {
                new_so_phieu: document.getElementById('move-new-so-phieu').value.trim(),
                new_head_name: document.getElementById('move-new-head-name').value.trim(),
                new_village_id: parseInt(document.getElementById('move-village-select').value),
                academic_year: this.config.academicYear,
                members: members
            };

            // Gửi request
            const response = await fetch(this.config.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message || 'Chuyển hộ thành công');
                if (result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    location.reload();
                }
            } else {
                alert(result.message || 'Có lỗi xảy ra khi chuyển hộ');
            }

        } catch (error) {
            alert('Có lỗi xảy ra khi chuyển hộ: ' + error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    validateForm() {
        const soPhieu = document.getElementById('move-new-so-phieu')?.value?.trim();
        const headName = document.getElementById('move-new-head-name')?.value?.trim();
        const wardId = document.getElementById('move-ward-select')?.value;
        const villageId = document.getElementById('move-village-select')?.value;
        
        return soPhieu && headName && wardId && villageId;
    }
}

// Initialize modal when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  // This will be called when the modal content is loaded
  if (document.getElementById('merge-household-modal')) {
    // Get config from data attributes or global variables
    const modalElement = document.getElementById('merge-household-modal');
    if (modalElement.dataset.config) {
      try {
        const config = JSON.parse(modalElement.dataset.config);
        new MergeHouseholdModal(config);
      } catch (error) {
        console.error('Error parsing config:', error);
        console.log('Config string:', modalElement.dataset.config);
      }
    }
  }
});

// Also initialize when modal is shown (for dynamic content)
document.addEventListener('click', function(e) {
  if (e.target.matches('[data-merge-modal]') || e.target.closest('[data-merge-modal]')) {
    setTimeout(() => {
      const modalElement = document.getElementById('merge-household-modal');
      if (modalElement && !modalElement.dataset.initialized) {
        if (modalElement.dataset.config) {
          try {
            const config = JSON.parse(modalElement.dataset.config);
            new MergeHouseholdModal(config);
            modalElement.dataset.initialized = 'true';
          } catch (error) {
            console.error('Error parsing config on click:', error);
            console.log('Config string:', modalElement.dataset.config);
          }
        }
      }
    }, 100);
  }
});

// Initialize when modal content is loaded via AJAX
document.addEventListener('DOMNodeInserted', function(e) {
  if (e.target.id === 'merge-household-modal' || e.target.querySelector('#merge-household-modal')) {
    setTimeout(() => {
      const modalElement = document.getElementById('merge-household-modal');
      if (modalElement && !modalElement.dataset.initialized) {
        if (modalElement.dataset.config) {
          try {
            const config = JSON.parse(modalElement.dataset.config);
            new MergeHouseholdModal(config);
            modalElement.dataset.initialized = 'true';
          } catch (error) {
            console.error('Error parsing config on DOMNodeInserted:', error);
            console.log('Config string:', modalElement.dataset.config);
          }
        }
      }
    }, 200);
  }
});

// Fallback: Check every 500ms if modal is visible and not initialized
setInterval(() => {
  const modalElement = document.getElementById('merge-household-modal');
  if (modalElement && modalElement.offsetParent !== null && !modalElement.dataset.initialized) {
    if (modalElement.dataset.config) {
      try {
        const config = JSON.parse(modalElement.dataset.config);
        new MergeHouseholdModal(config);
        modalElement.dataset.initialized = 'true';
      } catch (error) {
        console.error('Error parsing config in interval:', error);
        console.log('Config string:', modalElement.dataset.config);
      }
    }
  }
}, 500);
</script>

<script>
const debounce = (fn, ms=400)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); } };

function splitHousehold(opts){
  return {
    academicYear: opts.academicYear || '',
    checkUrl: opts.checkUrl,
    postUrl:  opts.postUrl,

    members: opts.members || [],
    relations: opts.relations || [],
    n: 1,
    forms: [],
    assign: {},
    roles: {},
    dupCodes: {},
    checking:false,

    // <-- tạo placeholder để có thể gọi an toàn
    debouncedCheck: () => {},

    init(){
      // KHỞI TẠO debounce Ở ĐÂY và bind(this)
      this.debouncedCheck = debounce(this.checkCodes.bind(this), 400);
      
      // Đảm bảo data được khởi tạo đúng cách
      if (!this.members) this.members = [];
      if (!this.assign) this.assign = {};
      if (!this.roles) this.roles = {};
      if (!this.forms) this.forms = [];
      
      this.applyN();
    },

    ensureRoleBucket(idx){ if (!this.roles[idx]) this.roles[idx] = {}; },

    applyN(){
      const cur = this.forms.length;
      if (this.n > cur) {
        for (let i=cur;i<this.n;i++) this.forms.push({so_phieu:'', head_id:null});
      } else if (this.n < cur) {
        this.forms = this.forms.slice(0, this.n);
        Object.keys(this.assign).forEach(pid=>{
          if (this.assign[pid] >= this.n) this.assign[pid] = '';
        });
      }
      for (let i=0;i<this.n;i++) this.ensureRoleBucket(i);
      // gọi sau khi debouncedCheck đã gán trong init()
      this.debouncedCheck();
    },

  membersIn(idx){
    // Defensive programming: kiểm tra data trước khi xử lý
    if (!this.members || !Array.isArray(this.members)) {
      console.warn('members is not an array:', this.members);
      return [];
    }
    if (!this.assign || typeof this.assign !== 'object') {
      console.warn('assign is not an object:', this.assign);
      return [];
    }
    if (idx === undefined || idx === null) {
      console.warn('idx is undefined:', idx);
      return [];
    }
    
    return this.members.filter(m => {
      if (!m || m.person_id === undefined || m.person_id === null) {
        return false;
      }
      const v = this.assign[m.person_id];
      return v !== '' && v != null && Number(v) === Number(idx);
    });
  },

    onAssignChange(pid){
      if (!pid || pid === undefined || pid === null) {
        console.warn('onAssignChange called with invalid pid:', pid);
        return;
      }
      
      const prevIdx = Object.prototype.hasOwnProperty.call(this.assign, pid) ? this.assign[pid] : '';
      if (prevIdx !== '' && this.forms[prevIdx] && Number(this.forms[prevIdx].head_id) === Number(pid)) {
        this.forms[prevIdx].head_id = null;
      }
      if (prevIdx !== '' && this.roles[prevIdx]) { delete this.roles[prevIdx][pid]; }

      const newIdx = this.assign[pid];
      if (newIdx !== '') this.ensureRoleBucket(newIdx);
    },

    onHeadChange(idx){
      if (idx === undefined || idx === null || !this.forms[idx]) {
        console.warn('onHeadChange called with invalid idx:', idx);
        return;
      }
      
      this.ensureRoleBucket(idx);
      const head = Number(this.forms[idx].head_id || 0);
      if (head) this.roles[idx][head] = 'Chủ hộ';
      Object.keys(this.roles[idx]).forEach(pidStr => {
        const pid = Number(pidStr);
        if (pid !== head && this.roles[idx][pid] === 'Chủ hộ') {
          delete this.roles[idx][pid];
        }
      });
    },

    async checkCodes(){
      const codes = this.forms.map(f => (f.so_phieu||'').trim()).filter(Boolean);
      if (!codes.length) { this.dupCodes={}; return; }
      this.checking = true;
      try {
        const u = new URL(this.checkUrl, location.origin);
        codes.forEach(c => u.searchParams.append('codes[]', c));
        if (this.academicYear) u.searchParams.set('year', this.academicYear);
        const res = await fetch(u, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        const j = await res.json();
        this.dupCodes = j.results || {};
      } catch(e){ console.error(e); }
      finally { this.checking = false; }
    },

    get hasEmptyCode(){ return this.forms.some(f => !(f.so_phieu||'').trim()); },
    get hasDupInForm(){
      const codes = this.forms.map(f => (f.so_phieu||'').trim()).filter(Boolean);
      return new Set(codes).size !== codes.length;
    },
    get hasDupInDb(){ return Object.values(this.dupCodes).some(v => v === true); },
    get headInvalid(){
      return this.forms.some((f,idx)=>{
        if (!f || !f.head_id) return true;
        const members = this.membersIn(idx);
        if (!Array.isArray(members)) return true;
        const ids = members.map(m=>Number(m.person_id));
        return ids.indexOf(Number(f.head_id)) === -1;
      });
    },
    get roleInvalid(){
      return this.forms.some((f,idx)=>{
        if (!f) return true;
        const bucket = this.roles[idx] || {};
        const members = this.membersIn(idx);
        if (!Array.isArray(members)) return true;
        return members.some(m => {
          if (!m || !m.person_id) return true;
          const isHead = Number(m.person_id) === Number(f.head_id);
          if (isHead) return false;
          return !(bucket[m.person_id] || '').trim();
        });
      });
    },
    get emptyMembers(){ 
      return this.forms.some((f,idx)=> {
        if (!f) return true;
        const members = this.membersIn(idx);
        return !Array.isArray(members) || members.length === 0;
      }); 
    },

    get canSubmit(){
      return !this.hasEmptyCode && !this.hasDupInForm && !this.hasDupInDb
        && !this.headInvalid && !this.roleInvalid && !this.emptyMembers;
    },

    async submit(){
      if (!this.canSubmit) return;
      const payload = {
        academic_year: this.academicYear || null,
        new_forms: this.forms.map((f,idx)=>({
          so_phieu: (f.so_phieu||'').trim(),
          head_id: Number(f.head_id),
          members: this.membersIn(idx).map(m => ({
            person_id: Number(m.person_id),
            role: Number(m.person_id) === Number(f.head_id)
              ? 'Chủ hộ'
              : (this.roles[idx]?.[m.person_id] || '')
          })),
        })),
      };

      const res = await fetch(this.postUrl, {
        method: 'POST',
        headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
        body: toFormData(payload),
      });
      const j = await res.json();
      if (j.ok) {
        if (window.Alpine?.store('appModal')) { Alpine.store('appModal').close(); }
        if (j.redirect) location.href = j.redirect; else location.reload();
      } else {
        alert(j.message || 'Không lưu được.');
      }
    },
  }
}

// helper: object -> FormData (hỗ trợ mảng/lồng)
function toFormData(obj, fd=new FormData(), pre=''){
  if (obj === null || obj === undefined) return fd;
  if (typeof obj !== 'object') { fd.append(pre, obj); return fd; }
  Object.keys(obj).forEach(k=>{
    const v = obj[k];
    const key = pre ? `${pre}[${k}]` : k;
    if (Array.isArray(v)) {
      v.forEach((vv, i)=> toFormData(vv, fd, `${key}[${i}]`));
    } else if (v && typeof v === 'object') {
      toFormData(v, fd, key);
    } else {
      fd.append(key, v ?? '');
    }
  });
  return fd;
}
</script>


 

<script>
/** ========== GLOBAL: schoolPicker – dùng được trong modal ========== */
window.schoolPicker = window.schoolPicker || function (opts = {}) {
  return {
    // OPTIONS
    inputName:  opts.inputName  || 'education[school_id]',
    withGeo:   !!opts.withGeo,
    scheme:     opts.schemeInit || 'new',
    geo:        Object.assign({province_id:null, district_id:null, ward_id:null}, opts.geoInit || {}),
    mirror:     Object.assign({}, opts.mirror || {}),
    initial:    opts.initial || null,
    endpoints:  Object.assign({ search: '/truong/datapicker' }, opts.endpoints || {}),
    perPage:    Number(opts.perPage || 10),

    // STATE
    q:'', open:false, loading:false,
    results:[], picked:null,
    page:1, hasMore:false,

    // LIFECYCLE
    init(){ this._boot(); },
    boot(){ this._boot(); },
    _boot(){
      if (this.withGeo) { this._syncGeoFromDom(); this._bindGeoDomListeners(); }
      if (this.initial) { this.picked = this.initial; this.q = this.initial.name || ''; }
      const inp = this.$root.querySelector('input[type="text"]');
      if (inp) inp.addEventListener('focus', () => { this.open = true; });
    },

    // GEO helpers: đọc các select tên geo_school[...]
    _syncGeoFromDom(){
      const read = (name) => {
        const el = this.$root.querySelector(`[name="${name}"]`);
        if (!el) return null;
        const v = (el.value || '').trim();
        if (v === '') return null;
        const n = Number(v);
        return Number.isFinite(n) ? n : v;
      };
      this.geo.province_id = read('geo_school[province_id]');
      this.geo.district_id = read('geo_school[district_id]');
      this.geo.ward_id     = read('geo_school[ward_id]');
    },
    _bindGeoDomListeners(){
      ['province_id','district_id','ward_id'].forEach(k => {
        const el = this.$root.querySelector(`[name="geo_school[${k}]"]`);
        if (!el) return;
        el.addEventListener('change', () => {
          this._syncGeoFromDom();
          if ((this.q||'').trim().length >= 2) this.search(true);
        });
      });
    },

    // SEARCH
    async search(reset = true){
      const q = (this.q||'').trim();
      if (q.length < 2) { this.results=[]; this.hasMore=false; this.open=!!q; return; }
      if (reset) this.page = 1; else this.page += 1;
      if (this.withGeo) this._syncGeoFromDom();

      // Map đúng tham số API: q, page, per_page, province_code_new, district_code_new, ward_code_new
      const p = new URLSearchParams({
        q,
        page: String(this.page),
        per_page: String(this.perPage),
      });
      if (this.geo.province_id) p.set('province_code_new', this.geo.province_id);
      if (this.geo.district_id) p.set('district_code_new', this.geo.district_id);
      if (this.geo.ward_id)     p.set('ward_code_new',     this.geo.ward_id);

      this.loading = true; this.open = true;

      try {
        const res = await fetch(`${this.endpoints.search}?${p.toString()}`, {
          headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
        });
        const data = await res.json();

        // Laravel paginator: data.data là mảng items
        const raw = Array.isArray(data) ? data : (data.data || data.items || []);
        const items = raw.map(d => ({
          id: d.id, code: d.code, name: d.name,
          ward_new: d.ward_new, province_new: d.province_new
        }));

        this.results = reset ? items : this.results.concat(items);

        // hasMore theo paginator
        const current = data.current_page ?? this.page;
        const last    = data.last_page ?? (items.length === this.perPage ? current+1 : current);
        this.hasMore  = Number.isFinite(last) ? (current < last) : (items.length >= this.perPage);

      } catch (e) {
        console.error('schoolPicker.search', e);
        if (reset) this.results = [];
        this.hasMore = false;
      } finally {
        this.loading = false;
        this.open = true;
      }
    },
    loadMore(){ if (this.hasMore) this.search(false); },

    // PICK/CLEAR
    pick(r){ this.picked=r; this.q=r?.name||''; this.results=[]; this.open=false; },
    clearPick(){ this.picked=null; this.q=''; this.results=[]; this.open=false; },
  };
};
</script>

<script>
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
    submitExportMany(){
      const form = this.$refs.exportManyForm;
      if (!form) return;
      const token = form.querySelector('input[name="_token"]').cloneNode(true);
      form.innerHTML = '';
      form.appendChild(token);
      document.querySelectorAll('input.row-checkbox:checked').forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = cb.value;
        form.appendChild(inp);
      });
      if (!form.querySelector('input[name="ids[]"]')) return;
      form.submit();
    }
  }
}
</script>

<script>
/** ========== CONFIRM STORE (1 bản duy nhất) ========== */
(function registerAppConfirm(){
  function mount(){
    try { if (Alpine.store('appConfirm')) return; } catch(e){}
    Alpine.store('appConfirm', {
      open:false, title:'', message:'', okText:'', cancelText:'', _onOk:null,
      show({title='', message='', okText='Đồng ý', cancelText='Hủy', onOk=null}={}){
        this.title = title; this.message = message;
        this.okText = okText; this.cancelText = cancelText;
        this._onOk = onOk; this.open = true;
      },
      ok(){ try{ this._onOk && this._onOk(); } finally { this.close(); } },
      cancel(){ this.close(); },
      close(){ this.open=false; this.title=''; this.message=''; this._onOk=null; }
    });
  }
  if (window.Alpine) mount();
  else document.addEventListener('alpine:init', mount, { once:true });

  // Intercept form[data-confirm]
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (!f.matches('form[data-confirm]')) return;
    if (f.dataset.confirmed === '1') return;
    e.preventDefault();

    if (!window.Alpine || !Alpine.store('appConfirm')) {
      if (confirm(f.dataset.confirm || 'Bạn có chắc chắn?')) { f.dataset.confirmed = '1'; f.submit(); }
      return;
    }

    Alpine.store('appConfirm').show({
      title: f.dataset.title || 'Xác nhận',
      message: f.dataset.confirm || 'Bạn có chắc chắn?',
      okText: f.dataset.ok || 'Đồng ý',
      cancelText: f.dataset.cancel || 'Hủy',
      onOk: () => { f.dataset.confirmed = '1'; f.submit(); }
    });
  });
})();
</script>

<script>
/** ========== MODAL STORE (1 bản duy nhất, compile Alpine trong nội dung tải về) ========== */
document.addEventListener('alpine:init', () => {
  Alpine.store('appModal', {
    open:false, loading:false, title:'', html:'',
 // cấu hình
    backdropClosable: false,   // ⬅️ mặc định KHÔNG đóng khi bấm nền
    escClosable: true,         // ⬅️ vẫn cho ESC đóng (đổi tùy ý)
    
    lastUrl: null,
    async showFrom(url, {title=''}={}) {
      this.lastUrl = url;           // ⬅️ nhớ URL
      this.open = true; this.loading = true; this.title = title;
      try {
        const res = await fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'}, cache: 'no-store' });
        const html = await res.text();
        this.setHtml(html);
      } finally { this.loading = false; }
    },
    setHtml(html){
      const body = document.getElementById('app-modal-body');
      if (!body) return;

      // Dọn cây cũ nếu có API
      if (window.Alpine && Alpine.destroyTree) { Alpine.destroyTree(body); }
      body.innerHTML = html;

      // Compile Alpine cho DOM mới
      if (window.Alpine && Alpine.initTree) { Alpine.initTree(body); }

      // Bind lại form / close trong nội dung vừa chèn
      this.bindAjaxForms();
      this.bindCloseButtons();
      
      // Bind JavaScript cho modal chuyển hộ
      this.bindMoveHouseholdModal();
    },

    bindAjaxForms(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      body.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          const res = await fetch(form.action, {
            method: (form.method||'POST').toUpperCase(),
            headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' },
            body: new FormData(form)
          });

          const ct = res.headers.get('content-type') || '';
          if (ct.includes('application/json')) {
            const data = await res.json();
            if (data.ok) { this.close(); if (data.redirect) location.href=data.redirect; else location.reload(); return; }
            if (data.html) { this.setHtml(data.html); return; }
            if (data.message) { this.setHtml(`<div class="text-red-600">${data.message}</div>`); return; }
            // nếu JSON nhưng không đúng schema -> fallback
            this.setHtml('<div class="text-red-600">Phản hồi không hợp lệ.</div>');
            return;
          }

          // Fallback: server trả HTML
          const html = await res.text();
          this.setHtml(html);
        }, { once:false });
      });
    },

    bindCloseButtons(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      body.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => { e.preventDefault(); this.close(); }, { once:false });
      });
    },

    bindMoveHouseholdModal(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      
      // Kiểm tra xem có modal chuyển hộ không
      const modal = body.querySelector('#move-household-modal');
      if (!modal) return;
      
      const config = JSON.parse(modal.dataset.config);
      
      // Khởi tạo MoveHouseholdModal
      new MoveHouseholdModal(config);
    },

    close(){ this.open=false; this.title=''; const body=document.getElementById('app-modal-body'); if (body) body.innerHTML=''; }
  });
});
</script>

<script>
/** ===== Spinner store ===== */
document.addEventListener('alpine:init', () => {
  Alpine.store('dl', {
    open: false, message: 'Đang xuất Excel…',
    show(msg){ this.message = msg || 'Đang xuất Excel…'; this.open = true; },
    hide(){ this.open = false; this.message = 'Đang xuất Excel…'; }
  });
});

/** ===== Helpers xuất file qua fetch + blob ===== */
function filenameFromDisposition(res, fallback = 'export.xlsx'){
  const cd = res.headers.get('Content-Disposition') || res.headers.get('content-disposition') || '';
  const m1 = /filename\*=UTF-8''([^;]+)/i.exec(cd);
  if (m1) return decodeURIComponent(m1[1]);
  const m2 = /filename="?([^"]+)"?/i.exec(cd);
  if (m2) return m2[1];
  return fallback;
}

async function downloadBlob(url, {method='GET', headers={}, body=null, fileName='export.xlsx', spinnerMsg} = {}){
  try{
    Alpine.store('dl').show(spinnerMsg || 'Đang xuất Excel…');

    const res = await fetch(url, {
      method,
      headers: Object.assign({
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      }, headers),
      body
    });

    if(!res.ok){
      const text = await res.text().catch(()=> '');
      Alpine.store('dl').hide();
      alert(text || ('Lỗi tải file (HTTP '+res.status+')'));
      return;
    }

    const blob = await res.blob();
    const name = filenameFromDisposition(res, fileName);
    const href = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = href; a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(href);
  }catch(err){
    alert('Không thể tải file: ' + (err?.message || err));
  }finally{
    Alpine.store('dl').hide();
  }
}

/** Serialize form (GET) -> URL?query */
function buildUrlWithForm(form, action){
  const url = new URL(action, window.location.origin);
  const fd = new FormData(form);
  for (const [k,v] of fd.entries()){
    if (v !== null && String(v).trim() !== '') url.searchParams.append(k, v);
  }
  return url.toString();
}
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Nút "Xuất Excel" trong form lọc (GET)
  const filterForm = document.querySelector('form[method="get"]');
  const exportBtn = filterForm?.querySelector('button[formaction][formmethod="get"]');
  if (filterForm && exportBtn){
    exportBtn.addEventListener('click', (ev) => {
      ev.preventDefault(); ev.stopPropagation();
      const url = buildUrlWithForm(filterForm, exportBtn.getAttribute('formaction'));
      downloadBlob(url, { method: 'GET', spinnerMsg: 'Đang xuất Excel theo bộ lọc…', fileName: 'surveys.xlsx' });
    });
  }
});
</script>
<script>
function bulkSelect(){
  return {
    selectedCount: 0,

    toggleAll(ev){ /* giữ nguyên */ const checked = ev.target.checked;
      document.querySelectorAll('input.row-checkbox').forEach(cb => { cb.checked = checked; });
      this.recount();
    },
    onRowToggle(){ this.recount(); },
    recount(){ this.selectedCount = document.querySelectorAll('input.row-checkbox:checked').length; },

    async submitExportMany(){
      const ids = Array.from(document.querySelectorAll('input.row-checkbox:checked'))
        .map(cb => cb.value);
      if (!ids.length) return;

      const form = this.$refs.exportManyForm; // form ẩn hiện có
      const url = form.getAttribute('action');
      const fd = new FormData();
      ids.forEach(id => fd.append('ids[]', id));
      // CSRF
      const token = form.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '';

      await downloadBlob(url, {
        method: 'POST',
        headers: token ? {'X-CSRF-TOKEN': token} : {},
        body: fd,
        spinnerMsg: `Đang xuất Excel (${ids.length})…`,
        fileName: 'surveys_selected.xlsx'
      });
    }
  }
}
</script>



@endpush
