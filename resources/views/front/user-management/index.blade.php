@extends('layouts.front')

@section('title', 'Quản lý người dùng & Phân quyền')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Quản lý người dùng & Phân quyền</h1>
            <p class="mt-2 text-slate-600">Quản lý người dùng, phân quyền chức năng và khu vực</p>
        </div>
        <div class="flex items-center gap-3">
            @if(auth()->user()->hasPermission('quan_ly_nguoi_dung', 'create'))
                <a href="{{ route('front.user-management.create') }}" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Tạo người dùng mới
                </a>
            @endif
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
                    <p class="text-2xl font-bold text-green-600">{{ $functions->count() ?? 0 }}</p>
                </div>
                <div class="text-green-400">
                    <i class="fas fa-cogs text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-purple-800">Danh mục chức năng</h3>
                    <p class="text-2xl font-bold text-purple-600">{{ $categories->count() ?? 0 }}</p>
                </div>
                <div class="text-purple-400">
                    <i class="fas fa-tags text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-orange-800">Vai trò hệ thống</h3>
                    <p class="text-2xl font-bold text-orange-600">{{ $roles->count() ?? 0 }}</p>
                </div>
                <div class="text-orange-400">
                    <i class="fas fa-user-tag text-3xl"></i>
                </div>
            </div>
        </div>
    </div> --}}

   

   
    <!-- Bộ lọc -->
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Tìm kiếm</label>
                <input type="text" name="q" value="{{ request('q') }}" 
                       placeholder="Tên, email..." 
                       class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Vai trò</label>
                <select name="role" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tất cả vai trò</option>
                    <option value="province" {{ request('role') == 'province' ? 'selected' : '' }}>Cấp Tỉnh</option>
                    <option value="ward" {{ request('role') == 'ward' ? 'selected' : '' }}>Cấp Xã</option>
                    <option value="school" {{ request('role') == 'school' ? 'selected' : '' }}>Cấp Trường</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="btn-primary w-full">
                    <i class="fas fa-search mr-2"></i> Lọc
                </button>
            </div>
        </form>
    </div>

    <!-- Danh sách người dùng -->
    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Danh sách người dùng</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Thông tin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Vai trò</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Cấp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Phạm vi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                                            <i class="fas fa-user text-slate-500"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-slate-900">{{ $user->name }}</div>
                                        {{-- <div class="text-sm text-slate-500">{{ $user->email }}</div>
                                        @if($user->phone_number)
                                            <div class="text-sm text-slate-500">{{ $user->phone_number }}</div>
                                        @endif --}}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $roleInfo = $user->getRoleLevelAndScope();
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $roleInfo['role_name'] ?? 'Chưa phân quyền' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->isProvinceLevel())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        <i class="fas fa-building mr-1"></i> Cấp Tỉnh
                                    </span>
                                @elseif($user->isWardLevel())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        <i class="fas fa-map-marker-alt mr-1"></i> Cấp Xã/Phường
                                    </span>
                                @elseif($user->isSchoolLevel())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-school mr-1"></i> Cấp Trường
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <i class="fas fa-question mr-1"></i> Chưa xác định
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                <div class="text-sm">
                                    {{ $roleInfo['scope_display'] ?? 'Chưa được phân quyền' }}
                                </div>
                                @if($user->province)
                                    <div class="text-xs text-slate-400 mt-1">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        
                                        @if($user->ward)
                                           
                                        @endif
                                        @if($user->school)
                                            
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                        Hoạt động
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                        Không hoạt động
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @php
                                        // Kiểm tra quyền quản lý dựa trên cấp độ địa lý
                                        $canManage = auth()->user()->canManageUser($user);
                                    @endphp
                                    
                                    <a href="{{ route('front.user-management.permissions', $user->id) }}" class="text-indigo-600 hover:underline text-sm flex items-center gap-1" title="Xem phân quyền">
                                        <i class="fas fa-eye"></i>
                                        <span>Xem</span>
                                    </a>
                                    @if(auth()->user()->hasPermission('quan_ly_nguoi_dung', 'edit') && $canManage)
                                        <a href="{{ route('front.user-management.edit', $user->id) }}" class="text-amber-600 hover:underline text-sm flex items-center gap-1" title="Chỉnh sửa thông tin">
                                            <i class="fas fa-user-edit"></i>
                                            <span>Sửa</span>
                                        </a>
                                    @endif
                                    @if(auth()->user()->hasPermission('quan_ly_nguoi_dung', 'delete') && $canManage)
                                        <form method="POST" action="{{ route('front.user-management.destroy', $user->id) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa người dùng này?')">
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
                            <td colspan="6" class="px-6 py-4 text-center text-slate-500">
                                Không có dữ liệu.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>
@endsection

