<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke for
 * POST /pos/orders/runtime-jobs/delete-draft/{jobId}.
 *
 * The actual Pos controller is loaded without its constructor. In-memory fakes
 * prove rejected requests stop before DB/model work and accepted requests only
 * reach the existing delete_order_draft(order_id, actor) contract.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosRuntimeDeleteDraftSmokeInput
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

final class PosRuntimeDeleteDraftSmokeSession
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

final class PosRuntimeDeleteDraftSmokeOutput
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

final class PosRuntimeDeleteDraftSmokeDb
{
    public array $calls = [];

    public function __construct(private array $job)
    {
    }

    public function table_exists(string $table): bool
    {
        $this->calls[] = ['table_exists', [$table]];
        return $table === 'pos_runtime_job';
    }

    public function select(string $select): self
    {
        $this->calls[] = ['select', [$select]];
        return $this;
    }

    public function from(string $table): self
    {
        $this->calls[] = ['from', [$table]];
        return $this;
    }

    public function join(string $table, string $condition, string $type = ''): self
    {
        $this->calls[] = ['join', [$table, $condition, $type]];
        return $this;
    }

    public function where(string $key, $value): self
    {
        $this->calls[] = ['where', [$key, $value]];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->calls[] = ['limit', [$limit]];
        return $this;
    }

    public function get(): self
    {
        $this->calls[] = ['get', []];
        return $this;
    }

    public function row_array(): array
    {
        $this->calls[] = ['row_array', []];
        return $this->job;
    }
}

final class PosRuntimeDeleteDraftSmokeModel
{
    public array $calls = [];

    public function delete_order_draft(int $orderId, int $actorEmployeeId): array
    {
        $this->calls[] = ['delete_order_draft', [$orderId, $actorEmployeeId]];
        return ['ok' => true, 'id' => $orderId];
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public $Pos_model;
    public array $current_user = ['id' => 77, 'employee_id' => 4242];
    public array $canCalls = [];
    public array $permissionCalls = [];

    public function __construct()
    {
    }

    public function can($page, $action = 'view'): bool
    {
        $this->canCalls[] = [(string)$page, (string)$action];
        return true;
    }

    public function require_permission($page, $action = 'view'): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
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
$validJob = [
    'id' => 701,
    'status' => 'FAILED',
    'order_id' => 501,
    'order_no' => 'ORDER-SMOKE',
    'order_status' => 'DRAFT',
];

function pos_runtime_delete_draft_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_runtime_delete_draft_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_runtime_delete_draft_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_runtime_delete_draft_method_block(string $source, string $method): string
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

function pos_runtime_delete_draft_invoke(
    string $requestMethod,
    array $headers,
    array $sessionValues,
    $routeId,
    ?array $job = null
): array {
    $input = new PosRuntimeDeleteDraftSmokeInput($requestMethod, $headers);
    $session = new PosRuntimeDeleteDraftSmokeSession($sessionValues);
    $output = new PosRuntimeDeleteDraftSmokeOutput();
    $db = new PosRuntimeDeleteDraftSmokeDb($job ?? [
        'id' => 701,
        'status' => 'FAILED',
        'order_id' => 501,
        'order_no' => 'ORDER-SMOKE',
        'order_status' => 'DRAFT',
    ]);
    $model = new PosRuntimeDeleteDraftSmokeModel();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = $db;
    $controller->Pos_model = $model;

    $exception = null;
    try {
        $reflection->getMethod('order_runtime_failed_job_delete_draft')->invoke($controller, $routeId);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    return [$controller, $input, $session, $output, $db, $model, $exception];
}

function pos_runtime_delete_draft_assert_guard_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    [$controller, $input, $session, $output, $db, $model, $exception] = $result;
    pos_runtime_delete_draft_check($exception === null, $label . ' completes at the scoped guard');
    pos_runtime_delete_draft_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_delete_draft_check($output->contentType === 'application/json', $label . ' returns JSON');
    pos_runtime_delete_draft_check($input->events === $expectedInputEvents, $label . ' uses only the expected input boundary');
    pos_runtime_delete_draft_check($session->reads === $expectedSessionReads, $label . ' uses only the expected session boundary');
    pos_runtime_delete_draft_check($db->calls === [], $label . ' performs no DB work');
    pos_runtime_delete_draft_check($model->calls === [], $label . ' performs no model work');
    pos_runtime_delete_draft_check(
        $controller->permissionCalls === [['pos.stock.commit.audit.index', 'edit']],
        $label . ' runs after the existing edit RBAC check'
    );
}

$controllerSource = pos_runtime_delete_draft_source($root . '/application/controllers/Pos.php');
$viewSource = pos_runtime_delete_draft_source($root . '/application/views/pos/stock_commit_audit_index.php');
$routeSource = pos_runtime_delete_draft_source($root . '/application/config/routes.php');
$modelSource = pos_runtime_delete_draft_source($root . '/application/models/Pos_model.php');
$targetBlock = pos_runtime_delete_draft_method_block($controllerSource, 'order_runtime_failed_job_delete_draft');

$permissionPosition = strpos($targetBlock, "require_permission(\$pageCode, 'edit')");
$guardPosition = strpos($targetBlock, 'require_pos_transaction_csrf()');
$idPosition = strpos($targetBlock, '$jobId = $this->parse_positive_runtime_id($jobId);');
$dbPosition = strpos($targetBlock, "\$this->db->table_exists('pos_runtime_job')");
$modelPosition = strpos($targetBlock, '$this->Pos_model->delete_order_draft(');
pos_runtime_delete_draft_check($targetBlock !== '', 'target endpoint exists in the actual controller');
pos_runtime_delete_draft_check(
    $permissionPosition !== false
        && $guardPosition !== false
        && $idPosition !== false
        && $dbPosition !== false
        && $modelPosition !== false
        && $permissionPosition < $guardPosition
        && $guardPosition < $idPosition
        && $idPosition < $dbPosition
        && $dbPosition < $modelPosition,
    'target order is existing edit RBAC, scoped CSRF, strict positive job ID, then DB/model'
);
pos_runtime_delete_draft_check(
    strpos($targetBlock, "\$this->can('pos.stock.commit.audit.index', 'edit')") !== false
        && strpos($targetBlock, "\$this->can('pos.cashier.index', 'edit')") !== false
        && strpos($targetBlock, "'pos.order.draft.index'") !== false,
    'target keeps the existing edit permission selector and fallback matrix'
);
pos_runtime_delete_draft_check(
    strpos($targetBlock, "->where('j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT')") !== false,
    'target query remains bound to ORDER_CONFIRM_STOCK_COMMIT jobs'
);
pos_runtime_delete_draft_check(
    strpos($targetBlock, "in_array(\$jobStatus, ['FAILED', 'CANCELLED'], true)") !== false
        && strpos($targetBlock, "in_array(\$orderStatus, ['DRAFT', 'PENDING', 'CONFIRMED'], true)") !== false,
    'target keeps the existing job and order status conditions'
);
pos_runtime_delete_draft_check(
    substr_count($targetBlock, '$this->Pos_model->delete_order_draft(') === 1,
    'target keeps one existing Pos_model delete writer call site'
);

pos_runtime_delete_draft_check(
    strpos($routeSource, "\$route['pos/orders/runtime-jobs/delete-draft/(:num)'] = 'pos/order_runtime_failed_job_delete_draft/\$1';") !== false,
    'delete-draft route remains unchanged'
);
pos_runtime_delete_draft_check(
    strpos($routeSource, "\$route['pos/orders/runtime-jobs/dismiss/(:num)'] = 'pos/order_runtime_failed_job_dismiss/\$1';") !== false
        && strpos($routeSource, "\$route['pos/orders/runtime-snapshots/retry/(:num)'] = 'pos/order_runtime_failed_snapshot_retry/\$1';") !== false
        && strpos($routeSource, "\$route['pos/orders/runtime-snapshots/dismiss/(:num)'] = 'pos/order_runtime_failed_snapshot_dismiss/\$1';") !== false,
    'dismiss and snapshot endpoint routes remain unchanged'
);
pos_runtime_delete_draft_check(
    strpos($modelSource, 'public function delete_order_draft(int $orderId, int $actorEmployeeId): array') !== false,
    'existing Pos_model delete_order_draft contract remains unchanged'
);

$sharedStart = strpos($viewSource, 'async function postJson(');
$repairWrapperStart = strpos($viewSource, 'function postStockCommitAuditRepairJson(');
$sharedSource = $sharedStart === false
    ? ''
    : substr($viewSource, $sharedStart, ($repairWrapperStart === false ? strlen($viewSource) : $repairWrapperStart) - $sharedStart);
$scopedCaller = "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/delete-draft'); ?>/' + jobId, {})";
$sharedCaller = "postJson('<?php echo site_url('pos/orders/runtime-jobs/delete-draft'); ?>/' + jobId, {})";
pos_runtime_delete_draft_check(
    strpos($sharedSource, 'X-Pos-Transaction-CSRF') === false
        && strpos($sharedSource, 'posTransactionCsrfToken') === false,
    'shared postJson remains neutral and does not add the POS transaction header'
);
pos_runtime_delete_draft_check(
    substr_count($viewSource, $scopedCaller) === 1
        && substr_count($viewSource, $sharedCaller) === 0,
    'active delete-draft caller uses the scoped wrapper exactly once'
);

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    pos_runtime_delete_draft_assert_guard_rejected(
        pos_runtime_delete_draft_invoke(
            $method,
            [$browserHeader => $validToken],
            $validSession,
            '701',
            $validJob
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
    pos_runtime_delete_draft_assert_guard_rejected(
        pos_runtime_delete_draft_invoke('POST', $headers, $sessionValues, '701', $validJob),
        403,
        $label,
        ['method', 'header:' . $canonicalHeader],
        $expectedSessionReads
    );
}

foreach (
    [
        'zero' => 0,
        'negative' => -1,
        'mixed' => '1abc',
        'float' => 1.5,
    ] as $label => $invalidId
) {
    $result = pos_runtime_delete_draft_invoke(
        'POST',
        [$browserHeader => $validToken],
        $validSession,
        $invalidId,
        $validJob
    );
    [$controller, $input, $session, $output, $db, $model, $exception] = $result;
    pos_runtime_delete_draft_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'response displayed',
        'invalid ID ' . $label . ' reaches only its JSON validation response'
    );
    pos_runtime_delete_draft_check($output->status === 422, 'invalid ID ' . $label . ' returns HTTP 422');
    pos_runtime_delete_draft_check(
        $input->events === ['method', 'header:' . $canonicalHeader],
        'invalid ID ' . $label . ' is parsed after scoped CSRF without reading a body'
    );
    pos_runtime_delete_draft_check($session->reads === [$sessionKey], 'invalid ID ' . $label . ' stays session-bound');
    pos_runtime_delete_draft_check($db->calls === [], 'invalid ID ' . $label . ' performs no DB work');
    pos_runtime_delete_draft_check($model->calls === [], 'invalid ID ' . $label . ' performs no model work');
    pos_runtime_delete_draft_check(
        $controller->permissionCalls === [['pos.stock.commit.audit.index', 'edit']],
        'invalid ID ' . $label . ' runs after edit RBAC'
    );
}

foreach (
    [
        'browser header' => [$browserHeader => $validToken],
        'canonical FastCGI header' => [$canonicalHeader => $validToken],
    ] as $label => $headers
) {
    $result = pos_runtime_delete_draft_invoke('POST', $headers, $validSession, '701', $validJob);
    [$controller, $input, $session, $output, $db, $model, $exception] = $result;
    pos_runtime_delete_draft_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'response displayed',
        'valid ' . $label . ' reaches the success response'
    );
    pos_runtime_delete_draft_check($output->status === 200, 'valid ' . $label . ' returns HTTP 200');
    pos_runtime_delete_draft_check(
        $input->events === ['method', 'header:' . $canonicalHeader],
        'valid ' . $label . ' uses only the scoped method/header input'
    );
    pos_runtime_delete_draft_check($session->reads === [$sessionKey], 'valid ' . $label . ' stays session-bound');
    pos_runtime_delete_draft_check(
        $model->calls === [['delete_order_draft', [501, 4242]]],
        'valid ' . $label . ' reaches delete_order_draft(order_id, actor) exactly once'
    );
    pos_runtime_delete_draft_check(
        $controller->permissionCalls === [['pos.stock.commit.audit.index', 'edit']],
        'valid ' . $label . ' keeps the existing edit RBAC permission'
    );
    $whereCalls = array_values(array_filter($db->calls, static fn(array $call): bool => $call[0] === 'where'));
    pos_runtime_delete_draft_check(
        $whereCalls === [
            ['where', ['j.id', 701]],
            ['where', ['j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT']],
        ],
        'valid ' . $label . ' binds the positive job ID and fixed job type in the lookup'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS runtime failed-job delete-draft CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS runtime failed-job delete-draft CSRF/binding checks\n";
