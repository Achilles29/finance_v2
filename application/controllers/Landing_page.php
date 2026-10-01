<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Landing_page extends MY_Controller
{
    private const PAGE_CODE = 'landing_page.index';
    private const MUTATION_CSRF_SESSION_KEY = 'landing_page_mutation_csrf';
    private const MUTATION_CSRF_FORM_FIELD = 'landing_page_mutation_csrf';
    private const MUTATION_CSRF_HEADER = 'X-Landing-Page-Csrf';

    public function __construct()
    {
        parent::__construct();
        $this->require_permission(self::PAGE_CODE);
        $this->load->model('Landing_page_model', 'lp');
    }

    // ── HALAMAN UTAMA ────────────────────────────────────────────────

    public function index(): void
    {
        $tab = in_array($this->input->get('tab', true), ['config', 'menu', 'gallery', 'embed', 'links'], true)
            ? $this->input->get('tab', true)
            : 'config';

        $data = [
            'title'              => 'Pengaturan Landing Page',
            'tab'                => $tab,
            'cfg'                => $this->lp->get_config(),
            'menus'              => $this->lp->get_landing_products(),
            'available_products' => $this->lp->get_available_landing_products(),
            'product_categories' => $this->lp->get_product_categories(),
            'galleries'          => $this->lp->get_gallery(true),
            'embeds'             => $this->lp->get_embed(true),
            'links'              => $this->lp->get_links(true),
            'landing_page_mutation_csrf' => $this->landing_page_mutation_csrf(),
        ];
        $this->render('landing_page/index', $data);
    }

    // ── CONFIG ───────────────────────────────────────────────────────

    public function config_update(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }

        $text_fields = [
            'hero_title', 'hero_subtitle', 'hero_image',
            'about_title', 'about_text', 'about_image',
            'address', 'phone', 'whatsapp',
            'order_url', 'member_url', 'instagram_url', 'linktree_url', 'map_url',
            'cta_title', 'cta_text', 'footer_text',
            'seo_title', 'seo_description', 'seo_canonical_url', 'seo_share_image',
            'seo_google_verification',
        ];
        $data = [];
        foreach ($text_fields as $f) {
            $data[$f] = trim((string)$this->input->post($f, true));
        }

        // Hero badges — textarea, satu badge per baris
        $badges = array_values(array_filter(
            array_map('trim', explode("\n", (string)$this->input->post('hero_badges_raw', true)))
        ));
        $data['hero_badges'] = json_encode($badges, JSON_UNESCAPED_UNICODE);

        // About points — textarea, satu poin per baris
        $points = array_values(array_filter(
            array_map('trim', explode("\n", (string)$this->input->post('about_points_raw', true)))
        ));
        $data['about_points'] = json_encode($points, JSON_UNESCAPED_UNICODE);

        // Pengaturan sumber data
        $gallery_source = $this->input->post('gallery_source', true);
        $data['menu_source']          = 'produk';
        $data['gallery_source']       = in_array($gallery_source, ['manual', 'produk'], true) ? $gallery_source : 'manual';
        $data['menu_limit']           = max(4, min(20, (int)$this->input->post('menu_limit', true)));
        $data['gallery_limit']        = max(4, min(12, (int)$this->input->post('gallery_limit', true)));
        $data['menu_best_seller_top'] = max(1, min(10, (int)$this->input->post('menu_best_seller_top', true)));
        $data['menu_kategori_ids']    = $this->_category_ids_from_post('menu_kategori_ids');
        $data['gallery_kategori_ids'] = $this->_category_ids_from_post('gallery_kategori_ids');
        $data['seo_indexing']         = $this->input->post('seo_indexing', true) === 'noindex' ? 'noindex' : 'index';

        $data['updated_by'] = (int)$this->current_user['id'];

        $this->lp->upsert_config($data);
        $this->session->set_flashdata('success', 'Pengaturan landing page berhasil disimpan.');
        redirect('landing-page?tab=config');
    }

    // ── MENU ─────────────────────────────────────────────────────────

    public function menu_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        if (!$this->require_landing_mutation()) {
            return;
        }
        $productId = (int)$this->input->post('product_id', true);
        $product = $this->lp->find_product($productId);
        if (!$product || (int)$product['is_active'] !== 1) {
            $this->json_error('Produk aktif tidak ditemukan.', 404);
            return;
        }
        if ((int)$product['show_landing'] === 1) {
            $this->json_error('Produk sudah tampil di landing page.', 422);
            return;
        }

        $this->lp->add_product_to_landing($productId);
        $this->json_ok(['message' => 'Produk ditambahkan ke landing page.']);
    }

    public function menu_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_product($id)) {
            $this->json_error('Produk tidak ditemukan.', 404);
            return;
        }
        $this->json_error('Nama, harga, foto, dan deskripsi harus diubah dari Master Produk.', 409);
    }

    public function menu_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_product($id)) { $this->json_error('Produk tidak ditemukan.', 404); return; }
        $this->lp->remove_product_from_landing($id);
        $this->json_ok(['message' => 'Produk disembunyikan dari landing page.']);
    }

    public function menu_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_product($id)) { $this->json_error('Produk tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_landing_product($id);
        $this->json_ok(['message' => 'Status tampil produk berhasil diubah.', 'is_active' => $new]);
    }

    public function menu_reorder(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->json_error('Urutan landing dihitung otomatis dari penjualan 90 hari.', 409);
    }

    private function _category_ids_from_post(string $field): string
    {
        $raw = $this->input->post($field, true);
        $values = is_array($raw) ? $raw : explode(',', (string)$raw);
        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) $ids[$id] = $id;
        }
        return implode(',', array_values($ids));
    }

    private function json_ok(array $data = [], int $statusCode = 200): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $statusCode = 422, array $data = []): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function landing_page_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::MUTATION_CSRF_SESSION_KEY, $token);
        }
        return $token;
    }

    private function require_landing_mutation(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->json_error('Metode request tidak diizinkan.', 405);
            return false;
        }

        $provided = trim((string)$this->input->get_request_header(self::MUTATION_CSRF_HEADER, true));
        if ($provided === '') {
            $provided = trim((string)$this->input->post(self::MUTATION_CSRF_FORM_FIELD, true));
        }
        $expected = $this->landing_page_mutation_csrf();
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
            || !hash_equals($expected, $provided)
        ) {
            $this->json_error('Permintaan Landing Page tidak valid.', 403);
            return false;
        }
        return true;
    }

    // ── GALLERY ───────────────────────────────────────────────────────

    public function gallery_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        if (!$this->require_landing_mutation()) {
            return;
        }
        $input = $this->_gallery_input();
        if ($input['image'] === '') { $this->json_error('URL gambar tidak boleh kosong.', 422); return; }

        $input['sort_order'] = $this->lp->next_gallery_sort();
        $input['created_by'] = (int)$this->current_user['id'];
        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->insert_gallery($input);
        $this->json_ok(['message' => 'Foto gallery berhasil ditambahkan.']);
    }

    public function gallery_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_gallery($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }

        $input = $this->_gallery_input();
        if ($input['image'] === '') { $this->json_error('URL gambar tidak boleh kosong.', 422); return; }

        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->update_gallery($id, $input);
        $this->json_ok(['message' => 'Foto gallery berhasil diperbarui.']);
    }

    public function gallery_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'delete');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_gallery($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $this->lp->delete_gallery($id);
        $this->json_ok(['message' => 'Foto gallery berhasil dihapus.']);
    }

    public function gallery_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_gallery($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_gallery($id);
        $this->json_ok(['message' => 'Status berhasil diubah.', 'is_active' => $new]);
    }

    public function gallery_reorder(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
        if (empty($ids)) { $this->json_error('Data urutan tidak valid.', 422); return; }
        $this->lp->reorder_gallery(array_values($ids));
        $this->json_ok(['message' => 'Urutan gallery berhasil disimpan.']);
    }

    private function _gallery_input(): array
    {
        return [
            'image'     => trim((string)$this->input->post('image', true)),
            'caption'   => trim((string)$this->input->post('caption', true)),
            'is_active' => $this->input->post('is_active', true) !== '0' ? 1 : 0,
        ];
    }

    // ── EMBED ─────────────────────────────────────────────────────────

    public function embed_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        if (!$this->require_landing_mutation()) {
            return;
        }
        $input = $this->_embed_input();
        if (trim($input['embed_html']) === '') { $this->json_error('Kode embed tidak boleh kosong.', 422); return; }

        $input['sort_order'] = $this->lp->next_embed_sort($input['embed_type']);
        $input['created_by'] = (int)$this->current_user['id'];
        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->insert_embed($input);
        $this->json_ok(['message' => 'Embed berhasil ditambahkan.']);
    }

    public function embed_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_embed($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }

        $input = $this->_embed_input();
        if (trim($input['embed_html']) === '') { $this->json_error('Kode embed tidak boleh kosong.', 422); return; }

        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->update_embed($id, $input);
        $this->json_ok(['message' => 'Embed berhasil diperbarui.']);
    }

    public function embed_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'delete');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_embed($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $this->lp->delete_embed($id);
        $this->json_ok(['message' => 'Embed berhasil dihapus.']);
    }

    public function embed_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_embed($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_embed($id);
        $this->json_ok(['message' => 'Status berhasil diubah.', 'is_active' => $new]);
    }

    private function _embed_input(): array
    {
        $type = $this->input->post('embed_type', true);
        return [
            'embed_type' => in_array($type, ['reel', 'photo'], true) ? $type : 'photo',
            'embed_html' => trim((string)$this->input->post('embed_html')), // sengaja tidak XSS-filter HTML embed
            'is_active'  => $this->input->post('is_active', true) !== '0' ? 1 : 0,
        ];
    }

    // ── LINKS ─────────────────────────────────────────────────────────

    public function links_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        if (!$this->require_landing_mutation()) {
            return;
        }
        $input = $this->_link_input();
        if ($input['label'] === '') { $this->json_error('Label tidak boleh kosong.', 422); return; }
        if ($input['url']   === '') { $this->json_error('URL tidak boleh kosong.', 422);   return; }

        $input['sort_order'] = $this->lp->next_link_sort();
        $input['created_by'] = (int)$this->current_user['id'];
        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->insert_link($input);
        $this->json_ok(['message' => 'Link berhasil ditambahkan.']);
    }

    public function links_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_link($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }

        $input = $this->_link_input();
        if ($input['label'] === '') { $this->json_error('Label tidak boleh kosong.', 422); return; }
        if ($input['url']   === '') { $this->json_error('URL tidak boleh kosong.', 422);   return; }

        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->update_link($id, $input);
        $this->json_ok(['message' => 'Link berhasil diperbarui.']);
    }

    public function links_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'delete');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_link($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $this->lp->delete_link($id);
        $this->json_ok(['message' => 'Link berhasil dihapus.']);
    }

    public function links_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        if ($id <= 0 || !$this->lp->find_link($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_link($id);
        $this->json_ok(['message' => 'Status berhasil diubah.', 'is_active' => $new]);
    }

    public function links_reorder(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if (!$this->require_landing_mutation()) {
            return;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
        if (empty($ids)) { $this->json_error('Data urutan tidak valid.', 422); return; }
        $this->lp->reorder_links(array_values($ids));
        $this->json_ok(['message' => 'Urutan link berhasil disimpan.']);
    }

    private function _link_input(): array
    {
        return [
            'label'     => trim((string)$this->input->post('label', true)),
            'url'       => trim((string)$this->input->post('url', true)),
            'icon'      => trim((string)$this->input->post('icon', true)),
            'is_active' => $this->input->post('is_active', true) !== '0' ? 1 : 0,
        ];
    }
}
