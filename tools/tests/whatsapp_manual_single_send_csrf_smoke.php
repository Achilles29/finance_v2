<?php

declare(strict_types=1);

/**
 * DB/network/bootstrap/secret-free behavior smoke for Whatsapp::manual()
 * single-send CSRF.
 *
 * The production controller source is evaluated under a test-only class name.
 * Only selected private seams are made protected so in-memory doubles can
 * prove ordering and outbound behavior without opening uploads, DB, or Bot API.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_MANUAL_SINGLE_CSRF_FIELD = 'wa_manual_single_send_csrf';
const WA_MANUAL_SINGLE_CSRF_SESSION = 'wa_manual_single_send_csrf';
const WA_MANUAL_SINGLE_CSRF_VALID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WA_MANUAL_SINGLE_CSRF_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const WA_MANUAL_SINGLE_CSRF_BROADCAST = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const WA_MANUAL_SINGLE_CSRF_LOG = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
const WA_MANUAL_SINGLE_CSRF_SETTINGS = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
const WA_MANUAL_SINGLE_CSRF_ENV = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

final class WhatsappManualSingleCsrfTrace
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

final class WhatsappManualSingleCsrfDenied extends RuntimeException
{
}

final class WhatsappManualSingleCsrfInput
{
    public array $postReads = [];
    public array $getReads = [];

    private string $requestMethod;
    private array $postData;
    private array $getData;

    public function __construct(string $requestMethod, array $postData = [], array $getData = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
        $this->getData = $getData;
    }

    public function method($upper = false): string
    {
        WhatsappManualSingleCsrfTrace::add('input:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $key = (string)$key;
        $this->postReads[] = $key;
        WhatsappManualSingleCsrfTrace::add('input:post:' . $key);
        return $this->postData[$key] ?? null;
    }

    public function get($key = null, $xssClean = false)
    {
        $key = (string)$key;
        $this->getReads[] = $key;
        WhatsappManualSingleCsrfTrace::add('input:get:' . $key);
        return $this->getData[$key] ?? '';
    }
}

final class WhatsappManualSingleCsrfSession
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
        WhatsappManualSingleCsrfTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->values[$key] = $value;
        $this->writes[$key] = (string)$value;
        WhatsappManualSingleCsrfTrace::add('session:write:' . $key);
    }

    public function set_flashdata($key, $value): void
    {
        $this->flash[(string)$key] = (string)$value;
        WhatsappManualSingleCsrfTrace::add('session:flash:' . (string)$key);
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

final class WhatsappManualSingleCsrfOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        WhatsappManualSingleCsrfTrace::add('output:status:' . $this->status);
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

final class WhatsappManualSingleCsrfDb
{
    public array $events = [];

    public function __call($method, $arguments): self
    {
        $event = 'db:' . (string)$method;
        $this->events[] = $event;
        WhatsappManualSingleCsrfTrace::add($event);
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
        return [];
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
    public ?string $renderedView = null;
    public array $renderedData = [];
    protected array $current_user = ['id' => 501];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->permissionCalls[] = [$page, $action];
        WhatsappManualSingleCsrfTrace::add('rbac:' . $page . ':' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappManualSingleCsrfDenied('permission denied', 403);
        }
    }

    public function can($page, $action = 'view'): bool
    {
        WhatsappManualSingleCsrfTrace::add('can:' . (string)$page . ':' . (string)$action);
        return !empty($this->permissions[(string)$page][(string)$action]);
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
        WhatsappManualSingleCsrfTrace::add('render:' . (string)$view);
    }
}

$whatsappManualSingleRedirect = '';
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $whatsappManualSingleRedirect;
    $whatsappManualSingleRedirect = (string)$uri;
    WhatsappManualSingleCsrfTrace::add('redirect:' . (string)$uri);
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
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$whatsappManualSingleControllerPath = dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';
$whatsappManualSingleControllerSource = (string)file_get_contents($whatsappManualSingleControllerPath);
$whatsappManualSingleHarnessSource = str_replace(
    'class Whatsapp extends MY_Controller',
    'class WhatsappManualSingleCsrfHarnessBase extends MY_Controller',
    $whatsappManualSingleControllerSource
);
foreach (
    [
        'personalOutboundEnabled',
        'createManualBulkQueue',
        'manualTargetsFromInput',
        'callBotApi',
        'logSend',
        'handleWaImageUpload',
    ] as $whatsappManualSingleSeam
) {
    $whatsappManualSingleHarnessSource = str_replace(
        'private function ' . $whatsappManualSingleSeam . '(',
        'protected function ' . $whatsappManualSingleSeam . '(',
        $whatsappManualSingleHarnessSource
    );
}
eval(substr($whatsappManualSingleHarnessSource, 5));

final class WhatsappManualSingleCsrfHarness extends WhatsappManualSingleCsrfHarnessBase
{
    public int $breakerCalls = 0;
    public int $uploadCalls = 0;
    public int $parseCalls = 0;
    public int $senderCalls = 0;
    public int $logCalls = 0;
    public int $bulkCalls = 0;
    public array $lastSenderPayload = [];

    protected function personalOutboundEnabled(): bool
    {
        $this->breakerCalls++;
        WhatsappManualSingleCsrfTrace::add('business:breaker');
        return true;
    }

    protected function createManualBulkQueue(): void
    {
        $this->bulkCalls++;
        WhatsappManualSingleCsrfTrace::add('business:bulk');
    }

    protected function manualTargetsFromInput(string $manualLines, string $memberIdsRaw): array
    {
        $this->parseCalls++;
        WhatsappManualSingleCsrfTrace::add('business:parse');
        return [['phone' => '628111111111', 'name' => 'Synthetic Recipient']];
    }

    protected function callBotApi(string $endpoint, string $method = 'GET', array $payload = [], int $timeout = 8): array
    {
        $this->senderCalls++;
        $this->lastSenderPayload = $payload;
        WhatsappManualSingleCsrfTrace::add('business:sender');
        return ['ok' => true];
    }

    protected function logSend(?int $broadcastId, string $source, ?string $phone, ?string $groupJid, ?string $name, string $message, string $status, string $error = ''): void
    {
        $this->logCalls++;
        WhatsappManualSingleCsrfTrace::add('business:log');
    }

    protected function handleWaImageUpload(string $field): array
    {
        $this->uploadCalls++;
        WhatsappManualSingleCsrfTrace::add('business:upload');
        return ['path' => null, 'url' => null, 'mime' => null, 'name' => null];
    }
}

$whatsappManualSingleChecks = 0;
$whatsappManualSingleFailures = [];

function whatsapp_manual_single_check(bool $condition, string $message): void
{
    global $whatsappManualSingleChecks, $whatsappManualSingleFailures;
    $whatsappManualSingleChecks++;
    if (!$condition) {
        $whatsappManualSingleFailures[] = $message;
    }
}

function whatsapp_manual_single_method_source(string $source, string $method): string
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

function whatsapp_manual_single_fixture(
    string $method,
    array $post = [],
    array $permissions = [],
    array $sessionValues = [],
    array $get = []
): array {
    WhatsappManualSingleCsrfTrace::reset();
    global $whatsappManualSingleRedirect;
    $whatsappManualSingleRedirect = '';

    $reflection = new ReflectionClass(WhatsappManualSingleCsrfHarness::class);
    /** @var WhatsappManualSingleCsrfHarness $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WhatsappManualSingleCsrfInput($method, $post, $get);
    $controller->session = new WhatsappManualSingleCsrfSession($sessionValues);
    $controller->output = new WhatsappManualSingleCsrfOutput();
    $controller->db = new WhatsappManualSingleCsrfDb();
    $controller->permissions = $permissions;

    return ['controller' => $controller, 'reflection' => $reflection];
}

function whatsapp_manual_single_rejection(array $fixture, int $status, string $label): void
{
    $controller = $fixture['controller'];
    $decoded = json_decode($controller->output->body, true);
    whatsapp_manual_single_check($controller->output->status === $status, $label . ' status ' . $status);
    whatsapp_manual_single_check(
        $controller->output->contentType === 'application/json'
            && is_array($decoded)
            && ($decoded['ok'] ?? null) === false,
        $label . ' returns structured rejection'
    );
    whatsapp_manual_single_check(
        $controller->breakerCalls === 0
            && $controller->uploadCalls === 0
            && $controller->parseCalls === 0
            && $controller->senderCalls === 0
            && $controller->logCalls === 0
            && $controller->bulkCalls === 0,
        $label . ' stops before breaker/upload/parsing/outbound/log'
    );
    whatsapp_manual_single_check($controller->db->events === [], $label . ' reaches no DB dependency');
}

$manualBlock = whatsapp_manual_single_method_source($whatsappManualSingleControllerSource, 'manual');
$mintBlock = whatsapp_manual_single_method_source($whatsappManualSingleControllerSource, 'wa_manual_single_send_csrf');
$guardBlock = whatsapp_manual_single_method_source($whatsappManualSingleControllerSource, 'require_wa_manual_single_send_csrf');

whatsapp_manual_single_check($manualBlock !== '' && $mintBlock !== '' && $guardBlock !== '', 'single-send controller methods exist');
whatsapp_manual_single_check(
    strpos($whatsappManualSingleControllerSource, "WA_MANUAL_SINGLE_SEND_CSRF_SESSION_KEY = 'wa_manual_single_send_csrf'") !== false
        && strpos($whatsappManualSingleControllerSource, "WA_MANUAL_SINGLE_SEND_CSRF_FORM_FIELD = 'wa_manual_single_send_csrf'") !== false,
    'session key and form field use the dedicated scope'
);
whatsapp_manual_single_check(
    strpos($mintBlock, 'random_bytes(32)') !== false
        && strpos($mintBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'mint uses 32 random bytes and strict lowercase 64-hex format'
);
whatsapp_manual_single_check(
    strpos($guardBlock, 'hash_equals') !== false
        && strpos($guardBlock, 'random_bytes') === false,
    'guard uses constant-time equality and never mints'
);

$createPosition = strpos($manualBlock, "require_permission(self::PAGE_MANUAL, 'create')");
$modePosition = strpos($manualBlock, "post('delivery_mode', true)");
$singleGuardPosition = strpos($manualBlock, 'require_wa_manual_single_send_csrf');
$breakerPosition = strpos($manualBlock, 'personalOutboundEnabled');
$uploadPosition = strpos($manualBlock, 'handleWaImageUpload');
$parsePosition = strpos($manualBlock, 'manualTargetsFromInput');
$senderPosition = strpos($manualBlock, 'callBotApi');
$logPosition = strpos($manualBlock, 'logSend');
whatsapp_manual_single_check(
    $createPosition !== false
        && $modePosition !== false
        && $singleGuardPosition !== false
        && $createPosition < $modePosition
        && $modePosition < $singleGuardPosition,
    'create RBAC and delivery mode selection precede the single guard'
);
whatsapp_manual_single_check(
    $singleGuardPosition < $breakerPosition
        && $singleGuardPosition < $uploadPosition
        && $singleGuardPosition < $parsePosition
        && $singleGuardPosition < $senderPosition
        && $singleGuardPosition < $logPosition,
    'single guard precedes every personal-send side effect'
);
whatsapp_manual_single_check(
    strpos($manualBlock, 'require_wa_broadcast_mutation_csrf') !== false,
    'bulk branch retains the broadcast CSRF guard'
);

$writerPermissions = ['wa.manual' => ['view' => true, 'create' => true]];
$viewPermissions = ['wa.manual' => ['view' => true]];

$viewOnlyGet = whatsapp_manual_single_fixture('GET', [], $viewPermissions, [], ['tab' => 'single']);
$viewOnlyGet['controller']->manual();
whatsapp_manual_single_check(
    !array_key_exists(WA_MANUAL_SINGLE_CSRF_FIELD, $viewOnlyGet['controller']->renderedData),
    'view-only single GET receives no token'
);
whatsapp_manual_single_check(
    !array_key_exists(WA_MANUAL_SINGLE_CSRF_SESSION, $viewOnlyGet['controller']->session->writes),
    'view-only single GET does not mint the token'
);

$editorGet = whatsapp_manual_single_fixture('GET', [], $writerPermissions, [], ['tab' => 'single']);
$editorGet['controller']->manual();
$editorToken = (string)($editorGet['controller']->renderedData[WA_MANUAL_SINGLE_CSRF_FIELD] ?? '');
whatsapp_manual_single_check(
    preg_match('/\A[0-9a-f]{64}\z/D', $editorToken) === 1
        && $editorGet['controller']->session->value(WA_MANUAL_SINGLE_CSRF_SESSION) === $editorToken,
    'editor single GET mints and renders a lowercase 64-hex token'
);
whatsapp_manual_single_check(
    $editorToken !== (string)($editorGet['controller']->renderedData['wa_log_retry_csrf'] ?? ''),
    'single-send token is distinct from the log-retry token'
);

$invalidCases = [
    'missing token' => '',
    'malformed uppercase token' => str_repeat('A', 64),
    'mismatched token' => WA_MANUAL_SINGLE_CSRF_OTHER,
    'broadcast token' => WA_MANUAL_SINGLE_CSRF_BROADCAST,
    'log token' => WA_MANUAL_SINGLE_CSRF_LOG,
    'settings token' => WA_MANUAL_SINGLE_CSRF_SETTINGS,
    'env token' => WA_MANUAL_SINGLE_CSRF_ENV,
];
foreach ($invalidCases as $label => $providedToken) {
    $fixture = whatsapp_manual_single_fixture(
        'POST',
        [
            'delivery_mode' => 'single',
            WA_MANUAL_SINGLE_CSRF_FIELD => $providedToken,
            'message' => 'must-not-be-read',
            'manual_numbers' => 'must-not-be-read',
            'selected_member_ids' => 'must-not-be-read',
        ],
        $writerPermissions,
        [
            WA_MANUAL_SINGLE_CSRF_SESSION => WA_MANUAL_SINGLE_CSRF_VALID,
            'wa_broadcast_mutation_csrf' => WA_MANUAL_SINGLE_CSRF_BROADCAST,
            'wa_log_retry_csrf' => WA_MANUAL_SINGLE_CSRF_LOG,
            'wa_settings_mutation_csrf' => WA_MANUAL_SINGLE_CSRF_SETTINGS,
            'wa_env_save_csrf' => WA_MANUAL_SINGLE_CSRF_ENV,
        ]
    );
    $fixture['controller']->manual();
    whatsapp_manual_single_rejection($fixture, 403, 'single POST ' . $label);
    whatsapp_manual_single_check(
        array_intersect(
            $fixture['controller']->input->postReads,
            ['message', 'manual_numbers', 'selected_member_ids', 'media_image']
        ) === [],
        'single POST ' . $label . ' reads no business payload'
    );
}

$malformedSession = whatsapp_manual_single_fixture(
    'POST',
    ['delivery_mode' => 'single', WA_MANUAL_SINGLE_CSRF_FIELD => WA_MANUAL_SINGLE_CSRF_VALID],
    $writerPermissions,
    [WA_MANUAL_SINGLE_CSRF_SESSION => 'short']
);
$malformedSession['controller']->manual();
whatsapp_manual_single_rejection($malformedSession, 403, 'single POST malformed session token');

$nonPost = whatsapp_manual_single_fixture(
    'PUT',
    ['delivery_mode' => 'single', WA_MANUAL_SINGLE_CSRF_FIELD => WA_MANUAL_SINGLE_CSRF_VALID],
    $writerPermissions,
    [WA_MANUAL_SINGLE_CSRF_SESSION => WA_MANUAL_SINGLE_CSRF_VALID]
);
$nonPost['controller']->manual();
whatsapp_manual_single_rejection($nonPost, 405, 'single non-POST');
whatsapp_manual_single_check(
    !in_array(WA_MANUAL_SINGLE_CSRF_FIELD, $nonPost['controller']->input->postReads, true),
    'single non-POST rejects before reading token body'
);

$validSingle = whatsapp_manual_single_fixture(
    'POST',
    [
        'delivery_mode' => 'single',
        WA_MANUAL_SINGLE_CSRF_FIELD => WA_MANUAL_SINGLE_CSRF_VALID,
        'message' => 'Synthetic message',
        'manual_numbers' => 'synthetic-target',
        'selected_member_ids' => '',
    ],
    $writerPermissions,
    [WA_MANUAL_SINGLE_CSRF_SESSION => WA_MANUAL_SINGLE_CSRF_VALID]
);
$validSingle['controller']->manual();
whatsapp_manual_single_check($validSingle['controller']->output->status === null, 'valid single token is not rejected');
whatsapp_manual_single_check(
    $validSingle['controller']->breakerCalls === 1
        && $validSingle['controller']->uploadCalls === 1
        && $validSingle['controller']->parseCalls === 1
        && $validSingle['controller']->senderCalls === 1
        && $validSingle['controller']->logCalls === 1,
    'valid single token reaches one mocked sender and one log write'
);
whatsapp_manual_single_check(
    ($validSingle['controller']->lastSenderPayload['to'] ?? '') === '628111111111',
    'valid single send uses the synthetic parsed target'
);
whatsapp_manual_single_check(
    $whatsappManualSingleRedirect === 'wa/manual',
    'valid single send preserves its redirect route'
);
whatsapp_manual_single_check($validSingle['controller']->db->events === [], 'valid mocked single send opens no DB dependency');

$validBulk = whatsapp_manual_single_fixture(
    'POST',
    [
        'delivery_mode' => 'bulk',
        'wa_broadcast_mutation_csrf' => WA_MANUAL_SINGLE_CSRF_BROADCAST,
        WA_MANUAL_SINGLE_CSRF_FIELD => WA_MANUAL_SINGLE_CSRF_VALID,
    ],
    $writerPermissions,
    ['wa_broadcast_mutation_csrf' => WA_MANUAL_SINGLE_CSRF_BROADCAST]
);
$validBulk['controller']->manual();
whatsapp_manual_single_check(
    $validBulk['controller']->bulkCalls === 1
        && $validBulk['controller']->senderCalls === 0
        && !in_array(WA_MANUAL_SINGLE_CSRF_SESSION, $validBulk['controller']->session->reads, true),
    'bulk still uses its broadcast token and bypasses the single token scope'
);

$bulkWithSingleToken = whatsapp_manual_single_fixture(
    'POST',
    [
        'delivery_mode' => 'bulk',
        'wa_broadcast_mutation_csrf' => WA_MANUAL_SINGLE_CSRF_VALID,
        WA_MANUAL_SINGLE_CSRF_FIELD => WA_MANUAL_SINGLE_CSRF_VALID,
    ],
    $writerPermissions,
    [
        'wa_broadcast_mutation_csrf' => WA_MANUAL_SINGLE_CSRF_BROADCAST,
        WA_MANUAL_SINGLE_CSRF_SESSION => WA_MANUAL_SINGLE_CSRF_VALID,
    ]
);
$bulkWithSingleToken['controller']->manual();
whatsapp_manual_single_rejection($bulkWithSingleToken, 403, 'bulk with single token');
whatsapp_manual_single_check(
    !in_array(WA_MANUAL_SINGLE_CSRF_SESSION, $bulkWithSingleToken['controller']->session->reads, true),
    'bulk cannot validate against the single-send session token'
);

$manualViewPath = dirname(__DIR__, 2) . '/application/views/wa/manual.php';
$manualViewSource = (string)file_get_contents($manualViewPath);
$singleFormStart = strpos($manualViewSource, 'id="waManualForm"');
$singleFormEnd = $singleFormStart === false ? false : strpos($manualViewSource, '</form>', $singleFormStart);
$bulkFormStart = strpos($manualViewSource, 'id="waBulkForm"');
$bulkFormEnd = $bulkFormStart === false ? false : strpos($manualViewSource, '</form>', $bulkFormStart);
$singleFormSource = $singleFormStart !== false && $singleFormEnd !== false
    ? substr($manualViewSource, $singleFormStart, $singleFormEnd - $singleFormStart)
    : '';
$bulkFormSource = $bulkFormStart !== false && $bulkFormEnd !== false
    ? substr($manualViewSource, $bulkFormStart, $bulkFormEnd - $bulkFormStart)
    : '';
whatsapp_manual_single_check(
    substr_count($singleFormSource, 'name="wa_manual_single_send_csrf"') === 1,
    'waManualForm contains exactly one scoped hidden token'
);
whatsapp_manual_single_check(
    strpos($singleFormSource, 'name="wa_broadcast_mutation_csrf"') === false,
    'waManualForm does not reuse the broadcast token'
);
whatsapp_manual_single_check(
    strpos($bulkFormSource, 'name="wa_broadcast_mutation_csrf"') !== false
        && strpos($bulkFormSource, 'name="wa_manual_single_send_csrf"') === false,
    'waBulkForm remains exclusively on the broadcast token'
);

if ($whatsappManualSingleFailures !== []) {
    foreach ($whatsappManualSingleFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(
        STDERR,
        '[FAIL] WhatsApp manual single-send CSRF smoke: '
        . count($whatsappManualSingleFailures) . ' failure(s), '
        . $whatsappManualSingleChecks . ' checks.' . PHP_EOL
    );
    exit(1);
}

echo '[PASS] WhatsApp manual single-send CSRF smoke: '
    . $whatsappManualSingleChecks
    . ' checks; scoped GET/POST, early rejection, mocked outbound, and bulk isolation verified.'
    . PHP_EOL;
