SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-11c_normalize_active_same_uom_purchase_catalogs.sql
-- Tujuan :
-- 1) Membersihkan master katalog pembelian aktif yang masih kontradiktif:
--    buy_uom_id = content_uom_id tetapi content_per_buy <> 1
-- 2) Menormalkan harga dari skema harga per-pack legacy menjadi harga
--    per-unit content aktual
-- 3) Mencegah receipt/PO baru menulis profile invalid serupa lagi
--
-- Catatan:
-- - Script ini hanya menyentuh master katalog aktif.
-- - Histori stok/dokumen live diperbaiki oleh script 2026-06-11b.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Normalize active same-UOM purchase catalogs 2026-06-11';

DROP TEMPORARY TABLE IF EXISTS tmp_active_same_uom_bad_catalog;
CREATE TEMPORARY TABLE tmp_active_same_uom_bad_catalog AS
SELECT
  c.id,
  c.profile_key,
  c.item_id,
  c.material_id,
  c.buy_uom_id,
  c.content_uom_id,
  ROUND(COALESCE(c.content_per_buy, 1), 6) AS old_cpb,
  ROUND(COALESCE(c.standard_price, 0), 2) AS old_standard_price,
  ROUND(COALESCE(c.last_unit_price, 0), 2) AS old_last_unit_price
FROM mst_purchase_catalog c
WHERE COALESCE(c.is_active, 1) = 1
  AND c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;

ALTER TABLE tmp_active_same_uom_bad_catalog
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_active_same_uom_bad_catalog_profile (profile_key);

UPDATE mst_purchase_catalog c
JOIN tmp_active_same_uom_bad_catalog t ON t.id = c.id
SET
  c.content_per_buy = 1.000000,
  c.conversion_factor_to_content = 1.00000000,
  c.standard_price = CASE
    WHEN c.standard_price IS NULL THEN NULL
    ELSE ROUND(c.standard_price / NULLIF(t.old_cpb, 0), 2)
  END,
  c.last_unit_price = CASE
    WHEN c.last_unit_price IS NULL THEN NULL
    ELSE ROUND(c.last_unit_price / NULLIF(t.old_cpb, 0), 2)
  END,
  c.notes = LEFT(TRIM(CONCAT(
    COALESCE(c.notes, ''),
    CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | normalized in-place from cpb=',
    CAST(t.old_cpb AS CHAR)
  )), 255),
  c.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'catalog_rows_normalized' AS metric, COUNT(*) AS total
FROM tmp_active_same_uom_bad_catalog
UNION ALL
SELECT 'active_same_uom_invalid_remaining', COUNT(*)
FROM mst_purchase_catalog c
WHERE COALESCE(c.is_active, 1) = 1
  AND c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;
