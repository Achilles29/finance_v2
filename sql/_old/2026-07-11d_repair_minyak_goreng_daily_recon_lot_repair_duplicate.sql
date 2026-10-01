START TRANSACTION;

-- Repair MINYAK GORENG KITCHEN Juli setelah daily-recon membuat kondisi tidak konsisten:
-- 1) ada profile legacy buy UOM PACK yang tidak sesuai master aktif (seharusnya PCS -> ML);
-- 2) ada lot source_table=lot_repair yang menggandakan lot opening/SR;
-- 3) monthly stock harus kembali mengikuti LOT FIFO bulan berjalan yang nyata.
--
-- Target:
-- - legacy PACK tidak tampil sebagai stok aktif;
-- - lot_repair duplikat terbuka dihapus;
-- - inv_division_monthly_stock Juli/KITCHEN/MINYAK GORENG disinkron ulang dari lot OPEN non-lot_repair.

SET @month_key := '2026-07-01';
SET @date_from := '2026-07-01';
SET @date_to := '2026-07-31';
SET @division_id := 3;
SET @destination_type := 'KITCHEN';
SET @item_id := 198;
SET @material_id := 143;
SET @content_uom_id := 9; -- ML
SET @valid_buy_uom_id := 1; -- PCS

CREATE TABLE IF NOT EXISTS zz_backup_inv_stock_adjustment_20260711d_minyak_recon AS
SELECT h.*
FROM inv_stock_adjustment h
JOIN inv_stock_adjustment_line l ON l.adjustment_id = h.id
WHERE h.adjustment_date BETWEEN @date_from AND @date_to
  AND h.stock_scope = 'DIVISION'
  AND h.division_id = @division_id
  AND h.destination_type = @destination_type
  AND l.item_id = @item_id
  AND l.material_id = @material_id;

CREATE TABLE IF NOT EXISTS zz_backup_inv_stock_adjustment_line_20260711d_minyak_recon AS
SELECT l.*
FROM inv_stock_adjustment_line l
JOIN inv_stock_adjustment h ON h.id = l.adjustment_id
WHERE h.adjustment_date BETWEEN @date_from AND @date_to
  AND h.stock_scope = 'DIVISION'
  AND h.division_id = @division_id
  AND h.destination_type = @destination_type
  AND l.item_id = @item_id
  AND l.material_id = @material_id;

CREATE TABLE IF NOT EXISTS zz_backup_inv_material_fifo_lot_20260711d_minyak_recon AS
SELECT *
FROM inv_material_fifo_lot
WHERE location_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND (receipt_date BETWEEN @date_from AND @date_to OR buy_uom_id <> @valid_buy_uom_id OR source_table = 'lot_repair');

CREATE TABLE IF NOT EXISTS zz_backup_inv_material_fifo_issue_log_20260711d_minyak_recon AS
SELECT ilog.*
FROM inv_material_fifo_issue_log ilog
WHERE ilog.source_table = 'inv_stock_adjustment'
  AND ilog.source_id IN (
    SELECT DISTINCT h.id
    FROM inv_stock_adjustment h
    JOIN inv_stock_adjustment_line l ON l.adjustment_id = h.id
    WHERE h.adjustment_date BETWEEN @date_from AND @date_to
      AND h.stock_scope = 'DIVISION'
      AND h.division_id = @division_id
      AND h.destination_type = @destination_type
      AND l.item_id = @item_id
      AND l.material_id = @material_id
  );

CREATE TABLE IF NOT EXISTS zz_backup_inv_material_fifo_issue_line_20260711d_minyak_recon AS
SELECT il.*
FROM inv_material_fifo_issue_line il
JOIN zz_backup_inv_material_fifo_issue_log_20260711d_minyak_recon ilog ON ilog.id = il.issue_id;

CREATE TABLE IF NOT EXISTS zz_backup_inv_stock_movement_log_20260711d_minyak_recon AS
SELECT *
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND movement_date BETWEEN @date_from AND @date_to;

CREATE TABLE IF NOT EXISTS zz_backup_inv_division_monthly_stock_20260711d_minyak_recon AS
SELECT *
FROM inv_division_monthly_stock
WHERE month_key = @month_key
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id;

CREATE TABLE IF NOT EXISTS zz_backup_inv_division_stock_opname_20260711d_minyak_recon AS
SELECT *
FROM inv_division_stock_opname
WHERE opname_date BETWEEN @date_from AND @date_to
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id;

DROP TEMPORARY TABLE IF EXISTS tmp_minyak_legacy_pack_adjustment;
CREATE TEMPORARY TABLE tmp_minyak_legacy_pack_adjustment AS
SELECT DISTINCT h.id
FROM inv_stock_adjustment h
JOIN inv_stock_adjustment_line l ON l.adjustment_id = h.id
WHERE h.adjustment_date BETWEEN @date_from AND @date_to
  AND h.stock_scope = 'DIVISION'
  AND h.division_id = @division_id
  AND h.destination_type = @destination_type
  AND h.status = 'POSTED'
  AND l.item_id = @item_id
  AND l.material_id = @material_id
  AND (COALESCE(l.buy_uom_id, 0) <> @valid_buy_uom_id OR UPPER(COALESCE(l.profile_buy_uom_code, '')) <> 'PCS');

DROP TEMPORARY TABLE IF EXISTS tmp_minyak_legacy_pack_issue;
CREATE TEMPORARY TABLE tmp_minyak_legacy_pack_issue AS
SELECT DISTINCT ilog.id
FROM inv_material_fifo_issue_log ilog
JOIN tmp_minyak_legacy_pack_adjustment bad ON bad.id = ilog.source_id
WHERE ilog.source_table = 'inv_stock_adjustment';

-- Legacy PACK memang harus menjadi 0, jadi issue FIFO legacy tidak di-rollback ke lot.
DELETE il
FROM inv_material_fifo_issue_line il
JOIN tmp_minyak_legacy_pack_issue bad ON bad.id = il.issue_id;

DELETE ilog
FROM inv_material_fifo_issue_log ilog
JOIN tmp_minyak_legacy_pack_issue bad ON bad.id = ilog.id;

DELETE mlog
FROM inv_stock_movement_log mlog
JOIN tmp_minyak_legacy_pack_adjustment bad ON bad.id = mlog.ref_id
WHERE mlog.ref_table = 'inv_stock_adjustment';

UPDATE inv_material_fifo_lot
SET qty_out = qty_in,
    qty_balance = 0.0000,
    status = 'CLOSED',
    updated_at = NOW()
WHERE location_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND (COALESCE(buy_uom_id, 0) <> @valid_buy_uom_id OR receipt_date < @date_from);

UPDATE inv_division_stock_opname o
JOIN tmp_minyak_legacy_pack_adjustment bad ON bad.id = o.adjustment_id
SET o.adjustment_id = NULL,
    o.notes = LEFT(CONCAT(COALESCE(o.notes, ''), ' | Repair 2026-07-11d: legacy PACK adjustment dibersihkan'), 255),
    o.updated_at = NOW();

DELETE l
FROM inv_stock_adjustment_line l
JOIN tmp_minyak_legacy_pack_adjustment bad ON bad.id = l.adjustment_id;

DELETE h
FROM inv_stock_adjustment h
JOIN tmp_minyak_legacy_pack_adjustment bad ON bad.id = h.id;

-- Hapus lot repair duplikat yang belum menjadi sumber issue FIFO.
DELETE l
FROM inv_material_fifo_lot l
WHERE l.location_scope = 'DIVISION'
  AND l.division_id = @division_id
  AND l.destination_type = @destination_type
  AND l.item_id = @item_id
  AND l.material_id = @material_id
  AND l.receipt_date BETWEEN @date_from AND @date_to
  AND l.source_table = 'lot_repair'
  AND l.qty_balance > 0
  AND NOT EXISTS (
    SELECT 1
    FROM inv_material_fifo_issue_line il
    WHERE il.lot_id = l.id
  );

DROP TEMPORARY TABLE IF EXISTS tmp_minyak_lot_sum;
CREATE TEMPORARY TABLE tmp_minyak_lot_sum AS
SELECT
  profile_key,
  SUM(qty_balance) AS qty_balance,
  SUM(ROUND(qty_balance * unit_cost, 2)) AS total_value,
  CASE WHEN SUM(qty_balance) > 0
       THEN ROUND(SUM(ROUND(qty_balance * unit_cost, 2)) / SUM(qty_balance), 6)
       ELSE 0 END AS avg_cost
FROM inv_material_fifo_lot
WHERE location_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND content_uom_id = @content_uom_id
  AND buy_uom_id = @valid_buy_uom_id
  AND status = 'OPEN'
  AND qty_balance > 0
  AND receipt_date BETWEEN @date_from AND @date_to
  AND COALESCE(source_table, '') <> 'lot_repair'
GROUP BY profile_key;

UPDATE inv_division_monthly_stock s
LEFT JOIN tmp_minyak_lot_sum ls ON ls.profile_key = s.profile_key
SET s.buy_uom_id = @valid_buy_uom_id,
    s.profile_buy_uom_code = 'PCS',
    s.closing_qty_content = ROUND(COALESCE(ls.qty_balance, 0), 4),
    s.closing_qty_buy = ROUND(COALESCE(ls.qty_balance, 0) / GREATEST(COALESCE(NULLIF(s.profile_content_per_buy, 0), 1), 0.000001), 4),
    s.avg_cost_per_content = ROUND(COALESCE(ls.avg_cost, 0), 6),
    s.total_value = ROUND(COALESCE(ls.total_value, 0), 2),
    s.last_movement_date = @date_to,
    s.last_movement_at = CONCAT(@date_to, ' 23:59:59'),
    s.last_movement_table = 'manual_repair',
    s.last_movement_id = NULL,
    s.source_mode = 'REBUILD',
    s.notes = LEFT(CONCAT(COALESCE(s.notes, ''), ' | Repair 2026-07-11d: sync from real July open lots, remove duplicate lot_repair/legacy PACK'), 255),
    s.updated_at = NOW()
WHERE s.month_key = @month_key
  AND s.division_id = @division_id
  AND s.destination_type = @destination_type
  AND s.item_id = @item_id
  AND s.material_id = @material_id;

COMMIT;

