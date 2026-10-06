@csrf
<div class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-6">
<label class="block text-xs text-slate-500 mb-1">Tên hoàn cảnh đặc biệt <span class="text-rose-600">*</span></label>
<input required type="text" name="name" value="{{ old('name',$specialCircumstance->name) }}" 
placeholder="Ví dụ: Chuyển đi, Chuyển đến, Chết..."
class="w-full rounded-lg border px-3 py-2">
@error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>
</div>

<div class="mt-4 flex items-center gap-2">
<button class="btn-primary">Lưu</button>
<a href="{{ route('admin.settings.special-circumstances.index') }}" class="btn-soft">Huỷ</a>
</div>
