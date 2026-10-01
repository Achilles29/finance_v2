CREATE TABLE IF NOT EXISTS stg_core_latest_purchase_material (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  source_po_id BIGINT UNSIGNED NOT NULL,
  source_po_no VARCHAR(50) NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  material_code VARCHAR(50) NULL,
  material_name VARCHAR(150) NULL,
  line_description VARCHAR(255) NULL,
  brand_name VARCHAR(100) NULL,
  qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  buy_uom_id BIGINT UNSIGNED NULL,
  buy_uom_code VARCHAR(30) NULL,
  content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,
  qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  content_uom_id BIGINT UNSIGNED NULL,
  content_uom_code VARCHAR(30) NULL,
  hpp DECIMAL(18,6) NULL,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  request_date DATE NULL,
  po_status VARCHAR(30) NULL,
  source_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stg_core_latest_purchase_material_material (material_id),
  KEY idx_stg_core_latest_purchase_material_date (request_date),
  KEY idx_stg_core_latest_purchase_material_status (po_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stg_core_latest_component_cost (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  component_code VARCHAR(50) NULL,
  component_name VARCHAR(150) NULL,
  component_type ENUM('BASE','PREPARE') NOT NULL DEFAULT 'BASE',
  uom_id BIGINT UNSIGNED NULL,
  uom_code VARCHAR(30) NULL,
  hpp_standard DECIMAL(18,6) NOT NULL DEFAULT 0,
  target_cost_per_uom DECIMAL(18,6) NOT NULL DEFAULT 0,
  cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  source_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stg_core_latest_component_cost_type (component_type),
  KEY idx_stg_core_latest_component_cost_name (component_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

TRUNCATE TABLE stg_core_latest_purchase_material;
TRUNCATE TABLE stg_core_latest_component_cost;

INSERT INTO stg_core_latest_purchase_material (
  id,
  source_po_id,
  source_po_no,
  material_id,
  material_code,
  material_name,
  line_description,
  brand_name,
  qty_buy,
  buy_uom_id,
  buy_uom_code,
  content_per_buy,
  qty_content,
  content_uom_id,
  content_uom_code,
  hpp,
  unit_price,
  request_date,
  po_status,
  source_updated_at
)
WITH ranked_purchase AS (
  SELECT
    l.id,
    l.po_id AS source_po_id,
    h.po_no AS source_po_no,
    l.material_id,
    m.material_code,
    TRIM(REPLACE(REPLACE(m.material_name, '\r', ' '), '\n', ' ')) AS material_name,
    TRIM(REPLACE(REPLACE(COALESCE(l.description, ''), '\r', ' '), '\n', ' ')) AS line_description,
    TRIM(REPLACE(REPLACE(COALESCE(l.brand_name, ''), '\r', ' '), '\n', ' ')) AS brand_name,
    ROUND(COALESCE(l.qty, 0), 4) AS qty_buy,
    l.uom_id AS buy_uom_id,
    ubuy.code AS buy_uom_code,
    ROUND(COALESCE(l.conversion_factor_to_base, 1), 6) AS content_per_buy,
    ROUND(COALESCE(l.qty, 0) * COALESCE(l.conversion_factor_to_base, 1), 4) AS qty_content,
    m.base_uom_id AS content_uom_id,
    ubase.code AS content_uom_code,
    l.hpp,
    ROUND(COALESCE(l.unit_price, 0), 2) AS unit_price,
    h.request_date,
    h.status AS po_status,
    COALESCE(l.updated_at, h.updated_at, l.created_at, h.created_at) AS source_updated_at,
    ROW_NUMBER() OVER (
      PARTITION BY l.material_id
      ORDER BY COALESCE(l.updated_at, h.updated_at, l.created_at, h.created_at) DESC, h.request_date DESC, l.id DESC
    ) AS rn
  FROM core.pur_purchase_order_line l
  INNER JOIN core.pur_purchase_order h
    ON h.id = l.po_id
  INNER JOIN core.m_material m
    ON m.id = l.material_id
  LEFT JOIN core.m_uom ubuy
    ON ubuy.id = l.uom_id
  LEFT JOIN core.m_uom ubase
    ON ubase.id = m.base_uom_id
  WHERE l.material_id IS NOT NULL
    AND h.status NOT IN ('VOID', 'REJECTED')
    AND COALESCE(l.unit_price, 0) > 0
)
SELECT
  id,
  source_po_id,
  source_po_no,
  material_id,
  material_code,
  material_name,
  NULLIF(line_description, '') AS line_description,
  NULLIF(brand_name, '') AS brand_name,
  qty_buy,
  buy_uom_id,
  buy_uom_code,
  content_per_buy,
  qty_content,
  content_uom_id,
  content_uom_code,
  hpp,
  unit_price,
  request_date,
  po_status,
  source_updated_at
FROM ranked_purchase
WHERE rn = 1;

INSERT INTO stg_core_latest_component_cost (
  id,
  component_code,
  component_name,
  component_type,
  uom_id,
  uom_code,
  hpp_standard,
  target_cost_per_uom,
  cost,
  source_updated_at
)
SELECT
  c.id,
  c.component_code,
  TRIM(REPLACE(REPLACE(c.component_name, '\r', ' '), '\n', ' ')) AS component_name,
  c.component_kind AS component_type,
  c.base_uom_id AS uom_id,
  u.code AS uom_code,
  ROUND(COALESCE(c.hpp_standard, 0), 6) AS hpp_standard,
  ROUND(COALESCE(c.target_cost_per_uom, 0), 6) AS target_cost_per_uom,
  ROUND(
    CASE
      WHEN COALESCE(c.target_cost_per_uom, 0) > 0 THEN c.target_cost_per_uom
      ELSE COALESCE(c.hpp_standard, 0)
    END,
    6
  ) AS cost,
  COALESCE(c.updated_at, c.created_at) AS source_updated_at
FROM core.prd_component c
LEFT JOIN core.m_uom u
  ON u.id = c.base_uom_id
WHERE COALESCE(c.is_active, 1) = 1;