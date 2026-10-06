{{-- Popup Tìm kiếm nâng cao đối tượng điều tra --}}
<div x-show="openAdvancedFilter"
     x-cloak
     class="fixed inset-0 z-[80] overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4"
     @click.self="openAdvancedFilter = false"
     @keydown.escape.window="openAdvancedFilter = false">
  
  <div class="relative w-full max-w-3xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-800"
       x-data="{ activeTab: 'personal' }">
    
    {{-- Header --}}
    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/80">
      <div class="flex items-center gap-2">
        <div class="p-1.5 rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
          </svg>
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Tìm kiếm nâng cao đối tượng điều tra</h3>
      </div>
      <button type="button"
              @click="openAdvancedFilter = false"
              class="h-8 w-8 rounded-lg grid place-items-center text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition-colors">
        ✕
      </button>
    </div>

    {{-- Tabs Header --}}
    <div class="flex border-b border-slate-200 dark:border-slate-700 bg-slate-100/60 dark:bg-slate-800/50 px-5 pt-2 gap-1.5 text-xs font-semibold">
      <button type="button"
              @click="activeTab = 'personal'"
              :class="activeTab === 'personal' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-white dark:bg-slate-900 border-b-2 font-bold shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
              class="px-4 py-2.5 rounded-t-xl transition-all flex items-center gap-1.5">
        1. Địa bàn & Cá nhân
      </button>
      <button type="button"
              @click="activeTab = 'education'"
              :class="activeTab === 'education' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-white dark:bg-slate-900 border-b-2 font-bold shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
              class="px-4 py-2.5 rounded-t-xl transition-all flex items-center gap-1.5">
        2. Học tập & Tốt nghiệp
      </button>
      <button type="button"
              @click="activeTab = 'special'"
              :class="activeTab === 'special' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-white dark:bg-slate-900 border-b-2 font-bold shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
              class="px-4 py-2.5 rounded-t-xl transition-all flex items-center gap-1.5">
        3. Khuyết tật, CMC & Khác
      </button>
    </div>

    {{-- Form body --}}
    <form method="GET" action="{{ route('front.surveys.index') }}" class="flex-1 overflow-y-auto p-5 max-h-[65vh]">
      
      {{-- Tab 1: Địa bàn & Cá nhân --}}
      <div x-show="activeTab === 'personal'" class="space-y-5">
        
        {{-- Section 1.1: Địa bàn & Phiếu --}}
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-3.5">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 border-b border-indigo-100 dark:border-indigo-900/40 pb-1.5 block">Địa bàn & Thông tin phiếu</span>
          
          <div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-4">
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm học</label>
              @php
                $base = $defaultYear ?? \App\Models\SurveyForm::currentAcademicYear();
                [$y1,$y2] = explode('-', $base);
                $years = [($y1-2).'-'.($y1-1), ($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
              @endphp
              <select name="year" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="all" @selected(($year ?? '')==='all')>— Tất cả —</option>
                @foreach($years as $yy)
                  <option value="{{ $yy }}" @selected($yy==($year ?? ''))>{{ $yy }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Số phiếu</label>
              <input name="so_phieu" value="{{ $soPhieu ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: AC001">
            </div>
            <div class="md:col-span-6">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Từ khóa (Tên/CMND/SĐT)</label>
              <input name="q" value="{{ $q ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="Nhập tên, số CMND...">
            </div>
            
            <div class="md:col-span-12 pt-1">
              @include('partials.front.geo-filter', [
                'scheme'      => request('scheme', 'old'),
                'schemeMode'  => 'new',
                'showHamlet'  => true,
                'schoolWardParam' => 'ward_id',
                'schoolUseCode'   => false,
                'showSchool'  => false,
                'enableSchool'=> false,
                'wrapperClass'=> 'grid md:grid-cols-12 gap-x-4 gap-y-4'
              ])
            </div>
          </div>
        </div>

        {{-- Section 1.2: Cá nhân & Chủ hộ --}}
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-3.5">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 border-b border-indigo-100 dark:border-indigo-900/40 pb-1.5 block">Thông tin đối tượng & Chủ hộ</span>
          
          <div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-4">
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Họ đệm đối tượng</label>
              <input name="ho_dem" value="{{ $hoDem ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="Nguyễn Văn">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tên đối tượng</label>
              <input name="ten" value="{{ $ten ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="An">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Chủ hộ - Họ đệm</label>
              <input name="chuho_ho_dem" value="{{ $chuhoHoDem ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="Trần Văn">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Chủ hộ - Tên</label>
              <input name="chuho_ten" value="{{ $chuhoTen ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="Bằng">
            </div>

            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Số CCCD</label>
              <input name="identity_number" value="{{ $identityNumber ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="040092xxxxxx">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Giới tính</label>
              <select name="is_female" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="" @selected(($isFemale ?? '') === null || ($isFemale ?? '') === '')>— Tất cả —</option>
                <option value="1" @selected(($isFemale ?? '') === '1')>Nữ</option>
                <option value="0" @selected(($isFemale ?? '') === '0')>Nam</option>
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Dân tộc</label>
              <select name="ethnicity_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Tất cả dân tộc —</option>
                @foreach($ethnicities ?? [] as $eth)
                  <option value="{{ $eth->id }}" @selected(($ethnicityId ?? '') == $eth->id)>{{ $eth->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Tôn giáo</label>
              <select name="religion_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Tất cả tôn giáo —</option>
                @foreach($religions ?? [] as $rel)
                  <option value="{{ $rel->id }}" @selected(($religionId ?? '') == $rel->id)>{{ $rel->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Độ tuổi từ</label>
              <input type="number" min="0" max="100" name="min_age" value="{{ $minAge ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 5">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Độ tuổi đến</label>
              <input type="number" min="0" max="100" name="max_age" value="{{ $maxAge ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 18">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm sinh từ</label>
              <input type="number" min="1900" max="2099" name="min_birth_year" value="{{ $minBirthYear ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 2005">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm sinh đến</label>
              <input type="number" min="1900" max="2099" name="max_birth_year" value="{{ $maxBirthYear ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 2018">
            </div>
          </div>
        </div>

      </div>

      {{-- Tab 2: Học tập & Tốt nghiệp --}}
      <div x-show="activeTab === 'education'" class="space-y-5">
        
        {{-- Section 2.1: Học tập & Trường lớp --}}
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-3.5">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 border-b border-indigo-100 dark:border-indigo-900/40 pb-1.5 block">Trường lớp & Quá trình học</span>
          
          <div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-4">
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Bậc / Khối học</label>
              <select name="education_block_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Tất cả các khối —</option>
                @foreach($blocks ?? [] as $b)
                  @php
                    $bLabel = $b->name;
                    if (in_array($b->name, ['1','2','3','4','5','6','7','8','9','10','11','12'])) {
                      $bLabel = "Lớp {$b->name}";
                    } elseif (str_starts_with($b->name, 'TDN')) {
                      $bLabel = "Dạy nghề ({$b->name})";
                    } elseif (str_starts_with($b->name, 'THCN')) {
                      $bLabel = "Trung cấp CN ({$b->name})";
                    } elseif (str_starts_with($b->name, 'SV')) {
                      $bLabel = "Sinh viên ({$b->name})";
                    } elseif (str_contains($b->name, 'tháng') || str_contains($b->name, 'tuổi')) {
                      $bLabel = "Mầm non ({$b->name})";
                    }
                  @endphp
                  <option value="{{ $b->id }}" @selected(($educationBlockId ?? '') == $b->id)>{{ $bLabel }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-5">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Trường học (Mã hoặc Tên)</label>
              <input name="school" value="{{ $school ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="Tên hoặc mã trường học">
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Lớp học</label>
              <input name="class_label" value="{{ $classLabel ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 1A, 5B">
            </div>

            <div class="md:col-span-12 flex flex-wrap gap-6 pt-2">
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="is_repeat_grade" value="1" @checked(($isRepeatGrade ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Lưu ban</span>
              </label>
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="hoc_hai_buoi" value="1" @checked(($hocHaiBuoi ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Học 2 ca / 2 buổi</span>
              </label>
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="trai_tuyen" value="1" @checked(($traiTuyen ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Học trái tuyến</span>
              </label>
            </div>
          </div>
        </div>

        {{-- Section 2.2: Tốt nghiệp PT & Nghề --}}
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-3.5">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 border-b border-indigo-100 dark:border-indigo-900/40 pb-1.5 block">Tốt nghiệp Phổ thông & Tốt nghiệp Nghề</span>
          
          <div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-4">
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Bậc tốt nghiệp Phổ thông</label>
              <select name="graduation_level_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Chọn bậc tốt nghiệp —</option>
                @foreach($gradLevels ?? [] as $gl)
                  <option value="{{ $gl->id }}" @selected(($graduationLevelId ?? '') == $gl->id)>{{ $gl->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm TN Phổ thông</label>
              <input type="number" min="1950" max="2099" name="graduation_year" value="{{ $graduationYear ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 2023">
            </div>
            <div class="md:col-span-4 flex items-end pb-1.5">
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="is_bo_tuc" value="1" @checked(($isBoTuc ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Hệ Bổ túc</span>
              </label>
            </div>

            <div class="md:col-span-6">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Bậc tốt nghiệp Nghề</label>
              <select name="vocational_level_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Chọn bậc TN nghề —</option>
                @foreach($vocLevels ?? [] as $vl)
                  <option value="{{ $vl->id }}" @selected(($vocationalLevelId ?? '') == $vl->id)>{{ $vl->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="md:col-span-6">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm TN Nghề</label>
              <input type="number" min="1950" max="2099" name="vocational_grad_year" value="{{ $vocationalGradYear ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 2024">
            </div>
          </div>
        </div>

      </div>

      {{-- Tab 3: Khuyết tật, CMC & Khác --}}
      <div x-show="activeTab === 'special'" class="space-y-5">
        
        {{-- Section 3.1: Bỏ học, Học xong & CMC --}}
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-3.5">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 border-b border-indigo-100 dark:border-indigo-900/40 pb-1.5 block">Bỏ học, Học xong & Xóa mù chữ</span>
          
          <div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-4">
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Bỏ học từ lớp</label>
              <input type="number" min="1" max="12" name="bo_hoc_tu_lop" value="{{ $boHocTuLop ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 1">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Bỏ học đến lớp</label>
              <input type="number" min="1" max="12" name="bo_hoc_den_lop" value="{{ $boHocDenLop ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 9">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm bỏ học</label>
              <input type="number" min="1990" max="2099" name="bo_hoc_nam" value="{{ $boHocNam ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 2022">
            </div>

            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Học xong từ lớp</label>
              <input type="number" min="1" max="12" name="hoc_xong_tu_lop" value="{{ $hocXongTuLop ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 5">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Học xong đến lớp</label>
              <input type="number" min="1" max="12" name="hoc_xong_den_lop" value="{{ $hocXongDenLop ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 9">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Năm học xong</label>
              <input type="number" min="1990" max="2099" name="hoc_xong_nam" value="{{ $hocXongNam ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: 2023">
            </div>

            <div class="md:col-span-6">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Đang học lớp Xóa mù chữ</label>
              <input name="lit_dang_hoc_lop" value="{{ $litDangHocLop ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: Lớp 1 CMC">
            </div>
            <div class="md:col-span-6">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Hoàn thành lớp Xóa mù chữ</label>
              <input name="lit_hoan_thanh_lop" value="{{ $litHoanThanhLop ?? '' }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all" placeholder="VD: Kỳ 2 CMC">
            </div>
          </div>
        </div>

        {{-- Section 3.2: Khuyết tật & Hoàn cảnh đặc biệt --}}
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-3.5">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 border-b border-indigo-100 dark:border-indigo-900/40 pb-1.5 block">Khuyết tật & Hoàn cảnh đặc biệt</span>
          
          <div class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-4">
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Khuyết tật</label>
              <select name="is_disabled" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Tất cả —</option>
                <option value="1" @selected(($isDisabled ?? '') === '1')>Có khuyết tật</option>
              </select>
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Loại khuyết tật cụ thể</label>
              <select name="dis_type" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Tất cả loại —</option>
                <option value="VAN_DONG" @selected(($disType ?? '') === 'VAN_DONG')>Vận động</option>
                <option value="NGHE_NOI" @selected(($disType ?? '') === 'NGHE_NOI')>Nghe / Nói</option>
                <option value="NHIN" @selected(($disType ?? '') === 'NHIN')>Nhìn</option>
                <option value="THAN_KINH" @selected(($disType ?? '') === 'THAN_KINH')>Thần kinh</option>
                <option value="TRI_TUE" @selected(($disType ?? '') === 'TRI_TUE')>Trí tuệ</option>
                <option value="HOC_TAP" @selected(($disType ?? '') === 'HOC_TAP')>Học tập</option>
                <option value="TU_KY" @selected(($disType ?? '') === 'TU_KY')>Tự kỷ</option>
                <option value="KHAC" @selected(($disType ?? '') === 'KHAC')>Khác</option>
              </select>
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Hoàn cảnh đặc biệt</label>
              <select name="sc_id" class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                <option value="">— Chọn hoàn cảnh —</option>
                @foreach($specials ?? [] as $sc)
                  <option value="{{ $sc->id }}" @selected(($scId ?? '') == $sc->id)>{{ $sc->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="md:col-span-12 flex flex-wrap gap-6 pt-2">
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="can_learn" value="1" @checked(($canLearn ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Có khả năng học tập</span>
              </label>
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="has_certificate" value="1" @checked(($hasCertificate ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Có chứng nhận khuyết tật</span>
              </label>
              <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="has_note" value="1" @checked(($hasNote ?? '') === '1') class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                <span>Có ghi chú thêm</span>
              </label>
            </div>
          </div>
        </div>

      </div>

      {{-- Footer buttons inside form --}}
      <div class="px-3 py-3 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 bg-slate-50/90 dark:bg-slate-800/90 backdrop-blur-sm sticky bottom-0 z-10 mt-4 rounded-b-2xl">
        <a href="{{ route('front.surveys.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-sm">
          Xóa bộ lọc
        </a>
        
        <div class="flex items-center gap-2">
          <button type="button"
                  @click="openAdvancedFilter = false"
                  class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 text-xs font-semibold transition-colors shadow-sm">
            Đóng
          </button>
          <button type="submit"
                  class="px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md hover:shadow-lg transition-all flex items-center gap-1.5">
            Tìm kiếm ngay
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
