@extends('layouts.admin')
@section('title','Quản lý báo cáo')
@section('crumb','Quản lý / Báo cáo')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div class="space-y-6"
     x-data="reportIdx"
     data-base-url="{{ route('admin.reports.index') }}"
     data-group-init='@json(request("group_id"))'
     data-search-init='@json(request("search"))'>

  <div class="flex items-center gap-3">
    <a href="{{ route('admin.reports.create') }}" class="btn-primary">+ Thêm nhóm báo cáo</a>
    <div class="ml-auto"></div>
  </div>

  <article class="card">
    <form @submit.prevent class="grid gap-3 md:grid-cols-12">
      <div class="md:col-span-4">
        <label class="block text-xs text-slate-500 mb-1">Từ khoá</label>
        <input type="text" x-model="search" placeholder="Tên báo cáo, mã báo cáo…"
               class="w-full rounded-lg border px-3 py-2"
               @input="debounce(()=>searchReports(), 350)"
               @keydown.enter.prevent="searchReports()">
      </div>

      <div class="md:col-span-4">
        <label class="block text-xs text-slate-500 mb-1">Nhóm báo cáo</label>
        <select x-model="group_id" @change="searchReports()" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Tất cả —</option>
          @foreach($reportGroups as $group)
            <option value="{{ $group->id }}">{{ $group->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-4 flex items-end gap-2">
        <button type="button" class="btn-primary" @click="searchReports()">Lọc</button>
        <button type="button" class="btn-soft" @click="resetFilters()">Xoá lọc</button>
      </div>
    </form>
  </article>

  <article class="card overflow-hidden relative">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Danh sách báo cáo</h2>
      <div class="text-sm text-slate-500" x-text="totalText"></div>
    </div>

    <div id="reportTableWrap">
      @include('admin.reports._table')
    </div>

    <div x-show="loading" x-transition class="absolute inset-0 bg-white/60 grid place-items-center" aria-live="polite">
      <div class="rounded-md border px-3 py-1 text-sm bg-white shadow">Đang tải…</div>
    </div>
  </article>
</div>

{{-- modal confirm xoá --}}
<div x-data="{ open:false, action:'', name:'', type:'' }"
     @confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name; type=$event.detail.type">
  <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
    <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
      <h3 class="font-semibold text-lg">Xoá <span x-text="type"></span>?</h3>
      <p class="mt-2 text-sm text-slate-600">Bạn chắc chắn muốn xoá <span class="font-medium" x-text="name"></span>?</p>
      <form :action="action" method="POST" class="mt-4 flex justify-end gap-2">
        @csrf @method('DELETE')
        <button type="button" class="btn-soft" @click="open=false">Huỷ</button>
        <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('reportIdx', () => ({
    baseUrl: '',
    search: '', group_id: '',
    loading: false,
    totalText: 'Tổng: {{ $reportGroups->count() }} nhóm báo cáo',
    _t: null,

    init(){
      const ds = this.$el.dataset
      this.baseUrl   = ds.baseUrl || ''
      this.search    = ds.searchInit ? JSON.parse(ds.searchInit) : ''
      this.group_id  = ds.groupInit ? (JSON.parse(ds.groupInit) ?? '') + '' : ''
    },

    debounce(fn, ms=400){ clearTimeout(this._t); this._t = setTimeout(fn, ms); },

    params(){
      const p = new URLSearchParams()
      if (this.search) p.set('search', this.search)
      if (this.group_id) p.set('group_id', this.group_id)
      p.set('partial','1')
      return p
    },

    searchReports(){
      this.loading = true
      fetch(`${this.baseUrl}?${this.params().toString()}`, {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept': 'text/html' }
      })
        .then(r => r.text())
        .then(html => {
          const wrap = document.getElementById('reportTableWrap')
          wrap.innerHTML = html

          // cập nhật tổng
          const meta = wrap.querySelector('#report-meta')
          if (meta && meta.dataset.total) this.totalText = 'Tổng: ' + meta.dataset.total

          // cập nhật URL
          const full = new URL(this.baseUrl, location.origin)
          if (this.search) full.searchParams.set('search', this.search); else full.searchParams.delete('search')
          if (this.group_id) full.searchParams.set('group_id', this.group_id); else full.searchParams.delete('group_id')
          history.replaceState({}, '', full.toString())
        })
        .finally(() => { this.loading = false })
    },

    resetFilters(){
      this.search = this.group_id = ''
      this.searchReports()
    }
  }))
})
</script>
@endpush
