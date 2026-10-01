<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * One resolver for employee compensation.
 *
 * Contracts are the sole source of compensation. att_daily stores the
 * resolved contract snapshot used on each attendance date so historical
 * payroll remains reproducible after a contract changes.
 */
class Compensation_model extends CI_Model
{
    private $fieldCache = [];
    private $resolutionCache = [];

    private function table_has_field(string $table, string $field): bool
    {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $this->fieldCache)) {
            $this->fieldCache[$key] = $this->db->field_exists($field, $table);
        }
        return (bool)$this->fieldCache[$key];
    }

    private function valid_date(string $date): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    private function empty_resolution(string $source = 'MISSING'): array
    {
        return [
            'source' => $source,
            'contract_id' => null,
            'snapshot_id' => null,
            'contract_number' => '',
            'basic_salary' => 0.0,
            'position_allowance' => 0.0,
            'objective_allowance' => 0.0,
            'meal_rate' => 0.0,
            'overtime_rate' => 0.0,
            'fixed_total' => 0.0,
        ];
    }

    private function normalize_contract_row(array $row): array
    {
        $basic = (float)($row['basic_salary_amount'] ?? $row['basic_salary'] ?? 0);
        $position = (float)($row['position_allowance_amount'] ?? $row['position_allowance'] ?? 0);
        $objective = (float)($row['other_allowance_amount'] ?? $row['other_allowance'] ?? 0);
        $meal = (float)($row['meal_rate_amount'] ?? $row['meal_rate'] ?? 0);

        return [
            'source' => 'CONTRACT',
            'contract_id' => (int)($row['id'] ?? $row['contract_id'] ?? 0) ?: null,
            'snapshot_id' => (int)($row['snapshot_id'] ?? 0) ?: null,
            'contract_number' => (string)($row['contract_number'] ?? ''),
            'basic_salary' => round($basic, 2),
            'position_allowance' => round($position, 2),
            'objective_allowance' => round($objective, 2),
            'meal_rate' => round($meal, 2),
            // Overtime is not an employee compensation component. Its rate is
            // resolved from Master Standar Lembur through attendance policy.
            'overtime_rate' => 0.0,
            'fixed_total' => round($basic + $position + $objective, 2),
        ];
    }

    /**
     * Resolve one immutable contract snapshot valid for a date. The caller
     * chooses whether it needs a live contract or may inspect a finalized
     * historical contract for a past attendance correction.
     */
    private function resolve_contract_for_date(int $employeeId, string $effectiveDate, array $statuses): ?array
    {
        if (
            $employeeId <= 0
            || !$this->valid_date($effectiveDate)
            || empty($statuses)
            || !$this->db->table_exists('hr_contract')
            || !$this->db->table_exists('hr_contract_comp_snapshot')
        ) {
            return null;
        }

        $row = $this->db->select('c.id, c.contract_number, c.basic_salary, c.position_allowance, c.other_allowance, c.meal_rate,
                s.id AS snapshot_id, s.basic_salary_amount, s.position_allowance_amount, s.other_allowance_amount, s.meal_rate_amount', false)
            ->from('hr_contract c')
            // A live contract without an immutable snapshot is incomplete and
            // must not become a new payroll source.
            ->join('hr_contract_comp_snapshot s', 's.contract_id = c.id', 'inner')
            ->where('c.employee_id', $employeeId)
            ->where_in('c.status', array_values(array_unique(array_map('strtoupper', $statuses))))
            ->where('c.start_date <=', $effectiveDate)
            ->group_start()
                ->where('c.end_date IS NULL', null, false)
                ->or_where('c.end_date >=', $effectiveDate)
            ->group_end()
            ->order_by('c.start_date', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        return $row ? $this->normalize_contract_row($row) : null;
    }

    /** Resolve the active contract snapshot that is valid on a date. */
    public function resolve_for_employee(int $employeeId, string $effectiveDate = ''): array
    {
        if ($employeeId <= 0) {
            return $this->empty_resolution();
        }
        if ($effectiveDate === '') {
            $effectiveDate = date('Y-m-d');
        }
        if (!$this->valid_date($effectiveDate)) {
            return $this->empty_resolution();
        }

        $cacheKey = implode('|', [$employeeId, $effectiveDate, 'ACTIVE']);
        if (isset($this->resolutionCache[$cacheKey])) {
            return $this->resolutionCache[$cacheKey];
        }

        $resolved = $this->resolve_contract_for_date($employeeId, $effectiveDate, ['ACTIVE'])
            ?: $this->empty_resolution();

        $this->resolutionCache[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * Used only when correcting a date in the past. EXPIRED and TERMINATED
     * contracts remain valid historical evidence inside their own date range;
     * they never qualify as the source for today's attendance.
     */
    public function resolve_finalized_contract_for_employee(int $employeeId, string $effectiveDate): array
    {
        if ($employeeId <= 0 || !$this->valid_date($effectiveDate)) {
            return $this->empty_resolution();
        }

        $cacheKey = implode('|', [$employeeId, $effectiveDate, 'FINALIZED']);
        if (isset($this->resolutionCache[$cacheKey])) {
            return $this->resolutionCache[$cacheKey];
        }

        $resolved = $this->resolve_contract_for_date(
            $employeeId,
            $effectiveDate,
            ['ACTIVE', 'EXPIRED', 'TERMINATED']
        ) ?: $this->empty_resolution();

        $this->resolutionCache[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * Default for drafting a contract. Prefer the contract that covers the
     * new start date; for a renewal just after expiry, copy the most recent
     * finalized contract snapshot instead of falling back to org_employee.
     */
    public function resolve_contract_default_for_draft(int $employeeId, string $startDate): array
    {
        if ($employeeId <= 0 || !$this->valid_date($startDate)) {
            return $this->empty_resolution();
        }

        $active = $this->resolve_for_employee($employeeId, $startDate);
        if (strtoupper((string)($active['source'] ?? '')) === 'CONTRACT') {
            return $active;
        }

        $historical = $this->resolve_finalized_contract_for_employee($employeeId, $startDate);
        if (strtoupper((string)($historical['source'] ?? '')) === 'CONTRACT') {
            return $historical;
        }

        $cacheKey = implode('|', [$employeeId, $startDate, 'DRAFT_DEFAULT']);
        if (isset($this->resolutionCache[$cacheKey])) {
            return $this->resolutionCache[$cacheKey];
        }

        $row = null;
        if ($this->db->table_exists('hr_contract') && $this->db->table_exists('hr_contract_comp_snapshot')) {
            $row = $this->db->select('c.id, c.contract_number, c.basic_salary, c.position_allowance, c.other_allowance, c.meal_rate,
                    s.id AS snapshot_id, s.basic_salary_amount, s.position_allowance_amount, s.other_allowance_amount, s.meal_rate_amount', false)
                ->from('hr_contract c')
                ->join('hr_contract_comp_snapshot s', 's.contract_id = c.id', 'inner')
                ->where('c.employee_id', $employeeId)
                ->where_in('c.status', ['ACTIVE', 'EXPIRED', 'TERMINATED'])
                ->where('c.end_date <', $startDate)
                ->order_by('c.end_date', 'DESC')
                ->order_by('c.id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
        }

        $resolved = $row ? $this->normalize_contract_row($row) : $this->empty_resolution();
        $this->resolutionCache[$cacheKey] = $resolved;
        return $resolved;
    }

    public function resolve_for_employees(array $employeeIds, string $effectiveDate = ''): array
    {
        $map = [];
        foreach (array_values(array_unique(array_map('intval', $employeeIds))) as $employeeId) {
            if ($employeeId <= 0) {
                continue;
            }
            $map[$employeeId] = $this->resolve_for_employee($employeeId, $effectiveDate);
        }
        return $map;
    }

    /** Build optional provenance fields for att_daily after the schema migration. */
    public function build_att_daily_provenance(array $resolved): array
    {
        $payload = [];
        if ($this->table_has_field('att_daily', 'compensation_contract_id')) {
            $payload['compensation_contract_id'] = !empty($resolved['contract_id']) ? (int)$resolved['contract_id'] : null;
        }
        if ($this->table_has_field('att_daily', 'compensation_snapshot_id')) {
            $payload['compensation_snapshot_id'] = !empty($resolved['snapshot_id']) ? (int)$resolved['snapshot_id'] : null;
        }
        if ($this->table_has_field('att_daily', 'compensation_source')) {
            $payload['compensation_source'] = substr((string)($resolved['source'] ?? 'MISSING'), 0, 30);
        }
        if ($this->table_has_field('att_daily', 'compensation_resolved_at')) {
            $payload['compensation_resolved_at'] = date('Y-m-d H:i:s');
        }
        return $payload;
    }

    public function find_active_overlaps(int $employeeId, string $startDate, string $endDate, int $excludeContractId = 0): array
    {
        if ($employeeId <= 0 || !$this->valid_date($startDate) || !$this->valid_date($endDate) || $endDate < $startDate) {
            return [];
        }

        $query = $this->db->select('id, contract_number, start_date, end_date, status')
            ->from('hr_contract')
            ->where('employee_id', $employeeId)
            ->where('status', 'ACTIVE')
            ->where('start_date <=', $endDate)
            ->where('end_date >=', $startDate);
        if ($excludeContractId > 0) {
            $query->where('id !=', $excludeContractId);
        }
        return $query->order_by('start_date', 'ASC')->get()->result_array();
    }

}
