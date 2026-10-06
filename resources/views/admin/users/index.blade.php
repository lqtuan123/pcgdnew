@extends('layouts.admin')

@section('title', 'Quản lý người dùng & Phân quyền')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Quản lý người dùng & Phân quyền</h1>
            <p class="mt-2 text-slate-600">Quản lý người dùng, phân quyền chức năng và địa giới hành chính</p>
        </div>
        <div class="flex items-center gap-3">
            @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                <a href="{{ route('admin.users.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Tạo người dùng mới
                </a>
                <a href="{{ route('admin.users.import.create') }}" class="btn-success">
                    <i class="fas fa-upload mr-2"></i> Import Excel
                </a>
            @endif
            <a href="{{ route('admin.roles.index') }}" class="btn-secondary">
                <i class="fas fa-user-tag mr-2"></i> Quản lý vai trò
            </a>
            <a href="{{ route('admin.permissions.functions') }}" class="btn-soft">
                <i class="fas fa-cogs mr-2"></i> Quản lý chức năng
            </a>
        </div>
    </div>

    <!-- Thống kê nhanh -->
    {{-- <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-blue-800">Tổng người dùng</h3>
                    <p class="text-2xl font-bold text-blue-600">{{ $users->count() }}</p>
                </div>
                <div class="text-blue-400">
                    <i class="fas fa-users text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-green-800">Chức năng hệ thống</h3>
                    <p class="text-2xl font-bold text-green-600">{{ $functions->count() }}</p>
                </div>
                <div class="text-green-400">
                    <i class="fas fa-cogs text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-purple-800">Vai trò</h3>
                    <p class="text-2xl font-bold text-purple-600">{{ $roles->count() }}</p>
                </div>
                <div class="text-purple-400">
                    <i class="fas fa-shield-alt text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-orange-800">Phân quyền vai trò</h3>
                    <p class="text-2xl font-bold text-orange-600">{{ $users->where('userRole', '!=', null)->count() }}</p>
                </div>
                <div class="text-orange-400">
                    <i class="fas fa-shield-alt text-3xl"></i>
                </div>
            </div>
        </div>
    </div> --}}

    <!-- Bộ lọc -->
    <article class="card">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-3">
                <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Tên / email người dùng…"
                    class="w-full rounded-lg border px-3 py-2"
                >
            </div>

            <div class="md:col-span-3">
                <label class="block text-xs text-slate-500 mb-1">Vai trò</label>
                <select name="role" class="w-full rounded-lg border px-3 py-2">
                    <option value="">— Tất cả —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(request('role') == $role->name)>{{ $role->display_name }}</option>
                    @endforeach
                </select>
            </div>

           

            <div class="md:col-span-3 flex items-end gap-2">
                <button type="submit" class="btn-primary">Lọc</button>
                <a href="{{ route('admin.users.index') }}" class="btn-soft">Xoá lọc</a>
            </div>
        </form>
    </article>

    <!-- Bảng danh sách -->
    <article class="card overflow-hidden">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Danh sách người dùng</h2>
            <div class="flex items-center gap-2">
                <div class="text-sm text-slate-500">Tổng: {{ $users->count() }}</div>
                @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                    <button type="button" id="bulkDeleteBtn" class="btn-danger" disabled>
                        <i class="fas fa-trash mr-2"></i> Xóa đã chọn
                    </button>
                @endif
            </div>
        </div>

        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                            <th class="th text-left">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            </th>
                        @endif
                        <th class="th text-left">#</th>
                        <th class="th text-left">Tên</th>
                        <th class="th text-left">Email</th>
                        <th class="th text-left">Vai trò</th>
                        <th class="th text-left">Cấp</th>
                        <th class="th text-left">Phạm vi</th>
                        <th class="th text-left">Trạng thái</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($users as $i => $user)
                        <tr class="hover:bg-slate-50">
                            @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                                <td class="td">
                                    @if($user->role_id != 1) {{-- Không cho phép chọn superadmin --}}
                                        <input type="checkbox" name="selected_users[]" value="{{ $user->id }}" class="user-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    @endif
                                </td>
                            @endif
                            <td class="td">{{ $i + 1 }}</td>
                            <td class="td font-medium">{{ $user->name }}</td>
                            <td class="td">{{ $user->email }}</td>
                            <td class="td">
                                @if($user->role_id == 1) {{-- Superadmin không được sửa --}}
                                    <span class="badge badge-primary">
                                        {{ $user->getRoleDisplayName() }}
                                    </span>
                                @else
                                    <select name="role_{{ $user->id }}" class="role-select text-xs border rounded px-2 py-1" data-user-id="{{ $user->id }}" data-current-role="{{ $user->role_id }}">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->role_id == $role->id)>{{ $role->display_name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="td">
                                @php
                                    $roleInfo = $user->getRoleLevelAndScope();
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $roleInfo['role_level'] }}
                                </span>
                            </td>
                            <td class="td">
                                <div class="text-sm text-slate-800 font-medium">
                                    {{ $roleInfo['scope_display'] ?? $roleInfo['scope_name'] ?? 'Chưa được phân quyền' }}
                                </div>
                            </td>
                            <td class="td">
                                @if($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Hoạt động
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                        Không hoạt động
                                    </span>
                                @endif
                            </td>
                            <td class="td text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2 || auth()->user()->role_id == 3)
                                        <a href="{{ route('admin.permissions.user.view', $user->id) }}" class="text-indigo-600 hover:underline text-sm flex items-center gap-1" title="Xem phân quyền">
                                            <i class="fas fa-eye"></i>
                                            <span>Xem</span>
                                        </a>
                                    @endif
                                    @if((auth()->user()->role_id == 1 || auth()->user()->role_id == 2) && $user->id !== auth()->id())
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="text-amber-600 hover:underline text-sm flex items-center gap-1" title="Chỉnh sửa thông tin">
                                            <i class="fas fa-user-edit"></i>
                                            <span>Sửa</span>
                                        </a>
                                    @endif
                                    @if((auth()->user()->role_id == 1 || auth()->user()->role_id == 2) && $user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa người dùng này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:underline text-sm flex items-center gap-1" title="Xóa người dùng">
                                                <i class="fas fa-trash"></i>
                                                <span>Xóa</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="td" colspan="{{ auth()->user()->role_id == 1 || auth()->user()->role_id == 2 ? '9' : '8' }}">Không có dữ liệu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-4">Xác nhận xóa</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Bạn có chắc chắn muốn xóa <span id="selectedCount">0</span> người dùng đã chọn?
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmBulkDelete" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-24 mr-2 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300">
                    Xóa
                </button>
                <button id="cancelBulkDelete" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-24 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Hủy
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bulk delete functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteModal = document.getElementById('bulkDeleteModal');
    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDelete');
    const cancelBulkDeleteBtn = document.getElementById('cancelBulkDelete');
    const selectedCountSpan = document.getElementById('selectedCount');

    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkDeleteButton();
        });
    }

    // Individual checkbox change
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkDeleteButton();
            updateSelectAllCheckbox();
        });
    });

    function updateBulkDeleteButton() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        if (checkedBoxes.length > 0) {
            bulkDeleteBtn.disabled = false;
            bulkDeleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            bulkDeleteBtn.disabled = true;
            bulkDeleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    function updateSelectAllCheckbox() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const totalBoxes = userCheckboxes.length;
        
        if (checkedBoxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedBoxes.length === totalBoxes) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Bulk delete button click
    bulkDeleteBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        selectedCountSpan.textContent = checkedBoxes.length;
        bulkDeleteModal.classList.remove('hidden');
    });

    // Cancel bulk delete
    cancelBulkDeleteBtn.addEventListener('click', function() {
        bulkDeleteModal.classList.add('hidden');
    });

    // Confirm bulk delete
    confirmBulkDeleteBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const userIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("admin.users.bulk-delete") }}';
        
        // Add CSRF token
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        // Add user IDs
        userIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    });

    // Quick role edit functionality
    const roleSelects = document.querySelectorAll('.role-select');
    
    roleSelects.forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const newRoleId = this.value;
            const currentRoleId = this.dataset.currentRole;
            
            if (newRoleId === currentRoleId) {
                return; // No change
            }
            
            if (confirm('Bạn có chắc chắn muốn thay đổi vai trò của người dùng này?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("admin.users.quick-update-role", ":id") }}'.replace(':id', userId);
                
                // Add CSRF token
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                // Add method override
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'PATCH';
                form.appendChild(methodInput);
                
                // Add role
                const roleInput = document.createElement('input');
                roleInput.type = 'hidden';
                roleInput.name = 'role_id';
                roleInput.value = newRoleId;
                form.appendChild(roleInput);
                
                document.body.appendChild(form);
                form.submit();
            } else {
                // Reset to original value
                this.value = currentRoleId;
            }
        });
    });
});
</script>
@endpush