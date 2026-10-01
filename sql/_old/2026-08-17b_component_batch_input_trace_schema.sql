SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-17b_component_batch_input_trace_schema.sql
-- Tujuan :
-- 1) Menyiapkan kolom jejak input batch component secara terkontrol
-- 2) Menghilangkan ALTER TABLE dari request simpan/post batch produksi
-- 3) Menjaga endpoint batch selalu dapat memberi respons JSON
-- ============================================================

START TRANSACTION;

ALTER TABLE inv_component_batch_input
  ADD COLUMN IF NOT EXISTS division_id BIGINT UNSIGNED NULL AFTER source_kind,
  ADD COLUMN IF NOT EXISTS item_id BIGINT UNSIGNED NULL AFTER division_id,
  ADD COLUMN IF NOT EXISTS plan_role VARCHAR(40) NULL AFTER line_no,
  ADD COLUMN IF NOT EXISTS fifo_issue_id BIGINT UNSIGNED NULL AFTER total_cost,
  ADD COLUMN IF NOT EXISTS fifo_issue_no VARCHAR(60) NULL AFTER fifo_issue_id;

COMMIT;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'inv_component_batch_input'
  AND COLUMN_NAME IN ('division_id', 'item_id', 'plan_role', 'fifo_issue_id', 'fifo_issue_no')
ORDER BY FIELD(COLUMN_NAME, 'division_id', 'item_id', 'plan_role', 'fifo_issue_id', 'fifo_issue_no');
