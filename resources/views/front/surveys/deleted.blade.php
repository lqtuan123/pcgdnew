@extends('layouts.front')
@section('title','Quản lý xóa tạm - Đối tượng & Phiếu điều tra')

@section('content')
<style>.th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb}.td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}</style>

<div x-data="detailUI()" x-init="boot()" 
     @view.window="openWith($event.detail.row ?? $event.detail)"
     @restore.window="restore($event.detail.id)"
     @hard-delete.window="hardDelete($event.detail.id)" class="mx-auto max-w-7xl px-4 py-6">

  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold text-red-600">Quản lý xóa tạm - Đối tượng & Phiếu điều tra</h1>
    <a href="{{ route('front.surveys.index') }}" class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">
      ← Quay lại danh sách chính
    </a>
  </div>

  <form method="get" class="mb-3 grid gap-3 md:grid-cols-12">
  @php
    $base = $defaultYear ?? \App\Models\SurveyForm::currentAcademicYear();
    [$y1,$y2] = explode('-', $base);
    $years = [($y1-2).'-'.($y1-1), ($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
  @endphp
  
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Năm học</label>
    <select name="year" class="w-full rounded-lg border px-3 py-2">
      <option value="all" @selected($year==='all')>— Tất cả —</option>
      @foreach($years as $yy)
        <option value="{{ $yy }}" @selected($yy==$year)>{{ $yy }}</option>
      @endforeach
    </select>
  </div>
   

  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Số phiếu</label>
    <input name="so_phieu" value="{{ $soPhieu }}" class="w-full rounded-lg border px-3 py-2" placeholder="VD: AC001">
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Tìm người (tên/CMND/điện thoại)</label>
    <input name="q" value="{{ $q }}" class="w-full rounded-lg border px-3 py-2">
  </div>

  {{-- Geo filter: cho chiếm trọn một hàng --}}
  <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        'namePrefix' => 'geo',
        'scheme'     => request('scheme','old'),
        'provinceId' => request('geo.province_id'),
        'districtId' => request('geo.district_id'),
        'wardId'     => request('geo.ward_id'),
        'villageId'  => request('geo.village_id'),
        'showHamlet' => true,
        'wrapperClass' => 'md:col-span-8 grid md:grid-cols-12 gap-3'
      ])
     
  </div>

  {{-- Hàng nút (gộp lại) --}}
  <div class="md:col-span-12 flex flex-wrap items-end gap-2">
    <button class="btn-primary">Lọc</button>
    <a href="{{ route('front.surveys.deleted') }}" class="btn-soft">Xoá lọc</a>
  </div>
</form>

  {{-- =============== DANH SÁCH ĐỐI TƯỢNG ĐÃ XÓA TẠM =============== --}}
  <div class="mb-4">
    <h2 class="text-lg font-semibold text-red-600 mb-3">Danh sách đối tượng điều tra đã xóa tạm</h2>
    
    {{-- Bulk Actions --}}
    <div class="mb-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <input type="checkbox" id="selectAllTargets" class="rounded border-gray-300" 
               @change="toggleAllTargets($event.target.checked)">
        <label for="selectAllTargets" class="text-sm text-gray-600">Chọn tất cả</label>
      </div>
      
      <div class="flex gap-2">
        @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit'))
          <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="selectedTargets.length === 0"
                  @click="bulkRestoreTargets()">
            Phục hồi (<span x-text="selectedTargets.length"></span>)
          </button>
          <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                  @click="restoreAllTargets()">
            Phục hồi tất cả ({{ $rows->total() }})
          </button>
        @endif
        
        @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'delete'))
          <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="selectedTargets.length === 0"
                  @click="bulkHardDeleteTargets()">
            Xóa thật (<span x-text="selectedTargets.length"></span>)
          </button>
          <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                  @click="hardDeleteAllTargets()">
            Xóa thật tất cả ({{ $rows->total() }})
          </button>
        @endif
      </div>
    </div>
  </div>

  {{-- DESKTOP TABLE --}}
  <div class="hidden md:block">
      <div class="-mx-4 overflow-x-auto">
        <table class="min-w-[1600px]">
          <thead>
            {{-- Hàng 1: tiêu đề nhóm --}}
            <tr class="bg-red-50">
              <th class="th sticky left-0 z-20 border-red-200" rowspan="2">
                <input type="checkbox" class="rounded border-gray-300" 
                       @change="toggleAllTargets($event.target.checked)">
              </th>
              <th class="th sticky left-0 z-20 border-red-200" rowspan="2">#</th>
              @php
                $i = 1;
              
              // Map màu cho từng nhóm (dùng màu đỏ nhạt cho trang deleted)
              $GROUP_BG = [ 'bg-red-50',
                'bg-orange-50',
                'bg-yellow-50',
                'bg-pink-50',
                'bg-purple-50',
                'bg-indigo-50',
                'bg-blue-50','bg-red-50', 'bg-orange-50','bg-yellow-50', 'bg-blue-50','bg-red-50', 'bg-orange-50','bg-yellow-50',
                'bg-pink-50',
                'bg-purple-50',
              ];
    
              @endphp
              @foreach($groups as $g)
              @php
                $i += 2;
              @endphp
                <th class="th text-center z-20 {{$GROUP_BG[$i]}} border-red-200" colspan="{{ count($g['keys']) }}">
                  {{ $g['title'] }}
                </th>
              @endforeach
            </tr>

            {{-- Hàng 2: tiêu đề từng cột --}}
            <tr class="bg-red-100">
              @foreach($groups as $g)
                @foreach($g['keys'] as $k)
                  <th class="th text-left">{{ $columns[$k] ?? $k }}</th>
                @endforeach
              @endforeach
            </tr>
          </thead>

          <tbody>
            @forelse($rows as $i => $r)
             
              <tr class="hover:bg-red-50">
                {{-- Checkbox --}}
                <td class="td sticky left-0 z-30 bg-white align-middle">
                  <input type="checkbox" class="rounded border-gray-300" 
                         :value="{{ $r['detail_id'] ?? 'null' }}"
                         x-model="selectedTargets">
                </td>
                
                {{-- Cột # + nút 3 chấm (sticky trái) --}}
                <td class="td sticky left-0 z-30 bg-white align-middle">
  <div class="flex items-center gap-2 min-w-[84px]">
    {{-- nút menu --}}
    <div x-data="kebabMenu()" x-init="init()" @keydown.escape.window="close()">
      <button type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100"
              @click="openMenu($event)" aria-label="Mở menu">
        ⋮
      </button>

      <template x-teleport="body">
        <div x-show="open" x-transition.opacity.duration.120ms
             @click.outside="close()" :style="style" class="fixed z-[9999]">
          <div x-ref="menu" class="w-44 rounded-xl border bg-white shadow-xl overflow-hidden">
             
            @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit'))
              <button class="w-full px-3 py-2 text-left text-green-600 hover:bg-green-50"
                      @click="close(); $dispatch('restore',{id: {{ $r['detail_id'] ?? 'null' }}})">Phục hồi</button>
            @endif
            @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'delete'))
              <button class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50"
                      @click="close(); $dispatch('hard-delete',{id: {{ $r['detail_id'] ?? 'null' }}})">Xóa thật</button>
            @endif
          </div>
        </div>
      </template>
    </div>

    {{-- STT --}}
    <span class="tabular-nums w-6 text-right">{{ $rows->firstItem() + $i }}</span>
  </div>
</td>

                {{-- Các cột dữ liệu còn lại --}}
                @foreach($groups as $g)
                  @foreach($g['keys'] as $k)
                    @php $v = $r[$k] ?? ''; @endphp

                    <td class="td">
                    @if($k == 'ho_dem' || $k === 'ten')
                    
                      <button type="button" class="text-red-700 hover:underline"
                           @click.stop.prevent="openWith({{ Js::from($r) }})">
                    @endif
                      
                        {{ is_bool($v) ? ($v ? '✓' : '') : $v }}
                     @if($k === 'ho_dem' || $k === 'ten')
                        </button>
                    @endif
                    </td>
                  @endforeach
                @endforeach
              </tr>
            @empty
              <tr><td class="td" colspan="{{ 2 + collect($groups)->sum(fn($g)=>count($g['keys'])) }}">
                <div class="text-center py-8 text-slate-500">
                  <svg class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <p class="text-lg font-medium">Không có đối tượng nào đã xóa tạm</p>
                  <p class="text-sm">Tất cả đối tượng đều đang hoạt động bình thường</p>
                </div>
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    <div class="mt-3">{{ $rows->appends(request()->except('page_surveys'))->links() }}</div>
  </div>

 {{-- =============== MOBILE LIST (<md) =============== --}}
  <div class="md:hidden">
    @forelse($rows as $i => $r)
      <div class="rounded-xl border border-red-200 p-3 mb-2 bg-red-50">
        <div class="flex items-start justify-between gap-3">
          <div>
            {{-- Họ tên -> mở popup --}}
            <button type="button" class="text-red-700 hover:underline"
                           @click.stop.prevent="openWith({{ Js::from($r) }})">
              {{ trim(($r['ho_dem'] ?? '').' '.($r['ten'] ?? '')) }}
            </button>

            <div class="mt-1 text-xs text-red-600 space-x-2">
              <span>#{{ $rows->firstItem() + $i }}</span>
              <span class="inline-block rounded bg-red-200 px-1.5 py-0.5 text-red-800">Đã xóa tạm</span>
              @if(!empty($r['so_phieu']))
                <span class="inline-block rounded bg-slate-100 px-1.5 py-0.5">Số phiếu: {{ $r['so_phieu'] }}</span>
              @endif
            </div>

            <div class="mt-2 text-sm text-slate-700">
              <div>
                <span class="text-slate-500">Sinh:</span>
                {{ trim(($r['dob_day']??'').'/'.($r['dob_month']??'').'/'.($r['dob_year']??''), '/ ') }}
                · <span class="text-slate-500">Giới:</span> {{ !empty($r['is_female']) ? 'Nữ' : 'Nam' }}
              </div>
              @if(!empty($r['school_name']) || !empty($r['education_grade_name']))
                <div>
                  <span class="text-slate-500">Học:</span>
                  {{ ($r['education_grade_name'] ?? '') ? 'Lớp '.$r['education_grade_name'].' · ' : '' }}
                  {{ $r['school_name'] ?? '' }}
                </div>
              @endif
              @if(!empty($r['addressline']))
                <div class="truncate"><span class="text-slate-500">Đ/c:</span> {{ $r['addressline'] }}</div>
              @endif
            </div>
          </div>

          {{-- Kebab hành động --}}
          <div x-data="{ open:false }" class="relative shrink-0">
              <button type="button"
                      class="h-8 w-8 rounded-lg grid place-items-center hover:bg-slate-100"
                      @click="open=!open">⋮</button>

              <div x-cloak x-show="open" @click.outside="open=false"
                  class="absolute right-0 top-full mt-1 w-40 rounded-xl border bg-white shadow-lg overflow-hidden z-10">

                <!-- Phục hồi -->
                @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit'))
                  <button type="button"
                          class="w-full px-3 py-2 text-left text-green-600 hover:bg-green-50"
                          @click="open=false; $refs.restoreForm.submit()">
                    Phục hồi
                  </button>
                @endif

                <!-- Xóa thật -->
                @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'delete'))
                  <button type="button"
                          class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50"
                          @click="open=false; $refs.hardDeleteForm.submit()">
                    Xóa thật
                  </button>
                @endif

                <!-- Form ẩn cho RESTORE -->
                <form x-ref="restoreForm" method="POST" action="/doituong-dieutra/{{ $r['detail_id'] }}/restore" class="hidden">
                  @csrf
                  <input type="hidden" name="id" value="{{ $r['detail_id'] }}">
                </form>

                <!-- Form ẩn cho HARD DELETE -->
                <form x-ref="hardDeleteForm" method="POST" action="/doituong-dieutra/{{ $r['detail_id'] }}/hard-delete" class="hidden">
                  @csrf
                  <input type="hidden" name="id" value="{{ $r['detail_id'] }}">
                </form>
              </div>
            </div>
        </div>
      </div>
    @empty
      <div class="text-center py-8 text-slate-500">
        <svg class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-lg font-medium">Không có đối tượng nào đã xóa tạm</p>
        <p class="text-sm">Tất cả đối tượng đều đang hoạt động bình thường</p>
      </div>
    @endforelse
  </div>

  {{-- =============== DANH SÁCH PHIẾU ĐÃ XÓA TẠM =============== --}}
  <div class="mt-8">
    <div class="mb-4 flex items-center justify-between py-3">
      <h2 class="text-xl font-semibold text-red-600">Danh sách phiếu điều tra đã xóa tạm</h2>
    </div>

    {{-- Bulk Actions for Surveys --}}
    <div class="mb-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <input type="checkbox" id="selectAllSurveys" class="rounded border-gray-300" 
               :checked="selectedSurveys.length === {{ $deletedSurveys->count() }} && {{ $deletedSurveys->count() }} > 0"
               @change="toggleAllSurveys($event.target.checked)">
        <label for="selectAllSurveys" class="text-sm text-gray-600">Chọn tất cả phiếu</label>
      </div>
      <div class="flex gap-2">
        @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'edit'))
          <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="selectedSurveys.length === 0"
                  @click="bulkRestoreSurveys()">
            Phục hồi phiếu (<span x-text="selectedSurveys.length"></span>)
          </button>
          <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                  @click="restoreAllSurveys()">
            Phục hồi tất cả ({{ $deletedSurveys->total() }})
          </button>
        @endif
        @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
          <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="selectedSurveys.length === 0"
                  @click="bulkHardDeleteSurveys()">
            Xóa thật phiếu (<span x-text="selectedSurveys.length"></span>)
          </button>
          <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                  @click="hardDeleteAllSurveys()">
            Xóa thật tất cả ({{ $deletedSurveys->total() }})
          </button>
        @endif
      </div>
    </div>

    {{-- DESKTOP TABLE PHIẾU --}}
    <div class="hidden md:block">
      <div class="-mx-4 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="bg-red-50">
              <th class="th sticky left-0 z-20 border-red-200">#</th>
              <th class="th text-left border-red-200">Mã phiếu</th>
              <th class="th text-left border-red-200">Chủ hộ</th>
              <th class="th text-left border-red-200">Địa chỉ cũ</th>
              <th class="th text-left border-red-200">Địa chỉ mới</th>
              <th class="th text-left border-red-200">Năm học</th>
              <th class="th text-left border-red-200">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            @forelse($deletedSurveys as $i => $survey)
              <tr class="hover:bg-red-50">
                <td class="td sticky left-0 z-30 bg-white align-middle">
                  <div class="flex items-center gap-2 min-w-[84px]">
                    {{-- checkbox --}}
                    <input type="checkbox" class="rounded border-gray-300" 
                           :value="{{ $survey->id }}" 
                           :checked="selectedSurveys.includes({{ $survey->id }})"
                           @change="toggleSurvey({{ $survey->id }}, $event.target.checked)">
                    {{-- nút menu --}}
                    <div x-data="kebabMenu()" x-init="init()" @keydown.escape.window="close()">
                      <button type="button"
                              class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100"
                              @click="openMenu($event)" aria-label="Mở menu">
                        ⋮
                      </button>

                      <template x-teleport="body">
                        <div x-show="open" x-transition.opacity.duration.120ms
                             @click.outside="close()" :style="style" class="fixed z-[9999]">
                          <div x-ref="menu" class="w-44 rounded-xl border bg-white shadow-xl overflow-hidden">
                            @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'edit'))
                              <button class="w-full px-3 py-2 text-left text-green-600 hover:bg-green-50"
                                      @click="close(); restoreSurvey({{ $survey->id }})">Phục hồi</button>
                            @endif
                            @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
                              <button class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50"
                                      @click="close(); hardDeleteSurvey({{ $survey->id }})">Xóa thật</button>
                            @endif
                          </div>
                        </div>
                      </template>
                    </div>

                    {{-- STT --}}
                    <span class="tabular-nums w-6 text-right">{{ $deletedSurveys->firstItem() + $i }}</span>
                  </div>
                </td>
                <td class="td">{{ $survey->so_phieu }}</td>
                <td class="td">{{ $survey->headPerson->full_name ?? 'N/A' }}</td>
                <td class="td">
                  @if($scheme === 'old')
                    {{ $survey->oldWard->name ?? 'N/A' }}
                  @else
                    {{ $survey->newWard->name ?? 'N/A' }}
                  @endif
                </td>
                <td class="td">{{ $survey->newWard->name ?? 'N/A' }}</td>
                <td class="td">{{ $survey->academic_year }}</td>
                <td class="td">
                  <div class="flex gap-2">
                    @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'edit'))
                      <button class="text-green-600 hover:text-green-900 text-sm" 
                              @click="restoreSurvey({{ $survey->id }})">
                        Phục hồi
                      </button>
                    @endif
                    @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
                      <button class="text-red-600 hover:text-red-900 text-sm"
                              @click="hardDeleteSurvey({{ $survey->id }})">
                        Xóa thật
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td class="td" colspan="7">
                  <div class="text-center py-8 text-slate-500">
                    <svg class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-lg font-medium">Không có phiếu nào đã xóa tạm</p>
                    <p class="text-sm">Tất cả phiếu đều đang hoạt động bình thường</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-3">{{ $deletedSurveys->appends(request()->except('page'))->links() }}</div>
    </div>

    {{-- MOBILE LIST PHIẾU --}}
    <div class="md:hidden">
      @forelse($deletedSurveys as $i => $survey)
        <div class="rounded-xl border border-red-200 p-3 mb-2 bg-red-50">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
              {{-- checkbox --}}
              <input type="checkbox" class="rounded border-gray-300 mt-1" 
                     :value="{{ $survey->id }}" 
                     :checked="selectedSurveys.includes({{ $survey->id }})"
                     @change="toggleSurvey({{ $survey->id }}, $event.target.checked)">
              <div>
              <div class="font-medium text-red-700">{{ $survey->so_phieu }}</div>
              <div class="text-sm text-slate-700 mt-1">
                <div><span class="text-slate-500">Chủ hộ:</span> {{ $survey->headPerson->full_name ?? 'N/A' }}</div>
                <div><span class="text-slate-500">Năm học:</span> {{ $survey->academic_year }}</div>
                @if($scheme === 'old')
                  <div><span class="text-slate-500">Địa chỉ cũ:</span> {{ $survey->oldWard->name ?? 'N/A' }}</div>
                @endif
                <div><span class="text-slate-500">Địa chỉ mới:</span> {{ $survey->newWard->name ?? 'N/A' }}</div>
              </div>
            </div>

            {{-- Kebab hành động --}}
            <div x-data="{ open:false }" class="relative shrink-0">
              <button type="button"
                      class="h-8 w-8 rounded-lg grid place-items-center hover:bg-slate-100"
                      @click="open=!open">⋮</button>

              <div x-cloak x-show="open" @click.outside="open=false"
                  class="absolute right-0 top-full mt-1 w-40 rounded-xl border bg-white shadow-lg overflow-hidden z-10">

                @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'edit'))
                  <button type="button"
                          class="w-full px-3 py-2 text-left text-green-600 hover:bg-green-50"
                          @click="open=false; restoreSurvey({{ $survey->id }})">
                    Phục hồi
                  </button>
                @endif

                @if(auth()->user()->hasPermission('quan_ly_phieu_dieu_tra', 'delete'))
                  <button type="button"
                          class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50"
                          @click="open=false; hardDeleteSurvey({{ $survey->id }})">
                    Xóa thật
                  </button>
                @endif
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="text-center py-8 text-slate-500">
          <svg class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="text-lg font-medium">Không có phiếu nào đã xóa tạm</p>
          <p class="text-sm">Tất cả phiếu đều đang hoạt động bình thường</p>
        </div>
      @endforelse
    </div>
  </div>

  {{-- =============== MODAL CHI TIẾT (dùng chung) =============== --}}
  <template x-if="open">
    <div class="fixed inset-0 z-[60] bg-black/40 grid place-items-center" @keydown.escape.window="close()">
      <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl"
           @click.outside="close()">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="text-lg font-semibold text-red-600" x-text="fullName(item)"></div>
            <div class="text-xs text-red-500 mt-0.5">
              <span class="inline-block rounded bg-red-200 px-2 py-1 text-red-800">Đã xóa tạm</span>
            </div>
            <div class="text-xs text-slate-500 mt-0.5">
              <span x-show="item.so_phieu">Số phiếu: <b x-text="item.so_phieu"></b></span>
            </div>
            <div class="text-xs text-slate-500 mt-0.5 space-x-4">
              <span x-show="hasOld(item)">
                Địa bàn (cũ): <b x-text="locOld(item)"></b>
              </span>
              <span x-show="hasNew(item)">
                Địa bàn (mới): <b x-text="locNew(item)"></b>
              </span>
            </div>
          </div>
          <button class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100" @click="close()">✕</button>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
          {{-- Thông tin cá nhân --}}
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Thông tin cá nhân</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Ngày sinh</dt>
              <dd class="col-span-2" x-text="dob(item)"></dd>

              <dt class="text-slate-500">Giới tính</dt>
              <dd class="col-span-2" x-text="item.is_female ? 'Nữ' : 'Nam'"></dd>

              <dt class="text-slate-500">Dân tộc</dt>
              <dd class="col-span-2" x-text="item.ethnicity || '—'"></dd>

              <dt class="text-slate-500">Tôn giáo</dt>
              <dd class="col-span-2" x-text="item.religion || '—'"></dd>

              <dt class="text-slate-500">Diện ưu tiên</dt>
              <dd class="col-span-2" x-text="item.priority_name || '—'"></dd>
              <dt class="text-slate-500">Chủ hộ</dt>
              <dd class="col-span-2" x-text="item.chuho_ho_dem + ' ' + item.chuho_ten"></dd>
              <dt class="text-slate-500">Địa chỉ</dt>
              <dd class="col-span-2" x-text="item.addressline || '—'"></dd>
              <dt class="text-slate-500">Diện cư trú</dt>
              <dd class="col-span-2" x-text="item.residence_type_name|| '—'  "></dd>
              <dt class="text-slate-500">Tình trạng cư trú</dt>
              <dd class="col-span-2" x-text="item.residence_status_name|| '—'  "></dd>
            </dl>
          </div>

          {{-- Học tập --}}
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Học tập</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Khối/Lớp</dt>
              <dd class="col-span-2"
                  x-text="[(item.education_block_code||''), (item.education_grade_name?('Lớp '+item.education_grade_name):'')].filter(Boolean).join(' · ') || '—'"></dd>

              <dt class="text-slate-500">Trường</dt>
              <dd class="col-span-2"
                  x-text="[(item.school_name||''), (item.school_code||'')].filter(Boolean).join(' · ') || '—'"></dd>

              <dt class="text-slate-500">TN PT</dt>
              <dd class="col-span-2"
                  x-text="[(item.graduation_level_name||''),(item.graduation_year||'')].filter(Boolean).join(' · ') || '—'"></dd>
              <dt class="text-slate-500">Bổ túc</dt>
              <dd class="col-span-2" x-text="item.is_bo_tuc ? 'Có' : 'Không'"></dd>

              <dt class="text-slate-500">TN nghề</dt>
              <dd class="col-span-2"
                  x-text="[(item.vocational_level_name||''),(item.vocational_grad_year||''),(item.vocational_grad_cert_no||'')].filter(Boolean).join(' · ') || '—'"></dd>
            </dl>
          </div>
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Bỏ học</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Học xong lớp</dt>
              <dd class="col-span-2"
                  x-text="item.hoc_xong_lop || '—'"></dd>

              <dt class="text-slate-500">Học xong năm</dt>
              <dd class="col-span-2"
                  x-text="item.hoc_xong_nam || '—'"></dd>
              
              <dt class="text-slate-500">Bỏ học lớp</dt>
              <dd class="col-span-2"
                  x-text="item.bo_hoc_lop || '—'"></dd>

              <dt class="text-slate-500">Bỏ học năm</dt>
              <dd class="col-span-2"
                  x-text="item.bo_hoc_nam || '—'"></dd>
              </dl>
          </div>
          {{-- Mù chữ --}}
          <div class="rounded-xl border p-3">
            <div class="font-medium mb-2">Mù chữ</div>
            <dl class="grid grid-cols-3 gap-y-1 text-sm">
              <dt class="text-slate-500">Đang học lớp</dt>
              <dd class="col-span-2"
                  x-text="item.lit_dang_hoc_lop || '—'"></dd>

              <dt class="text-slate-500">Hoàn thành lớp</dt>
              <dd class="col-span-2"
                  x-text="item.lit_hoan_thanh_lop || '—'"></dd>
              
             

              <dt class="text-slate-500">Tái mù mức</dt>
              <dd class="col-span-2"
                  x-text="item.lit_tai_mu_muc || '—'"></dd>
              </dl>
          </div>

          {{-- Hoàn cảnh & Liên lạc --}}
          <div class="rounded-xl border p-3 md:col-span-2">
            <div class="font-medium mb-2">Hoàn cảnh & Liên lạc</div>
            <dl class="grid grid-cols-4 gap-y-1 text-sm">
              <dt class="text-slate-500">Hoàn cảnh đặc biệt</dt>
              <dd class="col-span-3" x-text="[(item.sc_name||''),(item.sc_detail||'')].filter(Boolean).join(' · ') || '—'"></dd>

              <dt class="text-slate-500">Phụ huynh</dt>
              <dd class="col-span-3" x-text="item.contact_name || '—'"></dd>

              <dt class="text-slate-500">Điện thoại</dt>
              <dd class="col-span-3" x-text="item.contact_phone || '—'"></dd>

              <dt class="text-slate-500">Ghi chú</dt>
              <dd class="col-span-3" x-text="item.contact_note || '—'"></dd>
            </dl>
          </div>

          {{-- Khuyết tật --}}
          <div class="rounded-xl border p-3 md:col-span-2">
            <div class="font-medium mb-2">Khuyết tật</div>
            <div class="text-sm text-slate-700 grid grid-cols-2 md:grid-cols-4 gap-1">
              <template x-for="k in disabilityKeys" :key="k.key">
                <div>
                  <span class="text-slate-500" x-text="k.label"></span>: 
                  <b x-text="item[k.key] ? '✓' : ''"></b>
                </div>
              </template>
              <div><span class="text-slate-500">Có chứng nhận</span>: <b x-text="item.dis_has_certificate?'✓':''"></b></div>
              <div><span class="text-slate-500">Có thể học</span>: <b x-text="item.dis_can_learn?'✓':''"></b></div>
            </div>
          </div>
        </div>

        <div class="mt-5 flex items-center justify-end gap-2">
          @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit'))
            <button class="btn-primary bg-green-600 hover:bg-green-700" @click="restore(item.detail_id)">Phục hồi</button>
          @endif
          @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'delete'))
            <button class="btn-primary bg-red-600 hover:bg-red-700" @click="hardDelete(item.detail_id)">Xóa thật</button>
          @endif
          <button class="btn-primary" @click="close()">Đóng</button>
        </div>
      </div>
    </div>
  </template>
</div>
 
@endsection
@push('scripts')
<script>

  function kebabMenu(){
    return {
      open:false, style:'left:0;top:0', anchor:null,

      openMenu(ev){
        this.anchor = ev.currentTarget;
        this.open = true;
        this.$nextTick(() => this.place());
      },
      close(){ this.open = false; },

      place(){
        if (!this.anchor) return;

        const r = this.anchor.getBoundingClientRect();
        const menu = this.$refs.menu;
        // đo kích thước thực (fallback nếu menu chưa render xong)
        const w = (menu?.offsetWidth  ?? 176);
        const h = (menu?.offsetHeight ?? 160);
        const gap = 8;

        // ❌ KHÔNG cộng window.scrollX/scrollY vì là fixed
        let left = r.right + gap;
        let top  = r.top;

        const vw = window.innerWidth, vh = window.innerHeight;

        // lật sang trái nếu tràn phải
        if (left + w > vw - 8) left = r.left - w - gap;
        // chặn tràn dưới
        if (top + h > vh - 8)   top  = Math.max(8, vh - h - 8);
        // chặn tràn trên
        if (top < 8) top = 8;

        this.style = `left:${left}px; top:${top}px`;
      },

      init(){
        this._onScroll = () => { if (this.open) this.place(); };
        this._onResize = () => { if (this.open) this.place(); };
        // capture=true để bắt scroll của mọi container (overflow)
        window.addEventListener('scroll', this._onScroll, true);
        window.addEventListener('resize', this._onResize);
      },
      destroy(){
        window.removeEventListener('scroll', this._onScroll, true);
        window.removeEventListener('resize', this._onResize);
      }
    }
  }
</script>
<script>
function detailUI(){
  return {
    open:false,
    item:null,
    selectedTargets: [],
    selectedSurveys: [],
     // Có dữ liệu địa bàn cũ/mới?
    hasOld(r){ return !!(r?.old_province_name || r?.old_district_name || r?.old_ward_name || r?.village_name); },
    hasNew(r){ return !!(r?.new_province_name || r?.new_ward_name || r?.village_name_new); },

    // Chuỗi địa bàn cũ: Tỉnh · Huyện · Xã · Thôn
    locOld(r){
      if(!r) return '';
      const parts = [
        r.old_province_name,
        r.old_district_name,
        r.old_ward_name,
        r.village_name,
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    // Chuỗi địa bàn mới: Tỉnh · Xã · (Thôn nếu có)
    locNew(r){
      if(!r) return '';
      const parts = [
        r.new_province_name,
        r.new_ward_name,
        r.village_name_new
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    disabilityKeys: [
      {key:'dis_VAN_DONG', label:'Vận động'},
      {key:'dis_NGHE_NOI', label:'Nghe/Nói'},
      {key:'dis_NHIN',     label:'Nhìn'},
      {key:'dis_THAN_KINH',label:'Thần kinh'},
      {key:'dis_TRI_TUE',  label:'Trí tuệ'},
      {key:'dis_HOC_TAP',  label:'Học tập'},
      {key:'dis_TU_KY',    label:'Tự kỷ'},
      {key:'dis_KHAC',     label:'Khác'},
    ],
    boot(){},
    openWith(r){ this.item = r; this.open = true; },
    close(){ this.open = false; this.item = null; },
    fullName(r){ if(!r) return ''; return [r.ho_dem||'', r.ten||''].join(' ').trim(); },
    dob(r){
      if(!r) return '';
      const dd = [r.dob_day, r.dob_month, r.dob_year].filter(Boolean).join('/');
      return dd || '—';
    },

    // Hàm phục hồi đối tượng
    restore(id) {
      // console.log('restore called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID đối tượng: ' + id);
        return;
      }

      const message = 'Bạn có muốn phục hồi đối tượng này?\nĐối tượng sẽ xuất hiện lại trong danh sách chính.';
      
      if (confirm(message)) {
        // console.log('User confirmed, sending request to:', `/doituong-dieutra/${id}/restore`);
        
        // Gửi request phục hồi
        fetch(`/doituong-dieutra/${id}/restore`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          // console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          // console.log('Response data:', data);
          if (data.success) {
            alert('Đã phục hồi đối tượng thành công');
            // Reload trang để cập nhật danh sách
            window.location.reload();
          } else {
            // Kiểm tra nếu là lỗi phiếu bị xóa tạm
            if (data.survey_code) {
              alert('⚠️ ' + data.message + '\n\n' +
                    'Để phục hồi đối tượng:\n' +
                    '1. Phục hồi phiếu điều tra trước\n' +
                    '2. Sau khi phục hồi phiếu, hệ thống sẽ tự động phục hồi các đối tượng thuộc phiếu điều tra đó.');
            } else {
              alert('Lỗi: ' + (data.message || 'Không thể phục hồi đối tượng'));
            }
          }
        })
        .catch(error => {
          // console.error('Error:', error);
          alert('Lỗi kết nối: Không thể phục hồi đối tượng');
        });
      }
    },

    // Hàm xóa thật đối tượng
    hardDelete(id) {
      // console.log('hardDelete called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID đối tượng: ' + id);
        return;
      }

      const message = '⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT đối tượng này?\n\n' +
                     'Hành động này sẽ xóa vĩnh viễn đối tượng khỏi database và KHÔNG THỂ PHỤC HỒI!';
      
      if (confirm(message)) {
        // console.log('User confirmed hard delete, sending request to:', `/doituong-dieutra/${id}/hard-delete`);
        
        // Gửi request xóa thật
        fetch(`/doituong-dieutra/${id}/hard-delete`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          // console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          // console.log('Response data:', data);
          if (data.success) {
            alert('Đã xóa thật đối tượng thành công');
            // Reload trang để cập nhật danh sách
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa thật đối tượng'));
          }
        })
        .catch(error => {
          // console.error('Error:', error);
          alert('Lỗi kết nối: Không thể xóa thật đối tượng');
        });
      }
    },

    // Hàm phục hồi phiếu điều tra
    restoreSurvey(id) {
      // console.log('restoreSurvey called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID phiếu điều tra: ' + id);
        return;
      }

      const message = 'Bạn có muốn phục hồi phiếu điều tra này?\nPhiếu sẽ xuất hiện lại trong danh sách chính cùng với tất cả đối tượng liên quan.';
      
      if (confirm(message)) {
        // console.log('User confirmed, sending request to:', `/doituong-dieutra/survey/${id}/restore`);
        
        // Gửi request phục hồi
        fetch(`/doituong-dieutra/survey/${id}/restore`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          // console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          // console.log('Response data:', data);
          if (data.success) {
            alert('Đã phục hồi phiếu điều tra thành công');
            // Reload trang để cập nhật danh sách
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể phục hồi phiếu điều tra'));
          }
        })
        .catch(error => {
          // console.error('Error:', error);
          alert('Lỗi kết nối: Không thể phục hồi phiếu điều tra');
        });
      }
    },

    // Hàm xóa thật phiếu điều tra
    hardDeleteSurvey(id) {
      // console.log('hardDeleteSurvey called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID phiếu điều tra: ' + id);
        return;
      }

      const message = '⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT phiếu điều tra này?\n\n' +
                     'Hành động này sẽ xóa vĩnh viễn:\n' +
                     '- Phiếu điều tra\n' +
                     '- Tất cả đối tượng trong phiếu\n' +
                     '- Thông tin người dân liên quan\n\n' +
                     'Hành động này KHÔNG THỂ PHỤC HỒI!';
      
      if (confirm(message)) {
        // console.log('User confirmed hard delete, sending request to:', `/doituong-dieutra/survey/${id}/hard-delete`);
        
        // Gửi request xóa thật
        fetch(`/doituong-dieutra/survey/${id}/hard-delete`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          // console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          // console.log('Response data:', data);
          if (data.success) {
            alert('Đã xóa thật phiếu điều tra thành công');
            // Reload trang để cập nhật danh sách
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa thật phiếu điều tra'));
          }
        })
        .catch(error => {
          // console.error('Error:', error);
          alert('Lỗi kết nối: Không thể xóa thật phiếu điều tra');
        });
      }
    },

    // Bulk operations cho đối tượng
    toggleAllTargets(checked) {
      if (checked) {
        // Lấy tất cả checkbox values từ DOM
        const checkboxes = document.querySelectorAll('input[type="checkbox"][x-model="selectedTargets"]');
        this.selectedTargets = Array.from(checkboxes).map(cb => cb.value).filter(v => v !== 'null');
      } else {
        this.selectedTargets = [];
      }
    },

    bulkRestoreTargets() {
      if (this.selectedTargets.length === 0) {
        alert('Vui lòng chọn ít nhất một đối tượng để phục hồi');
        return;
      }

      const message = `Bạn có muốn phục hồi ${this.selectedTargets.length} đối tượng đã chọn?\nCác đối tượng sẽ xuất hiện lại trong danh sách chính.`;
      
      if (confirm(message)) {
        fetch('/doituong-dieutra/bulk-restore', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ ids: this.selectedTargets })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            this.selectedTargets = [];
            // Redirect về trang đầu tiên
            const url = new URL(window.location);
            url.searchParams.delete('page');
            url.searchParams.delete('page_surveys');
            window.location.href = url.toString();
          } else {
            // Hiển thị thông báo chi tiết về phiếu bị xóa tạm
            let message = data.message || 'Không thể phục hồi các đối tượng';
            if (data.deleted_surveys && data.deleted_surveys.length > 0) {
              message += '\n\nCác phiếu cần phục hồi trước: ' + data.deleted_surveys.join(', ');
            }
            alert(message);
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể phục hồi các đối tượng');
        });
      }
    },

    bulkHardDeleteTargets() {
      if (this.selectedTargets.length === 0) {
        alert('Vui lòng chọn ít nhất một đối tượng để xóa thật');
        return;
      }

      const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT ${this.selectedTargets.length} đối tượng đã chọn?\n\n` +
                     'Hành động này sẽ xóa vĩnh viễn các đối tượng khỏi database và KHÔNG THỂ PHỤC HỒI!';
      
      if (confirm(message)) {
        fetch('/doituong-dieutra/bulk-hard-delete-targets', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ ids: this.selectedTargets })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            this.selectedTargets = [];
            // Redirect về trang đầu tiên
            const url = new URL(window.location);
            url.searchParams.delete('page');
            url.searchParams.delete('page_surveys');
            window.location.href = url.toString();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa thật các đối tượng'));
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể xóa thật các đối tượng');
        });
      }
    },

    // Bulk operations for surveys
    toggleAllSurveys(checked) {
      if (checked) {
        this.selectedSurveys = @json($deletedSurveys->pluck('id')->toArray());
      } else {
        this.selectedSurveys = [];
      }
    },

    toggleSurvey(id, checked) {
      if (checked) {
        if (!this.selectedSurveys.includes(id)) {
          this.selectedSurveys.push(id);
        }
      } else {
        this.selectedSurveys = this.selectedSurveys.filter(item => item !== id);
      }
    },

    bulkRestoreSurveys() {
      if (this.selectedSurveys.length === 0) {
        alert('Vui lòng chọn ít nhất một phiếu để phục hồi');
        return;
      }

      const message = `Bạn có muốn phục hồi ${this.selectedSurveys.length} phiếu điều tra đã chọn?\nCác phiếu và đối tượng liên quan sẽ xuất hiện lại trong danh sách chính.`;
      if (confirm(message)) {
        fetch('/doituong-dieutra/bulk-restore-surveys', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ ids: this.selectedSurveys })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            this.selectedSurveys = [];
            // Redirect về trang đầu tiên
            const url = new URL(window.location);
            url.searchParams.delete('page');
            url.searchParams.delete('page_surveys');
            window.location.href = url.toString();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể phục hồi các phiếu'));
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể phục hồi các phiếu');
        });
      }
    },

    bulkHardDeleteSurveys() {
      if (this.selectedSurveys.length === 0) {
        alert('Vui lòng chọn ít nhất một phiếu để xóa thật');
        return;
      }

      const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT ${this.selectedSurveys.length} phiếu điều tra đã chọn?\n\n` +
                     'Hành động này sẽ xóa vĩnh viễn các phiếu và tất cả đối tượng liên quan khỏi database và KHÔNG THỂ PHỤC HỒI!';
      
      if (confirm(message)) {
        fetch('/doituong-dieutra/bulk-hard-delete-surveys', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ ids: this.selectedSurveys })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            this.selectedSurveys = [];
            // Redirect về trang đầu tiên
            const url = new URL(window.location);
            url.searchParams.delete('page');
            url.searchParams.delete('page_surveys');
            window.location.href = url.toString();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa thật các phiếu'));
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể xóa thật các phiếu');
        });
      }
    },

    // Phục hồi tất cả đối tượng theo bộ lọc
    restoreAllTargets() {
      const total = {{ $rows->total() }};
      if (total === 0) {
        alert('Không có đối tượng nào để phục hồi');
        return;
      }

      const message = `Bạn có muốn phục hồi TẤT CẢ ${total} đối tượng theo bộ lọc hiện tại?\n\n` +
                     'Các đối tượng sẽ xuất hiện lại trong danh sách chính.';
      
      if (confirm(message)) {
        // Lấy tất cả query params hiện tại
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
        fetch('/doituong-dieutra/restore-all-targets?' + params.toString(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            window.location.reload();
          } else {
            let message = data.message || 'Không thể phục hồi các đối tượng';
            if (data.deleted_surveys && data.deleted_surveys.length > 0) {
              message += '\n\nCác phiếu cần phục hồi trước: ' + data.deleted_surveys.join(', ');
            }
            alert(message);
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể phục hồi các đối tượng');
        });
      }
    },

    // Xóa thật tất cả đối tượng theo bộ lọc
    hardDeleteAllTargets() {
      const total = {{ $rows->total() }};
      if (total === 0) {
        alert('Không có đối tượng nào để xóa thật');
        return;
      }

      const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT TẤT CẢ ${total} đối tượng theo bộ lọc hiện tại?\n\n` +
                     'Hành động này sẽ xóa vĩnh viễn các đối tượng khỏi database và KHÔNG THỂ PHỤC HỒI!';
      
      if (confirm(message)) {
        // Lấy tất cả query params hiện tại
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
        fetch('/doituong-dieutra/hard-delete-all-targets?' + params.toString(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa thật các đối tượng'));
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể xóa thật các đối tượng');
        });
      }
    },

    // Phục hồi tất cả phiếu theo bộ lọc
    restoreAllSurveys() {
      const total = {{ $deletedSurveys->total() }};
      if (total === 0) {
        alert('Không có phiếu nào để phục hồi');
        return;
      }

      const message = `Bạn có muốn phục hồi TẤT CẢ ${total} phiếu điều tra theo bộ lọc hiện tại?\n\n` +
                     'Các phiếu và đối tượng liên quan sẽ xuất hiện lại trong danh sách chính.';
      
      if (confirm(message)) {
        // Lấy tất cả query params hiện tại
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
        fetch('/doituong-dieutra/restore-all-surveys?' + params.toString(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể phục hồi các phiếu'));
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể phục hồi các phiếu');
        });
      }
    },

    // Xóa thật tất cả phiếu theo bộ lọc
    hardDeleteAllSurveys() {
      const total = {{ $deletedSurveys->total() }};
      if (total === 0) {
        alert('Không có phiếu nào để xóa thật');
        return;
      }

      const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT TẤT CẢ ${total} phiếu điều tra theo bộ lọc hiện tại?\n\n` +
                     'Hành động này sẽ xóa vĩnh viễn:\n' +
                     '- Tất cả các phiếu\n' +
                     '- Tất cả đối tượng trong các phiếu\n' +
                     '- Thông tin người dân liên quan\n\n' +
                     'Hành động này KHÔNG THỂ PHỤC HỒI!';
      
      if (confirm(message)) {
        // Lấy tất cả query params hiện tại
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
        fetch('/doituong-dieutra/hard-delete-all-surveys?' + params.toString(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa thật các phiếu'));
          }
        })
        .catch(error => {
          alert('Lỗi kết nối: Không thể xóa thật các phiếu');
        });
      }
    }
  }
}
</script>
@endpush
