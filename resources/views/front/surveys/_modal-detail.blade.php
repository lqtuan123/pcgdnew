 
 <!-- _modal-detail.blade.php -->
<div
  x-data="detailUI()"
  x-init="
    // expose instance (tuỳ chọn)
    window.detailUIInstance = $data;

    // CHỐT: chụp context hiện tại vào biến thường (không phải $data)
    const ctx = $data;

    // đăng ký store dùng chung đúng cách
    if (!Alpine.store('detailModal')) {
      Alpine.store('detailModal', {
        openWith: (r) => { ctx.openWith(r) },
        close:    () => { ctx.close() }
      });
    }
  "
  x-cloak
>
 
 <template x-if="open">
    <div class="fixed inset-0 z-[60] bg-black/40 grid place-items-center" @keydown.escape.window="close()">
      <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl"
           @click.outside="close()">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="text-lg font-semibold" x-text="fullName(item)"></div>
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
          <button class="btn-primary" @click="close()">Đóng</button>
        </div>
      </div>
    </div>
  </template>
</div>