<div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
    <div class="p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $logo->name ?: ucfirst($logo->type) }}</h3>
                <p class="text-sm text-gray-600">{{ $logo->description ?: 'Không có mô tả' }}</p>
                <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded mt-2">
                    {{ ucfirst($logo->type) }}
                </span>
            </div>
            <div class="text-right">
                @if($logo->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>Đang dùng
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <i class="fas fa-clock mr-1"></i>Chưa dùng
                    </span>
                @endif
            </div>
        </div>

        <!-- Ảnh gốc -->
        <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Ảnh gốc:</h4>
            <img src="{{ Storage::url($logo->original_path) }}" alt="Original" class="max-w-full h-20 object-contain border border-gray-200 rounded">
        </div>

        <!-- Các kích thước -->
        <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Các kích thước:</h4>
            <div class="grid grid-cols-2 gap-2">
                @foreach($logo->sizes as $size)
                <div class="border border-gray-200 rounded p-2">
                    <div class="text-xs text-gray-600 mb-1">{{ $size->width }}x{{ $size->height }}</div>
                    <img src="{{ Storage::url($size->path) }}" alt="{{ $size->width }}x{{ $size->height }}" class="w-full max-height object-contain">
                    <div class="mt-1">
                        @if($size->is_active)
                            <span class="text-xs text-green-600 font-medium">Đang dùng</span>
                        @else
                            <form action="{{ route('admin.settings.logos.activate-size', [$logo, $size]) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
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
        <div class="flex space-x-2">
            @if(!$logo->is_active)
            <form action="{{ route('admin.settings.logos.activate', $logo) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-3 rounded">
                    <i class="fas fa-check mr-1"></i>Kích hoạt
                </button>
            </form>
            @endif
            <form action="{{ route('admin.settings.logos.destroy', $logo) }}" method="POST" class="flex-1">
                @csrf @method('DELETE')
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 px-3 rounded" 
                        onclick="return confirm('Bạn có chắc muốn xóa {{ $logo->name ?: $logo->type }} này?')">
                    <i class="fas fa-trash mr-1"></i>Xóa
                </button>
            </form>
        </div>
    </div>
</div>
