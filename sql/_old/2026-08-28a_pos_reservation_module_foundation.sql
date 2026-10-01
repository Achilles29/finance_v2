SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-28a_pos_reservation_module_foundation.sql
-- Tujuan :
-- 1) Menyediakan dokumen reservasi sebelum menjadi order POS resmi.
-- 2) Menjaga DP reservasi tetap memakai pos_payment dan mutasi rekening.
-- 3) Menyimpan produk, extra, status, dan jejak keputusan kasir.
-- 4) Menambahkan halaman Reservasi POS di sidebar setelah Self Order.
--
-- Prinsip penting:
-- - Reservasi PENDING belum mengurangi stok dan belum mengirim tiket produksi.
-- - Saat diterima kasir, aplikasi membuat satu pos_order ber-channel
--   RESERVATION. Order itulah yang menjadi sumber stok, HPP, penjualan,
--   pembayaran lanjutan, void, refund, dan cetak.
-- - DP tetap dicatat pada pos_payment bertipe DEPOSIT dan mutasi rekening
--   yang sudah berlaku. Tabel reservasi hanya menjadi tautan dan auditnya.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_reservation (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_no VARCHAR(50) NOT NULL,
  status ENUM('PENDING','VERIFIED_ACTIVE','VERIFIED_PAID','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  reservation_at DATETIME NOT NULL,
  reservation_end_at DATETIME NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  sales_channel_id BIGINT UNSIGNED NULL,
  service_type ENUM('DINE_IN','TAKE_AWAY','DELIVERY','PICKUP') NOT NULL DEFAULT 'DINE_IN',
  member_id BIGINT UNSIGNED NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(30) NULL,
  customer_email VARCHAR(150) NULL,
  guest_count INT UNSIGNED NOT NULL DEFAULT 1,
  table_no VARCHAR(40) NULL,
  notes VARCHAR(255) NULL,
  subtotal_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  service_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  deposit_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  deposit_applied_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  remaining_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  order_id BIGINT UNSIGNED NULL,
  settlement_payment_id BIGINT UNSIGNED NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_by_user_id BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  rejected_by BIGINT UNSIGNED NULL,
  rejected_by_user_id BIGINT UNSIGNED NULL,
  rejected_at DATETIME NULL,
  rejection_reason VARCHAR(255) NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  cancelled_by_user_id BIGINT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  cancellation_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_reservation_no (reservation_no),
  UNIQUE KEY uk_pos_reservation_order (order_id),
  UNIQUE KEY uk_pos_reservation_settlement_payment (settlement_payment_id),
  KEY idx_pos_reservation_status_time (status, reservation_at),
  KEY idx_pos_reservation_outlet_time (outlet_id, reservation_at),
  KEY idx_pos_reservation_member (member_id),
  KEY idx_pos_reservation_created_by (created_by),
  KEY idx_pos_reservation_created_by_user (created_by_user_id),
  KEY idx_pos_reservation_updated_by_user (updated_by_user_id),
  CONSTRAINT fk_pos_reservation_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pos_reservation_sales_channel FOREIGN KEY (sales_channel_id) REFERENCES pos_sales_channel(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_member FOREIGN KEY (member_id) REFERENCES crm_member(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_order FOREIGN KEY (order_id) REFERENCES pos_order(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pos_reservation_settlement_payment FOREIGN KEY (settlement_payment_id) REFERENCES pos_payment(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_verified_by FOREIGN KEY (verified_by) REFERENCES org_employee(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_rejected_by FOREIGN KEY (rejected_by) REFERENCES org_employee(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES org_employee(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Reservasi customer sebelum diverifikasi dan dibentuk menjadi order POS';

CREATE TABLE IF NOT EXISTS pos_reservation_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  bundle_id BIGINT UNSIGNED NULL,
  product_division_id_snapshot BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  uom_id BIGINT UNSIGNED NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  hpp_standard_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  hpp_live_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  cogs_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  availability_mode_snapshot ENUM('AUTO','FORCE_AVAILABLE','FORCE_OUT','MANUAL_ALLOWED') NOT NULL DEFAULT 'AUTO',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_reservation_line_no (reservation_id, line_no),
  KEY idx_pos_reservation_line_product (product_id),
  KEY idx_pos_reservation_line_bundle (bundle_id),
  KEY idx_pos_reservation_line_operational_division (operational_division_id),
  CONSTRAINT fk_pos_reservation_line_header FOREIGN KEY (reservation_id) REFERENCES pos_reservation(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_reservation_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pos_reservation_line_bundle FOREIGN KEY (bundle_id) REFERENCES pos_product_bundle(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_line_product_division FOREIGN KEY (product_division_id_snapshot) REFERENCES mst_product_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_line_operational_division FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_reservation_line_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Snapshot produk reservasi sebelum dibentuk menjadi order POS';

CREATE TABLE IF NOT EXISTS pos_reservation_line_extra (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  reservation_line_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  extra_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  cost_amount_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_reservation_line_extra_no (reservation_line_id, line_no),
  KEY idx_pos_reservation_line_extra_header (reservation_id),
  KEY idx_pos_reservation_line_extra_extra (extra_id),
  CONSTRAINT fk_pos_reservation_line_extra_header FOREIGN KEY (reservation_id) REFERENCES pos_reservation(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_reservation_line_extra_line FOREIGN KEY (reservation_line_id) REFERENCES pos_reservation_line(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_reservation_line_extra_extra FOREIGN KEY (extra_id) REFERENCES mst_extra(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Snapshot extra reservasi';

CREATE TABLE IF NOT EXISTS pos_reservation_payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  link_status ENUM('OPEN','PARTIAL','APPLIED','VOID') NOT NULL DEFAULT 'OPEN',
  linked_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  voided_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_reservation_payment_payment (payment_id),
  UNIQUE KEY uk_pos_reservation_payment_pair (reservation_id, payment_id),
  KEY idx_pos_reservation_payment_header (reservation_id, link_status),
  CONSTRAINT fk_pos_reservation_payment_header FOREIGN KEY (reservation_id) REFERENCES pos_reservation(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_reservation_payment_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Tautan DP POS ke reservasi; sumber kas tetap pos_payment';

CREATE TABLE IF NOT EXISTS pos_reservation_state_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  event_code VARCHAR(60) NOT NULL,
  actor_employee_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pos_reservation_state_log_header (reservation_id, created_at),
  KEY idx_pos_reservation_state_log_actor (actor_employee_id),
  KEY idx_pos_reservation_state_log_user (actor_user_id),
  CONSTRAINT fk_pos_reservation_state_log_header FOREIGN KEY (reservation_id) REFERENCES pos_reservation(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_reservation_state_log_actor FOREIGN KEY (actor_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Jejak perubahan reservasi, DP, verifikasi, penolakan, dan pembatalan';

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.reservation.index',
  'Reservasi POS',
  'POS',
  'Input, verifikasi, DP, dan pemantauan reservasi customer sebelum menjadi order POS resmi.',
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
  'pos.reservation',
  'Reservasi',
  'ri-calendar-check-line',
  '/pos/reservations',
  page.id,
  COALESCE(self_order.sort_order, 3) + 1,
  1,
  'MAIN',
  self_order.parent_id
FROM sys_page page
LEFT JOIN sys_menu self_order ON self_order.menu_code = 'pos.self_order'
WHERE page.page_code = 'pos.reservation.index'
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
  1, 1, 1, 1, 1,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'pos.reservation.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'HOD', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'pos_reservation' AS check_key, COUNT(*) AS total_rows FROM pos_reservation
UNION ALL
SELECT 'pos_reservation_line', COUNT(*) FROM pos_reservation_line
UNION ALL
SELECT 'pos_reservation_payment', COUNT(*) FROM pos_reservation_payment
UNION ALL
SELECT 'sys_page.pos.reservation.index', COUNT(*) FROM sys_page WHERE page_code = 'pos.reservation.index'
UNION ALL
SELECT 'sys_menu.pos.reservation', COUNT(*) FROM sys_menu WHERE menu_code = 'pos.reservation';
