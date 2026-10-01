SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6P - Cleanup mode rekening tunggal (opsi 2)
-- Tujuan:
-- 1) Nonaktifkan menu/halaman rekening duplikat yang membingungkan
-- 2) Nonaktifkan layer payment channel
-- 3) Hapus tabel payment channel jika sudah tidak dipakai
-- ============================================================
START TRANSACTION;

-- ------------------------------------------------------------
-- A. Nonaktifkan menu/pages yang tidak dipakai
-- ------------------------------------------------------------
UPDATE sys_menu
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code IN (
  'purchase.account',
  'master.purchase.payment_channel'
);

UPDATE sys_page
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE page_code IN (
  'purchase.account.index',
  'master.purchase.payment_channel'
);

-- ------------------------------------------------------------
-- B. Nonaktifkan permission untuk page yang tidak dipakai
-- ------------------------------------------------------------
UPDATE auth_role_permission rp
JOIN sys_page p ON p.id = rp.page_id
SET rp.can_view = 0,
    rp.can_create = 0,
    rp.can_edit = 0,
    rp.can_delete = 0,
    rp.can_export = 0,
    rp.updated_at = CURRENT_TIMESTAMP
WHERE p.page_code IN (
  'purchase.account.index',
  'master.purchase.payment_channel'
);

-- ------------------------------------------------------------
-- C. Lepas relasi payment_channel pada payment plan (jika ada)
-- ------------------------------------------------------------
SET @fk_channel := (
  SELECT kcu.CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE kcu
  WHERE kcu.TABLE_SCHEMA = DATABASE()
    AND kcu.TABLE_NAME = 'pur_purchase_payment_plan'
    AND kcu.COLUMN_NAME = 'payment_channel_id'
    AND kcu.REFERENCED_TABLE_NAME = 'pur_payment_channel'
  LIMIT 1
);

SET @sql_drop_fk_channel := IF(
  @fk_channel IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE pur_purchase_payment_plan DROP FOREIGN KEY ', @fk_channel)
);
PREPARE stmt_drop_fk_channel FROM @sql_drop_fk_channel;
EXECUTE stmt_drop_fk_channel;
DEALLOCATE PREPARE stmt_drop_fk_channel;

SET @idx_channel := (
  SELECT s.INDEX_NAME
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE()
    AND s.TABLE_NAME = 'pur_purchase_payment_plan'
    AND s.COLUMN_NAME = 'payment_channel_id'
    AND s.INDEX_NAME <> 'PRIMARY'
  LIMIT 1
);

SET @sql_drop_idx_channel := IF(
  @idx_channel IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE pur_purchase_payment_plan DROP INDEX ', @idx_channel)
);
PREPARE stmt_drop_idx_channel FROM @sql_drop_idx_channel;
EXECUTE stmt_drop_idx_channel;
DEALLOCATE PREPARE stmt_drop_idx_channel;

-- Kolom payment_channel_id boleh dihapus agar skema lebih bersih.
-- Jika ingin tetap simpan untuk histori, komentari blok di bawah.
SET @has_col_payment_channel := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS c
  WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.TABLE_NAME = 'pur_purchase_payment_plan'
    AND c.COLUMN_NAME = 'payment_channel_id'
);

SET @sql_drop_col_payment_channel := IF(
  @has_col_payment_channel = 0,
  'SELECT 1',
  'ALTER TABLE pur_purchase_payment_plan DROP COLUMN payment_channel_id'
);
PREPARE stmt_drop_col_payment_channel FROM @sql_drop_col_payment_channel;
EXECUTE stmt_drop_col_payment_channel;
DEALLOCATE PREPARE stmt_drop_col_payment_channel;

-- ------------------------------------------------------------
-- D. Hapus tabel payment channel jika sudah tidak dipakai
-- ------------------------------------------------------------
DROP TABLE IF EXISTS pur_payment_channel;

COMMIT;

-- Verifikasi ringkas
SELECT page_code, is_active
FROM sys_page
WHERE page_code IN ('purchase.account.index', 'master.purchase.payment_channel')
ORDER BY page_code;

SELECT menu_code, is_active
FROM sys_menu
WHERE menu_code IN ('purchase.account', 'master.purchase.payment_channel')
ORDER BY menu_code;

SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pur_payment_channel';
