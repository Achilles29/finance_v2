SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-22c_backfill_pos_voucher_usage_legacy_promo.sql
-- Tujuan :
-- 1) Memasukkan seluruh transaksi POS lama yang memiliki potongan
--    promo voucher atau voucher ke laporan Pemakaian Voucher.
-- 2) Memisahkan jejak voucher member lama dan promo voucher lama.
-- 3) Tidak menebak nama kampanye lama bila kode kampanye memang belum
--    pernah disimpan pada order/payment lama.
--
-- Prasyarat:
-- - Jalankan 2026-08-22b_pos_cashier_voucher_usage_report_foundation.sql
--   terlebih dahulu.
--
-- Catatan:
-- - Transaksi POS baru sudah mencatat kode voucher/kampanye secara tepat
--   melalui writer kasir.
-- - Untuk arsip lama, pos_order hanya menyimpan nominal promo/voucher,
--   bukan kode kampanye. Karena itu labelnya sengaja jujur sebagai arsip.
-- ============================================================

START TRANSACTION;

-- Promo kampanye lama, misalnya potongan SMADA, yang dahulu hanya tersimpan
-- sebagai promo_amount pada nota tanpa referensi campaign_id.
INSERT INTO pos_voucher_usage (
  source_key,
  voucher_kind,
  voucher_issue_id,
  campaign_id,
  voucher_code,
  voucher_label,
  member_id,
  order_id,
  payment_id,
  cashier_employee_id,
  face_value_amount,
  face_value_percent,
  applied_amount,
  usage_status,
  used_at,
  notes,
  created_at
)
SELECT
  CONCAT('LEGACY-PROMO:ORDER:', po.id) AS source_key,
  'CAMPAIGN' AS voucher_kind,
  NULL AS voucher_issue_id,
  NULL AS campaign_id,
  'PROMO-ARSIP' AS voucher_code,
  'Promo voucher arsip' AS voucher_label,
  po.member_id,
  po.id AS order_id,
  (
    SELECT pp.id
    FROM pos_payment pp
    WHERE pp.order_id = po.id
      AND pp.payment_type = 'FINAL'
      AND pp.payment_status = 'PAID'
    ORDER BY COALESCE(pp.paid_at, pp.created_at) DESC, pp.id DESC
    LIMIT 1
  ) AS payment_id,
  (
    SELECT pp.cashier_employee_id
    FROM pos_payment pp
    WHERE pp.order_id = po.id
      AND pp.payment_type = 'FINAL'
      AND pp.payment_status = 'PAID'
    ORDER BY COALESCE(pp.paid_at, pp.created_at) DESC, pp.id DESC
    LIMIT 1
  ) AS cashier_employee_id,
  0.00 AS face_value_amount,
  0.0000 AS face_value_percent,
  ROUND(COALESCE(po.promo_amount, 0), 2) AS applied_amount,
  CASE WHEN UPPER(COALESCE(po.status, '')) = 'VOID' THEN 'VOID' ELSE 'APPLIED' END AS usage_status,
  COALESCE(po.paid_at, po.ordered_at, po.created_at, NOW()) AS used_at,
  'Backfill promo voucher arsip: nominal tersimpan, tetapi kode kampanye belum dicatat pada transaksi lama.' AS notes,
  COALESCE(po.paid_at, po.ordered_at, po.created_at, NOW()) AS created_at
FROM pos_order po
WHERE COALESCE(po.promo_amount, 0) > 0.009
  AND NOT EXISTS (
    SELECT 1
    FROM pos_voucher_usage u
    WHERE u.order_id = po.id
      AND u.usage_status <> 'VOID'
  );

-- Voucher member lama yang memiliki nilai voucher_amount, tetapi belum
-- memiliki redemption/log pemakaian yang dapat ditautkan ke voucher_issue.
INSERT INTO pos_voucher_usage (
  source_key,
  voucher_kind,
  voucher_issue_id,
  campaign_id,
  voucher_code,
  voucher_label,
  member_id,
  order_id,
  payment_id,
  cashier_employee_id,
  face_value_amount,
  face_value_percent,
  applied_amount,
  usage_status,
  used_at,
  notes,
  created_at
)
SELECT
  CONCAT('LEGACY-VOUCHER:ORDER:', po.id) AS source_key,
  'ISSUE' AS voucher_kind,
  NULL AS voucher_issue_id,
  NULL AS campaign_id,
  'VOUCHER-ARSIP' AS voucher_code,
  'Voucher member arsip' AS voucher_label,
  po.member_id,
  po.id AS order_id,
  (
    SELECT pp.id
    FROM pos_payment pp
    WHERE pp.order_id = po.id
      AND pp.payment_type = 'FINAL'
      AND pp.payment_status = 'PAID'
    ORDER BY COALESCE(pp.paid_at, pp.created_at) DESC, pp.id DESC
    LIMIT 1
  ) AS payment_id,
  (
    SELECT pp.cashier_employee_id
    FROM pos_payment pp
    WHERE pp.order_id = po.id
      AND pp.payment_type = 'FINAL'
      AND pp.payment_status = 'PAID'
    ORDER BY COALESCE(pp.paid_at, pp.created_at) DESC, pp.id DESC
    LIMIT 1
  ) AS cashier_employee_id,
  0.00 AS face_value_amount,
  0.0000 AS face_value_percent,
  ROUND(COALESCE(po.voucher_amount, 0), 2) AS applied_amount,
  CASE WHEN UPPER(COALESCE(po.status, '')) = 'VOID' THEN 'VOID' ELSE 'APPLIED' END AS usage_status,
  COALESCE(po.paid_at, po.ordered_at, po.created_at, NOW()) AS used_at,
  'Backfill voucher arsip: nominal tersimpan, tetapi nomor voucher belum tercatat pada transaksi lama.' AS notes,
  COALESCE(po.paid_at, po.ordered_at, po.created_at, NOW()) AS created_at
FROM pos_order po
WHERE COALESCE(po.voucher_amount, 0) > 0.009
  AND NOT EXISTS (
    SELECT 1
    FROM pos_voucher_usage u
    WHERE u.order_id = po.id
      AND u.usage_status <> 'VOID'
  );

-- Bila transaksi lama memiliki satu kandidat kampanye AMOUNT yang unik
-- berdasarkan nominal potongan dan periode kampanye, tampilkan nama kampanye
-- tersebut. Ini aman untuk contoh SMADA40/60/70; kandidat ganda tetap arsip.
UPDATE pos_voucher_usage u
JOIN pos_order po ON po.id = u.order_id
JOIN (
  SELECT
    po2.id AS order_id,
    MIN(c.id) AS campaign_id
  FROM pos_order po2
  JOIN pos_voucher_campaign c
    ON UPPER(COALESCE(c.voucher_type, '')) = 'AMOUNT'
   AND ABS(COALESCE(c.discount_value, 0) - COALESCE(po2.promo_amount, 0)) < 0.005
   AND (c.start_date IS NULL OR DATE(COALESCE(po2.paid_at, po2.ordered_at)) >= c.start_date)
   AND (c.end_date IS NULL OR DATE(COALESCE(po2.paid_at, po2.ordered_at)) <= c.end_date)
  WHERE COALESCE(po2.promo_amount, 0) > 0.009
  GROUP BY po2.id
  HAVING COUNT(*) = 1
) inferred ON inferred.order_id = u.order_id
JOIN pos_voucher_campaign c ON c.id = inferred.campaign_id
SET
  u.campaign_id = c.id,
  u.voucher_code = c.campaign_code,
  u.voucher_label = c.campaign_name,
  u.notes = 'Backfill promo voucher arsip: kampanye dikenali unik dari nominal potongan dan periode kampanye.',
  u.updated_at = NOW()
WHERE u.source_key LIKE 'LEGACY-PROMO:ORDER:%'
  AND u.voucher_kind = 'CAMPAIGN'
  AND u.voucher_code = 'PROMO-ARSIP';

COMMIT;

SELECT
  voucher_kind,
  voucher_code,
  voucher_label,
  COUNT(*) AS total_nota,
  ROUND(SUM(applied_amount), 2) AS total_potongan
FROM pos_voucher_usage
GROUP BY voucher_kind, voucher_code, voucher_label
ORDER BY total_nota DESC, voucher_kind, voucher_code;
