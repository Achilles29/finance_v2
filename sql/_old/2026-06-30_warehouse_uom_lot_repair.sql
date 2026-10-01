START TRANSACTION;

-- 1) Sync item content UOM to material content UOM for linked items.
UPDATE mst_item i
JOIN mst_material m ON m.id = i.material_id
SET i.content_uom_id = m.content_uom_id
WHERE COALESCE(i.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 2) Repair warehouse opening snapshots whose content UOM no longer matches mst_material.
UPDATE inv_warehouse_stock_opening_snapshot s
JOIN mst_material m ON m.id = s.material_id
LEFT JOIN mst_uom u ON u.id = m.content_uom_id
SET
    s.content_uom_id = m.content_uom_id,
    s.profile_content_uom_code = u.code
WHERE COALESCE(s.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 3) Repair warehouse monthly opening rows if any mismatch exists.
UPDATE inv_warehouse_monthly_opening s
JOIN mst_material m ON m.id = s.material_id
LEFT JOIN mst_uom u ON u.id = m.content_uom_id
SET
    s.content_uom_id = m.content_uom_id,
    s.profile_content_uom_code = u.code
WHERE COALESCE(s.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 4) Repair warehouse monthly stock rows.
UPDATE inv_warehouse_monthly_stock s
JOIN mst_material m ON m.id = s.material_id
LEFT JOIN mst_uom u ON u.id = m.content_uom_id
SET
    s.content_uom_id = m.content_uom_id,
    s.profile_content_uom_code = u.code
WHERE COALESCE(s.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 5) Repair warehouse movement log rows.
UPDATE inv_stock_movement_log g
JOIN mst_material m ON m.id = g.material_id
LEFT JOIN mst_uom u ON u.id = m.content_uom_id
SET
    g.content_uom_id = m.content_uom_id,
    g.profile_content_uom_code = u.code
WHERE g.movement_scope = 'WAREHOUSE'
  AND COALESCE(g.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 6) Repair warehouse draft adjustment lines.
UPDATE inv_stock_adjustment_line l
JOIN inv_stock_adjustment h ON h.id = l.adjustment_id
JOIN mst_material m ON m.id = l.material_id
LEFT JOIN mst_uom u ON u.id = m.content_uom_id
SET
    l.content_uom_id = m.content_uom_id,
    l.profile_content_uom_code = u.code
WHERE h.stock_scope = 'WAREHOUSE'
  AND h.status = 'DRAFT'
  AND COALESCE(l.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 7) Repair warehouse FIFO lot UOM if a mismatch already exists.
UPDATE inv_material_fifo_lot l
JOIN mst_material m ON m.id = l.material_id
SET l.content_uom_id = m.content_uom_id
WHERE l.location_scope = 'WAREHOUSE'
  AND COALESCE(l.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0);

-- 8) Backfill missing warehouse opening lots for all opening snapshots with positive balance.
INSERT INTO inv_material_fifo_lot (
    lot_no,
    location_scope,
    receipt_date,
    expiry_date,
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key,
    qty_in,
    qty_out,
    qty_balance,
    unit_cost,
    source_table,
    source_id,
    source_line_id,
    receipt_id,
    receipt_line_id,
    parent_lot_id,
    status
)
SELECT
    CONCAT('OPEN', DATE_FORMAT(s.snapshot_month, '%Y%m'), '-', LPAD(s.id, 6, '0')) AS lot_no,
    'WAREHOUSE' AS location_scope,
    s.snapshot_month AS receipt_date,
    s.profile_expired_date AS expiry_date,
    NULL AS division_id,
    'GUDANG' AS destination_type,
    s.item_id,
    s.material_id,
    s.buy_uom_id,
    s.content_uom_id,
    s.profile_key,
    s.opening_qty_content AS qty_in,
    0 AS qty_out,
    s.opening_qty_content AS qty_balance,
    s.opening_avg_cost_per_content AS unit_cost,
    'inv_warehouse_stock_opening_snapshot' AS source_table,
    s.id AS source_id,
    NULL AS source_line_id,
    NULL AS receipt_id,
    NULL AS receipt_line_id,
    NULL AS parent_lot_id,
    CASE
        WHEN ROUND(COALESCE(s.opening_qty_content, 0), 4) > 0.0000 THEN 'OPEN'
        ELSE 'CLOSED'
    END AS status
FROM inv_warehouse_stock_opening_snapshot s
LEFT JOIN inv_material_fifo_lot l
    ON l.source_table = 'inv_warehouse_stock_opening_snapshot'
   AND l.source_id = s.id
WHERE ROUND(COALESCE(s.opening_qty_content, 0), 4) > 0.0000
  AND l.id IS NULL;

COMMIT;
