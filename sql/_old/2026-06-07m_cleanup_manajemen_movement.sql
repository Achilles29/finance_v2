SET NAMES utf8mb4;

-- Audit dulu: movement apa saja yang masuk untuk divisi destination_type OTHER
-- yang seharusnya tidak diposting (MANAJEMEN dan sejenisnya)
SELECT
  ml.id,
  ml.movement_no,
  ml.movement_date,
  ml.movement_type,
  ml.ref_table,
  ml.ref_id,
  d.name              AS division_name,
  ml.destination_type,
  COALESCE(mi.item_name, '-')     AS item_name,
  COALESCE(mm.material_name, '-') AS material_name,
  ml.qty_content_delta,
  ml.notes
FROM inv_stock_movement_log ml
JOIN mst_operational_division d ON d.id = ml.division_id
LEFT JOIN mst_item     mi ON mi.id = ml.item_id
LEFT JOIN mst_material mm ON mm.id = ml.material_id
WHERE ml.movement_scope  = 'DIVISION'
  AND ml.destination_type = 'OTHER'
  AND ml.ref_table        = 'pos_stock_commit'
ORDER BY ml.movement_date DESC, ml.id DESC;
