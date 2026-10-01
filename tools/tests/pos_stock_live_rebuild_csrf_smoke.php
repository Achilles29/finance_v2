<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke test for the two manual stock-live rebuild writers.
 *
 * The real Pos controller is loaded with small in-memory fakes. Rejected requests
 * must stop after RBAC and the existing session-bound transaction CSRF guard;
 * accepted requests reach only a recording rebuild-service stub.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosStockLiveRebuildCsrfSmokeInput
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

final class PosStockLiveRebuildCsrfSmokeSession
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

final class PosStockLiveRebuildCsrfSmokeOutput
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

final class PosStockLiveRebuildCsrfSmokeLoader
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

final class PosStockLiveRebuildCsrfSmokeService
{
    public array $calls = [];
    public array $arguments = [];

    public function rebuild_all_products(int $outletId, array $options, array $context): array
    {
        $this->calls[] = 'rebuild_all_products';
        $this->arguments[] = [$outletId, $options, $context];
        throw new RuntimeException('stock service reached: rebuild_all_products');
    }

    public function rebuild_product(int $outletId, int $productId, array $context): array
    {
        $this->calls[] = 'rebuild_product';
        $this->arguments[] = [$outletId, $productId, $context];
        throw new RuntimeException('stock service reached: rebuild_product');
    }
}

$posStockLiveRebuildCsrfSmokeForbiddenAccesses = [];

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $load;
    public $posavailabilityrebuildservice;
    public array $current_user = ['id' => 77, 'employee_id' => 4242];
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

    public function __get(string $name)
    {
        global $posStockLiveRebuildCsrfSmokeForbiddenAccesses;
        $posStockLiveRebuildCsrfSmokeForbiddenAccesses[] = $name;
        throw new RuntimeException('unexpected controller dependency access: ' . $name);
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];

function pos_stock_live_rebuild_csrf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_stock_live_rebuild_csrf_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_stock_live_rebuild_csrf_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_stock_live_rebuild_csrf_method_block(string $source, string $method): string
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

function pos_stock_live_rebuild_csrf_controller(
    string $requestMethod,
    array $headers,
    array $payload,
    array $sessionValues
): array {
    global $posStockLiveRebuildCsrfSmokeForbiddenAccesses;
    $posStockLiveRebuildCsrfSmokeForbiddenAccesses = [];

    $input = new PosStockLiveRebuildCsrfSmokeInput($requestMethod, $headers, $payload);
    $session = new PosStockLiveRebuildCsrfSmokeSession($sessionValues);
    $output = new PosStockLiveRebuildCsrfSmokeOutput();
    $loader = new PosStockLiveRebuildCsrfSmokeLoader();
    $service = new PosStockLiveRebuildCsrfSmokeService();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->load = $loader;
    $controller->posavailabilityrebuildservice = $service;

    return [$controller, $input, $session, $output, $loader, $service, $reflection];
}

function pos_stock_live_rebuild_csrf_invoke(
    string $action,
    string $requestMethod,
    array $headers,
    array $payload,
    array $sessionValues
): array {
    [$controller, $input, $session, $output, $loader, $service, $reflection] = pos_stock_live_rebuild_csrf_controller(
        $requestMethod,
        $headers,
        $payload,
        $sessionValues
    );

    $exception = null;
    try {
        $reflection->getMethod($action)->invoke($controller);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    global $posStockLiveRebuildCsrfSmokeForbiddenAccesses;
    return [
        $controller,
        $input,
        $session,
        $output,
        $loader,
        $service,
        $posStockLiveRebuildCsrfSmokeForbiddenAccesses,
        $exception,
    ];
}

function pos_stock_live_rebuild_csrf_assert_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    [$controller, $input, $session, $output, $loader, $service, $forbidden, $exception] = $result;
    pos_stock_live_rebuild_csrf_check($exception === null, $label . ' completes at the scoped guard');
    pos_stock_live_rebuild_csrf_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_stock_live_rebuild_csrf_check($output->contentType === 'application/json', $label . ' returns JSON');
    $body = json_decode($output->body, true);
    pos_stock_live_rebuild_csrf_check(
        is_array($body) && ($body['ok'] ?? null) === false,
        $label . ' returns a JSON rejection body'
    );
    pos_stock_live_rebuild_csrf_check($controller->permissionCalls === [['pos.stock.live.index', 'edit']], $label . ' runs RBAC first');
    pos_stock_live_rebuild_csrf_check($input->events === $expectedInputEvents, $label . ' uses the expected input sequence');
    pos_stock_live_rebuild_csrf_check($session->reads === $expectedSessionReads, $label . ' uses the expected session reads');
    pos_stock_live_rebuild_csrf_check($loader->libraryCalls === [], $label . ' does not load the rebuild service');
    pos_stock_live_rebuild_csrf_check($service->calls === [], $label . ' does not call the rebuild writer');
    pos_stock_live_rebuild_csrf_check($forbidden === [], $label . ' reaches no unexpected dependency');
}

$root = dirname(__DIR__, 2);
$controllerSource = pos_stock_live_rebuild_csrf_source($root . '/application/controllers/Pos.php');
$viewSource = pos_stock_live_rebuild_csrf_source($root . '/application/views/pos/stock_live_index.php');
$routeSource = pos_stock_live_rebuild_csrf_source($root . '/application/config/routes.php');
$sessionKey = 'pos_transaction_csrf';
$browserHeader = 'X-Pos-Transaction-CSRF';
$canonicalHeader = 'X-Pos-Transaction-Csrf';
$validToken = str_repeat('a', 64);
$crossSessionToken = str_repeat('b', 64);
$mismatchedToken = str_repeat('c', 64);
$validSession = [$sessionKey => $validToken];
$validPayloads = [
    'stock_live_rebuild_all' => ['outlet_id' => 17, 'division_id' => 8],
    'stock_live_rebuild' => ['outlet_id' => 17, 'product_id' => 99],
];

pos_stock_live_rebuild_csrf_check(
    strpos($routeSource, "\$route['pos/stock-live/rebuild'] = 'pos/stock_live_rebuild';") !== false
        && strpos($routeSource, "\$route['pos/stock-live/rebuild-all'] = 'pos/stock_live_rebuild_all';") !== false,
    'stock-live rebuild routes remain unchanged'
);
pos_stock_live_rebuild_csrf_check(
    strpos($controllerSource, "private const POS_TRANSACTION_CSRF_SESSION_KEY = 'pos_transaction_csrf';") !== false
        && strpos($controllerSource, "private const POS_TRANSACTION_CSRF_CI_HEADER = 'X-Pos-Transaction-Csrf';") !== false,
    'the writers use the existing transaction CSRF contract'
);

$writerMarkers = [
    'stock_live_rebuild_all' => 'rebuild_all_products(',
    'stock_live_rebuild' => 'rebuild_product(',
];
foreach ($writerMarkers as $action => $writerMarker) {
    $block = pos_stock_live_rebuild_csrf_method_block($controllerSource, $action);
    $permissionPosition = strpos($block, "\$this->require_permission(\$pageCode, 'edit');");
    $guardPosition = strpos($block, '$this->require_pos_transaction_csrf()');
    $payloadPosition = strpos($block, '$this->request_payload()');
    $actorPosition = strpos($block, '$this->current_actor_employee_id()');
    $loadPosition = strpos($block, "\$this->load->library('PosAvailabilityRebuildService');");
    $writerPosition = strpos($block, $writerMarker);

    pos_stock_live_rebuild_csrf_check($block !== '', $action . ' exists in the actual controller');
    pos_stock_live_rebuild_csrf_check(
        $permissionPosition !== false
            && $guardPosition !== false
            && $permissionPosition < $guardPosition,
        $action . ' keeps RBAC before scoped CSRF'
    );
    pos_stock_live_rebuild_csrf_check(
        $guardPosition !== false
            && $payloadPosition !== false
            && $guardPosition < $payloadPosition,
        $action . ' checks scoped CSRF before request_payload()'
    );
    pos_stock_live_rebuild_csrf_check(
        $guardPosition !== false
            && $actorPosition !== false
            && $guardPosition < $actorPosition,
        $action . ' checks scoped CSRF before resolving the actor'
    );
    pos_stock_live_rebuild_csrf_check(
        $guardPosition !== false
            && $loadPosition !== false
            && $guardPosition < $loadPosition,
        $action . ' checks scoped CSRF before loading the service'
    );
    pos_stock_live_rebuild_csrf_check(
        $guardPosition !== false
            && $writerPosition !== false
            && $guardPosition < $writerPosition,
        $action . ' checks scoped CSRF before its writer'
    );
}

$guardStart = strpos($controllerSource, 'private function require_pos_transaction_csrf(): bool');
$rejectStart = strpos($controllerSource, 'private function reject_pos_transaction_csrf(');
$guardSource = $guardStart === false
    ? ''
    : substr($controllerSource, $guardStart, ($rejectStart === false ? strlen($controllerSource) : $rejectStart) - $guardStart);
pos_stock_live_rebuild_csrf_check(
    strpos($guardSource, '$this->input->method(true) !== \'POST\'') !== false
        && strpos($guardSource, 'get_request_header(self::POS_TRANSACTION_CSRF_CI_HEADER, true)') !== false
        && strpos($guardSource, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardSource, '$this->input->raw_input_stream') === false,
    'the existing guard is POST-only, canonical-header/session-bound, and body-free'
);

pos_stock_live_rebuild_csrf_check(
    strpos($viewSource, 'const posTransactionCsrfToken =') !== false
        && strpos($viewSource, 'X-Pos-Transaction-CSRF') !== false,
    'stock-live view renders and sends the existing transaction token contract'
);
$globalPostJsonStart = strpos($viewSource, 'async function postJson(');
$transactionWrapperStart = strpos($viewSource, 'async function postPosTransactionJson(');
$globalPostJsonSource = $globalPostJsonStart === false
    ? ''
    : substr($viewSource, $globalPostJsonStart, ($transactionWrapperStart === false ? strlen($viewSource) : $transactionWrapperStart) - $globalPostJsonStart);
pos_stock_live_rebuild_csrf_check(
    strpos($globalPostJsonSource, 'X-Pos-Transaction-CSRF') === false,
    'shared postJson remains free of the scoped transaction header'
);
$allWrapperCall = "postPosTransactionJson('<?php echo site_url('pos/stock-live/rebuild-all'); ?>',";
$productWrapperCall = "postPosTransactionJson('<?php echo site_url('pos/stock-live/rebuild'); ?>',";
$allSharedCall = "postJson('<?php echo site_url('pos/stock-live/rebuild-all'); ?>',";
$productSharedCall = "postJson('<?php echo site_url('pos/stock-live/rebuild'); ?>',";
pos_stock_live_rebuild_csrf_check(substr_count($viewSource, $allWrapperCall) === 1, 'rebuild-all has exactly one scoped UI wrapper call');
pos_stock_live_rebuild_csrf_check(substr_count($viewSource, $productWrapperCall) === 1, 'per-product rebuild has exactly one scoped UI wrapper call');
pos_stock_live_rebuild_csrf_check(
    substr_count($viewSource, $allSharedCall) === 0 && substr_count($viewSource, $productSharedCall) === 0,
    'neither rebuild caller still uses shared postJson'
);

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    foreach (array_keys($writerMarkers) as $action) {
        pos_stock_live_rebuild_csrf_assert_rejected(
            pos_stock_live_rebuild_csrf_invoke($action, $method, [$browserHeader => $validToken], $validPayloads[$action], $validSession),
            405,
            $action . ' ' . $method,
            ['method'],
            []
        );
    }
}

$invalidCases = [
    'missing header' => [[], $validSession, []],
    'empty header' => [[$browserHeader => ''], $validSession, []],
    'malformed header' => [[$browserHeader => 'malformed'], $validSession, []],
    'absent session' => [[$browserHeader => $validToken], [], [$sessionKey]],
    'cross-session token' => [[$browserHeader => $crossSessionToken], $validSession, [$sessionKey]],
    'mismatched token' => [[$browserHeader => $mismatchedToken], $validSession, [$sessionKey]],
];
foreach ($invalidCases as $label => [$headers, $sessionValues, $expectedSessionReads]) {
    foreach (array_keys($writerMarkers) as $action) {
        pos_stock_live_rebuild_csrf_assert_rejected(
            pos_stock_live_rebuild_csrf_invoke($action, 'POST', $headers, $validPayloads[$action], $sessionValues),
            403,
            $action . ' POST ' . $label,
            ['method', 'header:' . $canonicalHeader],
            $expectedSessionReads
        );
    }
}

foreach ([$browserHeader, $canonicalHeader] as $headerName) {
    foreach (array_keys($writerMarkers) as $action) {
        $result = pos_stock_live_rebuild_csrf_invoke(
            $action,
            'POST',
            [$headerName => $validToken],
            $validPayloads[$action],
            $validSession
        );
        [$controller, $input, $session, $output, $loader, $service, $forbidden, $exception] = $result;
        $expectedCall = $action === 'stock_live_rebuild_all' ? 'rebuild_all_products' : 'rebuild_product';
        pos_stock_live_rebuild_csrf_check(
            $exception instanceof RuntimeException
                && $exception->getMessage() === 'stock service reached: ' . $expectedCall,
            $action . ' valid ' . $headerName . ' reaches the actual service call'
        );
        pos_stock_live_rebuild_csrf_check($input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream'], $action . ' valid ' . $headerName . ' reads body after CSRF');
        pos_stock_live_rebuild_csrf_check($session->reads === [$sessionKey], $action . ' valid ' . $headerName . ' stays session-bound');
        pos_stock_live_rebuild_csrf_check($loader->libraryCalls === [['PosAvailabilityRebuildService', null]], $action . ' valid ' . $headerName . ' loads the existing service');
        pos_stock_live_rebuild_csrf_check($controller->permissionCalls === [['pos.stock.live.index', 'edit']], $action . ' valid ' . $headerName . ' keeps edit RBAC');
        pos_stock_live_rebuild_csrf_check($forbidden === [], $action . ' valid ' . $headerName . ' reaches no unexpected dependency');

        $expectedArguments = $action === 'stock_live_rebuild_all'
            ? [[17, ['division_id' => 8], [
                'trigger_context' => 'MANUAL_REBUILD_ALL',
                'event_source' => 'MANUAL_REBUILD_ALL',
                'event_table' => 'mst_product_recipe',
                'event_id' => null,
                'actor_employee_id' => 4242,
            ]]]
            : [[17, 99, [
                'trigger_context' => 'MANUAL_REBUILD',
                'event_source' => 'MANUAL_REBUILD',
                'event_table' => 'pos_product_availability_cache',
                'event_id' => 99,
                'actor_employee_id' => 4242,
            ]]];
        pos_stock_live_rebuild_csrf_check($service->calls === [$expectedCall], $action . ' valid ' . $headerName . ' calls one expected service method');
        pos_stock_live_rebuild_csrf_check($service->arguments === $expectedArguments, $action . ' valid ' . $headerName . ' preserves payload and actor context');
    }
}

[$controller, $input, $session, $output, $loader, $service, $reflection] = pos_stock_live_rebuild_csrf_controller(
    'GET',
    [],
    [],
    $validSession
);
$tokenHelper = $reflection->getMethod('pos_transaction_csrf');
$tokenHelper->setAccessible(true);
$reusedTokenOne = $tokenHelper->invoke($controller);
$reusedTokenTwo = $tokenHelper->invoke($controller);
pos_stock_live_rebuild_csrf_check($reusedTokenOne === $validToken && $reusedTokenOne === $reusedTokenTwo, 'valid session token is reused without rotation');
pos_stock_live_rebuild_csrf_check($session->reads === [$sessionKey, $sessionKey] && $session->writes === [], 'token reuse only reads the existing session token');

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS stock-live rebuild CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS stock-live rebuild CSRF behavioral/source checks\n";
