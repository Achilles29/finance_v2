# Standar Reason Adjustment Component

## Bucket Sesuai `inv_component_adjustment_line`

1. `qty_waste` -> `waste_reason_code`
2. `qty_spoil` -> `spoil_reason_code`
3. `qty_adjust_pos` -> `adjustment_plus_reason_code`
4. `qty_adjust_neg` -> `adjustment_minus_reason_code`

## Inventaris Data Existing Saat Audit

Ringkasan hasil cek data `db_finance` pada 2026-06-19:

1. `WASTE`
   - `other`: 14
   - `expired_opened`: 2
2. `SPOILAGE`
   - `other`: 21
3. `ADJUSTMENT_PLUS`
   - `opening_correction`: 15
   - `other`: 12
   - `manual_reclass`: 4
   - `stock_found`: 1
4. `ADJUSTMENT_MINUS`
   - `counting_error`: 4
   - `other`: 2
   - `system_mismatch`: 2

## Katalog Reason Baku

### `WASTE`

1. `cancel_order`
2. `kitchen_error`
3. `overproduction`
4. `spillage`
5. `expired_opened`
6. `other`

### `SPOILAGE`

1. `expired`
2. `temperature_abuse`
3. `contamination`
4. `improper_storage`
5. `overstock`
6. `other`

### `ADJUSTMENT_PLUS`

1. `opening_correction`
2. `stock_found`
3. `manual_reclass`
4. `other`

### `ADJUSTMENT_MINUS`

1. `counting_error`
2. `system_mismatch`
3. `unrecorded_usage`
4. `process_loss`
5. `theft_suspected`
6. `other`

## Catatan Normalisasi

1. Legacy `over_usage` pada minus dipetakan ke `unrecorded_usage`.
2. Blank / unknown dipaksa jadi `other`.
3. UI dan model save harus memakai katalog yang sama agar tidak lahir reason liar baru.
