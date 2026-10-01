-- Classification metadata only. No balance/amount/date updates or historic backfill.
-- NULL means legacy/unreviewed; it must not be guessed from free-text notes.
ALTER TABLE fin_account_mutation_log
  ADD COLUMN IF NOT EXISTS report_category VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS client_request_key VARCHAR(64) NULL DEFAULT NULL,
  ADD UNIQUE INDEX IF NOT EXISTS uq_fin_mutation_request (account_id, client_request_key);

ALTER TABLE fin_cash_reconciliation_line
  ADD COLUMN IF NOT EXISTS report_category VARCHAR(32) NULL DEFAULT NULL;

ALTER TABLE fin_revenue_reconciliation_line
  ADD COLUMN IF NOT EXISTS report_category VARCHAR(32) NULL DEFAULT NULL;

-- Rollback: previous code ignores these nullable columns. Keep the metadata
-- and unique index when rolling back application code; do not erase audit data.
