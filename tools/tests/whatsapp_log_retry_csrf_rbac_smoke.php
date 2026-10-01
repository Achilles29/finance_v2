<?php

declare(strict_types=1);

/**
 * DB/bootstrap/network-free smoke test for WhatsApp log-retry CSRF and RBAC.
 *
 * The real controller is loaded without its constructor and exercised with
 * in-memory CI doubles. No credentials, runtime log, upload, DB connection,
 * or Bot API request is opened.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_LOG_RETRY_CSRF_FIELD = 'wa_log_retry_csrf';
const WA_LOG_RETRY_CSRF_SESSION = 'wa_log_retry_csrf';
const WA_LOG_RETRY_CSRF_VALID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WA_LOG_RETRY_CSRF_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const WA_LOG_RETRY_CSRF_ENGINE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const WA_LOG_RETRY_CSRF_ENV = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
const WA_LOG_RETRY_CSRF_TEMPLATE = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
const WA_LOG_RETRY_CSRF_SCHEDULE = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
const WA_LOG_RETRY_CSRF_BROADCAST = '9999999999999999999999999999999999999999999999999999999999999999';

final class WhatsappLogRetryCsrfTrace
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

final class WhatsappLogRetryCsrfDenied extends RuntimeException
{
}

final class WhatsappLogRetryCsrfInput
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
        WhatsappLogRetryCsrfTrace::add('input:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $key = (string)$key;
        $this->postReads[] = $key;
        WhatsappLogRetryCsrfTrace::add('input:post:' . $key);
        return $this->postData[$key] ?? null;
    }

    public function get($key = null, $xssClean = false)
    {
        $key = (string)$key;
        $this->getReads[] = $key;
        WhatsappLogRetryCsrfTrace::add('input:get:' . $key);
        return $this->getData[$key] ?? '';
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        WhatsappLogRetryCsrfTrace::add('input:header:' . $key);
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
            WhatsappLogRetryCsrfTrace::add('input:raw');
            return '';
        }
        throw new RuntimeException('Unexpected input property: ' . (string)$key);
    }
}

final class WhatsappLogRetryCsrfSession
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
        WhatsappLogRetryCsrfTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->values[$key] = $value;
        $this->writes[$key] = (string)$value;
        WhatsappLogRetryCsrfTrace::add('session:write:' . $key);
    }

    public function set_flashdata($key, $value): void
    {
        $this->flash[(string)$key] = (string)$value;
        WhatsappLogRetryCsrfTrace::add('session:flash:' . (string)$key);
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

final class WhatsappLogRetryCsrfOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        WhatsappLogRetryCsrfTrace::add('output:status:' . $this->status);
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

final class WhatsappLogRetryCsrfDb
{
    public array $events = [];
    public array $row = [];

    public function __call($method, $args): self
    {
        $this->events[] = 'db:' . (string)$method;
        WhatsappLogRetryCsrfTrace::add('db:' . (string)$method);
        return $this;
    }

    public function count_all($table): int
    {
        $this->events[] = 'db:count_all';
        return 0;
    }

    public function count_all_results($table = ''): int
    {
        $this->events[] = 'db:count_all_results';
        return 0;
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
        WhatsappLogRetryCsrfTrace::add('rbac:' . $page . ':' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappLogRetryCsrfDenied('permission denied', 403);
        }
    }

    public function can($page, $action = 'view'): bool
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->canCalls[] = [$page, $action];
        WhatsappLogRetryCsrfTrace::add('can:' . $page . ':' . $action);
        return !empty($this->permissions[$page][$action]);
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
        WhatsappLogRetryCsrfTrace::add('render:' . (string)$view);
    }
}

if (!function_exists('redirect')) {
    function redirect($uri = '', $method = 'auto', $code = null): void
    {
        WhatsappLogRetryCsrfTrace::add('redirect:' . (string)$uri);
    }
}

if (!function_exists('show_404')) {
    function show_404($page = '', $log_error = true): void
    {
        throw new WhatsappLogRetryCsrfDenied('404', 404);
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

$whatsappLogRetryCsrfChecks = 0;
$whatsappLogRetryCsrfFailures = [];

function whatsapp_log_retry_csrf_check(bool $condition, string $message): void
{
    global $whatsappLogRetryCsrfChecks, $whatsappLogRetryCsrfFailures;
    $whatsappLogRetryCsrfChecks++;
    if (!$condition) {
        $whatsappLogRetryCsrfFailures[] = $message;
    }
}

function whatsapp_log_retry_csrf_source_method(string $source, string $method): string
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

function whatsapp_log_retry_csrf_fixture(
    string $method,
    array $post = [],
    array $permissions = [],
    array $sessionValues = [],
    array $get = [],
    array $headers = [],
    array $row = []
): array {
    WhatsappLogRetryCsrfTrace::reset();
    $input = new WhatsappLogRetryCsrfInput($method, $post, $get, $headers);
    $session = new WhatsappLogRetryCsrfSession($sessionValues);
    $output = new WhatsappLogRetryCsrfOutput();
    $db = new WhatsappLogRetryCsrfDb();
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

function whatsapp_log_retry_csrf_rejection(array $fixture, int $status, string $label): void
{
    $decoded = json_decode($fixture['output']->body, true);
    whatsapp_log_retry_csrf_check($fixture['output']->status === $status, $label . ' status ' . $status);
    whatsapp_log_retry_csrf_check(
        $fixture['output']->contentType === 'application/json'
            && is_array($decoded)
            && ($decoded['ok'] ?? null) === false,
        $label . ' structured JSON rejection'
    );
}

function whatsapp_log_retry_csrf_no_dependency(array $fixture, string $label): void
{
    whatsapp_log_retry_csrf_check($fixture['db']->events === [], $label . ' reaches no DB/log dependency');
    whatsapp_log_retry_csrf_check($fixture['session']->flash === [], $label . ' emits no flash side effect');
    whatsapp_log_retry_csrf_check($fixture['input']->rawReads === 0, $label . ' reads no raw body');
}

function whatsapp_log_retry_csrf_run_behavior_case(string $case): void
{
    $validSession = [WA_LOG_RETRY_CSRF_SESSION => WA_LOG_RETRY_CSRF_VALID];
    $validHeader = ['X-Wa-Log-Retry-CSRF' => WA_LOG_RETRY_CSRF_VALID];
    $fixture = null;

    switch ($case) {
        case 'log-view-only':
            $fixture = whatsapp_log_retry_csrf_fixture(
                'POST', [], ['wa.log' => ['view' => true]], $validSession, [], $validHeader
            );
            break;
        case 'group-with-manual-only':
            $fixture = whatsapp_log_retry_csrf_fixture(
                'POST', [], ['wa.manual' => ['create' => true]], $validSession, [], $validHeader,
                ['status' => 'FAILED', 'source' => 'GROUP', 'group_jid' => 'safe-group@g.us', 'message_preview' => 'safe']
            );
            break;
        case 'manual-with-group-only':
            $fixture = whatsapp_log_retry_csrf_fixture(
                'POST', [], ['wa.group' => ['create' => true]], $validSession, [], $validHeader,
                ['status' => 'FAILED', 'source' => 'MANUAL', 'phone_number' => '628000000000', 'message_preview' => 'safe']
            );
            break;
        case 'broadcast-detail-only':
            $fixture = whatsapp_log_retry_csrf_fixture(
                'POST', [], ['wa.broadcast' => ['edit' => true]], $validSession, [], $validHeader,
                ['status' => 'FAILED', 'source' => 'BROADCAST', 'broadcast_id' => 42, 'message_preview' => 'safe']
            );
            break;
        case 'broadcast-misleading-group-jid':
            $fixture = whatsapp_log_retry_csrf_fixture(
                'POST', [], ['wa.group' => ['create' => true]], $validSession, [], $validHeader,
                [
                    'status' => 'FAILED',
                    'source' => 'BROADCAST',
                    'broadcast_id' => 42,
                    'group_jid' => 'misleading-group@g.us',
                    'message_preview' => 'safe',
                ]
            );
            break;
        case 'group-with-personal-circuit-disabled':
            // An empty GROUP message proves execution passes the personal-only
            // circuit breaker, then stops before any Bot API/network call.
            $fixture = whatsapp_log_retry_csrf_fixture(
                'POST', [], ['wa.group' => ['create' => true]], $validSession, [], $validHeader,
                ['status' => 'FAILED', 'source' => 'GROUP', 'group_jid' => 'safe-group@g.us', 'message_preview' => '']
            );
            break;
        default:
            fwrite(STDERR, 'Unknown behavior case.' . PHP_EOL);
            exit(2);
    }

    $fixture['controller']->api_log_retry(17);
    fwrite(STDERR, 'Behavior case returned without JSON termination.' . PHP_EOL);
    exit(3);
}

function whatsapp_log_retry_csrf_behavior_response(string $case): array
{
    $command = [PHP_BINARY, __FILE__, '--behavior-case=' . $case];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['exit' => -1, 'stdout' => '', 'stderr' => 'Unable to start behavior subprocess.', 'json' => null];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    return [
        'exit' => $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'json' => json_decode($stdout, true),
    ];
}

if (isset($argv[1]) && strncmp((string)$argv[1], '--behavior-case=', 16) === 0) {
    whatsapp_log_retry_csrf_run_behavior_case(substr((string)$argv[1], 16));
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Whatsapp.php');
$apiSource = whatsapp_log_retry_csrf_source_method($controllerSource, 'api_log_retry');
$guardSource = whatsapp_log_retry_csrf_source_method($controllerSource, 'require_wa_log_retry_csrf');
whatsapp_log_retry_csrf_check($apiSource !== '', 'api_log_retry source is present');
whatsapp_log_retry_csrf_check($guardSource !== '', 'retry CSRF guard source is present');
whatsapp_log_retry_csrf_check(strpos($controllerSource, 'WA_LOG_RETRY_CSRF_SESSION_KEY') !== false, 'retry CSRF session scope is declared');
whatsapp_log_retry_csrf_check(strpos($controllerSource, "WA_LOG_RETRY_CSRF_CI_HEADER = 'X-Wa-Log-Retry-Csrf'") !== false, 'canonical retry header is declared');
whatsapp_log_retry_csrf_check(strpos($guardSource, 'hash_equals') !== false, 'retry guard uses constant-time comparison');
whatsapp_log_retry_csrf_check(strpos($guardSource, 'raw_input_stream') === false, 'retry guard never reads raw body');
whatsapp_log_retry_csrf_check(strpos($guardSource, 'get_request_header') !== false, 'retry guard reads a dedicated header');
whatsapp_log_retry_csrf_check(strpos($guardSource, "input->get(") === false, 'retry guard has no query-token fallback');
whatsapp_log_retry_csrf_check(strpos($guardSource, "input->post(") === false, 'retry guard has no form/JSON-token fallback');

$guardPos = strpos($apiSource, 'require_wa_log_retry_csrf');
whatsapp_log_retry_csrf_check($guardPos !== false, 'api endpoint calls the retry CSRF guard');
whatsapp_log_retry_csrf_check(strpos($apiSource, 'can(self::PAGE_MANUAL') < $guardPos, 'coarse outbound RBAC precedes CSRF');
whatsapp_log_retry_csrf_check($guardPos < strpos($apiSource, "from('wa_send_log"), 'CSRF precedes log DB read');
whatsapp_log_retry_csrf_check($guardPos < strpos($apiSource, 'retryMessageFromLog'), 'CSRF precedes message reconstruction');
whatsapp_log_retry_csrf_check($guardPos < strpos($apiSource, 'personalOutboundEnabled'), 'CSRF precedes circuit-breaker/dependency');
whatsapp_log_retry_csrf_check($guardPos < strpos($apiSource, 'callBotApi'), 'CSRF precedes Bot API');
whatsapp_log_retry_csrf_check($guardPos < strpos($apiSource, 'logSend'), 'CSRF precedes log writer');

whatsapp_log_retry_csrf_check(strpos($apiSource, "can(self::PAGE_LOG, 'view')") === false, 'log view is not a coarse outbound permission');
whatsapp_log_retry_csrf_check(strpos($apiSource, "PAGE_LOG, 'view'") === false, 'log view is not used as retry action fallback');
whatsapp_log_retry_csrf_check(strpos($apiSource, "if (\$source === 'BROADCAST')") !== false, 'broadcast retry is classified before outbound branches');
whatsapp_log_retry_csrf_check(strpos($apiSource, "can(self::PAGE_BROADCAST, 'edit')") !== false, 'broadcast retry keeps edit authorization');
whatsapp_log_retry_csrf_check(strpos($apiSource, "can(self::PAGE_GROUP, 'create')") !== false, 'group retry requires group create');
whatsapp_log_retry_csrf_check(strpos($apiSource, "can(self::PAGE_MANUAL, 'create')") !== false, 'personal retry requires manual create');
whatsapp_log_retry_csrf_check(strpos($apiSource, 'if ($isGroup)') !== false && strpos($apiSource, 'elseif (!$this->can(self::PAGE_MANUAL') !== false, 'group and personal action branches remain distinct');

$writerPermissions = ['wa.manual' => ['create' => true]];
$writer = ['wa.manual' => ['create' => true], 'wa.group' => ['create' => true], 'wa.broadcast' => ['edit' => true]];
foreach (['GET', 'HEAD', 'PUT'] as $method) {
    $fixture = whatsapp_log_retry_csrf_fixture($method, [], $writer);
    $fixture['controller']->api_log_retry(17);
    whatsapp_log_retry_csrf_rejection($fixture, 405, 'dispatch ' . $method);
    whatsapp_log_retry_csrf_no_dependency($fixture, 'dispatch ' . $method);
}

$invalidTokens = [
    'missing' => '',
    'uppercase' => str_repeat('A', 64),
    'mismatch' => WA_LOG_RETRY_CSRF_OTHER,
    'Batch 37 engine token' => WA_LOG_RETRY_CSRF_ENGINE,
    'Batch 38 env token' => WA_LOG_RETRY_CSRF_ENV,
    'Batch 39 template token' => WA_LOG_RETRY_CSRF_TEMPLATE,
    'Batch 40 schedule token' => WA_LOG_RETRY_CSRF_SCHEDULE,
    'Batch 41 broadcast token' => WA_LOG_RETRY_CSRF_BROADCAST,
];
foreach ($invalidTokens as $label => $token) {
    $fixture = whatsapp_log_retry_csrf_fixture(
        'POST',
        ['wa_log_retry_csrf' => $token, 'message' => 'business-sentinel'],
        $writer,
        [WA_LOG_RETRY_CSRF_SESSION => WA_LOG_RETRY_CSRF_VALID],
        ['wa_log_retry_csrf' => $token],
        ['X-Wa-Log-Retry-CSRF' => $token]
    );
    $fixture['controller']->api_log_retry(17);
    whatsapp_log_retry_csrf_rejection($fixture, 403, 'dispatch ' . $label);
    whatsapp_log_retry_csrf_no_dependency($fixture, 'dispatch ' . $label);
    whatsapp_log_retry_csrf_check(
        $fixture['input']->headerReads === ['X-Wa-Log-Retry-Csrf'],
        'dispatch ' . $label . ' reads only canonical retry header'
    );
}

$alternativeOnly = whatsapp_log_retry_csrf_fixture(
    'POST',
    ['wa_log_retry_csrf' => WA_LOG_RETRY_CSRF_VALID],
    $writer,
    [WA_LOG_RETRY_CSRF_SESSION => WA_LOG_RETRY_CSRF_VALID],
    ['wa_log_retry_csrf' => WA_LOG_RETRY_CSRF_VALID]
);
$alternativeOnly['controller']->api_log_retry(17);
whatsapp_log_retry_csrf_rejection($alternativeOnly, 403, 'query/form-only token');
whatsapp_log_retry_csrf_no_dependency($alternativeOnly, 'query/form-only token');
whatsapp_log_retry_csrf_check($alternativeOnly['input']->getReads === [], 'query-only token is not read');
whatsapp_log_retry_csrf_check($alternativeOnly['input']->postReads === [], 'form-only token is not read');

$validHeader = whatsapp_log_retry_csrf_fixture(
    'POST', [], $writer,
    [WA_LOG_RETRY_CSRF_SESSION => WA_LOG_RETRY_CSRF_VALID], [],
    ['X-Wa-Log-Retry-CSRF' => WA_LOG_RETRY_CSRF_VALID]
);
$guard = (new ReflectionClass(Whatsapp::class))->getMethod('require_wa_log_retry_csrf');
$guard->setAccessible(true);
whatsapp_log_retry_csrf_check($guard->invoke($validHeader['controller']) === true, 'valid dedicated header passes retry guard');
whatsapp_log_retry_csrf_check($validHeader['output']->status === null, 'valid retry guard has no rejection');

$malformedSession = whatsapp_log_retry_csrf_fixture(
    'POST', [], $writer,
    [WA_LOG_RETRY_CSRF_SESSION => 'short'], [],
    ['X-Wa-Log-Retry-CSRF' => WA_LOG_RETRY_CSRF_VALID]
);
whatsapp_log_retry_csrf_check($guard->invoke($malformedSession['controller']) === false, 'malformed session token is rejected');
whatsapp_log_retry_csrf_rejection($malformedSession, 403, 'malformed session token');

$viewOnly = whatsapp_log_retry_csrf_fixture('GET', [], ['wa.log' => ['view' => true]]);
$viewOnly['controller']->log();
whatsapp_log_retry_csrf_check(!array_key_exists(WA_LOG_RETRY_CSRF_FIELD, $viewOnly['controller']->renderedData), 'log view-only data has no retry token');
whatsapp_log_retry_csrf_check($viewOnly['session']->writes === [], 'log view-only GET does not mint token');

$manualWriterView = whatsapp_log_retry_csrf_fixture(
    'GET', [], ['wa.log' => ['view' => true], 'wa.manual' => ['create' => true]]
);
$manualWriterView['controller']->log();
whatsapp_log_retry_csrf_check(
    preg_match('/\A[0-9a-f]{64}\z/D', (string)($manualWriterView['controller']->renderedData[WA_LOG_RETRY_CSRF_FIELD] ?? '')) === 1,
    'manual retry writer receives a scoped token on log view'
);

$groupWriterView = whatsapp_log_retry_csrf_fixture(
    'GET', [], ['wa.log' => ['view' => true], 'wa.group' => ['create' => true]]
);
$groupWriterView['controller']->log();
whatsapp_log_retry_csrf_check(
    preg_match('/\A[0-9a-f]{64}\z/D', (string)($groupWriterView['controller']->renderedData[WA_LOG_RETRY_CSRF_FIELD] ?? '')) === 1,
    'group retry writer receives a scoped token on log view'
);

$manualGroupOnly = whatsapp_log_retry_csrf_fixture(
    'GET', [], ['wa.manual' => ['view' => true], 'wa.group' => ['create' => true]], [], ['tab' => 'single']
);
$manualGroupOnly['controller']->manual();
whatsapp_log_retry_csrf_check(
    !array_key_exists(WA_LOG_RETRY_CSRF_FIELD, $manualGroupOnly['controller']->renderedData),
    'manual single view does not expose retry token for group-only create permission'
);
whatsapp_log_retry_csrf_check(
    !in_array(['wa.group', 'create'], $manualGroupOnly['controller']->canCalls, true),
    'manual view does not request group create permission'
);
whatsapp_log_retry_csrf_check(
    !array_key_exists('can_retry_group', $manualGroupOnly['controller']->renderedData),
    'manual view data omits unused group retry capability'
);

$manualSingleWriter = whatsapp_log_retry_csrf_fixture(
    'GET', [], ['wa.manual' => ['view' => true, 'create' => true]], [], ['tab' => 'single']
);
$manualSingleWriter['controller']->manual();
whatsapp_log_retry_csrf_check(
    preg_match('/\A[0-9a-f]{64}\z/D', (string)($manualSingleWriter['controller']->renderedData[WA_LOG_RETRY_CSRF_FIELD] ?? '')) === 1,
    'manual create permission receives retry token when single-tab retry UI renders'
);

$manualBulkWriter = whatsapp_log_retry_csrf_fixture(
    'GET', [], ['wa.manual' => ['view' => true, 'create' => true]], [], ['tab' => 'bulk']
);
$manualBulkWriter['controller']->manual();
whatsapp_log_retry_csrf_check(
    !array_key_exists(WA_LOG_RETRY_CSRF_FIELD, $manualBulkWriter['controller']->renderedData),
    'manual bulk tab does not expose single-log retry token'
);
whatsapp_log_retry_csrf_check(
    !array_key_exists(WA_LOG_RETRY_CSRF_SESSION, $manualBulkWriter['session']->writes),
    'manual bulk tab does not mint single-log retry token'
);

$behaviorCases = [
    'log-view-only' => static function (array $json): bool {
        return ($json['ok'] ?? null) === false && ($json['message'] ?? '') === 'Akses ditolak.';
    },
    'group-with-manual-only' => static function (array $json): bool {
        return ($json['ok'] ?? null) === false && ($json['message'] ?? '') === 'Akses retry pesan grup ditolak.';
    },
    'manual-with-group-only' => static function (array $json): bool {
        return ($json['ok'] ?? null) === false && ($json['message'] ?? '') === 'Akses retry pesan manual ditolak.';
    },
    'broadcast-detail-only' => static function (array $json): bool {
        return ($json['ok'] ?? null) === false
            && ($json['open_broadcast'] ?? null) === true
            && ($json['broadcast_id'] ?? null) === 42;
    },
    'broadcast-misleading-group-jid' => static function (array $json): bool {
        return ($json['ok'] ?? null) === false && ($json['message'] ?? '') === 'Akses retry broadcast ditolak.';
    },
    'group-with-personal-circuit-disabled' => static function (array $json): bool {
        return ($json['ok'] ?? null) === false
            && ($json['message'] ?? '') === 'Isi pesan pada log kosong, tidak bisa dikirim ulang.';
    },
];
foreach ($behaviorCases as $case => $assertion) {
    $response = whatsapp_log_retry_csrf_behavior_response($case);
    whatsapp_log_retry_csrf_check($response['exit'] === 0, $case . ' behavior subprocess exits cleanly');
    whatsapp_log_retry_csrf_check($response['stderr'] === '', $case . ' behavior subprocess has no warning/error');
    whatsapp_log_retry_csrf_check(is_array($response['json']), $case . ' behavior returns JSON');
    whatsapp_log_retry_csrf_check(
        is_array($response['json']) && $assertion($response['json']),
        $case . ' behavior preserves least-privilege classification'
    );
}

$viewSources = [
    'dashboard' => (string)file_get_contents($root . '/application/views/wa/dashboard.php'),
    'log' => (string)file_get_contents($root . '/application/views/wa/log.php'),
    'manual' => (string)file_get_contents($root . '/application/views/wa/manual.php'),
];
foreach ($viewSources as $label => $source) {
    whatsapp_log_retry_csrf_check(strpos($source, 'X-Wa-Log-Retry-CSRF') !== false, $label . ' caller uses dedicated retry header');
    whatsapp_log_retry_csrf_check(strpos($source, "method: 'POST'") !== false, $label . ' caller uses POST');
    whatsapp_log_retry_csrf_check(strpos($source, "credentials: 'same-origin'") !== false, $label . ' caller uses same-origin credentials');
}
foreach (['dashboard', 'log'] as $label) {
    whatsapp_log_retry_csrf_check(strpos($viewSources[$label], '$isGroupLog && $canRetryGroup') !== false, $label . ' gates group retry by group create');
    whatsapp_log_retry_csrf_check(strpos($viewSources[$label], '!$isBroadcastLog && !$isGroupLog && $canRetryManual') !== false, $label . ' gates personal retry by manual create');
    whatsapp_log_retry_csrf_check(strpos($viewSources[$label], '$isBroadcastLog && !empty($log[\'broadcast_id\']) && $canBroadcastEdit') !== false, $label . ' gates broadcast detail link by broadcast edit');
}
whatsapp_log_retry_csrf_check(strpos($viewSources['manual'], '($log[\'status\'] ?? \'\') === \'FAILED\' && $canRetryManual') !== false, 'manual caller gates retry by manual create');
whatsapp_log_retry_csrf_check(strpos($viewSources['manual'], "fetch('<?= site_url('wa/api/log-retry/') ?>'") !== false, 'manual retry caller remains first-party');

$dashboardMethod = whatsapp_log_retry_csrf_source_method($controllerSource, 'dashboard');
$logMethod = whatsapp_log_retry_csrf_source_method($controllerSource, 'log');
$manualMethod = whatsapp_log_retry_csrf_source_method($controllerSource, 'manual');
foreach ([$dashboardMethod, $logMethod, $manualMethod] as $methodSource) {
    whatsapp_log_retry_csrf_check(strpos($methodSource, 'can(self::PAGE_MANUAL, \'create\')') !== false, 'view controller computes manual retry permission');
    whatsapp_log_retry_csrf_check(strpos($methodSource, 'can(self::PAGE_GROUP, \'create\')') !== false || $methodSource === $manualMethod, 'view controller preserves group retry distinction where applicable');
}
whatsapp_log_retry_csrf_check(strpos($manualMethod, "can(self::PAGE_GROUP, 'create')") === false, 'manual controller does not request group retry permission');
whatsapp_log_retry_csrf_check(strpos($manualMethod, "'can_retry_group'") === false, 'manual controller omits unused group retry view data');
whatsapp_log_retry_csrf_check(strpos($manualMethod, "if (\$canCreate && \$tab === 'single')") !== false, 'manual controller mints retry token only for renderable single-tab UI');

if ($whatsappLogRetryCsrfFailures !== []) {
    foreach ($whatsappLogRetryCsrfFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, '[FAIL] WhatsApp log-retry CSRF/RBAC smoke: ' . count($whatsappLogRetryCsrfFailures) . ' failure(s), ' . $whatsappLogRetryCsrfChecks . ' checks.' . PHP_EOL);
    exit(1);
}

echo '[PASS] WhatsApp log-retry CSRF/RBAC smoke: ' . $whatsappLogRetryCsrfChecks . ' checks; POST/header transport, least-privilege retry actions, callers, and side-effect boundary verified without DB/network/bootstrap.' . PHP_EOL;
