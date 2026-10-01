SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mst_variable_cost_default (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope_code ENUM('PRODUCT','COMPONENT') NOT NULL,
    default_percent DECIMAL(10,4) NOT NULL DEFAULT 20.0000,
    notes VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mst_variable_cost_default_scope (scope_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mst_variable_cost_default (scope_code, default_percent, notes, is_active)
VALUES
    ('PRODUCT', 20.0000, 'Default variable cost product', 1),
    ('COMPONENT', 20.0000, 'Default variable cost component', 1)
ON DUPLICATE KEY UPDATE
    default_percent = VALUES(default_percent),
    notes = VALUES(notes),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP;

COMMIT;
