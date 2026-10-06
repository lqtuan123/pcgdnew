@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination" class="flex items-center justify-between text-sm">
    {{-- Trái: mô tả số bản ghi --}}
    @if ($paginator->firstItem())
        <div class="text-slate-600">
            Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }}
        </div>
    @else
        <div class="text-slate-600">Hiển thị {{ $paginator->count() }} mục</div>
    @endif

    {{-- Phải: nút phân trang kiểu “pill” --}}
    <div class="inline-flex overflow-hidden rounded-xl border bg-white/70 shadow-sm dark:bg-slate-900/70 dark:border-slate-800">
        {{-- Prev --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1.5 text-slate-400 select-none">Trước</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Trước</a>
        @endif

        {{-- Pages --}}
        @foreach ($elements as $element)
            {{-- Dấu ... --}}
            @if (is_string($element))
                <span class="px-3 py-1.5 text-slate-400">{{ $element }}</span>
            @endif

            {{-- Danh sách trang --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="px-3 py-1.5 bg-blue-600 text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Sau</a>
        @else
            <span class="px-3 py-1.5 text-slate-400 select-none">Sau</span>
        @endif
    </div>
</nav>
@endif
