START TRANSACTION;

-- Repair carry-forward Juli 2026:
-- 1. Tutup lot lama (< 2026-07-01) yang masih OPEN padahal opening Juli sudah dibuat.
-- 2. Sync ulang monthly_stock Juli hanya untuk identitas yang terdampak carry-forward dobel.

DROP TEMPORARY TABLE IF EXISTS tmp_material_carry_forward_identity;
CREATE TEMPORARY TABLE tmp_material_carry_forward_identity (
    division_id BIGINT UNSIGNED NOT NULL,
    destination_type VARCHAR(20) NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    buy_uom_id BIGINT UNSIGNED NULL,
    content_uom_id BIGINT UNSIGNED NOT NULL,
    profile_key CHAR(64) NULL
) ENGINE=Memory;

INSERT INTO tmp_material_carry_forward_identity (
    division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
)
SELECT DISTINCT
    oldlot.division_id,
    oldlot.destination_type,
    oldlot.item_id,
    oldlot.material_id,
    oldlot.buy_uom_id,
    oldlot.content_uom_id,
    oldlot.profile_key
FROM inv_material_fifo_lot oldlot
JOIN inv_material_fifo_lot openlot
  ON openlot.location_scope = 'DIVISION'
 AND openlot.status = 'OPEN'
 AND openlot.source_table = 'inv_division_stock_opening_snapshot'
 AND openlot.receipt_date = '2026-07-01'
 AND openlot.division_id = oldlot.division_id
 AND openlot.destination_type = oldlot.destination_type
 AND openlot.material_id = oldlot.material_id
 AND openlot.content_uom_id = oldlot.content_uom_id
 AND openlot.profile_key <=> oldlot.profile_key
WHERE oldlot.location_scope = 'DIVISION'
  AND oldlot.status = 'OPEN'
  AND oldlot.qty_balance > 0.0001
  AND oldlot.receipt_date < '2026-07-01';

UPDATE inv_material_fifo_lot oldlot
JOIN tmp_material_carry_forward_identity t
  ON t.division_id = oldlot.division_id
 AND t.destination_type = oldlot.destination_type
 AND t.material_id = oldlot.material_id
 AND t.content_uom_id = oldlot.content_uom_id
 AND t.profile_key <=> oldlot.profile_key
SET oldlot.qty_out = ROUND(oldlot.qty_out + oldlot.qty_balance, 4),
    oldlot.qty_balance = 0,
    oldlot.status = 'CLOSED',
    oldlot.updated_at = NOW()
WHERE oldlot.location_scope = 'DIVISION'
  AND oldlot.status = 'OPEN'
  AND oldlot.qty_balance > 0.0001
  AND oldlot.receipt_date < '2026-07-01';

DROP TEMPORARY TABLE IF EXISTS tmp_material_july_balance;
CREATE TEMPORARY TABLE tmp_material_july_balance AS
SELECT
    t.division_id,
    t.destination_type,
    t.material_id,
    t.content_uom_id,
    t.profile_key,
    ROUND(COALESCE(SUM(l.qty_balance), 0), 4) AS july_qty_balance
FROM tmp_material_carry_forward_identity t
LEFT JOIN inv_material_fifo_lot l
  ON l.location_scope = 'DIVISION'
 AND l.status = 'OPEN'
 AND l.division_id = t.division_id
 AND l.destination_type = t.destination_type
 AND l.material_id = t.material_id
 AND l.content_uom_id = t.content_uom_id
 AND l.profile_key <=> t.profile_key
 AND l.receipt_date >= '2026-07-01'
 AND l.receipt_date < '2026-08-01'
GROUP BY
    t.division_id, t.destination_type, t.material_id, t.content_uom_id, t.profile_key;

UPDATE inv_division_monthly_stock ms
JOIN tmp_material_july_balance b
  ON b.division_id = ms.division_id
 AND b.destination_type = ms.destination_type
 AND b.material_id = ms.material_id
 AND b.content_uom_id = ms.content_uom_id
 AND b.profile_key <=> ms.profile_key
SET ms.closing_qty_content = b.july_qty_balance,
    ms.closing_qty_buy = CASE
        WHEN COALESCE(ms.profile_content_per_buy, 0) > 0
            THEN ROUND(b.july_qty_balance / ms.profile_content_per_buy, 4)
        ELSE b.july_qty_balance
    END,
    ms.total_value = ROUND(b.july_qty_balance * COALESCE(ms.avg_cost_per_content, 0), 2),
    ms.updated_at = NOW()
WHERE ms.month_key = '2026-07-01';

DROP TEMPORARY TABLE IF EXISTS tmp_component_carry_forward_identity;
CREATE TEMPORARY TABLE tmp_component_carry_forward_identity (
    location_type VARCHAR(20) NOT NULL,
    division_id BIGINT UNSIGNED NULL,
    component_id BIGINT UNSIGNED NOT NULL,
    uom_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (location_type, division_id, component_id, uom_id)
) ENGINE=Memory;

INSERT INTO tmp_component_carry_forward_identity (
    location_type, division_id, component_id, uom_id
)
SELECT DISTINCT
    oldlot.location_type,
    oldlot.division_id,
    oldlot.component_id,
    oldlot.uom_id
FROM inv_component_lot oldlot
JOIN inv_component_lot openlot
  ON openlot.status = 'OPEN'
 AND openlot.source_table = 'inv_component_monthly_opening'
 AND openlot.receipt_date = '2026-07-01'
 AND openlot.location_type = oldlot.location_type
 AND openlot.division_id <=> oldlot.division_id
 AND openlot.component_id = oldlot.component_id
 AND openlot.uom_id = oldlot.uom_id
WHERE oldlot.status = 'OPEN'
  AND oldlot.qty_balance > 0.0001
  AND oldlot.receipt_date < '2026-07-01';

UPDATE inv_component_lot oldlot
JOIN tmp_component_carry_forward_identity t
  ON t.location_type = oldlot.location_type
 AND t.division_id <=> oldlot.division_id
 AND t.component_id = oldlot.component_id
 AND t.uom_id = oldlot.uom_id
SET oldlot.qty_out_total = ROUND(oldlot.qty_out_total + oldlot.qty_balance, 4),
    oldlot.qty_balance = 0,
    oldlot.status = 'CLOSED',
    oldlot.updated_at = NOW()
WHERE oldlot.status = 'OPEN'
  AND oldlot.qty_balance > 0.0001
  AND oldlot.receipt_date < '2026-07-01';

DROP TEMPORARY TABLE IF EXISTS tmp_component_july_balance;
CREATE TEMPORARY TABLE tmp_component_july_balance AS
SELECT
    t.location_type,
    t.division_id,
    t.component_id,
    t.uom_id,
    ROUND(COALESCE(SUM(l.qty_balance), 0), 4) AS july_qty_balance
FROM tmp_component_carry_forward_identity t
LEFT JOIN inv_component_lot l
  ON l.status = 'OPEN'
 AND l.location_type = t.location_type
 AND l.division_id <=> t.division_id
 AND l.component_id = t.component_id
 AND l.uom_id = t.uom_id
 AND l.receipt_date >= '2026-07-01'
 AND l.receipt_date < '2026-08-01'
GROUP BY
    t.location_type, t.division_id, t.component_id, t.uom_id;

UPDATE inv_component_monthly_stock ms
JOIN tmp_component_july_balance b
  ON b.location_type = ms.location_type
 AND b.division_id <=> ms.division_id
 AND b.component_id = ms.component_id
 AND b.uom_id = ms.uom_id
SET ms.closing_qty = b.july_qty_balance,
    ms.total_value = ROUND(b.july_qty_balance * COALESCE(ms.avg_cost, 0), 2),
    ms.updated_at = NOW()
WHERE ms.month_key = '2026-07-01';

COMMIT;

SELECT COUNT(*) AS affected_material_identity FROM tmp_material_carry_forward_identity;
SELECT COUNT(*) AS affected_component_identity FROM tmp_component_carry_forward_identity;
