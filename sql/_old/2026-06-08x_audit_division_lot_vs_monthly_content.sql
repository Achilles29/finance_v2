SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08x_audit_division_lot_vs_monthly_content.sql
-- Tujuan :
-- 1) Membandingkan saldo lot divisi vs closing monthly divisi per identity
-- 2) Mengklasifikasikan apakah lot raw sudah berbasis content,
--    masih berbasis buy qty legacy, atau benar-benar mismatch lain
-- 3) Membantu melacak sumber mismatch lot dari opening / receipt /
--    fulfillment / adjustment / lainnya
--
-- Catatan:
-- - qty_balance pada inv_material_fifo_lot secara desain seharusnya content qty
-- - kalau raw lot cocok ke closing_qty_buy, itu indikasi lot legacy berbasis buy qty
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_identity_balance;
CREATE TEMPORARY TABLE tmp_division_monthly_identity_balance AS
SELECT
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, i.material_id) AS material_id,
  s.buy_uom_id,
  s.content_uom_id,
  COALESCE(s.profile_key, '') AS profile_key,
  MAX(COALESCE(s.profile_name, '')) AS profile_name,
  ROUND(MAX(COALESCE(NULLIF(s.profile_content_per_buy, 0), 1)), 6) AS profile_content_per_buy,
  ROUND(SUM(COALESCE(s.closing_qty_buy, 0)), 4) AS closing_qty_buy,
  ROUND(SUM(COALESCE(s.closing_qty_content, 0)), 4) AS closing_qty_content,
  COUNT(*) AS monthly_rows
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
WHERE COALESCE(s.material_id, i.material_id, 0) > 0
GROUP BY
  s.division_id,
  COALESCE(s.destination_type, 'OTHER'),
  s.item_id,
  COALESCE(s.material_id, i.material_id),
  s.buy_uom_id,
  s.content_uom_id,
  COALESCE(s.profile_key, '');

ALTER TABLE tmp_division_monthly_identity_balance
  ADD KEY idx_monthly_identity (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_division_lot_identity_balance;
CREATE TEMPORARY TABLE tmp_division_lot_identity_balance AS
SELECT
  l.division_id,
  COALESCE(l.destination_type, 'OTHER') AS destination_type,
  l.item_id,
  COALESCE(l.material_id, i.material_id) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  ROUND(SUM(COALESCE(l.qty_in, 0)), 4) AS qty_in_raw,
  ROUND(SUM(COALESCE(l.qty_out, 0)), 4) AS qty_out_raw,
  ROUND(SUM(COALESCE(l.qty_balance, 0)), 4) AS qty_balance_raw,
  COUNT(*) AS lot_rows,
  GROUP_CONCAT(DISTINCT COALESCE(l.source_table, '?') ORDER BY COALESCE(l.source_table, '?') SEPARATOR ',') AS lot_sources
FROM inv_material_fifo_lot l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE l.location_scope = 'DIVISION'
  AND COALESCE(l.material_id, i.material_id, 0) > 0
GROUP BY
  l.division_id,
  COALESCE(l.destination_type, 'OTHER'),
  l.item_id,
  COALESCE(l.material_id, i.material_id),
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '');

ALTER TABLE tmp_division_lot_identity_balance
  ADD KEY idx_lot_identity (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_division_lot_monthly_compare;
CREATE TEMPORARY TABLE tmp_division_lot_monthly_compare AS
SELECT
  m.division_id,
  m.destination_type,
  m.item_id,
  m.material_id,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_key,
  m.profile_name,
  m.profile_content_per_buy,
  m.closing_qty_buy,
  m.closing_qty_content,
  COALESCE(l.qty_in_raw, 0) AS lot_qty_in_raw,
  COALESCE(l.qty_out_raw, 0) AS lot_qty_out_raw,
  COALESCE(l.qty_balance_raw, 0) AS lot_qty_balance_raw,
  ROUND(COALESCE(l.qty_balance_raw, 0) * m.profile_content_per_buy, 4) AS lot_qty_balance_scaled,
  COALESCE(l.lot_rows, 0) AS lot_rows,
  COALESCE(l.lot_sources, '') AS lot_sources,
  CASE
    WHEN ABS(COALESCE(l.qty_balance_raw, 0) - COALESCE(m.closing_qty_content, 0)) < 0.0001 THEN 'RAW_MATCH_CONTENT'
    WHEN ABS(COALESCE(l.qty_balance_raw, 0) - COALESCE(m.closing_qty_buy, 0)) < 0.0001 THEN 'RAW_MATCH_BUY'
    WHEN ABS((COALESCE(l.qty_balance_raw, 0) * m.profile_content_per_buy) - COALESCE(m.closing_qty_content, 0)) < 0.0001 THEN 'SCALED_MATCH_CONTENT'
    ELSE 'NO_MATCH'
  END AS match_mode,
  ROUND(COALESCE(l.qty_balance_raw, 0) - COALESCE(m.closing_qty_content, 0), 4) AS raw_minus_content,
  ROUND((COALESCE(l.qty_balance_raw, 0) * m.profile_content_per_buy) - COALESCE(m.closing_qty_content, 0), 4) AS scaled_minus_content
FROM tmp_division_monthly_identity_balance m
LEFT JOIN tmp_division_lot_identity_balance l
  ON l.division_id = m.division_id
 AND l.destination_type = m.destination_type
 AND l.item_id <=> m.item_id
 AND l.material_id <=> m.material_id
 AND l.buy_uom_id <=> m.buy_uom_id
 AND l.content_uom_id <=> m.content_uom_id
 AND l.profile_key <=> m.profile_key;

ALTER TABLE tmp_division_lot_monthly_compare
  ADD KEY idx_compare_mode (match_mode, material_id, division_id),
  ADD KEY idx_compare_profile (profile_key(32));

-- ------------------------------------------------------------
-- A. Ringkasan mode kecocokan lot vs monthly
-- ------------------------------------------------------------
SELECT
  match_mode,
  COUNT(*) AS identity_groups,
  SUM(CASE WHEN lot_rows > 0 THEN 1 ELSE 0 END) AS groups_with_lots
FROM tmp_division_lot_monthly_compare
GROUP BY match_mode
ORDER BY identity_groups DESC;

-- ------------------------------------------------------------
-- B. Breakdown sumber lot pada group mismatch / legacy buy-based
-- ------------------------------------------------------------
SELECT
  lot_sources,
  match_mode,
  COUNT(*) AS identity_groups
FROM tmp_division_lot_monthly_compare
WHERE lot_rows > 0
GROUP BY lot_sources, match_mode
ORDER BY identity_groups DESC, lot_sources ASC;

-- ------------------------------------------------------------
-- C. Detail identity yang lot-nya masih buy-based atau mismatch
-- ------------------------------------------------------------
SELECT
  c.division_id,
  d.code AS division_code,
  c.destination_type,
  c.item_id,
  i.item_code,
  i.item_name,
  c.material_id,
  m.material_code,
  m.material_name,
  c.buy_uom_id,
  bu.code AS buy_uom_code,
  c.content_uom_id,
  cu.code AS content_uom_code,
  c.profile_key,
  c.profile_name,
  c.profile_content_per_buy,
  c.closing_qty_buy,
  c.closing_qty_content,
  c.lot_qty_balance_raw,
  c.lot_qty_balance_scaled,
  c.raw_minus_content,
  c.scaled_minus_content,
  c.lot_rows,
  c.lot_sources,
  c.match_mode
FROM tmp_division_lot_monthly_compare c
LEFT JOIN mst_operational_division d ON d.id = c.division_id
LEFT JOIN mst_item i ON i.id = c.item_id
LEFT JOIN mst_material m ON m.id = c.material_id
LEFT JOIN mst_uom bu ON bu.id = c.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = c.content_uom_id
WHERE c.lot_rows > 0
  AND c.match_mode IN ('RAW_MATCH_BUY', 'NO_MATCH', 'SCALED_MATCH_CONTENT')
ORDER BY
  CASE c.match_mode
    WHEN 'NO_MATCH' THEN 0
    WHEN 'RAW_MATCH_BUY' THEN 1
    ELSE 2
  END,
  ABS(c.raw_minus_content) DESC,
  c.material_id,
  c.item_id;
