START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_wh_same_uom_fix;
CREATE TEMPORARY TABLE tmp_wh_same_uom_fix AS
SELECT
    s.id AS snapshot_id,
    s.snapshot_month,
    s.item_id,
    s.material_id,
    s.profile_key,
    CASE
        WHEN ms.id IS NOT NULL AND ROUND(COALESCE(ms.opening_qty_buy, 0), 4) > 0.0000
            THEN ms.opening_qty_buy
        WHEN ROUND(COALESCE(s.profile_content_per_buy, 1), 6) <> 1.000000
             AND ROUND(COALESCE(s.opening_qty_content, 0), 4) > 0.0000
            THEN ROUND(s.opening_qty_content / NULLIF(s.profile_content_per_buy, 0), 4)
        ELSE s.opening_qty_buy
    END AS normalized_open_qty
FROM inv_warehouse_stock_opening_snapshot s
LEFT JOIN inv_warehouse_monthly_stock ms
    ON ms.month_key = s.snapshot_month
   AND COALESCE(ms.item_id, 0) = COALESCE(s.item_id, 0)
   AND COALESCE(ms.material_id, 0) = COALESCE(s.material_id, 0)
   AND COALESCE(ms.profile_key, '') = COALESCE(s.profile_key, '')
WHERE COALESCE(s.buy_uom_id, 0) = COALESCE(s.content_uom_id, 0)
  AND (
      ROUND(COALESCE(s.profile_content_per_buy, 1), 6) <> 1.000000
      OR (
          ms.id IS NOT NULL
          AND ROUND(COALESCE(ms.profile_content_per_buy, 1), 6) <> 1.000000
      )
  );

-- Samakan master item untuk item yang memang sudah diputuskan buy/content UOM-nya sama.
UPDATE mst_item i
JOIN (
    SELECT DISTINCT item_id
    FROM tmp_wh_same_uom_fix
    WHERE item_id IS NOT NULL
) f ON f.item_id = i.id
SET
    i.content_per_buy = 1.000000,
    i.updated_at = NOW()
WHERE COALESCE(i.buy_uom_id, 0) = COALESCE(i.content_uom_id, 0)
  AND ROUND(COALESCE(i.content_per_buy, 1), 6) <> 1.000000;

-- Rapikan purchase catalog untuk item yang sama, agar PO berikutnya tidak kembali memakai rasio lama.
UPDATE mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
SET
    c.content_uom_id = i.content_uom_id,
    c.content_per_buy = 1.000000,
    c.conversion_factor_to_content = 1.00000000,
    c.updated_at = NOW()
WHERE COALESCE(i.buy_uom_id, 0) = COALESCE(i.content_uom_id, 0)
  AND (
      COALESCE(c.content_uom_id, 0) <> COALESCE(i.content_uom_id, 0)
      OR ROUND(COALESCE(c.content_per_buy, 1), 6) <> 1.000000
      OR ROUND(COALESCE(c.conversion_factor_to_content, 1), 8) <> 1.00000000
  );

-- Normalisasi opening snapshot gudang memakai kuantitas pack yang konsisten.
UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_wh_same_uom_fix f ON f.snapshot_id = s.id
LEFT JOIN mst_uom u ON u.id = s.content_uom_id
SET
    s.profile_content_per_buy = 1.000000,
    s.profile_content_uom_code = u.code,
    s.opening_qty_buy = f.normalized_open_qty,
    s.opening_qty_content = f.normalized_open_qty,
    s.opening_avg_cost_per_content = CASE
        WHEN ROUND(COALESCE(f.normalized_open_qty, 0), 4) > 0.0000
            THEN ROUND(s.opening_total_value / f.normalized_open_qty, 6)
        ELSE 0.000000
    END;

-- LOT opening gudang ikut dinormalisasi dari opening snapshot yang sama.
UPDATE inv_material_fifo_lot l
JOIN tmp_wh_same_uom_fix f
    ON f.snapshot_id = l.source_id
   AND l.source_table = 'inv_warehouse_stock_opening_snapshot'
SET
    l.qty_in = f.normalized_open_qty,
    l.qty_out = 0.0000,
    l.qty_balance = f.normalized_open_qty,
    l.unit_cost = CASE
        WHEN ROUND(COALESCE(f.normalized_open_qty, 0), 4) > 0.0000
            THEN ROUND((
                SELECT s.opening_total_value
                FROM inv_warehouse_stock_opening_snapshot s
                WHERE s.id = f.snapshot_id
            ) / f.normalized_open_qty, 6)
        ELSE 0.000000
    END,
    l.status = CASE
        WHEN ROUND(COALESCE(f.normalized_open_qty, 0), 4) > 0.0000 THEN 'OPEN'
        ELSE 'CLOSED'
    END
WHERE l.location_scope = 'WAREHOUSE';

-- Kalau monthly opening sudah ada, ikut disamakan.
UPDATE inv_warehouse_monthly_opening s
LEFT JOIN mst_uom u ON u.id = s.content_uom_id
SET
    s.profile_content_per_buy = 1.000000,
    s.profile_content_uom_code = u.code,
    s.opening_qty_content = s.opening_qty_buy,
    s.opening_avg_cost_per_content = CASE
        WHEN ROUND(COALESCE(s.opening_qty_buy, 0), 4) > 0.0000
            THEN ROUND(s.opening_total_value / s.opening_qty_buy, 6)
        ELSE 0.000000
    END
WHERE COALESCE(s.buy_uom_id, 0) = COALESCE(s.content_uom_id, 0)
  AND ROUND(COALESCE(s.profile_content_per_buy, 1), 6) <> 1.000000;

-- Monthly stock memakai qty_buy sebagai angka pack final yang konsisten.
UPDATE inv_warehouse_monthly_stock s
LEFT JOIN mst_uom u ON u.id = s.content_uom_id
SET
    s.profile_content_per_buy = 1.000000,
    s.profile_content_uom_code = u.code,
    s.opening_qty_content = s.opening_qty_buy,
    s.in_qty_content = s.in_qty_buy,
    s.out_qty_content = s.out_qty_buy,
    s.discarded_qty_content = s.discarded_qty_buy,
    s.spoil_qty_content = s.spoil_qty_buy,
    s.waste_qty_content = s.waste_qty_buy,
    s.process_loss_qty_content = s.process_loss_qty_buy,
    s.variance_qty_content = s.variance_qty_buy,
    s.adjustment_plus_qty_content = s.adjustment_plus_qty_buy,
    s.adjustment_minus_qty_content = s.adjustment_minus_qty_buy,
    s.closing_qty_content = s.closing_qty_buy,
    s.avg_cost_per_content = CASE
        WHEN ROUND(COALESCE(s.closing_qty_buy, 0), 4) > 0.0000
            THEN ROUND(s.total_value / s.closing_qty_buy, 6)
        ELSE 0.000000
    END
WHERE COALESCE(s.buy_uom_id, 0) = COALESCE(s.content_uom_id, 0)
  AND ROUND(COALESCE(s.profile_content_per_buy, 1), 6) <> 1.000000;

-- Movement log gudang yang masih menyimpan rasio lama dinormalkan ke qty pack.
UPDATE inv_stock_movement_log g
LEFT JOIN mst_uom u ON u.id = g.content_uom_id
SET
    g.unit_cost = ROUND(g.unit_cost * g.profile_content_per_buy, 6),
    g.qty_content_delta = g.qty_buy_delta,
    g.qty_content_after = g.qty_buy_after,
    g.profile_content_uom_code = u.code,
    g.profile_content_per_buy = 1.000000
WHERE g.movement_scope = 'WAREHOUSE'
  AND COALESCE(g.buy_uom_id, 0) = COALESCE(g.content_uom_id, 0)
  AND ROUND(COALESCE(g.profile_content_per_buy, 1), 6) <> 1.000000;

-- Draft adjustment gudang ikut dinormalkan supaya draft yang belum diposting tidak gagal lagi.
UPDATE inv_stock_adjustment_line l
JOIN inv_stock_adjustment h ON h.id = l.adjustment_id
LEFT JOIN mst_uom u ON u.id = l.content_uom_id
SET
    l.unit_cost = ROUND(l.unit_cost * l.profile_content_per_buy, 6),
    l.available_qty_content = l.available_qty_buy,
    l.qty_waste_content = ROUND(l.qty_waste_content / NULLIF(l.profile_content_per_buy, 0), 4),
    l.qty_spoil_content = ROUND(l.qty_spoil_content / NULLIF(l.profile_content_per_buy, 0), 4),
    l.qty_process_loss_content = ROUND(l.qty_process_loss_content / NULLIF(l.profile_content_per_buy, 0), 4),
    l.qty_variance_content = ROUND(l.qty_variance_content / NULLIF(l.profile_content_per_buy, 0), 4),
    l.qty_adjustment_plus_content = ROUND(l.qty_adjustment_plus_content / NULLIF(l.profile_content_per_buy, 0), 4),
    l.profile_content_uom_code = u.code,
    l.profile_content_per_buy = 1.000000
WHERE h.stock_scope = 'WAREHOUSE'
  AND h.status = 'DRAFT'
  AND COALESCE(l.buy_uom_id, 0) = COALESCE(l.content_uom_id, 0)
  AND ROUND(COALESCE(l.profile_content_per_buy, 1), 6) <> 1.000000;

DROP TEMPORARY TABLE IF EXISTS tmp_wh_same_uom_fix;

COMMIT;
