<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only access inspector. Uses the live auth resolver without impersonation. */
class Access_audit_model extends CI_Model
{
    public const ACTIONS = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_export'];

    public function report(int $userId, ?array $roleIds = null): ?array
    {
        $user = $this->db->select('id, username, is_active')->from('auth_user')
            ->where('id', $userId)->get()->row_array();
        if (!$user) {
            return null;
        }
        $this->load->model(['Auth_model', 'Role_model', 'Menu_model']);
        $roles = $this->Role_model->get_all();
        $assignments = $this->db->select('role_id')->from('auth_user_role')
            ->where('user_id', $userId)->get()->result_array();
        $assignedIds = array_map('intval', array_column($assignments, 'role_id'));
        if ($roleIds !== null) {
            foreach ($roleIds as $id) {
                if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    throw new InvalidArgumentException('Pilihan role tidak valid.');
                }
            }
            $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
            if (array_diff($roleIds, array_map('intval', array_column($roles, 'id'))) !== []) {
                throw new InvalidArgumentException('Pilihan role sudah tidak tersedia. Muat ulang halaman.');
            }
        }
        $selectedIds = $roleIds ?? $assignedIds;
        $pages = $this->db->select('id, page_code, page_name, module, is_active')
            ->from('sys_page')->order_by('module')->order_by('page_name')->get()->result_array();
        $current = $this->Auth_model->load_permissions($userId);
        $preview = $roleIds === null ? $current : $this->Auth_model->preview_permissions($userId, $roleIds);
        $roleOnly = $this->Auth_model->preview_permissions($userId, $roleIds, false);
        $scope = $this->Auth_model->preview_division_scope($userId, $roleIds);
        $currentScope = $roleIds === null ? $scope : $this->Auth_model->resolve_division_scope($userId);
        $usable = self::usable($user, $preview, $scope);
        $currentUsable = self::usable($user, $current, $currentScope);
        $rows = [];
        $changes = [];
        $overrides = [];
        foreach ($pages as $page) {
            if ((int)$page['is_active'] !== 1) {
                continue;
            }
            $code = (string)$page['page_code'];
            $row = $page + ['flags' => [], 'current_flags' => [], 'role_flags' => []];
            foreach (self::ACTIONS as $action) {
                $before = self::allowed($current, $code, $action);
                $after = self::allowed($preview, $code, $action);
                $base = self::allowed($roleOnly, $code, $action);
                $row['flags'][$action] = $after;
                $row['current_flags'][$action] = $before;
                $row['role_flags'][$action] = $base;
                if (($before && $currentUsable) !== ($after && $usable)) {
                    $changes[] = $page + ['action' => $action, 'before' => $before && $currentUsable, 'after' => $after && $usable];
                }
                if ($base !== $after) {
                    $overrides[] = $page + ['action' => $action, 'before' => $base, 'after' => $after];
                }
            }
            $rows[] = $row;
        }
        $trees = [];
        if ($usable) {
            foreach (['MAIN', 'MY'] as $type) {
                $trees = array_merge($trees, $this->flatten_menu($this->Menu_model->get_sidebar_tree(
                    $preview, isset($preview['__superadmin__']), $type
                )));
            }
        }
        return [
            'user' => $user, 'roles' => $roles, 'assigned_ids' => $assignedIds,
            'selected_ids' => $selectedIds, 'is_preview' => $roleIds !== null,
            'is_superadmin' => isset($preview['__superadmin__']), 'scope' => $scope,
            'current_scope' => $currentScope, 'current_is_superadmin' => isset($current['__superadmin__']),
            'usable' => $usable,
            'rows' => $rows, 'changes' => $changes, 'overrides' => $overrides, 'menus' => $trees,
            'baseline' => $this->baseline_report($roles, $pages),
        ];
    }

    public static function allowed(array $permissions, string $code, string $action): bool
    {
        return isset($permissions['__superadmin__']) || !empty($permissions[$code][$action]);
    }

    public static function usable(array $user, array $permissions, array $scope): bool
    {
        return (int)($user['is_active'] ?? 0) === 1 && (
            isset($permissions['__superadmin__']) || ($scope['state'] ?? '') === 'GLOBAL'
            || (($scope['state'] ?? '') === 'SINGLE' && (int)($scope['division_id'] ?? 0) > 0)
        );
    }

    private function flatten_menu(array $tree, string $parent = ''): array
    {
        $result = [];
        foreach ($tree as $node) {
            $label = $parent . (string)$node['menu_label'];
            if (!empty($node['page_id'])) {
                $result[] = ['label' => $label, 'page_code' => (string)($node['page_code'] ?? ''), 'url' => (string)($node['url'] ?? '')];
            }
            $result = array_merge($result, $this->flatten_menu((array)($node['children'] ?? []), $label . ' / '));
        }
        return $result;
    }

    private function baseline_report(array $roles, array $pages): array
    {
        // Fixed source-controlled path only. Never accept a filename or baseline from HTTP.
        $path = APPPATH . 'config/rbac_permission_baseline.json';
        $baseline = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        $actual = $this->db->select('r.role_code, p.page_code, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export')
            ->from('auth_role_permission rp')->join('auth_role r', 'r.id = rp.role_id')
            ->join('sys_page p', 'p.id = rp.page_id')->get()->result_array();
        return self::compare_baseline($baseline, $roles, $pages, $actual);
    }

    /** Closed per-role matrix: omitted actions/pages are denied only for explicitly baselined roles. */
    public static function compare_baseline($baseline, array $roles, array $pages, array $actual): array
    {
        $result = ['status' => 'UNCONFIGURED', 'label' => '', 'rows' => [], 'unmanaged_roles' => array_column($roles, 'role_code')];
        if (!is_array($baseline) || ($baseline['approved'] ?? false) !== true) {
            return $result;
        }
        if (($baseline['schema_version'] ?? 0) !== 1 || empty($baseline['roles']) || !is_array($baseline['roles'])) {
            $result['status'] = 'INVALID';
            return $result;
        }
        $pageMap = array_column($pages, null, 'page_code');
        $roleMap = array_column($roles, null, 'role_code');
        $actualMap = [];
        foreach ($actual as $row) {
            $actualMap[$row['role_code']][$row['page_code']] = $row;
        }
        // Validate the whole baseline before emitting comparisons; invalid input must not look like zero drift.
        foreach ($baseline['roles'] as $code => $permissions) {
            if (!is_string($code) || !is_array($permissions)) {
                $result['status'] = 'INVALID';
                return $result;
            }
            foreach ($permissions as $pageCode => $flags) {
                if (!is_string($pageCode) || !is_array($flags) || array_diff(array_keys($flags), self::ACTIONS)) {
                    $result['status'] = 'INVALID';
                    return $result;
                }
                foreach ($flags as $value) {
                    if (!in_array($value, [0, 1, false, true], true)) {
                        $result['status'] = 'INVALID';
                        return $result;
                    }
                }
            }
        }
        $result['status'] = 'READY';
        $result['label'] = (string)($baseline['label'] ?? 'Baseline disetujui');
        $result['unmanaged_roles'] = array_values(array_diff(array_keys($roleMap), array_keys($baseline['roles'])));
        foreach ($baseline['roles'] as $roleCode => $permissions) {
            if (!isset($roleMap[$roleCode]) || (int)$roleMap[$roleCode]['is_active'] !== 1) {
                $result['rows'][] = ['role' => $roleCode, 'page' => '', 'action' => '', 'expected' => true, 'actual' => false, 'note' => 'Role tidak tersedia atau nonaktif'];
            }
            $codes = array_merge(array_keys($permissions), array_keys($actualMap[$roleCode] ?? []));
            if ($roleCode === 'SUPERADMIN') {
                $codes = array_merge($codes, array_keys($pageMap));
            }
            foreach (array_unique($codes) as $pageCode) {
                if (!isset($pageMap[$pageCode]) || (int)$pageMap[$pageCode]['is_active'] !== 1) {
                    $result['rows'][] = ['role' => $roleCode, 'page' => $pageCode, 'action' => '', 'expected' => true, 'actual' => false, 'note' => 'Halaman tidak tersedia atau nonaktif'];
                    continue;
                }
                foreach (self::ACTIONS as $action) {
                    $expected = !empty($permissions[$pageCode][$action]);
                    // SUPERADMIN intentionally bypasses stored flags, just as the live resolver does.
                    $value = isset($roleMap[$roleCode]) && (int)$roleMap[$roleCode]['is_active'] === 1
                        && ($roleCode === 'SUPERADMIN' || !empty($actualMap[$roleCode][$pageCode][$action]));
                    if ($expected !== $value) {
                        $result['rows'][] = ['role' => $roleCode, 'page' => $pageCode, 'action' => $action, 'expected' => $expected, 'actual' => $value, 'note' => $value ? 'Izin tambahan' : 'Izin belum tersedia'];
                    }
                }
            }
        }
        return $result;
    }
}
