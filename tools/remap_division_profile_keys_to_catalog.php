<?php
declare(strict_types=1);

/**
 * Remap historical division profile_key to canonical mst_purchase_catalog.profile_key
 * by exact identity match (idempotent, single transaction when --apply).
 *
 * Usage:
 *   php tools/remap_division_profile_keys_to_catalog.php
 *   php tools/remap_division_profile_keys_to_catalog.php --apply
 */

$opts = getopt('', ['apply', 'db-host::', 'db-user::', 'db-pass::', 'db-name::']);
$apply = array_key_exists('apply', $opts);
$dbHost = isset($opts['db-host']) ? (string)$opts['db-host'] : '127.0.0.1';
$dbUser = isset($opts['db-user']) ? (string)$opts['db-user'] : 'root';
$dbPass = isset($opts['db-pass']) ? (string)$opts['db-pass'] : '';
$dbName = isset($opts['db-name']) ? (string)$opts['db-name'] : 'db_finance';

$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($db->connect_errno) {
    fwrite(STDERR, 'DB connection failed: ' . $db->connect_error . PHP_EOL);
    exit(1);
}
$db->set_charset('utf8mb4');

function scalar(mysqli $db, string $sql): int
{
    $rs = $db->query($sql);
    if (!$rs) {
        throw new RuntimeException('Query failed: ' . $db->error . ' | SQL: ' . $sql);
    }
    $row = $rs->fetch_row();
    return (int)($row[0] ?? 0);
}

function execOrThrow(mysqli $db, string $sql): int
{
    if (!$db->query($sql)) {
        throw new RuntimeException('Execute failed: ' . $db->error . ' | SQL: ' . $sql);
    }
    return $db->affected_rows;
}

$createCanonicalSql = "
    CREATE TEMPORARY TABLE tmp_catalog_canonical (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      item_id BIGINT UNSIGNED NOT NULL,
      material_id BIGINT UNSIGNED NOT NULL,
      buy_uom_id BIGINT UNSIGNED NOT NULL,
      content_uom_id BIGINT UNSIGNED NOT NULL,
      profile_content_per_buy DECIMAL(18,6) NOT NULL,
      profile_name VARCHAR(255) NOT NULL,
      profile_brand VARCHAR(255) NOT NULL,
      profile_description VARCHAR(255) NOT NULL,
      profile_expired_date DATE NULL,
      canonical_profile_key CHAR(64) NOT NULL,
      canonical_opening_profile_key CHAR(40) NOT NULL,
      PRIMARY KEY (id),
      KEY idx_cc_dims (item_id, material_id, buy_uom_id, content_uom_id, profile_expired_date, profile_content_per_buy)
    ) ENGINE=InnoDB
";

$insertCanonicalSql = "
    INSERT INTO tmp_catalog_canonical (
      item_id, material_id, buy_uom_id, content_uom_id, profile_content_per_buy,
      profile_name, profile_brand, profile_description, profile_expired_date,
      canonical_profile_key, canonical_opening_profile_key
    )
    SELECT
      c1.item_id,
      COALESCE(c1.material_id,0) AS material_id,
      c1.buy_uom_id,
      c1.content_uom_id,
      ROUND(COALESCE(c1.content_per_buy,0),6) AS profile_content_per_buy,
      UPPER(TRIM(COALESCE(c1.catalog_name,''))) AS profile_name,
      UPPER(TRIM(COALESCE(c1.brand_name,''))) AS profile_brand,
      UPPER(TRIM(COALESCE(c1.line_description,''))) AS profile_description,
      c1.expired_date AS profile_expired_date,
      c1.profile_key AS canonical_profile_key,
      LEFT(c1.profile_key, 40) AS canonical_opening_profile_key
    FROM mst_purchase_catalog c1
    LEFT JOIN mst_purchase_catalog c2
      ON COALESCE(c2.is_active,1) = 1
     AND c2.item_id = c1.item_id
     AND COALESCE(c2.material_id,0) = COALESCE(c1.material_id,0)
     AND c2.buy_uom_id = c1.buy_uom_id
     AND c2.content_uom_id = c1.content_uom_id
     AND ROUND(COALESCE(c2.content_per_buy,0),6) = ROUND(COALESCE(c1.content_per_buy,0),6)
     AND UPPER(TRIM(COALESCE(c2.catalog_name,''))) = UPPER(TRIM(COALESCE(c1.catalog_name,'')))
     AND UPPER(TRIM(COALESCE(c2.brand_name,''))) = UPPER(TRIM(COALESCE(c1.brand_name,'')))
     AND UPPER(TRIM(COALESCE(c2.line_description,''))) = UPPER(TRIM(COALESCE(c1.line_description,'')))
     AND ((c2.expired_date IS NULL AND c1.expired_date IS NULL) OR (c2.expired_date <=> c1.expired_date))
     AND (
        COALESCE(c2.last_purchase_date, '1000-01-01') > COALESCE(c1.last_purchase_date, '1000-01-01')
        OR (
            COALESCE(c2.last_purchase_date, '1000-01-01') = COALESCE(c1.last_purchase_date, '1000-01-01')
            AND c2.id > c1.id
        )
     )
    WHERE COALESCE(c1.is_active,1) = 1
      AND COALESCE(c1.profile_key,'') <> ''
      AND c2.id IS NULL
";

$createMapSql = "
    CREATE TEMPORARY TABLE tmp_division_profile_key_map (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      old_profile_key CHAR(64) NOT NULL,
      new_profile_key CHAR(64) NOT NULL,
      item_id BIGINT UNSIGNED NULL,
      material_id BIGINT UNSIGNED NULL,
      buy_uom_id BIGINT UNSIGNED NULL,
      content_uom_id BIGINT UNSIGNED NULL,
      profile_name VARCHAR(255) NULL,
      profile_brand VARCHAR(255) NULL,
      profile_description VARCHAR(255) NULL,
      profile_expired_date DATE NULL,
      profile_content_per_buy DECIMAL(18,6) NULL,
      PRIMARY KEY (id),
      KEY idx_map_old_key (old_profile_key),
      KEY idx_map_new_key (new_profile_key),
      KEY idx_map_dims (item_id, material_id, buy_uom_id, content_uom_id, profile_expired_date, profile_content_per_buy)
    ) ENGINE=InnoDB
";

$insertMapSql = "
    INSERT INTO tmp_division_profile_key_map (
      old_profile_key, new_profile_key, item_id, material_id, buy_uom_id, content_uom_id,
      profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
    )
    SELECT DISTINCT
      x.profile_key AS old_profile_key,
      cc.canonical_profile_key AS new_profile_key,
      x.item_id,
      x.material_id,
      x.buy_uom_id,
      x.content_uom_id,
      x.profile_name,
      x.profile_brand,
      x.profile_description,
      x.profile_expired_date,
      ROUND(COALESCE(x.profile_content_per_buy,0), 6) AS profile_content_per_buy
    FROM (
      SELECT 'opening' AS source_table, profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
      FROM inv_division_stock_opening_snapshot
      UNION ALL
      SELECT 'balance' AS source_table, profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
      FROM inv_division_stock_balance
      UNION ALL
      SELECT 'daily' AS source_table, profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
      FROM inv_division_daily_rollup
      UNION ALL
      SELECT 'movement' AS source_table, profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
      FROM inv_stock_movement_log
      WHERE movement_scope = 'DIVISION'
    ) x
    JOIN tmp_catalog_canonical cc
      ON cc.item_id = x.item_id
     AND cc.material_id = COALESCE(x.material_id,0)
     AND cc.buy_uom_id = x.buy_uom_id
     AND cc.content_uom_id = x.content_uom_id
     AND cc.profile_content_per_buy = ROUND(COALESCE(x.profile_content_per_buy,0),6)
     AND cc.profile_name = UPPER(TRIM(COALESCE(x.profile_name,'')))
     AND cc.profile_brand = UPPER(TRIM(COALESCE(x.profile_brand,'')))
     AND cc.profile_description = UPPER(TRIM(COALESCE(x.profile_description,'')))
     AND ((cc.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (cc.profile_expired_date <=> x.profile_expired_date))
    WHERE COALESCE(x.profile_key, '') <> ''
      AND (
            (x.source_table = 'opening' AND COALESCE(x.profile_key, '') <> COALESCE(cc.canonical_opening_profile_key, ''))
         OR (x.source_table <> 'opening' AND COALESCE(x.profile_key, '') <> COALESCE(cc.canonical_profile_key, ''))
      )
";

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'map_rows' => 0,
    'opening_candidates' => 0,
    'balance_candidates' => 0,
    'daily_candidates' => 0,
    'movement_candidates' => 0,
    'opening_conflicts' => 0,
    'balance_conflicts' => 0,
    'daily_conflicts' => 0,
    'opening_updated' => 0,
    'opening_merged' => 0,
    'opening_deleted' => 0,
    'balance_updated' => 0,
    'balance_merged' => 0,
    'balance_deleted' => 0,
    'daily_updated' => 0,
    'daily_merged' => 0,
    'daily_deleted' => 0,
    'movement_updated' => 0,
    'remaining_opening' => 0,
    'remaining_balance' => 0,
    'remaining_daily' => 0,
    'remaining_movement' => 0,
    'status' => 'ok',
    'error' => null,
];

try {
    execOrThrow($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_division_profile_key_map');
    execOrThrow($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_catalog_canonical');
    execOrThrow($db, $createCanonicalSql);
    execOrThrow($db, $insertCanonicalSql);
    execOrThrow($db, $createMapSql);
    execOrThrow($db, $insertMapSql);

    $summary['map_rows'] = scalar($db, 'SELECT COUNT(*) FROM tmp_division_profile_key_map');

    $summary['opening_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_stock_opening_snapshot s
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
        WHERE s.profile_key <> LEFT(m.new_profile_key, 40)
    ");

    $summary['balance_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_stock_balance b
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = b.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(b.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(b.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(b.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(b.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(b.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(b.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(b.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(b.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND b.profile_expired_date IS NULL) OR (m.profile_expired_date <=> b.profile_expired_date))
    ");

    $summary['daily_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_daily_rollup d
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = d.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(d.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(d.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(d.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(d.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(d.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(d.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(d.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(d.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND d.profile_expired_date IS NULL) OR (m.profile_expired_date <=> d.profile_expired_date))
    ");

    $summary['movement_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_stock_movement_log l
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = l.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(l.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(l.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(l.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(l.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(l.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(l.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(l.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(l.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND l.profile_expired_date IS NULL) OR (m.profile_expired_date <=> l.profile_expired_date))
        WHERE l.movement_scope = 'DIVISION'
    ");

    $summary['opening_conflicts'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_stock_opening_snapshot s
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
        JOIN inv_division_stock_opening_snapshot t
          ON t.snapshot_month = s.snapshot_month
         AND t.division_id = s.division_id
         AND t.destination_type = s.destination_type
         AND t.stock_domain = s.stock_domain
         AND t.item_id = s.item_id
         AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
         AND t.buy_uom_id = s.buy_uom_id
         AND t.content_uom_id = s.content_uom_id
            AND t.profile_key = LEFT(m.new_profile_key, 40)
        WHERE s.profile_key <> LEFT(m.new_profile_key, 40)
    ");

    $summary['balance_conflicts'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_stock_balance s
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
        JOIN inv_division_stock_balance t
          ON t.division_id = s.division_id
         AND t.destination_type = s.destination_type
         AND t.item_id = s.item_id
         AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
         AND t.buy_uom_id = s.buy_uom_id
         AND t.content_uom_id = s.content_uom_id
         AND t.profile_key = m.new_profile_key
    ");

    $summary['daily_conflicts'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_daily_rollup s
        JOIN tmp_division_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
        JOIN inv_division_daily_rollup t
          ON t.month_key = s.month_key
         AND t.movement_date = s.movement_date
         AND t.division_id = s.division_id
         AND t.destination_type = s.destination_type
         AND t.stock_domain = s.stock_domain
         AND t.item_id = s.item_id
         AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
         AND t.buy_uom_id = s.buy_uom_id
         AND t.content_uom_id = s.content_uom_id
         AND t.profile_key = m.new_profile_key
    ");

    if ($apply) {
        $db->begin_transaction();

        // Opening conflict merge -> target.
        $summary['opening_merged'] = execOrThrow($db, "
            UPDATE inv_division_stock_opening_snapshot t
            JOIN (
                SELECT
                    s.snapshot_month,
                    s.division_id,
                    s.destination_type,
                    s.stock_domain,
                    s.item_id,
                    s.material_id,
                    s.buy_uom_id,
                    s.content_uom_id,
                    LEFT(m.new_profile_key, 40) AS new_profile_key,
                    SUM(COALESCE(s.opening_qty_buy,0)) AS add_opening_qty_buy,
                    SUM(COALESCE(s.opening_qty_content,0)) AS add_opening_qty_content,
                    SUM(COALESCE(s.opening_total_value,0)) AS add_opening_total_value
                FROM inv_division_stock_opening_snapshot s
                JOIN tmp_division_profile_key_map m
                  ON m.old_profile_key = s.profile_key
                 AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
                 AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
                 AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
                 AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
                JOIN inv_division_stock_opening_snapshot ex
                  ON ex.snapshot_month = s.snapshot_month
                 AND ex.division_id = s.division_id
                 AND ex.destination_type = s.destination_type
                 AND ex.stock_domain = s.stock_domain
                 AND ex.item_id = s.item_id
                 AND COALESCE(ex.material_id,0) = COALESCE(s.material_id,0)
                 AND ex.buy_uom_id = s.buy_uom_id
                 AND ex.content_uom_id = s.content_uom_id
                 AND ex.profile_key = LEFT(m.new_profile_key, 40)
                WHERE s.profile_key <> LEFT(m.new_profile_key, 40)
                GROUP BY
                    s.snapshot_month, s.division_id, s.destination_type, s.stock_domain,
                    s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, LEFT(m.new_profile_key, 40)
            ) g
              ON t.snapshot_month = g.snapshot_month
             AND t.division_id = g.division_id
             AND t.destination_type = g.destination_type
             AND t.stock_domain = g.stock_domain
             AND t.item_id = g.item_id
             AND COALESCE(t.material_id,0) = COALESCE(g.material_id,0)
             AND t.buy_uom_id = g.buy_uom_id
             AND t.content_uom_id = g.content_uom_id
             AND t.profile_key = g.new_profile_key
            SET
              t.opening_qty_buy = ROUND(COALESCE(t.opening_qty_buy,0) + g.add_opening_qty_buy, 4),
              t.opening_qty_content = ROUND(COALESCE(t.opening_qty_content,0) + g.add_opening_qty_content, 4),
              t.opening_total_value = ROUND(COALESCE(t.opening_total_value,0) + g.add_opening_total_value, 2),
              t.opening_avg_cost_per_content = CASE
                WHEN ABS(COALESCE(t.opening_qty_content,0) + g.add_opening_qty_content) > 0.000001 THEN
                    ROUND((COALESCE(t.opening_total_value,0) + g.add_opening_total_value) / (COALESCE(t.opening_qty_content,0) + g.add_opening_qty_content), 6)
                ELSE COALESCE(t.opening_avg_cost_per_content,0)
              END,
              t.updated_at = NOW()
        ");

        // Opening delete merged sources.
        $summary['opening_deleted'] = execOrThrow($db, "
            DELETE s
            FROM inv_division_stock_opening_snapshot s
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = s.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
            JOIN inv_division_stock_opening_snapshot t
              ON t.snapshot_month = s.snapshot_month
             AND t.division_id = s.division_id
             AND t.destination_type = s.destination_type
             AND t.stock_domain = s.stock_domain
             AND t.item_id = s.item_id
             AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
             AND t.buy_uom_id = s.buy_uom_id
             AND t.content_uom_id = s.content_uom_id
             AND t.profile_key = LEFT(m.new_profile_key, 40)
            WHERE s.profile_key <> LEFT(m.new_profile_key, 40)
        ");

        // Opening non-conflict update.
        $summary['opening_updated'] = execOrThrow($db, "
            UPDATE inv_division_stock_opening_snapshot s
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = s.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
            LEFT JOIN inv_division_stock_opening_snapshot t
              ON t.snapshot_month = s.snapshot_month
             AND t.division_id = s.division_id
             AND t.destination_type = s.destination_type
             AND t.stock_domain = s.stock_domain
             AND t.item_id = s.item_id
             AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
             AND t.buy_uom_id = s.buy_uom_id
             AND t.content_uom_id = s.content_uom_id
             AND t.profile_key = LEFT(m.new_profile_key, 40)
            SET s.profile_key = LEFT(m.new_profile_key, 40),
                s.updated_at = NOW()
            WHERE t.id IS NULL
              AND s.profile_key <> LEFT(m.new_profile_key, 40)
        ");

        // Balance conflict merge -> target.
        $summary['balance_merged'] = execOrThrow($db, "
            UPDATE inv_division_stock_balance t
            JOIN (
                SELECT
                    s.division_id,
                    s.destination_type,
                    s.item_id,
                    s.material_id,
                    s.buy_uom_id,
                    s.content_uom_id,
                    m.new_profile_key,
                    SUM(COALESCE(s.qty_buy_balance,0)) AS add_qty_buy,
                    SUM(COALESCE(s.qty_content_balance,0)) AS add_qty_content,
                    SUM(COALESCE(s.qty_content_balance,0) * COALESCE(s.avg_cost_per_content,0)) AS add_total_value
                FROM inv_division_stock_balance s
                JOIN tmp_division_profile_key_map m
                  ON m.old_profile_key = s.profile_key
                 AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
                 AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
                 AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
                 AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
                JOIN inv_division_stock_balance ex
                  ON ex.division_id = s.division_id
                 AND ex.destination_type = s.destination_type
                 AND ex.item_id = s.item_id
                 AND COALESCE(ex.material_id,0) = COALESCE(s.material_id,0)
                 AND ex.buy_uom_id = s.buy_uom_id
                 AND ex.content_uom_id = s.content_uom_id
                 AND ex.profile_key = m.new_profile_key
                GROUP BY
                    s.division_id, s.destination_type, s.item_id, s.material_id,
                    s.buy_uom_id, s.content_uom_id, m.new_profile_key
            ) g
              ON t.division_id = g.division_id
             AND t.destination_type = g.destination_type
             AND t.item_id = g.item_id
             AND COALESCE(t.material_id,0) = COALESCE(g.material_id,0)
             AND t.buy_uom_id = g.buy_uom_id
             AND t.content_uom_id = g.content_uom_id
             AND t.profile_key = g.new_profile_key
            SET
              t.qty_buy_balance = ROUND(COALESCE(t.qty_buy_balance,0) + g.add_qty_buy, 4),
              t.qty_content_balance = ROUND(COALESCE(t.qty_content_balance,0) + g.add_qty_content, 4),
              t.avg_cost_per_content = CASE
                WHEN ABS(COALESCE(t.qty_content_balance,0) + g.add_qty_content) > 0.000001 THEN
                  ROUND(((COALESCE(t.qty_content_balance,0) * COALESCE(t.avg_cost_per_content,0)) + g.add_total_value) / (COALESCE(t.qty_content_balance,0) + g.add_qty_content), 6)
                ELSE COALESCE(t.avg_cost_per_content,0)
              END,
              t.updated_at = NOW()
        ");

        // Balance delete sources that already have canonical target.
        $summary['balance_deleted'] = execOrThrow($db, "
            DELETE s
            FROM inv_division_stock_balance s
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = s.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
            JOIN inv_division_stock_balance t
              ON t.division_id = s.division_id
             AND t.destination_type = s.destination_type
             AND t.item_id = s.item_id
             AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
             AND t.buy_uom_id = s.buy_uom_id
             AND t.content_uom_id = s.content_uom_id
             AND t.profile_key = m.new_profile_key
        ");

        // Balance non-conflict update.
        $summary['balance_updated'] = execOrThrow($db, "
            UPDATE inv_division_stock_balance s
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = s.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
            LEFT JOIN inv_division_stock_balance t
              ON t.division_id = s.division_id
             AND t.destination_type = s.destination_type
             AND t.item_id = s.item_id
             AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
             AND t.buy_uom_id = s.buy_uom_id
             AND t.content_uom_id = s.content_uom_id
             AND t.profile_key = m.new_profile_key
            SET s.profile_key = m.new_profile_key,
                s.updated_at = NOW()
            WHERE t.id IS NULL
        ");

        // Daily conflict merge -> target.
        $summary['daily_merged'] = execOrThrow($db, "
            UPDATE inv_division_daily_rollup t
            JOIN (
                SELECT
                    s.month_key, s.movement_date, s.division_id, s.destination_type, s.stock_domain,
                    s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id,
                    m.new_profile_key,
                    SUM(COALESCE(s.opening_qty_buy,0)) AS opening_qty_buy,
                    SUM(COALESCE(s.opening_qty_content,0)) AS opening_qty_content,
                    SUM(COALESCE(s.in_qty_buy,0)) AS in_qty_buy,
                    SUM(COALESCE(s.in_qty_content,0)) AS in_qty_content,
                    SUM(COALESCE(s.out_qty_buy,0)) AS out_qty_buy,
                    SUM(COALESCE(s.out_qty_content,0)) AS out_qty_content,
                    SUM(COALESCE(s.discarded_qty_buy,0)) AS discarded_qty_buy,
                    SUM(COALESCE(s.discarded_qty_content,0)) AS discarded_qty_content,
                    SUM(COALESCE(s.spoil_qty_buy,0)) AS spoil_qty_buy,
                    SUM(COALESCE(s.spoil_qty_content,0)) AS spoil_qty_content,
                    SUM(COALESCE(s.waste_qty_buy,0)) AS waste_qty_buy,
                    SUM(COALESCE(s.waste_qty_content,0)) AS waste_qty_content,
                    SUM(COALESCE(s.process_loss_qty_buy,0)) AS process_loss_qty_buy,
                    SUM(COALESCE(s.process_loss_qty_content,0)) AS process_loss_qty_content,
                    SUM(COALESCE(s.variance_qty_buy,0)) AS variance_qty_buy,
                    SUM(COALESCE(s.variance_qty_content,0)) AS variance_qty_content,
                    SUM(COALESCE(s.adjustment_plus_qty_buy,0)) AS adjustment_plus_qty_buy,
                    SUM(COALESCE(s.adjustment_plus_qty_content,0)) AS adjustment_plus_qty_content,
                    SUM(COALESCE(s.adjustment_qty_buy,0)) AS adjustment_qty_buy,
                    SUM(COALESCE(s.adjustment_qty_content,0)) AS adjustment_qty_content,
                    SUM(COALESCE(s.closing_qty_buy,0)) AS closing_qty_buy,
                    SUM(COALESCE(s.closing_qty_content,0)) AS closing_qty_content,
                    SUM(COALESCE(s.total_value,0)) AS total_value,
                    SUM(COALESCE(s.waste_total_value,0)) AS waste_total_value,
                    SUM(COALESCE(s.spoilage_total_value,0)) AS spoilage_total_value,
                    SUM(COALESCE(s.process_loss_total_value,0)) AS process_loss_total_value,
                    SUM(COALESCE(s.variance_total_value,0)) AS variance_total_value,
                    SUM(COALESCE(s.adjustment_plus_total_value,0)) AS adjustment_plus_total_value,
                    SUM(COALESCE(s.mutation_count,0)) AS mutation_count
                FROM inv_division_daily_rollup s
                JOIN tmp_division_profile_key_map m
                  ON m.old_profile_key = s.profile_key
                 AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
                 AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
                 AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
                 AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
                JOIN inv_division_daily_rollup ex
                  ON ex.month_key = s.month_key
                 AND ex.movement_date = s.movement_date
                 AND ex.division_id = s.division_id
                 AND ex.destination_type = s.destination_type
                 AND ex.stock_domain = s.stock_domain
                 AND ex.item_id = s.item_id
                 AND COALESCE(ex.material_id,0) = COALESCE(s.material_id,0)
                 AND ex.buy_uom_id = s.buy_uom_id
                 AND ex.content_uom_id = s.content_uom_id
                 AND ex.profile_key = m.new_profile_key
                GROUP BY
                    s.month_key, s.movement_date, s.division_id, s.destination_type, s.stock_domain,
                    s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, m.new_profile_key
            ) g
              ON t.month_key = g.month_key
             AND t.movement_date = g.movement_date
             AND t.division_id = g.division_id
             AND t.destination_type = g.destination_type
             AND t.stock_domain = g.stock_domain
             AND t.item_id = g.item_id
             AND COALESCE(t.material_id,0) = COALESCE(g.material_id,0)
             AND t.buy_uom_id = g.buy_uom_id
             AND t.content_uom_id = g.content_uom_id
             AND t.profile_key = g.new_profile_key
            SET
              t.opening_qty_buy = ROUND(COALESCE(t.opening_qty_buy,0) + g.opening_qty_buy, 4),
              t.opening_qty_content = ROUND(COALESCE(t.opening_qty_content,0) + g.opening_qty_content, 4),
              t.in_qty_buy = ROUND(COALESCE(t.in_qty_buy,0) + g.in_qty_buy, 4),
              t.in_qty_content = ROUND(COALESCE(t.in_qty_content,0) + g.in_qty_content, 4),
              t.out_qty_buy = ROUND(COALESCE(t.out_qty_buy,0) + g.out_qty_buy, 4),
              t.out_qty_content = ROUND(COALESCE(t.out_qty_content,0) + g.out_qty_content, 4),
              t.discarded_qty_buy = ROUND(COALESCE(t.discarded_qty_buy,0) + g.discarded_qty_buy, 4),
              t.discarded_qty_content = ROUND(COALESCE(t.discarded_qty_content,0) + g.discarded_qty_content, 4),
              t.spoil_qty_buy = ROUND(COALESCE(t.spoil_qty_buy,0) + g.spoil_qty_buy, 4),
              t.spoil_qty_content = ROUND(COALESCE(t.spoil_qty_content,0) + g.spoil_qty_content, 4),
              t.waste_qty_buy = ROUND(COALESCE(t.waste_qty_buy,0) + g.waste_qty_buy, 4),
              t.waste_qty_content = ROUND(COALESCE(t.waste_qty_content,0) + g.waste_qty_content, 4),
              t.process_loss_qty_buy = ROUND(COALESCE(t.process_loss_qty_buy,0) + g.process_loss_qty_buy, 4),
              t.process_loss_qty_content = ROUND(COALESCE(t.process_loss_qty_content,0) + g.process_loss_qty_content, 4),
              t.variance_qty_buy = ROUND(COALESCE(t.variance_qty_buy,0) + g.variance_qty_buy, 4),
              t.variance_qty_content = ROUND(COALESCE(t.variance_qty_content,0) + g.variance_qty_content, 4),
              t.adjustment_plus_qty_buy = ROUND(COALESCE(t.adjustment_plus_qty_buy,0) + g.adjustment_plus_qty_buy, 4),
              t.adjustment_plus_qty_content = ROUND(COALESCE(t.adjustment_plus_qty_content,0) + g.adjustment_plus_qty_content, 4),
              t.adjustment_qty_buy = ROUND(COALESCE(t.adjustment_qty_buy,0) + g.adjustment_qty_buy, 4),
              t.adjustment_qty_content = ROUND(COALESCE(t.adjustment_qty_content,0) + g.adjustment_qty_content, 4),
              t.closing_qty_buy = ROUND(COALESCE(t.closing_qty_buy,0) + g.closing_qty_buy, 4),
              t.closing_qty_content = ROUND(COALESCE(t.closing_qty_content,0) + g.closing_qty_content, 4),
              t.total_value = ROUND(COALESCE(t.total_value,0) + g.total_value, 2),
              t.waste_total_value = ROUND(COALESCE(t.waste_total_value,0) + g.waste_total_value, 2),
              t.spoilage_total_value = ROUND(COALESCE(t.spoilage_total_value,0) + g.spoilage_total_value, 2),
              t.process_loss_total_value = ROUND(COALESCE(t.process_loss_total_value,0) + g.process_loss_total_value, 2),
              t.variance_total_value = ROUND(COALESCE(t.variance_total_value,0) + g.variance_total_value, 2),
              t.adjustment_plus_total_value = ROUND(COALESCE(t.adjustment_plus_total_value,0) + g.adjustment_plus_total_value, 2),
              t.mutation_count = COALESCE(t.mutation_count,0) + g.mutation_count,
              t.avg_cost_per_content = CASE
                WHEN ABS(COALESCE(t.closing_qty_content,0) + g.closing_qty_content) > 0.000001 THEN
                    ROUND((COALESCE(t.total_value,0) + g.total_value) / (COALESCE(t.closing_qty_content,0) + g.closing_qty_content), 6)
                ELSE COALESCE(t.avg_cost_per_content,0)
              END,
              t.updated_at = NOW()
        ");

        // Daily delete merged sources.
        $summary['daily_deleted'] = execOrThrow($db, "
            DELETE s
            FROM inv_division_daily_rollup s
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = s.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
            JOIN inv_division_daily_rollup t
              ON t.month_key = s.month_key
             AND t.movement_date = s.movement_date
             AND t.division_id = s.division_id
             AND t.destination_type = s.destination_type
             AND t.stock_domain = s.stock_domain
             AND t.item_id = s.item_id
             AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
             AND t.buy_uom_id = s.buy_uom_id
             AND t.content_uom_id = s.content_uom_id
             AND t.profile_key = m.new_profile_key
        ");

        // Daily non-conflict update.
        $summary['daily_updated'] = execOrThrow($db, "
            UPDATE inv_division_daily_rollup s
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = s.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
            LEFT JOIN inv_division_daily_rollup t
              ON t.month_key = s.month_key
             AND t.movement_date = s.movement_date
             AND t.division_id = s.division_id
             AND t.destination_type = s.destination_type
             AND t.stock_domain = s.stock_domain
             AND t.item_id = s.item_id
             AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
             AND t.buy_uom_id = s.buy_uom_id
             AND t.content_uom_id = s.content_uom_id
             AND t.profile_key = m.new_profile_key
            SET s.profile_key = m.new_profile_key,
                s.updated_at = NOW()
            WHERE t.id IS NULL
        ");

        // Movement update (division only).
        $summary['movement_updated'] = execOrThrow($db, "
            UPDATE inv_stock_movement_log l
            JOIN tmp_division_profile_key_map m
              ON m.old_profile_key = l.profile_key
             AND COALESCE(m.item_id,0) = COALESCE(l.item_id,0)
             AND COALESCE(m.material_id,0) = COALESCE(l.material_id,0)
             AND COALESCE(m.buy_uom_id,0) = COALESCE(l.buy_uom_id,0)
             AND COALESCE(m.content_uom_id,0) = COALESCE(l.content_uom_id,0)
             AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(l.profile_content_per_buy,0),6)
             AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(l.profile_name,'')))
             AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(l.profile_brand,'')))
             AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(l.profile_description,'')))
             AND ((m.profile_expired_date IS NULL AND l.profile_expired_date IS NULL) OR (m.profile_expired_date <=> l.profile_expired_date))
            SET l.profile_key = m.new_profile_key
            WHERE l.movement_scope = 'DIVISION'
        ");

        $db->commit();
    }

    $summary['remaining_opening'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_stock_opening_snapshot x
      JOIN tmp_catalog_canonical cc
        ON cc.item_id = x.item_id
       AND cc.material_id = COALESCE(x.material_id,0)
       AND cc.buy_uom_id = x.buy_uom_id
       AND cc.content_uom_id = x.content_uom_id
       AND cc.profile_content_per_buy = ROUND(COALESCE(x.profile_content_per_buy,0),6)
       AND cc.profile_name = UPPER(TRIM(COALESCE(x.profile_name,'')))
       AND cc.profile_brand = UPPER(TRIM(COALESCE(x.profile_brand,'')))
       AND cc.profile_description = UPPER(TRIM(COALESCE(x.profile_description,'')))
       AND ((cc.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (cc.profile_expired_date <=> x.profile_expired_date))
      WHERE x.profile_key <> cc.canonical_opening_profile_key
    ");

    $summary['remaining_balance'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_stock_balance x
      JOIN tmp_catalog_canonical cc
        ON cc.item_id = x.item_id
       AND cc.material_id = COALESCE(x.material_id,0)
       AND cc.buy_uom_id = x.buy_uom_id
       AND cc.content_uom_id = x.content_uom_id
       AND cc.profile_content_per_buy = ROUND(COALESCE(x.profile_content_per_buy,0),6)
       AND cc.profile_name = UPPER(TRIM(COALESCE(x.profile_name,'')))
       AND cc.profile_brand = UPPER(TRIM(COALESCE(x.profile_brand,'')))
       AND cc.profile_description = UPPER(TRIM(COALESCE(x.profile_description,'')))
       AND ((cc.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (cc.profile_expired_date <=> x.profile_expired_date))
      WHERE x.profile_key <> cc.canonical_profile_key
    ");

    $summary['remaining_daily'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_division_daily_rollup x
      JOIN tmp_catalog_canonical cc
        ON cc.item_id = x.item_id
       AND cc.material_id = COALESCE(x.material_id,0)
       AND cc.buy_uom_id = x.buy_uom_id
       AND cc.content_uom_id = x.content_uom_id
       AND cc.profile_content_per_buy = ROUND(COALESCE(x.profile_content_per_buy,0),6)
       AND cc.profile_name = UPPER(TRIM(COALESCE(x.profile_name,'')))
       AND cc.profile_brand = UPPER(TRIM(COALESCE(x.profile_brand,'')))
       AND cc.profile_description = UPPER(TRIM(COALESCE(x.profile_description,'')))
       AND ((cc.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (cc.profile_expired_date <=> x.profile_expired_date))
      WHERE x.profile_key <> cc.canonical_profile_key
    ");

    $summary['remaining_movement'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_stock_movement_log x
        JOIN tmp_catalog_canonical cc
          ON cc.item_id = x.item_id
         AND cc.material_id = COALESCE(x.material_id,0)
         AND cc.buy_uom_id = x.buy_uom_id
         AND cc.content_uom_id = x.content_uom_id
         AND cc.profile_content_per_buy = ROUND(COALESCE(x.profile_content_per_buy,0),6)
         AND cc.profile_name = UPPER(TRIM(COALESCE(x.profile_name,'')))
         AND cc.profile_brand = UPPER(TRIM(COALESCE(x.profile_brand,'')))
         AND cc.profile_description = UPPER(TRIM(COALESCE(x.profile_description,'')))
         AND ((cc.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (cc.profile_expired_date <=> x.profile_expired_date))
        WHERE x.movement_scope = 'DIVISION'
          AND x.profile_key <> cc.canonical_profile_key
    ");
} catch (Throwable $e) {
  if ($apply) {
        $db->rollback();
    }
    $summary['status'] = 'failed';
    $summary['error'] = $e->getMessage();
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
