@extends('layouts.admin')
@section('title','Chi tiết văn bản')
@section('crumb','Quản lý / Văn bản / Chi tiết')

@section('content')
<article class="card space-y-3">
  <div><span class="text-slate-500 text-xs">Số/Ký hiệu:</span> <span class="font-medium">{{ $document->code ?: '—' }}</span></div>
  <div><span class="text-slate-500 text-xs">Tiêu đề:</span> <span class="font-medium">{{ $document->title }}</span></div>
  <div><span class="text-slate-500 text-xs">Ngày ban hành:</span> <span class="font-medium">{{ optional($document->issued_date)->format('d/m/Y') ?: '—' }}</span></div>
  <div><span class="text-slate-500 text-xs">Đơn vị cấp:</span> <span class="font-medium">{{ optional($document->issuer)->name ?: '—' }}</span></div>
  <div><span class="text-slate-500 text-xs">Trạng thái:</span> <span class="font-medium">{{ $document->status }}</span></div>
  <div>
    @if($document->file_path)
      <a href="{{ route('admin.documents.download', $document) }}" class="btn-soft">Tải tệp</a>
    @else
      <span class="text-slate-400 text-sm">Chưa đính kèm tệp</span>
    @endif
  </div>
  <div>
    <a href="{{ route('admin.documents.edit', $document) }}" class="text-amber-600 hover:underline">Sửa</a>
  </div>
</article>
@endsection
