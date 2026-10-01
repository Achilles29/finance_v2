SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mst_bank (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bank_code VARCHAR(10) NOT NULL,
  bank_name VARCHAR(150) NOT NULL,
  bank_alias VARCHAR(60) NULL,
  is_sharia TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_bank_code (bank_code),
  KEY idx_mst_bank_active_name (is_active, bank_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE fin_company_account
  ADD COLUMN IF NOT EXISTS bank_id BIGINT UNSIGNED NULL AFTER account_type;

ALTER TABLE org_employee
  ADD COLUMN IF NOT EXISTS bank_id BIGINT UNSIGNED NULL AFTER overtime_rate;

SET @fk_fin_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fin_company_account'
    AND CONSTRAINT_NAME = 'fk_fin_company_account_bank'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_fin := IF(
  @fk_fin_exists = 0,
  'ALTER TABLE fin_company_account ADD CONSTRAINT fk_fin_company_account_bank FOREIGN KEY (bank_id) REFERENCES mst_bank(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE st_fk_fin FROM @sql_fk_fin;
EXECUTE st_fk_fin;
DEALLOCATE PREPARE st_fk_fin;

SET @fk_emp_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'org_employee'
    AND CONSTRAINT_NAME = 'fk_org_employee_bank'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_emp := IF(
  @fk_emp_exists = 0,
  'ALTER TABLE org_employee ADD CONSTRAINT fk_org_employee_bank FOREIGN KEY (bank_id) REFERENCES mst_bank(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE st_fk_emp FROM @sql_fk_emp;
EXECUTE st_fk_emp;
DEALLOCATE PREPARE st_fk_emp;

INSERT INTO mst_bank (bank_code, bank_name, bank_alias, is_sharia, is_active) VALUES
  ('008', 'Bank Mandiri', 'MANDIRI', 0, 1),
  ('014', 'Bank Central Asia', 'BCA', 0, 1),
  ('002', 'Bank Rakyat Indonesia', 'BRI', 0, 1),
  ('113', 'Bank Jateng', 'BPD JATENG', 0, 1),
  ('009', 'Bank Negara Indonesia', 'BNI', 0, 1),
  ('011', 'Bank Danamon', 'DANAMON', 0, 1),
  ('451', 'Bank Syariah Indonesia', 'BSI', 1, 1)
ON DUPLICATE KEY UPDATE
  bank_name = VALUES(bank_name),
  bank_alias = VALUES(bank_alias),
  is_sharia = VALUES(is_sharia),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE org_employee e
LEFT JOIN mst_bank b ON (
  UPPER(TRIM(COALESCE(e.bank_name, ''))) = UPPER(TRIM(b.bank_alias))
  OR UPPER(TRIM(COALESCE(e.bank_name, ''))) = UPPER(TRIM(b.bank_name))
  OR (
      UPPER(TRIM(COALESCE(e.bank_name, ''))) LIKE CONCAT('%', UPPER(TRIM(b.bank_alias)), '%')
      AND b.bank_alias IS NOT NULL
  )
)
SET e.bank_id = b.id
WHERE e.bank_id IS NULL
  AND COALESCE(e.bank_name, '') <> '';

UPDATE fin_company_account a
LEFT JOIN mst_bank b ON (
  UPPER(TRIM(COALESCE(a.bank_name, ''))) = UPPER(TRIM(b.bank_alias))
  OR UPPER(TRIM(COALESCE(a.bank_name, ''))) = UPPER(TRIM(b.bank_name))
  OR (
      UPPER(TRIM(COALESCE(a.bank_name, ''))) LIKE CONCAT('%', UPPER(TRIM(b.bank_alias)), '%')
      AND b.bank_alias IS NOT NULL
  )
)
SET a.bank_id = b.id
WHERE a.bank_id IS NULL
  AND COALESCE(a.bank_name, '') <> '';

UPDATE org_employee e
LEFT JOIN mst_bank b ON b.id = e.bank_id
SET e.bank_name = COALESCE(b.bank_alias, b.bank_name, e.bank_name)
WHERE e.bank_id IS NOT NULL;

UPDATE fin_company_account a
LEFT JOIN mst_bank b ON b.id = a.bank_id
SET a.bank_name = COALESCE(b.bank_alias, b.bank_name, a.bank_name)
WHERE a.bank_id IS NOT NULL;

COMMIT;
