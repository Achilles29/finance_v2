-- ============================================================
-- Sync Extra Group + Group Item + Group Product dari core -> db_finance
-- Jalankan saat koneksi aktif ke db_finance
-- ============================================================
SET NAMES utf8mb4;

START TRANSACTION;

-- 1) Extra Group (wajib sebelum item dan product map)
INSERT INTO mst_extra_group (
  group_code,
  group_name,
  product_division_id,
  is_required,
  min_select,
  max_select,
  sort_order,
  is_active
)
SELECT
  CONCAT('EXG-', LPAD(g.id, 4, '0')) AS group_code,
  g.group_name,
  pd.id AS product_division_id,
  IFNULL(g.is_required, 0) AS is_required,
  IFNULL(g.min_select, 0) AS min_select,
  IFNULL(g.max_select, 1) AS max_select,
  IFNULL(g.display_order, 0) AS sort_order,
  IFNULL(g.is_active, 1) AS is_active
FROM core.pos_extra_group g
LEFT JOIN core.prd_product_division spd ON spd.id = g.product_division_id
LEFT JOIN mst_product_division pd ON pd.code = spd.division_code
ON DUPLICATE KEY UPDATE
  group_name = VALUES(group_name),
  product_division_id = VALUES(product_division_id),
  is_required = VALUES(is_required),
  min_select = VALUES(min_select),
  max_select = VALUES(max_select),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);

-- 2) Refresh Group Item mapping
DELETE FROM mst_extra_group_item;

INSERT INTO mst_extra_group_item (
  extra_group_id,
  extra_id,
  sort_order
)
SELECT
  tg.id AS extra_group_id,
  te.id AS extra_id,
  IFNULL(gi.display_order, 0) AS sort_order
FROM core.pos_extra_group_item gi
JOIN core.pos_extra_group sg ON sg.id = gi.extra_group_id
JOIN core.pos_extra se ON se.id = gi.extra_id
JOIN mst_extra_group tg ON tg.group_code = CONCAT('EXG-', LPAD(sg.id, 4, '0'))
JOIN mst_extra te ON te.extra_code = se.extra_code;

-- 3) Refresh Group Product mapping
DELETE FROM mst_product_extra_map;

INSERT INTO mst_product_extra_map (
  extra_group_id,
  product_id,
  sort_order
)
SELECT
  tg.id AS extra_group_id,
  tp.id AS product_id,
  IFNULL(gp.display_order, 0) AS sort_order
FROM core.pos_extra_group_product gp
JOIN core.pos_extra_group sg ON sg.id = gp.extra_group_id
JOIN core.prd_product sp ON sp.id = gp.product_id
JOIN mst_extra_group tg ON tg.group_code = CONCAT('EXG-', LPAD(sg.id, 4, '0'))
JOIN mst_product tp ON tp.product_code = sp.product_code;

COMMIT;

SELECT 'mst_extra_group' AS table_name, COUNT(*) AS total_rows FROM mst_extra_group
UNION ALL
SELECT 'mst_extra_group_item', COUNT(*) FROM mst_extra_group_item
UNION ALL
SELECT 'mst_product_extra_map', COUNT(*) FROM mst_product_extra_map;
