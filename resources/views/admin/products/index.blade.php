@extends('layouts.admin')

@section('title','Sản phẩm')
@section('crumb','Sản phẩm')

@section('content')
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Danh sách sản phẩm</h1>
    <a href="#" class="btn">+ Thêm sản phẩm</a>
  </div>

  <div class="mt-6 overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-slate-900 dark:border-slate-800">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
      <thead class="bg-slate-50 dark:bg-slate-800/50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">SKU</th>
          <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Tên</th>
          <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Giá</th>
          <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Tồn</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
        @foreach(range(1,8) as $i)
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
            <td class="px-4 py-3 text-sm font-mono">TP-50{{ $i }}</td>
            <td class="px-4 py-3 text-sm">Sản phẩm demo #{{ $i }}</td>
            <td class="px-4 py-3 text-sm">₫ {{ number_format(990000 + $i*10000,0,',','.') }}</td>
            <td class="px-4 py-3 text-sm">{{ 100 - $i }}</td>
            <td class="px-4 py-3 text-right">
              <a href="#" class="text-sm text-blue-600 hover:underline">Sửa</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
