<?php

declare(strict_types=1);

/**
 * Codex completion notification for the Finance Telegram group.
 *
 * Only the final assistant message is used. User prompts and intermediate tool
 * output are deliberately ignored. The summary is redacted and length-limited
 * before it leaves the server.
 */

function finance_codex_notify_limit(string $value, int $limit): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') <= $limit
            ? $value
            : rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . '…';
    }

    return strlen($value) <= $limit
        ? $value
        : rtrim(substr($value, 0, $limit - 3)) . '...';
}

function finance_codex_notify_summary(array $event): string
{
    $summary = $event['last-assistant-message'] ?? '';
    if (!is_string($summary) || trim($summary) === '') {
        return 'Tugas selesai. Detail hasil tersedia di thread Finance.';
    }

    $replacements = [
        '#/codex(?:@[A-Za-z0-9_]+)?\s+pair\s+[A-Za-z0-9_-]{20,}#i' => '/codex pair [kode pribadi disembunyikan]',
        '/-----BEGIN [^-]+PRIVATE KEY-----.*?-----END [^-]+PRIVATE KEY-----/si' => '[private key disembunyikan]',
        '/```.*?```/s' => '[blok kode disembunyikan]',
        '/\b[0-9]{8,12}:[A-Za-z0-9_-]{30,}\b/' => '[token Telegram disembunyikan]',
        '/\bsk-[A-Za-z0-9_-]{20,}\b/' => '[API key disembunyikan]',
        '/\b([A-Z0-9_]*(?:TOKEN|PASSWORD|PASSWD|SECRET|API_KEY|APIKEY)[A-Z0-9_]*)\s*([:=])\s*[^\s,;]+/i' => '$1$2[disembunyikan]',
        '/\[([^\]]+)\]\([^\s)]+\)/' => '$1',
        '#https?://[^\s<>]+#i' => '[tautan disembunyikan]',
    ];
    $summary = (string) preg_replace(array_keys($replacements), array_values($replacements), $summary);
    $summary = strip_tags($summary);
    $summary = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $summary);
    $summary = (string) preg_replace('/^#{1,6}\s*/m', '', $summary);
    $summary = str_replace(['**', '__', '`'], '', $summary);
    $summary = (string) preg_replace('/[ \t]+$/m', '', $summary);
    $summary = (string) preg_replace('/\n{3,}/', "\n\n", $summary);

    $lines = preg_split('/\R/u', trim($summary)) ?: [];
    $lines = array_slice($lines, 0, 18);
    $summary = trim(implode("\n", $lines));

    return $summary !== ''
        ? finance_codex_notify_limit($summary, 2400)
        : 'Tugas selesai. Detail hasil tersedia di thread Finance.';
}

function finance_codex_notify_build_message(array $event): string
{
    return "✅ Tugas Codex Finance selesai.\n"
        . 'Waktu: ' . date('Y-m-d H:i:s T') . "\n\n"
        . "Ringkasan hasil:\n"
        . finance_codex_notify_summary($event)
        . "\n\nDetail lengkap tetap tersedia di thread Finance.";
}

function finance_codex_notify_main(array $argv): int
{
    $event = isset($argv[1]) ? json_decode((string) $argv[1], true) : null;
    if (!is_array($event) || ($event['type'] ?? '') !== 'agent-turn-complete') {
        return 0;
    }

    $envPath = '/var/lib/finance-telegram/runtime.env';
    $chatPath = '/var/lib/finance-telegram/codex_chat_id';
    $logPath = '/var/log/finance-codex-notify.log';
    $env = is_readable($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : false;
    $chatId = is_readable($chatPath) ? trim((string) file_get_contents($chatPath)) : '';
    $token = is_array($env) ? trim((string) ($env['FINANCE_TELEGRAM_BOT_TOKEN'] ?? '')) : '';

    if (!preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/D', $token)
        || !preg_match('/^-[1-9][0-9]{0,18}$/D', $chatId)
        || !function_exists('curl_init')) {
        error_log(date('c') . " notification skipped: runtime configuration incomplete\n", 3, $logPath);
        return 0;
    }

    $body = json_encode([
        'chat_id' => $chatId,
        'text' => finance_codex_notify_build_message($event),
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($body)) {
        return 0;
    }

    $curl = curl_init('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage');
    if ($curl === false) {
        return 0;
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    $decoded = is_string($response) ? json_decode($response, true) : null;
    $ok = $curlError === 0 && $httpCode >= 200 && $httpCode < 300
        && is_array($decoded) && ($decoded['ok'] ?? false) === true;
    error_log(date('c') . ($ok ? " notification sent\n" : " notification failed\n"), 3, $logPath);

    return 0;
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(finance_codex_notify_main($argv));
}
