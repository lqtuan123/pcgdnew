@csrf
<div class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-4">
<label class="block text-xs text-slate-500 mb-1">Mã</label>
<input type="text" name="code" value="{{ old('code',$ethnicity->code) }}" class="w-full rounded-lg border px-3 py-2">
@error('code')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>


<div class="md:col-span-4">
<label class="block text-xs text-slate-500 mb-1">Tên dân tộc <span class="text-rose-600">*</span></label>
<input required type="text" name="name" value="{{ old('name',$ethnicity->name) }}" class="w-full rounded-lg border px-3 py-2">
@error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>


<div class="md:col-span-4">
<label class="block text-xs text-slate-500 mb-1">Slug (để trống sẽ tự tạo)</label>
<input type="text" name="slug" value="{{ old('slug',$ethnicity->slug) }}" class="w-full rounded-lg border px-3 py-2">
@error('slug')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>


<div class="md:col-span-3 flex items-center gap-2 mt-2">
<input type="hidden" name="is_minority" value="0">
<input id="is_minority" type="checkbox" name="is_minority" value="1" class="rounded border"
@checked(old('is_minority', (int)$ethnicity->is_minority))>
<label for="is_minority">Thuộc nhóm thiểu số</label>
@error('is_minority')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>


<div class="md:col-span-12">
<label class="block text-xs text-slate-500 mb-1">Ghi chú</label>
<textarea name="note" rows="3" class="w-full rounded-lg border px-3 py-2">{{ old('note',$ethnicity->note) }}</textarea>
@error('note')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
</div>
</div>


<div class="mt-4 flex items-center gap-2">
<button class="btn-primary">Lưu</button>
<a href="{{ route('admin.settings.ethnicities.index') }}" class="btn-soft">Huỷ</a>
</div>