<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source/render smoke for all six order-monitor writers.
 *
 * The real Pos controller is loaded without its constructor. Rejected requests
 * must stop after edit RBAC and the scoped CSRF guard; accepted requests reach
 * exactly one recording model writer with the existing task-or-bulk/actor/scope contract.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosOrderMonitorTaskCsrfSmokeInput
{
    public array $events = [];

    private string $requestMethod;
    private array $headers = [];
    private string $rawInput;

    public function __construct(string $requestMethod, array $headers = [], array $payload = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        foreach ($headers as $name => $value) {
            $this->headers[self::normalizeCgiHeaderName((string)$name)] = (string)$value;
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
        if ($name === 'raw_input_stream') {
            return $this->rawInput;
        }
        throw new RuntimeException('unexpected input property access: ' . $name);
    }
}

final class PosOrderMonitorTaskCsrfSmokeSession
{
    public array $reads = [];
    public array $writes = [];

    public function __construct(private array $values = [])
    {
    }

    public function userdata(string $key)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata(string $key, string $value): void
    {
        $this->writes[] = [$key, $value];
        $this->values[$key] = $value;
    }
}

final class PosOrderMonitorTaskCsrfSmokeOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';
    public int $displayCalls = 0;

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
        $this->displayCalls++;
        throw new RuntimeException('smoke output displayed');
    }
}

final class PosOrderMonitorTaskCsrfSmokeModel
{
    public array $calls = [];

    public function __construct(private bool $writerResult = true)
    {
    }

    public function ack_task(int $taskId, int $actorEmployeeId, array $scope): bool
    {
        return $this->record('ack_task', $taskId, $actorEmployeeId, $scope);
    }

    public function ready_task(int $taskId, int $actorEmployeeId, array $scope): bool
    {
        return $this->record('ready_task', $taskId, $actorEmployeeId, $scope);
    }

    public function checker_task(int $taskId, int $actorEmployeeId, array $scope): bool
    {
        return $this->record('checker_task', $taskId, $actorEmployeeId, $scope);
    }

    public function ack_order_station(int $orderId, string $stationRole, int $actorEmployeeId, array $scope): bool
    {
        return $this->record('ack_order_station', $orderId, $stationRole, $actorEmployeeId, $scope);
    }

    public function ready_order_station(int $orderId, string $stationRole, int $actorEmployeeId, array $scope): bool
    {
        return $this->record('ready_order_station', $orderId, $stationRole, $actorEmployeeId, $scope);
    }

    public function checker_order(int $orderId, int $actorEmployeeId, array $scope): bool
    {
        return $this->record('checker_order', $orderId, $actorEmployeeId, $scope);
    }

    private function record(string $method, ...$arguments): bool
    {
        $this->calls[] = [$method, ...$arguments];
        return $this->writerResult;
    }

    public function __call(string $method, array $arguments)
    {
        throw new RuntimeException('unexpected order-monitor model call: ' . $method);
    }
}

final class PosOrderMonitorTaskCsrfSmokeDb
{
    public array $calls = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        throw new RuntimeException('unexpected DB access: ' . $method);
    }
}

final class PosOrderMonitorTaskCsrfSmokeLoader
{
    public array $calls = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        throw new RuntimeException('unexpected loader access: ' . $method);
    }
}

$posOrderMonitorTaskCsrfForbiddenControllerAccesses = [];

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public $load;
    public $Pos_order_monitor_model;
    public array $current_user = [
        'id' => 77,
        'employee_id' => 4242,
        'is_superadmin' => true,
    ];
    public array $events = [];
    public array $permissionCalls = [];

    public function __construct()
    {
    }

    protected function require_permission(string $pageCode, string $action = 'view'): void
    {
        $this->events[] = 'permission';
        $this->permissionCalls[] = [$pageCode, $action];
    }

    protected function is_superadmin(): bool
    {
        $this->events[] = 'is_superadmin';
        return !empty($this->current_user['is_superadmin']);
    }

    public function __get(string $name)
    {
        global $posOrderMonitorTaskCsrfForbiddenControllerAccesses;
        $posOrderMonitorTaskCsrfForbiddenControllerAccesses[] = $name;
        throw new RuntimeException('unexpected controller dependency access: ' . $name);
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];

function pos_order_monitor_task_csrf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_order_monitor_task_csrf_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_order_monitor_task_csrf_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_order_monitor_task_csrf_method_block(string $source, string $method): string
{
    if (preg_match(
        '/\n    (?:public|protected|private) function ' . preg_quote($method, '/') . '\s*\(/',
        $source,
        $match,
        PREG_OFFSET_CAPTURE
    ) !== 1) {
        return '';
    }

    $start = (int)$match[0][1] + 1;
    $next = preg_match(
        '/\n    (?:public|protected|private) function /',
        $source,
        $nextMatch,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = $next === 1 ? (int)$nextMatch[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function pos_order_monitor_task_csrf_controller(
    string $requestMethod,
    array $headers,
    array $payload,
    array $sessionValues,
    bool $writerResult = true
): array {
    global $posOrderMonitorTaskCsrfForbiddenControllerAccesses;
    $posOrderMonitorTaskCsrfForbiddenControllerAccesses = [];

    $input = new PosOrderMonitorTaskCsrfSmokeInput($requestMethod, $headers, $payload);
    $session = new PosOrderMonitorTaskCsrfSmokeSession($sessionValues);
    $output = new PosOrderMonitorTaskCsrfSmokeOutput();
    $model = new PosOrderMonitorTaskCsrfSmokeModel($writerResult);
    $db = new PosOrderMonitorTaskCsrfSmokeDb();
    $loader = new PosOrderMonitorTaskCsrfSmokeLoader();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->Pos_order_monitor_model = $model;
    $controller->db = $db;
    $controller->load = $loader;
    $controller->current_user = [
        'id' => 77,
        'employee_id' => 4242,
        'is_superadmin' => true,
    ];

    return [$controller, $input, $session, $output, $model, $db, $loader, $reflection];
}

function pos_order_monitor_task_csrf_invoke(
    string $action,
    string $requestMethod,
    array $headers,
    array $payload,
    array $sessionValues,
    bool $writerResult = true
): array {
    [$controller, $input, $session, $output, $model, $db, $loader, $reflection]
        = pos_order_monitor_task_csrf_controller(
            $requestMethod,
            $headers,
            $payload,
            $sessionValues,
            $writerResult
        );

    $exception = null;
    try {
        $reflection->getMethod($action)->invoke($controller);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    global $posOrderMonitorTaskCsrfForbiddenControllerAccesses;
    return [
        $controller,
        $input,
        $session,
        $output,
        $model,
        $db,
        $loader,
        $posOrderMonitorTaskCsrfForbiddenControllerAccesses,
        $exception,
    ];
}

function pos_order_monitor_task_csrf_json(PosOrderMonitorTaskCsrfSmokeOutput $output): array
{
    $decoded = json_decode($output->body, true);
    return is_array($decoded) ? $decoded : [];
}

function pos_order_monitor_task_csrf_assert_rejected(
    array $result,
    int $status,
    string $label,
    array $expectedInputEvents,
    array $expectedSessionReads
): void {
    [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
    $body = pos_order_monitor_task_csrf_json($output);

    pos_order_monitor_task_csrf_check($exception === null, $label . ' completes at the scoped guard');
    pos_order_monitor_task_csrf_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_order_monitor_task_csrf_check($output->contentType === 'application/json', $label . ' returns JSON');
    pos_order_monitor_task_csrf_check(($body['ok'] ?? null) === false, $label . ' returns a rejection body');
    pos_order_monitor_task_csrf_check(
        $controller->permissionCalls === [['pos.order.monitor.index', 'edit']],
        $label . ' keeps existing edit RBAC first'
    );
    pos_order_monitor_task_csrf_check($controller->events === ['permission'], $label . ' does not resolve monitor scope');
    pos_order_monitor_task_csrf_check($input->events === $expectedInputEvents, $label . ' observes only the expected request metadata');
    pos_order_monitor_task_csrf_check($session->reads === $expectedSessionReads, $label . ' reads session only when required');
    pos_order_monitor_task_csrf_check($model->calls === [], $label . ' does not call any model writer');
    pos_order_monitor_task_csrf_check($db->calls === [], $label . ' does not access DB/scope queries');
    pos_order_monitor_task_csrf_check($loader->calls === [], $label . ' does not load a service');
    pos_order_monitor_task_csrf_check($forbidden === [], $label . ' reaches no unexpected controller dependency');
    pos_order_monitor_task_csrf_check($output->displayCalls === 0, $label . ' does not enter the legacy terminal response helper');
}

$root = dirname(__DIR__, 2);
$controllerSource = pos_order_monitor_task_csrf_source($root . '/application/controllers/Pos.php');
$viewSource = pos_order_monitor_task_csrf_source($root . '/application/views/pos/order_monitor_index.php');
$routeSource = pos_order_monitor_task_csrf_source($root . '/application/config/routes.php');
$modelSource = pos_order_monitor_task_csrf_source($root . '/application/models/Pos_order_monitor_model.php');

$sessionKey = 'pos_order_monitor_csrf';
$browserHeader = 'X-Pos-Order-Monitor-CSRF';
$canonicalHeader = 'X-Pos-Order-Monitor-Csrf';
$validToken = str_repeat('a', 64);
$otherToken = str_repeat('b', 64);
$mismatchedToken = str_repeat('c', 64);
$validSession = [$sessionKey => $validToken];
$validTaskId = 731;
$validPayload = ['task_id' => $validTaskId];
$validOrderId = 913;
$expectedScope = [
    'restricted' => false,
    'station_role' => 'ALL',
    'operational_division_id' => 0,
    'division_name' => '',
];
$actions = [
    'order_monitor_ack_task' => ['handler_action' => 'ack', 'writer' => 'ack_task', 'error' => 'Task gagal diterima.'],
    'order_monitor_ready_task' => ['handler_action' => 'ready', 'writer' => 'ready_task', 'error' => 'Task gagal ditandai siap.'],
    'order_monitor_checker_task' => ['handler_action' => 'checker', 'writer' => 'checker_task', 'error' => 'Task checker gagal diselesaikan.'],
];
$bulkActions = [
    'order_monitor_ack_order_station' => [
        'route' => 'ack-order-station',
        'handler_action' => 'ack',
        'writer' => 'ack_order_station',
        'station' => 'BAR',
        'payload' => ['order_id' => $validOrderId, 'station_role' => 'BAR'],
        'response' => ['ok' => true, 'order_id' => $validOrderId, 'station_role' => 'BAR'],
        'error' => 'Task stasiun gagal diterima.',
    ],
    'order_monitor_ready_order_station' => [
        'route' => 'ready-order-station',
        'handler_action' => 'ready',
        'writer' => 'ready_order_station',
        'station' => 'KITCHEN',
        'payload' => ['order_id' => $validOrderId, 'station_role' => 'KITCHEN'],
        'response' => ['ok' => true, 'order_id' => $validOrderId, 'station_role' => 'KITCHEN'],
        'error' => 'Task stasiun gagal ditandai siap.',
    ],
    'order_monitor_checker_order' => [
        'route' => 'checker-order',
        'handler_action' => 'checker',
        'writer' => 'checker_order',
        'station' => null,
        'payload' => ['order_id' => $validOrderId],
        'response' => ['ok' => true, 'order_id' => $validOrderId],
        'error' => 'Task checker gagal diselesaikan.',
    ],
];
$actionPayloads = [];
foreach (array_keys($actions) as $publicAction) {
    $actionPayloads[$publicAction] = $validPayload;
}
foreach ($bulkActions as $publicAction => $mapping) {
    $actionPayloads[$publicAction] = $mapping['payload'];
}

pos_order_monitor_task_csrf_check(
    strpos($controllerSource, "private const POS_ORDER_MONITOR_CSRF_SESSION_KEY = '" . $sessionKey . "';") !== false
        && strpos($controllerSource, "private const POS_ORDER_MONITOR_CSRF_HEADER = '" . $browserHeader . "';") !== false
        && strpos($controllerSource, "private const POS_ORDER_MONITOR_CSRF_CI_HEADER = '" . $canonicalHeader . "';") !== false,
    'controller defines the isolated order-monitor CSRF contract'
);

foreach ($actions as $publicAction => $mapping) {
    $routeSlug = str_replace(['order_monitor_', '_task'], ['', '-task'], $publicAction);
    pos_order_monitor_task_csrf_check(
        strpos($routeSource, "\$route['pos/order-monitor/" . $routeSlug . "'] = 'pos/" . $publicAction . "';") !== false,
        $publicAction . ' route remains unchanged'
    );

    $publicBlock = pos_order_monitor_task_csrf_method_block($controllerSource, $publicAction);
    pos_order_monitor_task_csrf_check($publicBlock !== '', $publicAction . ' exists in the actual controller');
    pos_order_monitor_task_csrf_check(
        substr_count($publicBlock, "\$this->handle_order_monitor_task_action('" . $mapping['handler_action'] . "');") === 1,
        $publicAction . ' preserves its original action mapping'
    );
    pos_order_monitor_task_csrf_check(
        strpos($publicBlock, 'require_order_monitor_task_csrf') === false
            && strpos($publicBlock, 'request_payload') === false
            && strpos($publicBlock, 'Pos_order_monitor_model') === false,
        $publicAction . ' has no duplicate guard/body/writer flow'
    );
}

pos_order_monitor_task_csrf_check(
    substr_count($controllerSource, 'require_order_monitor_task_csrf()') === 3,
    'only the two shared handlers call the single private scoped guard'
);

$handlerBlock = pos_order_monitor_task_csrf_method_block($controllerSource, 'handle_order_monitor_task_action');
$permissionPosition = strpos($handlerBlock, "\$this->require_permission(\$pageCode, 'edit');");
$guardPosition = strpos($handlerBlock, '$this->require_order_monitor_task_csrf()');
$payloadPosition = strpos($handlerBlock, '$this->request_payload()');
$taskIdPosition = strpos($handlerBlock, '$taskId =');
$taskValidationPosition = strpos($handlerBlock, 'if ($taskId <= 0)');
$actorPosition = strpos($handlerBlock, '$actorEmployeeId = $this->current_actor_employee_id()');
$scopePosition = strpos($handlerBlock, '$monitorScope = $this->current_actor_monitor_scope()');
$responsePosition = strpos($handlerBlock, '$this->json_ok([\'task_id\' => $taskId]);');

pos_order_monitor_task_csrf_check($handlerBlock !== '', 'shared task handler exists in the actual controller');
pos_order_monitor_task_csrf_check(
    $permissionPosition !== false && $guardPosition !== false && $permissionPosition < $guardPosition,
    'shared handler runs existing edit RBAC before scoped guard'
);
pos_order_monitor_task_csrf_check(
    $guardPosition !== false && $payloadPosition !== false && $guardPosition < $payloadPosition,
    'shared handler guards before request_payload()'
);
pos_order_monitor_task_csrf_check(
    $payloadPosition !== false
        && $taskIdPosition !== false
        && $taskValidationPosition !== false
        && $payloadPosition < $taskIdPosition
        && $taskIdPosition < $taskValidationPosition,
    'shared handler reads payload then validates a positive task_id'
);
pos_order_monitor_task_csrf_check(
    $taskValidationPosition !== false
        && $actorPosition !== false
        && $scopePosition !== false
        && $taskValidationPosition < $actorPosition
        && $actorPosition < $scopePosition,
    'shared handler validates task_id before actor/scope resolution'
);
foreach (['ack_task', 'ready_task', 'checker_task'] as $writer) {
    $writerPosition = strpos($handlerBlock, '->' . $writer . '(');
    pos_order_monitor_task_csrf_check(
        $scopePosition !== false
            && $writerPosition !== false
            && $responsePosition !== false
            && $scopePosition < $writerPosition
            && $writerPosition < $responsePosition
            && substr_count($handlerBlock, '->' . $writer . '(') === 1,
        'shared handler preserves exactly one ' . $writer . ' branch after scope resolution'
    );
}

$guardBlock = pos_order_monitor_task_csrf_method_block($controllerSource, 'require_order_monitor_task_csrf');
$tokenBlock = pos_order_monitor_task_csrf_method_block($controllerSource, 'order_monitor_task_csrf');
pos_order_monitor_task_csrf_check(
    strpos($guardBlock, '$this->input->method(true) !== \'POST\'') !== false
        && strpos($guardBlock, 'reject_order_monitor_task_csrf(405') !== false
        && strpos($guardBlock, 'get_request_header(self::POS_ORDER_MONITOR_CSRF_CI_HEADER, true)') !== false
        && strpos($guardBlock, "preg_match('/\\A[0-9a-fA-F]{64}\\z/D', \$providedToken)") !== false
        && strpos($guardBlock, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardBlock, 'reject_order_monitor_task_csrf(403') !== false
        && strpos($guardBlock, 'raw_input_stream') === false,
    'scoped guard is POST-only, canonical-header/session-bound, and body-free'
);
pos_order_monitor_task_csrf_check(
    strpos($tokenBlock, 'userdata(self::POS_ORDER_MONITOR_CSRF_SESSION_KEY)') !== false
        && strpos($tokenBlock, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenBlock, 'set_userdata(self::POS_ORDER_MONITOR_CSRF_SESSION_KEY, $token)') !== false,
    'page token helper reuses or generates an invariant 64-hex token'
);

$pageBlock = pos_order_monitor_task_csrf_method_block($controllerSource, 'order_monitor');
$pagePermissionPosition = strpos($pageBlock, "\$this->require_permission(\$pageCode, 'view');");
$pageTokenPosition = strpos($pageBlock, '$orderMonitorCsrfToken = $this->order_monitor_task_csrf()');
$pageScopePosition = strpos($pageBlock, '$monitorScope = $this->current_actor_monitor_scope()');
pos_order_monitor_task_csrf_check(
    $pagePermissionPosition !== false
        && $pageTokenPosition !== false
        && $pageScopePosition !== false
        && $pagePermissionPosition < $pageTokenPosition
        && $pageTokenPosition < $pageScopePosition
        && strpos($pageBlock, "'order_monitor_csrf_token' => \$orderMonitorCsrfToken") !== false,
    'order-monitor page generates and renders the scoped token only after view RBAC'
);

foreach ($bulkActions as $bulkAction => $mapping) {
    pos_order_monitor_task_csrf_check(
        strpos($routeSource, "\$route['pos/order-monitor/" . $mapping['route'] . "'] = 'pos/" . $bulkAction . "';") !== false,
        $bulkAction . ' route remains unchanged'
    );

    $bulkBlock = pos_order_monitor_task_csrf_method_block($controllerSource, $bulkAction);
    pos_order_monitor_task_csrf_check(
        $bulkBlock !== ''
            && substr_count($bulkBlock, "\$this->handle_order_monitor_bulk_action('" . $mapping['handler_action'] . "');") === 1
            && strpos($bulkBlock, 'require_order_monitor_task_csrf') === false
            && strpos($bulkBlock, 'request_payload') === false
            && strpos($bulkBlock, 'Pos_order_monitor_model') === false,
        $bulkAction . ' delegates once to its exact shared bulk action'
    );
}

$bulkHandlerBlock = pos_order_monitor_task_csrf_method_block($controllerSource, 'handle_order_monitor_bulk_action');
$bulkPermissionPosition = strpos($bulkHandlerBlock, "\$this->require_permission(\$pageCode, 'edit');");
$bulkGuardPosition = strpos($bulkHandlerBlock, '$this->require_order_monitor_task_csrf()');
$bulkPayloadPosition = strpos($bulkHandlerBlock, '$this->request_payload()');
$bulkIdPosition = strpos($bulkHandlerBlock, '$this->parse_positive_order_monitor_id(');
$bulkStationPosition = strpos($bulkHandlerBlock, '$stationRole = is_string($stationRoleValue)');
$bulkValidationPosition = strpos($bulkHandlerBlock, "if (\$action !== 'checker'");
$bulkCheckerValidationPosition = strpos($bulkHandlerBlock, "if (\$action === 'checker'");
$bulkActorPosition = strpos($bulkHandlerBlock, '$actorEmployeeId = $this->current_actor_employee_id()');
$bulkScopePosition = strpos($bulkHandlerBlock, '$monitorScope = $this->current_actor_monitor_scope()');
pos_order_monitor_task_csrf_check($bulkHandlerBlock !== '', 'shared bulk handler exists in the actual controller');
pos_order_monitor_task_csrf_check(
    $bulkPermissionPosition !== false
        && $bulkGuardPosition !== false
        && $bulkPayloadPosition !== false
        && $bulkPermissionPosition < $bulkGuardPosition
        && $bulkGuardPosition < $bulkPayloadPosition,
    'shared bulk handler runs edit RBAC then scoped guard before body access'
);
pos_order_monitor_task_csrf_check(
    $bulkPayloadPosition !== false
        && $bulkIdPosition !== false
        && $bulkStationPosition !== false
        && $bulkValidationPosition !== false
        && $bulkPayloadPosition < $bulkIdPosition
        && $bulkIdPosition < $bulkStationPosition
        && $bulkStationPosition < $bulkValidationPosition,
    'shared bulk handler strictly parses order_id and station payload before validation'
);
pos_order_monitor_task_csrf_check(
    $bulkValidationPosition !== false
        && $bulkCheckerValidationPosition !== false
        && $bulkActorPosition !== false
        && $bulkScopePosition !== false
        && $bulkValidationPosition < $bulkActorPosition
        && $bulkCheckerValidationPosition < $bulkActorPosition
        && $bulkActorPosition < $bulkScopePosition,
    'shared bulk handler validates payload before actor/scope resolution'
);
foreach (['ack_order_station', 'ready_order_station', 'checker_order'] as $writer) {
    $writerPosition = strpos($bulkHandlerBlock, '->' . $writer . '(');
    pos_order_monitor_task_csrf_check(
        $bulkScopePosition !== false
            && $writerPosition !== false
            && $bulkScopePosition < $writerPosition
            && substr_count($bulkHandlerBlock, '->' . $writer . '(') === 1,
        'shared bulk handler preserves exactly one ' . $writer . ' branch after scope resolution'
    );
}

$bulkIdParserBlock = pos_order_monitor_task_csrf_method_block($controllerSource, 'parse_positive_order_monitor_id');
pos_order_monitor_task_csrf_check(
    strpos($bulkIdParserBlock, 'is_bool($value)') !== false
        && strpos($bulkIdParserBlock, 'is_float($value)') !== false
        && strpos($bulkIdParserBlock, 'is_array($value)') !== false
        && strpos($bulkIdParserBlock, 'is_object($value)') !== false
        && strpos($bulkIdParserBlock, "preg_match('/\\A[1-9][0-9]*\\z/D', \$value)") !== false
        && strpos($bulkIdParserBlock, 'FILTER_VALIDATE_INT') !== false,
    'bulk order_id parser rejects coercive payload types and non-canonical positive IDs'
);

pos_order_monitor_task_csrf_check(
    strpos($modelSource, 'function ack_task(') !== false
        && strpos($modelSource, 'function ready_task(') !== false
        && strpos($modelSource, 'function checker_task(') !== false
        && strpos($modelSource, 'function ack_order_station(') !== false
        && strpos($modelSource, 'function ready_order_station(') !== false
        && strpos($modelSource, 'function checker_order(') !== false,
    'production order-monitor model retains all six existing writer methods'
);

$genericPostStart = strpos($viewSource, 'async function postJson(');
$scopedWrapperStart = strpos($viewSource, 'async function postOrderMonitorTaskJson(');
$readOnlyStart = strpos($viewSource, 'async function getJson(');
$readOnlySource = ($readOnlyStart === false || $genericPostStart === false)
    ? ''
    : substr($viewSource, $readOnlyStart, $genericPostStart - $readOnlyStart);
$genericPostSource = $genericPostStart === false
    ? ''
    : substr($viewSource, $genericPostStart, ($scopedWrapperStart === false ? strlen($viewSource) : $scopedWrapperStart) - $genericPostStart);
$scopedWrapperSource = $scopedWrapperStart === false
    ? ''
    : substr($viewSource, $scopedWrapperStart, (strpos($viewSource, "\n  function formQuery", $scopedWrapperStart) ?: strlen($viewSource)) - $scopedWrapperStart);
pos_order_monitor_task_csrf_check(
    $genericPostSource !== '' && strpos($genericPostSource, $browserHeader) === false,
    'generic postJson remains neutral and carries no scoped order-monitor header'
);
pos_order_monitor_task_csrf_check(
    $scopedWrapperSource !== ''
        && strpos($scopedWrapperSource, "credentials: 'same-origin'") !== false
        && strpos($scopedWrapperSource, "'Content-Type': 'application/json'") !== false
        && strpos($scopedWrapperSource, "'X-Requested-With': 'XMLHttpRequest'") !== false
        && strpos($scopedWrapperSource, "'" . $browserHeader . "': orderMonitorCsrfToken") !== false,
    'scoped UI wrapper sends credentials and the exact browser header contract'
);

$clickStart = strpos($viewSource, "boardEl.addEventListener('click'");
$clickEnd = $clickStart === false ? false : strpos($viewSource, "\n  renderBoard(currentPayload);", $clickStart);
$clickSource = ($clickStart === false || $clickEnd === false) ? '' : substr($viewSource, $clickStart, $clickEnd - $clickStart);
$uiMappings = [
    'ack-task' => 'ackTask',
    'ready-task' => 'readyTask',
    'checker-task' => 'checkerTask',
];
foreach ($uiMappings as $uiAction => $endpointName) {
    $pattern = "/(?:if|else if) \\(action === '" . preg_quote($uiAction, '/') . "'\\) \\{(.*?)(?=\\n    \\} else if|\\n    \\})/s";
    $matched = preg_match($pattern, $clickSource, $branchMatch) === 1;
    $branchSource = $matched ? (string)$branchMatch[1] : '';
    pos_order_monitor_task_csrf_check(
        $matched
            && strpos($branchSource, 'endpoint.' . $endpointName) !== false
            && strpos($branchSource, 'task_id:') !== false
            && strpos($branchSource, 'requestJson = postOrderMonitorTaskJson;') !== false,
        $uiAction . ' single-task caller remains on the scoped wrapper with task_id payload'
    );
}
foreach (['ack-order' => 'ackOrder', 'ready-order' => 'readyOrder', 'checker-order' => 'checkerOrder'] as $uiAction => $endpointName) {
    $pattern = "/(?:if|else if) \\(action === '" . preg_quote($uiAction, '/') . "'\\) \\{(.*?)(?=\\n    \\} else if|\\n    \\})/s";
    $matched = preg_match($pattern, $clickSource, $branchMatch) === 1;
    $branchSource = $matched ? (string)$branchMatch[1] : '';
    pos_order_monitor_task_csrf_check(
        $matched
            && strpos($branchSource, 'endpoint.' . $endpointName) !== false
            && strpos($branchSource, 'order_id:') !== false
            && strpos($branchSource, 'requestJson = postOrderMonitorTaskJson;') !== false,
        $uiAction . ' bulk caller selects the scoped wrapper with order_id payload'
    );
}
pos_order_monitor_task_csrf_check(
    substr_count($clickSource, 'requestJson = postOrderMonitorTaskJson;') === 6
        && substr_count($clickSource, 'await requestJson(url, payload);') === 1,
    'actual click dispatcher scopes exactly three task and three bulk callers'
);
pos_order_monitor_task_csrf_check(
    $readOnlySource !== ''
        && strpos($readOnlySource, $browserHeader) === false
        && substr_count($scopedWrapperSource, $browserHeader) === 1
        && strpos($genericPostSource, "credentials: 'same-origin'") === false,
    'read-only/generic helpers stay neutral while the single scoped wrapper owns the monitor header'
);

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    foreach ($actionPayloads as $publicAction => $requestPayload) {
        pos_order_monitor_task_csrf_assert_rejected(
            pos_order_monitor_task_csrf_invoke(
                $publicAction,
                $method,
                [$browserHeader => $validToken],
                $requestPayload,
                $validSession
            ),
            405,
            $publicAction . ' ' . $method,
            ['method'],
            []
        );
    }
}

$invalidCases = [
    'missing header' => [[], $validSession, []],
    'empty header' => [[$browserHeader => ''], $validSession, []],
    'short malformed header' => [[$browserHeader => 'abc123'], $validSession, []],
    'non-hex header' => [[$browserHeader => str_repeat('z', 64)], $validSession, []],
    'missing session token' => [[$browserHeader => $validToken], [], [$sessionKey]],
    'malformed session token' => [[$browserHeader => $validToken], [$sessionKey => 'malformed'], [$sessionKey]],
    'cross-session token' => [[$browserHeader => $otherToken], $validSession, [$sessionKey]],
    'mismatched token' => [[$browserHeader => $mismatchedToken], $validSession, [$sessionKey]],
];
foreach ($invalidCases as $caseLabel => [$headers, $sessionValues, $expectedSessionReads]) {
    foreach ($actionPayloads as $publicAction => $requestPayload) {
        pos_order_monitor_task_csrf_assert_rejected(
            pos_order_monitor_task_csrf_invoke(
                $publicAction,
                'POST',
                $headers,
                $requestPayload,
                $sessionValues
            ),
            403,
            $publicAction . ' POST ' . $caseLabel,
            ['method', 'header:' . $canonicalHeader],
            $expectedSessionReads
        );
    }
}

foreach ($actions as $publicAction => $mapping) {
    $result = pos_order_monitor_task_csrf_invoke(
        $publicAction,
        'POST',
        [$browserHeader => $validToken],
        ['task_id' => 0],
        $validSession
    );
    [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
    $body = pos_order_monitor_task_csrf_json($output);
    pos_order_monitor_task_csrf_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
        $publicAction . ' invalid task_id reaches the existing JSON error terminal'
    );
    pos_order_monitor_task_csrf_check($output->status === 422 && ($body['message'] ?? '') === 'Task monitor tidak valid.', $publicAction . ' preserves task_id validation error');
    pos_order_monitor_task_csrf_check($input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream'], $publicAction . ' validates task_id only after guard/body');
    pos_order_monitor_task_csrf_check($session->reads === [$sessionKey], $publicAction . ' invalid task_id remains session-bound');
    pos_order_monitor_task_csrf_check($controller->events === ['permission'], $publicAction . ' invalid task_id resolves no actor/scope');
    pos_order_monitor_task_csrf_check($model->calls === [] && $db->calls === [] && $loader->calls === [] && $forbidden === [], $publicAction . ' invalid task_id reaches no writer/DB/service');
}

$invalidOrderIds = [
    'missing' => [false, null],
    'null' => [true, null],
    'zero integer' => [true, 0],
    'negative integer' => [true, -4],
    'zero-padded string' => [true, '0913'],
    'float' => [true, 913.5],
    'boolean' => [true, true],
    'array' => [true, [913]],
    'non-numeric string' => [true, '913x'],
    'overflow string' => [true, str_repeat('9', 30)],
];
foreach ($invalidOrderIds as $caseLabel => [$includeOrderId, $invalidOrderId]) {
    foreach ($bulkActions as $publicAction => $mapping) {
        $payload = $mapping['payload'];
        unset($payload['order_id']);
        if ($includeOrderId) {
            $payload['order_id'] = $invalidOrderId;
        }
        $result = pos_order_monitor_task_csrf_invoke(
            $publicAction,
            'POST',
            [$canonicalHeader => $validToken],
            $payload,
            $validSession
        );
        [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
        $body = pos_order_monitor_task_csrf_json($output);
        $expectedMessage = $mapping['station'] === null
            ? 'Order monitor tidak valid.'
            : 'Order atau stasiun monitor tidak valid.';
        $label = $publicAction . ' invalid order_id ' . $caseLabel;

        pos_order_monitor_task_csrf_check(
            $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
            $label . ' reaches the existing JSON error terminal'
        );
        pos_order_monitor_task_csrf_check(
            $output->status === 422 && ($body['ok'] ?? null) === false && ($body['message'] ?? '') === $expectedMessage,
            $label . ' returns the appropriate existing HTTP 422 mapping'
        );
        pos_order_monitor_task_csrf_check(
            $input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream']
                && $session->reads === [$sessionKey],
            $label . ' reaches payload validation only after the scoped guard'
        );
        pos_order_monitor_task_csrf_check(
            $controller->permissionCalls === [['pos.order.monitor.index', 'edit']]
                && $controller->events === ['permission'],
            $label . ' stops before actor/scope resolution'
        );
        pos_order_monitor_task_csrf_check(
            $model->calls === [] && $db->calls === [] && $loader->calls === [] && $forbidden === [],
            $label . ' reaches no writer/DB/service dependency'
        );
    }
}

$invalidStations = [
    'missing' => [false, null],
    'null' => [true, null],
    'empty' => [true, ''],
    'unsupported checker' => [true, 'CHECKER'],
    'integer' => [true, 7],
    'boolean' => [true, true],
    'array' => [true, ['BAR']],
];
foreach ($invalidStations as $caseLabel => [$includeStation, $invalidStation]) {
    foreach (array_intersect_key($bulkActions, array_flip([
        'order_monitor_ack_order_station',
        'order_monitor_ready_order_station',
    ])) as $publicAction => $mapping) {
        $payload = ['order_id' => $validOrderId];
        if ($includeStation) {
            $payload['station_role'] = $invalidStation;
        }
        $result = pos_order_monitor_task_csrf_invoke(
            $publicAction,
            'POST',
            [$canonicalHeader => $validToken],
            $payload,
            $validSession
        );
        [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
        $body = pos_order_monitor_task_csrf_json($output);
        $label = $publicAction . ' invalid station_role ' . $caseLabel;

        pos_order_monitor_task_csrf_check(
            $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
            $label . ' reaches the existing JSON error terminal'
        );
        pos_order_monitor_task_csrf_check(
            $output->status === 422
                && ($body['ok'] ?? null) === false
                && ($body['message'] ?? '') === 'Order atau stasiun monitor tidak valid.',
            $label . ' preserves the existing HTTP 422 station mapping'
        );
        pos_order_monitor_task_csrf_check(
            $input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream']
                && $session->reads === [$sessionKey],
            $label . ' reaches station validation only after the scoped guard'
        );
        pos_order_monitor_task_csrf_check(
            $controller->permissionCalls === [['pos.order.monitor.index', 'edit']]
                && $controller->events === ['permission'],
            $label . ' stops before actor/scope resolution'
        );
        pos_order_monitor_task_csrf_check(
            $model->calls === [] && $db->calls === [] && $loader->calls === [] && $forbidden === [],
            $label . ' reaches no writer/DB/service dependency'
        );
    }
}

foreach ([$browserHeader, $canonicalHeader] as $headerName) {
    foreach ($actions as $publicAction => $mapping) {
        $result = pos_order_monitor_task_csrf_invoke(
            $publicAction,
            'POST',
            [$headerName => $validToken],
            $validPayload,
            $validSession
        );
        [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
        $body = pos_order_monitor_task_csrf_json($output);
        $expectedCall = [[$mapping['writer'], $validTaskId, 4242, $expectedScope]];
        $label = $publicAction . ' valid ' . $headerName;

        pos_order_monitor_task_csrf_check(
            $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
            $label . ' reaches the existing response terminal'
        );
        pos_order_monitor_task_csrf_check($output->status === 200 && $output->contentType === 'application/json', $label . ' returns existing HTTP 200 JSON');
        pos_order_monitor_task_csrf_check($body === ['ok' => true, 'task_id' => $validTaskId], $label . ' preserves the existing success body');
        pos_order_monitor_task_csrf_check($output->displayCalls === 1, $label . ' emits exactly one terminal response');
        pos_order_monitor_task_csrf_check($input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream'], $label . ' reads body only after the scoped guard');
        pos_order_monitor_task_csrf_check($session->reads === [$sessionKey], $label . ' binds the request to the scoped session token');
        pos_order_monitor_task_csrf_check($controller->permissionCalls === [['pos.order.monitor.index', 'edit']], $label . ' preserves existing edit RBAC');
        pos_order_monitor_task_csrf_check($controller->events === ['permission', 'is_superadmin'], $label . ' resolves monitor scope only after validation');
        pos_order_monitor_task_csrf_check($model->calls === $expectedCall && count($model->calls) === 1, $label . ' selects exactly one writer with exact task/actor/scope arguments');
        pos_order_monitor_task_csrf_check($db->calls === [] && $loader->calls === [] && $forbidden === [], $label . ' reaches no DB query, service, or unexpected dependency');
    }
}

foreach ([$browserHeader, $canonicalHeader] as $headerName) {
    foreach ($bulkActions as $publicAction => $mapping) {
        $result = pos_order_monitor_task_csrf_invoke(
            $publicAction,
            'POST',
            [$headerName => $validToken],
            $mapping['payload'],
            $validSession
        );
        [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
        $body = pos_order_monitor_task_csrf_json($output);
        $expectedArguments = $mapping['station'] === null
            ? [$mapping['writer'], $validOrderId, 4242, $expectedScope]
            : [$mapping['writer'], $validOrderId, $mapping['station'], 4242, $expectedScope];
        $label = $publicAction . ' valid ' . $headerName;

        pos_order_monitor_task_csrf_check(
            $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
            $label . ' reaches the existing response terminal'
        );
        pos_order_monitor_task_csrf_check(
            $output->status === 200 && $output->contentType === 'application/json' && $body === $mapping['response'],
            $label . ' preserves its exact existing success response'
        );
        pos_order_monitor_task_csrf_check($output->displayCalls === 1, $label . ' emits exactly one terminal response');
        pos_order_monitor_task_csrf_check(
            $input->events === ['method', 'header:' . $canonicalHeader, 'property:raw_input_stream']
                && $session->reads === [$sessionKey],
            $label . ' reads body only after the session-bound scoped guard'
        );
        pos_order_monitor_task_csrf_check(
            $controller->permissionCalls === [['pos.order.monitor.index', 'edit']]
                && $controller->events === ['permission', 'is_superadmin'],
            $label . ' preserves edit RBAC and resolves scope after validation'
        );
        pos_order_monitor_task_csrf_check(
            $model->calls === [$expectedArguments] && count($model->calls) === 1,
            $label . ' maps exact writer/order/station/actor 4242/scope arguments'
        );
        pos_order_monitor_task_csrf_check(
            $db->calls === [] && $loader->calls === [] && $forbidden === [],
            $label . ' reaches no DB query, service, or unexpected dependency'
        );
    }
}

foreach ($actions as $publicAction => $mapping) {
    $result = pos_order_monitor_task_csrf_invoke(
        $publicAction,
        'POST',
        [$canonicalHeader => $validToken],
        $validPayload,
        $validSession,
        false
    );
    [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
    $body = pos_order_monitor_task_csrf_json($output);
    pos_order_monitor_task_csrf_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
        $publicAction . ' writer failure reaches the existing error response terminal'
    );
    pos_order_monitor_task_csrf_check(
        $output->status === 422 && ($body['ok'] ?? null) === false && ($body['message'] ?? '') === $mapping['error'],
        $publicAction . ' preserves its existing writer error mapping'
    );
    pos_order_monitor_task_csrf_check(
        $model->calls === [[$mapping['writer'], $validTaskId, 4242, $expectedScope]],
        $publicAction . ' failure still invokes only its mapped writer once'
    );
    pos_order_monitor_task_csrf_check($db->calls === [] && $loader->calls === [] && $forbidden === [], $publicAction . ' failure reaches no unrelated dependency');
}

foreach ($bulkActions as $publicAction => $mapping) {
    $result = pos_order_monitor_task_csrf_invoke(
        $publicAction,
        'POST',
        [$canonicalHeader => $validToken],
        $mapping['payload'],
        $validSession,
        false
    );
    [$controller, $input, $session, $output, $model, $db, $loader, $forbidden, $exception] = $result;
    $body = pos_order_monitor_task_csrf_json($output);
    $expectedArguments = $mapping['station'] === null
        ? [$mapping['writer'], $validOrderId, 4242, $expectedScope]
        : [$mapping['writer'], $validOrderId, $mapping['station'], 4242, $expectedScope];

    pos_order_monitor_task_csrf_check(
        $exception instanceof RuntimeException && $exception->getMessage() === 'smoke output displayed',
        $publicAction . ' writer failure reaches the existing error response terminal'
    );
    pos_order_monitor_task_csrf_check(
        $output->status === 422 && ($body['ok'] ?? null) === false && ($body['message'] ?? '') === $mapping['error'],
        $publicAction . ' preserves its existing writer error mapping'
    );
    pos_order_monitor_task_csrf_check(
        $model->calls === [$expectedArguments],
        $publicAction . ' failure still invokes only its mapped bulk writer once'
    );
    pos_order_monitor_task_csrf_check(
        $controller->events === ['permission', 'is_superadmin']
            && $db->calls === []
            && $loader->calls === []
            && $forbidden === [],
        $publicAction . ' failure preserves actor/scope flow without unrelated dependencies'
    );
}

[$controller, $input, $session, $output, $model, $db, $loader, $reflection]
    = pos_order_monitor_task_csrf_controller('GET', [], [], [$sessionKey => $validToken]);
$tokenHelper = $reflection->getMethod('order_monitor_task_csrf');
$tokenHelper->setAccessible(true);
$reusedTokenOne = $tokenHelper->invoke($controller);
$reusedTokenTwo = $tokenHelper->invoke($controller);
pos_order_monitor_task_csrf_check($reusedTokenOne === $validToken && $reusedTokenTwo === $validToken, 'valid page token is reused without rotation');
pos_order_monitor_task_csrf_check($session->reads === [$sessionKey, $sessionKey] && $session->writes === [], 'valid token reuse only reads the isolated session key');

[$controller, $input, $session, $output, $model, $db, $loader, $reflection]
    = pos_order_monitor_task_csrf_controller('GET', [], [], []);
$tokenHelper = $reflection->getMethod('order_monitor_task_csrf');
$tokenHelper->setAccessible(true);
$generatedToken = $tokenHelper->invoke($controller);
$generatedAgain = $tokenHelper->invoke($controller);
pos_order_monitor_task_csrf_check(preg_match('/\A[0-9a-f]{64}\z/D', $generatedToken) === 1, 'missing page token generates 64 lowercase hex characters');
pos_order_monitor_task_csrf_check($generatedAgain === $generatedToken, 'generated page token is reused in the same session');
pos_order_monitor_task_csrf_check(count($session->writes) === 1 && $session->writes[0][0] === $sessionKey, 'generated token writes only the isolated session key once');

if (!function_exists('base_url')) {
    function base_url(string $uri = ''): string
    {
        return '/smoke/' . ltrim($uri, '/');
    }
}
if (!function_exists('html_escape')) {
    function html_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function pos_order_monitor_task_csrf_render_view(string $path, array $variables): ?string
{
    $baseLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (int $severity, string $message): bool {
        throw new RuntimeException($message, $severity);
    });

    try {
        extract($variables, EXTR_SKIP);
        include $path;
        $html = (string)ob_get_contents();
        ob_end_clean();
    } catch (Throwable $exception) {
        $html = null;
        while (ob_get_level() > $baseLevel) {
            ob_end_clean();
        }
    } finally {
        restore_error_handler();
    }

    return $html;
}

$renderToken = str_repeat('d', 64);
$renderedView = pos_order_monitor_task_csrf_render_view(
    $root . '/application/views/pos/order_monitor_index.php',
    [
        'page_title' => 'Smoke Order Monitor',
        'filters' => [
            'station' => 'ALL',
            'outlet_id' => 0,
            'date_from' => '2026-09-02',
            'date_to' => '2026-09-02',
        ],
        'payload' => [
            'stats' => [],
            'active_orders' => [],
            'completed_orders' => [],
            'generated_at' => '2026-09-02 12:00:00',
        ],
        'station_options' => ['ALL' => 'Semua'],
        'outlet_options' => [],
        'poll_ms' => 12000,
        'order_monitor_csrf_token' => $renderToken,
    ]
);
pos_order_monitor_task_csrf_check($renderedView !== null, 'actual order-monitor view renders without warning');
if ($renderedView !== null) {
    pos_order_monitor_task_csrf_check(strpos($renderedView, $renderToken) !== false, 'rendered view contains the session-bound scoped token');
    pos_order_monitor_task_csrf_check(strpos($renderedView, $browserHeader) !== false, 'rendered view contains the exact browser header');
    pos_order_monitor_task_csrf_check(
        substr_count($renderedView, 'requestJson = postOrderMonitorTaskJson;') === 6,
        'rendered view scopes exactly three single-task and three bulk action branches'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS order-monitor task CSRF smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS order-monitor six-writer CSRF behavioral/source/render checks\n";
