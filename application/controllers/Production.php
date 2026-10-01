<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Production extends MY_Controller
{
    private const COMPONENT_FORMULA_MUTATION_CSRF_SESSION_KEY = 'production_component_formula_mutation_csrf';
    private const COMPONENT_FORMULA_MUTATION_CSRF_CI_HEADER = 'X-Production-Component-Formula-Csrf';
    private const COMPONENT_FORMULA_REVISION_FIELD = 'component_formula_revision';
    private const COMPONENT_ADJUSTMENT_CSRF_SESSION_KEY = 'production_component_adjustment_csrf';
    private const COMPONENT_ADJUSTMENT_CSRF_CI_HEADER = 'X-Production-Component-Adjustment-Csrf';
    private const COMPONENT_BATCH_CSRF_SESSION_KEY = 'production_component_batch_csrf';
    private const COMPONENT_BATCH_CSRF_CI_HEADER = 'X-Production-Component-Batch-Csrf';
    private const COMPONENT_DAILY_RECON_CSRF_SESSION_KEY = 'production_component_daily_recon_csrf';
    private const COMPONENT_DAILY_RECON_CSRF_CI_HEADER = 'X-Production-Component-Daily-Recon-Csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Production_model');
        $this->load->library('ComponentStockWriter');
        $this->load->helper('component_adjustment_reason');
    }

    private function release_session_lock(): void
    {
        if (function_exists('session_write_close')) {
            @session_write_close();
        }
    }

    public function component_stock()
    {
        $this->require_permission('production.component.stock.index', 'view');
        $filters = $this->stock_filters();
        $stockMeta = [];
        $rows = $this->Production_model->component_stock_rows(
            $filters,
            (int)$filters['per_page'],
            (int)$filters['page'],
            $stockMeta
        );

        $this->render('production/component_stock_index', [
            'page_title'       => 'Stok Base/Prepare',
            'rows'             => $rows,
            'filters'          => $filters,
            'stock_meta'       => $stockMeta,
            'location_options' => $this->location_options(),
            'divisions'        => $this->active_divisions(),
        ]);
    }

    public function component_stock_data()
    {
        $this->require_permission('production.component.stock.index', 'view');
        $filters = $this->stock_filters();
        $rows = $this->Production_model->component_stock_rows($filters, 500);
        $this->json_ok(['rows' => $rows]);
    }

    public function component_movements()
    {
        $this->require_permission('production.component.movement.index', 'view');
        $filters = $this->movement_filters();
        $rows = $this->Production_model->component_movement_rows($filters, 500);

        $this->render('production/component_movement_index', [
            'page_title'       => 'Mutasi Base/Prepare',
            'rows'             => $rows,
            'filters'          => $filters,
            'location_options' => $this->location_options(),
            'divisions'        => $this->active_divisions(),
        ]);
    }

    public function component_movements_data()
    {
        $this->require_permission('production.component.movement.index', 'view');
        $filters = $this->movement_filters();
        $rows = $this->Production_model->component_movement_rows($filters, 1000);
        $this->json_ok(['rows' => $rows]);
    }

    public function component_daily()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $matrix = $this->Production_model->component_daily_matrix($filters, 500);

        $this->render('production/component_daily_index', [
            'page_title' => 'Daily Matrix Base/Prepare',
            'immersive_layout' => $this->input->get('matrix_fullscreen', true) === '1',
            'matrix_embedded' => $this->input->get('matrix_fullscreen', true) === '1',
            'filters' => $filters,
            'matrix' => $matrix,
            'location_options' => $this->location_options(),
            'divisions' => $this->active_divisions(),
            // Daily Matrix uses the same draft/post endpoints as the dedicated
            // adjustment and batch pages. Supply each endpoint-scoped token so
            // its quick actions remain usable after their mutation boundaries.
            'component_adjustment_csrf_token' => $this->component_adjustment_csrf(),
            'component_batch_csrf_token' => $this->component_batch_csrf(),
        ]);
    }

    public function component_daily_data()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $matrix = $this->Production_model->component_daily_matrix($filters, 1500);
        $this->json_ok($matrix);
    }

    public function component_monthly()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $rows = $this->Production_model->component_monthly_rows($filters, 500);

        $this->render('production/component_monthly_index', [
            'page_title' => 'Stok Bulanan Base/Prepare',
            'rows' => $rows,
            'filters' => $filters,
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_reconcile()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'view')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->component_reconcile_filters();
        $compare = $this->Production_model->component_reconcile_rows($filters, 500);

        $this->render('production/component_reconcile_index', [
            'page_title' => 'Rekonsiliasi Base/Prepare',
            'active_menu' => 'production.component.reconcile',
            'filters' => $filters,
            'rows' => $compare['rows'] ?? [],
            'summary' => $compare['summary'] ?? [],
            'as_of_date' => $compare['as_of_date'] ?? ($filters['as_of_date'] ?? date('Y-m-d')),
            'location_options' => $this->location_options(),
            'divisions' => $this->active_divisions(),
            'can_value_reconciliation' => $this->can('inventory.stock.value_reconciliation.index', 'view'),
        ]);
    }

    public function component_reconcile_audit()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'view')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->component_reconcile_filters();
        $result = $this->Production_model->component_reconcile_audit((string)($filters['as_of_date'] ?? ''), $filters);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Audit reconcile component gagal.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function component_reconcile_repair()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $this->json_error(
            'Rebuild saldo bulanan dari halaman rekonsiliasi dinonaktifkan agar tidak menimpa hasil hitung fisik. Gunakan Daily Recon untuk koreksi stok fisik, atau Sinkron Lot Ter-audit bila hanya struktur lot yang berbeda.',
            410
        );
    }

    public function component_reconcile_repair_all()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $this->json_error(
            'Repair All dari halaman rekonsiliasi dinonaktifkan agar tidak membangun ulang banyak saldo aktif tanpa review. Periksa satu identity melalui Audit, lalu gunakan Daily Recon atau Sinkron Lot Ter-audit sesuai jenis selisihnya.',
            410
        );
    }

    public function component_lot_repair()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $this->run_component_lot_structural_repair($this->request_payload(), 'Repair struktur lot component selesai.');
    }

    /** Sync a single component's FIFO lot total to match its monthly stock balance. */
    public function component_lot_sync_to_stock()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');

        $this->run_component_lot_structural_repair($this->request_payload(), 'Lot component sudah disamakan dengan saldo stok aktif.');
    }

    /** Insert a corrective ADJUSTMENT_PLUS/MINUS movement log entry so log net equals monthly stock. */
    public function component_movement_log_fix_to_stock()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $this->json_error(
            'Koreksi movement log langsung dinonaktifkan agar histori tidak dibuat ulang dari saldo bulanan. Gunakan Daily Recon untuk stok fisik; kemudian lakukan Sinkron Lot Ter-audit bila lot masih berbeda.',
            410
        );
    }

    /** Sync ALL component FIFO lots that over-state stock (lot > monthly_stock) to match stock. */
    public function component_lot_sync_all()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');

        $payload = $this->request_payload();
        $filters = [
            'as_of_date'    => trim((string)($payload['as_of_date'] ?? date('Y-m-d'))),
            'location_type' => strtoupper(trim((string)($payload['location_type'] ?? ''))),
            'division_id'   => !empty($payload['division_id']) ? (int)$payload['division_id'] : 0,
            'q'             => trim((string)($payload['q'] ?? '')),
            'type'          => strtoupper(trim((string)($payload['type'] ?? ''))),
        ];

        $compare = $this->Production_model->component_reconcile_rows($filters, 2000);
        $allRows = is_array($compare['rows'] ?? null) ? $compare['rows'] : [];

        // Only process rows where lot_qty > balance_qty (lot over-states stock)
        $toRepair = array_values(array_filter($allRows, static function (array $r): bool {
            return round((float)($r['lot_qty'] ?? 0), 4) - round((float)($r['balance_qty'] ?? 0), 4) > 0.01;
        }));

        if (empty($toRepair)) {
            $this->json_ok(['processed' => 0, 'repaired' => 0, 'skipped' => 0, 'failed' => 0, 'results' => []],
                'Tidak ada lot yang melebihi stok, tidak ada yang perlu disesuaikan.');
            return;
        }

        $repaired = 0; $skipped = 0; $failed = 0; $results = [];
        foreach ($toRepair as $row) {
            $locType  = strtoupper((string)($row['location_type'] ?? ''));
            $divId    = $row['division_id'] !== null ? (int)$row['division_id'] : null;
            $compId   = (int)($row['component_id'] ?? 0);
            $uom      = (int)($row['uom_id'] ?? 0);
            $label    = trim((string)($row['component_name'] ?? 'Component #' . $compId));

            if ($compId <= 0 || $uom <= 0) {
                $failed++;
                $results[] = ['label' => $label, 'status' => 'skipped', 'message' => 'Data tidak lengkap.'];
                continue;
            }
            $r = $this->reconcile_component_lot_structure([
                'event_date' => $filters['as_of_date'],
                'location_type' => $locType,
                'division_id' => $divId,
                'component_id' => $compId,
                'uom_id' => $uom,
            ]);
            if (!empty($r['ok'])) {
                if (($r['data']['action'] ?? '') === 'NONE') {
                    $skipped++;
                    $results[] = ['label' => $label, 'status' => 'skipped', 'message' => 'Lot sudah sesuai dengan saldo stok aktif.'];
                } else {
                    $repaired++;
                    $results[] = ['label' => $label, 'status' => 'repaired'];
                }
            } else {
                $failed++;
                $results[] = ['label' => $label, 'status' => 'failed', 'message' => (string)($r['message'] ?? '')];
            }
        }

        $total   = count($toRepair);
        $message = "Repair Lot Semua selesai: {$repaired} disesuaikan, {$skipped} dilewati, {$failed} gagal dari {$total} item.";
        if ($failed > 0) {
            $this->json_ok(['processed' => $total, 'repaired' => $repaired, 'skipped' => $skipped, 'failed' => $failed, 'results' => $results], $message);
        } else {
            $this->json_ok(['processed' => $total, 'repaired' => $repaired, 'skipped' => $skipped, 'failed' => 0, 'results' => $results], $message);
        }
    }

    private function run_component_lot_structural_repair(array $payload, string $successMessage): void
    {
        $result = $this->reconcile_component_lot_structure($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Repair struktur lot component gagal.'), 422, $result['data'] ?? []);
            return;
        }

        $this->json_ok($result['data'] ?? [], $successMessage);
    }

    private function reconcile_component_lot_structure(array $payload): array
    {
        $eventDate = trim((string)($payload['event_date'] ?? $payload['as_of_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            return ['ok' => false, 'message' => 'Tanggal repair lot component tidak valid.'];
        }

        return $this->componentstockwriter->reconcile_lots_to_monthly_stock([
            'event_date' => $eventDate,
            'location_type' => strtoupper(trim((string)($payload['location_type'] ?? ''))),
            'division_id' => isset($payload['division_id']) && $payload['division_id'] !== null && (int)$payload['division_id'] > 0
                ? (int)$payload['division_id'] : null,
            'component_id' => (int)($payload['component_id'] ?? 0),
            'uom_id' => (int)($payload['uom_id'] ?? 0),
            'created_by' => (int)($this->current_user['id'] ?? 0),
        ]);
    }

    /**
     * Legacy endpoint retained only to return a clear response to old browser
     * tabs. A lot-only mutation is not a physical stock adjustment and may not
     * alter FIFO outside the audited reconciliation flow.
     */
    public function component_lot_only_adjust()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $this->json_error(
            'Koreksi lot langsung sudah dinonaktifkan untuk mencegah mismatch. Gunakan Adjustment Component atau Daily Recon untuk stok fisik; gunakan Repair Lot ter-audit hanya untuk memperbaiki struktur lot.',
            410
        );
    }
    public function component_lots()
    {
        $pageCode = $this->can('production.component.lot.index', 'view')
            ? 'production.component.lot.index'
            : 'production.component.batch.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->lot_filters();

        $this->load->library('ComponentLotManager');
        $lotReady = $this->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            show_error((string)($lotReady['message'] ?? 'Schema lot component gagal disiapkan.'));
            return;
        }

        $rows = $this->componentlotmanager->listLots($filters, 500);
        $this->render('production/component_lot_index', [
            'page_title' => 'Lot FIFO Base/Prepare',
            'active_menu' => 'production.component.lot',
            'rows' => $rows,
            'filters' => $filters,
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_lot_usage($lotId)
    {
        $pageCode = $this->can('production.component.lot.index', 'view')
            ? 'production.component.lot.index'
            : 'production.component.batch.index';
        $this->require_permission($pageCode, 'view');
        $detail = $this->Production_model->component_lot_usage_detail((int)$lotId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Detail pemakaian lot component tidak ditemukan.'), 404, 'Not Found');
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $this->render('production/component_lot_usage_detail', [
            'page_title' => 'Pemakaian Lot ' . (string)($header['lot_no'] ?? '#'),
            'active_menu' => 'production.component.lot',
            'detail' => $detail,
        ]);
    }

    public function component_openings()
    {
        $this->require_permission('production.component.opening.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-t');
        }
        $month = substr($dateFrom, 0, 7);
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        $locationType = $this->normalize_location_filter($this->input->get('location_type', true));
        $divisionId = (int)$this->input->get('division_id', true);
        $filters = [
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'month' => $month,
            'location_type' => $locationType,
            'division_id' => $divisionId > 0 ? $divisionId : null,
        ];
        $editOpening = null;
        $editId = (int)$this->input->get('edit', true);
        if ($editId > 0) {
            $detail = $this->Production_model->component_opening_detail($editId);
            if (($detail['ok'] ?? false) && strtoupper((string)($detail['header']['status'] ?? '')) === 'DRAFT') {
                $editOpening = $detail;
            }
        }
        $detailOpening = null;
        $detailId = (int)$this->input->get('detail', true);
        if ($detailId <= 0 && $editId > 0) {
            $detailId = $editId;
        }
        if ($detailId > 0) {
            $detail = $this->Production_model->component_opening_detail($detailId);
            if (($detail['ok'] ?? false)) {
                $detailOpening = $detail;
            }
        }
        $openingTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($openingTab, ['documents', 'detail', 'snapshot'], true)) {
            $openingTab = $detailOpening !== null ? 'detail' : 'documents';
        }
        $rows = $this->Production_model->list_component_openings($filters, 500);
        $this->render('production/component_opening_index', [
            'page_title' => 'Opening Base/Prepare',
            'rows' => $rows,
            'edit_opening' => $editOpening,
            'detail_opening' => $detailOpening,
            'opening_tab' => $openingTab,
            'q' => $q,
            'month' => $month,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage,
            'selected_location_type' => $locationType,
            'selected_division_id' => $divisionId,
            'monthly_rows' => $this->Production_model->list_component_monthly_openings($filters, 500),
            'location_options' => $this->location_options(),
            'components' => $this->active_components(),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
            'component_opening_export_url' => site_url('production/component-openings/export-template'),
            'component_opening_export_existing_url' => site_url('production/component-openings/export-existing'),
            'component_opening_import_url' => site_url('production/component-openings/import'),
        ]);
    }

    public function component_opening_export_template()
    {
        if (!$this->can('production.component.opening.index', 'view') && !$this->can('production.component.opening.index', 'create')) {
            $this->require_permission('production.component.opening.index', 'view');
        }

        $month = $this->component_import_month((string)$this->input->get('month', true));
        $locationGroup = $this->component_import_location_group((string)$this->input->get('location_group', true));
        if ($locationGroup === '') {
            $locationGroup = $this->component_import_location_group((string)$this->input->get('location_type', true));
        }
        if ($locationGroup === '') {
            $locationGroup = 'REGULER';
        }

        $rows = [];
        foreach ($this->active_components() as $component) {
            $rows[] = [
                'opening_month' => $month,
                'location_group' => $locationGroup,
                'division_code' => (string)($component['division_code'] ?? ''),
                'division_name' => (string)($component['division_name'] ?? ''),
                'component_id' => (int)($component['id'] ?? 0),
                'component_code' => (string)($component['component_code'] ?? ''),
                'component_name' => (string)($component['component_name'] ?? ''),
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_code' => (string)($component['uom_code'] ?? ''),
                'opening_qty' => '',
                'unit_cost' => '',
                'note' => '',
            ];
        }

        $headers = [
            'opening_month', 'location_group', 'division_code', 'division_name',
            'component_id', 'component_code', 'component_name', 'uom_id', 'uom_code',
            'opening_qty', 'unit_cost', 'note',
        ];
        $filename = 'component-opening-template-' . strtolower($locationGroup) . '-' . $month . '.xlsx';

        $this->load->library('SimpleSpreadsheetIO');
        $this->simplespreadsheetio->output_xlsx($filename, $headers, $rows, 'Template Opening');
    }

    public function component_opening_export_existing()
    {
        if (!$this->can('production.component.opening.index', 'view') && !$this->can('production.component.opening.index', 'export')) {
            $this->require_permission('production.component.opening.index', 'view');
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'month' => $this->component_import_month((string)$this->input->get('month', true)),
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id' => (int)$this->input->get('division_id', true) > 0 ? (int)$this->input->get('division_id', true) : null,
        ];

        $documents = $this->Production_model->list_component_openings($filters, 1000);
        $rows = [];
        foreach ($documents as $document) {
            $openingId = (int)($document['id'] ?? 0);
            if ($openingId <= 0) {
                continue;
            }
            foreach ($this->Production_model->get_component_opening_lines($openingId) as $line) {
                $rows[] = [
                    'opening_no' => (string)($document['opening_no'] ?? ''),
                    'status' => (string)($document['status'] ?? ''),
                    'opening_month' => substr((string)($document['opening_date'] ?? ''), 0, 7),
                    'location_group' => $this->normalize_location_filter((string)($document['location_type'] ?? '')),
                    'division_name' => (string)($document['division_name'] ?? ''),
                    'component_id' => (string)($line['component_id'] ?? ''),
                    'component_code' => (string)($line['component_code'] ?? ''),
                    'component_name' => (string)($line['component_name'] ?? ''),
                    'uom_id' => (string)($line['uom_id'] ?? ''),
                    'uom_code' => (string)($line['uom_code'] ?? ''),
                    'opening_qty' => (string)($line['opening_qty'] ?? ''),
                    'unit_cost' => (string)($line['unit_cost'] ?? ''),
                    'total_value' => (string)($line['total_value'] ?? ''),
                    'note' => (string)($line['note'] ?? ''),
                ];
            }
        }

        $headers = [
            'opening_no', 'status', 'opening_month', 'location_group', 'division_name',
            'component_id', 'component_code', 'component_name', 'uom_id', 'uom_code',
            'opening_qty', 'unit_cost', 'total_value', 'note',
        ];
        $filename = 'component-opening-existing-' . preg_replace('/[^0-9\-]/', '', (string)($filters['month'] ?? date('Y-m'))) . '.xlsx';

        $this->load->library('SimpleSpreadsheetIO');
        $this->simplespreadsheetio->output_xlsx($filename, $headers, $rows, 'Opening Existing');
    }

    public function component_opening_import()
    {
        $this->require_permission('production.component.opening.index', 'create');

        $defaultMonth = $this->component_import_month((string)$this->input->post('month', true));
        $defaultLocationGroup = $this->component_import_location_group((string)$this->input->post('location_group', true));
        if ($defaultLocationGroup === '') {
            $defaultLocationGroup = $this->component_import_location_group((string)$this->input->post('location_type', true));
        }
        if ($defaultLocationGroup === '') {
            $defaultLocationGroup = 'REGULER';
        }
        $backUrl = $this->component_opening_redirect_url([
            'month' => $defaultMonth,
            'location_type' => $defaultLocationGroup,
        ]);

        $this->load->library('SimpleSpreadsheetIO');
        $parsed = $this->simplespreadsheetio->parse_uploaded_file('import_file');
        if (!($parsed['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($parsed['message'] ?? 'File import component opening tidak valid.'));
            redirect($backUrl);
            return;
        }

        $componentMaps = $this->component_opening_component_maps();
        $uomMap = $this->component_opening_uom_map();
        $groups = [];
        $errors = [];
        $skippedCount = 0;

        foreach ((array)($parsed['rows'] ?? []) as $index => $row) {
            $rowNumber = $index + 2;
            $qty = round($this->component_import_decimal($this->component_import_row_value($row, ['opening_qty', 'qty'], '0')), 4);
            if ($qty <= 0) {
                $skippedCount++;
                continue;
            }

            $component = null;
            $componentId = (int)$this->component_import_row_value($row, ['component_id'], 0);
            if ($componentId > 0 && !empty($componentMaps['id'][$componentId])) {
                $component = $componentMaps['id'][$componentId];
            }
            if ($component === null) {
                $componentCode = strtoupper(trim((string)$this->component_import_row_value($row, ['component_code'], '')));
                if ($componentCode !== '' && !empty($componentMaps['code'][$componentCode])) {
                    $component = $componentMaps['code'][$componentCode];
                }
            }
            if ($component === null) {
                $errors[] = 'Baris ' . $rowNumber . ': Component tidak ditemukan.';
                continue;
            }

            $divisionId = (int)($component['operational_division_id'] ?? 0);
            if ($divisionId <= 0) {
                $errors[] = 'Baris ' . $rowNumber . ': Divisi component belum terdefinisi.';
                continue;
            }

            $month = $this->component_import_month((string)$this->component_import_row_value($row, ['opening_month', 'month'], $defaultMonth));
            $locationGroup = $this->component_import_location_group((string)$this->component_import_row_value($row, ['location_group', 'location_type'], $defaultLocationGroup));
            if ($locationGroup === '') {
                $errors[] = 'Baris ' . $rowNumber . ': Lokasi harus REGULER atau EVENT.';
                continue;
            }

            $uomId = $this->component_import_uom_id($this->component_import_row_value($row, ['uom_id', 'uom_code'], ''), $uomMap, (int)($component['uom_id'] ?? 0));
            if ($uomId <= 0) {
                $errors[] = 'Baris ' . $rowNumber . ': UOM component tidak valid.';
                continue;
            }

            $groupKey = $month . '|' . $locationGroup . '|' . $divisionId;
            if (empty($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $month . ' / ' . $locationGroup . ' / ' . (string)($component['division_name'] ?? $component['division_code'] ?? ('Divisi #' . $divisionId)),
                    'header' => [
                        'id' => 0,
                        'opening_no' => '',
                        'opening_month' => $month,
                        'location_type' => $locationGroup,
                        'division_id' => $divisionId,
                        'notes' => 'Import spreadsheet opening component ' . date('Y-m-d H:i:s'),
                    ],
                    'lines' => [],
                ];
            }

            $groups[$groupKey]['lines'][] = [
                'component_id' => (int)($component['id'] ?? 0),
                'uom_id' => $uomId,
                'opening_qty' => $qty,
                'unit_cost' => round($this->component_import_decimal($this->component_import_row_value($row, ['unit_cost', 'cost'], '0')), 6),
                'note' => (string)$this->component_import_row_value($row, ['note', 'notes'], ''),
            ];
        }

        if (empty($groups) && empty($errors)) {
            $this->session->set_flashdata('error', 'Tidak ada baris import dengan qty opening lebih dari 0.');
            redirect($backUrl);
            return;
        }

        $successDocs = 0;
        $successLines = 0;
        foreach ($groups as $group) {
            $save = $this->Production_model->save_component_opening(
                (array)$group['header'],
                (array)$group['lines'],
                (int)($this->current_user['employee_id'] ?? 0)
            );
            if (!($save['ok'] ?? false)) {
                $errors[] = 'Dokumen ' . (string)($group['label'] ?? '-') . ': ' . (string)($save['message'] ?? 'Gagal menyimpan opening.');
                continue;
            }

            $post = $this->post_component_opening_document((int)($save['id'] ?? 0));
            if (!($post['ok'] ?? false)) {
                $errors[] = 'Dokumen ' . (string)($group['label'] ?? '-') . ': ' . (string)($post['message'] ?? 'Gagal posting opening import.');
                continue;
            }

            $successDocs++;
            $successLines += count((array)$group['lines']);
        }

        $summary = 'Import component opening selesai. Berhasil simpan+post ' . $successDocs . ' dokumen / ' . $successLines . ' baris';
        if ($skippedCount > 0) {
            $summary .= ', dilewati ' . $skippedCount . ' baris kosong/qty 0';
        }
        if (!empty($errors)) {
            $summary .= ', gagal ' . count($errors) . ' item. ' . implode(' | ', array_slice($errors, 0, 5));
        }

        if ($successDocs > 0 && empty($errors)) {
            $this->session->set_flashdata('success', $summary . '.');
        } elseif ($successDocs > 0) {
            $this->session->set_flashdata('warning', $summary);
        } else {
            $this->session->set_flashdata('error', $summary);
        }

        redirect($backUrl);
    }

    public function component_opening_save()
    {
        $this->require_permission('production.component.opening.index', 'create');
        $payload = $this->request_payload();
        $header = [
            'id' => (int)($payload['id'] ?? 0),
            'opening_no' => (string)($payload['opening_no'] ?? ''),
            'opening_month' => (string)($payload['opening_month'] ?? substr((string)($payload['opening_date'] ?? date('Y-m-d')), 0, 7)),
            'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'notes' => (string)($payload['notes'] ?? ''),
        ];
        $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'opening');
        $save = $this->Production_model->save_component_opening($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($save['ok'] ?? false)) {
            $extra = [];
            if (!empty($save['conflict']) && is_array($save['conflict'])) {
                $conflict = (array)$save['conflict'];
                $conflictId = (int)($conflict['id'] ?? 0);
                $conflictStatus = strtoupper((string)($conflict['status'] ?? ''));
                if ($conflictId > 0) {
                    $detailUrl = site_url('production/component-openings') . '?' . http_build_query([
                        'detail' => $conflictId,
                        'tab' => 'detail',
                    ]) . '#component-opening-detail-tabs';
                    $editUrl = site_url('production/component-openings') . '?' . http_build_query([
                        'edit' => $conflictId,
                        'detail' => $conflictId,
                        'tab' => 'detail',
                    ]) . '#component-opening-form-card';
                    $extra['conflict'] = [
                        'id' => $conflictId,
                        'opening_no' => (string)($conflict['opening_no'] ?? ''),
                        'status' => $conflictStatus,
                        'detail_url' => $detailUrl,
                        'edit_url' => $editUrl,
                    ];
                    if ($conflictStatus === 'DRAFT') {
                        $extra['conflict']['action'] = 'EDIT_DRAFT';
                    } elseif ($conflictStatus === 'POSTED') {
                        $extra['conflict']['action'] = 'REOPEN_OR_ADJUST';
                        $extra['conflict']['reopen_url'] = site_url('production/component-openings/reopen/' . $conflictId);
                        $extra['conflict']['adjustment_url'] = site_url('production/component-adjustments') . '?' . http_build_query([
                            'adjustment_date' => (string)($conflict['opening_date'] ?? date('Y-m-d')),
                            'location_type' => (string)($conflict['location_type'] ?? ''),
                            'division_id' => (int)($conflict['division_id'] ?? 0),
                            'notes' => 'Koreksi kekurangan opening ' . (string)($conflict['opening_no'] ?? ''),
                            'source_opening_no' => (string)($conflict['opening_no'] ?? ''),
                        ]);
                    }
                }
            }
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan opening.'), 422, $extra);
            return;
        }
        $this->json_ok(['id' => (int)$save['id']]);
    }

    public function component_opening_post($id)
    {
        $this->require_permission('production.component.opening.index', 'edit');
        $post = $this->post_component_opening_document((int)$id);
        if (!($post['ok'] ?? false)) {
            $this->json_error((string)($post['message'] ?? 'Posting opening gagal.'), (int)($post['status_code'] ?? 422));
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_opening_detail($id)
    {
        $this->require_permission('production.component.opening.index', 'view');
        $detail = $this->Production_model->component_opening_detail((int)$id);
        if (!($detail['ok'] ?? false)) {
            show_404();
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $title = 'Detail Opening Component';
        if (!empty($header['opening_no'])) {
            $title .= ' ' . (string)$header['opening_no'];
        }

        $this->render('production/component_opening_detail', [
            'page_title' => $title,
            'detail' => $detail,
        ]);
    }

    public function component_opening_delete($id)
    {
        $this->require_permission('production.component.opening.index', 'delete');
        $result = $this->Production_model->delete_draft_doc('inv_component_opening', 'inv_component_opening_line', 'opening_id', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus opening.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_opening_void($id)
    {
        $this->require_permission('production.component.opening.index', 'edit');
        $result = $this->Production_model->void_component_opening((int)$id, (int)($this->current_user['employee_id'] ?? 0));
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal void opening.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_opening_reopen($id)
    {
        $this->require_permission('production.component.opening.index', 'edit');
        $id = (int)$id;
        $result = $this->Production_model->reopen_component_opening_draft($id, (int)($this->current_user['employee_id'] ?? 0));
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membuka kembali opening ke draft.'), 422);
            return;
        }

        $editUrl = site_url('production/component-openings') . '?' . http_build_query([
            'edit' => $id,
            'detail' => $id,
            'tab' => 'detail',
        ]) . '#component-opening-form-card';
        $this->json_ok([
            'id' => $id,
            'edit_url' => $editUrl,
        ]);
    }

    public function component_opening_generate_monthly()
    {
        $this->require_permission('production.component.opening.index', 'create');
        $payload = $this->request_payload();
        $result = $this->Production_model->generate_component_monthly_opname_and_opening(
            [
                'month' => (string)($payload['month'] ?? date('Y-m')),
                'location_type' => $this->normalize_location_filter($payload['location_type'] ?? ''),
                'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            ],
            (int)($this->current_user['employee_id'] ?? 0)
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal generate carry-forward component.'), 422);
            return;
        }
        $this->json_ok(['result' => $result['data'] ?? []]);
    }

    public function component_adjustments()
    {
        $this->require_permission('production.component.adjustment.index', 'view');
        $q          = trim((string)$this->input->get('q', true));
        $dateFrom   = trim((string)$this->input->get('date_from', true));
        $dateTo     = trim((string)$this->input->get('date_to', true));
        $divisionId = (int)$this->input->get('filter_division_id', true);
        $locFilter  = $this->normalize_location_filter($this->input->get('filter_location_type', true));
        $perPage    = (int)$this->input->get('per_page', true);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');
        if (!in_array($perPage, [25, 50, 100, 0], true))      $perPage  = 25;

        $listFilters = [
            'q'             => $q,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'division_id'   => $divisionId,
            'location_type' => $locFilter,
        ];
        $rows     = $this->Production_model->list_component_adjustments($listFilters, 500);
        $lineRows = $this->Production_model->list_component_adjustment_detail_rows($listFilters, 2000);

        $activeListTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($activeListTab, ['nota', 'rincian'], true)) {
            $activeListTab = 'nota';
        }
        $prefillDate = trim((string)$this->input->get('adjustment_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
            $prefillDate = date('Y-m-d');
        }
        $prefillDivisionId = (int)$this->input->get('division_id', true);
        $prefill = [
            'adjustment_date'  => $prefillDate,
            'location_type'    => $this->normalize_location_type($this->input->get('location_type', true)),
            'division_id'      => $prefillDivisionId > 0 ? $prefillDivisionId : 0,
            'notes'            => trim((string)$this->input->get('notes', true)),
            'source_opening_no'=> trim((string)$this->input->get('source_opening_no', true)),
        ];
        $this->render('production/component_adjustment_index', [
            'page_title'       => 'Adjustment Base/Prepare',
            'rows'             => $rows,
            'line_rows'        => $lineRows,
            'active_list_tab'  => $activeListTab,
            'q'                => $q,
            'list_filters'     => array_merge($listFilters, ['per_page' => $perPage]),
            'prefill'          => $prefill,
            'location_options' => $this->location_options(),
            'components'       => $this->active_components(),
            'uoms'             => $this->active_uoms(),
            'divisions'        => $this->active_divisions(),
            'component_adjustment_csrf_token' => $this->component_adjustment_csrf(),
        ]);
    }

    public function component_adjustment_save()
    {
        $this->require_permission('production.component.adjustment.index', 'create');
        if (!$this->require_component_adjustment_csrf()) {
            return;
        }
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $payload = $this->request_payload();
            $header = [
                'id' => (int)($payload['id'] ?? 0),
                'adjustment_no' => (string)($payload['adjustment_no'] ?? ''),
                'adjustment_date' => (string)($payload['adjustment_date'] ?? date('Y-m-d')),
                'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
                'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
                'notes' => (string)($payload['notes'] ?? ''),
            ];
            $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'adjustment');
            $save = $this->Production_model->save_component_adjustment($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
            if (!($save['ok'] ?? false)) {
                $this->json_error((string)($save['message'] ?? 'Gagal menyimpan adjustment.'), 422);
                return;
            }
            $this->json_ok(['id' => (int)$save['id']]);
        } catch (Throwable $e) {
            $this->json_backend_exception('penyimpanan adjustment component', $e);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    public function component_stock_snapshot()
    {
        $canAccess = $this->can('production.component.adjustment.index', 'view')
            || $this->can('production.component.daily.index', 'view')
            || $this->can('production.component.stock.index', 'view')
            || $this->can('production.component.batch.index', 'view');
        if (!$canAccess) {
            $this->json_error('Anda tidak memiliki izin untuk melihat snapshot stok component.', 403);
            return;
        }

        $componentId = (int)$this->input->get('component_id', true);
        if ($componentId <= 0) {
            $this->json_error('Component wajib dipilih.', 422);
            return;
        }

        $uomId = (int)$this->input->get('uom_id', true);
        $divisionId = (int)$this->input->get('division_id', true);
        $lotId = (int)$this->input->get('lot_id', true);
        $snapshot = $this->Production_model->component_stock_snapshot(
            $componentId,
            $uomId,
            $divisionId > 0 ? $divisionId : null,
            $this->normalize_location_type($this->input->get('location_type', true)),
            $lotId > 0 ? $lotId : null
        );

        $this->json_ok(['snapshot' => $snapshot]);
    }

    public function component_adjustment_post($id)
    {
        $this->require_permission('production.component.adjustment.index', 'edit');
        if (!$this->require_component_adjustment_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_component_adjustment_step_up((int)$id, $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $post = $this->post_component_adjustment_document(
                (int)$id,
                (int)($this->current_user['employee_id'] ?? 0),
                (int)($this->current_user['id'] ?? 0)
            );
            if (!($post['ok'] ?? false)) {
                $this->json_error((string)($post['message'] ?? 'Posting adjustment gagal.'), 422);
                return;
            }
            $this->json_ok(['id' => (int)$id]);
        } catch (Throwable $e) {
            $this->json_backend_exception('posting adjustment component', $e);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    /** Issues a short-lived proof; the password never reaches the stock writer. */
    public function component_adjustment_step_up_verify()
    {
        $this->require_permission('production.component.adjustment.index', 'edit');
        if (!$this->require_component_adjustment_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_ADJUSTMENT_POST',
            $payload['adjustment_id'] ?? null,
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

    /** One posting path for the regular adjustment page and Daily Recon. */
    private function post_component_adjustment_document(int $id, int $actorEmployeeId, int $actorUserId = 0): array
    {
        $header = $this->Production_model->get_component_adjustment($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Adjustment tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya adjustment DRAFT yang bisa diposting.'];
        }

        $lines = $this->Production_model->get_component_adjustment_lines($id);
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Adjustment belum memiliki rincian.'];
        }
        if (empty($header['division_id'])) {
            $resolvedDivision = $this->Production_model->resolve_component_adjustment_division($lines);
            if (!($resolvedDivision['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($resolvedDivision['message'] ?? 'Divisi adjustment tidak bisa ditentukan untuk posting.')];
            }
            $header['division_id'] = (int)($resolvedDivision['division_id'] ?? 0);
            $this->db->where('id', $id)->update('inv_component_adjustment', ['division_id' => $header['division_id']]);
        }

        $post = $this->componentstockwriter->post_adjustment($header, $lines, $actorEmployeeId, $actorUserId);
        if (!($post['ok'] ?? false)) {
            return $post;
        }
        return ['ok' => true, 'id' => $id, 'data' => $post];
    }

    public function component_adjustment_void($id)
    {
        $this->require_permission('production.component.adjustment.index', 'delete');
        if (!$this->require_component_adjustment_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_component_adjustment_void_step_up((int)$id, $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Production_model->void_component_adjustment((int)$id, (int)($this->current_user['employee_id'] ?? 0));
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal VOID adjustment.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    /** Issues a proof for the documented reversal path; password never reaches the model. */
    public function component_adjustment_void_step_up_verify()
    {
        $this->require_permission('production.component.adjustment.index', 'delete');
        if (!$this->require_component_adjustment_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_ADJUSTMENT_VOID',
            $payload['adjustment_id'] ?? null,
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

    public function component_adjustment_delete($id)
    {
        $this->require_permission('production.component.adjustment.index', 'delete');
        if (!$this->require_component_adjustment_csrf()) {
            return;
        }
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $result = $this->Production_model->delete_draft_doc('inv_component_adjustment', 'inv_component_adjustment_line', 'adjustment_id', (int)$id);
        $this->db->db_debug = $dbDebugBefore;
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus adjustment.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_batches()
    {
        $this->require_permission('production.component.batch.index', 'view');
        $today      = date('Y-m-d');
        $q          = trim((string)$this->input->get('q', true));
        $dateFrom   = trim((string)($this->input->get('date_from', true) ?: date('Y-m-01')));
        $dateTo     = trim((string)($this->input->get('date_to', true) ?: $today));
        $divisionId = (int)$this->input->get('division_id', true);
        $locType       = strtoupper(trim((string)$this->input->get('location_type', true)));
        $componentType = strtoupper(trim((string)$this->input->get('type', true)));

        $filters = [
            'q'             => $q,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'division_id'   => $divisionId > 0 ? $divisionId : null,
            'location_type' => $locType,
            'type'          => $componentType,
        ];
        $rows = $this->Production_model->list_component_batches($filters, 500);
        $this->render('production/component_batch_index', [
            'page_title'       => 'Batch Produksi Base/Prepare',
            'rows'             => $rows,
            'filters'          => $filters,
            'q'                => $q,
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'filter_division'  => $divisionId,
            'filter_location'  => $locType,
            'location_options' => $this->location_options(),
            'components'       => $this->active_components(),
            'materials'        => $this->active_materials(),
            'uoms'             => $this->active_uoms(),
            'divisions'        => $this->active_divisions(),
            'component_batch_csrf_token' => $this->component_batch_csrf(),
        ]);
    }

    public function component_batch_save()
    {
        $this->require_permission('production.component.batch.index', 'create');
        if (!$this->require_component_batch_csrf()) {
            return;
        }
        $this->release_session_lock();
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
        $payload = $this->request_payload();
        $header = [
            'id' => (int)($payload['id'] ?? 0),
            'batch_no' => (string)($payload['batch_no'] ?? ''),
            'batch_date' => (string)($payload['batch_date'] ?? date('Y-m-d')),
            'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'component_id' => (int)($payload['component_id'] ?? 0),
            'output_qty' => (float)($payload['output_qty'] ?? 0),
            'output_uom_id' => (int)($payload['output_uom_id'] ?? 0),
            'scaling_mode' => (string)($payload['scaling_mode'] ?? 'BATCH'),
            'batch_count' => (float)($payload['batch_count'] ?? 0),
            'reference_line_no' => (int)($payload['reference_line_no'] ?? 0),
            'reference_actual_qty' => (float)($payload['reference_actual_qty'] ?? 0),
            'notes' => (string)($payload['notes'] ?? ''),
        ];
        $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'batch');
        $save = $this->Production_model->save_component_batch($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($save['ok'] ?? false)) {
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan batch.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$save['id']]);
        } catch (Throwable $e) {
            $this->json_backend_exception('penyimpanan batch component', $e);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    public function component_batch_preview()
    {
        $this->require_permission('production.component.batch.index', 'view');
        $this->release_session_lock();
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $payload = $this->request_payload();
            if (empty($payload)) {
                $payload = [
                    'component_id' => (int)$this->input->get('component_id', true),
                    'location_type' => (string)$this->input->get('location_type', true),
                    'batch_date' => (string)$this->input->get('batch_date', true),
                    'scaling_mode' => (string)$this->input->get('scaling_mode', true),
                    'batch_count' => (float)$this->input->get('batch_count', true),
                    'reference_line_no' => (int)$this->input->get('reference_line_no', true),
                    'reference_actual_qty' => (float)$this->input->get('reference_actual_qty', true),
                ];
            }
            $preview = $this->Production_model->component_batch_preview([
                'component_id' => (int)($payload['component_id'] ?? 0),
                'location_type' => (string)($payload['location_type'] ?? ''),
                'batch_date' => (string)($payload['batch_date'] ?? date('Y-m-d')),
                'scaling_mode' => (string)($payload['scaling_mode'] ?? 'BATCH'),
                'batch_count' => (float)($payload['batch_count'] ?? 0),
                'reference_line_no' => (int)($payload['reference_line_no'] ?? 0),
                'reference_actual_qty' => (float)($payload['reference_actual_qty'] ?? 0),
            ]);
            if (!($preview['ok'] ?? false)) {
                $this->json_error((string)($preview['message'] ?? 'Preview batch gagal.'), 422);
                return;
            }
            $this->json_ok($preview);
        } catch (Throwable $e) {
            $this->json_backend_exception('preview batch component', $e);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    public function component_batch_post($id)
    {
        $this->require_permission('production.component.batch.index', 'edit');
        if (!$this->require_component_batch_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_component_batch_step_up((int)$id, $payload, 'COMPONENT_BATCH_POST')) {
            return;
        }
        unset($payload['step_up_proof']);
        $this->release_session_lock();
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $id = (int)$id;
            $header = $this->Production_model->get_component_batch($id);
            if (!$header) {
                $this->json_error('Batch tidak ditemukan.', 404);
                return;
            }
            if (strtoupper((string)$header['status']) !== 'DRAFT') {
                $this->json_error('Hanya batch DRAFT yang bisa diposting.', 422);
                return;
            }
            $inputs = $this->Production_model->get_component_batch_inputs($id);
            $post = $this->componentstockwriter->post_batch($header, $inputs, (int)($this->current_user['employee_id'] ?? 0));
            if (!($post['ok'] ?? false)) {
                $this->json_error((string)($post['message'] ?? 'Posting batch gagal.'), 422);
                return;
            }
            $this->json_ok([
                'id' => $id,
                'recovery_warnings' => $post['recovery_warnings'] ?? [],
            ]);
        } catch (Throwable $e) {
            $this->json_backend_exception('posting batch component', $e);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    /** Issues a short-lived proof before a batch changes component stock and cost. */
    public function component_batch_step_up_verify()
    {
        $this->require_permission('production.component.batch.index', 'edit');
        if (!$this->require_component_batch_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_BATCH_POST',
            $payload['batch_id'] ?? null,
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

    public function component_batch_status($id)
    {
        $this->require_permission('production.component.batch.index', 'view');
        $this->release_session_lock();
        $id = (int)$id;
        $header = $this->Production_model->get_component_batch($id);
        if (!$header) {
            $this->json_error('Batch tidak ditemukan.', 404);
            return;
        }

        $this->json_ok([
            'id' => $id,
            'status' => strtoupper((string)($header['status'] ?? '')),
            'posted_at' => (string)($header['posted_at'] ?? ''),
            'updated_at' => (string)($header['updated_at'] ?? ''),
        ]);
    }

    public function component_batch_delete($id)
    {
        $this->require_permission('production.component.batch.index', 'delete');
        if (!$this->require_component_batch_csrf()) {
            return;
        }
        $result = $this->Production_model->delete_draft_doc('inv_component_batch', 'inv_component_batch_input', 'batch_id', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus batch.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_batch_void($id)
    {
        $this->require_permission('production.component.batch.index', 'delete');
        if (!$this->require_component_batch_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        if (!$this->consume_component_batch_step_up((int)$id, $payload, 'COMPONENT_BATCH_VOID')) {
            return;
        }
        unset($payload['step_up_proof']);
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Production_model->void_component_batch((int)$id, (int)($this->current_user['employee_id'] ?? 0));
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal VOID batch.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    /** Issues a separate one-use proof for reversal of a posted component batch. */
    public function component_batch_void_step_up_verify()
    {
        $this->require_permission('production.component.batch.index', 'delete');
        if (!$this->require_component_batch_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_BATCH_VOID',
            $payload['batch_id'] ?? null,
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

    public function component_batch_usage($id)
    {
        $this->require_permission('production.component.batch.index', 'view');
        $this->release_session_lock();
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $detail = $this->Production_model->component_batch_usage_detail((int)$id);
        } catch (Throwable $e) {
            $this->json_backend_exception('detail pemakaian batch', $e);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($detail['ok'] ?? false)) {
            $this->json_error((string)($detail['message'] ?? 'Detail pemakaian batch tidak ditemukan.'), 404);
            return;
        }
        $this->json_ok($detail);
    }

    public function component_batch_usage_page($id)
    {
        $this->require_permission('production.component.batch.index', 'view');
        $this->release_session_lock();
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $detail = $this->Production_model->component_batch_usage_detail((int)$id);
        } catch (Throwable $e) {
            log_message('error', 'component batch detail page fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            show_error('Rincian batch belum dapat dibuka. Muat ulang halaman. Jika masih berulang, pastikan migration rincian input batch sudah dijalankan.', 500, 'Rincian Batch Belum Tersedia');
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Detail pemakaian batch tidak ditemukan.'), 404, 'Rincian Batch Tidak Ditemukan');
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $title = 'Detail Usage Batch';
        if (!empty($header['batch_no'])) {
            $title .= ' ' . (string)$header['batch_no'];
        }

        $this->render('production/component_batch_usage_detail', [
            'page_title' => $title,
            'detail' => $detail,
        ]);
    }

    public function component_picker_search()
    {
        $canAccess = $this->can('production.component.opening.index', 'view')
            || $this->can('production.component.adjustment.index', 'view')
            || $this->can('production.component.batch.index', 'view')
            || $this->can('production.component.category.index', 'view')
            || $this->can('production.component.master.index', 'view')
            || $this->can('production.component.formula.index', 'view');
        if (!$canAccess) {
            $this->json_error('Anda tidak memiliki izin untuk pencarian component.', 403);
            return;
        }

        $entity = strtoupper(trim((string)$this->input->get('entity', true)));
        if (!in_array($entity, ['COMPONENT', 'MATERIAL'], true)) {
            $entity = 'COMPONENT';
        }
        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }
        $excludeId = (int)$this->input->get('exclude_id', true);
        $componentType = strtoupper(trim((string)$this->input->get('component_type', true)));
        $divisionId = (int)$this->input->get('division_id', true);
        $locationType = $this->normalize_location_type($this->input->get('location_type', true));
        $batchDate = trim((string)$this->input->get('batch_date', true));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $batchDate)) {
            $batchDate = '';
        }

        $rows = $this->Production_model->search_picker_options($entity, $q, $limit, [
            'exclude_id' => $excludeId,
            'component_type' => $componentType,
            'division_id' => $divisionId > 0 ? $divisionId : null,
            'location_type' => $locationType,
            'batch_date' => $batchDate,
        ]);
        $this->json_ok(['rows' => $rows]);
    }

    public function component_categories()
    {
        $this->require_permission('production.component.category.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = $this->Production_model->list_component_categories(['q' => $q], 300);
        $this->render('production/component_category_index', [
            'page_title' => 'Kategori Base/Prepare',
            'rows' => $rows,
            'q' => $q,
            'components_for_mapping' => $this->Production_model->list_components_for_mapping(1500),
            'unmapped_components' => $this->Production_model->list_unmapped_components(300),
        ]);
    }

    public function component_category_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('production.component.category.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Production_model->save_component_category($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan kategori.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function component_category_toggle($id)
    {
        $this->require_permission('production.component.category.index', 'edit');
        $result = $this->Production_model->toggle_active('mst_component_category', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal ubah status kategori.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'is_active' => (int)$result['is_active']]);
    }

    public function component_category_quick_map()
    {
        $this->require_permission('production.component.category.index', 'edit');
        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $categoryId = (int)($payload['component_category_id'] ?? 0);
        $result = $this->Production_model->quick_map_component_category($componentId, $categoryId);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mapping kategori.'), 422);
            return;
        }
        $this->json_ok(['id' => $componentId]);
    }

    public function component_masters()
    {
        $this->require_permission('production.component.master.index', 'view');
        $filters = $this->component_master_filters();
        $this->render('production/component_master_index', [
            'page_title' => 'Master Base/Prepare',
            'filters' => $filters,
            'categories' => $this->Production_model->list_component_categories([], 1000),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
            'product_divisions' => $this->active_product_divisions(),
        ]);
    }

    public function component_masters_data()
    {
        $this->require_permission('production.component.master.index', 'view');
        $filters = $this->component_master_filters();
        $result = $this->Production_model->list_components_paginated($filters);
        $this->json_ok($result + ['filters' => $filters]);
    }

    public function component_master_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('production.component.master.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Production_model->save_component_master($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan master component.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function component_master_toggle($id)
    {
        $this->require_permission('production.component.master.index', 'edit');
        $result = $this->Production_model->toggle_active('mst_component', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal ubah status master component.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'is_active' => (int)$result['is_active']]);
    }

    public function component_master_usage($componentId)
    {
        $this->require_permission('production.component.master.index', 'view');
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            show_error('Component tidak valid.', 422, 'Invalid Request');
            return;
        }
        $detail = $this->Production_model->component_usage_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Pemakaian component tidak ditemukan.'), 404, 'Not Found');
            return;
        }
        $this->render('production/component_usage_detail', [
            'page_title' => 'Pemakaian Component',
            'detail' => $detail,
        ]);
    }

    public function component_formulas()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $filters = $this->component_formula_filters();
        $this->render('production/component_formula_index', [
            'page_title' => 'Resep / Formula Base/Prepare',
            'filters' => $filters,
            'categories' => $this->Production_model->list_component_categories([], 1000),
            'divisions' => $this->active_divisions(),
            'uoms' => $this->active_uoms(),
            'materials' => $this->active_materials(),
            'components' => $this->active_components(),
        ]);
    }

    public function component_formulas_data()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $filters = $this->component_formula_filters();
        $result = $this->Production_model->list_component_formula_components_paginated($filters);
        $this->json_ok($result + ['filters' => $filters]);
    }

    public function component_formula_detail()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $componentId = (int)$this->input->get('component_id', true);
        if ($componentId <= 0) {
            $this->json_error('Component wajib dipilih.', 422);
            return;
        }
        $detail = $this->Production_model->component_formula_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            $this->json_error((string)($detail['message'] ?? 'Formula tidak ditemukan.'), 404);
            return;
        }
        $this->json_ok($detail);
    }

    public function component_formula_source_search()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $lineType = strtoupper(trim((string)$this->input->get('line_type', true)));
        $q = trim((string)$this->input->get('q', true));
        $componentId = (int)$this->input->get('component_id', true);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }

        if ($lineType === 'MATERIAL') {
            $rows = $this->db->select('m.id, m.material_code AS code, m.material_name AS name, u.code AS uom_code')
                ->from('mst_material m')
                ->join('mst_uom u', 'u.id = m.content_uom_id', 'left')
                ->where('m.is_active', 1)
                ->group_start()
                    ->like('m.material_name', $q)
                    ->or_like('m.material_code', $q)
                ->group_end()
                ->order_by('m.material_name', 'ASC')
                ->limit($limit)
                ->get()->result_array();
            $this->json_ok(['rows' => $rows]);
            return;
        }

        $componentType = '';
        if ($componentId > 0) {
            $parent = $this->db->select('component_type')->from('mst_component')->where('id', $componentId)->limit(1)->get()->row_array();
            $componentType = strtoupper((string)($parent['component_type'] ?? ''));
        }

        $this->db->select('c.id, c.component_code AS code, c.component_name AS name, c.component_type, u.code AS uom_code')
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->where('c.is_active', 1);
        if ($componentId > 0) {
            $this->db->where('c.id <>', $componentId);
        }
        if ($componentType === 'BASE') {
            $this->db->where('c.component_type', 'BASE');
        } elseif ($componentType === 'PREPARE') {
            $this->db->where_in('c.component_type', ['BASE', 'PREPARE']);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->group_end();
        }
        $rows = $this->db->order_by('c.component_type', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->limit($limit)
            ->get()->result_array();
        $this->json_ok(['rows' => $rows]);
    }

    public function component_formula_show($componentId)
    {
        $this->require_permission('production.component.formula.index', 'view');
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            show_error('Component tidak valid.', 422, 'Invalid Request');
            return;
        }
        $detail = $this->Production_model->component_formula_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Formula tidak ditemukan.'), 404, 'Not Found');
            return;
        }
        $canRestore = $this->can('production.component.formula.index', 'edit');
        $this->render('production/component_formula_detail', [
            'page_title' => 'Detail Formula Component',
            'detail' => $detail,
            'versions' => $this->Production_model->component_formula_versions($componentId),
            'can_restore' => $canRestore,
            'production_component_formula_mutation_csrf' => $canRestore ? $this->component_formula_mutation_csrf() : '',
            'component_formula_revision' => $this->Production_model->component_formula_revision($componentId),
        ]);
    }

    public function component_formula_edit($componentId)
    {
        $this->require_permission('production.component.formula.index', 'edit');
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            show_error('Component tidak valid.', 422, 'Invalid Request');
            return;
        }
        $detail = $this->Production_model->component_formula_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Formula tidak ditemukan.'), 404, 'Not Found');
            return;
        }
        $sourceDivisionRows = $this->db
            ->select('id, name')
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->order_by('name', 'ASC')
            ->get()->result_array();
        $sourceDivisions = array_map(fn($d) => ['value' => (int)$d['id'], 'label' => (string)$d['name']], $sourceDivisionRows);
        $this->render('production/component_formula_edit', [
            'page_title' => 'Edit Formula Component',
            'detail' => $detail,
            'uoms' => $this->active_uoms(),
            'materials' => $this->active_materials(),
            'components' => $this->active_components(),
            'source_divisions' => $sourceDivisions,
            'production_component_formula_mutation_csrf' => $this->component_formula_mutation_csrf(),
            'component_formula_revision' => $this->Production_model->component_formula_revision($componentId),
        ]);
    }

    public function component_formula_save()
    {
        $this->require_component_formula_save_access();
        if (!$this->require_component_formula_mutation_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('production.component.formula.index', $id > 0 ? 'edit' : 'create');
        // Formula wajib disimpan sebagai satu snapshot melalui endpoint bulk.
        // Jangan hidupkan kembali writer per-baris: jalur itu tidak dapat
        // menjaga revision lock, audit payload, dan histori formula konsisten.
        $this->json_error(
            'Endpoint simpan satu baris formula sudah dipensiunkan. Gunakan editor Formula Component terbaru.',
            410
        );
    }

    public function component_formula_save_bulk()
    {
        $this->require_permission('production.component.formula.index', 'edit');
        if (!$this->require_component_formula_mutation_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $lines = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : [];
        $expectedRevision = (string)($payload[self::COMPONENT_FORMULA_REVISION_FIELD] ?? '');
        if (preg_match('/\A[0-9a-f]{64}\z/D', $expectedRevision) !== 1) {
            $this->json_error('Snapshot formula tidak valid. Muat ulang halaman sebelum menyimpan.', 422);
            return;
        }
        $sourceIp = method_exists($this->input, 'ip_address')
            ? trim((string)$this->input->ip_address())
            : '';
        $result = $this->Production_model->save_component_formula_bulk(
            $componentId,
            $lines,
            $expectedRevision,
            [
                'actor_user_id' => !empty($this->current_user['id']) ? (int)$this->current_user['id'] : null,
                'source_ip' => $sourceIp !== '' ? substr($sourceIp, 0, 45) : null,
            ]
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error(
                (string)($result['message'] ?? 'Gagal simpan formula bulk.'),
                (int)($result['status'] ?? 422)
            );
            return;
        }
        $this->json_ok(['component_id' => $componentId]);
    }

    /** Issues a one-use proof for one exact immutable formula version. */
    public function component_formula_restore_step_up_verify()
    {
        $this->require_permission('production.component.formula.index', 'edit');
        if (!$this->require_component_formula_mutation_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $versionId = (int)($payload['formula_version_id'] ?? 0);
        if ($componentId <= 0 || $versionId <= 0) {
            $this->json_error('Component atau versi formula tidak valid.', 422);
            return;
        }
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_FORMULA_RESTORE',
            $versionId,
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

    /** Restores a historical snapshot only after a version-bound one-use proof. */
    public function component_formula_restore()
    {
        $this->require_permission('production.component.formula.index', 'edit');
        if (!$this->require_component_formula_mutation_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $versionId = (int)($payload['formula_version_id'] ?? 0);
        $expectedRevision = (string)($payload[self::COMPONENT_FORMULA_REVISION_FIELD] ?? '');
        if ($componentId <= 0 || $versionId <= 0 || preg_match('/\A[0-9a-f]{64}\z/D', $expectedRevision) !== 1) {
            $this->json_error('Data pemulihan formula tidak valid. Muat ulang halaman sebelum mencoba lagi.', 422);
            return;
        }
        if (!$this->consume_component_formula_restore_step_up($versionId, $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $this->release_session_lock();
        $sourceIp = method_exists($this->input, 'ip_address')
            ? trim((string)$this->input->ip_address())
            : '';
        $result = $this->Production_model->restore_component_formula_version(
            $componentId,
            $versionId,
            $expectedRevision,
            [
                'actor_user_id' => !empty($this->current_user['id']) ? (int)$this->current_user['id'] : null,
                'source_ip' => $sourceIp !== '' ? substr($sourceIp, 0, 45) : null,
            ]
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error(
                (string)($result['message'] ?? 'Pemulihan formula gagal.'),
                (int)($result['status'] ?? 422)
            );
            return;
        }
        $this->json_ok([
            'component_id' => $componentId,
            'restored_version_id' => $versionId,
        ]);
    }

    public function component_formula_delete($id)
    {
        $this->require_permission('production.component.formula.index', 'delete');
        if (!$this->require_component_formula_mutation_csrf()) {
            return;
        }

        // Penghapusan satu baris harus dilakukan dari editor bulk agar
        // perubahan tercatat sebagai snapshot formula versi baru.
        $this->json_error(
            'Endpoint hapus satu baris formula sudah dipensiunkan. Gunakan editor Formula Component terbaru.',
            410
        );
    }

    public function component_cost_variables()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $this->render('production/component_cost_variable_index', [
            'page_title' => 'Pengaturan Variable Cost',
            'rows' => $this->Production_model->variable_cost_default_list(),
        ]);
    }

    public function component_cost_variable_save()
    {
        $this->require_permission('production.component.formula.index', 'edit');
        $payload = $this->request_payload();
        $result = $this->Production_model->save_variable_cost_default($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal simpan variable cost default.'), 422);
            return;
        }
        $this->json_ok();
    }

    // ── Component Daily Recon ─────────────────────────────────

    private const PAGE_COMPONENT_DAILY_RECON = 'production.component.daily.recon.index';
    private const PAGE_COMPONENT_OPNAME_MONTHLY = 'production.component.opname.monthly';

    public function component_daily_recon()
    {
        $pageCode = $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'view')
            ? self::PAGE_COMPONENT_DAILY_RECON
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');

        $opnameDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $locationType = strtoupper(trim((string)$this->input->get('location_type', true)));
        if (!in_array($locationType, ['REGULER', 'EVENT'], true)) {
            $locationType = '';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $type       = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE'], true)) {
            $type = '';
        }
        $q           = trim((string)$this->input->get('q', true));
        $componentId = max(0, (int)$this->input->get('component_id', true));
        $isSuperadmin = !empty($this->current_user['is_superadmin']);
        $canCreate    = $isSuperadmin || $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create');

        $this->render('production/component_daily_recon_index', [
            'page_title'    => 'Daily Recon Stok Component',
            'active_menu'   => 'production.component.daily.recon',
            'opname_date'   => $opnameDate,
            'location_type' => $locationType,
            'division_id'   => $divisionId,
            'type'          => $type,
            'q'             => $q,
            'component_id'  => $componentId,
            'divisions'     => $this->active_divisions(),
            'can_create'    => $canCreate,
            'division_scope_id' => $scopeDivisionId,
            'component_daily_recon_csrf_token' => $this->component_daily_recon_csrf(),
        ]);
    }

    public function component_daily_recon_data()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'view') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'view'
        );

        $opnameDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $targetMonth = date('Y-m-01', strtotime($opnameDate));
        $nextMonth = date('Y-m-01', strtotime($targetMonth . ' +1 month'));

        $locationType = strtoupper(trim((string)$this->input->get('location_type', true)));
        if (!in_array($locationType, ['REGULER', 'EVENT'], true)) {
            $locationType = '';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $type       = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE'], true)) {
            $type = '';
        }
        $q = trim((string)$this->input->get('q', true));
        $componentId = max(0, (int)$this->input->get('component_id', true));

        if (!$this->db->table_exists('inv_component_monthly_stock')) {
            $this->json_ok(['rows' => [], 'meta' => ['total_components' => 0]]);
            return;
        }

        $divNameCol  = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divNameExpr = $divNameCol ? ('d.' . $divNameCol) : 'CAST(s.division_id AS CHAR)';
        $divCodeCol  = $this->db->field_exists('code', 'mst_operational_division') ? 'd.code' : 'NULL';

        $latestSub = "SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
                      FROM inv_component_monthly_stock
                      WHERE month_key = " . $this->db->escape($targetMonth) . "
                      GROUP BY location_type, division_id, component_id, uom_id";

        $where = '';
        if ($locationType !== '') {
            $where .= " AND s.location_type = " . $this->db->escape($locationType);
        }
        if ($divisionId > 0) {
            $where .= " AND s.division_id = " . (int)$divisionId;
        }
        if ($type !== '') {
            $where .= " AND c.component_type = " . $this->db->escape($type);
        }
        if ($q !== '') {
            $qLike  = $this->db->escape('%' . $q . '%');
            $where .= " AND (c.component_name LIKE {$qLike} OR c.component_code LIKE {$qLike})";
        }
        if ($componentId > 0) {
            $where .= ' AND s.component_id = ' . $componentId;
        }
        if ($this->db->field_exists('is_active', 'mst_component')) {
            $where .= " AND COALESCE(c.is_active, 1) = 1";
        }

        $catNameExpr = $this->db->field_exists('name', 'mst_component_category') ? 'cat.name' : 'NULL';

        $sql = "
            SELECT
                s.location_type,
                s.division_id,
                {$divNameExpr}               AS division_name,
                {$divCodeCol}                AS division_code,
                s.component_id,
                c.component_code,
                c.component_name,
                c.component_type,
                s.uom_id,
                COALESCE(u.code, '')         AS uom_code,
                s.closing_qty                AS system_qty,
                s.avg_cost,
                COALESCE({$catNameExpr}, '') AS category_name
            FROM inv_component_monthly_stock s
            INNER JOIN ({$latestSub}) lm
                ON  lm.location_type  = s.location_type
                AND lm.division_id  <=> s.division_id
                AND lm.component_id   = s.component_id
                AND lm.uom_id         = s.uom_id
                AND lm.max_month      = s.month_key
            JOIN  mst_component c ON c.id = s.component_id
            LEFT JOIN mst_operational_division d ON d.id = s.division_id
            LEFT JOIN mst_uom u ON u.id = s.uom_id
            LEFT JOIN mst_component_category cat ON cat.id = c.component_category_id
            WHERE 1=1 {$where}
            ORDER BY {$divNameExpr}, s.location_type, c.component_type, c.component_name, u.code
        ";

        $stockRows = ($r = $this->db->query($sql)) ? $r->result_array() : [];
        $confirmMode = $this->daily_recon_confirm_mode();
        $requiredComponentTokens = $this->daily_recon_required_tokens('pos.daily_recon_required_components');
        $componentLineKeys = [];
        foreach ($stockRows as &$stockRow) {
            $stockRow['recon_line_key'] = $this->component_recon_line_key($stockRow);
            $componentLineKeys[(string)$stockRow['recon_line_key']] = (string)$stockRow['recon_line_key'];
        }
        unset($stockRow);

        $confirmedComponentLines = [];
        if ($this->db->table_exists('inv_daily_recon_checkpoint_line')) {
            $lineQuery = $this->db->select('line_key, checkpoint_stage')
                ->from('inv_daily_recon_checkpoint_line')
                ->where('checkpoint_date', $opnameDate)
                ->where('recon_domain', 'COMPONENT');
            if ($divisionId > 0) {
                $lineQuery->where('division_id', $divisionId);
            }
            foreach ($lineQuery->get()->result_array() as $lineRow) {
                $confirmedComponentLines[(string)$lineRow['line_key'] . '|' . strtoupper((string)$lineRow['checkpoint_stage'])] = true;
            }
        }

        // ── Load lot data for multi-lot expand/collapse ───────────────────────
        // Key: "compId|uomId|divId" — location_type sengaja diabaikan agar
        // match tetap benar meski monthly_stock menyimpan REGULER/EVENT sementara
        // inv_component_lot menyimpan BAR/KITCHEN/BAR_EVENT/KITCHEN_EVENT.
        $lotsByKey = [];
        if ($this->db->table_exists('inv_component_lot') && !empty($stockRows)) {
            $compIds = implode(',', array_unique(array_map('intval', array_column($stockRows, 'component_id'))));
            $lotSql  = "
                SELECT l.id AS lot_id, l.lot_no, l.location_type AS lot_location_type,
                       l.division_id, l.component_id, l.uom_id,
                       l.qty_balance AS system_qty, l.unit_cost,
                       l.receipt_date, l.expiry_date
                FROM inv_component_lot l
                WHERE l.component_id IN ({$compIds})
                  AND l.status = 'OPEN'
                  AND l.receipt_date >= " . $this->db->escape($targetMonth) . "
                  AND l.receipt_date < " . $this->db->escape($nextMonth) . "
                ORDER BY l.receipt_date ASC, l.id ASC
            ";
            $lotResult = $this->db->query($lotSql);
            foreach ($lotResult ? $lotResult->result_array() : [] as $lot) {
                // Key pakai compId|uomId|divId saja, bebas dari ambiguitas location_type
                $lk = (int)$lot['component_id'] . '|' . (int)$lot['uom_id'] . '|' . (int)$lot['division_id'];
                $lotsByKey[$lk][] = $lot;
            }
        }

        // ── Load physical counts for this opname date ─────────────────────────
        $opnameMap    = [];
        $lotOpnameMap = [];  // keyed: parentKey => [lot_id => row]
        $hasLotIdCol  = $this->db->field_exists('lot_id', 'inv_component_stock_opname');

        if ($this->db->table_exists('inv_component_stock_opname')) {
            $selCols = 'location_type, division_id, component_id, uom_id, physical_qty, notes, adjustment_id'
                       . ($hasLotIdCol ? ', lot_id' : '');
            $opnameQ = $this->db->select($selCols)
                ->from('inv_component_stock_opname')
                ->where('opname_date', $opnameDate);
            if ($divisionId > 0) $opnameQ->where('division_id', $divisionId);
            if ($locationType !== '') $opnameQ->where('location_type', $locationType);
            foreach ($opnameQ->get()->result_array() as $oRow) {
                $lotId = $hasLotIdCol ? (int)($oRow['lot_id'] ?? 0) : 0;
                $k = $oRow['location_type'] . '|' . $oRow['division_id'] . '|' . $oRow['component_id'] . '|' . $oRow['uom_id'];
                if ($lotId > 0) {
                    $lotOpnameMap[$k][$lotId] = $oRow;
                } else {
                    $opnameMap[$k] = $oRow;
                }
            }
        }

        // ── Group by division+location_type ───────────────────────────────────
        $groups = [];
        foreach ($stockRows as $r) {
            $divId   = (int)$r['division_id'];
            $locType = (string)$r['location_type'];
            $ikey    = $locType . '|' . $divId . '|' . $r['component_id'] . '|' . $r['uom_id'];
            $opname  = $opnameMap[$ikey] ?? null;
            $sysQty  = (float)$r['system_qty'];
            $physQty = ($opname !== null && $opname['physical_qty'] !== null)
                ? (float)$opname['physical_qty'] : null;
            $selisih = $physQty !== null ? round($physQty - $sysQty, 4) : null;

            // Build lot sub-rows for multi-lot components
            $lotLookupKey = (int)$r['component_id'] . '|' . (int)$r['uom_id'] . '|' . $divId;
            $rawLots      = $lotsByKey[$lotLookupKey] ?? [];
            $lotCount     = count($rawLots);
            $lotSubRows = [];
            if ($lotCount > 1) {
                $lotOpnamesForKey = $lotOpnameMap[$ikey] ?? [];
                foreach ($rawLots as $lot) {
                    $lotId     = (int)$lot['lot_id'];
                    $lotSysQty = (float)$lot['system_qty'];
                    $lotOpname = $lotOpnamesForKey[$lotId] ?? null;
                    $lotPhys   = ($lotOpname && $lotOpname['physical_qty'] !== null) ? (float)$lotOpname['physical_qty'] : null;
                    $lotSel    = $lotPhys !== null ? round($lotPhys - $lotSysQty, 4) : null;
                    $lotSubRows[] = [
                        'lot_id'            => $lotId,
                        'lot_no'            => (string)$lot['lot_no'],
                        'lot_specific_type' => (string)$lot['lot_location_type'],
                        'receipt_date'      => (string)$lot['receipt_date'],
                        'expiry_date'       => (string)($lot['expiry_date'] ?? ''),
                        'unit_cost'         => (float)$lot['unit_cost'],
                        'identity_key'      => $ikey . '|' . $lotId,
                        'system_qty'        => $lotSysQty,
                        'physical_qty'      => $lotPhys,
                        'selisih'           => $lotSel,
                        'adjustment_id'     => ($lotOpname && !empty($lotOpname['adjustment_id'])) ? (int)$lotOpname['adjustment_id'] : null,
                    ];
                    $lotReasons = [];
                    if ($confirmMode === 'ROW_REQUIRED') {
                        $lotReasons[] = 'mode wajib satu per satu';
                    }
                    if ($lotCount > 1) {
                        $lotReasons[] = $lotCount . ' lot aktif';
                    }
                    if ($this->daily_recon_token_matches($requiredComponentTokens, [
                        (string)($r['component_id'] ?? ''),
                        (string)($r['component_code'] ?? ''),
                        (string)($r['component_name'] ?? ''),
                    ])) {
                        $lotReasons[] = 'daftar wajib recon';
                    }
                    $lotLineKey = $this->component_recon_line_key([
                        'division_id' => $divId,
                        'location_type' => $locType,
                        'component_id' => (int)$r['component_id'],
                        'uom_id' => (int)$r['uom_id'],
                        'lot_id' => $lotId,
                    ]);
                    $lastIdx = count($lotSubRows) - 1;
                    $lotSubRows[$lastIdx]['recon_line_key'] = $lotLineKey;
                    $lotSubRows[$lastIdx]['must_row_confirm'] = !empty($lotReasons);
                    $lotSubRows[$lastIdx]['must_row_confirm_reason'] = implode(', ', array_unique($lotReasons));
                    $lotSubRows[$lastIdx]['confirmed_open'] = isset($confirmedComponentLines[$lotLineKey . '|OPEN']);
                    $lotSubRows[$lastIdx]['confirmed_close'] = isset($confirmedComponentLines[$lotLineKey . '|CLOSE']);
                }
            }

            $row = [
                'location_type'  => $locType,
                'division_id'    => $divId,
                'division_name'  => (string)$r['division_name'],
                'division_code'  => strtoupper(trim((string)$r['division_code'])),
                'component_id'   => (int)$r['component_id'],
                'component_code' => $r['component_code'],
                'component_name' => $r['component_name'],
                'component_type' => $r['component_type'],
                'category_name'  => $r['category_name'],
                'uom_id'         => (int)$r['uom_id'],
                'uom_code'       => $r['uom_code'],
                'identity_key'   => $ikey,
                'system_qty'     => $sysQty,
                'avg_cost'       => (float)$r['avg_cost'],
                'physical_qty'   => $physQty,
                'selisih'        => $selisih,
                'opname_notes'   => (string)($opname['notes'] ?? ''),
                'adjustment_id'  => ($opname && !empty($opname['adjustment_id']))
                    ? (int)$opname['adjustment_id'] : null,
                'lot_count'      => $lotCount,
                'lots'           => $lotSubRows,
            ];
            $mustReasons = [];
            if ($confirmMode === 'ROW_REQUIRED') {
                $mustReasons[] = 'mode wajib satu per satu';
            }
            if ($lotCount > 1) {
                $mustReasons[] = $lotCount . ' lot aktif';
            }
            if ($this->daily_recon_token_matches($requiredComponentTokens, [
                (string)($row['component_id'] ?? ''),
                (string)($row['component_code'] ?? ''),
                (string)($row['component_name'] ?? ''),
            ])) {
                $mustReasons[] = 'daftar wajib recon';
            }
            $lineKey = $this->component_recon_line_key($row);
            $row['recon_line_key'] = $lineKey;
            $row['must_row_confirm'] = !empty($mustReasons);
            $row['must_row_confirm_reason'] = implode(', ', array_unique($mustReasons));
            $row['confirmed_open'] = isset($confirmedComponentLines[$lineKey . '|OPEN']);
            $row['confirmed_close'] = isset($confirmedComponentLines[$lineKey . '|CLOSE']);

            $gkey = $divId . '|' . $locType;
            if (!isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'division_id'   => $divId,
                    'division_name' => $row['division_name'],
                    'location_type' => $locType,
                    'rows'          => [],
                ];
            }
            $groups[$gkey]['rows'][] = $row;
        }

        $this->json_ok([
            'rows' => array_values($groups),
            'meta' => [
                'opname_date'      => $opnameDate,
                'total_components' => count($stockRows),
                'total_groups'     => count($groups),
                'confirm_mode'     => $confirmMode,
            ],
        ]);
    }

    /**
     * Issues a short-lived proof for one component identity before Daily Recon
     * can create and post its generated adjustment in the same server action.
     */
    public function component_daily_recon_step_up_verify()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'create'
        );
        $this->require_permission('production.component.adjustment.index', 'create');
        if (!$this->require_component_daily_recon_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        if ($componentId <= 0) {
            $this->json_error('component_id wajib diisi untuk verifikasi recon.', 422);
            return;
        }
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->issue(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_DAILY_RECON_POST',
            $componentId,
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

    public function component_daily_recon_save()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'create'
        );
        if (!$this->require_component_daily_recon_csrf()) {
            return;
        }

        $this->release_session_lock();
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
        $payload      = $this->request_payload();
        $opnameDate   = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $locationType = $this->normalize_location_filter((string)($payload['location_type'] ?? 'REGULER'));
        if ($locationType === '') {
            $locationType = 'REGULER';
        }
        $divisionId  = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $divisionCode = strtoupper(trim((string)($payload['division_code'] ?? '')));
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId       = (int)($payload['uom_id'] ?? 0);
        $lotId       = (int)($payload['lot_id'] ?? 0);
        $physQty     = isset($payload['physical_qty']) && $payload['physical_qty'] !== ''
            ? round((float)$payload['physical_qty'], 4) : null;
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        if ($componentId <= 0 || $uomId <= 0) {
            $this->json_error('component_id dan uom_id wajib diisi.', 422);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $this->json_error('Tanggal recon component tidak valid.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_component_stock_opname')) {
            $this->json_error('Tabel opname belum ada. Jalankan SQL setup terlebih dahulu.', 500);
            return;
        }

        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->load->library('InventoryPeriodGuard');
            $period = $this->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $opnameDate,
                null,
                'Automatic component period from daily recon'
            );
            if (!($period['ok'] ?? false)) {
                $this->json_error((string)($period['message'] ?? 'Periode stok tidak dapat dipakai.'), 409);
                return;
            }
        }

        $specificLocation = $this->resolve_component_recon_location($divisionId, $divisionCode, $locationType);
        if ($specificLocation === '') {
            $this->json_error('Lokasi spesifik component tidak dapat ditentukan (divisi harus BAR, KITCHEN, atau ROASTERY).', 422);
            return;
        }
        $snapshot = $this->current_component_recon_snapshot(
            $opnameDate,
            $specificLocation,
            $divisionId,
            $componentId,
            $uomId,
            $lotId
        );
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Saldo component tidak dapat diverifikasi.'), 409);
            return;
        }

        $hasLotIdCol = $this->db->field_exists('lot_id', 'inv_component_stock_opname');
        $systemQty   = (float)($snapshot['system_qty'] ?? 0);
        $selisih     = $physQty !== null ? round($physQty - $systemQty, 4) : null;

        $q = $this->db
            ->where('opname_date', $opnameDate)
            ->where('location_type', $locationType);
        if ($divisionId !== null) {
            $q->where('division_id', $divisionId);
        } else {
            $q->where('division_id IS NULL', null, false);
        }
        $q->where('component_id', $componentId)->where('uom_id', $uomId);
        if ($hasLotIdCol) {
            $q->where('lot_id', $lotId > 0 ? $lotId : 0);
        }
        $existing = $q->get('inv_component_stock_opname')->row_array();

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('inv_component_stock_opname', [
                'physical_qty' => $physQty,
                'system_qty'   => $systemQty,
                'notes'        => $notes !== '' ? $notes : null,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        } else {
            $insertData = [
                'opname_date'   => $opnameDate,
                'location_type' => $locationType,
                'division_id'   => $divisionId,
                'component_id'  => $componentId,
                'uom_id'        => $uomId,
                'system_qty'    => $systemQty,
                'physical_qty'  => $physQty,
                'notes'         => $notes !== '' ? $notes : null,
                'created_by'    => $userId > 0 ? $userId : null,
            ];
            if ($hasLotIdCol) $insertData['lot_id'] = $lotId > 0 ? $lotId : 0;
            $this->db->insert('inv_component_stock_opname', $insertData);
        }

        $this->json_ok([
            'selisih' => $selisih,
            'physical_qty' => $physQty,
            'system_qty' => $systemQty,
        ]);
        } catch (Throwable $e) {
            $this->json_backend_exception('penyimpanan Daily Recon component', $e);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    public function component_daily_recon_adjust()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'create'
        );
        $this->require_permission('production.component.adjustment.index', 'create');
        if (!$this->require_component_daily_recon_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $componentIdForProof = (int)($payload['component_id'] ?? 0);
        if ($componentIdForProof <= 0) {
            $this->json_error('component_id wajib diisi.', 422);
            return;
        }
        if (!$this->consume_component_daily_recon_step_up($componentIdForProof, $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $this->release_session_lock();

        $requestDbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
        $opnameDate   = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $locationType = $this->normalize_location_filter((string)($payload['location_type'] ?? 'REGULER'));
        if ($locationType === '') {
            $locationType = 'REGULER';
        }
        $divisionId    = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $divCode       = strtoupper(trim((string)($payload['division_code'] ?? '')));
        $componentId   = (int)($payload['component_id'] ?? 0);
        $uomId         = (int)($payload['uom_id'] ?? 0);
        $lotId         = !empty($payload['lot_id']) ? (int)$payload['lot_id'] : 0;

        if ($componentId <= 0 || $uomId <= 0) {
            $this->json_error('component_id dan uom_id wajib diisi.', 422);
            return;
        }

        $specificLocation = $this->resolve_component_recon_location($divisionId, $divCode, $locationType);
        if ($specificLocation === '') {
            $this->json_error('Lokasi spesifik component tidak dapat ditentukan (divisi harus BAR, KITCHEN, atau ROASTERY).', 422);
            return;
        }

        $snapshot = $this->current_component_recon_snapshot(
            $opnameDate,
            $specificLocation,
            $divisionId,
            $componentId,
            $uomId,
            $lotId
        );
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Saldo component tidak dapat diverifikasi.'), 409);
            return;
        }

        // When a later batch already brought the system balance back to the
        // physical count, allow the operator to close the original deficit
        // without manufacturing a zero-quantity adjustment.
        $settlementOnlyRequested = !empty($payload['settle_open_deficit'])
            && strtoupper(trim((string)($payload['input_mode'] ?? ''))) === 'PHYSICAL_COUNT'
            && array_key_exists('physical_qty', $payload);
        if ($settlementOnlyRequested) {
            $physicalQty = round((float)($payload['physical_qty'] ?? 0), 4);
            $systemQty = round((float)($snapshot['system_qty'] ?? 0), 4);
            if ($physicalQty < -0.0001) {
                $this->json_error('Stok fisik tidak boleh bernilai negatif.', 422);
                return;
            }
            if (abs($physicalQty - $systemQty) <= 0.0001) {
                if ($physicalQty <= 0.0001 || !file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
                    $this->json_error('Tidak ada stok fisik yang dapat dipakai untuk menyelesaikan defisit ini.', 422);
                    return;
                }
                $this->load->library('InventoryDeficitService');
                $settlement = $this->inventorydeficitservice->settle([
                    'stock_domain' => 'COMPONENT',
                    'deficit_date' => $opnameDate,
                    'location_scope' => $specificLocation,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'content_uom_id' => $uomId,
                    'qty_available' => $physicalQty,
                    'estimated_unit_cost' => (float)($snapshot['avg_cost'] ?? 0),
                    'source_module' => 'INVENTORY_RECON',
                    'source_table' => 'inventory_deficit_recon',
                    'source_id' => (int)($payload['deficit_id'] ?? 0) ?: null,
                    'notes' => 'Recon fisik mengonfirmasi penyelesaian defisit tanpa perubahan saldo.',
                    'created_by' => (int)($this->current_user['id'] ?? 0) ?: null,
                ]);
                if (!($settlement['ok'] ?? false)) {
                    $this->json_error((string)($settlement['message'] ?? 'Gagal menyelesaikan defisit dari hasil recon.'), 422);
                    return;
                }
                if ((float)($settlement['settled_qty'] ?? 0) <= 0.0001) {
                    $this->json_error('Tidak ditemukan defisit terbuka yang cocok dengan component ini.', 422);
                    return;
                }
                $this->json_ok([
                    'settlement_only' => true,
                    'settled_qty' => (float)($settlement['settled_qty'] ?? 0),
                ], 'Stok sistem sudah sama dengan hitungan fisik. Defisit ditutup sebagai hasil recon tanpa membuat adjustment baru.');
                return;
            }
        }

        $this->load->library('InventoryAdjustmentIntent');
        $intent = $this->inventoryadjustmentintent->resolve(
            $payload,
            'COMPONENT',
            (float)($snapshot['system_qty'] ?? 0)
        );
        if (!($intent['ok'] ?? false)) {
            $this->json_error((string)($intent['message'] ?? 'Parameter adjustment tidak lengkap.'), 409);
            return;
        }

        $physQty       = (float)($intent['physical_qty'] ?? 0);
        $systemQty     = (float)($intent['system_qty'] ?? 0);
        $selisih       = (float)($intent['delta_qty'] ?? 0);
        $adjType       = strtoupper(trim((string)($payload['adjustment_type'] ?? '')));
        $reasonCode    = strtolower(trim((string)($payload['reason_code'] ?? 'other')));
        $notes         = trim((string)($payload['notes'] ?? ''));
        $userId        = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        $absQty = round(abs($selisih), 4);
        $adjNo  = 'CMPREC-' . date('Ymd', strtotime($opnameDate))
                . '-' . strtoupper(substr(md5($componentId . $uomId . $opnameDate . $locationType), 0, 6));

        // Map UI type (WASTE/SPOILAGE/ADJUSTMENT_MINUS/ADJUSTMENT_PLUS) to model fields
        // Daily Recon component follows the same settlement rule as material:
        // a positive physical count closes an exact deficit by default, unless
        // the caller explicitly opts out.
        $isPhysicalCount = strtoupper(trim((string)($intent['input_mode'] ?? ''))) === 'PHYSICAL_COUNT';
        $hasSettlementChoice = array_key_exists('settle_open_deficit', $payload);
        $settleOpenDeficit = $isPhysicalCount
            && ($hasSettlementChoice ? !empty($payload['settle_open_deficit']) : $selisih > 0.0001);

        $line = [
            'component_id'                => $componentId,
            'uom_id'                      => $uomId,
            'selected_lot_id'             => $lotId > 0 ? $lotId : null,
            'input_mode'                  => (string)($intent['input_mode'] ?? 'PHYSICAL_COUNT'),
            'settle_open_deficit'         => $settleOpenDeficit ? 1 : 0,
            'available_qty'               => $systemQty,
            'system_qty_snapshot'         => (float)($intent['system_qty'] ?? $systemQty),
            'physical_qty_snapshot'       => ($intent['input_mode'] ?? '') === 'PHYSICAL_COUNT'
                ? (float)($intent['physical_qty'] ?? $physQty)
                : null,
            'qty_waste'                   => 0,
            'waste_reason_code'           => '',
            'qty_spoil'                   => 0,
            'spoil_reason_code'           => '',
            'qty_adjust_pos'              => 0,
            'adjustment_plus_reason_code' => '',
            'qty_adjust_neg'              => 0,
            'adjustment_minus_reason_code'=> '',
            'unit_cost'                   => max(0, (float)($snapshot['avg_cost'] ?? 0)),
            'note'                        => $notes,
        ];
        if ($selisih < 0) {
            $validNeg = ['WASTE', 'SPOILAGE', 'ADJUSTMENT_MINUS'];
            if (!in_array($adjType, $validNeg, true)) {
                $adjType = 'ADJUSTMENT_MINUS';
            }
            if ($adjType === 'WASTE') {
                $line['qty_waste']         = $absQty;
                $line['waste_reason_code'] = $reasonCode ?: 'other';
            } elseif ($adjType === 'SPOILAGE') {
                $line['qty_spoil']          = $absQty;
                $line['spoil_reason_code']  = $reasonCode ?: 'other';
            } else {
                $line['qty_adjust_neg']                   = $absQty;
                $line['adjustment_minus_reason_code']     = $reasonCode ?: 'other';
            }
        } else {
            $line['qty_adjust_pos']                  = $absQty;
            $line['adjustment_plus_reason_code']     = $reasonCode ?: 'other';
        }

        $header = [
            'id'              => 0,
            'adjustment_no'   => $adjNo,
            'adjustment_date' => $opnameDate,
            'location_type'   => $specificLocation,
            'division_id'     => $divisionId,
            'notes'           => 'Daily recon (' . strtolower((string)$intent['input_mode']) . ')' . ($notes !== '' ? ': ' . $notes : ''),
        ];

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $save = $this->Production_model->save_component_adjustment($header, [$line], $userId);
            if (!($save['ok'] ?? false)) {
                $this->json_error((string)($save['message'] ?? 'Gagal menyimpan adjustment.'), 422);
                return;
            }

            $adjId  = (int)($save['id'] ?? 0);
            $post   = $this->post_component_adjustment_document(
                $adjId,
                $userId,
                (int)($this->current_user['id'] ?? 0)
            );
            if (!($post['ok'] ?? false)) {
                $this->json_error('Tersimpan tapi gagal posting: ' . (string)($post['message'] ?? ''), 422);
                return;
            }
        } catch (Throwable $e) {
            $this->json_backend_exception('posting adjustment component dari Daily Recon', $e);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        // A successful stock post must still return JSON when the optional recon tag fails.
        $reconTagWarning = '';
        if ($this->db->table_exists('inv_component_stock_opname') && $adjId > 0) {
            $tagDbDebugBefore = (bool)$this->db->db_debug;
            $this->db->db_debug = false;
            try {
                $lotIdAdj    = !empty($payload['lot_id']) ? (int)$payload['lot_id'] : 0;
                $hasLotIdAdj = $this->db->field_exists('lot_id', 'inv_component_stock_opname');
                $q = $this->db->where('opname_date', $opnameDate)->where('location_type', $locationType);
                if ($divisionId !== null) {
                    $q->where('division_id', $divisionId);
                } else {
                    $q->where('division_id IS NULL', null, false);
                }
                $q->where('component_id', $componentId)->where('uom_id', $uomId);
                if ($hasLotIdAdj) {
                    $q->where('lot_id', $lotIdAdj > 0 ? $lotIdAdj : 0);
                }
                if (!$q->update('inv_component_stock_opname', ['adjustment_id' => $adjId])) {
                    $dbError = $this->db->error();
                    throw new RuntimeException((string)($dbError['message'] ?? 'Gagal menandai baris Daily Recon.'));
                }
            } catch (Throwable $e) {
                log_message('error', 'component_daily_recon_adjust tag warning: ' . $e->getMessage());
                $reconTagWarning = 'Adjustment sudah diposting, tetapi penanda Daily Recon belum tersimpan. Muat ulang halaman lalu cek adjustment #' . $adjId . '.';
            } finally {
                $this->db->db_debug = $tagDbDebugBefore;
            }
        }

        $scopeLabel = $lotId > 0 ? 'lot component' : 'saldo component';
        $this->json_ok(
            [
                'adjustment_id' => $adjId,
                'warning' => $reconTagWarning,
            ],
            'Adjustment ' . $scopeLabel . ' berhasil diposting. Adj #' . $adjId . ' sudah tercatat.'
        );
        } catch (Throwable $e) {
            $this->json_backend_exception('posting Daily Recon component', $e);
        } finally {
            $this->db->db_debug = $requestDbDebugBefore;
        }
    }

    public function component_daily_recon_confirm()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'create'
        );
        if (!$this->require_component_daily_recon_csrf()) {
            return;
        }

        $payload = $this->request_payload();
        $date = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->json_error('Tanggal recon tidak valid.', 422);
            return;
        }

        $divisionId = (int)($payload['division_id'] ?? 0);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $stage = strtoupper(trim((string)($payload['stage'] ?? '')));
        $scope = strtoupper(trim((string)($payload['scope'] ?? 'ALL')));
        $notes = trim((string)($payload['notes'] ?? ''));
        $userId = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        if ($divisionId <= 0) {
            $this->json_error('Pilih satu divisi terlebih dahulu sebelum konfirmasi recon.', 422);
            return;
        }
        if (!in_array($stage, ['OPEN', 'CLOSE'], true)) {
            $this->json_error('Tahap recon harus OPEN atau CLOSE.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_daily_recon_checkpoint')) {
            $this->json_error('Tabel checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a dulu.', 500);
            return;
        }

        if ($scope === 'ROW') {
            if (!$this->db->table_exists('inv_daily_recon_checkpoint_line')) {
                $this->json_error('Tabel detail checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a dulu.', 500);
                return;
            }
            $lineKey = trim((string)($payload['line_key'] ?? ''));
            if ($lineKey === '') {
                $this->json_error('line_key wajib diisi untuk konfirmasi per baris.', 422);
                return;
            }
            $this->upsert_daily_recon_checkpoint_line($date, 'COMPONENT', $divisionId, $stage, [
                'line_key' => $lineKey,
                'line_label' => trim((string)($payload['line_label'] ?? '')),
                'component_id' => !empty($payload['component_id']) ? (int)$payload['component_id'] : null,
                'uom_id' => !empty($payload['uom_id']) ? (int)$payload['uom_id'] : null,
                'lot_id' => !empty($payload['lot_id']) ? (int)$payload['lot_id'] : null,
                'required_reason' => trim((string)($payload['required_reason'] ?? '')),
                'source_page' => 'production/component-daily-recon',
                'notes' => $notes,
                'confirmed_by' => $userId,
            ]);
            $this->json_ok([
                'opname_date' => $date,
                'division_id' => $divisionId,
                'stage' => $stage,
                'line_key' => $lineKey,
            ], 'Baris component berhasil dikonfirmasi.');
            return;
        }

        $this->upsert_daily_recon_checkpoint($date, 'COMPONENT', $divisionId, $stage, 'production/component-daily-recon', $notes, $userId);
        $this->json_ok([
            'opname_date' => $date,
            'division_id' => $divisionId,
            'stage' => $stage,
        ], 'Konfirmasi daily recon component berhasil disimpan.');
    }

    private function upsert_daily_recon_checkpoint(string $date, string $domain, int $divisionId, string $stage, string $sourcePage, string $notes, int $userId): void
    {
        $existing = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->limit(1)
            ->get()
            ->row_array();

        $row = [
            'checkpoint_date' => $date,
            'recon_domain' => $domain,
            'division_id' => $divisionId,
            'checkpoint_stage' => $stage,
            'source_page' => $sourcePage,
            'notes' => $notes !== '' ? $notes : null,
            'confirmed_by' => $userId > 0 ? $userId : null,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($existing['id'])) {
            $this->db->where('id', (int)$existing['id'])->update('inv_daily_recon_checkpoint', $row);
            return;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('inv_daily_recon_checkpoint', $row);
    }

    private function upsert_daily_recon_checkpoint_line(string $date, string $domain, int $divisionId, string $stage, array $line): void
    {
        $lineKey = trim((string)($line['line_key'] ?? ''));
        if ($lineKey === '') {
            return;
        }

        $existing = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint_line')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->where('line_key', $lineKey)
            ->limit(1)
            ->get()
            ->row_array();

        $row = [
            'checkpoint_date' => $date,
            'recon_domain' => $domain,
            'division_id' => $divisionId,
            'checkpoint_stage' => $stage,
            'line_key' => $lineKey,
            'line_label' => trim((string)($line['line_label'] ?? '')),
            'component_id' => $line['component_id'] ?? null,
            'uom_id' => $line['uom_id'] ?? null,
            'lot_id' => $line['lot_id'] ?? null,
            'required_reason' => trim((string)($line['required_reason'] ?? '')) ?: null,
            'source_page' => trim((string)($line['source_page'] ?? '')),
            'notes' => trim((string)($line['notes'] ?? '')) ?: null,
            'confirmed_by' => !empty($line['confirmed_by']) ? (int)$line['confirmed_by'] : null,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($existing['id'])) {
            $this->db->where('id', (int)$existing['id'])->update('inv_daily_recon_checkpoint_line', $row);
            return;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('inv_daily_recon_checkpoint_line', $row);
    }

    private function component_recon_line_key(array $row): string
    {
        return implode('|', [
            'C',
            (int)($row['division_id'] ?? 0),
            strtoupper((string)($row['location_type'] ?? 'REGULER')),
            (int)($row['component_id'] ?? 0),
            (int)($row['uom_id'] ?? 0),
            (int)($row['lot_id'] ?? 0),
        ]);
    }

    /**
     * Daily-recon UI groups locations as REGULER/EVENT, while stock and lots
     * are stored in their real BAR/KITCHEN/ROASTERY location. Resolve that
     * mapping server-side so the browser never chooses a different bucket.
     */
    private function resolve_component_recon_location(?int $divisionId, string $divisionCode, string $locationGroup): string
    {
        $divisionCode = strtoupper(trim($divisionCode));
        $locationGroup = strtoupper(trim($locationGroup));
        if (!in_array($locationGroup, ['REGULER', 'EVENT'], true)) {
            $locationGroup = 'REGULER';
        }

        if ($divisionCode === '' && !empty($divisionId) && $this->db->table_exists('mst_operational_division')) {
            $division = $this->db
                ->select('code')
                ->from('mst_operational_division')
                ->where('id', (int)$divisionId)
                ->limit(1)
                ->get()
                ->row_array();
            $divisionCode = strtoupper(trim((string)($division['code'] ?? '')));
        }

        $base = [
            'BAR' => 'BAR',
            'KITCHEN' => 'KITCHEN',
            'ROASTERY' => 'ROASTERY',
        ][$divisionCode] ?? '';

        return $base === '' ? '' : ($locationGroup === 'EVENT' ? $base . '_EVENT' : $base);
    }

    /**
     * Resolves the component balance at posting time. Daily Recon may stay open
     * while batch/POS movements happen, therefore browser-provided balances are
     * never used as the accounting source for a physical-count adjustment.
     */
    private function current_component_recon_snapshot(
        string $opnameDate,
        string $specificLocation,
        ?int $divisionId,
        int $componentId,
        int $uomId,
        int $lotId = 0
    ): array {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)
            || $specificLocation === ''
            || $componentId <= 0
            || $uomId <= 0
        ) {
            return ['ok' => false, 'message' => 'Konteks stok component tidak lengkap. Muat ulang Daily Recon lalu coba kembali.'];
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            if ($lotId > 0) {
                if (!$this->db->table_exists('inv_component_lot')) {
                    return ['ok' => false, 'message' => 'Tabel lot component belum tersedia.'];
                }
                $lotQuery = $this->db
                    ->select('id, qty_balance, unit_cost, location_type, division_id, component_id, uom_id')
                    ->from('inv_component_lot')
                    ->where('id', $lotId)
                    ->where('location_type', $specificLocation)
                    ->where('component_id', $componentId)
                    ->where('uom_id', $uomId);
                if ($divisionId !== null && $divisionId > 0) {
                    $lotQuery->where('division_id', $divisionId);
                } else {
                    $lotQuery->where('division_id IS NULL', null, false);
                }
                $lotResult = $lotQuery->limit(1)->get();
                $lot = $lotResult ? $lotResult->row_array() : [];
                if (empty($lot)) {
                    return [
                        'ok' => false,
                        'message' => 'Lot component tidak cocok dengan lokasi, divisi, component, atau UOM yang sedang direcon. Muat ulang halaman lalu pilih lot yang benar.',
                    ];
                }
                return [
                    'ok' => true,
                    'source' => 'LOT',
                    'lot_id' => (int)$lot['id'],
                    'system_qty' => round((float)($lot['qty_balance'] ?? 0), 4),
                    'avg_cost' => round((float)($lot['unit_cost'] ?? 0), 6),
                ];
            }

            if (!$this->db->table_exists('inv_component_monthly_stock')) {
                return ['ok' => false, 'message' => 'Tabel saldo bulanan component belum tersedia.'];
            }
            $monthKey = date('Y-m-01', strtotime($opnameDate));
            $stockQuery = $this->db
                ->select('id, closing_qty, avg_cost')
                ->from('inv_component_monthly_stock')
                ->where('month_key', $monthKey)
                ->where('location_type', $specificLocation)
                ->where('component_id', $componentId)
                ->where('uom_id', $uomId);
            if ($divisionId !== null && $divisionId > 0) {
                $stockQuery->where('division_id', $divisionId);
            } else {
                $stockQuery->where('division_id IS NULL', null, false);
            }
            $stockResult = $stockQuery->order_by('id', 'DESC')->limit(1)->get();
            $stock = $stockResult ? $stockResult->row_array() : [];
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (empty($stock)) {
            // A POS shortage can be recorded before the component has its
            // first monthly row. Let Daily Recon count that exact component as
            // zero-system stock instead of forcing the operator to leave this
            // workflow and create a separate adjustment document.
            if ($this->db->table_exists('inv_stock_deficit')) {
                $deficitQuery = $this->db
                    ->select('COALESCE(SUM(qty_remaining), 0) AS qty_remaining', false)
                    ->select("CASE WHEN COALESCE(SUM(qty_remaining), 0) > 0.0001
                        THEN COALESCE(SUM(qty_remaining * estimated_unit_cost), 0) / SUM(qty_remaining)
                        ELSE 0 END AS avg_cost", false)
                    ->from('inv_stock_deficit')
                    ->where('stock_domain', 'COMPONENT')
                    ->where('status', 'OPEN')
                    ->where('location_scope', $specificLocation)
                    ->where('component_id', $componentId)
                    ->where('content_uom_id', $uomId)
                    ->where('qty_remaining >', 0.0001);
                if ($divisionId !== null && $divisionId > 0) {
                    $deficitQuery->where('division_id', $divisionId);
                } else {
                    $deficitQuery->where('division_id IS NULL', null, false);
                }
                $deficit = $deficitQuery->get()->row_array() ?: [];
                if ((float)($deficit['qty_remaining'] ?? 0) > 0.0001) {
                    return [
                        'ok' => true,
                        'source' => 'OPEN_DEFICIT',
                        'monthly_stock_id' => 0,
                        'system_qty' => 0.0,
                        'avg_cost' => round((float)($deficit['avg_cost'] ?? 0), 6),
                        'is_deficit_virtual' => true,
                    ];
                }
            }
            return [
                'ok' => false,
                'message' => 'Saldo component tidak ditemukan pada bulan aktif. Muat ulang Daily Recon; untuk component baru gunakan Adjustment Component.',
            ];
        }

        return [
            'ok' => true,
            'source' => 'MONTHLY_STOCK',
            'monthly_stock_id' => (int)$stock['id'],
            'system_qty' => round((float)($stock['closing_qty'] ?? 0), 4),
            'avg_cost' => round((float)($stock['avg_cost'] ?? 0), 6),
        ];
    }

    private function daily_recon_confirm_mode(): string
    {
        $mode = strtoupper(trim($this->daily_recon_config_value('pos.daily_recon_confirm_mode', 'BULK_ALLOWED')));
        return in_array($mode, ['BULK_ALLOWED', 'ROW_REQUIRED'], true) ? $mode : 'BULK_ALLOWED';
    }

    private function daily_recon_config_value(string $key, string $default = ''): string
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

    private function daily_recon_required_tokens(string $configKey): array
    {
        $raw = $this->daily_recon_config_value($configKey, '');
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = strtoupper(trim((string)$part));
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
        return $tokens;
    }

    private function daily_recon_token_matches(array $tokens, array $candidates): bool
    {
        if (empty($tokens)) {
            return false;
        }
        foreach ($candidates as $candidate) {
            $value = strtoupper(trim((string)$candidate));
            if ($value !== '' && isset($tokens[$value])) {
                return true;
            }
        }
        return false;
    }

    private function stock_filters()
    {
        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [25, 50, 100, 200, 0], true)) {
            $perPage = 25;
        }
        $page = max(1, (int)$this->input->get('page', true));
        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'per_page'      => $perPage,
            'page'          => $page,
        ];
    }

    private function movement_filters()
    {
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'movement_type' => strtoupper(trim((string)$this->input->get('movement_type', true))),
            'division_id'   => (int)$this->input->get('division_id', true),
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'per_page'      => $perPage,
        ];
    }

    private function daily_filters()
    {
        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [25, 50, 100, 0], true)) {
            $perPage = 25;
        }

        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'component_id'  => (int)$this->input->get('component_id', true),
            'per_page'      => $perPage,
        ];
    }

    private function component_reconcile_filters(): array
    {
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        $asOfDate = trim((string)$this->input->get('as_of_date', true));

        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo)) {
            $asOfDate = $dateTo;
        } elseif (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $dateTo = $asOfDate;

        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'as_of_date'    => $asOfDate,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'component_id'  => (int)$this->input->get('component_id', true),
            'uom_id'        => (int)$this->input->get('uom_id', true),
            'per_page'      => $perPage,
            'limit'         => 500,
        ];
    }

    private function lot_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['OPEN', 'CLOSED', 'VOID', 'ALL'], true)) {
            $status = 'OPEN';
        }
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'status'        => $status,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'per_page'      => $perPage,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
        ];
    }

    private function normalize_component_type_filter($value): string
    {
        $value = strtoupper(trim((string)$value));
        return in_array($value, ['BASE', 'PREPARE'], true) ? $value : '';
    }
    private function normalize_location_filter($value)
    {
        $value = strtoupper(trim((string)$value));
        if (in_array($value, ['REGULER', 'EVENT'], true)) {
            return $value;
        }
        if (in_array($value, ['BAR', 'KITCHEN', 'ROASTERY'], true)) {
            return 'REGULER';
        }
        if (in_array($value, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true)) {
            return 'EVENT';
        }
        return '';
    }
    private function location_options()
    {
        return ['' => 'Semua Tujuan', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
    }

    private function component_opening_redirect_url(array $state = []): string
    {
        $query = [
            'month' => $this->component_import_month((string)($state['month'] ?? date('Y-m'))),
            'location_type' => $this->component_import_location_group((string)($state['location_type'] ?? 'REGULER')) ?: 'REGULER',
        ];

        return site_url('production/component-openings') . '?' . http_build_query($query);
    }

    private function component_import_row_value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $default;
    }

    private function component_import_decimal($value): float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace(' ', '', $raw);
        if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (strpos($raw, ',') !== false) {
            $raw = str_replace(',', '.', $raw);
        }
        return (float)$raw;
    }

    private function component_import_month(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m');
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 0, 7);
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            $date = $this->component_excel_serial_to_date((float)$value);
            if ($date !== null) {
                return substr($date, 0, 7);
            }
        }
        $time = strtotime($value);
        return $time ? date('Y-m', $time) : date('Y-m');
    }

    private function component_import_location_group(string $value): string
    {
        $normalized = $this->normalize_location_filter($value);
        return in_array($normalized, ['REGULER', 'EVENT'], true) ? $normalized : '';
    }

    private function component_excel_serial_to_date(float $serial): ?string
    {
        if ($serial <= 0) {
            return null;
        }
        $days = (int)floor($serial) - 25569;
        if ($days <= 0) {
            return null;
        }
        return gmdate('Y-m-d', $days * 86400);
    }

    private function component_opening_component_maps(): array
    {
        $rows = $this->active_components();
        $maps = ['id' => [], 'code' => []];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $maps['id'][$id] = $row;
            }
            $code = strtoupper(trim((string)($row['component_code'] ?? '')));
            if ($code !== '') {
                $maps['code'][$code] = $row;
            }
        }
        return $maps;
    }

    private function component_opening_uom_map(): array
    {
        $rows = $this->active_uoms();
        $map = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                if ($code !== '') {
                    $map['CODE:' . $code] = $row;
                }
            }
        }
        return $map;
    }

    private function post_component_opening_document(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Opening tidak valid.', 'status_code' => 404];
        }

        $header = $this->Production_model->get_component_opening($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Opening tidak ditemukan.', 'status_code' => 404];
        }
        if (strtoupper((string)($header['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya opening DRAFT yang bisa diposting.', 'status_code' => 422];
        }

        $conflict = $this->Production_model->find_component_opening_month_conflict(
            $id,
            (string)($header['opening_date'] ?? date('Y-m-d')),
            strtoupper(trim((string)($header['location_type'] ?? ''))),
            !empty($header['division_id']) ? (int)$header['division_id'] : null
        );
        if ($conflict) {
            return [
                'ok' => false,
                'message' => 'Opening bulan yang sama sudah ada di dokumen ' . (string)($conflict['opening_no'] ?? ('#' . (int)($conflict['id'] ?? 0))) . '.',
                'status_code' => 422,
            ];
        }

        $linesRaw = $this->Production_model->get_component_opening_lines($id);
        $lines = [];
        foreach ($linesRaw as $line) {
            $lines[] = [
                'id' => (int)($line['id'] ?? 0),
                'component_id' => (int)($line['component_id'] ?? 0),
                'uom_id' => (int)($line['uom_id'] ?? 0),
                'opening_qty' => (float)($line['opening_qty'] ?? 0),
                'qty' => (float)($line['opening_qty'] ?? 0),
                'movement_type' => 'OPENING',
                'unit_cost' => (float)($line['unit_cost'] ?? 0),
                'note' => (string)($line['note'] ?? ''),
            ];
        }

        $header['opening_date'] = substr((string)($header['opening_date'] ?? date('Y-m-d')), 0, 7) . '-01';
        $post = $this->componentstockwriter->post_opening($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($post['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($post['message'] ?? 'Posting opening gagal.'), 'status_code' => 422];
        }

        $this->db->where('id', $id)->update('inv_component_opening', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => !empty($this->current_user['employee_id']) ? (int)$this->current_user['employee_id'] : null,
        ]);

        return ['ok' => true, 'id' => $id];
    }

    private function component_import_uom_id($rawValue, array $uomMap, int $fallbackId): int
    {
        $value = trim((string)$rawValue);
        if ($value === '') {
            return $fallbackId;
        }

        $numeric = (int)$value;
        if ($numeric > 0 && !empty($uomMap[$numeric])) {
            return $numeric;
        }

        $code = strtoupper($value);
        if (!empty($uomMap['CODE:' . $code])) {
            return (int)($uomMap['CODE:' . $code]['id'] ?? 0);
        }

        return $fallbackId;
    }

    private function normalize_location_type($value)
    {
        $value = strtoupper(trim((string)$value));
        return in_array($value, ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true) ? $value : '';
    }

    private function component_master_filters(): array
    {
        $q = trim((string)$this->input->get('q', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $type = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE', 'ALL'], true)) {
            $type = 'ALL';
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $categoryId = (int)$this->input->get('category_id', true);

        $page = (int)$this->input->get('page', true);
        if ($page <= 0) {
            $page = 1;
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit < 0 || $limit > 300) {
            $limit = 50;
        }

        return [
            'q' => $q,
            'status' => $status,
            'type' => $type,
            'division_id' => $divisionId > 0 ? $divisionId : 0,
            'category_id' => $categoryId > 0 ? $categoryId : 0,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private function component_formula_filters(): array
    {
        $q = trim((string)$this->input->get('q', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $type = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE', 'ALL'], true)) {
            $type = 'ALL';
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $categoryId = (int)$this->input->get('category_id', true);
        $page = (int)$this->input->get('page', true);
        if ($page <= 0) {
            $page = 1;
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 300) {
            $limit = 50;
        }

        return [
            'q' => $q,
            'status' => $status,
            'type' => $type,
            'division_id' => $divisionId > 0 ? $divisionId : 0,
            'category_id' => $categoryId > 0 ? $categoryId : 0,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private function active_components(): array
    {
        return $this->db->select('c.id, c.component_code, c.component_name, c.uom_id, c.component_type, c.operational_division_id, u.code AS uom_code, d.code AS division_code, d.name AS division_name')
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->where('c.is_active', 1)
            ->order_by('c.component_type', 'ASC')
            ->order_by('d.name', 'ASC')
            ->order_by('cat.name', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->get()->result_array();
    }

    private function active_uoms(): array
    {
        return $this->db->select('id, code, name')->from('mst_uom')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
    }

    private function active_divisions(): array
    {
        $scopeDivisionId = $this->active_division_id();
        $query = $this->db->select('id, code, name')
            ->from('mst_operational_division')
            ->where('is_active', 1);
        if ($scopeDivisionId !== null) {
            $query->where('id', $scopeDivisionId);
        }
        return $query->order_by('name', 'ASC')->get()->result_array();
    }

    private function active_product_divisions(): array
    {
        return $this->db->select('id, code, name')->from('mst_product_division')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
    }

    private function active_materials(): array
    {
        return $this->db->select('m.id, m.material_code, m.material_name, m.content_uom_id, u.code AS content_uom_code')
            ->from('mst_material m')
            ->join('mst_uom u', 'u.id = m.content_uom_id', 'left')
            ->where('m.is_active', 1)
            ->order_by('m.material_name', 'ASC')
            ->get()->result_array();
    }

    private function request_payload(): array
    {
        $raw = (string)$this->input->raw_input_stream;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $post = $this->input->post(null, true);
        return is_array($post) ? $post : [];
    }

    private function require_component_formula_save_access(): void
    {
        $pageCode = 'production.component.formula.index';
        if ($this->can($pageCode, 'create') || $this->can($pageCode, 'edit')) {
            return;
        }

        // Do not inspect the request payload until an applicable writer permission
        // and the endpoint-specific POST/CSRF boundary have both been established.
        $this->require_permission($pageCode, 'create');
    }

    private function component_formula_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::COMPONENT_FORMULA_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::COMPONENT_FORMULA_MUTATION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function component_adjustment_csrf(): string
    {
        $token = (string)$this->session->userdata(self::COMPONENT_ADJUSTMENT_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::COMPONENT_ADJUSTMENT_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_component_adjustment_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_component_adjustment_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->get_request_header(
            self::COMPONENT_ADJUSTMENT_CSRF_CI_HEADER,
            true
        );
        $sessionToken = (string)$this->session->userdata(self::COMPONENT_ADJUSTMENT_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_component_adjustment_csrf(403, 'Permintaan posting adjustment component tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_component_adjustment_csrf(int $statusCode, string $message): void
    {
        $this->clear_output_buffers();
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function component_batch_csrf(): string
    {
        $token = (string)$this->session->userdata(self::COMPONENT_BATCH_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::COMPONENT_BATCH_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_component_batch_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_component_batch_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->get_request_header(
            self::COMPONENT_BATCH_CSRF_CI_HEADER,
            true
        );
        $sessionToken = (string)$this->session->userdata(self::COMPONENT_BATCH_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_component_batch_csrf(403, 'Permintaan perubahan batch component tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_component_batch_csrf(int $statusCode, string $message): void
    {
        $this->clear_output_buffers();
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function component_daily_recon_csrf(): string
    {
        $token = (string)$this->session->userdata(self::COMPONENT_DAILY_RECON_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::COMPONENT_DAILY_RECON_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_component_daily_recon_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_component_daily_recon_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->get_request_header(
            self::COMPONENT_DAILY_RECON_CSRF_CI_HEADER,
            true
        );
        $sessionToken = (string)$this->session->userdata(self::COMPONENT_DAILY_RECON_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_component_daily_recon_csrf(403, 'Permintaan Daily Recon component tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_component_daily_recon_csrf(int $statusCode, string $message): void
    {
        $this->clear_output_buffers();
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function consume_component_daily_recon_step_up(int $componentId, array $payload): bool
    {
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_DAILY_RECON_POST',
            $componentId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_component_batch_step_up(int $batchId, array $payload, string $action): bool
    {
        if (!in_array($action, ['COMPONENT_BATCH_POST', 'COMPONENT_BATCH_VOID'], true)) {
            $this->json_error('Aksi verifikasi batch tidak valid.', 422);
            return false;
        }
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            max(0, (int)($this->current_user['id'] ?? 0)),
            $action,
            $batchId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_component_adjustment_step_up(int $adjustmentId, array $payload): bool
    {
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_ADJUSTMENT_POST',
            $adjustmentId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_component_adjustment_void_step_up(int $adjustmentId, array $payload): bool
    {
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_ADJUSTMENT_VOID',
            $adjustmentId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_component_formula_restore_step_up(int $versionId, array $payload): bool
    {
        $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
        $result = $this->sensitiveactionstepup->consume(
            max(0, (int)($this->current_user['id'] ?? 0)),
            'COMPONENT_FORMULA_RESTORE',
            $versionId,
            $payload['step_up_proof'] ?? null
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function require_component_formula_mutation_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_component_formula_mutation_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI normalizes CGI/FastCGI header names to title case (...-Csrf).
        // This scoped token is header-only: query, form, JSON, and raw-body
        // token fields are deliberately not accepted as fallbacks.
        $providedToken = (string)$this->input->get_request_header(
            self::COMPONENT_FORMULA_MUTATION_CSRF_CI_HEADER,
            true
        );
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_component_formula_mutation_csrf(403, 'Permintaan perubahan formula component tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::COMPONENT_FORMULA_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_component_formula_mutation_csrf(403, 'Permintaan perubahan formula component tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_component_formula_mutation_csrf(int $statusCode, string $message): void
    {
        $this->clear_output_buffers();
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function normalize_lines(array $lines, string $mode): array
    {
        $result = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ($mode === 'opening') {
                $result[] = [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['uom_id'] ?? 0),
                    'opening_qty' => round((float)($line['opening_qty'] ?? 0), 4),
                    'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                    'note' => (string)($line['note'] ?? ''),
                ];
                continue;
            }
            if ($mode === 'adjustment') {
                $result[] = [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['uom_id'] ?? 0),
                    'selected_lot_id' => !empty($line['selected_lot_id']) ? (int)$line['selected_lot_id'] : null,
                    'available_qty' => round((float)($line['available_qty'] ?? 0), 4),
                    'qty_spoil' => round((float)($line['qty_spoil'] ?? 0), 4),
                    'spoil_reason_code' => (string)($line['spoil_reason_code'] ?? ''),
                    'qty_waste' => round((float)($line['qty_waste'] ?? 0), 4),
                    'waste_reason_code' => (string)($line['waste_reason_code'] ?? ''),
                    'qty_adjust_pos' => round((float)($line['qty_adjust_pos'] ?? 0), 4),
                    'adjustment_plus_reason_code' => (string)($line['adjustment_plus_reason_code'] ?? ''),
                    'qty_adjust_neg' => round((float)($line['qty_adjust_neg'] ?? 0), 4),
                    'adjustment_minus_reason_code' => (string)($line['adjustment_minus_reason_code'] ?? ''),
                    'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                    'note' => (string)($line['note'] ?? ''),
                ];
                continue;
            }
            if ($mode === 'batch') {
                $result[] = [
                    'source_kind' => strtoupper(trim((string)($line['source_kind'] ?? ''))),
                    'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
                    'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                    'component_id' => !empty($line['component_id']) ? (int)$line['component_id'] : null,
                    'uom_id' => (int)($line['uom_id'] ?? 0),
                    'qty' => round((float)($line['qty'] ?? 0), 4),
                    'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                    'notes' => (string)($line['notes'] ?? ''),
                ];
            }
        }
        return $result;
    }

    private function json_ok(array $data = [], string $message = ''): void
    {
        $this->clear_output_buffers();
        $payload = ['ok' => true] + ($message !== '' ? ['message' => $message] : []) + $data;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $statusCode = 400, array $data = []): void
    {
        $this->clear_output_buffers();
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /** Keep AJAX callers on the JSON contract even when a dependency fails. */
    private function json_backend_exception(string $operation, Throwable $e): void
    {
        log_message('error', $operation . ' fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $this->json_error(
            'Server belum dapat memproses ' . $operation . '. Muat ulang halaman lalu coba kembali. Jika masih berulang, hubungi administrator.',
            500
        );
    }

    public function component_opname()
    {
        $pageCode = $this->can(self::PAGE_COMPONENT_OPNAME_MONTHLY, 'view')
            ? self::PAGE_COMPONENT_OPNAME_MONTHLY
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'q'             => trim((string)$this->input->get('q', true)),
        ];
        $rows = $this->Production_model->list_component_monthly_opname($filters, 500);

        $this->render('production/component_opname_monthly_index', [
            'page_title'  => 'Stok Opname Bulanan Component',
            'active_menu' => 'production.component.opname.monthly',
            'rows'        => $rows,
            'filters'     => $filters,
            'divisions'   => $this->active_divisions(),
            'generate_url' => site_url('production/component-openings/generate-monthly'),
        ]);
    }

    public function component_opening_monthly()
    {
        $pageCode = $this->can(self::PAGE_COMPONENT_OPNAME_MONTHLY, 'view')
            ? self::PAGE_COMPONENT_OPNAME_MONTHLY
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'q'             => trim((string)$this->input->get('q', true)),
        ];
        $rows = $this->Production_model->list_component_monthly_openings($filters, 500);

        $this->render('production/component_opening_monthly_index', [
            'page_title'  => 'Opening Stok Bulanan Component',
            'active_menu' => 'production.component.opening.monthly',
            'rows'        => $rows,
            'filters'     => $filters,
            'divisions'   => $this->active_divisions(),
        ]);
    }

    private function clear_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}
