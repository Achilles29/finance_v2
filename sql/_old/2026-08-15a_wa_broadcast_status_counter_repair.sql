START TRANSACTION;

-- Sinkronkan ulang counter/status broadcast dari wa_broadcast_line.
-- Tidak mengubah isi pesan atau target. Hanya memperbaiki header agar sesuai hasil kirim aktual.

CREATE TABLE IF NOT EXISTS zz_bak_wa_broadcast_20260815 AS
SELECT *
FROM wa_broadcast;

DROP TEMPORARY TABLE IF EXISTS tmp_wa_broadcast_line_summary;
CREATE TEMPORARY TABLE tmp_wa_broadcast_line_summary AS
SELECT
  broadcast_id,
  COUNT(*) AS total_targets,
  SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) AS total_sent,
  SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS total_failed,
  SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS total_pending,
  MAX(sent_at) AS last_sent_at
FROM wa_broadcast_line
GROUP BY broadcast_id;

UPDATE wa_broadcast b
LEFT JOIN tmp_wa_broadcast_line_summary s ON s.broadcast_id = b.id
SET b.total_targets = COALESCE(s.total_targets, 0),
    b.total_sent = COALESCE(s.total_sent, 0),
    b.total_failed = COALESCE(s.total_failed, 0);

UPDATE wa_broadcast b
LEFT JOIN tmp_wa_broadcast_line_summary s ON s.broadcast_id = b.id
SET b.status = CASE
    WHEN COALESCE(s.total_targets, 0) = 0 THEN 'DRAFT'
    WHEN COALESCE(s.total_pending, 0) > 0 THEN 'DRAFT'
    WHEN COALESCE(s.total_failed, 0) > 0 THEN 'FAILED'
    ELSE 'DONE'
  END,
  b.finished_at = CASE
    WHEN COALESCE(s.total_pending, 0) = 0 AND COALESCE(s.total_targets, 0) > 0 AND s.last_sent_at IS NOT NULL THEN s.last_sent_at
    WHEN COALESCE(s.total_pending, 0) = 0 AND COALESCE(s.total_targets, 0) > 0 THEN b.finished_at
    ELSE NULL
  END
WHERE b.status NOT IN ('SENDING', 'CANCELLED');

COMMIT;
