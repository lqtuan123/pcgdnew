<div class="mb-4 flex items-center justify-between py-3">
  <h1 class="text-xl font-semibold">Danh sách đối tượng</h1>
  <div class="flex gap-2">
    @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'create'))
      <a href="{{ route('front.surveys.import') }}" class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-slate-700">Nhập Excel</a>
    @endif
    <button type="button" 
      @click="openAdvancedFilter = true"
      class="relative rounded-lg bg-indigo-600 px-3 py-2 text-white hover:bg-indigo-700 transition-colors flex items-center gap-1.5 shadow-sm font-medium">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
      Tìm kiếm nâng cao
      @if(($activeFilterCount ?? 0) > 0)
        <span class="ml-1 px-1.5 py-0.5 text-xs font-bold bg-amber-400 text-slate-900 rounded-full">
          {{ $activeFilterCount }}
        </span>
      @endif
    </button>
    {{-- @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'create'))
      <a href="{{ route('front.surveys.create') }}" class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700">Thêm phiếu mới</a>
    @endif --}}
    @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'edit'))
      <button 
        @click="updateGraduationLevels($event)" 
        class="rounded-lg bg-purple-600 px-3 py-2 text-white hover:bg-purple-700 transition-colors"
        title="Tự động cập nhật bậc tốt nghiệp dựa vào thông tin học tập">
        🎓 Cập nhật bậc TN
      </button>
      <button 
        @click="updateNineTenSessions($event)" 
        class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700 transition-colors"
        title="Cập nhật học 9-10 b/tuần cho khối 1, trẻ 6 tuổi">
        ⏰ Cập nhật 9-10 b/t
      </button>
    @endif
    @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view'))
      <a href="{{ $exportUrl }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">Xuất Excel</a>
    @endif
  </div>
</div>
