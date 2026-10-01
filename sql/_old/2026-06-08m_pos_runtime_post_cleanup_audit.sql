SET NAMES utf8mb4;

-- ============================================================
-- AUDIT: POS runtime post-cleanup
-- Tanggal: 2026-06-08
--
-- Tujuan:
-- - melihat ringkasan job POS runtime
-- - melihat job FAILED terbaru
-- - melihat order yang stock_commit_status-nya belum sukses
-- ============================================================

SELECT
  status,
  COUNT(*) AS total_jobs
FROM pos_runtime_job
GROUP BY status
ORDER BY status;

SELECT
  j.id,
  j.job_code,
  j.status,
  j.attempts,
  j.max_attempts,
  j.order_id,
  o.order_no,
  o.outlet_id,
  po.outlet_name,
  o.stock_commit_status,
  j.snapshot_id,
  s.commit_no,
  s.commit_status,
  LEFT(COALESCE(j.last_error, ''), 255) AS last_error_short,
  j.run_after,
  j.started_at,
  j.finished_at,
  j.updated_at
FROM pos_runtime_job j
LEFT JOIN pos_order o ON o.id = j.order_id
LEFT JOIN pos_outlet po ON po.id = o.outlet_id
LEFT JOIN pos_stock_commit s ON s.id = j.snapshot_id
WHERE j.status = 'FAILED'
ORDER BY COALESCE(j.updated_at, j.created_at) DESC
LIMIT 25;

SELECT
  o.id,
  o.order_no,
  o.outlet_id,
  po.outlet_name,
  o.status AS order_status,
  o.stock_commit_status,
  o.stock_committed_at,
  latest.job_code,
  latest.job_status,
  latest.job_attempts,
  latest.commit_no,
  latest.commit_status,
  LEFT(COALESCE(latest.last_error, ''), 255) AS latest_last_error
FROM pos_order o
LEFT JOIN pos_outlet po ON po.id = o.outlet_id
LEFT JOIN (
  SELECT
    x.order_id,
    x.job_code,
    x.status AS job_status,
    x.attempts AS job_attempts,
    x.last_error,
    s.commit_no,
    s.commit_status
  FROM pos_runtime_job x
  LEFT JOIN pos_stock_commit s ON s.id = x.snapshot_id
  INNER JOIN (
    SELECT order_id, MAX(id) AS max_id
    FROM pos_runtime_job
    WHERE job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
    GROUP BY order_id
  ) y ON y.max_id = x.id
) latest ON latest.order_id = o.id
WHERE COALESCE(o.stock_commit_status, '') <> 'SUCCESS'
ORDER BY o.id DESC
LIMIT 50;
