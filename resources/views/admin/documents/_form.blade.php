@csrf
<div class="grid gap-3 md:grid-cols-12">
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Số/Ký hiệu</label>
    <input type="text" name="code" value="{{ old('code',$document->code) }}" class="w-full rounded-lg border px-3 py-2">
    @error('code')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-9">
    <label class="block text-xs text-slate-500 mb-1">Tiêu đề <span class="text-rose-600">*</span></label>
    <input required type="text" name="title" value="{{ old('title',$document->title) }}" class="w-full rounded-lg border px-3 py-2">
    @error('title')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Ngày ban hành</label>
    <input type="date" name="issued_date" value="{{ old('issued_date', optional($document->issued_date)->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2">
    @error('issued_date')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-5">
    <label class="block text-xs text-slate-500 mb-1">Đơn vị cấp</label>
    <select name="issuer_id" class="w-full rounded-lg border px-3 py-2">
      <option value="">— Chọn —</option>
      @foreach($issuers as $is)
        <option value="{{ $is->id }}" @selected(old('issuer_id',$document->issuer_id)==$is->id)>{{ $is->name }}</option>
      @endforeach
    </select>
    @error('issuer_id')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-4">
    <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
    <select name="status" class="w-full rounded-lg border px-3 py-2">
      @php $st = old('status',$document->status ?? 'published'); @endphp
      <option value="draft"     @selected($st==='draft')>Nháp</option>
      <option value="published" @selected($st==='published')>Công bố</option>
      <option value="archived"  @selected($st==='archived')>Lưu trữ</option>
    </select>
    @error('status')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="md:col-span-12">
    <label class="block text-xs text-slate-500 mb-1">Tệp văn bản (PDF/Office)</label>
    <input type="file" name="file" class="w-full rounded-lg border px-3 py-2">
    @error('file')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror

    @if(!empty($document->file_path))
      <div class="mt-2 text-sm">
        Tệp hiện tại:
        <a href="{{ route('admin.documents.download', $document) }}" class="text-blue-600 hover:underline">Tải xuống</a>
      </div>
    @endif
  </div>
</div>

<div class="mt-4 flex items-center gap-2">
  <button class="btn-primary">Lưu</button>
  <a href="{{ route('admin.documents.index') }}" class="btn-soft">Huỷ</a>
</div>
