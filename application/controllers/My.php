<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class My extends MY_Controller
{
    private const PAGE_HOME = 'my.home.index';
    private const PAGE_ATTENDANCE = 'my.attendance.index';
    private const PAGE_PROFILE = 'my.profile.index';
    private const PAGE_SCHEDULE = 'my.schedule.index';
    private const PAGE_PAYROLL = 'my.payroll.index';
    private const PAGE_LEAVE = 'my.leave.index';
    private const PAGE_MEAL = 'my.meal.index';
    private const PAGE_OVERTIME = 'my.overtime.index';
    private const PAGE_PH = 'my.ph.index';
    private const PAGE_ADJUSTMENT = 'my.adjustment.index';
    private const PAGE_CASH_ADVANCE = 'my.cash_advance.index';
    private const PAGE_BONUS = 'my.bonus.index';

    /** @var array<string,bool> */
    private $registeredPageCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('My_portal_model');
        $this->load->model('Attendance_model');
        $this->load->model('Payroll_preview_model');
        $this->load->model('Payroll_model');
        $this->load->model('Hr_contract_model');
        $this->sync_profile_portal_registry();
    }

    private function selected_employee_id(): int
    {
        $sessionEmployeeId = (int)($this->current_user['employee_id'] ?? 0);
        if ($sessionEmployeeId > 0) {
            return $sessionEmployeeId;
        }

        if ($this->is_superadmin()) {
            return max(0, (int)$this->input->get('employee_id', true));
        }

        return 0;
    }

    private function per_page(string $param = 'per_page'): int
    {
        $pp = (int)$this->input->get($param, true);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    private function page(string $param = 'page'): int
    {
        return max(1, (int)$this->input->get($param, true));
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

    public function index()
    {
        $this->require_registered_page_permission(self::PAGE_HOME);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        $attendanceAlerts = [];
        $revisionRule = $this->My_portal_model->attendance_revision_window_rule();
        $revisionWindowDays = (int)($revisionRule['days'] ?? 7);
        $revisionMinDate = !empty($revisionRule['enabled'])
            ? date('Y-m-d', strtotime(date('Y-m-d') . ' -' . $revisionWindowDays . ' day'))
            : '';
        $revisionMaxDate = date('Y-m-d');
        $revisionNoticeText = $this->My_portal_model->attendance_revision_notice_text($revisionRule);
        if ($employee) {
            $policy = $this->My_portal_model->get_active_policy();
            $attendanceAlerts = $this->My_portal_model->get_attendance_gap_alerts((int)$employee['id'], date('Y-m-d'), $policy);
        }

        $leaveUrl = site_url('my/leave-requests' . (!empty($employeeId) && $this->is_superadmin() ? ('?employee_id=' . $employeeId) : ''));

        $data = [
            'title' => 'Portal Pegawai',
            'active_menu' => 'my.home',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'attendance_alerts' => $attendanceAlerts,
            'revision_window_rule' => $revisionRule,
            'revision_window_days' => $revisionWindowDays,
            'revision_min_date' => $revisionMinDate,
            'revision_max_date' => $revisionMaxDate,
            'revision_notice_text' => $revisionNoticeText,
            'leave_url' => $leaveUrl,
            'bonus_target_summary' => $employee ? $this->Payroll_model->get_my_bonus_target_summary(date('Y-m')) : [],
        ];
        $this->render('my/index', $data);
    }

    public function attendance()
    {
        $this->require_registered_page_permission(self::PAGE_ATTENDANCE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $today = date('Y-m-d');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $rows = [];
        $policy = $this->My_portal_model->get_active_policy();
        $todaySchedule = null;
        $todayPresence = ['checkin_count' => 0, 'checkout_count' => 0, 'last_checkin_at' => null, 'last_checkout_at' => null];
        $pendingPeerFeedback = [];
        $pg = ['total' => 0, 'per_page' => $this->per_page(), 'page' => 1, 'total_pages' => 1, 'offset' => 0];
        $locationOptions = $this->Attendance_model->get_active_locations();
        $defaultLocationId = $this->Attendance_model->get_default_location_id();
        $phAutoResult = null;

        if ($employee) {
            $phAutoResult = $this->My_portal_model->ensure_auto_ph_presence((int)$employee['id'], $today, $policy);
            $todaySchedule = $this->My_portal_model->get_schedule_with_shift((int)$employee['id'], $today);
            $todayPresence = $this->My_portal_model->get_today_presence_state((int)$employee['id'], $today);
            $pendingPeerFeedback = $this->Payroll_model->get_pending_peer_feedback_targets((int)$employee['id'], $today);

            $perPage = $this->per_page();
            $page = $this->page();
            $total = $this->My_portal_model->count_my_daily((int)$employee['id'], $filters);
            $pg = $this->build_pagination($total, $perPage, $page);
            $rows = $this->My_portal_model->list_my_daily((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        }

        $data = [
            'title' => 'Absensi Saya',
            'active_menu' => 'my.attendance',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'policy' => $policy,
            'today' => $today,
            'today_schedule' => $todaySchedule,
            'today_presence' => $todayPresence,
            'ph_auto_result' => $phAutoResult,
            'pending_peer_feedback' => $pendingPeerFeedback,
            'location_options' => $locationOptions,
            'default_location_id' => $defaultLocationId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PRESENT', 'LATE', 'ALPHA', 'SICK', 'LEAVE', 'OFF', 'HOLIDAY'],
        ];
        $this->render('my/attendance', $data);
    }

    public function attendance_mark()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_registered_page_permission(self::PAGE_ATTENDANCE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my/attendance');
        }

        $eventType = strtoupper(trim((string)$this->input->post('event_type', true)));
        $locationId = (int)$this->input->post('location_id', true);
        $latitude = $this->input->post('latitude', true);
        $longitude = $this->input->post('longitude', true);
        $latVal = ($latitude === '' || $latitude === null) ? null : (float)$latitude;
        $lonVal = ($longitude === '' || $longitude === null) ? null : (float)$longitude;
        $policy = $this->My_portal_model->get_active_policy();
        $result = $this->My_portal_model->mark_attendance((int)$employee['id'], date('Y-m-d'), $eventType, $policy, $locationId, $latVal, $lonVal);
        if (!empty($result['ok'])) {
            // PH grant/use is synchronized inside the attendance transaction.
            // Do not retry it after commit: a post-commit error would make a
            // successful attendance look failed to the employee.
            $pendingPeerFeedback = $this->Payroll_model->get_pending_peer_feedback_targets((int)$employee['id'], date('Y-m-d'));
            if (!empty($pendingPeerFeedback)) {
                $this->session->set_flashdata('warning', 'Masih ada rekan kerja hari ini yang belum Anda nilai. Buka Bonus Saya untuk mengisi penilaian 360.');
            }
        }
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses absensi.'));

        $redirectParams = [];
        if ($this->is_superadmin()) {
            $redirectParams['employee_id'] = (int)$employee['id'];
        }
        $suffix = $redirectParams ? ('?' . http_build_query($redirectParams)) : '';
        redirect('my/attendance' . $suffix);
    }

    public function profile()
    {
        $this->require_registered_page_permission(self::PAGE_PROFILE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        $sessionEmployeeId = (int)($this->current_user['employee_id'] ?? 0);
        $contractRows = [];
        $selectedContractId = max(0, (int)$this->input->get('contract_id', true));
        $selectedContract = null;
        $approvalMap = [];
        $signatureMap = [];
        $canEmployeeSign = false;

        if ($employee) {
            $contractRows = $this->My_portal_model->list_my_contracts((int)$employee['id']);
            if ($selectedContractId <= 0 && !empty($contractRows)) {
                $selectedContractId = (int)($contractRows[0]['id'] ?? 0);
            }
            if ($selectedContractId > 0) {
                $this->Hr_contract_model->refresh_document_verification($selectedContractId);
                $selectedContract = $this->My_portal_model->get_my_contract_detail((int)$employee['id'], $selectedContractId);
                if (!$selectedContract && !empty($contractRows)) {
                    $selectedContractId = (int)($contractRows[0]['id'] ?? 0);
                    if ($selectedContractId > 0) {
                        $this->Hr_contract_model->refresh_document_verification($selectedContractId);
                        $selectedContract = $this->My_portal_model->get_my_contract_detail((int)$employee['id'], $selectedContractId);
                    }
                }
                if ($selectedContract) {
                    $contractStatus = strtoupper(trim((string)($selectedContract['status'] ?? 'DRAFT')));
                    $canEmployeeSign = $sessionEmployeeId > 0
                        && (int)$employee['id'] === $sessionEmployeeId
                        && in_array($contractStatus, ['GENERATED', 'SIGNED'], true);
                    foreach ((array)($selectedContract['approvals'] ?? []) as $approval) {
                        $approvalMap[(string)($approval['approver_role'] ?? '')] = $approval;
                    }
                    foreach ((array)($selectedContract['signatures'] ?? []) as $signature) {
                        $signatureMap[(string)($signature['signer_role'] ?? '')] = $signature;
                    }
                }
            }
        }

        $this->render('my/profile', [
            'title' => 'Kontrak Saya',
            'active_menu' => 'my.profile',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'contract_rows' => $contractRows,
            'selected_contract_id' => $selectedContractId,
            'selected_contract' => $selectedContract,
            'approval_map' => $approvalMap,
            'signature_map' => $signatureMap,
            'can_employee_sign' => $canEmployeeSign,
        ]);
    }

    public function profile_contract_sign(int $contractId)
    {
        $this->require_registered_page_permission(self::PAGE_PROFILE);

        if ($this->input->method() !== 'post') {
            redirect('my/profile');
            return;
        }

        $sessionEmployeeId = (int)($this->current_user['employee_id'] ?? 0);
        if ($sessionEmployeeId <= 0) {
            $this->session->set_flashdata('error', 'Hanya akun pegawai yang terhubung langsung yang dapat menandatangani kontrak.');
            redirect('my/profile');
            return;
        }

        $employee = $this->My_portal_model->get_employee_by_id($sessionEmployeeId);
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai untuk akun ini tidak ditemukan.');
            redirect('my/profile');
            return;
        }

        $contract = $this->My_portal_model->get_my_contract_detail($sessionEmployeeId, $contractId);
        if (!$contract) {
            $this->session->set_flashdata('error', 'Kontrak tidak ditemukan atau bukan milik akun ini.');
            redirect('my/profile');
            return;
        }

        if ((string)$this->input->post('agree_contract', true) !== '1') {
            $this->session->set_flashdata('error', 'Centang persetujuan kontrak sebelum menandatangani.');
            redirect('my/profile?contract_id=' . (int)$contractId);
            return;
        }

        $signerName = trim((string)($employee['employee_name'] ?? ''));
        if ($signerName === '') {
            $signerName = trim((string)($contract['employee_name'] ?? ''));
        }

        $result = $this->Hr_contract_model->portal_employee_signoff(
            $contractId,
            $signerName,
            (int)($this->current_user['id'] ?? 0),
            trim((string)$this->input->post('signature_data', false)),
            trim((string)$this->input->post('approval_note', true))
        );

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan persetujuan kontrak.'));
        redirect('my/profile?contract_id=' . (int)$contractId);
    }

    public function profile_contract_print(int $contractId)
    {
        $this->require_registered_page_permission(self::PAGE_PROFILE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            show_404();
            return;
        }

        $this->Hr_contract_model->refresh_document_verification($contractId);
        $row = $this->My_portal_model->get_my_contract_detail((int)$employee['id'], $contractId);
        if (!$row) {
            show_404();
            return;
        }

        $approvalMap = [];
        foreach ((array)($row['approvals'] ?? []) as $approval) {
            $approvalMap[(string)($approval['approver_role'] ?? '')] = $approval;
        }

        $signatureMap = [];
        foreach ((array)($row['signatures'] ?? []) as $signature) {
            $signatureMap[(string)($signature['signer_role'] ?? '')] = $signature;
        }

        $backQuery = [];
        if ($this->is_superadmin()) {
            $backQuery['employee_id'] = (int)$employee['id'];
        }
        $backQuery['contract_id'] = (int)$contractId;
        $backUrl = site_url('my/profile' . '?' . http_build_query($backQuery));

        $this->load->view('hr_contract/print', [
            'row' => $row,
            'approval_map' => $approvalMap,
            'signature_map' => $signatureMap,
            'verify_url' => site_url('hr-contracts/verify/' . (string)($row['verification_token'] ?? '')),
            'ctx' => 'my',
            'back_url' => $backUrl,
            'business_profile' => $this->business_profile(),
        ]);
    }

    public function schedule()
    {
        $this->require_registered_page_permission(self::PAGE_SCHEDULE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $dateStart = $month . '-01';
        $dateEnd = date('Y-m-t', strtotime($dateStart));
        $holidayDates = $this->Attendance_model->get_holiday_dates_between($dateStart, $dateEnd);
        $holidayMap = array_fill_keys($holidayDates, true);

        $scheduleRows = [];
        $scheduleMap = [];
        if ($employee) {
            $scheduleRows = $this->My_portal_model->list_monthly_schedules((int)$employee['id'], $dateStart, $dateEnd);
            foreach ($scheduleRows as $row) {
                $scheduleDate = (string)($row['schedule_date'] ?? '');
                if ($scheduleDate !== '') {
                    $scheduleMap[$scheduleDate] = $row;
                }
            }
        }

        $days = [];
        $scheduledCount = 0;
        $holidayCount = 0;
        for ($ts = strtotime($dateStart); $ts <= strtotime($dateEnd); $ts = strtotime('+1 day', $ts)) {
            $date = date('Y-m-d', $ts);
            $row = $scheduleMap[$date] ?? null;
            $isHoliday = isset($holidayMap[$date]);
            if ($row) {
                $scheduledCount++;
            }
            if ($isHoliday) {
                $holidayCount++;
            }

            $days[] = [
                'date' => $date,
                'day_label' => date('d M Y', $ts),
                'dow_label' => date('D', $ts),
                'is_today' => $date === date('Y-m-d'),
                'is_holiday' => $isHoliday,
                'schedule' => $row,
            ];
        }

        $this->render('my/schedule', [
            'title' => 'Jadwal Shift Saya',
            'active_menu' => 'my.schedule',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'month' => $month,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'days' => $days,
            'summary' => [
                'total_days' => count($days),
                'scheduled_days' => $scheduledCount,
                'unscheduled_days' => max(0, count($days) - $scheduledCount),
                'holiday_days' => $holidayCount,
            ],
        ]);
    }

    public function payroll()
    {
        $this->require_registered_page_permission(self::PAGE_PAYROLL);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $dateStart = trim((string)$this->input->get('date_start', true));
        $dateEnd = trim((string)$this->input->get('date_end', true));
        if ($dateStart === '') {
            $dateStart = date('Y-m-01');
        }
        if ($dateEnd === '') {
            $dateEnd = date('Y-m-t');
        }

        $summary = null;
        $dailyRows = [];
        $generatedRows = [];
        $generatedSummary = ['count' => 0, 'total_transfer' => 0.0];
        if ($employee) {
            [$summary, $dailyRows] = $this->Payroll_preview_model->estimate_employee_attendance_payroll((int)$employee['id'], $dateStart, $dateEnd);
            $generatedRows = $this->Payroll_model->list_generated_salary_lines_by_employee((int)$employee['id'], $dateStart, $dateEnd, 100, 0);
            $generatedSummary['count'] = count($generatedRows);
            foreach ($generatedRows as $gr) {
                $generatedSummary['total_transfer'] += (float)($gr['transfer_amount'] ?? 0);
            }
            $generatedSummary['total_transfer'] = round((float)$generatedSummary['total_transfer'], 2);
        }

        $this->render('my/payroll', [
            'title' => 'Estimasi Gaji Saya',
            'active_menu' => 'my.payroll',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'summary' => $summary,
            'daily_rows' => $dailyRows,
            'generated_rows' => $generatedRows,
            'generated_summary' => $generatedSummary,
        ]);
    }

    public function payroll_slip(int $lineId)
    {
        $this->require_registered_page_permission(self::PAGE_PAYROLL);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            show_404();
            return;
        }
        $line = $this->Payroll_model->get_salary_disbursement_line_slip($lineId, (int)$employee['id']);
        if (!$line) {
            show_404();
            return;
        }
        $this->load->view('payroll/salary_slip', [
            'line' => $line,
            'context' => 'my',
        ]);
    }

    public function leave_requests()
    {
        $this->require_registered_page_permission(self::PAGE_LEAVE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        if ($this->input->method() === 'post') {
            $result = $this->My_portal_model->create_leave_request((int)$employee['id'], [
                'request_date' => trim((string)$this->input->post('request_date', true)),
                'request_type' => strtoupper(trim((string)$this->input->post('request_type', true))),
                'requested_checkin_at' => trim((string)$this->input->post('requested_checkin_at', true)),
                'requested_checkout_at' => trim((string)$this->input->post('requested_checkout_at', true)),
                'requested_status' => strtoupper(trim((string)$this->input->post('requested_status', true))),
                'reason' => trim((string)$this->input->post('reason', true)),
                'allow_past_revision_override' => $this->is_superadmin(),
            ]);
            $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan pengajuan.'));
            $suffix = $this->is_superadmin() ? ('?employee_id=' . (int)$employee['id']) : '';
            redirect('my/leave-requests' . $suffix);
            return;
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'request_type' => strtoupper(trim((string)$this->input->get('request_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_leave_requests((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_leave_requests((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        $revisionRule = $this->My_portal_model->attendance_revision_window_rule();
        $revisionWindowDays = (int)($revisionRule['days'] ?? 7);
        $revisionMinDate = !empty($revisionRule['enabled'])
            ? date('Y-m-d', strtotime(date('Y-m-d') . ' -' . $revisionWindowDays . ' day'))
            : '';
        $revisionMaxDate = date('Y-m-d');
        $revisionNoticeText = $this->My_portal_model->attendance_revision_notice_text($revisionRule);

        $this->render('my/leave_requests', [
            'title' => 'Pengajuan Absensi Saya',
            'active_menu' => 'my.leave',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
            'request_type_options' => ['LEAVE', 'SICK', 'MISSING_CHECKIN', 'MISSING_CHECKOUT', 'STATUS_CORRECTION'],
            'status_correction_options' => ['PRESENT', 'LATE', 'ALPHA', 'SICK', 'LEAVE', 'OFF'],
            'revision_window_rule' => $revisionRule,
            'revision_window_days' => $revisionWindowDays,
            'revision_min_date' => $revisionMinDate,
            'revision_max_date' => $revisionMaxDate,
            'revision_notice_text' => $revisionNoticeText,
        ]);
    }

    public function leave_request_cancel(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_registered_page_permission(self::PAGE_LEAVE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai tidak valid.');
            redirect('my/leave-requests');
            return;
        }

        $note = trim((string)$this->input->post('notes', true));
        $result = $this->Attendance_model->process_pending_request_action(
            $id,
            (int)$employee['id'],
            'CANCEL',
            $note,
            $this->is_superadmin()
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membatalkan pengajuan.'));

        $suffix = $this->is_superadmin() ? ('?employee_id=' . (int)$employee['id']) : '';
        redirect('my/leave-requests' . $suffix);
    }

    public function leave_request_schedule()
    {
        $this->require_registered_page_permission(self::PAGE_LEAVE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->output->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => 0, 'message' => 'Pegawai tidak ditemukan.']));
            return;
        }

        $date = trim((string)$this->input->get('date', true));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->output->set_status_header(422)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => 0, 'message' => 'Tanggal tidak valid.']));
            return;
        }

        $schedule = $this->My_portal_model->get_schedule_with_shift((int)$employee['id'], $date);
        if (!$schedule) {
            $this->output->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok' => 1,
                    'has_schedule' => 0,
                    'message' => 'Tidak ada jadwal shift pada tanggal ini.',
                ]));
            return;
        }

        $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => 1,
                'has_schedule' => 1,
                'shift_code' => (string)($schedule['shift_code'] ?? ''),
                'shift_name' => (string)($schedule['shift_name'] ?? ''),
                'start_time' => (string)($schedule['start_time'] ?? ''),
                'end_time' => (string)($schedule['end_time'] ?? ''),
                'is_overnight' => (int)($schedule['is_overnight'] ?? 0),
            ]));
    }

    public function meal_ledger()
    {
        $this->require_registered_page_permission(self::PAGE_MEAL);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_meal_ledger((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_meal_ledger((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        $summary = $this->My_portal_model->my_meal_ledger_summary((int)$employee['id'], $filters);

        $this->render('my/meal_ledger', [
            'title' => 'Uang Makan Saya',
            'active_menu' => 'my.meal',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'summary' => $summary,
            'status_options' => ['UNPAID', 'PENDING', 'PAID', 'FAILED', 'VOID'],
        ]);
    }

    public function overtime()
    {
        $this->require_registered_page_permission(self::PAGE_OVERTIME);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_overtime_entries((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_overtime_entries((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);

        $this->render('my/overtime', [
            'title' => 'Lembur Saya',
            'active_menu' => 'my.overtime',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED'],
        ]);
    }

    public function ph_ledger()
    {
        $this->require_registered_page_permission(self::PAGE_PH);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'tx_type' => strtoupper(trim((string)$this->input->get('tx_type', true))),
            'expired_state' => strtoupper(trim((string)$this->input->get('expired_state', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if (!in_array($filters['expired_state'], ['ALL', 'ACTIVE', 'EXPIRED'], true)) {
            $filters['expired_state'] = 'ALL';
        }
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01', strtotime('-3 month'));
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }
        $this->Attendance_model->sync_ph_expiry_ledger(date('Y-m-d'), (int)($this->current_user['id'] ?? 0));

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_ph_ledger((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_ph_ledger((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        $summary = $this->My_portal_model->my_ph_balance_summary((int)$employee['id']);

        $this->render('my/ph_ledger', [
            'title' => 'PH Saya',
            'active_menu' => 'my.ph',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'summary' => $summary,
            'tx_type_options' => ['GRANT', 'USE', 'EXPIRE', 'ADJUST', 'VOID'],
        ]);
    }

    public function manual_adjustments()
    {
        $this->require_registered_page_permission(self::PAGE_ADJUSTMENT);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'adjustment_kind' => strtoupper(trim((string)$this->input->get('adjustment_kind', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_manual_adjustments((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_manual_adjustments((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);

        $this->render('my/manual_adjustments', [
            'title' => 'Tambahan / Pengurangan Saya',
            'active_menu' => 'my.adjustment',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED'],
            'kind_options' => ['ADDITION', 'DEDUCTION'],
        ]);
    }

    public function cash_advance()
    {
        $this->require_registered_page_permission(self::PAGE_CASH_ADVANCE);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_cash_advances((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_cash_advances((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        foreach ($rows as &$row) {
            $row['installments'] = $this->My_portal_model->list_cash_advance_installments((int)($row['id'] ?? 0));
        }
        unset($row);

        $this->render('my/cash_advance', [
            'title' => 'Kasbon Saya',
            'active_menu' => 'my.cash_advance',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['DRAFT', 'APPROVED', 'REJECTED', 'SETTLED', 'VOID'],
        ]);
    }

    public function bonus()
    {
        @set_time_limit(180);
        $this->require_registered_page_permission(self::PAGE_BONUS);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $date = trim((string)$this->input->get('date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['summary', '360', 'history', 'penalties'], true)) {
            $tab = 'summary';
        }

        $dailyFilters = ['q' => trim((string)$this->input->get('daily_q', true))];
        $historyFilters = ['q' => trim((string)$this->input->get('history_q', true))];
        $penaltyTypeFilters = ['q' => trim((string)$this->input->get('penalty_type_q', true))];
        $penaltyFilters = [
            'q' => trim((string)$this->input->get('penalty_q', true)),
            'date_start' => trim((string)$this->input->get('penalty_date_start', true)),
            'date_end' => trim((string)$this->input->get('penalty_date_end', true)),
            'status' => strtoupper(trim((string)$this->input->get('penalty_status', true))),
        ];
        if (!in_array($penaltyFilters['status'], ['ALL', 'DRAFT', 'APPROVED', 'REJECTED'], true)) {
            $penaltyFilters['status'] = 'ALL';
        }
        if ($penaltyFilters['date_start'] === '') {
            $penaltyFilters['date_start'] = $month . '-01';
        }
        if ($penaltyFilters['date_end'] === '') {
            $penaltyFilters['date_end'] = date('Y-m-t', strtotime($penaltyFilters['date_start']));
        }
        $dailyPg = $this->build_pagination(
            $this->Payroll_model->count_my_bonus_daily_rows((int)$employee['id'], $month, $dailyFilters['q'], false),
            $this->per_page('daily_per_page'),
            $this->page('daily_page')
        );
        $historyPg = $this->build_pagination(
            $this->Payroll_model->count_my_peer_feedback_history((int)$employee['id'], $month, $historyFilters['q']),
            $this->per_page('history_per_page'),
            $this->page('history_page')
        );
        $penaltyTypePg = $this->build_pagination(
            $this->Payroll_model->count_bonus_penalty_types($penaltyTypeFilters['q']),
            $this->per_page('penalty_type_per_page'),
            $this->page('penalty_type_page')
        );
        $penaltyPg = $this->build_pagination(
            $this->Payroll_model->count_my_bonus_penalty_rows(
                (int)$employee['id'],
                $month,
                $penaltyFilters['q'],
                $penaltyFilters['date_start'],
                $penaltyFilters['date_end'],
                $penaltyFilters['status']
            ),
            $this->per_page('penalty_per_page'),
            $this->page('penalty_page')
        );

        $this->render('my/bonus', [
            'title' => 'Bonus Saya',
            'active_menu' => 'my.bonus',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'month' => $month,
            'date' => $date,
            'tab' => $tab,
            'daily_filters' => $dailyFilters,
            'history_filters' => $historyFilters,
            'penalty_type_filters' => $penaltyTypeFilters,
            'penalty_filters' => $penaltyFilters,
            'daily_pg' => $dailyPg,
            'history_pg' => $historyPg,
            'penalty_type_pg' => $penaltyTypePg,
            'penalty_pg' => $penaltyPg,
              'summary' => $this->Payroll_model->get_employee_bonus_overview((int)$employee['id'], $month),
              'target_summary' => $this->Payroll_model->get_my_bonus_target_summary($month),
              'daily_rows' => $this->Payroll_model->list_my_bonus_daily_rows((int)$employee['id'], $month, $dailyFilters['q'], $dailyPg['per_page'], $dailyPg['offset'], false),
              'penalty_rows' => $this->Payroll_model->list_my_bonus_penalty_rows(
                  (int)$employee['id'],
                  $month,
                  $penaltyFilters['q'],
                  $penaltyFilters['date_start'],
                  $penaltyFilters['date_end'],
                  $penaltyFilters['status'],
                  $penaltyPg['per_page'],
                  $penaltyPg['offset']
              ),
              'penalty_type_rows' => $this->Payroll_model->list_bonus_penalty_types($penaltyTypeFilters['q'], $penaltyTypePg['per_page'], $penaltyTypePg['offset']),
              'pending_peer_feedback' => $this->Payroll_model->get_pending_peer_feedback_targets((int)$employee['id'], $date),
              'peer_history_rows' => $this->Payroll_model->list_my_peer_feedback_history((int)$employee['id'], $month, $historyFilters['q'], $historyPg['per_page'], $historyPg['offset']),
          ]);
    }

    public function bonus_daily_detail(int $employeeDailyId)
    {
        $this->require_registered_page_permission(self::PAGE_BONUS);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $detail = $this->Payroll_model->get_my_bonus_daily_audit_detail($employeeDailyId, (int)$employee['id']);
        if (!$detail) {
            $this->session->set_flashdata('error', 'Detail bonus harian tidak ditemukan atau bukan milik pegawai ini.');
            redirect('my/bonus' . ($employeeId > 0 ? ('?employee_id=' . $employeeId) : ''));
            return;
        }

        $header = $detail['header'];
        $month = substr((string)($header['attendance_date'] ?? date('Y-m-d')), 0, 7);
        $query = ['month' => $month, 'tab' => 'summary'];
        if ($employeeId > 0) {
            $query['employee_id'] = $employeeId;
        }

        $this->render('my/bonus_daily_audit', [
            'title' => 'Audit Bonus Harian',
            'active_menu' => 'my.bonus',
            'detail' => $detail,
            'employee' => $employee,
            'back_url' => site_url('my/bonus?' . http_build_query($query)),
        ]);
    }

    public function bonus_peer_submit()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_registered_page_permission(self::PAGE_BONUS);

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my/bonus');
            return;
        }

        $date = trim((string)$this->input->post('date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $toIds = (array)$this->input->post('to_employee_id');
        $ratings = (array)$this->input->post('star_rating');
        $reasons = (array)$this->input->post('reason_text');
        $shiftIds = (array)$this->input->post('shift_id');

        $entries = [];
        foreach ($toIds as $idx => $toId) {
            $entries[] = [
                'to_employee_id' => (int)$toId,
                'star_rating' => (int)($ratings[$idx] ?? 0),
                'reason_text' => trim((string)($reasons[$idx] ?? '')),
                'shift_id' => (int)($shiftIds[$idx] ?? 0),
            ];
        }

        $result = $this->Payroll_model->submit_peer_feedback_batch((int)$employee['id'], $date, $entries);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan penilaian 360.'));
        if (!empty($result['warning'])) {
            $this->session->set_flashdata('warning', (string)$result['warning']);
        }

        $query = ['tab' => '360', 'date' => $date];
        if ($this->is_superadmin()) {
            $query['employee_id'] = (int)$employee['id'];
        }
        redirect('my/bonus?' . http_build_query($query));
    }

    private function render_placeholder(string $activeMenu, string $title, string $message): void
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $this->render('my/placeholder', [
            'title' => $title,
            'active_menu' => $activeMenu,
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'message' => $message,
        ]);
    }

    private function require_registered_page_permission(string $pageCode): void
    {
        $this->require_permission($pageCode, 'view');
    }

    private function sync_profile_portal_registry(): void
    {
        if (!$this->db->table_exists('sys_page') || !$this->db->table_exists('sys_menu')) {
            return;
        }

        $page = $this->db->select('id, page_name, description, module')
            ->from('sys_page')
            ->where('page_code', self::PAGE_PROFILE)
            ->limit(1)
            ->get()
            ->row_array();
        if (!empty($page)) {
            $payload = [];
            if ((string)($page['page_name'] ?? '') !== 'Kontrak Saya') {
                $payload['page_name'] = 'Kontrak Saya';
            }
            if ((string)($page['module'] ?? '') !== 'MY_PORTAL') {
                $payload['module'] = 'MY_PORTAL';
            }
            if ((string)($page['description'] ?? '') !== 'Profil pegawai dan akses kontrak kerja pribadi.') {
                $payload['description'] = 'Profil pegawai dan akses kontrak kerja pribadi.';
            }
            if (!empty($payload)) {
                $this->db->where('id', (int)$page['id'])->update('sys_page', $payload);
            }
        }

        $menu = $this->db->select('id, menu_label, url, sidebar_type')
            ->from('sys_menu')
            ->where('menu_code', 'my.profile')
            ->limit(1)
            ->get()
            ->row_array();
        if (!empty($menu)) {
            $payload = [];
            if ((string)($menu['menu_label'] ?? '') !== 'Kontrak Saya') {
                $payload['menu_label'] = 'Kontrak Saya';
            }
            if ((string)($menu['url'] ?? '') !== '/my/profile') {
                $payload['url'] = '/my/profile';
            }
            if ((string)($menu['sidebar_type'] ?? '') !== 'MY') {
                $payload['sidebar_type'] = 'MY';
            }
            if (!empty($payload)) {
                $this->db->where('id', (int)$menu['id'])->update('sys_menu', $payload);
            }
        }
    }

    private function is_registered_page(string $pageCode): bool
    {
        if (!array_key_exists($pageCode, $this->registeredPageCache)) {
            $exists = $this->db
                ->select('id')
                ->from('sys_page')
                ->where('page_code', $pageCode)
                ->where('is_active', 1)
                ->limit(1)
                ->get()
                ->row_array();

            $this->registeredPageCache[$pageCode] = !empty($exists);
        }

        return $this->registeredPageCache[$pageCode];
    }
}
