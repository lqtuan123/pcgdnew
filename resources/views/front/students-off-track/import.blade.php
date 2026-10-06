@extends('layouts.front')
@section('title','Nhập học sinh trái tuyến từ Excel')
@section('crumb','Nhập Excel')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="excelImportUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">

  <!-- Overlay đọc file -->
  <div x-show="loading" 
       x-cloak 
       @click.stop
       @click.self=""
       class="fixed inset-0 z-[60] grid place-items-center bg-black/30 pointer-events-auto">
    <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-xl pointer-events-none">
      <svg class="h-6 w-6 animate-spin text-cyan-600" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
      </svg>
      <span class="text-sm text-slate-700">Đang đọc file Excel...</span>
    </div>
  </div>

  <!-- Overlay lưu dữ liệu -->
  <div x-show="saving" 
       x-cloak 
       @click.stop
       @click.self=""
       class="fixed inset-0 z-[60] grid place-items-center bg-black/30 pointer-events-auto">
    <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-xl pointer-events-none">
      <svg class="h-6 w-6 animate-spin text-emerald-600" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
      </svg>
      <span class="text-sm text-slate-700">Đang lưu dữ liệu vào hệ thống...</span>
    </div>
  </div>

  <!-- Giới thiệu -->
  <div class="card">
    <h1 class="text-lg font-semibold">Đang chọn File Excel. Vui lòng chọn đơn vị hành chính</h1>
    <div class="mt-3 flex gap-2">
      <button @click="fileType = 'old'" 
              :class="fileType === 'old' ? 'bg-emerald-600' : 'bg-gray-600'"
              :disabled="loading || saving"
              @click.stop
              class="rounded-lg px-3 py-2 text-white disabled:bg-gray-400 disabled:cursor-not-allowed">File Excel cũ</button>
      <button @click="fileType = 'new'" 
              :class="fileType === 'new' ? 'bg-emerald-600' : 'bg-gray-600'"
              :disabled="loading || saving"
              @click.stop
              class="rounded-lg px-3 py-2 text-white disabled:bg-gray-400 disabled:cursor-not-allowed">File Excel mới</button>
    </div>
  </div>

  <!-- Bộ chọn tham số -->
  <div class="mt-3 card grid gap-4 md:grid-cols-12">
    <!-- Năm học -->
    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select x-model="academicYear" 
              :disabled="loading || saving"
              class="w-full rounded-lg border px-3 py-2 disabled:bg-gray-100 disabled:cursor-not-allowed">
        <template x-for="yy in yearOptions" :key="yy">
          <option :value="yy" :selected="yy === '{{ date("Y") . "-" . (date("Y") + 1) }}'" x-text="yy"></option>
        </template>
      </select>
    </div>

    <!-- File -->
    <div class="md:col-span-9">
      <label class="block text-xs text-slate-500 mb-1">Chọn tệp Excel</label>
      <input type="file" 
             @change="handleFileSelect($event)" 
             accept=".xlsx,.xls" 
             :disabled="loading || saving"
             class="w-full rounded-lg border px-3 py-2 disabled:bg-gray-100 disabled:cursor-not-allowed">
      <p class="text-xs text-slate-500 mt-1">Hỗ trợ .xlsx/.xls. Dung lượng ≤ 20MB.</p>
    </div>

    <!-- Địa bàn -->
    <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        'scheme'      => 'new',
        'schemeMode'  => 'new',      // Khóa ở hành chính mới
        'showHamlet'  => true,
        'schoolWardParam' => 'ward_id',
        'schoolUseCode'   => false,
        // (tùy chọn) trường:
        'showSchool'  => true,
        'enableSchool'=> request('enable_school', true),
        'schoolName'  => 'school_id',
        'schoolId'    => request('school_id'),
        'schoolDataUrl' => route('front.schools.datapicker'),
        'wrapperClass'=> 'md:col-span-8 grid md:grid-cols-12 gap-3'
      ])
    </div>
  </div>

  <!-- Preview data -->
  <div x-show="_previewData.length > 0" class="mt-3 card">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-medium">Dữ liệu preview (<span x-text="_previewData.length"></span> dòng)</h3>
      <div class="text-xs text-gray-500">
        Năm học: <span x-text="academicYear"></span> | 
        Tỉnh: <span x-text="document.querySelector('input[name=\'geo[province_id]\']')?.value || 'Chưa chọn'"></span> | 
        Xã: <span x-text="document.querySelector('input[name=\'geo[ward_id]\']')?.value || 'Chưa chọn'"></span>
      </div>
      <div class="flex gap-2">
        <button @click="saveData()" 
                :disabled="_previewData.length === 0 || saving"
                @click.stop
                class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
          <span x-show="!saving">Lưu vào hệ thống</span>
          <span x-show="saving" class="flex items-center gap-2">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            Đang lưu...
          </span>
        </button>
        <button @click="resetData()" 
                x-show="_previewData.length > 0"
                :disabled="loading || saving"
                @click.stop
                class="rounded-lg bg-gray-600 px-3 py-2 text-white hover:bg-gray-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
          Reset
        </button>
        <button @click="downloadErrorXLSX()" 
                x-show="rowErrors.length > 0"
                :disabled="loading || saving"
                @click.stop
                class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
          Tải file lỗi
        </button>
      </div>
    </div>

    <!-- Kết quả xử lý -->
    <div x-show="summary.total > 0" class="mb-3 card">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold">
          <span class="text-slate-700">Kết quả:</span>
          <span class="text-slate-600">Tổng: <span class="font-bold text-blue-600" x-text="summary.total"></span> dòng</span>
          <span class="text-slate-500">·</span>
          <span class="text-green-600 font-bold">Cập nhật thành công: <span x-text="summary.ok"></span></span>
          <span class="text-slate-500">·</span>
          <span class="text-red-600 font-bold">Lỗi <span x-text="summary.fail"></span></span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" @click="downloadErrorXLSX()" 
                  :disabled="!errorsAll.length || loading || saving"
                  @click.stop
                  class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
            Tải danh sách lỗi (.xlsx)
          </button>
        </div>
      </div>
    </div>

    <!-- Error display table -->
    <template x-if="errorsAll.length">
      <div class="mt-3">
        <h4 class="text-sm font-semibold text-red-600 mb-2">Chi tiết lỗi:</h4>
        <div class="overflow-x-auto max-h-80 overflow-y-auto border border-gray-200 rounded-lg">
        <table class="min-w-full border text-sm">
            <thead class="sticky top-0 bg-white z-10">
              <tr class="bg-red-50">
                <th class="border px-2 py-1 text-left">#</th>
                <th class="border px-2 py-1 text-left">Dòng Excel</th>
                <th class="border px-2 py-1 text-left">Họ tên</th>
                <th class="border px-2 py-1 text-left">Lỗi</th>
            </tr>
          </thead>
          <tbody>
              <template x-for="(e,idx) in errorsAll" :key="idx">
                <tr class="hover:bg-red-25">
                  <td class="border px-2 py-1" x-text="idx+1"></td>
                  <td class="border px-2 py-1 font-medium" x-text="e.excel_row ?? ''"></td>
                  <td class="border px-2 py-1">
                    <span x-text="e.ho_ten || ''"></span>
                  </td>
                  <td class="border px-2 py-1 text-red-600">
                  <span x-text="Array.isArray(e.messages) ? e.messages.join('; ') : e.messages"></span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        </div>
        <p class="mt-2 text-xs text-slate-500">
          Tổng cộng <span class="font-semibold text-red-600" x-text="errorsAll.length"></span> lỗi. Nhấn "Tải danh sách lỗi (.xlsx)" để tải file Excel chi tiết.
        </p>
      </div>
    </template>

    <!-- Data table với giới hạn chiều cao -->
    <div class="overflow-x-auto max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
      <table class="w-full border-collapse text-sm">
        <thead class="sticky top-0 bg-white z-10">
          <!-- Dòng tiêu đề nhóm -->
          <tr class="text-xs font-semibold">
            <th colspan="11" class="th bg-blue-50 text-blue-800 border-b-2 border-blue-200 text-center">Thông tin đối tượng</th>
            <th colspan="3" class="th bg-green-50 text-green-800 border-b-2 border-green-200 text-center">Thông tin nơi đang ở</th>
            <th colspan="10" class="th bg-purple-50 text-purple-800 border-b-2 border-purple-200 text-center">Thông tin học tập</th>
            <th colspan="10" class="th bg-orange-50 text-orange-800 border-b-2 border-orange-200 text-center">Khuyết tật</th>
            <th colspan="2" class="th bg-pink-50 text-pink-800 border-b-2 border-pink-200 text-center">Hoàn cảnh</th>
          </tr>
          <!-- Dòng tiêu đề cột -->
          <tr class="bg-slate-100">
            <th class="th">TT</th>
            <th class="th">Họ đệm</th>
            <th class="th">Tên</th>
            <th class="th">Ngày</th>
            <th class="th">Tháng</th>
            <th class="th">Năm sinh</th>
            <th class="th">Số định danh cá nhân</th>
            <th class="th">Nữ</th>
            <th class="th">Dân tộc</th>
            <th class="th">Tôn giáo</th>
            <th class="th">Diện ưu tiên</th>
            <th class="th">Tỉnh/Thành</th>
            <th class="th">Quận/Huyện</th>
            <th class="th">Phường/Xã</th>
            <th class="th">Khối học</th>
            <th class="th">Lớp học</th>
            <th class="th">Học 2 buổi/ngày</th>
            <th class="th">Học đầy đủ theo quy định</th>
            <th class="th">Mã trường</th>
            <th class="th">Bậc tốt nghiệp</th>
            <th class="th">Bổ túc</th>
            <th class="th">Năm tốt nghiệp</th>
            <th class="th">Bỏ học lớp</th>
            <th class="th">Bỏ học năm</th>
            <th class="th">Khuyết tật vận động</th>
            <th class="th">Khuyết tật nghe, nói</th>
            <th class="th">Khuyết tật nhìn</th>
            <th class="th">Khuyết tật thần kinh, tâm thần</th>
            <th class="th">Khuyết tật trí tuệ</th>
            <th class="th">Khuyết tật học tập</th>
            <th class="th">Tự kỷ</th>
            <th class="th">Khuyết tật khác</th>
            <th class="th">Có chứng nhận khuyết tật</th>
            <th class="th">Khả năng học tập</th>
            <th class="th">Hoàn cảnh đặc biệt</th>
            <th class="th">Chi tiết hoàn cảnh đặc biệt</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(row, index) in _previewData" :key="index">
            <tr :class="{'bg-red-50': row.hasError}">
              <td class="td" x-text="index + 1"></td>
              <td class="td" x-text="row.ho_dem || ''"></td>
              <td class="td" x-text="row.ten || ''"></td>
              <td class="td" x-text="row.dob_day || ''"></td>
              <td class="td" x-text="row.dob_month || ''"></td>
              <td class="td" x-text="row.dob_year || ''"></td>
              <td class="td" x-text="row.identity_number || ''"></td>
              <td class="td" x-text="row.is_female ? 'x' : ''"></td>
              <td class="td" x-text="row.ethnicity || ''"></td>
              <td class="td" x-text="row.religion || ''"></td>
              <td class="td" x-text="row.priority_name || ''"></td>
              <td class="td" x-text="row.province_name || ''"></td>
              <td class="td" x-text="row.district_name || ''"></td>
              <td class="td" x-text="row.ward_name || ''"></td>
              <td class="td" x-text="row.education_block_name || ''"></td>
              <td class="td" x-text="row.education_grade_name || ''"></td>
              <td class="td" x-text="row.hoc_hai_buoi ? 'v' : ''"></td>
              <td class="td" x-text="row.hoc_9_10_buoi_tuan ? 'v' : ''"></td>
              <td class="td" x-text="row.school_code || ''"></td>
              <td class="td" x-text="row.graduation_level_name || ''"></td>
              <td class="td" x-text="row.is_bo_tuc ? 'x' : ''"></td>
              <td class="td" x-text="row.graduation_year || ''"></td>
              <td class="td" x-text="row.bo_hoc_lop || ''"></td>
              <td class="td" x-text="row.bo_hoc_nam || ''"></td>
              <td class="td" x-text="row.dis_VAN_DONG ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_NGHE_NOI ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_NHIN ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_THAN_KINH ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_TRI_TUE ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_HOC_TAP ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_TU_KY ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_KHAC ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_has_certificate ? 'x' : ''"></td>
              <td class="td" x-text="row.dis_can_learn ? 'x' : ''"></td>
              <td class="td" x-text="row.special_circumstance_name || ''"></td>
              <td class="td" x-text="row.special_circumstance_detail || ''"></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function excelImportUI() {
  return {
    loading: false,
    saving: false,
    academicYear: '{{ date("Y") . "-" . (date("Y") + 1) }}',
    _fileType: 'new', // Mặc định chọn File Excel mới
    scheme: 'new', // Luôn dùng hành chính mới
    _previewData: [],
    rowErrors: [],
    messageErrors: [],
    showErrors: false,
    summary: { total: 0, ok: 0, fail: 0 },
    errorsAll: [],
    
    
    yearOptions: [
      @php
        $currentYear = date('Y');
        $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
        $years = [];
        // Tạo danh sách 5 năm: 3 năm cũ + năm hiện tại + 1 năm tiếp theo
        for ($i = -3; $i <= 1; $i++) {
          $year = $currentYear + $i;
          $years[] = $year . '-' . ($year + 1);
        }
        // Sắp xếp từ mới đến cũ
        rsort($years);
        echo "'" . implode("', '", $years) . "'";
      @endphp
    ],

    boot() {
      
      // Kiểm tra CSRF token ngay khi load
      const csrfToken = document.querySelector('meta[name="csrf-token"]');
      if (!csrfToken) {
        console.error('CSRF token NOT found in meta tag!');
      }
      
      // Force load danh sách tỉnh cho cả hai hệ
      setTimeout(() => {
        this.forceLoadProvinces();
      }, 100);
      
      // Ngăn chặn click events khi đang loading/saving
      document.addEventListener('click', (e) => {
        if (this.loading || this.saving) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      }, true);
      
      // Không cần kiểm tra canSave nữa
      
      // Không cần setup geo listeners nữa
    },

    // Watcher để tự động cập nhật scheme khi thay đổi fileType
    get currentScheme() {
      return this.fileType === 'new' ? 'new' : 'old';
    },

    // Watcher để setup geo listeners khi fileType thay đổi
    get fileType() {
      return this._fileType || 'new';
    },
    set fileType(value) {
      // Ngăn chặn thay đổi fileType khi đang loading/saving
      if (this.loading || this.saving) {
        return;
      }
      
      this._fileType = value;
      
      // Cập nhật scheme của geo-filter
      this.updateGeoFilterScheme(value);
      
      // Bỏ thông báo về hành chính cũ/mới
      // Cả Excel cũ và mới đều dùng hành chính mới
      
      // Không cần setup geo listeners nữa
    },

    // Không cần setup geo listeners nữa

    // Force load danh sách tỉnh cho cả hai hệ khi trang load
    forceLoadProvinces() {
      const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
      if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        if (geoFilterData) {
          // Load danh sách tỉnh cho cả hai hệ
          geoFilterData.loadProvinces('old');
          geoFilterData.loadProvinces('new');
        }
      }
    },

    // Cập nhật scheme của geo-filter
    updateGeoFilterScheme(fileType) {
      // Tìm geo-filter component trên trang
      const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
      if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        if (geoFilterData) {
          // Luôn dùng scheme new cho cả Excel cũ và mới
          geoFilterData.scheme = 'new';
          
          // Kiểm tra xem user có phải admin không
          const isAdmin = @json(auth()->user()?->role_level === 'admin');
          
          // Cả Excel cũ và mới đều dùng hành chính mới và khóa tỉnh/xã theo user
          if (!isAdmin) {
            this.autoAssignGeoForNonAdmin(geoFilterData);
          }
          // Khóa các trường cho người dùng không phải admin
          geoFilterData.lockProvince = !isAdmin;
          geoFilterData.lockWard = !isAdmin;
          
          // Reset các giá trị của hệ cũ (không dùng nữa)
          geoFilterData.selProvOld = '';
          geoFilterData.selDistOld = '';
          geoFilterData.selWardOld = '';
          geoFilterData.selHamletOld = '';
          
          // Đảm bảo load lại danh sách tỉnh cho hệ mới
          if (geoFilterData.provincesNew.length === 0) {
            geoFilterData.loadProvinces('new');
          }
          
          // Trigger change event để geo-filter cập nhật UI
          geoFilterElement.dispatchEvent(new Event('change'));
        }
      }
    },

    // Tự động gán geo cho người dùng không phải admin
    autoAssignGeoForNonAdmin(geoFilterData) {
      // Kiểm tra xem user có phải admin không
      const isAdmin = @json(auth()->user()?->role_level === 'admin');
      
      if (!isAdmin) {
        // Lấy thông tin geo từ user
        const userProvinceId = @json(auth()->user()?->province_id);
        const userWardId = @json(auth()->user()?->ward_id);
        
        // Gán province và ward nếu có
        if (userProvinceId) {
          geoFilterData.selProv = userProvinceId;
        }
        if (userWardId) {
          geoFilterData.selWard = userWardId;
        }
      }
    },

    // Watcher cho previewData
    get previewData() {
      return this._previewData || [];
    },
    set previewData(value) {
      // Ngăn chặn thay đổi previewData khi đang loading/saving
      if (this.loading || this.saving) {
        return;
      }
      
      this._previewData = value;
    },

    async handleFileSelect(event) {
      const file = event.target.files[0];
      if (!file) return;

      // Ngăn chặn multiple file selections
      if (this.loading || this.saving) {
        return;
      }

      this.loading = true;
      // Reset data trực tiếp thay vì qua setter
      this._previewData = [];
      this.rowErrors = [];
      this.messageErrors = [];
      this.showErrors = false;
      this.summary = { total: 0, ok: 0, fail: 0 };
      this.errorsAll = [];

      try {
        // Chọn hàm đọc file dựa trên fileType
        const data = this.fileType === 'new' 
          ? await this.readExcelFileNew(file)
          : await this.readExcelFile(file);
        this._previewData = data;
        
        // Debug trạng thái sau khi có dữ liệu
        setTimeout(() => {
          // File load completed
        }, 100);
      } catch (error) {
        console.error('Error reading file:', error);
        alert('Lỗi khi đọc file: ' + error.message);
      } finally {
        // Đảm bảo loading được reset ngay cả khi có lỗi
        setTimeout(() => {
          this.loading = false;
        }, 100);
      }
    },

    async readExcelFile(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
          try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            
            // Tìm hàng có số thứ tự tăng dần (1, 2, 3, ..., 36)
            let numberRowIndex = -1;
            for (let i = 0; i < Math.min(jsonData.length, 25); i++) {
              const row = jsonData[i] || [];
              let consec = 0;
              for (let c = 0; c < row.length; c++) {
                if (String(row[c]).trim() === String(c+1)) consec++;
              }
              const justNums = row.filter(v => /^\s*\d+\s*$/.test(String(v))).length;
              if (consec >= 8 || justNums >= 15) {
                numberRowIndex = i;
                break;
              }
            }
            
            if (numberRowIndex === -1) {
              throw new Error('Không tìm thấy hàng có số thứ tự tăng dần (1, 2, 3, ..., 36)');
            }
            
            // Lấy header từ hàng trước hàng số thứ tự
            const headerRowIndex = numberRowIndex > 0 ? numberRowIndex - 1 : 0;
            const headers = (jsonData[headerRowIndex] || []).map(h => String(h||'').trim());
            
            // Lấy dữ liệu từ hàng sau hàng số thứ tự
            const rows = jsonData.slice(numberRowIndex + 1).filter(row => row.length > 0);
            
            // Mapping từ số thứ tự sang field names (36 cột)
            const COLUMN_MAPPING = {
              1: 'row_no',
              2: 'ho_dem', 3: 'ten', 4: 'dob_day', 5: 'dob_month', 6: 'dob_year', 7: 'identity_number',
              8: 'is_female', 9: 'ethnicity', 10: 'religion', 11: 'priority_name',
              12: 'province_name', 13: 'district_name', 14: 'ward_name',
              15: 'education_block_name', 16: 'education_grade_name', 17: 'hoc_hai_buoi', 18: 'hoc_9_10_buoi_tuan',
              19: 'school_code', 20: 'graduation_level_name', 21: 'is_bo_tuc', 22: 'graduation_year',
              23: 'bo_hoc_lop', 24: 'bo_hoc_nam',
              25: 'dis_VAN_DONG', 26: 'dis_NGHE_NOI', 27: 'dis_NHIN', 28: 'dis_THAN_KINH',
              29: 'dis_TRI_TUE', 30: 'dis_HOC_TAP', 31: 'dis_TU_KY', 32: 'dis_KHAC',
              33: 'dis_has_certificate', 34: 'dis_can_learn',
              35: 'special_circumstance_name', 36: 'special_circumstance_detail'
            };
            
            // Tạo mapping từ số thứ tự sang vị trí cột
            const numberRow = jsonData[numberRowIndex] || [];
            const columnIndexMap = {}; // số thứ tự -> vị trí cột
            
            for (let i = 0; i < numberRow.length; i++) {
              const raw = String(numberRow[i] ?? '').trim();
              const num = parseInt(raw.replace(/\D+/g,''), 10);
              if (num >= 1 && num <= 36) {
                columnIndexMap[num] = i;
              }
            }
            
            // Map dữ liệu theo số thứ tự
            const mappedData = rows.map((row, index) => {
              const mappedRow = { row_no: index + 1 };
              
              // Map từng số thứ tự sang field name và lấy giá trị từ vị trí cột tương ứng
              for (const [num, colIndex] of Object.entries(columnIndexMap)) {
                const fieldName = COLUMN_MAPPING[parseInt(num)];
                if (fieldName && row[colIndex] !== undefined) {
                  let value = row[colIndex] || '';
                  
                  // Convert các trường số từ string sang integer (theo logic survey)
                  if (['dob_day', 'dob_month', 'dob_year', 'graduation_year', 'bo_hoc_nam'].includes(fieldName)) {
                    const v = parseInt(value, 10);
                    value = isNaN(v) ? null : v;
                  }
                  
                  // Convert các trường boolean từ string sang boolean (theo logic survey)
                  if (['is_female', 'hoc_hai_buoi', 'hoc_9_10_buoi_tuan', 'is_bo_tuc', 'dis_VAN_DONG', 'dis_NGHE_NOI', 'dis_NHIN', 'dis_THAN_KINH', 'dis_TRI_TUE', 'dis_HOC_TAP', 'dis_TU_KY', 'dis_KHAC', 'dis_has_certificate', 'dis_can_learn'].includes(fieldName)) {
                    const originalValue = value;
                    value = this.toBool(value);
                    // Debug cho các trường đặc biệt
                    if (['hoc_hai_buoi', 'hoc_9_10_buoi_tuan'].includes(fieldName)) {
                    }
                  }
                  
                  mappedRow[fieldName] = value;
                }
              }
              
              return mappedRow;
            });
            
            resolve(mappedData);
          } catch (error) {
            reject(error);
          }
        };
        reader.onerror = () => reject(new Error('Lỗi khi đọc file'));
        reader.readAsArrayBuffer(file);
      });
    },

    // Hàm đọc file Excel mới (có cột ID và bỏ cột Quận/Huyện)
    async readExcelFileNew(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
          try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            
            // Tìm hàng có số thứ tự tăng dần (1, 2, 3, ..., 36) - có ID đầu và bỏ cột Quận/Huyện
            let numberRowIndex = -1;
            for (let i = 0; i < Math.min(jsonData.length, 25); i++) {
              const row = jsonData[i] || [];
              let consec = 0;
              for (let c = 0; c < row.length; c++) {
                if (String(row[c]).trim() === String(c+1)) consec++;
              }
              const justNums = row.filter(v => /^\s*\d+\s*$/.test(String(v))).length;
              if (consec >= 8 || justNums >= 15) { // Tăng lên 15 vì có 36 cột
                numberRowIndex = i;
                break;
              }
            }
            
            if (numberRowIndex === -1) {
              throw new Error('Không tìm thấy hàng có số thứ tự tăng dần (1, 2, 3, ..., 36)');
            }
            
            // Lấy header từ hàng trước hàng số thứ tự
            const headerRowIndex = numberRowIndex > 0 ? numberRowIndex - 1 : 0;
            const headers = (jsonData[headerRowIndex] || []).map(h => String(h||'').trim());
            
            // Lấy dữ liệu từ hàng sau hàng số thứ tự
            const rows = jsonData.slice(numberRowIndex + 1).filter(row => row.length > 0);
            
            // Debug: Log thông tin về file Excel mới
            console.log('Excel mới - Number row index:', numberRowIndex);
            console.log('Excel mới - Headers:', headers);
            console.log('Excel mới - First data row:', rows[0]);
            console.log('Excel mới - Total data rows:', rows.length);
            
            // Mapping từ số thứ tự sang field names (36 cột - có ID và bỏ Quận/Huyện)
            const COLUMN_MAPPING = {
              1: 'id', // ID
              2: 'row_no', // TT
              3: 'ho_dem', // Họ đệm
              4: 'ten', // Tên
              5: 'dob_day', // Ngày
              6: 'dob_month', // Tháng
              7: 'dob_year', // Năm sinh
              8: 'identity_number', // Số định danh cá nhân
              9: 'is_female', // Nữ
              10: 'ethnicity', // Dân tộc
              11: 'religion', // Tôn giáo
              12: 'priority_name', // Diện ưu tiên
              13: 'province_name', // Tỉnh/Thành
              14: 'ward_name', // Phường/Xã (bỏ Quận/Huyện)
              15: 'education_block_name', // Khối học
              16: 'education_grade_name', // Lớp học
              17: 'hoc_hai_buoi', // Học 2 buổi/ngày
              18: 'hoc_9_10_buoi_tuan', // Học đầy đủ theo quy định
              19: 'school_code', // Mã trường
              20: 'graduation_level_name', // Bậc tốt nghiệp
              21: 'is_bo_tuc', // Bổ túc
              22: 'graduation_year', // Năm tốt nghiệp
              23: 'bo_hoc_lop', // Lớp
              24: 'bo_hoc_nam', // Năm
              25: 'dis_VAN_DONG', // Khuyết tật vận động
              26: 'dis_NGHE_NOI', // Khuyết tật nghe, nói
              27: 'dis_NHIN', // Khuyết tật nhìn
              28: 'dis_THAN_KINH', // Khuyết tật thần kinh, tâm thần
              29: 'dis_TRI_TUE', // Khuyết tật trí tuệ
              30: 'dis_HOC_TAP', // Khuyết tật học tập
              31: 'dis_TU_KY', // Tự kỷ
              32: 'dis_KHAC', // Khuyết tật khác
              33: 'dis_has_certificate', // Có chứng nhận khuyết tật
              34: 'dis_can_learn', // Khả năng học tập
              35: 'special_circumstance_name', // Hoàn cảnh đặc biệt
              36: 'special_circumstance_detail' // Chi tiết hoàn cảnh đặc biệt
            };
            
            const result = [];
            for (let i = 0; i < rows.length; i++) {
              const row = rows[i];
              const mappedRow = { row_no: i + 1 };
              
              for (let col = 0; col < row.length; col++) {
                const fieldName = COLUMN_MAPPING[col + 1];
                if (fieldName) {
                  let value = row[col];
                  
                  // Xử lý các trường boolean
                  if (['is_female', 'hoc_hai_buoi', 'hoc_9_10_buoi_tuan', 'is_bo_tuc', 
                       'dis_VAN_DONG', 'dis_NGHE_NOI', 'dis_NHIN', 'dis_THAN_KINH',
                       'dis_TRI_TUE', 'dis_HOC_TAP', 'dis_TU_KY', 'dis_KHAC',
                       'dis_has_certificate', 'dis_can_learn'].includes(fieldName)) {
                    value = this.toBool(value);
                  }
                  
                  // Xử lý các trường số
                  if (['dob_day', 'dob_month', 'dob_year', 'graduation_year', 'bo_hoc_lop', 'bo_hoc_nam'].includes(fieldName)) {
                    value = value ? parseInt(value) : null;
                  }
                  
                  // Debug: Log ID để kiểm tra
                  if (fieldName === 'id') {
                    console.log('Excel ID found:', value, 'Type:', typeof value);
                  }
                  
                  mappedRow[fieldName] = value;
                }
              }
              
              result.push(mappedRow);
            }
            
            resolve(result);
          } catch (error) {
            reject(error);
          }
        };
        reader.onerror = () => reject(new Error('Lỗi khi đọc file'));
        reader.readAsArrayBuffer(file);
      });
    },

    // Function toBool từ survey (logic chuẩn) - hỗ trợ cả 'v' và 'x'
    toBool(v) {
      const raw = String(v ?? '').trim();
      const s = raw.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
      if (!s) return false;
      if (raw.includes('✓') || raw.includes('✔')) return true;
      if (['1','x','v','yes','y','true','t','co'].includes(s)) return true; // Thêm 'v' vào danh sách
      if (['0','no','n','false','k','ko','khong'].includes(s)) return false;
      if (/^\d+$/.test(s)) return Number(s) > 0;
      return false;
    },

    parseBoolean(value) {
      if (typeof value === 'boolean') return value;
      if (typeof value === 'number') return value > 0;
      if (typeof value === 'string') {
        const s = value.toLowerCase().trim();
        return ['1', 'true', 'x', 'có', 'co', 'yes', 'y'].includes(s);
      }
      return false;
    },


    async saveData() {
      if (this._previewData.length === 0) {
        alert('Vui lòng chọn file Excel trước');
        return;
      }

      // Ngăn chặn multiple clicks
      if (this.saving) {
        return;
      }

      this.saving = true;

      // Lấy năm học từ selectbox (đảm bảo lấy đúng giá trị)
      const academicYearSelect = document.querySelector('select[x-model="academicYear"]');
      const academicYear = academicYearSelect?.value || this.academicYear;
      
      // Lấy geo data từ geo filter (luôn cần để so sánh)
      // Kiểm tra cả select và hidden input vì khi khóa sẽ dùng hidden input
      const geoData = {
        province_id: document.querySelector('select[name="geo[province_id]"]')?.value || 
                     document.querySelector('input[name="geo[province_id]"]')?.value || null,
        district_id: document.querySelector('select[name="geo[district_id]"]')?.value || 
                     document.querySelector('input[name="geo[district_id]"]')?.value || null,
        ward_id: document.querySelector('select[name="geo[ward_id]"]')?.value || 
                 document.querySelector('input[name="geo[ward_id]"]')?.value || null,
        village_id: document.querySelector('select[name="geo[village_id]"]')?.value || 
                    document.querySelector('input[name="geo[village_id]"]')?.value || null,
      };
      
      // Debug geo data
      console.log('Geo data from select boxes:', geoData);
      console.log('Province select element:', document.querySelector('select[name="geo[province_id]"]'));
      console.log('Ward select element:', document.querySelector('select[name="geo[ward_id]"]'));
      
      // Debug: Check all geo elements (select + hidden input)
      console.log('All geo elements:');
      console.log('geo[province_id] select:', document.querySelector('select[name="geo[province_id]"]'));
      console.log('geo[province_id] input:', document.querySelector('input[name="geo[province_id]"]'));
      console.log('geo[ward_id] select:', document.querySelector('select[name="geo[ward_id]"]'));
      console.log('geo[ward_id] input:', document.querySelector('input[name="geo[ward_id]"]'));
      console.log('geo[village_id] select:', document.querySelector('select[name="geo[village_id]"]'));
      console.log('geo[village_id] input:', document.querySelector('input[name="geo[village_id]"]'));
      
      // Debug: Check Alpine.js data
      const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
      if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        console.log('Alpine.js geo data:', {
          scheme: geoFilterData.scheme,
          selProvNew: geoFilterData.selProvNew,
          selWardNew: geoFilterData.selWardNew,
          selHamletNew: geoFilterData.selHamletNew
        });
      }
      
      // Validate geo data
      if (!geoData.province_id || !geoData.ward_id) {
        alert('Vui lòng chọn tỉnh và xã trước khi import!');
        return;
      }
      console.log('Academic year from select box:', academicYear);
      
      // Lấy scheme từ fileType
      const scheme = this.currentScheme;
      
      // Geo data and scheme check completed

      const payload = {
        academic_year: academicYear,
        scheme: scheme,
        geo: geoData,
        rows: this._previewData
      };

      // Debug payload để xem dữ liệu gửi lên
      // Payload and preview data check completed
      
      // Debug chi tiết các trường có thể gây lỗi validation
      // Validation debug completed
      // Row validation check completed
      
      // Format check completed
      // Final data check completed

      try {
        // Kiểm tra CSRF token trước khi gửi
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) {
          throw new Error('CSRF token không tìm thấy trong meta tag');
        }
        
        const token = csrfToken.getAttribute('content');
        if (!token) {
          throw new Error('CSRF token rỗng');
        }
        
        // CSRF token check completed
        
        // Sử dụng fetch thuần như survey import
        let response;
        try {
          // Chọn URL dựa trên fileType
          const url = this.fileType === 'new' 
            ? '{{ route("front.students-off-track.import.new") }}'
            : '{{ route("front.students-off-track.import.old") }}';
          
          // Debug: Log payload trước khi gửi
          console.log('Sending payload:', payload);
          console.log('File type:', this.fileType);
          console.log('Scheme:', payload.scheme);
          console.log('Geo data:', payload.geo);
          console.log('First row:', payload.rows[0]);
          console.log('Total rows:', payload.rows.length);
          
          // Fetch request initiated
          response = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': token,
              'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
          });
        } catch (fetchError) {
          console.error('Fetch error:', fetchError);
          throw fetchError;
        }
        
        // Kiểm tra response status
        // Response status check completed
        
        if (response.status === 419) {
          console.error('CSRF token mismatch - Status 419');
          // Backup method initiated
          
          // Phương pháp backup: tạo form ẩn và submit
          const form = document.createElement('form');
          form.method = 'POST';
          // Chọn action dựa trên fileType
          form.action = this.fileType === 'new' 
            ? '{{ route("front.students-off-track.import.new") }}'
            : '{{ route("front.students-off-track.import.old") }}';
          form.style.display = 'none';
          
          // Thêm CSRF token
          const csrfInput = document.createElement('input');
          csrfInput.type = 'hidden';
          csrfInput.name = '_token';
          csrfInput.value = token;
          form.appendChild(csrfInput);
          
          // Thêm dữ liệu
          const dataInput = document.createElement('input');
          dataInput.type = 'hidden';
          dataInput.name = 'rows';
          dataInput.value = JSON.stringify(payload.rows);
          form.appendChild(dataInput);
          
          const yearInput = document.createElement('input');
          yearInput.type = 'hidden';
          yearInput.name = 'academic_year';
          yearInput.value = payload.academic_year;
          form.appendChild(yearInput);
          
          const schemeInput = document.createElement('input');
          schemeInput.type = 'hidden';
          schemeInput.name = 'scheme';
          schemeInput.value = payload.scheme;
          form.appendChild(schemeInput);
          
          const geoInput = document.createElement('input');
          geoInput.type = 'hidden';
          geoInput.name = 'geo';
          geoInput.value = JSON.stringify(payload.geo);
          form.appendChild(geoInput);
          
          document.body.appendChild(form);
          form.submit();
          return;
        }

        let result = null;
        try {
          // Xử lý response giống survey import
          result = await response.clone().json();
          // Raw response check completed
          
          // Debug: Log response
          console.log('Server response:', result);
          if (result.debug) {
            console.log('Debug info:', result.debug);
          }
          
          if (!result) {
            console.error('Empty response from server');
            alert('Lỗi: Server trả về response rỗng');
            return;
          }
        } catch (jsonError) {
          console.error('Failed to parse response:', jsonError);
          alert('Lỗi: Server trả về dữ liệu không hợp lệ');
          return;
        }
        
        // Debug response để xem lỗi cụ thể
        // Server response check completed
        
        if (result && typeof result === 'object') {
          // Response details check completed
        } else {
          console.error('Invalid response format:', result);
          alert('Lỗi: Định dạng phản hồi từ server không hợp lệ');
          return;
        }
        
        // Xem chi tiết lỗi dob_day (chỉ khi có errors)
        if (result.errors && typeof result.errors === 'object') {
          const dobDayErrors = Object.keys(result.errors).filter(key => key.includes('dob_day'));
          // dob_day errors check completed
          
          // Xem chi tiết lỗi is_female
          const isFemaleErrors = Object.keys(result.errors).filter(key => key.includes('is_female'));
          // is_female errors check completed
        }

        // Xử lý response giống survey import
        if (response.ok) {
          // Cập nhật thống kê và hiển thị kết quả (format giống survey)
          this.summary.total = result.total_rows || 0;
          this.summary.ok = result.ok_count || 0;
          this.summary.fail = result.fail_count || 0;
          
          // Xử lý lỗi từng dòng (ưu tiên format có ho_ten)
          this.errorsAll = [];
          if (result.errors && result.errors.length > 0) {
            // Format phức tạp có ho_ten
            this.errorsAll = result.errors.map((error, index) => ({
              excel_row: error.row_no || (index + 1),
              ho_ten: error.ho_ten || '',
              messages: Array.isArray(error.errors) ? error.errors : [error.errors || 'Lỗi không xác định']
            }));
          } else if (Array.isArray(result.row_errors) && Array.isArray(result.message_errors)) {
            // Fallback: format đơn giản không có ho_ten
            this.errorsAll = result.row_errors.map((rowNo, index) => ({
              excel_row: rowNo,
              ho_ten: '', // Không có trong format đơn giản
              messages: [result.message_errors[index] ?? 'Lỗi không xác định']
            }));
          }
          
          // Debug để xem thống kê
          // Success response check completed
        } else if (response.status === 422) {
          // Xử lý lỗi 422 giống survey import
          // Validation failed check completed
          
          // Cập nhật thống kê
          this.summary.total = result.total_rows || 0;
          this.summary.ok = result.ok_count || 0;
          this.summary.fail = result.fail_count || 0;
          
          // Gom lỗi từng dòng (giống survey import)
          if (Array.isArray(result.row_errors)) {
            let list = result.row_errors;
            
            // Dạng server hiện tại: row_errors = [index,...], message_errors = ["msg",...]
            if (list.length && typeof list[0] === 'number' && Array.isArray(result.message_errors)) {
              list = list.map((idx, k) => ({
                excel_row: idx,
                ho_ten: '', // Không có trong format đơn giản
                messages: [result.message_errors[k] ?? 'Lỗi không xác định']
              }));
            }
            
            this.errorsAll = list;
          } else {
            // Dự phòng: lỗi validate chung của request (không phải lỗi từng dòng)
            const text = result?.message || 'Dữ liệu không hợp lệ (422).';
            this.errorsAll = [{ excel_row: null, ho_ten: '', messages: [text] }];
          }
        }
      } catch (error) {
        console.error('Save error:', error);
        console.error('Error type:', typeof error);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        // Hiển thị lỗi chung
        alert('Lỗi khi lưu dữ liệu: ' + (error.message || 'Lỗi không xác định'));
      } finally {
        // Đảm bảo saving được reset ngay cả khi có lỗi
        setTimeout(() => {
          this.saving = false;
        }, 100);
      }
    },

    resetData() {
      // // Xác nhận trước khi reset
      // if (!confirm('Bạn có chắc muốn xóa tất cả dữ liệu đã chọn/nhập? Hành động này không thể hoàn tác.')) {
      //   return;
      // }
      
      // Ngăn chặn reset khi đang loading/saving
      if (this.loading || this.saving) {
        return;
      }
      
      // Reset tất cả dữ liệu
      this._previewData = [];
      this.rowErrors = [];
      this.messageErrors = [];
      this.showErrors = false;
      this.summary = { total: 0, ok: 0, fail: 0 };
      this.errorsAll = [];
      this.saving = false;
      this.loading = false;
      
      // Reset file input
      const fileInput = document.querySelector('input[type="file"]');
      if (fileInput) {
        fileInput.value = '';
      }
      
      // Reset geo filter (giữ nguyên để tiện sử dụng)
      // Không reset academicYear và scheme để tiện sử dụng
    },

    getStudentName(excelRow) {
      if (!excelRow || !this._previewData.length) return '';
      
      // Tìm học sinh trong previewData dựa trên excel_row
      const student = this._previewData.find(row => row.excel_row === excelRow);
      if (student) {
        const hoDem = student.ho_dem || '';
        const ten = student.ten || '';
        return `${hoDem} ${ten}`.trim();
      }
      
      return '';
    },

    displayRowErrors(rowErrors, messageErrors = []) {
      if (!Array.isArray(rowErrors)) {
        console.error('rowErrors is not an array:', rowErrors);
        return;
      }
      
      this.rowErrors = rowErrors.map((rowError, index) => {
        if (typeof rowError === 'object' && rowError.row_no) {
          return {
            row_no: rowError.row_no,
            message: Array.isArray(rowError.errors) ? rowError.errors.join('; ') : (rowError.errors || 'Lỗi không xác định')
          };
        } else {
          return {
            row_no: rowError || `Dòng ${index + 1}`,
            message: messageErrors[index] || 'Lỗi không xác định'
          };
        }
      });
      this.messageErrors = messageErrors;
      this.showErrors = true;
    },

    displayValidationErrors(errors) {
      this.rowErrors = errors.map(error => ({
        row_no: error.row_no || 'không rõ',
        message: Array.isArray(error.errors) ? error.errors.join('; ') : error.errors
      }));
      this.messageErrors = errors.map(error => 
        Array.isArray(error.errors) ? error.errors : [error.errors]
      ).flat();
      this.showErrors = true;
    },

    downloadErrorXLSX() {
      if (!this.errorsAll.length) { 
        alert('Không có lỗi.'); 
        return; 
      }
      
      // Ngăn chặn download khi đang loading/saving
      if (this.loading || this.saving) {
        return;
      }
      
      const rows = this.errorsAll.map(e => ({
        'Dòng Excel': e.excel_row ?? '',
        'Họ tên': e.ho_ten ?? '',
        'Lỗi': Array.isArray(e.messages) ? e.messages.join('; ') : ''
      }));

      const ws = XLSX.utils.json_to_sheet(rows);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Lỗi');
      XLSX.writeFile(wb, 'loi_hoc_sinh_trai_tuyen.xlsx');
    }
  }
}
</script>

<!-- Include XLSX.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- Include Axios for better CSRF handling -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.6.0/axios.min.js"></script>

<script>
// Cấu hình axios để tự động gửi CSRF token
if (typeof axios !== 'undefined') {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
}
</script>
@endsection
