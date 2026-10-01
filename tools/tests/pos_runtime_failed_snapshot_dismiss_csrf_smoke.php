<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke for
 * POST /pos/orders/runtime-snapshots/dismiss/{snapshotId}.
 *
 * The real Pos method is invoked through Reflection. Fakes and tripwires prove
 * the RBAC/CSRF/ID boundary, lookup and state gates, server-side close mapping,
 * legacy writer/cancellation order, response contract, route, and scoped caller.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosRuntimeFailedSnapshotDismissSmokeTrace
{
    public array $events = [];
}

final class PosRuntimeFailedSnapshotDismissSmokeInput
{
    public array $events = [];
    private string $requestMethod;
    private array $headers = [];

    public function __construct(string $requestMethod, array $headers = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        foreach ($headers as $name => $value) {
            $this->headers[self::normalizeHeader((string)$name)] = (string)$value;
        }
    }

    private static function normalizeHeader(string $name): string
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
        throw new RuntimeException('request body/input tripwire reached: ' . $name);
    }
}

final class PosRuntimeFailedSnapshotDismissSmokeSession
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

final class PosRuntimeFailedSnapshotDismissSmokeOutput
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

final class PosRuntimeFailedSnapshotDismissSmokeActor implements ArrayAccess
{
    public array $reads = [];

    public function __construct(private PosRuntimeFailedSnapshotDismissSmokeTrace $trace)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->reads[] = ['exists', $offset];
        $this->trace->events[] = 'actor:exists:' . (string)$offset;
        throw new RuntimeException('actor tripwire reached: ' . (string)$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->reads[] = ['get', $offset];
        $this->trace->events[] = 'actor:get:' . (string)$offset;
        throw new RuntimeException('actor tripwire reached: ' . (string)$offset);
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

final class PosRuntimeFailedSnapshotDismissSmokeDb
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotDismissSmokeTrace $trace,
        private array $snapshot,
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

    public function where_in(string $key, array $values): self
    {
        $this->record('where_in', [$key, $values]);
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

    public function table_exists(string $table): bool
    {
        $this->record('table_exists', [$table]);
        return $this->tableReady && $table === 'pos_runtime_job';
    }

    public function update(string $table, array $data): bool
    {
        $this->record('update', [$table, $data]);
        return true;
    }
}

final class PosRuntimeFailedSnapshotDismissSmokeLoader
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotDismissSmokeTrace $trace,
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

final class PosRuntimeFailedSnapshotDismissSmokeStockService
{
    public array $calls = [];

    public function __construct(
        private PosRuntimeFailedSnapshotDismissSmokeTrace $trace,
        private array $result,
        private bool $tripwire = false
    ) {
    }

    public function mark_reversed(int $snapshotId, string $closeAs): array
    {
        $this->calls[] = ['mark_reversed', [$snapshotId, $closeAs]];
        $this->trace->events[] = 'stock:mark_reversed:' . $closeAs;
        if ($this->tripwire) {
            throw new RuntimeException('stock service tripwire reached: mark_reversed');
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
$otherToken = str_repeat('b', 64);
$validSession = [$sessionKey => $validToken];
$cancelReason = 'Ditutup manual dari audit stock commit POS karena snapshot gagal sudah tidak perlu diproses ulang.';
$stateError = 'Snapshot FAILED ini masih butuh retry/rebuild. Tutup manual hanya diizinkan bila order sudah VOID atau stock commit order sudah final.';
$validSnapshot = [
    'id' => 801,
    'commit_status' => 'FAILED',
    'order_id' => 501,
    'order_no' => 'ORDER-SMOKE',
    'order_status' => 'VOID',
    'stock_commit_status' => 'QUEUED',
];

function pos_runtime_failed_snapshot_dismiss_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_runtime_failed_snapshot_dismiss_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_runtime_failed_snapshot_dismiss_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_runtime_failed_snapshot_dismiss_method_block(string $source, string $method): string
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

function pos_runtime_failed_snapshot_dismiss_invoke(
    string $requestMethod,
    array $headers,
    array $sessionValues,
    $routeId,
    array $options = []
): array {
    global $validSnapshot;

    $trace = new PosRuntimeFailedSnapshotDismissSmokeTrace();
    $input = new PosRuntimeFailedSnapshotDismissSmokeInput($requestMethod, $headers);
    $session = new PosRuntimeFailedSnapshotDismissSmokeSession($sessionValues);
    $output = new PosRuntimeFailedSnapshotDismissSmokeOutput();
    $actor = new PosRuntimeFailedSnapshotDismissSmokeActor($trace);
    $db = new PosRuntimeFailedSnapshotDismissSmokeDb(
        $trace,
        array_key_exists('snapshot', $options) ? (array)$options['snapshot'] : $validSnapshot,
        (bool)($options['table_ready'] ?? true),
        (bool)($options['tripwire_db'] ?? false)
    );
    $loader = new PosRuntimeFailedSnapshotDismissSmokeLoader(
        $trace,
        (bool)($options['tripwire_loader'] ?? false)
    );
    $stockService = new PosRuntimeFailedSnapshotDismissSmokeStockService(
        $trace,
        (array)($options['mark_result'] ?? ['ok' => true]),
        (bool)($options['tripwire_service'] ?? false)
    );

    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->current_user = $actor;
    $controller->db = $db;
    $controller->load = $loader;
    $controller->posstockcommitservice = $stockService;
    $controller->canResults = (array)($options['can_results'] ?? ['pos.stock.commit.audit.index:edit' => true]);
    $controller->denyPermission = (bool)($options['deny_permission'] ?? false);

    $exception = null;
    try {
        $reflection->getMethod('order_runtime_failed_snapshot_dismiss')->invoke($controller, $routeId);
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
        'trace',
        'exception'
    );
}

function pos_runtime_failed_snapshot_dismiss_json(array $result): array
{
    $decoded = json_decode($result['output']->body, true);
    return is_array($decoded) ? $decoded : [];
}

function pos_runtime_failed_snapshot_dismiss_assert_response(array $result, int $status, string $label): void
{
    pos_runtime_failed_snapshot_dismiss_check(
        $result['exception'] instanceof RuntimeException
            && $result['exception']->getMessage() === 'response displayed',
        $label . ' reaches its JSON response'
    );
    pos_runtime_failed_snapshot_dismiss_check($result['output']->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_failed_snapshot_dismiss_check($result['output']->contentType === 'application/json', $label . ' returns JSON content type');
    pos_runtime_failed_snapshot_dismiss_check(
        $result['input']->events === ['method', 'header:X-Pos-Transaction-Csrf'],
        $label . ' reads no request body or unscoped input'
    );
    pos_runtime_failed_snapshot_dismiss_check($result['actor']->reads === [], $label . ' does not read actor state');
}

function pos_runtime_failed_snapshot_dismiss_assert_guard_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    pos_runtime_failed_snapshot_dismiss_check($result['exception'] === null, $label . ' completes at the scoped guard');
    pos_runtime_failed_snapshot_dismiss_check($result['output']->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_failed_snapshot_dismiss_check($result['output']->contentType === 'application/json', $label . ' returns JSON content type');
    pos_runtime_failed_snapshot_dismiss_check($result['input']->events === $expectedInputEvents, $label . ' uses only expected input boundary');
    pos_runtime_failed_snapshot_dismiss_check($result['session']->reads === $expectedSessionReads, $label . ' uses only expected session boundary');
    pos_runtime_failed_snapshot_dismiss_check($result['db']->calls === [], $label . ' performs no DB work');
    pos_runtime_failed_snapshot_dismiss_check($result['actor']->reads === [], $label . ' performs no actor work');
    pos_runtime_failed_snapshot_dismiss_check($result['loader']->calls === [], $label . ' loads no service');
    pos_runtime_failed_snapshot_dismiss_check($result['stockService']->calls === [], $label . ' calls no writer');
    pos_runtime_failed_snapshot_dismiss_check($result['trace']->events === [], $label . ' crosses no downstream tripwire');
    pos_runtime_failed_snapshot_dismiss_check(
        pos_runtime_failed_snapshot_dismiss_json($result) === [
            'ok' => false,
            'message' => $status === 405 ? 'Metode request tidak diizinkan.' : 'Permintaan transaksi POS tidak valid.',
        ],
        $label . ' preserves scoped rejection payload'
    );
}

function pos_runtime_failed_snapshot_dismiss_lookup_calls(int $snapshotId): array
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

function pos_runtime_failed_snapshot_dismiss_assert_pre_writer(array $result, string $label): void
{
    pos_runtime_failed_snapshot_dismiss_check($result['loader']->calls === [], $label . ' does not load the stock service');
    pos_runtime_failed_snapshot_dismiss_check($result['stockService']->calls === [], $label . ' does not call mark_reversed');
    pos_runtime_failed_snapshot_dismiss_check($result['actor']->reads === [], $label . ' does not resolve an actor');
}

$controllerSource = pos_runtime_failed_snapshot_dismiss_source($root . '/application/controllers/Pos.php');
$viewSource = pos_runtime_failed_snapshot_dismiss_source($root . '/application/views/pos/stock_commit_audit_index.php');
$routeSource = pos_runtime_failed_snapshot_dismiss_source($root . '/application/config/routes.php');
$serviceSource = pos_runtime_failed_snapshot_dismiss_source($root . '/application/libraries/PosStockCommitService.php');
$targetBlock = pos_runtime_failed_snapshot_dismiss_method_block($controllerSource, 'order_runtime_failed_snapshot_dismiss');

$orderedMarkers = [
    "require_permission(\$pageCode, 'edit')",
    'require_pos_transaction_csrf()',
    '$routeSnapshotId = $snapshotId;',
    '$snapshotId = $this->parse_positive_runtime_id($snapshotId);',
    '$snapshot = $this->db->select(',
    "\$snapshotStatus !== 'FAILED'",
    '$orderId = (int)($snapshot[\'order_id\'] ?? 0);',
    '$orderId <= 0',
    "\$orderStatus === 'VOID'",
    "in_array(\$orderCommitStatus, ['POSTED', 'REVERSED', 'NOT_REQUIRED'], true)",
    "if (\$closeAs === '')",
    "\$this->load->library('PosStockCommitService', null, 'posstockcommitservice')",
    '$this->posstockcommitservice->mark_reversed($snapshotId, $closeAs)',
    "\$this->db->table_exists('pos_runtime_job')",
    "->where('snapshot_id', \$snapshotId)",
    "->where('job_type', 'ORDER_CONFIRM_STOCK_COMMIT')",
    "->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])",
    "->update('pos_runtime_job'",
    '$this->json_ok([',
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

pos_runtime_failed_snapshot_dismiss_check($targetBlock !== '', 'target endpoint exists in actual Pos controller');
pos_runtime_failed_snapshot_dismiss_check(
    $markersOrdered,
    'target order is edit RBAC, scoped CSRF, strict ID, unchanged lookup, state gates, writer, filtered cancel, then JSON'
);
pos_runtime_failed_snapshot_dismiss_check(
    substr_count($targetBlock, '$this->require_pos_transaction_csrf()') === 1
        && substr_count($targetBlock, '$this->parse_positive_runtime_id($snapshotId)') === 1
        && substr_count($targetBlock, '$this->posstockcommitservice->mark_reversed($snapshotId, $closeAs)') === 1,
    'target has one scoped guard, one strict parser, and one mark_reversed writer'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($targetBlock, "\$this->can('pos.stock.commit.audit.index', 'edit')") !== false
        && strpos($targetBlock, "\$this->can('pos.cashier.index', 'edit')") !== false
        && strpos($targetBlock, "'pos.order.draft.index'") !== false,
    'target preserves stock-audit to cashier to draft edit-RBAC selector'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($targetBlock, '(string)$snapshotId !== (string)$routeSnapshotId') !== false,
    'target rejects non-canonical textual route IDs'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($targetBlock, "->join('pos_order o', 'o.id = s.order_id', 'left')") !== false
        && strpos($targetBlock, "->where('s.id', \$snapshotId)") !== false
        && strpos($targetBlock, '->limit(1)') !== false,
    'target preserves LEFT JOIN, parsed lookup binding, and limit'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($targetBlock, "if (\$snapshotStatus !== 'FAILED')") !== false
        && strpos($targetBlock, 'if ($orderId <= 0)') !== false
        && strpos($targetBlock, "if (\$orderStatus === 'VOID')") !== false
        && strpos($targetBlock, "elseif (in_array(\$orderCommitStatus, ['POSTED', 'REVERSED', 'NOT_REQUIRED'], true))") !== false,
    'target keeps FAILED-only, positive order, VOID precedence, and final-stock close policy'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($targetBlock, 'request_payload(') === false
        && strpos($targetBlock, 'raw_input_stream') === false
        && strpos($targetBlock, '$this->input->post(') === false
        && strpos($targetBlock, '$this->input->get(') === false
        && strpos($targetBlock, 'current_actor_employee_id(') === false,
    'target reads no request body/client closeAs and resolves no actor'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($targetBlock, "'last_error' => '" . $cancelReason . "'") !== false,
    'target preserves legacy cancellation reason'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($serviceSource, "public function mark_reversed(int \$commitId, string \$status = 'REVERSED'): array") !== false,
    'PosStockCommitService mark_reversed contract remains unchanged'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($routeSource, "\$route['pos/orders/runtime-snapshots/dismiss/(:num)'] = 'pos/order_runtime_failed_snapshot_dismiss/\$1';") !== false,
    'snapshot dismiss route remains unchanged'
);

$sharedStart = strpos($viewSource, 'async function postJson(');
$repairWrapperStart = strpos($viewSource, 'function postStockCommitAuditRepairJson(');
$sharedSource = $sharedStart === false
    ? ''
    : substr($viewSource, $sharedStart, ($repairWrapperStart === false ? strlen($viewSource) : $repairWrapperStart) - $sharedStart);
$dismissStart = strpos($viewSource, "document.querySelectorAll('.sca_dismiss_snapshot_btn')");
$processAllStart = strpos($viewSource, "document.getElementById('sca_process_all_btn')", $dismissStart === false ? 0 : $dismissStart);
pos_runtime_failed_snapshot_dismiss_check(
    $processAllStart !== false
        && $dismissStart !== false
        && $processAllStart > $dismissStart,
    'process-all button delimiter exists after the snapshot dismiss handler'
);
$dismissCallerBlock = $dismissStart === false
    ? ''
    : substr($viewSource, $dismissStart, ($processAllStart === false ? strlen($viewSource) : $processAllStart) - $dismissStart);
$scopedCaller = "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {})";
$neutralCaller = "postJson('<?php echo site_url('pos/orders/runtime-snapshots/dismiss'); ?>/' + snapshotId, {})";
pos_runtime_failed_snapshot_dismiss_check(
    strpos($sharedSource, 'X-Pos-Transaction-CSRF') === false
        && strpos($sharedSource, 'posTransactionCsrfToken') === false,
    'shared postJson remains neutral'
);
pos_runtime_failed_snapshot_dismiss_check(
    substr_count($viewSource, "document.querySelectorAll('.sca_dismiss_snapshot_btn')") === 1
        && substr_count($dismissCallerBlock, $scopedCaller) === 1
        && substr_count($dismissCallerBlock, $neutralCaller) === 0
        && substr_count($dismissCallerBlock, 'snapshotId, {})') === 1,
    'active snapshot dismiss handler uses scoped caller exactly once with an empty body'
);
pos_runtime_failed_snapshot_dismiss_check(
    strpos($dismissCallerBlock, 'closeAs,') === false
        && strpos($dismissCallerBlock, '{ closeAs') === false
        && strpos($dismissCallerBlock, 'close_as') === false,
    'caller does not send client closeAs to the endpoint'
);

$rbacDenied = pos_runtime_failed_snapshot_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    [
        'deny_permission' => true,
        'tripwire_db' => true,
        'tripwire_loader' => true,
        'tripwire_service' => true,
    ]
);
pos_runtime_failed_snapshot_dismiss_check(
    $rbacDenied['exception'] instanceof RuntimeException
        && $rbacDenied['exception']->getMessage() === 'RBAC denied',
    'RBAC denial stops at existing permission guard'
);
pos_runtime_failed_snapshot_dismiss_check($rbacDenied['input']->events === [], 'RBAC denial occurs before method/header/body access');
pos_runtime_failed_snapshot_dismiss_check($rbacDenied['session']->reads === [], 'RBAC denial occurs before session access');
pos_runtime_failed_snapshot_dismiss_check($rbacDenied['db']->calls === [], 'RBAC denial occurs before DB work');
pos_runtime_failed_snapshot_dismiss_check($rbacDenied['actor']->reads === [], 'RBAC denial occurs before actor work');
pos_runtime_failed_snapshot_dismiss_check($rbacDenied['loader']->calls === [], 'RBAC denial occurs before service load');
pos_runtime_failed_snapshot_dismiss_check($rbacDenied['stockService']->calls === [], 'RBAC denial occurs before writer');

$selectorCases = [
    'stock audit' => [
        ['pos.stock.commit.audit.index:edit' => true],
        [['pos.stock.commit.audit.index', 'edit']],
        [['pos.stock.commit.audit.index', 'edit']],
    ],
    'cashier fallback' => [
        ['pos.stock.commit.audit.index:edit' => false, 'pos.cashier.index:edit' => true],
        [['pos.stock.commit.audit.index', 'edit'], ['pos.cashier.index', 'edit']],
        [['pos.cashier.index', 'edit']],
    ],
    'draft fallback' => [
        ['pos.stock.commit.audit.index:edit' => false, 'pos.cashier.index:edit' => false],
        [['pos.stock.commit.audit.index', 'edit'], ['pos.cashier.index', 'edit']],
        [['pos.order.draft.index', 'edit']],
    ],
];
foreach ($selectorCases as $label => [$canResults, $expectedCan, $expectedPermission]) {
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'GET',
        [],
        $validSession,
        '801',
        ['can_results' => $canResults, 'tripwire_db' => true, 'tripwire_loader' => true, 'tripwire_service' => true]
    );
    pos_runtime_failed_snapshot_dismiss_check($result['output']->status === 405, $label . ' reaches POST-only guard');
    pos_runtime_failed_snapshot_dismiss_check($result['controller']->canCalls === $expectedCan, $label . ' keeps can selector calls');
    pos_runtime_failed_snapshot_dismiss_check($result['controller']->permissionCalls === $expectedPermission, $label . ' keeps edit permission target');
    pos_runtime_failed_snapshot_dismiss_check($result['db']->calls === [], $label . ' reaches no DB work');
    pos_runtime_failed_snapshot_dismiss_check($result['actor']->reads === [], $label . ' reaches no actor work');
    pos_runtime_failed_snapshot_dismiss_check($result['loader']->calls === [], $label . ' reaches no service load');
}

foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        $method,
        [$browserHeader => $validToken],
        $validSession,
        '801',
        ['tripwire_db' => true, 'tripwire_loader' => true, 'tripwire_service' => true]
    );
    pos_runtime_failed_snapshot_dismiss_assert_guard_rejected($result, 405, $method, ['method'], []);
}

$csrfCases = [
    'missing header' => [[], $validSession, ['method', 'header:' . $canonicalHeader], []],
    'empty header' => [[$browserHeader => ''], $validSession, ['method', 'header:' . $canonicalHeader], []],
    'malformed header' => [[$browserHeader => 'not-a-token'], $validSession, ['method', 'header:' . $canonicalHeader], []],
    'missing session' => [[$browserHeader => $validToken], [], ['method', 'header:' . $canonicalHeader], [$sessionKey]],
    'malformed session' => [[$browserHeader => $validToken], [$sessionKey => 'bad'], ['method', 'header:' . $canonicalHeader], [$sessionKey]],
    'mismatched session' => [[$browserHeader => $otherToken], $validSession, ['method', 'header:' . $canonicalHeader], [$sessionKey]],
];
foreach ($csrfCases as $label => [$headers, $sessionValues, $expectedInput, $expectedReads]) {
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        $headers,
        $sessionValues,
        '801',
        ['tripwire_db' => true, 'tripwire_loader' => true, 'tripwire_service' => true]
    );
    pos_runtime_failed_snapshot_dismiss_assert_guard_rejected($result, 403, $label, $expectedInput, $expectedReads);
}

$invalidIds = [
    'zero int' => 0,
    'negative int' => -1,
    'zero string' => '0',
    'negative string' => '-1',
    'mixed text' => '801x',
    'decimal string' => '801.0',
    'leading zero' => '0801',
    'leading whitespace' => ' 801',
    'trailing whitespace' => '801 ',
    'plus sign' => '+801',
    'boolean' => true,
    'float' => 801.0,
    'array' => ['801'],
    'overflow' => (string)PHP_INT_MAX . '0',
];
foreach ($invalidIds as $label => $routeId) {
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        $routeId,
        ['tripwire_db' => true, 'tripwire_loader' => true, 'tripwire_service' => true]
    );
    pos_runtime_failed_snapshot_dismiss_assert_response($result, 422, 'invalid ID ' . $label);
    pos_runtime_failed_snapshot_dismiss_check(
        pos_runtime_failed_snapshot_dismiss_json($result) === [
            'ok' => false,
            'message' => 'Snapshot stock commit POS tidak valid untuk ditutup.',
        ],
        'invalid ID ' . $label . ' preserves validation error payload'
    );
    pos_runtime_failed_snapshot_dismiss_check($result['db']->calls === [], 'invalid ID ' . $label . ' performs no DB work');
    pos_runtime_failed_snapshot_dismiss_assert_pre_writer($result, 'invalid ID ' . $label);
}

$missingSnapshot = pos_runtime_failed_snapshot_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    ['snapshot' => []]
);
pos_runtime_failed_snapshot_dismiss_assert_response($missingSnapshot, 404, 'missing snapshot');
pos_runtime_failed_snapshot_dismiss_check(
    $missingSnapshot['db']->calls === pos_runtime_failed_snapshot_dismiss_lookup_calls(801),
    'missing snapshot preserves exact lookup and parsed binding'
);
pos_runtime_failed_snapshot_dismiss_check(
    pos_runtime_failed_snapshot_dismiss_json($missingSnapshot) === [
        'ok' => false,
        'message' => 'Snapshot stock commit POS tidak ditemukan.',
    ],
    'missing snapshot preserves 404 payload'
);
pos_runtime_failed_snapshot_dismiss_assert_pre_writer($missingSnapshot, 'missing snapshot');

foreach (['QUEUED', 'PROCESSING', 'COMMITTED', 'REVERSED', 'VOID'] as $status) {
    $snapshot = $validSnapshot;
    $snapshot['commit_status'] = $status;
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        ['snapshot' => $snapshot]
    );
    pos_runtime_failed_snapshot_dismiss_assert_response($result, 422, 'snapshot status ' . $status);
    pos_runtime_failed_snapshot_dismiss_check(
        pos_runtime_failed_snapshot_dismiss_json($result) === [
            'ok' => false,
            'message' => 'Hanya snapshot FAILED yang bisa ditutup dari audit ini.',
        ],
        'snapshot status ' . $status . ' preserves FAILED-only error'
    );
    pos_runtime_failed_snapshot_dismiss_check(
        $result['db']->calls === pos_runtime_failed_snapshot_dismiss_lookup_calls(801),
        'snapshot status ' . $status . ' stops after lookup'
    );
    pos_runtime_failed_snapshot_dismiss_assert_pre_writer($result, 'snapshot status ' . $status);
}

foreach ([0, -1] as $orderId) {
    $snapshot = $validSnapshot;
    $snapshot['order_id'] = $orderId;
    $snapshot['order_status'] = 'VOID';
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        ['snapshot' => $snapshot]
    );
    pos_runtime_failed_snapshot_dismiss_assert_response($result, 422, 'invalid order ID ' . $orderId);
    pos_runtime_failed_snapshot_dismiss_check(
        pos_runtime_failed_snapshot_dismiss_json($result) === ['ok' => false, 'message' => $stateError],
        'invalid order ID ' . $orderId . ' is rejected before VOID mapping with preserved state error'
    );
    pos_runtime_failed_snapshot_dismiss_assert_pre_writer($result, 'invalid order ID ' . $orderId);
}

$ineligibleStates = [
    'confirmed queued' => ['CONFIRMED', 'QUEUED'],
    'pending failed' => ['PENDING', 'FAILED'],
    'draft blank' => ['DRAFT', ''],
    'blank queued' => ['', 'QUEUED'],
];
foreach ($ineligibleStates as $label => [$orderStatus, $commitStatus]) {
    $snapshot = $validSnapshot;
    $snapshot['order_status'] = $orderStatus;
    $snapshot['stock_commit_status'] = $commitStatus;
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        ['snapshot' => $snapshot]
    );
    pos_runtime_failed_snapshot_dismiss_assert_response($result, 422, $label);
    pos_runtime_failed_snapshot_dismiss_check(
        pos_runtime_failed_snapshot_dismiss_json($result) === ['ok' => false, 'message' => $stateError],
        $label . ' preserves retry/rebuild state error'
    );
    pos_runtime_failed_snapshot_dismiss_assert_pre_writer($result, $label);
}

$markFailureSnapshot = $validSnapshot;
$markFailureSnapshot['order_status'] = 'CONFIRMED';
$markFailureSnapshot['stock_commit_status'] = 'POSTED';
$markFailure = pos_runtime_failed_snapshot_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    ['snapshot' => $markFailureSnapshot, 'mark_result' => ['ok' => false, 'message' => 'writer smoke failure']]
);
pos_runtime_failed_snapshot_dismiss_assert_response($markFailure, 422, 'mark_reversed failure');
pos_runtime_failed_snapshot_dismiss_check(
    $markFailure['loader']->calls === [['PosStockCommitService', 'posstockcommitservice']],
    'mark failure loads the legacy stock service only after state gates'
);
pos_runtime_failed_snapshot_dismiss_check(
    $markFailure['stockService']->calls === [['mark_reversed', [801, 'REVERSED']]],
    'mark failure uses server-derived REVERSED exactly once'
);
pos_runtime_failed_snapshot_dismiss_check(
    $markFailure['db']->calls === pos_runtime_failed_snapshot_dismiss_lookup_calls(801),
    'mark failure stops before table check or job cancellation'
);
pos_runtime_failed_snapshot_dismiss_check(
    pos_runtime_failed_snapshot_dismiss_json($markFailure) === ['ok' => false, 'message' => 'writer smoke failure'],
    'mark failure preserves writer error response'
);

$mappingCases = [
    'VOID precedence' => [' void ', 'POSTED', 'VOID'],
    'POSTED final' => ['CONFIRMED', ' posted ', 'REVERSED'],
    'REVERSED final' => ['PAID', 'REVERSED', 'REVERSED'],
    'NOT_REQUIRED final' => ['PENDING', 'not_required', 'REVERSED'],
];
foreach ($mappingCases as $label => [$orderStatus, $commitStatus, $expectedCloseAs]) {
    $snapshot = $validSnapshot;
    $snapshot['order_status'] = $orderStatus;
    $snapshot['stock_commit_status'] = $commitStatus;
    $snapshot['client_close_as'] = $expectedCloseAs === 'VOID' ? 'REVERSED' : 'VOID';
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        '801',
        ['snapshot' => $snapshot]
    );
    pos_runtime_failed_snapshot_dismiss_assert_response($result, 200, $label);
    pos_runtime_failed_snapshot_dismiss_check(
        $result['loader']->calls === [['PosStockCommitService', 'posstockcommitservice']],
        $label . ' loads only PosStockCommitService after gates'
    );
    pos_runtime_failed_snapshot_dismiss_check(
        $result['stockService']->calls === [['mark_reversed', [801, $expectedCloseAs]]],
        $label . ' passes server-derived closeAs to mark_reversed'
    );
    pos_runtime_failed_snapshot_dismiss_check(
        $result['session']->reads === [$sessionKey],
        $label . ' remains bound to scoped session token'
    );
    $expectedLookup = pos_runtime_failed_snapshot_dismiss_lookup_calls(801);
    pos_runtime_failed_snapshot_dismiss_check(
        array_slice($result['db']->calls, 0, count($expectedLookup)) === $expectedLookup,
        $label . ' preserves exact lookup before writer'
    );
    $cancelCalls = array_slice($result['db']->calls, count($expectedLookup));
    pos_runtime_failed_snapshot_dismiss_check(count($cancelCalls) === 5, $label . ' performs one filtered cancellation update');
    pos_runtime_failed_snapshot_dismiss_check($cancelCalls[0] === ['table_exists', ['pos_runtime_job']], $label . ' checks only runtime-job table');
    pos_runtime_failed_snapshot_dismiss_check($cancelCalls[1] === ['where', ['snapshot_id', 801]], $label . ' cancellation binds only the snapshot ID');
    pos_runtime_failed_snapshot_dismiss_check($cancelCalls[2] === ['where', ['job_type', 'ORDER_CONFIRM_STOCK_COMMIT']], $label . ' cancellation fixes job type');
    pos_runtime_failed_snapshot_dismiss_check(
        $cancelCalls[3] === ['where_in', ['status', ['QUEUED', 'PROCESSING', 'FAILED']]],
        $label . ' cancellation limits mutable statuses'
    );
    $updateData = $cancelCalls[4][1][1] ?? [];
    pos_runtime_failed_snapshot_dismiss_check(
        ($cancelCalls[4][0] ?? '') === 'update'
            && ($cancelCalls[4][1][0] ?? '') === 'pos_runtime_job'
            && ($updateData['status'] ?? '') === 'CANCELLED'
            && ($updateData['last_error'] ?? '') === $cancelReason
            && preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', (string)($updateData['finished_at'] ?? '')) === 1,
        $label . ' preserves cancellation payload and reason'
    );
    $body = pos_runtime_failed_snapshot_dismiss_json($result);
    pos_runtime_failed_snapshot_dismiss_check(
        $body === [
            'ok' => true,
            'message' => 'Snapshot gagal untuk order ORDER-SMOKE berhasil ditutup sebagai ' . $expectedCloseAs . '.',
            'snapshot_id' => 801,
            'order_id' => 501,
            'commit_status' => $expectedCloseAs,
        ],
        $label . ' preserves success response payload'
    );
    $markPosition = array_search('stock:mark_reversed:' . $expectedCloseAs, $result['trace']->events, true);
    $tablePosition = array_search('db:table_exists', $result['trace']->events, true);
    $updatePosition = array_search('db:update', $result['trace']->events, true);
    pos_runtime_failed_snapshot_dismiss_check(
        is_int($markPosition) && is_int($tablePosition) && is_int($updatePosition)
            && $markPosition < $tablePosition && $tablePosition < $updatePosition,
        $label . ' orders mark_reversed before matching-job cancellation'
    );
}

foreach ([$browserHeader, $canonicalHeader] as $providedHeader) {
    $result = pos_runtime_failed_snapshot_dismiss_invoke(
        'POST',
        [$providedHeader => $validToken],
        $validSession,
        801
    );
    pos_runtime_failed_snapshot_dismiss_assert_response($result, 200, 'valid header ' . $providedHeader);
    pos_runtime_failed_snapshot_dismiss_check(
        $result['stockService']->calls === [['mark_reversed', [801, 'VOID']]],
        'valid header ' . $providedHeader . ' reaches writer once'
    );
}

$tableMissing = pos_runtime_failed_snapshot_dismiss_invoke(
    'POST',
    [$browserHeader => $validToken],
    $validSession,
    '801',
    ['table_ready' => false]
);
pos_runtime_failed_snapshot_dismiss_assert_response($tableMissing, 200, 'runtime table absent');
pos_runtime_failed_snapshot_dismiss_check(
    $tableMissing['stockService']->calls === [['mark_reversed', [801, 'VOID']]],
    'runtime table absence does not change legacy snapshot writer'
);
pos_runtime_failed_snapshot_dismiss_check(
    $tableMissing['db']->calls === array_merge(
        pos_runtime_failed_snapshot_dismiss_lookup_calls(801),
        [['table_exists', ['pos_runtime_job']]]
    ),
    'runtime table absence skips cancellation update and preserves success'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS runtime failed-snapshot dismiss CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS runtime failed-snapshot dismiss CSRF/binding checks\n";
