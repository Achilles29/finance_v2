START TRANSACTION;

-- Repair QUARTER CHICKEN FRIED KITCHEN Reguler Juli 2026.
--
-- Kondisi:
-- - inv_component_monthly_stock closing_qty sudah 0.
-- - inv_component_lot masih punya 2 lot OPEN total 4 PRS.
-- - component-daily membaca monthly stock sehingga kosong/0, sedangkan
--   component-daily-recon membaca lot aktif sehingga terlihat masih ada stok.
--
-- Target:
-- - monthly stock tetap 0 dan total_value 0.
-- - semua lot aktif component 86 bulan Juli ditutup menjadi 0.
-- - ada issue log audit manual untuk menutup orphan lot, tanpa menambah movement
--   karena movement monthly sudah net 0.

SET @month_key := '2026-07-01';
SET @repair_date := '2026-07-26';
SET @location_type := 'KITCHEN';
SET @division_id := 3;
SET @component_id := 86;
SET @uom_id := 26;
SET @issue_no := 'ICIREPAIR20260726QCF86';

CREATE TABLE IF NOT EXISTS zz_bak_comp_monthly_20260726d_qcf AS
SELECT *
FROM inv_component_monthly_stock
WHERE 1 = 0;

INSERT INTO zz_bak_comp_monthly_20260726d_qcf
SELECT *
FROM inv_component_monthly_stock
WHERE month_key = @month_key
  AND location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id;

CREATE TABLE IF NOT EXISTS zz_bak_comp_lot_20260726d_qcf AS
SELECT *
FROM inv_component_lot
WHERE 1 = 0;

INSERT INTO zz_bak_comp_lot_20260726d_qcf
SELECT *
FROM inv_component_lot
WHERE location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id
  AND receipt_date BETWEEN @month_key AND LAST_DAY(@month_key);

CREATE TABLE IF NOT EXISTS zz_bak_comp_issue_log_20260726d_qcf AS
SELECT *
FROM inv_component_lot_issue_log
WHERE 1 = 0;

INSERT INTO zz_bak_comp_issue_log_20260726d_qcf
SELECT *
FROM inv_component_lot_issue_log
WHERE source_table = 'manual_repair_component_lot_sync'
  AND source_id = @component_id
  AND issue_date = @repair_date;

CREATE TABLE IF NOT EXISTS zz_bak_comp_issue_line_20260726d_qcf AS
SELECT il.*
FROM inv_component_lot_issue_line il
WHERE 1 = 0;

INSERT INTO zz_bak_comp_issue_line_20260726d_qcf
SELECT il.*
FROM inv_component_lot_issue_line il
JOIN inv_component_lot_issue_log ig ON ig.id = il.issue_id
WHERE ig.source_table = 'manual_repair_component_lot_sync'
  AND ig.source_id = @component_id
  AND ig.issue_date = @repair_date;

DROP TEMPORARY TABLE IF EXISTS tmp_qcf_open_lots;
CREATE TEMPORARY TABLE tmp_qcf_open_lots AS
SELECT
  id AS lot_id,
  qty_balance,
  unit_cost,
  ROUND(qty_balance * unit_cost, 2) AS line_cost
FROM inv_component_lot
WHERE location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id
  AND receipt_date BETWEEN @month_key AND LAST_DAY(@month_key)
  AND status = 'OPEN'
  AND qty_balance > 0;

INSERT INTO inv_component_lot_issue_log (
  issue_no,
  issue_date,
  issue_datetime,
  location_type,
  division_id,
  component_id,
  uom_id,
  issue_qty,
  total_cost,
  source_module,
  source_table,
  source_id,
  source_line_id,
  notes,
  status,
  created_at,
  updated_at
)
SELECT
  @issue_no,
  @repair_date,
  NOW(),
  @location_type,
  @division_id,
  @component_id,
  @uom_id,
  ROUND(SUM(qty_balance), 4),
  ROUND(SUM(line_cost), 2),
  'MANUAL_REPAIR',
  'manual_repair_component_lot_sync',
  @component_id,
  NULL,
  'Repair 2026-07-26: close orphan component lots because monthly stock closing is zero',
  'POSTED',
  NOW(),
  NOW()
FROM tmp_qcf_open_lots
HAVING ROUND(SUM(qty_balance), 4) > 0
ON DUPLICATE KEY UPDATE
  issue_qty = VALUES(issue_qty),
  total_cost = VALUES(total_cost),
  notes = VALUES(notes),
  updated_at = NOW();

SET @issue_id := (
  SELECT id
  FROM inv_component_lot_issue_log
  WHERE issue_no = @issue_no
  LIMIT 1
);

INSERT INTO inv_component_lot_issue_line (
  issue_id,
  lot_id,
  qty_out,
  unit_cost,
  total_cost,
  source_balance_before,
  source_balance_after,
  created_at
)
SELECT
  @issue_id,
  lot_id,
  ROUND(qty_balance, 4),
  unit_cost,
  line_cost,
  ROUND(qty_balance, 4),
  0.0000,
  NOW()
FROM tmp_qcf_open_lots
WHERE @issue_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM inv_component_lot_issue_line x
    WHERE x.issue_id = @issue_id
      AND x.lot_id = tmp_qcf_open_lots.lot_id
  );

UPDATE inv_component_lot l
JOIN tmp_qcf_open_lots t ON t.lot_id = l.id
SET l.qty_out_total = ROUND(l.qty_out_total + t.qty_balance, 4),
    l.qty_balance = 0.0000,
    l.status = 'CLOSED',
    l.updated_at = NOW();

UPDATE inv_component_monthly_stock
SET closing_qty = 0.0000,
    avg_cost = 0.000000,
    total_value = 0.00,
    last_movement_date = @repair_date,
    last_movement_at = NOW(),
    last_movement_table = 'manual_repair_component_lot_sync',
    last_movement_id = NULL,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-26: close orphan LOT QCF, monthly stays 0'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id;

COMMIT;
