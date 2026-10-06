@csrf
<div class="grid gap-4 md:grid-cols-12">
  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Nhóm báo cáo <span class="text-rose-600">*</span></label>
    <select name="report_group_id" class="w-full rounded-lg border px-3 py-2 @error('report_group_id') border-rose-500 @enderror" required>
      <option value="">— Chọn nhóm báo cáo —</option>
      @foreach($reportGroups as $group)
        <option value="{{ $group->id }}" @selected(old('report_group_id', $reportDetail->report_group_id ?? request('group_id')) == $group->id)>
          {{ $group->name }}
        </option>
      @endforeach
    </select>
    @error('report_group_id')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Mã báo cáo <span class="text-rose-600">*</span></label>
    <input type="text" name="code" value="{{ old('code', $reportDetail->code ?? '') }}" 
           class="w-full rounded-lg border px-3 py-2 @error('code') border-rose-500 @enderror"
           placeholder="Ví dụ: mam_non_1_1, tieu_hoc_2_1..."
           required>
    @error('code')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-12">
    <label class="block text-xs text-slate-500 mb-1">Tên báo cáo <span class="text-rose-600">*</span></label>
    <input type="text" name="name" value="{{ old('name', $reportDetail->name ?? '') }}" 
           class="w-full rounded-lg border px-3 py-2 @error('name') border-rose-500 @enderror"
           placeholder="Ví dụ: Thống kê trẻ từ 0-5 tuổi..."
           required>
    @error('name')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-12">
    <label class="block text-xs text-slate-500 mb-1">Mô tả</label>
    <textarea name="description" rows="3" 
              class="w-full rounded-lg border px-3 py-2 @error('description') border-rose-500 @enderror"
              placeholder="Mô tả về báo cáo...">{{ old('description', $reportDetail->description ?? '') }}</textarea>
    @error('description')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-12">
    <label class="block text-xs text-slate-500 mb-1">Giá trị/Dữ liệu báo cáo</label>
    <textarea name="value" rows="4" 
              class="w-full rounded-lg border px-3 py-2 @error('value') border-rose-500 @enderror"
              placeholder="Nhập dữ liệu hoặc giá trị báo cáo...">{{ old('value', $reportDetail->value ?? '') }}</textarea>
    @error('value')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Thứ tự sắp xếp</label>
    <input type="number" name="sort_order" value="{{ old('sort_order', $reportDetail->sort_order ?? 0) }}" 
           class="w-full rounded-lg border px-3 py-2 @error('sort_order') border-rose-500 @enderror"
           min="0">
    @error('sort_order')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
    <select name="is_active" class="w-full rounded-lg border px-3 py-2 @error('is_active') border-rose-500 @enderror">
      <option value="1" @selected(old('is_active', $reportDetail->is_active ?? true) == 1)>Hoạt động</option>
      <option value="0" @selected(old('is_active', $reportDetail->is_active ?? true) == 0)>Tạm dừng</option>
    </select>
    @error('is_active')
      <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
    @enderror
  </div>
</div>
