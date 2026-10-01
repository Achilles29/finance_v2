SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16j_audit_pos_skipped_destination_other_commit_lines.sql
-- Tujuan :
-- 1) Mendaftar commit line POS yang tidak terposting karena
--    destination_type jatuh ke OTHER
-- 2) Menunjukkan konteks produk/extra/material serta divisi default
--    produk vs divisi recipe agar akar masalah cepat terlihat
-- 3) Menjadi daftar target untuk CLI repair repost skipped commit line
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_pos_skipped_destination_other_lines;
CREATE TEMPORARY TABLE tmp_pos_skipped_destination_other_lines AS
SELECT
  cl.id AS commit_line_id,
  cl.commit_id,
  c.commit_no,
  COALESCE(c.commit_status, '') AS commit_status,
  c.order_id,
  COALESCE(o.order_no, '') AS order_no,
  COALESCE(o.status, '') AS order_status,
  cl.line_type,
  cl.order_line_id,
  cl.order_line_extra_id,
  cl.product_id,
  p.product_code,
  p.product_name,
  cl.extra_id,
  ex.extra_code,
  ex.extra_name,
  cl.source_kind,
  cl.material_id,
  m.material_code,
  m.material_name,
  cl.component_id,
  comp.component_code,
  comp.component_name,
  ROUND(COALESCE(cl.required_qty, 0), 4) AS required_qty,
  cl.required_uom_id,
  u.code AS required_uom_code,
  COALESCE(p.default_operational_division_id, 0) AS default_operational_division_id,
  COALESCE(od.code, '') AS default_operational_division_code,
  COALESCE(od.name, '') AS default_operational_division_name,
  COALESCE(rc.recipe_division_count, 0) AS recipe_division_count,
  COALESCE(rc.recipe_division_codes, '') AS recipe_division_codes,
  COALESCE(rc.target_division_id, 0) AS recipe_target_division_id,
  COALESCE(rt.code, '') AS recipe_target_division_code,
  COALESCE(rt.name, '') AS recipe_target_division_name,
  cl.movement_ref_type,
  cl.movement_ref_id,
  COALESCE(cl.notes, '') AS line_notes,
  COALESCE(j.status, '') AS latest_job_status,
  COALESCE(j.id, 0) AS latest_job_id,
  CASE
    WHEN COALESCE(rc.recipe_division_count, 0) = 1 THEN 'FIX_PRODUCT_DEFAULT_DIVISION_AND_REPOST'
    WHEN COALESCE(rc.recipe_division_count, 0) > 1 THEN 'REVIEW_MULTI_RECIPE_DIVISION'
    ELSE 'REVIEW_PRODUCT_WITHOUT_RECIPE_DIVISION'
  END AS suggested_repair
FROM pos_stock_commit_line cl
JOIN pos_stock_commit c ON c.id = cl.commit_id
LEFT JOIN pos_order o ON o.id = c.order_id
LEFT JOIN mst_product p ON p.id = cl.product_id
LEFT JOIN mst_extra ex ON ex.id = cl.extra_id
LEFT JOIN mst_material m ON m.id = cl.material_id
LEFT JOIN mst_component comp ON comp.id = cl.component_id
LEFT JOIN mst_uom u ON u.id = cl.required_uom_id
LEFT JOIN mst_operational_division od ON od.id = p.default_operational_division_id
LEFT JOIN (
  SELECT
    r.product_id,
    COUNT(DISTINCT r.source_division_id) AS recipe_division_count,
    MIN(r.source_division_id) AS target_division_id,
    GROUP_CONCAT(DISTINCT d.code ORDER BY d.code SEPARATOR ',') AS recipe_division_codes
  FROM mst_product_recipe r
  LEFT JOIN mst_operational_division d ON d.id = r.source_division_id
  WHERE r.source_division_id IS NOT NULL
  GROUP BY r.product_id
) rc ON rc.product_id = cl.product_id
LEFT JOIN mst_operational_division rt ON rt.id = rc.target_division_id
LEFT JOIN (
  SELECT x.snapshot_id, MAX(x.id) AS latest_job_id
  FROM pos_runtime_job x
  GROUP BY x.snapshot_id
) jl ON jl.snapshot_id = cl.commit_id
LEFT JOIN pos_runtime_job j ON j.id = jl.latest_job_id
WHERE COALESCE(cl.movement_ref_type, 'NONE') = 'NONE'
  AND COALESCE(cl.movement_ref_id, 0) = 0
  AND COALESCE(cl.notes, '') LIKE '%destination OTHER%';

ALTER TABLE tmp_pos_skipped_destination_other_lines
  ADD PRIMARY KEY (commit_line_id),
  ADD KEY idx_tmp_pos_skip_commit (commit_id, order_id),
  ADD KEY idx_tmp_pos_skip_product (product_id, extra_id, material_id),
  ADD KEY idx_tmp_pos_skip_suggested (suggested_repair);

-- ------------------------------------------------------------
-- A. Ringkasan bucket repair
-- ------------------------------------------------------------
SELECT
  suggested_repair,
  COUNT(*) AS total_lines,
  COUNT(DISTINCT commit_id) AS total_commits,
  COUNT(DISTINCT order_id) AS total_orders
FROM tmp_pos_skipped_destination_other_lines
GROUP BY suggested_repair
ORDER BY total_lines DESC, suggested_repair;

-- ------------------------------------------------------------
-- B. Detail line yang ter-skip
-- ------------------------------------------------------------
SELECT
  commit_line_id,
  commit_id,
  commit_no,
  commit_status,
  order_id,
  order_no,
  order_status,
  line_type,
  product_id,
  product_code,
  product_name,
  extra_id,
  extra_code,
  extra_name,
  source_kind,
  material_id,
  material_code,
  material_name,
  component_id,
  component_code,
  component_name,
  required_qty,
  required_uom_code,
  default_operational_division_code,
  recipe_division_codes,
  movement_ref_type,
  movement_ref_id,
  latest_job_status,
  latest_job_id,
  suggested_repair,
  line_notes
FROM tmp_pos_skipped_destination_other_lines
ORDER BY commit_id DESC, commit_line_id ASC;

-- ------------------------------------------------------------
-- C. Produk sumber yang paling sering memicu skip
-- ------------------------------------------------------------
SELECT
  product_id,
  product_code,
  product_name,
  default_operational_division_code,
  recipe_division_codes,
  COUNT(*) AS skipped_lines,
  COUNT(DISTINCT commit_id) AS affected_commits
FROM tmp_pos_skipped_destination_other_lines
GROUP BY
  product_id,
  product_code,
  product_name,
  default_operational_division_code,
  recipe_division_codes
ORDER BY skipped_lines DESC, affected_commits DESC, product_id;
