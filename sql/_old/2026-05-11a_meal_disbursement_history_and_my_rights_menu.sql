SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- 1) PH eligibility backfill: effective date minimal dari join_date
-- supaya grant PH historis tidak terblokir karena effective_date terlalu baru.
-- =========================================================
UPDATE att_ph_eligibility pe
JOIN org_employee e ON e.id = pe.employee_id
SET pe.effective_date = COALESCE(e.join_date, pe.effective_date)
WHERE e.join_date IS NOT NULL
  AND pe.effective_date > e.join_date;

-- =========================================================
-- 2) Meal disbursement history (anti double payout per pegawai per tanggal)
-- =========================================================
CREATE TABLE IF NOT EXISTS pay_meal_disbursement (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  disbursement_no VARCHAR(60) NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  disbursement_date DATE NOT NULL,
  company_account_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','PAID','VOID') NOT NULL DEFAULT 'DRAFT',
  total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_meal_disbursement_no (disbursement_no),
  KEY idx_pay_meal_disbursement_period (period_start, period_end),
  KEY idx_pay_meal_disbursement_status (status),
  CONSTRAINT fk_pay_meal_disbursement_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_meal_disbursement_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  disbursement_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  att_daily_id BIGINT UNSIGNED NULL,
  meal_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  transfer_status ENUM('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PENDING',
  transfer_ref_no VARCHAR(100) NULL,
  paid_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_meal_line_employee_date (employee_id, attendance_date),
  UNIQUE KEY uk_pay_meal_line_disbursement_date (disbursement_id, employee_id, attendance_date),
  KEY idx_pay_meal_line_disbursement (disbursement_id),
  KEY idx_pay_meal_line_att_daily (att_daily_id),
  CONSTRAINT fk_pay_meal_line_disbursement FOREIGN KEY (disbursement_id) REFERENCES pay_meal_disbursement(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_meal_line_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pay_meal_line_att_daily FOREIGN KEY (att_daily_id) REFERENCES att_daily(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 3) Employee portal pages/menu: Lembur, PH, Tambahan/Pengurangan
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('my.overtime.index', 'Lembur Saya', 'MY_PORTAL', 'Riwayat lembur pegawai', 1),
  ('my.ph.index', 'PH Saya', 'MY_PORTAL', 'Saldo & histori PH pegawai', 1),
  ('my.adjustment.index', 'Tambahan/Pengurangan Saya', 'MY_PORTAL', 'Riwayat tambahan/pengurangan manual pegawai', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1,
       0,
       0,
       0,
       0,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('my.overtime.index','my.ph.index','my.adjustment.index')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  src.url,
  p.id,
  src.sort_order,
  1,
  'MY',
  NULL
FROM (
  SELECT 'my.overtime' AS menu_code, 'Lembur Saya' AS menu_label, 'ri-time-line' AS icon, '/my/overtime' AS url, 'my.overtime.index' AS page_code, 6 AS sort_order
  UNION ALL SELECT 'my.ph', 'PH Saya', 'ri-calendar-check-line', '/my/ph-ledger', 'my.ph.index', 7
  UNION ALL SELECT 'my.adjustment', 'Tambahan/Pengurangan', 'ri-exchange-dollar-line', '/my/manual-adjustments', 'my.adjustment.index', 8
) src
JOIN sys_page p ON p.page_code = src.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET sort_order = 9,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'my.cash_advance' AND sidebar_type = 'MY';

UPDATE sys_menu
SET sort_order = 10,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'my.profile' AND sidebar_type = 'MY';

COMMIT;
