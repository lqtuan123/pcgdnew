@csrf
<div class="grid gap-4 md:grid-cols-12">
  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Tên nhóm báo cáo <span class="text-rose-600">*</span></label>
    <input type="text" name="name" value="{{ old('name', $reportGroup->name ?? '') }}" 
           class="w-full rounded-lg border px-3 py-2 @error('name') border-rose-500 @enderror"
           placeholder="Ví dụ: Mầm non, Tiểu học..."
           required>
    @error('name')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Mã nhóm báo cáo <span class="text-rose-600">*</span></label>
    <input type="text" name="code" value="{{ old('code', $reportGroup->code ?? '') }}" 
           class="w-full rounded-lg border px-3 py-2 @error('code') border-rose-500 @enderror"
           placeholder="Ví dụ: mam_non, tieu_hoc..."
           required>
    @error('code')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-12">
    <label class="block text-xs text-slate-500 mb-1">Mô tả</label>
    <textarea name="description" rows="3" 
              class="w-full rounded-lg border px-3 py-2 @error('description') border-rose-500 @enderror"
              placeholder="Mô tả về nhóm báo cáo...">{{ old('description', $reportGroup->description ?? '') }}</textarea>
    @error('description')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Thứ tự sắp xếp</label>
    <input type="number" name="sort_order" value="{{ old('sort_order', $reportGroup->sort_order ?? 0) }}" 
           class="w-full rounded-lg border px-3 py-2 @error('sort_order') border-rose-500 @enderror"
           min="0">
    @error('sort_order')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
    <select name="is_active" class="w-full rounded-lg border px-3 py-2 @error('is_active') border-rose-500 @enderror">
      <option value="1" @selected(old('is_active', $reportGroup->is_active ?? true) == 1)>Hoạt động</option>
      <option value="0" @selected(old('is_active', $reportGroup->is_active ?? true) == 0)>Tạm dừng</option>
    </select>
    @error('is_active')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>
</div>
