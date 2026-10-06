<?php
// app/Services/DbRestoreService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DbRestoreService
{
    /**
     * Khôi phục từ thư mục backups/{backupId} trên server.
     * - Đọc manifest.json để lấy thứ tự bảng & danh sách part.
     * - (Mặc định) DROP TABLE và tạo lại schema trước khi import.
     * - Import CSV theo đúng thứ tự, mỗi part theo batch.
     */

    protected function getTableColumnMeta(string $table): array
{
    // cache đơn giản theo request
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    $db = config('database.connections.mysql.database');
    $rows = DB::select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ", [$db, $table]);

    $meta = [];
    foreach ($rows as $r) {
        $meta[$r->COLUMN_NAME] = [
            'type'       => strtolower($r->DATA_TYPE),
            'nullable'   => strtoupper($r->IS_NULLABLE) === 'YES',
        ];
    }
    return $cache[$table] = $meta;
}

protected function castCsvValue($val, ?array $meta)
{
    // Nulls
    if ($val === '' || $val === null) return null;

    if (!$meta) return $val; // không biết type thì giữ nguyên

    $type = $meta['type'];

    // Số
    if (in_array($type, ['tinyint','smallint','mediumint','int','bigint','decimal','float','double','year','bit'])) {
        // hợp lệ nếu là số, ngược lại nếu trống đã xử lý ở trên
        return is_numeric($val) ? $val : null;
    }

    // Ngày/giờ: nếu CSV để rỗng đã trả null; còn có giá trị thì để nguyên (chuỗi '2025-09-27 03:49:02'…)
    if (in_array($type, ['date','datetime','timestamp','time'])) {
        return $val === '' ? null : $val;
    }

    // JSON/BLOB/TEXT/VARCHAR… giữ nguyên
    return $val;
}


    public function importFromBackupDir(string $backupId, array $opt = []): array
    {
        $disk = Storage::disk(config('dbbackup.storage_disk', 'local'));
        $base = "backups/$backupId";
        if (!$disk->exists("$base/manifest.json")) {
            return ['ok'=>false,'message'=>"Không tìm thấy manifest cho [$backupId]."];
        }
        $manifest = json_decode($disk->get("$base/manifest.json"), true) ?: [];

        $dropAndCreate = $opt['drop_and_create']        ?? config('dbbackup.drop_and_create', true);
        $truncate      = $opt['truncate_before_import'] ?? config('dbbackup.truncate_before_import', false);
        $useLoadData   = (bool) (config('dbbackup.use_load_data', false));
        $batchSize     = (int)  ($opt['batch_size'] ?? 2000);

        // --- TẮT ràng buộc trước khi drop/create/import ---
        DB::statement("SET FOREIGN_KEY_CHECKS=0");
        DB::statement("SET UNIQUE_CHECKS=0");

        try {
            // 1) DROP theo thứ tự NGƯỢC (child → parent)
            if ($dropAndCreate) {
                $reverse = array_reverse($manifest['order'] ?? []);
                foreach ($reverse as $t) {
                    try { DB::statement("DROP TABLE IF EXISTS `$t`"); } catch (\Throwable $e) {}
                }
            }

            // 2) CREATE theo thứ tự chuẩn (parent → child)
            foreach (($manifest['order'] ?? []) as $t) {
                $schemaPath = "$base/schema/$t.sql";
                if ($disk->exists($schemaPath)) {
                    DB::unprepared($disk->get($schemaPath));
                }
            }

            // 3) IMPORT dữ liệu
            foreach (($manifest['order'] ?? []) as $t) {
                if (!$dropAndCreate && $truncate) {
                    try { DB::statement("TRUNCATE TABLE `$t`"); } catch (\Throwable $e) {}
                }
                foreach (($manifest['parts'][$t] ?? []) as $file) {
                    $rel = "$base/data/$t/$file";
                    if ($disk->exists($rel)) {
                        $abs = $disk->path($rel);
                        if ($useLoadData) $this->importCsvByLoadData($t, $abs);
                        else              $this->importCsvByInsert($t, $abs, $batchSize);
                    }
                }
            }
        } finally {
            // --- BẬT lại ràng buộc ---
            DB::statement("SET UNIQUE_CHECKS=1");
            DB::statement("SET FOREIGN_KEY_CHECKS=1");
        }

        return ['ok'=>true];
    }

    /**
     * Nhập CSV bằng INSERT theo lô (an toàn mặc định).
     * - Dòng đầu là header (tên cột).
     */
 protected function importCsvByInsert(string $table, string $csvPath, int $batchSize = 2000): void
{
    $fh = @fopen($csvPath, 'r');
    if (!$fh) return;

    $cols = fgetcsv($fh);
    if (!$cols) { fclose($fh); return; }

    $meta = $this->getTableColumnMeta($table);
    $rows = [];
    DB::disableQueryLog();

    while (($row = fgetcsv($fh)) !== false) {
        $assoc = [];
        foreach ($cols as $i => $c) {
            $assoc[$c] = $this->castCsvValue($row[$i] ?? null, $meta[$c] ?? null);
        }
        $rows[] = $assoc;

        if (count($rows) >= $batchSize) {
            DB::table($table)->insert($rows);
            $rows = [];
        }
    }
    if ($rows) DB::table($table)->insert($rows);
    fclose($fh);
}


    /**
     * Nhập CSV bằng LOAD DATA LOCAL INFILE (nhanh hơn rất nhiều).
     * Cần bật:
     *  - MySQL server: local_infile=1
     *  - Laravel PDO: PDO::MYSQL_ATTR_LOCAL_INFILE => true (trong config/database.php)
     */
    protected function writeProgress(string $base, array $data): void {
        Storage::disk('local')->put("$base/progress.json", json_encode($data));
    }

   protected function importCsvByLoadData(string $table, string $csvPath): void
    {
        $cols = collect(DB::select("SHOW COLUMNS FROM `$table`"))->pluck('Field')->all();
        if (empty($cols)) return;

        // dùng biến tạm @col cho tất cả cột → SET col = NULLIF(@col, '')
        $varList = implode(',', array_map(fn($c) => '@'.$c, $cols));
        $setList = implode(', ', array_map(fn($c) => "`$c` = NULLIF(@$c, '')", $cols));

        $path = addslashes($csvPath);
        $sql = "
            LOAD DATA LOCAL INFILE '$path'
            INTO TABLE `$table`
            CHARACTER SET utf8mb4
            FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\"'
            LINES TERMINATED BY '\\n'
            IGNORE 1 LINES ($varList)
            SET $setList
        ";
        DB::statement($sql);
    }

}
