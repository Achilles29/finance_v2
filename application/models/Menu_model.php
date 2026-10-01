<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menu_model — Sidebar menu dinamis, sys_page registry, dan favorit
 */
class Menu_model extends CI_Model
{
    // ---------------------------------------------------------------
    // SIDEBAR
    // ---------------------------------------------------------------

    /**
     * Ambil seluruh menu sidebar, sudah difilter sesuai izin user.
     * Return struktur tree: parent → children.
     *
     * @param array  $perms       Cache izin user dari session
     * @param bool   $is_superadmin
     * @param string $type        'MAIN' atau 'MY'
     */
    public function get_sidebar_tree(array $perms, bool $is_superadmin, string $type = 'MAIN'): array
    {
        $this->db->select('m.id, m.parent_id, m.menu_code, m.menu_label, m.icon, m.url, m.page_id, m.sort_order, m.is_active, p.page_code, p.is_active AS page_is_active');
        $this->db->from('sys_menu m');
        $this->db->join('sys_page p', 'p.id = m.page_id', 'left');
        $this->db->where('m.is_active', 1);
        $this->db->where('m.sidebar_type', $type);
        $this->db->order_by('m.sort_order', 'ASC');
        $rows = $this->db->get()->result_array();

        // Filter berdasarkan izin
        $allowed = [];
        foreach ($rows as $row) {
            $row = $this->normalize_sidebar_menu_row($row);
            if (!$this->is_menu_accessible_for_user($row, $perms, $is_superadmin)) {
                continue;
            }
            $row['is_favoritable'] = $this->is_favoritable_menu_for_user($row, $perms, $is_superadmin);
            $allowed[$row['id']] = $row;
        }

        return $this->_build_tree($allowed);
    }

    private function _build_tree(array $items, ?int $parent_id = null): array
    {
        $tree = [];
        foreach ($items as $item) {
            $item_parent = $item['parent_id'] ? (int)$item['parent_id'] : null;
            if ($item_parent === $parent_id) {
                $item['children'] = $this->_build_tree($items, (int)$item['id']);

                // A group row itself has no page permission. Keep it only when
                // at least one authorized descendant remains. This prevents a
                // role from seeing empty category shells after menu regrouping.
                $url = trim((string)($item['url'] ?? ''));
                $hasRealUrl = $url !== '' && $url !== '#'
                    && stripos($url, 'javascript:') !== 0;
                if (!$hasRealUrl && $item['children'] === []) {
                    continue;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    // ---------------------------------------------------------------
    // FAVORITES
    // ---------------------------------------------------------------

    public function is_menu_accessible_for_user(array $row, array $perms, bool $is_superadmin): bool
    {
        if (empty($row['is_active']) || (int)$row['is_active'] !== 1) {
            return false;
        }

        $url = trim((string)($row['url'] ?? ''));
        $hasRealUrl = $url !== '' && $url !== '#' && stripos($url, 'javascript:') !== 0;
        if (empty($row['page_id'])) {
            return !$hasRealUrl;
        }

        $pageCode = trim((string)($row['page_code'] ?? ''));
        if ($pageCode === '' || empty($row['page_is_active']) || (int)$row['page_is_active'] !== 1) {
            return false;
        }
        if ($is_superadmin) {
            return true;
        }

        return !empty($perms[$pageCode]['can_view']);
    }

    public function is_favoritable_menu_for_user(array $row, array $perms, bool $is_superadmin): bool
    {
        if (!$this->is_menu_accessible_for_user($row, $perms, $is_superadmin)) {
            return false;
        }

        $url = trim((string)($row['url'] ?? ''));
        return $url !== '' && $url !== '#' && stripos($url, 'javascript:') !== 0;
    }

    public function find_favoritable_menu_for_user(int $menu_id, array $perms, bool $is_superadmin): ?array
    {
        $row = $this->db
            ->select('m.id, m.menu_label, m.icon, m.url, m.page_id, m.is_active, p.page_code, p.is_active AS page_is_active')
            ->from('sys_menu m')
            ->join('sys_page p', 'p.id = m.page_id', 'left')
            ->where('m.id', $menu_id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            return null;
        }

        $row = $this->normalize_sidebar_menu_row($row);
        return $this->is_favoritable_menu_for_user($row, $perms, $is_superadmin) ? $row : null;
    }

    public function get_favorites(int $user_id, array $perms, bool $is_superadmin): array
    {
        $this->db->select('f.id, f.menu_id, f.sort_order, m.menu_label, m.icon, m.url, m.page_id, m.is_active, p.page_code, p.is_active AS page_is_active');
        $this->db->from('sys_sidebar_favorite f');
        $this->db->join('sys_menu m', 'm.id = f.menu_id');
        $this->db->join('sys_page p', 'p.id = m.page_id', 'left');
        $this->db->where('f.user_id', $user_id);
        $this->db->order_by('f.sort_order', 'ASC');
        $rows = $this->db->get()->result_array();
        $favorites = [];
        foreach ($rows as $row) {
            $row = $this->normalize_sidebar_menu_row($row);
            if ($this->is_favoritable_menu_for_user($row, $perms, $is_superadmin)) {
                $favorites[] = $row;
            }
        }
        return $favorites;
    }

    public function pin_favorite(int $user_id, int $menu_id): bool
    {
        if ($user_id <= 0 || $menu_id <= 0) {
            return false;
        }

        $sql = 'INSERT INTO sys_sidebar_favorite (user_id, menu_id, sort_order, created_at) '
            . 'SELECT ?, ?, COALESCE(MAX(sort_order), 0) + 1, ? '
            . 'FROM sys_sidebar_favorite WHERE user_id = ? '
            . 'ON DUPLICATE KEY UPDATE menu_id = VALUES(menu_id)';

        return (bool)$this->db->query($sql, [
            $user_id,
            $menu_id,
            date('Y-m-d H:i:s'),
            $user_id,
        ]);
    }

    public function unpin_favorite(int $user_id, int $menu_id): bool
    {
        return (bool)$this->db->where('user_id', $user_id)->where('menu_id', $menu_id)
            ->delete('sys_sidebar_favorite');
    }

    public function get_favorite_menu_ids_for_user(int $user_id, array $perms, bool $is_superadmin): array
    {
        return array_map(static function (array $row): int {
            return (int)($row['menu_id'] ?? 0);
        }, $this->get_favorites($user_id, $perms, $is_superadmin));
    }

    public function reorder_favorites(int $user_id, array $menu_ids): bool
    {
        $this->db->trans_start();
        $order = 1;
        foreach ($menu_ids as $menu_id) {
            $this->db->where('user_id', $user_id)->where('menu_id', (int)$menu_id)
                ->update('sys_sidebar_favorite', ['sort_order' => $order++]);
        }
        $this->db->trans_complete();
        return $this->db->trans_status() !== false;
    }

    public function get_favorite_summary(): array
    {
        $total_favorite_rows = (int)$this->db->count_all('sys_sidebar_favorite');

        $active_users = (int)$this->db->distinct()
            ->select('user_id')
            ->from('sys_sidebar_favorite')
            ->count_all_results();

        $top_menus = $this->db
            ->select('m.menu_label, COUNT(f.id) AS total_pin', false)
            ->from('sys_sidebar_favorite f')
            ->join('sys_menu m', 'm.id = f.menu_id')
            ->group_by('m.id, m.menu_label')
            ->order_by('total_pin', 'DESC')
            ->limit(5)
            ->get()
            ->result_array();

        $top_users = $this->db
            ->select('u.username, COUNT(f.id) AS total_pin', false)
            ->from('sys_sidebar_favorite f')
            ->join('auth_user u', 'u.id = f.user_id', 'left')
            ->group_by('f.user_id, u.username')
            ->order_by('total_pin', 'DESC')
            ->limit(5)
            ->get()
            ->result_array();

        return [
            'total_rows' => $total_favorite_rows,
            'active_users' => $active_users,
            'top_menus' => $top_menus,
            'top_users' => $top_users,
        ];
    }

    public function get_sidebar_tree_raw(string $type = 'MAIN'): array
    {
        $this->db->select('id, parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type');
        $this->db->from('sys_menu');
        $this->db->where('sidebar_type', $type);
        $this->db->order_by('is_active', 'DESC');
        $this->db->order_by('sort_order', 'ASC');
        $rows = $this->db->get()->result_array();

        $indexed = [];
        foreach ($rows as $row) {
            $row = $this->normalize_sidebar_menu_row($row);
            $row['children'] = [];
            $indexed[(int)$row['id']] = $row;
        }

        $tree = [];
        foreach ($indexed as $id => $item) {
            $parentId = !empty($item['parent_id']) ? (int)$item['parent_id'] : 0;
            if ($parentId > 0 && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$indexed[$id];
            } else {
                $tree[] = &$indexed[$id];
            }
        }

        return $tree;
    }

    public function get_sidebar_flat_raw(string $type = 'MAIN'): array
    {
        $this->db->select('id, parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type');
        $this->db->from('sys_menu');
        $this->db->where('sidebar_type', $type);
        $this->db->order_by('parent_id IS NULL', 'DESC', false);
        $this->db->order_by('parent_id', 'ASC');
        $this->db->order_by('sort_order', 'ASC');
        $rows = $this->db->get()->result_array();
        return array_map([$this, 'normalize_sidebar_menu_row'], $rows);
    }

    /**
     * Read-only health check for the canonical sys_page/sys_menu foundation.
     * Counts represent issue groups; details remain bounded for the admin UI.
     */
    public function validate_navigation_registry(int $detailLimit = 25): array
    {
        $detailLimit = max(1, min(100, $detailLimit));
        $realUrl = "TRIM(COALESCE(m.url, '')) <> ''"
            . " AND TRIM(COALESCE(m.url, '')) <> '#'"
            . " AND LOWER(TRIM(COALESCE(m.url, ''))) NOT IN ('javascript:void(0)', 'javascript:void(0);')";
        $normalizedUrl = "LOWER(TRIM(BOTH '/' FROM TRIM(COALESCE(m.url, ''))))";

        $queries = [
            'missing_page' => "SELECT m.id, m.menu_code, m.menu_label, m.url, m.page_id, p.page_code, p.is_active AS page_is_active
                FROM sys_menu m LEFT JOIN sys_page p ON p.id = m.page_id
                WHERE m.is_active = 1 AND {$realUrl} AND (m.page_id IS NULL OR p.id IS NULL OR p.is_active <> 1)
                ORDER BY m.id ASC",
            'missing_icon' => "SELECT m.id, m.menu_code, m.menu_label, m.parent_id, m.sort_order
                FROM sys_menu m
                WHERE m.is_active = 1 AND TRIM(COALESCE(m.icon, '')) = ''
                ORDER BY m.id ASC",
            'duplicate_url' => "SELECT {$normalizedUrl} AS canonical_url, COUNT(*) AS total_rows,
                    GROUP_CONCAT(m.id ORDER BY m.id) AS menu_ids,
                    GROUP_CONCAT(m.menu_code ORDER BY m.id SEPARATOR ', ') AS menu_codes
                FROM sys_menu m
                WHERE m.is_active = 1 AND {$realUrl}
                GROUP BY {$normalizedUrl} HAVING COUNT(*) > 1
                ORDER BY canonical_url ASC",
            'duplicate_code' => "SELECT LOWER(TRIM(m.menu_code)) AS canonical_code, COUNT(*) AS total_rows,
                    GROUP_CONCAT(m.id ORDER BY m.id) AS menu_ids
                FROM sys_menu m
                WHERE m.is_active = 1
                GROUP BY LOWER(TRIM(m.menu_code)) HAVING COUNT(*) > 1
                ORDER BY canonical_code ASC",
            'sort_collision' => "SELECT m.sidebar_type, COALESCE(m.parent_id, 0) AS parent_id, m.sort_order,
                    COUNT(*) AS total_rows, GROUP_CONCAT(m.id ORDER BY m.id) AS menu_ids,
                    GROUP_CONCAT(m.menu_code ORDER BY m.id SEPARATOR ', ') AS menu_codes
                FROM sys_menu m
                WHERE m.is_active = 1
                GROUP BY m.sidebar_type, COALESCE(m.parent_id, 0), m.sort_order HAVING COUNT(*) > 1
                ORDER BY m.sidebar_type, parent_id, m.sort_order",
            'invalid_favorite' => "SELECT f.id, f.user_id, f.menu_id, m.menu_code, m.url, m.is_active
                FROM sys_sidebar_favorite f LEFT JOIN sys_menu m ON m.id = f.menu_id
                LEFT JOIN sys_page p ON p.id = m.page_id
                WHERE m.id IS NULL OR m.is_active <> 1
                   OR TRIM(COALESCE(m.url, '')) IN ('', '#', 'javascript:void(0)', 'javascript:void(0);')
                   OR (m.page_id IS NOT NULL AND (p.id IS NULL OR p.is_active <> 1))
                ORDER BY f.id ASC",
            'invalid_group_url' => "SELECT DISTINCT m.id, m.menu_code, m.menu_label, m.url
                FROM sys_menu m INNER JOIN sys_menu child ON child.parent_id = m.id AND child.is_active = 1
                WHERE m.is_active = 1 AND {$realUrl}
                ORDER BY m.id ASC",
        ];

        $counts = [];
        $details = [];
        foreach ($queries as $issueCode => $sql) {
            $query = $this->db->query($sql);
            $rows = $query ? $query->result_array() : [];
            $counts[$issueCode] = count($rows);
            $details[$issueCode] = array_slice($rows, 0, $detailLimit);
        }

        return [
            'ok' => true,
            'total_issues' => array_sum($counts),
            'issue_counts' => $counts,
            'details' => $details,
            'detail_limit' => $detailLimit,
        ];
    }

    public function get_menu_by_id(int $id): ?array
    {
        $row = $this->db->get_where('sys_menu', ['id' => $id])->row_array() ?: null;
        return $row ? $this->normalize_sidebar_menu_row($row) : null;
    }

    public function get_parent_candidates(string $type, int $excludeId = 0): array
    {
        $this->db->select('id, menu_label, menu_code');
        $this->db->from('sys_menu');
        $this->db->where('sidebar_type', $type);
        $this->db->where('is_active', 1);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        $this->db->order_by('menu_label', 'ASC');
        return $this->db->get()->result_array();
    }

    public function create_sidebar_menu(array $data): int
    {
        $this->db->insert('sys_menu', $data);
        return (int)$this->db->insert_id();
    }

    public function update_sidebar_menu(int $id, array $data): bool
    {
        return $this->db->where('id', $id)->update('sys_menu', $data);
    }

    public function menu_code_exists(string $menuCode, int $excludeId = 0): bool
    {
        $this->db->from('sys_menu');
        $this->db->where('menu_code', $menuCode);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function save_sidebar_structure(string $type, array $tree): void
    {
        $order = 1;
        foreach ($tree as $node) {
            $this->persist_sidebar_node($type, $node, null, $order++);
        }
    }

    private function persist_sidebar_node(string $type, array $node, ?int $parentId, int $sortOrder): void
    {
        $menuId = (int)($node['id'] ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $this->db->where('id', $menuId)
            ->where('sidebar_type', $type)
            ->update('sys_menu', [
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
            ]);

        $childOrder = 1;
        $children = $node['children'] ?? [];
        if (!is_array($children)) {
            $children = [];
        }
        foreach ($children as $child) {
            $this->persist_sidebar_node($type, (array)$child, $menuId, $childOrder++);
        }
    }

    private function normalize_sidebar_menu_row(array $row): array
    {
        if (array_key_exists('url', $row) && $row['url'] !== null) {
            $row['url'] = trim((string)$row['url']);
        }

        return $row;
    }

    // ---------------------------------------------------------------
    // SYS_PAGE REGISTRY
    // ---------------------------------------------------------------

    public function get_all_pages(): array
    {
        $this->db->where('is_active', 1);
        $this->db->order_by('module, page_name');
        return $this->db->get('sys_page')->result_array();
    }

    /**
     * Daftarkan halaman baru ke sys_page jika belum ada.
     * Dipanggil dari controller saat pertama kali diakses superadmin.
     */
    public function register_page(string $page_code, string $page_name, string $module, string $description = ''): void
    {
        $exists = $this->db->get_where('sys_page', ['page_code' => $page_code])->num_rows();
        if (!$exists) {
            $this->db->insert('sys_page', [
                'page_code'   => $page_code,
                'page_name'   => $page_name,
                'module'      => $module,
                'description' => $description,
                'is_active'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
