SET NAMES utf8mb4;

-- ============================================================
-- CLEANUP TARGETED: division monthly / FIFO exact-identity gaps
-- Tanggal: 2026-06-08
--
-- Fokus:
-- 1. bootstrap exact open lot untuk monthly positif yang belum punya lot
-- 2. close stale open lot yang tidak punya monthly positif pasangan
-- 3. sync 1 open lot yang tertinggal dari aggregate fallback
--
-- Catatan:
-- - script ini sengaja targeted ke kandidat audit 2026-06-08
-- - tidak memaksa semua mismatch mengikuti SUM movement, karena ada
--   kasus reset/repair historis yang lebih tepat dibaca dari saldo
--   latest monthly live/rebuild
-- ============================================================

START TRANSACTION;


-- ============================================================
-- STEP 1. Bootstrap exact open lot dari latest monthly positif
--         yang belum punya pasangan lot exact.
--
-- Kandidat saat audit:
-- - BAR / OREO CRUMB / profile 06f5...
-- - BAR / SINGLE ORIGIN / profile 2a05...
-- - BAR / LEMON / profile a069...
-- ============================================================
INSERT INTO inv_material_fifo_lot (
  lot_no,
  location_scope,
  receipt_date,
  expiry_date,
  division_id,
  destination_type,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  qty_in,
  qty_out,
  qty_balance,
  unit_cost,
  source_table,
  source_id,
  source_line_id,
  receipt_id,
  receipt_line_id,
  parent_lot_id,
  status
)
SELECT
  CONCAT('LOTFIX-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', dms.id),
  'DIVISION',
  COALESCE(dms.last_movement_date, dms.month_key),
  dms.profile_expired_date,
  dms.division_id,
  dms.destination_type,
  dms.item_id,
  dms.material_id,
  dms.buy_uom_id,
  dms.content_uom_id,
  dms.profile_key,
  dms.closing_qty_content,
  0,
  dms.closing_qty_content,
  dms.avg_cost_per_content,
  'inv_division_monthly_stock',
  dms.id,
  NULL,
  NULL,
  NULL,
  NULL,
  'OPEN'
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key = dms.identity_key
AND latest.max_month = dms.month_key
WHERE dms.id IN (803, 1741, 1807)
  AND dms.closing_qty_content > 0.001
  AND NOT EXISTS (
    SELECT 1
    FROM inv_material_fifo_lot fl
    WHERE fl.location_scope = 'DIVISION'
      AND fl.status = 'OPEN'
      AND fl.division_id = dms.division_id
      AND fl.destination_type = dms.destination_type
      AND COALESCE(fl.item_id, 0) = COALESCE(dms.item_id, 0)
      AND COALESCE(fl.material_id, 0) = COALESCE(dms.material_id, 0)
      AND COALESCE(fl.buy_uom_id, 0) = COALESCE(dms.buy_uom_id, 0)
      AND fl.content_uom_id = dms.content_uom_id
      AND COALESCE(fl.profile_key, '') = COALESCE(dms.profile_key, '')
      AND fl.qty_balance > 0.001
  );

SELECT ROW_COUNT() AS step1_bootstrap_lot_rows;


-- ============================================================
-- STEP 2. Close stale open lot yang sudah tidak punya monthly
--         positif pasangan.
--
-- Kandidat saat audit:
-- - lot 12  : CARAMEL CRUMB BAR
-- - lot 114 : SEREH BAR
-- - lot 403 : KANI STICK KITCHEN
-- ============================================================
UPDATE inv_material_fifo_lot
SET
  qty_out = qty_in,
  qty_balance = 0,
  status = 'CLOSED'
WHERE id IN (12, 114, 403)
  AND status = 'OPEN'
  AND qty_balance > 0.001;

SELECT ROW_COUNT() AS step2_closed_stale_lots;


-- ============================================================
-- STEP 3. Sync lot AIR MINERAL GALON yang tertinggal 165 karena
--         aggregate fallback sudah mengurangi monthly tetapi lot
--         exact belum ikut turun.
--
-- Target:
-- - monthly latest id 1811 = 5685
-- - open lot id 839      = 5850 -> turunkan ke 5685
-- ============================================================
UPDATE inv_material_fifo_lot fl
INNER JOIN inv_division_monthly_stock dms ON dms.id = 1811
SET
  fl.qty_out = ROUND(fl.qty_in - dms.closing_qty_content, 4),
  fl.qty_balance = ROUND(dms.closing_qty_content, 4),
  fl.status = CASE WHEN dms.closing_qty_content > 0.001 THEN 'OPEN' ELSE 'CLOSED' END
WHERE fl.id = 839
  AND fl.location_scope = 'DIVISION'
  AND fl.status = 'OPEN';

SELECT ROW_COUNT() AS step3_synced_open_lot_rows;


-- ============================================================
-- STEP 4. Verifikasi targeted cleanup
-- ============================================================
SELECT
  COUNT(*) AS positive_monthly_without_open_lot_after
FROM (
  SELECT
    dms.division_id,
    dms.destination_type,
    dms.item_id,
    dms.material_id,
    dms.buy_uom_id,
    dms.content_uom_id,
    dms.profile_key,
    dms.closing_qty_content AS monthly_qty
  FROM inv_division_monthly_stock dms
  INNER JOIN (
    SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
    FROM inv_division_monthly_stock
    GROUP BY division_id, destination_type, identity_key
  ) latest ON
      latest.division_id = dms.division_id
  AND latest.destination_type = dms.destination_type
  AND latest.identity_key = dms.identity_key
  AND latest.max_month = dms.month_key
  WHERE dms.material_id IS NOT NULL
    AND dms.closing_qty_content > 0.001
) x
WHERE NOT EXISTS (
  SELECT 1
  FROM inv_material_fifo_lot fl
  WHERE fl.location_scope = 'DIVISION'
    AND fl.status = 'OPEN'
    AND fl.division_id = x.division_id
    AND fl.destination_type = x.destination_type
    AND COALESCE(fl.item_id, 0) = COALESCE(x.item_id, 0)
    AND COALESCE(fl.material_id, 0) = COALESCE(x.material_id, 0)
    AND COALESCE(fl.buy_uom_id, 0) = COALESCE(x.buy_uom_id, 0)
    AND fl.content_uom_id = x.content_uom_id
    AND COALESCE(fl.profile_key, '') = COALESCE(x.profile_key, '')
    AND fl.qty_balance > 0.001
);

SELECT
  COUNT(*) AS open_lots_without_positive_monthly_after
FROM inv_material_fifo_lot fl
WHERE fl.location_scope = 'DIVISION'
  AND fl.status = 'OPEN'
  AND fl.qty_balance > 0.001
  AND NOT EXISTS (
    SELECT 1
    FROM inv_division_monthly_stock dms
    INNER JOIN (
      SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
      FROM inv_division_monthly_stock
      GROUP BY division_id, destination_type, identity_key
    ) latest ON
        latest.division_id = dms.division_id
    AND latest.destination_type = dms.destination_type
    AND latest.identity_key = dms.identity_key
    AND latest.max_month = dms.month_key
    WHERE dms.division_id = fl.division_id
      AND dms.destination_type = fl.destination_type
      AND COALESCE(dms.item_id, 0) = COALESCE(fl.item_id, 0)
      AND COALESCE(dms.material_id, 0) = COALESCE(fl.material_id, 0)
      AND COALESCE(dms.buy_uom_id, 0) = COALESCE(fl.buy_uom_id, 0)
      AND dms.content_uom_id = fl.content_uom_id
      AND COALESCE(dms.profile_key, '') = COALESCE(fl.profile_key, '')
      AND dms.closing_qty_content > 0.001
  );

SELECT
  fl.id,
  fl.qty_in,
  fl.qty_out,
  fl.qty_balance,
  dms.closing_qty_content AS monthly_qty
FROM inv_material_fifo_lot fl
INNER JOIN inv_division_monthly_stock dms ON dms.id = 1811
WHERE fl.id = 839;

COMMIT;
