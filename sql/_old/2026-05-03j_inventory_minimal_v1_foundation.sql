SET NAMES utf8mb4;

-- ============================================================
-- Tahap 7J v1 (minimal) - Opening Snapshot + Daily Rollup
-- Tujuan:
-- 1) Menjaga skema sederhana, aman, dan mudah rebuild
-- 2) Mendukung UI daily bulanan (awal/in/out/adj/akhir, hpp, nilai)
-- 3) Menghindari terlalu banyak tabel turunan di fase awal
-- ============================================================
START TRANSACTION;

-- ------------------------------------------------------------
-- A. Snapshot stok awal bulanan (manual/opname/auto)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_stock_opening_snapshot (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  snapshot_month DATE NOT NULL,
  stock_scope ENUM('WAREHOUSE','DIVISION') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,

  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,

  source_type ENUM('AUTO_REBUILD','OPNAME','MANUAL') NOT NULL DEFAULT 'AUTO_REBUILD',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_inv_opening_scope_profile_month (snapshot_month, stock_scope, division_id, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_opening_month (snapshot_month),
  KEY idx_inv_opening_scope (stock_scope, division_id),
  KEY idx_inv_opening_item (item_id),
  KEY idx_inv_opening_material (material_id),
  CONSTRAINT fk_inv_opening_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_opening_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_opening_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_opening_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_opening_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- B. Daily rollup gudang (khusus WAREHOUSE)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_warehouse_daily_rollup (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  movement_date DATE NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,

  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  adjustment_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_movement_at DATETIME NULL,
  rebuild_batch_no VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_inv_wh_daily_profile_day (movement_date, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_wh_daily_month (month_key),
  KEY idx_inv_wh_daily_day (movement_date),
  KEY idx_inv_wh_daily_item (item_id),
  KEY idx_inv_wh_daily_material (material_id),
  CONSTRAINT fk_inv_wh_daily_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_wh_daily_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_wh_daily_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_daily_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- C. Daily rollup divisi (khusus DIVISION)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_division_daily_rollup (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  movement_date DATE NOT NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,

  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  adjustment_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_movement_at DATETIME NULL,
  rebuild_batch_no VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_inv_div_daily_profile_day (movement_date, division_id, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_div_daily_month (month_key),
  KEY idx_inv_div_daily_scope_day (division_id, movement_date),
  KEY idx_inv_div_daily_item (item_id),
  KEY idx_inv_div_daily_material (material_id),
  CONSTRAINT fk_inv_div_daily_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_div_daily_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_div_daily_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_div_daily_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_daily_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'inv_stock_opening_snapshot' AS table_name, COUNT(*) AS total_rows FROM inv_stock_opening_snapshot
UNION ALL
SELECT 'inv_warehouse_daily_rollup', COUNT(*) FROM inv_warehouse_daily_rollup
UNION ALL
SELECT 'inv_division_daily_rollup', COUNT(*) FROM inv_division_daily_rollup;
