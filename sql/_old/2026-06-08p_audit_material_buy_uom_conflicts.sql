SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08p_audit_material_buy_uom_conflicts.sql
-- Tujuan :
-- 1) Mengaudit material_id yang memiliki lebih dari satu buy_uom_id
--    di lintas master dan tabel stok/transaksi aktif
-- 2) Menemukan konflik exact profile_key antara tabel aktif dan
--    purchase catalog kanonik
-- 3) Menjadi dasar penentuan repair data item-centric berikutnya
--
-- Catatan:
-- - Script ini TIDAK mengubah data
-- - Fokus utama:
--   a) active purchase catalog
--   b) division monthly / opening / movement
--   c) FIFO lot
--   d) PO / receipt / request lines
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_material_buy_uom_audit_rows;
CREATE TEMPORARY TABLE tmp_material_buy_uom_audit_rows AS
SELECT
  'mst_item' AS source_table,
  i.id AS row_id,
  i.material_id,
  i.id AS item_id,
  i.buy_uom_id,
  i.content_uom_id,
  ROUND(COALESCE(i.content_per_buy, 1), 6) AS content_per_buy,
  '' AS profile_key,
  COALESCE(i.is_active, 1) AS active_flag
FROM mst_item i
WHERE COALESCE(i.material_id, 0) > 0
  AND COALESCE(i.buy_uom_id, 0) > 0
  AND COALESCE(i.is_active, 1) = 1

UNION ALL

SELECT
  'mst_purchase_catalog',
  c.id,
  c.material_id,
  c.item_id,
  c.buy_uom_id,
  c.content_uom_id,
  ROUND(COALESCE(c.content_per_buy, 1), 6),
  COALESCE(c.profile_key, ''),
  COALESCE(c.is_active, 1)
FROM mst_purchase_catalog c
WHERE COALESCE(c.material_id, 0) > 0
  AND COALESCE(c.buy_uom_id, 0) > 0
  AND COALESCE(c.is_active, 1) = 1

UNION ALL

SELECT
  'inv_division_monthly_stock',
  s.id,
  COALESCE(s.material_id, i.material_id),
  s.item_id,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  COALESCE(s.profile_key, ''),
  1
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
WHERE COALESCE(s.material_id, i.material_id, 0) > 0
  AND COALESCE(s.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'inv_division_stock_opening_snapshot',
  s.id,
  COALESCE(s.material_id, i.material_id),
  s.item_id,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  COALESCE(s.profile_key, ''),
  1
FROM inv_division_stock_opening_snapshot s
LEFT JOIN mst_item i ON i.id = s.item_id
WHERE COALESCE(s.material_id, i.material_id, 0) > 0
  AND COALESCE(s.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'inv_stock_movement_log',
  l.id,
  COALESCE(l.material_id, i.material_id),
  l.item_id,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6),
  COALESCE(l.profile_key, ''),
  1
FROM inv_stock_movement_log l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE l.movement_scope = 'DIVISION'
  AND COALESCE(l.material_id, i.material_id, 0) > 0
  AND COALESCE(l.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'inv_material_fifo_lot',
  f.id,
  COALESCE(f.material_id, i.material_id),
  f.item_id,
  f.buy_uom_id,
  f.content_uom_id,
  NULL,
  COALESCE(f.profile_key, ''),
  1
FROM inv_material_fifo_lot f
LEFT JOIN mst_item i ON i.id = f.item_id
WHERE COALESCE(f.material_id, i.material_id, 0) > 0
  AND COALESCE(f.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'pur_purchase_order_line',
  l.id,
  COALESCE(l.material_id, i.material_id),
  l.item_id,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.content_per_buy, 1), 6),
  COALESCE(l.profile_key, ''),
  1
FROM pur_purchase_order_line l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE COALESCE(l.material_id, i.material_id, 0) > 0
  AND COALESCE(l.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'pur_purchase_receipt_line',
  l.id,
  COALESCE(l.material_id, i.material_id),
  l.item_id,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.conversion_factor_to_content, 1), 6),
  COALESCE(l.profile_key, ''),
  1
FROM pur_purchase_receipt_line l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE COALESCE(l.material_id, i.material_id, 0) > 0
  AND COALESCE(l.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'pur_division_request_line',
  l.id,
  COALESCE(l.material_id, i.material_id),
  l.item_id,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6),
  COALESCE(l.profile_key, ''),
  1
FROM pur_division_request_line l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE COALESCE(l.material_id, i.material_id, 0) > 0
  AND COALESCE(l.buy_uom_id, 0) > 0

UNION ALL

SELECT
  'pur_store_request_line',
  l.id,
  COALESCE(l.material_id, i.material_id),
  l.item_id,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6),
  COALESCE(l.profile_key, ''),
  1
FROM pur_store_request_line l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE COALESCE(l.material_id, i.material_id, 0) > 0
  AND COALESCE(l.buy_uom_id, 0) > 0;

ALTER TABLE tmp_material_buy_uom_audit_rows
  ADD KEY idx_tmp_material_buy_uom_audit_material (material_id, buy_uom_id, source_table),
  ADD KEY idx_tmp_material_buy_uom_audit_profile (profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_material_buy_uom_conflicts;
CREATE TEMPORARY TABLE tmp_material_buy_uom_conflicts AS
SELECT
  material_id,
  COUNT(*) AS total_rows,
  COUNT(DISTINCT buy_uom_id) AS distinct_buy_uom_count
FROM tmp_material_buy_uom_audit_rows
GROUP BY material_id
HAVING COUNT(DISTINCT buy_uom_id) > 1;

ALTER TABLE tmp_material_buy_uom_conflicts
  ADD PRIMARY KEY (material_id);

DROP TEMPORARY TABLE IF EXISTS tmp_material_canonical_buy_uom;
CREATE TEMPORARY TABLE tmp_material_canonical_buy_uom AS
SELECT
  picked.material_id,
  picked.buy_uom_id,
  picked.content_uom_id,
  picked.content_per_buy,
  picked.source_table,
  picked.source_id
FROM (
  SELECT
    base.material_id,
    base.buy_uom_id,
    base.content_uom_id,
    base.content_per_buy,
    base.source_table,
    base.source_id,
    ROW_NUMBER() OVER (
      PARTITION BY base.material_id
      ORDER BY base.rank_bucket ASC, base.source_id DESC
    ) AS rn
  FROM (
    SELECT
      c.material_id,
      c.buy_uom_id,
      c.content_uom_id,
      ROUND(COALESCE(c.content_per_buy, 1), 6) AS content_per_buy,
      'mst_purchase_catalog' AS source_table,
      c.id AS source_id,
      1 AS rank_bucket
    FROM mst_purchase_catalog c
    WHERE COALESCE(c.material_id, 0) > 0
      AND COALESCE(c.is_active, 1) = 1

    UNION ALL

    SELECT
      i.material_id,
      i.buy_uom_id,
      i.content_uom_id,
      ROUND(COALESCE(i.content_per_buy, 1), 6),
      'mst_item',
      i.id,
      2
    FROM mst_item i
    WHERE COALESCE(i.material_id, 0) > 0
      AND COALESCE(i.is_active, 1) = 1
  ) base
) picked
WHERE picked.rn = 1;

ALTER TABLE tmp_material_canonical_buy_uom
  ADD PRIMARY KEY (material_id);

-- ------------------------------------------------------------
-- A. Ringkasan material yang punya lebih dari satu buy_uom
-- ------------------------------------------------------------
SELECT
  c.material_id,
  m.material_code,
  m.material_name,
  c.distinct_buy_uom_count,
  c.total_rows,
  canon.buy_uom_id AS canonical_buy_uom_id,
  bu.code AS canonical_buy_uom_code,
  canon.content_uom_id AS canonical_content_uom_id,
  cu.code AS canonical_content_uom_code,
  canon.content_per_buy AS canonical_content_per_buy,
  canon.source_table AS canonical_source_table,
  canon.source_id AS canonical_source_id,
  GROUP_CONCAT(DISTINCT CONCAT(r.buy_uom_id, ':', COALESCE(bux.code, '?')) ORDER BY r.buy_uom_id SEPARATOR ', ') AS observed_buy_uoms
FROM tmp_material_buy_uom_conflicts c
JOIN mst_material m ON m.id = c.material_id
LEFT JOIN tmp_material_canonical_buy_uom canon ON canon.material_id = c.material_id
LEFT JOIN mst_uom bu ON bu.id = canon.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = canon.content_uom_id
JOIN tmp_material_buy_uom_audit_rows r ON r.material_id = c.material_id
LEFT JOIN mst_uom bux ON bux.id = r.buy_uom_id
GROUP BY
  c.material_id, m.material_code, m.material_name, c.distinct_buy_uom_count, c.total_rows,
  canon.buy_uom_id, bu.code, canon.content_uom_id, cu.code, canon.content_per_buy, canon.source_table, canon.source_id
ORDER BY c.distinct_buy_uom_count DESC, c.total_rows DESC, c.material_id;

-- ------------------------------------------------------------
-- B. Breakdown per material + source table + buy_uom
-- ------------------------------------------------------------
SELECT
  r.material_id,
  m.material_code,
  m.material_name,
  r.source_table,
  r.buy_uom_id,
  bu.code AS buy_uom_code,
  COUNT(*) AS row_count,
  COUNT(DISTINCT NULLIF(r.profile_key, '')) AS distinct_profile_keys,
  MIN(r.row_id) AS sample_min_row_id,
  MAX(r.row_id) AS sample_max_row_id
FROM tmp_material_buy_uom_audit_rows r
JOIN tmp_material_buy_uom_conflicts c ON c.material_id = r.material_id
JOIN mst_material m ON m.id = r.material_id
LEFT JOIN mst_uom bu ON bu.id = r.buy_uom_id
GROUP BY
  r.material_id, m.material_code, m.material_name, r.source_table, r.buy_uom_id, bu.code
ORDER BY r.material_id, r.source_table, r.buy_uom_id;

-- ------------------------------------------------------------
-- C. Exact profile_key mismatch terhadap purchase catalog
-- ------------------------------------------------------------
SELECT
  r.source_table,
  r.material_id,
  m.material_code,
  m.material_name,
  r.profile_key,
  COUNT(*) AS mismatched_rows,
  GROUP_CONCAT(DISTINCT CONCAT(r.buy_uom_id, ':', COALESCE(bur.code, '?')) ORDER BY r.buy_uom_id SEPARATOR ', ') AS row_buy_uoms,
  GROUP_CONCAT(DISTINCT CONCAT(c.buy_uom_id, ':', COALESCE(buc.code, '?')) ORDER BY c.buy_uom_id SEPARATOR ', ') AS catalog_buy_uoms
FROM tmp_material_buy_uom_audit_rows r
JOIN mst_purchase_catalog c
  ON c.profile_key = r.profile_key
 AND COALESCE(c.material_id, 0) = COALESCE(r.material_id, 0)
LEFT JOIN mst_material m ON m.id = r.material_id
LEFT JOIN mst_uom bur ON bur.id = r.buy_uom_id
LEFT JOIN mst_uom buc ON buc.id = c.buy_uom_id
WHERE r.profile_key <> ''
  AND (COALESCE(r.buy_uom_id, 0) <> COALESCE(c.buy_uom_id, 0)
    OR COALESCE(r.content_uom_id, 0) <> COALESCE(c.content_uom_id, 0))
GROUP BY r.source_table, r.material_id, m.material_code, m.material_name, r.profile_key
ORDER BY mismatched_rows DESC, r.material_id, r.source_table, r.profile_key;

-- ------------------------------------------------------------
-- D. Summary total
-- ------------------------------------------------------------
SELECT 'materials_with_multi_buy_uom_across_tables' AS metric, COUNT(*) AS total
FROM tmp_material_buy_uom_conflicts

UNION ALL

SELECT 'materials_with_multi_buy_uom_in_active_catalog', COUNT(*)
FROM (
  SELECT material_id
  FROM mst_purchase_catalog
  WHERE COALESCE(material_id, 0) > 0
    AND COALESCE(is_active, 1) = 1
  GROUP BY material_id
  HAVING COUNT(DISTINCT buy_uom_id) > 1
) x

UNION ALL

SELECT 'materials_with_multi_buy_uom_in_active_item_master', COUNT(*)
FROM (
  SELECT material_id
  FROM mst_item
  WHERE COALESCE(material_id, 0) > 0
    AND COALESCE(is_active, 1) = 1
  GROUP BY material_id
  HAVING COUNT(DISTINCT buy_uom_id) > 1
) x

UNION ALL

SELECT 'exact_profile_key_uom_mismatch_rows', COUNT(*)
FROM (
  SELECT 1
  FROM tmp_material_buy_uom_audit_rows r
  JOIN mst_purchase_catalog c
    ON c.profile_key = r.profile_key
   AND COALESCE(c.material_id, 0) = COALESCE(r.material_id, 0)
  WHERE r.profile_key <> ''
    AND (COALESCE(r.buy_uom_id, 0) <> COALESCE(c.buy_uom_id, 0)
      OR COALESCE(r.content_uom_id, 0) <> COALESCE(c.content_uom_id, 0))
  GROUP BY r.source_table, r.row_id
) x;
