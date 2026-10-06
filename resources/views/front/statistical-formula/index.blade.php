@extends('layouts.front')

@section('title', 'Cấu hình công thức Thống kê')

@section('content')

<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-calculator mr-2 text-blue-600"></i>Cấu hình công thức Thống kê
                </h2>
                <div class="flex items-center gap-3">
                    @if($canEdit)
                        <a href="{{ route('front.statistical-formula.criteria') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-edit mr-2"></i>Chỉnh sửa tiêu chí
                        </a>
                    @endif
                    <a href="{{ route('front.home') }}" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200">
                        <i class="fas fa-home mr-2"></i>Thoát
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mr-2 mt-0.5"></i>
                    <div class="text-sm text-blue-800">
                        <strong>Hướng dẫn:</strong> Tùy chỉnh công thức thống kê riêng cho từng mẫu theo nhu cầu của đơn vị. 
                        Tính năng này chỉ dành cho Sở, các cấp khác chỉ xem.
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-1/4">Thống kê</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-1/5">Tiêu chí</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-1/6">Công thức</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-medium text-gray-700 w-2/5">Mô tả</th>
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
                                    </tr>
                                    @endforeach
                                @else
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $group->sort_order }}.{{ $report->sort_order }} {{ $report->name }}</div>
                                    </td>
                                    <td colspan="3" class="border border-gray-300 px-4 py-3 text-center text-sm text-gray-500 italic">
                                        Chưa có công thức
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
let currentReportDetailId = null;

function editFormula(reportDetailId) {
    currentReportDetailId = reportDetailId;
    
    // Load existing formulas
    fetch(`/statistical-formula/edit/${reportDetailId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('formulasContainer').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('editFormulaModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Có lỗi xảy ra khi tải dữ liệu', 'error');
        });
}

function addFormulaRow() {
    const container = document.getElementById('formulasContainer');
    const rowIndex = container.children.length;
    
    const rowHtml = `
        <div class="formula-row border rounded p-3 mb-3" data-index="${rowIndex}">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Tiêu chí</label>
                    <input type="text" class="form-control" name="formulas[${rowIndex}][criteria]" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Công thức</label>
                    <input type="text" class="form-control" name="formulas[${rowIndex}][formula]" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="formulas[${rowIndex}][description]" rows="2"></textarea>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeFormulaRow(${rowIndex})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="formulas[${rowIndex}][is_active]" value="1" checked>
                        <label class="form-check-label">Sử dụng công thức này</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Thứ tự</label>
                    <input type="number" class="form-control" name="formulas[${rowIndex}][sort_order]" value="${rowIndex}" min="0">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', rowHtml);
}

function removeFormulaRow(index) {
    const row = document.querySelector(`[data-index="${index}"]`);
    if (row) {
        row.remove();
    }
}

function saveFormulas() {
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    
    const formulas = [];
    const rows = document.querySelectorAll('.formula-row');
    
    rows.forEach((row, index) => {
        const criteria = row.querySelector(`input[name*="[criteria]"]`).value;
        const formula = row.querySelector(`input[name*="[formula]"]`).value;
        const description = row.querySelector(`textarea[name*="[description]"]`).value;
        const isActive = row.querySelector(`input[name*="[is_active]"]`).checked;
        const sortOrder = row.querySelector(`input[name*="[sort_order]"]`).value;
        
        if (criteria && formula) {
            formulas.push({
                criteria: criteria,
                formula: formula,
                description: description,
                is_active: isActive,
                sort_order: parseInt(sortOrder) || 0
            });
        }
    });
    
    formData.append('formulas', JSON.stringify(formulas));
    
    // Show loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';
    btn.disabled = true;
    
    fetch(`/statistical-formula/update/${currentReportDetailId}`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('editFormulaModal')).hide();
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi lưu công thức', 'error');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
@endsection
