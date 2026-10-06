@extends('layouts.front')
@section('title', 'Import Báo cáo tài chính GDMN')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Import Báo Cáo Tài Chính GDMN</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Tải lên file Excel để import dữ liệu báo cáo tài chính
                </p>
            </div>
            <a href="{{ route('gdmn-finance.index') }}" 
               class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Quay lại
            </a>
        </div>
    </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if(session('warning'))
            <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-yellow-800">{{ session('warning') }}</p>
                        @if(session('errors_detail'))
                            <pre class="mt-2 text-xs text-yellow-700 whitespace-pre-wrap">{{ session('errors_detail') }}</pre>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        @foreach($errors->all() as $error)
                            <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Content -->
        <div class="card">
            <!-- Filter Form -->
            <form method="GET" class="grid gap-4 md:grid-cols-3">
                    <!-- Năm -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 mb-2">Năm</label>
                        <select name="year" id="year" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                            @for($i = date('Y'); $i >= 2020; $i--)
                                <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    
                    <!-- Tỉnh/Thành phố -->
                    <div>
                        <label for="province_id_filter" class="block text-sm font-medium text-gray-700 mb-2">Tỉnh/Thành phố</label>
                        @php
                            $user = Auth::user();
                            $lockProvince = !$user || ($user->role_id != 1 && $user->province_id);
                        @endphp
                        
                        @if($lockProvince)
                            <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm text-gray-700">
                                {{ $provinces->first()->name ?? 'N/A' }}
                            </div>
                            <input type="hidden" name="province_id" value="{{ $selectedProvinceId }}">
                        @else
                            <select name="province_id" id="province_id_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                                <option value="">Tất cả tỉnh</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}" {{ ($selectedProvinceId ?? request('province_id')) == $province->id ? 'selected' : '' }}>
                                        {{ $province->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    
                    <!-- Xã/Phường -->
                    <div>
                        <label for="ward_id_filter" class="block text-sm font-medium text-gray-700 mb-2">Xã/Phường <span class="text-red-500">*</span></label>
                        @php
                            $lockWard = !$user || ($user->role_id != 1 && $user->ward_id);
                        @endphp
                        
                        @if($lockWard)
                            <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm text-gray-700">
                                {{ $wards->firstWhere('id', $selectedWardId)->name ?? 'N/A' }}
                            </div>
                            <input type="hidden" name="ward_id" value="{{ $selectedWardId }}">
                        @else
                            <select name="ward_id" id="ward_id_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                                <option value="">-- Chọn xã/phường --</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ ($selectedWardId ?? request('ward_id')) == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            </form>

            <!-- Download Template Button -->
            <div class="mt-3 card">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">Tải file Excel mẫu</h3>
                        <p class="text-sm text-slate-600 mt-1">File mẫu bao gồm cả hướng dẫn chi tiết và dữ liệu mẫu</p>
                    </div>
                    <a href="{{ route('gdmn-finance.template', array_filter(['year' => $year, 'province_id' => $selectedProvinceId, 'ward_id' => $selectedWardId])) }}" 
                       class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
                        Tải file Excel mẫu
                    </a>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="mt-3 card">
                <h3 class="text-lg font-semibold mb-4">Tải lên file Excel</h3>
                
                @if(!$selectedWardId)
                    <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                        <p class="text-sm text-yellow-800">⚠️ Vui lòng chọn Xã/Phường trước khi import dữ liệu.</p>
                    </div>
                @endif
                
                <form action="{{ route('gdmn-finance.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="ward_id" value="{{ $selectedWardId }}">
                    
                    <div class="space-y-6">
                    <!-- File Upload -->
                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                            Chọn file Excel để import <span class="text-red-500">*</span>
                        </label>
                        <input type="file" 
                               id="file" 
                               name="file" 
                               accept=".xlsx,.xls" 
                               required 
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               onchange="updateFileName(this)">
                        <p class="text-xs text-gray-500 mt-1">Hỗ trợ .xlsx/.xls. Dung lượng ≤ 10MB.</p>
                        <p id="file-name" class="text-sm text-blue-600 font-medium mt-2"></p>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-200">
                        <a href="{{ route('gdmn-finance.index') }}" 
                           class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-slate-700">
                            Hủy
                        </a>
                        <button type="submit" 
                                {{ !$selectedWardId ? 'disabled' : '' }}
                                class="rounded-lg {{ $selectedWardId ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-400 cursor-not-allowed' }} px-3 py-2 text-white">
                            Bắt đầu Import
                        </button>
                    </div>
                </div>
            </form>
        </div>

     
    </div>
</div>

<script>
function updateFileName(input) {
    const fileNameDisplay = document.getElementById('file-name');
    if (input.files && input.files[0]) {
        fileNameDisplay.textContent = '📄 ' + input.files[0].name;
    } else {
        fileNameDisplay.textContent = '';
    }
}
</script>

<style>
.card {
    @apply bg-white rounded-lg shadow-sm border p-6;
}
</style>
@endsection


