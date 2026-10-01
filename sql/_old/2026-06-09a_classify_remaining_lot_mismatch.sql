SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-09a_classify_remaining_lot_mismatch.sql
-- Tujuan : Klasifikasi sisa mismatch lot vs monthly setelah repair _y dan _z
--
-- Kategori:
--   EXPECTED_DEBT  : monthly negatif, lot = 0 → ini by design (POS boleh minus)
--                    Tidak perlu repair, ini hutang produksi normal
--   STILL_EXCESS   : lot LEBIH dari monthly (positif), belum berhasil di-trim
--                    Ini perlu diinvestigasi lebih lanjut
--   STILL_SHORT    : lot KURANG dari monthly (positif), belum berhasil di-tambah
--                    Ini seharusnya sudah tertangani _y, aneh kalau masih ada
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_cls_monthly;
CREATE TEMPORARY TABLE tmp_cls_monthly AS
SELECT
  s.division_id,
  s.destination_type,
  s.item_id,
  COALESCE(s.material_id, mi.material_id)              AS material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_key,
  ROUND(SUM(COALESCE(s.closing_qty_content, 0)), 4)    AS closing_content
FROM inv_division_monthly_stock s
LEFT JOIN mst_item mi ON mi.id = s.item_id
WHERE COALESCE(s.material_id, mi.material_id, 0) > 0
GROUP BY
  s.division_id, s.destination_type, s.item_id,
  COALESCE(s.material_id, mi.material_id),
  s.buy_uom_id, s.content_uom_id, s.profile_key;

ALTER TABLE tmp_cls_monthly
  ADD KEY idx_cm (division_id, destination_type, item_id, material_id,
                  buy_uom_id, content_uom_id, profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_cls_lot;
CREATE TEMPORARY TABLE tmp_cls_lot AS
SELECT
  l.division_id,
  l.destination_type,
  l.item_id,
  COALESCE(l.material_id, mi.material_id)              AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
  ROUND(SUM(l.qty_balance), 4)                         AS lot_balance
FROM inv_material_fifo_lot l
LEFT JOIN mst_item mi ON mi.id = l.item_id
WHERE l.location_scope = 'DIVISION'
  AND COALESCE(l.material_id, mi.material_id, 0) > 0
GROUP BY
  l.division_id, l.destination_type, l.item_id,
  COALESCE(l.material_id, mi.material_id),
  l.buy_uom_id, l.content_uom_id, l.profile_key;

ALTER TABLE tmp_cls_lot
  ADD KEY idx_cl (division_id, destination_type, item_id, material_id,
                  buy_uom_id, content_uom_id, profile_key(32));

-- ---- Klasifikasi ----
DROP TEMPORARY TABLE IF EXISTS tmp_cls_result;
CREATE TEMPORARY TABLE tmp_cls_result AS
SELECT
  m.division_id,
  m.destination_type,
  m.item_id,
  m.material_id,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_key,
  m.closing_content,
  COALESCE(l.lot_balance, 0) AS lot_balance,
  ROUND(COALESCE(l.lot_balance, 0) - m.closing_content, 4) AS diff,  -- + = lot lebih, - = lot kurang
  CASE
    -- Lot OK
    WHEN ABS(COALESCE(l.lot_balance, 0) - m.closing_content) < 0.0001
      THEN 'OK'
    -- Monthly negatif, lot sudah 0 → by design (POS overconsumption)
    WHEN m.closing_content < 0 AND COALESCE(l.lot_balance, 0) < 0.0001
      THEN 'EXPECTED_DEBT'
    -- Monthly negatif tapi lot masih ada → perlu dikosongkan
    WHEN m.closing_content < 0 AND COALESCE(l.lot_balance, 0) > 0.0001
      THEN 'STILL_EXCESS'
    -- Monthly positif, lot kurang → seharusnya sudah di-fix _y
    WHEN (m.closing_content - COALESCE(l.lot_balance, 0)) > 0.0001
      THEN 'STILL_SHORT'
    -- Monthly positif, lot lebih → seharusnya sudah di-fix _z
    WHEN (COALESCE(l.lot_balance, 0) - m.closing_content) > 0.0001
      THEN 'STILL_EXCESS'
    ELSE 'OK'
  END AS category
FROM tmp_cls_monthly m
LEFT JOIN tmp_cls_lot l
  ON l.division_id      = m.division_id
 AND l.destination_type <=> m.destination_type
 AND l.item_id         <=> m.item_id
 AND l.material_id     <=> m.material_id
 AND l.buy_uom_id      <=> m.buy_uom_id
 AND l.content_uom_id  <=> m.content_uom_id
 AND l.profile_key     <=> m.profile_key;

-- ---- Summary ----
SELECT
  category,
  COUNT(*)                              AS identity_groups,
  ROUND(SUM(ABS(diff)), 2)             AS total_abs_diff,
  ROUND(SUM(closing_content), 2)       AS total_closing_content,
  ROUND(SUM(lot_balance), 2)           AS total_lot_balance
FROM tmp_cls_result
GROUP BY category
ORDER BY FIELD(category, 'OK', 'EXPECTED_DEBT', 'STILL_SHORT', 'STILL_EXCESS');

-- ---- Detail: hanya yang MASIH bermasalah (bukan OK dan bukan EXPECTED_DEBT) ----
SELECT
  r.category,
  d.code                               AS division_code,
  r.destination_type,
  i.item_code, i.item_name,
  mat.material_code, mat.material_name,
  bu.code AS buy_uom, cu.code AS content_uom,
  r.closing_content,
  r.lot_balance,
  r.diff
FROM tmp_cls_result r
LEFT JOIN mst_operational_division d  ON d.id  = r.division_id
LEFT JOIN mst_item                 i  ON i.id   = r.item_id
LEFT JOIN mst_material           mat  ON mat.id = r.material_id
LEFT JOIN mst_uom                 bu  ON bu.id  = r.buy_uom_id
LEFT JOIN mst_uom                 cu  ON cu.id  = r.content_uom_id
WHERE r.category NOT IN ('OK', 'EXPECTED_DEBT')
ORDER BY r.category, ABS(r.diff) DESC, r.material_id;
