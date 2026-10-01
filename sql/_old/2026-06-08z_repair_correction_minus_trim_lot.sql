SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08z_repair_correction_minus_trim_lot.sql
-- Tujuan :
--   Lanjutan repair: tangani sisa CORRECTION_MINUS dan SCALE_BUY residual
--   (lot balance masih lebih dari stok isi / closing_qty_content)
--
-- Kasus:
--   A) FULL_CLOSE  : closing_content = 0  → tutup semua lot OPEN identity itu
--   B) TRIM        : closing_content > 0  → kurangi lot OPEN terbaru sbesar kelebihan
--
-- Catatan:
--   - Script ini AMAN dijalankan berkali-kali (idempotent via guard WHERE excess > 0.0001)
--   - Jika lot terbaru tidak punya cukup balance, sisa kelebihan dilaporkan di verifikasi akhir
-- ============================================================

-- ===========================================================
-- TAHAP 0: Re-derive current state & preview
-- ===========================================================

DROP TEMPORARY TABLE IF EXISTS tmp_z_monthly;
CREATE TEMPORARY TABLE tmp_z_monthly AS
SELECT
  s.division_id,
  s.destination_type,
  s.item_id,
  COALESCE(s.material_id, mi.material_id)             AS material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_key,
  ROUND(SUM(COALESCE(s.closing_qty_content, 0)), 4)   AS closing_content
FROM inv_division_monthly_stock s
LEFT JOIN mst_item mi ON mi.id = s.item_id
WHERE COALESCE(s.material_id, mi.material_id, 0) > 0
GROUP BY
  s.division_id, s.destination_type, s.item_id,
  COALESCE(s.material_id, mi.material_id),
  s.buy_uom_id, s.content_uom_id, s.profile_key;

ALTER TABLE tmp_z_monthly
  ADD KEY idx_zm (division_id, destination_type, item_id, material_id,
                  buy_uom_id, content_uom_id, profile_key(32));

-- Snapshot lot saat ini (termasuk material dari mst_item)
DROP TEMPORARY TABLE IF EXISTS tmp_z_lot_snapshot;
CREATE TEMPORARY TABLE tmp_z_lot_snapshot AS
SELECT
  l.id,
  l.division_id,
  l.destination_type,
  l.item_id,
  COALESCE(l.material_id, mi.material_id)             AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
  l.qty_in,
  l.qty_out,
  l.qty_balance,
  l.status,
  l.receipt_date
FROM inv_material_fifo_lot l
LEFT JOIN mst_item mi ON mi.id = l.item_id
WHERE l.location_scope = 'DIVISION'
  AND COALESCE(l.material_id, mi.material_id, 0) > 0;

ALTER TABLE tmp_z_lot_snapshot
  ADD KEY idx_zls_id (id),
  ADD KEY idx_zls_identity (division_id, destination_type, item_id, material_id,
                             buy_uom_id, content_uom_id, profile_key(32), status, qty_balance);

DROP TEMPORARY TABLE IF EXISTS tmp_z_lot_agg;
CREATE TEMPORARY TABLE tmp_z_lot_agg AS
SELECT
  division_id, destination_type, item_id, material_id,
  buy_uom_id, content_uom_id, profile_key,
  ROUND(SUM(qty_balance), 4) AS total_lot_balance
FROM tmp_z_lot_snapshot
GROUP BY division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key;

ALTER TABLE tmp_z_lot_agg
  ADD KEY idx_zla (division_id, destination_type, item_id, material_id,
                   buy_uom_id, content_uom_id, profile_key(32));

-- Identifikasi identity group yang lot > stok
DROP TEMPORARY TABLE IF EXISTS tmp_z_plan;
CREATE TEMPORARY TABLE tmp_z_plan AS
SELECT
  m.division_id,
  m.destination_type,
  m.item_id,
  m.material_id,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_key,
  m.closing_content,
  la.total_lot_balance,
  ROUND(la.total_lot_balance - m.closing_content, 4) AS excess,
  CASE WHEN m.closing_content < 0.0001 THEN 'FULL_CLOSE' ELSE 'TRIM' END AS action
FROM tmp_z_monthly m
JOIN tmp_z_lot_agg la
  ON la.division_id      = m.division_id
 AND la.destination_type <=> m.destination_type
 AND la.item_id         <=> m.item_id
 AND la.material_id     <=> m.material_id
 AND la.buy_uom_id      <=> m.buy_uom_id
 AND la.content_uom_id  <=> m.content_uom_id
 AND la.profile_key     <=> m.profile_key
WHERE (la.total_lot_balance - m.closing_content) > 0.0001;

ALTER TABLE tmp_z_plan
  ADD KEY idx_zp_action   (action),
  ADD KEY idx_zp_identity (division_id, destination_type, item_id, material_id,
                           buy_uom_id, content_uom_id, profile_key(32));

-- ---- Preview 0-A: Ringkasan ----
SELECT
  action,
  COUNT(*) AS identity_groups,
  ROUND(SUM(excess), 4) AS total_excess
FROM tmp_z_plan
GROUP BY action;

-- ---- Preview 0-B: Detail ----
SELECT
  rp.action,
  d.code                                                AS division_code,
  rp.destination_type,
  i.item_code, i.item_name,
  mat.material_code, mat.material_name,
  bu.code AS buy_uom, cu.code AS content_uom,
  rp.closing_content,
  rp.total_lot_balance,
  rp.excess
FROM tmp_z_plan rp
LEFT JOIN mst_operational_division d  ON d.id  = rp.division_id
LEFT JOIN mst_item                 i  ON i.id   = rp.item_id
LEFT JOIN mst_material           mat  ON mat.id = rp.material_id
LEFT JOIN mst_uom                 bu  ON bu.id  = rp.buy_uom_id
LEFT JOIN mst_uom                 cu  ON cu.id  = rp.content_uom_id
ORDER BY rp.action, rp.excess DESC, rp.material_id;

-- ===========================================================
-- TAHAP 1: Identifikasi lot yang akan diubah
-- ===========================================================

-- Case A: semua lot OPEN di identity dengan stok=0
DROP TEMPORARY TABLE IF EXISTS tmp_z_lots_to_close;
CREATE TEMPORARY TABLE tmp_z_lots_to_close AS
SELECT lx.id
FROM tmp_z_lot_snapshot lx
JOIN tmp_z_plan rp
  ON rp.division_id      = lx.division_id
 AND rp.destination_type <=> lx.destination_type
 AND rp.item_id         <=> lx.item_id
 AND rp.material_id     <=> lx.material_id
 AND rp.buy_uom_id      <=> lx.buy_uom_id
 AND rp.content_uom_id  <=> lx.content_uom_id
 AND rp.profile_key     <=> lx.profile_key
WHERE rp.action = 'FULL_CLOSE'
  AND lx.status = 'OPEN'
  AND lx.qty_balance > 0;

ALTER TABLE tmp_z_lots_to_close ADD PRIMARY KEY (id);

SELECT 'lots_to_close' AS info, COUNT(*) AS total FROM tmp_z_lots_to_close;

-- Case B: lot OPEN terbaru (MAX id) per identity dengan stok>0
DROP TEMPORARY TABLE IF EXISTS tmp_z_trim_target;
CREATE TEMPORARY TABLE tmp_z_trim_target AS
SELECT
  MAX(lx.id)   AS lot_id,
  rp.excess
FROM tmp_z_plan rp
JOIN tmp_z_lot_snapshot lx
  ON lx.division_id      = rp.division_id
 AND lx.destination_type <=> rp.destination_type
 AND lx.item_id         <=> rp.item_id
 AND lx.material_id     <=> rp.material_id
 AND lx.buy_uom_id      <=> rp.buy_uom_id
 AND lx.content_uom_id  <=> rp.content_uom_id
 AND lx.profile_key     <=> rp.profile_key
 AND lx.status = 'OPEN'
 AND lx.qty_balance > 0
WHERE rp.action = 'TRIM'
GROUP BY
  rp.division_id, rp.destination_type, rp.item_id, rp.material_id,
  rp.buy_uom_id, rp.content_uom_id, rp.profile_key, rp.excess;

ALTER TABLE tmp_z_trim_target ADD PRIMARY KEY (lot_id);

SELECT 'lots_to_trim' AS info, COUNT(*) AS total FROM tmp_z_trim_target;

-- ===========================================================
-- TAHAP 2: Eksekusi repair
-- ===========================================================

START TRANSACTION;

-- ---- A. FULL_CLOSE: tutup semua lot OPEN di identity dgn stok=0 ----
UPDATE inv_material_fifo_lot l
JOIN tmp_z_lots_to_close tc ON tc.id = l.id
SET
  l.qty_out     = l.qty_in,
  l.qty_balance = 0,
  l.status      = 'CLOSED',
  l.updated_at  = NOW();

SELECT 'A_FULL_CLOSE_updated' AS step, ROW_COUNT() AS rows_affected;

-- ---- B. TRIM: kurangi lot terbaru sebesar kelebihan ----
-- LEAST() guard: tidak bisa minus (jika lot tidak cukup, sisakan 0)
UPDATE inv_material_fifo_lot l
JOIN tmp_z_trim_target tt ON tt.lot_id = l.id
SET
  l.qty_out     = ROUND(l.qty_out + LEAST(tt.excess, l.qty_balance), 4),
  l.qty_balance = ROUND(GREATEST(0, l.qty_balance - tt.excess), 4),
  l.status      = CASE
                    WHEN ROUND(l.qty_balance - tt.excess, 4) <= 0
                    THEN 'CLOSED'
                    ELSE l.status
                  END,
  l.updated_at  = NOW();

SELECT 'B_TRIM_updated' AS step, ROW_COUNT() AS rows_affected;

COMMIT;

-- ===========================================================
-- TAHAP 3: Verifikasi post-repair
-- ===========================================================

DROP TEMPORARY TABLE IF EXISTS tmp_z_post;
CREATE TEMPORARY TABLE tmp_z_post AS
SELECT
  l.division_id,
  l.destination_type,
  l.item_id,
  COALESCE(l.material_id, mi.material_id)             AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
  ROUND(SUM(l.qty_balance), 4)                        AS post_lot_balance
FROM inv_material_fifo_lot l
LEFT JOIN mst_item mi ON mi.id = l.item_id
WHERE l.location_scope = 'DIVISION'
  AND COALESCE(l.material_id, mi.material_id, 0) > 0
GROUP BY
  l.division_id, l.destination_type, l.item_id,
  COALESCE(l.material_id, mi.material_id),
  l.buy_uom_id, l.content_uom_id, l.profile_key;

-- ---- Verifikasi 3-A: Summary ----
SELECT
  rp.action                                             AS original_action,
  CASE
    WHEN ABS(COALESCE(pl.post_lot_balance, 0) - rp.closing_content) < 0.0001
    THEN 'NOW_OK'
    ELSE 'STILL_MISMATCH'
  END                                                   AS post_status,
  COUNT(*)                                              AS groups,
  ROUND(SUM(rp.excess), 4)                             AS total_excess_before,
  ROUND(SUM(COALESCE(pl.post_lot_balance, 0) - rp.closing_content), 4) AS remaining_excess
FROM tmp_z_plan rp
LEFT JOIN tmp_z_post pl
  ON pl.division_id      = rp.division_id
 AND pl.destination_type <=> rp.destination_type
 AND pl.item_id         <=> rp.item_id
 AND pl.material_id     <=> rp.material_id
 AND pl.buy_uom_id      <=> rp.buy_uom_id
 AND pl.content_uom_id  <=> rp.content_uom_id
 AND pl.profile_key     <=> rp.profile_key
GROUP BY rp.action, post_status
ORDER BY FIELD(rp.action,'FULL_CLOSE','TRIM'), post_status;

-- ---- Verifikasi 3-B: Yang masih mismatch (lot tidak cukup untuk diserap) ----
SELECT
  rp.action                                             AS original_action,
  d.code                                                AS division_code,
  rp.destination_type,
  i.item_code, i.item_name,
  mat.material_code, mat.material_name,
  bu.code AS buy_uom, cu.code AS content_uom,
  rp.closing_content,
  rp.total_lot_balance                                  AS pre_lot_balance,
  COALESCE(pl.post_lot_balance, 0)                     AS post_lot_balance,
  ROUND(COALESCE(pl.post_lot_balance, 0) - rp.closing_content, 4) AS remaining_excess
FROM tmp_z_plan rp
LEFT JOIN tmp_z_post pl
  ON pl.division_id      = rp.division_id
 AND pl.destination_type <=> rp.destination_type
 AND pl.item_id         <=> rp.item_id
 AND pl.material_id     <=> rp.material_id
 AND pl.buy_uom_id      <=> rp.buy_uom_id
 AND pl.content_uom_id  <=> rp.content_uom_id
 AND pl.profile_key     <=> rp.profile_key
LEFT JOIN mst_operational_division d  ON d.id  = rp.division_id
LEFT JOIN mst_item                 i  ON i.id   = rp.item_id
LEFT JOIN mst_material           mat  ON mat.id = rp.material_id
LEFT JOIN mst_uom                 bu  ON bu.id  = rp.buy_uom_id
LEFT JOIN mst_uom                 cu  ON cu.id  = rp.content_uom_id
WHERE ABS(COALESCE(pl.post_lot_balance, 0) - rp.closing_content) >= 0.0001
ORDER BY remaining_excess DESC, rp.material_id;
