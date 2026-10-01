SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-29a_attendance_schedule_monthly_override_authority.sql
-- Tujuan :
-- 1) Menentukan jabatan yang boleh menyetujui override batas
--    jadwal kerja bulanan.
-- 2) Menentukan user spesifik yang boleh menyetujui override.
-- Superadmin tetap menjadi fallback keamanan aplikasi.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS att_schedule_monthly_override_position (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_id BIGINT UNSIGNED NOT NULL,
  position_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_att_schedule_monthly_override_position (policy_id, position_id),
  KEY idx_att_schedule_monthly_override_position_position (position_id),
  CONSTRAINT fk_att_schedule_monthly_override_position_policy
    FOREIGN KEY (policy_id) REFERENCES att_attendance_policy(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_schedule_monthly_override_position_position
    FOREIGN KEY (position_id) REFERENCES org_position(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Jabatan yang boleh mengoverride batas jadwal bulanan';

CREATE TABLE IF NOT EXISTS att_schedule_monthly_override_user (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_att_schedule_monthly_override_user (policy_id, user_id),
  KEY idx_att_schedule_monthly_override_user_user (user_id),
  CONSTRAINT fk_att_schedule_monthly_override_user_policy
    FOREIGN KEY (policy_id) REFERENCES att_attendance_policy(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_schedule_monthly_override_user_user
    FOREIGN KEY (user_id) REFERENCES auth_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='User spesifik yang boleh mengoverride batas jadwal bulanan';

ALTER TABLE att_schedule_monthly_override
  COMMENT = 'Persetujuan otoritas untuk jadwal pegawai melampaui batas hari kerja bulanan';

COMMIT;

SELECT 'attendance monthly schedule override authority ready' AS status;
