SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-22a_production_component_cost_variable_setting.sql
-- Tujuan :
-- 1) Menjamin tabel default variable cost tersedia.
-- 2) Menambah page/menu Production untuk pengaturan variable cost.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mst_variable_cost_default (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  scope_code ENUM('PRODUCT','COMPONENT') NOT NULL,
  default_percent DECIMAL(10,4) NOT NULL DEFAULT 20.0000,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mst_variable_cost_default_scope (scope_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO mst_variable_cost_default (scope_code, default_percent, notes, is_active)
VALUES
  ('COMPONENT', 20.0000, 'Default variable cost untuk kalkulasi HPP component', 1),
  ('PRODUCT', 20.0000, 'Default variable cost untuk kalkulasi HPP product', 1)
ON DUPLICATE KEY UPDATE
  default_percent = VALUES(default_percent),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('production.component.cost.variable.index', 'Pengaturan Variable Cost', 'PRODUKSI', 'Pengaturan default variable cost component dan product', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.cost.variable', 'Pengaturan Variable Cost', 'ri-percent-line', '/production/component-cost-variables', p.id, 8, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.cost.variable.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  1,
  0,
  0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'production.component.cost.variable.index'
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
