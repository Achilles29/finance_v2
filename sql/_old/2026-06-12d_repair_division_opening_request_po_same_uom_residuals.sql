SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-12d_repair_division_opening_request_po_same_uom_residuals.sql
-- Tujuan :
-- 1) Membersihkan residu same-UOM invalid yang masih tersisa pada:
--    - inv_division_stock_opening_snapshot
--    - pur_division_request_line
--    - pur_purchase_order_line
-- 2) Menormalkan semantics profile:
--    buy_uom_id = content_uom_id => profile_content_per_buy/content_per_buy = 1
-- 3) Menyamakan qty_buy dengan qty_content pada lane histori yang masih
--    membawa konversi profile lama
--
-- Catatan:
-- - Script ini fokus ke residu setelah repair live 2026-06-11 dan warehouse
--   residual 2026-06-12.
-- - Catalog yang masih memakai profile sama dan masih invalid ikut
--   dinormalisasi agar profile lama tidak terus menularkan data kontradiktif.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair division opening/request/PO same-UOM residuals 2026-06-12';

DROP TEMPORARY TABLE IF EXISTS tmp_same_uom_residual_profiles;
CREATE TEMPORARY TABLE tmp_same_uom_residual_profiles AS
SELECT
  z.profile_key,
  MAX(z.item_id) AS item_id,
  MAX(z.material_id) AS material_id,
  MAX(z.buy_uom_id) AS buy_uom_id,
  MAX(z.content_uom_id) AS content_uom_id,
  ROUND(MAX(COALESCE(z.old_cpb, 1)), 6) AS old_cpb,
  MAX(z.target_uom_code) AS target_uom_code
FROM (
  SELECT
    s.profile_key,
    s.item_id,
    s.material_id,
    s.buy_uom_id,
    s.content_uom_id,
    s.profile_content_per_buy AS old_cpb,
    COALESCE(
      NULLIF(s.profile_content_uom_code, ''),
      NULLIF(s.profile_buy_uom_code, ''),
      cu.code,
      bu.code
    ) AS target_uom_code
  FROM inv_division_stock_opening_snapshot s
  LEFT JOIN mst_uom bu ON bu.id = s.buy_uom_id
  LEFT JOIN mst_uom cu ON cu.id = s.content_uom_id
  WHERE s.buy_uom_id IS NOT NULL
    AND s.content_uom_id IS NOT NULL
    AND s.buy_uom_id = s.content_uom_id
    AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001

  UNION ALL

  SELECT
    l.profile_key,
    l.item_id,
    l.material_id,
    l.buy_uom_id,
    l.content_uom_id,
    l.profile_content_per_buy AS old_cpb,
    COALESCE(
      NULLIF(l.profile_content_uom_code, ''),
      NULLIF(l.profile_buy_uom_code, ''),
      cu.code,
      bu.code
    ) AS target_uom_code
  FROM pur_division_request_line l
  LEFT JOIN mst_uom bu ON bu.id = l.buy_uom_id
  LEFT JOIN mst_uom cu ON cu.id = l.content_uom_id
  WHERE l.buy_uom_id IS NOT NULL
    AND l.content_uom_id IS NOT NULL
    AND l.buy_uom_id = l.content_uom_id
    AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001

  UNION ALL

  SELECT
    l.profile_key,
    l.item_id,
    l.material_id,
    l.buy_uom_id,
    l.content_uom_id,
    l.content_per_buy AS old_cpb,
    COALESCE(
      NULLIF(l.snapshot_content_uom_code, ''),
      NULLIF(l.snapshot_buy_uom_code, ''),
      cu.code,
      bu.code
    ) AS target_uom_code
  FROM pur_purchase_order_line l
  LEFT JOIN mst_uom bu ON bu.id = l.buy_uom_id
  LEFT JOIN mst_uom cu ON cu.id = l.content_uom_id
  WHERE l.buy_uom_id IS NOT NULL
    AND l.content_uom_id IS NOT NULL
    AND l.buy_uom_id = l.content_uom_id
    AND ABS(COALESCE(l.content_per_buy, 1) - 1) > 0.0001
) z
GROUP BY z.profile_key;

ALTER TABLE tmp_same_uom_residual_profiles
  ADD PRIMARY KEY (profile_key);

UPDATE mst_purchase_catalog c
JOIN tmp_same_uom_residual_profiles p ON p.profile_key = c.profile_key
SET
  c.content_per_buy = 1.000000,
  c.conversion_factor_to_content = 1.00000000,
  c.standard_price = CASE
    WHEN c.standard_price IS NULL THEN NULL
    ELSE ROUND(c.standard_price / NULLIF(p.old_cpb, 0), 2)
  END,
  c.last_unit_price = CASE
    WHEN c.last_unit_price IS NULL THEN NULL
    ELSE ROUND(c.last_unit_price / NULLIF(p.old_cpb, 0), 2)
  END,
  c.notes = LEFT(TRIM(CONCAT(
    COALESCE(c.notes, ''),
    CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | normalized referenced catalog'
  )), 255),
  c.updated_at = CURRENT_TIMESTAMP
WHERE c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_same_uom_residual_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE pur_division_request_line l
JOIN tmp_same_uom_residual_profiles p ON p.profile_key = l.profile_key
SET
  l.profile_content_per_buy = 1.000000,
  l.profile_buy_uom_code = COALESCE(p.target_uom_code, l.profile_buy_uom_code),
  l.profile_content_uom_code = COALESCE(p.target_uom_code, l.profile_content_uom_code),
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE pur_purchase_order_line l
JOIN tmp_same_uom_residual_profiles p ON p.profile_key = l.profile_key
SET
  l.content_per_buy = 1.000000,
  l.conversion_factor_to_content = 1.00000000,
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0), 4),
  l.unit_price = CASE
    WHEN ABS(COALESCE(l.qty_content, 0)) > 0.0001 THEN ROUND(COALESCE(l.line_subtotal, 0) / NULLIF(l.qty_content, 0), 2)
    ELSE ROUND(COALESCE(l.unit_price, 0) / NULLIF(p.old_cpb, 0), 2)
  END,
  l.snapshot_buy_uom_code = COALESCE(p.target_uom_code, l.snapshot_buy_uom_code),
  l.snapshot_content_uom_code = COALESCE(p.target_uom_code, l.snapshot_content_uom_code),
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.content_per_buy, 1) - 1) > 0.0001;

COMMIT;

SELECT 'target_profiles_repaired' AS metric, COUNT(*) AS total
FROM tmp_same_uom_residual_profiles
UNION ALL
SELECT 'division_opening_invalid_remaining', COUNT(*)
FROM inv_division_stock_opening_snapshot
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'request_line_invalid_remaining', COUNT(*)
FROM pur_division_request_line
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'po_line_invalid_remaining', COUNT(*)
FROM pur_purchase_order_line
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'referenced_catalog_invalid_remaining', COUNT(*)
FROM mst_purchase_catalog c
JOIN tmp_same_uom_residual_profiles p ON p.profile_key = c.profile_key
WHERE c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;
