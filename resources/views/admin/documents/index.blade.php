@extends('layouts.admin')
@section('title','Văn bản')
@section('crumb','Quản lý / Văn bản')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div class="space-y-6"
     x-data="docIdx"
     data-base-url="{{ route('admin.documents.index') }}"
     data-q-init='@json(request("q",""))'
     data-issuer-init='@json(request("issuer_id"))'
     data-status-init='@json(request("status"))'
     data-from-init='@json(request("from"))'
     data-to-init='@json(request("to"))'>

  <div class="flex items-center gap-3">
    <a href="{{ route('admin.documents.create') }}" class="btn-primary">+ Thêm văn bản</a>
    <div class="ml-auto"></div>
  </div>

  <article class="card">
    <form @submit.prevent class="grid gap-3 md:grid-cols-12">
      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Từ khoá</label>
        <input type="text" x-model="q" placeholder="Số hiệu / tiêu đề…"
               class="w-full rounded-lg border px-3 py-2"
               @input="debounce(()=>search(), 350)"
               @keydown.enter.prevent="search()">
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Đơn vị cấp</label>
        <select x-model="issuer_id" @change="search()" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Tất cả —</option>
          @foreach($issuers as $is)
            <option value="{{ $is->id }}">{{ $is->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
        <select x-model="status" @change="search()" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Tất cả —</option>
          <option value="draft">Nháp</option>
          <option value="published">Công bố</option>
          <option value="archived">Lưu trữ</option>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs text-slate-500 mb-1">Từ ngày</label>
        <input type="date" x-model="from" @change="search()" class="w-full rounded-lg border px-3 py-2">
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs text-slate-500 mb-1">Đến ngày</label>
        <input type="date" x-model="to" @change="search()" class="w-full rounded-lg border px-3 py-2">
      </div>

      <div class="md:col-span-12 flex items-end gap-2">
        <button type="button" class="btn-primary" @click="search()">Lọc</button>
        <button type="button" class="btn-soft" @click="resetFilters()">Xoá lọc</button>
      </div>
    </form>
  </article>

  <article class="card overflow-hidden relative">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Danh sách văn bản</h2>
      <div class="text-sm text-slate-500" x-text="totalText"></div>
    </div>

    <div id="docTableWrap">
      @include('admin.documents._table')
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
      <h3 class="font-semibold text-lg">Xoá văn bản?</h3>
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
  Alpine.data('docIdx', () => ({
    baseUrl: '',
    q: '', issuer_id: '', status: '', from: '', to: '',
    loading: false,
    totalText: 'Tổng: {{ $documents->total() }}',
    _t: null,

    init(){
      const ds = this.$el.dataset
      this.baseUrl   = ds.baseUrl || ''
      this.q         = ds.qInit      ? JSON.parse(ds.qInit)      : ''
      this.issuer_id = ds.issuerInit ? (JSON.parse(ds.issuerInit) ?? '') + '' : ''
      this.status    = ds.statusInit ? (JSON.parse(ds.statusInit) ?? '') + '' : ''
      this.from      = ds.fromInit   ? JSON.parse(ds.fromInit)   : ''
      this.to        = ds.toInit     ? JSON.parse(ds.toInit)     : ''
    },

    debounce(fn, ms=400){ clearTimeout(this._t); this._t = setTimeout(fn, ms); },

    params(){
      const p = new URLSearchParams()
      if (this.q) p.set('q', this.q)
      if (this.issuer_id) p.set('issuer_id', this.issuer_id)
      if (this.status) p.set('status', this.status)
      if (this.from) p.set('from', this.from)
      if (this.to) p.set('to', this.to)
      p.set('partial','1')
      return p
    },

    search(){
      this.loading = true
      fetch(`${this.baseUrl}?${this.params().toString()}`, {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept': 'text/html' }
      })
        .then(r => r.text())
        .then(html => {
          const wrap = document.getElementById('docTableWrap')
          wrap.innerHTML = html

          // cập nhật tổng
          const meta = wrap.querySelector('#doc-meta')
          if (meta && meta.dataset.total) this.totalText = 'Tổng: ' + meta.dataset.total

          // cập nhật URL (không có partial)
          const full = new URL(this.baseUrl, location.origin)
          if (this.q) full.searchParams.set('q', this.q); else full.searchParams.delete('q')
          if (this.issuer_id) full.searchParams.set('issuer_id', this.issuer_id); else full.searchParams.delete('issuer_id')
          if (this.status) full.searchParams.set('status', this.status); else full.searchParams.delete('status')
          if (this.from) full.searchParams.set('from', this.from); else full.searchParams.delete('from')
          if (this.to) full.searchParams.set('to', this.to); else full.searchParams.delete('to')
          history.replaceState({}, '', full.toString())
        })
        .finally(() => { this.loading = false })
    },

    resetFilters(){
      this.q = this.issuer_id = this.status = this.from = this.to = ''
      this.search()
    }
  }))
})
</script>
@endpush
