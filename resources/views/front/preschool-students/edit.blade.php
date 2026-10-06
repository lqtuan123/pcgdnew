@extends('layouts.front')
@section('title','Chỉnh sửa học sinh mầm non')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
  <!-- Hiển thị thông báo -->
  @if(session('success'))
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
      {{ session('success') }}
    </div>
  @endif

  @if(session('error'))
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      {{ session('error') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="mb-6">
    <h1 class="text-2xl font-semibold mb-2">Chỉnh sửa học sinh mầm non</h1>
    <div class="text-sm text-gray-600">
      <strong>Học sinh:</strong> {{ $student->person->full_name }}<br>
      <strong>Khối học:</strong> {{ $student->personEducation->block->name ?? '—' }}<br>
      <strong>Số phiếu:</strong> {{ $student->so_phieu }}<br>
      <strong>Năm học:</strong> {{ $student->academic_year }}
    </div>
  </div>

  <form method="POST" action="{{ route('front.preschool-students.update', $student->id) }}" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-medium mb-4">Thông tin mầm non</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Học bán trú -->
        <div class="flex items-center">
          <input type="checkbox" name="hoc_ban_tru" id="hoc_ban_tru" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->hoc_ban_tru ? 'checked' : '' }}>
          <label for="hoc_ban_tru" class="ml-2 text-sm font-medium text-gray-700">
            Học bán trú
          </label>
        </div>

        <!-- Học đủ ngày theo quy định -->
        <div class="flex items-center">
          <input type="checkbox" name="hoc_du_ngay_theo_quy_dinh" id="hoc_du_ngay_theo_quy_dinh" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->hoc_du_ngay_theo_quy_dinh ? 'checked' : '' }}>
          <label for="hoc_du_ngay_theo_quy_dinh" class="ml-2 text-sm font-medium text-gray-700">
            Học đủ ngày theo quy định
          </label>
        </div>

        <!-- Chuyên cần -->
        <div class="flex items-center">
          <input type="checkbox" name="chuyen_can" id="chuyen_can" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->chuyen_can ? 'checked' : '' }}>
          <label for="chuyen_can" class="ml-2 text-sm font-medium text-gray-700">
            Chuyên cần
          </label>
        </div>

        <!-- Theo dõi BĐ cân nặng -->
        <div class="flex items-center">
          <input type="checkbox" name="theo_doi_bd_can_nang" id="theo_doi_bd_can_nang" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->theo_doi_bd_can_nang ? 'checked' : '' }}>
          <label for="theo_doi_bd_can_nang" class="ml-2 text-sm font-medium text-gray-700">
            Trẻ được theo dõi BĐ cân nặng
          </label>
        </div>

        <!-- SDD nhẹ cân -->
        <div class="flex items-center">
          <input type="checkbox" name="sdd_nhe_can" id="sdd_nhe_can" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->sdd_nhe_can ? 'checked' : '' }}>
          <label for="sdd_nhe_can" class="ml-2 text-sm font-medium text-gray-700">
            SDD nhẹ cân
          </label>
        </div>

        <!-- Theo dõi BĐ chiều cao -->
        <div class="flex items-center">
          <input type="checkbox" name="theo_doi_bd_chieu_cao" id="theo_doi_bd_chieu_cao" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->theo_doi_bd_chieu_cao ? 'checked' : '' }}>
          <label for="theo_doi_bd_chieu_cao" class="ml-2 text-sm font-medium text-gray-700">
            Trẻ được theo dõi BĐ chiều cao
          </label>
        </div>

        <!-- SDD thấp còi -->
        <div class="flex items-center">
          <input type="checkbox" name="sdd_thap_coi" id="sdd_thap_coi" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->sdd_thap_coi ? 'checked' : '' }}>
          <label for="sdd_thap_coi" class="ml-2 text-sm font-medium text-gray-700">
            SDD thấp còi
          </label>
        </div>

        <!-- Dân tộc chuẩn bị TV -->
        <div class="flex items-center">
          <input type="checkbox" name="dan_toc_chuan_bi_tv" id="dan_toc_chuan_bi_tv" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->dan_toc_chuan_bi_tv ? 'checked' : '' }}>
          <label for="dan_toc_chuan_bi_tv" class="ml-2 text-sm font-medium text-gray-700">
            Trẻ dân tộc được chuẩn bị TV
          </label>
        </div>

        <!-- Hoàn thành chương trình GDMN -->
        <div class="flex items-center">
          <input type="checkbox" name="hoan_thanh_chuong_trinh_gdmn" id="hoan_thanh_chuong_trinh_gdmn" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->hoan_thanh_chuong_trinh_gdmn ? 'checked' : '' }}>
          <label for="hoan_thanh_chuong_trinh_gdmn" class="ml-2 text-sm font-medium text-gray-700">
            Hoàn thành chương trình GDMN
          </label>
        </div>

        <!-- Học 2 buổi -->
        <div class="flex items-center">
          <input type="checkbox" name="hoc_2_buoi" id="hoc_2_buoi" value="1" 
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                 {{ $student->personMamnon->hoc_2_buoi ? 'checked' : '' }}>
          <label for="hoc_2_buoi" class="ml-2 text-sm font-medium text-gray-700">
            Học 2 buổi
          </label>
        </div>
      </div>
    </div>

    <!-- Thông tin liên hệ -->
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-medium mb-4">Thông tin liên hệ</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Họ tên Cha/Mẹ -->
        <div>
          <label for="contact_name" class="block text-sm font-medium text-gray-700 mb-2">
            Họ tên Cha/Mẹ
          </label>
          <input type="text" name="contact_name" id="contact_name" 
                 value="{{ $student->person->contact_name ?? '' }}"
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Số điện thoại -->
        <div>
          <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
            Số điện thoại
          </label>
          <input type="text" name="phone" id="phone" 
                 value="{{ $student->person->phone ?? '' }}"
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 placeholder="Nhập số điện thoại">
        </div>

        <!-- Địa chỉ -->
        <div class="md:col-span-2">
          <label for="addressline" class="block text-sm font-medium text-gray-700 mb-2">
            Địa chỉ
          </label>
          <textarea name="addressline" id="addressline" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Nhập địa chỉ chi tiết">{{ $student->person->addressline ?? '' }}</textarea>
        </div>
      </div>
    </div>

    <!-- Thông tin học tập -->
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-medium mb-4">Thông tin học tập</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Khối học -->
        <div>
          <label for="education_block_id" class="block text-sm font-medium text-gray-700 mb-2">
            Khối học
          </label>
          <select name="education_block_id" id="education_block_id" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">-- Chọn khối học --</option>
            @foreach(\App\Models\EducationBlock::all() as $block)
              <option value="{{ $block->id }}" 
                      @selected($student->education && $student->education->education_block_id == $block->id)>
                {{ $block->name }}
              </option>
            @endforeach
          </select>
        </div>

        <!-- Lớp học -->
        <div>
          <label for="class_label" class="block text-sm font-medium text-gray-700 mb-2">
            Lớp học
          </label>
          <input type="text" name="class_label" id="class_label" 
                 value="{{ $student->education->class_label ?? '' }}"
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 placeholder="Nhập tên lớp học">
        </div>

        <!-- Mã trường -->
        <div>
          <label for="school_code" class="block text-sm font-medium text-gray-700 mb-2">
            Mã trường
          </label>
          <input type="text" name="school_code" id="school_code" 
                 value="{{ $student->education && $student->education->school ? $student->education->school->code : '' }}"
                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 placeholder="Nhập mã trường...">
        </div>
      </div>
    </div>

    <!-- Nút hành động -->
    <div class="flex items-center justify-end space-x-4">
      <a href="{{ route('front.preschool-students.index') }}" 
         class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        Hủy
      </a>
      <button type="submit" 
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        Cập nhật
      </button>
    </div>
  </form>
</div>

@endsection
