<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Operator workspace for visibility and controlled locking of inventory.
 * It never writes stock quantities directly.
 */
class Inventory_control extends MY_Controller
{
    private const INVENTORY_CONTROL_MUTATION_CSRF_NAME = 'inventory_control_mutation_csrf';
    private const PAGE_DEFICIT = 'inventory.stock.deficit.index';
    private const PAGE_PERIOD = 'inventory.stock.period.index';
    private const PAGE_HEALTH = 'inventory.stock.health.index';
    private const PAGE_INTEGRITY_AUDIT = 'inventory.stock.integrity_audit.index';
    private const PAGE_VALUE_RECON = 'inventory.stock.value_reconciliation.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Inventory_control_model');
        $this->load->model('Production_model');
    }

    public function deficits()
    {
        $this->require_permission(self::PAGE_DEFICIT, 'view');
        $reconPermissions = $this->deficit_recon_permissions();
        $filters = $this->deficit_filters();
        $perPage = $this->per_page((int)($this->input->get('per_page', true) ?: 50));
        $page = max(1, (int)($this->input->get('page', true) ?: 1));
        $total = $this->Inventory_control_model->count_deficits($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $summary = $this->Inventory_control_model->deficit_summary($filters);
        $summary['group_count'] = $total;

        $this->render('inventory/stock_deficit_index', [
            'page_title' => 'Defisit Stok',
            'active_menu' => 'inventory.stock.deficit',
            'filters' => $filters,
            'summary' => $summary,
            'rows' => $this->Inventory_control_model->list_deficits($filters, $perPage, ($page - 1) * $perPage),
            'divisions' => $this->active_divisions(),
            'can_material_recon' => $reconPermissions['material'],
            'can_warehouse_recon' => $reconPermissions['warehouse'],
            'can_component_recon' => $reconPermissions['component'],
            'pg' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function deficit_detail($id)
    {
        $this->require_permission(self::PAGE_DEFICIT, 'view');
        $detail = $this->Inventory_control_model->get_deficit_detail((int)$id, $this->active_division_id());
        if ($detail === null) {
            show_error('Data defisit stok tidak ditemukan.', 404, 'Defisit Stok Tidak Ditemukan');
            return;
        }
        $header = (array)($detail['header'] ?? []);
        $reconPermissions = $this->deficit_recon_permissions();
        $isComponent = strtoupper((string)($header['stock_domain'] ?? '')) === 'COMPONENT';
        $hasDivision = (int)($header['division_id'] ?? 0) > 0;
        $isWarehouseMaterial = !$isComponent
            && strtoupper((string)($header['location_scope'] ?? '')) === 'WAREHOUSE';
        $canInlineRecon = $isComponent
            ? ($hasDivision && $reconPermissions['component'])
            : ($hasDivision ? $reconPermissions['material'] : ($isWarehouseMaterial && $reconPermissions['warehouse']));
        $mutationCsrf = $this->inventory_control_mutation_csrf();

        $this->render('inventory/stock_deficit_detail', [
            'page_title' => 'Rincian Defisit Stok',
            'active_menu' => 'inventory.stock.deficit',
            'detail' => $detail,
            'can_inline_recon' => $canInlineRecon,
            'can_write_off' => $this->is_superadmin(),
            'write_off_schema_ready' => $this->Inventory_control_model->deficit_write_off_schema_ready(),
            'inventory_control_mutation_csrf_name' => $mutationCsrf['name'],
            'inventory_control_mutation_csrf_value' => $mutationCsrf['value'],
        ]);
    }

    /**
     * Administrative closure is deliberately separate from VOID and from a
     * physical reconciliation. It records that a historical deficit will no
     * longer be settled because the item is discontinued or otherwise cannot
     * return to the operational stock flow.
     */
    public function deficit_write_off($id)
    {
        $this->require_permission(self::PAGE_DEFICIT, 'view');
        if (!$this->is_superadmin()) {
            show_error('Hanya Superadmin yang dapat menutup administratif defisit stok.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_inventory_control_mutation_csrf()) {
            return;
        }

        $reason = strtoupper(trim((string)$this->input->post('written_off_reason_code', true)));
        $notes = trim((string)$this->input->post('written_off_notes', true));
        $confirmation = strtoupper(trim((string)$this->input->post('confirmation', true)));
        $allowedReasons = ['DISCONTINUED', 'HISTORICAL_CUTOFF', 'UNRECOVERABLE', 'OTHER'];
        if (!in_array($reason, $allowedReasons, true) || $notes === '' || $confirmation !== 'TUTUP') {
            $this->session->set_flashdata('error', 'Pilih alasan, isi catatan, lalu ketik TUTUP untuk menutup administratif defisit.');
            redirect('inventory/stock/deficits/detail/' . (int)$id);
            return;
        }

        $result = $this->Inventory_control_model->write_off_deficit_group(
            (int)$id,
            (int)($this->current_user['id'] ?? 0),
            $reason,
            $notes
        );
        $this->session->set_flashdata(
            !empty($result['ok']) ? 'success' : 'error',
            (string)($result['message'] ?? (!empty($result['ok']) ? 'Defisit ditutup administratif.' : 'Gagal menutup administratif defisit.'))
        );
        redirect('inventory/stock/deficits/detail/' . (int)$id);
    }

    /**
     * Direct deficit reconciliation posts through the existing physical-count
     * writers, so an operator must have both recon and adjustment authority.
     */
    private function deficit_recon_permissions(): array
    {
        $isSuperadmin = $this->is_superadmin();
        $canMaterial = $isSuperadmin || (
            $this->can('inventory.stock.opname.division.index', 'create')
            && $this->can('purchase.stock.adjustment.division.index', 'create')
        );
        $canWarehouse = $isSuperadmin || (
            $this->can('purchase.stock.adjustment.warehouse.index', 'create')
            && $this->can('purchase.stock.adjustment.warehouse.index', 'edit')
        );
        $canComponentDailyRecon = $isSuperadmin
            || $this->can('production.component.daily.recon.index', 'create')
            || $this->can('production.component.daily.index', 'create');

        return [
            'material' => $canMaterial,
            'warehouse' => $canWarehouse,
            'component' => $canComponentDailyRecon
                && ($isSuperadmin || $this->can('production.component.adjustment.index', 'create')),
        ];
    }

    public function periods()
    {
        $this->require_permission(self::PAGE_PERIOD, 'view');
        $filters = $this->period_filters();
        $perPage = $this->per_page((int)($this->input->get('per_page', true) ?: 50));
        $page = max(1, (int)($this->input->get('page', true) ?: 1));
        $total = $this->Inventory_control_model->count_periods($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $mutationCsrf = $this->inventory_control_mutation_csrf();

        $this->render('inventory/stock_period_index', [
            'page_title' => 'Tutup Periode Stok',
            'active_menu' => 'inventory.stock.period',
            'filters' => $filters,
            'summary' => $this->Inventory_control_model->period_summary($filters),
            'rows' => $this->Inventory_control_model->list_periods($filters, $perPage, ($page - 1) * $perPage),
            'current_health' => $this->Inventory_control_model->inventory_health_summary((string)($filters['month_to'] ?? date('Y-m-01'))),
            'can_create' => $this->can(self::PAGE_PERIOD, 'create'),
            'can_edit' => $this->can(self::PAGE_PERIOD, 'edit'),
            'inventory_control_mutation_csrf_name' => $mutationCsrf['name'],
            'inventory_control_mutation_csrf_value' => $mutationCsrf['value'],
            'pg' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * Read-only current-period queue for stock, lot, and value mismatches.
     * Repairs stay in the established recon pages so this screen cannot write
     * a hidden stock correction by accident.
     */
    public function health()
    {
        $this->require_permission(self::PAGE_HEALTH, 'view');
        $filters = $this->health_filters();
        $perPage = $this->per_page((int)($this->input->get('per_page', true) ?: 50));
        $page = max(1, (int)($this->input->get('page', true) ?: 1));
        $total = $this->Inventory_control_model->count_inventory_health_rows($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);

        $this->render('inventory/stock_health_index', [
            'page_title' => 'Kesehatan Stok Aktif',
            'active_menu' => 'inventory.stock.health',
            'filters' => $filters,
            'summary' => $this->Inventory_control_model->inventory_health_summary((string)$filters['month']),
            'rows' => $this->Inventory_control_model->list_inventory_health_rows($filters, $perPage, ($page - 1) * $perPage),
            'divisions' => $this->active_divisions(),
            'pg' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * Read-only operator view for the active-month integrity audit. The audit
     * is intentionally opt-in because its checks inspect several transaction
     * sources and should not make an ordinary page load slow.
     */
    public function integrity_audit()
    {
        $this->require_permission(self::PAGE_INTEGRITY_AUDIT, 'view');

        $month = $this->normalize_month((string)$this->input->get('month', true)) ?? date('Y-m-01');
        $limit = max(1, min(50, (int)($this->input->get('limit', true) ?: 5)));
        $runAudit = (string)$this->input->get('run', true) === '1';
        $audit = null;
        $auditError = '';

        if ($runAudit) {
            try {
                $this->load->library('InventoryActiveMonthIntegrityAudit');
                $audit = $this->inventoryactivemonthintegrityaudit->run([
                    'month' => $month,
                    'limit' => $limit,
                ]);
                $checks = (array)($audit['checks'] ?? []);
                usort($checks, static function (array $left, array $right): int {
                    $rank = ['ERROR' => 0, 'WARNING' => 1, 'INFO' => 2, 'PASS' => 3];
                    $leftRank = $rank[strtoupper((string)($left['status'] ?? 'PASS'))] ?? 3;
                    $rightRank = $rank[strtoupper((string)($right['status'] ?? 'PASS'))] ?? 3;
                    if ($leftRank !== $rightRank) {
                        return $leftRank <=> $rightRank;
                    }
                    return (int)($right['issue_count'] ?? 0) <=> (int)($left['issue_count'] ?? 0);
                });
                $audit['checks'] = $checks;
            } catch (\Throwable $exception) {
                log_message('error', 'Inventory integrity audit UI failed: ' . $exception->getMessage());
                $auditError = 'Audit belum dapat dijalankan. Tidak ada data yang diubah. Periksa log server atau jalankan audit CLI untuk detail teknis.';
            }
        }

        $this->render('inventory/stock_integrity_audit_index', [
            'page_title' => 'Audit Integritas Stok',
            'active_menu' => 'inventory.stock.integrity_audit',
            'month' => $month,
            'limit' => $limit,
            'run_audit' => $runAudit,
            'audit' => $audit,
            'audit_error' => $auditError,
        ]);
    }

    /**
     * A value-only reconciliation is deliberately separate from physical
     * counting. It can be opened from one exact Health row and never changes
     * quantity.
     */
    public function value_reconciliation()
    {
        $this->require_permission(self::PAGE_VALUE_RECON, 'view');
        $this->load->library('InventoryValueReconciliationService');

        $contextInput = $this->value_reconciliation_context_input('get');
        $context = [];
        if (!empty($contextInput['monthly_stock_id'])) {
            $context = $this->inventoryvaluereconciliationservice->context($contextInput);
        }
        $month = (string)($contextInput['month'] ?? date('Y-m-01'));
        $candidateFilters = [];
        $valueCandidates = [];
        if (empty($contextInput['monthly_stock_id'])) {
            // The menu must be useful on its own: show only value-only
            // findings that can safely be handled by this workspace.
            $candidateFilters = $this->health_filters();
            $candidateFilters['month'] = $month;
            $candidateRows = $this->Inventory_control_model->list_inventory_health_rows($candidateFilters, 100, 0);
            $valueCandidates = array_values(array_filter($candidateRows, static function (array $row): bool {
                return (string)($row['issue_type'] ?? '') === 'SELISIH_NILAI'
                    && (int)($row['monthly_stock_id'] ?? 0) > 0;
            }));
        }
        $hppSuggestions = [];
        if (!empty($context['ok']) && strtoupper((string)($contextInput['stock_domain'] ?? '')) === 'COMPONENT') {
            $hppSuggestions = $this->Production_model->component_hpp_suggestions([
                'component_id' => (int)($contextInput['component_id'] ?? 0),
                'uom_id' => (int)($contextInput['uom_id'] ?? 0),
                'location_type' => (string)($contextInput['location_type'] ?? ''),
                'division_id' => (int)($contextInput['division_id'] ?? 0),
                'stock' => is_array($context['stock'] ?? null) ? $context['stock'] : [],
                'lots' => is_array($context['lots'] ?? null) ? $context['lots'] : [],
            ]);
        }
        $mutationCsrf = $this->inventory_control_mutation_csrf();

        $this->render('inventory/stock_value_reconciliation_index', [
            'page_title' => 'Koreksi Nilai Stok',
            'active_menu' => 'inventory.stock.value_reconciliation',
            'context_input' => $contextInput,
            'context' => $context,
            'hpp_suggestions' => $hppSuggestions,
            'records' => $this->inventoryvaluereconciliationservice->listRecords($month),
            'value_candidates' => $valueCandidates,
            'candidate_filters' => $candidateFilters,
            'schema_ready' => $this->inventoryvaluereconciliationservice->isReady(),
            'can_post' => $this->is_superadmin(),
            'inventory_control_mutation_csrf_name' => $mutationCsrf['name'],
            'inventory_control_mutation_csrf_value' => $mutationCsrf['value'],
            'health_url' => site_url('inventory/stock/health') . '?' . http_build_query([
                'month' => substr($month, 0, 7),
            ]),
        ]);
    }

    public function value_reconciliation_post()
    {
        $this->require_permission(self::PAGE_VALUE_RECON, 'view');
        if (!$this->is_superadmin()) {
            show_error('Hanya Superadmin yang dapat memposting koreksi nilai stok.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_inventory_control_mutation_csrf()) {
            return;
        }

        $this->load->library('InventoryValueReconciliationService');
        $payload = $this->value_reconciliation_context_input('post');
        foreach (['resolution_mode', 'reason', 'notes', 'confirmation', 'manual_total_value', 'manual_unit_cost'] as $field) {
            $payload[$field] = $this->input->post($field, true);
        }
        $result = $this->inventoryvaluereconciliationservice->post(
            $payload,
            (int)($this->current_user['id'] ?? 0)
        );
        $this->session->set_flashdata(
            !empty($result['ok']) ? 'success' : 'error',
            !empty($result['ok'])
                ? 'Koreksi nilai ' . (string)($result['revaluation_no'] ?? '') . ' berhasil diposting. Jumlah stok tidak berubah.'
                : (string)($result['message'] ?? 'Koreksi nilai stok gagal diposting.')
        );

        redirect('inventory/stock/value-reconciliation?' . http_build_query($this->value_reconciliation_redirect_query($payload)));
    }

    public function value_reconciliation_void()
    {
        $this->require_permission(self::PAGE_VALUE_RECON, 'view');
        if (!$this->is_superadmin()) {
            show_error('Hanya Superadmin yang dapat membatalkan koreksi nilai stok.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_inventory_control_mutation_csrf()) {
            return;
        }

        $this->load->library('InventoryValueReconciliationService');
        $revaluationId = (int)$this->input->post('revaluation_id', true);
        $voidNotes = trim((string)$this->input->post('void_notes', true));
        $result = $this->inventoryvaluereconciliationservice->voidRecord(
            $revaluationId,
            (int)($this->current_user['id'] ?? 0),
            $voidNotes
        );
        $this->session->set_flashdata(
            !empty($result['ok']) ? 'success' : 'error',
            !empty($result['ok'])
                ? 'Koreksi nilai ' . (string)($result['revaluation_no'] ?? '') . ' berhasil di-VOID. Nilai sebelum dokumen dipulihkan dan jumlah stok tidak berubah.'
                : (string)($result['message'] ?? 'VOID koreksi nilai gagal.')
        );

        $month = $this->normalize_month((string)$this->input->post('month', true)) ?? date('Y-m-01');
        redirect('inventory/stock/value-reconciliation?month=' . rawurlencode($month));
    }

    public function period_detail($id)
    {
        $this->require_permission(self::PAGE_PERIOD, 'view');
        $period = $this->Inventory_control_model->get_period((int)$id);
        if ($period === null) {
            show_error('Periode stok tidak ditemukan.', 404, 'Periode Stok Tidak Ditemukan');
            return;
        }
        $this->load->library('InventoryCutoffService');
        $cutoffPosting = $this->inventorycutoffservice->preflight($period);
        $mutationCsrf = $this->inventory_control_mutation_csrf();

        $this->render('inventory/stock_period_detail', [
            'page_title' => 'Rincian Tutup Periode Stok',
            'active_menu' => 'inventory.stock.period',
            'period' => $period,
            'preflight' => $this->Inventory_control_model->period_close_preflight(
                (string)($period['stock_domain'] ?? ''),
                (string)($period['period_month'] ?? '')
            ),
            'cutoff_preview' => $this->Inventory_control_model->period_cutoff_preview(
                (string)($period['stock_domain'] ?? ''),
                (string)($period['period_month'] ?? '')
            ),
            'cutoff_posting' => $cutoffPosting,
            'cutoff_schema_ready' => $this->inventorycutoffservice->isReady(),
            'cutoff_runs' => $this->Inventory_control_model->list_cutoff_runs((int)($period['id'] ?? 0)),
            'can_edit' => $this->can(self::PAGE_PERIOD, 'edit'),
            'can_post_cutoff' => $this->is_superadmin(),
            'inventory_control_mutation_csrf_name' => $mutationCsrf['name'],
            'inventory_control_mutation_csrf_value' => $mutationCsrf['value'],
            'health_url' => site_url('inventory/stock/health') . '?' . http_build_query([
                'month' => substr((string)($period['period_month'] ?? ''), 0, 7),
                'stock_domain' => strtoupper((string)($period['stock_domain'] ?? '')),
            ]),
        ]);
    }

    public function period_open()
    {
        $this->require_permission(self::PAGE_PERIOD, 'create');
        if (!$this->require_inventory_control_mutation_csrf()) {
            return;
        }
        $month = $this->normalize_month((string)$this->input->post('period_month', true));
        $domain = strtoupper(trim((string)$this->input->post('stock_domain', true)));
        $notes = trim((string)$this->input->post('notes', true));
        if ($month === null || !in_array($domain, ['MATERIAL', 'COMPONENT', 'BOTH'], true)) {
            $this->session->set_flashdata('error', 'Bulan dan domain periode wajib dipilih dengan benar.');
            redirect('inventory/stock/periods');
            return;
        }

        $this->load->library('InventoryPeriodGuard');
        $domains = $domain === 'BOTH' ? ['MATERIAL', 'COMPONENT'] : [$domain];
        $actorId = (int)($this->current_user['id'] ?? 0);
        $this->db->trans_begin();
        try {
            foreach ($domains as $targetDomain) {
                $result = $this->inventoryperiodguard->ensureOpen($targetDomain, $month, $actorId, $notes);
                if (!($result['ok'] ?? false)) {
                    throw new RuntimeException((string)($result['message'] ?? 'Gagal menyiapkan periode stok.'));
                }
            }
            $this->db->trans_commit();
            $this->session->set_flashdata('success', 'Periode stok ' . date('m/Y', strtotime($month)) . ' sudah disiapkan.');
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'inventory period open failed: ' . $e->getMessage());
            $this->session->set_flashdata('error', $e->getMessage());
        }
        redirect('inventory/stock/periods?month_from=' . rawurlencode($month) . '&month_to=' . rawurlencode($month));
    }

    public function period_close($id)
    {
        $this->require_permission(self::PAGE_PERIOD, 'edit');
        $period = $this->Inventory_control_model->get_period((int)$id);
        if ($period === null) {
            show_error('Periode stok tidak ditemukan.', 404, 'Periode Stok Tidak Ditemukan');
            return;
        }
        $this->session->set_flashdata('error', 'Kunci tanpa posting tidak lagi digunakan. Pakai Posting Cut-off Resmi agar opname dan stok awal bulan berikutnya terbentuk sebelum periode dikunci.');
        redirect('inventory/stock/periods/detail/' . (int)$id);
    }

    public function period_cutoff_post($id)
    {
        $this->require_permission(self::PAGE_PERIOD, 'edit');
        if (!$this->is_superadmin()) {
            show_error('Hanya Superadmin yang dapat memposting cut-off stok resmi.', 403, 'Akses Ditolak');
            return;
        }
        if (!$this->require_inventory_control_mutation_csrf()) {
            return;
        }

        $period = $this->Inventory_control_model->get_period((int)$id);
        if ($period === null) {
            show_error('Periode stok tidak ditemukan.', 404, 'Periode Stok Tidak Ditemukan');
            return;
        }

        $confirmation = strtoupper(trim((string)$this->input->post('confirmation', true)));
        $notes = trim((string)$this->input->post('notes', true));
        if ($confirmation !== 'POST CUT-OFF' || $notes === '') {
            $this->session->set_flashdata('error', 'Isi catatan resmi lalu ketik POST CUT-OFF untuk menjalankan pembentukan opening dan penguncian periode.');
            redirect('inventory/stock/periods/detail/' . (int)$id);
            return;
        }

        $this->load->library('InventoryCutoffService');
        $result = $this->inventorycutoffservice->post(
            $period,
            (int)($this->current_user['id'] ?? 0),
            (string)$this->input->ip_address(),
            $notes,
            (string)$this->input->post('acknowledge_warnings', true) === '1'
        );
        $this->session->set_flashdata(
            !empty($result['ok']) ? 'success' : 'error',
            (string)($result['message'] ?? (!empty($result['ok']) ? 'Cut-off stok resmi berhasil.' : 'Cut-off stok resmi gagal.'))
        );
        redirect('inventory/stock/periods/detail/' . (int)$id);
    }

    public function period_reopen($id)
    {
        $this->require_permission(self::PAGE_PERIOD, 'edit');
        if (!$this->require_inventory_control_mutation_csrf()) {
            return;
        }
        $period = $this->Inventory_control_model->get_period((int)$id);
        if ($period === null) {
            show_error('Periode stok tidak ditemukan.', 404, 'Periode Stok Tidak Ditemukan');
            return;
        }
        $confirmation = strtoupper(trim((string)$this->input->post('confirmation', true)));
        $notes = trim((string)$this->input->post('notes', true));
        if ($confirmation !== 'BUKA' || $notes === '') {
            $this->session->set_flashdata('error', 'Ketik BUKA dan isi alasan resmi sebelum membuka kembali periode.');
            redirect('inventory/stock/periods/detail/' . (int)$id);
            return;
        }

        $this->load->library('InventoryPeriodGuard');
        $result = $this->inventoryperiodguard->reopenPeriod(
            (string)($period['stock_domain'] ?? ''),
            (string)($period['period_month'] ?? ''),
            (int)($this->current_user['id'] ?? 0),
            $notes
        );
        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', (string)($result['message'] ?? (($result['ok'] ?? false) ? 'Periode stok dibuka kembali.' : 'Gagal membuka kembali periode stok.')));
        redirect('inventory/stock/periods/detail/' . (int)$id);
    }

    private function inventory_control_mutation_csrf(): array
    {
        $name = self::INVENTORY_CONTROL_MUTATION_CSRF_NAME;
        $token = (string)$this->session->userdata($name);
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata($name, $token);
        }

        return [
            'name' => $name,
            'value' => $token,
        ];
    }

    private function require_inventory_control_mutation_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_inventory_control_mutation(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->post(self::INVENTORY_CONTROL_MUTATION_CSRF_NAME, true);
        $sessionToken = (string)$this->session->userdata(self::INVENTORY_CONTROL_MUTATION_CSRF_NAME);
        if (
            preg_match('/\A[0-9a-fA-F]{64}\z/D', $sessionToken) !== 1
            || preg_match('/\A[0-9a-fA-F]{64}\z/D', $providedToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_inventory_control_mutation(403, 'Permintaan mutasi inventory tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_inventory_control_mutation(int $statusCode, string $message): void
    {
        if ($this->input->is_ajax_request()) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            $this->output
                ->set_status_header($statusCode)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok' => false,
                    'message' => $message,
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            $this->output->_display();
            exit;
        }

        show_error(
            $message,
            $statusCode,
            $statusCode === 405 ? 'Metode Tidak Diizinkan' : 'Akses Ditolak'
        );
    }

    private function deficit_filters(): array
    {
        // An open deficit can originate in a previous month and remain
        // relevant today. Keep the default date range empty so the operator
        // sees the current outstanding obligation, not only this month's row.
        $dateFrom = $this->normalize_date((string)$this->input->get('date_from', true));
        $dateTo = $this->normalize_date((string)$this->input->get('date_to', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $domain = strtoupper(trim((string)$this->input->get('stock_domain', true)));
        $locationScope = strtoupper(trim((string)$this->input->get('location_scope', true)));
        $destinationType = strtoupper(trim((string)$this->input->get('destination_type', true)));
        $scopeDivisionId = $this->active_division_id();
        $divisionId = $scopeDivisionId ?? (int)$this->input->get('division_id', true);

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => in_array($status, ['OPEN', 'SETTLED', 'VOID', 'WRITTEN_OFF'], true) ? $status : 'OPEN',
            'stock_domain' => in_array($domain, ['MATERIAL', 'COMPONENT'], true) ? $domain : '',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId > 0 ? $divisionId : 0,
            'location_scope' => preg_match('/^[A-Z0-9_]{1,30}$/', $locationScope) ? $locationScope : '',
            'destination_type' => preg_match('/^[A-Z0-9_]{1,30}$/', $destinationType) ? $destinationType : '',
        ];
    }

    private function period_filters(): array
    {
        $current = date('Y-m-01');
        $monthFrom = $this->normalize_month((string)$this->input->get('month_from', true)) ?? date('Y-m-01', strtotime('-5 months'));
        $monthTo = $this->normalize_month((string)$this->input->get('month_to', true)) ?? $current;
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $domain = strtoupper(trim((string)$this->input->get('stock_domain', true)));

        return [
            'status' => in_array($status, ['OPEN', 'CLOSING', 'CLOSED', 'REOPENED'], true) ? $status : '',
            'stock_domain' => in_array($domain, ['MATERIAL', 'COMPONENT'], true) ? $domain : '',
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
        ];
    }

    private function health_filters(): array
    {
        $month = $this->normalize_month((string)$this->input->get('month', true)) ?? date('Y-m-01');
        $domain = strtoupper(trim((string)$this->input->get('stock_domain', true)));
        $scopeDivisionId = $this->active_division_id();
        $divisionId = $scopeDivisionId ?? (int)$this->input->get('division_id', true);

        return [
            'month' => $month,
            'stock_domain' => in_array($domain, ['MATERIAL', 'COMPONENT'], true) ? $domain : '',
            'division_id' => $divisionId > 0 ? $divisionId : 0,
            'q' => trim((string)$this->input->get('q', true)),
        ];
    }

    private function value_reconciliation_context_input(string $source): array
    {
        $read = function (string $key) use ($source) {
            return $source === 'post'
                ? $this->input->post($key, true)
                : $this->input->get($key, true);
        };

        return [
            'month' => $this->normalize_month((string)$read('month')) ?? date('Y-m-01'),
            'stock_domain' => strtoupper(trim((string)$read('stock_domain'))),
            'location_scope' => strtoupper(trim((string)$read('location_scope'))),
            'location_type' => strtoupper(trim((string)$read('location_type'))),
            'division_id' => (int)$read('division_id'),
            'item_id' => (int)$read('item_id'),
            'material_id' => (int)$read('material_id'),
            'component_id' => (int)$read('component_id'),
            'buy_uom_id' => (int)$read('buy_uom_id'),
            'uom_id' => (int)$read('uom_id'),
            'profile_key' => trim((string)$read('profile_key')),
            'monthly_stock_id' => (int)$read('monthly_stock_id'),
        ];
    }

    private function value_reconciliation_redirect_query(array $payload): array
    {
        $keys = [
            'month', 'stock_domain', 'location_scope', 'location_type', 'division_id',
            'item_id', 'material_id', 'component_id', 'buy_uom_id', 'uom_id',
            'profile_key', 'monthly_stock_id',
        ];
        $query = [];
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== 0 && $value !== '0') {
                $query[$key] = $value;
            }
        }
        if (empty($query['month'])) {
            $query['month'] = date('Y-m-01');
        }
        return $query;
    }

    private function active_divisions(): array
    {
        $scopeDivisionId = $this->active_division_id();
        $db = $this->db->select('id, code, name')->from('mst_operational_division')->where('is_active', 1);
        if ($scopeDivisionId !== null) {
            $db->where('id', $scopeDivisionId);
        }
        return $db->order_by('name', 'ASC')->get()->result_array();
    }

    private function per_page(int $value): int
    {
        return in_array($value, [25, 50, 100], true) ? $value : 50;
    }

    private function normalize_date(string $value): ?string
    {
        $value = trim($value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function normalize_month(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date ? $date->format('Y-m-01') : null;
    }
}
