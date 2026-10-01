<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke for
 * POST /pos/orders/runtime-snapshots/retry/{snapshotId}.
 *
 * The actual Pos controller is loaded without its constructor. In-memory
 * fakes/tripwires prove RBAC, scoped CSRF, canonical route-ID parsing, the
 * existing snapshot/order state policy, and the legacy retry writer sequence.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosRuntimeFailedSnapshotRetrySmokeTrace
{
    public array $events = [];
}

final class PosRuntimeFailedSnapshotRetrySmokeInput
{
    public array $events = [];

    private string $requestMethod;
    private array $headers = [];

    public function __construct(string $requestMethod, array $headers = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        foreach ($headers as $name => $value) {
            $this->headers[self::normalizeCgiHeaderName((string)$name)] = (string)$value;
        }
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
        $this->events[] = 'header:' . $name;
        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return $value;
            }
        }
        return '';
    }

    public function __get(string $name)
    {
        $this->events[] = 'property:' . $name;
        throw new RuntimeException('unexpected input property access: ' . $name);
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeSession
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

final class PosRuntimeFailedSnapshotRetrySmokeOutput
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

    public function _display(): void
    {
        throw new RuntimeException('response displayed');
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeActor implements ArrayAccess
{
    public array $reads = [];

    public function __construct(
        private PosRuntimeFailedSnapshotRetrySmokeTrace $trace,
        private int $employeeId = 4242,
        private bool $tripwire = false
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->reads[] = ['exists', $offset];
        $this->trace->events[] = 'actor:exists:' . (string)$offset;
        if ($this->tripwire) {
            throw new RuntimeException('actor tripwire reached: ' . (string)$offset);
        }
        return $offset === 'employee_id';
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->reads[] = ['get', $offset];
        $this->trace->events[] = 'actor:get:' . (string)$offset;
        if ($this->tripwire) {
            throw new RuntimeException('actor tripwire reached: ' . (string)$offset);
        }
        return $offset === 'employee_id' ? $this->employeeId : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('unexpected actor write');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('unexpected actor unset');
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeDb
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotRetrySmokeTrace $trace,
        private array $snapshot,
        private bool $tripwire = false
    ) {
    }

    private function record(string $method, array $arguments = []): void
    {
        $this->calls[] = [$method, $arguments];
        $this->trace->events[] = 'db:' . $method;
        if ($this->tripwire) {
            throw new RuntimeException('database tripwire reached: ' . $method);
        }
    }

    public function select(string $select): self
    {
        $this->record('select', [$select]);
        return $this;
    }

    public function from(string $table): self
    {
        $this->record('from', [$table]);
        return $this;
    }

    public function join(string $table, string $condition, string $type = ''): self
    {
        $this->record('join', [$table, $condition, $type]);
        return $this;
    }

    public function where(string $key, $value): self
    {
        $this->record('where', [$key, $value]);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->record('limit', [$limit]);
        return $this;
    }

    public function get(): self
    {
        $this->record('get');
        return $this;
    }

    public function row_array(): array
    {
        $this->record('row_array');
        return $this->snapshot;
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeLoader
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotRetrySmokeTrace $trace,
        private bool $tripwire = false
    ) {
    }

    public function library(string $name, $params = null, $objectName = null): void
    {
        $this->calls[] = [$name, $objectName];
        $this->trace->events[] = 'load:' . $name . ':' . ($objectName === null ? 'null' : (string)$objectName);
        if ($this->tripwire) {
            throw new RuntimeException('loader tripwire reached: ' . $name);
        }
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeStockService
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotRetrySmokeTrace $trace,
        private array $refreshResult,
        private array $markQueuedResult,
        private bool $tripwire = false
    ) {
    }

    public function refresh_snapshot_from_order(int $snapshotId, int $actorEmployeeId = 0, array $options = []): array
    {
        $this->calls[] = ['refresh_snapshot_from_order', [$snapshotId, $actorEmployeeId, $options]];
        $this->trace->events[] = 'stock:refresh_snapshot_from_order';
        if ($this->tripwire) {
            throw new RuntimeException('stock service tripwire reached: refresh_snapshot_from_order');
        }
        return $this->refreshResult;
    }

    public function mark_queued(int $snapshotId): array
    {
        $this->calls[] = ['mark_queued', [$snapshotId]];
        $this->trace->events[] = 'stock:mark_queued';
        if ($this->tripwire) {
            throw new RuntimeException('stock service tripwire reached: mark_queued');
        }
        return $this->markQueuedResult;
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeModel
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotRetrySmokeTrace $trace,
        private array $updateResult,
        private bool $tripwire = false
    ) {
    }

    public function update_order_stock_commit_state(int $orderId, string $status, array $context = []): array
    {
        $this->calls[] = ['update_order_stock_commit_state', [$orderId, $status, $context]];
        $this->trace->events[] = 'model:update_order_stock_commit_state';
        if ($this->tripwire) {
            throw new RuntimeException('model tripwire reached: update_order_stock_commit_state');
        }
        return $this->updateResult;
    }
}

final class PosRuntimeFailedSnapshotRetrySmokeRuntimeService
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotRetrySmokeTrace $trace,
        private array $queueResult,
        private array $processResult,
        private array $latestResult,
        private bool $tripwire = false
    ) {
    }

    public function queue_order_confirm_commit(int $orderId, int $snapshotId, int $actorEmployeeId = 0, array $meta = []): array
    {
        $this->calls[] = ['queue_order_confirm_commit', [$orderId, $snapshotId, $actorEmployeeId, $meta]];
        $this->trace->events[] = 'runtime:queue_order_confirm_commit';
        if ($this->tripwire) {
            throw new RuntimeException('runtime service tripwire reached: queue_order_confirm_commit');
        }
        return $this->queueResult;
    }

    public function process_pending_jobs(array $options = []): array
    {
        $this->calls[] = ['process_pending_jobs', [$options]];
        $this->trace->events[] = 'runtime:process_pending_jobs';
        if ($this->tripwire) {
            throw new RuntimeException('runtime service tripwire reached: process_pending_jobs');
        }
        return $this->processResult;
    }

    public function latest_job_for_order(int $orderId): array
    {
        $this->calls[] = ['latest_job_for_order', [$orderId]];
        $this->trace->events[] = 'runtime:latest_job_for_order';
        if ($this->tripwire) {
            throw new RuntimeException('runtime service tripwire reached: latest_job_for_order');
        }
        return $this->latestResult;
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public $load;
    public $Pos_model;
    public $posruntimejobservice;
    public $posstockcommitservice;
    public $current_user;
    public array $canCalls = [];
    public array $permissionCalls = [];
    public array $canResults = ['pos.stock.commit.audit.index:edit' => true];
    public bool $denyPermission = false;

    public function __construct()
    {
    }

    public function can($page, $action = 'view'): bool
    {
        $key = (string)$page . ':' . (string)$action;
        $this->canCalls[] = [(string)$page, (string)$action];
        return (bool)($this->canResults[$key] ?? false);
    }

    public function require_permission($page, $action = 'view'): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if ($this->denyPermission) {
            throw new RuntimeException('RBAC denied');
        }
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];
$root = dirname(__DIR__, 2);
$sessionKey = 'pos_transaction_csrf';
$browserHeader = 'X-Pos-Transaction-CSRF';
$canonicalHeader = 'X-Pos-Transaction-Csrf';
$validToken = str_repeat('a', 64);
$validSession = [$sessionKey => $validToken];
$validSnapshot = [
    'id' => 801,
    'commit_status' => 'FAILED',
    'order_id' => 501,
    'order_no' => 'ORDER-SNAPSHOT-SMOKE',
    'order_status' => 'CONFIRMED',
    'stock_commit_status' => 'FAILED',
];
$defaultProcessResult = [
    'ok' => true,
    'processed_count' => 1,
    'success_count' => 1,
    'failed_count' => 0,
    'jobs' => [['ok' => true, 'job' => ['id' => 901, 'status' => 'SUCCESS']]],
];
$defaultLatestResult = [
    'ok' => true,
    'job' => ['id' => 901, 'order_id' => 501, 'snapshot_id' => 801, 'status' => 'SUCCESS'],
];

function pos_runtime_failed_snapshot_retry_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_runtime_failed_snapshot_retry_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_runtime_failed_snapshot_retry_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_runtime_failed_snapshot_retry_method_block(string $source, string $method): string
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

function pos_runtime_failed_snapshot_retry_invoke(
    string $requestMethod,
    array $headers,
    array $sessionValues,
    $routeId,
    array $options = []
): array {
    global $validSnapshot, $defaultProcessResult, $defaultLatestResult;

    $trace = new PosRuntimeFailedSnapshotRetrySmokeTrace();
    $input = new PosRuntimeFailedSnapshotRetrySmokeInput($requestMethod, $headers);
    $session = new PosRuntimeFailedSnapshotRetrySmokeSession($sessionValues);
    $output = new PosRuntimeFailedSnapshotRetrySmokeOutput();
    $actor = new PosRuntimeFailedSnapshotRetrySmokeActor(
        $trace,
        (int)($options['actor_employee_id'] ?? 4242),
        (bool)($options['tripwire_actor'] ?? false)
    );
    $db = new PosRuntimeFailedSnapshotRetrySmokeDb(
        $trace,
        array_key_exists('snapshot', $options) ? (array)$options['snapshot'] : $validSnapshot,
        (bool)($options['tripwire_db'] ?? false)
    );
    $loader = new PosRuntimeFailedSnapshotRetrySmokeLoader(
        $trace,
        (bool)($options['tripwire_loader'] ?? false)
    );
    $stockService = new PosRuntimeFailedSnapshotRetrySmokeStockService(
        $trace,
        (array)($options['refresh_result'] ?? ['ok' => true, 'id' => 801, 'order_id' => 501, 'line_count' => 2]),
        (array)($options['mark_queued_result'] ?? ['ok' => true]),
        (bool)($options['tripwire_stock_service'] ?? false)
    );
    $model = new PosRuntimeFailedSnapshotRetrySmokeModel(
        $trace,
        (array)($options['update_result'] ?? ['ok' => true]),
        (bool)($options['tripwire_model'] ?? false)
    );
    $runtimeService = new PosRuntimeFailedSnapshotRetrySmokeRuntimeService(
        $trace,
        (array)($options['queue_result'] ?? ['ok' => true, 'job_id' => 901, 'status' => 'QUEUED']),
        (array)($options['process_result'] ?? $defaultProcessResult),
        (array)($options['latest_result'] ?? $defaultLatestResult),
        (bool)($options['tripwire_runtime_service'] ?? false)
    );

    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->current_user = $actor;
    $controller->db = $db;
    $controller->load = $loader;
    $controller->Pos_model = $model;
    $controller->posstockcommitservice = $stockService;
    $controller->posruntimejobservice = $runtimeService;
    $controller->canResults = (array)($options['can_results'] ?? ['pos.stock.commit.audit.index:edit' => true]);
    $controller->denyPermission = (bool)($options['deny_permission'] ?? false);

    $exception = null;
    try {
        $reflection->getMethod('order_runtime_failed_snapshot_retry')->invoke($controller, $routeId);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    return compact(
        'controller',
        'input',
        'session',
        'output',
        'actor',
        'db',
        'loader',
        'stockService',
        'model',
        'runtimeService',
        'trace',
        'exception'
    );
}

function pos_runtime_failed_snapshot_retry_assert_guard_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    pos_runtime_failed_snapshot_retry_check($result['exception'] === null, $label . ' completes at the scoped guard');
    pos_runtime_failed_snapshot_retry_check($result['output']->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_failed_snapshot_retry_check($result['output']->contentType === 'application/json', $label . ' returns JSON');
    pos_runtime_failed_snapshot_retry_check($result['input']->events === $expectedInputEvents, $label . ' uses only the expected input boundary');
    pos_runtime_failed_snapshot_retry_check($result['session']->reads === $expectedSessionReads, $label . ' uses only the expected session boundary');
    pos_runtime_failed_snapshot_retry_check($result['db']->calls === [], $label . ' performs no DB work');
    pos_runtime_failed_snapshot_retry_check($result['actor']->reads === [], $label . ' does not resolve the actor');
    pos_runtime_failed_snapshot_retry_check($result['loader']->calls === [], $label . ' does not load a service');
    pos_runtime_failed_snapshot_retry_check($result['stockService']->calls === [], $label . ' does not call the stock service');
    pos_runtime_failed_snapshot_retry_check($result['model']->calls === [], $label . ' does not call the order model');
    pos_runtime_failed_snapshot_retry_check($result['runtimeService']->calls === [], $label . ' does not call the runtime service');
}

function pos_runtime_failed_snapshot_retry_assert_display_response(array $result, int $status, string $label): void
{
    pos_runtime_failed_snapshot_retry_check(
        $result['exception'] instanceof RuntimeException
            && $result['exception']->getMessage() === 'response displayed',
        $label . ' reaches its JSON response without crossing a tripwire'
    );
    pos_runtime_failed_snapshot_retry_check($result['output']->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_failed_snapshot_retry_check($result['output']->contentType === 'application/json', $label . ' returns JSON');
}

function pos_runtime_failed_snapshot_retry_expected_lookup_calls(int $snapshotId): array
{
    return [
        ['select', ['s.*, o.order_no, o.status AS order_status, o.stock_commit_status']],
        ['from', ['pos_stock_commit s']],
        ['join', ['pos_order o', 'o.id = s.order_id', 'left']],
        ['where', ['s.id', $snapshotId]],
        ['limit', [1]],
        ['get', []],
        ['row_array', []],
    ];
}

function pos_runtime_failed_snapshot_retry_assert_before_writers(array $result, string $label): void
{
    pos_runtime_failed_snapshot_retry_check($result['actor']->reads === [], $label . ' does not resolve the actor');
    pos_runtime_failed_snapshot_retry_check($result['loader']->calls === [], $label . ' does not load services');
    pos_runtime_failed_snapshot_retry_check($result['stockService']->calls === [], $label . ' does not call stock writers');
    pos_runtime_failed_snapshot_retry_check($result['model']->calls === [], $label . ' does not update the order');
    pos_runtime_failed_snapshot_retry_check($result['runtimeService']->calls === [], $label . ' does not queue/process a job');
}

$controllerSource = pos_runtime_failed_snapshot_retry_source($root . '/application/controllers/Pos.php');
$viewSource = pos_runtime_failed_snapshot_retry_source($root . '/application/views/pos/stock_commit_audit_index.php');
$routeSource = pos_runtime_failed_snapshot_retry_source($root . '/application/config/routes.php');
$stockServiceSource = pos_runtime_failed_snapshot_retry_source($root . '/application/libraries/PosStockCommitService.php');
$runtimeServiceSource = pos_runtime_failed_snapshot_retry_source($root . '/application/libraries/PosRuntimeJobService.php');
$targetBlock = pos_runtime_failed_snapshot_retry_method_block($controllerSource, 'order_runtime_failed_snapshot_retry');

$orderedMarkers = [
    "require_permission(\$pageCode, 'edit')",
    'require_pos_transaction_csrf()',
    '$routeSnapshotId = $snapshotId;',
    '$snapshotId = $this->parse_positive_runtime_id($snapshotId);',
    '$snapshot = $this->db->select(',
    "\$snapshotStatus !== 'FAILED'",
    '$orderId <= 0',
    "\$orderStatus === 'VOID'",
    "in_array(\$orderCommitStatus, ['POSTED', 'REVERSED', 'NOT_REQUIRED'], true)",
    '$actorEmployeeId = $this->current_actor_employee_id();',
    "\$this->load->library('PosRuntimeJobService', null, 'posruntimejobservice')",
    "\$this->load->library('PosStockCommitService', null, 'posstockcommitservice')",
    '$this->posstockcommitservice->refresh_snapshot_from_order(',
    '$this->posstockcommitservice->mark_queued(',
    '$this->Pos_model->update_order_stock_commit_state(',
    '$this->posruntimejobservice->queue_order_confirm_commit(',
    '$this->process_runtime_job_now(',
    '$this->posruntimejobservice->latest_job_for_order(',
];
$lastPosition = -1;
$markersOrdered = $targetBlock !== '';
foreach ($orderedMarkers as $marker) {
    $position = strpos($targetBlock, $marker);
    if ($position === false || $position <= $lastPosition) {
        $markersOrdered = false;
        break;
    }
    $lastPosition = $position;
}
pos_runtime_failed_snapshot_retry_check($targetBlock !== '', 'target endpoint exists in the actual controller');
pos_runtime_failed_snapshot_retry_check(
    $markersOrdered,
    'target order is edit RBAC, scoped CSRF, canonical ID, lookup/state gates, actor/service load, then legacy writers'
);
pos_runtime_failed_snapshot_retry_check(
    substr_count($targetBlock, '$this->require_pos_transaction_csrf()') === 1
        && substr_count($targetBlock, '$this->parse_positive_runtime_id($snapshotId)') === 1
        && substr_count($targetBlock, '$this->current_actor_employee_id()') === 1,
    'target has one scoped guard, one strict parser, and one post-gate actor resolution'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($targetBlock, '(string)$snapshotId !== (string)$routeSnapshotId') !== false,
    'target rejects non-canonical textual route IDs after the shared positive-ID parser'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($targetBlock, "\$this->can('pos.stock.commit.audit.index', 'edit')") !== false
        && strpos($targetBlock, "\$this->can('pos.cashier.index', 'edit')") !== false
        && strpos($targetBlock, "'pos.order.draft.index'") !== false,
    'target keeps the existing edit permission selector and fallback matrix'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($targetBlock, "->join('pos_order o', 'o.id = s.order_id', 'left')") !== false
        && strpos($targetBlock, "->where('s.id', \$snapshotId)") !== false
        && strpos($targetBlock, '->limit(1)') !== false,
    'target preserves LEFT JOIN, parsed snapshot binding, and limit(1)'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($targetBlock, "if (\$snapshotStatus !== 'FAILED')") !== false
        && strpos($targetBlock, "if (\$orderStatus === 'VOID')") !== false
        && strpos($targetBlock, "['POSTED', 'REVERSED', 'NOT_REQUIRED']") !== false,
    'target preserves FAILED-only, valid-order, non-VOID, and non-final stock state policy'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($stockServiceSource, 'public function refresh_snapshot_from_order(int $commitId, int $actorEmployeeId = 0, array $options = []): array') !== false
        && strpos($stockServiceSource, 'public function mark_queued(int $commitId): array') !== false,
    'PosStockCommitService refresh/mark_queued contracts remain unchanged'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($runtimeServiceSource, 'public function queue_order_confirm_commit(int $orderId, int $snapshotId, int $actorEmployeeId = 0, array $meta = []): array') !== false
        && strpos($runtimeServiceSource, 'public function process_pending_jobs(array $options = []): array') !== false
        && strpos($runtimeServiceSource, 'public function process_job(int $jobId): array') !== false,
    'PosRuntimeJobService queue/process contracts remain unchanged'
);

pos_runtime_failed_snapshot_retry_check(
    strpos($routeSource, "\$route['pos/orders/runtime-snapshots/retry/(:num)'] = 'pos/order_runtime_failed_snapshot_retry/\$1';") !== false,
    'snapshot retry route remains unchanged'
);
pos_runtime_failed_snapshot_retry_check(
    strpos($routeSource, "\$route['pos/orders/runtime-snapshots/dismiss/(:num)'] = 'pos/order_runtime_failed_snapshot_dismiss/\$1';") !== false
        && strpos($routeSource, "\$route['pos/orders/runtime-jobs/process-all'] = 'pos/order_runtime_jobs_process_all';") !== false,
    'snapshot dismiss and process-all routes remain unchanged'
);

$sharedStart = strpos($viewSource, 'async function postJson(');
$repairWrapperStart = strpos($viewSource, 'function postStockCommitAuditRepairJson(');
$sharedSource = $sharedStart === false
    ? ''
    : substr($viewSource, $sharedStart, ($repairWrapperStart === false ? strlen($viewSource) : $repairWrapperStart) - $sharedStart);
$retryStart = strpos($viewSource, "document.querySelectorAll('.sca_retry_snapshot_btn')");
$dismissStart = strpos($viewSource, "document.querySelectorAll('.sca_dismiss_snapshot_btn')", $retryStart === false ? 0 : $retryStart);
$retryCallerBlock = $retryStart === false
    ? ''
    : substr($viewSource, $retryStart, ($dismissStart === false ? strlen($viewSource) : $dismissStart) - $retryStart);
$retryScopedCaller = "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/retry'); ?>/' + snapshotId, {})";
$retrySharedCaller = "postJson('<?php echo site_url('pos/orders/runtime-snapshots/retry'); ?>/' + snapshotId, {})";
pos_runtime_failed_snapshot_retry_check(
    strpos($sharedSource, 'X-Pos-Transaction-CSRF') === false
        && strpos($sharedSource, 'posTransactionCsrfToken') === false,
    'shared postJson remains neutral and does not add the POS transaction header'
);
pos_runtime_failed_snapshot_retry_check(
    substr_count($viewSource, "document.querySelectorAll('.sca_retry_snapshot_btn')") === 1
        && substr_count($retryCallerBlock, $retryScopedCaller) === 1
        && substr_count($retryCallerBlock, $retrySharedCaller) === 0,
    'only the active snapshot retry handler uses its scoped caller exactly once'
);
pos_runtime_failed_snapshot_retry_check(
    substr_count($viewSource, "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {})") === 1
        && substr_count($viewSource, "postJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {})") === 0,
    'Batch 25 snapshot dismiss caller is scoped exactly once'
);

$rbacDenied = pos_runtime_failed_snapshot_retry_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    [
        'deny_permission' => true,
        'tripwire_db' => true,
        'tripwire_actor' => true,
        'tripwire_loader' => true,
        'tripwire_stock_service' => true,
        'tripwire_model' => true,
        'tripwire_runtime_service' => true,
    ]
);
pos_runtime_failed_snapshot_retry_check(
    $rbacDenied['exception'] instanceof RuntimeException
        && $rbacDenied['exception']->getMessage() === 'RBAC denied',
    'RBAC rejection stops at the existing permission guard'
);
pos_runtime_failed_snapshot_retry_check($rbacDenied['input']->events === [], 'RBAC rejection occurs before CSRF input access');
pos_runtime_failed_snapshot_retry_check($rbacDenied['session']->reads === [], 'RBAC rejection occurs before session access');
pos_runtime_failed_snapshot_retry_check($rbacDenied['db']->calls === [], 'RBAC rejection occurs before DB work');
pos_runtime_failed_snapshot_retry_check($rbacDenied['actor']->reads === [], 'RBAC rejection occurs before actor resolution');
pos_runtime_failed_snapshot_retry_check($rbacDenied['loader']->calls === [], 'RBAC rejection occurs before service load');

$permissionMatrixCases = [
    'stock audit edit' => [
        ['pos.stock.commit.audit.index:edit' => true],
        [['pos.stock.commit.audit.index', 'edit']],
        [['pos.stock.commit.audit.index', 'edit']],
    ],
    'cashier edit fallback' => [
        ['pos.stock.commit.audit.index:edit' => false, 'pos.cashier.index:edit' => true],
        [['pos.stock.commit.audit.index', 'edit'], ['pos.cashier.index', 'edit']],
        [['pos.cashier.index', 'edit']],
    ],
    'draft edit fallback' => [
        ['pos.stock.commit.audit.index:edit' => false, 'pos.cashier.index:edit' => false],
        [['pos.stock.commit.audit.index', 'edit'], ['pos.cashier.index', 'edit']],
        [['pos.order.draft.index', 'edit']],
    ],
];
foreach ($permissionMatrixCases as $label => [$canResults, $expectedCanCalls, $expectedPermissionCalls]) {
    $result = pos_runtime_failed_snapshot_retry_invoke(
        'GET',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        [
            'can_results' => $canResults,
            'tripwire_db' => true,
            'tripwire_actor' => true,
            'tripwire_loader' => true,
        ]
    );
    pos_runtime_failed_snapshot_retry_check($result['output']->status === 405, $label . ' still reaches POST-only guard');
    pos_runtime_failed_snapshot_retry_check($result['controller']->canCalls === $expectedCanCalls, $label . ' keeps can() selector behavior');
    pos_runtime_failed_snapshot_retry_check($result['controller']->permissionCalls === $expectedPermissionCalls, $label . ' keeps edit permission behavior');
    pos_runtime_failed_snapshot_retry_check($result['db']->calls === [], $label . ' performs no DB work');
    pos_runtime_failed_snapshot_retry_check($result['actor']->reads === [], $label . ' does not resolve the actor');
    pos_runtime_failed_snapshot_retry_check($result['loader']->calls === [], $label . ' does not load services');
}

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    pos_runtime_failed_snapshot_retry_assert_guard_rejected(
        pos_runtime_failed_snapshot_retry_invoke(
            $method,
            [$browserHeader => $validToken],
            $validSession,
            '801',
            [
                'tripwire_db' => true,
                'tripwire_actor' => true,
                'tripwire_loader' => true,
                'tripwire_stock_service' => true,
                'tripwire_model' => true,
                'tripwire_runtime_service' => true,
            ]
        ),
        405,
        $method,
        ['method'],
        []
    );
}

$invalidCsrfCases = [
    'missing CSRF' => [[], $validSession, []],
    'malformed CSRF' => [[$browserHeader => 'not-a-token'], $validSession, []],
    'no-session CSRF' => [[$browserHeader => $validToken], [], [$sessionKey]],
    'cross-session CSRF' => [[$browserHeader => $validToken], [$sessionKey => str_repeat('b', 64)], [$sessionKey]],
    'mismatch CSRF' => [[$browserHeader => str_repeat('c', 64)], $validSession, [$sessionKey]],
];
foreach ($invalidCsrfCases as $label => [$headers, $sessionValues, $expectedSessionReads]) {
    pos_runtime_failed_snapshot_retry_assert_guard_rejected(
        pos_runtime_failed_snapshot_retry_invoke(
            'POST',
            $headers,
            $sessionValues,
            '801',
            [
                'tripwire_db' => true,
                'tripwire_actor' => true,
                'tripwire_loader' => true,
                'tripwire_stock_service' => true,
                'tripwire_model' => true,
                'tripwire_runtime_service' => true,
            ]
        ),
        403,
        $label,
        ['method', 'header:' . $canonicalHeader],
        $expectedSessionReads
    );
}

$invalidIds = [
    'zero' => 0,
    'negative' => -1,
    'mixed' => '1abc',
    'float' => 1.5,
    'leading zero' => '0801',
    'leading whitespace' => ' 801',
    'trailing whitespace' => '801 ',
    'overflow' => str_repeat('9', 40),
];
foreach ($invalidIds as $label => $invalidId) {
    $result = pos_runtime_failed_snapshot_retry_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        $invalidId,
        [
            'tripwire_db' => true,
            'tripwire_actor' => true,
            'tripwire_loader' => true,
            'tripwire_stock_service' => true,
            'tripwire_model' => true,
            'tripwire_runtime_service' => true,
        ]
    );
    pos_runtime_failed_snapshot_retry_assert_display_response($result, 422, 'invalid ID ' . $label);
    pos_runtime_failed_snapshot_retry_check(
        $result['input']->events === ['method', 'header:' . $canonicalHeader],
        'invalid ID ' . $label . ' is parsed after scoped CSRF without reading a body'
    );
    pos_runtime_failed_snapshot_retry_check($result['session']->reads === [$sessionKey], 'invalid ID ' . $label . ' stays session-bound');
    pos_runtime_failed_snapshot_retry_check($result['db']->calls === [], 'invalid ID ' . $label . ' performs no DB work');
    pos_runtime_failed_snapshot_retry_assert_before_writers($result, 'invalid ID ' . $label);
}

$missingSnapshot = pos_runtime_failed_snapshot_retry_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    [
        'snapshot' => [],
        'tripwire_actor' => true,
        'tripwire_loader' => true,
        'tripwire_stock_service' => true,
        'tripwire_model' => true,
        'tripwire_runtime_service' => true,
    ]
);
pos_runtime_failed_snapshot_retry_assert_display_response($missingSnapshot, 404, 'missing snapshot');
pos_runtime_failed_snapshot_retry_check(
    $missingSnapshot['db']->calls === pos_runtime_failed_snapshot_retry_expected_lookup_calls(801),
    'missing snapshot uses the complete bound LEFT JOIN lookup'
);
pos_runtime_failed_snapshot_retry_assert_before_writers($missingSnapshot, 'missing snapshot');

foreach (['DRAFT', 'QUEUED', 'PROCESSING', 'COMMITTED', 'REVERSED', 'VOID', ''] as $status) {
    $snapshot = $validSnapshot;
    $snapshot['commit_status'] = $status;
    $label = $status === '' ? 'empty' : $status;
    $result = pos_runtime_failed_snapshot_retry_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        [
            'snapshot' => $snapshot,
            'tripwire_actor' => true,
            'tripwire_loader' => true,
            'tripwire_stock_service' => true,
            'tripwire_model' => true,
            'tripwire_runtime_service' => true,
        ]
    );
    pos_runtime_failed_snapshot_retry_assert_display_response($result, 422, 'non-FAILED snapshot status ' . $label);
    pos_runtime_failed_snapshot_retry_check(
        $result['db']->calls === pos_runtime_failed_snapshot_retry_expected_lookup_calls(801),
        'non-FAILED snapshot status ' . $label . ' uses the bound lookup'
    );
    pos_runtime_failed_snapshot_retry_assert_before_writers($result, 'non-FAILED snapshot status ' . $label);
}

$stateGateCases = [
    'zero order ID' => ['order_id', 0],
    'negative order ID' => ['order_id', -1],
    'VOID order' => ['order_status', 'VOID'],
    'POSTED order stock state' => ['stock_commit_status', 'POSTED'],
    'REVERSED order stock state' => ['stock_commit_status', 'REVERSED'],
    'NOT_REQUIRED order stock state' => ['stock_commit_status', 'NOT_REQUIRED'],
];
foreach ($stateGateCases as $label => [$field, $value]) {
    $snapshot = $validSnapshot;
    $snapshot[$field] = $value;
    $result = pos_runtime_failed_snapshot_retry_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        [
            'snapshot' => $snapshot,
            'tripwire_actor' => true,
            'tripwire_loader' => true,
            'tripwire_stock_service' => true,
            'tripwire_model' => true,
            'tripwire_runtime_service' => true,
        ]
    );
    pos_runtime_failed_snapshot_retry_assert_display_response($result, 422, $label);
    pos_runtime_failed_snapshot_retry_check(
        $result['db']->calls === pos_runtime_failed_snapshot_retry_expected_lookup_calls(801),
        $label . ' uses the bound lookup'
    );
    pos_runtime_failed_snapshot_retry_assert_before_writers($result, $label);
}

$refreshFailure = pos_runtime_failed_snapshot_retry_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    ['refresh_result' => ['ok' => false, 'message' => 'simulated refresh failure']]
);
pos_runtime_failed_snapshot_retry_assert_display_response($refreshFailure, 422, 'refresh failure');
pos_runtime_failed_snapshot_retry_check(
    $refreshFailure['stockService']->calls === [['refresh_snapshot_from_order', [801, 4242, []]]],
    'refresh failure calls only refresh_snapshot_from_order with canonical ID and actor'
);
pos_runtime_failed_snapshot_retry_check($refreshFailure['model']->calls === [], 'refresh failure does not update order state');
pos_runtime_failed_snapshot_retry_check($refreshFailure['runtimeService']->calls === [], 'refresh failure does not queue/process a job');
$refreshFailurePayload = json_decode($refreshFailure['output']->body, true);
pos_runtime_failed_snapshot_retry_check(
    is_array($refreshFailurePayload)
        && ($refreshFailurePayload['ok'] ?? null) === false
        && ($refreshFailurePayload['message'] ?? '') === 'simulated refresh failure',
    'refresh failure preserves the existing error response contract'
);

$queueFailure = pos_runtime_failed_snapshot_retry_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    ['queue_result' => ['ok' => false, 'message' => 'simulated queue failure']]
);
pos_runtime_failed_snapshot_retry_assert_display_response($queueFailure, 422, 'queue failure');
pos_runtime_failed_snapshot_retry_check(
    $queueFailure['stockService']->calls === [
        ['refresh_snapshot_from_order', [801, 4242, []]],
        ['mark_queued', [801]],
    ],
    'queue failure preserves refresh then mark_queued'
);
pos_runtime_failed_snapshot_retry_check(
    $queueFailure['model']->calls === [[
        'update_order_stock_commit_state',
        [501, 'QUEUED', [
            'actor_employee_id' => 4242,
            'event_code' => 'ORDER_CONFIRM_STOCK_RETRY_AUDIT',
            'note' => 'Retry manual snapshot FAILED dari audit stock commit POS.',
        ]],
    ]],
    'queue failure preserves the existing QUEUED order-state payload'
);
pos_runtime_failed_snapshot_retry_check(
    $queueFailure['runtimeService']->calls === [[
        'queue_order_confirm_commit',
        [501, 801, 4242, [
            'event_source' => 'ORDER_CONFIRM_AUDIT_RETRY',
            'event_id' => 801,
        ]],
    ]],
    'queue failure preserves the existing runtime queue payload and stops before process'
);

$processFailureResult = [
    'ok' => false,
    'message' => 'simulated process failure',
    'processed_count' => 1,
    'success_count' => 0,
    'failed_count' => 1,
    'jobs' => [['ok' => false, 'message' => 'simulated worker failure']],
];
$processFailure = pos_runtime_failed_snapshot_retry_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    ['process_result' => $processFailureResult]
);
pos_runtime_failed_snapshot_retry_assert_display_response($processFailure, 422, 'process failure');
pos_runtime_failed_snapshot_retry_check(
    $processFailure['runtimeService']->calls === [
        ['queue_order_confirm_commit', [501, 801, 4242, [
            'event_source' => 'ORDER_CONFIRM_AUDIT_RETRY',
            'event_id' => 801,
        ]]],
        ['process_pending_jobs', [[
            'limit' => 1,
            'order_id' => 501,
            'job_id' => 901,
        ]]],
    ],
    'process failure queues then processes exactly one bound job and does not load latest'
);
$processFailurePayload = json_decode($processFailure['output']->body, true);
pos_runtime_failed_snapshot_retry_check(
    is_array($processFailurePayload)
        && ($processFailurePayload['ok'] ?? null) === false
        && ($processFailurePayload['message'] ?? '') === 'simulated process failure'
        && ($processFailurePayload['result'] ?? null) === $processFailureResult
        && ($processFailurePayload['job_id'] ?? null) === 901
        && ($processFailurePayload['order_id'] ?? null) === 501
        && ($processFailurePayload['snapshot_id'] ?? null) === 801,
    'process failure preserves the existing detailed error payload'
);

foreach (
    [
        'browser header' => [$browserHeader => $validToken],
        'canonical FastCGI header' => [$canonicalHeader => $validToken],
    ] as $label => $headers
) {
    $result = pos_runtime_failed_snapshot_retry_invoke('POST', $headers, $validSession, '801');
    pos_runtime_failed_snapshot_retry_assert_display_response($result, 200, 'valid ' . $label);
    pos_runtime_failed_snapshot_retry_check(
        $result['input']->events === ['method', 'header:' . $canonicalHeader],
        'valid ' . $label . ' uses only the scoped method/header input'
    );
    pos_runtime_failed_snapshot_retry_check($result['session']->reads === [$sessionKey], 'valid ' . $label . ' stays session-bound');
    pos_runtime_failed_snapshot_retry_check(
        $result['controller']->permissionCalls === [['pos.stock.commit.audit.index', 'edit']],
        'valid ' . $label . ' keeps the existing edit RBAC permission'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['db']->calls === pos_runtime_failed_snapshot_retry_expected_lookup_calls(801),
        'valid ' . $label . ' preserves the exact LEFT JOIN lookup and canonical ID binding'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['actor']->reads === [['exists', 'employee_id'], ['get', 'employee_id']],
        'valid ' . $label . ' resolves the actor once after state gates'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['loader']->calls === [
            ['PosRuntimeJobService', 'posruntimejobservice'],
            ['PosStockCommitService', 'posstockcommitservice'],
            ['PosRuntimeJobService', null],
        ],
        'valid ' . $label . ' preserves explicit service loads plus one-job processing load'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['stockService']->calls === [
            ['refresh_snapshot_from_order', [801, 4242, []]],
            ['mark_queued', [801]],
        ],
        'valid ' . $label . ' preserves refresh then mark_queued exactly once'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['model']->calls === [[
            'update_order_stock_commit_state',
            [501, 'QUEUED', [
                'actor_employee_id' => 4242,
                'event_code' => 'ORDER_CONFIRM_STOCK_RETRY_AUDIT',
                'note' => 'Retry manual snapshot FAILED dari audit stock commit POS.',
            ]],
        ]],
        'valid ' . $label . ' preserves the QUEUED order update exactly once'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['runtimeService']->calls === [
            ['queue_order_confirm_commit', [501, 801, 4242, [
                'event_source' => 'ORDER_CONFIRM_AUDIT_RETRY',
                'event_id' => 801,
            ]]],
            ['process_pending_jobs', [[
                'limit' => 1,
                'order_id' => 501,
                'job_id' => 901,
            ]]],
            ['latest_job_for_order', [501]],
        ],
        'valid ' . $label . ' preserves queue, process one bound job, then latest lookup'
    );
    pos_runtime_failed_snapshot_retry_check(
        $result['trace']->events === [
            'db:select',
            'db:from',
            'db:join',
            'db:where',
            'db:limit',
            'db:get',
            'db:row_array',
            'actor:exists:employee_id',
            'actor:get:employee_id',
            'load:PosRuntimeJobService:posruntimejobservice',
            'load:PosStockCommitService:posstockcommitservice',
            'stock:refresh_snapshot_from_order',
            'stock:mark_queued',
            'model:update_order_stock_commit_state',
            'runtime:queue_order_confirm_commit',
            'load:PosRuntimeJobService:null',
            'runtime:process_pending_jobs',
            'runtime:latest_job_for_order',
        ],
        'valid ' . $label . ' proves lookup/state gates precede the exact legacy writer sequence'
    );
    $payload = json_decode($result['output']->body, true);
    pos_runtime_failed_snapshot_retry_check(
        is_array($payload)
            && ($payload['ok'] ?? false) === true
            && ($payload['order_id'] ?? null) === 501
            && ($payload['snapshot_id'] ?? null) === 801
            && ($payload['job']['id'] ?? null) === 901
            && ($payload['processed_count'] ?? null) === 1
            && ($payload['success_count'] ?? null) === 1
            && ($payload['failed_count'] ?? null) === 0
            && ($payload['jobs'] ?? null) === $defaultProcessResult['jobs'],
        'valid ' . $label . ' returns the existing success payload'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS runtime failed-snapshot retry CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS runtime failed-snapshot retry CSRF/state/writer checks\n";
