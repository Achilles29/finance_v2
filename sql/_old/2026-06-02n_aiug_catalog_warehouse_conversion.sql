START TRANSACTION;

-- Canonical AIUG profile setup:
-- - price profile 6000 uses legacy transaction profile_key c306...
-- - price profile 9500 uses existing material profile_key b678...
-- - malformed legacy item profiles are deactivated
-- - corrupt duplicate material profile 1485 is deactivated

UPDATE mst_purchase_catalog
SET line_kind = 'MATERIAL',
    material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    content_per_buy = 19000.000000,
    conversion_factor_to_content = 19000.00000000,
    profile_key = 'c306a1d063a59ca4c5d873f951634205cca552c8f01dba8bf2f9c0dd4da657d6',
    notes = TRIM(CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END, 'AIUG canonical GLN->ML conversion 2026-06-02')),
    updated_at = NOW()
WHERE id = 927;

UPDATE mst_purchase_catalog
SET buy_uom_id = 35,
    content_uom_id = 9,
    content_per_buy = 19000.000000,
    conversion_factor_to_content = 19000.00000000,
    notes = TRIM(CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END, 'AIUG canonical GLN->ML conversion 2026-06-02')),
    updated_at = NOW()
WHERE id = 1486;

UPDATE mst_purchase_catalog
SET buy_uom_id = 35,
    content_uom_id = 9,
    content_per_buy = 19000.000000,
    conversion_factor_to_content = 19000.00000000,
    is_active = 0,
    notes = TRIM(CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END, 'AIUG duplicate/corrupt profile deactivated 2026-06-02')),
    updated_at = NOW()
WHERE id = 1485;

UPDATE mst_purchase_catalog
SET is_active = 0,
    notes = TRIM(CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END, 'AIUG malformed legacy profile deactivated 2026-06-02')),
    updated_at = NOW()
WHERE id IN (140, 185);

-- Convert legacy warehouse purchase/receipt chain from PCS->PCS into GLN->ML material identity.
UPDATE pur_purchase_order_line
SET line_kind = 'MATERIAL',
    material_id = 264,
    buy_uom_id = 35,
    content_per_buy = 19000.000000,
    qty_content = 152000.0000,
    content_uom_id = 9,
    conversion_factor_to_content = 19000.00000000,
    snapshot_material_name = 'AIR ISI ULANG GALON',
    snapshot_buy_uom_code = 'GLN',
    snapshot_content_uom_code = 'ML',
    updated_at = NOW()
WHERE id = 2;

UPDATE pur_purchase_receipt_line
SET line_kind = 'MATERIAL',
    material_id = 264,
    qty_buy_received = 8.0000,
    buy_uom_id = 35,
    qty_content_received = 152000.0000,
    content_uom_id = 9,
    conversion_factor_to_content = 19000.00000000,
    profile_key = 'c306a1d063a59ca4c5d873f951634205cca552c8f01dba8bf2f9c0dd4da657d6',
    updated_at = NOW()
WHERE id = 1;

UPDATE pur_store_request_line
SET line_kind = 'MATERIAL',
    material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    qty_buy_requested = 1.0000,
    qty_content_requested = 19000.0000,
    qty_buy_fulfilled = 1.0000,
    qty_content_fulfilled = 19000.0000,
    updated_at = NOW()
WHERE id = 2;

UPDATE pur_store_request_fulfillment_line
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    qty_buy_posted = 1.0000,
    qty_content_posted = 19000.0000,
    unit_cost_snapshot = 0.315789,
    updated_at = NOW()
WHERE id = 1;

UPDATE inv_material_fifo_issue_log
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    issue_qty = 19000.0000
WHERE id = 12;

UPDATE inv_material_fifo_issue_line
SET qty_out = 19000.0000,
    unit_cost = 0.315789,
    total_cost = 6000.00,
    source_balance_before = 152000.0000,
    source_balance_after = 133000.0000,
    target_balance_before = 0.0000,
    target_balance_after = 19000.0000
WHERE id = 12;

UPDATE inv_material_fifo_lot
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    qty_in = 152000.0000,
    qty_out = 19000.0000,
    qty_balance = 133000.0000,
    unit_cost = 0.315789,
    updated_at = NOW()
WHERE id = 776;

UPDATE inv_material_fifo_lot
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    qty_in = 19000.0000,
    qty_out = 0.0000,
    qty_balance = 19000.0000,
    unit_cost = 0.315789,
    updated_at = NOW()
WHERE id = 791;

UPDATE inv_material_fifo_lot
SET buy_uom_id = 35,
    content_uom_id = 9,
    updated_at = NOW()
WHERE id IN (805, 806);

UPDATE inv_stock_movement_log
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    qty_buy_delta = 8.0000,
    qty_content_delta = 152000.0000,
    qty_buy_after = 8.0000,
    qty_content_after = 152000.0000,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    unit_cost = 0.315789
WHERE id = 433;

UPDATE inv_stock_movement_log
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    qty_buy_delta = -1.0000,
    qty_content_delta = -19000.0000,
    qty_buy_after = 7.0000,
    qty_content_after = 133000.0000,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    unit_cost = 0.315789
WHERE id = 448;

UPDATE inv_stock_movement_log
SET material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    qty_buy_delta = 1.0000,
    qty_content_delta = 19000.0000,
    qty_buy_after = 1.0000,
    qty_content_after = 19000.0000,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    unit_cost = 0.315789
WHERE id = 449;

UPDATE inv_stock_movement_log
SET buy_uom_id = 35,
    content_uom_id = 9,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML'
WHERE id IN (629, 698, 701);

UPDATE inv_warehouse_monthly_stock
SET stock_domain = 'MATERIAL',
    material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    in_qty_buy = 8.0000,
    in_qty_content = 152000.0000,
    in_total_value = 48000.00,
    out_qty_buy = 1.0000,
    out_qty_content = 19000.0000,
    out_total_value = 6000.00,
    closing_qty_buy = 7.0000,
    closing_qty_content = 133000.0000,
    avg_cost_per_content = 0.315789,
    total_value = 42000.00,
    updated_at = NOW()
WHERE id = 165;

UPDATE inv_division_monthly_stock
SET stock_domain = 'MATERIAL',
    material_id = 264,
    buy_uom_id = 35,
    content_uom_id = 9,
    profile_content_per_buy = 19000.000000,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    in_qty_buy = 1.0000,
    in_qty_content = 19000.0000,
    in_total_value = 6000.00,
    closing_qty_buy = 1.0000,
    closing_qty_content = 19000.0000,
    avg_cost_per_content = 0.315789,
    total_value = 6000.00,
    updated_at = NOW()
WHERE id = 640;

UPDATE inv_division_monthly_stock
SET buy_uom_id = 35,
    content_uom_id = 9,
    profile_buy_uom_code = 'GLN',
    profile_content_uom_code = 'ML',
    updated_at = NOW()
WHERE id = 668;

COMMIT;
