SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-24a_repair_failed_pos_void_material_lot_pairs.sql
-- Tujuan :
-- 1) Mendeteksi pengembalian stok POS lama yang sudah menambah stok
--    divisi, tetapi belum mengembalikan lot FIFO pasangannya.
-- 2) Menambahkan lot pasangan saja. Script ini TIDAK menambah stok
--    bulanan, TIDAK menulis movement baru, dan TIDAK mengubah kas,
--    penjualan, HPP order, PO, SR, atau refund.
-- 3) Hanya memproses kandidat aman pada bulan aktif: commit VOID/FAILED
--    tanpa referensi movement pada snapshot dan movement fallback lama.
--
-- Cara pakai:
-- - Jalankan terlebih dahulu apa adanya (@apply = 0) untuk melihat preview.
-- - Bila semua kandidat memang jejak gagal Void POS yang diharapkan,
--   ubah @apply menjadi 1 lalu jalankan ulang file ini.
-- - Script idempotent: lot repair yang sama tidak dibuat dua kali.
-- ============================================================

SET @as_of_date := CURDATE();
SET @target_month := DATE_FORMAT(@as_of_date, '%Y-%m-01');
SET @apply := 0;

DROP TEMPORARY TABLE IF EXISTS tmp_pos_void_unpaired_reversal;
CREATE TEMPORARY TABLE tmp_pos_void_unpaired_reversal AS
SELECT
  MIN(m.id) AS source_movement_id,
  MIN(m.movement_date) AS movement_date,
  m.division_id,
  m.destination_type,
  m.item_id,
  m.material_id,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_key,
  MAX(m.profile_expired_date) AS profile_expired_date,
  ROUND(SUM(m.qty_content_delta), 4) AS reversal_qty,
  ROUND(MIN(COALESCE(m.unit_cost, 0)), 6) AS min_unit_cost,
  ROUND(MAX(COALESCE(m.unit_cost, 0)), 6) AS max_unit_cost,
  GROUP_CONCAT(DISTINCT c.commit_no ORDER BY c.id SEPARATOR ', ') AS source_commit_nos
FROM inv_stock_movement_log m
INNER JOIN pos_stock_commit c
  ON c.id = m.ref_id
WHERE m.ref_table = 'pos_stock_commit_reversal'
  AND m.movement_scope = 'DIVISION'
  AND m.movement_type = 'VOID_REVERSE'
  AND m.movement_date >= @target_month
  AND m.movement_date <= @as_of_date
  AND COALESCE(m.qty_content_delta, 0) > 0.0001
  AND COALESCE(m.material_id, 0) > 0
  AND COALESCE(c.commit_status, '') IN ('VOID', 'FAILED')
  -- Jejak ini adalah fallback lama yang dulu menambah aggregate stock
  -- tanpa lot. Reversal FIFO yang normal tidak masuk kandidat.
  AND COALESCE(m.notes, '') LIKE 'POS return to stock aggregate reversal.%'
  AND NOT EXISTS (
    SELECT 1
    FROM pos_stock_commit_line cl
    WHERE cl.commit_id = c.id
      AND COALESCE(cl.movement_ref_id, 0) > 0
  )
GROUP BY
  m.division_id,
  m.destination_type,
  m.item_id,
  m.material_id,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_pos_void_lot_repair_candidates;
CREATE TEMPORARY TABLE tmp_pos_void_lot_repair_candidates AS
SELECT
  r.*,
  ROUND(COALESCE(s.stock_qty, 0), 4) AS stock_qty_before,
  ROUND(COALESCE(l.lot_qty, 0), 4) AS lot_qty_before,
  ROUND(COALESCE(s.stock_value, 0), 2) AS stock_value_before,
  ROUND(COALESCE(l.lot_value, 0), 2) AS lot_value_before,
  ROUND(COALESCE(s.stock_qty, 0) - COALESCE(l.lot_qty, 0), 4) AS qty_gap_before,
  ROUND(COALESCE(s.stock_value, 0) - COALESCE(l.lot_value, 0), 2) AS value_gap_before,
  ROUND(
    LEAST(
      GREATEST(COALESCE(s.stock_qty, 0) - COALESCE(l.lot_qty, 0), 0),
      COALESCE(r.reversal_qty, 0)
    ),
    4
  ) AS qty_to_restore,
  CASE
    WHEN ABS(COALESCE(r.max_unit_cost, 0) - COALESCE(r.min_unit_cost, 0)) <= 0.000001 THEN 1
    ELSE 0
  END AS has_consistent_cost
FROM tmp_pos_void_unpaired_reversal r
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key,
    SUM(closing_qty_content) AS stock_qty,
    SUM(COALESCE(total_value, ROUND(closing_qty_content * COALESCE(avg_cost_per_content, 0), 2))) AS stock_value
  FROM inv_division_monthly_stock
  WHERE month_key = @target_month
    AND material_id IS NOT NULL
  GROUP BY
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key
) s
  ON s.division_id = r.division_id
 AND s.destination_type <=> r.destination_type
 AND s.item_id <=> r.item_id
 AND s.material_id <=> r.material_id
 AND s.buy_uom_id <=> r.buy_uom_id
 AND s.content_uom_id <=> r.content_uom_id
 AND s.profile_key <=> r.profile_key
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key,
    SUM(qty_balance) AS lot_qty,
    SUM(qty_balance * COALESCE(unit_cost, 0)) AS lot_value
  FROM inv_material_fifo_lot
  WHERE location_scope = 'DIVISION'
    AND status = 'OPEN'
    AND qty_balance > 0.0001
    AND receipt_date <= @as_of_date
  GROUP BY
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key
) l
  ON l.division_id = r.division_id
 AND l.destination_type <=> r.destination_type
 AND l.item_id <=> r.item_id
 AND l.material_id <=> r.material_id
 AND l.buy_uom_id <=> r.buy_uom_id
 AND l.content_uom_id <=> r.content_uom_id
 AND l.profile_key <=> r.profile_key
WHERE COALESCE(s.stock_qty, 0) - COALESCE(l.lot_qty, 0) > 0.0001;

-- Preview sebelum melakukan perubahan.
SELECT
  source_commit_nos,
  source_movement_id,
  movement_date,
  division_id,
  destination_type,
  item_id,
  material_id,
  content_uom_id,
  profile_key,
  reversal_qty,
  stock_qty_before,
  lot_qty_before,
  qty_gap_before,
  min_unit_cost,
  max_unit_cost,
  has_consistent_cost,
  qty_to_restore,
  value_gap_before,
  ROUND(
    (stock_value_before - (qty_to_restore * max_unit_cost)) - lot_value_before,
    2
  ) AS estimated_value_gap_before_failed_void,
  CASE
    WHEN ABS((stock_value_before - (qty_to_restore * max_unit_cost)) - lot_value_before) <= 0.05
      THEN 'VOID_FAILED_ONLY'
    ELSE 'PREEXISTING_VALUE_GAP'
  END AS diagnosis
FROM tmp_pos_void_lot_repair_candidates
ORDER BY ABS(value_gap_before) DESC, ABS(qty_gap_before) DESC, material_id;

START TRANSACTION;

-- Menambah LOT saja untuk menutup gap yang terbukti berasal dari fallback
-- Void POS lama. Stok bulanan sudah terlanjur bertambah, sehingga sengaja
-- tidak ada INSERT movement maupun UPDATE stock pada bagian ini.
INSERT INTO inv_material_fifo_lot (
  lot_no,
  location_scope,
  receipt_date,
  expiry_date,
  division_id,
  destination_type,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  qty_in,
  qty_out,
  qty_balance,
  unit_cost,
  source_table,
  source_id,
  source_line_id,
  receipt_id,
  receipt_line_id,
  parent_lot_id,
  status,
  created_at,
  updated_at
)
SELECT
  LEFT(CONCAT('REPAIR-POSVOID-', DATE_FORMAT(movement_date, '%Y%m%d'), '-', source_movement_id), 80) AS lot_no,
  'DIVISION' AS location_scope,
  movement_date AS receipt_date,
  profile_expired_date AS expiry_date,
  division_id,
  destination_type,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  qty_to_restore AS qty_in,
  0.0000 AS qty_out,
  qty_to_restore AS qty_balance,
  max_unit_cost AS unit_cost,
  'inv_stock_void_lot_repair' AS source_table,
  source_movement_id AS source_id,
  NULL AS source_line_id,
  NULL AS receipt_id,
  NULL AS receipt_line_id,
  NULL AS parent_lot_id,
  'OPEN' AS status,
  NOW() AS created_at,
  NOW() AS updated_at
FROM tmp_pos_void_lot_repair_candidates c
WHERE @apply = 1
  AND c.qty_to_restore > 0.0001
  AND c.has_consistent_cost = 1
  AND NOT EXISTS (
    SELECT 1
    FROM inv_material_fifo_lot existing_repair
    WHERE existing_repair.source_table = 'inv_stock_void_lot_repair'
      AND existing_repair.source_id = c.source_movement_id
  );

COMMIT;

-- Verifikasi hasil. Bila nilai masih berbeda tetapi qty sudah sama,
-- itu adalah selisih biaya lama yang harus ditinjau lewat Koreksi Nilai
-- Stok, bukan diubah otomatis oleh script ini.
SELECT
  c.source_commit_nos,
  c.division_id,
  c.destination_type,
  c.material_id,
  c.profile_key,
  c.stock_qty_before,
  ROUND(COALESCE(SUM(l.qty_balance), 0), 4) AS lot_qty_after,
  ROUND(c.stock_qty_before - COALESCE(SUM(l.qty_balance), 0), 4) AS qty_gap_after,
  c.stock_value_before,
  ROUND(COALESCE(SUM(l.qty_balance * COALESCE(l.unit_cost, 0)), 0), 2) AS lot_value_after,
  ROUND(c.stock_value_before - COALESCE(SUM(l.qty_balance * COALESCE(l.unit_cost, 0)), 0), 2) AS value_gap_after
FROM tmp_pos_void_lot_repair_candidates c
LEFT JOIN inv_material_fifo_lot l
  ON l.location_scope = 'DIVISION'
 AND l.status = 'OPEN'
 AND l.division_id = c.division_id
 AND l.destination_type <=> c.destination_type
 AND l.item_id <=> c.item_id
 AND l.material_id <=> c.material_id
 AND l.buy_uom_id <=> c.buy_uom_id
 AND l.content_uom_id <=> c.content_uom_id
 AND l.profile_key <=> c.profile_key
 AND l.receipt_date <= @as_of_date
GROUP BY
  c.source_commit_nos,
  c.division_id,
  c.destination_type,
  c.material_id,
  c.profile_key,
  c.stock_qty_before,
  c.stock_value_before
ORDER BY
  ABS(c.stock_value_before - COALESCE(SUM(l.qty_balance * COALESCE(l.unit_cost, 0)), 0)) DESC,
  ABS(c.stock_qty_before - COALESCE(SUM(l.qty_balance), 0)) DESC,
  c.material_id;
