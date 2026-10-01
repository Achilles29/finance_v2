<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke test for POST /pos/orders/runtime-jobs/trigger/{orderId}.
 *
 * The real Pos controller is loaded with small in-memory fakes. The fake DB only
 * returns a prepared job context; it never connects to or mutates a database.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosRuntimeJobTriggerSmokeInput
{
    public array $events = [];

    private string $requestMethod;
    private array $headers;
    private string $rawInput;

    public function __construct(string $requestMethod, array $headers = [], array $payload = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->headers = [];
        foreach ($headers as $key => $value) {
            $this->headers[self::normalizeCgiHeaderName((string)$key)] = $value;
        }
        $this->rawInput = (string)json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private static function normalizeCgiHeaderName(string $name): string
    {
        $name = str_replace(['_', '-'], ' ', strtolower($name));
        return str_replace(' ', '-', ucwords($name));
    }

    public function method(bool $upper = false): string
    {
        $this->events[] = 'method';
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get_request_header(string $name, bool $xssClean = false): string
    {
        $name = (string)$name;
        $this->events[] = 'header:' . $name;
        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return (string)$value;
            }
        }
        return '';
    }

    public function __get(string $name)
    {
        $this->events[] = 'property:' . $name;
        if ($name === 'raw_input_stream') {
            return $this->rawInput;
        }
        throw new RuntimeException('unexpected input property access: ' . $name);
    }
}

final class PosRuntimeJobTriggerSmokeSession
{
    public array $reads = [];

    public function __construct(private array $values)
    {
    }

    public function userdata(string $key)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }
}

final class PosRuntimeJobTriggerSmokeOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function set_content_type(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function set_output(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}

final class PosRuntimeJobTriggerSmokeDbResult
{
    public function __construct(private array $row)
    {
    }

    public function row_array(): array
    {
        return $this->row;
    }
}

final class PosRuntimeJobTriggerSmokeDb
{
    public int $tableChecks = 0;
    public int $queryCalls = 0;
    public array $lastParams = [];

    public function __construct(private ?array $job)
    {
    }

    public function table_exists(string $table): bool
    {
        $this->tableChecks++;
        return in_array($table, ['pos_runtime_job', 'pos_order', 'pos_stock_commit'], true);
    }

    public function query(string $sql, array $params = []): PosRuntimeJobTriggerSmokeDbResult
    {
        $this->queryCalls++;
        $this->lastParams = $params;
        return new PosRuntimeJobTriggerSmokeDbResult($this->job ?? []);
    }
}

final class PosRuntimeJobTriggerSmokeLoader
{
    public array $libraryCalls = [];

    public function library(string $name, $params = null, $objectName = null): void
    {
        $this->libraryCalls[] = [$name, $objectName];
    }
}

final class PosRuntimeJobTriggerSmokeService
{
    public array $calls = [];
    public array $arguments = [];

    public function process_pending_jobs(array $options): array
    {
        $this->calls[] = 'process_pending_jobs';
        $this->arguments[] = $options;
        throw new RuntimeException('process_pending_jobs reached');
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public $load;
    public $posruntimejobservice;
    public array $events = [];
    public array $permissionCalls = [];

    public function __construct()
    {
    }

    public function can($page, $action = 'view'): bool
    {
        $this->events[] = 'can';
        return true;
    }

    public function require_permission($page, $action = 'view'): void
    {
        $this->events[] = 'permission';
        $this->permissionCalls[] = [(string)$page, (string)$action];
    }

    private function json_error(string $message, int $status = 422, array $extra = []): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output((string)json_encode(['ok' => false, 'message' => $message] + $extra));
    }

    private function json_ok(array $payload = [], string $message = ''): void
    {
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output((string)json_encode(['ok' => true] + $payload));
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];
$sessionKey = 'pos_transaction_csrf';
$browserHeader = 'X-Pos-Transaction-CSRF';
$canonicalHeader = 'X-Pos-Transaction-Csrf';
$validToken = str_repeat('a', 64);

function pos_runtime_job_trigger_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_runtime_job_trigger_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_runtime_job_trigger_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_runtime_job_trigger_method_block(string $source, string $method): string
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

function pos_runtime_job_trigger_job(array $overrides = []): array
{
    return array_merge([
        'id' => 701,
        'job_type' => 'ORDER_CONFIRM_STOCK_COMMIT',
        'status' => 'QUEUED',
        'order_id' => 501,
        'snapshot_id' => 801,
        'attempts' => 0,
        'max_attempts' => 3,
        'started_at' => null,
        'order_status' => 'CONFIRMED',
        'stock_commit_status' => 'QUEUED',
        'stock_committed_at' => null,
        'outlet_id' => 9,
        'snapshot_order_id' => 501,
        'snapshot_commit_status' => 'PENDING',
        'commit_no' => 'COMMIT-SMOKE',
        'linked_order_id' => 501,
    ], $overrides);
}

function pos_runtime_job_trigger_controller(
    string $requestMethod,
    array $headers,
    array $payload,
    ?array $job = null,
    array $sessionValues = []
): array {
    $input = new PosRuntimeJobTriggerSmokeInput($requestMethod, $headers, $payload);
    $session = new PosRuntimeJobTriggerSmokeSession($sessionValues);
    $output = new PosRuntimeJobTriggerSmokeOutput();
    $db = new PosRuntimeJobTriggerSmokeDb($job);
    $service = new PosRuntimeJobTriggerSmokeService();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = $db;
    $controller->load = new PosRuntimeJobTriggerSmokeLoader();
    $controller->posruntimejobservice = $service;

    return [$controller, $input, $session, $output, $db, $service, $reflection];
}

function pos_runtime_job_trigger_invoke(
    string $requestMethod,
    array $headers,
    array $payload,
    ?array $job = null,
    array $sessionValues = [],
    string $routeId = '501'
): array {
    [$controller, $input, $session, $output, $db, $service, $reflection] = pos_runtime_job_trigger_controller(
        $requestMethod,
        $headers,
        $payload,
        $job,
        $sessionValues
    );
    $exception = null;
    try {
        $reflection->getMethod('order_runtime_job_trigger')->invoke($controller, $routeId);
    } catch (Throwable $caught) {
        $exception = $caught;
    }
    return [$controller, $input, $session, $output, $db, $service, $exception];
}

function pos_runtime_job_trigger_assert_rejected(
    array $result,
    int $status,
    string $label,
    bool $expectDbQuery = false
): void {
    [$controller, $input, $session, $output, $db, $service, $exception] = $result;
    pos_runtime_job_trigger_check($exception === null, $label . ' completes without exception');
    pos_runtime_job_trigger_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_job_trigger_check($output->contentType === 'application/json', $label . ' returns JSON');
    pos_runtime_job_trigger_check($service->calls === [], $label . ' does not call the runtime service writer');
    pos_runtime_job_trigger_check(
        $expectDbQuery ? $db->queryCalls === 1 : $db->queryCalls === 0,
        $label . ($expectDbQuery ? ' performs only its binding read' : ' performs no binding read')
    );
    pos_runtime_job_trigger_check(
        $controller->permissionCalls === [['pos.cashier.index', 'edit']],
        $label . ' runs after the existing edit RBAC check'
    );
}

$root = dirname(__DIR__, 2);
$controllerSource = pos_runtime_job_trigger_source($root . '/application/controllers/Pos.php');
$targetBlock = pos_runtime_job_trigger_method_block($controllerSource, 'order_runtime_job_trigger');
$routeSource = pos_runtime_job_trigger_source($root . '/application/config/routes.php');

pos_runtime_job_trigger_check(
    strpos($routeSource, "\$route['pos/orders/runtime-jobs/trigger/(:num)'] = 'pos/order_runtime_job_trigger/\$1';") !== false,
    'target route remains scoped to a numeric order ID'
);
pos_runtime_job_trigger_check(
    $targetBlock !== ''
        && strpos($targetBlock, "require_permission(\$pageCode, 'edit')") < strpos($targetBlock, 'require_pos_transaction_csrf()')
        && strpos($targetBlock, 'require_pos_transaction_csrf()') < strpos($targetBlock, 'request_payload()')
        && strpos($targetBlock, 'request_payload()') < strpos($targetBlock, 'process_pending_jobs('),
    'controller order is RBAC, scoped POST/CSRF, body/binding validation, then processing'
);
pos_runtime_job_trigger_check(
    strpos($targetBlock, "'order_id' => \$orderId") !== false
        && strpos($targetBlock, "'job_id' => \$jobId") !== false
        && substr_count($targetBlock, 'process_pending_jobs(') === 1,
    'valid requests call process_pending_jobs once with both resource IDs'
);
$guardStart = strpos($controllerSource, 'private function require_pos_transaction_csrf(): bool');
$helperStart = strpos($controllerSource, 'private function pos_transaction_csrf(): string');
$rejectStart = strpos($controllerSource, 'private function reject_pos_transaction_csrf(');
$guardSource = $guardStart === false ? '' : substr($controllerSource, $guardStart, ($rejectStart === false ? strlen($controllerSource) : $rejectStart) - $guardStart);
$helperSource = $helperStart === false ? '' : substr($controllerSource, $helperStart, ($guardStart === false ? strlen($controllerSource) : $guardStart) - $helperStart);
pos_runtime_job_trigger_check(
    strpos($guardSource, 'POS_TRANSACTION_CSRF_CI_HEADER') !== false
        && strpos($guardSource, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardSource, '$this->input->get(') === false
        && strpos($guardSource, '$this->input->post(') === false,
    'target uses the existing canonical, session-bound scoped CSRF guard without query/body token fallback'
);
pos_runtime_job_trigger_check(
    strpos($helperSource, 'userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY)') !== false
        && strpos($helperSource, 'set_userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY, $token)') !== false,
    'target uses the existing POS transaction token helper'
);

$viewNames = [
    'cashier_index',
    'order_draft_index',
    'reservation_index',
    'self_order_orders',
    'online_food_orders',
    'stock_live_index',
];
foreach ($viewNames as $viewName) {
    $view = pos_runtime_job_trigger_source($root . '/application/views/pos/' . $viewName . '.php');
    $triggerPos = strpos($view, 'runtime-jobs/trigger');
    $wrapperPos = strpos($view, 'postPosTransactionJson(');
    $triggerCallMarker = $viewName === 'reservation_index'
        ? 'postPosTransactionJson(`${urls.orderTrigger}'
        : ($viewName === 'stock_live_index'
            ? "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>/"
            : "postPosTransactionJson(`<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>");
    $triggerCall = strpos($view, $triggerCallMarker);
    pos_runtime_job_trigger_check(
        strpos($view, 'pos_transaction_csrf_token') !== false,
        $viewName . ' renders a scoped transaction token'
    );
    pos_runtime_job_trigger_check(
        $triggerPos !== false
            && $wrapperPos !== false
            && $triggerCall !== false
            && strpos($view, 'postJson(`<?php echo site_url(\'pos/orders/runtime-jobs/trigger\'); ?>') === false
            && strpos($view, 'postJson(\'<?php echo site_url(\'pos/orders/runtime-jobs/trigger\'); ?>') === false,
        $viewName . ' sends the trigger through its scoped wrapper'
    );
    $wrapperStart = strpos($view, 'function postPosTransactionJson(');
    $wrapperSource = $wrapperStart === false ? '' : substr($view, $wrapperStart, 1800);
    pos_runtime_job_trigger_check(
        strpos($wrapperSource, 'X-Pos-Transaction-CSRF') !== false,
        $viewName . ' scoped wrapper carries X-Pos-Transaction-CSRF'
    );
}

$validSession = [$sessionKey => $validToken];
$validBrowserHeaders = [$browserHeader => $validToken];
$validCanonicalHeaders = [$canonicalHeader => $validToken];

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    pos_runtime_job_trigger_assert_rejected(
        pos_runtime_job_trigger_invoke($method, $validBrowserHeaders, ['job_id' => 701], pos_runtime_job_trigger_job(), $validSession),
        405,
        $method . ' is rejected before header/session/body/DB/service work'
    );
}

$invalidCsrfCases = [
    'missing header' => [[], $validSession, ['method', 'header:' . $canonicalHeader]],
    'empty header' => [[$browserHeader => ''], $validSession, ['method', 'header:' . $canonicalHeader]],
    'malformed header' => [[$browserHeader => 'bad'], $validSession, ['method', 'header:' . $canonicalHeader]],
    'missing session' => [$validBrowserHeaders, [], ['method', 'header:' . $canonicalHeader]],
    'malformed session' => [$validBrowserHeaders, [$sessionKey => 'bad'], ['method', 'header:' . $canonicalHeader]],
    'mismatched token' => [[$browserHeader => str_repeat('b', 64)], $validSession, ['method', 'header:' . $canonicalHeader]],
];
foreach ($invalidCsrfCases as $label => [$headers, $sessionValues, $expectedInputEvents]) {
    $result = pos_runtime_job_trigger_invoke('POST', $headers, ['job_id' => 701], pos_runtime_job_trigger_job(), $sessionValues);
    pos_runtime_job_trigger_assert_rejected($result, 403, 'POST ' . $label);
    pos_runtime_job_trigger_check($result[1]->events === $expectedInputEvents, 'POST ' . $label . ' reads only method and canonical header');
    pos_runtime_job_trigger_check(
        $result[2]->reads === ($label === 'missing header' || $label === 'empty header' || $label === 'malformed header' ? [] : [$sessionKey]),
        'POST ' . $label . ' reads session only for a valid-looking header'
    );
}

foreach (['0', '-1', '1.5', '1abc', '999999999999999999999'] as $badOrderId) {
    pos_runtime_job_trigger_assert_rejected(
        pos_runtime_job_trigger_invoke('POST', $validBrowserHeaders, ['job_id' => 701], pos_runtime_job_trigger_job(), $validSession, $badOrderId),
        422,
        'invalid route order ID ' . $badOrderId
    );
}

foreach ([[], ['job_id' => 0], ['job_id' => -1], ['job_id' => 1.5], ['job_id' => '1abc']] as $payload) {
    pos_runtime_job_trigger_assert_rejected(
        pos_runtime_job_trigger_invoke('POST', $validBrowserHeaders, $payload, pos_runtime_job_trigger_job(), $validSession),
        422,
        'invalid body job ID'
    );
}

foreach (
    [
        'job-order mismatch' => ['order_id' => 502],
        'wrong job type' => ['job_type' => 'OTHER_RUNTIME_JOB'],
        'terminal SUCCESS' => ['status' => 'SUCCESS'],
        'terminal CANCELLED' => ['status' => 'CANCELLED'],
        'unknown status' => ['status' => 'UNKNOWN'],
        'failed attempts exhausted' => ['status' => 'FAILED', 'attempts' => 3, 'max_attempts' => 3],
        'fresh processing' => ['status' => 'PROCESSING', 'started_at' => date('Y-m-d H:i:s')],
        'terminal order' => ['order_status' => 'VOID'],
        'reversed order stock' => ['stock_commit_status' => 'REVERSED'],
        'terminal snapshot' => ['snapshot_commit_status' => 'VOID'],
        'snapshot outside order scope' => ['snapshot_order_id' => 502],
    ] as $label => $overrides
) {
    pos_runtime_job_trigger_assert_rejected(
        pos_runtime_job_trigger_invoke('POST', $validBrowserHeaders, ['job_id' => 701], pos_runtime_job_trigger_job($overrides), $validSession),
        422,
        $label,
        true
    );
}

foreach (
    [
        'scope key' => ['job_id' => 701, 'scope' => 'ALL'],
        'mismatched body order scope' => ['job_id' => 701, 'order_id' => 502],
    ] as $label => $payload
) {
    pos_runtime_job_trigger_assert_rejected(
        pos_runtime_job_trigger_invoke('POST', $validBrowserHeaders, $payload, pos_runtime_job_trigger_job(), $validSession),
        422,
        $label
    );
}

foreach (
    [
        'browser header' => $validBrowserHeaders,
        'canonical CI header' => $validCanonicalHeaders,
    ] as $label => $headers
) {
    $result = pos_runtime_job_trigger_invoke(
        'POST',
        $headers,
        ['job_id' => 701, 'limit' => 99],
        pos_runtime_job_trigger_job(),
        $validSession
    );
    [$controller, $input, $session, $output, $db, $service, $exception] = $result;
    pos_runtime_job_trigger_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'process_pending_jobs reached',
        'valid ' . $label . ' reaches process_pending_jobs before response'
    );
    pos_runtime_job_trigger_check($service->calls === ['process_pending_jobs'], 'valid ' . $label . ' calls process_pending_jobs exactly once');
    pos_runtime_job_trigger_check(
        $service->arguments === [['limit' => 5, 'order_id' => 501, 'job_id' => 701]],
        'valid ' . $label . ' passes the bounded order/job/limit scope'
    );
    pos_runtime_job_trigger_check($db->lastParams === [701], 'valid ' . $label . ' binds the body job ID in the read-only lookup');
    pos_runtime_job_trigger_check(
        $input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream'],
        'valid ' . $label . ' parses body only after canonical scoped CSRF'
    );
    pos_runtime_job_trigger_check($session->reads === [$sessionKey], 'valid ' . $label . ' stays session-bound');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS runtime-job trigger smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS runtime-job trigger CSRF/binding checks\n";
