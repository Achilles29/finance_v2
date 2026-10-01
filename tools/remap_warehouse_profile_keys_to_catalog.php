<?php
declare(strict_types=1);

/**
 * Remap historical warehouse profile_key to canonical mst_purchase_catalog.profile_key
 * by exact identity match.
 *
 * Strategy:
 * - Dry-run by default (hanya hitung kandidat + konflik).
 * - --apply hanya update baris NON-conflict agar aman.
 * - Conflict rows dilaporkan untuk ditangani merge terukur.
 *
 * Usage:
 *   php tools/remap_warehouse_profile_keys_to_catalog.php
 *   php tools/remap_warehouse_profile_keys_to_catalog.php --apply
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
    'balance_updated' => 0,
    'daily_updated' => 0,
    'movement_updated' => 0,
    'remaining_opening' => 0,
    'remaining_balance' => 0,
    'remaining_daily' => 0,
    'remaining_movement' => 0,
    'status' => 'ok',
    'error' => null,
];

try {
    execOrThrow($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_profile_key_map');
    execOrThrow($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_catalog_canonical');

    execOrThrow($db, "
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
    ");

    execOrThrow($db, "
        INSERT INTO tmp_catalog_canonical (
          item_id, material_id, buy_uom_id, content_uom_id, profile_content_per_buy,
          profile_name, profile_brand, profile_description, profile_expired_date,
          canonical_profile_key, canonical_opening_profile_key
        )
        SELECT
          COALESCE(c1.item_id,0) AS item_id,
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
    ");

    execOrThrow($db, "
        CREATE TEMPORARY TABLE tmp_warehouse_profile_key_map (
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
    ");

    execOrThrow($db, "
        INSERT INTO tmp_warehouse_profile_key_map (
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
          FROM inv_warehouse_stock_opening_snapshot
          UNION ALL
          SELECT 'balance' AS source_table, profile_key, item_id, 0 AS material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
          FROM inv_warehouse_stock_balance
          UNION ALL
          SELECT 'daily' AS source_table, profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
          FROM inv_warehouse_daily_rollup
          UNION ALL
          SELECT 'movement' AS source_table, profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy
          FROM inv_stock_movement_log
          WHERE movement_scope = 'WAREHOUSE'
        ) x
        JOIN tmp_catalog_canonical cc
          ON COALESCE(cc.item_id,0) = COALESCE(x.item_id,0)
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
    ");

    $summary['map_rows'] = scalar($db, 'SELECT COUNT(*) FROM tmp_warehouse_profile_key_map');

    $summary['opening_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_stock_opening_snapshot s
        JOIN tmp_warehouse_profile_key_map m
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
        FROM inv_warehouse_stock_balance s
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
        WHERE s.profile_key <> m.new_profile_key
    ");
    $summary['daily_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_daily_rollup s
        JOIN tmp_warehouse_profile_key_map m
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
        WHERE s.profile_key <> m.new_profile_key
    ");
    $summary['movement_candidates'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_stock_movement_log s
        JOIN tmp_warehouse_profile_key_map m
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
        WHERE s.movement_scope = 'WAREHOUSE'
          AND s.profile_key <> m.new_profile_key
    ");

    $summary['opening_conflicts'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_stock_opening_snapshot s
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
        JOIN inv_warehouse_stock_opening_snapshot t
          ON t.snapshot_month = s.snapshot_month
         AND t.stock_domain = s.stock_domain
         AND COALESCE(t.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(t.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(t.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND t.profile_key = LEFT(m.new_profile_key,40)
        WHERE s.profile_key <> LEFT(m.new_profile_key,40)
    ");
    $summary['balance_conflicts'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_stock_balance s
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
        JOIN inv_warehouse_stock_balance t
          ON COALESCE(t.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(t.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(t.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND t.profile_key = m.new_profile_key
        WHERE s.profile_key <> m.new_profile_key
    ");
    $summary['daily_conflicts'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_daily_rollup s
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = s.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
        JOIN inv_warehouse_daily_rollup t
          ON t.movement_date = s.movement_date
         AND t.stock_domain = s.stock_domain
         AND COALESCE(t.item_id,0) = COALESCE(s.item_id,0)
         AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
         AND COALESCE(t.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
         AND COALESCE(t.content_uom_id,0) = COALESCE(s.content_uom_id,0)
         AND t.profile_key = m.new_profile_key
        WHERE s.profile_key <> m.new_profile_key
    ");

    if ($apply) {
        $db->begin_transaction();
        try {
            $summary['opening_updated'] = execOrThrow($db, "
                UPDATE IGNORE inv_warehouse_stock_opening_snapshot s
                JOIN tmp_warehouse_profile_key_map m
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
                LEFT JOIN inv_warehouse_stock_opening_snapshot t
                  ON t.snapshot_month = s.snapshot_month
                 AND t.stock_domain = s.stock_domain
                 AND COALESCE(t.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
                 AND COALESCE(t.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(t.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND t.profile_key = LEFT(m.new_profile_key,40)
                SET s.profile_key = LEFT(m.new_profile_key,40),
                    s.updated_at = NOW()
                WHERE s.profile_key <> LEFT(m.new_profile_key,40)
                  AND t.id IS NULL
            ");

            $summary['balance_updated'] = execOrThrow($db, "
                UPDATE IGNORE inv_warehouse_stock_balance s
                JOIN tmp_warehouse_profile_key_map m
                  ON m.old_profile_key = s.profile_key
                 AND COALESCE(m.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(m.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(m.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(s.profile_content_per_buy,0),6)
                 AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(s.profile_name,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(s.profile_brand,'')))
                 AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(s.profile_description,'')))
                 AND ((m.profile_expired_date IS NULL AND s.profile_expired_date IS NULL) OR (m.profile_expired_date <=> s.profile_expired_date))
                LEFT JOIN inv_warehouse_stock_balance t
                  ON COALESCE(t.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(t.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(t.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND t.profile_key = m.new_profile_key
                SET s.profile_key = m.new_profile_key,
                    s.updated_at = NOW()
                WHERE s.profile_key <> m.new_profile_key
                  AND t.id IS NULL
            ");

            $summary['daily_updated'] = execOrThrow($db, "
                UPDATE IGNORE inv_warehouse_daily_rollup s
                JOIN tmp_warehouse_profile_key_map m
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
                LEFT JOIN inv_warehouse_daily_rollup t
                  ON t.movement_date = s.movement_date
                 AND t.stock_domain = s.stock_domain
                 AND COALESCE(t.item_id,0) = COALESCE(s.item_id,0)
                 AND COALESCE(t.material_id,0) = COALESCE(s.material_id,0)
                 AND COALESCE(t.buy_uom_id,0) = COALESCE(s.buy_uom_id,0)
                 AND COALESCE(t.content_uom_id,0) = COALESCE(s.content_uom_id,0)
                 AND t.profile_key = m.new_profile_key
                SET s.profile_key = m.new_profile_key,
                    s.updated_at = NOW()
                WHERE s.profile_key <> m.new_profile_key
                  AND t.id IS NULL
            ");

            $summary['movement_updated'] = execOrThrow($db, "
                UPDATE inv_stock_movement_log s
                JOIN tmp_warehouse_profile_key_map m
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
                SET s.profile_key = m.new_profile_key
                WHERE s.movement_scope = 'WAREHOUSE'
                  AND s.profile_key <> m.new_profile_key
            ");

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    $summary['remaining_opening'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_stock_opening_snapshot x
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = x.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(x.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(x.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(x.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(x.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(x.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(x.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(x.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(x.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (m.profile_expired_date <=> x.profile_expired_date))
        WHERE x.profile_key <> LEFT(m.new_profile_key, 40)
    ");
    $summary['remaining_balance'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_stock_balance x
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = x.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(x.item_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(x.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(x.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(x.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(x.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(x.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(x.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (m.profile_expired_date <=> x.profile_expired_date))
        WHERE x.profile_key <> m.new_profile_key
    ");
    $summary['remaining_daily'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_warehouse_daily_rollup x
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = x.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(x.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(x.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(x.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(x.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(x.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(x.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(x.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(x.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (m.profile_expired_date <=> x.profile_expired_date))
        WHERE x.profile_key <> m.new_profile_key
    ");
    $summary['remaining_movement'] = scalar($db, "
        SELECT COUNT(*)
        FROM inv_stock_movement_log x
        JOIN tmp_warehouse_profile_key_map m
          ON m.old_profile_key = x.profile_key
         AND COALESCE(m.item_id,0) = COALESCE(x.item_id,0)
         AND COALESCE(m.material_id,0) = COALESCE(x.material_id,0)
         AND COALESCE(m.buy_uom_id,0) = COALESCE(x.buy_uom_id,0)
         AND COALESCE(m.content_uom_id,0) = COALESCE(x.content_uom_id,0)
         AND ROUND(COALESCE(m.profile_content_per_buy,0),6) = ROUND(COALESCE(x.profile_content_per_buy,0),6)
         AND UPPER(TRIM(COALESCE(m.profile_name,''))) = UPPER(TRIM(COALESCE(x.profile_name,'')))
         AND UPPER(TRIM(COALESCE(m.profile_brand,''))) = UPPER(TRIM(COALESCE(x.profile_brand,'')))
         AND UPPER(TRIM(COALESCE(m.profile_description,''))) = UPPER(TRIM(COALESCE(x.profile_description,'')))
         AND ((m.profile_expired_date IS NULL AND x.profile_expired_date IS NULL) OR (m.profile_expired_date <=> x.profile_expired_date))
        WHERE x.movement_scope = 'WAREHOUSE'
          AND x.profile_key <> m.new_profile_key
    ");
} catch (Throwable $e) {
    $summary['status'] = 'error';
    $summary['error'] = $e->getMessage();
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($summary['status'] === 'ok' ? 0 : 1);
