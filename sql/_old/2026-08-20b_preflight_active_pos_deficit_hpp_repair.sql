SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-20b_preflight_active_pos_deficit_hpp_repair.sql
-- Tujuan :
-- 1) Memeriksa kesiapan repair HPP defisit POS pada bulan aktif
-- 2) Menghitung kandidat yang akan disentuh SQL 2026-08-19h dan 2026-08-19i
-- 3) Menampilkan antrean yang tidak boleh diperbaiki otomatis karena
--    sudah memiliki pembalikan refund/VOID
--
-- Aman dijalankan berulang:
-- - hanya SELECT
-- - tidak mengubah POS, stok, lot, movement, defisit, HPP, atau kas
--
-- Prasyarat sebelum repair sebenarnya:
-- - 2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql
-- ============================================================

SET @schema_name := DATABASE();
SET @active_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');
SET @next_month := DATE_ADD(@active_month, INTERVAL 1 MONTH);

SELECT
  'movement_ref_type_supports_inventory_deficit' AS metric,
  CASE
    WHEN COALESCE((
      SELECT COLUMN_TYPE
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'pos_stock_commit_line'
        AND COLUMN_NAME = 'movement_ref_type'
      LIMIT 1
    ), '') LIKE '%''INVENTORY_DEFICIT''%'
      THEN 'READY'
    ELSE 'RUN_2026_08_19H'
  END AS result

UNION ALL

SELECT
  'cost_source_supports_deficit_pending',
  CASE
    WHEN COALESCE((
      SELECT COLUMN_TYPE
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'pos_stock_commit_line'
        AND COLUMN_NAME = 'cost_source'
      LIMIT 1
    ), '') LIKE '%''DEFICIT_PENDING''%'
      THEN 'READY'
    ELSE 'RUN_2026_08_19H'
  END;

SELECT 'full_deficit_hpp_repair_candidates' AS metric, COUNT(*) AS total
FROM inv_stock_deficit d
JOIN pos_stock_commit c
  ON c.id = d.source_id
 AND d.source_table = 'pos_stock_commit'
JOIN pos_stock_commit_line cl
  ON cl.id = d.source_line_id
 AND cl.commit_id = c.id
WHERE d.deficit_date >= @active_month
  AND d.deficit_date < @next_month
  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
  AND COALESCE(d.issued_qty, 0) <= 0.0001
  AND ABS(
    COALESCE(cl.total_cost_live, 0)
    - (
      COALESCE(cl.committed_qty, d.requested_qty, 0)
      * COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0)
    )
  ) > 0.01

UNION ALL

SELECT 'partial_deficit_hpp_repair_candidates', COUNT(*)
FROM inv_stock_deficit d
JOIN pos_stock_commit c
  ON c.id = d.source_id
 AND d.source_table = 'pos_stock_commit'
JOIN pos_stock_commit_line cl
  ON cl.id = d.source_line_id
 AND cl.commit_id = c.id
WHERE d.deficit_date >= @active_month
  AND d.deficit_date < @next_month
  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
  AND COALESCE(d.issued_qty, 0) > 0.0001
  AND COALESCE(d.requested_qty, 0) > COALESCE(d.issued_qty, 0) + 0.0001
  AND COALESCE(d.estimated_unit_cost, 0) > 0.000001
  AND COALESCE(cl.total_cost_live, 0) + 0.01
      < (COALESCE(d.requested_qty, 0) - COALESCE(d.issued_qty, 0))
        * COALESCE(d.estimated_unit_cost, 0)

UNION ALL

SELECT 'full_deficit_reference_repair_candidates', COUNT(*)
FROM inv_stock_deficit d
JOIN pos_stock_commit c
  ON c.id = d.source_id
 AND d.source_table = 'pos_stock_commit'
JOIN pos_stock_commit_line cl
  ON cl.id = d.source_line_id
 AND cl.commit_id = c.id
WHERE d.deficit_date >= @active_month
  AND d.deficit_date < @next_month
  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
  AND COALESCE(d.issued_qty, 0) <= 0.0001
  AND (
    COALESCE(cl.movement_ref_type, 'NONE') <> 'INVENTORY_DEFICIT'
    OR COALESCE(cl.movement_ref_id, 0) <> d.id
  );

SELECT 'missing_pos_deficit_cogs_candidates' AS metric, COUNT(*) AS total
FROM inv_stock_deficit_settlement s
JOIN inv_stock_deficit d
  ON d.id = s.deficit_id
 AND d.source_table = 'pos_stock_commit'
LEFT JOIN inv_stock_deficit_cogs_adjustment a
  ON a.deficit_settlement_id = s.id
WHERE s.settlement_date >= @active_month
  AND s.settlement_date < @next_month
  AND COALESCE(s.qty_settled, 0) > 0.0001
  AND COALESCE(s.unit_cost, 0) > 0.000001
  AND a.id IS NULL

UNION ALL

SELECT 'invalid_pos_deficit_cogs_without_reversal', COUNT(*)
FROM inv_stock_deficit_cogs_adjustment a
JOIN inv_stock_deficit_settlement s ON s.id = a.deficit_settlement_id
JOIN inv_stock_deficit d
  ON d.id = s.deficit_id
 AND d.source_table = 'pos_stock_commit'
LEFT JOIN (
  SELECT cogs_adjustment_id, COUNT(*) AS reversal_count
  FROM inv_stock_deficit_cogs_reversal
  GROUP BY cogs_adjustment_id
) r ON r.cogs_adjustment_id = a.id
WHERE s.settlement_date >= @active_month
  AND s.settlement_date < @next_month
  AND COALESCE(s.qty_settled, 0) > 0.0001
  AND COALESCE(s.unit_cost, 0) > 0.000001
  AND COALESCE(r.reversal_count, 0) = 0
  AND (
    ABS(COALESCE(a.qty_adjusted, 0) - COALESCE(s.qty_settled, 0)) > 0.0001
    OR ABS(COALESCE(a.provisional_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.provisional_unit_cost, 0)) > 0.01
    OR ABS(COALESCE(a.actual_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.actual_unit_cost, 0)) > 0.01
    OR ABS(COALESCE(a.variance_amount, 0) - (COALESCE(a.actual_amount, 0) - COALESCE(a.provisional_amount, 0))) > 0.01
  )

UNION ALL

SELECT 'pos_deficit_cogs_with_reversal_manual_review', COUNT(DISTINCT a.id)
FROM inv_stock_deficit_cogs_adjustment a
JOIN inv_stock_deficit_settlement s ON s.id = a.deficit_settlement_id
JOIN inv_stock_deficit d
  ON d.id = s.deficit_id
 AND d.source_table = 'pos_stock_commit'
JOIN inv_stock_deficit_cogs_reversal r ON r.cogs_adjustment_id = a.id
WHERE s.settlement_date >= @active_month
  AND s.settlement_date < @next_month;
