<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Role_model — CRUD role dan matrix izin per halaman
 */
class Role_model extends CI_Model
{
    // ---------------------------------------------------------------
    // READ
    // ---------------------------------------------------------------

    public function get_all(bool $active_only = false): array
    {
        $this->db->select('r.*, COALESCE(d.division_name, \'—\') AS division_scope_name,
            (SELECT COUNT(*) FROM auth_user_role ur WHERE ur.role_id = r.id) AS user_count,
            (SELECT COUNT(*) FROM auth_role_permission rp WHERE rp.role_id = r.id) AS page_count', false);
        $this->db->from('auth_role r');
        $this->db->join('org_division d', 'd.id = r.division_scope_id', 'left');
        if ($active_only) {
            $this->db->where('r.is_active', 1);
        }
        $this->db->order_by('r.role_name');
        return $this->db->get()->result_array();
    }

    public function get_by_id(int $id): ?array
    {
        $this->db->select('r.*, COALESCE(d.division_name, NULL) AS division_scope_name', false);
        $this->db->from('auth_role r');
        $this->db->join('org_division d', 'd.id = r.division_scope_id', 'left');
        $this->db->where('r.id', $id);
        $this->db->limit(1);
        return $this->db->get()->row_array() ?: null;
    }

    /**
     * Ambil semua user yang memiliki role ini.
     */
    public function get_users_in_role(int $role_id): array
    {
        $this->db->select('u.id, u.username, u.email, u.is_active, u.last_login_at,
            e.employee_name, p.position_name, div.division_name', false);
        $this->db->from('auth_user_role ur');
        $this->db->join('auth_user u', 'u.id = ur.user_id');
        $this->db->join('org_employee e', 'e.id = u.employee_id', 'left');
        $this->db->join('org_position p', 'p.id = e.position_id', 'left');
        $this->db->join('org_division div', 'div.id = e.division_id', 'left');
        $this->db->where('ur.role_id', $role_id);
        $this->db->order_by('u.username');
        return $this->db->get()->result_array();
    }

    /**
     * Ambil semua halaman yang aktif, dikelompokkan per module,
     * sekaligus sertakan izin role ini untuk setiap halaman.
     *
     * Return: ['MODULE' => [['page_code'=>..., 'can_view'=>0, ...], ...], ...]
     */
    public function get_pages_with_permissions(int $role_id): array
    {
        $rows = $this->get_page_registry_snapshot($role_id, false);
        $grouped = [];
        foreach ($rows as $row) {
            $groupKey = (string)($row['resolved_group_code'] ?? $row['module'] ?? 'OTHER');
            $grouped[$groupKey][] = $row;
        }
        return $grouped;
    }

    public function get_matrix_group_registry(): array
    {
        $registry = $this->get_default_matrix_group_registry();
        $rows = $this->db
            ->select('group_code, group_label, icon, color, bg_color, sort_order')
            ->from('sys_matrix_group')
            ->order_by('sort_order', 'ASC')
            ->order_by('group_code', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['group_code'] ?? '')));
            if ($code === '') {
                continue;
            }

            if (!isset($registry[$code])) {
                $registry[$code] = [
                    'group_code' => $code,
                    'group_label' => $row['group_label'] ?: $code,
                    'icon' => $row['icon'] ?: 'ri-folder-line',
                    'color' => $row['color'] ?: '#64748b',
                    'bg_color' => $row['bg_color'] ?: '#f8fafc',
                    'sort_order' => (int)($row['sort_order'] ?? 9999),
                ];
                continue;
            }

            if (!empty($row['group_label'])) {
                $registry[$code]['group_label'] = $row['group_label'];
            }
            if (!empty($row['icon'])) {
                $registry[$code]['icon'] = $row['icon'];
            }
            if (!empty($row['color'])) {
                $registry[$code]['color'] = $row['color'];
            }
            if (!empty($row['bg_color'])) {
                $registry[$code]['bg_color'] = $row['bg_color'];
            }
        }

        uasort($registry, static function (array $a, array $b): int {
            $sortCmp = ((int)$a['sort_order']) <=> ((int)$b['sort_order']);
            if ($sortCmp !== 0) {
                return $sortCmp;
            }
            return strcmp((string)$a['group_code'], (string)$b['group_code']);
        });

        return $registry;
    }

    public function get_pages_for_matrix_layout(): array
    {
        return $this->get_page_registry_snapshot(null, true);
    }

    /**
     * Simpan matrix_group baru untuk satu halaman (sys_page).
     * Dipakai dari AJAX endpoint di Roles controller.
     */
    public function save_page_matrix_group(string $pageCode, string $newGroup): bool
    {
        if (trim($pageCode) === '') {
            return false;
        }

        if (!$this->db->field_exists('matrix_group', 'sys_page')) {
            return false;
        }

        $normalizedGroup = strtoupper(trim($newGroup));

        if ($normalizedGroup !== '') {
            $existingGroup = $this->db
                ->select('id')
                ->from('sys_matrix_group')
                ->where('group_code', $normalizedGroup)
                ->limit(1)
                ->get()
                ->row_array();

            if (!$existingGroup) {
                $nextSort = (int)$this->db
                    ->select_max('sort_order')
                    ->get('sys_matrix_group')
                    ->row('sort_order');

                $this->db->insert('sys_matrix_group', [
                    'group_code' => $normalizedGroup,
                    'group_label' => ucwords(strtolower(str_replace('_', ' ', $normalizedGroup))),
                    'icon' => 'ri-folder-line',
                    'color' => '#64748b',
                    'bg_color' => '#f8fafc',
                    'sort_order' => $nextSort > 0 ? $nextSort + 10 : 1000,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->db->where('page_code', $pageCode);
        return $this->db->update('sys_page', [
            'matrix_group' => $normalizedGroup !== '' ? $normalizedGroup : null,
        ]);
    }

    public function get_registry_audit_summary(): array
    {
        $activePageCount = (int)$this->db
            ->from('sys_page')
            ->where('is_active', 1)
            ->count_all_results();

        $activeMenuCount = (int)$this->db
            ->from('sys_menu')
            ->where('is_active', 1)
            ->count_all_results();

        $activeMenusWithoutPageCount = (int)$this->db
            ->from('sys_menu')
            ->where('is_active', 1)
            ->where('COALESCE(TRIM(url), \'\') <> \'\'', null, false)
            ->where('url <>', '#')
            ->where('page_id IS NULL', null, false)
            ->count_all_results();

        $snapshot = $this->get_page_registry_snapshot(null, false);
        $activePagesWithoutMenu = array_values(array_filter($snapshot, static function (array $row): bool {
            return empty($row['has_menu']);
        }));
        $activePagesWithoutMenuCount = count($activePagesWithoutMenu);
        $sharedPageRegistryRows = array_values(array_filter($snapshot, static function (array $row): bool {
            return (int)($row['menu_count'] ?? 0) > 1;
        }));

        $menusWithoutPage = $this->db
            ->select('id, menu_code, menu_label, url')
            ->from('sys_menu')
            ->where('is_active', 1)
            ->where('COALESCE(TRIM(url), \'\') <> \'\'', null, false)
            ->where('url <>', '#')
            ->where('page_id IS NULL', null, false)
            ->order_by('menu_label', 'ASC')
            ->limit(50)
            ->get()
            ->result_array();

        $pagesWithoutMenu = array_slice(array_map(static function (array $row): array {
            return [
                'id' => $row['page_id'],
                'page_code' => $row['page_code'],
                'page_name' => $row['page_name'],
                'module' => $row['module'],
                'matrix_group' => $row['matrix_group'] ?? '',
                'resolved_group_code' => $row['resolved_group_code'] ?? '',
                'usage_type' => $row['usage_type'] ?? 'orphan',
            ];
        }, $activePagesWithoutMenu), 0, 50);

        $controllerMissingPages = $this->get_controller_registry_gaps();
        $pagesWithoutPermissions = $this->get_pages_without_role_permissions();

        return [
            'active_page_count' => $activePageCount,
            'active_menu_count' => $activeMenuCount,
            'active_menus_without_page_count' => $activeMenusWithoutPageCount,
            'active_pages_without_menu_count' => (int)$activePagesWithoutMenuCount,
            'shared_page_registry_count' => count($sharedPageRegistryRows),
            'shared_page_registry_rows' => array_slice(array_map(static function (array $row): array {
                return [
                    'page_code' => $row['page_code'],
                    'page_name' => $row['page_name'],
                    'module' => $row['module'],
                    'resolved_group_code' => $row['resolved_group_code'] ?? '',
                    'menu_count' => (int)($row['menu_count'] ?? 0),
                    'menu_items' => $row['menu_items'] ?? [],
                ];
            }, $sharedPageRegistryRows), 0, 50),
            'controller_missing_page_count' => count($controllerMissingPages),
            'controller_missing_pages' => $controllerMissingPages,
            'pages_without_permission_count' => count($pagesWithoutPermissions),
            'pages_without_permissions' => $pagesWithoutPermissions,
            'menus_without_page' => $menusWithoutPage,
            'pages_without_menu' => $pagesWithoutMenu,
        ];
    }

    private function get_page_registry_snapshot(?int $roleId = null, bool $includeInactive = false): array
    {
        $hasMatrixGroup = $this->db->field_exists('matrix_group', 'sys_page');
        $select = 'p.id AS page_id, p.page_code, p.page_name, p.module, p.description, p.is_active';
        if ($hasMatrixGroup) {
            $select .= ', COALESCE(p.matrix_group, \'\') AS matrix_group';
        } else {
            $select .= ', \'\' AS matrix_group';
        }
        if ($roleId !== null) {
            $select .= ',
                COALESCE(rp.can_view,0) AS can_view,
                COALESCE(rp.can_create,0) AS can_create,
                COALESCE(rp.can_edit,0) AS can_edit,
                COALESCE(rp.can_delete,0) AS can_delete,
                COALESCE(rp.can_export,0) AS can_export';
        } else {
            $select .= ', 0 AS can_view, 0 AS can_create, 0 AS can_edit, 0 AS can_delete, 0 AS can_export';
        }

        $this->db->select($select, false);
        $this->db->from('sys_page p');
        if ($roleId !== null) {
            $this->db->join(
                'auth_role_permission rp',
                'rp.page_id = p.id AND rp.role_id = ' . (int)$roleId,
                'left'
            );
        }
        if (!$includeInactive) {
            $this->db->where('p.is_active', 1);
        }
        $pageRows = $this->db->get()->result_array();

        $menuRows = $this->get_active_menu_registry_rows();
        $menusByPageId = [];
        foreach ($menuRows as $menuRow) {
            $pageId = (int)($menuRow['page_id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }
            $menusByPageId[$pageId][] = $menuRow;
        }

        $groupRegistry = $this->get_matrix_group_registry();
        $controllerCodes = $this->get_controller_page_codes_lookup();
        $resolved = [];

        foreach ($pageRows as $row) {
            $pageId = (int)$row['page_id'];
            $pageMenus = $menusByPageId[$pageId] ?? [];
            usort($pageMenus, static function (array $a, array $b): int {
                $topSortCmp = ((int)$a['top_sort_order']) <=> ((int)$b['top_sort_order']);
                if ($topSortCmp !== 0) {
                    return $topSortCmp;
                }
                $typeCmp = strcmp((string)$a['sidebar_type'], (string)$b['sidebar_type']);
                if ($typeCmp !== 0) {
                    return $typeCmp;
                }
                $sortCmp = ((int)$a['sort_order']) <=> ((int)$b['sort_order']);
                if ($sortCmp !== 0) {
                    return $sortCmp;
                }
                return strcmp((string)$a['menu_code'], (string)$b['menu_code']);
            });

            $primaryMenu = $pageMenus[0] ?? null;
            $resolvedGroupCode = $this->resolve_page_group_code($row, $primaryMenu);
            $groupMeta = $groupRegistry[$resolvedGroupCode] ?? [
                'group_code' => $resolvedGroupCode,
                'group_label' => ucwords(strtolower(str_replace('_', ' ', $resolvedGroupCode))),
                'icon' => 'ri-folder-line',
                'color' => '#64748b',
                'bg_color' => '#f8fafc',
                'sort_order' => 9999,
            ];

            $row['has_menu'] = !empty($pageMenus) ? 1 : 0;
            $row['menu_count'] = count($pageMenus);
            $row['menu_items'] = $pageMenus;
            $row['menu_code'] = $primaryMenu['menu_code'] ?? '';
            $row['menu_label'] = $primaryMenu['menu_label'] ?? '';
            $row['menu_url'] = $primaryMenu['url'] ?? '';
            $row['menu_sort_order'] = (int)($primaryMenu['sort_order'] ?? 9999);
            $row['top_menu_code'] = $primaryMenu['top_menu_code'] ?? '';
            $row['top_menu_label'] = $primaryMenu['top_menu_label'] ?? '';
            $row['top_sort_order'] = (int)($primaryMenu['top_sort_order'] ?? 9999);
            $row['resolved_group_code'] = $groupMeta['group_code'];
            $row['resolved_group_label'] = $groupMeta['group_label'];
            $row['resolved_group_sort'] = (int)$groupMeta['sort_order'];
            $row['resolved_group_icon'] = $groupMeta['icon'];
            $row['resolved_group_color'] = $groupMeta['color'];
            $row['resolved_group_bg'] = $groupMeta['bg_color'];
            $row['resolved_row_bucket'] = $this->resolve_registry_row_bucket($row);
            $row['usage_type'] = isset($controllerCodes[strtolower((string)$row['page_code'])]) ? 'controller' : 'orphan';

            $resolved[] = $row;
        }

        usort($resolved, static function (array $a, array $b): int {
            $groupCmp = ((int)$a['resolved_group_sort']) <=> ((int)$b['resolved_group_sort']);
            if ($groupCmp !== 0) {
                return $groupCmp;
            }
            $menuCmp = ((int)$b['has_menu']) <=> ((int)$a['has_menu']);
            if ($menuCmp !== 0) {
                return $menuCmp;
            }
            $bucketCmp = ((int)$a['resolved_row_bucket']) <=> ((int)$b['resolved_row_bucket']);
            if ($bucketCmp !== 0) {
                return $bucketCmp;
            }
            $topCmp = ((int)$a['top_sort_order']) <=> ((int)$b['top_sort_order']);
            if ($topCmp !== 0) {
                return $topCmp;
            }
            $menuSortCmp = ((int)$a['menu_sort_order']) <=> ((int)$b['menu_sort_order']);
            if ($menuSortCmp !== 0) {
                return $menuSortCmp;
            }
            $labelA = strtolower(trim((string)($a['menu_label'] ?: $a['page_name'])));
            $labelB = strtolower(trim((string)($b['menu_label'] ?: $b['page_name'])));
            $labelCmp = strcmp($labelA, $labelB);
            if ($labelCmp !== 0) {
                return $labelCmp;
            }
            return strcmp((string)$a['page_code'], (string)$b['page_code']);
        });

        return $resolved;
    }

    private function resolve_registry_row_bucket(array $row): int
    {
        $resolvedGroup = strtoupper(trim((string)($row['resolved_group_code'] ?? '')));
        $menuCode = strtolower(trim((string)($row['menu_code'] ?? '')));
        $pageCode = strtolower(trim((string)($row['page_code'] ?? '')));
        $lookup = trim($menuCode . ' ' . $pageCode);

        if ($resolvedGroup === 'INVENTORY') {
            if (
                strpos($lookup, 'warehouse') !== false
                || strpos($lookup, 'gudang') !== false
            ) {
                return 10;
            }

            if (
                strpos($lookup, 'component') !== false
                || strpos($lookup, 'base/prepare') !== false
                || strpos($pageCode, 'production.component.') === 0
            ) {
                return 30;
            }

            if (
                strpos($lookup, 'division') !== false
                || strpos($lookup, 'material') !== false
                || strpos($lookup, 'daily-recon') !== false
                || strpos($lookup, 'opname.division') !== false
            ) {
                return 20;
            }
        }

        return 50;
    }

    private function get_active_menu_registry_rows(): array
    {
        $menuRows = $this->db
            ->select('id, parent_id, menu_code, menu_label, COALESCE(url, \'\') AS url, page_id, sort_order, sidebar_type')
            ->from('sys_menu')
            ->where('is_active', 1)
            ->order_by('sidebar_type', 'ASC')
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        $indexed = [];
        foreach ($menuRows as $row) {
            $indexed[(int)$row['id']] = $row;
        }

        foreach ($indexed as $id => $row) {
            $top = $row;
            $parentId = !empty($row['parent_id']) ? (int)$row['parent_id'] : 0;
            while ($parentId > 0 && isset($indexed[$parentId])) {
                $top = $indexed[$parentId];
                $parentId = !empty($top['parent_id']) ? (int)$top['parent_id'] : 0;
            }
            $indexed[$id]['top_menu_code'] = (string)($top['menu_code'] ?? $row['menu_code']);
            $indexed[$id]['top_menu_label'] = (string)($top['menu_label'] ?? $row['menu_label']);
            $indexed[$id]['top_sort_order'] = (int)($top['sort_order'] ?? $row['sort_order'] ?? 9999);
        }

        return array_values($indexed);
    }

    private function get_default_matrix_group_registry(): array
    {
        $definitions = [
            'DASHBOARD' => ['group_label' => 'Dashboard', 'icon' => 'ri-dashboard-line', 'color' => '#1d4ed8', 'bg_color' => '#eff6ff'],
            'POS' => ['group_label' => 'POS & Kasir', 'icon' => 'ri-store-2-line', 'color' => '#be185d', 'bg_color' => '#fdf2f8'],
            'PURCHASE' => ['group_label' => 'Purchase', 'icon' => 'ri-shopping-cart-2-line', 'color' => '#ea580c', 'bg_color' => '#fff7ed'],
            'INVENTORY' => ['group_label' => 'Inventory', 'icon' => 'ri-archive-drawer-line', 'color' => '#059669', 'bg_color' => '#f0fdf4'],
            'PRODUCT' => ['group_label' => 'Produk', 'icon' => 'ri-store-2-line', 'color' => '#7c3aed', 'bg_color' => '#f5f3ff'],
            'PRODUKSI' => ['group_label' => 'Base / Prepare', 'icon' => 'ri-flask-line', 'color' => '#0f766e', 'bg_color' => '#ecfeff'],
            'HR' => ['group_label' => 'Karyawan & HR', 'icon' => 'ri-team-line', 'color' => '#0284c7', 'bg_color' => '#f0f9ff'],
            'ATTENDANCE' => ['group_label' => 'Absensi', 'icon' => 'ri-calendar-check-line', 'color' => '#b45309', 'bg_color' => '#fffbeb'],
            'LOYALTY' => ['group_label' => 'Member & Promo', 'icon' => 'ri-user-star-line', 'color' => '#2563eb', 'bg_color' => '#eff6ff'],
            'FINANCE' => ['group_label' => 'Keuangan', 'icon' => 'ri-bank-line', 'color' => '#be123c', 'bg_color' => '#fff1f2'],
            'PAYROLL' => ['group_label' => 'Penggajian', 'icon' => 'ri-money-dollar-circle-line', 'color' => '#0d9488', 'bg_color' => '#f0fdfa'],
            'MASTER' => ['group_label' => 'Master Data', 'icon' => 'ri-database-2-line', 'color' => '#475569', 'bg_color' => '#f8fafc'],
            'AUTH' => ['group_label' => 'Hak Akses', 'icon' => 'ri-shield-keyhole-line', 'color' => '#2563eb', 'bg_color' => '#eff6ff'],
            'SYSTEM' => ['group_label' => 'Sistem', 'icon' => 'ri-settings-3-line', 'color' => '#475569', 'bg_color' => '#f1f5f9'],
            'MENU_BOOK' => ['group_label' => 'Menu Book', 'icon' => 'ri-book-open-line', 'color' => '#7c2d12', 'bg_color' => '#fff7ed'],
            'MY_PORTAL' => ['group_label' => 'Portal Saya', 'icon' => 'ri-user-settings-line', 'color' => '#4338ca', 'bg_color' => '#eef2ff'],
            'REPORT' => ['group_label' => 'Laporan', 'icon' => 'ri-bar-chart-2-line', 'color' => '#6d28d9', 'bg_color' => '#faf5ff'],
            'SYS' => ['group_label' => 'Sistem (Legacy)', 'icon' => 'ri-settings-3-line', 'color' => '#64748b', 'bg_color' => '#f8fafc'],
            'SISTEM' => ['group_label' => 'Sistem & Hak Akses (Legacy)', 'icon' => 'ri-settings-3-line', 'color' => '#64748b', 'bg_color' => '#f8fafc'],
        ];

        $orderMap = [];
        $topMenus = $this->db
            ->select('menu_code')
            ->from('sys_menu')
            ->where('is_active', 1)
            ->where('sidebar_type', 'MAIN')
            ->where('parent_id IS NULL', null, false)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        $nextSort = 10;
        foreach ($topMenus as $topMenu) {
            foreach ($this->map_top_menu_to_group_codes((string)($topMenu['menu_code'] ?? '')) as $groupCode) {
                if (!isset($orderMap[$groupCode])) {
                    $orderMap[$groupCode] = $nextSort;
                    $nextSort += 10;
                }
            }
        }

        if (!isset($orderMap['MY_PORTAL'])) {
            $orderMap['MY_PORTAL'] = $nextSort;
            $nextSort += 10;
        }
        if (!isset($orderMap['REPORT'])) {
            $orderMap['REPORT'] = $nextSort;
            $nextSort += 10;
        }
        if (!isset($orderMap['SYS'])) {
            $orderMap['SYS'] = $nextSort;
            $nextSort += 10;
        }
        if (!isset($orderMap['SISTEM'])) {
            $orderMap['SISTEM'] = $nextSort;
        }

        $registry = [];
        foreach ($definitions as $code => $meta) {
            $registry[$code] = [
                'group_code' => $code,
                'group_label' => $meta['group_label'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'bg_color' => $meta['bg_color'],
                'sort_order' => $orderMap[$code] ?? 9999,
            ];
        }

        return $registry;
    }

    private function map_top_menu_to_group_codes(string $menuCode): array
    {
        switch ($menuCode) {
            case 'dashboard':
                return ['DASHBOARD'];
            case 'pos.cashier':
            case 'pos.self_order':
            case 'pos.online_food':
            case 'grp.pos':
            case 'pos.report.group':
                return ['POS'];
            case 'grp.purchase':
                return ['PURCHASE'];
            case 'grp.inventory':
                return ['INVENTORY'];
            case 'produk':
                return ['PRODUCT', 'PRODUKSI'];
            case 'grp.hr':
                return ['HR', 'ATTENDANCE'];
            case 'grp.loyalty':
                return ['LOYALTY'];
            case 'grp.finance':
                return ['FINANCE'];
            case 'grp.payroll':
                return ['PAYROLL'];
            case 'grp.master':
                return ['MASTER'];
            case 'grp.system':
                return ['AUTH', 'SYSTEM'];
            case 'grp.menu_book':
                return ['MENU_BOOK'];
            case 'production.component.opening.monthly':
                return ['PRODUKSI'];
            default:
                return [];
        }
    }

    private function resolve_page_group_code(array $pageRow, ?array $primaryMenu): string
    {
        $explicit = strtoupper(trim((string)($pageRow['matrix_group'] ?? '')));
        if ($explicit !== '') {
            return $explicit;
        }

        $pageCode = strtolower(trim((string)($pageRow['page_code'] ?? '')));
        $topMenuCode = strtolower(trim((string)($primaryMenu['top_menu_code'] ?? '')));
        $sidebarType = strtoupper(trim((string)($primaryMenu['sidebar_type'] ?? '')));

        if ($sidebarType === 'MY' || strpos($pageCode, 'my.') === 0) {
            return 'MY_PORTAL';
        }

        switch ($topMenuCode) {
            case 'dashboard':
                return 'DASHBOARD';
            case 'pos.cashier':
            case 'pos.self_order':
            case 'pos.online_food':
            case 'grp.pos':
            case 'pos.report.group':
                return 'POS';
            case 'grp.purchase':
                return 'PURCHASE';
            case 'grp.inventory':
                return 'INVENTORY';
            case 'produk':
                return strpos($pageCode, 'production.') === 0 ? 'PRODUKSI' : 'PRODUCT';
            case 'grp.hr':
                return strpos($pageCode, 'attendance.') === 0 ? 'ATTENDANCE' : 'HR';
            case 'grp.loyalty':
                return 'LOYALTY';
            case 'grp.finance':
                return 'FINANCE';
            case 'grp.payroll':
                return 'PAYROLL';
            case 'grp.master':
                return 'MASTER';
            case 'grp.system':
                return strpos($pageCode, 'auth.') === 0 ? 'AUTH' : 'SYSTEM';
            case 'grp.menu_book':
                return 'MENU_BOOK';
            case 'production.component.opening.monthly':
                return 'PRODUKSI';
        }

        if (strpos($pageCode, 'dashboard.') === 0) return 'DASHBOARD';
        if (strpos($pageCode, 'auth.') === 0) return 'AUTH';
        if (strpos($pageCode, 'system.') === 0) return 'SYSTEM';
        if (strpos($pageCode, 'finance.') === 0) return 'FINANCE';
        if (strpos($pageCode, 'payroll.') === 0) return 'PAYROLL';
        if (strpos($pageCode, 'loyalty.') === 0) return 'LOYALTY';
        if (strpos($pageCode, 'attendance.') === 0) return 'ATTENDANCE';
        if (strpos($pageCode, 'hr.') === 0) return 'HR';
        if (strpos($pageCode, 'pos.') === 0) return 'POS';
        if (strpos($pageCode, 'procurement.') === 0 || strpos($pageCode, 'purchase.') === 0) return 'PURCHASE';
        if (strpos($pageCode, 'inventory.') === 0) return 'INVENTORY';
        if (strpos($pageCode, 'production.') === 0) return 'PRODUKSI';
        if (strpos($pageCode, 'product.') === 0) return 'PRODUCT';
        if (strpos($pageCode, 'menu_book.') === 0) return 'MENU_BOOK';
        if (strpos($pageCode, 'master.') === 0) return 'MASTER';

        $module = strtoupper(trim((string)($pageRow['module'] ?? 'SYSTEM')));
        return $module !== '' ? $module : 'SYSTEM';
    }

    private function get_controller_page_codes_lookup(): array
    {
        $basePath = APPPATH . 'controllers';
        $codes = [];
        if (!is_dir($basePath)) {
            return $codes;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());
            if ($contents === false || $contents === '') {
                continue;
            }

            if (!preg_match_all(
                "/'([a-z0-9_.]+(?:\\.index|\\.guide)|production\\.component\\.opname\\.monthly|production\\.component\\.opening\\.monthly|inventory\\.stock\\.opname\\.(?:division|warehouse)\\.monthly)'/i",
                $contents,
                $matches
            )) {
                continue;
            }

            foreach ($matches[1] as $code) {
                $codes[strtolower($code)] = true;
            }
        }

        return $codes;
    }

    private function get_controller_registry_gaps(): array
    {
        $controllerCodes = $this->get_controller_page_codes_lookup();
        if (empty($controllerCodes)) {
            return [];
        }

        $dbRows = $this->db
            ->select('LOWER(page_code) AS page_code')
            ->from('sys_page')
            ->get()
            ->result_array();

        $dbLookup = [];
        foreach ($dbRows as $row) {
            $dbLookup[(string)$row['page_code']] = true;
        }

        $missing = [];
        foreach (array_keys($controllerCodes) as $pageCode) {
            if (!isset($dbLookup[$pageCode])) {
                $missing[] = [
                    'page_code' => $pageCode,
                    'suggested_group' => $this->resolve_page_group_code([
                        'page_code' => $pageCode,
                        'module' => strtoupper(strtok($pageCode, '.')),
                        'matrix_group' => '',
                    ], null),
                ];
            }
        }

        usort($missing, static function (array $a, array $b): int {
            return strcmp((string)$a['page_code'], (string)$b['page_code']);
        });

        return $missing;
    }

    private function get_pages_without_role_permissions(): array
    {
        $rows = $this->db
            ->select('p.page_code, p.page_name, p.module, COUNT(rp.id) AS perm_rows', false)
            ->from('sys_page p')
            ->join('auth_role_permission rp', 'rp.page_id = p.id', 'left')
            ->where('p.is_active', 1)
            ->group_by('p.id, p.page_code, p.page_name, p.module')
            ->having('COUNT(rp.id) = 0', null, false)
            ->order_by('p.module', 'ASC')
            ->order_by('p.page_code', 'ASC')
            ->get()
            ->result_array();

        return array_map(static function (array $row): array {
            return [
                'page_code' => $row['page_code'],
                'page_name' => $row['page_name'],
                'module' => $row['module'],
            ];
        }, $rows);
    }

    /**
     * Ambil hanya izin yang sudah disimpan untuk role ini
     * (untuk display di tabel cepat)
     */
    public function get_permissions(int $role_id): array
    {
        $this->db->select('rp.*, p.page_code, p.page_name, p.module');
        $this->db->from('auth_role_permission rp');
        $this->db->join('sys_page p', 'p.id = rp.page_id');
        $this->db->where('rp.role_id', $role_id);
        $this->db->order_by('p.module, p.page_name');
        return $this->db->get()->result_array();
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    public function create(array $data): int|false
    {
        if ($this->_code_exists($data['role_code'])) return false;

        $this->db->insert('auth_role', [
            'role_code'         => strtoupper(preg_replace('/\s+/', '_', trim($data['role_code']))),
            'role_name'         => $data['role_name'],
            'description'       => $data['description'] ?? null,
            'division_scope_id' => isset($data['division_scope_id']) ? ((int)$data['division_scope_id'] ?: null) : null,
            'is_active'         => 1,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------

    public function update(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        return $this->db->update('auth_role', [
            'role_name'         => $data['role_name'],
            'description'       => $data['description'] ?? null,
            'division_scope_id' => isset($data['division_scope_id']) ? ((int)$data['division_scope_id'] ?: null) : null,
            'is_active'         => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): bool
    {
        if ($this->db->trans_begin() === false) {
            return false;
        }

        $role_query = $this->db->query(
            'SELECT id, role_code FROM auth_role WHERE id = ? LIMIT 1 FOR UPDATE',
            [$id]
        );

        if ($role_query === false) {
            $this->db->trans_rollback();
            return false;
        }

        $role = $role_query->row_array();
        if (!$role || strtoupper(trim((string) $role['role_code'])) === 'SUPERADMIN') {
            $this->db->trans_rollback();
            return false;
        }

        $user_query = $this->db
            ->select('role_id')
            ->from('auth_user_role')
            ->where('role_id', $id)
            ->limit(1)
            ->get();

        if ($user_query === false || $user_query->num_rows() > 0) {
            $this->db->trans_rollback();
            return false;
        }

        $permission_delete = $this->db
            ->where('role_id', $id)
            ->delete('auth_role_permission');
        $permission_affected = $this->db->affected_rows();

        if ($permission_delete === false
            || $permission_affected < 0
            || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $role_delete = $this->db
            ->where('id', $id)
            ->delete('auth_role');
        $role_affected = $this->db->affected_rows();

        if ($role_delete === false
            || $role_affected !== 1
            || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $permission_assert_query = $this->db
            ->select('role_id')
            ->from('auth_role_permission')
            ->where('role_id', $id)
            ->limit(1)
            ->get();

        if ($permission_assert_query === false || $permission_assert_query->num_rows() > 0) {
            $this->db->trans_rollback();
            return false;
        }

        $role_assert_query = $this->db
            ->select('id')
            ->from('auth_role')
            ->where('id', $id)
            ->limit(1)
            ->get();

        if ($role_assert_query === false || $role_assert_query->num_rows() > 0) {
            $this->db->trans_rollback();
            return false;
        }

        if ($this->db->trans_commit() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $permission_postcondition_query = $this->db
            ->select('role_id')
            ->from('auth_role_permission')
            ->where('role_id', $id)
            ->limit(1)
            ->get();

        if ($permission_postcondition_query === false
            || $permission_postcondition_query->num_rows() > 0) {
            return false;
        }

        $role_postcondition_query = $this->db
            ->select('id')
            ->from('auth_role')
            ->where('id', $id)
            ->limit(1)
            ->get();

        if ($role_postcondition_query === false || $role_postcondition_query->num_rows() > 0) {
            return false;
        }

        return true;
    }

    // ---------------------------------------------------------------
    // MATRIX IZIN — SAVE
    // ---------------------------------------------------------------

    /**
     * Simpan matrix izin untuk role.
     * Menerima array: [page_id => ['can_view'=>1, 'can_create'=>0, ...], ...]
     *
     * Hapus dulu semua izin role ini, lalu insert ulang (lebih simpel).
     */
    public function save_permissions(int $role_id, array $matrix): void
    {
        $this->db->trans_start();

        // Hapus izin lama role ini
        $this->db->where('role_id', $role_id)->delete('auth_role_permission');

        $now = date('Y-m-d H:i:s');
        foreach ($matrix as $page_id => $flags) {
            $page_id = (int)$page_id;
            if ($page_id <= 0) continue;

            // Hanya insert jika minimal can_view = 1
            $can_view = (int)($flags['can_view'] ?? 0);
            if ($can_view === 0) continue;

            $this->db->insert('auth_role_permission', [
                'role_id'    => $role_id,
                'page_id'    => $page_id,
                'can_view'   => 1,
                'can_create' => (int)($flags['can_create'] ?? 0),
                'can_edit'   => (int)($flags['can_edit'] ?? 0),
                'can_delete' => (int)($flags['can_delete'] ?? 0),
                'can_export' => (int)($flags['can_export'] ?? 0),
                'created_at' => $now,
            ]);
        }

        $this->db->trans_complete();

        // Stamp waktu agar session user yang sudah login bisa dideteksi stale
        $this->db->where('id', $role_id)->update('auth_role', [
            'permissions_updated_at' => $now,
        ]);
    }

    // ---------------------------------------------------------------
    // VALIDATION
    // ---------------------------------------------------------------

    private function _code_exists(string $code, int $exclude_id = 0): bool
    {
        $this->db->where('role_code', strtoupper($code));
        if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('auth_role') > 0;
    }

    /**
     * Ambil semua divisi operasional untuk opsi division_scope pada form role.
     */
    public function get_division_options(): array
    {
        return $this->db->select('id, division_name')
            ->order_by('division_name')
            ->get('org_division')
            ->result_array();
    }

    // ---------------------------------------------------------------
    // USER ASSIGNMENT
    // ---------------------------------------------------------------

    /**
     * Ambil SEMUA user aktif beserta flag apakah memiliki role ini.
     * Disertai info karyawan, jabatan, divisi untuk tampilan.
     *
     * Return: [['id', 'username', 'email', 'employee_name', 'position_name',
     *           'division_name', 'division_id', 'last_login_at', 'has_role'], ...]
     */
    public function get_all_users_with_role_flag(int $role_id): array
    {
        $this->db->select('u.id, u.username, u.email, u.last_login_at,
            e.employee_name, p.position_name,
            div.division_name, div.id AS division_id,
            CASE WHEN ur.user_id IS NOT NULL THEN 1 ELSE 0 END AS has_role,
            ur.assigned_at', false);
        $this->db->from('auth_user u');
        $this->db->join('org_employee e',   'e.id = u.employee_id', 'left');
        $this->db->join('org_position p',   'p.id = e.position_id', 'left');
        $this->db->join('org_division div', 'div.id = e.division_id', 'left');
        $this->db->join('auth_user_role ur',
            'ur.user_id = u.id AND ur.role_id = ' . (int)$role_id, 'left');
        $this->db->where('u.is_active', 1);
        $this->db->order_by('div.division_name, e.employee_name, u.username');
        return $this->db->get()->result_array();
    }

    /**
     * Simpan daftar user yang memiliki role ini.
     * Hapus semua assignment lama, insert ulang yang baru.
     *
     * @param  int   $role_id
     * @param  array $user_ids   Array of user IDs yang di-assign
     * @param  int   $assigned_by User ID yang melakukan perubahan
     * @return int   Jumlah user yang di-assign
     */
    public function save_user_assignments(int $role_id, array $user_ids, int $assigned_by = 0): int
    {
        $new_ids = array_unique(array_filter(array_map('intval', $user_ids)));

        // Catat user lama sebelum dihapus agar bisa diikutkan stamp
        $old_ids = array_map('intval', array_column(
            $this->db->select('user_id')->from('auth_user_role')
                ->where('role_id', $role_id)->get()->result_array(),
            'user_id'
        ));

        $this->db->trans_start();

        // Hapus semua assignment lama untuk role ini
        $this->db->where('role_id', $role_id)->delete('auth_user_role');

        $now = date('Y-m-d H:i:s');
        foreach ($new_ids as $uid) {
            if ($uid <= 0) continue;
            $this->db->insert('auth_user_role', [
                'user_id'     => $uid,
                'role_id'     => $role_id,
                'assigned_by' => $assigned_by ?: null,
                'assigned_at' => $now,
            ]);
        }

        $this->db->trans_complete();

        // Stamp session-invalidation untuk semua user yang terdampak
        // (user lama yang dicopot maupun user baru yang ditambahkan)
        $affected = array_unique(array_merge($old_ids, $new_ids));
        $affected = array_values(array_filter($affected));
        if (!empty($affected)) {
            $this->db->where_in('id', $affected)
                ->update('auth_user', ['permissions_updated_at' => $now]);
        }

        return count($new_ids);
    }
}
