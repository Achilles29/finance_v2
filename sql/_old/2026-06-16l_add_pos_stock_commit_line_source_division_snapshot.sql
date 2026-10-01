SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16l_add_pos_stock_commit_line_source_division_snapshot.sql
-- Tujuan :
-- 1) Menyimpan snapshot source division per commit line POS
-- 2) Mengunci histori agar repair/audit tidak bergantung penuh
--    pada recipe aktif yang bisa berubah di kemudian hari
-- 3) Menjadi fondasi audit lintas divisi untuk material/component
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_stock_commit_line
  ADD COLUMN IF NOT EXISTS resolved_source_division_id BIGINT UNSIGNED NULL AFTER component_id,
  ADD COLUMN IF NOT EXISTS resolved_source_division_code VARCHAR(30) NULL AFTER resolved_source_division_id,
  ADD COLUMN IF NOT EXISTS resolved_source_division_name VARCHAR(100) NULL AFTER resolved_source_division_code;

ALTER TABLE pos_stock_commit_line
  ADD KEY idx_pos_stock_commit_line_source_division (resolved_source_division_id),
  ADD KEY idx_pos_stock_commit_line_commit_source (commit_id, resolved_source_division_id, material_id, component_id);

COMMIT;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_stock_commit_line'
  AND COLUMN_NAME IN ('resolved_source_division_id', 'resolved_source_division_code', 'resolved_source_division_name')
ORDER BY COLUMN_NAME;
