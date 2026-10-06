@extends('layouts.admin')

@section('title', 'Quản lý vai trò')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Quản lý vai trò</h1>
            <p class="mt-2 text-slate-600">Quản lý các vai trò và phân quyền trong hệ thống</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-soft">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
            {{-- Nút tạo vai trò mới - hiển thị cho super admin và admin --}}
            @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                <a href="{{ route('admin.roles.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Tạo vai trò mới
                </a>
            @endif
        </div>
    </div>

    <!-- Bộ lọc và hành động -->
    <article class="card">
        <form method="GET" action="{{ route('admin.roles.index') }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
                <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Tên vai trò / tên hiển thị…"
                    class="w-full rounded-lg border px-3 py-2"
                >
            </div>

            <div class="md:col-span-3 flex items-end gap-2">
                <button type="submit" class="btn-primary">Lọc</button>
                <a href="{{ route('admin.roles.index') }}" class="btn-soft">Xoá lọc</a>
            </div>
        </form>
    </article>

    <!-- Bảng danh sách -->
    <article class="card overflow-hidden">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Danh sách vai trò</h2>
            <div class="flex items-center gap-2">
                <div class="text-sm text-slate-500">Tổng: {{ $roles->count() }}</div>
                <button type="button" id="selectAllBtn" class="text-sm text-indigo-600 hover:underline">
                    Chọn tất cả
                </button>
            </div>
        </div>
        @if(session('success'))
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-emerald-600 mr-2"></i>
                    <span class="text-emerald-800">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-4 bg-rose-50 border border-rose-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-rose-600 mr-2"></i>
                    <span class="text-rose-800">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Bulk Actions -->
        <div id="bulkActions" class="hidden mb-4 p-4 bg-slate-50 border border-slate-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600">
                        <span id="selectedCount">0</span> vai trò được chọn
                    </span>
                    <button type="button" id="bulkDeleteBtn" class="text-rose-600 hover:underline text-sm flex items-center gap-1">
                        <i class="fas fa-trash"></i>
                        <span>Xóa đã chọn</span>
                    </button>
                </div>
                <button type="button" id="clearSelectionBtn" class="text-slate-500 hover:text-slate-700 text-sm">
                    Bỏ chọn tất cả
                </button>
            </div>
        </div>

        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-left w-12">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded">
                        </th>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Tên vai trò</th>
                        <th class="th text-left">Tên hiển thị</th>
                        <th class="th text-left">Số người dùng</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($roles as $i => $role)
                        <tr class="hover:bg-slate-50">
                            <td class="td">
                                <input type="checkbox" class="role-checkbox rounded" value="{{ $role->id }}" 
                                       data-role-name="{{ $role->display_name }}"
                                       {{ (!$role->is_system ?? true) ? '' : 'disabled' }}>
                            </td>
                            <td class="td">{{ $i + 1 }}</td>
                            <td class="td">
                                <code class="bg-slate-100 text-slate-800 px-2 py-1 rounded text-sm">{{ $role->name }}</code>
                            </td>
                            <td class="td font-medium">{{ $role->display_name }}</td>
                            <td class="td">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                    {{ $role->users_count }}
                                </span>
                            </td>
                            <td class="td text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.roles.show', $role) }}" class="text-indigo-600 hover:underline text-sm flex items-center gap-1" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                        <span>Xem</span>
                                    </a>
                                    
                                    {{-- Nút Sửa - hiển thị cho tất cả user có quyền admin --}}
                                    @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="text-amber-600 hover:underline text-sm flex items-center gap-1" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                            <span>Sửa</span>
                                        </a>
                                    @endif
                                    
                                    {{-- Nút Xóa - chỉ hiển thị cho super admin và không cho phép xóa role hệ thống --}}
                                    @if((auth()->user()->role_id == 1) && (!$role->is_system ?? true))
                                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa vai trò này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:underline text-sm flex items-center gap-1" title="Xóa vai trò">
                                                <i class="fas fa-trash"></i>
                                                <span>Xóa</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="td" colspan="6">Không có vai trò nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>

<!-- Bulk Delete Form -->
<form id="bulkDeleteForm" method="POST" action="{{ route('admin.roles.bulk-delete') }}" style="display: none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="role_ids" id="bulkDeleteIds">
</form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const roleCheckboxes = document.querySelectorAll('.role-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');
    const bulkDeleteIds = document.getElementById('bulkDeleteIds');

    // Cập nhật trạng thái bulk actions
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.role-checkbox:checked');
        const count = checkedBoxes.length;
        
        selectedCount.textContent = count;
        
        if (count > 0) {
            bulkActions.classList.remove('hidden');
        } else {
            bulkActions.classList.add('hidden');
        }
        
        // Cập nhật trạng thái select all
        if (count === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (count === roleCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        roleCheckboxes.forEach(checkbox => {
            if (!checkbox.disabled) {
                checkbox.checked = isChecked;
            }
        });
        updateBulkActions();
    });

    // Individual checkboxes
    roleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    // Select all button
    selectAllBtn.addEventListener('click', function() {
        roleCheckboxes.forEach(checkbox => {
            if (!checkbox.disabled) {
                checkbox.checked = true;
            }
        });
        updateBulkActions();
    });

    // Clear selection button
    clearSelectionBtn.addEventListener('click', function() {
        roleCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateBulkActions();
    });

    // Bulk delete
    bulkDeleteBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.role-checkbox:checked');
        const roleIds = Array.from(checkedBoxes).map(checkbox => checkbox.value);
        const roleNames = Array.from(checkedBoxes).map(checkbox => checkbox.dataset.roleName);
        
        if (roleIds.length === 0) {
            alert('Vui lòng chọn ít nhất một vai trò để xóa.');
            return;
        }
        
        const confirmMessage = `Bạn có chắc chắn muốn xóa ${roleIds.length} vai trò sau?\n\n${roleNames.join('\n')}\n\nHành động này không thể hoàn tác!`;
        
        if (confirm(confirmMessage)) {
            bulkDeleteIds.value = roleIds.join(',');
            bulkDeleteForm.submit();
        }
    });

    // Khởi tạo trạng thái ban đầu
    updateBulkActions();
});
</script>
@endpush
