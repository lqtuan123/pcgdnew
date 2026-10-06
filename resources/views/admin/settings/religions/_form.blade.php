@csrf
<div class="grid gap-3 md:grid-cols-12">
  <div class="md:col-span-4">
    <label class="block text-xs text-slate-500 mb-1">Mã</label>
    <input type="text" name="code" value="{{ old('code',$religion->code) }}" class="w-full rounded-lg border px-3 py-2">
    @error('code')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-4">
    <label class="block text-xs text-slate-500 mb-1">Tên tôn giáo <span class="text-rose-600">*</span></label>
    <input required type="text" name="name" value="{{ old('name',$religion->name) }}" class="w-full rounded-lg border px-3 py-2">
    @error('name')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-4">
    <label class="block text-xs text-slate-500 mb-1">Slug (để trống sẽ tự tạo)</label>
    <input type="text" name="slug" value="{{ old('slug',$religion->slug) }}" class="w-full rounded-lg border px-3 py-2">
    @error('slug')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-3 flex items-center gap-2 mt-2">
    <input type="hidden" name="is_official" value="0">
    <input id="is_official" type="checkbox" name="is_official" value="1" class="rounded border"
           @checked(old('is_official', (int)$religion->is_official))>
    <label for="is_official">Chính thức (được công nhận)</label>
    @error('is_official')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-12">
    <label class="block text-xs text-slate-500 mb-1">Ghi chú</label>
    <textarea name="note" rows="3" class="w-full rounded-lg border px-3 py-2">{{ old('note',$religion->note) }}</textarea>
    @error('note')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>
</div>

<div class="mt-4 flex items-center gap-2">
  <button class="btn-primary">Lưu</button>
  <a href="{{ route('admin.settings.religions.index') }}" class="btn-soft">Huỷ</a>
</div>
