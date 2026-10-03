<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos extends MY_Controller
{
    private const POS_TRANSACTION_CSRF_SESSION_KEY = 'pos_transaction_csrf';
    private const POS_TRANSACTION_CSRF_HEADER = 'X-Pos-Transaction-CSRF';
    private const POS_TRANSACTION_CSRF_CI_HEADER = 'X-Pos-Transaction-Csrf';
    private const POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY = 'pos_stock_commit_audit_csrf';
    private const POS_STOCK_COMMIT_AUDIT_CSRF_HEADER = 'X-Pos-Stock-Commit-CSRF';
    private const POS_STOCK_COMMIT_AUDIT_CSRF_CI_HEADER = 'X-Pos-Stock-Commit-Csrf';
    private const POS_ORDER_MONITOR_CSRF_SESSION_KEY = 'pos_order_monitor_csrf';
    private const POS_ORDER_MONITOR_CSRF_HEADER = 'X-Pos-Order-Monitor-CSRF';
    private const POS_ORDER_MONITOR_CSRF_CI_HEADER = 'X-Pos-Order-Monitor-Csrf';
    private const POS_AVAILABILITY_QUEUE_CSRF_SESSION_KEY = 'pos_availability_queue_csrf';
    private const POS_AVAILABILITY_QUEUE_CSRF_FORM_FIELD = 'pos_availability_queue_csrf';
    private const CUSTOMER_REVIEW_CSRF_SESSION_KEY = 'pos_customer_review_csrf';
    private const CUSTOMER_REVIEW_CSRF_HEADER = 'X-Pos-Review-Csrf';
    private const POS_PRINTER_GENERAL_CSRF_SESSION_KEY = 'pos_printer_general_csrf';
    private const POS_PRINTER_GENERAL_CSRF_FORM_FIELD = 'pos_printer_general_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_model');
        $this->load->model('Pos_report_model');
        $this->load->model('Cost_control_model');
        $this->load->model('Pos_order_monitor_model');
        $this->load->model('Pos_availability_queue_model');
        $this->load->model('Pos_print_model');
        $this->load->model('Pos_customer_review_model');
        $this->load->model('Pos_reservation_model');
        $this->load->model('Purchase_model');
        $this->load->model('Production_model');
        $this->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        $this->load->library('PosRuntimeJobService', null, 'posruntimejobservice');
    }

    public function members()
    {
        redirect('loyalty/members');
    }

    public function members_data()
    {
        $pageCode = $this->can('loyalty.member.index', 'view')
            ? 'loyalty.member.index'
            : 'pos.member.index';
        $this->require_permission($pageCode, 'view');
        $this->json_ok($this->Pos_model->member_rows($this->member_filters()));
    }

    public function member_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $pageCode = $this->can('loyalty.member.index', $id > 0 ? 'edit' : 'create')
            ? 'loyalty.member.index'
            : 'pos.member.index';
        $this->require_permission($pageCode, $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_member($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function member_toggle($id)
    {
        $pageCode = $this->can('loyalty.member.index', 'edit')
            ? 'loyalty.member.index'
            : 'pos.member.index';
        $this->require_permission($pageCode, 'edit');
        $result = $this->Pos_model->toggle_member((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function payment_methods()
    {
        $this->require_permission('pos.payment_method.index', 'view');
        $this->render('pos/payment_method_index', [
            'page_title' => 'Payment Method POS',
            'filters' => $this->payment_method_filters(),
            'filter_options' => $this->Pos_model->payment_method_filter_options(),
        ]);
    }

    public function stock_commit_audit()
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'view')
            ? 'pos.stock.commit.audit.index'
            : 'pos.stock.live.index';
        $this->require_permission($pageCode, 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $stockCommitAuditCsrfToken = $this->stock_commit_audit_csrf();
        if (!isset($this->posruntimejobservice) || !is_object($this->posruntimejobservice)) {
            $this->load->library('PosRuntimeJobService', null, 'posruntimejobservice');
        }

        $asOfDate = trim((string)$this->input->get('as_of_date', true));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $auditMonthFrom = date('Y-m-01', strtotime($asOfDate));
        $auditMonthTo = date('Y-m-t', strtotime($asOfDate));
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['material', 'component'], true)) {
            $tab = 'material';
        }

        $q = trim((string)$this->input->get('q', true));
        $divisionId = max(0, (int)$this->input->get('division_id', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['MATCH', 'MISMATCH'], true)) {
            $status = 'ALL';
        }
        $limit = max(10, min(200, (int)$this->input->get('limit', true)));
        if ($limit <= 0) {
            $limit = 25;
        }
        $page = max(1, (int)$this->input->get('page', true));
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $suspectFilter = strtoupper(trim((string)$this->input->get('suspect', true)));
        if ($suspectFilter === '') {
            $suspectFilter = 'ALL';
        }
        $locationType = strtoupper(trim((string)$this->input->get('location_type', true)));
        if ($locationType === '') {
            $locationType = 'ALL';
        }
        $componentType = strtoupper(trim((string)$this->input->get('type', true)));
        if ($componentType === '') {
            $componentType = 'ALL';
        }
        $dailyCheckFilter = strtoupper(trim((string)$this->input->get('daily_check', true)));
        if (!in_array($dailyCheckFilter, ['DRIFT', 'OK', 'UNKNOWN'], true)) {
            $dailyCheckFilter = 'ALL';
        }

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();

        $materialCompareRaw = $this->Purchase_model->list_division_material_stock_compare(
            $asOfDate,
            $q,
            $divisionId > 0 ? $divisionId : null,
            5000,
            $destinationFilter
        );
        $materialRowsAll = is_array($materialCompareRaw['rows'] ?? null) ? $materialCompareRaw['rows'] : [];
        $materialRowsAll = $this->sort_material_compare_rows($materialRowsAll);
        $this->load->library('PosOrderStockService', null, 'posorderstockservice');
        $voidMaterialLotAudit = $this->posorderstockservice->audit_void_material_lot_drift(
            $asOfDate,
            $materialRowsAll,
            50
        );
        $materialOptions = $this->build_material_compare_options($materialRowsAll);
        $materialRowsFiltered = $this->filter_material_compare_rows($materialRowsAll, [
            'status' => $status,
            'suspect' => $suspectFilter,
            'daily_check' => $dailyCheckFilter,
        ]);
        $materialFilteredSummary = $this->build_material_compare_summary($materialRowsFiltered);
        $materialPagination = $this->paginate_rows($materialRowsFiltered, $page, $limit);
        $materialCompare = [
            'as_of_date' => $materialCompareRaw['as_of_date'] ?? $asOfDate,
            'rows' => $materialPagination['rows'],
            'summary' => $materialFilteredSummary,
            'summary_all' => is_array($materialCompareRaw['summary'] ?? null) ? $materialCompareRaw['summary'] : $materialFilteredSummary,
            'options' => $materialOptions,
            'pagination' => $materialPagination['pagination'],
            'filters' => [
                'q' => $q,
                'division_id' => $divisionId,
                'destination' => $destinationFilter,
                'status' => $status,
                'suspect' => $suspectFilter,
                'daily_check' => $dailyCheckFilter,
                'limit' => $limit,
                'page' => $materialPagination['pagination']['current_page'],
            ],
        ];
        $domainAudit = $this->Purchase_model->production_domain_root_cause_audit([
            'limit' => 20,
            'active_only' => true,
        ]);
        $componentCompareRaw = $this->Production_model->component_reconcile_rows([
            'as_of_date' => $asOfDate,
            'q' => $q,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'type' => $componentType === 'ALL' ? '' : $componentType,
        ], 5000);
        $componentRowsAll = is_array($componentCompareRaw['rows'] ?? null) ? $componentCompareRaw['rows'] : [];
        $componentRowsAll = $this->sort_component_compare_rows($componentRowsAll);
        $componentOptions = $this->build_component_compare_options($componentRowsAll);
        $componentRowsFiltered = $this->filter_component_compare_rows($componentRowsAll, [
            'status' => $status,
            'daily_check' => $dailyCheckFilter,
        ]);
        $componentFilteredSummary = $this->build_component_compare_summary($componentRowsFiltered);
        $componentPagination = $this->paginate_rows($componentRowsFiltered, $page, $limit);
        $componentCompare = [
            'as_of_date' => $componentCompareRaw['as_of_date'] ?? $asOfDate,
            'rows' => $componentPagination['rows'],
            'summary' => $componentFilteredSummary,
            'summary_all' => $this->build_component_compare_summary($componentRowsAll),
            'options' => $componentOptions,
            'pagination' => $componentPagination['pagination'],
            'filters' => [
                'q' => $q,
                'division_id' => $divisionId,
                'location_type' => $locationType,
                'type' => $componentType,
                'status' => $status,
                'daily_check' => $dailyCheckFilter,
                'limit' => $limit,
                'page' => $componentPagination['pagination']['current_page'],
            ],
        ];

        $failedJobs = $this->posruntimejobservice->failed_jobs(['limit' => 15]);
        $activeJobs = $this->posruntimejobservice->active_jobs(['limit' => 15]);
        $failedCommitSnapshots = $this->posruntimejobservice->failed_commit_snapshots([
            'limit' => 20,
            'q' => $q,
            'date_from' => $auditMonthFrom,
            'date_to' => $auditMonthTo,
        ]);

        $this->render('pos/stock_commit_audit_index', [
            'page_title' => 'Audit Commit Stok POS',
            'active_menu' => 'pos.stock.commit.audit',
            'pos_master_tab_active' => 'stock-commit-audit',
            'audit_tab' => $tab,
            'as_of_date' => $asOfDate,
            'audit_month_from' => $auditMonthFrom,
            'audit_month_to' => $auditMonthTo,
            'material_compare' => $materialCompare,
            'void_material_lot_audit' => $voidMaterialLotAudit,
            'domain_audit' => !empty($domainAudit['ok']) ? (array)($domainAudit['data'] ?? []) : ['summary' => [], 'rows' => []],
            'component_compare' => $componentCompare,
            'division_options' => $divisionOptions,
            'failed_jobs' => !empty($failedJobs['ok']) ? (array)($failedJobs['rows'] ?? []) : [],
            'active_jobs' => !empty($activeJobs['ok']) ? (array)($activeJobs['rows'] ?? []) : [],
            'failed_commit_snapshots' => !empty($failedCommitSnapshots['ok']) ? (array)($failedCommitSnapshots['rows'] ?? []) : [],
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
            'stock_commit_audit_csrf_token' => $stockCommitAuditCsrfToken,
        ]);
    }

    public function stock_commit_audit_repair_material_mismatches()
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : 'pos.stock.live.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_stock_commit_audit_csrf()) {
            return;
        }
        $this->reject_legacy_stock_repair();
        return;
        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $asOfDate = trim((string)($payload['as_of_date'] ?? ''));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $q = trim((string)($payload['q'] ?? ''));
        $divisionId = max(0, (int)($payload['division_id'] ?? 0));
        $destination = strtoupper(trim((string)($payload['destination'] ?? 'ALL')));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $suspect = strtoupper(trim((string)($payload['suspect'] ?? 'ALL')));

        $compare = $this->Purchase_model->list_division_material_stock_compare(
            $asOfDate,
            $q,
            $divisionId > 0 ? $divisionId : null,
            5000,
            $destination
        );
        $rows = $this->filter_material_compare_rows((array)($compare['rows'] ?? []), [
            'status' => 'MISMATCH',
            'suspect' => $suspect,
        ]);
        if (empty($rows)) {
            $this->json_ok([
                'message' => 'Tidak ada mismatch bahan baku yang perlu direpair.',
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'results' => [],
            ]);
            return;
        }

        $results = [];
        $successCount = 0;
        foreach ($rows as $row) {
            $repair = $this->Purchase_model->repair_division_material_reconcile($asOfDate, [
                'division_id' => (int)($row['division_id'] ?? 0),
                'item_id' => (int)($row['item_id'] ?? 0),
                'material_id' => (int)($row['material_id'] ?? 0),
                'destination' => (string)($row['destination_group'] ?? 'ALL'),
            ]);
            if (!empty($repair['ok'])) {
                $successCount++;
            }
            $results[] = [
                'label' => trim((string)($row['material_name'] ?? '-')) . ' @ ' . trim((string)($row['division_name'] ?? '-')),
                'identity' => [
                    'division_id' => (int)($row['division_id'] ?? 0),
                    'item_id' => (int)($row['item_id'] ?? 0),
                    'material_id' => (int)($row['material_id'] ?? 0),
                    'destination' => (string)($row['destination_group'] ?? 'ALL'),
                ],
                'result' => $repair,
            ];
        }

        $this->json_ok([
            'message' => 'Batch repair mismatch bahan baku selesai dijalankan.',
            'processed_count' => count($rows),
            'success_count' => $successCount,
            'failed_count' => count($rows) - $successCount,
            'results' => $results,
        ]);
    }

    public function stock_commit_audit_repair_component_mismatches()
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : 'pos.stock.live.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_stock_commit_audit_csrf()) {
            return;
        }
        $this->reject_legacy_stock_repair();
        return;
        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $asOfDate = trim((string)($payload['as_of_date'] ?? ''));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $q = trim((string)($payload['q'] ?? ''));
        $divisionId = max(0, (int)($payload['division_id'] ?? 0));
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? 'ALL')));
        if ($locationType === '') {
            $locationType = 'ALL';
        }
        $componentType = strtoupper(trim((string)($payload['type'] ?? 'ALL')));
        if ($componentType === '') {
            $componentType = 'ALL';
        }

        $compare = $this->Production_model->component_reconcile_rows([
            'as_of_date' => $asOfDate,
            'q' => $q,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'type' => $componentType === 'ALL' ? '' : $componentType,
        ], 5000);
        $rows = $this->filter_component_compare_rows((array)($compare['rows'] ?? []), [
            'status' => 'MISMATCH',
        ]);
        if (empty($rows)) {
            $this->json_ok([
                'message' => 'Tidak ada mismatch base/prepare yang perlu direpair.',
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'results' => [],
            ]);
            return;
        }

        $results = [];
        $successCount = 0;
        foreach ($rows as $row) {
            $repair = $this->Production_model->repair_component_reconcile([
                'location_type' => (string)($row['location_type'] ?? ''),
                'division_id' => array_key_exists('division_id', $row) && $row['division_id'] !== null && $row['division_id'] !== ''
                    ? (int)$row['division_id']
                    : null,
                'component_id' => (int)($row['component_id'] ?? 0),
                'uom_id' => (int)($row['uom_id'] ?? 0),
            ]);
            if (!empty($repair['ok'])) {
                $successCount++;
            }
            $results[] = [
                'label' => trim((string)($row['component_name'] ?? '-')) . ' @ ' . trim((string)($row['division_name'] ?? '-')),
                'identity' => [
                    'location_type' => (string)($row['location_type'] ?? ''),
                    'division_id' => array_key_exists('division_id', $row) && $row['division_id'] !== null && $row['division_id'] !== ''
                        ? (int)$row['division_id']
                        : null,
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                ],
                'result' => $repair,
            ];
        }

        $this->json_ok([
            'message' => 'Batch repair mismatch base/prepare selesai dijalankan.',
            'processed_count' => count($rows),
            'success_count' => $successCount,
            'failed_count' => count($rows) - $successCount,
            'results' => $results,
        ]);
    }

    public function stock_commit_audit_repair_material_drift()
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : 'pos.stock.live.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_stock_commit_audit_csrf()) {
            return;
        }
        $this->reject_legacy_stock_repair();
        return;
        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $asOfDate = trim((string)($payload['as_of_date'] ?? ''));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $q = trim((string)($payload['q'] ?? ''));
        $divisionId = max(0, (int)($payload['division_id'] ?? 0));
        $destination = strtoupper(trim((string)($payload['destination'] ?? 'ALL')));
        if ($destination === '') {
            $destination = 'ALL';
        }

        $compare = $this->Purchase_model->list_division_material_stock_compare(
            $asOfDate,
            $q,
            $divisionId > 0 ? $divisionId : null,
            5000,
            $destination
        );
        $rows = $this->filter_material_compare_rows((array)($compare['rows'] ?? []), [
            'daily_check' => 'DRIFT',
        ]);
        if (empty($rows)) {
            $this->json_ok([
                'message' => 'Tidak ada bahan baku dengan drift monthly stock yang perlu direpair.',
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'results' => [],
            ]);
            return;
        }

        $results = [];
        $successCount = 0;
        foreach ($rows as $row) {
            $repair = $this->Purchase_model->repair_material_monthly_stock_drift($asOfDate, [
                'division_id' => (int)($row['division_id'] ?? 0),
                'item_id' => (int)($row['item_id'] ?? 0),
                'material_id' => (int)($row['material_id'] ?? 0),
                'destination_group' => (string)($row['destination_group'] ?? 'REGULER'),
            ]);
            if (!empty($repair['ok'])) {
                $successCount++;
            }
            $results[] = [
                'label' => trim((string)($row['material_name'] ?? '-')) . ' @ ' . trim((string)($row['division_name'] ?? '-')),
                'result' => $repair,
            ];
        }

        $this->json_ok([
            'message' => 'Batch repair drift monthly stock bahan baku selesai.',
            'processed_count' => count($rows),
            'success_count' => $successCount,
            'failed_count' => count($rows) - $successCount,
            'results' => $results,
        ]);
    }

    public function stock_commit_audit_repair_component_drift()
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : 'pos.stock.live.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_stock_commit_audit_csrf()) {
            return;
        }
        $this->reject_legacy_stock_repair();
        return;
        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $asOfDate = trim((string)($payload['as_of_date'] ?? ''));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $q = trim((string)($payload['q'] ?? ''));
        $divisionId = max(0, (int)($payload['division_id'] ?? 0));
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? 'ALL')));
        if ($locationType === '') {
            $locationType = 'ALL';
        }
        $componentType = strtoupper(trim((string)($payload['type'] ?? 'ALL')));

        $compare = $this->Production_model->component_reconcile_rows([
            'as_of_date' => $asOfDate,
            'q' => $q,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'type' => $componentType === 'ALL' ? '' : $componentType,
        ], 5000);
        $rows = $this->filter_component_compare_rows((array)($compare['rows'] ?? []), [
            'daily_check' => 'DRIFT',
        ]);
        if (empty($rows)) {
            $this->json_ok([
                'message' => 'Tidak ada component dengan drift monthly stock yang perlu direpair.',
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'results' => [],
            ]);
            return;
        }

        $results = [];
        $successCount = 0;
        foreach ($rows as $row) {
            $repair = $this->Production_model->repair_component_monthly_stock_drift([
                'location_type' => (string)($row['location_type'] ?? ''),
                'division_id' => array_key_exists('division_id', $row) && $row['division_id'] !== null ? (int)$row['division_id'] : null,
                'component_id' => (int)($row['component_id'] ?? 0),
                'uom_id' => (int)($row['uom_id'] ?? 0),
            ]);
            if (!empty($repair['ok'])) {
                $successCount++;
            }
            $results[] = [
                'label' => trim((string)($row['component_name'] ?? '-')) . ' @ ' . trim((string)($row['division_name'] ?? '-')),
                'result' => $repair,
            ];
        }

        $this->json_ok([
            'message' => 'Batch repair drift monthly stock component selesai.',
            'processed_count' => count($rows),
            'success_count' => $successCount,
            'failed_count' => count($rows) - $successCount,
            'results' => $results,
        ]);
    }

    private function reject_legacy_stock_repair(): void
    {
        $this->output->set_status_header(410)->set_content_type('application/json')->set_output(json_encode([
            'ok' => false,
            'message' => 'Repair saldo langsung dinonaktifkan. Gunakan Daily Recon untuk qty fisik atau rekonsiliasi nilai ter-audit untuk HPP. Histori, lot, dan opening tidak diubah.',
        ]));
    }

    private function stock_commit_audit_csrf(): string
    {
        $token = (string)$this->session->userdata(self::POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_stock_commit_audit_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_stock_commit_audit_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI normalizes CGI/FastCGI names to title case (for example, ...-Csrf).
        // Keep the browser-facing contract above, but use that canonical name for
        // the request lookup so this guard is safe with exact-key input doubles too.
        $providedToken = trim((string)$this->input->get_request_header(self::POS_STOCK_COMMIT_AUDIT_CSRF_CI_HEADER, true));
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $providedToken) !== 1) {
            $this->reject_stock_commit_audit_csrf(403, 'Permintaan repair stock commit POS tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-fA-F]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_stock_commit_audit_csrf(403, 'Permintaan repair stock commit POS tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_stock_commit_audit_csrf(int $statusCode, string $message): void
    {
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
    }

    private function sort_material_compare_rows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            $leftMismatch = empty($left['is_match']) ? 1 : 0;
            $rightMismatch = empty($right['is_match']) ? 1 : 0;
            if ($leftMismatch !== $rightMismatch) {
                return $rightMismatch <=> $leftMismatch;
            }

            $leftDelta = max(
                abs((float)($left['delta_balance_vs_movement'] ?? 0)),
                abs((float)($left['delta_daily_vs_movement'] ?? 0)),
                abs((float)($left['delta_matrix_vs_movement'] ?? 0))
            );
            $rightDelta = max(
                abs((float)($right['delta_balance_vs_movement'] ?? 0)),
                abs((float)($right['delta_daily_vs_movement'] ?? 0)),
                abs((float)($right['delta_matrix_vs_movement'] ?? 0))
            );
            if (abs($leftDelta - $rightDelta) > 0.0001) {
                return $rightDelta <=> $leftDelta;
            }

            $cmp = strcasecmp((string)($left['division_name'] ?? ''), (string)($right['division_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string)($left['material_name'] ?? ''), (string)($right['material_name'] ?? ''));
        });

        return $rows;
    }

    private function filter_material_compare_rows(array $rows, array $filters): array
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $suspect = strtoupper(trim((string)($filters['suspect'] ?? 'ALL')));
        $dailyCheck = strtoupper(trim((string)($filters['daily_check'] ?? 'ALL')));

        return array_values(array_filter($rows, static function (array $row) use ($status, $suspect, $dailyCheck): bool {
            if ($status === 'MATCH' && empty($row['is_match'])) {
                return false;
            }
            if ($status === 'MISMATCH' && !empty($row['is_match'])) {
                return false;
            }
            if ($suspect !== 'ALL' && strtoupper((string)($row['suspect_table'] ?? '')) !== $suspect) {
                return false;
            }
            if ($dailyCheck !== 'ALL' && strtoupper((string)($row['daily_check_status'] ?? 'UNKNOWN')) !== $dailyCheck) {
                return false;
            }
            return true;
        }));
    }

    private function build_material_compare_summary(array $rows): array
    {
        $summary = [
            'total_rows' => count($rows),
            'match_rows' => 0,
            'mismatch_rows' => 0,
            'drift_rows' => 0,
        ];
        foreach ($rows as $row) {
            if (!empty($row['is_match'])) {
                $summary['match_rows']++;
            } else {
                $summary['mismatch_rows']++;
            }
            if (strtoupper((string)($row['daily_check_status'] ?? '')) === 'DRIFT') {
                $summary['drift_rows']++;
            }
        }

        return $summary;
    }

    private function build_material_compare_options(array $rows): array
    {
        $destinations = ['ALL' => 'Semua Tujuan'];
        $suspects = ['ALL' => 'Semua Status Audit'];
        foreach ($rows as $row) {
            $destinationGroup = strtoupper(trim((string)($row['destination_group'] ?? 'REGULER')));
            if ($destinationGroup !== '' && !isset($destinations[$destinationGroup])) {
                $destinations[$destinationGroup] = (string)($row['destination_name'] ?? $destinationGroup);
            }

            $suspect = strtoupper(trim((string)($row['suspect_table'] ?? 'MATCH')));
            if ($suspect !== '' && !isset($suspects[$suspect])) {
                $suspects[$suspect] = $this->format_material_suspect_label($suspect);
            }
        }

        return [
            'destinations' => $destinations,
            'suspects' => $suspects,
        ];
    }

    private function format_material_suspect_label(string $suspect): string
    {
        $map = [
            'MATCH' => 'Sinkron',
            'BALANCE' => 'Stok Divisi Drift',
            'DAILY' => 'Snapshot Harian Drift',
            'MOVEMENT_OR_SOURCE' => 'Movement / Sumber Drift',
            'MULTIPLE' => 'Multiple Drift',
        ];

        return $map[$suspect] ?? $suspect;
    }

    private function sort_component_compare_rows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            $leftMismatch = empty($left['is_match']) ? 1 : 0;
            $rightMismatch = empty($right['is_match']) ? 1 : 0;
            if ($leftMismatch !== $rightMismatch) {
                return $rightMismatch <=> $leftMismatch;
            }

            $leftDelta = max(
                abs((float)($left['delta_balance_daily'] ?? 0)),
                abs((float)($left['delta_balance_movement'] ?? 0)),
                abs((float)($left['delta_daily_movement'] ?? 0))
            );
            $rightDelta = max(
                abs((float)($right['delta_balance_daily'] ?? 0)),
                abs((float)($right['delta_balance_movement'] ?? 0)),
                abs((float)($right['delta_daily_movement'] ?? 0))
            );
            if (abs($leftDelta - $rightDelta) > 0.0001) {
                return $rightDelta <=> $leftDelta;
            }

            $cmp = strcasecmp((string)($left['division_name'] ?? ''), (string)($right['division_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string)($left['component_name'] ?? ''), (string)($right['component_name'] ?? ''));
        });

        return $rows;
    }

    private function filter_component_compare_rows(array $rows, array $filters): array
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $dailyCheck = strtoupper(trim((string)($filters['daily_check'] ?? 'ALL')));

        return array_values(array_filter($rows, static function (array $row) use ($status, $dailyCheck): bool {
            if ($status === 'MATCH' && empty($row['is_match'])) {
                return false;
            }
            if ($status === 'MISMATCH' && !empty($row['is_match'])) {
                return false;
            }
            if ($dailyCheck !== 'ALL' && strtoupper((string)($row['daily_check_status'] ?? 'UNKNOWN')) !== $dailyCheck) {
                return false;
            }
            return true;
        }));
    }

    private function build_component_compare_summary(array $rows): array
    {
        $matched = 0;
        $driftRows = 0;
        foreach ($rows as $row) {
            if (!empty($row['is_match'])) {
                $matched++;
            }
            if (strtoupper((string)($row['daily_check_status'] ?? '')) === 'DRIFT') {
                $driftRows++;
            }
        }

        return [
            'total' => count($rows),
            'matched' => $matched,
            'mismatched' => count($rows) - $matched,
            'drift_rows' => $driftRows,
        ];
    }

    private function build_component_compare_options(array $rows): array
    {
        $locations = ['ALL' => 'Semua Lokasi'];
        $types = ['ALL' => 'Semua Tipe'];
        foreach ($rows as $row) {
            $locationType = strtoupper(trim((string)($row['location_type'] ?? '')));
            if ($locationType !== '' && !isset($locations[$locationType])) {
                $locations[$locationType] = $locationType;
            }

            $componentType = strtoupper(trim((string)($row['component_type'] ?? '')));
            if ($componentType !== '' && !isset($types[$componentType])) {
                $types[$componentType] = $componentType;
            }
        }

        return [
            'locations' => $locations,
            'types' => $types,
        ];
    }

    private function paginate_rows(array $rows, int $page, int $limit): array
    {
        $totalRows = count($rows);
        $totalPages = max(1, (int)ceil($totalRows / max(1, $limit)));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * $limit;

        return [
            'rows' => array_slice($rows, $offset, $limit),
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $limit,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'from' => $totalRows > 0 ? ($offset + 1) : 0,
                'to' => min($offset + $limit, $totalRows),
            ],
        ];
    }

    public function deposits()
    {
        $this->require_permission('pos.deposit.index', 'view');
        $this->render('pos/deposit_index', [
            'page_title' => 'Deposit / DP POS',
            'filters' => $this->deposit_filters(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
        ]);
    }

    public function deposits_data()
    {
        $this->require_permission('pos.deposit.index', 'view');
        $this->json_ok($this->Pos_model->deposit_rows($this->deposit_filters()));
    }

    public function self_order()
    {
        redirect('pos/self-order/orders');
    }

    public function reservations()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $this->render('pos/reservation_index', [
            'page_title' => 'Reservasi Customer',
            'active_menu' => 'pos.reservation.index',
            'pos_master_tab_active' => 'reservation',
            'filters' => $this->reservation_filters(),
            'filter_options' => $this->Pos_reservation_model->page_options($this->current_actor_employee_id()),
            'schema_ready' => $this->Pos_reservation_model->schema_ready(),
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function reservations_data()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $this->json_ok($this->Pos_reservation_model->reservation_rows($this->reservation_filters()));
    }

    public function reservation_products_data()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $this->json_ok($this->Pos_reservation_model->reservation_product_rows($this->reservation_filters()));
    }

    public function reservation_detail($id)
    {
        $this->require_permission('pos.reservation.index', 'view');
        $reservation = $this->Pos_reservation_model->find_reservation((int)$id);
        if (!$reservation) {
            $this->json_error('Reservasi tidak ditemukan.', 404);
            return;
        }
        $this->json_ok(['reservation' => $reservation]);
    }

    public function reservation_catalog()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'category_id' => max(0, (int)$this->input->get('category_id', true)),
            'limit' => max(1, min(120, (int)$this->input->get('limit', true) ?: 32)),
        ];
        $this->json_ok(['rows' => $this->Pos_model->order_product_catalog($filters)]);
    }

    public function reservation_bundle_catalog()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'limit' => max(1, min(60, (int)$this->input->get('limit', true) ?: 24)),
        ];
        $this->json_ok(['rows' => $this->Pos_model->order_bundle_catalog($filters)]);
    }

    public function reservation_extra_options()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->json_ok(['groups' => $this->Pos_model->order_extra_options($productId)]);
    }

    public function reservation_member_search()
    {
        $this->require_permission('pos.reservation.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok(['rows' => $this->Pos_model->order_member_search($q, $limit)]);
    }

    public function reservation_save()
    {
        $this->require_permission('pos.reservation.index', 'view');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $id = max(0, (int)($payload['id'] ?? 0));
        $action = $id > 0 ? 'edit' : 'create';
        $this->require_permission('pos.reservation.index', $action);
        $result = $this->Pos_reservation_model->save_reservation(
            $payload,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan reservasi.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'reservation_no' => (string)($result['reservation_no'] ?? ''),
            'deposit' => (array)($result['deposit'] ?? []),
            'reservation' => (array)($result['reservation'] ?? []),
        ]);
    }

    public function reservation_deposit($id)
    {
        $this->require_permission('pos.reservation.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $result = $this->Pos_reservation_model->add_deposit(
            (int)$id,
            $payload,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menambahkan DP reservasi.'), 422);
            return;
        }
        $this->json_ok([
            'deposit' => (array)($result['deposit'] ?? []),
            'reservation' => (array)($result['reservation'] ?? []),
        ]);
    }

    public function reservation_verify($id)
    {
        $this->require_permission('pos.reservation.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $this->verify_reservation_and_respond(
            (int)$id,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id()
        );
    }

    /**
     * Returning a reservation deposit voids a financial receipt. Keep that
     * proof separate from ordinary POS refunds and bind it to both the exact
     * reservation and the close decision (reject versus cancel).
     */
    public function reservation_refund_step_up_verify()
    {
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $closeMode = strtoupper(trim((string)($payload['close_mode'] ?? '')));
        $stepUpAction = $this->reservation_deposit_refund_step_up_action($closeMode);
        if ($stepUpAction === null) {
            $this->json_error('Aksi verifikasi ulang reservasi tidak valid.', 422);
            return;
        }
        $this->require_permission('pos.reservation.index', $closeMode === 'CANCEL' ? 'delete' : 'edit');
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            $this->current_actor_user_id(),
            $stepUpAction,
            $payload['reservation_id'] ?? null,
            $payload['password'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    public function reservation_reject($id)
    {
        $this->require_permission('pos.reservation.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $refundDeposit = !empty($payload['refund_deposit']);
        if ($refundDeposit && !$this->consume_reservation_deposit_refund_step_up((int)$id, 'REJECT', $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_reservation_model->reject_reservation(
            (int)$id,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id(),
            trim((string)($payload['reason'] ?? '')),
            $refundDeposit
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menolak reservasi.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function reservation_cancel($id)
    {
        $this->require_permission('pos.reservation.index', 'delete');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $refundDeposit = !empty($payload['refund_deposit']);
        if ($refundDeposit && !$this->consume_reservation_deposit_refund_step_up((int)$id, 'CANCEL', $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_reservation_model->cancel_reservation(
            (int)$id,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id(),
            trim((string)($payload['reason'] ?? '')),
            $refundDeposit
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membatalkan reservasi.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function self_order_settings()
    {
        $this->require_permission('pos.self_order.index', 'view');

        if (strtoupper((string)$this->input->method()) === 'POST') {
            $this->require_permission('pos.self_order.index', 'edit');
            $result = $this->Pos_model->save_self_order_settings($this->input->post(null, false) ?: []);
            $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', (string)($result['message'] ?? (($result['ok'] ?? false) ? 'Pengaturan self order berhasil disimpan.' : 'Gagal menyimpan pengaturan self order.')));
            redirect('pos/self-order/settings');
            return;
        }

        $this->render('pos/self_order_settings', [
            'page_title' => 'Self Order POS',
            'active_menu' => 'pos.self_order.index',
            'settings' => $this->Pos_model->self_order_settings(),
            'qris_payment_method_options' => $this->Pos_model->self_order_qris_payment_method_options(),
        ]);
    }

    public function self_order_tables()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $this->render('pos/self_order_tables', [
            'page_title' => 'QR Meja Self Order',
            'active_menu' => 'pos.self_order.index',
            'filters' => $this->self_order_table_filters(),
            'settings' => $this->Pos_model->self_order_settings(),
        ]);
    }

    public function self_order_tables_data()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $this->json_ok($this->Pos_model->self_order_table_rows($this->self_order_table_filters()));
    }

    public function self_order_table_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.self_order.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_self_order_table($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan meja self order.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)($result['id'] ?? 0)]);
    }

    public function self_order_table_bulk_save()
    {
        $this->require_permission('pos.self_order.index', 'create');
        $payload = $this->request_payload();

        $prefix      = trim((string)($payload['prefix'] ?? ''));
        $from        = (int)($payload['from'] ?? 1);
        $to          = (int)($payload['to'] ?? 1);
        $capacity    = max(0, (int)($payload['capacity'] ?? 0));
        $sortStart   = max(0, (int)($payload['sort_order_start'] ?? 0));
        $isActive    = !isset($payload['is_active']) || (int)$payload['is_active'] === 1 ? 1 : 0;

        if ($prefix === '') { $this->json_error('Prefix nama meja wajib diisi.', 422); return; }
        if ($from < 1 || $to < $from) { $this->json_error('Rentang nomor tidak valid.', 422); return; }
        if ($to - $from >= 100) { $this->json_error('Maksimal 100 meja sekaligus.', 422); return; }

        $items = [];
        for ($i = $from; $i <= $to; $i++) {
            $items[] = [
                'nama_meja'  => $prefix . ' ' . $i,
                'qr_label'   => '',
                'capacity'   => $capacity,
                'sort_order' => $sortStart + ($i - $from),
                'is_active'  => $isActive,
            ];
        }

        $result = $this->Pos_model->bulk_save_self_order_tables($items);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan meja.'), 422);
            return;
        }
        $this->json_ok(['count' => (int)($result['count'] ?? 0)],
            (int)($result['count'] ?? 0) . ' meja berhasil dibuat.');
    }

    public function self_order_table_delete($id)
    {
        $this->require_permission('pos.self_order.index', 'delete');
        $result = $this->Pos_model->delete_self_order_table((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus meja self order.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

public function self_order_tables_print()
{
    $this->require_permission('pos.self_order.index', 'view');

    $rows = $this->Pos_model->self_order_print_rows();

    usort($rows, function ($a, $b) {
        return $a['id'] <=> $b['id'];
    });

    $settings = $this->Pos_model->self_order_settings();

    $this->load->view('pos/self_order_tables_print', [
        'title'    => 'Print QR Meja Self Order',
        'rows'     => $rows,
        'settings' => $settings,
    ]);
}
    public function self_order_orders()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $filters = $this->self_order_order_filters();
        $this->render('pos/self_order_orders', [
            'page_title' => 'Orderan Self Order',
            'active_menu' => 'pos.self_order.index',
            'filters' => $filters,
            'filter_options' => $this->Pos_model->self_order_order_filter_options(),
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function self_order_orders_data()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $this->json_ok($this->Pos_model->self_order_order_rows($this->self_order_order_filters()));
    }

    public function self_order_order_detail($id)
    {
        $this->require_permission('pos.self_order.index', 'view');
        $result = $this->Pos_model->find_self_order_order((int)$id);
        if (!$result) {
            $this->json_error('Order self order tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($result + [
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
        ]);
    }

    public function self_order_order_verify($id)
    {
        $this->require_permission('pos.self_order.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->verify_self_order_and_respond((int)$id, $this->current_actor_employee_id(), $payload);
    }

    public function self_order_order_reject($id)
    {
        $this->require_permission('pos.self_order.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $reason = trim((string)($payload['reason'] ?? ''));
        $result = $this->Pos_model->reject_self_order_order((int)$id, $this->current_actor_employee_id(), $reason);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menolak order self order.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'status' => (string)($result['status'] ?? 'REJECTED'),
            'reason' => (string)($result['reason'] ?? ''),
        ]);
    }

    public function online_food()
    {
        redirect('pos/online-food/orders');
    }

    public function online_food_orders()
    {
        $this->require_permission('pos.online_food.index', 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $filters = $this->self_order_order_filters();
        $this->render('pos/online_food_orders', [
            'page_title' => 'Orderan Online Food',
            'active_menu' => 'pos.online_food.index',
            'filters' => $filters,
            'filter_options' => $this->Pos_model->online_food_order_filter_options(),
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function online_food_orders_data()
    {
        $this->require_permission('pos.online_food.index', 'view');
        $this->json_ok($this->Pos_model->online_food_order_rows($this->self_order_order_filters()));
    }

    public function online_food_order_detail($id)
    {
        $this->require_permission('pos.online_food.index', 'view');
        $result = $this->Pos_model->find_online_food_order((int)$id);
        if (!$result) {
            $this->json_error('Order online food tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($result + [
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
        ]);
    }

    public function online_food_order_verify($id)
    {
        $this->require_permission('pos.online_food.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->verify_self_order_and_respond((int)$id, $this->current_actor_employee_id(), $payload, [
            'context_method' => 'online_food_verification_context',
            'label' => 'online food',
            'label_title' => 'Online Food',
            'event_source' => 'ONLINE_FOOD_VERIFY',
            'event_prefix' => 'ONLINE_FOOD',
        ]);
    }

    public function online_food_order_reject($id)
    {
        $this->require_permission('pos.online_food.index', 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $reason = trim((string)($payload['reason'] ?? ''));
        $result = $this->Pos_model->reject_online_food_order((int)$id, $this->current_actor_employee_id(), $reason);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menolak order online food.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'status' => (string)($result['status'] ?? 'REJECTED'),
            'reason' => (string)($result['reason'] ?? ''),
        ]);
    }

    public function online_food_settings()
    {
        $this->require_permission('pos.online_food.index', 'view');

        if (strtoupper((string)$this->input->method()) === 'POST') {
            $this->require_permission('pos.online_food.index', 'edit');
            $result = $this->Pos_model->save_online_food_settings($this->input->post(null, false) ?: []);
            $this->session->set_flashdata(
                ($result['ok'] ?? false) ? 'success' : 'error',
                (string)($result['message'] ?? (($result['ok'] ?? false) ? 'Pengaturan online food berhasil disimpan.' : 'Gagal menyimpan pengaturan online food.'))
            );
            redirect('pos/online-food/settings');
            return;
        }

        $this->render('pos/online_food_settings', [
            'page_title' => 'Online Food POS',
            'active_menu' => 'pos.online_food.index',
            'settings' => $this->Pos_model->online_food_settings(),
            'payment_method_options' => $this->Pos_model->online_food_payment_method_options(),
        ]);
    }

    public function online_food_locations()
    {
        $this->require_permission('pos.online_food.index', 'view');

        $this->render('pos/online_food_locations', [
            'page_title' => 'Alamat Online Food',
            'active_menu' => 'pos.online_food.index',
            'filters' => $this->online_food_location_filters(),
        ]);
    }

    public function online_food_locations_data()
    {
        $this->require_permission('pos.online_food.index', 'view');
        $this->json_ok($this->Pos_model->online_food_location_rows($this->online_food_location_filters()));
    }

    public function online_food_location_member_search()
    {
        $this->require_permission('pos.online_food.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(15, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function online_food_location_save()
    {
        $this->require_permission('pos.online_food.index', 'edit');
        $result = $this->Pos_model->save_online_food_location($this->request_payload());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan pengaturan alamat online food.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
        ]);
    }

    public function online_food_location_delete($id)
    {
        $this->require_permission('pos.online_food.index', 'delete');
        $result = $this->Pos_model->delete_online_food_location((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus alamat online food.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
        ]);
    }

    public function deposit_member_search()
    {
        $this->require_permission('pos.deposit.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(15, (int)$this->input->get('limit', true)));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function deposit_save()
    {
        $this->require_permission('pos.deposit.index', 'create');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_deposit(
            $payload,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan deposit / DP.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'member_id' => (int)($result['member_id'] ?? 0),
        ]);
    }

    public function deposit_void($id)
    {
        $this->require_permission('pos.deposit.index', 'edit');
        $result = $this->Pos_model->void_deposit(
            (int)$id,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membatalkan deposit / DP.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function sales_channels()
    {
        $this->require_permission('pos.sales_channel.index', 'view');
        $this->render('pos/sales_channel_index', [
            'page_title' => 'Sales Channel POS',
            'filters' => $this->sales_channel_filters(),
            'filter_options' => $this->Pos_model->sales_channel_filter_options(),
        ]);
    }

    public function sales_channels_data()
    {
        $this->require_permission('pos.sales_channel.index', 'view');
        $this->json_ok($this->Pos_model->sales_channel_rows($this->sales_channel_filters()));
    }

    public function sales_channel_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.sales_channel.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_sales_channel($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan sales channel.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function sales_channel_toggle($id)
    {
        $this->require_permission('pos.sales_channel.index', 'edit');
        $result = $this->Pos_model->toggle_sales_channel((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status sales channel.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function sales_channel_delete($id)
    {
        $this->require_permission('pos.sales_channel.index', 'delete');
        $result = $this->Pos_model->delete_sales_channel((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus sales channel.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function payment_methods_data()
    {
        $this->require_permission('pos.payment_method.index', 'view');
        $this->json_ok($this->Pos_model->payment_method_rows($this->payment_method_filters()));
    }

    public function payment_method_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.payment_method.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_payment_method($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan payment method.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function payment_method_toggle($id)
    {
        $this->require_permission('pos.payment_method.index', 'edit');
        $result = $this->Pos_model->toggle_payment_method((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status payment method.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function outlets_terminals()
    {
        $this->require_permission('pos.outlet_terminal.index', 'view');
        $this->render('pos/outlet_terminal_index', [
            'page_title' => 'Outlet + Terminal POS',
            'outlet_filters' => $this->outlet_filters(),
            'terminal_filters' => $this->terminal_filters(),
            'filter_options' => $this->Pos_model->outlet_terminal_filter_options(),
        ]);
    }

    public function outlets_data()
    {
        $this->require_permission('pos.outlet_terminal.index', 'view');
        $this->json_ok($this->Pos_model->outlet_rows($this->outlet_filters()));
    }

    public function outlet_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.outlet_terminal.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_outlet($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan outlet.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function outlet_toggle($id)
    {
        $this->require_permission('pos.outlet_terminal.index', 'edit');
        $result = $this->Pos_model->toggle_outlet((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status outlet.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function terminals_data()
    {
        $this->require_permission('pos.outlet_terminal.index', 'view');
        $this->json_ok($this->Pos_model->terminal_rows($this->terminal_filters()));
    }

    public function terminal_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.outlet_terminal.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_terminal($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan terminal.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function terminal_toggle($id)
    {
        $this->require_permission('pos.outlet_terminal.index', 'edit');
        $result = $this->Pos_model->toggle_terminal((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status terminal.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printers()
    {
        $this->require_permission('pos.printer.index', 'view');
        if ($this->Pos_print_model->ready()) {
            $connections = $this->Pos_print_model->connection_rows(['status' => 'ALL', 'limit' => 100]);
            $layouts = $this->Pos_print_model->layout_rows(['status' => 'ALL', 'limit' => 100]);
            $routes = $this->Pos_print_model->route_rows(['status' => 'ALL', 'limit' => 100]);
            $attempts = $this->Pos_print_model->attempt_rows(['status' => 'ALL', 'limit' => 5]);
            $this->render('pos/printer_config_index', [
                'page_title' => 'Printer POS',
                'active_menu' => 'pos.printer.index',
                'config_ready' => true,
                'connection_total' => (int)($connections['meta']['total'] ?? 0),
                'layout_total' => (int)($layouts['meta']['total'] ?? 0),
                'route_total' => (int)($routes['meta']['total'] ?? 0),
                'route_active_total' => count(array_filter((array)($routes['rows'] ?? []), static function ($route): bool {
                    return !empty($route['is_active']);
                })),
                'recent_attempts' => (array)($attempts['rows'] ?? []),
                'event_type_labels' => $this->Pos_print_model->event_type_labels(),
                'document_type_labels' => $this->Pos_print_model->document_type_labels(),
            ]);
            return;
        }
        $this->render('pos/printer_index', [
            'page_title' => 'Printer POS',
            'active_menu' => 'pos.printer.index',
            'template_filters' => $this->printer_template_filters(),
            'profile_filters' => $this->printer_profile_filters(),
            'device_filters' => $this->printer_device_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_templates()
    {
        $this->require_permission('pos.printer.index', 'view');
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/layouts');
            return;
        }
        $this->render('pos/printer_templates_index', [
            'page_title' => 'Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'template_filters' => $this->printer_template_filters(),
        ]);
    }

    public function printer_profiles()
    {
        $this->require_permission('pos.printer.index', 'view');
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/rules');
            return;
        }
        $this->render('pos/printer_profiles_index', [
            'page_title' => 'Pengaturan Output Printer POS',
            'active_menu' => 'pos.printer.index',
            'profile_filters' => $this->printer_profile_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_devices()
    {
        $this->require_permission('pos.printer.index', 'view');
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/connections');
            return;
        }
        $this->render('pos/printer_devices_index', [
            'page_title' => 'Device Printer POS',
            'active_menu' => 'pos.printer.index',
            'device_filters' => $this->printer_device_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_workspace_legacy()
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers');
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_index', [
            'page_title' => 'Printer POS',
            'active_menu' => 'pos.printer.index',
            'template_filters' => $this->printer_template_filters(),
            'profile_filters' => $this->printer_profile_filters(),
            'device_filters' => $this->printer_device_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_settings()
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/general');
            return;
        }
        $this->require_permission('pos.printer.index', 'edit');
        $general = $this->Pos_model->printer_general_settings();
        $payload = is_array($general['payload'] ?? null) ? $general['payload'] : [];

        if ($this->input->method() === 'post') {
            $result = $this->Pos_model->save_printer_general_settings([
                'title' => $this->input->post('title', false),
                'subtitle' => $this->input->post('subtitle', false),
                'logo_url' => $this->input->post('logo_url', false),
                'wifi_name' => $this->input->post('wifi_name', false),
                'wifi_password' => $this->input->post('wifi_password', false),
                'show_customer_point_info' => $this->input->post('show_customer_point_info') ? 1 : 0,
                'show_customer_stamp_info' => $this->input->post('show_customer_stamp_info') ? 1 : 0,
                'show_customer_voucher' => $this->input->post('show_customer_voucher') ? 1 : 0,
                'customer_voucher_limit' => $this->input->post('customer_voucher_limit', false),
                'customer_voucher_message_template' => $this->input->post('customer_voucher_message_template', false),
                'customer_voucher_align' => $this->input->post('customer_voucher_align', false),
                'header_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('header_lines', false))),
                'footer_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('footer_lines', false))),
            ]);
            if ($result['ok'] ?? false) {
                $this->session->set_flashdata('success', 'Pengaturan umum printer berhasil disimpan.');
                redirect('pos/printers/settings');
                return;
            }
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan pengaturan umum printer.'));
            $payload = array_merge($payload, [
                'title' => (string)$this->input->post('title', false),
                'subtitle' => (string)$this->input->post('subtitle', false),
                'logo_url' => (string)$this->input->post('logo_url', false),
                'wifi_name' => (string)$this->input->post('wifi_name', false),
                'wifi_password' => (string)$this->input->post('wifi_password', false),
                'show_customer_point_info' => $this->input->post('show_customer_point_info') ? 1 : 0,
                'show_customer_stamp_info' => $this->input->post('show_customer_stamp_info') ? 1 : 0,
                'show_customer_voucher' => $this->input->post('show_customer_voucher') ? 1 : 0,
                'customer_voucher_limit' => max(1, (int)$this->input->post('customer_voucher_limit', false)),
                'customer_voucher_message_template' => (string)$this->input->post('customer_voucher_message_template', false),
                'customer_voucher_align' => strtoupper((string)$this->input->post('customer_voucher_align', false)),
                'header_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('header_lines', false))),
                'footer_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('footer_lines', false))),
            ]);
        }

        $this->render('pos/printer_settings', [
            'page_title' => 'Pengaturan Umum Printer POS',
            'active_menu' => 'pos.printer.index',
            'payload' => $payload,
        ]);
    }

    public function printer_templates_data()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Layout Dokumen. Daftar template lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $this->json_ok($this->Pos_model->printer_template_rows($this->printer_template_filters()));
    }

    public function printer_connections()
    {
        $this->require_printer_config_permission('pos.printer.connection', 'view');
        $this->render('pos/printer_connections_index', [
            'page_title' => 'Koneksi Printer POS',
            'active_menu' => 'pos.printer.index',
            'printer_options' => $this->Pos_print_model->options(),
            'can_create' => $this->printer_config_can('pos.printer.connection', 'create'),
            'can_edit' => $this->printer_config_can('pos.printer.connection', 'edit'),
        ]);
    }

    public function printer_connections_data()
    {
        $this->require_printer_config_permission('pos.printer.connection', 'view');
        $this->json_ok($this->Pos_print_model->connection_rows($this->printer_config_filters('connection')));
    }

    public function printer_connection_save()
    {
        $payload = $this->request_payload();
        $id = max(0, (int)($payload['id'] ?? 0));
        $this->require_printer_config_permission('pos.printer.connection', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_print_model->save_connection($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan koneksi printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_connection_toggle($id)
    {
        $this->require_printer_config_permission('pos.printer.connection', 'edit');
        $result = $this->Pos_print_model->toggle_connection((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status koneksi printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_connection_test($id)
    {
        $this->require_printer_config_permission('pos.printer.connection', 'edit');
        $connection = $this->Pos_print_model->find_connection((int)$id);
        if (!$connection) {
            $this->json_error('Koneksi printer tidak ditemukan.', 404);
            return;
        }
        if (empty($connection['is_active'])) {
            $this->json_error('Aktifkan koneksi printer sebelum melakukan test.', 422);
            return;
        }
        if (strtoupper((string)($connection['connection_type'] ?? '')) !== 'LOCAL_AGENT'
            || trim((string)($connection['agent_printer_code'] ?? '')) === ''
            || (int)($connection['python_port'] ?? 0) <= 0) {
            $this->json_error('Koneksi ini belum memiliki Local Agent, kode printer, atau port yang valid.', 422);
            return;
        }
        $attemptId = $this->Pos_print_model->create_attempt([
            'event_code' => 'TEST',
            'document_type' => 'RECEIPT',
            'attempt_kind' => 'TEST',
            'connection_id' => (int)$connection['id'],
            'connection_name' => (string)($connection['connection_name'] ?? ''),
            'route_name' => 'Test koneksi manual',
            'outlet_id' => (int)($connection['outlet_id'] ?? 0),
            'line_count' => 0,
        ]);
        $width = $this->posprinterpreviewservice->normalizePaperWidthMm($connection['paper_width_mm'] ?? 80);
        $chars = $this->posprinterpreviewservice->normalizeCharsPerLine($width, (int)($connection['chars_per_line'] ?? 0));
        $this->json_ok(['target' => [
            'print_attempt_id' => $attemptId,
            'printer_code' => (string)$connection['agent_printer_code'],
            'printer_name' => (string)$connection['connection_name'],
            'python_port' => (int)$connection['python_port'],
            'paper_width_mm' => $width,
            'chars_per_line' => $chars,
            'copies' => max(1, (int)($connection['default_copy_count'] ?? 1)),
            'cut_mode' => in_array(strtoupper((string)($connection['cut_mode'] ?? 'PARTIAL')), ['NONE', 'PARTIAL', 'FULL'], true)
                ? strtoupper((string)($connection['cut_mode'] ?? 'PARTIAL'))
                : 'PARTIAL',
            'open_drawer' => !empty($connection['open_drawer']) ? 1 : 0,
            'text' => str_repeat('=', $chars) . "\nTEST PRINTER POS\n" . (string)$connection['connection_name'] . "\n" . date('d-m-Y H:i:s') . "\nJika ini terbaca, koneksi siap.\n" . str_repeat('=', $chars),
        ]]);
    }

    public function printer_general()
    {
        $this->require_printer_config_permission('pos.printer.general', 'view');
        $outletId = 0;
        if ($this->input->method() === 'post') {
            $this->require_printer_config_permission('pos.printer.general', 'edit');
            if (!$this->require_printer_general_csrf()) {
                return;
            }

            $logoUpload = $this->store_printer_general_logo_upload();
            if (!($logoUpload['ok'] ?? false)) {
                $this->session->set_flashdata('error', (string)($logoUpload['message'] ?? 'Logo tidak dapat diunggah.'));
                redirect('pos/printers/general');
                return;
            }

            $settings = [
                'title' => $this->input->post('title', false),
                'subtitle' => $this->input->post('subtitle', false),
                'wifi_name' => $this->input->post('wifi_name', false),
                'wifi_password' => $this->input->post('wifi_password', false),
                'customer_voucher_limit' => $this->input->post('customer_voucher_limit', false),
                'customer_voucher_message_template' => $this->input->post('customer_voucher_message_template', false),
                'customer_voucher_align' => $this->input->post('customer_voucher_align', false),
                'customer_review_qr_enabled' => $this->input->post('customer_review_qr_enabled', false),
                'customer_review_message' => $this->input->post('customer_review_message', false),
                'header_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('header_lines', false))),
                'footer_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('footer_lines', false))),
            ];
            if (!empty($logoUpload['logo_url'])) {
                $settings['logo_url'] = (string)$logoUpload['logo_url'];
            }

            $result = $this->Pos_print_model->save_general_settings($settings);
            if ($result['ok'] ?? false) {
                $this->session->set_flashdata('success', 'Tampilan umum cetak berhasil disimpan dan akan dipakai semua cetakan yang relevan.');
            } else {
                $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan tampilan umum cetak.'));
            }
            redirect('pos/printers/general');
            return;
        }
        $this->render('pos/printer_general_index', [
            'page_title' => 'Tampilan Umum Cetak POS',
            'active_menu' => 'pos.printer.index',
            'outlet_id' => $outletId,
            'general' => $this->Pos_print_model->general_settings($outletId),
            'printer_options' => $this->Pos_print_model->options(),
            'can_edit' => $this->printer_config_can('pos.printer.general', 'edit'),
            'printer_general_csrf_token' => $this->printer_general_csrf(),
        ]);
    }

    public function printer_layouts()
    {
        $this->require_printer_config_permission('pos.printer.layout', 'view');
        $this->render('pos/printer_layouts_index', [
            'page_title' => 'Layout Cetak POS',
            'active_menu' => 'pos.printer.index',
            'document_types' => $this->Pos_print_model->document_types(),
            'document_type_labels' => $this->Pos_print_model->document_type_labels(),
            'can_create' => $this->printer_config_can('pos.printer.layout', 'create'),
            'can_edit' => $this->printer_config_can('pos.printer.layout', 'edit'),
        ]);
    }

    public function printer_layout_editor($id = 0)
    {
        $this->require_printer_config_permission('pos.printer.layout', 'view');
        $id = max(0, (int)$id);
        if ($id === 0) {
            $this->require_printer_config_permission('pos.printer.layout', 'create');
        }
        $layout = $id > 0 ? $this->Pos_print_model->find_layout($id) : null;
        if ($id > 0 && !$layout) {
            show_404();
            return;
        }
        $this->render('pos/printer_layout_editor', [
            'page_title' => $layout ? 'Edit Layout Cetak POS' : 'Tambah Layout Cetak POS',
            'active_menu' => 'pos.printer.index',
            'layout_row' => $layout,
            'layout_routes' => $layout ? $this->Pos_print_model->routes_for_layout($id) : [],
            'layout_test_routes' => $layout ? $this->Pos_print_model->routes_for_layout($id, true) : [],
            'printer_options' => $this->Pos_print_model->options(),
            'general_settings' => $this->Pos_print_model->general_settings(),
            'document_types' => $this->Pos_print_model->document_types(),
            'document_type_labels' => $this->Pos_print_model->document_type_labels(),
            'can_create' => $this->printer_config_can('pos.printer.layout', 'create'),
            'can_edit' => $this->printer_config_can('pos.printer.layout', 'edit'),
        ]);
    }

    public function printer_layouts_data()
    {
        $this->require_printer_config_permission('pos.printer.layout', 'view');
        $this->json_ok($this->Pos_print_model->layout_rows($this->printer_config_filters('layout')));
    }

    public function printer_layout_save()
    {
        $payload = $this->request_payload();
        $id = max(0, (int)($payload['id'] ?? 0));
        $this->require_printer_config_permission('pos.printer.layout', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_print_model->save_layout($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan layout cetak.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_layout_preview()
    {
        $this->require_printer_config_permission('pos.printer.layout', 'view');
        $input = $this->request_payload();
        $documentType = strtoupper(trim((string)($input['document_type'] ?? 'RECEIPT')));
        $payload = $this->Pos_print_model->preview_layout_payload($input);
        $connection = $this->Pos_print_model->find_connection(max(0, (int)($input['connection_id'] ?? 0)));
        $printer = [
            'printer_name' => (string)($connection['connection_name'] ?? 'Preview tanpa printer'),
            'printer_role' => (string)($connection['location_label'] ?? 'CUSTOM'),
            'connection_type' => (string)($connection['connection_type'] ?? 'LOCAL_AGENT'),
            'paper_width_mm' => (int)($connection['paper_width_mm'] ?? 80),
            'chars_per_line' => (int)($connection['chars_per_line'] ?? 48),
            'python_port' => (int)($connection['python_port'] ?? 0),
            'agent_host' => (string)($connection['agent_host'] ?? ''),
        ];
        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $printer, $documentType);
        $this->attach_customer_review_preview_url($preview);
        $this->json_ok(['preview' => $preview, 'connection' => $connection ?: null]);
    }

    public function printer_layout_test($id)
    {
        $this->require_printer_config_permission('pos.printer.layout', 'edit');
        $layout = $this->Pos_print_model->find_layout((int)$id);
        if (!$layout) {
            $this->json_error('Layout cetak tidak ditemukan.', 404);
            return;
        }
        $usableRoutes = $this->Pos_print_model->routes_for_layout((int)$layout['id'], true);
        $requestedRouteId = max(0, (int)($this->request_payload()['route_id'] ?? 0));
        $route = null;
        foreach ($usableRoutes as $candidate) {
            if ($requestedRouteId <= 0 || (int)$candidate['id'] === $requestedRouteId) {
                $route = $candidate;
                break;
            }
        }
        if (!$route) {
            $this->json_error('Layout belum dipakai oleh aturan cetak aktif dengan koneksi Local Agent yang siap. Simpan layout, lalu hubungkan ke Aturan Cetak terlebih dahulu.', 422);
            return;
        }
        $template = $this->Pos_print_model->runtime_template($route);
        $preview = $this->posprinterpreviewservice->buildPreviewPackage((array)($template['payload'] ?? []), [
            'printer_name' => (string)($route['connection_name'] ?? ''),
            'printer_role' => (string)($route['location_label'] ?? 'CUSTOM'),
            'connection_type' => (string)($route['connection_type'] ?? 'LOCAL_AGENT'),
            'paper_width_mm' => (int)($route['paper_width_mm'] ?? 80),
            'chars_per_line' => (int)($route['chars_per_line'] ?? 48),
            'python_port' => (int)($route['python_port'] ?? 0),
            'agent_host' => (string)($route['agent_host'] ?? ''),
        ], (string)($template['document_type'] ?? 'RECEIPT'));
        $textLines = (array)($preview['lines'] ?? []);
        if (!empty($preview['logo_url'])) {
            $logoMarker = $this->posprinterpreviewservice->buildLogoPrintMarker((string)$preview['logo_url']);
            if ($logoMarker !== '') {
                array_unshift($textLines, $logoMarker);
            }
        }
        if (!empty($preview['payload']['show_customer_review_qr']) && !empty($preview['payload']['customer_review_qr_enabled'])) {
            $reviewUrl = $this->Pos_customer_review_model->station_url();
            if ($reviewUrl !== '') {
                $textLines[] = '[[QRCODE:' . $reviewUrl . ']]';
            }
        }
        $attemptId = $this->Pos_print_model->create_attempt([
            'event_code' => 'TEST',
            'document_type' => (string)($template['document_type'] ?? 'RECEIPT'),
            'attempt_kind' => 'TEST',
            'route_id' => (int)$route['id'],
            'connection_id' => (int)$route['connection_id'],
            'layout_id' => (int)$layout['id'],
            'connection_name' => (string)($route['connection_name'] ?? ''),
            'connection_code' => (string)($route['connection_code'] ?? ''),
            'route_name' => (string)($route['route_name'] ?? ''),
            'route_code' => (string)($route['route_code'] ?? ''),
            'layout_name' => (string)($layout['layout_name'] ?? ''),
            'layout_code' => (string)($layout['layout_code'] ?? ''),
            'outlet_id' => (int)($route['outlet_id'] ?? 0),
            'terminal_id' => (int)($route['terminal_id'] ?? 0),
            'line_count' => count($textLines),
            'copy_count' => max(1, (int)($route['copy_count'] ?? 0) ?: (int)($route['default_copy_count'] ?? 1)),
        ]);
        $this->json_ok(['target' => [
            'print_attempt_id' => $attemptId,
            'printer_code' => (string)($route['agent_printer_code'] ?? ''),
            'printer_name' => (string)($route['connection_name'] ?? ''),
            'python_port' => (int)($route['python_port'] ?? 0),
            'paper_width_mm' => (int)($preview['paper_width_mm'] ?? 80),
            'chars_per_line' => (int)($preview['chars_per_line'] ?? 48),
            'copies' => max(1, (int)($route['copy_count'] ?? 0) ?: (int)($route['default_copy_count'] ?? 1)),
            'cut_mode' => in_array(strtoupper((string)($route['cut_mode'] ?? 'PARTIAL')), ['NONE', 'PARTIAL', 'FULL'], true)
                ? strtoupper((string)($route['cut_mode'] ?? 'PARTIAL'))
                : 'PARTIAL',
            'open_drawer' => !empty($route['open_drawer']) ? 1 : 0,
            'text' => implode("\n", $textLines) . "\n",
        ]]);
    }

    public function printer_layout_toggle($id)
    {
        $this->require_printer_config_permission('pos.printer.layout', 'edit');
        $result = $this->Pos_print_model->toggle_layout((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status layout.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_rules()
    {
        $this->require_printer_config_permission('pos.printer.rule', 'view');
        $this->render('pos/printer_rules_index', [
            'page_title' => 'Aturan Cetak POS',
            'active_menu' => 'pos.printer.index',
            'printer_options' => $this->Pos_print_model->options(),
            'event_types' => $this->Pos_print_model->event_types(),
            'document_types' => $this->Pos_print_model->document_types(),
            'event_type_labels' => $this->Pos_print_model->event_type_labels(),
            'document_type_labels' => $this->Pos_print_model->document_type_labels(),
            'print_mode_labels' => $this->Pos_print_model->print_mode_labels(),
            'can_create' => $this->printer_config_can('pos.printer.rule', 'create'),
            'can_edit' => $this->printer_config_can('pos.printer.rule', 'edit'),
        ]);
    }

    public function printer_rules_data()
    {
        $this->require_printer_config_permission('pos.printer.rule', 'view');
        $this->json_ok($this->Pos_print_model->route_rows($this->printer_config_filters('rule')));
    }

    public function printer_rule_save()
    {
        $payload = $this->request_payload();
        $id = max(0, (int)($payload['id'] ?? 0));
        $this->require_printer_config_permission('pos.printer.rule', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_print_model->save_route($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan aturan cetak.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_rule_toggle($id)
    {
        $this->require_printer_config_permission('pos.printer.rule', 'edit');
        $result = $this->Pos_print_model->toggle_route((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status aturan cetak.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active'], 'print_mode' => (string)($result['print_mode'] ?? '')]);
    }

    public function customer_reviews()
    {
        $this->require_permission($this->customer_review_permission_page(), 'view');
        $csrfToken = $this->customer_review_csrf();
        $this->output->set_header('Cache-Control: private, no-store');
        $options = $this->Pos_print_model->options();
        $this->render('pos/customer_reviews_index', [
            'page_title' => 'Ulasan Pelanggan POS',
            'active_menu' => 'pos.customer_review',
            'customer_review_csrf_token' => $csrfToken,
            'customer_review_csrf_header' => self::CUSTOMER_REVIEW_CSRF_HEADER,
            'can_edit' => $this->can($this->customer_review_permission_page(), 'edit'),
            'general' => $this->Pos_print_model->general_settings(),
            'stations' => $this->Pos_customer_review_model->station_rows(),
            'station_ready' => $this->Pos_customer_review_model->station_ready(),
            'outlets' => (array)($options['outlets'] ?? []),
        ]);
    }

    public function customer_reviews_data()
    {
        $this->require_permission($this->customer_review_permission_page(), 'view');
        $this->json_ok($this->Pos_customer_review_model->rows($this->customer_review_filters()));
    }

    public function customer_review_visibility($id)
    {
        $this->require_permission($this->customer_review_permission_page(), 'edit');
        if (!$this->require_customer_review_csrf()) return;
        $payload = $this->request_payload();
        $result = $this->Pos_customer_review_model->set_visibility(
            (int)$id,
            !empty($payload['hidden']),
            max(0, (int)($this->current_user['id'] ?? 0)),
            trim((string)($payload['reason'] ?? ''))
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal memperbarui status ulasan.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'review_status' => (string)$result['review_status']]);
    }

    public function customer_review_settings()
    {
        $this->require_permission($this->customer_review_permission_page(), 'edit');
        if (!$this->require_customer_review_csrf()) return;
        $payload = $this->request_payload();
        $result = $this->Pos_print_model->save_customer_review_settings($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Pengaturan QR ulasan belum dapat disimpan.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)($result['id'] ?? 0)]);
    }

    public function customer_review_station_save()
    {
        $this->require_permission($this->customer_review_permission_page(), 'edit');
        if (!$this->require_customer_review_csrf()) return;
        $result = $this->Pos_customer_review_model->save_station($this->request_payload());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'QR area belum dapat disimpan.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)($result['id'] ?? 0)]);
    }

    public function customer_review_station_toggle($id)
    {
        $this->require_permission($this->customer_review_permission_page(), 'edit');
        if (!$this->require_customer_review_csrf()) return;
        $result = $this->Pos_customer_review_model->toggle_station((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Status QR area belum dapat diubah.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function customer_review_station_print($id)
    {
        $this->require_permission($this->customer_review_permission_page(), 'view');
        $station = $this->Pos_customer_review_model->find_station((int)$id);
        if (!$station) {
            show_404();
            return;
        }
        $this->load->view('pos/customer_review_station_print', [
            'station' => $station,
            'station_url' => $this->Pos_customer_review_model->station_url($station),
            'business_profile' => $this->business_profile(),
        ]);
    }

    public function printer_preview_live()
    {
        $this->require_printer_config_permission('pos.printer.layout', 'view');
        $this->render('pos/printer_preview_live', [
            'page_title' => 'Preview Aturan Cetak',
            'active_menu' => 'pos.printer.index',
            'routes' => (array)($this->Pos_print_model->route_rows(['status' => 'ACTIVE', 'limit' => 100])['rows'] ?? []),
            'event_type_labels' => $this->Pos_print_model->event_type_labels(),
            'document_type_labels' => $this->Pos_print_model->document_type_labels(),
        ]);
    }

    public function printer_preview_live_data($routeId = 0)
    {
        $this->require_printer_config_permission('pos.printer.layout', 'view');
        $route = $this->Pos_print_model->find_route((int)$routeId);
        if (!$route) {
            $this->json_error('Aturan cetak tidak ditemukan.', 404);
            return;
        }
        $template = $this->Pos_print_model->runtime_template($route);
        $preview = $this->posprinterpreviewservice->buildPreviewPackage((array)($template['payload'] ?? []), [
            'printer_name' => (string)($route['connection_name'] ?? ''),
            'printer_role' => (string)($route['location_label'] ?? 'CUSTOM'),
            'connection_type' => (string)($route['connection_type'] ?? 'LOCAL_AGENT'),
            'paper_width_mm' => (int)($route['paper_width_mm'] ?? 80),
            'chars_per_line' => (int)($route['chars_per_line'] ?? 48),
            'python_port' => (int)($route['python_port'] ?? 0),
            'agent_host' => (string)($route['agent_host'] ?? ''),
        ], (string)($template['document_type'] ?? 'RECEIPT'));
        $this->attach_customer_review_preview_url($preview);
        $this->json_ok(['route' => $route, 'template' => $template, 'preview' => $preview]);
    }

    public function printer_monitor()
    {
        $this->require_printer_config_permission('pos.printer.monitor', 'view');
        $this->render('pos/printer_monitor_index', [
            'page_title' => 'Monitor Cetak POS',
            'active_menu' => 'pos.printer.index',
            'event_type_labels' => $this->Pos_print_model->event_type_labels(),
            'document_type_labels' => $this->Pos_print_model->document_type_labels(),
        ]);
    }

    public function printer_monitor_data()
    {
        $this->require_printer_config_permission('pos.printer.monitor', 'view');
        $this->json_ok($this->Pos_print_model->attempt_rows($this->printer_config_filters('monitor')));
    }

    public function printer_attempt_ack()
    {
        // Cashier must be able to acknowledge its own browser-to-agent request,
        // even though the configuration pages remain admin-only.
        $this->require_permission('pos.printer.index', 'view');
        $payload = $this->request_payload();
        $result = $this->Pos_print_model->acknowledge_attempt(
            max(0, (int)($payload['attempt_id'] ?? 0)),
            (string)($payload['status'] ?? 'FAILED'),
            (string)($payload['message'] ?? ''),
            $this->current_actor_employee_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal memperbarui riwayat cetak.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'status' => (string)$result['status']]);
    }

    public function printer_guide_config()
    {
        $this->require_printer_config_permission('pos.printer.guide', 'view');
        $this->render('pos/printer_guide_config', [
            'page_title' => 'Panduan Printer POS',
            'active_menu' => 'pos.printer.index',
            'download_files' => $this->printer_download_files(),
            'can_agent_provision' => $this->can('pos.printer.connection', 'edit'),
        ]);
    }

    public function printer_template_create()
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/layouts/create');
            return;
        }
        $this->require_permission('pos.printer.index', 'create');
        $documentType = strtoupper(trim((string)$this->input->get('document_type', true)));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }

        $generalSettings = $this->Pos_model->printer_general_settings();
        $payload = $this->posprinterpreviewservice->defaultPayload($documentType, (array)($generalSettings['payload'] ?? []));
        if ($this->input->method() === 'post') {
            $saved = $this->save_printer_template_from_form(0);
            if ($saved['ok']) {
                redirect('pos/printers/templates/preview/' . (int)$saved['id']);
                return;
            }
            $this->session->set_flashdata('error', (string)$saved['message']);
            $payload = $this->posprinterpreviewservice->payloadFromInput($this->input->post(null, false), $documentType, (array)($generalSettings['payload'] ?? []));
        }

        $this->render('pos/printer_template_editor', [
            'page_title' => 'Tambah Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => null,
            'document_types' => ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'],
            'payload' => $payload,
            'preview_printers' => $this->Pos_model->active_printer_preview_options(),
        ]);
    }

    public function printer_template_edit($id)
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/layouts');
            return;
        }
        $this->require_permission('pos.printer.index', 'edit');
        $row = $this->Pos_model->find_printer_template((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $generalSettings = $this->Pos_model->printer_general_settings();
        $payload = $this->posprinterpreviewservice->decodePayload((string)($row['template_payload'] ?? '{}'), (string)($row['document_type'] ?? 'RECEIPT'), (array)($generalSettings['payload'] ?? []));
        if ($this->input->method() === 'post') {
            $saved = $this->save_printer_template_from_form((int)$id);
            if ($saved['ok']) {
                redirect('pos/printers/templates/preview/' . (int)$saved['id']);
                return;
            }
            $this->session->set_flashdata('error', (string)$saved['message']);
            $payload = $this->posprinterpreviewservice->payloadFromInput($this->input->post(null, false), (string)($row['document_type'] ?? 'RECEIPT'), (array)($generalSettings['payload'] ?? []));
            $row = array_merge($row, $this->input->post(null, false) ?: []);
        }

        $this->render('pos/printer_template_editor', [
            'page_title' => 'Edit Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => $row,
            'document_types' => ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'],
            'payload' => $payload,
            'preview_printers' => $this->Pos_model->active_printer_preview_options(),
        ]);
    }

    public function printer_template_preview($id)
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/preview-live');
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $row = $this->Pos_model->find_printer_template((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $previewPrinters = $this->Pos_model->active_printer_preview_options();
        $selectedPrinterId = max(0, (int)$this->input->get('printer_id', true));
        $selectedPrinter = [];
        foreach ($previewPrinters as $printer) {
            if ((int)$printer['id'] === $selectedPrinterId) {
                $selectedPrinter = $printer;
                break;
            }
        }
        if (!$selectedPrinter && !empty($previewPrinters)) {
            $selectedPrinter = $previewPrinters[0];
            $selectedPrinterId = (int)$selectedPrinter['id'];
        }

        $generalSettings = $this->Pos_model->printer_general_settings();
        $payload = $this->posprinterpreviewservice->decodePayload((string)($row['template_payload'] ?? '{}'), (string)($row['document_type'] ?? 'RECEIPT'), (array)($generalSettings['payload'] ?? []));
        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $selectedPrinter, (string)($row['document_type'] ?? 'RECEIPT'));

        $this->render('pos/printer_template_preview', [
            'page_title' => 'Preview Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => $row,
            'payload' => $payload,
            'preview' => $preview,
            'preview_printers' => $previewPrinters,
            'selected_printer_id' => $selectedPrinterId,
        ]);
    }

    public function printer_template_live_preview()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan preview pada editor Layout Dokumen. Preview template lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $payload = $this->request_payload();
        $documentType = strtoupper(trim((string)($payload['document_type'] ?? 'RECEIPT')));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }
        $printerId = (int)($payload['printer_id'] ?? 0);
        $printer = $printerId > 0 ? ($this->Pos_model->find_printer_device($printerId) ?: []) : [];
        $generalSettings = $this->Pos_model->printer_general_settings();
        $templatePayload = $this->posprinterpreviewservice->payloadFromInput($payload, $documentType, (array)($generalSettings['payload'] ?? []));
        $preview = $this->posprinterpreviewservice->buildPreviewPackage($templatePayload, $printer, $documentType);
        $this->json_ok($preview);
    }

    public function printer_template_save()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan simpan pada editor Layout Dokumen. Template lama sudah tidak dipakai.', 410);
            return;
        }
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.printer.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_printer_template($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan template printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_template_toggle($id)
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan status pada halaman Layout Dokumen. Template lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'edit');
        $result = $this->Pos_model->toggle_printer_template((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status template printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_profiles_data()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Aturan Cetak. Profile output lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $this->json_ok($this->Pos_model->printer_profile_rows($this->printer_profile_filters()));
    }

    public function printer_profile_save()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Aturan Cetak. Profile output lama sudah tidak dipakai.', 410);
            return;
        }
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.printer.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_printer_profile($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan profile printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_profile_toggle($id)
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Aturan Cetak. Profile output lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'edit');
        $result = $this->Pos_model->toggle_printer_profile((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status profile printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_devices_data()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Koneksi Printer. Device lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $this->json_ok($this->Pos_model->printer_device_rows($this->printer_device_filters()));
    }

    public function printer_device_save()
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Koneksi Printer. Device lama sudah tidak dipakai.', 410);
            return;
        }
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.printer.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_printer_device($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan device printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_device_toggle($id)
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan halaman Koneksi Printer. Device lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'edit');
        $result = $this->Pos_model->toggle_printer_device((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status device printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_preview($id)
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/preview-live');
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $row = $this->Pos_model->find_printer_device((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $templates = $this->Pos_model->active_printer_template_options();
        $templateId = max(0, (int)$this->input->get('template_id', true));
        $selectedTemplate = [];
        foreach ($templates as $template) {
            if ((int)$template['id'] === $templateId) {
                $selectedTemplate = $template;
                break;
            }
        }
        if (!$selectedTemplate) {
            foreach ($templates as $template) {
                if (strtoupper((string)($template['document_type'] ?? '')) === 'RECEIPT' && (int)($template['is_default'] ?? 0) === 1) {
                    $selectedTemplate = $template;
                    break;
                }
            }
        }
        if (!$selectedTemplate && !empty($templates)) {
            $selectedTemplate = $templates[0];
        }

        $payload = $this->posprinterpreviewservice->defaultPayload('RECEIPT');
        $documentType = 'RECEIPT';
        if (!empty($selectedTemplate)) {
            $documentType = (string)($selectedTemplate['document_type'] ?? 'RECEIPT');
            $templateRow = $this->Pos_model->find_printer_template((int)$selectedTemplate['id']);
            if ($templateRow) {
                $selectedTemplate = $templateRow;
                $payload = $this->posprinterpreviewservice->decodePayload((string)($templateRow['template_payload'] ?? '{}'), $documentType);
            }
        }

        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $row, $documentType);
        $logoPrintMarker = $this->posprinterpreviewservice->buildLogoPrintMarker((string)($preview['logo_url'] ?? ''));
        $this->render('pos/printer_preview', [
            'page_title' => 'Preview Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => $row,
            'templates' => $templates,
            'selected_template' => $selectedTemplate,
            'preview' => $preview,
            'logo_print_marker' => $logoPrintMarker,
            'autoprint' => (int)$this->input->get('autoprint', true) === 1,
        ]);
    }

    public function printer_test($id)
    {
        if ($this->Pos_print_model->ready()) {
            $this->json_error('Gunakan tombol Test pada Koneksi Printer atau editor Layout Dokumen. Test perangkat lama sudah tidak dipakai.', 410);
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $row = $this->Pos_model->find_printer_device((int)$id);
        if (!$row) {
            $this->json_error('Device printer tidak ditemukan.', 404);
            return;
        }

        $templates = $this->Pos_model->active_printer_template_options();
        $templateId = max(0, (int)$this->input->get('template_id', true));
        $selectedTemplate = [];
        foreach ($templates as $template) {
            if ((int)$template['id'] === $templateId) {
                $selectedTemplate = $template;
                break;
            }
        }
        if (!$selectedTemplate) {
            foreach ($templates as $template) {
                if (
                    strtoupper((string)($template['document_type'] ?? '')) === 'RECEIPT'
                    && (int)($template['is_default'] ?? 0) === 1
                ) {
                    $selectedTemplate = $template;
                    break;
                }
            }
        }
        if (!$selectedTemplate && !empty($templates)) {
            $selectedTemplate = $templates[0];
        }

        $payload = $this->posprinterpreviewservice->defaultPayload('RECEIPT');
        $documentType = 'RECEIPT';
        if (!empty($selectedTemplate)) {
            $documentType = (string)($selectedTemplate['document_type'] ?? 'RECEIPT');
            $templateRow = $this->Pos_model->find_printer_template((int)$selectedTemplate['id']);
            if ($templateRow) {
                $selectedTemplate = $templateRow;
                $payload = $this->posprinterpreviewservice->decodePayload(
                    (string)($templateRow['template_payload'] ?? '{}'),
                    $documentType
                );
            }
        }

        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $row, $documentType);
        $logoPrintMarker = $this->posprinterpreviewservice->buildLogoPrintMarker((string)($preview['logo_url'] ?? ''));
        $this->json_ok([
            'printer' => [
                'id' => (int)($row['id'] ?? 0),
                'device_code' => (string)($row['device_code'] ?? ''),
                'device_name' => (string)($row['device_name'] ?? ''),
                'agent_host' => (string)($row['agent_host'] ?? ''),
                'python_port' => (int)($preview['summary']['python_port'] ?? $row['python_port'] ?? 0),
            ],
            'template' => [
                'id' => (int)($selectedTemplate['id'] ?? 0),
                'template_name' => (string)($selectedTemplate['template_name'] ?? 'Preview Default'),
                'document_type' => (string)($selectedTemplate['document_type'] ?? $documentType),
            ],
            'preview' => $preview,
            'print_payload' => [
                'text' => ($logoPrintMarker !== '' ? $logoPrintMarker . "\n" : '')
                    . implode("\n", (array)($preview['lines'] ?? [])),
                'paper_width_mm' => (int)($preview['paper_width_mm'] ?? 80),
                'chars_per_line' => (int)($preview['chars_per_line'] ?? 48),
            ],
        ]);
    }

    public function printer_guide()
    {
        if ($this->Pos_print_model->ready()) {
            redirect('pos/printers/guide');
            return;
        }
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_guide', [
            'page_title' => 'Panduan Printer POS',
            'active_menu' => 'pos.printer.index',
            'download_files' => $this->printer_download_files(),
            'can_agent_provision' => $this->can('pos.printer.connection', 'edit'),
        ]);
    }

    public function printer_download($key = '')
    {
        if (in_array($key, ['config_json', 'agent_bundle'], true)) {
            $this->require_permission('pos.printer.connection', 'edit');
        } else {
            $this->require_permission('pos.printer.index', 'view');
        }
        if ($key === 'agent_bundle') {
            $this->printer_download_bundle();
            return;
        }

        $files = $this->printer_download_files();
        if (!isset($files[$key])) {
            show_404();
            return;
        }

        $file = $files[$key];
        if ($key === 'config_json') {
            try {
                $content = $this->build_printer_agent_config_json(trim((string)$this->input->get('agent_name', true)));
            } catch (RuntimeException $exception) {
                show_error(html_escape($exception->getMessage()), 503, 'Konfigurasi Printer Agent belum siap');
                return;
            }
            $this->output
                ->set_content_type('application/json')
                ->set_header('Content-Disposition: attachment; filename="' . $file['filename'] . '"')
                ->set_output($content);
            return;
        }

        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            show_404();
            return;
        }

        $mime = 'application/octet-stream';
        if (substr($path, -3) === '.md') {
            $mime = 'text/markdown';
        } elseif (substr($path, -3) === '.py') {
            $mime = 'text/x-python';
        } elseif (substr($path, -4) === '.bat') {
            $mime = 'text/plain';
        } elseif (substr($path, -5) === '.json') {
            $mime = 'application/json';
        } elseif (substr($path, -3) === '.sh') {
            $mime = 'text/plain';
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Disposition: attachment; filename="' . $file['filename'] . '"')
            ->set_output((string)file_get_contents($path));
    }

    public function order_draft()
    {
        $this->require_permission('pos.order.draft.index', 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $filters = $this->order_draft_filters('UNPAID');
        $this->render('pos/order_draft_index', [
            'page_title' => 'Draft Order POS',
            'active_menu' => 'pos.order.draft.index',
            'workspace_mode' => 'UNPAID',
            'filters' => $filters,
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function order_paid()
    {
        $pageCode = $this->order_workspace_page_code('view', 'pos.order.paid.index');
        $this->require_permission($pageCode, 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $filters = $this->order_draft_filters('PAID');
        $this->render('pos/order_paid_index', [
            'page_title'               => 'Pesanan Terbayar POS',
            'active_menu'              => 'pos.order.paid.index',
            'workspace_mode'           => 'PAID',
            'filters'                  => $filters,
            'filter_options'           => $this->Pos_model->order_draft_filter_options(),
            'payment_method_options'   => $this->Pos_model->deposit_payment_method_options(),
            'can_edit_payment_method'  => $this->can_edit_sales_transaction_payment(),
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function cashier()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $bootstrap = $this->Pos_model->cashier_bootstrap_options($this->current_actor_employee_id());
        $this->render_cashier('pos/cashier_index', [
            'page_title' => 'Kasir POS',
            'active_menu' => 'pos.cashier.index',
            'filters' => $this->order_draft_filters('MIXED'),
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
            'catalog_filters' => $this->Pos_model->cashier_catalog_filter_options(),
            'cashier_bootstrap' => $bootstrap,
            'active_cashier_session' => $bootstrap['active_session'] ?? null,
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function cashier_open()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $reconStatus = $this->Pos_model->daily_recon_gate_status('OPEN');
        if (!empty($reconStatus['enabled']) && empty($reconStatus['complete'])) {
            $this->json_error($this->daily_recon_block_message($reconStatus), 409, [
                'daily_recon_status' => $reconStatus,
            ]);
            return;
        }
        $result = $this->Pos_model->open_cashier_session($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membuka kasir POS.'), 422, [
                'code' => (string)($result['code'] ?? ''),
            ]);
            return;
        }
        $this->json_ok([
            'session' => (array)($result['session'] ?? []),
            'already_open' => !empty($result['already_open']),
            'daily_recon_status' => $reconStatus,
        ]);
    }

    public function cashier_recon_status()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');

        $stage = strtoupper(trim((string)$this->input->get('stage', true)));
        $date = trim((string)$this->input->get('date', true));
        $this->json_ok([
            'status' => $this->Pos_model->daily_recon_gate_status($stage, $date),
        ]);
    }

    public function daily_recon_settings()
    {
        $pageCode = 'pos.daily_recon_settings.index';
        $this->require_permission($pageCode, 'view');

        $mode = strtoupper(trim($this->pos_app_config_value('pos.daily_recon_gate_mode', 'OPEN_AND_CLOSE')));
        if (!in_array($mode, ['OFF', 'OPEN_ONLY', 'CLOSE_ONLY', 'OPEN_AND_CLOSE'], true)) {
            $mode = 'OPEN_AND_CLOSE';
        }

        $policy = strtoupper(trim($this->pos_app_config_value('pos.daily_recon_gate_policy', 'BLOCK')));
        if ($policy === '') {
            $policy = 'BLOCK';
        }
        $confirmMode = strtoupper(trim($this->pos_app_config_value('pos.daily_recon_confirm_mode', 'BULK_ALLOWED')));
        if (!in_array($confirmMode, ['BULK_ALLOWED', 'ROW_REQUIRED'], true)) {
            $confirmMode = 'BULK_ALLOWED';
        }

        $this->render('pos/daily_recon_settings', [
            'page_title' => 'Pengaturan Gate Daily Recon POS',
            'active_menu' => 'pos.daily_recon_settings',
            'mode' => $mode,
            'policy' => $policy,
            'confirm_mode' => $confirmMode,
            'required_materials' => $this->pos_app_config_value('pos.daily_recon_required_materials', ''),
            'required_components' => $this->pos_app_config_value('pos.daily_recon_required_components', ''),
            'required_material_ids' => $this->daily_recon_selected_ids($this->pos_app_config_value('pos.daily_recon_required_materials', '')),
            'required_component_ids' => $this->daily_recon_selected_ids($this->pos_app_config_value('pos.daily_recon_required_components', '')),
            'material_options' => $this->daily_recon_material_options(),
            'component_options' => $this->daily_recon_component_options(),
            'can_edit' => $this->can($pageCode, 'edit'),
        ]);
    }

    public function daily_recon_settings_save()
    {
        $pageCode = 'pos.daily_recon_settings.index';
        $this->require_permission($pageCode, 'edit');

        $mode = strtoupper(trim((string)$this->input->post('daily_recon_gate_mode', true)));
        if (!in_array($mode, ['OFF', 'OPEN_ONLY', 'CLOSE_ONLY', 'OPEN_AND_CLOSE'], true)) {
            $this->session->set_flashdata('error', 'Mode gate daily recon tidak valid.');
            redirect('pos/daily-recon-settings');
            return;
        }
        $confirmMode = strtoupper(trim((string)$this->input->post('daily_recon_confirm_mode', true)));
        if (!in_array($confirmMode, ['BULK_ALLOWED', 'ROW_REQUIRED'], true)) {
            $this->session->set_flashdata('error', 'Mode konfirmasi daily recon tidak valid.');
            redirect('pos/daily-recon-settings');
            return;
        }
        $requiredMaterialIds = array_values(array_unique(array_filter(array_map('intval', (array)$this->input->post('daily_recon_required_materials', false)))));
        $requiredComponentIds = array_values(array_unique(array_filter(array_map('intval', (array)$this->input->post('daily_recon_required_components', false)))));
        sort($requiredMaterialIds);
        sort($requiredComponentIds);
        $requiredMaterials = implode("\n", $requiredMaterialIds);
        $requiredComponents = implode("\n", $requiredComponentIds);

        $this->save_pos_app_config(
            'pos.daily_recon_gate_mode',
            $mode,
            'Mode gate daily recon sebelum buka/tutup POS: OFF, OPEN_ONLY, CLOSE_ONLY, OPEN_AND_CLOSE.'
        );
        $this->save_pos_app_config(
            'pos.daily_recon_gate_policy',
            'BLOCK',
            'Kebijakan gate daily recon POS. BLOCK: kasir tidak bisa buka/tutup sebelum checkpoint recon lengkap.'
        );
        $this->save_pos_app_config(
            'pos.daily_recon_confirm_mode',
            $confirmMode,
            'Mode konfirmasi daily recon: BULK_ALLOWED atau ROW_REQUIRED.'
        );
        $this->save_pos_app_config(
            'pos.daily_recon_required_materials',
            $requiredMaterials,
            'Daftar material_id yang wajib recon per baris.'
        );
        $this->save_pos_app_config(
            'pos.daily_recon_required_components',
            $requiredComponents,
            'Daftar component_id yang wajib recon per baris.'
        );

        $this->session->set_flashdata('success', 'Pengaturan gate daily recon POS berhasil disimpan.');
        redirect('pos/daily-recon-settings');
    }

    public function cashier_close()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $reconStatus = $this->Pos_model->daily_recon_gate_status('CLOSE');
        if (!empty($reconStatus['enabled']) && empty($reconStatus['complete'])) {
            $this->json_error($this->daily_recon_block_message($reconStatus), 409, [
                'daily_recon_status' => $reconStatus,
            ]);
            return;
        }
        $result = $this->Pos_model->close_cashier_session($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menutup kasir POS.'), 422);
            return;
        }

        $shiftId = (int)($result['shift_id'] ?? 0);
        $directPrint = $shiftId > 0
            ? $this->Pos_model->direct_print_targets_for_shift_close($shiftId, (array)($result['report'] ?? []))
            : ['ok' => true, 'targets' => []];

        $this->json_ok([
            'shift_id' => $shiftId,
            'summary' => (array)($result['summary'] ?? []),
            'report' => (array)($result['report'] ?? []),
            'daily_recon_status' => $reconStatus,
            'direct_print_targets' => (array)(($directPrint['ok'] ?? false) ? ($directPrint['targets'] ?? []) : []),
            'print_prepare_message' => !($directPrint['ok'] ?? false)
                ? (string)($directPrint['message'] ?? 'Payload direct print tutup kasir gagal disiapkan.')
                : '',
        ]);
    }

    public function cashier_close_preview()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->cashier_close_preview($this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Preview tutup kasir belum tersedia.'), 422);
            return;
        }
        $this->json_ok([
            'shift_id' => (int)($result['shift_id'] ?? 0),
            'session' => (array)($result['session'] ?? []),
            'report' => (array)($result['report'] ?? []),
            'daily_recon_status' => $this->Pos_model->daily_recon_gate_status('CLOSE'),
        ]);
    }

    public function cashier_session_status()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $this->json_ok([
            'session' => $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id()),
        ]);
    }

    public function order_monitor()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'view');
        $orderMonitorCsrfToken = $this->order_monitor_task_csrf();
        $filters = $this->order_monitor_filters();
        $monitorScope = $this->current_actor_monitor_scope();
        $this->render('pos/order_monitor_index', [
            'page_title' => 'Monitor Dapur, Bar & Checker',
            'active_menu' => 'pos.order.monitor',
            'filters' => $filters,
            'monitor_scope' => $monitorScope,
            'order_monitor_csrf_token' => $orderMonitorCsrfToken,
            'station_options' => $this->Pos_order_monitor_model->station_options(),
            'outlet_options' => $this->Pos_order_monitor_model->active_outlets(),
            'payload' => $this->Pos_order_monitor_model->board_payload(
                (string)($filters['station'] ?? 'ALL'),
                (int)($filters['outlet_id'] ?? 0),
                (string)($filters['date_from'] ?? ''),
                (string)($filters['date_to'] ?? ''),
                $monitorScope
            ),
            'poll_ms' => 12000,
        ]);
    }

    public function order_monitor_data()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->order_monitor_filters();
        $monitorScope = $this->current_actor_monitor_scope();
        $this->json_ok([
            'payload' => $this->Pos_order_monitor_model->board_payload(
                (string)($filters['station'] ?? 'ALL'),
                (int)($filters['outlet_id'] ?? 0),
                (string)($filters['date_from'] ?? ''),
                (string)($filters['date_to'] ?? ''),
                $monitorScope
            ),
        ]);
    }

    public function order_monitor_ack_task()
    {
        $this->handle_order_monitor_task_action('ack');
    }

    public function order_monitor_ready_task()
    {
        $this->handle_order_monitor_task_action('ready');
    }

    public function order_monitor_checker_task()
    {
        $this->handle_order_monitor_task_action('checker');
    }

    public function order_monitor_ack_order_station()
    {
        $this->handle_order_monitor_bulk_action('ack');
    }

    public function order_monitor_ready_order_station()
    {
        $this->handle_order_monitor_bulk_action('ready');
    }

    public function order_monitor_checker_order()
    {
        $this->handle_order_monitor_bulk_action('checker');
    }

    public function stock_live()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $posTransactionCsrfToken = $this->pos_transaction_csrf();
        $filters = $this->stock_live_filters();
        $filterOptions = $this->Pos_model->stock_live_filter_options();
        if ((int)($filters['outlet_id'] ?? 0) <= 0 && !empty($filterOptions['outlets'][0]['id'])) {
            $filters['outlet_id'] = (int)$filterOptions['outlets'][0]['id'];
        }
        $this->render('pos/stock_live_index', [
            'page_title' => 'Stock Live POS',
            'active_menu' => 'pos.stock.live.index',
            'filters' => $filters,
            'filter_options' => $filterOptions,
            'pos_transaction_csrf_token' => $posTransactionCsrfToken,
        ]);
    }

    public function stock_live_data()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->stock_live_filters();
        if ((int)($filters['outlet_id'] ?? 0) <= 0) {
            $outlets = $this->Pos_model->local_outlet_options();
            if (!empty($outlets[0]['id'])) {
                $filters['outlet_id'] = (int)$outlets[0]['id'];
            }
        }
        $dataset = $this->Pos_model->stock_live_rows($filters);
        $rows = (array)($dataset['rows'] ?? []);
        $latestLogs = [];
        if ((int)$filters['outlet_id'] > 0) {
            $latestLogs = $this->Pos_model->stock_live_latest_log_map((int)$filters['outlet_id'], array_column($rows, 'id'));
        }

        $this->load->library('PosAvailabilityRebuildService');
        $resultRows = [];
        foreach ($rows as $row) {
            $productId = (int)($row['id'] ?? 0);
            $live = $filters['outlet_id'] > 0
                ? $this->posavailabilityrebuildservice->resolve_live_availability((int)$filters['outlet_id'], $productId, [
                    'trigger_context' => 'PAGE_LIST',
                    'actor_employee_id' => $this->current_actor_employee_id(),
                ])
                : ['ok' => false, 'message' => 'Outlet belum dipilih.'];

            $comparison = ($live['ok'] ?? false)
                ? $this->posavailabilityrebuildservice->compare_cache_snapshot([
                    'availability_status' => (string)($row['cache_availability_status'] ?? ''),
                    'estimated_available_qty' => (float)($row['cache_estimated_available_qty'] ?? 0),
                    'bottleneck_name_snapshot' => (string)($row['cache_bottleneck_name_snapshot'] ?? ''),
                    'hpp_live_snapshot' => (float)($row['cache_hpp_live_snapshot'] ?? 0),
                    'is_dirty' => (int)($row['cache_is_dirty'] ?? 0),
                ], $live)
                : ['mismatch_flag' => 0, 'note' => 'Outlet belum dipilih.'];

            $resultRows[] = [
                'product_id' => $productId,
                'product_code' => (string)($row['product_code'] ?? ''),
                'product_name' => (string)($row['product_name'] ?? ''),
                'product_division_id' => (int)($row['product_division_id'] ?? 0),
                'default_operational_division_id' => (int)($row['default_operational_division_id'] ?? 0),
                'product_division_name' => (string)($row['product_division_name'] ?? ''),
                'classification_name' => (string)($row['classification_name'] ?? ''),
                'product_category_name' => (string)($row['product_category_name'] ?? ''),
                'selling_price' => (float)($row['selling_price'] ?? 0),
                'cache' => [
                    'status' => (string)($row['cache_availability_status'] ?? ''),
                    'qty' => (float)($row['cache_estimated_available_qty'] ?? 0),
                    'bottleneck' => (string)($row['cache_bottleneck_name_snapshot'] ?? ''),
                    'hpp' => (float)($row['cache_hpp_live_snapshot'] ?? 0),
                    'source_mode' => (string)($row['cache_source_mode'] ?? ''),
                    'computed_at' => (string)($row['cache_computed_at'] ?? ''),
                    'last_commit_event' => (string)($row['cache_last_commit_event'] ?? ''),
                    'is_dirty' => (int)($row['cache_is_dirty'] ?? 0),
                ],
                'live' => ($live['ok'] ?? false) ? [
                    'status' => (string)($live['availability_status'] ?? ''),
                    'qty' => (float)($live['estimated_available_qty'] ?? 0),
                    'bottleneck' => (string)($live['bottleneck_name_snapshot'] ?? ''),
                    'hpp' => (float)($live['hpp_live_snapshot'] ?? 0),
                    'override_allowed' => (int)($live['override_allowed'] ?? 0),
                    'line_count' => count((array)($live['lines'] ?? [])),
                ] : null,
                'comparison' => (array)$comparison,
                'live_error' => !($live['ok'] ?? false) ? (string)($live['message'] ?? 'Gagal hitung live.') : '',
                'latest_log' => (array)($latestLogs[$productId] ?? []),
            ];
        }

        if (!empty($filters['mismatch_only'])) {
            $resultRows = array_values(array_filter($resultRows, static function (array $row): bool {
                return !empty($row['comparison']['mismatch_flag']);
            }));
        }

        $this->json_ok([
            'rows' => $resultRows,
            'meta' => (array)($dataset['meta'] ?? []),
        ]);
    }

    public function stock_live_probe()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->load->library('PosAvailabilityRebuildService');
        $result = $this->posavailabilityrebuildservice->probe_compare($outletId, $productId, [
            'trigger_context' => 'MANUAL_PROBE',
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menjalankan probe stock live.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function stock_live_rebuild_all()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($payload['division_id'] ?? 0));
        $this->load->library('PosAvailabilityRebuildService');
        $result = $this->posavailabilityrebuildservice->rebuild_all_products($outletId, [
            'division_id' => $divisionId,
        ], [
            'trigger_context' => 'MANUAL_REBUILD_ALL',
            'event_source' => 'MANUAL_REBUILD_ALL',
            'event_table' => 'mst_product_recipe',
            'event_id' => null,
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal rebuild total stock live POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function stock_live_rebuild()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $productId = max(0, (int)($payload['product_id'] ?? 0));
        $this->load->library('PosAvailabilityRebuildService');
        $result = $this->posavailabilityrebuildservice->rebuild_product($outletId, $productId, [
            'trigger_context' => 'MANUAL_REBUILD',
            'event_source' => 'MANUAL_REBUILD',
            'event_table' => 'pos_product_availability_cache',
            'event_id' => $productId,
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal rebuild stock live POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    /**
     * Operator monitor for the lightweight availability cache worker.
     * Opening this page never recalculates recipes or writes inventory data.
     */
    public function availability_queue()
    {
        $this->require_permission('pos.availability.queue.index', 'view');
        $availabilityQueueCsrfToken = $this->pos_availability_queue_csrf();
        $filters = $this->availability_queue_filters();
        $dataset = $this->Pos_availability_queue_model->rows($filters);

        $this->render('pos/availability_queue_index', [
            'page_title' => 'Ketersediaan POS',
            'active_menu' => 'pos.availability.queue',
            'filters' => $filters,
            'summary' => $this->Pos_availability_queue_model->summary(),
            'rows' => (array)($dataset['rows'] ?? []),
            'pagination' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_availability_queue_model->outlet_options(),
            'queue_ready' => $this->Pos_availability_queue_model->is_ready(),
            'can_process_queue' => $this->can('pos.availability.queue.index', 'edit'),
            'pos_availability_queue_csrf_token' => $availabilityQueueCsrfToken,
            // Path ini mengikuti cron Ubuntu yang sudah disiapkan untuk server.
            'cron_command' => '* * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100',
        ]);
    }

    /**
     * A bounded, manual worker pass for an operator. It uses the same queue
     * service as cron and never invokes a shell command from the web request.
     */
    public function availability_queue_process()
    {
        $this->require_permission('pos.availability.queue.index', 'edit');
        if (!$this->require_pos_availability_queue_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $limit = max(1, min(100, (int)($payload['limit'] ?? 25)));
        $this->load->library('PosAvailabilityQueueService');
        $result = $this->posavailabilityqueueservice->processPendingJobs([
            'limit' => $limit,
        ]);

        $processed = (int)($result['processed_count'] ?? 0);
        $success = (int)($result['success_count'] ?? 0);
        $failed = (int)($result['failed_count'] ?? 0);
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata(
                'error',
                'Proses sekali selesai untuk ' . $processed . ' job: ' . $success . ' berhasil, ' . $failed . ' gagal. Periksa baris gagal sebelum mengulanginya.'
            );
        } else {
            $this->session->set_flashdata(
                'success',
                $processed > 0
                    ? 'Proses sekali selesai: ' . $success . ' cache produk POS berhasil diperbarui.'
                    : 'Tidak ada job availability POS yang siap diproses saat ini.'
            );
        }

        redirect($this->availability_queue_redirect_url($payload));
    }

    /**
     * Reset one exhausted availability job. It does not change product stock
     * or recipe; the following worker pass performs the actual cache refresh.
     */
    public function availability_queue_retry($jobId)
    {
        $this->require_permission('pos.availability.queue.index', 'edit');
        if (!$this->require_pos_availability_queue_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $this->load->library('PosAvailabilityQueueService');
        $result = $this->posavailabilityqueueservice->retryJob((int)$jobId);
        $this->session->set_flashdata(
            !empty($result['ok']) ? 'success' : 'error',
            (string)($result['message'] ?? 'Job availability POS tidak dapat diproses.')
        );
        redirect($this->availability_queue_redirect_url($payload));
    }

    public function order_draft_data()
    {
        $pageCode = $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $workspaceMode = strtoupper(trim((string)$this->input->get('workspace_mode', true)));
        if (!in_array($workspaceMode, ['UNPAID', 'PAID', 'MIXED'], true)) {
            $workspaceMode = 'MIXED';
        }
        $this->json_ok($this->Pos_model->order_draft_rows($this->order_draft_filters($workspaceMode)));
    }

    public function order_draft_load($id)
    {
        $pageCode = $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->find_order_draft((int)$id);
        if (!$result) {
            $this->json_error('Draft order tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($result + [
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
        ]);
    }

    public function order_draft_delete($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'delete') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'delete');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $result = $this->Pos_model->delete_order_draft((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus draft order POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
        ]);
    }

    public function order_draft_member_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function order_draft_product_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $q = trim((string)$this->input->get('q', true));
        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $limit = max(1, min(30, (int)$this->input->get('limit', true) ?: 12));
        $this->json_ok([
            'rows' => $this->Pos_model->order_product_search($q, $outletId, $limit),
        ]);
    }

    public function cashier_catalog()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'category_id' => max(0, (int)$this->input->get('category_id', true)),
            'limit' => max(1, min(120, (int)$this->input->get('limit', true) ?: 32)),
        ];
        $this->json_ok([
            'rows' => $this->Pos_model->order_product_catalog($filters),
        ]);
    }

    public function cashier_bundle_catalog()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'limit' => max(1, min(60, (int)$this->input->get('limit', true) ?: 24)),
        ];
        $this->json_ok([
            'rows' => $this->Pos_model->order_bundle_catalog($filters),
        ]);
    }

    public function order_draft_bundle_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $q = trim((string)$this->input->get('q', true));
        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_bundle_search($q, $outletId, $limit),
        ]);
    }

    public function order_draft_extra_options()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->json_ok([
            'groups' => $this->Pos_model->order_extra_options($productId),
        ]);
    }

    public function order_draft_save()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $action = $id > 0 ? 'edit' : 'create';
        $this->require_permission($pageCode, $action);
        $result = $this->Pos_model->save_order_draft($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan draft order POS.'), 422);
            return;
        }
        if (
            !empty($result['append_mode'])
            && empty($result['header_only_update'])
            && (int)($result['appended_line_count'] ?? 0) > 0
        ) {
            $this->Pos_order_monitor_model->sync_order_tasks((int)($result['id'] ?? 0));
        }
        $this->json_ok([
            'id' => (int)$result['id'],
            'order_no' => (string)($result['order_no'] ?? ''),
        ]);
    }

    public function order_draft_confirm($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $actorEmployeeId = $this->current_actor_employee_id();
        $orderId = (int)$id;
        $this->confirm_order_and_respond($orderId, $actorEmployeeId);
    }

    public function order_draft_save_confirm()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $action = $id > 0 ? 'edit' : 'create';
        $this->require_permission($pageCode, $action);
        $actorEmployeeId = $this->current_actor_employee_id();
        $saved = $this->Pos_model->save_order_draft($payload, $actorEmployeeId);
        if (!($saved['ok'] ?? false)) {
            $this->json_error((string)($saved['message'] ?? 'Gagal menyimpan draft order POS.'), 422);
            return;
        }
        $orderId = (int)($saved['id'] ?? 0);
        if ($orderId <= 0) {
            $this->json_error('Draft order tersimpan tetapi ID order tidak valid.', 422);
            return;
        }
        $this->confirm_order_and_respond($orderId, $actorEmployeeId, [
            'append_mode' => !empty($saved['append_mode']),
            'header_only_update' => !empty($saved['header_only_update']),
            'line_ids' => (array)($saved['appended_line_ids'] ?? []),
            'appended_line_count' => (int)($saved['appended_line_count'] ?? 0),
        ]);
    }

    private function confirm_order_and_respond(int $orderId, int $actorEmployeeId, array $options = []): void
    {
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $appendMode = !empty($options['append_mode']);
        $headerOnlyUpdate = !empty($options['header_only_update']);
        $lineIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['line_ids'] ?? [])))));
        $appendedLineCount = (int)($options['appended_line_count'] ?? count($lineIds));

        if ($appendMode && $headerOnlyUpdate && empty($lineIds)) {
            $this->json_ok([
                'id' => $orderId,
                'snapshot_id' => 0,
                'commit_no' => '',
                'resolved_line_count' => 0,
                'runtime_job_id' => 0,
                'runtime_job_code' => '',
                'print_job_count' => 0,
                'runtime_kickoff' => [
                    'ok' => false,
                    'mode' => 'not_required',
                    'message' => 'Tidak ada item baru. Sistem hanya menyimpan perubahan header transaksi POS.',
                ],
                'stock_sync' => [
                    'queued' => false,
                    'status' => 'NOT_REQUIRED',
                    'kickoff_ok' => false,
                ],
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => true,
                'appended_line_count' => 0,
                'header_only_update' => true,
            ]);
            return;
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'line_ids' => $lineIds,
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order POS.'), 422);
            return;
        }
        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));

        if (empty($resolved['lines'])) {
            $finalize = $this->Pos_model->finalize_order_confirmation($orderId, 0, $actorEmployeeId, 'NOT_REQUIRED');
            if (!($finalize['ok'] ?? false)) {
                $this->json_error((string)($finalize['message'] ?? 'Order POS gagal difinalkan.'), 422);
                return;
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            $this->json_ok([
                'id' => $orderId,
                'snapshot_id' => 0,
                'commit_no' => '',
                'resolved_line_count' => 0,
                'runtime_job_id' => 0,
                'runtime_job_code' => '',
                'print_job_count' => 0,
                'runtime_kickoff' => [
                    'ok' => false,
                    'mode' => 'not_required',
                    'message' => 'Stock commit dilewati karena item yang dikonfirmasi belum memiliki recipe product.',
                ],
                'stock_sync' => [
                    'queued' => false,
                    'status' => 'NOT_REQUIRED',
                    'kickoff_ok' => false,
                ],
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => $appendMode,
                'appended_line_count' => $appendedLineCount,
                'header_only_update' => false,
                'warning_message' => $warningMessage,
            ]);
            return;
        }

        $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit.'), 422);
            return;
        }

        $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, (int)$snapshot['id'], $actorEmployeeId, [
            'event_source' => 'ORDER_CONFIRM',
            'event_id' => $orderId,
        ]);
        if (!($queued['ok'] ?? false)) {
            $this->json_error((string)($queued['message'] ?? 'Snapshot berhasil, tetapi queue runtime POS gagal dibuat.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $markQueued = $this->posstockcommitservice->mark_queued((int)$snapshot['id']);
        if (!($markQueued['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot stock commit gagal ditandai queued.');
            $this->json_error((string)($markQueued['message'] ?? 'Gagal menandai stock commit sebagai queued.'), 422);
            return;
        }

        $finalize = $this->Pos_model->finalize_order_confirmation($orderId, (int)$snapshot['id'], $actorEmployeeId, 'QUEUED');
        if (!($finalize['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Order POS gagal difinalkan setelah queue dibuat.');
            $this->json_error((string)($finalize['message'] ?? 'Snapshot berhasil, tetapi order gagal difinalkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);

        $kickoff = [
            'ok' => false,
            'mode' => 'client_trigger_required',
            'message' => 'Queue stok POS akan dipicu dari client setelah response confirm diterima.',
        ];
        if (function_exists('fastcgi_finish_request')) {
            $kickoff = $this->schedule_runtime_job_processing($orderId, (int)($queued['job_id'] ?? 0), 1);
        }

        $this->json_ok([
            'id' => $orderId,
            'snapshot_id' => (int)$snapshot['id'],
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => (int)($queued['job_id'] ?? 0),
            'runtime_job_code' => (string)($queued['job_code'] ?? ''),
            'print_job_count' => 0,
            'runtime_kickoff' => $kickoff,
            'stock_sync' => [
                'queued' => true,
                'status' => 'QUEUED',
                'kickoff_ok' => !empty($kickoff['ok']),
            ],
            'stock_commit_status' => 'QUEUED',
            'append_mode' => $appendMode,
            'appended_line_count' => $appendedLineCount,
            'header_only_update' => false,
            'warning_message' => $warningMessage,
        ]);
    }

    public function order_confirm_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $payload = $this->request_payload();
        $snapshotId = (int)($payload['snapshot_id'] ?? 0);
        $directPrint = $this->Pos_model->direct_print_targets_for_order_confirm((int)$id, $snapshotId);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'snapshot_id' => $snapshotId,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_reprint_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_order_reprint_step_up((int)$id, $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $lineScope = strtoupper(trim((string)($payload['line_scope'] ?? 'ALL')));
        $printerId = (int)($payload['printer_id'] ?? 0);
        $directPrint = $this->Pos_model->direct_print_targets_for_order_reprint((int)$id, [
            'line_scope' => $lineScope,
            'printer_id' => $printerId,
        ]);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload cetak ulang order gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'line_scope' => $lineScope,
            'printer_id' => $printerId,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_reprint_step_up_verify()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            $this->current_actor_user_id(),
            'ORDER_REPRINT',
            $payload['order_id'] ?? null,
            $payload['password'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    public function order_void_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $directPrint = $this->Pos_model->direct_print_targets_for_void((int)$id);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print void gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_refund_print_targets($id)
    {
        $pageCode = $this->can('pos.order.paid.index', 'view') ? 'pos.order.paid.index' : 'pos.cashier.index';
        $this->require_permission($pageCode, 'view');
        $directPrint = $this->Pos_model->direct_print_targets_for_refund((int)$id);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print refund gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_runtime_sync($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $eventSource = strtoupper(trim((string)($payload['event_source'] ?? 'ORDER_CONFIRM')));
        if (!in_array($eventSource, ['ORDER_CONFIRM', 'ORDER_VOID', 'ORDER_REFUND'], true)) {
            $eventSource = 'ORDER_CONFIRM';
        }
        $eventId = max(0, (int)($payload['event_id'] ?? 0));
        $result = $this->trigger_stock_live_refresh_for_order((int)$id, $eventSource, $eventId ?: (int)$id, true);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Sinkronisasi stok background gagal dijalankan.'), 422, [
                'success_count' => (int)($result['success_count'] ?? 0),
                'failed_count' => (int)($result['failed_count'] ?? 0),
            ]);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'event_source' => $eventSource,
            'success_count' => (int)($result['success_count'] ?? 0),
            'failed_count' => (int)($result['failed_count'] ?? 0),
            'marked_product_count' => (int)($result['marked_product_count'] ?? 0),
        ]);
    }

    public function order_runtime_job_trigger($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');

        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $orderId = $this->parse_positive_runtime_id($id);
        if ($orderId === null) {
            $this->reject_pos_runtime_job_trigger(422, 'Order runtime POS tidak valid.');
            return;
        }

        $payload = $this->request_payload();
        $jobId = $this->parse_positive_runtime_id($payload['job_id'] ?? null);
        if ($jobId === null) {
            $this->reject_pos_runtime_job_trigger(422, 'Job runtime POS tidak valid.');
            return;
        }

        // The trigger has one fixed resource scope: the route order and its
        // ORDER_CONFIRM_STOCK_COMMIT job. Do not accept a client-supplied scope.
        if (array_key_exists('scope', $payload)) {
            $this->reject_pos_runtime_job_trigger(422, 'Scope job runtime POS tidak dapat ditentukan dari request.');
            return;
        }
        if (array_key_exists('order_id', $payload)) {
            $payloadOrderId = $this->parse_positive_runtime_id($payload['order_id']);
            if ($payloadOrderId === null || $payloadOrderId !== $orderId) {
                $this->reject_pos_runtime_job_trigger(422, 'Scope order job runtime POS tidak sesuai route.');
                return;
            }
        }

        $limit = max(1, min(5, (int)($payload['limit'] ?? 1)));

        if (
            !$this->db->table_exists('pos_runtime_job')
            || !$this->db->table_exists('pos_order')
            || !$this->db->table_exists('pos_stock_commit')
        ) {
            $this->reject_pos_runtime_job_trigger(422, 'Queue runtime POS belum siap.');
            return;
        }

        // Keep this binding read-only and aligned with PosRuntimeJobService's
        // existing job_context query. No new table or schema is introduced.
        $job = $this->db->query(
            "SELECT j.*, o.id AS linked_order_id, o.status AS order_status,
                    o.stock_commit_status, o.stock_committed_at, o.outlet_id,
                    s.order_id AS snapshot_order_id,
                    s.commit_status AS snapshot_commit_status, s.commit_no
             FROM pos_runtime_job j
             LEFT JOIN pos_order o ON o.id = j.order_id
             LEFT JOIN pos_stock_commit s ON s.id = j.snapshot_id
             WHERE j.id = ?
             LIMIT 1",
            [$jobId]
        )->row_array();

        if (!$job) {
            $this->reject_pos_runtime_job_trigger(404, 'Job runtime POS tidak ditemukan.');
            return;
        }
        if ((int)($job['order_id'] ?? 0) !== $orderId) {
            $this->reject_pos_runtime_job_trigger(422, 'Job runtime POS tidak terikat pada order route.');
            return;
        }
        if ((string)($job['job_type'] ?? '') !== 'ORDER_CONFIRM_STOCK_COMMIT') {
            $this->reject_pos_runtime_job_trigger(422, 'Tipe job runtime POS tidak diizinkan untuk trigger ini.');
            return;
        }
        if ((int)($job['linked_order_id'] ?? 0) !== $orderId) {
            $this->reject_pos_runtime_job_trigger(422, 'Order scope job runtime POS tidak ditemukan.');
            return;
        }
        if (
            (int)($job['snapshot_id'] ?? 0) <= 0
            || (int)($job['snapshot_order_id'] ?? 0) !== $orderId
        ) {
            $this->reject_pos_runtime_job_trigger(422, 'Snapshot job runtime POS tidak berada pada scope order.');
            return;
        }

        $jobStatus = strtoupper(trim((string)($job['status'] ?? '')));
        if (!in_array($jobStatus, ['QUEUED', 'PROCESSING', 'FAILED'], true)) {
            $this->reject_pos_runtime_job_trigger(422, 'Job runtime POS sudah terminal atau statusnya tidak dapat diproses.');
            return;
        }
        if ($jobStatus === 'PROCESSING') {
            $startedAt = strtotime((string)($job['started_at'] ?? ''));
            if ($startedAt > 0 && $startedAt >= strtotime('-5 minutes')) {
                $this->reject_pos_runtime_job_trigger(422, 'Job runtime POS sedang diproses oleh worker lain.');
                return;
            }
        }
        if (
            $jobStatus === 'FAILED'
            && (int)($job['attempts'] ?? 0) >= (int)($job['max_attempts'] ?? 0)
        ) {
            $this->reject_pos_runtime_job_trigger(422, 'Job runtime POS sudah mencapai batas percobaan.');
            return;
        }

        $orderStatus = strtoupper(trim((string)($job['order_status'] ?? '')));
        if (in_array($orderStatus, ['VOID', 'REFUND_FULL', 'REFUNDED_FULL'], true)) {
            $this->reject_pos_runtime_job_trigger(422, 'Order sudah terminal; job runtime POS tidak boleh diproses ulang.');
            return;
        }
        if (strtoupper(trim((string)($job['stock_commit_status'] ?? ''))) === 'REVERSED') {
            $this->reject_pos_runtime_job_trigger(422, 'Stock commit order sudah direversal; job runtime POS tidak boleh diproses ulang.');
            return;
        }
        if (in_array(strtoupper(trim((string)($job['snapshot_commit_status'] ?? ''))), ['REVERSED', 'VOID'], true)) {
            $this->reject_pos_runtime_job_trigger(422, 'Snapshot stock commit sudah terminal; job runtime POS tidak boleh diproses ulang.');
            return;
        }

        $this->load->library('PosRuntimeJobService');
        $processed = $this->posruntimejobservice->process_pending_jobs([
            'limit' => $limit,
            'order_id' => $orderId,
            'job_id' => $jobId,
        ]);
        $latestJob = [];
        if (!empty($processed['jobs'][0]['job']) && is_array($processed['jobs'][0]['job'])) {
            $latestJob = $processed['jobs'][0]['job'];
        }

        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Job runtime POS gagal diproses.'), 422, [
                'job' => (array)$latestJob,
                'result' => $processed,
            ]);
            return;
        }

        $this->json_ok([
            'id' => $orderId,
            'job' => (array)$latestJob,
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
        ]);
    }

    public function order_runtime_job_status($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $this->load->library('PosRuntimeJobService');

        $latest = $this->posruntimejobservice->latest_job_for_order((int)$id);
        if (!($latest['ok'] ?? false)) {
            $this->json_error((string)($latest['message'] ?? 'Status queue runtime POS belum tersedia.'), 404);
            return;
        }

        $this->json_ok([
            'id' => (int)$id,
            'job' => (array)($latest['job'] ?? []),
        ]);
    }

    public function order_runtime_failed_jobs()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $this->load->library('PosRuntimeJobService');

        $result = $this->posruntimejobservice->failed_jobs([
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Data job gagal POS belum tersedia.'), 422);
            return;
        }

        $this->json_ok([
            'rows' => (array)($result['rows'] ?? []),
        ]);
    }

    public function order_runtime_active_jobs()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $this->load->library('PosRuntimeJobService');

        $result = $this->posruntimejobservice->active_jobs([
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Data job pending POS belum tersedia.'), 422);
            return;
        }

        $this->json_ok([
            'rows' => (array)($result['rows'] ?? []),
        ]);
    }

    public function order_runtime_job_retry($jobId)
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $jobId = $this->parse_positive_runtime_id($jobId);
        if ($jobId === null) {
            $this->json_error('Job runtime POS tidak valid untuk retry.', 422);
            return;
        }

        $this->load->library('PosRuntimeJobService');

        $retried = $this->posruntimejobservice->retry_job($jobId, $this->current_actor_employee_id());
        if (!($retried['ok'] ?? false)) {
            $this->json_error((string)($retried['message'] ?? 'Job runtime POS gagal di-retry.'), 422);
            return;
        }

        $job = (array)($retried['job'] ?? []);
        $processed = $this->process_runtime_job_now((int)($job['order_id'] ?? 0), (int)($job['id'] ?? 0), 1);
        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Job retry POS gagal diproses.'), 422, [
                'job' => $job,
                'result' => $processed,
            ]);
            return;
        }

        $latest = $this->posruntimejobservice->latest_job_for_order((int)($job['order_id'] ?? 0));
        $this->json_ok([
            'job' => (array)($latest['job'] ?? $job),
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
        ]);
    }

    public function order_runtime_failed_job_delete_draft($jobId)
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $jobId = $this->parse_positive_runtime_id($jobId);
        if ($jobId === null) {
            $this->json_error('Job runtime POS tidak valid untuk hapus draft.', 422);
            return;
        }

        if (!$this->db->table_exists('pos_runtime_job')) {
            $this->json_error('Queue runtime POS belum siap.', 422);
            return;
        }

        $job = $this->db->select('j.id, j.status, j.order_id, o.order_no, o.status AS order_status')
            ->from('pos_runtime_job j')
            ->join('pos_order o', 'o.id = j.order_id', 'inner')
            ->where('j.id', $jobId)
            ->where('j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$job) {
            $this->json_error('Job runtime POS tidak ditemukan.', 404);
            return;
        }

        $jobStatus = strtoupper(trim((string)($job['status'] ?? '')));
        if (!in_array($jobStatus, ['FAILED', 'CANCELLED'], true)) {
            $this->json_error('Hanya job FAILED/CANCELLED yang boleh dipakai untuk hapus draft dari audit ini.', 422);
            return;
        }

        $orderStatus = strtoupper(trim((string)($job['order_status'] ?? '')));
        if (!in_array($orderStatus, ['DRAFT', 'PENDING', 'CONFIRMED'], true)) {
            $this->json_error('Order ini sudah diproses (status: ' . $orderStatus . '), tidak bisa dihapus dari audit job gagal.', 422);
            return;
        }

        $deleted = $this->Pos_model->delete_order_draft((int)($job['order_id'] ?? 0), $this->current_actor_employee_id());
        if (!($deleted['ok'] ?? false)) {
            $this->json_error((string)($deleted['message'] ?? 'Draft order POS gagal dihapus dari audit.'), 422);
            return;
        }

        $this->json_ok([
            'message' => 'Draft order ' . (string)($job['order_no'] ?? '-') . ' berhasil dihapus beserta artefak job/snapshot gagalnya.',
            'order_id' => (int)($job['order_id'] ?? 0),
            'job_id' => $jobId,
        ]);
    }

    public function order_runtime_failed_job_dismiss($jobId)
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $routeJobId = $jobId;
        $jobId = $this->parse_positive_runtime_id($jobId);
        if ($jobId === null || (string)$jobId !== (string)$routeJobId) {
            $this->json_error('Job runtime POS tidak valid untuk ditutup.', 422);
            return;
        }

        if (!$this->db->table_exists('pos_runtime_job')) {
            $this->json_error('Queue runtime POS belum siap.', 422);
            return;
        }

        $job = $this->db->select('j.id, j.status, j.order_id, o.order_no, o.status AS order_status')
            ->from('pos_runtime_job j')
            ->join('pos_order o', 'o.id = j.order_id', 'left')
            ->where('j.id', $jobId)
            ->where('j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$job) {
            $this->json_error('Job runtime POS tidak ditemukan.', 404);
            return;
        }

        $jobStatus = strtoupper(trim((string)($job['status'] ?? '')));
        if ($jobStatus !== 'FAILED') {
            $this->json_error('Hanya job FAILED yang bisa ditutup dari audit.', 422);
            return;
        }

        $this->load->library('PosRuntimeJobService');
        $closed = $this->posruntimejobservice->cancel_job(
            $jobId,
            'Ditutup manual dari audit stock commit POS karena order sudah tidak perlu diproses ulang.'
        );
        if (!($closed['ok'] ?? false)) {
            $this->json_error((string)($closed['message'] ?? 'Job runtime POS gagal ditutup.'), 422);
            return;
        }

        $this->json_ok([
            'message' => 'Job gagal untuk order ' . (string)($job['order_no'] ?? '-') . ' berhasil ditutup.',
            'job_id' => $jobId,
            'order_id' => (int)($job['order_id'] ?? 0),
        ]);
    }

    public function order_runtime_failed_snapshot_retry($snapshotId)
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $routeSnapshotId = $snapshotId;
        $snapshotId = $this->parse_positive_runtime_id($snapshotId);
        if ($snapshotId === null || (string)$snapshotId !== (string)$routeSnapshotId) {
            $this->json_error('Snapshot stock commit POS tidak valid untuk retry.', 422);
            return;
        }

        $snapshot = $this->db->select('s.*, o.order_no, o.status AS order_status, o.stock_commit_status')
            ->from('pos_stock_commit s')
            ->join('pos_order o', 'o.id = s.order_id', 'left')
            ->where('s.id', $snapshotId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$snapshot) {
            $this->json_error('Snapshot stock commit POS tidak ditemukan.', 404);
            return;
        }

        $snapshotStatus = strtoupper(trim((string)($snapshot['commit_status'] ?? '')));
        if ($snapshotStatus !== 'FAILED') {
            $this->json_error('Hanya snapshot FAILED yang bisa di-retry dari audit ini.', 422);
            return;
        }

        $orderId = (int)($snapshot['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->json_error('Order sumber snapshot tidak valid untuk retry.', 422);
            return;
        }

        $orderStatus = strtoupper(trim((string)($snapshot['order_status'] ?? '')));
        if ($orderStatus === 'VOID') {
            $this->json_error('Order ini sudah VOID. Tutup snapshot gagal ini, jangan di-retry lagi.', 422);
            return;
        }

        $orderCommitStatus = strtoupper(trim((string)($snapshot['stock_commit_status'] ?? '')));
        if (in_array($orderCommitStatus, ['POSTED', 'REVERSED', 'NOT_REQUIRED'], true)) {
            $this->json_error('Status stock order sudah ' . $orderCommitStatus . '. Snapshot gagal ini lebih aman ditutup, bukan di-retry.', 422);
            return;
        }

        $actorEmployeeId = $this->current_actor_employee_id();
        $this->load->library('PosRuntimeJobService', null, 'posruntimejobservice');
        $this->load->library('PosStockCommitService', null, 'posstockcommitservice');

        $refreshed = $this->posstockcommitservice->refresh_snapshot_from_order($snapshotId, $actorEmployeeId);
        if (!($refreshed['ok'] ?? false)) {
            $this->json_error((string)($refreshed['message'] ?? 'Snapshot gagal direfresh sebelum retry.'), 422);
            return;
        }

        $this->posstockcommitservice->mark_queued($snapshotId);
        $this->Pos_model->update_order_stock_commit_state($orderId, 'QUEUED', [
            'actor_employee_id' => $actorEmployeeId,
            'event_code' => 'ORDER_CONFIRM_STOCK_RETRY_AUDIT',
            'note' => 'Retry manual snapshot FAILED dari audit stock commit POS.',
        ]);

        $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, $snapshotId, $actorEmployeeId, [
            'event_source' => 'ORDER_CONFIRM_AUDIT_RETRY',
            'event_id' => $snapshotId,
        ]);
        if (!($queued['ok'] ?? false)) {
            $this->json_error((string)($queued['message'] ?? 'Job runtime POS gagal dibuat ulang untuk snapshot FAILED.'), 422);
            return;
        }

        $jobId = (int)($queued['job_id'] ?? 0);
        $processed = $this->process_runtime_job_now($orderId, $jobId, 1);
        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Snapshot FAILED berhasil diantrikan ulang, tetapi proses job masih gagal.'), 422, [
                'result' => $processed,
                'job_id' => $jobId,
                'order_id' => $orderId,
                'snapshot_id' => $snapshotId,
            ]);
            return;
        }

        $latest = $this->posruntimejobservice->latest_job_for_order($orderId);
        $this->json_ok([
            'message' => 'Snapshot FAILED untuk order ' . (string)($snapshot['order_no'] ?? '-') . ' berhasil direfresh, diantrikan ulang, dan diproses.',
            'order_id' => $orderId,
            'snapshot_id' => $snapshotId,
            'job' => (array)($latest['job'] ?? []),
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
        ]);
    }

    public function order_runtime_failed_snapshot_dismiss($snapshotId)
    {
        $pageCode = $this->can('pos.stock.commit.audit.index', 'edit')
            ? 'pos.stock.commit.audit.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $routeSnapshotId = $snapshotId;
        $snapshotId = $this->parse_positive_runtime_id($snapshotId);
        if ($snapshotId === null || (string)$snapshotId !== (string)$routeSnapshotId) {
            $this->json_error('Snapshot stock commit POS tidak valid untuk ditutup.', 422);
            return;
        }

        $snapshot = $this->db->select('s.*, o.order_no, o.status AS order_status, o.stock_commit_status')
            ->from('pos_stock_commit s')
            ->join('pos_order o', 'o.id = s.order_id', 'left')
            ->where('s.id', $snapshotId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$snapshot) {
            $this->json_error('Snapshot stock commit POS tidak ditemukan.', 404);
            return;
        }

        $snapshotStatus = strtoupper(trim((string)($snapshot['commit_status'] ?? '')));
        if ($snapshotStatus !== 'FAILED') {
            $this->json_error('Hanya snapshot FAILED yang bisa ditutup dari audit ini.', 422);
            return;
        }

        $orderId = (int)($snapshot['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->json_error('Snapshot FAILED ini masih butuh retry/rebuild. Tutup manual hanya diizinkan bila order sudah VOID atau stock commit order sudah final.', 422);
            return;
        }

        $orderStatus = strtoupper(trim((string)($snapshot['order_status'] ?? '')));
        $orderCommitStatus = strtoupper(trim((string)($snapshot['stock_commit_status'] ?? '')));
        $closeAs = '';
        if ($orderStatus === 'VOID') {
            $closeAs = 'VOID';
        } elseif (in_array($orderCommitStatus, ['POSTED', 'REVERSED', 'NOT_REQUIRED'], true)) {
            $closeAs = 'REVERSED';
        }

        if ($closeAs === '') {
            $this->json_error('Snapshot FAILED ini masih butuh retry/rebuild. Tutup manual hanya diizinkan bila order sudah VOID atau stock commit order sudah final.', 422);
            return;
        }

        $this->load->library('PosStockCommitService', null, 'posstockcommitservice');
        $closed = $this->posstockcommitservice->mark_reversed($snapshotId, $closeAs);
        if (!($closed['ok'] ?? false)) {
            $this->json_error((string)($closed['message'] ?? 'Snapshot FAILED tidak bisa ditutup.'), 422);
            return;
        }

        if ($this->db->table_exists('pos_runtime_job')) {
            $this->db->where('snapshot_id', $snapshotId)
                ->where('job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
                ->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])
                ->update('pos_runtime_job', [
                    'status' => 'CANCELLED',
                    'finished_at' => date('Y-m-d H:i:s'),
                    'last_error' => 'Ditutup manual dari audit stock commit POS karena snapshot gagal sudah tidak perlu diproses ulang.',
                ]);
        }

        $this->json_ok([
            'message' => 'Snapshot gagal untuk order ' . (string)($snapshot['order_no'] ?? '-') . ' berhasil ditutup sebagai ' . $closeAs . '.',
            'snapshot_id' => $snapshotId,
            'order_id' => (int)($snapshot['order_id'] ?? 0),
            'commit_status' => $closeAs,
        ]);
    }

    public function order_runtime_jobs_process_all()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $limit = max(1, min(200, (int)($payload['limit'] ?? 50)));
        if ($outletId <= 0) {
            $this->json_error('Pilih outlet dulu sebelum memproses semua pending queue POS.', 422);
            return;
        }

        $this->load->library('PosRuntimeJobService');
        $processed = $this->posruntimejobservice->process_pending_jobs([
            'limit' => $limit,
            'outlet_id' => $outletId,
        ]);
        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Pending queue POS gagal diproses.'), 422, [
                'result' => $processed,
            ]);
            return;
        }

        $this->json_ok([
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
            'outlet_id' => $outletId,
        ]);
    }

    public function order_runtime_failed_jobs_retry_all()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $limit = max(1, min(25, (int)($payload['limit'] ?? 10)));
        @set_time_limit(0);
        $this->load->library('PosRuntimeJobService');
        $failed = $this->posruntimejobservice->failed_jobs([
            'limit' => $limit,
            'outlet_id' => max(0, (int)($payload['outlet_id'] ?? 0)),
            'q' => trim((string)($payload['q'] ?? '')),
        ]);
        if (!($failed['ok'] ?? false)) {
            $this->json_error((string)($failed['message'] ?? 'Data job gagal POS belum tersedia.'), 422);
            return;
        }

        $rows = array_values((array)($failed['rows'] ?? []));
        if (empty($rows)) {
            $this->json_ok([
                'message' => 'Belum ada job gagal yang bisa di-retry.',
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'jobs' => [],
            ]);
            return;
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $job) {
            $jobId = (int)($job['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $retried = $this->posruntimejobservice->retry_job($jobId, $this->current_actor_employee_id());
            if (!($retried['ok'] ?? false)) {
                $failedCount++;
                $results[] = [
                    'job_id' => $jobId,
                    'order_no' => (string)($job['order_no'] ?? ''),
                    'ok' => false,
                    'message' => (string)($retried['message'] ?? 'Retry job gagal.'),
                ];
                continue;
            }

            $latestJob = (array)($retried['job'] ?? []);
            $processed = $this->process_runtime_job_now((int)($latestJob['order_id'] ?? 0), (int)($latestJob['id'] ?? $jobId), 1);
            if (!($processed['ok'] ?? false)) {
                $failedCount++;
                $results[] = [
                    'job_id' => $jobId,
                    'order_no' => (string)($job['order_no'] ?? ''),
                    'ok' => false,
                    'message' => (string)($processed['message'] ?? 'Retry job gagal diproses.'),
                ];
                continue;
            }

            $successCount++;
            $results[] = [
                'job_id' => $jobId,
                'order_no' => (string)($job['order_no'] ?? ''),
                'ok' => true,
                'message' => 'Job berhasil diantrekan ulang dan diproses.',
            ];
        }

        $this->json_ok([
            'message' => 'Retry massal job gagal selesai diproses.',
            'processed_count' => count($results),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'jobs' => $results,
        ]);
    }

    public function runtime_jobs_run($limit = 5, $orderId = 0, $jobId = 0)
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        $options = [
            'limit' => max(1, min(50, (int)$limit)),
            'order_id' => max(0, (int)$orderId),
            'job_id' => max(0, (int)$jobId),
        ];

        $this->load->library('PosRuntimeJobService');
        $result = $this->posruntimejobservice->process_pending_jobs($options);
        $this->load->library('PosAvailabilityQueueService');
        if ($this->posavailabilityqueueservice->isReady()) {
            $result['availability_queue'] = $this->posavailabilityqueueservice->processPendingJobs([
                'limit' => max(1, min(200, (int)$limit)),
            ]);
        } else {
            $result['availability_queue'] = [
                'ok' => true,
                'skipped' => true,
                'message' => 'Antrean availability POS belum diaktifkan oleh migration.',
            ];
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    /**
     * Dedicated CLI entrypoint for cron when no POS order queue is pending.
     */
    public function availability_queue_run($limit = 25)
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        $this->load->library('PosAvailabilityQueueService');
        $result = $this->posavailabilityqueueservice->processPendingJobs([
            'limit' => max(1, min(200, (int)$limit)),
        ]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    private function spawn_runtime_job_worker(int $limit = 1, int $orderId = 0, int $jobId = 0): array
    {
        $phpBinary = $this->resolve_cli_php_binary();
        $indexPath = realpath(FCPATH . 'index.php');
        if ($phpBinary === null || $indexPath === false) {
            return ['ok' => false, 'message' => 'PHP CLI atau front controller Finance tidak ditemukan untuk menjalankan worker background POS.'];
        }

        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $command = 'start /B "" ' . $this->escape_windows_arg($phpBinary)
            . ' ' . $this->escape_windows_arg($indexPath)
            . ' pos runtime_jobs_run '
            . (int)max(1, min(50, $limit)) . ' '
            . (int)max(0, $orderId) . ' '
            . (int)max(0, $jobId)
            . ' >NUL 2>&1';

        @pclose(@popen($command, 'r'));

        return [
            'ok' => true,
            'mode' => 'async_cli_spawn',
        ];
    }

    private function process_runtime_job_now(int $orderId, int $jobId, int $limit = 1): array
    {
        $this->load->library('PosRuntimeJobService');
        return $this->posruntimejobservice->process_pending_jobs([
            'limit' => max(1, min(5, $limit)),
            'order_id' => max(0, $orderId),
            'job_id' => max(0, $jobId),
        ]);
    }

    private function schedule_runtime_job_processing(int $orderId, int $jobId, int $limit = 1): array
    {
        if (!function_exists('fastcgi_finish_request')) {
            return [
                'ok' => false,
                'mode' => 'client_trigger_required',
                'message' => 'FastCGI post-response worker tidak tersedia di environment ini.',
            ];
        }

        $safeOrderId = max(0, $orderId);
        $safeJobId = max(0, $jobId);
        $safeLimit = max(1, min(5, $limit));
        if ($safeOrderId <= 0 || $safeJobId <= 0) {
            return ['ok' => false, 'message' => 'Order/job runtime POS tidak valid untuk diproses background.'];
        }

        if (function_exists('session_write_close')) {
            @session_write_close();
        }
        @ignore_user_abort(true);

        register_shutdown_function(function () use ($safeOrderId, $safeJobId, $safeLimit): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                $CI =& get_instance();
                if (!$CI) {
                    return;
                }
                $CI->load->library('PosRuntimeJobService');
                $CI->posruntimejobservice->process_pending_jobs([
                    'limit' => $safeLimit,
                    'order_id' => $safeOrderId,
                    'job_id' => $safeJobId,
                ]);
            } catch (Throwable $e) {
                log_message('error', 'POS runtime post-response processing failed for order ' . $safeOrderId . ' job ' . $safeJobId . ': ' . $e->getMessage());
            }
        });

        return [
            'ok' => true,
            'mode' => 'fastcgi_post_response',
        ];
    }

    public function order_reversal_preview($id)
    {
        $pageCode = $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->order_reversal_preview((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan preview void/refund POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_reversal_step_up_verify()
    {
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $action = strtoupper(trim((string)($payload['action'] ?? '')));
        if (!in_array($action, ['VOID', 'REFUND'], true)) {
            $this->json_error('Aksi verifikasi ulang tidak valid.', 422);
            return;
        }
        $this->require_permission($this->order_reversal_step_up_page_code($action), 'edit');
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            $this->current_actor_user_id(),
            $action,
            $payload['order_id'] ?? null,
            $payload['password'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    public function order_payment_prepare($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->cashier_payment_prepare((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan pembayaran POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_payment_voucher_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $orderId = (int)$this->input->get('order_id', true);
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(12, (int)$this->input->get('limit', true)));
        $result = $this->Pos_model->search_cashier_vouchers($orderId, $this->current_actor_employee_id(), $q, $limit);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal memeriksa voucher pembayaran POS.'), 422, [
                'rows' => (array)($result['rows'] ?? []),
            ]);
            return;
        }
        $this->json_ok([
            'rows' => (array)($result['rows'] ?? []),
        ]);
    }

    public function order_payment_save()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : $this->order_workspace_page_code('edit');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_cashier_payment($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan pembayaran POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? 'PAID'),
            'paid_now' => (float)($result['paid_now'] ?? 0),
            'entered_now' => (float)($result['entered_now'] ?? 0),
            'deposit_applied_amount' => (float)($result['deposit_applied_amount'] ?? 0),
            'change_total' => (float)($result['change_total'] ?? 0),
            'remaining_due' => (float)($result['remaining_due'] ?? 0),
            'loyalty' => (array)($result['loyalty'] ?? []),
        ]);
    }

    public function order_payment_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $directPrint = $this->Pos_model->direct_print_targets_for_payment((int)$id);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print payment gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_receipt_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');

        $paymentId = $this->Pos_model->final_payment_id_for_order((int)$id);
        if ($paymentId <= 0) {
            $this->json_error('Struk belum bisa dicetak ulang karena payment final belum ada.', 422);
            return;
        }

        $directPrint = $this->Pos_model->direct_print_targets_for_payment($paymentId, false);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print payment gagal disiapkan.'), 422);
            return;
        }

        $this->json_ok([
            'id' => (int)$id,
            'payment_id' => $paymentId,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_void_save()
    {
        $pageCode = $this->order_workspace_page_code('edit');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_order_reversal_step_up('VOID', $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_model->save_order_void($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan void POS.'), 422);
            return;
        }
        $this->Pos_order_monitor_model->sync_order_tasks((int)($payload['order_id'] ?? 0));
        $availabilityRefresh = (array)($result['availability_rebuild'] ?? []);
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'void_no' => (string)($result['void_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? ''),
            'availability_rebuild' => [
                'success_count' => (int)($availabilityRefresh['success_count'] ?? 0),
                'failed_count' => (int)($availabilityRefresh['failed_count'] ?? 0),
            ],
            'warning' => $result['warning'] ?? null,
        ]);
    }

    public function order_refund_save()
    {
        $pageCode = $this->order_workspace_page_code('edit', 'pos.order.paid.index');
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_pos_transaction_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_order_reversal_step_up('REFUND', $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_model->save_order_refund($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan refund POS.'), 422);
            return;
        }
        $this->Pos_order_monitor_model->sync_order_tasks((int)($payload['order_id'] ?? 0));
        $availabilityRefresh = (array)($result['availability_rebuild'] ?? []);
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'refund_no' => (string)($result['refund_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? ''),
            'availability_rebuild' => [
                'success_count' => (int)($availabilityRefresh['success_count'] ?? 0),
                'failed_count' => (int)($availabilityRefresh['failed_count'] ?? 0),
            ],
            'warning' => $result['warning'] ?? null,
        ]);
    }

    public function report_sales()
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->sales_report_filters();
        $dataset = $this->Pos_report_model->sales_summary_report($filters);
        $this->render('pos/report_sales_index', [
            'page_title' => 'Laporan Penjualan POS',
            'active_menu' => 'pos.report.sales',
            'report_nav_active' => 'sales',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
        ]);
    }

    public function report_cost_control()
    {
        $pageCode = $this->report_view_page_code('pos.report.cost_control.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->cost_control_filters();
        $this->render('pos/report_cost_control_index', [
            'page_title' => 'Cost Control POS',
            'active_menu' => 'pos.report.cost_control',
            'report_nav_active' => 'cost_control',
            'filters' => $filters,
            'dashboard' => $this->Cost_control_model->dashboard($filters),
            'divisions' => $this->Cost_control_model->division_options(),
        ]);
    }

    public function report_sales_detail()
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.detail.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->sales_report_filters();
        $dataset = $this->Pos_report_model->sales_detail_report($filters);
        $this->render('pos/report_sales_detail_index', [
            'page_title' => 'Laporan Penjualan Produk POS',
            'active_menu' => 'pos.report.sales.detail',
            'report_nav_active' => 'sales_detail',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_sales_extra()
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.extra.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->sales_report_filters();
        $dataset = $this->Pos_report_model->sales_extra_report($filters);
        $this->render('pos/report_sales_extra_index', [
            'page_title' => 'Laporan Penjualan Extra POS',
            'active_menu' => 'pos.report.sales.extra',
            'report_nav_active' => 'sales_extra',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    /**
     * Audit read-only untuk angka penjualan dan HPP. Halaman sengaja hanya
     * menjalankan query setelah operator menekan tombol agar membuka menu
     * laporan tetap ringan dan tidak mengganggu proses kasir.
     */
    public function report_sales_audit()
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.audit.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->sales_hpp_audit_filters();
        $runAudit = (string)$this->input->get('run', true) === '1';
        $audit = [];
        $auditError = '';

        if ($runAudit) {
            try {
                $audit = $this->Pos_report_model->sales_hpp_integrity_audit($filters);
            } catch (\Throwable $exception) {
                log_message('error', 'POS sales and HPP audit failed: ' . $exception->getMessage());
                $auditError = 'Audit belum dapat dijalankan. Tidak ada transaksi, stok, lot, HPP, atau kas yang diubah. Periksa log server untuk detail teknis.';
            }
        }

        $this->render('pos/report_sales_audit_index', [
            'page_title' => 'Audit Penjualan & HPP POS',
            'active_menu' => 'pos.report.sales.audit',
            'report_nav_active' => 'sales_audit',
            'filters' => $filters,
            'outlets' => $this->Pos_report_model->outlet_options(),
            'run_audit' => $runAudit,
            'audit' => $audit,
            'audit_error' => $auditError,
        ]);
    }

    public function report_sales_transaction($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.detail.index');
        $this->require_permission($pageCode, 'view');
        $order = $this->Pos_model->find_order_draft((int)$id);
        if (!$order) {
            show_404();
        }
        $this->render('pos/report_sales_transaction', [
            'page_title' => 'Detail Transaksi POS',
            'active_menu' => 'pos.report.sales',
            'order' => $order,
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
            'point_ledgers' => $this->Pos_report_model->order_point_ledger_rows((int)$id),
            'stamp_ledgers' => $this->Pos_report_model->order_stamp_ledger_rows((int)$id),
            'voucher_redemptions' => $this->Pos_report_model->order_voucher_redemption_rows((int)$id),
            'voucher_issues' => $this->Pos_report_model->order_voucher_issue_rows((int)$id),
            'margin_audit' => $this->Pos_report_model->order_margin_audit((int)$id),
            'payment_method_options' => $this->Pos_model->deposit_payment_method_options(),
            'can_edit_payment_method' => $this->can_edit_sales_transaction_payment(),
        ]);
    }

    public function report_sales_document_print($id, $documentType = 'invoice')
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.detail.index');
        $this->require_permission($pageCode, 'view');

        $documentType = strtolower(trim((string)$documentType));
        if (!in_array($documentType, ['invoice', 'receipt'], true)) {
            show_404();
            return;
        }

        $order = $this->Pos_model->find_order_draft((int)$id);
        if (!$order) {
            show_404();
            return;
        }

        $reviewUrl = '';
        if ($documentType === 'receipt') {
            $review = $this->Pos_customer_review_model->ensure_for_order((array)($order['header'] ?? []));
            $token = trim((string)($review['review_token'] ?? ''));
            if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
                $reviewUrl = site_url('review/' . strtolower($token));
            }
        }

        $this->load->view('pos/report_sales_document_print', [
            'order' => $order,
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'document_type' => $documentType,
            'logo_url' => is_file(FCPATH . 'assets/img/logo.png') ? base_url('assets/img/logo.png') : '',
            'review_url' => $reviewUrl,
        ]);
    }

    public function report_sales_payment_line_update($id)
    {
        if (!$this->can_edit_sales_transaction_payment()) {
            $this->json_error('Anda tidak memiliki izin untuk mengubah metode pembayaran transaksi POS.', 403);
            return;
        }

        $paymentMethodId = (int)$this->input->post('payment_method_id', true);
        $result = $this->Pos_model->update_payment_line_method((int)$id, $paymentMethodId, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $statusCode = max(400, (int)($result['status_code'] ?? 422));
            $this->json_error((string)($result['message'] ?? 'Gagal memperbarui metode pembayaran.'), $statusCode);
            return;
        }

        $this->json_ok([
            'message' => (string)($result['message'] ?? 'Metode pembayaran berhasil diperbarui.'),
            'line' => (array)($result['line'] ?? []),
        ]);
    }

    public function report_payments()
    {
        $pageCode = $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->payment_report_filters();
        $dataset = $this->Pos_report_model->payment_report($filters);
        $this->render('pos/report_payment_index', [
            'page_title' => 'Laporan Pembayaran POS',
            'active_menu' => 'pos.report.payment',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_daily_sales()
    {
        $pageCode = $this->can('pos.report.daily_sales.index', 'view')
            ? 'pos.report.daily_sales.index'
            : $this->report_view_page_code('pos.report.sales.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->daily_sales_report_filters();
        $dataset = $this->Pos_report_model->daily_sales_report((string)$filters['date'], (int)$filters['outlet_id']);

        $this->load->model('Module_notification_model');
        $this->render('pos/report_daily_sales', [
            'page_title' => 'Daily Sales POS',
            'active_menu' => 'pos.report.daily_sales',
            'report_nav_active' => 'daily_sales',
            'daily_sales_wa_enabled' => in_array('WA', $this->Module_notification_model->enabled_channels('DAILY_SALES'), true),
            'daily_sales_wa_csrf' => $this->pos_transaction_csrf(),
            'filters' => $filters,
            'outlets' => $this->Pos_report_model->outlet_options(),
            'overview' => (array)($dataset['overview'] ?? []),
            'pay_methods' => (array)($dataset['pay_methods'] ?? []),
            'pay_accounts' => (array)($dataset['pay_accounts'] ?? []),
            'shifts' => (array)($dataset['shifts'] ?? []),
            'by_division' => (array)($dataset['by_division'] ?? []),
            'total_purchase' => (float)($dataset['total_purchase'] ?? 0),
            'net_daily_sales' => (float)($dataset['net_daily_sales'] ?? 0),
            'prev_date' => date('Y-m-d', strtotime((string)$filters['date'] . ' -1 day')),
            'next_date' => date('Y-m-d', strtotime((string)$filters['date'] . ' +1 day')),
        ]);
    }

    public function report_daily_sales_notify()
    {
        if (!$this->require_pos_transaction_csrf()) return;
        $pageCode = $this->can('pos.report.daily_sales.index', 'view')
            ? 'pos.report.daily_sales.index' : $this->report_view_page_code('pos.report.sales.index');
        if (!$this->can($pageCode, 'view')) {
            $this->json_error('Anda tidak memiliki izin melihat dan membagikan laporan Daily Sales.', 403);
            return;
        }
        $this->load->model('Module_notification_model');
        if (!in_array('WA', $this->Module_notification_model->enabled_channels('DAILY_SALES'), true)) {
            $this->json_error('Daily Sales (PDF) belum aktif atau belum memiliki grup penerima. Periksa pengaturan WA dan hak paket.', 403);
            return;
        }
        $this->load->library('Daily_sales_pdf');
        try {
            $filters = Daily_sales_pdf::filters($this->input->get('date', true), $this->input->get('outlet_id', true));
            $outletName = 'Semua Outlet';
            if ($filters['outlet_id'] > 0) {
                $options = array_column($this->Pos_report_model->outlet_options(), 'outlet_name', 'id');
                if (!isset($options[$filters['outlet_id']])) throw new InvalidArgumentException('Outlet tidak tersedia. Pilih kembali outlet pada laporan.');
                $outletName = (string)$options[$filters['outlet_id']];
            }
            $dataset = $this->Pos_report_model->daily_sales_report($filters['date'], $filters['outlet_id']);
            $snapshot = Daily_sales_pdf::snapshot($filters, $dataset, $outletName);
            $html = $this->load->view('pos/report_daily_sales_print', $filters + [
                'outlet_name' => $outletName, 'pdf_mode' => true,
            ] + $dataset, true);
            $attachment = $this->daily_sales_pdf->create($html, $filters, $snapshot);
            $result = $this->Module_notification_model->enqueue_daily_sales($filters, $outletName, $snapshot, (int)($this->current_user['id'] ?? 0), $attachment);
            $this->json_ok($result);
        } catch (InvalidArgumentException | RuntimeException $error) {
            $this->json_error($error->getMessage(), 422);
        } catch (Throwable $error) {
            log_message('error', 'Daily Sales PDF notification failed; inspect renderer/storage and queue.');
            $this->json_error('PDF Daily Sales belum dapat diantrekan. Periksa status WA sebelum mencoba kembali.', 500);
        }
    }

    public function report_daily_sales_print()
    {
        $pageCode = $this->can('pos.report.daily_sales.index', 'view')
            ? 'pos.report.daily_sales.index'
            : $this->report_view_page_code('pos.report.sales.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->daily_sales_report_filters();
        $dataset = $this->Pos_report_model->daily_sales_report((string)$filters['date'], (int)$filters['outlet_id']);
        $outletName = '';
        foreach ($this->Pos_report_model->outlet_options() as $outlet) {
            if ((int)($outlet['id'] ?? 0) === (int)$filters['outlet_id']) {
                $outletName = (string)($outlet['outlet_name'] ?? '');
                break;
            }
        }

        $this->load->view('pos/report_daily_sales_print', [
            'date' => (string)$filters['date'],
            'outlet_id' => (int)$filters['outlet_id'],
            'outlet_name' => $outletName,
            'overview' => (array)($dataset['overview'] ?? []),
            'pay_methods' => (array)($dataset['pay_methods'] ?? []),
            'pay_accounts' => (array)($dataset['pay_accounts'] ?? []),
            'shifts' => (array)($dataset['shifts'] ?? []),
            'by_division' => (array)($dataset['by_division'] ?? []),
            'total_purchase' => (float)($dataset['total_purchase'] ?? 0),
            'net_daily_sales' => (float)($dataset['net_daily_sales'] ?? 0),
        ]);
    }

    public function report_payment_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');
        $row = $this->Pos_report_model->find_payment((int)$id);
        if (!$row) {
            show_404();
        }
        $this->render('pos/report_payment_detail', [
            'page_title' => 'Detail Pembayaran POS',
            'active_menu' => 'pos.report.payment',
            'row' => $row,
            'lines' => $this->Pos_report_model->payment_lines((int)$id),
        ]);
    }

    public function report_payment_methods()
    {
        $pageCode = $this->can('pos.report.payment.method.index', 'view')
            ? 'pos.report.payment.method.index'
            : $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->payment_summary_report_filters();
        $dataset = $this->Pos_report_model->payment_method_report($filters);

        $this->render('pos/report_payment_methods', [
            'page_title' => 'Laporan Metode Pembayaran POS',
            'active_menu' => 'pos.report.payment.method',
            'report_nav_active' => 'payment_methods',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_payment_accounts()
    {
        $pageCode = $this->can('pos.report.payment.account.index', 'view')
            ? 'pos.report.payment.account.index'
            : $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->payment_summary_report_filters();
        $dataset = $this->Pos_report_model->payment_account_report($filters);

        $this->render('pos/report_payment_accounts', [
            'page_title' => 'Laporan Rekening Pembayaran POS',
            'active_menu' => 'pos.report.payment.account',
            'report_nav_active' => 'payment_accounts',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_refunds()
    {
        $pageCode = $this->report_view_page_code('pos.report.refund.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->refund_report_filters();
        $dataset = $this->Pos_report_model->refund_report($filters);
        $this->render('pos/report_refund_index', [
            'page_title' => 'Laporan Refund POS',
            'active_menu' => 'pos.report.refund',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_refund_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.refund.index');
        $this->require_permission($pageCode, 'view');
        $row = $this->Pos_report_model->find_refund((int)$id);
        if (!$row) {
            show_404();
        }
        $this->render('pos/report_refund_detail', [
            'page_title' => 'Detail Refund POS',
            'active_menu' => 'pos.report.refund',
            'row' => $row,
            'lines' => $this->Pos_report_model->refund_lines((int)$id),
        ]);
    }

    public function report_voids()
    {
        $pageCode = $this->report_view_page_code('pos.report.void.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->void_report_filters();
        $dataset = $this->Pos_report_model->void_report($filters);
        $this->render('pos/report_void_index', [
            'page_title' => 'Laporan Void POS',
            'active_menu' => 'pos.report.void',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_cashier_close()
    {
        $pageCode = $this->report_view_page_code('pos.report.cashier.close.index');
        $this->require_permission($pageCode, 'view');

        $this->load->model('Finance_report_model');
        $filters = $this->cashier_close_report_filters();
        $accounts = $this->Finance_report_model->active_company_accounts();
        $selectedAccountId = (int)($filters['account_id'] ?? 0);

        $selectedAccount = null;
        foreach ($accounts as $account) {
            if ((int)($account['id'] ?? 0) === $selectedAccountId) {
                $selectedAccount = $account;
                break;
            }
        }

        $filters['account_id'] = $selectedAccountId;
        $filters['account_label'] = $selectedAccountId > 0
            ? $this->cashier_close_account_label($selectedAccount)
            : 'Semua rekening';
        $dataset = $this->Pos_report_model->cashier_close_report($filters);

        $this->render('pos/report_cashier_close_index', [
            'page_title' => 'Laporan Tutup Kasir POS',
            'active_menu' => 'pos.report.cashier.close',
            'report_nav_active' => 'cashier_close',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
            'accounts' => $accounts,
            'selected_account' => $selectedAccount,
        ]);
    }

    public function report_cashier_close_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.cashier.close.index');
        $this->require_permission($pageCode, 'view');

        $shiftId = (int)$id;
        $this->load->model('Finance_report_model');
        $accounts = $this->Finance_report_model->active_company_accounts();
        $focusAccountId = max(0, (int)$this->input->get('account_id', true));

        $audit = $this->Pos_report_model->cashier_close_detail($shiftId, $focusAccountId);
        $report = $this->Pos_model->shift_close_report($shiftId);
        if (!$audit || !$report) {
            show_404();
        }

        $selectedAccount = null;
        foreach ($accounts as $account) {
            if ((int)($account['id'] ?? 0) === $focusAccountId) {
                $selectedAccount = $account;
                break;
            }
        }

        $this->render('pos/report_cashier_close_detail', [
            'page_title' => 'Detail Tutup Kasir POS',
            'active_menu' => 'pos.report.cashier.close',
            'report_nav_active' => 'cashier_close',
            'row' => $audit,
            'report' => $report,
            'accounts' => $accounts,
            'selected_account' => $selectedAccount,
            'focus_account_id' => $focusAccountId,
        ]);
    }

    public function report_void_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.void.index');
        $this->require_permission($pageCode, 'view');
        $row = $this->Pos_report_model->find_void((int)$id);
        if (!$row) {
            show_404();
        }

        $extras = $this->Pos_report_model->void_extras((int)$id);
        $extrasByVoidLine = [];
        foreach ($extras as $extra) {
            $voidLineId = (int)($extra['void_line_id'] ?? 0);
            if (!isset($extrasByVoidLine[$voidLineId])) {
                $extrasByVoidLine[$voidLineId] = [];
            }
            $extrasByVoidLine[$voidLineId][] = $extra;
        }

        $this->render('pos/report_void_detail', [
            'page_title' => 'Detail Void POS',
            'active_menu' => 'pos.report.void',
            'row' => $row,
            'lines' => $this->Pos_report_model->void_lines((int)$id),
            'extras_by_void_line' => $extrasByVoidLine,
        ]);
    }

    private function member_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $memberStatus = strtoupper(trim((string)$this->input->get('member_status', true)));
        if (!in_array($memberStatus, ['ALL', 'ACTIVE', 'INACTIVE', 'SUSPENDED', 'EXPIRED'], true)) {
            $memberStatus = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'member_status' => $memberStatus,
            'tier' => trim((string)$this->input->get('tier', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function sales_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'CONFIRMED', 'PAID_PARTIAL', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED', 'REFUND_PARTIAL', 'REFUND_FULL'], true)) {
            $status = 'ALL';
        }

        $orderScope = strtoupper(trim((string)$this->input->get('order_scope', true)));
        if (!in_array($orderScope, ['ALL', 'REGULAR', 'EVENT'], true)) {
            $orderScope = 'ALL';
        }

        $serviceType = strtoupper(trim((string)$this->input->get('service_type', true)));
        if (!in_array($serviceType, ['ALL', 'DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'ALL';
        }

        $today = date('Y-m-d');
        $dateFrom = $this->optional_report_date_input('date_from');
        $dateTo = $this->optional_report_date_input('date_to');
        if ($dateFrom === '') {
            $dateFrom = $today;
        }
        if ($dateTo === '') {
            $dateTo = $today;
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'order_scope' => $orderScope,
            'service_type' => $serviceType,
            'payment_method_id' => max(0, (int)$this->input->get('payment_method_id', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function cost_control_filters(): array
    {
        $dateFrom = $this->optional_report_date_input('date_from');
        $dateTo = $this->optional_report_date_input('date_to');
        $location = strtoupper(trim((string)$this->input->get('location_type', true)));
        if (!in_array($location, ['ALL', 'REGULAR', 'EVENT'], true)) {
            $location = 'ALL';
        }
        $view = strtolower(trim((string)$this->input->get('view', true)));
        if (!in_array($view, ['flow', 'margin'], true)) {
            $view = 'flow';
        }
        return [
            'date_from' => $dateFrom !== '' ? $dateFrom : date('Y-m-01'),
            'date_to' => $dateTo !== '' ? $dateTo : date('Y-m-d'),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'location_type' => $location,
            'view' => $view,
        ];
    }

    private function sales_hpp_audit_filters(): array
    {
        $today = date('Y-m-d');
        $dateFrom = $this->optional_report_date_input('date_from');
        $dateTo = $this->optional_report_date_input('date_to');
        if ($dateFrom === '') {
            $dateFrom = date('Y-m-01');
        }
        if ($dateTo === '') {
            $dateTo = $today;
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => max(1, min(50, (int)($this->input->get('limit', true) ?: 10))),
        ];
    }

    private function payment_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'PENDING', 'PAID', 'FAILED', 'VOID'], true)) {
            $status = 'ALL';
        }

        $paymentType = strtoupper(trim((string)$this->input->get('payment_type', true)));
        if (!in_array($paymentType, ['ALL', 'FINAL', 'DEPOSIT', 'REFUND'], true)) {
            $paymentType = 'FINAL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'payment_type' => $paymentType,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function refund_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'POSTED', 'VOID'], true)) {
            $status = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function payment_summary_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'PENDING', 'PAID', 'FAILED', 'VOID'], true)) {
            $status = 'PAID';
        }

        $paymentType = strtoupper(trim((string)$this->input->get('payment_type', true)));
        if (!in_array($paymentType, ['ALL', 'FINAL', 'DEPOSIT', 'REFUND'], true)) {
            $paymentType = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'payment_type' => $paymentType,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
        ];
    }

    private function void_report_filters(): array
    {
        $voidScope = strtoupper(trim((string)$this->input->get('void_scope', true)));
        if (!in_array($voidScope, ['ALL', 'FULL', 'PARTIAL'], true)) {
            $voidScope = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'void_scope' => $voidScope,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->optional_report_date_input('date_from'),
            'date_to' => $this->optional_report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function cashier_close_report_filters(): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $dateFrom = $this->optional_report_date_input('date_from');
        $dateTo = $this->optional_report_date_input('date_to');

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'account_id' => max(0, (int)$this->input->get('account_id', true)),
            'date_from' => $dateFrom !== '' ? $dateFrom : $monthStart,
            'date_to' => $dateTo !== '' ? $dateTo : $today,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function daily_sales_report_filters(): array
    {
        return [
            'date' => $this->report_date_input('date'),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
        ];
    }

    private function cashier_close_account_label(?array $account): string
    {
        $row = is_array($account) ? $account : [];
        $code = trim((string)($row['account_code'] ?? ''));
        $name = trim((string)($row['account_name'] ?? ''));
        $bank = trim((string)($row['bank_name'] ?? ''));

        $parts = [];
        if ($code !== '') {
            $parts[] = $code;
        }
        if ($name !== '') {
            $parts[] = $name;
        }

        $label = implode(' - ', $parts);
        if ($bank !== '') {
            $label .= ($label !== '' ? ' | ' : '') . $bank;
        }

        return $label !== '' ? $label : 'Semua rekening';
    }

    private function payment_method_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $methodType = strtoupper(trim((string)$this->input->get('method_type', true)));
        if (!in_array($methodType, ['ALL', 'CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $methodType = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'method_type' => $methodType,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function sales_channel_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $serviceType = strtoupper(trim((string)$this->input->get('service_type', true)));
        if (!in_array($serviceType, ['ALL', 'DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'service_type' => $serviceType,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function deposit_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('payment_status', true)));
        if (!in_array($status, ['PAID', 'PENDING', 'VOID', 'FAILED', 'ALL'], true)) {
            $status = 'PAID';
        }
        $settlement = strtoupper(trim((string)$this->input->get('settlement_status', true)));
        if (!in_array($settlement, ['ALL', 'OPEN', 'PARTIAL', 'FULL'], true)) {
            $settlement = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'payment_status' => $status,
            'settlement_status' => $settlement,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function self_order_table_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
        ];
    }

    private function outlet_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('outlet_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('outlet_q', true)),
            'status' => $status,
            'page' => max(1, (int)$this->input->get('outlet_page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('outlet_limit', true) ?: 25)),
        ];
    }

    private function terminal_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('terminal_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('terminal_q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('terminal_outlet_id', true)),
            'page' => max(1, (int)$this->input->get('terminal_page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('terminal_limit', true) ?: 25)),
        ];
    }

    private function printer_template_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('template_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $documentType = strtoupper(trim((string)$this->input->get('document_type', true)));
        if (!in_array($documentType, ['ALL', 'RECEIPT', 'KITCHEN_TICKET', 'REFUND_SLIP', 'VOID_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('template_q', true)),
            'status' => $status,
            'document_type' => $documentType,
            'page' => max(1, (int)$this->input->get('template_page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('template_limit', true) ?: 10)),
        ];
    }

    private function printer_profile_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('profile_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('profile_q', true)),
            'status' => $status,
            'page' => max(1, (int)$this->input->get('profile_page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('profile_limit', true) ?: 10)),
        ];
    }

    private function printer_device_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('device_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('device_q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('device_outlet_id', true)),
            'page' => max(1, (int)$this->input->get('device_page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('device_limit', true) ?: 10)),
        ];
    }

    private function printer_config_filters(string $prefix): array
    {
        $status = strtoupper(trim((string)$this->input->get($prefix . '_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $filters = [
            'q' => trim((string)$this->input->get($prefix . '_q', true)),
            'status' => $status,
            'page' => max(1, (int)$this->input->get($prefix . '_page', true)),
            'limit' => max(5, min(100, (int)$this->input->get($prefix . '_limit', true) ?: 25)),
        ];
        if ($prefix === 'layout') {
            $filters['document_type'] = strtoupper(trim((string)$this->input->get('layout_document_type', true) ?: 'ALL'));
        }
        if ($prefix === 'rule') {
            $filters['event_code'] = strtoupper(trim((string)$this->input->get('rule_event_code', true) ?: 'ALL'));
        }
        if ($prefix === 'monitor') {
            $filters['status'] = strtoupper(trim((string)$this->input->get('monitor_status', true) ?: 'ALL'));
        }
        return $filters;
    }

    private function customer_review_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('review_status', true)));
        if (!in_array($status, ['ALL', 'OPEN', 'SUBMITTED', 'HIDDEN'], true)) {
            $status = 'ALL';
        }
        $from = trim((string)$this->input->get('review_date_from', true));
        $to = trim((string)$this->input->get('review_date_to', true));
        return [
            'q' => trim((string)$this->input->get('review_q', true)),
            'status' => $status,
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '',
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '',
            'page' => max(1, (int)$this->input->get('review_page', true)),
            'limit' => max(10, min(100, (int)($this->input->get('review_limit', true) ?: 25))),
        ];
    }

    private function customer_review_permission_page(): string
    {
        $hasPage = $this->db->table_exists('sys_page')
            && (int)$this->db->from('sys_page')->where('page_code', 'pos.customer_review.index')->count_all_results() > 0;
        return $hasPage ? 'pos.customer_review.index' : 'pos.printer.index';
    }

    private function customer_review_csrf(): string
    {
        $token = $this->session->userdata(self::CUSTOMER_REVIEW_CSRF_SESSION_KEY);
        if (!is_string($token) || preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::CUSTOMER_REVIEW_CSRF_SESSION_KEY, $token);
        }
        return $token;
    }

    private function require_customer_review_csrf(): bool
    {
        $this->output->set_header('Cache-Control: private, no-store');
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            $this->json_error('Metode request tidak diizinkan.', 405);
            return false;
        }
        // CI/FastCGI canonicalizes the header to X-Pos-Review-Csrf.
        $provided = $this->input->get_request_header(self::CUSTOMER_REVIEW_CSRF_HEADER, false);
        $expected = $this->session->userdata(self::CUSTOMER_REVIEW_CSRF_SESSION_KEY);
        if (!is_string($provided) || !is_string($expected)
            || preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
            || !hash_equals($expected, $provided)
        ) {
            $this->json_error('Sesi formulir ulasan tidak valid. Muat ulang halaman lalu coba kembali.', 403);
            return false;
        }
        return true;
    }

    /** Keep preview QR targets valid without creating a fake receipt review. */
    private function attach_customer_review_preview_url(array &$preview): void
    {
        if (empty($preview['customer_review_qr']['enabled'])) {
            return;
        }
        $url = $this->Pos_customer_review_model->station_url();
        if ($url !== '') {
            $preview['customer_review_qr']['url'] = $url;
        }
    }

    private function printer_config_permission_page(string $pageCode): string
    {
        $hasPage = $this->db->table_exists('sys_page')
            && (int)$this->db->from('sys_page')->where('page_code', $pageCode)->count_all_results() > 0;
        return $hasPage ? $pageCode : 'pos.printer.index';
    }

    private function printer_config_can(string $pageCode, string $action = 'view'): bool
    {
        return $this->can($this->printer_config_permission_page($pageCode), $action);
    }

    private function require_printer_config_permission(string $pageCode, string $action = 'view'): void
    {
        $this->require_permission($this->printer_config_permission_page($pageCode), $action);
    }

    private function printer_general_csrf(): string
    {
        $token = (string)$this->session->userdata(self::POS_PRINTER_GENERAL_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::POS_PRINTER_GENERAL_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_printer_general_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            show_error('Metode request tidak diizinkan.', 405, 'Method Not Allowed');
            return false;
        }

        $providedToken = trim((string)$this->input->post(self::POS_PRINTER_GENERAL_CSRF_FORM_FIELD, false));
        $sessionToken = (string)$this->session->userdata(self::POS_PRINTER_GENERAL_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            show_error('Sesi formulir tampilan cetak tidak valid. Muat ulang halaman lalu coba kembali.', 403, 'Akses Ditolak');
            return false;
        }

        return true;
    }

    /**
     * Menyimpan hanya gambar logo yang berasal dari formulir Printer General.
     * File lama tidak dihapus agar perubahan branding yang keliru tetap dapat
     * dipulihkan manual oleh administrator bila diperlukan.
     */
    private function store_printer_general_logo_upload(): array
    {
        $file = $_FILES['logo_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true];
        }
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Unggah logo gagal. Pilih kembali file PNG atau JPG yang ukurannya tidak lebih dari 1 MB.'];
        }

        $uploadPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assets/uploads/pos-printer-logo';
        if (!is_dir($uploadPath) && !@mkdir($uploadPath, 0775, true) && !is_dir($uploadPath)) {
            return ['ok' => false, 'message' => 'Folder logo cetak belum dapat disiapkan. Hubungi administrator server.'];
        }
        if (!is_writable($uploadPath)) {
            return ['ok' => false, 'message' => 'Folder logo cetak tidak dapat ditulis. Hubungi administrator server.'];
        }

        $config = [
            'upload_path' => $uploadPath,
            'allowed_types' => 'png|jpg|jpeg',
            'max_size' => 1024,
            'max_width' => 2048,
            'max_height' => 2048,
            'encrypt_name' => true,
            'file_ext_tolower' => true,
            'remove_spaces' => true,
        ];
        $this->load->library('upload', $config, 'printer_general_logo_upload');
        $uploader = $this->printer_general_logo_upload;
        if (!$uploader->do_upload('logo_file')) {
            return ['ok' => false, 'message' => 'Logo harus berupa PNG atau JPG, maksimal 1 MB dan 2048 × 2048 piksel.'];
        }

        $upload = (array)$uploader->data();
        $fullPath = (string)($upload['full_path'] ?? '');
        $imageInfo = $fullPath !== '' ? @getimagesize($fullPath) : false;
        $imageType = is_array($imageInfo) ? (int)($imageInfo[2] ?? 0) : 0;
        if (!in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            // Hanya hapus file yang baru saja ditulis pada request ini dan gagal
            // verifikasi isi gambar; tidak menyentuh logo atau data lama.
            if ($fullPath !== '' && is_file($fullPath)) {
                @unlink($fullPath);
            }
            return ['ok' => false, 'message' => 'Isi file bukan gambar PNG atau JPG yang valid.'];
        }

        $fileName = basename((string)($upload['file_name'] ?? ''));
        if ($fileName === '') {
            return ['ok' => false, 'message' => 'Nama file logo tidak valid.'];
        }

        return [
            'ok' => true,
            'logo_url' => base_url('assets/uploads/pos-printer-logo/' . rawurlencode($fileName)),
        ];
    }

    private function stock_live_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'AVAILABLE', 'LIMITED', 'OUT', 'HIDDEN'], true)) {
            $status = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'status' => $status,
            'dirty_only' => !empty($this->input->get('dirty_only', true)),
            'mismatch_only' => !empty($this->input->get('mismatch_only', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function pos_availability_queue_csrf(): string
    {
        $token = (string)$this->session->userdata(self::POS_AVAILABILITY_QUEUE_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::POS_AVAILABILITY_QUEUE_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_pos_availability_queue_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            show_error('Metode request tidak diizinkan.', 405, 'Method Not Allowed');
            return false;
        }

        $providedToken = trim((string)$this->input->post(self::POS_AVAILABILITY_QUEUE_CSRF_FORM_FIELD, false));
        $sessionToken = (string)$this->session->userdata(self::POS_AVAILABILITY_QUEUE_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            show_error('Permintaan antrean ketersediaan POS tidak valid.', 403, 'Akses Ditolak');
            return false;
        }

        return true;
    }

    private function availability_queue_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'QUEUED', 'PROCESSING', 'FAILED', 'SUCCESS', 'CANCELLED'], true)) {
            $status = 'ALL';
        }

        return [
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'per_page' => max(10, min(100, (int)$this->input->get('per_page', true) ?: 25)),
        ];
    }

    private function availability_queue_redirect_url(array $payload): string
    {
        $status = strtoupper(trim((string)($payload['return_status'] ?? 'ALL')));
        if (!in_array($status, ['ALL', 'QUEUED', 'PROCESSING', 'FAILED', 'SUCCESS', 'CANCELLED'], true)) {
            $status = 'ALL';
        }
        $query = [
            'status' => $status,
            'outlet_id' => max(0, (int)($payload['return_outlet_id'] ?? 0)),
            'q' => trim((string)($payload['return_q'] ?? '')),
            'per_page' => max(10, min(100, (int)($payload['return_per_page'] ?? 25))),
            'page' => max(1, (int)($payload['return_page'] ?? 1)),
        ];
        $query = array_filter($query, static function ($value, string $key): bool {
            return $key === 'status' || $key === 'per_page' || $key === 'page' || $value !== '' && $value !== 0;
        }, ARRAY_FILTER_USE_BOTH);

        return 'pos/availability-queue?' . http_build_query($query);
    }

    private function self_order_order_filters(): array
    {
        $paymentTab = strtoupper(trim((string)$this->input->get('payment_tab', true)));
        if (!in_array($paymentTab, ['ALL', 'KASIR', 'QRIS'], true)) {
            $paymentTab = 'ALL';
        }
        $statusTab = strtoupper(trim((string)$this->input->get('status_tab', true)));
        if (!in_array($statusTab, ['ALL', 'NEEDS_VERIFY', 'WAITING_PAYMENT', 'ACTIVE_CASHIER', 'PAID_ORDER', 'REJECTED'], true)) {
            $statusTab = 'NEEDS_VERIFY';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'payment_tab' => $paymentTab,
            'status_tab' => $statusTab,
            'date_from' => $this->optional_report_date_input('date_from') ?: date('Y-m-d'),
            'date_to' => $this->optional_report_date_input('date_to') ?: date('Y-m-d'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ];
    }

    private function reservation_filters(): array
    {
        $statusTab = strtoupper(trim((string)$this->input->get('status_tab', true)));
        if (!in_array($statusTab, ['ACTIVE', 'COMPLETED', 'OVERDUE', 'ALL'], true)) {
            $statusTab = 'ACTIVE';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'status_tab' => $statusTab,
            'date_from' => $this->optional_report_date_input('date_from'),
            'date_to' => $this->optional_report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(10, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function online_food_location_filters(): array
    {
        $freeStatus = strtoupper(trim((string)$this->input->get('free_status', true)));
        if (!in_array($freeStatus, ['ALL', 'FREE', 'NORMAL'], true)) {
            $freeStatus = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'free_status' => $freeStatus,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(10, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function verify_reservation_and_respond(int $reservationId, int $actorEmployeeId, int $actorUserId): void
    {
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $prepared = $this->Pos_reservation_model->prepare_verification($reservationId, $actorEmployeeId, $actorUserId);
        if (!($prepared['ok'] ?? false)) {
            $this->json_error((string)($prepared['message'] ?? 'Reservasi belum siap diverifikasi.'), 422);
            return;
        }

        $orderId = (int)($prepared['order_id'] ?? 0);
        $isPaid = !empty($prepared['is_paid']);
        if ($orderId <= 0) {
            $this->json_error('Order POS hasil reservasi tidak valid.', 422);
            return;
        }

        if (!empty($prepared['already_verified'])) {
            $this->json_ok([
                'id' => $orderId,
                'reservation_id' => $reservationId,
                'already_verified' => true,
                'workspace_bucket' => $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER',
                'target_status' => $isPaid ? 'PAID' : 'CONFIRMED',
                'direct_print_targets' => [],
            ]);
            return;
        }

        if (!empty($prepared['already_pos_finalized'])) {
            $completed = $this->Pos_reservation_model->complete_verification($reservationId, $orderId, $actorEmployeeId, $isPaid, $actorUserId);
            if (!($completed['ok'] ?? false)) {
                $this->json_error((string)($completed['message'] ?? 'Order POS sudah terbentuk, tetapi status reservasi belum dapat diselesaikan.'), 422);
                return;
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            $this->json_ok([
                'id' => $orderId,
                'reservation_id' => $reservationId,
                'recovered_finalization' => true,
                'workspace_bucket' => $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER',
                'target_status' => $isPaid ? 'PAID' : 'CONFIRMED',
                'direct_print_targets' => [],
            ]);
            return;
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'allowed_statuses' => ['PENDING'],
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit reservasi.'), 422);
            return;
        }

        $warningMessages = [];
        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));
        if ($warningMessage !== '') {
            $warningMessages[] = $warningMessage;
        }

        $snapshotId = 0;
        $commitNo = '';
        $jobId = 0;
        $jobCode = '';
        $stockCommitStatus = 'PENDING';
        $kickoff = [
            'ok' => false,
            'mode' => 'not_required',
            'message' => 'Stock commit dilewati karena seluruh produk reservasi belum memiliki recipe.',
        ];

        if (!empty($resolved['lines'])) {
            $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
            if (!($snapshot['ok'] ?? false)) {
                $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit reservasi.'), 422);
                return;
            }
            $snapshotId = (int)($snapshot['id'] ?? 0);
            $commitNo = (string)($snapshot['commit_no'] ?? '');

            $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, $snapshotId, $actorEmployeeId, [
                'event_source' => 'RESERVATION_VERIFY',
                'event_id' => $reservationId,
            ]);
            if (!($queued['ok'] ?? false)) {
                $this->json_error((string)($queued['message'] ?? 'Snapshot reservasi berhasil, tetapi antrean stok gagal dibuat.'), 422, [
                    'snapshot_id' => $snapshotId,
                    'commit_no' => $commitNo,
                ]);
                return;
            }
            $jobId = (int)($queued['job_id'] ?? 0);
            $jobCode = (string)($queued['job_code'] ?? '');
            $markQueued = $this->posstockcommitservice->mark_queued($snapshotId);
            if (!($markQueued['ok'] ?? false)) {
                $this->posruntimejobservice->cancel_job($jobId, 'Snapshot reservasi gagal ditandai queued.');
                $this->json_error((string)($markQueued['message'] ?? 'Gagal menandai stock commit reservasi sebagai queued.'), 422);
                return;
            }
            $stockCommitStatus = 'QUEUED';
            $kickoff = [
                'ok' => false,
                'mode' => 'client_trigger_required',
                'message' => 'Queue stok reservasi akan dipicu dari halaman setelah verifikasi selesai.',
            ];
            if (function_exists('fastcgi_finish_request')) {
                $kickoff = $this->schedule_runtime_job_processing($orderId, $jobId, 1);
            }
        }

        $finalize = $this->Pos_model->finalize_self_order_verification($orderId, $snapshotId, $actorEmployeeId, [
            'payment_mode' => 'RESERVATION',
            'is_paid' => $isPaid,
            'payment' => [],
            'stock_commit_status' => $stockCommitStatus,
            'verify_destination' => $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER',
            'allow_paid_destination' => true,
            'order_label' => 'Reservasi',
            'event_prefix' => 'RESERVATION',
        ]);
        if (!($finalize['ok'] ?? false)) {
            if ($jobId > 0) {
                $this->posruntimejobservice->cancel_job($jobId, 'Order reservasi gagal difinalkan setelah queue dibuat.');
            }
            $this->json_error((string)($finalize['message'] ?? 'Order reservasi gagal difinalkan.'), 422, [
                'snapshot_id' => $snapshotId,
                'commit_no' => $commitNo,
            ]);
            return;
        }

        $completed = $this->Pos_reservation_model->complete_verification($reservationId, $orderId, $actorEmployeeId, $isPaid, $actorUserId);
        if (!($completed['ok'] ?? false)) {
            $this->json_error((string)($completed['message'] ?? 'Order reservasi sudah dibuat, tetapi status reservasi belum dapat diperbarui.'), 422, [
                'order_id' => $orderId,
                'snapshot_id' => $snapshotId,
                'commit_no' => $commitNo,
            ]);
            return;
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);

        $directPrintTargets = [];
        // A ticket is an operational instruction, not a stock-commit result.
        // With snapshotId 0 the existing printer helper intentionally uses all
        // order lines, so a recipe gap cannot silently suppress BAR/KITCHEN.
        $kotPrint = $this->Pos_model->direct_print_targets_for_order_confirm($orderId, $snapshotId);
        if ($kotPrint['ok'] ?? false) {
            $directPrintTargets = array_merge($directPrintTargets, (array)($kotPrint['targets'] ?? []));
        } else {
            $warningMessages[] = (string)($kotPrint['message'] ?? 'Tiket produksi belum dapat disiapkan untuk cetak.');
        }
        $paymentId = (int)($prepared['payment_id'] ?? 0);
        if ($paymentId > 0 && $isPaid) {
            $receiptPrint = $this->Pos_model->direct_print_targets_for_payment($paymentId, true);
            if ($receiptPrint['ok'] ?? false) {
                $directPrintTargets = array_merge($directPrintTargets, (array)($receiptPrint['targets'] ?? []));
            } else {
                $warningMessages[] = (string)($receiptPrint['message'] ?? 'Struk DP belum dapat disiapkan untuk cetak.');
            }
        }

        $this->json_ok([
            'id' => $orderId,
            'reservation_id' => $reservationId,
            'snapshot_id' => $snapshotId,
            'commit_no' => $commitNo,
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => $jobId,
            'runtime_job_code' => $jobCode,
            'runtime_kickoff' => $kickoff,
            'stock_sync' => [
                'queued' => $jobId > 0,
                'status' => $stockCommitStatus,
                'kickoff_ok' => !empty($kickoff['ok']),
            ],
            'stock_commit_status' => $stockCommitStatus,
            'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ($isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER')),
            'target_status' => (string)($finalize['target_status'] ?? ($isPaid ? 'PAID' : 'CONFIRMED')),
            'payment_id' => $paymentId,
            'is_paid' => $isPaid ? 1 : 0,
            'loyalty' => (array)($finalize['loyalty'] ?? []),
            'direct_print_targets' => $directPrintTargets,
            'warning_message' => implode(' ', array_values(array_filter($warningMessages))),
        ]);
    }

    private function verify_self_order_and_respond(int $orderId, int $actorEmployeeId, array $payload = [], array $options = []): void
    {
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $contextMethod = (string)($options['context_method'] ?? 'self_order_verification_context');
        $label = (string)($options['label'] ?? 'self order');
        $labelTitle = (string)($options['label_title'] ?? 'Self Order');
        $eventSource = (string)($options['event_source'] ?? 'SELF_ORDER_VERIFY');
        $eventPrefix = (string)($options['event_prefix'] ?? 'SELF_ORDER');

        $context = $this->Pos_model->{$contextMethod}($orderId);
        if (!($context['ok'] ?? false)) {
            $this->json_error((string)($context['message'] ?? 'Order ' . $label . ' belum siap diverifikasi.'), 422);
            return;
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'allowed_statuses' => ['PENDING', 'PAID'],
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order ' . $label . '.'), 422);
            return;
        }
        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));
        $verifyDestination = strtoupper(trim((string)($payload['verify_destination'] ?? '')));

        if (empty($resolved['lines'])) {
            $finalize = $this->Pos_model->finalize_self_order_verification($orderId, 0, $actorEmployeeId, [
                'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
                'payment_status' => (string)($context['payment_status'] ?? 'PENDING'),
                'is_paid' => !empty($context['is_paid']),
                'payment' => (array)($context['payment'] ?? []),
                'stock_commit_status' => 'NOT_REQUIRED',
                'verify_destination' => $verifyDestination,
                'order_label' => $labelTitle,
                'event_prefix' => $eventPrefix,
            ]);
            if (!($finalize['ok'] ?? false)) {
                $this->json_error((string)($finalize['message'] ?? 'Order ' . $label . ' gagal difinalkan.'), 422);
                return;
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            $this->json_ok([
                'id' => $orderId,
                'snapshot_id' => 0,
                'commit_no' => '',
                'resolved_line_count' => 0,
                'runtime_job_id' => 0,
                'runtime_job_code' => '',
                'runtime_kickoff' => [
                    'ok' => false,
                    'mode' => 'not_required',
                    'message' => 'Stock commit dilewati karena item ' . $label . ' belum memiliki recipe product.',
                ],
                'stock_sync' => [
                    'queued' => false,
                    'status' => 'NOT_REQUIRED',
                    'kickoff_ok' => false,
                ],
                'stock_commit_status' => 'NOT_REQUIRED',
                'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ''),
                'target_status' => (string)($finalize['target_status'] ?? ''),
                'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
                'loyalty' => (array)($finalize['loyalty'] ?? []),
                'direct_print_targets' => [],
                'warning_message' => $warningMessage,
            ]);
            return;
        }

        $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit ' . $label . '.'), 422);
            return;
        }

        $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, (int)$snapshot['id'], $actorEmployeeId, [
            'event_source' => $eventSource,
            'event_id' => $orderId,
        ]);
        if (!($queued['ok'] ?? false)) {
            $this->json_error((string)($queued['message'] ?? 'Snapshot berhasil, tetapi queue runtime ' . $label . ' gagal dibuat.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $markQueued = $this->posstockcommitservice->mark_queued((int)$snapshot['id']);
        if (!($markQueued['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot ' . $label . ' gagal ditandai queued.');
            $this->json_error((string)($markQueued['message'] ?? 'Gagal menandai stock commit ' . $label . ' sebagai queued.'), 422);
            return;
        }

        $finalize = $this->Pos_model->finalize_self_order_verification($orderId, (int)$snapshot['id'], $actorEmployeeId, [
            'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
            'payment_status' => (string)($context['payment_status'] ?? 'PENDING'),
            'is_paid' => !empty($context['is_paid']),
            'payment' => (array)($context['payment'] ?? []),
            'stock_commit_status' => 'QUEUED',
            'verify_destination' => $verifyDestination,
            'order_label' => $labelTitle,
            'event_prefix' => $eventPrefix,
        ]);
        if (!($finalize['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Order ' . $label . ' gagal difinalkan setelah queue dibuat.');
            $this->json_error((string)($finalize['message'] ?? 'Order ' . $label . ' gagal difinalkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);

        $kickoff = [
            'ok' => false,
            'mode' => 'client_trigger_required',
            'message' => 'Queue stok ' . $label . ' akan dipicu dari client setelah response verify diterima.',
        ];
        if (function_exists('fastcgi_finish_request')) {
            $kickoff = $this->schedule_runtime_job_processing($orderId, (int)($queued['job_id'] ?? 0), 1);
        }

        $directPrint = $this->Pos_model->direct_print_targets_for_order_confirm($orderId, (int)$snapshot['id']);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Order diverifikasi, tetapi payload cetak gagal disiapkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $this->json_ok([
            'id' => $orderId,
            'snapshot_id' => (int)$snapshot['id'],
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => (int)($queued['job_id'] ?? 0),
            'runtime_job_code' => (string)($queued['job_code'] ?? ''),
            'runtime_kickoff' => $kickoff,
            'stock_sync' => [
                'queued' => true,
                'status' => 'QUEUED',
                'kickoff_ok' => !empty($kickoff['ok']),
            ],
            'stock_commit_status' => 'QUEUED',
            'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ''),
            'target_status' => (string)($finalize['target_status'] ?? ''),
            'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
            'loyalty' => (array)($finalize['loyalty'] ?? []),
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
            'warning_message' => $warningMessage,
        ]);
    }

    private function order_monitor_filters(): array
    {
        $station = strtoupper(trim((string)$this->input->get('station', true)));
        if (!in_array($station, ['ALL', 'BAR', 'KITCHEN', 'CHECKER'], true)) {
            $station = 'ALL';
        }

        return [
            'station' => $station,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->optional_report_date_input('date_from') ?: date('Y-m-d'),
            'date_to' => $this->optional_report_date_input('date_to') ?: date('Y-m-d'),
        ];
    }

    private function handle_order_monitor_task_action(string $action): void
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_order_monitor_task_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $taskId = max(0, (int)($payload['task_id'] ?? 0));
        if ($taskId <= 0) {
            $this->json_error('Task monitor tidak valid.', 422);
            return;
        }

        $actorEmployeeId = $this->current_actor_employee_id();
        $monitorScope = $this->current_actor_monitor_scope();
        if ($action === 'ack') {
            $ok = $this->Pos_order_monitor_model->ack_task($taskId, $actorEmployeeId, $monitorScope);
            $errorMessage = 'Task gagal diterima.';
        } elseif ($action === 'ready') {
            $ok = $this->Pos_order_monitor_model->ready_task($taskId, $actorEmployeeId, $monitorScope);
            $errorMessage = 'Task gagal ditandai siap.';
        } else {
            $ok = $this->Pos_order_monitor_model->checker_task($taskId, $actorEmployeeId, $monitorScope);
            $errorMessage = 'Task checker gagal diselesaikan.';
        }

        if (!$ok) {
            $this->json_error($errorMessage, 422);
            return;
        }

        $this->json_ok(['task_id' => $taskId]);
    }

    private function handle_order_monitor_bulk_action(string $action): void
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'edit');
        if (!$this->require_order_monitor_task_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $orderId = $this->parse_positive_order_monitor_id($payload['order_id'] ?? null);
        $stationRoleValue = $payload['station_role'] ?? null;
        $stationRole = is_string($stationRoleValue) ? strtoupper(trim($stationRoleValue)) : '';

        if ($action !== 'checker' && ($orderId === null || !in_array($stationRole, ['BAR', 'KITCHEN'], true))) {
            $this->json_error('Order atau stasiun monitor tidak valid.', 422);
            return;
        }
        if ($action === 'checker' && $orderId === null) {
            $this->json_error('Order monitor tidak valid.', 422);
            return;
        }

        $actorEmployeeId = $this->current_actor_employee_id();
        $monitorScope = $this->current_actor_monitor_scope();
        if ($action === 'ack') {
            $ok = $this->Pos_order_monitor_model->ack_order_station($orderId, $stationRole, $actorEmployeeId, $monitorScope);
            $errorMessage = 'Task stasiun gagal diterima.';
        } elseif ($action === 'ready') {
            $ok = $this->Pos_order_monitor_model->ready_order_station($orderId, $stationRole, $actorEmployeeId, $monitorScope);
            $errorMessage = 'Task stasiun gagal ditandai siap.';
        } else {
            $ok = $this->Pos_order_monitor_model->checker_order($orderId, $actorEmployeeId, $monitorScope);
            $errorMessage = 'Task checker gagal diselesaikan.';
        }

        if (!$ok) {
            $this->json_error($errorMessage, 422);
            return;
        }

        if ($action === 'checker') {
            $this->json_ok(['order_id' => $orderId]);
            return;
        }
        $this->json_ok(['order_id' => $orderId, 'station_role' => $stationRole]);
    }

    private function parse_positive_order_monitor_id($value): ?int
    {
        if (is_bool($value) || is_float($value) || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string)$value);
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $parsed === false ? null : (int)$parsed;
    }

    private function order_monitor_task_csrf(): string
    {
        $token = (string)$this->session->userdata(self::POS_ORDER_MONITOR_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::POS_ORDER_MONITOR_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_order_monitor_task_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_order_monitor_task_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = trim((string)$this->input->get_request_header(self::POS_ORDER_MONITOR_CSRF_CI_HEADER, true));
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $providedToken) !== 1) {
            $this->reject_order_monitor_task_csrf(403, 'Permintaan task order monitor tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::POS_ORDER_MONITOR_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-fA-F]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_order_monitor_task_csrf(403, 'Permintaan task order monitor tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_order_monitor_task_csrf(int $statusCode, string $message): void
    {
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
    }

    private function current_actor_monitor_scope(): array
    {
        if ($this->is_superadmin()) {
            return [
                'restricted' => false,
                'station_role' => 'ALL',
                'operational_division_id' => 0,
                'division_name' => '',
            ];
        }

        $employeeId = $this->current_actor_employee_id();
        if ($employeeId <= 0 || !$this->db->table_exists('org_employee')) {
            return [
                'restricted' => false,
                'station_role' => 'ALL',
                'operational_division_id' => 0,
                'division_name' => '',
            ];
        }

        $employee = $this->db->select('e.division_id, d.division_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()
            ->row_array();

        $divisionName = strtoupper(trim((string)($employee['division_name'] ?? '')));
        if (!in_array($divisionName, ['BAR', 'KITCHEN'], true)) {
            return [
                'restricted' => false,
                'station_role' => 'ALL',
                'operational_division_id' => 0,
                'division_name' => $divisionName,
            ];
        }

        $operationalDivisionId = 0;
        if ($this->db->table_exists('mst_operational_division')) {
            $operational = $this->db->select('id')
                ->from('mst_operational_division')
                ->group_start()
                ->where('UPPER(code)', $divisionName)
                ->or_where('UPPER(name)', $divisionName)
                ->group_end()
                ->limit(1)
                ->get()
                ->row_array();
            $operationalDivisionId = (int)($operational['id'] ?? 0);
        }
        if ($operationalDivisionId <= 0) {
            $operationalDivisionId = max(0, (int)($employee['division_id'] ?? 0));
        }

        return [
            'restricted' => $operationalDivisionId > 0,
            'station_role' => $divisionName,
            'operational_division_id' => $operationalDivisionId,
            'division_name' => $divisionName,
        ];
    }

    private function trigger_stock_live_refresh_for_order(int $orderId, string $eventSource, ?int $eventId = null, bool $rebuildNow = true): array
    {
        $order = $this->Pos_model->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order tidak ditemukan untuk refresh stock live.'];
        }

        $header = (array)($order['header'] ?? []);
        $outletId = max(0, (int)($header['outlet_id'] ?? 0));
        $productIds = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if ($outletId <= 0 || empty($productIds)) {
            return ['ok' => false, 'message' => 'Outlet atau produk order tidak siap untuk refresh stock live.'];
        }

        $this->load->library('PosAvailabilityRebuildService');
        $productIds = array_values($productIds);
        $dirty = $this->posavailabilityrebuildservice->mark_dirty($outletId, $productIds, [
            'event_source' => $eventSource,
        ]);
        if (!$rebuildNow) {
            return [
                'ok' => true,
                'success_count' => 0,
                'failed_count' => 0,
                'marked_product_count' => count($productIds),
                'dirty' => $dirty,
            ];
        }
        return $this->posavailabilityrebuildservice->rebuild_products($outletId, $productIds, [
            'trigger_context' => $eventSource,
            'event_source' => $eventSource,
            'event_table' => 'pos_order',
            'event_id' => $eventId ?: $orderId,
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
    }

    private function order_workspace_page_code(string $ability = 'view', string $preferredPageCode = ''): string
    {
        if ($preferredPageCode !== '' && $this->can($preferredPageCode, $ability)) {
            return $preferredPageCode;
        }
        if ($this->can('pos.cashier.index', $ability)) {
            return 'pos.cashier.index';
        }
        return 'pos.order.draft.index';
    }

    private function order_draft_filters(string $workspaceMode = 'MIXED'): array
    {
        $workspaceMode = strtoupper(trim($workspaceMode));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $allowedStatuses = ['DRAFT', 'CONFIRMED', 'PAID', 'ALL'];
        $defaultStatus = 'ALL';
        if ($workspaceMode === 'UNPAID') {
            $allowedStatuses = ['DRAFT', 'CONFIRMED', 'ALL'];
            $defaultStatus = 'DRAFT';
        } elseif ($workspaceMode === 'PAID') {
            $allowedStatuses = ['PAID', 'ALL'];
            $defaultStatus = 'PAID';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $status = $defaultStatus;
        }

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $workspaceMode === 'PAID' ? $today : $monthStart;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $workspaceMode === 'PAID' ? $today : $monthEnd;
        }

        return [
            'q'              => trim((string)$this->input->get('q', true)),
            'status'         => $status,
            'workspace_mode' => $workspaceMode,
            // The cashier's active-order panel must not surface unverified
            // self orders. Those remain actionable in the Self Order queue.
            'cashier_recent' => (string)$this->input->get('cashier_recent', true) === '1',
            'outlet_id'      => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'page'           => max(1, (int)$this->input->get('page', true)),
            'limit'          => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ];
    }

    private function report_view_page_code(string $preferredPageCode): string
    {
        if ($preferredPageCode !== '' && $this->can($preferredPageCode, 'view')) {
            return $preferredPageCode;
        }
        if ($this->can('pos.order.draft.index', 'view')) {
            return 'pos.order.draft.index';
        }
        if ($this->can('pos.cashier.index', 'view')) {
            return 'pos.cashier.index';
        }
        if ($this->can('pos.stock.live.index', 'view')) {
            return 'pos.stock.live.index';
        }
        return 'pos.order.draft.index';
    }

    private function can_edit_sales_transaction_payment(): bool
    {
        return $this->can('pos.report.sales.detail.index', 'edit')
            || $this->can('pos.report.sales.index', 'edit')
            || $this->can('pos.order.draft.index', 'edit')
            || $this->can('pos.cashier.index', 'edit');
    }

    private function report_date_input(string $key): string
    {
        $value = trim((string)$this->input->get($key, true));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return date('Y-m-d');
    }

    private function optional_report_date_input(string $key): string
    {
        $value = trim((string)$this->input->get($key, true));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return '';
    }

    private function current_actor_employee_id(): int
    {
        return max(0, (int)($this->current_user['employee_id'] ?? 0));
    }

    private function current_actor_user_id(): int
    {
        return max(0, (int)($this->current_user['id'] ?? 0));
    }

    private function order_reversal_step_up_page_code(string $action): string
    {
        return $action === 'REFUND'
            ? $this->order_workspace_page_code('edit', 'pos.order.paid.index')
            : $this->order_workspace_page_code('edit');
    }

    private function consume_order_reversal_step_up(string $action, array $payload): bool
    {
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            $this->current_actor_user_id(),
            $action,
            $payload['order_id'] ?? null,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function reservation_deposit_refund_step_up_action(string $closeMode): ?string
    {
        if ($closeMode === 'REJECT') {
            return 'RESERVATION_REJECT_DEPOSIT_REFUND';
        }
        if ($closeMode === 'CANCEL') {
            return 'RESERVATION_CANCEL_DEPOSIT_REFUND';
        }
        return null;
    }

    private function consume_reservation_deposit_refund_step_up(int $reservationId, string $closeMode, array $payload): bool
    {
        $stepUpAction = $this->reservation_deposit_refund_step_up_action($closeMode);
        if ($stepUpAction === null) {
            $this->json_error('Aksi pengembalian DP reservasi tidak valid.', 422);
            return false;
        }
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            $this->current_actor_user_id(),
            $stepUpAction,
            $reservationId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan sebelum mengembalikan DP reservasi.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_order_reprint_step_up(int $orderId, array $payload): bool
    {
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            $this->current_actor_user_id(),
            'ORDER_REPRINT',
            $orderId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function resolve_cli_php_binary(): ?string
    {
        $candidates = [
            'c:/xampp/php/php.exe',
            'C:/xampp/php/php.exe',
        ];
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $candidates[] = PHP_BINARY;
        }
        if (defined('PHP_BINDIR') && PHP_BINDIR) {
            $candidates[] = rtrim(str_replace('\\', '/', PHP_BINDIR), '/') . '/php.exe';
        }

        foreach ($candidates as $candidate) {
            $normalized = str_replace('\\', '/', (string)$candidate);
            if ($normalized !== '' && is_file($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function escape_windows_arg(string $value): string
    {
        return '"' . str_replace('"', '\\"', str_replace('\\', '/', $value)) . '"';
    }

    private function request_payload(): array
    {
        $raw = (string)$this->input->raw_input_stream;
        if ($raw === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function parse_positive_runtime_id($value): ?int
    {
        if (is_bool($value) || is_float($value) || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string)$value);
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $parsed === false ? null : (int)$parsed;
    }

    private function pos_transaction_csrf(): string
    {
        $token = (string)$this->session->userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_pos_transaction_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_pos_transaction_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI normalizes CGI/FastCGI names to title case (for example, ...-Csrf).
        // Keep the browser-facing contract above, but use that canonical name for
        // the request lookup so this guard is safe with exact-key input doubles too.
        $providedToken = trim((string)$this->input->get_request_header(self::POS_TRANSACTION_CSRF_CI_HEADER, true));
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $providedToken) !== 1) {
            $this->reject_pos_transaction_csrf(403, 'Permintaan transaksi POS tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-fA-F]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_pos_transaction_csrf(403, 'Permintaan transaksi POS tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_pos_transaction_csrf(int $statusCode, string $message): void
    {
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
    }

    private function reject_pos_runtime_job_trigger(int $statusCode, string $message, array $data = []): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function save_printer_template_from_form(int $id): array
    {
        $documentType = strtoupper(trim((string)$this->input->post('document_type', true)));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }
        $generalSettings = $this->Pos_model->printer_general_settings();
        $templatePayload = $this->posprinterpreviewservice->payloadFromInput($this->input->post(null, false), $documentType, (array)($generalSettings['payload'] ?? []));
        return $this->Pos_model->save_printer_template([
            'id' => $id,
            'template_code' => trim((string)$this->input->post('template_code', true)),
            'template_name' => trim((string)$this->input->post('template_name', true)),
            'document_type' => $documentType,
            'template_payload' => json_encode($templatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_default' => $this->input->post('is_default') ? 1 : 0,
            'is_active' => $this->input->post('is_active') ? 1 : 0,
        ]);
    }

    private function printer_download_files(): array
    {
        $base = dirname(APPPATH) . '/tools/pos_printer_agent' . DIRECTORY_SEPARATOR;
        return [
            'readme' => ['filename' => 'README.md', 'path' => $base . 'README.md'],
            'requirements' => ['filename' => 'requirements.txt', 'path' => $base . 'requirements.txt'],
            'agent_py' => ['filename' => 'agent.py', 'path' => $base . 'agent.py'],
            'check_saved_printers' => ['filename' => 'check_saved_printers.py', 'path' => $base . 'check_saved_printers.py'],
            'detect_py' => ['filename' => 'detect_printers.py', 'path' => $base . 'detect_printers.py'],
            'run_windows' => ['filename' => 'run_windows.bat', 'path' => $base . 'run_windows.bat'],
            'install_windows_task' => ['filename' => 'install_windows_task.bat', 'path' => $base . 'install_windows_task.bat'],
            'uninstall_windows_task' => ['filename' => 'uninstall_windows_task.bat', 'path' => $base . 'uninstall_windows_task.bat'],
            'detect_windows' => ['filename' => 'detect_windows.bat', 'path' => $base . 'detect_windows.bat'],
            'run_linux' => ['filename' => 'run_linux.sh', 'path' => $base . 'run_linux.sh'],
            'install_linux_service' => ['filename' => 'install_linux_service.sh', 'path' => $base . 'install_linux_service.sh'],
            'uninstall_linux_service' => ['filename' => 'uninstall_linux_service.sh', 'path' => $base . 'uninstall_linux_service.sh'],
            'detect_linux' => ['filename' => 'detect_linux.sh', 'path' => $base . 'detect_linux.sh'],
            'config_example' => ['filename' => 'config.example.json', 'path' => $base . 'config.example.json'],
            'config_json' => ['filename' => 'config.json', 'path' => ''],
        ];
    }

    /**
     * Builds a clean agent package on demand. Active agent config and logs stay
     * on the cashier computer and are deliberately excluded from the archive.
     */
    private function printer_download_bundle(): void
    {
        if (!class_exists('ZipArchive')) {
            show_error('Fitur unduh paket printer membutuhkan ekstensi ZIP pada server.', 500);
            return;
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'finance_printer_agent_');
        if ($temporaryBase === false) {
            show_error('Paket printer belum bisa dibuat karena folder sementara server tidak tersedia.', 500);
            return;
        }

        $archivePath = $temporaryBase . '.zip';
        @unlink($temporaryBase);
        $archive = new ZipArchive();
        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($archivePath);
            show_error('Paket printer belum bisa dibuat. Coba ulangi beberapa saat lagi.', 500);
            return;
        }

        foreach ($this->printer_download_files() as $key => $file) {
            if ($key === 'config_json') {
                continue;
            }
            $path = (string)($file['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $archive->addFile($path, 'pos_printer_agent/' . (string)$file['filename']);
        }
        $archive->close();

        $content = file_get_contents($archivePath);
        @unlink($archivePath);
        if ($content === false) {
            show_error('Paket printer belum bisa dibaca setelah dibuat.', 500);
            return;
        }

        $this->output
            ->set_content_type('application/zip')
            ->set_header('Content-Disposition: attachment; filename="finance-pos-printer-agent.zip"')
            ->set_output($content);
    }

    private function build_printer_agent_config(string $agentName = ''): array
    {
        $agentName = trim($agentName) !== '' ? trim($agentName) : 'POS-PRINTER-AGENT-01';
        $printers = $this->Pos_model->active_printer_devices_for_agent_config($agentName);
        return [
            'agent_name' => $agentName,
            'retry_seconds' => 10,
            'print_retry_count' => 2,
            'log_file' => './agent.log',
            'api' => [
                'enabled' => true,
                'base_url' => rtrim(base_url(), '/'),
                'endpoint' => '/pos/printers/bootstrap',
                'key' => $this->printer_agent_current_key($agentName),
                'agent_name_param' => 'agent_name',
                'refresh_seconds' => 30,
                'timeout_seconds' => 8,
            ],
            'log_max_bytes' => 2097152,
            'log_backup_count' => 3,
            'logo' => [
                'mode' => 'esc_star',
                'threshold' => 180,
                'scale' => 1.5,
                'max_height_dots' => 160,
                'fetch_timeout_seconds' => 10,
            ],
            'printers' => array_map(static function (array $row): array {
                return [
                    'printer_code' => (string)($row['printer_code'] ?? ''),
                    'printer_name' => (string)($row['printer_name'] ?? ''),
                    'printer_role' => (string)($row['printer_role'] ?? 'CUSTOM'),
                    'mac_address' => (string)($row['mac_address'] ?? ''),
                    'python_port' => (int)($row['python_port'] ?? 3000),
                    'paper_width_mm' => (int)($row['paper_width_mm'] ?? 80),
                ];
            }, $printers),
        ];
    }

    private function build_printer_agent_config_json(string $agentName = ''): string
    {
        $json = json_encode(
            $this->build_printer_agent_config($agentName),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return $json !== false ? $json : '{}';
    }

    private function verify_printer_agent_key(): bool
    {
        $agentName = $this->normalise_printer_agent_name((string)$this->input->get('agent_name', true));
        $expectedKeys = $this->printer_agent_expected_keys($agentName);
        if (empty($expectedKeys)) {
            $this->output
                ->set_status_header(503)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Printer agent tidak tersedia.',
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            return false;
        }

        $providedKey = trim((string)$this->input->get_request_header('X-Printer-Key', true));
        $isValid = false;
        foreach ($expectedKeys as $expectedKey) {
            if ($providedKey !== '' && hash_equals($expectedKey, $providedKey)) {
                $isValid = true;
                break;
            }
        }
        if (!$isValid) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Akses printer agent ditolak.',
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            return false;
        }
        return true;
    }

    private function printer_agent_current_key(string $agentName): string
    {
        $keys = $this->printer_agent_expected_keys($this->normalise_printer_agent_name($agentName));
        if (empty($keys)) {
            throw new RuntimeException('POS Printer Agent belum dipasangkan dengan key pada server. Hubungi administrator server untuk melengkapi environment PHP-FPM.');
        }
        return (string)$keys[0];
    }

    private function printer_agent_expected_keys(string $agentName): array
    {
        $configured = trim((string)getenv('POS_PRINTER_AGENT_KEYS'));
        if ($configured !== '') {
            $decoded = json_decode($configured, true);
            if (!is_array($decoded) || $agentName === '' || !array_key_exists($agentName, $decoded)) {
                return [];
            }
            $record = $decoded[$agentName];
            if (is_string($record)) {
                $record = ['current' => $record];
            }
            if (!is_array($record)) {
                return [];
            }
            return $this->printer_agent_non_empty_keys([
                $record['current'] ?? '',
                $record['previous'] ?? '',
            ]);
        }

        return $this->printer_agent_non_empty_keys([
            getenv('POS_PRINTER_BOOTSTRAP_KEY'),
            getenv('POS_PRINTER_BOOTSTRAP_KEY_PREVIOUS'),
        ]);
    }

    private function printer_agent_non_empty_keys(array $values): array
    {
        $keys = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $keys[] = $value;
            }
        }
        return array_values(array_unique($keys));
    }

    private function normalise_printer_agent_name(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z0-9][A-Z0-9_.-]{0,79}$/', $value) ? $value : '';
    }

    private function pos_app_config_value(string $key, string $default = ''): string
    {
        if ($key === '' || !$this->db->table_exists('sys_app_config')) {
            return $default;
        }

        $row = $this->db->select('config_value')
            ->from('sys_app_config')
            ->where('config_key', $key)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ? (string)($row['config_value'] ?? $default) : $default;
    }

    private function daily_recon_selected_ids(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $id = (int)trim((string)$part);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $result = array_keys($ids);
        sort($result);
        return $result;
    }

    private function daily_recon_material_options(): array
    {
        if (!$this->db->table_exists('mst_material')) {
            return [];
        }

        $this->db->select('id, material_code, material_name')
            ->from('mst_material');
        if ($this->db->field_exists('is_active', 'mst_material')) {
            $this->db->where('COALESCE(is_active, 1) = 1', null, false);
        }
        return $this->db
            ->order_by('material_name', 'ASC')
            ->order_by('material_code', 'ASC')
            ->get()
            ->result_array();
    }

    private function daily_recon_component_options(): array
    {
        if (!$this->db->table_exists('mst_component')) {
            return [];
        }

        $this->db->select('id, component_code, component_name')
            ->from('mst_component');
        if ($this->db->field_exists('is_active', 'mst_component')) {
            $this->db->where('COALESCE(is_active, 1) = 1', null, false);
        }
        return $this->db
            ->order_by('component_name', 'ASC')
            ->order_by('component_code', 'ASC')
            ->get()
            ->result_array();
    }

    private function daily_recon_block_message(array $status): string
    {
        $stage = strtoupper((string)($status['stage'] ?? ''));
        $stageLabel = $stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
        $missing = is_array($status['missing'] ?? null) ? $status['missing'] : [];
        $examples = [];
        foreach (array_slice($missing, 0, 5) as $row) {
            $division = trim((string)($row['division_code'] ?? $row['division_name'] ?? ''));
            $domain = trim((string)($row['domain_label'] ?? $row['domain'] ?? 'Recon'));
            $line = trim((string)($row['line_label'] ?? ''));
            $reason = trim((string)($row['reason'] ?? ''));
            $examples[] = trim($division . ' - ' . $domain . ($line !== '' ? ' - ' . $line : '') . ($reason !== '' ? ' (' . $reason . ')' : ''));
        }
        $suffix = '';
        if (!empty($examples)) {
            $suffix = ' Belum lengkap: ' . implode('; ', $examples);
            if (count($missing) > count($examples)) {
                $suffix .= '; dan ' . (count($missing) - count($examples)) . ' item lain';
            }
            $suffix .= '.';
        }
        return 'Kasir belum bisa ' . $stageLabel . ' karena Gate Daily Recon aktif dan checkpoint recon belum lengkap.' . $suffix;
    }

    private function save_pos_app_config(string $key, string $value, string $description = ''): void
    {
        if ($key === '' || !$this->db->table_exists('sys_app_config')) {
            return;
        }

        $row = [
            'config_group' => 'pos',
            'config_key' => $key,
            'config_value' => $value,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db->field_exists('updated_by', 'sys_app_config')) {
            $row['updated_by'] = (int)($this->current_user['id'] ?? 0) ?: null;
        }

        $existing = $this->db->select('config_key')
            ->from('sys_app_config')
            ->where('config_key', $key)
            ->limit(1)
            ->get()
            ->row_array();

        if ($existing) {
            $this->db->where('config_key', $key)->update('sys_app_config', $row);
            return;
        }

        $this->db->insert('sys_app_config', $row);
    }

    private function json_ok(array $data = []): void 
    { 
        $payload = ['ok' => true] + $data; 
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output 
            ->set_status_header(200)
            ->set_content_type('application/json') 
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)); 
        $this->output->_display();
        exit;
    } 
 
    private function json_error(string $message, int $statusCode = 400, array $data = []): void 
    { 
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output 
            ->set_status_header($statusCode) 
            ->set_content_type('application/json') 
            ->set_output(json_encode([ 
                'ok' => false, 
                'message' => $message, 
            ] + $data, JSON_INVALID_UTF8_SUBSTITUTE)); 
        $this->output->_display();
        exit;
    } 
}
