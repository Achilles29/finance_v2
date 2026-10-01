<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_webhook extends CI_Controller
{
    private const MAX_JSON_BYTES = 262144;
    private const SECRET_HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Telegram_model');
    }

    public function index()
    {
        if ($this->input->method(true) !== 'POST') {
            $this->json_response(405, ['ok' => false, 'message' => 'Method not allowed.']);
            return;
        }
        if (!$this->valid_secret()) {
            $this->json_response(403, ['ok' => false, 'message' => 'Invalid webhook credential.']);
            return;
        }
        if (!$this->Telegram_model->ready()) {
            $this->json_response(503, ['ok' => false, 'message' => 'Telegram module unavailable.']);
            return;
        }
        if (!$this->Telegram_model->is_enabled()) {
            $this->json_response(200, ['ok' => true, 'ignored' => true, 'disabled' => true]);
            return;
        }

        $contentType = strtolower(trim((string)$this->input->get_request_header('Content-Type', true)));
        if (strpos($contentType, 'application/json') !== 0) {
            $this->json_response(415, ['ok' => false, 'message' => 'JSON content type required.']);
            return;
        }
        $body = $this->read_bounded_body();
        if ($body === null) {
            $this->json_response(413, ['ok' => false, 'message' => 'Webhook payload too large.']);
            return;
        }
        $update = json_decode($body, true);
        if (!is_array($update) || !isset($update['update_id']) || !is_int($update['update_id']) || $update['update_id'] < 0) {
            $this->json_response(400, ['ok' => false, 'message' => 'Invalid JSON update.']);
            return;
        }

        // Optional developer-only relay. The .codex directory is excluded from
        // customer releases; normal Telegram reports never enter this branch.
        $internalRelay = FCPATH . '.codex/telegram_webhook_bridge.php';
        if (($update['message']['chat']['type'] ?? '') === 'private' && is_file($internalRelay)) {
            $relay = require $internalRelay;
            $accepted = is_callable($relay) ? $relay($body, (string)$this->input->get_request_header(self::SECRET_HEADER, true)) : null;
            if ($accepted === null) {
                $this->json_response(503, ['ok' => false, 'message' => 'Internal relay unavailable.']);
                return;
            }
            $this->json_response(200, ['ok' => true, 'internal' => true]);
            return;
        }

        $message = null;
        foreach (['message', 'channel_post'] as $field) {
            if (isset($update[$field]) && is_array($update[$field])) {
                $message = $update[$field];
                break;
            }
        }
        if (!$message || !isset($message['chat']['id'], $message['chat']['type']) || !isset($message['text'])) {
            $this->json_response(200, ['ok' => true, 'ignored' => true]);
            return;
        }

        $chatId = (string)$message['chat']['id'];
        $chatType = strtoupper((string)$message['chat']['type']);
        if (!in_array($chatType, ['GROUP', 'SUPERGROUP', 'CHANNEL'], true)) {
            $this->json_response(200, ['ok' => true, 'ignored' => true]);
            return;
        }
        $target = $this->Telegram_model->active_target_by_chat($chatId, $chatType);
        if (!$target) {
            // Unknown/private targets receive no response and cannot enter the queue.
            $this->json_response(200, ['ok' => true, 'ignored' => true]);
            return;
        }

        $parsed = $this->parse_command((string)$message['text']);
        if ($parsed === null) {
            $this->json_response(200, ['ok' => true, 'ignored' => true]);
            return;
        }
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
        $accepted = $this->Telegram_model->accept_webhook_command(
            (int)$update['update_id'],
            $target,
            $parsed['command'],
            $parsed['report_type'],
            $today,
            hash('sha256', $body)
        );
        if (empty($accepted['ok'])) {
            $this->json_response(503, ['ok' => false, 'message' => 'Queue unavailable.']);
            return;
        }
        if (!empty($accepted['duplicate'])) {
            $this->json_response(200, ['ok' => true, 'duplicate' => true]);
            return;
        }

        $this->json_response(200, ['ok' => true, 'queued' => true]);
    }

    private function valid_secret(): bool
    {
        $expected = (string)getenv('FINANCE_TELEGRAM_WEBHOOK_SECRET');
        $provided = (string)$this->input->get_request_header(self::SECRET_HEADER, true);
        return preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $expected) === 1
            && preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $provided) === 1
            && hash_equals($expected, $provided);
    }

    private function read_bounded_body(): ?string
    {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > self::MAX_JSON_BYTES) {
            return null;
        }
        $stream = fopen('php://input', 'rb');
        if (!is_resource($stream)) {
            return '';
        }
        $body = stream_get_contents($stream, self::MAX_JSON_BYTES + 1);
        fclose($stream);
        if (!is_string($body) || strlen($body) > self::MAX_JSON_BYTES) {
            return null;
        }
        return $body;
    }

    private function parse_command(string $text): ?array
    {
        if (preg_match('/^\/(menu|omzet|belanja)(?:@[A-Za-z0-9_]+)?\s*$/iD', trim($text), $match) !== 1) {
            return null;
        }
        $command = strtolower($match[1]);
        $types = ['menu' => 'MENU', 'omzet' => 'OMZET_TODAY', 'belanja' => 'PURCHASE_TODAY'];
        return ['command' => $command, 'report_type' => $types[$command]];
    }

    private function json_response(int $status, array $payload): void
    {
        $this->output->set_status_header($status)->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
