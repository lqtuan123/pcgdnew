<form data-ajax method="POST" action="{{ route('front.surveyforms.members.store',$survey) }}" class="grid gap-3">
  @csrf
  <div>
    <label class="text-sm text-slate-600">Họ tên</label>
    <input name="name" class="w-full rounded-lg border px-3 py-2" required>
  </div>
  <div class="grid grid-cols-3 gap-2">
    <div><label class="text-sm">Ngày</label><input name="dob_day" class="w-full rounded-lg border px-3 py-2"></div>
    <div><label class="text-sm">Tháng</label><input name="dob_month" class="w-full rounded-lg border px-3 py-2"></div>
    <div><label class="text-sm">Năm</label><input name="dob_year" class="w-full rounded-lg border px-3 py-2"></div>
  </div>
   <div class="flex items-center justify-end gap-2 mt-2">
    <button type="button" class="btn-soft" data-close-modal>Đóng</button>  {{-- đổi từ onclick=... --}}
    <button class="btn-primary">Lưu</button>
  </div>
</form>
