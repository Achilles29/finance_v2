<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/telegram/codex_notify.php';

$failures = [];
$checks = 0;
$check = static function (bool $condition, string $label) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $label;
        echo 'FAIL: ' . $label . PHP_EOL;
        return;
    }
    echo 'PASS: ' . $label . PHP_EOL;
};

$telegramToken = '1234567890:' . str_repeat('A', 35);
$event = [
    'type' => 'agent-turn-complete',
    'input-messages' => ['PROMPT_RAHASIA_TIDAK_BOLEH_TERKIRIM'],
    'last-assistant-message' => "## Selesai\n\n- Login sudah diperbaiki.\n- Laporan: [buka](https://internal.example.test/private).\nTOKEN={$telegramToken}\nPASSWORD=super-secret\n```\nAPI_KEY=hidden-in-code\n```",
];
$summary = finance_codex_notify_summary($event);
$message = finance_codex_notify_build_message($event);

$check(strpos($summary, 'Login sudah diperbaiki.') !== false, 'final result is included');
$check(strpos($summary, 'PROMPT_RAHASIA') === false, 'user prompt is never included');
$check(strpos($summary, $telegramToken) === false, 'Telegram token is redacted');
$check(strpos($summary, 'super-secret') === false, 'password assignment is redacted');
$check(strpos($summary, 'hidden-in-code') === false, 'fenced code is removed');
$check(strpos($summary, 'internal.example.test') === false, 'link target is removed');
$check(strpos($message, 'Ringkasan hasil:') !== false, 'notification has a user-facing summary heading');
$check(strlen($message) < 4096, 'message remains below Telegram text limit');
$pairCode = str_repeat('Q', 32);
$check(strpos(finance_codex_notify_summary(['last-assistant-message' => '/codex pair ' . $pairCode]), $pairCode) === false,
    'private owner pairing code never leaks into the group completion summary');
$check(
    finance_codex_notify_summary(['last-assistant-message' => '']) === 'Tugas selesai. Detail hasil tersedia di thread Finance.',
    'empty final response uses a safe fallback'
);

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d of %d checks failed.\n", count($failures), $checks));
    exit(1);
}

echo sprintf("All %d Codex Telegram summary checks passed.\n", $checks);
