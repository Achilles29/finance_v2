SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08y_repair_division_lot_vs_monthly_content.sql
-- Tujuan :
--   Repair qty lot FIFO divisi agar qty_balance = stok isi
--   (closing_qty_content dari inv_division_monthly_stock)
--
-- Kasus yang ditangani otomatis:
--   A) SCALE_BUY      : lot tersimpan dalam buy qty → kalikan cpb
--   B) SEED_LOT       : tidak ada lot, stok ada → buat lot awal
--   C) CORRECTION_PLUS: lot kurang dari stok → tambah correction lot
--
-- Kasus yang DILAPORKAN SAJA (tidak diubah otomatis):
--   D) CORRECTION_MINUS: lot LEBIH dari stok → perlu review manual
--
-- Cara pakai:
--   1) Jalankan file ini; cek result set preview dulu (TAHAP 0)
--   2) Jika preview OK, konfirmasi commit sudah masuk (TAHAP 2)
--   3) Cek verifikasi post-repair (TAHAP 3)
--   4) Untuk CORRECTION_MINUS, tindak lanjut manual
-- ============================================================

-- ===========================================================
-- TAHAP 0: Build repair plan & preview (SEBELUM ubah data)
-- ===========================================================

DROP TEMPORARY TABLE IF EXISTS tmp_rm;
CREATE TEMPORARY TABLE tmp_rm AS
SELECT
  s.division_id,
  COALESCE(s.destination_type, 'OTHER')               AS destination_type,
  s.item_id,
  COALESCE(s.material_id, mi.material_id)             AS material_id,
  s.buy_uom_id,
  s.content_uom_id,
  COALESCE(s.profile_key, '')                         AS profile_key,
  ROUND(MAX(COALESCE(NULLIF(s.profile_content_per_buy, 0), 1)), 6) AS cpb,
  ROUND(SUM(COALESCE(s.closing_qty_buy,     0)), 4)   AS closing_buy,
  ROUND(SUM(COALESCE(s.closing_qty_content, 0)), 4)   AS closing_content
FROM inv_division_monthly_stock s
LEFT JOIN mst_item mi ON mi.id = s.item_id
WHERE COALESCE(s.material_id, mi.material_id, 0) > 0
GROUP BY
  s.division_id, COALESCE(s.destination_type,'OTHER'),
  s.item_id, COALESCE(s.material_id, mi.material_id),
  s.buy_uom_id, s.content_uom_id, COALESCE(s.profile_key,'');

ALTER TABLE tmp_rm
  ADD KEY idx_rm (division_id, destination_type, item_id, material_id,
                  buy_uom_id, content_uom_id, profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_rl;
CREATE TEMPORARY TABLE tmp_rl AS
SELECT
  l.division_id,
  COALESCE(l.destination_type, 'OTHER')               AS destination_type,
  l.item_id,
  COALESCE(l.material_id, mi.material_id)             AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '')                         AS profile_key,
  ROUND(SUM(l.qty_balance), 4)                        AS total_lot_balance,
  COUNT(*)                                            AS lot_rows
FROM inv_material_fifo_lot l
LEFT JOIN mst_item mi ON mi.id = l.item_id
WHERE l.location_scope = 'DIVISION'
  AND COALESCE(l.material_id, mi.material_id, 0) > 0
GROUP BY
  l.division_id, COALESCE(l.destination_type,'OTHER'),
  l.item_id, COALESCE(l.material_id, mi.material_id),
  l.buy_uom_id, l.content_uom_id, COALESCE(l.profile_key,'');

ALTER TABLE tmp_rl
  ADD KEY idx_rl (division_id, destination_type, item_id, material_id,
                  buy_uom_id, content_uom_id, profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_repair_plan;
CREATE TEMPORARY TABLE tmp_repair_plan AS
SELECT
  m.division_id,
  m.destination_type,
  m.item_id,
  m.material_id,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_key,
  m.cpb,
  m.closing_buy,
  m.closing_content,
  COALESCE(l.total_lot_balance, 0)   AS current_lot_balance,
  COALESCE(l.lot_rows, 0)            AS lot_rows,
  CASE
    -- Sudah match content: tidak perlu apa-apa
    WHEN ABS(COALESCE(l.total_lot_balance, 0) - m.closing_content) < 0.0001
      THEN 'OK'

    -- Lot tersimpan dalam buy qty (cocok ke closing_buy atau scale cocok ke content)
    WHEN m.cpb > 1
     AND (
       ABS(COALESCE(l.total_lot_balance, 0) - m.closing_buy) < 0.0001
       OR ABS((COALESCE(l.total_lot_balance, 0) * m.cpb) - m.closing_content) < 0.0001
     )
      THEN 'SCALE_BUY'

    -- Tidak ada lot sama sekali tapi stok ada
    WHEN COALESCE(l.lot_rows, 0) = 0 AND m.closing_content > 0
      THEN 'SEED_LOT'

    -- Lot kurang dari stok → perlu tambah
    WHEN (m.closing_content - COALESCE(l.total_lot_balance, 0)) > 0.0001
      THEN 'CORRECTION_PLUS'

    -- Lot lebih dari stok → perlu review manual
    WHEN (COALESCE(l.total_lot_balance, 0) - m.closing_content) > 0.0001
      THEN 'CORRECTION_MINUS'

    ELSE 'OK'
  END AS repair_action
FROM tmp_rm m
LEFT JOIN tmp_rl l
  ON l.division_id      = m.division_id
 AND l.destination_type = m.destination_type
 AND l.item_id         <=> m.item_id
 AND l.material_id     <=> m.material_id
 AND l.buy_uom_id      <=> m.buy_uom_id
 AND l.content_uom_id  <=> m.content_uom_id
 AND l.profile_key     <=> m.profile_key;

ALTER TABLE tmp_repair_plan
  ADD KEY idx_rp_action   (repair_action),
  ADD KEY idx_rp_identity (division_id, destination_type, item_id, material_id,
                           buy_uom_id, content_uom_id, profile_key(32));

-- ---- Preview 0-A: Ringkasan per action ----
SELECT
  repair_action,
  COUNT(*) AS identity_groups,
  SUM(CASE WHEN lot_rows > 0 THEN 1 ELSE 0 END)    AS groups_with_lots,
  ROUND(SUM(closing_content), 2)                    AS total_closing_content,
  ROUND(SUM(current_lot_balance), 2)                AS total_lot_balance
FROM tmp_repair_plan
GROUP BY repair_action
ORDER BY FIELD(repair_action,
  'OK','SCALE_BUY','SEED_LOT','CORRECTION_PLUS','CORRECTION_MINUS');

-- ---- Preview 0-B: Detail identity yang akan diubah ----
SELECT
  rp.repair_action,
  d.code                                                AS division_code,
  rp.destination_type,
  i.item_code,
  i.item_name,
  mat.material_code,
  mat.material_name,
  bu.code                                               AS buy_uom,
  cu.code                                               AS content_uom,
  rp.cpb,
  rp.closing_buy,
  rp.closing_content,
  rp.current_lot_balance,
  ROUND(rp.current_lot_balance * rp.cpb, 4)            AS lot_scaled_preview,
  ROUND(rp.closing_content - rp.current_lot_balance, 4) AS delta_content,
  rp.lot_rows
FROM tmp_repair_plan rp
LEFT JOIN mst_operational_division d  ON d.id  = rp.division_id
LEFT JOIN mst_item                 i  ON i.id   = rp.item_id
LEFT JOIN mst_material           mat  ON mat.id = rp.material_id
LEFT JOIN mst_uom                 bu  ON bu.id  = rp.buy_uom_id
LEFT JOIN mst_uom                 cu  ON cu.id  = rp.content_uom_id
WHERE rp.repair_action <> 'OK'
ORDER BY
  FIELD(rp.repair_action,
    'SCALE_BUY','SEED_LOT','CORRECTION_PLUS','CORRECTION_MINUS'),
  ABS(rp.closing_content - rp.current_lot_balance) DESC,
  rp.material_id, rp.division_id;

-- ===========================================================
-- TAHAP 1: Identifikasi lot yang perlu di-scale (SCALE_BUY)
-- ===========================================================

DROP TEMPORARY TABLE IF EXISTS tmp_lots_to_scale;
CREATE TEMPORARY TABLE tmp_lots_to_scale AS
SELECT
  l.id,
  l.qty_in       AS old_qty_in,
  l.qty_out      AS old_qty_out,
  l.qty_balance  AS old_qty_balance,
  l.unit_cost    AS old_unit_cost,
  ROUND(l.qty_in      * rp.cpb, 4)                   AS new_qty_in,
  ROUND(l.qty_out     * rp.cpb, 4)                   AS new_qty_out,
  ROUND(l.qty_balance * rp.cpb, 4)                   AS new_qty_balance,
  CASE WHEN rp.cpb > 0
       THEN ROUND(l.unit_cost / rp.cpb, 6)
       ELSE l.unit_cost END                           AS new_unit_cost,
  rp.cpb,
  rp.closing_content
FROM inv_material_fifo_lot l
LEFT JOIN mst_item mi ON mi.id = l.item_id
JOIN tmp_repair_plan rp
  ON rp.division_id      = l.division_id
 AND rp.destination_type = COALESCE(l.destination_type, 'OTHER')
 AND rp.item_id         <=> l.item_id
 AND rp.material_id     <=> COALESCE(l.material_id, mi.material_id)
 AND rp.buy_uom_id      <=> l.buy_uom_id
 AND rp.content_uom_id  <=> l.content_uom_id
 AND rp.profile_key     <=> COALESCE(l.profile_key, '')
WHERE l.location_scope = 'DIVISION'
  AND rp.repair_action  = 'SCALE_BUY'
  AND COALESCE(l.material_id, mi.material_id, 0) > 0;

ALTER TABLE tmp_lots_to_scale ADD PRIMARY KEY (id);

-- Cek: berapa lot yang akan di-scale
SELECT 'lots_to_scale' AS info, COUNT(*) AS total FROM tmp_lots_to_scale;

-- ===========================================================
-- TAHAP 2: Eksekusi repair (dalam satu transaksi)
-- Untuk rollback: jalankan ROLLBACK sebelum COMMIT
-- ===========================================================

START TRANSACTION;

-- ---- A. SCALE_BUY: update qty & unit_cost dari buy ke content unit ----
UPDATE inv_material_fifo_lot l
JOIN tmp_lots_to_scale ts ON ts.id = l.id
SET
  l.qty_in      = ts.new_qty_in,
  l.qty_out     = ts.new_qty_out,
  l.qty_balance = ts.new_qty_balance,
  l.unit_cost   = ts.new_unit_cost,
  l.updated_at  = NOW();

SELECT 'A_SCALE_BUY_updated' AS step, ROW_COUNT() AS rows_affected;

-- ---- B. SEED_LOT: buat lot awal dari closing_content ----
-- Catatan: destination_type 'OTHER' → NULL (placeholder COALESCE di audit)
INSERT IGNORE INTO inv_material_fifo_lot
  (lot_no, location_scope, receipt_date,
   division_id, destination_type, item_id, material_id,
   buy_uom_id, content_uom_id, profile_key,
   qty_in, qty_out, qty_balance, unit_cost,
   source_table, status, created_at)
SELECT
  'SEED-20260608'                               AS lot_no,
  'DIVISION'                                    AS location_scope,
  CURDATE()                                     AS receipt_date,
  rp.division_id,
  NULLIF(rp.destination_type, 'OTHER')          AS destination_type,
  rp.item_id,
  rp.material_id,
  rp.buy_uom_id,
  rp.content_uom_id,
  NULLIF(rp.profile_key, '')                    AS profile_key,
  rp.closing_content                            AS qty_in,
  0                                             AS qty_out,
  rp.closing_content                            AS qty_balance,
  0                                             AS unit_cost,
  'REPAIR'                                      AS source_table,
  'OPEN'                                        AS status,
  NOW()                                         AS created_at
FROM tmp_repair_plan rp
WHERE rp.repair_action = 'SEED_LOT'
  AND rp.closing_content > 0;

SELECT 'B_SEED_LOT_inserted' AS step, ROW_COUNT() AS rows_affected;

-- ---- C. CORRECTION_PLUS: tambah lot selisih (lot kurang dari stok) ----
INSERT IGNORE INTO inv_material_fifo_lot
  (lot_no, location_scope, receipt_date,
   division_id, destination_type, item_id, material_id,
   buy_uom_id, content_uom_id, profile_key,
   qty_in, qty_out, qty_balance, unit_cost,
   source_table, status, created_at)
SELECT
  'REPAIR-20260608'                             AS lot_no,
  'DIVISION'                                    AS location_scope,
  CURDATE()                                     AS receipt_date,
  rp.division_id,
  NULLIF(rp.destination_type, 'OTHER')          AS destination_type,
  rp.item_id,
  rp.material_id,
  rp.buy_uom_id,
  rp.content_uom_id,
  NULLIF(rp.profile_key, '')                    AS profile_key,
  ROUND(rp.closing_content - rp.current_lot_balance, 4) AS qty_in,
  0                                             AS qty_out,
  ROUND(rp.closing_content - rp.current_lot_balance, 4) AS qty_balance,
  0                                             AS unit_cost,
  'REPAIR'                                      AS source_table,
  'OPEN'                                        AS status,
  NOW()                                         AS created_at
FROM tmp_repair_plan rp
WHERE rp.repair_action = 'CORRECTION_PLUS';

SELECT 'C_CORRECTION_PLUS_inserted' AS step, ROW_COUNT() AS rows_affected;

-- ---- D. CORRECTION_MINUS: hanya dicatat, TIDAK diubah otomatis ----
-- (lihat hasil TAHAP 3 bagian CORRECTION_MINUS)

COMMIT;

-- ===========================================================
-- TAHAP 3: Verifikasi post-repair
-- ===========================================================

DROP TEMPORARY TABLE IF EXISTS tmp_post_lot;
CREATE TEMPORARY TABLE tmp_post_lot AS
SELECT
  l.division_id,
  COALESCE(l.destination_type, 'OTHER')               AS destination_type,
  l.item_id,
  COALESCE(l.material_id, mi.material_id)             AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '')                         AS profile_key,
  ROUND(SUM(l.qty_balance), 4)                        AS post_lot_balance
FROM inv_material_fifo_lot l
LEFT JOIN mst_item mi ON mi.id = l.item_id
WHERE l.location_scope = 'DIVISION'
  AND COALESCE(l.material_id, mi.material_id, 0) > 0
GROUP BY
  l.division_id, COALESCE(l.destination_type,'OTHER'),
  l.item_id, COALESCE(l.material_id, mi.material_id),
  l.buy_uom_id, l.content_uom_id, COALESCE(l.profile_key,'');

-- ---- Verifikasi 3-A: Summary status setelah repair ----
SELECT
  rp.repair_action   AS original_action,
  CASE
    WHEN ABS(COALESCE(pl.post_lot_balance, 0) - rp.closing_content) < 0.0001
      THEN 'NOW_OK'
    ELSE 'STILL_MISMATCH'
  END                AS post_status,
  COUNT(*)           AS groups,
  ROUND(SUM(rp.closing_content), 2) AS total_closing_content,
  ROUND(SUM(COALESCE(pl.post_lot_balance, 0)), 2) AS total_post_lot
FROM tmp_repair_plan rp
LEFT JOIN tmp_post_lot pl
  ON pl.division_id      = rp.division_id
 AND pl.destination_type = rp.destination_type
 AND pl.item_id         <=> rp.item_id
 AND pl.material_id     <=> rp.material_id
 AND pl.buy_uom_id      <=> rp.buy_uom_id
 AND pl.content_uom_id  <=> rp.content_uom_id
 AND pl.profile_key     <=> rp.profile_key
WHERE rp.repair_action <> 'OK'
GROUP BY rp.repair_action, post_status
ORDER BY FIELD(rp.repair_action,
  'SCALE_BUY','SEED_LOT','CORRECTION_PLUS','CORRECTION_MINUS'),
  post_status;

-- ---- Verifikasi 3-B: Yang MASIH mismatch → perlu review manual ----
SELECT
  rp.repair_action                                      AS original_action,
  d.code                                                AS division_code,
  rp.destination_type,
  i.item_code,
  i.item_name,
  mat.material_code,
  mat.material_name,
  bu.code                                               AS buy_uom,
  cu.code                                               AS content_uom,
  rp.cpb,
  rp.closing_content,
  rp.current_lot_balance                                AS pre_lot_balance,
  COALESCE(pl.post_lot_balance, 0)                     AS post_lot_balance,
  ROUND(rp.closing_content - COALESCE(pl.post_lot_balance, 0), 4) AS remaining_delta
FROM tmp_repair_plan rp
LEFT JOIN tmp_post_lot pl
  ON pl.division_id      = rp.division_id
 AND pl.destination_type = rp.destination_type
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
WHERE rp.repair_action <> 'OK'
  AND ABS(COALESCE(pl.post_lot_balance, rp.current_lot_balance) - rp.closing_content) >= 0.0001
ORDER BY ABS(rp.closing_content - COALESCE(pl.post_lot_balance, 0)) DESC,
         rp.material_id, rp.division_id;

-- ---- Verifikasi 3-C: CORRECTION_MINUS — lot masih lebih dari stok ----
-- Ini butuh review manual sebelum dikurangi
SELECT
  'PERLU_REVIEW_MANUAL'                                 AS status,
  d.code                                                AS division_code,
  rp.destination_type,
  i.item_code,
  i.item_name,
  mat.material_code,
  mat.material_name,
  bu.code                                               AS buy_uom,
  cu.code                                               AS content_uom,
  rp.closing_content                                    AS stok_isi_monthly,
  rp.current_lot_balance                                AS lot_balance_sekarang,
  ROUND(rp.current_lot_balance - rp.closing_content, 4) AS kelebihan_lot,
  rp.lot_rows                                           AS jumlah_lot
FROM tmp_repair_plan rp
LEFT JOIN mst_operational_division d  ON d.id  = rp.division_id
LEFT JOIN mst_item                 i  ON i.id   = rp.item_id
LEFT JOIN mst_material           mat  ON mat.id = rp.material_id
LEFT JOIN mst_uom                 bu  ON bu.id  = rp.buy_uom_id
LEFT JOIN mst_uom                 cu  ON cu.id  = rp.content_uom_id
WHERE rp.repair_action = 'CORRECTION_MINUS'
ORDER BY kelebihan_lot DESC, rp.material_id, rp.division_id;
