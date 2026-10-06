@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination" class="flex items-center justify-end text-sm">
  <div class="inline-flex overflow-hidden rounded-xl border bg-white/70 shadow-sm dark:bg-slate-900/70 dark:border-slate-800">
    @if ($paginator->onFirstPage())
      <span class="px-3 py-1.5 text-slate-400 select-none">Trước</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Trước</a>
    @endif

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Sau</a>
    @else
      <span class="px-3 py-1.5 text-slate-400 select-none">Sau</span>
    @endif
  </div>
</nav>
@endif
