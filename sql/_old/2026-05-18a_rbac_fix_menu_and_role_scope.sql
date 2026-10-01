-- ============================================================
-- RBAC Fix — 2026-05-18
-- 1. Hapus menu sys.access_control yang 404 (sudah di-cover oleh sys.roles)
-- 2. Tambah division_scope_id ke auth_role (scope divisi, nullable)
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. Hapus menu sys.access_control (404 — tidak ada controller-nya)
--    sys.users dan sys.roles sudah mencakup semua kebutuhan RBAC.
-- ============================================================
DELETE FROM sys_sidebar_favorite
WHERE menu_id = (SELECT id FROM sys_menu WHERE menu_code = 'sys.access_control' LIMIT 1);

DELETE FROM sys_menu WHERE menu_code = 'sys.access_control';

-- ============================================================
-- 2. Tambah division_scope_id ke auth_role (nullable)
--    Role yang punya division_scope_id hanya relevan untuk divisi tersebut.
--    Dipakai sebagai label/filter pada UI — bukan hard enforcement otomatis.
--    Hard enforcement dilakukan per-controller jika diperlukan.
-- ============================================================
ALTER TABLE auth_role
  ADD COLUMN IF NOT EXISTS division_scope_id BIGINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Opsional: scope role ke divisi tertentu (kitchen, bar, dll). NULL = lintas divisi.'
  AFTER description,
  ADD KEY IF NOT EXISTS idx_auth_role_division (division_scope_id);

-- Coba tambah FK jika tabel org_division sudah ada
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'auth_role'
    AND CONSTRAINT_NAME = 'fk_auth_role_division'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE auth_role ADD CONSTRAINT fk_auth_role_division FOREIGN KEY (division_scope_id) REFERENCES org_division(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
