<?php

declare(strict_types=1);

/**
 * DB-free smoke test for scoped WhatsApp engine-control CSRF.
 *
 * The real Whatsapp controller is loaded without its constructor. Rejected
 * requests run against synthetic CI dependencies and an unreachable stream
 * wrapper, so the test never reads runtime files/logs, executes commands, or
 * opens a database connection.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);
defined('FCPATH') || define('FCPATH', 'waenginecsrfprobe://root/');

final class WhatsappEngineControlCsrfSmokeTrace
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

final class WhatsappEngineControlCsrfSmokeStream
{
    public function url_stat($path, $flags)
    {
        WhatsappEngineControlCsrfSmokeTrace::add('filesystem');
        return false;
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        WhatsappEngineControlCsrfSmokeTrace::add('filesystem');
        return false;
    }

    public function dir_opendir($path, $options): bool
    {
        WhatsappEngineControlCsrfSmokeTrace::add('filesystem');
        return false;
    }
}

if (!in_array('waenginecsrfprobe', stream_get_wrappers(), true)) {
    stream_wrapper_register('waenginecsrfprobe', WhatsappEngineControlCsrfSmokeStream::class);
}

final class WhatsappEngineControlCsrfSmokeInput
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
            $this->headers[self::normalizeCgiHeaderName((string)$name)] = (string)$value;
        }
        $this->queryToken = $queryToken;
        $this->bodyToken = $bodyToken;
        $this->rawInput = $rawInput;
    }

    private static function normalizeCgiHeaderName(string $name): string
    {
        $name = str_replace(['_', '-'], ' ', strtolower($name));
        return str_replace(' ', '-', ucwords($name));
    }

    public function method($upper = false): string
    {
        WhatsappEngineControlCsrfSmokeTrace::add('csrf:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        WhatsappEngineControlCsrfSmokeTrace::add('csrf:header:' . $key);
        return (string)($this->headers[$key] ?? '');
    }

    public function get($key = null, $xssClean = false): string
    {
        WhatsappEngineControlCsrfSmokeTrace::add('query');
        return $this->queryToken;
    }

    public function post($key = null, $xssClean = false): string
    {
        WhatsappEngineControlCsrfSmokeTrace::add('body');
        return $this->bodyToken;
    }

    public function __get($key)
    {
        $key = (string)$key;
        WhatsappEngineControlCsrfSmokeTrace::add('property:' . $key);
        if ($key === 'raw_input_stream') {
            return $this->rawInput;
        }
        throw new RuntimeException('Unexpected input property: ' . $key);
    }
}

final class WhatsappEngineControlCsrfSmokeSession
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
        WhatsappEngineControlCsrfSmokeTrace::add('session:read:' . $key);
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        WhatsappEngineControlCsrfSmokeTrace::add('session:write:' . $key);
        $this->writes[$key] = (string)$value;
        $this->values[$key] = $value;
    }
}

final class WhatsappEngineControlCsrfSmokeOutput
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

final class WhatsappEngineControlCsrfSmokeDb
{
    public array $calls = [];

    public function __call(string $method, array $arguments): self
    {
        WhatsappEngineControlCsrfSmokeTrace::add('db:' . $method);
        $this->calls[] = $method;
        return $this;
    }
}

final class WhatsappEngineControlCsrfSmokePermissionDenied extends RuntimeException
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
        WhatsappEngineControlCsrfSmokeTrace::add('rbac');
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new WhatsappEngineControlCsrfSmokePermissionDenied('permission denied');
        }
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

$whatsappEngineControlCsrfChecks = 0;
$whatsappEngineControlCsrfFailures = [];

function whatsapp_engine_control_csrf_check(bool $condition, string $message): void
{
    global $whatsappEngineControlCsrfChecks, $whatsappEngineControlCsrfFailures;
    $whatsappEngineControlCsrfChecks++;
    if (!$condition) {
        $whatsappEngineControlCsrfFailures[] = $message;
    }
}

function whatsapp_engine_control_csrf_method_block(string $source, string $method): string
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

function whatsapp_engine_control_csrf_controller(
    string $requestMethod,
    array $headers = [],
    array $sessionValues = [],
    bool $permissionAllowed = true,
    string $queryToken = '',
    string $bodyToken = '',
    string $rawInput = ''
): array {
    WhatsappEngineControlCsrfSmokeTrace::reset();
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WhatsappEngineControlCsrfSmokeInput(
        $requestMethod,
        $headers,
        $queryToken,
        $bodyToken,
        $rawInput
    );
    $controller->session = new WhatsappEngineControlCsrfSmokeSession($sessionValues);
    $controller->output = new WhatsappEngineControlCsrfSmokeOutput();
    $controller->db = new WhatsappEngineControlCsrfSmokeDb();
    $controller->permissionAllowed = $permissionAllowed;

    return [$controller, $reflection];
}

function whatsapp_engine_control_csrf_invoke_endpoint(
    string $endpoint,
    string $requestMethod,
    array $headers = [],
    array $sessionValues = [],
    bool $permissionAllowed = true,
    string $queryToken = '',
    string $bodyToken = '',
    string $rawInput = ''
): array {
    [$controller, $reflection] = whatsapp_engine_control_csrf_controller(
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
        $reflection->getMethod($endpoint)->invoke($controller);
    } catch (Throwable $exception) {
        $caught = $exception;
    }

    return [$controller, $caught, WhatsappEngineControlCsrfSmokeTrace::$events];
}

function whatsapp_engine_control_csrf_rejection(
    WhatsappEngineControlCsrfSmokeOutput $output,
    int $expectedStatus,
    string $label
): void {
    whatsapp_engine_control_csrf_check(
        $output->status === $expectedStatus,
        $label . ' returns HTTP ' . $expectedStatus
    );
    whatsapp_engine_control_csrf_check(
        $output->contentType === 'application/json',
        $label . ' returns JSON content type'
    );
    $decoded = json_decode($output->body, true);
    whatsapp_engine_control_csrf_check(
        is_array($decoded) && ($decoded['ok'] ?? null) === false,
        $label . ' returns a JSON rejection body'
    );
}

function whatsapp_engine_control_csrf_no_side_effect(array $events, string $label): void
{
    $sideEffects = array_values(array_filter(
        $events,
        static fn(string $event): bool => $event === 'filesystem'
            || strpos($event, 'db:') === 0
    ));
    whatsapp_engine_control_csrf_check(
        $sideEffects === [],
        $label . ' reaches no filesystem or DB dependency'
    );
}

function whatsapp_engine_control_csrf_render_view(string $path, array $variables): string
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

$sessionKey = 'wa_engine_control_csrf';
$browserHeader = 'X-Wa-Engine-Control-CSRF';
$canonicalHeader = 'X-Wa-Engine-Control-Csrf';
$validToken = str_repeat('a', 64);
$otherToken = str_repeat('b', 64);
$renderSentinel = str_repeat('c', 64);
$endpoints = ['api_engine_start', 'api_engine_stop', 'api_session_reset'];

whatsapp_engine_control_csrf_check(is_string($controllerSource), 'Whatsapp controller source is readable');
whatsapp_engine_control_csrf_check(is_string($viewSource), 'WhatsApp settings view source is readable');
whatsapp_engine_control_csrf_check(
    strpos($controllerSource, "private const WA_ENGINE_CONTROL_CSRF_SESSION_KEY = '" . $sessionKey . "';") !== false,
    'controller defines the dedicated engine-control session scope'
);
whatsapp_engine_control_csrf_check(
    strpos($controllerSource, "private const WA_ENGINE_CONTROL_CSRF_HEADER = '" . $browserHeader . "';") !== false,
    'controller defines the browser header contract'
);
whatsapp_engine_control_csrf_check(
    strpos($controllerSource, "private const WA_ENGINE_CONTROL_CSRF_CI_HEADER = '" . $canonicalHeader . "';") !== false,
    'controller defines the canonical CI header lookup'
);

$guardBlock = whatsapp_engine_control_csrf_method_block($controllerSource, 'require_wa_engine_control_csrf');
$tokenBlock = whatsapp_engine_control_csrf_method_block($controllerSource, 'wa_engine_control_csrf');
$rejectBlock = whatsapp_engine_control_csrf_method_block($controllerSource, 'reject_wa_engine_control_csrf');
$settingsBlock = whatsapp_engine_control_csrf_method_block($controllerSource, 'settings');

whatsapp_engine_control_csrf_check(
    strpos($tokenBlock, 'userdata(self::WA_ENGINE_CONTROL_CSRF_SESSION_KEY)') !== false
        && strpos($tokenBlock, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenBlock, 'set_userdata(self::WA_ENGINE_CONTROL_CSRF_SESSION_KEY, $token)') !== false,
    'token helper uses a session-bound 32-byte random token'
);
whatsapp_engine_control_csrf_check(
    strpos($guardBlock, "method(true) !== 'POST'") !== false
        && strpos($guardBlock, 'reject_wa_engine_control_csrf(405') !== false,
    'guard rejects every non-POST method with 405'
);
whatsapp_engine_control_csrf_check(
    strpos($guardBlock, 'get_request_header(self::WA_ENGINE_CONTROL_CSRF_CI_HEADER, true)') !== false
        && strpos($guardBlock, "preg_match('/\\A[0-9a-fA-F]{64}\\z/D', \$providedToken)") !== false
        && strpos($guardBlock, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardBlock, 'reject_wa_engine_control_csrf(403') !== false,
    'guard validates the exact header/session token and rejects invalid values with 403'
);
whatsapp_engine_control_csrf_check(
    strpos($guardBlock, '$this->input->get(') === false
        && strpos($guardBlock, '$this->input->post(') === false
        && strpos($guardBlock, 'raw_input_stream') === false,
    'guard contains no body/query token fallback'
);
whatsapp_engine_control_csrf_check(
    strpos($rejectBlock, 'set_status_header($statusCode)') !== false
        && strpos($rejectBlock, "set_content_type('application/json')") !== false
        && strpos($rejectBlock, "'ok' => false") !== false,
    'rejection helper emits a JSON HTTP error'
);

$canEditPosition = strpos($settingsBlock, '$canEdit = $this->can(self::PAGE_SETTINGS, \'edit\');');
$conditionalPosition = strpos($settingsBlock, 'if ($canEdit) {', $canEditPosition === false ? 0 : $canEditPosition);
$tokenRenderPosition = strpos($settingsBlock, '$this->wa_engine_control_csrf()', $conditionalPosition === false ? 0 : $conditionalPosition);
$renderPosition = strpos($settingsBlock, "\$this->render('wa/settings', \$viewData)");
whatsapp_engine_control_csrf_check(
    $canEditPosition !== false
        && $conditionalPosition !== false
        && $tokenRenderPosition !== false
        && $renderPosition !== false
        && $canEditPosition < $conditionalPosition
        && $conditionalPosition < $tokenRenderPosition
        && $tokenRenderPosition < $renderPosition,
    'settings creates and passes the token only inside the edit-permission branch'
);

$endpointOrderingOk = true;
foreach ($endpoints as $endpoint) {
    $block = whatsapp_engine_control_csrf_method_block($controllerSource, $endpoint);
    $permissionPosition = strpos($block, "require_permission(self::PAGE_SETTINGS, 'edit')");
    $guardPosition = strpos($block, 'require_wa_engine_control_csrf()');
    $dependencyMarkers = $endpoint === 'api_session_reset'
        ? ['realpath(', 'function_exists(\'exec\')', 'exec(', '$this->db->']
        : ['function_exists(\'exec\')', 'realpath(', 'engineStatusSnapshot(', 'exec(', '$this->db->'];
    $dependencyPositions = [];
    foreach ($dependencyMarkers as $marker) {
        $position = strpos($block, $marker);
        if ($position !== false) {
            $dependencyPositions[] = $position;
        }
    }
    $firstDependency = $dependencyPositions === [] ? false : min($dependencyPositions);
    $ordered = $block !== ''
        && substr_count($block, 'require_wa_engine_control_csrf()') === 1
        && $permissionPosition !== false
        && $guardPosition !== false
        && $firstDependency !== false
        && $permissionPosition < $guardPosition
        && $guardPosition < $firstDependency;
    $endpointOrderingOk = $endpointOrderingOk && $ordered;
    whatsapp_engine_control_csrf_check(
        $ordered,
        $endpoint . ' keeps RBAC -> scoped CSRF -> dependency/filesystem/exec/DB order'
    );
}

foreach ($endpoints as $endpoint) {
    [$controller, $caught, $events] = whatsapp_engine_control_csrf_invoke_endpoint(
        $endpoint,
        'POST',
        [$browserHeader => $validToken],
        [$sessionKey => $validToken],
        false
    );
    whatsapp_engine_control_csrf_check(
        $caught instanceof WhatsappEngineControlCsrfSmokePermissionDenied,
        $endpoint . ' stops on denied RBAC'
    );
    whatsapp_engine_control_csrf_check(
        $controller->permissionCalls === [['wa.settings', 'edit']],
        $endpoint . ' checks wa.settings:edit'
    );
    whatsapp_engine_control_csrf_check(
        $events === ['rbac'],
        $endpoint . ' runs RBAC before any CSRF or side-effect dependency'
    );
}

if ($endpointOrderingOk) {
    foreach ($endpoints as $endpoint) {
        foreach (['GET', 'PUT', 'PATCH'] as $method) {
            $label = $endpoint . ' ' . $method;
            [$controller, $caught, $events] = whatsapp_engine_control_csrf_invoke_endpoint(
                $endpoint,
                $method,
                [$browserHeader => $validToken],
                [$sessionKey => $validToken]
            );
            whatsapp_engine_control_csrf_check($caught === null, $label . ' returns normally after rejection');
            whatsapp_engine_control_csrf_rejection($controller->output, 405, $label);
            whatsapp_engine_control_csrf_check(
                array_slice($events, 0, 2) === ['rbac', 'csrf:method'],
                $label . ' checks RBAC before the method guard'
            );
            whatsapp_engine_control_csrf_check(
                !in_array('csrf:header:' . $canonicalHeader, $events, true),
                $label . ' rejects before reading the CSRF header'
            );
            whatsapp_engine_control_csrf_no_side_effect($events, $label);
        }

        $invalidCases = [
            'missing' => [[], [$sessionKey => $validToken]],
            'malformed' => [[$browserHeader => str_repeat('g', 64)], [$sessionKey => $validToken]],
            'mismatch' => [[$browserHeader => $otherToken], [$sessionKey => $validToken]],
        ];
        foreach ($invalidCases as $case => [$headers, $sessionValues]) {
            $label = $endpoint . ' ' . $case;
            [$controller, $caught, $events] = whatsapp_engine_control_csrf_invoke_endpoint(
                $endpoint,
                'POST',
                $headers,
                $sessionValues
            );
            whatsapp_engine_control_csrf_check($caught === null, $label . ' returns normally after rejection');
            whatsapp_engine_control_csrf_rejection($controller->output, 403, $label);
            whatsapp_engine_control_csrf_check(
                array_slice($events, 0, 3) === ['rbac', 'csrf:method', 'csrf:header:' . $canonicalHeader],
                $label . ' checks RBAC before the canonical header guard'
            );
            whatsapp_engine_control_csrf_no_side_effect($events, $label);
        }
    }
}

[$controller, $reflection] = whatsapp_engine_control_csrf_controller(
    'POST',
    [],
    [$sessionKey => $validToken],
    true,
    $validToken,
    $validToken,
    json_encode(['csrf' => $validToken]) ?: ''
);
$guardResult = $reflection->getMethod('require_wa_engine_control_csrf')->invoke($controller);
whatsapp_engine_control_csrf_check($guardResult === false, 'body/query-only token is rejected');
whatsapp_engine_control_csrf_rejection($controller->output, 403, 'body/query-only token');
whatsapp_engine_control_csrf_check(
    !in_array('query', WhatsappEngineControlCsrfSmokeTrace::$events, true)
        && !in_array('body', WhatsappEngineControlCsrfSmokeTrace::$events, true)
        && !in_array('property:raw_input_stream', WhatsappEngineControlCsrfSmokeTrace::$events, true),
    'guard does not read query, form body, or raw body'
);

foreach ([$browserHeader => 'browser', $canonicalHeader => 'canonical CI'] as $header => $label) {
    [$controller, $reflection] = whatsapp_engine_control_csrf_controller(
        'POST',
        [$header => $validToken],
        [$sessionKey => $validToken]
    );
    $guardResult = $reflection->getMethod('require_wa_engine_control_csrf')->invoke($controller);
    whatsapp_engine_control_csrf_check($guardResult === true, 'valid ' . $label . ' header is accepted');
    whatsapp_engine_control_csrf_check($controller->output->status === null, 'valid ' . $label . ' header emits no rejection');
    whatsapp_engine_control_csrf_check(
        WhatsappEngineControlCsrfSmokeTrace::$events === [
            'csrf:method',
            'csrf:header:' . $canonicalHeader,
            'session:read:' . $sessionKey,
        ],
        'valid ' . $label . ' header uses only method, canonical header, and scoped session'
    );
}

[$controller, $reflection] = whatsapp_engine_control_csrf_controller('GET');
$generatedToken = $reflection->getMethod('wa_engine_control_csrf')->invoke($controller);
whatsapp_engine_control_csrf_check(
    is_string($generatedToken) && preg_match('/\A[0-9a-f]{64}\z/D', $generatedToken) === 1,
    'token helper generates lowercase 64-hex output'
);
whatsapp_engine_control_csrf_check(
    isset($controller->session->writes[$sessionKey])
        && $controller->session->writes[$sessionKey] === $generatedToken,
    'token helper stores generated output in the dedicated session scope'
);

$commonViewData = [
    'settings' => [],
    'bot_api_token_configured' => false,
    'session' => [],
    'wa_engine_control_csrf_token' => $renderSentinel,
];
$viewOnlyOutput = whatsapp_engine_control_csrf_render_view(
    $viewPath,
    $commonViewData + ['can_edit' => false]
);
$editOutput = whatsapp_engine_control_csrf_render_view(
    $viewPath,
    $commonViewData + ['can_edit' => true]
);
whatsapp_engine_control_csrf_check(
    strpos($viewOnlyOutput, $renderSentinel) === false,
    'view-only rendering does not expose an engine-control token even if one is supplied'
);
whatsapp_engine_control_csrf_check(
    strpos($editOutput, $renderSentinel) !== false,
    'edit rendering includes its synthetic engine-control token'
);

$headerHelperStart = strpos($viewSource, 'function waEngineControlHeaders()');
$headerHelperEnd = $headerHelperStart === false ? false : strpos($viewSource, "\n}", $headerHelperStart);
$headerHelperBlock = ($headerHelperStart === false || $headerHelperEnd === false)
    ? ''
    : substr($viewSource, $headerHelperStart, $headerHelperEnd - $headerHelperStart + 2);
$engineActionBlock = whatsapp_engine_control_csrf_method_block($viewSource, 'engineAction');
$restartStart = strpos(
    $viewSource,
    "document.getElementById('btn-engine-restart')?.addEventListener"
);
$restartEnd = $restartStart === false ? false : strpos($viewSource, "document.getElementById('btn-engine-refresh')", $restartStart);
$restartBlock = ($restartStart === false || $restartEnd === false)
    ? ''
    : substr($viewSource, $restartStart, $restartEnd - $restartStart);
$resetStart = strpos(
    $viewSource,
    "document.getElementById('btn-session-reset')?.addEventListener"
);
$resetBlock = $resetStart === false ? '' : substr($viewSource, $resetStart);

whatsapp_engine_control_csrf_check(
    strpos($headerHelperBlock, "'" . $browserHeader . "': waEngineControlCsrfToken") !== false,
    'UI header helper carries the browser-facing CSRF header'
);
whatsapp_engine_control_csrf_check(
    strpos($engineActionBlock, "site_url('wa/api/engine-start')") !== false
        && strpos($engineActionBlock, "site_url('wa/api/engine-stop')") !== false
        && strpos($engineActionBlock, "method: 'POST'") !== false
        && strpos($engineActionBlock, 'headers: waEngineControlHeaders()') !== false,
    'shared Start/Stop caller sends POST with the scoped header'
);
whatsapp_engine_control_csrf_check(
    substr_count($restartBlock, 'headers: waEngineControlHeaders()') === 2
        && strpos($restartBlock, "site_url('wa/api/engine-stop')") !== false
        && strpos($restartBlock, "site_url('wa/api/engine-start')") !== false,
    'both restart requests carry the scoped header'
);
whatsapp_engine_control_csrf_check(
    strpos($resetBlock, "site_url('wa/api/session-reset')") !== false
        && strpos($resetBlock, "method: 'POST'") !== false
        && strpos($resetBlock, 'headers: waEngineControlHeaders()') !== false,
    'session reset caller sends POST with the scoped header'
);
whatsapp_engine_control_csrf_check(
    substr_count($viewSource, 'headers: waEngineControlHeaders()') === 4,
    'all four engine-control fetch sites use the scoped header helper'
);

if ($whatsappEngineControlCsrfFailures !== []) {
    fwrite(STDERR, 'FAIL: ' . count($whatsappEngineControlCsrfFailures) . ' of '
        . $whatsappEngineControlCsrfChecks . " checks failed\n");
    foreach ($whatsappEngineControlCsrfFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo 'PASS: ' . $whatsappEngineControlCsrfChecks
    . " WhatsApp engine-control CSRF checks; DB/runtime-secret/log free.\n";
