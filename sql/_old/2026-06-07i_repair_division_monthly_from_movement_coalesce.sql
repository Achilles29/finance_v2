SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07i_repair_division_monthly_from_movement_coalesce.sql
-- Tujuan :
-- Repair closing_qty di inv_division_monthly_stock menggunakan
-- kumulatif inv_stock_movement_log sebagai sumber kebenaran.
--
-- Perbedaan dengan 07h:
-- - Pakai COALESCE pada semua kolom nullable di movement log
--   (item_id, material_id, buy_uom_id, profile_key) agar join cocok
-- - Kolom non-nullable (destination_type, content_uom_id) join langsung
--
-- Urutan:
-- 1. Jalankan bagian DIAG dulu — cek ada/tidaknya join gap
-- 2. Jalankan bagian A (audit SELECT)
-- 3. Cek hasilnya, lalu jalankan bagian C (UPDATE)
-- 4. Verifikasi dengan bagian D
-- ============================================================


-- ============================================================
-- DIAG-1: cek berapa row monthly stock yang TIDAK punya pasangan
--         di movement log sama sekali (join miss total)
-- ============================================================
SELECT
  COUNT(*) AS monthly_rows_no_movement_match,
  SUM(dms.closing_qty_content) AS total_closing_no_match
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    COALESCE(item_id,    0)  AS item_id,
    COALESCE(material_id,0)  AS material_id,
    COALESCE(buy_uom_id, 0)  AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
WHERE mv.cumulative_content IS NULL;


-- ============================================================
-- DIAG-2: cek berapa row movement log yang destination_type = NULL
--         (bisa jadi tidak masuk join)
-- ============================================================
SELECT
  movement_scope,
  destination_type,
  COUNT(*) AS movement_rows
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
GROUP BY movement_scope, destination_type
ORDER BY destination_type IS NULL DESC, destination_type;


-- ============================================================
-- A. AUDIT: identity dengan selisih > 0.001 (pakai COALESCE join)
-- ============================================================
SELECT
  d.name                              AS division_name,
  dms.destination_type,
  dms.month_key,
  COALESCE(mi.item_name,    '-')      AS item_name,
  COALESCE(mm.material_name,'-')      AS material_name,
  COALESCE(dms.profile_key, '(null)') AS profile_key,
  dms.last_movement_date,
  dms.closing_qty_content             AS snapshot_qty,
  mv.cumulative_content               AS movement_total,
  ROUND(dms.closing_qty_content - mv.cumulative_content, 4) AS selisih,
  CASE
    WHEN dms.closing_qty_content > mv.cumulative_content THEN 'SNAPSHOT_LEBIH'
    ELSE 'MOVEMENT_LEBIH'
  END                                 AS arah_selisih
FROM inv_division_monthly_stock dms
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
    division_id,
    destination_type,
    COALESCE(item_id,    0)  AS item_id,
    COALESCE(material_id,0)  AS material_id,
    COALESCE(buy_uom_id, 0)  AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
LEFT JOIN mst_operational_division d  ON d.id = dms.division_id
LEFT JOIN mst_item                 mi ON mi.id = dms.item_id
LEFT JOIN mst_material             mm ON mm.id = dms.material_id
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001
ORDER BY ABS(dms.closing_qty_content - mv.cumulative_content) DESC;


-- ============================================================
-- B. RINGKASAN
-- ============================================================
SELECT
  COUNT(*) AS identity_mismatch_count,
  SUM(ABS(ROUND(dms.closing_qty_content - mv.cumulative_content, 4))) AS total_selisih
FROM inv_division_monthly_stock dms
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
    division_id, destination_type,
    COALESCE(item_id,    0) AS item_id,
    COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- C. REPAIR
-- Jalankan setelah bagian A hasilnya masuk akal
-- ============================================================
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
    division_id, destination_type,
    COALESCE(item_id,    0) AS item_id,
    COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
SET
  dms.closing_qty_content = ROUND(mv.cumulative_content, 4),
  dms.closing_qty_buy     = ROUND(mv.cumulative_buy,     4),
  dms.total_value         = ROUND(mv.cumulative_content * dms.avg_cost_per_content, 2)
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- D. VERIFIKASI: harusnya 0
-- ============================================================
SELECT COUNT(*) AS sisa_mismatch
FROM inv_division_monthly_stock dms
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
    division_id, destination_type,
    COALESCE(item_id,    0) AS item_id,
    COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;
