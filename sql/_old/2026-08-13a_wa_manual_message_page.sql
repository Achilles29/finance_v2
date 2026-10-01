START TRANSACTION;

-- Tambah halaman kirim pesan WA manual: nomor diketik langsung atau dipilih dari crm_member.

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('wa.manual', 'WA Kirim Manual', 'WA', 'Kirim pesan WhatsApp manual ke nomor atau member terdaftar', 1)
ON DUPLICATE KEY UPDATE
    page_name   = VALUES(page_name),
    module      = VALUES(module),
    description = VALUES(description),
    is_active   = VALUES(is_active),
    updated_at  = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'wa.manual', 'Kirim Manual', 'ri-send-plane-line', 'wa/manual',
       p.id, 5, 1, 'MAIN', g.id
FROM sys_page p
JOIN sys_menu g ON g.menu_code = 'grp.wa'
WHERE p.page_code = 'wa.manual'
ON DUPLICATE KEY UPDATE
    menu_label = VALUES(menu_label),
    icon       = VALUES(icon),
    url        = VALUES(url),
    page_id    = VALUES(page_id),
    parent_id  = VALUES(parent_id),
    sort_order = VALUES(sort_order),
    is_active  = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET sort_order = 6,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'wa.log';

UPDATE sys_menu
SET sort_order = 7,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'wa.settings';

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 1, 1, 1, NOW()
FROM auth_role r
CROSS JOIN sys_page p
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'ADMIN')
  AND p.page_code = 'wa.manual'
  AND NOT EXISTS (
    SELECT 1
    FROM auth_role_permission x
    WHERE x.role_id = r.id AND x.page_id = p.id
  );

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 0, 0, 1, NOW()
FROM auth_role r
CROSS JOIN sys_page p
WHERE r.role_code IN ('MANAGER', 'GM')
  AND p.page_code = 'wa.manual'
  AND NOT EXISTS (
    SELECT 1
    FROM auth_role_permission x
    WHERE x.role_id = r.id AND x.page_id = p.id
  );

UPDATE auth_role_permission rp
JOIN auth_role r ON r.id = rp.role_id
JOIN sys_page p ON p.id = rp.page_id
SET rp.can_view = 1,
    rp.can_create = 1,
    rp.can_edit = CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'ADMIN') THEN 1 ELSE rp.can_edit END,
    rp.can_delete = CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'ADMIN') THEN 1 ELSE rp.can_delete END,
    rp.can_export = 1,
    rp.updated_at = CURRENT_TIMESTAMP
WHERE p.page_code = 'wa.manual'
  AND r.role_code IN ('SUPERADMIN', 'CEO', 'ADMIN', 'MANAGER', 'GM');

COMMIT;
