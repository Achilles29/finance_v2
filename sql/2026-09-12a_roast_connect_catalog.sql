-- Optional Roast Studio catalog connector. No inventory/production changes.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS sys_roast_connect (
  id tinyint unsigned NOT NULL PRIMARY KEY,
  instance_id varchar(80) NOT NULL,
  name varchar(160) NOT NULL DEFAULT 'Finance',
  enabled tinyint(1) NOT NULL DEFAULT 0,
  division_id bigint unsigned DEFAULT NULL,
  destination_type varchar(30) NOT NULL DEFAULT 'ROASTERY',
  token_hash char(64) DEFAULT NULL,
  token_tail char(4) DEFAULT NULL,
  token_created_at datetime DEFAULT NULL,
  expires_at datetime DEFAULT NULL,
  revision int unsigned NOT NULL DEFAULT 0,
  updated_by bigint unsigned DEFAULT NULL,
  updated_at datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sys_roast_connect_audit (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type varchar(40) NOT NULL,
  actor_id bigint unsigned NOT NULL,
  details text NOT NULL,
  created_at datetime NOT NULL,
  KEY idx_roast_connect_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO sys_roast_connect (id,instance_id) VALUES (1,REPLACE(UUID(),'-',''));

START TRANSACTION;
INSERT INTO sys_page(page_code,page_name,module,description,is_active)
VALUES('system.roast_connect','Integrasi Roast Studio','SYSTEM','Pengelolaan token dan akses katalog untuk Roast Studio, khusus superadmin',1)
ON DUPLICATE KEY UPDATE page_name=VALUES(page_name),description=VALUES(description),is_active=1,updated_at=CURRENT_TIMESTAMP;
INSERT INTO sys_menu(menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type,parent_id)
SELECT 'system.roast_connect','Integrasi Roast Studio','ri-links-line','system/roast-connect',p.id,8,1,'MAIN',m.id
FROM sys_page p JOIN sys_menu m ON m.menu_code='grp.system' WHERE p.page_code='system.roast_connect'
ON DUPLICATE KEY UPDATE menu_label=VALUES(menu_label),url=VALUES(url),page_id=VALUES(page_id),parent_id=VALUES(parent_id),is_active=1,updated_at=CURRENT_TIMESTAMP;
INSERT INTO auth_role_permission(role_id,page_id,can_view,can_create,can_edit,can_delete,can_export,created_at)
SELECT r.id,p.id,1,1,1,0,0,NOW() FROM auth_role r JOIN sys_page p ON p.page_code='system.roast_connect' WHERE r.role_code='SUPERADMIN'
ON DUPLICATE KEY UPDATE can_view=1,can_create=1,can_edit=1,updated_at=CURRENT_TIMESTAMP;
COMMIT;
