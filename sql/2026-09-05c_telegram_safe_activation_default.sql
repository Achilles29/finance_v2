-- Keep Telegram disabled until the setup assistant prerequisites are complete.
-- Preserve an operator-edited switch (identified by non-NULL updated_by).
SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO tg_setting (setting_key, setting_value, description, updated_by)
VALUES ('telegram.enabled', '0', 'Master switch Telegram bot; default OFF until setup prerequisites are verified', NULL)
ON DUPLICATE KEY UPDATE
  setting_value = IF(updated_by IS NULL, VALUES(setting_value), setting_value),
  description = VALUES(description);

COMMIT;
