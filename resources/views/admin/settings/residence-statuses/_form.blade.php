@csrf
<div class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-6">
<label class="block text-xs text-slate-500 mb-1">Tên tình trạng cư trú <span class="text-rose-600">*</span></label>
<input required type="text" name="name" value="{{ old('name',$residenceStatus->name) }}" 
placeholder="Ví dụ: Vắng, Lưu trú..."
class="w-full rounded-lg border px-3 py-2">
@error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>
</div>

<div class="mt-4 flex items-center gap-2">
<button class="btn-primary">Lưu</button>
<a href="{{ route('admin.settings.residence-statuses.index') }}" class="btn-soft">Huỷ</a>
</div>
