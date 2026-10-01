SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25a_pos_print_rule_mode_and_customer_review_foundation.sql
-- Tujuan :
-- 1) Menambah tiga pilihan perilaku pada aturan cetak POS
-- 2) Menyimpan tautan ulasan pelanggan yang aman per nota POS
-- 3) Menambahkan laporan ulasan pelanggan untuk moderator internal
--
-- Prasyarat:
-- - Jalankan 2026-08-24c_pos_print_configuration_single_source_foundation.sql
--   terlebih dahulu.
--
-- Catatan:
-- - OFF tidak membuat target cetak.
-- - AUTO mengirim target langsung ke Local Agent.
-- - ASK membuat target, lalu kasir memilih cetak atau lewati.
-- - QR ulasan baru benar-benar muncul pada struk bila pengaturan umum dan
--   layout struk sama-sama mengizinkannya.
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_print_route
  ADD COLUMN IF NOT EXISTS print_mode ENUM('OFF','AUTO','ASK') NOT NULL DEFAULT 'AUTO' AFTER notes,
  ADD INDEX IF NOT EXISTS idx_pos_print_route_event_mode (event_code, print_mode, outlet_id, terminal_id);

-- Konfigurasi lama tetap berperilaku sama: route aktif menjadi otomatis,
-- sedangkan route nonaktif menjadi tidak mencetak.
UPDATE pos_print_route
SET print_mode = CASE WHEN COALESCE(is_active, 0) = 1 THEN 'AUTO' ELSE 'OFF' END
WHERE COALESCE(NULLIF(print_mode, ''), '') = ''
   OR print_mode NOT IN ('OFF', 'AUTO', 'ASK');

UPDATE pos_print_route
SET is_active = CASE WHEN print_mode = 'OFF' THEN 0 ELSE 1 END;

CREATE TABLE IF NOT EXISTS pos_customer_review (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  review_token CHAR(64) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  member_id BIGINT UNSIGNED NULL,
  order_no_snapshot VARCHAR(60) NULL,
  customer_name_snapshot VARCHAR(150) NULL,
  rating TINYINT UNSIGNED NULL,
  review_text TEXT NULL,
  review_status ENUM('OPEN','SUBMITTED','HIDDEN') NOT NULL DEFAULT 'OPEN',
  submitted_at DATETIME NULL,
  hidden_by BIGINT UNSIGNED NULL,
  hidden_at DATETIME NULL,
  hidden_reason VARCHAR(255) NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_customer_review_token (review_token),
  UNIQUE KEY uk_pos_customer_review_order (order_id),
  KEY idx_pos_customer_review_status_date (review_status, submitted_at),
  KEY idx_pos_customer_review_outlet_date (outlet_id, submitted_at),
  CONSTRAINT fk_pos_customer_review_order
    FOREIGN KEY (order_id) REFERENCES pos_order(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pos_customer_review_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_customer_review_member
    FOREIGN KEY (member_id) REFERENCES crm_member(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_customer_review_hidden_by
    FOREIGN KEY (hidden_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ulasan pelanggan dari QR unik pada struk pembayaran POS.';

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.customer_review.index',
  'Ulasan Pelanggan POS',
  'POS',
  'Daftar dan moderasi ulasan pelanggan yang masuk melalui QR pada struk pembayaran.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.customer_review',
  'Ulasan Pelanggan',
  'ri-star-smile-line',
  '/pos/customer-reviews',
  page.id,
  12,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE page.page_code = 'pos.customer_review.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  role.id,
  page.id,
  1,
  0,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','HOD') THEN 1 ELSE 0 END,
  0,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_FIN','HOD') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'pos.customer_review.index'
WHERE role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_FIN','HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'pos_print_route.print_mode' AS check_key, COUNT(*) AS total_rows
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_print_route'
  AND COLUMN_NAME = 'print_mode'
UNION ALL
SELECT 'pos_customer_review', COUNT(*) FROM pos_customer_review
UNION ALL
SELECT 'sys_page.pos.customer_review.index', COUNT(*) FROM sys_page WHERE page_code = 'pos.customer_review.index'
UNION ALL
SELECT 'sys_menu.pos.customer_review', COUNT(*) FROM sys_menu WHERE menu_code = 'pos.customer_review';
