<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Short-lived, one-use reauthentication proof for high-impact web actions. */
class SensitiveActionStepUp
{
    private const SESSION_KEY = 'finance_sensitive_action_step_up';
    private const TTL_SECONDS = 180;
    private const FAILURE_WINDOW_SECONDS = 600;
    private const FAILURE_LIMIT = 5;
    private const ACTIONS = ['VOID', 'REFUND', 'PERIOD_REOPEN', 'ORDER_REPRINT', 'RESERVATION_REJECT_DEPOSIT_REFUND', 'RESERVATION_CANCEL_DEPOSIT_REFUND', 'COMPONENT_ADJUSTMENT_POST', 'COMPONENT_ADJUSTMENT_VOID', 'COMPONENT_BATCH_POST', 'COMPONENT_BATCH_VOID', 'COMPONENT_DAILY_RECON_POST', 'COMPONENT_FORMULA_RESTORE', 'STOCK_ADJUSTMENT_POST', 'STOCK_ADJUSTMENT_VOID', 'STOCK_TRANSFER_POST', 'STOCK_TRANSFER_VOID', 'STOCK_OPENING_POST', 'STOCK_OPENING_VOID', 'STOCK_OPENING_IMPORT'];

    /** @var CI_Controller */
    private $ci;
    /** @var callable */
    private $clock;

    public function __construct(array $options = [])
    {
        $this->ci =& get_instance();
        $this->clock = $options['clock'] ?? static function (): int { return time(); };
    }

    public function issue(int $userId, $action, $targetId, $password): array
    {
        $action = $this->action($action);
        $targetId = $this->positiveId($targetId);
        if ($userId <= 0 || $action === null || $targetId === null || !is_string($password) || $password === '' || strlen($password) > 72) {
            return ['ok' => false, 'status' => 422, 'message' => 'Data verifikasi ulang tidak valid.'];
        }

        $now = ($this->clock)();
        $state = $this->state();
        $failure = (array)($state['failure'] ?? []);
        if ((int)($failure['until'] ?? 0) > $now) {
            return ['ok' => false, 'status' => 429, 'message' => 'Terlalu banyak verifikasi gagal. Tunggu sebentar lalu coba lagi.'];
        }

        $row = $this->ci->db->select('id, password_hash')
            ->from('auth_user')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        $valid = is_array($row) && !empty($row['password_hash'])
            && password_verify($password, (string)$row['password_hash']);
        if (!$valid) {
            $this->recordFailure($state, $now);
            return ['ok' => false, 'status' => 403, 'message' => 'Verifikasi ulang tidak berhasil.'];
        }

        $proof = bin2hex(random_bytes(32));
        $this->ci->session->set_userdata(self::SESSION_KEY, [
            'user_id' => $userId,
            'action' => $action,
            'target_id' => $targetId,
            'proof_hash' => hash('sha256', $proof),
            'expires_at' => $now + self::TTL_SECONDS,
            'failure' => [],
        ]);
        return ['ok' => true, 'proof' => $proof, 'expires_in_seconds' => self::TTL_SECONDS];
    }

    public function consume(int $userId, $action, $targetId, $proof): array
    {
        $action = $this->action($action);
        $targetId = $this->positiveId($targetId);
        $state = $this->state();
        $now = ($this->clock)();
        $valid = $userId > 0 && $action !== null && $targetId !== null
            && is_string($proof) && preg_match('/\A[0-9a-f]{64}\z/D', $proof) === 1
            && (int)($state['user_id'] ?? 0) === $userId
            && (string)($state['action'] ?? '') === $action
            && (int)($state['target_id'] ?? 0) === $targetId
            && (int)($state['expires_at'] ?? 0) >= $now
            && is_string($state['proof_hash'] ?? null)
            && hash_equals((string)$state['proof_hash'], hash('sha256', $proof));
        if (!$valid) {
            return ['ok' => false, 'status' => 428, 'message' => 'Verifikasi ulang diperlukan sebelum aksi ini.'];
        }

        // One proof can authorize one exact action on one exact document only.
        $this->ci->session->unset_userdata(self::SESSION_KEY);
        return ['ok' => true];
    }

    private function state(): array
    {
        $state = $this->ci->session->userdata(self::SESSION_KEY);
        return is_array($state) ? $state : [];
    }

    private function recordFailure(array $state, int $now): void
    {
        $failure = (array)($state['failure'] ?? []);
        $windowStart = (int)($failure['window_start'] ?? 0);
        $count = $windowStart > 0 && $now - $windowStart < self::FAILURE_WINDOW_SECONDS
            ? (int)($failure['count'] ?? 0) + 1
            : 1;
        $state['failure'] = [
            'window_start' => $count === 1 ? $now : $windowStart,
            'count' => $count,
            'until' => $count >= self::FAILURE_LIMIT ? $now + self::FAILURE_WINDOW_SECONDS : 0,
        ];
        unset($state['proof_hash'], $state['user_id'], $state['action'], $state['target_id'], $state['expires_at']);
        $this->ci->session->set_userdata(self::SESSION_KEY, $state);
    }

    private function action($value): ?string
    {
        if (!is_scalar($value)) return null;
        $action = strtoupper(trim((string)$value));
        return in_array($action, self::ACTIONS, true) ? $action : null;
    }

    private function positiveId($value): ?int
    {
        if (is_bool($value) || is_float($value) || is_array($value) || is_object($value)) return null;
        $value = trim((string)$value);
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int)$id;
    }
}
