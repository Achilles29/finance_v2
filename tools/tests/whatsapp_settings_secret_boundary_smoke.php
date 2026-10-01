<?php

declare(strict_types=1);

/**
 * DB-free smoke test for the WhatsApp settings secret boundary.
 *
 * Endpoint scenarios execute the real controller against a synthetic temp
 * wa-engine directory and in-memory CI stubs. No application bootstrap,
 * database, network, repository .env, or runtime secret is accessed.
 */

const WHATSAPP_SECRET_SMOKE_DB_PASS = 'SYNTHETIC_DB_PASS_SENTINEL_34';
const WHATSAPP_SECRET_SMOKE_WA_TOKEN = 'SYNTHETIC_WA_TOKEN_SENTINEL_34';
const WHATSAPP_SECRET_SMOKE_EXTRA = 'SYNTHETIC_EXTRA_SECRET_SENTINEL_34';
const WHATSAPP_SECRET_SMOKE_ERROR = 'SYNTHETIC_ERROR_SECRET_SENTINEL_34';
const WHATSAPP_SECRET_SMOKE_ENV_CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WHATSAPP_SECRET_SMOKE_SETTINGS_CSRF = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

$whatsappSecretSmokeFixtureRoot = getenv('WHATSAPP_SECRET_SMOKE_ROOT');
if (!is_string($whatsappSecretSmokeFixtureRoot) || $whatsappSecretSmokeFixtureRoot === '') {
    $whatsappSecretSmokeFixtureRoot = sys_get_temp_dir() . '/finance-wa-secret-smoke-unused';
}

defined('BASEPATH') || define('BASEPATH', __DIR__);
defined('FCPATH') || define('FCPATH', rtrim($whatsappSecretSmokeFixtureRoot, '/\\') . DIRECTORY_SEPARATOR);

final class WhatsappSecretSmokeDenied extends RuntimeException
{
}

final class WhatsappSecretSmokeInput
{
    public string $raw_input_stream;
    private string $requestMethod;
    private array $postData;
    private array $headers;

    public function __construct(
        string $requestMethod = 'GET',
        array $postData = [],
        array $jsonData = [],
        array $headers = []
    )
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
        $this->headers = $headers;
        $this->raw_input_stream = json_encode($jsonData, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function method($upper = false): string
    {
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key, $xssClean = false)
    {
        return $this->postData[(string)$key] ?? null;
    }

    public function get_request_header($key, $xssClean = false): string
    {
        return (string)($this->headers[(string)$key] ?? '');
    }
}

final class WhatsappSecretSmokeDb
{
    public array $row;
    public array $updates = [];

    public function __construct()
    {
        $this->row = [
            'id' => 1,
            'bot_api_url' => 'http://127.0.0.1:3070',
            'bot_api_token' => WHATSAPP_SECRET_SMOKE_WA_TOKEN,
            'node_path' => '/synthetic/node',
        ];
    }

    public function field_exists($field, $table): bool
    {
        return (string)$field === 'node_path' && (string)$table === 'wa_session';
    }

    public function where($field, $value = null): self
    {
        return $this;
    }

    public function update($table, $data): bool
    {
        $this->updates[] = [(string)$table, (array)$data];
        if ((string)$table === 'wa_session') {
            $this->row = array_merge($this->row, (array)$data);
        }
        return true;
    }
}

final class WhatsappSecretSmokeSession
{
    public array $flash = [];
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        return $this->values[(string)$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $this->values[(string)$key] = $value;
    }

    public function set_flashdata($key, $value): void
    {
        $this->flash[(string)$key] = (string)$value;
    }
}

class MY_Controller
{
    public $input;
    public $db;
    public $session;
    public bool $allowEdit = true;
    public array $permissionCalls = [];
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if ((string)$action === 'edit' && !$this->allowEdit) {
            throw new WhatsappSecretSmokeDenied('denied');
        }
    }

    public function can($page, $action = 'view'): bool
    {
        return (string)$action !== 'edit' || $this->allowEdit;
    }

    public function render($view, array $data = []): void
    {
        $this->renderedData = $data;
    }
}

$whatsappSecretSmokeRedirect = '';
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $whatsappSecretSmokeRedirect;
    $whatsappSecretSmokeRedirect = (string)$uri;
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

function whatsapp_secret_smoke_controller(
    bool $allowEdit,
    ?WhatsappSecretSmokeInput $input = null,
    ?WhatsappSecretSmokeDb $db = null,
    ?WhatsappSecretSmokeSession $session = null
): Whatsapp {
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->allowEdit = $allowEdit;
    $controller->input = $input ?? new WhatsappSecretSmokeInput();
    $controller->db = $db ?? new WhatsappSecretSmokeDb();
    $controller->session = $session ?? new WhatsappSecretSmokeSession();
    return $controller;
}

function whatsapp_secret_smoke_run_child_scenario(string $scenario): void
{
    if ($scenario === 'read-denied') {
        $controller = whatsapp_secret_smoke_controller(false);
        try {
            $controller->api_env_read();
        } catch (WhatsappSecretSmokeDenied $exception) {
            echo json_encode([
                'denied' => true,
                'permission_calls' => $controller->permissionCalls,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo json_encode(['denied' => false], JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($scenario === 'read-edit') {
        whatsapp_secret_smoke_controller(true)->api_env_read();
        return;
    }

    if ($scenario === 'save-env-blank') {
        $input = new WhatsappSecretSmokeInput('POST', [], [
            'DB_HOST' => '',
            'DB_PASS' => '',
        ], ['X-Wa-Env-Save-Csrf' => WHATSAPP_SECRET_SMOKE_ENV_CSRF]);
        $session = new WhatsappSecretSmokeSession([
            'wa_env_save_csrf' => WHATSAPP_SECRET_SMOKE_ENV_CSRF,
        ]);
        whatsapp_secret_smoke_controller(true, $input, null, $session)->api_env_save();
        return;
    }

    if ($scenario === 'save-env-error') {
        $input = new WhatsappSecretSmokeInput('POST', [], [
            'DB_HOST' => WHATSAPP_SECRET_SMOKE_ERROR,
        ], ['X-Wa-Env-Save-Csrf' => WHATSAPP_SECRET_SMOKE_ENV_CSRF]);
        $session = new WhatsappSecretSmokeSession([
            'wa_env_save_csrf' => WHATSAPP_SECRET_SMOKE_ENV_CSRF,
        ]);
        whatsapp_secret_smoke_controller(true, $input, null, $session)->api_env_save();
        return;
    }

    if ($scenario === 'save-settings-blank') {
        $input = new WhatsappSecretSmokeInput('POST', [
            'bot_api_url' => 'http://127.0.0.1:3080',
            'bot_api_token' => '',
            'node_path' => '',
            'wa_settings_mutation_csrf' => WHATSAPP_SECRET_SMOKE_SETTINGS_CSRF,
        ]);
        $db = new WhatsappSecretSmokeDb();
        $session = new WhatsappSecretSmokeSession([
            'wa_settings_mutation_csrf' => WHATSAPP_SECRET_SMOKE_SETTINGS_CSRF,
        ]);
        $controller = whatsapp_secret_smoke_controller(true, $input, $db, $session);
        $controller->settings();
        echo json_encode([
            'token_preserved' => ($db->row['bot_api_token'] ?? null) === WHATSAPP_SECRET_SMOKE_WA_TOKEN,
            'token_omitted_from_update' => !array_key_exists('bot_api_token', $db->updates[0][1] ?? []),
            'flash_is_generic' => ($session->flash['success'] ?? '') === 'Pengaturan disimpan.',
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode(['unknown_scenario' => true], JSON_UNESCAPED_UNICODE);
}

$whatsappSecretSmokeScenario = $argv[1] ?? '';
if ($whatsappSecretSmokeScenario !== '') {
    whatsapp_secret_smoke_run_child_scenario((string)$whatsappSecretSmokeScenario);
    exit;
}

$whatsappSecretSmokeChecks = 0;
$whatsappSecretSmokeFailures = [];

function whatsapp_secret_smoke_check(bool $condition, string $message): void
{
    global $whatsappSecretSmokeChecks, $whatsappSecretSmokeFailures;
    $whatsappSecretSmokeChecks++;
    if (!$condition) {
        $whatsappSecretSmokeFailures[] = $message;
    }
}

function whatsapp_secret_smoke_method_block(string $source, string $method): string
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
 * @return array{exit_code:int, stdout:string, stderr:string, json:array|null}
 */
function whatsapp_secret_smoke_child(string $scenario, string $fixtureRoot): array
{
    $command = [PHP_BINARY, __FILE__, $scenario];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = [
        'WHATSAPP_SECRET_SMOKE_ROOT' => $fixtureRoot,
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2), $environment);
    if (!is_resource($process)) {
        return ['exit_code' => 255, 'stdout' => '', 'stderr' => 'child unavailable', 'json' => null];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $decoded = json_decode((string)$stdout, true);
    return [
        'exit_code' => (int)$exitCode,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Whatsapp.php';
$viewPath = $root . '/application/views/wa/settings.php';
$controllerSource = @file_get_contents($controllerPath);
$viewSource = @file_get_contents($viewPath);

whatsapp_secret_smoke_check($controllerSource !== false, 'Whatsapp controller source is readable');
whatsapp_secret_smoke_check($viewSource !== false, 'WhatsApp settings view source is readable');

$readBlock = is_string($controllerSource)
    ? whatsapp_secret_smoke_method_block($controllerSource, 'api_env_read')
    : '';
$permissionPosition = strpos($readBlock, "require_permission(self::PAGE_SETTINGS, 'edit')");
$pathPosition = strpos($readBlock, "realpath(FCPATH . 'wa-engine')");
whatsapp_secret_smoke_check(
    $permissionPosition !== false && $pathPosition !== false && $permissionPosition < $pathPosition,
    'api_env_read requires wa.settings:edit before resolving or reading the env file'
);

if (is_string($viewSource)) {
    whatsapp_secret_smoke_check(
        strpos($viewSource, "['bot_api_token']") === false,
        'settings view has no bot token data interpolation'
    );
    whatsapp_secret_smoke_check(
        preg_match('/name="bot_api_token"[^>]*type="text"|type="text"[^>]*name="bot_api_token"/', $viewSource) !== 1,
        'settings bot token field is not a readable text input'
    );
    whatsapp_secret_smoke_check(
        strpos($viewSource, 'value="local-dev-token"') === false
            && strpos($viewSource, 'e.DB_PASS') === false
            && strpos($viewSource, 'e.WA_TOKEN') === false,
        'settings UI does not seed or refill secret values'
    );
}

$fixtureRoot = sys_get_temp_dir() . '/finance-wa-secret-smoke-' . bin2hex(random_bytes(8));
$engineDir = $fixtureRoot . '/wa-engine';
$envPath = $engineDir . '/.env';
if (!mkdir($engineDir, 0700, true) && !is_dir($engineDir)) {
    fwrite(STDERR, "[FAIL] synthetic fixture directory could not be created\n");
    exit(1);
}

register_shutdown_function(static function () use ($envPath, $engineDir, $fixtureRoot): void {
    if (is_file($envPath) || is_link($envPath)) {
        @unlink($envPath);
    } elseif (is_dir($envPath)) {
        @rmdir($envPath);
    }
    @rmdir($engineDir);
    @rmdir($fixtureRoot);
});

$fixtureContent = implode("\n", [
    'WA_PORT=3070',
    'WA_TOKEN=' . WHATSAPP_SECRET_SMOKE_WA_TOKEN,
    'DB_HOST=127.0.0.1',
    'DB_USER=synthetic_user',
    'DB_PASS=' . WHATSAPP_SECRET_SMOKE_DB_PASS,
    'DB_NAME=synthetic_finance',
    'EXTRA_PRIVATE_SECRET=' . WHATSAPP_SECRET_SMOKE_EXTRA,
    '',
]);
whatsapp_secret_smoke_check(
    file_put_contents($envPath, $fixtureContent) !== false,
    'synthetic env fixture was created'
);

$denied = whatsapp_secret_smoke_child('read-denied', $fixtureRoot);
whatsapp_secret_smoke_check(
    $denied['exit_code'] === 0
        && ($denied['json']['denied'] ?? false) === true
        && ($denied['json']['permission_calls'][0][1] ?? '') === 'edit',
    'view-only caller is denied at the edit permission gate'
);
whatsapp_secret_smoke_check(
    strpos($denied['stdout'], WHATSAPP_SECRET_SMOKE_DB_PASS) === false
        && strpos($denied['stdout'], WHATSAPP_SECRET_SMOKE_WA_TOKEN) === false,
    'denied response contains no env sentinel'
);

$read = whatsapp_secret_smoke_child('read-edit', $fixtureRoot);
$readJson = $read['json'] ?? [];
whatsapp_secret_smoke_check(
    $read['exit_code'] === 0
        && ($readJson['ok'] ?? false) === true
        && ($readJson['exists'] ?? false) === true
        && ($readJson['env']['DB_PASS']['configured'] ?? false) === true
        && !isset($readJson['env']['WA_TOKEN']),
    'edit caller receives known env status without exposing the retired WA_TOKEN key'
);
whatsapp_secret_smoke_check(
    strpos($read['stdout'], WHATSAPP_SECRET_SMOKE_DB_PASS) === false
        && strpos($read['stdout'], WHATSAPP_SECRET_SMOKE_WA_TOKEN) === false
        && strpos($read['stdout'], WHATSAPP_SECRET_SMOKE_EXTRA) === false
        && strpos($read['stdout'], 'EXTRA_PRIVATE_SECRET') === false,
    'env read JSON omits known and additional secret values and additional key names'
);

$saveBlank = whatsapp_secret_smoke_child('save-env-blank', $fixtureRoot);
$savedContent = @file_get_contents($envPath);
whatsapp_secret_smoke_check(
    $saveBlank['exit_code'] === 0 && ($saveBlank['json']['ok'] ?? false) === true,
    'blank-secret env save completes successfully'
);
whatsapp_secret_smoke_check(
    is_string($savedContent)
        && strpos($savedContent, 'DB_PASS=' . WHATSAPP_SECRET_SMOKE_DB_PASS) !== false
        && strpos($savedContent, 'WA_TOKEN=' . WHATSAPP_SECRET_SMOKE_WA_TOKEN) !== false
        && strpos($savedContent, 'EXTRA_PRIVATE_SECRET=' . WHATSAPP_SECRET_SMOKE_EXTRA) !== false,
    'blank managed secret and unmanaged legacy/additional secrets are preserved'
);
whatsapp_secret_smoke_check(
    is_string($savedContent) && preg_match('/^DB_HOST=$/m', $savedContent) === 1,
    'explicit blank non-secret env field remains distinguishable and is saved blank'
);
whatsapp_secret_smoke_check(
    strpos($saveBlank['stdout'], WHATSAPP_SECRET_SMOKE_DB_PASS) === false
        && strpos($saveBlank['stdout'], WHATSAPP_SECRET_SMOKE_WA_TOKEN) === false
        && strpos($saveBlank['stdout'], WHATSAPP_SECRET_SMOKE_EXTRA) === false,
    'successful env save response does not reflect preserved secrets'
);

$settingsBlank = whatsapp_secret_smoke_child('save-settings-blank', $fixtureRoot);
whatsapp_secret_smoke_check(
    $settingsBlank['exit_code'] === 0
        && ($settingsBlank['json']['token_preserved'] ?? false) === true
        && ($settingsBlank['json']['token_omitted_from_update'] ?? false) === true
        && ($settingsBlank['json']['flash_is_generic'] ?? false) === true,
    'blank settings token preserves the old DB value and uses a generic flash message'
);
whatsapp_secret_smoke_check(
    strpos($settingsBlank['stdout'], WHATSAPP_SECRET_SMOKE_WA_TOKEN) === false,
    'settings save harness output does not reflect the old token'
);

@unlink($envPath);
whatsapp_secret_smoke_check(mkdir($envPath, 0700), 'synthetic unwritable env target was created');
$saveError = whatsapp_secret_smoke_child('save-env-error', $fixtureRoot);
$saveErrorJson = $saveError['json'] ?? [];
whatsapp_secret_smoke_check(
    $saveError['exit_code'] === 0
        && ($saveErrorJson['ok'] ?? true) === false
        && !array_key_exists('content', $saveErrorJson)
        && !array_key_exists('path', $saveErrorJson),
    'env write error response contains only generic non-secret metadata'
);
whatsapp_secret_smoke_check(
    strpos($saveError['stdout'], WHATSAPP_SECRET_SMOKE_ERROR) === false
        && strpos($saveError['stdout'], WHATSAPP_SECRET_SMOKE_DB_PASS) === false
        && strpos($saveError['stdout'], WHATSAPP_SECRET_SMOKE_WA_TOKEN) === false,
    'env write error output does not reflect submitted or existing env content'
);

if ($whatsappSecretSmokeFailures !== []) {
    foreach ($whatsappSecretSmokeFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, '[FAIL] WhatsApp settings secret boundary smoke failed: '
        . count($whatsappSecretSmokeFailures) . " failure(s) across {$whatsappSecretSmokeChecks} checks.\n");
    exit(1);
}

echo "[PASS] WhatsApp settings secret boundary smoke: {$whatsappSecretSmokeChecks} checks.\n";
