-- Preserve the actual WhatsApp session state after a 401/logout event.
-- Without this enum member MariaDB truncates LOGGED_OUT and leaves a stale
-- DISCONNECTED status, which makes QR recovery diagnostics misleading.
ALTER TABLE wa_session
  MODIFY status ENUM('CONNECTED', 'DISCONNECTED', 'WAITING_QR', 'LOGGED_OUT', 'UNKNOWN')
  NOT NULL DEFAULT 'UNKNOWN';
