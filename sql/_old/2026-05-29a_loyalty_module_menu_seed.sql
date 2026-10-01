SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-29a_loyalty_module_menu_seed.sql
-- Tujuan :
-- 1) Menambah modul Loyalty terpisah dari POS
-- 2) Memindahkan Member dan pengaturan promo loyalty ke sidebar sendiri
-- 3) Menonaktifkan menu member lama di grup POS agar tidak dobel
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'grp.loyalty', 'Member & Promo', 'ri-user-star-line', NULL, NULL, 3, 1, 'MAIN', NULL
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM sys_menu WHERE menu_code = 'grp.loyalty'
)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('loyalty.member.index', 'Member & Loyalitas', 'LOYALTY', 'Master member dan saldo loyalitas pelanggan', 1),
  ('loyalty.point_rule.index', 'Aturan Poin', 'LOYALTY', 'Pengaturan rule point member', 1),
  ('loyalty.stamp_campaign.index', 'Campaign Stamp', 'LOYALTY', 'Pengaturan campaign stamp member', 1),
  ('loyalty.voucher_campaign.index', 'Campaign Voucher', 'LOYALTY', 'Pengaturan campaign voucher member', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'loyalty.member', 'Member', 'ri-user-heart-line', '/loyalty/members', p.id, 1, 1, 'MAIN', parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.member.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'loyalty.point_rule', 'Poin', 'ri-coin-line', '/loyalty/point-rules', p.id, 2, 1, 'MAIN', parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.point_rule.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'loyalty.stamp_campaign', 'Stamp', 'ri-coupon-3-line', '/loyalty/stamp-campaigns', p.id, 3, 1, 'MAIN', parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.stamp_campaign.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'loyalty.voucher_campaign', 'Voucher', 'ri-ticket-2-line', '/loyalty/voucher-campaigns', p.id, 4, 1, 'MAIN', parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.voucher_campaign.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id,
  1,
  CASE WHEN p.page_code = 'loyalty.member.index' OR r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN p.page_code = 'loyalty.member.index' OR r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  0,
  0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'loyalty.member.index',
  'loyalty.point_rule.index',
  'loyalty.stamp_campaign.index',
  'loyalty.voucher_campaign.index'
)
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','KASIR')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'pos.member';

COMMIT;

SELECT 'grp.loyalty' AS seed_key, COUNT(*) AS total_rows FROM sys_menu WHERE menu_code = 'grp.loyalty'
UNION ALL
SELECT 'loyalty.pages', COUNT(*) FROM sys_page WHERE page_code IN (
  'loyalty.member.index',
  'loyalty.point_rule.index',
  'loyalty.stamp_campaign.index',
  'loyalty.voucher_campaign.index'
)
UNION ALL
SELECT 'pos.member.active', COUNT(*) FROM sys_menu WHERE menu_code = 'pos.member' AND is_active = 1;
