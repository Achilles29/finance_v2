-- Aman dinonaktifkan karena tidak lagi dipakai controller aktif atau sudah digantikan page lain.

UPDATE sys_page
SET is_active = 0,
    updated_at = NOW()
WHERE page_code IN (
    'master.purchase.company_account',
    'procurement.purchasing.index'
)
  AND is_active = 1;