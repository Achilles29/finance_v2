SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mst_purchase_catalog_vendor (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  catalog_id BIGINT UNSIGNED NOT NULL,
  vendor_id BIGINT UNSIGNED NOT NULL,
  standard_price DECIMAL(18,2) NULL,
  last_unit_price DECIMAL(18,2) NULL,
  last_purchase_date DATE NULL,
  last_purchase_order_id BIGINT UNSIGNED NULL,
  last_purchase_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_mst_purchase_catalog_vendor_catalog_vendor (catalog_id, vendor_id),
  KEY idx_mst_purchase_catalog_vendor_vendor (vendor_id),
  KEY idx_mst_purchase_catalog_vendor_active (is_active),
  KEY fk_mst_purchase_catalog_vendor_last_po (last_purchase_order_id),
  KEY fk_mst_purchase_catalog_vendor_last_line (last_purchase_line_id),
  CONSTRAINT fk_mst_purchase_catalog_vendor_catalog FOREIGN KEY (catalog_id) REFERENCES mst_purchase_catalog(id),
  CONSTRAINT fk_mst_purchase_catalog_vendor_vendor FOREIGN KEY (vendor_id) REFERENCES mst_vendor(id),
  CONSTRAINT fk_mst_purchase_catalog_vendor_last_po FOREIGN KEY (last_purchase_order_id) REFERENCES pur_purchase_order(id),
  CONSTRAINT fk_mst_purchase_catalog_vendor_last_line FOREIGN KEY (last_purchase_line_id) REFERENCES pur_purchase_order_line(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @schema_name := DATABASE();
SET @has_catalog_vendor_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'mst_purchase_catalog'
    AND COLUMN_NAME = 'vendor_id'
);

SET @vendor_backfill_sql := IF(
  @has_catalog_vendor_id > 0,
  "INSERT INTO mst_purchase_catalog_vendor (
    catalog_id,
    vendor_id,
    standard_price,
    last_unit_price,
    last_purchase_date,
    last_purchase_order_id,
    last_purchase_line_id,
    notes,
    is_active
  )
  SELECT
    picked.canonical_id AS catalog_id,
    src.vendor_id,
    src.standard_price,
    src.last_unit_price,
    src.last_purchase_date,
    CASE
      WHEN po.id IS NOT NULL AND pol.id IS NOT NULL AND pol.purchase_order_id = po.id THEN src.last_purchase_order_id
      ELSE NULL
    END AS last_purchase_order_id,
    CASE
      WHEN po.id IS NOT NULL AND pol.id IS NOT NULL AND pol.purchase_order_id = po.id THEN src.last_purchase_line_id
      ELSE NULL
    END AS last_purchase_line_id,
    src.notes,
    COALESCE(src.is_active, 1) AS is_active
  FROM mst_purchase_catalog src
  JOIN (
    SELECT
      mapped.canonical_id,
      mapped.vendor_id,
      COALESCE(MAX(CASE WHEN mapped.is_active = 1 THEN mapped.source_id END), MAX(mapped.source_id)) AS source_id
    FROM (
      SELECT
        src0.id AS source_id,
        COALESCE(src0.is_active, 1) AS is_active,
        src0.vendor_id,
        canon.canonical_id
      FROM mst_purchase_catalog src0
      JOIN (
        SELECT
          UPPER(TRIM(COALESCE(line_kind, 'ITEM'))) AS line_kind_key,
          COALESCE(item_id, 0) AS item_id_key,
          COALESCE(material_id, 0) AS material_id_key,
          COALESCE(buy_uom_id, 0) AS buy_uom_id_key,
          COALESCE(content_uom_id, 0) AS content_uom_id_key,
          UPPER(TRIM(COALESCE(catalog_name, ''))) AS catalog_name_key,
          UPPER(TRIM(COALESCE(brand_name, ''))) AS brand_name_key,
          UPPER(TRIM(COALESCE(line_description, ''))) AS line_description_key,
          COALESCE(DATE_FORMAT(expired_date, '%Y-%m-%d'), '') AS expired_date_key,
          ROUND(COALESCE(content_per_buy, 0), 6) AS content_per_buy_key,
          COALESCE(MIN(CASE WHEN COALESCE(is_active, 1) = 1 THEN id END), MIN(id)) AS canonical_id
        FROM mst_purchase_catalog
        GROUP BY
          UPPER(TRIM(COALESCE(line_kind, 'ITEM'))),
          COALESCE(item_id, 0),
          COALESCE(material_id, 0),
          COALESCE(buy_uom_id, 0),
          COALESCE(content_uom_id, 0),
          UPPER(TRIM(COALESCE(catalog_name, ''))),
          UPPER(TRIM(COALESCE(brand_name, ''))),
          UPPER(TRIM(COALESCE(line_description, ''))),
          COALESCE(DATE_FORMAT(expired_date, '%Y-%m-%d'), ''),
          ROUND(COALESCE(content_per_buy, 0), 6)
      ) canon
        ON canon.line_kind_key = UPPER(TRIM(COALESCE(src0.line_kind, 'ITEM')))
       AND canon.item_id_key = COALESCE(src0.item_id, 0)
       AND canon.material_id_key = COALESCE(src0.material_id, 0)
       AND canon.buy_uom_id_key = COALESCE(src0.buy_uom_id, 0)
       AND canon.content_uom_id_key = COALESCE(src0.content_uom_id, 0)
       AND canon.catalog_name_key = UPPER(TRIM(COALESCE(src0.catalog_name, '')))
       AND canon.brand_name_key = UPPER(TRIM(COALESCE(src0.brand_name, '')))
       AND canon.line_description_key = UPPER(TRIM(COALESCE(src0.line_description, '')))
       AND canon.expired_date_key = COALESCE(DATE_FORMAT(src0.expired_date, '%Y-%m-%d'), '')
       AND canon.content_per_buy_key = ROUND(COALESCE(src0.content_per_buy, 0), 6)
      WHERE src0.vendor_id IS NOT NULL AND src0.vendor_id > 0
    ) mapped
    GROUP BY mapped.canonical_id, mapped.vendor_id
  ) picked ON picked.source_id = src.id
  LEFT JOIN pur_purchase_order po ON po.id = src.last_purchase_order_id
  LEFT JOIN pur_purchase_order_line pol ON pol.id = src.last_purchase_line_id
  ON DUPLICATE KEY UPDATE
    standard_price = VALUES(standard_price),
    last_unit_price = VALUES(last_unit_price),
    last_purchase_date = VALUES(last_purchase_date),
    last_purchase_order_id = VALUES(last_purchase_order_id),
    last_purchase_line_id = VALUES(last_purchase_line_id),
    notes = VALUES(notes),
    is_active = VALUES(is_active),
    updated_at = CURRENT_TIMESTAMP",
  "SELECT 'skip vendor backfill: mst_purchase_catalog.vendor_id already removed' AS info"
);
PREPARE stmt_vendor_backfill FROM @vendor_backfill_sql;
EXECUTE stmt_vendor_backfill;
DEALLOCATE PREPARE stmt_vendor_backfill;

SET @has_catalog_vendor_fk := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'mst_purchase_catalog'
    AND CONSTRAINT_NAME = 'fk_mst_purchase_catalog_vendor'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @drop_catalog_vendor_fk_sql := IF(
  @has_catalog_vendor_fk > 0,
  'ALTER TABLE mst_purchase_catalog DROP FOREIGN KEY fk_mst_purchase_catalog_vendor',
  "SELECT 'skip drop fk: fk_mst_purchase_catalog_vendor missing' AS info"
);
PREPARE stmt_drop_catalog_vendor_fk FROM @drop_catalog_vendor_fk_sql;
EXECUTE stmt_drop_catalog_vendor_fk;
DEALLOCATE PREPARE stmt_drop_catalog_vendor_fk;

SET @has_catalog_vendor_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'mst_purchase_catalog'
    AND INDEX_NAME = 'idx_mst_purchase_catalog_vendor'
);
SET @drop_catalog_vendor_idx_sql := IF(
  @has_catalog_vendor_idx > 0,
  'ALTER TABLE mst_purchase_catalog DROP INDEX idx_mst_purchase_catalog_vendor',
  "SELECT 'skip drop index: idx_mst_purchase_catalog_vendor missing' AS info"
);
PREPARE stmt_drop_catalog_vendor_idx FROM @drop_catalog_vendor_idx_sql;
EXECUTE stmt_drop_catalog_vendor_idx;
DEALLOCATE PREPARE stmt_drop_catalog_vendor_idx;

SET @drop_catalog_vendor_col_sql := IF(
  @has_catalog_vendor_id > 0,
  'ALTER TABLE mst_purchase_catalog DROP COLUMN vendor_id',
  "SELECT 'skip drop column: mst_purchase_catalog.vendor_id already removed' AS info"
);
PREPARE stmt_drop_catalog_vendor_col FROM @drop_catalog_vendor_col_sql;
EXECUTE stmt_drop_catalog_vendor_col;
DEALLOCATE PREPARE stmt_drop_catalog_vendor_col;

COMMIT;

SELECT COUNT(*) AS vendor_link_total FROM mst_purchase_catalog_vendor;