SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-19a_loyalty_redeem_module.sql
-- Tujuan :
-- 1) Buat tabel pos_redeem_transaction sebagai log terpusat
--    semua aktivitas redeem (poin, stamp, voucher) oleh member
-- 2) Daftarkan halaman loyalty.redeem.index di sys_page
-- 3) Tambah menu "Redeem" di bawah grp.loyalty
-- 4) Set permissions untuk role yang relevan
-- ============================================================

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────
-- 1. Tabel log redeem terpusat
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pos_redeem_transaction (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  redeem_no        VARCHAR(30)     NOT NULL                   COMMENT 'Nomor unik transaksi redeem, format RDM-YYYYMMDD-NNNN',
  member_id        BIGINT UNSIGNED NOT NULL,
  redeem_type      ENUM('POINT','STAMP','VOUCHER') NOT NULL   COMMENT 'Jenis aset yang ditebus',

  -- Untuk redeem_type = POINT
  point_ledger_id  BIGINT UNSIGNED NULL                       COMMENT 'FK ke pos_point_ledger',
  points_used      DECIMAL(14,4)   NULL                       COMMENT 'Jumlah poin yang dikurangi',

  -- Untuk redeem_type = STAMP
  stamp_ledger_id  BIGINT UNSIGNED NULL                       COMMENT 'FK ke pos_stamp_ledger',
  stamps_used      DECIMAL(14,4)   NULL                       COMMENT 'Jumlah stamp yang dikurangi',

  -- Untuk redeem_type = VOUCHER
  voucher_issue_id BIGINT UNSIGNED NULL                       COMMENT 'FK ke pos_voucher_issue',
  voucher_code     VARCHAR(80)     NULL                       COMMENT 'Kode voucher snapshot saat ditebus',

  -- Reward / benefit yang diterima member
  reward_type      ENUM('DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','VOUCHER_ISSUED','CUSTOM') NULL,
  reward_desc      VARCHAR(255)    NULL                       COMMENT 'Deskripsi singkat reward',
  reward_amount    DECIMAL(14,2)   NULL                       COMMENT 'Nilai moneter reward bila ada',

  -- Metadata
  notes            TEXT            NULL,
  redeemed_by      BIGINT UNSIGNED NULL                       COMMENT 'FK ke auth_user (operator yang proses)',
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_redeem_no (redeem_no),
  KEY idx_prt_member   (member_id),
  KEY idx_prt_type     (redeem_type),
  KEY idx_prt_created  (created_at),
  KEY idx_prt_voucher  (voucher_issue_id),

  CONSTRAINT fk_prt_member FOREIGN KEY (member_id) REFERENCES crm_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Log terpusat semua transaksi redeem loyalty member (poin/stamp/voucher)';

-- ─────────────────────────────────────────────────────────────
-- 2. Halaman sys_page
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('loyalty.redeem.index', 'Redeem Poin / Voucher / Stamp', 'LOYALTY',
        'Proses penukaran (redeem) poin, stamp, dan voucher milik member', 1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 3. Menu sidebar di bawah grp.loyalty (sort_order 5)
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'loyalty.redeem',
  'Redeem',
  'ri-gift-2-line',
  '/loyalty/redeem',
  p.id,
  5,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.redeem.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon       = VALUES(icon),
  url        = VALUES(url),
  page_id    = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active  = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 4. Permissions — KASIR & ke atas bisa view+create, delete hanya ADMIN+
-- ─────────────────────────────────────────────────────────────
INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT
  r.id,
  p.id,
  1,
  1,
  0,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'loyalty.redeem.index'
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','KASIR')
ON DUPLICATE KEY UPDATE
  can_view   = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit   = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ─────────────────────────────────────────────────────────────
-- Verifikasi
-- ─────────────────────────────────────────────────────────────
SELECT 'pos_redeem_transaction' AS item, COUNT(*) AS count FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'pos_redeem_transaction'
UNION ALL
SELECT 'sys_page loyalty.redeem.index', COUNT(*) FROM sys_page WHERE page_code = 'loyalty.redeem.index'
UNION ALL
SELECT 'sys_menu loyalty.redeem', COUNT(*) FROM sys_menu WHERE menu_code = 'loyalty.redeem'
UNION ALL
SELECT 'permissions seeded', COUNT(*) FROM auth_role_permission rp
  JOIN sys_page p ON p.id = rp.page_id WHERE p.page_code = 'loyalty.redeem.index';
