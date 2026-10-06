@extends('layouts.admin')

@section('title', 'Chi tiết Logo')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('admin.settings.logos.index') }}" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Chi tiết Logo</h1>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">{{ $logo->name ?: ucfirst($logo->type) }}</h2>
                        <p class="text-sm text-gray-600">{{ $logo->description ?: 'Không có mô tả' }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                            {{ ucfirst($logo->type) }}
                        </span>
                        @if($logo->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2">
                                <i class="fas fa-check-circle mr-1"></i>Đang dùng
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="p-6">
                <!-- Thông tin cơ bản -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Thông tin cơ bản</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tên:</dt>
                                <dd class="text-sm text-gray-900">{{ $logo->name ?: 'Không có tên' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Loại:</dt>
                                <dd class="text-sm text-gray-900">{{ ucfirst($logo->type) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Mô tả:</dt>
                                <dd class="text-sm text-gray-900">{{ $logo->description ?: 'Không có mô tả' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Ngày tạo:</dt>
                                <dd class="text-sm text-gray-900">{{ $logo->created_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Trạng thái:</dt>
                                <dd class="text-sm">
                                    @if($logo->is_active)
                                        <span class="text-green-600 font-medium">Đang hoạt động</span>
                                    @else
                                        <span class="text-gray-600">Chưa kích hoạt</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Ảnh gốc</h3>
                        <div class="border border-gray-200 rounded-lg p-4">
                                                            <img src="{{ Storage::url($logo->original_path) }}" 
                                     alt="Original" 
                                     style="max-width: 100%; height: 128px; object-fit: contain; margin: 0 auto;">
                            <div class="text-center mt-2">
                                <span class="text-xs text-gray-500">File gốc</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Các kích thước -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Các kích thước có sẵn</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach($logo->sizes as $size)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="text-center">
                                <div class="text-sm font-medium text-gray-900 mb-2">{{ $size->width }}x{{ $size->height }}</div>
                                <img src="{{ Storage::url($size->path) }}" 
                                     alt="{{ $size->width }}x{{ $size->height }}" 
                                     class="w-full max-height object-contain">
                                <div class="text-xs text-gray-600 mb-2">
                                    @if($size->is_active)
                                        <span class="text-green-600 font-medium">Đang dùng</span>
                                    @else
                                        <span class="text-gray-500">Chưa dùng</span>
                                    @endif
                                </div>
                                @if(!$size->is_active)
                                <form action="{{ route('admin.settings.logos.activate-size', [$logo, $size]) }}" method="POST">
                                    @csrf
                                    <button type="submit" style="font-size: 12px; color: #2563eb; font-weight: 500; background: none; border: none; cursor: pointer; padding: 0;" onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                                        Dùng size này
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Hành động -->
                <div class="flex space-x-4 pt-6 border-t border-gray-200">
                    <!-- Debug: Logo active status = {{ $logo->is_active ? 'true' : 'false' }} -->
                    @if(!$logo->is_active)
                    <form action="{{ route('admin.settings.logos.activate', $logo) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full bg-blue-600 hover:bg-red-700 text-white text-sm font-medium py-2 px-3 rounded">
                            <i class="fas fa-check mr-2"></i>Kích hoạt
                        </button>
                    </form>
                    @else
                    <span style="background-color: #059669; color: white; font-weight: bold; padding: 8px 16px; border-radius: 4px;">
                        <i class="fas fa-check mr-2"></i>Đã kích hoạt
                    </span>
                    @endif
                    <form action="{{ route('admin.settings.logos.destroy', $logo) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit" style="background-color: #dc2626; color: white; font-weight: bold; padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer;" onmouseover="this.style.backgroundColor='#b91c1c'" onmouseout="this.style.backgroundColor='#dc2626'" 
                                onclick="return confirm('Bạn có chắc muốn xóa {{ $logo->name ?: $logo->type }} này?')">
                            <i class="fas fa-trash mr-2"></i>Xóa
                        </button>
                    </form>
                    <a href="{{ route('admin.settings.logos.index') }}" style="background-color: #d1d5db; color: #1f2937; font-weight: bold; padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-block;" onmouseover="this.style.backgroundColor='#9ca3af'" onmouseout="this.style.backgroundColor='#d1d5db'">
                        Quay lại danh sách
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
