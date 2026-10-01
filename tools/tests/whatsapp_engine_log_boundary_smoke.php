<?php

declare(strict_types=1);

/**
 * DB-free smoke test for the WhatsApp engine diagnostic boundary.
 *
 * Endpoint scenarios execute the real Whatsapp controller and real
 * MY_Controller permission guard against fake CI dependencies. Only synthetic
 * files under a temporary FCPATH are used; no DB, network, repository .env,
 * runtime log, or runtime secret is accessed.
 */

const WHATSAPP_ENGINE_SMOKE_TOKEN = 'SYNTHETIC_LOG_TOKEN_SENTINEL_36';
const WHATSAPP_ENGINE_SMOKE_PASSWORD = 'SYNTHETIC_LOG_PASSWORD_SENTINEL_36';
const WHATSAPP_ENGINE_SMOKE_PATH = '/synthetic/private/engine/path/sentinel-36';
const WHATSAPP_ENGINE_SMOKE_PHONE = '628123456789036';
const WHATSAPP_ENGINE_SMOKE_JID = '628123456789036-123456@g.us';
const WHATSAPP_ENGINE_SMOKE_ANSI = 'SYNTHETIC_ANSI_SENTINEL_36';
const WHATSAPP_ENGINE_SMOKE_CONTROL = 'SYNTHETIC_CONTROL_SENTINEL_36';
const WHATSAPP_ENGINE_SMOKE_LISTING = 'SYNTHETIC_DIRECTORY_LISTING_SENTINEL_36';

$whatsappEngineSmokeFixtureRoot = getenv('WHATSAPP_ENGINE_SMOKE_ROOT');
if (!is_string($whatsappEngineSmokeFixtureRoot) || $whatsappEngineSmokeFixtureRoot === '') {
    $whatsappEngineSmokeFixtureRoot = sys_get_temp_dir() . '/finance-wa-engine-smoke-unused';
}

defined('BASEPATH') || define('BASEPATH', __DIR__);
defined('FCPATH') || define(
    'FCPATH',
    rtrim($whatsappEngineSmokeFixtureRoot, '/\\') . DIRECTORY_SEPARATOR
);

final class WhatsappEngineSmokeProbeStream
{
    public static int $accesses = 0;

    public function url_stat($path, $flags)
    {
        self::$accesses++;
        return false;
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        self::$accesses++;
        return false;
    }

    public function dir_opendir($path, $options): bool
    {
        self::$accesses++;
        return false;
    }
}

final class WhatsappEngineSmokeInput
{
    public string $raw_input_stream = '{}';

    public function is_ajax_request(): bool
    {
        return true;
    }
}

final class WhatsappEngineSmokeOutput
{
    public int $status = 200;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        return $this;
    }

    public function set_content_type($contentType): self
    {
        $this->contentType = (string)$contentType;
        return $this;
    }

    public function set_output($body): self
    {
        $this->body = (string)$body;
        return $this;
    }

    public function _display(): void
    {
        fwrite(STDERR, json_encode([
            'type' => 'http',
            'status' => $this->status,
            'content_type' => $this->contentType,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL);
        echo $this->body;
    }
}

final class WhatsappEngineSmokeDb
{
    public function table_exists($table): bool
    {
        return false;
    }
}

class CI_Controller
{
    public $db;
    public $input;
    public $output;

    public function __construct()
    {
    }
}

require dirname(__DIR__, 2) . '/application/core/MY_Controller.php';
require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

function whatsapp_engine_smoke_controller(bool $allowEdit): Whatsapp
{
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->db = new WhatsappEngineSmokeDb();
    $controller->input = new WhatsappEngineSmokeInput();
    $controller->output = new WhatsappEngineSmokeOutput();

    $baseReflection = new ReflectionClass(MY_Controller::class);
    $currentUser = $baseReflection->getProperty('current_user');
    $currentUser->setAccessible(true);
    $currentUser->setValue($controller, ['id' => 0, 'is_superadmin' => false]);

    $permissions = $baseReflection->getProperty('user_perms');
    $permissions->setAccessible(true);
    $permissions->setValue($controller, [
        'wa.settings' => [
            'can_view' => true,
            'can_edit' => $allowEdit,
        ],
    ]);

    return $controller;
}

function whatsapp_engine_smoke_run_child_scenario(string $scenario): void
{
    if ($scenario === 'logs-denied') {
        if (!stream_wrapper_register('probe', WhatsappEngineSmokeProbeStream::class)) {
            fwrite(STDERR, "probe stream registration failed\n");
            exit(2);
        }
        register_shutdown_function(static function (): void {
            fwrite(STDERR, json_encode([
                'type' => 'probe',
                'accesses' => WhatsappEngineSmokeProbeStream::$accesses,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL);
        });
        whatsapp_engine_smoke_controller(false)->api_engine_logs();
        return;
    }

    if ($scenario === 'logs-edit' || $scenario === 'logs-missing') {
        whatsapp_engine_smoke_controller(true)->api_engine_logs();
        return;
    }

    echo json_encode(['unknown_scenario' => true], JSON_UNESCAPED_UNICODE);
}

$whatsappEngineSmokeScenario = $argv[1] ?? '';
if ($whatsappEngineSmokeScenario !== '') {
    whatsapp_engine_smoke_run_child_scenario((string)$whatsappEngineSmokeScenario);
    exit;
}

$whatsappEngineSmokeChecks = 0;
$whatsappEngineSmokeFailures = [];

function whatsapp_engine_smoke_check(bool $condition, string $message): void
{
    global $whatsappEngineSmokeChecks, $whatsappEngineSmokeFailures;
    $whatsappEngineSmokeChecks++;
    if (!$condition) {
        $whatsappEngineSmokeFailures[] = $message;
    }
}

function whatsapp_engine_smoke_method_block(string $source, string $method): string
{
    $start = strpos($source, 'public function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    $next = preg_match(
        '/\n    (?:public|protected|private) function /',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = $next === 1 ? (int)$matches[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

/**
 * @return array{exit_code:int, stdout:string, stderr:string, json:array|null, metadata:array<string, array>}
 */
function whatsapp_engine_smoke_child(string $scenario, string $fixtureRoot): array
{
    $command = [PHP_BINARY, __FILE__, $scenario];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = [
        'WHATSAPP_ENGINE_SMOKE_ROOT' => $fixtureRoot,
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2), $environment);
    if (!is_resource($process)) {
        return [
            'exit_code' => 255,
            'stdout' => '',
            'stderr' => 'child unavailable',
            'json' => null,
            'metadata' => [],
        ];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $metadata = [];
    foreach (preg_split('/\r\n|\r|\n/', trim((string)$stderr)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }
        $decodedLine = json_decode($line, true);
        if (is_array($decodedLine) && isset($decodedLine['type'])) {
            $metadata[(string)$decodedLine['type']] = $decodedLine;
        }
    }
    $decoded = json_decode((string)$stdout, true);

    return [
        'exit_code' => (int)$exitCode,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
        'json' => is_array($decoded) ? $decoded : null,
        'metadata' => $metadata,
    ];
}

/**
 * @param string[] $needles
 */
function whatsapp_engine_smoke_contains_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($haystack, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function whatsapp_engine_smoke_definition_count(string $content, string $key): int
{
    return preg_match_all(
        '/^\s*' . preg_quote($key, '/') . '\s*=/m',
        $content,
        $matches
    ) ?: 0;
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Whatsapp.php';
$viewPath = $root . '/application/views/wa/settings.php';
$controllerSource = @file_get_contents($controllerPath);
$viewSource = @file_get_contents($viewPath);

whatsapp_engine_smoke_check($controllerSource !== false, 'Whatsapp controller source is readable');
whatsapp_engine_smoke_check($viewSource !== false, 'WhatsApp settings view source is readable');

$logsBlock = is_string($controllerSource)
    ? whatsapp_engine_smoke_method_block($controllerSource, 'api_engine_logs')
    : '';
$permissionPosition = strpos($logsBlock, "require_permission(self::PAGE_SETTINGS, 'edit')");
$boundaryPositions = array_filter([
    strpos($logsBlock, 'FCPATH'),
    strpos($logsBlock, 'realpath('),
    strpos($logsBlock, 'engineLogPath('),
    strpos($logsBlock, 'is_file('),
    strpos($logsBlock, 'is_readable('),
], static fn($position): bool => $position !== false);
$firstBoundaryPosition = $boundaryPositions === [] ? false : min($boundaryPositions);
whatsapp_engine_smoke_check(
    $permissionPosition !== false
        && $firstBoundaryPosition !== false
        && $permissionPosition < $firstBoundaryPosition,
    'api_engine_logs requires wa.settings:edit before path or filesystem access'
);
whatsapp_engine_smoke_check(
    preg_match('/\b(?:exec|file|file_get_contents)\s*\(/', $logsBlock) !== 1
        && strpos($logsBlock, 'ls -la') === false
        && strpos($logsBlock, 'tail -n') === false
        && strpos($logsBlock, "'logs'") === false
        && strpos($logsBlock, "'file'") === false,
    'api_engine_logs has no command execution, content read, raw log, filename, or listing response'
);

if (is_string($viewSource)) {
    $logMarker = strpos($viewSource, '<!-- Log diagnostic status (edit-only) -->');
    $beforeMarker = $logMarker === false ? '' : substr($viewSource, 0, $logMarker);
    $editOpen = strrpos($beforeMarker, '<?php if ($canEdit): ?>');
    $editClose = $logMarker === false ? false : strpos($viewSource, '<?php endif; ?>', $logMarker);
    $editOnlyBlock = $editOpen === false || $editClose === false
        ? ''
        : substr($viewSource, $editOpen, $editClose - $editOpen);
    whatsapp_engine_smoke_check(
        $logMarker !== false
            && strpos($editOnlyBlock, 'id="btn-engine-log"') !== false
            && strpos($editOnlyBlock, 'id="engine-log-output"') !== false,
        'settings log diagnostics controls are enclosed by the edit-only view boundary'
    );
    whatsapp_engine_smoke_check(
        strpos($viewSource, 'd.logs') === false,
        'settings JavaScript consumes generic diagnostic messages instead of raw logs'
    );
}

$fixtureRoot = sys_get_temp_dir() . '/finance-wa-engine-smoke-' . bin2hex(random_bytes(8));
$engineDir = $fixtureRoot . '/wa-engine';
$logPath = $engineDir . '/wa-engine.log';
$listingPath = $engineDir . '/' . WHATSAPP_ENGINE_SMOKE_LISTING;
if (!mkdir($engineDir, 0700, true) && !is_dir($engineDir)) {
    fwrite(STDERR, "[FAIL] synthetic fixture directory could not be created\n");
    exit(1);
}

register_shutdown_function(static function () use (
    $listingPath,
    $logPath,
    $engineDir,
    $fixtureRoot
): void {
    @unlink($listingPath);
    @unlink($logPath);
    @rmdir($engineDir);
    @rmdir($fixtureRoot);
});

$syntheticLog = implode("\n", [
    'token=' . WHATSAPP_ENGINE_SMOKE_TOKEN,
    'password=' . WHATSAPP_ENGINE_SMOKE_PASSWORD,
    'path=' . WHATSAPP_ENGINE_SMOKE_PATH,
    'phone=' . WHATSAPP_ENGINE_SMOKE_PHONE,
    'jid=' . WHATSAPP_ENGINE_SMOKE_JID,
    "\x1b[31m" . WHATSAPP_ENGINE_SMOKE_ANSI . "\x1b[0m",
    WHATSAPP_ENGINE_SMOKE_CONTROL . "\x07\x1f",
    '',
]);
whatsapp_engine_smoke_check(
    file_put_contents($logPath, $syntheticLog) !== false
        && file_put_contents($listingPath, 'synthetic directory entry') !== false,
    'synthetic log and directory fixtures were created'
);

$denied = whatsapp_engine_smoke_child('logs-denied', 'probe://permission-boundary');
whatsapp_engine_smoke_check(
    $denied['exit_code'] === 0
        && ($denied['metadata']['http']['status'] ?? 0) === 403
        && ($denied['json']['ok'] ?? true) === false
        && ($denied['json']['page_code'] ?? '') === 'wa.settings'
        && ($denied['json']['action'] ?? '') === 'edit',
    'view-only caller receives HTTP 403 from the real edit permission guard'
);
whatsapp_engine_smoke_check(
    ($denied['metadata']['probe']['accesses'] ?? -1) === 0,
    'denied request performs no FCPATH filesystem access before returning 403'
);

$sensitiveValues = [
    WHATSAPP_ENGINE_SMOKE_TOKEN,
    WHATSAPP_ENGINE_SMOKE_PASSWORD,
    WHATSAPP_ENGINE_SMOKE_PATH,
    WHATSAPP_ENGINE_SMOKE_PHONE,
    WHATSAPP_ENGINE_SMOKE_JID,
    WHATSAPP_ENGINE_SMOKE_ANSI,
    WHATSAPP_ENGINE_SMOKE_CONTROL,
    WHATSAPP_ENGINE_SMOKE_LISTING,
    $fixtureRoot,
    'wa-engine.log',
];

$available = whatsapp_engine_smoke_child('logs-edit', $fixtureRoot);
$availableJson = $available['json'] ?? [];
$availableKeys = array_keys($availableJson);
sort($availableKeys, SORT_STRING);
whatsapp_engine_smoke_check(
    $available['exit_code'] === 0
        && $available['stderr'] === ''
        && ($availableJson['ok'] ?? false) === true
        && ($availableJson['status'] ?? '') === 'available'
        && ($availableJson['available'] ?? false) === true
        && $availableKeys === ['available', 'message', 'ok', 'status'],
    'edit caller receives only the generic available diagnostic schema'
);
whatsapp_engine_smoke_check(
    !whatsapp_engine_smoke_contains_any($available['stdout'], $sensitiveValues)
        && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $available['stdout']) !== 1,
    'available response contains no log sentinel, raw control data, path, filename, or listing'
);

whatsapp_engine_smoke_check(@unlink($logPath), 'synthetic log fixture was removed for missing case');
$missing = whatsapp_engine_smoke_child('logs-missing', $fixtureRoot);
$missingJson = $missing['json'] ?? [];
$missingKeys = array_keys($missingJson);
sort($missingKeys, SORT_STRING);
whatsapp_engine_smoke_check(
    $missing['exit_code'] === 0
        && $missing['stderr'] === ''
        && ($missingJson['ok'] ?? false) === true
        && ($missingJson['status'] ?? '') === 'unavailable'
        && ($missingJson['available'] ?? true) === false
        && ($missingJson['message'] ?? '') === 'Log diagnostik belum tersedia di server.'
        && $missingKeys === ['available', 'message', 'ok', 'status'],
    'missing log returns only the generic unavailable diagnostic schema'
);
whatsapp_engine_smoke_check(
    !whatsapp_engine_smoke_contains_any($missing['stdout'], $sensitiveValues),
    'missing response contains no path, filename, directory listing, or synthetic sentinel'
);

$controller = whatsapp_engine_smoke_controller(true);
$controllerReflection = new ReflectionClass(Whatsapp::class);
$updatesMethod = $controllerReflection->getMethod('waEnvUpdatesFromPayload');
$updatesMethod->setAccessible(true);
$mergeMethod = $controllerReflection->getMethod('mergeWaEnvContent');
$mergeMethod->setAccessible(true);

$existingEnv = implode("\r\n", [
    '# preserved managed-key comment',
    '  WA_TOKEN =old-token-first',
    'EXTRA_PRIVATE_KEY=keep-additional-value',
    'WA_TOKEN=old-token-duplicate',
    '  DB_USER =old-user-first',
    'DB_USER=old-user-duplicate',
    'DB_HOST=old-host-first',
    'DB_PASS=keep-blank-secret-value',
    'DB_HOST=old-host-duplicate',
    'DB_NAME=untouched-managed-first',
    'DB_NAME=untouched-managed-duplicate',
    '# preserved trailing comment',
    '',
]);
$payload = [
    'DB_USER' => "new-user\r\nINJECTED_ENV_KEY=blocked\0tail",
    'DB_HOST' => "db.internal\rINJECTED_HOST_KEY=blocked\nend",
    'DB_PASS' => "\r\n\0\t",
    'WA_PORT' => "3090\nINJECTED_PORT_KEY=blocked\0tail",
    'UNMANAGED_KEY' => "must-not-append\nUNMANAGED_INJECTION=blocked",
];
/** @var array<string, string> $updates */
$updates = $updatesMethod->invoke($controller, $payload);
/** @var string $mergedEnv */
$mergedEnv = $mergeMethod->invoke($controller, $existingEnv, $updates);

whatsapp_engine_smoke_check(
    !array_key_exists('DB_PASS', $updates)
        && !array_key_exists('UNMANAGED_KEY', $updates),
    'blank secret and unmanaged payload keys are excluded from env updates'
);
whatsapp_engine_smoke_check(
    preg_match('/[\r\n\x00]/', implode('', $updates)) !== 1
        && strpos($mergedEnv, "\r") === false
        && strpos($mergedEnv, "\0") === false
        && substr($mergedEnv, -1) === "\n",
    'CRLF and NUL are removed from payload values and merged output uses LF'
);
whatsapp_engine_smoke_check(
    whatsapp_engine_smoke_definition_count($mergedEnv, 'DB_USER') === 1
        && whatsapp_engine_smoke_definition_count($mergedEnv, 'DB_HOST') === 1
        && whatsapp_engine_smoke_definition_count($mergedEnv, 'WA_PORT') === 1,
    'each touched managed env key has exactly one canonical definition'
);
whatsapp_engine_smoke_check(
    strpos($mergedEnv, '  DB_USER =new-userINJECTED_ENV_KEY=blockedtail') !== false
        && strpos($mergedEnv, 'DB_HOST=db.internalINJECTED_HOST_KEY=blockedend') !== false
        && strpos($mergedEnv, 'WA_PORT=3090INJECTED_PORT_KEY=blockedtail') !== false,
    'canonical env definitions keep the first position and sanitized submitted value'
);
whatsapp_engine_smoke_check(
    whatsapp_engine_smoke_definition_count($mergedEnv, 'WA_TOKEN') === 2
        && strpos($mergedEnv, '  WA_TOKEN =old-token-first') !== false
        && strpos($mergedEnv, 'WA_TOKEN=old-token-duplicate') !== false,
    'retired WA_TOKEN definitions remain untouched for rollback'
);
whatsapp_engine_smoke_check(
    preg_match('/^INJECTED_(?:ENV|HOST|PORT)_KEY=/m', $mergedEnv) !== 1
        && strpos($mergedEnv, 'UNMANAGED_KEY=') === false
        && strpos($mergedEnv, 'UNMANAGED_INJECTION=') === false,
    'payload controls cannot create injected or unmanaged env lines'
);
whatsapp_engine_smoke_check(
    whatsapp_engine_smoke_definition_count($mergedEnv, 'DB_PASS') === 1
        && strpos($mergedEnv, 'DB_PASS=keep-blank-secret-value') !== false,
    'blank secret payload preserves its existing env definition and value'
);
whatsapp_engine_smoke_check(
    whatsapp_engine_smoke_definition_count($mergedEnv, 'DB_NAME') === 2
        && strpos($mergedEnv, 'DB_NAME=untouched-managed-first') !== false
        && strpos($mergedEnv, 'DB_NAME=untouched-managed-duplicate') !== false
        && strpos($mergedEnv, 'EXTRA_PRIVATE_KEY=keep-additional-value') !== false
        && strpos($mergedEnv, '# preserved managed-key comment') !== false
        && strpos($mergedEnv, '# preserved trailing comment') !== false,
    'untouched managed keys, additional keys, and comments are preserved'
);

/** @var string $directMerge */
$directMerge = $mergeMethod->invoke($controller, '', [
    'DB_HOST' => "safe\nDIRECT_INJECTION=blocked\0tail",
    'UNMANAGED_KEY' => "blocked\nSECOND_INJECTION=blocked",
]);
whatsapp_engine_smoke_check(
    whatsapp_engine_smoke_definition_count($directMerge, 'DB_HOST') === 1
        && preg_match('/^(?:DIRECT_INJECTION|UNMANAGED_KEY|SECOND_INJECTION)=/m', $directMerge) !== 1
        && strpos($directMerge, "\0") === false,
    'env merge independently enforces managed keys and line-injection safety'
);
whatsapp_engine_smoke_check(
    !file_exists($engineDir . '/.env'),
    'smoke test never creates or writes an env file'
);

if ($whatsappEngineSmokeFailures !== []) {
    foreach ($whatsappEngineSmokeFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, '[FAIL] WhatsApp engine log boundary smoke failed: '
        . count($whatsappEngineSmokeFailures)
        . " failure(s) across {$whatsappEngineSmokeChecks} checks.\n");
    exit(1);
}

echo "[PASS] WhatsApp engine log boundary smoke: {$whatsappEngineSmokeChecks} checks.\n";
