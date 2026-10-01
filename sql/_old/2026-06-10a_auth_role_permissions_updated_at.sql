-- ============================================================
-- 2026-06-10a: Permission cache invalidation + workbench page fix
-- ============================================================
-- Masalah yang diperbaiki:
--
--   1. Role matrix diubah admin → user yang sudah login tidak
--      mendapat perubahan sampai logout-login ulang.
--
--   2. User ditambah/dicopot dari role → session user terdampak
--      tidak pernah direfresh (kode lama hanya bilang "login ulang").
--
--   3. Override permission user diubah → hanya refresh session
--      jika user yang diedit adalah diri sendiri.
--
--   4. procurement.workbench.index ("Procurement API Access") adalah
--      page gate internal lama yang tidak muncul natural di matrix UI.
--      Admin ceklis procurement.store_request.index tapi tidak tahu
--      workbench juga harus diceklis → workbench terhapus saat save,
--      user dapat 403 meski matrix terlihat benar.
--      FIX: controller kini pakai page_code yang sama dengan matrix UI
--      (PAGE_SR = procurement.store_request.index,
--       PAGE_DIVISION = procurement.division.index).
--      Workbench dinonaktifkan agar tidak muncul lagi di matrix.
--
-- Solusi session cache:
--   - auth_role.permissions_updated_at  → distamp saat matrix diubah
--   - auth_user.permissions_updated_at  → distamp saat role/override user diubah
--   - MY_Controller cek kedua sinyal ini tiap 120 detik (throttled),
--     lalu auto-refresh permission dari DB tanpa user perlu logout.
--
-- File ini IDEMPOTENT — aman dijalankan ulang (IF NOT EXISTS).
-- ============================================================

-- 1. Kolom sinyal di level role (permission matrix berubah)
ALTER TABLE auth_role
  ADD COLUMN IF NOT EXISTS permissions_updated_at DATETIME NULL DEFAULT NULL
  AFTER is_active;

-- 2. Kolom sinyal di level user (role assignment atau override berubah)
ALTER TABLE auth_user
  ADD COLUMN IF NOT EXISTS permissions_updated_at DATETIME NULL DEFAULT NULL;

-- 3. Nonaktifkan procurement.workbench.index — page gate internal lama
--    yang membingungkan karena tidak sinkron dengan label di matrix UI.
--    Kini controller langsung memakai procurement.store_request.index
--    dan procurement.division.index yang tampil jelas di matrix.
UPDATE sys_page
  SET is_active = 0
  WHERE page_code = 'procurement.workbench.index';

-- 4. Stamp role MGR agar user yang sedang login dengan role Manajemen
--    langsung terdeteksi stale pada request berikutnya.
--    Kondisi: hanya update jika belum distamp atau stampnya sudah > 1 jam lalu,
--    agar aman dijalankan ulang tanpa mengganggu fresh session.
UPDATE auth_role
  SET permissions_updated_at = NOW()
  WHERE role_code = 'MGR'
    AND (permissions_updated_at IS NULL
         OR permissions_updated_at < NOW() - INTERVAL 1 HOUR);
