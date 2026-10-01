-- ============================================================
-- FIX: Repair identity_key STALE di inv_division_monthly_stock
-- Date   : 2026-06-15
-- Scope  : db_finance (server)
-- Penyebab:
--   Baris yang dibuat saat material_id=NULL menyimpan identity_key
--   sebagai SHA256(item|0|uom...) — material_id=0 ikut di-hash.
--   Setelah Repair Material ID mengisi material_id, identity_key
--   tidak ikut diperbarui sehingga menjadi "stale".
--
-- Aturan: untuk row yang punya profile_key IS NOT NULL,
--         identity_key HARUS = profile_key (sesuai buildInventoryMonthlyIdentityKey).
--
-- Dampak jika dibiarkan:
--   - upsertMonthlyStock (saat PO/SR/POS baru) tidak menemukan row lama
--     → INSERT row baru dengan identity_key=profile_key (yang benar)
--     → double row untuk profil yang sama → double count di reconcile
--
-- Verifikasi sebelum jalankan (harus > 0 untuk ada yang perlu diperbaiki):
-- SELECT COUNT(*) FROM inv_division_monthly_stock
-- WHERE profile_key IS NOT NULL AND profile_key != identity_key;
-- ============================================================

-- Fix: samakan identity_key dengan profile_key untuk semua baris stale
-- UPDATE IGNORE: baris yang konflikh unique key (profil duplikat) dilewati tanpa error
UPDATE IGNORE inv_division_monthly_stock
SET identity_key = profile_key,
    updated_at   = NOW()
WHERE profile_key IS NOT NULL
  AND profile_key != identity_key;

-- Verifikasi setelah dijalankan (harus = 0):
-- SELECT COUNT(*) FROM inv_division_monthly_stock
-- WHERE profile_key IS NOT NULL AND profile_key != identity_key;

-- ============================================================
-- Item yang terpengaruh (sesuai data server 2026-06-15):
--   BUNCIS, CHOCO BALL, GULA PASIR KITCHEN, ICE CUBE,
--   JAMUR ENOKI, KECAP MANIS, KEJU MOZARELLA, KEMIRI,
--   KOL UNGU, KUNYIT, MINYAK GORENG, MSG, TOMAT
--
-- PENTING: Tidak ada data yang hilang. Nilai closing_qty_content
-- sudah benar. Hanya identity_key yang perlu diselaraskan.
-- ============================================================
