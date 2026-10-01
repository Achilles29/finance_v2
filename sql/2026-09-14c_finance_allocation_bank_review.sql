-- PREPARED ONLY: run via IDE after review/backup. No cash posting or historic backfill.
-- Prerequisites: 2026-09-13a, 2026-09-14a and 2026-09-14b.
CREATE TABLE IF NOT EXISTS fin_receipt_distribution (
 receipt_id BIGINT UNSIGNED PRIMARY KEY, revision INT UNSIGNED NOT NULL DEFAULT 0,
 request_key CHAR(32) NOT NULL, payload_hash CHAR(64) NOT NULL,
 notes VARCHAR(255) NOT NULL, updated_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fin_receipt_allocation (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, receipt_id BIGINT UNSIGNED NOT NULL,
 settlement_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(18,2) NOT NULL,
 revision INT UNSIGNED NOT NULL, active_key VARCHAR(80) NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_fin_receipt_allocation(active_key), KEY ix_fin_receipt_alloc_case(settlement_id,active_key),
 KEY ix_fin_receipt_alloc_receipt(receipt_id,active_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fin_plan_allocation (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, plan_id BIGINT UNSIGNED NOT NULL,
 mutation_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(18,2) NOT NULL,
 active_key VARCHAR(80) NULL, request_key CHAR(32) NOT NULL, notes VARCHAR(255) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 unlinked_by BIGINT UNSIGNED NULL, unlinked_at DATETIME NULL, unlink_reason VARCHAR(255) NULL,
 UNIQUE KEY uq_fin_plan_alloc_pair(active_key), UNIQUE KEY uq_fin_plan_alloc_request(request_key),
 KEY ix_fin_plan_alloc_mutation(mutation_id,active_key), KEY ix_fin_plan_alloc_plan(plan_id,active_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fin_bank_statement_row (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, account_id BIGINT UNSIGNED NOT NULL,
 row_hash CHAR(64) NOT NULL, file_hash CHAR(64) NOT NULL, statement_date DATE NOT NULL,
 reference_no VARCHAR(100) NOT NULL, description VARCHAR(255) NOT NULL,
 direction VARCHAR(3) NOT NULL, amount DECIMAL(18,2) NOT NULL,
 mutation_id BIGINT UNSIGNED NULL, active_mutation_id BIGINT UNSIGNED NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_fin_bank_row(account_id,row_hash), UNIQUE KEY uq_fin_bank_match(active_mutation_id),
 KEY ix_fin_bank_date(account_id,statement_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE fin_revenue_reconciliation_line
 ADD COLUMN IF NOT EXISTS counter_account_id BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS counter_payment_method_id BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS counter_mutation_id BIGINT UNSIGNED NULL;
-- Original enum does not allow TRANSFER. Add without rewriting any business values.
ALTER TABLE fin_revenue_reconciliation_line MODIFY COLUMN resolution_type ENUM('NONE','IN','OUT','TRANSFER') NOT NULL DEFAULT 'NONE';
-- Keep tables/history on rollback. Revert application code only; do not drop data.
