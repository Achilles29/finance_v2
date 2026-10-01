<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement extends MY_Controller
{
    private const PROCUREMENT_MUTATION_CSRF_SESSION_KEY = 'procurement_mutation_csrf';
    private const PROCUREMENT_MUTATION_CSRF_CI_HEADER = 'X-Procurement-Mutation-Csrf';
    private const PAGE_SR       = 'procurement.store_request.index';
    private const PAGE_DIVISION = 'procurement.division.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Procurement_model');
        $this->load->model('Purchase_model');
    }

    public function workbench()
    {
        redirect('store-requests');
    }

    public function division_requests()
    {
        $this->division_po_sr();
    }

    public function purchasing_desk()
    {
        redirect('store-requests');
    }

    public function store_requests()
    {
        $this->require_permission(self::PAGE_SR, 'view');
        $today = date('Y-m-d');
        $activeTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($activeTab, ['nota', 'rincian'], true)) {
            $activeTab = 'nota';
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'destination_type' => strtoupper(trim((string)$this->input->get('destination_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = $today;
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = $today;
        }
        $limit = (int)$this->input->get('limit', true);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $rows = $this->Procurement_model->list_store_requests($filters, $limit, 0);
        $lineRows = $activeTab === 'rincian'
            ? $this->Procurement_model->list_store_request_lines($filters, $limit, 0)
            : [];
        $rangeFilters = $filters;
        $rangeFilters['status'] = '';
        $purposeAttentionFilters = $rangeFilters;
        $purposeAttentionFilters['q'] = '';
        $ids = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $rows), static function ($id) {
            return $id > 0;
        }));

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();

        $data = [
            'title' => 'Store Request',
            'active_menu' => 'procurement.store-request',
            'has_schema' => $this->Procurement_model->has_store_request_schema(),
            'filters' => $filters,
            'active_tab' => $activeTab,
            'limit' => $limit,
            'rows' => $rows,
            'line_rows' => $lineRows,
            'summary' => $this->Procurement_model->get_store_request_summary($rangeFilters),
            'filtered_summary' => $this->Procurement_model->get_store_request_summary($filters),
            'line_summary' => $this->Procurement_model->get_store_request_line_summary($filters),
            'usage_purpose_attention' => $this->Procurement_model->get_store_request_usage_purpose_attention($purposeAttentionFilters),
            'month_attention_summary' => $this->Procurement_model->get_store_request_summary([
                'q' => '',
                'status' => '',
                'division_id' => 0,
                'destination_type' => '',
                'date_start' => date('Y-m-01'),
                'date_end' => date('Y-m-t'),
            ]),
            'timeline_map' => $this->Procurement_model->list_store_request_timeline_map($ids),
            'division_options' => $divisionOptions,
            'status_options' => $this->Procurement_model->list_store_request_status_options(),
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $this->Procurement_model->build_destination_guard_map($divisionOptions),
            'can_create' => (
                $this->can(self::PAGE_SR, 'create')
                || $this->can(self::PAGE_SR, 'edit')
                || in_array(strtoupper((string)($this->current_user['role_code'] ?? '')), ['SUPERADMIN', 'CEO', 'ADMIN'], true)
            ),
            'can_edit' => (
                $this->can(self::PAGE_SR, 'edit')
                || in_array(strtoupper((string)($this->current_user['role_code'] ?? '')), ['SUPERADMIN', 'CEO', 'ADMIN'], true)
            ),
            'can_repair_history' => $this->canRepairStoreRequestHistory(),
            'procurement_mutation_csrf_token' => $this->procurement_mutation_csrf(),
        ];
        $this->render('procurement/store_requests', $data);
    }

    public function store_request_create()
    {
        $this->require_permission(self::PAGE_SR, 'create');

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();
        $data = [
            'title' => 'Buat Store Request',
            'active_menu' => 'procurement.store-request',
            'mode' => 'create',
            'request_id' => 0,
            'header' => [
                'request_date' => date('Y-m-d'),
                'needed_date' => date('Y-m-d'),
                'request_division_id' => (int)($divisionOptions[0]['id'] ?? 0),
                'destination_type' => '',
                'notes' => '',
                'sr_no' => '',
                'status' => 'DRAFT',
            ],
            'lines' => [],
            'division_options' => $divisionOptions,
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $this->Procurement_model->build_destination_guard_map($divisionOptions),
            'can_create' => true,
            'can_edit' => true,
            'procurement_mutation_csrf_token' => $this->procurement_mutation_csrf(),
        ];

        $this->render('procurement/store_request_form', $data);
    }

    public function store_request_edit(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'edit');
        if ($id <= 0) {
            show_404();
            return;
        }

        $detail = $this->Procurement_model->get_store_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $status = strtoupper((string)($header['status'] ?? 'DRAFT'));
        if ($status !== 'DRAFT') {
            $this->session->set_flashdata('error', 'Hanya Store Request status DRAFT yang dapat diedit.');
            redirect('store-requests');
            return;
        }

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();
        $data = [
            'title' => 'Edit Store Request',
            'active_menu' => 'procurement.store-request',
            'mode' => 'edit',
            'request_id' => $id,
            'header' => [
                'request_date' => (string)($header['request_date'] ?? date('Y-m-d')),
                'needed_date' => (string)($header['needed_date'] ?? date('Y-m-d')),
                'request_division_id' => (int)($header['request_division_id'] ?? 0),
                'destination_type' => (string)($header['destination_type'] ?? ''),
                'notes' => (string)($header['notes'] ?? ''),
                'sr_no' => (string)($header['sr_no'] ?? ''),
                'status' => (string)($header['status'] ?? 'DRAFT'),
            ],
            'lines' => (array)($detail['lines'] ?? []),
            'division_options' => $divisionOptions,
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $this->Procurement_model->build_destination_guard_map($divisionOptions),
            'can_create' => true,
            'can_edit' => true,
            'procurement_mutation_csrf_token' => $this->procurement_mutation_csrf(),
        ];

        $this->render('procurement/store_request_form', $data);
    }

    public function store_request_detail(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'view');
        if ($id <= 0) {
            show_404();
            return;
        }

        $detail = $this->Procurement_model->get_store_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }

        $data = [
            'title' => 'Store Request / Detail',
            'active_menu' => 'procurement.store-request',
            'detail' => $detail,
            'stock_review_history' => $this->Procurement_model->stock_review_history('SR',$id),
        ];
        $data['detail']['lines'] = $this->Procurement_model->current_stock_rows((array)$detail['lines'], (array)$detail['header']);

        $this->render('procurement/store_request_detail', $data);
    }

    public function division_po_sr()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) {
            show_error('Anda tidak memiliki akses ke pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $filters = $this->readDivisionPoSrFilters($scope);
        $limit = (int)$this->input->get('limit', true);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $reportPayload = $this->buildDivisionPoSrReportPayload($filters, $limit, $scope);
        $printPicker = $this->buildDivisionPoSrPrintPicker($filters);

        $data = [
            'title' => 'PO/SR Divisi',
            'active_menu' => 'procurement.division',
            'has_schema' => $this->Procurement_model->has_division_request_schema(),
            'filters' => $filters,
            'active_tab' => $filters['tab'],
            'limit' => $limit,
            'rows' => $reportPayload['rows'],
            'line_rows' => $reportPayload['line_rows'],
            'links_map' => $reportPayload['links_map'],
            'division_options' => $scope['division_options'],
            'can_create' => $scope['can_create'],
            'can_verify' => $scope['can_verify'],
            'can_manage_own' => $scope['can_edit_own'],
            'is_purchase_scope' => $scope['is_purchase'],
            'print_picker_rows' => $printPicker['rows'],
            'print_picker_links_map' => $printPicker['links_map'],
            'print_picker_week_start' => $printPicker['week_start'],
            'print_picker_week_end' => $printPicker['week_end'],
        ];

        $data += $this->division_notification_view($scope);
        $this->render('procurement/division_po_sr', $data);
    }

    public function division_po_sr_print()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) {
            show_error('Anda tidak memiliki akses ke pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $filters = $this->readDivisionPoSrFilters($scope);
        $selectedDate = (string)($filters['date_start'] ?: $filters['date_end'] ?: date('Y-m-d'));
        $filters['date_field'] = 'NEEDED_DATE';
        $filters['date_start'] = $selectedDate;
        $filters['date_end'] = $selectedDate;
        $filters['tab'] = 'lines';
        $reportPayload = $this->buildDivisionPoSrReportPayload($filters, 2000, $scope);

        $this->load->view('procurement/division_po_sr_print', [
            'title' => 'Cetak Pengajuan Divisi',
            'rows' => $reportPayload['rows'],
            'line_rows' => $reportPayload['line_rows'],
            'links_map' => $reportPayload['links_map'],
            'filters' => $filters,
            'summary' => $reportPayload['summary'],
            'is_purchase_scope' => $scope['is_purchase'],
            'back_url' => site_url('procurement/division-po-sr') . '?' . http_build_query([
                'tab' => 'notes',
                'q' => (string)($filters['q'] ?? ''),
                'status' => (string)($filters['status'] ?? ''),
                'date_field' => 'NEEDED_DATE',
                'division_id' => (int)($filters['division_id'] ?? 0),
                'date_start' => $selectedDate,
                'date_end' => $selectedDate,
                'limit' => 50,
            ]),
            'printed_at' => date('Y-m-d H:i:s'),
            'show_print_controls' => true,
            'pdf_mode' => false,
        ]);
    }

    public function division_po_sr_pdf()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) {
            show_error('Anda tidak memiliki akses ke pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $filters = $this->readDivisionPoSrFilters($scope);
        $selectedDate = (string)($filters['date_start'] ?: $filters['date_end'] ?: date('Y-m-d'));
        $filters['date_field'] = 'NEEDED_DATE';
        $filters['date_start'] = $selectedDate;
        $filters['date_end'] = $selectedDate;
        $filters['tab'] = 'lines';
        $reportPayload = $this->buildDivisionPoSrReportPayload($filters, 2000, $scope);

        $html = $this->load->view('procurement/division_po_sr_print', [
            'title' => 'Cetak Pengajuan Divisi',
            'rows' => $reportPayload['rows'],
            'line_rows' => $reportPayload['line_rows'],
            'links_map' => $reportPayload['links_map'],
            'filters' => $filters,
            'summary' => $reportPayload['summary'],
            'is_purchase_scope' => $scope['is_purchase'],
            'back_url' => site_url('procurement/division-po-sr'),
            'printed_at' => date('Y-m-d H:i:s'),
            'show_print_controls' => false,
            'pdf_mode' => true,
        ], true);

        $pdfBinary = $this->renderDivisionPoSrPdfBinary($html);
        if ($pdfBinary === null) {
            show_error('Gagal membuat file PDF otomatis. Pastikan Microsoft Edge atau Google Chrome tersedia di server.', 500, 'PDF Gagal Dibuat');
            return;
        }

        $dateLabel = $selectedDate;
        $fileName = 'division-po-sr-' . preg_replace('/[^0-9\-]+/', '-', $dateLabel) . '.pdf';
        $this->output
            ->set_content_type('application/pdf')
            ->set_header('Content-Disposition: attachment; filename="' . $fileName . '"')
            ->set_output($pdfBinary);
        return;
    }

    public function division_po_sr_create()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_create']) {
            show_error('Anda tidak memiliki akses membuat pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $destinationGuardMap = $this->Procurement_model->build_destination_guard_map($scope['division_options']);
        $defaultDivisionId = (int)($scope['default_division_id'] ?? 0);
        $defaultDestination = (string)(($destinationGuardMap[$defaultDivisionId] ?? ['OTHER'])[0] ?? 'OTHER');

        $header = [
            'request_date' => date('Y-m-d'),
            'needed_date' => date('Y-m-d', strtotime('+1 day')),
            'division_id' => (int)($scope['default_division_id'] ?? 0),
            'destination_type' => $defaultDestination,
            'notes' => '',
            'request_no' => '',
            'status' => 'SUBMITTED',
        ];
        $lines = [];

        if (strtolower((string)$this->input->method()) === 'post') {
            [$headerInput, $linesInput] = $this->readDivisionRequestFormPayload();
            if ($headerInput !== null) {
                $header = array_merge($header, $headerInput);
                $lines = $linesInput;
                if (!$scope['is_purchase']) {
                    $header['division_id'] = $this->sanitizeDivisionRequestDivisionId((int)($header['division_id'] ?? 0), $scope);
                }

                $dbDebugBefore = (bool)$this->db->db_debug;
                $this->db->db_debug = false;
                try {
                    $result = $this->Procurement_model->create_division_request(
                        $header,
                        $lines,
                        (int)($this->current_user['id'] ?? 0),
                        (string)$this->input->ip_address()
                    );
                } finally {
                    $this->db->db_debug = $dbDebugBefore;
                }

                if ($result['ok'] ?? false) {
                    $this->session->set_flashdata('success', (string)($result['message'] ?? 'Pengajuan divisi berhasil dibuat.'));
                    redirect('procurement/division-po-sr/detail/' . (int)($result['data']['request_id'] ?? 0));
                    return;
                }

                $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal membuat pengajuan divisi.'));
            }
        }

        $this->render('procurement/division_po_sr_form', [
            'title' => 'Buat Pengajuan Divisi',
            'active_menu' => 'procurement.division',
            'mode' => 'create',
            'header' => $header,
            'lines' => $lines,
            'division_options' => $scope['division_options'],
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $destinationGuardMap,
            'uom_options' => $this->Purchase_model->list_active_uoms(),
            'vendor_options' => $this->Purchase_model->list_active_vendors(),
            'is_purchase_scope' => $scope['is_purchase'],
            'can_verify' => false,
            'stock_review_csrf' => $this->procurement_mutation_csrf(),
        ]);
    }

    public function division_po_sr_edit(int $id = 0)
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view'] || $id <= 0) {
            show_error('Pengajuan divisi tidak ditemukan atau tidak dapat diakses.', 403, 'Akses Ditolak');
            return;
        }

        $detail = $this->Procurement_model->get_division_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }
        if (!$this->isDivisionRequestAccessible((int)($detail['header']['division_id'] ?? 0), $scope)) {
            show_error('Pengajuan ini berada di luar scope divisi Anda.', 403, 'Akses Ditolak');
            return;
        }

        $status = strtoupper((string)($detail['header']['status'] ?? 'SUBMITTED'));
        $hasDocs = !empty((array)($detail['links'] ?? []));
        $canVerify = $scope['can_verify'] && $status !== 'VOID' && array_filter($detail['lines'], static function ($line) use ($status) {
            return in_array($line['review_status'] ?? ($status === 'SUBMITTED' ? 'PENDING' : $status), ['PENDING','REJECTED'], true);
        });
        $hasLineDecisions = (bool)array_filter($detail['lines'], static function ($line) { return !empty($line['reviewed_at']); });
        $canEditOwn = $scope['can_edit_own'] && in_array($status, ['SUBMITTED', 'REJECTED'], true) && !$hasDocs && !$hasLineDecisions;
        if (!$canVerify && !$canEditOwn) {
            $this->session->set_flashdata('error', 'Pengajuan ini tidak dapat diedit pada status saat ini.');
            redirect('procurement/division-po-sr/detail/' . $id);
            return;
        }

        $destinationGuardMap = $this->Procurement_model->build_destination_guard_map($scope['division_options']);

        $header = [
            'request_no' => (string)($detail['header']['request_no'] ?? ''),
            'request_date' => (string)($detail['header']['request_date'] ?? date('Y-m-d')),
            'needed_date' => (string)($detail['header']['needed_date'] ?? date('Y-m-d')),
            'division_id' => (int)($detail['header']['division_id'] ?? 0),
            'destination_type' => (string)($detail['header']['destination_type'] ?? (($destinationGuardMap[(int)($detail['header']['division_id'] ?? 0)] ?? ['OTHER'])[0] ?? 'OTHER')),
            'notes' => (string)($detail['header']['notes'] ?? ''),
            'status' => (string)($detail['header']['status'] ?? 'SUBMITTED'),
        ];
        $lines = (array)($detail['lines'] ?? []);

        if (strtolower((string)$this->input->method()) === 'post') {
            if ($canVerify && !$this->require_division_verification_csrf()) return;
            [$headerInput, $linesInput] = $this->readDivisionRequestFormPayload();
            if ($headerInput !== null) {
                $header = array_merge($header, $headerInput);
                $header['division_id'] = (int)($detail['header']['division_id'] ?? 0);
                $lines = $linesInput;

                $dbDebugBefore = (bool)$this->db->db_debug;
                $this->db->db_debug = false;
                try {
                    $result = $canVerify
                        ? $this->Procurement_model->verify_division_request(
                            $id,
                            $header,
                            $lines,
                            (int)($this->current_user['id'] ?? 0),
                            (string)$this->input->ip_address()
                        )
                        : $this->Procurement_model->update_division_request(
                            $id,
                            $header,
                            $lines,
                            (int)($this->current_user['id'] ?? 0)
                        );
                } finally {
                    $this->db->db_debug = $dbDebugBefore;
                }

                if ($result['ok'] ?? false) {
                    $this->session->set_flashdata('success', (string)($result['message'] ?? 'Pengajuan divisi berhasil diperbarui.'));
                    redirect('procurement/division-po-sr/detail/' . $id);
                    return;
                }

                $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal memproses pengajuan divisi.'));
            }
        }

        $this->render('procurement/division_po_sr_form', [
            'title' => $canVerify ? 'Verifikasi Pengajuan Divisi' : 'Edit Pengajuan Divisi',
            'active_menu' => 'procurement.division',
            'mode' => $canVerify ? 'verify' : 'edit',
            'request_id' => $id,
            'header' => $header,
            'lines' => $lines,
            'division_options' => $scope['division_options'],
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $destinationGuardMap,
            'uom_options' => $this->Purchase_model->list_active_uoms(),
            'vendor_options' => $this->Purchase_model->list_active_vendors(),
            'is_purchase_scope' => $scope['is_purchase'],
            'can_verify' => $canVerify,
            'stock_review_csrf' => $this->procurement_mutation_csrf(),
        ]);
    }

    public function division_stock_preview()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) { $this->jsonError('Akses pengajuan tidak tersedia.',403); return; }
        if (!$this->require_procurement_mutation_csrf()) return;
        $this->output->set_header('Cache-Control: private, no-store');
        $raw = (string)$this->input->raw_input_stream;
        $payload = strlen($raw)<=131072 ? json_decode($raw,true,16) : null;
        if (!is_array($payload) || !is_array($payload['header'] ?? null) || !is_array($payload['lines'] ?? null)
            || count($payload['lines'])>100 || !is_scalar($payload['request_id'] ?? 0)) {
            $this->jsonError('Data pemeriksaan stok tidak valid atau terlalu besar.',400); return;
        }
        $id = (int)($payload['request_id'] ?? 0); $header = $payload['header'];
        foreach ($payload['lines'] as $line) {
            if (!is_array($line)) { $this->jsonError('Baris pengajuan tidak valid.',400); return; }
            foreach ($line as $value) {
                if ($value!==null && !is_scalar($value)) { $this->jsonError('Isian baris pengajuan tidak valid.',400); return; }
            }
        }
        if ($id<0 || !is_scalar($header['division_id'] ?? null) || !is_string($header['destination_type'] ?? null)) {
            $this->jsonError('Divisi/lokasi stok tidak valid.',400); return;
        }
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            if ($id>0) {
                $query = $this->db->query('SELECT * FROM pur_division_request WHERE id=?',[$id]);
                $existing = $query ? $query->row_array() : [];
                if (!$existing) { $this->jsonError('Pengajuan tidak ditemukan.',404); return; }
                $header['division_id'] = (int)$existing['division_id'];
                $header['updated_at'] = (string)($existing['updated_at'] ?? '');
            }
            $divisionId = (int)$header['division_id'];
            if ($divisionId<=0 || !$this->isDivisionRequestAccessible($divisionId,$scope)) {
                $this->jsonError('Divisi berada di luar akses Anda.',403); return;
            }
            $guards = $this->Procurement_model->build_destination_guard_map($scope['division_options']);
            $destination = strtoupper(trim($header['destination_type']));
            if (!in_array($destination,$guards[$divisionId] ?? [],true)) {
                $this->jsonError('Pilih lokasi stok yang sesuai divisi.',422); return;
            }
            $header['destination_type'] = $destination;
            $result = $this->Procurement_model->preview_division_stock($id,$header,$payload['lines'],(int)($this->current_user['id'] ?? 0));
            $this->output->set_status_header(!empty($result['ok'])?200:422)->set_content_type('application/json')
                ->set_output(json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $e) {
            $this->jsonError('Pemeriksaan stok belum tersedia. Jangan menganggap saldo nol; coba lagi atau hubungi pengelola.',503);
        } finally { $this->db->db_debug = $dbDebugBefore; }
    }

    public function store_request_stock_preview()
    {
        if (!$this->can(self::PAGE_SR, 'create') && !$this->can(self::PAGE_SR, 'edit')) {
            $this->jsonError('Akses pemeriksaan stok SR tidak tersedia.',403); return;
        }
        if (!$this->require_procurement_mutation_csrf()) return;
        $this->output->set_header('Cache-Control: private, no-store');
        $raw = (string)$this->input->raw_input_stream;
        $payload = strlen($raw)<=131072 ? json_decode($raw,true,16) : null;
        try {
            if (!is_array($payload)) throw new InvalidArgumentException('Data pemeriksaan stok tidak valid.');
            $data = $this->Procurement_model->preview_manual_stock($payload, false);
            $this->output->set_content_type('application/json')->set_output(json_encode(['ok'=>true,'data'=>$data],JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (InvalidArgumentException $e) { $this->jsonError($e->getMessage(),422); }
        catch (Throwable $e) { $this->jsonError('Stok belum dapat dibaca. Coba perbarui; saldo ini bukan nol.',503); }
    }

    public function division_po_sr_profile_search()
    {
        $this->require_permission(self::PAGE_DIVISION, 'view');

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->search_division_request_candidates($q, $limit);
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal memuat kandidat pengajuan divisi: ' . $e->getMessage(), 500);
            return;
        }
        $this->db->db_debug = $dbDebugBefore;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'rows' => (array)($result['rows'] ?? []),
                'source' => (string)($result['source'] ?? 'EMPTY'),
                'allow_manual' => !empty($result['allow_manual']),
            ]));
    }

    public function division_po_sr_store()
    {
        $this->require_permission(self::PAGE_DIVISION, 'create');
        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Baris PO/SR wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->create_division_request(
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal membuat pengajuan PO/SR divisi.'), 422);
            return;
        }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function division_po_sr_detail(int $id = 0)
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view'] || $id <= 0) {
            show_error('Pengajuan divisi tidak ditemukan atau tidak dapat diakses.', 403, 'Akses Ditolak');
            return;
        }

        $detail = $this->Procurement_model->get_division_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }
        if (!$this->isDivisionRequestAccessible((int)($detail['header']['division_id'] ?? 0), $scope)) {
            show_error('Pengajuan ini berada di luar scope divisi Anda.', 403, 'Akses Ditolak');
            return;
        }

        $status = strtoupper((string)($detail['header']['status'] ?? 'SUBMITTED'));
        $hasDocs = !empty((array)($detail['links'] ?? []));
        $canVerify = $scope['can_verify'] && $status !== 'VOID' && array_filter($detail['lines'], static function ($line) use ($status) {
            return in_array($line['review_status'] ?? ($status === 'SUBMITTED' ? 'PENDING' : $status), ['PENDING','REJECTED'], true);
        });
        $hasLineDecisions = (bool)array_filter($detail['lines'], static function ($line) { return !empty($line['reviewed_at']); });
        $canEditOwn = $scope['can_edit_own'] && in_array($status, ['SUBMITTED', 'REJECTED'], true) && !$hasDocs && !$hasLineDecisions;
        $canVoid = ($scope['can_verify'] || $canEditOwn) && in_array($status, ['SUBMITTED', 'REJECTED'], true) && !$hasDocs;

        $detail['lines'] = $this->Procurement_model->current_stock_rows((array)$detail['lines'], (array)$detail['header']);

        $this->render('procurement/division_po_sr_detail', [
            'title' => 'Detail Pengajuan Divisi',
            'active_menu' => 'procurement.division',
            'detail' => $detail,
            'can_verify' => $canVerify,
            'can_edit' => $canEditOwn,
            'can_reject' => false,
            'can_void' => $canVoid,
            'is_purchase_scope' => $scope['is_purchase'],
            'stock_review_history' => $this->Procurement_model->stock_review_history('REQUEST',$id),
        ] + $this->division_notification_view($scope));
    }

    private function division_notification_view(array $scope): array
    {
        $channels = [];
        if (!empty($scope['can_view']) && (!empty($scope['can_create']) || !empty($scope['can_edit_own']) || !empty($scope['can_verify']))) {
            $this->load->model('Module_notification_model');
            $channels = $this->Module_notification_model->enabled_channels();
        }
        return ['notification_channels' => $channels, 'notification_csrf' => $channels ? $this->procurement_mutation_csrf() : ''];
    }

    public function division_po_sr_notify(int $id = 0)
    {
        // A PDF render can outlive a transient browser disconnect. Finish the
        // idempotent queue write so a second click does not create ambiguity.
        ignore_user_abort(true);
        if (!$this->require_procurement_mutation_csrf()) return;
        $scope = $this->divisionPoSrScope();
        if (empty($scope['can_view']) || (empty($scope['can_create']) && empty($scope['can_edit_own']) && empty($scope['can_verify']))) {
            $this->jsonError('Anda tidak memiliki izin mengirim pengajuan divisi.', 403);
            return;
        }
        // Check scope before reading request items or generating an outbound payload.
        $header = $this->db->select('division_id')->from('pur_division_request')->where('id', $id)->get()->row_array();
        if (!$header || !$this->isDivisionRequestAccessible((int)$header['division_id'], $scope)) {
            $this->jsonError('Pengajuan tidak ditemukan atau berada di luar divisi Anda.', 403);
            return;
        }
        $payload = $this->requestPayload();
        $channel = is_string($payload['channel'] ?? null) ? $payload['channel'] : '';
        if (!in_array($channel, ['WA', 'TELEGRAM'], true)) {
            $this->jsonError('Pilih kanal WhatsApp atau Telegram.', 422);
            return;
        }
        $this->load->model('Module_notification_model');
        if (!$this->Module_notification_model->allowed('DIVISION_REQUEST')) {
            $this->jsonError('Integrasi notifikasi belum tersedia dalam paket aplikasi.', 403);
            return;
        }
        try {
            $detail = $this->Procurement_model->get_division_request_detail($id);
            if (!$detail) throw new RuntimeException('Pengajuan tidak ditemukan.');
            $attachment = $channel === 'WA' ? $this->createDivisionNotificationPdf($detail) : [];
            $result = $this->Module_notification_model->enqueue_division($channel, $detail, (int)($this->current_user['id'] ?? 0), $attachment);
            $this->output->set_content_type('application/json')->set_output(json_encode($result));
        } catch (Throwable $error) {
            log_message('error', 'Division PO/SR WA notification failed for request #' . $id . ': ' . $error->getMessage());
            $message = $error instanceof RuntimeException
                ? $error->getMessage()
                : 'PDF pengajuan belum dapat dibuat. Detail teknis telah dicatat untuk diperiksa.';
            $this->jsonError($message, 422);
        }
    }

    public function division_po_sr_verify(int $id = 0)
    {
        $this->require_permission(self::PAGE_DIVISION, 'edit');
        if (!$this->require_procurement_mutation_csrf()) return;
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Baris hasil verifikasi wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->verify_division_request(
                $id,
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan verifikasi purchase.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function division_po_sr_line_action(int $id = 0, int $lineId = 0)
    {
        if (!$this->require_procurement_mutation_csrf()) return;
        $scope = $this->divisionPoSrScope();
        $header = $this->db->select('division_id')->from('pur_division_request')->where('id',$id)->get()->row_array();
        if (!$scope['can_view'] || !$scope['can_verify'] || !$header || !$this->isDivisionRequestAccessible((int)$header['division_id'],$scope)) {
            $this->jsonError('Anda tidak memiliki akses memverifikasi rincian pengajuan ini.',403); return;
        }
        $payload = $this->requestPayload();
        $action = $payload['action'] ?? '';
        if ($lineId<=0 || !is_string($action) || !in_array($action,['VERIFY','REJECT','REOPEN'],true)
            || ($action==='VERIFY' && !is_array($payload['line'] ?? null))
            || (isset($payload['line']) && !is_array($payload['line']))
            || (isset($payload['stock_review']) && !is_array($payload['stock_review']))
            || (isset($payload['reason']) && !is_string($payload['reason']))) {
            $this->jsonError('Data keputusan rincian tidak valid.',422); return;
        }
        foreach (($payload['line'] ?? []) as $value) {
            if ($value!==null && !is_scalar($value)) { $this->jsonError('Isian rincian tidak valid.',422); return; }
        }
        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->decide_division_request_line($id,$lineId,$action,$payload,(int)$this->current_user['id'],(string)$this->input->ip_address());
            $this->output->set_status_header(!empty($result['ok'])?200:422)->set_content_type('application/json')
                ->set_output(json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error','Division line decision: '.$e->getMessage());
            $this->jsonError('Keputusan belum dapat disimpan. Muat ulang untuk memeriksa status rincian.',500);
        } finally { $this->db->db_debug = $debug; }
    }

    public function division_po_sr_action(int $id = 0)
    {
        if (strtolower((string)$this->input->method()) !== 'post') {
            show_error('Method tidak diizinkan.', 405, 'Method Not Allowed');
            return;
        }
        if ($id <= 0) {
            show_404();
            return;
        }

        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) {
            show_error('Anda tidak memiliki akses ke pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $detail = $this->Procurement_model->get_division_request_detail($id);
        if (!$detail || !$this->isDivisionRequestAccessible((int)($detail['header']['division_id'] ?? 0), $scope)) {
            show_error('Pengajuan ini berada di luar scope divisi Anda.', 403, 'Akses Ditolak');
            return;
        }

        $action = strtoupper(trim((string)$this->input->post('action', true)));
        $notes = trim((string)$this->input->post('notes', true));
        if ($action === '') {
            $this->session->set_flashdata('error', 'Action wajib diisi.');
            redirect('procurement/division-po-sr/detail/' . $id);
            return;
        }

        if ($action === 'REJECT' && !$scope['can_verify']) {
            show_error('Hanya purchase yang dapat me-reject pengajuan.', 403, 'Akses Ditolak');
            return;
        }
        if ($action === 'VOID' && !($scope['can_verify'] || $scope['can_edit_own'])) {
            show_error('Anda tidak memiliki akses untuk meng-void pengajuan.', 403, 'Akses Ditolak');
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->apply_division_request_action(
                $id,
                $action,
                $notes,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $this->session->set_flashdata(
            ($result['ok'] ?? false) ? 'success' : 'error',
            (string)($result['message'] ?? 'Gagal memproses aksi pengajuan divisi.')
        );
        redirect('procurement/division-po-sr/detail/' . $id);
    }

    public function store_request_profile_search()
    {
        $this->require_permission(self::PAGE_SR, 'view');

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $rows = $this->Procurement_model->search_warehouse_profiles($q, $limit);
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal memuat profile gudang: ' . $e->getMessage(), 500);
            return;
        }
        $this->db->db_debug = $dbDebugBefore;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'rows' => $rows,
            ]));
    }

    public function store_request_store()
    {
        $this->require_permission(self::PAGE_SR, 'create');
        if (!$this->require_procurement_mutation_csrf()) {
            return;
        }

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Line Store Request wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->create_store_request(
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal membuat Store Request.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_update(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'edit');
        if (!$this->require_procurement_mutation_csrf()) {
            return;
        }
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Line Store Request wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->update_store_request(
                $id,
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memperbarui Store Request.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_action(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'edit');
        if (!$this->require_procurement_mutation_csrf()) {
            return;
        }
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $action = strtoupper(trim((string)($payload['action'] ?? '')));
        $notes = trim((string)($payload['notes'] ?? ''));

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->apply_store_request_action(
                $id,
                $action,
                $notes,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memproses aksi Store Request.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_split_preview(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'view');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->preview_split($id);
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal membaca split shortage: ' . $e->getMessage(), 500);
            return;
        }
        $this->db->db_debug = $dbDebugBefore;

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal membaca split shortage.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_fulfill(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'edit');
        if (!$this->require_procurement_mutation_csrf()) {
            return;
        }
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $date = trim((string)($payload['fulfillment_date'] ?? date('Y-m-d')));
        $notes = trim((string)($payload['notes'] ?? ''));

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->fulfill_auto_from_warehouse(
                $id,
                $date,
                $notes,
                (int)($this->current_user['id'] ?? 0)
            );
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal posting fulfillment: ' . $e->getMessage(), 500);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal posting fulfillment.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_repair_history(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }
        if (!$this->canRepairStoreRequestHistory()) {
            $this->jsonError('Akses repair histori SR dibatasi untuk admin.', 403);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->repair_void_store_request_history(
                $id,
                (int)($this->current_user['id'] ?? 0)
            );
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal repair histori SR: ' . $e->getMessage(), 500);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal repair histori SR.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_generate_po(int $id = 0)
    {
        $this->require_permission(self::PAGE_SR, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $shortagePayload = $this->Procurement_model->get_shortage_po_payload($id);
            if (!($shortagePayload['ok'] ?? false)) {
                $this->jsonError((string)($shortagePayload['message'] ?? 'Tidak ada shortage untuk dibuat PO.'), 422);
                return;
            }

            $purchaseTypeId = $this->Procurement_model->find_inventory_purchase_type_id();
            if ($purchaseTypeId === null || $purchaseTypeId <= 0) {
                $this->jsonError('Purchase type inventory untuk draft PO shortage belum tersedia.', 422);
                return;
            }

            $header = (array)($shortagePayload['header'] ?? []);
            $srNo = (string)($header['sr_no'] ?? ('SR#' . $id));
            $requestDate = (string)($header['request_date'] ?? date('Y-m-d'));
            $expectedDate = (string)($header['needed_date'] ?? date('Y-m-d'));

            $poHeader = [
                'request_date' => $requestDate,
                'expected_date' => $expectedDate,
                'purchase_type_id' => $purchaseTypeId,
                'destination_type' => 'GUDANG',
                'status' => 'DRAFT',
                'notes' => 'Auto draft PO shortage dari ' . $srNo,
                'external_ref_no' => $srNo,
            ];

            $poResult = $this->Purchase_model->store_order_with_lines(
                $poHeader,
                (array)($shortagePayload['lines'] ?? []),
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
            if (!($poResult['ok'] ?? false)) {
                $this->jsonError((string)($poResult['message'] ?? 'Gagal membuat draft PO shortage.'), 422);
                return;
            }

            $poData = (array)($poResult['data'] ?? []);
            $poId = (int)($poData['purchase_order_id'] ?? 0);
            if ($poId > 0) {
                $this->Procurement_model->link_store_request_po(
                    $id,
                    $poId,
                    'SHORTAGE',
                    'Auto generated dari split shortage',
                    (int)($this->current_user['id'] ?? 0)
                );
            }
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'message' => 'Draft PO shortage berhasil dibuat.',
                'data' => [
                    'purchase_order_id' => (int)($poData['purchase_order_id'] ?? 0),
                    'po_no' => (string)($poData['po_no'] ?? ''),
                    'redirect_url' => site_url('purchase-orders/detail/' . (int)($poData['purchase_order_id'] ?? 0)),
                ],
            ]));
    }

    private function divisionPoSrScope(): array
    {
        static $scope = null;
        if ($scope !== null) {
            return $scope;
        }

        $roleCode = strtoupper((string)($this->current_user['role_code'] ?? ''));
        $isAdminRole = in_array($roleCode, ['SUPERADMIN', 'CEO', 'ADMIN'], true);
        $canModuleView = $this->can(self::PAGE_DIVISION, 'view');
        $canModuleCreate = $this->can(self::PAGE_DIVISION, 'create');
        $canModuleEdit = $this->can(self::PAGE_DIVISION, 'edit');
        $isPurchase = $canModuleEdit || $isAdminRole;

        $allDivisionOptions = $this->Purchase_model->list_active_operational_divisions();
        $divisionScope = $this->resolveCurrentUserDivisionScope($allDivisionOptions);

        $canDivisionScopedCrud = !empty($divisionScope['allowed_division_ids']);
        $scope = [
            'is_purchase' => $isPurchase,
            'can_view' => $canModuleView || $isPurchase || $canDivisionScopedCrud,
            'can_create' => $canModuleCreate || $isPurchase || $canDivisionScopedCrud,
            'can_edit_own' => !$isPurchase && ($canModuleCreate || $canDivisionScopedCrud),
            'can_verify' => $isPurchase,
            'allowed_division_ids' => $isPurchase ? [] : (array)$divisionScope['allowed_division_ids'],
            'division_options' => $isPurchase ? $allDivisionOptions : (array)$divisionScope['division_options'],
            'default_division_id' => $isPurchase
                ? (int)($allDivisionOptions[0]['id'] ?? 0)
                : (int)($divisionScope['default_division_id'] ?? 0),
        ];

        return $scope;
    }

    private function readDivisionPoSrFilters(array $scope): array
    {
        $today = date('Y-m-d');
        $filters = [
            'tab' => trim((string)$this->input->get('tab', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_field' => strtoupper(trim((string)$this->input->get('date_field', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];

        if (!in_array($filters['tab'], ['notes', 'lines'], true)) {
            $filters['tab'] = 'notes';
        }
        if (!in_array($filters['date_field'], ['REQUEST_DATE', 'NEEDED_DATE'], true)) {
            $filters['date_field'] = 'REQUEST_DATE';
        }
        if ($filters['date_start'] === '' && $filters['date_end'] === '') {
            $filters['date_start'] = $today;
            $filters['date_end'] = $today;
        } elseif ($filters['date_start'] === '' && $filters['date_end'] !== '') {
            $filters['date_start'] = $filters['date_end'];
        } elseif ($filters['date_end'] === '' && $filters['date_start'] !== '') {
            $filters['date_end'] = $filters['date_start'];
        }
        if (!$scope['is_purchase']) {
            $filters['allowed_division_ids'] = $scope['allowed_division_ids'];
            if (
                $filters['division_id'] > 0
                && !in_array((int)$filters['division_id'], $scope['allowed_division_ids'], true)
            ) {
                $filters['division_id'] = 0;
            }
            if ($filters['division_id'] <= 0 && count($scope['allowed_division_ids']) === 1) {
                $filters['division_id'] = (int)$scope['allowed_division_ids'][0];
            }
        }

        return $filters;
    }

    private function buildDivisionPoSrReportPayload(array $filters, int $limit, array $scope): array
    {
        $rows = $this->Procurement_model->list_division_requests($filters, $limit);
        $lineRows = $this->Procurement_model->list_division_request_line_rows($filters, $limit);
        $lineRows = $this->prepareDivisionPoSrPrintRows($lineRows);
        $requestIds = array_values(array_unique(array_filter(array_merge(
            array_map(static function ($row) {
                return (int)($row['id'] ?? 0);
            }, $rows),
            array_map(static function ($line) {
                return (int)($line['request_id'] ?? 0);
            }, $lineRows)
        ), static function ($id) {
            return $id > 0;
        })));
        $linksMap = $this->Procurement_model->list_division_request_links_map($requestIds);

        $summary = [
            'request_count' => count($requestIds),
            'line_total' => count($lineRows),
            'qty_total' => 0.0,
            'division_count' => 0,
            'sr_doc_count' => 0,
            'po_doc_count' => 0,
            'status_counts' => [
                'SUBMITTED' => 0,
                'VERIFIED' => 0,
                'REJECTED' => 0,
                'VOID' => 0,
            ],
        ];

        $divisionNames = [];
        foreach ($rows as $row) {
            $divisionName = trim((string)($row['division_name'] ?? ''));
            if ($divisionName !== '') {
                $divisionNames[$divisionName] = true;
            }
            $summary['sr_doc_count'] += (int)($row['sr_count'] ?? 0);
            $summary['po_doc_count'] += (int)($row['po_count'] ?? 0);
            $statusKey = strtoupper((string)($row['status'] ?? ''));
            if (isset($summary['status_counts'][$statusKey])) {
                $summary['status_counts'][$statusKey]++;
            }
        }
        foreach ($lineRows as $line) {
            $summary['qty_total'] += (float)($line['qty_content_requested'] ?? 0);
            $divisionName = trim((string)($line['division_name'] ?? ''));
            if ($divisionName !== '') {
                $divisionNames[$divisionName] = true;
            }
        }
        $summary['division_count'] = count($divisionNames);

        return [
            'rows' => $rows,
            'line_rows' => $lineRows,
            'links_map' => $linksMap,
            'summary' => $summary,
            'scope' => $scope,
        ];
    }

    private function buildDivisionPoSrPrintPicker(array $filters): array
    {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($tomorrow > $weekEnd) {
            $weekEnd = $tomorrow;
        }

        $pickerFilters = $filters;
        $pickerFilters['tab'] = 'notes';
        $pickerFilters['date_field'] = 'NEEDED_DATE';
        $pickerFilters['date_start'] = $weekStart;
        $pickerFilters['date_end'] = $weekEnd;

        $rows = $this->Procurement_model->list_division_requests($pickerFilters, 500);
        $requestIds = array_values(array_unique(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $rows), static function ($id) {
            return $id > 0;
        })));

        return [
            'rows' => $rows,
            'links_map' => $this->Procurement_model->list_division_request_links_map($requestIds),
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
        ];
    }

    private function renderDivisionPoSrPdfBinary(string $html): ?string
    {
        $browserPath = $this->resolvePdfBrowserBinary();
        if ($browserPath === null) {
            return null;
        }

        $htmlFile = tempnam(sys_get_temp_dir(), 'dreq_html_');
        $pdfFile = tempnam(sys_get_temp_dir(), 'dreq_pdf_');
        if ($htmlFile === false || $pdfFile === false) {
            return null;
        }

        $htmlPath = $htmlFile . '.html';
        $pdfPath = $pdfFile . '.pdf';
        @rename($htmlFile, $htmlPath);
        @rename($pdfFile, $pdfPath);
        file_put_contents($htmlPath, $html);

        $fileUrl = 'file:///' . str_replace(' ', '%20', str_replace('\\', '/', $htmlPath));
        $command = 'timeout 30s "' . $browserPath . '" --headless --no-sandbox --disable-gpu --disable-dev-shm-usage --allow-file-access-from-files --print-to-pdf="' . $pdfPath . '" --print-to-pdf-no-header "' . $fileUrl . '"';
        exec($command, $output, $exitCode);

        $binary = null;
        if ($exitCode === 0 && is_file($pdfPath) && filesize($pdfPath) > 0) {
            $binary = file_get_contents($pdfPath);
        }

        @unlink($htmlPath);
        @unlink($pdfPath);
        return $binary !== false ? $binary : null;
    }

    private function resolvePdfBrowserBinary(): ?string
    {
        // PHP-FPM is intentionally restricted with open_basedir. Checking a
        // binary below /usr emits a warning that corrupts the JSON response.
        // The production renderer is provisioned at this stable Linux path.
        if (PHP_OS_FAMILY !== 'Windows') {
            return '/usr/bin/google-chrome';
        }

        $candidates = [
            'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
            'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        ];

        foreach ($candidates as $candidate) {
            if (@is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Both download and outbound PDF use the same read-only stock enrichment. */
    private function prepareDivisionPoSrPrintRows(array $lines, array $header = []): array
    {
        if ($header) {
            foreach ($lines as &$line) {
                $line += [
                    'request_id' => (int)($header['id'] ?? 0),
                    'request_no' => $header['request_no'] ?? '-',
                    'request_date' => $header['request_date'] ?? '',
                    'division_id' => (int)($header['division_id'] ?? 0),
                    'division_name' => $header['division_name'] ?? '-',
                    'destination_type' => $header['destination_type'] ?? '',
                    'needed_date' => $header['needed_date'] ?? '',
                    // Detail reader uses notes; download reader aliases it as line_notes.
                    'line_notes' => $line['notes'] ?? '',
                ];
            }
            unset($line);
        }
        return $this->Procurement_model->current_stock_rows($lines, $header);
    }

    private function createDivisionNotificationPdf(array $detail): array
    {
        $header = (array)($detail['header'] ?? []);
        $id = (int)($header['id'] ?? 0);
        $lines = (array)($detail['lines'] ?? []);
        if ($id <= 0 || !$lines) throw new RuntimeException('PDF pengajuan tidak dapat dibuat karena data rincian kosong.');
        $lineRows = $this->prepareDivisionPoSrPrintRows($lines, $header);
        $html = $this->load->view('procurement/division_po_sr_print', [
            'title' => 'Pengajuan PO / SR ' . (string)($header['request_no'] ?? ''), 'line_rows' => $lineRows,
            'filters' => ['date_start' => $header['needed_date'] ?? ''], 'printed_at' => date('Y-m-d H:i:s'),
            'show_print_controls' => false, 'pdf_mode' => true,
        ], true);
        $binary = $this->renderDivisionPoSrPdfBinary($html);
        if ($binary === null) throw new RuntimeException('PDF pengajuan gagal dibuat. Pastikan Google Chrome tersedia di server.');
        // Keep outgoing documents outside the public asset tree and inside
        // PHP-FPM's allowed application path.
        $directory = APPPATH . 'cache/wa-attachments';
        if (!is_dir($directory) && !@mkdir($directory, 02770, true) && !is_dir($directory)) throw new RuntimeException('Folder lampiran WA tidak dapat dibuat.');
        $safeNo = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($header['request_no'] ?? $id));
        $name = 'pengajuan-' . trim($safeNo, '-') . '.pdf';
        $path = $directory . '/' . $id . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.pdf';
        if (@file_put_contents($path, $binary, LOCK_EX) === false || !@chmod($path, 0640)) throw new RuntimeException('PDF pengajuan tidak dapat disimpan untuk pengiriman WA.');
        return ['path' => $path, 'name' => $name];
    }

    private function resolveCurrentUserDivisionScope(array $allDivisionOptions): array
    {
        $employeeId = (int)($this->current_user['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return [
                'allowed_division_ids' => [],
                'division_options' => [],
                'default_division_id' => 0,
            ];
        }

        $employee = $this->db
            ->select('d.division_code, d.division_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()
            ->row_array();
        $divisionCode = strtoupper(trim((string)($employee['division_code'] ?? '')));
        $allowedCodes = [];
        if ($divisionCode === 'BAR') {
            $allowedCodes = ['BAR', 'BAR_EVENT'];
        } elseif ($divisionCode === 'KITCHEN') {
            $allowedCodes = ['KITCHEN', 'KITCHEN_EVENT'];
        } elseif ($divisionCode === 'ROASTERY') {
            $allowedCodes = ['ROASTERY', 'ROASTERY_EVENT'];
        }

        if (empty($allowedCodes)) {
            return [
                'allowed_division_ids' => [],
                'division_options' => [],
                'default_division_id' => 0,
            ];
        }

        $divisionOptions = [];
        foreach ($allDivisionOptions as $option) {
            $code = strtoupper(trim((string)($option['code'] ?? '')));
            if (in_array($code, $allowedCodes, true)) {
                $divisionOptions[] = $option;
            }
        }

        return [
            'allowed_division_ids' => array_values(array_map(static function ($option) {
                return (int)($option['id'] ?? 0);
            }, $divisionOptions)),
            'division_options' => $divisionOptions,
            'default_division_id' => (int)($divisionOptions[0]['id'] ?? 0),
        ];
    }

    private function isDivisionRequestAccessible(int $divisionId, array $scope): bool
    {
        if ($scope['is_purchase'] ?? false) {
            return true;
        }
        return $divisionId > 0 && in_array($divisionId, (array)($scope['allowed_division_ids'] ?? []), true);
    }

    private function sanitizeDivisionRequestDivisionId(int $divisionId, array $scope): int
    {
        $allowedDivisionIds = (array)($scope['allowed_division_ids'] ?? []);
        if (empty($allowedDivisionIds)) {
            return $divisionId;
        }
        if (in_array($divisionId, $allowedDivisionIds, true)) {
            return $divisionId;
        }
        return (int)($allowedDivisionIds[0] ?? 0);
    }

    private function readDivisionRequestFormPayload(): array
    {
        $requestDate = trim((string)$this->input->post('request_date', true));
        $neededDate = trim((string)$this->input->post('needed_date', true));
        $divisionId = (int)$this->input->post('division_id', true);
        $destinationType = strtoupper(trim((string)$this->input->post('destination_type', true)));
        $notes = trim((string)$this->input->post('notes', true));
        $linesJson = (string)$this->input->post('lines_json', false);
        $decodedLines = json_decode($linesJson, true);
        if (!is_array($decodedLines)) {
            $decodedLines = [];
        }

        if ($requestDate === '' || $divisionId <= 0 || $destinationType === '') {
            $this->session->set_flashdata('error', 'Tanggal request, divisi, dan lokasi stok wajib diisi.');
            return [null, []];
        }
        if (empty($decodedLines)) {
            $this->session->set_flashdata('error', 'Minimal 1 line pengajuan wajib diisi.');
            return [null, []];
        }

        $reviewJson = $this->input->post('stock_review_json',false);
        $review = is_string($reviewJson) && strlen($reviewJson)<=8192 ? json_decode($reviewJson,true,8) : null;

        return [[
            'request_date' => $requestDate,
            'needed_date' => $neededDate,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'notes' => $notes,
            'stock_review' => is_array($review) ? $review : [],
        ], $decodedLines];
    }

    private function requestPayload(): array
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

    private function procurement_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::PROCUREMENT_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::PROCUREMENT_MUTATION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_division_verification_csrf(): bool
    {
        $token = $this->input->post('procurement_csrf',false);
        $expected = $this->session->userdata(self::PROCUREMENT_MUTATION_CSRF_SESSION_KEY);
        if ($this->input->method(true)!=='POST' || !is_string($token) || !is_string($expected)
            || !preg_match('/\A[a-f0-9]{64}\z/D',$expected) || !hash_equals($expected,$token)) {
            $this->jsonError('Sesi verifikasi tidak valid. Muat ulang halaman pengajuan.',403); return false;
        }
        return true;
    }

    private function require_procurement_mutation_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            $this->jsonError('Permintaan mutasi procurement tidak valid.', 405);
            return false;
        }

        $providedToken = (string)$this->input->get_request_header(
            self::PROCUREMENT_MUTATION_CSRF_CI_HEADER,
            true
        );
        $sessionToken = (string)$this->session->userdata(self::PROCUREMENT_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->jsonError('Permintaan mutasi procurement tidak valid.', 403);
            return false;
        }

        return true;
    }

    private function jsonError(string $message, int $statusCode = 400): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ]));
    }

    private function canRepairStoreRequestHistory(): bool
    {
        return in_array(
            strtoupper((string)($this->current_user['role_code'] ?? '')),
            ['SUPERADMIN', 'CEO', 'ADMIN'],
            true
        );
    }
}
