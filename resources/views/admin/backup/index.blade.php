@extends('layouts.admin')

@section('title', 'Backup & Restore')
@section('crumb', 'Backup & Restore')

@section('content')
  <style>[x-cloak]{display:none!important}</style>

  <div x-data="dbBackup" x-init="init()" x-cloak class="space-y-6">

    <!-- ===== Toolbar ===== -->
    <div class="flex items-center gap-3">
      <button class="btn-soft" @click="reloadBackups()">Tải lại danh sách</button>
      
      <label class="inline-flex items-center gap-2">
        <input type="file" id="uploadZipGlobal" accept=".zip" class="hidden" @change="doUploadZip">
        <button type="button" class="btn-soft" @click="$el.previousElementSibling.click()">
          Tải ZIP lên server
        </button>
      </label>

      <div class="ml-auto text-sm text-slate-500" x-show="now">
        <span x-text="now"></span>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- ===== Khối: Tạo backup FULL ===== -->
      <article class="card lg:col-span-1">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Tạo bản sao lưu (FULL)</h2>
        </div>

        <form class="mt-4 space-y-3" @submit.prevent="startExport">
          <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm">
              <span class="block text-xs text-slate-500 mb-1">Giới hạn mỗi file (MB)</span>
              <input type="number" min="1" max="50" class="w-full rounded-lg border px-3 py-2"
                     x-model.number="form.max_mb">
            </label>
            <label class="flex items-center gap-2 text-sm mt-6">
              <input type="checkbox" class="rounded" x-model="form.exclude_volatile">
              <span>Loại trừ bảng tạm/log (nếu đã cấu hình)</span>
            </label>
          </div>

          <div class="pt-2 flex items-center gap-3">
           <button class="btn-primary" :disabled="exporting">
            <span x-show="!exporting">Tạo backup FULL</span>
            <span x-show="exporting">Đang chạy…</span>
          </button>
            <div class="text-xs text-slate-500" x-show="exporting">Vui lòng giữ trang mở cho đến khi có kết quả.</div>
          </div>
          <template x-if="exportToast">
            <div class="mt-2 text-sm text-green-700" x-text="exportToast"></div>
          </template>
          <template x-if="exportError">
            <div class="mt-2 text-sm text-rose-700" x-text="exportError"></div>
          </template>
        </form>
      </article>

      <!-- ===== Khối: Danh sách backup + Chi tiết ===== -->
      <article class="card lg:col-span-2">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">Bản sao lưu gần đây</h2>
          <div class="text-sm text-slate-500">(click để xem chi tiết & tải file)</div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-4">
          <!-- List -->
          <div class="md:col-span-4">
            <div class="-mx-3 max-h-[60vh] overflow-y-auto">
              <ul class="divide-y divide-slate-100">
                <template x-if="!backups.length">
                  <li class="px-3 py-2 text-sm text-slate-500">Chưa có bản sao lưu nào.</li>
                </template>
                <template x-for="b in backups" :key="b.id">
                  <li>
                    <button class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between"
                            :class="selBackupId===b.id ? 'bg-blue-50/70 ring-1 ring-blue-400 rounded-md' : ''"
                            @click="openBackup(b.id)">
                      <div>
                        <div class="font-medium" x-text="b.id"></div>
                        <div class="text-xs text-slate-400" x-text="b.created_at || ''"></div>
                      </div>
                      <span class="badge-primary" x-show="selBackupId===b.id">đang xem</span>
                    </button>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <!-- Detail -->
          <div class="md:col-span-8">
            <template x-if="loadingDetail">
              <div class="p-3 text-sm text-slate-500">Đang tải manifest & danh sách file…</div>
              
            </template>

            <template x-if="!loadingDetail && !manifest">
              <div class="p-3 text-sm text-slate-500">Chọn một bản sao lưu ở cột bên trái để xem chi tiết.</div>
            </template>

            <template x-if="manifest">
              <div class="space-y-3">
                <div class="rounded-lg border p-3 bg-slate-50">
                  <div class="text-sm">
                    <b>Mã backup:</b> <span x-text="manifest.backup_id"></span> ·
                    <b>DB:</b> <span x-text="manifest.db"></span> ·
                    <b>MySQL:</b> <span x-text="manifest.version || '—'"></span> ·
                    <b>File ≤</b> <span x-text="Math.round(manifest.max_bytes/1024/1024) + 'MB'"></span>
                  </div>
                  <div class="mt-1 text-xs text-slate-500">
                    <a class="underline"
                       :href="downloadUrl(selBackupId,'manifest.json')">Tải manifest.json</a>
                    <span class="mx-2">•</span>
                    <span>Tổng bảng: <b x-text="manifest.order.length"></b></span>
                  </div>
                  <div class="mt-1 text-xs">
                    <a class="underline" :href="routeZip(selBackupId)">Tải tất cả (.zip)</a>
                    <span class="mx-2">•</span>
                    
                    <button class="text-green-700 hover:underline" @click="restoreFromSelected()" :disabled="!selBackupId">Khôi phục từ bản này</button>
                  </div>
                </div>

                <div class="-mx-3 overflow-y-auto max-h-[52vh]">
                  <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 sticky top-0">
                      <tr>
                        <th class="th">Bảng</th>
                        <th class="th">File</th>
                        <th class="th w-28">Kích thước</th>
                        <th class="th w-20 text-right">Tải</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                      <template x-for="f in files" :key="f.path">
                        <tr class="hover:bg-slate-50">
                          <td class="td" x-text="f.table"></td>
                          <td class="td">
                            <code x-text="f.path"></code>
                          </td>
                          <td class="td" x-text="prettySize(f.size)"></td>
                          <td class="td text-right">
                            <a class="text-blue-600 hover:underline"
                               :href="downloadUrl(selBackupId, f.path)">Tải</a>
                          </td>
                        </tr>
                      </template>
                      <template x-if="!files.length">
                        <tr><td class="td" colspan="4">Không có file dữ liệu.</td></tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>
          </div>
        </div>
      </article>
    </div>

    <!-- ===== Khối: Restore (upload tuần tự) ===== -->
    <section class="card" style="display:none" x-cloak>
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Khôi phục (upload tuần tự)</h2>
        <div class="text-sm text-slate-500" x-show="restore.sessionId">
          Session: <b x-text="restore.sessionId"></b>
        </div>
      </div>

      <div class="mt-3 grid md:grid-cols-12 gap-4">
        <div class="md:col-span-4 space-y-2">
         <button class="btn-soft w-full" @click="createRestoreSession" :disabled="restore.creating">
          <span x-show="!restore.creating">Tạo phiên restore</span>
          <span x-show="restore.creating">Đang tạo…</span>
        </button>
          <div class="text-xs text-slate-500">Mỗi lần khôi phục nên tạo 1 session mới.</div>
        </div>

        <div class="md:col-span-8">
          <div class="grid md:grid-cols-12 gap-3">
            <div class="md:col-span-8">
              <label class="block text-xs text-slate-500 mb-1">
                Chọn <b>cả thư mục</b> backup (manifest + schema + data)
              </label>
              <input type="file" id="dirPick" class="w-full rounded-lg border px-3 py-2"
                     webkitdirectory directory multiple @change="onPickFiles">
              <div class="mt-2 text-xs text-slate-500" x-show="restore.picked.length">
                Đã chọn <b x-text="restore.picked.length"></b> file ·
                Tổng <b x-text="prettySize(restore.totalBytes)"></b>
              </div>
            </div>
            <div class="md:col-span-4 flex items-end">
              <button class="btn-primary w-full" @click="uploadSequential"
                      :disabled="!restore.sessionId || !restore.picked.length || restore.uploading">
                <template x-show="!restore.uploading">Upload tuần tự</template>
                <template x-show="restore.uploading">Đang upload…</template>
              </button>
            </div>
          </div>

          <!-- progress -->
          <div class="mt-3" x-show="restore.uploading || restore.progress>0">
            <div class="w-full h-3 bg-slate-200 rounded-xl overflow-hidden">
              <div class="h-3 bg-blue-500" :style="`width:${restore.progress}%`"></div>
            </div>
            <div class="mt-1 text-xs text-slate-600">
              <span x-text="restore.uploadedCount"></span>/<span x-text="restore.picked.length"></span> file ·
              <span x-text="prettySize(restore.uploadedBytes)"></span>/<span x-text="prettySize(restore.totalBytes)"></span>
            </div>
          </div>

          <div class="mt-4 flex items-center gap-3">
            <button class="btn-soft"
                    @click="runImport"
                    :disabled="!restore.sessionId || restore.running">
              <template x-show="!restore.running">Bắt đầu khôi phục</template>
              <template x-show="restore.running">Đang khôi phục…</template>
            </button>
            <div class="text-sm" :class="restore.resultOk ? 'text-green-700' : 'text-rose-700'"
                 x-text="restore.resultMsg"></div>
          </div>
        </div>


      </div>
    </section>

      <!-- Đặt ở cuối trang (trướcendsection) hoặc ngay trong <div x-data="dbBackup"> -->
    <div x-show="exporting"
        class="fixed inset-0 z-[80] grid place-items-center bg-white/70 backdrop-blur-sm"
          style="display:none">
        <div class="flex flex-col items-center gap-3 p-6 rounded-2xl shadow-xl bg-white">
          <div class="h-10 w-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
          <div class="text-sm text-slate-600">Đang tạo bản sao lưu… vui lòng đợi</div>
        </div>
    </div>
    <div x-show="progress.info || exporting"
      class="fixed inset-0 z-[80] grid place-items-center bg-white/70 backdrop-blur-sm"
      style="display:none">
      <div class="flex flex-col items-center gap-3 p-6 rounded-2xl shadow-xl bg-white">
        <div class="h-10 w-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
        <template x-if="progress.info">
          <div class="text-sm text-slate-700 text-center">
            <div>Đang sao lưu bảng: <b x-text="progress.info.current_table || '—'"></b></div>
            <div class="mt-1">
              <span x-text="progress.info.current_index || 0"></span> /
              <span x-text="progress.info.total_tables || 0"></span> bảng
            </div>
            <div class="w-52 h-2 mt-2 bg-slate-200 rounded-full overflow-hidden">
              <div class="h-2 bg-blue-600" :style="`width:${Math.round((progress.info.current_index||0)*100/(progress.info.total_tables||1))}%`"></div>
            </div>
          </div>
        </template>
        <div class="text-xs text-slate-500" x-show="!progress.info">Đang chuẩn bị…</div>
      </div>
    </div>
    {{-- restore spinner --}}
    <div x-show="restore.running"
        class="fixed inset-0 z-[90] grid place-items-center bg-white/70 backdrop-blur-sm"
        style="display:none" aria-live="polite" aria-busy="true">
      <div class="flex flex-col items-center gap-3 p-6 rounded-2xl shadow-xl bg-white">
        <div class="h-10 w-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
        <div class="text-sm text-slate-700 text-center">
          Đang khôi phục dữ liệu… vui lòng đợi
        </div>
      </div>
    </div>
  </div>





@endsection

@push('scripts')
<script>
(() => {
  const R = {
    // Routes
    exportStart : @json(route('admin.backup.export.start')),
    listBase    : @json(url('admin/backup/list')),           // GET /{backupId}?json=1
    downloadBase: @json(url('admin/backup/download')),       // GET /{backupId}/{path}
    restore      : {
      session: @json(route('admin.restore.session')),       // POST
      upload : @json(url('admin/backup/restore/upload')),    // POST /{sessionId}
      run    : @json(url('admin/backup/restore/run')),       // POST /{sessionId}
    },
    csrf: @json(csrf_token()),
    // seed danh sách backup từ server (nếu controller ->index() truyền $dirs)
    seedBackups: @json(collect($dirs ?? [])->map(fn($d)=>basename($d))->values()),
  };

  function factory() {
    return {
      now: new Date().toLocaleString(),
      // backup list
      backups: [],               // [{id, created_at}]
      selBackupId: null,
      loadingDetail: false,
      manifest: null,
      files: [],
      // export form
      form: { max_mb: 5, exclude_volatile: false },
      exporting: false, exportToast: '', exportError: '',
      // restore
      restore: {
        sessionId: null, creating: false,
        picked: [], totalBytes: 0,
        uploading: false, uploadedCount: 0, uploadedBytes: 0, progress: 0,
        running: false, resultOk: false, resultMsg: '',
      },

      init(){
        this.reloadBackups();
        setInterval(()=> this.now = new Date().toLocaleString(), 1000);
      },

    // progress bar

    progress: { id: null, info: null, timer: null },

    startExport: async function(){
      this.exportToast=''; this.exportError=''; this.exporting=true;
      try{
        const res = await fetch(R.exportStart, {
          method:'POST',
          headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'},
          body: JSON.stringify({ max_mb: this.form.max_mb, exclude_volatile: this.form.exclude_volatile })
        });
        const j = res.ok ? await res.json().catch(()=>({})) : null;

        const id = j?.backupId;
        if (id) {
          this.progress.id = id;
          // bắt đầu poll
          this.progress.timer = setInterval(async () => {
            const u = @json(route('admin.backup.progress', ['backupId'=>'__ID__'])).replace('__ID__', encodeURIComponent(id));
            const r = await fetch(u, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (r.ok) {
              this.progress.info = await r.json();
              if (this.progress.info?.finished) { clearInterval(this.progress.timer); }
            }
          }, 1000);
          // hiển thị trong overlay
        }

        if (id) {
          this.backups.unshift({id, created_at:''});
          this.exportToast = 'Đã tạo bản sao lưu ' + id;
          this.openBackup(id);
        } else {
          this.exportToast = 'Đã gửi yêu cầu backup.';
        }
      } catch(e){
        this.exportError = 'Lỗi: ' + (e.message || e);
      } finally {
        this.exporting=false; // nếu muốn giữ overlay đến khi progress.done, bạn có thể delay đóng
      }
    },

      /* ===== helpers ===== */
      prettySize(n){
        if (n>=1024*1024) return (n/1024/1024).toFixed(1)+' MB';
        if (n>=1024) return (n/1024).toFixed(1)+' KB';
        return n+' B';
      },
      downloadUrl(backupId, relPath){
        return `${R.downloadBase}/${encodeURIComponent(backupId)}/${encodeURIComponent(relPath)}`;
      },

      /* ===== Backups ===== */
      routeZip(id){
        return @json(route('admin.backup.bundle', ['backupId' => '__ID__'])).replace('__ID__', encodeURIComponent(id));
      },
      async doUploadZip(e){
        if(!e.target.files?.length) return;
        const fd = new FormData(); fd.append('file', e.target.files[0]);
        const res = await fetch(@json(route('admin.backup.uploadZip')), {
          method:'POST', headers:{'X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'}, body: fd
        });
        const j = await res.json();
        alert(j.message || (j.ok?'Đã tải ZIP lên server.':'Lỗi'));
        if (j.ok && j.backupId) { this.reloadBackups(); this.openBackup(j.backupId); }
      },
      async restoreFromSelected(){
        if(!this.selBackupId) return;
        if(!confirm('Khôi phục CSDL từ backup '+this.selBackupId+' ?\n(Tất cả bảng sẽ bị ghi đè theo manifest.)')) return;

        this.restore.running = true;   // ← BẬT overlay
        try{
          const res = await fetch(
            @json(route('admin.backup.restoreFrom', ['backupId'=>'__ID__']))
              .replace('__ID__', encodeURIComponent(this.selBackupId)),
            { method:'POST',
              headers:{'X-CSRF-TOKEN':R.csrf,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
            }
          );
          const j = await res.json();
          alert(j.ok ? 'Khôi phục thành công.' : (j.message || 'Khôi phục thất bại.'));
        } catch(e){
          alert('Lỗi: ' + (e.message || e));
        } finally {
          this.restore.running = false; // ← TẮT overlay
        }
      },

      reloadBackups(){
        // Ưu tiên seed từ server; nếu bạn có API riêng, thay bằng fetch API
        this.backups = (R.seedBackups || []).map(id => ({id, created_at: ''}));
        // auto open newest
        if (this.backups.length) this.openBackup(this.backups[0].id);
      },

      async openBackup(backupId){
        this.selBackupId = backupId; this.manifest = null; this.files = [];
        this.loadingDetail = true;
        try{
          // yêu cầu controller hỗ trợ JSON khi có ?json=1
          const res = await fetch(`${R.listBase}/${backupId}?json=1`, {
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
          });
          if (res.ok){
            const j = await res.json();
            this.manifest = j.manifest;
            // j.files: [{table, path, size}] theo thứ tự
            this.files = j.files || [];
            // fallback nếu controller trả về khác format:
            if (!this.manifest && j.backupId) {
              this.manifest = { backup_id: j.backupId, order: [], max_bytes: this.form.max_mb*1024*1024 };
            }
          } else {
            this.manifest = null; this.files = [];
          }
        } catch(e){
          console.error(e); this.manifest=null; this.files=[];
        } finally {
          this.loadingDetail = false;
        }
      },

      async startExport(){
        this.exportToast=''; this.exportError=''; this.exporting=true;
        try{
          const res = await fetch(R.exportStart, {
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ max_mb: this.form.max_mb, exclude_volatile: this.form.exclude_volatile })
          });
          if (res.ok){
            const j = await res.json().catch(()=>({}));
            // kỳ vọng { backupId: "YYYYmmdd_HHMMSS" }
            if (j && j.backupId){
              // prepend vào danh sách
              this.backups.unshift({id: j.backupId, created_at: ''});
              this.exportToast = 'Đã tạo bản sao lưu ' + j.backupId;
              this.openBackup(j.backupId);
            } else {
              this.exportToast = 'Đã gửi yêu cầu backup. Vào danh sách để xem.';
            }
          } else {
            this.exportError = 'Không thể tạo backup (HTTP ' + res.status + ')';
          }
        } catch(e){
          this.exportError = 'Lỗi: ' + (e.message || e);
        } finally {
          this.exporting=false;
        }
      },

      /* ===== Restore ===== */
      async createRestoreSession(){
        this.restore.creating=true;
        try{
          const res = await fetch(R.restore.session, { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'} });
          const j = await res.json();
          this.restore.sessionId = j.session_id;
          this.restore.picked=[]; this.restore.totalBytes=0; this.restore.uploadedCount=0; this.restore.uploadedBytes=0; this.restore.progress=0;
        } finally { this.restore.creating=false; }
      },

      onPickFiles(e){
        const files = Array.from(e.target.files || []);
        if (!files.length) return;
        // Ưu tiên thứ tự: manifest.json -> schema/* -> data/*
        const manifest = files.filter(f => f.name==='manifest.json');
        const schema   = files.filter(f => (f.webkitRelativePath||'').includes('/schema/'));
        const data     = files.filter(f => (f.webkitRelativePath||'').includes('/data/'));
        // sau cùng: mọi thứ còn lại (nếu có)
        const others   = files.filter(f => !manifest.includes(f) && !schema.includes(f) && !data.includes(f));

        const ordered = [...manifest, ...schema.sort((a,b)=>(a.name>b.name?1:-1)), ...data.sort((a,b)=>(a.name>b.name?1:-1)), ...others];
        this.restore.picked = ordered;
        this.restore.totalBytes = ordered.reduce((s,f)=>s+f.size,0);
        this.restore.uploadedCount=0; this.restore.uploadedBytes=0; this.restore.progress=0;
      },

      async uploadSequential(){
        if (!this.restore.sessionId || !this.restore.picked.length) return;
        this.restore.uploading = true; this.restore.uploadedCount=0; this.restore.uploadedBytes=0; this.restore.progress=0;

        for (const f of this.restore.picked){
          const rel = f.webkitRelativePath || f.name; // giữ nguyên cây thư mục
          const fd = new FormData();
          fd.append('file', f);
          fd.append('relative_path', rel);
          const res = await fetch(`${R.restore.upload}/${encodeURIComponent(this.restore.sessionId)}`, {
            method:'POST', headers:{'X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'}, body: fd
          });
          if (!res.ok){ alert('Upload lỗi: ' + rel); this.restore.uploading=false; return; }
          this.restore.uploadedCount += 1;
          this.restore.uploadedBytes += f.size;
          this.restore.progress = Math.round(this.restore.uploadedBytes * 100 / this.restore.totalBytes);
        }

        this.restore.uploading=false;
        alert('Đã upload xong tất cả file của phiên.');
      },

      async runImport(){
        if (!this.restore.sessionId) return;
        this.restore.running = true; this.restore.resultOk=false; this.restore.resultMsg='';
        try{
          const res = await fetch(`${R.restore.run}/${encodeURIComponent(this.restore.sessionId)}`, {
            method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':R.csrf,'X-Requested-With':'XMLHttpRequest'}
          });
          const j = await res.json();
          this.restore.resultOk = !!j.ok;
          this.restore.resultMsg = j.ok ? 'Khôi phục thành công.' : (j.message || 'Khôi phục thất bại.');
        } catch(e){
          this.restore.resultOk = false; this.restore.resultMsg = 'Lỗi: ' + (e.message||e);
        } finally {
          this.restore.running=false;
        }
      },
    };
  }

  const register = () => Alpine.data('dbBackup', () => factory());
  if (window.Alpine) register();
  document.addEventListener('alpine:init', register);
})();
</script>
@endpush
