@extends('layouts.front')
@section('title', 'Thêm báo cáo tài chính GDMN')
@section('crumb', 'Thêm báo cáo tài chính GDMN')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Thêm báo cáo tài chính GDMN</h1>
        <p class="mt-1 text-sm text-gray-600">
            Nhập dữ liệu báo cáo tài chính chi cho giáo dục mầm non
        </p>
    </div>

    <!-- Form -->
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <!-- Existing Data Alert -->
        <div id="existing-data-alert" class="hidden mb-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Đang chỉnh sửa dữ liệu hiện có!</strong> 
                        <span id="existing-data-info"></span>
                    </p>
                    <p class="text-xs text-blue-600 mt-1">
                        Nhấn "Lưu dữ liệu" để cập nhật hoặc thay đổi các trường khác để tạo mới.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('gdmn-finance.store') }}" id="create-form">
            @csrf
            <input type="hidden" name="existing_id" id="existing_id" value="">
            
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Năm -->
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700">Năm <span class="text-red-500">*</span></label>
                    <select name="year" id="year" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('year') border-red-300 @enderror">
                        @for($i = date('Y'); $i >= 2020; $i--)
                            <option value="{{ $i }}" {{ old('year', date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                    </select>
                    @error('year')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tỉnh/Thành phố -->
                <div>
                    <label for="province_id" class="block text-sm font-medium text-gray-700">Tỉnh/Thành phố <span class="text-red-500">*</span></label>
                    @php
                        $user = Auth::user();
                        $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
                        $userProvinceId = $user?->province_id;
                        $userProvinceName = $user?->province?->name ?? null;
                        $lockProvince = $isNonAdmin && !empty($userProvinceId);
                    @endphp
                    
                    @if($lockProvince)
                        <!-- Hiển thị tỉnh đã khóa -->
                        <div class="mt-1 px-3 py-2 rounded-md border bg-gray-50 text-sm text-gray-700">
                            {{ $userProvinceName ?? 'ID #' . $userProvinceId }}
                        </div>
                        <input type="hidden" name="province_id" value="{{ $userProvinceId }}">
                    @else
                        <!-- Dropdown chọn tỉnh -->
                        <select name="province_id" id="province_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('province_id') border-red-300 @enderror">
                            <option value="">Chọn tỉnh/thành phố</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>
                                    {{ $province->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    
                    @error('province_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Xã/Phường -->
                <div>
                    <label for="ward_id" class="block text-sm font-medium text-gray-700">Xã/Phường <span class="text-red-500">*</span></label>
                    @php
                        $user = Auth::user();
                        $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
                        $userWardId = $user?->ward_id;
                        $userWardName = $user?->ward?->name ?? null;
                        $lockWard = $isNonAdmin && !empty($userWardId);
                    @endphp
                    
                    @if($lockWard)
                        <!-- Hiển thị xã đã khóa -->
                        <div class="mt-1 px-3 py-2 rounded-md border bg-gray-50 text-sm text-gray-700">
                            {{ $userWardName ?? 'ID #' . $userWardId }}
                        </div>
                        <input type="hidden" name="ward_id" value="{{ $userWardId }}">
                    @else
                        <!-- Dropdown chọn xã -->
                        <select name="ward_id" id="ward_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('ward_id') border-red-300 @enderror">
                            <option value="">Chọn xã/phường</option>
                            @foreach($wards as $ward)
                                <option value="{{ $ward->id }}" {{ old('ward_id') == $ward->id ? 'selected' : '' }}>
                                    {{ $ward->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    
                    @error('ward_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Mục chi chính -->
                <div>
                    <label for="main_item" class="block text-sm font-medium text-gray-700">Mục chi chính <span class="text-red-500">*</span></label>
                    <select name="main_item" id="main_item" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('main_item') border-red-300 @enderror">
                        <option value="">Chọn mục chi</option>
                        @foreach(\App\Models\GdmnFinance::MAIN_ITEMS as $key => $value)
                            <option value="{{ $key }}" {{ old('main_item') == $key ? 'selected' : '' }}>
                                {{ $key }}. {{ $value }}
                            </option>
                        @endforeach
                    </select>
                    @error('main_item')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tiểu mục -->
                <div>
                    <label for="sub_item" class="block text-sm font-medium text-gray-700">Tiểu mục</label>
                    <select name="sub_item" id="sub_item" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sub_item') border-red-300 @enderror">
                        <option value="">Chọn tiểu mục (nếu có)</option>
                    </select>
                    @error('sub_item')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Số tiền -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700">Số tiền <span class="text-red-500">*</span></label>
                    <input type="text" name="amount" id="amount" required 
                           value="{{ old('amount') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-300 @enderror"
                           placeholder="Nhập số tiền">
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Đơn vị tính -->
                <div>
                    <label for="unit" class="block text-sm font-medium text-gray-700">Đơn vị tính <span class="text-red-500">*</span></label>
                    <select name="unit" id="unit" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('unit') border-red-300 @enderror">
                        <option value="Tr.đồng" {{ old('unit', 'Tr.đồng') == 'Tr.đồng' ? 'selected' : '' }}>Tr.đồng</option>
                        <option value="%" {{ old('unit') == '%' ? 'selected' : '' }}>%</option>
                        <option value="Tỷ đồng" {{ old('unit') == 'Tỷ đồng' ? 'selected' : '' }}>Tỷ đồng</option>
                        <option value="Đồng" {{ old('unit') == 'Đồng' ? 'selected' : '' }}>Đồng</option>
                    </select>
                    @error('unit')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Ghi chú -->
            <div class="mt-6">
                <label for="note" class="block text-sm font-medium text-gray-700">Ghi chú</label>
                <textarea name="note" id="note" rows="3" 
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('note') border-red-300 @enderror"
                          placeholder="Nhập ghi chú (nếu có)">{{ old('note') }}</textarea>
                @error('note')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('gdmn-finance.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Hủy
                </a>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Lưu dữ liệu
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Hàm chuyển số sang chữ tiếng Việt
function numberToWords(number, unit = 'đồng') {
    const ones = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    const tens = ['', '', 'hai mươi', 'ba mươi', 'bốn mươi', 'năm mươi', 
                  'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'];
    const units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

    function convertGroup(num) {
        let result = '';
        const hundreds = Math.floor(num / 100);
        if (hundreds > 0) {
            result += ones[hundreds] + ' trăm';
            num %= 100;
            if (num > 0 && num < 10) result += ' lẻ';
        }
        
        const tensDigit = Math.floor(num / 10);
        const onesDigit = num % 10;
        
        if (tensDigit > 1) {
            if (result) result += ' ';
            result += tens[tensDigit];
            if (onesDigit == 1) result += ' mốt';
            else if (onesDigit == 5 && tensDigit > 0) result += ' lăm';
            else if (onesDigit > 0) result += ' ' + ones[onesDigit];
        } else if (tensDigit == 1) {
            if (result) result += ' ';
            result += 'mười';
            if (onesDigit == 5) result += ' lăm';
            else if (onesDigit > 0) result += ' ' + ones[onesDigit];
        } else {
            if (onesDigit > 0) {
                if (result) result += ' ';
                result += ones[onesDigit];
            }
        }
        return result;
    }

    function convertInteger(num) {
        if (num == 0) return '';
        num = num.toString().replace(/^0+/, '');
        if (!num) return '';
        
        const groups = [];
        const len = num.length;
        for (let i = len; i > 0; i -= 3) {
            const start = Math.max(0, i - 3);
            groups.push(num.substring(start, i));
        }
        groups.reverse();
        
        const result = [];
        const groupCount = groups.length;
        for (let i = 0; i < groupCount; i++) {
            const groupValue = parseInt(groups[i]);
            if (groupValue > 0) {
                let groupWords = convertGroup(groupValue);
                const unitIndex = groupCount - i - 1;
                if (unitIndex > 0 && units[unitIndex]) {
                    groupWords += ' ' + units[unitIndex];
                }
                result.push(groupWords);
            }
        }
        return result.join(' ');
    }

    let isNegative = false;
    if (number < 0) {
        isNegative = true;
        number = Math.abs(number);
    }

    const parts = number.toString().split('.');
    const integerPart = parts[0];
    const decimalPart = parts[1] || '';
    let words = convertInteger(integerPart);

    if (decimalPart && parseInt(decimalPart) > 0) {
        const trimmedDecimal = decimalPart.replace(/0+$/, '');
        if (trimmedDecimal) {
            words += ' phẩy ' + trimmedDecimal.split('').map(d => d == '0' ? 'không' : ones[parseInt(d)]).join(' ');
        }
    }

    if (words) {
        words += ' ' + unit;
    } else {
        words = 'không ' + unit;
    }

    if (isNegative) {
        words = 'âm ' + words;
    }

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function currencyToWords(value, unit) {
    const cleanValue = value.toString().replace(/[^\d\.,]/g, '');
    let normalizedValue = cleanValue.replace(/\./g, '').replace(',', '.');
    const number = parseFloat(normalizedValue);
    
    if (isNaN(number) || number === 0) {
        return 'Không ' + (unit === 'Tr.đồng' ? 'triệu đồng' : unit);
    }
    
    if (unit === 'Tr.đồng' || unit === 'Tr.Đồng') {
        const actualAmount = number * 1000000;
        return numberToWords(actualAmount, 'đồng');
    }
    
    return numberToWords(number, unit.replace('Tr.đồng', 'triệu đồng'));
}

function updateTooltip(input, unit) {
    const value = input.value;
    if (value && value.trim() !== '' && value !== '0') {
        const words = currencyToWords(value, unit);
        input.setAttribute('title', words);
    } else {
        input.removeAttribute('title');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.getElementById('year');
    const provinceSelect = document.getElementById('province_id');
    const wardSelect = document.getElementById('ward_id');
    const mainItemSelect = document.getElementById('main_item');
    const subItemSelect = document.getElementById('sub_item');
    const unitSelect = document.getElementById('unit');
    const amountInput = document.getElementById('amount');
    const noteInput = document.getElementById('note');
    
    const subItems = @json(\App\Models\GdmnFinance::SUB_ITEMS);
    const units = @json(\App\Models\GdmnFinance::UNITS);
    
    let existingDataId = null;
    
    function updateSubItems() {
        const mainItem = mainItemSelect.value;
        subItemSelect.innerHTML = '<option value="">Chọn tiểu mục (nếu có)</option>';
        
        if (mainItem && subItems[mainItem]) {
            Object.entries(subItems[mainItem]).forEach(([key, value]) => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = key + '. ' + value;
                subItemSelect.appendChild(option);
            });
        }
    }
    
    function updateUnit() {
        const mainItem = mainItemSelect.value;
        if (mainItem && units[mainItem]) {
            unitSelect.value = units[mainItem];
        }
        updateAmountTooltip();
    }
    
    function updateAmountTooltip() {
        if (amountInput && unitSelect) {
            updateTooltip(amountInput, unitSelect.value);
        }
    }
    
    async function checkExistingData() {
        if (!yearSelect || !mainItemSelect) {
            return;
        }
        
        const year = yearSelect.value;
        const provinceId = (provinceSelect?.value) || document.querySelector('input[name="province_id"]')?.value;
        const wardId = (wardSelect?.value) || document.querySelector('input[name="ward_id"]')?.value;
        const mainItem = mainItemSelect.value;
        const subItem = subItemSelect?.value;
        
        if (!year || !wardId || !mainItem) {
            hideExistingDataAlert();
            return;
        }
        
        try {
            const requestData = {
                year: year,
                province_id: provinceId,
                ward_id: wardId,
                main_item: mainItem,
                sub_item: subItem || null
            };
            
            const response = await fetch(`/api/gdmn-finance/check-existing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.exists) {
                    showExistingDataAlert(data);
                } else {
                    hideExistingDataAlert();
                }
            }
        } catch (error) {
            // Silent error handling
        }
    }
    
    function showExistingDataAlert(data) {
        existingDataId = data.id;
        const alert = document.getElementById('existing-data-alert');
        const info = document.getElementById('existing-data-info');
        const existingIdInput = document.getElementById('existing_id');
        
        // Set existing ID in hidden input for update
        if (existingIdInput) {
            existingIdInput.value = data.id;
        }
        
        if (info) {
            info.textContent = `Số tiền hiện tại: ${data.amount} ${data.unit}`;
        }
        
        if (alert) {
            alert.classList.remove('hidden');
        }
        
        // Pre-fill form with existing data
        if (amountInput) {
            amountInput.value = data.amount;
        }
        
        if (unitSelect) {
            unitSelect.value = data.unit;
        }
        
        if (noteInput) {
            noteInput.value = data.note || '';
        }
    }
    
    function hideExistingDataAlert() {
        const alert = document.getElementById('existing-data-alert');
        const existingIdInput = document.getElementById('existing_id');
        
        if (alert) {
            alert.classList.add('hidden');
        }
        
        if (existingIdInput) {
            existingIdInput.value = '';
        }
        
        existingDataId = null;
    }
    
    // Event listeners
    if (mainItemSelect) {
        mainItemSelect.addEventListener('change', function() {
            updateSubItems();
            updateUnit();
            setTimeout(checkExistingData, 100);
        });
    }
    
    if (subItemSelect) {
        subItemSelect.addEventListener('change', function() {
            setTimeout(checkExistingData, 100);
        });
    }
    
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            setTimeout(checkExistingData, 100);
        });
    }
    
    if (wardSelect) {
        wardSelect.addEventListener('change', function() {
            setTimeout(checkExistingData, 100);
        });
    }
    
    // Thêm event listeners cho input số tiền
    if (amountInput) {
        // Update tooltip khi blur
        amountInput.addEventListener('blur', updateAmountTooltip);
        
        // Update tooltip khi input thay đổi
        amountInput.addEventListener('input', updateAmountTooltip);
        
        // Update tooltip khi hover
        amountInput.addEventListener('mouseenter', updateAmountTooltip);
        
        // Update tooltip khi đơn vị thay đổi
        unitSelect.addEventListener('change', updateAmountTooltip);
        
        // Cập nhật tooltip ban đầu
        updateAmountTooltip();
    }
    
    // Initialize on page load
    updateSubItems();
    updateUnit();
    setTimeout(checkExistingData, 100);
});
</script>

<style>
input[type="text"][title] {
    cursor: help;
}

input[type="text"][title]:hover {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>
@endsection
