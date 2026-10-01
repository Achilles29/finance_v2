-- Hapus total TING TING CRUMBLE dari BAR (semua tabel)

START TRANSACTION;

DELETE l FROM inv_component_lot l
JOIN mst_component c ON c.id = l.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND l.location_type IN ('BAR', 'BAR_EVENT');
SELECT ROW_COUNT() AS lot_deleted;

DELETE ml FROM inv_component_movement_log ml
JOIN mst_component c ON c.id = ml.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND ml.location_type IN ('BAR', 'BAR_EVENT');
SELECT ROW_COUNT() AS movement_log_deleted;

DELETE ms FROM inv_component_monthly_stock ms
JOIN mst_component c ON c.id = ms.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND ms.location_type IN ('BAR', 'BAR_EVENT');
SELECT ROW_COUNT() AS monthly_stock_deleted;

DELETE mo FROM inv_component_monthly_opname mo
JOIN mst_component c ON c.id = mo.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND mo.location_type IN ('BAR', 'BAR_EVENT');
SELECT ROW_COUNT() AS opname_deleted;

DELETE op FROM inv_component_monthly_opening op
JOIN mst_component c ON c.id = op.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND op.location_type IN ('BAR', 'BAR_EVENT');
SELECT ROW_COUNT() AS opening_deleted;

COMMIT;
