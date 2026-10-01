SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14d_seed_target_metric_estimated_profit.sql
-- Tujuan :
-- 1) Menambahkan metric target untuk profit estimasi dan margin profit estimasi
-- 2) Menyambungkan metric baru dengan engine target yang sudah membaca:
--    - omzet bersih
--    - HPP live
--    - belanja operasional / utilitas / lainnya
--    - estimasi gaji berjalan
--    - adjustment gudang / divisi / component
-- 3) Menyediakan indikator yang lebih mudah dipakai user saat membuat target bonus
-- ============================================================

START TRANSACTION;

INSERT INTO fin_metric_catalog (
  metric_code,
  metric_group,
  metric_label,
  metric_unit,
  metric_scope,
  comparator_hint,
  description,
  is_active
)
VALUES
  (
    'ESTIMATED_PROFIT_VALUE',
    'PROFITABILITY',
    'Profit Estimasi',
    'AMOUNT',
    'GLOBAL',
    'MIN',
    'Estimasi profit dari omzet bersih dikurangi HPP live, belanja operasional, estimasi gaji berjalan, dan adjustment stok.',
    1
  ),
  (
    'ESTIMATED_PROFIT_PERCENT',
    'PROFITABILITY',
    'Margin Profit Estimasi %',
    'PERCENT',
    'GLOBAL',
    'MIN',
    'Persentase profit estimasi terhadap omzet bersih periode berjalan.',
    1
  )
ON DUPLICATE KEY UPDATE
  metric_group = VALUES(metric_group),
  metric_label = VALUES(metric_label),
  metric_unit = VALUES(metric_unit),
  metric_scope = VALUES(metric_scope),
  comparator_hint = VALUES(comparator_hint),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT metric_code, metric_group, metric_label, metric_unit, metric_scope, comparator_hint
FROM fin_metric_catalog
WHERE metric_code IN ('ESTIMATED_PROFIT_VALUE', 'ESTIMATED_PROFIT_PERCENT')
ORDER BY metric_code;
