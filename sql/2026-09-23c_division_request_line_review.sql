-- Per-line decisions, preserving existing request/document identities and stock.
ALTER TABLE pur_division_request_line
 ADD COLUMN IF NOT EXISTS estimated_unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS review_status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
 ADD COLUMN IF NOT EXISTS reviewed_by BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS review_notes VARCHAR(1000) NULL;

-- Only untouched historical lines inherit the old document-wide decision.
UPDATE pur_division_request_line l JOIN pur_division_request r ON r.id=l.request_id
SET l.review_status=r.status
WHERE l.review_status='PENDING' AND l.reviewed_at IS NULL
 AND r.status IN ('VERIFIED','REJECTED','VOID');

ALTER TABLE pur_division_request_link
 ADD COLUMN IF NOT EXISTS request_line_id BIGINT UNSIGNED NULL;
ALTER TABLE pur_division_stock_review
 ADD COLUMN IF NOT EXISTS request_line_id BIGINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE pur_division_stock_review
 ADD UNIQUE INDEX IF NOT EXISTS uk_division_stock_review_line (request_id,request_line_id);
ALTER TABLE pur_division_stock_review
 DROP INDEX IF EXISTS uk_division_stock_review_request;
