@extends('layouts.front')
@section('title', 'Cấu hình phân tách thập phân')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Cấu hình phân tách thập phân</h1>
            <p class="mt-1 text-sm text-gray-600">Chọn cách hiển thị số thập phân theo ý muốn của bạn</p>
        </div>

        <div class="p-6">
            <form id="decimalFormatForm" class="space-y-6">
                @csrf
                
                <div class="space-y-4">
                    @foreach($formats as $format)
                    <div class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <input type="radio" 
                               id="format_{{ $format->id }}" 
                               name="decimal_format_id" 
                               value="{{ $format->id }}"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                               {{ $userFormat && $userFormat->id == $format->id ? 'checked' : '' }}>
                        
                        <label for="format_{{ $format->id }}" class="ml-3 flex-1 cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">{{ $format->name }}</h3>
                                    <p class="text-sm text-gray-500">{{ $format->description }}</p>
                                </div>
                                <div class="text-sm font-mono text-gray-700">
                                    Ví dụ: 1{{ $format->thousands_separator }}234{{ $format->decimal_separator }}56
                                </div>
                            </div>
                        </label>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                    <div class="text-sm text-gray-600">
                        Cấu hình này sẽ được áp dụng cho tất cả số thập phân trong hệ thống
                    </div>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Lưu cấu hình
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Demo Section -->
    <div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Xem trước</h2>
            <p class="mt-1 text-sm text-gray-600">Xem cách số sẽ được hiển thị với cấu hình đã chọn</p>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Số nguyên</label>
                    <div class="text-lg font-mono text-gray-900" id="demoInteger">1,234,567</div>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Số thập phân</label>
                    <div class="text-lg font-mono text-gray-900" id="demoDecimal">1,234.56</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('decimalFormatForm');
    const radioButtons = document.querySelectorAll('input[name="decimal_format_id"]');
    
    // Cập nhật demo khi chọn format
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            updateDemo();
        });
    });
    
    // Xử lý submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedFormat = document.querySelector('input[name="decimal_format_id"]:checked');
        if (!selectedFormat) {
            alert('Vui lòng chọn một cấu hình');
            return;
        }
        
        const formData = new FormData(form);
        
        fetch('{{ route("front.decimal-format.update") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hiển thị thông báo thành công
                const alert = document.createElement('div');
                alert.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                alert.textContent = data.message;
                document.body.appendChild(alert);
                
                setTimeout(() => {
                    alert.remove();
                }, 3000);
                
                updateDemo();
            } else {
                alert('Có lỗi xảy ra: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi cập nhật cấu hình');
        });
    });
    
    // Cập nhật demo
    function updateDemo() {
        const selectedFormat = document.querySelector('input[name="decimal_format_id"]:checked');
        if (!selectedFormat) return;
        
        const formatId = selectedFormat.value;
        const format = @json($formats);
        const selectedFormatData = format.find(f => f.id == formatId);
        
        if (selectedFormatData) {
            const integerDemo = '1234567'.replace(/\B(?=(\d{3})+(?!\d))/g, selectedFormatData.thousands_separator);
            const decimalDemo = '1234.56'.replace('.', selectedFormatData.decimal_separator)
                                        .replace(/\B(?=(\d{3})+(?!\d))/g, selectedFormatData.thousands_separator);
            
            document.getElementById('demoInteger').textContent = integerDemo;
            document.getElementById('demoDecimal').textContent = decimalDemo;
        }
    }
    
    // Khởi tạo demo
    updateDemo();
});
</script>
@endsection
