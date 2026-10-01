SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06e_item_centric_stock_domain_material_audit.sql
-- Tujuan :
-- 1) Audit semua jejak stock_domain='MATERIAL' yang masih aktif dipakai
-- 2) Bedakan mana yang:
--    - material-only legacy (item_id NULL)
--    - item-linked legacy (item_id ada, material_id ada)
-- 3) Tunjukkan kandidat yang TIDAK aman diubah massal karena sudah punya
--    pasangan bucket ITEM untuk exact identity yang sama
--
-- Catatan:
-- - Script ini TIDAK mengubah data apa pun
-- - Script ini aman untuk server yang beberapa tabel snapshot-nya
--   belum punya kolom stock_domain
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_material_domain_audit_summary;
CREATE TEMPORARY TABLE tmp_material_domain_audit_summary (
  table_name VARCHAR(100) NOT NULL,
  has_stock_domain_column TINYINT(1) NOT NULL DEFAULT 0,
  total_material_rows BIGINT NOT NULL DEFAULT 0,
  item_linked_rows BIGINT NOT NULL DEFAULT 0,
  material_only_rows BIGINT NOT NULL DEFAULT 0
);

SET @table_name := 'inv_stock_movement_log';
SET @has_column := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "VALUES ('", @table_name, "', 0, 0, 0, 0)"
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_monthly_stock';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_monthly_stock';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_daily_rollup';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_daily_rollup';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_stock_adjustment_line';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_stock_opening_snapshot';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_stock_opening_snapshot';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_stock_opening_snapshot';
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_column > 0,
  CONCAT(
    'INSERT INTO tmp_material_domain_audit_summary ',
    "SELECT '", @table_name, "', 1, COUNT(*), ",
    'COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN 1 ELSE 0 END), 0), ',
    'COALESCE(SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END), 0) ',
    'FROM ', @table_name, " WHERE stock_domain = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_material_domain_audit_summary VALUES ('", @table_name, "', 0, 0, 0, 0)")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- A. Ringkasan jumlah MATERIAL per tabel utama
-- ------------------------------------------------------------
SELECT *
FROM tmp_material_domain_audit_summary
ORDER BY table_name;

-- ------------------------------------------------------------
-- B. Kandidat material legacy yang sebenarnya sudah item-linked
--    Ini calon migrasi data paling logis, tapi belum tentu aman massal.
-- ------------------------------------------------------------
SELECT 'division_monthly_item_linked_material' AS audit_group,
       s.id,
       s.month_key,
       s.division_id,
       s.destination_type,
       s.item_id,
       s.material_id,
       s.buy_uom_id,
       s.content_uom_id,
       s.profile_key,
       s.identity_key,
       s.closing_qty_content,
       s.avg_cost_per_content,
       s.total_value
FROM inv_division_monthly_stock s
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL
  AND s.material_id IS NOT NULL
ORDER BY s.division_id, s.destination_type, s.material_id, s.item_id, s.id
LIMIT 200;

-- ------------------------------------------------------------
-- C. Kandidat yang BERBAHAYA kalau langsung diubah ke ITEM
--    karena exact identity yang sama sudah punya row ITEM.
-- ------------------------------------------------------------
SELECT
  s.id AS material_row_id,
  s.month_key,
  s.division_id,
  s.destination_type,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_key,
  s.identity_key AS material_identity_key,
  i.id AS item_row_id,
  i.identity_key AS item_identity_key,
  s.closing_qty_content AS material_qty,
  i.closing_qty_content AS item_qty
FROM inv_division_monthly_stock s
JOIN inv_division_monthly_stock i
  ON i.month_key = s.month_key
 AND i.division_id = s.division_id
 AND i.destination_type = s.destination_type
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id = s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND i.stock_domain = 'ITEM'
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL
ORDER BY s.division_id, s.destination_type, s.material_id, s.item_id, s.id
LIMIT 200;

-- ------------------------------------------------------------
-- D. Movement log item-linked yang masih MATERIAL
--    Ini bukti bahwa histori lama masih menyimpan domain legacy.
-- ------------------------------------------------------------
SELECT
  id,
  movement_date,
  movement_scope,
  division_id,
  destination_type,
  movement_type,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  qty_content_delta,
  stock_domain,
  ref_table,
  ref_id
FROM inv_stock_movement_log
WHERE stock_domain = 'MATERIAL'
  AND item_id IS NOT NULL
ORDER BY id DESC
LIMIT 200;

-- ------------------------------------------------------------
-- E. Summary risiko migrasi
-- ------------------------------------------------------------
SELECT 'division_monthly_material_item_linked_rows' AS metric, COUNT(*) AS total
FROM inv_division_monthly_stock
WHERE stock_domain = 'MATERIAL'
  AND item_id IS NOT NULL

UNION ALL

SELECT 'division_monthly_material_item_linked_with_item_twin', COUNT(*)
FROM inv_division_monthly_stock s
JOIN inv_division_monthly_stock i
  ON i.month_key = s.month_key
 AND i.division_id = s.division_id
 AND i.destination_type = s.destination_type
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id = s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND i.stock_domain = 'ITEM'
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL

UNION ALL

SELECT 'movement_log_material_item_linked_rows', COUNT(*)
FROM inv_stock_movement_log
WHERE stock_domain = 'MATERIAL'
  AND item_id IS NOT NULL;
