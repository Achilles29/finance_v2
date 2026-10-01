SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-19b_loyalty_redeem_rules_schema.sql
-- Tujuan :
-- 1) Tabel pos_redeem_rule — katalog hadiah redeem
-- 2) Tambah rule_id ke pos_redeem_transaction bila tabel ada
-- 3) Daftarkan halaman + menu + permissions
-- ============================================================

-- ─────────────────────────────────────────────────────────────
-- 1. Katalog reward redeem (DDL — tidak perlu dalam transaksi)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pos_redeem_rule (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_code           VARCHAR(60)     NOT NULL,
  rule_name           VARCHAR(150)    NOT NULL,
  description         TEXT            NULL,

  cost_type           ENUM('POINT','STAMP','BOTH') NOT NULL DEFAULT 'POINT',
  point_cost          DECIMAL(14,4)   NULL,
  stamp_campaign_id   BIGINT UNSIGNED NULL,
  stamp_cost          DECIMAL(14,4)   NULL,

  reward_type         ENUM('VOUCHER','PRODUCT','MERCHANDISE','DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','OTHER')
                      NOT NULL DEFAULT 'DISCOUNT_AMOUNT',
  voucher_campaign_id BIGINT UNSIGNED NULL,
  product_id          BIGINT UNSIGNED NULL,
  product_qty         DECIMAL(10,4)   NULL,
  discount_amount     DECIMAL(14,2)   NULL,
  discount_percent    DECIMAL(8,4)    NULL,
  reward_notes        VARCHAR(255)    NULL,

  min_spend_amount    DECIMAL(14,2)   NULL,
  stock_qty           INT             NULL     COMMENT 'NULL = tidak terbatas',
  redeemed_count      INT             NOT NULL DEFAULT 0,

  valid_from          DATE            NULL,
  valid_until         DATE            NULL,

  is_active           TINYINT(1)      NOT NULL DEFAULT 1,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME        NULL     ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_rr_code              (rule_code),
  KEY idx_rr_cost_type               (cost_type),
  KEY idx_rr_reward_type             (reward_type),
  KEY idx_rr_active                  (is_active),
  KEY idx_rr_stamp_campaign          (stamp_campaign_id),
  KEY idx_rr_voucher_campaign        (voucher_campaign_id),

  CONSTRAINT fk_rr_stamp_campaign    FOREIGN KEY (stamp_campaign_id)   REFERENCES pos_stamp_campaign(id),
  CONSTRAINT fk_rr_voucher_campaign  FOREIGN KEY (voucher_campaign_id) REFERENCES pos_voucher_campaign(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Katalog reward yang bisa ditebus member (poin/stamp)';

-- ─────────────────────────────────────────────────────────────
-- 2. Tambah rule_id ke pos_redeem_transaction
--    Hanya dieksekusi bila tabel ada DAN kolom belum ada.
--    Aman dijalankan ulang (idempotent).
-- ─────────────────────────────────────────────────────────────
SET @_tbl_ok = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_redeem_transaction'
);

SET @_col_ok = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'pos_redeem_transaction'
    AND COLUMN_NAME  = 'rule_id'
);

-- Bila tabel ada dan kolom belum ada → ALTER, selain itu → noop
SET @_sql = IF(
  @_tbl_ok > 0 AND @_col_ok = 0,
  'ALTER TABLE pos_redeem_transaction ADD COLUMN rule_id BIGINT UNSIGNED NULL AFTER redeem_type',
  'SELECT 1 AS noop'
);

PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

-- ─────────────────────────────────────────────────────────────
-- 3–5. DML: page, menu, permissions (dalam transaksi)
-- ─────────────────────────────────────────────────────────────
START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('loyalty.redeem_rule.index', 'Pengaturan Redeem', 'LOYALTY',
        'Katalog reward yang bisa ditukar dengan poin atau stamp oleh member', 1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'loyalty.redeem_rule',
  'Pengaturan Redeem',
  'ri-settings-3-line',
  '/loyalty/redeem-rules',
  p.id,
  6,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.redeem_rule.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon       = VALUES(icon),
  url        = VALUES(url),
  page_id    = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active  = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'loyalty.redeem_rule.index'
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
SELECT 'pos_redeem_rule'               AS item, COUNT(*) AS count
  FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pos_redeem_rule'
UNION ALL
SELECT 'sys_page loyalty.redeem_rule.index',  COUNT(*) FROM sys_page  WHERE page_code='loyalty.redeem_rule.index'
UNION ALL
SELECT 'sys_menu loyalty.redeem_rule',        COUNT(*) FROM sys_menu  WHERE menu_code='loyalty.redeem_rule'
UNION ALL
SELECT 'permissions seeded',                  COUNT(*) FROM auth_role_permission rp
  JOIN sys_page p ON p.id=rp.page_id WHERE p.page_code='loyalty.redeem_rule.index';
