-- Batch 53.2: align successful-login boundaries with failed_at DATETIME(6).
-- Execute this whole file with a delimiter-aware MySQL/MariaDB client (for
-- example: mysql database_name < this_file.sql). The DELIMITER directives are
-- client commands and must not be split or sent individually by an SQL runner.
--
-- The guard accepts only the known DATETIME or DATETIME(6), NOT NULL starting
-- contracts. A schema mismatch stops before the column definition is changed.

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_auth_session_log_login_at_usec_20260903b$$
CREATE PROCEDURE sp_auth_session_log_login_at_usec_20260903b()
BEGIN
    DECLARE v_table_count INT DEFAULT 0;
    DECLARE v_column_count INT DEFAULT 0;
    DECLARE v_data_type VARCHAR(64) DEFAULT NULL;
    DECLARE v_datetime_precision INT DEFAULT NULL;
    DECLARE v_is_nullable VARCHAR(3) DEFAULT NULL;
    DECLARE v_column_default VARCHAR(255) DEFAULT NULL;
    DECLARE v_extra VARCHAR(255) DEFAULT NULL;

    SELECT COUNT(*)
      INTO v_table_count
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'auth_session_log';

    IF v_table_count <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Batch 53.2 preflight failed: auth_session_log table is missing.';
    END IF;

    SELECT COUNT(*)
      INTO v_column_count
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'auth_session_log'
       AND COLUMN_NAME = 'login_at';

    IF v_column_count <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Batch 53.2 preflight failed: auth_session_log.login_at column is missing.';
    END IF;

    SELECT DATA_TYPE, DATETIME_PRECISION, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
      INTO v_data_type, v_datetime_precision, v_is_nullable, v_column_default, v_extra
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'auth_session_log'
       AND COLUMN_NAME = 'login_at';

    IF LOWER(v_data_type) <> 'datetime'
       OR v_datetime_precision IS NULL
       OR v_datetime_precision NOT IN (0, 6) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Batch 53.2 preflight failed: login_at must be DATETIME or DATETIME(6).';
    END IF;

    IF UPPER(v_is_nullable) <> 'NO' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Batch 53.2 preflight failed: login_at must already be NOT NULL.';
    END IF;

    IF NOT (
        v_datetime_precision = 6
        AND LOWER(REPLACE(COALESCE(v_column_default, ''), ' ', '')) = 'current_timestamp(6)'
        AND LOWER(COALESCE(v_extra, '')) NOT LIKE '%on update%'
    ) THEN
        ALTER TABLE auth_session_log
            MODIFY COLUMN login_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);
    END IF;
END$$

CALL sp_auth_session_log_login_at_usec_20260903b()$$
DROP PROCEDURE IF EXISTS sp_auth_session_log_login_at_usec_20260903b$$

DELIMITER ;
