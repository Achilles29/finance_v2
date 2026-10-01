-- ============================================================
-- FIX: Backfill material_id di inv_division_monthly_stock
-- Date   : 2026-06-15
-- Scope  : db_finance
-- Ticket : Repair LOT gagal karena baris monthly stock tidak punya material_id
-- ============================================================

-- Isi material_id dari mst_item.material_id untuk baris yang fixable
-- (item sudah punya material di katalog, tapi monthly stock belum terisi)
UPDATE inv_division_monthly_stock ms
JOIN mst_item i ON i.id = ms.item_id AND i.material_id IS NOT NULL
SET ms.material_id = i.material_id,
    ms.updated_at  = NOW()
WHERE ms.material_id IS NULL;

-- Verifikasi: setelah dijalankan, hanya item non-material (kemasan, plastik, dll)
-- yang masih punya material_id = NULL di monthly_stock.
-- SELECT COUNT(*) AS still_null_fixable
-- FROM inv_division_monthly_stock ms
-- JOIN mst_item i ON i.id = ms.item_id AND i.material_id IS NOT NULL
-- WHERE ms.material_id IS NULL;
-- => Harus = 0

-- ============================================================
-- Penyebab: baris-baris ini dibuat oleh proses lama (legacy PO / SR / Adjustment / Opening)
-- sebelum normalizeCanonicalPayload menambah logika resolve material_id dari item.
-- Untuk future-proofing, normalizeCanonicalPayload di InventoryLedger.php sudah
-- otomatis mengisi material_id dari mst_item saat movement baru dibuat.
-- ============================================================
