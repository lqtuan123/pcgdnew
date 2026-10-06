@extends('layouts.admin')
@section('title','Đơn vị cấp văn bản')
@section('crumb','Cài đặt / Đơn vị cấp văn bản')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div class="space-y-6"
     x-data="issIdx"
     data-base-url="{{ route('admin.settings.issuers.index') }}"
     data-q-init='@json(request("q",""))'>

  <div class="flex items-center gap-3">
    <a href="{{ route('admin.settings.issuers.create') }}" class="btn-primary">+ Thêm đơn vị</a>
    <div class="ml-auto"></div>
  </div>

  <article class="card">
    <form @submit.prevent class="grid gap-3 md:grid-cols-12">
      <div class="md:col-span-6">
        <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
        <div class="relative">
          <input type="text" x-model="q" placeholder="Tên / mã đơn vị…"
                 class="w-full rounded-lg border px-3 py-2 pr-9"
                 @input="debounce(()=>search(), 350)"
                 @keydown.enter.prevent="search()">
          <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  x-show="q" @click="q=''; search()" x-cloak title="Xoá từ khoá">✕</button>
        </div>
      </div>

      <div class="md:col-span-6 flex items-end gap-2">
        <button type="button" class="btn-primary" @click="search()">Lọc</button>
        <button type="button" class="btn-soft" @click="q=''; search()">Xoá lọc</button>
      </div>
    </form>
  </article>

  <article class="card overflow-hidden relative">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Danh sách đơn vị cấp</h2>
      <div class="text-sm text-slate-500" x-text="totalText"></div>
    </div>

    <div id="issTableWrap">
      @include('admin.settings.issuers._table')
    </div>

    <div x-show="loading" x-transition class="absolute inset-0 bg-white/60 grid place-items-center" aria-live="polite">
      <div class="rounded-md border px-3 py-1 text-sm bg-white shadow">Đang tải…</div>
    </div>
  </article>
</div>

{{-- modal confirm xoá --}}
<div x-data="{ open:false, action:'', name:'' }"
     @confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name">
  <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
    <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
      <h3 class="font-semibold text-lg">Xoá đơn vị?</h3>
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
  Alpine.data('issIdx', () => ({
    baseUrl: '',
    q: '',
    loading: false,
    totalText: 'Tổng: {{ $issuers->total() }}',
    _t: null,

    init() {
      const ds = this.$el.dataset
      this.baseUrl = ds.baseUrl || ''
      this.q = ds.qInit ? JSON.parse(ds.qInit) : ''
    },

    debounce(fn, ms=400){ clearTimeout(this._t); this._t = setTimeout(fn, ms); },

    search(){
      this.loading = true
      const params = new URLSearchParams()
      if (this.q) params.set('q', this.q)
      params.set('partial','1')

      fetch(`${this.baseUrl}?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
      })
        .then(r => r.text())
        .then(html => {
          const wrap = document.getElementById('issTableWrap')
          wrap.innerHTML = html

          // cập nhật tổng (lấy từ partial nếu có)
          const meta = wrap.querySelector('#iss-meta')
          if (meta && meta.dataset.total) this.totalText = 'Tổng: ' + meta.dataset.total

          // cập nhật URL (không kèm partial)
          const full = new URL(this.baseUrl, location.origin)
          if (this.q) full.searchParams.set('q', this.q); else full.searchParams.delete('q')
          history.replaceState({}, '', full.toString())
        })
        .finally(() => { this.loading = false })
    }
  }))
})
</script>
@endpush
