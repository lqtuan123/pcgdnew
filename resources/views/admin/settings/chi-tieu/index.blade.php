@extends('layouts.admin')
@section('title', 'Quản lý chỉ tiêu')
@section('crumb', 'Cài đặt / Chỉ tiêu')

@section('content')
    <div class="space-y-6" x-data="chiTieuIndex">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.pcgd-chi-tieu.index') }}" class="text-slate-600 hover:text-slate-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="text-xl font-semibold">Quản lý chỉ tiêu</h2>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" 
                        x-show="selected.length > 0"
                        @click="bulkDelete()"
                        class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition">
                    🗑️ Xóa đã chọn (<span x-text="selected.length"></span>)
                </button>
                <a href="{{ route('admin.settings.chi-tieu.create') }}" class="btn-primary">+ Thêm chỉ tiêu</a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <article class="card">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-center" style="width: 40px;">
                            <input type="checkbox" 
                                   class="rounded"
                                   @change="toggleAll($event.target.checked)">
                        </th>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Mã chỉ tiêu</th>
                        <th class="th text-left">Tên chỉ tiêu</th>
                        <th class="th text-center">Số chương trình</th>
                        <th class="th text-center">Trạng thái</th>
                        <th class="th text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($chiTieus as $i => $ct)
                        <tr class="hover:bg-slate-50" :class="selected.includes({{ $ct->id }}) ? 'bg-blue-50' : ''">
                            <td class="td text-center">
                                <input type="checkbox" 
                                       class="rounded"
                                       value="{{ $ct->id }}"
                                       x-model="selected">
                            </td>
                            <td class="td">{{ $chiTieus->firstItem() + $i }}</td>
                            <td class="td">
                                <code class="text-sm bg-slate-100 px-2 py-1 rounded">{{ $ct->ma_chi_tieu }}</code>
                            </td>
                            <td class="td">
                                <div class="font-medium">{{ $ct->ten_chi_tieu }}</div>
                                @if($ct->mo_ta)
                                    <div class="text-xs text-slate-500 mt-1">{{ Str::limit($ct->mo_ta, 60) }}</div>
                                @endif
                            </td>
                            <td class="td text-center">
                                <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-sm">
                                    {{ $ct->chuong_trinhs_count }} chương trình
                                </span>
                            </td>
                            <td class="td text-center">
                                @if($ct->is_active)
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">✓ Hoạt động</span>
                                @else
                                    <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs">✕ Tắt</span>
                                @endif
                            </td>
                            <td class="td text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.settings.chi-tieu.edit', $ct) }}" 
                                       class="text-amber-600 hover:underline text-sm">
                                        Sửa
                                    </a>
                                    <span class="text-slate-300">|</span>
                                    <button type="button"
                                            @click="deleteSingle({{ $ct->id }}, '{{ $ct->ten_chi_tieu }}')"
                                            class="text-rose-600 hover:underline text-sm">
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="td text-center text-slate-500" colspan="7">Chưa có chỉ tiêu nào</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $chiTieus->links() }}
            </div>
        </article>

        {{-- Modal xác nhận xóa --}}
        <div x-show="showDeleteModal" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition>
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" @click="showDeleteModal = false"></div>
                
                <div class="relative bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
                    <h3 class="text-lg font-semibold mb-4">Xác nhận xóa</h3>
                    <p class="text-sm text-slate-600 mb-6" x-text="deleteMessage"></p>
                    
                    <div class="flex justify-end gap-3">
                        <button type="button" 
                                @click="showDeleteModal = false"
                                class="btn-soft">
                            Hủy
                        </button>
                        <button type="button"
                                @click="confirmDelete()"
                                class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700">
                            Xóa
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('chiTieuIndex', () => ({
            selected: [],
            showDeleteModal: false,
            deleteMessage: '',
            deleteType: 'single',
            deleteId: null,

            toggleAll(checked) {
                if (checked) {
                    this.selected = [...document.querySelectorAll('input[type="checkbox"][value]')]
                        .map(cb => parseInt(cb.value));
                } else {
                    this.selected = [];
                }
            },

            deleteSingle(id, name) {
                this.deleteType = 'single';
                this.deleteId = id;
                this.deleteMessage = `Bạn có chắc chắn muốn xóa chỉ tiêu "${name}"?`;
                this.showDeleteModal = true;
            },

            bulkDelete() {
                if (this.selected.length === 0) return;
                
                this.deleteType = 'bulk';
                this.deleteMessage = `Bạn có chắc chắn muốn xóa ${this.selected.length} chỉ tiêu đã chọn?`;
                this.showDeleteModal = true;
            },

            async confirmDelete() {
                try {
                    let url, data;
                    
                    if (this.deleteType === 'single') {
                        url = `/admin/settings/chi-tieu/${this.deleteId}`;
                        data = { _method: 'DELETE' };
                    } else {
                        url = '{{ route("admin.settings.chi-tieu.bulk-delete") }}';
                        data = { ids: this.selected };
                    }

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (result.success) {
                        window.location.reload();
                    } else {
                        alert(result.message || 'Có lỗi xảy ra');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi xóa');
                }
            }
        }));
    });
</script>
@endpush

