SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08i_audit_invalid_same_uom_profile_structure.sql
-- Tujuan :
-- 1) Mengaudit profile/item stok yang punya struktur konversi tidak valid:
--    buy_uom_id = content_uom_id tetapi content_per_buy <> 1
-- 2) Menemukan kasus yang masih bisa dipetakan ke profile legacy valid
--    (buy_uom_id <> content_uom_id) untuk repair data semi-otomatis
-- 3) Menjadi daftar kerja untuk mencegah batch component gagal lagi
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_monthly;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_monthly AS
SELECT
    s.id AS monthly_id,
    s.month_key,
    s.division_id,
    COALESCE(s.destination_type, 'OTHER') AS destination_type,
    s.item_id,
    COALESCE(s.material_id, i.material_id) AS material_id,
    s.profile_key AS invalid_profile_key,
    s.profile_name,
    COALESCE(c.brand_name, s.profile_brand, '') AS profile_brand,
    s.buy_uom_id AS invalid_buy_uom_id,
    s.content_uom_id AS invalid_content_uom_id,
    ROUND(COALESCE(s.profile_content_per_buy, 1), 6) AS profile_content_per_buy,
    s.closing_qty_buy,
    s.closing_qty_content,
    c.id AS invalid_catalog_id
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_purchase_catalog c ON c.profile_key = s.profile_key
WHERE COALESCE(s.material_id, i.material_id, 0) > 0
  AND COALESCE(s.buy_uom_id, 0) = COALESCE(s.content_uom_id, 0)
  AND COALESCE(s.buy_uom_id, 0) > 0
  AND ABS(ROUND(COALESCE(s.profile_content_per_buy, 1), 6) - 1) > 0.000001;

ALTER TABLE tmp_invalid_same_uom_monthly ADD PRIMARY KEY (monthly_id);

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_monthly_with_legacy;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_monthly_with_legacy AS
SELECT
    t.*, 
    legacy.id AS legacy_catalog_id,
    legacy.profile_key AS legacy_profile_key,
    legacy.buy_uom_id AS legacy_buy_uom_id,
    legacy.content_uom_id AS legacy_content_uom_id,
    ROUND(COALESCE(legacy.content_per_buy, 1), 6) AS legacy_content_per_buy
FROM tmp_invalid_same_uom_monthly t
LEFT JOIN mst_purchase_catalog legacy
  ON legacy.id = (
    SELECT l.id
    FROM mst_purchase_catalog l
    WHERE COALESCE(l.item_id, 0) = COALESCE(t.item_id, 0)
      AND COALESCE(l.material_id, 0) = COALESCE(t.material_id, 0)
      AND UPPER(TRIM(COALESCE(l.catalog_name, ''))) = UPPER(TRIM(COALESCE(t.profile_name, '')))
      AND UPPER(TRIM(COALESCE(l.brand_name, ''))) = UPPER(TRIM(COALESCE(t.profile_brand, '')))
      AND ROUND(COALESCE(l.content_per_buy, 1), 6) = ROUND(COALESCE(t.profile_content_per_buy, 1), 6)
      AND COALESCE(l.buy_uom_id, 0) <> COALESCE(l.content_uom_id, 0)
    ORDER BY COALESCE(l.is_active, 1) DESC, l.id ASC
    LIMIT 1
  );

SELECT 'division_monthly_invalid_same_uom_total' AS metric, COUNT(*) AS total
FROM tmp_invalid_same_uom_monthly
UNION ALL
SELECT 'division_monthly_invalid_same_uom_auto_fixable', COUNT(*)
FROM tmp_invalid_same_uom_monthly_with_legacy
WHERE COALESCE(legacy_catalog_id, 0) > 0
UNION ALL
SELECT 'division_monthly_invalid_same_uom_manual_review', COUNT(*)
FROM tmp_invalid_same_uom_monthly_with_legacy
WHERE COALESCE(legacy_catalog_id, 0) = 0
UNION ALL
SELECT 'active_catalog_invalid_same_uom_total', COUNT(*)
FROM mst_purchase_catalog
WHERE COALESCE(is_active, 1) = 1
  AND COALESCE(buy_uom_id, 0) = COALESCE(content_uom_id, 0)
  AND COALESCE(buy_uom_id, 0) > 0
  AND ABS(ROUND(COALESCE(content_per_buy, 1), 6) - 1) > 0.000001
UNION ALL
SELECT 'movement_division_invalid_same_uom_total', COUNT(*)
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND COALESCE(buy_uom_id, 0) = COALESCE(content_uom_id, 0)
  AND COALESCE(buy_uom_id, 0) > 0
  AND ABS(ROUND(COALESCE(profile_content_per_buy, 1), 6) - 1) > 0.000001;

SELECT *
FROM tmp_invalid_same_uom_monthly_with_legacy
ORDER BY monthly_id;
