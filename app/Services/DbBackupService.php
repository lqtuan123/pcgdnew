<?php
// app/Services/DbBackupService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DbBackupService
{
        public function importFromBackupDir(string $backupId, array $opt = []): array
    {
        $disk = Storage::disk(config('dbbackup.storage_disk','local'));
        $base = "backups/$backupId";
        if (!$disk->exists("$base/manifest.json")) {
            return ['ok'=>false,'message'=>"Không tìm thấy manifest cho $backupId"];
        }
        $manifest = json_decode($disk->get("$base/manifest.json"), true);

        $dropAndCreate = $opt['drop_and_create'] ?? config('dbbackup.drop_and_create', true);
        $truncate      = $opt['truncate_before_import'] ?? config('dbbackup.truncate_before_import', false);

        // 1) Schema
        foreach ($manifest['order'] as $t) {
            $schemaPath = "$base/schema/$t.sql";
            if ($disk->exists($schemaPath)) {
                if ($dropAndCreate) {
                    DB::statement("DROP TABLE IF EXISTS `$t`");
                }
                DB::unprepared($disk->get($schemaPath));
            }
        }

        // 2) Data
        DB::statement("SET FOREIGN_KEY_CHECKS=0");
        DB::statement("SET UNIQUE_CHECKS=0");
        try {
            foreach ($manifest['order'] as $t) {
                if ($truncate && !$dropAndCreate) {
                    try { DB::statement("TRUNCATE TABLE `$t`"); } catch (\Throwable $e) {}
                }
                foreach (($manifest['parts'][$t] ?? []) as $file) {
                    $path = "$base/data/$t/$file";
                    if ($disk->exists($path)) {
                        $this->importCsvPart($t, $disk->path($path), 2000);
                    }
                }
            }
        } finally {
            DB::statement("SET UNIQUE_CHECKS=1");
            DB::statement("SET FOREIGN_KEY_CHECKS=1");
        }

        return ['ok'=>true];
    }

    protected function importCsvPart(string $table, string $csvPath, int $batchSize = 2000): void
    {
        $fh = fopen($csvPath, 'r'); if (!$fh) return;
        $cols = fgetcsv($fh); if (!$cols) { fclose($fh); return; }

        $rows = [];
        DB::disableQueryLog();
        while (($row = fgetcsv($fh)) !== false) {
            $assoc = [];
            foreach ($cols as $i=>$c) $assoc[$c] = $row[$i] ?? null;
            $rows[] = $assoc;
            if (count($rows) >= $batchSize) { DB::table($table)->insert($rows); $rows = []; }
        }
        if ($rows) DB::table($table)->insert($rows);
        fclose($fh);
    }
    protected function writeProgress(string $base, array $data): void {
        Storage::disk('local')->put("$base/progress.json", json_encode($data));
    }

    public function exportFull(int $maxMb = 5, bool $excludeVolatile = false): string
    {
        $disk = Storage::disk('local');
        $backupId = now()->format('Ymd_His');
        $base = "backups/$backupId";
        $disk->makeDirectory("$base/schema");
        $disk->makeDirectory("$base/data");

        // 1) Lấy toàn bộ bảng
        $tables = $this->getAllTables($excludeVolatile);
       
        // 2) Sắp xếp theo phụ thuộc FK (parent → child)
        $order = $this->topoOrderByFk($tables);
        $progress = [
        'status'        => 'running',
        'total_tables'  => count($order),
        'current_index' => 0,
        'current_table' => null,
        'finished'      => false,
        'backup_id'     => $backupId,
        ];
        $this->writeProgress($base, $progress);
        $manifest = [
            'backup_id' => $backupId,
            'db'        => config('database.connections.mysql.database'),
            'created_at'=> now()->toDateTimeString(),
            'order'     => $order,
            'parts'     => [],
            'max_bytes' => $maxMb*1024*1024,
            'format'    => 'csv',
            'version'   => DB::selectOne('select version() v')->v ?? null,
        ];

        // 3) Ghi schema
       foreach ($order as $idx => $t) {
            $ddl = DB::selectOne("SHOW CREATE TABLE `$t`");
            $create = $ddl->{'Create Table'} ?? '';
            $disk->put("$base/schema/$t.sql", $create . ";\n");
          
               $progress['current_index'] = $idx + 1;
            $progress['current_table'] = $t;
            $this->writeProgress($base, $progress);
           
        }
 
        // 4) Ghi dữ liệu từng bảng, cắt ≤ 5MB
        foreach ($order as $idx => $t) {
            $manifest['parts'][$t] = $this->exportTableCsv($t, "$base/data", $manifest['max_bytes']);
           
         
        }
        
        // 5) Lưu manifest
        $disk->put("$base/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $progress['status']   = 'done';
        $progress['finished'] = true;
        $this->writeProgress($base, $progress);
        return $backupId;
    }

    protected function getAllTables(bool $excludeVolatile): array
    {
        $all = collect(DB::select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'"))
            ->map(fn($r)=>array_values((array)$r)[0])->values()->all();

        if (!$excludeVolatile) return $all;

        $patterns = config('dbbackup.volatile_tables', []);
        if (empty($patterns)) return $all;

        return array_values(array_filter($all, function($t) use ($patterns) {
            foreach ($patterns as $p) {
                if (str_contains($p, '%')) {
                    $regex = '/^'.str_replace('%','.*',preg_quote($p,'/')).'$/i';
                    if (preg_match($regex, $t)) return false;
                } elseif (strcasecmp($t, $p)===0) {
                    return false;
                }
            }
            return true;
        }));
    }

    protected function exportTableCsv(string $table, string $dataDir, int $maxBytes): array
    {
        $disk = Storage::disk('local');

        $cols = collect(DB::select("SHOW COLUMNS FROM `$table`"))->pluck('Field')->all();

        $pk = $this->primaryKey($table);
        $sql = "SELECT * FROM `$table`" . ($pk ? " ORDER BY `$pk` ASC" : "");

        $part = 1;
        $relDir = "$table";
        $disk->makeDirectory("$dataDir/$relDir");

        $currPath = "$dataDir/$relDir/{$table}.part{$part}.csv";
        $handle = fopen($disk->path($currPath), 'w');
        fputcsv($handle, $cols);
        $parts = [ "{$table}.part{$part}.csv" ];

        DB::disableQueryLog();
        foreach (DB::cursor($sql) as $row) {
            $arr = [];
            foreach ($cols as $c) $arr[] = data_get($row, $c);
            fputcsv($handle, $arr);

            // Cắt file theo kích thước
            if (ftell($handle) >= $maxBytes) {
                fclose($handle);
                $part++;
                $currPath = "$dataDir/$relDir/{$table}.part{$part}.csv";
                $handle = fopen($disk->path($currPath), 'w');
                fputcsv($handle, $cols);
                $parts[] = "{$table}.part{$part}.csv";
            }
        }
        if (is_resource($handle)) fclose($handle);
        return $parts;
    }

    protected function primaryKey(string $table): ?string
    {
        $res = DB::select("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'");
        return $res[0]->Column_name ?? null;
    }

    protected function topoOrderByFk(array $tables): array
    {
        $set = array_fill_keys($tables, true);
        $edges = []; foreach ($tables as $t) $edges[$t]=[];

        $db = config('database.connections.mysql.database');
        $rows = DB::select("
            SELECT TABLE_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$db]);

        foreach ($rows as $r) {
            $child = $r->TABLE_NAME;
            $parent = $r->REFERENCED_TABLE_NAME;
            if (isset($set[$child]) && isset($set[$parent])) $edges[$parent][] = $child;
        }

        $visited=[]; $stack=[]; $out=[];
        $dfs = function($u) use (&$dfs,&$visited,&$stack,&$edges,&$out) {
            $visited[$u]=true; $stack[$u]=true;
            foreach ($edges[$u] as $v) {
                if (!empty($stack[$v])) continue;
                if (empty($visited[$v])) $dfs($v);
            }
            $stack[$u]=false; $out[]=$u;
        };
        foreach ($tables as $t) if (empty($visited[$t])) $dfs($t);
        return $out; // parent trước child
    }
}
