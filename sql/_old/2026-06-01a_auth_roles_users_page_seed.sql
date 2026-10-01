INSERT INTO sys_page (page_code, page_name, module, description, is_active, created_at)
SELECT 'auth.roles.users', 'Atur Pengguna per Role', 'SISTEM', 'Menentukan user mana yang masuk ke suatu role.', 1, NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM sys_page
    WHERE page_code = 'auth.roles.users'
);

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT rp.role_id, target_page.id, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export, NOW()
FROM auth_role_permission rp
JOIN sys_page source_page ON source_page.id = rp.page_id AND source_page.page_code = 'auth.roles.index'
JOIN sys_page target_page ON target_page.page_code = 'auth.roles.users'
LEFT JOIN auth_role_permission existing ON existing.role_id = rp.role_id AND existing.page_id = target_page.id
WHERE existing.role_id IS NULL;