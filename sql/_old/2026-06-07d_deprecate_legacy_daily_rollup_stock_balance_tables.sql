SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07d_deprecate_legacy_daily_rollup_stock_balance_tables.sql
-- Tujuan :
-- 1) Menandai tabel legacy daily_rollup / stock_balance sebagai
--    backup deprecated dengan RENAME TABLE
-- 2) Menghindari DROP langsung agar rollback masih mudah
--
-- Catatan penting:
-- - Jalankan SETELAH audit dependency DB bersih/diterima
-- - Script ini TIDAK menghapus data
-- - Jika tabel backup tujuan sudah ada, script akan STOP
-- ============================================================

SET @suffix := '_legacy_backup_20260607';

SET @src1 := 'inv_warehouse_daily_rollup';
SET @dst1 := CONCAT(@src1, @suffix);
SET @src2 := 'inv_division_daily_rollup';
SET @dst2 := CONCAT(@src2, @suffix);
SET @src3 := 'inv_component_daily_rollup';
SET @dst3 := CONCAT(@src3, @suffix);
SET @src4 := 'inv_warehouse_stock_balance';
SET @dst4 := CONCAT(@src4, @suffix);
SET @src5 := 'inv_division_stock_balance';
SET @dst5 := CONCAT(@src5, @suffix);
SET @src6 := 'inv_component_stock_balance';
SET @dst6 := CONCAT(@src6, @suffix);

SET @guard_message := NULL;

SET @guard_message := IF(
  @guard_message IS NULL
  AND EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN (@dst1, @dst2, @dst3, @dst4, @dst5, @dst6)
  ),
  'Salah satu tabel backup legacy sudah ada. Batalkan agar tidak menimpa backup lama.',
  @guard_message
);

SET @sql_guard := IF(
  @guard_message IS NOT NULL,
  CONCAT(
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''',
    REPLACE(@guard_message, '''', ''''''),
    ''''
  ),
  'SELECT 1'
);
PREPARE stmt_guard FROM @sql_guard; EXECUTE stmt_guard; DEALLOCATE PREPARE stmt_guard;

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_rename_pairs;
CREATE TEMPORARY TABLE tmp_legacy_rename_pairs (
  src_name VARCHAR(100) NOT NULL,
  dst_name VARCHAR(140) NOT NULL
);

INSERT INTO tmp_legacy_rename_pairs (src_name, dst_name)
SELECT @src1, @dst1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @src1
UNION ALL
SELECT @src2, @dst2 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @src2
UNION ALL
SELECT @src3, @dst3 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @src3
UNION ALL
SELECT @src4, @dst4 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @src4
UNION ALL
SELECT @src5, @dst5 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @src5
UNION ALL
SELECT @src6, @dst6 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @src6;

SET @sql_rename := (
  SELECT CASE
    WHEN COUNT(*) = 0 THEN 'SELECT ''no_legacy_tables_found'' AS status'
    ELSE CONCAT(
      'RENAME TABLE ',
      GROUP_CONCAT(CONCAT(src_name, ' TO ', dst_name) ORDER BY src_name SEPARATOR ', ')
    )
  END
  FROM tmp_legacy_rename_pairs
);

SET @sql_exec := @sql_rename;
PREPARE stmt_rename FROM @sql_exec; EXECUTE stmt_rename; DEALLOCATE PREPARE stmt_rename;

SELECT 'legacy_backup_table_exists' AS metric, COUNT(*) AS total
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (@dst1, @dst2, @dst3, @dst4, @dst5, @dst6)

UNION ALL

SELECT 'legacy_source_table_remaining', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (@src1, @src2, @src3, @src4, @src5, @src6);
