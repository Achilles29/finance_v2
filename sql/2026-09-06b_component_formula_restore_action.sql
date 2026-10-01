-- Permit an explicit immutable-history restore event after the initial
-- component-formula version-history migration has created both tables.
SET NAMES utf8mb4;

ALTER TABLE `mst_component_formula_version`
  MODIFY COLUMN `change_action` ENUM('BASELINE','REPLACE','RESTORE') NOT NULL;
