@extends('layouts.admin')
@section('title', 'Cấu hình hiển thị thập phân')
@section('crumb', 'Cài đặt / Cấu hình hiển thị thập phân')

@section('content')
    <style>
        [x-cloak] {
            display: none !important
        }
    </style>
    <div class="space-y-6" x-data="decimalFormatIdx">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.decimal-formats.create') }}" class="btn-primary">+ Thêm cấu hình</a>
            <div class="ml-auto"></div>
        </div>

        <article class="card">
            <div class="sm:hidden text-xs text-slate-500 mt-1 px-1">Kéo ngang để xem thêm →</div>
            <div class="-mx-4 mt-3 overflow-x-auto overflow-y-visible select-none md:select-auto" style="-webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; touch-action: pan-x;">
                <table class="min-w-[760px] sm:min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th text-left whitespace-nowrap">#</th>
                            <th class="th text-left">Tên cấu hình</th>
                            <th class="th text-left whitespace-nowrap">Dấu tách thập phân</th>
                            <th class="th text-left whitespace-nowrap">Dấu tách hàng nghìn</th>
                            <th class="th text-left whitespace-nowrap">Ví dụ</th>
                            <th class="th text-center whitespace-nowrap">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($formats as $i => $format)
                            <tr class="hover:bg-slate-50">
                                <td class="td whitespace-nowrap">{{ $i + 1 }}</td>
                                <td class="td">{{ $format->name }}</td>
                                <td class="td font-mono text-center whitespace-nowrap">{{ $format->decimal_separator }}</td>
                                <td class="td font-mono text-center whitespace-nowrap">{{ $format->thousands_separator }}</td>
                                <td class="td font-mono text-sm whitespace-nowrap">
                                    {{ $format->formatNumber(1234567.89) }}
                                </td>
                                <td class="td text-right whitespace-nowrap">
                                    <!-- Desktop: Hiển thị các nút inline -->
                                    <div class="hidden md:flex items-center gap-2">
                                        <a href="{{ route('admin.settings.decimal-formats.edit', $format) }}"
                                           class="text-amber-600 hover:underline text-sm">
                                            Sửa
                                        </a>
                                    
                                        <button type="button"
                                            class="text-rose-600 hover:underline text-sm"
                                            @click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.decimal-formats.destroy', $format) }}', name: @js($format->name)})">
                                            Xoá
                                        </button>
                                    </div>

                                    <!-- Mobile: Hiển thị dropdown (teleport ra body để không bị che) -->
                                    <div class="md:hidden" x-data="{ open: false, dropdownStyle: '' }">
                                        <button @click="open = !open; $nextTick(() => positionDropdown($el))"
                                            class="text-slate-600 hover:text-slate-800 p-1 rounded">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                            </svg>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="open" x-transition @click.away="open = false"
                                                class="fixed z-[200] bg-white rounded-md shadow-lg border w-48"
                                                x-ref="dropdown"
                                                :style="dropdownStyle">
                                                <div class="py-1">
                                                    <a href="{{ route('admin.settings.decimal-formats.edit', $format) }}"
                                                        class="block px-4 py-2 text-sm text-amber-600 hover:bg-amber-50">
                                                        Sửa
                                                    </a>

                                                    <button
                                                        @click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.decimal-formats.destroy', $format) }}', name: @js($format->name)}); open = false"
                                                        class="block w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-rose-50">
                                                        Xoá
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="td" colspan="6">Không có dữ liệu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        {{-- Modal confirm xoá --}}
        <div x-data="{ open: false, action: '', name: '' }"
            @confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name">
            <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
                <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
                <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
                    <h3 class="font-semibold text-lg">Xoá cấu hình?</h3>
                    <p class="mt-2 text-sm text-slate-600">Bạn chắc chắn muốn xoá <span class="font-medium"
                            x-text="name"></span>?</p>
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
                Alpine.data('decimalFormatIdx', () => ({
                    dropdownStyle: '',
                    positionDropdown(button) {
                        let rect = button.getBoundingClientRect();
                        this.dropdownStyle =
                            `top:${rect.bottom + window.scrollY}px; left:${rect.left + window.scrollX - 160}px;`;
                    }
                }))
            })
        </script>
    @endpush
