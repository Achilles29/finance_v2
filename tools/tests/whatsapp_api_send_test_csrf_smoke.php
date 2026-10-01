<?php

declare(strict_types=1);

/**
 * Behavior smoke for the scoped WhatsApp settings send-test CSRF boundary.
 *
 * Loads the controller without CI bootstrap and replaces outbound/log seams
 * with in-memory doubles. No DB, network, credential, upload, or runtime-log
 * access is performed.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_SEND_TEST_SESSION = 'wa_send_test_csrf';
const WA_SEND_TEST_HEADER = 'X-Wa-Send-Test-CSRF';
const WA_SEND_TEST_CI_HEADER = 'X-Wa-Send-Test-Csrf';
const WA_SEND_TEST_VALID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WA_SEND_TEST_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

final class WhatsappSendTestSmokeTrace
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

final class WhatsappSendTestSmokeDenied extends RuntimeException
{
}

final class WhatsappSendTestSmokeInput
{
    public int $rawReads = 0;
    public array $headerReads = [];
    public array $postReads = [];
    public array $getReads = [];

    private string $requestMethod;
    private array $headers = [];
    private string $rawInput;

    public function __construct(string $method, array $headers = [], string $rawInput = '')
    {
        $this->requestMethod = strtoupper($method);
        foreach ($headers as $name => $value) {
            $this->headers[self::canonicalHeaderName((string)$name)] = (string)$value;
        }
        $this->rawInput = $rawInput;
    }

    private static function canonicalHeaderName(string $name): string
    {
        $normalized = str_replace(['_', '-'], ' ', strtolower($name));
        return str_replace(' ', '-', ucwords($normalized));
    }

    public function method($upper = false): string
    {
        WhatsappSendTestSmokeTrace::add('method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get_request_header($name, $xssClean = false): string
    {
        $name = (string)$name;
        $this->headerReads[] = $name;
        WhatsappSendTestSmokeTrace::add('header:' . $name);
        return $this->headers[$name] ?? '';
    }

    public function post($key = null, $xssClean = false)
    {
        $this->postReads[] = (string)$key;
        WhatsappSendTestSmokeTrace::add('post');
        return null;
    }

    public function get($key = null, $xssClean = false)
    {
        $this->getReads[] = (string)$key;
        WhatsappSendTestSmokeTrace::add('get');
        return null;
    }

    public function __get($key)
    {
        if ((string)$key === 'raw_input_stream') {
            $this->rawReads++;
            WhatsappSendTestSmokeTrace::add('raw');
            return $this->rawInput;
        }
        throw new RuntimeException('Unexpected input property: ' . (string)$key);
    }
}

final class WhatsappSendTestSmokeSession
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
        $this->reads[] = $key;
        WhatsappSendTestSmokeTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->values[$key] = $value;
        $this->writes[$key] = (string)$value;
        WhatsappSendTestSmokeTrace::add('session:write:' . $key);
    }

    public function set_flashdata($key, $value): void
    {
        WhatsappSendTestSmokeTrace::add('flash');
    }

    public function value(string $key): ?string
    {
        $value = $this->values[$key] ?? null;
        return is_string($value) ? $value : null;
    }
}

final class WhatsappSendTestSmokeOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        WhatsappSendTestSmokeTrace::add('status:' . $this->status);
        return $this;
    }

    public function set_content_type($type): self
    {
        $this->contentType = (string)$type;
        return $this;
    }

    public function set_output($body): self
    {
        $this->body = (string)$body;
        return $this;
    }
}

final class WhatsappSendTestSmokeDb
{
    public array $calls = [];

    public function __call(string $method, array $arguments): self
    {
        $this->calls[] = $method;
        WhatsappSendTestSmokeTrace::add('db:' . $method);
        return $this;
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public array $permissions = [];
    public array $permissionCalls = [];
    public array $canCalls = [];
    public ?string $renderedView = null;
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->permissionCalls[] = [$page, $action];
        WhatsappSendTestSmokeTrace::add('rbac:' . $page . ':' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappSendTestSmokeDenied('permission denied', 403);
        }
    }

    public function can($page, $action = 'view'): bool
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->canCalls[] = [$page, $action];
        return !empty($this->permissions[$page][$action]);
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
    }
}

if (!function_exists('redirect')) {
    function redirect($uri = '', $method = 'auto', $code = null): void
    {
        WhatsappSendTestSmokeTrace::add('redirect');
    }
}

if (!function_exists('site_url')) {
    function site_url($uri = ''): string
    {
        return '/index.php/' . ltrim((string)$uri, '/');
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
$controllerSource = (string)file_get_contents($controllerPath);
$viewSource = (string)file_get_contents($viewPath);

$harnessSource = str_replace(
    'class Whatsapp extends MY_Controller',
    'class WhatsappSendTestSmokeBase extends MY_Controller',
    $controllerSource
);
foreach (['personalOutboundEnabled', 'callBotApi', 'logSend', 'jsonOut', 'waSession'] as $seam) {
    $harnessSource = str_replace(
        'private function ' . $seam . '(',
        'protected function ' . $seam . '(',
        $harnessSource
    );
}
eval(substr($harnessSource, 5));

final class WhatsappSendTestSmokeHarness extends WhatsappSendTestSmokeBase
{
    public bool $breakerEnabled = true;
    public int $breakerCalls = 0;
    public int $senderCalls = 0;
    public int $logCalls = 0;
    public int $jsonCalls = 0;
    public array $senderPayload = [];
    public array $jsonPayload = [];

    protected function personalOutboundEnabled(): bool
    {
        $this->breakerCalls++;
        WhatsappSendTestSmokeTrace::add('breaker');
        return $this->breakerEnabled;
    }

    protected function callBotApi(string $endpoint, string $method = 'GET', array $payload = [], int $timeout = 8): array
    {
        $this->senderCalls++;
        $this->senderPayload = ['endpoint' => $endpoint, 'method' => $method, 'payload' => $payload];
        WhatsappSendTestSmokeTrace::add('sender');
        return ['ok' => true, 'message' => 'synthetic success'];
    }

    protected function logSend(?int $broadcastId, string $source, ?string $phone, ?string $groupJid, ?string $name, string $message, string $status, string $error = ''): void
    {
        $this->logCalls++;
        WhatsappSendTestSmokeTrace::add('log');
    }

    protected function jsonOut(array $data): void
    {
        $this->jsonCalls++;
        $this->jsonPayload = $data;
        WhatsappSendTestSmokeTrace::add('json');
    }

    protected function waSession(): array
    {
        WhatsappSendTestSmokeTrace::add('wa-session:mock');
        return ['bot_api_url' => 'http://127.0.0.1:3070', 'node_path' => '', 'bot_api_token' => 'configured'];
    }
}

$checks = 0;
$failures = [];

function wa_send_test_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function wa_send_test_method_source(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    $matched = preg_match(
        '/\n    (?:public|protected|private) function /',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = $matched === 1 ? (int)$matches[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function wa_send_test_fixture(
    string $method,
    array $headers = [],
    array $session = [],
    array $permissions = [],
    string $raw = ''
): WhatsappSendTestSmokeHarness {
    WhatsappSendTestSmokeTrace::reset();
    $reflection = new ReflectionClass(WhatsappSendTestSmokeHarness::class);
    /** @var WhatsappSendTestSmokeHarness $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WhatsappSendTestSmokeInput($method, $headers, $raw);
    $controller->session = new WhatsappSendTestSmokeSession($session);
    $controller->output = new WhatsappSendTestSmokeOutput();
    $controller->db = new WhatsappSendTestSmokeDb();
    $controller->permissions = $permissions;
    return $controller;
}

function wa_send_test_rejection(WhatsappSendTestSmokeHarness $controller, int $status, string $label): void
{
    $decoded = json_decode($controller->output->body, true);
    wa_send_test_check($controller->output->status === $status, $label . ' returns HTTP ' . $status);
    wa_send_test_check(
        $controller->output->contentType === 'application/json'
            && is_array($decoded)
            && ($decoded['ok'] ?? null) === false,
        $label . ' returns structured JSON rejection'
    );
    wa_send_test_check(
        $controller->input->rawReads === 0
            && $controller->breakerCalls === 0
            && $controller->senderCalls === 0
            && $controller->logCalls === 0
            && $controller->db->calls === [],
        $label . ' stops before raw body, breaker, Bot API, log, and DB'
    );
}

function wa_send_test_render(string $path, array $variables): string
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

$apiSource = wa_send_test_method_source($controllerSource, 'api_send_test');
$mintSource = wa_send_test_method_source($controllerSource, 'wa_send_test_csrf');
$guardSource = wa_send_test_method_source($controllerSource, 'require_wa_send_test_csrf');

wa_send_test_check($apiSource !== '' && $mintSource !== '' && $guardSource !== '', 'send-test endpoint and dedicated helpers exist');
wa_send_test_check(
    strpos($controllerSource, "WA_SEND_TEST_CSRF_SESSION_KEY = '" . WA_SEND_TEST_SESSION . "'") !== false
        && strpos($controllerSource, "WA_SEND_TEST_CSRF_HEADER = '" . WA_SEND_TEST_HEADER . "'") !== false
        && strpos($controllerSource, "WA_SEND_TEST_CSRF_CI_HEADER = '" . WA_SEND_TEST_CI_HEADER . "'") !== false,
    'dedicated session and browser/canonical header contracts are declared'
);
wa_send_test_check(
    strpos($mintSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($mintSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'token helper generates strict lowercase 64-hex from random_bytes(32)'
);
wa_send_test_check(
    strpos($guardSource, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardSource, 'get_request_header(self::WA_SEND_TEST_CSRF_CI_HEADER, true)') !== false
        && strpos($guardSource, 'input->post(') === false
        && strpos($guardSource, 'input->get(') === false
        && strpos($guardSource, 'raw_input_stream') === false,
    'guard uses canonical header/hash_equals with no query, form, or raw-body fallback'
);

$permissionPosition = strpos($apiSource, "require_permission(self::PAGE_SETTINGS, 'edit')");
$guardPosition = strpos($apiSource, 'require_wa_send_test_csrf()');
$breakerPosition = strpos($apiSource, 'personalOutboundEnabled()');
$rawPosition = strpos($apiSource, 'raw_input_stream');
$validationPosition = strpos($apiSource, 'if (!$to || !$message)');
$senderPosition = strpos($apiSource, 'callBotApi(');
$logPosition = strpos($apiSource, 'logSend(');
wa_send_test_check(
    $permissionPosition !== false
        && $guardPosition !== false
        && $breakerPosition !== false
        && $rawPosition !== false
        && $validationPosition !== false
        && $senderPosition !== false
        && $logPosition !== false
        && $permissionPosition < $guardPosition
        && $guardPosition < $breakerPosition
        && $breakerPosition < $rawPosition
        && $rawPosition < $validationPosition
        && $validationPosition < $senderPosition
        && $senderPosition < $logPosition,
    'endpoint order is edit RBAC -> method/CSRF -> breaker -> raw JSON -> validation -> Bot API/log'
);

$editor = ['wa.settings' => ['view' => true, 'edit' => true]];
$viewOnly = ['wa.settings' => ['view' => true]];

$denied = wa_send_test_fixture(
    'POST',
    [WA_SEND_TEST_HEADER => WA_SEND_TEST_VALID],
    [WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID],
    $viewOnly,
    '{"to":"must-not-read","message":"must-not-read"}'
);
$deniedException = null;
try {
    $denied->api_send_test();
} catch (Throwable $exception) {
    $deniedException = $exception;
}
wa_send_test_check($deniedException instanceof WhatsappSendTestSmokeDenied, 'view-only user is denied by edit RBAC');
wa_send_test_check($denied->permissionCalls === [['wa.settings', 'edit']], 'endpoint checks exactly wa.settings:edit');
wa_send_test_check(WhatsappSendTestSmokeTrace::$events === ['rbac:wa.settings:edit'], 'RBAC denial precedes every CSRF and business read');

foreach (['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE'] as $method) {
    $controller = wa_send_test_fixture(
        $method,
        [WA_SEND_TEST_HEADER => WA_SEND_TEST_VALID],
        [WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID],
        $editor,
        '{"to":"must-not-read","message":"must-not-read"}'
    );
    $controller->api_send_test();
    wa_send_test_rejection($controller, 405, $method . ' request');
    wa_send_test_check($controller->input->headerReads === [], $method . ' rejects before reading a header');
}

$foreignTokens = [
    'missing' => '',
    'malformed uppercase' => str_repeat('A', 64),
    'mismatch' => WA_SEND_TEST_OTHER,
    'settings token' => str_repeat('c', 64),
    'manual token' => str_repeat('d', 64),
    'broadcast token' => str_repeat('e', 64),
    'log token' => str_repeat('f', 64),
    'env token' => str_repeat('1', 64),
    'engine token' => str_repeat('2', 64),
];
foreach ($foreignTokens as $label => $provided) {
    $headers = $provided === '' ? [] : [WA_SEND_TEST_HEADER => $provided];
    $controller = wa_send_test_fixture(
        'POST',
        $headers,
        [
            WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID,
            'wa_settings_mutation_csrf' => str_repeat('c', 64),
            'wa_manual_single_send_csrf' => str_repeat('d', 64),
            'wa_broadcast_mutation_csrf' => str_repeat('e', 64),
            'wa_log_retry_csrf' => str_repeat('f', 64),
            'wa_env_save_csrf' => str_repeat('1', 64),
            'wa_engine_control_csrf' => str_repeat('2', 64),
        ],
        $editor,
        '{"to":"must-not-read","message":"must-not-read"}'
    );
    $controller->api_send_test();
    wa_send_test_rejection($controller, 403, $label);
    wa_send_test_check(
        $controller->input->headerReads === [WA_SEND_TEST_CI_HEADER],
        $label . ' uses the canonical CI lookup only'
    );
    wa_send_test_check(
        $provided === '' || $label === 'malformed uppercase'
            ? $controller->session->reads === []
            : $controller->session->reads === [WA_SEND_TEST_SESSION],
        $label . ' does not read any foreign token session'
    );
}

foreach ([WA_SEND_TEST_HEADER => 'browser header', WA_SEND_TEST_CI_HEADER => 'canonical header'] as $header => $label) {
    $controller = wa_send_test_fixture(
        'POST',
        [$header => WA_SEND_TEST_VALID],
        [WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID],
        $editor
    );
    $guard = (new ReflectionClass(WhatsappSendTestSmokeBase::class))->getMethod('require_wa_send_test_csrf');
    $guard->setAccessible(true);
    wa_send_test_check($guard->invoke($controller) === true, $label . ' normalizes to an accepted scoped token');
    wa_send_test_check($controller->input->headerReads === [WA_SEND_TEST_CI_HEADER], $label . ' is read through canonical CI name');
}

$blocked = wa_send_test_fixture(
    'POST',
    [WA_SEND_TEST_HEADER => WA_SEND_TEST_VALID],
    [WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID],
    $editor,
    '{"to":"must-not-read","message":"must-not-read"}'
);
$blocked->breakerEnabled = false;
$blocked->api_send_test();
wa_send_test_check(
    $blocked->breakerCalls === 1
        && $blocked->input->rawReads === 0
        && $blocked->senderCalls === 0
        && $blocked->logCalls === 0
        && ($blocked->jsonPayload['ok'] ?? null) === false,
    'valid CSRF still honors the personal circuit breaker before raw JSON/outbound/log'
);

$invalidJson = wa_send_test_fixture(
    'POST',
    [WA_SEND_TEST_HEADER => WA_SEND_TEST_VALID],
    [WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID],
    $editor,
    'null'
);
$invalidJson->api_send_test();
wa_send_test_check(
    $invalidJson->breakerCalls === 1
        && $invalidJson->input->rawReads === 1
        && $invalidJson->senderCalls === 0
        && $invalidJson->logCalls === 0
        && ($invalidJson->jsonPayload['ok'] ?? null) === false,
    'decoded non-object JSON follows existing validation without outbound/log'
);

$valid = wa_send_test_fixture(
    'POST',
    [WA_SEND_TEST_HEADER => WA_SEND_TEST_VALID],
    [WA_SEND_TEST_SESSION => WA_SEND_TEST_VALID],
    $editor,
    '{"to":" 628111111111 ","message":" Synthetic message "}'
);
$valid->api_send_test();
wa_send_test_check(
    $valid->breakerCalls === 1
        && $valid->input->rawReads === 1
        && $valid->senderCalls === 1
        && $valid->logCalls === 1
        && $valid->jsonCalls === 1
        && $valid->db->calls === [],
    'valid request reaches mocked breaker, sender, response, and log exactly once without DB'
);
wa_send_test_check(
    $valid->senderPayload === [
        'endpoint' => '/internal/send',
        'method' => 'POST',
        'payload' => ['to' => '628111111111', 'message' => 'Synthetic message'],
    ] && ($valid->jsonPayload['ok'] ?? null) === true,
    'valid request preserves Bot API endpoint, payload trimming, and success response behavior'
);
wa_send_test_check(
    WhatsappSendTestSmokeTrace::$events === [
        'rbac:wa.settings:edit',
        'method',
        'header:' . WA_SEND_TEST_CI_HEADER,
        'session:read:' . WA_SEND_TEST_SESSION,
        'breaker',
        'raw',
        'sender',
        'log',
        'json',
    ],
    'valid behavior follows the required boundary order'
);

$tokenFixture = wa_send_test_fixture('GET');
$mint = (new ReflectionClass(WhatsappSendTestSmokeBase::class))->getMethod('wa_send_test_csrf');
$mint->setAccessible(true);
$generated = $mint->invoke($tokenFixture);
wa_send_test_check(
    is_string($generated)
        && preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1
        && $tokenFixture->session->value(WA_SEND_TEST_SESSION) === $generated,
    'token helper mints and stores lowercase 64-hex in only the dedicated scope'
);
wa_send_test_check(
    array_keys($tokenFixture->session->writes) === [WA_SEND_TEST_SESSION],
    'token mint does not write settings/manual/broadcast/log/env/engine scopes'
);

$viewSettings = wa_send_test_fixture('GET', [], [], $viewOnly);
$viewSettings->settings();
wa_send_test_check(
    !array_key_exists('wa_send_test_csrf_token', $viewSettings->renderedData)
        && !array_key_exists(WA_SEND_TEST_SESSION, $viewSettings->session->writes),
    'view-only settings behavior neither mints nor passes the send-test token'
);

$editSettings = wa_send_test_fixture('GET', [], [], $editor);
$editSettings->settings();
$renderedToken = (string)($editSettings->renderedData['wa_send_test_csrf_token'] ?? '');
wa_send_test_check(
    preg_match('/\A[0-9a-f]{64}\z/D', $renderedToken) === 1
        && $editSettings->session->value(WA_SEND_TEST_SESSION) === $renderedToken,
    'editor settings behavior mints and passes the dedicated send-test token'
);
wa_send_test_check(
    $renderedToken !== (string)($editSettings->renderedData['wa_settings_mutation_csrf'] ?? '')
        && $renderedToken !== (string)($editSettings->renderedData['wa_engine_control_csrf_token'] ?? '')
        && $renderedToken !== (string)($editSettings->renderedData['wa_env_save_csrf_token'] ?? ''),
    'editor send-test token is isolated from other settings-page token scopes'
);

$sentinel = str_repeat('9', 64);
$commonView = [
    'settings' => [],
    'bot_api_token_configured' => false,
    'wa_send_test_csrf_token' => $sentinel,
];
$viewOnlyHtml = wa_send_test_render($viewPath, $commonView + ['can_edit' => false]);
$editorHtml = wa_send_test_render($viewPath, $commonView + ['can_edit' => true]);
wa_send_test_check(
    strpos($viewOnlyHtml, 'id="btn-send-test"') === false
        && strpos($viewOnlyHtml, 'id="test-phone"') === false
        && strpos($viewOnlyHtml, $sentinel) === false
        && strpos($viewOnlyHtml, WA_SEND_TEST_HEADER) === false,
    'view-only rendered UI exposes no send control, caller, header, or token'
);
wa_send_test_check(
    strpos($editorHtml, 'id="btn-send-test"') !== false
        && strpos($editorHtml, $sentinel) !== false
        && strpos($editorHtml, "'" . WA_SEND_TEST_HEADER . "': waSendTestCsrfToken") !== false,
    'editor rendered UI includes the control and scoped browser header token'
);

$callerStart = strpos($viewSource, "document.getElementById('btn-send-test')");
$callerEnd = $callerStart === false ? false : strpos($viewSource, '<?php endif; ?>', $callerStart);
$callerSource = ($callerStart === false || $callerEnd === false)
    ? ''
    : substr($viewSource, $callerStart, $callerEnd - $callerStart);
wa_send_test_check(
    strpos($callerSource, "site_url('wa/api/send-test')") !== false
        && strpos($callerSource, "method: 'POST'") !== false
        && strpos($callerSource, "credentials: 'same-origin'") !== false
        && strpos($callerSource, "'" . WA_SEND_TEST_HEADER . "': waSendTestCsrfToken") !== false,
    'existing fetch caller uses POST, same-origin credentials, and dedicated browser header'
);
wa_send_test_check(
    strpos($callerSource, 'body: JSON.stringify({ to: phone, message: msg })') !== false
        && strpos($callerSource, '.then(waSafeJsonResponse)') !== false
        && strpos($callerSource, '✓ Pesan terkirim!') !== false
        && strpos($callerSource, "d.message || 'Gagal'") !== false,
    'fetch caller preserves request payload and response handling behavior'
);

if ($failures !== []) {
    fwrite(STDERR, '[FAIL] WhatsApp api_send_test CSRF smoke: ' . count($failures) . ' of ' . $checks . " checks failed.\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo '[PASS] WhatsApp api_send_test CSRF smoke: ' . $checks
    . " checks; RBAC, early header-only CSRF, token isolation, mocked sender, and editor-only UI verified.\n";
