-- ============================================================
-- Fondasi alur transaksi inventori: item -> material
-- Konsisten dengan source division operasional
-- ============================================================
SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_item_material_source_map (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  item_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  source_division_id BIGINT UNSIGNED NOT NULL,
  qty_material_per_item DECIMAL(18,6) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_item_material_source_map (item_id, material_id, source_division_id),
  KEY idx_inv_item_material_source_map_item (item_id),
  KEY idx_inv_item_material_source_map_material (material_id),
  KEY idx_inv_item_material_source_map_source_div (source_division_id),
  CONSTRAINT fk_inv_item_material_source_map_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_item_material_source_map_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_item_material_source_map_source_div FOREIGN KEY (source_division_id) REFERENCES mst_operational_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_item_material_txn (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  trx_no VARCHAR(40) NOT NULL,
  trx_date DATE NOT NULL,
  source_division_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  qty_item DECIMAL(18,4) NOT NULL,
  qty_material DECIMAL(18,4) NOT NULL,
  conversion_factor DECIMAL(18,6) NOT NULL,
  ref_type VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
  ref_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_item_material_txn_no (trx_no),
  KEY idx_inv_item_material_txn_date (trx_date),
  KEY idx_inv_item_material_txn_source_div (source_division_id),
  KEY idx_inv_item_material_txn_item (item_id),
  KEY idx_inv_item_material_txn_material (material_id),
  CONSTRAINT fk_inv_item_material_txn_source_div FOREIGN KEY (source_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_item_material_txn_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_item_material_txn_material FOREIGN KEY (material_id) REFERENCES mst_material(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed awal mapping otomatis dari item material existing
INSERT INTO inv_item_material_source_map (
  item_id,
  material_id,
  source_division_id,
  qty_material_per_item,
  notes,
  is_active
)
SELECT
  i.id AS item_id,
  i.material_id,
  od.id AS source_division_id,
  1.000000 AS qty_material_per_item,
  'Seed awal auto dari item.is_material=1',
  1
FROM mst_item i
JOIN mst_operational_division od ON od.is_active = 1
WHERE i.is_material = 1
  AND i.material_id IS NOT NULL
ON DUPLICATE KEY UPDATE
  qty_material_per_item = VALUES(qty_material_per_item),
  is_active = VALUES(is_active);

COMMIT;

SELECT 'inv_item_material_source_map' AS table_name, COUNT(*) AS total_rows FROM inv_item_material_source_map
UNION ALL
SELECT 'inv_item_material_txn', COUNT(*) FROM inv_item_material_txn;
