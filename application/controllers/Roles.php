<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Roles — Manajemen role dan matrix izin CRUD per halaman
 */
class Roles extends MY_Controller
{
    const PAGE_INDEX  = 'auth.roles.index';
    const PAGE_MANAGE = 'auth.roles.manage';
    const PAGE_MATRIX = 'auth.roles.matrix';
    const PAGE_USERS  = 'auth.roles.users';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Role_model');
        $this->load->library('form_validation');
    }

    // ---------------------------------------------------------------
    // INDEX
    // ---------------------------------------------------------------

    public function index()
    {
        $this->require_permission(self::PAGE_INDEX);

        $data = [
            'title'       => 'Manajemen Role',
            'active_menu' => 'sys.roles',
            'roles'       => $this->Role_model->get_all(),
            'registry_audit' => $this->Role_model->get_registry_audit_summary(),
        ];

        $this->render('roles/index', $data);
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    public function create()
    {
        $this->require_permission(self::PAGE_MANAGE, 'create');

        $data = [
            'title'             => 'Buat Role Baru',
            'active_menu'       => 'sys.roles',
            'form_action'       => 'roles/store',
            'edit_mode'         => false,
            'role'              => null,
            'division_options'  => $this->Role_model->get_division_options(),
        ];

        $this->render('roles/form', $data);
    }

    public function store()
    {
        $this->require_permission(self::PAGE_MANAGE, 'create');

        $this->form_validation->set_rules('role_code', 'Kode Role', 'required|trim|min_length[2]|max_length[50]|alpha_dash');
        $this->form_validation->set_rules('role_name', 'Nama Role', 'required|trim|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[255]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('roles/create');
        }

        $role_id = $this->Role_model->create([
            'role_code'         => $this->input->post('role_code', true),
            'role_name'         => $this->input->post('role_name', true),
            'description'       => $this->input->post('description', true),
            'division_scope_id' => (int)$this->input->post('division_scope_id') ?: null,
        ]);

        if ($role_id === false) {
            $this->session->set_flashdata('error', 'Kode role sudah dipakai.');
            redirect('roles/create');
        }

        $this->session->set_flashdata('success', 'Role berhasil dibuat. Sekarang atur matrix izinnya.');
        redirect('roles/matrix/' . $role_id);
    }

    // ---------------------------------------------------------------
    // EDIT / UPDATE
    // ---------------------------------------------------------------

    public function edit(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $data = [
            'title'             => 'Edit Role: ' . htmlspecialchars($role['role_name']),
            'active_menu'       => 'sys.roles',
            'form_action'       => 'roles/update/' . $id,
            'edit_mode'         => true,
            'role'              => $role,
            'division_options'  => $this->Role_model->get_division_options(),
        ];

        $this->render('roles/form', $data);
    }

    public function update(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $this->form_validation->set_rules('role_name',   'Nama Role',  'required|trim|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[255]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('roles/edit/' . $id);
        }

        $this->Role_model->update($id, [
            'role_name'         => $this->input->post('role_name', true),
            'description'       => $this->input->post('description', true),
            'is_active'         => $this->input->post('is_active') ? 1 : 0,
            'division_scope_id' => (int)$this->input->post('division_scope_id') ?: null,
        ]);

        $this->session->set_flashdata('success', 'Role berhasil diperbarui.');
        redirect('roles');
    }

    // ---------------------------------------------------------------
    // DELETE
    // ---------------------------------------------------------------

    public function delete(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'delete');

        // Cegah hapus role system default
        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();
        if ($role['role_code'] === 'SUPERADMIN') {
            $this->session->set_flashdata('error', 'Role SUPERADMIN tidak bisa dihapus.');
            redirect('roles');
        }

        $result = $this->Role_model->delete($id);
        if (!$result) {
            $this->session->set_flashdata('error', 'Role tidak bisa dihapus karena masih dipakai user.');
        } else {
            $this->session->set_flashdata('success', 'Role berhasil dihapus.');
        }
        redirect('roles');
    }

    // ---------------------------------------------------------------
    // MATRIX IZIN
    // ---------------------------------------------------------------

    public function matrix(int $id)
    {
        $this->require_permission(self::PAGE_MATRIX, 'view');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $data = [
            'title'            => 'Matrix Izin: ' . htmlspecialchars($role['role_name']),
            'active_menu'      => 'sys.roles',
            'role'             => $role,
            'pages_by_module'  => $this->Role_model->get_pages_with_permissions($id),
            'group_meta'       => $this->Role_model->get_matrix_group_registry(),
            'users_in_role'    => $this->Role_model->get_users_in_role($id),
        ];

        $this->render('roles/matrix', $data);
    }

    public function save_matrix(int $id)
    {
        $this->require_permission(self::PAGE_MATRIX, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        // Input format: perms[page_id][can_view] = 1, perms[page_id][can_create] = 1, dll
        $matrix_raw = $this->input->post('perms') ?: [];

        // Sanitasi
        $matrix = [];
        foreach ($matrix_raw as $page_id => $flags) {
            $page_id = (int)$page_id;
            if ($page_id <= 0) continue;
            $matrix[$page_id] = [
                'can_view'   => isset($flags['can_view'])   ? 1 : 0,
                'can_create' => isset($flags['can_create']) ? 1 : 0,
                'can_edit'   => isset($flags['can_edit'])   ? 1 : 0,
                'can_delete' => isset($flags['can_delete']) ? 1 : 0,
                'can_export' => isset($flags['can_export']) ? 1 : 0,
            ];
        }

        $this->Role_model->save_permissions($id, $matrix);

        $this->session->set_flashdata('success', 'Matrix izin berhasil disimpan.');
        redirect('roles/matrix/' . $id);
    }

    // ---------------------------------------------------------------
    // ASSIGN USER KE ROLE
    // ---------------------------------------------------------------

    /**
     * Halaman assign user: tampilkan semua user aktif dengan checkbox.
     */
    public function users(int $id)
    {
        $pageCode = $this->can(self::PAGE_USERS, 'view') ? self::PAGE_USERS : self::PAGE_INDEX;
        $this->require_permission($pageCode, 'view');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $all_users = $this->Role_model->get_all_users_with_role_flag($id);

        // Kelompokkan per divisi untuk tampilan
        $users_by_division = [];
        foreach ($all_users as $u) {
            $div = $u['division_name'] ?: '— Tanpa Divisi';
            $users_by_division[$div][] = $u;
        }
        ksort($users_by_division);

        $data = [
            'title'             => 'Assign User — ' . $role['role_name'],
            'active_menu'       => 'sys.roles',
            'role'              => $role,
            'all_users'         => $all_users,
            'users_by_division' => $users_by_division,
        ];

        $this->render('roles/users', $data);
    }

    /**
     * Simpan assignment user ke role (POST dari form roles/users).
     */
    public function save_users(int $id)
    {
        $pageCode = $this->can(self::PAGE_USERS, 'edit') ? self::PAGE_USERS : self::PAGE_MANAGE;
        $this->require_permission($pageCode, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $user_ids = $this->input->post('user_ids') ?: [];
        $count    = $this->Role_model->save_user_assignments(
            $id,
            $user_ids,
            (int)$this->current_user['id']
        );

        $this->session->set_flashdata(
            'success',
            "{$count} user di-assign ke role <b>" . htmlspecialchars($role['role_name']) . "</b>. "
            . "User yang terdampak perlu login ulang agar perubahan izin aktif."
        );
        redirect('roles/users/' . $id);
    }

    // ---------------------------------------------------------------
    // MATRIX GROUP LAYOUT
    // ---------------------------------------------------------------

    /**
     * Halaman konfigurasi pengelompokan sys_page di matrix role.
     * Terpisah dari per-role matrix editor — berlaku untuk semua role.
     */
    public function matrix_groups()
    {
        $this->require_permission(self::PAGE_MATRIX, 'view');

        $hasGroup = $this->db->field_exists('matrix_group', 'sys_page');
        $pages = $this->Role_model->get_pages_for_matrix_layout();
        $groupMeta = $this->Role_model->get_matrix_group_registry();

        // Group pages and collect unique group names
        $grouped   = [];
        $allGroups = [];
        foreach ($pages as $page) {
            $gk = (string)($page['resolved_group_code'] ?? $page['module'] ?? 'OTHER');
            $grouped[$gk][] = $page;
            $allGroups[$gk] = true;
            if (!empty($page['module'])) {
                $allGroups[(string)$page['module']] = true;
            }
        }
        foreach (array_keys($groupMeta) as $groupCode) {
            $allGroups[$groupCode] = true;
        }
        $allGroups = array_keys($allGroups);
        usort($allGroups, static function (string $a, string $b) use ($groupMeta): int {
            $sortA = (int)($groupMeta[$a]['sort_order'] ?? 9999);
            $sortB = (int)($groupMeta[$b]['sort_order'] ?? 9999);
            if ($sortA !== $sortB) {
                return $sortA <=> $sortB;
            }
            return strcmp($a, $b);
        });

        $data = [
            'title'         => 'Konfigurasi Grup Matrix',
            'active_menu'   => 'sys.roles',
            'pages_grouped' => $grouped,
            'all_groups'    => $allGroups,
            'has_group_col' => $hasGroup,
            'total_pages'   => count($pages),
            'group_meta'    => $groupMeta,
        ];

        $this->render('roles/matrix_layout', $data);
    }

    // ---------------------------------------------------------------
    // AUDIT AJAX ENDPOINTS
    // ---------------------------------------------------------------

    /**
     * AJAX — Daftarkan menu ke sys_page (auto-derive) lalu link sys_menu.page_id.
     * POST: menu_code, menu_label, url
     * Response: { ok: true, page_code, page_id }
     */
    public function quick_register_menu()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $this->require_permission(self::PAGE_INDEX, 'edit');

        $menuCode  = trim((string)$this->input->post('menu_code', true));
        $menuLabel = trim((string)$this->input->post('menu_label', true));
        $url       = trim((string)$this->input->post('url', true));

        if ($menuCode === '') {
            $this->json_error('menu_code tidak boleh kosong.', 422);
            return;
        }

        $this->load->model('Menu_model');

        // Derive page_code dari URL (replace / → . , strip leading slash)
        $pageCode = strtolower(trim($url, '/'));
        $pageCode = preg_replace('/[^a-z0-9]+/', '.', $pageCode);
        $pageCode = trim($pageCode, '.');
        if ($pageCode === '') {
            $pageCode = strtolower(preg_replace('/[^a-z0-9]+/', '.', trim($menuCode, '.')));
        }

        // Derive module dari segment pertama URL atau page_code
        $segments = explode('/', trim($url, '/'));
        $module   = strtoupper($segments[0] ?? 'SYS');

        // Check if page already exists
        $existingPage = $this->db->get_where('sys_page', ['page_code' => $pageCode])->row_array();
        if ($existingPage) {
            $pageId = (int)$existingPage['id'];
        } else {
            $this->Menu_model->register_page($pageCode, $menuLabel, $module, 'Auto-registered dari audit sidebar');
            $pageId = (int)$this->db->insert_id();
            if ($pageId <= 0) {
                $row = $this->db->get_where('sys_page', ['page_code' => $pageCode])->row_array();
                $pageId = (int)($row['id'] ?? 0);
            }
        }

        if ($pageId <= 0) {
            $this->json_error('Gagal mendaftarkan page.', 500);
            return;
        }

        // Link sys_menu.page_id
        $this->db->where('menu_code', $menuCode)
                 ->update('sys_menu', ['page_id' => $pageId]);

        $this->json_ok(['page_code' => $pageCode, 'page_id' => $pageId]);
    }

    /**
     * AJAX — Nonaktifkan menu (sys_menu.is_active = 0).
     * POST: menu_code
     */
    public function deactivate_menu_item()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $this->require_permission(self::PAGE_INDEX, 'edit');

        $menuCode = trim((string)$this->input->post('menu_code', true));
        if ($menuCode === '') {
            $this->json_error('menu_code tidak boleh kosong.', 422);
            return;
        }

        $this->db->where('menu_code', $menuCode)
                 ->update('sys_menu', ['is_active' => 0]);

        $this->json_ok(['menu_code' => $menuCode]);
    }

    /**
     * AJAX — Nonaktifkan page (sys_page.is_active = 0).
     * POST: page_code
     */
    public function deactivate_page_item()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $this->require_permission(self::PAGE_INDEX, 'edit');

        $pageCode = trim((string)$this->input->post('page_code', true));
        if ($pageCode === '') {
            $this->json_error('page_code tidak boleh kosong.', 422);
            return;
        }

        $this->db->where('page_code', $pageCode)
                 ->update('sys_page', ['is_active' => 0]);

        $this->json_ok(['page_code' => $pageCode]);
    }

    /**
     * AJAX — Simpan matrix_group untuk satu page.
     * POST: page_code, group_code
     */
    public function save_page_matrix_group()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $this->require_permission(self::PAGE_MATRIX, 'edit');

        $pageCode  = trim((string)$this->input->post('page_code', true));
        $groupCode = trim((string)$this->input->post('group_code', true));

        if ($pageCode === '') {
            $this->json_error('page_code tidak boleh kosong.', 422);
            return;
        }

        $ok = $this->Role_model->save_page_matrix_group($pageCode, $groupCode);
        if (!$ok) {
            $this->json_error('Gagal menyimpan group. Pastikan kolom matrix_group sudah ada (jalankan SQL migration).', 500);
            return;
        }

        $this->json_ok(['page_code' => $pageCode, 'group_code' => $groupCode]);
    }

    /**
     * AJAX — Toggle is_active untuk sys_page.
     * POST: page_code
     */
    public function toggle_page_active()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $this->require_permission(self::PAGE_INDEX, 'edit');

        $pageCode = trim((string)$this->input->post('page_code', true));
        if ($pageCode === '') {
            $this->json_error('page_code tidak boleh kosong.', 422);
            return;
        }

        $row = $this->db->select('id, is_active')
                        ->where('page_code', $pageCode)
                        ->get('sys_page')
                        ->row_array();

        if (!$row) {
            $this->json_error('Page tidak ditemukan.', 404);
            return;
        }

        $newState = $row['is_active'] ? 0 : 1;
        $this->db->where('page_code', $pageCode)
                 ->update('sys_page', ['is_active' => $newState]);

        $this->json_ok(['page_code' => $pageCode, 'is_active' => $newState]);
    }

    // ---------------------------------------------------------------
    // JSON helpers
    // ---------------------------------------------------------------

    private function json_ok(array $data = []): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $statusCode = 422): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
