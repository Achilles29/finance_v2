SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07j_cleanup_orphan_monthly_stock.sql
-- Tujuan :
-- Bersihkan baris inv_division_monthly_stock bulan ini yang:
-- 1. Tidak punya pasangan di movement log (orphan identity)
--    → sisa snapshot lama dari migrasi MATERIAL->ITEM yang identitynya sudah bergeser
-- 2. closing_qty = 0 dan tidak ada movement apapun
--    → snapshot kosong yang tidak perlu
--
-- Aman karena:
-- - Hanya menyentuh bulan berjalan (DATE_FORMAT(CURDATE(),'%Y-%m-01'))
-- - Row yang dihapus tidak punya movement backing = tidak ada data stok nyata
-- - Setelah dihapus, sistem akan buat ulang snapshot dari movement saat diakses
--
-- Urutan:
-- 1. Jalankan AUDIT dulu (SELECT)
-- 2. Cek hasilnya masuk akal
-- 3. Jalankan DELETE
-- 4. Verifikasi
-- ============================================================

SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- ============================================================
-- A. AUDIT: tampilkan row yang akan dihapus
-- ============================================================
SELECT
  d.name                              AS division_name,
  dms.destination_type,
  dms.month_key,
  COALESCE(mi.item_name,    '-')      AS item_name,
  COALESCE(mm.material_name,'-')      AS material_name,
  COALESCE(dms.profile_key, '(null)') AS profile_key,
  dms.closing_qty_content,
  dms.last_movement_date,
  dms.source_mode,
  CASE
    WHEN mv.cumulative_content IS NULL  THEN 'NO_MOVEMENT_MATCH'
    WHEN ABS(mv.cumulative_content) < 0.001 THEN 'ZERO_MOVEMENT'
    ELSE 'HAS_MOVEMENT'
  END AS movement_status
FROM inv_division_monthly_stock dms
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
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
LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_item                 mi ON mi.id  = dms.item_id
LEFT JOIN mst_material             mm ON mm.id  = dms.material_id
WHERE dms.month_key = @cur_month
  AND (
    mv.cumulative_content IS NULL                        -- tidak ada pasangan di movement log
    OR ABS(mv.cumulative_content) < 0.001               -- movement log sum = 0
  )
ORDER BY d.name, dms.destination_type, item_name;


-- ============================================================
-- B. RINGKASAN sebelum DELETE
-- ============================================================
SELECT
  COUNT(*) AS rows_to_delete,
  SUM(dms.closing_qty_content) AS total_closing_qty
FROM inv_division_monthly_stock dms
LEFT JOIN (
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
WHERE dms.month_key = @cur_month
  AND (mv.cumulative_content IS NULL OR ABS(mv.cumulative_content) < 0.001);


-- ============================================================
-- C. DELETE orphan rows
-- Jalankan setelah bagian A hasilnya masuk akal
-- ============================================================
DELETE dms FROM inv_division_monthly_stock dms
LEFT JOIN (
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
WHERE dms.month_key = @cur_month
  AND (mv.cumulative_content IS NULL OR ABS(mv.cumulative_content) < 0.001);


-- ============================================================
-- D. VERIFIKASI: jalankan ulang DIAG-1 dari 07i, harusnya = 0
-- ============================================================
SELECT COUNT(*) AS sisa_orphan_rows
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
WHERE mv.cumulative_content IS NULL;
