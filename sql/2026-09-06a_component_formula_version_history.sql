-- Immutable formula version history for the canonical Production formula editor.
-- Safe to run repeatedly through tools/db/migration_runner.php.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mst_component_formula_version` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `component_id` BIGINT UNSIGNED NOT NULL,
  `version_no` INT UNSIGNED NOT NULL,
  `change_action` ENUM('BASELINE','REPLACE') NOT NULL,
  `formula_revision` CHAR(64) NOT NULL,
  `line_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `source_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mcfv_component_version` (`component_id`, `version_no`),
  KEY `idx_mcfv_component_created` (`component_id`, `created_at`, `id`),
  KEY `idx_mcfv_actor` (`actor_user_id`),
  CONSTRAINT `fk_mcfv_component` FOREIGN KEY (`component_id`) REFERENCES `mst_component` (`id`),
  CONSTRAINT `fk_mcfv_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `auth_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable snapshots of component formulas';

CREATE TABLE IF NOT EXISTS `mst_component_formula_version_line` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formula_version_id` BIGINT UNSIGNED NOT NULL,
  `original_line_id` BIGINT UNSIGNED NULL,
  `line_no` INT UNSIGNED NOT NULL,
  `line_type` ENUM('MATERIAL','COMPONENT') NOT NULL,
  `material_id` BIGINT UNSIGNED NULL,
  `material_item_id` BIGINT UNSIGNED NULL,
  `sub_component_id` BIGINT UNSIGNED NULL,
  `source_division_id` BIGINT UNSIGNED NULL,
  `uom_id` BIGINT UNSIGNED NULL,
  `qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `notes` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mcfvl_version_line` (`formula_version_id`, `line_no`, `id`),
  KEY `idx_mcfvl_material` (`material_id`),
  KEY `idx_mcfvl_component` (`sub_component_id`),
  CONSTRAINT `fk_mcfvl_version` FOREIGN KEY (`formula_version_id`) REFERENCES `mst_component_formula_version` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable lines belonging to a component formula version';
