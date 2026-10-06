@extends('layouts.front')
@section('title','Danh sách phiếu điều tra')

@section('content')
<style>
  .th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb}
  .td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}
</style>

<div x-data="bulkSelect()"    @open-detail.window="if ($store.detailModal) { $store.detailModal.openWith($event.detail) }"
     @soft-delete-one.window="if (window.detailUIInstance?.softDelete) { detailUIInstance.softDelete($event.detail) }"
     class="mx-auto max-w-7xl px-4 py-6">

  {{-- Thanh tiêu đề + nút --}}
  @include('front.surveys._action-bar', [
    'exportUrl' => route('front.surveys.export', request()->query())
  ])

  {{-- Form lọc (gọi geo-filter ở trong) --}}
  @include('front.surveys._filter-form', [
    'defaultYear' => $defaultYear ?? \App\Models\SurveyForm::currentAcademicYear(),
    'year'        => $year,
    'soPhieu'     => $soPhieu,
    'q'           => $q,
    'minAge'      => $minAge,
    'maxAge'      => $maxAge,
    'minority'    => $minority,
    // tham số cho geo-filter:
    'geo'         => [
      'scheme'     => request('scheme','old'),
      'provinceId' => request('geo.province_id'),
      'districtId' => request('geo.district_id'),
      'wardId'     => request('geo.ward_id'),
      'villageId'  => request('geo.village_id'),
      'showHamlet' => true,
      'wrapperClass' => 'md:col-span-8 grid md:grid-cols-12 gap-3'
    ],
  ])

  {{-- Thanh thao tác Bulk --}}
  @include('front.surveys._bulk-toolbar', [
    'rows' => $rows,
    'softUrl' => route('front.surveys.bulk_soft_delete'),
    'hardUrl' => route('front.surveys.bulk_hard_delete'),
  ])

  {{-- Bảng desktop --}}
  @include('front.surveys._desktop-table', [
    'rows'         => $rows,
    'groups'       => $groups,
    'columns'      => $columns,
    'sortableKeys' => $sortableKeys ?? [],
  ])

  {{-- Danh sách mobile --}}
  @include('front.surveys._mobile-list', [
    'rows' => $rows,
  ])

  {{-- Modal chi tiết dùng chung --}}
  @include('front.surveys._modal-detail')

</div>

{{-- MODAL AJAX DÙNG CHUNG --}}
<div x-data
     class="fixed inset-0 z-[70] grid place-items-center bg-black/40"
     x-show="$store.appModal?.open"
     x-cloak
     @click.self.stop="$store.appModal.onBackdrop()"
     @keydown.escape.window="$store.appModal.onEsc()"  data-ajax 
     role="dialog" aria-modal="true">
  <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white shadow-2xl" @click.stop>
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 id="modal-title" class="font-medium" x-text="$store.appModal.title"></h3>
      <button class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
              @click="$store.appModal.close()">✕</button>
    </div>
    <div class="p-4">
      <template x-if="$store.appModal.loading"><div class="text-sm text-slate-500">Đang tải…</div></template>
      <div id="app-modal-body"></div>
    </div>
  </div>
</div>

@endsection

@push('scripts')

 <script>
  // VD route: /dieu-tra/edit/{survey}
  const EDIT_URL_BASE = @json(url('dieu-tra/edit'));


</script>
<script>
document.addEventListener('alpine:init', () => {
  try { if (Alpine.store('appModal')) return; } catch(e){}
  Alpine.store('appModal', {
    open:false, loading:false, title:'',
    backdropClosable:false, escClosable:true,
    onBackdrop(){ if (this.backdropClosable) this.close(); },
    onEsc(){ if (this.escClosable) this.close(); },

    async showFrom(url, {title=''}={}) {
      this.open = true; this.loading = true; this.title = title;
      try {
        const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        const html = await res.text();
        this.setHtml(html);
      } catch {
        this.setHtml('<div class="text-red-600">Không tải được nội dung.</div>');
      } finally { this.loading = false; }
    },

    setHtml(html){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      if (window.Alpine && Alpine.destroyTree) Alpine.destroyTree(body);
      body.innerHTML = html;
      if (window.Alpine && Alpine.initTree) Alpine.initTree(body);
      const af = body.querySelector('[autofocus]') || body.querySelector('button, input, select, textarea, [tabindex]');
      if (af) af.focus({preventScroll:true});
      // bind close button inside modal content
      body.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => { e.preventDefault(); this.close(); });
      });
      
      this.bindAjaxForms(); // NEW 👈 intercept các form data-ajax trong nội dung vừa chèn
    },
  // NEW: intercept form submit trong modal
    bindAjaxForms(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;

      body.querySelectorAll('form[data-ajax]').forEach(form => {
        // tránh gắn lặp lại nhiều lần
        if (form._ajaxBound) return;
        form._ajaxBound = true;

        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();

          const method = (form.method || 'POST').toUpperCase();
          const action = form.action || location.href;
          const fd = new FormData(form);
          // nếu muốn truyền lại URL hiện tại để reload đúng trang
          if (!fd.has('redirect')) fd.append('redirect', location.href);

          try {
            const res = await fetch(action, {
              method,
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
              },
              body: fd
            });

            const ct = res.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
              const data = await res.json();
              if (data.ok) {
                this.close();
                if (data.redirect) location.href = data.redirect;
                else location.reload();
                return;
              }
              // server trả lại form HTML trong khóa html => render lại form (hiển thị lỗi)
              if (data.html) { this.setHtml(data.html); return; }
              // thông báo lỗi chung
              this.setHtml(`<div class="text-red-600">${data.message || 'Không lưu được.'}</div>`);
              return;
            }

            // Fallback: server trả HTML (form có lỗi validate…) -> render lại
            const html = await res.text();
            this.setHtml(html);

          } catch (e) {
            this.setHtml('<div class="text-red-600">Lỗi mạng hoặc máy chủ.</div>');
          }
        }, { passive:false });
      });
    },

     
    close(){ this.open=false; this.title=''; const b=document.getElementById('app-modal-body'); if (b) b.innerHTML=''; }
  });
});
</script>


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
     // Có dữ liệu địa bàn cũ/mới?
    hasOld(r){ return !!(r?.old_province_name || r?.old_district_name || r?.old_ward_name || r?.village_name); },
    hasNew(r){ return !!(r?.new_province_name || r?.new_ward_name || r?.village_name_new); /* nếu có thôn mới */ },

    // Chuỗi địa bàn cũ: Tỉnh · Huyện · Xã · Thôn
    locOld(r){
      if(!r) return '';
      const parts = [
        r.old_province_name,
        r.old_district_name,
        r.old_ward_name,
        r.village_name,          // thôn/ấp (cũ)
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    // Chuỗi địa bàn mới: Tỉnh · Xã · (Thôn nếu có)
    locNew(r){
      if(!r) return '';
      const parts = [
        r.new_province_name,
        r.new_ward_name,
        r.village_name_new       // nếu có trường thôn/ấp mới, không có thì bỏ
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

    // Hàm xóa tạm đối tượng
    softDelete(id) {
      console.log('softDelete called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID đối tượng: ' + id);
        return;
      }

      const message = 'Bạn có muốn xóa tạm đối tượng?\nĐể phục hồi đối tượng vào Điều tra -> Danh sách xóa tạm.';
      
      if (confirm(message)) {
        console.log('User confirmed, sending request to:', `/doituong-dieutra/${id}/soft-delete`);
        
        // Gửi request xóa tạm
        fetch(`/doituong-dieutra/${id}/soft-delete`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          console.log('Response data:', data);
          if (data.success) {
            alert('Đã xóa tạm đối tượng thành công');
            // Reload trang để cập nhật danh sách
            window.location.reload();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa tạm đối tượng'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Lỗi kết nối: Không thể xóa tạm đối tượng');
        });
      }
    },
  }
}

/** ========== BULK SELECT ========== */
function bulkSelect(){
  return {
    selectedCount: 0,
    toggleAll(ev){
      const checked = ev.target.checked;
      document.querySelectorAll('input.row-checkbox').forEach(cb => { cb.checked = checked; });
      this.recount();
    },
    onRowToggle(){ this.recount(); },
    recount(){ this.selectedCount = document.querySelectorAll('input.row-checkbox:checked').length; },
    confirmBulkDelete(){
      if (this.selectedCount === 0) {
        alert('Vui lòng chọn ít nhất một đối tượng để xóa tạm.');
        return;
      }
      
      const message = `Bạn chắc chắn muốn xóa tạm ${this.selectedCount} đối tượng đã chọn?\n\nĐể phục hồi các đối tượng vào Điều tra -> Danh sách xóa tạm.`;
      
      if (confirm(message)) {
        // Thêm IDs vào form trước khi submit
        const form = this.$refs.bulkSelForm;
        const selectedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
        const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        // Xóa các input cũ
        form.querySelectorAll('input[name="ids[]"]').forEach(input => input.remove());
        
        // Thêm các input mới
        ids.forEach(id => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = id;
          form.appendChild(input);
        });
        
        console.log('Submitting bulk soft delete with IDs:', ids);
        form.submit();
      }
    },
    confirmBulkHardDelete(){
      if (this.selectedCount === 0) {
        alert('Vui lòng chọn ít nhất một đối tượng để xóa thật.');
        return;
      }
      
      const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT ${this.selectedCount} đối tượng đã chọn?\n\nHành động này sẽ xóa vĩnh viễn các đối tượng khỏi dữ liệu và KHÔNG THỂ PHỤC HỒI!`;
      
      if (confirm(message)) {
        // Thêm IDs vào form trước khi submit
        const form = this.$refs.bulkHardDeleteForm;
        const selectedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
        const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        // Xóa các input cũ
        form.querySelectorAll('input[name="ids[]"]').forEach(input => input.remove());
        
        // Thêm các input mới
        ids.forEach(id => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = id;
          form.appendChild(input);
        });
        
        console.log('Submitting bulk hard delete with IDs:', ids);
        form.submit();
      }
    }
  }
}
</script>
@endpush
