@extends('layouts.front')

@section('title', 'Cấu hình báo cáo')

@section('content')

<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title mb-0 text-xl font-semibold">
                <i class="fas fa-cog me-2"></i>Cấu hình báo cáo
            </h2>
               <div class="flex items-center gap-2 mb-2 mt-2">
                   <a href="{{ route('front.home') }}" class="rounded-lg bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">
                       <i class="fas fa-home mr-1"></i>Thoát
                   </a>
               </div>
        </div>
        
        <div class="card-body">
            @if(!$canEdit)
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Thông báo:</strong> Bạn không có quyền sửa cấu hình báo cáo. Bạn chỉ có thể xem cấu hình hiện tại.
                </div>
            @endif
            
            <form id="reportConfigForm">
                @csrf
                @foreach($reportGroups as $group)
                        <div class="mb-4">
                            <h5 class="bg-light p-2 mb-3 border rounded">
                                {{ $group->name }}
                            </h5>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered border-gray-300">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th width="70%" class="border border-gray-300 px-4 py-2 font-normal">Mẫu báo cáo</th>
                                            <th width="10%" class="text-center border border-gray-300 px-4 py-2 font-normal">Bắt buộc</th>
                                            <th width="10%" class="text-center border border-gray-300 px-4 py-2 font-normal">Không bắt buộc</th>
                                            <th width="10%" class="text-center border border-gray-300 px-4 py-2 font-normal">Không sử dụng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group->reportDetails as $index => $report)
                                        <tr>
                                            <td class="border border-gray-300 px-4 py-2">
                                                {{ $group->sort_order }}.{{ $index + 1 }} {{ $report->name }}
                                            </td>
                                            <td class="text-center border border-gray-300 px-4 py-2">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="config[{{ $report->id }}][required]" 
                                                           value="1" 
                                                           id="required_{{ $report->id }}"
                                                           {{ ($report->reportConfig && $report->reportConfig->status == 'required') ? 'checked' : '' }}
                                                           @if(!$canEdit) disabled @endif
                                                           onchange="updateReportStatus({{ $report->id }}, 'required', this.checked)">
                                                </div>
                                            </td>
                                            <td class="text-center border border-gray-300 px-4 py-2">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="config[{{ $report->id }}][optional]" 
                                                           value="1" 
                                                           id="optional_{{ $report->id }}"
                                                           {{ (!$report->reportConfig || $report->reportConfig->status == 'optional') ? 'checked' : '' }}
                                                           @if(!$canEdit) disabled @endif
                                                           onchange="updateReportStatus({{ $report->id }}, 'optional', this.checked)">
                                                </div>
                                            </td>
                                            <td class="text-center border border-gray-300 px-4 py-2">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="config[{{ $report->id }}][disabled]" 
                                                           value="1" 
                                                           id="disabled_{{ $report->id }}"
                                                           {{ ($report->reportConfig && $report->reportConfig->status == 'disabled') ? 'checked' : '' }}
                                                           @if(!$canEdit) disabled @endif
                                                           onchange="updateReportStatus({{ $report->id }}, 'disabled', this.checked)">
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endforeach
                    </form>
        </div>
    </div>
</div>

<script>
function updateReportStatus(reportDetailId, status, isChecked) {
    // Kiểm tra quyền chỉnh sửa
    @if(!$canEdit)
        showNotification('Bạn không có quyền sửa cấu hình báo cáo.', 'error');
        return;
    @endif
    
    // Uncheck other checkboxes for the same report
    const otherCheckboxes = document.querySelectorAll(`input[name*="[${reportDetailId}]"]`);
    otherCheckboxes.forEach(checkbox => {
        if (checkbox.id !== `${status}_${reportDetailId}`) {
            checkbox.checked = false;
        }
    });
    
    // If unchecking, set to optional by default
    if (!isChecked) {
        const optionalCheckbox = document.getElementById(`optional_${reportDetailId}`);
        if (optionalCheckbox) {
            optionalCheckbox.checked = true;
        }
        status = 'optional';
    }
    
    // Save to server
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    formData.append('report_detail_id', reportDetailId);
    formData.append('status', status);
    
    fetch('{{ route("front.report-config.update") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cập nhật thành công!', 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra', 'error');
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
