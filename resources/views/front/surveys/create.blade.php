@extends('layouts.front')
@section('title','Tạo phiếu điều tra (nhập tay 1 dòng)')

@section('content')
<style>[x-cloak]{display:none!important}.th{padding:.5rem .75rem;font-weight:600}</style>
@php
  $base  = $defaultYear ?? \App\Models\SurveyForm::currentAcademicYear();
  [$y1,$y2] = explode('-', $base);
  $years = [($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
@endphp

<div x-data="manualCreateUI()" x-init="boot()" class="mx-auto max-w-6xl px-4 py-6">

  <div class="card">
    <h1 class="text-lg font-semibold">Tạo mới phiếu điều tra</h1>
    <p class="text-sm text-slate-600 mt-1">Nhập tay 1 dòng theo đúng mẫu 50 cột (giống Import). Khi bấm Lưu, dữ liệu sẽ đi qua pipeline Import.</p>
  </div>

  <form x-ref="form" method="POST" action="{{ route('front.surveys.store') }}" class="mt-3 grid gap-4 md:grid-cols-12">
    @csrf

    {{-- Năm học --}}
    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select name="academic_year" x-model="academicYear" class="w-full rounded-lg border px-3 py-2">
        @foreach($years as $yy)
          <option value="{{ $yy }}">{{ $yy }}</option>
        @endforeach
      </select>
    </div>

    {{-- GEO picker tái dùng --}}
    @include('partials.front.geo-filter', [
      'wrapperClass' => 'md:col-span-12 grid gap-3 md:grid-cols-12',
      'namePrefix'   => 'geo',
      'scheme'       => old('scheme', request('scheme','old')),
      'provinceId'   => old('geo.province_id', request('geo.province_id')),
      'districtId'   => old('geo.district_id', request('geo.district_id')),
      'wardId'       => old('geo.ward_id', request('geo.ward_id')),
      'villageId'    => old('geo.village_id', request('geo.village_id')),
      'showHamlet'   => true,
    ])

    {{-- ===================== 50 cột ===================== --}}
    <div class="md:col-span-12 grid gap-4">

      {{-- Thông tin đối tượng --}}
      <div class="rounded-xl border p-4">
        <div class="th">Thông tin đối tượng</div>
        <div class="mt-3 grid gap-3 md:grid-cols-4">
           <div>
            <label class="block text-xs text-slate-500 mb-1">Số căn cước</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.identity_number">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Họ đệm</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.ho_dem">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Tên <span class="text-red-500">*</span></label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.ten" required>
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Ngày sinh</label>
            <input type="number" min="1" max="31" class="w-full rounded-lg border px-3 py-2" x-model="row.dob_day">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Tháng sinh</label>
            <input type="number" min="1" max="12" class="w-full rounded-lg border px-3 py-2" x-model="row.dob_month">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Năm sinh</label>
            <input type="number" min="1900" max="2100" class="w-full rounded-lg border px-3 py-2" x-model="row.dob_year">
          </div>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" class="accent-cyan-600" x-model="row.is_female">
            <span>Giới tính (Nữ)</span>
          </label>
          {{-- Dân tộc --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Dân tộc</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model="row.ethnicity">
              <option value="">— Chọn dân tộc —</option>
              @foreach($ethnicities as $e)
                <option value="{{ $e->name }}">
                  {{ $e->name }}@if($e->code) — {{ $e->code }}@endif
                </option>
              @endforeach
            </select>
          </div>

          {{-- Tôn giáo --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Tôn giáo</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model="row.religion">
              <option value="">— Chọn tôn giáo —</option>
              @foreach($religions as $r)
                <option value="{{ $r->name }}">
                  {{ $r->name }}@if($r->code) — {{ $r->code }}@endif
                </option>
              @endforeach
            </select>
          </div>
          {{-- Diện ưu tiên --}}
          <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Diện ưu tiên</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model="row.priority_name">
              <option value="">— Chọn diện ưu tiên —</option>
              @foreach($priorities as $p)
                <option value="{{ $p->name }}">{{ $p->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Thông tin nơi đang ở --}}
      <div class="rounded-xl border p-4">
        <div class="th">Thông tin nơi đang ở</div>
        <div class="mt-3 grid gap-3 md:grid-cols-4">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Chủ hộ - Họ đệm</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.chuho_ho_dem">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Chủ hộ - Tên</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.chuho_ten">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Địa chỉ</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.addressline">
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Số phiếu <span class="text-red-500">*</span></label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.so_phieu" required placeholder="VD: AC001">
          </div>
          {{-- Diện cư trú (ResidenceType) --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Diện cư trú</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    x-model="row.residence_type_name">
              <option value="">— Chọn diện cư trú —</option>
              @foreach($residenceTypes as $t)
                <option value="{{ $t->name }}">
                  {{ $t->name }}@if($t->code) ({{ $t->code }}) @endif
                </option>
              @endforeach
            </select>
          </div>
          {{-- Tình trạng cư trú (ResidenceStatus) --}}
          <div class="md:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Tình trạng cư trú</label>
            <select class="w-full rounded-lg border px-3 py-2"
                    x-model="row.residence_status_name">
              <option value="">— Chọn tình trạng —</option>
              @foreach($residenceStatuses as $s)
                <option value="{{ $s->name }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Thông tin học tập --}}
      <div class="rounded-xl border p-4">
        <div class="th">Thông tin học tập</div>

        {{-- Chọn trường (có geo riêng) --}}
        <div>
          @include('partials.front.school-picker', [
            'inputName' => 'education[school_id]',
            'withGeo'   => true,
            'scheme'    => old('scheme','new'),
            'geo'       => [
              'province_id' => old('geo_school.province_id'),
              'district_id' => old('geo_school.district_id'),
              'ward_id'     => old('geo_school.ward_id'),
            ],
            'mirror'    => ['code'=>'education[school_code]','name'=>'education[school_name]'],
          ])
        </div>

        <div class="mt-3 grid gap-3 md:grid-cols-4">
          {{-- Khối học (EducationBlock) --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Khối học</label>
            <select name="education[education_block_id]" class="w-full rounded-lg border px-3 py-2">
              <option value="">— Chọn khối học —</option>
              @foreach($blocks as $b)
                <option value="{{ $b->id }}" @selected(old('education.education_block_id') == $b->id)>{{ $b->name }}</option>
              @endforeach
            </select>
            @error('education.education_block_id')
              <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1">Lớp học</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.education_grade_name">
          </div>

          {{-- Bậc tốt nghiệp PT --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Bậc TN Phổ thông</label>
            <select name="education[graduation_level_id]" class="w-full rounded-lg border px-3 py-2">
              <option value="">— Chọn bậc TN PT —</option>
              @foreach($gradLevels as $gl)
                <option value="{{ $gl->id }}" @selected(old('education.graduation_level_id') == $gl->id)>{{ $gl->name }}</option>
              @endforeach
            </select>
            @error('education.graduation_level_id')
              <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
          </div>

          <label class="inline-flex items-center gap-2">
            <input type="checkbox" class="accent-cyan-600" x-model="row.is_bo_tuc">
            <span>Bổ túc</span>
          </label>

          <div>
            <label class="block text-xs text-slate-500 mb-1">Năm TN PT</label>
            <input type="text" class="w-full rounded-lg border px-3 py-2" x-model="row.graduation_year" placeholder="2023-2024">
          </div>

          {{-- Bậc tốt nghiệp Nghề --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Bậc TN Nghề</label>
            <select name="education[vocational_level_id]" class="w-full rounded-lg border px-3 py-2">
              <option value="">— Chọn bậc TN nghề —</option>
              @foreach($vocLevels as $vl)
                <option value="{{ $vl->id }}" @selected(old('education.vocational_level_id') == $vl->id)>{{ $vl->name }}</option>
              @endforeach
            </select>
            @error('education.vocational_level_id')
              <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1">Số bằng TN</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.vocational_grad_cert_no">
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1">Năm TN nghề</label>
            <input type="text" class="w-full rounded-lg border px-3 py-2" x-model="row.vocational_grad_year" placeholder="2023-2024">
          </div>

          
          {{-- Học xong lớp (select từ EducationGrade -> map ra số lớp) --}}
          <div>
            <label class="block text-xs text-slate-500 mb-1">Học xong lớp</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model.number="row.hoc_xong_grade_id">
              <option value="">— Chọn lớp —</option>
              <template x-for="g in grades" :key="g.id">
                <option :value="g.id" x-text="g.code"></option>
              </template>
            </select>
            {{-- nếu bạn muốn đồng thời post ID ra form (không cần cho pipeline rows) --}}
            <input type="hidden" name="hoc_xong_grade_id" :value="row.hoc_xong_grade_id">
          </div>


          <div>
            <label class="block text-xs text-slate-500 mb-1">Học xong năm</label>
            <input type="text" class="w-full rounded-lg border px-3 py-2" x-model="row.hoc_xong_nam" placeholder="2023-2024">
          </div>
          {{-- Bỏ học lớp (select từ EducationGrade -> map ra số lớp) --}}
         <div>
            <label class="block text-xs text-slate-500 mb-1">Bỏ học lớp</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model.number="row.bo_hoc_grade_id">
              <option value="">— Chọn lớp —</option>
              <template x-for="g in grades" :key="g.id">
                <option :value="g.id" x-text="g.code"></option>
              </template>
            </select>
            {{-- nếu bạn muốn đồng thời post ID ra form (không cần cho pipeline rows) --}}
            <input type="hidden" name="bo_hoc_grade_id" :value="row.bo_hoc_grade_id">
          </div>
         
          <div>
            <label class="block text-xs text-slate-500 mb-1">Bỏ học năm</label>
            <input type="text" class="w-full rounded-lg border px-3 py-2" x-model="row.bo_hoc_nam" placeholder="2023-2024">
          </div>
        </div>
      </div>

      {{-- Xóa mù chữ --}}
      <div class="rounded-xl border p-4">
        <div class="th">Xóa mù chữ</div>
        <div class="mt-3 grid gap-3 md:grid-cols-4">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Đang học lớp</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.lit_dang_hoc_lop">
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1">Hoàn thành lớp</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model.number="row.lit_hoan_thanh_lop">
              <option value="">— Chọn mức —</option>
              <option :value="3">3</option>
              <option :value="5">5</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-slate-500 mb-1">Tái mù mức</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model.number="row.lit_tai_mu_muc">
              <option value="">— Chọn mức —</option>
              <option :value="1">1</option>
              <option :value="2">2</option>
            </select>
          </div>
        </div>
      </div>

      {{-- Khuyết tật --}}
      <div class="rounded-xl border p-4">
        <div class="th">Khuyết tật</div>
        <div class="mt-3 grid gap-3 md:grid-cols-5">
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_VAN_DONG"><span>Vận động</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_NGHE_NOI"><span>Nghe/Nói</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_NHIN"><span>Nhìn</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_THAN_KINH"><span>Thần kinh</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_TRI_TUE"><span>Trí tuệ</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_HOC_TAP"><span>Học tập</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_TU_KY"><span>Tự kỷ</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_KHAC"><span>Khác</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_has_certificate"><span>Có chứng nhận</span></label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" class="accent-cyan-600" x-model="row.dis_can_learn"><span>Có thể học</span></label>
        </div>
      </div>

      {{-- Hoàn cảnh & Liên lạc --}}
      <div class="rounded-xl border p-4">
        <div class="th">Hoàn cảnh & Liên lạc</div>
        <div class="mt-3 grid gap-3 md:grid-cols-4">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Hoàn cảnh ĐB</label>
            <select class="w-full rounded-lg border px-3 py-2" x-model="row.sc_name">
              <option value="">— Chọn hoàn cảnh —</option>
              @foreach($specials as $s)
                <option value="{{ $s->name }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="md:col-span-3"><label class="block text-xs text-slate-500 mb-1">Chi tiết HC</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.sc_detail"></div>
          <div><label class="block text-xs text-slate-500 mb-1">Quan hệ với chủ hộ</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.relationship_name"></div>
          <div><label class="block text-xs text-slate-500 mb-1">Họ tên (Cha/Mẹ)</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.contact_name"></div>
          <div><label class="block text-xs text-slate-500 mb-1">Điện thoại</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.contact_phone"></div>
          <div class="md:col-span-2"><label class="block text-xs text-slate-500 mb-1">Ghi chú</label>
            <input class="w-full rounded-lg border px-3 py-2" x-model="row.contact_note"></div>
        </div>
      </div>
    </div>
    {{-- =================== /50 cột =================== --}}

    {{-- rows JSON cho pipeline import --}}
    <textarea name="rows" x-ref="rows" class="hidden"></textarea>

    <div class="md:col-span-12 flex items-center gap-2">
      <button type="button" class="btn-primary" @click="submit()">Lưu</button>
      <a href="{{ route('front.surveys.index') }}" class="btn-soft">Huỷ</a>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  // danh sách lớp cho Alpine
   // đẩy thẳng JSON ra JS, tránh Blade đếm ngoặc
  window.EDU_GRADES = {!! $grades->map(function($g){
      return [
        'id'    => $g->id,
        'code'  => $g->code,
        'name'  => $g->name,
        'label' => trim($g->name . ($g->code ? ' ('.$g->code.')' : '')),
      ];
  })->values()->toJson() !!};
 </script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('manualCreateUI', () => ({
    academicYear: @json($base),
    grades: window.EDU_GRADES || [],
    row: { identity_number:'',
      ho_dem:'', ten:'', dob_day:'', dob_month:'', dob_year:'', is_female:false,
      ethnicity:'', religion:'', priority_name:'',
      chuho_ho_dem:'', chuho_ten:'', addressline:'', so_phieu:'',
      residence_type_name:'', residence_status_name:'',
      education_block_code:'',            // sẽ set = text option khối học
      education_grade_name:'', studying_district:'',
      school_name:'', school_code:'',     // sẽ set từ school-picker
      graduation_level_name:'',           // sẽ set = text option
      is_bo_tuc:false, graduation_year:'',
      vocational_level_name:'',           // sẽ set = text option
      vocational_grad_cert_no:'', vocational_grad_year:'',
      hoc_xong_lop:'', hoc_xong_nam:'', bo_hoc_lop:'', bo_hoc_nam:'',
      lit_dang_hoc_lop:'', lit_hoan_thanh_lop:'', lit_tai_mu_muc:'',
      dis_VAN_DONG:false, dis_NGHE_NOI:false, dis_NHIN:false, dis_THAN_KINH:false,
      dis_TRI_TUE:false, dis_HOC_TAP:false, dis_TU_KY:false, dis_KHAC:false,
      dis_has_certificate:false, dis_can_learn:false,
      sc_name:'', sc_detail:'', relationship_name:'', contact_name:'', contact_phone:'', contact_note:'',

      // chọn theo ID để map ra số lớp
      bo_hoc_grade_id:'', hoc_xong_grade_id:'',
    },

    gradeNumberFrom(g){
      if(!g) return null;
      const s = String(g.code || g.name || '');
      const m = s.match(/(\d{1,2})\s*$/);
      return m ? parseInt(m[1],10) : null;
    },

    boot(){
      this.$watch('row.bo_hoc_grade_id', (id) => {
        const g = this.grades.find(x => String(x.id)===String(id));
        this.row.bo_hoc_lop = this.gradeNumberFrom(g) ?? '';
      });
      this.$watch('row.hoc_xong_grade_id', (id) => {
        const g = this.grades.find(x => String(x.id)===String(id));
        this.row.hoc_xong_lop = this.gradeNumberFrom(g) ?? '';
      });
    },

    normalize(){
      const ints = [
        'dob_day','dob_month','dob_year','graduation_year','vocational_grad_year',
        'hoc_xong_lop','hoc_xong_nam','bo_hoc_lop','bo_hoc_nam','lit_hoan_thanh_lop','lit_tai_mu_muc'
      ];
      ints.forEach(k => {
        const v = parseInt(this.row[k],10);
        this.row[k] = Number.isFinite(v) ? v : '';
      });
    },

    submit(){
      if(!this.row.so_phieu){ alert('Vui lòng nhập Số phiếu'); return; }

      // helpers đọc DOM theo name
      const valOf  = (sel) => (this.$root.querySelector(sel)?.value ?? '').trim();
      const textOf = (sel) => {
        const el = this.$root.querySelector(sel);
        if (!el) return '';
        const i = el.selectedIndex;
        return (i >= 0 ? el.options[i].text.trim() : '');
      };

      this.$nextTick(() => {
        // 1) Mirror từ school-picker (hidden inputs do partial tạo ra)
        this.row.school_code = valOf('[name="education[school_code]"]');
        this.row.school_name = valOf('[name="education[school_name]"]');

        // 2) Khối học / Bậc TN PT / Bậc TN Nghề: dùng TEXT hiển thị để feed vào cột import
        this.row.education_block_code   = textOf('select[name="education[education_block_id]"]'); // cột "Khối học"
        this.row.graduation_level_name  = textOf('select[name="education[graduation_level_id]"]');
        this.row.vocational_level_name  = textOf('select[name="education[vocational_level_id]"]');

        // 3) Chuẩn hoá số & serialize
        this.normalize();
        this.$refs.rows.value = JSON.stringify([this.row]);
        this.$refs.form.submit();
      });
    },
  }));
});
</script>

@endpush
