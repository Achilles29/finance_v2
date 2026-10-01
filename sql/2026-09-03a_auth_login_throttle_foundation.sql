-- Batch 53: append-only failure audit used by web login throttling.
-- DDL only; safe to rerun and contains no seed/data mutation.
CREATE TABLE IF NOT EXISTS auth_login_failure (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  ip_address  VARCHAR(45) NOT NULL,
  failed_at   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_auth_login_failure_user_time (user_id, failed_at),
  KEY idx_auth_login_failure_ip_time (ip_address, failed_at),
  CONSTRAINT fk_auth_login_failure_user
    FOREIGN KEY (user_id) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Append-only failed web login audit for bounded throttling';
