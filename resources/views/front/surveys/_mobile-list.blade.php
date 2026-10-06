<div class="md:hidden">
  @forelse($rows as $i => $r)
    <div class="rounded-xl border p-3 mb-2">
      <div class="flex items-start justify-between gap-3">
        <div>
          <button type="button" class="text-cyan-700 hover:underline"
                  @click.stop.prevent="openWith({{ Js::from($r) }})">
            {{ trim(($r['ho_dem'] ?? '').' '.($r['ten'] ?? '')) }}
          </button>
          <div class="mt-1 text-xs text-slate-500 space-x-2">
            <span>#{{ $rows->firstItem() + $i }}</span>
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

        <div x-data="{ open:false }" class="relative shrink-0">
          <button type="button" class="h-8 w-8 rounded-lg grid place-items-center hover:bg-slate-100"
                  @click="open=!open">⋮</button>

          <div x-cloak x-show="open" @click.outside="open=false"
               class="absolute right-0 top-full mt-1 w-40 rounded-xl border bg-white shadow-lg overflow-hidden z-10">
            <button type="button" class="w-full px-3 py-2 text-left text-red-600 hover:bg-red-50"
                    @click="open=false; $refs.delForm.submit()">Xoá</button>
            <form x-ref="delForm" method="POST" class="hidden">
              @csrf @method('DELETE')
            </form>
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="text-sm text-slate-600">Chưa có dữ liệu.</div>
  @endforelse
</div>
