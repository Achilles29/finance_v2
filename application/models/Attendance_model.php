<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_model extends CI_Model
{
    private $attDailyFieldCache = [];
    private $lockedPeriodDateCache = [];

    private function att_daily_has_field(string $field): bool
    {
        if (!array_key_exists($field, $this->attDailyFieldCache)) {
            $this->attDailyFieldCache[$field] = $this->db->field_exists($field, 'att_daily');
        }
        return (bool)$this->attDailyFieldCache[$field];
    }

    private function get_locked_period_for_date(string $date): ?array
    {
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        if (array_key_exists($date, $this->lockedPeriodDateCache)) {
            return $this->lockedPeriodDateCache[$date];
        }
        if (!$this->db->table_exists('pay_payroll_period') || !$this->db->table_exists('pay_salary_disbursement')) {
            $this->lockedPeriodDateCache[$date] = null;
            return null;
        }

        $row = $this->db->select('p.id, p.period_code, d.disbursement_no, d.status AS disbursement_status')
            ->from('pay_payroll_period p')
            ->join('pay_salary_disbursement d', 'd.payroll_period_id = p.id AND d.status <> "VOID"', 'inner')
            ->where('p.period_start <=', $date)
            ->where('p.period_end >=', $date)
            ->order_by('p.period_start', 'DESC')
            ->order_by('d.id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        $this->lockedPeriodDateCache[$date] = $row ?: null;
        return $this->lockedPeriodDateCache[$date];
    }

    private function immutable_period_guard_message(string $date): string
    {
        $locked = $this->get_locked_period_for_date($date);
        if (!$locked) {
            return '';
        }
        return 'Periode payroll ' . (string)($locked['period_code'] ?? '#') . ' sudah terkunci oleh batch ' . (string)($locked['disbursement_no'] ?? '#') . ' [' . strtoupper((string)($locked['disbursement_status'] ?? '-')) . ']. Perubahan lembur diblokir.';
    }

    private function filter_existing_fields(string $table, array $payload): array
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($this->db->field_exists($key, $table)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
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

    private function insert_ph_grant_ledger(array $payload): bool
    {
        $sql = "INSERT IGNORE INTO att_employee_ph_ledger
            (employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->query($sql, [
            (int)($payload['employee_id'] ?? 0),
            (string)($payload['tx_date'] ?? ''),
            (string)($payload['tx_type'] ?? 'GRANT'),
            (float)($payload['qty_days'] ?? 0),
            $payload['expired_at'] ?? null,
            (string)($payload['ref_table'] ?? 'att_daily'),
            isset($payload['ref_id']) ? (int)$payload['ref_id'] : null,
            (string)($payload['entry_mode'] ?? 'AUTO'),
            (string)($payload['notes'] ?? ''),
            isset($payload['created_by']) && (int)$payload['created_by'] > 0 ? (int)$payload['created_by'] : null,
            (string)($payload['created_at'] ?? date('Y-m-d H:i:s')),
            (string)($payload['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);
        return $this->db->affected_rows() > 0;
    }

    private function is_ph_shift_code(string $shiftCode): bool
    {
        return in_array(strtoupper(trim($shiftCode)), ['PH', 'PHB'], true);
    }

    /**
     * Older releases accidentally granted PH when the employee used a PH
     * shift. The attendance shift is the immutable business fact; a schedule
     * may legitimately be corrected before attendance is finalized.
     */
    private function legacy_wrong_auto_ph_grant_id_set(int $employeeId = 0, string $asOfDate = ''): array
    {
        if (
            !$this->db->table_exists('att_employee_ph_ledger')
            || !$this->db->table_exists('att_daily')
            || !$this->db->table_exists('att_shift')
        ) {
            return [];
        }

        $sql = "
            SELECT ledger_grant.id
            FROM att_employee_ph_ledger ledger_grant
            JOIN att_daily daily_row
              ON ledger_grant.ref_table = 'att_daily'
             AND ledger_grant.ref_id = daily_row.id
            JOIN att_shift daily_shift ON daily_shift.id = daily_row.shift_id
            WHERE ledger_grant.tx_type = 'GRANT'
              AND UPPER(COALESCE(ledger_grant.entry_mode, '')) = 'AUTO'
              AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
        ";
        $params = [];
        if ($employeeId > 0) {
            $sql .= ' AND ledger_grant.employee_id = ?';
            $params[] = $employeeId;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
            $sql .= ' AND ledger_grant.tx_date <= ?';
            $params[] = $asOfDate;
        }

        $idSet = [];
        foreach ($this->db->query($sql, $params)->result_array() as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $idSet[$id] = true;
            }
        }
        return $idSet;
    }

    /**
     * Keep historical rows visible for audit, but exclude the known reversed
     * logic and its expiry child rows when calculating usable PH capacity.
     */
    private function filter_legacy_wrong_auto_ph_ledger_rows(array $rows, array $wrongGrantIds): array
    {
        if (empty($wrongGrantIds)) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($wrongGrantIds): bool {
            $ledgerId = (int)($row['id'] ?? 0);
            if ($ledgerId > 0 && isset($wrongGrantIds[$ledgerId])) {
                return false;
            }

            return !(
                strtoupper((string)($row['tx_type'] ?? '')) === 'EXPIRE'
                && (string)($row['ref_table'] ?? '') === 'att_employee_ph_ledger'
                && isset($wrongGrantIds[(int)($row['ref_id'] ?? 0)])
            );
        }));
    }

    /**
     * A PH grant must be based on one real work session, not merely on a
     * non-empty timestamp that may have been corrected to an unrelated date.
     */
    private function is_valid_ph_work_attendance(array $row, array $policy, bool $requireCheckout): bool
    {
        $attendanceDate = trim((string)($row['attendance_date'] ?? ''));
        $status = strtoupper(trim((string)($row['attendance_status'] ?? '')));
        if ($attendanceDate === '' || !in_array($status, ['PRESENT', 'LATE'], true)) {
            return false;
        }

        $checkinAt = trim((string)($row['checkin_at'] ?? ''));
        $checkoutAt = trim((string)($row['checkout_at'] ?? ''));
        $checkinTs = $checkinAt !== '' ? strtotime($checkinAt) : 0;
        $checkoutTs = $checkoutAt !== '' ? strtotime($checkoutAt) : 0;
        if ($checkinTs <= 0 || date('Y-m-d', $checkinTs) !== $attendanceDate) {
            return false;
        }
        if ($requireCheckout && $checkoutTs <= 0) {
            return false;
        }
        if ($checkoutTs <= 0) {
            return true;
        }
        if ($checkoutTs < $checkinTs) {
            return false;
        }

        $startTime = trim((string)($row['scheduled_start_time'] ?? ''));
        $endTime = trim((string)($row['scheduled_end_time'] ?? ''));
        if ($startTime === '' || $endTime === '') {
            return true;
        }

        $startTs = strtotime($attendanceDate . ' ' . $startTime);
        $endTs = strtotime($attendanceDate . ' ' . $endTime);
        $isOvernight = (int)($row['scheduled_is_overnight'] ?? 0) === 1;
        if ($startTs > 0 && $endTs > 0 && ($isOvernight || $endTs <= $startTs)) {
            $endTs = strtotime('+1 day', $endTs);
        }
        if ($endTs <= 0) {
            return true;
        }

        $closeMinutes = max(0, (int)($policy['checkout_close_minutes_after'] ?? 180));
        return $checkoutTs <= ($endTs + ($closeMinutes * 60));
    }

    private function list_ph_grant_attendance_candidates(string $dateStart, string $dateEnd, int $employeeId = 0): array
    {
        $sql = "
            SELECT DISTINCT
                ad.id AS daily_id,
                ad.employee_id,
                ad.attendance_date,
                ad.checkin_at,
                ad.checkout_at,
                ad.attendance_status,
                ad.source_type,
                scheduled_shift.shift_code AS scheduled_shift_code,
                scheduled_shift.start_time AS scheduled_start_time,
                scheduled_shift.end_time AS scheduled_end_time,
                scheduled_shift.is_overnight AS scheduled_is_overnight,
                daily_shift.shift_code AS daily_shift_code,
                pe.effective_date,
                pe.expiry_months_override
            FROM att_daily ad
            JOIN att_shift_schedule ss
              ON ss.employee_id = ad.employee_id
              AND ss.schedule_date = ad.attendance_date
            JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
            LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
            JOIN att_holiday_calendar hc
              ON hc.holiday_date = ad.attendance_date
             AND hc.is_active = 1
             AND hc.holiday_type = 'NATIONAL'
            JOIN att_ph_eligibility pe
              ON pe.employee_id = ad.employee_id
             AND pe.is_eligible = 1
             AND pe.effective_date <= ad.attendance_date
            WHERE ad.attendance_date >= ?
              AND ad.attendance_date <= ?
              AND ad.attendance_status IN ('PRESENT', 'LATE')
              AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
              AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
        ";
        $params = [$dateStart, $dateEnd];
        if ($employeeId > 0) {
            $sql .= ' AND ad.employee_id = ?';
            $params[] = $employeeId;
        }
        $sql .= ' ORDER BY ad.attendance_date ASC, ad.id ASC';
        return $this->db->query($sql, $params)->result_array();
    }

    private function create_ph_grant_from_attendance_row(array $row, array $policy, int $actorUserId): array
    {
        $dailyId = (int)($row['daily_id'] ?? 0);
        $employeeId = (int)($row['employee_id'] ?? 0);
        $attendanceDate = (string)($row['attendance_date'] ?? '');
        if ($dailyId <= 0 || $employeeId <= 0 || $attendanceDate === '') {
            return ['created' => false, 'skipped' => true, 'message' => 'Data absensi kandidat PH tidak lengkap.'];
        }

        $requireCheckout = (int)($policy['ph_grant_requires_checkout'] ?? 1) === 1;
        if (!$this->is_valid_ph_work_attendance($row, $policy, $requireCheckout)) {
            return ['created' => false, 'skipped' => true, 'message' => 'Presensi kerja belum final atau berada di luar rentang shift.'];
        }

        $exists = $this->db->select('id')
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->where('tx_type', 'GRANT')
            ->where('ref_table', 'att_daily')
            ->where('ref_id', $dailyId)
            ->limit(1)
            ->get()->row_array();
        if ($exists) {
            return ['created' => false, 'skipped' => true, 'message' => 'Grant PH sudah ada.'];
        }

        $grantQty = round((float)($policy['ph_grant_qty_per_day'] ?? 1), 2);
        if ($grantQty <= 0) {
            $grantQty = 1;
        }
        $expiryMonths = $row['expiry_months_override'] !== null
            ? max(0, (int)$row['expiry_months_override'])
            : max(0, (int)($policy['ph_expiry_months'] ?? 0));
        $expiredAt = $expiryMonths > 0
            ? date('Y-m-d', strtotime($attendanceDate . ' +' . $expiryMonths . ' month'))
            : null;
        $now = date('Y-m-d H:i:s');
        $created = $this->insert_ph_grant_ledger([
            'employee_id' => $employeeId,
            'tx_date' => $attendanceDate,
            'tx_type' => 'GRANT',
            'qty_days' => $grantQty,
            'expired_at' => $expiredAt,
            'ref_table' => 'att_daily',
            'ref_id' => $dailyId,
            'entry_mode' => 'AUTO',
            'notes' => 'Auto grant: hadir pada hari libur nasional dengan shift kerja reguler.',
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'created' => $created,
            'skipped' => !$created,
            'message' => $created ? 'Grant PH dibuat.' : 'Grant PH sudah ada atau gagal dibuat.',
        ];
    }

    public function get_active_policy(): array
    {
        $row = $this->db->from('att_attendance_policy')
            ->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        if ($row) {
            // PH is paid compensatory leave, not a regular clock-in shift.
            // Keep it automatic regardless of a legacy policy value.
            $row['ph_attendance_mode'] = 'AUTO_PRESENT';
            // Keep one business definition everywhere: PH is earned only by
            // working a regular shift on an active national holiday.
            $row['ph_grant_mode'] = 'HOLIDAY_ONLY';
            $row['ph_grant_holiday_type'] = 'NATIONAL';
            if (!isset($row['ph_grant_requires_checkout'])) {
                $row['ph_grant_requires_checkout'] = 1;
            }
            if (!isset($row['ph_grant_qty_per_day']) || (float)$row['ph_grant_qty_per_day'] <= 0) {
                $row['ph_grant_qty_per_day'] = 1.0;
            }
            if (!isset($row['overtime_calc_mode']) || $row['overtime_calc_mode'] === '') {
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

        return [
            'policy_code' => 'FINANCE_DEFAULT',
            'policy_name' => 'Finance Default Policy',
            'checkin_open_minutes_before' => 30,
            'enforce_geofence' => 1,
            'require_photo' => 0,
            'late_deduction_per_minute' => 0,
            'alpha_deduction_per_day' => 0,
            'use_basic_salary_daily_rate' => 1,
            'default_work_days_per_month' => 26,
            'attendance_calc_mode' => 'DAILY',
            'payroll_late_deduction_scope' => 'BASIC_ONLY',
            'allowance_late_treatment' => 'FULL_IF_PRESENT',
            'meal_calc_mode' => 'MONTHLY',
            'overtime_calc_mode' => 'AUTO',
            'operation_start_time' => '08:00:00',
            'operation_end_time' => '23:00:00',
            'night_shift_checkout_credit_after' => '22:00:00',
            'night_shift_checkout_credit_to_operation_end' => 1,
            'checkout_close_minutes_after' => 180,
            'enable_late_deduction' => 1,
            'enable_alpha_deduction' => 1,
            'prorate_deduction_scope' => 'BASIC_ONLY',
            'pending_request_scope' => 'SELF_ONLY',
            'pending_approval_levels' => 3,
            'attendance_revision_window_mode' => 'ON',
            'attendance_revision_window_days' => 7,
            'ph_attendance_mode' => 'AUTO_PRESENT',
            'ph_grant_mode' => 'HOLIDAY_ONLY',
            'ph_grant_holiday_type' => 'NATIONAL',
            'ph_grant_requires_checkout' => 1,
            'ph_grant_qty_per_day' => 1,
            'ph_expiry_months' => 3,
            'ph_gets_meal_allowance' => 0,
            'ph_gets_bonus' => 0,
        ];
    }

    public function save_policy(
        array $payload,
        array $submitterPositionIds = [],
        array $verifierByLevel = [],
        array $scheduleOverridePositionIds = [],
        array $scheduleOverrideUserIds = []
    ): void
    {
        $payload = $this->filter_existing_fields('att_attendance_policy', $payload);
        if (empty($payload)) {
            return;
        }

        $this->db->trans_start();
        $current = $this->db->from('att_attendance_policy')
            ->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        if ($this->db->field_exists('is_active', 'att_attendance_policy')) {
            $this->db->set('is_active', 0)->update('att_attendance_policy');
        }

        if ($current) {
            $policyId = (int)$current['id'];
            $this->db->where('id', $policyId)->update('att_attendance_policy', $payload + ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->insert('att_attendance_policy', $payload + ['is_active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            $policyId = (int)$this->db->insert_id();
        }

        if (!empty($policyId)) {
            $this->save_pending_submitter_positions($policyId, $submitterPositionIds);
            $this->save_pending_verifier_positions($policyId, $verifierByLevel);
            $this->save_schedule_monthly_override_authorities(
                $policyId,
                $scheduleOverridePositionIds,
                $scheduleOverrideUserIds
            );
        }
        $this->db->trans_complete();
    }

    public function get_division_options(): array
    {
        return $this->db->select('id AS value, division_name AS label')
            ->from('org_division')
            ->where('is_active', 1)
            ->order_by('division_name', 'ASC')
            ->get()->result_array();
    }

    public function get_position_options(): array
    {
        return $this->db->select('id AS value, position_name AS label')
            ->from('org_position')
            ->where('is_active', 1)
            ->order_by('position_name', 'ASC')
            ->get()->result_array();
    }

    public function get_active_user_options(): array
    {
        if (!$this->db->table_exists('auth_user')) {
            return [];
        }

        return $this->db->select("\n                u.id AS value,\n                CONCAT(\n                    COALESCE(NULLIF(e.employee_name, ''), u.username),\n                    ' (@', u.username, ')',\n                    CASE\n                        WHEN COALESCE(p.position_name, '') <> '' THEN CONCAT(' - ', p.position_name)\n                        ELSE ''\n                    END\n                ) AS label\n            ", false)
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('u.is_active', 1)
            ->order_by('e.employee_name', 'ASC')
            ->order_by('u.username', 'ASC')
            ->get()->result_array();
    }

    public function get_schedule_monthly_override_position_ids(int $policyId): array
    {
        if ($policyId <= 0 || !$this->db->table_exists('att_schedule_monthly_override_position')) {
            return [];
        }

        $rows = $this->db->select('position_id')
            ->from('att_schedule_monthly_override_position')
            ->where('policy_id', $policyId)
            ->order_by('position_id', 'ASC')
            ->get()->result_array();

        return array_map(static function ($row): int {
            return (int)($row['position_id'] ?? 0);
        }, $rows);
    }

    public function get_schedule_monthly_override_user_ids(int $policyId): array
    {
        if ($policyId <= 0 || !$this->db->table_exists('att_schedule_monthly_override_user')) {
            return [];
        }

        $rows = $this->db->select('user_id')
            ->from('att_schedule_monthly_override_user')
            ->where('policy_id', $policyId)
            ->order_by('user_id', 'ASC')
            ->get()->result_array();

        return array_map(static function ($row): int {
            return (int)($row['user_id'] ?? 0);
        }, $rows);
    }

    /**
     * Superadmin remains the recovery path. Other accounts must be assigned
     * explicitly through the active attendance policy, by user or position.
     */
    public function can_user_override_schedule_monthly_limit(int $userId, bool $isSuperadmin = false): bool
    {
        if ($isSuperadmin) {
            return true;
        }
        if ($userId <= 0) {
            return false;
        }

        $policyId = (int)($this->get_active_policy()['id'] ?? 0);
        if ($policyId <= 0) {
            return false;
        }

        if ($this->db->table_exists('att_schedule_monthly_override_user')) {
            $userMatch = $this->db->select('authority.id')
                ->from('att_schedule_monthly_override_user authority')
                ->join('auth_user u', 'u.id = authority.user_id', 'inner')
                ->where('authority.policy_id', $policyId)
                ->where('authority.user_id', $userId)
                ->where('u.is_active', 1)
                ->limit(1)
                ->get()->row_array();
            if ($userMatch) {
                return true;
            }
        }

        if (!$this->db->table_exists('att_schedule_monthly_override_position')) {
            return false;
        }

        $positionMatch = $this->db->select('authority.id')
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'inner')
            ->join('org_position p', 'p.id = e.position_id', 'inner')
            ->join('att_schedule_monthly_override_position authority', 'authority.position_id = e.position_id', 'inner')
            ->where('u.id', $userId)
            ->where('authority.policy_id', $policyId)
            ->where('u.is_active', 1)
            ->where('e.is_active', 1)
            ->where('p.is_active', 1)
            ->limit(1)
            ->get()->row_array();

        return (bool)$positionMatch;
    }

    public function get_pending_submitter_position_ids(int $policyId): array
    {
        if ($policyId <= 0 || !$this->db->table_exists('att_pending_submitter_position')) {
            return [];
        }
        $rows = $this->db->select('position_id')
            ->from('att_pending_submitter_position')
            ->where('policy_id', $policyId)
            ->order_by('position_id', 'ASC')
            ->get()->result_array();
        return array_map(static function ($r) { return (int)$r['position_id']; }, $rows);
    }

    public function get_pending_verifier_position_ids(int $policyId, int $level): array
    {
        if ($policyId <= 0 || $level <= 0 || !$this->db->table_exists('att_pending_verifier_position')) {
            return [];
        }
        $rows = $this->db->select('position_id')
            ->from('att_pending_verifier_position')
            ->where('policy_id', $policyId)
            ->where('verify_level', $level)
            ->order_by('position_id', 'ASC')
            ->get()->result_array();
        return array_map(static function ($r) { return (int)$r['position_id']; }, $rows);
    }

    private function save_pending_submitter_positions(int $policyId, array $positionIds): void
    {
        if (!$this->db->table_exists('att_pending_submitter_position')) {
            return;
        }

        $this->db->where('policy_id', $policyId)->delete('att_pending_submitter_position');

        $clean = [];
        foreach ($positionIds as $id) {
            $val = (int)$id;
            if ($val > 0) {
                $clean[$val] = true;
            }
        }

        if (empty($clean)) {
            return;
        }

        $batch = [];
        foreach (array_keys($clean) as $positionId) {
            $batch[] = [
                'policy_id' => $policyId,
                'position_id' => $positionId,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        $this->db->insert_batch('att_pending_submitter_position', $batch);
    }

    private function save_pending_verifier_positions(int $policyId, array $verifierByLevel): void
    {
        if (!$this->db->table_exists('att_pending_verifier_position')) {
            return;
        }

        $this->db->where('policy_id', $policyId)->delete('att_pending_verifier_position');

        $batch = [];
        foreach ($verifierByLevel as $level => $ids) {
            $levelInt = (int)$level;
            if ($levelInt < 1 || $levelInt > 3 || !is_array($ids)) {
                continue;
            }

            $clean = [];
            foreach ($ids as $id) {
                $val = (int)$id;
                if ($val > 0) {
                    $clean[$val] = true;
                }
            }

            foreach (array_keys($clean) as $positionId) {
                $batch[] = [
                    'policy_id' => $policyId,
                    'verify_level' => $levelInt,
                    'position_id' => $positionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (!empty($batch)) {
            $this->db->insert_batch('att_pending_verifier_position', $batch);
        }
    }

    private function save_schedule_monthly_override_authorities(
        int $policyId,
        array $positionIds,
        array $userIds
    ): void {
        if ($policyId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        if ($this->db->table_exists('att_schedule_monthly_override_position')) {
            $this->db->where('policy_id', $policyId)->delete('att_schedule_monthly_override_position');

            $positions = [];
            foreach ($positionIds as $positionId) {
                $positionId = (int)$positionId;
                if ($positionId > 0) {
                    $positions[$positionId] = true;
                }
            }
            if (!empty($positions)) {
                $batch = [];
                foreach (array_keys($positions) as $positionId) {
                    $batch[] = [
                        'policy_id' => $policyId,
                        'position_id' => $positionId,
                        'created_at' => $now,
                    ];
                }
                $this->db->insert_batch('att_schedule_monthly_override_position', $batch);
            }
        }

        if ($this->db->table_exists('att_schedule_monthly_override_user')) {
            $this->db->where('policy_id', $policyId)->delete('att_schedule_monthly_override_user');

            $users = [];
            foreach ($userIds as $userId) {
                $userId = (int)$userId;
                if ($userId > 0) {
                    $users[$userId] = true;
                }
            }
            if (!empty($users)) {
                $batch = [];
                foreach (array_keys($users) as $userId) {
                    $batch[] = [
                        'policy_id' => $policyId,
                        'user_id' => $userId,
                        'created_at' => $now,
                    ];
                }
                $this->db->insert_batch('att_schedule_monthly_override_user', $batch);
            }
        }
    }

    public function count_daily(array $f): int
    {
        $this->build_daily_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_daily(array $f, int $limit, int $offset): array
    {
        $this->build_daily_query($f, true);
        return $this->db
            ->order_by('ad.attendance_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_daily_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ad.*, e.employee_code, e.employee_name, d.division_name, p.position_name, s.shift_code, s.shift_name');
        }

        $this->db->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift s', 's.id = ad.shift_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ad.attendance_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ad.attendance_date <=', $f['date_end']);
        }
        if (!empty($f['status'])) {
            $this->db->where('ad.attendance_status', $f['status']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->group_end();
        }
    }

    public function count_logs(array $f): int
    {
        $this->build_logs_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_logs(array $f, int $limit, int $offset): array
    {
        $this->build_logs_query($f, true);
        return $this->db
            ->order_by('ap.attendance_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_logs_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ap.*, e.employee_code, e.employee_name, d.division_name, s.shift_code, s.shift_name, l.location_name');
        }

        $this->db->from('att_presence ap')
            ->join('org_employee e', 'e.id = ap.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_shift s', 's.id = ap.shift_id', 'left')
            ->join('att_location l', 'l.id = ap.location_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ap.attendance_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ap.attendance_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['event_type'])) {
            $this->db->where('ap.event_type', $f['event_type']);
        }
        if (!empty($f['source_type'])) {
            $this->db->where('ap.source_type', $f['source_type']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('l.location_name', $q)
                ->group_end();
        }
    }

    public function count_schedules(array $f): int
    {
        $this->build_schedules_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function count_schedule_employees(array $f): int
    {
        $this->db->select('COUNT(DISTINCT e.id) AS cnt', false);
        $this->build_schedules_query($f, false);
        $row = $this->db->get()->row_array();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * The normal schedule screen is employee-first. Individual assignments are
     * loaded only after an employee is opened, keeping the list readable.
     */
    public function list_schedule_employee_summaries(array $f, int $limit, int $offset): array
    {
        $this->db->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name,
                COUNT(DISTINCT ss.schedule_date) AS scheduled_days,
                MIN(ss.schedule_date) AS first_schedule_date,
                MAX(ss.schedule_date) AS last_schedule_date,
                GROUP_CONCAT(DISTINCT s.shift_code ORDER BY s.shift_code SEPARATOR ", ") AS shift_codes', false);
        $this->build_schedules_query($f, false);

        return $this->db
            ->group_by('e.id')
            ->group_by('e.employee_code')
            ->group_by('e.employee_name')
            ->group_by('d.division_name')
            ->order_by('e.employee_name', 'ASC')
            ->order_by('e.employee_code', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    public function get_schedule_employee_detail(int $employeeId, array $f): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $employeeQuery = $this->db->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.id', $employeeId);
        if (!empty($f['division_id'])) {
            $employeeQuery->where('e.division_id', (int)$f['division_id']);
        }
        $employee = $employeeQuery->limit(1)->get()->row_array();
        if (!$employee) {
            return null;
        }

        $this->build_schedules_query($f, true);
        $rows = $this->db
            ->where('e.id', $employeeId)
            ->order_by('ss.schedule_date', 'ASC')
            ->order_by('s.shift_code', 'ASC')
            ->get()
            ->result_array();

        return [
            'employee' => $employee,
            'rows' => $rows,
        ];
    }

    /**
     * Workforce recap intentionally counts distinct employees per date,
     * division, and shift so legacy duplicate schedule rows cannot inflate it.
     */
    public function get_schedule_recap(array $f): array
    {
        $this->db->select("ss.schedule_date,
                e.division_id,
                COALESCE(d.division_name, 'Tanpa Divisi') AS division_name,
                s.id AS shift_id,
                s.shift_code,
                s.shift_name,
                COUNT(DISTINCT ss.employee_id) AS employee_count,
                GROUP_CONCAT(DISTINCT CONCAT(e.employee_code, ' - ', e.employee_name) ORDER BY e.employee_name SEPARATOR ' | ') AS employee_names", false);
        $this->build_schedules_query($f, false);

        return $this->db
            ->group_by('ss.schedule_date')
            ->group_by('e.division_id')
            ->group_by('d.division_name')
            ->group_by('s.id')
            ->group_by('s.shift_code')
            ->group_by('s.shift_name')
            ->order_by('ss.schedule_date', 'ASC')
            ->order_by('d.division_name', 'ASC')
            ->order_by('s.shift_code', 'ASC')
            ->get()
            ->result_array();
    }

    public function list_schedules(array $f, int $limit, int $offset): array
    {
        $this->build_schedules_query($f, true);
        return $this->db
            ->order_by('ss.schedule_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_schedules_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ss.*, e.employee_code, e.employee_name, d.division_name, s.shift_code, s.shift_name');
        }

        $this->db->from('att_shift_schedule ss')
            ->join('org_employee e', 'e.id = ss.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_shift s', 's.id = ss.shift_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ss.schedule_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ss.schedule_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['shift_code'])) {
            $this->db->where('s.shift_code', $f['shift_code']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->group_end();
        }
    }

    public function get_shift_options(): array
    {
        return $this->db->select('id AS value, CONCAT(shift_code, \' - \', shift_name) AS label', false)
            ->from('att_shift')
            ->where('is_active', 1)
            ->order_by('shift_code', 'ASC')
            ->get()->result_array();
    }

    public function get_employee_options(?int $divisionId = null): array
    {
        $this->db->select('e.id AS value, CONCAT(e.employee_code, \' - \', e.employee_name) AS label', false)
            ->from('org_employee e')
            ->where('e.is_active', 1);
        if (!empty($divisionId)) {
            $this->db->where('e.division_id', (int)$divisionId);
        }
        return $this->db->order_by('e.employee_name', 'ASC')->get()->result_array();
    }

    private function normalize_meal_calendar_dates(string $dateStart, string $dateEnd): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
            $dateStart = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            $dateEnd = date('Y-m-t', strtotime($dateStart));
        }
        if ($dateEnd < $dateStart) {
            $dateEnd = $dateStart;
        }

        $startTs = strtotime($dateStart);
        $endTs = strtotime($dateEnd);
        if ($startTs <= 0 || $endTs <= 0) {
            $dateStart = date('Y-m-01');
            $dateEnd = date('Y-m-t');
            $startTs = strtotime($dateStart);
            $endTs = strtotime($dateEnd);
        }

        $maxDays = 62;
        if ((int)floor(($endTs - $startTs) / 86400) + 1 > $maxDays) {
            $endTs = strtotime('+' . ($maxDays - 1) . ' day', $startTs);
            $dateEnd = date('Y-m-d', $endTs);
        }

        return [$dateStart, $dateEnd];
    }

    private function apply_meal_calendar_filter_conditions(array $filters): void
    {
        $this->db->where('e.is_active', 1);

        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->group_end();
        }
    }

    /**
     * The meal calendar is a payment ledger, not a duplicate payroll report.
     * Only CUSTOM entitlements are payable through its batch workflow.
     */
    private function apply_custom_meal_calendar_mode_filter(string $alias = 'ad'): void
    {
        if (!$this->att_daily_has_field('meal_mode_snapshot')) {
            $this->db->where('1 = 0', null, false);
            return;
        }
        $this->db->where("COALESCE({$alias}.meal_mode_snapshot, 'MONTHLY') = 'CUSTOM'", null, false);
    }

    public function count_meal_calendar_employees(array $filters): int
    {
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates(
            (string)($filters['date_start'] ?? ''),
            (string)($filters['date_end'] ?? '')
        );

        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_daily ad', 'ad.employee_id = e.id AND ad.attendance_date >= ' . $this->db->escape($dateStart) . ' AND ad.attendance_date <= ' . $this->db->escape($dateEnd), 'inner', false);
        $this->apply_custom_meal_calendar_mode_filter('ad');
        $this->apply_meal_calendar_filter_conditions($filters);
        $row = $this->db->select('COUNT(DISTINCT e.id) AS c', false)->get()->row_array();
        return (int)($row['c'] ?? 0);
    }

    public function list_meal_calendar_employees(array $filters, int $limit, int $offset): array
    {
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates(
            (string)($filters['date_start'] ?? ''),
            (string)($filters['date_end'] ?? '')
        );

        $rows = $this->db->select("
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                MIN(COALESCE(ad.snapshot_meal_rate, 0)) AS meal_rate_min,
                MAX(COALESCE(ad.snapshot_meal_rate, 0)) AS meal_rate,
                COUNT(ad.id) AS day_rows,
                SUM(CASE WHEN COALESCE(ad.meal_amount,0) > 0 THEN 1 ELSE 0 END) AS meal_days,
                SUM(COALESCE(ad.meal_amount,0)) AS meal_total,
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM pay_meal_disbursement_line mdl
                            JOIN pay_meal_disbursement md ON md.id = mdl.disbursement_id
                            WHERE mdl.employee_id = ad.employee_id
                              AND mdl.attendance_date = ad.attendance_date
                              AND mdl.transfer_status = 'PAID'
                              AND md.status = 'PAID'
                        ) THEN COALESCE(ad.meal_amount,0)
                        ELSE 0
                    END
                ) AS paid_total
            ", false)
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_daily ad', 'ad.employee_id = e.id AND ad.attendance_date >= ' . $this->db->escape($dateStart) . ' AND ad.attendance_date <= ' . $this->db->escape($dateEnd), 'inner', false)
            ->group_by('e.id')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset);

        $this->apply_meal_calendar_filter_conditions($filters);
        $this->apply_custom_meal_calendar_mode_filter('ad');
        return $rows->get()->result_array();
    }

    public function meal_calendar_summary(array $filters): array
    {
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates(
            (string)($filters['date_start'] ?? ''),
            (string)($filters['date_end'] ?? '')
        );

        $row = $this->db->select("
                COUNT(DISTINCT e.id) AS employee_count,
                SUM(CASE WHEN COALESCE(ad.meal_amount,0) > 0 THEN 1 ELSE 0 END) AS meal_days,
                SUM(COALESCE(ad.meal_amount,0)) AS meal_total,
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM pay_meal_disbursement_line mdl
                            JOIN pay_meal_disbursement md ON md.id = mdl.disbursement_id
                            WHERE mdl.employee_id = ad.employee_id
                              AND mdl.attendance_date = ad.attendance_date
                              AND mdl.transfer_status = 'PAID'
                              AND md.status = 'PAID'
                        ) THEN COALESCE(ad.meal_amount,0)
                        ELSE 0
                    END
                ) AS paid_total
            ", false)
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_daily ad', 'ad.employee_id = e.id AND ad.attendance_date >= ' . $this->db->escape($dateStart) . ' AND ad.attendance_date <= ' . $this->db->escape($dateEnd), 'inner', false);
        $this->apply_custom_meal_calendar_mode_filter('ad');
        $this->apply_meal_calendar_filter_conditions($filters);
        $row = $row->get()->row_array() ?: [];

        $mealTotal = round((float)($row['meal_total'] ?? 0), 2);
        $paidTotal = round((float)($row['paid_total'] ?? 0), 2);
        return [
            'employee_count' => (int)($row['employee_count'] ?? 0),
            'meal_days' => (int)($row['meal_days'] ?? 0),
            'meal_total' => $mealTotal,
            'paid_total' => $paidTotal,
            'unpaid_total' => round(max(0, $mealTotal - $paidTotal), 2),
        ];
    }

    public function meal_calendar_daily_map(array $employeeIds, string $dateStart, string $dateEnd): array
    {
        if (empty($employeeIds)) {
            return [];
        }
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates($dateStart, $dateEnd);
        $cleanIds = array_values(array_filter(array_map('intval', $employeeIds), static function ($v) {
            return $v > 0;
        }));
        if (empty($cleanIds)) {
            return [];
        }

        $rows = $this->db->select("
                ad.employee_id,
                ad.attendance_date,
                ad.attendance_status,
                ad.checkin_at,
                ad.checkout_at,
                ad.meal_amount,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM pay_meal_disbursement_line mdl
                        JOIN pay_meal_disbursement md ON md.id = mdl.disbursement_id
                        WHERE mdl.employee_id = ad.employee_id
                          AND mdl.attendance_date = ad.attendance_date
                          AND mdl.transfer_status = 'PAID'
                          AND md.status = 'PAID'
                    ) THEN 1 ELSE 0
                END AS is_paid
            ", false)
            ->from('att_daily ad')
            ->where_in('ad.employee_id', $cleanIds)
            ->where('ad.attendance_date >=', $dateStart)
            ->where('ad.attendance_date <=', $dateEnd)
            ;
        $this->apply_custom_meal_calendar_mode_filter('ad');
        $rows = $rows->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $eid = (int)($row['employee_id'] ?? 0);
            $date = (string)($row['attendance_date'] ?? '');
            if ($eid <= 0 || $date === '') {
                continue;
            }
            if (!isset($map[$eid])) {
                $map[$eid] = [];
            }
            $map[$eid][$date] = $row;
        }
        return $map;
    }

    public function count_ph_assignments(array $filters): int
    {
        $this->build_ph_assignment_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_ph_assignments(array $filters, int $limit, int $offset): array
    {
        $this->build_ph_assignment_query($filters, true);
        return $this->db
            ->order_by('d.division_name', 'ASC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_ph_assignment_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                pe.id AS assignment_id,
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                COALESCE(pe.is_eligible, 0) AS is_eligible,
                pe.effective_date,
                pe.expiry_months_override,
                pe.notes,
                COALESCE((
                    SELECT SUM(CASE WHEN l.tx_type IN ('GRANT', 'ADJUST') THEN l.qty_days ELSE 0 END)
                    FROM att_employee_ph_ledger l
                    WHERE l.employee_id = e.id
                ), 0) AS grant_adjust_days,
                COALESCE((
                    SELECT SUM(CASE WHEN l.tx_type IN ('USE', 'EXPIRE') THEN l.qty_days ELSE 0 END)
                    FROM att_employee_ph_ledger l
                    WHERE l.employee_id = e.id
                ), 0) AS use_expire_days
            ", false);
        }

        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_ph_eligibility pe', 'pe.employee_id = e.id', 'left')
            ->where('e.is_active', 1);

        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }

        if (isset($filters['is_eligible']) && $filters['is_eligible'] !== '') {
            if ((string)$filters['is_eligible'] === '1') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 1', null, false);
            } elseif ((string)$filters['is_eligible'] === '0') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 0', null, false);
            }
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->group_end();
        }
    }

    /**
     * Eligibility changes must not leave a PH schedule that cannot be honored.
     * Past schedules remain historical and are deliberately out of this guard.
     */
    private function count_future_ph_schedules(int $employeeId, string $dateStart, string $dateEndExclusive = ''): int
    {
        if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
            return 0;
        }

        $query = $this->db->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'inner')
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date >=', $dateStart)
            ->where("UPPER(TRIM(COALESCE(s.shift_code, ''))) IN ('PH', 'PHB')", null, false);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEndExclusive)) {
            $query->where('ss.schedule_date <', $dateEndExclusive);
        }
        return (int)$query->count_all_results();
    }

    public function upsert_ph_assignment(array $payload, int $actorUserId): array
    {
        if (!$this->db->table_exists('att_ph_eligibility')) {
            return ['ok' => false, 'message' => 'Tabel assignment PH belum tersedia. Jalankan migration terbaru.'];
        }

        $employeeId = (int)($payload['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return ['ok' => false, 'message' => 'Pegawai wajib dipilih.'];
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }

        $effectiveDate = trim((string)($payload['effective_date'] ?? ''));
        if ($effectiveDate === '') {
            $effectiveDate = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
            return ['ok' => false, 'message' => 'Format tanggal efektif wajib YYYY-MM-DD.'];
        }

        $expiryOverride = $payload['expiry_months_override'] ?? null;
        if ($expiryOverride === '' || $expiryOverride === null) {
            $expiryOverride = null;
        } else {
            $expiryOverride = max(0, (int)$expiryOverride);
        }

        $isEligible = !empty($payload['is_eligible']) ? 1 : 0;
        $today = date('Y-m-d');
        $conflictingSchedules = 0;
        if ($isEligible !== 1) {
            $conflictingSchedules = $this->count_future_ph_schedules($employeeId, $today);
        } elseif ($effectiveDate > $today) {
            $conflictingSchedules = $this->count_future_ph_schedules($employeeId, $today, $effectiveDate);
        }
        if ($conflictingSchedules > 0) {
            return [
                'ok' => false,
                'message' => 'Hak PH belum dapat diubah karena masih ada ' . $conflictingSchedules . ' jadwal PH hari ini/masa depan yang akan menjadi tidak valid. Ubah atau hapus jadwal PH tersebut terlebih dahulu.',
            ];
        }

        $dbPayload = [
            'employee_id' => $employeeId,
            'is_eligible' => $isEligible,
            'effective_date' => $effectiveDate,
            'expiry_months_override' => $expiryOverride,
            'notes' => trim((string)($payload['notes'] ?? '')) ?: null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $sql = $this->db->insert_string('att_ph_eligibility', $dbPayload)
            . ' ON DUPLICATE KEY UPDATE'
            . ' is_eligible=VALUES(is_eligible),'
            . ' effective_date=VALUES(effective_date),'
            . ' expiry_months_override=VALUES(expiry_months_override),'
            . ' notes=VALUES(notes),'
            . ' created_by=VALUES(created_by),'
            . ' updated_at=VALUES(updated_at)';
        $this->db->query($sql);

        return ['ok' => true, 'message' => 'Assignment PH pegawai berhasil disimpan.'];
    }

    public function delete_ph_assignment(int $assignmentId): array
    {
        if (!$this->db->table_exists('att_ph_eligibility')) {
            return ['ok' => false, 'message' => 'Tabel assignment PH belum tersedia.'];
        }
        if ($assignmentId <= 0) {
            return ['ok' => false, 'message' => 'ID assignment tidak valid.'];
        }
        $row = $this->db->select('id, employee_id')
            ->from('att_ph_eligibility')
            ->where('id', $assignmentId)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Assignment PH tidak ditemukan.'];
        }
        $futureSchedules = $this->count_future_ph_schedules((int)($row['employee_id'] ?? 0), date('Y-m-d'));
        if ($futureSchedules > 0) {
            return [
                'ok' => false,
                'message' => 'Assignment PH tidak dapat dihapus karena masih ada ' . $futureSchedules . ' jadwal PH hari ini/masa depan. Hapus atau ubah jadwal tersebut terlebih dahulu.',
            ];
        }
        $this->db->where('id', $assignmentId)->delete('att_ph_eligibility');
        return ['ok' => true, 'message' => 'Assignment PH berhasil dihapus.'];
    }

    public function count_ph_ledger(array $filters): int
    {
        $this->build_ph_ledger_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_ph_ledger(array $filters, int $limit, int $offset): array
    {
        $this->build_ph_ledger_query($filters, true);
        return $this->db
            ->order_by('l.tx_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function sync_ph_expiry_ledger(string $asOfDate, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel ledger PH belum tersedia.', 'inserted' => 0, 'scanned' => 0];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
            return ['ok' => false, 'message' => 'Format tanggal as-of tidak valid.', 'inserted' => 0, 'scanned' => 0];
        }

        $candidates = $this->db->select('DISTINCT employee_id', false)
            ->from('att_employee_ph_ledger')
            ->where('tx_type', 'GRANT')
            ->where('expired_at IS NOT NULL', null, false)
            // expired_at means "berlaku sampai". A lot stays usable on this
            // exact date and is expired when the following date is processed.
            ->where('expired_at <', $asOfDate)
            ->get()->result_array();

        $inserted = 0;
        $scanned = 0;
        foreach ($candidates as $candidate) {
            $employeeId = (int)($candidate['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            $wrongGrantIds = $this->legacy_wrong_auto_ph_grant_id_set($employeeId, $asOfDate);
            $rows = $this->db->select('id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id')
                ->from('att_employee_ph_ledger')
                ->where('employee_id', $employeeId)
                ->where('tx_date <=', $asOfDate)
                ->order_by('tx_date', 'ASC')
                ->order_by('id', 'ASC')
                ->get()->result_array();
            $rows = $this->filter_legacy_wrong_auto_ph_ledger_rows($rows, $wrongGrantIds);
            if (empty($rows)) {
                continue;
            }

            $lots = [];
            $lotById = [];
            $consumeFromLots = static function (array &$lots, float $qty): void {
                $remain = max(0.0, $qty);
                if ($remain <= 0) {
                    return;
                }
                foreach ($lots as &$lot) {
                    if ($remain <= 0) {
                        break;
                    }
                    $available = max(0.0, (float)($lot['remaining'] ?? 0));
                    if ($available <= 0) {
                        continue;
                    }
                    $used = min($available, $remain);
                    $lot['remaining'] = round($available - $used, 2);
                    $remain = round($remain - $used, 2);
                }
                unset($lot);
            };

            foreach ($rows as $row) {
                $txType = strtoupper((string)($row['tx_type'] ?? ''));
                $qty = round((float)($row['qty_days'] ?? 0), 2);
                if (abs($qty) <= 0.0001) {
                    continue;
                }
                if ($txType === 'GRANT') {
                    if ($qty < 0) {
                        continue;
                    }
                    $lot = [
                        'ledger_id' => (int)$row['id'],
                        'remaining' => $qty,
                        'expired_at' => (string)($row['expired_at'] ?? ''),
                        'tx_date' => (string)($row['tx_date'] ?? ''),
                    ];
                    $lots[] = $lot;
                    $lotById[(int)$row['id']] = count($lots) - 1;
                    continue;
                }

                // Migration may create a debit adjustment to settle an
                // already-approved historic PH use. It is never exposed as
                // a negative value in the operator form.
                if ($txType === 'ADJUST') {
                    if ($qty > 0) {
                        $lots[] = [
                            'ledger_id' => (int)$row['id'],
                            'remaining' => $qty,
                            'expired_at' => '',
                            'tx_date' => (string)($row['tx_date'] ?? ''),
                        ];
                        $lotById[(int)$row['id']] = count($lots) - 1;
                    } else {
                        $consumeFromLots($lots, abs($qty));
                    }
                    continue;
                }

                if ($txType === 'USE') {
                    $consumeFromLots($lots, $qty);
                    continue;
                }

                if ($txType === 'EXPIRE') {
                    $refTable = (string)($row['ref_table'] ?? '');
                    $refId = (int)($row['ref_id'] ?? 0);
                    if ($refTable === 'att_employee_ph_ledger' && $refId > 0 && isset($lotById[$refId])) {
                        $idx = (int)$lotById[$refId];
                        $available = max(0.0, (float)($lots[$idx]['remaining'] ?? 0));
                        $lots[$idx]['remaining'] = round(max(0.0, $available - $qty), 2);
                    } else {
                        $consumeFromLots($lots, $qty);
                    }
                }
            }

            foreach ($lots as $lot) {
                $scanned++;
                $lotId = (int)($lot['ledger_id'] ?? 0);
                $remaining = round((float)($lot['remaining'] ?? 0), 2);
                $expiredAt = (string)($lot['expired_at'] ?? '');
                if ($lotId <= 0 || $remaining <= 0 || $expiredAt === '' || $expiredAt >= $asOfDate) {
                    continue;
                }

                $created = $this->insert_ph_grant_ledger([
                    'employee_id' => $employeeId,
                    'tx_date' => date('Y-m-d', strtotime($expiredAt . ' +1 day')),
                    'tx_type' => 'EXPIRE',
                    'qty_days' => $remaining,
                    'expired_at' => null,
                    'ref_table' => 'att_employee_ph_ledger',
                    'ref_id' => $lotId,
                    'entry_mode' => 'AUTO',
                    'notes' => 'Auto expire PH setelah tanggal berlaku berakhir.',
                    'created_by' => $actorUserId > 0 ? $actorUserId : null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($created) {
                    $inserted++;
                }
            }
        }

        return [
            'ok' => true,
            'message' => 'Sinkron auto-expire PH selesai.',
            'inserted' => $inserted,
            'scanned' => $scanned,
        ];
    }

    private function ph_validate_rows_integrity(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string)($a['tx_date'] ?? ''), (string)($b['tx_date'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        $lots = [];
        $lotByLedgerId = [];
        $eps = 0.0001;

        $sumAvailable = static function (array $lotsRef, string $date, callable $matcher): float {
            $sum = 0.0;
            foreach ($lotsRef as $lot) {
                $remaining = (float)($lot['remaining'] ?? 0);
                if ($remaining <= 0) {
                    continue;
                }
                if ($matcher($lot, $date)) {
                    $sum += $remaining;
                }
            }
            return round($sum, 2);
        };

        $consume = static function (array &$lotsRef, float $qty, string $date, callable $matcher): float {
            $remain = round(max(0.0, $qty), 2);
            if ($remain <= 0) {
                return 0.0;
            }
            foreach ($lotsRef as &$lot) {
                if ($remain <= 0) {
                    break;
                }
                $available = round(max(0.0, (float)($lot['remaining'] ?? 0)), 2);
                if ($available <= 0) {
                    continue;
                }
                if (!$matcher($lot, $date)) {
                    continue;
                }
                $used = min($available, $remain);
                $lot['remaining'] = round($available - $used, 2);
                $remain = round($remain - $used, 2);
            }
            unset($lot);
            return $remain;
        };

        foreach ($rows as $row) {
            $ledgerId = (int)($row['id'] ?? 0);
            $txDate = (string)($row['tx_date'] ?? '');
            $txType = strtoupper((string)($row['tx_type'] ?? ''));
            $qty = round((float)($row['qty_days'] ?? 0), 2);
            if (abs($qty) <= $eps || $txDate === '' || $txType === '') {
                continue;
            }

            if ($txType === 'GRANT') {
                if ($qty < 0) {
                    return [
                        'ok' => false,
                        'message' => 'Mutasi GRANT tidak boleh bernilai negatif pada ' . $txDate . '.',
                    ];
                }
                $lots[] = [
                    'ledger_id' => $ledgerId,
                    'remaining' => $qty,
                    'expired_at' => (string)($row['expired_at'] ?? ''),
                    'tx_date' => $txDate,
                    'tx_type' => 'GRANT',
                ];
                if ($ledgerId > 0) {
                    $lotByLedgerId[$ledgerId] = count($lots) - 1;
                }
                continue;
            }

            if ($txType === 'ADJUST') {
                if ($qty > 0) {
                    $lots[] = [
                        'ledger_id' => $ledgerId,
                        'remaining' => $qty,
                        'expired_at' => '',
                        'tx_date' => $txDate,
                        'tx_type' => 'ADJUST',
                    ];
                    if ($ledgerId > 0) {
                        $lotByLedgerId[$ledgerId] = count($lots) - 1;
                    }
                    continue;
                }

                $debitQty = abs($qty);
                $activeAvailable = $sumAvailable(
                    $lots,
                    $txDate,
                    static function (array $lot, string $date): bool {
                        $exp = (string)($lot['expired_at'] ?? '');
                        return ($exp === '' || $exp >= $date);
                    }
                );
                if ($debitQty > ($activeAvailable + $eps)) {
                    return [
                        'ok' => false,
                        'message' => 'Koreksi debit PH melebihi saldo aktif pada ' . $txDate . '.',
                    ];
                }
                $remain = $consume(
                    $lots,
                    $debitQty,
                    $txDate,
                    static function (array $lot, string $date): bool {
                        $exp = (string)($lot['expired_at'] ?? '');
                        return ($exp === '' || $exp >= $date);
                    }
                );
                if ($remain > $eps) {
                    return [
                        'ok' => false,
                        'message' => 'Koreksi debit PH gagal dialokasikan ke lot aktif pada ' . $txDate . '.',
                    ];
                }
                continue;
            }

            if ($qty < 0) {
                return [
                    'ok' => false,
                    'message' => 'Mutasi ' . $txType . ' tidak boleh bernilai negatif pada ' . $txDate . '.',
                ];
            }

            if ($txType === 'USE') {
                $activeAvailable = $sumAvailable(
                    $lots,
                    $txDate,
                    static function (array $lot, string $date): bool {
                        $exp = (string)($lot['expired_at'] ?? '');
                        return ($exp === '' || $exp >= $date);
                    }
                );
                if ($qty > ($activeAvailable + $eps)) {
                    return [
                        'ok' => false,
                        'message' => 'Mutasi USE melebihi saldo PH aktif pada ' . $txDate . '.',
                    ];
                }
                $remain = $consume(
                    $lots,
                    $qty,
                    $txDate,
                    static function (array $lot, string $date): bool {
                        $exp = (string)($lot['expired_at'] ?? '');
                        return ($exp === '' || $exp >= $date);
                    }
                );
                if ($remain > $eps) {
                    return [
                        'ok' => false,
                        'message' => 'Mutasi USE gagal dialokasikan ke lot aktif pada ' . $txDate . '.',
                    ];
                }
                continue;
            }

            if ($txType === 'EXPIRE') {
                $refTable = (string)($row['ref_table'] ?? '');
                $refId = (int)($row['ref_id'] ?? 0);
                if ($refTable === 'att_employee_ph_ledger' && $refId > 0 && isset($lotByLedgerId[$refId])) {
                    $idx = (int)$lotByLedgerId[$refId];
                    $available = round(max(0.0, (float)($lots[$idx]['remaining'] ?? 0)), 2);
                    if ($qty > ($available + $eps)) {
                        return [
                            'ok' => false,
                            'message' => 'Mutasi EXPIRE melebihi sisa lot referensi pada ' . $txDate . '.',
                        ];
                    }
                    $lots[$idx]['remaining'] = round(max(0.0, $available - $qty), 2);
                } else {
                    $expirableAvailable = $sumAvailable(
                        $lots,
                        $txDate,
                        static function (array $lot, string $date): bool {
                            $exp = (string)($lot['expired_at'] ?? '');
                            return ($exp !== '' && $exp <= $date);
                        }
                    );
                    if ($qty > ($expirableAvailable + $eps)) {
                        return [
                            'ok' => false,
                            'message' => 'Mutasi EXPIRE melebihi jatah yang memang sudah expired pada ' . $txDate . '.',
                        ];
                    }
                    $remain = $consume(
                        $lots,
                        $qty,
                        $txDate,
                        static function (array $lot, string $date): bool {
                            $exp = (string)($lot['expired_at'] ?? '');
                            return ($exp !== '' && $exp <= $date);
                        }
                    );
                    if ($remain > $eps) {
                        return [
                            'ok' => false,
                            'message' => 'Mutasi EXPIRE gagal dialokasikan ke lot expired pada ' . $txDate . '.',
                        ];
                    }
                }
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    private function ph_validate_upsert_transaction(
        int $employeeId,
        string $txDate,
        string $txType,
        float $qtyDays,
        int $excludeId = 0,
        int $candidateId = 0,
        ?string $expiredAt = null,
        ?string $refTable = null,
        ?int $refId = null
    ): array {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel ledger PH belum tersedia.'];
        }
        $rows = $this->db->select('id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id')
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->order_by('tx_date', 'ASC')
            ->order_by('id', 'ASC')
            ->get()->result_array();

        if ($excludeId > 0) {
            $rows = array_values(array_filter($rows, static function (array $r) use ($excludeId): bool {
                return (int)($r['id'] ?? 0) !== $excludeId;
            }));
        }

        $maxId = 0;
        foreach ($rows as $r) {
            $maxId = max($maxId, (int)($r['id'] ?? 0));
        }
        if ($candidateId <= 0) {
            $candidateId = $maxId + 1;
        }

        $rows[] = [
            'id' => $candidateId,
            'tx_date' => $txDate,
            'tx_type' => $txType,
            'qty_days' => round($qtyDays, 2),
            'expired_at' => $expiredAt,
            'ref_table' => $refTable,
            'ref_id' => $refId,
        ];

        return $this->ph_validate_rows_integrity($rows);
    }

    private function ph_validate_delete_transaction(int $employeeId, int $deleteId): array
    {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel ledger PH belum tersedia.'];
        }
        $rows = $this->db->select('id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id')
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->order_by('tx_date', 'ASC')
            ->order_by('id', 'ASC')
            ->get()->result_array();
        $rows = array_values(array_filter($rows, static function (array $r) use ($deleteId): bool {
            return (int)($r['id'] ?? 0) !== $deleteId;
        }));
        return $this->ph_validate_rows_integrity($rows);
    }

    private function build_ph_ledger_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                l.*,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                u.username AS created_by_username
            ", false);
        }

        $this->db->from('att_employee_ph_ledger l')
            ->join('org_employee e', 'e.id = l.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('auth_user u', 'u.id = l.created_by', 'left');

        if (!empty($filters['employee_id'])) {
            $this->db->where('l.employee_id', (int)$filters['employee_id']);
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
        if (!empty($filters['date_start'])) {
            $this->db->where('l.tx_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('l.tx_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('l.notes', $q)
                ->group_end();
        }
    }

    public function save_ph_ledger_entry(array $payload, int $actorUserId): array
    {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel ledger PH belum tersedia. Jalankan migration terbaru.'];
        }

        $employeeId = (int)($payload['employee_id'] ?? 0);
        $txType = strtoupper(trim((string)($payload['tx_type'] ?? 'ADJUST')));
        $qtyDays = round((float)($payload['qty_days'] ?? 0), 2);
        $txDate = trim((string)($payload['tx_date'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $qtyDays <= 0 || $txDate === '') {
            return ['ok' => false, 'message' => 'Pegawai, qty hari, dan tanggal transaksi wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            return ['ok' => false, 'message' => 'Format tanggal transaksi wajib YYYY-MM-DD.'];
        }
        if (!in_array($txType, ['GRANT', 'USE', 'EXPIRE', 'ADJUST'], true)) {
            $txType = 'ADJUST';
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }

        if (in_array($txType, ['USE', 'EXPIRE'], true)) {
            $validate = $this->ph_validate_upsert_transaction(
                $employeeId,
                $txDate,
                $txType,
                $qtyDays
            );
            if (empty($validate['ok'])) {
                return $validate;
            }
        }

        $this->db->insert('att_employee_ph_ledger', [
            'employee_id' => $employeeId,
            'tx_date' => $txDate,
            'tx_type' => $txType,
            'qty_days' => $qtyDays,
            'entry_mode' => 'MANUAL',
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => 'Mutasi PH manual berhasil ditambahkan.'];
    }

    public function get_ph_ledger_by_id(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('att_employee_ph_ledger')) {
            return null;
        }
        return $this->db->from('att_employee_ph_ledger')
            ->where('id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;
    }

    public function update_ph_ledger_entry(int $id, array $payload, int $actorUserId, bool $isSuperadmin = false): array
    {
        $row = $this->get_ph_ledger_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Mutasi PH tidak ditemukan.'];
        }
        if (strtoupper((string)($row['entry_mode'] ?? 'AUTO')) !== 'MANUAL' && !$isSuperadmin) {
            return ['ok' => false, 'message' => 'Mutasi otomatis tidak bisa diedit.'];
        }

        $employeeId = (int)($payload['employee_id'] ?? 0);
        $txType = strtoupper(trim((string)($payload['tx_type'] ?? 'ADJUST')));
        $qtyDays = round((float)($payload['qty_days'] ?? 0), 2);
        $txDate = trim((string)($payload['tx_date'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $qtyDays <= 0 || $txDate === '') {
            return ['ok' => false, 'message' => 'Pegawai, qty hari, dan tanggal transaksi wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            return ['ok' => false, 'message' => 'Format tanggal transaksi wajib YYYY-MM-DD.'];
        }
        if (!in_array($txType, ['GRANT', 'USE', 'EXPIRE', 'ADJUST'], true)) {
            $txType = 'ADJUST';
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }

        if (in_array($txType, ['USE', 'EXPIRE'], true)) {
            $validate = $this->ph_validate_upsert_transaction(
                $employeeId,
                $txDate,
                $txType,
                $qtyDays,
                $id,
                $id,
                (string)($row['expired_at'] ?? ''),
                (string)($row['ref_table'] ?? ''),
                isset($row['ref_id']) ? (int)$row['ref_id'] : null
            );
            if (empty($validate['ok'])) {
                return $validate;
            }
        }

        $updatePayload = [
            'employee_id' => $employeeId,
            'tx_date' => $txDate,
            'tx_type' => $txType,
            'qty_days' => $qtyDays,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($isSuperadmin) {
            $updatePayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $this->db->where('id', $id)->update('att_employee_ph_ledger', $updatePayload);
        return ['ok' => true, 'message' => 'Mutasi PH berhasil diperbarui.'];
    }

    public function delete_ph_ledger_entry(int $id, bool $isSuperadmin = false): array
    {
        $row = $this->get_ph_ledger_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Mutasi PH tidak ditemukan.'];
        }
        if (strtoupper((string)($row['entry_mode'] ?? 'AUTO')) !== 'MANUAL' && !$isSuperadmin) {
            return ['ok' => false, 'message' => 'Mutasi otomatis tidak bisa dihapus.'];
        }
        $employeeId = (int)($row['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $validate = $this->ph_validate_delete_transaction($employeeId, $id);
            if (empty($validate['ok'])) {
                return $validate;
            }
        }
        $this->db->where('id', $id)->delete('att_employee_ph_ledger');
        return ['ok' => true, 'message' => 'Mutasi PH berhasil dihapus.'];
    }

    public function ph_ledger_summary(array $filters): array
    {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return [
                'grant' => 0.0,
                'use' => 0.0,
                'expire' => 0.0,
                'adjust' => 0.0,
                'balance' => 0.0,
            ];
        }

        $this->db->select("
            COALESCE(SUM(CASE WHEN l.tx_type = 'GRANT' THEN l.qty_days ELSE 0 END),0) AS grant_days,
            COALESCE(SUM(CASE WHEN l.tx_type = 'USE' THEN l.qty_days ELSE 0 END),0) AS use_days,
            COALESCE(SUM(CASE WHEN l.tx_type = 'EXPIRE' THEN l.qty_days ELSE 0 END),0) AS expire_days,
            COALESCE(SUM(CASE WHEN l.tx_type = 'ADJUST' THEN l.qty_days ELSE 0 END),0) AS adjust_days
        ", false)->from('att_employee_ph_ledger l');

        if (!empty($filters['employee_id'])) {
            $this->db->where('l.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('l.tx_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('l.tx_date <=', (string)$filters['date_end']);
        }
        $row = $this->db->get()->row_array() ?: [];

        $grant = (float)($row['grant_days'] ?? 0);
        $use = (float)($row['use_days'] ?? 0);
        $expire = (float)($row['expire_days'] ?? 0);
        $adjust = (float)($row['adjust_days'] ?? 0);
        return [
            'grant' => round($grant, 2),
            'use' => round($use, 2),
            'expire' => round($expire, 2),
            'adjust' => round($adjust, 2),
            'balance' => round(($grant + $adjust) - ($use + $expire), 2),
        ];
    }

    public function count_ph_recap(array $filters): int
    {
        $this->build_ph_recap_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_ph_recap(array $filters, int $limit, int $offset): array
    {
        $this->build_ph_recap_query($filters, true);
        return $this->db
            ->order_by('d.division_name', 'ASC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_ph_recap_query(array $filters, bool $withSelect): void
    {
        $month = trim((string)($filters['month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        if ($withSelect) {
            $this->db->select("
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                COALESCE(pe.is_eligible, 0) AS is_eligible,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='GRANT' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS grant_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='USE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS use_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='EXPIRE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS expire_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='ADJUST' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS adjust_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='GRANT' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id AND l.tx_date >= " . $this->db->escape($monthStart) . " AND l.tx_date <= " . $this->db->escape($monthEnd) . "),0) AS grant_month,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='USE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id AND l.tx_date >= " . $this->db->escape($monthStart) . " AND l.tx_date <= " . $this->db->escape($monthEnd) . "),0) AS use_month,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='EXPIRE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id AND l.tx_date >= " . $this->db->escape($monthStart) . " AND l.tx_date <= " . $this->db->escape($monthEnd) . "),0) AS expire_month
            ", false);
        }

        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_ph_eligibility pe', 'pe.employee_id = e.id', 'left')
            ->where('e.is_active', 1);

        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }
        if (isset($filters['is_eligible']) && $filters['is_eligible'] !== '') {
            if ((string)$filters['is_eligible'] === '1') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 1', null, false);
            } elseif ((string)$filters['is_eligible'] === '0') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 0', null, false);
            }
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->group_end();
        }
    }

    public function sync_ph_grants_from_attendance(string $dateStart, string $dateEnd, int $actorUserId, bool $allowHistorical = false): array
    {
        if (!$this->db->table_exists('att_ph_eligibility') || !$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel PH belum lengkap. Jalankan migration terbaru.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd) || $dateEnd < $dateStart) {
            return ['ok' => false, 'message' => 'Rentang tanggal PH tidak valid.'];
        }
        $currentMonthStart = date('Y-m-01');
        if (!$allowHistorical && $dateStart < $currentMonthStart) {
            return [
                'ok' => false,
                'message' => 'Sinkron massal hanya untuk bulan berjalan agar riwayat PH/payroll lama tidak berubah tanpa rekonsiliasi. Untuk data lama, gunakan laporan audit PH dan koreksi yang disetujui.',
            ];
        }

        $policy = $this->get_active_policy();
        $rows = $this->list_ph_grant_attendance_candidates($dateStart, $dateEnd);
        $inserted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $result = $this->create_ph_grant_from_attendance_row($row, $policy, $actorUserId);
            if (!empty($result['created'])) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'message' => 'Sinkron grant PH selesai.',
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total_scanned' => count($rows),
        ];
    }

    public function sync_ph_grant_for_employee_date(int $employeeId, string $date, int $actorUserId = 0): array
    {
        if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Pegawai atau tanggal PH tidak valid.'];
        }
        if (!$this->db->table_exists('att_ph_eligibility') || !$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Tabel PH belum lengkap.'];
        }

        $policy = $this->get_active_policy();
        $rows = $this->list_ph_grant_attendance_candidates($date, $date, $employeeId);
        if (empty($rows)) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Tidak memenuhi syarat grant PH.'];
        }

        $inserted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $result = $this->create_ph_grant_from_attendance_row($row, $policy, $actorUserId);
            if (!empty($result['created'])) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'message' => $inserted > 0 ? 'Grant PH dibuat.' : 'Grant PH sudah ada atau presensi belum memenuhi syarat.',
        ];
    }

    private function get_ph_use_attendance_candidate(int $employeeId, string $date): ?array
    {
        $row = $this->db->query("
            SELECT
                ad.id AS daily_id,
                ad.employee_id,
                ad.attendance_date,
                ad.attendance_status,
                ss.id AS schedule_id,
                ss.shift_id AS scheduled_shift_id,
                scheduled_shift.shift_code AS scheduled_shift_code,
                ad.shift_id AS daily_shift_id,
                daily_shift.shift_code AS daily_shift_code,
                pe.is_eligible,
                pe.effective_date
            FROM att_daily ad
            LEFT JOIN att_shift_schedule ss
              ON ss.employee_id = ad.employee_id
             AND ss.schedule_date = ad.attendance_date
            LEFT JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
            JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
            LEFT JOIN att_ph_eligibility pe ON pe.employee_id = ad.employee_id
            WHERE ad.employee_id = ?
              AND ad.attendance_date = ?
              AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
              AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
            ORDER BY ad.id DESC
            LIMIT 1
        ", [$employeeId, $date])->row_array();
        return $row ?: null;
    }

    /**
     * Creates the one PH USE mutation tied to a real attendance row. The
     * ledger reference makes reloads, manual attendance, and approvals idempotent.
     */
    public function sync_ph_use_for_employee_date(int $employeeId, string $date, int $actorUserId = 0): array
    {
        if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Pegawai atau tanggal penggunaan PH tidak valid.'];
        }
        if (!$this->db->table_exists('att_ph_eligibility') || !$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Tabel PH belum lengkap.'];
        }

        $candidate = $this->get_ph_use_attendance_candidate($employeeId, $date);
        if (!$candidate) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Tidak ada kehadiran final yang memakai shift PH.'];
        }
        if ((int)($candidate['is_eligible'] ?? 0) !== 1 || (string)($candidate['effective_date'] ?? '') > $date) {
            return ['ok' => false, 'inserted' => 0, 'skipped' => 1, 'message' => 'Pegawai belum memiliki hak PH aktif untuk memakai shift PH ini.'];
        }

        $dailyId = (int)($candidate['daily_id'] ?? 0);
        $exists = $this->db->select('id')
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->where('tx_type', 'USE')
            ->where('ref_table', 'att_daily')
            ->where('ref_id', $dailyId)
            ->limit(1)
            ->get()->row_array();
        if ($exists) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Penggunaan PH sudah tercatat.'];
        }

        $this->sync_ph_expiry_ledger(date('Y-m-d'), $actorUserId);
        $hasMatchingPhSchedule = (int)($candidate['schedule_id'] ?? 0) > 0
            && $this->is_ph_shift_code((string)($candidate['scheduled_shift_code'] ?? ''));
        $capacity = $this->validate_ph_schedule_capacity(
            $employeeId,
            $date,
            (int)($candidate['daily_shift_id'] ?? $candidate['scheduled_shift_id'] ?? 0),
            0,
            $hasMatchingPhSchedule
        );
        if (empty($capacity['ok'])) {
            return [
                'ok' => false,
                'inserted' => 0,
                'skipped' => 1,
                'message' => (string)($capacity['message'] ?? 'Saldo PH tidak cukup.'),
            ];
        }

        $now = date('Y-m-d H:i:s');
        $created = $this->insert_ph_grant_ledger([
            'employee_id' => $employeeId,
            'tx_date' => $date,
            'tx_type' => 'USE',
            'qty_days' => 1,
            'expired_at' => null,
            'ref_table' => 'att_daily',
            'ref_id' => $dailyId,
            'entry_mode' => 'AUTO',
            'notes' => 'Auto use: kehadiran pada jadwal shift PH.',
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$created) {
            $exists = $this->db->select('id')
                ->from('att_employee_ph_ledger')
                ->where('employee_id', $employeeId)
                ->where('tx_type', 'USE')
                ->where('ref_table', 'att_daily')
                ->where('ref_id', $dailyId)
                ->limit(1)
                ->get()->row_array();
            if ($exists) {
                return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Penggunaan PH sudah tercatat.'];
            }
            return ['ok' => false, 'inserted' => 0, 'skipped' => 0, 'message' => 'Gagal mencatat penggunaan PH.'];
        }

        return ['ok' => true, 'inserted' => 1, 'skipped' => 0, 'message' => 'Penggunaan PH dicatat.'];
    }
    public function count_overtime_entries(array $f): int
    {
        $this->build_overtime_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function get_overtime_entry_summary(array $f): array
    {
        $this->build_overtime_query($f, false);
        $row = $this->db
            ->select(
                "COUNT(oe.id) AS total_entries,
                COALESCE(SUM(oe.overtime_hours), 0) AS total_hours,
                COALESCE(SUM(oe.total_overtime_pay), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN oe.status = 'APPROVED' THEN 1 ELSE 0 END), 0) AS approved_entries,
                COALESCE(SUM(CASE WHEN oe.status = 'PENDING' THEN 1 ELSE 0 END), 0) AS pending_entries,
                COALESCE(SUM(CASE WHEN oe.status = 'REJECTED' THEN 1 ELSE 0 END), 0) AS rejected_entries,
                COALESCE(SUM(CASE WHEN oe.status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_entries,
                COALESCE(SUM(CASE WHEN oe.status = 'APPROVED' THEN oe.total_overtime_pay ELSE 0 END), 0) AS approved_amount",
                false
            )
            ->get()
            ->row_array();

        return [
            'total_entries' => (int)($row['total_entries'] ?? 0),
            'total_hours' => round((float)($row['total_hours'] ?? 0), 2),
            'total_amount' => round((float)($row['total_amount'] ?? 0), 2),
            'approved_entries' => (int)($row['approved_entries'] ?? 0),
            'pending_entries' => (int)($row['pending_entries'] ?? 0),
            'rejected_entries' => (int)($row['rejected_entries'] ?? 0),
            'cancelled_entries' => (int)($row['cancelled_entries'] ?? 0),
            'approved_amount' => round((float)($row['approved_amount'] ?? 0), 2),
        ];
    }

    public function list_overtime_entries(array $f, int $limit, int $offset): array
    {
        $this->build_overtime_query($f, true);
        return $this->db
            ->order_by('oe.overtime_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_overtime_query(array $f, bool $withSelect): void
    {
        $hasStandardSchema = $this->has_overtime_standard_schema();
        if ($withSelect) {
            $select = 'oe.*, e.employee_code, e.employee_name, d.division_name, p.position_name, ap.employee_name AS approved_by_username, ap.employee_name AS approved_by_name, ap.employee_code AS approved_by_employee_code';
            if ($hasStandardSchema) {
                $select .= ', os.standard_name AS overtime_standard_name, os.hourly_rate AS overtime_standard_rate';
            }
            $this->db->select($select);
        }
        $this->db->from('att_overtime_entry oe')
            ->join('org_employee e', 'e.id = oe.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('org_employee ap', 'ap.id = oe.approved_by', 'left');
        if ($hasStandardSchema) {
            $this->db->join('att_overtime_standard os', 'os.id = oe.overtime_standard_id', 'left');
        }

        if (!empty($f['date_start'])) {
            $this->db->where('oe.overtime_date >=', (string)$f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('oe.overtime_date <=', (string)$f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['employee_id'])) {
            $this->db->where('oe.employee_id', (int)$f['employee_id']);
        }
        if (!empty($f['status'])) {
            $this->db->where('oe.status', strtoupper((string)$f['status']));
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('oe.notes', $q)
                ->group_end();
        }
    }

    public function get_overtime_entry_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $hasStandardSchema = $this->has_overtime_standard_schema();
        if ($hasStandardSchema) {
            $this->db->select('oe.*, os.standard_name AS overtime_standard_name, os.hourly_rate AS overtime_standard_rate, ap.employee_name AS approved_by_name, ap.employee_code AS approved_by_employee_code')
                ->from('att_overtime_entry oe')
                ->join('att_overtime_standard os', 'os.id = oe.overtime_standard_id', 'left')
                ->join('org_employee ap', 'ap.id = oe.approved_by', 'left')
                ->where('oe.id', $id)
                ->limit(1);
            return $this->db->get()->row_array() ?: null;
        }
        return $this->db->select('oe.*, ap.employee_name AS approved_by_name, ap.employee_code AS approved_by_employee_code')
            ->from('att_overtime_entry oe')
            ->join('org_employee ap', 'ap.id = oe.approved_by', 'left')
            ->where('oe.id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function get_overtime_standard_options(): array
    {
        if (!$this->has_overtime_standard_schema()) {
            return [];
        }
        return $this->db->select('id AS value, standard_code, standard_name, hourly_rate')
            ->from('att_overtime_standard')
            ->where('is_active', 1)
            ->order_by('hourly_rate', 'ASC')
            ->order_by('standard_name', 'ASC')
            ->get()->result_array();
    }

    /**
     * AUTO overtime has no per-entry selector, so it must read the one
     * standard explicitly selected in Attendance Settings. Contract rates are
     * deliberately not a fallback here.
     */
    public function get_policy_overtime_standard(array $policy = []): ?array
    {
        if (!$this->db->table_exists('att_overtime_standard')) {
            return null;
        }
        if (empty($policy)) {
            $policy = $this->get_active_policy();
        }

        $standardId = (int)($policy['default_overtime_standard_id'] ?? 0);
        if ($standardId <= 0) {
            return null;
        }

        $row = $this->db->select('id, standard_code, standard_name, hourly_rate')
            ->from('att_overtime_standard')
            ->where('id', $standardId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function get_policy_overtime_rate(array $policy = []): float
    {
        $standard = $this->get_policy_overtime_standard($policy);
        return round((float)($standard['hourly_rate'] ?? 0), 2);
    }

    public function is_active_overtime_standard(int $standardId): bool
    {
        if ($standardId <= 0 || !$this->db->table_exists('att_overtime_standard')) {
            return false;
        }

        return $this->db->from('att_overtime_standard')
            ->where('id', $standardId)
            ->where('is_active', 1)
            ->count_all_results() > 0;
    }

    public function save_overtime_entry(array $payload, int $actorEmployeeId = 0): array
    {
        $id = (int)($payload['id'] ?? 0);
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $overtimeStandardId = (int)($payload['overtime_standard_id'] ?? 0);
        $overtimeDate = trim((string)($payload['overtime_date'] ?? ''));
        $startTime = trim((string)($payload['start_time'] ?? ''));
        $endTime = trim((string)($payload['end_time'] ?? ''));
        $status = strtoupper(trim((string)($payload['status'] ?? 'PENDING')));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $overtimeDate === '' || $startTime === '' || $endTime === '') {
            return ['ok' => false, 'message' => 'Pegawai, tanggal, jam mulai, dan jam selesai wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $overtimeDate)) {
            return ['ok' => false, 'message' => 'Tanggal lembur tidak valid.'];
        }
        $lockMessage = $this->immutable_period_guard_message($overtimeDate);
        if ($lockMessage !== '') {
            return ['ok' => false, 'message' => $lockMessage];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            return ['ok' => false, 'message' => 'Format jam lembur harus HH:MM.'];
        }
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            $status = 'PENDING';
        }
        if ($status === 'APPROVED' && $actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'Akun login belum tertaut ke data pegawai, sehingga tidak bisa menjadi approver lembur APPROVED. Hubungkan user ke pegawai atau simpan sebagai PENDING dulu.'];
        }

        $employee = $this->db->select('id')->from('org_employee')->where('id', $employeeId)->where('is_active', 1)->limit(1)->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak ditemukan atau nonaktif.'];
        }

        if ($status === 'APPROVED') {
            $coverage = $this->validate_daily_compensation_contract_coverage($employeeId, $overtimeDate);
            if (empty($coverage['ok'])) {
                return ['ok' => false, 'message' => 'Lembur APPROVED ditolak: ' . (string)($coverage['message'] ?? 'Kontrak kompensasi tidak tersedia.')];
            }
        }

        $startAt = strtotime($overtimeDate . ' ' . $startTime . ':00');
        $endAt = strtotime($overtimeDate . ' ' . $endTime . ':00');
        if (!$startAt || !$endAt) {
            return ['ok' => false, 'message' => 'Jam lembur tidak valid.'];
        }
        if ($endAt <= $startAt) {
            $endAt = strtotime('+1 day', $endAt);
        }
        if ($endAt <= $startAt) {
            return ['ok' => false, 'message' => 'Jam selesai harus setelah jam mulai.'];
        }

        $hours = round(($endAt - $startAt) / 3600, 2);
        if ($hours <= 0) {
            return ['ok' => false, 'message' => 'Durasi lembur harus lebih dari 0 jam.'];
        }

        $hasStandardSchema = $this->has_overtime_standard_schema();
        if (!$hasStandardSchema || $overtimeStandardId <= 0) {
            return ['ok' => false, 'message' => 'Pilih standar lembur aktif dari master sebelum menyimpan.'];
        }

        $standard = $this->db->select('id, standard_name, hourly_rate, is_active')
            ->from('att_overtime_standard')
            ->where('id', $overtimeStandardId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$standard) {
            return ['ok' => false, 'message' => 'Standar lembur tidak valid atau nonaktif.'];
        }

        // The master standard is the only source of the saved rate.
        $rate = (float)($standard['hourly_rate'] ?? 0);
        $totalPay = round($hours * $rate, 2);

        $dbPayload = [
            'employee_id' => $employeeId,
            'overtime_date' => $overtimeDate,
            'start_at' => date('Y-m-d H:i:s', $startAt),
            'end_at' => date('Y-m-d H:i:s', $endAt),
            'overtime_hours' => $hours,
            'overtime_rate' => round($rate, 2),
            'total_overtime_pay' => $totalPay,
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $dbPayload['overtime_standard_id'] = (int)$standard['id'];

        if ($status === 'APPROVED') {
            $dbPayload['approved_by'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
            $dbPayload['approved_at'] = date('Y-m-d H:i:s');
        } else {
            $dbPayload['approved_by'] = null;
            $dbPayload['approved_at'] = null;
        }

        if ($id > 0) {
            $exists = $this->get_overtime_entry_by_id($id);
            if (!$exists) {
                return ['ok' => false, 'message' => 'Data lembur tidak ditemukan.'];
            }
            $oldDate = (string)($exists['overtime_date'] ?? '');
            $oldLockMessage = $this->immutable_period_guard_message($oldDate);
            if ($oldLockMessage !== '') {
                return ['ok' => false, 'message' => $oldLockMessage];
            }
            $oldEmployeeId = (int)($exists['employee_id'] ?? 0);
            $this->db->where('id', $id)->update('att_overtime_entry', $dbPayload);
            $this->recompute_overtime_daily_payroll($oldEmployeeId, $oldDate);
            if ($oldEmployeeId !== $employeeId || $oldDate !== $overtimeDate) {
                $this->recompute_overtime_daily_payroll($employeeId, $overtimeDate);
            }
            return ['ok' => true, 'message' => 'Data lembur berhasil diperbarui.'];
        }

        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('att_overtime_entry', $dbPayload);
        $this->recompute_overtime_daily_payroll($employeeId, $overtimeDate);
        return ['ok' => true, 'message' => 'Data lembur berhasil ditambahkan.'];
    }

    public function delete_overtime_entry(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID lembur tidak valid.'];
        }
        $exists = $this->get_overtime_entry_by_id($id);
        if (!$exists) {
            return ['ok' => false, 'message' => 'Data lembur tidak ditemukan.'];
        }
        $lockMessage = $this->immutable_period_guard_message((string)($exists['overtime_date'] ?? ''));
        if ($lockMessage !== '') {
            return ['ok' => false, 'message' => $lockMessage];
        }
        $this->db->where('id', $id)->delete('att_overtime_entry');
        $this->recompute_overtime_daily_payroll((int)$exists['employee_id'], (string)$exists['overtime_date']);
        return ['ok' => true, 'message' => 'Data lembur berhasil dihapus.'];
    }

    /**
     * Rebuild attendance salary snapshots using one explicit contract snapshot.
     * This is intentionally contract-scoped so a late-generated contract cannot
     * accidentally overwrite dates that belong to another compensation period.
     */
    public function regenerate_daily_payroll_from_contract(int $contractId, string $dateStart = '', string $dateEnd = ''): array
    {
        if ($contractId <= 0) {
            return ['ok' => false, 'message' => 'Kontrak tidak valid.'];
        }
        if (!$this->db->table_exists('hr_contract') || !$this->db->table_exists('att_daily')) {
            return ['ok' => false, 'message' => 'Data kontrak atau absensi harian belum tersedia.'];
        }

        $requiredSnapshotFields = [
            'snapshot_basic_salary',
            'snapshot_position_allowance',
            'snapshot_objective_allowance',
            'snapshot_meal_rate',
            'snapshot_overtime_rate',
        ];
        foreach ($requiredSnapshotFields as $field) {
            if (!$this->att_daily_has_field($field)) {
                return ['ok' => false, 'message' => 'Kolom snapshot gaji harian belum lengkap.'];
            }
        }

        $contract = $this->db->select('
                c.id,
                c.employee_id,
                c.contract_number,
                c.status,
                c.start_date,
                c.end_date,
                s.id AS target_snapshot_id,
                COALESCE(s.basic_salary_amount, c.basic_salary) AS target_basic_salary,
                COALESCE(s.position_allowance_amount, c.position_allowance) AS target_position_allowance,
                COALESCE(s.other_allowance_amount, c.other_allowance) AS target_objective_allowance,
                COALESCE(s.meal_rate_amount, c.meal_rate) AS target_meal_rate
            ', false)
            ->from('hr_contract c')
            ->join('hr_contract_comp_snapshot s', 's.contract_id = c.id', 'left')
            ->where('c.id', $contractId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$contract) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }

        $contractStatus = strtoupper(trim((string)($contract['status'] ?? '')));
        if (!in_array($contractStatus, ['GENERATED', 'SIGNED', 'ACTIVE', 'EXPIRED'], true)) {
            return ['ok' => false, 'message' => 'Status kontrak tidak dapat dipakai untuk rebuild gaji harian.'];
        }

        $contractStart = (string)($contract['start_date'] ?? '');
        $contractEnd = (string)($contract['end_date'] ?? '');
        $isValidDate = static function (string $date): bool {
            return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
        };
        if (!$isValidDate($contractStart) || !$isValidDate($contractEnd)) {
            return ['ok' => false, 'message' => 'Rentang tanggal kontrak tidak valid.'];
        }
        if (($dateStart !== '' && !$isValidDate($dateStart)) || ($dateEnd !== '' && !$isValidDate($dateEnd))) {
            return ['ok' => false, 'message' => 'Rentang tanggal rebuild tidak valid.'];
        }

        $effectiveStart = $dateStart !== '' && $dateStart > $contractStart ? $dateStart : $contractStart;
        $effectiveEnd = $dateEnd !== '' && $dateEnd < $contractEnd ? $dateEnd : $contractEnd;
        if ($effectiveEnd < $effectiveStart) {
            return ['ok' => false, 'message' => 'Rentang rebuild berada di luar masa berlaku kontrak.'];
        }

        $dailyRows = $this->db->from('att_daily')
            ->where('employee_id', (int)$contract['employee_id'])
            ->where('attendance_date >=', $effectiveStart)
            ->where('attendance_date <=', $effectiveEnd)
            ->order_by('attendance_date', 'ASC')
            ->get()
            ->result_array();
        if (empty($dailyRows)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada absensi harian pada rentang kontrak yang dipilih.',
                'changed_count' => 0,
                'rows' => [],
            ];
        }

        $needsRebuild = [];
        $lockedDates = [];
        foreach ($dailyRows as $dailyRow) {
            if (!$this->daily_compensation_snapshot_differs($dailyRow, $contract)) {
                continue;
            }

            $attendanceDate = (string)($dailyRow['attendance_date'] ?? '');
            $locked = $this->get_locked_period_for_date($attendanceDate);
            if ($locked) {
                $lockedDates[] = $attendanceDate . ' (' . (string)($locked['period_code'] ?? '#') . ')';
                continue;
            }
            $needsRebuild[] = $dailyRow;
        }

        if (!empty($lockedDates)) {
            return [
                'ok' => false,
                'message' => 'Rebuild dibatalkan karena sebagian tanggal sudah terkunci payroll: ' . implode(', ', $lockedDates) . '.',
            ];
        }
        if (empty($needsRebuild)) {
            return [
                'ok' => true,
                'message' => 'Snapshot gaji harian pada rentang kontrak sudah sesuai.',
                'changed_count' => 0,
                'rows' => [],
            ];
        }

        $activePolicy = $this->get_active_policy();
        if (empty($activePolicy)) {
            return ['ok' => false, 'message' => 'Kebijakan absensi aktif tidak ditemukan.'];
        }

        $changedRows = [];
        $this->db->trans_start();
        foreach ($needsRebuild as $dailyRow) {
            $calculationRow = $dailyRow;
            $calculationRow['snapshot_basic_salary'] = (float)$contract['target_basic_salary'];
            $calculationRow['snapshot_position_allowance'] = (float)$contract['target_position_allowance'];
            $calculationRow['snapshot_objective_allowance'] = (float)$contract['target_objective_allowance'];
            $calculationRow['snapshot_meal_rate'] = (float)$contract['target_meal_rate'];
            $calculationRow['compensation_source'] = 'CONTRACT';
            $calculationRow['compensation_contract_id'] = (int)$contract['id'];
            $calculationRow['compensation_snapshot_id'] = (int)($contract['target_snapshot_id'] ?? 0) ?: null;

            // Preserve the policy that was locked on the attendance date.
            $policy = $this->daily_policy_snapshot_for_rebuild($dailyRow, $activePolicy);
            $calculationRow['snapshot_overtime_rate'] = isset($dailyRow['snapshot_overtime_rate'])
                && $dailyRow['snapshot_overtime_rate'] !== null
                ? (float)$dailyRow['snapshot_overtime_rate']
                : $this->get_policy_overtime_rate($policy);
            $manualOvertimePay = $this->get_manual_overtime_pay(
                (int)$contract['employee_id'],
                (string)$dailyRow['attendance_date']
            );
            $payload = $this->build_daily_payroll_payload(
                $calculationRow,
                $policy,
                (int)$contract['employee_id'],
                $manualOvertimePay
            );
            $this->load->model('Compensation_model');
            $payload += $this->Compensation_model->build_att_daily_provenance([
                'source' => 'CONTRACT',
                'contract_id' => (int)$contract['id'],
                'snapshot_id' => (int)($contract['target_snapshot_id'] ?? 0) ?: null,
            ]);

            $this->db->where('id', (int)$dailyRow['id'])->update('att_daily', $payload);
            $changedRows[] = [
                'attendance_date' => (string)$dailyRow['attendance_date'],
                'before_daily_salary_amount' => round((float)($dailyRow['daily_salary_amount'] ?? 0), 2),
                'after_daily_salary_amount' => round((float)($payload['daily_salary_amount'] ?? 0), 2),
            ];
        }
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal rebuild snapshot dan perhitungan gaji harian.'];
        }

        return [
            'ok' => true,
            'message' => 'Snapshot dan perhitungan gaji harian berhasil direbuild dari kontrak ' . (string)$contract['contract_number'] . '.',
            'changed_count' => count($changedRows),
            'rows' => $changedRows,
        ];
    }

    private function daily_compensation_snapshot_differs(array $dailyRow, array $contract): bool
    {
        $pairs = [
            ['snapshot_basic_salary', 'target_basic_salary'],
            ['snapshot_position_allowance', 'target_position_allowance'],
            ['snapshot_objective_allowance', 'target_objective_allowance'],
            ['snapshot_meal_rate', 'target_meal_rate'],
        ];
        foreach ($pairs as [$dailyField, $contractField]) {
            if (abs((float)($dailyRow[$dailyField] ?? 0) - (float)($contract[$contractField] ?? 0)) > 0.005) {
                return true;
            }
        }
        return false;
    }

    private function daily_policy_snapshot_for_rebuild(array $dailyRow, array $fallbackPolicy): array
    {
        $policy = $fallbackPolicy;
        $fieldMap = [
            'policy_snapshot_id' => 'id',
            'policy_snapshot_code' => 'policy_code',
            'policy_snapshot_name' => 'policy_name',
            'attendance_mode_snapshot' => 'attendance_calc_mode',
            'meal_mode_snapshot' => 'meal_calc_mode',
            'prorate_scope_snapshot' => 'prorate_deduction_scope',
            'overtime_mode_snapshot' => 'overtime_calc_mode',
            'allowance_late_treatment_snapshot' => 'allowance_late_treatment',
            'enable_late_deduction_snapshot' => 'enable_late_deduction',
            'enable_alpha_deduction_snapshot' => 'enable_alpha_deduction',
            'late_deduction_per_minute_snapshot' => 'late_deduction_per_minute',
            'alpha_deduction_per_day_snapshot' => 'alpha_deduction_per_day',
            'work_days_snapshot' => 'default_work_days_per_month',
        ];
        foreach ($fieldMap as $dailyField => $policyField) {
            if (array_key_exists($dailyField, $dailyRow) && $dailyRow[$dailyField] !== null && $dailyRow[$dailyField] !== '') {
                $policy[$policyField] = $dailyRow[$dailyField];
            }
        }
        return $policy;
    }

    private function has_overtime_standard_schema(): bool
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }
        $checked = $this->db->table_exists('att_overtime_standard')
            && $this->db->field_exists('overtime_standard_id', 'att_overtime_entry');
        return $checked;
    }

    private function recompute_overtime_daily_payroll(int $employeeId, string $date): void
    {
        if ($employeeId <= 0 || $date === '') {
            return;
        }
        if (!$this->db->table_exists('att_daily')) {
            return;
        }
        $dailyRow = $this->db->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();
        if (!$dailyRow) {
            return;
        }

        $policy = $this->get_active_policy();
        $manualOvertimePay = $this->get_manual_overtime_pay($employeeId, $date);
        $payload = $this->build_daily_payroll_payload($dailyRow, $policy, $employeeId, $manualOvertimePay);
        if (!empty($payload)) {
            $this->db->where('id', (int)$dailyRow['id'])->update('att_daily', $payload);
        }
    }

    private function get_schedule_shift(int $shiftId): ?array
    {
        if ($shiftId <= 0) {
            return null;
        }
        $row = $this->db->select('id, shift_code, shift_name')
            ->from('att_shift')
            ->where('id', $shiftId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        return $row ?: null;
    }

    private function get_schedule_employee_profile(int $employeeId): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }
        $row = $this->db->select('e.id, p.position_code')
            ->from('org_employee e')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->where('e.is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        return $row ?: null;
    }

    /**
     * A schedule is also the entry point for paid attendance. Current and
     * future assignments require an ACTIVE contract; past corrections may use
     * a finalized historical contract only within its original date range.
     */
    private function validate_schedule_contract_coverage(int $employeeId, string $date): array
    {
        if (!$this->db->table_exists('hr_contract') || !$this->db->table_exists('hr_contract_comp_snapshot')) {
            return [
                'ok' => false,
                'message' => 'Jadwal ditolak: fondasi kontrak kompensasi belum tersedia. Jalankan migration kontrak terlebih dahulu.',
            ];
        }

        $this->load->model('Compensation_model');
        $requiresActiveContract = $date >= date('Y-m-d');
        $compensation = $requiresActiveContract
            ? $this->Compensation_model->resolve_for_employee($employeeId, $date)
            : $this->Compensation_model->resolve_finalized_contract_for_employee($employeeId, $date);
        if (
            strtoupper((string)($compensation['source'] ?? '')) !== 'CONTRACT'
            || (int)($compensation['contract_id'] ?? 0) <= 0
        ) {
            return [
                'ok' => false,
                'message' => $requiresActiveContract
                    ? 'Jadwal ditolak: pegawai belum memiliki kontrak ACTIVE beserta snapshot kompensasi yang efektif pada ' . date('d/m/Y', strtotime($date)) . '. Generate, tanda tangani, lalu aktifkan kontrak terlebih dahulu.'
                    : 'Jadwal ditolak: tidak ada kontrak final beserta snapshot kompensasi yang mencakup tanggal ' . date('d/m/Y', strtotime($date)) . '.',
            ];
        }

        return ['ok' => true, 'compensation' => $compensation];
    }

    /**
     * Pending approval may repair an old row that already has its immutable
     * salary snapshot. New/current paid attendance must always resolve from
     * an ACTIVE contract, never from the legacy employee cache.
     */
    private function validate_daily_compensation_contract_coverage(int $employeeId, string $date, ?array $dailyRow = null): array
    {
        if (!$this->db->table_exists('hr_contract') || !$this->db->table_exists('hr_contract_comp_snapshot')) {
            return [
                'ok' => false,
                'message' => 'Absensi ditolak: fondasi kontrak kompensasi belum tersedia. Jalankan migration kontrak terlebih dahulu.',
            ];
        }

        $isHistorical = $date < date('Y-m-d');
        $hasHistoricalSnapshot = $isHistorical
            && $this->att_daily_has_field('snapshot_basic_salary')
            && is_array($dailyRow)
            && array_key_exists('snapshot_basic_salary', $dailyRow)
            && $dailyRow['snapshot_basic_salary'] !== null;
        if ($hasHistoricalSnapshot) {
            return ['ok' => true, 'historical_snapshot' => true];
        }

        $this->load->model('Compensation_model');
        $compensation = $isHistorical
            ? $this->Compensation_model->resolve_finalized_contract_for_employee($employeeId, $date)
            : $this->Compensation_model->resolve_for_employee($employeeId, $date);
        if (
            strtoupper((string)($compensation['source'] ?? '')) !== 'CONTRACT'
            || (int)($compensation['contract_id'] ?? 0) <= 0
        ) {
            return [
                'ok' => false,
                'message' => $isHistorical
                    ? 'Absensi ditolak: tanggal ini belum memiliki snapshot gaji harian maupun kontrak final yang mencakup tanggal kerja.'
                    : 'Absensi ditolak: pegawai belum memiliki kontrak ACTIVE beserta snapshot kompensasi pada tanggal kerja.',
            ];
        }

        return ['ok' => true, 'compensation' => $compensation];
    }

    /**
     * A finalized attendance row already drives payroll and, for PH, the
     * ledger. Changing its source schedule afterwards would silently change
     * the meaning of a completed day, so schedule changes must stop here.
     */
    private function validate_finalized_schedule_change(array $scheduleRow, int $newShiftId, string $newDate): array
    {
        $employeeId = (int)($scheduleRow['employee_id'] ?? 0);
        $oldShiftId = (int)($scheduleRow['shift_id'] ?? 0);
        $oldDate = (string)($scheduleRow['schedule_date'] ?? '');
        if ($employeeId <= 0 || $oldDate === '' || ($oldShiftId === $newShiftId && $oldDate === $newDate)) {
            return ['ok' => true];
        }

        $daily = $this->db->select('id, attendance_status')
            ->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $oldDate)
            ->where_in('attendance_status', ['PRESENT', 'LATE', 'HOLIDAY'])
            ->limit(1)
            ->get()
            ->row_array();
        if (!$daily) {
            return ['ok' => true];
        }

        return [
            'ok' => false,
            'message' => 'Jadwal tidak dapat diubah karena presensi tanggal ' . date('d/m/Y', strtotime($oldDate)) . ' sudah final. Koreksi melalui proses absensi agar gaji dan ledger PH tetap konsisten.',
        ];
    }

    private function validate_finalized_schedule_delete(array $scheduleRow): array
    {
        return $this->validate_finalized_schedule_change($scheduleRow, -1, '__DELETE__');
    }

    private function get_schedule_monthly_override(int $employeeId, string $monthStart): ?array
    {
        if (!$this->db->table_exists('att_schedule_monthly_override')) {
            return null;
        }
        $row = $this->db->from('att_schedule_monthly_override')
            ->where('employee_id', $employeeId)
            ->where('month_start', $monthStart)
            ->limit(1)
            ->get()
            ->row_array();
        return $row ?: null;
    }

    private function save_schedule_monthly_override(int $employeeId, string $monthStart, int $baseLimit, int $approvedLimit, array $options): array
    {
        if (!$this->db->table_exists('att_schedule_monthly_override')) {
            return ['ok' => false, 'message' => 'Migration guard jadwal belum dijalankan.'];
        }

        $actorUserId = max(0, (int)($options['actor_user_id'] ?? 0));
        $reason = trim((string)($options['override_reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Override batas jadwal bulanan untuk kebutuhan operasional.';
        }
        $now = date('Y-m-d H:i:s');
        $payload = [
            'employee_id' => $employeeId,
            'month_start' => $monthStart,
            'base_limit_days' => $baseLimit,
            'approved_limit_days' => $approvedLimit,
            'reason' => $reason,
            'approved_by' => $actorUserId > 0 ? $actorUserId : null,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $sql = $this->db->insert_string('att_schedule_monthly_override', $payload)
            . ' ON DUPLICATE KEY UPDATE'
            . ' base_limit_days=VALUES(base_limit_days),'
            . ' approved_limit_days=GREATEST(approved_limit_days, VALUES(approved_limit_days)),'
            . ' reason=VALUES(reason),'
            . ' approved_by=VALUES(approved_by),'
            . ' approved_at=VALUES(approved_at),'
            . ' updated_at=VALUES(updated_at)';
        $this->db->query($sql);
        return $this->db->error()['code'] ? ['ok' => false, 'message' => 'Gagal menyimpan override batas jadwal.'] : ['ok' => true];
    }

    /**
     * Simulate recorded PH mutations plus real past use and future PH
     * reservations. This prevents a second PH schedule from bypassing the
     * balance merely because its attendance row has not been created yet.
     */
    private function validate_ph_schedule_capacity(
        int $employeeId,
        string $candidateDate,
        int $candidateShiftId,
        int $excludeScheduleId = 0,
        bool $candidateAlreadyScheduled = false
    ): array {
        $candidateShift = $this->get_schedule_shift($candidateShiftId);
        if (!$candidateShift || !$this->is_ph_shift_code((string)($candidateShift['shift_code'] ?? ''))) {
            return ['ok' => true, 'message' => ''];
        }

        $eligibility = $this->db->select('is_eligible, effective_date')
            ->from('att_ph_eligibility')
            ->where('employee_id', $employeeId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$eligibility || (int)($eligibility['is_eligible'] ?? 0) !== 1 || (string)($eligibility['effective_date'] ?? '') > $candidateDate) {
            return ['ok' => false, 'message' => 'Shift PH ditolak: pegawai belum memiliki hak PH aktif pada tanggal tersebut.'];
        }

        $ledgerRows = $this->db->select('id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id')
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->where('tx_date <=', $candidateDate)
            ->order_by('tx_date', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        // Do not let old bad AUTO grants from PH leave attendance inflate a
        // new PH schedule. Their expiry children are excluded too.
        $wrongAutoGrantIds = $this->legacy_wrong_auto_ph_grant_id_set($employeeId, $candidateDate);
        $ledgerRows = $this->filter_legacy_wrong_auto_ph_ledger_rows($ledgerRows, $wrongAutoGrantIds);

        // Some historic regular-holiday attendances are valid but did not get
        // their AUTO GRANT. Treat them as a virtual grant for scheduling only.
        // The ledger stays untouched until an approved historical reconciliation
        // is run, while employees are not punished or over-credited meanwhile.
        $grantByDailyId = [];
        foreach ($ledgerRows as $ledgerRow) {
            if (
                strtoupper((string)($ledgerRow['tx_type'] ?? '')) === 'GRANT'
                && (string)($ledgerRow['ref_table'] ?? '') === 'att_daily'
                && (int)($ledgerRow['ref_id'] ?? 0) > 0
            ) {
                $grantByDailyId[(int)$ledgerRow['ref_id']] = true;
            }
        }
        $policy = $this->get_active_policy();
        $grantHistoryStart = max('2000-01-01', (string)($eligibility['effective_date'] ?? '2000-01-01'));
        $grantCandidates = $this->list_ph_grant_attendance_candidates($grantHistoryStart, $candidateDate, $employeeId);
        $virtualGrantCount = 0;
        foreach ($grantCandidates as $grantCandidate) {
            $dailyId = (int)($grantCandidate['daily_id'] ?? 0);
            if ($dailyId <= 0 || isset($grantByDailyId[$dailyId])) {
                continue;
            }
            $requireCheckout = (int)($policy['ph_grant_requires_checkout'] ?? 1) === 1;
            if (!$this->is_valid_ph_work_attendance($grantCandidate, $policy, $requireCheckout)) {
                continue;
            }
            $grantQty = round((float)($policy['ph_grant_qty_per_day'] ?? 1), 2);
            if ($grantQty <= 0) {
                $grantQty = 1;
            }
            $grantDate = (string)($grantCandidate['attendance_date'] ?? '');
            $expiryMonths = array_key_exists('expiry_months_override', $grantCandidate) && $grantCandidate['expiry_months_override'] !== null
                ? max(0, (int)$grantCandidate['expiry_months_override'])
                : max(0, (int)($policy['ph_expiry_months'] ?? 0));
            $ledgerRows[] = [
                'id' => 0,
                'tx_date' => $grantDate,
                'tx_type' => 'GRANT',
                'qty_days' => $grantQty,
                'expired_at' => $expiryMonths > 0 ? date('Y-m-d', strtotime($grantDate . ' +' . $expiryMonths . ' month')) : null,
                'ref_table' => 'att_daily',
                'ref_id' => $dailyId,
            ];
            $grantByDailyId[$dailyId] = true;
            $virtualGrantCount++;
        }
        $maxSyntheticId = 0;
        foreach ($ledgerRows as $ledgerRow) {
            $maxSyntheticId = max($maxSyntheticId, (int)($ledgerRow['id'] ?? 0));
        }

        $scheduledRows = $this->db->select("\n                ss.id AS schedule_id,\n                ss.schedule_date,\n                ss.shift_id,\n                ad.id AS daily_id,\n                ad.shift_id AS daily_shift_id,\n                ad.attendance_status,\n                daily_shift.shift_code AS daily_shift_code,\n                use_ledger.id AS use_ledger_id,\n                void_ledger.id AS void_ledger_id\n            ", false)
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'inner')
            ->join('att_daily ad', 'ad.employee_id = ss.employee_id AND ad.attendance_date = ss.schedule_date', 'left')
            ->join('att_shift daily_shift', 'daily_shift.id = ad.shift_id', 'left')
            ->join("att_employee_ph_ledger use_ledger", "use_ledger.employee_id = ss.employee_id AND use_ledger.tx_type = 'USE' AND use_ledger.ref_table = 'att_daily' AND use_ledger.ref_id = ad.id", 'left', false)
            ->join("att_employee_ph_ledger void_ledger", "void_ledger.employee_id = ss.employee_id AND void_ledger.tx_type = 'VOID' AND void_ledger.ref_table = 'att_daily' AND void_ledger.ref_id = ad.id", 'left', false)
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date <=', $candidateDate)
            ->where("UPPER(TRIM(COALESCE(s.shift_code, ''))) IN ('PH', 'PHB')", null, false)
            ->order_by('ss.schedule_date', 'ASC')
            ->order_by('ss.id', 'ASC')
            ->get()
            ->result_array();

        $today = date('Y-m-d');
        $reserved = 0;
        foreach ($scheduledRows as $scheduleRow) {
            if ($excludeScheduleId > 0 && (int)($scheduleRow['schedule_id'] ?? 0) === $excludeScheduleId) {
                continue;
            }
            $scheduleDate = (string)($scheduleRow['schedule_date'] ?? '');
            $attendanceStatus = strtoupper((string)($scheduleRow['attendance_status'] ?? ''));
            $dailyShiftIsPh = $this->is_ph_shift_code((string)($scheduleRow['daily_shift_code'] ?? ''));
            $hasRecordedUse = (int)($scheduleRow['use_ledger_id'] ?? 0) > 0;
            $hasVoidedUse = (int)($scheduleRow['void_ledger_id'] ?? 0) > 0;
            $isBeforeEligibility = $scheduleDate < (string)($eligibility['effective_date'] ?? '');
            $isActualPastUse = $scheduleDate < $today
                && $dailyShiftIsPh
                && in_array($attendanceStatus, ['HOLIDAY', 'PRESENT', 'LATE'], true);
            $isFutureReservation = $scheduleDate >= $today;
            // A void record or a schedule before PH eligibility is historical
            // context, not a live debit against a newly granted PH balance.
            if ($hasRecordedUse || $hasVoidedUse || $isBeforeEligibility || (!$isActualPastUse && !$isFutureReservation)) {
                continue;
            }
            $ledgerRows[] = [
                'id' => ++$maxSyntheticId,
                'tx_date' => $scheduleDate,
                'tx_type' => 'USE',
                'qty_days' => 1,
                'expired_at' => null,
                'ref_table' => 'att_shift_schedule',
                'ref_id' => (int)($scheduleRow['schedule_id'] ?? 0),
            ];
            $reserved++;
        }

        if (!$candidateAlreadyScheduled) {
            $ledgerRows[] = [
                'id' => ++$maxSyntheticId,
                'tx_date' => $candidateDate,
                'tx_type' => 'USE',
                'qty_days' => 1,
                'expired_at' => null,
                'ref_table' => 'att_shift_schedule',
                'ref_id' => 0,
            ];
            $reserved++;
        }

        $integrity = $this->ph_validate_rows_integrity($ledgerRows);
        if (empty($integrity['ok'])) {
            return [
                'ok' => false,
                'message' => 'Shift PH ditolak: saldo tidak cukup setelah menghitung PH yang sudah dipakai atau dijadwalkan. ' . (string)($integrity['message'] ?? 'Periksa ledger PH.'),
                'reserved' => $reserved,
                'virtual_grants' => $virtualGrantCount,
                'ignored_wrong_auto_grants' => count($wrongAutoGrantIds),
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'reserved' => $reserved,
            'virtual_grants' => $virtualGrantCount,
            'ignored_wrong_auto_grants' => count($wrongAutoGrantIds),
        ];
    }

    /**
     * Public preflight used by the employee attendance page before it creates
     * an automatic PH attendance row.
     */
    public function validate_scheduled_ph_use(int $employeeId, string $date, int $shiftId): array
    {
        return $this->validate_ph_schedule_capacity($employeeId, $date, $shiftId, 0, true);
    }

    private function validate_schedule_change(int $employeeId, int $shiftId, string $date, int $excludeScheduleId = 0, array $options = []): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'message' => 'Tanggal jadwal tidak valid.'];
        }
        $employee = $this->get_schedule_employee_profile($employeeId);
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }
        $contractCoverage = $this->validate_schedule_contract_coverage($employeeId, $date);
        if (empty($contractCoverage['ok'])) {
            return $contractCoverage;
        }
        $shift = $this->get_schedule_shift($shiftId);
        if (!$shift) {
            return ['ok' => false, 'message' => 'Shift tidak valid atau nonaktif.'];
        }

        if ($this->is_ph_shift_code((string)($shift['shift_code'] ?? ''))) {
            $capacity = $this->validate_ph_schedule_capacity($employeeId, $date, $shiftId, $excludeScheduleId, false);
            if (empty($capacity['ok'])) {
                return $capacity;
            }
        }

        $policy = $this->get_active_policy();
        $baseLimit = max(1, (int)($policy['default_work_days_per_month'] ?? 26));
        $monthStart = date('Y-m-01', strtotime($date));
        $monthEnd = date('Y-m-t', strtotime($date));
        $counter = $this->db->select('COUNT(DISTINCT schedule_date) AS total_days', false)
            ->from('att_shift_schedule')
            ->where('employee_id', $employeeId)
            ->where('schedule_date >=', $monthStart)
            ->where('schedule_date <=', $monthEnd);
        if ($excludeScheduleId > 0) {
            $counter->where('id !=', $excludeScheduleId);
        }
        $countRow = $counter->get()->row_array() ?: [];
        $scheduledAfter = (int)($countRow['total_days'] ?? 0) + 1;
        $isSecurity = strtoupper(trim((string)($employee['position_code'] ?? ''))) === 'SECURITY';
        if ($isSecurity || $scheduledAfter <= $baseLimit) {
            return ['ok' => true, 'message' => '', 'scheduled_days' => $scheduledAfter, 'base_limit_days' => $baseLimit];
        }

        $override = $this->get_schedule_monthly_override($employeeId, $monthStart);
        if ($override && (int)($override['approved_limit_days'] ?? 0) >= $scheduledAfter) {
            return [
                'ok' => true,
                'message' => 'Jadwal di atas batas standar memakai override yang sudah tercatat.',
                'warning' => true,
                'scheduled_days' => $scheduledAfter,
                'base_limit_days' => $baseLimit,
            ];
        }

        $canMonthlyOverride = array_key_exists('can_monthly_override', $options)
            ? !empty($options['can_monthly_override'])
            : $this->can_user_override_schedule_monthly_limit(
                (int)($options['actor_user_id'] ?? 0),
                !empty($options['is_superadmin'])
            );
        if (!$canMonthlyOverride) {
            return [
                'ok' => false,
                'message' => 'Jadwal ditolak: total ' . $scheduledAfter . ' hari melebihi batas ' . $baseLimit . ' hari/bulan. Hanya user atau jabatan yang diberi otoritas di Pengaturan Absensi yang dapat membuka override.',
                'scheduled_days' => $scheduledAfter,
                'base_limit_days' => $baseLimit,
            ];
        }
        if (empty($options['allow_monthly_override'])) {
            return [
                'ok' => false,
                'requires_override' => true,
                'message' => 'Total jadwal menjadi ' . $scheduledAfter . ' hari, melebihi batas ' . $baseLimit . ' hari/bulan. Setujui override untuk melanjutkan.',
                'scheduled_days' => $scheduledAfter,
                'base_limit_days' => $baseLimit,
            ];
        }

        $savedOverride = $this->save_schedule_monthly_override($employeeId, $monthStart, $baseLimit, $scheduledAfter, $options);
        if (empty($savedOverride['ok'])) {
            return $savedOverride;
        }
        return [
            'ok' => true,
            'message' => 'Override jadwal dicatat: ' . $scheduledAfter . ' hari pada ' . date('F Y', strtotime($monthStart)) . '.',
            'warning' => true,
            'scheduled_days' => $scheduledAfter,
            'base_limit_days' => $baseLimit,
        ];
    }

    public function save_schedule(int $employeeId, int $shiftId, string $date, string $notes = '', int $createdBy = 0, array $guardOptions = []): array
    {
        if ($employeeId <= 0 || $shiftId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data jadwal tidak lengkap.'];
        }

        $exists = $this->db->select('id, employee_id, shift_id, schedule_date')
            ->from('att_shift_schedule')
            ->where('employee_id', $employeeId)
            ->where('schedule_date', $date)
            ->limit(1)
            ->get()->row_array();

        if ($exists) {
            $finalized = $this->validate_finalized_schedule_change($exists, $shiftId, $date);
            if (empty($finalized['ok'])) {
                return $finalized;
            }
        }

        $guard = $this->validate_schedule_change(
            $employeeId,
            $shiftId,
            $date,
            (int)($exists['id'] ?? 0),
            $guardOptions
        );
        if (empty($guard['ok'])) {
            return $guard;
        }

        $payload = [
            'shift_id' => $shiftId,
            'notes' => ($notes !== '') ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($exists) {
            $this->db->where('id', (int)$exists['id'])->update('att_shift_schedule', $payload);
            return array_merge($guard, ['ok' => true, 'message' => (string)($guard['message'] ?? '') ?: 'Jadwal diperbarui.']);
        }

        $this->db->insert('att_shift_schedule', [
            'employee_id' => $employeeId,
            'shift_id' => $shiftId,
            'schedule_date' => $date,
            'notes' => ($notes !== '') ? $notes : null,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return array_merge($guard, ['ok' => true, 'message' => (string)($guard['message'] ?? '') ?: 'Jadwal ditambahkan.']);
    }

    public function update_schedule(int $id, int $shiftId, string $date, string $notes = '', array $guardOptions = []): array
    {
        $row = $this->db->from('att_shift_schedule')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Jadwal tidak ditemukan.'];
        }
        if ($shiftId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Shift dan tanggal wajib diisi.'];
        }

        $finalized = $this->validate_finalized_schedule_change($row, $shiftId, $date);
        if (empty($finalized['ok'])) {
            return $finalized;
        }

        $dup = $this->db->select('id')
            ->from('att_shift_schedule')
            ->where('employee_id', (int)$row['employee_id'])
            ->where('schedule_date', $date)
            ->where('id !=', $id)
            ->limit(1)
            ->get()->row_array();
        if ($dup) {
            return ['ok' => false, 'message' => 'Pegawai sudah punya jadwal lain di tanggal tersebut.'];
        }

        $guard = $this->validate_schedule_change(
            (int)$row['employee_id'],
            $shiftId,
            $date,
            $id,
            $guardOptions
        );
        if (empty($guard['ok'])) {
            return $guard;
        }

        $this->db->where('id', $id)->update('att_shift_schedule', [
            'shift_id' => $shiftId,
            'schedule_date' => $date,
            'notes' => ($notes !== '') ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return array_merge($guard, ['ok' => true, 'message' => (string)($guard['message'] ?? '') ?: 'Jadwal berhasil diperbarui.']);
    }

    public function delete_schedule(int $id): array
    {
        $row = $this->db->from('att_shift_schedule')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Jadwal tidak ditemukan.'];
        }
        $finalized = $this->validate_finalized_schedule_delete($row);
        if (empty($finalized['ok'])) {
            return $finalized;
        }
        $this->db->where('id', $id)->delete('att_shift_schedule');
        return ['ok' => true, 'message' => 'Jadwal berhasil dihapus.'];
    }

    public function bulk_save_schedule(array $employeeIds, int $shiftId, string $startDate, string $endDate, string $notes = '', int $createdBy = 0, array $guardOptions = []): array
    {
        if ($shiftId <= 0 || $startDate === '' || $endDate === '' || empty($employeeIds)) {
            return ['ok' => false, 'message' => 'Data bulk jadwal tidak lengkap.'];
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if (!$startTs || !$endTs || $endTs < $startTs) {
            return ['ok' => false, 'message' => 'Rentang tanggal tidak valid.'];
        }

        $cleanEmployeeIds = [];
        foreach ($employeeIds as $employeeId) {
            $val = (int)$employeeId;
            if ($val > 0) {
                $cleanEmployeeIds[$val] = true;
            }
        }
        if (empty($cleanEmployeeIds)) {
            return ['ok' => false, 'message' => 'Tidak ada pegawai valid dipilih.'];
        }

        $affected = 0;
        $this->db->trans_begin();
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $date = date('Y-m-d', $ts);
            foreach (array_keys($cleanEmployeeIds) as $employeeId) {
                $exists = $this->db->select('id, employee_id, shift_id, schedule_date')
                    ->from('att_shift_schedule')
                    ->where('employee_id', $employeeId)
                    ->where('schedule_date', $date)
                    ->limit(1)
                    ->get()->row_array();

                if ($exists) {
                    $finalized = $this->validate_finalized_schedule_change($exists, $shiftId, $date);
                    if (empty($finalized['ok'])) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Bulk dibatalkan pada ' . $date . ' untuk pegawai #' . $employeeId . ': ' . (string)($finalized['message'] ?? 'Presensi final tidak boleh diubah.'),
                        ];
                    }
                }

                $guard = $this->validate_schedule_change(
                    $employeeId,
                    $shiftId,
                    $date,
                    (int)($exists['id'] ?? 0),
                    $guardOptions
                );
                if (empty($guard['ok'])) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => 'Bulk dibatalkan pada ' . $date . ' untuk pegawai #' . $employeeId . ': ' . (string)($guard['message'] ?? 'Validasi jadwal gagal.'),
                        'requires_override' => !empty($guard['requires_override']),
                    ];
                }
                if ($exists) {
                    $this->db->where('id', (int)$exists['id'])->update('att_shift_schedule', [
                        'shift_id' => $shiftId,
                        'notes' => ($notes !== '') ? $notes : null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $this->db->insert('att_shift_schedule', [
                        'employee_id' => $employeeId,
                        'shift_id' => $shiftId,
                        'schedule_date' => $date,
                        'notes' => ($notes !== '') ? $notes : null,
                        'created_by' => $createdBy > 0 ? $createdBy : null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $affected++;
            }
        }
        $this->db->trans_commit();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan bulk jadwal.'];
        }

        return ['ok' => true, 'message' => 'Bulk jadwal berhasil diproses: ' . $affected . ' baris.'];
    }

    public function get_shift_code_map(): array
    {
        $rows = $this->db->select('id, shift_code, shift_name')
            ->from('att_shift')
            ->where('is_active', 1)
            ->order_by('shift_code', 'ASC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['shift_code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $map[$code] = $row;
        }
        return $map;
    }

    public function schedule_matrix(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $employees = $this->db->select('id, employee_code, employee_name, division_id, position_id')
            ->from('org_employee')
            ->where('is_active', 1)
            ->order_by('division_id', 'ASC')
            ->order_by('position_id', 'ASC')
            ->order_by('employee_name', 'ASC')
            ->get()->result();

        $rows = $this->db->select('ss.employee_id, ss.schedule_date, s.shift_code')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.schedule_date >=', $start)
            ->where('ss.schedule_date <=', $end)
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $employeeId = (int)($row['employee_id'] ?? 0);
            $scheduleDate = (string)($row['schedule_date'] ?? '');
            if ($employeeId <= 0 || $scheduleDate === '') {
                continue;
            }
            $map[$employeeId][$scheduleDate] = (string)($row['shift_code'] ?? '');
        }

        return [
            'employees' => $employees,
            'schedule_map' => $map,
            'start' => $start,
            'end' => $end,
        ];
    }

    public function get_holiday_dates_between(string $startDate, string $endDate): array
    {
        if ($startDate === '' || $endDate === '' || !$this->db->table_exists('att_holiday_calendar')) {
            return [];
        }

        $rows = $this->db->select('holiday_date')
            ->from('att_holiday_calendar')
            ->where('holiday_date >=', $startDate)
            ->where('holiday_date <=', $endDate)
            ->where('is_active', 1)
            ->get()->result_array();

        return array_values(array_filter(array_map(static function ($r) {
            return (string)($r['holiday_date'] ?? '');
        }, $rows)));
    }

    /**
     * Keep national holidays distinct from company or special days because
     * schedule planning and PH use the national calendar as the formal marker.
     */
    public function get_national_holidays_between(string $startDate, string $endDate): array
    {
        if ($startDate === '' || $endDate === '' || !$this->db->table_exists('att_holiday_calendar')) {
            return [];
        }

        return $this->db
            ->select('holiday_date, holiday_name')
            ->from('att_holiday_calendar')
            ->where('holiday_date >=', $startDate)
            ->where('holiday_date <=', $endDate)
            ->where('holiday_type', 'NATIONAL')
            ->where('is_active', 1)
            ->order_by('holiday_date', 'ASC')
            ->order_by('holiday_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function upsert_schedule_by_shift_code(int $employeeId, string $date, string $shiftCode, int $actorEmployeeId = 0, array $guardOptions = []): array
    {
        $shiftCode = strtoupper(trim($shiftCode));
        $date = trim($date);
        if ($employeeId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data tidak lengkap.'];
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid.'];
        }

        if ($shiftCode === '') {
            $existing = $this->db->from('att_shift_schedule')
                ->where('employee_id', $employeeId)
                ->where('schedule_date', $date)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            if ($existing) {
                $finalized = $this->validate_finalized_schedule_delete($existing);
                if (empty($finalized['ok'])) {
                    return $finalized;
                }
                $this->db->where('id', (int)$existing['id'])->delete('att_shift_schedule');
            }
            return ['ok' => true, 'message' => 'Jadwal dihapus.'];
        }

        $shift = $this->db->select('id, shift_code')
            ->from('att_shift')
            ->where('shift_code', $shiftCode)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$shift) {
            return ['ok' => false, 'message' => 'Kode shift tidak valid: ' . $shiftCode];
        }

        // Update one existing row explicitly. This keeps the spreadsheet safe
        // even before a legacy database receives its unique schedule migration.
        $existing = $this->db->select('id, employee_id, shift_id, schedule_date')
            ->from('att_shift_schedule')
            ->where('employee_id', $employeeId)
            ->where('schedule_date', $date)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        if ($existing) {
            $finalized = $this->validate_finalized_schedule_change($existing, (int)$shift['id'], $date);
            if (empty($finalized['ok'])) {
                return $finalized;
            }
        }
        $guard = $this->validate_schedule_change(
            $employeeId,
            (int)$shift['id'],
            $date,
            (int)($existing['id'] ?? 0),
            $guardOptions
        );
        if (empty($guard['ok'])) {
            return $guard;
        }

        $now = date('Y-m-d H:i:s');
        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('att_shift_schedule', [
                'shift_id' => (int)$shift['id'],
                'notes' => null,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->insert('att_shift_schedule', [
                'employee_id' => $employeeId,
                'schedule_date' => $date,
                'shift_id' => (int)$shift['id'],
                'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return array_merge($guard, ['ok' => true, 'message' => (string)($guard['message'] ?? '') ?: 'Jadwal tersimpan.']);
    }

    public function get_active_locations(): array
    {
        $hasDefault = $this->db->field_exists('is_default', 'att_location');
        $select = $hasDefault
            ? 'id AS value, location_name AS label, is_default'
            : 'id AS value, location_name AS label, 0 AS is_default';
        $this->db->select($select, false)
            ->from('att_location')
            ->where('is_active', 1);
        if ($hasDefault) {
            $this->db->order_by('is_default', 'DESC');
        }
        return $this->db->order_by('location_name', 'ASC')->get()->result_array();
    }

    public function get_default_location_id(): int
    {
        if (!$this->db->field_exists('is_default', 'att_location')) {
            $row = $this->db->select('id')->from('att_location')->where('is_active', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
            return (int)($row['id'] ?? 0);
        }
        $row = $this->db->select('id')->from('att_location')->where('is_active', 1)->where('is_default', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
        if ($row) {
            return (int)$row['id'];
        }
        $fallback = $this->db->select('id')->from('att_location')->where('is_active', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
        return (int)($fallback['id'] ?? 0);
    }

    public function count_pending_requests(array $f): int
    {
        $this->build_pending_requests_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_pending_requests(array $f, int $limit, int $offset): array
    {
        $this->build_pending_requests_query($f, true);
        return $this->db
            ->order_by('pr.request_date', 'DESC')
            ->order_by('pr.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function pending_request_approval_history_map(array $requestIds): array
    {
        $cleanIds = [];
        foreach ($requestIds as $requestId) {
            $id = (int)$requestId;
            if ($id > 0) {
                $cleanIds[$id] = true;
            }
        }
        if (empty($cleanIds) || !$this->db->table_exists('att_pending_request_approval')) {
            return [];
        }

        $rows = $this->db->select('pa.pending_request_id, pa.approval_level, pa.action, pa.notes, pa.acted_at, pa.created_at, pa.approver_employee_id, e.employee_name AS approver_name, e.employee_code AS approver_code')
            ->from('att_pending_request_approval pa')
            ->join('org_employee e', 'e.id = pa.approver_employee_id', 'left')
            ->where_in('pa.pending_request_id', array_keys($cleanIds))
            ->order_by('pa.pending_request_id', 'ASC')
            ->order_by('pa.approval_level', 'ASC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $requestId = (int)($row['pending_request_id'] ?? 0);
            if ($requestId <= 0) {
                continue;
            }
            if (!isset($map[$requestId])) {
                $map[$requestId] = [];
            }
            $map[$requestId][] = $row;
        }

        return $map;
    }

    public function pending_request_existing_daily_map(array $rows): array
    {
        $employeeIds = [];
        $dates = [];
        foreach ($rows as $row) {
            $employeeId = (int)($row['employee_id'] ?? 0);
            $requestDate = trim((string)($row['request_date'] ?? ''));
            if ($employeeId <= 0 || $requestDate === '') {
                continue;
            }
            $employeeIds[$employeeId] = true;
            $dates[$requestDate] = true;
        }

        if (empty($employeeIds) || empty($dates)) {
            return [];
        }

        $dailyRows = $this->db
            ->select('ad.employee_id, ad.attendance_date, ad.attendance_status, ad.checkin_at, ad.checkout_at, ad.source_type, ad.shift_id, s.shift_code')
            ->from('att_daily ad')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where_in('ad.employee_id', array_keys($employeeIds))
            ->where_in('ad.attendance_date', array_keys($dates))
            ->get()
            ->result_array();

        $map = [];
        foreach ($dailyRows as $dailyRow) {
            $key = (int)($dailyRow['employee_id'] ?? 0) . '|' . (string)($dailyRow['attendance_date'] ?? '');
            if ($key === '0|') {
                continue;
            }
            $map[$key] = $dailyRow;
        }

        return $map;
    }

    public function pending_request_schedule_map(array $rows): array
    {
        $employeeIds = [];
        $dates = [];
        foreach ($rows as $row) {
            $employeeId = (int)($row['employee_id'] ?? 0);
            $requestDate = trim((string)($row['request_date'] ?? ''));
            if ($employeeId <= 0 || $requestDate === '') {
                continue;
            }
            $employeeIds[$employeeId] = true;
            $dates[$requestDate] = true;
        }

        if (empty($employeeIds) || empty($dates) || !$this->db->table_exists('att_shift_schedule')) {
            return [];
        }

        $scheduleRows = $this->db
            ->select('ss.employee_id, ss.schedule_date, ss.shift_id, ss.notes, s.shift_code, s.shift_name, s.start_time, s.end_time, COALESCE(s.is_overnight, 0) AS is_overnight', false)
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where_in('ss.employee_id', array_keys($employeeIds))
            ->where_in('ss.schedule_date', array_keys($dates))
            ->get()
            ->result_array();

        $map = [];
        foreach ($scheduleRows as $scheduleRow) {
            $key = (int)($scheduleRow['employee_id'] ?? 0) . '|' . (string)($scheduleRow['schedule_date'] ?? '');
            if ($key === '0|') {
                continue;
            }
            $map[$key] = $scheduleRow;
        }

        return $map;
    }

    private function build_pending_requests_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $select = 'pr.*, e.employee_code, e.employee_name, d.division_name, p.position_name';
            if ($this->db->table_exists('att_pending_request_approval')) {
                $select .= ",
                    (SELECT COUNT(*) FROM att_pending_request_approval pa WHERE pa.pending_request_id = pr.id AND pa.action = 'APPROVED') AS approved_levels,
                    (SELECT MAX(pa.approval_level) FROM att_pending_request_approval pa WHERE pa.pending_request_id = pr.id AND pa.action = 'APPROVED') AS last_approved_level,
                    (SELECT GROUP_CONCAT(CONCAT('L', pa.approval_level, ':', COALESCE(a.employee_name, '-'), ' ', pa.action) ORDER BY pa.approval_level SEPARATOR ' | ')
                       FROM att_pending_request_approval pa
                       LEFT JOIN org_employee a ON a.id = pa.approver_employee_id
                      WHERE pa.pending_request_id = pr.id) AS approval_timeline";
            }
            $this->db->select($select, false);
        }

        $this->db->from('att_pending_request pr')
            ->join('org_employee e', 'e.id = pr.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('pr.request_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('pr.request_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['status'])) {
            $this->db->where('pr.status', $f['status']);
        }
        if (!empty($f['request_type'])) {
            $this->db->where('pr.request_type', $f['request_type']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('pr.reason', $q)
                ->group_end();
        }
    }

    public function count_anomalies(array $f): int
    {
        $this->build_anomalies_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_anomalies(array $f, int $limit, int $offset): array
    {
        $this->build_anomalies_query($f, true);
        return $this->db
            ->order_by('ad.attendance_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_anomalies_query(array $f, bool $withSelect): void
    {
        $issueCase = "CASE
            WHEN ad.checkin_at IS NULL AND ad.checkout_at IS NOT NULL THEN 'MISSING_CHECKIN'
            WHEN ad.checkin_at IS NOT NULL AND ad.checkout_at IS NULL THEN 'MISSING_CHECKOUT'
            WHEN ad.checkin_at IS NOT NULL AND ad.checkout_at IS NOT NULL AND ad.checkout_at < ad.checkin_at THEN 'CHECKOUT_BEFORE_CHECKIN'
            WHEN ad.checkin_at IS NOT NULL AND ad.checkout_at IS NOT NULL AND COALESCE(ad.work_minutes,0) = 0 THEN 'ZERO_WORK_WITH_CHECKIO'
            WHEN COALESCE(ad.late_minutes,0) > 0 AND ad.attendance_status <> 'LATE' THEN 'STATUS_MISMATCH_LATE'
            ELSE NULL
        END";

        if ($withSelect) {
            $this->db->select(
                "ad.*, e.employee_code, e.employee_name, d.division_name, p.position_name, s.shift_code, s.shift_name, {$issueCase} AS issue_type",
                false
            );
        }

        $this->db->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where("({$issueCase}) IS NOT NULL", null, false);

        if (!empty($f['date_start'])) {
            $this->db->where('ad.attendance_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ad.attendance_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['issue_type'])) {
            $this->db->where("({$issueCase}) = " . $this->db->escape($f['issue_type']), null, false);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('s.shift_code', $q)
                ->group_end();
        }
    }

    public function master_health_summary(): array
    {
        $summary = [
            'active_employee' => (int)$this->db->from('org_employee')->where('is_active', 1)->count_all_results(),
            'employee_without_user' => 0,
            'user_without_employee' => 0,
            'employee_without_division' => 0,
            'employee_without_position' => 0,
            'employee_without_month_schedule' => 0,
            'employee_without_active_contract' => 0,
        ];

        $summary['employee_without_user'] = (int)$this->db
            ->from('org_employee e')
            ->join('auth_user u', 'u.employee_id = e.id', 'left')
            ->where('e.is_active', 1)
            ->where('u.id IS NULL', null, false)
            ->count_all_results();

        $summary['user_without_employee'] = (int)$this->db
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'left')
            ->where('u.is_active', 1)
            ->where('(u.employee_id IS NULL OR e.id IS NULL)', null, false)
            ->count_all_results();

        $summary['employee_without_division'] = (int)$this->db
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.division_id IS NULL OR d.id IS NULL)', null, false)
            ->count_all_results();

        $summary['employee_without_position'] = (int)$this->db
            ->from('org_employee e')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.position_id IS NULL OR p.id IS NULL)', null, false)
            ->count_all_results();

        $summary['employee_without_month_schedule'] = (int)$this->db
            ->from('org_employee e')
            ->join('att_shift_schedule ss', "ss.employee_id = e.id AND ss.schedule_date >= '" . date('Y-m-01') . "' AND ss.schedule_date <= '" . date('Y-m-t') . "'", 'left')
            ->where('e.is_active', 1)
            ->where('ss.id IS NULL', null, false)
            ->count_all_results();

        if ($this->db->table_exists('hr_contract')) {
            $summary['employee_without_active_contract'] = (int)$this->db
                ->from('org_employee e')
                ->join('hr_contract c', "c.employee_id = e.id AND c.status = 'ACTIVE' AND c.start_date <= CURDATE() AND c.end_date >= CURDATE()", 'left')
                ->where('e.is_active', 1)
                ->where('c.id IS NULL', null, false)
                ->count_all_results();
        }

        return $summary;
    }

    public function count_master_health_issues(array $filters): int
    {
        return count($this->master_health_issue_rows($filters));
    }

    public function list_master_health_issues(array $filters, int $limit, int $offset): array
    {
        $rows = $this->master_health_issue_rows($filters);
        return array_slice($rows, max(0, $offset), max(1, $limit));
    }

    private function master_health_issue_rows(array $filters): array
    {
        $rows = [];
        $q = strtolower(trim((string)($filters['q'] ?? '')));
        $issueFilter = strtoupper(trim((string)($filters['issue_type'] ?? '')));

        $addRows = static function (array $sourceRows, string $issueType, string $issueLabel) use (&$rows, $q, $issueFilter): void {
            if ($issueFilter !== '' && $issueFilter !== $issueType) {
                return;
            }

            foreach ($sourceRows as $r) {
                $searchBlob = strtolower(
                    (string)($r['employee_code'] ?? '') . ' ' .
                    (string)($r['employee_name'] ?? '') . ' ' .
                    (string)($r['username'] ?? '') . ' ' .
                    (string)($r['email'] ?? '')
                );
                if ($q !== '' && strpos($searchBlob, $q) === false) {
                    continue;
                }

                $rows[] = [
                    'issue_type' => $issueType,
                    'issue_label' => $issueLabel,
                    'employee_id' => (int)($r['employee_id'] ?? 0),
                    'employee_code' => (string)($r['employee_code'] ?? '-'),
                    'employee_name' => (string)($r['employee_name'] ?? '-'),
                    'username' => (string)($r['username'] ?? '-'),
                    'email' => (string)($r['email'] ?? '-'),
                    'division_name' => (string)($r['division_name'] ?? '-'),
                    'position_name' => (string)($r['position_name'] ?? '-'),
                    'notes' => (string)($r['notes'] ?? ''),
                ];
            }
        };

        $employeeWithoutUser = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('auth_user u', 'u.employee_id = e.id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1)
            ->where('u.id IS NULL', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutUser, 'EMPLOYEE_WITHOUT_USER', 'Pegawai aktif belum terhubung user login');

        $userWithoutEmployee = $this->db
            ->select('COALESCE(e.id, 0) AS employee_id, COALESCE(e.employee_code, \'-\') AS employee_code, COALESCE(e.employee_name, \'-\') AS employee_name, u.username, u.email')
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'left')
            ->where('u.is_active', 1)
            ->where('(u.employee_id IS NULL OR e.id IS NULL)', null, false)
            ->get()->result_array();
        $addRows($userWithoutEmployee, 'USER_WITHOUT_EMPLOYEE', 'User aktif tidak punya relasi pegawai');

        $employeeWithoutDivision = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.division_id IS NULL OR d.id IS NULL)', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutDivision, 'EMPLOYEE_WITHOUT_DIVISION', 'Pegawai aktif belum punya divisi valid');

        $employeeWithoutPosition = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name')
            ->from('org_employee e')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.position_id IS NULL OR p.id IS NULL)', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutPosition, 'EMPLOYEE_WITHOUT_POSITION', 'Pegawai aktif belum punya jabatan valid');

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $employeeWithoutSchedule = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift_schedule ss', "ss.employee_id = e.id AND ss.schedule_date >= '{$monthStart}' AND ss.schedule_date <= '{$monthEnd}'", 'left')
            ->where('e.is_active', 1)
            ->where('ss.id IS NULL', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutSchedule, 'EMPLOYEE_WITHOUT_MONTH_SCHEDULE', 'Pegawai aktif belum punya jadwal shift bulan berjalan');

        if ($this->db->table_exists('hr_contract')) {
            $employeeWithoutContract = $this->db
                ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name, p.position_name')
                ->from('org_employee e')
                ->join('org_division d', 'd.id = e.division_id', 'left')
                ->join('org_position p', 'p.id = e.position_id', 'left')
                ->join('hr_contract c', "c.employee_id = e.id AND c.status = 'ACTIVE' AND c.start_date <= CURDATE() AND c.end_date >= CURDATE()", 'left')
                ->where('e.is_active', 1)
                ->where('c.id IS NULL', null, false)
                ->get()->result_array();
            $addRows($employeeWithoutContract, 'EMPLOYEE_WITHOUT_ACTIVE_CONTRACT', 'Pegawai aktif belum punya kontrak ACTIVE');
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string)$a['issue_type'], (string)$b['issue_type']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)$a['employee_name'], (string)$b['employee_name']);
        });

        return $rows;
    }

    public function process_pending_request_action(int $requestId, int $actorEmployeeId, string $action, string $notes, bool $isSuperadmin = false, bool $forceFinalApprove = false): array
    {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['APPROVE', 'REJECT', 'CANCEL'], true)) {
            return ['ok' => false, 'message' => 'Aksi tidak valid.'];
        }

        $req = $this->db->from('att_pending_request')->where('id', $requestId)->limit(1)->get()->row_array();
        if (!$req) {
            return ['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }
        if (strtoupper((string)($req['status'] ?? '')) !== 'PENDING') {
            return ['ok' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.'];
        }

        $policy = $this->get_active_policy();
        $policyId = (int)($policy['id'] ?? 0);
        $approvalLevels = max(1, min(3, (int)($policy['pending_approval_levels'] ?? 3)));

        $approvedLevels = 0;
        $hasApprovalTable = $this->db->table_exists('att_pending_request_approval');
        if ($hasApprovalTable) {
            $approvedLevels = (int)$this->db
                ->from('att_pending_request_approval')
                ->where('pending_request_id', $requestId)
                ->where('action', 'APPROVED')
                ->count_all_results();
        } else {
            $approvalLevels = 1;
        }

        $currentLevel = min($approvalLevels, max(1, $approvedLevels + 1));

        if ($action === 'CANCEL') {
            if (!$isSuperadmin && (int)$req['employee_id'] !== $actorEmployeeId) {
                return ['ok' => false, 'message' => 'Hanya pengaju atau superadmin yang dapat membatalkan.'];
            }
            $this->db->where('id', $requestId)->update('att_pending_request', [
                'status' => 'CANCELLED',
                'approval_notes' => $notes !== '' ? $notes : 'Dibatalkan pengaju',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return ['ok' => true, 'message' => 'Pengajuan dibatalkan.'];
        }

        if (!$isSuperadmin && !$this->can_verify_level($policyId, $currentLevel, $actorEmployeeId)) {
            return ['ok' => false, 'message' => 'Anda tidak terdaftar sebagai verifier pada level saat ini.'];
        }

        if ($forceFinalApprove && !$isSuperadmin) {
            return ['ok' => false, 'message' => 'Override final approval hanya untuk superadmin.'];
        }

        $this->db->trans_start();
        if ($hasApprovalTable) {
            $this->db->where('pending_request_id', $requestId)
                ->where('approval_level', $currentLevel)
                ->delete('att_pending_request_approval');
            $this->db->insert('att_pending_request_approval', [
                'pending_request_id' => $requestId,
                'approval_level' => $currentLevel,
                'approver_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'action' => $action === 'APPROVE' ? 'APPROVED' : 'REJECTED',
                'notes' => $notes !== '' ? $notes : null,
                'acted_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($action === 'REJECT') {
            $this->db->where('id', $requestId)->update('att_pending_request', [
                'status' => 'REJECTED',
                'approved_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $notes !== '' ? $notes : 'Ditolak pada level ' . $currentLevel,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->trans_complete();
            return ['ok' => $this->db->trans_status(), 'message' => 'Pengajuan ditolak.'];
        }

        if ($forceFinalApprove || $currentLevel >= $approvalLevels) {
            $apply = $this->apply_pending_request_to_daily($req, $notes);
            if (!$apply['ok']) {
                $this->db->trans_rollback();
                return $apply;
            }

            $this->db->where('id', $requestId)->update('att_pending_request', [
                'status' => 'APPROVED',
                'approved_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $notes !== '' ? $notes : ($forceFinalApprove ? 'Disetujui override final' : 'Disetujui final'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->trans_complete();
            return ['ok' => $this->db->trans_status(), 'message' => ($forceFinalApprove ? 'Pengajuan override final disetujui' : 'Pengajuan disetujui final') . ' dan rekap harian diperbarui.'];
        }

        $this->db->where('id', $requestId)->update('att_pending_request', [
            'approval_notes' => $notes !== '' ? $notes : ('Disetujui level ' . $currentLevel),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->trans_complete();

        return ['ok' => $this->db->trans_status(), 'message' => 'Pengajuan disetujui level ' . $currentLevel . '. Menunggu verifier level berikutnya.'];
    }

    private function can_verify_level(int $policyId, int $level, int $actorEmployeeId): bool
    {
        if ($actorEmployeeId <= 0 || $policyId <= 0 || $level <= 0) {
            return false;
        }

        if (!$this->db->table_exists('att_pending_verifier_position')) {
            return false;
        }

        $employee = $this->db->select('position_id')
            ->from('org_employee')
            ->where('id', $actorEmployeeId)
            ->limit(1)
            ->get()->row_array();
        $positionId = (int)($employee['position_id'] ?? 0);
        if ($positionId <= 0) {
            return false;
        }

        $exists = $this->db->select('id')
            ->from('att_pending_verifier_position')
            ->where('policy_id', $policyId)
            ->where('verify_level', $level)
            ->where('position_id', $positionId)
            ->limit(1)
            ->get()->row_array();

        return !empty($exists);
    }

    private function apply_pending_request_to_daily(array $req, string $notes = ''): array
    {
        $employeeId = (int)($req['employee_id'] ?? 0);
        $date = (string)($req['request_date'] ?? '');
        $requestType = strtoupper((string)($req['request_type'] ?? ''));
        if ($employeeId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data pengajuan tidak valid untuk diterapkan.'];
        }

        $daily = $this->db->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();

        $coverage = $this->validate_daily_compensation_contract_coverage($employeeId, $date, $daily ?: null);
        if (empty($coverage['ok'])) {
            return $coverage;
        }

        $schedule = $this->db->select('ss.shift_id, s.start_time, s.end_time, s.is_overnight, s.grace_late_minute')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date', $date)
            ->limit(1)
            ->get()->row_array();
        if (!is_array($schedule)) {
            $schedule = [];
        }

        $shiftId = (int)($daily['shift_id'] ?? 0);
        if ($shiftId <= 0) {
            $shiftId = (int)($schedule['shift_id'] ?? 0);
        }
        if ($shiftId > 0 && empty($schedule)) {
            $fallbackShift = $this->db
                ->select('id AS shift_id, start_time, end_time, is_overnight, grace_late_minute')
                ->from('att_shift')
                ->where('id', $shiftId)
                ->limit(1)
                ->get()
                ->row_array();
            if (is_array($fallbackShift)) {
                $schedule = $fallbackShift;
            }
        }

        $checkinAt = (string)($daily['checkin_at'] ?? '');
        $checkoutAt = (string)($daily['checkout_at'] ?? '');
        $status = (string)($daily['attendance_status'] ?? 'OFF');
        if ($requestType === 'MISSING_CHECKIN' && !empty($req['requested_checkin_at'])) {
            $checkinAt = (string)$req['requested_checkin_at'];
        } elseif ($requestType === 'MISSING_CHECKOUT' && !empty($req['requested_checkout_at'])) {
            $checkoutAt = (string)$req['requested_checkout_at'];
        } elseif ($requestType === 'STATUS_CORRECTION' && !empty($req['requested_status'])) {
            if (strtoupper((string)$req['requested_status']) === 'HOLIDAY') {
                return ['ok' => false, 'message' => 'Status HOLIDAY tidak boleh diajukan manual. Gunakan jadwal shift PH untuk mekanisme PH pegawai.'];
            }
            $status = (string)$req['requested_status'];
        } elseif (in_array($requestType, ['LEAVE', 'SICK'], true)) {
            $status = $requestType === 'LEAVE' ? 'LEAVE' : 'SICK';
            $checkinAt = '';
            $checkoutAt = '';
        }

        [$startTs, $endTs] = $this->shift_bounds_from_schedule($date, $schedule);
        $inTs = $checkinAt !== '' ? strtotime($checkinAt) : 0;
        $outTs = $checkoutAt !== '' ? strtotime($checkoutAt) : 0;
        $policy = $this->get_active_policy();

        if ($inTs > 0 && $outTs > 0) {
            $outTsNormalized = $this->normalize_checkout_timestamp($inTs, $outTs, $schedule);
            if ($outTsNormalized !== $outTs) {
                $outTs = $outTsNormalized;
                $checkoutAt = date('Y-m-d H:i:s', $outTs);
            }
        }

        if ($outTs > 0) {
            $outTsCredited = $this->apply_checkout_credit_to_operation_end($outTs, $date, $policy);
            if ($outTsCredited !== $outTs) {
                $outTs = $outTsCredited;
                $checkoutAt = date('Y-m-d H:i:s', $outTs);
            }
        }

        [$lateMinutes, $workMinutes, $statusDerived] = $this->compute_daily_metrics(
            $date,
            $checkinAt,
            $checkoutAt,
            $schedule
        );
        if (!in_array($requestType, ['LEAVE', 'SICK', 'STATUS_CORRECTION'], true)) {
            $status = $statusDerived;
        }

        $scheduledWorkMinutes = ($startTs > 0 && $endTs > $startTs) ? (int)floor(($endTs - $startTs) / 60) : 0;
        $overtimeMode = strtoupper((string)($policy['overtime_calc_mode'] ?? 'AUTO'));
        if (!in_array($overtimeMode, ['AUTO', 'MANUAL'], true)) {
            $overtimeMode = 'AUTO';
        }

        $overtimeMinutes = 0;
        $manualOvertimePay = 0.0;
        if ($overtimeMode === 'MANUAL') {
            if ($workMinutes > 0 && $scheduledWorkMinutes > 0) {
                $workMinutes = min($workMinutes, $scheduledWorkMinutes);
            }
            $manualOvertimePay = $this->get_manual_overtime_pay($employeeId, $date);
            $overtimeMinutes = 0;
        } elseif ($inTs > 0 && $outTs > $inTs && $endTs > 0 && $outTs > $endTs) {
            $overtimeMinutes = (int)floor(($outTs - $endTs) / 60);
        }

        $payload = [
            'shift_id' => $shiftId > 0 ? $shiftId : null,
            'checkin_at' => $checkinAt !== '' ? $checkinAt : null,
            'checkout_at' => $checkoutAt !== '' ? $checkoutAt : null,
            'attendance_status' => $status,
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => ($outTs > 0 && $endTs > 0 && $outTs < $endTs) ? max(0, (int)floor(($endTs - $outTs) / 60)) : 0,
            'overtime_minutes' => max(0, $overtimeMinutes),
            'source_type' => 'PENDING_APPROVAL',
            'remarks' => $notes !== '' ? $notes : ('Approved pending request #' . (int)$req['id']),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($daily) {
            $this->db->where('id', (int)$daily['id'])->update('att_daily', $payload);
        } else {
            $this->db->insert('att_daily', [
                'attendance_date' => $date,
                'employee_id' => $employeeId,
                'created_at' => date('Y-m-d H:i:s'),
            ] + $payload);
        }

        $dailyRow = $this->db->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();
        if ($dailyRow) {
            $dailyPayrollPayload = $this->build_daily_payroll_payload($dailyRow, $policy, $employeeId, $manualOvertimePay);
            if (!empty($dailyPayrollPayload)) {
                $this->db->where('id', (int)$dailyRow['id'])->update('att_daily', $dailyPayrollPayload);
            }
            $this->sync_ph_grant_for_employee_date($employeeId, $date, 0);
            $use = $this->sync_ph_use_for_employee_date($employeeId, $date, 0);
            if (empty($use['ok'])) {
                return $use;
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    private function compute_daily_metrics(string $date, string $checkinAt, string $checkoutAt, array $schedule): array
    {
        $lateMinutes = 0;
        $workMinutes = 0;
        $status = 'OFF';

        $startTime = (string)($schedule['start_time'] ?? '');
        $endTime = (string)($schedule['end_time'] ?? '');
        $isOvernight = (int)($schedule['is_overnight'] ?? 0);
        $grace = max(0, (int)($schedule['grace_late_minute'] ?? 0));

        $startTs = ($startTime !== '') ? strtotime($date . ' ' . $startTime) : 0;
        $endTs = ($endTime !== '') ? strtotime($date . ' ' . $endTime) : 0;
        if ($startTs > 0 && $endTs > 0 && ($isOvernight === 1 || $endTs <= $startTs)) {
            $endTs = strtotime('+1 day', $endTs);
        }

        $inTs = $checkinAt !== '' ? strtotime($checkinAt) : 0;
        $outTs = $checkoutAt !== '' ? strtotime($checkoutAt) : 0;
        $outTs = $this->normalize_checkout_timestamp($inTs, $outTs, $schedule);

        if ($inTs > 0 && $startTs > 0) {
            $lateMinutes = max(0, ((int)floor(($inTs - $startTs) / 60)) - $grace);
        }
        if ($inTs > 0 && $outTs > $inTs) {
            $workMinutes = (int)floor(($outTs - $inTs) / 60);
        }

        if ($inTs > 0 || $outTs > 0) {
            $status = $lateMinutes > 0 ? 'LATE' : 'PRESENT';
        }

        return [$lateMinutes, max(0, $workMinutes), $status];
    }

    private function normalize_checkout_timestamp(int $checkinTs, int $checkoutTs, array $schedule): int
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

    private function shift_bounds_from_schedule(string $date, array $schedule): array
    {
        $startTime = (string)($schedule['start_time'] ?? '');
        $endTime = (string)($schedule['end_time'] ?? '');
        $isOvernight = (int)($schedule['is_overnight'] ?? 0);

        $startTs = ($startTime !== '') ? strtotime($date . ' ' . $startTime) : 0;
        $endTs = ($endTime !== '') ? strtotime($date . ' ' . $endTime) : 0;
        if ($startTs > 0 && $endTs > 0 && ($isOvernight === 1 || $endTs <= $startTs)) {
            $endTs = strtotime('+1 day', $endTs);
        }
        return [$startTs ?: 0, $endTs ?: 0];
    }

    private function apply_checkout_credit_to_operation_end(int $checkoutTs, string $date, array $policy): int
    {
        if ($checkoutTs <= 0 || (int)($policy['night_shift_checkout_credit_to_operation_end'] ?? 1) !== 1) {
            return $checkoutTs;
        }

        $creditAfter = trim((string)($policy['night_shift_checkout_credit_after'] ?? ''));
        $opStart = trim((string)($policy['operation_start_time'] ?? ''));
        $opEnd = trim((string)($policy['operation_end_time'] ?? ''));
        if ($creditAfter === '' || $opStart === '' || $opEnd === '') {
            return $checkoutTs;
        }

        $checkoutClock = date('H:i:s', $checkoutTs);
        if ($checkoutClock < $creditAfter) {
            return $checkoutTs;
        }

        $opEndTs = strtotime($date . ' ' . $opEnd);
        if ($opEndTs <= 0) {
            return $checkoutTs;
        }
        if ($opEnd < $opStart) {
            $opEndTs = strtotime('+1 day', $opEndTs);
        }

        if ($opEndTs > 0 && $checkoutTs < $opEndTs) {
            return (int)$opEndTs;
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

    private function build_daily_payroll_payload(array $dailyRow, array $policy, int $employeeId, float $manualOvertimePay = 0.0): array
    {
        $checkinTs = !empty($dailyRow['checkin_at']) ? strtotime((string)$dailyRow['checkin_at']) : 0;
        $checkoutTs = !empty($dailyRow['checkout_at']) ? strtotime((string)$dailyRow['checkout_at']) : 0;
        $scheduleMeta = [
            'start_time' => (string)($dailyRow['start_time'] ?? ''),
            'end_time' => (string)($dailyRow['end_time'] ?? ''),
            'is_overnight' => (int)($dailyRow['is_overnight'] ?? 0),
        ];
        if ($checkinTs > 0 && $checkoutTs > 0 && ($scheduleMeta['start_time'] === '' || $scheduleMeta['end_time'] === '')) {
            $shiftMeta = $this->db->select('start_time, end_time, is_overnight')
                ->from('att_shift')
                ->where('id', (int)($dailyRow['shift_id'] ?? 0))
                ->limit(1)
                ->get()->row_array();
            if ($shiftMeta) {
                $scheduleMeta = $shiftMeta;
            }
        }
        $checkoutTs = $this->normalize_checkout_timestamp($checkinTs, $checkoutTs, $scheduleMeta);
        $hasCompletedCheckout = ($checkinTs > 0 && $checkoutTs > $checkinTs);
        $attendanceDate = (string)($dailyRow['attendance_date'] ?? '');

        $compensationContext = $this->resolve_daily_compensation($employeeId, $attendanceDate, $dailyRow);
        $compensation = (array)($compensationContext['resolved'] ?? []);
        $basicSalary = (float)($compensation['basic_salary'] ?? 0);
        $positionAllowance = (float)($compensation['position_allowance'] ?? 0);
        $objectiveAllowance = (float)($compensation['objective_allowance'] ?? 0);
        $mealRate = (float)($compensation['meal_rate'] ?? 0);

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
        $overtimeMode = strtoupper((string)($policy['overtime_calc_mode'] ?? 'AUTO'));
        if (!in_array($overtimeMode, ['AUTO', 'MANUAL'], true)) {
            $overtimeMode = 'AUTO';
        }
        // Automatic overtime is governed by the configured master standard,
        // never by a salary-derived rate in an employee contract.
        $overtimeRate = $this->get_policy_overtime_rate($policy);

        $status = strtoupper((string)($dailyRow['attendance_status'] ?? 'OFF'));
        $lateMinutes = (int)($dailyRow['late_minutes'] ?? 0);
        $workMinutes = max(0, (int)($dailyRow['work_minutes'] ?? 0));
        $scheduledWorkMinutes = $this->resolve_scheduled_work_minutes(
            $employeeId,
            $attendanceDate,
            (int)($dailyRow['shift_id'] ?? 0)
        );
        $isPresentish = in_array($status, ['PRESENT', 'LATE', 'HOLIDAY'], true);
        $isHolidayPaidDay = ($status === 'HOLIDAY');
        $isPayrollPaidDay = ($hasCompletedCheckout || $isHolidayPaidDay);
        $isCheckedIn = $checkinTs > 0;
        $phGetsMealAllowance = (int)($policy['ph_gets_meal_allowance'] ?? 0) === 1;
        $allowanceEligible = $isPresentish;
        if ($allowanceEligible && $allowanceLateTreatment === 'DEDUCT_IF_LATE' && $status === 'LATE') {
            $allowanceEligible = false;
        }

        $basicAmount = 0.0;
        $allowanceAmount = 0.0;
        $mealAmount = 0.0;
        $overtimePay = 0.0;
        $lateDeduction = 0.0;
        $alphaDeduction = 0.0;
        $grossAmount = 0.0;
        $netAmount = 0.0;

        $mealAmount = 0.0;
        // Both modes earn the same daily meal entitlement. The mode controls
        // its settlement: MONTHLY is included in payroll, while CUSTOM is
        // recorded for a separate meal-disbursement batch.
        if ($isPresentish && $isCheckedIn) {
            $mealAmount = $mealRate;
        } elseif ($isHolidayPaidDay && $phGetsMealAllowance) {
            $mealAmount = $mealRate;
        }

        if ($isPayrollPaidDay) {
            $basicAmount = $isPresentish ? $basicDailyRate : 0;
            $allowanceAmount = $allowanceEligible ? $allowanceDailyRate : 0;
            if ($hasCompletedCheckout && $overtimeMode === 'MANUAL') {
                $overtimePay = max(0, $manualOvertimePay);
            } elseif ($hasCompletedCheckout) {
                $overtimeMinutes = max(0, (int)($dailyRow['overtime_minutes'] ?? 0));
                $overtimePay = ($overtimeMinutes > 0 && $overtimeRate > 0)
                    ? (($overtimeMinutes / 60) * $overtimeRate)
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
                        $deductionFromProrate = ($basicAmount + $allowanceAmount + $mealAmount) * (1 - $ratio);
                    } else {
                        $deductionFromProrate = $basicAmount * (1 - $ratio);
                    }
                } else {
                    $missingRatio = max(0, 1 - $ratio);
                    if ($prorateScope === 'THP_TOTAL') {
                        $deductionFromProrate = (($basicDailyRate + $allowanceDailyRate + $mealAmount) * $missingRatio);
                    } else {
                        $deductionFromProrate = ($basicDailyRate * $missingRatio);
                    }
                }
                $lateDeduction += round($deductionFromProrate, 2);
            }

            $grossAmount = round($basicAmount + $allowanceAmount + $mealAmount + $overtimePay, 2);
            $baseForNet = ($mealMode === 'CUSTOM') ? ($grossAmount - $mealAmount) : $grossAmount;
            $netAmount = round($baseForNet - ($lateDeduction + $alphaDeduction), 2);
        }

        $manualAdj = $this->get_manual_adjustment_totals_by_date($employeeId, $attendanceDate);
        $manualAddition = (float)($manualAdj['addition'] ?? 0);
        $manualDeduction = (float)($manualAdj['deduction'] ?? 0);
        $manualNet = (float)($manualAdj['net'] ?? 0);

        $payload = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $payload += $this->build_daily_policy_lock_payload($policy);
        if ($this->att_daily_has_field('snapshot_basic_salary')) {
            $payload['snapshot_basic_salary'] = round($basicSalary, 2);
            $payload['snapshot_position_allowance'] = round($positionAllowance, 2);
            $payload['snapshot_objective_allowance'] = round($objectiveAllowance, 2);
            $payload['snapshot_meal_rate'] = round($mealRate, 2);
            $payload['snapshot_overtime_rate'] = round($overtimeRate, 2);
        }
        $payload += $this->daily_compensation_provenance_payload($dailyRow, $compensationContext);
        if ($this->att_daily_has_field('basic_amount')) {
            $payload['basic_amount'] = round($basicAmount, 2);
            $payload['allowance_amount'] = round($allowanceAmount, 2);
            $payload['meal_amount'] = round($mealAmount, 2);
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

        return $payload;
    }

    private function resolve_scheduled_work_minutes(int $employeeId, string $date, int $shiftId): int
    {
        if ($date === '') {
            return 0;
        }

        $shift = null;
        if ($shiftId > 0) {
            $shift = $this->db->select('start_time, end_time, is_overnight')
                ->from('att_shift')
                ->where('id', $shiftId)
                ->limit(1)
                ->get()->row_array();
        }
        if (!$shift && $employeeId > 0) {
            $shift = $this->db->select('s.start_time, s.end_time, s.is_overnight')
                ->from('att_shift_schedule ss')
                ->join('att_shift s', 's.id = ss.shift_id', 'left')
                ->where('ss.employee_id', $employeeId)
                ->where('ss.schedule_date', $date)
                ->limit(1)
                ->get()->row_array();
        }
        if (!$shift) {
            return 0;
        }

        [$startTs, $endTs] = $this->shift_bounds_from_schedule($date, $shift);
        if ($startTs <= 0 || $endTs <= $startTs) {
            return 0;
        }
        return max(0, (int)floor(($endTs - $startTs) / 60));
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
}
