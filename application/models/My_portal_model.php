<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class My_portal_model extends CI_Model
{
    private $attDailyFieldCache = [];

    private function att_daily_has_field(string $field): bool
    {
        if (!array_key_exists($field, $this->attDailyFieldCache)) {
            $this->attDailyFieldCache[$field] = $this->db->field_exists($field, 'att_daily');
        }
        return (bool)$this->attDailyFieldCache[$field];
    }

    private function build_daily_policy_lock_payload(array $policy): array
    {
        $payload = [];
        $set = function (string $field, $value) use (&$payload): void {
            if ($this->att_daily_has_field($field)) {
                $payload[$field] = $value;
            }
        };

        $set('policy_snapshot_id', (int)($policy['id'] ?? 0) ?: null);
        $set('policy_snapshot_code', (string)($policy['policy_code'] ?? ''));
        $set('policy_snapshot_name', (string)($policy['policy_name'] ?? ''));
        $set('attendance_mode_snapshot', strtoupper((string)($policy['attendance_calc_mode'] ?? 'DAILY')));
        $set('meal_mode_snapshot', strtoupper((string)($policy['meal_calc_mode'] ?? 'MONTHLY')));
        $set('prorate_scope_snapshot', strtoupper((string)($policy['prorate_deduction_scope'] ?? ($policy['payroll_late_deduction_scope'] ?? 'BASIC_ONLY'))));
        $set('overtime_mode_snapshot', strtoupper((string)($policy['overtime_calc_mode'] ?? 'AUTO')));
        $set('allowance_late_treatment_snapshot', strtoupper((string)($policy['allowance_late_treatment'] ?? 'FULL_IF_PRESENT')));
        $set('enable_late_deduction_snapshot', (int)($policy['enable_late_deduction'] ?? 1));
        $set('enable_alpha_deduction_snapshot', (int)($policy['enable_alpha_deduction'] ?? 1));
        $set('late_deduction_per_minute_snapshot', round((float)($policy['late_deduction_per_minute'] ?? 0), 2));
        $set('alpha_deduction_per_day_snapshot', round((float)($policy['alpha_deduction_per_day'] ?? 0), 2));
        $set('work_days_snapshot', (int)($policy['default_work_days_per_month'] ?? 26));

        return $payload;
    }

    private function policy_overtime_rate(array $policy): float
    {
        $this->load->model('Attendance_model');
        return $this->Attendance_model->get_policy_overtime_rate($policy);
    }

    private function resolve_daily_compensation(int $employeeId, string $attendanceDate, array $dailyRow): array
    {
        $hasSnapshot = $this->att_daily_has_field('snapshot_basic_salary')
            && array_key_exists('snapshot_basic_salary', $dailyRow)
            && $dailyRow['snapshot_basic_salary'] !== null;

        if ($hasSnapshot) {
            return [
                'has_snapshot' => true,
                'resolved' => [
                    'source' => (string)($dailyRow['compensation_source'] ?? 'LEGACY_SNAPSHOT') ?: 'LEGACY_SNAPSHOT',
                    'contract_id' => !empty($dailyRow['compensation_contract_id']) ? (int)$dailyRow['compensation_contract_id'] : null,
                    'snapshot_id' => !empty($dailyRow['compensation_snapshot_id']) ? (int)$dailyRow['compensation_snapshot_id'] : null,
                    'basic_salary' => (float)($dailyRow['snapshot_basic_salary'] ?? 0),
                    'position_allowance' => (float)($dailyRow['snapshot_position_allowance'] ?? 0),
                    'objective_allowance' => (float)($dailyRow['snapshot_objective_allowance'] ?? 0),
                    'meal_rate' => (float)($dailyRow['snapshot_meal_rate'] ?? 0),
                    'overtime_rate' => (float)($dailyRow['snapshot_overtime_rate'] ?? 0),
                ],
            ];
        }

        $this->load->model('Compensation_model');
        $resolved = $this->Compensation_model->resolve_for_employee($employeeId, $attendanceDate);
        if (strtoupper((string)($resolved['source'] ?? '')) !== 'CONTRACT' && $attendanceDate < date('Y-m-d')) {
            $resolved = $this->Compensation_model->resolve_finalized_contract_for_employee($employeeId, $attendanceDate);
        }
        return [
            'has_snapshot' => false,
            'resolved' => $resolved,
        ];
    }

    private function daily_compensation_provenance_payload(array $dailyRow, array $context): array
    {
        $hasStoredSource = $this->att_daily_has_field('compensation_source')
            && trim((string)($dailyRow['compensation_source'] ?? '')) !== '';
        if (!empty($context['has_snapshot']) && $hasStoredSource) {
            return [];
        }

        $this->load->model('Compensation_model');
        return $this->Compensation_model->build_att_daily_provenance((array)($context['resolved'] ?? []));
    }
    public function count_my_leave_requests(int $employeeId, array $filters): int
    {
        $this->build_my_leave_requests_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_leave_requests(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_leave_requests_query($employeeId, $filters, true);
        return $this->db->order_by('pr.request_date', 'DESC')
            ->order_by('pr.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_leave_requests_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('pr.*');
            if ($this->db->table_exists('att_pending_request_approval')) {
                $this->db->select("
                    (SELECT GROUP_CONCAT(CONCAT('L', pa.approval_level, ':', pa.action, ' @', DATE_FORMAT(pa.acted_at, '%Y-%m-%d %H:%i')) ORDER BY pa.approval_level SEPARATOR ' | ')
                     FROM att_pending_request_approval pa
                     WHERE pa.pending_request_id = pr.id) AS approval_timeline
                ", false);
            }
        }

        $this->db->from('att_pending_request pr')
            ->where('pr.employee_id', $employeeId);

        if (!empty($filters['date_start'])) {
            $this->db->where('pr.request_date >=', $filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('pr.request_date <=', $filters['date_end']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('pr.status', $filters['status']);
        }
        if (!empty($filters['request_type'])) {
            $this->db->where('pr.request_type', $filters['request_type']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('pr.reason', $q)
                ->or_like('pr.approval_notes', $q)
                ->group_end();
        }
    }

    public function create_leave_request(int $employeeId, array $payload): array
    {
        if ($employeeId <= 0) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid.'];
        }

        $policy = $this->get_active_policy();
        if (!$this->can_submit_pending_request($employeeId, $policy)) {
            return ['ok' => false, 'message' => 'Anda tidak diizinkan mengajukan absen berdasarkan pengaturan policy saat ini.'];
        }

        $requestType = strtoupper(trim((string)($payload['request_type'] ?? '')));
        $requestDate = trim((string)($payload['request_date'] ?? ''));
        $reason = trim((string)($payload['reason'] ?? ''));

        if (!in_array($requestType, ['LEAVE', 'SICK', 'MISSING_CHECKIN', 'MISSING_CHECKOUT', 'STATUS_CORRECTION'], true)) {
            return ['ok' => false, 'message' => 'Jenis pengajuan tidak valid.'];
        }
        if ($requestDate === '') {
            return ['ok' => false, 'message' => 'Tanggal pengajuan wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate) || strtotime($requestDate) === false) {
            return ['ok' => false, 'message' => 'Tanggal pengajuan tidak valid.'];
        }
        if ($reason === '') {
            return ['ok' => false, 'message' => 'Alasan pengajuan wajib diisi.'];
        }

        $allowPastRevisionOverride = !empty($payload['allow_past_revision_override']);
        $revisionRule = $this->attendance_revision_window_rule();
        if (!$allowPastRevisionOverride && !empty($revisionRule['enabled'])) {
            $cutoffDate = date('Y-m-d', strtotime(date('Y-m-d') . ' -' . (int)$revisionRule['days'] . ' day'));
            if ($requestDate < $cutoffDate) {
                return [
                    'ok' => false,
                    'message' => $this->attendance_revision_notice_text($revisionRule),
                ];
            }
        }

        $requestedCheckinAt = null;
        $requestedCheckoutAt = null;
        $requestedStatus = null;
        if ($requestType === 'MISSING_CHECKIN') {
            $requestedCheckinAt = $this->normalize_datetime((string)($payload['requested_checkin_at'] ?? ''));
            if ($requestedCheckinAt === null) {
                return ['ok' => false, 'message' => 'Tanggal waktu check-in pengganti wajib diisi.'];
            }
        } elseif ($requestType === 'MISSING_CHECKOUT') {
            $requestedCheckoutAt = $this->normalize_datetime((string)($payload['requested_checkout_at'] ?? ''));
            if ($requestedCheckoutAt === null) {
                return ['ok' => false, 'message' => 'Tanggal waktu check-out pengganti wajib diisi.'];
            }
        } elseif ($requestType === 'STATUS_CORRECTION') {
            $requestedStatus = strtoupper(trim((string)($payload['requested_status'] ?? '')));
            if (!in_array($requestedStatus, ['PRESENT', 'LATE', 'ALPHA', 'SICK', 'LEAVE', 'OFF'], true)) {
                return ['ok' => false, 'message' => 'Status koreksi tidak valid.'];
            }
        }

        $dup = $this->db->select('id')
            ->from('att_pending_request')
            ->where('employee_id', $employeeId)
            ->where('request_date', $requestDate)
            ->where('request_type', $requestType)
            ->where('status', 'PENDING')
            ->limit(1)
            ->get()->row_array();
        if ($dup) {
            return ['ok' => false, 'message' => 'Pengajuan sejenis pada tanggal tersebut masih pending.'];
        }

        $this->db->insert('att_pending_request', [
            'employee_id' => $employeeId,
            'request_date' => $requestDate,
            'request_type' => $requestType,
            'requested_checkin_at' => $requestedCheckinAt,
            'requested_checkout_at' => $requestedCheckoutAt,
            'requested_status' => $requestedStatus,
            'reason' => $reason,
            'status' => 'PENDING',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => 'Pengajuan berhasil dibuat.'];
    }

    public function attendance_revision_window_rule(): array
    {
        $policy = $this->get_active_policy();
        $mode = strtoupper(trim((string)($policy['attendance_revision_window_mode'] ?? 'ON')));
        if (!in_array($mode, ['OFF', 'ON', 'BY_DAYS'], true)) {
            $mode = 'ON';
        }

        $customDays = max(1, (int)($policy['attendance_revision_window_days'] ?? 7));
        $effectiveDays = $mode === 'BY_DAYS' ? $customDays : 7;

        return [
            'mode' => $mode,
            'enabled' => $mode !== 'OFF',
            'days' => $effectiveDays,
            'custom_days' => $customDays,
        ];
    }

    public function attendance_revision_window_days(): int
    {
        return (int)($this->attendance_revision_window_rule()['days'] ?? 7);
    }

    public function attendance_revision_notice_text(?array $rule = null): string
    {
        $rule = is_array($rule) ? $rule : $this->attendance_revision_window_rule();
        if (empty($rule['enabled'])) {
            return 'Batas waktu pengajuan revisi absensi saat ini dinonaktifkan.';
        }

        $days = max(1, (int)($rule['days'] ?? 7));
        return 'Pengajuan revisi absensi hanya dapat diajukan paling lambat ' . $days . ' hari kalender sejak tanggal shift. Setelah melewati batas waktu tersebut, pengajuan tidak dapat diproses.';
    }

    private function can_submit_pending_request(int $employeeId, array $policy): bool
    {
        $scope = strtoupper((string)($policy['pending_request_scope'] ?? 'SELF_ONLY'));
        if ($scope === 'SELF_ONLY' || $scope === 'SELF_AND_POSITION') {
            return true;
        }
        if ($scope !== 'POSITION_ONLY') {
            return true;
        }

        $policyId = (int)($policy['id'] ?? 0);
        if ($policyId <= 0 || !$this->db->table_exists('att_pending_submitter_position')) {
            return false;
        }

        $employee = $this->db->select('position_id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->limit(1)
            ->get()->row_array();
        $positionId = (int)($employee['position_id'] ?? 0);
        if ($positionId <= 0) {
            return false;
        }

        $allowed = $this->db->select('id')
            ->from('att_pending_submitter_position')
            ->where('policy_id', $policyId)
            ->where('position_id', $positionId)
            ->limit(1)
            ->get()->row_array();
        return !empty($allowed);
    }

    private function normalize_datetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        if (strlen($value) === 16) {
            $value .= ':00';
        }
        $ts = strtotime($value);
        if (!$ts) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    public function get_employee_by_id(int $employeeId): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $row = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->where('e.is_active', 1)
            ->get()->row_array();

        return $row ?: null;
    }

    public function get_employee_options(): array
    {
        return $this->db->select('id AS value, CONCAT(employee_code, \' - \', employee_name) AS label', false)
            ->from('org_employee')
            ->where('is_active', 1)
            ->order_by('employee_name', 'ASC')
            ->get()->result_array();
    }

    public function list_my_contracts(int $employeeId): array
    {
        if ($employeeId <= 0 || !$this->db->table_exists('hr_contract')) {
            return [];
        }

        return $this->db->select('
                c.id,
                c.contract_number,
                c.contract_type,
                c.status,
                c.start_date,
                c.end_date,
                c.generated_at,
                c.document_issued_at,
                c.template_id,
                c.verification_token,
                t.template_name,
                t.template_code
            ')
            ->from('hr_contract c')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left')
            ->where('c.employee_id', $employeeId)
            ->order_by('COALESCE(c.start_date, "1900-01-01")', 'DESC', false)
            ->order_by('c.id', 'DESC')
            ->get()->result_array();
    }

    public function get_my_contract_detail(int $employeeId, int $contractId): ?array
    {
        if ($employeeId <= 0 || $contractId <= 0 || !$this->db->table_exists('hr_contract')) {
            return null;
        }

        $row = $this->db->select('id')
            ->from('hr_contract')
            ->where('id', $contractId)
            ->where('employee_id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            return null;
        }

        $detail = $this->db->select('c.*, e.employee_code, e.employee_name, e.employee_nip, e.email, e.mobile_phone, d.division_name, p.position_name, t.template_name, t.template_code, uc.username AS created_by_username, ug.username AS generated_by_username')
            ->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left')
            ->join('auth_user uc', 'uc.id = c.created_by', 'left')
            ->join('auth_user ug', 'ug.id = c.generated_by', 'left')
            ->where('c.id', $contractId)
            ->where('c.employee_id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        if (!$detail) {
            return null;
        }

        $detail['approvals'] = $this->db->from('hr_contract_approval')
            ->where('contract_id', $contractId)
            ->order_by('approver_role', 'ASC')
            ->get()->result_array();

        $detail['signatures'] = $this->db->select('id, contract_id, signer_role, signer_name, signer_user_id, signature_data, signed_at, ip_address')
            ->from('hr_contract_signature')
            ->where('contract_id', $contractId)
            ->order_by('signer_role', 'ASC')
            ->get()->result_array();

        $detail['snapshot'] = $this->db->from('hr_contract_comp_snapshot')
            ->where('contract_id', $contractId)
            ->limit(1)
            ->get()->row_array() ?: null;

        if (!empty($detail['snapshot']['id'])) {
            $detail['snapshot_lines'] = $this->db->from('hr_contract_comp_snapshot_line')
                ->where('snapshot_id', (int)$detail['snapshot']['id'])
                ->order_by('sort_order', 'ASC')
                ->get()->result_array();
        } else {
            $detail['snapshot_lines'] = [];
        }

        return $detail;
    }

    public function get_active_policy(): array
    {
        $row = $this->db->from('att_attendance_policy')
            ->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            $row = [];
        }

        // PH is paid compensatory leave, so its attendance is always created
        // automatically when the scheduled employee opens their attendance page.
        $row['ph_attendance_mode'] = 'AUTO_PRESENT';
        // Keep the portal aligned with the attendance engine. A PH grant only
        // comes from a regular shift worked on an active national holiday.
        $row['ph_grant_mode'] = 'HOLIDAY_ONLY';
        $row['ph_grant_holiday_type'] = 'NATIONAL';
        if (!isset($row['checkin_open_minutes_before'])) {
            $row['checkin_open_minutes_before'] = 30;
        }
        if (!isset($row['checkout_close_minutes_after'])) {
            $row['checkout_close_minutes_after'] = 180;
        }
        if (!isset($row['night_shift_checkout_credit_to_operation_end'])) {
            $row['night_shift_checkout_credit_to_operation_end'] = 1;
        }
        if (empty($row['overtime_calc_mode'])) {
            $row['overtime_calc_mode'] = 'AUTO';
        }
        if (empty($row['attendance_revision_window_mode']) || !in_array(strtoupper((string)$row['attendance_revision_window_mode']), ['OFF', 'ON', 'BY_DAYS'], true)) {
            $row['attendance_revision_window_mode'] = 'ON';
        }
        if (!isset($row['attendance_revision_window_days']) || (int)$row['attendance_revision_window_days'] <= 0) {
            $row['attendance_revision_window_days'] = 7;
        }

        return $row;
    }

    public function get_schedule_with_shift(int $employeeId, string $date): ?array
    {
        if ($employeeId <= 0 || $date === '') {
            return null;
        }

        $row = $this->db->select('ss.*, s.shift_code, s.shift_name, s.start_time, s.end_time, s.is_overnight, s.grace_late_minute')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date', $date)
            ->get()->row_array();

        return $row ?: null;
    }

    public function list_monthly_schedules(int $employeeId, string $dateStart, string $dateEnd): array
    {
        if ($employeeId <= 0 || $dateStart === '' || $dateEnd === '') {
            return [];
        }

        return $this->db->select('
                ss.schedule_date,
                ss.notes,
                s.shift_code,
                s.shift_name,
                s.start_time,
                s.end_time,
                COALESCE(s.is_overnight, 0) AS is_overnight
            ')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date >=', $dateStart)
            ->where('ss.schedule_date <=', $dateEnd)
            ->order_by('ss.schedule_date', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_today_presence_state(int $employeeId, string $date): array
    {
        $rows = $this->db->select('event_type, attendance_at')
            ->from('att_presence')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->order_by('attendance_at', 'ASC')
            ->get()->result_array();

        $state = [
            'checkin_count' => 0,
            'checkout_count' => 0,
            'last_checkin_at' => null,
            'last_checkout_at' => null,
        ];

        foreach ($rows as $row) {
            $event = strtoupper((string)($row['event_type'] ?? ''));
            if ($event === 'CHECKIN') {
                $state['checkin_count']++;
                $state['last_checkin_at'] = $row['attendance_at'];
            } elseif ($event === 'CHECKOUT') {
                $state['checkout_count']++;
                $state['last_checkout_at'] = $row['attendance_at'];
            }
        }

        return $state;
    }

    public function get_attendance_gap_alerts(int $employeeId, string $today, array $policy = []): array
    {
        if ($employeeId <= 0 || $today === '') {
            return [];
        }

        if (empty($policy)) {
            $policy = $this->get_active_policy();
        }

        $phAttendanceMode = strtoupper((string)($policy['ph_attendance_mode'] ?? 'AUTO_PRESENT'));
        $closeMinutes = (int)($policy['checkout_close_minutes_after'] ?? 180);
        if ($closeMinutes < 0) {
            $closeMinutes = 180;
        }

        $nowTs = time();
        $monthStart = date('Y-m-01', strtotime($today));
        $scheduleRows = $this->db->select('ss.schedule_date')
            ->from('att_shift_schedule ss')
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date >=', $monthStart)
            ->where('ss.schedule_date <=', $today)
            ->order_by('ss.schedule_date', 'DESC')
            ->get()
            ->result_array();

        $datesToCheck = [];
        foreach ($scheduleRows as $scheduleRow) {
            $scheduleDate = (string)($scheduleRow['schedule_date'] ?? '');
            if ($scheduleDate !== '') {
                $datesToCheck[] = $scheduleDate;
            }
        }

        if (empty($datesToCheck)) {
            return [];
        }

        $alerts = [];
        $seen = [];

        foreach ($datesToCheck as $date) {
            $schedule = $this->get_schedule_with_shift($employeeId, $date);
            if (!$schedule) {
                continue;
            }

            $shiftCode = strtoupper(trim((string)($schedule['shift_code'] ?? '')));
            if (in_array($shiftCode, ['PH', 'PHB'], true) && $phAttendanceMode === 'AUTO_PRESENT') {
                continue;
            }

            [$startTs, $endTs] = $this->shift_bounds(
                $date,
                (string)($schedule['start_time'] ?? ''),
                (string)($schedule['end_time'] ?? ''),
                (int)($schedule['is_overnight'] ?? 0)
            );
            if ($startTs <= 0 || $endTs <= 0) {
                continue;
            }

            $daily = $this->db->select('checkin_at, checkout_at, attendance_status')
                ->from('att_daily')
                ->where('employee_id', $employeeId)
                ->where('attendance_date', $date)
                ->limit(1)
                ->get()
                ->row_array();

            $checkinTs = !empty($daily['checkin_at']) ? strtotime((string)$daily['checkin_at']) : 0;
            $checkoutTs = !empty($daily['checkout_at']) ? strtotime((string)$daily['checkout_at']) : 0;
            $shiftLabel = trim((string)(($schedule['shift_code'] ?? '') . ' - ' . ($schedule['shift_name'] ?? '')));
            if ($shiftLabel === '-' || $shiftLabel === '') {
                $shiftLabel = trim((string)($schedule['shift_code'] ?? 'Tanpa shift'));
            }
            $timeLabel = trim((string)(
                (trim((string)($schedule['start_time'] ?? '')) !== '' ? substr((string)$schedule['start_time'], 0, 5) : '-')
                . ' - ' .
                (trim((string)($schedule['end_time'] ?? '')) !== '' ? substr((string)$schedule['end_time'], 0, 5) : '-')
            ));

            if ($nowTs > $startTs && $checkinTs <= 0) {
                $key = 'CHECKIN|' . $date;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $alerts[] = [
                        'type' => 'MISSING_CHECKIN',
                        'severity' => ($date < $today || $nowTs > $endTs) ? 'danger' : 'warning',
                        'date' => $date,
                        'title' => 'Absen masuk belum tercatat',
                        'message' => 'Jadwal shift ' . $shiftLabel . ' (' . $timeLabel . ') pada ' . date('d-m-Y', strtotime($date)) . ' sudah berjalan, tetapi check-in belum ada.',
                    ];
                }
                continue;
            }

            if ($checkinTs > 0 && $checkoutTs <= 0 && $nowTs > $endTs) {
                $key = 'CHECKOUT|' . $date;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $alerts[] = [
                        'type' => 'MISSING_CHECKOUT',
                        'severity' => ($date < $today || $nowTs > ($endTs + ($closeMinutes * 60))) ? 'danger' : 'warning',
                        'date' => $date,
                        'title' => 'Absen pulang belum tercatat',
                        'message' => 'Anda sudah check-in untuk shift ' . $shiftLabel . ' (' . $timeLabel . ') pada ' . date('d-m-Y', strtotime($date)) . ', tetapi check-out belum ada.',
                    ];
                }
            }
        }

        usort($alerts, static function (array $left, array $right): int {
            return strcmp((string)($right['date'] ?? ''), (string)($left['date'] ?? ''));
        });

        return $alerts;
    }

    private function get_presence_events_by_type(int $employeeId, string $date): array
    {
        $rows = $this->db->select('event_type, attendance_at')
            ->from('att_presence')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->order_by('attendance_at', 'ASC')
            ->get()->result_array();

        $events = [
            'CHECKIN' => [],
            'CHECKOUT' => [],
        ];

        foreach ($rows as $row) {
            $eventType = strtoupper((string)($row['event_type'] ?? ''));
            $ts = strtotime((string)($row['attendance_at'] ?? ''));
            if (!in_array($eventType, ['CHECKIN', 'CHECKOUT'], true) || $ts <= 0) {
                continue;
            }
            $events[$eventType][] = $ts;
        }

        return $events;
    }

    public function count_my_daily(int $employeeId, array $filters): int
    {
        $this->build_my_daily_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_daily(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_daily_query($employeeId, $filters, true);
        return $this->db->order_by('ad.attendance_date', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_daily_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ad.*, s.shift_code, s.shift_name');
        }

        $this->db->from('att_daily ad')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where('ad.employee_id', $employeeId);

        if (!empty($filters['date_start'])) {
            $this->db->where('ad.attendance_date >=', $filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ad.attendance_date <=', $filters['date_end']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('ad.attendance_status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('ad.remarks', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->group_end();
        }
    }

    public function ensure_auto_ph_presence(int $employeeId, string $date, array $policy): array
    {
        $noop = ['ok' => true, 'created' => false, 'recorded' => false, 'message' => ''];
        if ($employeeId <= 0 || strtoupper((string)($policy['ph_attendance_mode'] ?? 'AUTO_PRESENT')) !== 'AUTO_PRESENT') {
            return $noop;
        }

        $schedule = $this->get_schedule_with_shift($employeeId, $date);
        if (!$schedule || !in_array(strtoupper(trim((string)($schedule['shift_code'] ?? ''))), ['PH', 'PHB'], true)) {
            return $noop;
        }

        $CI = get_instance();
        // CI exposes its loader as a property, not a load() method.
        if (!$CI || !isset($CI->load) || !is_callable([$CI->load, 'model'])) {
            return ['ok' => false, 'created' => false, 'message' => 'Layanan validasi PH belum tersedia.'];
        }
        $CI->load->model('Attendance_model');
        if (!isset($CI->Attendance_model)
            || !method_exists($CI->Attendance_model, 'validate_scheduled_ph_use')
            || !method_exists($CI->Attendance_model, 'sync_ph_use_for_employee_date')) {
            return ['ok' => false, 'created' => false, 'message' => 'Layanan validasi saldo PH belum tersedia. Jalankan deploy attendance terbaru.'];
        }

        $exists = $this->db->select('ad.id, ad.attendance_status, s.shift_code')
            ->from('att_daily ad')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where('ad.employee_id', $employeeId)
            ->where('ad.attendance_date', $date)
            ->limit(1)
            ->get()->row_array();
        if ($exists) {
            $used = $CI->Attendance_model->sync_ph_use_for_employee_date($employeeId, $date, 0);
            $recorded = in_array(strtoupper(trim((string)($exists['shift_code'] ?? ''))), ['PH', 'PHB'], true)
                && in_array(strtoupper((string)($exists['attendance_status'] ?? '')), ['HOLIDAY', 'PRESENT', 'LATE'], true);
            return empty($used['ok'])
                ? ['ok' => false, 'created' => false, 'message' => (string)($used['message'] ?? 'Saldo PH tidak cukup.')]
                : array_replace($noop, ['recorded' => $recorded, 'message' => $recorded
                    ? 'Presensi PH dan penggunaan jatahnya sudah tercatat. Membuka ulang halaman tidak memakai jatah tambahan.'
                    : 'Presensi tanggal ini sudah memiliki catatan lain. Minta admin meninjau status/shift; data tidak diganti otomatis.']);
        }

        $capacity = $CI->Attendance_model->validate_scheduled_ph_use(
            $employeeId,
            $date,
            (int)($schedule['shift_id'] ?? 0)
        );
        if (empty($capacity['ok'])) {
            return ['ok' => false, 'created' => false, 'message' => (string)($capacity['message'] ?? 'Saldo PH tidak cukup untuk shift PH ini.')];
        }

        $this->load->model('Compensation_model');
        $compensation = $this->Compensation_model->resolve_for_employee($employeeId, $date);
        if (
            strtoupper((string)($compensation['source'] ?? '')) !== 'CONTRACT'
            || (int)($compensation['contract_id'] ?? 0) <= 0
        ) {
            return [
                'ok' => false,
                'created' => false,
                'message' => 'Presensi PH ditolak: belum ada kontrak ACTIVE yang efektif pada tanggal tersebut.',
            ];
        }

        [$startTs, $endTs] = $this->shift_bounds($date, (string)$schedule['start_time'], (string)$schedule['end_time'], (int)$schedule['is_overnight']);
        $minutes = 0;
        if ($startTs > 0 && $endTs > $startTs) {
            $minutes = (int)floor(($endTs - $startTs) / 60);
        }

        if ($this->db->trans_begin() === false) {
            return ['ok' => false, 'created' => false, 'message' => 'Presensi PH belum dapat diproses. Silakan muat ulang halaman.'];
        }
        $inserted = $this->db->insert('att_daily', [
            'attendance_date' => $date,
            'employee_id' => $employeeId,
            'shift_id' => (int)$schedule['shift_id'],
            'checkin_at' => $startTs > 0 ? date('Y-m-d H:i:s', $startTs) : null,
            'checkout_at' => $endTs > 0 ? date('Y-m-d H:i:s', $endTs) : null,
            'attendance_status' => 'HOLIDAY',
            'work_minutes' => max(0, $minutes),
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'overtime_pay' => 0,
            'daily_salary_amount' => 0,
            'source_type' => 'AUTO',
            'remarks' => 'Auto hadir PH saat buka halaman absen',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $insertedId = $inserted ? (int)$this->db->insert_id() : 0;
        if ($insertedId <= 0) {
            $this->db->trans_rollback();
            return ['ok' => false, 'created' => false, 'message' => 'Gagal membuat kehadiran otomatis untuk shift PH.'];
        }
        $this->compute_and_store_ph_salary($insertedId, $employeeId, $date, $policy, $compensation);
        $used = $CI->Attendance_model->sync_ph_use_for_employee_date($employeeId, $date, 0);
        if (empty($used['ok'])) {
            $this->db->trans_rollback();
            return ['ok' => false, 'created' => false, 'message' => (string)($used['message'] ?? 'Saldo PH tidak cukup.')];
        }
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'created' => false, 'message' => 'Gagal menyimpan kehadiran PH.'];
        }
        if ($this->db->trans_commit() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'created' => false, 'message' => 'Gagal menyimpan kehadiran PH.'];
        }

        return ['ok' => true, 'created' => true, 'recorded' => true, 'message' => 'Shift PH otomatis ditandai hadir dan satu jatah PH dipakai.'];
    }

    private function compute_and_store_ph_salary(int $dailyId, int $employeeId, string $date, array $policy, array $compensation): void
    {
        $workDays       = max(1, (int)($policy['default_work_days_per_month'] ?? 26));
        $basicSalary    = (float)($compensation['basic_salary'] ?? 0);
        $posAllowance   = (float)($compensation['position_allowance'] ?? 0);
        $objAllowance   = (float)($compensation['objective_allowance'] ?? 0);
        $mealRate       = (float)($compensation['meal_rate'] ?? 0);
        $overtimeRate   = $this->policy_overtime_rate($policy);

        $basicDailyRate    = $basicSalary / $workDays;
        $allowanceDailyRate = ($posAllowance + $objAllowance) / $workDays;
        $mealMode          = strtoupper((string)($policy['meal_calc_mode'] ?? 'MONTHLY'));
        $phGetsMeal        = (int)($policy['ph_gets_meal_allowance'] ?? 0) === 1;

        $basicEst  = $basicDailyRate;
        $allowEst  = $allowanceDailyRate;
        $mealEst   = $phGetsMeal ? $mealRate : 0.0;
        $gross     = round($basicEst + $allowEst + $mealEst, 2);
        $net       = $mealMode === 'CUSTOM'
            ? round($gross - $mealEst, 2)
            : $gross; // No deductions on PH

        $manualAdj = $this->get_manual_adjustment_totals_by_date($employeeId, $date);

        $payload = [
            'overtime_pay'          => 0,
            'daily_salary_amount'   => round($net + (float)($manualAdj['net'] ?? 0), 2),
        ];
        if ($this->att_daily_has_field('basic_amount')) {
            $payload['basic_amount']            = round($basicEst, 2);
            $payload['allowance_amount']        = round($allowEst, 2);
            $payload['meal_amount']             = round($mealEst, 2);
            $payload['late_deduction_amount']   = 0;
            $payload['alpha_deduction_amount']  = 0;
            $payload['gross_amount']            = $gross;
            $payload['net_amount']              = round($net, 2);
        }
        if ($this->att_daily_has_field('manual_addition_amount')) {
            $payload['manual_addition_amount'] = round((float)($manualAdj['addition'] ?? 0), 2);
        }
        if ($this->att_daily_has_field('manual_deduction_amount')) {
            $payload['manual_deduction_amount'] = round((float)($manualAdj['deduction'] ?? 0), 2);
        }
        if ($this->att_daily_has_field('manual_adjustment_net_amount')) {
            $payload['manual_adjustment_net_amount'] = round((float)($manualAdj['net'] ?? 0), 2);
        }
        if ($this->att_daily_has_field('snapshot_basic_salary')) {
            $payload['snapshot_basic_salary']         = round($basicSalary, 2);
            $payload['snapshot_position_allowance']   = round($posAllowance, 2);
            $payload['snapshot_objective_allowance']  = round($objAllowance, 2);
            $payload['snapshot_meal_rate']            = round($mealRate, 2);
        }
        if ($this->att_daily_has_field('snapshot_overtime_rate')) {
            $payload['snapshot_overtime_rate'] = round($overtimeRate, 2);
        }
        $payload += $this->build_daily_policy_lock_payload($policy);
        $this->load->model('Compensation_model');
        $payload += $this->Compensation_model->build_att_daily_provenance($compensation);

        $this->db->where('id', $dailyId)->update('att_daily', $payload);
    }

    public function mark_attendance(
        int $employeeId,
        string $date,
        string $eventType,
        array $policy,
        int $locationId = 0,
        ?float $latitude = null,
        ?float $longitude = null
    ): array
    {
        $eventType = strtoupper(trim($eventType));
        if (!in_array($eventType, ['CHECKIN', 'CHECKOUT'], true)) {
            return ['ok' => 0, 'message' => 'Jenis absen tidak valid.'];
        }

        $schedule = $this->get_schedule_with_shift($employeeId, $date);
        if (!$schedule) {
            return ['ok' => 0, 'message' => 'Jadwal shift hari ini belum tersedia.'];
        }

        // Device attendance is a current operational event and must snapshot
        // only an effective ACTIVE contract.
        $this->load->model('Compensation_model');
        $compensation = $this->Compensation_model->resolve_for_employee($employeeId, $date);
        if (
            strtoupper((string)($compensation['source'] ?? '')) !== 'CONTRACT'
            || (int)($compensation['contract_id'] ?? 0) <= 0
        ) {
            return ['ok' => 0, 'message' => 'Absensi ditolak: kontrak ACTIVE beserta snapshot kompensasi belum efektif untuk hari ini.'];
        }

        $shiftCode = strtoupper((string)($schedule['shift_code'] ?? ''));
        if (in_array($shiftCode, ['PH', 'PHB'], true) && strtoupper((string)($policy['ph_attendance_mode'] ?? 'AUTO_PRESENT')) === 'AUTO_PRESENT') {
            $autoPresence = $this->ensure_auto_ph_presence($employeeId, $date, $policy);
            return [
                'ok' => !empty($autoPresence['ok']) ? 1 : 0,
                'message' => (string)($autoPresence['message'] ?? 'Shift PH otomatis ditandai hadir penuh.'),
            ];
        }

        $nowTs = time();
        [$startTs, $endTs] = $this->shift_bounds(
            $date,
            (string)$schedule['start_time'],
            (string)$schedule['end_time'],
            (int)$schedule['is_overnight']
        );

        if ($eventType === 'CHECKIN') {
            $openMinutes = (int)($policy['checkin_open_minutes_before'] ?? 30);
            if ($openMinutes < 0) {
                $openMinutes = 0;
            }
            if ($startTs > 0 && $nowTs < ($startTs - ($openMinutes * 60))) {
                return ['ok' => 0, 'message' => 'Belum masuk window check-in sesuai jadwal shift.'];
            }
        }

        if ($eventType === 'CHECKOUT') {
            $closeMinutes = (int)($policy['checkout_close_minutes_after'] ?? 180);
            if ($closeMinutes >= 0 && $endTs > 0 && $nowTs > ($endTs + ($closeMinutes * 60))) {
                return ['ok' => 0, 'message' => 'Window check-out sudah ditutup untuk shift ini.'];
            }
        }

        $location = null;
        if ($locationId > 0) {
            $location = $this->db->select('id, latitude, longitude, radius_meter, location_name')
                ->from('att_location')
                ->where('id', $locationId)
                ->where('is_active', 1)
                ->limit(1)
                ->get()->row_array();
        }
        if (!$location) {
            $this->db->select('id, latitude, longitude, radius_meter, location_name')
                ->from('att_location')
                ->where('is_active', 1);
            if ($this->db->field_exists('is_default', 'att_location')) {
                $this->db->order_by('is_default', 'DESC');
            }
            $location = $this->db->order_by('id', 'ASC')
                ->limit(1)
                ->get()->row_array();
        }
        $resolvedLocationId = (int)($location['id'] ?? 0);

        $enforceGeo = (int)($policy['enforce_geofence'] ?? 0) === 1;
        $locLat = isset($location['latitude']) ? (float)$location['latitude'] : null;
        $locLon = isset($location['longitude']) ? (float)$location['longitude'] : null;
        $radiusMeter = isset($location['radius_meter']) ? (float)$location['radius_meter'] : 0.0;
        $hasLocationCenter = ($locLat !== null && $locLon !== null && $locLat != 0.0 && $locLon != 0.0 && $radiusMeter > 0);

        if ($enforceGeo && !$hasLocationCenter) {
            return ['ok' => 0, 'message' => 'Lokasi absensi belum punya koordinat/radius valid. Hubungi admin untuk melengkapi master lokasi.'];
        }

        if ($enforceGeo && $hasLocationCenter) {
            if ($latitude === null || $longitude === null) {
                return ['ok' => 0, 'message' => 'GPS perangkat belum terbaca. Izinkan akses lokasi lalu ulangi absensi.'];
            }
            $distance = $this->haversine_meter($latitude, $longitude, $locLat, $locLon);
            if ($distance > $radiusMeter) {
                return [
                    'ok' => 0,
                    'message' => 'Lokasi di luar radius absensi. Jarak saat ini ±' . (int)round($distance) . 'm, batas ' . (int)round($radiusMeter) . 'm (' . (string)($location['location_name'] ?? 'lokasi') . ').'
                ];
            }
        }

        $now = date('Y-m-d H:i:s', $nowTs);
        $this->db->trans_begin();
        $this->db->insert('att_presence', [
            'employee_id' => $employeeId,
            'shift_id' => (int)$schedule['shift_id'],
            'attendance_date' => $date,
            'attendance_time' => date('H:i:s', $nowTs),
            'attendance_at' => $now,
            'event_type' => $eventType,
            'source_type' => 'DEVICE',
            'location_id' => $resolvedLocationId > 0 ? $resolvedLocationId : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'created_at' => $now,
        ]);

        $recompute = $this->recompute_daily($employeeId, $date, $schedule, $policy);
        if (empty($recompute['ok'])) {
            $this->db->trans_rollback();
            return ['ok' => 0, 'message' => (string)($recompute['message'] ?? 'Gagal menyimpan absensi.')];
        }
        $this->db->trans_commit();
        if (!$this->db->trans_status()) {
            return ['ok' => 0, 'message' => 'Gagal menyimpan absensi.'];
        }

        return ['ok' => 1, 'message' => 'Absen ' . strtolower($eventType) . ' berhasil dicatat.'];
    }

    private function recompute_daily(int $employeeId, string $date, array $schedule, array $policy): array
    {
        [$startTs, $endTs] = $this->shift_bounds(
            $date,
            (string)$schedule['start_time'],
            (string)$schedule['end_time'],
            (int)$schedule['is_overnight']
        );

        $events = $this->get_presence_events_by_type($employeeId, $date);
        $effectiveCheckinTs = $this->select_effective_checkin_ts((array)($events['CHECKIN'] ?? []), $startTs);
        $effectiveCheckoutTs = $this->select_effective_checkout_ts((array)($events['CHECKOUT'] ?? []), $endTs);
        $effectiveCheckoutTs = $this->normalize_checkout_for_overnight_shift($effectiveCheckinTs, $effectiveCheckoutTs, $schedule);
        $checkinAt = $effectiveCheckinTs > 0 ? date('Y-m-d H:i:s', $effectiveCheckinTs) : null;
        $checkoutAt = $effectiveCheckoutTs > 0 ? date('Y-m-d H:i:s', $effectiveCheckoutTs) : null;

        if ($effectiveCheckoutTs > 0 && (int)($policy['night_shift_checkout_credit_to_operation_end'] ?? 1) === 1) {
            $creditAfter = trim((string)($policy['night_shift_checkout_credit_after'] ?? ''));
            $opStart = trim((string)($policy['operation_start_time'] ?? ''));
            $opEnd = trim((string)($policy['operation_end_time'] ?? ''));
            if ($creditAfter !== '' && $opStart !== '' && $opEnd !== '') {
                $checkoutClock = date('H:i:s', $effectiveCheckoutTs);
                if ($checkoutClock >= $creditAfter) {
                    $opEndTs = strtotime($date . ' ' . $opEnd);
                    if ($opEnd < $opStart) {
                        $opEndTs = strtotime('+1 day', $opEndTs);
                    }
                    if ($opEndTs > 0 && $effectiveCheckoutTs < $opEndTs) {
                        $effectiveCheckoutTs = $opEndTs;
                    }
                }
            }
        }

        $lateMinutes = 0;
        if ($checkinAt && $startTs > 0) {
            $grace = max(0, (int)($schedule['grace_late_minute'] ?? 0));
            $late = (int)floor(($effectiveCheckinTs - $startTs) / 60) - $grace;
            $lateMinutes = max(0, $late);
        }

        $workMinutes = 0;
        if ($effectiveCheckinTs > 0 && $effectiveCheckoutTs > 0) {
            if ($effectiveCheckoutTs > $effectiveCheckinTs) {
                $workMinutes = (int)floor(($effectiveCheckoutTs - $effectiveCheckinTs) / 60);
            }
        }

        $status = 'OFF';
        if ($checkinAt || $checkoutAt) {
            $status = ($lateMinutes > 0) ? 'LATE' : 'PRESENT';
        }

        $daily = $this->db
            ->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();

        $dailyId = (int)($daily['id'] ?? 0);

        // Check-in tidak boleh bergeser ke waktu yang lebih buruk saat ada check-in ulang.
        // Jika sudah tersimpan check-in lebih awal, pertahankan waktu paling awal.
        $existingCheckinTs = !empty($daily['checkin_at']) ? strtotime((string)$daily['checkin_at']) : 0;
        if ($existingCheckinTs > 0) {
            if ($effectiveCheckinTs <= 0 || $existingCheckinTs < $effectiveCheckinTs) {
                $effectiveCheckinTs = $existingCheckinTs;
                $checkinAt = date('Y-m-d H:i:s', $effectiveCheckinTs);
            }
        }

        // Check-out boleh terus membaik (mendekati/jika melewati jam pulang), jadi pertahankan yang paling akhir.
        $existingCheckoutTs = !empty($daily['checkout_at']) ? strtotime((string)$daily['checkout_at']) : 0;
        $existingCheckoutTs = $this->normalize_checkout_for_overnight_shift($effectiveCheckinTs, $existingCheckoutTs, $schedule);
        if ($existingCheckoutTs > 0 && $existingCheckoutTs > $effectiveCheckoutTs) {
            $effectiveCheckoutTs = $existingCheckoutTs;
            $checkoutAt = date('Y-m-d H:i:s', $effectiveCheckoutTs);
        }

        // Recompute metric setelah finalisasi effective checkin/checkout.
        $lateMinutes = 0;
        if ($effectiveCheckinTs > 0 && $startTs > 0) {
            $grace = max(0, (int)($schedule['grace_late_minute'] ?? 0));
            $late = (int)floor(($effectiveCheckinTs - $startTs) / 60) - $grace;
            $lateMinutes = max(0, $late);
        }

        $workMinutes = 0;
        if ($effectiveCheckinTs > 0 && $effectiveCheckoutTs > 0 && $effectiveCheckoutTs > $effectiveCheckinTs) {
            $workMinutes = (int)floor(($effectiveCheckoutTs - $effectiveCheckinTs) / 60);
        }

        $overtimeMode = strtoupper((string)($policy['overtime_calc_mode'] ?? 'AUTO'));
        if (!in_array($overtimeMode, ['AUTO', 'MANUAL'], true)) {
            $overtimeMode = 'AUTO';
        }
        $scheduledWorkMinutes = ($startTs > 0 && $endTs > $startTs) ? (int)floor(($endTs - $startTs) / 60) : 0;
        $overtimeMinutes = max(0, ($endTs > 0 && $effectiveCheckoutTs > $endTs) ? (int)floor(($effectiveCheckoutTs - $endTs) / 60) : 0);
        if ($overtimeMode === 'MANUAL') {
            if ($scheduledWorkMinutes > 0 && $workMinutes > $scheduledWorkMinutes) {
                $workMinutes = $scheduledWorkMinutes;
            }
            $overtimeMinutes = 0;
        }

        $status = 'OFF';
        if ($effectiveCheckinTs > 0 || $effectiveCheckoutTs > 0) {
            $status = ($lateMinutes > 0) ? 'LATE' : 'PRESENT';
        }

        $payload = [
            'shift_id' => (int)$schedule['shift_id'],
            'checkin_at' => $checkinAt,
            'checkout_at' => $effectiveCheckoutTs > 0 ? date('Y-m-d H:i:s', $effectiveCheckoutTs) : null,
            'attendance_status' => $status,
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => 0,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_pay' => 0,
            'daily_salary_amount' => 0,
            'source_type' => 'AUTO',
            'remarks' => $effectiveCheckoutTs > 0 ? null : 'Menunggu checkout',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $payload += $this->build_daily_policy_lock_payload($policy);

        if ($effectiveCheckinTs > 0 && $effectiveCheckoutTs > 0 && $endTs > 0 && $effectiveCheckoutTs < $endTs) {
            $payload['early_leave_minutes'] = max(0, (int)floor(($endTs - $effectiveCheckoutTs) / 60));
        }

        $compensationContext = $this->resolve_daily_compensation($employeeId, $date, $daily ?: []);
        $compensation = (array)($compensationContext['resolved'] ?? []);
        $basicSalary = (float)($compensation['basic_salary'] ?? 0);
        $positionAllowance = (float)($compensation['position_allowance'] ?? 0);
        $objectiveAllowance = (float)($compensation['objective_allowance'] ?? 0);
        $mealRate = (float)($compensation['meal_rate'] ?? 0);
        $overtimeRate = $this->policy_overtime_rate($policy);

        if ($this->att_daily_has_field('snapshot_basic_salary')) {
            $payload['snapshot_basic_salary'] = round($basicSalary, 2);
            $payload['snapshot_position_allowance'] = round($positionAllowance, 2);
            $payload['snapshot_objective_allowance'] = round($objectiveAllowance, 2);
            $payload['snapshot_meal_rate'] = round($mealRate, 2);
            $payload['snapshot_overtime_rate'] = round($overtimeRate, 2);
        }
        $payload += $this->daily_compensation_provenance_payload($daily ?: [], $compensationContext);

        $hasCompletedCheckout = ($effectiveCheckinTs > 0 && $effectiveCheckoutTs > 0 && $effectiveCheckoutTs > $effectiveCheckinTs);
        $workDays = max(1, (int)($policy['default_work_days_per_month'] ?? 26));
        $basicDailyRate = $basicSalary / $workDays;
        $allowanceDailyRate = ($positionAllowance + $objectiveAllowance) / $workDays;
        $mealMode = strtoupper((string)($policy['meal_calc_mode'] ?? 'MONTHLY'));
        $allowanceLateTreatment = strtoupper((string)($policy['allowance_late_treatment'] ?? 'FULL_IF_PRESENT'));
        $enableLateDeduction = (int)($policy['enable_late_deduction'] ?? 1) === 1;
        $enableAlphaDeduction = (int)($policy['enable_alpha_deduction'] ?? 1) === 1;
        $lateDeductionPerMinute = (float)($policy['late_deduction_per_minute'] ?? 0);
        $alphaDeductionPerDay = (float)($policy['alpha_deduction_per_day'] ?? 0);
        $attendanceMode = strtoupper((string)($policy['attendance_calc_mode'] ?? 'DAILY'));
        $prorateScope = strtoupper((string)($policy['prorate_deduction_scope'] ?? ($policy['payroll_late_deduction_scope'] ?? 'BASIC_ONLY')));
        if (!in_array($prorateScope, ['BASIC_ONLY', 'THP_TOTAL'], true)) {
            $prorateScope = 'BASIC_ONLY';
        }
        $hasConfiguredDeduction = ($enableLateDeduction && $lateDeductionPerMinute > 0)
            || ($enableAlphaDeduction && $alphaDeductionPerDay > 0);

        $basicEst = 0.0;
        $allowEst = 0.0;
        $mealEst = 0.0;
        $overtimePay = 0.0;
        $lateDeduction = 0.0;
        $alphaDeduction = 0.0;
        $grossAmount = 0.0;
        $netAmount = 0.0;

        $isPresentish = in_array($status, ['PRESENT', 'LATE', 'HOLIDAY'], true);
        $isHolidayPaidDay = ($status === 'HOLIDAY');
        $isPayrollPaidDay = ($hasCompletedCheckout || $isHolidayPaidDay);
        $phGetsMealAllowance = (int)($policy['ph_gets_meal_allowance'] ?? 0) === 1;
        $mealEst = 0.0;
        if ($isPresentish && $effectiveCheckinTs > 0) {
            $mealEst = $mealRate;
        } elseif ($isHolidayPaidDay && $phGetsMealAllowance) {
            $mealEst = $mealRate;
        }

        if ($isPayrollPaidDay) {
            $allowanceEligible = $isPresentish;
            if ($allowanceEligible && $allowanceLateTreatment === 'DEDUCT_IF_LATE' && $status === 'LATE') {
                $allowanceEligible = false;
            }

            $basicEst = $isPresentish ? $basicDailyRate : 0;
            $allowEst = $allowanceEligible ? $allowanceDailyRate : 0;
            if ($hasCompletedCheckout && $overtimeMode === 'MANUAL') {
                $overtimePay = $this->get_manual_overtime_pay($employeeId, $date);
            } elseif ($hasCompletedCheckout) {
                $overtimePay = ((int)$payload['overtime_minutes'] > 0 && $overtimeRate > 0)
                    ? (((int)$payload['overtime_minutes'] / 60) * $overtimeRate)
                    : 0;
            } else {
                $overtimePay = 0.0;
            }
            $lateDeduction = ($hasCompletedCheckout && $enableLateDeduction) ? ($lateMinutes * $lateDeductionPerMinute) : 0;
            $alphaDeduction = ($hasCompletedCheckout && $enableAlphaDeduction && $status === 'ALPHA') ? $alphaDeductionPerDay : 0;

            if ($hasCompletedCheckout && !$hasConfiguredDeduction && $scheduledWorkMinutes > 0) {
                $ratio = max(0, min(1, $workMinutes / $scheduledWorkMinutes));
                $deductionFromProrate = 0.0;
                if ($attendanceMode === 'DAILY') {
                    if ($prorateScope === 'THP_TOTAL') {
                        $deductionFromProrate = ($basicEst + $allowEst + $mealEst) * (1 - $ratio);
                    } else {
                        $deductionFromProrate = $basicEst * (1 - $ratio);
                    }
                } else {
                    $missingRatio = max(0, 1 - $ratio);
                    if ($prorateScope === 'THP_TOTAL') {
                        $deductionFromProrate = (($basicDailyRate + $allowanceDailyRate + $mealEst) * $missingRatio);
                    } else {
                        $deductionFromProrate = ($basicDailyRate * $missingRatio);
                    }
                }
                $lateDeduction += round($deductionFromProrate, 2);
            }

            $grossAmount = round($basicEst + $allowEst + $mealEst + $overtimePay, 2);
            $baseForNet = ($mealMode === 'CUSTOM') ? ($grossAmount - $mealEst) : $grossAmount;
            $netAmount = round($baseForNet - ($lateDeduction + $alphaDeduction), 2);
        }

        $manualAdj = $this->get_manual_adjustment_totals_by_date($employeeId, $date);
        $manualAddition = (float)($manualAdj['addition'] ?? 0);
        $manualDeduction = (float)($manualAdj['deduction'] ?? 0);
        $manualNet = (float)($manualAdj['net'] ?? 0);

        if ($this->att_daily_has_field('basic_amount')) {
            $payload['basic_amount'] = round($basicEst, 2);
            $payload['allowance_amount'] = round($allowEst, 2);
            $payload['meal_amount'] = round($mealEst, 2);
            $payload['late_deduction_amount'] = round($lateDeduction, 2);
            $payload['alpha_deduction_amount'] = round($alphaDeduction, 2);
            $payload['gross_amount'] = round($grossAmount, 2);
            $payload['net_amount'] = round($netAmount, 2);
        }
        if ($this->att_daily_has_field('manual_addition_amount')) {
            $payload['manual_addition_amount'] = round($manualAddition, 2);
        }
        if ($this->att_daily_has_field('manual_deduction_amount')) {
            $payload['manual_deduction_amount'] = round($manualDeduction, 2);
        }
        if ($this->att_daily_has_field('manual_adjustment_net_amount')) {
            $payload['manual_adjustment_net_amount'] = round($manualNet, 2);
        }

        $payload['overtime_pay'] = round($overtimePay, 2);
        $payload['daily_salary_amount'] = round($netAmount + $manualNet, 2);

        if ($dailyId > 0) {
            $this->db->where('id', $dailyId)->update('att_daily', $payload);
        } else {
            $this->db->insert('att_daily', [
                'attendance_date' => $date,
                'employee_id' => $employeeId,
                'created_at' => date('Y-m-d H:i:s'),
            ] + $payload);
        }

        // Keep both PH mutations in sync. A normal national-holiday shift may
        // grant PH, while an actual PH shift consumes it.
        $CI = get_instance();
        if ($CI && isset($CI->load) && is_callable([$CI->load, 'model'])) {
            $CI->load->model('Attendance_model');
            if (isset($CI->Attendance_model) && method_exists($CI->Attendance_model, 'sync_ph_grant_for_employee_date')) {
                $CI->Attendance_model->sync_ph_grant_for_employee_date($employeeId, $date, 0);
            }
            if (isset($CI->Attendance_model) && method_exists($CI->Attendance_model, 'sync_ph_use_for_employee_date')) {
                $use = $CI->Attendance_model->sync_ph_use_for_employee_date($employeeId, $date, 0);
                if (empty($use['ok'])) {
                    return $use;
                }
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    private function select_effective_checkin_ts(array $checkinTsList, int $shiftStartTs): int
    {
        if (empty($checkinTsList)) {
            return 0;
        }
        sort($checkinTsList);

        if ($shiftStartTs <= 0) {
            return (int)$checkinTsList[0];
        }

        $beforeOrOn = array_values(array_filter($checkinTsList, static function ($ts) use ($shiftStartTs) {
            return (int)$ts <= $shiftStartTs;
        }));
        if (!empty($beforeOrOn)) {
            return (int)max($beforeOrOn);
        }

        return (int)min($checkinTsList);
    }

    private function select_effective_checkout_ts(array $checkoutTsList, int $shiftEndTs): int
    {
        if (empty($checkoutTsList)) {
            return 0;
        }
        sort($checkoutTsList);

        if ($shiftEndTs <= 0) {
            return (int)max($checkoutTsList);
        }

        $afterOrOn = array_values(array_filter($checkoutTsList, static function ($ts) use ($shiftEndTs) {
            return (int)$ts >= $shiftEndTs;
        }));
        if (!empty($afterOrOn)) {
            return (int)max($afterOrOn);
        }

        return (int)max($checkoutTsList);
    }

    private function normalize_checkout_for_overnight_shift(int $checkinTs, int $checkoutTs, array $schedule): int
    {
        if ($checkinTs <= 0 || $checkoutTs <= 0 || $checkoutTs > $checkinTs) {
            return $checkoutTs;
        }

        $startTime = trim((string)($schedule['start_time'] ?? ''));
        $endTime = trim((string)($schedule['end_time'] ?? ''));
        $isOvernight = (int)($schedule['is_overnight'] ?? 0) === 1;
        if ($isOvernight || ($startTime !== '' && $endTime !== '' && $endTime <= $startTime)) {
            return (int)strtotime('+1 day', $checkoutTs);
        }

        return $checkoutTs;
    }

    private function get_manual_overtime_pay(int $employeeId, string $date): float
    {
        if ($employeeId <= 0 || $date === '' || !$this->db->table_exists('att_overtime_entry')) {
            return 0.0;
        }
        $row = $this->db->select('COALESCE(SUM(total_overtime_pay),0) AS total', false)
            ->from('att_overtime_entry')
            ->where('employee_id', $employeeId)
            ->where('overtime_date', $date)
            ->where('status', 'APPROVED')
            ->get()->row_array();
        return round((float)($row['total'] ?? 0), 2);
    }

    private function get_manual_adjustment_totals_by_date(int $employeeId, string $date): array
    {
        if ($employeeId <= 0 || $date === '' || !$this->db->table_exists('pay_manual_adjustment')) {
            return ['addition' => 0.0, 'deduction' => 0.0, 'net' => 0.0];
        }
        $rows = $this->db->select('adjustment_kind, COALESCE(SUM(amount),0) AS total_amount', false)
            ->from('pay_manual_adjustment')
            ->where('employee_id', $employeeId)
            ->where('adjustment_date', $date)
            ->where('status', 'APPROVED')
            ->group_by('adjustment_kind')
            ->get()->result_array();
        $addition = 0.0;
        $deduction = 0.0;
        foreach ($rows as $row) {
            $kind = strtoupper((string)($row['adjustment_kind'] ?? ''));
            $amount = (float)($row['total_amount'] ?? 0);
            if ($kind === 'DEDUCTION') {
                $deduction += $amount;
            } else {
                $addition += $amount;
            }
        }
        return [
            'addition' => round($addition, 2),
            'deduction' => round($deduction, 2),
            'net' => round($addition - $deduction, 2),
        ];
    }

    public function count_my_meal_ledger(int $employeeId, array $filters): int
    {
        $this->build_my_meal_ledger_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_meal_ledger(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_meal_ledger_query($employeeId, $filters, true);
        return $this->db->order_by('ad.attendance_date', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_meal_ledger_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                ad.attendance_date,
                ad.meal_amount,
                ad.attendance_status,
                mdl.id AS disbursement_line_id,
                mdl.transfer_status,
                mdl.transfer_ref_no,
                mdl.paid_at,
                md.disbursement_no,
                md.disbursement_date,
                md.status AS disbursement_status
            ", false);
        }

        $this->db->from('att_daily ad')
            ->join('pay_meal_disbursement_line mdl', 'mdl.employee_id = ad.employee_id AND mdl.attendance_date = ad.attendance_date', 'left')
            ->join('pay_meal_disbursement md', 'md.id = mdl.disbursement_id', 'left')
            ->where('ad.employee_id', $employeeId)
            ->where('COALESCE(ad.meal_amount,0) >', 0);
        // Monthly meal is settled together with salary, so the employee meal
        // ledger only exposes CUSTOM rows that can be paid separately.
        if (!$this->att_daily_has_field('meal_mode_snapshot')) {
            $this->db->where('1 = 0', null, false);
        } else {
            $this->db->where("COALESCE(ad.meal_mode_snapshot, 'MONTHLY') = 'CUSTOM'", null, false);
        }

        if (!empty($filters['date_start'])) {
            $this->db->where('ad.attendance_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ad.attendance_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['status'])) {
            $status = strtoupper((string)$filters['status']);
            if ($status === 'UNPAID') {
                $this->db->where('mdl.id IS NULL', null, false);
            } elseif (in_array($status, ['PENDING', 'PAID', 'FAILED', 'VOID'], true)) {
                $this->db->where('mdl.transfer_status', $status);
            }
        }
    }

    public function my_meal_ledger_summary(int $employeeId, array $filters): array
    {
        $this->build_my_meal_ledger_query($employeeId, $filters, true);
        $rows = $this->db->get()->result_array();
        $eligible = 0.0;
        $paid = 0.0;
        $pending = 0.0;
        foreach ($rows as $row) {
            $amount = (float)($row['meal_amount'] ?? 0);
            $eligible += $amount;
            $lineId = (int)($row['disbursement_line_id'] ?? 0);
            $transferStatus = strtoupper((string)($row['transfer_status'] ?? ''));
            if ($lineId <= 0) {
                $pending += $amount;
                continue;
            }
            if ($transferStatus === 'PAID') {
                $paid += $amount;
            } elseif ($transferStatus !== 'VOID') {
                $pending += $amount;
            }
        }
        return [
            'eligible_total' => round($eligible, 2),
            'paid_total' => round($paid, 2),
            'pending_total' => round($pending, 2),
        ];
    }

    public function count_my_overtime_entries(int $employeeId, array $filters): int
    {
        $this->build_my_overtime_entries_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_overtime_entries(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_overtime_entries_query($employeeId, $filters, true);
        return $this->db->order_by('oe.overtime_date', 'DESC')
            ->order_by('oe.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_overtime_entries_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('oe.*, os.standard_name AS overtime_standard_name, os.hourly_rate AS overtime_standard_rate');
        }

        $this->db->from('att_overtime_entry oe')
            ->join('att_overtime_standard os', 'os.id = oe.overtime_standard_id', 'left')
            ->where('oe.employee_id', $employeeId);

        if (!empty($filters['date_start'])) {
            $this->db->where('oe.overtime_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('oe.overtime_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('oe.status', strtoupper((string)$filters['status']));
        }
    }

    public function count_my_ph_ledger(int $employeeId, array $filters): int
    {
        $this->build_my_ph_ledger_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_ph_ledger(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_ph_ledger_query($employeeId, $filters, true);
        return $this->db->order_by('l.tx_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_ph_ledger_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                l.*,
                (
                    SELECT COALESCE(SUM(
                      CASE x.tx_type
                        WHEN 'GRANT' THEN x.qty_days
                        WHEN 'ADJUST' THEN x.qty_days
                        WHEN 'USE' THEN -x.qty_days
                        WHEN 'EXPIRE' THEN -x.qty_days
                        ELSE 0
                      END
                    ),0)
                    FROM att_employee_ph_ledger x
                    WHERE x.employee_id = l.employee_id
                      AND (x.tx_date < l.tx_date OR (x.tx_date = l.tx_date AND x.id <= l.id))
                ) AS running_balance
            ", false);
        }

        $this->db->from('att_employee_ph_ledger l')
            ->where('l.employee_id', $employeeId);

        if (!empty($filters['date_start'])) {
            $this->db->where('l.tx_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('l.tx_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['tx_type'])) {
            $this->db->where('l.tx_type', strtoupper((string)$filters['tx_type']));
        }
        $expiredState = strtoupper(trim((string)($filters['expired_state'] ?? 'ALL')));
        if ($expiredState === 'ACTIVE') {
            $this->db->where('l.tx_type', 'GRANT');
            $this->db->group_start()
                ->where('l.expired_at IS NULL', null, false)
                ->or_where('l.expired_at >=', date('Y-m-d'))
                ->group_end();
        } elseif ($expiredState === 'EXPIRED') {
            $this->db->where('l.tx_type', 'GRANT');
            $this->db->where('l.expired_at IS NOT NULL', null, false);
            $this->db->where('l.expired_at <', date('Y-m-d'));
        }
    }

    public function my_ph_balance_summary(int $employeeId): array
    {
        $row = $this->db->select("
            COALESCE(SUM(CASE WHEN tx_type='GRANT' THEN qty_days ELSE 0 END),0) AS grant_total,
            COALESCE(SUM(CASE WHEN tx_type='USE' THEN qty_days ELSE 0 END),0) AS use_total,
            COALESCE(SUM(CASE WHEN tx_type='EXPIRE' THEN qty_days ELSE 0 END),0) AS expire_total,
            COALESCE(SUM(CASE WHEN tx_type='ADJUST' THEN qty_days ELSE 0 END),0) AS adjust_total
        ", false)
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->get()->row_array();

        $grant = (float)($row['grant_total'] ?? 0);
        $use = (float)($row['use_total'] ?? 0);
        $expire = (float)($row['expire_total'] ?? 0);
        $adjust = (float)($row['adjust_total'] ?? 0);
        return [
            'grant_total' => round($grant, 2),
            'use_total' => round($use, 2),
            'expire_total' => round($expire, 2),
            'adjust_total' => round($adjust, 2),
            'balance' => round(($grant + $adjust) - ($use + $expire), 2),
        ];
    }

    public function count_my_manual_adjustments(int $employeeId, array $filters): int
    {
        $this->build_my_manual_adjustment_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_manual_adjustments(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_manual_adjustment_query($employeeId, $filters, true);
        return $this->db->order_by('ma.adjustment_date', 'DESC')
            ->order_by('ma.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_manual_adjustment_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ma.*');
        }
        $this->db->from('pay_manual_adjustment ma')
            ->where('ma.employee_id', $employeeId);

        if (!empty($filters['date_start'])) {
            $this->db->where('ma.adjustment_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ma.adjustment_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('ma.status', strtoupper((string)$filters['status']));
        }
        if (!empty($filters['adjustment_kind'])) {
            $this->db->where('ma.adjustment_kind', strtoupper((string)$filters['adjustment_kind']));
        }
    }

    public function count_my_cash_advances(int $employeeId, array $filters): int
    {
        $this->build_my_cash_advance_query($employeeId, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_my_cash_advances(int $employeeId, array $filters, int $limit, int $offset): array
    {
        $this->build_my_cash_advance_query($employeeId, $filters, true);
        return $this->db->order_by('ca.request_date', 'DESC')
            ->order_by('ca.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_my_cash_advance_query(int $employeeId, array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                ca.*,
                COALESCE((SELECT SUM(i.plan_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id),0) AS installment_plan_total,
                COALESCE((SELECT SUM(i.paid_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id),0) AS installment_paid_total
            ", false);
        }
        $this->db->from('pay_cash_advance ca')
            ->where('ca.employee_id', $employeeId);

        if (!empty($filters['date_start'])) {
            $this->db->where('ca.request_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ca.request_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('ca.status', strtoupper((string)$filters['status']));
        }
    }

    public function list_cash_advance_installments(int $cashAdvanceId): array
    {
        if ($cashAdvanceId <= 0) {
            return [];
        }
        return $this->db->from('pay_cash_advance_installment')
            ->where('cash_advance_id', $cashAdvanceId)
            ->order_by('installment_no', 'ASC')
            ->get()->result_array();
    }

    private function shift_bounds(string $date, string $startTime, string $endTime, int $isOvernight): array
    {
        $startTs = strtotime($date . ' ' . $startTime);
        $endTs = strtotime($date . ' ' . $endTime);
        if ($startTs > 0 && $endTs > 0 && ($isOvernight === 1 || $endTs <= $startTs)) {
            $endTs = strtotime('+1 day', $endTs);
        }
        return [$startTs ?: 0, $endTs ?: 0];
    }

    private function haversine_meter(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
