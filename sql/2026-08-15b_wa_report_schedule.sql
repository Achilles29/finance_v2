-- Jadwal laporan WhatsApp berbasis data database.
-- Controller Whatsapp::ensureSchema() juga membuat tabel ini otomatis,
-- file ini disediakan agar perubahan skema terdokumentasi dan bisa dijalankan manual.

CREATE TABLE IF NOT EXISTS wa_report_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  report_type ENUM('OMZET_TODAY','PURCHASE_TODAY','ADJUSTMENT_TODAY','PO_SR_TODAY') NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  send_time TIME NOT NULL,
  date_offset_days SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) DEFAULT NULL,
  last_run_at DATETIME DEFAULT NULL,
  last_sent_at DATETIME DEFAULT NULL,
  last_sent_date DATE DEFAULT NULL,
  last_status ENUM('SENT','FAILED','SKIPPED') DEFAULT NULL,
  last_error VARCHAR(500) DEFAULT NULL,
  run_claim_token CHAR(32) DEFAULT NULL,
  run_claimed_at DATETIME DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_active_time (is_active, send_time),
  KEY idx_last_sent_date (last_sent_date),
  KEY idx_template (template_id),
  KEY idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO wa_template (template_code, name, category, body, sample_variables, is_active, created_by)
SELECT
  'REPORT_DEFAULT',
  'Default Laporan Otomatis',
  'INFO',
  '{{report_title}}

{{report_body}}

Dikirim otomatis: {{generated_at}}',
  '{"report_title":"Omzet hari ini","report_body":"Total: Rp 1.000.000","generated_at":"15/08/2026 19:30"}',
  1,
  0
WHERE NOT EXISTS (
  SELECT 1 FROM wa_template WHERE template_code = 'REPORT_DEFAULT'
);
