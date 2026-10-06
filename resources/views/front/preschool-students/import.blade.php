@extends('layouts.front')
@section('title','Nhập học sinh mầm non từ Excel')
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
    <h1 class="text-lg font-semibold">Nhập Excel học sinh mầm non</h1>
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
          <option :value="yy" x-text="yy"></option>
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
      <p class="text-xs text-slate-500 mt-1">Hỗ trợ .xlsx/.xls. Dung lượng ≤ 10MB.</p>
    </div>

    <!-- Địa bàn -->
    <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        'scheme'      => 'new',
        'schemeMode'  => 'new',   // hoặc 'old' / 'new'
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
                x-show="errorsAll.length > 0"
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
            <th colspan="8" class="th bg-blue-50 text-blue-800 border-b-2 border-blue-200 text-center">Thông tin học sinh</th>
            <th colspan="11" class="th bg-green-50 text-green-800 border-b-2 border-green-200 text-center">Thông tin học tập</th>
            <th colspan="8" class="th bg-purple-50 text-purple-800 border-b-2 border-purple-200 text-center">Thông tin liên hệ</th>
          </tr>
          <!-- Dòng tiêu đề cột -->
          <tr class="bg-slate-100">
            <th class="th">TT</th>
            <th class="th">Họ đệm</th>
            <th class="th">Tên</th>
            <th class="th">Ngày</th>
            <th class="th">Tháng</th>
            <th class="th">Năm sinh</th>
            <th class="th">Nữ</th>
            <th class="th">Dân tộc</th>
            <th class="th">Khối học</th>
            <th class="th">Lớp học</th>
            <th class="th">Mã trường</th>
            <th class="th">Học 2 buổi</th>
            <th class="th">Học bán trú</th>
            <th class="th">Học đủ ngày</th>
            <th class="th">Chuyên cần</th>
            <th class="th">Theo dõi cân nặng</th>
            <th class="th">SDD nhẹ cân</th>
            <th class="th">Theo dõi chiều cao</th>
            <th class="th">SDD thấp còi</th>
            <th class="th">Dân tộc chuẩn bị TV</th>
            <th class="th">Hoàn thành GDMN</th>
            <th class="th">Họ tên chủ hộ</th>
            <th class="th">Địa chỉ</th>
            <th class="th">Tổ/Thôn/Ấp</th>
            <th class="th">Số phiếu</th>
            <th class="th">Họ tên Cha/Mẹ</th>
            <th class="th">Điện thoại</th>
            <th class="th">Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(row, index) in _previewData" :key="index">
            <tr :class="{'bg-red-50': row.hasError, 'bg-green-50': row.found}">
              <td class="td" x-text="index + 1"></td>
              <td class="td" x-text="row.ho_dem || ''"></td>
              <td class="td" x-text="row.ten || ''"></td>
              <td class="td" x-text="row.dob_day || ''"></td>
              <td class="td" x-text="row.dob_month || ''"></td>
              <td class="td" x-text="row.dob_year || ''"></td>
              <td class="td" x-text="row.is_female ? 'x' : ''"></td>
              <td class="td" x-text="row.ethnicity || ''"></td>
              <td class="td" x-text="row.education_block_name || ''"></td>
              <td class="td" x-text="row.education_grade_name || ''"></td>
              <td class="td" x-text="row.school_code || ''"></td>
              <td class="td" x-text="row.hoc_2_buoi ? 'x' : ''"></td>
              <td class="td" x-text="row.hoc_ban_tru ? 'x' : ''"></td>
              <td class="td" x-text="row.hoc_du_ngay_theo_quy_dinh ? 'x' : ''"></td>
              <td class="td" x-text="row.chuyen_can ? 'x' : ''"></td>
              <td class="td" x-text="row.theo_doi_bd_can_nang ? 'x' : ''"></td>
              <td class="td" x-text="row.sdd_nhe_can ? 'x' : ''"></td>
              <td class="td" x-text="row.theo_doi_bd_chieu_cao ? 'x' : ''"></td>
              <td class="td" x-text="row.sdd_thap_coi ? 'x' : ''"></td>
              <td class="td" x-text="row.dan_toc_chuan_bi_tv ? 'x' : ''"></td>
              <td class="td" x-text="row.hoan_thanh_chuong_trinh_gdmn ? 'x' : ''"></td>
              <td class="td" x-text="row.head_person_name || ''"></td>
              <td class="td" x-text="row.addressline || ''"></td>
              <td class="td" x-text="row.village_name || ''"></td>
              <td class="td" x-text="row.so_phieu || ''"></td>
              <td class="td" x-text="row.contact_name || ''"></td>
              <td class="td" x-text="row.phone || ''"></td>
              <td class="td">
                <span x-show="row.found" class="text-green-600 font-medium">✓ Tìm thấy</span>
                <span x-show="row.hasError" class="text-red-600 font-medium">✗ Lỗi</span>
                <span x-show="!row.found && !row.hasError" class="text-gray-500">Chưa xử lý</span>
              </td>
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
    academicYear: '{{ \App\Models\SurveyForm::currentAcademicYear() }}',
    _previewData: [],
    errorsAll: [],
    summary: { total: 0, ok: 0, fail: 0 },
    
    yearOptions: [
      @php
        $currentYear = date('Y');
        $years = [];
        for ($year = 2000; $year <= $currentYear + 1; $year++) {
          $years[] = $year . '-' . ($year + 1);
        }
        echo "'" . implode("', '", $years) . "'";
      @endphp
    ],

    boot() {
      // Kiểm tra CSRF token ngay khi load
      const csrfToken = document.querySelector('meta[name="csrf-token"]');
      if (!csrfToken) {
        console.error('CSRF token NOT found in meta tag!');
      }
      
      // Ngăn chặn click events khi đang loading/saving
      document.addEventListener('click', (e) => {
        if (this.loading || this.saving) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      }, true);
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
      this.errorsAll = [];
      this.summary = { total: 0, ok: 0, fail: 0 };

      try {
        const data = await this.readExcelFile(file);
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
            
            // Tìm hàng có số thứ tự tăng dần (1, 2, 3, ..., 27)
            let numberRowIndex = -1;
            for (let i = 0; i < Math.min(jsonData.length, 25); i++) {
              const row = jsonData[i] || [];
              let consec = 0;
              for (let c = 0; c < row.length; c++) {
                if (String(row[c]).trim() === String(c+1)) consec++;
              }
              const justNums = row.filter(v => /^\s*\d+\s*$/.test(String(v))).length;
              if (consec >= 8 || justNums >= 10) {
                numberRowIndex = i;
                break;
              }
            }
            
            if (numberRowIndex === -1) {
              throw new Error('Không tìm thấy hàng có số thứ tự tăng dần (1, 2, 3, ..., 27)');
            }
            
            // Lấy dữ liệu từ hàng sau hàng số thứ tự
            const rows = jsonData.slice(numberRowIndex + 1).filter(row => row.length > 0);
            
            // Mapping từ số thứ tự sang field names (27 cột cho học sinh mầm non)
            const COLUMN_MAPPING = {
              1: 'row_no',           // A - TT
              2: 'ho_dem',           // B - Họ đệm
              3: 'ten',              // C - Tên
              4: 'dob_day',          // D - Ngày
              5: 'dob_month',        // E - Tháng
              6: 'dob_year',         // F - Năm sinh
              7: 'is_female',        // G - Nữ
              8: 'ethnicity',        // H - Dân tộc
              9: 'education_block_name',  // I - Khối học
              10: 'education_grade_name', // J - Lớp học
              11: 'school_code',     // K - Mã trường
              12: 'hoc_2_buoi',      // L - Học 2 buổi
              13: 'hoc_ban_tru',     // M - Học bán trú
              14: 'hoc_du_ngay_theo_quy_dinh', // N - Học đủ ngày
              15: 'chuyen_can',      // O - Chuyên cần
              16: 'theo_doi_bd_can_nang', // P - Theo dõi cân nặng
              17: 'sdd_nhe_can',     // Q - SDD nhẹ cân
              18: 'theo_doi_bd_chieu_cao', // R - Theo dõi chiều cao
              19: 'sdd_thap_coi',    // S - SDD thấp còi
              20: 'dan_toc_chuan_bi_tv', // T - Dân tộc chuẩn bị TV
              21: 'hoan_thanh_chuong_trinh_gdmn', // U - Hoàn thành GDMN
              22: 'head_person_name', // V - Họ tên chủ hộ
              23: 'addressline',     // W - Địa chỉ
              24: 'village_name',    // X - Tổ/Thôn/Ấp
              25: 'so_phieu',        // Y - Số phiếu
              26: 'contact_name',    // Z - Họ tên Cha/Mẹ
              27: 'phone'            // AA - Điện thoại
            };
            
            // Tạo mapping từ số thứ tự sang vị trí cột
            const numberRow = jsonData[numberRowIndex] || [];
            const columnIndexMap = {}; // số thứ tự -> vị trí cột
            
            for (let i = 0; i < numberRow.length; i++) {
              const raw = String(numberRow[i] ?? '').trim();
              const num = parseInt(raw.replace(/\D+/g,''), 10);
              if (num >= 1 && num <= 27) {
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
                  
                  // Convert các trường số từ string sang integer
                  if (['dob_day', 'dob_month', 'dob_year'].includes(fieldName)) {
                    const v = parseInt(value, 10);
                    value = isNaN(v) ? null : v;
                  }
                  
                  // Convert các trường boolean từ string sang boolean
                  if (['is_female', 'hoc_2_buoi', 'hoc_ban_tru', 'hoc_du_ngay_theo_quy_dinh', 
                       'chuyen_can', 'theo_doi_bd_can_nang', 'sdd_nhe_can', 'theo_doi_bd_chieu_cao', 
                       'sdd_thap_coi', 'dan_toc_chuan_bi_tv', 'hoan_thanh_chuong_trinh_gdmn'].includes(fieldName)) {
                    value = this.toBool(value);
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

      // Lấy năm học từ selectbox
      const academicYearSelect = document.querySelector('select[x-model="academicYear"]');
      const academicYear = academicYearSelect?.value || this.academicYear;
      
      // Lấy geo data từ geo filter
      const geoData = {
        province_id: document.querySelector('select[name="geo[province_id]"]')?.value || null,
        district_id: document.querySelector('select[name="geo[district_id]"]')?.value || null,
        ward_id: document.querySelector('select[name="geo[ward_id]"]')?.value || null,
        village_id: document.querySelector('select[name="geo[village_id]"]')?.value || null,
      };
      
      const payload = {
        academic_year: academicYear,
        geo: geoData,
        rows: this._previewData
      };

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
        
        const response = await fetch('{{ route("front.preschool-students.import.save") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });
        
        let result = null;
        try {
          result = await response.clone().json();
          
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
        
        if (response.ok) {
          // Cập nhật thống kê và hiển thị kết quả
          this.summary.total = result.total_rows || 0;
          this.summary.ok = result.updated_count || 0;
          this.summary.fail = result.errors ? result.errors.length : 0;
          
          // Cập nhật trạng thái trong preview data
          this._previewData.forEach((row, index) => {
            const error = result.errors?.find(e => e.row === index + 1);
            if (error) {
              row.hasError = true;
              row.errorMessage = error.message;
            } else {
              row.found = true;
            }
          });
          
          // Xử lý lỗi từng dòng
          this.errorsAll = [];
          if (result.errors && result.errors.length > 0) {
            this.errorsAll = result.errors.map((error, index) => ({
              excel_row: error.row,
              ho_ten: `${error.ho_dem || ''} ${error.ten || ''}`.trim(),
              messages: [error.message || 'Lỗi không xác định']
            }));
          }
          
          alert(`Cập nhật thành công: ${this.summary.ok} học sinh\nLỗi: ${this.summary.fail} dòng`);
        } else {
          alert('Lỗi: ' + (result.message || 'Có lỗi xảy ra'));
        }
      } catch (error) {
        console.error('Save error:', error);
        alert('Lỗi khi lưu dữ liệu: ' + (error.message || 'Lỗi không xác định'));
      } finally {
        // Đảm bảo saving được reset ngay cả khi có lỗi
        setTimeout(() => {
          this.saving = false;
        }, 100);
      }
    },

    resetData() {
      // Ngăn chặn reset khi đang loading/saving
      if (this.loading || this.saving) {
        return;
      }
      
      // Reset tất cả dữ liệu
      this._previewData = [];
      this.errorsAll = [];
      this.summary = { total: 0, ok: 0, fail: 0 };
      this.saving = false;
      this.loading = false;
      
      // Reset file input
      const fileInput = document.querySelector('input[type="file"]');
      if (fileInput) {
        fileInput.value = '';
      }
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
      XLSX.writeFile(wb, 'loi_hoc_sinh_mam_non.xlsx');
    }
  }
}
</script>

<!-- Include XLSX.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

@endsection