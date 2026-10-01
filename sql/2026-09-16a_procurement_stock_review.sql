-- PREPARED_NOT_APPLIED. Apply manually to the intended instance; no stock changes.
-- Records the evidence reviewed by Purchase before forming SR/PO from a division request.
CREATE TABLE IF NOT EXISTS pur_division_stock_review (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 request_id BIGINT UNSIGNED NOT NULL,
 reviewed_by BIGINT UNSIGNED NOT NULL,
 reviewed_at DATETIME NOT NULL,
 source_ip VARCHAR(45) NOT NULL DEFAULT '',
 confirmed_with VARCHAR(150) NOT NULL DEFAULT '',
 reason VARCHAR(1000) NOT NULL DEFAULT '',
 snapshot_hash CHAR(64) NOT NULL,
 snapshot_json LONGTEXT NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uk_division_stock_review_request (request_id),
 KEY idx_division_stock_review_at (reviewed_at),
 CONSTRAINT fk_division_stock_review_request FOREIGN KEY (request_id) REFERENCES pur_division_request(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- No backfill: existing verified documents must not receive fabricated confirmations.
-- Keep history if rolling back code. Do not drop this table as a rollback shortcut.
SHOW COLUMNS FROM pur_division_stock_review;
SHOW INDEX FROM pur_division_stock_review;
