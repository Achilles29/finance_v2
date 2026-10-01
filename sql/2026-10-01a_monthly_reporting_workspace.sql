-- Additive report registry only. No inventory, accounting, or cash amounts change.
INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'procurement.sr_report.index','Laporan SR','PURCHASE','PURCHASE','Realisasi SR, permintaan, matrix, rincian dan ekspor bulanan',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='procurement.sr_report.index');
INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'procurement.material_report.index','Laporan Bahan Baku ke Divisi','PURCHASE','PURCHASE','Purchase langsung dan SR persediaan bahan baku ke divisi',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='procurement.material_report.index');
INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'finance.monthly_analysis.index','Analisis Keuangan Bulanan','FINANCE','FINANCE','Arus kas, perbandingan bulan, rincian dan ringkasan jurnal',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='finance.monthly_analysis.index');

INSERT INTO sys_menu (parent_id,menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type)
SELECT parent.id,'procurement.report.sr','Laporan SR','ri-file-chart-line','/store-requests/report',p.id,51,1,'MAIN'
FROM sys_menu parent JOIN sys_page p ON p.page_code='procurement.sr_report.index'
WHERE parent.menu_code='grp.purchase' AND NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code='procurement.report.sr');
INSERT INTO sys_menu (parent_id,menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type)
SELECT parent.id,'procurement.report.materials','Bahan Baku ke Divisi','ri-route-line','/procurement/reports/division-materials',p.id,52,1,'MAIN'
FROM sys_menu parent JOIN sys_page p ON p.page_code='procurement.material_report.index'
WHERE parent.menu_code='grp.purchase' AND NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code='procurement.report.materials');
INSERT INTO sys_menu (parent_id,menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type)
SELECT parent.id,'finance.monthly_analysis','Analisis Keuangan Bulanan','ri-bar-chart-grouped-line','/finance-reports/monthly-analysis',p.id,94,1,'MAIN'
FROM sys_menu parent JOIN sys_page p ON p.page_code='finance.monthly_analysis.index'
WHERE parent.menu_code='grp.finance' AND NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code='finance.monthly_analysis');

-- Preserve existing decisions on reruns. New reports are view/export only.
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT r.id,p.id,1,0,0,0,1 FROM auth_role r JOIN sys_page p
ON p.page_code IN ('procurement.sr_report.index','procurement.material_report.index','finance.monthly_analysis.index')
WHERE r.role_code='SUPERADMIN' AND NOT EXISTS (SELECT 1 FROM auth_role_permission x WHERE x.role_id=r.id AND x.page_id=p.id);
-- SR inherits SR visibility, never division-request privileges implicitly.
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT a.role_id,p.id,a.can_view,0,0,0,LEAST(a.can_view,a.can_export)
FROM auth_role_permission a JOIN sys_page old ON old.id=a.page_id AND old.page_code='procurement.store_request.index'
JOIN sys_page p ON p.page_code='procurement.sr_report.index'
WHERE NOT EXISTS (SELECT 1 FROM auth_role_permission x WHERE x.role_id=a.role_id AND x.page_id=p.id);
-- Combined material report requires both purchase-report and SR visibility.
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT a.role_id,p.id,LEAST(a.can_view,b.can_view),0,0,0,LEAST(a.can_view,b.can_view,a.can_export,b.can_export)
FROM auth_role_permission a JOIN sys_page pa ON pa.id=a.page_id AND pa.page_code='purchase.report.index'
JOIN auth_role_permission b ON b.role_id=a.role_id JOIN sys_page pb ON pb.id=b.page_id AND pb.page_code='procurement.store_request.index'
JOIN sys_page p ON p.page_code='procurement.material_report.index'
WHERE NOT EXISTS (SELECT 1 FROM auth_role_permission x WHERE x.role_id=a.role_id AND x.page_id=p.id);
-- Global finance access only; do not grant cross-company cash visibility to division-scoped roles.
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT a.role_id,p.id,MAX(a.can_view),0,0,0,MAX(LEAST(a.can_view,a.can_export))
FROM auth_role_permission a JOIN auth_role r ON r.id=a.role_id AND r.division_scope_id IS NULL
JOIN sys_page old ON old.id=a.page_id AND old.page_code IN ('finance.accounting.index','finance.control.index')
JOIN sys_page p ON p.page_code='finance.monthly_analysis.index'
WHERE NOT EXISTS (SELECT 1 FROM auth_role_permission x WHERE x.role_id=a.role_id AND x.page_id=p.id)
GROUP BY a.role_id,p.id;
UPDATE auth_role r SET permissions_updated_at=CURRENT_TIMESTAMP
WHERE EXISTS (SELECT 1 FROM auth_role_permission a JOIN sys_page p ON p.id=a.page_id
WHERE a.role_id=r.id AND p.page_code IN ('procurement.sr_report.index','procurement.material_report.index','finance.monthly_analysis.index'));
