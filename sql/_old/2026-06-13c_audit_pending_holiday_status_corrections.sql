SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13c_audit_pending_holiday_status_corrections.sql
-- Tujuan :
-- 1) Audit pengajuan koreksi status lama yang masih meminta status HOLIDAY
-- 2) Membantu review manual karena setelah patch konsep final,
--    status HOLIDAY tidak boleh lagi diajukan manual
-- ============================================================

SELECT
  r.id,
  r.employee_id,
  e.employee_code,
  e.employee_name,
  r.request_date,
  r.request_type,
  r.requested_status,
  r.status AS approval_status,
  r.reason,
  s.shift_code,
  s.shift_name
FROM att_pending_request r
LEFT JOIN org_employee e
  ON e.id = r.employee_id
LEFT JOIN att_shift_schedule ss
  ON ss.employee_id = r.employee_id
 AND ss.schedule_date = r.request_date
LEFT JOIN att_shift s
  ON s.id = ss.shift_id
WHERE r.request_type = 'STATUS_CORRECTION'
  AND UPPER(COALESCE(r.requested_status, '')) = 'HOLIDAY'
ORDER BY r.request_date DESC, e.employee_name ASC, r.id DESC;
