SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-27b_online_food_orders_payment_mode.sql
-- Tujuan :
-- 1) Menambah pengaturan payment otomatis/manual untuk Online Food
-- 2) Menambah submenu Orderan Online Food
-- 3) Menyediakan konfigurasi WhatsApp admin untuk konfirmasi manual
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_online_food_setting
  ADD COLUMN IF NOT EXISTS payment_default ENUM('AUTO','MANUAL') NOT NULL DEFAULT 'MANUAL' AFTER allow_qris,
  ADD COLUMN IF NOT EXISTS payment_auto_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_default,
  ADD COLUMN IF NOT EXISTS payment_manual_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_auto_enabled,
  ADD COLUMN IF NOT EXISTS auto_payment_provider ENUM('MIDTRANS') NOT NULL DEFAULT 'MIDTRANS' AFTER payment_manual_enabled,
  ADD COLUMN IF NOT EXISTS midtrans_server_key VARCHAR(255) NULL AFTER auto_payment_provider,
  ADD COLUMN IF NOT EXISTS midtrans_client_key VARCHAR(255) NULL AFTER midtrans_server_key,
  ADD COLUMN IF NOT EXISTS midtrans_is_production TINYINT(1) NOT NULL DEFAULT 0 AFTER midtrans_client_key,
  ADD COLUMN IF NOT EXISTS manual_whatsapp_number VARCHAR(32) NULL AFTER min_order_amount,
  ADD COLUMN IF NOT EXISTS manual_whatsapp_template VARCHAR(255) NULL AFTER manual_whatsapp_number,
  ADD COLUMN IF NOT EXISTS manual_payment_instructions TEXT NULL AFTER manual_whatsapp_template;

UPDATE pos_online_food_setting
SET
  payment_auto_enabled = COALESCE(NULLIF(allow_qris, 0), payment_auto_enabled),
  payment_manual_enabled = COALESCE(NULLIF(allow_cod, 0), payment_manual_enabled),
  payment_default = CASE WHEN COALESCE(allow_qris, 0) = 1 THEN 'AUTO' ELSE 'MANUAL' END
WHERE id = 1;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.online_food.orders',
  'Orderan Online Food',
  'ri-file-list-3-line',
  '/pos/online-food/orders',
  p.id,
  1,
  1,
  'MAIN',
  m.id
FROM sys_page p
JOIN sys_menu m ON m.menu_code = 'pos.online_food'
WHERE p.page_code = 'pos.online_food.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET sort_order = 2, updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'pos.online_food.settings';

COMMIT;

SELECT 'pos_online_food_setting.payment_mode' AS seed_key, COUNT(*) AS total_rows
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_online_food_setting'
  AND COLUMN_NAME IN ('payment_default', 'payment_auto_enabled', 'payment_manual_enabled', 'manual_whatsapp_number')
UNION ALL
SELECT 'sys_menu.pos.online_food.orders', COUNT(*)
FROM sys_menu
WHERE menu_code = 'pos.online_food.orders';
