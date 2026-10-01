SET NAMES utf8mb4;

-- A3-4: explicit page aliases reuse canonical permissions without copying any
-- role/user permission rows. Execute with a delimiter-aware MySQL/MariaDB client.
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_a3_page_alias_registry_assert_20260904b$$
CREATE PROCEDURE sp_a3_page_alias_registry_assert_20260904b(IN p_phase VARCHAR(8))
BEGIN
  DECLARE v_issues INT DEFAULT 0;

  IF p_phase = 'PRE' THEN
    SELECT COUNT(*) INTO v_issues
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'sys_page'
      AND table_type = 'BASE TABLE';

    IF v_issues <> 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-4 preflight failed: sys_page base table is missing';
    END IF;

    -- Seed aliases may not also be page registry codes, whether those pages are
    -- active or inactive. This keeps runtime resolution canonical-only.
    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'attendance.schedules.v2.index' alias_code
      UNION ALL SELECT 'my.schedule.index'
      UNION ALL SELECT 'pos.stock.commit.audit.index'
      UNION ALL SELECT 'product.monitoring.availability.index'
      UNION ALL SELECT 'production.component.lot.index'
      UNION ALL SELECT 'production.component.reconcile.index'
      UNION ALL SELECT 'purchase.account.index'
      UNION ALL SELECT 'purchase.stock.division.lot.index'
      UNION ALL SELECT 'purchase.stock.opening.index'
      UNION ALL SELECT 'purchase.stock.warehouse.lot.index'
    ) expected_alias
    JOIN sys_page page ON page.page_code = expected_alias.alias_code;

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-4 preflight failed: alias_code collides with sys_page.page_code';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'sys_page_alias'
      AND table_type = 'BASE TABLE';

    IF v_issues = 1 THEN
      SELECT COUNT(*) INTO v_issues
      FROM sys_page_alias alias
      JOIN sys_page page ON page.page_code = alias.alias_code
      WHERE alias.is_active = 1;

      IF v_issues > 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'A3-4 preflight failed: active alias_code collides with sys_page.page_code';
      END IF;
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'attendance.schedules.index' page_code
      UNION ALL SELECT 'my.attendance.index'
      UNION ALL SELECT 'pos.stock.live.index'
      UNION ALL SELECT 'product.availability'
      UNION ALL SELECT 'production.component.lots'
      UNION ALL SELECT 'production.component.daily.index'
      UNION ALL SELECT 'finance.account.index'
      UNION ALL SELECT 'purchase.stock.division.index'
      UNION ALL SELECT 'purchase.stock.opening.warehouse.index'
      UNION ALL SELECT 'purchase.stock.warehouse.index'
    ) required_page
    LEFT JOIN sys_page page
      ON page.page_code = required_page.page_code AND page.is_active = 1
    WHERE page.id IS NULL;

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-4 preflight failed: required active canonical page is missing';
    END IF;
  ELSEIF p_phase = 'POST' THEN
    SELECT COUNT(*) INTO v_issues
    FROM sys_page_alias alias
    LEFT JOIN sys_page page ON page.id = alias.page_id
    WHERE alias.is_active = 1
      AND (page.id IS NULL OR COALESCE(page.is_active, 0) <> 1);

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-4 postflight failed: active alias points to missing or inactive page';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM sys_page_alias alias
    JOIN sys_page page ON page.page_code = alias.alias_code
    WHERE alias.is_active = 1;

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-4 postflight failed: active alias_code collides with sys_page.page_code';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'attendance.schedules.v2.index' alias_code, 'attendance.schedules.index' canonical_page_code
      UNION ALL SELECT 'my.schedule.index', 'my.attendance.index'
      UNION ALL SELECT 'pos.stock.commit.audit.index', 'pos.stock.live.index'
      UNION ALL SELECT 'product.monitoring.availability.index', 'product.availability'
      UNION ALL SELECT 'production.component.lot.index', 'production.component.lots'
      UNION ALL SELECT 'production.component.reconcile.index', 'production.component.daily.index'
      UNION ALL SELECT 'purchase.account.index', 'finance.account.index'
      UNION ALL SELECT 'purchase.stock.division.lot.index', 'purchase.stock.division.index'
      UNION ALL SELECT 'purchase.stock.opening.index', 'purchase.stock.opening.warehouse.index'
      UNION ALL SELECT 'purchase.stock.warehouse.lot.index', 'purchase.stock.warehouse.index'
    ) expected
    LEFT JOIN sys_page_alias alias
      ON alias.alias_code = expected.alias_code AND alias.is_active = 1
    LEFT JOIN sys_page page
      ON page.id = alias.page_id
      AND page.page_code = expected.canonical_page_code
      AND page.is_active = 1
    WHERE alias.id IS NULL OR page.id IS NULL;

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-4 postflight failed: seeded alias mapping is incomplete or invalid';
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'A3-4 assertion failed: unknown phase';
  END IF;
END$$

DELIMITER ;

CALL sp_a3_page_alias_registry_assert_20260904b('PRE');

CREATE TABLE IF NOT EXISTS sys_page_alias (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  alias_code   VARCHAR(100)    NOT NULL,
  page_id      BIGINT UNSIGNED NOT NULL,
  description  VARCHAR(255)    NULL,
  is_active    TINYINT(1)      NOT NULL DEFAULT 1,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sys_page_alias_code (alias_code),
  KEY idx_sys_page_alias_page (page_id),
  KEY idx_sys_page_alias_active (is_active),
  CONSTRAINT fk_sys_page_alias_page FOREIGN KEY (page_id) REFERENCES sys_page(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Alias page code menuju permission page kanonis';

-- alias_code is globally UNIQUE across active and inactive rows. A rerun
-- reactivates and remaps the same row rather than creating another identity.

START TRANSACTION;

INSERT INTO sys_page_alias (alias_code, page_id, description, is_active)
SELECT seed.alias_code, page.id, seed.description, 1
FROM (
  SELECT 'attendance.schedules.v2.index' alias_code, 'attendance.schedules.index' canonical_page_code, 'Alias jadwal attendance v2' description
  UNION ALL SELECT 'my.schedule.index', 'my.attendance.index', 'Alias jadwal portal pegawai'
  UNION ALL SELECT 'pos.stock.commit.audit.index', 'pos.stock.live.index', 'Alias audit commit stok POS'
  UNION ALL SELECT 'product.monitoring.availability.index', 'product.availability', 'Alias monitoring ketersediaan produk'
  UNION ALL SELECT 'production.component.lot.index', 'production.component.lots', 'Alias lot komponen produksi'
  UNION ALL SELECT 'production.component.reconcile.index', 'production.component.daily.index', 'Alias rekonsiliasi komponen produksi'
  UNION ALL SELECT 'purchase.account.index', 'finance.account.index', 'Alias akun pembelian'
  UNION ALL SELECT 'purchase.stock.division.lot.index', 'purchase.stock.division.index', 'Alias lot stok divisi'
  UNION ALL SELECT 'purchase.stock.opening.index', 'purchase.stock.opening.warehouse.index', 'Alias stok awal gudang'
  UNION ALL SELECT 'purchase.stock.warehouse.lot.index', 'purchase.stock.warehouse.index', 'Alias lot stok gudang'
) seed
JOIN sys_page page
  ON page.page_code = seed.canonical_page_code AND page.is_active = 1
ON DUPLICATE KEY UPDATE
  page_id = VALUES(page_id),
  description = VALUES(description),
  is_active = 1,
  updated_at = CURRENT_TIMESTAMP;

CALL sp_a3_page_alias_registry_assert_20260904b('POST');
COMMIT;

DROP PROCEDURE IF EXISTS sp_a3_page_alias_registry_assert_20260904b;

-- Read-only post-apply summary. active_aliases may grow after this migration;
-- invalid_active_aliases must remain zero.
SELECT 'active_aliases' metric, COUNT(*) total
FROM sys_page_alias alias
WHERE alias.is_active = 1
UNION ALL
SELECT 'invalid_active_aliases', COUNT(*)
FROM sys_page_alias alias
LEFT JOIN sys_page page ON page.id = alias.page_id
WHERE alias.is_active = 1 AND (page.id IS NULL OR COALESCE(page.is_active, 0) <> 1);
