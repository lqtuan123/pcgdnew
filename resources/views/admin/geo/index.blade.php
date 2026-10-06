@extends('layouts.admin')

@section('title', 'Duyệt địa giới')
@section('crumb', 'Duyệt địa giới')

@section('content')
  <style>[x-cloak]{display:none!important}</style>

  <div x-data="geoBrowser"
     x-init="init()"
     ondrop="return false" ondragover="return false"
     @dragenter.prevent.stop
     @dragover.prevent.stop
     @drop.prevent.stop
     x-cloak
     class="space-y-6"
     
     >
    <!-- Toolbar -->
    <div class="flex items-center gap-3">
      <button class="btn-soft" @click="reloadAll()">Tải lại</button>
      <div class="ml-auto flex items-center gap-2 text-sm">
        <span class="tab active">VN</span>
        <span class="tab">Khác</span>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-1">
      <!-- ============ BÊN CŨ: 4 CẤP (1→2→3→4) ============ -->
      <article class="card">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Tỉnh cũ <span class="text-slate-400 text-sm">(scheme=old)</span></h2>
          <div class="flex items-center gap-2">
            <input x-model="qOld" type="text" placeholder="Tìm tỉnh cũ…" class="h-9 w-44 rounded-lg border px-3 text-sm">
            <button class="btn-soft" @click="loadProvinces('old')">Tải lại</button>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-0 -mx-4">
          <!-- L1: Provinces (old) -->
          <div class="border-r">
            <div class="-mr-4 max-h-[62vh] overflow-y-auto pr-4">
              <template x-if="loading.old"><div class="p-3 text-sm text-slate-500">Đang tải…</div></template>
              <ul class="divide-y divide-slate-100" x-show="!loading.old">
                <template x-for="p in filtered(provinces.old, qOld)" :key="p.id">
                  <li>
                    <button
                      class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between"
                      :class="selOld.l1?.id===p.id ? 'bg-blue-50/60 ring-1 ring-blue-400 rounded-md' : ''"
                      @click="openOldL1(p)"
                    >
                      <span class="truncate">
                        <span class="font-medium" x-text="p.name"></span>
                        <span class="text-slate-400 text-xs ml-2" x-text="p.code"></span>
                      </span>
                      <span class="badge-primary" x-show="selOld.l1?.id===p.id">đang xem</span>
                    </button>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <!-- L2: Districts (old) -->
          <div class="border-r">
            <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500">Cấp 2 thuộc</div>
                <div class="font-medium truncate" x-text="selOld.l1?.name || '—'"></div>
              </div>
              <button class="text-xs text-green-600 hover:underline"
                      x-show="selOld.l1" @click="startAdd(selOld.l1)">+ Cấp 2</button>
            </div>
            <div class="-mx-4 max-h-[58vh] overflow-y-auto px-4">
              <template x-if="loading.l2old"><div class="p-3 text-sm text-slate-500">Đang tải cấp 2…</div></template>
              <ul class="divide-y divide-slate-100">
                <template x-for="d in listOld.l2" :key="d.id">
                  <li class="flex items-center justify-between">
                    <button class="flex-1 px-3 py-2 text-left hover:bg-slate-50"
                            :class="selOld.l2?.id===d.id ? 'bg-blue-50/60 rounded-md' : ''"
                            @click="openOldL2(d)">
                      <div class="truncate" x-text="d.name"></div>
                      <div class="text-xs text-slate-400" x-text="'Mã: '+d.code"></div>
                    </button>
                    <div class="px-3 whitespace-nowrap text-xs">
                      <button class="text-blue-600 hover:underline" @click="startEdit(d)">Sửa</button>
                      <button class="text-green-600 hover:underline ml-3" @click="startAdd(d)">+ Cấp 3</button>
                      <button class="text-rose-600 hover:underline ml-3" @click="askDelete(d)">Xoá</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <!-- L3: Communes (old) -->
          <div class="border-r">
            <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500">Cấp 3 thuộc</div>
                <div class="font-medium truncate" x-text="selOld.l2?.name || '—'"></div>
              </div>
              <button class="text-xs text-green-600 hover:underline"
                      x-show="selOld.l2" @click="startAdd(selOld.l2)">+ Cấp 3</button>
            </div>

            <div class="-mx-4 max-h-[58vh] overflow-y-auto px-4">
              <template x-if="loading.l3old"><div class="p-3 text-sm text-slate-500">Đang tải cấp 3…</div></template>
              <ul class="divide-y divide-slate-100">
                <template x-for="w in listOld.l3" :key="w.id">
                  <li class="flex items-center justify-between">
                    <button class="flex-1 px-3 py-2 text-left hover:bg-slate-50"
                            :class="selOld.l3?.id===w.id ? 'bg-blue-50/60 ring-1 ring-blue-400 rounded-md' : ''"
                            @click="openOldL3(w)">
                      <div class="truncate" x-text="w.name"></div>
                      <div class="text-xs text-slate-400" x-text="'Mã: '+w.code"></div>
                    </button>

                    <div class="px-3 text-xs whitespace-nowrap">
                      <button class="text-blue-600 hover:underline" @click="startEdit(w)">Sửa</button>
                      <button class="text-green-600 hover:underline ml-3" @click="startAddLevel4(w)">+ Cấp 4</button>
                      <button class="text-rose-600 hover:underline ml-3" @click="askDelete(w)">Xoá</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <!-- L4: Villages (old) -->
          <div>
            <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500">Cấp 4 thuộc</div>
                <div class="font-medium truncate" x-text="selOld.l3?.name || '—'"></div>
              </div>
              <button class="text-xs text-green-600 hover:underline"
                      x-show="selOld.l3" @click="startAdd(selOld.l3)">+ Cấp 4</button>
            </div>
            <div class="-mx-4 max-h-[58vh] overflow-y-auto px-4">
              <template x-if="loading.l4old"><div class="p-3 text-sm text-slate-500">Đang tải cấp 4…</div></template>
              <ul class="divide-y divide-slate-100">
                <template x-if="!listOld.l4.length && selOld.l3">
                  <li class="p-3 text-sm text-slate-500">Chưa có cấp 4. Nhấn “+ Cấp 4” để thêm.</li>
                </template>
                <template x-for="v in listOld.l4" :key="v.id">
                  <li class="flex items-center justify-between px-3 py-2 hover:bg-slate-50">
                    <div class="min-w-0">
                      <div class="truncate" x-text="v.name"></div>
                      <div class="text-xs text-slate-400" x-text="'Mã: '+v.code"></div>
                    </div>
                    <div class="text-xs whitespace-nowrap">
                      <button class="text-blue-600 hover:underline" @click="startEdit(v)">Sửa</button>
                      <button class="text-rose-600 hover:underline ml-3" @click="askDelete(v)">Xoá</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </div>
      </article>

      <!-- ============ BÊN MỚI: 3 CẤP (1→3→4) ============ -->
      <article class="card">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Tỉnh mới <span class="text-slate-400 text-sm">(scheme=new)</span></h2>
          <div class="flex items-center gap-2">
            <input x-model="qNew" type="text" placeholder="Tìm tỉnh mới…" class="h-9 w-44 rounded-lg border px-3 text-sm">
            <button class="btn-soft" @click="loadProvinces('new')">Tải lại</button>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-0 -mx-4">
          <!-- L1: Provinces (new) -->
          <div class="border-r">
            <div class="-mr-4 max-h-[62vh] overflow-y-auto pr-4">
              <template x-if="loading.new"><div class="p-3 text-sm text-slate-500">Đang tải…</div></template>
              <ul class="divide-y divide-slate-100" x-show="!loading.new">
                <template x-for="p in filtered(provinces.new, qNew)" :key="p.id">
                  <li>
                    <button
                      class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between"
                      :class="selNew.l1?.id===p.id ? 'bg-blue-50/60 ring-1 ring-blue-400 rounded-md' : ''"
                      @click="openNewL1(p)"
                    >
                      <span class="truncate">
                        <span class="font-medium" x-text="p.name"></span>
                        <span class="text-slate-400 text-xs ml-2" x-text="p.code"></span>
                      </span>
                      <span class="badge-primary" x-show="selNew.l1?.id===p.id">đang xem</span>
                    </button>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <!-- L3: Wards (new) -->
          <div class="border-r">
            <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500">Cấp 3 thuộc</div>
                <div class="font-medium truncate" x-text="selNew.l1?.name || '—'"></div>
              </div>
              <button class="text-xs text-green-600 hover:underline"
                      x-show="selNew.l1" @click="startAdd(selNew.l1)">+ Cấp 3</button>
            </div>

            <div class="-mx-4 max-h-[58vh] overflow-y-auto px-4">
              <template x-if="loading.l3new"><div class="p-3 text-sm text-slate-500">Đang tải cấp 3…</div></template>
              <ul class="divide-y divide-slate-100">
                <template x-for="w in listNew.l3" :key="w.id">
                  <li class="flex items-center justify-between">
                    <button class="flex-1 px-3 py-2 text-left hover:bg-slate-50"
                            :class="selNew.l3?.id===w.id ? 'bg-blue-50/60 ring-1 ring-blue-400 rounded-md' : ''"
                            @click="openNewL3(w)">
                      <div class="truncate" x-text="w.name"></div>
                      <div class="text-xs text-slate-400" x-text="'Mã: '+w.code"></div>
                    </button>

                    <div class="px-3 text-xs whitespace-nowrap">
                      <button class="text-blue-600 hover:underline" @click="startEdit(w)">Sửa</button>
                      <button class="text-green-600 hover:underline ml-3" @click="startAddLevel4(w)">+ Cấp 4</button>
                      <button class="text-rose-600 hover:underline ml-3" @click="askDelete(w)">Xoá</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <!-- L4: Villages (new) -->
          <div>
            <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500">Cấp 4 thuộc</div>
                <div class="font-medium truncate" x-text="selNew.l3?.name || '—'"></div>
              </div>
              <button class="text-xs text-green-600 hover:underline"
                      x-show="selNew.l3" @click="startAdd(selNew.l3)">+ Cấp 4</button>
            </div>
            <div class="-mx-4 max-h-[58vh] overflow-y-auto px-4">
              <template x-if="loading.l4new"><div class="p-3 text-sm text-slate-500">Đang tải cấp 4…</div></template>
              <ul class="divide-y divide-slate-100">
                <template x-if="!listNew.l4.length && selNew.l3">
                  <li class="p-3 text-sm text-slate-500">Chưa có cấp 4. Nhấn “+ Cấp 4” để thêm.</li>
                </template>
                <template x-for="v in listNew.l4" :key="v.id">
                  <li class="flex items-center justify-between px-3 py-2 hover:bg-slate-50">
                    <div class="min-w-0">
                      <div class="truncate" x-text="v.name"></div>
                      <div class="text-xs text-slate-400" x-text="'Mã: '+v.code"></div>
                    </div>
                    <div class="text-xs whitespace-nowrap">
                      <button class="text-blue-600 hover:underline" @click="startEdit(v)">Sửa</button>
                      <button class="text-rose-600 hover:underline ml-3" @click="askDelete(v)">Xoá</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </div>
      </article>
    </div>

    <!-- ==================== PANEL ÁNH XẠ CŨ ↔ MỚI ==================== -->
   <!-- ==================== PANEL ÁNH XẠ CŨ ↔ MỚI (NHẸ) ==================== -->
<section class="card">
  <div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold">Ánh xạ giữa “cũ” ↔ “mới” (nhẹ trình duyệt)</h2>
    <div class="flex gap-1">
      <button class="tab" :class="mapLevel===1 ? 'active' : ''" @click="mapLevel=1">Cấp 1 (Tỉnh)</button>
      <button class="tab" :class="mapLevel===3 ? 'active' : ''" @click="mapLevel=3">Cấp 3</button>
      <button class="tab" :class="mapLevel===4 ? 'active' : ''" @click="mapLevel=4">Cấp 4</button>
    </div>
  </div>

  <!-- Tìm nhanh theo tên tỉnh -->
  <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
    <!-- Tỉnh cũ -->
    <div class="relative">
      <label class="block text-xs text-slate-500 mb-1">Tìm Tỉnh (cũ)</label>
      <input type="text" class="w-full rounded-lg border px-3 py-2" placeholder="gõ tên tỉnh cũ…"
             x-model="findOldProv">
      <div class="absolute z-10 mt-1 w-full bg-white border rounded-lg max-h-64 overflow-y-auto"
           x-show="findOldProv && filtered(provinces.old, findOldProv).length">
        <ul class="divide-y divide-slate-100">
          <template x-for="p in filtered(provinces.old, findOldProv).slice(0,10)">
            <li>
              <button class="w-full text-left px-3 py-2 hover:bg-slate-50"
                      @click="pickOldProv(p)">
                <div class="font-medium" x-text="p.name"></div>
                <div class="text-xs text-slate-400" x-text="p.code"></div>
              </button>
            </li>
          </template>
        </ul>
      </div>
      <div class="mt-2 text-sm" x-show="selOld.l1">
        Đang chọn: <span class="font-medium" x-text="selOld.l1?.name"></span>
        <button class="ml-2 text-xs text-slate-500 underline" @click="clearOldProv()">bỏ chọn</button>
      </div>
    </div>

    <!-- Tỉnh mới -->
    <div class="relative">
      <label class="block text-xs text-slate-500 mb-1">Tìm Tỉnh (mới)</label>
      <input type="text" class="w-full rounded-lg border px-3 py-2" placeholder="gõ tên tỉnh mới…"
             x-model="findNewProv">
      <div class="absolute z-10 mt-1 w-full bg-white border rounded-lg max-h-64 overflow-y-auto"
           x-show="findNewProv && filtered(provinces.new, findNewProv).length">
        <ul class="divide-y divide-slate-100">
          <template x-for="p in filtered(provinces.new, findNewProv).slice(0,10)">
            <li>
              <button class="w-full text-left px-3 py-2 hover:bg-slate-50"
                      @click="pickNewProv(p)">
                <div class="font-medium" x-text="p.name"></div>
                <div class="text-xs text-slate-400" x-text="p.code"></div>
              </button>
            </li>
          </template>
        </ul>
      </div>
      <div class="mt-2 text-sm" x-show="selNew.l1">
        Đang chọn: <span class="font-medium" x-text="selNew.l1?.name"></span>
        <button class="ml-2 text-xs text-slate-500 underline" @click="clearNewProv()">bỏ chọn</button>
      </div>
    </div>
  </div>

  <!-- Bộ lọc theo cấp (chỉ nạp khi cần) -->
  <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-12" x-show="mapLevel===3">
    <div class="md:col-span-4">
      <label class="block text-xs text-slate-500 mb-1">Cấp 2 (cũ)</label>
      <select class="w-full rounded-lg border px-3 py-2"
              x-model="filterOldDistrictId"
              @change="onFilterOldDistrictChangedShallow">
        <option value="">— Chọn cấp 2 —</option>
        <template x-for="d in listOld.l2">
          <option :key="'od'+d.id" :value="d.id" x-text="d.name"></option>
        </template>
      </select>
      <p class="mt-1 text-xs text-slate-500" x-show="!selOld.l1">Chọn Tỉnh (cũ) trước để có danh sách Cấp 2.</p>
    </div>
    <div class="md:col-span-4">
      <label class="block text-xs text-slate-500 mb-1">Bên Mới: Cấp 3 dưới Tỉnh (mới)</label>
      <select class="w-full rounded-lg border px-3 py-2" x-model="filterNewWardId">
        <option value="">— (không lọc) —</option>
        <template x-for="w in listNew.l3">
          <option :key="'nw'+w.id" :value="w.id" x-text="w.name"></option>
        </template>
      </select>
      <p class="mt-1 text-xs text-slate-500" x-show="!selNew.l1">Chọn Tỉnh (mới) để hiện Cấp 3.</p>
    </div>
    <div class="md:col-span-4 flex items-end">
      <button class="btn-soft w-full" @click="loadMappings()">Xem ánh xạ cấp 3</button>
    </div>
  </div>

  <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-12" x-show="mapLevel===4">
    <div class="md:col-span-6">
      <label class="block text-xs text-slate-500 mb-1">Cấp 3 (cũ)</label>
      <select class="w-full rounded-lg border px-3 py-2"
              x-model="filterOldWardId" @change="onFilterOldWardChangedShallow">
        <option value="">— Chọn —</option>
        <template x-for="w in listOld.l3">
          <option :key="'ow'+w.id" :value="w.id" x-text="w.name"></option>
        </template>
      </select>
    </div>
    <div class="md:col-span-6">
      <label class="block text-xs text-slate-500 mb-1">Cấp 3 (mới)</label>
      <select class="w-full rounded-lg border px-3 py-2"
              x-model="filterNewWardId" @change="onFilterNewWardChangedShallow">
        <option value="">— Chọn —</option>
        <template x-for="w in listNew.l3">
          <option :key="'nw'+w.id" :value="w.id" x-text="w.name"></option>
        </template>
      </select>
    </div>
    <div class="md:col-span-12">
      <button class="btn-soft" @click="loadMappings()">Xem ánh xạ cấp 4</button>
    </div>
  </div>

  <!-- Tìm trong bảng ánh xạ (sau khi bấm Xem ánh xạ mới có dữ liệu) -->
  <div class="mt-3">
    <input x-model="mapSearch" type="text" placeholder="Tìm trong kết quả ánh xạ…"
           class="w-full md:w-80 rounded-lg border px-3 py-2">
  </div>

  <!-- Form thêm ánh xạ (theo cấp đang chọn) -->
  <div class="mt-4 space-y-3">
    <!-- Cấp 1 -->
    <div x-show="mapLevel===1">
      <div class="text-sm">
        Bên Cũ: <b x-text="selOld.l1?.name || '—'"></b> ·
        Bên Mới: <b x-text="selNew.l1?.name || '—'"></b>
      </div>
      <div class="grid md:grid-cols-12 gap-3 mt-2">
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Relation</label>
          <select class="w-full rounded-lg border px-2 py-2" x-model="mapForm.relation">
            <option value="successor">successor</option>
            <option value="unchanged">unchanged</option>
            <option value="merged_into">merged_into</option>
            <option value="split_from">split_from</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs text-slate-500 mb-1">Weight</label>
          <input type="number" step="0.1" class="w-full rounded-lg border px-2 py-2" x-model.number="mapForm.weight">
        </div>
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Hiệu lực</label>
          <input type="date" class="w-full rounded-lg border px-2 py-2" x-model="mapForm.effective_date">
        </div>
        <div class="md:col-span-4">
          <label class="block text-xs text-slate-500 mb-1">Ghi chú</label>
          <input type="text" class="w-full rounded-lg border px-3 py-2" x-model="mapForm.note">
        </div>
        <div class="md:col-span-12">
          <button class="btn-primary"
                  :disabled="!(selOld.l1 && selNew.l1)"
                  @click="createMappingLevel1()">+ Thêm ánh xạ cấp 1</button>
          <button class="btn-soft ml-2" @click="loadMappings()">Xem ánh xạ cấp 1</button>
        </div>
      </div>
    </div>

    <!-- Cấp 3 -->
    <div x-show="mapLevel===3">
      <div class="grid md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">Bên Cũ (Cấp 3)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-model="mapForm.old_unit_id">
            <option value="">— Chọn —</option>
            <template x-for="o in listOld.l3">
              <option :key="'o'+o.id" :value="o.id" x-text="o.name + ' (' + (o.code || '—') + ')'"></option>
            </template>
          </select>
        </div>
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">Bên Mới (Cấp 3)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-model="mapForm.new_unit_id">
            <option value="">— Chọn —</option>
            <template x-for="n in listNew.l3">
              <option :key="'n'+n.id" :value="n.id" x-text="n.name + ' (' + (n.code || '—') + ')'"></option>
            </template>
          </select>
        </div>
        <div class="md:col-span-12">
          <button class="btn-primary"
                  :disabled="!(Number(mapForm.old_unit_id)||0) || !(Number(mapForm.new_unit_id)||0)"
                  @click="createMapping()">+ Thêm ánh xạ cấp 3</button>
        </div>
      </div>
    </div>

    <!-- Cấp 4 -->
    <div x-show="mapLevel===4">
      <div class="grid md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">Bên Cũ (Cấp 4)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-model="mapForm.old_unit_id">
            <option value="">— Chọn —</option>
            <template x-for="o in listOld.l4">
              <option :key="'oo'+o.id" :value="o.id" x-text="o.name + ' (' + (o.code || '—') + ')'"></option>
            </template>
          </select>
        </div>
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">Bên Mới (Cấp 4)</label>
          <select class="w-full rounded-lg border px-3 py-2" x-model="mapForm.new_unit_id">
            <option value="">— Chọn —</option>
            <template x-for="n in listNew.l4">
              <option :key="'nn'+n.id" :value="n.id" x-text="n.name + ' (' + (n.code || '—') + ')'"></option>
            </template>
          </select>
        </div>
        <div class="md:col-span-12">
          <button class="btn-primary"
                  :disabled="!(Number(mapForm.old_unit_id)||0) || !(Number(mapForm.new_unit_id)||0)"
                  @click="createMapping()">+ Thêm ánh xạ cấp 4</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bảng kết quả ánh xạ (chỉ render khi bấm Xem) -->
  <div class="-mx-4 mt-4 overflow-x-auto" x-show="mappings.length || mapLoading">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="th">Cũ</th>
          <th class="th">Mới</th>
          <th class="th">Relation</th>
          <th class="th">Weight</th>
          <th class="th">Hiệu lực</th>
          <th class="th">Ghi chú</th>
          <th class="th text-right">Thao tác</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        <template x-if="mapLoading">
          <tr><td class="td" colspan="7">Đang tải ánh xạ…</td></tr>
        </template>
        <template x-if="!mapLoading && !mappingsFiltered().length">
          <tr><td class="td" colspan="7">Không có dữ liệu theo bộ lọc hiện tại.</td></tr>
        </template>
        <template x-for="m in mappingsFiltered()" :key="m.id">
          <tr class="hover:bg-slate-50">
            <td class="td">
              <div class="font-medium" x-text="m.old.name"></div>
              <div class="text-xs text-slate-400" x-text="m.old.code"></div>
            </td>
            <td class="td">
              <div class="font-medium" x-text="m.new.name"></div>
              <div class="text-xs text-slate-400" x-text="m.new.code"></div>
            </td>
            <td class="td" x-text="m.relation || '—'"></td>
            <td class="td" x-text="m.weight ?? '—'"></td>
            <td class="td" x-text="m.effective_date || '—'"></td>
            <td class="td" x-text="m.note || ''"></td>
            <td class="td text-right">
              <button class="text-rose-600 hover:underline" @click="deleteMapping(m)">Xoá</button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</section>


    <!-- Drawer: Sửa -->
    <div x-show="editUnit" x-transition class="fixed inset-0 z-[60] flex">
      <div class="hidden md:block flex-1 bg-black/30" @click="cancelEdit"></div>
      <div class="w-full md:w-[420px] bg-white h-full shadow-xl">
        <div class="p-4 border-b flex items-center justify-between">
          <h3 class="font-semibold">Sửa đơn vị</h3>
          <button class="btn-soft" @click="cancelEdit">Đóng</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-xs text-slate-500">ID: <span x-text="editUnit?.id"></span></div>
          <label class="block text-sm">Mã (code)</label>
          <input type="text" x-model="editCode" class="w-full rounded-lg border px-3 py-2">
          <label class="block text-sm mt-2">Tên</label>
          <input type="text" x-model="editName" class="w-full rounded-lg border px-3 py-2">
          <div class="pt-2">
            <button class="btn-primary" @click="submitEdit()">Lưu</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Drawer: Thêm con -->
    <div x-show="addParent" x-transition class="fixed inset-0 z-[60] flex">
      <div class="hidden md:block flex-1 bg-black/30" @click="cancelAdd()"></div>
      <div class="w-full md:w-[420px] bg-white h-full shadow-xl">
        <div class="p-4 border-b flex items-center justify-between">
          <h3 class="font-semibold">Thêm cấp <span x-text="addLevel"></span></h3>
          <button class="btn-soft" @click="cancelAdd()">Đóng</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-xs text-slate-500">
            Cha: <span class="font-medium" x-text="addParent?.name"></span>
            <span class="text-slate-400"> (code: <span x-text="addParent?.code"></span>)</span>
          </div>
          <label class="block text-sm">Mã (code)</label>
          <input type="text" x-model="addForm.code" class="w-full rounded-lg border px-3 py-2">
          <label class="block text-sm">Tên</label>
          <input type="text" x-model="addForm.name" class="w-full rounded-lg border px-3 py-2">
          <div class="pt-2">
            <button class="btn-primary" @click="submitAdd()">Thêm</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Confirm Xoá -->
    <div x-show="delUnit" x-transition class="fixed inset-0 z-[70] grid place-items-center">
      <div class="absolute inset-0 bg-black/40" @click="delUnit=null"></div>
      <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
        <h3 class="font-semibold text-lg">Xoá đơn vị?</h3>
        <p class="mt-2 text-sm text-slate-600">
          <span class="font-medium" x-text="delUnit?.name"></span>
          <span class="text-slate-400"> (code: <span x-text="delUnit?.code"></span>)</span>
        </p>
        <p class="mt-2 text-xs text-slate-500">Không thể xoá nếu còn đơn vị con, đang ánh xạ hoặc dùng trong địa chỉ.</p>
        <div class="mt-4 flex justify-end gap-2">
          <button class="btn-soft" @click="delUnit=null">Huỷ</button>
          <button class="px-3 py-2 rounded-lg bg-rose-600 text-white" @click="submitDelete()">Xoá</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // Chặn drag/drop ngoài input/textarea/contenteditable
  (function () {
    const guard = (e) => {
      const ok = e.target && e.target.closest('input, textarea, [contenteditable="true"]');
      if (!ok) { e.preventDefault(); e.stopPropagation(); }
    };
    window.addEventListener('dragenter', guard, true);
    window.addEventListener('dragover',  guard, true);
    window.addEventListener('drop',      guard, true);
  })();
</script>
<script>
   

(() => {
  const R = {
    provinces   : @json(route('admin.geo.provinces')),   // GET ?scheme=old|new
    childrenBase: @json(url('admin/geo/children')),      // GET /{parentId}
    unitsBase   : @json(url('admin/geo/units')),         // POST /child, PATCH /{id}, DELETE /{id}
    mappingsBase: @json(url('admin/geo/mappings')),      // GET, POST, DELETE /{id}
    csrf        : @json(csrf_token()),
  };

  function geoBrowserFactory() {
    return {
      /* ===== Browser state ===== */
      provinces: { old: [], new: [] },
      qOld: '', qNew: '',
      selOld:  { l1:null, l2:null, l3:null },
      listOld: { l2:[],   l3:[],   l4:[]   },
      selNew:  { l1:null, l3:null },
      listNew: { l3:[],   l4:[]   },
      loading: { old:true, new:true, l2old:false, l3old:false, l4old:false, l3new:false, l4new:false },

      // edit/add/delete
      editUnit:null, editName:'', editCode:'',
      addParent:null, addLevel:null, addForm:{ code:'', name:'' },
      delUnit:null,

      /* ===== Mapping state (lightweight) ===== */
      findOldProv: '', findNewProv: '',
      mapLevel: 1,           // 1 (tỉnh), 3, 4
      mapLoading: false,
      mappings: [],
      mapForm: { old_unit_id:null, new_unit_id:null, relation:'successor', weight:1, effective_date:'', note:'' },
      filterOldDistrictId: '', filterOldWardId: '', filterNewWardId: '',
      mapSearch: '',

      /* ===== lifecycle ===== */
      async init() {
        await Promise.all([this.loadProvinces('old'), this.loadProvinces('new')]);
        // Không auto loadMappings() để nhẹ trình duyệt
      },

      /* ===== helpers ===== */
      filtered(list, q){
        if(!q) return list;
        q=q.toLowerCase();
        return list.filter(i => (i.name||'').toLowerCase().includes(q) || (i.code||'').toLowerCase().includes(q));
      },

      async reloadAll(){
        await Promise.all([this.loadProvinces('old'), this.loadProvinces('new')]);
        this.selOld={l1:null,l2:null,l3:null}; this.selNew={l1:null,l3:null};
        this.listOld={l2:[],l3:[],l4:[]}; this.listNew={l3:[],l4:[]};
        this.findOldProv=''; this.findNewProv='';
        this.filterOldDistrictId=''; this.filterOldWardId=''; this.filterNewWardId='';
        this.mappings = []; this.mapSearch='';
      },

      /* ===== data loaders ===== */
      async loadProvinces(scheme) {
        this.loading[scheme] = true;
        try {
          const res = await fetch(`${R.provinces}?scheme=${scheme}`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
          this.provinces[scheme] = res.ok ? await res.json() : [];
        } finally { this.loading[scheme] = false; }
      },
      async fetchChildren(parentId) {
        const res = await fetch(`${R.childrenBase}/${parentId}`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
        const json = res.ok ? await res.json() : { children: [] };
        return json.children || [];
      },

      /* ===== OLD: 1→2→3→4 ===== */
      async openOldL1(p){
        this.selOld = { l1: p, l2: null, l3: null };
        this.listOld = { l2: [], l3: [], l4: [] };
        this.loading.l2old = true;
        this.listOld.l2 = await this.fetchChildren(p.id);
        this.loading.l2old = false;
      },
      async openOldL2(d){
        this.selOld.l2 = d; this.selOld.l3 = null; this.listOld.l3 = []; this.listOld.l4 = [];
        this.loading.l3old = true;
        this.listOld.l3 = await this.fetchChildren(d.id);
        this.loading.l3old = false;
      },
      async openOldL3(w){
        this.selOld.l3 = w; this.listOld.l4 = [];
        this.loading.l4old = true;
        this.listOld.l4 = await this.fetchChildren(w.id);
        this.loading.l4old = false;
      },

      /* ===== NEW: 1→3→4 ===== */
      async openNewL1(p){
        this.selNew = { l1: p, l3: null };
        this.listNew = { l3: [], l4: [] };
        this.loading.l3new = true;
        this.listNew.l3 = await this.fetchChildren(p.id);
        this.loading.l3new = false;
      },
      async openNewL3(w){
        this.selNew.l3 = w; this.listNew.l4 = [];
        this.loading.l4new = true;
        this.listNew.l4 = await this.fetchChildren(w.id);
        this.loading.l4new = false;
      },

      /* ===== ADD (child) ===== */
      startAdd(parent){
        const lvl = (parent?.type_level || 0) + 1;
        if (lvl < 2 || lvl > 4) return alert('Chỉ thêm tới cấp 4');
        this.addParent = parent; this.addLevel = lvl; this.addForm = { code:'', name:'' };
      },
      startAddLevel4(parent){
        if (parent.type_level !== 3) return alert('Chỉ thêm cấp 4 dưới cấp 3');
        this.startAdd(parent);
      },
      cancelAdd(){ this.addParent=null; this.addLevel=null; this.addForm={code:'',name:''}; },
      async submitAdd(){
        if(!this.addParent) return;
        const res = await fetch(R.unitsBase + '/child', {
          method:'POST',
          headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'},
          body: JSON.stringify({ parent_id:this.addParent.id, code:this.addForm.code, name:this.addForm.name })
        });
        const json = await res.json();
        if(json.ok){
          const p = this.addParent;
          if (this.selOld.l1 && p.id===this.selOld.l1.id) await this.openOldL1(this.selOld.l1);
          if (this.selOld.l2 && p.id===this.selOld.l2.id) await this.openOldL2(this.selOld.l2);
          if (this.selOld.l3 && p.id===this.selOld.l3.id) await this.openOldL3(this.selOld.l3);
          if (this.selNew.l1 && p.id===this.selNew.l1.id) await this.openNewL1(this.selNew.l1);
          if (this.selNew.l3 && p.id===this.selNew.l3.id) await this.openNewL3(this.selNew.l3);
          this.cancelAdd(); alert(json.toast || 'Đã thêm');
        } else alert(json.message || 'Không thể thêm');
      },

      /* ===== EDIT ===== */
      startEdit(u){ this.editUnit={...u}; this.editName=u.name||''; this.editCode=u.code||''; },
      cancelEdit(){ this.editUnit=null; this.editName=''; this.editCode=''; },
      async submitEdit(){
        if(!this.editUnit) return;
        const res = await fetch(`${R.unitsBase}/${this.editUnit.id}`, {
          method:'PATCH',
          headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'},
          body: JSON.stringify({ name:this.editName, code:this.editCode })
        });
        const json = await res.json();
        if(json.ok){
          const lists = [this.listOld.l2, this.listOld.l3, this.listOld.l4, this.listNew.l3, this.listNew.l4, this.provinces.old, this.provinces.new];
          for (const L of lists) { const i = L.findIndex(x=>x.id===this.editUnit.id); if (i>=0) { L[i].name=this.editName; L[i].code=this.editCode; } }
          this.cancelEdit(); alert(json.toast || 'Đã cập nhật');
        } else alert(json.message || 'Không thể lưu');
      },

      /* ===== DELETE ===== */
      askDelete(u){ this.delUnit = u; },
      async submitDelete(){
        if(!this.delUnit) return;
        const id = this.delUnit.id;
        const res = await fetch(`${R.unitsBase}/${id}`, { method:'DELETE', headers:{'Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'} });
        const json = await res.json();
        if(json.ok){
          const lists = [this.listOld.l2, this.listOld.l3, this.listOld.l4, this.listNew.l3, this.listNew.l4];
          for (const L of lists) { const i = L.findIndex(x=>x.id===id); if (i>=0) L.splice(i,1); }
          if (this.selOld.l2?.id === id) { this.selOld.l2=null; this.listOld.l3=[]; this.listOld.l4=[]; }
          if (this.selOld.l3?.id === id) { this.selOld.l3=null; this.listOld.l4=[]; }
          if (this.selNew.l3?.id === id) { this.selNew.l3=null; this.listNew.l4=[]; }
          this.delUnit = null;
        } else alert(json.message || 'Không thể xoá');
      },

      /* ===== Mapping: search-pick tỉnh ===== */
      pickOldProv(p){
        this.findOldProv = '';
        this.openOldL1(p);
        this.filterOldDistrictId = '';
        this.filterOldWardId = '';
        this.mappings = []; // clear until user clicks "Xem ánh xạ"
      },
      clearOldProv(){
        this.selOld = { l1:null, l2:null, l3:null };
        this.listOld = { l2:[], l3:[], l4:[] };
        this.filterOldDistrictId = '';
        this.filterOldWardId = '';
        this.mappings = [];
      },
      pickNewProv(p){
        this.findNewProv = '';
        this.openNewL1(p);
        this.filterNewWardId = '';
        this.mappings = [];
      },
      clearNewProv(){
        this.selNew = { l1:null, l3:null };
        this.listNew = { l3:[], l4:[] };
        this.filterNewWardId = '';
        this.mappings = [];
      },

      /* ===== Mapping: shallow handlers (không auto load) ===== */
      async onFilterOldDistrictChangedShallow(){
        const id = Number(this.filterOldDistrictId) || null;
        if (id){
          const d = this.listOld.l2.find(x=>x.id == id);
          if (d) await this.openOldL2(d);
        } else {
          this.selOld.l2 = null; this.listOld.l3 = []; this.listOld.l4 = [];
        }
      },
      async onFilterOldWardChangedShallow(){
        const id = Number(this.filterOldWardId) || null;
        if (id){
          const w = this.listOld.l3.find(x=>x.id == id);
          if (w) await this.openOldL3(w);
        } else {
          this.selOld.l3 = null; this.listOld.l4 = [];
        }
      },
      async onFilterNewWardChangedShallow(){
        const id = Number(this.filterNewWardId) || null;
        if (id){
          const w = this.listNew.l3.find(x=>x.id == id);
          if (w) await this.openNewL3(w);
        } else {
          this.selNew.l3 = null; this.listNew.l4 = [];
        }
      },

      /* ===== Mapping: load/search/list ===== */
      mappingsFiltered(){
        const q = (this.mapSearch||'').toLowerCase().trim();
        if (!q) return this.mappings;
        return this.mappings.filter(m =>
          (m.old.name||'').toLowerCase().includes(q) ||
          (m.new.name||'').toLowerCase().includes(q) ||
          (m.old.code||'').toLowerCase().includes(q) ||
          (m.new.code||'').toLowerCase().includes(q)
        );
      },

      async loadMappings(){
        this.mapLoading = true; this.mappings = [];
        try{
          const params = new URLSearchParams({ level: String(this.mapLevel) });

          if (this.mapLevel === 1){
            const oldId = this.selOld.l1?.id || '';
            const newId = this.selNew.l1?.id || '';
            if (!oldId && !newId) { this.mapLoading=false; return; }
            if (oldId) params.set('old_id', oldId);
            if (newId) params.set('new_id', newId);
          }

          if (this.mapLevel === 3){
            const oldDistrictId = Number(this.filterOldDistrictId) || this.selOld.l2?.id || '';
            const newProvId     = this.selNew.l1?.id || '';
            if (!oldDistrictId && !newProvId) { this.mapLoading=false; return; }
            if (oldDistrictId) params.set('old_parent_id', oldDistrictId);
            if (newProvId)     params.set('new_parent_id', newProvId);
          }

          if (this.mapLevel === 4){
            const oldWardParent = Number(this.filterOldWardId) || this.selOld.l3?.id || '';
            const newWardParent = Number(this.filterNewWardId) || this.selNew.l3?.id || '';
            if (!oldWardParent && !newWardParent) { this.mapLoading=false; return; }
            if (oldWardParent) params.set('old_parent_id', oldWardParent);
            if (newWardParent) params.set('new_parent_id', newWardParent);
          }

          const res = await fetch(`${R.mappingsBase}?${params.toString()}`, {
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
          });
          const json = await res.json();
          this.mappings = (json && json.data) || [];
        } finally { this.mapLoading = false; }
      },

      /* ===== Mapping: create/delete ===== */
      async createMappingLevel1(){
        if (!(this.selOld.l1 && this.selNew.l1)) return;
        const body = {
          level: 1,
          old_unit_id: this.selOld.l1.id,
          new_unit_id: this.selNew.l1.id,
          relation: this.mapForm.relation || 'successor',
          weight: Number(this.mapForm.weight) || 1,
          effective_date: this.mapForm.effective_date || '',
          note: this.mapForm.note || ''
        };
        const res = await fetch(R.mappingsBase, {
          method:'POST',
          headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'},
          body: JSON.stringify(body)
        });
        const json = await res.json();
        if (json.ok){ alert('Đã thêm ánh xạ cấp 1'); await this.loadMappings(); }
        else alert(json.message || 'Không thể tạo ánh xạ');
      },

      async createMapping(){
        const body = {
          level: this.mapLevel,
          old_unit_id: Number(this.mapForm.old_unit_id) || null,
          new_unit_id: Number(this.mapForm.new_unit_id) || null,
          relation: this.mapForm.relation || 'successor',
          weight: Number(this.mapForm.weight) || 1,
          effective_date: this.mapForm.effective_date || '',
          note: this.mapForm.note || ''
        };
        if (!body.old_unit_id || !body.new_unit_id) return;

        const res = await fetch(R.mappingsBase, {
          method:'POST',
          headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'},
          body: JSON.stringify(body)
        });
        const json = await res.json();
        if (json.ok){
          this.mapForm.old_unit_id=null; this.mapForm.new_unit_id=null;
          await this.loadMappings();
        } else alert(json.message || 'Không thể tạo ánh xạ');
      },

      async deleteMapping(m){
        if (!confirm('Xoá ánh xạ này?')) return;
        const res = await fetch(`${R.mappingsBase}/${m.id}`, { method:'DELETE', headers:{'Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'} });
        const json = await res.json();
        if (json.ok){
          this.mappings = this.mappings.filter(x => x.id !== m.id);
        } else alert(json.message || 'Không thể xoá');
      },
    };
  }

  const register = () => Alpine.data('geoBrowser', () => geoBrowserFactory());
  if (window.Alpine) register();
  document.addEventListener('alpine:init', register);
})();
</script>
@endpush
