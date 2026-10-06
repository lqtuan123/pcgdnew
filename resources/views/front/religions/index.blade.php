{{-- resources/views/front/religions/index.blade.php --}}
@extends('layouts.front')
@section('title','Danh mục Tôn giáo')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="religionsUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Danh mục Tôn giáo</h1>
    <a :href="exportHref" class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
      Xuất excel
    </a>
  </div>

  {{-- Bộ lọc --}}
  <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-6">
    <div class="sm:col-span-2">
      <input type="text" x-model.trim="filters.q" placeholder="Tìm theo mã / tên…"
             class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <select x-model="filters.official" class="w-full rounded-lg border px-3 py-2">
        <option value="all">— Tất cả —</option>
        <option value="only">Được công nhận</option>
        <option value="exclude">Khác</option>
      </select>
    </div>
    <div class="sm:col-span-2 flex items-center gap-2">
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

  {{-- LOADING overlay --}}
  <div class="relative">
    <div x-show="loading" x-transition.opacity x-cloak
         class="absolute inset-0 z-10 grid place-items-center bg-white/70 rounded-xl border">
      <div class="flex items-center gap-2 text-slate-600">
        <svg class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
        </svg>
        Đang tải…
      </div>
    </div>
  </div>

  {{-- MOBILE: Card list --}}
  <div class="md:hidden space-y-3">
    <template x-for="(r,i) in rows" :key="safeKey(r,i)">
      <div class="rounded-xl border bg-white p-3 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-xs text-slate-500">Mã</div>
            <div class="font-mono text-sm" x-text="r.code || '—'"></div>
          </div>
          <span
            :class="r.is_official
              ? 'rounded-full bg-emerald-50 text-emerald-700 border-emerald-200'
              : 'rounded-full bg-slate-50 text-slate-700 border-slate-200'"
            class="shrink-0 border px-2 py-0.5 text-xs font-medium">
            <span x-text="r.is_official ? 'Được công nhận' : 'Khác'"></span>
          </span>
        </div>
        <div class="mt-2 text-slate-900 font-semibold" x-text="r.name"></div>
        <div class="mt-2 text-sm text-slate-600" x-text="r.note || '—'"></div>
      </div>
    </template>
    <div x-show="rows.length===0" class="rounded-xl border bg-white p-4 text-center text-slate-500">
      Không có dữ liệu
    </div>
  </div>

  {{-- DESKTOP: Table --}}
  <div class="hidden md:block overflow-x-auto rounded-xl border bg-white shadow-sm">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="w-40 px-3 py-2 text-left">Mã</th>
          <th class="min-w-[280px] px-3 py-2 text-left">Tên tôn giáo</th>
          <th class="w-48 px-3 py-2 text-left">Trạng thái</th>
          <th class="px-3 py-2 text-left">Ghi chú</th>
        </tr>
      </thead>
      <tbody class="[&>tr:nth-child(even)]:bg-slate-50/40">
        <template x-for="(r,i) in rows" :key="safeKey(r,i)">
          <tr class="border-t">
            <td class="px-3 py-2 font-mono" x-text="r.code || '—'"></td>
            <td class="px-3 py-2" x-text="r.name"></td>
            <td class="px-3 py-2">
              <span x-show="r.is_official"
                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 border border-emerald-200">
                Được công nhận
              </span>
              <span x-show="!r.is_official"
                class="rounded-full bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-700 border border-slate-200">
                Khác
              </span>
            </td>
            <td class="px-3 py-2" x-text="r.note || '—'"></td>
          </tr>
        </template>
        <tr x-show="rows.length===0">
          <td colspan="4" class="px-3 py-6 text-center text-slate-500">Không có dữ liệu</td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  <div class="mt-3 flex items-center justify-between text-sm">
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
function religionsUI(){
  const DATA_URL   = @json(route('front.religions.data'));
  const EXPORT_URL = @json(route('front.religions.export'));

  async function fetchJson(params={}){
    const u = new URL(DATA_URL, window.location.origin);
    Object.entries(params).forEach(([k,v])=>{
      if (v !== '' && v !== null && v !== undefined) u.searchParams.set(k, v);
    });
    const res = await fetch(u.toString(), { headers:{Accept:'application/json'} });
    if(!res.ok) throw new Error('API '+res.status);
    return res.json();
  }

  return {
    loading:false,
    rows:[], page:1, pageInput:1, lastPage:1, perPage:20,
    filters:{ q:'', official:'all' },

    get exportHref(){
      const u = new URL(EXPORT_URL, window.location.origin);
      const params = { q:this.filters.q, official:this.filters.official };
      Object.entries(params).forEach(([k,v])=>{
        if(v!=='' && v!==null && v!==undefined) u.searchParams.set(k,v);
      });
      return u.toString();
    },

    safeKey(row, i){ return 'rg-' + (row.id ?? row.code ?? i) + '-' + i; },

    async boot(){ await this.fetch(); },

    async fetch(){
      this.loading = true;
      try {
        const js = await fetchJson({
          page: this.page, per_page: this.perPage,
          q: this.filters.q, official: this.filters.official
        });
        this.rows = js.data || [];
        this.page = js.current_page; this.pageInput = this.page;
        this.lastPage = js.last_page; this.perPage = js.per_page;
      } finally { this.loading = false; }
    },

    async goto(p){ if(p<1) p=1; if(p>this.lastPage) p=this.lastPage; this.page=p; this.pageInput=p; await this.fetch(); },
    async apply(){ this.page=1; await this.fetch(); },
    async clear(){ this.filters.q=''; this.filters.official='all'; this.page=1; await this.fetch(); },
  };
}
</script>
@endpush
