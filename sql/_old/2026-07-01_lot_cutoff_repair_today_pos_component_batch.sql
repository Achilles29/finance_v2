START TRANSACTION;

-- Repair lot pemakaian tanggal 2026-07-01 yang masih mengambil lot bulan lama.
-- Fokus:
-- 1. Batch component ICB202607010001
-- 2. Sales POS hari 2026-07-01
--
-- Catatan:
-- - Script ini idempotent secara praktis: hanya memproses baris issue_line
--   yang masih menunjuk ke old_lot_id.
-- - 1 baris sengaja tidak dipindah otomatis:
--   inv_component_lot_issue_line.id = 10013 (CIRENG MIX)
--   karena opening lot Juli saat ini hanya punya saldo 2,00 sedangkan qty salah-potong 3,00.
--   Itu menandakan shortage riil di Juli yang sebelumnya tertutup lot Juni.

DROP TEMPORARY TABLE IF EXISTS tmp_material_lot_repair_seed;
CREATE TEMPORARY TABLE tmp_material_lot_repair_seed (
    issue_line_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    old_lot_id BIGINT UNSIGNED NOT NULL,
    new_lot_id BIGINT UNSIGNED NOT NULL,
    qty_out DECIMAL(18,4) NOT NULL
) ENGINE=Memory;

INSERT INTO tmp_material_lot_repair_seed (issue_line_id, old_lot_id, new_lot_id, qty_out) VALUES
    (22331, 2205, 3783, 40.0000),
    (22333, 2013, 3789, 40.0000),
    (22305, 1371, 3745, 20.0000),
    (22307, 2055, 3687, 1.0000),
    (22309, 1209, 3651, 200.0000),
    (22311, 1937, 3853, 25.0000),
    (22313, 1975, 3909, 50.0000),
    (22315, 1287, 4397, 3.0000),
    (22317, 2175, 4427, 45.0000),
    (22319, 1293, 4449, 24.0000),
    (22321, 1719, 4355, 24.0000),
    (22323, 870, 4137, 0.5000),
    (22325, 2453, 4293, 1.0000),
    (22327, 675, 4305, 1.0000),
    (22329, 275, 3907, 3.0000),
    (22341, 2189, 3643, 12.0000),
    (22343, 1983, 3767, 25.0000),
    (22345, 847, 3587, 150.0000),
    (22347, 2189, 3643, 18.0000),
    (22349, 847, 3587, 100.0000),
    (22351, 1315, 3779, 15.0000),
    (22353, 88, 3691, 15.0000),
    (22355, 1209, 3651, 100.0000),
    (22357, 2641, 3617, 1.0000),
    (22359, 1481, 4207, 6.0000),
    (22361, 954, 4287, 1.0000);

DROP TEMPORARY TABLE IF EXISTS tmp_material_lot_repair_active;
CREATE TEMPORARY TABLE tmp_material_lot_repair_active AS
SELECT s.*
FROM tmp_material_lot_repair_seed s
JOIN inv_material_fifo_issue_line il
  ON il.id = s.issue_line_id
 AND il.lot_id = s.old_lot_id;

UPDATE inv_material_fifo_lot l
JOIN (
    SELECT old_lot_id, SUM(qty_out) AS qty_total
    FROM tmp_material_lot_repair_active
    GROUP BY old_lot_id
) x ON x.old_lot_id = l.id
SET l.qty_out = ROUND(l.qty_out - x.qty_total, 4),
    l.qty_balance = ROUND(l.qty_balance + x.qty_total, 4),
    l.status = CASE WHEN ROUND(l.qty_balance + x.qty_total, 4) > 0 THEN 'OPEN' ELSE 'CLOSED' END,
    l.updated_at = NOW();

UPDATE inv_material_fifo_lot l
JOIN (
    SELECT new_lot_id, SUM(qty_out) AS qty_total
    FROM tmp_material_lot_repair_active
    GROUP BY new_lot_id
) x ON x.new_lot_id = l.id
SET l.qty_out = ROUND(l.qty_out + x.qty_total, 4),
    l.qty_balance = ROUND(l.qty_balance - x.qty_total, 4),
    l.status = CASE WHEN ROUND(l.qty_balance - x.qty_total, 4) > 0 THEN 'OPEN' ELSE 'CLOSED' END,
    l.updated_at = NOW();

UPDATE inv_material_fifo_issue_line il
JOIN tmp_material_lot_repair_active t
  ON t.issue_line_id = il.id
JOIN inv_material_fifo_lot nl
  ON nl.id = t.new_lot_id
SET il.lot_id = t.new_lot_id,
    il.target_lot_id = NULL,
    il.unit_cost = nl.unit_cost,
    il.total_cost = ROUND(il.qty_out * nl.unit_cost, 2),
    il.source_balance_before = NULL,
    il.source_balance_after = NULL,
    il.target_balance_before = NULL,
    il.target_balance_after = NULL;

UPDATE inv_material_fifo_issue_log ih
JOIN (
    SELECT touched.issue_id, totals.total_cost
    FROM (
        SELECT DISTINCT il.issue_id
        FROM inv_material_fifo_issue_line il
        JOIN tmp_material_lot_repair_active t ON t.issue_line_id = il.id
    ) touched
    JOIN (
        SELECT issue_id, ROUND(SUM(total_cost), 2) AS total_cost
        FROM inv_material_fifo_issue_line
        GROUP BY issue_id
    ) totals ON totals.issue_id = touched.issue_id
) x ON x.issue_id = ih.id
SET ih.total_cost = x.total_cost;

DROP TEMPORARY TABLE IF EXISTS tmp_component_lot_repair_seed;
CREATE TEMPORARY TABLE tmp_component_lot_repair_seed (
    issue_line_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    old_lot_id BIGINT UNSIGNED NOT NULL,
    new_lot_id BIGINT UNSIGNED NOT NULL,
    qty_out DECIMAL(18,4) NOT NULL
) ENGINE=Memory;

INSERT INTO tmp_component_lot_repair_seed (issue_line_id, old_lot_id, new_lot_id, qty_out) VALUES
    (10011, 909, 1245, 20.0000),
    (10017, 849, 1303, 1.0000),
    (10019, 921, 1341, 1.0000),
    (10021, 869, 1335, 1.0000),
    (10023, 775, 1421, 15.0000),
    (10025, 291, 1351, 15.0000),
    (10027, 307, 1369, 80.0000),
    (10029, 897, 1375, 142.3000),
    (10031, 983, 1375, 127.7000),
    (10033, 307, 1369, 75.0000),
    (10035, 849, 1303, 3.0000),
    (10037, 921, 1341, 1.0000),
    (10039, 50, 1373, 1.0000),
    (10041, 755, 1327, 120.0000),
    (10043, 831, 1409, 20.0000),
    (10045, 891, 1395, 15.0000),
    (10047, 983, 1375, 90.0000);

DROP TEMPORARY TABLE IF EXISTS tmp_component_lot_repair_active;
CREATE TEMPORARY TABLE tmp_component_lot_repair_active AS
SELECT s.*
FROM tmp_component_lot_repair_seed s
JOIN inv_component_lot_issue_line il
  ON il.id = s.issue_line_id
 AND il.lot_id = s.old_lot_id;

UPDATE inv_component_lot l
JOIN (
    SELECT old_lot_id, SUM(qty_out) AS qty_total
    FROM tmp_component_lot_repair_active
    GROUP BY old_lot_id
) x ON x.old_lot_id = l.id
SET l.qty_out_total = ROUND(l.qty_out_total - x.qty_total, 4),
    l.qty_balance = ROUND(l.qty_balance + x.qty_total, 4),
    l.status = CASE WHEN ROUND(l.qty_balance + x.qty_total, 4) > 0 THEN 'OPEN' ELSE 'CLOSED' END,
    l.updated_at = NOW();

UPDATE inv_component_lot l
JOIN (
    SELECT new_lot_id, SUM(qty_out) AS qty_total
    FROM tmp_component_lot_repair_active
    GROUP BY new_lot_id
) x ON x.new_lot_id = l.id
SET l.qty_out_total = ROUND(l.qty_out_total + x.qty_total, 4),
    l.qty_balance = ROUND(l.qty_balance - x.qty_total, 4),
    l.status = CASE WHEN ROUND(l.qty_balance - x.qty_total, 4) > 0 THEN 'OPEN' ELSE 'CLOSED' END,
    l.last_issue_at = NOW(),
    l.updated_at = NOW();

UPDATE inv_component_lot_issue_line il
JOIN tmp_component_lot_repair_active t
  ON t.issue_line_id = il.id
JOIN inv_component_lot nl
  ON nl.id = t.new_lot_id
SET il.lot_id = t.new_lot_id,
    il.unit_cost = nl.unit_cost,
    il.total_cost = ROUND(il.qty_out * nl.unit_cost, 2),
    il.source_balance_before = NULL,
    il.source_balance_after = NULL;

UPDATE inv_component_lot_issue_log ih
JOIN (
    SELECT touched.issue_id, totals.total_cost
    FROM (
        SELECT DISTINCT il.issue_id
        FROM inv_component_lot_issue_line il
        JOIN tmp_component_lot_repair_active t ON t.issue_line_id = il.id
    ) touched
    JOIN (
        SELECT issue_id, ROUND(SUM(total_cost), 2) AS total_cost
        FROM inv_component_lot_issue_line
        GROUP BY issue_id
    ) totals ON totals.issue_id = touched.issue_id
) x ON x.issue_id = ih.id
SET ih.total_cost = x.total_cost,
    ih.updated_at = NOW();

COMMIT;

SELECT COUNT(*) AS material_rows_repaired FROM tmp_material_lot_repair_active;
SELECT COUNT(*) AS component_rows_repaired FROM tmp_component_lot_repair_active;

-- Review manual shortage yang tidak dipindah otomatis.
SELECT il.id AS issue_line_id,
       ih.source_table,
       ih.source_id,
       c.component_name,
       il.qty_out,
       oldlot.lot_no AS old_lot_no,
       oldlot.receipt_date AS old_receipt_date,
       newlot.id AS july_opening_lot_id,
       newlot.lot_no AS july_opening_lot_no,
       newlot.qty_balance AS july_opening_balance
FROM inv_component_lot_issue_line il
JOIN inv_component_lot_issue_log ih ON ih.id = il.issue_id
JOIN inv_component_lot oldlot ON oldlot.id = il.lot_id
LEFT JOIN inv_component_lot newlot
       ON newlot.location_type = ih.location_type
      AND newlot.division_id <=> ih.division_id
      AND newlot.component_id = ih.component_id
      AND newlot.uom_id = ih.uom_id
      AND newlot.source_table = 'inv_component_monthly_opening'
      AND newlot.receipt_date = '2026-07-01'
LEFT JOIN mst_component c ON c.id = ih.component_id
WHERE il.id = 10013;
