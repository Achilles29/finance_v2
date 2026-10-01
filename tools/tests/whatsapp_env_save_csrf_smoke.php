<?php

declare(strict_types=1);

/**
 * DB-free smoke test for scoped WhatsApp .env-save CSRF.
 *
 * The real controller is loaded without its constructor. Rejected endpoint
 * requests use traced in-memory CI doubles and an unreachable stream wrapper,
 * so no runtime .env/log/secret, filesystem write, command, or DB is touched.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);
defined('FCPATH') || define('FCPATH', 'waenvsavecsrfprobe://root/');

final class WhatsappEnvSaveCsrfSmokeTrace
{
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public static function add(string $event): void
    {
        self::$events[] = $event;
    }
}

final class WhatsappEnvSaveCsrfSmokeStream
{
    public function url_stat($path, $flags)
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('filesystem');
        return false;
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        WhatsappEnvSaveCsrfSmokeTrace::add(strpbrk((string)$mode, 'waxc+') === false ? 'filesystem' : 'write');
        return false;
    }

    public function dir_opendir($path, $options): bool
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('filesystem');
        return false;
    }
}

if (!in_array('waenvsavecsrfprobe', stream_get_wrappers(), true)) {
    stream_wrapper_register('waenvsavecsrfprobe', WhatsappEnvSaveCsrfSmokeStream::class);
}

final class WhatsappEnvSaveCsrfSmokeInput
{
    private string $requestMethod;
    private array $headers;
    private string $queryToken;
    private string $bodyToken;
    private string $rawInput;

    public function __construct(
        string $requestMethod,
        array $headers = [],
        string $queryToken = '',
        string $bodyToken = '',
        string $rawInput = ''
    ) {
        $this->requestMethod = strtoupper($requestMethod);
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->headers[self::canonicalHeaderName((string)$name)] = (string)$value;
        }
        $this->queryToken = $queryToken;
        $this->bodyToken = $bodyToken;
        $this->rawInput = $rawInput;
    }

    private static function canonicalHeaderName(string $name): string
    {
        $name = str_replace(['_', '-'], ' ', strtolower($name));
        return str_replace(' ', '-', ucwords($name));
    }

    public function method($upper = false): string
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('csrf:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        WhatsappEnvSaveCsrfSmokeTrace::add('csrf:header:' . $key);
        return (string)($this->headers[$key] ?? '');
    }

    public function get($key = null, $xssClean = false): string
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('query');
        return $this->queryToken;
    }

    public function post($key = null, $xssClean = false): string
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('body');
        return $this->bodyToken;
    }

    public function __get($key)
    {
        $key = (string)$key;
        WhatsappEnvSaveCsrfSmokeTrace::add('property:' . $key);
        if ($key === 'raw_input_stream') {
            return $this->rawInput;
        }
        throw new RuntimeException('Unexpected input property: ' . $key);
    }
}

final class WhatsappEnvSaveCsrfSmokeSession
{
    public array $reads = [];
    public array $writes = [];
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $key = (string)$key;
        WhatsappEnvSaveCsrfSmokeTrace::add('session:read:' . $key);
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        WhatsappEnvSaveCsrfSmokeTrace::add('session:write:' . $key);
        $this->writes[$key] = (string)$value;
        $this->values[$key] = $value;
    }
}

final class WhatsappEnvSaveCsrfSmokeOutput
{
    public ?int $status = null;
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
}

final class WhatsappEnvSaveCsrfSmokeDb
{
    public function __call(string $method, array $arguments): self
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('db:' . $method);
        return $this;
    }
}

final class WhatsappEnvSaveCsrfSmokePermissionDenied extends RuntimeException
{
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public bool $permissionAllowed = true;
    public array $permissionCalls = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        WhatsappEnvSaveCsrfSmokeTrace::add('rbac');
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new WhatsappEnvSaveCsrfSmokePermissionDenied('permission denied');
        }
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

$whatsappEnvSaveCsrfChecks = 0;
$whatsappEnvSaveCsrfFailures = [];

function whatsapp_env_save_csrf_check(bool $condition, string $message): void
{
    global $whatsappEnvSaveCsrfChecks, $whatsappEnvSaveCsrfFailures;
    $whatsappEnvSaveCsrfChecks++;
    if (!$condition) {
        $whatsappEnvSaveCsrfFailures[] = $message;
    }
}

function whatsapp_env_save_csrf_method_block(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
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

function whatsapp_env_save_csrf_controller(
    string $requestMethod,
    array $headers = [],
    array $sessionValues = [],
    bool $permissionAllowed = true,
    string $queryToken = '',
    string $bodyToken = '',
    string $rawInput = ''
): array {
    WhatsappEnvSaveCsrfSmokeTrace::reset();
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WhatsappEnvSaveCsrfSmokeInput(
        $requestMethod,
        $headers,
        $queryToken,
        $bodyToken,
        $rawInput
    );
    $controller->session = new WhatsappEnvSaveCsrfSmokeSession($sessionValues);
    $controller->output = new WhatsappEnvSaveCsrfSmokeOutput();
    $controller->db = new WhatsappEnvSaveCsrfSmokeDb();
    $controller->permissionAllowed = $permissionAllowed;

    return [$controller, $reflection];
}

function whatsapp_env_save_csrf_invoke_endpoint(
    string $requestMethod,
    array $headers = [],
    array $sessionValues = [],
    bool $permissionAllowed = true,
    string $queryToken = '',
    string $bodyToken = '',
    string $rawInput = ''
): array {
    [$controller] = whatsapp_env_save_csrf_controller(
        $requestMethod,
        $headers,
        $sessionValues,
        $permissionAllowed,
        $queryToken,
        $bodyToken,
        $rawInput
    );
    $caught = null;
    try {
        $controller->api_env_save();
    } catch (Throwable $exception) {
        $caught = $exception;
    }

    return [$controller, $caught, WhatsappEnvSaveCsrfSmokeTrace::$events];
}

function whatsapp_env_save_csrf_rejection(
    WhatsappEnvSaveCsrfSmokeOutput $output,
    int $expectedStatus,
    string $label
): void {
    whatsapp_env_save_csrf_check($output->status === $expectedStatus, $label . ' returns HTTP ' . $expectedStatus);
    whatsapp_env_save_csrf_check($output->contentType === 'application/json', $label . ' returns JSON content type');
    $decoded = json_decode($output->body, true);
    whatsapp_env_save_csrf_check(
        is_array($decoded) && ($decoded['ok'] ?? null) === false,
        $label . ' returns a JSON rejection body'
    );
}

function whatsapp_env_save_csrf_no_rejected_side_effect(array $events, string $label): void
{
    $forbidden = array_values(array_filter(
        $events,
        static fn(string $event): bool => $event === 'filesystem'
            || $event === 'write'
            || $event === 'property:raw_input_stream'
            || $event === 'query'
            || $event === 'body'
            || strpos($event, 'db:') === 0
    ));
    whatsapp_env_save_csrf_check(
        $forbidden === [],
        $label . ' reaches no payload, query/body fallback, filesystem/write, or DB dependency'
    );
}

function whatsapp_env_save_csrf_render_view(string $path, array $variables): string
{
    require_once __DIR__ . '/module_notification_view_stub.php';
    $renderer = new class {
        public $session;
        public $load;

        public function __construct()
        {
            $this->session = new class {
                public function flashdata($key)
                {
                    return null;
                }
            };
        }

        public function render(string $path, array $variables): string
        {
            $this->load = new ModuleNotificationViewStub();
            extract($variables, EXTR_SKIP);
            ob_start();
            include $path;
            return (string)ob_get_clean();
        }
    };

    return $renderer->render($path, $variables);
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return '/index.php/' . ltrim($path, '/');
    }
}

if (!function_exists('html_escape')) {
    function html_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Whatsapp.php';
$viewPath = $root . '/application/views/wa/settings.php';
$controllerSource = file_get_contents($controllerPath);
$viewSource = file_get_contents($viewPath);

$sessionKey = 'wa_env_save_csrf';
$browserHeader = 'X-Wa-Env-Save-CSRF';
$canonicalHeader = 'X-Wa-Env-Save-Csrf';
$validToken = str_repeat('a', 64);
$otherToken = str_repeat('b', 64);
$renderSentinel = str_repeat('d', 64);

whatsapp_env_save_csrf_check(is_string($controllerSource), 'Whatsapp controller source is readable');
whatsapp_env_save_csrf_check(is_string($viewSource), 'WhatsApp settings view source is readable');
whatsapp_env_save_csrf_check(
    strpos($controllerSource, "private const WA_ENV_SAVE_CSRF_SESSION_KEY = '" . $sessionKey . "';") !== false,
    'controller defines the dedicated env-save session scope'
);
whatsapp_env_save_csrf_check(
    strpos($controllerSource, "private const WA_ENV_SAVE_CSRF_HEADER = '" . $browserHeader . "';") !== false,
    'controller defines the browser env-save header contract'
);
whatsapp_env_save_csrf_check(
    strpos($controllerSource, "private const WA_ENV_SAVE_CSRF_CI_HEADER = '" . $canonicalHeader . "';") !== false,
    'controller defines the canonical CI env-save header lookup'
);

$guardBlock = whatsapp_env_save_csrf_method_block($controllerSource, 'require_wa_env_save_csrf');
$tokenBlock = whatsapp_env_save_csrf_method_block($controllerSource, 'wa_env_save_csrf');
$rejectBlock = whatsapp_env_save_csrf_method_block($controllerSource, 'reject_wa_env_save_csrf');
$endpointBlock = whatsapp_env_save_csrf_method_block($controllerSource, 'api_env_save');
$settingsBlock = whatsapp_env_save_csrf_method_block($controllerSource, 'settings');

whatsapp_env_save_csrf_check(
    strpos($tokenBlock, 'userdata(self::WA_ENV_SAVE_CSRF_SESSION_KEY)') !== false
        && strpos($tokenBlock, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D', \$token)") !== false
        && strpos($tokenBlock, 'set_userdata(self::WA_ENV_SAVE_CSRF_SESSION_KEY, $token)') !== false,
    'token helper uses a scoped 32-byte random lowercase 64-hex token'
);
whatsapp_env_save_csrf_check(
    strpos($guardBlock, "method(true) !== 'POST'") !== false
        && strpos($guardBlock, 'reject_wa_env_save_csrf(405') !== false,
    'env-save guard rejects every non-POST method with 405'
);
whatsapp_env_save_csrf_check(
    strpos($guardBlock, 'get_request_header(self::WA_ENV_SAVE_CSRF_CI_HEADER, true)') !== false
        && substr_count($guardBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D'") === 2
        && strpos($guardBlock, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardBlock, 'reject_wa_env_save_csrf(403') !== false,
    'guard strictly validates lowercase header/session tokens and rejects invalid values with 403'
);
whatsapp_env_save_csrf_check(
    strpos($guardBlock, '$this->input->get(') === false
        && strpos($guardBlock, '$this->input->post(') === false
        && strpos($guardBlock, 'raw_input_stream') === false,
    'guard has no query, form body, or raw body fallback'
);
whatsapp_env_save_csrf_check(
    strpos($tokenBlock, 'WA_ENGINE_CONTROL') === false
        && strpos($guardBlock, 'WA_ENGINE_CONTROL') === false
        && strpos($endpointBlock, 'require_wa_engine_control_csrf') === false,
    'env-save token, guard, and endpoint do not reuse the engine-control scope'
);
whatsapp_env_save_csrf_check(
    strpos($rejectBlock, 'set_status_header($statusCode)') !== false
        && strpos($rejectBlock, "set_content_type('application/json')") !== false
        && strpos($rejectBlock, "'ok' => false") !== false,
    'env-save rejection helper emits a JSON HTTP error'
);

$permissionPosition = strpos($endpointBlock, "require_permission(self::PAGE_SETTINGS, 'edit')");
$guardPosition = strpos($endpointBlock, 'require_wa_env_save_csrf()');
$payloadPosition = strpos($endpointBlock, 'raw_input_stream');
$pathPosition = strpos($endpointBlock, "realpath(FCPATH . 'wa-engine')");
$readPosition = strpos($endpointBlock, 'file_get_contents($envFile)');
$updatesPosition = strpos($endpointBlock, 'waEnvUpdatesFromPayload($payload)');
$mergePosition = strpos($endpointBlock, 'mergeWaEnvContent($existingContent, $updates)');
$writePosition = strpos($endpointBlock, 'writeWaEnvAtomically($envFile, $content)');
whatsapp_env_save_csrf_check(
    $permissionPosition !== false
        && $guardPosition !== false
        && $payloadPosition !== false
        && $pathPosition !== false
        && $readPosition !== false
        && $updatesPosition !== false
        && $mergePosition !== false
        && $writePosition !== false
        && substr_count($endpointBlock, 'require_wa_env_save_csrf()') === 1
        && $permissionPosition < $guardPosition
        && $guardPosition < $payloadPosition
        && $payloadPosition < $pathPosition
        && $pathPosition < $readPosition
        && $readPosition < $updatesPosition
        && $updatesPosition < $mergePosition
        && $mergePosition < $writePosition,
    'api_env_save keeps RBAC -> method/scoped CSRF -> payload -> path/read -> updates/merge -> atomic write order'
);

$atomicWriteBlock = whatsapp_env_save_csrf_method_block($controllerSource, 'writeWaEnvAtomically');
whatsapp_env_save_csrf_check(
    strpos($atomicWriteBlock, 'tempnam($directory,') !== false
        && strpos($atomicWriteBlock, 'file_put_contents($temporary, $content, LOCK_EX)') !== false
        && strpos($atomicWriteBlock, 'rename($temporary, $envFile)') !== false
        && strpos($atomicWriteBlock, 'chmod($envFile, 0660)') !== false,
    'atomic env writer preserves the old file until replace and retains runtime group write access'
);

$canEditPosition = strpos($settingsBlock, '$canEdit = $this->can(self::PAGE_SETTINGS, \'edit\');');
$conditionalPosition = strpos($settingsBlock, 'if ($canEdit) {', $canEditPosition === false ? 0 : $canEditPosition);
$tokenRenderPosition = strpos($settingsBlock, '$this->wa_env_save_csrf()', $conditionalPosition === false ? 0 : $conditionalPosition);
$renderPosition = strpos($settingsBlock, "\$this->render('wa/settings', \$viewData)");
whatsapp_env_save_csrf_check(
    $canEditPosition !== false
        && $conditionalPosition !== false
        && $tokenRenderPosition !== false
        && $renderPosition !== false
        && $canEditPosition < $conditionalPosition
        && $conditionalPosition < $tokenRenderPosition
        && $tokenRenderPosition < $renderPosition,
    'settings mints and passes the env-save token only inside the edit branch'
);

[$controller, $caught, $events] = whatsapp_env_save_csrf_invoke_endpoint(
    'POST',
    [$browserHeader => $validToken],
    [$sessionKey => $validToken],
    false,
    $validToken,
    $validToken,
    json_encode(['DB_HOST' => 'synthetic.invalid']) ?: '{}'
);
whatsapp_env_save_csrf_check(
    $caught instanceof WhatsappEnvSaveCsrfSmokePermissionDenied,
    'RBAC denial stops api_env_save'
);
whatsapp_env_save_csrf_check(
    $controller->permissionCalls === [['wa.settings', 'edit']],
    'api_env_save checks wa.settings:edit'
);
whatsapp_env_save_csrf_check($events === ['rbac'], 'RBAC denial occurs before method, CSRF, payload, filesystem/write, or DB');

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    $label = 'api_env_save ' . $method;
    [$controller, $caught, $events] = whatsapp_env_save_csrf_invoke_endpoint(
        $method,
        [$browserHeader => $validToken],
        [$sessionKey => $validToken],
        true,
        $validToken,
        $validToken,
        json_encode(['DB_HOST' => 'synthetic.invalid']) ?: '{}'
    );
    whatsapp_env_save_csrf_check($caught === null, $label . ' returns normally after rejection');
    whatsapp_env_save_csrf_rejection($controller->output, 405, $label);
    whatsapp_env_save_csrf_check($events === ['rbac', 'csrf:method'], $label . ' rejects before header and payload');
    whatsapp_env_save_csrf_no_rejected_side_effect($events, $label);
}

$invalidCases = [
    'missing' => [[], [$sessionKey => $validToken]],
    'malformed uppercase' => [[$browserHeader => str_repeat('A', 64)], [$sessionKey => $validToken]],
    'mismatch' => [[$browserHeader => $otherToken], [$sessionKey => $validToken]],
];
foreach ($invalidCases as $case => [$headers, $sessionValues]) {
    $label = 'api_env_save ' . $case;
    [$controller, $caught, $events] = whatsapp_env_save_csrf_invoke_endpoint(
        'POST',
        $headers,
        $sessionValues,
        true,
        $validToken,
        $validToken,
        json_encode(['DB_HOST' => 'synthetic.invalid']) ?: '{}'
    );
    whatsapp_env_save_csrf_check($caught === null, $label . ' returns normally after rejection');
    whatsapp_env_save_csrf_rejection($controller->output, 403, $label);
    whatsapp_env_save_csrf_check(
        array_slice($events, 0, 3) === ['rbac', 'csrf:method', 'csrf:header:' . $canonicalHeader],
        $label . ' checks RBAC, method, and canonical header in order'
    );
    whatsapp_env_save_csrf_no_rejected_side_effect($events, $label);
}

[$controller, $reflection] = whatsapp_env_save_csrf_controller(
    'POST',
    [],
    [$sessionKey => $validToken],
    true,
    $validToken,
    $validToken,
    json_encode(['csrf' => $validToken]) ?: '{}'
);
$guardResult = $reflection->getMethod('require_wa_env_save_csrf')->invoke($controller);
whatsapp_env_save_csrf_check($guardResult === false, 'body/query-only token is rejected');
whatsapp_env_save_csrf_rejection($controller->output, 403, 'body/query-only token');
whatsapp_env_save_csrf_check(
    WhatsappEnvSaveCsrfSmokeTrace::$events === ['csrf:method', 'csrf:header:' . $canonicalHeader],
    'guard does not read query, form body, raw body, filesystem, write, or DB for a body/query-only token'
);

foreach ([$browserHeader => 'browser', $canonicalHeader => 'canonical CI'] as $header => $label) {
    [$controller, $reflection] = whatsapp_env_save_csrf_controller(
        'POST',
        [$header => $validToken],
        [$sessionKey => $validToken]
    );
    $guardResult = $reflection->getMethod('require_wa_env_save_csrf')->invoke($controller);
    whatsapp_env_save_csrf_check($guardResult === true, 'valid ' . $label . ' header is accepted');
    whatsapp_env_save_csrf_check($controller->output->status === null, 'valid ' . $label . ' header emits no rejection');
    whatsapp_env_save_csrf_check(
        WhatsappEnvSaveCsrfSmokeTrace::$events === [
            'csrf:method',
            'csrf:header:' . $canonicalHeader,
            'session:read:' . $sessionKey,
        ],
        'valid ' . $label . ' header uses only method, canonical header, and env-save session scope'
    );
}

[$controller, $reflection] = whatsapp_env_save_csrf_controller('GET');
$generatedToken = $reflection->getMethod('wa_env_save_csrf')->invoke($controller);
whatsapp_env_save_csrf_check(
    is_string($generatedToken) && preg_match('/\A[0-9a-f]{64}\z/D', $generatedToken) === 1,
    'token helper generates lowercase 64-hex output'
);
whatsapp_env_save_csrf_check(
    isset($controller->session->writes[$sessionKey])
        && $controller->session->writes[$sessionKey] === $generatedToken,
    'token helper stores generated output in the dedicated env-save session scope'
);
whatsapp_env_save_csrf_check(
    $controller->session->reads === [$sessionKey]
        && !isset($controller->session->writes['wa_engine_control_csrf']),
    'token helper neither reads nor writes the engine-control session scope'
);

$commonViewData = [
    'settings' => [],
    'bot_api_token_configured' => false,
    'session' => [],
    'wa_engine_control_csrf_token' => str_repeat('c', 64),
    'wa_env_save_csrf_token' => $renderSentinel,
];
$viewOnlyOutput = whatsapp_env_save_csrf_render_view($viewPath, $commonViewData + ['can_edit' => false]);
$editOutput = whatsapp_env_save_csrf_render_view($viewPath, $commonViewData + ['can_edit' => true]);
whatsapp_env_save_csrf_check(
    strpos($viewOnlyOutput, $renderSentinel) === false,
    'view-only rendering does not expose an env-save token even if supplied'
);
whatsapp_env_save_csrf_check(
    strpos($editOutput, $renderSentinel) !== false,
    'edit rendering includes its synthetic env-save token'
);

$saveStart = strpos($viewSource, "document.getElementById('btn-env-save')?.addEventListener");
$saveEnd = $saveStart === false ? false : strpos($viewSource, "// ─── Reset Sesi WA", $saveStart);
$saveBlock = ($saveStart === false || $saveEnd === false)
    ? ''
    : substr($viewSource, $saveStart, $saveEnd - $saveStart);
whatsapp_env_save_csrf_check(
    strpos($saveBlock, "site_url('wa/api/env-save')") !== false
        && strpos($saveBlock, "method: 'POST'") !== false
        && strpos($saveBlock, "'X-Wa-Env-Save-CSRF': waEnvSaveCsrfToken") !== false
        && strpos($saveBlock, 'body: JSON.stringify(payload)') !== false,
    'env-save UI sends JSON by POST with the dedicated browser header'
);
whatsapp_env_save_csrf_check(
    strpos($saveBlock, 'waEngineControlHeaders()') === false
        && strpos($saveBlock, 'X-Wa-Engine-Control-CSRF') === false,
    'env-save UI does not reuse engine-control CSRF'
);

if ($whatsappEnvSaveCsrfFailures !== []) {
    fwrite(STDERR, 'FAIL: ' . count($whatsappEnvSaveCsrfFailures) . ' of '
        . $whatsappEnvSaveCsrfChecks . " checks failed\n");
    foreach ($whatsappEnvSaveCsrfFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo 'PASS: ' . $whatsappEnvSaveCsrfChecks
    . " WhatsApp env-save CSRF checks; DB/runtime-secret/log free.\n";
