<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke test for the three POS runtime-job mutation
 * endpoints: retry/{jobId}, process-all, and retry-failed-all.
 *
 * Pos.php is the actual controller. Only its parent, input, session, output,
 * loader, and runtime service are replaced with small in-memory fakes.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosRuntimeJobMutationCsrfSmokeInput
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

final class PosRuntimeJobMutationCsrfSmokeSession
{
    public array $reads = [];
    public array $writes = [];

    public function __construct(private array $values)
    {
    }

    public function userdata(string $key)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata(string $key, string $value): void
    {
        $this->writes[] = $key;
        $this->values[$key] = $value;
    }
}

final class PosRuntimeJobMutationCsrfSmokeOutput
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
        // Pos's normal JSON helpers exit after display. Throwing lets this
        // smoke test inspect a downstream validation response in-process.
        throw new RuntimeException('response displayed');
    }
}

final class PosRuntimeJobMutationCsrfSmokeLoader
{
    public array $libraryCalls = [];

    public function library(string $name, $params = null, $objectName = null): void
    {
        $this->libraryCalls[] = [
            $name,
            $objectName === null ? null : (string)$objectName,
        ];
    }
}

final class PosRuntimeJobMutationCsrfSmokeDb
{
    public function __call(string $name, array $arguments)
    {
        throw new RuntimeException('database dependency reached: ' . $name);
    }
}

final class PosRuntimeJobMutationCsrfSmokeService
{
    public array $calls = [];
    public array $arguments = [];

    public function __construct(
        private string $throwAt = '',
        private array $failedRows = []
    ) {
    }

    public function retry_job(int $jobId, int $actorEmployeeId = 0): array
    {
        $this->calls[] = 'retry_job';
        $this->arguments[] = [$jobId, $actorEmployeeId];
        if ($this->throwAt === 'retry_job') {
            throw new RuntimeException('retry_job reached');
        }
        return [
            'ok' => true,
            'job' => ['id' => $jobId, 'order_id' => 501],
        ];
    }

    public function process_pending_jobs(array $options = []): array
    {
        $this->calls[] = 'process_pending_jobs';
        $this->arguments[] = [$options];
        if ($this->throwAt === 'process_pending_jobs') {
            throw new RuntimeException('process_pending_jobs reached');
        }
        return [
            'ok' => true,
            'processed_count' => 1,
            'success_count' => 1,
            'failed_count' => 0,
            'jobs' => [],
        ];
    }

    public function failed_jobs(array $filters = []): array
    {
        $this->calls[] = 'failed_jobs';
        $this->arguments[] = [$filters];
        if ($this->throwAt === 'failed_jobs') {
            throw new RuntimeException('failed_jobs reached');
        }
        return ['ok' => true, 'rows' => $this->failedRows];
    }

    public function latest_job_for_order(int $orderId): array
    {
        $this->calls[] = 'latest_job_for_order';
        $this->arguments[] = [$orderId];
        return ['ok' => true, 'job' => ['id' => 701, 'order_id' => $orderId]];
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
    public array $permissionCalls = [];

    public function __construct()
    {
    }

    public function can($page, $action = 'view'): bool
    {
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

function pos_runtime_job_mutation_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_runtime_job_mutation_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_runtime_job_mutation_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_runtime_job_mutation_method_block(string $source, string $method): string
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

function pos_runtime_job_mutation_controller(
    string $requestMethod,
    array $headers,
    array $payload,
    string $throwAt = '',
    array $failedRows = [],
    ?array $sessionValues = null
): array {
    $input = new PosRuntimeJobMutationCsrfSmokeInput($requestMethod, $headers, $payload);
    $session = new PosRuntimeJobMutationCsrfSmokeSession($sessionValues ?? [
        'pos_transaction_csrf' => str_repeat('a', 64),
    ]);
    $output = new PosRuntimeJobMutationCsrfSmokeOutput();
    $loader = new PosRuntimeJobMutationCsrfSmokeLoader();
    $service = new PosRuntimeJobMutationCsrfSmokeService($throwAt, $failedRows);
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = new PosRuntimeJobMutationCsrfSmokeDb();
    $controller->load = $loader;
    $controller->posruntimejobservice = $service;

    return [$controller, $input, $session, $output, $loader, $service, $reflection];
}

function pos_runtime_job_mutation_invoke(
    string $action,
    string $requestMethod,
    array $headers,
    array $payload,
    string $routeId = '701',
    string $throwAt = '',
    array $failedRows = [],
    ?array $sessionValues = null
): array {
    [$controller, $input, $session, $output, $loader, $service, $reflection] = pos_runtime_job_mutation_controller(
        $requestMethod,
        $headers,
        $payload,
        $throwAt,
        $failedRows,
        $sessionValues
    );
    $exception = null;
    try {
        $arguments = $action === 'order_runtime_job_retry' ? [$routeId] : [];
        $reflection->getMethod($action)->invokeArgs($controller, $arguments);
    } catch (Throwable $caught) {
        $exception = $caught;
    }
    return [$controller, $input, $session, $output, $loader, $service, $exception];
}

function pos_runtime_job_mutation_assert_guard_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    [$controller, $input, $session, $output, $loader, $service, $exception] = $result;
    pos_runtime_job_mutation_check($exception === null, $label . ' completes without exception');
    pos_runtime_job_mutation_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_runtime_job_mutation_check($output->contentType === 'application/json', $label . ' returns JSON');
    pos_runtime_job_mutation_check($input->events === $expectedInputEvents, $label . ' stops at the scoped guard input boundary');
    pos_runtime_job_mutation_check($session->reads === $expectedSessionReads, $label . ' reads only the required session token');
    pos_runtime_job_mutation_check($loader->libraryCalls === [], $label . ' does not load the runtime service');
    pos_runtime_job_mutation_check($service->calls === [], $label . ' does not call the runtime service');
    pos_runtime_job_mutation_check($controller->permissionCalls === [['pos.stock.live.index', 'edit']], $label . ' runs after edit RBAC');
}

$controllerSource = pos_runtime_job_mutation_source($root . '/application/controllers/Pos.php');
$routesSource = pos_runtime_job_mutation_source($root . '/application/config/routes.php');
$serviceSource = pos_runtime_job_mutation_source($root . '/application/libraries/PosRuntimeJobService.php');
$configSource = pos_runtime_job_mutation_source($root . '/application/config/config.php');

$actions = [
    'order_runtime_job_retry' => [
        'payload' => ['ignored' => 'body', 'job_id' => 701],
        'serviceMarker' => 'retry_job(',
    ],
    'order_runtime_jobs_process_all' => [
        'payload' => ['outlet_id' => 17, 'limit' => 37],
        'serviceMarker' => 'process_pending_jobs(',
    ],
    'order_runtime_failed_jobs_retry_all' => [
        'payload' => ['outlet_id' => 17, 'limit' => 3, 'q' => 'needle'],
        'serviceMarker' => 'failed_jobs(',
    ],
];

foreach ($actions as $action => $definition) {
    $block = pos_runtime_job_mutation_method_block($controllerSource, $action);
    $permissionPosition = strpos($block, "require_permission(\$pageCode, 'edit')");
    $guardPosition = strpos($block, 'require_pos_transaction_csrf()');
    $payloadPosition = strpos($block, '$this->request_payload()');
    $idPosition = strpos($block, '$this->parse_positive_runtime_id(');
    $actorPosition = strpos($block, '$this->current_actor_employee_id()');
    $loadPosition = strpos($block, '$this->load->library(\'PosRuntimeJobService\');');
    $servicePosition = strpos($block, $definition['serviceMarker']);

    pos_runtime_job_mutation_check($block !== '', $action . ' exists in the actual controller');
    pos_runtime_job_mutation_check(
        $permissionPosition !== false
            && $guardPosition !== false
            && $permissionPosition < $guardPosition,
        $action . ' keeps edit RBAC before scoped CSRF'
    );
    foreach (
        [
            'request payload' => $payloadPosition,
            'positive ID validation' => $idPosition,
            'actor resolution' => $actorPosition,
            'service loading' => $loadPosition,
            'service dependency' => $servicePosition,
        ] as $dependencyLabel => $dependencyPosition
    ) {
        if ($dependencyPosition === false) {
            continue;
        }
        pos_runtime_job_mutation_check(
            $guardPosition < $dependencyPosition,
            $action . ' checks scoped CSRF before ' . $dependencyLabel
        );
    }
}

$retryBlock = pos_runtime_job_mutation_method_block($controllerSource, 'order_runtime_job_retry');
$retryGuard = strpos($retryBlock, 'require_pos_transaction_csrf()');
$retryId = strpos($retryBlock, '$jobId = $this->parse_positive_runtime_id($jobId);');
$retryService = strpos($retryBlock, 'retry_job($jobId,');
pos_runtime_job_mutation_check(
    $retryId !== false && $retryService !== false && $retryGuard !== false && $retryGuard < $retryId && $retryId < $retryService,
    'retry validates a positive route job ID after CSRF and before the service'
);

$retryAllBlock = pos_runtime_job_mutation_method_block($controllerSource, 'order_runtime_failed_jobs_retry_all');
$retryAllGuard = strpos($retryAllBlock, 'require_pos_transaction_csrf()');
$retryAllTimeLimit = strpos($retryAllBlock, '@set_time_limit(0);');
pos_runtime_job_mutation_check(
    $retryAllGuard !== false && $retryAllTimeLimit !== false && $retryAllGuard < $retryAllTimeLimit,
    'retry-failed-all applies set_time_limit only after RBAC and scoped CSRF'
);

pos_runtime_job_mutation_check(
    strpos($routesSource, "\$route['pos/orders/runtime-jobs/retry/(:num)'] = 'pos/order_runtime_job_retry/\$1';") !== false
        && strpos($routesSource, "\$route['pos/orders/runtime-jobs/process-all'] = 'pos/order_runtime_jobs_process_all';") !== false
        && strpos($routesSource, "\$route['pos/orders/runtime-jobs/retry-failed-all'] = 'pos/order_runtime_failed_jobs_retry_all';") !== false,
    'runtime-job mutation routes remain unchanged'
);
pos_runtime_job_mutation_check(
    strpos($serviceSource, 'public function retry_job(int $jobId, int $actorEmployeeId = 0): array') !== false
        && strpos($serviceSource, 'public function process_pending_jobs(array $options = []): array') !== false
        && strpos($serviceSource, 'public function failed_jobs(array $filters = []): array') !== false,
    'existing runtime service contracts remain unchanged'
);
pos_runtime_job_mutation_check(
    preg_match('/\$config\[\'csrf_protection\'\]\s*=\s*FALSE\s*;/i', $configSource) === 1,
    'global CSRF protection remains unchanged and disabled'
);

$validBrowserHeaders = [$browserHeader => $validToken];
$validCanonicalHeaders = [$canonicalHeader => $validToken];
$validSessionReads = [$sessionKey];

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    foreach (array_keys($actions) as $action) {
        pos_runtime_job_mutation_assert_guard_rejected(
            pos_runtime_job_mutation_invoke(
                $action,
                $method,
                $validBrowserHeaders,
                ['outlet_id' => 17, 'limit' => 37, 'job_id' => 701]
            ),
            405,
            $action . ' ' . $method,
            ['method'],
            []
        );
    }
}

$invalidCsrfCases = [
    'missing header' => [[], [$sessionKey => $validToken], []],
    'malformed header' => [[$browserHeader => 'not-a-token'], [$sessionKey => $validToken], []],
    'absent session' => [$validBrowserHeaders, [], $validSessionReads],
    'cross-session token' => [[$browserHeader => str_repeat('b', 64)], [$sessionKey => $validToken], $validSessionReads],
    'mismatched token' => [[$browserHeader => str_repeat('c', 64)], [$sessionKey => $validToken], $validSessionReads],
];
foreach ($invalidCsrfCases as $label => [$headers, $sessionValues, $expectedSessionReads]) {
    foreach (array_keys($actions) as $action) {
        $result = pos_runtime_job_mutation_invoke(
            $action,
            'POST',
            $headers,
            ['outlet_id' => 17, 'limit' => 37, 'job_id' => 701],
            '701',
            '',
            [],
            $sessionValues
        );
        pos_runtime_job_mutation_assert_guard_rejected(
            $result,
            403,
            $action . ' POST ' . $label,
            ['method', 'header:' . $canonicalHeader],
            $expectedSessionReads
        );
    }
}

foreach (['0', '-1', '1abc', 1.5] as $invalidJobId) {
    $result = pos_runtime_job_mutation_invoke(
        'order_runtime_job_retry',
        'POST',
        $validBrowserHeaders,
        ['job_id' => 701, 'payload_should_not_matter' => true],
        (string)$invalidJobId
    );
    [$controller, $input, $session, $output, $loader, $service, $exception] = $result;
    pos_runtime_job_mutation_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'response displayed',
        'retry invalid job ID ' . (string)$invalidJobId . ' reaches only its JSON validation response'
    );
    pos_runtime_job_mutation_check($output->status === 422, 'retry invalid job ID ' . (string)$invalidJobId . ' returns HTTP 422');
    pos_runtime_job_mutation_check($input->events === ['method', 'header:' . $canonicalHeader], 'retry invalid job ID stops before body');
    pos_runtime_job_mutation_check($session->reads === $validSessionReads, 'retry invalid job ID uses only the session token');
    pos_runtime_job_mutation_check($loader->libraryCalls === [], 'retry invalid job ID does not load the service');
    pos_runtime_job_mutation_check($service->calls === [], 'retry invalid job ID does not call the service');
}

$failedRows = [['id' => 701, 'order_no' => 'ORDER-SMOKE']];
foreach (
    [
        'browser header' => $validBrowserHeaders,
        'canonical header' => $validCanonicalHeaders,
    ] as $headerLabel => $headers
) {
    $result = pos_runtime_job_mutation_invoke(
        'order_runtime_job_retry',
        'POST',
        $headers,
        ['ignored' => 'body', 'job_id' => 999],
        '701',
        'process_pending_jobs'
    );
    [$controller, $input, $session, $output, $loader, $service, $exception] = $result;
    pos_runtime_job_mutation_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'process_pending_jobs reached',
        'valid retry with ' . $headerLabel . ' reaches processing dependency'
    );
    pos_runtime_job_mutation_check($input->events === ['method', 'header:' . $canonicalHeader], 'valid retry keeps its route-only ID input after CSRF');
    pos_runtime_job_mutation_check($session->reads === $validSessionReads, 'valid retry stays session-bound');
    pos_runtime_job_mutation_check(
        $loader->libraryCalls === [['PosRuntimeJobService', null], ['PosRuntimeJobService', null]],
        'valid retry loads the existing service for retry and processing'
    );
    pos_runtime_job_mutation_check($service->calls === ['retry_job', 'process_pending_jobs'], 'valid retry calls the expected service methods');
    pos_runtime_job_mutation_check(
        $service->arguments === [
            [701, 4242],
            [['limit' => 1, 'order_id' => 501, 'job_id' => 701]],
        ],
        'valid retry preserves the positive job ID, actor, and processing scope'
    );
}

foreach (
    [
        'browser header' => $validBrowserHeaders,
        'canonical header' => $validCanonicalHeaders,
    ] as $headerLabel => $headers
) {
    $result = pos_runtime_job_mutation_invoke(
        'order_runtime_jobs_process_all',
        'POST',
        $headers,
        ['outlet_id' => 17, 'limit' => 37],
        '701',
        'process_pending_jobs'
    );
    [$controller, $input, $session, $output, $loader, $service, $exception] = $result;
    pos_runtime_job_mutation_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'process_pending_jobs reached',
        'valid process-all with ' . $headerLabel . ' reaches the processing service'
    );
    pos_runtime_job_mutation_check($input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream'], 'valid process-all parses body after CSRF');
    pos_runtime_job_mutation_check($session->reads === $validSessionReads, 'valid process-all stays session-bound');
    pos_runtime_job_mutation_check($loader->libraryCalls === [['PosRuntimeJobService', null]], 'valid process-all loads the existing service once');
    pos_runtime_job_mutation_check($service->calls === ['process_pending_jobs'], 'valid process-all calls only process_pending_jobs');
    pos_runtime_job_mutation_check(
        $service->arguments === [[['limit' => 37, 'outlet_id' => 17]]],
        'valid process-all preserves payload limit and outlet scope'
    );
}

foreach (
    [
        'browser header' => $validBrowserHeaders,
        'canonical header' => $validCanonicalHeaders,
    ] as $headerLabel => $headers
) {
    $result = pos_runtime_job_mutation_invoke(
        'order_runtime_failed_jobs_retry_all',
        'POST',
        $headers,
        ['outlet_id' => 17, 'limit' => 3, 'q' => 'needle'],
        '701',
        'process_pending_jobs',
        $failedRows
    );
    [$controller, $input, $session, $output, $loader, $service, $exception] = $result;
    pos_runtime_job_mutation_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'process_pending_jobs reached',
        'valid retry-failed-all with ' . $headerLabel . ' reaches the processing service'
    );
    pos_runtime_job_mutation_check($input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream'], 'valid retry-failed-all parses body after CSRF');
    pos_runtime_job_mutation_check($session->reads === $validSessionReads, 'valid retry-failed-all stays session-bound');
    pos_runtime_job_mutation_check(
        $loader->libraryCalls === [['PosRuntimeJobService', null], ['PosRuntimeJobService', null]],
        'valid retry-failed-all loads the existing service for batch and processing'
    );
    pos_runtime_job_mutation_check(
        $service->calls === ['failed_jobs', 'retry_job', 'process_pending_jobs'],
        'valid retry-failed-all calls failed lookup, retry, and processing in order'
    );
    pos_runtime_job_mutation_check(
        $service->arguments === [
            [['limit' => 3, 'outlet_id' => 17, 'q' => 'needle']],
            [701, 4242],
            [['limit' => 1, 'order_id' => 501, 'job_id' => 701]],
        ],
        'valid retry-failed-all preserves payload, actor, and job processing scope'
    );
}

$stockCommitControllerBlock = pos_runtime_job_mutation_method_block($controllerSource, 'stock_commit_audit');
$purchaseSource = pos_runtime_job_mutation_source($root . '/application/controllers/Purchase.php');
$purchaseControllerBlock = pos_runtime_job_mutation_method_block($purchaseSource, 'stock_division_reconcile_index');
pos_runtime_job_mutation_check(
    strpos($stockCommitControllerBlock, '$posTransactionCsrfToken = $this->pos_transaction_csrf();') !== false
        && strpos($stockCommitControllerBlock, "'pos_transaction_csrf_token' => \$posTransactionCsrfToken") !== false,
    'POS stock-commit audit renders the shared POS transaction token'
);
pos_runtime_job_mutation_check(
    strpos($purchaseSource, "private const POS_TRANSACTION_CSRF_SESSION_KEY = 'pos_transaction_csrf';") !== false
        && strpos($purchaseControllerBlock, '$posTransactionCsrfToken = $this->pos_transaction_csrf();') !== false
        && strpos($purchaseControllerBlock, "'pos_transaction_csrf_token' => \$posTransactionCsrfToken") !== false
        && strpos($purchaseSource, 'bin2hex(random_bytes(32))') !== false,
    'Purchase reconcile renders a session-bound POS transaction token with the same contract'
);

$viewContracts = [
    'stock-live' => $root . '/application/views/pos/stock_live_index.php',
    'stock-commit-audit' => $root . '/application/views/pos/stock_commit_audit_index.php',
    'purchase-reconcile' => $root . '/application/views/purchase/stock_division_reconcile_index.php',
];
$runtimeMarkers = [
    "retry" => "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/'",
    "process-all" => "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/process-all'); ?>',",
    "retry-failed-all" => "postPosTransactionJson('<?php echo site_url('pos/orders/runtime-jobs/retry-failed-all'); ?>',",
];
foreach ($viewContracts as $viewName => $viewPath) {
    $viewSource = pos_runtime_job_mutation_source($viewPath);
    $wrapperStart = strpos($viewSource, 'async function postPosTransactionJson(');
    $sharedStart = strpos($viewSource, 'async function postJson(');
    $sharedSource = $sharedStart === false
        ? ''
        : substr($viewSource, $sharedStart, ($wrapperStart === false ? strlen($viewSource) : $wrapperStart) - $sharedStart);
    $wrapperSource = $wrapperStart === false ? '' : substr($viewSource, $wrapperStart, 1800);

    pos_runtime_job_mutation_check(strpos($viewSource, 'pos_transaction_csrf_token') !== false, $viewName . ' renders a POS transaction token');
    pos_runtime_job_mutation_check($wrapperStart !== false, $viewName . ' defines the separate POS transaction wrapper');
    pos_runtime_job_mutation_check(strpos($wrapperSource, 'X-Pos-Transaction-CSRF') !== false, $viewName . ' wrapper carries the scoped POS header');
    pos_runtime_job_mutation_check(strpos($sharedSource, 'X-Pos-Transaction-CSRF') === false, $viewName . ' shared postJson stays free of the POS transaction header');

    if ($viewName === 'purchase-reconcile') {
        pos_runtime_job_mutation_check(substr_count($viewSource, $runtimeMarkers['retry']) === 1, $viewName . ' uses the scoped wrapper for retry exactly once');
        pos_runtime_job_mutation_check(substr_count($viewSource, "postJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/") === 0, $viewName . ' has no shared retry caller');
    } else {
        foreach (['retry', 'process-all', $viewName === 'stock-commit-audit' ? 'retry-failed-all' : null] as $markerKey) {
            if ($markerKey === null) {
                continue;
            }
            pos_runtime_job_mutation_check(substr_count($viewSource, $runtimeMarkers[$markerKey]) === 1, $viewName . ' uses the scoped wrapper for ' . $markerKey . ' exactly once');
            pos_runtime_job_mutation_check(
                substr_count($viewSource, str_replace('postPosTransactionJson', 'postJson', $runtimeMarkers[$markerKey])) === 0,
                $viewName . ' has no shared postJson caller for ' . $markerKey
            );
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS runtime-job mutation CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS runtime-job mutation CSRF/binding checks\n";
