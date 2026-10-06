@extends('layouts.admin')
@section('title','Chi tiết trường')
@section('crumb','Trường học')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <a href="{{ route('admin.schools.index') }}" class="btn-soft">← Quay lại</a>
    <div class="flex items-center gap-2">
      <a href="{{ route('admin.school-areas.index', $school) }}" class="btn-primary">
        <i class="fas fa-map-marker-alt mr-1"></i>Khu vực tuyển sinh
      </a>
      <a href="{{ route('admin.schools.edit',$school) }}" class="btn-soft">Sửa</a>
       
    </div>
  </div>

  <article class="card">
    <h2 class="text-lg font-semibold mb-3">Thông tin trường</h2>
    <div class="grid md:grid-cols-2 gap-4 text-sm">
      <div><div class="text-slate-500">Mã</div><div class="font-medium">{{ $school->code }}</div></div>
      <div><div class="text-slate-500">Tên</div><div class="font-medium">{{ $school->name }}</div></div>
      <div><div class="text-slate-500">Loại hình</div><div>{{ optional($school->schoolType)->name ?: '—' }}</div></div>
      <div><div class="text-slate-500">Bậc học</div><div>{{ optional($school->educationalLevel)->name ?: '—' }}</div></div>
      <div><div class="text-slate-500">Phường/Xã (cũ)</div><div>{{ $school->ward_code ?: '—' }}</div></div>
      <div><div class="text-slate-500">Quận/Huyện (cũ)</div><div>{{ $school->district_code ?: '—' }}</div></div>
    </div>
  </article>

  <article class="card">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Giáo viên</h2>
      @if($teachers instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="text-sm text-slate-500">Tổng: {{ $teachers->total() }}</div>
      @endif
    </div>

    @if($teachers instanceof \Illuminate\Support\Collection && $teachers->isEmpty())
      <div class="p-4 text-sm text-slate-500">Chưa có dữ liệu giáo viên.</div>
    @else
      <div class="-mx-4 mt-3 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="th text-left">#</th>
              <th class="th text-left">Họ tên</th>
              <th class="th text-left">Bộ môn</th>
              <th class="th text-left">Email</th>
              <th class="th text-left">Điện thoại</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            @foreach($teachers as $i => $t)
              <tr class="hover:bg-slate-50">
                <td class="td">{{ method_exists($teachers,'firstItem') ? $teachers->firstItem() + $i : $i+1 }}</td>
                <td class="td">{{ $t->name ?? '—' }}</td>
                <td class="td">{{ $t->subject ?? '—' }}</td>
                <td class="td">{{ $t->email ?? '—' }}</td>
                <td class="td">{{ $t->phone ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if(method_exists($teachers,'links'))
        <div class="mt-3">{{ $teachers->links() }}</div>
      @endif
    @endif
  </article>
</div>
@endsection
