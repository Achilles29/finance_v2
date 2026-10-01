SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16m_audit_pos_cross_division_commit_mismatches.sql
-- Tujuan :
-- 1) Mengaudit commit line POS yang source division recipe/snapshot
--    berbeda dengan division movement yang benar-benar terpotong
-- 2) Menangkap kasus seperti produk BAR yang salah memotong bahan/
--    component milik KITCHEN
-- 3) Menjadi dasar repair historis rollback + repost
--
-- Catatan:
-- - Prioritaskan jalankan 2026-06-16l dulu agar snapshot source
--   division ikut tersimpan untuk commit baru
-- - Audit ini tetap fallback ke recipe aktif bila snapshot line lama
--   belum punya resolved_source_division_id
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_pos_cross_division_recipe_hint;
CREATE TEMPORARY TABLE tmp_pos_cross_division_recipe_hint AS
SELECT
  raw.commit_line_id,
  COALESCE(
    raw.component_source_division_id,
    raw.material_source_division_id,
    raw.first_recipe_source_division_id
  ) AS source_division_id,
  d.code AS source_division_code,
  d.name AS source_division_name
FROM (
  SELECT
    cl.id AS commit_line_id,
    (
      SELECT r.source_division_id
      FROM mst_product_recipe r
      JOIN mst_item ri ON ri.id = r.material_item_id
      WHERE r.product_id = cl.product_id
        AND COALESCE(cl.material_id, 0) > 0
        AND COALESCE(ri.material_id, 0) = COALESCE(cl.material_id, 0)
        AND r.source_division_id IS NOT NULL
      ORDER BY r.id ASC
      LIMIT 1
    ) AS material_source_division_id,
    (
      SELECT r.source_division_id
      FROM mst_product_recipe r
      WHERE r.product_id = cl.product_id
        AND COALESCE(cl.component_id, 0) > 0
        AND COALESCE(r.component_id, 0) = COALESCE(cl.component_id, 0)
        AND r.source_division_id IS NOT NULL
      ORDER BY r.id ASC
      LIMIT 1
    ) AS component_source_division_id,
    (
      SELECT r.source_division_id
      FROM mst_product_recipe r
      WHERE r.product_id = cl.product_id
        AND r.source_division_id IS NOT NULL
      ORDER BY r.id ASC
      LIMIT 1
    ) AS first_recipe_source_division_id
  FROM pos_stock_commit_line cl
) raw
LEFT JOIN mst_operational_division d
  ON d.id = COALESCE(
    raw.component_source_division_id,
    raw.material_source_division_id,
    raw.first_recipe_source_division_id
  )
WHERE COALESCE(
  raw.component_source_division_id,
  raw.material_source_division_id,
  raw.first_recipe_source_division_id,
  0
) > 0;

ALTER TABLE tmp_pos_cross_division_recipe_hint
  ADD PRIMARY KEY (commit_line_id),
  ADD KEY idx_tmp_pos_cross_division_recipe_hint_division (source_division_id);

DROP TEMPORARY TABLE IF EXISTS tmp_pos_cross_division_commit_audit;
CREATE TEMPORARY TABLE tmp_pos_cross_division_commit_audit AS
SELECT
  cl.id AS commit_line_id,
  cl.commit_id,
  c.order_id,
  cl.line_type,
  cl.source_kind,
  cl.product_id,
  cl.extra_id,
  cl.material_id,
  cl.component_id,
  cl.source_name_snapshot,
  cl.movement_ref_type,
  cl.movement_ref_id,
  COALESCE(cl.resolved_source_division_id, rh.source_division_id, 0) AS expected_division_id,
  COALESCE(cl.resolved_source_division_code, rh.source_division_code, '') AS expected_division_code,
  COALESCE(cl.resolved_source_division_name, rh.source_division_name, '') AS expected_division_name,
  CASE
    WHEN UPPER(TRIM(COALESCE(cl.resolved_source_division_code, rh.source_division_code, ''))) IN ('BAR', 'BEVERAGE')
      THEN CASE WHEN UPPER(TRIM(COALESCE(o.order_scope, 'REGULAR'))) = 'EVENT' THEN 'BAR_EVENT' ELSE 'BAR' END
    WHEN UPPER(TRIM(COALESCE(cl.resolved_source_division_code, rh.source_division_code, ''))) IN ('KITCHEN', 'FOOD')
      THEN CASE WHEN UPPER(TRIM(COALESCE(o.order_scope, 'REGULAR'))) = 'EVENT' THEN 'KITCHEN_EVENT' ELSE 'KITCHEN' END
    ELSE 'OTHER'
  END AS expected_destination_type,
  CASE
    WHEN COALESCE(cl.source_kind, '') = 'COMPONENT'
      THEN COALESCE(cli.division_id, cml.division_id, 0)
    ELSE COALESCE(mfi.division_id, sml.division_id, 0)
  END AS actual_division_id,
  CASE
    WHEN COALESCE(cl.source_kind, '') = 'COMPONENT'
      THEN COALESCE(cli.location_type, cml.location_type, 'OTHER')
    ELSE COALESCE(mfi.destination_type, sml.destination_type, 'OTHER')
  END AS actual_destination_type,
  CASE
    WHEN COALESCE(cl.source_kind, '') = 'COMPONENT' AND cli.id IS NOT NULL THEN 'COMPONENT_LOT_ISSUE'
    WHEN COALESCE(cl.source_kind, '') = 'COMPONENT' AND cml.id IS NOT NULL THEN 'COMPONENT_MOVEMENT'
    WHEN COALESCE(cl.source_kind, '') <> 'COMPONENT' AND mfi.id IS NOT NULL THEN 'FIFO_ISSUE'
    WHEN COALESCE(cl.source_kind, '') <> 'COMPONENT' AND sml.id IS NOT NULL THEN 'LEDGER_MOVEMENT'
    ELSE 'UNKNOWN'
  END AS actual_ref_kind
FROM pos_stock_commit_line cl
JOIN pos_stock_commit c ON c.id = cl.commit_id
LEFT JOIN pos_order o ON o.id = c.order_id
LEFT JOIN tmp_pos_cross_division_recipe_hint rh ON rh.commit_line_id = cl.id
LEFT JOIN inv_material_fifo_issue_log mfi
  ON COALESCE(cl.source_kind, '') <> 'COMPONENT'
 AND mfi.id = cl.movement_ref_id
 AND mfi.source_table = 'pos_stock_commit'
 AND mfi.source_id = c.id
 AND mfi.source_line_id = cl.id
LEFT JOIN inv_stock_movement_log sml
  ON COALESCE(cl.source_kind, '') <> 'COMPONENT'
 AND sml.id = cl.movement_ref_id
LEFT JOIN inv_component_lot_issue_log cli
  ON COALESCE(cl.source_kind, '') = 'COMPONENT'
 AND cli.id = cl.movement_ref_id
 AND cli.source_table = 'pos_stock_commit'
 AND cli.source_id = c.id
 AND cli.source_line_id = cl.id
LEFT JOIN inv_component_movement_log cml
  ON COALESCE(cl.source_kind, '') = 'COMPONENT'
 AND cml.id = cl.movement_ref_id
WHERE COALESCE(cl.movement_ref_id, 0) > 0
  AND COALESCE(cl.source_kind, '') IN ('MATERIAL', 'COMPONENT');

ALTER TABLE tmp_pos_cross_division_commit_audit
  ADD PRIMARY KEY (commit_line_id),
  ADD KEY idx_tmp_pos_cross_division_commit_audit_expected (expected_division_id, expected_destination_type),
  ADD KEY idx_tmp_pos_cross_division_commit_audit_actual (actual_division_id, actual_destination_type);

-- ------------------------------------------------------------
-- A. Ringkasan mismatch lintas divisi
-- ------------------------------------------------------------
SELECT
  COUNT(*) AS total_candidate_lines,
  SUM(
    CASE
      WHEN COALESCE(expected_division_id, 0) > 0
       AND expected_destination_type <> 'OTHER'
       AND (
         COALESCE(actual_division_id, 0) <> COALESCE(expected_division_id, 0)
         OR UPPER(TRIM(COALESCE(actual_destination_type, 'OTHER'))) <> UPPER(TRIM(COALESCE(expected_destination_type, 'OTHER')))
       )
      THEN 1 ELSE 0
    END
  ) AS total_cross_division_mismatch
FROM tmp_pos_cross_division_commit_audit;

-- ------------------------------------------------------------
-- B. Detail line yang salah potong lintas divisi
-- ------------------------------------------------------------
SELECT
  a.commit_line_id,
  a.commit_id,
  a.order_id,
  a.line_type,
  a.source_kind,
  a.product_id,
  p.product_code,
  p.product_name,
  a.extra_id,
  a.material_id,
  m.material_code,
  m.material_name,
  a.component_id,
  cp.component_code,
  cp.component_name,
  a.source_name_snapshot,
  a.expected_division_id,
  a.expected_division_code,
  a.expected_division_name,
  a.expected_destination_type,
  a.actual_division_id,
  od.code AS actual_division_code,
  od.name AS actual_division_name,
  a.actual_destination_type,
  a.actual_ref_kind,
  a.movement_ref_type,
  a.movement_ref_id
FROM tmp_pos_cross_division_commit_audit a
LEFT JOIN mst_product p ON p.id = a.product_id
LEFT JOIN mst_material m ON m.id = a.material_id
LEFT JOIN mst_component cp ON cp.id = a.component_id
LEFT JOIN mst_operational_division od ON od.id = a.actual_division_id
WHERE COALESCE(a.expected_division_id, 0) > 0
  AND a.expected_destination_type <> 'OTHER'
  AND (
    COALESCE(a.actual_division_id, 0) <> COALESCE(a.expected_division_id, 0)
    OR UPPER(TRIM(COALESCE(a.actual_destination_type, 'OTHER'))) <> UPPER(TRIM(COALESCE(a.expected_destination_type, 'OTHER')))
  )
ORDER BY a.commit_id, a.commit_line_id;
