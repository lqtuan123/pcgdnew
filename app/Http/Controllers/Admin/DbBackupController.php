<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\DbBackupService;
use App\Services\DbRestoreService;
use ZipArchive;
class DbBackupController extends Controller
{
    public function index()
    {
        $dirs = collect(Storage::disk('local')->directories('backups'))
            ->sortDesc()->take(50);
        return view('admin.backup.index', compact('dirs'));
    }
    
    public function bundle(string $backupId)
    {
        $disk = Storage::disk(config('dbbackup.storage_disk', 'local'));
        $base = "backups/$backupId";
        abort_unless($disk->exists("$base/manifest.json"), 404, 'Không thấy manifest');

        $zipRel = "$base/bundle.zip";
        $zipAbs = $disk->path($zipRel);

        if (!$disk->exists($zipRel)) {
            // Tạo ZIP mới
            $zip = new ZipArchive();
            if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Không tạo được file ZIP');
            }
            $add = function($from, $to) use ($disk, $zip, &$add) {
                $full = $disk->path($from);
                if (is_dir($full)) {
                    $zip->addEmptyDir($to);
                    foreach (scandir($full) as $f) {
                        if ($f==='.' || $f==='..') continue;
                        $add("$from/$f", "$to/$f");
                    }
                } else {
                    $zip->addFile($full, $to);
                }
            };
            $add("$base/manifest.json", "manifest.json");
            if ($disk->exists("$base/schema")) $add("$base/schema", "schema");
            if ($disk->exists("$base/data"))   $add("$base/data",   "data");
            $zip->close();
        }

        return response()->download($zipAbs, "$backupId.zip");
    }
     public function restoreFromBackupId(string $backupId, Request $r, DbRestoreService $svc)
    {
        $opt = [
            'drop_and_create'        => filter_var($r->input('drop_and_create', true), FILTER_VALIDATE_BOOLEAN),
            'truncate_before_import' => filter_var($r->input('truncate_before_import', false), FILTER_VALIDATE_BOOLEAN),
        ];
        $res = $svc->importFromBackupDir($backupId, $opt);
        $code = $res['ok'] ? 200 : 422;
        return $r->wantsJson() ? response()->json($res, $code)
                               : back()->with($res['ok'] ? 'ok' : 'error', $res['message'] ?? 'Đã khôi phục');
    }

    public function uploadZipToBackups(Request $r)
    {
        $r->validate([
            'file' => 'required|file|mimes:zip|max:512000', // 500MB; chỉnh tùy bạn
            // nếu muốn: 'overwrite' => 'nullable|boolean'
        ]);

        $disk = Storage::disk(config('dbbackup.storage_disk','local'));
        $tmp  = $r->file('file')->getRealPath();

        // Đọc manifest bên trong ZIP để lấy backup_id (ưu tiên)
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            return response()->json(['ok'=>false,'message'=>'Không mở được ZIP'], 422);
        }
        $manifestJson = null;
        $index = $zip->locateName('manifest.json', ZipArchive::FL_NOCASE|ZipArchive::FL_NODIR);
        if ($index !== false) {
            $stream = $zip->getStream($zip->getNameIndex($index));
            $manifestJson = $stream ? stream_get_contents($stream) : null;
            if ($stream) fclose($stream);
        }
        $manifest = $manifestJson ? json_decode($manifestJson, true) : null;

        // Xác định backupId
        $backupId = $manifest['backup_id'] ?? pathinfo($r->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
        $backupId = preg_replace('/[^0-9_]/', '', $backupId) ?: now()->format('Ymd_His');

        $base = "backups/$backupId";
        if ($disk->exists($base)) {
            // nếu muốn ghi đè, bạn có thể xóa thư mục cũ ở đây
            // $disk->deleteDirectory($base);
        }
        $disk->makeDirectory($base);

        // Lưu bản ZIP lại (tuỳ thích)
        $disk->putFileAs("backups", $r->file('file'), "$backupId.zip");

        // Giải nén: manifest.json, schema/, data/
        for ($i=0; $i<$zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (substr($name, -1)==='/') { $disk->makeDirectory("$base/".rtrim($name,'/')); continue; }
            $stream = $zip->getStream($name);
            if (!$stream) continue;
            $contents = stream_get_contents($stream);
            fclose($stream);

            // Loại bỏ dấu / đầu, map đúng thư mục đích
            $name = ltrim($name, '/');
            if ($name === 'manifest.json') {
                $disk->put("$base/manifest.json", $contents);
            } else {
                $disk->makeDirectory("$base/".dirname($name));
                $disk->put("$base/$name", $contents);
            }
        }
        $zip->close();

        return response()->json(['ok'=>true,'backupId'=>$backupId,'message'=>"Đã tải lên & giải nén vào backups/$backupId"]);
    }
    public function startExport(Request $r, DbBackupService $svc)
    {
        // dd('Chức năng này đã bị vô hiệu hóa tạm thời.');
        $maxMb           = (int)($r->input('max_mb', 5));       // mặc định 5MB
        $excludeVolatile = (bool)$r->input('exclude_volatile'); // mặc định false
        $backupId        = $svc->exportFull($maxMb, $excludeVolatile);

        return redirect()->route('admin.backup.list', $backupId)
            ->with('ok', "Đã tạo backup $backupId (full).");
    }

    public function listFiles(string $backupId, Request $request)
    {
        $disk = Storage::disk(config('dbbackup.storage_disk', 'local'));
        $base = "backups/$backupId";
        $manifestPath = "$base/manifest.json";

        // 1) Kiểm tra manifest
        if (!$disk->exists($manifestPath)) {
            $payload = ['ok' => false, 'message' => "Manifest không tồn tại cho backup [$backupId]."];
            if ($request->wantsJson() || $request->boolean('json')) {
                return response()->json($payload, 404);
            }
            abort(404, $payload['message']);
        }

        // 2) Đọc & dựng danh sách file theo đúng thứ tự manifest
        $manifest = json_decode($disk->get($manifestPath), true) ?: [];
        $files = [];
        $order = $manifest['order'] ?? [];
        $parts = $manifest['parts'] ?? [];

        foreach ($order as $table) {
            foreach (($parts[$table] ?? []) as $part) {
                $rel = "data/$table/$part";
                $abs = "$base/$rel";
                $size = $disk->exists($abs) ? $disk->size($abs) : 0;
                $files[] = ['table' => $table, 'path' => $rel, 'size' => $size];
            }
        }

        // 3) Nếu client muốn JSON (AJAX hoặc ?json=1) → trả JSON
        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'ok'       => true,
                'backupId' => $backupId,
                'manifest' => $manifest,
                'files'    => $files,
            ]);
        }

        // 4) Mặc định: render view như cũ
        return view('admin.backup.list', compact('backupId', 'manifest', 'files'));
    }

    public function download(string $backupId, string $path)
    {
        $full = "backups/$backupId/$path";
        abort_unless(Storage::disk('local')->exists($full), 404);
        return Storage::disk('local')->download($full);
    }
}
