<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Dùng CREATE OR REPLACE để tiện chỉnh sửa sau này
       DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW v_survey_detail_flat AS
SELECT
  sfd.id                 AS detail_id,
  sfd.survey_form_id     AS survey_id,
  sfd.person_id,
  sfd.academic_year,
  sf.so_phieu,

  -- ĐỊA BÀN
  op.name AS old_province_name,
  od.name AS old_district_name,
  ow.name AS old_ward_name,
  np.name AS new_province_name,
  nw.name AS new_ward_name,
  vg.name AS village_name,

 op.id AS old_province_id,
  od.id AS old_district_id,
  ow.id AS old_ward_id,
  np.id AS new_province_id,
  nw.id AS new_ward_id,
  vg.id AS village_id,

  -- THÔNG TIN NGƯỜI
  p.ho_dem,
  p.ten,
  p.identity_number,
  p.phone         AS contact_phone,
  p.addressline,
  p.dob_year, p.dob_month, p.dob_day,
  p.is_female,
  p.ethnicity_id,
  p.religion_id,
  eth.name        AS ethnicity,
  rel.name        AS religion,
  rt.name         AS residence_type_name,
  rs.name         AS residence_status_name,

  -- Chủ hộ & quan hệ với chủ hộ
  hp.ho_dem       AS chuho_ho_dem,
  hp.ten          AS chuho_ten,
  hm.role         AS relationship_name,

  -- Liên lạc phụ huynh
  p.contact_name  AS contact_name,
  p.note          AS contact_note,

  -- HỌC TẬP
  pe.class_label        AS education_grade_name,
  eb.name               AS education_block_code,
  pe.school_id,
  pe.education_block_id,
  pe.graduation_level_id,
  pe.vocational_level_id,
  pe.is_repeat_grade,
  pe.hoc_hai_buoi,
  pe.trai_tuyen,
  COALESCE(pe.studying_district_custom, wsn.name, ws.name) AS studying_district,
  COALESCE(pe.school_name_custom, sch.name)              AS school_name,
  sch.code              AS school_code,
  gl.name               AS graduation_level_name,
  pe.is_bo_tuc,
  pe.graduation_year,
  vl.name               AS vocational_level_name,
  pe.vocational_grad_year,
  pe.graduation_certificate_no,
  pe.hoc_xong_lop, pe.hoc_xong_nam,
  pe.bo_hoc_lop,  pe.bo_hoc_nam,

  -- MÙ CHỮ
  lit.dang_hoc_lop   AS lit_dang_hoc_lop,
  lit.hoan_thanh_lop AS lit_hoan_thanh_lop,
  lit.tai_mu_chu_muc AS lit_tai_mu_muc,

  -- HOÀN CẢNH & KHUYẾT TẬT (tên cột trùng với columns() của bạn)
  psc.sc_id,
  sc.name            AS sc_name,
  pc.name            AS priority_name,
  psc.chi_tiet       AS sc_detail,

  -- 8 cờ khuyết tật theo danh mục (1/0)
   -- 8 cờ khuyết tật (so khớp id an toàn bằng FIND_IN_SET)
  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_vd.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_VAN_DONG,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_nn.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_NGHE_NOI,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_nh.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_NHIN,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_tk.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_THAN_KINH,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_tt.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_TRI_TUE,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_ht.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_HOC_TAP,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_tk2.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_TU_KY,

  CASE WHEN pd.id IS NOT NULL AND FIND_IN_SET(
      dc_kh.id, REPLACE(REPLACE(REPLACE(pd.disability_ids,'[',''),']',''),'"','')
  ) > 0 THEN 1 ELSE 0 END AS dis_KHAC,

  -- 2 cờ tổng hợp
  COALESCE(pd.has_certificate, 0) AS dis_has_certificate,
  COALESCE(pd.can_learn, 0)       AS dis_can_learn,

  -- Ngày sinh “xấp xỉ” để lọc/sort tuổi
  STR_TO_DATE(CONCAT(
    p.dob_year,'-',
    LPAD(COALESCE(NULLIF(p.dob_month,0),12),2,'0'),'-',
    LPAD(COALESCE(NULLIF(p.dob_day,0),31),2,'0')
  ), '%Y-%m-%d') AS dob_date

FROM survey_form_details sfd
JOIN survey_forms sf ON sf.id = sfd.survey_form_id
JOIN people       p  ON p.id  = sfd.person_id

-- Địa bàn
LEFT JOIN geo_units op ON op.id = sf.old_province_id
LEFT JOIN geo_units od ON od.id = sf.old_district_id
LEFT JOIN geo_units ow ON ow.id = sf.old_ward_id
LEFT JOIN geo_units np ON np.id = sf.new_province_id
LEFT JOIN geo_units nw ON nw.id = sf.new_ward_id
LEFT JOIN geo_units vg ON vg.id = sf.village_id

-- Danh mục người
LEFT JOIN ethnicities        eth ON eth.id = p.ethnicity_id
LEFT JOIN religions          rel ON rel.id = p.religion_id
LEFT JOIN residence_types     rt ON rt.id  = p.residence_type_id
LEFT JOIN residence_statuses  rs ON rs.id  = p.residence_status_id

-- Chủ hộ & quan hệ với chủ hộ
LEFT JOIN people hp ON hp.id = sf.head_person_id
LEFT JOIN household_members hm
       ON hm.household_id = sf.household_id
      AND hm.person_id    = p.id

-- Học tập
LEFT JOIN person_educations    pe  ON pe.id  = sfd.person_education_id
LEFT JOIN education_blocks     eb  ON eb.id  = pe.education_block_id
LEFT JOIN schools              sch ON sch.id = pe.school_id
LEFT JOIN geo_units wsn ON wsn.id = sch.new_ward_id
LEFT JOIN geo_units ws  ON ws.id  = sch.old_ward_id
LEFT JOIN graduation_levels    gl  ON gl.id  = pe.graduation_level_id
LEFT JOIN vocational_levels    vl  ON vl.id  = pe.vocational_level_id

-- Mù chữ
LEFT JOIN person_literacy      lit ON lit.id = sfd.literacy_id
LEFT JOIN priority_categories      pc ON pc.id = p.priority_category_id
-- Khuyết tật / Hoàn cảnh
LEFT JOIN person_disabilities           pd  ON pd.id  = sfd.person_disability_id
LEFT JOIN person_special_circumstances  psc ON psc.id = sfd.person_special_circumstance_id
LEFT JOIN special_circumstances         sc  ON sc.id  = psc.sc_id

-- Map từng mã danh mục khuyết tật -> id (để JSON_CONTAINS)
LEFT JOIN disability_categories dc_vd  ON dc_vd.code = 'VAN_DONG'
LEFT JOIN disability_categories dc_nn  ON dc_nn.code = 'NGHE_NOI'
LEFT JOIN disability_categories dc_nh  ON dc_nh.code = 'NHIN'
LEFT JOIN disability_categories dc_tk  ON dc_tk.code = 'THAN_KINH'
LEFT JOIN disability_categories dc_tt  ON dc_tt.code = 'TRI_TUE'
LEFT JOIN disability_categories dc_ht  ON dc_ht.code = 'HOC_TAP'
LEFT JOIN disability_categories dc_tk2 ON dc_tk2.code = 'TU_KY'
LEFT JOIN disability_categories dc_kh  ON dc_kh.code = 'KHAC'

WHERE
  sfd.is_deleted = 0
  AND sf.is_deleted = 0;

SQL);
    }

    public function down(): void
    {
        // Xoá view khi rollback
        DB::unprepared('DROP VIEW IF EXISTS v_survey_detail_flat');
    }
};
