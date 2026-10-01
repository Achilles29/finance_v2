START TRANSACTION;

-- Repair stok aktif yang masih memakai mst_item nonaktif.
--
-- Keputusan:
-- 1) AIR MINERAL GALON: migrate item_id 189 -> 220.
-- 2) AIR MINERAL BOTOL: migrate item_id 1 -> 2.
-- 3) AYAM PAHA PASAR: jangan migrate, saldo aktif dan lot aktif dijadikan 0.
-- 4) GULA KITCHEN: jangan migrate; saat script dibuat tidak ada saldo aktif item_id 177.
--
-- Catatan:
-- - PO/SR/receipt historis tidak diubah agar jejak nota tetap asli.
-- - Guard kode sudah mencegah katalog/item nonaktif muncul untuk PO/SR baru.

SET @month_key := '2026-07-01';
SET @date_from := '2026-07-01';
SET @date_to := '2026-07-31';

SET @galon_old_item := 189;
SET @galon_new_item := 220;
SET @botol_old_item := 1;
SET @botol_new_item := 2;
SET @ayam_paha_item := 14;
SET @gula_kitchen_item := 177;

-- Backup baris terdampak.
CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_catalog AS
SELECT *
FROM mst_purchase_catalog
WHERE item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_div_monthly AS
SELECT *
FROM inv_division_monthly_stock
WHERE month_key = @month_key
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_wh_monthly AS
SELECT *
FROM inv_warehouse_monthly_stock
WHERE month_key = @month_key
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_lot AS
SELECT *
FROM inv_material_fifo_lot
WHERE item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item)
  AND (
    (status = 'OPEN' AND qty_balance > 0.0001)
    OR receipt_date BETWEEN @date_from AND @date_to
  );

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_div_opening AS
SELECT *
FROM inv_division_stock_opening_snapshot
WHERE snapshot_month = @month_key
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_wh_opening AS
SELECT *
FROM inv_warehouse_stock_opening_snapshot
WHERE snapshot_month = @month_key
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_movement AS
SELECT *
FROM inv_stock_movement_log
WHERE movement_date BETWEEN @date_from AND @date_to
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_adj_line AS
SELECT *
FROM inv_stock_adjustment_line
WHERE item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item)
  AND created_at BETWEEN CONCAT(@date_from, ' 00:00:00') AND CONCAT(@date_to, ' 23:59:59');

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_div_opname AS
SELECT *
FROM inv_division_monthly_opname
WHERE month_key = @month_key
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

CREATE TABLE IF NOT EXISTS zz_bak_20260713_inactive_item_wh_opname AS
SELECT *
FROM inv_warehouse_monthly_opname
WHERE month_key = @month_key
  AND item_id IN (@galon_old_item, @galon_new_item, @botol_old_item, @botol_new_item, @ayam_paha_item, @gula_kitchen_item);

-- A. Migrate AIR MINERAL GALON 189 -> 220 untuk stok aktif dan katalog aktif.
UPDATE mst_purchase_catalog
SET item_id = @galon_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 189 -> 220'), 255),
    updated_at = NOW()
WHERE item_id = @galon_old_item;

UPDATE inv_division_monthly_stock
SET item_id = @galon_new_item,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 189 -> 220'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @galon_old_item;

UPDATE inv_warehouse_monthly_stock
SET item_id = @galon_new_item,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 189 -> 220'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @galon_old_item;

UPDATE inv_material_fifo_lot
SET item_id = @galon_new_item,
    updated_at = NOW()
WHERE item_id = @galon_old_item
  AND (
    (status = 'OPEN' AND qty_balance > 0.0001)
    OR receipt_date BETWEEN @date_from AND @date_to
  );

UPDATE inv_division_stock_opening_snapshot
SET item_id = @galon_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 189 -> 220'), 255),
    updated_at = NOW()
WHERE snapshot_month = @month_key
  AND item_id = @galon_old_item;

UPDATE inv_warehouse_stock_opening_snapshot
SET item_id = @galon_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 189 -> 220'), 255),
    updated_at = NOW()
WHERE snapshot_month = @month_key
  AND item_id = @galon_old_item;

UPDATE inv_stock_movement_log
SET item_id = @galon_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 189 -> 220'), 255)
WHERE movement_date BETWEEN @date_from AND @date_to
  AND item_id = @galon_old_item;

UPDATE inv_stock_adjustment_line
SET item_id = @galon_new_item,
    updated_at = NOW()
WHERE item_id = @galon_old_item
  AND created_at BETWEEN CONCAT(@date_from, ' 00:00:00') AND CONCAT(@date_to, ' 23:59:59');

UPDATE inv_division_monthly_opname
SET item_id = @galon_new_item,
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @galon_old_item;

UPDATE inv_warehouse_monthly_opname
SET item_id = @galon_new_item,
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @galon_old_item;

-- B. Migrate AIR MINERAL BOTOL 1 -> 2 untuk stok aktif dan katalog aktif.
UPDATE mst_purchase_catalog
SET item_id = @botol_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 1 -> 2'), 255),
    updated_at = NOW()
WHERE item_id = @botol_old_item;

UPDATE inv_division_monthly_stock
SET item_id = @botol_new_item,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 1 -> 2'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @botol_old_item;

UPDATE inv_warehouse_monthly_stock
SET item_id = @botol_new_item,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 1 -> 2'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @botol_old_item;

UPDATE inv_material_fifo_lot
SET item_id = @botol_new_item,
    updated_at = NOW()
WHERE item_id = @botol_old_item
  AND (
    (status = 'OPEN' AND qty_balance > 0.0001)
    OR receipt_date BETWEEN @date_from AND @date_to
  );

UPDATE inv_division_stock_opening_snapshot
SET item_id = @botol_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 1 -> 2'), 255),
    updated_at = NOW()
WHERE snapshot_month = @month_key
  AND item_id = @botol_old_item;

UPDATE inv_warehouse_stock_opening_snapshot
SET item_id = @botol_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 1 -> 2'), 255),
    updated_at = NOW()
WHERE snapshot_month = @month_key
  AND item_id = @botol_old_item;

UPDATE inv_stock_movement_log
SET item_id = @botol_new_item,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: migrate inactive item 1 -> 2'), 255)
WHERE movement_date BETWEEN @date_from AND @date_to
  AND item_id = @botol_old_item;

UPDATE inv_stock_adjustment_line
SET item_id = @botol_new_item,
    updated_at = NOW()
WHERE item_id = @botol_old_item
  AND created_at BETWEEN CONCAT(@date_from, ' 00:00:00') AND CONCAT(@date_to, ' 23:59:59');

UPDATE inv_division_monthly_opname
SET item_id = @botol_new_item,
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @botol_old_item;

UPDATE inv_warehouse_monthly_opname
SET item_id = @botol_new_item,
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @botol_old_item;

-- C. AYAM PAHA PASAR tidak dimigrate: nolkan stok aktif dan tutup lot aktif.
UPDATE inv_material_fifo_lot
SET qty_out = qty_in,
    qty_balance = 0.0000,
    status = 'CLOSED',
    updated_at = NOW()
WHERE item_id = @ayam_paha_item
  AND status = 'OPEN'
  AND qty_balance > 0.0001;

UPDATE inv_division_monthly_stock
SET opening_qty_buy = 0.0000,
    opening_qty_content = 0.0000,
    in_qty_buy = 0.0000,
    in_qty_content = 0.0000,
    out_qty_buy = 0.0000,
    out_qty_content = 0.0000,
    discarded_qty_buy = 0.0000,
    discarded_qty_content = 0.0000,
    spoil_qty_buy = 0.0000,
    spoil_qty_content = 0.0000,
    waste_qty_buy = 0.0000,
    waste_qty_content = 0.0000,
    process_loss_qty_buy = 0.0000,
    process_loss_qty_content = 0.0000,
    variance_qty_buy = 0.0000,
    variance_qty_content = 0.0000,
    adjustment_plus_qty_buy = 0.0000,
    adjustment_plus_qty_content = 0.0000,
    adjustment_minus_qty_buy = 0.0000,
    adjustment_minus_qty_content = 0.0000,
    closing_qty_buy = 0.0000,
    closing_qty_content = 0.0000,
    avg_cost_per_content = 0.000000,
    total_value = 0.00,
    waste_total_value = 0.00,
    spoilage_total_value = 0.00,
    process_loss_total_value = 0.00,
    variance_total_value = 0.00,
    adjustment_plus_total_value = 0.00,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: inactive item 14 saldo aktif dinolkan'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @ayam_paha_item;

UPDATE inv_division_stock_opening_snapshot
SET opening_qty_buy = 0.0000,
    opening_qty_content = 0.0000,
    opening_avg_cost_per_content = 0.000000,
    opening_total_value = 0.00,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-13: inactive item 14 opening dinolkan'), 255),
    updated_at = NOW()
WHERE snapshot_month = @month_key
  AND item_id = @ayam_paha_item;

UPDATE inv_division_monthly_opname
SET opening_qty_buy = 0.0000,
    opening_qty_content = 0.0000,
    in_qty_buy = 0.0000,
    in_qty_content = 0.0000,
    out_qty_buy = 0.0000,
    out_qty_content = 0.0000,
    discarded_qty_buy = 0.0000,
    discarded_qty_content = 0.0000,
    spoil_qty_buy = 0.0000,
    spoil_qty_content = 0.0000,
    waste_qty_buy = 0.0000,
    waste_qty_content = 0.0000,
    process_loss_qty_buy = 0.0000,
    process_loss_qty_content = 0.0000,
    variance_qty_buy = 0.0000,
    variance_qty_content = 0.0000,
    adjustment_plus_qty_buy = 0.0000,
    adjustment_plus_qty_content = 0.0000,
    adjustment_qty_buy = 0.0000,
    adjustment_qty_content = 0.0000,
    closing_qty_buy = 0.0000,
    closing_qty_content = 0.0000,
    avg_cost_per_content = 0.000000,
    total_value = 0.00,
    waste_total_value = 0.00,
    spoilage_total_value = 0.00,
    process_loss_total_value = 0.00,
    variance_total_value = 0.00,
    adjustment_plus_total_value = 0.00,
    updated_at = NOW()
WHERE month_key = @month_key
  AND item_id = @ayam_paha_item;

COMMIT;
