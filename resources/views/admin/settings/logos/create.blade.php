@extends('layouts.admin')

@section('title', 'Tải lên Logo & Favicon')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('admin.settings.logos.index') }}" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Tải lên Logo & Favicon</h1>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <form action="{{ route('admin.settings.logos.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
        
                <!-- Loại -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Loại *</label>
                    <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Chọn loại</option>
                        <option value="logo" {{ old('type') == 'logo' ? 'selected' : '' }}>Logo</option>
                        <option value="favicon" {{ old('type') == 'favicon' ? 'selected' : '' }}>Favicon</option>
                        <option value="email" {{ old('type') == 'email' ? 'selected' : '' }}>Email</option>
                    </select>
                    @error('type')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tên -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tên</label>
                    <input type="text" name="name" value="{{ old('name') }}" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="Tên logo (Sẽ dùng làm tên của website)">
                    @error('name')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Mô tả -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                    <textarea name="description" rows="3" 
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Mô tả logo (tùy chọn)">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- File upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File *</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                        <input type="file" name="file" id="file" class="hidden" required accept="image/*">
                        <label for="file" class="cursor-pointer">
                            <div class="text-gray-400 text-4xl mb-4">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="text-lg font-medium text-gray-900 mb-2">
                                Chọn file hoặc kéo thả vào đây
                            </div>
                            <div class="text-sm text-gray-600">
                                PNG, JPG, JPEG, SVG, ICO (tối đa 2MB)
                            </div>
                        </label>
                    </div>
                    @error('file')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Preview -->
                <div id="preview" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Xem trước:</label>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <img id="preview-image" class="max-w-full h-32 object-contain mx-auto">
                        <div class="text-center mt-2">
                            <span id="file-info" class="text-sm text-gray-600"></span>
                        </div>
                    </div>
                </div>

                <!-- Thông tin kích thước -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-blue-900 mb-2">Kích thước sẽ được tạo:</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div id="logo-sizes" class="hidden">
                            <div class="text-blue-700">Logo: 64x64, 128x128, 256x256, 512x512</div>
                        </div>
                        <div id="favicon-sizes" class="hidden">
                            <div class="text-blue-700">Favicon: 16x16, 32x32, 48x48, 64x64, 128x128, 192x192</div>
                        </div>
                        <div id="email-sizes" class="hidden">
                            <div class="text-blue-700">Email: 200x60, 300x90, 400x120</div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-4">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                        <i class="fas fa-upload mr-2"></i>Tải lên
                    </button>
                    <a href="{{ route('admin.settings.logos.index') }}" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-4 rounded-lg text-center transition-colors">
                        Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file');
    const preview = document.getElementById('preview');
    const previewImage = document.getElementById('preview-image');
    const fileInfo = document.getElementById('file-info');
    const typeSelect = document.querySelector('select[name="type"]');
    
    // Show/hide size info based on type
    function updateSizeInfo() {
        const type = typeSelect.value;
        document.getElementById('logo-sizes').classList.add('hidden');
        document.getElementById('favicon-sizes').classList.add('hidden');
        document.getElementById('email-sizes').classList.add('hidden');
        
        if (type) {
            document.getElementById(type + '-sizes').classList.remove('hidden');
        }
    }
    
    typeSelect.addEventListener('change', updateSizeInfo);
    
    // File preview
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                preview.classList.remove('hidden');
                fileInfo.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('hidden');
        }
    });
    
    // Drag and drop
    const dropZone = document.querySelector('.border-dashed');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        dropZone.classList.add('border-blue-400');
    }
    
    function unhighlight(e) {
        dropZone.classList.remove('border-blue-400');
    }
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    }
});
</script>
@endsection
