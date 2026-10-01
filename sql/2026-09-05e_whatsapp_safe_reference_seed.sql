-- GAP-07: canonical, repeat-safe WhatsApp reference seed.
-- This replaces the data-seed portion of 2026-08-15b without replaying its
-- historical DDL. Schema must already come from the clean-install baseline or
-- an explicitly supported upgrade source.

INSERT INTO wa_template
  (template_code, name, category, body, sample_variables, is_active, created_by)
SELECT
  'REPORT_DEFAULT',
  'Default Laporan Otomatis',
  'INFO',
  '{{report_title}}\n\n{{report_body}}\n\nDikirim otomatis: {{generated_at}}',
  '{"report_title":"Omzet hari ini","report_body":"Total: Rp 1.000.000","generated_at":"15/08/2026 19:30"}',
  1,
  0
WHERE NOT EXISTS (
  SELECT 1 FROM wa_template WHERE BINARY template_code = BINARY 'REPORT_DEFAULT'
);

INSERT INTO wa_session (id)
SELECT 1
WHERE NOT EXISTS (
  SELECT 1 FROM wa_session WHERE id = 1
);
