SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-26a_cancel_terminal_pos_runtime_jobs.sql
-- Tujuan :
-- 1) Menutup job stock commit POS lama yang masih aktif, tetapi order
--    atau snapshot-nya sudah VOID / refund penuh / reversed.
-- 2) Mencegah worker lama memproses ulang transaksi yang sudah selesai.
--
-- Aman dijalankan berulang:
-- - Hanya job QUEUED, PROCESSING, dan FAILED yang diperbarui.
-- - Tidak mengubah order, pembayaran, stok, lot, HPP, atau jurnal.
-- ============================================================

START TRANSACTION;

UPDATE pos_runtime_job job
INNER JOIN pos_order order_header
  ON order_header.id = job.order_id
LEFT JOIN pos_stock_commit commit_snapshot
  ON commit_snapshot.id = job.snapshot_id
SET
  job.status = 'CANCELLED',
  job.finished_at = NOW(),
  job.last_error = CASE
    WHEN COALESCE(job.last_error, '') = ''
      THEN 'Dibatalkan otomatis: order atau snapshot stock commit sudah terminal.'
    ELSE CONCAT(job.last_error, ' | Dibatalkan otomatis: order atau snapshot sudah terminal.')
  END
WHERE job.job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
  AND job.status IN ('QUEUED', 'PROCESSING', 'FAILED')
  AND (
  UPPER(COALESCE(order_header.status, '')) IN ('VOID', 'REFUND_FULL', 'REFUNDED_FULL')
  OR UPPER(COALESCE(order_header.stock_commit_status, '')) = 'REVERSED'
    OR UPPER(COALESCE(commit_snapshot.commit_status, '')) IN ('REVERSED', 'VOID')
  );

SELECT ROW_COUNT() AS total_job_dibatalkan;

COMMIT;

SELECT
  job.id,
  job.job_code,
  job.status AS job_status,
  order_header.order_no,
  order_header.status AS order_status,
  order_header.stock_commit_status,
  commit_snapshot.commit_no,
  commit_snapshot.commit_status AS snapshot_commit_status,
  job.finished_at,
  job.last_error
FROM pos_runtime_job job
INNER JOIN pos_order order_header
  ON order_header.id = job.order_id
LEFT JOIN pos_stock_commit commit_snapshot
  ON commit_snapshot.id = job.snapshot_id
WHERE job.job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
  AND job.status = 'CANCELLED'
  AND (
    UPPER(COALESCE(order_header.status, '')) IN ('VOID', 'REFUND_FULL', 'REFUNDED_FULL')
    OR UPPER(COALESCE(order_header.stock_commit_status, '')) = 'REVERSED'
    OR UPPER(COALESCE(commit_snapshot.commit_status, '')) IN ('REVERSED', 'VOID')
  )
ORDER BY job.id DESC;
