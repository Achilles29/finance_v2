-- PREPARED / BELUM DIJALANKAN. Sidebar + permissions only; no business data.
-- Prerequisites: sys_page, sys_menu (grp.system), auth_role, auth_role_permission.
-- Run on the intended instance only. Safe to repeat sequentially; preserve existing grants/menu.
START TRANSACTION;
INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'system.guide.index','Panduan Aplikasi','SYSTEM','SYSTEM','Panduan penggunaan aplikasi; tidak memberikan izin modul tujuan',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='system.guide.index');
INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'system.guide.server','Panduan Admin Server','SYSTEM','SYSTEM','Bab instalasi, konfigurasi, cron dan pemulihan; butuh izin Panduan Aplikasi juga',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='system.guide.server');

INSERT INTO sys_menu (parent_id,menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type)
SELECT g.id,'system.guide','Panduan Aplikasi','ri-book-open-line','guide',p.id,85,1,'MAIN'
FROM sys_page p JOIN sys_menu g ON g.menu_code='grp.system'
WHERE p.page_code='system.guide.index'
AND NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code='system.guide');

-- Only SUPERADMIN receives initial view rights. Admin grants operator roles through Roles UI.
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT r.id,p.id,1,0,0,0,0 FROM auth_role r JOIN sys_page p
ON p.page_code IN ('system.guide.index','system.guide.server')
WHERE r.role_code='SUPERADMIN'
AND NOT EXISTS (SELECT 1 FROM auth_role_permission a WHERE a.role_id=r.id AND a.page_id=p.id);
COMMIT;

-- Postcheck metadata (must return two pages, one menu, SUPERADMIN grants).
SELECT page_code,page_name,is_active FROM sys_page WHERE page_code IN ('system.guide.index','system.guide.server');
SELECT menu_code,url,parent_id,is_active FROM sys_menu WHERE menu_code='system.guide';
SELECT r.role_code,p.page_code,a.can_view FROM auth_role_permission a
JOIN auth_role r ON r.id=a.role_id JOIN sys_page p ON p.id=a.page_id
WHERE r.role_code='SUPERADMIN' AND p.page_code IN ('system.guide.index','system.guide.server');
-- No automatic rollback/delete. Revoke guide view rights via UI if disabling.
