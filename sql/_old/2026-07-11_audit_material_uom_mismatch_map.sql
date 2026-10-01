-- Audit sinkronisasi UOM bahan baku dari master purchase catalog sampai stok dan resep.
-- File ini hanya SELECT, tidak mengubah data.

-- 1) Purchase catalog aktif yang tidak sinkron dengan material milik item.
SELECT
  c.id AS catalog_id,
  c.profile_key,
  c.catalog_name,
  c.item_id,
  i.item_name,
  i.material_id AS item_material_id,
  im.material_name AS item_material_name,
  cu.code AS catalog_content_uom,
  imu.code AS item_material_uom,
  c.material_id AS catalog_material_id,
  cm.material_name AS catalog_material_name,
  cmu.code AS catalog_material_uom,
  c.content_per_buy,
  CASE
    WHEN c.material_id IS NOT NULL
      AND i.material_id IS NOT NULL
      AND c.material_id <> i.material_id
      THEN 'MATERIAL_LINK_MISMATCH'
    WHEN COALESCE(c.content_uom_id, 0) <> COALESCE(im.content_uom_id, 0)
      THEN 'CONTENT_UOM_MISMATCH_TO_ITEM_MATERIAL'
    ELSE 'OK'
  END AS issue
FROM mst_purchase_catalog c
LEFT JOIN mst_item i ON i.id = c.item_id
LEFT JOIN mst_material im ON im.id = i.material_id
LEFT JOIN mst_material cm ON cm.id = c.material_id
LEFT JOIN mst_uom cu ON cu.id = c.content_uom_id
LEFT JOIN mst_uom imu ON imu.id = im.content_uom_id
LEFT JOIN mst_uom cmu ON cmu.id = cm.content_uom_id
WHERE c.is_active = 1
  AND i.material_id IS NOT NULL
  AND (
    (c.material_id IS NOT NULL AND c.material_id <> i.material_id)
    OR COALESCE(c.content_uom_id, 0) <> COALESCE(im.content_uom_id, 0)
  )
ORDER BY issue, c.catalog_name, c.id;

-- 2) Stok divisi bulan berjalan yang UOM isinya tidak sama dengan master material.
SELECT
  s.id AS monthly_stock_id,
  s.month_key,
  s.division_id,
  d.code AS division_code,
  s.destination_type,
  s.item_id,
  i.item_name,
  COALESCE(s.material_id, i.material_id) AS material_id,
  m.material_name,
  s.profile_key,
  s.profile_name,
  s.closing_qty_content,
  s.total_value,
  su.code AS stock_uom,
  mu.code AS material_uom,
  s.notes
FROM inv_division_monthly_stock s
LEFT JOIN mst_operational_division d ON d.id = s.division_id
LEFT JOIN mst_item i ON i.id = s.item_id
JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
LEFT JOIN mst_uom su ON su.id = s.content_uom_id
LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
WHERE s.month_key = DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND COALESCE(s.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0)
ORDER BY m.material_name, s.division_id, s.destination_type, s.id;

-- 3) Lot FIFO divisi OPEN yang UOM isinya tidak sama dengan master material.
SELECT
  l.id AS lot_id,
  l.lot_no,
  l.receipt_date,
  l.location_scope,
  l.division_id,
  d.code AS division_code,
  l.destination_type,
  l.item_id,
  i.item_name,
  COALESCE(l.material_id, i.material_id) AS material_id,
  m.material_name,
  l.profile_key,
  l.qty_in,
  l.qty_out,
  l.qty_balance,
  lu.code AS lot_uom,
  mu.code AS material_uom,
  l.status,
  l.source_table,
  l.source_id,
  l.receipt_id,
  l.receipt_line_id
FROM inv_material_fifo_lot l
LEFT JOIN mst_operational_division d ON d.id = l.division_id
LEFT JOIN mst_item i ON i.id = l.item_id
JOIN mst_material m ON m.id = COALESCE(l.material_id, i.material_id)
LEFT JOIN mst_uom lu ON lu.id = l.content_uom_id
LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
WHERE l.location_scope = 'DIVISION'
  AND l.status = 'OPEN'
  AND COALESCE(l.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0)
ORDER BY m.material_name, l.receipt_date, l.id;

-- 4) Resep POS material yang UOM-nya tidak sama dengan master material.
SELECT
  r.id AS recipe_id,
  p.id AS product_id,
  p.product_name,
  r.ingredient_role,
  r.qty,
  ru.code AS recipe_uom,
  r.source_division_id,
  d.code AS source_division,
  r.material_item_id,
  i.item_name,
  i.material_id,
  m.material_name,
  mu.code AS material_uom
FROM mst_product_recipe r
JOIN mst_product p ON p.id = r.product_id
LEFT JOIN mst_item i ON i.id = r.material_item_id
LEFT JOIN mst_material m ON m.id = i.material_id
LEFT JOIN mst_uom ru ON ru.id = r.uom_id
LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
LEFT JOIN mst_operational_division d ON d.id = r.source_division_id
WHERE r.line_type = 'MATERIAL'
  AND COALESCE(r.uom_id, 0) <> COALESCE(m.content_uom_id, 0)
ORDER BY m.material_name, p.product_name, r.id;

-- 5) Formula component material yang item-nya tidak sinkron dengan material master.
SELECT
  f.id AS formula_line_id,
  c.id AS component_id,
  c.component_name,
  f.qty,
  f.source_division_id,
  d.code AS source_division,
  COALESCE(f.material_id, i.material_id) AS material_id,
  m.material_name,
  mu.code AS material_uom,
  f.material_item_id,
  i.item_name,
  iu.code AS item_uom
FROM mst_component_formula f
JOIN mst_component c ON c.id = f.component_id
LEFT JOIN mst_item i ON i.id = f.material_item_id
LEFT JOIN mst_material m ON m.id = COALESCE(f.material_id, i.material_id)
LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
LEFT JOIN mst_uom iu ON iu.id = i.content_uom_id
LEFT JOIN mst_operational_division d ON d.id = f.source_division_id
WHERE f.line_type = 'MATERIAL'
  AND f.material_item_id IS NOT NULL
  AND i.material_id IS NOT NULL
  AND COALESCE(i.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0)
ORDER BY m.material_name, c.component_name, f.id;
