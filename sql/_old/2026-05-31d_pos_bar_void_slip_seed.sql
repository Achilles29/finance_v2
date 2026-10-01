SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO pos_printer_template (
  template_code,
  template_name,
  document_type,
  template_payload,
  is_default,
  is_active
)
SELECT
  'VOID_BAR',
  'VOID BAR',
  'VOID_SLIP',
  src.template_payload,
  0,
  1
FROM pos_printer_template src
WHERE src.template_code = 'TPL-BAR'
LIMIT 1
ON DUPLICATE KEY UPDATE
  template_name = VALUES(template_name),
  document_type = VALUES(document_type),
  template_payload = VALUES(template_payload),
  is_default = VALUES(is_default),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT id, template_code, template_name, document_type, is_default, is_active
FROM pos_printer_template
WHERE template_code = 'VOID_BAR';
