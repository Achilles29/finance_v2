<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_model');
    }

    private function per_page(): int
    {
        $pp = (int)$this->input->get('per_page', true);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    private function page(): int
    {
        return max(1, (int)$this->input->get('page', true));
    }

    private function build_pagination(int $total, int $perPage, int $page): array
    {
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private function actor_user_id(): int
    {
        return (int)($this->current_user['id'] ?? 0);
    }

    private function request_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        $post = $this->input->post(NULL, true);
        return is_array($post) ? $post : [];
    }

    private function json_ok(array $data = [], int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $payload = array_merge(['ok' => true], $data);
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $status = 422, array $extra = []): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $payload = array_merge(['ok' => false, 'message' => $message], $extra);
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function parties()
    {
        $this->require_permission('finance.party.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true) ?: 'ACTIVE')),
            'party_type' => strtoupper(trim((string)$this->input->get('party_type', true))),
        ];
        if (!in_array($filters['status'], ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $filters['status'] = 'ACTIVE';
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Finance_model->count_parties($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Finance_model->list_parties($filters, $pg['per_page'], $pg['offset']);

        $editId = (int)$this->input->get('edit_id', true);
        $editRow = $editId > 0 ? $this->Finance_model->get_party_by_id($editId) : null;

        $this->render('finance/party_index', [
            'page_title' => 'Relasi Utang & Piutang',
            'active_menu' => 'finance.party',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'edit_row' => $editRow,
        ]);
    }

    public function party_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('finance.party.index', $id > 0 ? 'edit' : 'create');

        $result = $this->Finance_model->save_party($payload, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan data pihak.'));
            return;
        }

        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'row' => $result['row'] ?? null,
            'message' => (string)($result['message'] ?? 'Data pihak berhasil disimpan.'),
        ]);
    }

    public function party_toggle(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('finance.party.index', 'edit');
        $result = $this->Finance_model->toggle_party($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal mengubah status pihak.'));
        redirect('finance/relasi');
    }

    public function party_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('finance.party.index', 'delete');
        $result = $this->Finance_model->delete_party($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus pihak.'));
        redirect('finance/relasi');
    }

    public function party_search()
    {
        $this->require_permission('finance.party.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true)));
        $this->json_ok(['rows' => $this->Finance_model->party_search($q, $limit)]);
    }

    public function member_search()
    {
        $this->require_permission('finance.party.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true)));
        $this->json_ok(['rows' => $this->Finance_model->member_search($q, $limit)]);
    }

    public function utang()
    {
        $this->loan_index('payable');
    }

    public function piutang()
    {
        $this->loan_index('receivable');
    }

    private function loan_index(string $kind): void
    {
        $cfg = $this->loan_page_config($kind);
        $this->require_permission($cfg['page_code'], 'view');

        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['recap', 'all', 'party', 'account', 'party_account'], true)) {
            $tab = 'recap';
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'party_id' => (int)$this->input->get('party_id', true),
            'account_id' => (int)$this->input->get('account_id', true),
            'impact_mode' => strtoupper(trim((string)$this->input->get('impact_mode', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
            'tab' => $tab,
        ];

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $tab === 'all'
            ? $this->Finance_model->count_loan_docs($kind, $filters)
            : $this->Finance_model->count_loan_tab_rows($kind, $filters, $tab);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $tab === 'all'
            ? $this->Finance_model->list_loan_docs($kind, $filters, $pg['per_page'], $pg['offset'])
            : $this->Finance_model->list_loan_tab_rows($kind, $filters, $tab, $pg['per_page'], $pg['offset']);
        $summary = $this->Finance_model->summarize_loan_docs($kind, $filters);
        $recap = $this->Finance_model->summarize_loan_recap($kind, $filters);

        $detailId = (int)$this->input->get('detail_id', true);
        $detailRow = $detailId > 0 ? $this->Finance_model->get_loan_by_id($kind, $detailId) : null;
        $detailPayments = $detailId > 0 ? $this->Finance_model->list_loan_payments($kind, $detailId) : [];

        $editId = (int)$this->input->get('edit_id', true);
        $editRow = $editId > 0 ? $this->Finance_model->get_loan_by_id($kind, $editId) : null;

        $this->render('finance/loan_index', [
            'page_title' => $cfg['page_title'],
            'active_menu' => $cfg['menu_code'],
            'loan_cfg' => $cfg,
            'filters' => $filters,
            'pg' => $pg,
            'loan_tab' => $tab,
            'rows' => $rows,
            'summary' => $summary,
            'recap' => $recap,
            'detail_row' => $detailRow,
            'detail_payments' => $detailPayments,
            'edit_row' => $editRow,
            'company_account_options' => $this->Finance_model->get_company_account_options(),
        ]);
    }

    public function utang_store()
    {
        $this->loan_store('payable');
    }

    public function piutang_store()
    {
        $this->loan_store('receivable');
    }

    private function loan_store(string $kind): void
    {
        $cfg = $this->loan_page_config($kind);
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission($cfg['page_code'], 'create');
        $result = $this->Finance_model->save_loan($kind, $this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan transaksi.'));
        redirect($cfg['base_url'] . (!empty($result['ok']) && !empty($result['id']) ? '?detail_id=' . (int)$result['id'] : ''));
    }

    public function utang_update(int $id)
    {
        $this->loan_update('payable', $id);
    }

    public function piutang_update(int $id)
    {
        $this->loan_update('receivable', $id);
    }

    private function loan_update(string $kind, int $id): void
    {
        $cfg = $this->loan_page_config($kind);
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission($cfg['page_code'], 'edit');
        $payload = $this->input->post(NULL, true) ?: [];
        $payload['id'] = $id;
        $result = $this->Finance_model->save_loan($kind, $payload, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui transaksi.'));
        redirect($cfg['base_url'] . '?detail_id=' . $id);
    }

    public function utang_payment(int $id)
    {
        $this->loan_payment('payable', $id);
    }

    public function piutang_payment(int $id)
    {
        $this->loan_payment('receivable', $id);
    }

    private function loan_payment(string $kind, int $id): void
    {
        $cfg = $this->loan_page_config($kind);
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission($cfg['page_code'], 'edit');
        $result = $this->Finance_model->save_loan_payment($kind, $id, $this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan pembayaran.'));
        redirect($cfg['base_url'] . '?detail_id=' . $id);
    }

    public function utang_void(int $id)
    {
        $this->loan_void('payable', $id);
    }

    public function piutang_void(int $id)
    {
        $this->loan_void('receivable', $id);
    }

    private function loan_void(string $kind, int $id): void
    {
        $cfg = $this->loan_page_config($kind);
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission($cfg['page_code'], 'delete');
        $result = $this->Finance_model->void_loan($kind, $id, trim((string)$this->input->post('notes', true)), $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal VOID transaksi.'));
        redirect($cfg['base_url']);
    }

    public function utang_delete(int $id)
    {
        $this->loan_delete('payable', $id);
    }

    public function piutang_delete(int $id)
    {
        $this->loan_delete('receivable', $id);
    }

    private function loan_delete(string $kind, int $id): void
    {
        $cfg = $this->loan_page_config($kind);
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission($cfg['page_code'], 'delete');
        $result = $this->Finance_model->delete_loan($kind, $id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus transaksi.'));
        redirect($cfg['base_url']);
    }

    private function loan_page_config(string $kind): array
    {
        if ($kind === 'receivable') {
            return [
                'page_code' => 'finance.receivable.index',
                'menu_code' => 'finance.receivable',
                'base_url' => 'finance/piutang',
                'page_title' => 'Piutang Pihak Luar',
                'title' => 'Piutang',
                'title_plural' => 'Piutang',
                'create_label' => 'Tambah Piutang',
                'payment_label' => 'Terima Pembayaran',
                'impact_help_apply' => 'Saldo rekening akan berkurang sekarang, karena dana piutang benar-benar keluar dari kas/rekening perusahaan. Rekening ini juga menjadi pengait laporan saldo rekening dan saldo riil.',
                'impact_help_keep' => 'Saldo rekening tidak diubah. Pakai ini bila piutang sudah terjadi sebelum aplikasi dipakai, tetapi rekening tetap wajib dipilih agar posisi piutang tetap terbaca per rekening pada laporan.',
            ];
        }

        return [
            'page_code' => 'finance.payable.index',
            'menu_code' => 'finance.payable',
            'base_url' => 'finance/utang',
            'page_title' => 'Utang Pihak Luar',
            'title' => 'Utang',
            'title_plural' => 'Utang',
            'create_label' => 'Tambah Utang',
            'payment_label' => 'Bayar Utang',
            'impact_help_apply' => 'Saldo rekening akan bertambah sekarang, karena dana utang memang masuk ke kas/rekening perusahaan. Rekening ini juga menjadi pengait laporan saldo rekening dan saldo riil.',
            'impact_help_keep' => 'Saldo rekening tidak diubah. Pakai ini bila utang sudah terjadi sebelum aplikasi dipakai, tetapi rekening tetap wajib dipilih agar posisi utang tetap terbaca per rekening pada laporan.',
        ];
    }
}
