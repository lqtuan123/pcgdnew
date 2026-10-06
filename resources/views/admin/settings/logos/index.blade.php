@extends('layouts.admin')

@section('title', 'Quản lý Logo & Favicon')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Quản lý Logo & Favicon</h1>
        <a href="{{ route('admin.settings.logos.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus mr-2"></i>Tải lên mới
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <!-- Form tải lên nhanh -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Tải lên nhanh</h2>
        <form action="{{ route('admin.settings.logos.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Loại</label>
                    <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Chọn loại</option>
                        <option value="logo">Logo</option>
                        <option value="favicon">Favicon</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tên</label>
                    <input type="text" name="name" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tên logo">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                    <input type="text" name="description" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Mô tả">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File</label>
                    <input type="file" name="file" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-upload mr-2"></i>Tải lên
            </button>
        </form>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                <button onclick="showTab('logo')" id="tab-logo" class="tab-button border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600">
                    <i class="fas fa-image mr-2"></i>Logo Website
                </button>
                <button onclick="showTab('favicon')" id="tab-favicon" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    <i class="fas fa-star mr-2"></i>Favicon
                </button>
                <button onclick="showTab('email')" id="tab-email" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                    <i class="fas fa-envelope mr-2"></i>Logo Email
                </button>
            </nav>
        </div>

        <div class="p-6">
            <!-- Logo Tab -->
            <div id="content-logo" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($logos->where('type', 'logo') as $logo)
                        @include('admin.settings.logos.partials.logo-card', ['logo' => $logo])
                    @endforeach
                </div>
                @if($logos->where('type', 'logo')->isEmpty())
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-6xl mb-4">
                            <i class="fas fa-image"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có logo nào</h3>
                        <p class="text-gray-600 mb-4">Bắt đầu bằng cách tải lên logo đầu tiên.</p>
                    </div>
                @endif
            </div>

            <!-- Favicon Tab -->
            <div id="content-favicon" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($logos->where('type', 'favicon') as $logo)
                        @include('admin.settings.logos.partials.logo-card', ['logo' => $logo])
                    @endforeach
                </div>
                @if($logos->where('type', 'favicon')->isEmpty())
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-6xl mb-4">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có favicon nào</h3>
                        <p class="text-gray-600 mb-4">Bắt đầu bằng cách tải lên favicon đầu tiên.</p>
                    </div>
                @endif
            </div>

            <!-- Email Tab -->
            <div id="content-email" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($logos->where('type', 'email') as $logo)
                        @include('admin.settings.logos.partials.logo-card', ['logo' => $logo])
                    @endforeach
                </div>
                @if($logos->where('type', 'email')->isEmpty())
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-6xl mb-4">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có logo email nào</h3>
                        <p class="text-gray-600 mb-4">Bắt đầu bằng cách tải lên logo email đầu tiên.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function showTab(type) {
    // Ẩn tất cả content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Bỏ active tất cả tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Hiển thị content được chọn
    document.getElementById('content-' + type).classList.remove('hidden');
    
    // Active tab được chọn
    document.getElementById('tab-' + type).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + type).classList.add('border-blue-500', 'text-blue-600');
}

// Mặc định hiển thị tab logo
document.addEventListener('DOMContentLoaded', function() {
    showTab('logo');
});
</script>
@endsection
