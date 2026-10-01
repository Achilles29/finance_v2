SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19i_backfill_active_pos_deficit_cogs_adjustments.sql
-- Tujuan :
-- 1) Membuat koreksi HPP yang belum tercatat untuk penyelesaian defisit
--    POS pada bulan aktif.
-- 2) Membetulkan nominal koreksi lama yang masih tidak sama dengan
--    settlement sumbernya, hanya bila belum pernah dibalik oleh refund/VOID.
-- 3) Menjaga order POS, stok, lot, movement, dan defisit tetap immutable.
--
-- Prasyarat:
-- - Jalankan 2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql.
-- - Deploy InventoryDeficitService + InventoryDeficitCogsService terbaru.
--
-- Batas aman:
-- - Hanya settlement pada BULAN AKTIF menurut CURDATE().
-- - Hanya defisit yang sumbernya pos_stock_commit.
-- - Settlement dengan biaya nol tidak disentuh, karena HPP aktual belum
--   diketahui secara sah.
-- - Koreksi yang sudah memiliki pembalikan refund/VOID tidak diubah oleh
--   script ini dan akan ditampilkan sebagai antrean review manual.
-- ============================================================

START TRANSACTION;

SET @active_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');
SET @next_month := DATE_ADD(@active_month, INTERVAL 1 MONTH);
SET @repair_tag := 'Backfill active POS deficit HPP correction 2026-08-19';

-- ------------------------------------------------------------
-- 1) Backfill settlement POS yang sebelumnya berhasil menutup defisit,
--    tetapi saat itu fondasi koreksi HPP belum ikut mencatat baris audit.
-- ------------------------------------------------------------
INSERT INTO inv_stock_deficit_cogs_adjustment (
  deficit_id,
  deficit_settlement_id,
  stock_domain,
  order_id,
  order_line_id,
  stock_commit_id,
  stock_commit_line_id,
  operational_division_id,
  sale_date,
  settlement_date,
  recognition_date,
  recognition_period_month,
  recognition_policy,
  qty_adjusted,
  provisional_unit_cost,
  provisional_amount,
  actual_unit_cost,
  actual_amount,
  variance_amount,
  status,
  notes,
  created_by,
  created_at,
  updated_at
)
SELECT
  x.deficit_id,
  x.settlement_id,
  x.stock_domain,
  x.order_id,
  x.order_line_id,
  x.stock_commit_id,
  x.stock_commit_line_id,
  x.operational_division_id,
  x.sale_date,
  x.settlement_date,
  x.recognition_date,
  DATE_FORMAT(x.recognition_date, '%Y-%m-01'),
  x.recognition_policy,
  x.qty_settled,
  x.provisional_unit_cost,
  ROUND(x.qty_settled * x.provisional_unit_cost, 2),
  x.actual_unit_cost,
  ROUND(x.qty_settled * x.actual_unit_cost, 2),
  ROUND(
    (x.qty_settled * x.actual_unit_cost)
    - (x.qty_settled * x.provisional_unit_cost),
    2
  ),
  'POSTED',
  CONCAT(@repair_tag, ' | settlement #', x.settlement_id),
  x.created_by,
  NOW(),
  NOW()
FROM (
  SELECT
    s.id AS settlement_id,
    d.id AS deficit_id,
    d.stock_domain,
    COALESCE(cl.order_id, c.order_id) AS order_id,
    cl.order_line_id,
    c.id AS stock_commit_id,
    cl.id AS stock_commit_line_id,
    cl.resolved_source_division_id AS operational_division_id,
    DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date)) AS sale_date,
    s.settlement_date,
    ROUND(s.qty_settled, 4) AS qty_settled,
    ROUND(COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0), 6) AS provisional_unit_cost,
    ROUND(s.unit_cost, 6) AS actual_unit_cost,
    s.created_by,
    CASE
      WHEN DATE_FORMAT(DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date)), '%Y-%m') = DATE_FORMAT(s.settlement_date, '%Y-%m')
       AND NOT EXISTS (
         SELECT 1
         FROM fin_period_close fp
         WHERE fp.period_start <= DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date))
           AND fp.period_end >= DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date))
           AND UPPER(COALESCE(fp.status, '')) = 'CLOSED'
       )
        THEN DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date))
      ELSE s.settlement_date
    END AS recognition_date,
    CASE
      WHEN DATE_FORMAT(DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date)), '%Y-%m') = DATE_FORMAT(s.settlement_date, '%Y-%m')
       AND NOT EXISTS (
         SELECT 1
         FROM fin_period_close fp
         WHERE fp.period_start <= DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date))
           AND fp.period_end >= DATE(COALESCE(o.paid_at, o.ordered_at, c.committed_at, c.created_at, d.deficit_date))
           AND UPPER(COALESCE(fp.status, '')) = 'CLOSED'
       )
        THEN 'SALE_MONTH_OPEN'
      ELSE 'SETTLEMENT_MONTH'
    END AS recognition_policy
  FROM inv_stock_deficit_settlement s
  INNER JOIN inv_stock_deficit d ON d.id = s.deficit_id
  INNER JOIN pos_stock_commit c
    ON c.id = d.source_id
   AND d.source_table = 'pos_stock_commit'
  INNER JOIN pos_stock_commit_line cl
    ON cl.id = d.source_line_id
   AND cl.commit_id = c.id
  LEFT JOIN pos_order o ON o.id = c.order_id
  LEFT JOIN inv_stock_deficit_cogs_adjustment existing
    ON existing.deficit_settlement_id = s.id
  WHERE s.settlement_date >= @active_month
    AND s.settlement_date < @next_month
    AND COALESCE(s.qty_settled, 0) > 0.0001
    AND COALESCE(s.unit_cost, 0) > 0.000001
    AND existing.id IS NULL
) x;

-- ------------------------------------------------------------
-- 2) Perbaiki baris koreksi lama yang belum pernah dibalik.
--    Jika sudah ada reversal, angka historis tidak boleh ditimpa otomatis.
-- ------------------------------------------------------------
UPDATE inv_stock_deficit_cogs_adjustment a
INNER JOIN inv_stock_deficit_settlement s ON s.id = a.deficit_settlement_id
INNER JOIN inv_stock_deficit d
  ON d.id = s.deficit_id
 AND d.source_table = 'pos_stock_commit'
INNER JOIN pos_stock_commit c ON c.id = d.source_id
INNER JOIN pos_stock_commit_line cl
  ON cl.id = d.source_line_id
 AND cl.commit_id = c.id
LEFT JOIN (
  SELECT cogs_adjustment_id, COUNT(*) AS reversal_count
  FROM inv_stock_deficit_cogs_reversal
  GROUP BY cogs_adjustment_id
) r ON r.cogs_adjustment_id = a.id
SET
  a.qty_adjusted = ROUND(s.qty_settled, 4),
  a.provisional_unit_cost = ROUND(COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0), 6),
  a.provisional_amount = ROUND(
    s.qty_settled * COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0),
    2
  ),
  a.actual_unit_cost = ROUND(s.unit_cost, 6),
  a.actual_amount = ROUND(s.qty_settled * s.unit_cost, 2),
  a.variance_amount = ROUND(
    (s.qty_settled * s.unit_cost)
    - (s.qty_settled * COALESCE(NULLIF(d.estimated_unit_cost, 0), cl.unit_cost_live, 0)),
    2
  ),
  a.notes = LEFT(CONCAT(
    COALESCE(a.notes, ''),
    CASE WHEN COALESCE(a.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | normalized to settlement #', s.id
  ), 255),
  a.updated_at = NOW()
WHERE s.settlement_date >= @active_month
  AND s.settlement_date < @next_month
  AND COALESCE(s.qty_settled, 0) > 0.0001
  AND COALESCE(s.unit_cost, 0) > 0.000001
  AND a.status = 'POSTED'
  AND COALESCE(r.reversal_count, 0) = 0
  AND (
    ABS(COALESCE(a.qty_adjusted, 0) - COALESCE(s.qty_settled, 0)) > 0.0001
    OR ABS(COALESCE(a.provisional_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.provisional_unit_cost, 0)) > 0.01
    OR ABS(COALESCE(a.actual_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.actual_unit_cost, 0)) > 0.01
    OR ABS(COALESCE(a.variance_amount, 0) - (COALESCE(a.actual_amount, 0) - COALESCE(a.provisional_amount, 0))) > 0.01
  );

COMMIT;

-- ------------------------------------------------------------
-- Post-check: semua angka berikut harus nol kecuali antrean manual
-- (baris yang telah memiliki reversal).
-- ------------------------------------------------------------
SELECT 'missing_active_pos_deficit_cogs' AS metric, COUNT(*) AS total
FROM inv_stock_deficit_settlement s
INNER JOIN inv_stock_deficit d ON d.id = s.deficit_id
LEFT JOIN inv_stock_deficit_cogs_adjustment a ON a.deficit_settlement_id = s.id
WHERE d.source_table = 'pos_stock_commit'
  AND s.settlement_date >= @active_month
  AND s.settlement_date < @next_month
  AND COALESCE(s.qty_settled, 0) > 0.0001
  AND COALESCE(s.unit_cost, 0) > 0.000001
  AND a.id IS NULL

UNION ALL

SELECT 'invalid_active_pos_deficit_cogs_without_reversal', COUNT(*)
FROM inv_stock_deficit_cogs_adjustment a
INNER JOIN inv_stock_deficit_settlement s ON s.id = a.deficit_settlement_id
INNER JOIN inv_stock_deficit d ON d.id = s.deficit_id
LEFT JOIN (
  SELECT cogs_adjustment_id, COUNT(*) AS reversal_count
  FROM inv_stock_deficit_cogs_reversal
  GROUP BY cogs_adjustment_id
) r ON r.cogs_adjustment_id = a.id
WHERE d.source_table = 'pos_stock_commit'
  AND s.settlement_date >= @active_month
  AND s.settlement_date < @next_month
  AND COALESCE(r.reversal_count, 0) = 0
  AND (
    ABS(COALESCE(a.qty_adjusted, 0) - COALESCE(s.qty_settled, 0)) > 0.0001
    OR ABS(COALESCE(a.provisional_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.provisional_unit_cost, 0)) > 0.01
    OR ABS(COALESCE(a.actual_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.actual_unit_cost, 0)) > 0.01
    OR ABS(COALESCE(a.variance_amount, 0) - (COALESCE(a.actual_amount, 0) - COALESCE(a.provisional_amount, 0))) > 0.01
  )

UNION ALL

SELECT 'active_pos_deficit_cogs_with_reversal_manual_review', COUNT(DISTINCT a.id)
FROM inv_stock_deficit_cogs_adjustment a
INNER JOIN inv_stock_deficit_settlement s ON s.id = a.deficit_settlement_id
INNER JOIN inv_stock_deficit d ON d.id = s.deficit_id
INNER JOIN inv_stock_deficit_cogs_reversal r ON r.cogs_adjustment_id = a.id
WHERE d.source_table = 'pos_stock_commit'
  AND s.settlement_date >= @active_month
  AND s.settlement_date < @next_month;

