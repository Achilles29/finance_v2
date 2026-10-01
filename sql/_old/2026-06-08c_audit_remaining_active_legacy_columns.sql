SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08c_audit_remaining_active_legacy_columns.sql
-- Tujuan :
-- 1) Menginventarisasi kolom stock_domain/line_kind yang masih hidup
--    di tabel aktif
-- 2) Menunjukkan mana yang masih non-nullable / masih ikut index
-- 3) Menjadi checklist phase drop berikutnya
-- ============================================================

SELECT
  c.TABLE_NAME,
  c.COLUMN_NAME,
  c.IS_NULLABLE,
  c.COLUMN_TYPE,
  c.COLUMN_DEFAULT
FROM information_schema.COLUMNS c
WHERE c.TABLE_SCHEMA = DATABASE()
  AND c.COLUMN_NAME IN ('stock_domain', 'line_kind')
  AND c.TABLE_NAME NOT LIKE '%backup%'
  AND c.TABLE_NAME NOT LIKE '%legacy%'
  AND c.TABLE_NAME NOT LIKE 'tmp_%'
ORDER BY c.TABLE_NAME, c.COLUMN_NAME;

SELECT
  s.TABLE_NAME,
  s.INDEX_NAME,
  GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX) AS indexed_columns
FROM information_schema.STATISTICS s
WHERE s.TABLE_SCHEMA = DATABASE()
  AND s.COLUMN_NAME IN ('stock_domain', 'line_kind')
  AND s.TABLE_NAME NOT LIKE '%backup%'
  AND s.TABLE_NAME NOT LIKE '%legacy%'
  AND s.TABLE_NAME NOT LIKE 'tmp_%'
GROUP BY s.TABLE_NAME, s.INDEX_NAME
ORDER BY s.TABLE_NAME, s.INDEX_NAME;
