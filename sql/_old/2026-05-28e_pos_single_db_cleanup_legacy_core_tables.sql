SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28e_pos_single_db_cleanup_legacy_core_tables.sql
-- Tujuan :
-- 1) Membersihkan tabel legacy hasil adopsi core yang tidak dipakai lagi
-- 2) Menegaskan standar lokal:
--    - member     => crm_member
--    - settlement => fin_company_account
-- 3) Menghapus kolom legacy yang membingungkan dari runtime POS
-- Catatan:
-- - Jalankan SETELAH 2026-05-28d_pos_single_db_import_from_core.sql sukses.
-- - File ini sengaja memakai guard agar tidak menghapus data sebelum import lokal siap.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Guard: pastikan target lokal sudah berisi data
-- ------------------------------------------------------------
SET @crm_member_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_member'
);

SET @crm_member_rows := IF(
  @crm_member_exists > 0,
  (SELECT COUNT(*) FROM crm_member),
  0
);

SET @legacy_member_rows := (
  SELECT
    COALESCE((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_customer'), 0)
);

SET @legacy_member_total := 0;
SET @legacy_member_total := @legacy_member_total + IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_customer') > 0,
  (SELECT COUNT(*) FROM crm_customer),
  0
);
SET @legacy_member_total := @legacy_member_total + IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_member_account') > 0,
  (SELECT COUNT(*) FROM crm_member_account),
  0
);

SET @fin_company_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fin_company_account'
);

SET @fin_company_rows := IF(
  @fin_company_exists > 0,
  (SELECT COUNT(*) FROM fin_company_account),
  0
);

SET @legacy_bank_rows := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'm_bank_account') > 0,
  (SELECT COUNT(*) FROM m_bank_account),
  0
);

SET @guard_message := NULL;

SET @guard_message := IF(
  @crm_member_rows = 0 AND @legacy_member_total > 0,
  'Cleanup dibatalkan: crm_member masih kosong sementara tabel legacy member masih berisi data. Jalankan import 2026-05-28d terlebih dulu.',
  @guard_message
);

SET @guard_message := IF(
  @guard_message IS NULL AND @fin_company_rows = 0 AND @legacy_bank_rows > 0,
  'Cleanup dibatalkan: fin_company_account masih kosong sementara m_bank_account masih berisi data. Jalankan import 2026-05-28d terlebih dulu.',
  @guard_message
);

SET @sql_guard := IF(
  @guard_message IS NOT NULL,
  CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''', REPLACE(@guard_message, '''', ''''''), ''''),
  'SELECT 1'
);
PREPARE stmt FROM @sql_guard; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- B. Lepas FK/index legacy payment method
-- ------------------------------------------------------------
SET @has_idx_pm_bank := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_payment_method'
    AND INDEX_NAME = 'idx_pos_payment_method_bank'
);
SET @sql_drop_idx_pm_bank := IF(
  @has_idx_pm_bank > 0,
  'ALTER TABLE pos_payment_method DROP INDEX idx_pos_payment_method_bank',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_idx_pm_bank; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col_pm_bank := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_payment_method'
    AND COLUMN_NAME = 'bank_account_id'
);
SET @sql_drop_col_pm_bank := IF(
  @has_col_pm_bank > 0,
  'ALTER TABLE pos_payment_method DROP COLUMN bank_account_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_col_pm_bank; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- C. Drop FK legacy member tables
-- ------------------------------------------------------------
SET @has_fk_member_customer := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_member_account'
    AND CONSTRAINT_NAME = 'fk_crm_member_customer'
);
SET @sql_drop_fk_member_customer := IF(
  @has_fk_member_customer > 0,
  'ALTER TABLE crm_member_account DROP FOREIGN KEY fk_crm_member_customer',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_fk_member_customer; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- D. Drop legacy tables
-- ------------------------------------------------------------
SET @has_table_member_account := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_member_account'
);
SET @sql_drop_table_member_account := IF(
  @has_table_member_account > 0,
  'DROP TABLE crm_member_account',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_table_member_account; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_table_customer := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_customer'
);
SET @sql_drop_table_customer := IF(
  @has_table_customer > 0,
  'DROP TABLE crm_customer',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_table_customer; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_table_bank := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'm_bank_account'
);
SET @sql_drop_table_bank := IF(
  @has_table_bank > 0,
  'DROP TABLE m_bank_account',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_table_bank; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

-- Quick check
SELECT 'crm_member' AS table_name, COUNT(*) AS total_rows FROM crm_member
UNION ALL
SELECT 'fin_company_account', COUNT(*) FROM fin_company_account
UNION ALL
SELECT 'legacy.crm_customer.exists', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_customer'
UNION ALL
SELECT 'legacy.crm_member_account.exists', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_member_account'
UNION ALL
SELECT 'legacy.m_bank_account.exists', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'm_bank_account'
UNION ALL
SELECT 'legacy.pos_payment_method.bank_account_id.exists', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_payment_method' AND COLUMN_NAME = 'bank_account_id';
