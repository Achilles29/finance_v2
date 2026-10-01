SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-08a_backfill_auto_voucher_all_customers.sql
-- Tujuan :
-- 1) Menerbitkan AUTO VOUCHER untuk semua transaksi paid yang
--    memenuhi syarat campaign, termasuk customer non-member
-- 2) Menjaga member_id boleh NULL supaya voucher bisa dipakai
--    sebagai pemicu customer mendaftar member di transaksi berikutnya
-- 3) Tidak menggandakan voucher yang sudah pernah terbit untuk
--    kombinasi campaign + order + payment yang sama
-- ============================================================

START TRANSACTION;

INSERT INTO pos_voucher_issue (
  voucher_issue_no,
  campaign_id,
  member_id,
  source_order_id,
  source_payment_id,
  voucher_code,
  voucher_status,
  amount_snapshot,
  percent_snapshot,
  min_spend_amount,
  issued_at,
  expired_at,
  notes,
  created_at
)
SELECT
  CONCAT('VCH-BF-', LPAD(p.id, 12, '0')) AS voucher_issue_no,
  c.id AS campaign_id,
  NULLIF(COALESCE(o.member_id, p.member_id, 0), 0) AS member_id,
  o.id AS source_order_id,
  p.id AS source_payment_id,
  CONCAT('AUTO-', DATE_FORMAT(COALESCE(p.paid_at, o.paid_at, p.created_at), '%Y%m%d'), '-', LPAD(p.id, 6, '0')) AS voucher_code,
  CASE
    WHEN c.valid_day_count > 0
     AND DATE_ADD(COALESCE(p.paid_at, o.paid_at, p.created_at), INTERVAL c.valid_day_count DAY) < NOW()
      THEN 'EXPIRED'
    ELSE 'OPEN'
  END AS voucher_status,
  CASE WHEN c.voucher_type = 'PERCENT' THEN 0 ELSE ROUND(COALESCE(c.discount_value, 0), 2) END AS amount_snapshot,
  CASE WHEN c.voucher_type = 'PERCENT' THEN ROUND(COALESCE(c.discount_value, 0), 4) ELSE 0 END AS percent_snapshot,
  c.min_spend_amount,
  COALESCE(p.paid_at, o.paid_at, p.created_at) AS issued_at,
  CASE
    WHEN c.valid_day_count > 0 THEN DATE_ADD(COALESCE(p.paid_at, o.paid_at, p.created_at), INTERVAL c.valid_day_count DAY)
    ELSE NULL
  END AS expired_at,
  'Backfill AUTO VOUCHER semua customer 2026-07-08' AS notes,
  COALESCE(p.paid_at, o.paid_at, p.created_at) AS created_at
FROM pos_voucher_campaign c
JOIN pos_order o
JOIN pos_payment p
  ON p.order_id = o.id
 AND p.payment_type = 'FINAL'
 AND p.payment_status = 'PAID'
LEFT JOIN pos_voucher_issue existing
  ON existing.campaign_id = c.id
 AND existing.source_order_id = o.id
 AND existing.source_payment_id = p.id
WHERE c.is_active = 1
  AND c.issue_mode = 'AUTO_FROM_TXN'
  AND existing.id IS NULL
  AND COALESCE(o.grand_total, 0) >= COALESCE(c.min_spend_amount, 0)
  AND (c.start_date IS NULL OR DATE(COALESCE(p.paid_at, o.paid_at, p.created_at)) >= c.start_date)
  AND (c.end_date IS NULL OR DATE(COALESCE(p.paid_at, o.paid_at, p.created_at)) <= c.end_date)
  AND (
    c.trigger_product_id IS NULL
    OR EXISTS (
      SELECT 1
      FROM pos_order_line ol
      WHERE ol.order_id = o.id
        AND ol.product_id = c.trigger_product_id
        AND COALESCE(ol.qty, 0) > 0
    )
  );

COMMIT;

SELECT
  c.campaign_code,
  c.campaign_name,
  COUNT(v.id) AS total_voucher,
  SUM(v.voucher_status = 'OPEN') AS open_voucher,
  SUM(v.voucher_status = 'EXPIRED') AS expired_voucher,
  SUM(v.member_id IS NULL) AS non_member_voucher
FROM pos_voucher_campaign c
LEFT JOIN pos_voucher_issue v ON v.campaign_id = c.id
WHERE c.issue_mode = 'AUTO_FROM_TXN'
GROUP BY c.id, c.campaign_code, c.campaign_name
ORDER BY c.id;
