INSERT INTO sys_page (page_code, page_name, module, description, is_active, created_at)
SELECT seed.page_code, seed.page_name, seed.module, seed.description, 1, NOW()
FROM (
    SELECT 'dashboard.index' AS page_code, 'Dashboard' AS page_name, 'DASHBOARD' AS module, 'Ringkasan operasional utama aplikasi.' AS description
    UNION ALL SELECT 'my.home.index', 'Beranda Saya', 'MY_PORTAL', 'Beranda ringkasan portal pegawai.'
    UNION ALL SELECT 'my.attendance.index', 'Absensi Saya', 'MY_PORTAL', 'Riwayat dan aksi absensi pegawai.'
    UNION ALL SELECT 'my.leave.index', 'Pengajuan Izin Saya', 'MY_PORTAL', 'Pengajuan izin, sakit, dan koreksi absensi pribadi.'
    UNION ALL SELECT 'my.payroll.index', 'Payroll Saya', 'MY_PORTAL', 'Estimasi gaji dan slip gaji pribadi.'
    UNION ALL SELECT 'my.profile.index', 'Profil Saya', 'MY_PORTAL', 'Profil dan data diri pegawai.'
    UNION ALL SELECT 'my.meal.index', 'Uang Makan Saya', 'MY_PORTAL', 'Ledger uang makan pegawai.'
    UNION ALL SELECT 'my.cash_advance.index', 'Kasbon Saya', 'MY_PORTAL', 'Monitoring kasbon dan cicilan pegawai.'
) seed
LEFT JOIN sys_page existing ON existing.page_code = seed.page_code
WHERE existing.id IS NULL;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT role_seed.role_id, target_page.id, role_seed.can_view, role_seed.can_create, role_seed.can_edit, role_seed.can_delete, role_seed.can_export, NOW()
FROM (
    SELECT ar.id AS role_id, 1 AS can_view, 0 AS can_create, 0 AS can_edit, 0 AS can_delete, 0 AS can_export
    FROM auth_role ar
    WHERE ar.is_active = 1
) role_seed
JOIN sys_page target_page ON target_page.page_code = 'dashboard.index'
LEFT JOIN auth_role_permission existing ON existing.role_id = role_seed.role_id AND existing.page_id = target_page.id
WHERE existing.role_id IS NULL;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT rp.role_id, target_page.id, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export, NOW()
FROM auth_role_permission rp
JOIN sys_page source_page ON source_page.id = rp.page_id AND source_page.page_code = 'my.adjustment.index'
JOIN sys_page target_page ON target_page.page_code IN (
    'my.home.index',
    'my.attendance.index',
    'my.leave.index',
    'my.payroll.index',
    'my.profile.index',
    'my.meal.index',
    'my.cash_advance.index'
)
LEFT JOIN auth_role_permission existing ON existing.role_id = rp.role_id AND existing.page_id = target_page.id
WHERE existing.role_id IS NULL;

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'dashboard.index'
SET m.page_id = p.id
WHERE m.menu_code = 'dashboard'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.home.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.home'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.attendance.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.attendance'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.leave.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.leave'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.payroll.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.payroll'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.profile.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.profile'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.meal.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.meal'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'my.cash_advance.index'
SET m.page_id = p.id
WHERE m.menu_code = 'my.cash_advance'
  AND (m.page_id IS NULL OR m.page_id <> p.id);

-- Menu grup ini sengaja tidak diberi page_id karena hanya parent struktural.
-- grp.finance
-- grp.purchase