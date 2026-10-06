@csrf
<div class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-6">
<label class="block text-xs text-slate-500 mb-1">Tên cấu hình <span class="text-rose-600">*</span></label>
<input required type="text" name="name" value="{{ old('name',$decimalFormat->name ?? '') }}" 
       placeholder="Ví dụ: Cấu hình Việt Nam" class="w-full rounded-lg border px-3 py-2">
@error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>

<div class="md:col-span-3">
<label class="block text-xs text-slate-500 mb-1">Dấu tách thập phân <span class="text-rose-600">*</span></label>
<select required name="decimal_separator" class="w-full rounded-lg border px-3 py-2">
    <option value="," @selected(old('decimal_separator', $decimalFormat->decimal_separator ?? ',') == ',')>, (dấu phẩy)</option>
    <option value="." @selected(old('decimal_separator', $decimalFormat->decimal_separator ?? ',') == '.')>. (dấu chấm)</option>
</select>
@error('decimal_separator')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>

<div class="md:col-span-3">
<label class="block text-xs text-slate-500 mb-1">Dấu tách hàng nghìn <span class="text-rose-600">*</span></label>
<select required name="thousands_separator" class="w-full rounded-lg border px-3 py-2">
    <option value="." @selected(old('thousands_separator', $decimalFormat->thousands_separator ?? '.') == '.')>. (dấu chấm)</option>
    <option value="," @selected(old('thousands_separator', $decimalFormat->thousands_separator ?? '.') == ',')>, (dấu phẩy)</option>
</select>
@error('thousands_separator')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>

<div class="md:col-span-12">
<label class="block text-xs text-slate-500 mb-1">Mô tả</label>
<textarea name="description" rows="3" placeholder="Mô tả chi tiết về cấu hình này..." 
          class="w-full rounded-lg border px-3 py-2">{{ old('description',$decimalFormat->description ?? '') }}</textarea>
@error('description')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>

<div class="md:col-span-12">
<div class="bg-slate-50 p-4 rounded-lg">
    <h4 class="font-medium text-sm mb-2">Ví dụ hiển thị:</h4>
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="text-slate-600">Số nguyên:</span>
            <span class="font-mono ml-2" id="example-integer">1.234.567</span>
        </div>
        <div>
            <span class="text-slate-600">Số thập phân:</span>
            <span class="font-mono ml-2" id="example-decimal">1.234.567,89</span>
        </div>
    </div>
</div>
</div>
</div>

<div class="mt-4 flex items-center gap-2">
<button class="btn-primary">Lưu</button>
<a href="{{ route('admin.settings.decimal-formats.index') }}" class="btn-soft">Huỷ</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const decimalSelect = document.querySelector('select[name="decimal_separator"]');
    const thousandsSelect = document.querySelector('select[name="thousands_separator"]');
    
    function updateExample() {
        const decimal = decimalSelect.value;
        const thousands = thousandsSelect.value;
        
        const exampleInteger = '1234567'.replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
        const exampleDecimal = '1234567.89'.replace('.', decimal).replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
        
        document.getElementById('example-integer').textContent = exampleInteger;
        document.getElementById('example-decimal').textContent = exampleDecimal;
    }
    
    decimalSelect.addEventListener('change', updateExample);
    thousandsSelect.addEventListener('change', updateExample);
    
    // Cập nhật ví dụ khi trang load
    updateExample();
});
</script>
