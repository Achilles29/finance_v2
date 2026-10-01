<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke for
 * POST /pos/orders/runtime-jobs/dismiss/{jobId}.
 *
 * The actual Pos controller is loaded without its constructor. In-memory
 * fakes prove RBAC, scoped CSRF, strict route-ID parsing, lookup binding, status
 * policy, and the existing cancel_job(jobId, reason) contract.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosRuntimeFailedJobDismissSmokeTrace
{
    public array $events = [];
}

final class PosRuntimeFailedJobDismissSmokeInput
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

final class PosRuntimeFailedJobDismissSmokeSession
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

final class PosRuntimeFailedJobDismissSmokeOutput
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

final class PosRuntimeFailedJobDismissSmokeDb
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedJobDismissSmokeTrace $trace,
        private array $job,
        private bool $tableReady = true,
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

    public function table_exists(string $table): bool
    {
        $this->record('table_exists', [$table]);
        return $this->tableReady && $table === 'pos_runtime_job';
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
        return $this->job;
    }
}

final class PosRuntimeFailedJobDismissSmokeLoader
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedJobDismissSmokeTrace $trace,
        private bool $tripwire = false
    ) {
    }

    public function library(string $name, $params = null, $objectName = null): void
    {
        $this->calls[] = [$name, $objectName];
        $this->trace->events[] = 'load:' . $name;
        if ($this->tripwire) {
            throw new RuntimeException('loader tripwire reached: ' . $name);
        }
    }
}

final class PosRuntimeFailedJobDismissSmokeService
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedJobDismissSmokeTrace $trace,
        private array $result = ['ok' => true, 'id' => 701, 'status' => 'CANCELLED'],
        private bool $tripwire = false
    ) {
    }

    public function cancel_job(int $jobId, string $reason = ''): array
    {
        $this->calls[] = ['cancel_job', [$jobId, $reason]];
        $this->trace->events[] = 'service:cancel_job';
        if ($this->tripwire) {
            throw new RuntimeException('service tripwire reached: cancel_job');
        }
        return $this->result;
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
    public array $current_user = ['id' => 77, 'employee_id' => 4242];
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
$cancelReason = 'Ditutup manual dari audit stock commit POS karena order sudah tidak perlu diproses ulang.';
$validJob = [
    'id' => 701,
    'status' => 'FAILED',
    'order_id' => 501,
    'order_no' => 'ORDER-SMOKE',
    'order_status' => 'VOID',
];

function pos_runtime_failed_job_dismiss_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_runtime_failed_job_dismiss_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_runtime_failed_job_dismiss_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_runtime_failed_job_dismiss_method_block(string $source, string $method): string
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

function pos_runtime_failed_job_dismiss_invoke(
    string $requestMethod,
    array $headers,
    array $sessionValues,
    $routeId,
    array $options = []
): array {
    global $validJob;

    $trace = new PosRuntimeFailedJobDismissSmokeTrace();
    $input = new PosRuntimeFailedJobDismissSmokeInput($requestMethod, $headers);
    $session = new PosRuntimeFailedJobDismissSmokeSession($sessionValues);
    $output = new PosRuntimeFailedJobDismissSmokeOutput();
    $db = new PosRuntimeFailedJobDismissSmokeDb(
        $trace,
        array_key_exists('job', $options) ? (array)$options['job'] : $validJob,
        (bool)($options['table_ready'] ?? true),
        (bool)($options['tripwire_db'] ?? false)
    );
    $loader = new PosRuntimeFailedJobDismissSmokeLoader(
        $trace,
        (bool)($options['tripwire_loader'] ?? false)
    );
    $service = new PosRuntimeFailedJobDismissSmokeService(
        $trace,
        (array)($options['service_result'] ?? ['ok' => true, 'id' => 701, 'status' => 'CANCELLED']),
        (bool)($options['tripwire_service'] ?? false)
    );

    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = $db;
    $controller->load = $loader;
    $controller->posruntimejobservice = $service;
    $controller->canResults = (array)($options['can_results'] ?? ['pos.stock.commit.audit.index:edit' => true]);
    $controller->denyPermission = (bool)($options['deny_permission'] ?? false);

    $exception = null;
    try {
        $reflection->getMethod('order_runtime_failed_job_dismiss')->invoke($controller, $routeId);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    return compact(
        'controller',
        'input',
        'session',
        'output',
        'db',
        'loader',
        'service',
        'trace',
        'exception'
    );
}

function pos_runtime_failed_job_dismiss_assert_guard_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    global $canonicalHeader, $sessionKey;

    pos_runtime_failed_job_dismiss_check($result['exception'] === null, $label . ' completes at the scoped guard');
    pos_runtime_failed_job_dismiss_check($result['output']->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_failed_job_dismiss_check($result['output']->contentType === 'application/json', $label . ' returns JSON');
    pos_runtime_failed_job_dismiss_check($result['input']->events === $expectedInputEvents, $label . ' uses only the expected input boundary');
    pos_runtime_failed_job_dismiss_check($result['session']->reads === $expectedSessionReads, $label . ' uses only the expected session boundary');
    pos_runtime_failed_job_dismiss_check($result['db']->calls === [], $label . ' performs no DB work');
    pos_runtime_failed_job_dismiss_check($result['loader']->calls === [], $label . ' does not load the runtime service');
    pos_runtime_failed_job_dismiss_check($result['service']->calls === [], $label . ' does not call the runtime service');
    pos_runtime_failed_job_dismiss_check($result['trace']->events === [], $label . ' does not cross a downstream tripwire');
    pos_runtime_failed_job_dismiss_check(
        $result['controller']->permissionCalls === [['pos.stock.commit.audit.index', 'edit']],
        $label . ' runs after the existing edit RBAC check'
    );
}

function pos_runtime_failed_job_dismiss_assert_display_response(array $result, int $status, string $label): void
{
    pos_runtime_failed_job_dismiss_check(
        $result['exception'] instanceof RuntimeException
            && $result['exception']->getMessage() === 'response displayed',
        $label . ' reaches its JSON response without crossing a tripwire'
    );
    pos_runtime_failed_job_dismiss_check($result['output']->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_failed_job_dismiss_check($result['output']->contentType === 'application/json', $label . ' returns JSON');
}

function pos_runtime_failed_job_dismiss_expected_lookup_calls(int $jobId, bool $tableReady = true): array
{
    $calls = [['table_exists', ['pos_runtime_job']]];
    if (!$tableReady) {
        return $calls;
    }
    return array_merge($calls, [
        ['select', ['j.id, j.status, j.order_id, o.order_no, o.status AS order_status']],
        ['from', ['pos_runtime_job j']],
        ['join', ['pos_order o', 'o.id = j.order_id', 'left']],
        ['where', ['j.id', $jobId]],
        ['where', ['j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT']],
        ['limit', [1]],
        ['get', []],
        ['row_array', []],
    ]);
}

$controllerSource = pos_runtime_failed_job_dismiss_source($root . '/application/controllers/Pos.php');
$viewSource = pos_runtime_failed_job_dismiss_source($root . '/application/views/pos/stock_commit_audit_index.php');
$routeSource = pos_runtime_failed_job_dismiss_source($root . '/application/config/routes.php');
$serviceSource = pos_runtime_failed_job_dismiss_source($root . '/application/libraries/PosRuntimeJobService.php');
$targetBlock = pos_runtime_failed_job_dismiss_method_block($controllerSource, 'order_runtime_failed_job_dismiss');

$permissionPosition = strpos($targetBlock, "require_permission(\$pageCode, 'edit')");
$guardPosition = strpos($targetBlock, 'require_pos_transaction_csrf()');
$idPosition = strpos($targetBlock, '$jobId = $this->parse_positive_runtime_id($jobId);');
$dbPosition = strpos($targetBlock, "\$this->db->table_exists('pos_runtime_job')");
$lookupPosition = strpos($targetBlock, '$job = $this->db->select(');
$statusPosition = strpos($targetBlock, "\$jobStatus !== 'FAILED'");
$loadPosition = strpos($targetBlock, "\$this->load->library('PosRuntimeJobService')");
$servicePosition = strpos($targetBlock, '$this->posruntimejobservice->cancel_job(');
pos_runtime_failed_job_dismiss_check($targetBlock !== '', 'target endpoint exists in the actual controller');
pos_runtime_failed_job_dismiss_check(
    $permissionPosition !== false
        && $guardPosition !== false
        && $idPosition !== false
        && $dbPosition !== false
        && $lookupPosition !== false
        && $statusPosition !== false
        && $loadPosition !== false
        && $servicePosition !== false
        && $permissionPosition < $guardPosition
        && $guardPosition < $idPosition
        && $idPosition < $dbPosition
        && $dbPosition < $lookupPosition
        && $lookupPosition < $statusPosition
        && $statusPosition < $loadPosition
        && $loadPosition < $servicePosition,
    'target order is existing edit RBAC, scoped CSRF, strict route ID, DB lookup/status, then service load/call'
);
pos_runtime_failed_job_dismiss_check(
    substr_count($targetBlock, '$this->require_pos_transaction_csrf()') === 1
        && substr_count($targetBlock, '$this->parse_positive_runtime_id($jobId)') === 1
        && substr_count($targetBlock, '$this->posruntimejobservice->cancel_job(') === 1,
    'target has one scoped guard, one strict parser, and one cancel call site'
);
pos_runtime_failed_job_dismiss_check(
    strpos($targetBlock, "\$this->can('pos.stock.commit.audit.index', 'edit')") !== false
        && strpos($targetBlock, "\$this->can('pos.cashier.index', 'edit')") !== false
        && strpos($targetBlock, "'pos.order.draft.index'") !== false,
    'target keeps the existing edit permission selector and fallback matrix'
);
pos_runtime_failed_job_dismiss_check(
    strpos($targetBlock, '(string)$jobId !== (string)$routeJobId') !== false,
    'target rejects non-canonical textual route IDs after the shared positive-ID parser'
);
pos_runtime_failed_job_dismiss_check(
    strpos($targetBlock, "->join('pos_order o', 'o.id = j.order_id', 'left')") !== false
        && strpos($targetBlock, "->where('j.id', \$jobId)") !== false
        && strpos($targetBlock, "->where('j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT')") !== false
        && strpos($targetBlock, '->limit(1)') !== false,
    'target preserves LEFT JOIN, parsed ID binding, fixed job type, and limit(1)'
);
pos_runtime_failed_job_dismiss_check(
    strpos($targetBlock, "if (\$jobStatus !== 'FAILED')") !== false,
    'target preserves the FAILED-only close policy'
);
pos_runtime_failed_job_dismiss_check(
    strpos($targetBlock, "'" . $cancelReason . "'") !== false,
    'target preserves the existing manual-dismiss cancellation reason'
);
pos_runtime_failed_job_dismiss_check(
    strpos($serviceSource, "public function cancel_job(int \$jobId, string \$reason = ''): array") !== false
        && strpos($serviceSource, "->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])") !== false,
    'PosRuntimeJobService cancel_job contract remains unchanged'
);

pos_runtime_failed_job_dismiss_check(
    strpos($routeSource, "\$route['pos/orders/runtime-jobs/dismiss/(:num)'] = 'pos/order_runtime_failed_job_dismiss/\$1';") !== false,
    'dismiss route remains unchanged'
);
pos_runtime_failed_job_dismiss_check(
    strpos($routeSource, "\$route['pos/orders/runtime-jobs/delete-draft/(:num)'] = 'pos/order_runtime_failed_job_delete_draft/\$1';") !== false
        && strpos($routeSource, "\$route['pos/orders/runtime-snapshots/retry/(:num)'] = 'pos/order_runtime_failed_snapshot_retry/\$1';") !== false
        && strpos($routeSource, "\$route['pos/orders/runtime-snapshots/dismiss/(:num)'] = 'pos/order_runtime_failed_snapshot_dismiss/\$1';") !== false,
    'delete-draft and snapshot routes remain unchanged'
);

$sharedStart = strpos($viewSource, 'async function postJson(');
$repairWrapperStart = strpos($viewSource, 'function postStockCommitAuditRepairJson(');
$sharedSource = $sharedStart === false
    ? ''
    : substr($viewSource, $sharedStart, ($repairWrapperStart === false ? strlen($viewSource) : $repairWrapperStart) - $sharedStart);
$dismissStart = strpos($viewSource, "document.querySelectorAll('.sca_dismiss_failed_job_btn')");
$snapshotRetryStart = strpos($viewSource, "document.querySelectorAll('.sca_retry_snapshot_btn')", $dismissStart === false ? 0 : $dismissStart);
$snapshotDismissStart = strpos($viewSource, "document.querySelectorAll('.sca_dismiss_snapshot_btn')", $snapshotRetryStart === false ? 0 : $snapshotRetryStart);
$processAllStart = strpos($viewSource, "document.getElementById('sca_process_all_btn')", $snapshotDismissStart === false ? 0 : $snapshotDismissStart);
pos_runtime_failed_job_dismiss_check(
    $processAllStart !== false
        && $snapshotDismissStart !== false
        && $processAllStart > $snapshotDismissStart,
    'process-all button delimiter exists after the snapshot dismiss handler'
);
$dismissCallerBlock = $dismissStart === false
    ? ''
    : substr($viewSource, $dismissStart, ($snapshotRetryStart === false ? strlen($viewSource) : $snapshotRetryStart) - $dismissStart);
$snapshotRetryCallerBlock = $snapshotRetryStart === false
    ? ''
    : substr($viewSource, $snapshotRetryStart, ($snapshotDismissStart === false ? strlen($viewSource) : $snapshotDismissStart) - $snapshotRetryStart);
$snapshotDismissCallerBlock = $snapshotDismissStart === false
    ? ''
    : substr($viewSource, $snapshotDismissStart, ($processAllStart === false ? strlen($viewSource) : $processAllStart) - $snapshotDismissStart);
$dismissScopedCaller = "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/dismiss'); ?>/' + jobId, {})";
$dismissSharedCaller = "postJson('<?php echo site_url('pos/orders/runtime-jobs/dismiss'); ?>/' + jobId, {})";
pos_runtime_failed_job_dismiss_check(
    strpos($sharedSource, 'X-Pos-Transaction-CSRF') === false
        && strpos($sharedSource, 'posTransactionCsrfToken') === false,
    'shared postJson remains neutral and does not add the POS transaction header'
);
pos_runtime_failed_job_dismiss_check(
    substr_count($viewSource, "document.querySelectorAll('.sca_dismiss_failed_job_btn')") === 1
        && substr_count($dismissCallerBlock, $dismissScopedCaller) === 1
        && substr_count($dismissCallerBlock, $dismissSharedCaller) === 0,
    'only the active failed-job dismiss handler uses its scoped caller exactly once'
);
pos_runtime_failed_job_dismiss_check(
    substr_count($viewSource, "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/delete-draft'); ?>/' + jobId, {})") === 1,
    'Batch 22 delete-draft caller remains scoped and unchanged'
);
pos_runtime_failed_job_dismiss_check(
    substr_count($snapshotRetryCallerBlock, "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/retry'); ?>/' + snapshotId, {})") === 1
        && substr_count($snapshotRetryCallerBlock, "pos/orders/runtime-snapshots/dismiss") === 0
        && substr_count($snapshotDismissCallerBlock, "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {})") === 1
        && substr_count($snapshotDismissCallerBlock, "pos/orders/runtime-snapshots/retry") === 0
        && substr_count($viewSource, "postJson('<?php echo site_url('pos/orders/runtime-snapshots/retry'); ?>/' + snapshotId, {})") === 0
        && substr_count($viewSource, "postJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {})") === 0,
    'Batch 24 retry and Batch 25 dismiss snapshot callers are split into their scoped handlers'
);

$rbacDenied = pos_runtime_failed_job_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '701',
    [
        'deny_permission' => true,
        'tripwire_db' => true,
        'tripwire_loader' => true,
        'tripwire_service' => true,
    ]
);
pos_runtime_failed_job_dismiss_check(
    $rbacDenied['exception'] instanceof RuntimeException
        && $rbacDenied['exception']->getMessage() === 'RBAC denied',
    'RBAC rejection stops at the existing permission guard'
);
pos_runtime_failed_job_dismiss_check($rbacDenied['input']->events === [], 'RBAC rejection occurs before CSRF input access');
pos_runtime_failed_job_dismiss_check($rbacDenied['session']->reads === [], 'RBAC rejection occurs before session access');
pos_runtime_failed_job_dismiss_check($rbacDenied['db']->calls === [], 'RBAC rejection occurs before DB work');
pos_runtime_failed_job_dismiss_check($rbacDenied['loader']->calls === [], 'RBAC rejection occurs before service load');
pos_runtime_failed_job_dismiss_check($rbacDenied['service']->calls === [], 'RBAC rejection occurs before service call');

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
    $result = pos_runtime_failed_job_dismiss_invoke(
        'GET',
        [$browserHeader => $validToken],
        $validSession,
        '701',
        [
            'can_results' => $canResults,
            'tripwire_db' => true,
            'tripwire_loader' => true,
            'tripwire_service' => true,
        ]
    );
    pos_runtime_failed_job_dismiss_check($result['output']->status === 405, $label . ' still reaches POST-only guard');
    pos_runtime_failed_job_dismiss_check($result['controller']->canCalls === $expectedCanCalls, $label . ' keeps can() selector behavior');
    pos_runtime_failed_job_dismiss_check($result['controller']->permissionCalls === $expectedPermissionCalls, $label . ' keeps edit permission behavior');
    pos_runtime_failed_job_dismiss_check($result['db']->calls === [], $label . ' performs no DB work');
    pos_runtime_failed_job_dismiss_check($result['loader']->calls === [], $label . ' does not load the service');
    pos_runtime_failed_job_dismiss_check($result['service']->calls === [], $label . ' does not call the service');
}

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    pos_runtime_failed_job_dismiss_assert_guard_rejected(
        pos_runtime_failed_job_dismiss_invoke(
            $method,
            [$browserHeader => $validToken],
            $validSession,
            '701',
            [
                'tripwire_db' => true,
                'tripwire_loader' => true,
                'tripwire_service' => true,
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
    pos_runtime_failed_job_dismiss_assert_guard_rejected(
        pos_runtime_failed_job_dismiss_invoke(
            'POST',
            $headers,
            $sessionValues,
            '701',
            [
                'tripwire_db' => true,
                'tripwire_loader' => true,
                'tripwire_service' => true,
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
    'leading zero' => '0701',
    'leading whitespace' => ' 701',
    'trailing whitespace' => '701 ',
    'overflow' => str_repeat('9', 40),
];
foreach ($invalidIds as $label => $invalidId) {
    $result = pos_runtime_failed_job_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        $invalidId,
        [
            'tripwire_db' => true,
            'tripwire_loader' => true,
            'tripwire_service' => true,
        ]
    );
    pos_runtime_failed_job_dismiss_assert_display_response($result, 422, 'invalid ID ' . $label);
    pos_runtime_failed_job_dismiss_check(
        $result['input']->events === ['method', 'header:' . $canonicalHeader],
        'invalid ID ' . $label . ' is parsed after scoped CSRF without reading a body'
    );
    pos_runtime_failed_job_dismiss_check($result['session']->reads === [$sessionKey], 'invalid ID ' . $label . ' stays session-bound');
    pos_runtime_failed_job_dismiss_check($result['db']->calls === [], 'invalid ID ' . $label . ' performs no DB work');
    pos_runtime_failed_job_dismiss_check($result['loader']->calls === [], 'invalid ID ' . $label . ' does not load the service');
    pos_runtime_failed_job_dismiss_check($result['service']->calls === [], 'invalid ID ' . $label . ' does not call the service');
}

$tableMissing = pos_runtime_failed_job_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '701',
    [
        'table_ready' => false,
        'tripwire_loader' => true,
        'tripwire_service' => true,
    ]
);
pos_runtime_failed_job_dismiss_assert_display_response($tableMissing, 422, 'missing runtime table');
pos_runtime_failed_job_dismiss_check(
    $tableMissing['db']->calls === pos_runtime_failed_job_dismiss_expected_lookup_calls(701, false),
    'missing runtime table stops after the readiness lookup'
);
pos_runtime_failed_job_dismiss_check($tableMissing['loader']->calls === [], 'missing runtime table does not load the service');
pos_runtime_failed_job_dismiss_check($tableMissing['service']->calls === [], 'missing runtime table does not call the service');

$jobMissing = pos_runtime_failed_job_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '701',
    [
        'job' => [],
        'tripwire_loader' => true,
        'tripwire_service' => true,
    ]
);
pos_runtime_failed_job_dismiss_assert_display_response($jobMissing, 404, 'missing job');
pos_runtime_failed_job_dismiss_check(
    $jobMissing['db']->calls === pos_runtime_failed_job_dismiss_expected_lookup_calls(701),
    'missing job uses the complete bound lookup'
);
pos_runtime_failed_job_dismiss_check($jobMissing['loader']->calls === [], 'missing job does not load the service');
pos_runtime_failed_job_dismiss_check($jobMissing['service']->calls === [], 'missing job does not call the service');

foreach (['QUEUED', 'PROCESSING', 'SUCCESS', 'CANCELLED', ''] as $status) {
    $statusLabel = $status === '' ? 'empty' : $status;
    $job = $validJob;
    $job['status'] = $status;
    $result = pos_runtime_failed_job_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '701',
        [
            'job' => $job,
            'tripwire_loader' => true,
            'tripwire_service' => true,
        ]
    );
    pos_runtime_failed_job_dismiss_assert_display_response($result, 422, 'non-FAILED status ' . $statusLabel);
    pos_runtime_failed_job_dismiss_check(
        $result['db']->calls === pos_runtime_failed_job_dismiss_expected_lookup_calls(701),
        'non-FAILED status ' . $statusLabel . ' uses the bound lookup'
    );
    pos_runtime_failed_job_dismiss_check($result['loader']->calls === [], 'non-FAILED status ' . $statusLabel . ' does not load the service');
    pos_runtime_failed_job_dismiss_check($result['service']->calls === [], 'non-FAILED status ' . $statusLabel . ' does not call cancel_job');
}

$serviceFailure = pos_runtime_failed_job_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '701',
    ['service_result' => ['ok' => false, 'message' => 'simulated cancel failure']]
);
pos_runtime_failed_job_dismiss_assert_display_response($serviceFailure, 422, 'cancel_job failure');
pos_runtime_failed_job_dismiss_check(
    $serviceFailure['loader']->calls === [['PosRuntimeJobService', null]],
    'cancel_job failure loads the existing service exactly once after lookup'
);
pos_runtime_failed_job_dismiss_check(
    $serviceFailure['service']->calls === [['cancel_job', [701, $cancelReason]]],
    'cancel_job failure still invokes the existing contract exactly once'
);

foreach (
    [
        'browser header' => [$browserHeader => $validToken],
        'canonical FastCGI header' => [$canonicalHeader => $validToken],
    ] as $label => $headers
) {
    $result = pos_runtime_failed_job_dismiss_invoke('POST', $headers, $validSession, '701');
    pos_runtime_failed_job_dismiss_assert_display_response($result, 200, 'valid ' . $label);
    pos_runtime_failed_job_dismiss_check(
        $result['input']->events === ['method', 'header:' . $canonicalHeader],
        'valid ' . $label . ' uses only the scoped method/header input'
    );
    pos_runtime_failed_job_dismiss_check($result['session']->reads === [$sessionKey], 'valid ' . $label . ' stays session-bound');
    pos_runtime_failed_job_dismiss_check(
        $result['controller']->permissionCalls === [['pos.stock.commit.audit.index', 'edit']],
        'valid ' . $label . ' keeps the existing edit RBAC permission'
    );
    pos_runtime_failed_job_dismiss_check(
        $result['db']->calls === pos_runtime_failed_job_dismiss_expected_lookup_calls(701),
        'valid ' . $label . ' preserves the exact LEFT JOIN lookup and parsed ID/type binding'
    );
    pos_runtime_failed_job_dismiss_check(
        $result['loader']->calls === [['PosRuntimeJobService', null]],
        'valid ' . $label . ' loads PosRuntimeJobService exactly once'
    );
    pos_runtime_failed_job_dismiss_check(
        $result['service']->calls === [['cancel_job', [701, $cancelReason]]],
        'valid ' . $label . ' reaches cancel_job(jobId, old reason) exactly once'
    );
    pos_runtime_failed_job_dismiss_check(
        $result['trace']->events === [
            'db:table_exists',
            'db:select',
            'db:from',
            'db:join',
            'db:where',
            'db:where',
            'db:limit',
            'db:get',
            'db:row_array',
            'load:PosRuntimeJobService',
            'service:cancel_job',
        ],
        'valid ' . $label . ' loads/calls the service only after the completed DB lookup'
    );
    $payload = json_decode($result['output']->body, true);
    pos_runtime_failed_job_dismiss_check(
        is_array($payload)
            && ($payload['ok'] ?? false) === true
            && ($payload['job_id'] ?? null) === 701
            && ($payload['order_id'] ?? null) === 501,
        'valid ' . $label . ' returns the existing success payload'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS runtime failed-job dismiss CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS runtime failed-job dismiss CSRF/binding checks\n";
