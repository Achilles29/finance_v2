<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gowes_participants extends MY_Controller
{
    private const PAGE = 'loyalty.voucher_campaign.index';
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Gowes_voucher_model');
    }

    public function index(): void
    {
        $this->require_permission(self::PAGE, 'view');
        $token = (string)$this->session->userdata('gowes_import_csrf');
        if ($token === '') { $token = bin2hex(random_bytes(32)); $this->session->set_userdata('gowes_import_csrf', $token); }
        $search = mb_substr(trim((string)$this->input->get('q', true)), 0, 150);
        $page = max(1, (int)$this->input->get('page'));
        $this->render('gowes/participants', array_merge($this->Gowes_voucher_model->admin_data($search, $page), [
            'page_title' => 'Peserta GOWES VOL9', 'active_menu' => self::PAGE, 'promo_tab_active' => 'gowes',
            'import_csrf' => $token, 'search' => $search, 'page' => $page,
            'can_import' => $this->can(self::PAGE, 'create') && $this->can(self::PAGE, 'edit'),
            'import_result' => $this->session->flashdata('gowes_import_result'),
        ]));
    }

    public function import(): void
    {
        $this->require_permission(self::PAGE, 'create');
        $this->require_permission(self::PAGE, 'edit');
        $token = $this->input->post('import_csrf', false);
        $expected = (string)$this->session->userdata('gowes_import_csrf');
        if ($this->input->method(true) !== 'POST' || !is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
            show_error('Permintaan impor tidak valid. Muat ulang halaman.', 403); return;
        }
        $file = $_FILES['participants'] ?? [];
        $result = ['ok' => false, 'message' => 'Pilih file XLSX maksimal 2 MB.'];
        if (($file['error'] ?? -1) === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])
            && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'xlsx') {
            $this->load->library('Gowes_participant_import');
            $prepared = $this->gowes_participant_import->read($file['tmp_name']);
            $result = $this->Gowes_voucher_model->import_participants($prepared, $file['name'], hash_file('sha256', $file['tmp_name']), (int)$this->current_user['id']);
        }
        $this->session->set_flashdata('gowes_import_result', $result);
        redirect('loyalty/gowes-participants');
    }
}
