SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-27c_online_food_payment_ux_defaults.sql
-- Tujuan :
-- 1) Mengisi template WhatsApp dan instruksi manual default Online Food
-- 2) Memilih payment method MIDTRANS bila tersedia
-- ============================================================

START TRANSACTION;

UPDATE pos_online_food_setting
SET manual_whatsapp_template = 'Halo admin, saya mau konfirmasi pesanan Online Food {order_no} dengan total Rp {total}. Mohon dibantu untuk metode pembayaran manual/COD dan estimasi pengantarannya.'
WHERE id = 1
  AND (manual_whatsapp_template IS NULL OR TRIM(manual_whatsapp_template) = '');

UPDATE pos_online_food_setting
SET manual_payment_instructions = 'Untuk pembayaran manual, hubungi admin melalui tombol WhatsApp. Setelah admin mengonfirmasi pesanan, kasir akan memproses order dan pembayaran dilakukan melalui POS.'
WHERE id = 1
  AND (manual_payment_instructions IS NULL OR TRIM(manual_payment_instructions) = '');

UPDATE pos_online_food_setting s
JOIN (
  SELECT id
  FROM pos_payment_method
  WHERE is_active = 1
    AND (
      UPPER(method_code) IN ('MDTR', 'MIDTRANS')
      OR UPPER(method_name) LIKE '%MIDTRANS%'
    )
  ORDER BY id ASC
  LIMIT 1
) pm ON 1 = 1
SET s.qris_payment_method_id = pm.id
WHERE s.id = 1
  AND s.qris_payment_method_id IS NULL;

COMMIT;

SELECT
  qris_payment_method_id,
  manual_whatsapp_template,
  manual_payment_instructions
FROM pos_online_food_setting
WHERE id = 1;
