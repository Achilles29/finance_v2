SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07h_repair_division_monthly_stock_mismatch.sql
-- Tujuan :
-- 1) Audit selisih closing_qty di inv_division_monthly_stock (bulan terakhir per identity)
--    vs kumulatif inv_stock_movement_log (sumber kebenaran)
-- 2) Repair closing_qty pada baris bulan terbaru yang tidak sesuai
--
-- Struktur tabel:
-- - inv_division_monthly_stock: 1 baris per (month_key, division_id, destination_type, identity_key)
-- - identity_key: hash dari (item_id, material_id, buy_uom_id, content_uom_id, profile_key)
-- - closing_qty_content: saldo akhir bulan = kumulatif semua movement s.d. baris ini
--
-- Urutan aman:
-- a) Jalankan bagian A (audit SELECT saja)
-- b) Cek hasilnya masuk akal
-- c) Jalankan bagian C (UPDATE repair)
-- d) Verifikasi dengan bagian D
-- ============================================================


-- ============================================================
-- A. AUDIT: identity dengan selisih > 0.001 di bulan terbaru
-- ============================================================
SELECT
  d.name                              AS division_name,
  dms.destination_type,
  dms.month_key,
  COALESCE(mi.item_name,    '-')      AS item_name,
  COALESCE(mm.material_name,'-')      AS material_name,
  dms.profile_key,
  dms.last_movement_date,
  dms.closing_qty_content             AS snapshot_qty,
  mv.cumulative_content               AS movement_total,
  ROUND(dms.closing_qty_content - mv.cumulative_content, 4) AS selisih,
  CASE
    WHEN dms.closing_qty_content > mv.cumulative_content THEN 'SNAPSHOT_LEBIH'
    ELSE 'MOVEMENT_LEBIH'
  END                                 AS arah_selisih
FROM inv_division_monthly_stock dms

-- Hanya baris bulan terbaru per identity
INNER JOIN (
  SELECT
    division_id,
    destination_type,
    identity_key,
    MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key

-- Kumulatif movement seluruh waktu per identity
INNER JOIN (
  SELECT
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
    mv.division_id      <=> dms.division_id
AND mv.destination_type <=> dms.destination_type
AND mv.item_id          <=> dms.item_id
AND mv.material_id      <=> dms.material_id
AND mv.buy_uom_id       <=> dms.buy_uom_id
AND mv.content_uom_id    = dms.content_uom_id
AND mv.profile_key      <=> dms.profile_key

LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_item                 mi ON mi.id  = dms.item_id
LEFT JOIN mst_material             mm ON mm.id  = dms.material_id

WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001
ORDER BY ABS(dms.closing_qty_content - mv.cumulative_content) DESC;


-- ============================================================
-- B. RINGKASAN: jumlah identity mismatch dan total selisih
-- ============================================================
SELECT
  COUNT(*)                                                              AS identity_mismatch_count,
  SUM(ABS(ROUND(dms.closing_qty_content - mv.cumulative_content, 4))) AS total_selisih_content
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
    division_id, destination_type, item_id, material_id,
    buy_uom_id, content_uom_id, profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
    mv.division_id      <=> dms.division_id
AND mv.destination_type <=> dms.destination_type
AND mv.item_id          <=> dms.item_id
AND mv.material_id      <=> dms.material_id
AND mv.buy_uom_id       <=> dms.buy_uom_id
AND mv.content_uom_id    = dms.content_uom_id
AND mv.profile_key      <=> dms.profile_key
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- C. REPAIR: update closing_qty bulan terbaru ke kumulatif movement
--
-- JALANKAN HANYA SETELAH bagian A menunjukkan data masuk akal
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
    division_id, destination_type, item_id, material_id,
    buy_uom_id, content_uom_id, profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
    mv.division_id      <=> dms.division_id
AND mv.destination_type <=> dms.destination_type
AND mv.item_id          <=> dms.item_id
AND mv.material_id      <=> dms.material_id
AND mv.buy_uom_id       <=> dms.buy_uom_id
AND mv.content_uom_id    = dms.content_uom_id
AND mv.profile_key      <=> dms.profile_key
SET
  dms.closing_qty_content = ROUND(mv.cumulative_content, 4),
  dms.closing_qty_buy     = ROUND(mv.cumulative_buy, 4),
  dms.total_value         = ROUND(mv.cumulative_content * dms.avg_cost_per_content, 2)
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- D. VERIFIKASI: harusnya 0 sisa mismatch setelah repair
-- ============================================================
SELECT
  COUNT(*) AS sisa_mismatch
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
    division_id, destination_type, item_id, material_id,
    buy_uom_id, content_uom_id, profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
    mv.division_id      <=> dms.division_id
AND mv.destination_type <=> dms.destination_type
AND mv.item_id          <=> dms.item_id
AND mv.material_id      <=> dms.material_id
AND mv.buy_uom_id       <=> dms.buy_uom_id
AND mv.content_uom_id    = dms.content_uom_id
AND mv.profile_key      <=> dms.profile_key
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;
