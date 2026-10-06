@extends('layouts.admin')
@section('title','Sửa chức năng - ' . $function->name)
@section('crumb','Cài đặt / Hướng dẫn / ' . $category->name . ' / Sửa')

@section('content')
<article class="card">
    <form method="POST" action="{{ route('admin.settings.guides.functions.update', [$category->id, $function->id]) }}">
        @csrf
        @method('PUT')
        <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-12">
                <label class="block text-xs text-slate-500 mb-1">Tên chức năng <span class="text-rose-600">*</span></label>
                <input type="text" name="name" value="{{ old('name', $function->name) }}" 
                       class="w-full rounded-lg border px-3 py-2" 
                       placeholder="Nhập tên chức năng" required>
                @error('name')
                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mt-4 flex items-center gap-2">
            <button class="btn-primary">Cập nhật</button>
            <a href="{{ route('admin.settings.guides.functions.index', $category->id) }}" class="btn-soft">Hủy</a>
        </div>
    </form>
</article>
@endsection
