@extends('layouts.admin')

@section('title', 'Chỉnh sửa role')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Chỉnh sửa role: {{ $role->display_name }}</h1>
        <a href="{{ route('admin.roles.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left mr-2"></i> Quay lại
        </a>
    </div>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
        @csrf
        @method('PUT')
        
        <!-- Thông tin cơ bản -->
        <article class="card">
            <h2 class="text-lg font-semibold mb-4">Thông tin cơ bản</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tên Role</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 bg-slate-100" 
                           id="name" value="{{ $role->name }}" disabled>
                    <small class="text-slate-500 text-xs mt-1">Tên role không thể thay đổi.</small>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tên Hiển Thị <span class="text-rose-500">*</span></label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 @error('display_name') border-rose-300 @enderror" 
                           id="display_name" name="display_name" value="{{ old('display_name', $role->display_name) }}" 
                           placeholder="vd: Giáo viên, Quản lý" required>
                    @error('display_name')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

        </article>

        <!-- Phân quyền chức năng -->
        <article class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Phân quyền chức năng</h2>
                <span class="text-sm text-slate-500">Tích nhanh để cấp tất cả quyền cho một danh mục</span>
            </div>
            
            <div class="space-y-4">
                @foreach($categories as $category)
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-slate-800">{{ $category }}</h3>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       class="category-checkbox rounded" 
                                       data-category="{{ $category }}"
                                       onchange="toggleCategoryPermissions('{{ $category }}')">
                                <span class="ml-2 text-sm font-medium">Tích tất cả</span>
                            </label>
                        </div>
                        
                        <div class="space-y-3">
                            @foreach($systemFunctions->where('category', $category) as $function)
                                @php
                                    $functionPermissions = $permissions[$function->code] ?? [];
                                @endphp
                                <div class="border rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-slate-800 mb-1">{{ $function->name }}</h4>
                                            <div class="flex gap-4" id="permissions-{{ $function->code }}">
                                                @if(($functionPermissions['view'] ?? false) || ($functionPermissions['create'] ?? false) || ($functionPermissions['edit'] ?? false) || ($functionPermissions['delete'] ?? false))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        ✓ Đã cấp quyền
                                                    </span>
                                                @else
                                                    <span class="text-slate-400 text-xs">Chưa có quyền</span>
                                                @endif
                                            </div>
                                        </div>
                                        <label class="flex items-center cursor-pointer ml-4">
                                            <input type="checkbox" 
                                                   class="function-checkbox rounded" 
                                                   data-function="{{ $function->code }}"
                                                   data-category="{{ $category }}"
                                                   onchange="toggleFunctionPermissions('{{ $function->code }}')"
                                                   name="permissions[{{ $function->code }}]"
                                                   value="1"
                                                   @if(($functionPermissions['view'] ?? false) || ($functionPermissions['create'] ?? false) || ($functionPermissions['edit'] ?? false) || ($functionPermissions['delete'] ?? false)) checked @endif>
                                            <span class="ml-2 text-sm font-medium text-slate-600">Cấp quyền</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
        
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-2"></i> Cập nhật role
            </button>
            <a href="{{ route('admin.roles.index') }}" class="btn-soft">
                <i class="fas fa-times mr-2"></i> Hủy
            </a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
function toggleCategoryPermissions(category) {
    const categoryCheckbox = document.querySelector(`input[data-category="${category}"].category-checkbox`);
    const functionCheckboxes = document.querySelectorAll(`input[data-category="${category}"].function-checkbox`);
    
    if (categoryCheckbox.checked) {
        // Tích tất cả functions trong category
        functionCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
            toggleFunctionPermissions(checkbox.dataset.function);
        });
    } else {
        // Bỏ tích tất cả functions trong category
        functionCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
            toggleFunctionPermissions(checkbox.dataset.function);
        });
    }
}

function toggleFunctionPermissions(functionCode) {
    const functionCheckbox = document.querySelector(`input[data-function="${functionCode}"]`);
    const isChecked = functionCheckbox.checked;
    
    // Tạo hoặc cập nhật hidden inputs cho 4 quyền
    const actions = ['view', 'create', 'edit', 'delete'];
    actions.forEach(action => {
        let input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = `permissions[${functionCode}][${action}]`;
            input.value = '1';
            functionCheckbox.parentNode.appendChild(input);
        }
        input.value = isChecked ? '1' : '0';
    });
    
    // Cập nhật hiển thị các quyền con
    updateFunctionPermissionsDisplay(functionCode, isChecked);
}

function updateFunctionPermissionsDisplay(functionCode, isChecked) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    if (isChecked) {
        // Hiển thị tất cả 4 quyền khi tích nhanh với khả năng click để toggle
        permissionsContainer.innerHTML = `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 cursor-pointer hover:bg-blue-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'view')" title="Click để toggle">Xem</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 cursor-pointer hover:bg-green-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'create')" title="Click để toggle">Tạo</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 cursor-pointer hover:bg-amber-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'edit')" title="Click để toggle">Sửa</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 cursor-pointer hover:bg-red-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'delete')" title="Click để toggle">Xóa</span>
        `;
    } else {
        // Hiển thị "Chưa có quyền"
        permissionsContainer.innerHTML = '<span class="text-slate-400 text-xs">Chưa có quyền</span>';
    }
}

function toggleIndividualPermission(functionCode, action) {
    // Tìm input tương ứng
    const input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
    if (input) {
        // Toggle quyền này (nếu đang có thì bỏ, nếu chưa có thì thêm)
        if (input.value === '1') {
            input.value = '0'; // Bỏ tích
        } else {
            input.value = '1'; // Thêm tích
        }
        
        // Cập nhật hiển thị
        updateFunctionPermissionsDisplayAfterIndividualToggle(functionCode);
        
        // Kiểm tra xem còn quyền nào không
        const actions = ['view', 'create', 'edit', 'delete'];
        const hasAnyPermission = actions.some(action => {
            const actionInput = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
            return actionInput && actionInput.value === '1';
        });
        
        // Cập nhật trạng thái checkbox "Tích nhanh"
        const functionCheckbox = document.querySelector(`input[data-function="${functionCode}"]`);
        functionCheckbox.checked = hasAnyPermission;
        
        // Cập nhật trạng thái category checkbox
        updateCategoryCheckboxState(functionCode);
    }
}

function updateFunctionPermissionsDisplayAfterIndividualToggle(functionCode) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    // Lấy trạng thái hiện tại của các quyền
    const actions = ['view', 'create', 'edit', 'delete'];
    const actionLabels = {
        'view': { text: 'Xem', class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
        'create': { text: 'Tạo', class: 'bg-green-100 text-green-800 hover:bg-green-200' },
        'edit': { text: 'Sửa', class: 'bg-amber-100 text-amber-800 hover:bg-amber-200' },
        'delete': { text: 'Xóa', class: 'bg-red-100 text-red-800 hover:bg-red-200' }
    };
    
    let html = '';
    actions.forEach(action => {
        const input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
        if (input && input.value === '1') {
            const label = actionLabels[action];
            html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                          onclick="toggleIndividualPermission('${functionCode}', '${action}')" title="Click để toggle">${label.text}</span>`;
        }
    });
    
    if (html === '') {
        html = '<span class="text-slate-400 text-xs">Chưa có quyền</span>';
    }
    
    permissionsContainer.innerHTML = html;
}

function updateCategoryCheckboxState(functionCode) {
    // Tìm category của function này
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const categoryRow = functionRow.closest('.border.rounded-lg.p-4');
    const categoryCheckbox = categoryRow.querySelector('.category-checkbox');
    
    if (categoryCheckbox) {
        // Kiểm tra tất cả functions trong category này
        const allFunctionCheckboxes = categoryRow.querySelectorAll('input[data-function]');
        const allChecked = Array.from(allFunctionCheckboxes).every(checkbox => checkbox.checked);
        categoryCheckbox.checked = allChecked;
    }
}

function updateFunctionPermissionsDisplayWithCurrentPermissions(functionCode, functionPermissions) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    // Lấy trạng thái hiện tại của các quyền
    const actions = ['view', 'create', 'edit', 'delete'];
    const actionLabels = {
        'view': { text: 'Xem', class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
        'create': { text: 'Tạo', class: 'bg-green-100 text-green-800 hover:bg-green-200' },
        'edit': { text: 'Sửa', class: 'bg-amber-100 text-amber-800 hover:bg-amber-200' },
        'delete': { text: 'Xóa', class: 'bg-red-100 text-red-800 hover:bg-red-200' }
    };
    
    let html = '';
    let hasAnyPermission = false;
    
    actions.forEach(action => {
        if (functionPermissions[action]) {
            hasAnyPermission = true;
            const label = actionLabels[action];
            html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                          onclick="toggleIndividualPermission('${functionCode}', '${action}')" title="Click để toggle">${label.text}</span>`;
        }
    });
    
    if (html === '') {
        html = '<span class="text-slate-400 text-xs">Chưa có quyền</span>';
    }
    
    permissionsContainer.innerHTML = html;
    
    // Cập nhật trạng thái checkbox chính
    const functionCheckbox = document.querySelector(`input[data-function="${functionCode}"]`);
    functionCheckbox.checked = hasAnyPermission;
}

// Cập nhật trạng thái category checkbox khi thay đổi function permissions
document.addEventListener('DOMContentLoaded', function() {
    const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    
    categoryCheckboxes.forEach(categoryCheckbox => {
        const category = categoryCheckbox.dataset.category;
        const functionCheckboxes = document.querySelectorAll(`input[data-category="${category}"].function-checkbox`);
        
        // Cập nhật trạng thái category checkbox dựa trên function permissions
        function updateCategoryCheckbox() {
            const checkedCount = Array.from(functionCheckboxes).filter(cb => cb.checked).length;
            const totalCount = functionCheckboxes.length;
            
            if (checkedCount === 0) {
                categoryCheckbox.checked = false;
                categoryCheckbox.indeterminate = false;
            } else if (checkedCount === totalCount) {
                categoryCheckbox.checked = true;
                categoryCheckbox.indeterminate = false;
            } else {
                categoryCheckbox.checked = false;
                categoryCheckbox.indeterminate = true;
            }
        }
        
        // Lắng nghe thay đổi trên function permissions
        functionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCategoryCheckbox);
        });
        
        // Cập nhật trạng thái ban đầu
        updateCategoryCheckbox();
    });
    
    // Khởi tạo hidden inputs và hiển thị cho tất cả functions
    const functionCheckboxes = document.querySelectorAll('.function-checkbox');
    functionCheckboxes.forEach(checkbox => {
        const functionCode = checkbox.dataset.function;
        const isChecked = checkbox.checked;
        
        // Lấy permissions hiện có từ PHP
        const currentPermissions = @json($permissions);
        const functionPermissions = currentPermissions[functionCode] || {};
        
        const actions = ['view', 'create', 'edit', 'delete'];
        actions.forEach(action => {
            let input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = `permissions[${functionCode}][${action}]`;
                input.value = '0';
                checkbox.parentNode.appendChild(input);
            }
            
            // Set giá trị dựa trên permissions hiện có
            input.value = functionPermissions[action] ? '1' : '0';
        });
        
        // Hiển thị trạng thái ban đầu với permissions chi tiết
        updateFunctionPermissionsDisplayWithCurrentPermissions(functionCode, functionPermissions);
    });
    
    // Xử lý form submission
    document.querySelector('form').addEventListener('submit', function(e) {
        console.log('Form submitting...');
        const permissions = {};
        
        // Thu thập permissions từ hidden inputs
        this.querySelectorAll('input[name^="permissions["]').forEach(input => {
            const name = input.name;
            const matches = name.match(/permissions\[([^\]]+)\]\[([^\]]+)\]/);
            if (matches) {
                const functionCode = matches[1];
                const action = matches[2];
                if (!permissions[functionCode]) {
                    permissions[functionCode] = {};
                }
                permissions[functionCode][action] = input.value === '1';
            }
        });
        
        console.log('Collected permissions:', permissions);
        
        // Xóa permissions input cũ nếu có
        const existingPermissionsInput = this.querySelector('input[name="permissions"]');
        if (existingPermissionsInput) {
            existingPermissionsInput.remove();
        }
        
        // Thêm permissions vào form data
        const permissionsInput = document.createElement('input');
        permissionsInput.type = 'hidden';
        permissionsInput.name = 'permissions';
        permissionsInput.value = JSON.stringify(permissions);
        this.appendChild(permissionsInput);
        
        console.log('Final form data permissions:', permissionsInput.value);
    });
});
</script>
@endpush
