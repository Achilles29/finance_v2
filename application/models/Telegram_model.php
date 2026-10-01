<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_model extends CI_Model
{
    private const TABLES = [
        'tg_target',
        'tg_schedule',
        'tg_webhook_update',
        'tg_delivery_queue',
        'tg_delivery_log',
        'tg_setting',
    ];

    public function ready(): bool
    {
        foreach (self::TABLES as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }
        return true;
    }

    public function is_enabled(): bool
    {
        return $this->setting('telegram.enabled', '0') === '1';
    }

    public function setting(string $key, string $default = ''): string
    {
        if (!$this->db->table_exists('tg_setting')) {
            return $default;
        }
        $row = $this->db->select('setting_value')->from('tg_setting')
            ->where('setting_key', $key)->limit(1)->get()->row_array();
        return $row ? (string)$row['setting_value'] : $default;
    }

    public function save_enabled(bool $enabled, int $actorId): bool
    {
        $sql = 'INSERT INTO tg_setting (setting_key, setting_value, description, updated_by, updated_at) '
            . "VALUES ('telegram.enabled', ?, 'Master switch Telegram bot', ?, NOW()) "
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()';
        return (bool)$this->db->query($sql, [$enabled ? '1' : '0', $actorId > 0 ? $actorId : null]);
    }

    public function targets(bool $activeOnly = false): array
    {
        $query = $this->db->from('tg_target');
        if ($activeOnly) {
            $query->where('is_active', 1);
        }
        return $query->order_by('is_active', 'DESC')->order_by('title', 'ASC')->order_by('id', 'ASC')
            ->get()->result_array();
    }

    public function target(int $id): ?array
    {
        $row = $this->db->from('tg_target')->where('id', $id)->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function active_target_count(): int
    {
        return (int)$this->db->from('tg_target')->where('is_active', 1)->count_all_results();
    }

    public function save_discovered_target(array $candidate, int $actorId): bool
    {
        $sql = 'INSERT INTO tg_target (chat_id, target_type, title, is_active, created_by, updated_by, created_at, updated_at) '
            . 'VALUES (?, ?, ?, 1, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE target_type = VALUES(target_type), '
            . 'title = VALUES(title), is_active = 1, updated_by = VALUES(updated_by), updated_at = NOW()';
        $actor = $actorId > 0 ? $actorId : null;
        return (bool)$this->db->query($sql, [
            (string)$candidate['chat_id'], (string)$candidate['target_type'], (string)$candidate['title'], $actor, $actor,
        ]);
    }

    public function active_target_by_chat(string $chatId, string $chatType = ''): ?array
    {
        $query = $this->db->from('tg_target')->where('chat_id', $chatId)->where('is_active', 1);
        if ($chatType !== '') {
            $query->where('target_type', strtoupper($chatType));
        }
        $row = $query->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function save_target(array $data, int $id, int $actorId): bool
    {
        $payload = [
            'chat_id' => (string)$data['chat_id'],
            'target_type' => (string)$data['target_type'],
            'title' => (string)$data['title'],
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'updated_by' => $actorId > 0 ? $actorId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            return (bool)$this->db->where('id', $id)->update('tg_target', $payload);
        }
        $payload['created_by'] = $actorId > 0 ? $actorId : null;
        $payload['created_at'] = date('Y-m-d H:i:s');
        return (bool)$this->db->insert('tg_target', $payload);
    }

    public function schedules(): array
    {
        return $this->db->select('s.*, t.title AS target_title, t.chat_id, t.is_active AS target_active')
            ->from('tg_schedule s')->join('tg_target t', 't.id = s.target_id')
            ->order_by('s.is_active', 'DESC')->order_by('s.send_time', 'ASC')->order_by('s.id', 'ASC')
            ->get()->result_array();
    }

    public function schedule(int $id): ?array
    {
        $row = $this->db->from('tg_schedule')->where('id', $id)->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function save_schedule(array $data, int $id, int $actorId): bool
    {
        $payload = [
            'target_id' => (int)$data['target_id'],
            'schedule_name' => (string)$data['schedule_name'],
            'report_type' => (string)$data['report_type'],
            'send_time' => (string)$data['send_time'],
            'timezone' => 'Asia/Jakarta',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'updated_by' => $actorId > 0 ? $actorId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            return (bool)$this->db->where('id', $id)->update('tg_schedule', $payload);
        }
        $payload['created_by'] = $actorId > 0 ? $actorId : null;
        $payload['created_at'] = date('Y-m-d H:i:s');
        return (bool)$this->db->insert('tg_schedule', $payload);
    }

    /**
     * Stores an accepted update and its delivery atomically. update_id and the
     * queue idempotency key independently prevent duplicate delivery.
     */
    public function accept_webhook_command(
        int $updateId,
        array $target,
        string $command,
        string $reportType,
        string $reportDate,
        string $payloadHash
    ): array {
        $this->db->trans_begin();
        $inserted = $this->db->query(
            'INSERT IGNORE INTO tg_webhook_update '
            . '(update_id, target_id, chat_id, command_name, payload_sha256, received_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$updateId, (int)$target['id'], (string)$target['chat_id'], $command, $payloadHash]
        );
        if (!$inserted || $this->db->affected_rows() !== 1) {
            $this->db->trans_commit();
            return ['ok' => true, 'duplicate' => true, 'queue_id' => 0];
        }

        $queueId = $this->enqueue_internal([
            'idempotency_key' => 'webhook:' . $updateId . ':' . $command,
            'source_type' => 'COMMAND',
            'source_ref' => (string)$updateId,
            'target_id' => (int)$target['id'],
            'report_type' => $reportType,
            'report_date' => $reportDate,
            'message_text' => null,
            'max_attempts' => 3,
        ]);
        if ($queueId <= 0 || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'duplicate' => false, 'queue_id' => 0];
        }
        $this->db->where('update_id', $updateId)->update('tg_webhook_update', ['queue_id' => $queueId]);
        $this->db->trans_commit();
        return ['ok' => true, 'duplicate' => false, 'queue_id' => $queueId];
    }

    public function enqueue_test(int $targetId, string $message, string $idempotencyKey, int $actorId): int
    {
        return $this->enqueue_internal([
            'idempotency_key' => $idempotencyKey,
            'source_type' => 'TEST',
            'source_ref' => $actorId > 0 ? (string)$actorId : null,
            'target_id' => $targetId,
            'report_type' => null,
            'report_date' => null,
            'message_text' => $message,
            'max_attempts' => 1,
        ]);
    }

    public function enqueue_due_schedules(DateTimeImmutable $now): int
    {
        $today = $now->format('Y-m-d');
        $rows = $this->db->select('s.*')->from('tg_schedule s')
            ->join('tg_target t', 't.id = s.target_id AND t.is_active = 1')
            ->where('s.is_active', 1)
            ->where('s.send_time <=', $now->format('H:i:s'))
            ->group_start()->where('s.last_enqueued_date IS NULL', null, false)->or_where('s.last_enqueued_date <', $today)->group_end()
            ->order_by('s.id', 'ASC')->get()->result_array();

        $count = 0;
        foreach ($rows as $row) {
            $key = 'schedule:' . (int)$row['id'] . ':' . $today;
            $queueId = $this->enqueue_internal([
                'idempotency_key' => $key,
                'source_type' => 'SCHEDULE',
                'source_ref' => (string)(int)$row['id'],
                'target_id' => (int)$row['target_id'],
                'report_type' => (string)$row['report_type'],
                'report_date' => $today,
                'message_text' => null,
                'max_attempts' => 3,
            ]);
            if ($queueId > 0) {
                $this->db->where('id', (int)$row['id'])
                    ->group_start()->where('last_enqueued_date IS NULL', null, false)->or_where('last_enqueued_date <', $today)->group_end()
                    ->update('tg_schedule', ['last_enqueued_date' => $today, 'last_queue_id' => $queueId, 'updated_at' => date('Y-m-d H:i:s')]);
                if ($this->db->affected_rows() === 1) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function enqueue_internal(array $data): int
    {
        $sql = 'INSERT INTO tg_delivery_queue '
            . '(idempotency_key, source_type, source_ref, target_id, report_type, report_date, message_text, status, attempt_count, max_attempts, available_at, created_at, updated_at) '
            . "VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', 0, ?, NOW(), NOW(), NOW()) "
            . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $ok = $this->db->query($sql, [
            (string)$data['idempotency_key'],
            (string)$data['source_type'],
            $data['source_ref'],
            (int)$data['target_id'],
            $data['report_type'],
            $data['report_date'],
            $data['message_text'],
            (int)$data['max_attempts'],
        ]);
        return $ok ? (int)$this->db->insert_id() : 0;
    }

    /** Atomic single-row lease; stale workers cannot finalize another lease. */
    public function claim_queue(int $leaseSeconds = 120): ?array
    {
        $leaseSeconds = max(30, min(600, $leaseSeconds));
        $token = bin2hex(random_bytes(16));
        $expiredAt = date('Y-m-d H:i:s', time() - $leaseSeconds);
        $sql = "UPDATE tg_delivery_queue SET status = 'PROCESSING', lease_token = ?, leased_at = NOW(), "
            . 'attempt_count = attempt_count + 1, updated_at = NOW() '
            . "WHERE attempt_count < max_attempts AND ((status = 'PENDING' AND available_at <= NOW()) "
            . "OR (status = 'PROCESSING' AND leased_at < ?)) ORDER BY id ASC LIMIT 1";
        if (!$this->db->query($sql, [$token, $expiredAt]) || $this->db->affected_rows() !== 1) {
            return null;
        }

        $row = $this->db->select('q.*, t.chat_id, t.title AS target_title, t.is_active AS target_active')
            ->from('tg_delivery_queue q')->join('tg_target t', 't.id = q.target_id')
            ->where('q.lease_token', $token)->where('q.status', 'PROCESSING')->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function claim_queue_by_id(int $queueId): ?array
    {
        if ($queueId <= 0) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $sql = "UPDATE tg_delivery_queue SET status = 'PROCESSING', lease_token = ?, leased_at = NOW(), "
            . 'attempt_count = attempt_count + 1, updated_at = NOW() '
            . "WHERE id = ? AND status = 'PENDING' AND available_at <= NOW() AND attempt_count < max_attempts";
        if (!$this->db->query($sql, [$token, $queueId]) || $this->db->affected_rows() !== 1) {
            return null;
        }
        $row = $this->db->select('q.*, t.chat_id, t.title AS target_title, t.is_active AS target_active')
            ->from('tg_delivery_queue q')->join('tg_target t', 't.id = q.target_id')
            ->where('q.id', $queueId)->where('q.lease_token', $token)->where('q.status', 'PROCESSING')
            ->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function finalize_queue(array $queue, array $result, string $renderedMessage): bool
    {
        $queueId = (int)($queue['id'] ?? 0);
        $leaseToken = (string)($queue['lease_token'] ?? '');
        $status = strtoupper((string)($result['status'] ?? 'FAILED'));
        if ($queueId <= 0 || $leaseToken === '' || !in_array($status, ['SENT', 'FAILED', 'UNKNOWN'], true)) {
            return false;
        }

        $attempt = (int)($queue['attempt_count'] ?? 0);
        $maxAttempts = (int)($queue['max_attempts'] ?? 1);
        $nextStatus = $status;
        $availableAt = null;
        if ($status === 'FAILED' && $attempt < $maxAttempts) {
            $nextStatus = 'PENDING';
            $availableAt = date('Y-m-d H:i:s', time() + min(300, 30 * max(1, $attempt)));
        }

        $payload = [
            'status' => $nextStatus,
            'lease_token' => null,
            'leased_at' => null,
            'last_error' => $status === 'SENT' ? null : mb_substr((string)($result['error'] ?? 'Gagal mengirim.'), 0, 500, 'UTF-8'),
            'http_code' => (int)($result['http_code'] ?? 0),
            'telegram_message_id' => $result['message_id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($availableAt !== null) {
            $payload['available_at'] = $availableAt;
        }
        if ($status === 'SENT') {
            $payload['sent_at'] = date('Y-m-d H:i:s');
        }

        $this->db->trans_begin();
        $this->db->where('id', $queueId)->where('lease_token', $leaseToken)->where('status', 'PROCESSING')
            ->update('tg_delivery_queue', $payload);
        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->insert('tg_delivery_log', [
            'queue_id' => $queueId,
            'target_id' => (int)$queue['target_id'],
            'attempt_no' => $attempt,
            'delivery_status' => $status,
            'http_code' => (int)($result['http_code'] ?? 0),
            'telegram_message_id' => $result['message_id'] ?? null,
            'message_preview' => mb_substr($renderedMessage, 0, 500, 'UTF-8'),
            'error_message' => $status === 'SENT' ? null : mb_substr((string)($result['error'] ?? ''), 0, 500, 'UTF-8'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return true;
    }

    /**
     * Gives one terminal UNKNOWN queue an audited manual disposition. RESEND
     * creates a separate queue identity; the ambiguous attempt is never reused.
     */
    public function resolve_unknown(
        int $queueId,
        string $action,
        string $reason,
        int $actorId,
        string $requestId
    ): array {
        $action = strtoupper(trim($action));
        $reason = trim($reason);
        if ($queueId <= 0 || $actorId <= 0 || !in_array($action, ['CONFIRM_SENT', 'CLOSE_FAILED', 'RESEND'], true)
            || $reason === '' || mb_strlen($reason, 'UTF-8') > 500
            || preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1) {
            return ['ok' => false, 'message' => 'Parameter resolusi UNKNOWN tidak valid.', 'queue_id' => 0];
        }

        $this->db->trans_begin();
        $queue = $this->db->query(
            "SELECT * FROM tg_delivery_queue WHERE id = ? AND status = 'UNKNOWN' FOR UPDATE",
            [$queueId]
        )->row_array();
        if (!$queue || !empty($queue['resolution_action'])) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Queue bukan UNKNOWN aktif atau sudah diselesaikan.', 'queue_id' => 0];
        }

        $resolutionQueueId = null;
        if ($action === 'RESEND') {
            $resolutionQueueId = $this->enqueue_internal([
                'idempotency_key' => 'manual-resend:' . $queueId . ':' . $requestId,
                'source_type' => 'RESEND',
                'source_ref' => (string)$queueId,
                'target_id' => (int)$queue['target_id'],
                'report_type' => $queue['report_type'],
                'report_date' => $queue['report_date'],
                'message_text' => $queue['message_text'],
                'max_attempts' => max(1, (int)$queue['max_attempts']),
            ]);
            if ($resolutionQueueId <= 0) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Antrean resend gagal dibuat.', 'queue_id' => 0];
            }
        }

        $payload = [
            'resolution_action' => $action,
            'resolution_reason' => $reason,
            'resolved_by' => $actorId,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution_queue_id' => $resolutionQueueId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($action === 'CONFIRM_SENT') {
            $payload['status'] = 'SENT';
            $payload['sent_at'] = date('Y-m-d H:i:s');
        } elseif ($action === 'CLOSE_FAILED') {
            $payload['status'] = 'FAILED';
        }

        $this->db->where('id', $queueId)->where('status', 'UNKNOWN')
            ->where('resolution_action IS NULL', null, false)->update('tg_delivery_queue', $payload);
        if ($this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Status UNKNOWN berubah saat diproses.', 'queue_id' => 0];
        }
        $this->db->trans_commit();
        return ['ok' => true, 'message' => '', 'queue_id' => (int)($resolutionQueueId ?? $queueId)];
    }

    public function queue_rows(int $limit = 100): array
    {
        return $this->db->select('q.*, t.title AS target_title, t.chat_id')
            ->from('tg_delivery_queue q')->join('tg_target t', 't.id = q.target_id')
            ->order_by('q.id', 'DESC')->limit(max(1, min(200, $limit)))->get()->result_array();
    }

    public function log_rows(int $limit = 200): array
    {
        return $this->db->select('l.*, t.title AS target_title, q.source_type, q.report_type, q.status AS queue_status, '
                . 'q.resolution_action, q.resolution_reason, q.resolved_by, q.resolved_at, q.resolution_queue_id, '
                . 'resolver.username AS resolved_by_username')
            ->from('tg_delivery_log l')->join('tg_target t', 't.id = l.target_id')
            ->join('tg_delivery_queue q', 'q.id = l.queue_id')
            ->join('auth_user resolver', 'resolver.id = q.resolved_by', 'left')
            ->order_by('l.id', 'DESC')->limit(max(1, min(500, $limit)))->get()->result_array();
    }

    public function dashboard_stats(): array
    {
        $count = function (string $table, array $where = []): int {
            $query = $this->db->from($table);
            foreach ($where as $field => $value) {
                $query->where($field, $value);
            }
            return (int)$query->count_all_results();
        };
        return [
            'active_targets' => $count('tg_target', ['is_active' => 1]),
            'active_schedules' => $count('tg_schedule', ['is_active' => 1]),
            'pending_queue' => $count('tg_delivery_queue', ['status' => 'PENDING']),
            'unknown_queue' => (int)$this->db->from('tg_delivery_queue')
                ->where('status', 'UNKNOWN')->where('resolution_action IS NULL', null, false)->count_all_results(),
        ];
    }
}
