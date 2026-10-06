@extends('layouts.front')
@section('title','Danh mục Tài liệu')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="docsUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Danh mục Tài liệu</h1>
    <a :href="exportHref" class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">Xuất Excel</a>
  </div>

  <!-- Filters -->
  <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-4">
    <div class="md:col-span-2">
      <input x-model.trim="filters.q" type="text" placeholder="Tìm mã / tiêu đề…"
             class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <select x-model="filters.issuer_id" class="w-full rounded-lg border px-1 py-2">
        <option value="">— Cơ quan ban hành — </option>
        @foreach($issuers as $i)
          <option value="{{ $i->id }}">{{ $i->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <select x-model="filters.status" class="w-full rounded-lg border px-3 py-2">
        <option value="">— Trạng thái —</option>
        @foreach($statuses as $st)
          <option value="{{ $st }}">{{ $st }}</option>
        @endforeach
      </select>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <input x-model="filters.issued_from" type="date" class="rounded-lg border px-3 py-2">
      <input x-model="filters.issued_to"   type="date" class="rounded-lg border px-3 py-2">
    </div>
    <div class="flex items-center gap-2">
      <select x-model.number="perPage" class="rounded-lg border px-6 py-2">
        <option value="10">10 dòng</option>
        <option value="20">20 dòng</option>
        <option value="50">50 dòng</option>
        <option value="100">100 dòng</option>
      </select>
      <button @click="apply()" class="rounded-lg bg-cyan-600 px-3 py-2 text-white">Lọc</button>
      <button @click="clear()" class="rounded-lg bg-slate-100 px-3 py-2">Xoá</button>
    </div>
  </div>

  <!-- Desktop table -->
  <div class="relative hidden overflow-hidden rounded-xl border bg-white shadow-sm md:block">
    <div x-show="loading" x-transition.opacity x-cloak
         class="absolute inset-0 z-10 grid place-items-center bg-white/70">
      <div class="flex items-center gap-2 text-slate-600">
        <svg class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
          <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round" class="opacity-75"/>
        </svg>
        Đang tải…
      </div>
    </div>

    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="w-16 px-3 py-2 text-left">#</th>
          <th class="w-36 px-3 py-2 text-left">Mã</th>
          <th class="px-3 py-2 text-left">Tiêu đề</th>
          <th class="w-40 px-3 py-2 text-left">Ngày ban hành</th>
          <th class="w-60 px-3 py-2 text-left">Cơ quan ban hành</th>
          <th class="w-40 px-3 py-2 text-left">Trạng thái</th>
          <th class="w-24 px-3 py-2 text-left">Tệp</th>
        </tr>
      </thead>
      <tbody class="[&>tr:nth-child(even)]:bg-slate-50/40">
        <template x-for="(r,i) in rows" :key="r.id">
          <tr class="border-t">
            <td class="px-3 py-2" x-text="(page-1)*perPage + i + 1"></td>
            <td class="px-3 py-2 font-mono" x-text="r.code"></td>
            <td class="px-3 py-2">
              <div class="font-medium" x-text="r.title"></div>
              <div class="text-xs text-slate-400" x-text="r.note"></div>
            </td>
            <td class="px-3 py-2" x-text="r.issued_date || '—'"></td>
            <td class="px-3 py-2" x-text="r.issuer?.name || '—'"></td>
            <td class="px-3 py-2">
              <span class="rounded-full px-2 py-1 text-xs"
                    :class="r.status ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-600'"
                    x-text="r.status || '—'"></span>
            </td>
            <td class="px-3 py-2">
              <template x-if="r.file_url">
                <a :href="r.file_url" target="_blank" class="text-blue-600 hover:underline">Xem</a>
              </template>
              <template x-if="!r.file_url">
                <span class="text-slate-400">—</span>
              </template>
            </td>
          </tr>
        </template>
        <tr x-show="rows.length===0">
          <td colspan="7" class="px-3 py-6 text-center text-slate-500">Không có dữ liệu</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="md:hidden space-y-3">
    <template x-for="(r,i) in rows" :key="r.id">
      <div class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-xs text-slate-500" x-text="'#'+((page-1)*perPage + i + 1)"></div>
            <div class="mt-0.5 font-medium" x-text="r.title"></div>
            <div class="text-xs text-slate-500">
              <span class="font-mono" x-text="r.code"></span>
              <span class="mx-1">•</span>
              <span x-text="r.issued_date || '—'"></span>
            </div>
          </div>
          <span class="shrink-0 rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-700"
                x-text="r.status || '—'"></span>
        </div>
        <div class="mt-2 text-sm text-slate-700" x-text="r.issuer?.name || '—'"></div>
        <div class="mt-1 line-clamp-2 text-xs text-slate-500" x-text="r.note"></div>

        <div class="mt-3">
          <template x-if="r.file_url">
            <a :href="r.file_url" target="_blank"
               class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs text-white">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 17l4 4 4-4M12 3v18"/></svg>
              Xem tệp
            </a>
          </template>
        </div>
      </div>
    </template>

    <div x-show="rows.length===0" class="rounded-xl border bg-white p-6 text-center text-slate-500">
      Không có dữ liệu
    </div>
  </div>

  <!-- Pagination -->
  <div class="mt-4 flex items-center justify-between text-sm">
    <button class="rounded-lg bg-slate-100 px-3 py-2 disabled:opacity-50"
            :disabled="page<=1" @click="goto(page-1)">Trang trước</button>
    <div class="flex items-center gap-2">
      Trang
      <input type="number" min="1" :max="lastPage" x-model.number="pageInput"
             @keydown.enter.prevent="goto(pageInput)"
             class="w-16 rounded-lg border px-3 py-2">
      trên <span x-text="lastPage"></span>
    </div>
    <button class="rounded-lg bg-slate-100 px-3 py-2 disabled:opacity-50"
            :disabled="page>=lastPage" @click="goto(page+1)">Trang sau</button>
  </div>
</div>
@endsection

@push('scripts')
<script>
function docsUI(){
  const DATA_URL   = @json(route('front.documents.data'));
  const EXPORT_URL = @json(route('front.documents.export'));

  async function fetchJson(url, params = {}){
    const u = new URL(url, window.location.origin);
    Object.entries(params).forEach(([k,v])=>{
      if (v !== '' && v !== null && v !== undefined) u.searchParams.set(k, v);
    });
    const res = await fetch(u.toString(), { headers: {Accept:'application/json'} });
    if (!res.ok) throw new Error('API '+res.status);
    return res.json();
  }

  return {
    loading: false,
    rows: [],
    page: 1, pageInput: 1, lastPage: 1, perPage: 20,
    filters: {
      q: '',
      issuer_id: '',
      status: '',
      issued_from: '',
      issued_to: ''
    },

    get exportHref(){
      const u = new URL(EXPORT_URL, window.location.origin);
      const p = { ...this.filters };
      Object.entries(p).forEach(([k,v])=>{ if(v) u.searchParams.set(k,v); });
      return u.toString();
    },

    async boot(){ await this.fetch(); },

    async fetch(){
      this.loading = true;
      try {
        const js = await fetchJson(DATA_URL, {
          page: this.page,
          per_page: this.perPage,
          ...this.filters
        });
        this.rows      = js.data || [];
        this.page      = js.current_page || 1;
        this.pageInput = this.page;
        this.lastPage  = js.last_page || 1;
        this.perPage   = js.per_page || this.perPage;
      } finally {
        this.loading = false;
      }
    },

    async goto(p){
      if (p<1) p=1; if (p>this.lastPage) p=this.lastPage;
      this.page = p; this.pageInput = p;
      await this.fetch();
    },

    async apply(){ this.page=1; await this.fetch(); },

    async clear(){
      this.filters.q = '';
      this.filters.issuer_id = '';
      this.filters.status = '';
      this.filters.issued_from = '';
      this.filters.issued_to = '';
      this.page = 1;
      await this.fetch();
    },
  }
}
</script>
@endpush
