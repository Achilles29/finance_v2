<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/../libraries/Finance_settlement_control.php';

class Finance_reports extends MY_Controller
{
    private const PERIOD_CLOSE_CSRF_KEY = 'finance_period_close_csrf';
    private const RECON_CSRF_KEY = 'finance_reconciliation_csrf';

    private function reconciliation_csrf(): string
    {
        $token = $this->session->userdata(self::RECON_CSRF_KEY);
        if (!is_string($token) || preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::RECON_CSRF_KEY, $token);
        }
        return $token;
    }

    private function require_reconciliation_csrf(): bool
    {
        $provided = $this->input->get_request_header('X-Finance-Reconciliation-CSRF', false);
        $expected = $this->session->userdata(self::RECON_CSRF_KEY);
        if ($this->input->method(true) !== 'POST' || !is_string($provided) || !is_string($expected)
            || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1 || !hash_equals($expected, $provided)) {
            $this->cash_reconciliation_json(['ok' => false, 'message' => 'Sesi formulir tidak valid. Muat ulang halaman rekonsiliasi.'], 403);
            return false;
        }
        return true;
    }

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_report_model');
        $this->load->model('Finance_cash_reconciliation_model');
        $this->load->model('Finance_revenue_reconciliation_model');
    }

    private function per_page(int $default = 25): int
    {
        $pp = (int)$this->input->get('per_page', true);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : $default;
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

    private function cash_reconciliation_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $payload = json_decode($raw, true);
            if (is_array($payload)) {
                return $payload;
            }
        }

        $post = $this->input->post(NULL, true);
        return is_array($post) ? $post : [];
    }

    private function cash_reconciliation_json(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function cash_reconciliation()
    {
        $this->require_permission('finance.cash_reconciliation.index', 'view');

        $reconciliationDate = trim((string)$this->input->get('date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconciliationDate)) {
            $reconciliationDate = date('Y-m-d');
        }
        $reconciliationId = max(0, (int)$this->input->get('reconciliation_id', true));

        $dashboard = $this->Finance_cash_reconciliation_model->dashboard($reconciliationDate, $reconciliationId);
        $this->render('finance/cash_reconciliation', [
            'page_title' => 'Rekonsiliasi Kas',
            'reconciliation_csrf' => $this->reconciliation_csrf(),
            'active_menu' => 'finance.cash_reconciliation',
            'finance_tab_active' => 'cash-reconciliation',
            'dashboard' => $dashboard,
            'reconciliation_date' => $reconciliationDate,
            'can_reconcile_edit' => $this->can('finance.cash_reconciliation.index', 'edit'),
            'report_categories' => Finance_mutation_policy::categories(),
            'settlement_options' => Finance_settlement_control::options($this->db),
            'round_create_url' => site_url('finance-reports/cash-reconciliation/round-create'),
            'save_url' => site_url('finance-reports/cash-reconciliation/line-save'),
            'post_url' => site_url('finance-reports/cash-reconciliation/line-post'),
            'mutation_url' => site_url('finance/mutations'),
        ]);
    }

    public function cash_reconciliation_line_save()
    {
        if (!$this->require_reconciliation_csrf()) return;
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('finance.cash_reconciliation.index', 'edit');

        $payload = $this->cash_reconciliation_payload();
        $result = $this->Finance_cash_reconciliation_model->save_line(
            $payload,
            $this->actor_user_id(),
            (string)$this->input->ip_address()
        );
        if (empty($result['ok'])) {
            $this->cash_reconciliation_json([
                'ok' => false,
                'message' => (string)($result['message'] ?? 'Gagal menyimpan rekonsiliasi kas.'),
            ], 422);
            return;
        }

        $date = (string)($result['line']['reconciliation_date'] ?? $payload['reconciliation_date'] ?? date('Y-m-d'));
        $reconciliationId = (int)($result['line']['reconciliation_id'] ?? 0);
        $this->cash_reconciliation_json([
            'ok' => true,
            'message' => (string)($result['message'] ?? 'Saldo riil tersimpan.'),
            'line' => $result['line'] ?? [],
            'dashboard' => $this->Finance_cash_reconciliation_model->dashboard($date, $reconciliationId),
        ]);
    }

    public function cash_reconciliation_line_post()
    {
        if (!$this->require_reconciliation_csrf()) return;
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('finance.cash_reconciliation.index', 'edit');

        $payload = $this->cash_reconciliation_payload();
        $result = $this->Finance_cash_reconciliation_model->post_line(
            (int)($payload['line_id'] ?? 0),
            $this->actor_user_id(),
            (string)$this->input->ip_address()
        );
        if (empty($result['ok'])) {
            $this->cash_reconciliation_json([
                'ok' => false,
                'message' => (string)($result['message'] ?? 'Gagal memposting penyesuaian rekonsiliasi.'),
            ], 422);
            return;
        }

        $date = (string)($result['line']['reconciliation_date'] ?? date('Y-m-d'));
        $reconciliationId = (int)($result['line']['reconciliation_id'] ?? 0);
        $this->cash_reconciliation_json([
            'ok' => true,
            'message' => (string)($result['message'] ?? 'Penyesuaian berhasil diposting.'),
            'line' => $result['line'] ?? [],
            'dashboard' => $this->Finance_cash_reconciliation_model->dashboard($date, $reconciliationId),
        ]);
    }

    public function cash_reconciliation_round_create()
    {
        if (!$this->require_reconciliation_csrf()) return;
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('finance.cash_reconciliation.index', 'edit');

        $payload = $this->cash_reconciliation_payload();
        $result = $this->Finance_cash_reconciliation_model->create_round(
            (string)($payload['reconciliation_date'] ?? ''),
            $this->actor_user_id(),
            (string)$this->input->ip_address()
        );
        if (empty($result['ok'])) {
            $this->cash_reconciliation_json([
                'ok' => false,
                'message' => (string)($result['message'] ?? 'Gagal membuat sesi pengecekan.'),
            ], 422);
            return;
        }

        $header = (array)($result['header'] ?? []);
        $query = http_build_query([
            'date' => (string)($header['reconciliation_date'] ?? $payload['reconciliation_date'] ?? date('Y-m-d')),
            'reconciliation_id' => (int)($header['id'] ?? 0),
        ]);
        $this->cash_reconciliation_json([
            'ok' => true,
            'message' => (string)($result['message'] ?? 'Sesi pengecekan dibuat.'),
            'header' => $header,
            'redirect_url' => site_url('finance-reports/cash-reconciliation') . '?' . $query,
        ]);
    }

    public function revenue_reconciliation()
    {
        $this->require_permission('finance.revenue_reconciliation.index', 'view');
        $reconciliationDate = trim((string)$this->input->get('date', true));
        $revenueDate = trim((string)$this->input->get('revenue_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconciliationDate)) $reconciliationDate = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $revenueDate)) $revenueDate = date('Y-m-d', strtotime('-1 day'));
        $reconciliationId = max(0, (int)$this->input->get('reconciliation_id', true));
        $this->render('finance/revenue_reconciliation', [
            'page_title' => 'Rekonsiliasi Pendapatan',
            'reconciliation_csrf' => $this->reconciliation_csrf(),
            'active_menu' => 'finance.cash_reconciliation',
            'finance_tab_active' => 'revenue-reconciliation',
            'dashboard' => $this->Finance_revenue_reconciliation_model->dashboard($reconciliationDate, $revenueDate, $reconciliationId),
            'can_reconcile_edit' => $this->can('finance.revenue_reconciliation.index', 'edit'),
            'report_categories' => Finance_mutation_policy::categories(),
            'settlement_options' => Finance_settlement_control::options($this->db),
            'save_url' => site_url('finance-reports/revenue-reconciliation/line-save'),
            'post_url' => site_url('finance-reports/revenue-reconciliation/line-post'),
            'round_create_url' => site_url('finance-reports/revenue-reconciliation/round-create'),
        ]);
    }

    public function revenue_reconciliation_line_save()
    {
        if (!$this->require_reconciliation_csrf()) return;
        if ($this->input->method() !== 'post') show_404();
        $this->require_permission('finance.revenue_reconciliation.index', 'edit');
        $payload = $this->cash_reconciliation_payload();
        $result = $this->Finance_revenue_reconciliation_model->save_line($payload, $this->actor_user_id());
        $this->cash_reconciliation_json($result, empty($result['ok']) ? 422 : 200);
    }

    public function revenue_reconciliation_line_post()
    {
        if (!$this->require_reconciliation_csrf()) return;
        if ($this->input->method() !== 'post') show_404();
        $this->require_permission('finance.revenue_reconciliation.index', 'edit');
        $payload = $this->cash_reconciliation_payload();
        $result = $this->Finance_revenue_reconciliation_model->post_line((int)($payload['line_id'] ?? 0), $this->actor_user_id());
        $this->cash_reconciliation_json($result, empty($result['ok']) ? 422 : 200);
    }

    public function revenue_reconciliation_round_create()
    {
        if (!$this->require_reconciliation_csrf()) return;
        if ($this->input->method() !== 'post') show_404();
        $this->require_permission('finance.revenue_reconciliation.index', 'edit');
        $payload = $this->cash_reconciliation_payload();
        $result = $this->Finance_revenue_reconciliation_model->create_round($payload, $this->actor_user_id());
        if (!empty($result['ok'])) {
            $header = (array)$result['header'];
            $result['redirect_url'] = site_url('finance-reports/revenue-reconciliation') . '?' . http_build_query([
                'date'=>$header['reconciliation_date'], 'revenue_date'=>$header['revenue_date'], 'reconciliation_id'=>$header['id'],
            ]);
        }
        $this->cash_reconciliation_json($result, empty($result['ok']) ? 422 : 200);
    }

    public function cash_vault_daily()
    {
        $this->require_permission('finance.cash_vault_daily.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $accounts = $this->Finance_report_model->active_company_accounts();
        $selectedAccountId = (int)$this->input->get('account_id', true);
        if ($selectedAccountId <= 0) {
            $selectedAccountId = (int)$this->input->get('bank_account_id', true);
        }

        $allowedIds = array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, (array)$accounts);
        $allowedIds = array_values(array_filter($allowedIds));

        if ($selectedAccountId <= 0 || !in_array($selectedAccountId, $allowedIds, true)) {
            $selectedAccountId = $this->Finance_report_model->default_cash_account_id($accounts);
        }

        $report = $this->Finance_report_model->cash_vault_daily($month, $selectedAccountId);

        $this->render('finance/cash_vault_daily', [
            'page_title' => 'Laporan Brankas Harian',
            'active_menu' => 'finance.cash_vault_daily',
            'month' => $month,
            'accounts' => $accounts,
            'selected_account_id' => $selectedAccountId,
            'report' => $report,
        ]);
    }

    public function cash_position()
    {
        $this->require_permission('finance.cash_position.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $viewMode = strtoupper(trim((string)$this->input->get('view_mode', true)));
        if (!in_array($viewMode, ['PHYSICAL', 'REAL', 'HISTORICAL'], true)) {
            $viewMode = 'REAL';
        }

        $accounts = $this->Finance_report_model->active_company_accounts();
        $selectedAccountId = (int)$this->input->get('account_id', true);
        $allowedIds = array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, (array)$accounts);
        $allowedIds = array_values(array_filter($allowedIds));
        if ($selectedAccountId > 0 && !in_array($selectedAccountId, $allowedIds, true)) {
            $selectedAccountId = 0;
        }

        $report = $this->Finance_report_model->cash_position_exposure($month, $selectedAccountId, $viewMode);

        $this->render('finance/cash_position_exposure', [
            'page_title' => 'Posisi Kas & Eksposur',
            'active_menu' => 'finance.cash_position',
            'month' => $month,
            'view_mode' => $viewMode,
            'accounts' => $accounts,
            'selected_account_id' => $selectedAccountId,
            'report' => $report,
        ]);
    }

    public function financial_estimation()
    {
        $this->require_permission('finance.financial_estimation.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        $year = (int)$this->input->get('year', true);
        if (!preg_match('/^\d{1,2}$/', $month)) {
            $month = date('n');
        }
        $monthInt = max(1, min(12, (int)$month));
        if ($year <= 0) {
            $year = (int)date('Y');
        }

        $report = $this->Finance_report_model->financial_estimation_report($year, $monthInt);

        $this->render('finance/financial_estimation', [
            'page_title' => 'Estimasi Keuangan',
            'active_menu' => 'finance.financial_estimation',
            'month' => $monthInt,
            'year' => $year,
            'report' => $report,
        ]);
    }

    public function bank_daily_recap()
    {
        $this->require_permission('finance.bank_daily_recap.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $report = $this->Finance_report_model->bank_daily_recap($month);

        $this->render('finance/bank_daily_recap', [
            'page_title' => 'Rekap Rekening Harian',
            'active_menu' => 'finance.bank_daily_recap',
            'month' => $month,
            'report' => $report,
        ]);
    }

    public function daily_overview()
    {
        $this->require_permission('finance.daily_overview.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $report = $this->Finance_report_model->daily_overview_report($month);

        $this->render('finance/daily_overview', [
            'page_title' => 'Keuangan Harian',
            'active_menu' => 'finance.daily_overview',
            'month' => $month,
            'report' => $report,
        ]);
    }

    public function period_close()
    {
        $this->require_permission('finance.period_close.index', 'view');
        $csrf = $this->period_close_csrf();
        $this->output->set_header('Cache-Control: private, no-store');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'period_type' => strtoupper(trim((string)$this->input->get('period_type', true))),
        ];

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Finance_report_model->count_period_closes($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Finance_report_model->list_period_closes($filters, $pg['per_page'], $pg['offset']);
        $summary = $this->Finance_report_model->summarize_period_closes($filters);

        $this->render('finance/period_close_index', [
            'page_title' => 'Tutup Periode Keuangan',
            'active_menu' => 'finance.period_close',
            'filters' => $filters,
            'period_close_csrf' => $csrf,
            'can_create' => $this->can('finance.period_close.index', 'create'),
            'can_edit' => $this->can('finance.period_close.index', 'edit'),
            'pg' => $pg,
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function period_close_detail($id = 0)
    {
        $this->require_permission('finance.period_close.index', 'view');

        $row = $this->Finance_report_model->get_period_close_detail((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $csrf = $this->period_close_csrf();
        $this->output->set_header('Cache-Control: private, no-store');
        $this->render('finance/period_close_detail', [
            'page_title' => 'Detail Tutup Periode',
            'active_menu' => 'finance.period_close',
            'row' => $row,
            'period_close_csrf' => $csrf,
            'can_edit' => $this->can('finance.period_close.index', 'edit'),
            'snapshot_rows' => $this->Finance_report_model->list_period_close_snapshots((int)$id),
            'metric_rows' => $this->Finance_report_model->list_period_close_metrics((int)$id),
            'snapshot_summary' => $this->Finance_report_model->summarize_period_close_snapshots((int)$id),
            'metric_summary' => $this->Finance_report_model->summarize_period_close_metrics((int)$id),
        ]);
    }

    public function period_close_store()
    {
        $this->require_permission('finance.period_close.index', 'create');
        if (!$this->require_period_close_csrf()) {
            return;
        }

        $payload = $this->input->post(NULL, true) ?: [];
        unset($payload[self::PERIOD_CLOSE_CSRF_KEY]);
        $result = $this->Finance_report_model->save_period_close($payload, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan draft tutup periode.'));
        redirect('finance-reports/period-close');
    }

    public function period_close_process($id = 0)
    {
        $this->require_permission('finance.period_close.index', 'edit');
        if (!$this->require_period_close_csrf()) {
            return;
        }

        $result = $this->Finance_report_model->close_period((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses close period.'));
        // Both official forms return to this document; never trust a posted URL.
        redirect('finance-reports/period-close/detail/' . (int)$id);
    }

    public function period_close_reopen($id = 0)
    {
        $this->require_permission('finance.period_close.index', 'edit');
        if (!$this->require_period_close_csrf()) {
            return;
        }
        if (!$this->require_period_reopen_step_up((int)$id)) {
            redirect('finance-reports/period-close/detail/' . (int)$id);
            return;
        }

        $result = $this->Finance_report_model->reopen_period((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membuka ulang period close.'));
        redirect('finance-reports/period-close/detail/' . (int)$id);
    }

    private function require_period_reopen_step_up(int $periodCloseId): bool
    {
        $password = $this->input->post('step_up_password', false);
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $issued = $this->sensitiveactionstepup->issue(
            $this->actor_user_id(),
            'PERIOD_REOPEN',
            $periodCloseId,
            $password
        );
        if (empty($issued['ok'])) {
            $this->session->set_flashdata('error', (string)($issued['message'] ?? 'Verifikasi ulang tidak berhasil.'));
            return false;
        }

        $consumed = $this->sensitiveactionstepup->consume(
            $this->actor_user_id(),
            'PERIOD_REOPEN',
            $periodCloseId,
            $issued['proof'] ?? null
        );
        if (empty($consumed['ok'])) {
            $this->session->set_flashdata('error', (string)($consumed['message'] ?? 'Verifikasi ulang diperlukan.'));
            return false;
        }
        return true;
    }

    private function period_close_csrf(): array
    {
        $token = $this->session->userdata(self::PERIOD_CLOSE_CSRF_KEY);
        if (!is_string($token) || preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::PERIOD_CLOSE_CSRF_KEY, $token);
        }
        return ['name' => self::PERIOD_CLOSE_CSRF_KEY, 'value' => $token];
    }

    private function require_period_close_csrf(): bool
    {
        $this->output->set_header('Cache-Control: private, no-store');
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            $this->reject_period_close_request(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $provided = $this->input->post(self::PERIOD_CLOSE_CSRF_KEY, false);
        $expected = $this->session->userdata(self::PERIOD_CLOSE_CSRF_KEY);
        if (!is_string($provided) || !is_string($expected)
            || preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
            || !hash_equals($expected, $provided)
        ) {
            $this->reject_period_close_request(403, 'Sesi formulir tidak valid. Muat ulang halaman Tutup Periode Keuangan, lalu coba lagi.');
            return false;
        }
        return true;
    }

    private function reject_period_close_request(int $status, string $message): void
    {
        if ($this->input->is_ajax_request()) {
            $this->output->set_status_header($status)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }
        show_error($message, $status, 'Tutup Periode Keuangan');
    }

    public function targets()
    {
        $this->require_permission('finance.target.index', 'view');

        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['list', 'progress', 'guide'], true)) {
            $tab = 'list';
        }

        $defaultDateStart = date('Y-m-01');
        $defaultDateEnd = date('Y-m-t');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'target_scope' => strtoupper(trim((string)$this->input->get('target_scope', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $filters['date_start'])) {
            $filters['date_start'] = $defaultDateStart;
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $filters['date_end'])) {
            $filters['date_end'] = $defaultDateEnd;
        }
        if ($filters['date_start'] > $filters['date_end']) {
            [$filters['date_start'], $filters['date_end']] = [$filters['date_end'], $filters['date_start']];
        }

        $effectiveFilters = $filters;
        if ($tab === 'progress') {
            $effectiveFilters['status'] = 'ACTIVE';
            $effectiveFilters['sort_mode'] = 'PROGRESS';
        }

        $perPage = $this->per_page(50);
        $page = $this->page();
        $total = $this->Finance_report_model->count_target_plans($effectiveFilters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = [];
        $progressRows = [];
        if ($tab === 'progress') {
            $progressRows = $this->Finance_report_model->list_target_progress_dashboard($effectiveFilters, $pg['per_page'], $pg['offset'], date('Y-m-d'));
        } else {
            $rows = $this->Finance_report_model->list_target_plans($filters, $pg['per_page'], $pg['offset']);
        }
        $summary = $this->Finance_report_model->summarize_target_plans($effectiveFilters);

        $this->render('finance/target_plan_index', [
            'page_title' => 'Target Keuangan',
            'active_menu' => 'finance.target',
            'tab' => $tab,
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'progress_rows' => $progressRows,
            'summary' => $summary,
            'division_options' => $this->Finance_report_model->division_options(),
            'company_accounts' => $this->Finance_report_model->active_company_accounts(),
            'metric_catalog' => $this->Finance_report_model->metric_catalog_options(),
        ]);
    }

    public function target_realize_bulk()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        @set_time_limit(300);

        $this->require_permission('finance.target.index', 'edit');
        $ids = $this->input->post('target_ids');
        $targetIds = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
        $redirectTo = trim((string)$this->input->post('redirect_to', true));
        if ($redirectTo === '') {
            $redirectTo = 'finance-reports/targets';
        }

        if (empty($targetIds)) {
            $this->session->set_flashdata('error', 'Pilih minimal satu target yang ingin dihitung.');
            redirect($redirectTo);
        }

        $result = $this->Finance_report_model->generate_target_realization_bulk($targetIds, $this->actor_user_id());
        if (!empty($result['ok'])) {
            $this->session->set_flashdata('success', (string)($result['message'] ?? 'Target terpilih berhasil dihitung / ditimpa ulang.'));
            if (!empty($result['failed_rows'])) {
                $this->session->set_flashdata('error', implode(' | ', (array)$result['failed_rows']));
            }
        } else {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menghitung target terpilih.'));
        }

        redirect($redirectTo);
    }

    public function target_detail($id = 0)
    {
        $this->require_permission('finance.target.index', 'view');

        $row = $this->Finance_report_model->get_target_plan_detail((int)$id);
        if (!$row) {
            show_404();
        }

        $this->render('finance/target_plan_detail', [
            'page_title' => 'Detail Target Keuangan',
            'active_menu' => 'finance.target',
            'row' => $row,
            'metric_lines' => $this->Finance_report_model->list_target_plan_lines((int)$id),
            'realization_summary' => $this->Finance_report_model->summarize_target_plan_realization((int)$id),
        ]);
    }

    public function target_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'create');
        $result = $this->Finance_report_model->save_target_plan($this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan draft target keuangan.'));
        redirect('finance-reports/targets');
    }

    public function target_generate_daily_range()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'create');
        $result = $this->Finance_report_model->bulk_generate_daily_targets($this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal generate target harian per rentang tanggal.'));
        redirect('finance-reports/targets');
    }

    public function target_realize($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        @set_time_limit(180);

        $this->require_permission('finance.target.index', 'edit');
        $result = $this->Finance_report_model->generate_target_realization((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghitung realisasi target.'));
        $redirectTo = trim((string)$this->input->post('redirect_to', true));
        redirect($redirectTo !== '' ? $redirectTo : 'finance-reports/targets');
    }

    public function target_update($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'edit');
        $result = $this->Finance_report_model->update_target_plan((int)$id, $this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui target.'));
        redirect('finance-reports/targets/detail/' . (int)$id);
    }

    public function target_lines_save($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'edit');
        $result = $this->Finance_report_model->save_target_plan_lines((int)$id, $this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan metric target.'));
        redirect('finance-reports/targets/detail/' . (int)$id);
    }
}
