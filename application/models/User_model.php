<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User_model — CRUD user, assignment role, dan override izin
 */
class User_model extends CI_Model
{
    // ---------------------------------------------------------------
    // READ
    // ---------------------------------------------------------------

    public function get_all(array $filter = []): array
    {
        $this->db->select('u.id, u.username, u.email, u.is_active, u.last_login_at, u.created_at, u.employee_id,
            e.employee_name, d.division_name, d.sort_order AS division_sort_order,
            p.position_name, p.sort_order AS position_sort_order', false);
        $this->db->from('auth_user u');
        $this->db->join('org_employee e', 'e.id = u.employee_id', 'left');
        $this->db->join('org_division d', 'd.id = e.division_id', 'left');
        $this->db->join('org_position p', 'p.id = e.position_id', 'left');

        if (!empty($filter['search'])) {
            $s = $this->db->escape_like_str($filter['search']);
            $this->db->group_start();
            $this->db->like('u.username', $s, 'both');
            $this->db->or_like('u.email', $s, 'both');
            $this->db->or_like('e.employee_name', $s, 'both');
            $this->db->or_like('d.division_name', $s, 'both');
            $this->db->or_like('p.position_name', $s, 'both');
            $this->db->group_end();
        }

        if (isset($filter['is_active'])) {
            $this->db->where('u.is_active', (int)$filter['is_active']);
        }

        $this->db->order_by('CASE WHEN u.employee_id IS NULL OR u.employee_id = 0 THEN 1 ELSE 0 END', '', false);
        $this->db->order_by('COALESCE(d.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('d.division_name', 'ASC');
        $this->db->order_by('COALESCE(p.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('p.position_name', 'ASC');
        $this->db->order_by('e.employee_name', 'ASC');
        $this->db->order_by('u.username', 'ASC');
        return $this->db->get()->result_array();
    }

    public function get_by_id(int $id): ?array
    {
        $row = $this->db->get_where('auth_user', ['id' => $id])->row_array();
        if ($row) unset($row['password_hash']);
        return $row ?: null;
    }

    public function get_detail_by_id(int $id): ?array
    {
        $row = $this->db->select('u.id, u.username, u.email, u.is_active, u.last_login_at, u.created_at, u.updated_at, u.employee_id,
                e.employee_code, e.employee_name, e.employee_nip,
                d.id AS division_id, d.division_name,
                p.id AS position_id, p.position_name', false)
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('u.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        if ($row) {
            unset($row['password_hash']);
        }

        return $row ?: null;
    }

    public function get_primary_user_by_employee_id(int $employeeId): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $row = $this->db->select('id, username, email, employee_id, is_active, created_at, updated_at')
            ->from('auth_user')
            ->where('employee_id', $employeeId)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function get_employee_options(?int $excludeUserId = null): array
    {
        $this->db->select(
            "e.id,
             e.employee_code,
             e.employee_name,
             d.division_name,
             p.position_name,
             u.id AS linked_user_id,
             u.username AS linked_username",
            false
        );
        $this->db->from('org_employee e');
        $this->db->join('org_division d', 'd.id = e.division_id', 'left');
        $this->db->join('org_position p', 'p.id = e.position_id', 'left');
        $this->db->join('auth_user u', 'u.employee_id = e.id', 'left');
        $this->db->where('e.is_active', 1);
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $this->db->group_start();
            $this->db->where('u.id IS NULL', null, false);
            $this->db->or_where('u.id', $excludeUserId);
            $this->db->group_end();
        }
        $this->db->order_by('d.sort_order', 'ASC');
        $this->db->order_by('d.division_name', 'ASC');
        $this->db->order_by('p.sort_order', 'ASC');
        $this->db->order_by('p.position_name', 'ASC');
        $this->db->order_by('e.employee_name', 'ASC');
        return $this->db->get()->result_array();
    }

    public function employee_exists(int $employeeId): bool
    {
        if ($employeeId <= 0) {
            return false;
        }
        return $this->db->where('id', $employeeId)
            ->where('is_active', 1)
            ->count_all_results('org_employee') > 0;
    }

    public function is_employee_linked_to_other_user(int $employeeId, int $excludeUserId = 0): bool
    {
        if ($employeeId <= 0) {
            return false;
        }

        $this->db->from('auth_user');
        $this->db->where('employee_id', $employeeId);
        if ($excludeUserId > 0) {
            $this->db->where('id !=', $excludeUserId);
        }
        return $this->db->count_all_results() > 0;
    }

    /**
     * Ambil daftar role yang dimiliki user
     */
    public function get_user_roles(int $user_id): array
    {
        $this->db->select('r.id, r.role_code, r.role_name');
        $this->db->from('auth_user_role ur');
        $this->db->join('auth_role r', 'r.id = ur.role_id');
        $this->db->where('ur.user_id', $user_id);
        $this->db->order_by('r.role_name');
        return $this->db->get()->result_array();
    }

    /**
     * Ambil semua override izin user ini
     */
    public function get_user_overrides(int $user_id): array
    {
        $this->db->select('o.*, p.page_code, p.page_name, p.module');
        $this->db->from('auth_user_permission_override o');
        $this->db->join('sys_page p', 'p.id = o.page_id');
        $this->db->where('o.user_id', $user_id);
        $this->db->order_by('p.module, p.page_name');
        return $this->db->get()->result_array();
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    /**
     * Buat user baru. Return insert_id atau false jika username/email sudah ada.
     */
    public function create(array $data): int|false
    {
        if ($this->_username_exists($data['username'])) return false;
        if (!empty($data['email']) && $this->_email_exists($data['email'])) return false;

        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $this->db->insert('auth_user', [
            'username'      => $data['username'],
            'email'         => $data['email'] ?? null,
            'password_hash' => $hash,
            'employee_id'   => $data['employee_id'] ?? null,
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------

    public function update(int $id, array $data): bool
    {
        $update = [
            'email'      => $data['email'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Update password hanya jika diisi
        if (!empty($data['password'])) {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (isset($data['employee_id'])) {
            $update['employee_id'] = $data['employee_id'] ?: null;
        }

        $this->db->where('id', $id);
        return $this->db->update('auth_user', $update);
    }

    public function toggle_active(int $id): bool
    {
        $this->db->where('id', $id);
        $this->db->set('is_active', 'IF(is_active=1, 0, 1)', false);
        $this->db->set('updated_at', date('Y-m-d H:i:s'));
        return $this->db->update('auth_user');
    }

    // ---------------------------------------------------------------
    // ROLE ASSIGNMENT
    // ---------------------------------------------------------------

    /**
     * Sync role user: hapus yang lama, insert yang baru (dalam 1 transaksi).
     *
     * @param int   $user_id
     * @param array $role_ids   Array of role ID integers
     * @param int   $assigned_by
     */
    public function sync_roles(int $user_id, array $role_ids, int $assigned_by): void
    {
        $this->db->trans_start();

        $this->replace_user_roles($user_id, $role_ids, $assigned_by);

        $this->db->trans_complete();

        // Stamp agar MY_Controller tahu session user ini perlu direfresh
        $this->db->where('id', $user_id)
            ->update('auth_user', ['permissions_updated_at' => date('Y-m-d H:i:s')]);
    }

    public function get_role_selection_options(bool $active_only = true): array
    {
        $this->db->select('id, role_code, role_name, description, is_active');
        $this->db->from('auth_role');
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('role_name', 'ASC');
        return $this->db->get()->result_array();
    }

    public function get_protected_role_ids(): array
    {
        $rows = $this->db->select('id')
            ->from('auth_role')
            ->where_in('role_code', $this->protected_role_codes())
            ->get()
            ->result_array();

        return array_map('intval', array_column($rows, 'id'));
    }

    public function normalize_role_ids(array $role_ids, bool $allow_protected = true): array
    {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $role_ids))));
        if (empty($normalized)) {
            return [];
        }

        $this->db->select('id');
        $this->db->from('auth_role');
        $this->db->where_in('id', $normalized);
        $this->db->where('is_active', 1);
        if (!$allow_protected) {
            $this->db->where_not_in('role_code', $this->protected_role_codes());
        }
        $rows = $this->db->get()->result_array();

        return array_map('intval', array_column($rows, 'id'));
    }

    public function preserve_protected_role_ids(array $submitted_role_ids, array $existing_role_ids, bool $allow_protected): array
    {
        $submitted_role_ids = $this->normalize_role_ids($submitted_role_ids, $allow_protected);
        if ($allow_protected) {
            return $submitted_role_ids;
        }

        $protected_role_ids = $this->get_protected_role_ids();
        if (empty($protected_role_ids)) {
            return $submitted_role_ids;
        }

        $existing_role_ids = $this->normalize_role_ids($existing_role_ids, true);
        $preserved_role_ids = array_values(array_intersect($existing_role_ids, $protected_role_ids));

        return array_values(array_unique(array_merge($submitted_role_ids, $preserved_role_ids)));
    }

    public function get_employee_role_ids(int $employee_id): array
    {
        if ($employee_id <= 0 || !$this->db->table_exists('org_employee_role_assignment')) {
            return [];
        }

        $rows = $this->db->select('role_id')
            ->from('org_employee_role_assignment')
            ->where('employee_id', $employee_id)
            ->order_by('role_id', 'ASC')
            ->get()
            ->result_array();

        return array_map('intval', array_column($rows, 'role_id'));
    }

    public function get_linked_user_ids_by_employee(int $employee_id): array
    {
        if ($employee_id <= 0) {
            return [];
        }

        $rows = $this->db->select('id')
            ->from('auth_user')
            ->where('employee_id', $employee_id)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        return array_map('intval', array_column($rows, 'id'));
    }

    public function get_effective_role_ids_for_employee(int $employee_id): array
    {
        $role_ids = $this->get_employee_role_ids($employee_id);
        $default_role_id = $this->get_position_default_role_id($employee_id);
        if ($default_role_id > 0) {
            $role_ids[] = $default_role_id;
        }

        return $this->normalize_role_ids($role_ids, true);
    }

    public function sync_employee_roles(int $employee_id, array $role_ids, int $assigned_by): array
    {
        if ($employee_id <= 0 || !$this->db->table_exists('org_employee_role_assignment')) {
            return [];
        }

        $role_ids = $this->normalize_role_ids($role_ids, true);

        $this->db->trans_start();

        $this->db->where('employee_id', $employee_id)->delete('org_employee_role_assignment');

        $now = date('Y-m-d H:i:s');
        foreach ($role_ids as $role_id) {
            $this->db->insert('org_employee_role_assignment', [
                'employee_id' => $employee_id,
                'role_id' => (int)$role_id,
                'assigned_by' => $assigned_by > 0 ? $assigned_by : null,
                'assigned_at' => $now,
            ]);
        }

        $effective_role_ids = $this->get_effective_role_ids_for_employee($employee_id);
        $linked_user_ids = $this->get_linked_user_ids_by_employee($employee_id);
        foreach ($linked_user_ids as $user_id) {
            $this->replace_user_roles($user_id, $effective_role_ids, $assigned_by);
        }

        $this->db->trans_complete();

        return $linked_user_ids;
    }

    // ---------------------------------------------------------------
    // PERMISSION OVERRIDES
    // ---------------------------------------------------------------

    /**
     * Simpan override izin untuk user.
     * Upsert — insert atau update jika sudah ada.
     */
    public function save_override(int $user_id, int $page_id, array $data, int $by): void
    {
        $payload = [
            'user_id'       => $user_id,
            'page_id'       => $page_id,
            'override_type' => $data['override_type'],
            'can_view'      => (int)($data['can_view'] ?? 0),
            'can_create'    => (int)($data['can_create'] ?? 0),
            'can_edit'      => (int)($data['can_edit'] ?? 0),
            'can_delete'    => (int)($data['can_delete'] ?? 0),
            'can_export'    => (int)($data['can_export'] ?? 0),
            'reason'        => $data['reason'] ?? null,
            'overridden_by' => $by,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $exists = $this->db->get_where('auth_user_permission_override', [
            'user_id' => $user_id, 'page_id' => $page_id
        ])->row();

        if ($exists) {
            $this->db->where('user_id', $user_id)->where('page_id', $page_id)
                ->update('auth_user_permission_override', $payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('auth_user_permission_override', $payload);
        }
    }

    public function delete_override(int $user_id, int $page_id): void
    {
        $this->db->where('user_id', $user_id)->where('page_id', $page_id)
            ->delete('auth_user_permission_override');
    }

    // ---------------------------------------------------------------
    // VALIDATION HELPERS
    // ---------------------------------------------------------------

    private function _username_exists(string $username, int $exclude_id = 0): bool
    {
        $this->db->where('username', $username);
        if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('auth_user') > 0;
    }

    private function _email_exists(string $email, int $exclude_id = 0): bool
    {
        $this->db->where('email', $email);
        if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('auth_user') > 0;
    }

    public function is_username_taken(string $username, int $exclude_id = 0): bool
    {
        return $this->_username_exists($username, $exclude_id);
    }

    public function is_email_taken(string $email, int $exclude_id = 0): bool
    {
        return $this->_email_exists($email, $exclude_id);
    }

    private function replace_user_roles(int $user_id, array $role_ids, int $assigned_by): void
    {
        $this->db->where('user_id', $user_id)->delete('auth_user_role');

        $now = date('Y-m-d H:i:s');
        foreach ($this->normalize_role_ids($role_ids, true) as $role_id) {
            $this->db->insert('auth_user_role', [
                'user_id' => $user_id,
                'role_id' => (int)$role_id,
                'assigned_by' => $assigned_by > 0 ? $assigned_by : null,
                'assigned_at' => $now,
            ]);
        }
    }

    private function get_position_default_role_id(int $employee_id): int
    {
        if ($employee_id <= 0) {
            return 0;
        }

        $row = $this->db->select('p.default_role_id')
            ->from('org_employee e')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employee_id)
            ->limit(1)
            ->get()
            ->row_array();

        return (int)($row['default_role_id'] ?? 0);
    }

    private function protected_role_codes(): array
    {
        return ['ADMIN', 'SUPERADMIN'];
    }
}
