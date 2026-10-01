SET NAMES utf8mb4;

-- ============================================================
-- FIX: Semua mismatch di inv_division_monthly_stock
-- vs inv_stock_movement_log
-- Tanggal: 2026-06-07
-- PRASYARAT: Jalankan 07t dulu, pastikan output sudah jelas.
-- ============================================================
--
-- Urutan eksekusi:
--   1. Jalankan STEP 1 (MISMATCH case)
--   2. Jalankan STEP 2 (NO_EXACT_MATCH case)
--   3. Jalankan STEP 3 (FIFO lot — hanya jika STEP 3 diagnosis perlu)
--   4. Jalankan STEP 4 (verifikasi akhir)
--
-- Setiap step pakai START TRANSACTION + ROLLBACK by default.
-- Ganti ROLLBACK dengan COMMIT jika ROW_COUNT dan verifikasi masuk akal.
-- ============================================================


-- ============================================================
-- STEP 1: Fix MISMATCH — update closing_qty monthly ke nilai
--         cumulative movement log (untuk row yang punya exact match
--         tapi nilainya berbeda)
-- ============================================================

START TRANSACTION;

UPDATE inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
INNER JOIN (
  SELECT
    ml.division_id,
    ml.destination_type,
    COALESCE(ml.item_id,     0) AS item_id,
    COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id,  0) AS buy_uom_id,
    ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content,
    ROUND(SUM(ml.qty_buy_delta),     4) AS cumulative_buy
  FROM inv_stock_movement_log ml
  WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0), COALESCE(ml.material_id, 0),
    COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv ON
    mv.division_id      = dms.division_id
AND mv.destination_type  = dms.destination_type
AND mv.item_id           = COALESCE(dms.item_id,    0)
AND mv.material_id       = COALESCE(dms.material_id,0)
AND mv.buy_uom_id        = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id    = dms.content_uom_id
AND mv.profile_key       = COALESCE(dms.profile_key,'')
SET
  dms.closing_qty_content = mv.cumulative_content,
  dms.closing_qty_buy     = mv.cumulative_buy,
  dms.total_value         = ROUND(mv.cumulative_content * dms.avg_cost_per_content, 2),
  dms.notes               = CONCAT(COALESCE(dms.notes, ''), ' | fix-mismatch-07u')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;

SELECT ROW_COUNT() AS step1_rows_updated_mismatch;

-- Cek sisa mismatch setelah step ini (seharusnya tipe MISMATCH = 0)
SELECT
  CASE WHEN mv_exact.cumulative_content IS NULL THEN 'NO_EXACT_MATCH' ELSE 'MISMATCH' END AS type,
  COUNT(*) AS sisa
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock GROUP BY division_id, destination_type, identity_key
) latest ON latest.division_id = dms.division_id AND latest.destination_type = dms.destination_type
  AND latest.identity_key = dms.identity_key AND latest.max_month = dms.month_key
LEFT JOIN (
  SELECT ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0) AS item_id, COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id, 0) AS buy_uom_id, ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log ml WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type, COALESCE(ml.item_id, 0),
    COALESCE(ml.material_id, 0), COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv_exact ON mv_exact.division_id = dms.division_id AND mv_exact.destination_type = dms.destination_type
  AND mv_exact.item_id = COALESCE(dms.item_id, 0) AND mv_exact.material_id = COALESCE(dms.material_id, 0)
  AND mv_exact.buy_uom_id = COALESCE(dms.buy_uom_id, 0) AND mv_exact.content_uom_id = dms.content_uom_id
  AND mv_exact.profile_key = COALESCE(dms.profile_key, '')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) > 0.001
GROUP BY type;

-- Kalau step1_rows_updated masuk akal dan tipe MISMATCH = 0:
ROLLBACK;
-- Ganti dengan COMMIT jika hasilnya sesuai


-- ============================================================
-- STEP 2: Fix NO_EXACT_MATCH — zero-kan closing_qty untuk row
--         yang punya balance > 0 tapi tidak ada satupun
--         movement log dengan identity persis sama
--
-- Aman: baris tetap ada, hanya closing_qty diset 0
-- Alasan: movement log adalah truth; row tanpa exact match berarti
--         balancenya phantom atau sudah dipindah ke identity lain
-- ============================================================

START TRANSACTION;

UPDATE inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
SET
  dms.closing_qty_content = 0,
  dms.closing_qty_buy     = 0,
  dms.total_value         = 0,
  dms.notes               = CONCAT(COALESCE(dms.notes, ''), ' | zeroed-no-exact-match-07u')
WHERE dms.material_id IS NOT NULL
  AND dms.closing_qty_content > 0.001
  AND NOT EXISTS (
    SELECT 1
    FROM inv_stock_movement_log ml
    WHERE ml.movement_scope              = 'DIVISION'
      AND ml.division_id                 = dms.division_id
      AND ml.destination_type            = dms.destination_type
      AND COALESCE(ml.item_id,     0)    = COALESCE(dms.item_id,    0)
      AND COALESCE(ml.material_id, 0)    = COALESCE(dms.material_id,0)
      AND COALESCE(ml.buy_uom_id,  0)    = COALESCE(dms.buy_uom_id, 0)
      AND ml.content_uom_id              = dms.content_uom_id
      AND COALESCE(ml.profile_key, '')   = COALESCE(dms.profile_key,'')
  );

SELECT ROW_COUNT() AS step2_rows_zeroed_no_exact_match;

-- Cek sisa mismatch setelah step 1+2 (seharusnya 0)
SELECT COUNT(*) AS sisa_mismatch_total
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock GROUP BY division_id, destination_type, identity_key
) latest ON latest.division_id = dms.division_id AND latest.destination_type = dms.destination_type
  AND latest.identity_key = dms.identity_key AND latest.max_month = dms.month_key
LEFT JOIN (
  SELECT ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0) AS item_id, COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id, 0) AS buy_uom_id, ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log ml WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type, COALESCE(ml.item_id, 0),
    COALESCE(ml.material_id, 0), COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv_exact ON mv_exact.division_id = dms.division_id AND mv_exact.destination_type = dms.destination_type
  AND mv_exact.item_id = COALESCE(dms.item_id, 0) AND mv_exact.material_id = COALESCE(dms.material_id, 0)
  AND mv_exact.buy_uom_id = COALESCE(dms.buy_uom_id, 0) AND mv_exact.content_uom_id = dms.content_uom_id
  AND mv_exact.profile_key = COALESCE(dms.profile_key, '')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) > 0.001;

ROLLBACK;
-- Ganti dengan COMMIT jika sisa_mismatch_total = 0 dan step2_rows_zeroed masuk akal


-- ============================================================
-- STEP 3: Fix FIFO lot stale — close lot yang qty_balance > 0
--         tapi monthly stock untuk identity yang sama sudah 0
--         (akibat step 1 mengubah ke 0, atau step 2 zero-kan)
--
-- Tujuan: pastikan adjust minus berikutnya tidak temukan phantom lot
-- ============================================================

START TRANSACTION;

UPDATE inv_material_fifo_lot fl
SET
  fl.qty_balance = 0,
  fl.status      = 'CLOSED',
  fl.notes       = CONCAT(COALESCE(fl.notes, ''), ' | close-stale-lot-07u')
WHERE fl.location_scope = 'DIVISION'
  AND fl.status = 'OPEN'
  AND fl.qty_balance > 0.001
  AND NOT EXISTS (
    -- Monthly stock untuk identity ini punya closing > 0
    SELECT 1
    FROM inv_division_monthly_stock dms
    INNER JOIN (
      SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
      FROM inv_division_monthly_stock
      GROUP BY division_id, destination_type, identity_key
    ) latest ON latest.division_id = dms.division_id AND latest.destination_type = dms.destination_type
      AND latest.identity_key = dms.identity_key AND latest.max_month = dms.month_key
    WHERE dms.division_id                = fl.division_id
      AND dms.destination_type           = fl.destination_type
      AND COALESCE(dms.item_id,     0)   = COALESCE(fl.item_id,     0)
      AND COALESCE(dms.material_id, 0)   = COALESCE(fl.material_id, 0)
      AND COALESCE(dms.buy_uom_id,  0)   = COALESCE(fl.buy_uom_id,  0)
      AND dms.content_uom_id             = fl.content_uom_id
      AND COALESCE(dms.profile_key, '')  = COALESCE(fl.profile_key, '')
      AND dms.closing_qty_content > 0.001
  );

SELECT ROW_COUNT() AS step3_fifo_lots_closed;

-- Verifikasi: cek apakah masih ada lot dengan balance > 0 yang monthly = 0
SELECT COUNT(*) AS sisa_lot_tanpa_monthly_balance
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
    ) latest ON latest.division_id = dms.division_id AND latest.destination_type = dms.destination_type
      AND latest.identity_key = dms.identity_key AND latest.max_month = dms.month_key
    WHERE dms.division_id = fl.division_id AND dms.destination_type = fl.destination_type
      AND COALESCE(dms.item_id, 0) = COALESCE(fl.item_id, 0)
      AND COALESCE(dms.material_id, 0) = COALESCE(fl.material_id, 0)
      AND COALESCE(dms.buy_uom_id, 0) = COALESCE(fl.buy_uom_id, 0)
      AND dms.content_uom_id = fl.content_uom_id
      AND COALESCE(dms.profile_key, '') = COALESCE(fl.profile_key, '')
      AND dms.closing_qty_content > 0.001
  );

ROLLBACK;
-- Ganti dengan COMMIT jika step3_fifo_lots_closed masuk akal


-- ============================================================
-- STEP 4: Verifikasi akhir — jalankan setelah semua COMMIT
--         Seharusnya 0 mismatch tersisa
-- ============================================================
SELECT
  COUNT(*)                           AS total_mismatch_tersisa,
  SUM(ABS(ROUND(
    dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0), 4
  )))                                AS total_selisih_tersisa
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON latest.division_id = dms.division_id AND latest.destination_type = dms.destination_type
  AND latest.identity_key = dms.identity_key AND latest.max_month = dms.month_key
LEFT JOIN (
  SELECT ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0) AS item_id, COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id, 0) AS buy_uom_id, ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log ml WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type, COALESCE(ml.item_id, 0),
    COALESCE(ml.material_id, 0), COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv_exact ON mv_exact.division_id = dms.division_id AND mv_exact.destination_type = dms.destination_type
  AND mv_exact.item_id = COALESCE(dms.item_id, 0) AND mv_exact.material_id = COALESCE(dms.material_id, 0)
  AND mv_exact.buy_uom_id = COALESCE(dms.buy_uom_id, 0) AND mv_exact.content_uom_id = dms.content_uom_id
  AND mv_exact.profile_key = COALESCE(dms.profile_key, '')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) > 0.001;
