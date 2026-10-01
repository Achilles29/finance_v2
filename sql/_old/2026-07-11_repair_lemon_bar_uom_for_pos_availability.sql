START TRANSACTION;

-- FISH & CHIPS memakai LEMON dari BAR dengan satuan resep BUAH.
-- Master LEMON dan item LEMON juga memakai content_uom_id BUAH (20).
-- Receipt/lot 2026-07-05 salah tersimpan sebagai PCS (1), sehingga cache POS membaca stok LEMON BUAH = 0.

SET @material_id := 132;
SET @item_id := 5;
SET @division_id := 2;
SET @destination_type := 'BAR';
SET @receipt_id := 795;
SET @receipt_line_id := 1275;
SET @wrong_uom_id := 1;   -- PCS
SET @target_uom_id := 20; -- BUAH
SET @target_uom_code := 'BUAH';
SET @profile_key := '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257';

UPDATE pur_purchase_receipt_line
SET content_uom_id = @target_uom_id,
    updated_at = NOW()
WHERE id = @receipt_line_id
  AND purchase_receipt_id = @receipt_id
  AND item_id = @item_id
  AND material_id = @material_id
  AND content_uom_id = @wrong_uom_id;

UPDATE inv_stock_movement_log
SET content_uom_id = @target_uom_id,
    profile_content_uom_code = @target_uom_code
WHERE movement_scope = 'DIVISION'
  AND receipt_id = @receipt_id
  AND receipt_line_id = @receipt_line_id
  AND item_id = @item_id
  AND material_id = @material_id
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND profile_key = @profile_key
  AND content_uom_id = @wrong_uom_id;

UPDATE inv_material_fifo_lot
SET content_uom_id = @target_uom_id,
    updated_at = NOW()
WHERE id = 4781
  AND location_scope = 'DIVISION'
  AND receipt_id = @receipt_id
  AND receipt_line_id = @receipt_line_id
  AND item_id = @item_id
  AND material_id = @material_id
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND profile_key = @profile_key
  AND content_uom_id = @wrong_uom_id;

UPDATE inv_division_monthly_stock
SET content_uom_id = @target_uom_id,
    profile_content_uom_code = @target_uom_code,
    notes = CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-11: content UOM PCS -> BUAH for POS availability'),
    updated_at = NOW()
WHERE id = 5279
  AND month_key = '2026-07-01'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND profile_key = @profile_key
  AND content_uom_id = @wrong_uom_id;

COMMIT;

