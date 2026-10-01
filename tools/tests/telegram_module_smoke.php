<?php

declare(strict_types=1);

/** DB/network/bootstrap-free contract smoke for the Telegram MVP. */
$root = dirname(__DIR__, 2);
$paths = [
    'controller' => 'application/controllers/Telegram.php',
    'webhook' => 'application/controllers/Telegram_webhook.php',
    'model' => 'application/models/Telegram_model.php',
    'client' => 'application/libraries/TelegramBotClient.php',
    'report' => 'application/libraries/TelegramReportService.php',
    'sql' => 'sql/2026-09-05a_telegram_bot_foundation.sql',
    'guide_sql' => 'sql/2026-09-05b_telegram_setup_guide.sql',
    'safe_sql' => 'sql/2026-09-05c_telegram_safe_activation_default.sql',
    'catalog' => 'tools/db/migration_catalog.json',
    'guide_view' => 'application/views/telegram/guide.php',
    'settings_view' => 'application/views/telegram/settings.php',
];
$source = [];
$failures = [];
$checks = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }
};

foreach ($paths as $key => $relative) {
    $contents = @file_get_contents($root . '/' . $relative);
    $check(is_string($contents) && $contents !== '', $relative . ' is readable');
    $source[$key] = is_string($contents) ? $contents : '';
}
$check(
    strpos($source['controller'], "private const PAGE_GUIDE = 'tg.guide';") !== false
        && strpos($source['controller'], 'function guide()') !== false
        && strpos($source['controller'], "require_permission(self::PAGE_GUIDE, 'view')") !== false
        && strpos($source['controller'], "render('telegram/guide'") !== false
        && strpos($source['controller'], "'active_menu' => 'tg.guide'") !== false
        && strpos($source['controller'], "'webhook_url' => \$this->telegram_client->configured_webhook_url()") !== false
        && strpos($source['controller'], "site_url('telegram_webhook')") === false,
    'guide endpoint is schema/RBAC guarded and receives the canonical webhook URL'
);
$guideMethodStart = (int)strpos($source['controller'], 'public function guide()');
$guideMethodEnd = (int)strpos($source['controller'], 'public function target_save()', $guideMethodStart);
$guideMethod = substr($source['controller'], $guideMethodStart, $guideMethodEnd - $guideMethodStart);
$check(strpos($guideMethod, '$this->require_schema();') !== false, 'guide endpoint requires Telegram schema readiness');

preg_match_all('/data-bs-toggle="pill"/', $source['guide_view'], $guideTabs);
preg_match_all('/data-bs-toggle="tab"/', $source['settings_view'], $settingsTabs);
$check(count($guideTabs[0] ?? []) === 3
    && strpos($source['guide_view'], 'Pemilik/Operator') !== false
    && strpos($source['guide_view'], 'Admin Server') !== false
    && strpos($source['guide_view'], 'Jika Bermasalah') !== false,
    'guide presents exactly three user-friendly role-based tabs');
$check(count($settingsTabs[0] ?? []) === 2
    && strpos($source['settings_view'], 'Saya pengguna aplikasi') !== false
    && strpos($source['settings_view'], 'Tugas admin server') !== false
    && strpos($source['settings_view'], 'Saya sudah membuat bot, lalu apa?') !== false,
    'settings presents a concise user-first setup assistant and a separate server-admin tab');
foreach (['setup_check_bot'=>'tg_bot_check_csrf','setup_discover_targets'=>'tg_target_discovery_csrf',
    'setup_save_discovered_target'=>'tg_discovered_target_save_csrf','setup_install_webhook'=>'tg_webhook_install_csrf',
    'setup_check_webhook'=>'tg_webhook_check_csrf'] as $action => $csrfField) {
    $check(strpos($source['settings_view'], "site_url('telegram/" . $action . "')") !== false
        && strpos($source['settings_view'], 'name="' . $csrfField . '"') !== false,
        'settings wires ' . $action . ' with its scoped CSRF field');
}
$check(strpos($source['settings_view'], 'name="candidate_key"') !== false
    && strpos($source['settings_view'], "candidate['target_type']") !== false
    && strpos($source['settings_view'], 'name="opaque_key"') === false
    && strpos($source['settings_view'], 'name="discovery_key"') === false,
    'settings submits only the controller-owned opaque candidate_key and renders the canonical target type');
$check(strpos($source['settings_view'], "&& !\$isEnabled") === false
    && strpos($source['settings_view'], 'Switch tidak perlu dinyalakan untuk melakukan test.') !== false,
    'test send remains available before activation and can be repeated without unsafe workflow coupling');
$check(strpos($source['settings_view'], "['url_matches_configured']") !== false
    && strpos($source['settings_view'], "['configured']") !== false,
    'webhook badge requires both configured and exact canonical URL match');
$check(preg_match('/<input[^>]+(?:TOKEN|SECRET|WEBHOOK_URL)/i', $source['settings_view'] . $source['guide_view']) !== 1
    && preg_match('/getenv\s*\(|api\.telegram\.org\/bot|\bcurl\b/i', $source['settings_view'] . $source['guide_view']) !== 1
    && strpos($source['settings_view'] . $source['guide_view'], "site_url('telegram_webhook')") === false,
    'user-facing setup never accepts credentials, exposes a Bot API endpoint, or derives the webhook URL from a request');

$check(
    strpos($source['webhook'], 'accept_webhook_command') !== false
        && strpos($source['webhook'], 'claim_queue') === false
        && strpos($source['webhook'], 'send_message') === false
        && strpos($source['webhook'], 'telegram_report') === false
        && strpos($source['webhook'], 'telegram_client') === false,
    'webhook only validates, deduplicates, enqueues, and acknowledges'
);

$check(
    strpos($source['webhook'], "method(true) !== 'POST'") !== false
        && strpos($source['webhook'], 'MAX_JSON_BYTES') !== false
        && strpos($source['webhook'], '262144') !== false
        && strpos($source['webhook'], 'application/json') !== false,
    'webhook is POST-only with bounded JSON input'
);
$check(
    strpos($source['webhook'], 'FINANCE_TELEGRAM_WEBHOOK_SECRET') !== false
        && strpos($source['webhook'], 'X-Telegram-Bot-Api-Secret-Token') !== false
        && strpos($source['webhook'], 'hash_equals') !== false,
    'webhook requires the environment secret in the official header'
);
$check(
    strpos($source['webhook'], "['GROUP', 'SUPERGROUP', 'CHANNEL']") !== false
        && strpos($source['webhook'], 'active_target_by_chat') !== false
        && strpos($source['model'], "where('is_active', 1)") !== false,
    'webhook accepts only active allowlisted internal targets'
);
foreach (['menu', 'omzet', 'belanja'] as $command) {
    $check(strpos($source['webhook'], $command) !== false, 'command is registered: /' . $command);
}
foreach (['OMZET_TODAY', 'PURCHASE_TODAY'] as $reportType) {
    $check(strpos($source['controller'], $reportType) !== false && strpos($source['report'], $reportType) !== false, 'report and schedule type exists: ' . $reportType);
}
$check(
    strpos($source['sql'], 'UNIQUE KEY uk_tg_queue_idempotency') !== false
        && strpos($source['model'], 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)') !== false,
    'queue enqueue is idempotent'
);
$check(
    strpos($source['model'], 'bin2hex(random_bytes(16))') !== false
        && strpos($source['model'], "where('lease_token', \$leaseToken)") !== false
        && strpos($source['model'], "where('status', 'PROCESSING')") !== false,
    'queue uses random lease ownership and owner-only finalization'
);
$check(
    strpos($source['client'], "failure('UNKNOWN'") !== false
        && strpos($source['model'], "if (\$status === 'FAILED' && \$attempt < \$maxAttempts)") !== false
        && strpos($source['model'], "\$status === 'UNKNOWN'") === false,
    'timeout is UNKNOWN and only FAILED can auto-retry'
);
$check(
    (strpos($source['client'], 'CURLOPT_SSL_VERIFYPEER => true') !== false || strpos($source['client'], 'CURLOPT_SSL_VERIFYPEER=>true') !== false)
        && (strpos($source['client'], 'CURLOPT_SSL_VERIFYHOST => 2') !== false || strpos($source['client'], 'CURLOPT_SSL_VERIFYHOST=>2') !== false)
        && strpos($source['client'], 'MAX_RESPONSE_BYTES') !== false
        && strpos($source['client'], 'CURLOPT_WRITEFUNCTION') !== false
        && strpos($source['client'], 'CURLINFO_REQUEST_SIZE') !== false,
    'client verifies TLS, bounds responses, and detects ambiguous post-request transport errors'
);
foreach (['target_save', 'schedule_save', 'settings', 'test_send'] as $method) {
    $check(strpos($source['controller'], 'function ' . $method) !== false, 'browser mutation exists: ' . $method);
}
$check(
    strpos($source['controller'], 'function resolve_unknown') !== false
        && strpos($source['controller'], "require_permission(self::PAGE_LOG, 'edit')") !== false
        && strpos($source['controller'], "require_post_csrf('unknown_resolution')") !== false
        && strpos($source['model'], "['CONFIRM_SENT', 'CLOSE_FAILED', 'RESEND']") !== false
        && strpos($source['model'], "'manual-resend:' . \$queueId . ':' . \$requestId") !== false,
    'UNKNOWN resolution is manual, RBAC/CSRF guarded, reasoned, and resend gets a new idempotency key'
);
$check(
    substr_count($source['controller'], 'require_post_csrf(') >= 5
        && strpos($source['controller'], 'require_permission(self::PAGE_DASHBOARD') !== false
        && strpos($source['controller'], 'require_permission(self::PAGE_DELIVERY') !== false
        && strpos($source['controller'], 'require_permission(self::PAGE_SETTINGS') !== false,
    'browser writes are RBAC and scoped-CSRF guarded'
);
$check(
    strpos($source['client'], 'FINANCE_TELEGRAM_BOT_TOKEN') !== false
        && stripos($source['client'] . $source['controller'] . $source['webhook'], 'node') === false,
    'client is environment-configured PHP with no Node dependency'
);

foreach (['tg_target', 'tg_schedule', 'tg_webhook_update', 'tg_delivery_queue', 'tg_delivery_log', 'tg_setting'] as $table) {
    $check(preg_match('/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\b/i', $source['sql']) === 1, 'idempotent table DDL: ' . $table);
}
$check(
    strpos($source['sql'], "ENUM('COMMAND','SCHEDULE','TEST','RESEND')") !== false
        && strpos($source['sql'], 'resolution_action') !== false
        && strpos($source['sql'], 'resolution_reason') !== false
        && strpos($source['sql'], 'resolved_by') !== false
        && strpos($source['sql'], 'resolution_queue_id') !== false,
    'initial schema carries audited UNKNOWN resolution and resend linkage'
);
foreach (['tg.dashboard', 'tg.delivery', 'tg.log', 'tg.settings', 'grp.telegram'] as $code) {
    $check(strpos($source['sql'], "'" . $code . "'") !== false, 'page/menu registry contains ' . $code);
}
$permissionBlock = substr($source['sql'], (int)strpos($source['sql'], 'INSERT INTO auth_role_permission'));
$check(
    strpos($permissionBlock, "role.role_code = 'SUPERADMIN'") !== false
        && preg_match("/role\.role_code\s+IN\s*\([^)]/i", $permissionBlock) !== 1,
    'RBAC seed grants only SUPERADMIN'
);
$check(
    strpos($source['sql'], 'FINANCE_TELEGRAM_BOT_TOKEN') === false
        && strpos($source['sql'], 'FINANCE_TELEGRAM_WEBHOOK_SECRET') === false,
    'migration stores no Telegram credential'
);
$check(
    preg_match("/VALUES\s*\(\s*'tg\.guide'\s*,\s*'Panduan Setup Telegram'\s*,\s*'TELEGRAM'/i", $source['guide_sql']) === 1
        && preg_match("/SELECT\s*'tg\.guide'\s*,\s*'Panduan Setup'\s*,\s*'ri-question-line'\s*,\s*'telegram\/guide'\s*,\s*page\.id\s*,\s*5\s*,\s*1\s*,\s*'MAIN'\s*,\s*parent\.id/i", $source['guide_sql']) === 1
        && strpos($source['guide_sql'], "parent.menu_code = 'grp.telegram'") !== false,
    'guide migration upserts the exact canonical page and child menu'
);
$guidePermissionBlock = substr($source['guide_sql'], (int)strpos($source['guide_sql'], 'INSERT INTO auth_role_permission'));
$check(
    preg_match('/SELECT\s+role\.id,\s*page\.id,\s*1,\s*0,\s*0,\s*0,\s*0,\s*NOW\(\)/i', $guidePermissionBlock) === 1
        && strpos($guidePermissionBlock, "page.page_code = 'tg.guide'") !== false
        && strpos($guidePermissionBlock, "role.role_code = 'SUPERADMIN'") !== false
        && preg_match('/ON DUPLICATE KEY UPDATE\s+can_view\s*=\s*1,\s*can_create\s*=\s*0,\s*can_edit\s*=\s*0,\s*can_delete\s*=\s*0,\s*can_export\s*=\s*0/is', $guidePermissionBlock) === 1,
    'guide migration grants SUPERADMIN view only and resets every writer permission to zero'
);
$check(
    strpos($source['guide_sql'], 'FINANCE_TELEGRAM_') === false
        && preg_match('/\b(?:tg_target|tg_schedule|tg_setting)\b/i', $source['guide_sql']) !== 1,
    'guide migration stores no credential, setting, target, schedule, or business data'
);

$catalog = json_decode($source['catalog'], true);
$migration = null;
foreach ((array)($catalog['migrations'] ?? []) as $entry) {
    if (($entry['id'] ?? '') === '2026-09-05a-telegram-bot-foundation') {
        $migration = $entry;
    }
}
$check(is_array($migration), 'Telegram migration is managed in the catalog');
$check(
    is_array($migration)
        && ($migration['order'] ?? 0) > 2026090403
        && ($migration['dependencies'] ?? []) === ['2026-09-04c-a5-schema-migration-registry-foundation'],
    'Telegram migration is ordered after 2026-09-04c'
);
$check(
    is_array($migration) && hash_file('sha256', $root . '/' . $paths['sql']) === ($migration['sha256'] ?? ''),
    'Telegram migration catalog checksum matches'
);
$guideMigration = null;
foreach ((array)($catalog['migrations'] ?? []) as $entry) {
    if (($entry['id'] ?? '') === '2026-09-05b-telegram-setup-guide') {
        $guideMigration = $entry;
    }
}
$check(
    is_array($guideMigration)
        && ($guideMigration['order'] ?? null) === 2026090502
        && ($guideMigration['path'] ?? '') === $paths['guide_sql']
        && ($guideMigration['dependencies'] ?? []) === ['2026-09-05a-telegram-bot-foundation']
        && ($guideMigration['classification'] ?? '') === 'seed'
        && ($guideMigration['policies'] ?? []) === ['clean_install', 'upgrade'],
    'guide migration has the exact catalog order, dependency, classification, and policies'
);
$check(
    is_array($guideMigration) && hash_file('sha256', $root . '/' . $paths['guide_sql']) === ($guideMigration['sha256'] ?? ''),
    'guide migration catalog checksum matches'
);
$safeMigration = null;
foreach ((array)($catalog['migrations'] ?? []) as $entry) {
    if (($entry['id'] ?? '') === '2026-09-05c-telegram-safe-activation-default') $safeMigration = $entry;
}
$check(is_array($safeMigration)
    && ($safeMigration['order'] ?? null) === 2026090503
    && ($safeMigration['dependencies'] ?? []) === ['2026-09-05b-telegram-setup-guide']
    && ($safeMigration['classification'] ?? '') === 'seed'
    && hash_file('sha256', $root . '/' . $paths['safe_sql']) === ($safeMigration['sha256'] ?? ''),
    'safe activation migration has exact order, dependency, class, and checksum');
$check(count($catalog['migrations'] ?? []) === 27 && count($catalog['legacy_unmanaged_sql'] ?? []) === 7,
    'catalog contains exactly twenty-seven managed and seven legacy SQL files');

if (!defined('BASEPATH')) {
    define('BASEPATH', $root . '/system/');
}
require_once $root . '/' . $paths['client'];
$validUrls = [
    'https://finance.example.com/telegram_webhook',
    'https://bot.example.com/finance/telegram_webhook',
    'https://8.8.8.8/telegram_webhook',
];
foreach ($validUrls as $url) $check(TelegramBotClient::validate_webhook_url($url), 'canonical webhook URL accepts safe fixture');
$invalidUrls = [
    'http://finance.example.com/telegram_webhook',
    'https://localhost/telegram_webhook',
    'https://bot.internal.local/telegram_webhook',
    'https://127.0.0.1/telegram_webhook',
    'https://10.0.0.1/telegram_webhook',
    'https://[::1]/telegram_webhook',
    'https://user:pass@example.com/telegram_webhook',
    'https://example.com/telegram_webhook?x=1',
    'https://example.com/telegram_webhook#x',
    'https://example.com/telegram_webhook/',
    "https://example.com/telegram_webhook\n",
    'https://example.com/not_telegram_webhook',
    'https://' . str_repeat('a', 2040) . '.com/telegram_webhook',
];
foreach ($invalidUrls as $url) $check(!TelegramBotClient::validate_webhook_url($url), 'canonical webhook URL rejects unsafe fixture');
$updates = [];
for ($i = 1; $i <= 22; $i++) {
    $updates[] = ['update_id'=>$i,'message'=>['from'=>['id'=>999,'token'=>'LEAK'],'text'=>'private payload','chat'=>['id'=>-$i,'type'=>$i===2?'private':'supergroup','title'=>" Team\n".$i]]];
}
$updates[] = ['update_id'=>30,'channel_post'=>['sender_chat'=>['id'=>99],'chat'=>['id'=>-1,'type'=>'channel','title'=>'Duplicate']]];
$sanitized = TelegramBotClient::sanitize_discovery_updates($updates);
$check(count($sanitized) === 20, 'discovery sanitizer caps unique supported negative targets at 20');
$check(array_keys($sanitized[0] ?? []) === ['chat_id','target_type','title'] && ($sanitized[0]['title'] ?? '') === 'Team1', 'discovery sanitizer emits only projected fields and removes title controls');
$check(count(array_unique(array_column($sanitized, 'chat_id'))) === count($sanitized)
    && count(array_filter($sanitized, static function(array $row): bool { return (int)$row['chat_id'] >= 0 || !in_array($row['target_type'], ['GROUP','SUPERGROUP','CHANNEL'], true); })) === 0,
    'discovery sanitizer rejects private/non-negative and deduplicates chat IDs');

$controllerContracts = [
    'setup_check_bot'=>["require_permission(self::PAGE_SETTINGS, 'create')","require_post_csrf('bot_check')"],
    'setup_discover_targets'=>["require_permission(self::PAGE_SETTINGS, 'create')","require_post_csrf('target_discovery')"],
    'setup_save_discovered_target'=>["require_permission(self::PAGE_DASHBOARD, 'create')","require_post_csrf('discovered_target_save')"],
    'setup_install_webhook'=>["require_permission(self::PAGE_SETTINGS, 'edit')","require_post_csrf('webhook_install')"],
    'setup_check_webhook'=>["require_permission(self::PAGE_SETTINGS, 'create')","require_post_csrf('webhook_check')"],
];
foreach ($controllerContracts as $method => $tokens) {
    $start = strpos($source['controller'], 'public function '.$method.'()');
    $next = strpos($source['controller'], 'public function ', $start + 20);
    $block = $start === false ? '' : substr($source['controller'], $start, ($next === false ? strlen($source['controller']) : $next) - $start);
    $check($block !== '' && strpos($block, $tokens[0]) !== false && strpos($block, '$this->require_schema();') !== false
        && strpos($block, $tokens[1]) !== false && strpos($block, "redirect('telegram/settings')") !== false,
        $method . ' enforces permission, schema, scoped CSRF, and PRG');
}
$check(strpos($source['controller'], "post('candidate_key'") !== false && strpos($source['controller'], "'candidate_key'=>\$key") !== false
    && strpos($source['controller'], 'bin2hex(random_bytes(16))') !== false && strpos($source['controller'], 'DISCOVERY_TTL_SECONDS = 600') !== false,
    'discovery uses 32hex opaque candidate keys with a ten-minute server-session lifetime');
$check(strpos($source['controller'], "post('discovery_key'") === false
    && strpos($source['controller'], "unset(\$state['candidates'][\$key])") !== false
    && strpos($source['controller'], 'sanitize_discovery_updates') !== false,
    'discovered target save accepts only candidate_key, revalidates, and consumes it once');
$check(strpos($source['controller'], '!$this->Telegram_model->is_enabled()') !== false
    && strpos($source['controller'], '$this->Telegram_model->active_target_count() < 1') !== false
    && substr_count($source['controller'], 'configured_webhook_url()') >= 3,
    'webhook install and switch activation enforce server URL/config, active target, and enabled prerequisites in backend');
$secretPosition = strpos($source['webhook'], 'if (!$this->valid_secret())');
$readyPosition = strpos($source['webhook'], 'if (!$this->Telegram_model->ready())');
$disabledPosition = strpos($source['webhook'], 'if (!$this->Telegram_model->is_enabled())');
$check($secretPosition !== false && $readyPosition > $secretPosition && $disabledPosition > $readyPosition
    && strpos($source['webhook'], "['ok' => true, 'ignored' => true, 'disabled' => true]") !== false,
    'valid-secret webhook returns 503 only for missing schema and ACKs disabled switch with ignored+disabled');
$check(strpos($source['client'], "getenv('FINANCE_TELEGRAM_BOT_TOKEN')") !== false
    && strpos($source['client'].$source['webhook'], "getenv('FINANCE_TELEGRAM_WEBHOOK_SECRET')") !== false
    && strpos($source['client'], 'FINANCE_TELEGRAM_WEBHOOK_URL') !== false
    && preg_match('/curl_error|log_message|[\'\"]description[\'\"]/', $source['client']) !== 1,
    'client uses canonical environment names and exposes no raw Telegram diagnostics');
$check(strpos($source['client'], "private const API_BASE = 'https://api.telegram.org'") !== false
    && strpos($source['client'], "['sendMessage', 'getMe', 'getWebhookInfo', 'getUpdates', 'setWebhook']") !== false
    && strpos($source['client'], 'CURLOPT_FOLLOWLOCATION=>false') !== false
    && strpos($source['client'], 'CURLOPT_SSL_VERIFYPEER=>true') !== false,
    'API host/method allowlist, no redirects, and TLS verification are hardcoded');
$check(strpos($source['client'], "'drop_pending_updates'=>\$firstInstall") !== false
    && strpos($source['client'], "'url_matches_configured'") !== false
    && strpos($source['client'], 'hash_equals($canonicalUrl') !== false,
    'install drops pending updates only on first install and verifies exact canonical URL without returning it');
$check(strpos($source['model'], "setting('telegram.enabled', '0')") !== false
    && strpos($source['safe_sql'], 'updated_by IS NULL') !== false
    && strpos($source['safe_sql'], 'description = VALUES(description)') !== false,
    'Telegram defaults disabled and safe seed preserves operator-edited switch while updating description');

require_once $root . '/' . $paths['report'];
$fixtureSummary = TelegramReportService::summarize_omzet_fixture([
    ['payment_status' => 'PAID', 'payment_type' => 'FINAL', 'paid_at' => '2026-09-05 08:00:00', 'created_at' => '2026-09-04 20:00:00', 'net_amount' => 100],
    ['payment_status' => 'PAID', 'payment_type' => 'DEPOSIT', 'paid_at' => null, 'created_at' => '2026-09-05 09:00:00', 'net_amount' => 30],
    ['payment_status' => 'PAID', 'payment_type' => 'FINAL', 'paid_at' => '2026-09-04 23:59:59', 'created_at' => '2026-09-05 00:01:00', 'net_amount' => 120],
    ['payment_status' => 'PENDING', 'payment_type' => 'FINAL', 'paid_at' => '2026-09-05 10:00:00', 'created_at' => '2026-09-05 10:00:00', 'net_amount' => 999],
    ['payment_status' => 'PAID', 'payment_type' => 'REFUND', 'paid_at' => '2026-09-05 11:00:00', 'created_at' => '2026-09-05 11:00:00', 'net_amount' => 999],
], [
    ['refund_status' => 'POSTED', 'refunded_at' => '2026-09-05 12:00:00', 'refund_amount' => 25, 'fixture_semantic' => 'PARTIAL'],
    ['refund_status' => 'POSTED', 'refunded_at' => '2026-09-05 13:00:00', 'refund_amount' => 120, 'fixture_semantic' => 'FULL_FROM_PRIOR_DATE_RECEIPT'],
    ['refund_status' => 'POSTED', 'refunded_at' => '2026-09-06 00:00:00', 'refund_amount' => 500],
    ['refund_status' => 'VOID', 'refunded_at' => '2026-09-05 14:00:00', 'refund_amount' => 500],
], '2026-09-05');
$check(
    $fixtureSummary === ['gross_receipt' => 130.0, 'refund' => 145.0, 'net' => -15.0, 'transaction_count' => 2],
    'DB-free omzet fixture enforces receipt date precedence plus cross-date partial/full refund semantics'
);
$check(
    strpos($source['report'], "where('payment_status', 'PAID')") !== false
        && strpos($source['report'], "where_in('payment_type', ['FINAL', 'DEPOSIT'])") !== false
        && substr_count($source['report'], 'COALESCE(paid_at, created_at)') >= 2
        && strpos($source['report'], "where('refund_status', 'POSTED')") !== false
        && strpos($source['report'], "where('refunded_at >=', \$start)") !== false
        && strpos($source['report'], "where('refunded_at <', \$end)") !== false,
    'runtime omzet query mirrors the DB-free receipt/refund contract'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' of ' . $checks . ' Telegram smoke checks failed.' . PHP_EOL);
    exit(1);
}
echo 'Telegram module smoke: PASS (' . $checks . ' DB/network-free checks).' . PHP_EOL;
