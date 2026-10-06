@extends('layouts.admin')

@section('title', 'Quản lý phân quyền')

@section('content')
<div class="space-y-6" x-data="permissionIndex()" x-init="init()">
    <!-- Toolbar -->
    <div class="flex items-center gap-3">
        @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
            <button type="button" class="btn-primary" @click="openCreateUserModal()">
                <i class="fas fa-plus mr-2"></i> Tạo người dùng mới
            </button>
        @endif
        <a href="{{ route('admin.permissions.functions') }}" class="btn-soft">
            <i class="fas fa-cogs mr-2"></i> Quản lý chức năng
        </a>
        <div class="ml-auto"></div>
    </div>

    <!-- Thống kê nhanh -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
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
    </div>

    <!-- Bộ lọc -->
    <article class="card">
        <form id="userFilterForm" method="GET" action="{{ route('admin.permissions.index') }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-3">
                <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Tên / email người dùng…"
                    class="w-full rounded-lg border px-3 py-2"
                    @input.debounce.500ms="submitForm()"
                    @keydown.enter.prevent="submitForm()"
                >
            </div>

            <div class="md:col-span-3">
                <label class="block text-xs text-slate-500 mb-1">Vai trò</label>
                <select name="role" class="w-full rounded-lg border px-3 py-2" @change="submitForm()">
                    <option value="">— Tất cả —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(request('role') == $role->name)>{{ $role->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block text-xs text-slate-500 mb-1">Danh mục chức năng</label>
                <select name="category" class="w-full rounded-lg border px-3 py-2" @change="submitForm()">
                    <option value="">— Tất cả —</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') == $category)>{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3 flex items-end gap-2">
                <button class="btn-primary">Lọc</button>
                <a href="{{ route('admin.permissions.index') }}" class="btn-soft">Xoá lọc</a>
            </div>
        </form>
    </article>

    <!-- Bảng danh sách -->
    <article class="card overflow-hidden">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Danh sách người dùng</h2>
            <div class="flex items-center gap-2">
                <div class="text-sm text-slate-500">Tổng: {{ $users->count() }}</div>
            </div>
        </div>

        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Tên</th>
                        <th class="th text-left">Email</th>
                        <th class="th text-left">Vai trò</th>
                        <th class="th text-left">Role Level</th>
                        <th class="th text-left">Phạm vi</th>
                        <th class="th text-left">Trạng thái</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($users as $i => $user)
                        <tr class="hover:bg-slate-50">
                            <td class="td">{{ $i + 1 }}</td>
                            <td class="td font-medium">{{ $user->name }}</td>
                            <td class="td">{{ $user->email }}</td>
                            <td class="td">
                                <span class="badge badge-primary">
                                    {{ $user->getRoleDisplayName() }}
                                </span>
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
                                <div class="text-xs text-slate-600">
                                    {{ $roleInfo['scope'] }}
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
                                        <button class="text-blue-600 hover:underline text-sm" @click="viewUser({{ $user->id }})">
                                            Xem
                                        </button>
                                    @endif
                                    @if(auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="text-amber-600 hover:underline text-sm">
                                            Sửa
                                        </a>
                                        <button class="text-purple-600 hover:underline text-sm" @click="managePermissions({{ $user->id }})">
                                            Phân quyền
                                        </button>
                                    @endif
                                    @if((auth()->user()->role_id == 1 || auth()->user()->role_id == 2) && $user->id !== auth()->id())
                                        <button class="text-rose-600 hover:underline text-sm" @click="deleteUser({{ $user->id }})">
                                            Xoá
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="td" colspan="9">Không có dữ liệu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>

<!-- Modal tạo người dùng mới -->
<div x-data="{ open: false }" x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center" x-cloak>
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
    
    <div class="relative bg-white rounded-xl p-6 w-[800px] max-h-[90vh] overflow-y-auto shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-lg">Tạo người dùng mới</h3>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="open = false">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form @submit.prevent="createUser()">
            @csrf
            <div class="space-y-4">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tên người dùng *</label>
                        <input type="text" class="w-full rounded-lg border px-3 py-2" name="name" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                        <input type="email" class="w-full rounded-lg border px-3 py-2" name="email" required>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Số điện thoại</label>
                        <input type="text" class="w-full rounded-lg border px-3 py-2" name="phone_number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Vai trò *</label>
                        <select class="w-full rounded-lg border px-3 py-2" name="role" required>
                            <option value="">Chọn vai trò</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <!-- Phân quyền chức năng -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Phân quyền chức năng</label>
                    <div class="grid md:grid-cols-2 gap-4">
                        @foreach($categories as $category)
                            <div class="border rounded-lg p-4">
                                <h5 class="font-medium text-slate-800 mb-3">{{ ucfirst($category) }}</h5>
                                <div class="space-y-2">
                                    @foreach($functions->where('category', $category) as $function)
                                        <div class="border-b pb-2 last:border-b-0">
                                            <label class="text-sm font-medium text-slate-700">{{ $function->name }}</label>
                                            <div class="grid grid-cols-4 gap-2 mt-1">
                                                <label class="flex items-center">
                                                    <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][view]">
                                                    <span class="ml-2 text-xs">Xem</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][create]">
                                                    <span class="ml-2 text-xs">Tạo</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][edit]">
                                                    <span class="ml-2 text-xs">Sửa</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][delete]">
                                                    <span class="ml-2 text-xs">Xóa</span>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" class="btn-soft" @click="open = false">Hủy</button>
                <button type="submit" class="btn-primary">Tạo người dùng</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal phân quyền -->
<div x-data="{ open: false, userId: null }" x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center" x-cloak>
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
    
    <div class="relative bg-white rounded-xl p-6 w-[800px] max-h-[90vh] overflow-y-auto shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-lg">Phân quyền chức năng</h3>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="open = false">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form @submit.prevent="savePermissions()">
            @csrf
            <input type="hidden" :name="'user_id'" :value="userId">
            <div id="permissionContainer">
                <!-- Nội dung sẽ được load bằng AJAX -->
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" class="btn-soft" @click="open = false">Hủy</button>
                <button type="submit" class="btn-primary">Lưu phân quyền</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal xem thông tin user -->
<div x-data="{ open: false, user: null }" x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center" x-cloak>
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
    
    <div class="relative bg-white rounded-xl p-6 w-[600px] max-h-[90vh] overflow-y-auto shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-lg">Thông tin người dùng</h3>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="open = false">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="viewUserContent">
            <!-- Nội dung sẽ được load bằng AJAX -->
        </div>
        
        <div class="flex items-center justify-end gap-3 mt-6">
            <button type="button" class="btn-soft" @click="open = false">Đóng</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('permissionIndex', () => ({
        init() {
            // Khởi tạo
        },
        
        submitForm() {
            document.getElementById('userFilterForm')?.submit();
        },
        
        openCreateUserModal() {
            this.$refs.createUserModal?.open = true;
        },
        
        async createUser() {
            const form = event.target;
            const formData = new FormData(form);
            const permissions = {};
            
            // Thu thập permissions
            form.querySelectorAll('input[name^="permissions["]').forEach(input => {
                const name = input.name;
                const matches = name.match(/permissions\[([^\]]+)\]\[([^\]]+)\]/);
                if (matches) {
                    const functionCode = matches[1];
                    const action = matches[2];
                    if (!permissions[functionCode]) {
                        permissions[functionCode] = {};
                    }
                    permissions[functionCode][action] = input.checked;
                }
            });
            
            // Xóa permissions cũ khỏi FormData và thêm JSON
            formData.delete('permissions');
            formData.append('permissions', JSON.stringify(permissions));
            
            try {
                const response = await fetch('{{ route("admin.permissions.api.create.user") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const result = await response.json();
                
                if (result.ok) {
                    alert('Tạo người dùng thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi tạo người dùng');
            }
        },
        
        async viewUser(userId) {
            try {
                const response = await fetch(`{{ route("admin.permissions.api.user.permissions", ":id") }}`.replace(':id', userId));
                const result = await response.json();
                
                if (result.ok) {
                    const user = result.data.user;
                    const permissions = result.data.permissions;
                    
                    let content = `
                        <div class="space-y-4">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <h5 class="font-medium text-slate-800">Thông tin cơ bản</h5>
                                    <p class="text-sm text-slate-600"><strong>Tên:</strong> ${user.name}</p>
                                    <p class="text-sm text-slate-600"><strong>Email:</strong> ${user.email}</p>
                                    <p class="text-sm text-slate-600"><strong>Vai trò:</strong> ${user.role_display}</p>
                                </div>
                                <div>
                                    <h5 class="font-medium text-slate-800">Phân quyền chức năng</h5>
                    `;
                    
                    for (const [code, perms] of Object.entries(permissions)) {
                        content += `<p class="text-sm text-slate-600"><strong>${code}:</strong> `;
                        const actions = [];
                        if (perms.view) actions.push('Xem');
                        if (perms.create) actions.push('Tạo');
                        if (perms.edit) actions.push('Sửa');
                        if (perms.delete) actions.push('Xóa');
                        content += actions.join(', ') + '</p>';
                    }
                    
                    content += '</div></div></div>';
                    document.getElementById('viewUserContent').innerHTML = content;
                    this.$refs.viewUserModal?.open = true;
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi tải thông tin người dùng');
            }
        },
        
        async managePermissions(userId) {
            this.userId = userId;
            await this.loadUserPermissions(userId);
            this.$refs.permissionModal?.open = true;
        },
        
        async loadUserPermissions(userId) {
            try {
                const response = await fetch(`{{ route("admin.permissions.api.user.permissions", ":id") }}`.replace(':id', userId));
                const result = await response.json();
                
                if (result.ok) {
                    const user = result.data.user;
                    const permissions = result.data.permissions;
                    
                    let content = `
                        <h5 class="font-medium text-slate-800 mb-4">Phân quyền cho: ${user.name}</h5>
                        <div class="grid md:grid-cols-2 gap-4">
                    `;
                    
                    @foreach($categories as $category)
                        content += `
                            <div class="border rounded-lg p-4">
                                <h6 class="font-medium text-slate-800 mb-3">{{ ucfirst($category) }}</h6>
                                <div class="space-y-2">
                        `;
                        
                        @foreach($functions->where('category', $category) as $function)
                            const perms = permissions['{{ $function->code }}'] || {view: false, create: false, edit: false, delete: false};
                            content += `
                                <div class="border-b pb-2 last:border-b-0">
                                    <label class="text-sm font-medium text-slate-700">{{ $function->name }}</label>
                                    <div class="grid grid-cols-4 gap-2 mt-1">
                                        <label class="flex items-center">
                                            <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][view]" ${perms.view ? 'checked' : ''}>
                                            <span class="ml-2 text-xs">Xem</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][create]" ${perms.create ? 'checked' : ''}>
                                            <span class="ml-2 text-xs">Tạo</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][edit]" ${perms.edit ? 'checked' : ''}>
                                            <span class="ml-2 text-xs">Sửa</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" class="rounded" name="permissions[{{ $function->code }}][delete]" ${perms.delete ? 'checked' : ''}>
                                            <span class="ml-2 text-xs">Xóa</span>
                                        </label>
                                    </div>
                                </div>
                            `;
                        @endforeach
                        
                        content += '</div></div>';
                    @endforeach
                    
                    content += '</div>';
                    document.getElementById('permissionContainer').innerHTML = content;
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi tải phân quyền');
            }
        },
        
        async savePermissions() {
            const form = event.target;
            const permissions = {};
            
            // Thu thập permissions từ modal
            form.querySelectorAll('input[name^="permissions["]').forEach(input => {
                const name = input.name;
                const matches = name.match(/permissions\[([^\]]+)\]\[([^\]]+)\]/);
                if (matches) {
                    const functionCode = matches[1];
                    const action = matches[2];
                    if (!permissions[functionCode]) {
                        permissions[functionCode] = {};
                    }
                    permissions[functionCode][action] = input.checked;
                }
            });
            
            try {
                const response = await fetch('{{ route("admin.permissions.api.assign") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        user_id: this.userId,
                        permissions: JSON.stringify(permissions) // Gửi dưới dạng JSON string
                    })
                });
                
                const result = await response.json();
                
                if (result.ok) {
                    alert('Phân quyền thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi phân quyền');
            }
        },
        
        async deleteUser(userId) {
            if (confirm('Bạn có chắc chắn muốn xóa người dùng này?')) {
                try {
                    const response = await fetch(`{{ route("admin.users.destroy", ":id") }}`.replace(':id', userId), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    
                    if (response.ok) {
                        alert('Xóa người dùng thành công!');
                        location.reload();
                    } else {
                        alert('Có lỗi xảy ra khi xóa người dùng');
                    }
                } catch (error) {
                    alert('Có lỗi xảy ra khi xóa người dùng');
                }
            }
        }
    }));
});
</script>
@endpush