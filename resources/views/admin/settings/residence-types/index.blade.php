@extends('layouts.admin')
@section('title','Diện cư trú')
@section('crumb','Cài đặt / Diện cư trú')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="space-y-6" x-data="residenceTypeIdx">
<div class="flex items-center gap-3">
<a href="{{ route('admin.settings.residence-types.create') }}" class="btn-primary">+ Thêm diện cư trú</a>
<div class="ml-auto"></div>
</div>

<article class="card">
<form method="GET" action="{{ route('admin.settings.residence-types.index') }}" class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-6">
<label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
<input type="text" name="q" value="{{ request('q') }}" placeholder="Tên diện cư trú…"
class="w-full rounded-lg border px-3 py-2">
</div>
<div class="md:col-span-3 flex items-end gap-2">
<button class="btn-primary">Lọc</button>
<a href="{{ route('admin.settings.residence-types.index') }}" class="btn-soft">Xoá lọc</a>
</div>
</form>
</article>

<article class="card">
<div class="-mx-4 mt-3 overflow-x-auto">
<table class="min-w-full divide-y divide-slate-200">
<thead class="bg-slate-50">
<tr>
<th class="th text-left">#</th>
<th class="th text-left">Tên diện cư trú</th>
<th class="th text-left">Ngày tạo</th>
<th class="th text-right">Thao tác</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200">
@forelse($residenceTypes as $i => $rt)
<tr class="hover:bg-slate-50">
<td class="td">{{ $residenceTypes->firstItem() + $i }}</td>
<td class="td">{{ $rt->name }}</td>
<td class="td">{{ $rt->created_at->format('d/m/Y H:i') }}</td>
<td class="td text-right">
<a href="{{ route('admin.settings.residence-types.edit',$rt) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
<button type="button" class="text-rose-600 hover:underline"
@click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.residence-types.destroy',$rt) }}', name: @js($rt->name)})">
Xoá
</button>
</td>
</tr>
@empty
<tr><td class="td" colspan="4">Không có dữ liệu.</td></tr>
@endforelse
</tbody>
</table>
</div>

<div class="mt-3">{{ $residenceTypes->withQueryString()->links() }}</div>
</article>
</div>

{{-- Modal confirm xoá --}}
<div x-data="{ open:false, action:'', name:'' }"
@confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name">
<div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
<div class="absolute inset-0 bg-black/40" @click="open=false"></div>
<div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
<h3 class="font-semibold text-lg">Xoá diện cư trú?</h3>
<p class="mt-2 text-sm text-slate-600">Bạn chắc chắn muốn xoá <span class="font-medium" x-text="name"></span>?</p>
<form :action="action" method="POST" class="mt-4 flex justify-end gap-2">
@csrf @method('DELETE')
<button type="button" class="btn-soft" @click="open=false">Huỷ</button>
<button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
</form>
</div>
</div>
</div>
@endsection

@push('scripts')
<script>document.addEventListener('alpine:init',()=>{ Alpine.data('residenceTypeIdx',()=>({})) })</script>
@endpush
