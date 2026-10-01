SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-26b_finance_cash_reconciliation_rounds.sql
-- Tujuan :
-- 1) Mengubah rekonsiliasi kas dari satu dokumen harian menjadi
--    beberapa sesi pengecekan dalam satu tanggal.
-- 2) Mengganti status legacy DRAFT menjadi OPEN / Rekon Berjalan.
-- 3) Tidak mengubah saldo rekening dan tidak membuat mutasi baru.
-- ============================================================

ALTER TABLE fin_cash_reconciliation
  ADD COLUMN IF NOT EXISTS round_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER reconciliation_date;

ALTER TABLE fin_cash_reconciliation
  ADD COLUMN IF NOT EXISTS reconciled_at DATETIME NULL AFTER round_no;

-- Perluasan enum dilakukan sebelum data legacy DRAFT dipindahkan.
ALTER TABLE fin_cash_reconciliation
  MODIFY COLUMN status ENUM('DRAFT','OPEN','REVIEWED','COMPLETED') NOT NULL DEFAULT 'OPEN';

SET @has_legacy_unique_date := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fin_cash_reconciliation'
    AND INDEX_NAME = 'uk_fin_cash_recon_date'
);
SET @sql_drop_legacy_unique_date := IF(
  @has_legacy_unique_date > 0,
  'ALTER TABLE fin_cash_reconciliation DROP INDEX uk_fin_cash_recon_date',
  'SELECT 1'
);
PREPARE stmt_drop_legacy_unique_date FROM @sql_drop_legacy_unique_date;
EXECUTE stmt_drop_legacy_unique_date;
DEALLOCATE PREPARE stmt_drop_legacy_unique_date;

START TRANSACTION;

UPDATE fin_cash_reconciliation
SET round_no = 1
WHERE round_no IS NULL OR round_no < 1;

UPDATE fin_cash_reconciliation
SET reconciled_at = COALESCE(reconciled_at, updated_at, created_at, CONCAT(reconciliation_date, ' 00:00:00'))
WHERE reconciled_at IS NULL;

UPDATE fin_cash_reconciliation
SET status = 'OPEN'
WHERE status = 'DRAFT';

COMMIT;

SET @has_date_round_unique := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fin_cash_reconciliation'
    AND INDEX_NAME = 'uk_fin_cash_recon_date_round'
);
SET @sql_add_date_round_unique := IF(
  @has_date_round_unique = 0,
  'ALTER TABLE fin_cash_reconciliation ADD UNIQUE KEY uk_fin_cash_recon_date_round (reconciliation_date, round_no)',
  'SELECT 1'
);
PREPARE stmt_add_date_round_unique FROM @sql_add_date_round_unique;
EXECUTE stmt_add_date_round_unique;
DEALLOCATE PREPARE stmt_add_date_round_unique;

ALTER TABLE fin_cash_reconciliation
  MODIFY COLUMN status ENUM('OPEN','REVIEWED','COMPLETED') NOT NULL DEFAULT 'OPEN';

SELECT
  h.id,
  h.reconciliation_no,
  h.reconciliation_date,
  h.round_no,
  h.reconciled_at,
  h.status,
  COUNT(l.id) AS line_count,
  SUM(CASE WHEN l.actual_balance IS NOT NULL THEN 1 ELSE 0 END) AS counted_count,
  SUM(CASE WHEN l.status = 'OPEN' THEN 1 ELSE 0 END) AS open_count,
  SUM(CASE WHEN l.status = 'POSTED' THEN 1 ELSE 0 END) AS posted_count
FROM fin_cash_reconciliation h
LEFT JOIN fin_cash_reconciliation_line l ON l.reconciliation_id = h.id
GROUP BY h.id, h.reconciliation_no, h.reconciliation_date, h.round_no, h.reconciled_at, h.status
ORDER BY h.reconciliation_date DESC, h.round_no DESC, h.id DESC;
