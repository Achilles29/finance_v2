SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-28b_pos_reservation_creator_audit_and_cashier_independence.sql
-- Tujuan :
-- 1) Memisahkan input reservasi dari kewajiban membuka sesi kasir.
-- 2) Menyimpan akun login pembuat/perubah/penyetuju reservasi.
-- 3) Membolehkan DP reservasi dicatat sebelum ada kasir aktif.
-- 4) Menegaskan hak akses penuh Superadmin, Management, HOD, dan Barista.
--
-- Catatan:
-- - Verifikasi ke POS tetap hanya dapat dilakukan oleh kasir dengan sesi aktif.
-- - cashier_employee_id pada DP pra-kasir boleh NULL; jejak akun tetap ada
--   pada reservasi dan mutasi rekening.
-- - Aman dijalankan berulang.
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_reservation
  ADD COLUMN IF NOT EXISTS verified_by_user_id BIGINT UNSIGNED NULL AFTER verified_by,
  ADD COLUMN IF NOT EXISTS rejected_by_user_id BIGINT UNSIGNED NULL AFTER rejected_by,
  ADD COLUMN IF NOT EXISTS cancelled_by_user_id BIGINT UNSIGNED NULL AFTER cancelled_by,
  ADD COLUMN IF NOT EXISTS created_by_user_id BIGINT UNSIGNED NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS updated_by_user_id BIGINT UNSIGNED NULL AFTER created_by_user_id,
  ADD INDEX IF NOT EXISTS idx_pos_reservation_created_by_user (created_by_user_id),
  ADD INDEX IF NOT EXISTS idx_pos_reservation_updated_by_user (updated_by_user_id);

ALTER TABLE pos_reservation_state_log
  ADD COLUMN IF NOT EXISTS actor_user_id BIGINT UNSIGNED NULL AFTER actor_employee_id,
  ADD INDEX IF NOT EXISTS idx_pos_reservation_state_log_user (actor_user_id);

-- DP reservasi boleh diterima oleh akun yang berhak sebelum orang tersebut
-- membuka kasir. Setelah diterima kasir, pembayaran final tetap selalu
-- memakai cashier_employee_id dan cashier_session_id dari sesi kasir.
ALTER TABLE pos_payment
  MODIFY COLUMN cashier_employee_id BIGINT UNSIGNED NULL;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  role.id,
  page.id,
  1, 1, 1, 1, 1,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'pos.reservation.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'HOD', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  COLUMN_NAME,
  IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'pos_payment' AND COLUMN_NAME = 'cashier_employee_id')
    OR (TABLE_NAME = 'pos_reservation' AND COLUMN_NAME IN (
      'created_by_user_id', 'updated_by_user_id', 'verified_by_user_id',
      'rejected_by_user_id', 'cancelled_by_user_id'
    ))
    OR (TABLE_NAME = 'pos_reservation_state_log' AND COLUMN_NAME = 'actor_user_id')
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT
  role.role_code,
  permission.can_view,
  permission.can_create,
  permission.can_edit,
  permission.can_delete,
  permission.can_export
FROM auth_role_permission permission
JOIN auth_role role ON role.id = permission.role_id
JOIN sys_page page ON page.id = permission.page_id
WHERE page.page_code = 'pos.reservation.index'
  AND role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'HOD', 'BARISTA')
ORDER BY role.role_code;
