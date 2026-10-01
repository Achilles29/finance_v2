START TRANSACTION;

-- Backup row terdampak sebelum repair sinkronisasi UOM/material bahan baku.
CREATE TABLE IF NOT EXISTS zz_backup_mst_purchase_catalog_20260711_uom_sync AS
SELECT *
FROM mst_purchase_catalog
WHERE id IN (140,185,1024,1025,1026,1027,1028,1029,1030,2055,2557,2567,2697);

CREATE TABLE IF NOT EXISTS zz_backup_pur_purchase_order_line_20260711_uom_sync AS
SELECT *
FROM pur_purchase_order_line
WHERE id IN (3441,3561,3897);

CREATE TABLE IF NOT EXISTS zz_backup_pur_purchase_receipt_line_20260711_uom_sync AS
SELECT *
FROM pur_purchase_receipt_line
WHERE id IN (1465);

CREATE TABLE IF NOT EXISTS zz_backup_inv_stock_movement_log_20260711_uom_sync AS
SELECT *
FROM inv_stock_movement_log
WHERE profile_key IN (
  '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257',
  '0e55002934a54e755ded4799797aeab826cbc26f9ec69c26fd958d457c837ce4',
  '839d107589b638f609fd403afc0a54e58259795ed08194d9f90b60b5b49f658e',
  '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
);

CREATE TABLE IF NOT EXISTS zz_backup_inv_material_fifo_lot_20260711_uom_sync AS
SELECT *
FROM inv_material_fifo_lot
WHERE id IN (3907,4997)
   OR profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043';

CREATE TABLE IF NOT EXISTS zz_backup_inv_material_fifo_issue_log_20260711_uom_sync AS
SELECT *
FROM inv_material_fifo_issue_log
WHERE profile_key IN (
  '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257',
  '839d107589b638f609fd403afc0a54e58259795ed08194d9f90b60b5b49f658e',
  '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
);

CREATE TABLE IF NOT EXISTS zz_backup_inv_division_monthly_stock_20260711_uom_sync AS
SELECT *
FROM inv_division_monthly_stock
WHERE id IN (5371,5499)
   OR profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043';

CREATE TABLE IF NOT EXISTS zz_backup_inv_division_stock_opening_snapshot_20260711_uom_sync AS
SELECT *
FROM inv_division_stock_opening_snapshot
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043';

CREATE TABLE IF NOT EXISTS zz_backup_inv_division_monthly_opname_20260711_uom_sync AS
SELECT *
FROM inv_division_monthly_opname
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043';

CREATE TABLE IF NOT EXISTS zz_backup_inv_stock_adjustment_line_20260711_uom_sync AS
SELECT *
FROM inv_stock_adjustment_line
WHERE profile_key = '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257';

-- A. Nonaktifkan katalog legacy yang material_id-nya tertukar dan sudah ada katalog aktif yang benar.
UPDATE mst_purchase_catalog
SET is_active = 0,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Disabled 2026-07-11: material_id tidak sesuai item material'), 255),
    updated_at = NOW()
WHERE id IN (1024,1025,1028,1029,1030);

UPDATE mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
JOIN mst_material m ON m.id = i.material_id
SET c.material_id = i.material_id,
    c.content_uom_id = m.content_uom_id,
    c.updated_at = NOW()
WHERE c.id IN (1024,1025,1028,1029,1030);

-- B. Repair katalog PEWARNA MAKANAN MERAH yang tidak punya katalog aktif benar.
UPDATE mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
JOIN mst_material m ON m.id = i.material_id
SET c.material_id = i.material_id,
    c.content_uom_id = m.content_uom_id,
    c.notes = LEFT(CONCAT(COALESCE(c.notes, ''), ' | Repair 2026-07-11: material_id/content_uom mengikuti item material'), 255),
    c.updated_at = NOW()
WHERE c.id = 1027;

-- C. Nonaktifkan katalog KEJU SPREADY yang salah UOM dan tidak pernah dipakai transaksi.
UPDATE mst_purchase_catalog
SET is_active = 0,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Disabled 2026-07-11: content_uom tidak sesuai material dan ada katalog pengganti'), 255),
    updated_at = NOW()
WHERE id = 2055;

UPDATE mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
JOIN mst_material m ON m.id = i.material_id
SET c.material_id = i.material_id,
    c.content_uom_id = m.content_uom_id,
    c.updated_at = NOW()
WHERE c.id = 2055;

UPDATE mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
JOIN mst_material m ON m.id = i.material_id
SET c.material_id = i.material_id,
    c.content_uom_id = m.content_uom_id,
    c.notes = LEFT(CONCAT(COALESCE(c.notes, ''), ' | Repair 2026-07-11: inactive legacy material/uom mengikuti item material'), 255),
    c.updated_at = NOW()
WHERE c.id IN (140,185,1026);

-- D. Repair katalog aktif yang dipakai transaksi agar UOM isi mengikuti master material.
UPDATE mst_purchase_catalog
SET content_uom_id = 20,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-11: content_uom PCS -> BUAH mengikuti mst_material'), 255),
    updated_at = NOW()
WHERE id = 2557;

UPDATE mst_purchase_catalog
SET content_uom_id = 1,
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-11: content_uom SLICE -> PCS mengikuti mst_material'), 255),
    updated_at = NOW()
WHERE id IN (2567,2697);

-- E. Repair PO line sumber profil LEMON dan DRIED LIME.
UPDATE pur_purchase_order_line
SET content_uom_id = 20,
    snapshot_content_uom_code = 'BUAH',
    updated_at = NOW()
WHERE id = 3441
  AND item_id = 5
  AND material_id = 132;

UPDATE pur_purchase_order_line
SET content_uom_id = 1,
    snapshot_content_uom_code = 'PCS',
    updated_at = NOW()
WHERE id IN (3561,3897)
  AND item_id = 264
  AND material_id = 64;

-- F. Repair receipt DRIED LIME yang sudah masuk sebagai SLICE.
UPDATE pur_purchase_receipt_line
SET content_uom_id = 1,
    updated_at = NOW()
WHERE id = 1465
  AND item_id = 264
  AND material_id = 64;

-- G. Repair movement/adjustment/lot/stock aktif LEMON dan DRIED LIME.
UPDATE inv_stock_movement_log
SET content_uom_id = 20,
    profile_content_uom_code = 'BUAH'
WHERE profile_key = '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257'
  AND item_id = 5
  AND material_id = 132;

UPDATE inv_stock_adjustment_line
SET content_uom_id = 20,
    profile_content_uom_code = 'BUAH',
    updated_at = NOW()
WHERE profile_key = '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257'
  AND item_id = 5
  AND material_id = 132;

UPDATE inv_material_fifo_issue_log
SET content_uom_id = 20
WHERE profile_key = '30eabedbddd7ff1c349786887e80f4a8bd2396072cc8c2bf5f34b0d3bf808257'
  AND item_id = 5
  AND material_id = 132;

UPDATE inv_stock_movement_log
SET content_uom_id = 1,
    profile_content_uom_code = 'PCS'
WHERE profile_key IN (
    '0e55002934a54e755ded4799797aeab826cbc26f9ec69c26fd958d457c837ce4',
    '839d107589b638f609fd403afc0a54e58259795ed08194d9f90b60b5b49f658e'
  )
  AND item_id = 264
  AND material_id = 64;

UPDATE inv_material_fifo_lot
SET content_uom_id = 1,
    updated_at = NOW()
WHERE id = 4997
  AND item_id = 264
  AND material_id = 64;

UPDATE inv_material_fifo_issue_log
SET content_uom_id = 1
WHERE profile_key IN (
    '0e55002934a54e755ded4799797aeab826cbc26f9ec69c26fd958d457c837ce4',
    '839d107589b638f609fd403afc0a54e58259795ed08194d9f90b60b5b49f658e'
  )
  AND item_id = 264
  AND material_id = 64;

UPDATE inv_division_monthly_stock
SET content_uom_id = 1,
    profile_content_uom_code = 'PCS',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-11: content_uom SLICE -> PCS mengikuti mst_material'), 255),
    updated_at = NOW()
WHERE id = 5371
  AND item_id = 264
  AND material_id = 64;

-- H. Repair metadata UOM BOWL TA legacy supaya tidak lagi dibaca sebagai ML.
-- Quantity tidak diubah di sini karena butuh keputusan stok fisik terpisah.
UPDATE inv_division_monthly_stock
SET content_uom_id = 1,
    profile_content_uom_code = 'PCS',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-11: content_uom ML -> PCS mengikuti mst_material'), 255),
    updated_at = NOW()
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
  AND item_id = 191
  AND material_id = 24;

UPDATE inv_division_stock_opening_snapshot
SET content_uom_id = 1,
    profile_content_uom_code = 'PCS',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-11: content_uom ML -> PCS mengikuti mst_material'), 255),
    updated_at = NOW()
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
  AND item_id = 191
  AND material_id = 24;

UPDATE inv_division_monthly_opname
SET content_uom_id = 1,
    profile_content_uom_code = 'PCS',
    updated_at = NOW()
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
  AND item_id = 191
  AND material_id = 24;

UPDATE inv_material_fifo_lot
SET content_uom_id = 1,
    updated_at = NOW()
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
  AND item_id = 191
  AND material_id = 24;

UPDATE inv_material_fifo_issue_log
SET content_uom_id = 1
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
  AND item_id = 191
  AND material_id = 24;

UPDATE inv_stock_movement_log
SET content_uom_id = 1,
    profile_content_uom_code = 'PCS'
WHERE profile_key = '8c42085ea4ceb1f0ae28d709735358ea41190a3404503faaa17ab362eb3ce043'
  AND item_id = 191
  AND material_id = 24;

COMMIT;
