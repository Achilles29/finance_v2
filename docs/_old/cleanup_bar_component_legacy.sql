-- ============================================================
-- Cleanup: Hapus data stok legacy TING TING CRUMBLE dan
-- BREAD TOAST dari divisi BAR (inv_component_monthly_stock,
-- inv_component_movement_log, inv_component_lot,
-- inv_component_lot_issue_log, inv_component_lot_issue_line,
-- inv_component_stock_opname)
--
-- Jalankan SATU PERSATU, verifikasi SELECT dulu sebelum DELETE.
-- Backup tabel dulu jika perlu.
-- ============================================================

-- ── Identifikasi divisi BAR ──────────────────────────────────
-- Cek ID divisi BAR
SELECT id, code, name FROM mst_operational_division WHERE code = 'BAR';

-- ── Identifikasi component TING TING CRUMBLE & BREAD TOAST ──
SELECT id, component_code, component_name, operational_division_id
FROM mst_component
WHERE component_name IN ('TING TING CRUMBLE', 'BREAD TOAST')
   OR component_code IN ('TING TING CRUMBLE', 'BREAD TOAST');

-- ── Ganti @BAR_DIV_ID dan @COMP_IDS sesuai hasil query di atas ──
SET @BAR_DIV_ID   = (SELECT id FROM mst_operational_division WHERE code = 'BAR' LIMIT 1);
SET @TING_ID      = (SELECT id FROM mst_component WHERE component_name = 'TING TING CRUMBLE' LIMIT 1);
SET @BREAD_ID     = (SELECT id FROM mst_component WHERE component_name = 'BREAD TOAST' LIMIT 1);

-- ============================================================
-- 1. PREVIEW: monthly stock yang akan dihapus
-- ============================================================
SELECT ms.id, ms.month_key, ms.location_type, ms.division_id,
       c.component_name, ms.uom_id, ms.closing_qty, ms.avg_cost
FROM inv_component_monthly_stock ms
JOIN mst_component c ON c.id = ms.component_id
WHERE ms.division_id = @BAR_DIV_ID
  AND ms.component_id IN (@TING_ID, @BREAD_ID);

-- ── DELETE monthly stock ─────────────────────────────────────
DELETE FROM inv_component_monthly_stock
WHERE division_id = @BAR_DIV_ID
  AND component_id IN (@TING_ID, @BREAD_ID);

-- ============================================================
-- 2. PREVIEW: movement log yang akan dihapus
-- ============================================================
SELECT ml.id, ml.movement_date, ml.location_type, ml.division_id,
       c.component_name, ml.movement_type, ml.qty_in, ml.qty_out, ml.unit_cost
FROM inv_component_movement_log ml
JOIN mst_component c ON c.id = ml.component_id
WHERE ml.division_id = @BAR_DIV_ID
  AND ml.component_id IN (@TING_ID, @BREAD_ID)
ORDER BY ml.movement_date;

-- ── DELETE movement log ──────────────────────────────────────
DELETE FROM inv_component_movement_log
WHERE division_id = @BAR_DIV_ID
  AND component_id IN (@TING_ID, @BREAD_ID);

-- ============================================================
-- 3. PREVIEW: lot issue lines yang akan dihapus
--    (harus dihapus sebelum issue log dan lot)
-- ============================================================
SELECT il.id, il.issue_id, il.lot_id, il.qty_issued
FROM inv_component_lot_issue_line il
JOIN inv_component_lot l ON l.id = il.lot_id
WHERE l.division_id = @BAR_DIV_ID
  AND l.component_id IN (@TING_ID, @BREAD_ID);

-- ── DELETE lot issue lines ───────────────────────────────────
DELETE il FROM inv_component_lot_issue_line il
JOIN inv_component_lot l ON l.id = il.lot_id
WHERE l.division_id = @BAR_DIV_ID
  AND l.component_id IN (@TING_ID, @BREAD_ID);

-- ============================================================
-- 4. PREVIEW: lot issue log yang akan dihapus
-- ============================================================
SELECT log.id, log.issue_date, log.location_type, log.division_id,
       c.component_name, log.qty_out
FROM inv_component_lot_issue_log log
JOIN mst_component c ON c.id = log.component_id
WHERE log.division_id = @BAR_DIV_ID
  AND log.component_id IN (@TING_ID, @BREAD_ID);

-- ── DELETE lot issue log ─────────────────────────────────────
DELETE FROM inv_component_lot_issue_log
WHERE division_id = @BAR_DIV_ID
  AND component_id IN (@TING_ID, @BREAD_ID);

-- ============================================================
-- 5. PREVIEW: lot FIFO yang akan dihapus
-- ============================================================
SELECT l.id, l.location_type, l.division_id, c.component_name,
       l.lot_no, l.qty_in_total, l.qty_out_total, l.qty_balance, l.status
FROM inv_component_lot l
JOIN mst_component c ON c.id = l.component_id
WHERE l.division_id = @BAR_DIV_ID
  AND l.component_id IN (@TING_ID, @BREAD_ID);

-- ── DELETE lot FIFO ──────────────────────────────────────────
DELETE FROM inv_component_lot
WHERE division_id = @BAR_DIV_ID
  AND component_id IN (@TING_ID, @BREAD_ID);

-- ============================================================
-- 6. PREVIEW: opname record yang akan dihapus (jika tabel ada)
-- ============================================================
SELECT * FROM inv_component_stock_opname
WHERE division_id = @BAR_DIV_ID
  AND component_id IN (@TING_ID, @BREAD_ID);

-- ── DELETE opname ────────────────────────────────────────────
DELETE FROM inv_component_stock_opname
WHERE division_id = @BAR_DIV_ID
  AND component_id IN (@TING_ID, @BREAD_ID);

-- ============================================================
-- 7. VERIFIKASI: pastikan semua sudah bersih
-- ============================================================
SELECT 'monthly_stock' AS tbl, COUNT(*) AS sisa
FROM inv_component_monthly_stock
WHERE division_id = @BAR_DIV_ID AND component_id IN (@TING_ID, @BREAD_ID)
UNION ALL
SELECT 'movement_log', COUNT(*)
FROM inv_component_movement_log
WHERE division_id = @BAR_DIV_ID AND component_id IN (@TING_ID, @BREAD_ID)
UNION ALL
SELECT 'lot', COUNT(*)
FROM inv_component_lot
WHERE division_id = @BAR_DIV_ID AND component_id IN (@TING_ID, @BREAD_ID)
UNION ALL
SELECT 'lot_issue_log', COUNT(*)
FROM inv_component_lot_issue_log
WHERE division_id = @BAR_DIV_ID AND component_id IN (@TING_ID, @BREAD_ID);
-- Semua harus 0
