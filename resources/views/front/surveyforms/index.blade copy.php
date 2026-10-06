@extends('layouts.front')
@section('title','Danh sách phiếu điều tra')

@section('content')
<style>
  .th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb}
  .td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}
</style>
<style>[x-cloak]{display:none!important}</style>
<div x-data="bulkSelect()" class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Danh sách phiếu điều tra (chủ hộ)</h1>
    <div class="flex gap-2">
      <a href="{{ route('front.surveyforms.import') }}" class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-slate-700">Nhập Excel</a>
      <a href="{{ route('front.surveyforms.export') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">Xuất Excel</a>
      
    {{-- MỚI: mở modal AJAX --}}
      <button type="button"
              class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700"
              @click="$store.appModal.showFrom('{{ route('front.surveyforms.modal.create') }}',{title:'Thêm phiếu điều tra'})">
        Thêm phiếu
      </button>
    </div>
  </div>

  {{-- Bộ lọc --}}
  <form method="get" class="mb-3 grid gap-3 md:grid-cols-12">
    @php
      $base = \App\Models\SurveyForm::currentAcademicYear();
      [$y1,$y2] = explode('-', $base);
      $years = [($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
    @endphp
    {{-- Geo filter: chiếm trọn hàng --}}
    <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        'namePrefix' => 'geo',
        'scheme'     => request('scheme','old'),
        'provinceId' => request('geo.province_id'),
        'districtId' => request('geo.district_id'),
        'wardId'     => request('geo.ward_id'),
        'villageId'  => request('geo.village_id'),
        'showHamlet' => true,
        // layout gợi ý: radio 3 cột + cụm selector 9 cột
        'wrapperClass' => 'grid md:grid-cols-12 gap-3'
      ])
    </div>
      
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

    <div class="md:col-span-4">
      <label class="block text-xs text-slate-500 mb-1">Tìm chủ hộ (họ tên)</label>
      <input name="q" value="{{ $q }}" class="w-full rounded-lg border px-3 py-2" placeholder="VD: Nguyễn Văn A">
    </div>

    <div class="md:col-span-2 flex items-end gap-2">
      <button class="rounded-lg bg-slate-800 px-3 py-2 text-white hover:bg-slate-900">Lọc</button>
      <a href="{{ route('front.surveyforms.index') }}" class="rounded-lg border px-3 py-2 hover:bg-slate-50">Xoá lọc</a>
    </div>
  </form>
  {{-- Thông báo --}}
@if(session('status'))
  <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">
    {{ session('status') }}
  </div>
@endif

<div   class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between rounded-xl border bg-slate-50 p-3">
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
    {{-- Form xóa các đã chọn --}}
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


    {{-- Xóa hết theo bộ lọc --}}
    <form method="POST" action="{{ route('front.surveyforms.bulk_delete') }}"
      data-confirm="Xóa MỀM TẤT CẢ các phiếu ĐANG LỌC?"
      data-title="Xóa theo bộ lọc" data-ok="Xóa" data-cancel="Hủy">
      @csrf
      <input type="hidden" name="scope" value="filtered">
      <input type="hidden" name="year" value="{{ $year }}">
      <input type="hidden" name="so_phieu" value="{{ $soPhieu }}">
      <input type="hidden" name="q" value="{{ $q }}">

      {{-- giữ geo filter --}}
      <input type="hidden" name="scheme" value="{{ request('scheme','old') }}">
      <input type="hidden" name="geo[province_id]" value="{{ request('geo.province_id') }}">
      <input type="hidden" name="geo[district_id]" value="{{ request('geo.district_id') }}">
      <input type="hidden" name="geo[ward_id]"     value="{{ request('geo.ward_id') }}">
      <input type="hidden" name="geo[village_id]"  value="{{ request('geo.village_id') }}">
      
      <button class="rounded-lg bg-amber-600 px-3 py-2 text-white hover:bg-amber-700">Xóa hết (theo bộ lọc)</button>
    </form>

    {{-- Xóa toàn bộ --}}
    <form method="POST" action="{{ route('front.surveyforms.bulk_delete') }}"
      data-confirm="Xóa MỀM TOÀN BỘ phiếu?"
      data-title="Xóa toàn bộ" data-ok="Xóa" data-cancel="Hủy">
      @csrf
      <input type="hidden" name="scope" value="all">
      <button class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700">Xóa toàn bộ</button>
    </form>
  </div>
</div>

  {{-- Bảng --}}
  <div class="-mx-4 overflow-x-auto">
  <table class="min-w-[1100px]">
    <thead>
      <tr class="bg-slate-100">
        <th class="th sticky left-0 z-30 bg-slate-100 w-10">
          <input type="checkbox" @change="toggleAll($event)" aria-label="Chọn tất cả">
        </th>

        {{-- cột nút hành động (mảnh, dùng icon) --}}
        <th class="th sticky left-10 z-30 bg-slate-100 w-10"></th>

        {{-- STT phải đẩy thêm 40px: left-20 (≈ 80px) --}}
        <th class="th sticky left-20 z-20 bg-slate-100 w-12">#</th>

        <th class="th">Số phiếu</th>
        <th class="th">Chủ hộ</th>
        <th class="th">Giới tính</th>
        <th class="th">Ngày sinh</th>
        <th class="th">Điện thoại</th>
        <th class="th">Ngày điều tra</th>
        <th class="th">Loại cư trú</th>
        <th class="th">Tình trạng cư trú</th>
        <th class="th">Địa bàn (cũ)</th>
        <th class="th text-right">Tổng TV</th>
        <th class="th"></th>
      </tr>
    </thead>

    <tbody>
      @forelse($rows as $i => $r)
        <tr class="hover:bg-slate-50">
          {{-- checkbox --}}
          <td class="td sticky left-0 z-20 bg-white w-10">
            <input type="checkbox" class="row-checkbox" name="ids[]" value="{{ $r['id'] }}"
                  @change="onRowToggle()" aria-label="Chọn phiếu {{ $r['so_phieu'] }}" form="bulkSelForm">
          </td>

          {{-- nút hành động (icon, rất mảnh) --}}
          <td class="td sticky left-10 z-20 bg-white w-10">
            <div x-data="{ open:false }" class="relative">
              <button type="button"
                      class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
                      @click="open=!open" aria-label="Hành động">
                {{-- icon 3 chấm dọc --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor">
                  <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/>
                </svg>
              </button>

              {{-- MENU xổ sang phải của nút --}}
              <div x-cloak x-show="open" @click.outside="open=false"
                  class="absolute left-full top-0 ml-2 w-48 rounded-xl border bg-white shadow-xl z-50 overflow-hidden">
                <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                        @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.members.create',$r['id']) }}',{title:'Thêm thành viên'})">
                  Thêm thành viên
                </button>

                <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                        @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.members.index',$r['id']) }}',{title:'Danh sách thành viên'})">
                  Xem thành viên
                </button>

                <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                        @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.edit',$r['id']) }}',{title:'Điều chỉnh phiếu'})">
                  Điều chỉnh phiếu
                </button>

                <button class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                        @click="open=false; $store.appModal.showFrom('{{ route('front.surveyforms.modal.split',$r['id']) }}',{title:'Tách hộ'})">
                  Tách hộ
                </button>

                <form method="POST" action="{{ route('front.surveyforms.destroy',$r['id']) }}"
                      data-confirm="Xóa mềm phiếu này và chi tiết liên quan?"
                      data-title="Xóa phiếu" data-ok="Xóa" data-cancel="Hủy">
                  @csrf @method('DELETE')
                  <button type="submit" class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50">
                    Xóa phiếu
                  </button>
                </form>
              </div>
            </div>
          </td>

          {{-- STT (đẩy 80px giống header) --}}
          <td class="td sticky left-20 z-10 bg-white w-12 tabular-nums">
            {{ $rows->firstItem() + $i }}
          </td>

          {{-- ...các cột còn lại giữ nguyên --}}
          <td class="td">{{ $r['so_phieu'] }}</td>
          <td class="td font-medium">{{ $r['chu_ho'] ?: '—' }}</td>
          <td class="td">{{ $r['head_gender'] ?? '—' }}</td>
          <td class="td tabular-nums">{{ $r['head_dob'] ?? '—' }}</td>
          <td class="td">{{ $r['head_phone'] ?: '—' }}</td>
          <td class="td">{{ $r['surveyed_at'] ?: '—' }}</td>
          <td class="td">{{ $r['res_type'] ?: '—' }}</td>
          <td class="td">{{ $r['res_status'] ?: '—' }}</td>
          <td class="td">{{ $r['addressline'] ?: '—' }}</td>
          <td class="td text-right tabular-nums">{{ $r['member_count'] }}</td>
          <td class="td">
            {{-- có thể bỏ nhóm nút Sửa/Xóa ở đây nếu đã dùng menu ở trên --}}
          </td>
        </tr>
      @empty
        <tr><td class="td" colspan="14">Chưa có dữ liệu.</td></tr>
      @endforelse
      </tbody>
  </table>
</div>

  <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
</div>
<!-- MODAL AJAX DÙNG CHUNG -->
<template x-if="$store.appModal.open">
  <div class="fixed inset-0 z-[70] grid place-items-center bg-black/40" x-cloak>
    <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h3 class="font-medium" x-text="$store.appModal.title"></h3>
        <button class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
                @click="$store.appModal.close()">✕</button>
      </div>
      <div class="p-4">
        <template x-if="$store.appModal.loading">
          <div class="text-sm text-slate-500">Đang tải…</div>
        </template>
        <div id="app-modal-body" x-html="$store.appModal.html"></div>
      </div>
    </div>
  </div>
</template>
<!-- CONFIRM MODAL DÙNG CHUNG -->
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


@endsection
@push('scripts')
<script>
  function bulkSelect(){
    return {
      selectedCount: 0,
      toggleAll(ev){
        const checked = ev.target.checked;
        document.querySelectorAll('input.row-checkbox').forEach(cb => {
          cb.checked = checked;
        });
        this.recount();
      },
      onRowToggle(){
        this.recount();
      },
      recount(){
        this.selectedCount = document.querySelectorAll('input.row-checkbox:checked').length;
      }
    }
  }
</script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.store('appModal', {
    open:false, loading:false, title:'', html:'',

    async showFrom(url, {title=''}={}) {
      this.open = true; this.loading = true; this.title = title; this.html = '';
      try {
        const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        this.html = await res.text();
      } catch (e) {
        this.html = '<div class="text-red-600">Không tải được nội dung.</div>';
      } finally {
        this.loading = false;
        setTimeout(() => { this.bindAjaxForms(); this.bindCloseButtons(); }, 0);
      }
    },

    bindAjaxForms(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      body.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          const res = await fetch(form.action, {
            method: (form.method||'POST').toUpperCase(),
            headers: { 'X-Requested-With':'XMLHttpRequest' },
            body: new FormData(form)
          });
          const ct = res.headers.get('content-type') || '';
          if (ct.includes('application/json')) {
            const data = await res.json();
            if (data.ok) { this.close(); window.location.reload(); return; }
            if (data.html) { document.getElementById('app-modal-body').innerHTML = data.html; this.bindAjaxForms(); this.bindCloseButtons(); return; }
          }
          document.getElementById('app-modal-body').innerHTML = await res.text();
          this.bindAjaxForms(); this.bindCloseButtons();
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

    close(){ this.open=false; this.html=''; this.title=''; }
  });
});
</script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.store('appModal', {
    open:false, loading:false, title:'', html:'',

    async showFrom(url, {title=''}={}) {
      this.open = true; this.loading = true; this.title = title; this.html = '';
      try {
        const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'text/html, application/json' }});
        const ct  = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
          const data = await res.json();
          if (data.ok) {
            this.close();
            if (data.redirect) window.location.assign(data.redirect); else window.location.reload();
            return;
          }
          if (data.html) {
            this.html = data.html;            // <== gán vào store
            Alpine.nextTick(() => {           // <== dùng Alpine.nextTick (store không có this.$nextTick)
              const body = document.getElementById('app-modal-body');
              if (body) {
                body.innerHTML = this.html;    // render vào DOM
                Alpine.initTree(body);         // <== re-init Alpine bên trong
                this.bindAjaxForms();
                this.bindCloseButtons();
              }
            });
            return;
          }
        }
        // fallback text/html
        this.html = await res.text();
        Alpine.nextTick(() => {
          const body = document.getElementById('app-modal-body');
          if (body) {
            body.innerHTML = this.html;
            Alpine.initTree(body);
            this.bindAjaxForms();
            this.bindCloseButtons();
          }
        });
      } catch (e) {
        this.html = '<div class="text-red-600">Không tải được nội dung.</div>';
      } finally {
        this.loading = false;
      }
    },

    bindAjaxForms(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      body.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          const res = await fetch(form.action, {
            method: (form.method||'POST').toUpperCase(),
            headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'text/html, application/json' },
            body: new FormData(form)
          });
          const ct = res.headers.get('content-type') || '';
          if (ct.includes('application/json')) {
            const data = await res.json();
            if (data.ok) {
              this.close();
              if (data.redirect) window.location.assign(data.redirect); else window.location.reload();
              return;
            }
            if (data.html) {
              this.html = data.html;
              const body = document.getElementById('app-modal-body');
              if (body) {
                body.innerHTML = this.html;
                Alpine.initTree(body);
                this.bindAjaxForms();
                this.bindCloseButtons();
              }
              return;
            }
          }
          this.html = await res.text();
          const body2 = document.getElementById('app-modal-body');
          if (body2) {
            body2.innerHTML = this.html;
            Alpine.initTree(body2);
            this.bindAjaxForms();
            this.bindCloseButtons();
          }
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

    close(){ this.open=false; this.html=''; this.title=''; }
  });
});
</script>
@endpush