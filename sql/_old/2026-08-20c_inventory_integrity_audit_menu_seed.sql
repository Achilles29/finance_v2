SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-20c_inventory_integrity_audit_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan layar Audit Integritas Stok ke Kontrol Stok
-- 2) Memberi akses baca kepada operator inventory dan manajemen
-- 3) Tidak mengubah stok, lot, movement, HPP, atau saldo historis
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'inventory.stock.integrity_audit.index',
  'Audit Integritas Stok',
  'INVENTORY',
  'Pemeriksaan baca-saja jejak adjustment, batch, transfer, POS, defisit, HPP, lot, dan period lock bulan aktif.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (
  menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id
)
SELECT
  'inventory.stock.integrity_audit',
  'Audit Integritas Stok',
  'ri-shield-check-line',
  '/inventory/stock/integrity-audit',
  page.id,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'inventory.stock.group.control'
WHERE page.page_code = 'inventory.stock.integrity_audit.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  role.id,
  page.id,
  1, 0, 0, 0, 1,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'inventory.stock.integrity_audit.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, m.menu_label, parent.menu_code AS parent_code, m.url, p.page_code
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
LEFT JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'inventory.stock.integrity_audit';
