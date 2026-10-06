<div class="hidden md:block">
  <div class="-mx-4 overflow-x-auto">
    <table class="min-w-[1600px]">
      <thead>
        <tr class="bg-slate-100">
          <th class="th sticky left-0 z-30 bg-slate-100 w-10" rowspan="2">
            <input type="checkbox" @change="toggleAll($event)" aria-label="Chọn tất cả">
          </th>
          <th class="th sticky left-10 z-30 bg-slate-100 w-10" rowspan="2"></th>
          <th class="th sticky left-20 z-20 bg-slate-100 w-12 border-slate-600" rowspan="2">#</th>

          @php $groupIdx = 0; @endphp
          @foreach($groups as $g)
            @php
              $groupIdx++;
              $bgMap = ['bg-amber-50','bg-sky-50','bg-emerald-50','bg-rose-50','bg-violet-50','bg-orange-50','bg-slate-50'];
              $bg = $bgMap[$groupIdx % count($bgMap)];
            @endphp
            <th class="th text-center z-20 {{ $bg }}" colspan="{{ count($g['keys']) }}">
              {{ $g['title'] }}
            </th>
          @endforeach
        </tr>

        <tr class="bg-slate-50">
          @foreach($groups as $g)
            @foreach($g['keys'] as $k)
              @php
                $isSortable = in_array($k, $sortableKeys, true);
                $isActive   = request('sort') === $k;
                $dir        = request('dir','asc') === 'desc' ? 'desc' : 'asc';
                $nextDir    = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
                $arrow      = $isActive ? ($dir === 'asc' ? '▲' : '▼') : '↕';
                $label      = $columns[$k] ?? $k;
              @endphp
              <th class="th text-left">
                @if($isSortable)
                  <a href="{{ request()->fullUrlWithQuery(['sort'=>$k,'dir'=>$nextDir]) }}"
                     class="inline-flex items-center gap-1 hover:underline"
                     aria-sort="{{ $isActive ? ($dir==='asc'?'ascending':'descending') : 'none' }}">
                    <span>{{ $label }}</span><span class="text-slate-400 text-xs">{{ $arrow }}</span>
                  </a>
                @else
                  {{ $label }}
                @endif
              </th>
            @endforeach
          @endforeach
        </tr>
      </thead>

      <tbody>
        @forelse($rows as $i => $r)
          <tr class="hover:bg-slate-50">
            <td class="td sticky left-0 z-20 bg-white w-10">
              <input type="checkbox" class="row-checkbox" name="ids[]" value="{{ $r['detail_id'] }}"
                     @change="onRowToggle()" aria-label="Chọn đối tượng {{ $r['ten'] }}" form="bulkSelForm">
            </td>

            <td class="td sticky left-10 z-20 bg-white w-10">
              <div x-data="kebabMenu()" x-init="init()" @keydown.escape.window="close()">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100"
                        @click="openMenu($event)" aria-label="Mở menu">⋮</button>
                <template x-teleport="body">
                  <div x-show="open" x-transition.opacity.duration.120ms
                       @click.outside="close()" :style="style" class="fixed z-[9999]">
                    <div x-ref="menu" class="w-44 rounded-xl border bg-white shadow-xl overflow-hidden"></div>
                  </div>
                </template>
              </div>
            </td>

            <td class="td sticky left-20 z-10 bg-white w-12 tabular-nums">{{ $rows->firstItem() + $i }}</td>

            @foreach($groups as $g)
              @foreach($g['keys'] as $k)
                @php $v = $r[$k] ?? ''; @endphp
                <td class="td">
                  @if($k === 'ho_dem' || $k === 'ten')
                    <button type="button" class="text-cyan-700 hover:underline"
                            @click.stop.prevent="openWith({{ Js::from($r) }})">
                      {{ is_bool($v) ? ($v ? '✓' : '') : $v }}
                    </button>
                  @else
                    {{ is_bool($v) ? ($v ? '✓' : '') : $v }}
                  @endif
                </td>
              @endforeach
            @endforeach
          </tr>
        @empty
          <tr>
            <td class="td" colspan="{{ 3 + collect($groups)->sum(fn($g)=>count($g['keys'])) }}">Chưa có dữ liệu.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
</div>
