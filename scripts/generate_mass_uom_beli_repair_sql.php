<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$sourceFile = $root . '/docs/_UOM_BELI.MD';
$targetFile = $root . '/sql/2026-06-08w_repair_all_material_canonical_uom_from_doc.sql';

if (!is_file($sourceFile)) {
    fwrite(STDERR, "Source file not found: {$sourceFile}\n");
    exit(1);
}

$choices = [];
foreach (file($sourceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $parts = preg_split('/\t+/', trim($line));
    if (count($parts) < 2) {
        continue;
    }
    $choices[] = [
        'material_name' => trim($parts[0]),
        'buy_uom_code' => trim($parts[1]),
    ];
}

if (!$choices) {
    fwrite(STDERR, "No choices found in {$sourceFile}\n");
    exit(1);
}

$sql = [];
$sql[] = 'SET NAMES utf8mb4;';
$sql[] = '';
$sql[] = '-- ============================================================';
$sql[] = '-- File   : 2026-06-08w_repair_all_material_canonical_uom_from_doc.sql';
$sql[] = '-- Tujuan :';
$sql[] = '-- 1) Menormalkan UOM beli kanonik untuk SEMUA material yang';
$sql[] = '--    sudah diputuskan di docs/_UOM_BELI.MD';
$sql[] = '-- 2) Menjaga qty_content sebagai source of truth, lalu';
$sql[] = '--    menghitung ulang qty_buy berdasarkan content_per_buy';
$sql[] = '-- 3) Menyapu master + tabel aktif agar siklus PO / SR /';
$sql[] = '--    monthly / movement / fifo / POS tidak pecah UOM lagi';
$sql[] = '--';
$sql[] = '-- Catatan penting:';
$sql[] = '-- - Script ini memang intervensi data secara luas';
$sql[] = '-- - Backup temporary table dibuat untuk tabel aktif utama';
$sql[] = '-- - content_per_buy per profile dipertahankan bila profile';
$sql[] = '--   catalog yang cocok masih ada dan valid';
$sql[] = '-- ============================================================';
$sql[] = '';
$sql[] = 'START TRANSACTION;';
$sql[] = '';
$sql[] = "SET @repair_tag := 'Repair all canonical material buy UOM from _UOM_BELI 2026-06-08';";
$sql[] = '';
$sql[] = "INSERT INTO mst_uom (code, name, description, is_active)";
$sql[] = "SELECT 'JRG', 'JERIGEN', 'UOM beli kanonik untuk material jerigen', 1";
$sql[] = 'FROM DUAL';
$sql[] = 'WHERE NOT EXISTS (';
$sql[] = "  SELECT 1 FROM mst_uom WHERE code = 'JRG'";
$sql[] = ');';
$sql[] = '';
$sql[] = 'DROP TEMPORARY TABLE IF EXISTS tmp_uom_choice_all;';
$sql[] = 'CREATE TEMPORARY TABLE tmp_uom_choice_all (';
$sql[] = '  material_name VARCHAR(255) NOT NULL,';
$sql[] = '  chosen_buy_uom_code VARCHAR(40) NOT NULL';
$sql[] = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
$sql[] = '';

foreach ($choices as $choice) {
    $materialName = sqlQuote($choice['material_name']);
    $buyUomCode = sqlQuote($choice['buy_uom_code']);
    $sql[] = "INSERT INTO tmp_uom_choice_all(material_name, chosen_buy_uom_code) VALUES ('{$materialName}', '{$buyUomCode}');";
}

$sql[] = '';
$sql[] = 'DROP TEMPORARY TABLE IF EXISTS tmp_all_material_canonical;';
$sql[] = <<<'SQL'
CREATE TEMPORARY TABLE tmp_all_material_canonical AS
SELECT
  m.id AS material_id,
  m.material_name,
  uc.chosen_buy_uom_code,
  bu.id AS canonical_buy_uom_id,
  bu.code AS canonical_buy_uom_code,
  COALESCE(ai.content_uom_id, cc.content_uom_id, ac.content_uom_id, sm.content_uom_id) AS canonical_content_uom_id,
  COALESCE(cu.code, '?') AS canonical_content_uom_code,
  ROUND(COALESCE(
    NULLIF(cc.content_per_buy, 0),
    CASE
      WHEN COALESCE(ai.buy_uom_id, 0) = COALESCE(bu.id, 0) THEN NULLIF(ai.content_per_buy, 0)
      ELSE NULL
    END,
    NULLIF(ac.content_per_buy, 0),
    CASE
      WHEN COALESCE(sm.buy_uom_id, 0) = COALESCE(bu.id, 0) THEN NULLIF(sm.profile_content_per_buy, 0)
      ELSE NULL
    END,
    1
  ), 6) AS default_content_per_buy
FROM tmp_uom_choice_all uc
JOIN mst_material m
  ON BINARY TRIM(m.material_name) = BINARY TRIM(uc.material_name)
JOIN mst_uom bu
  ON BINARY TRIM(bu.code) = BINARY TRIM(uc.chosen_buy_uom_code)
LEFT JOIN (
  SELECT i1.material_id, i1.buy_uom_id, i1.content_uom_id, ROUND(COALESCE(NULLIF(i1.content_per_buy, 0), 1), 6) AS content_per_buy
  FROM mst_item i1
  JOIN (
    SELECT material_id, MAX(id) AS keep_id
    FROM mst_item
    WHERE COALESCE(is_active, 1) = 1
      AND COALESCE(material_id, 0) > 0
    GROUP BY material_id
  ) pick ON pick.keep_id = i1.id
) ai ON ai.material_id = m.id
LEFT JOIN (
  SELECT c1.material_id, bu1.code AS buy_uom_code, c1.content_uom_id, ROUND(COALESCE(NULLIF(c1.content_per_buy, 0), 1), 6) AS content_per_buy
  FROM mst_purchase_catalog c1
  JOIN mst_uom bu1 ON bu1.id = c1.buy_uom_id
  JOIN (
    SELECT material_id, buy_uom_id, MAX(id) AS keep_id
    FROM mst_purchase_catalog
    WHERE COALESCE(is_active, 1) = 1
      AND COALESCE(material_id, 0) > 0
    GROUP BY material_id, buy_uom_id
  ) pick ON pick.keep_id = c1.id
) cc
  ON cc.material_id = m.id
 AND BINARY TRIM(cc.buy_uom_code) = BINARY TRIM(uc.chosen_buy_uom_code)
LEFT JOIN (
  SELECT c1.material_id, c1.content_uom_id, ROUND(COALESCE(NULLIF(c1.content_per_buy, 0), 1), 6) AS content_per_buy
  FROM mst_purchase_catalog c1
  JOIN (
    SELECT material_id, MAX(id) AS keep_id
    FROM mst_purchase_catalog
    WHERE COALESCE(is_active, 1) = 1
      AND COALESCE(material_id, 0) > 0
    GROUP BY material_id
  ) pick ON pick.keep_id = c1.id
) ac ON ac.material_id = m.id
LEFT JOIN (
  SELECT s1.material_id, s1.buy_uom_id, s1.content_uom_id, ROUND(COALESCE(NULLIF(s1.profile_content_per_buy, 0), 1), 6) AS profile_content_per_buy
  FROM inv_division_monthly_stock s1
  JOIN (
    SELECT material_id, MAX(id) AS keep_id
    FROM inv_division_monthly_stock
    WHERE COALESCE(material_id, 0) > 0
    GROUP BY material_id
  ) pick ON pick.keep_id = s1.id
) sm ON sm.material_id = m.id
LEFT JOIN mst_uom cu
  ON cu.id = COALESCE(ai.content_uom_id, cc.content_uom_id, ac.content_uom_id, sm.content_uom_id);
SQL;

$sql[] = '';
$sql[] = 'ALTER TABLE tmp_all_material_canonical';
$sql[] = '  ADD PRIMARY KEY (material_id);';
$sql[] = '';
$sql[] = 'DROP TEMPORARY TABLE IF EXISTS tmp_all_catalog_snapshot;';
$sql[] = <<<'SQL'
CREATE TEMPORARY TABLE tmp_all_catalog_snapshot AS
SELECT
  c.id,
  c.material_id,
  COALESCE(c.profile_key, '') AS profile_key,
  ROUND(COALESCE(NULLIF(c.content_per_buy, 0), d.default_content_per_buy), 6) AS canonical_content_per_buy
FROM mst_purchase_catalog c
JOIN tmp_all_material_canonical d ON d.material_id = c.material_id;
SQL;
$sql[] = '';
$sql[] = 'ALTER TABLE tmp_all_catalog_snapshot';
$sql[] = '  ADD PRIMARY KEY (id),';
$sql[] = '  ADD KEY idx_tmp_all_catalog_snapshot_profile (material_id, profile_key);';
$sql[] = '';

$backupTables = [
    'mst_item',
    'mst_purchase_catalog',
    'inv_division_monthly_stock',
    'inv_warehouse_monthly_stock',
    'inv_division_stock_opening_snapshot',
    'inv_warehouse_stock_opening_snapshot',
    'inv_stock_movement_log',
    'inv_material_fifo_lot',
    'inv_material_fifo_issue_log',
    'inv_stock_adjustment_line',
    'pur_division_request_line',
    'pur_store_request_line',
    'pur_store_request_fulfillment_line',
    'pur_purchase_order_line',
    'pur_purchase_receipt_line',
];

foreach ($backupTables as $table) {
    $temp = 'tmp_uom_fix_backup_' . $table;
    $sql[] = "DROP TEMPORARY TABLE IF EXISTS {$temp};";
    $sql[] = "CREATE TEMPORARY TABLE {$temp} AS";
    $sql[] = "SELECT * FROM {$table} WHERE material_id IN (SELECT material_id FROM tmp_all_material_canonical);";
    $sql[] = '';
}

$sql[] = <<<'SQL'
UPDATE mst_item i
JOIN tmp_all_material_canonical d ON d.material_id = i.material_id
SET
  i.buy_uom_id = d.canonical_buy_uom_id,
  i.content_uom_id = d.canonical_content_uom_id,
  i.content_per_buy = d.default_content_per_buy,
  i.updated_at = CURRENT_TIMESTAMP;

UPDATE mst_purchase_catalog c
JOIN tmp_all_material_canonical d ON d.material_id = c.material_id
SET
  c.buy_uom_id = d.canonical_buy_uom_id,
  c.content_uom_id = d.canonical_content_uom_id,
  c.content_per_buy = ROUND(COALESCE(NULLIF(c.content_per_buy, 0), d.default_content_per_buy), 6),
  c.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_monthly_stock s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_all_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_stock_movement_log l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

UPDATE inv_stock_adjustment_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.available_qty_buy = ROUND(COALESCE(l.available_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.note = LEFT(TRIM(CONCAT(COALESCE(l.note, ''), CASE WHEN COALESCE(l.note, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_material_fifo_lot f
JOIN tmp_all_material_canonical d ON d.material_id = f.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = f.material_id
 AND cs.profile_key = COALESCE(f.profile_key, '')
SET
  f.qty_in = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_in, 0)
        ELSE COALESCE(f.qty_in, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.qty_out = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_out, 0)
        ELSE COALESCE(f.qty_out, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.qty_balance = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_balance, 0)
        ELSE COALESCE(f.qty_balance, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.buy_uom_id = d.canonical_buy_uom_id,
  f.content_uom_id = d.canonical_content_uom_id,
  f.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_material_fifo_issue_log l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.issue_qty = ROUND(
    (
      CASE
        WHEN COALESCE(l.buy_uom_id, 0) = COALESCE(l.content_uom_id, 0) THEN COALESCE(l.issue_qty, 0)
        ELSE COALESCE(l.issue_qty, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

UPDATE pur_division_request_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_store_request_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_approved = ROUND(COALESCE(l.qty_content_approved, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_fulfilled = ROUND(COALESCE(l.qty_content_fulfilled, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_store_request_fulfillment_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_posted = ROUND(COALESCE(l.qty_content_posted, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_order_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 6),
  l.conversion_factor_to_content = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 8),
  l.snapshot_buy_uom_code = d.canonical_buy_uom_code,
  l.snapshot_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_receipt_line l
JOIN tmp_all_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_all_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.conversion_factor_to_content = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 8),
  l.qty_buy_received = ROUND(COALESCE(l.qty_content_received, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;
SQL;

$sql[] = '';
$sql[] = 'COMMIT;';
$sql[] = '';
$sql[] = "SELECT 'selected_materials' AS metric, COUNT(*) AS total FROM tmp_all_material_canonical";
foreach ($backupTables as $table) {
    $temp = 'tmp_uom_fix_backup_' . $table;
    $sql[] = 'UNION ALL';
    $sql[] = "SELECT '{$table}_rows_repaired', COUNT(*) FROM {$temp}";
}
$sql[] = ';';

file_put_contents($targetFile, implode(PHP_EOL, $sql) . PHP_EOL);

echo "Generated {$targetFile}\n";

function sqlQuote(string $value): string
{
    return str_replace("'", "''", $value);
}
