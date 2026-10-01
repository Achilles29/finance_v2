SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-22b_pos_cashier_voucher_usage_report_foundation.sql
-- Tujuan :
-- 1) Mencatat pemakaian voucher per nota POS, termasuk promo voucher umum
-- 2) Menyediakan halaman laporan Pemakaian Voucher di Member & Promo
-- 3) Membackfill jejak redeem voucher lama tanpa mengubah nilai order lama
--
-- Catatan:
-- - Voucher tetap satu kali pakai bila berasal dari voucher issue.
-- - Nilai applied_amount selalu nilai potongan yang benar-benar dipakai di nota,
--   bukan nominal muka voucher bila nominal voucher lebih besar dari tagihan.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_voucher_usage (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_key VARCHAR(100) NOT NULL,
  voucher_kind ENUM('ISSUE','CAMPAIGN') NOT NULL DEFAULT 'ISSUE',
  voucher_issue_id BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  voucher_code VARCHAR(60) NULL,
  voucher_label VARCHAR(150) NULL,
  member_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  cashier_employee_id BIGINT UNSIGNED NULL,
  face_value_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  face_value_percent DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  usage_status ENUM('APPLIED','REVERSED','VOID') NOT NULL DEFAULT 'APPLIED',
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_voucher_usage_payment_source (payment_id, source_key),
  KEY idx_pos_voucher_usage_used_at (used_at, usage_status),
  KEY idx_pos_voucher_usage_issue (voucher_issue_id, used_at),
  KEY idx_pos_voucher_usage_campaign (campaign_id, used_at),
  KEY idx_pos_voucher_usage_order (order_id),
  KEY idx_pos_voucher_usage_member (member_id),
  KEY idx_pos_voucher_usage_cashier (cashier_employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jejak redeem lama dipindahkan ke log baru agar tetap muncul di laporan.
INSERT INTO pos_voucher_usage (
  source_key,
  voucher_kind,
  voucher_issue_id,
  campaign_id,
  voucher_code,
  voucher_label,
  member_id,
  order_id,
  payment_id,
  cashier_employee_id,
  face_value_amount,
  face_value_percent,
  applied_amount,
  usage_status,
  used_at,
  notes,
  created_at
)
SELECT
  CONCAT('ISSUE:', r.voucher_issue_id),
  'ISSUE',
  r.voucher_issue_id,
  vi.campaign_id,
  vi.voucher_code,
  COALESCE(NULLIF(vc.campaign_name, ''), NULLIF(vi.notes, ''), vi.voucher_code, 'Voucher'),
  COALESCE(r.member_id, vi.member_id),
  r.order_id,
  r.payment_id,
  p.cashier_employee_id,
  COALESCE(vi.amount_snapshot, 0),
  COALESCE(vi.percent_snapshot, 0),
  COALESCE(r.redeem_amount, 0),
  'APPLIED',
  COALESCE(r.redeemed_at, vi.redeemed_at, NOW()),
  COALESCE(r.notes, 'Backfill redeem voucher lama'),
  COALESCE(r.redeemed_at, vi.redeemed_at, NOW())
FROM pos_voucher_redemption r
LEFT JOIN pos_voucher_issue vi ON vi.id = r.voucher_issue_id
LEFT JOIN pos_voucher_campaign vc ON vc.id = vi.campaign_id
LEFT JOIN pos_payment p ON p.id = r.payment_id
WHERE NOT EXISTS (
  SELECT 1
  FROM pos_voucher_usage u
  WHERE u.source_key = CONCAT('ISSUE:', r.voucher_issue_id)
    AND u.payment_id <=> r.payment_id
);

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'loyalty.voucher_usage.index',
  'Laporan Pemakaian Voucher',
  'LOYALTY',
  'Riwayat voucher dan promo voucher yang dipakai pada transaksi POS berikut nota, customer, kasir, dan nilai potongannya.',
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
  'loyalty.voucher_usage',
  'Pemakaian Voucher',
  'ri-coupon-2-line',
  '/loyalty/voucher-usages',
  page.id,
  5,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE page.page_code = 'loyalty.voucher_usage.index'
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
  1,
  0,
  0,
  0,
  CASE WHEN role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'loyalty.voucher_usage.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'KASIR', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'pos_voucher_usage' AS check_key, COUNT(*) AS total_rows
FROM pos_voucher_usage
UNION ALL
SELECT 'sys_page.loyalty.voucher_usage.index', COUNT(*)
FROM sys_page
WHERE page_code = 'loyalty.voucher_usage.index'
UNION ALL
SELECT 'sys_menu.loyalty.voucher_usage', COUNT(*)
FROM sys_menu
WHERE menu_code = 'loyalty.voucher_usage';
