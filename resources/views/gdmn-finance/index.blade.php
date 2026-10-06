@extends('layouts.front')
@section('title', 'Báo cáo tài chính GDMN')
@section('description', 'Báo cáo tài chính chi cho giáo dục mầm non')

@php
    // Lấy cấu hình decimal format của user
    $decimalFormat = Auth::user()->getDecimalFormat();
    $decimalSep = $decimalFormat->decimal_separator ?? ',';
    $thousandsSep = $decimalFormat->thousands_separator ?? '.';
@endphp

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Báo cáo tài chính GDMN</h1>
                <p class="text-sm text-gray-600 mt-1">Báo cáo tài chính chi cho giáo dục mầm non theo từng năm</p>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Định dạng số: <strong class="mx-1">{{ $decimalFormat->name }}</strong>
            <span class="text-gray-400">(VD: 1{{ $thousandsSep }}234{{ $decimalSep }}56)</span>
            <a href="/cau-hinh/phan-tach-thap-phan" class="ml-2 text-blue-600 hover:text-blue-700 underline">Thay đổi</a>
        </p>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Bộ lọc dữ liệu</h3>
        </div>
        <form method="GET" class="grid gap-4 md:grid-cols-4">
            <div>
                <label for="year" class="block text-sm font-medium text-gray-700 mb-2">Năm</label>
                <select name="year" id="year" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @for($i = date('Y'); $i >= 2020; $i--)
                        <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            
            <div>
                <label for="province_id" class="block text-sm font-medium text-gray-700 mb-2">Tỉnh/Thành phố</label>
                @php
                    $user = Auth::user();
                    $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
                    $userProvinceId = $user?->province_id;
                    $userProvinceName = $user?->province?->name ?? null;
                    $lockProvince = $isNonAdmin && !empty($userProvinceId);
                @endphp
                
                @if($lockProvince)
                    <!-- Hiển thị tỉnh đã khóa -->
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm text-gray-700">
                        {{ $userProvinceName ?? 'ID #' . $userProvinceId }}
                    </div>
                    <input type="hidden" name="province_id" value="{{ $userProvinceId }}">
                @else
                    <!-- Dropdown chọn tỉnh -->
                    <select name="province_id" id="province_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tất cả tỉnh</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>
                                {{ $province->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>
            
             <div>
                 <label for="ward_id" class="block text-sm font-medium text-gray-700 mb-2">Xã/Phường <span class="text-red-500">*</span></label>
                 @php
                     $user = Auth::user();
                     $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
                     $userWardId = $user?->ward_id;
                     $userWardName = $user?->ward?->name ?? null;
                     $lockWard = $isNonAdmin && !empty($userWardId);
                 @endphp
                 
                 @if($lockWard)
                     <!-- Hiển thị xã đã khóa -->
                     <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm text-gray-700">
                         {{ $userWardName ?? 'ID #' . $userWardId }}
                     </div>
                     <input type="hidden" name="ward_id" value="{{ $userWardId }}">
                 @else
                     <!-- Dropdown chọn xã -->
                     <select name="ward_id" id="ward_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                         <option value="">-- Chọn xã/phường --</option>
                         @foreach($wards as $ward)
                             <option value="{{ $ward->id }}" {{ ($selectedWardId ?? request('ward_id')) == $ward->id ? 'selected' : '' }}>
                                 {{ $ward->name }}
                             </option>
                         @endforeach
                     </select>
                 @endif
             </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Lọc dữ liệu
                </button>
            </div>
        </form>
    </div>

     {{-- Report Table --}}
     <div class="bg-white rounded-xl shadow-sm border">
         <div class="px-6 py-4 border-b border-gray-200">
             <div class="flex items-center justify-between">
                 <div>
                     <h3 class="text-lg font-semibold text-gray-900">Báo cáo tài chính GDMN năm {{ $year }}</h3>
                     @if($selectedWardId)
                         <p class="text-sm text-gray-600 mt-1">
                             Xã/Phường: <span class="font-medium">{{ $finances->first()?->ward?->name ?? 'Chưa có dữ liệu' }}</span>
                         </p>
                     @endif
                 </div>
                 
                 <!-- Export/Import Buttons -->
                 <div class="flex items-center gap-2">
                     <a href="{{ route('gdmn-finance.export', array_filter(['year' => $year, 'province_id' => request('province_id'), 'ward_id' => request('ward_id')])) }}" 
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
                         Xuất Excel
                     </a>
                     
                     <a href="{{ route('gdmn-finance.import-form', array_filter(['year' => $year, 'province_id' => request('province_id'), 'ward_id' => request('ward_id')])) }}" 
                        class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-slate-700">
                         Nhập Excel
                     </a>
                 </div>
             </div>
         </div>
        
        @if(!$selectedWardId)
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa chọn xã/phường</h3>
                <p class="mt-1 text-sm text-gray-500">Vui lòng chọn xã/phường để xem và nhập dữ liệu báo cáo tài chính.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">TT</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nội dung</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">ĐVT</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">{{ $year }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach(\App\Models\GdmnFinance::MAIN_ITEMS as $mainKey => $mainName)
                        @php
                            $mainData = $finances->where('main_item', $mainKey)->whereNull('sub_item')->first();
                            $subItems = \App\Models\GdmnFinance::SUB_ITEMS[$mainKey] ?? [];
                        @endphp
                        
                        <!-- Main Item Row -->
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                {{ $mainKey }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                {{ $mainName }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-left">
                                {{ $mainData->unit ?? \App\Models\GdmnFinance::UNITS[$mainKey] ?? 'Tr.đồng' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                @if($mainData)
                                    <div class="flex items-center justify-center space-x-2">
                                            <input type="text" 
                                                   value="{{ $mainData->amount }}" 
                                                   class="w-24 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                   onchange="updateAmount({{ $mainData->id }}, this.value)"
                                                   onkeypress="if(event.key==='Enter') saveAmount({{ $mainData->id }})"
                                                   onblur="saveAmount({{ $mainData->id }})"
                                                   data-id="{{ $mainData->id }}"
                                                   data-decimal-sep="{{ $decimalSep }}"
                                                   data-thousands-sep="{{ $thousandsSep }}"
                                                   placeholder="0">
                                        <button onclick="saveAmount({{ $mainData->id }})" 
                                                class="text-green-600 hover:text-green-800 text-xs">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @else
                                    <div class="flex items-center justify-center space-x-2">
                                            <input type="text" 
                                                   value="" 
                                                   class="w-24 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                   onkeypress="if(event.key==='Enter') { this.blur(); createNewRecord({{ $mainKey }}, null, this.value); }"
                                                   onblur="if(this.value && this.value.trim() !== '') createNewRecord({{ $mainKey }}, null, this.value)"
                                                   placeholder="Nhập số tiền">
                                        <button onclick="createNewRecord({{ $mainKey }}, null, this.parentElement.querySelector('input').value)" 
                                                class="text-blue-600 hover:text-blue-800 text-xs">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Sub Items -->
                        @if(!empty($subItems))
                            @foreach($subItems as $subKey => $subName)
                                @php
                                    $subData = $finances->where('main_item', $mainKey)->where('sub_item', $subKey)->first();
                                @endphp
                                <tr class="bg-white">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        -
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 pl-8">
                                        - {{ $subName }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-left">
                                        {{ $subData->unit ?? \App\Models\GdmnFinance::UNITS[$mainKey] ?? 'Tr.đồng' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        @if($subData)
                                            <div class="flex items-center justify-center space-x-2">
                                                <input type="text" 
                                                       value="{{ $subData->amount }}" 
                                                       class="w-24 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                       onchange="updateAmount({{ $subData->id }}, this.value)"
                                                       onkeypress="if(event.key==='Enter') saveAmount({{ $subData->id }})"
                                                       onblur="saveAmount({{ $subData->id }})"
                                                       data-id="{{ $subData->id }}"
                                                       data-decimal-sep="{{ $decimalSep }}"
                                                       data-thousands-sep="{{ $thousandsSep }}"
                                                       placeholder="0">
                                                <button onclick="saveAmount({{ $subData->id }})" 
                                                        class="text-green-600 hover:text-green-800 text-xs">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @else
                                            <div class="flex items-center justify-center space-x-2">
                                                <input type="text" 
                                                       value="" 
                                                       class="w-24 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                       onkeypress="if(event.key==='Enter') { this.blur(); createNewRecord({{ $mainKey }}, {{ $subKey }}, this.value); }"
                                                       onblur="if(this.value && this.value.trim() !== '') createNewRecord({{ $mainKey }}, {{ $subKey }}, this.value)"
                                                       data-decimal-sep="{{ $decimalSep }}"
                                                       data-thousands-sep="{{ $thousandsSep }}"
                                                       placeholder="Nhập số tiền">
                                                <button onclick="createNewRecord({{ $mainKey }}, {{ $subKey }}, this.parentElement.querySelector('input').value)" 
                                                        class="text-blue-600 hover:text-blue-800 text-xs">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Summary --}}
    {{-- @if($finances->count() > 0)
        <div class="bg-white rounded-xl shadow-sm border p-6 mt-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tổng kết</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ $finances->count() }}
                    </div>
                    <div class="text-sm text-gray-500">Số bản ghi</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        @php
                            // Chỉ tính tổng từ các chỉ mục chính (main_item), không tính tiểu mục
                            $totalAmount = $finances->whereNull('sub_item')->sum(function($item) {
                                $amount = str_replace(',', '.', $item->amount);
                                return floatval($amount);
                            });
                        @endphp
                        {{ number_format($totalAmount, 0, ',', '.') }}
                    </div>
                    <div class="text-sm text-gray-500">Tổng số tiền (Tr.đồng)</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        {{ $finances->groupBy('ward_id')->count() }}
                    </div>
                    <div class="text-sm text-gray-500">Số xã có dữ liệu</div>
                </div>
            </div>
        </div>
    @endif --}}

    {{-- Actions --}}
    <div class="mt-8 flex justify-end space-x-3">
        
        
        
        
        <a href="{{ route('gdmn-finance.create') }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Thêm dữ liệu
        </a>
    </div>
</div>

<script>
// Lấy cấu hình decimal format
const DECIMAL_SEP = '{{ $decimalSep }}';
const THOUSANDS_SEP = '{{ $thousandsSep }}';

// Đơn vị tính theo từng mục chính
const UNITS = {
    1: 'Tr.đồng',
    2: '%',
    3: 'Tr.đồng',
    4: 'Tr.đồng',
    5: 'Tr.đồng',
    6: 'Tr.đồng',
    7: 'Tr.đồng'
};

// Hàm chuyển số sang chữ tiếng Việt
function numberToWords(number, unit = 'đồng') {
    const ones = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    const tens = ['', '', 'hai mươi', 'ba mươi', 'bốn mươi', 'năm mươi', 
                  'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'];
    const units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

    function convertGroup(num) {
        let result = '';
        
        // Hàng trăm
        const hundreds = Math.floor(num / 100);
        if (hundreds > 0) {
            result += ones[hundreds] + ' trăm';
            num %= 100;
            
            if (num > 0 && num < 10) {
                result += ' lẻ';
            }
        }
        
        // Hàng chục và đơn vị
        const tensDigit = Math.floor(num / 10);
        const onesDigit = num % 10;
        
        if (tensDigit > 1) {
            if (result) result += ' ';
            result += tens[tensDigit];
            
            if (onesDigit == 1) {
                result += ' mốt';
            } else if (onesDigit == 5 && tensDigit > 0) {
                result += ' lăm';
            } else if (onesDigit > 0) {
                result += ' ' + ones[onesDigit];
            }
        } else if (tensDigit == 1) {
            if (result) result += ' ';
            result += 'mười';
            
            if (onesDigit == 5) {
                result += ' lăm';
            } else if (onesDigit > 0) {
                result += ' ' + ones[onesDigit];
            }
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

    function convertDecimal(decimal) {
        const result = [];
        for (let i = 0; i < decimal.length; i++) {
            const digit = parseInt(decimal[i]);
            result.push(digit == 0 ? 'không' : ones[digit]);
        }
        return result.join(' ');
    }

    // Xử lý số âm
    let isNegative = false;
    if (number < 0) {
        isNegative = true;
        number = Math.abs(number);
    }

    // Tách phần nguyên và phần thập phân
    const parts = number.toString().split('.');
    const integerPart = parts[0];
    const decimalPart = parts[1] || '';

    // Chuyển đổi
    let words = convertInteger(integerPart);

    // Thêm phần thập phân nếu có
    if (decimalPart && parseInt(decimalPart) > 0) {
        const trimmedDecimal = decimalPart.replace(/0+$/, '');
        if (trimmedDecimal) {
            words += ' phẩy ' + convertDecimal(trimmedDecimal);
        }
    }

    // Thêm đơn vị
    if (words) {
        words += ' ' + unit;
    } else {
        words = 'không ' + unit;
    }

    // Thêm từ "âm" nếu là số âm
    if (isNegative) {
        words = 'âm ' + words;
    }

    // Viết hoa chữ cái đầu
    return words.charAt(0).toUpperCase() + words.slice(1);
}

// Hàm chuyển số tiền sang chữ (với đơn vị Tr.Đồng)
function currencyToWords(value, unit) {
    // Loại bỏ các ký tự không phải số
    const cleanValue = value.toString().replace(/[^\d\.,]/g, '');
    
    // Chuyển đổi dấu phân cách về dạng chuẩn
    let normalizedValue = cleanValue;
    if (DECIMAL_SEP === ',') {
        normalizedValue = cleanValue.replace(/\./g, '').replace(',', '.');
    } else {
        normalizedValue = cleanValue.replace(/,/g, '');
    }
    
    const number = parseFloat(normalizedValue);
    
    if (isNaN(number) || number === 0) {
        return 'Không ' + (unit === 'Tr.đồng' ? 'triệu đồng' : unit);
    }
    
    // Nếu đơn vị là Tr.đồng (triệu đồng)
    if (unit === 'Tr.đồng' || unit === 'Tr.Đồng') {
        // Nhân với 1 triệu để có số tiền thực
        const actualAmount = number * 1000000;
        return numberToWords(actualAmount, 'đồng');
    }
    
    // Các đơn vị khác
    return numberToWords(number, unit.replace('Tr.đồng', 'triệu đồng'));
}

// Hàm cập nhật tooltip cho input
function updateTooltip(input, unit) {
    const value = input.value;
    if (value && value.trim() !== '' && value !== '0') {
        const words = currencyToWords(value, unit);
        input.setAttribute('title', words);
    } else {
        input.removeAttribute('title');
    }
}

// Biến lưu trữ dữ liệu tạm thời
let pendingUpdates = {};
let saveTimeouts = {};
let processingRecords = {}; // Theo dõi các bản ghi đang được xử lý

// Helper function để giữ nguyên giá trị nhập vào (không parse)
function parseAmount(value) {
    // Giữ nguyên format người dùng nhập: 4, 4.1, 4,1
    if (!value || value === '') return '0';
    return value.toString().trim();
}

// Helper function để validate số theo format
function isValidNumber(value) {
    if (!value || value.trim() === '') return false;
    
    // Cho phép số, dấu phẩy, dấu chấm, khoảng trắng
    const regex = /^[\d\s\.,]+$/;
    return regex.test(value);
}

// Helper function để format số theo cấu hình user
function formatNumberDisplay(value) {
    if (!value || value === '') return '';
    
    let strValue = value.toString().trim();
    
    // Tách phần nguyên và phần thập phân
    let decimalPart = '';
    let integerPart = strValue;
    
    // Kiểm tra có phần thập phân không (dấu phẩy hoặc chấm)
    if (strValue.includes(',')) {
        let parts = strValue.split(',');
        integerPart = parts[0];
        decimalPart = parts.slice(1).join(''); // Nối lại nếu có nhiều dấu phẩy
    } else if (strValue.includes('.')) {
        let parts = strValue.split('.');
        integerPart = parts[0];
        decimalPart = parts.slice(1).join(''); // Nối lại nếu có nhiều dấu chấm
    }
    
    // Loại bỏ khoảng trắng và dấu phân cách khỏi phần nguyên
    integerPart = integerPart.replace(/[\s\.,]/g, '');
    
    // Nếu phần nguyên không phải số, trả về nguyên bản
    if (!/^\d*$/.test(integerPart)) {
        return value;
    }
    
    // Nếu phần nguyên rỗng, đặt là 0
    if (integerPart === '') {
        integerPart = '0';
    }
    
    // Thêm dấu phân cách hàng nghìn cho phần nguyên
    let formattedInteger = '';
    let count = 0;
    for (let i = integerPart.length - 1; i >= 0; i--) {
        if (count === 3) {
            formattedInteger = THOUSANDS_SEP + formattedInteger;
            count = 0;
        }
        formattedInteger = integerPart[i] + formattedInteger;
        count++;
    }
    
    // Ghép phần nguyên và phần thập phân
    if (decimalPart !== '') {
        return formattedInteger + DECIMAL_SEP + decimalPart;
    }
    
    return formattedInteger;
}

// Format input khi blur (rời khỏi ô nhập)
function formatInputOnBlur(input) {
    const value = input.value;
    if (value && value.trim() !== '') {
        const formatted = formatNumberDisplay(value);
        if (formatted !== value) {
            input.value = formatted;
        }
    }
}


// Xử lý lọc xã theo tỉnh
document.addEventListener('DOMContentLoaded', function() {
    const provinceSelect = document.getElementById('province_id');
    const wardSelect = document.getElementById('ward_id');
    
    // Thêm auto-format cho tất cả input số tiền
    document.querySelectorAll('input[type="text"][placeholder*="tiền"], input[type="text"][data-decimal-sep]').forEach(input => {
        // Lấy đơn vị từ cột ĐVT gần nhất
        const row = input.closest('tr');
        let unit = 'Tr.đồng'; // Mặc định
        if (row) {
            const unitCell = row.querySelectorAll('td')[2]; // Cột ĐVT là cột thứ 3
            if (unitCell) {
                unit = unitCell.textContent.trim();
            }
        }
        
        // Lưu đơn vị vào input để sử dụng sau
        input.setAttribute('data-unit', unit);
        
        // Format giá trị hiện có khi load trang
        if (input.value && input.value.trim() !== '' && input.value !== '0') {
            formatInputOnBlur(input);
            updateTooltip(input, unit);
        }
        
        // Format và update tooltip khi focus out
        input.addEventListener('blur', function() {
            formatInputOnBlur(this);
            updateTooltip(this, this.getAttribute('data-unit') || 'Tr.đồng');
        });
        
        // Update tooltip khi input thay đổi (live update)
        input.addEventListener('input', function() {
            updateTooltip(this, this.getAttribute('data-unit') || 'Tr.đồng');
        });
        
        // Update tooltip khi hover
        input.addEventListener('mouseenter', function() {
            updateTooltip(this, this.getAttribute('data-unit') || 'Tr.đồng');
        });
        
        // Chỉ cho phép nhập số và dấu phân cách
        input.addEventListener('keypress', function(e) {
            const char = e.key;
            const currentValue = this.value;
            
            // Cho phép: số, dấu phẩy, dấu chấm, backspace, delete, arrow keys, tab
            if (!/[\d\.,]/.test(char) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'].includes(char)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Kiểm tra xem tỉnh có bị khóa không
    const isProvinceLocked = provinceSelect && provinceSelect.disabled;
    
    
    if (provinceSelect && wardSelect && !isProvinceLocked) {
        provinceSelect.addEventListener('change', function() {
            const provinceId = this.value;
            
            if (provinceId) {
                // Gọi API để lấy danh sách xã theo tỉnh
                fetch(`/api/gdmn-finance/wards/${provinceId}`)
                    .then(response => response.json())
                    .then(data => {
                        wardSelect.innerHTML = '<option value="">Tất cả xã</option>';
                        data.forEach(ward => {
                            const option = document.createElement('option');
                            option.value = ward.id;
                            option.textContent = ward.name;
                            wardSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            } else {
                // Reset về tất cả xã
                wardSelect.innerHTML = '<option value="">Tất cả xã</option>';
                @foreach($wards as $ward)
                    const option{{ $ward->id }} = document.createElement('option');
                    option{{ $ward->id }}.value = '{{ $ward->id }}';
                    option{{ $ward->id }}.textContent = '{{ $ward->name }}';
                    wardSelect.appendChild(option{{ $ward->id }});
                @endforeach
            }
        });
    }
});

// Cập nhật số tiền và tự động lưu sau 0.5 giây
function updateAmount(id, value) {
    pendingUpdates[id] = value;
    
    // Clear timeout cũ nếu có
    if (saveTimeouts[id]) {
        clearTimeout(saveTimeouts[id]);
    }
    
    // Tự động lưu sau 0.5 giây
    saveTimeouts[id] = setTimeout(() => {
        saveAmount(id);
    }, 500);
}

// Lưu số tiền ngay lập tức
async function saveAmount(id) {
    const amount = pendingUpdates[id];
    if (amount === undefined) return;
    
    // Clear timeout nếu đang chờ
    if (saveTimeouts[id]) {
        clearTimeout(saveTimeouts[id]);
        delete saveTimeouts[id];
    }
    
    // Hiển thị loading state
    const input = document.querySelector(`input[data-id="${id}"]`);
    if (input) {
        // Format trước khi lưu
        formatInputOnBlur(input);
        
        input.style.opacity = '0.6';
        input.disabled = true;
    }
    
    try {
        const response = await fetch(`/gdmn-finance/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
                body: JSON.stringify({
                    amount: parseAmount(amount),
                    _method: 'PUT'
                })
        });
        
        if (response.ok) {
            const result = await response.json();
            if (result.success !== false) {
                delete pendingUpdates[id];
                showNotification('Đã cập nhật thành công!', 'success');
                // Không cần reload trang, chỉ cập nhật UI
            } else {
                showNotification(result.message || 'Có lỗi xảy ra khi cập nhật!', 'error');
            }
        } else {
            const error = await response.json();
            showNotification(error.message || 'Có lỗi xảy ra khi cập nhật!', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi cập nhật!', 'error');
    } finally {
        // Khôi phục input state
        if (input) {
            input.style.opacity = '1';
            input.disabled = false;
        }
    }
}

    // Tạo bản ghi mới
    async function createNewRecord(mainItem, subItem, amount) {
        // Kiểm tra giá trị rỗng
        if (!amount || amount.toString().trim() === '') {
            return; // Không hiển thị lỗi nếu không có số tiền
        }
        
        // Tạo key để theo dõi
        const recordKey = `${mainItem}_${subItem || 'null'}`;
        
        // Nếu đang xử lý bản ghi này, bỏ qua
        if (processingRecords[recordKey]) {
            return;
        }
        
        // Đánh dấu đang xử lý
        processingRecords[recordKey] = true;
        
        // Hiển thị loading state
        const input = document.querySelector(`input[onblur*="createNewRecord(${mainItem}, ${subItem || 'null'}"]`);
        if (input) {
            input.style.opacity = '0.6';
            input.disabled = true;
        }
        
         // Lấy ward_id từ form hoặc từ user
         const wardId = document.querySelector('select[name="ward_id"]')?.value || 
                       document.querySelector('input[name="ward_id"]')?.value || 
                       {{ $selectedWardId ?? 'null' }};
        
        // Lấy province_id từ form hoặc từ user
        const provinceId = document.querySelector('select[name="province_id"]')?.value || 
                          document.querySelector('input[name="province_id"]')?.value || 
                          {{ request('province_id') ? request('province_id') : 'null' }};
        
        // Kiểm tra ward_id có hợp lệ không
        if (!wardId || wardId === 'null' || wardId === '') {
            showNotification('Vui lòng chọn xã/phường trước khi nhập dữ liệu!', 'error');
            processingRecords[recordKey] = false;
            if (input) {
                input.style.opacity = '1';
                input.disabled = false;
            }
            return;
        }
        
        try {
            const requestData = {
                year: {{ $year }},
                province_id: provinceId,
                ward_id: wardId,
                main_item: mainItem,
                sub_item: subItem,
                amount: parseAmount(amount),
                unit: UNITS[mainItem] || 'Tr.đồng'
            };
            
            const response = await fetch('/gdmn-finance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            });
        
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                showNotification('Đã tạo bản ghi mới thành công!', 'success');
                
                // Cập nhật UI động thay vì reload trang
                updateUIAfterCreate(mainItem, subItem, amount, result.id);
            } else {
                showNotification(result.message || 'Có lỗi xảy ra khi tạo bản ghi!', 'error');
            }
        } else {
            const error = await response.json();
            showNotification(error.message || 'Có lỗi xảy ra khi tạo bản ghi!', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi tạo bản ghi!', 'error');
    } finally {
        // Khôi phục input state
        if (input) {
            input.style.opacity = '1';
            input.disabled = false;
        }
        
        // Xóa flag đang xử lý
        delete processingRecords[recordKey];
    }
}

// Hiển thị thông báo
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white shadow-lg ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        type === 'info' ? 'bg-blue-500' : 'bg-gray-500'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Auto remove sau 2 giây
    setTimeout(() => {
        notification.remove();
    }, 2000);
}

// Lưu tất cả thay đổi
function saveAllChanges() {
    const pendingIds = Object.keys(pendingUpdates);
    if (pendingIds.length === 0) {
        showNotification('Không có thay đổi nào để lưu!', 'info');
        return;
    }
    
    pendingIds.forEach(id => {
        saveAmount(id);
    });
    
    showNotification(`Đang lưu ${pendingIds.length} thay đổi...`, 'info');
}

// Lưu khi nhấn Ctrl+S
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        saveAllChanges();
    }
});

// Cập nhật UI sau khi tạo bản ghi mới
function updateUIAfterCreate(mainItem, subItem, amount, newId) {
    // Tìm input đã tạo bản ghi
    const input = document.querySelector(`input[onchange*="createNewRecord(${mainItem}, ${subItem || 'null'}"]`);
    if (!input) return;
    
    // Lấy đơn vị từ data attribute hoặc từ row
    const unit = input.getAttribute('data-unit') || 'Tr.đồng';
    
    // Thay đổi input thành input cập nhật
    input.setAttribute('data-id', newId);
    input.setAttribute('onchange', `updateAmount(${newId}, this.value)`);
    input.setAttribute('onkeypress', `if(event.key==='Enter') saveAmount(${newId})`);
    input.setAttribute('onblur', `saveAmount(${newId})`);
    
    // Cập nhật tooltip cho input mới
    updateTooltip(input, unit);
    
    // Thêm event listeners cho tooltip
    input.addEventListener('input', function() {
        updateTooltip(this, this.getAttribute('data-unit') || 'Tr.đồng');
    });
    
    input.addEventListener('mouseenter', function() {
        updateTooltip(this, this.getAttribute('data-unit') || 'Tr.đồng');
    });
    
    input.addEventListener('blur', function() {
        formatInputOnBlur(this);
        updateTooltip(this, this.getAttribute('data-unit') || 'Tr.đồng');
    });
    
    // Thay đổi nút từ tạo mới thành lưu
    const button = input.parentElement.querySelector('button');
    if (button) {
        button.setAttribute('onclick', `saveAmount(${newId})`);
        button.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        `;
        button.className = 'text-green-600 hover:text-green-800 text-xs';
    }
}

// Tự động lưu khi rời khỏi trang
window.addEventListener('beforeunload', function(e) {
    if (Object.keys(pendingUpdates).length > 0) {
        e.preventDefault();
        e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc chắn muốn rời khỏi trang?';
    }
});
</script>

<style>
/* Tooltip styling */
input[type="text"][title] {
    cursor: help;
    position: relative;
}

input[type="text"][title]:hover {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Custom tooltip with better styling */
input[type="text"][data-decimal-sep] {
    transition: all 0.2s ease;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        font-size: 12px;
    }
    
    table {
        border-collapse: collapse;
        width: 100%;
    }
    
    th, td {
        border: 1px solid #000;
        padding: 8px;
        text-align: left;
    }
    
    th {
        background-color: #f3f4f6;
        font-weight: bold;
    }
    
    input, button {
        display: none !important;
    }
}

/* Responsive table */
@media (max-width: 768px) {
    .overflow-x-auto {
        font-size: 12px;
    }
    
    .w-24 {
        width: 4rem;
    }
    
    .w-32 {
        width: 6rem;
    }
}
</style>
@endsection