-- Public GOWES VOL9 claims use issued vouchers, never a reusable public promo.
CREATE TABLE IF NOT EXISTS evt_gowes_participant (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    participant_name VARCHAR(150) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    voucher_issue_id BIGINT UNSIGNED NULL,
    claimed_at DATETIME NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_gowes_email (email),
    UNIQUE KEY uk_gowes_voucher (voucher_issue_id),
    CONSTRAINT fk_gowes_voucher FOREIGN KEY (voucher_issue_id) REFERENCES pos_voucher_issue(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evt_gowes_import (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_name VARCHAR(200) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    total_rows INT UNSIGNED NOT NULL,
    added_rows INT UNSIGNED NOT NULL,
    updated_rows INT UNSIGNED NOT NULL,
    duplicate_rows INT UNSIGNED NOT NULL,
    invalid_rows INT UNSIGNED NOT NULL,
    invalid_row_numbers TEXT NULL,
    imported_by BIGINT UNSIGNED NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pos_voucher_campaign
    (campaign_code, campaign_name, issue_mode, voucher_type, discount_value,
     max_discount_amount, min_spend_amount, valid_day_count, is_active)
SELECT 'GOWESVOL9-JF3X', 'GOWES VOL9 - JAJAN FEST 3X', 'MEMBER_TARGETED', 'PERCENT', 15, 0, 0, 7, 1
WHERE NOT EXISTS (SELECT 1 FROM pos_voucher_campaign WHERE campaign_code = 'GOWESVOL9-JF3X');
