<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hr_contract_model extends CI_Model
{
    private function compensation_defaults(int $employeeId, string $effectiveDate): array
    {
        $this->load->model('Compensation_model');
        return $this->Compensation_model->resolve_contract_default_for_draft($employeeId, $effectiveDate);
    }

    private function compensation_value(array $payload, string $field, float $fallback): float
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
            return round(max(0, $fallback), 2);
        }
        return round(max(0, (float)$payload[$field]), 2);
    }

    private function resolve_previous_contract_id(int $employeeId, string $startDate, int $requestedId = 0): ?int
    {
        if ($requestedId > 0) {
            $requested = $this->db->select('id')
                ->from('hr_contract')
                ->where('id', $requestedId)
                ->where('employee_id', $employeeId)
                ->limit(1)
                ->get()
                ->row_array();
            if ($requested) {
                return (int)$requested['id'];
            }
        }

        $active = $this->db->select('id')
            ->from('hr_contract')
            ->where('employee_id', $employeeId)
            ->where('status', 'ACTIVE')
            ->where('start_date <=', $startDate)
            ->where('end_date >=', $startDate)
            ->order_by('start_date', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        if ($active) {
            return (int)$active['id'];
        }

        $previous = $this->db->select('id')
            ->from('hr_contract')
            ->where('employee_id', $employeeId)
            ->where('end_date <', $startDate)
            ->where_in('status', ['ACTIVE', 'SIGNED', 'EXPIRED'])
            ->order_by('end_date', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        return $previous ? (int)$previous['id'] : null;
    }

    public function get_employee_options(): array
    {
        return $this->db->select("e.id AS value, CONCAT(e.employee_code, ' - ', e.employee_name) AS label", false)
            ->from('org_employee e')
            ->where('e.is_active', 1)
            ->order_by('e.employee_name', 'ASC')
            ->get()->result_array();
    }

    public function get_template_options(): array
    {
        return $this->db->select('id AS value, template_name AS label', false)
            ->from('hr_contract_template')
            ->where('is_active', 1)
            ->order_by('template_name', 'ASC')
            ->get()->result_array();
    }

    public function get_employee_compensation_prefills(array $employeeIds, string $effectiveDate = ''): array
    {
        if ($effectiveDate === '') {
            $effectiveDate = date('Y-m-d');
        }
        $this->load->model('Compensation_model');
        $result = [];
        foreach (array_values(array_unique(array_map('intval', $employeeIds))) as $employeeId) {
            if ($employeeId <= 0) {
                continue;
            }
            $result[$employeeId] = $this->Compensation_model->resolve_contract_default_for_draft($employeeId, $effectiveDate);
        }
        return $result;
    }

    public function count_templates(array $filters): int
    {
        $this->build_template_list_query($filters, false);
        $row = $this->db->select('COUNT(DISTINCT t.id) AS cnt', false)->get()->row_array();
        return (int)($row['cnt'] ?? 0);
    }

    public function list_templates(array $filters, int $limit, int $offset): array
    {
        $this->build_template_list_query($filters, true);
        return $this->db->order_by('t.template_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_template_list_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('t.*, u.username AS created_by_username, COUNT(c.id) AS contract_count', false);
        } else {
            $this->db->select('t.id');
        }

        $this->db->from('hr_contract_template t')
            ->join('auth_user u', 'u.id = t.created_by', 'left')
            ->join('hr_contract c', 'c.template_id = t.id', 'left')
            ->group_by('t.id');

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('t.template_code', $q)
                ->or_like('t.template_name', $q)
                ->or_like('t.body_html', $q)
                ->group_end();
        }

        $active = strtoupper(trim((string)($filters['is_active'] ?? '')));
        if ($active === '1' || $active === '0') {
            $this->db->where('t.is_active', (int)$active);
        }

        $contractType = strtoupper(trim((string)($filters['contract_type'] ?? '')));
        if ($contractType !== '') {
            $this->db->where('t.contract_type', $contractType);
        }
    }

    public function get_template(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->select('t.*, u.username AS created_by_username')
            ->from('hr_contract_template t')
            ->join('auth_user u', 'u.id = t.created_by', 'left')
            ->where('t.id', $id)
            ->limit(1)
            ->get()->row_array();

        return $row ?: null;
    }

    public function get_template_by_code(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $row = $this->db->from('hr_contract_template')
            ->where('template_code', $code)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();

        return $row ?: null;
    }

    public function save_template(array $payload, int $id = 0): array
    {
        $data = [
            'template_code' => strtoupper(trim((string)($payload['template_code'] ?? ''))),
            'template_name' => trim((string)($payload['template_name'] ?? '')),
            'contract_type' => strtoupper(trim((string)($payload['contract_type'] ?? 'K1'))),
            'duration_months' => max(1, (int)($payload['duration_months'] ?? 3)),
            'body_html' => (string)($payload['body_html'] ?? ''),
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
        ];

        if ($data['template_code'] === '' || $data['template_name'] === '') {
            return ['ok' => false, 'message' => 'Kode template dan nama template wajib diisi.'];
        }

        if (!in_array($data['contract_type'], ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $data['contract_type'] = 'CUSTOM';
        }

        $this->db->from('hr_contract_template')->where('template_code', $data['template_code']);
        if ($id > 0) {
            $this->db->where('id !=', $id);
        }
        if ($this->db->count_all_results() > 0) {
            return ['ok' => false, 'message' => 'Kode template sudah digunakan.'];
        }

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update('hr_contract_template', $data);
            if ($this->db->affected_rows() < 0) {
                return ['ok' => false, 'message' => 'Gagal memperbarui template kontrak.'];
            }
            return ['ok' => true, 'id' => $id, 'message' => 'Template kontrak berhasil diperbarui.'];
        }

        $data['created_by'] = !empty($payload['created_by']) ? (int)$payload['created_by'] : null;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('hr_contract_template', $data);
        $newId = (int)$this->db->insert_id();

        if ($newId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat template kontrak.'];
        }

        return ['ok' => true, 'id' => $newId, 'message' => 'Template kontrak berhasil dibuat.'];
    }

    public function delete_template(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID template tidak valid.'];
        }

        $template = $this->get_template($id);
        if (!$template) {
            return ['ok' => false, 'message' => 'Template tidak ditemukan.'];
        }

        $used = (int)$this->db->from('hr_contract')->where('template_id', $id)->count_all_results();
        if ($used > 0) {
            return ['ok' => false, 'message' => 'Template sudah dipakai kontrak dan tidak dapat dihapus. Nonaktifkan saja.'];
        }

        $this->db->where('id', $id)->delete('hr_contract_template');
        if ($this->db->affected_rows() <= 0) {
            return ['ok' => false, 'message' => 'Gagal menghapus template kontrak.'];
        }

        return ['ok' => true, 'message' => 'Template kontrak berhasil dihapus.'];
    }

    public function count_contracts(array $filters): int
    {
        $this->build_contract_list_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function count_contracts_by_status(array $filters, array $statuses): array
    {
        $filtersWithoutStatus = $filters;
        unset($filtersWithoutStatus['status']);

        $this->build_contract_list_query($filtersWithoutStatus, false);
        $rows = $this->db->select('c.status, COUNT(*) AS cnt', false)
            ->group_by('c.status')
            ->get()->result_array();

        $counts = ['ALL' => 0];
        foreach ($statuses as $status) {
            $counts[(string)$status] = 0;
        }

        foreach ($rows as $row) {
            $status = strtoupper((string)($row['status'] ?? ''));
            $cnt = (int)($row['cnt'] ?? 0);
            if ($status !== '') {
                $counts[$status] = $cnt;
            }
            $counts['ALL'] += $cnt;
        }

        return $counts;
    }

    public function list_contracts(array $filters, int $limit, int $offset): array
    {
        $this->build_contract_list_query($filters, true);
        return $this->db->order_by('c.start_date', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_contract_list_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('c.*, e.employee_code, e.employee_name, d.division_name, p.position_name, t.template_name');
        }

        $this->db->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left');

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.contract_number', $q)
                ->or_like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('c.notes', $q)
                ->group_end();
        }

        if (!empty($filters['status'])) {
            $this->db->where('c.status', (string)$filters['status']);
        }
        if (!empty($filters['contract_type'])) {
            $this->db->where('c.contract_type', (string)$filters['contract_type']);
        }
        if (!empty($filters['employee_id'])) {
            $this->db->where('c.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['template_id'])) {
            $this->db->where('c.template_id', (int)$filters['template_id']);
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('c.start_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('c.end_date <=', (string)$filters['date_end']);
        }
    }

    public function get_contract_detail(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->select('c.*, e.employee_code, e.employee_name, e.employee_nip, e.email, e.mobile_phone, d.division_name, p.position_name, t.template_name, t.template_code, t.duration_months AS template_duration_months, t.body_html AS template_body_html, pc.contract_number AS previous_contract_number, uc.username AS created_by_username, ug.username AS generated_by_username')
            ->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left')
            ->join('hr_contract pc', 'pc.id = c.previous_contract_id', 'left')
            ->join('auth_user uc', 'uc.id = c.created_by', 'left')
            ->join('auth_user ug', 'ug.id = c.generated_by', 'left')
            ->where('c.id', $id)
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            return null;
        }

        $row['approvals'] = $this->db->from('hr_contract_approval')
            ->where('contract_id', $id)
            ->order_by('approver_role', 'ASC')
            ->get()->result_array();

        $row['signatures'] = $this->db->select('id, contract_id, signer_role, signer_name, signer_user_id, signature_data, signed_at, ip_address')
            ->from('hr_contract_signature')
            ->where('contract_id', $id)
            ->order_by('signer_role', 'ASC')
            ->get()->result_array();

        $row['snapshot'] = $this->db->from('hr_contract_comp_snapshot')
            ->where('contract_id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;

        if (!empty($row['snapshot']['id'])) {
            $row['snapshot_lines'] = $this->db->from('hr_contract_comp_snapshot_line')
                ->where('snapshot_id', (int)$row['snapshot']['id'])
                ->order_by('sort_order', 'ASC')
                ->get()->result_array();
        } else {
            $row['snapshot_lines'] = [];
        }

        $row['render_vars'] = $this->build_contract_render_vars($row);
        $row['body_html_rendered'] = $this->build_rendered_contract_body($row);

        return $row;
    }

    public function get_contract_by_token(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = $this->db->select('id')->from('hr_contract')->where('verification_token', $token)->limit(1)->get()->row_array();
        if (!$row) {
            return null;
        }

        return $this->get_contract_detail((int)$row['id']);
    }

    public function create_draft_auto(array $payload, int $actorId): array
    {
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $startDate = trim((string)($payload['start_date'] ?? ''));
        $endDate = trim((string)($payload['end_date'] ?? ''));
        $templateId = (int)($payload['template_id'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $startDate === '') {
            return ['ok' => false, 'message' => 'Pegawai dan tanggal mulai wajib diisi.'];
        }

        $employee = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        if (!$employee) {
            return ['ok' => false, 'message' => 'Data pegawai tidak ditemukan.'];
        }

        $template = null;
        if ($templateId > 0) {
            $template = $this->db->from('hr_contract_template')->where('id', $templateId)->limit(1)->get()->row_array();
            if (!$template) {
                return ['ok' => false, 'message' => 'Template kontrak tidak ditemukan.'];
            }
        }

        if ($endDate === '') {
            $duration = max(1, (int)($template['duration_months'] ?? 3));
            $endDate = $this->add_months($startDate, $duration);
        }

        if ($startDate > $endDate) {
            return ['ok' => false, 'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'];
        }

        $contractType = strtoupper(trim((string)($payload['contract_type'] ?? '')));
        if ($contractType === '' && !empty($template['contract_type'])) {
            $contractType = strtoupper((string)$template['contract_type']);
        }
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $contractNumber = $this->generate_contract_number($contractType, $startDate);
        $compensation = $this->compensation_defaults($employeeId, $startDate);
        $hasCompensationInput = false;
        foreach (['basic_salary', 'position_allowance', 'other_allowance', 'meal_rate'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && $payload[$field] !== '') {
                $hasCompensationInput = true;
                break;
            }
        }
        if (!$hasCompensationInput && (string)($compensation['source'] ?? '') !== 'CONTRACT') {
            return ['ok' => false, 'message' => 'Kontrak pertama harus dibuat melalui Generate Kontrak agar seluruh komponen gaji disepakati dan tersnapshot.'];
        }
        $previousContractId = $this->resolve_previous_contract_id(
            $employeeId,
            $startDate,
            (int)($payload['previous_contract_id'] ?? 0)
        );

        $insert = [
            'contract_number' => $contractNumber,
            'employee_id' => $employeeId,
            'template_id' => $templateId > 0 ? $templateId : null,
            'previous_contract_id' => $previousContractId,
            'contract_type' => $contractType,
            'status' => 'DRAFT',
            'position_snapshot' => (string)($employee['position_name'] ?? ''),
            'division_snapshot' => (string)($employee['division_name'] ?? ''),
            'basic_salary' => $this->compensation_value($payload, 'basic_salary', (float)($compensation['basic_salary'] ?? 0)),
            'position_allowance' => $this->compensation_value($payload, 'position_allowance', (float)($compensation['position_allowance'] ?? 0)),
            'other_allowance' => $this->compensation_value($payload, 'other_allowance', (float)($compensation['objective_allowance'] ?? 0)),
            'meal_rate' => $this->compensation_value($payload, 'meal_rate', (float)($compensation['meal_rate'] ?? 0)),
            // Legacy schema column retained only for old document records.
            // New contracts never define a personal overtime rate.
            'overtime_rate' => 0.0,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $notes,
            'created_by' => $actorId > 0 ? $actorId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($template['body_html'])) {
            $insert['body_html'] = (string)$template['body_html'];
        }

        $this->db->insert('hr_contract', $insert);
        $newId = (int)$this->db->insert_id();

        if ($newId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat draft kontrak.'];
        }

        return ['ok' => true, 'id' => $newId, 'message' => 'Draft kontrak berhasil dibuat.'];
    }

    public function preview_contract_html(int $templateId, int $employeeId, array $payload = []): array
    {
        $template = $this->get_template($templateId);
        if (!$template) {
            return ['ok' => false, 'message' => 'Template kontrak tidak ditemukan.'];
        }
        return $this->preview_contract_html_inline((string)($template['body_html'] ?? ''), $employeeId, $payload, (string)($template['contract_type'] ?? 'CUSTOM'), (int)($template['duration_months'] ?? 3));
    }

    public function preview_contract_html_inline(string $templateHtml, int $employeeId, array $payload = [], string $fallbackContractType = 'CUSTOM', int $fallbackDurationMonths = 3): array
    {
        $templateHtml = trim($templateHtml);
        if ($templateHtml === '') {
            return ['ok' => false, 'message' => 'Body template kosong.'];
        }

        $employee = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        if (!$employee) {
            return ['ok' => false, 'message' => 'Data pegawai tidak ditemukan.'];
        }

        $startDate = trim((string)($payload['start_date'] ?? date('Y-m-d')));
        if ($startDate === '') {
            $startDate = date('Y-m-d');
        }

        $endDate = trim((string)($payload['end_date'] ?? ''));
        if ($endDate === '') {
            $endDate = $this->add_months($startDate, max(1, $fallbackDurationMonths));
        }

        $contractType = strtoupper(trim((string)($payload['contract_type'] ?? $fallbackContractType ?: 'CUSTOM')));
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $compensation = $this->compensation_defaults($employeeId, $startDate);
        $basicSalary = $this->compensation_value($payload, 'basic_salary', (float)($compensation['basic_salary'] ?? 0));
        $positionAllowance = $this->compensation_value($payload, 'position_allowance', (float)($compensation['position_allowance'] ?? 0));
        $otherAllowance = $this->compensation_value($payload, 'other_allowance', (float)($compensation['objective_allowance'] ?? 0));
        $mealRate = $this->compensation_value($payload, 'meal_rate', (float)($compensation['meal_rate'] ?? 0));

        $vars = $this->build_contract_render_vars([
            'contract_number' => 'DRAFT-' . date('YmdHis'),
            'contract_type' => $contractType,
            'employee_id' => $employeeId,
            'employee_code' => (string)($employee['employee_code'] ?? ''),
            'employee_name' => (string)($employee['employee_name'] ?? ''),
            'division_name' => (string)($payload['division_snapshot'] ?? $employee['division_name'] ?? ''),
            'position_name' => (string)($payload['position_snapshot'] ?? $employee['position_name'] ?? ''),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'basic_salary' => $basicSalary,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'document_issued_at' => date('Y-m-d H:i:s'),
            'template_duration_months' => $fallbackDurationMonths,
        ], [
            'contract_type' => $contractType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'basic_salary' => $basicSalary,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'division_name' => (string)($payload['division_snapshot'] ?? $employee['division_name'] ?? ''),
            'position_name' => (string)($payload['position_snapshot'] ?? $employee['position_name'] ?? ''),
            'employee_code' => (string)($employee['employee_code'] ?? ''),
            'employee_name' => (string)($employee['employee_name'] ?? ''),
        ]);

        return [
            'ok' => true,
            'html' => $this->sanitize_contract_body($this->render_contract_html($templateHtml, $vars)),
            'vars' => $vars,
        ];
    }

    public function generate_contract(int $id, int $actorId): array
    {
        $row = $this->db->select('c.*, e.employee_code, e.employee_name, d.division_name, p.position_name, t.body_html AS template_body_html')
            ->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left')
            ->where('c.id', $id)
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }
        if ((string)$row['status'] !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Generate hanya bisa dilakukan dari status DRAFT.'];
        }

        $contractType = strtoupper((string)$row['contract_type']);
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $contractNumber = $this->generate_contract_number($contractType, (string)$row['start_date'], $id);
        // A draft already carries the agreed figures. Do not re-read Master
        // Pegawai here, otherwise an unrelated master edit changes a contract.
        $basic = (float)($row['basic_salary'] ?? 0);
        $positionAllowance = (float)($row['position_allowance'] ?? 0);
        $otherAllowance = (float)($row['other_allowance'] ?? 0);
        $mealRate = (float)($row['meal_rate'] ?? 0);
        // Overtime belongs to the operational master standard, never to a
        // contract's fixed compensation snapshot.
        $overtimeRate = 0.0;
        $fixedTotal = $basic + $positionAllowance + $otherAllowance;

        $templateHtml = trim((string)($row['template_body_html'] ?? ''));
        if ($templateHtml === '') {
            $templateHtml = trim((string)($row['body_html'] ?? ''));
        }

        $renderVars = $this->build_contract_render_vars($row, [
            'contract_number' => $contractNumber,
            'contract_type' => $contractType,
            'employee_code' => (string)($row['employee_code'] ?? ''),
            'employee_name' => (string)($row['employee_name'] ?? ''),
            'division_name' => (string)($row['division_name'] ?? ''),
            'position_name' => (string)($row['position_name'] ?? ''),
            'start_date' => (string)($row['start_date'] ?? ''),
            'end_date' => (string)($row['end_date'] ?? ''),
            'basic_salary' => $basic,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'overtime_rate' => $overtimeRate,
            'document_issued_at' => date('Y-m-d H:i:s'),
        ]);

        $renderedBody = $this->render_contract_html($templateHtml, $renderVars);

        $this->db->trans_start();

        $verificationToken = $this->ensure_unique_verification_token();

        $this->db->where('id', $id)->update('hr_contract', [
            'contract_number' => $contractNumber,
            'status' => 'GENERATED',
            'position_snapshot' => (string)($row['position_name'] ?? ''),
            'division_snapshot' => (string)($row['division_name'] ?? ''),
            'basic_salary' => $basic,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'overtime_rate' => $overtimeRate,
            'verification_token' => $verificationToken,
            'body_html' => $this->sanitize_contract_body($renderedBody),
            'generated_by' => $actorId > 0 ? $actorId : null,
            'generated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $snapshot = $this->db->from('hr_contract_comp_snapshot')->where('contract_id', $id)->limit(1)->get()->row_array();
        $snapshotPayload = [
            'contract_id' => $id,
            'employee_id' => (int)$row['employee_id'],
            'effective_start' => (string)$row['start_date'],
            'effective_end' => (string)$row['end_date'],
            'basic_salary_amount' => $basic,
            'position_allowance_amount' => $positionAllowance,
            'other_allowance_amount' => $otherAllowance,
            'meal_rate_amount' => $mealRate,
            'overtime_rate_amount' => $overtimeRate,
            'fixed_total_amount' => $fixedTotal,
            'source_notes' => 'Auto snapshot saat generate kontrak',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($snapshot) {
            $snapshotId = (int)$snapshot['id'];
            $this->db->where('id', $snapshotId)->update('hr_contract_comp_snapshot', $snapshotPayload);
        } else {
            $snapshotPayload['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('hr_contract_comp_snapshot', $snapshotPayload);
            $snapshotId = (int)$this->db->insert_id();
        }

        if ($snapshotId > 0) {
            $this->db->where('snapshot_id', $snapshotId)->delete('hr_contract_comp_snapshot_line');
            $lines = [
                ['code' => 'GAJI_POKOK', 'name' => 'Gaji Pokok', 'type' => 'EARNING', 'amount' => $basic, 'sort' => 1],
                ['code' => 'TUNJANGAN_JABATAN', 'name' => 'Tunjangan Jabatan', 'type' => 'EARNING', 'amount' => $positionAllowance, 'sort' => 2],
                ['code' => 'TUNJANGAN_LAIN', 'name' => 'Tunjangan Lain', 'type' => 'EARNING', 'amount' => $otherAllowance, 'sort' => 3],
                ['code' => 'UANG_MAKAN', 'name' => 'Uang Makan', 'type' => 'EARNING', 'amount' => $mealRate, 'sort' => 4],
            ];

            foreach ($lines as $line) {
                $this->db->insert('hr_contract_comp_snapshot_line', [
                    'snapshot_id' => $snapshotId,
                    'component_code_snapshot' => $line['code'],
                    'component_name_snapshot' => $line['name'],
                    'component_type' => $line['type'],
                    'amount' => (float)$line['amount'],
                    'sort_order' => (int)$line['sort'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal generate kontrak dan snapshot.'];
        }

        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Kontrak berhasil di-generate dengan nomor otomatis dan snapshot gaji.'];
    }

    public function approve_contract(int $id, string $approverRole, string $approvalAction, string $approverName, int $approverUserId, string $note = ''): array
    {
        $approverRole = strtoupper(trim($approverRole));
        $approvalAction = strtoupper(trim($approvalAction));
        if (!in_array($approverRole, ['EMPLOYEE', 'COMPANY'], true)) {
            return ['ok' => false, 'message' => 'Role approval tidak valid.'];
        }
        if (!in_array($approvalAction, ['APPROVED', 'REVOKED'], true)) {
            return ['ok' => false, 'message' => 'Aksi approval tidak valid.'];
        }

        $contract = $this->db->select('id,status')->from('hr_contract')->where('id', $id)->limit(1)->get()->row_array();
        if (!$contract) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }
        if (!in_array((string)$contract['status'], ['DRAFT', 'GENERATED', 'SIGNED'], true)) {
            return ['ok' => false, 'message' => 'Status kontrak tidak dapat diproses approval.'];
        }

        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO hr_contract_approval (contract_id, approver_role, approval_status, approver_name, approver_user_id, approval_note, approved_at, revoked_at, ip_address, user_agent, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    approval_status = VALUES(approval_status),
                    approver_name = VALUES(approver_name),
                    approver_user_id = VALUES(approver_user_id),
                    approval_note = VALUES(approval_note),
                    approved_at = VALUES(approved_at),
                    revoked_at = VALUES(revoked_at),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    updated_at = VALUES(updated_at)";

        $this->db->query($sql, [
            $id,
            $approverRole,
            $approvalAction,
            $approverName,
            $approverUserId > 0 ? $approverUserId : null,
            $note,
            $now,
            $approvalAction === 'REVOKED' ? $now : null,
            (string)$this->input->ip_address(),
            substr((string)$this->input->user_agent(), 0, 255),
            $now,
            $now,
        ]);

        $this->sync_signed_status($id);
        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Approval kontrak berhasil disimpan.'];
    }

    public function sign_contract(int $id, string $signerRole, string $signerName, int $signerUserId, string $signatureData): array
    {
        $signerRole = strtoupper(trim($signerRole));
        if (!in_array($signerRole, ['EMPLOYEE', 'COMPANY'], true)) {
            return ['ok' => false, 'message' => 'Role tanda tangan tidak valid.'];
        }

        $signatureData = trim($signatureData);
        if ($signatureData === '' || strlen($signatureData) < 30 || strpos($signatureData, 'data:image') !== 0) {
            return ['ok' => false, 'message' => 'Data tanda tangan tidak valid.'];
        }

        $contract = $this->db->select('id,status')->from('hr_contract')->where('id', $id)->limit(1)->get()->row_array();
        if (!$contract) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }
        if (!in_array((string)$contract['status'], ['GENERATED', 'SIGNED'], true)) {
            return ['ok' => false, 'message' => 'Status kontrak tidak bisa ditandatangani.'];
        }

        $this->db->trans_start();
        $this->db->where('contract_id', $id)->where('signer_role', $signerRole)->delete('hr_contract_signature');

        $this->db->insert('hr_contract_signature', [
            'contract_id' => $id,
            'signer_role' => $signerRole,
            'signer_name' => $signerName,
            'signer_user_id' => $signerUserId > 0 ? $signerUserId : null,
            'signature_data' => $signatureData,
            'signed_at' => date('Y-m-d H:i:s'),
            'ip_address' => (string)$this->input->ip_address(),
            'user_agent' => substr((string)$this->input->user_agent(), 0, 255),
        ]);

        $this->sync_signed_status($id);

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan tanda tangan kontrak.'];
        }

        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Tanda tangan kontrak berhasil disimpan.'];
    }

    public function portal_employee_signoff(int $id, string $employeeName, int $employeeUserId, string $signatureData, string $note = ''): array
    {
        $employeeName = trim($employeeName);
        if ($employeeName === '') {
            return ['ok' => false, 'message' => 'Nama pegawai untuk tanda tangan tidak valid.'];
        }

        $signatureData = trim($signatureData);
        if ($signatureData === '' || strlen($signatureData) < 30 || strpos($signatureData, 'data:image') !== 0) {
            return ['ok' => false, 'message' => 'Data tanda tangan tidak valid.'];
        }

        $contract = $this->db->select('id,status')
            ->from('hr_contract')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$contract) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }

        $status = strtoupper(trim((string)($contract['status'] ?? 'DRAFT')));
        if (!in_array($status, ['GENERATED', 'SIGNED'], true)) {
            return ['ok' => false, 'message' => 'Kontrak belum siap untuk ditandatangani pegawai.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        try {
            $sql = "INSERT INTO hr_contract_approval (contract_id, approver_role, approval_status, approver_name, approver_user_id, approval_note, approved_at, revoked_at, ip_address, user_agent, created_at, updated_at)
                    VALUES (?, 'EMPLOYEE', 'APPROVED', ?, ?, ?, ?, NULL, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        approval_status = VALUES(approval_status),
                        approver_name = VALUES(approver_name),
                        approver_user_id = VALUES(approver_user_id),
                        approval_note = VALUES(approval_note),
                        approved_at = VALUES(approved_at),
                        revoked_at = VALUES(revoked_at),
                        ip_address = VALUES(ip_address),
                        user_agent = VALUES(user_agent),
                        updated_at = VALUES(updated_at)";
            $this->db->query($sql, [
                $id,
                $employeeName,
                $employeeUserId > 0 ? $employeeUserId : null,
                trim($note),
                $now,
                (string)$this->input->ip_address(),
                substr((string)$this->input->user_agent(), 0, 255),
                $now,
                $now,
            ]);

            $this->db->where('contract_id', $id)->where('signer_role', 'EMPLOYEE')->delete('hr_contract_signature');
            $this->db->insert('hr_contract_signature', [
                'contract_id' => $id,
                'signer_role' => 'EMPLOYEE',
                'signer_name' => $employeeName,
                'signer_user_id' => $employeeUserId > 0 ? $employeeUserId : null,
                'signature_data' => $signatureData,
                'signed_at' => $now,
                'ip_address' => (string)$this->input->ip_address(),
                'user_agent' => substr((string)$this->input->user_agent(), 0, 255),
            ]);

            $this->sync_signed_status($id);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan persetujuan dan tanda tangan kontrak.');
            }
            $this->db->trans_commit();
            $this->refresh_document_verification($id);
            return ['ok' => true, 'message' => 'Persetujuan dan tanda tangan kontrak berhasil disimpan.'];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function transition_status(int $id, string $toStatus): array
    {
        $toStatus = strtoupper(trim($toStatus));
        $allowed = ['GENERATED', 'SIGNED', 'ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'];
        if (!in_array($toStatus, $allowed, true)) {
            return ['ok' => false, 'message' => 'Status tujuan tidak valid.'];
        }

        $row = $this->db->select('id, employee_id, previous_contract_id, status, start_date, end_date')
            ->from('hr_contract')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }

        $from = strtoupper((string)$row['status']);
        $map = [
            'DRAFT' => ['GENERATED', 'CANCELLED'],
            'GENERATED' => ['SIGNED', 'ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'],
            'SIGNED' => ['ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'],
            'ACTIVE' => ['EXPIRED', 'TERMINATED'],
            'EXPIRED' => [],
            'TERMINATED' => [],
            'CANCELLED' => [],
        ];

        if (!in_array($toStatus, $map[$from] ?? [], true)) {
            return ['ok' => false, 'message' => 'Transisi status dari ' . $from . ' ke ' . $toStatus . ' tidak diizinkan.'];
        }

        if ($toStatus === 'SIGNED' && !$this->has_complete_signoff($id)) {
            return ['ok' => false, 'message' => 'Belum bisa SIGNED: approval dan tanda tangan EMPLOYEE + COMPANY wajib lengkap.'];
        }

        if ($toStatus === 'ACTIVE') {
            if (!$this->has_complete_signoff($id)) {
                return ['ok' => false, 'message' => 'Belum bisa ACTIVE: approval dan tanda tangan belum lengkap.'];
            }
            $startDate = (string)($row['start_date'] ?? '');
            $endDate = (string)($row['end_date'] ?? '');
            $today = date('Y-m-d');
            if ($startDate === '' || $endDate === '' || $endDate < $startDate) {
                return ['ok' => false, 'message' => 'Rentang tanggal kontrak tidak valid.'];
            }
            if ($startDate > $today) {
                return ['ok' => false, 'message' => 'Kontrak baru dapat ACTIVE pada atau setelah tanggal mulai.'];
            }
            if ($endDate < $today) {
                return ['ok' => false, 'message' => 'Kontrak telah melewati tanggal akhir dan tidak dapat diaktifkan.'];
            }
        }

        $this->db->trans_begin();
        try {
            if ($toStatus === 'ACTIVE') {
                $this->load->model('Compensation_model');
                // Serialize a renewal against the employee's current active contract.
                $this->db->query(
                    'SELECT id FROM hr_contract WHERE employee_id = ? AND status = ? FOR UPDATE',
                    [(int)$row['employee_id'], 'ACTIVE']
                );

                $conflicts = $this->Compensation_model->find_active_overlaps(
                    (int)$row['employee_id'],
                    $startDate,
                    $endDate,
                    $id
                );
                $previousContractId = (int)($row['previous_contract_id'] ?? 0);
                foreach ($conflicts as $conflict) {
                    $conflictId = (int)($conflict['id'] ?? 0);
                    if ($previousContractId <= 0 || $conflictId !== $previousContractId) {
                        throw new RuntimeException('Ada kontrak ACTIVE yang bertumpang tindih: ' . (string)($conflict['contract_number'] ?? '#') . '. Hubungkan sebagai kontrak sebelumnya atau koreksi masa berlaku lebih dahulu.');
                    }

                    $previousEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
                    if ($previousEnd < (string)($conflict['start_date'] ?? '')) {
                        throw new RuntimeException('Kontrak sebelumnya tidak dapat ditutup karena tanggal mulai kontrak baru tidak valid.');
                    }
                    if (!$this->db->where('id', $conflictId)->update('hr_contract', [
                        'end_date' => $previousEnd,
                        'status' => $previousEnd < $today ? 'EXPIRED' : 'ACTIVE',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ])) {
                        throw new RuntimeException('Gagal menutup masa berlaku kontrak sebelumnya.');
                    }
                }
            }

            if (!$this->db->where('id', $id)->where('status', $from)->update('hr_contract', [
                'status' => $toStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) || $this->db->affected_rows() <= 0) {
                throw new RuntimeException('Status kontrak berubah oleh proses lain. Muat ulang halaman lalu coba kembali.');
            }
            if (!$this->db->trans_status()) {
                throw new RuntimeException('Gagal menyimpan perubahan status kontrak.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $this->refresh_document_verification($id);
        $message = 'Status kontrak berhasil diubah ke ' . $toStatus . '.';

        return ['ok' => true, 'message' => $message];
    }

    public function refresh_document_verification(int $contractId): ?array
    {
        $contractId = (int)$contractId;
        if ($contractId <= 0) {
            return null;
        }

        $row = $this->db->select('id, verification_token, final_document_hash, document_issued_at')
            ->from('hr_contract')
            ->where('id', $contractId)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            return null;
        }

        $token = (string)($row['verification_token'] ?? '');
        if ($token === '') {
            $token = $this->ensure_unique_verification_token($contractId);
        }

        $issuedAt = !empty($row['document_issued_at']) ? (string)$row['document_issued_at'] : date('Y-m-d H:i:s');

        $this->db->where('id', $contractId)->update('hr_contract', [
            'verification_token' => $token,
            'document_issued_at' => $issuedAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $hash = $this->compute_document_hash($contractId);
        if ($hash === null) {
            return null;
        }

        $this->db->where('id', $contractId)->update('hr_contract', [
            'final_document_hash' => $hash,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'verification_token' => $token,
            'final_document_hash' => $hash,
            'document_issued_at' => $issuedAt,
        ];
    }

    private function compute_document_hash(int $contractId): ?string
    {
        $row = $this->get_contract_detail($contractId);
        if (!$row) {
            return null;
        }

        $payload = [
            'contract_number' => (string)($row['contract_number'] ?? ''),
            'employee_code' => (string)($row['employee_code'] ?? ''),
            'employee_name' => (string)($row['employee_name'] ?? ''),
            'contract_type' => (string)($row['contract_type'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'start_date' => (string)($row['start_date'] ?? ''),
            'end_date' => (string)($row['end_date'] ?? ''),
            'body_html' => $this->sanitize_contract_body((string)($row['body_html_rendered'] ?? $row['body_html'] ?? '')),
            'comp' => [
                'basic_salary' => round((float)($row['basic_salary'] ?? 0), 2),
                'position_allowance' => round((float)($row['position_allowance'] ?? 0), 2),
                'other_allowance' => round((float)($row['other_allowance'] ?? 0), 2),
                'meal_rate' => round((float)($row['meal_rate'] ?? 0), 2),
                'overtime_rate' => round((float)($row['overtime_rate'] ?? 0), 2),
            ],
            'approvals' => array_map(static function ($a) {
                return [
                    'approver_role' => (string)($a['approver_role'] ?? ''),
                    'approval_status' => (string)($a['approval_status'] ?? ''),
                    'approver_name' => (string)($a['approver_name'] ?? ''),
                    'approved_at' => (string)($a['approved_at'] ?? ''),
                ];
            }, (array)($row['approvals'] ?? [])),
            'signatures' => array_map(static function ($s) {
                return [
                    'signer_role' => (string)($s['signer_role'] ?? ''),
                    'signer_name' => (string)($s['signer_name'] ?? ''),
                    'signed_at' => (string)($s['signed_at'] ?? ''),
                    'signature_hash' => hash('sha256', (string)($s['signature_data'] ?? '')),
                ];
            }, (array)($row['signatures'] ?? [])),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return null;
        }

        return hash('sha256', $json);
    }

    private function has_complete_signoff(int $contractId): bool
    {
        $approved = $this->db->select('COUNT(DISTINCT approver_role) AS cnt', false)
            ->from('hr_contract_approval')
            ->where('contract_id', $contractId)
            ->where('approval_status', 'APPROVED')
            ->where_in('approver_role', ['EMPLOYEE', 'COMPANY'])
            ->get()->row_array();

        $signed = $this->db->select('COUNT(DISTINCT signer_role) AS cnt', false)
            ->from('hr_contract_signature')
            ->where('contract_id', $contractId)
            ->where_in('signer_role', ['EMPLOYEE', 'COMPANY'])
            ->get()->row_array();

        return ((int)($approved['cnt'] ?? 0) >= 2) && ((int)($signed['cnt'] ?? 0) >= 2);
    }

    private function sync_signed_status(int $contractId): void
    {
        $contract = $this->db->select('id, status, start_date, end_date')
            ->from('hr_contract')
            ->where('id', $contractId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$contract) {
            return;
        }

        $status = strtoupper((string)$contract['status']);
        if (!in_array($status, ['GENERATED', 'SIGNED'], true)) {
            return;
        }

        if ($this->has_complete_signoff($contractId)) {
            if ($status === 'GENERATED') {
                $this->db->where('id', $contractId)->update('hr_contract', [
                    'status' => 'SIGNED',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $status = 'SIGNED';
            }

            $today = date('Y-m-d');
            if ($status === 'SIGNED'
                && (string)($contract['start_date'] ?? '') <= $today
                && (string)($contract['end_date'] ?? '') >= $today) {
                $this->transition_status($contractId, 'ACTIVE');
            }
        }
    }

    public function sanitize_contract_body(string $html): string
    {
        return trim($html);
    }

    private function render_contract_html(string $template, array $vars): string
    {
        if ($template === '') {
            return '';
        }

        $issuedAt = trim((string)($vars['document_issued_at'] ?? $vars['issued_at'] ?? ''));
        $startDate = trim((string)($vars['start_date'] ?? ''));
        $endDate = trim((string)($vars['end_date'] ?? ''));
        $formatMoney = static function ($value, int $decimals = 0): string {
            return 'Rp ' . number_format((float)$value, $decimals, ',', '.');
        };

        $replace = [
            '{{CONTRACT_NUMBER}}' => (string)($vars['contract_number'] ?? ''),
            '{{CONTRACT_TYPE}}' => (string)($vars['contract_type'] ?? ''),
            '{{EMPLOYEE_CODE}}' => (string)($vars['employee_code'] ?? ''),
            '{{EMPLOYEE_NAME}}' => (string)($vars['employee_name'] ?? ''),
            '{{DIVISION_NAME}}' => (string)($vars['division_name'] ?? ''),
            '{{POSITION_NAME}}' => (string)($vars['position_name'] ?? ''),
            '{{NAMA_PEGAWAI}}' => (string)($vars['employee_name'] ?? ''),
            '{{JABATAN}}' => (string)($vars['position_name'] ?? ''),
            '{{DIVISI}}' => (string)($vars['division_name'] ?? ''),
            '{{OUTLET}}' => (string)($vars['outlet_name'] ?? $vars['division_name'] ?? ''),
            '{{KONTRAK_SEBELUMNYA}}' => (string)($vars['previous_contract_number'] ?? ''),
            '{{TANGGAL_KONTRAK}}' => $issuedAt !== '' ? $this->format_contract_date_label($issuedAt) : (string)($vars['start_date'] ?? ''),
            '{{DURASI_KONTRAK}}' => (string)($vars['contract_duration_label'] ?? ''),
            '{{PERIODE_KONTRAK}}' => trim(($startDate !== '' ? $startDate : '') . ($startDate !== '' || $endDate !== '' ? ' s/d ' : '') . ($endDate !== '' ? $endDate : '')),
            '{{TANGGAL_MULAI}}' => $startDate !== '' ? $this->format_contract_date_label($startDate) : '',
            '{{TANGGAL_AKHIR}}' => $endDate !== '' ? $this->format_contract_date_label($endDate) : '',
            '{{GAJI_POKOK_DASAR}}' => $formatMoney($vars['basic_salary'] ?? 0),
            '{{TUNJANGAN_JABATAN}}' => $formatMoney($vars['position_allowance'] ?? 0),
            '{{TUNJANGAN_OBJEKTIF}}' => $formatMoney($vars['other_allowance'] ?? 0),
            '{{UANG_MAKAN}}' => $formatMoney($vars['meal_rate'] ?? 0),
            '{{TARIF_LEMBUR}}' => (string)($vars['overtime_policy_label'] ?? 'Master Standar Lembur yang berlaku'),
            '{{TOTAL_KOMPENSASI_TETAP}}' => $formatMoney($vars['fixed_total'] ?? 0),
            '{{START_DATE}}' => (string)($vars['start_date'] ?? ''),
            '{{END_DATE}}' => (string)($vars['end_date'] ?? ''),
            '{{BASIC_SALARY}}' => number_format((float)($vars['basic_salary'] ?? 0), 2, '.', ''),
            '{{POSITION_ALLOWANCE}}' => number_format((float)($vars['position_allowance'] ?? 0), 2, '.', ''),
            '{{OTHER_ALLOWANCE}}' => number_format((float)($vars['other_allowance'] ?? 0), 2, '.', ''),
            '{{MEAL_RATE}}' => number_format((float)($vars['meal_rate'] ?? 0), 2, '.', ''),
            '{{OVERTIME_RATE}}' => (string)($vars['overtime_policy_label'] ?? 'Master Standar Lembur yang berlaku'),
            '{{FIXED_TOTAL}}' => number_format((float)($vars['fixed_total'] ?? 0), 2, '.', ''),
        ];

        return strtr($template, $replace);
    }

    private function build_contract_render_vars(array $row, array $overrides = []): array
    {
        $startDate = trim((string)($overrides['start_date'] ?? $row['start_date'] ?? ''));
        $endDate = trim((string)($overrides['end_date'] ?? $row['end_date'] ?? ''));
        $issuedAt = trim((string)($overrides['document_issued_at'] ?? $row['document_issued_at'] ?? $row['generated_at'] ?? $row['updated_at'] ?? ''));
        if ($issuedAt === '') {
            $issuedAt = date('Y-m-d H:i:s');
        }

        $contractType = strtoupper(trim((string)($overrides['contract_type'] ?? $row['contract_type'] ?? 'CUSTOM')));
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $employeeName = (string)($overrides['employee_name'] ?? $row['employee_name'] ?? '');
        $divisionName = trim((string)($overrides['division_name'] ?? $row['division_snapshot'] ?? $row['division_name'] ?? ''));
        $positionName = trim((string)($overrides['position_name'] ?? $row['position_snapshot'] ?? $row['position_name'] ?? ''));
        $outletName = trim((string)($overrides['outlet_name'] ?? $row['outlet_snapshot'] ?? ''));
        if ($outletName === '') {
            $outletName = $divisionName !== '' ? $divisionName : '-';
        }

        $previousContractNumber = trim((string)($row['previous_contract_number'] ?? ''));
        if ($previousContractNumber === '' && !empty($row['previous_contract_id'])) {
            $prev = $this->db->select('contract_number')
                ->from('hr_contract')
                ->where('id', (int)$row['previous_contract_id'])
                ->limit(1)
                ->get()->row_array();
            if ($prev) {
                $previousContractNumber = (string)($prev['contract_number'] ?? '');
            }
        }
        if ($previousContractNumber === '' && !empty($row['employee_id'])) {
            $qb = $this->db->select('contract_number, id, start_date')
                ->from('hr_contract')
                ->where('employee_id', (int)$row['employee_id']);
            if (!empty($row['id'])) {
                $qb->where('id <>', (int)$row['id']);
            }
            if ($startDate !== '') {
                $qb->where('start_date <', $startDate);
            }
            $prev = $qb->order_by('start_date', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()->row_array();
            if ($prev) {
                $previousContractNumber = (string)($prev['contract_number'] ?? '');
            }
        }

        $durationMonths = (int)($overrides['duration_months'] ?? $row['template_duration_months'] ?? 0);
        if ($durationMonths <= 0) {
            $durationMonths = $this->estimate_contract_months($startDate, $endDate);
        }

        $durationLabel = $durationMonths > 0 ? $durationMonths . ' bulan' : '-';
        if ($durationLabel === '-' && $startDate !== '' && $endDate !== '') {
            $durationLabel = trim($startDate . ' s/d ' . $endDate);
        }

        // Once generated, the immutable contract snapshot is the document's
        // compensation source. Draft previews use the contract draft values.
        $snapshot = is_array($row['snapshot'] ?? null) ? $row['snapshot'] : [];
        $basicSalary = (float)($overrides['basic_salary'] ?? $snapshot['basic_salary_amount'] ?? $row['basic_salary'] ?? 0);
        $positionAllowance = (float)($overrides['position_allowance'] ?? $snapshot['position_allowance_amount'] ?? $row['position_allowance'] ?? 0);
        $otherAllowance = (float)($overrides['other_allowance'] ?? $snapshot['other_allowance_amount'] ?? $row['other_allowance'] ?? 0);
        $mealRate = (float)($overrides['meal_rate'] ?? $snapshot['meal_rate_amount'] ?? $row['meal_rate'] ?? 0);

        $vars = [
            'contract_number' => (string)($overrides['contract_number'] ?? $row['contract_number'] ?? ''),
            'contract_type' => $contractType,
            'employee_code' => (string)($overrides['employee_code'] ?? $row['employee_code'] ?? ''),
            'employee_name' => $employeeName,
            'division_name' => $divisionName,
            'position_name' => $positionName,
            'outlet_name' => $outletName,
            'previous_contract_number' => $previousContractNumber !== '' ? $previousContractNumber : '-',
            'document_issued_at' => $issuedAt,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'contract_duration_label' => $durationLabel,
            'basic_salary' => $basicSalary,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'overtime_rate' => 0.0,
            'overtime_policy_label' => 'Master Standar Lembur yang berlaku',
        ];
        $vars['fixed_total'] = $basicSalary + $positionAllowance + $otherAllowance;

        $vars['nama_pegawai'] = $employeeName;
        $vars['jabatan'] = $positionName;
        $vars['divisi'] = $divisionName;
        $vars['outlet'] = $outletName;
        $vars['tanggal_kontrak'] = $this->format_contract_date_label($issuedAt);
        $vars['durasi_kontrak'] = $durationLabel;
        $vars['kontrak_sebelumnya'] = $vars['previous_contract_number'];
        $vars['periode_kontrak'] = trim(($startDate !== '' ? $startDate : '-') . ' s/d ' . ($endDate !== '' ? $endDate : '-'));

        return $vars;
    }

    private function build_rendered_contract_body(array $row): string
    {
        $source = trim((string)($row['body_html'] ?? ''));
        if ($source === '') {
            $source = trim((string)($row['template_body_html'] ?? ''));
        }

        if ($source === '') {
            return '';
        }

        $vars = $row['render_vars'] ?? $this->build_contract_render_vars($row);
        return $this->sanitize_contract_body($this->render_contract_html($source, (array)$vars));
    }

    private function format_contract_date_label(string $dateTime): string
    {
        $dateTime = trim($dateTime);
        if ($dateTime === '') {
            return '-';
        }

        $ts = strtotime($dateTime);
        if ($ts === false) {
            return $dateTime;
        }

        return date('d/m/Y', $ts);
    }

    private function estimate_contract_months(string $startDate, string $endDate): int
    {
        if ($startDate === '' || $endDate === '') {
            return 0;
        }

        try {
            $start = new DateTimeImmutable($startDate);
            $end = new DateTimeImmutable($endDate);
        } catch (Throwable $e) {
            return 0;
        }

        if ($end <= $start) {
            return 0;
        }

        $years = (int)$start->diff($end)->y;
        $months = (int)$start->diff($end)->m;
        $days = (int)$start->diff($end)->d;
        $totalMonths = ($years * 12) + $months;
        if ($days > 0) {
            $totalMonths++;
        }

        return max(1, $totalMonths);
    }

    private function add_months(string $startDate, int $months): string
    {
        $ts = strtotime($startDate);
        if ($ts === false) {
            $ts = time();
        }

        return date('Y-m-d', strtotime('+' . max(1, $months) . ' months', $ts));
    }

    private function ensure_unique_verification_token(int $excludeId = 0): string
    {
        for ($i = 0; $i < 10; $i++) {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $token = sha1(uniqid('ctr', true) . microtime(true));
            }
            $this->db->from('hr_contract')->where('verification_token', $token);
            if ($excludeId > 0) {
                $this->db->where('id !=', $excludeId);
            }
            if ($this->db->count_all_results() === 0) {
                return $token;
            }
        }

        return sha1(uniqid('ctr', true) . microtime(true));
    }

    private function generate_contract_number(string $contractType, string $startDate, int $excludeId = 0): string
    {
        $period = date('Ym', strtotime($startDate ?: date('Y-m-d')));
        $typeCode = strtoupper(trim($contractType));
        if (!in_array($typeCode, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $typeCode = 'CUSTOM';
        }

        $prefix = 'CTR/' . $typeCode . '/' . $period . '/';

        $rows = $this->db->select('contract_number')
            ->from('hr_contract')
            ->like('contract_number', $prefix, 'after')
            ->get()->result_array();

        $maxSeq = 0;
        foreach ($rows as $r) {
            $no = (string)($r['contract_number'] ?? '');
            if (preg_match('/(\d+)$/', $no, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $next = $maxSeq + 1;
        while ($next <= 999999) {
            $candidate = $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
            $this->db->from('hr_contract')->where('contract_number', $candidate);
            if ($excludeId > 0) {
                $this->db->where('id !=', $excludeId);
            }
            $exists = $this->db->count_all_results() > 0;
            if (!$exists) {
                return $candidate;
            }
            $next++;
        }

        return $prefix . date('His');
    }
}
