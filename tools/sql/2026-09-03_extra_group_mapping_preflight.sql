-- Batch 66: read-only Extra Group mapping schema and data preflight.
-- Output is tabular marker data. Detail rows contain identifiers, statuses,
-- and division identifiers only; every detail category is capped at 50 rows.

START TRANSACTION READ ONLY;

SELECT
    'B66_SCHEMA',
    'TABLE_PRESENCE',
    IF(COUNT(*) = 5, 'PASS', 'FAIL'),
    COUNT(*),
    5
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_type = 'BASE TABLE'
  AND table_name IN (
      'mst_product_extra_map',
      'mst_extra_group_item',
      'mst_product',
      'mst_extra_group',
      'mst_extra'
  );

SELECT
    'B66_SCHEMA',
    'ENGINE_INNODB',
    IF(COUNT(*) = 5, 'PASS', 'FAIL'),
    COUNT(*),
    5
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_type = 'BASE TABLE'
  AND UPPER(engine) = 'INNODB'
  AND table_name IN (
      'mst_product_extra_map',
      'mst_extra_group_item',
      'mst_product',
      'mst_extra_group',
      'mst_extra'
  );

SELECT
    'B66_SCHEMA',
    'REQUIRED_COLUMNS',
    IF(COUNT(*) = 16, 'PASS', 'FAIL'),
    COUNT(*),
    16
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
      (table_name = 'mst_product_extra_map' AND column_name IN ('id', 'extra_group_id', 'product_id', 'sort_order'))
      OR (table_name = 'mst_extra_group_item' AND column_name IN ('id', 'extra_group_id', 'extra_id', 'sort_order'))
      OR (table_name = 'mst_product' AND column_name IN ('id', 'product_division_id', 'is_active'))
      OR (table_name = 'mst_extra_group' AND column_name IN ('id', 'product_division_id', 'is_active'))
      OR (table_name = 'mst_extra' AND column_name IN ('id', 'is_active'))
  );

SELECT
    'B66_SCHEMA',
    'UNIQUE_MAP_PAIR',
    IF(COUNT(*) >= 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT index_name
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'mst_product_extra_map'
    GROUP BY index_name
    HAVING MAX(non_unique) = 0
       AND COUNT(*) = 2
       AND SUM(column_name = 'extra_group_id') = 1
       AND SUM(column_name = 'product_id') = 1
) AS matching_unique_map_pair;

SELECT
    'B66_SCHEMA',
    'UNIQUE_ITEM_PAIR',
    IF(COUNT(*) >= 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT index_name
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'mst_extra_group_item'
    GROUP BY index_name
    HAVING MAX(non_unique) = 0
       AND COUNT(*) = 2
       AND SUM(column_name = 'extra_group_id') = 1
       AND SUM(column_name = 'extra_id') = 1
) AS matching_unique_item_pair;

SELECT
    'B66_SCHEMA',
    'FK_MAP_GROUP',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT constraint_name
    FROM information_schema.key_column_usage
    WHERE constraint_schema = DATABASE()
      AND table_name = 'mst_product_extra_map'
      AND constraint_name = 'fk_mst_product_extra_map_group'
    GROUP BY constraint_name
    HAVING COUNT(*) = 1
       AND MAX(column_name = 'extra_group_id') = 1
       AND MAX(referenced_table_schema = DATABASE()) = 1
       AND MAX(referenced_table_name = 'mst_extra_group') = 1
       AND MAX(referenced_column_name = 'id') = 1
) AS matching_fk_map_group;

SELECT
    'B66_SCHEMA',
    'FK_MAP_PRODUCT',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT constraint_name
    FROM information_schema.key_column_usage
    WHERE constraint_schema = DATABASE()
      AND table_name = 'mst_product_extra_map'
      AND constraint_name = 'fk_mst_product_extra_map_product'
    GROUP BY constraint_name
    HAVING COUNT(*) = 1
       AND MAX(column_name = 'product_id') = 1
       AND MAX(referenced_table_schema = DATABASE()) = 1
       AND MAX(referenced_table_name = 'mst_product') = 1
       AND MAX(referenced_column_name = 'id') = 1
) AS matching_fk_map_product;

SELECT
    'B66_SCHEMA',
    'FK_ITEM_GROUP',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT constraint_name
    FROM information_schema.key_column_usage
    WHERE constraint_schema = DATABASE()
      AND table_name = 'mst_extra_group_item'
      AND constraint_name = 'fk_mst_extra_group_item_group'
    GROUP BY constraint_name
    HAVING COUNT(*) = 1
       AND MAX(column_name = 'extra_group_id') = 1
       AND MAX(referenced_table_schema = DATABASE()) = 1
       AND MAX(referenced_table_name = 'mst_extra_group') = 1
       AND MAX(referenced_column_name = 'id') = 1
) AS matching_fk_item_group;

SELECT
    'B66_SCHEMA',
    'FK_ITEM_EXTRA',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT constraint_name
    FROM information_schema.key_column_usage
    WHERE constraint_schema = DATABASE()
      AND table_name = 'mst_extra_group_item'
      AND constraint_name = 'fk_mst_extra_group_item_extra'
    GROUP BY constraint_name
    HAVING COUNT(*) = 1
       AND MAX(column_name = 'extra_id') = 1
       AND MAX(referenced_table_schema = DATABASE()) = 1
       AND MAX(referenced_table_name = 'mst_extra') = 1
       AND MAX(referenced_column_name = 'id') = 1
) AS matching_fk_item_extra;

SELECT
    'B66_SCHEMA',
    'FK_MAP_GROUP_RULES',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT key_usage.constraint_name
    FROM information_schema.key_column_usage AS key_usage
    INNER JOIN information_schema.referential_constraints AS reference_rule
        ON reference_rule.constraint_schema = key_usage.constraint_schema
       AND reference_rule.table_name = key_usage.table_name
       AND reference_rule.constraint_name = key_usage.constraint_name
    WHERE key_usage.constraint_schema = DATABASE()
      AND key_usage.table_name = 'mst_product_extra_map'
      AND key_usage.constraint_name = 'fk_mst_product_extra_map_group'
    GROUP BY key_usage.constraint_name
    HAVING COUNT(*) = 1
       AND MAX(key_usage.column_name = 'extra_group_id') = 1
       AND MAX(key_usage.referenced_table_schema = DATABASE()) = 1
       AND MAX(key_usage.referenced_table_name = 'mst_extra_group') = 1
       AND MAX(key_usage.referenced_column_name = 'id') = 1
       AND MAX(reference_rule.referenced_table_name = 'mst_extra_group') = 1
       AND MAX(UPPER(reference_rule.delete_rule) = 'RESTRICT') = 1
       AND MAX(UPPER(reference_rule.update_rule) = 'RESTRICT') = 1
) AS matching_fk_map_group_rules;

SELECT
    'B66_SCHEMA',
    'FK_MAP_PRODUCT_RULES',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT key_usage.constraint_name
    FROM information_schema.key_column_usage AS key_usage
    INNER JOIN information_schema.referential_constraints AS reference_rule
        ON reference_rule.constraint_schema = key_usage.constraint_schema
       AND reference_rule.table_name = key_usage.table_name
       AND reference_rule.constraint_name = key_usage.constraint_name
    WHERE key_usage.constraint_schema = DATABASE()
      AND key_usage.table_name = 'mst_product_extra_map'
      AND key_usage.constraint_name = 'fk_mst_product_extra_map_product'
    GROUP BY key_usage.constraint_name
    HAVING COUNT(*) = 1
       AND MAX(key_usage.column_name = 'product_id') = 1
       AND MAX(key_usage.referenced_table_schema = DATABASE()) = 1
       AND MAX(key_usage.referenced_table_name = 'mst_product') = 1
       AND MAX(key_usage.referenced_column_name = 'id') = 1
       AND MAX(reference_rule.referenced_table_name = 'mst_product') = 1
       AND MAX(UPPER(reference_rule.delete_rule) = 'RESTRICT') = 1
       AND MAX(UPPER(reference_rule.update_rule) = 'RESTRICT') = 1
) AS matching_fk_map_product_rules;

SELECT
    'B66_SCHEMA',
    'FK_ITEM_GROUP_RULES',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT key_usage.constraint_name
    FROM information_schema.key_column_usage AS key_usage
    INNER JOIN information_schema.referential_constraints AS reference_rule
        ON reference_rule.constraint_schema = key_usage.constraint_schema
       AND reference_rule.table_name = key_usage.table_name
       AND reference_rule.constraint_name = key_usage.constraint_name
    WHERE key_usage.constraint_schema = DATABASE()
      AND key_usage.table_name = 'mst_extra_group_item'
      AND key_usage.constraint_name = 'fk_mst_extra_group_item_group'
    GROUP BY key_usage.constraint_name
    HAVING COUNT(*) = 1
       AND MAX(key_usage.column_name = 'extra_group_id') = 1
       AND MAX(key_usage.referenced_table_schema = DATABASE()) = 1
       AND MAX(key_usage.referenced_table_name = 'mst_extra_group') = 1
       AND MAX(key_usage.referenced_column_name = 'id') = 1
       AND MAX(reference_rule.referenced_table_name = 'mst_extra_group') = 1
       AND MAX(UPPER(reference_rule.delete_rule) = 'RESTRICT') = 1
       AND MAX(UPPER(reference_rule.update_rule) = 'RESTRICT') = 1
) AS matching_fk_item_group_rules;

SELECT
    'B66_SCHEMA',
    'FK_ITEM_EXTRA_RULES',
    IF(COUNT(*) = 1, 'PASS', 'FAIL'),
    COUNT(*),
    1
FROM (
    SELECT key_usage.constraint_name
    FROM information_schema.key_column_usage AS key_usage
    INNER JOIN information_schema.referential_constraints AS reference_rule
        ON reference_rule.constraint_schema = key_usage.constraint_schema
       AND reference_rule.table_name = key_usage.table_name
       AND reference_rule.constraint_name = key_usage.constraint_name
    WHERE key_usage.constraint_schema = DATABASE()
      AND key_usage.table_name = 'mst_extra_group_item'
      AND key_usage.constraint_name = 'fk_mst_extra_group_item_extra'
    GROUP BY key_usage.constraint_name
    HAVING COUNT(*) = 1
       AND MAX(key_usage.column_name = 'extra_id') = 1
       AND MAX(key_usage.referenced_table_schema = DATABASE()) = 1
       AND MAX(key_usage.referenced_table_name = 'mst_extra') = 1
       AND MAX(key_usage.referenced_column_name = 'id') = 1
       AND MAX(reference_rule.referenced_table_name = 'mst_extra') = 1
       AND MAX(UPPER(reference_rule.delete_rule) = 'RESTRICT') = 1
       AND MAX(UPPER(reference_rule.update_rule) = 'RESTRICT') = 1
) AS matching_fk_item_extra_rules;

SELECT 'B66_FINDING', 'ORPHAN_MAP_GROUP', COUNT(*)
FROM mst_product_extra_map AS mapping
LEFT JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
WHERE extra_group.id IS NULL;

SELECT 'B66_FINDING', 'ORPHAN_MAP_PRODUCT', COUNT(*)
FROM mst_product_extra_map AS mapping
LEFT JOIN mst_product AS product ON product.id = mapping.product_id
WHERE product.id IS NULL;

SELECT 'B66_FINDING', 'ORPHAN_ITEM_GROUP', COUNT(*)
FROM mst_extra_group_item AS mapping
LEFT JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
WHERE extra_group.id IS NULL;

SELECT 'B66_FINDING', 'ORPHAN_ITEM_EXTRA', COUNT(*)
FROM mst_extra_group_item AS mapping
LEFT JOIN mst_extra AS extra_item ON extra_item.id = mapping.extra_id
WHERE extra_item.id IS NULL;

SELECT 'B66_FINDING', 'DUPLICATE_MAP_PAIR', COUNT(*)
FROM (
    SELECT extra_group_id, product_id
    FROM mst_product_extra_map
    GROUP BY extra_group_id, product_id
    HAVING COUNT(*) > 1
) AS duplicate_map_pairs;

SELECT 'B66_FINDING', 'DUPLICATE_ITEM_PAIR', COUNT(*)
FROM (
    SELECT extra_group_id, extra_id
    FROM mst_extra_group_item
    GROUP BY extra_group_id, extra_id
    HAVING COUNT(*) > 1
) AS duplicate_item_pairs;

SELECT 'B66_FINDING', 'INACTIVE_MAP', COUNT(*)
FROM mst_product_extra_map AS mapping
INNER JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
INNER JOIN mst_product AS product ON product.id = mapping.product_id
WHERE COALESCE(extra_group.is_active, 0) <> 1
   OR COALESCE(product.is_active, 0) <> 1;

SELECT 'B66_FINDING', 'INACTIVE_ITEM', COUNT(*)
FROM mst_extra_group_item AS mapping
INNER JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
INNER JOIN mst_extra AS extra_item ON extra_item.id = mapping.extra_id
WHERE COALESCE(extra_group.is_active, 0) <> 1
   OR COALESCE(extra_item.is_active, 0) <> 1;

SELECT 'B66_FINDING', 'DIVISION_MISMATCH', COUNT(*)
FROM mst_product_extra_map AS mapping
INNER JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
INNER JOIN mst_product AS product ON product.id = mapping.product_id
WHERE extra_group.product_division_id IS NOT NULL
  AND NOT (extra_group.product_division_id <=> product.product_division_id);

SELECT
    'B66_ROW', 'ORPHAN_MAP_GROUP', mapping.id, mapping.extra_group_id,
    mapping.product_id, NULL, NULL, NULL, NULL
FROM mst_product_extra_map AS mapping
LEFT JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
WHERE extra_group.id IS NULL
ORDER BY mapping.id
LIMIT 50;

SELECT
    'B66_ROW', 'ORPHAN_MAP_PRODUCT', mapping.id, mapping.extra_group_id,
    mapping.product_id, NULL, extra_group.is_active,
    extra_group.product_division_id, NULL
FROM mst_product_extra_map AS mapping
LEFT JOIN mst_product AS product ON product.id = mapping.product_id
LEFT JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
WHERE product.id IS NULL
ORDER BY mapping.id
LIMIT 50;

SELECT
    'B66_ROW', 'ORPHAN_ITEM_GROUP', mapping.id, mapping.extra_group_id,
    mapping.extra_id, NULL, NULL, NULL, NULL
FROM mst_extra_group_item AS mapping
LEFT JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
WHERE extra_group.id IS NULL
ORDER BY mapping.id
LIMIT 50;

SELECT
    'B66_ROW', 'ORPHAN_ITEM_EXTRA', mapping.id, mapping.extra_group_id,
    mapping.extra_id, NULL, extra_group.is_active,
    extra_group.product_division_id, NULL
FROM mst_extra_group_item AS mapping
LEFT JOIN mst_extra AS extra_item ON extra_item.id = mapping.extra_id
LEFT JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
WHERE extra_item.id IS NULL
ORDER BY mapping.id
LIMIT 50;

SELECT
    'B66_ROW', 'DUPLICATE_MAP_PAIR', MIN(id), extra_group_id,
    product_id, COUNT(*), NULL, NULL, NULL
FROM mst_product_extra_map
GROUP BY extra_group_id, product_id
HAVING COUNT(*) > 1
ORDER BY extra_group_id, product_id
LIMIT 50;

SELECT
    'B66_ROW', 'DUPLICATE_ITEM_PAIR', MIN(id), extra_group_id,
    extra_id, COUNT(*), NULL, NULL, NULL
FROM mst_extra_group_item
GROUP BY extra_group_id, extra_id
HAVING COUNT(*) > 1
ORDER BY extra_group_id, extra_id
LIMIT 50;

SELECT
    'B66_ROW', 'INACTIVE_MAP', mapping.id, mapping.extra_group_id,
    mapping.product_id, product.is_active, extra_group.is_active,
    extra_group.product_division_id, product.product_division_id
FROM mst_product_extra_map AS mapping
INNER JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
INNER JOIN mst_product AS product ON product.id = mapping.product_id
WHERE COALESCE(extra_group.is_active, 0) <> 1
   OR COALESCE(product.is_active, 0) <> 1
ORDER BY mapping.id
LIMIT 50;

SELECT
    'B66_ROW', 'INACTIVE_ITEM', mapping.id, mapping.extra_group_id,
    mapping.extra_id, extra_item.is_active, extra_group.is_active,
    extra_group.product_division_id, NULL
FROM mst_extra_group_item AS mapping
INNER JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
INNER JOIN mst_extra AS extra_item ON extra_item.id = mapping.extra_id
WHERE COALESCE(extra_group.is_active, 0) <> 1
   OR COALESCE(extra_item.is_active, 0) <> 1
ORDER BY mapping.id
LIMIT 50;

SELECT
    'B66_ROW', 'DIVISION_MISMATCH', mapping.id, mapping.extra_group_id,
    mapping.product_id, product.is_active, extra_group.is_active,
    extra_group.product_division_id, product.product_division_id
FROM mst_product_extra_map AS mapping
INNER JOIN mst_extra_group AS extra_group ON extra_group.id = mapping.extra_group_id
INNER JOIN mst_product AS product ON product.id = mapping.product_id
WHERE extra_group.product_division_id IS NOT NULL
  AND NOT (extra_group.product_division_id <=> product.product_division_id)
ORDER BY mapping.id
LIMIT 50;

SELECT 'B66_END', 'OK'
FROM information_schema.schemata
WHERE schema_name = DATABASE();

COMMIT;
