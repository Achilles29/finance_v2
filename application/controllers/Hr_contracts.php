<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hr_contracts extends MY_Controller
{
    private const PAGE_CONTRACT = 'hr.contract.index';
    private const PAGE_TEMPLATE = 'hr.contract_template.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Hr_contract_model');
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
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
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

    private function ctx(): string
    {
        $ctx = strtolower(trim((string)$this->input->get('ctx', true)));
        return in_array($ctx, ['employee', 'finance'], true) ? $ctx : 'finance';
    }

    private function url_with_ctx(string $path, array $query = []): string
    {
        $ctx = $this->ctx();
        $query = array_merge(['ctx' => $ctx], $query);
        return site_url($path) . '?' . http_build_query($query);
    }

    /**
     * Keep older contract entry points compatible: omitted compensation fields
     * use the effective prior contract and are never coerced to zero.
     */
    private function compensation_input_payload(string $method): array
    {
        $payload = [];
        foreach (['basic_salary', 'position_allowance', 'other_allowance', 'meal_rate'] as $field) {
            $value = $method === 'get'
                ? $this->input->get($field, true)
                : $this->input->post($field, true);
            if ($value === null || trim((string)$value) === '') {
                continue;
            }
            $payload[$field] = max(0, (float)$value);
        }

        return $payload;
    }

    public function index()
    {
        $this->require_permission(self::PAGE_CONTRACT, 'view');

        $rawStatus = strtoupper(trim((string)$this->input->get('status', true)));
        if ($rawStatus === '') {
            $rawStatus = 'ACTIVE';
        }
        if ($rawStatus === 'ALL') {
            $rawStatus = '';
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'employee_id' => (int)$this->input->get('employee_id', true),
            'template_id' => (int)$this->input->get('template_id', true),
            'status' => $rawStatus,
            'contract_type' => strtoupper(trim((string)$this->input->get('contract_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];

        $statusOptions = ['DRAFT', 'GENERATED', 'SIGNED', 'ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'];
        if ($filters['status'] !== '' && !in_array($filters['status'], $statusOptions, true)) {
            $filters['status'] = '';
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Hr_contract_model->count_contracts($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Hr_contract_model->list_contracts($filters, $pg['per_page'], $pg['offset']);
        $statusCounts = $this->Hr_contract_model->count_contracts_by_status($filters, $statusOptions);

        $data = [
            'title' => 'Operasional Kontrak Pegawai',
            'active_menu' => 'hr.contract',
            'ctx' => $this->ctx(),
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => $statusOptions,
            'status_counts' => $statusCounts,
            'contract_type_options' => ['K1', 'K2', 'K3', 'CUSTOM'],
            'employee_options' => $this->Hr_contract_model->get_employee_options(),
            'template_options' => $this->Hr_contract_model->get_template_options(),
            'can_create' => $this->can(self::PAGE_CONTRACT, 'create'),
            'can_edit' => $this->can(self::PAGE_CONTRACT, 'edit'),
        ];

        $this->render('hr_contract/index', $data);
    }

    public function templates()
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'is_active' => trim((string)$this->input->get('is_active', true)),
            'contract_type' => strtoupper(trim((string)$this->input->get('contract_type', true))),
        ];

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Hr_contract_model->count_templates($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Hr_contract_model->list_templates($filters, $pg['per_page'], $pg['offset']);

        $data = [
            'title' => 'Template Kontrak',
            'active_menu' => 'hr.contract',
            'ctx' => $this->ctx(),
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'contract_type_options' => ['K1', 'K2', 'K3', 'CUSTOM'],
            'can_create' => $this->can(self::PAGE_TEMPLATE, 'create'),
            'can_edit' => $this->can(self::PAGE_TEMPLATE, 'edit'),
            'can_delete' => $this->can(self::PAGE_TEMPLATE, 'delete'),
        ];

        $this->render('hr_contract/templates', $data);
    }

    public function template_edit($id = 0)
    {
        $id = (int)$id;
        $isCreate = $id <= 0;
        $this->require_permission(self::PAGE_TEMPLATE, $isCreate ? 'create' : 'edit');

        $row = $isCreate ? null : $this->Hr_contract_model->get_template($id);
        if (!$isCreate && !$row) {
            show_404();
        }

        if ($this->input->method() === 'post') {
            $payload = [
                'template_code' => trim((string)$this->input->post('template_code', true)),
                'template_name' => trim((string)$this->input->post('template_name', true)),
                'contract_type' => strtoupper(trim((string)$this->input->post('contract_type', true))),
                'duration_months' => (int)$this->input->post('duration_months', true),
                'body_html' => (string)$this->input->post('body_html', false),
                'is_active' => (int)$this->input->post('is_active', true) === 1 ? 1 : 0,
                'created_by' => (int)($this->current_user['id'] ?? 0),
            ];

            $save = $this->Hr_contract_model->save_template($payload, $id);
            $this->session->set_flashdata(!empty($save['ok']) ? 'success' : 'error', (string)($save['message'] ?? 'Gagal menyimpan template.'));

            if (!empty($save['ok']) && !empty($save['id'])) {
                redirect($this->url_with_ctx('hr-contracts/template-edit/' . (int)$save['id']));
                return;
            }
        }

        if (!$row) {
            $row = [
                'id' => 0,
                'template_code' => '',
                'template_name' => '',
                'contract_type' => 'K1',
                'duration_months' => 3,
                'body_html' => '<p>Isi template kontrak. Gunakan placeholder: {{NAMA_PEGAWAI}}, {{JABATAN}}, {{DIVISI}}, {{OUTLET}}, {{TANGGAL_KONTRAK}}, {{TANGGAL_MULAI}}, {{TANGGAL_AKHIR}}, {{DURASI_KONTRAK}}, {{GAJI_POKOK_DASAR}}, {{TUNJANGAN_JABATAN}}, {{TUNJANGAN_OBJEKTIF}}, {{UANG_MAKAN}}, {{TARIF_LEMBUR}} (Master Standar Lembur), {{TOTAL_KOMPENSASI_TETAP}}.</p>',
                'is_active' => 1,
            ];
        }

        $data = [
            'title' => ($isCreate ? 'Buat' : 'Edit') . ' Template Kontrak',
            'active_menu' => 'hr.contract',
            'ctx' => $this->ctx(),
            'row' => $row,
            'is_create' => $isCreate,
            'employee_options' => $this->Hr_contract_model->get_employee_options(),
            'contract_type_options' => ['K1', 'K2', 'K3', 'CUSTOM'],
            'can_delete' => !$isCreate && $this->can(self::PAGE_TEMPLATE, 'delete'),
        ];

        $this->render('hr_contract/template_edit', $data);
    }

    public function template_delete(int $id)
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'delete');
        if ($this->input->method() !== 'post') {
            redirect($this->url_with_ctx('hr-contracts/templates'));
            return;
        }

        $result = $this->Hr_contract_model->delete_template($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus template.'));
        redirect($this->url_with_ctx('hr-contracts/templates'));
    }

    public function template_preview()
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'view');
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $templateId = (int)$this->input->post('template_id', true);
        $employeeId = (int)$this->input->post('employee_id', true);
        if ($employeeId <= 0) {
            $employeeId = (int)($this->Hr_contract_model->get_employee_options()[0]['value'] ?? 0);
        }

        $payload = array_merge([
            'contract_type' => strtoupper(trim((string)$this->input->post('contract_type', true))),
            'duration_months' => (int)$this->input->post('duration_months', true),
            'start_date' => trim((string)$this->input->post('start_date', true)),
            'end_date' => trim((string)$this->input->post('end_date', true)),
        ], $this->compensation_input_payload('post'));

        $html = trim((string)$this->input->post('body_html', false));
        $preview = $html !== ''
            ? $this->Hr_contract_model->preview_contract_html_inline($html, $employeeId, $payload, (string)($payload['contract_type'] ?? 'CUSTOM'), max(1, (int)($payload['duration_months'] ?? 3)))
            : $this->Hr_contract_model->preview_contract_html($templateId, $employeeId, $payload);

        $this->output->set_content_type('text/html');
        if (empty($preview['ok'])) {
            echo '<div class="alert alert-warning mb-0">' . html_escape((string)($preview['message'] ?? 'Preview tidak tersedia.')) . '</div>';
            return;
        }

        echo (string)$preview['html'];
    }

    public function generate()
    {
        $this->require_permission(self::PAGE_CONTRACT, 'create');

        if ($this->input->get('preview', true) === '1') {
            $templateId = (int)$this->input->get('template_id', true);
            $employeeId = (int)$this->input->get('employee_id', true);
            $preview = $this->Hr_contract_model->preview_contract_html($templateId, $employeeId, array_merge([
                'contract_type' => '',
                'start_date' => trim((string)$this->input->get('start_date', true)),
                'end_date' => trim((string)$this->input->get('end_date', true)),
            ], $this->compensation_input_payload('get')));

            $this->output->set_content_type('text/html');
            if (empty($preview['ok'])) {
                echo '<div class="alert alert-warning mb-0">' . html_escape((string)($preview['message'] ?? 'Preview tidak tersedia.')) . '</div>';
                return;
            }
            echo (string)$preview['html'];
            return;
        }

        if ($this->input->method() === 'post') {
            $payload = array_merge([
                'employee_id' => (int)$this->input->post('employee_id', true),
                'template_id' => (int)$this->input->post('template_id', true),
                'previous_contract_id' => (int)$this->input->post('previous_contract_id', true),
                'contract_type' => '',
                'start_date' => trim((string)$this->input->post('start_date', true)),
                'end_date' => trim((string)$this->input->post('end_date', true)),
                'notes' => trim((string)$this->input->post('notes', true)),
            ], $this->compensation_input_payload('post'));

            $draft = $this->Hr_contract_model->create_draft_auto($payload, (int)($this->current_user['id'] ?? 0));
            if (empty($draft['ok']) || empty($draft['id'])) {
                $this->session->set_flashdata('error', (string)($draft['message'] ?? 'Gagal membuat draft kontrak.'));
                redirect($this->url_with_ctx('hr-contracts/generate'));
                return;
            }

            $generate = $this->Hr_contract_model->generate_contract((int)$draft['id'], (int)($this->current_user['id'] ?? 0));
            $this->session->set_flashdata(!empty($generate['ok']) ? 'success' : 'error', (string)($generate['message'] ?? 'Gagal generate kontrak.'));
            redirect($this->url_with_ctx('hr-contracts/view/' . (int)$draft['id']));
            return;
        }

        $employeeOptions = $this->Hr_contract_model->get_employee_options();
        $employeeIds = array_map(static function (array $row): int {
            return (int)($row['value'] ?? 0);
        }, $employeeOptions);

        $data = [
            'title' => 'Generate Kontrak Pegawai',
            'active_menu' => 'hr.contract',
            'ctx' => $this->ctx(),
            'employee_options' => $employeeOptions,
            'compensation_prefills' => $this->Hr_contract_model->get_employee_compensation_prefills($employeeIds),
            'template_options' => $this->Hr_contract_model->get_template_options(),
            'prefill' => [
                'template_id' => (int)$this->input->get('template_id', true),
                'employee_id' => (int)$this->input->get('employee_id', true),
            ],
        ];

        $this->render('hr_contract/generate', $data);
    }

    public function detail(int $id)
    {
        $this->view($id);
    }

    public function view(int $id)
    {
        $this->require_permission(self::PAGE_CONTRACT, 'view');

        $this->Hr_contract_model->refresh_document_verification($id);
        $row = $this->Hr_contract_model->get_contract_detail($id);
        if (!$row) {
            show_404();
        }

        $approvalMap = [];
        foreach ((array)($row['approvals'] ?? []) as $a) {
            $approvalMap[(string)$a['approver_role']] = $a;
        }

        $signatureMap = [];
        foreach ((array)($row['signatures'] ?? []) as $s) {
            $signatureMap[(string)$s['signer_role']] = $s;
        }

        $data = [
            'title' => 'Detail Kontrak: ' . (string)$row['contract_number'],
            'active_menu' => 'hr.contract',
            'ctx' => $this->ctx(),
            'row' => $row,
            'approval_map' => $approvalMap,
            'signature_map' => $signatureMap,
            'can_edit' => $this->can(self::PAGE_CONTRACT, 'edit'),
            'current_user' => $this->current_user,
        ];

        $this->render('hr_contract/detail', $data);
    }

    public function print_view(int $id)
    {
        $this->require_permission(self::PAGE_CONTRACT, 'view');

        $this->Hr_contract_model->refresh_document_verification($id);
        $row = $this->Hr_contract_model->get_contract_detail($id);
        if (!$row) {
            show_404();
        }

        $approvalMap = [];
        foreach ((array)($row['approvals'] ?? []) as $a) {
            $approvalMap[(string)$a['approver_role']] = $a;
        }

        $signatureMap = [];
        foreach ((array)($row['signatures'] ?? []) as $s) {
            $signatureMap[(string)$s['signer_role']] = $s;
        }

        $this->load->view('hr_contract/print', [
            'row' => $row,
            'approval_map' => $approvalMap,
            'signature_map' => $signatureMap,
            'verify_url' => site_url('hr-contracts/verify/' . (string)($row['verification_token'] ?? '')),
            'ctx' => $this->ctx(),
            'business_profile' => $this->business_profile(),
        ]);
    }

    public function create_draft()
    {
        $this->require_permission(self::PAGE_CONTRACT, 'create');

        if ($this->input->method() !== 'post') {
            redirect($this->url_with_ctx('hr/contracts'));
            return;
        }

        $payload = array_merge([
            'employee_id' => (int)$this->input->post('employee_id', true),
            'template_id' => (int)$this->input->post('template_id', true),
            'previous_contract_id' => (int)$this->input->post('previous_contract_id', true),
            'contract_type' => strtoupper(trim((string)$this->input->post('contract_type', true))),
            'start_date' => trim((string)$this->input->post('start_date', true)),
            'end_date' => trim((string)$this->input->post('end_date', true)),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], $this->compensation_input_payload('post'));

        $result = $this->Hr_contract_model->create_draft_auto($payload, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membuat draft kontrak.'));

        if (!empty($result['ok']) && !empty($result['id'])) {
            redirect($this->url_with_ctx('hr-contracts/view/' . (int)$result['id']));
            return;
        }

        redirect($this->url_with_ctx('hr/contracts'));
    }

    public function generate_contract(int $id)
    {
        $this->require_permission(self::PAGE_CONTRACT, 'edit');

        $result = $this->Hr_contract_model->generate_contract($id, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal generate kontrak.'));
        redirect($this->url_with_ctx('hr-contracts/view/' . $id));
    }

    public function approve(int $id)
    {
        $this->require_permission(self::PAGE_CONTRACT, 'edit');

        if ($this->input->method() !== 'post') {
            redirect($this->url_with_ctx('hr-contracts/view/' . $id));
            return;
        }

        $role = strtoupper(trim((string)$this->input->post('approver_role', true)));
        $action = strtoupper(trim((string)$this->input->post('approval_action', true)));
        $name = trim((string)$this->input->post('approver_name', true));
        $note = trim((string)$this->input->post('approval_note', true));

        if ($name === '') {
            $name = (string)($this->current_user['username'] ?? 'SYSTEM');
        }

        $result = $this->Hr_contract_model->approve_contract(
            $id,
            $role,
            $action,
            $name,
            (int)($this->current_user['id'] ?? 0),
            $note
        );

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses approval kontrak.'));
        redirect($this->url_with_ctx('hr-contracts/view/' . $id));
    }

    public function sign(int $id)
    {
        $this->require_permission(self::PAGE_CONTRACT, 'edit');

        if ($this->input->method() !== 'post') {
            redirect($this->url_with_ctx('hr-contracts/view/' . $id));
            return;
        }

        $role = strtoupper(trim((string)$this->input->post('signer_role', true)));
        $name = trim((string)$this->input->post('signer_name', true));
        $signatureData = trim((string)$this->input->post('signature_data', false));

        if ($name === '') {
            $name = (string)($this->current_user['username'] ?? 'SYSTEM');
        }

        $result = $this->Hr_contract_model->sign_contract(
            $id,
            $role,
            $name,
            (int)($this->current_user['id'] ?? 0),
            $signatureData
        );

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan tanda tangan kontrak.'));
        redirect($this->url_with_ctx('hr-contracts/view/' . $id));
    }

    public function transition(int $id, string $toStatus)
    {
        $this->require_permission(self::PAGE_CONTRACT, 'edit');

        if ($this->input->method() !== 'post') {
            redirect($this->url_with_ctx('hr-contracts/view/' . $id));
            return;
        }

        $result = $this->Hr_contract_model->transition_status($id, $toStatus);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal mengubah status kontrak.'));
        redirect($this->url_with_ctx('hr-contracts/view/' . $id));
    }
}
