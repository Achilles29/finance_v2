SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-17a_enable_roastery_stock_flow.sql
-- Tujuan :
-- 1) Membuka jalur destination/location ROASTERY untuk purchase,
--    store request, stok divisi, FIFO lot, movement, adjustment,
--    component stock, component batch, sampai POS costing/availability.
-- 2) Menambahkan purchase type "Belanja Stok Roastery".
-- 3) Menambahkan divisi produk ROASTERY agar produk sales dapat
--    diarahkan default ke divisi operasional ROASTERY.
--
-- Catatan:
-- - ROASTERY = reguler roastery.
-- - ROASTERY_EVENT = event roastery, disiapkan agar pola BAR_EVENT /
--   KITCHEN_EVENT tetap konsisten jika roastery ikut event.
-- - Jalankan setelah mst_operational_division punya code ROASTERY.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A0. Normalisasi nilai legacy kosong sebelum enum diperluas
-- ------------------------------------------------------------
-- Beberapa data lama menyimpan enum sebagai '' (empty enum value).
-- Jika langsung MODIFY ENUM, MySQL strict akan memunculkan:
-- 1265 - Data truncated for column ... at row 1.
-- Nilai kosong ini bukan destinasi valid, jadi dinormalisasi ke OTHER.
UPDATE mst_purchase_type
SET default_destination = NULL
WHERE COALESCE(TRIM(CAST(default_destination AS CHAR)), '') = '';

UPDATE pur_purchase_order
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE pur_purchase_receipt
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE pur_store_request
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE pur_division_request
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_division_monthly_stock
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_division_stock_opening_snapshot
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_division_monthly_opening
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_division_monthly_opname
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_division_stock_opname
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_material_fifo_lot
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_material_fifo_issue_log
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_stock_movement_log
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_stock_adjustment
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

UPDATE inv_stock_opening_snapshot
SET destination_type = 'OTHER'
WHERE COALESCE(TRIM(CAST(destination_type AS CHAR)), '') = '';

-- Untuk component, fallback aman existing adalah KITCHEN agar data lama
-- tetap masuk lokasi produksi valid.
UPDATE inv_component_adjustment
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

UPDATE inv_component_batch
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

UPDATE inv_component_monthly_opening
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

UPDATE inv_component_monthly_opname
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

UPDATE inv_component_monthly_stock
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

UPDATE inv_component_movement_log
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

UPDATE inv_component_opening
SET location_type = 'KITCHEN'
WHERE COALESCE(TRIM(CAST(location_type AS CHAR)), '') = '';

-- ------------------------------------------------------------
-- A. Buka enum destination bahan baku / purchase
-- ------------------------------------------------------------
ALTER TABLE mst_purchase_type
  MODIFY default_destination ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NULL DEFAULT NULL;

ALTER TABLE pur_purchase_order
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE pur_purchase_receipt
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE pur_store_request
  MODIFY destination_type ENUM(
    'BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE pur_division_request
  MODIFY destination_type ENUM(
    'BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_division_monthly_stock
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_division_stock_opening_snapshot
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_division_monthly_opening
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_division_monthly_opname
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_division_stock_opname
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_material_fifo_lot
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_material_fifo_issue_log
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_stock_movement_log
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_stock_adjustment
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

ALTER TABLE inv_stock_opening_snapshot
  MODIFY destination_type ENUM(
    'GUDANG','BAR','KITCHEN','ROASTERY',
    'BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT',
    'OFFICE','OTHER'
  ) NOT NULL DEFAULT 'OTHER';

-- ------------------------------------------------------------
-- B. Buka enum location component / produksi batch
-- ------------------------------------------------------------
ALTER TABLE inv_component_adjustment
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

ALTER TABLE inv_component_batch
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

ALTER TABLE inv_component_monthly_opening
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

ALTER TABLE inv_component_monthly_opname
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

ALTER TABLE inv_component_monthly_stock
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

ALTER TABLE inv_component_movement_log
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

ALTER TABLE inv_component_opening
  MODIFY location_type ENUM('BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') NOT NULL DEFAULT 'KITCHEN';

-- ------------------------------------------------------------
-- C. Seed Purchase Type Roastery
-- ------------------------------------------------------------
INSERT INTO mst_purchase_type (
  type_code, type_name, posting_type_id, destination_behavior,
  default_destination, sort_order, notes, is_active, created_at, updated_at
)
SELECT
  'ROASTERY_STOK',
  'Belanja Stok Roastery',
  pt.id,
  'REQUIRED',
  'ROASTERY',
  6,
  'Pembelian bahan/stok langsung ke divisi ROASTERY',
  1,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM mst_posting_type pt
WHERE pt.type_code = 'INVENTORY'
LIMIT 1
ON DUPLICATE KEY UPDATE
  type_name = VALUES(type_name),
  posting_type_id = VALUES(posting_type_id),
  destination_behavior = VALUES(destination_behavior),
  default_destination = VALUES(default_destination),
  notes = VALUES(notes),
  is_active = 1,
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO mst_purchase_type (
  type_code, type_name, posting_type_id, destination_behavior,
  default_destination, sort_order, notes, is_active, created_at, updated_at
)
SELECT
  'ROASTERY_STOK_EVENT',
  'Belanja Stok Roastery Event',
  pt.id,
  'REQUIRED',
  'ROASTERY_EVENT',
  7,
  'Pembelian bahan/stok event ke divisi ROASTERY',
  1,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM mst_posting_type pt
WHERE pt.type_code = 'INVENTORY'
LIMIT 1
ON DUPLICATE KEY UPDATE
  type_name = VALUES(type_name),
  posting_type_id = VALUES(posting_type_id),
  destination_behavior = VALUES(destination_behavior),
  default_destination = VALUES(default_destination),
  notes = VALUES(notes),
  is_active = 1,
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO mst_purchase_type (
  type_code, type_name, posting_type_id, destination_behavior,
  default_destination, sort_order, notes, is_active, created_at, updated_at
)
SELECT
  'ROASTERY_OPS',
  'Barang Operasional Roastery',
  pt.id,
  'NONE',
  NULL,
  10,
  'Barang operasional/non-stok untuk divisi ROASTERY',
  1,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM mst_posting_type pt
WHERE pt.type_code = 'EXPENSE'
LIMIT 1
ON DUPLICATE KEY UPDATE
  type_name = VALUES(type_name),
  posting_type_id = VALUES(posting_type_id),
  destination_behavior = VALUES(destination_behavior),
  default_destination = VALUES(default_destination),
  notes = VALUES(notes),
  is_active = 1,
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- D. Seed Product Division Roastery untuk sales/POS
-- ------------------------------------------------------------
INSERT INTO mst_product_division (
  code, name, pos_scope, sort_order, default_operational_division_id,
  is_active, created_at, updated_at
)
SELECT
  'ROASTERY',
  'ROASTERY',
  'REGULAR',
  COALESCE((SELECT MAX(x.sort_order) + 1 FROM mst_product_division x), 10),
  od.id,
  1,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM mst_operational_division od
WHERE od.code = 'ROASTERY'
LIMIT 1
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  pos_scope = VALUES(pos_scope),
  default_operational_division_id = VALUES(default_operational_division_id),
  is_active = 1,
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ------------------------------------------------------------
-- E. Verifikasi ringkas
-- ------------------------------------------------------------
SELECT id, code, name, is_active
FROM mst_operational_division
WHERE code = 'ROASTERY';

SELECT type_code, type_name, default_destination, is_active
FROM mst_purchase_type
WHERE type_code IN ('ROASTERY_STOK', 'ROASTERY_STOK_EVENT', 'ROASTERY_OPS')
ORDER BY type_code;

SELECT code, name, default_operational_division_id, is_active
FROM mst_product_division
WHERE code = 'ROASTERY';
