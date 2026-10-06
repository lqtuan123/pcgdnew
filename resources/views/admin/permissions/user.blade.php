@extends('layouts.admin')

@section('title', 'Phân quyền chức năng')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Phân quyền chức năng</h1>
            <p class="mt-2 text-slate-600">Phân quyền chức năng cho: <strong>{{ $user->name }}</strong></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-soft">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Thông báo -->
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Form phân quyền -->

    <form method="POST" action="{{ route('admin.permissions.user.assign', $user->id) }}" class="space-y-6">
        @csrf
        
        
        <div class="grid md:grid-cols-2 gap-6">
            @foreach($categories as $category)
                <article class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">{{ ucfirst($category) }}</h3>
                        <button type="button" 
                                class="btn-soft text-xs"
                                onclick="toggleCategoryAll('{{ $category }}')">
                            <i class="fas fa-check-double mr-1"></i> Chọn tất cả
                        </button>
                    </div>
                    <div class="space-y-4">
                        @foreach($functions->where('category', $category) as $function)
                            <div class="border rounded-lg p-4 hover:bg-slate-50 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-medium text-slate-800">{{ $function->name }}</h4>
                                    <button type="button" 
                                            class="text-xs text-blue-600 hover:text-blue-800"
                                            onclick="toggleFunctionAll('{{ $function->code }}')">
                                        <i class="fas fa-check mr-1"></i> Tất cả
                                    </button>
                                </div>
                                @if($function->description)
                                    <p class="text-sm text-slate-500 mb-3">{{ $function->description }}</p>
                                @endif
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <label class="flex items-center p-2 rounded hover:bg-slate-100 transition-colors">
                                        <input 
                                            type="checkbox" 
                                            class="rounded text-blue-600 focus:ring-blue-500" 
                                            name="permissions[{{ $function->code }}][view]"
                                            value="1"
                                            data-category="{{ $category }}"
                                            data-function="{{ $function->code }}"
                                            {{ isset($permissions[$function->code]['view']) && $permissions[$function->code]['view'] ? 'checked' : '' }}
                                        >
                                        <span class="ml-2 text-sm font-medium">Xem</span>
                                    </label>
                                    <label class="flex items-center p-2 rounded hover:bg-slate-100 transition-colors">
                                        <input 
                                            type="checkbox" 
                                            class="rounded text-green-600 focus:ring-green-500" 
                                            name="permissions[{{ $function->code }}][create]"
                                            value="1"
                                            data-category="{{ $category }}"
                                            data-function="{{ $function->code }}"
                                            {{ isset($permissions[$function->code]['create']) && $permissions[$function->code]['create'] ? 'checked' : '' }}
                                        >
                                        <span class="ml-2 text-sm font-medium">Tạo</span>
                                    </label>
                                    <label class="flex items-center p-2 rounded hover:bg-slate-100 transition-colors">
                                        <input 
                                            type="checkbox" 
                                            class="rounded text-amber-600 focus:ring-amber-500" 
                                            name="permissions[{{ $function->code }}][edit]"
                                            value="1"
                                            data-category="{{ $category }}"
                                            data-function="{{ $function->code }}"
                                            {{ isset($permissions[$function->code]['edit']) && $permissions[$function->code]['edit'] ? 'checked' : '' }}
                                        >
                                        <span class="ml-2 text-sm font-medium">Sửa</span>
                                    </label>
                                    <label class="flex items-center p-2 rounded hover:bg-slate-100 transition-colors">
                                        <input 
                                            type="checkbox" 
                                            class="rounded text-red-600 focus:ring-red-500" 
                                            name="permissions[{{ $function->code }}][delete]"
                                            value="1"
                                            data-category="{{ $category }}"
                                            data-function="{{ $function->code }}"
                                            {{ isset($permissions[$function->code]['delete']) && $permissions[$function->code]['delete'] ? 'checked' : '' }}
                                        >
                                        <span class="ml-2 text-sm font-medium">Xóa</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.users.show', $user->id) }}" class="btn-soft">Hủy</a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-2"></i> Lưu phân quyền
            </button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
// Chọn tất cả trong một category
function toggleCategoryAll(category) {
    const checkboxes = document.querySelectorAll(`input[data-category="${category}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    // Cập nhật text nút
    const button = event.target.closest('button');
    if (button) {
        button.innerHTML = allChecked 
            ? '<i class="fas fa-check-double mr-1"></i> Chọn tất cả'
            : '<i class="fas fa-times mr-1"></i> Bỏ chọn tất cả';
    }
}

// Chọn tất cả trong một function
function toggleFunctionAll(functionCode) {
    const checkboxes = document.querySelectorAll(`input[data-function="${functionCode}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    // Cập nhật text nút
    const button = event.target.closest('button');
    if (button) {
        button.innerHTML = allChecked 
            ? '<i class="fas fa-check mr-1"></i> Tất cả'
            : '<i class="fas fa-times mr-1"></i> Bỏ tất cả';
    }
}

// Cập nhật trạng thái nút khi thay đổi checkbox
document.addEventListener('DOMContentLoaded', function() {
    // Cập nhật trạng thái nút category khi thay đổi checkbox
    document.querySelectorAll('input[data-category]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const category = this.dataset.category;
            const checkboxes = document.querySelectorAll(`input[data-category="${category}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const noneChecked = Array.from(checkboxes).every(cb => !cb.checked);
            
            const button = document.querySelector(`button[onclick="toggleCategoryAll('${category}')"]`);
            if (button) {
                if (allChecked) {
                    button.innerHTML = '<i class="fas fa-times mr-1"></i> Bỏ chọn tất cả';
                } else if (noneChecked) {
                    button.innerHTML = '<i class="fas fa-check-double mr-1"></i> Chọn tất cả';
                } else {
                    button.innerHTML = '<i class="fas fa-check-double mr-1"></i> Chọn tất cả';
                }
            }
        });
    });
    
    // Cập nhật trạng thái nút function khi thay đổi checkbox
    document.querySelectorAll('input[data-function]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const functionCode = this.dataset.function;
            const checkboxes = document.querySelectorAll(`input[data-function="${functionCode}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const noneChecked = Array.from(checkboxes).every(cb => !cb.checked);
            
            const button = document.querySelector(`button[onclick="toggleFunctionAll('${functionCode}')"]`);
            if (button) {
                if (allChecked) {
                    button.innerHTML = '<i class="fas fa-times mr-1"></i> Bỏ tất cả';
                } else if (noneChecked) {
                    button.innerHTML = '<i class="fas fa-check mr-1"></i> Tất cả';
                } else {
                    button.innerHTML = '<i class="fas fa-check mr-1"></i> Tất cả';
                }
            }
        });
    });
});
</script>
@endpush

