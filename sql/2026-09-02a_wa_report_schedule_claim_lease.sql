-- Batch 47: atomic claim/lease untuk runner jadwal laporan WhatsApp.
-- Wajib dijalankan setelah 2026-08-15b_wa_report_schedule.sql (base table).
-- ADD COLUMN IF NOT EXISTS membuat migration aman dijalankan ulang.

ALTER TABLE wa_report_schedule
  ADD COLUMN IF NOT EXISTS run_claim_token CHAR(32) NULL AFTER last_error,
  ADD COLUMN IF NOT EXISTS run_claimed_at DATETIME NULL AFTER run_claim_token;
