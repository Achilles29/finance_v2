<?php

declare(strict_types=1);

/**
 * DB/bootstrap/network-free smoke test for WhatsApp broadcast mutation CSRF.
 *
 * The real controller is loaded without its constructor and invoked against
 * in-memory CI doubles. No credentials, runtime log, upload, DB, or Bot API
 * dependency is opened.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_BROADCAST_CSRF_FIELD = 'wa_broadcast_mutation_csrf';
const WA_BROADCAST_CSRF_SESSION = 'wa_broadcast_mutation_csrf';
const WA_BROADCAST_CSRF_VALID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WA_BROADCAST_CSRF_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const WA_BROADCAST_CSRF_ENGINE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const WA_BROADCAST_CSRF_ENV = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
const WA_BROADCAST_CSRF_TEMPLATE = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
const WA_BROADCAST_CSRF_SCHEDULE = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

final class WhatsappBroadcastCsrfTrace
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

final class WhatsappBroadcastCsrfDenied extends RuntimeException
{
}

final class WhatsappBroadcastCsrfInput
{
    public array $postReads = [];
    public array $getReads = [];
    public array $headerReads = [];
    public int $rawReads = 0;

    private string $requestMethod;
    private array $postData;
    private array $getData;
    private array $headers;

    public function __construct(string $method, array $post = [], array $get = [], array $headers = [])
    {
        $this->requestMethod = strtoupper($method);
        $this->postData = $post;
        $this->getData = $get;
        $this->headers = [];
        foreach ($headers as $key => $value) {
            $normalized = str_replace(['_', '-'], ' ', strtolower((string)$key));
            $normalized = str_replace(' ', '-', ucwords($normalized));
            $this->headers[$normalized] = (string)$value;
        }
    }

    public function method($upper = false): string
    {
        WhatsappBroadcastCsrfTrace::add('input:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $key = (string)$key;
        $this->postReads[] = $key;
        WhatsappBroadcastCsrfTrace::add('input:post:' . $key);
        return $this->postData[$key] ?? null;
    }

    public function get($key = null, $xssClean = false)
    {
        $key = (string)$key;
        $this->getReads[] = $key;
        WhatsappBroadcastCsrfTrace::add('input:get:' . $key);
        return $this->getData[$key] ?? '';
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        WhatsappBroadcastCsrfTrace::add('input:header:' . $key);
        return $this->headers[$key] ?? '';
    }

    public function server($key, $xssClean = false): string
    {
        return '';
    }

    public function __get($key)
    {
        if ((string)$key === 'raw_input_stream') {
            $this->rawReads++;
            WhatsappBroadcastCsrfTrace::add('input:raw');
            return '';
        }
        throw new RuntimeException('Unexpected input property: ' . (string)$key);
    }
}

final class WhatsappBroadcastCsrfSession
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
        $this->reads[] = $key;
        WhatsappBroadcastCsrfTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->values[$key] = $value;
        $this->writes[$key] = (string)$value;
        WhatsappBroadcastCsrfTrace::add('session:write:' . $key);
    }

    public function set_flashdata($key, $value): void
    {
        $this->flash[(string)$key] = (string)$value;
        WhatsappBroadcastCsrfTrace::add('session:flash:' . (string)$key);
    }

    public function flashdata($key)
    {
        return null;
    }

    public function value(string $key): ?string
    {
        $value = $this->values[$key] ?? null;
        return is_string($value) ? $value : null;
    }
}

final class WhatsappBroadcastCsrfOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        WhatsappBroadcastCsrfTrace::add('output:status:' . $this->status);
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

final class WhatsappBroadcastCsrfDb
{
    public array $events = [];
    public array $row = [];

    public function __call($method, $args): self
    {
        $this->events[] = 'db:' . (string)$method;
        WhatsappBroadcastCsrfTrace::add('db:' . (string)$method);
        return $this;
    }

    public function get($table = ''): self
    {
        return $this->__call('get', [$table]);
    }

    public function result_array(): array
    {
        $this->events[] = 'db:result_array';
        return [];
    }

    public function row_array(): array
    {
        $this->events[] = 'db:row_array';
        return $this->row;
    }

    public function count_all_results($table = ''): int
    {
        $this->events[] = 'db:count_all_results';
        return 0;
    }

    public function table_exists($table): bool
    {
        $this->events[] = 'db:table_exists';
        return false;
    }

    public function field_exists($field, $table): bool
    {
        $this->events[] = 'db:field_exists';
        return false;
    }

    public function trans_status(): bool
    {
        return true;
    }

    public function insert_id(): int
    {
        return 1;
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
    protected array $current_user = ['id' => 101];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->permissionCalls[] = [$page, $action];
        WhatsappBroadcastCsrfTrace::add('rbac:' . $page . ':' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappBroadcastCsrfDenied('permission denied', 403);
        }
    }

    public function can($page, $action = 'view'): bool
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->canCalls[] = [$page, $action];
        WhatsappBroadcastCsrfTrace::add('can:' . $page . ':' . $action);
        return !empty($this->permissions[$page][$action]);
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
        WhatsappBroadcastCsrfTrace::add('render:' . (string)$view);
    }
}

if (!function_exists('redirect')) {
    function redirect($uri = '', $method = 'auto', $code = null): void
    {
        WhatsappBroadcastCsrfTrace::add('redirect:' . (string)$uri);
    }
}

if (!function_exists('show_404')) {
    function show_404($page = '', $log_error = true): void
    {
        throw new WhatsappBroadcastCsrfDenied('404', 404);
    }
}

if (!function_exists('site_url')) {
    function site_url($uri = ''): string
    {
        return '/' . ltrim((string)$uri, '/');
    }
}

if (!function_exists('html_escape')) {
    function html_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

$whatsappBroadcastCsrfChecks = 0;
$whatsappBroadcastCsrfFailures = [];

function whatsapp_broadcast_csrf_check(bool $condition, string $message): void
{
    global $whatsappBroadcastCsrfChecks, $whatsappBroadcastCsrfFailures;
    $whatsappBroadcastCsrfChecks++;
    if (!$condition) {
        $whatsappBroadcastCsrfFailures[] = $message;
    }
}

function whatsapp_broadcast_csrf_method_source(string $source, string $method): string
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

function whatsapp_broadcast_csrf_fixture(
    string $method,
    array $post = [],
    array $permissions = [],
    array $sessionValues = [],
    array $get = [],
    array $headers = [],
    array $row = []
): array {
    WhatsappBroadcastCsrfTrace::reset();
    $input = new WhatsappBroadcastCsrfInput($method, $post, $get, $headers);
    $session = new WhatsappBroadcastCsrfSession($sessionValues);
    $output = new WhatsappBroadcastCsrfOutput();
    $db = new WhatsappBroadcastCsrfDb();
    $db->row = $row;
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = $db;
    $controller->permissions = $permissions;
    return compact('controller', 'input', 'session', 'output', 'db', 'reflection');
}

function whatsapp_broadcast_csrf_reject(array $fixture, int $status, string $label): void
{
    $decoded = json_decode($fixture['output']->body, true);
    whatsapp_broadcast_csrf_check($fixture['output']->status === $status, $label . ' status ' . $status);
    whatsapp_broadcast_csrf_check(
        $fixture['output']->contentType === 'application/json'
            && is_array($decoded)
            && ($decoded['ok'] ?? null) === false,
        $label . ' structured JSON rejection'
    );
}

function whatsapp_broadcast_csrf_no_business_access(array $fixture, string $label): void
{
    $business = [
        'name', 'template_id', 'custom_message', 'target_type', 'scheduled_at',
        'notes', 'manual_lines', 'selected_member_ids', 'delay_pattern',
        'media_image', 'manual_numbers', 'message',
    ];
    whatsapp_broadcast_csrf_check(
        array_intersect($fixture['input']->postReads, $business) === [],
        $label . ' reads no business payload'
    );
    whatsapp_broadcast_csrf_check($fixture['db']->events === [], $label . ' reaches no DB dependency');
    whatsapp_broadcast_csrf_check($fixture['session']->flash === [], $label . ' emits no flash side effect');
    whatsapp_broadcast_csrf_check($fixture['input']->rawReads === 0, $label . ' reads no raw body');
}

$controllerSource = (string)file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php');
$methodBlocks = [];
foreach (['broadcast_create', 'broadcast_edit', 'broadcast_delete', 'broadcast_deactivate', 'manual', 'api_broadcast_start'] as $method) {
    $methodBlocks[$method] = whatsapp_broadcast_csrf_method_source($controllerSource, $method);
    whatsapp_broadcast_csrf_check($methodBlocks[$method] !== '', $method . ' source is present');
}

foreach (['broadcast_create', 'broadcast_edit', 'broadcast_delete', 'broadcast_deactivate'] as $method) {
    $block = $methodBlocks[$method];
    whatsapp_broadcast_csrf_check(
        strpos($block, 'require_permission') < strpos($block, 'require_wa_broadcast_mutation_csrf'),
        $method . ' keeps RBAC before CSRF'
    );
    whatsapp_broadcast_csrf_check(
        strpos($block, 'require_wa_broadcast_mutation_csrf') < strpos($block, '$this->db->'),
        $method . ' guards before DB access'
    );
}

$manualBlock = $methodBlocks['manual'];
whatsapp_broadcast_csrf_check(
    strpos($manualBlock, "require_permission(self::PAGE_MANUAL, 'create')") < strpos($manualBlock, 'require_wa_broadcast_mutation_csrf'),
    'manual bulk keeps create RBAC before CSRF'
);
whatsapp_broadcast_csrf_check(
    strpos($manualBlock, 'require_wa_broadcast_mutation_csrf') < strpos($manualBlock, 'createManualBulkQueue'),
    'manual bulk guards before queue writer'
);
whatsapp_broadcast_csrf_check(
    strpos($manualBlock, "delivery_mode', true") !== false
        && strpos($manualBlock, '$deliveryMode') < strpos($manualBlock, "if (\$deliveryMode === 'bulk'"),
    'manual reads only delivery mode before deciding bulk guard'
);

$apiBlock = $methodBlocks['api_broadcast_start'];
whatsapp_broadcast_csrf_check(
    strpos($apiBlock, "can(self::PAGE_BROADCAST, 'edit')") < strpos($apiBlock, 'require_wa_broadcast_mutation_csrf'),
    'dispatch keeps initial broadcast RBAC selection before CSRF'
);
whatsapp_broadcast_csrf_check(
    strpos($apiBlock, 'require_wa_broadcast_mutation_csrf') < strpos($apiBlock, "where('id', \$id)"),
    'dispatch guards before queue DB read'
);
whatsapp_broadcast_csrf_check(
    strpos($apiBlock, 'require_wa_broadcast_mutation_csrf') < strpos($apiBlock, 'GET_LOCK'),
    'dispatch guards before queue lock'
);
whatsapp_broadcast_csrf_check(
    strpos($apiBlock, 'require_wa_broadcast_mutation_csrf') < strpos($apiBlock, 'callBotApi'),
    'dispatch guards before Bot API'
);

$guard = (new ReflectionClass(Whatsapp::class))->getMethod('require_wa_broadcast_mutation_csrf');
$guard->setAccessible(true);

$writerPermissions = ['wa.broadcast' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true]];
$writerCases = [
    ['broadcast create missing', 'broadcast_create', 'POST', ['create' => true], ['wa.broadcast_mutation_csrf' => '']],
    ['broadcast edit missing', 'broadcast_edit', 'POST', ['edit' => true], ['wa.broadcast_mutation_csrf' => '']],
    ['broadcast delete GET', 'broadcast_delete', 'GET', ['delete' => true], []],
    ['broadcast delete missing', 'broadcast_delete', 'POST', ['delete' => true], ['wa_broadcast_mutation_csrf' => '']],
    ['broadcast deactivate GET', 'broadcast_deactivate', 'GET', ['edit' => true], []],
    ['broadcast deactivate missing', 'broadcast_deactivate', 'POST', ['edit' => true], ['wa_broadcast_mutation_csrf' => '']],
];
foreach ($writerCases as [$label, $method, $requestMethod, $actionPermission, $post]) {
    $fixture = whatsapp_broadcast_csrf_fixture(
        $requestMethod,
        $post,
        ['wa.broadcast' => $actionPermission]
    );
    $fixture['controller']->{$method}(17);
    whatsapp_broadcast_csrf_reject($fixture, $requestMethod === 'GET' ? 405 : 403, $label);
    whatsapp_broadcast_csrf_no_business_access($fixture, $label);
    whatsapp_broadcast_csrf_check(
        $fixture['controller']->permissionCalls === [['wa.broadcast', array_key_first($actionPermission)]],
        $label . ' performs RBAC before rejection'
    );
}

$manualFixture = whatsapp_broadcast_csrf_fixture(
    'POST',
    ['delivery_mode' => 'bulk', 'name' => 'business-sentinel', 'manual_numbers' => 'business-sentinel'],
    ['wa.manual' => ['view' => true, 'create' => true]]
);
$manualFixture['controller']->manual();
whatsapp_broadcast_csrf_reject($manualFixture, 403, 'manual bulk missing token');
whatsapp_broadcast_csrf_no_business_access($manualFixture, 'manual bulk missing token');
whatsapp_broadcast_csrf_check(
    $manualFixture['controller']->permissionCalls === [['wa.manual', 'view'], ['wa.manual', 'create']],
    'manual bulk keeps view and create RBAC before CSRF'
);

$apiGet = whatsapp_broadcast_csrf_fixture(
    'GET', [], ['wa.broadcast' => ['edit' => true], 'wa.manual' => ['create' => true]]
);
$apiGet['controller']->api_broadcast_start(17);
whatsapp_broadcast_csrf_reject($apiGet, 405, 'dispatch GET');
whatsapp_broadcast_csrf_no_business_access($apiGet, 'dispatch GET');
whatsapp_broadcast_csrf_check(
    $apiGet['controller']->canCalls === [['wa.broadcast', 'edit'], ['wa.manual', 'create']],
    'dispatch selection runs before CSRF'
);

$apiTokens = [
    'missing' => '',
    'malformed' => str_repeat('A', 64),
    'mismatch' => WA_BROADCAST_CSRF_OTHER,
    'Batch 37 engine token' => WA_BROADCAST_CSRF_ENGINE,
    'Batch 38 env token' => WA_BROADCAST_CSRF_ENV,
    'Batch 39 template token' => WA_BROADCAST_CSRF_TEMPLATE,
    'Batch 40 schedule token' => WA_BROADCAST_CSRF_SCHEDULE,
];
foreach ($apiTokens as $label => $token) {
    $fixture = whatsapp_broadcast_csrf_fixture(
        'POST',
        ['retry' => '1', 'wa_broadcast_mutation_csrf' => $token],
        ['wa.broadcast' => ['edit' => true], 'wa.manual' => ['create' => true]],
        [WA_BROADCAST_CSRF_SESSION => WA_BROADCAST_CSRF_VALID],
        ['wa_broadcast_mutation_csrf' => $token],
        ['X-Wa-Broadcast-CSRF' => $token]
    );
    $fixture['controller']->api_broadcast_start(17);
    whatsapp_broadcast_csrf_reject($fixture, 403, 'dispatch ' . $label);
    whatsapp_broadcast_csrf_no_business_access($fixture, 'dispatch ' . $label);
    whatsapp_broadcast_csrf_check(
        $fixture['input']->headerReads === ['X-Wa-Broadcast-Csrf'],
        'dispatch ' . $label . ' reads only canonical scoped header'
    );
}

$alternativeOnly = whatsapp_broadcast_csrf_fixture(
    'POST', [], ['wa.broadcast' => ['edit' => true]],
    [WA_BROADCAST_CSRF_SESSION => WA_BROADCAST_CSRF_VALID],
    ['wa_broadcast_mutation_csrf' => WA_BROADCAST_CSRF_VALID],
    [],
    []
);
$alternativeOnly['controller']->api_broadcast_start(17);
whatsapp_broadcast_csrf_reject($alternativeOnly, 403, 'dispatch query/body-only token');
whatsapp_broadcast_csrf_check(
    $alternativeOnly['input']->getReads === [] && $alternativeOnly['input']->rawReads === 0,
    'dispatch does not use query or raw body as token fallback'
);

$validHeader = whatsapp_broadcast_csrf_fixture(
    'POST', [], ['wa.broadcast' => ['edit' => true]],
    [WA_BROADCAST_CSRF_SESSION => WA_BROADCAST_CSRF_VALID], [],
    ['X-Wa-Broadcast-CSRF' => WA_BROADCAST_CSRF_VALID]
);
whatsapp_broadcast_csrf_check(
    $guard->invoke($validHeader['controller'], true) === true,
    'valid dispatch header token passes scoped guard'
);
whatsapp_broadcast_csrf_check($validHeader['output']->status === null, 'valid dispatch guard has no rejection');

$validForm = whatsapp_broadcast_csrf_fixture(
    'POST', [WA_BROADCAST_CSRF_FIELD => WA_BROADCAST_CSRF_VALID], [],
    [WA_BROADCAST_CSRF_SESSION => WA_BROADCAST_CSRF_VALID]
);
whatsapp_broadcast_csrf_check(
    $guard->invoke($validForm['controller']) === true,
    'valid form token passes scoped guard'
);

$invalidSession = whatsapp_broadcast_csrf_fixture(
    'POST', [WA_BROADCAST_CSRF_FIELD => WA_BROADCAST_CSRF_VALID], [],
    [WA_BROADCAST_CSRF_SESSION => 'short']
);
whatsapp_broadcast_csrf_check($guard->invoke($invalidSession['controller']) === false, 'malformed session token is rejected');
whatsapp_broadcast_csrf_reject($invalidSession, 403, 'malformed session token');

$createGet = whatsapp_broadcast_csrf_fixture('GET', [], ['wa.broadcast' => ['create' => true]]);
$createGet['controller']->broadcast_create();
$createToken = $createGet['controller']->renderedData[WA_BROADCAST_CSRF_FIELD] ?? '';
whatsapp_broadcast_csrf_check(
    is_string($createToken) && preg_match('/\A[0-9a-f]{64}\z/D', $createToken) === 1
        && $createGet['session']->value(WA_BROADCAST_CSRF_SESSION) === $createToken,
    'broadcast create writer GET mints a scoped token'
);

$createViewOnly = whatsapp_broadcast_csrf_fixture('GET', [], ['wa.broadcast' => ['view' => true]]);
$createDenied = null;
try {
    $createViewOnly['controller']->broadcast_create();
} catch (Throwable $exception) {
    $createDenied = $exception;
}
whatsapp_broadcast_csrf_check($createDenied instanceof WhatsappBroadcastCsrfDenied, 'broadcast create view-only role is denied');
whatsapp_broadcast_csrf_check($createViewOnly['session']->writes === [], 'view-only create does not mint token');

$editGet = whatsapp_broadcast_csrf_fixture(
    'GET', [], ['wa.broadcast' => ['edit' => true]], [], [], [],
    ['id' => 17, 'status' => 'DRAFT', 'delay_pattern_json' => null]
);
$editGet['controller']->broadcast_edit(17);
$editToken = $editGet['controller']->renderedData[WA_BROADCAST_CSRF_FIELD] ?? '';
whatsapp_broadcast_csrf_check(
    is_string($editToken) && preg_match('/\A[0-9a-f]{64}\z/D', $editToken) === 1,
    'broadcast edit writer GET mints a scoped token'
);

foreach (
    [
        ['broadcast list view-only', 'broadcast', ['wa.broadcast' => ['view' => true]], WA_BROADCAST_CSRF_FIELD],
        ['broadcast list writer', 'broadcast', $writerPermissions, WA_BROADCAST_CSRF_FIELD],
    ] as [$label, $method, $permissions, $tokenKey]
) {
    $fixture = whatsapp_broadcast_csrf_fixture('GET', [], $permissions);
    $fixture['controller']->{$method}();
    $hasToken = array_key_exists($tokenKey, $fixture['controller']->renderedData);
    whatsapp_broadcast_csrf_check(
        $hasToken === ($label === 'broadcast list writer'),
        $label . ' token visibility matches writer role'
    );
}

$manualView = whatsapp_broadcast_csrf_fixture('GET', [], ['wa.manual' => ['view' => true]], [], ['tab' => 'bulk']);
$manualView['controller']->manual();
whatsapp_broadcast_csrf_check(
    !array_key_exists(WA_BROADCAST_CSRF_FIELD, $manualView['controller']->renderedData)
        && $manualView['session']->writes === [],
    'manual bulk view-only GET does not mint token'
);
$manualWriter = whatsapp_broadcast_csrf_fixture('GET', [], ['wa.manual' => ['view' => true, 'create' => true]], [], ['tab' => 'bulk']);
$manualWriter['controller']->manual();
whatsapp_broadcast_csrf_check(
    preg_match('/\A[0-9a-f]{64}\z/D', (string)($manualWriter['controller']->renderedData[WA_BROADCAST_CSRF_FIELD] ?? '')) === 1,
    'manual bulk writer GET mints a scoped token'
);

$viewSources = [
    'broadcast list' => (string)file_get_contents(dirname(__DIR__, 2) . '/application/views/wa/broadcast.php'),
    'broadcast form' => (string)file_get_contents(dirname(__DIR__, 2) . '/application/views/wa/broadcast_form.php'),
    'broadcast detail' => (string)file_get_contents(dirname(__DIR__, 2) . '/application/views/wa/broadcast_detail.php'),
    'manual' => (string)file_get_contents(dirname(__DIR__, 2) . '/application/views/wa/manual.php'),
];
whatsapp_broadcast_csrf_check(strpos($viewSources['broadcast list'], 'name="wa_broadcast_mutation_csrf"') !== false, 'broadcast list mutation forms contain token');
whatsapp_broadcast_csrf_check(strpos($viewSources['broadcast form'], 'name="wa_broadcast_mutation_csrf"') !== false, 'broadcast create/edit form contains token');
whatsapp_broadcast_csrf_check(strpos($viewSources['broadcast detail'], 'name="wa_broadcast_mutation_csrf"') !== false, 'broadcast detail delete form contains token');
whatsapp_broadcast_csrf_check(strpos($viewSources['manual'], 'name="wa_broadcast_mutation_csrf"') !== false, 'manual bulk form contains token');
foreach (['broadcast list', 'broadcast detail'] as $label) {
    whatsapp_broadcast_csrf_check(
        strpos($viewSources[$label], "<a href=\"<?= site_url('wa/broadcast/delete") === false,
        $label . ' has no GET delete anchor'
    );
}
foreach (['broadcast detail', 'manual'] as $label) {
    whatsapp_broadcast_csrf_check(
        substr_count($viewSources[$label], "method: 'POST'") >= 1
            && strpos($viewSources[$label], "'X-Wa-Broadcast-CSRF'") !== false,
        $label . ' dispatch caller uses POST and scoped header'
    );
}
whatsapp_broadcast_csrf_check(strpos($viewSources['broadcast detail'], '?retry=1&line_id=') !== false, 'broadcast retry query remains available');
whatsapp_broadcast_csrf_check(strpos($viewSources['manual'], '?retry=1&line_id=') !== false, 'manual retry query remains available');

$guardSource = whatsapp_broadcast_csrf_method_source($controllerSource, 'require_wa_broadcast_mutation_csrf');
whatsapp_broadcast_csrf_check(strpos($guardSource, 'hash_equals') !== false, 'scoped guard uses constant-time comparison');
whatsapp_broadcast_csrf_check(strpos($guardSource, 'random_bytes') === false, 'guard never mints tokens during validation');
whatsapp_broadcast_csrf_check(strpos($guardSource, 'raw_input_stream') === false, 'guard never reads raw request body');
whatsapp_broadcast_csrf_check(strpos($guardSource, 'get_request_header') !== false, 'dispatch guard reads dedicated header path');

if ($whatsappBroadcastCsrfFailures !== []) {
    foreach ($whatsappBroadcastCsrfFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, '[FAIL] WhatsApp broadcast mutation CSRF smoke: ' . count($whatsappBroadcastCsrfFailures) . ' failure(s), ' . $whatsappBroadcastCsrfChecks . ' checks.' . PHP_EOL);
    exit(1);
}

echo '[PASS] WhatsApp broadcast mutation CSRF smoke: ' . $whatsappBroadcastCsrfChecks . ' checks; broadcast/manual mutations and dispatch transport verified without DB/network/bootstrap.' . PHP_EOL;
