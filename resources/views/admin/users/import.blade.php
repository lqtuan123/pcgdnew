@extends('layouts.admin')

@section('title', 'Import Người Dùng')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Import Người Dùng Từ Excel</h1>
            <p class="mt-2 text-slate-600">Import hàng loạt người dùng từ file Excel với validation tự động</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-soft">
                <i class="fas fa-arrow-left mr-2"></i>
                Quay lại
            </a>
        </div>
    </div>
    <!-- Kết quả import -->
    @if(session('import_stats'))
        @php $stats = session('import_stats'); @endphp
        <article class="card">
            <div class="flex items-center gap-2 mb-4">
                <i class="fas fa-info-circle text-blue-600"></i>
                <h2 class="text-lg font-semibold">Kết quả import</h2>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="text-center p-3 bg-slate-50 rounded-lg">
                    <div class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</div>
                    <div class="text-sm text-slate-600">Tổng số dòng</div>
                </div>
                <div class="text-center p-3 bg-emerald-50 rounded-lg">
                    <div class="text-2xl font-bold text-emerald-600">{{ $stats['created'] }}</div>
                    <div class="text-sm text-emerald-600">Thành công</div>
                </div>
                <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['updated'] }}</div>
                    <div class="text-sm text-blue-600">Cập nhật</div>
                </div>
                <div class="text-center p-3 bg-rose-50 rounded-lg">
                    <div class="text-2xl font-bold text-rose-600">{{ count($stats['errors']) }}</div>
                    <div class="text-sm text-rose-600">Lỗi</div>
                </div>
            </div>
            
            @if(count($stats['errors']) > 0)
                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-slate-700 mb-2">Chi tiết lỗi:</h3>
                    <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 max-h-60 overflow-y-auto">
                        @foreach($stats['errors'] as $error)
                            <div class="text-sm text-rose-700 py-1">{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    @endif

    <!-- Form upload và hướng dẫn -->
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Form upload -->
        <div class="lg:col-span-2">
            <article class="card">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-upload mr-2 text-blue-600"></i>
                    Upload File Excel
                </h2>
                
                <form action="{{ route('admin.users.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    
                    <div>
                        <label for="file" class="block text-sm font-medium text-slate-700 mb-2">
                            Chọn file Excel <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="file" 
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('file') border-rose-300 @enderror" 
                                   id="file" 
                                   name="file" 
                                   accept=".xlsx,.xls,.csv"
                                   required>
                        </div>
                        @error('file')
                            <div class="text-rose-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                        <p class="text-sm text-slate-500 mt-1">
                            Hỗ trợ định dạng: .xlsx, .xls, .csv
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-upload mr-2"></i>
                            Import Người Dùng
                        </button>
                        <a href="{{ route('admin.users.import.template') }}" class="btn-soft">
                            <i class="fas fa-download mr-2"></i>
                            Tải Template Mẫu
                        </a>
                    </div>
                </form>
            </article>
        </div>

        <!-- Hướng dẫn -->
        <div class="lg:col-span-1">
            <article class="card">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Hướng dẫn Import
                </h3>
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-700 mb-2">Các cột bắt buộc:</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li><strong>Họ tên:</strong> Tên đầy đủ</li>
                            <li><strong>Email:</strong> Email hợp lệ</li>
                            <li><strong>Vai trò:</strong> Chọn từ dropdown (admin, user)</li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-slate-700 mb-2">Các cột tùy chọn:</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li><strong>Số điện thoại:</strong> SĐT liên hệ</li>
                            <li><strong>Cấp độ vai trò:</strong> province, ward, school</li>
                            <li><strong>Tỉnh/Thành phố:</strong> Chọn từ dropdown (cho cấp province)</li>
                            <li><strong>Phường/Xã:</strong> Chọn từ dropdown (cho cấp ward)</li>
                            <li><strong>Trường học:</strong> Chọn từ dropdown (cho cấp school)</li>
                            <li><strong>Mật khẩu:</strong> Nếu để trống hệ thống sẽ dùng mặc định <code>password123</code></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-semibold text-slate-700 mb-2">Tính năng đặc biệt:</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li><strong>Dropdown validation:</strong> Các cột có sẵn danh sách chọn</li>
                            <li><strong>Dữ liệu thực:</strong> Lấy từ database hiện tại</li>
                            <li><strong>Sheets tham chiếu:</strong> Xem danh sách đầy đủ các giá trị</li>
                            <li><strong>Tự động cập nhật:</strong> Template luôn có dữ liệu mới nhất</li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                                <div class="text-sm">
                                    <strong class="text-amber-800">Lưu ý:</strong>
                                    <span class="text-amber-700">Email phải là duy nhất trong hệ thống.</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                                <div class="text-sm">
                                    <strong class="text-blue-800">Template Excel:</strong>
                                    <span class="text-blue-700">File Excel mẫu chứa dữ liệu thực và hướng dẫn chi tiết.</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-list text-blue-600 mt-0.5"></i>
                                <div class="text-sm">
                                    <strong class="text-blue-800">Danh sách giá trị:</strong>
                                    <span class="text-blue-700">Xem hướng dẫn trong file Excel để biết các giá trị có thể chọn.</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-key text-indigo-600 mt-0.5"></i>
                                <div class="text-sm">
                                    <strong class="text-indigo-800">Mật khẩu:</strong>
                                    <span class="text-indigo-700">Nếu để trống hệ thống sẽ dùng mặc định <code>password123</code>.</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                                <div class="text-sm">
                                    <strong class="text-amber-800">Lưu ý:</strong>
                                    <span class="text-amber-700">Vui lòng nhập chính xác các giá trị theo đúng định dạng trong file mẫu.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </div>

    <!-- Bảng mẫu dữ liệu -->
    <article class="card">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-table text-blue-600"></i>
            <h2 class="text-lg font-semibold">Mẫu dữ liệu Excel</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full with-col-dividers">
                <thead class="thead-group">
                    <tr>
                        <th class="th">Họ tên</th>
                        <th class="th">Email</th>
                        <th class="th">Số điện thoại</th>
                        <th class="th">Vai trò</th>
                        <th class="th">Cấp độ vai trò</th>
                        <th class="th">Tỉnh/Thành phố</th>
                        <th class="th">Phường/Xã</th>
                        <th class="th">Trường học</th>
                        <th class="th">Mật khẩu</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="td">Nguyễn Văn A</td>
                        <td class="td">nguyenvana@example.com</td>
                        <td class="td">0123456789</td>
                        <td class="td">
                            <span class="badge badge-soft">user</span>
                        </td>
                        <td class="td">
                            <span class="badge badge-primary">province</span>
                        </td>
                        <td class="td">Đắk Lắk</td>
                        <td class="td text-slate-400">-</td>
                        <td class="td text-slate-400">-</td>
                        <td class="td font-mono text-sm">password123</td>
                    </tr>
                    <tr>
                        <td class="td">Trần Thị B</td>
                        <td class="td">tranthib@example.com</td>
                        <td class="td">0987654321</td>
                        <td class="td">
                            <span class="badge badge-soft">user</span>
                        </td>
                        <td class="td">
                            <span class="badge badge-primary">ward</span>
                        </td>
                        <td class="td text-slate-400">-</td>
                        <td class="td">Phường 1</td>
                        <td class="td text-slate-400">-</td>
                        <td class="td font-mono text-sm">password123</td>
                    </tr>
                    <tr>
                        <td class="td">Lê Văn C</td>
                        <td class="td">levanc@example.com</td>
                        <td class="td">0369258147</td>
                        <td class="td">
                            <span class="badge badge-soft">user</span>
                        </td>
                        <td class="td">
                            <span class="badge badge-primary">school</span>
                        </td>
                        <td class="td text-slate-400">-</td>
                        <td class="td text-slate-400">-</td>
                        <td class="td">Trường THCS ABC</td>
                        <td class="td font-mono text-sm">password123</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File input change handler
    const fileInput = document.getElementById('file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Chọn file...';
            // Update any label if needed
            const label = this.nextElementSibling;
            if (label && label.classList.contains('custom-file-label')) {
                label.textContent = fileName;
            }
        });
    }

    // Form submit handler
    const form = document.querySelector('form[enctype="multipart/form-data"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('file');
            if (!fileInput || !fileInput.files.length) {
                e.preventDefault();
                alert('Vui lòng chọn file Excel để import.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang import...';
                submitBtn.disabled = true;
                
                // Re-enable button after 30 seconds (fallback)
                setTimeout(function() {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 30000);
            }
        });
    }
});
</script>
@endpush
