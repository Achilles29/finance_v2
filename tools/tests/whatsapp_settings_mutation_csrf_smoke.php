<?php

declare(strict_types=1);

/**
 * Bootstrap/DB/network/secret-free behavior smoke for Whatsapp::settings CSRF.
 *
 * The real controller method runs without its constructor against in-memory
 * CodeIgniter doubles. No application bootstrap or external resource is used.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class WhatsappSettingsCsrfTrace
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

final class WhatsappSettingsCsrfDenied extends RuntimeException
{
}

final class WhatsappSettingsCsrfInput
{
    private string $requestMethod;
    private array $postData;

    public function __construct(string $requestMethod, array $postData = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
    }

    public function method($upper = false): string
    {
        WhatsappSettingsCsrfTrace::add('request:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $key = (string)$key;
        WhatsappSettingsCsrfTrace::add(
            $key === 'wa_settings_mutation_csrf' ? 'csrf:form' : 'business:post:' . $key
        );
        return $this->postData[$key] ?? null;
    }
}

final class WhatsappSettingsCsrfSession
{
    public array $reads = [];
    public array $writes = [];
    public array $flash = [];
    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $key = (string)$key;
        WhatsappSettingsCsrfTrace::add('session:read:' . $key);
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        WhatsappSettingsCsrfTrace::add('session:write:' . $key);
        $this->writes[$key] = (string)$value;
        $this->values[$key] = $value;
    }

    public function set_flashdata($key, $value): void
    {
        WhatsappSettingsCsrfTrace::add('flash:' . (string)$key);
        $this->flash[(string)$key] = (string)$value;
    }

    public function flashdata($key)
    {
        return null;
    }
}

final class WhatsappSettingsCsrfOutput
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

final class WhatsappSettingsCsrfDb
{
    public array $updates = [];
    private array $row;

    public function __construct(array $row = [])
    {
        $this->row = $row + [
            'id' => 1,
            'status' => 'UNKNOWN',
            'bot_api_url' => 'http://127.0.0.1:3070',
            'bot_api_token' => '',
            'node_path' => '/synthetic/node',
        ];
    }

    public function from($table): self
    {
        WhatsappSettingsCsrfTrace::add('db:from:' . (string)$table);
        return $this;
    }

    public function where($field, $value = null): self
    {
        WhatsappSettingsCsrfTrace::add('db:where');
        return $this;
    }

    public function limit($limit, $offset = 0): self
    {
        WhatsappSettingsCsrfTrace::add('db:limit');
        return $this;
    }

    public function get(): self
    {
        WhatsappSettingsCsrfTrace::add('db:get');
        return $this;
    }

    public function row_array(): array
    {
        WhatsappSettingsCsrfTrace::add('db:row');
        return $this->row;
    }

    public function field_exists($field, $table): bool
    {
        WhatsappSettingsCsrfTrace::add('db:schema:' . (string)$field . ':' . (string)$table);
        return (string)$field === 'node_path' && (string)$table === 'wa_session';
    }

    public function update($table, $data): bool
    {
        WhatsappSettingsCsrfTrace::add('db:update:' . (string)$table);
        $this->updates[] = [(string)$table, (array)$data];
        return true;
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public bool $allowView = true;
    public bool $allowEdit = true;
    public array $permissionCalls = [];
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $action = (string)$action;
        WhatsappSettingsCsrfTrace::add('rbac:' . $action);
        $this->permissionCalls[] = [(string)$page, $action];
        if (($action === 'view' && !$this->allowView) || ($action === 'edit' && !$this->allowEdit)) {
            throw new WhatsappSettingsCsrfDenied('permission denied');
        }
    }

    public function can($page, $action = 'view'): bool
    {
        WhatsappSettingsCsrfTrace::add('can:' . (string)$action);
        return (string)$action !== 'edit' || $this->allowEdit;
    }

    public function render($view, array $data = []): void
    {
        WhatsappSettingsCsrfTrace::add('render:' . (string)$view);
        $this->renderedData = $data;
    }
}

$whatsappSettingsCsrfRedirect = '';
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $whatsappSettingsCsrfRedirect;
    WhatsappSettingsCsrfTrace::add('redirect:' . (string)$uri);
    $whatsappSettingsCsrfRedirect = (string)$uri;
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

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

final class WhatsappSettingsCurlBoundaryDouble extends Whatsapp
{
    public int $curlInitCalls = 0;

    public function __construct()
    {
    }

    protected function initializeBotApiCurl(string $url)
    {
        $this->curlInitCalls++;
        WhatsappSettingsCsrfTrace::add('curl:init');
        return false;
    }
}

$whatsappSettingsCsrfChecks = 0;
$whatsappSettingsCsrfFailures = [];

function whatsapp_settings_csrf_check(bool $condition, string $message): void
{
    global $whatsappSettingsCsrfChecks, $whatsappSettingsCsrfFailures;
    $whatsappSettingsCsrfChecks++;
    if (!$condition) {
        $whatsappSettingsCsrfFailures[] = $message;
    }
}

function whatsapp_settings_csrf_method_block(string $source, string $method): string
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

function whatsapp_settings_csrf_controller(
    string $method,
    array $postData = [],
    array $sessionValues = [],
    bool $allowEdit = true
): array {
    WhatsappSettingsCsrfTrace::reset();
    global $whatsappSettingsCsrfRedirect;
    $whatsappSettingsCsrfRedirect = '';

    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WhatsappSettingsCsrfInput($method, $postData);
    $controller->session = new WhatsappSettingsCsrfSession($sessionValues);
    $controller->output = new WhatsappSettingsCsrfOutput();
    $controller->db = new WhatsappSettingsCsrfDb();
    $controller->allowEdit = $allowEdit;

    return [$controller, $reflection];
}

function whatsapp_settings_csrf_run(
    string $method,
    array $postData = [],
    array $sessionValues = [],
    bool $allowEdit = true
): array {
    [$controller, $reflection] = whatsapp_settings_csrf_controller(
        $method,
        $postData,
        $sessionValues,
        $allowEdit
    );
    $caught = null;
    try {
        $controller->settings();
    } catch (Throwable $exception) {
        $caught = $exception;
    }

    global $whatsappSettingsCsrfRedirect;
    return [$controller, $reflection, $caught, WhatsappSettingsCsrfTrace::$events, $whatsappSettingsCsrfRedirect];
}

function whatsapp_settings_csrf_rejected_before_business(array $fixture, string $label): void
{
    /** @var Whatsapp $controller */
    [$controller, , $caught, $events] = $fixture;
    whatsapp_settings_csrf_check($caught === null, $label . ' returns normally');
    whatsapp_settings_csrf_check($controller->output->status === 403, $label . ' returns HTTP 403');
    $body = json_decode($controller->output->body, true);
    whatsapp_settings_csrf_check(
        $controller->output->contentType === 'application/json'
            && is_array($body)
            && ($body['ok'] ?? null) === false,
        $label . ' returns a JSON rejection'
    );
    $forbidden = array_values(array_filter(
        $events,
        static fn(string $event): bool => strpos($event, 'business:') === 0
            || strpos($event, 'db:') === 0
            || strpos($event, 'flash:') === 0
            || strpos($event, 'redirect:') === 0
    ));
    whatsapp_settings_csrf_check(
        $forbidden === [],
        $label . ' is rejected before business input, schema, DB, flash, and redirect'
    );
}

function whatsapp_settings_csrf_render(string $viewPath, array $variables): string
{
    $renderer = new class {
        public $session;
        public $load;

        public function __construct()
        {
            $this->session = new WhatsappSettingsCsrfSession();
            // The integration partial has its own DB/disposable/render tests.
            $this->load = new class {
                public function view(string $path, array $data): void {
                    if ($path !== 'notifications/settings') throw new RuntimeException('Unexpected partial');
                }
            };
        }

        public function render(string $viewPath, array $variables): string
        {
            extract($variables, EXTR_SKIP);
            ob_start();
            include $viewPath;
            return (string)ob_get_clean();
        }
    };

    return $renderer->render($viewPath, $variables);
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Whatsapp.php';
$viewPath = $root . '/application/views/wa/settings.php';
$controllerSource = file_get_contents($controllerPath);
$viewSource = file_get_contents($viewPath);
$sessionKey = 'wa_settings_mutation_csrf';
$validToken = str_repeat('a', 64);
$otherToken = str_repeat('b', 64);

whatsapp_settings_csrf_check(is_string($controllerSource), 'controller source is readable');
whatsapp_settings_csrf_check(is_string($viewSource), 'settings view source is readable');
whatsapp_settings_csrf_check(
    strpos($controllerSource, "private const WA_SETTINGS_MUTATION_CSRF_SESSION_KEY = 'wa_settings_mutation_csrf';") !== false
        && strpos($controllerSource, "private const WA_SETTINGS_MUTATION_CSRF_FORM_FIELD = 'wa_settings_mutation_csrf';") !== false,
    'controller defines the dedicated settings session and form scope'
);

$settingsBlock = whatsapp_settings_csrf_method_block($controllerSource, 'settings');
$tokenBlock = whatsapp_settings_csrf_method_block($controllerSource, 'wa_settings_mutation_csrf');
$guardBlock = whatsapp_settings_csrf_method_block($controllerSource, 'require_wa_settings_mutation_csrf');
$editPosition = strpos($settingsBlock, "require_permission(self::PAGE_SETTINGS, 'edit')");
$guardPosition = strpos($settingsBlock, 'require_wa_settings_mutation_csrf()');
$businessPosition = strpos($settingsBlock, "post('bot_api_url'");
$validationPosition = strpos($settingsBlock, 'normalizeBotApiBaseUrl($botApiUrl)');
$schemaPosition = strpos($settingsBlock, "field_exists('node_path', 'wa_session')");
$updatePosition = strpos($settingsBlock, "update('wa_session', \$updateData)");
whatsapp_settings_csrf_check(
    $editPosition !== false
        && $guardPosition !== false
        && $businessPosition !== false
        && $validationPosition !== false
        && $schemaPosition !== false
        && $updatePosition !== false
        && $editPosition < $guardPosition
        && $guardPosition < $businessPosition
        && $businessPosition < $validationPosition
        && $validationPosition < $schemaPosition
        && $schemaPosition < $updatePosition,
    'settings keeps edit RBAC -> scoped CSRF -> URL validation -> schema -> update order'
);
whatsapp_settings_csrf_check(
    strpos($tokenBlock, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D', \$token)") !== false
        && strpos($guardBlock, 'hash_equals($sessionToken, $providedToken)') !== false,
    'token helper and guard enforce random lowercase 64-hex session-bound tokens'
);
foreach (['WA_ENGINE_CONTROL', 'WA_ENV_SAVE', 'WA_BROADCAST', 'WA_LOG_RETRY'] as $foreignConstant) {
    whatsapp_settings_csrf_check(
        strpos($tokenBlock, $foreignConstant) === false && strpos($guardBlock, $foreignConstant) === false,
        'settings token does not reuse ' . $foreignConstant . ' scope'
    );
}

$normalizerBlock = whatsapp_settings_csrf_method_block($controllerSource, 'normalizeBotApiBaseUrl');
$curlInitializerBlock = whatsapp_settings_csrf_method_block($controllerSource, 'initializeBotApiCurl');
$callBotApiBlock = whatsapp_settings_csrf_method_block($controllerSource, 'callBotApi');
$callValidationPosition = strpos($callBotApiBlock, 'normalizeBotApiBaseUrl');
$callCurlPosition = strpos($callBotApiBlock, 'initializeBotApiCurl');
whatsapp_settings_csrf_check(
    strpos($normalizerBlock, '127\\.0\\.0\\.1|localhost') !== false
        && strpos($normalizerBlock, '$port < 1 || $port > 65535') !== false
        && strpos($normalizerBlock, "return 'http://127.0.0.1:' . \$port;") !== false,
    'URL helper is a no-DNS allowlist that emits only the canonical loopback base'
);
whatsapp_settings_csrf_check(
    $callValidationPosition !== false
        && $callCurlPosition !== false
        && $callValidationPosition < $callCurlPosition
        && strpos($curlInitializerBlock, 'curl_init($url)') !== false
        && strpos($callBotApiBlock, '$url = $botApiBaseUrl . $endpoint') !== false,
    'callBotApi validates the stored base and builds from its canonical value before curl_init'
);
whatsapp_settings_csrf_check(
    strpos($callBotApiBlock, 'CURLOPT_FOLLOWLOCATION => false') !== false
        && strpos($callBotApiBlock, 'CURLOPT_MAXREDIRS      => 0') !== false
        && strpos($callBotApiBlock, "CURLOPT_PROXY          => ''") !== false,
    'callBotApi explicitly disables redirect following and proxy use'
);

[$urlController, $urlReflection] = whatsapp_settings_csrf_controller('GET');
$normalizer = $urlReflection->getMethod('normalizeBotApiBaseUrl');
$normalizer->setAccessible(true);
$validBotApiUrls = [
    'canonical' => ['http://127.0.0.1:3070', 'http://127.0.0.1:3070'],
    'localhost alias' => ['http://localhost:3070', 'http://127.0.0.1:3070'],
    'localhost root path' => ['http://localhost:3070/', 'http://127.0.0.1:3070'],
    'minimum port' => ['http://127.0.0.1:1', 'http://127.0.0.1:1'],
    'maximum port' => ['http://127.0.0.1:65535', 'http://127.0.0.1:65535'],
];
foreach ($validBotApiUrls as $label => [$inputUrl, $expectedUrl]) {
    whatsapp_settings_csrf_check(
        $normalizer->invoke($urlController, $inputUrl) === $expectedUrl,
        $label . ' is accepted and canonicalized'
    );
}

$invalidBotApiUrls = [
    'HTTPS scheme' => 'https://127.0.0.1:3070',
    'non-loopback host' => 'http://example.invalid:3070',
    'alternate loopback address' => 'http://127.0.0.2:3070',
    'IPv6 loopback' => 'http://[::1]:3070',
    'localhost suffix' => 'http://localhost.example.invalid:3070',
    'userinfo' => 'http://user@127.0.0.1:3070',
    'query' => 'http://127.0.0.1:3070?target=elsewhere',
    'fragment' => 'http://127.0.0.1:3070#fragment',
    'non-root path' => 'http://127.0.0.1:3070/internal/status',
    'missing port' => 'http://127.0.0.1',
    'non-numeric port' => 'http://127.0.0.1:port',
    'zero port' => 'http://127.0.0.1:0',
    'out-of-range port' => 'http://127.0.0.1:65536',
    'leading-zero port' => 'http://127.0.0.1:03070',
    'integer host alias' => 'http://2130706433:3070',
    'uppercase scheme' => 'HTTP://127.0.0.1:3070',
    'malformed URL' => 'not-a-url',
];
foreach (['blank URL' => ''] + $invalidBotApiUrls as $label => $inputUrl) {
    whatsapp_settings_csrf_check(
        $normalizer->invoke($urlController, $inputUrl) === null,
        $label . ' is rejected by the URL helper'
    );
}

$callBotApi = $urlReflection->getMethod('callBotApi');
$callBotApi->setAccessible(true);
putenv('FINANCE_WA_ENGINE_API_TOKEN=synthetic-settings-csrf-token');
foreach (['blank URL' => ''] + $invalidBotApiUrls as $label => $inputUrl) {
    WhatsappSettingsCsrfTrace::reset();
    $curlBoundaryController = new WhatsappSettingsCurlBoundaryDouble();
    $curlBoundaryController->input = new WhatsappSettingsCsrfInput('GET');
    $curlBoundaryController->session = new WhatsappSettingsCsrfSession();
    $curlBoundaryController->output = new WhatsappSettingsCsrfOutput();
    $curlBoundaryController->db = new WhatsappSettingsCsrfDb(['bot_api_url' => $inputUrl]);
    $result = $callBotApi->invoke($curlBoundaryController, '/internal/status', 'GET');
    whatsapp_settings_csrf_check(
        ($result['ok'] ?? null) === false
            && ($result['message'] ?? '') === 'Konfigurasi URL WA Bot tidak valid.'
            && $curlBoundaryController->curlInitCalls === 0
            && !in_array('curl:init', WhatsappSettingsCsrfTrace::$events, true)
            && $curlBoundaryController->db->updates === [],
        $label . ' stored base returns a generic error before the curl double is called'
    );
}
putenv('FINANCE_WA_ENGINE_API_TOKEN');

[$getViewer, , $getViewerError, $getViewerEvents] = whatsapp_settings_csrf_run('GET', [], [], false);
whatsapp_settings_csrf_check($getViewerError === null, 'view-only GET completes');
whatsapp_settings_csrf_check(
    !in_array('db:update:wa_session', $getViewerEvents, true)
        && !in_array('csrf:form', $getViewerEvents, true)
        && !isset($getViewer->renderedData['wa_settings_mutation_csrf'])
        && !isset($getViewer->session->writes[$sessionKey]),
    'view-only GET is read-only and neither mints nor renders the settings token'
);
whatsapp_settings_csrf_check(
    $getViewer->permissionCalls === [['wa.settings', 'view']],
    'view-only GET retains wa.settings:view RBAC'
);

[$getEditor, , $getEditorError, $getEditorEvents] = whatsapp_settings_csrf_run('GET');
whatsapp_settings_csrf_check($getEditorError === null, 'editor GET completes');
whatsapp_settings_csrf_check(
    !in_array('db:update:wa_session', $getEditorEvents, true)
        && preg_match('/\A[0-9a-f]{64}\z/D', (string)($getEditor->renderedData[$sessionKey] ?? '')) === 1
        && isset($getEditor->session->writes[$sessionKey]),
    'editor GET remains DB-read-only and mints/passes the scoped token'
);

$businessPayload = [
    'bot_api_url' => 'http://127.0.0.1:3080',
    'bot_api_token' => '',
    'node_path' => '',
];
[$deniedController, , $deniedError, $deniedEvents] = whatsapp_settings_csrf_run(
    'POST',
    [$sessionKey => $validToken] + $businessPayload,
    [$sessionKey => $validToken],
    false
);
whatsapp_settings_csrf_check($deniedError instanceof WhatsappSettingsCsrfDenied, 'edit RBAC denial stops POST');
whatsapp_settings_csrf_check(
    $deniedController->permissionCalls === [['wa.settings', 'view'], ['wa.settings', 'edit']]
        && $deniedEvents === ['rbac:view', 'request:method', 'rbac:edit'],
    'edit RBAC denial occurs before CSRF, business input, schema, and write'
);

$invalidCases = [
    'missing' => [$businessPayload, [$sessionKey => $validToken]],
    'empty' => [[$sessionKey => ''] + $businessPayload, [$sessionKey => $validToken]],
    'malformed' => [[$sessionKey => str_repeat('A', 64)] + $businessPayload, [$sessionKey => $validToken]],
    'mismatch' => [[$sessionKey => $otherToken] + $businessPayload, [$sessionKey => $validToken]],
];
foreach ($invalidCases as $label => [$postData, $sessionValues]) {
    $fixture = whatsapp_settings_csrf_run('POST', $postData, $sessionValues);
    whatsapp_settings_csrf_rejected_before_business($fixture, 'POST ' . $label);
}

$foreignScopes = [
    'wa_engine_control_csrf',
    'wa_env_save_csrf',
    'wa_broadcast_mutation_csrf',
    'wa_log_retry_csrf',
];
foreach ($foreignScopes as $index => $foreignScope) {
    $foreignToken = str_repeat(dechex($index + 1), 64);
    $fixture = whatsapp_settings_csrf_run(
        'POST',
        [$sessionKey => $foreignToken] + $businessPayload,
        [$foreignScope => $foreignToken]
    );
    whatsapp_settings_csrf_rejected_before_business($fixture, 'POST foreign scope ' . $foreignScope);
    /** @var Whatsapp $foreignController */
    $foreignController = $fixture[0];
    whatsapp_settings_csrf_check(
        $foreignController->session->reads === [$sessionKey],
        'guard reads only the settings session key for ' . $foreignScope
    );
}

foreach ($invalidBotApiUrls as $label => $invalidUrl) {
    [$invalidUrlController, , $invalidUrlError, $invalidUrlEvents, $invalidUrlRedirect] = whatsapp_settings_csrf_run(
        'POST',
        [$sessionKey => $validToken, 'bot_api_url' => $invalidUrl] + $businessPayload,
        [$sessionKey => $validToken]
    );
    $invalidUrlDbEvents = array_values(array_filter(
        $invalidUrlEvents,
        static fn(string $event): bool => strpos($event, 'db:') === 0
    ));
    whatsapp_settings_csrf_check(
        $invalidUrlError === null
            && $invalidUrlController->db->updates === []
            && $invalidUrlDbEvents === []
            && !in_array('business:post:bot_api_token', $invalidUrlEvents, true)
            && !in_array('business:post:node_path', $invalidUrlEvents, true)
            && ($invalidUrlController->session->flash['error'] ?? '') !== ''
            && !isset($invalidUrlController->session->flash['success'])
            && $invalidUrlRedirect === 'wa/settings',
        $label . ' settings POST is rejected before other settings input, schema, and DB update'
    );
}

[$localhostController, , $localhostError, , $localhostRedirect] = whatsapp_settings_csrf_run(
    'POST',
    [$sessionKey => $validToken, 'bot_api_url' => 'http://localhost:3081/'] + $businessPayload,
    [$sessionKey => $validToken]
);
$localhostUpdate = $localhostController->db->updates[0][1] ?? [];
whatsapp_settings_csrf_check(
    $localhostError === null
        && ($localhostUpdate['bot_api_url'] ?? '') === 'http://127.0.0.1:3081'
        && $localhostRedirect === 'wa/settings',
    'settings POST accepts localhost input and persists the canonical loopback base'
);

[$blankUrlController, , $blankUrlError, , $blankUrlRedirect] = whatsapp_settings_csrf_run(
    'POST',
    [$sessionKey => $validToken, 'bot_api_url' => '   '] + $businessPayload,
    [$sessionKey => $validToken]
);
$blankUrlUpdate = $blankUrlController->db->updates[0][1] ?? [];
whatsapp_settings_csrf_check(
    $blankUrlError === null
        && ($blankUrlUpdate['bot_api_url'] ?? '') === 'http://127.0.0.1:3070'
        && !array_key_exists('bot_api_token', $blankUrlUpdate)
        && $blankUrlRedirect === 'wa/settings',
    'blank URL keeps the canonical default and blank bot token remains write-only/omitted'
);

[$validController, , $validError, $validEvents, $validRedirect] = whatsapp_settings_csrf_run(
    'POST',
    [$sessionKey => $validToken] + $businessPayload,
    [$sessionKey => $validToken]
);
whatsapp_settings_csrf_check($validError === null, 'valid POST completes');
whatsapp_settings_csrf_check(
    $validController->permissionCalls === [['wa.settings', 'view'], ['wa.settings', 'edit']],
    'valid POST retains view and edit RBAC'
);
$validUpdate = $validController->db->updates[0] ?? [];
whatsapp_settings_csrf_check(
    ($validUpdate[0] ?? '') === 'wa_session'
        && ($validUpdate[1]['bot_api_url'] ?? '') === 'http://127.0.0.1:3080'
        && array_key_exists('node_path', $validUpdate[1] ?? [])
        && $validUpdate[1]['node_path'] === null
        && !array_key_exists('bot_api_token', $validUpdate[1] ?? [])
        && $validRedirect === 'wa/settings',
    'valid token reaches the existing update/redirect and blank bot token remains omitted'
);
$validEventPositions = [];
foreach ($validEvents as $position => $event) {
    $validEventPositions[$event] = $position;
}
whatsapp_settings_csrf_check(
    ($validEventPositions['rbac:edit'] ?? PHP_INT_MAX) < ($validEventPositions['csrf:form'] ?? -1)
        && ($validEventPositions['session:read:' . $sessionKey] ?? PHP_INT_MAX) < ($validEventPositions['business:post:bot_api_url'] ?? -1)
        && ($validEventPositions['business:post:node_path'] ?? PHP_INT_MAX) < ($validEventPositions['db:schema:node_path:wa_session'] ?? -1)
        && ($validEventPositions['db:schema:node_path:wa_session'] ?? PHP_INT_MAX) < ($validEventPositions['db:update:wa_session'] ?? -1),
    'valid behavior follows edit RBAC -> scoped form CSRF -> business input -> schema -> update'
);

$foreignValues = [
    'wa_engine_control_csrf' => str_repeat('1', 64),
    'wa_env_save_csrf' => str_repeat('2', 64),
    'wa_broadcast_mutation_csrf' => str_repeat('3', 64),
    'wa_log_retry_csrf' => str_repeat('4', 64),
];
[$tokenController, $tokenReflection] = whatsapp_settings_csrf_controller('GET', [], $foreignValues);
$generatedToken = $tokenReflection->getMethod('wa_settings_mutation_csrf')->invoke($tokenController);
whatsapp_settings_csrf_check(
    is_string($generatedToken)
        && preg_match('/\A[0-9a-f]{64}\z/D', $generatedToken) === 1
        && ($tokenController->session->writes[$sessionKey] ?? '') === $generatedToken,
    'token helper generates and stores lowercase 64-hex in the settings scope'
);
whatsapp_settings_csrf_check(
    $tokenController->session->reads === [$sessionKey]
        && array_keys($tokenController->session->writes) === [$sessionKey],
    'token helper reads/writes only the settings scope'
);

$renderSentinel = str_repeat('d', 64);
$commonViewData = [
    'settings' => [],
    'bot_api_token_configured' => false,
    'wa_settings_mutation_csrf' => $renderSentinel,
    'wa_engine_control_csrf_token' => str_repeat('e', 64),
    'wa_env_save_csrf_token' => str_repeat('f', 64),
];
$viewerHtml = whatsapp_settings_csrf_render($viewPath, $commonViewData + ['can_edit' => false]);
$editorHtml = whatsapp_settings_csrf_render($viewPath, $commonViewData + ['can_edit' => true]);
$hiddenTokenHtml = 'name="wa_settings_mutation_csrf" value="' . $renderSentinel . '"';
whatsapp_settings_csrf_check(
    strpos($viewerHtml, $hiddenTokenHtml) === false && strpos($viewerHtml, $renderSentinel) === false,
    'view-only HTML does not render the settings token even when supplied'
);
whatsapp_settings_csrf_check(
    substr_count($editorHtml, $hiddenTokenHtml) === 1,
    'editor HTML renders exactly one scoped hidden form token'
);

if ($whatsappSettingsCsrfFailures !== []) {
    fwrite(STDERR, 'FAIL: ' . count($whatsappSettingsCsrfFailures) . ' of '
        . $whatsappSettingsCsrfChecks . " checks failed\n");
    foreach ($whatsappSettingsCsrfFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo 'PASS: ' . $whatsappSettingsCsrfChecks
    . " WhatsApp settings mutation CSRF checks; bootstrap/DB/network/secret free.\n";
