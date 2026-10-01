START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, matrix_group, description, is_active)
SELECT 'wa.report_schedule', 'WA Jadwal Laporan', 'WHATSAPP', 'WHATSAPP', 'Jadwal laporan otomatis dan daftar command bot WhatsApp', 1
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'wa.report_schedule'
);

SET @page_id := (SELECT id FROM sys_page WHERE page_code = 'wa.report_schedule' LIMIT 1);
SET @parent_id := (
  SELECT id
  FROM sys_menu
  WHERE menu_code IN ('grp.whatsapp', 'grp.wa')
     OR menu_label = 'WhatsApp'
  ORDER BY id
  LIMIT 1
);
SET @parent_id := COALESCE(@parent_id, 403);

UPDATE sys_menu
SET sort_order = sort_order + 1
WHERE parent_id = @parent_id
  AND sort_order >= 4
  AND menu_code <> 'wa.report_schedule';

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type)
SELECT @parent_id, 'wa.report_schedule', 'Jadwal Laporan', 'ri-calendar-schedule-line', 'wa/template/schedules', @page_id, 4, 1, 'MAIN'
WHERE NOT EXISTS (
  SELECT 1 FROM sys_menu WHERE menu_code = 'wa.report_schedule'
);

UPDATE sys_menu
SET parent_id = @parent_id,
    menu_label = 'Jadwal Laporan',
    icon = 'ri-calendar-schedule-line',
    url = 'wa/template/schedules',
    page_id = @page_id,
    sort_order = 4,
    is_active = 1,
    sidebar_type = 'MAIN'
WHERE menu_code = 'wa.report_schedule';

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export)
SELECT rp.role_id, @page_id, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export
FROM auth_role_permission rp
JOIN sys_page p ON p.id = rp.page_id AND p.page_code = 'wa.template'
WHERE NOT EXISTS (
  SELECT 1
  FROM auth_role_permission x
  WHERE x.role_id = rp.role_id
    AND x.page_id = @page_id
);

COMMIT;
