SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-21d_audit_pos_refund_overpayment.sql
-- Tujuan :
-- 1) Menemukan refund POS posted yang totalnya melebihi uang yang pernah
--    diterima dari order.
-- 2) Hanya membaca data sebagai daftar tindak lanjut manual.
--
-- Catatan:
-- - Jangan menghapus refund langsung dari database.
-- - Bila ada temuan, perbaikan harus memakai dokumen pembatalan/reversal
--   yang juga membalik kas dan stok secara dapat diaudit.
-- ============================================================

SELECT
  o.id AS order_id,
  o.order_no,
  o.status AS order_status,
  o.ordered_at,
  COALESCE(o.paid_total, 0) AS paid_total,
  COALESCE(SUM(r.refund_amount), 0) AS posted_refund_total,
  ROUND(COALESCE(SUM(r.refund_amount), 0) - COALESCE(o.paid_total, 0), 2) AS over_refund_amount,
  GROUP_CONCAT(
    CONCAT(r.refund_no, ' | ', DATE_FORMAT(r.refunded_at, '%Y-%m-%d %H:%i:%s'), ' | Rp ', FORMAT(r.refund_amount, 2))
    ORDER BY r.refunded_at, r.id
    SEPARATOR ' || '
  ) AS refund_documents
FROM pos_order o
INNER JOIN pos_refund r
  ON r.order_id = o.id
 AND r.refund_status = 'POSTED'
GROUP BY
  o.id,
  o.order_no,
  o.status,
  o.ordered_at,
  o.paid_total
HAVING COALESCE(SUM(r.refund_amount), 0) > COALESCE(o.paid_total, 0) + 0.009
ORDER BY over_refund_amount DESC, o.ordered_at DESC;
