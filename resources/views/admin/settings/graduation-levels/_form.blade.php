@csrf
<div class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-6">
<label class="block text-xs text-slate-500 mb-1">Tên bậc tốt nghiệp <span class="text-rose-600">*</span></label>
<input required type="text" name="name" value="{{ old('name',$graduationLevel->name) }}" 
placeholder="Ví dụ: MN, TH, THCS, THPT..."
class="w-full rounded-lg border px-3 py-2">
@error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>
</div>

<div class="mt-4 flex items-center gap-2">
<button class="btn-primary">Lưu</button>
<a href="{{ route('admin.settings.graduation-levels.index') }}" class="btn-soft">Huỷ</a>
</div>
