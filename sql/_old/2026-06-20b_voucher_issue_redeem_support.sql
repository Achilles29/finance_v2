-- ============================================================
-- Migration: 2026-06-20b
-- Tujuan   : Support voucher standalone dari proses redeem rule
--            (tidak wajib punya voucher_campaign)
-- ============================================================

-- 1. Drop FK campaign_id (agar bisa dijadikan nullable)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pos_voucher_issue'
      AND CONSTRAINT_NAME = 'fk_pos_voucher_issue_campaign'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE pos_voucher_issue DROP FOREIGN KEY fk_pos_voucher_issue_campaign',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Ubah campaign_id menjadi nullable
ALTER TABLE pos_voucher_issue
    MODIFY COLUMN campaign_id BIGINT UNSIGNED NULL;

-- 3. Re-add FK (nullable FK valid di MySQL: hanya berlaku jika nilai != NULL)
SET @fk_exists2 = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pos_voucher_issue'
      AND CONSTRAINT_NAME = 'fk_pos_voucher_issue_campaign'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql2 = IF(@fk_exists2 = 0,
    'ALTER TABLE pos_voucher_issue ADD CONSTRAINT fk_pos_voucher_issue_campaign FOREIGN KEY (campaign_id) REFERENCES pos_voucher_campaign(id)',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- 4. Tambah kolom redeem_rule_id (FK ke pos_redeem_rule)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pos_voucher_issue'
      AND COLUMN_NAME  = 'redeem_rule_id'
);
SET @sql3 = IF(@col_exists = 0,
    'ALTER TABLE pos_voucher_issue ADD COLUMN redeem_rule_id BIGINT UNSIGNED NULL AFTER campaign_id',
    'SELECT 1'
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 5. Tambah kolom min_spend_amount (disimpan snapshot dari rule)
SET @col2_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'pos_voucher_issue'
      AND COLUMN_NAME  = 'min_spend_amount'
);
SET @sql4 = IF(@col2_exists = 0,
    'ALTER TABLE pos_voucher_issue ADD COLUMN min_spend_amount DECIMAL(14,2) NULL AFTER percent_snapshot',
    'SELECT 1'
);
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- Verifikasi
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'pos_voucher_issue'
  AND COLUMN_NAME IN ('campaign_id','redeem_rule_id','min_spend_amount')
ORDER BY ORDINAL_POSITION;
