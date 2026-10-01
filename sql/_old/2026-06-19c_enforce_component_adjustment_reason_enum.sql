SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-19c_enforce_component_adjustment_reason_enum.sql
-- Tujuan :
-- 1) Mengunci kolom reason_code adjustment component ke enum baku
-- 2) Mencegah reason liar baru masuk dari UI / script lama
-- 3) Jalankan SETELAH 2026-06-19b selesai
-- ============================================================

ALTER TABLE inv_component_adjustment_line
  MODIFY spoil_reason_code ENUM(
    'expired',
    'temperature_abuse',
    'contamination',
    'improper_storage',
    'overstock',
    'other'
  ) NULL,
  MODIFY waste_reason_code ENUM(
    'cancel_order',
    'kitchen_error',
    'overproduction',
    'spillage',
    'expired_opened',
    'other'
  ) NULL,
  MODIFY adjustment_plus_reason_code ENUM(
    'opening_correction',
    'stock_found',
    'manual_reclass',
    'other'
  ) NULL,
  MODIFY adjustment_minus_reason_code ENUM(
    'counting_error',
    'system_mismatch',
    'unrecorded_usage',
    'process_loss',
    'theft_suspected',
    'other'
  ) NULL;

SHOW COLUMNS FROM inv_component_adjustment_line
WHERE Field IN (
  'spoil_reason_code',
  'waste_reason_code',
  'adjustment_plus_reason_code',
  'adjustment_minus_reason_code'
);
