<?php
declare(strict_types=1);

/**
 * Batch 52 Finance -> wa-engine service-auth smoke.
 *
 * Source extraction uses only synthetic environment values. It does not load
 * CodeIgniter, read a runtime .env, access a database, open cURL, or print a
 * credential.
 */

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Whatsapp.php';
$settingsPath = $root . '/application/views/wa/settings.php';
$guidePath = $root . '/application/views/wa/guide.php';
$templatePath = $root . '/wa-engine/.env.example';
$runbookPath = $root . '/docs/wa_engine_internal_service_auth_runbook.md';

$source = (string)file_get_contents($controllerPath);
$settings = (string)file_get_contents($settingsPath);
$guide = (string)file_get_contents($guidePath);
$template = (string)file_get_contents($templatePath);
$runbook = (string)file_get_contents($runbookPath);

$checks = 0;
$failures = [];

function batch52_php_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function batch52_php_method(string $source, string $name): string
{
    $start = strpos($source, 'private function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $end = strpos($source, "\n    private function ", $start + 1);
    return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

$callBotApi = batch52_php_method($source, 'callBotApi');
$buildEnvString = batch52_php_method($source, 'buildEnvString');
$settingsMethod = strpos($source, 'public function settings()');
$settingsEnd = strpos($source, "\n    public function api_status()", $settingsMethod === false ? 0 : $settingsMethod);
$settingsBlock = ($settingsMethod !== false && $settingsEnd !== false)
    ? substr($source, $settingsMethod, $settingsEnd - $settingsMethod)
    : '';

batch52_php_check($callBotApi !== '', 'callBotApi method is present');
batch52_php_check(
    strpos($source, "private const WA_ENGINE_API_TOKEN_ENV = 'FINANCE_WA_ENGINE_API_TOKEN';") !== false
        && strpos($source, "private const WA_ENGINE_API_TOKEN_HEADER = 'X-Finance-Wa-Engine-Token';") !== false,
    'dedicated process variable and header constants are exact'
);

$envReadAt = strpos($callBotApi, 'getenv(self::WA_ENGINE_API_TOKEN_ENV)');
$failClosedAt = strpos($callBotApi, "\$serviceToken === ''");
$sessionAt = strpos($callBotApi, '$this->waSession()');
$curlAt = strpos($callBotApi, '$this->initializeBotApiCurl($url)');
batch52_php_check(
    $envReadAt !== false && $failClosedAt !== false && $sessionAt !== false && $curlAt !== false
        && $envReadAt < $failClosedAt && $failClosedAt < $sessionAt && $sessionAt < $curlAt,
    'missing process credential fails before session lookup and cURL initialization'
);
batch52_php_check(
    strpos($callBotApi, '$url = $botApiBaseUrl . $endpoint;') !== false
        && strpos($callBotApi, '?token=') === false
        && strpos($callBotApi, 'urlencode(') === false,
    'Finance preserves the endpoint URL without a token query string'
);
batch52_php_check(
    strpos($callBotApi, "self::WA_ENGINE_API_TOKEN_HEADER . ': ' . \$serviceToken") !== false
        && strpos($callBotApi, 'X-Sync-Token') === false
        && strpos($callBotApi, "['bot_api_token']") === false,
    'Finance sends only the dedicated service credential transport'
);
batch52_php_check(
    strpos($callBotApi, "['/internal/send', '/internal/send-group']") !== false
        && strpos($callBotApi, 'CURLOPT_FOLLOWLOCATION => false') !== false
        && strpos($callBotApi, 'CURLOPT_MAXREDIRS      => 0') !== false
        && strpos($callBotApi, "CURLOPT_PROXY          => ''") !== false,
    'existing send timeout and no-follow/no-proxy boundary remain intact'
);

$dynamicMethod = preg_replace('/^private function callBotApi/', 'public function callBotApi', $callBotApi, 1);
batch52_php_check(is_string($dynamicMethod) && $dynamicMethod !== $callBotApi, 'callBotApi can be isolated for fail-closed behavior probe');
if (is_string($dynamicMethod) && $dynamicMethod !== $callBotApi) {
    eval('class Batch52FinanceCallerHarness {'
        . "private const WA_ENGINE_API_TOKEN_ENV = 'FINANCE_WA_ENGINE_API_TOKEN';"
        . "private const WA_ENGINE_API_TOKEN_HEADER = 'X-Finance-Wa-Engine-Token';"
        . 'public int $sessionCalls = 0; public int $curlCalls = 0;'
        . 'private function waSession(): array { $this->sessionCalls++; return []; }'
        . 'private function initializeBotApiCurl(string $url) { $this->curlCalls++; return null; }'
        . $dynamicMethod
        . '}');

    $previousToken = getenv('FINANCE_WA_ENGINE_API_TOKEN');
    putenv('FINANCE_WA_ENGINE_API_TOKEN=');
    $harness = new Batch52FinanceCallerHarness();
    $result = $harness->callBotApi('/internal/status', 'GET');
    if ($previousToken === false) {
        putenv('FINANCE_WA_ENGINE_API_TOKEN');
    } else {
        putenv('FINANCE_WA_ENGINE_API_TOKEN=' . $previousToken);
    }
    batch52_php_check(
        ($result['ok'] ?? null) === false && $harness->sessionCalls === 0 && $harness->curlCalls === 0,
        'empty process credential returns failure without DB or cURL work'
    );
}

batch52_php_check(
    strpos($buildEnvString, '$k === self::WA_ENGINE_API_TOKEN_ENV') !== false
        && strpos($buildEnvString, '$k === self::WA_GROUP_COMMAND_TOKEN_ENV') !== false,
    'web-root .env launcher excludes both process-only service credentials'
);
$dynamicBuildEnv = preg_replace('/^private function buildEnvString/', 'public function buildEnvString', $buildEnvString, 1);
batch52_php_check(
    is_string($dynamicBuildEnv) && $dynamicBuildEnv !== $buildEnvString,
    'buildEnvString can be isolated for process-only exclusion probe'
);
if (is_string($dynamicBuildEnv) && $dynamicBuildEnv !== $buildEnvString) {
    eval('class Batch52EnvLauncherHarness {'
        . "private const WA_ENGINE_API_TOKEN_ENV = 'FINANCE_WA_ENGINE_API_TOKEN';"
        . "private const WA_GROUP_COMMAND_TOKEN_ENV = 'FINANCE_WA_ENGINE_COMMAND_TOKEN';"
        . $dynamicBuildEnv
        . '}');
    $fixtureDir = sys_get_temp_dir() . '/finance-wa-api-auth-' . bin2hex(random_bytes(8));
    $fixtureCreated = mkdir($fixtureDir, 0700, true);
    $fixturePath = $fixtureDir . '/.env';
    $fixtureWritten = $fixtureCreated && file_put_contents($fixturePath, implode("\n", [
        'FINANCE_WA_ENGINE_API_TOKEN=synthetic-file-api-token',
        'FINANCE_WA_ENGINE_COMMAND_TOKEN=synthetic-file-callback-token',
        'WA_PORT=3998',
        'DB_HOST=synthetic-db-host',
        '',
    ])) !== false;
    $builtEnvironment = $fixtureWritten
        ? (new Batch52EnvLauncherHarness())->buildEnvString($fixtureDir)
        : '';
    if (is_file($fixturePath)) {
        @unlink($fixturePath);
    }
    if (is_dir($fixtureDir)) {
        @rmdir($fixtureDir);
    }
    batch52_php_check(
        $fixtureWritten
            && strpos($builtEnvironment, 'FINANCE_WA_ENGINE_API_TOKEN=') === false
            && strpos($builtEnvironment, 'FINANCE_WA_ENGINE_COMMAND_TOKEN=') === false
            && strpos($builtEnvironment, 'WA_PORT=') !== false
            && strpos($builtEnvironment, 'DB_HOST=') !== false,
        'launcher behavior excludes service credentials while retaining unrelated environment keys'
    );
}
batch52_php_check(
    strpos($settingsBlock, "['bot_api_token']") === false
        && strpos($settingsBlock, "post('bot_api_token'") === false
        && strpos($settingsBlock, 'bot_api_token_configured') === false,
    'settings controller neither edits nor exposes legacy DB token status'
);
batch52_php_check(
    strpos($settings, 'bot_api_token') === false && strpos($settings, 'WA_TOKEN') === false,
    'settings UI has no legacy token editor or status reference'
);
batch52_php_check(
    strpos($guide, 'WA_TOKEN') === false && strpos($guide, 'local-dev-token') === false,
    'operator guide has no legacy token or development fallback instruction'
);
batch52_php_check(
    strpos($template, 'FINANCE_WA_ENGINE_API_TOKEN=') !== false
        && preg_match('/^FINANCE_WA_ENGINE_API_TOKEN=\s*$/m', $template) === 1
        && strpos($template, 'WA_TOKEN=') === false
        && strpos($template, 'local-dev-token') === false,
    '.env example names the process-only variable without a value or legacy fallback'
);
batch52_php_check(
    strpos($source, "private const WA_GROUP_COMMAND_TOKEN_ENV = 'FINANCE_WA_ENGINE_COMMAND_TOKEN';") !== false
        && strpos($source, "private const WA_GROUP_COMMAND_TOKEN_CI_HEADER = 'X-Finance-Group-Command-Token';") !== false,
    'Batch 50 callback credential remains separately named and intact'
);
batch52_php_check(
    strpos($runbook, 'PHP/FPM') !== false
        && strpos($runbook, 'wa-engine') !== false
        && strpos($runbook, 'di luar web root') !== false
        && strpos($runbook, 'tidak menjalankan restart atau cutover') !== false
        && strpos($runbook, 'FINANCE_WA_ENGINE_COMMAND_TOKEN') !== false,
    'runbook documents provisioning, no-restart scope, DB boundary, and separate callback auth'
);

if ($failures !== []) {
    fwrite(STDERR, "Whatsapp engine API service-auth smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'Whatsapp engine API service-auth smoke passed (' . $checks . " checks).\n";
