SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-01g_sidebar_page_registry_sync.sql
-- Tujuan : Melengkapi registry DB untuk halaman sidebar yang
--          sebelumnya hanya mengandalkan runtime/permission lama.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('pos.order.paid.index', 'Pesanan Terbayar POS', 'POS', 'Workbench pesanan POS berstatus PAID beserta filter dan tindak lanjut operasionalnya.', 1),
  ('product.monitoring.availability.index', 'Monitoring Stok Produk', 'MASTER', 'Monitoring ketersediaan produk berbasis resep, mode stok, dan outlet.', 1),
  ('production.component.reconcile.index', 'Rekonsiliasi Base/Prepare', 'PRODUKSI', 'Rekonsiliasi stok harian base/prepare terhadap projection dan movement aktual.', 1),
  ('production.component.lot.index', 'Lot FIFO Base/Prepare', 'PRODUKSI', 'Monitoring lot FIFO component/base prepare beserta detail pemakaiannya.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.order.paid.index',
  'Pesanan Terbayar',
  'ri-wallet-3-line',
  '/pos/orders/paid',
  p.id,
  45,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.order.paid.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'product.monitoring.availability.index'
SET m.page_id = p.id,
    m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code = 'product.monitoring.availability';

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'product.monitoring.stock'
SET child.parent_id = parent.id,
    child.sort_order = 1,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code = 'product.monitoring.availability';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'production.component.reconcile.index'
SET m.page_id = p.id,
    m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code = 'production.component.reconcile';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'production.component.lot.index'
SET m.page_id = p.id,
    m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code = 'production.component.lot';

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  dst.id,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  NOW()
FROM auth_role_permission rp
JOIN sys_page src ON src.id = rp.page_id AND src.page_code = 'pos.order.draft.index'
JOIN sys_page dst ON dst.page_code = 'pos.order.paid.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  dst.id,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  NOW()
FROM auth_role_permission rp
JOIN sys_page src ON src.id = rp.page_id AND src.page_code = 'master.product_extra.workspace.index'
JOIN sys_page dst ON dst.page_code = 'product.monitoring.availability.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  dst.id,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  NOW()
FROM auth_role_permission rp
JOIN sys_page src ON src.id = rp.page_id AND src.page_code = 'production.component.daily.index'
JOIN sys_page dst ON dst.page_code = 'production.component.reconcile.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  dst.id,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  NOW()
FROM auth_role_permission rp
JOIN sys_page src ON src.id = rp.page_id AND src.page_code = 'production.component.batch.index'
JOIN sys_page dst ON dst.page_code = 'production.component.lot.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, m.menu_label, parent.menu_code AS parent_code, p.page_code, m.sort_order
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
LEFT JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code IN (
  'pos.order.paid.index',
  'product.monitoring.availability',
  'production.component.reconcile',
  'production.component.lot'
)
ORDER BY m.menu_code;