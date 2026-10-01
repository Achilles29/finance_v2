SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08h_audit_division_movement_without_monthly_identity.sql
-- Tujuan :
-- 1) Mengaudit identity stok bahan baku divisi yang ada di movement log
--    tetapi tidak punya pasangan di inv_division_monthly_stock
-- 2) Menjadi daftar investigasi untuk pos/stock-commit-audit,
--    bukan source tampilan halaman stok divisi/material harian
-- 3) Menegaskan bahwa source-of-truth halaman stok divisi hanya
--    berasal dari inv_division_monthly_stock dengan material_id tidak null
-- ============================================================

SET @as_of_date := CURDATE();
SET @target_month := DATE_FORMAT(@as_of_date, '%Y-%m-01');

DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_latest_identities;
CREATE TEMPORARY TABLE tmp_division_monthly_latest_identities AS
SELECT
    s.division_id,
    COALESCE(s.destination_type, 'OTHER') AS destination_type,
    s.identity_key,
    s.item_id,
    COALESCE(s.material_id, i.material_id) AS material_id,
    s.buy_uom_id,
    s.content_uom_id,
    COALESCE(s.profile_key, '') AS profile_key
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
JOIN (
    SELECT division_id, COALESCE(destination_type, 'OTHER') AS destination_type, identity_key, MAX(month_key) AS max_month
    FROM inv_division_monthly_stock
    WHERE month_key <= @target_month
    GROUP BY division_id, COALESCE(destination_type, 'OTHER'), identity_key
) lm
  ON lm.division_id = s.division_id
 AND lm.destination_type = COALESCE(s.destination_type, 'OTHER')
 AND lm.identity_key = s.identity_key
 AND lm.max_month = s.month_key
WHERE COALESCE(s.material_id, i.material_id, 0) > 0;

ALTER TABLE tmp_division_monthly_latest_identities
  ADD KEY idx_monthly_identity (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_division_movement_latest_identities;
CREATE TEMPORARY TABLE tmp_division_movement_latest_identities AS
SELECT
    l.division_id,
    COALESCE(l.destination_type, 'OTHER') AS destination_type,
    l.item_id,
    COALESCE(l.material_id, i.material_id) AS material_id,
    l.buy_uom_id,
    l.content_uom_id,
    COALESCE(l.profile_key, '') AS profile_key,
    l.profile_name,
    l.profile_brand,
    l.profile_description,
    MAX(l.movement_date) AS last_movement_date,
    MAX(l.id) AS last_movement_id
FROM inv_stock_movement_log l
LEFT JOIN mst_item i ON i.id = l.item_id
WHERE l.movement_scope = 'DIVISION'
  AND l.movement_date <= @as_of_date
  AND COALESCE(l.material_id, i.material_id, 0) > 0
GROUP BY
    l.division_id,
    COALESCE(l.destination_type, 'OTHER'),
    l.item_id,
    COALESCE(l.material_id, i.material_id),
    l.buy_uom_id,
    l.content_uom_id,
    COALESCE(l.profile_key, ''),
    l.profile_name,
    l.profile_brand,
    l.profile_description;

ALTER TABLE tmp_division_movement_latest_identities
  ADD KEY idx_movement_identity (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

SET @division_name_expr := (
    SELECT CASE
        WHEN COUNT(*) = 1 THEN 'd.division_name'
        ELSE CASE
            WHEN (
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'mst_operational_division'
                  AND COLUMN_NAME = 'name'
            ) = 1
            THEN 'd.name'
            ELSE 'CAST(mv.division_id AS CHAR)'
        END
    END
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mst_operational_division'
      AND COLUMN_NAME = 'division_name'
);

SET @sql := CONCAT(
'SELECT
    mv.division_id,
    ', @division_name_expr, ' AS division_name,
    mv.destination_type,
    mv.item_id,
    it.item_code,
    it.item_name,
    mv.material_id,
    m.material_code,
    m.material_name,
    mv.buy_uom_id,
    mv.content_uom_id,
    mv.profile_key,
    mv.profile_name,
    mv.profile_brand,
    mv.profile_description,
    mv.last_movement_date,
    mv.last_movement_id
FROM tmp_division_movement_latest_identities mv
LEFT JOIN tmp_division_monthly_latest_identities ms
  ON ms.division_id = mv.division_id
 AND ms.destination_type = mv.destination_type
 AND ms.item_id <=> mv.item_id
 AND ms.material_id <=> mv.material_id
 AND ms.buy_uom_id <=> mv.buy_uom_id
 AND ms.content_uom_id <=> mv.content_uom_id
 AND ms.profile_key <=> mv.profile_key
LEFT JOIN mst_operational_division d ON d.id = mv.division_id
LEFT JOIN mst_item it ON it.id = mv.item_id
LEFT JOIN mst_material m ON m.id = mv.material_id
WHERE ms.identity_key IS NULL
ORDER BY mv.division_id, mv.destination_type, mv.material_id, mv.item_id, mv.last_movement_date DESC'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
