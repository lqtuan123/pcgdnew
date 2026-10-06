@extends('layouts.front')

@section('title', 'Thông tin người dùng')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Thông tin người dùng</h1>
            <p class="mt-2 text-slate-600">Chi tiết thông tin của {{ $userToEdit->name }}</p>
        </div>
        <div>
            <a href="{{ route('front.user-management.index') }}" class="btn-soft">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Thông tin chi tiết người dùng -->
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Thông tin cơ bản -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-slate-800 border-b border-slate-200 pb-2">
                    <i class="fas fa-user mr-2"></i> Thông tin cơ bản
                </h3>
                
                <div class="space-y-3">
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Tên:</div>
                        <div class="flex-1 text-slate-800">{{ $userToEdit->name }}</div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Email:</div>
                        <div class="flex-1 text-slate-800">{{ $userToEdit->email }}</div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Điện thoại:</div>
                        <div class="flex-1 text-slate-800">
                            {{ $userToEdit->phone_number ?? 'Chưa cập nhật' }}
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Vai trò:</div>
                        <div class="flex-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $userToEdit->getRoleDisplayName() }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Trạng thái:</div>
                        <div class="flex-1">
                            @if($userToEdit->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                    <i class="fas fa-check-circle mr-1"></i> Hoạt động
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                    <i class="fas fa-times-circle mr-1"></i> Không hoạt động
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Địa giới quản lý -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-slate-800 border-b border-slate-200 pb-2">
                    <i class="fas fa-map-marker-alt mr-2"></i> Địa giới quản lý
                </h3>
                
                <div class="space-y-3">
                    @php
                        $roleInfo = $userToEdit->getRoleLevelAndScope();
                    @endphp
                    
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Cấp độ:</div>
                        <div class="flex-1">
                            @if($userToEdit->isProvinceLevel())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <i class="fas fa-building mr-1"></i> Cấp Tỉnh
                                </span>
                            @elseif($userToEdit->isWardLevel())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                    <i class="fas fa-map-marker-alt mr-1"></i> Cấp Xã/Phường
                                </span>
                            @elseif($userToEdit->isSchoolLevel())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-school mr-1"></i> Cấp Trường
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <i class="fas fa-question mr-1"></i> Chưa xác định
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-24 text-sm font-medium text-slate-500">Phạm vi:</div>
                        <div class="flex-1 text-slate-800">
                            {{ $roleInfo['scope_display'] ?? 'Chưa được phân quyền' }}
                        </div>
                    </div>
                    
                    @if($userToEdit->province)
                        <div class="flex items-start">
                            <div class="w-24 text-sm font-medium text-slate-500">Tỉnh:</div>
                            <div class="flex-1 text-slate-800">{{ $userToEdit->province->name ?? 'N/A' }}</div>
                        </div>
                    @endif
                    
                    @if($userToEdit->ward)
                        <div class="flex items-start">
                            <div class="w-24 text-sm font-medium text-slate-500">Xã/Phường:</div>
                            <div class="flex-1 text-slate-800">{{ $userToEdit->ward->name ?? 'N/A' }}</div>
                        </div>
                    @endif
                    
                    @if($userToEdit->school)
                        <div class="flex items-start">
                            <div class="w-24 text-sm font-medium text-slate-500">Trường:</div>
                            <div class="flex-1 text-slate-800">{{ $userToEdit->school->name ?? 'N/A' }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
