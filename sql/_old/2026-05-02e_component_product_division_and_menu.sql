SET NAMES utf8mb4;

START TRANSACTION;

-- 1) Add product_division_id to mst_component (backward-compatible migration).
SET @has_component_pd := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component'
    AND COLUMN_NAME = 'product_division_id'
);
SET @sql := IF(
  @has_component_pd = 0,
  'ALTER TABLE mst_component ADD COLUMN product_division_id BIGINT UNSIGNED NULL AFTER component_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill product_division_id for existing component rows.
UPDATE mst_component c
LEFT JOIN mst_product_division pd_default ON pd_default.code = 'GENERAL'
SET c.product_division_id = COALESCE(c.product_division_id, pd_default.id, (SELECT id FROM mst_product_division ORDER BY id LIMIT 1))
WHERE c.product_division_id IS NULL OR c.product_division_id = 0;

-- Ensure column is NOT NULL after data backfill.
SET @sql := 'ALTER TABLE mst_component MODIFY COLUMN product_division_id BIGINT UNSIGNED NOT NULL';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure supporting index exists.
SET @has_idx_component_pd := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component'
    AND INDEX_NAME = 'idx_mst_component_product_division'
);
SET @sql := IF(
  @has_idx_component_pd = 0,
  'ALTER TABLE mst_component ADD KEY idx_mst_component_product_division (product_division_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure FK exists.
SET @has_fk_component_pd := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component'
    AND CONSTRAINT_NAME = 'fk_mst_component_product_division'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @has_fk_component_pd = 0,
  'ALTER TABLE mst_component ADD CONSTRAINT fk_mst_component_product_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Register page for variable cost settings.
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('master.variable_cost_default.index', 'Pengaturan Variable Cost Default', 'MASTER', 'Kelola default variable cost product/component', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_page
SET page_name = 'Master Bahan Baku',
    updated_at = CURRENT_TIMESTAMP
WHERE page_code = 'master.material.index';

UPDATE sys_menu
SET menu_label = 'Master Bahan Baku',
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'master.material';

-- 3) Add menu entries for relation hubs and variable cost settings.
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.product.recipe', 'Resep Produk', 'ri-book-open-line', '/master/relation/product-recipe', p.id, 10, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.product_recipe.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.component.formula', 'Formula Component', 'ri-function-line', '/master/relation/component-formula', p.id, 11, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.component_formula.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.variable.cost.default', 'Variable Cost Default', 'ri-percent-line', '/master/variable-cost-default', p.id, 12, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.variable_cost_default.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.product.division', 'Divisi Produk', 'ri-git-merge-line', '/master/product-division', p.id, 13, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.product_division.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.product.classification', 'Klasifikasi Produk', 'ri-git-branch-line', '/master/product-classification', p.id, 14, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.product_classification.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.product.category', 'Kategori Produk', 'ri-node-tree', '/master/product-category', p.id, 15, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.product_category.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.item.category', 'Kategori Item/Bahan Baku', 'ri-price-tag-3-line', '/master/item-category', p.id, 16, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.item_category.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.component.category', 'Kategori Component', 'ri-price-tag-2-line', '/master/component-category', p.id, 17, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.component_category.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.product.extra.map', 'Mapping Extra Produk', 'ri-links-line', '/master/relation/product-extra', p.id, 18, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.product_extra_map.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- 4) Default grants for operational roles.
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1, 1, 1, 1, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'master.variable_cost_default.index',
  'master.product_recipe.index',
  'master.component_formula.index',
  'master.product_division.index',
  'master.product_classification.index',
  'master.product_category.index',
  'master.item_category.index',
  'master.component_category.index',
  'master.product_extra_map.index'
)
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
