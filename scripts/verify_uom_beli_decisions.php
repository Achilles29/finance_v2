<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$databaseConfig = $root . '/application/config/database.php';
$sourceFile = $root . '/docs/_UOM_BELI.MD';
$reportFile = $root . '/docs/2026-06-08u_uom_beli_verification.md';

if (!is_file($databaseConfig) || !is_file($sourceFile)) {
    fwrite(STDERR, "Missing required files.\n");
    exit(1);
}

if (!defined('BASEPATH')) {
    define('BASEPATH', $root . '/system/');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

require $databaseConfig;

if (!isset($db['default'])) {
    fwrite(STDERR, "Database config not found.\n");
    exit(1);
}

$cfg = $db['default'];
$mysqli = mysqli_init();
$mysqli->real_connect(
    $cfg['hostname'],
    $cfg['username'],
    $cfg['password'],
    $cfg['database']
);
$mysqli->set_charset('utf8mb4');

$choices = loadChoices($sourceFile);
$materials = loadMaterialMap($mysqli);
$catalogByMaterial = loadCatalogSummary($mysqli);
$itemsByMaterial = loadItemSummary($mysqli);
$observedByMaterial = loadObservedSummary($mysqli);
$mismatchByMaterial = loadExactMismatchSummary($mysqli);

$rows = [];
foreach ($choices as $choice) {
    $normalized = normalizeName($choice['material_name']);
    $material = $materials[$normalized] ?? null;
    $row = [
        'choice_material_name' => $choice['material_name'],
        'chosen_buy_uom_code' => $choice['buy_uom_code'],
        'material_id' => $material['id'] ?? null,
        'material_code' => $material['material_code'] ?? '',
        'active_catalog_buy_uoms' => '',
        'active_item_buy_uoms' => '',
        'observed_active_uoms' => '',
        'exact_mismatch_groups' => 0,
        'status' => 'REVIEW_NOT_FOUND',
        'note' => 'Nama material belum ketemu exact ke mst_material.',
    ];

    if ($material) {
        $materialId = (int) $material['id'];
        $catalog = $catalogByMaterial[$materialId] ?? null;
        $item = $itemsByMaterial[$materialId] ?? null;
        $observed = $observedByMaterial[$materialId] ?? null;
        $mismatch = $mismatchByMaterial[$materialId] ?? 0;

        $row['active_catalog_buy_uoms'] = $catalog['uoms'] ?? '';
        $row['active_item_buy_uoms'] = $item['uoms'] ?? '';
        $row['observed_active_uoms'] = $observed['uoms'] ?? '';
        $row['exact_mismatch_groups'] = $mismatch;

        $row = classifyChoice($row, $catalog, $item, $observed, $mismatch);
    }

    $rows[] = $row;
}

usort($rows, static function (array $a, array $b): int {
    $order = [
        'REVIEW_NOT_FOUND' => 0,
        'REVIEW_NO_CATALOG_MATCH' => 1,
        'REVIEW_MIXED_CATALOG' => 2,
        'OK_WITH_LEGACY_CLEANUP' => 3,
        'OK_STRONG' => 4,
    ];
    $left = $order[$a['status']] ?? 99;
    $right = $order[$b['status']] ?? 99;
    if ($left !== $right) {
        return $left <=> $right;
    }

    return strcmp($a['choice_material_name'], $b['choice_material_name']);
});

$statusCounts = [];
foreach ($rows as $row) {
    $statusCounts[$row['status']] = ($statusCounts[$row['status']] ?? 0) + 1;
}

$markdown = [];
$markdown[] = '# Verifikasi UOM Beli Kanonik';
$markdown[] = '';
$markdown[] = 'Tanggal: ' . date('Y-m-d H:i:s');
$markdown[] = '';
$markdown[] = 'Sumber keputusan: `docs/_UOM_BELI.MD`';
$markdown[] = '';
$markdown[] = '## Ringkasan';
$markdown[] = '';
$markdown[] = '| Status | Total | Arti |';
$markdown[] = '| --- | ---: | --- |';
$statusMeaning = [
    'OK_STRONG' => 'Pilihan sudah jadi satu-satunya buy UOM aktif di catalog.',
    'OK_WITH_LEGACY_CLEANUP' => 'Pilihan sudah didukung catalog aktif, tapi masih ada jejak UOM lain di tabel aktif.',
    'REVIEW_MIXED_CATALOG' => 'Pilihan ada di catalog aktif, tapi sibling buy UOM aktif lain masih hidup.',
    'REVIEW_NO_CATALOG_MATCH' => 'Pilihan belum cocok dengan buy UOM catalog aktif sekarang.',
    'REVIEW_NOT_FOUND' => 'Nama material belum ketemu exact ke mst_material.',
];
foreach ($statusMeaning as $status => $meaning) {
    $markdown[] = sprintf('| %s | %d | %s |', $status, $statusCounts[$status] ?? 0, $meaning);
}

$markdown[] = '';
$markdown[] = '## Detail';
$markdown[] = '';
$markdown[] = '| Material | Pilihan | Material ID | Catalog Aktif | Item Aktif | Jejak Tabel Aktif | Exact Mismatch | Status | Catatan |';
$markdown[] = '| --- | --- | ---: | --- | --- | --- | ---: | --- | --- |';
foreach ($rows as $row) {
    $markdown[] = sprintf(
        '| %s | %s | %s | %s | %s | %s | %d | %s | %s |',
        escapeMd($row['choice_material_name']),
        escapeMd($row['chosen_buy_uom_code']),
        $row['material_id'] === null ? '-' : (string) $row['material_id'],
        escapeMd($row['active_catalog_buy_uoms'] ?: '-'),
        escapeMd($row['active_item_buy_uoms'] ?: '-'),
        escapeMd($row['observed_active_uoms'] ?: '-'),
        (int) $row['exact_mismatch_groups'],
        $row['status'],
        escapeMd($row['note'])
    );
}

$markdown[] = '';
$markdown[] = '## Prioritas Praktis';
$markdown[] = '';
$markdown[] = '1. `REVIEW_NO_CATALOG_MATCH`: jangan direpair massal dulu, karena pilihan belum hidup di catalog aktif.';
$markdown[] = '2. `REVIEW_MIXED_CATALOG`: pilihan boleh jadi benar, tapi sibling UOM aktif lain perlu diputuskan nasibnya.';
$markdown[] = '3. `OK_WITH_LEGACY_CLEANUP`: keputusan sudah masuk akal, tinggal sapu row legacy di movement/monthly/opening/lot.';
$markdown[] = '4. `OK_STRONG`: aman dijadikan dasar repair massal.';
$markdown[] = '';

file_put_contents($reportFile, implode(PHP_EOL, $markdown) . PHP_EOL);

echo "Report written to {$reportFile}\n";

function loadChoices(string $sourceFile): array
{
    $rows = [];
    foreach (file($sourceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = preg_split('/\t+/', trim($line));
        if (count($parts) < 2) {
            continue;
        }
        $rows[] = [
            'material_name' => trim($parts[0]),
            'buy_uom_code' => trim($parts[1]),
        ];
    }

    return $rows;
}

function loadMaterialMap(mysqli $mysqli): array
{
    $sql = "SELECT id, material_code, material_name FROM mst_material";
    $res = $mysqli->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $map[normalizeName($row['material_name'])] = $row;
    }

    return $map;
}

function loadCatalogSummary(mysqli $mysqli): array
{
    $sql = "
        SELECT
            c.material_id,
            GROUP_CONCAT(DISTINCT CONCAT(c.buy_uom_id, ':', COALESCE(u.code, '?')) ORDER BY c.buy_uom_id SEPARATOR ', ') AS uoms,
            GROUP_CONCAT(DISTINCT CONCAT(COALESCE(u.code, '?'), '||', COALESCE(u.name, '?')) ORDER BY c.buy_uom_id SEPARATOR '|') AS codes
        FROM mst_purchase_catalog c
        LEFT JOIN mst_uom u ON u.id = c.buy_uom_id
        WHERE COALESCE(c.is_active, 1) = 1
          AND COALESCE(c.material_id, 0) > 0
        GROUP BY c.material_id
    ";
    return fetchSummaryMap($mysqli, $sql);
}

function loadItemSummary(mysqli $mysqli): array
{
    $sql = "
        SELECT
            i.material_id,
            GROUP_CONCAT(DISTINCT CONCAT(i.buy_uom_id, ':', COALESCE(u.code, '?')) ORDER BY i.buy_uom_id SEPARATOR ', ') AS uoms,
            GROUP_CONCAT(DISTINCT CONCAT(COALESCE(u.code, '?'), '||', COALESCE(u.name, '?')) ORDER BY i.buy_uom_id SEPARATOR '|') AS codes
        FROM mst_item i
        LEFT JOIN mst_uom u ON u.id = i.buy_uom_id
        WHERE COALESCE(i.is_active, 1) = 1
          AND COALESCE(i.material_id, 0) > 0
        GROUP BY i.material_id
    ";
    return fetchSummaryMap($mysqli, $sql);
}

function loadObservedSummary(mysqli $mysqli): array
{
    $sql = "
        SELECT
            x.material_id,
            GROUP_CONCAT(DISTINCT CONCAT(x.buy_uom_id, ':', COALESCE(u.code, '?')) ORDER BY x.buy_uom_id SEPARATOR ', ') AS uoms,
            GROUP_CONCAT(DISTINCT CONCAT(COALESCE(u.code, '?'), '||', COALESCE(u.name, '?')) ORDER BY x.buy_uom_id SEPARATOR '|') AS codes
        FROM (
            SELECT material_id, buy_uom_id FROM mst_purchase_catalog WHERE COALESCE(is_active, 1) = 1 AND COALESCE(material_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM inv_division_monthly_stock WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM inv_stock_movement_log WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM inv_material_fifo_lot WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM inv_division_stock_opening_snapshot WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM pur_division_request_line WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM pur_purchase_order_line WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM pur_purchase_receipt_line WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM pur_store_request_line WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
            UNION ALL
            SELECT material_id, buy_uom_id FROM pur_store_request_fulfillment_line WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0
        ) x
        LEFT JOIN mst_uom u ON u.id = x.buy_uom_id
        GROUP BY x.material_id
    ";
    return fetchSummaryMap($mysqli, $sql);
}

function loadExactMismatchSummary(mysqli $mysqli): array
{
    $sql = "
        SELECT z.material_id, COUNT(*) AS mismatch_groups
        FROM (
            SELECT r.source_table, r.material_id, r.profile_key
            FROM (
                SELECT 'inv_division_stock_opening_snapshot' AS source_table, material_id, buy_uom_id, content_uom_id, profile_key
                FROM inv_division_stock_opening_snapshot
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
                UNION ALL
                SELECT 'inv_division_monthly_stock', material_id, buy_uom_id, content_uom_id, profile_key
                FROM inv_division_monthly_stock
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
                UNION ALL
                SELECT 'inv_stock_movement_log', material_id, buy_uom_id, content_uom_id, profile_key
                FROM inv_stock_movement_log
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
                UNION ALL
                SELECT 'inv_material_fifo_lot', material_id, buy_uom_id, content_uom_id, profile_key
                FROM inv_material_fifo_lot
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
                UNION ALL
                SELECT 'pur_division_request_line', material_id, buy_uom_id, content_uom_id, profile_key
                FROM pur_division_request_line
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
                UNION ALL
                SELECT 'pur_purchase_order_line', material_id, buy_uom_id, content_uom_id, profile_key
                FROM pur_purchase_order_line
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
                UNION ALL
                SELECT 'pur_purchase_receipt_line', material_id, buy_uom_id, content_uom_id, profile_key
                FROM pur_purchase_receipt_line
                WHERE COALESCE(material_id, 0) > 0 AND COALESCE(buy_uom_id, 0) > 0 AND COALESCE(profile_key, '') <> ''
            ) r
            JOIN mst_purchase_catalog c
              ON BINARY c.profile_key = BINARY r.profile_key
             AND COALESCE(c.material_id, 0) = COALESCE(r.material_id, 0)
            WHERE COALESCE(r.buy_uom_id, 0) <> COALESCE(c.buy_uom_id, 0)
               OR COALESCE(r.content_uom_id, 0) <> COALESCE(c.content_uom_id, 0)
            GROUP BY r.source_table, r.material_id, r.profile_key
        ) z
        GROUP BY z.material_id
    ";

    $res = $mysqli->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $map[(int) $row['material_id']] = (int) $row['mismatch_groups'];
    }

    return $map;
}

function fetchSummaryMap(mysqli $mysqli, string $sql): array
{
    $res = $mysqli->query($sql);
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $map[(int) $row['material_id']] = $row;
    }

    return $map;
}

function classifyChoice(array $row, ?array $catalog, ?array $item, ?array $observed, int $mismatch): array
{
    $chosen = strtoupper(trim($row['chosen_buy_uom_code']));
    $catalogCodes = explodeCodes($catalog['codes'] ?? '');
    $observedCodes = explodeCodes($observed['codes'] ?? '');
    $catalogEntryCount = countUomEntries($catalog['uoms'] ?? '');
    $observedEntryCount = countUomEntries($observed['uoms'] ?? '');

    if (!$catalog || empty($catalogCodes)) {
        $row['status'] = 'REVIEW_NO_CATALOG_MATCH';
        $row['note'] = 'Belum ada catalog aktif yang bisa dijadikan pegangan.';
        return $row;
    }

    if (!in_array($chosen, $catalogCodes, true)) {
        $row['status'] = 'REVIEW_NO_CATALOG_MATCH';
        $row['note'] = 'Pilihan belum cocok dengan buy UOM catalog aktif saat ini.';
        return $row;
    }

    $hasMixedCatalog = $catalogEntryCount > 1;
    $hasObservedLegacy = $observedEntryCount > 1 || $mismatch > 0;

    if ($hasMixedCatalog) {
        $row['status'] = 'REVIEW_MIXED_CATALOG';
        $row['note'] = 'Pilihan ada di catalog aktif, tetapi sibling buy UOM aktif lain masih hidup.';
        return $row;
    }

    if ($hasObservedLegacy) {
        $row['status'] = 'OK_WITH_LEGACY_CLEANUP';
        $row['note'] = 'Catalog aktif sudah tunggal, tetapi row legacy di tabel aktif masih perlu disapu.';
        return $row;
    }

    $row['status'] = 'OK_STRONG';
    $row['note'] = 'Pilihan sudah konsisten di catalog aktif dan tidak terlihat konflik aktif lain.';
    return $row;
}

function explodeCodes(string $codes): array
{
    if ($codes === '') {
        return [];
    }

    $parts = [];
    foreach (explode('|', strtoupper($codes)) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        foreach (explode('||', $part) as $alias) {
            $alias = trim($alias);
            if ($alias !== '') {
                $parts[] = $alias;
            }
        }
    }
    $parts = array_values(array_unique($parts));

    return $parts;
}

function countUomEntries(string $uoms): int
{
    if ($uoms === '') {
        return 0;
    }

    $parts = array_map('trim', explode(',', $uoms));
    $parts = array_values(array_filter(array_unique($parts), static function (string $value): bool {
        return $value !== '';
    }));

    return count($parts);
}

function normalizeName(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value));
    return mb_strtoupper($value, 'UTF-8');
}

function escapeMd(string $value): string
{
    return str_replace('|', '\\|', $value);
}
