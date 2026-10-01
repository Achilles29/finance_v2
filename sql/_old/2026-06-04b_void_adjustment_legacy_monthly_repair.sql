SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-04b_void_adjustment_legacy_monthly_repair.sql
-- Tujuan :
-- 1) Menutup artefak lot yang bersumber dari adjustment berstatus VOID
-- 2) Menormalkan row stok bulanan legacy ITEM->MATERIAL untuk bahan baku produksi
-- 3) Menormalkan row stok bulanan MATERIAL legacy tanpa profile ke profile kanonik saudara
-- 4) TIDAK dieksekusi otomatis oleh aplikasi; jalankan manual setelah review
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_void_adjustment_lot_targets;
DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_item_material_targets;
DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_monthly_item_material_targets;
DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_null_profile_targets;
DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_monthly_null_profile_targets;
DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_item_material_merge_targets;
DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_monthly_item_material_merge_targets;

CREATE TEMPORARY TABLE tmp_void_adjustment_lot_targets AS
SELECT
  l.id AS lot_id,
  l.qty_balance,
  l.status
FROM inv_material_fifo_lot l
JOIN inv_stock_adjustment a
  ON a.id = l.source_id
 AND l.source_table = 'inv_stock_adjustment'
WHERE a.status = 'VOID'
  AND (
    COALESCE(l.qty_balance, 0) <> 0
    OR UPPER(COALESCE(l.status, 'OPEN')) <> 'CLOSED'
  );

CREATE TEMPORARY TABLE tmp_division_monthly_item_material_targets AS
SELECT
  s.id AS row_id,
  i.material_id AS target_material_id,
  c.id AS canonical_row_id
FROM inv_division_monthly_stock s
JOIN mst_item i ON i.id = s.item_id
LEFT JOIN inv_division_monthly_stock c
  ON c.month_key = s.month_key
 AND c.division_id = s.division_id
 AND c.destination_type = s.destination_type
 AND COALESCE(c.profile_key, '') = COALESCE(s.profile_key, '')
 AND c.id <> s.id
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0
  AND COALESCE(s.profile_key, '') <> '';

CREATE TEMPORARY TABLE tmp_warehouse_monthly_item_material_targets AS
SELECT
  s.id AS row_id,
  i.material_id AS target_material_id,
  c.id AS canonical_row_id
FROM inv_warehouse_monthly_stock s
JOIN mst_item i ON i.id = s.item_id
LEFT JOIN inv_warehouse_monthly_stock c
  ON c.month_key = s.month_key
 AND COALESCE(c.profile_key, '') = COALESCE(s.profile_key, '')
 AND c.id <> s.id
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0
  AND COALESCE(s.profile_key, '') <> '';

CREATE TEMPORARY TABLE tmp_division_monthly_item_material_merge_targets AS
SELECT *
FROM tmp_division_monthly_item_material_targets
WHERE canonical_row_id IS NOT NULL;

CREATE TEMPORARY TABLE tmp_warehouse_monthly_item_material_merge_targets AS
SELECT *
FROM tmp_warehouse_monthly_item_material_targets
WHERE canonical_row_id IS NOT NULL;

CREATE TEMPORARY TABLE tmp_division_monthly_null_profile_targets AS
SELECT
  l.id AS legacy_row_id,
  c.id AS canonical_row_id,
  c.item_id AS canonical_item_id,
  c.material_id AS canonical_material_id,
  c.profile_key AS canonical_profile_key,
  c.profile_name AS canonical_profile_name,
  c.profile_brand AS canonical_profile_brand,
  c.profile_description AS canonical_profile_description,
  c.profile_expired_date AS canonical_profile_expired_date,
  c.profile_content_per_buy AS canonical_content_per_buy,
  c.buy_uom_id AS canonical_buy_uom_id,
  c.content_uom_id AS canonical_content_uom_id,
  c.profile_buy_uom_code AS canonical_buy_uom_code,
  c.profile_content_uom_code AS canonical_content_uom_code
FROM inv_division_monthly_stock l
JOIN inv_division_monthly_stock c
  ON c.month_key = l.month_key
 AND c.division_id = l.division_id
 AND c.destination_type = l.destination_type
 AND COALESCE(c.material_id, 0) = COALESCE(l.material_id, 0)
 AND COALESCE(c.content_uom_id, 0) = COALESCE(l.content_uom_id, 0)
 AND c.id <> l.id
 AND COALESCE(c.profile_key, '') <> ''
WHERE UPPER(COALESCE(l.stock_domain, 'ITEM')) = 'MATERIAL'
  AND COALESCE(l.profile_key, '') = '';

CREATE TEMPORARY TABLE tmp_warehouse_monthly_null_profile_targets AS
SELECT
  l.id AS legacy_row_id,
  c.id AS canonical_row_id,
  c.item_id AS canonical_item_id,
  c.material_id AS canonical_material_id,
  c.profile_key AS canonical_profile_key,
  c.profile_name AS canonical_profile_name,
  c.profile_brand AS canonical_profile_brand,
  c.profile_description AS canonical_profile_description,
  c.profile_expired_date AS canonical_profile_expired_date,
  c.profile_content_per_buy AS canonical_content_per_buy,
  c.buy_uom_id AS canonical_buy_uom_id,
  c.content_uom_id AS canonical_content_uom_id,
  c.profile_buy_uom_code AS canonical_buy_uom_code,
  c.profile_content_uom_code AS canonical_content_uom_code
FROM inv_warehouse_monthly_stock l
JOIN inv_warehouse_monthly_stock c
  ON c.month_key = l.month_key
 AND COALESCE(c.material_id, 0) = COALESCE(l.material_id, 0)
 AND COALESCE(c.content_uom_id, 0) = COALESCE(l.content_uom_id, 0)
 AND c.id <> l.id
 AND COALESCE(c.profile_key, '') <> ''
WHERE UPPER(COALESCE(l.stock_domain, 'ITEM')) = 'MATERIAL'
  AND COALESCE(l.profile_key, '') = '';

-- ============================================================
-- 1) Tutup lot artefak yang bersumber dari adjustment VOID
-- ============================================================
UPDATE inv_material_fifo_lot l
JOIN tmp_void_adjustment_lot_targets t ON t.lot_id = l.id
SET l.qty_balance = 0,
    l.status = 'CLOSED';

-- ============================================================
-- 2) Row bulanan ITEM yang sebenarnya bahan baku -> MATERIAL
--    Jika row kanonik dengan profile_key yang sama sudah ada, qty/value digabung
--    ke row kanonik lalu row legacy dihapus agar tidak menabrak unique key.
-- ============================================================
UPDATE inv_division_monthly_stock c
JOIN tmp_division_monthly_item_material_merge_targets t ON t.canonical_row_id = c.id
JOIN inv_division_monthly_stock s ON s.id = t.row_id
SET
  c.opening_qty_buy = c.opening_qty_buy + s.opening_qty_buy,
  c.opening_qty_content = c.opening_qty_content + s.opening_qty_content,
  c.opening_total_value = c.opening_total_value + s.opening_total_value,
  c.in_qty_buy = c.in_qty_buy + s.in_qty_buy,
  c.in_qty_content = c.in_qty_content + s.in_qty_content,
  c.in_total_value = c.in_total_value + s.in_total_value,
  c.out_qty_buy = c.out_qty_buy + s.out_qty_buy,
  c.out_qty_content = c.out_qty_content + s.out_qty_content,
  c.out_total_value = c.out_total_value + s.out_total_value,
  c.discarded_qty_buy = c.discarded_qty_buy + s.discarded_qty_buy,
  c.discarded_qty_content = c.discarded_qty_content + s.discarded_qty_content,
  c.discarded_total_value = c.discarded_total_value + s.discarded_total_value,
  c.spoil_qty_buy = c.spoil_qty_buy + s.spoil_qty_buy,
  c.spoil_qty_content = c.spoil_qty_content + s.spoil_qty_content,
  c.spoilage_total_value = c.spoilage_total_value + s.spoilage_total_value,
  c.waste_qty_buy = c.waste_qty_buy + s.waste_qty_buy,
  c.waste_qty_content = c.waste_qty_content + s.waste_qty_content,
  c.waste_total_value = c.waste_total_value + s.waste_total_value,
  c.process_loss_qty_buy = c.process_loss_qty_buy + s.process_loss_qty_buy,
  c.process_loss_qty_content = c.process_loss_qty_content + s.process_loss_qty_content,
  c.process_loss_total_value = c.process_loss_total_value + s.process_loss_total_value,
  c.variance_qty_buy = c.variance_qty_buy + s.variance_qty_buy,
  c.variance_qty_content = c.variance_qty_content + s.variance_qty_content,
  c.variance_total_value = c.variance_total_value + s.variance_total_value,
  c.adjustment_plus_qty_buy = c.adjustment_plus_qty_buy + s.adjustment_plus_qty_buy,
  c.adjustment_plus_qty_content = c.adjustment_plus_qty_content + s.adjustment_plus_qty_content,
  c.adjustment_plus_total_value = c.adjustment_plus_total_value + s.adjustment_plus_total_value,
  c.adjustment_minus_qty_buy = c.adjustment_minus_qty_buy + s.adjustment_minus_qty_buy,
  c.adjustment_minus_qty_content = c.adjustment_minus_qty_content + s.adjustment_minus_qty_content,
  c.adjustment_minus_total_value = c.adjustment_minus_total_value + s.adjustment_minus_total_value,
  c.closing_qty_buy = c.closing_qty_buy + s.closing_qty_buy,
  c.closing_qty_content = c.closing_qty_content + s.closing_qty_content,
  c.total_value = c.total_value + s.total_value,
  c.movement_day_count = c.movement_day_count + s.movement_day_count,
  c.mutation_count = c.mutation_count + s.mutation_count,
  c.avg_cost_per_content = CASE
    WHEN ABS((c.closing_qty_content + s.closing_qty_content)) > 0.000001
      THEN ROUND((c.total_value + s.total_value) / (c.closing_qty_content + s.closing_qty_content), 6)
    ELSE c.avg_cost_per_content
  END,
  c.updated_at = NOW();

DELETE s
FROM inv_division_monthly_stock s
JOIN tmp_division_monthly_item_material_merge_targets t ON t.row_id = s.id;

UPDATE inv_warehouse_monthly_stock c
JOIN tmp_warehouse_monthly_item_material_merge_targets t ON t.canonical_row_id = c.id
JOIN inv_warehouse_monthly_stock s ON s.id = t.row_id
SET
  c.opening_qty_buy = c.opening_qty_buy + s.opening_qty_buy,
  c.opening_qty_content = c.opening_qty_content + s.opening_qty_content,
  c.opening_total_value = c.opening_total_value + s.opening_total_value,
  c.in_qty_buy = c.in_qty_buy + s.in_qty_buy,
  c.in_qty_content = c.in_qty_content + s.in_qty_content,
  c.in_total_value = c.in_total_value + s.in_total_value,
  c.out_qty_buy = c.out_qty_buy + s.out_qty_buy,
  c.out_qty_content = c.out_qty_content + s.out_qty_content,
  c.out_total_value = c.out_total_value + s.out_total_value,
  c.discarded_qty_buy = c.discarded_qty_buy + s.discarded_qty_buy,
  c.discarded_qty_content = c.discarded_qty_content + s.discarded_qty_content,
  c.discarded_total_value = c.discarded_total_value + s.discarded_total_value,
  c.spoil_qty_buy = c.spoil_qty_buy + s.spoil_qty_buy,
  c.spoil_qty_content = c.spoil_qty_content + s.spoil_qty_content,
  c.spoilage_total_value = c.spoilage_total_value + s.spoilage_total_value,
  c.waste_qty_buy = c.waste_qty_buy + s.waste_qty_buy,
  c.waste_qty_content = c.waste_qty_content + s.waste_qty_content,
  c.waste_total_value = c.waste_total_value + s.waste_total_value,
  c.process_loss_qty_buy = c.process_loss_qty_buy + s.process_loss_qty_buy,
  c.process_loss_qty_content = c.process_loss_qty_content + s.process_loss_qty_content,
  c.process_loss_total_value = c.process_loss_total_value + s.process_loss_total_value,
  c.variance_qty_buy = c.variance_qty_buy + s.variance_qty_buy,
  c.variance_qty_content = c.variance_qty_content + s.variance_qty_content,
  c.variance_total_value = c.variance_total_value + s.variance_total_value,
  c.adjustment_plus_qty_buy = c.adjustment_plus_qty_buy + s.adjustment_plus_qty_buy,
  c.adjustment_plus_qty_content = c.adjustment_plus_qty_content + s.adjustment_plus_qty_content,
  c.adjustment_plus_total_value = c.adjustment_plus_total_value + s.adjustment_plus_total_value,
  c.adjustment_minus_qty_buy = c.adjustment_minus_qty_buy + s.adjustment_minus_qty_buy,
  c.adjustment_minus_qty_content = c.adjustment_minus_qty_content + s.adjustment_minus_qty_content,
  c.adjustment_minus_total_value = c.adjustment_minus_total_value + s.adjustment_minus_total_value,
  c.closing_qty_buy = c.closing_qty_buy + s.closing_qty_buy,
  c.closing_qty_content = c.closing_qty_content + s.closing_qty_content,
  c.total_value = c.total_value + s.total_value,
  c.movement_day_count = c.movement_day_count + s.movement_day_count,
  c.mutation_count = c.mutation_count + s.mutation_count,
  c.avg_cost_per_content = CASE
    WHEN ABS((c.closing_qty_content + s.closing_qty_content)) > 0.000001
      THEN ROUND((c.total_value + s.total_value) / (c.closing_qty_content + s.closing_qty_content), 6)
    ELSE c.avg_cost_per_content
  END,
  c.updated_at = NOW();

DELETE s
FROM inv_warehouse_monthly_stock s
JOIN tmp_warehouse_monthly_item_material_merge_targets t ON t.row_id = s.id;

UPDATE inv_division_monthly_stock s
JOIN tmp_division_monthly_item_material_targets t ON t.row_id = s.id
SET s.stock_domain = 'MATERIAL',
    s.material_id = t.target_material_id,
    s.identity_key = s.profile_key,
    s.updated_at = NOW()
WHERE t.canonical_row_id IS NULL;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_warehouse_monthly_item_material_targets t ON t.row_id = s.id
SET s.stock_domain = 'MATERIAL',
    s.material_id = t.target_material_id,
    s.identity_key = s.profile_key,
    s.updated_at = NOW()
WHERE t.canonical_row_id IS NULL;

-- ============================================================
-- 3) Row MATERIAL legacy tanpa profile -> sejajarkan ke sibling kanonik
--    Karena row kanonik saudara sudah ada, qty/value dipindah ke row kanonik
--    lalu row legacy dihapus agar tidak membuat duplicate identity.
-- ============================================================
UPDATE inv_division_monthly_stock c
JOIN tmp_division_monthly_null_profile_targets t ON t.canonical_row_id = c.id
JOIN inv_division_monthly_stock s ON s.id = t.legacy_row_id
SET
  c.opening_qty_buy = c.opening_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.opening_qty_content / t.canonical_content_per_buy, 4) ELSE s.opening_qty_buy END,
  c.opening_qty_content = c.opening_qty_content + s.opening_qty_content,
  c.opening_total_value = c.opening_total_value + s.opening_total_value,
  c.in_qty_buy = c.in_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.in_qty_content / t.canonical_content_per_buy, 4) ELSE s.in_qty_buy END,
  c.in_qty_content = c.in_qty_content + s.in_qty_content,
  c.in_total_value = c.in_total_value + s.in_total_value,
  c.out_qty_buy = c.out_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.out_qty_content / t.canonical_content_per_buy, 4) ELSE s.out_qty_buy END,
  c.out_qty_content = c.out_qty_content + s.out_qty_content,
  c.out_total_value = c.out_total_value + s.out_total_value,
  c.discarded_qty_buy = c.discarded_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.discarded_qty_content / t.canonical_content_per_buy, 4) ELSE s.discarded_qty_buy END,
  c.discarded_qty_content = c.discarded_qty_content + s.discarded_qty_content,
  c.discarded_total_value = c.discarded_total_value + s.discarded_total_value,
  c.spoil_qty_buy = c.spoil_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.spoil_qty_content / t.canonical_content_per_buy, 4) ELSE s.spoil_qty_buy END,
  c.spoil_qty_content = c.spoil_qty_content + s.spoil_qty_content,
  c.spoilage_total_value = c.spoilage_total_value + s.spoilage_total_value,
  c.waste_qty_buy = c.waste_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.waste_qty_content / t.canonical_content_per_buy, 4) ELSE s.waste_qty_buy END,
  c.waste_qty_content = c.waste_qty_content + s.waste_qty_content,
  c.waste_total_value = c.waste_total_value + s.waste_total_value,
  c.process_loss_qty_buy = c.process_loss_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.process_loss_qty_content / t.canonical_content_per_buy, 4) ELSE s.process_loss_qty_buy END,
  c.process_loss_qty_content = c.process_loss_qty_content + s.process_loss_qty_content,
  c.process_loss_total_value = c.process_loss_total_value + s.process_loss_total_value,
  c.variance_qty_buy = c.variance_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.variance_qty_content / t.canonical_content_per_buy, 4) ELSE s.variance_qty_buy END,
  c.variance_qty_content = c.variance_qty_content + s.variance_qty_content,
  c.variance_total_value = c.variance_total_value + s.variance_total_value,
  c.adjustment_plus_qty_buy = c.adjustment_plus_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.adjustment_plus_qty_content / t.canonical_content_per_buy, 4) ELSE s.adjustment_plus_qty_buy END,
  c.adjustment_plus_qty_content = c.adjustment_plus_qty_content + s.adjustment_plus_qty_content,
  c.adjustment_plus_total_value = c.adjustment_plus_total_value + s.adjustment_plus_total_value,
  c.adjustment_minus_qty_buy = c.adjustment_minus_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.adjustment_minus_qty_content / t.canonical_content_per_buy, 4) ELSE s.adjustment_minus_qty_buy END,
  c.adjustment_minus_qty_content = c.adjustment_minus_qty_content + s.adjustment_minus_qty_content,
  c.adjustment_minus_total_value = c.adjustment_minus_total_value + s.adjustment_minus_total_value,
  c.closing_qty_buy = c.closing_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.closing_qty_content / t.canonical_content_per_buy, 4) ELSE s.closing_qty_buy END,
  c.closing_qty_content = c.closing_qty_content + s.closing_qty_content,
  c.total_value = c.total_value + s.total_value,
  c.movement_day_count = c.movement_day_count + s.movement_day_count,
  c.mutation_count = c.mutation_count + s.mutation_count,
  c.avg_cost_per_content = CASE
    WHEN ABS((c.closing_qty_content + s.closing_qty_content)) > 0.000001
      THEN ROUND((c.total_value + s.total_value) / (c.closing_qty_content + s.closing_qty_content), 6)
    ELSE c.avg_cost_per_content
  END,
  c.updated_at = NOW();

DELETE s
FROM inv_division_monthly_stock s
JOIN tmp_division_monthly_null_profile_targets t ON t.legacy_row_id = s.id;

UPDATE inv_warehouse_monthly_stock c
JOIN tmp_warehouse_monthly_null_profile_targets t ON t.canonical_row_id = c.id
JOIN inv_warehouse_monthly_stock s ON s.id = t.legacy_row_id
SET
  c.opening_qty_buy = c.opening_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.opening_qty_content / t.canonical_content_per_buy, 4) ELSE s.opening_qty_buy END,
  c.opening_qty_content = c.opening_qty_content + s.opening_qty_content,
  c.opening_total_value = c.opening_total_value + s.opening_total_value,
  c.in_qty_buy = c.in_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.in_qty_content / t.canonical_content_per_buy, 4) ELSE s.in_qty_buy END,
  c.in_qty_content = c.in_qty_content + s.in_qty_content,
  c.in_total_value = c.in_total_value + s.in_total_value,
  c.out_qty_buy = c.out_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.out_qty_content / t.canonical_content_per_buy, 4) ELSE s.out_qty_buy END,
  c.out_qty_content = c.out_qty_content + s.out_qty_content,
  c.out_total_value = c.out_total_value + s.out_total_value,
  c.discarded_qty_buy = c.discarded_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.discarded_qty_content / t.canonical_content_per_buy, 4) ELSE s.discarded_qty_buy END,
  c.discarded_qty_content = c.discarded_qty_content + s.discarded_qty_content,
  c.discarded_total_value = c.discarded_total_value + s.discarded_total_value,
  c.spoil_qty_buy = c.spoil_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.spoil_qty_content / t.canonical_content_per_buy, 4) ELSE s.spoil_qty_buy END,
  c.spoil_qty_content = c.spoil_qty_content + s.spoil_qty_content,
  c.spoilage_total_value = c.spoilage_total_value + s.spoilage_total_value,
  c.waste_qty_buy = c.waste_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.waste_qty_content / t.canonical_content_per_buy, 4) ELSE s.waste_qty_buy END,
  c.waste_qty_content = c.waste_qty_content + s.waste_qty_content,
  c.waste_total_value = c.waste_total_value + s.waste_total_value,
  c.process_loss_qty_buy = c.process_loss_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.process_loss_qty_content / t.canonical_content_per_buy, 4) ELSE s.process_loss_qty_buy END,
  c.process_loss_qty_content = c.process_loss_qty_content + s.process_loss_qty_content,
  c.process_loss_total_value = c.process_loss_total_value + s.process_loss_total_value,
  c.variance_qty_buy = c.variance_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.variance_qty_content / t.canonical_content_per_buy, 4) ELSE s.variance_qty_buy END,
  c.variance_qty_content = c.variance_qty_content + s.variance_qty_content,
  c.variance_total_value = c.variance_total_value + s.variance_total_value,
  c.adjustment_plus_qty_buy = c.adjustment_plus_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.adjustment_plus_qty_content / t.canonical_content_per_buy, 4) ELSE s.adjustment_plus_qty_buy END,
  c.adjustment_plus_qty_content = c.adjustment_plus_qty_content + s.adjustment_plus_qty_content,
  c.adjustment_plus_total_value = c.adjustment_plus_total_value + s.adjustment_plus_total_value,
  c.adjustment_minus_qty_buy = c.adjustment_minus_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.adjustment_minus_qty_content / t.canonical_content_per_buy, 4) ELSE s.adjustment_minus_qty_buy END,
  c.adjustment_minus_qty_content = c.adjustment_minus_qty_content + s.adjustment_minus_qty_content,
  c.adjustment_minus_total_value = c.adjustment_minus_total_value + s.adjustment_minus_total_value,
  c.closing_qty_buy = c.closing_qty_buy + CASE WHEN COALESCE(t.canonical_content_per_buy, 0) > 0 THEN ROUND(s.closing_qty_content / t.canonical_content_per_buy, 4) ELSE s.closing_qty_buy END,
  c.closing_qty_content = c.closing_qty_content + s.closing_qty_content,
  c.total_value = c.total_value + s.total_value,
  c.movement_day_count = c.movement_day_count + s.movement_day_count,
  c.mutation_count = c.mutation_count + s.mutation_count,
  c.avg_cost_per_content = CASE
    WHEN ABS((c.closing_qty_content + s.closing_qty_content)) > 0.000001
      THEN ROUND((c.total_value + s.total_value) / (c.closing_qty_content + s.closing_qty_content), 6)
    ELSE c.avg_cost_per_content
  END,
  c.updated_at = NOW();

DELETE s
FROM inv_warehouse_monthly_stock s
JOIN tmp_warehouse_monthly_null_profile_targets t ON t.legacy_row_id = s.id;

-- ============================================================
-- 4) Validasi ringkas setelah repair
-- ============================================================
SELECT
  COUNT(*) AS remaining_void_lot_leaks
FROM inv_material_fifo_lot l
JOIN inv_stock_adjustment a
  ON a.id = l.source_id
 AND l.source_table = 'inv_stock_adjustment'
WHERE a.status = 'VOID'
  AND (
    COALESCE(l.qty_balance, 0) <> 0
    OR UPPER(COALESCE(l.status, 'OPEN')) <> 'CLOSED'
  );

SELECT
  'division_monthly_item_backed_material_remaining' AS check_key,
  COUNT(*) AS total_rows
FROM inv_division_monthly_stock s
JOIN mst_item i ON i.id = s.item_id
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0
UNION ALL
SELECT
  'warehouse_monthly_item_backed_material_remaining',
  COUNT(*)
FROM inv_warehouse_monthly_stock s
JOIN mst_item i ON i.id = s.item_id
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0
UNION ALL
SELECT
  'division_monthly_null_profile_remaining',
  COUNT(*)
FROM inv_division_monthly_stock s
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'MATERIAL'
  AND COALESCE(s.profile_key, '') = ''
UNION ALL
SELECT
  'warehouse_monthly_null_profile_remaining',
  COUNT(*)
FROM inv_warehouse_monthly_stock s
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'MATERIAL'
  AND COALESCE(s.profile_key, '') = '';

COMMIT;

SELECT
  'Lanjutkan langkah berikut:' AS note,
  '1) buka /pos/stock-commit-audit 2) retry job gagal 3) cek /pos/stock-live dan /inventory/stock/division/reconcile' AS next_step;
