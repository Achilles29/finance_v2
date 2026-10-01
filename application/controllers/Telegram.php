<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram extends MY_Controller
{
    private const PAGE_DASHBOARD = 'tg.dashboard';
    private const PAGE_DELIVERY = 'tg.delivery';
    private const PAGE_GUIDE = 'tg.guide';
    private const PAGE_LOG = 'tg.log';
    private const PAGE_SETTINGS = 'tg.settings';
    private const DISCOVERY_SESSION_KEY = 'tg_setup_discovery';
    private const DISCOVERY_TTL_SECONDS = 600;
    private const REPORT_TYPES = [
        'OMZET_TODAY' => 'Omzet hari ini',
        'PURCHASE_TODAY' => 'Belanja hari ini',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Telegram_model');
        $this->load->library('TelegramBotClient', [], 'telegram_client');
        $this->load->library('TelegramReportService', [], 'telegram_report');
    }

    public function index()
    {
        $this->require_permission(self::PAGE_DASHBOARD, 'view');
        $this->require_schema();
        $editId = max(0, (int)$this->input->get('edit', true));
        $this->render('telegram/dashboard', [
            'title' => 'Telegram Bot',
            'active_menu' => 'tg.dashboard',
            'stats' => $this->Telegram_model->dashboard_stats(),
            'targets' => $this->Telegram_model->targets(),
            'edit_target' => $editId > 0 ? $this->Telegram_model->target($editId) : null,
            'can_create' => $this->can(self::PAGE_DASHBOARD, 'create'),
            'can_edit' => $this->can(self::PAGE_DASHBOARD, 'edit'),
            'target_csrf' => $this->csrf_token('target'),
        ]);
    }

    public function guide()
    {
        $this->require_permission(self::PAGE_GUIDE, 'view');
        $this->require_schema();
        $this->render('telegram/guide', [
            'title' => 'Panduan Setup Telegram',
            'active_menu' => 'tg.guide',
            'webhook_url' => $this->telegram_client->configured_webhook_url(),
        ]);
    }

    public function target_save()
    {
        $id = max(0, (int)$this->input->post('id', true));
        $this->require_permission(self::PAGE_DASHBOARD, $id > 0 ? 'edit' : 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('target')) {
            return;
        }

        $chatId = trim((string)$this->input->post('chat_id', true));
        $targetType = strtoupper(trim((string)$this->input->post('target_type', true)));
        $title = trim((string)$this->input->post('title', true));
        if (preg_match('/^-[0-9]{5,19}$/D', $chatId) !== 1
            || !in_array($targetType, ['GROUP', 'SUPERGROUP', 'CHANNEL'], true)
            || $title === '' || mb_strlen($title, 'UTF-8') > 150) {
            $this->session->set_flashdata('error', 'Target harus berupa group/channel internal dengan Chat ID negatif dan nama yang valid.');
            redirect('telegram' . ($id > 0 ? '?edit=' . $id : ''));
            return;
        }

        $ok = $this->Telegram_model->save_target([
            'chat_id' => $chatId,
            'target_type' => $targetType,
            'title' => $title,
            'is_active' => (int)$this->input->post('is_active', true) === 1,
        ], $id, $this->actor_id());
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Target Telegram tersimpan.' : 'Target Telegram gagal disimpan. Pastikan Chat ID belum terdaftar.');
        redirect('telegram');
    }

    public function delivery()
    {
        $this->require_permission(self::PAGE_DELIVERY, 'view');
        $this->require_schema();
        $editId = max(0, (int)$this->input->get('edit', true));
        $this->render('telegram/delivery', [
            'title' => 'Delivery Telegram',
            'active_menu' => 'tg.delivery',
            'schedules' => $this->Telegram_model->schedules(),
            'queue_rows' => $this->Telegram_model->queue_rows(100),
            'targets' => $this->Telegram_model->targets(true),
            'report_types' => self::REPORT_TYPES,
            'edit_schedule' => $editId > 0 ? $this->Telegram_model->schedule($editId) : null,
            'can_create' => $this->can(self::PAGE_DELIVERY, 'create'),
            'can_edit' => $this->can(self::PAGE_DELIVERY, 'edit'),
            'schedule_csrf' => $this->csrf_token('schedule'),
        ]);
    }

    public function schedule_save()
    {
        $id = max(0, (int)$this->input->post('id', true));
        $this->require_permission(self::PAGE_DELIVERY, $id > 0 ? 'edit' : 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('schedule')) {
            return;
        }

        $targetId = (int)$this->input->post('target_id', true);
        $target = $this->Telegram_model->target($targetId);
        $name = trim((string)$this->input->post('schedule_name', true));
        $reportType = strtoupper(trim((string)$this->input->post('report_type', true)));
        $sendTime = trim((string)$this->input->post('send_time', true));
        if (!$target || empty($target['is_active']) || $name === '' || mb_strlen($name, 'UTF-8') > 120
            || !isset(self::REPORT_TYPES[$reportType]) || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $sendTime) !== 1) {
            $this->session->set_flashdata('error', 'Target aktif, nama, tipe laporan, atau jam jadwal tidak valid.');
            redirect('telegram/delivery' . ($id > 0 ? '?edit=' . $id : ''));
            return;
        }

        $ok = $this->Telegram_model->save_schedule([
            'target_id' => $targetId,
            'schedule_name' => $name,
            'report_type' => $reportType,
            'send_time' => $sendTime . ':00',
            'is_active' => (int)$this->input->post('is_active', true) === 1,
        ], $id, $this->actor_id());
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Jadwal Telegram tersimpan.' : 'Jadwal Telegram gagal disimpan.');
        redirect('telegram/delivery');
    }

    public function log()
    {
        $this->require_permission(self::PAGE_LOG, 'view');
        $this->require_schema();
        $this->render('telegram/log', [
            'title' => 'Log Telegram',
            'active_menu' => 'tg.log',
            'logs' => $this->Telegram_model->log_rows(300),
            'can_resolve' => $this->can(self::PAGE_LOG, 'edit'),
            'resolution_csrf' => $this->csrf_token('unknown_resolution'),
            'resolution_request_id' => bin2hex(random_bytes(16)),
        ]);
    }

    public function resolve_unknown()
    {
        $this->require_permission(self::PAGE_LOG, 'edit');
        $this->require_schema();
        if (!$this->require_post_csrf('unknown_resolution')) {
            return;
        }

        $queueId = max(0, (int)$this->input->post('queue_id', true));
        $action = strtoupper(trim((string)$this->input->post('action', true)));
        $reason = trim((string)$this->input->post('reason', true));
        $requestId = strtolower(trim((string)$this->input->post('request_id', true)));
        if ($queueId <= 0 || !in_array($action, ['CONFIRM_SENT', 'CLOSE_FAILED', 'RESEND'], true)
            || $reason === '' || mb_strlen($reason, 'UTF-8') > 500
            || preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1) {
            $this->session->set_flashdata('error', 'Resolusi UNKNOWN, alasan, atau request ID tidak valid.');
            redirect('telegram/log');
            return;
        }

        $result = $this->Telegram_model->resolve_unknown(
            $queueId,
            $action,
            $reason,
            $this->actor_id(),
            $requestId
        );
        $ok = !empty($result['ok']);
        $message = $ok
            ? ($action === 'RESEND' ? 'UNKNOWN ditutup dan antrean resend baru dibuat.' : 'Resolusi UNKNOWN tersimpan dalam audit.')
            : (string)($result['message'] ?? 'UNKNOWN gagal diselesaikan. Muat ulang log dan periksa status terkini.');
        $this->session->set_flashdata($ok ? 'success' : 'error', $message);
        redirect('telegram/log');
    }

    public function settings()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');
        $this->require_schema();
        if ($this->input->method(true) === 'POST') {
            $this->require_permission(self::PAGE_SETTINGS, 'edit');
            if (!$this->require_post_csrf('settings')) {
                return;
            }
            $enable = (int)$this->input->post('enabled', true) === 1;
            if ($enable && (!$this->telegram_client->is_configured() || !$this->telegram_client->is_secret_configured()
                || $this->telegram_client->configured_webhook_url() === '' || $this->Telegram_model->active_target_count() < 1)) {
                $this->session->set_flashdata('error', 'Master switch hanya dapat diaktifkan setelah konfigurasi server valid dan minimal satu target aktif tersedia.');
                redirect('telegram/settings');
                return;
            }
            $ok = $this->Telegram_model->save_enabled($enable, $this->actor_id());
            $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Pengaturan Telegram tersimpan.' : 'Pengaturan Telegram gagal disimpan.');
            redirect('telegram/settings');
            return;
        }

        $discovery = $this->discovery_session();
        $webhookUrl = $this->telegram_client->configured_webhook_url();
        $this->render('telegram/settings', [
            'title' => 'Pengaturan Telegram',
            'active_menu' => 'tg.settings',
            'enabled' => $this->Telegram_model->is_enabled(),
            'token_configured' => $this->telegram_client->is_configured(),
            'secret_configured' => $this->telegram_client->is_secret_configured(),
            'webhook_url_configured' => $webhookUrl !== '',
            'webhook_url' => $webhookUrl,
            'bot_check' => $this->session->flashdata('tg_bot_check'),
            'webhook_check' => $this->session->flashdata('tg_webhook_check'),
            'discovered_targets' => $this->discovery_rows($discovery),
            'discovery_expires_at' => (int)($discovery['expires_at'] ?? 0),
            'active_target_count' => $this->Telegram_model->active_target_count(),
            'targets' => $this->Telegram_model->targets(true),
            'can_edit' => $this->can(self::PAGE_SETTINGS, 'edit'),
            'can_test' => $this->can(self::PAGE_SETTINGS, 'create'),
            'can_guide' => $this->can(self::PAGE_GUIDE, 'view'),
            'can_target_create' => $this->can(self::PAGE_DASHBOARD, 'create'),
            'settings_csrf' => $this->csrf_token('settings'),
            'test_csrf' => $this->csrf_token('test_send'),
            'bot_check_csrf' => $this->csrf_token('bot_check'),
            'target_discovery_csrf' => $this->csrf_token('target_discovery'),
            'discovered_target_save_csrf' => $this->csrf_token('discovered_target_save'),
            'webhook_install_csrf' => $this->csrf_token('webhook_install'),
            'webhook_check_csrf' => $this->csrf_token('webhook_check'),
            'test_request_id' => bin2hex(random_bytes(16)),
        ]);
    }

    public function notification_settings()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        if (!$this->require_post_csrf('settings')) return;
        $this->load->model('Module_notification_model');
        try {
            if ($this->input->post('action', true) === 'retry') {
                $this->Module_notification_model->retry('TELEGRAM', (int)$this->input->post('queue_id', true));
            } else {
                $this->Module_notification_model->save_rules('TELEGRAM', (array)$this->input->post('notifications'), $this->actor_id());
            }
            $this->session->set_flashdata('success', 'Pengaturan / antrean notifikasi Telegram tersimpan.');
        } catch (InvalidArgumentException | RuntimeException $error) {
            $this->session->set_flashdata('error', $error->getMessage());
        }
        redirect('telegram/settings');
    }

    public function setup_check_bot()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('bot_check')) return;
        $this->session->set_flashdata('tg_bot_check', $this->telegram_client->get_me());
        redirect('telegram/settings');
    }

    public function setup_discover_targets()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('target_discovery')) return;
        $result = $this->telegram_client->discover_targets();
        if (empty($result['ok'])) {
            $this->session->unset_userdata(self::DISCOVERY_SESSION_KEY);
            $this->session->set_flashdata('error', (string)($result['error'] ?? 'Discovery target Telegram gagal.'));
            redirect('telegram/settings');
            return;
        }
        $candidates = [];
        foreach (array_slice((array)($result['targets'] ?? []), 0, 20) as $candidate) {
            $key = bin2hex(random_bytes(16));
            $candidates[$key] = $candidate;
        }
        $this->session->set_userdata(self::DISCOVERY_SESSION_KEY, ['expires_at'=>time()+self::DISCOVERY_TTL_SECONDS,'candidates'=>$candidates]);
        $this->session->set_flashdata($candidates ? 'success' : 'error', $candidates ? 'Target Telegram ditemukan. Pilih target untuk disimpan.' : 'Belum ada group/channel yang dapat ditemukan.');
        redirect('telegram/settings');
    }

    public function setup_save_discovered_target()
    {
        $this->require_permission(self::PAGE_DASHBOARD, 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('discovered_target_save')) return;
        $key = strtolower(trim((string)$this->input->post('candidate_key', true)));
        $state = $this->discovery_session();
        $candidate = preg_match('/^[0-9a-f]{32}$/D', $key) === 1 ? ($state['candidates'][$key] ?? null) : null;
        if (isset($state['candidates'][$key])) {
            unset($state['candidates'][$key]);
            $this->session->set_userdata(self::DISCOVERY_SESSION_KEY, $state);
        }
        $safe = is_array($candidate) ? TelegramBotClient::sanitize_discovery_updates([['message'=>['chat'=>[
            'id'=>$candidate['chat_id'] ?? null,'type'=>strtolower((string)($candidate['target_type'] ?? '')),'title'=>$candidate['title'] ?? '',
        ]]]]) : [];
        if (count($safe) !== 1 || $safe[0] !== $candidate) {
            $this->session->set_flashdata('error', 'Pilihan discovery tidak valid atau sudah kedaluwarsa.');
            redirect('telegram/settings');
            return;
        }
        $ok = $this->Telegram_model->save_discovered_target($candidate, $this->actor_id());
        $this->session->set_flashdata($ok?'success':'error', $ok?'Target discovery disimpan aktif.':'Target discovery gagal disimpan.');
        redirect('telegram/settings');
    }

    public function setup_install_webhook()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        $this->require_schema();
        if (!$this->require_post_csrf('webhook_install')) return;
        if (!$this->telegram_client->is_configured() || !$this->telegram_client->is_secret_configured()
            || $this->telegram_client->configured_webhook_url() === '' || !$this->Telegram_model->is_enabled()
            || $this->Telegram_model->active_target_count() < 1) {
            $this->session->set_flashdata('error', 'Webhook memerlukan konfigurasi valid, master switch ON, dan minimal satu target aktif.');
            redirect('telegram/settings');
            return;
        }
        $result = $this->telegram_client->install_webhook();
        $this->session->set_flashdata(!empty($result['ok'])?'success':'error', !empty($result['ok'])?'Webhook Telegram terpasang.':(string)$result['error']);
        redirect('telegram/settings');
    }

    public function setup_check_webhook()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('webhook_check')) return;
        $this->session->set_flashdata('tg_webhook_check', $this->telegram_client->get_webhook_info());
        redirect('telegram/settings');
    }

    public function test_send()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'create');
        $this->require_schema();
        if (!$this->require_post_csrf('test_send')) {
            return;
        }
        $targetId = (int)$this->input->post('target_id', true);
        $target = $this->Telegram_model->target($targetId);
        $message = trim((string)$this->input->post('message', true));
        $requestId = strtolower(trim((string)$this->input->post('request_id', true)));
        if (!$target || empty($target['is_active']) || $message === '' || strlen($message) > 1000
            || preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1) {
            $this->session->set_flashdata('error', 'Target aktif, pesan test, atau request ID tidak valid.');
            redirect('telegram/settings');
            return;
        }

        $queueId = $this->Telegram_model->enqueue_test($targetId, $message, 'test:' . $requestId, $this->actor_id());
        $result = $queueId > 0 ? $this->process_queue_id($queueId) : ['processed' => false, 'status' => 'FAILED'];
        $status = (string)($result['status'] ?? 'FAILED');
        if (!empty($result['processed']) && $status === 'SENT') {
            $this->session->set_flashdata('success', 'Pesan test Telegram terkirim.');
        } elseif ($status === 'UNKNOWN') {
            $this->session->set_flashdata('error', 'Pengiriman timeout dan berstatus UNKNOWN; tidak akan dicoba ulang otomatis.');
        } else {
            $this->session->set_flashdata('error', 'Pesan test belum terkirim. Periksa log delivery.');
        }
        redirect('telegram/settings');
    }

    /** CLI cron: php index.php telegram run_due */
    public function run_due()
    {
        if (!$this->require_cli()) {
            return;
        }
        if (!$this->Telegram_model->ready() || !$this->Telegram_model->is_enabled()) {
            echo json_encode(['ok' => false, 'message' => 'Telegram belum siap atau dinonaktifkan.']) . PHP_EOL;
            return;
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
        $enqueued = $this->Telegram_model->enqueue_due_schedules($now);
        $processed = $this->process_queue_batch(50);
        $moduleNotifications = $this->run_module_notifications();
        echo json_encode(['ok' => true, 'enqueued' => $enqueued, 'processed' => $processed, 'module_notifications' => $moduleNotifications], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    /** CLI worker: php index.php telegram process_queue */
    public function process_queue()
    {
        if (!$this->require_cli()) {
            return;
        }
        if (!$this->Telegram_model->ready() || !$this->Telegram_model->is_enabled()) {
            echo json_encode(['ok' => false, 'message' => 'Telegram belum siap atau dinonaktifkan.']) . PHP_EOL;
            return;
        }
        echo json_encode(['ok' => true, 'processed' => $this->process_queue_batch(50), 'module_notifications' => $this->run_module_notifications()], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    private function run_module_notifications(): array
    {
        $this->load->model('Module_notification_model');
        try {
            return $this->Module_notification_model->run('TELEGRAM', function (array $row): array {
                return $this->telegram_client->send_message($row['destination'], $row['message_text']);
            });
        } catch (Throwable $error) {
            log_message('error', 'Module notification Telegram worker failed; queue evidence retained.');
            return ['state' => 'ERROR'];
        }
    }

    private function process_queue_batch(int $limit): int
    {
        $processed = 0;
        for ($i = 0; $i < $limit; $i++) {
            $queue = $this->Telegram_model->claim_queue(120);
            if (!$queue) {
                break;
            }
            $this->deliver_claimed_queue($queue);
            $processed++;
        }
        return $processed;
    }

    private function process_queue_id(int $queueId): array
    {
        $queue = $this->Telegram_model->claim_queue_by_id($queueId);
        if (!$queue) {
            return ['processed' => false, 'status' => 'DUPLICATE'];
        }
        $status = $this->deliver_claimed_queue($queue);
        return ['processed' => true, 'status' => $status];
    }

    private function deliver_claimed_queue(array $queue): string
    {
        $message = trim((string)($queue['message_text'] ?? ''));
        if ($message === '') {
            $message = $this->telegram_report->build(
                (string)($queue['report_type'] ?? ''),
                (string)($queue['report_date'] ?? date('Y-m-d'))
            );
        }
        if (empty($queue['target_active'])) {
            $result = ['status' => 'FAILED', 'ok' => false, 'http_code' => 0, 'message_id' => null, 'error' => 'Target Telegram sudah tidak aktif.'];
        } else {
            $result = $this->telegram_client->send_message((string)$queue['chat_id'], $message);
        }
        $this->Telegram_model->finalize_queue($queue, $result, $message);
        return (string)$result['status'];
    }

    private function require_schema(): void
    {
        if (!$this->Telegram_model->ready()) {
            show_error('Migration Telegram 2026-09-05a belum diterapkan.', 503, 'Telegram Belum Siap');
        }
    }

    private function require_cli(): bool
    {
        if ($this->input->is_cli_request()) {
            return true;
        }
        show_error('Endpoint ini hanya tersedia melalui CLI.', 404);
        return false;
    }

    private function actor_id(): int
    {
        return (int)($this->current_user['id'] ?? 0);
    }

    private function discovery_session(): array
    {
        $state = $this->session->userdata(self::DISCOVERY_SESSION_KEY);
        if (!is_array($state) || (int)($state['expires_at'] ?? 0) <= time() || !is_array($state['candidates'] ?? null)) {
            $this->session->unset_userdata(self::DISCOVERY_SESSION_KEY);
            return [];
        }
        return $state;
    }

    private function discovery_rows(array $state): array
    {
        $rows = [];
        foreach ((array)($state['candidates'] ?? []) as $key => $candidate) {
            if (preg_match('/^[0-9a-f]{32}$/D', (string)$key) !== 1 || !is_array($candidate)) continue;
            $rows[] = ['candidate_key'=>$key,'chat_id'=>$candidate['chat_id'],'target_type'=>$candidate['target_type'],'title'=>$candidate['title']];
        }
        return $rows;
    }

    private function csrf_token(string $scope): string
    {
        $key = 'tg_' . $scope . '_csrf';
        $token = (string)$this->session->userdata($key);
        if (preg_match('/^[0-9a-f]{64}$/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata($key, $token);
        }
        return $token;
    }

    private function require_post_csrf(string $scope): bool
    {
        if ($this->input->method(true) !== 'POST') {
            show_error('Metode request tidak diizinkan.', 405);
            return false;
        }
        $expected = $this->csrf_token($scope);
        $provided = trim((string)$this->input->post('tg_' . $scope . '_csrf', true));
        if ($provided === '' || !hash_equals($expected, $provided)) {
            show_error('Permintaan Telegram tidak valid.', 403, 'CSRF Ditolak');
            return false;
        }
        return true;
    }
}
