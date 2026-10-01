SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A9 - POS Runtime Queue / Background Stock Commit
-- File   : 2026-05-30a_pos_runtime_job_queue.sql
-- Tujuan :
-- 1) Memindahkan posting stok POS keluar dari request confirm
-- 2) Menyediakan antrean job untuk stock commit + rebuild availability
-- 3) Menambah status QUEUED / PROCESSING / FAILED agar audit lebih jelas
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_order
  MODIFY COLUMN stock_commit_status ENUM('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED') NOT NULL DEFAULT 'PENDING';

ALTER TABLE pos_stock_commit
  MODIFY COLUMN commit_status ENUM('DRAFT','QUEUED','PROCESSING','COMMITTED','FAILED','PARTIAL_REVERSED','REVERSED','VOID') NOT NULL DEFAULT 'DRAFT';

CREATE TABLE IF NOT EXISTS pos_runtime_job (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_code VARCHAR(40) NOT NULL,
  job_type ENUM('ORDER_CONFIRM_STOCK_COMMIT') NOT NULL DEFAULT 'ORDER_CONFIRM_STOCK_COMMIT',
  status ENUM('QUEUED','PROCESSING','SUCCESS','FAILED','CANCELLED') NOT NULL DEFAULT 'QUEUED',
  order_id BIGINT UNSIGNED NOT NULL,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
  run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_by_employee_id BIGINT UNSIGNED NULL,
  payload_json LONGTEXT NULL,
  result_json LONGTEXT NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_runtime_job_code (job_code),
  KEY idx_pos_runtime_job_queue (job_type, status, run_after),
  KEY idx_pos_runtime_job_order (order_id, id),
  KEY idx_pos_runtime_job_snapshot (snapshot_id),
  CONSTRAINT fk_pos_runtime_job_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_runtime_job_snapshot FOREIGN KEY (snapshot_id) REFERENCES pos_stock_commit(id),
  CONSTRAINT fk_pos_runtime_job_actor FOREIGN KEY (created_by_employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'pos_runtime_job' AS table_name, COUNT(*) AS total_rows FROM pos_runtime_job;