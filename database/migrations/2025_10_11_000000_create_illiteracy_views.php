<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // -------- VIEW (SƠ ĐỒ MỚI) --------
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_illiteracy_village_new AS
SELECT
  x.academic_year,
  x.new_province_id,
  x.new_ward_id,
  x.village_id                        AS unit_id,
  COALESCE(x.village_name,'Không rõ') AS unit_name,

  /* Tổng dân số (toàn xã) */
  COUNT(*)                   AS pop_all,
  SUM(x.is_female=1)         AS pop_all_f,
  SUM(x.cond_dt)             AS pop_all_dt,
  SUM(x.cond_dt_f)           AS pop_all_dt_f,

  /* 15–25: dân số */
  SUM(x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))                           AS pop_15_25,
  SUM(x.is_female=1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))         AS pop_15_25_f,
  SUM(x.cond_dt AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))             AS pop_15_25_dt,
  SUM(x.cond_dt_f AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))           AS pop_15_25_dt_f,

  /* 15–25: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))           AS ill1_15_25,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))  AS ill1_15_25_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))     AS ill1_15_25_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))    AS ill1_15_25_ndt,

  /* 15–25: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))           AS ill2_15_25,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))  AS ill2_15_25_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))     AS ill2_15_25_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))    AS ill2_15_25_ndt,

  /* 15–35: dân số */
  SUM(x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))                           AS pop_15_35,
  SUM(x.is_female=1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))         AS pop_15_35_f,
  SUM(x.cond_dt AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))             AS pop_15_35_dt,
  SUM(x.cond_dt_f AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))           AS pop_15_35_dt_f,

  /* 15–35: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))           AS ill1_15_35,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))  AS ill1_15_35_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))     AS ill1_15_35_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))    AS ill1_15_35_ndt,

  /* 15–35: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))           AS ill2_15_35,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))  AS ill2_15_35_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))     AS ill2_15_35_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))    AS ill2_15_35_ndt,

  /* 15–60: dân số */
  SUM(x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))                           AS pop_15_60,
  SUM(x.is_female=1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))         AS pop_15_60_f,
  SUM(x.cond_dt AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))             AS pop_15_60_dt,
  SUM(x.cond_dt_f AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))           AS pop_15_60_dt_f,

  /* 15–60: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))           AS ill1_15_60,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))  AS ill1_15_60_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))     AS ill1_15_60_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))    AS ill1_15_60_ndt,

  /* 15–60: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))           AS ill2_15_60,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))  AS ill2_15_60_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))     AS ill2_15_60_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))    AS ill2_15_60_ndt,

  /* ≥61: dân số */
  SUM(x.dob_year <= (x.y1-61))                                              AS pop_ge_61,
  SUM(x.is_female=1 AND x.dob_year <= (x.y1-61))                            AS pop_ge_61_f,
  SUM(x.cond_dt AND x.dob_year <= (x.y1-61))                                 AS pop_ge_61_dt,
  SUM(x.cond_dt_f AND x.dob_year <= (x.y1-61))                               AS pop_ge_61_dt_f,

  /* ≥61: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year <= (x.y1-61))                               AS ill1_ge_61,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year <= (x.y1-61))             AS ill1_ge_61_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year <= (x.y1-61))                AS ill1_ge_61_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year <= (x.y1-61))               AS ill1_ge_61_ndt,

  /* ≥61: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year <= (x.y1-61))                               AS ill2_ge_61,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year <= (x.y1-61))             AS ill2_ge_61_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year <= (x.y1-61))                AS ill2_ge_61_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year <= (x.y1-61))               AS ill2_ge_61_ndt

FROM (
  SELECT
    v.*,
    CAST(SUBSTRING_INDEX(v.academic_year,'-',1) AS UNSIGNED)                         AS y1,
    /* DTTS & DTTS nữ */
    (UPPER(COALESCE(v.ethnicity,'')) <> 'KINH')                                      AS cond_dt,
    ((UPPER(COALESCE(v.ethnicity,'')) <> 'KINH') AND v.is_female=1)                  AS cond_dt_f,
    /* Mù chữ mức 1 */
    (
      (v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 3)
      OR (v.bo_hoc_lop IS NULL AND v.education_block_code IS NULL AND UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT'))
      OR COALESCE(v.lit_tai_mu_muc,0) = 1
    ) AS cond_ill1,
    /* Mù chữ mức 2 */
    (
      (v.bo_hoc_lop IS NULL AND v.education_block_code IS NULL AND UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT'))
      OR (v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 5)
      OR COALESCE(v.lit_tai_mu_muc,0) = 2
    ) AS cond_ill2
  FROM v_survey_detail_flat AS v
) AS x
GROUP BY x.academic_year, x.new_province_id, x.new_ward_id, x.village_id, x.village_name
ORDER BY x.village_name;
SQL);

        // -------- VIEW (SƠ ĐỒ CŨ) --------
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_illiteracy_village_old AS
SELECT
  x.academic_year,
  x.old_province_id,
  x.old_ward_id,
  x.village_id                        AS unit_id,
  COALESCE(x.village_name,'Không rõ') AS unit_name,

  /* Tổng dân số (toàn xã) */
  COUNT(*)                   AS pop_all,
  SUM(x.is_female=1)         AS pop_all_f,
  SUM(x.cond_dt)             AS pop_all_dt,
  SUM(x.cond_dt_f)           AS pop_all_dt_f,

  /* 15–25: dân số */
  SUM(x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))                           AS pop_15_25,
  SUM(x.is_female=1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))         AS pop_15_25_f,
  SUM(x.cond_dt AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))             AS pop_15_25_dt,
  SUM(x.cond_dt_f AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))           AS pop_15_25_dt_f,

  /* 15–25: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))           AS ill1_15_25,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))  AS ill1_15_25_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))     AS ill1_15_25_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))    AS ill1_15_25_ndt,

  /* 15–25: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))           AS ill2_15_25,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))  AS ill2_15_25_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))     AS ill2_15_25_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-25) AND (x.y1-15))    AS ill2_15_25_ndt,

  /* 15–35: dân số */
  SUM(x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))                           AS pop_15_35,
  SUM(x.is_female=1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))         AS pop_15_35_f,
  SUM(x.cond_dt AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))             AS pop_15_35_dt,
  SUM(x.cond_dt_f AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))           AS pop_15_35_dt_f,

  /* 15–35: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))           AS ill1_15_35,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))  AS ill1_15_35_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))     AS ill1_15_35_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))    AS ill1_15_35_ndt,

  /* 15–35: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))           AS ill2_15_35,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))  AS ill2_15_35_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))     AS ill2_15_35_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-35) AND (x.y1-15))    AS ill2_15_35_ndt,

  /* 15–60: dân số */
  SUM(x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))                           AS pop_15_60,
  SUM(x.is_female=1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))         AS pop_15_60_f,
  SUM(x.cond_dt AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))             AS pop_15_60_dt,
  SUM(x.cond_dt_f AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))           AS pop_15_60_dt_f,

  /* 15–60: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))           AS ill1_15_60,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))  AS ill1_15_60_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))     AS ill1_15_60_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))    AS ill1_15_60_ndt,

  /* 15–60: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))           AS ill2_15_60,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))  AS ill2_15_60_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))     AS ill2_15_60_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year BETWEEN (x.y1-60) AND (x.y1-15))    AS ill2_15_60_ndt,

  /* ≥61: dân số */
  SUM(x.dob_year <= (x.y1-61))                                              AS pop_ge_61,
  SUM(x.is_female=1 AND x.dob_year <= (x.y1-61))                            AS pop_ge_61_f,
  SUM(x.cond_dt AND x.dob_year <= (x.y1-61))                                 AS pop_ge_61_dt,
  SUM(x.cond_dt_f AND x.dob_year <= (x.y1-61))                               AS pop_ge_61_dt_f,

  /* ≥61: mức 1 */
  SUM(x.cond_ill1 AND x.dob_year <= (x.y1-61))                               AS ill1_ge_61,
  SUM(x.cond_ill1 AND x.is_female=1 AND x.dob_year <= (x.y1-61))             AS ill1_ge_61_f,
  SUM(x.cond_ill1 AND x.cond_dt  AND x.dob_year <= (x.y1-61))                AS ill1_ge_61_dt,
  SUM(x.cond_ill1 AND x.cond_dt_f AND x.dob_year <= (x.y1-61))               AS ill1_ge_61_ndt,

  /* ≥61: mức 2 */
  SUM(x.cond_ill2 AND x.dob_year <= (x.y1-61))                               AS ill2_ge_61,
  SUM(x.cond_ill2 AND x.is_female=1 AND x.dob_year <= (x.y1-61))             AS ill2_ge_61_f,
  SUM(x.cond_ill2 AND x.cond_dt  AND x.dob_year <= (x.y1-61))                AS ill2_ge_61_dt,
  SUM(x.cond_ill2 AND x.cond_dt_f AND x.dob_year <= (x.y1-61))               AS ill2_ge_61_ndt

FROM (
  SELECT
    v.*,
    CAST(SUBSTRING_INDEX(v.academic_year,'-',1) AS UNSIGNED)                         AS y1,
    (UPPER(COALESCE(v.ethnicity,'')) <> 'KINH')                                      AS cond_dt,
    ((UPPER(COALESCE(v.ethnicity,'')) <> 'KINH') AND v.is_female=1)                  AS cond_dt_f,
    (
      (v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 3)
      OR (v.bo_hoc_lop IS NULL AND v.education_block_code IS NULL AND UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT'))
      OR COALESCE(v.lit_tai_mu_muc,0) = 1
    ) AS cond_ill1,
    (
      (v.bo_hoc_lop IS NULL AND v.education_block_code IS NULL AND UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT'))
      OR (v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 5)
      OR COALESCE(v.lit_tai_mu_muc,0) = 2
    ) AS cond_ill2
  FROM v_survey_detail_flat AS v
) AS x
GROUP BY x.academic_year, x.old_province_id, x.old_ward_id, x.village_id, x.village_name
ORDER BY x.village_name;
SQL);

        // -------- OPTIONAL: tạo index nếu v_survey_detail_flat là bảng thực (không phải VIEW) --------
        // Nếu là VIEW, block này sẽ tự bỏ qua.
        $isBaseTable = DB::scalar("
            SELECT CASE WHEN TABLE_TYPE='BASE TABLE' THEN 1 ELSE 0 END
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_survey_detail_flat'
            LIMIT 1
        ");

        if ((int)$isBaseTable === 1) {
            // Helper tạo index nếu chưa có
            $ensureIndex = function (string $indexName, string $sql) {
                $exists = DB::scalar("
                    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'v_survey_detail_flat'
                      AND INDEX_NAME = ?
                ", [$indexName]);
                if ((int)$exists === 0) {
                    DB::statement($sql);
                }
            };

            // Các index gợi ý (đặt tên ngắn gọn để tránh quá 64 ký tự)
            $ensureIndex('idx_vflat_year_newgeo_vil',
                "CREATE INDEX idx_vflat_year_newgeo_vil ON v_survey_detail_flat (academic_year, new_province_id, new_ward_id, village_id)"
            );
            $ensureIndex('idx_vflat_year_oldgeo_vil',
                "CREATE INDEX idx_vflat_year_oldgeo_vil ON v_survey_detail_flat (academic_year, old_province_id, old_ward_id, village_id)"
            );
            $ensureIndex('idx_vflat_year_dob',
                "CREATE INDEX idx_vflat_year_dob ON v_survey_detail_flat (academic_year, dob_year)"
            );
        }
    }

    public function down(): void
    {
        // Xóa view, index sẽ tự bị drop nếu là VIEW; nếu đã tạo index trên bảng thực thì không đụng.
        DB::unprepared("DROP VIEW IF EXISTS vw_illiteracy_village_new;");
        DB::unprepared("DROP VIEW IF EXISTS vw_illiteracy_village_old;");
    }
};
