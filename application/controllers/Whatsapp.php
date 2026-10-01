<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp extends MY_Controller
{
    private const PAGE_DASHBOARD = 'wa.dashboard';
    private const PAGE_BROADCAST = 'wa.broadcast';
    private const PAGE_TEMPLATE  = 'wa.template';
    private const PAGE_REPORT_SCHEDULE = 'wa.report_schedule';
    private const PAGE_GROUP     = 'wa.group';
    private const PAGE_LOG       = 'wa.log';
    private const PAGE_MANUAL    = 'wa.manual';
    private const PAGE_SETTINGS  = 'wa.settings';
    private const WA_ENGINE_CONTROL_CSRF_SESSION_KEY = 'wa_engine_control_csrf';
    private const WA_ENGINE_CONTROL_CSRF_HEADER = 'X-Wa-Engine-Control-CSRF';
    private const WA_ENGINE_CONTROL_CSRF_CI_HEADER = 'X-Wa-Engine-Control-Csrf';
    private const WA_ENV_SAVE_CSRF_SESSION_KEY = 'wa_env_save_csrf';
    private const WA_ENV_SAVE_CSRF_HEADER = 'X-Wa-Env-Save-CSRF';
    private const WA_ENV_SAVE_CSRF_CI_HEADER = 'X-Wa-Env-Save-Csrf';
    private const WA_TEMPLATE_GROUP_MUTATION_CSRF_SESSION_KEY = 'wa_template_group_mutation_csrf';
    private const WA_TEMPLATE_GROUP_MUTATION_CSRF_FORM_FIELD = 'wa_template_group_mutation_csrf';
    private const WA_REPORT_SCHEDULE_MUTATION_CSRF_SESSION_KEY = 'wa_report_schedule_mutation_csrf';
    private const WA_REPORT_SCHEDULE_MUTATION_CSRF_FORM_FIELD = 'wa_report_schedule_mutation_csrf';
    private const WA_BROADCAST_MUTATION_CSRF_SESSION_KEY = 'wa_broadcast_mutation_csrf';
    private const WA_BROADCAST_MUTATION_CSRF_FORM_FIELD = 'wa_broadcast_mutation_csrf';
    private const WA_BROADCAST_MUTATION_CSRF_HEADER = 'X-Wa-Broadcast-CSRF';
    private const WA_BROADCAST_MUTATION_CSRF_CI_HEADER = 'X-Wa-Broadcast-Csrf';
    private const WA_MANUAL_SINGLE_SEND_CSRF_SESSION_KEY = 'wa_manual_single_send_csrf';
    private const WA_MANUAL_SINGLE_SEND_CSRF_FORM_FIELD = 'wa_manual_single_send_csrf';
    private const WA_LOG_RETRY_CSRF_SESSION_KEY = 'wa_log_retry_csrf';
    private const WA_LOG_RETRY_CSRF_HEADER = 'X-Wa-Log-Retry-CSRF';
    private const WA_LOG_RETRY_CSRF_CI_HEADER = 'X-Wa-Log-Retry-Csrf';
    private const WA_SETTINGS_MUTATION_CSRF_SESSION_KEY = 'wa_settings_mutation_csrf';
    private const WA_SETTINGS_MUTATION_CSRF_FORM_FIELD = 'wa_settings_mutation_csrf';
    private const WA_SEND_TEST_CSRF_SESSION_KEY = 'wa_send_test_csrf';
    private const WA_SEND_TEST_CSRF_HEADER = 'X-Wa-Send-Test-CSRF';
    private const WA_SEND_TEST_CSRF_CI_HEADER = 'X-Wa-Send-Test-Csrf';
    private const WA_GROUP_COMMAND_TOKEN_ENV = 'FINANCE_WA_ENGINE_COMMAND_TOKEN';
    private const WA_GROUP_COMMAND_TOKEN_CI_HEADER = 'X-Finance-Group-Command-Token';
    private const WA_GROUP_COMMAND_LEGACY_CI_HEADER = 'X-Sync-Token';
    private const WA_ENGINE_API_TOKEN_ENV = 'FINANCE_WA_ENGINE_API_TOKEN';
    private const WA_ENGINE_COMMAND_URL_ENV = 'FINANCE_COMMAND_URL';
    private const WA_ENGINE_SECRET_FILE = '/etc/finance-wa-engine.env';
    private const WA_ENGINE_API_TOKEN_HEADER = 'X-Finance-Wa-Engine-Token';
    private const WA_ENV_FIELDS = ['WA_PORT', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
    private const WA_ENV_SECRET_FIELDS = ['DB_PASS'];
    private const WA_BOT_API_DEFAULT_URL = 'http://127.0.0.1:3070';
    private const MANUAL_BULK_NOTE_PREFIX = '[MANUAL_BULK]';
    // Emergency circuit breaker. Personal/member sends must move to the
    // official WhatsApp Business Platform before this is enabled again.
    private const PERSONAL_OUTBOUND_ENABLED = false;
    private const PERSONAL_OUTBOUND_LOCK_MESSAGE = 'Pengiriman WhatsApp personal sedang dikunci untuk melindungi akun yang dibatasi. Antrean lama sudah dihentikan; gunakan laporan grup saja sampai kanal resmi WhatsApp Business Platform siap.';

    /**
     * Keeps user-facing finance summaries free from a transaction that was
     * later VOIDed, while the raw mutation log still remains available for audit.
     */
    private function applyEffectiveAccountMutationFilter($builder, string $alias = ''): void
    {
        if (!$this->db->field_exists('reversal_of_mutation_id', 'fin_account_mutation_log')) {
            return;
        }

        $base = $alias !== '' ? rtrim($alias, '.') . '.' : 'fin_account_mutation_log.';
        $builder->where($base . 'reversal_of_mutation_id IS NULL', null, false)
            ->where(
                'NOT EXISTS (SELECT 1 FROM fin_account_mutation_log reversal '
                . 'WHERE reversal.reversal_of_mutation_id = ' . $base . 'id)',
                null,
                false
            );
    }

    private function effectiveAccountMutationSqlFilter(string $alias): string
    {
        if (!$this->db->field_exists('reversal_of_mutation_id', 'fin_account_mutation_log')) {
            return '';
        }

        $prefix = rtrim($alias, '.') . '.';
        return ' AND ' . $prefix . 'reversal_of_mutation_id IS NULL'
            . ' AND NOT EXISTS (SELECT 1 FROM fin_account_mutation_log reversal'
            . ' WHERE reversal.reversal_of_mutation_id = ' . $prefix . 'id)';
    }

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function personalOutboundEnabled(): bool
    {
        return self::PERSONAL_OUTBOUND_ENABLED;
    }

    // ──────────────────────────────────────────────────────────
    // DASHBOARD
    // ──────────────────────────────────────────────────────────
    public function dashboard()
    {
        $this->require_permission(self::PAGE_DASHBOARD, 'view');

        $session = $this->waSessionStatusView($this->waSession());
        $stats   = $this->dashboardStats();

        $canRetryManual = $this->can(self::PAGE_MANUAL, 'create');
        $canRetryGroup = $this->can(self::PAGE_GROUP, 'create');
        $viewData = [
            'title'       => 'WA Dashboard',
            'active_menu' => 'wa.dashboard',
            'session'     => $session,
            'stats'       => $stats,
            'can_retry_manual' => $canRetryManual,
            'can_retry_group' => $canRetryGroup,
            'can_broadcast_edit' => $this->can(self::PAGE_BROADCAST, 'edit'),
        ];
        if ($canRetryManual || $canRetryGroup) {
            $viewData['wa_log_retry_csrf'] = $this->wa_log_retry_csrf();
        }

        $this->render('wa/dashboard', $viewData);
    }

    // ──────────────────────────────────────────────────────────
    // BROADCAST
    // ──────────────────────────────────────────────────────────
    public function broadcast()
    {
        $this->require_permission(self::PAGE_BROADCAST, 'view');

        $allowedStatuses = ['DRAFT','QUEUED','SENDING','DONE','FAILED','CANCELLED'];
        $allowedTargets  = ['MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM'];
        $perPageOptions  = [10, 25, 50, 100];

        $status     = strtoupper(trim((string)$this->input->get('status', true)));
        $targetType = strtoupper(trim((string)$this->input->get('target_type', true)));
        $tab        = strtolower(trim((string)$this->input->get('tab', true)));
        $q          = trim((string)$this->input->get('q', true));
        $page       = max(1, (int)$this->input->get('page', true));
        $perPage    = (int)$this->input->get('per_page', true);

        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }
        if (!in_array($targetType, $allowedTargets, true)) {
            $targetType = '';
        }
        if (!in_array($tab, ['active', 'inactive', 'all'], true)) {
            $tab = 'active';
        }
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $this->db->from('wa_broadcast b')->join('auth_user u', 'u.id = b.created_by', 'left');
        $this->applyBroadcastListFilters($status, $targetType, $tab, $q);
        $totalRows = (int)$this->db->count_all_results();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $this->db->from('wa_broadcast b')
            ->select('b.*, u.username AS created_by_name')
            ->join('auth_user u', 'u.id = b.created_by', 'left')
            ->order_by('b.created_at', 'DESC')
            ->order_by('b.id', 'DESC')
            ->limit($perPage, $offset);
        $this->applyBroadcastListFilters($status, $targetType, $tab, $q);

        $broadcasts = $this->db->get()->result_array();
        $statusCounts = $this->broadcastStatusCounts();

        $canCreate = $this->can(self::PAGE_BROADCAST, 'create');
        $canEdit = $this->can(self::PAGE_BROADCAST, 'edit');
        $canDelete = $this->can(self::PAGE_BROADCAST, 'delete');
        $viewData = [
            'title'       => 'WA Broadcast',
            'active_menu' => 'wa.broadcast',
            'broadcasts'  => $broadcasts,
            'filter_status' => $status,
            'filter_target_type' => $targetType,
            'filter_tab'  => $tab,
            'filter_q'    => $q,
            'page'        => $page,
            'per_page'    => $perPage,
            'per_page_options' => $perPageOptions,
            'total_rows'  => $totalRows,
            'total_pages' => $totalPages,
            'status_counts' => $statusCounts,
            'can_create'  => $canCreate,
            'can_edit'    => $canEdit,
            'can_delete'  => $canDelete,
            'personal_outbound_enabled' => $this->personalOutboundEnabled(),
            'personal_outbound_lock_message' => self::PERSONAL_OUTBOUND_LOCK_MESSAGE,
        ];
        if ($canCreate || $canEdit || $canDelete) {
            $viewData['wa_broadcast_mutation_csrf'] = $this->wa_broadcast_mutation_csrf();
        }

        $this->render('wa/broadcast', $viewData);
    }

    private function applyBroadcastListFilters(string $status, string $targetType, string $tab, string $q): void
    {
        if ($tab === 'active') {
            $this->db->where('b.status !=', 'CANCELLED');
        } elseif ($tab === 'inactive') {
            $this->db->where('b.status', 'CANCELLED');
        }
        if ($status !== '') {
            $this->db->where('b.status', $status);
        }
        if ($targetType !== '') {
            $this->db->where('b.target_type', $targetType);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('b.name', $q)
                ->or_like('b.notes', $q)
                ->or_like('b.custom_message', $q)
                ->or_like('u.username', $q)
            ->group_end();
        }
    }

    private function broadcastStatusCounts(): array
    {
        $counts = [
            'all' => 0,
            'active' => 0,
            'inactive' => 0,
            'status' => [],
        ];
        $rows = $this->db->select('status, COUNT(*) AS total', false)
            ->from('wa_broadcast')
            ->group_by('status')
            ->get()
            ->result_array();
        foreach ($rows as $row) {
            $status = strtoupper((string)($row['status'] ?? ''));
            $total = (int)($row['total'] ?? 0);
            $counts['status'][$status] = $total;
            $counts['all'] += $total;
            if ($status === 'CANCELLED') {
                $counts['inactive'] += $total;
            } else {
                $counts['active'] += $total;
            }
        }
        return $counts;
    }

    public function broadcast_create()
    {
        $this->require_permission(self::PAGE_BROADCAST, 'create');

        $requestMethod = $this->input->method(true);
        if ($requestMethod !== 'GET' && !$this->require_wa_broadcast_mutation_csrf()) {
            return;
        }

        $templates = $this->db->from('wa_template')->where('is_active', 1)
            ->order_by('name', 'ASC')->get()->result_array();
        $groups    = $this->db->from('wa_group_map')->where('is_active', 1)
            ->order_by('group_name', 'ASC')->get()->result_array();

        if ($requestMethod === 'POST') {
            if (!$this->personalOutboundEnabled()) {
                $this->session->set_flashdata('error', self::PERSONAL_OUTBOUND_LOCK_MESSAGE);
                redirect('wa/broadcast');
                return;
            }
            $name          = trim((string)$this->input->post('name', true));
            $templateId    = (int)$this->input->post('template_id', true);
            $customMessage = trim((string)$this->input->post('custom_message', false));
            $targetType    = (string)$this->input->post('target_type', true);
            $scheduledAt   = trim((string)$this->input->post('scheduled_at', true));
            $notes         = trim((string)$this->input->post('notes', true));
            $manualLines   = (string)$this->input->post('manual_lines', false);
            $delayPattern  = $this->broadcastDelayPatternFromRequest();
            $memberIdsRaw  = trim((string)$this->input->post('selected_member_ids', true));
            $selectedMemberIds = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', $memberIdsRaw)))));
            $media         = $this->handleWaImageUpload('media_image');

            if ($name === '') {
                $this->session->set_flashdata('error', 'Nama broadcast wajib diisi.');
                redirect('wa/broadcast/create');
                return;
            }
            if (!empty($media['error'])) {
                $this->session->set_flashdata('error', $media['error']);
                redirect('wa/broadcast/create');
                return;
            }
            if ($customMessage === '' && $templateId <= 0 && empty($media['path'])) {
                $this->session->set_flashdata('error', 'Pilih template, isi pesan, atau upload gambar.');
                redirect('wa/broadcast/create');
                return;
            }
            if ($targetType === 'SELECTED_MEMBERS' && empty($selectedMemberIds)) {
                $this->session->set_flashdata('error', 'Pilih minimal satu member untuk target broadcast.');
                redirect('wa/broadcast/create');
                return;
            }

            $targetType = in_array($targetType, ['MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM'], true) ? $targetType : 'MANUAL';
            $broadcastId = $this->saveBroadcast([
                'name'           => $name,
                'template_id'    => $templateId > 0 ? $templateId : null,
                'custom_message' => $customMessage ?: null,
                'media_path'     => $media['path'] ?? null,
                'media_url'      => $media['url'] ?? null,
                'media_mime'     => $media['mime'] ?? null,
                'media_name'     => $media['name'] ?? null,
                'target_type'    => $targetType,
                'scheduled_at'   => $scheduledAt !== '' ? $scheduledAt : null,
                'delay_pattern_json' => $this->encodeBroadcastDelayPattern($delayPattern),
                'notes'          => $notes ?: null,
                'status'         => 'DRAFT',
                'created_by'     => (int)($this->current_user['id'] ?? 0),
            ], $manualLines, $targetType, $selectedMemberIds);

            $this->session->set_flashdata('success', 'Broadcast berhasil dibuat.');
            redirect('wa/broadcast/detail/' . $broadcastId);
            return;
        }

        $this->render('wa/broadcast_form', [
            'title'       => 'Buat Broadcast',
            'active_menu' => 'wa.broadcast',
            'mode'        => 'create',
            'templates'   => $templates,
            'groups'      => $groups,
            'broadcast'   => [],
            'wa_broadcast_mutation_csrf' => $this->wa_broadcast_mutation_csrf(),
        ]);
    }

    public function broadcast_edit(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'edit');

        $requestMethod = $this->input->method(true);
        if ($requestMethod !== 'GET' && !$this->require_wa_broadcast_mutation_csrf()) {
            return;
        }

        $broadcast = $this->db->from('wa_broadcast')->where('id', $id)->limit(1)->get()->row_array();
        if (!$broadcast) { show_404(); return; }

        if (!in_array((string)$broadcast['status'], ['DRAFT','FAILED','CANCELLED'], true)) {
            $this->session->set_flashdata('error', 'Broadcast yang sudah berjalan atau selesai tidak dapat diedit.');
            redirect('wa/broadcast/detail/' . $id);
            return;
        }

        $templates = $this->db->from('wa_template')->where('is_active', 1)
            ->order_by('name', 'ASC')->get()->result_array();
        $groups = $this->db->from('wa_group_map')->where('is_active', 1)
            ->order_by('group_name', 'ASC')->get()->result_array();

        if ($requestMethod === 'POST') {
            if (!$this->personalOutboundEnabled()) {
                $this->session->set_flashdata('error', self::PERSONAL_OUTBOUND_LOCK_MESSAGE);
                redirect('wa/broadcast/detail/' . $id);
                return;
            }
            $name          = trim((string)$this->input->post('name', true));
            $templateId    = (int)$this->input->post('template_id', true);
            $customMessage = trim((string)$this->input->post('custom_message', false));
            $targetType    = (string)$this->input->post('target_type', true);
            $scheduledAt   = trim((string)$this->input->post('scheduled_at', true));
            $notes         = trim((string)$this->input->post('notes', true));
            $manualLines   = (string)$this->input->post('manual_lines', false);
            $delayPattern  = $this->broadcastDelayPatternFromRequest();
            $memberIdsRaw  = trim((string)$this->input->post('selected_member_ids', true));
            $selectedMemberIds = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', $memberIdsRaw)))));
            $media         = $this->handleWaImageUpload('media_image');

            if ($name === '') {
                $this->session->set_flashdata('error', 'Nama broadcast wajib diisi.');
                redirect('wa/broadcast/edit/' . $id);
                return;
            }
            if (!empty($media['error'])) {
                $this->session->set_flashdata('error', $media['error']);
                redirect('wa/broadcast/edit/' . $id);
                return;
            }
            if ($customMessage === '' && $templateId <= 0 && empty($media['path']) && empty($broadcast['media_path'])) {
                $this->session->set_flashdata('error', 'Pilih template, isi pesan, atau upload gambar.');
                redirect('wa/broadcast/edit/' . $id);
                return;
            }
            if ($targetType === 'SELECTED_MEMBERS' && empty($selectedMemberIds)) {
                $this->session->set_flashdata('error', 'Pilih minimal satu member untuk target broadcast.');
                redirect('wa/broadcast/edit/' . $id);
                return;
            }

            $targetType = in_array($targetType, ['MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM'], true) ? $targetType : 'MANUAL';
            $update = [
                'name'           => $name,
                'template_id'    => $templateId > 0 ? $templateId : null,
                'custom_message' => $customMessage ?: null,
                'target_type'    => $targetType,
                'scheduled_at'   => $scheduledAt !== '' ? $scheduledAt : null,
                'delay_pattern_json' => $this->encodeBroadcastDelayPattern($delayPattern),
                'notes'          => $notes ?: null,
                'status'         => 'DRAFT',
                'total_sent'     => 0,
                'total_failed'   => 0,
                'started_at'     => null,
                'finished_at'    => null,
            ];
            if (!empty($media['path'])) {
                $update['media_path'] = $media['path'];
                $update['media_url']  = $media['url'] ?? null;
                $update['media_mime'] = $media['mime'] ?? null;
                $update['media_name'] = $media['name'] ?? null;
            }

            $targets = $this->buildBroadcastTargets($manualLines, $targetType, $selectedMemberIds);
            $this->db->trans_start();
            $this->db->where('id', $id)->update('wa_broadcast', $update);
            $this->db->where('broadcast_id', $id)->delete('wa_broadcast_line');
            $this->insertBroadcastLines($id, $targets);
            $this->db->where('id', $id)->update('wa_broadcast', ['total_targets' => count($targets)]);
            $this->db->trans_complete();

            if (!$this->db->trans_status()) {
                $this->session->set_flashdata('error', 'Broadcast gagal diperbarui.');
                redirect('wa/broadcast/edit/' . $id);
                return;
            }

            $this->session->set_flashdata('success', 'Broadcast berhasil diperbarui.');
            redirect('wa/broadcast/detail/' . $id);
            return;
        }

        $lines = $this->db->from('wa_broadcast_line')
            ->where('broadcast_id', $id)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        $broadcast['manual_lines'] = $this->broadcastManualLinesFromRows($lines);
        if (!empty($broadcast['scheduled_at'])) {
            $broadcast['scheduled_at'] = date('Y-m-d\TH:i', strtotime($broadcast['scheduled_at']));
        }
        $broadcast['delay_pattern'] = $this->normalizeBroadcastDelayPattern($broadcast['delay_pattern_json'] ?? null);

        $this->render('wa/broadcast_form', [
            'title'       => 'Edit Broadcast',
            'active_menu' => 'wa.broadcast',
            'mode'        => 'edit',
            'templates'   => $templates,
            'groups'      => $groups,
            'broadcast'   => $broadcast,
            'selected_members' => $this->broadcastSelectedMembersFromRows($lines),
            'wa_broadcast_mutation_csrf' => $this->wa_broadcast_mutation_csrf(),
        ]);
    }

    public function broadcast_detail(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'view');

        $broadcast = $this->db->from('wa_broadcast b')
            ->select('b.*, t.name AS template_name, t.body AS template_body, u.username AS created_by_name')
            ->join('wa_template t', 't.id = b.template_id', 'left')
            ->join('auth_user u', 'u.id = b.created_by', 'left')
            ->where('b.id', $id)->limit(1)->get()->row_array();
        if (!$broadcast) { show_404(); return; }

        $broadcast['delay_pattern'] = $this->normalizeBroadcastDelayPattern($broadcast['delay_pattern_json'] ?? null);

        $lines = $this->db->from('wa_broadcast_line')
            ->where('broadcast_id', $id)->order_by('id', 'ASC')->get()->result_array();

        $canEdit = $this->can(self::PAGE_BROADCAST, 'edit');
        $canDelete = $this->can(self::PAGE_BROADCAST, 'delete');
        $viewData = [
            'title'       => 'Detail Broadcast',
            'active_menu' => 'wa.broadcast',
            'broadcast'   => $broadcast,
            'lines'       => $lines,
            'can_edit'    => $canEdit,
            'can_delete'  => $canDelete,
            'personal_outbound_enabled' => $this->personalOutboundEnabled(),
            'personal_outbound_lock_message' => self::PERSONAL_OUTBOUND_LOCK_MESSAGE,
        ];
        if ($canEdit || $canDelete) {
            $viewData['wa_broadcast_mutation_csrf'] = $this->wa_broadcast_mutation_csrf();
        }

        $this->render('wa/broadcast_detail', $viewData);
    }

    public function broadcast_delete(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'delete');
        if (!$this->require_wa_broadcast_mutation_csrf()) {
            return;
        }

        $row = $this->db->from('wa_broadcast')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row || !in_array($row['status'], ['DRAFT','FAILED','CANCELLED'], true)) {
            $this->session->set_flashdata('error', 'Broadcast tidak dapat dihapus pada status ini.');
            redirect('wa/broadcast');
            return;
        }
        $this->db->where('id', $id)->delete('wa_broadcast');
        $this->session->set_flashdata('success', 'Broadcast dihapus.');
        redirect('wa/broadcast');
    }

    public function broadcast_deactivate(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'edit');
        if (!$this->require_wa_broadcast_mutation_csrf()) {
            return;
        }

        $row = $this->db->from('wa_broadcast')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            $this->session->set_flashdata('error', 'Broadcast tidak ditemukan.');
            redirect('wa/broadcast');
            return;
        }

        if (in_array((string)$row['status'], ['DONE','CANCELLED'], true)) {
            $this->session->set_flashdata('error', 'Broadcast pada status ini tidak perlu dinonaktifkan.');
            redirect($this->input->server('HTTP_REFERER') ?: 'wa/broadcast');
            return;
        }

        $this->db->where('id', $id)->update('wa_broadcast', [
            'status' => 'CANCELLED',
            'finished_at' => date('Y-m-d H:i:s'),
            'notes' => trim((string)($row['notes'] ?? '')) !== ''
                ? trim((string)$row['notes']) . "\nDinonaktifkan pada " . date('Y-m-d H:i:s')
                : 'Dinonaktifkan pada ' . date('Y-m-d H:i:s'),
        ]);

        if ($this->db->affected_rows() < 1) {
            $this->session->set_flashdata('error', 'Broadcast gagal dinonaktifkan.');
        } else {
            $this->session->set_flashdata('success', 'Broadcast dinonaktifkan.');
        }
        redirect($this->input->server('HTTP_REFERER') ?: 'wa/broadcast');
    }

    // ──────────────────────────────────────────────────────────
    // TEMPLATE
    // ──────────────────────────────────────────────────────────
    public function template()
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'view');

        if ($this->input->method() === 'post') {
            $action = (string)$this->input->post('action', true);

            if ($action === 'save') {
                $id           = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_TEMPLATE, $id > 0 ? 'edit' : 'create');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $code         = trim((string)$this->input->post('template_code', true));
                $name         = trim((string)$this->input->post('name', true));
                $category     = (string)$this->input->post('category', true);
                $body         = (string)$this->input->post('body', false);
                $sampleVars   = trim((string)$this->input->post('sample_variables', false));

                if ($code === '' || $name === '' || $body === '') {
                    $this->session->set_flashdata('error', 'Kode, nama, dan isi template wajib diisi.');
                } else {
                    $data = [
                        'template_code'    => $code,
                        'name'             => $name,
                        'category'         => in_array($category, ['BROADCAST','GROUP','PROMO','INFO','REMINDER','CUSTOM'], true) ? $category : 'BROADCAST',
                        'body'             => $body,
                        'sample_variables' => $sampleVars !== '' ? $sampleVars : null,
                        'created_by'       => (int)($this->current_user['id'] ?? 0),
                    ];
                    if ($id > 0) {
                        $this->db->where('id', $id)->update('wa_template', $data);
                        $this->session->set_flashdata('success', 'Template diperbarui.');
                    } else {
                        $this->db->insert('wa_template', $data);
                        $this->session->set_flashdata('success', 'Template disimpan.');
                    }
                }
            } elseif ($action === 'toggle') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_TEMPLATE, 'edit');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $row = $this->db->from('wa_template')->where('id', $id)->limit(1)->get()->row_array();
                if ($row) {
                    $this->db->where('id', $id)->update('wa_template', ['is_active' => $row['is_active'] ? 0 : 1]);
                }
            } elseif ($action === 'delete') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_TEMPLATE, 'delete');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $this->db->where('id', $id)->delete('wa_template');
                $this->session->set_flashdata('success', 'Template dihapus.');
            }
            redirect('wa/template');
            return;
        }

        $templates = $this->db->from('wa_template')->order_by('category', 'ASC')
            ->order_by('name', 'ASC')->get()->result_array();

        $canCreate = $this->can(self::PAGE_TEMPLATE, 'create');
        $canEdit = $this->can(self::PAGE_TEMPLATE, 'edit');
        $canDelete = $this->can(self::PAGE_TEMPLATE, 'delete');
        $viewData = [
            'title'       => 'Template Pesan WA',
            'active_menu' => 'wa.template',
            'templates'   => $templates,
            'can_create'  => $canCreate,
            'can_edit'    => $canEdit,
            'can_delete'  => $canDelete,
        ];
        if ($canCreate || $canEdit || $canDelete) {
            $viewData['wa_template_group_mutation_csrf'] = $this->wa_template_group_mutation_csrf();
        }

        $this->render('wa/template', $viewData);
    }

    public function report_schedules()
    {
        $this->require_permission(self::PAGE_REPORT_SCHEDULE, 'view');

        if ($this->input->method() === 'post') {
            $action = (string)$this->input->post('action', true);

            if ($action === 'save_schedule') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_REPORT_SCHEDULE, $id > 0 ? 'edit' : 'create');
                if (!$this->require_wa_report_schedule_mutation_csrf()) {
                    return;
                }
                $name = trim((string)$this->input->post('name', true));
                $reportType = strtoupper(trim((string)$this->input->post('report_type', true)));
                $templateId = (int)$this->input->post('template_id', true);
                $groupId = (int)$this->input->post('group_id', true);
                $sendTimeInput = $this->input->post('send_time', true);
                $sendTimes = $this->parseWaScheduleTimes(is_array($sendTimeInput) ? $sendTimeInput : (string)$sendTimeInput);
                $sendTime = $sendTimes[0] ?? null;
                $dateOffset = (int)$this->input->post('date_offset_days', true);
                $notes = trim((string)$this->input->post('notes', true));
                $isActive = (int)$this->input->post('is_active', true) === 1 ? 1 : 0;

                if ($name === '' || $templateId <= 0 || $groupId <= 0 || !isset($this->waReportTypeOptions()[$reportType])) {
                    $this->session->set_flashdata('error', 'Nama, tipe laporan, template, dan grup wajib valid.');
                    redirect('wa/template/schedules');
                    return;
                }
                if ($sendTime === null) {
                    $this->session->set_flashdata('error', 'Jam kirim tidak valid. Gunakan format 20:11.');
                    redirect('wa/template/schedules');
                    return;
                }

                $payload = [
                    'name' => $name,
                    'report_type' => $reportType,
                    'template_id' => $templateId,
                    'group_id' => $groupId,
                    'send_time' => $sendTime,
                    'date_offset_days' => max(-7, min(1, $dateOffset)),
                    'is_active' => $isActive,
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => (int)($this->current_user['id'] ?? 0),
                    'last_run_at' => null,
                    'last_sent_at' => null,
                    'last_sent_date' => null,
                    'last_status' => null,
                    'last_error' => null,
                ];
                if ($id > 0) {
                    $old = $this->db->from('wa_report_schedule')->where('id', $id)->limit(1)->get()->row_array();
                    $sameScheduleKey = $old
                        && (string)($old['report_type'] ?? '') === $reportType
                        && (int)($old['template_id'] ?? 0) === $templateId
                        && (int)($old['group_id'] ?? 0) === $groupId
                        && substr((string)($old['send_time'] ?? ''), 0, 8) === $sendTime
                        && (int)($old['date_offset_days'] ?? 0) === max(-7, min(1, $dateOffset));
                    unset($payload['created_by']);
                    if ($sameScheduleKey) {
                        unset($payload['last_run_at'], $payload['last_sent_at'], $payload['last_sent_date'], $payload['last_status'], $payload['last_error']);
                    }
                    $this->db->where('id', $id)->update('wa_report_schedule', $payload);
                    if (count($sendTimes) > 1) {
                        $extraPayload = $payload;
                        $extraPayload['created_by'] = (int)($this->current_user['id'] ?? 0);
                        $extraPayload['last_run_at'] = null;
                        $extraPayload['last_sent_at'] = null;
                        $extraPayload['last_sent_date'] = null;
                        $extraPayload['last_status'] = null;
                        $extraPayload['last_error'] = null;
                        foreach (array_slice($sendTimes, 1) as $extraTime) {
                            $extraPayload['send_time'] = $extraTime;
                            $this->db->insert('wa_report_schedule', $extraPayload);
                        }
                    }
                    $this->session->set_flashdata('success', 'Jadwal laporan diperbarui.');
                } else {
                    foreach ($sendTimes as $time) {
                        $payload['send_time'] = $time;
                        $this->db->insert('wa_report_schedule', $payload);
                    }
                    $this->session->set_flashdata('success', 'Jadwal laporan disimpan.');
                }
            } elseif ($action === 'toggle_schedule') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_REPORT_SCHEDULE, 'edit');
                if (!$this->require_wa_report_schedule_mutation_csrf()) {
                    return;
                }
                $row = $this->db->from('wa_report_schedule')->where('id', $id)->limit(1)->get()->row_array();
                if ($row) {
                    $this->db->where('id', $id)->update('wa_report_schedule', ['is_active' => (int)$row['is_active'] ? 0 : 1]);
                }
            } elseif ($action === 'delete_schedule') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_REPORT_SCHEDULE, 'delete');
                if (!$this->require_wa_report_schedule_mutation_csrf()) {
                    return;
                }
                $this->db->where('id', $id)->delete('wa_report_schedule');
                $this->session->set_flashdata('success', 'Jadwal laporan dihapus.');
            } elseif ($action === 'send_now') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_REPORT_SCHEDULE, 'edit');
                if (!$this->require_wa_report_schedule_mutation_csrf()) {
                    return;
                }
                $result = $this->sendWaReportSchedule($id, true);
                if ($this->input->is_ajax_request()) {
                    $this->jsonOut([
                        'ok' => !empty($result['ok']),
                        'message' => (string)($result['message'] ?? 'Gagal kirim laporan.'),
                    ]);
                    return;
                }
                $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal kirim laporan.'));
            }

            redirect('wa/template/schedules');
            return;
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'report_type' => strtoupper(trim((string)$this->input->get('report_type', true))),
            'group_id' => (int)$this->input->get('group_id', true),
            'date_offset_days' => trim((string)$this->input->get('date_offset_days', true)),
        ];
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        $page = max(1, (int)$this->input->get('page', true));

        $applyScheduleFilters = function () use ($filters) {
            if ($filters['q'] !== '') {
                $q = $filters['q'];
                $this->db->group_start()
                    ->like('s.name', $q)
                    ->or_like('s.notes', $q)
                    ->or_like('t.name', $q)
                    ->or_like('t.template_code', $q)
                    ->or_like('g.group_name', $q)
                    ->or_like('g.group_jid', $q)
                    ->group_end();
            }
            if ($filters['status'] === 'ACTIVE') {
                $this->db->where('s.is_active', 1);
            } elseif ($filters['status'] === 'INACTIVE') {
                $this->db->where('s.is_active', 0);
            } elseif ($filters['status'] === 'SENT') {
                $this->db->where('s.last_status', 'SENT');
            } elseif ($filters['status'] === 'FAILED') {
                $this->db->where('s.last_status', 'FAILED');
            } elseif ($filters['status'] === 'NEVER') {
                $this->db->where('s.last_status IS NULL', null, false);
            }
            if ($filters['report_type'] !== '' && isset($this->waReportTypeOptions()[$filters['report_type']])) {
                $this->db->where('s.report_type', $filters['report_type']);
            }
            if ($filters['group_id'] > 0) {
                $this->db->where('s.group_id', $filters['group_id']);
            }
            if ($filters['date_offset_days'] !== '' && in_array((int)$filters['date_offset_days'], [-1, 0], true)) {
                $this->db->where('s.date_offset_days', (int)$filters['date_offset_days']);
            }
        };

        $this->db->from('wa_report_schedule s')
            ->join('wa_template t', 't.id = s.template_id', 'left')
            ->join('wa_group_map g', 'g.id = s.group_id', 'left');
        $applyScheduleFilters();
        $totalRows = (int)$this->db->count_all_results();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $this->db->select('s.*, t.name AS template_name, g.group_name, g.group_jid')
            ->from('wa_report_schedule s')
            ->join('wa_template t', 't.id = s.template_id', 'left')
            ->join('wa_group_map g', 'g.id = s.group_id', 'left');
        $applyScheduleFilters();
        $schedules = $this->db
            ->order_by('s.is_active', 'DESC')
            ->order_by('s.send_time', 'ASC')
            ->order_by('s.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->result_array();

        $templates = $this->db->from('wa_template')
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();
        $groups = $this->db->from('wa_group_map')
            ->where('is_active', 1)
            ->order_by('group_name', 'ASC')
            ->get()
            ->result_array();

        $canCreate = $this->can(self::PAGE_REPORT_SCHEDULE, 'create');
        $canEdit = $this->can(self::PAGE_REPORT_SCHEDULE, 'edit');
        $canDelete = $this->can(self::PAGE_REPORT_SCHEDULE, 'delete');
        $viewData = [
            'title' => 'Jadwal Laporan WA',
            'active_menu' => 'wa.report_schedule',
            'schedules' => $schedules,
            'templates' => $templates,
            'groups' => $groups,
            'report_types' => $this->waReportTypeOptions(),
            'filters' => $filters,
            'pg' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalRows,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
            'can_create' => $canCreate,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ];
        if ($canCreate || $canEdit || $canDelete) {
            $viewData['wa_report_schedule_mutation_csrf'] = $this->wa_report_schedule_mutation_csrf();
        }

        $this->render('wa/report_schedule', $viewData);
    }

    // ──────────────────────────────────────────────────────────
    // GROUP
    // ──────────────────────────────────────────────────────────
    public function group()
    {
        $this->require_permission(self::PAGE_GROUP, 'view');

        if ($this->input->method() === 'post') {
            $action = (string)$this->input->post('action', true);
            if ($action === 'save') {
                $id        = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_GROUP, $id > 0 ? 'edit' : 'create');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $key       = trim((string)$this->input->post('group_key', true));
                $name      = trim((string)$this->input->post('group_name', true));
                $jid       = trim((string)$this->input->post('group_jid', true));
                $purpose   = trim((string)$this->input->post('purpose', true));
                $notes     = trim((string)$this->input->post('notes', true));

                if ($key === '' || $name === '') {
                    $this->session->set_flashdata('error', 'Key dan nama grup wajib diisi.');
                } else {
                    $data = ['group_key' => $key, 'group_name' => $name,
                             'group_jid' => $jid ?: null, 'purpose' => $purpose ?: null,
                             'notes' => $notes ?: null];
                    if ($id > 0) {
                        $this->db->where('id', $id)->update('wa_group_map', $data);
                        $this->session->set_flashdata('success', 'Grup diperbarui.');
                    } else {
                        $this->db->insert('wa_group_map', $data);
                        $this->session->set_flashdata('success', 'Grup disimpan.');
                    }
                }
            } elseif ($action === 'toggle') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_GROUP, 'edit');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $row = $this->db->from('wa_group_map')->where('id', $id)->limit(1)->get()->row_array();
                if ($row) {
                    $this->db->where('id', $id)->update('wa_group_map', ['is_active' => $row['is_active'] ? 0 : 1]);
                }
            } elseif ($action === 'delete') {
                $id = (int)$this->input->post('id', true);
                $this->require_permission(self::PAGE_GROUP, 'delete');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $this->db->where('id', $id)->delete('wa_group_map');
                $this->session->set_flashdata('success', 'Grup dihapus.');
            } elseif ($action === 'send_group') {
                $this->require_permission(self::PAGE_GROUP, 'create');
                if (!$this->require_wa_template_group_mutation_csrf()) {
                    return;
                }
                $id      = (int)$this->input->post('id', true);
                $message = trim((string)$this->input->post('message', false));
                $media   = $this->handleWaImageUpload('media_image');
                $group   = $this->db->from('wa_group_map')->where('id', $id)->limit(1)->get()->row_array();
                if (!empty($media['error'])) {
                    $this->session->set_flashdata('error', $media['error']);
                } elseif ($group && $group['group_jid'] && ($message !== '' || !empty($media['path']))) {
                    $result = $this->callBotApi('/internal/send-group', 'POST', [
                        'group_jid' => $group['group_jid'],
                        'message'   => $message,
                        'image_path'=> $media['path'] ?? null,
                    ]);
                    if ($result['ok'] ?? false) {
                        $this->db->where('id', $id)->update('wa_group_map', ['last_sent_at' => date('Y-m-d H:i:s')]);
                        $this->logSend(null, 'GROUP', null, $group['group_jid'], $group['group_name'], $this->messageLogPreview($message, $media), 'SENT');
                        $this->session->set_flashdata('success', 'Pesan terkirim ke grup ' . $group['group_name']);
                    } else {
                        $this->logSend(null, 'GROUP', null, $group['group_jid'], $group['group_name'], $this->messageLogPreview($message, $media), 'FAILED', $result['message'] ?? '');
                        $this->session->set_flashdata('error', 'Gagal kirim: ' . ($result['message'] ?? 'error'));
                    }
                } else {
                    $this->session->set_flashdata('error', 'Grup atau pesan/gambar tidak valid.');
                }
            }
            redirect('wa/group');
            return;
        }

        $groups = $this->db->from('wa_group_map')->order_by('group_name', 'ASC')->get()->result_array();

        $canCreate = $this->can(self::PAGE_GROUP, 'create');
        $canEdit = $this->can(self::PAGE_GROUP, 'edit');
        $canDelete = $this->can(self::PAGE_GROUP, 'delete');
        $viewData = [
            'title'       => 'Manajemen Grup WA',
            'active_menu' => 'wa.group',
            'groups'      => $groups,
            'can_create'  => $canCreate,
            'can_edit'    => $canEdit,
            'can_delete'  => $canDelete,
        ];
        if ($canCreate || $canEdit || $canDelete) {
            $viewData['wa_template_group_mutation_csrf'] = $this->wa_template_group_mutation_csrf();
        }

        $this->render('wa/group', $viewData);
    }

    // ──────────────────────────────────────────────────────────
    // LOG
    // ──────────────────────────────────────────────────────────
    public function log()
    {
        $this->require_permission(self::PAGE_LOG, 'view');

        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        $status   = (string)$this->input->get('status', true);
        $source   = (string)$this->input->get('source', true);

        if ($dateFrom === '') $dateFrom = date('Y-m-01');
        if ($dateTo   === '') $dateTo   = date('Y-m-d');

        $this->db->from('wa_send_log l')
            ->select('l.*')
            ->where('DATE(l.sent_at) >=', $dateFrom)
            ->where('DATE(l.sent_at) <=', $dateTo)
            ->order_by('l.sent_at', 'DESC');

        if ($status && in_array($status, ['SENT','FAILED','PENDING'], true)) {
            $this->db->where('l.status', $status);
        }
        if ($source && in_array($source, ['BROADCAST','MANUAL','GROUP','SYSTEM','SCHEDULED'], true)) {
            $this->db->where('l.source', $source);
        }

        $logs = $this->db->limit(200)->get()->result_array();

        $canRetryManual = $this->can(self::PAGE_MANUAL, 'create');
        $canRetryGroup = $this->can(self::PAGE_GROUP, 'create');
        $viewData = [
            'title'       => 'Log Pengiriman WA',
            'active_menu' => 'wa.log',
            'logs'        => $logs,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'filter_status' => $status,
            'filter_source' => $source,
            'can_retry_manual' => $canRetryManual,
            'can_retry_group' => $canRetryGroup,
            'can_broadcast_edit' => $this->can(self::PAGE_BROADCAST, 'edit'),
        ];
        if ($canRetryManual || $canRetryGroup) {
            $viewData['wa_log_retry_csrf'] = $this->wa_log_retry_csrf();
        }

        $this->render('wa/log', $viewData);
    }

    // ──────────────────────────────────────────────────────────
    // MANUAL MESSAGE
    // ──────────────────────────────────────────────────────────
    public function manual()
    {
        $this->require_permission(self::PAGE_MANUAL, 'view');

        $requestMethod = $this->input->method(true);
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        $tab = in_array($tab, ['single', 'bulk'], true) ? $tab : 'single';

        if ($requestMethod !== 'GET') {
            $this->require_permission(self::PAGE_MANUAL, 'create');

            $deliveryMode = (string)$this->input->post('delivery_mode', true);
            if ($deliveryMode === 'bulk') {
                if (!$this->require_wa_broadcast_mutation_csrf()) {
                    return;
                }
            } elseif (!$this->require_wa_manual_single_send_csrf()) {
                return;
            }

            if (!$this->personalOutboundEnabled()) {
                $tab = $deliveryMode === 'bulk' ? 'bulk' : 'single';
                $this->session->set_flashdata('error', self::PERSONAL_OUTBOUND_LOCK_MESSAGE);
                redirect('wa/manual?tab=' . $tab);
                return;
            }

            if ($deliveryMode === 'bulk') {
                $this->createManualBulkQueue();
                return;
            }

            $message = trim((string)$this->input->post('message', false));
            $manualLines = (string)$this->input->post('manual_numbers', false);
            $memberIdsRaw = trim((string)$this->input->post('selected_member_ids', true));
            $media = $this->handleWaImageUpload('media_image');

            if (!empty($media['error'])) {
                $this->session->set_flashdata('error', $media['error']);
                redirect('wa/manual');
                return;
            }

            if ($message === '' && empty($media['path'])) {
                $this->session->set_flashdata('error', 'Isi pesan atau gambar wajib diisi.');
                redirect('wa/manual');
                return;
            }

            $targets = $this->manualTargetsFromInput($manualLines, $memberIdsRaw);

            if (empty($targets)) {
                $this->session->set_flashdata('error', 'Tidak ada nomor tujuan valid. Gunakan format 628xxxx atau pilih member aktif.');
                redirect('wa/manual');
                return;
            }

            $sent = 0;
            $failed = 0;
            $errors = [];
            foreach ($targets as $target) {
                $result = $this->callBotApi('/internal/send', 'POST', [
                    'to'      => $target['phone'],
                    'message' => $message,
                    'image_path' => $media['path'] ?? null,
                ]);
                if ($result['ok'] ?? false) {
                    $this->logSend(null, 'MANUAL', $target['phone'], null, $target['name'] ?: null, $this->messageLogPreview($message, $media), 'SENT');
                    $sent++;
                } else {
                    $err = (string)($result['message'] ?? 'Gagal kirim');
                    $this->logSend(null, 'MANUAL', $target['phone'], null, $target['name'] ?: null, $this->messageLogPreview($message, $media), 'FAILED', $err);
                    $failed++;
                    if (count($errors) < 5) {
                        $errors[] = ($target['name'] ? $target['name'] . ' · ' : '') . $target['phone'] . ': ' . $err;
                    }
                }
            }

            if ($failed > 0) {
                $this->session->set_flashdata('error', 'Pesan manual selesai dengan sebagian gagal. Terkirim: ' . $sent . ', gagal: ' . $failed . (empty($errors) ? '' : '. Contoh: ' . implode(' | ', $errors)));
            } else {
                $this->session->set_flashdata('success', 'Pesan manual terkirim ke ' . $sent . ' nomor.');
            }
            redirect('wa/manual');
            return;
        }

        $recentManualLogs = $this->db->from('wa_send_log')
            ->where('source', 'MANUAL')
            ->where('broadcast_id IS NULL', null, false)
            ->order_by('sent_at', 'DESC')
            ->limit(20)
            ->get()
            ->result_array();

        $bulkQueue = [];
        $bulkLines = [];
        $bulkLineStatusCounts = ['PENDING' => 0, 'SENT' => 0, 'FAILED' => 0, 'SKIPPED' => 0];
        $bulkId = max(0, (int)$this->input->get('bulk_id', true));
        $bulkAutoStart = $tab === 'bulk'
            && $bulkId > 0
            && (string)$this->input->get('autostart', true) === '1';
        if ($tab === 'bulk' && $bulkId > 0) {
            $candidate = $this->db->from('wa_broadcast')->where('id', $bulkId)->limit(1)->get()->row_array();
            if ($candidate && $this->isManualBulkQueue($candidate)) {
                $bulkQueue = $candidate;
                $bulkQueue['delay_pattern'] = $this->normalizeBroadcastDelayPattern($bulkQueue['delay_pattern_json'] ?? null);
                $bulkLines = $this->db->from('wa_broadcast_line')
                    ->where('broadcast_id', $bulkId)
                    ->order_by('id', 'ASC')
                    ->get()
                    ->result_array();
                foreach ($bulkLines as $line) {
                    $status = strtoupper((string)($line['status'] ?? 'PENDING'));
                    $bulkLineStatusCounts[$status] = (int)($bulkLineStatusCounts[$status] ?? 0) + 1;
                }
            } else {
                $this->session->set_flashdata('error', 'Antrean pesan bulk tidak ditemukan.');
                redirect('wa/manual?tab=bulk');
                return;
            }
        }

        $recentBulkQueues = $this->db->from('wa_broadcast')
            ->select('id, name, status, total_targets, total_sent, total_failed, created_at, started_at, finished_at')
            ->where('target_type', 'CUSTOM')
            ->like('notes', self::MANUAL_BULK_NOTE_PREFIX, 'after')
            ->order_by('id', 'DESC')
            ->limit(8)
            ->get()
            ->result_array();

        $canCreate = $this->can(self::PAGE_MANUAL, 'create');
        $viewData = [
            'title'       => 'Kirim Pesan Manual WA',
            'active_menu' => 'wa.manual',
            'can_create'  => $canCreate,
            'can_retry_manual' => $canCreate,
            'recent_logs' => $recentManualLogs,
            'active_tab'  => $tab,
            'bulk_queue'  => $bulkQueue,
            'bulk_lines'  => $bulkLines,
            'bulk_line_status_counts' => $bulkLineStatusCounts,
            'recent_bulk_queues' => $recentBulkQueues,
            'bulk_auto_start' => $bulkAutoStart,
            'personal_outbound_enabled' => $this->personalOutboundEnabled(),
            'personal_outbound_lock_message' => self::PERSONAL_OUTBOUND_LOCK_MESSAGE,
        ];
        if ($canCreate && $tab === 'bulk') {
            $viewData['wa_broadcast_mutation_csrf'] = $this->wa_broadcast_mutation_csrf();
        }
        if ($canCreate && $tab === 'single') {
            $viewData['wa_manual_single_send_csrf'] = $this->wa_manual_single_send_csrf();
            $viewData['wa_log_retry_csrf'] = $this->wa_log_retry_csrf();
        }

        $this->render('wa/manual', $viewData);
    }

    // ──────────────────────────────────────────────────────────
    // SETTINGS
    // ──────────────────────────────────────────────────────────
    public function settings()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');

        $requestMethod = $this->input->method(true);
        if ($requestMethod === 'POST') {
            $this->require_permission(self::PAGE_SETTINGS, 'edit');
            if (!$this->require_wa_settings_mutation_csrf()) {
                return;
            }

            $botApiUrl = trim((string)$this->input->post('bot_api_url', true));
            if ($botApiUrl === '') {
                $botApiUrl = self::WA_BOT_API_DEFAULT_URL;
            }
            $botApiUrl = $this->normalizeBotApiBaseUrl($botApiUrl);
            if ($botApiUrl === null) {
                $this->session->set_flashdata('error', 'URL API Bot tidak valid. Gunakan http://127.0.0.1:<port>.');
                redirect('wa/settings');
                return;
            }

            $nodePath = trim((string)$this->input->post('node_path', true));

            $updateData = [
                'bot_api_url'   => $botApiUrl,
                'node_path'     => $nodePath ?: null,
            ];
            // Kolom node_path mungkin belum ada di DB lama — tangani gracefully
            if (!$this->db->field_exists('node_path', 'wa_session')) {
                unset($updateData['node_path']);
            }
            $this->db->where('id', 1)->update('wa_session', $updateData);
            $this->session->set_flashdata('success', 'Pengaturan disimpan.');
            redirect('wa/settings');
            return;
        }

        $session = $this->waSession();
        $settingsView = [
            'bot_api_url' => (string)($session['bot_api_url'] ?? 'http://127.0.0.1:3070'),
            'node_path' => (string)($session['node_path'] ?? ''),
        ];
        $canEdit = $this->can(self::PAGE_SETTINGS, 'edit');

        $viewData = [
            'title'       => 'Pengaturan WA',
            'active_menu' => 'wa.settings',
            'settings'    => $settingsView,
            'can_edit'    => $canEdit,
        ];
        if ($canEdit) {
            $viewData['wa_settings_mutation_csrf'] = $this->wa_settings_mutation_csrf();
            $viewData['wa_engine_control_csrf_token'] = $this->wa_engine_control_csrf();
            $viewData['wa_env_save_csrf_token'] = $this->wa_env_save_csrf();
            $viewData['wa_send_test_csrf_token'] = $this->wa_send_test_csrf();
        }

        $this->render('wa/settings', $viewData);
    }

    // ──────────────────────────────────────────────────────────
    // JSON API — status bot
    // ──────────────────────────────────────────────────────────
    public function notification_settings()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_wa_settings_mutation_csrf()) return;
        $this->load->model('Module_notification_model');
        try {
            if ($this->input->post('action', true) === 'retry') {
                $this->Module_notification_model->retry('WA', (int)$this->input->post('queue_id', true));
            } else {
                $this->Module_notification_model->save_rules('WA', (array)$this->input->post('notifications'), (int)($this->current_user['id'] ?? 0));
            }
            $this->session->set_flashdata('success', 'Pengaturan / antrean notifikasi WhatsApp tersimpan.');
        } catch (InvalidArgumentException | RuntimeException $error) {
            $this->session->set_flashdata('error', $error->getMessage());
        }
        redirect('wa/settings');
    }

    public function api_status()
    {
        $this->require_permission(self::PAGE_DASHBOARD, 'view');
        $result = $this->callBotApi('/internal/status', 'GET');
        $this->output->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    // JSON API — kirim pesan test
    public function api_send_test()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_wa_send_test_csrf()) {
            return;
        }

        if (!$this->personalOutboundEnabled()) {
            $this->jsonOut(['ok' => false, 'message' => self::PERSONAL_OUTBOUND_LOCK_MESSAGE]);
            return;
        }
        $decoded = json_decode((string)$this->input->raw_input_stream, true);
        $payload = is_array($decoded) ? $decoded : [];
        $to      = trim((string)($payload['to'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));

        if (!$to || !$message) {
            $this->jsonOut(['ok' => false, 'message' => 'Nomor dan pesan wajib diisi.']);
            return;
        }

        $result = $this->callBotApi('/internal/send', 'POST', ['to' => $to, 'message' => $message]);
        if ($result['ok'] ?? false) {
            $this->logSend(null, 'MANUAL', $to, null, null, $message, 'SENT');
        } else {
            $this->logSend(null, 'MANUAL', $to, null, null, $message, 'FAILED', $result['message'] ?? '');
        }
        $this->jsonOut($result);
    }

    public function api_log_retry(int $id = 0)
    {
        if (!$this->can(self::PAGE_MANUAL, 'create')
            && !$this->can(self::PAGE_GROUP, 'create')
            && !$this->can(self::PAGE_BROADCAST, 'edit')) {
            $this->jsonOut(['ok' => false, 'message' => 'Akses ditolak.']);
            return;
        }

        if (!$this->require_wa_log_retry_csrf()) {
            return;
        }

        $log = $this->db->from('wa_send_log')->where('id', $id)->limit(1)->get()->row_array();
        if (!$log || ($log['status'] ?? '') !== 'FAILED') {
            $this->jsonOut(['ok' => false, 'message' => 'Log gagal tidak ditemukan.']);
            return;
        }

        $source = strtoupper(trim((string)($log['source'] ?? '')));
        $groupJid = trim((string)($log['group_jid'] ?? ''));
        $isGroup = $source === 'GROUP' || $groupJid !== '';

        if ($source === 'BROADCAST') {
            if (!$this->can(self::PAGE_BROADCAST, 'edit')) {
                $this->jsonOut(['ok' => false, 'message' => 'Akses retry broadcast ditolak.']);
                return;
            }
            $broadcastId = (int)($log['broadcast_id'] ?? 0);
            $this->jsonOut([
                'ok' => false,
                'open_broadcast' => $broadcastId > 0,
                'broadcast_id' => $broadcastId,
                'message' => 'Pesan broadcast dikirim ulang dari halaman detail broadcast.',
            ]);
            return;
        }

        if ($isGroup) {
            if (!$this->can(self::PAGE_GROUP, 'create')) {
                $this->jsonOut(['ok' => false, 'message' => 'Akses retry pesan grup ditolak.']);
                return;
            }
        } elseif (!$this->can(self::PAGE_MANUAL, 'create')) {
            $this->jsonOut(['ok' => false, 'message' => 'Akses retry pesan manual ditolak.']);
            return;
        }

        if (!$isGroup && !$this->personalOutboundEnabled()) {
            $this->jsonOut(['ok' => false, 'message' => self::PERSONAL_OUTBOUND_LOCK_MESSAGE]);
            return;
        }

        $message = trim($this->retryMessageFromLog($log));
        if ($message === '') {
            $this->jsonOut(['ok' => false, 'message' => 'Isi pesan pada log kosong, tidak bisa dikirim ulang.']);
            return;
        }

        if ($isGroup) {
            if ($groupJid === '') {
                $this->jsonOut(['ok' => false, 'message' => 'JID grup pada log kosong.']);
                return;
            }
            $result = $this->callBotApi('/internal/send-group', 'POST', [
                'group_jid' => $groupJid,
                'message' => $message,
            ]);
            $this->logSend(null, 'GROUP', null, $groupJid, $log['display_name'] ?? null, $message, ($result['ok'] ?? false) ? 'SENT' : 'FAILED', $result['message'] ?? '');
            $this->jsonOut($result);
            return;
        }

        $phone = trim((string)($log['phone_number'] ?? ''));
        if ($phone === '') {
            $this->jsonOut(['ok' => false, 'message' => 'Nomor tujuan pada log kosong.']);
            return;
        }
        $result = $this->callBotApi('/internal/send', 'POST', [
            'to' => $phone,
            'message' => $message,
        ]);
        $this->logSend(null, 'MANUAL', $phone, null, $log['display_name'] ?? null, $message, ($result['ok'] ?? false) ? 'SENT' : 'FAILED', $result['message'] ?? '');
        $this->jsonOut($result);
    }

    public function api_member_search()
    {
        if (!$this->can(self::PAGE_MANUAL, 'view') && !$this->can(self::PAGE_BROADCAST, 'view')) {
            $this->jsonOut(['ok' => false, 'message' => 'Akses ditolak.']);
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        $limit = max(1, min(30, $limit > 0 ? $limit : 10));

        if ($q === '' || !$this->db->table_exists('crm_member')) {
            $this->jsonOut(['ok' => true, 'rows' => []]);
            return;
        }

        $rows = $this->db
            ->select('id, member_no, member_name, mobile_phone, member_tier, member_status')
            ->from('crm_member')
            ->where('is_active', 1)
            ->where('member_status', 'ACTIVE')
            ->where('mobile_phone IS NOT NULL', null, false)
            ->where('mobile_phone !=', '')
            ->group_start()
                ->like('member_no', $q)
                ->or_like('member_name', $q)
                ->or_like('mobile_phone', $q)
            ->group_end()
            ->order_by('member_name', 'ASC')
            ->order_by('member_no', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        $this->jsonOut(['ok' => true, 'rows' => $rows]);
    }

    // JSON API — daftar member berpaginasi untuk pemilih pesan bulk.
    public function api_member_picker()
    {
        if (!$this->can(self::PAGE_MANUAL, 'view')) {
            $this->jsonOut(['ok' => false, 'message' => 'Akses ditolak.']);
            return;
        }

        if (!$this->db->table_exists('crm_member')) {
            $this->jsonOut([
                'ok' => true,
                'rows' => [],
                'page' => 1,
                'per_page' => 25,
                'total_rows' => 0,
                'total_pages' => 1,
            ]);
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $page = max(1, (int)$this->input->get('page', true));
        $perPage = (int)$this->input->get('per_page', true);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $this->db->from('crm_member');
        $this->applyManualMemberPickerFilters($q);
        $totalRows = (int)$this->db->count_all_results();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);

        $this->db->select('id, member_no, member_name, mobile_phone, member_tier, member_status')
            ->from('crm_member')
            ->order_by('member_name', 'ASC')
            ->order_by('member_no', 'ASC')
            ->order_by('id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage);
        $this->applyManualMemberPickerFilters($q);

        $this->jsonOut([
            'ok' => true,
            'rows' => $this->db->get()->result_array(),
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ]);
    }

    // JSON API — mulai kirim broadcast
    public function api_broadcast_start(int $id = 0)
    {
        $canBroadcastEdit = $this->can(self::PAGE_BROADCAST, 'edit');
        $canManualCreate = $this->can(self::PAGE_MANUAL, 'create');
        if (!$canBroadcastEdit && !$canManualCreate) {
            $this->jsonOut(['ok' => false, 'message' => 'Akses pengiriman antrean ditolak.']);
            return;
        }
        if (!$this->require_wa_broadcast_mutation_csrf(true)) {
            return;
        }
        if (!$this->personalOutboundEnabled()) {
            $this->jsonOut(['ok' => false, 'stopped' => true, 'message' => self::PERSONAL_OUTBOUND_LOCK_MESSAGE]);
            return;
        }

        $broadcast = $this->db->from('wa_broadcast')->where('id', $id)->limit(1)->get()->row_array();
        if (!$broadcast || !in_array($broadcast['status'], ['DRAFT','FAILED','SENDING'], true)) {
            $this->jsonOut(['ok' => false, 'message' => 'Antrean tidak dapat dikirim pada status ini.']);
            return;
        }
        $isManualBulk = $this->isManualBulkQueue($broadcast);
        if (($isManualBulk && !$canManualCreate) || (!$isManualBulk && !$canBroadcastEdit)) {
            $this->jsonOut(['ok' => false, 'message' => 'Anda tidak memiliki izin untuk mengirim antrean ini.']);
            return;
        }
        $logSource = $isManualBulk ? 'MANUAL' : 'BROADCAST';
        // A named MySQL lock prevents two browser tabs from processing the
        // same queue simultaneously. Release it on shutdown as jsonOut()
        // exits immediately after writing the response.
        $locked = (int)($this->db->query('SELECT GET_LOCK(?, 0) AS locked', ['wa_broadcast_send_' . $id])->row_array()['locked'] ?? 0);
        if ($locked !== 1) {
            $this->jsonOut(['ok' => false, 'message' => 'Antrean sedang diproses oleh sesi lain.']);
            return;
        }
        register_shutdown_function(function () use ($id): void {
            try {
                $this->db->query('SELECT RELEASE_LOCK(?)', ['wa_broadcast_send_' . $id]);
            } catch (Throwable $e) {
                // The DB connection may already be closed during shutdown.
            }
        });

        $botStatus = $this->callBotApi('/internal/status', 'GET', [], 2);
        if (!($botStatus['ok'] ?? false) || strtoupper((string)($botStatus['status'] ?? '')) !== 'CONNECTED') {
            $this->jsonOut([
                'ok' => false,
                'stopped' => true,
                'message' => 'Bot WhatsApp tidak terhubung. Antrean dihentikan; tidak ada nomor yang ditandai gagal.',
            ]);
            return;
        }
        $retryMode = (string)$this->input->get('retry', true) === '1';
        $retryLast = (string)$this->input->get('retry_last', true) === '1';
        $lineId = (int)$this->input->get('line_id', true);
        $lineQuery = $this->db->from('wa_broadcast_line')
            ->where('broadcast_id', $id)
            ->order_by('id', 'ASC')
            ->limit(1);
        if ($retryMode) {
            $lineQuery->where('status', 'FAILED');
            if ($lineId > 0) {
                $lineQuery->where('id', $lineId);
            }
        } else {
            $lineQuery->where('status', 'PENDING');
        }
        $line = $lineQuery->get()->row_array();

        if (!$line) {
            $this->jsonOut(['ok' => false, 'message' => 'Tidak ada target yang bisa dikirim.']);
            return;
        }

        // Resolve pesan
        $messageTemplate = $broadcast['custom_message'] ?: '';
        if (!$messageTemplate && $broadcast['template_id']) {
            $tpl = $this->db->from('wa_template')->where('id', $broadcast['template_id'])->limit(1)->get()->row_array();
            $messageTemplate = $tpl['body'] ?? '';
        }
        $mediaPath = trim((string)($broadcast['media_path'] ?? ''));
        $mediaMeta = [
            'path' => $mediaPath ?: null,
            'name' => $broadcast['media_name'] ?? null,
            'mime' => $broadcast['media_mime'] ?? null,
        ];

        $broadcastUpdate = ['status' => 'SENDING'];
        if (empty($broadcast['started_at'])) {
            $broadcastUpdate['started_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', $id)->update('wa_broadcast', $broadcastUpdate);

        $sent = $failed = 0;
        $msg = $this->resolveMessage($messageTemplate, (array)json_decode($line['variables_json'] ?? '{}', true));
        $result = $this->callBotApi('/internal/send', 'POST', [
            'to'         => $line['phone_number'],
            'message'    => $msg,
            'image_path' => $mediaPath ?: null,
        ]);

        if ($this->isBotUnavailableResult($result)) {
            $this->jsonOut([
                'ok' => false,
                'stopped' => true,
                'message' => 'Bot WhatsApp terputus saat proses berjalan. Antrean dihentikan; nomor ini tetap pending.',
            ]);
            return;
        }
        if (!($result['ok'] ?? false)) {
            $botStatusAfterSend = $this->callBotApi('/internal/status', 'GET', [], 2);
            if (!($botStatusAfterSend['ok'] ?? false) || strtoupper((string)($botStatusAfterSend['status'] ?? '')) !== 'CONNECTED') {
                $this->jsonOut([
                    'ok' => false,
                    'stopped' => true,
                    'message' => 'Bot WhatsApp terputus saat proses berjalan. Antrean dihentikan; nomor ini tetap pending.',
                ]);
                return;
            }
        }

        if ($result['ok'] ?? false) {
            $this->db->where('id', $line['id'])->update('wa_broadcast_line', [
                'status'           => 'SENT',
                'resolved_message' => $msg,
                'sent_at'          => date('Y-m-d H:i:s'),
                'error_msg'        => null,
            ]);
            $this->logSend($id, $logSource, $line['phone_number'], null, $line['display_name'], $this->messageLogPreview($msg, $mediaMeta), 'SENT');
            $sent++;
        } else {
            $errMsg = $result['message'] ?? 'error';
            $this->db->where('id', $line['id'])->update('wa_broadcast_line', [
                'status'      => 'FAILED',
                'error_msg'   => $errMsg,
                'retry_count' => (int)$line['retry_count'] + 1,
            ]);
            $this->logSend($id, $logSource, $line['phone_number'], null, $line['display_name'], $this->messageLogPreview($msg, $mediaMeta), 'FAILED', $errMsg);
            $failed++;
        }

        $totalSent = (int)$this->db->where('broadcast_id', $id)->where('status', 'SENT')->count_all_results('wa_broadcast_line');
        $totalFailed = (int)$this->db->where('broadcast_id', $id)->where('status', 'FAILED')->count_all_results('wa_broadcast_line');
        $totalPending = (int)$this->db->where('broadcast_id', $id)->where('status', 'PENDING')->count_all_results('wa_broadcast_line');
        $hasMore = $retryMode ? !$retryLast : $totalPending > 0;
        $finalStatus = $hasMore ? 'SENDING' : ($totalFailed > 0 ? 'FAILED' : 'DONE');
        $headerUpdate = [
            'status'       => $finalStatus,
            'total_sent'   => $totalSent,
            'total_failed' => $totalFailed,
        ];
        if (!$hasMore) {
            $headerUpdate['finished_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', $id)->update('wa_broadcast', [
            'status'       => $headerUpdate['status'],
            'total_sent'   => $headerUpdate['total_sent'],
            'total_failed' => $headerUpdate['total_failed'],
            'finished_at'  => $headerUpdate['finished_at'] ?? null,
        ]);

        $errorExamples = $failed > 0
            ? $this->db->select('display_name, phone_number, error_msg')
                ->from('wa_broadcast_line')
                ->where('broadcast_id', $id)
                ->where('status', 'FAILED')
                ->limit(3)
                ->get()
                ->result_array()
            : [];
        $this->jsonOut([
            // Delivery failure is a completed attempt: mark that recipient
            // failed, then let the browser continue to the next one.
            'ok' => true,
            'sent' => $sent,
            'failed' => $failed,
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'total_pending' => $totalPending,
            'has_more' => $hasMore,
            'status' => $finalStatus,
            'delay_seconds' => $hasMore
                ? $this->broadcastDelayForLine($broadcast['delay_pattern_json'] ?? null, $this->broadcastLineSequence($id, (int)$line['id']))
                : 0,
            'sent_line_id' => (int)$line['id'],
            'message' => $hasMore
                ? ($failed > 0
                    ? 'Nomor ini gagal; melanjutkan antrean ke nomor berikutnya.'
                    : 'Pesan personal dikirim, melanjutkan antrean berikutnya.')
                : ($failed > 0
                ? 'Sebagian/semua target gagal dikirim. Cek detail error per penerima, lalu gunakan tombol Kirim Ulang Gagal.'
                : 'Broadcast berhasil dikirim.'),
            'errors' => $errorExamples,
        ]);
    }

    // JSON API — preview pesan dari template + variabel
    public function api_template_preview()
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'view');
        $payload  = json_decode((string)$this->input->raw_input_stream, true) ?? [];
        $body     = (string)($payload['body'] ?? '');
        $vars     = (array)($payload['variables'] ?? []);
        $resolved = $this->resolveMessage($body, $vars);
        $this->jsonOut(['ok' => true, 'preview' => $resolved]);
    }

    public function api_schedule_run()
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        $result = $this->runDueWaReportSchedules();
        $this->load->model('Module_notification_model');
        try {
            $result['module_notifications'] = $this->Module_notification_model->run('WA', function (array $row): array {
                if (strpos($row['target_key'], 'phone:') === 0) {
                    if (!$this->personalOutboundEnabled()) return ['ok' => false, 'status' => 'FAILED'];
                    return $this->callBotApi('/internal/send', 'POST', ['to' => $row['destination'], 'message' => $row['message_text']]);
                }
                return $this->callBotApi('/internal/send-group', 'POST', ['group_jid' => $row['destination'], 'message' => $row['message_text'], 'document_path' => $row['attachment_path'] ?? null, 'document_name' => $row['attachment_name'] ?? null]);
            });
        } catch (Throwable $error) {
            log_message('error', 'Module notification WA worker failed; queue evidence retained.');
            $result['module_notifications'] = ['state' => 'ERROR'];
        }
        $this->jsonOut($result);
    }

    public function api_group_command()
    {
        if (!$this->require_wa_group_command_service_auth()) {
            return;
        }

        $payload = json_decode((string)$this->input->raw_input_stream, true) ?: [];
        $groupJid = trim((string)($payload['group_jid'] ?? ''));
        $commandRaw = trim((string)($payload['command'] ?? ''));
        if ($groupJid === '' || $commandRaw === '') {
            $this->jsonOut(['ok' => false, 'message' => 'group_jid dan command wajib diisi.']);
            return;
        }

        $group = $this->db->from('wa_group_map')
            ->where('group_jid', $groupJid)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$group) {
            $this->jsonOut(['ok' => false, 'message' => 'Grup belum terdaftar aktif.']);
            return;
        }

        $command = strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/^[!\/.#]+/', '', $commandRaw))));
        $command = $command === 'input mutasi' ? 'mutasi bantuan' : preg_replace('/^input\s+(?=mutasi\b)/', '', $command);
        $date = (strpos($command, 'kemarin') !== false) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
        $type = null;
        if (preg_match('/^(menu|help|bantuan|laporan)$/', $command)) {
            $this->jsonOut(['ok' => true, 'message' => $this->waCommandMenuMessage((string)$group['group_name'])]);
            return;
        }
        if (preg_match('/^mutasi\s+(help|bantuan|format)$/', $command)) {
            $this->jsonOut(['ok' => true, 'message' => $this->waMutationCommandHelp()]);
            return;
        }
        if (preg_match('/^mutasi\s+(in|out|transfer)\b/', $command)) {
            $this->jsonOut([
                'ok' => true,
                'message' => $this->dispatchWaMutationCommand($commandRaw, $command, $group, $payload),
            ]);
            return;
        }
        if (preg_match('/\bstok\s+kritis\b/', $command)) {
            $report = $this->buildWaCriticalStockReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bpos\s+pending\b/', $command)) {
            $report = $this->buildWaPosPendingReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bkas\s+hari\s+ini\b/', $command)) {
            $report = $this->buildWaCashTodayReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\brefund(\s+hari\s+ini)?\b/', $command)) {
            $report = $this->buildWaRefundTodayReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bvoid(\s+hari\s+ini)?\b/', $command)) {
            $report = $this->buildWaVoidTodayReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\btop\s+produk\b|\bproduk\s+terbanyak\b/', $command)) {
            $report = $this->buildWaTopProductReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\babsen\s+bolong\b/', $command)) {
            $report = $this->buildWaMissingAttendanceReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bpengajuan\s+absen\s+pending\b|\babsen\s+pending\b/', $command)) {
            $report = $this->buildWaPendingAttendanceRequestReport();
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bstock\s+missmatch\b|\bstok\s+missmatch\b|\bstock\s+mismatch\b|\bstok\s+mismatch\b/', $command)) {
            $report = $this->buildWaStockMismatchReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bstock\s+minus\b|\bstok\s+minus\b/', $command)) {
            $report = $this->buildWaStockMinusReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bbatch\s+gagal\b/', $command)) {
            $report = $this->buildWaBatchFailedReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bqueue\s+pos\b|\bpos\s+queue\b/', $command)) {
            $report = $this->buildWaPosQueueReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bestimasi\b|\bfinancial\s+estimation\b|\bestimasi\s+keuangan\b/', $command)) {
            $report = $this->buildWaFinancialEstimationReport($command, $date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\b(keuangan|saldo|rekening|kas)\b/', $command)) {
            $report = $this->buildWaFinanceSummaryReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\bmutasi\b/', $command)) {
            $report = $this->buildWaFinanceMutationReport($date);
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (preg_match('/\b(hutang|belum\s+bayar|belum\s+paid|po\s+belum\s+bayar)\b/', $command)) {
            $report = $this->buildWaUnpaidPurchaseReport();
            $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
            $this->jsonOut(['ok' => true, 'message' => $message]);
            return;
        }
        if (strpos($command, 'omzet') !== false) {
            $type = 'OMZET_TODAY';
        } elseif (strpos($command, 'belanja') !== false || strpos($command, 'purchase') !== false) {
            $type = 'PURCHASE_TODAY';
        } elseif (strpos($command, 'adjust') !== false || strpos($command, 'penyesuaian') !== false) {
            $type = 'ADJUSTMENT_TODAY';
        } elseif (strpos($command, 'pengajuan') !== false || strpos($command, 'po sr') !== false || preg_match('/\b(po|sr)\b/', $command)) {
            $type = 'PO_SR_TODAY';
        }

        if ($type === null) {
            $this->jsonOut(['ok' => true, 'message' => "Perintah tidak dikenali.\n\n" . $this->waCommandMenuMessage((string)$group['group_name'])]);
            return;
        }

        $report = $this->buildWaDynamicReport($type, $date);
        $message = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
        $this->jsonOut(['ok' => true, 'message' => $message]);
    }

    private function require_wa_group_command_service_auth(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_group_command_service_auth(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // Tolak credential legacy secara eksplisit agar caller tidak dapat
        // mencampur token URL/internal engine dengan credential callback ini.
        $legacyQueryToken = trim((string)$this->input->get('token', true));
        $legacyHeaderToken = trim((string)$this->input->get_request_header(self::WA_GROUP_COMMAND_LEGACY_CI_HEADER, true));
        if ($legacyQueryToken !== '' || $legacyHeaderToken !== '') {
            $this->reject_wa_group_command_service_auth(403, 'Credential command tidak valid.');
            return false;
        }

        $expectedToken = $this->waEngineSecretValue(self::WA_GROUP_COMMAND_TOKEN_ENV);
        if ($expectedToken === '') {
            $this->reject_wa_group_command_service_auth(403, 'Credential command tidak valid.');
            return false;
        }

        // Nama ini adalah bentuk kanonis lookup header CodeIgniter/FastCGI.
        // Tidak ada fallback ke query, X-Sync-Token, wa_session, atau token dev.
        $providedToken = trim((string)$this->input->get_request_header(self::WA_GROUP_COMMAND_TOKEN_CI_HEADER, true));
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            $this->reject_wa_group_command_service_auth(403, 'Credential command tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_group_command_service_auth(int $statusCode, string $message): void
    {
        $this->output->set_status_header($statusCode);
        $this->jsonOut([
            'ok' => false,
            'message' => $message,
        ]);
    }

    // JSON API — ambil QR aktif dari wa-engine (polling saat WAITING_QR).
    // QR WhatsApp berumur singkat, jadi jangan kirim QR cache ketika engine mati.
    public function api_qr()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');
        $result = $this->callBotApi('/internal/qr', 'GET');
        if (($result['ok'] ?? false) === true) {
            $this->jsonOut($result);
            return;
        }

        // Token internal dapat belum tersedia pada process manager, sedangkan
        // engine tetap sehat dan sudah menyimpan QR terbaru di wa_session.
        // Jangan pernah melayani QR cache bila engine mati agar QR kedaluwarsa
        // tidak disalahartikan sebagai QR aktif.
        if ($this->engineHealthCheck(1)) {
            $session = $this->waSession();
            $qr = trim((string)($session['qr_data'] ?? ''));
            $this->jsonOut([
                'ok'     => true,
                'status' => (string)($session['status'] ?? 'UNKNOWN'),
                'qr'     => $qr !== '' ? $qr : null,
                'has_qr' => $qr !== '',
                'message' => $qr !== ''
                    ? 'QR aktif diambil dari status engine.'
                    : 'Engine aktif, menunggu QR baru dari WhatsApp.',
            ]);
            return;
        }

        $this->jsonOut([
            'ok'      => false,
            'status'  => 'ENGINE_OFFLINE',
            'has_qr'  => false,
            'message' => 'wa-engine tidak dapat dijangkau. Jalankan atau restart engine untuk membuat QR baru.',
        ]);
    }

    // JSON API — cek apakah proses wa-engine berjalan
    public function api_engine_status()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');

        if (!function_exists('exec')) {
            $this->jsonOut(['ok' => false, 'running' => false, 'message' => 'PHP exec() dinonaktifkan di server ini.']);
            return;
        }

        $status = $this->engineStatusSnapshot();
        $engineHealthy = $this->engineHealthCheck(1);
        $running = $status['running'] || $engineHealthy;
        $portListening = $status['port_listening'] || $engineHealthy;
        $message = '';
        if ($engineHealthy) {
            $message = 'Engine merespons health-check lokal.';
        } elseif ($status['running'] && !$status['port_listening']) {
            $message = 'Proses node terdeteksi, tetapi port belum listen.';
        } elseif (!$status['running'] && $status['port_listening']) {
            $message = 'Port aktif, tetapi proses node utama tidak terdeteksi.';
        }

        $this->jsonOut([
            'ok'             => true,
            'running'        => $running,
            'pids'           => $status['process_pids'],
            'port'           => $status['port'],
            'port_pids'      => $status['port_pids'],
            'port_listening' => $portListening,
            'message'        => $message,
        ]);
    }

    // JSON API — mulai wa-engine via nohup
    public function api_engine_start()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_wa_engine_control_csrf()) {
            return;
        }

        if (!function_exists('exec')) {
            $this->jsonOut(['ok' => false, 'message' => 'PHP exec() dinonaktifkan di server ini.']);
            return;
        }

        $engineDir = realpath(FCPATH . 'wa-engine');
        if (!$engineDir || !file_exists($engineDir . '/index.js')) {
            $this->jsonOut(['ok' => false, 'message' => 'Folder wa-engine tidak ditemukan di: ' . FCPATH . 'wa-engine']);
            return;
        }
        if (!is_dir($engineDir . '/node_modules')) {
            $this->jsonOut([
                'ok' => false,
                'message' => 'Dependency wa-engine belum terpasang. Jalankan npm ci dari folder wa-engine sebelum Start.',
            ]);
            return;
        }

        // Health-check loopback tidak memerlukan credential API. Deteksi
        // lsof/ss dapat gagal ketika PHP-FPM tidak diizinkan melihat PID Node.
        if ($this->engineHealthCheck(1)) {
            $this->jsonOut([
                'ok'      => true,
                'running' => true,
                'message' => 'wa-engine sudah berjalan dan merespons health-check lokal.',
            ]);
            return;
        }

        $status = $this->engineStatusSnapshot();
        $port = $status['port'];
        if (!empty($status['process_pids']) && $status['port_listening']) {
            $this->jsonOut([
                'ok'      => true,
                'running' => true,
                'pid'     => $status['process_pids'][0],
                'message' => "wa-engine sudah berjalan · PID " . implode(', ', $status['process_pids']),
            ]);
            return;
        }
        if (empty($status['process_pids']) && !empty($status['port_pids'])) {
            $this->jsonOut(['ok' => false, 'message' => "Port {$port} sedang dipakai proses lain (PID: " . implode(', ', $status['port_pids']) . "). Hentikan proses itu dulu."]);
            return;
        }
        if (!empty($status['process_pids']) && !$status['port_listening']) {
            $this->jsonOut(['ok' => false, 'message' => "Proses wa-engine lama masih terdeteksi (PID: " . implode(', ', $status['process_pids']) . ') tetapi port belum aktif. Klik Stop lalu Start kembali.']);
            return;
        }

        $nodePath = $this->findNodePath();
        if (!$nodePath) {
            // Coba info tambahan via exec untuk debug
            $whichOut = [];
            exec('which node 2>/dev/null || which nodejs 2>/dev/null', $whichOut);
            $whichResult = trim($whichOut[0] ?? '');
            $hint = $whichResult !== ''
                ? " (which node: {$whichResult} — isi field 'Path Node.js' di Settings dengan path ini)"
                : ' — jalankan: which node, lalu isi field "Path Node.js" di Settings.';
            $this->jsonOut(['ok' => false, 'message' => 'Node.js tidak ditemukan.' . $hint]);
            return;
        }

        $envStr  = $this->buildEnvString($engineDir);
        $logPath = $this->engineLogPath($engineDir, true);
        if (!file_exists($logPath)) {
            @touch($logPath);
        }
        clearstatcache(true, $logPath);
        if (!is_file($logPath) || !is_writable($logPath)) {
            $this->jsonOut([
                'ok' => false,
                'message' => 'Log runtime wa-engine tidak dapat ditulis. Periksa permission folder wa-engine sebelum Start.',
            ]);
            return;
        }
        $pidPath = rtrim($engineDir, '/\\') . '/wa-engine.pid';
        if (file_exists($pidPath)) {
            @unlink($pidPath);
        }
        // Environment assignment wajib dijalankan oleh `env`; tanpa itu Bash
        // menganggap WA_PORT=3070 sebagai executable dan engine langsung mati.
        $runner = "exec env {$envStr}" . escapeshellarg($nodePath)
            . " " . escapeshellarg($engineDir . '/index.js')
            . " >> " . escapeshellarg($logPath) . " 2>&1";

        $ssdPath = '';
        $ssdOut = [];
        exec('command -v start-stop-daemon 2>/dev/null', $ssdOut);
        if (!empty($ssdOut[0])) {
            $ssdPath = trim((string)$ssdOut[0]);
        }
        if ($ssdPath !== '') {
            $cmd = "cd " . escapeshellarg($engineDir)
                . " && " . escapeshellarg($ssdPath)
                . " --start --background"
                . " --chdir " . escapeshellarg($engineDir)
                . " --make-pidfile --pidfile " . escapeshellarg($pidPath)
                . " --startas /bin/bash -- -c " . escapeshellarg($runner)
                . " && cat " . escapeshellarg($pidPath)
                . " 2>&1";
        } else {
            // Fallback untuk server tanpa start-stop-daemon.
            $cmd = "cd " . escapeshellarg($engineDir)
                 . " && {$envStr}nohup " . escapeshellarg($nodePath)
                 . " " . escapeshellarg($engineDir . '/index.js')
                 . " >> " . escapeshellarg($logPath) . " 2>&1 </dev/null & echo \$!"
                 . " 2>&1";
        }

        $output = [];
        exec($cmd, $output);
        // Ambil baris terakhir yang berisi angka murni sebagai PID
        $pid = 0;
        foreach (array_reverse($output) as $line) {
            $line = trim($line);
            if (ctype_digit($line)) { $pid = (int)$line; break; }
        }

        $running = false;
        $portListening = false;
        // Import Baileys dan pemulihan sesi dapat melampaui enam detik pada
        // server yang sedang sibuk. Tunggu sampai 20 detik sebelum menyatakan
        // bootstrap gagal, sambil tetap memeriksa port setiap setengah detik.
        for ($i = 0; $i < 40; $i++) {
            usleep(500000);
            $check = $this->engineStatusSnapshot();
            $engineHealthy = $this->engineHealthCheck(1);
            $running = $check['running'] || $engineHealthy;
            $portListening = $check['port_listening'] || $engineHealthy;
            if ($running && $portListening) {
                break;
            }
        }

        if ($running && $portListening) {
            $suffix = basename($logPath) !== 'wa-engine.log'
                ? ' · Log fallback: ' . basename($logPath)
                : '';
            $this->jsonOut(['ok' => true, 'pid' => $pid, 'message' => "wa-engine berjalan · Node: {$nodePath} · PID {$pid}{$suffix}"]);
        } else {
            $cmdHint = '';
            if (!empty($output)) {
                $cmdHint = ' | Output start: ' . mb_substr(trim(implode(' | ', $output)), 0, 300, 'UTF-8');
            }
            $logHint = '(log tidak ada — proses crash seketika)';
            if (file_exists($logPath)) {
                $logLines = [];
                exec("tail -n 8 " . escapeshellarg($logPath) . " 2>/dev/null", $logLines);
                if (!empty($logLines)) {
                    // Hapus ANSI escape codes, karakter kontrol, dan batasi panjang
                    $raw = implode(' | ', array_filter($logLines));
                    $raw = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/u', '', $raw);
                    $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw);
                    $raw = mb_substr(trim($raw), 0, 400, 'UTF-8');
                    if ($raw !== '') $logHint = 'Log: ' . $raw;
                }
            }
            $this->jsonOut(['ok' => false, 'message' => "Port {$port} belum terbuka setelah 20 detik. {$logHint} | Node: {$nodePath}{$cmdHint}"]);
        }
    }

    // JSON API — hentikan wa-engine
    public function api_engine_stop()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_wa_engine_control_csrf()) {
            return;
        }

        if (!function_exists('exec')) {
            $this->jsonOut(['ok' => false, 'message' => 'PHP exec() dinonaktifkan di server ini.']);
            return;
        }

        $status = $this->engineStatusSnapshot();
        $port = $status['port'];
        $pids = array_values(array_unique(array_merge($status['process_pids'], $status['port_pids'])));

        if (empty($pids)) {
            $this->removeEnginePidFile();
            $this->jsonOut(['ok' => true, 'message' => "wa-engine sudah tidak berjalan."]);
            return;
        }

        foreach ($pids as $pid) {
            exec("kill {$pid} 2>/dev/null");
        }

        usleep(1200000);
        $after = $this->engineStatusSnapshot();
        if ($after['running'] || $after['port_listening']) {
            foreach ($pids as $pid) {
                exec("kill -9 {$pid} 2>/dev/null");
            }
            usleep(800000);
            $after = $this->engineStatusSnapshot();
        }

        if (!$after['running'] && !$after['port_listening']) {
            $this->removeEnginePidFile();
            $this->jsonOut(['ok' => true, 'message' => 'wa-engine dihentikan (PID: ' . implode(', ', $pids) . ')']);
        } else {
            $still = array_values(array_unique(array_merge($after['process_pids'], $after['port_pids'])));
            $this->jsonOut(['ok' => false, 'message' => 'Stop gagal penuh. Proses masih aktif: ' . implode(', ', $still) . '. Jika dikelola PM2, hentikan via `pm2 stop wa-engine`.']);
        }
    }

    // JSON API — status diagnostik log wa-engine
    public function api_engine_logs()
    {
        // Permission harus diputuskan sebelum path atau status file disentuh.
        $this->require_permission(self::PAGE_SETTINGS, 'edit');

        $engineDir = realpath(FCPATH . 'wa-engine') ?: (FCPATH . 'wa-engine');
        $logFile   = $this->engineLogPath($engineDir, false);
        $available = is_file($logFile) && is_readable($logFile);

        // Isi log, path, nama file, dan directory listing tidak boleh keluar
        // melalui HTTP. Endpoint ini hanya memberi status diagnostik generik.
        $this->jsonOut([
            'ok' => true,
            'status' => $available ? 'available' : 'unavailable',
            'available' => $available,
            'message' => $available
                ? 'Log diagnostik tersedia di server.'
                : 'Log diagnostik belum tersedia di server.',
        ]);
    }

    // JSON API — baca file .env wa-engine
    public function api_env_read()
    {
        // Permission harus diputuskan sebelum path atau file secret disentuh.
        $this->require_permission(self::PAGE_SETTINGS, 'edit');

        $engineDir = realpath(FCPATH . 'wa-engine');
        $envFile = $engineDir ? $engineDir . '/.env' : '';
        $exists = $envFile !== '' && is_file($envFile);
        $content = '';
        if ($exists) {
            $read = @file_get_contents($envFile);
            if ($read === false) {
                $this->jsonOut([
                    'ok' => false,
                    'message' => 'Status konfigurasi wa-engine tidak dapat dibaca.',
                ]);
                return;
            }
            $content = $read;
        }

        // Pertahankan key `env` untuk kompatibilitas UI, tetapi hanya berisi
        // boolean status key yang dikenal. Nilai .env tidak pernah dipantulkan.
        $this->jsonOut([
            'ok' => true,
            'env' => $this->waEnvStatusFromContent($content),
            'exists' => $exists,
        ]);
    }

    // JSON API — simpan file .env wa-engine
    public function api_env_save()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_wa_env_save_csrf()) {
            return;
        }

        $decoded = json_decode((string)$this->input->raw_input_stream, true);
        $payload = is_array($decoded) ? $decoded : [];
        $engineDir = realpath(FCPATH . 'wa-engine');

        if (!$engineDir) {
            $this->jsonOut(['ok' => false, 'message' => 'Folder wa-engine tidak ditemukan.']);
            return;
        }

        $envFile = $engineDir . '/.env';
        $exists = is_file($envFile);
        $existingContent = '';
        if ($exists) {
            $read = @file_get_contents($envFile);
            if ($read === false) {
                $this->jsonOut([
                    'ok' => false,
                    'message' => 'Konfigurasi wa-engine tidak dapat disimpan dengan aman.',
                ]);
                return;
            }
            $existingContent = $read;
        }

        $updates = $this->waEnvUpdatesFromPayload($payload);
        if ($updates === []) {
            $this->jsonOut([
                'ok' => true,
                'env' => $this->waEnvStatusFromContent($existingContent),
                'exists' => $exists,
                'message' => 'Tidak ada nilai konfigurasi yang diubah.',
            ]);
            return;
        }

        $content = $this->mergeWaEnvContent($existingContent, $updates);
        if (!$this->writeWaEnvAtomically($envFile, $content)) {
            $this->jsonOut([
                'ok' => false,
                'permission' => true,
                'message' => 'Konfigurasi wa-engine tidak dapat disimpan. Pastikan folder runtime wa-engine dapat ditulis oleh proses PHP.',
            ]);
            return;
        }

        $this->jsonOut([
            'ok' => true,
            'env' => $this->waEnvStatusFromContent($content),
            'exists' => true,
            'message' => 'File .env berhasil disimpan. Restart wa-engine agar perubahan berlaku.',
        ]);
    }

    /**
     * Bentuk status aman untuk UI. Hanya key yang didukung yang disebutkan;
     * nilai dan nama key tambahan dari file tidak pernah keluar dari controller.
     */
    private function waEnvStatusFromContent(string $content): array
    {
        $configured = array_fill_keys(self::WA_ENV_FIELDS, false);
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }
            $key = $matches[1];
            if (!array_key_exists($key, $configured)) {
                continue;
            }
            $value = trim((string)$matches[2]);
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            $configured[$key] = trim($value) !== '';
        }

        $status = [];
        foreach (self::WA_ENV_FIELDS as $key) {
            $status[$key] = [
                'configured' => $configured[$key],
                'secret' => in_array($key, self::WA_ENV_SECRET_FIELDS, true),
            ];
        }
        return $status;
    }

    /**
     * Secret kosong selalu berarti "tidak berubah". Non-secret hanya berubah
     * bila key dikirim, termasuk ketika caller sengaja mengirim string kosong.
     */
    private function waEnvUpdatesFromPayload(array $payload): array
    {
        $updates = [];
        foreach (self::WA_ENV_FIELDS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = str_replace(["\r", "\n", "\0"], '', (string)$payload[$key]);
            if (in_array($key, self::WA_ENV_SECRET_FIELDS, true) && trim($value) === '') {
                continue;
            }
            $updates[$key] = $value;
        }
        return $updates;
    }

    /**
     * Update hanya key yang diminta. Baris lain, termasuk secret tambahan yang
     * tidak dikelola UI, dipertahankan tanpa mem-parsing atau memantulkannya.
     */
    private function mergeWaEnvContent(string $existingContent, array $updates): string
    {
        $lines = $existingContent === ''
            ? ['# wa-engine environment — digenerate oleh Finance App', '']
            : (preg_split('/\r\n|\r|\n/', $existingContent) ?: []);
        $seen = [];
        $merged = [];

        // Defense in depth: pemanggil normal sudah melewati
        // waEnvUpdatesFromPayload(), tetapi merge tidak boleh bisa menyisipkan
        // baris baru bila kelak dipakai dari jalur internal lain.
        foreach ($updates as $key => $value) {
            if (!in_array($key, self::WA_ENV_FIELDS, true)) {
                unset($updates[$key]);
                continue;
            }
            $updates[$key] = str_replace(["\r", "\n", "\0"], '', (string)$value);
        }

        foreach ($lines as $line) {
            if (!preg_match('/^(\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*)=(.*)$/', $line, $matches)) {
                $merged[] = $line;
                continue;
            }
            $key = $matches[2];
            if (!array_key_exists($key, $updates)) {
                $merged[] = $line;
                continue;
            }

            // Kemunculan pertama menjadi posisi kanonis. Duplikat key yang
            // disentuh dibuang; key yang tidak disentuh tetap apa adanya.
            if (isset($seen[$key])) {
                continue;
            }
            $merged[] = $matches[1] . $key . $matches[3] . '=' . $updates[$key];
            $seen[$key] = true;
        }

        foreach ($updates as $key => $value) {
            if (!isset($seen[$key])) {
                $merged[] = $key . '=' . $value;
            }
        }

        return rtrim(implode("\n", $merged), "\n") . "\n";
    }

    /**
     * Keep the previous .env intact if a write is interrupted. The runtime
     * directory is shared by PHP-FPM and the Node process, so retain group
     * write access after replacing the file.
     */
    private function writeWaEnvAtomically(string $envFile, string $content): bool
    {
        $directory = dirname($envFile);
        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        $temporary = @tempnam($directory, '.env-write-');
        if ($temporary === false) {
            return false;
        }

        $written = @file_put_contents($temporary, $content, LOCK_EX);
        if ($written === false || !@chmod($temporary, 0660) || !@rename($temporary, $envFile)) {
            @unlink($temporary);
            return false;
        }

        @chmod($envFile, 0660);
        return true;
    }

    // JSON API — hapus sesi WA (auth_info) untuk paksa QR baru
    public function api_session_reset()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_wa_engine_control_csrf()) {
            return;
        }

        $engineDir = realpath(FCPATH . 'wa-engine');
        if (!$engineDir) {
            $this->jsonOut(['ok' => false, 'message' => 'Folder wa-engine tidak ditemukan.']);
            return;
        }

        $authDir = $engineDir . '/auth_info';
        if (!is_dir($authDir)) {
            $this->jsonOut(['ok' => true, 'message' => 'Folder auth_info belum ada — sesi sudah bersih.']);
            return;
        }

        if (function_exists('exec')) {
            $status = $this->engineStatusSnapshot();
            $pids = array_values(array_unique(array_merge($status['process_pids'], $status['port_pids'])));
            foreach ($pids as $pid) {
                exec('kill ' . (int)$pid . ' 2>/dev/null');
            }
            usleep(1000000);
            $statusAfterKill = $this->engineStatusSnapshot();
            $remainingPids = array_values(array_unique(array_merge($statusAfterKill['process_pids'], $statusAfterKill['port_pids'])));
            foreach ($remainingPids as $pid) {
                exec('kill -9 ' . (int)$pid . ' 2>/dev/null');
            }
            usleep(500000);
        }

        // Hapus semua file di dalam auth_info, lalu folder-nya
        $deleted = 0;
        foreach (glob($authDir . '/*') ?: [] as $file) {
            if (is_file($file)) { @unlink($file); $deleted++; }
        }
        @rmdir($authDir);

        if (is_dir($authDir)) {
            $this->jsonOut(['ok' => false, 'message' => "Tidak semua file berhasil dihapus (terhapus: {$deleted}). Coba hapus manual: rm -rf " . escapeshellarg($authDir)]);
            return;
        }

        $this->db->where('id', 1)->update('wa_session', [
            'status'       => 'UNKNOWN',
            'phone_number' => null,
            'qr_data'      => null,
            'last_ping_at' => date('Y-m-d H:i:s'),
        ]);

        $this->jsonOut(['ok' => true, 'message' => "Sesi WA direset ({$deleted} file dihapus). Klik Start wa-engine lalu scan QR baru."]);
    }

    // ──────────────────────────────────────────────────────────
    // PANDUAN
    // ──────────────────────────────────────────────────────────
    public function guide()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');
        $session = $this->waSessionStatusView($this->waSession());
        $this->render('wa/guide', [
            'title'       => 'Panduan WhatsApp Bot',
            'active_menu' => 'wa.settings',
            'session'     => $session,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────
    private function waSession(): array
    {
        return $this->db->from('wa_session')->where('id', 1)->limit(1)->get()->row_array()
            ?: ['id' => 1, 'status' => 'UNKNOWN', 'bot_api_url' => 'http://127.0.0.1:3070', 'bot_api_token' => '', 'node_path' => ''];
    }

    private function waSessionStatusView(array $session): array
    {
        return [
            'status' => (string)($session['status'] ?? 'UNKNOWN'),
            'phone_number' => (string)($session['phone_number'] ?? ''),
            'last_ping_at' => (string)($session['last_ping_at'] ?? ''),
        ];
    }

    private function dashboardStats(): array
    {
        $totalBroadcast  = (int)$this->db->count_all('wa_broadcast');
        $doneBroadcast   = (int)$this->db->where('status', 'DONE')->count_all_results('wa_broadcast');
        $totalSent       = (int)$this->db->select_sum('total_sent')->from('wa_broadcast')->get()->row_array()['total_sent'];
        $todaySent       = (int)$this->db->where('DATE(sent_at)', date('Y-m-d'))->where('status', 'SENT')->count_all_results('wa_send_log');
        $totalTemplates  = (int)$this->db->where('is_active', 1)->count_all_results('wa_template');
        $totalGroups     = (int)$this->db->where('is_active', 1)->count_all_results('wa_group_map');

        $recentLogs = $this->db->from('wa_send_log')->order_by('sent_at', 'DESC')->limit(10)->get()->result_array();

        return compact('totalBroadcast','doneBroadcast','totalSent','todaySent','totalTemplates','totalGroups','recentLogs');
    }

    private function createManualBulkQueue(): void
    {
        $redirectBase = 'wa/manual?tab=bulk';
        if (!$this->personalOutboundEnabled()) {
            $this->session->set_flashdata('error', self::PERSONAL_OUTBOUND_LOCK_MESSAGE);
            redirect($redirectBase);
            return;
        }
        $message = trim((string)$this->input->post('message', false));
        $manualLines = (string)$this->input->post('manual_numbers', false);
        $memberIdsRaw = trim((string)$this->input->post('selected_member_ids', true));
        $media = $this->handleWaImageUpload('media_image');

        if (!empty($media['error'])) {
            $this->session->set_flashdata('error', $media['error']);
            redirect($redirectBase);
            return;
        }
        if ($message === '' && empty($media['path'])) {
            $this->session->set_flashdata('error', 'Isi pesan atau gambar wajib diisi.');
            redirect($redirectBase);
            return;
        }

        $targets = $this->manualTargetsFromInput($manualLines, $memberIdsRaw);
        if (empty($targets)) {
            $this->session->set_flashdata('error', 'Pilih minimal satu member atau isi nomor tujuan yang valid.');
            redirect($redirectBase);
            return;
        }

        $delayPattern = $this->broadcastDelayPatternFromRequest();
        $this->db->trans_start();
        $this->db->insert('wa_broadcast', [
            'name' => 'Bulk Manual · ' . date('d/m/Y H:i'),
            'template_id' => null,
            'custom_message' => $message ?: null,
            'media_path' => $media['path'] ?? null,
            'media_url' => $media['url'] ?? null,
            'media_mime' => $media['mime'] ?? null,
            'media_name' => $media['name'] ?? null,
            'target_type' => 'CUSTOM',
            'status' => 'DRAFT',
            'scheduled_at' => null,
            'delay_pattern_json' => $this->encodeBroadcastDelayPattern($delayPattern),
            'total_targets' => count($targets),
            'notes' => self::MANUAL_BULK_NOTE_PREFIX . ' Antrean dibuat dari tab Kirim Pesan Bulk.',
            'created_by' => (int)($this->current_user['id'] ?? 0),
        ]);
        $bulkId = (int)$this->db->insert_id();
        $this->insertBroadcastLines($bulkId, $targets);
        $this->db->trans_complete();

        if (!$this->db->trans_status() || $bulkId <= 0) {
            $this->session->set_flashdata('error', 'Antrean pesan bulk gagal dibuat.');
            redirect($redirectBase);
            return;
        }

        $this->session->set_flashdata('success', 'Antrean bulk dibuat untuk ' . count($targets) . ' nomor. Pengiriman akan dimulai otomatis.');
        redirect($redirectBase . '&bulk_id=' . $bulkId . '&autostart=1');
    }

    private function manualTargetsFromInput(string $manualLines, string $memberIdsRaw): array
    {
        $targets = [];
        foreach ($this->parseManualTargets($manualLines) as $target) {
            $phone = (string)($target['phone'] ?? '');
            if ($phone === '') {
                continue;
            }
            $name = trim((string)($target['name'] ?? ''));
            $targets[$phone] = [
                'phone' => $phone,
                'name' => $name,
                'vars' => ['nama' => $name ?: 'Pelanggan'],
            ];
        }

        foreach ($this->manualMemberRowsByIds($this->manualMemberIdsFromRaw($memberIdsRaw)) as $member) {
            $phone = $this->normalizeWaPhone((string)($member['mobile_phone'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $name = trim((string)($member['member_name'] ?? ''));
            $targets[$phone] = [
                'phone' => $phone,
                'name' => $name,
                'vars' => ['nama' => $name ?: 'Pelanggan'],
            ];
        }

        return array_values($targets);
    }

    private function manualMemberIdsFromRaw(string $raw): array
    {
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', $raw)))));
    }

    private function isManualBulkQueue(array $broadcast): bool
    {
        return strtoupper((string)($broadcast['target_type'] ?? '')) === 'CUSTOM'
            && strpos((string)($broadcast['notes'] ?? ''), self::MANUAL_BULK_NOTE_PREFIX) === 0;
    }

    private function applyManualMemberPickerFilters(string $q): void
    {
        $this->db->where('is_active', 1)
            ->where('member_status', 'ACTIVE')
            ->where('mobile_phone IS NOT NULL', null, false)
            ->where('mobile_phone !=', '');
        if ($q !== '') {
            $this->db->group_start()
                ->like('member_no', $q)
                ->or_like('member_name', $q)
                ->or_like('mobile_phone', $q)
            ->group_end();
        }
    }

    private function saveBroadcast(array $data, string $manualLines = '', string $targetType = 'MANUAL', array $selectedMemberIds = []): int
    {
        $this->db->insert('wa_broadcast', $data);
        $broadcastId = (int)$this->db->insert_id();

        $targets = $this->buildBroadcastTargets($manualLines, $targetType, $selectedMemberIds);
        $this->insertBroadcastLines($broadcastId, $targets);
        $this->db->where('id', $broadcastId)->update('wa_broadcast', ['total_targets' => count($targets)]);

        return $broadcastId;
    }

    private function broadcastDelayPatternFromRequest(): array
    {
        return $this->normalizeBroadcastDelayPattern($this->input->post('delay_pattern', false));
    }

    private function normalizeBroadcastDelayPattern($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        $value = is_array($value) ? $value : [];
        $isList = array_keys($value) === range(0, count($value) - 1);
        $pattern = [];
        for ($slot = 1; $slot <= 10; $slot++) {
            $raw = $isList
                ? ($value[$slot - 1] ?? 2)
                : ($value[$slot] ?? $value[(string)$slot] ?? 2);
            $pattern[$slot] = max(1, min(60, (int)$raw));
        }
        return $pattern;
    }

    private function encodeBroadcastDelayPattern(array $pattern): string
    {
        return json_encode($this->normalizeBroadcastDelayPattern($pattern));
    }

    private function broadcastDelayForLine($patternValue, int $sequence): int
    {
        $pattern = $this->normalizeBroadcastDelayPattern($patternValue);
        $slot = (($sequence - 1) % 10) + 1;
        return $pattern[$slot];
    }

    private function broadcastLineSequence(int $broadcastId, int $lineId): int
    {
        return max(1, (int)$this->db->from('wa_broadcast_line')
            ->where('broadcast_id', $broadcastId)
            ->where('id <=', $lineId)
            ->count_all_results());
    }

    private function buildBroadcastTargets(string $manualLines = '', string $targetType = 'MANUAL', array $selectedMemberIds = []): array
    {
        $targets = [];
        if ($targetType === 'MANUAL') {
            foreach ($this->parseManualTargets($manualLines) as $target) {
                $targets[] = ['phone' => $target['phone'], 'name' => $target['name'], 'vars' => []];
            }
        } elseif ($targetType === 'SELECTED_MEMBERS') {
            foreach ($this->manualMemberRowsByIds($selectedMemberIds) as $row) {
                $phone = $this->normalizeWaPhone((string)($row['mobile_phone'] ?? ''));
                if ($phone === '') continue;
                $name = (string)($row['member_name'] ?? '');
                $targets[] = ['phone' => $phone, 'name' => $name, 'vars' => ['nama' => $name ?: 'Pelanggan']];
            }
        } elseif (in_array($targetType, ['ALL_MEMBERS','MEMBER_ACTIVE'], true)) {
            if ($this->db->table_exists('crm_member')) {
                $q = $this->db->from('crm_member m')
                    ->select('m.mobile_phone, m.member_name')
                    ->where('m.mobile_phone IS NOT NULL')
                    ->where('m.mobile_phone !=', '');
                if ($targetType === 'MEMBER_ACTIVE') {
                    $q->where('m.is_active', 1)
                      ->where('m.member_status', 'ACTIVE');
                } else {
                    $q->where('m.member_status !=', 'CLOSED');
                }
                foreach ($q->get()->result_array() as $row) {
                    $phone = $this->normalizeWaPhone((string)($row['mobile_phone'] ?? ''));
                    if ($phone === '') continue;
                    $name = (string)($row['member_name'] ?? '');
                    $targets[] = ['phone' => $phone, 'name' => $name, 'vars' => ['nama' => $name ?: 'Pelanggan']];
                }
            }
        }
        return $targets;
    }

    private function insertBroadcastLines(int $broadcastId, array $targets): void
    {
        $rows = [];
        foreach ($targets as $t) {
            $rows[] = [
                'broadcast_id'  => $broadcastId,
                'phone_number'  => $t['phone'],
                'display_name'  => !empty($t['name']) ? mb_substr((string)$t['name'], 0, 100) : null,
                'variables_json'=> !empty($t['vars']) ? json_encode($t['vars'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
                'status'        => 'PENDING',
            ];
        }

        foreach (array_chunk($rows, 100) as $batch) {
            $this->db->insert_batch('wa_broadcast_line', $batch);
        }
    }

    private function broadcastManualLinesFromRows(array $lines): string
    {
        $rows = [];
        foreach ($lines as $line) {
            $phone = trim((string)($line['phone_number'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $name = trim((string)($line['display_name'] ?? ''));
            $rows[] = $name !== '' ? $phone . '|' . $name : $phone;
        }
        return implode("\n", $rows);
    }

    private function broadcastSelectedMembersFromRows(array $lines): array
    {
        if (empty($lines) || !$this->db->table_exists('crm_member')) {
            return [];
        }

        $phones = [];
        foreach ($lines as $line) {
            $phone = $this->normalizeWaPhone((string)($line['phone_number'] ?? ''));
            if ($phone !== '') {
                $phones[$phone] = true;
            }
        }
        if (empty($phones)) {
            return [];
        }

        $members = $this->db
            ->select('id, member_no, member_name, mobile_phone')
            ->from('crm_member')
            ->where('is_active', 1)
            ->where('member_status', 'ACTIVE')
            ->where('mobile_phone IS NOT NULL', null, false)
            ->where('mobile_phone !=', '')
            ->get()
            ->result_array();

        $matched = [];
        foreach ($members as $member) {
            $phone = $this->normalizeWaPhone((string)($member['mobile_phone'] ?? ''));
            if ($phone !== '' && isset($phones[$phone])) {
                $matched[] = $member;
            }
        }
        return $matched;
    }

    private function resolveMessage(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }
        return $template;
    }

    private function normalizeBotApiBaseUrl(string $url): ?string
    {
        if (preg_match('/\Ahttp:\/\/(127\.0\.0\.1|localhost):([1-9][0-9]{0,4})\/?\z/D', $url, $matches) !== 1) {
            return null;
        }

        $port = (int)$matches[2];
        if ($port < 1 || $port > 65535) {
            return null;
        }

        return 'http://127.0.0.1:' . $port;
    }

    protected function initializeBotApiCurl(string $url)
    {
        return curl_init($url);
    }

    /**
     * Liveness check for the local Node process. Unlike /internal/* this
     * endpoint is intentionally unauthenticated, but it is loopback-only and
     * returns no session, QR, or WhatsApp data.
     */
    private function engineHealthCheck(int $timeout = 1): bool
    {
        if (!function_exists('fsockopen')) {
            return false;
        }

        $port = $this->enginePort();
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, max(1, $timeout));
        if (!is_resource($socket)) {
            return false;
        }

        stream_set_timeout($socket, max(1, $timeout));
        fwrite($socket, "GET /health HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
        $line = fgets($socket);
        fclose($socket);

        return is_string($line) && preg_match('/\AHTTP\/1\.[01]\s+200\b/', $line) === 1;
    }

    private function callBotApi(string $endpoint, string $method = 'GET', array $payload = [], int $timeout = 8): array
    {
        $serviceToken = $this->waEngineSecretValue(self::WA_ENGINE_API_TOKEN_ENV);
        if ($serviceToken === '' || preg_match('/[\x00-\x1F\x7F]/', $serviceToken)) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'Credential internal WA Bot belum dikonfigurasi.'];
        }

        $session = $this->waSession();
        $botApiBaseUrl = $this->normalizeBotApiBaseUrl((string)($session['bot_api_url'] ?? ''));
        if ($botApiBaseUrl === null) {
            return ['ok' => false, 'status' => 'FAILED', 'message' => 'Konfigurasi URL WA Bot tidak valid.'];
        }

        $url = $botApiBaseUrl . $endpoint;

        $ch = $this->initializeBotApiCurl($url);
        $timeout = in_array($endpoint, ['/internal/send', '/internal/send-group'], true) ? 75 : max(1, $timeout);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_PROXY          => '',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                self::WA_ENGINE_API_TOKEN_HEADER . ': ' . $serviceToken,
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($jsonPayload === false) {
                return ['ok' => false, 'message' => 'Payload pesan WA tidak valid untuk dikirim.'];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        $response   = curl_exec($ch);
        $httpCode   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            if ($endpoint === '/internal/status') {
                $this->db->where('id', 1)->update('wa_session', [
                    'last_ping_at' => date('Y-m-d H:i:s'),
                    'status'       => 'DISCONNECTED',
                    'phone_number' => null,
                ]);
            }
            return ['ok' => false, 'message' => 'Tidak dapat menghubungi WA Bot: ' . $curlError];
        }

        $decoded = json_decode($response ?: '', true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'message' => 'Respon bot tidak valid (HTTP ' . $httpCode . ')'];
        }

        // These HTTP responses reject before dispatch in wa-engine. A 500/timeout
        // is ambiguous and must not cause an automatic duplicate notification.
        if (in_array($endpoint, ['/internal/send', '/internal/send-group'], true) && empty($decoded['ok'])) {
            $decoded['status'] = in_array($httpCode, [400, 401, 403, 423, 503], true) ? 'FAILED' : 'UNKNOWN';
        }

        // Update last_ping_at jika status endpoint
        if ($endpoint === '/internal/status') {
            $this->db->where('id', 1)->update('wa_session', [
                'last_ping_at' => date('Y-m-d H:i:s'),
                'status'       => strtoupper($decoded['status'] ?? 'UNKNOWN'),
                'phone_number' => $decoded['phone'] ?? null,
            ]);
        }

        return $decoded;
    }

    private function isBotUnavailableResult(array $result): bool
    {
        $message = strtolower(trim((string)($result['message'] ?? '')));
        return $message !== '' && (
            strpos($message, 'bot tidak terhubung') !== false
            || strpos($message, 'tidak dapat menghubungi wa bot') !== false
            || strpos($message, 'connection refused') !== false
        );
    }

    private function normalizeWaScheduleTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $raw = str_replace(['.', ' '], ':', $raw);
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $raw, $m)) {
            $hour = (int)$m[1];
            $minute = (int)$m[2];
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d:00', $hour, $minute);
            }
        }

        if (preg_match('/^(\d{2})(\d{2})$/', $raw, $m)) {
            $hour = (int)$m[1];
            $minute = (int)$m[2];
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d:00', $hour, $minute);
            }
        }

        return null;
    }

    private function parseWaScheduleTimes($raw): array
    {
        $times = [];
        $parts = is_array($raw) ? $raw : preg_split('/[\r\n,;]+/', (string)$raw);
        foreach ($parts as $part) {
            $time = $this->normalizeWaScheduleTime((string)$part);
            if ($time !== null) {
                $times[$time] = $time;
            }
        }
        return array_values($times);
    }

    private function logSend(?int $broadcastId, string $source, ?string $phone, ?string $groupJid, ?string $name, string $message, string $status, string $error = ''): void
    {
        $this->db->insert('wa_send_log', [
            'broadcast_id'   => $broadcastId,
            'source'         => $source,
            'phone_number'   => $phone,
            'group_jid'      => $groupJid,
            'display_name'   => $name,
            'message_preview'=> mb_substr($message, 0, 500),
            'status'         => $status,
            'error_detail'   => $error ?: null,
            'sent_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    private function normalizeWaPhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (strpos($phone, '00') === 0) {
            $phone = substr($phone, 2);
        }
        if (strpos($phone, '0') === 0) {
            $phone = '62' . substr($phone, 1);
        } elseif (strpos($phone, '8') === 0) {
            $phone = '62' . $phone;
        }
        return strlen($phone) >= 10 ? $phone : '';
    }

    private function parseManualTargets(string $manualLines): array
    {
        $targets = [];
        foreach (preg_split('/[\r\n,;]+/', $manualLines) as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $raw, 2));
            $phone = $this->normalizeWaPhone((string)($parts[0] ?? ''));
            if ($phone === '') {
                continue;
            }
            $targets[] = [
                'phone' => $phone,
                'name'  => (string)($parts[1] ?? ''),
                'source'=> 'manual',
            ];
        }
        return $targets;
    }

    private function manualMemberRowsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids) || !$this->db->table_exists('crm_member')) {
            return [];
        }
        return $this->db
            ->select('id, member_no, member_name, mobile_phone')
            ->from('crm_member')
            ->where('is_active', 1)
            ->where('member_status', 'ACTIVE')
            ->where_in('id', $ids)
            ->order_by('member_name', 'ASC')
            ->order_by('member_no', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    private function handleWaImageUpload(string $field): array
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        $error = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => 'Upload gambar gagal. Kode error: ' . $error];
        }

        $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
        $originalName = (string)($_FILES[$field]['name'] ?? 'image');
        $size = (int)($_FILES[$field]['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['error' => 'File upload tidak valid.'];
        }
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['error' => 'Ukuran gambar maksimal 5 MB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return ['error' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.'];
        }

        $relativeDir = 'uploads/wa/' . date('Y/m');
        $absoluteDir = rtrim(FCPATH, '/\\') . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return ['error' => 'Folder upload WA tidak bisa dibuat: ' . $absoluteDir];
        }
        if (!is_writable($absoluteDir)) {
            return ['error' => 'Folder upload WA tidak writable: ' . $absoluteDir];
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $absolutePath = $absoluteDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $absolutePath)) {
            return ['error' => 'Gagal menyimpan file gambar WA.'];
        }
        @chmod($absolutePath, 0644);

        $relativePath = $relativeDir . '/' . $filename;
        return [
            'path' => $absolutePath,
            'url'  => base_url($relativePath),
            'mime' => $mime,
            'name' => mb_substr($originalName, 0, 255),
        ];
    }

    private function messageLogPreview(string $message, array $media = []): string
    {
        $message = trim($message);
        $mediaName = trim((string)($media['name'] ?? ''));
        if (!empty($media['path']) || !empty($media['url'])) {
            $prefix = '[GAMBAR' . ($mediaName !== '' ? ': ' . $mediaName : '') . ']';
            return trim($prefix . ($message !== '' ? "\n" . $message : ''));
        }
        return $message;
    }

    private function retryMessageFromLog(array $log): string
    {
        $message = trim((string)($log['message_preview'] ?? ''));
        if (strtoupper((string)($log['source'] ?? '')) !== 'SCHEDULED') {
            return $message;
        }

        $probe = mb_strtolower($message, 'UTF-8');
        $type = null;
        if (strpos($probe, 'omzet') !== false) {
            $type = 'OMZET_TODAY';
        } elseif (strpos($probe, 'belanja') !== false) {
            $type = 'PURCHASE_TODAY';
        } elseif (strpos($probe, 'adjustment') !== false || strpos($probe, 'penyesuaian') !== false) {
            $type = 'ADJUSTMENT_TODAY';
        } elseif (strpos($probe, 'pengajuan po') !== false || strpos($probe, 'po/sr') !== false || strpos($probe, 'po sr') !== false) {
            $type = 'PO_SR_TODAY';
        }

        if ($type === null) {
            return $message;
        }

        $date = !empty($log['sent_at']) ? date('Y-m-d', strtotime((string)$log['sent_at'])) : date('Y-m-d');
        $report = $this->buildWaDynamicReport($type, $date);
        $rebuilt = trim((string)($report['title'] ?? '') . "\n\n" . (string)($report['body'] ?? ''));
        return $rebuilt !== '' ? $rebuilt : $message;
    }

    private function waReportTypeOptions(): array
    {
        return [
            'OMZET_TODAY' => 'Omzet hari ini',
            'PURCHASE_TODAY' => 'Rincian belanja hari ini',
            'ADJUSTMENT_TODAY' => 'Rincian adjustment hari ini',
            'PO_SR_TODAY' => 'Pengajuan PO/SR divisi hari ini',
        ];
    }

    private function waCommandMenuMessage(string $groupName = ''): string
    {
        $title = '*Menu Laporan Bot WA*';
        if (trim($groupName) !== '') {
            $title .= "\nGrup: " . trim($groupName);
        }
        return $title . "\n\n"
            . "Ketik salah satu perintah berikut di grup:\n"
            . "1. *menu* - menampilkan daftar perintah.\n"
            . "2. *omzet* - mengirim laporan omzet hari ini.\n"
            . "3. *belanja* - mengirim rincian belanja hari ini.\n"
            . "4. *adjustment* atau *penyesuaian* - mengirim rincian adjustment hari ini.\n"
            . "5. *pengajuan* atau *po sr* - mengirim pengajuan PO/SR hari ini.\n"
            . "6. *keuangan* atau *saldo* - saldo rekening dan rekap mutasi hari ini.\n"
            . "7. *mutasi* - daftar mutasi rekening hari ini.\n"
            . "8. *hutang* atau *po belum bayar* - daftar purchase order yang belum PAID.\n"
            . "9. *stok kritis* - daftar stok nol/minus dan component di bawah min stok.\n"
            . "10. *pos pending* - order POS aktif yang belum selesai/terposting.\n"
            . "11. *kas hari ini* - rekap mutasi kas/rekening hari ini.\n"
            . "12. *refund hari ini* - daftar refund hari ini.\n"
            . "13. *void hari ini* - daftar void hari ini.\n"
            . "14. *top produk* - produk terjual terbanyak hari ini.\n"
            . "15. *absen bolong* - check-in/check-out yang belum lengkap.\n"
            . "16. *pengajuan absen pending* - revisi absensi yang menunggu approval.\n"
            . "17. *stock missmatch* - mismatch stok vs lot bulan berjalan.\n"
            . "18. *stock minus* - stok negatif bulan berjalan.\n"
            . "19. *batch gagal* - batch component draft/gagal posting yang perlu dicek.\n"
            . "20. *queue pos* - queue commit stock POS pending/gagal.\n"
            . "21. *estimasi* - estimasi keuangan seperti halaman /finance-reports/financial-estimation.\n\n"
            . "22. *mutasi bantuan* - format input mutasi IN/OUT/TRANSFER (khusus admin grup).\n\n"
            . "Tambahkan kata *kemarin* untuk mengambil data kemarin. Contoh: *omzet kemarin*.\n"
            . "Untuk estimasi: *estimasi*, *estimasi bulan lalu*, *estimasi 2026-08*, atau *estimasi agustus 2026*.";
    }

    private function buildWaFinanceSummaryReport(string $date): array
    {
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['title' => 'Keuangan ' . $this->waDateLabel($date), 'body' => 'Tabel rekening/mutasi belum tersedia.'];
        }

        $accounts = $this->db
            ->select('id, account_code, account_name, account_type, current_balance')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('account_code', 'ASC')
            ->get()
            ->result_array();

        $summaryQuery = $this->db
            ->select("COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE 0 END),0) AS total_in", false)
            ->select("COALESCE(SUM(CASE WHEN mutation_type = 'OUT' THEN amount ELSE 0 END),0) AS total_out", false)
            ->select('COUNT(*) AS mutation_count', false)
            ->from('fin_account_mutation_log')
            ->where('mutation_date', $date);
        $this->applyEffectiveAccountMutationFilter($summaryQuery);
        $summary = $summaryQuery
            ->get()
            ->row_array() ?: [];

        $totalBalance = 0.0;
        $lines = [];
        $lines[] = '*Saldo Rekening Aktif*';
        if (!$accounts) {
            $lines[] = 'Belum ada rekening aktif.';
        } else {
            foreach ($accounts as $idx => $row) {
                $balance = (float)($row['current_balance'] ?? 0);
                $totalBalance += $balance;
                $lines[] = ($idx + 1) . '. ' . (string)($row['account_code'] ?? '-') . ' - ' . (string)($row['account_name'] ?? '-') . ' (' . (string)($row['account_type'] ?? '-') . '): ' . $this->waMoney($balance);
            }
        }

        $totalIn = (float)($summary['total_in'] ?? 0);
        $totalOut = (float)($summary['total_out'] ?? 0);
        $lines[] = '';
        $lines[] = '*Rekap Mutasi ' . $this->waDateLabel($date) . '*';
        $lines[] = 'Masuk: ' . $this->waMoney($totalIn);
        $lines[] = 'Keluar: ' . $this->waMoney($totalOut);
        $lines[] = 'Net: ' . $this->waMoney($totalIn - $totalOut);
        $lines[] = 'Jumlah mutasi: ' . number_format((int)($summary['mutation_count'] ?? 0), 0, ',', '.');
        $lines[] = '';
        $lines[] = '*Total Saldo*: ' . $this->waMoney($totalBalance);

        return ['title' => 'Keuangan ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaUnpaidPurchaseReport(): array
    {
        if (!$this->db->table_exists('pur_purchase_order')) {
            return ['title' => 'PO Belum PAID', 'body' => 'Tabel purchase order belum tersedia.'];
        }

        $summaryRows = $this->db
            ->select('po.status, COUNT(*) AS po_count, COALESCE(SUM(po.grand_total),0) AS total', false)
            ->from('pur_purchase_order po')
            ->where_not_in('po.status', ['DRAFT', 'REJECTED', 'VOID', 'PAID'])
            ->group_by('po.status')
            ->order_by('po.status', 'ASC')
            ->get()
            ->result_array();

        $rows = $this->db
            ->select('po.po_no, po.request_date, po.status, po.grand_total, pt.type_name AS purchase_type_name, v.vendor_name')
            ->from('pur_purchase_order po')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left')
            ->where_not_in('po.status', ['DRAFT', 'REJECTED', 'VOID', 'PAID'])
            ->order_by('po.request_date', 'ASC')
            ->order_by('po.id', 'ASC')
            ->limit(20)
            ->get()
            ->result_array();

        $grandTotal = 0.0;
        $grandCount = 0;
        $lines = [];
        $lines[] = '*Rekap Status*';
        if (!$summaryRows) {
            $lines[] = 'Tidak ada PO belum PAID.';
        } else {
            foreach ($summaryRows as $row) {
                $count = (int)($row['po_count'] ?? 0);
                $total = (float)($row['total'] ?? 0);
                $grandCount += $count;
                $grandTotal += $total;
                $lines[] = '- ' . (string)($row['status'] ?? '-') . ': ' . number_format($count, 0, ',', '.') . ' PO · ' . $this->waMoney($total);
            }
        }
        $lines[] = '';
        $lines[] = '*Total belum PAID*: ' . number_format($grandCount, 0, ',', '.') . ' PO · ' . $this->waMoney($grandTotal);
        $lines[] = '';
        if ($rows) {
            $lines[] = '*Detail tertua*';
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . (string)($row['po_no'] ?? '-') . ' · ' . $this->waDateLabel((string)($row['request_date'] ?? date('Y-m-d'))) . ' · ' . (string)($row['status'] ?? '-') . ' · ' . trim((string)($row['purchase_type_name'] ?? '-')) . ' · ' . trim((string)($row['vendor_name'] ?? '-')) . ' · ' . $this->waMoney((float)($row['grand_total'] ?? 0));
            }
            if ($grandCount > 20) {
                $lines[] = '...dan ' . number_format($grandCount - 20, 0, ',', '.') . ' PO lain.';
            }
        }

        return ['title' => 'PO Belum PAID', 'body' => implode("\n", $lines)];
    }

    private function buildWaFinanceMutationReport(string $date): array
    {
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['title' => 'Mutasi Rekening ' . $this->waDateLabel($date), 'body' => 'Tabel rekening/mutasi belum tersedia.'];
        }

        $summaryQuery = $this->db
            ->select("COALESCE(SUM(CASE WHEN l.mutation_type = 'IN' THEN l.amount ELSE 0 END),0) AS total_in", false)
            ->select("COALESCE(SUM(CASE WHEN l.mutation_type = 'OUT' THEN l.amount ELSE 0 END),0) AS total_out", false)
            ->select('COUNT(*) AS mutation_count', false)
            ->from('fin_account_mutation_log l')
            ->where('l.mutation_date', $date);
        $this->applyEffectiveAccountMutationFilter($summaryQuery, 'l');
        $summary = $summaryQuery
            ->get()
            ->row_array() ?: [];

        $rowsQuery = $this->db
            ->select('l.*, a.account_code, a.account_name')
            ->from('fin_account_mutation_log l')
            ->join('fin_company_account a', 'a.id = l.account_id', 'left')
            ->where('l.mutation_date', $date)
            ->order_by('l.created_at', 'DESC')
            ->order_by('l.id', 'DESC');
        $this->applyEffectiveAccountMutationFilter($rowsQuery, 'l');
        $rows = $rowsQuery
            ->limit(15)
            ->get()
            ->result_array();

        $totalIn = (float)($summary['total_in'] ?? 0);
        $totalOut = (float)($summary['total_out'] ?? 0);
        $lines = [];
        $lines[] = 'Masuk: ' . $this->waMoney($totalIn);
        $lines[] = 'Keluar: ' . $this->waMoney($totalOut);
        $lines[] = 'Net: ' . $this->waMoney($totalIn - $totalOut);
        $lines[] = 'Jumlah: ' . number_format((int)($summary['mutation_count'] ?? 0), 0, ',', '.') . ' mutasi';
        $lines[] = '';
        if (!$rows) {
            $lines[] = 'Belum ada mutasi pada tanggal ini.';
        } else {
            $lines[] = '*Detail terakhir*';
            foreach ($rows as $idx => $row) {
                $note = trim((string)($row['notes'] ?? ''));
                $ref = trim((string)($row['ref_no'] ?? ''));
                $tail = [];
                if ($ref !== '') {
                    $tail[] = 'Ref: ' . $ref;
                }
                if ($note !== '') {
                    $tail[] = $note;
                }
                $lines[] = ($idx + 1) . '. ' . (string)($row['account_code'] ?? '-') . ' ' . (string)($row['account_name'] ?? '-') . ' · ' . (string)($row['mutation_type'] ?? '-') . ' ' . $this->waMoney((float)($row['amount'] ?? 0)) . ' · Saldo ' . $this->waMoney((float)($row['balance_after'] ?? 0)) . (count($tail) ? ' · ' . implode(' · ', $tail) : '');
            }
            if ((int)($summary['mutation_count'] ?? 0) > 15) {
                $lines[] = '...dan ' . number_format(((int)$summary['mutation_count']) - 15, 0, ',', '.') . ' mutasi lain.';
            }
        }

        return ['title' => 'Mutasi Rekening ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaFinancialEstimationReport(string $command, string $date): array
    {
        if (!file_exists(APPPATH . 'models/Finance_report_model.php')) {
            return ['title' => 'Estimasi Keuangan', 'body' => 'Model estimasi keuangan belum tersedia.'];
        }

        [$year, $month] = $this->waResolveMonthYearFromCommand($command, $date);
        $this->load->model('Finance_report_model');
        $report = $this->Finance_report_model->financial_estimation_report($year, $month);
        $overview = (array)($report['overview'] ?? []);
        $rows = (array)($report['rows'] ?? []);
        $label = (string)($report['month_label'] ?? date('F Y', strtotime(sprintf('%04d-%02d-01', $year, $month))));

        $lines = [];
        $lines[] = '*Ringkasan*';
        $lines[] = 'Total Penjualan: ' . $this->waMoney2((float)($overview['total_sales'] ?? 0));
        $lines[] = 'Refund: ' . $this->waMoney2((float)($overview['total_refund'] ?? 0));
        $lines[] = 'Pengeluaran: ' . $this->waMoney2((float)($overview['total_expense'] ?? 0));
        $lines[] = 'Pendapatan Kotor: ' . $this->waMoney2((float)($overview['total_gross_profit'] ?? 0));
        $lines[] = 'Estimasi Gaji: ' . $this->waMoney2((float)($overview['total_salary'] ?? 0));
        $lines[] = '*Estimasi Profit Bersih*: ' . $this->waMoney2((float)($overview['total_final_profit'] ?? 0));
        $lines[] = 'Data absen: ' . number_format((int)($overview['attendance_days_with_data'] ?? 0), 0, ',', '.') . '/' . number_format((int)($overview['days_in_month'] ?? 0), 0, ',', '.') . ' hari';

        $activeRows = [];
        foreach ($rows as $row) {
            $hasValue = abs((float)($row['sales_total'] ?? 0)) > 0.001
                || abs((float)($row['refund_total'] ?? 0)) > 0.001
                || abs((float)($row['expense_total'] ?? 0)) > 0.001
                || abs((float)($row['salary_total'] ?? 0)) > 0.001;
            if ($hasValue) {
                $activeRows[] = $row;
            }
        }

        $lines[] = '';
        $lines[] = '*Rincian Harian*';
        if (!$activeRows) {
            $lines[] = 'Belum ada aktivitas keuangan pada periode ini.';
        } else {
            foreach ($activeRows as $idx => $row) {
                $parts = [];
                $parts[] = 'Jual ' . $this->waMoney2((float)($row['sales_total'] ?? 0));
                if (abs((float)($row['refund_total'] ?? 0)) > 0.001) {
                    $parts[] = 'Refund ' . $this->waMoney2((float)($row['refund_total'] ?? 0));
                }
                $parts[] = 'Keluar ' . $this->waMoney2((float)($row['expense_total'] ?? 0));
                $parts[] = 'Gaji ' . $this->waMoney2((float)($row['salary_total'] ?? 0));
                $parts[] = 'Final ' . $this->waMoney2((float)($row['final_profit'] ?? 0));
                $lines[] = ($idx + 1) . '. ' . $this->waDateLabel((string)($row['date'] ?? '')) . ' · ' . implode(' · ', $parts);
            }
        }

        $lines[] = '';
        $lines[] = '*Total Periode*';
        $lines[] = 'Jumlah hari beraktivitas: ' . number_format(count($activeRows), 0, ',', '.') . ' hari';
        $lines[] = 'Penjualan: ' . $this->waMoney2((float)($overview['total_sales'] ?? 0));
        $lines[] = 'Refund: ' . $this->waMoney2((float)($overview['total_refund'] ?? 0));
        $lines[] = 'Penjualan Bersih: ' . $this->waMoney2((float)($overview['total_sales'] ?? 0) - (float)($overview['total_refund'] ?? 0));
        $lines[] = 'Pengeluaran: ' . $this->waMoney2((float)($overview['total_expense'] ?? 0));
        $lines[] = 'Estimasi Gaji: ' . $this->waMoney2((float)($overview['total_salary'] ?? 0));
        $lines[] = 'Estimasi Profit Bersih: ' . $this->waMoney2((float)($overview['total_final_profit'] ?? 0));

        return ['title' => 'Estimasi Keuangan ' . $label, 'body' => implode("\n", $lines)];
    }

    private function buildWaCriticalStockReport(string $date): array
    {
        $month = date('Y-m-01', strtotime($date));
        $lines = [];

        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $rows = $this->db->query("
                SELECT COALESCE(m.material_name, i.item_name, s.profile_name, '-') AS name,
                       COALESCE(d.code, CONCAT('DIV-', s.division_id)) AS division_code,
                       s.destination_type,
                       s.closing_qty_content AS qty,
                       s.profile_content_uom_code AS uom
                FROM inv_division_monthly_stock s
                LEFT JOIN mst_operational_division d ON d.id = s.division_id
                LEFT JOIN mst_item i ON i.id = s.item_id
                LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
                WHERE s.month_key = ?
                  AND COALESCE(s.destination_type, '') <> 'OTHER'
                  AND COALESCE(s.closing_qty_content, 0) <= 0
                ORDER BY s.closing_qty_content ASC, name ASC
                LIMIT 8
            ", [$month])->result_array();
            $lines[] = '*Bahan baku divisi <= 0*';
            $lines = array_merge($lines, $this->waFormatStockRows($rows));
            $lines[] = '';
        }

        if ($this->db->table_exists('inv_warehouse_monthly_stock')) {
            $rows = $this->db->query("
                SELECT COALESCE(m.material_name, i.item_name, s.profile_name, '-') AS name,
                       'GUDANG' AS division_code,
                       '' AS destination_type,
                       s.closing_qty_content AS qty,
                       s.profile_content_uom_code AS uom
                FROM inv_warehouse_monthly_stock s
                LEFT JOIN mst_item i ON i.id = s.item_id
                LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
                WHERE s.month_key = ?
                  AND COALESCE(s.closing_qty_content, 0) <= 0
                ORDER BY s.closing_qty_content ASC, name ASC
                LIMIT 8
            ", [$month])->result_array();
            $lines[] = '*Gudang <= 0*';
            $lines = array_merge($lines, $this->waFormatStockRows($rows));
            $lines[] = '';
        }

        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $rows = $this->db->query("
                SELECT c.component_name AS name,
                       COALESCE(d.code, CONCAT('DIV-', s.division_id)) AS division_code,
                       s.location_type AS destination_type,
                       s.closing_qty AS qty,
                       u.code AS uom,
                       COALESCE(c.min_stock, 0) AS min_stock
                FROM inv_component_monthly_stock s
                JOIN mst_component c ON c.id = s.component_id
                LEFT JOIN mst_operational_division d ON d.id = s.division_id
                LEFT JOIN mst_uom u ON u.id = s.uom_id
                WHERE s.month_key = ?
                  AND c.is_active = 1
                  AND COALESCE(s.closing_qty, 0) <= COALESCE(c.min_stock, 0)
                ORDER BY s.closing_qty ASC, c.component_name ASC
                LIMIT 10
            ", [$month])->result_array();
            $lines[] = '*Base/Prepare <= min stock*';
            if (!$rows) {
                $lines[] = 'Tidak ada stok kritis.';
            } else {
                foreach ($rows as $idx => $row) {
                    $lines[] = ($idx + 1) . '. ' . (string)$row['name'] . ' · ' . (string)$row['division_code'] . '/' . (string)$row['destination_type'] . ' · stok ' . $this->waNumber((float)$row['qty']) . ' ' . (string)$row['uom'] . ' · min ' . $this->waNumber((float)$row['min_stock']);
                }
            }
        }

        return ['title' => 'Stok Kritis ' . date('F Y', strtotime($month)), 'body' => trim(implode("\n", $lines))];
    }

    private function buildWaPosPendingReport(string $date): array
    {
        if (!$this->db->table_exists('pos_order')) {
            return ['title' => 'POS Pending ' . $this->waDateLabel($date), 'body' => 'Tabel POS belum tersedia.'];
        }

        $summary = $this->db->query("
            SELECT status, stock_commit_status, COUNT(*) AS total, COALESCE(SUM(grand_total),0) AS amount
            FROM pos_order
            WHERE DATE(COALESCE(ordered_at, created_at)) = ?
              AND status NOT IN ('PAID','VOID','REFUND_FULL')
            GROUP BY status, stock_commit_status
            ORDER BY total DESC
        ", [$date])->result_array();
        $rows = $this->db->query("
            SELECT order_no, customer_name, service_type, status, stock_commit_status, grand_total, ordered_at
            FROM pos_order
            WHERE DATE(COALESCE(ordered_at, created_at)) = ?
              AND status NOT IN ('PAID','VOID','REFUND_FULL')
            ORDER BY ordered_at ASC, id ASC
            LIMIT 15
        ", [$date])->result_array();

        $lines = ['*Rekap*'];
        if (!$summary) {
            $lines[] = 'Tidak ada order POS pending.';
        } else {
            foreach ($summary as $row) {
                $lines[] = '- ' . (string)$row['status'] . ' / stok ' . (string)$row['stock_commit_status'] . ': ' . number_format((int)$row['total'], 0, ',', '.') . ' order · ' . $this->waMoney((float)$row['amount']);
            }
        }
        $lines[] = '';
        $lines[] = '*Detail*';
        $lines = array_merge($lines, $this->waFormatPosOrderRows($rows));
        return ['title' => 'POS Pending ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaCashTodayReport(string $date): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return ['title' => 'Kas Hari Ini ' . $this->waDateLabel($date), 'body' => 'Tabel mutasi rekening belum tersedia.'];
        }
        $effectiveMutationFilter = $this->effectiveAccountMutationSqlFilter('l');
        $rows = $this->db->query("
            SELECT a.account_code, a.account_name,
                   COALESCE(SUM(CASE WHEN l.mutation_type = 'IN' THEN l.amount ELSE 0 END),0) AS total_in,
                   COALESCE(SUM(CASE WHEN l.mutation_type = 'OUT' THEN l.amount ELSE 0 END),0) AS total_out,
                   COUNT(l.id) AS mutation_count
            FROM fin_company_account a
            LEFT JOIN fin_account_mutation_log l ON l.account_id = a.id AND l.mutation_date = ?
                $effectiveMutationFilter
            WHERE a.is_active = 1
            GROUP BY a.id
            HAVING mutation_count > 0 OR total_in <> 0 OR total_out <> 0
            ORDER BY a.account_code ASC
        ", [$date])->result_array();
        $totalIn = 0.0;
        $totalOut = 0.0;
        $lines = [];
        if (!$rows) {
            $lines[] = 'Belum ada mutasi kas/rekening pada tanggal ini.';
        } else {
            foreach ($rows as $idx => $row) {
                $in = (float)$row['total_in'];
                $out = (float)$row['total_out'];
                $totalIn += $in;
                $totalOut += $out;
                $lines[] = ($idx + 1) . '. ' . (string)$row['account_name'] . ' · Masuk ' . $this->waMoney($in) . ' · Keluar ' . $this->waMoney($out) . ' · Net ' . $this->waMoney($in - $out);
            }
            $lines[] = '';
            $lines[] = '*Total Masuk*: ' . $this->waMoney($totalIn);
            $lines[] = '*Total Keluar*: ' . $this->waMoney($totalOut);
            $lines[] = '*Net*: ' . $this->waMoney($totalIn - $totalOut);
        }
        return ['title' => 'Kas Hari Ini ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaRefundTodayReport(string $date): array
    {
        if (!$this->db->table_exists('pos_refund')) {
            return ['title' => 'Refund Hari Ini ' . $this->waDateLabel($date), 'body' => 'Tabel refund belum tersedia.'];
        }
        $summary = $this->db->query("
            SELECT COUNT(*) AS total_count, COALESCE(SUM(refund_amount),0) AS total_amount
            FROM pos_refund
            WHERE DATE(COALESCE(refunded_at, created_at)) = ?
              AND refund_status = 'POSTED'
        ", [$date])->row_array() ?: [];
        $rows = $this->db->query("
            SELECT r.refund_no, r.refund_amount, r.reason, r.return_to_stock, o.order_no, o.customer_name, r.refunded_at
            FROM pos_refund r
            LEFT JOIN pos_order o ON o.id = r.order_id
            WHERE DATE(COALESCE(r.refunded_at, r.created_at)) = ?
              AND r.refund_status = 'POSTED'
            ORDER BY r.refunded_at DESC, r.id DESC
            LIMIT 15
        ", [$date])->result_array();
        $lines = [
            'Total refund: ' . number_format((int)($summary['total_count'] ?? 0), 0, ',', '.') . ' transaksi',
            'Nilai refund: ' . $this->waMoney((float)($summary['total_amount'] ?? 0)),
            '',
            '*Detail*'
        ];
        if (!$rows) {
            $lines[] = 'Tidak ada refund hari ini.';
        } else {
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . (string)($row['order_no'] ?: $row['refund_no']) . ' · ' . trim((string)($row['customer_name'] ?: '-')) . ' · ' . $this->waMoney((float)$row['refund_amount']) . ' · stok ' . ((int)$row['return_to_stock'] ? 'dikembalikan' : 'tidak dikembalikan') . ' · ' . trim((string)($row['reason'] ?: '-'));
            }
        }
        return ['title' => 'Refund Hari Ini ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaVoidTodayReport(string $date): array
    {
        if (!$this->db->table_exists('pos_void')) {
            return ['title' => 'Void Hari Ini ' . $this->waDateLabel($date), 'body' => 'Tabel void belum tersedia.'];
        }
        $summary = $this->db->query("
            SELECT COUNT(*) AS total_count, COALESCE(SUM(amount_void),0) AS total_amount
            FROM pos_void
            WHERE DATE(created_at) = ?
        ", [$date])->row_array() ?: [];
        $rows = $this->db->query("
            SELECT void_no, order_no_snapshot, member_name_snapshot, void_scope, return_to_stock, amount_void, reason, created_at
            FROM pos_void
            WHERE DATE(created_at) = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 15
        ", [$date])->result_array();
        $lines = [
            'Total void: ' . number_format((int)($summary['total_count'] ?? 0), 0, ',', '.') . ' transaksi',
            'Nilai void: ' . $this->waMoney((float)($summary['total_amount'] ?? 0)),
            '',
            '*Detail*'
        ];
        if (!$rows) {
            $lines[] = 'Tidak ada void hari ini.';
        } else {
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . (string)($row['order_no_snapshot'] ?: $row['void_no']) . ' · ' . trim((string)($row['member_name_snapshot'] ?: '-')) . ' · ' . (string)$row['void_scope'] . ' · ' . $this->waMoney((float)$row['amount_void']) . ' · stok ' . ((int)$row['return_to_stock'] ? 'dikembalikan' : 'tidak dikembalikan') . ' · ' . trim((string)($row['reason'] ?: '-'));
            }
        }
        return ['title' => 'Void Hari Ini ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaTopProductReport(string $date): array
    {
        if (!$this->db->table_exists('pos_order_line')) {
            return ['title' => 'Top Produk ' . $this->waDateLabel($date), 'body' => 'Tabel penjualan produk belum tersedia.'];
        }
        $rows = $this->db->query("
            SELECT p.product_name, COALESCE(pd.name, '-') AS division_name,
                   COALESCE(SUM(l.qty),0) AS qty, COALESCE(SUM(l.net_amount),0) AS net_amount
            FROM pos_order_line l
            JOIN pos_order o ON o.id = l.order_id
            LEFT JOIN mst_product p ON p.id = l.product_id
            LEFT JOIN mst_product_division pd ON pd.id = p.product_division_id
            WHERE DATE(COALESCE(o.paid_at, o.ordered_at, o.created_at)) = ?
              AND o.status IN ('PAID','REFUND_PARTIAL')
              AND COALESCE(l.line_status, '') <> 'VOID'
              AND COALESCE(l.line_type, 'PRODUCT') <> 'BUNDLE_ITEM'
            GROUP BY p.id, p.product_name, pd.name
            ORDER BY qty DESC, net_amount DESC
            LIMIT 20
        ", [$date])->result_array();
        $lines = [];
        if (!$rows) {
            $lines[] = 'Belum ada produk terjual berstatus PAID.';
        } else {
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . (string)($row['product_name'] ?: '-') . ' · ' . (string)$row['division_name'] . ' · ' . $this->waNumber((float)$row['qty']) . ' porsi · ' . $this->waMoney((float)$row['net_amount']);
            }
        }
        return ['title' => 'Top Produk ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaMissingAttendanceReport(string $date): array
    {
        if (!$this->db->table_exists('att_daily')) {
            return ['title' => 'Absen Bolong', 'body' => 'Tabel absensi harian belum tersedia.'];
        }

        $baseSql = "
            FROM att_daily d
            JOIN org_employee e ON e.id = d.employee_id
            LEFT JOIN att_shift s ON s.id = d.shift_id
            WHERE d.attendance_date <= CURDATE()
              AND e.is_active = 1
              AND d.attendance_status NOT IN ('OFF','HOLIDAY','LEAVE','SICK')
              AND (
                (
                  d.checkin_at IS NULL
                  AND (
                    (s.id IS NOT NULL AND STR_TO_DATE(CONCAT(d.attendance_date, ' ', s.start_time), '%Y-%m-%d %H:%i:%s') <= NOW())
                    OR (s.id IS NULL AND d.attendance_date < CURDATE())
                  )
                )
                OR
                (
                  d.checkout_at IS NULL
                  AND (
                    (
                      s.id IS NOT NULL
                      AND DATE_ADD(
                        STR_TO_DATE(CONCAT(d.attendance_date, ' ', s.end_time), '%Y-%m-%d %H:%i:%s'),
                        INTERVAL COALESCE(s.is_overnight, 0) DAY
                      ) <= NOW()
                    )
                    OR (s.id IS NULL AND d.attendance_date < CURDATE())
                  )
                )
              )
        ";

        $countRow = $this->db->query("SELECT COUNT(*) AS total " . $baseSql)->row_array() ?: [];
        $rows = $this->db->query("
            SELECT e.employee_name,
                   d.attendance_date,
                   s.shift_name,
                   s.start_time,
                   s.end_time,
                   s.is_overnight,
                   d.attendance_status,
                   d.checkin_at,
                   d.checkout_at,
                   CASE
                     WHEN d.checkin_at IS NULL
                       AND (
                         (s.id IS NOT NULL AND STR_TO_DATE(CONCAT(d.attendance_date, ' ', s.start_time), '%Y-%m-%d %H:%i:%s') <= NOW())
                         OR (s.id IS NULL AND d.attendance_date < CURDATE())
                       )
                     THEN 1 ELSE 0
                   END AS missing_checkin_due,
                   CASE
                     WHEN d.checkout_at IS NULL
                       AND (
                         (
                           s.id IS NOT NULL
                           AND DATE_ADD(
                             STR_TO_DATE(CONCAT(d.attendance_date, ' ', s.end_time), '%Y-%m-%d %H:%i:%s'),
                             INTERVAL COALESCE(s.is_overnight, 0) DAY
                           ) <= NOW()
                         )
                         OR (s.id IS NULL AND d.attendance_date < CURDATE())
                       )
                     THEN 1 ELSE 0
                   END AS missing_checkout_due
            " . $baseSql . "
            ORDER BY d.attendance_date DESC, e.employee_name ASC
            LIMIT 50
        ")->result_array();
        $lines = [];
        if (!$rows) {
            $lines[] = 'Tidak ada absen masuk/pulang yang sudah melewati jadwal.';
        } else {
            $total = (int)($countRow['total'] ?? count($rows));
            $lines[] = 'Total overdue: ' . number_format($total, 0, ',', '.') . ' baris';
            $lines[] = '';
            foreach ($rows as $idx => $row) {
                $miss = [];
                if ((int)($row['missing_checkin_due'] ?? 0) === 1) {
                    $miss[] = 'check-in';
                }
                if ((int)($row['missing_checkout_due'] ?? 0) === 1) {
                    $miss[] = 'check-out';
                }
                $shiftTime = '';
                if (!empty($row['start_time']) || !empty($row['end_time'])) {
                    $shiftTime = ' (' . substr((string)($row['start_time'] ?? ''), 0, 5) . '-' . substr((string)($row['end_time'] ?? ''), 0, 5) . ')';
                }
                $lines[] = ($idx + 1) . '. ' . $this->waDateLabel((string)$row['attendance_date']) . ' · ' . (string)$row['employee_name'] . ' · ' . trim((string)($row['shift_name'] ?: '-')) . $shiftTime . ' · belum ' . implode(' & ', $miss);
            }
            if ($total > count($rows)) {
                $lines[] = '...dan ' . number_format($total - count($rows), 0, ',', '.') . ' baris lain.';
            }
        }
        return ['title' => 'Absen Bolong Overdue', 'body' => implode("\n", $lines)];
    }

    private function buildWaPendingAttendanceRequestReport(): array
    {
        if (!$this->db->table_exists('att_pending_request')) {
            return ['title' => 'Pengajuan Absen Pending', 'body' => 'Tabel pengajuan absen belum tersedia.'];
        }
        $rows = $this->db->query("
            SELECT r.request_date, r.request_type, r.reason, r.created_at, e.employee_name
            FROM att_pending_request r
            JOIN org_employee e ON e.id = r.employee_id
            WHERE r.status = 'PENDING'
            ORDER BY r.request_date ASC, r.id ASC
            LIMIT 30
        ")->result_array();
        $lines = [];
        if (!$rows) {
            $lines[] = 'Tidak ada pengajuan absen pending.';
        } else {
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . $this->waDateLabel((string)$row['request_date']) . ' · ' . (string)$row['employee_name'] . ' · ' . (string)$row['request_type'] . ' · ' . trim((string)($row['reason'] ?: '-'));
            }
        }
        return ['title' => 'Pengajuan Absen Pending', 'body' => implode("\n", $lines)];
    }

    private function buildWaStockMismatchReport(string $date): array
    {
        $month = date('Y-m-01', strtotime($date));
        $dateFrom = $month;
        $dateTo = date('Y-m-t', strtotime($month));
        $lines = [];

        $division = $this->db->query("
            SELECT COALESCE(m.material_name, i.item_name, s.profile_name, '-') AS name,
                   COALESCE(d.code, CONCAT('DIV-', s.division_id)) AS division_code,
                   s.destination_type,
                   s.closing_qty_content AS stock_qty,
                   COALESCE(l.lot_qty, 0) AS lot_qty,
                   s.total_value AS stock_value,
                   COALESCE(l.lot_value, 0) AS lot_value
            FROM inv_division_monthly_stock s
            LEFT JOIN (
                SELECT division_id, destination_type, material_id, profile_key,
                       SUM(qty_balance) AS lot_qty,
                       SUM(qty_balance * unit_cost) AS lot_value
                FROM inv_material_fifo_lot
                WHERE location_scope = 'DIVISION'
                  AND status = 'OPEN'
                  AND qty_balance > 0
                  AND receipt_date BETWEEN ? AND ?
                GROUP BY division_id, destination_type, material_id, profile_key
            ) l ON l.division_id = s.division_id
               AND l.destination_type = s.destination_type
               AND l.material_id = s.material_id
               AND l.profile_key = s.profile_key
            LEFT JOIN mst_operational_division d ON d.id = s.division_id
            LEFT JOIN mst_item i ON i.id = s.item_id
            LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
            WHERE s.month_key = ?
              AND COALESCE(s.destination_type, '') <> 'OTHER'
              AND COALESCE(s.closing_qty_content, 0) >= 0
              AND (ABS(COALESCE(s.closing_qty_content,0) - COALESCE(l.lot_qty,0)) > 0.01
                   OR ABS(COALESCE(s.total_value,0) - COALESCE(l.lot_value,0)) > 1)
            ORDER BY ABS(COALESCE(s.closing_qty_content,0) - COALESCE(l.lot_qty,0)) DESC
            LIMIT 10
        ", [$dateFrom, $dateTo, $month])->result_array();

        $component = $this->db->query("
            SELECT c.component_name AS name,
                   COALESCE(d.code, CONCAT('DIV-', s.division_id)) AS division_code,
                   s.location_type AS destination_type,
                   s.closing_qty AS stock_qty,
                   COALESCE(l.lot_qty, 0) AS lot_qty,
                   s.total_value AS stock_value,
                   COALESCE(l.lot_value, 0) AS lot_value
            FROM inv_component_monthly_stock s
            JOIN mst_component c ON c.id = s.component_id
            LEFT JOIN (
                SELECT division_id, location_type, component_id,
                       SUM(qty_balance) AS lot_qty,
                       SUM(qty_balance * unit_cost) AS lot_value
                FROM inv_component_lot
                WHERE status = 'OPEN'
                  AND qty_balance > 0
                  AND receipt_date BETWEEN ? AND ?
                GROUP BY division_id, location_type, component_id
            ) l ON l.division_id = s.division_id
               AND l.location_type = s.location_type
               AND l.component_id = s.component_id
            LEFT JOIN mst_operational_division d ON d.id = s.division_id
            WHERE s.month_key = ?
              AND c.is_active = 1
              AND COALESCE(s.closing_qty, 0) >= 0
              AND (ABS(COALESCE(s.closing_qty,0) - COALESCE(l.lot_qty,0)) > 0.01
                   OR ABS(COALESCE(s.total_value,0) - COALESCE(l.lot_value,0)) > 1)
            ORDER BY ABS(COALESCE(s.closing_qty,0) - COALESCE(l.lot_qty,0)) DESC
            LIMIT 10
        ", [$dateFrom, $dateTo, $month])->result_array();

        $lines[] = '*Bahan baku divisi*';
        $lines = array_merge($lines, $this->waFormatMismatchRows($division));
        $lines[] = '';
        $lines[] = '*Component*';
        $lines = array_merge($lines, $this->waFormatMismatchRows($component));
        return ['title' => 'Stock Mismatch ' . date('F Y', strtotime($month)), 'body' => implode("\n", $lines)];
    }

    private function buildWaStockMinusReport(string $date): array
    {
        $month = date('Y-m-01', strtotime($date));
        $queries = [
            'Gudang' => "SELECT COALESCE(m.material_name, i.item_name, s.profile_name, '-') AS name, 'GUDANG' AS division_code, '' AS destination_type, s.closing_qty_content AS qty, s.profile_content_uom_code AS uom FROM inv_warehouse_monthly_stock s LEFT JOIN mst_item i ON i.id=s.item_id LEFT JOIN mst_material m ON m.id=COALESCE(s.material_id,i.material_id) WHERE s.month_key=? AND COALESCE(s.closing_qty_content,0)<0 ORDER BY s.closing_qty_content ASC LIMIT 8",
            'Bahan baku divisi' => "SELECT COALESCE(m.material_name, i.item_name, s.profile_name, '-') AS name, COALESCE(d.code, CONCAT('DIV-',s.division_id)) AS division_code, s.destination_type, s.closing_qty_content AS qty, s.profile_content_uom_code AS uom FROM inv_division_monthly_stock s LEFT JOIN mst_operational_division d ON d.id=s.division_id LEFT JOIN mst_item i ON i.id=s.item_id LEFT JOIN mst_material m ON m.id=COALESCE(s.material_id,i.material_id) WHERE s.month_key=? AND COALESCE(s.destination_type,'')<>'OTHER' AND COALESCE(s.closing_qty_content,0)<0 ORDER BY s.closing_qty_content ASC LIMIT 8",
            'Component' => "SELECT c.component_name AS name, COALESCE(d.code, CONCAT('DIV-',s.division_id)) AS division_code, s.location_type AS destination_type, s.closing_qty AS qty, u.code AS uom FROM inv_component_monthly_stock s JOIN mst_component c ON c.id=s.component_id LEFT JOIN mst_operational_division d ON d.id=s.division_id LEFT JOIN mst_uom u ON u.id=s.uom_id WHERE s.month_key=? AND c.is_active=1 AND COALESCE(s.closing_qty,0)<0 ORDER BY s.closing_qty ASC LIMIT 8",
        ];
        $lines = [];
        foreach ($queries as $label => $sql) {
            $lines[] = '*' . $label . '*';
            $rows = $this->db->query($sql, [$month])->result_array();
            $lines = array_merge($lines, $this->waFormatStockRows($rows));
            $lines[] = '';
        }
        return ['title' => 'Stock Minus ' . date('F Y', strtotime($month)), 'body' => trim(implode("\n", $lines))];
    }

    private function buildWaBatchFailedReport(string $date): array
    {
        if (!$this->db->table_exists('inv_component_batch')) {
            return ['title' => 'Batch Gagal ' . $this->waDateLabel($date), 'body' => 'Tabel batch component belum tersedia.'];
        }
        $rows = $this->db->query("
            SELECT b.batch_no, b.batch_date, b.location_type, b.output_qty, b.status, b.notes, c.component_name
            FROM inv_component_batch b
            LEFT JOIN mst_component c ON c.id = b.component_id
            WHERE (b.batch_date = ? OR DATE(COALESCE(b.updated_at, b.created_at)) = ?)
              AND b.status = 'DRAFT'
            ORDER BY b.updated_at DESC, b.id DESC
            LIMIT 15
        ", [$date, $date])->result_array();
        $lines = ['Tabel batch tidak punya status FAILED. Berikut batch DRAFT hari ini yang belum terposting.'];
        if (!$rows) {
            $lines[] = 'Tidak ada batch draft/gagal posting yang perlu dicek.';
        } else {
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . (string)$row['batch_no'] . ' · ' . (string)$row['component_name'] . ' · ' . (string)$row['location_type'] . ' · qty ' . $this->waNumber((float)$row['output_qty']) . ' · ' . trim((string)($row['notes'] ?: '-'));
            }
        }
        return ['title' => 'Batch Gagal ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function buildWaPosQueueReport(string $date): array
    {
        if (!$this->db->table_exists('pos_stock_commit')) {
            return ['title' => 'Queue POS ' . $this->waDateLabel($date), 'body' => 'Tabel queue POS belum tersedia.'];
        }
        $summary = $this->db->query("
            SELECT commit_status, COUNT(*) AS total
            FROM pos_stock_commit
            WHERE DATE(COALESCE(created_at, updated_at)) = ?
              AND commit_status IN ('QUEUED','PROCESSING','FAILED')
            GROUP BY commit_status
        ", [$date])->result_array();
        $rows = $this->db->query("
            SELECT c.commit_no, c.commit_status, c.notes, c.updated_at, o.order_no, o.customer_name
            FROM pos_stock_commit c
            LEFT JOIN pos_order o ON o.id = c.order_id
            WHERE DATE(COALESCE(c.created_at, c.updated_at)) = ?
              AND c.commit_status IN ('QUEUED','PROCESSING','FAILED')
            ORDER BY FIELD(c.commit_status,'FAILED','PROCESSING','QUEUED'), c.updated_at DESC, c.id DESC
            LIMIT 15
        ", [$date])->result_array();
        $lines = ['*Rekap commit stock POS*'];
        if (!$summary) {
            $lines[] = 'Tidak ada queue POS pending/gagal.';
        } else {
            foreach ($summary as $row) {
                $lines[] = '- ' . (string)$row['commit_status'] . ': ' . number_format((int)$row['total'], 0, ',', '.');
            }
        }
        $lines[] = '';
        $lines[] = '*Detail*';
        if (!$rows) {
            $lines[] = '-';
        } else {
            foreach ($rows as $idx => $row) {
                $lines[] = ($idx + 1) . '. ' . (string)($row['order_no'] ?: $row['commit_no']) . ' · ' . (string)$row['commit_status'] . ' · ' . trim((string)($row['customer_name'] ?: '-')) . ' · ' . trim((string)($row['notes'] ?: '-'));
            }
        }
        return ['title' => 'Queue POS ' . $this->waDateLabel($date), 'body' => implode("\n", $lines)];
    }

    private function waFormatStockRows(array $rows): array
    {
        if (!$rows) {
            return ['Tidak ada data.'];
        }
        $lines = [];
        foreach ($rows as $idx => $row) {
            $location = trim((string)($row['division_code'] ?? '') . ' ' . (string)($row['destination_type'] ?? ''));
            $lines[] = ($idx + 1) . '. ' . (string)($row['name'] ?? '-') . ' · ' . trim($location) . ' · ' . $this->waNumber((float)($row['qty'] ?? 0)) . ' ' . trim((string)($row['uom'] ?? ''));
        }
        return $lines;
    }

    private function waFormatMismatchRows(array $rows): array
    {
        if (!$rows) {
            return ['Tidak ada mismatch non-minus.'];
        }
        $lines = [];
        foreach ($rows as $idx => $row) {
            $qtyDiff = (float)($row['stock_qty'] ?? 0) - (float)($row['lot_qty'] ?? 0);
            $valueDiff = (float)($row['stock_value'] ?? 0) - (float)($row['lot_value'] ?? 0);
            $lines[] = ($idx + 1) . '. ' . (string)($row['name'] ?? '-') . ' · ' . (string)($row['division_code'] ?? '-') . '/' . (string)($row['destination_type'] ?? '-') . ' · stok ' . $this->waNumber((float)($row['stock_qty'] ?? 0)) . ' · lot ' . $this->waNumber((float)($row['lot_qty'] ?? 0)) . ' · selisih ' . $this->waNumber($qtyDiff) . ' · nilai ' . $this->waMoney($valueDiff);
        }
        return $lines;
    }

    private function waFormatPosOrderRows(array $rows): array
    {
        if (!$rows) {
            return ['Tidak ada data.'];
        }
        $lines = [];
        foreach ($rows as $idx => $row) {
            $lines[] = ($idx + 1) . '. ' . (string)($row['order_no'] ?? '-') . ' · ' . trim((string)($row['customer_name'] ?: '-')) . ' · ' . (string)($row['service_type'] ?? '-') . ' · ' . (string)($row['status'] ?? '-') . ' · stok ' . (string)($row['stock_commit_status'] ?? '-') . ' · ' . $this->waMoney((float)($row['grand_total'] ?? 0));
        }
        return $lines;
    }

    private function dispatchWaMutationCommand(string $raw, string $command, array $group, array $context): string
    {
        $sender = (string)($context['sender_jid'] ?? '');
        $messageId = (string)($context['message_id'] ?? '');
        if (($context['sender_is_admin'] ?? false) !== true
            || !preg_match('/\A[0-9]+@(s\.whatsapp\.net|lid)\z/D', $sender)) {
            return 'Input mutasi hanya diizinkan untuk admin grup WhatsApp yang terdaftar aktif. Ketik mutasi bantuan untuk formatnya.';
        }
        $timestamp = (int)($context['message_timestamp'] ?? 0);
        if (!preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $messageId) || $timestamp < time() - 900 || $timestamp > time() + 60) {
            return 'Identitas/waktu pesan mutasi tidak valid atau sudah kedaluwarsa. Kirim perintah baru; riwayat pesan tidak diposting otomatis.';
        }
        if (!$this->db->field_exists('client_request_key', 'fin_account_mutation_log')) {
            return 'Pengaman duplikasi mutasi belum tersedia. Jalankan migrasi 2026-09-13a melalui Finance.';
        }
        $key = substr(hash('sha256', 'wa|' . $group['group_jid'] . '|' . $messageId), 0, 32);
        $lockName = 'wa-mutation-' . $key;
        $lock = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', [$lockName]);
        if (!$lock || (int)($lock->row_array()['acquired'] ?? 0) !== 1) {
            return 'Pesan mutasi ini sedang diproses. Periksa laporan mutasi sebelum mengirim perintah baru.';
        }
        try {
            // One incoming message may create two transfer rows, but only one posting.
            $previous = $this->db->get_where('fin_account_mutation_log', ['client_request_key' => $key], 1);
            if (!$previous) {
                return 'Riwayat pesan mutasi belum dapat diperiksa. Tidak ada mutasi baru yang diposting.';
            }
            if ($previous->row_array()) {
                return 'Pesan mutasi ini sudah diposting sebelumnya; saldo tidak diubah lagi.';
            }
            $context['client_request_key'] = $key;
            return $this->handleWaMutationInputCommand($raw, $command, $group, $context);
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    private function handleWaMutationInputCommand(string $rawCommand, string $normalizedCommand, array $group, array $context): string
    {
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return 'Tabel rekening/mutasi belum tersedia.';
        }

        $mode = '';
        if (preg_match('/^mutasi\s+(in|out|transfer)\b/i', $normalizedCommand, $m)) {
            $mode = strtoupper((string)$m[1]);
        }
        if (!in_array($mode, ['IN', 'OUT', 'TRANSFER'], true)) {
            return $this->waMutationCommandHelp();
        }

        $fields = $this->parseWaKeyValueCommand($rawCommand);
        // Allow an explicit category suffix with the existing compact format.
        $compactCommand = preg_replace('/\s+\b(?:kategori|category)\s*:.*$/iu', '', $rawCommand);
        $keyValueAccounts = isset($fields['rekening']) || isset($fields['akun']) || isset($fields['dari']) || isset($fields['ke']);
        $compact = $keyValueAccounts ? [] : $this->parseWaCompactMutationCommand($compactCommand, $mode);
        if (!empty($compact['error'])) {
            return $compact['error'];
        }
        $amount = $this->parseWaMoneyValue((string)($fields['nominal'] ?? $fields['jumlah'] ?? ($compact['amount_text'] ?? '')));
        $date = $this->normalizeWaDateValue((string)($fields['tanggal'] ?? ($compact['date'] ?? '')), date('Y-m-d', (int)$context['message_timestamp']));
        $referenceNo = trim((string)($fields['ref'] ?? ''));
        $notes = trim((string)($fields['catatan'] ?? $fields['note'] ?? ($compact['notes'] ?? '')));

        if ($amount <= 0) {
            return "Nominal wajib lebih dari 0.\n\n" . $this->waMutationCommandHelp();
        }
        if ($date === '') {
            return "Tanggal tidak valid.\n\n" . $this->waMutationCommandHelp();
        }
        if ($notes === '') {
            return "Catatan wajib diisi agar mutasi via WA bisa diaudit.\n\n" . $this->waMutationCommandHelp();
        }

        require_once APPPATH . 'libraries/Finance_mutation_policy.php';
        $category = strtoupper(trim((string)($fields['kategori'] ?? $fields['category'] ?? '')));
        if ($mode !== 'TRANSFER' && !Finance_mutation_policy::valid($category, $mode)) {
            return "Kategori laporan wajib diisi sesuai arah mutasi; belum ada saldo yang diubah.\n\n" . $this->waMutationCommandHelp();
        }
        if ($mode !== 'TRANSFER' && in_array($category, ['PROMO_EXPENSE', 'PLATFORM_FEE'], true)) {
            return 'Biaya promo/platform harus diinput melalui Finance dengan rincian settlement agar tidak terpotong dua kali.';
        }

        $payload = [
            'mutation_type' => $mode,
            'mutation_date' => $date,
            'amount' => $amount,
            'report_category' => $category,
            'client_request_key' => $context['client_request_key'],
            'reference_no' => $referenceNo !== '' ? $referenceNo : null,
            'notes' => 'Via WA ' . trim((string)($group['group_name'] ?? 'Grup'))
                . ' [' . $context['sender_jid'] . '; pesan ' . $context['message_id'] . ']: ' . $notes,
        ];

        if ($mode === 'TRANSFER') {
            $from = !empty($compact['from_account'])
                ? ['ok' => true, 'account' => $compact['from_account']]
                : $this->resolveWaFinanceAccount((string)($fields['dari'] ?? ''));
            $to = !empty($compact['to_account'])
                ? ['ok' => true, 'account' => $compact['to_account']]
                : $this->resolveWaFinanceAccount((string)($fields['ke'] ?? ''));
            if (!$from['ok']) {
                return 'Rekening sumber tidak valid: ' . $from['message'];
            }
            if (!$to['ok']) {
                return 'Rekening tujuan tidak valid: ' . $to['message'];
            }
            $payload['account_id'] = (int)$from['account']['id'];
            $payload['to_account_id'] = (int)$to['account']['id'];
        } else {
            $account = !empty($compact['account'])
                ? ['ok' => true, 'account' => $compact['account']]
                : $this->resolveWaFinanceAccount((string)($fields['rekening'] ?? $fields['akun'] ?? ''));
            if (!$account['ok']) {
                return 'Rekening tidak valid: ' . $account['message'];
            }
            $payload['account_id'] = (int)$account['account']['id'];
        }

        $this->load->model('Purchase_model');
        $result = $this->Purchase_model->apply_manual_account_mutation($payload, 0, (string)$this->input->ip_address());
        if (empty($result['ok'])) {
            return 'Gagal posting mutasi: ' . (string)($result['message'] ?? 'Tidak diketahui.');
        }
        if (!empty($result['replayed'])) {
            return 'Pesan mutasi ini sudah diposting sebelumnya; saldo tidak diubah lagi.';
        }

        if ($mode === 'TRANSFER') {
            $fromLabel = $this->waAccountLabel($from['account']);
            $toLabel = $this->waAccountLabel($to['account']);
            return "Mutasi transfer berhasil diposting.\nDari: {$fromLabel}\nKe: {$toLabel}\nNominal: Rp " . number_format($amount, 2, ',', '.') . "\nTanggal: {$date}";
        }

        $accountLabel = $this->waAccountLabel($account['account']);
        return "Mutasi {$mode} berhasil diposting.\nRekening: {$accountLabel}\nNominal: Rp " . number_format($amount, 2, ',', '.') . "\nTanggal: {$date}";
    }

    private function waMutationCommandHelp(): string
    {
        return "Format input mutasi (admin grup terdaftar aktif):\n"
            . "- mutasi in TUNAI 50000 setoran owner kategori:OWNER_CAPITAL\n"
            . "- mutasi out TUNAI 25000 beli bensin kategori:OPERATING_EXPENSE\n"
            . "- mutasi transfer TUNAI MANDIRI 100000 setor bank\n"
            . "- mutasi transfer TUNAI MANDIRI 100000 setor bank 2026-08-16\n"
            . "Format kolom: mutasi in rekening:TUNAI nominal:50000 kategori:OWNER_CAPITAL catatan:setoran owner\n"
            . "Kategori IN: OTHER_INCOME, OWNER_CAPITAL, CASH_SURPLUS, BALANCE_CORRECTION.\n"
            . "Kategori OUT: OPERATING_EXPENSE, OWNER_DRAWING, CASH_SHORTAGE, BALANCE_CORRECTION.\n"
            . "Gunakan nama/kode rekening aktif (lihat saldo). TRANSFER tidak perlu kategori. Catatan wajib; tanggal opsional di akhir catatan.\n"
            . "Perintah langsung memposting. Pesan yang sama tidak diposting dua kali; periksa mutasi sebelum mengirim pesan baru.";
    }

    private function parseWaKeyValueCommand(string $command): array
    {
        $fields = [];
        if (preg_match_all('/\b(rekening|akun|dari|ke|nominal|jumlah|tanggal|ref|catatan|note|kategori|category)\s*:\s*(.*?)(?=\s+\b(?:rekening|akun|dari|ke|nominal|jumlah|tanggal|ref|catatan|note|kategori|category)\s*:|$)/iu', $command, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fields[strtolower((string)$match[1])] = trim((string)$match[2]);
            }
        }
        return $fields;
    }

    private function parseWaCompactMutationCommand(string $command, string $mode): array
    {
        $body = trim(preg_replace('/^[!\/.#]*\s*(?:input\s+)?mutasi\s+(in|out|transfer)\b/i', '', $command));
        if ($body === '') {
            return [];
        }

        $date = '';
        if (preg_match('/\s(\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\s*$/', $body, $m)) {
            $date = (string)$m[1];
            $body = trim(substr($body, 0, -strlen((string)$m[1])));
        }

        if ($mode === 'TRANSFER') {
            $from = $this->matchWaAccountPrefix($body);
            if (!$from['ok']) {
                return ['error' => 'Rekening sumber tidak valid: ' . $from['message']];
            }
            $rest = trim((string)$from['remaining']);
            $to = $this->matchWaAccountPrefix($rest);
            if (!$to['ok']) {
                return ['error' => 'Rekening tujuan tidak valid: ' . $to['message']];
            }
            $rest = trim((string)$to['remaining']);
            $money = $this->extractWaMoneyAndNotes($rest);
            return [
                'from_account' => $from['account'],
                'to_account' => $to['account'],
                'amount_text' => $money['amount_text'] ?? '',
                'notes' => $money['notes'] ?? '',
                'date' => $date,
            ];
        }

        $account = $this->matchWaAccountPrefix($body);
        if (!$account['ok']) {
            return ['error' => 'Rekening tidak valid: ' . $account['message']];
        }
        $money = $this->extractWaMoneyAndNotes(trim((string)$account['remaining']));
        return [
            'account' => $account['account'],
            'amount_text' => $money['amount_text'] ?? '',
            'notes' => $money['notes'] ?? '',
            'date' => $date,
        ];
    }

    private function matchWaAccountPrefix(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'message' => 'rekening wajib diisi.'];
        }

        $accounts = $this->db
            ->select('id, account_code, account_name, account_type, current_balance')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->get()
            ->result_array();

        $candidates = [];
        foreach ($accounts as $account) {
            foreach ([(string)($account['account_name'] ?? ''), (string)($account['account_code'] ?? '')] as $label) {
                $label = trim($label);
                if ($label === '') {
                    continue;
                }
                $candidates[] = [
                    'label' => $label,
                    'account' => $account,
                    'length' => strlen($label),
                ];
            }
        }
        usort($candidates, static function ($a, $b) {
            return ($b['length'] <=> $a['length']);
        });

        foreach ($candidates as $candidate) {
            $pattern = '/^' . preg_quote((string)$candidate['label'], '/') . '(?=\s|$)/iu';
            if (preg_match($pattern, $text, $m)) {
                return [
                    'ok' => true,
                    'account' => $candidate['account'],
                    'remaining' => trim(substr($text, strlen((string)$m[0]))),
                ];
            }
        }

        return ['ok' => false, 'message' => 'rekening tidak ditemukan atau tidak aktif.'];
    }

    private function extractWaMoneyAndNotes(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['amount_text' => '', 'notes' => ''];
        }
        if (!preg_match('/^(?:rp\s*)?(-?[0-9][0-9.,]*)(?=\s|$)\s*(.*)$/iu', $text, $m)) {
            return ['amount_text' => '', 'notes' => $text];
        }

        return [
            'amount_text' => (string)$m[1],
            'notes' => trim((string)$m[2]),
        ];
    }

    private function parseWaMoneyValue(string $value): float
    {
        $value = strtolower(trim($value));
        $value = str_replace(['rp', ' '], '', $value);
        if (!preg_match('/\A-?(?:[0-9]+(?:[.,][0-9]{1,2})?|[0-9]{1,3}(?:\.[0-9]{3})+(?:,[0-9]{1,2})?|[0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?)\z/D', $value)) {
            return 0.0;
        }
        if (strpos($value, ',') !== false) {
            if (preg_match('/\A-?[0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?\z/D', $value)) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
        } else {
            $parts = explode('.', $value);
            if (count($parts) > 2 || (count($parts) === 2 && strlen(end($parts)) === 3)) {
                $value = str_replace('.', '', $value);
            }
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        return round((float)$value, 2);
    }

    private function normalizeWaDateValue(string $value, string $fallbackDate): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'hari ini' || $value === 'today') {
            return $fallbackDate;
        }
        if ($value === 'kemarin' || $value === 'yesterday') {
            return date('Y-m-d', strtotime('-1 day'));
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $value : '';
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
            return checkdate((int)$m[2], (int)$m[1], (int)$m[3])
                ? sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]) : '';
        }
        return '';
    }

    private function resolveWaFinanceAccount(string $needle): array
    {
        $needle = trim($needle);
        if ($needle === '') {
            return ['ok' => false, 'message' => 'rekening wajib diisi.'];
        }

        $this->db->select('id, account_code, account_name, account_type, current_balance')
            ->from('fin_company_account')
            ->where('is_active', 1);
        if (ctype_digit($needle)) {
            $this->db->group_start()
                ->where('id', (int)$needle)
                ->or_where('account_code', $needle)
                ->group_end();
        } else {
            $this->db->group_start()
                ->where('LOWER(account_code)', strtolower($needle))
                ->or_like('LOWER(account_name)', strtolower($needle), 'both', false)
                ->group_end();
        }
        $rows = $this->db->order_by('account_code', 'ASC')->limit(5)->get()->result_array();
        if (!$rows) {
            return ['ok' => false, 'message' => 'rekening tidak ditemukan atau tidak aktif.'];
        }
        if (count($rows) > 1) {
            $labels = [];
            foreach ($rows as $row) {
                $labels[] = $this->waAccountLabel($row);
            }
            return ['ok' => false, 'message' => 'hasil lebih dari satu. Pakai kode rekening: ' . implode('; ', $labels)];
        }
        return ['ok' => true, 'account' => $rows[0]];
    }

    private function waAccountLabel(array $account): string
    {
        return trim((string)($account['account_code'] ?? '-') . ' - ' . (string)($account['account_name'] ?? '-'));
    }

    private function runDueWaReportSchedules(): array
    {
        if (!$this->db->table_exists('wa_report_schedule')) {
            return ['ok' => false, 'message' => 'Tabel wa_report_schedule belum tersedia.'];
        }

        // A missing report migration must not abort the separate order notification queue.
        foreach (['run_claim_token', 'run_claimed_at'] as $column) {
            if (!$this->db->field_exists($column, 'wa_report_schedule')) {
                log_message('error', 'WA report worker requires 2026-09-02a_wa_report_schedule_claim_lease.sql');
                return ['ok' => false, 'message' => 'Migrasi pengunci jadwal WA belum diterapkan.'];
            }
        }

        $today = date('Y-m-d');
        $nowTime = date('H:i:s');
        $retryAfter = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $leaseExpiredAt = $retryAfter;
        $rows = $this->db->from('wa_report_schedule')
            ->where('is_active', 1)
            ->where('send_time <=', $nowTime)
            ->group_start()
                ->where('last_sent_date IS NULL', null, false)
                ->or_where('last_sent_date <', $today)
            ->group_end()
            ->group_start()
                ->where('last_run_at IS NULL', null, false)
                ->or_where('last_run_at <', $retryAfter)
            ->group_end()
            ->group_start()
                ->where('run_claim_token IS NULL', null, false)
                ->or_where('run_claim_token', '')
                ->or_where('run_claimed_at IS NULL', null, false)
                ->or_where('run_claimed_at <', $leaseExpiredAt)
            ->group_end()
            ->order_by('send_time', 'ASC')
            ->limit(20)
            ->get()
            ->result_array();

        $sent = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $row) {
            $claimToken = $this->claimDueWaReportSchedule(
                (int)$row['id'],
                $today,
                $nowTime,
                $retryAfter,
                $leaseExpiredAt
            );
            if ($claimToken === null) {
                continue;
            }

            $row['run_claim_token'] = $claimToken;
            $row['run_claimed_at'] = date('Y-m-d H:i:s');
            $result = $this->sendWaReportSchedule((int)$row['id'], false, $row, $claimToken);
            if (!empty($result['ok'])) {
                $sent++;
            } else {
                $failed++;
                $errors[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)($row['name'] ?? ''),
                    'message' => (string)($result['message'] ?? 'Gagal'),
                ];
            }
        }

        return [
            'ok' => $failed === 0,
            'checked' => count($rows),
            'sent' => $sent,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 5),
        ];
    }

    private function claimDueWaReportSchedule(
        int $scheduleId,
        string $today,
        string $nowTime,
        string $retryAfter,
        string $leaseExpiredAt
    ): ?string {
        if ($scheduleId <= 0) {
            return null;
        }

        try {
            $claimToken = bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            return null;
        }
        $claimedAt = date('Y-m-d H:i:s');

        $this->db->set([
            'run_claim_token' => $claimToken,
            'run_claimed_at' => $claimedAt,
        ])->where('id', $scheduleId)
            ->where('is_active', 1)
            ->where('send_time <=', $nowTime)
            ->group_start()
                ->where('last_sent_date IS NULL', null, false)
                ->or_where('last_sent_date <', $today)
            ->group_end()
            ->group_start()
                ->where('last_run_at IS NULL', null, false)
                ->or_where('last_run_at <', $retryAfter)
            ->group_end()
            ->group_start()
                ->where('run_claim_token IS NULL', null, false)
                ->or_where('run_claim_token', '')
                ->or_where('run_claimed_at IS NULL', null, false)
                ->or_where('run_claimed_at <', $leaseExpiredAt)
            ->group_end()
            ->update('wa_report_schedule');

        return $this->db->affected_rows() === 1 ? $claimToken : null;
    }

    private function sendWaReportSchedule(
        int $scheduleId,
        bool $manual = false,
        ?array $schedule = null,
        ?string $claimToken = null
    ): array
    {
        if (!$this->db->table_exists('wa_report_schedule')) {
            return ['ok' => false, 'message' => 'Tabel jadwal laporan belum tersedia.'];
        }

        if (!$schedule) {
            $schedule = $this->db->from('wa_report_schedule')->where('id', $scheduleId)->limit(1)->get()->row_array();
        }
        if (!$schedule) {
            return ['ok' => false, 'message' => 'Jadwal laporan tidak ditemukan.'];
        }

        $group = $this->db->from('wa_group_map')
            ->where('id', (int)($schedule['group_id'] ?? 0))
            ->limit(1)
            ->get()
            ->row_array();
        if (!$group || !(int)($group['is_active'] ?? 0) || trim((string)($group['group_jid'] ?? '')) === '') {
            $finalized = $this->markWaReportScheduleFailed($scheduleId, 'Grup tujuan belum aktif atau JID grup kosong.', $claimToken);
            if ($claimToken !== null && !$finalized) {
                return ['ok' => false, 'message' => 'Lease jadwal sudah berpindah; hasil runner lama tidak difinalisasi.'];
            }
            return ['ok' => false, 'message' => 'Grup tujuan belum aktif atau JID grup kosong.'];
        }

        $template = $this->db->from('wa_template')
            ->where('id', (int)($schedule['template_id'] ?? 0))
            ->limit(1)
            ->get()
            ->row_array();
        if (!$template || !(int)($template['is_active'] ?? 0)) {
            $finalized = $this->markWaReportScheduleFailed($scheduleId, 'Template laporan tidak aktif atau tidak ditemukan.', $claimToken);
            if ($claimToken !== null && !$finalized) {
                return ['ok' => false, 'message' => 'Lease jadwal sudah berpindah; hasil runner lama tidak difinalisasi.'];
            }
            return ['ok' => false, 'message' => 'Template laporan tidak aktif atau tidak ditemukan.'];
        }

        $date = date('Y-m-d', strtotime(((int)($schedule['date_offset_days'] ?? 0)) . ' days'));
        $report = $this->buildWaDynamicReport((string)($schedule['report_type'] ?? ''), $date);
        $message = $this->renderWaTemplate((string)($template['body'] ?? ''), [
            'nama_jadwal' => (string)($schedule['name'] ?? ''),
            'nama_grup' => (string)($group['group_name'] ?? ''),
            'tanggal' => $this->waDateLabel($date),
            'tanggal_iso' => $date,
            'report_type' => (string)($schedule['report_type'] ?? ''),
            'report_title' => (string)($report['title'] ?? ''),
            'report_body' => (string)($report['body'] ?? ''),
            'generated_at' => date('d/m/Y H:i'),
        ]);

        if (trim($message) === '') {
            $message = (string)($report['title'] ?? 'Laporan WA') . "\n" . (string)($report['body'] ?? '');
        }

        $result = $this->callBotApi('/internal/send-group', 'POST', [
            'group_jid' => $group['group_jid'],
            'message' => $message,
        ]);

        $ok = !empty($result['ok']);
        $this->logSend(null, 'SCHEDULED', null, (string)$group['group_jid'], (string)$group['group_name'], $message, $ok ? 'SENT' : 'FAILED', (string)($result['message'] ?? ''));
        $finalPayload = [
            'last_run_at' => date('Y-m-d H:i:s'),
            'last_sent_at' => $ok ? date('Y-m-d H:i:s') : ($schedule['last_sent_at'] ?? null),
            'last_sent_date' => $ok ? date('Y-m-d') : ($schedule['last_sent_date'] ?? null),
            'last_status' => $ok ? 'SENT' : 'FAILED',
            'last_error' => $ok ? null : mb_substr((string)($result['message'] ?? 'Gagal kirim'), 0, 500, 'UTF-8'),
        ];
        $finalQuery = $this->db->where('id', $scheduleId);
        if ($claimToken !== null) {
            $finalQuery->where('run_claim_token', $claimToken);
            $finalPayload['run_claim_token'] = null;
            $finalPayload['run_claimed_at'] = null;
        }
        $finalQuery->update('wa_report_schedule', $finalPayload);
        if ($claimToken !== null && $this->db->affected_rows() !== 1) {
            return ['ok' => false, 'message' => 'Lease jadwal sudah berpindah; hasil runner lama tidak difinalisasi.'];
        }

        return [
            'ok' => $ok,
            'message' => $ok
                ? ($manual ? 'Laporan berhasil dikirim ke grup.' : 'Laporan terjadwal terkirim.')
                : ('Gagal kirim laporan: ' . (string)($result['message'] ?? 'WA Bot error')),
        ];
    }

    private function markWaReportScheduleFailed(int $scheduleId, string $message, ?string $claimToken = null): bool
    {
        if ($scheduleId <= 0 || !$this->db->table_exists('wa_report_schedule')) {
            return false;
        }
        $payload = [
            'last_run_at' => date('Y-m-d H:i:s'),
            'last_status' => 'FAILED',
            'last_error' => mb_substr($message, 0, 500, 'UTF-8'),
        ];
        $query = $this->db->where('id', $scheduleId);
        if ($claimToken !== null) {
            $query->where('run_claim_token', $claimToken);
            $payload['run_claim_token'] = null;
            $payload['run_claimed_at'] = null;
        }
        $query->update('wa_report_schedule', $payload);

        return $claimToken === null || $this->db->affected_rows() === 1;
    }

    private function renderWaTemplate(string $template, array $vars): string
    {
        $replace = [];
        foreach ($vars as $key => $value) {
            $replace['{{' . $key . '}}'] = (string)$value;
            $replace['{{' . strtoupper($key) . '}}'] = (string)$value;
        }
        return strtr($template, $replace);
    }

    private function buildWaDynamicReport(string $type, string $date): array
    {
        switch (strtoupper($type)) {
            case 'OMZET_TODAY':
                return $this->buildWaOmzetReport($date);
            case 'PURCHASE_TODAY':
                return $this->buildWaPurchaseReport($date);
            case 'ADJUSTMENT_TODAY':
                return $this->buildWaAdjustmentReport($date);
            case 'PO_SR_TODAY':
                return $this->buildWaPoSrReport($date);
            default:
                return ['title' => 'Laporan WA', 'body' => 'Tipe laporan belum dikenali.'];
        }
    }

    private function buildWaOmzetReport(string $date): array
    {
        if (!$this->db->table_exists('pos_order')) {
            return ['title' => 'Omzet ' . $this->waDateLabel($date), 'body' => 'Data POS belum tersedia.'];
        }

        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';
        $summary = $this->db
            ->select('COUNT(*) AS order_count', false)
            ->select("SUM(CASE WHEN status IN ('PAID','REFUND_PARTIAL') THEN 1 ELSE 0 END) AS paid_order_count", false)
            ->select("SUM(CASE WHEN status IN ('PENDING','CONFIRMED','PAID_PARTIAL','IN_KITCHEN','READY','SERVED') THEN 1 ELSE 0 END) AS unpaid_order_count", false)
            ->select("COALESCE(SUM(CASE WHEN status IN ('PAID','REFUND_PARTIAL') THEN grand_total ELSE 0 END),0) AS paid_total", false)
            ->select("COALESCE(SUM(CASE WHEN status IN ('PENDING','CONFIRMED','PAID_PARTIAL','IN_KITCHEN','READY','SERVED') THEN grand_total ELSE 0 END),0) AS unpaid_total", false)
            ->select("COALESCE(SUM(CASE WHEN status NOT IN ('DRAFT','VOID','REFUND_FULL') THEN grand_total ELSE 0 END),0) AS omzet_total", false)
            ->from('pos_order')
            ->where('ordered_at >=', $dateStart)
            ->where('ordered_at <=', $dateEnd)
            ->where_not_in('status', ['DRAFT', 'VOID', 'REFUND_FULL'])
            ->get()
            ->row_array() ?: [];

        $refund = ['payment_count' => 0, 'total' => 0];
        if ($this->db->table_exists('pos_payment')) {
            $refund = $this->db->select('COUNT(*) AS payment_count, COALESCE(SUM(ABS(net_amount)),0) AS total', false)
                ->from('pos_payment')
                ->where('payment_status', 'PAID')
                ->where('payment_type', 'REFUND')
                ->where('paid_at >=', $dateStart)
                ->where('paid_at <=', $dateEnd)
                ->get()
                ->row_array() ?: [];
        }
        $refundTotal = (float)($refund['total'] ?? 0);
        $paidTotal = (float)($summary['paid_total'] ?? 0);

        $lines = [];
        $lines[] = '*Omzet ' . $this->waDateLabel($date) . '*';
        $lines[] = 'Total Bersih: ' . $this->waMoney(max(0, $paidTotal - $refundTotal));
        $lines[] = 'Total Omzet: ' . $this->waMoney((float)($summary['omzet_total'] ?? 0));
        $lines[] = 'Order: ' . number_format((int)($summary['order_count'] ?? 0), 0, ',', '.');
        $lines[] = 'PAID: ' . $this->waMoney($paidTotal) . ' / ' . number_format((int)($summary['paid_order_count'] ?? 0), 0, ',', '.') . ' order';
        $lines[] = 'Belum PAID: ' . $this->waMoney((float)($summary['unpaid_total'] ?? 0)) . ' / ' . number_format((int)($summary['unpaid_order_count'] ?? 0), 0, ',', '.') . ' order';
        $lines[] = 'Refund: ' . $this->waMoney($refundTotal) . ' / ' . number_format((int)($refund['payment_count'] ?? 0), 0, ',', '.') . ' transaksi';

        if ($this->db->table_exists('pos_payment_line') && $this->db->table_exists('pos_payment_method')) {
            $rows = $this->db->select('COALESCE(pm.method_name, "Tanpa metode") AS method_name, COUNT(*) AS line_count, COALESCE(SUM(pl.amount),0) AS total', false)
                ->from('pos_payment_line pl')
                ->join('pos_payment p', 'p.id = pl.payment_id', 'inner')
                ->join('pos_order o', 'o.id = p.order_id', 'inner')
                ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
                ->where('p.payment_status', 'PAID')
                ->where('p.payment_type !=', 'REFUND')
                ->where('pl.status', 'PAID')
                ->where('o.ordered_at >=', $dateStart)
                ->where('o.ordered_at <=', $dateEnd)
                ->group_by('pm.method_name')
                ->order_by('total', 'DESC')
                ->limit(8)
                ->get()
                ->result_array();
            if ($rows) {
                $lines[] = '';
                $lines[] = 'PAID per metode:';
                foreach ($rows as $row) {
                    $lines[] = '- ' . (string)$row['method_name'] . ': ' . $this->waMoney((float)$row['total']);
                }
            }
        }

        return ['title' => 'Omzet hari ini', 'body' => implode("\n", $lines)];
    }

    private function buildWaPurchaseReport(string $date): array
    {
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return ['title' => 'Belanja ' . $this->waDateLabel($date), 'body' => 'Data purchase order belum tersedia.'];
        }

        $summary = $this->db->select('COUNT(*) AS doc_count, COALESCE(SUM(grand_total),0) AS total', false)
            ->from('pur_purchase_order')
            ->where('request_date', $date)
            ->where('status !=', 'VOID')
            ->get()
            ->row_array() ?: [];

        $lines = [];
        $lines[] = '*Rincian belanja ' . $this->waDateLabel($date) . '*';
        $lines[] = 'PO tercatat: ' . $this->waMoney2((float)($summary['total'] ?? 0)) . ' | PO: ' . number_format((int)($summary['doc_count'] ?? 0), 0, ',', '.');

        $rows = $this->db
            ->select('po.po_no, po.status, po.grand_total, po.purchase_type_id')
            ->select('COALESCE(pt.type_name, "Lainnya") AS type_name, COALESCE(pt.sort_order, 999999) AS type_sort', false)
            ->select('COALESCE(a.account_name, a.account_code, "") AS payment_account_name', false)
            ->select('l.line_no, l.qty_buy, l.content_per_buy, l.qty_content, l.line_subtotal')
            ->select('COALESCE(l.snapshot_item_name, i.item_name, l.snapshot_material_name, m.material_name, l.snapshot_line_description, "Item") AS line_name', false)
            ->select('COALESCE(l.snapshot_brand_name, l.brand_name, "") AS brand_name', false)
            ->select('COALESCE(l.snapshot_buy_uom_code, bu.code, "") AS buy_uom_code', false)
            ->select('COALESCE(l.snapshot_content_uom_code, cu.code, "") AS content_uom_code', false)
            ->from('pur_purchase_order_line l')
            ->join('pur_purchase_order po', 'po.id = l.purchase_order_id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('fin_company_account a', 'a.id = po.payment_account_id', 'left')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->join('mst_uom bu', 'bu.id = l.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = l.content_uom_id', 'left')
            ->where('po.request_date', $date)
            ->where('po.status !=', 'VOID')
            ->order_by('type_sort', 'ASC', false)
            ->order_by('pt.type_name', 'ASC')
            ->order_by('po.id', 'ASC')
            ->order_by('l.line_no', 'ASC')
            ->limit(200)
            ->get()
            ->result_array();

        $grouped = [];
        $typeTotals = [];
        $paymentTotals = [];
        $unpaidTotals = [];
        $paidPoTotal = 0.0;
        foreach ($rows as $row) {
            $typeName = trim((string)($row['type_name'] ?? 'Lainnya')) ?: 'Lainnya';
            $grouped[$typeName][] = $row;
            $typeTotals[$typeName] = ($typeTotals[$typeName] ?? 0) + (float)($row['line_subtotal'] ?? 0);

            $status = strtoupper((string)($row['status'] ?? ''));
            if ($status === 'PAID') {
                $paidPoTotal += (float)($row['line_subtotal'] ?? 0);
                $method = trim((string)($row['payment_account_name'] ?? '')) ?: 'Tanpa rekening';
                $paymentTotals[$method] = ($paymentTotals[$method] ?? 0) + (float)($row['line_subtotal'] ?? 0);
            } else {
                $unpaidTotals[$status ?: 'BELUM PAID'] = ($unpaidTotals[$status ?: 'BELUM PAID'] ?? 0) + (float)($row['line_subtotal'] ?? 0);
            }
        }

        $cashOutRows = [];
        $otherCashOutTotal = 0.0;
        if ($this->db->table_exists('fin_account_mutation_log')) {
            $cashOutQuery = $this->db
                ->select('ml.mutation_no, ml.amount, ml.ref_module, ml.ref_table, ml.ref_no, ml.notes')
                ->select('COALESCE(a.account_name, a.account_code, "Tanpa rekening") AS account_name', false)
                ->from('fin_account_mutation_log ml')
                ->join('fin_company_account a', 'a.id = ml.account_id', 'left')
                ->where('ml.mutation_date', $date)
                ->where('ml.mutation_type', 'OUT')
                ->where('ml.ref_module IS NOT NULL', null, false)
                ->where_not_in('ml.ref_module', ['PURCHASE', 'FINANCE', 'FINANCE_TRANSFER', 'POS'])
                ->order_by('ml.ref_module', 'ASC')
                ->order_by('ml.id', 'ASC');
            $this->applyEffectiveAccountMutationFilter($cashOutQuery, 'ml');
            $cashOutRows = $cashOutQuery
                ->limit(100)
                ->get()
                ->result_array();
            foreach ($cashOutRows as $row) {
                $amount = (float)($row['amount'] ?? 0);
                $otherCashOutTotal += $amount;
                $method = trim((string)($row['account_name'] ?? 'Tanpa rekening')) ?: 'Tanpa rekening';
                $paymentTotals[$method] = ($paymentTotals[$method] ?? 0) + $amount;
            }
        }

        $lines[] = 'Uang keluar tercatat: ' . $this->waMoney2($paidPoTotal + $otherCashOutTotal);

        if (!$grouped && !$cashOutRows) {
            $lines[] = 'Tidak ada rincian belanja pada tanggal ini.';
            return ['title' => 'Rincian belanja hari ini', 'body' => implode("\n", $lines)];
        }

        foreach ($grouped as $typeName => $items) {
            $lines[] = '';
            $lines[] = '*' . $typeName . ':*';
            $no = 1;
            foreach ($items as $item) {
                $name = trim((string)($item['line_name'] ?? 'Item'));
                $brand = trim((string)($item['brand_name'] ?? ''));
                $nameText = $brand !== '' ? ($name . ' - ' . $brand) : $name;
                $qty = $this->waNumber((float)($item['qty_buy'] ?? 0));
                $buyUom = trim((string)($item['buy_uom_code'] ?? ''));
                $contentPerBuy = number_format((float)($item['content_per_buy'] ?? 0), 2, ',', '.');
                $contentUom = trim((string)($item['content_uom_code'] ?? ''));
                $paymentText = strtoupper((string)($item['status'] ?? '')) === 'PAID'
                    ? (trim((string)($item['payment_account_name'] ?? '')) ?: 'Tanpa rekening')
                    : strtoupper((string)($item['status'] ?? 'BELUM PAID'));
                $lines[] = $no . '. ' . $nameText
                    . ', ' . $qty . ($buyUom !== '' ? ' ' . $buyUom : '')
                    . ', isi per ' . ($buyUom !== '' ? $buyUom : 'unit') . ': ' . $contentPerBuy . ($contentUom !== '' ? ' ' . $contentUom : '')
                    . ', Payment: ' . $this->waMoney2((float)($item['line_subtotal'] ?? 0)) . ' ' . $paymentText;
                $no++;
            }
        }

        if ($cashOutRows) {
            $lines[] = '';
            $lines[] = '*Pengeluaran Lain:*';
            $no = 1;
            foreach ($cashOutRows as $row) {
                $label = $this->waCashOutLabel((string)($row['ref_module'] ?? ''), (string)($row['ref_table'] ?? ''), (string)($row['ref_no'] ?? ''), (string)($row['notes'] ?? ''));
                $account = trim((string)($row['account_name'] ?? 'Tanpa rekening')) ?: 'Tanpa rekening';
                $lines[] = $no . '. ' . $label . ', Payment: ' . $this->waMoney2((float)($row['amount'] ?? 0)) . ' ' . $account;
                $no++;
            }
        }

        $lines[] = '';
        $lines[] = '*Rekapitulasi Belanja:*';
        $idx = 1;
        foreach ($typeTotals as $typeName => $total) {
            $lines[] = $idx . '. ' . $typeName . ': ' . $this->waMoney2((float)$total);
            $idx++;
        }
        if ($otherCashOutTotal > 0) {
            $lines[] = $idx . '. Pengeluaran Lain: ' . $this->waMoney2($otherCashOutTotal);
            $idx++;
        }

        $lines[] = '';
        $lines[] = '*Rekapitulasi Metode Pembayaran:*';
        $idx = 1;
        if ($paymentTotals) {
            foreach ($paymentTotals as $method => $total) {
                $lines[] = $idx . '. ' . $method . ': ' . $this->waMoney2((float)$total);
                $idx++;
            }
        }
        if ($unpaidTotals) {
            $parts = [];
            foreach ($unpaidTotals as $status => $total) {
                $parts[] = $this->waMoney2((float)$total) . ' (status ' . $status . ')';
            }
            $lines[] = $idx . '. Belum terbayar: ' . implode(', ', $parts);
        }

        return ['title' => 'Rincian belanja hari ini', 'body' => implode("\n", $lines)];
    }

    private function waCashOutLabel(string $module, string $table, string $refNo, string $notes): string
    {
        $module = strtoupper(trim($module));
        $table = strtolower(trim($table));
        $label = trim($refNo) !== '' ? trim($refNo) : trim($notes);
        if ($module === 'PAYROLL' && strpos($table, 'meal') !== false) {
            return 'Uang makan' . ($label !== '' ? ' - ' . $label : '');
        }
        if ($module === 'PAYROLL' && strpos($table, 'cash_advance') !== false) {
            return 'Kasbon' . ($label !== '' ? ' - ' . $label : '');
        }
        if ($module === 'PAYROLL' && strpos($table, 'salary') !== false) {
            return 'Gaji' . ($label !== '' ? ' - ' . $label : '');
        }
        if ($module !== '') {
            return ucwords(strtolower(str_replace('_', ' ', $module))) . ($label !== '' ? ' - ' . $label : '');
        }
        return $label !== '' ? $label : 'Pengeluaran lain';
    }

    private function buildWaAdjustmentReport(string $date): array
    {
        $lines = ['*Rincian adjustment ' . $this->waDateLabel($date) . '*'];
        $hasAny = false;

        if ($this->db->table_exists('inv_stock_adjustment') && $this->db->table_exists('inv_stock_adjustment_line')) {
            $row = $this->db->select('COUNT(DISTINCT h.id) AS doc_count, COUNT(l.id) AS line_count, COALESCE(SUM((COALESCE(l.qty_waste_content,0)+COALESCE(l.qty_spoil_content,0)+COALESCE(l.qty_process_loss_content,0)+COALESCE(l.qty_variance_content,0))*COALESCE(l.unit_cost,0)),0) AS value_out, COALESCE(SUM(COALESCE(l.qty_adjustment_plus_content,0)*COALESCE(l.unit_cost,0)),0) AS value_plus', false)
                ->from('inv_stock_adjustment h')
                ->join('inv_stock_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
                ->where('h.status', 'POSTED')
                ->where('h.adjustment_date', $date)
                ->get()
                ->row_array() ?: [];
            $lines[] = 'Bahan baku: ' . (int)($row['doc_count'] ?? 0) . ' nota / ' . (int)($row['line_count'] ?? 0) . ' line · keluar ' . $this->waMoney((float)($row['value_out'] ?? 0)) . ' · plus ' . $this->waMoney((float)($row['value_plus'] ?? 0));
            $detailRows = $this->db
                ->select('h.stock_scope, h.destination_type')
                ->select('COALESCE(d.code, h.destination_type, "-") AS location_name', false)
                ->select('COALESCE(l.profile_name, l.profile_description, i.item_name, m.material_name, "Bahan") AS line_name', false)
                ->select('COALESCE(l.profile_brand, "") AS brand_name', false)
                ->select('l.profile_content_uom_code AS uom_code, l.unit_cost')
                ->select('(COALESCE(l.qty_adjustment_plus_content,0) - COALESCE(l.qty_waste_content,0) - COALESCE(l.qty_spoil_content,0) - COALESCE(l.qty_process_loss_content,0) - COALESCE(l.qty_variance_content,0)) AS net_qty', false)
                ->select('(COALESCE(l.qty_adjustment_plus_content,0)*COALESCE(l.unit_cost,0) - (COALESCE(l.qty_waste_content,0)+COALESCE(l.qty_spoil_content,0)+COALESCE(l.qty_process_loss_content,0)+COALESCE(l.qty_variance_content,0))*COALESCE(l.unit_cost,0)) AS net_value', false)
                ->select("CASE
                    WHEN COALESCE(l.qty_adjustment_plus_content,0) <> 0 THEN 'Adjustment Plus'
                    WHEN COALESCE(l.qty_waste_content,0) <> 0 THEN 'Waste'
                    WHEN COALESCE(l.qty_spoil_content,0) <> 0 THEN 'Spoil'
                    WHEN COALESCE(l.qty_process_loss_content,0) <> 0 THEN 'Process Loss'
                    WHEN COALESCE(l.qty_variance_content,0) <> 0 THEN 'Variance'
                    ELSE 'Adjustment'
                END AS reason_type", false)
                ->select('COALESCE(l.adjustment_plus_reason_code,l.waste_reason_code,l.spoil_reason_code,l.process_loss_reason_code,l.variance_reason_code,"-") AS reason_code', false)
                ->select('l.note AS line_note')
                ->from('inv_stock_adjustment h')
                ->join('inv_stock_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
                ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
                ->join('mst_item i', 'i.id = l.item_id', 'left')
                ->join('mst_material m', 'm.id = l.material_id', 'left')
                ->where('h.status', 'POSTED')
                ->where('h.adjustment_date', $date)
                ->order_by('h.id', 'ASC')
                ->order_by('l.line_no', 'ASC')
                ->limit(30)
                ->get()
                ->result_array();
            if ($detailRows) {
                $lines[] = 'Detail bahan baku:';
                $no = 1;
                foreach ($detailRows as $detail) {
                    $brand = trim((string)($detail['brand_name'] ?? ''));
                    $name = trim((string)($detail['line_name'] ?? 'Bahan'));
                    $label = $brand !== '' ? ($name . ' - ' . $brand) : $name;
                    $uom = trim((string)($detail['uom_code'] ?? ''));
                    $lines[] = $no . '. ' . $label
                        . ' · ' . (string)($detail['location_name'] ?? '-')
                        . ' · ' . $this->waNumber((float)($detail['net_qty'] ?? 0)) . ($uom !== '' ? ' ' . $uom : '')
                        . ' · ' . $this->waReasonText((string)($detail['reason_type'] ?? ''), (string)($detail['reason_code'] ?? ''))
                        . ($this->waNoteText((string)($detail['line_note'] ?? '')) !== '' ? ' · ' . $this->waNoteText((string)($detail['line_note'] ?? '')) : '')
                        . ' · ' . $this->waMoney((float)($detail['net_value'] ?? 0));
                    $no++;
                }
            }
            $hasAny = true;
        }

        if ($this->db->table_exists('inv_component_adjustment') && $this->db->table_exists('inv_component_adjustment_line')) {
            $row = $this->db->select('COUNT(DISTINCT h.id) AS doc_count, COUNT(l.id) AS line_count, COALESCE(SUM((COALESCE(l.qty_waste,0)+COALESCE(l.qty_spoil,0)+COALESCE(l.qty_adjust_neg,0))*COALESCE(l.unit_cost,0)),0) AS value_out, COALESCE(SUM(COALESCE(l.qty_adjust_pos,0)*COALESCE(l.unit_cost,0)),0) AS value_plus', false)
                ->from('inv_component_adjustment h')
                ->join('inv_component_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
                ->where('h.status', 'POSTED')
                ->where('h.adjustment_date', $date)
                ->get()
                ->row_array() ?: [];
            $lines[] = 'Component: ' . (int)($row['doc_count'] ?? 0) . ' nota / ' . (int)($row['line_count'] ?? 0) . ' line · keluar ' . $this->waMoney((float)($row['value_out'] ?? 0)) . ' · plus ' . $this->waMoney((float)($row['value_plus'] ?? 0));
            $detailRows = $this->db
                ->select('h.location_type')
                ->select('COALESCE(d.code, h.location_type, "-") AS location_name', false)
                ->select('COALESCE(c.component_name, "Component") AS component_name', false)
                ->select('u.code AS uom_code, l.unit_cost')
                ->select('(COALESCE(l.qty_adjust_pos,0) - COALESCE(l.qty_waste,0) - COALESCE(l.qty_spoil,0) - COALESCE(l.qty_adjust_neg,0)) AS net_qty', false)
                ->select('(COALESCE(l.qty_adjust_pos,0)*COALESCE(l.unit_cost,0) - (COALESCE(l.qty_waste,0)+COALESCE(l.qty_spoil,0)+COALESCE(l.qty_adjust_neg,0))*COALESCE(l.unit_cost,0)) AS net_value', false)
                ->select("CASE
                    WHEN COALESCE(l.qty_adjust_pos,0) <> 0 THEN 'Adjustment Plus'
                    WHEN COALESCE(l.qty_waste,0) <> 0 THEN 'Waste'
                    WHEN COALESCE(l.qty_spoil,0) <> 0 THEN 'Spoil'
                    WHEN COALESCE(l.qty_adjust_neg,0) <> 0 THEN 'Adjustment Minus'
                    ELSE 'Adjustment'
                END AS reason_type", false)
                ->select('COALESCE(l.adjustment_plus_reason_code,l.waste_reason_code,l.spoil_reason_code,l.adjustment_minus_reason_code,"-") AS reason_code', false)
                ->select('l.note AS line_note')
                ->from('inv_component_adjustment h')
                ->join('inv_component_adjustment_line l', 'l.adjustment_id = h.id', 'inner')
                ->join('mst_component c', 'c.id = l.component_id', 'left')
                ->join('mst_uom u', 'u.id = l.uom_id', 'left')
                ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
                ->where('h.status', 'POSTED')
                ->where('h.adjustment_date', $date)
                ->order_by('h.id', 'ASC')
                ->order_by('l.line_no', 'ASC')
                ->limit(30)
                ->get()
                ->result_array();
            if ($detailRows) {
                $lines[] = 'Detail component:';
                $no = 1;
                foreach ($detailRows as $detail) {
                    $uom = trim((string)($detail['uom_code'] ?? ''));
                    $lines[] = $no . '. ' . trim((string)($detail['component_name'] ?? 'Component'))
                        . ' · ' . (string)($detail['location_name'] ?? '-')
                        . ' · ' . $this->waNumber((float)($detail['net_qty'] ?? 0)) . ($uom !== '' ? ' ' . $uom : '')
                        . ' · ' . $this->waReasonText((string)($detail['reason_type'] ?? ''), (string)($detail['reason_code'] ?? ''))
                        . ($this->waNoteText((string)($detail['line_note'] ?? '')) !== '' ? ' · ' . $this->waNoteText((string)($detail['line_note'] ?? '')) : '')
                        . ' · ' . $this->waMoney((float)($detail['net_value'] ?? 0));
                    $no++;
                }
            }
            $hasAny = true;
        }

        if (!$hasAny) {
            $lines[] = 'Data adjustment belum tersedia.';
        }
        return ['title' => 'Rincian adjustment hari ini', 'body' => implode("\n", $lines)];
    }

    private function buildWaPoSrReport(string $date): array
    {
        $lines = ['*Pengajuan PO/SR ' . $this->waDateLabel($date) . '*'];

        if (!$this->db->table_exists('pur_division_request') || !$this->db->table_exists('pur_division_request_line')) {
            $lines[] = 'Data pengajuan divisi belum tersedia.';
            return ['title' => 'Pengajuan PO/SR divisi hari ini', 'body' => implode("\n", $lines)];
        }

        $rows = $this->db
            ->select('r.status, r.destination_type')
            ->select('COALESCE(d.name, r.destination_type, "-") AS division_name', false)
            ->select('l.line_no, l.profile_name, l.profile_brand, l.profile_description')
            ->select('l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code')
            ->select('l.qty_content_requested, l.qty_content_to_po, l.qty_content_to_sr, l.notes AS line_notes')
            ->from('pur_division_request_line l')
            ->join('pur_division_request r', 'r.id = l.request_id', 'inner')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left')
            ->where('r.request_date', $date)
            ->where('r.status !=', 'VOID')
            ->order_by("FIELD(r.destination_type, 'BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT', 'OFFICE', 'OTHER')", '', false)
            ->order_by('r.id', 'ASC')
            ->order_by('l.line_no', 'ASC')
            ->limit(300)
            ->get()
            ->result_array();

        $poGroups = [];
        $srGroups = [];
        foreach ($rows as $row) {
            $destination = strtoupper(trim((string)($row['destination_type'] ?? 'OTHER')));
            $label = $this->waDivisionDestinationShortLabel($destination, (string)($row['division_name'] ?? ''));
            $poQtyContent = round((float)($row['qty_content_to_po'] ?? 0), 4);
            $srQtyContent = round((float)($row['qty_content_to_sr'] ?? 0), 4);
            if ($poQtyContent > 0) {
                $poGroups[$destination]['label'] = $label;
                $poGroups[$destination]['rows'][] = $this->waFormatDivisionRequestLine($row, $poQtyContent);
            }
            if ($srQtyContent > 0) {
                $srGroups[$destination]['label'] = $label;
                $srGroups[$destination]['rows'][] = $this->waFormatDivisionRequestLine($row, $srQtyContent);
            }
        }

        if (empty($poGroups) && empty($srGroups)) {
            $lines[] = 'Tidak ada pengajuan PO/SR divisi.';
            return ['title' => 'Pengajuan PO/SR divisi hari ini', 'body' => implode("\n", $lines)];
        }

        foreach ($poGroups as $group) {
            $lines[] = '';
            $lines[] = '*Belanja Stok ' . $group['label'] . '*';
            $no = 1;
            foreach ((array)$group['rows'] as $line) {
                $lines[] = $no . '. ' . $line;
                $no++;
            }
        }

        foreach ($srGroups as $group) {
            $lines[] = '';
            $lines[] = '*SR ' . $group['label'] . '*';
            $no = 1;
            foreach ((array)$group['rows'] as $line) {
                $lines[] = $no . '. ' . $line;
                $no++;
            }
        }

        return ['title' => 'Pengajuan PO/SR divisi hari ini', 'body' => implode("\n", $lines)];
    }

    private function waDivisionDestinationShortLabel(string $destinationType, string $divisionName = ''): string
    {
        switch (strtoupper(trim($destinationType))) {
            case 'BAR':
                return 'Bar';
            case 'KITCHEN':
                return 'Kitchen';
            case 'ROASTERY':
                return 'Roastery';
            case 'BAR_EVENT':
                return 'Bar Event';
            case 'KITCHEN_EVENT':
                return 'Kitchen Event';
            case 'ROASTERY_EVENT':
                return 'Roastery Event';
            case 'OFFICE':
                return 'Office';
            default:
                $divisionName = trim($divisionName);
                return $divisionName !== '' ? ucwords(strtolower($divisionName)) : 'Lainnya';
        }
    }

    private function waFormatDivisionRequestLine(array $row, float $qtyContent): string
    {
        $brand = trim((string)($row['profile_brand'] ?? ''));
        $name = trim((string)($row['profile_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['profile_description'] ?? 'Item'));
        }
        $label = $brand !== '' ? ($name . ' - ' . $brand) : $name;

        $contentPerBuy = (float)($row['profile_content_per_buy'] ?? 1);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }
        $qtyBuy = round($qtyContent / $contentPerBuy, 4);
        $buyUom = trim((string)($row['profile_buy_uom_code'] ?? ''));
        $contentUom = trim((string)($row['profile_content_uom_code'] ?? ''));
        $parts = [
            $label,
            $this->waNumber($qtyBuy) . ($buyUom !== '' ? ' ' . $buyUom : ''),
        ];
        if ($contentPerBuy > 0 && $contentUom !== '') {
            $parts[] = 'isi per ' . ($buyUom !== '' ? $buyUom : 'unit') . ': ' . $this->waNumber($contentPerBuy) . ' ' . $contentUom;
        }
        $notes = trim((string)($row['line_notes'] ?? ''));
        if ($notes !== '') {
            $parts[] = 'catatan: ' . $notes;
        }
        return implode(' · ', $parts);
    }

    private function waDateLabel(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : $date;
    }

    private function waResolveMonthYearFromCommand(string $command, string $date): array
    {
        $baseTs = strtotime($date) ?: time();
        $year = (int)date('Y', $baseTs);
        $month = (int)date('n', $baseTs);
        $command = strtolower(trim($command));

        if (preg_match('/\bbulan\s+lalu\b/', $command)) {
            $prevTs = strtotime('first day of previous month', strtotime(date('Y-m-01', $baseTs)));
            return [(int)date('Y', $prevTs), (int)date('n', $prevTs)];
        }

        if (preg_match('/\b(20\d{2})[-\/](\d{1,2})\b/', $command, $m)) {
            return [(int)$m[1], max(1, min(12, (int)$m[2]))];
        }

        if (preg_match('/\b(\d{1,2})[-\/](20\d{2})\b/', $command, $m)) {
            return [(int)$m[2], max(1, min(12, (int)$m[1]))];
        }

        $monthMap = [
            'januari' => 1, 'jan' => 1, 'january' => 1,
            'februari' => 2, 'feb' => 2, 'february' => 2,
            'maret' => 3, 'mar' => 3, 'march' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5, 'may' => 5,
            'juni' => 6, 'jun' => 6, 'june' => 6,
            'juli' => 7, 'jul' => 7, 'july' => 7,
            'agustus' => 8, 'agu' => 8, 'aug' => 8, 'august' => 8,
            'september' => 9, 'sep' => 9,
            'oktober' => 10, 'okt' => 10, 'oct' => 10, 'october' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'des' => 12, 'dec' => 12, 'december' => 12,
        ];
        foreach ($monthMap as $name => $monthNo) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b(?:\s+(20\d{2}))?/', $command, $m)) {
                return [!empty($m[1]) ? (int)$m[1] : $year, $monthNo];
            }
        }

        return [$year, $month];
    }

    private function waMoney(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function waMoney2(float $amount): string
    {
        return 'Rp ' . number_format($amount, 2, ',', '.');
    }

    private function waNumber(float $amount): string
    {
        $formatted = number_format($amount, 4, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');
        return $formatted === '' ? '0' : $formatted;
    }

    private function waReasonText(string $type, string $code): string
    {
        $type = trim($type) !== '' ? trim($type) : 'Adjustment';
        $code = trim($code);
        $label = $code !== '' && $code !== '-'
            ? ucwords(strtolower(str_replace('_', ' ', $code)))
            : '-';
        return $type . ': ' . $label;
    }

    private function waNoteText(string $note): string
    {
        $note = trim($note);
        return $note !== '' ? ('Catatan: ' . $note) : '';
    }

    private function wa_engine_control_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_ENGINE_CONTROL_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_ENGINE_CONTROL_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_env_save_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_ENV_SAVE_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_ENV_SAVE_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_template_group_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_TEMPLATE_GROUP_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_TEMPLATE_GROUP_MUTATION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_report_schedule_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_REPORT_SCHEDULE_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_REPORT_SCHEDULE_MUTATION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_broadcast_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_BROADCAST_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_BROADCAST_MUTATION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_manual_single_send_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_MANUAL_SINGLE_SEND_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_MANUAL_SINGLE_SEND_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_log_retry_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_LOG_RETRY_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_LOG_RETRY_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_settings_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_SETTINGS_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_SETTINGS_MUTATION_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function wa_send_test_csrf(): string
    {
        $token = (string)$this->session->userdata(self::WA_SEND_TEST_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::WA_SEND_TEST_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    private function require_wa_send_test_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_send_test_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI menormalkan nama CGI/FastCGI ke title case (...-Csrf).
        // Token kirim-test hanya diterima dari header khusus endpoint ini.
        $providedToken = (string)$this->input->get_request_header(self::WA_SEND_TEST_CSRF_CI_HEADER, true);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_send_test_csrf(403, 'Permintaan pengiriman test WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_SEND_TEST_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_send_test_csrf(403, 'Permintaan pengiriman test WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_send_test_csrf(int $statusCode, string $message): void
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

    private function require_wa_settings_mutation_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_settings_mutation_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->post(self::WA_SETTINGS_MUTATION_CSRF_FORM_FIELD, false);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_settings_mutation_csrf(403, 'Permintaan perubahan pengaturan WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_SETTINGS_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_settings_mutation_csrf(403, 'Permintaan perubahan pengaturan WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_settings_mutation_csrf(int $statusCode, string $message): void
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

    private function require_wa_log_retry_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_log_retry_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI menormalkan nama CGI/FastCGI ke title case (...-Csrf).
        // Token retry hanya diterima dari header khusus; query, form, JSON,
        // dan raw body tidak menjadi jalur alternatif.
        $providedToken = (string)$this->input->get_request_header(self::WA_LOG_RETRY_CSRF_CI_HEADER, true);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_log_retry_csrf(403, 'Permintaan retry log WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_LOG_RETRY_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_log_retry_csrf(403, 'Permintaan retry log WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_log_retry_csrf(int $statusCode, string $message): void
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

    private function require_wa_broadcast_mutation_csrf(bool $headerOnly = false): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_broadcast_mutation_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        if ($headerOnly) {
            // CI menormalkan nama CGI/FastCGI ke title case (...-Csrf).
            // Kontrak browser tetap memakai ...-CSRF; tidak ada fallback ke
            // query, form field, atau raw body untuk endpoint dispatch.
            $providedToken = (string)$this->input->get_request_header(self::WA_BROADCAST_MUTATION_CSRF_CI_HEADER, true);
        } else {
            $providedToken = (string)$this->input->post(self::WA_BROADCAST_MUTATION_CSRF_FORM_FIELD, false);
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_broadcast_mutation_csrf(403, 'Permintaan mutasi broadcast WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_BROADCAST_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_broadcast_mutation_csrf(403, 'Permintaan mutasi broadcast WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function require_wa_manual_single_send_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_manual_single_send_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->post(self::WA_MANUAL_SINGLE_SEND_CSRF_FORM_FIELD, false);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_manual_single_send_csrf(403, 'Permintaan pengiriman manual WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_MANUAL_SINGLE_SEND_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_manual_single_send_csrf(403, 'Permintaan pengiriman manual WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_manual_single_send_csrf(int $statusCode, string $message): void
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

    private function reject_wa_broadcast_mutation_csrf(int $statusCode, string $message): void
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

    private function require_wa_report_schedule_mutation_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_report_schedule_mutation_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->post(self::WA_REPORT_SCHEDULE_MUTATION_CSRF_FORM_FIELD, false);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_report_schedule_mutation_csrf(403, 'Permintaan mutasi jadwal laporan WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_REPORT_SCHEDULE_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_report_schedule_mutation_csrf(403, 'Permintaan mutasi jadwal laporan WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_report_schedule_mutation_csrf(int $statusCode, string $message): void
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

    private function require_wa_template_group_mutation_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_template_group_mutation_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        $providedToken = (string)$this->input->post(self::WA_TEMPLATE_GROUP_MUTATION_CSRF_FORM_FIELD, false);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_template_group_mutation_csrf(403, 'Permintaan mutasi template/grup WhatsApp tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_TEMPLATE_GROUP_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_template_group_mutation_csrf(403, 'Permintaan mutasi template/grup WhatsApp tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_template_group_mutation_csrf(int $statusCode, string $message): void
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

    private function require_wa_env_save_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_env_save_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI menormalkan nama CGI/FastCGI ke title case (...-Csrf).
        // Kontrak browser tetap memakai ...-CSRF, sedangkan lookup memakai
        // bentuk kanonis agar aman juga pada input double yang exact-key.
        $providedToken = (string)$this->input->get_request_header(self::WA_ENV_SAVE_CSRF_CI_HEADER, true);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_env_save_csrf(403, 'Permintaan penyimpanan konfigurasi wa-engine tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_ENV_SAVE_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_env_save_csrf(403, 'Permintaan penyimpanan konfigurasi wa-engine tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_env_save_csrf(int $statusCode, string $message): void
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

    private function require_wa_engine_control_csrf(): bool
    {
        if ($this->input->method(true) !== 'POST') {
            $this->reject_wa_engine_control_csrf(405, 'Metode request tidak diizinkan.');
            return false;
        }

        // CI menormalkan nama CGI/FastCGI ke title case (...-Csrf).
        // Kontrak browser tetap memakai ...-CSRF, sedangkan lookup memakai
        // bentuk kanonis agar aman juga pada input double yang exact-key.
        $providedToken = trim((string)$this->input->get_request_header(self::WA_ENGINE_CONTROL_CSRF_CI_HEADER, true));
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $providedToken) !== 1) {
            $this->reject_wa_engine_control_csrf(403, 'Permintaan kontrol wa-engine tidak valid.');
            return false;
        }

        $sessionToken = (string)$this->session->userdata(self::WA_ENGINE_CONTROL_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-fA-F]{64}\z/D', $sessionToken) !== 1
            || !hash_equals($sessionToken, $providedToken)
        ) {
            $this->reject_wa_engine_control_csrf(403, 'Permintaan kontrol wa-engine tidak valid.');
            return false;
        }

        return true;
    }

    private function reject_wa_engine_control_csrf(int $statusCode, string $message): void
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

    private function jsonOut(array $data): void
    {
        // Flush semua output buffer (termasuk PHP warnings/notices) agar tidak mencemari JSON
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"ok":false,"message":"JSON encode error"}';
        }
        // Bypass CI output system: pakai header + echo + exit agar dijamin hanya JSON yang terkirim
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo $json;
        exit;
    }

    private function enginePort(): int
    {
        $url = $this->waSession()['bot_api_url'] ?? 'http://127.0.0.1:3070';
        if (preg_match('/:(\d+)/', $url, $m)) return (int)$m[1];
        return 3070;
    }

    private function findNodePath(): string
    {
        // Gunakan exec() untuk test executable — TIDAK pakai file_exists() karena
        // aaPanel mengaktifkan open_basedir yang memblokir akses ke /usr/bin, /usr/local/bin dll.
        $canExec = function (string $path): bool {
            if ($path === '') return false;
            $out = [];
            exec('test -x ' . escapeshellarg($path) . ' 2>/dev/null && echo 1', $out);
            return trim($out[0] ?? '') === '1';
        };

        // 1. Path tersimpan di DB (diisi user via settings)
        $session = $this->waSession();
        $stored  = trim((string)($session['node_path'] ?? ''));
        if ($stored !== '' && $canExec($stored)) return $stored;

        // 2. which node / nodejs — cara paling reliable, exec() tidak dibatasi open_basedir
        foreach (['which node', 'which nodejs'] as $cmd) {
            $out = [];
            exec($cmd . ' 2>/dev/null', $out);
            $p = trim($out[0] ?? '');
            if ($p !== '' && $canExec($p)) return $p;
        }

        // 3. NVM paths — test via canExec (glob juga bisa gagal karena open_basedir)
        $nvmCandidates = [];
        foreach (['/root', '/home/*', '/www'] as $base) {
            $pattern = $base . '/.nvm/versions/node/*/bin/node';
            $found   = @glob($pattern) ?: [];
            $nvmCandidates = array_merge($nvmCandidates, $found);
        }
        if (!empty($nvmCandidates)) {
            usort($nvmCandidates, fn($a, $b) => version_compare(
                basename(dirname(dirname($a))), basename(dirname(dirname($b)))
            ));
            $latest = end($nvmCandidates);
            if ($canExec($latest)) return $latest;
        }

        // 4. Path sistem umum — test via canExec
        foreach ([
            '/usr/local/bin/node',
            '/usr/bin/node',
            '/usr/bin/nodejs',
            '/opt/nodejs/bin/node',
            '/snap/bin/node',
            '/www/server/nodejs/bin/node',
        ] as $p) {
            if ($canExec($p)) return $p;
        }

        return '';
    }

    private function buildEnvString(string $engineDir): string
    {
        $envFile = $engineDir . '/.env';
        $values = [];
        foreach (file_exists($envFile) ? (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim($k);
            $v = trim($v);
            if ($k === self::WA_GROUP_COMMAND_TOKEN_ENV || $k === self::WA_ENGINE_API_TOKEN_ENV) {
                // Credential service wajib diwariskan dari process environment
                // PHP/FPM, bukan file di bawah web root.
                continue;
            }
            if ($k !== '' && preg_match('/^[A-Z_][A-Z0-9_]*$/i', $k)) {
                $values[$k] = $v;
            }
        }

        // Ikuti koneksi aktif Finance bila .env belum dibuat atau hanya
        // sebagian diisi. Nilai .env yang sengaja diatur tetap diprioritaskan.
        foreach ([
            'DB_HOST' => 'hostname',
            'DB_USER' => 'username',
            'DB_PASS' => 'password',
            'DB_NAME' => 'database',
        ] as $envKey => $databaseProperty) {
            if (isset($values[$envKey]) && trim((string)$values[$envKey]) !== '') {
                continue;
            }
            $value = isset($this->db->{$databaseProperty}) ? (string)$this->db->{$databaseProperty} : '';
            if ($value !== '') {
                $values[$envKey] = $value;
            }
        }

        // Secrets stay outside the document root. They are injected only into
        // the child process and are never written to wa-engine/.env or output.
        foreach ([self::WA_ENGINE_API_TOKEN_ENV, self::WA_GROUP_COMMAND_TOKEN_ENV, self::WA_ENGINE_COMMAND_URL_ENV] as $secretKey) {
            $secretValue = $this->waEngineSecretValue($secretKey);
            if ($secretValue !== '') {
                $values[$secretKey] = $secretValue;
            }
        }

        $str = '';
        foreach ($values as $key => $value) {
            $str .= $key . '=' . escapeshellarg($value) . ' ';
        }
        return $str;
    }

    /** Read one process secret from environment or the root-managed runtime file. */
    private function waEngineSecretValue(string $key): string
    {
        $value = trim((string)getenv($key));
        if ($value !== '') {
            return preg_match('/[\x00-\x1F\x7F]/', $value) ? '' : $value;
        }

        $path = self::WA_ENGINE_SECRET_FILE;
        if (!is_readable($path)) {
            return '';
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (!preg_match('/^' . preg_quote($key, '/') . '=(.+)$/D', trim($line), $matches)) {
                continue;
            }
            $value = trim((string)$matches[1]);
            return preg_match('/[\x00-\x1F\x7F]/', $value) ? '' : $value;
        }
        return '';
    }

    private function engineScriptPath(): string
    {
        $engineDir = realpath(FCPATH . 'wa-engine') ?: (FCPATH . 'wa-engine');
        return rtrim($engineDir, '/\\') . '/index.js';
    }

    private function removeEnginePidFile(): void
    {
        $engineDir = realpath(FCPATH . 'wa-engine') ?: (FCPATH . 'wa-engine');
        $pidPath = rtrim($engineDir, '/\\') . '/wa-engine.pid';
        if (is_file($pidPath)) {
            @unlink($pidPath);
        }
    }

    private function engineProcessPids(): array
    {
        $script = $this->engineScriptPath();
        $matches = [];

        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $cmdlineFile) {
            $pid = (int)basename(dirname($cmdlineFile));
            if ($pid <= 0) {
                continue;
            }
            $raw = @file_get_contents($cmdlineFile);
            if ($raw === false || $raw === '') {
                continue;
            }
            $cmdline = str_replace("\0", ' ', trim($raw));
            if ($cmdline === '') {
                continue;
            }
            if (strpos($cmdline, $script) === false) {
                continue;
            }
            $exePath = @readlink('/proc/' . $pid . '/exe');
            $comm    = trim((string)@file_get_contents('/proc/' . $pid . '/comm'));
            $baseExe = $exePath ? basename($exePath) : '';
            $firstArg = strtok($cmdline, ' ') ?: '';
            $isNode  = in_array($baseExe, ['node', 'nodejs'], true)
                || in_array($comm, ['node', 'nodejs'], true)
                || in_array(basename($firstArg), ['node', 'nodejs'], true)
                || (bool)preg_match('/(^|\s)(\/[^\s]+\/)?node(js)?\s+/i', $cmdline);
            if (!$isNode) {
                continue;
            }
            $matches[] = $pid;
        }

        sort($matches);
        return array_values(array_unique($matches));
    }

    private function enginePortPids(int $port): array
    {
        if (!function_exists('exec')) {
            return [];
        }

        $out = [];
        exec("lsof -tiTCP:{$port} -sTCP:LISTEN 2>/dev/null", $out);
        $pids = array_values(array_filter(array_map('intval', $out)));
        if (!empty($pids)) {
            return $pids;
        }

        $ssOut = [];
        exec("ss -ltnp '( sport = :{$port} )' 2>/dev/null", $ssOut);
        $joined = implode("\n", $ssOut);
        if (preg_match_all('/pid=(\d+)/', $joined, $m)) {
            return array_values(array_unique(array_map('intval', $m[1])));
        }
        return [];
    }

    private function engineStatusSnapshot(): array
    {
        $port = $this->enginePort();
        $processPids = $this->engineProcessPids();
        $portPids = $this->enginePortPids($port);
        $portListening = !empty($portPids);

        // PHP-FPM tertentu tidak diizinkan melihat PID Node via lsof/ss.
        // Socket loopback adalah bukti langsung bahwa engine sudah menerima koneksi.
        if (!$portListening && function_exists('fsockopen')) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.5);
            if (is_resource($socket)) {
                $portListening = true;
                fclose($socket);
            }
        }

        return [
            'port'           => $port,
            'process_pids'   => $processPids,
            'port_pids'      => $portPids,
            'running'        => !empty($processPids),
            'port_listening' => $portListening,
        ];
    }

    private function engineLogPath(string $engineDir, bool $forWrite = false): string
    {
        $primary  = rtrim($engineDir, '/\\') . '/wa-engine.log';
        $fallback = rtrim($engineDir, '/\\') . '/wa-engine-web.log';

        if ($forWrite) {
            clearstatcache(true, $primary);
            if ((!file_exists($primary) && is_writable($engineDir)) || (file_exists($primary) && is_writable($primary))) {
                return $primary;
            }
            @chmod($primary, 0666);
            clearstatcache(true, $primary);
            if (file_exists($primary) && is_writable($primary)) {
                return $primary;
            }
            return $fallback;
        }

        $primaryReadable  = is_readable($primary);
        $fallbackReadable = is_readable($fallback);
        if ($primaryReadable && $fallbackReadable) {
            return (@filemtime($fallback) ?: 0) >= (@filemtime($primary) ?: 0) ? $fallback : $primary;
        }
        if ($primaryReadable) {
            return $primary;
        }
        if ($fallbackReadable) {
            return $fallback;
        }
        return $primary;
    }

    private function ensureSchema(): void
    {
        if (!$this->db->table_exists('wa_session')) {
            return; // SQL belum dijalankan
        }
        // Schema WhatsApp is prepared by migration before deployment. Runtime
        // requests may seed safe defaults below, but must not alter tables.
        if ($this->db->table_exists('wa_template')) {
            $exists = $this->db->from('wa_template')
                ->where('template_code', 'REPORT_DEFAULT')
                ->limit(1)
                ->count_all_results();
            if (!$exists) {
                $this->db->insert('wa_template', [
                    'template_code' => 'REPORT_DEFAULT',
                    'name' => 'Default Laporan Otomatis',
                    'category' => 'INFO',
                    'body' => "{{report_title}}\n\n{{report_body}}\n\nDikirim otomatis: {{generated_at}}",
                    'sample_variables' => '{"report_title":"Omzet hari ini","report_body":"Total: Rp 1.000.000","generated_at":"15/08/2026 19:30"}',
                    'is_active' => 1,
                    'created_by' => 0,
                ]);
            }
        }
        $row = $this->db->from('wa_session')->where('id', 1)->limit(1)->get()->row_array();
        if (!$row) {
            $this->db->insert('wa_session', ['id' => 1]);
        }
    }
}
