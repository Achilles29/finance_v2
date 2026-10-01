<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sidebar — AJAX endpoint untuk pin/unpin favorit
 */
class Sidebar extends MY_Controller
{
    private const FAVORITE_CSRF_SESSION_KEY = 'sidebar_favorite_csrf';
    private const FAVORITE_CSRF_CI_HEADER = 'X-Sidebar-Favorite-Csrf';
    private const STRUCTURE_CSRF_SESSION_KEY = 'sidebar_structure_csrf';
    private const STRUCTURE_CSRF_FORM_FIELD = 'sidebar_structure_csrf';
    private const STRUCTURE_CSRF_CI_HEADER = 'X-Sidebar-Structure-Csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
    }

    public function pin()
    {
        if (!$this->require_favorite_mutation_request()) {
            return;
        }

        $menuId = (int)$this->input->post('menu_id');
        $menu = $menuId > 0
            ? $this->Menu_model->find_favoritable_menu_for_user($menuId, $this->user_perms, $this->is_superadmin())
            : null;
        if (!$menu) {
            $this->favorite_json_error(404, 'Menu favorit tidak ditemukan.');
            return;
        }

        $userId = (int)($this->current_user['id'] ?? 0);
        if (!$this->Menu_model->pin_favorite($userId, $menuId)) {
            $this->favorite_json_error(500, 'Gagal menyimpan favorit.');
            return;
        }
        $this->clear_sidebar_favorite_cache_files($userId);
        $this->favorite_json_ok();
    }

    public function unpin()
    {
        if (!$this->require_favorite_mutation_request()) {
            return;
        }

        $menuId = (int)$this->input->post('menu_id');
        if ($menuId <= 0) {
            $this->favorite_json_error(400, 'Payload favorit tidak valid.');
            return;
        }

        $userId = (int)($this->current_user['id'] ?? 0);
        if (!$this->Menu_model->unpin_favorite($userId, $menuId)) {
            $this->favorite_json_error(500, 'Gagal menghapus favorit.');
            return;
        }
        $this->clear_sidebar_favorite_cache_files($userId);
        $this->favorite_json_ok();
    }

    public function reorder()
    {
        if (!$this->require_favorite_mutation_request()) {
            return;
        }

        $ids = $this->input->post('ids');
        if (!is_array($ids) || $ids === []) {
            $this->favorite_json_error(400, 'Urutan favorit tidak valid.');
            return;
        }

        $normalized = [];
        foreach ($ids as $id) {
            $menuId = (int)$id;
            if ($menuId <= 0 || isset($normalized[$menuId])) {
                $this->favorite_json_error(400, 'Urutan favorit tidak valid.');
                return;
            }
            $normalized[$menuId] = $menuId;
        }
        $normalized = array_values($normalized);

        $userId = (int)($this->current_user['id'] ?? 0);
        $owned = array_fill_keys($this->Menu_model->get_favorite_menu_ids_for_user(
            $userId,
            $this->user_perms,
            $this->is_superadmin()
        ), true);
        $normalizedSet = array_fill_keys($normalized, true);
        if (count($normalizedSet) !== count($owned)) {
            $this->favorite_json_error(400, 'Urutan favorit tidak valid.');
            return;
        }
        foreach ($normalized as $menuId) {
            if (empty($owned[$menuId])) {
                $this->favorite_json_error(400, 'Urutan favorit tidak valid.');
                return;
            }
        }
        foreach ($owned as $menuId => $_owned) {
            if (empty($normalizedSet[$menuId])) {
                $this->favorite_json_error(400, 'Urutan favorit tidak valid.');
                return;
            }
        }

        if (!$this->Menu_model->reorder_favorites($userId, $normalized)) {
            $this->favorite_json_error(500, 'Gagal menyimpan urutan favorit.');
            return;
        }
        $this->clear_sidebar_favorite_cache_files($userId);
        $this->favorite_json_ok();
    }

    private function require_favorite_mutation_request(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            $this->favorite_json_error(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $provided = trim((string)$this->input->get_request_header(self::FAVORITE_CSRF_CI_HEADER, true));
        $expected = (string)$this->session->userdata(self::FAVORITE_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
            || !hash_equals($expected, $provided)
        ) {
            $this->favorite_json_error(403, 'Permintaan favorit tidak valid.');
            return false;
        }
        return true;
    }

    private function favorite_json_ok(): void
    {
        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true]));
    }

    private function favorite_json_error(int $status, string $message): void
    {
        $this->output->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function clear_sidebar_favorite_cache_files(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        $cacheDir = APPPATH . 'cache/sidebar';
        foreach (glob($cacheDir . DIRECTORY_SEPARATOR . 'sidebar_' . $userId . '_*.json') ?: [] as $cacheFile) {
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }

    public function manage()
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengatur struktur sidebar.', 403, 'Akses Ditolak');
            return;
        }
        $structureCsrfToken = $this->sidebar_structure_csrf();

        $type = strtoupper((string)$this->input->get('type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = 'MAIN';
        }

        $editId = (int)$this->input->get('edit_id', true);
        $editMenu = null;
        if ($editId > 0) {
            $candidate = $this->Menu_model->get_menu_by_id($editId);
            if ($candidate && ($candidate['sidebar_type'] ?? '') === $type) {
                $editMenu = $candidate;
            }
        }

        $data = [
            'title' => 'Manajemen Sidebar (Drag & Drop)',
            'active_menu' => 'grp.system',
            'sidebar_type_selected' => $type,
            'sidebar_tree_raw' => $this->Menu_model->get_sidebar_tree_raw($type),
            'sidebar_tree_preview' => $this->build_sidebar_preview_tree($type),
            'sidebar_flat_raw' => $this->Menu_model->get_sidebar_flat_raw($type),
            'parent_candidates' => $this->Menu_model->get_parent_candidates($type, $editId),
            'edit_menu' => $editMenu,
            'favorite_summary' => $this->Menu_model->get_favorite_summary(),
            'page_registry' => $this->Menu_model->get_all_pages(),
            'registry_validation' => $this->Menu_model->validate_navigation_registry(),
            'sidebar_structure_csrf_token' => $structureCsrfToken,
        ];

        $this->render('sidebar/manage', $data);
    }

    public function save_structure()
    {
        if (!$this->is_superadmin()) {
            $this->output->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Forbidden']));
            return;
        }

        if (!$this->require_sidebar_structure_mutation_request()) {
            return;
        }

        $type = strtoupper((string)$this->input->post('sidebar_type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = 'MAIN';
        }

        $treeJson = (string)$this->input->post('tree_json', false);
        $tree = json_decode($treeJson, true);
        if (!is_array($tree)) {
            $this->output->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Payload tidak valid']));
            return;
        }

        $this->db->trans_start();
        $this->Menu_model->save_sidebar_structure($type, $tree);
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->output->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Gagal menyimpan struktur sidebar']));
            return;
        }

        $this->clear_sidebar_cache_files();

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true]));
    }

    private function sidebar_structure_csrf(): string
    {
        $token = (string)$this->session->userdata(self::STRUCTURE_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::STRUCTURE_CSRF_SESSION_KEY, $token);
        }
        return $token;
    }

    private function require_sidebar_structure_mutation_request(bool $requireAjax = true, bool $allowFormField = false): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            if ($allowFormField) {
                show_error('Metode request tidak diizinkan.', 405, 'Method Not Allowed');
            } else {
                $this->structure_json_error(405, 'Metode request tidak diizinkan.');
            }
            return false;
        }
        if ($requireAjax && !$this->input->is_ajax_request()) {
            $this->structure_json_error(404, 'Permintaan struktur sidebar tidak ditemukan.');
            return false;
        }

        $provided = $allowFormField
            ? trim((string)$this->input->post(self::STRUCTURE_CSRF_FORM_FIELD, false))
            : trim((string)$this->input->get_request_header(self::STRUCTURE_CSRF_CI_HEADER, true));
        $expected = (string)$this->session->userdata(self::STRUCTURE_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
            || !hash_equals($expected, $provided)
        ) {
            if ($allowFormField) {
                show_error('Permintaan struktur sidebar tidak valid.', 403, 'Akses Ditolak');
            } else {
                $this->structure_json_error(403, 'Permintaan struktur sidebar tidak valid.');
            }
            return false;
        }
        return true;
    }

    private function structure_json_error(int $status, string $message): void
    {
        $this->output->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function clear_sidebar_cache_files(): void
    {
        $cacheDir = APPPATH . 'cache/sidebar';
        if (!is_dir($cacheDir)) {
            return;
        }

        $pattern = $cacheDir . DIRECTORY_SEPARATOR . 'sidebar_*.json';
        $cacheFiles = glob($pattern);
        if (!is_array($cacheFiles)) {
            return;
        }

        foreach ($cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }

    private function build_sidebar_preview_tree(string $type): array
    {
        return $this->Menu_model->get_sidebar_tree_raw($type);
    }
    public function menu_store()
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengelola sidebar.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_sidebar_structure_mutation_request(false, true)) {
            return;
        }

        $type = strtoupper((string)$this->input->post('sidebar_type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = 'MAIN';
        }

        $menuCode = trim((string)$this->input->post('menu_code', true));
        $menuLabel = trim((string)$this->input->post('menu_label', true));
        $icon = trim((string)$this->input->post('icon', true));
        $url = trim((string)$this->input->post('url', true));
        $parentId = (int)$this->input->post('parent_id', true);
        $sortOrder = (int)$this->input->post('sort_order', true);

        if ($menuCode === '' || $menuLabel === '') {
            $this->session->set_flashdata('error', 'Kode menu dan nama menu wajib diisi.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
            return;
        }

        if ($this->Menu_model->menu_code_exists($menuCode)) {
            $this->session->set_flashdata('error', 'Kode menu sudah digunakan.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
            return;
        }

        $pageId = (int)$this->input->post('page_id', true) ?: null;

        $this->Menu_model->create_sidebar_menu([
            'parent_id' => $parentId > 0 ? $parentId : null,
            'menu_code' => $menuCode,
            'menu_label' => $menuLabel,
            'icon' => $icon !== '' ? $icon : 'ri-circle-line',
            'url' => $url !== '' ? $url : null,
            'page_id' => $pageId,
            'sort_order' => $sortOrder > 0 ? $sortOrder : 999,
            'is_active' => 1,
            'sidebar_type' => $type,
        ]);
        $this->clear_sidebar_cache_files();

        $this->session->set_flashdata('success', 'Menu sidebar berhasil ditambahkan.');
        redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
    }

    public function menu_update(int $id)
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengelola sidebar.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_sidebar_structure_mutation_request(false, true)) {
            return;
        }

        $row = $this->Menu_model->get_menu_by_id($id);
        if (!$row) {
            show_404();
            return;
        }

        $type = strtoupper((string)$this->input->post('sidebar_type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = (string)$row['sidebar_type'];
        }

        $menuCode = trim((string)$this->input->post('menu_code', true));
        $menuLabel = trim((string)$this->input->post('menu_label', true));
        $icon = trim((string)$this->input->post('icon', true));
        $url = trim((string)$this->input->post('url', true));
        $parentId = (int)$this->input->post('parent_id', true);
        $sortOrder = (int)$this->input->post('sort_order', true);
        $isActive = $this->input->post('is_active') ? 1 : 0;

        if ($menuCode === '' || $menuLabel === '') {
            $this->session->set_flashdata('error', 'Kode menu dan nama menu wajib diisi.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data&edit_id=' . $id);
            return;
        }

        if ($parentId === $id) {
            $parentId = 0;
        }

        if ($this->Menu_model->menu_code_exists($menuCode, $id)) {
            $this->session->set_flashdata('error', 'Kode menu sudah digunakan menu lain.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data&edit_id=' . $id);
            return;
        }

        $pageId = (int)$this->input->post('page_id', true) ?: null;

        $this->Menu_model->update_sidebar_menu($id, [
            'parent_id' => $parentId > 0 ? $parentId : null,
            'menu_code' => $menuCode,
            'menu_label' => $menuLabel,
            'icon' => $icon !== '' ? $icon : 'ri-circle-line',
            'url' => $url !== '' ? $url : null,
            'page_id' => $pageId,
            'sort_order' => $sortOrder > 0 ? $sortOrder : 999,
            'is_active' => $isActive,
            'sidebar_type' => $type,
        ]);
        $this->clear_sidebar_cache_files();

        $this->session->set_flashdata('success', 'Menu sidebar berhasil diperbarui.');
        redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
    }

    public function menu_delete(int $id)
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengelola sidebar.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_sidebar_structure_mutation_request(false, true)) {
            return;
        }

        $row = $this->Menu_model->get_menu_by_id($id);
        if (!$row) {
            show_404();
            return;
        }

        $type = (string)$row['sidebar_type'];

        $hasChild = $this->db->from('sys_menu')->where('parent_id', $id)->count_all_results() > 0;
        if ($hasChild) {
            $this->session->set_flashdata('error', 'Menu tidak bisa dihapus karena masih memiliki submenu.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
            return;
        }

        // Soft delete agar histori favorit dan relasi tetap aman.
        $this->Menu_model->update_sidebar_menu($id, ['is_active' => 0]);
        $this->clear_sidebar_cache_files();

        $this->session->set_flashdata('success', 'Menu sidebar dinonaktifkan (soft delete).');
        redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
    }

    /**
     * AJAX — Toggle is_active untuk menu sidebar.
     * POST: (none required, aksi ditentukan oleh state saat ini)
     * Response: { ok: true, is_active: 0|1 }
     */
    public function menu_toggle_active(int $id)
    {
        if (!$this->is_superadmin()) {
            $this->json_error('Hanya superadmin yang dapat mengelola sidebar.', 403);
            return;
        }
        if (!$this->require_sidebar_structure_mutation_request(false)) {
            return;
        }
        if (!$this->input->is_ajax_request()) {
            $this->json_error('Permintaan menu sidebar tidak ditemukan.', 404);
            return;
        }

        $row = $this->Menu_model->get_menu_by_id($id);
        if (!$row) {
            $this->json_error('Menu tidak ditemukan.', 404);
            return;
        }

        // Cegah toggle jika menu masih punya anak aktif
        if ((int)$row['is_active'] === 1) {
            $hasActiveChild = $this->db->from('sys_menu')
                ->where('parent_id', $id)
                ->where('is_active', 1)
                ->count_all_results() > 0;
            if ($hasActiveChild) {
                $this->json_error('Menu masih memiliki submenu aktif. Nonaktifkan submenu terlebih dahulu.', 422);
                return;
            }
        }

        $newActive = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->Menu_model->update_sidebar_menu($id, ['is_active' => $newActive]);
        $this->clear_sidebar_cache_files();

        $this->json_ok(['id' => $id, 'is_active' => $newActive]);
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
