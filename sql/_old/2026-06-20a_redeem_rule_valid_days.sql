SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-20a_redeem_rule_valid_days.sql
-- Tujuan : Ganti valid_from + valid_until di pos_redeem_rule
--          menjadi valid_days (INT, jumlah hari berlaku)
-- ============================================================

-- Tambah kolom baru (idempotent)
SET @_col = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'pos_redeem_rule'
    AND COLUMN_NAME  = 'valid_days'
);
SET @_add = IF(@_col = 0,
  'ALTER TABLE pos_redeem_rule ADD COLUMN valid_days INT NULL COMMENT ''Jumlah hari berlaku sejak diterbitkan; NULL = selamanya'' AFTER stock_qty',
  'SELECT 1'
);
PREPARE _s FROM @_add; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Hapus kolom valid_from (idempotent)
SET @_cf = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'pos_redeem_rule'
    AND COLUMN_NAME  = 'valid_from'
);
SET @_drop_from = IF(@_cf > 0,
  'ALTER TABLE pos_redeem_rule DROP COLUMN valid_from',
  'SELECT 1'
);
PREPARE _s FROM @_drop_from; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Hapus kolom valid_until (idempotent)
SET @_cu = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'pos_redeem_rule'
    AND COLUMN_NAME  = 'valid_until'
);
SET @_drop_until = IF(@_cu > 0,
  'ALTER TABLE pos_redeem_rule DROP COLUMN valid_until',
  'SELECT 1'
);
PREPARE _s FROM @_drop_until; EXECUTE _s; DEALLOCATE PREPARE _s;

-- Verifikasi
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'pos_redeem_rule'
  AND COLUMN_NAME IN ('valid_days', 'valid_from', 'valid_until')
ORDER BY COLUMN_NAME;
