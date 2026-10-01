-- ============================================================
-- ZERO OUT: TING TING CRUMBLE di lokasi BAR (salah catat)
-- Aksi: void semua lot BAR + hapus monthly_stock BAR untuk
--       component ini agar tidak muncul lagi di saldo.
-- ============================================================

-- STEP 1 — Konfirmasi component & lot yang akan di-nolkan
SELECT
    c.id            AS component_id,
    c.component_code,
    c.component_name,
    l.id            AS lot_id,
    l.lot_no,
    l.location_type,
    l.division_id,
    d.name          AS division_name,
    l.qty_in_total,
    l.qty_out_total,
    l.qty_balance,
    l.unit_cost,
    l.status,
    l.receipt_date
FROM mst_component c
JOIN inv_component_lot l ON l.component_id = c.id
LEFT JOIN mst_operational_division d ON d.id = l.division_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND l.location_type IN ('BAR', 'BAR_EVENT');

-- STEP 2 — Lihat monthly stock BAR yang akan dihapus
SELECT
    ms.month_key,
    ms.location_type,
    ms.division_id,
    d.name AS division_name,
    ms.closing_qty,
    ms.avg_cost,
    ms.total_value
FROM inv_component_monthly_stock ms
JOIN mst_component c ON c.id = ms.component_id
LEFT JOIN mst_operational_division d ON d.id = ms.division_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND ms.location_type IN ('BAR', 'BAR_EVENT')
ORDER BY ms.month_key DESC;

-- ============================================================
-- STEP 3 — EKSEKUSI ZERO OUT
-- Cek hasil Step 1 & 2 dulu sebelum lanjut.
-- ============================================================

START TRANSACTION;

-- 3A. Void semua lot TING TING CRUMBLE di BAR
UPDATE inv_component_lot l
JOIN mst_component c ON c.id = l.component_id
SET
    l.qty_balance = 0,
    l.status      = 'VOID',
    l.updated_at  = NOW()
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND l.location_type IN ('BAR', 'BAR_EVENT')
  AND l.status <> 'VOID';

SELECT ROW_COUNT() AS lot_rows_voided;

-- 3B. Hapus monthly stock BAR untuk component ini
--     (monthly_stock akan di-rebuild via Repair di UI jika diperlukan)
DELETE ms FROM inv_component_monthly_stock ms
JOIN mst_component c ON c.id = ms.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND ms.location_type IN ('BAR', 'BAR_EVENT');

SELECT ROW_COUNT() AS monthly_stock_rows_deleted;

-- 3C. Tambahkan movement VOID ke movement_log sebagai audit trail
--     (agar histori tercatat bahwa stok ini dinolkan secara manual)
INSERT INTO inv_component_movement_log
    (location_type, division_id, component_id, uom_id,
     movement_date, movement_datetime, movement_type,
     qty_in, qty_out, qty_before, qty_after,
     unit_cost, source_table, source_module, notes,
     created_at, updated_at)
SELECT
    l.location_type,
    l.division_id,
    l.component_id,
    l.uom_id,
    CURDATE()           AS movement_date,
    NOW()               AS movement_datetime,
    'VOID'              AS movement_type,
    0                   AS qty_in,
    l.qty_in_total - l.qty_out_total AS qty_out,   -- jumlah yang di-void
    l.qty_in_total - l.qty_out_total AS qty_before,
    0                   AS qty_after,
    l.unit_cost,
    'inv_component_lot' AS source_table,
    'MANUAL_VOID'       AS source_module,
    CONCAT('Manual void: stok BAR salah catat, lot ', l.lot_no) AS notes,
    NOW()               AS created_at,
    NOW()               AS updated_at
FROM inv_component_lot l
JOIN mst_component c ON c.id = l.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND l.location_type IN ('BAR', 'BAR_EVENT')
  AND (l.qty_in_total - l.qty_out_total) > 0;   -- hanya yang masih ada saldo

SELECT ROW_COUNT() AS movement_log_inserted;

-- ============================================================
-- STEP 4 — Verifikasi sebelum COMMIT
-- ============================================================
SELECT
    l.id, l.lot_no, l.location_type,
    l.qty_balance, l.status
FROM inv_component_lot l
JOIN mst_component c ON c.id = l.component_id
WHERE (c.component_name LIKE '%TING TING CRUMBLE%' OR c.component_code LIKE '%CRUMBLE%')
  AND l.location_type IN ('BAR', 'BAR_EVENT');

-- Jika lot_rows_voided > 0 dan qty_balance semua = 0, status = VOID → COMMIT
-- Jika ada yang tidak sesuai → ROLLBACK

COMMIT;
-- atau: ROLLBACK;

-- ============================================================
-- SETELAH COMMIT:
-- • Stok TING TING CRUMBLE di BAR sudah = 0, tidak akan muncul
--   lagi di saldo / adjustment form.
-- • Tidak perlu Repair karena monthly_stock sudah dihapus.
-- • Jika ingin stok tercatat di divisi yang BENAR, buat Opening
--   baru untuk divisi yang benar via /production/component-openings.
-- ============================================================
