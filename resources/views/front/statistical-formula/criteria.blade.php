@extends('layouts.front')

@section('title', 'Quản lý tiêu chí Thống kê')

@section('content')

<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-list-check mr-2 text-blue-600"></i>Quản lý tiêu chí Thống kê
                </h2>
                <div class="flex items-center gap-3">
                    <a href="{{ route('front.statistical-formula.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                        <i class="fas fa-calculator mr-2"></i>Công thức thống kê
                    </a>
                    <a href="{{ route('front.statistical-formula.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Thoát
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mr-2 mt-0.5"></i>
                    <div class="text-sm text-blue-800">
                        <strong>Hướng dẫn:</strong> Quản lý trạng thái kích hoạt/tắt của các tiêu chí thống kê. 
                        Chỉ những tiêu chí được kích hoạt mới hiển thị trong báo cáo.
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-1/4">Báo cáo</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-1/5">Tiêu chí</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-1/6">Công thức</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-2/5">Mô tả</th>
                            <th class="border border-gray-300 px-4 py-3 text-center text-sm font-medium text-gray-700 w-20">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($reportGroups as $group)
                            @foreach($group->reportDetails as $report)
                                @php 
                                    $formulas = $report->statisticalFormulas; 
                                @endphp
                                @if($formulas->count() > 0)
                                    @foreach($formulas as $index => $formula)
                                    <tr class="hover:bg-gray-50">
                                        @if($index === 0)
                                        <td rowspan="{{ $formulas->count() }}" class="border border-gray-300 px-4 py-3 align-middle">
                                            <div class="font-semibold text-gray-900">{{ $group->sort_order }}.{{ $report->sort_order }} {{ $report->name }}</div>
                                        </td>
                                        @endif
                                        <td class="border border-gray-300 px-4 py-3 text-sm text-gray-700">{{ $formula->criteria }}</td>
                                        <td class="border border-gray-300 px-4 py-3 text-sm text-gray-700">{{ $formula->formula }}</td>
                                        <td class="border border-gray-300 px-4 py-3 text-sm text-gray-600">{{ $formula->description }}</td>
                                        @if($index === 0)
                                        <td rowspan="{{ $formulas->count() }}" class="border border-gray-300 px-4 py-3 text-center align-middle">
                                            <div class="space-y-2">
                                                @foreach($formulas as $formulaItem)
                                                <div class="flex items-center justify-center">
                                                    <button type="button" 
                                                            class="toggle-criteria-btn inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors duration-200 {{ $formulaItem->is_active ? 'bg-green-100 text-green-800 border border-green-300 hover:bg-green-200' : 'bg-gray-100 text-gray-800 border border-gray-300 hover:bg-gray-200' }}"
                                                            data-formula-id="{{ $formulaItem->id }}"
                                                            data-is-active="{{ $formulaItem->is_active }}"
                                                            onclick="toggleCriteria({{ $formulaItem->id }})"
                                                            title="{{ $formulaItem->is_active ? 'Tắt tiêu chí' : 'Kích hoạt tiêu chí' }}">
                                                        <i class="fas {{ $formulaItem->is_active ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                                        {{ $formulaItem->is_active ? 'Đang kích hoạt' : 'Đã tắt' }}
                                                    </button>
                                                </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach
                                @else
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $group->sort_order }}.{{ $report->sort_order }} {{ $report->name }}</div>
                                    </td>
                                    <td colspan="4" class="border border-gray-300 px-4 py-3 text-center text-sm text-gray-500 italic">
                                        Chưa có tiêu chí
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCriteria(formulaId) {
    const btn = document.querySelector(`[data-formula-id="${formulaId}"]`);
    const originalText = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Đang xử lý...';
    btn.disabled = true;
    
    fetch(`/cong-thuc-thong-ke/toggle-criteria/${formulaId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button appearance
            const isActive = data.is_active;
            btn.className = `toggle-criteria-btn inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors duration-200 ${isActive ? 'bg-green-100 text-green-800 border border-green-300 hover:bg-green-200' : 'bg-gray-100 text-gray-800 border border-gray-300 hover:bg-gray-200'}`;
            btn.innerHTML = `<i class="fas ${isActive ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>${isActive ? 'Đang kích hoạt' : 'Đã tắt'}`;
            btn.title = isActive ? 'Tắt tiêu chí' : 'Kích hoạt tiêu chí';
            btn.setAttribute('data-is-active', isActive);
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi cập nhật trạng thái tiêu chí', 'error');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            ${message}
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}
</script>
@endsection
