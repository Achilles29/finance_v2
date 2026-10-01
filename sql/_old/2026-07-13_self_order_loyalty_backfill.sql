START TRANSACTION;

-- Backfill poin self-order QRIS yang sudah PAID tetapi ledger poin belum terbentuk.
-- Rule aktif: MEMBER01, 1 poin per Rp20.000 NET, minimal Rp100.000.
-- Target terdeteksi: MSO-20260706194735-D236 / payment MSP-20260706194735-471D.

INSERT INTO pos_point_ledger (
  member_id,
  order_id,
  payment_id,
  rule_id,
  ledger_type,
  points_in,
  points_out,
  balance_after,
  expired_at,
  notes,
  created_at
)
SELECT
  o.member_id,
  o.id,
  p.id,
  r.id,
  'EARN',
  FLOOR(o.grand_total / r.amount_per_point),
  0,
  COALESCE((
    SELECT SUM(pl.points_in - pl.points_out)
    FROM pos_point_ledger pl
    WHERE pl.member_id = o.member_id
  ), 0) + FLOOR(o.grand_total / r.amount_per_point),
  CASE
    WHEN r.point_expiry_days > 0
      THEN DATE_ADD(DATE(p.paid_at), INTERVAL r.point_expiry_days DAY)
    ELSE NULL
  END,
  'Poin otomatis dari pembayaran POS (backfill self-order QRIS 2026-07-13)',
  p.paid_at
FROM pos_order o
JOIN pos_payment p
  ON p.order_id = o.id
 AND p.payment_type = 'FINAL'
 AND p.payment_status = 'PAID'
JOIN pos_point_rule r
  ON r.rule_code = 'MEMBER01'
 AND r.is_active = 1
WHERE o.id = 1523
  AND o.status = 'PAID'
  AND o.member_id IS NOT NULL
  AND o.grand_total >= r.min_spend_amount
  AND r.amount_per_point > 0
  AND FLOOR(o.grand_total / r.amount_per_point) > 0
  AND NOT EXISTS (
    SELECT 1
    FROM pos_point_ledger x
    WHERE x.member_id = o.member_id
      AND x.order_id = o.id
      AND x.payment_id = p.id
      AND x.rule_id = r.id
      AND x.ledger_type = 'EARN'
  );

UPDATE crm_member m
SET m.point_balance_cache = COALESCE((
      SELECT SUM(pl.points_in - pl.points_out)
      FROM pos_point_ledger pl
      WHERE pl.member_id = m.id
    ), 0),
    m.stamp_balance_cache = COALESCE((
      SELECT SUM(sl.stamp_in - sl.stamp_out)
      FROM pos_stamp_ledger sl
      WHERE sl.member_id = m.id
    ), 0),
    m.updated_at = NOW()
WHERE m.id = 207;

COMMIT;
