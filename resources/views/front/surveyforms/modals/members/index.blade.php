{{-- resources/views/front/surveyforms/modals/members/index.blade.php --}}
<div class="flex items-center justify-between mb-3">
  <div class="font-medium">Thành viên hộ ({{ $rows->count() }})</div>
  <div class="flex gap-2">
    <button type="button"
      class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700"
      @click="$store.appModal.showFrom('{{ route('front.surveyforms.modal.members.create', $survey->id) }}', {title:'Thêm thành viên'})">
      Thêm thành viên
    </button>
    <button type="button" class="rounded-lg border px-3 py-2" data-close-modal>Đóng</button>
  </div>
</div>

<style>
  .th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb; white-space:nowrap}
  .td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9; vertical-align:top}
  .sticky-col{position:sticky; left:0; background:#fff; z-index:10}
  .sticky-col-2{position:sticky; left:3.5rem; background:#fff; z-index:10} /* ~56px cho cột STT */
</style>

<div class="-mx-2 overflow-x-auto">
  <style>
  /* cell base */
  .th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb;white-space:nowrap}
  .td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}

  /* màu nền BODY theo nhóm qua colgroup */
  .m-col-g1{background:#fff7ed}  /* amber-50 */
  .m-col-g2{background:#f0f9ff}  /* sky-50  */
  .m-col-g3{background:#eef2ff}  /* indigo-50 */
  .m-col-g4{background:#fefce8}  /* yellow-50 */
  .m-col-g5{background:#faf5ff}  /* violet-50 */
  .m-col-g6{background:#ecfeff}  /* cyan-50  */
  .m-col-g7{background:#f0fdf4}  /* green-50 */
  .m-col-stt,.m-col-act{background:#ffffff}

  /* header nhóm (hàng 1) – đậm hơn chút */
  .ghead{color:#1f2937}
  .ghead.g1{background:#fde68a}  /* amber-300  */
  .ghead.g2{background:#7dd3fc}  /* sky-300    */
  .ghead.g3{background:#c7d2fe}  /* indigo-300 */
  .ghead.g4{background:#fde047}  /* yellow-300 */
  .ghead.g5{background:#d8b4fe}  /* violet-300 */
  .ghead.g6{background:#a5f3fc}  /* cyan-300   */
  .ghead.g7{background:#86efac}  /* green-300  */

  /* header chi tiết (hàng 2) – nhạt cùng tông */
  thead th.g1{background:#fff7ed}
  thead th.g2{background:#f0f9ff}
  thead th.g3{background:#eef2ff}
  thead th.g4{background:#fefce8}
  thead th.g5{background:#faf5ff}
  thead th.g6{background:#ecfeff}
  thead th.g7{background:#f0fdf4}

  /* vạch phân nhóm cho BODY (đặt viền trái vào cột đầu nhóm) */
 .members tbody td:nth-child(2),
.members tbody td:nth-child(8),
.members tbody td:nth-child(12),
.members tbody td:nth-child(17),
.members tbody td:nth-child(20),
.members tbody td:nth-child(21),
.members tbody td:nth-child(23){
  box-shadow: inset 1px 0 0 #e2e8f0;
}

  /* hover hàng */
  .members tbody tr:hover td{background:#f8fafc}
</style>

<table class="members min-w-[1400px] w-full text-sm">
  {{-- tô màu body theo nhóm qua colgroup (không ảnh hưởng thead) --}}
  <colgroup>
    <col class="m-col-stt">            {{-- # --}}
    <col span="6" class="m-col-g1">    {{-- đối tượng --}}
    <col span="4" class="m-col-g2">    {{-- nơi đang ở --}}
    <col span="5" class="m-col-g3">    {{-- học tập --}}
    <col span="3" class="m-col-g4">    {{-- xóa mù --}}
    <col span="1" class="m-col-g5">    {{-- khuyết tật --}}
    <col span="2" class="m-col-g6">    {{-- hoàn cảnh --}}
    <col span="2" class="m-col-g7">    {{-- liên lạc --}}
    <col class="m-col-act">            {{-- actions --}}
  </colgroup>

  <thead>
    {{-- Hàng nhóm --}}
    <tr>
      <th class="th sticky left-0 z-20 bg-slate-50 w-14" rowspan="2">#</th>
      <th class="th ghead g1" colspan="6">Thông tin đối tượng</th>
      <th class="th ghead g2" colspan="4">Thông tin nơi đang ở</th>
      <th class="th ghead g3" colspan="5">Thông tin học tập</th>
      <th class="th ghead g4" colspan="3">Xóa mù chữ</th>
      <th class="th ghead g5" colspan="1">Khuyết tật</th>
      <th class="th ghead g6" colspan="2">Hoàn cảnh</th>
      <th class="th ghead g7" colspan="2">Thông tin liên lạc</th>
      
      <th class="th" rowspan="2"></th>
    </tr>

    {{-- Hàng tiêu đề cột chi tiết --}}
    <tr>
      {{-- Đối tượng --}}
      <th class="th g1">Họ và tên</th>
      <th class="th g1">CCCD</th>
      <th class="th g1">Địa bàn</th>
      <th class="th g1">Ngày sinh</th>
       <th class="th g1">Giới tính</th>
      <th class="th g1">Diện ưu tiên</th>
     
      
      {{-- Nơi ở --}}
      <th class="th g2">Quan hệ</th>
      <th class="th g2">Địa chỉ</th>
      <th class="th g2">Diện cư trú</th>
      <th class="th g2">Tình trạng</th>
      {{-- Học tập --}}
      <th class="th g3">Khối/Lớp</th>
      <th class="th g3">Trường (mã)</th>
      <th class="th g3">Quận/Huyện học</th>
      <th class="th g3">TN PT</th>
      <th class="th g3">TN nghề</th>
      {{-- Xóa mù --}}
      <th class="th g4">Đang học</th>
      <th class="th g4">Hoàn thành</th>
      <th class="th g4">Tái mù</th>
      {{-- Khuyết tật --}}
      <th class="th g5">Danh mục</th>
      {{-- Hoàn cảnh --}}
      <th class="th g6">Diện ƯT/HC</th>
      <th class="th g6">Chi tiết</th>
      {{-- Liên lạc --}}
      <th class="th g7">Phụ huynh</th>
      <th class="th g7">Điện thoại</th>
    </tr>
  </thead>

  <tbody>
     
    @forelse($rows as $i => $r)
      <tr class="hover:bg-slate-50">
        {{-- STT (sticky) --}}
        <td class="td sticky left-0 z-10 bg-white w-14 tabular-nums">{{ $i+1 }}</td>

        {{-- Đối tượng --}}
        <td class="td font-medium">{{ $r['full_name'] ?? '—' }}</td>
        <td class="td">{{ $r['identity'] ?: '—' }}</td>
        <td class="td">{{ $r['geo_text'] ?: '—' }}</td>
        <td class="td tabular-nums">{{ $r['dob_text'] ?: '—' }}</td>
        <td class="td">{{ $r['gender'] ?: '—' }}</td>
        <td class="td">{{ $r['priority_category'] ?: '—' }}</td>
        {{-- Nơi ở --}}
        <td class="td">{{ $r['role'] ?: '—' }}</td>
        <td class="td">{{ $r['addressline'] ?: '—' }}</td>
        <td class="td">{{ $r['res_type'] ?: '—' }}</td>
        <td class="td">{{ $r['res_status'] ?: '—' }}</td>

        {{-- Học tập --}}
        <td class="td">
          {{ $r['block'] ?: '—' }}
          @if(!empty($r['class_label'])) • {{ $r['class_label'] }} @endif
        </td>
        <td class="td">
          {{ $r['school_name'] ?: '—' }}
          @if(!empty($r['school_code'])) ({{ $r['school_code'] }}) @endif
        </td>
        <td class="td">{{ $r['studying_district'] ?: '—' }}</td>
        <td class="td">
          @if(!empty($r['grad_pt']) || !empty($r['grad_year']))
            {{ $r['grad_pt'] ?: '—' }} @if(!empty($r['grad_year'])) ({{ $r['grad_year'] }}) @endif
          @else — @endif
        </td>
        <td class="td">
          @if(!empty($r['voc_level']) || !empty($r['voc_year']))
            {{ $r['voc_level'] ?: '—' }} @if(!empty($r['voc_year'])) ({{ $r['voc_year'] }}) @endif
          @else — @endif
        </td>

        {{-- Xóa mù --}}
        <td class="td">{{ $r['lit_class'] ?: '—' }}</td>
        <td class="td">{{ $r['lit_done'] ?: '—' }}</td>
        <td class="td">{{ $r['lit_level'] ?: '—' }}</td>

        {{-- Khuyết tật --}}
        <td class="td">{{ $r['disabilities'] ?: '—' }}</td>

        {{-- Hoàn cảnh --}}
        <td class="td">{{ $r['special_name'] ?: '—' }}</td>
        <td class="td">{{ $r['special_detail'] ?: '—' }}</td>

        {{-- Liên lạc --}}
        <td class="td">{{ $r['contact_name'] ?: '—' }}</td>
        <td class="td">{{ $r['contact_phone'] ?: '—' }}</td>

        {{-- Hành động --}}
        <td class="td">
          <div class="flex gap-1">
            <button type="button"
              class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
              @click="$store.appModal.showFrom('{{ route('front.surveyforms.modal.members.edit', $r['detail_id']) }}',{title:'Điều chỉnh thành viên'})"
              title="Điều chỉnh">✏️</button>

            <button type="button"
                class="h-8 w-8 grid place-items-center rounded-lg hover:bg-rose-50 text-rose-600"
                @click="
                  if (confirm('Xoá mềm thành viên này?')) {
                    fetch('{{ route('front.surveyforms.members.destroy', [$survey->id, $r['detail_id']]) }}', {
                      method: 'DELETE',
                      headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
                    })
                    .then(r => r.json())
                    .then(j => {
                      if (j.ok) {
                         $store.appModal.showFrom($store.appModal.lastUrl, { title:'Danh sách thành viên' });
                      } else {
                        alert(j.message || 'Không xoá được.');
                      }
                    });
                  }"
                title="Xoá">🗑️</button>
          </div>
        </td>
      </tr>
    @empty
      <tr><td class="td text-center text-slate-500" colspan="24">Chưa có thành viên.</td></tr>
    @endforelse
  </tbody>
</table>
</div>
