@extends('layouts.admin')

@section('title','Import danh mục trường')
@section('crumb','Import trường')

@section('content')
  <div class="card max-w-2xl">
    <h2 class="text-lg font-semibold">Import từ Excel</h2>
    <p class="text-sm text-slate-500 mt-1">
      File có các cột: <em>Mã trường, Tên trường, Loại hình trường, Bậc học,
      Tên phường xã, Tên quận huyện, Mã phường xã, Mã quận huyện</em>.
      (Địa phận đều thuộc <b>scheme=old</b>; mã cũ sẽ được tự động thêm tiền tố <code>cu-</code> nếu thiếu.)
    </p>

    <form method="POST" action="{{ route('admin.schools.import.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
      @csrf
      <input type="file" name="file" class="block w-full rounded border p-2" required>
      @error('file') <div class="text-rose-600 text-sm">{{ $message }}</div> @enderror

      <button class="btn-primary">Tải lên & Import</button>
    </form>

    @if(session('import_stats'))
      @php($s = session('import_stats'))
      <div class="mt-6 rounded-lg border bg-slate-50 p-4">
        <div class="font-medium mb-2">Kết quả:</div>
        <ul class="text-sm leading-6">
          <li>Tổng dòng: <b>{{ $s['total'] }}</b></li>
          <li>Tạo mới: <b class="text-emerald-600">{{ $s['created'] }}</b></li>
          <li>Cập nhật: <b class="text-blue-600">{{ $s['updated'] }}</b></li>
          <li>Lỗi: <b class="text-rose-600">{{ count($s['errors']) }}</b></li>
        </ul>
        @if(!empty($s['errors']))
          <details class="mt-2">
            <summary class="cursor-pointer text-sm text-slate-600">Chi tiết lỗi</summary>
            <ul class="mt-2 text-sm text-rose-600 space-y-1">
              @foreach($s['errors'] as $e)
                <li>• {{ $e }}</li>
              @endforeach
            </ul>
          </details>
        @endif
      </div>
    @endif
  </div>
@endsection
