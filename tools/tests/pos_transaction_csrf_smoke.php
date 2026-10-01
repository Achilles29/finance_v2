<?php

declare(strict_types=1);

/**
 * Behavioral, DB-free smoke test for the core POS transaction writers.
 *
 * The real Pos controller is loaded with a deliberately small parent stub.
 * Rejected requests must stop after RBAC and the scoped header/session check.
 * Accepted requests use a model stub that records exactly one writer call and
 * then throws before any real model, task, or availability dependency can run.
 */

define('BASEPATH', __DIR__);

class PosTransactionCsrfSmokeInput
{
    public array $events = [];

    private string $requestMethod;
    private array $headers;
    private string $rawInput;

    public function __construct(string $requestMethod, array $headers = [], string $rawInput = '{"sentinel":true}')
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->headers = [];
        foreach ($headers as $key => $value) {
            $this->headers[self::normalizeCgiHeaderName((string)$key)] = $value;
        }
        $this->rawInput = $rawInput;
    }

    private static function normalizeCgiHeaderName(string $name): string
    {
        $name = str_replace(['_', '-'], ' ', strtolower($name));
        return str_replace(' ', '-', ucwords($name));
    }

    public function method($upper = false): string
    {
        $this->events[] = 'method';
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->events[] = 'header:' . $key;
        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp($headerName, $key) === 0) {
                return (string)$value;
            }
        }
        return '';
    }

    public function __get($key)
    {
        $key = (string)$key;
        $this->events[] = 'property:' . $key;
        if ($key === 'raw_input_stream') {
            return $this->rawInput;
        }
        throw new RuntimeException('unexpected input property access: ' . $key);
    }
}

class PosTransactionCsrfSmokeSession
{
    public array $reads = [];
    public array $writes = [];

    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $key = (string)$key;
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->writes[] = $key;
        $this->values[$key] = $value;
    }
}

class PosTransactionCsrfSmokeResponseComplete extends RuntimeException
{
}

class PosTransactionCsrfSmokeOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
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

    public function _display(): void
    {
        throw new PosTransactionCsrfSmokeResponseComplete('response complete');
    }
}

class PosTransactionCsrfSmokeModel
{
    public array $writerCalls = [];
    public array $writerArguments = [];
    public array $calls = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        $writers = [
            'save_cashier_payment',
            'save_order_void',
            'save_order_refund',
            'direct_print_targets_for_order_reprint',
            'open_cashier_session',
            'close_cashier_session',
            'delete_order_draft',
            'save_order_draft',
            'finalize_order_confirmation',
            'finalize_self_order_verification',
            'reject_self_order_order',
            'reject_online_food_order',
        ];
        if (in_array($method, ['self_order_verification_context', 'online_food_verification_context'], true)) {
            return [
                'ok' => true,
                'payment_mode' => 'QRIS',
                'payment_status' => 'PAID',
                'is_paid' => true,
                'payment' => ['id' => 91],
            ];
        }
        if ($method === 'daily_recon_gate_status') {
            return ['enabled' => false, 'complete' => true];
        }
        if ($method === 'resolve_order_stock_commit_payload') {
            return [
                'ok' => true,
                'header' => ['id' => 1701],
                'lines' => [['id' => 1, 'product_id' => 99]],
                'resolved_line_count' => 1,
            ];
        }
        if (in_array($method, $writers, true)) {
            $this->writerCalls[$method] = ($this->writerCalls[$method] ?? 0) + 1;
            $this->writerArguments[$method][] = $arguments;
            throw new RuntimeException('writer reached: ' . $method);
        }
        throw new RuntimeException('unexpected model method: ' . $method);
    }
}

class PosTransactionCsrfSmokeReservationModel
{
    public array $calls = [];
    public array $arguments = [];
    public array $writerCalls = [];
    public array $writerArguments = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        $this->arguments[$method][] = $arguments;
        if ($method === 'prepare_verification') {
            return [
                'ok' => true,
                'order_id' => 2702,
                'is_paid' => true,
                'payment_id' => 91,
            ];
        }
        if (in_array($method, ['save_reservation', 'add_deposit', 'reject_reservation', 'cancel_reservation'], true)) {
            $this->writerCalls[$method] = ($this->writerCalls[$method] ?? 0) + 1;
            $this->writerArguments[$method][] = $arguments;
            if ($method === 'save_reservation') {
                return [
                    'ok' => true,
                    'id' => 2901,
                    'reservation_no' => 'RSV-SMOKE',
                    'deposit' => ['amount' => 125000],
                    'reservation' => ['id' => 2901, 'sentinel' => 'response-smoke'],
                ];
            }
            throw new RuntimeException('writer reached: ' . $method);
        }
        throw new RuntimeException('unexpected reservation model method: ' . $method);
    }
}

class PosTransactionCsrfSmokeLoader
{
    public array $libraryCalls = [];

    public function library($name, $params = null, $objectName = null): void
    {
        $this->libraryCalls[] = [(string)$name, $objectName === null ? null : (string)$objectName];
    }
}

class PosTransactionCsrfSmokeStepUp
{
    public array $calls = [];

    public function consume($userId, $action, $targetId, $proof): array
    {
        $this->calls[] = [(int)$userId, (string)$action, $targetId, $proof];

        $valid = (int)$userId === 2
            && in_array((string)$action, ['VOID', 'REFUND', 'ORDER_REPRINT', 'RESERVATION_REJECT_DEPOSIT_REFUND', 'RESERVATION_CANCEL_DEPOSIT_REFUND'], true)
            && (int)$targetId === 1701
            && is_string($proof)
            && preg_match('/\A[a-f0-9]{64}\z/D', $proof) === 1;

        return $valid
            ? ['ok' => true]
            : ['ok' => false, 'status' => 428, 'message' => 'Verifikasi ulang diperlukan.'];
    }
}

class PosTransactionCsrfSmokeService
{
    public array $calls = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        if ($method === 'create_snapshot') {
            return ['ok' => true, 'id' => 1, 'commit_no' => 'COMMIT-SMOKE'];
        }
        if ($method === 'mark_queued') {
            return ['ok' => true];
        }
        if ($method === 'queue_order_confirm_commit') {
            return ['ok' => true, 'job_id' => 2, 'job_code' => 'JOB-SMOKE'];
        }
        throw new RuntimeException('unexpected service method: ' . $method);
    }
}

class PosTransactionCsrfSmokeMonitor
{
    public array $calls = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        return null;
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public array $current_user = ['id' => 2, 'employee_id' => 1];
    public array $events = [];
    public array $canCalls = [];
    public array $permissionCalls = [];
    public PosTransactionCsrfSmokeModel $posModel;
    public PosTransactionCsrfSmokeReservationModel $posReservationModel;
    public PosTransactionCsrfSmokeLoader $load;
    public PosTransactionCsrfSmokeService $posStockCommitService;
    public PosTransactionCsrfSmokeService $posRuntimeJobService;
    public PosTransactionCsrfSmokeMonitor $posOrderMonitorModel;
    public PosTransactionCsrfSmokeStepUp $sensitiveactionstepup;

    public function __construct()
    {
    }

    public function can($page, $action = 'view'): bool
    {
        $this->events[] = 'can';
        $this->canCalls[] = [(string)$page, (string)$action];
        return true;
    }

    public function require_permission($page, $action = 'view'): void
    {
        $this->events[] = 'permission';
        $this->permissionCalls[] = [(string)$page, (string)$action];
    }

    public function __get($name)
    {
        if ((string)$name === 'Pos_model') {
            return $this->posModel;
        }
        if ((string)$name === 'Pos_reservation_model') {
            return $this->posReservationModel;
        }
        if ((string)$name === 'posstockcommitservice') {
            return $this->posStockCommitService;
        }
        if ((string)$name === 'posruntimejobservice') {
            return $this->posRuntimeJobService;
        }
        if ((string)$name === 'Pos_order_monitor_model') {
            return $this->posOrderMonitorModel;
        }
        throw new RuntimeException('unexpected controller dependency access: ' . $name);
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];

function pos_transaction_csrf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_transaction_csrf_hex64(string $value): bool
{
    return preg_match('/\A[0-9a-fA-F]{64}\z/D', $value) === 1;
}

function pos_transaction_csrf_controller(
    string $requestMethod,
    array $headers = [],
    array $sessionValues = [],
    string $rawInput = '{"sentinel":true}'
): array {
    $input = new PosTransactionCsrfSmokeInput($requestMethod, $headers, $rawInput);
    $session = new PosTransactionCsrfSmokeSession($sessionValues);
    $output = new PosTransactionCsrfSmokeOutput();
    $model = new PosTransactionCsrfSmokeModel();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->posModel = $model;
    $controller->posReservationModel = new PosTransactionCsrfSmokeReservationModel();
    $controller->load = new PosTransactionCsrfSmokeLoader();
    $controller->posStockCommitService = new PosTransactionCsrfSmokeService();
    $controller->posRuntimeJobService = new PosTransactionCsrfSmokeService();
    $controller->posOrderMonitorModel = new PosTransactionCsrfSmokeMonitor();
    $controller->sensitiveactionstepup = new PosTransactionCsrfSmokeStepUp();

    return [$controller, $input, $session, $output, $model, $reflection];
}

function pos_transaction_csrf_invoke_action(
    string $action,
    string $requestMethod,
    array $headers = [],
    array $sessionValues = [],
    array $arguments = [],
    string $rawInput = '{"sentinel":true}'
): array {
    [$controller, $input, $session, $output, $model, $reflection] = pos_transaction_csrf_controller(
        $requestMethod,
        $headers,
        $sessionValues,
        $rawInput
    );

    $exception = null;
    try {
        $reflection->getMethod($action)->invokeArgs($controller, $arguments);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    return [$controller, $input, $session, $output, $model, $exception];
}

function pos_transaction_csrf_method_block(string $source, string $method): string
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
    $end = ($next === 1) ? (int)$matches[0][1] : strlen($source);

    return substr($source, $start, $end - $start);
}

function pos_transaction_csrf_view_wrapper_block(string $source): string
{
    $start = strpos($source, 'function postPosTransactionJson(');
    if ($start === false) {
        return '';
    }

    $next = preg_match(
        '/\n\s*(?:async\s+)?function\s+[A-Za-z_$][A-Za-z0-9_$]*\s*\(/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = ($next === 1) ? (int)$matches[0][1] : strlen($source);

    return substr($source, $start, $end - $start);
}

function pos_transaction_csrf_view_request_block(string $source): string
{
    $start = strpos($source, 'async function request(');
    if ($start === false) {
        return '';
    }

    $next = preg_match(
        '/\n\s*(?:async\s+)?function\s+[A-Za-z_$][A-Za-z0-9_$]*\s*\(/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = ($next === 1) ? (int)$matches[0][1] : strlen($source);

    return substr($source, $start, $end - $start);
}

function pos_transaction_csrf_view_function_block(string $source, string $function): string
{
    $start = strpos($source, 'function ' . $function . '(');
    if ($start === false) {
        return '';
    }

    $next = preg_match(
        '/\n\s*(?:async\s+)?function\s+[A-Za-z_$][A-Za-z0-9_$]*\s*\(/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = ($next === 1) ? (int)$matches[0][1] : strlen($source);

    return substr($source, $start, $end - $start);
}

function pos_transaction_csrf_wrapper_invocation_count(string $source): int
{
    $found = preg_match_all(
        '/\bpostPosTransactionJson\s*\(/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE
    );
    if ($found === false) {
        return 0;
    }

    $invocationCount = 0;
    foreach ($matches[0] as $match) {
        $prefix = substr($source, 0, (int)$match[1]);
        if (preg_match('/function\s+$/', $prefix) === 1) {
            continue;
        }
        $invocationCount++;
    }

    return $invocationCount;
}

function pos_transaction_csrf_json_rejection(
    PosTransactionCsrfSmokeOutput $output,
    int $status,
    string $label
): void {
    pos_transaction_csrf_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_transaction_csrf_check($output->contentType === 'application/json', $label . ' returns JSON content type');
    $body = json_decode($output->body, true);
    pos_transaction_csrf_check(
        is_array($body) && ($body['ok'] ?? null) === false,
        $label . ' returns a JSON rejection body'
    );
}

$root = dirname(__DIR__, 2);
$controllerSource = file_get_contents($root . '/application/controllers/Pos.php');
$configSource = file_get_contents($root . '/application/config/config.php');
$viewPaths = [
    'cashier' => $root . '/application/views/pos/cashier_index.php',
    'draft' => $root . '/application/views/pos/order_draft_index.php',
    'paid' => $root . '/application/views/pos/order_paid_index.php',
    'reservation' => $root . '/application/views/pos/reservation_index.php',
    'self_order' => $root . '/application/views/pos/self_order_orders.php',
    'online_food' => $root . '/application/views/pos/online_food_orders.php',
];
$viewSources = [];
foreach ($viewPaths as $name => $path) {
    $viewSources[$name] = file_get_contents($path);
}

pos_transaction_csrf_check(is_string($controllerSource), 'Pos.php can be read');
pos_transaction_csrf_check(
    is_string($configSource)
        && preg_match('/\$config\[\'csrf_protection\'\]\s*=\s*FALSE\s*;/i', $configSource) === 1,
    'global CSRF remains disabled outside this scoped batch'
);

$sessionKey = 'pos_transaction_csrf';
$headerName = 'X-Pos-Transaction-CSRF';
$canonicalHeaderName = 'X-Pos-Transaction-Csrf';
$validToken = str_repeat('a', 64);
$otherToken = str_repeat('b', 64);

pos_transaction_csrf_check(
    strpos($controllerSource, "private const POS_TRANSACTION_CSRF_SESSION_KEY = '" . $sessionKey . "';") !== false,
    'controller defines the scoped transaction session key'
);
pos_transaction_csrf_check(
    strpos($controllerSource, "private const POS_TRANSACTION_CSRF_HEADER = '" . $headerName . "';") !== false,
    'controller defines the browser transaction header contract'
);
pos_transaction_csrf_check(
    strpos($controllerSource, "private const POS_TRANSACTION_CSRF_CI_HEADER = '" . $canonicalHeaderName . "';") !== false,
    'controller defines the canonical CI transaction header lookup'
);
pos_transaction_csrf_check(
    strpos($controllerSource, 'bin2hex(random_bytes(32))') !== false,
    'token generation uses 32 random bytes and hex encoding'
);
pos_transaction_csrf_check(
    preg_match_all('/\$this->pos_transaction_csrf\(\)/', $controllerSource, $matches) === 8,
    'token is generated only for the eight relevant transaction views'
);
pos_transaction_csrf_check(
    substr_count($controllerSource, "'pos_transaction_csrf_token' => \$posTransactionCsrfToken") === 8,
    'the eight relevant transaction views receive the rendered token'
);
pos_transaction_csrf_check(
    preg_match_all('/\$this->require_pos_transaction_csrf\s*\(\s*\)/', $controllerSource, $matches) === 33,
    'exactly thirty-two transaction writers and one refund-proof issuer call the scoped guard'
);

$actions = [
    'order_payment_save' => [
        'writer' => '$this->Pos_model->save_cashier_payment(',
        'extra' => [],
        'payload' => true,
    ],
    'order_void_save' => [
        'writer' => '$this->Pos_model->save_order_void(',
        'extra' => ['$this->Pos_order_monitor_model->sync_order_tasks('],
        'payload' => true,
    ],
    'order_refund_save' => [
        'writer' => '$this->Pos_model->save_order_refund(',
        'extra' => ['$this->Pos_order_monitor_model->sync_order_tasks('],
        'payload' => true,
    ],
    'order_reprint_print_targets' => [
        'writer' => '$this->Pos_model->direct_print_targets_for_order_reprint(',
        'extra' => [],
        'payload' => true,
        'strictOrder' => true,
    ],
    'cashier_open' => [
        'writer' => '$this->Pos_model->open_cashier_session(',
        'extra' => ['$this->request_payload()', '$this->Pos_model->daily_recon_gate_status('],
        'payload' => true,
    ],
    'cashier_close' => [
        'writer' => '$this->Pos_model->close_cashier_session(',
        'extra' => ['$this->request_payload()', '$this->Pos_model->daily_recon_gate_status(', '$this->Pos_model->direct_print_targets_for_shift_close('],
        'payload' => true,
    ],
    'order_draft_delete' => [
        'writer' => '$this->Pos_model->delete_order_draft(',
        'extra' => [],
        'payload' => false,
    ],
    'order_draft_save' => [
        'writer' => '$this->Pos_model->save_order_draft(',
        'extra' => [],
        'payload' => true,
        'actionGuard' => true,
    ],
    'order_draft_confirm' => [
        'writer' => '$this->confirm_order_and_respond(',
        'extra' => ['$this->current_actor_employee_id()'],
        'payload' => false,
    ],
    'order_draft_save_confirm' => [
        'writer' => '$this->Pos_model->save_order_draft(',
        'extra' => [],
        'payload' => true,
        'actionGuard' => true,
    ],
    'reservation_save' => [
        'writer' => '$this->Pos_reservation_model->save_reservation(',
        'extra' => ['$this->current_actor_employee_id()', '$this->current_actor_user_id()'],
        'payload' => true,
        'strictOrder' => true,
        'actionGuard' => true,
        'viewPermission' => "\$this->require_permission('pos.reservation.index', 'view');",
        'actionPermission' => "\$this->require_permission('pos.reservation.index', \$action);",
        'errorResponse' => "\$this->json_error((string)(\$result['message'] ?? 'Gagal menyimpan reservasi.'), 422);",
        'response' => [
            "'id' => (int)(\$result['id'] ?? 0)",
            "'reservation_no' => (string)(\$result['reservation_no'] ?? '')",
            "'deposit' => (array)(\$result['deposit'] ?? [])",
            "'reservation' => (array)(\$result['reservation'] ?? [])",
        ],
    ],
    'reservation_verify' => [
        'writer' => '$this->verify_reservation_and_respond(',
        'extra' => ['$this->current_actor_employee_id()', '$this->current_actor_user_id()'],
        'payload' => false,
    ],
    'reservation_deposit' => [
        'writer' => '$this->Pos_reservation_model->add_deposit(',
        'extra' => ['$this->current_actor_employee_id()', '$this->current_actor_user_id()'],
        'payload' => true,
        'strictOrder' => true,
        'response' => [
            "'deposit' => (array)(\$result['deposit'] ?? [])",
            "'reservation' => (array)(\$result['reservation'] ?? [])",
        ],
    ],
    'reservation_reject' => [
        'writer' => '$this->Pos_reservation_model->reject_reservation(',
        'extra' => ['$this->current_actor_employee_id()', '$this->current_actor_user_id()'],
        'payload' => true,
        'strictOrder' => true,
        'response' => ['$this->json_ok($result);'],
    ],
    'reservation_cancel' => [
        'writer' => '$this->Pos_reservation_model->cancel_reservation(',
        'extra' => ['$this->current_actor_employee_id()', '$this->current_actor_user_id()'],
        'payload' => true,
        'strictOrder' => true,
        'response' => ['$this->json_ok($result);'],
    ],
    'self_order_order_verify' => [
        'writer' => '$this->verify_self_order_and_respond(',
        'extra' => ['$this->current_actor_employee_id()'],
        'payload' => true,
    ],
    'self_order_order_reject' => [
        'writer' => '$this->Pos_model->reject_self_order_order(',
        'extra' => ['$this->current_actor_employee_id()'],
        'payload' => true,
        'strictOrder' => true,
        'errorResponse' => "\$this->json_error((string)(\$result['message'] ?? 'Gagal menolak order self order.'), 422);",
        'response' => [
            "'id' => (int)(\$result['id'] ?? 0)",
            "'status' => (string)(\$result['status'] ?? 'REJECTED')",
            "'reason' => (string)(\$result['reason'] ?? '')",
        ],
    ],
    'online_food_order_verify' => [
        'writer' => '$this->verify_self_order_and_respond(',
        'extra' => ['$this->current_actor_employee_id()'],
        'payload' => true,
    ],
    'online_food_order_reject' => [
        'writer' => '$this->Pos_model->reject_online_food_order(',
        'extra' => ['$this->current_actor_employee_id()'],
        'payload' => true,
        'strictOrder' => true,
        'errorResponse' => "\$this->json_error((string)(\$result['message'] ?? 'Gagal menolak order online food.'), 422);",
        'response' => [
            "'id' => (int)(\$result['id'] ?? 0)",
            "'status' => (string)(\$result['status'] ?? 'REJECTED')",
            "'reason' => (string)(\$result['reason'] ?? '')",
        ],
    ],
];

foreach ($actions as $action => $markers) {
    $block = pos_transaction_csrf_method_block($controllerSource, $action);
    $permissionPosition = strpos($block, '$this->require_permission(');
    $guardPosition = strpos($block, '$this->require_pos_transaction_csrf()');
    $payloadPosition = strpos($block, '$this->request_payload()');
    $idPosition = strpos($block, '$id =');
    $actionSelectorPosition = strpos($block, "\$action = \$id > 0 ? 'edit' : 'create';");
    $writerPosition = strpos($block, $markers['writer']);
    $actorPosition = strpos($block, '$this->current_actor_employee_id()');
    $guardCalls = preg_match_all('/\$this->require_pos_transaction_csrf\s*\(\s*\)/', $block);
    $permissionPositions = [];
    $permissionOffset = 0;
    while (($permissionAt = strpos($block, '$this->require_permission(', $permissionOffset)) !== false) {
        $permissionPositions[] = $permissionAt;
        $permissionOffset = $permissionAt + 1;
    }

    pos_transaction_csrf_check($block !== '', $action . ' exists in the controller');
    pos_transaction_csrf_check(
        $guardCalls === 1,
        $action . ' has exactly one scoped CSRF guard call'
    );
    pos_transaction_csrf_check(
        $permissionPosition !== false
            && $guardPosition !== false
            && $permissionPosition < $guardPosition,
        $action . ' checks RBAC before scoped CSRF'
    );
    if (!($markers['payload'] ?? true)) {
        pos_transaction_csrf_check(
            $payloadPosition === false,
            $action . ' does not read a request body before downstream work'
        );
        pos_transaction_csrf_check(
            $guardPosition !== false
                && $actorPosition !== false
                && $guardPosition < $actorPosition,
            $action . ' checks scoped CSRF before resolving the actor'
        );
    } else {
        pos_transaction_csrf_check(
            $guardPosition !== false
                && $payloadPosition !== false
                && $guardPosition < $payloadPosition,
            $action . ' checks scoped CSRF before request payload'
        );
    }
    if (!empty($markers['strictOrder'])) {
        pos_transaction_csrf_check(
            $permissionPosition !== false
                && $guardPosition !== false
                && $payloadPosition !== false
                && $writerPosition !== false
                && $permissionPosition < $guardPosition
                && $guardPosition < $payloadPosition
                && $payloadPosition < $writerPosition,
            $action . ' keeps permission -> guard -> request payload -> model order'
        );
    }
    if ($action === 'order_draft_delete') {
        pos_transaction_csrf_check(
            strpos($block, "\$pageCode = \$this->can('pos.cashier.index', 'delete') ? 'pos.cashier.index' : 'pos.order.draft.index';") !== false
                && strpos($block, "require_permission(\$pageCode, 'delete')") !== false
                && strpos($block, "'edit'") === false,
            $action . ' keeps the selector/fallback and uses delete permission'
        );
        pos_transaction_csrf_check(
            $guardPosition !== false
                && $actorPosition !== false
                && $guardPosition < $actorPosition,
            $action . ' checks scoped CSRF before resolving the actor'
        );
    }
    if (!empty($markers['actionGuard'])) {
        $actionPermissionPosition = $permissionPositions[1] ?? false;
        $viewPermissionMarker = $markers['viewPermission']
            ?? "\$this->require_permission(\$pageCode, 'view');";
        $actionPermissionMarker = $markers['actionPermission']
            ?? "\$this->require_permission(\$pageCode, \$action);";
        pos_transaction_csrf_check(
            count($permissionPositions) === 2
                && strpos($block, $viewPermissionMarker) !== false
                && $actionSelectorPosition !== false
                && strpos($block, $actionPermissionMarker) !== false,
            $action . ' keeps static RBAC and post-CSRF create/edit action guard'
        );
        pos_transaction_csrf_check(
            $guardPosition !== false
                && $payloadPosition !== false
                && $idPosition !== false
                && $actionSelectorPosition !== false
                && $actionPermissionPosition !== false
                && $guardPosition < $payloadPosition
                && $payloadPosition < $idPosition
                && $idPosition < $actionSelectorPosition
                && $actionSelectorPosition < $actionPermissionPosition
                && $actionPermissionPosition < $writerPosition,
            $action . ' keeps guard -> payload -> id -> action permission -> writer order'
        );
    }
    pos_transaction_csrf_check(
        $guardPosition !== false
            && $writerPosition !== false
            && $guardPosition < $writerPosition,
        $action . ' checks scoped CSRF before its writer'
    );
    foreach ($markers['extra'] as $marker) {
        $extraPosition = strpos($block, $marker);
        pos_transaction_csrf_check(
            $guardPosition !== false
                && $extraPosition !== false
                && $guardPosition < $extraPosition,
            $action . ' checks scoped CSRF before ' . $marker
        );
    }
    foreach (($markers['response'] ?? []) as $marker) {
        $responsePosition = strpos($block, $marker);
        pos_transaction_csrf_check(
            $writerPosition !== false
                && $responsePosition !== false
                && $writerPosition < $responsePosition,
            $action . ' preserves its existing response marker ' . $marker
        );
    }
    if (isset($markers['errorResponse'])) {
        $failurePosition = strpos($block, "if (!(\$result['ok'] ?? false)) {");
        $errorPosition = strpos($block, $markers['errorResponse']);
        $errorReturnPosition = $errorPosition === false
            ? false
            : strpos($block, 'return;', $errorPosition);
        $successPosition = strpos($block, '$this->json_ok([', $writerPosition === false ? 0 : $writerPosition);
        pos_transaction_csrf_check(
            $writerPosition !== false
                && $failurePosition !== false
                && $errorPosition !== false
                && $errorReturnPosition !== false
                && $successPosition !== false
                && $writerPosition < $failurePosition
                && $failurePosition < $errorPosition
                && $errorPosition < $errorReturnPosition
                && $errorReturnPosition < $successPosition,
            $action . ' preserves writer -> 422 error/return -> success response flow'
        );
    }
}

$guardStart = strpos($controllerSource, 'private function require_pos_transaction_csrf(): bool');
$helperStart = strpos($controllerSource, 'private function pos_transaction_csrf(): string');
$rejectStart = strpos($controllerSource, 'private function reject_pos_transaction_csrf(');
$guardSource = $guardStart === false
    ? ''
    : substr($controllerSource, $guardStart, ($rejectStart === false ? strlen($controllerSource) : $rejectStart) - $guardStart);
$helperSource = $helperStart === false
    ? ''
    : substr($controllerSource, $helperStart, ($guardStart === false ? strlen($controllerSource) : $guardStart) - $helperStart);
$rejectSource = $rejectStart === false ? '' : substr($controllerSource, $rejectStart);
$payloadSource = pos_transaction_csrf_method_block($controllerSource, 'request_payload');

pos_transaction_csrf_check(
    strpos($guardSource, '$this->input->method(true) !== \'POST\'') !== false
        && strpos($guardSource, 'reject_pos_transaction_csrf(405') !== false,
    'private guard rejects non-POST with 405'
);
pos_transaction_csrf_check(
    strpos($guardSource, 'get_request_header(self::POS_TRANSACTION_CSRF_CI_HEADER, true)') !== false
        && strpos($guardSource, 'preg_match(\'/\\A[0-9a-fA-F]{64}\\z/D\', $providedToken)') !== false
        && strpos($guardSource, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardSource, 'reject_pos_transaction_csrf(403') !== false,
    'private guard validates exact header/session token with hash_equals and rejects invalid values with 403'
);
pos_transaction_csrf_check(
    strpos($guardSource, '$this->input->get(') === false
        && strpos($guardSource, '$this->input->post(') === false
        && strpos($guardSource, 'raw_input_stream') === false,
    'private guard has no query/body token fallback or payload access'
);
pos_transaction_csrf_check(
    strpos($helperSource, 'userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY)') !== false
        && strpos($helperSource, 'set_userdata(self::POS_TRANSACTION_CSRF_SESSION_KEY, $token)') !== false
        && strpos($helperSource, 'preg_match(\'/\\A[0-9a-fA-F]{64}\\z/D\', $token)') !== false,
    'token helper reuses only a valid 64-hex session token'
);
pos_transaction_csrf_check(
    strpos($payloadSource, 'json_decode(') !== false,
    'request payload path includes JSON decode after the writer guard'
);
pos_transaction_csrf_check(
    strpos($rejectSource, "set_content_type('application/json')") !== false
        && strpos($rejectSource, 'set_status_header($statusCode)') !== false
        && strpos($rejectSource, "'ok' => false") !== false,
    'scoped rejection writes JSON status and body'
);

$viewRenderMarkers = [
    'cashier' => [
        'wrapperCalls' => 11,
        'targetCalls' => [
            "postPosTransactionJson('<?php echo site_url('pos/orders/payment/save'); ?>', payload)",
            "postPosTransactionJson('<?php echo site_url('pos/orders/draft/save'); ?>', payload)",
            "postPosTransactionJson('<?php echo site_url('pos/orders/draft/save-confirm'); ?>', buildOrderPayload())",
            "postPosTransactionJson('<?php echo site_url('pos/orders/reprint-step-up/verify'); ?>', {",
            'postPosTransactionJson(`<?php echo site_url(\'pos/orders/reprint-print-targets\'); ?>/${Number(order.id || 0)}`, {',
            'postPosTransactionJson(saveUrl, payload)',
            "postPosTransactionJson('<?php echo site_url('pos/cashier/open'); ?>', payload)",
            "postPosTransactionJson('<?php echo site_url('pos/cashier/close'); ?>', payload)",
            'await postPosTransactionJson(`<?php echo site_url(\'pos/orders/draft/delete\'); ?>/${Number(order.id || 0)}`, {})',
        ],
    ],
    'draft' => [
        'wrapperCalls' => 5,
        'targetCalls' => [
            "postPosTransactionJson('<?php echo site_url('pos/orders/draft/save'); ?>', payload)",
            "postPosTransactionJson('<?php echo site_url('pos/orders/draft/confirm'); ?>/' + order.id, {})",
            'await postPosTransactionJson(endpoint, payload)',
            'await postPosTransactionJson(`<?php echo site_url(\'pos/orders/draft/delete\'); ?>/${Number(order.id || 0)}`, {})',
        ],
    ],
    'paid' => [
        'wrapperCalls' => 2,
        'targetCalls' => [
            "postPosTransactionJson('<?php echo site_url('pos/orders/refund/save'); ?>', payload)",
            "postPosTransactionJson('<?php echo site_url('pos/orders/reversal-step-up/verify'); ?>', {",
        ],
    ],
    'reservation' => [
        'wrapperCalls' => 6,
        'directFetch' => true,
        'targetCalls' => [
            'postPosTransactionJson(urls.save,payload)',
            'postPosTransactionJson(`${urls.deposit}/${id}`,payload)',
            'postPosTransactionJson(`${urls.verify}/${id}`,{})',
            'postPosTransactionJson(urls.refundStepUp,{reservation_id:id,close_mode:state.closeMode,password})',
            'await postPosTransactionJson(`${endpoint}/${id}`,payload)',
        ],
        'legacyCalls' => [
            'request(urls.save,\'POST\',payload)',
            'request(`${urls.deposit}/${id}`,\'POST\',payload)',
            'request(`${urls.verify}/${id}`,\'POST\',{})',
        ],
    ],
    'self_order' => [
        'wrapperCalls' => 4,
        'directFetch' => true,
        'targetCalls' => [
            'postPosTransactionJson(`<?php echo site_url(\'pos/orders/runtime-jobs/trigger\'); ?>/${safeOrderId}`, { job_id: safeJobId, limit: 1 })',
            'postPosTransactionJson(`<?php echo site_url(\'pos/orders/runtime-sync\'); ?>/${safeOrderId}`, {',
            'postPosTransactionJson(`<?php echo site_url(\'pos/self-order/orders/verify\'); ?>/${Number(verifyRow.id || 0)}`, {',
            'postPosTransactionJson(`<?php echo site_url(\'pos/self-order/orders/reject\'); ?>/${Number(rejectRow.id || 0)}`, { reason })',
        ],
        'legacyCalls' => [
            'postJson(`<?php echo site_url(\'pos/orders/runtime-jobs/trigger\'); ?>/${safeOrderId}`, { job_id: safeJobId, limit: 1 })',
            'postJson(`<?php echo site_url(\'pos/orders/runtime-sync\'); ?>/${safeOrderId}`, {',
            'postJson(`<?php echo site_url(\'pos/self-order/orders/verify\'); ?>/${Number(verifyRow.id || 0)}`, {',
            'postJson(`<?php echo site_url(\'pos/self-order/orders/reject\'); ?>/${Number(rejectRow.id || 0)}`, { reason })',
        ],
    ],
    'online_food' => [
        'wrapperCalls' => 4,
        'directFetch' => true,
        'targetCalls' => [
            'postPosTransactionJson(`<?php echo site_url(\'pos/orders/runtime-jobs/trigger\'); ?>/${safeOrderId}`, { job_id: safeJobId, limit: 1 })',
            'postPosTransactionJson(`<?php echo site_url(\'pos/orders/runtime-sync\'); ?>/${safeOrderId}`, {',
            'postPosTransactionJson(`<?php echo site_url(\'pos/online-food/orders/verify\'); ?>/${Number(verifyRow.id || 0)}`, {',
            'postPosTransactionJson(`<?php echo site_url(\'pos/online-food/orders/reject\'); ?>/${Number(rejectRow.id || 0)}`, { reason })',
        ],
        'legacyCalls' => [
            'postJson(`<?php echo site_url(\'pos/orders/runtime-jobs/trigger\'); ?>/${safeOrderId}`, { job_id: safeJobId, limit: 1 })',
            'postJson(`<?php echo site_url(\'pos/orders/runtime-sync\'); ?>/${safeOrderId}`, {',
            'postJson(`<?php echo site_url(\'pos/online-food/orders/verify\'); ?>/${Number(verifyRow.id || 0)}`, {',
            'postJson(`<?php echo site_url(\'pos/online-food/orders/reject\'); ?>/${Number(rejectRow.id || 0)}`, { reason })',
        ],
    ],
];

foreach ($viewSources as $name => $viewSource) {
    $wrapperSource = pos_transaction_csrf_view_wrapper_block($viewSource);
    pos_transaction_csrf_check(
        strpos($viewSource, 'pos_transaction_csrf_token') !== false,
        $name . ' view receives the rendered transaction token'
    );
    pos_transaction_csrf_check(
        $wrapperSource !== '',
        $name . ' view defines the transaction wrapper'
    );
    $wrapperHasHeader = !empty($viewRenderMarkers[$name]['directFetch'])
        ? strpos($wrapperSource, "'X-Pos-Transaction-CSRF':posTransactionCsrfToken") !== false
            || strpos($wrapperSource, "'X-Pos-Transaction-CSRF': posTransactionCsrfToken") !== false
        : preg_match(
            '~postJson\s*\(\s*url\s*,\s*payload\s*,\s*\{\s*headers\s*:\s*\{\s*[\'\"]X-Pos-Transaction-CSRF[\'\"]\s*:\s*posTransactionCsrfToken\s*\}\s*\}\s*\)~',
            $wrapperSource
        ) === 1;
    pos_transaction_csrf_check(
        $wrapperHasHeader,
        $name . ' view wrapper sends the browser transaction header'
    );
    pos_transaction_csrf_check(
        pos_transaction_csrf_wrapper_invocation_count($viewSource) === $viewRenderMarkers[$name]['wrapperCalls'],
        $name . ' view has the expected invocation-only scoped wrapper count'
    );
    $globalPostJsonStart = strpos($viewSource, 'async function postJson(');
    if ($globalPostJsonStart === false) {
        $globalPostJsonStart = strpos($viewSource, 'function postJson(');
    }
    $wrapperStart = strpos($viewSource, 'function postPosTransactionJson(');
    $globalPostJsonSource = $globalPostJsonStart === false
        ? ''
        : substr($viewSource, $globalPostJsonStart, ($wrapperStart === false ? strlen($viewSource) : $wrapperStart) - $globalPostJsonStart);
    if ($name !== 'reservation') {
        pos_transaction_csrf_check(
            $globalPostJsonSource !== ''
                && strpos($globalPostJsonSource, $headerName) === false,
            $name . ' shared postJson helper exists and does not add the scoped transaction header'
        );
    }
    foreach ($viewRenderMarkers[$name]['targetCalls'] as $targetCall) {
        pos_transaction_csrf_check(
            strpos($viewSource, $targetCall) !== false,
            $name . ' view keeps its target transaction call inside the wrapper'
        );
    }
    foreach (($viewRenderMarkers[$name]['legacyCalls'] ?? []) as $legacyCall) {
        pos_transaction_csrf_check(
            strpos($viewSource, $legacyCall) === false,
            $name . ' verification caller no longer uses the unscoped POST helper'
        );
    }
}

$reservationRequestSource = pos_transaction_csrf_view_request_block($viewSources['reservation']);
pos_transaction_csrf_check(
    $reservationRequestSource !== ''
        && strpos($reservationRequestSource, "'X-Requested-With':'XMLHttpRequest'") !== false
        && strpos($reservationRequestSource, $headerName) === false
        && strpos($reservationRequestSource, 'posTransactionCsrfToken') === false,
    'reservation generic request helper remains neutral and does not send the transaction token'
);
pos_transaction_csrf_check(
    strpos($viewSources['reservation'], 'const data=await request(`${urls.detail}/${id}`)') !== false
        && strpos($viewSources['reservation'], 'const data=await request(`${endpoint}?${statusQuery()}`,\'GET\',null,') !== false,
    'reservation read-only detail and list callers keep using the generic request helper'
);
$reservationSaveCallerSource = pos_transaction_csrf_view_function_block(
    $viewSources['reservation'],
    'saveReservation'
);
pos_transaction_csrf_check(
    $reservationSaveCallerSource !== ''
        && substr_count($reservationSaveCallerSource, 'postPosTransactionJson(') === 1
        && strpos($reservationSaveCallerSource, 'const data=await postPosTransactionJson(urls.save,payload)') !== false
        && strpos($reservationSaveCallerSource, 'request(urls.save') === false
        && strpos($reservationSaveCallerSource, $headerName) === false,
    'reservation save caller alone uses the scoped wrapper without duplicating its header or generic POST helper'
);

pos_transaction_csrf_check(
    strpos($viewSources['cashier'], "pos/orders/refund/save") === false,
    'cashier view does not target the refund writer'
);
pos_transaction_csrf_check(
    strpos($viewSources['draft'], "pos/orders/refund/save") === false,
    'draft view does not target the refund writer'
);
pos_transaction_csrf_check(
    strpos($viewSources['paid'], "pos/orders/payment/save") === false
        && strpos($viewSources['paid'], "pos/orders/void/save") === false,
    'paid view does not target payment or void writers'
);

$writerMethodByAction = [
    'order_payment_save' => 'save_cashier_payment',
    'order_void_save' => 'save_order_void',
    'order_refund_save' => 'save_order_refund',
    'order_reprint_print_targets' => 'direct_print_targets_for_order_reprint',
    'cashier_open' => 'open_cashier_session',
    'cashier_close' => 'close_cashier_session',
    'order_draft_delete' => 'delete_order_draft',
    'order_draft_save' => 'save_order_draft',
    'order_draft_confirm' => 'finalize_order_confirmation',
    'order_draft_save_confirm' => 'save_order_draft',
    'reservation_verify' => 'finalize_self_order_verification',
    'self_order_order_verify' => 'finalize_self_order_verification',
    'self_order_order_reject' => 'reject_self_order_order',
    'online_food_order_verify' => 'finalize_self_order_verification',
    'online_food_order_reject' => 'reject_online_food_order',
];

foreach ($writerMethodByAction as $action => $writerMethod) {
    $actionArguments = in_array($action, [
        'order_draft_delete',
        'order_draft_confirm',
        'order_reprint_print_targets',
        'reservation_verify',
        'self_order_order_verify',
        'self_order_order_reject',
        'online_food_order_verify',
        'online_food_order_reject',
    ], true) ? [1701] : [];
    if (in_array($action, ['self_order_order_verify', 'online_food_order_verify'], true)) {
        $actionRawInput = '{"verify_destination":"PAID_ORDER","sentinel":true}';
    } elseif (in_array($action, ['self_order_order_reject', 'online_food_order_reject'], true)) {
        $actionRawInput = '{"reason":"  Alasan smoke  ","sentinel":true}';
    } elseif (in_array($action, ['order_void_save', 'order_refund_save', 'order_reprint_print_targets'], true)) {
        $actionRawInput = '{"order_id":1701,"step_up_proof":"' . str_repeat('a', 64) . '","sentinel":true}';
    } else {
        $actionRawInput = '{"sentinel":true}';
    }
    [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
        $action,
        'GET',
        [],
        [$sessionKey => $validToken],
        $actionArguments,
        $actionRawInput
    );
    pos_transaction_csrf_check($exception === null, $action . ' GET completes at the guard');
    pos_transaction_csrf_json_rejection($output, 405, $action . ' GET');
    pos_transaction_csrf_check($input->events === ['method'], $action . ' GET reads no header, session, or payload');
    pos_transaction_csrf_check($session->reads === [], $action . ' GET reads no session token');
    pos_transaction_csrf_check(
        $model->writerCalls === [] && $model->calls === [],
        $action . ' GET reaches no model dependency or writer'
    );
    if (in_array($action, ['reservation_verify', 'self_order_order_verify', 'online_food_order_verify'], true)) {
        pos_transaction_csrf_check(
            $model->calls === []
                && $controller->posReservationModel->calls === []
                && $controller->load->libraryCalls === []
                && $controller->posStockCommitService->calls === []
                && $controller->posRuntimeJobService->calls === [],
            $action . ' GET reaches no verification helper dependency'
        );
    }
    pos_transaction_csrf_check(
        in_array('permission', $controller->events, true),
        $action . ' GET runs the guard after RBAC'
    );
    if ($action === 'order_draft_delete') {
        pos_transaction_csrf_check(
            $controller->canCalls === [['pos.cashier.index', 'delete']]
                && $controller->permissionCalls === [['pos.cashier.index', 'delete']],
            $action . ' GET selects the existing fallback page with delete permission'
        );
    }
    if (in_array($action, ['self_order_order_reject', 'online_food_order_reject'], true)) {
        $expectedPage = $action === 'online_food_order_reject'
            ? 'pos.online_food.index'
            : 'pos.self_order.index';
        pos_transaction_csrf_check(
            $controller->permissionCalls === [[$expectedPage, 'edit']],
            $action . ' GET preserves its edit permission before the scoped guard'
        );
    }

    $invalidCases = [
        'missing header' => [[], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
        'empty header' => [[$headerName => ''], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
        'malformed header' => [[$headerName => 'malformed'], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
        'missing session' => [[$headerName => $validToken], [], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
        'malformed session' => [[$headerName => $validToken], [$sessionKey => 'malformed'], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
        'mismatched token' => [[$headerName => $otherToken], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
    ];

    foreach ($invalidCases as $case => [$headers, $sessionValues, $expectedInputEvents, $expectedReads]) {
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            $action,
            'POST',
            $headers,
            $sessionValues,
            $actionArguments,
            $actionRawInput
        );
        pos_transaction_csrf_check($exception === null, $action . ' ' . $case . ' completes at the guard');
        pos_transaction_csrf_json_rejection($output, 403, $action . ' ' . $case);
        pos_transaction_csrf_check(
            $input->events === $expectedInputEvents,
            $action . ' ' . $case . ' reads no payload before rejection'
        );
        pos_transaction_csrf_check(
            $session->reads === $expectedReads,
            $action . ' ' . $case . ' reads session only for a valid-looking header'
        );
        pos_transaction_csrf_check($model->writerCalls === [], $action . ' ' . $case . ' reaches no writer');
        pos_transaction_csrf_check($model->calls === [], $action . ' ' . $case . ' reaches no model/query dependency');
        if (in_array($action, ['reservation_verify', 'self_order_order_verify', 'online_food_order_verify'], true)) {
            pos_transaction_csrf_check(
                $controller->posReservationModel->calls === []
                    && $controller->load->libraryCalls === []
                    && $controller->posStockCommitService->calls === []
                    && $controller->posRuntimeJobService->calls === [],
                $action . ' ' . $case . ' reaches no verification helper or service dependency'
            );
        }
        if (in_array($action, ['order_draft_save', 'order_draft_save_confirm'], true)) {
            pos_transaction_csrf_check(
                $controller->permissionCalls === [['pos.cashier.index', 'view']],
                $action . ' ' . $case . ' does not evaluate body-derived create/edit permission'
            );
        }
        if ($action === 'order_draft_confirm') {
            pos_transaction_csrf_check(
                $controller->load->libraryCalls === []
                    && $controller->posStockCommitService->calls === []
                    && $controller->posRuntimeJobService->calls === [],
                $action . ' ' . $case . ' reaches no confirmation service downstream'
            );
        }
        pos_transaction_csrf_check(
            in_array('permission', $controller->events, true),
            $action . ' ' . $case . ' runs after RBAC'
        );
        if (in_array($action, ['self_order_order_reject', 'online_food_order_reject'], true)) {
            $expectedPage = $action === 'online_food_order_reject'
                ? 'pos.online_food.index'
                : 'pos.self_order.index';
            pos_transaction_csrf_check(
                $controller->permissionCalls === [[$expectedPage, 'edit']],
                $action . ' ' . $case . ' preserves its edit permission before rejection'
            );
        }
    }

    foreach ([
        'browser header' => $headerName,
        'canonical CI header' => $canonicalHeaderName,
    ] as $label => $providedHeaderName) {
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            $action,
            'POST',
            [$providedHeaderName => $validToken],
            [$sessionKey => $validToken],
            $actionArguments,
            $actionRawInput
        );
        pos_transaction_csrf_check(
            $exception instanceof RuntimeException
                && $exception->getMessage() === 'writer reached: ' . $writerMethod,
            $action . ' valid ' . $label . ' reaches its writer stub'
        );
        pos_transaction_csrf_check(
            ($model->writerCalls[$writerMethod] ?? 0) === 1,
            $action . ' valid ' . $label . ' reaches the writer exactly once'
        );
        pos_transaction_csrf_check(
            $input->events === (
                !($actions[$action]['payload'] ?? true)
                    ? ['method', 'header:' . $canonicalHeaderName]
                    : ['method', 'header:' . $canonicalHeaderName, 'property:raw_input_stream']
            ),
            $action . ' valid ' . $label . ' uses the canonical header and expected payload path'
        );
        pos_transaction_csrf_check(
            $session->reads === [$sessionKey]
                && $output->status === null,
            $action . ' valid ' . $label . ' remains session-bound before downstream side effects'
        );
        if (in_array($action, ['reservation_verify', 'self_order_order_verify', 'online_food_order_verify'], true)) {
            $finalizeArguments = $model->writerArguments['finalize_self_order_verification'][0] ?? [];
            $expectedOrderId = $action === 'reservation_verify' ? 2702 : 1701;
            $expectedOrderLabel = $action === 'reservation_verify'
                ? 'Reservasi'
                : ($action === 'online_food_order_verify' ? 'Online Food' : 'Self Order');
            $expectedEventPrefix = $action === 'reservation_verify'
                ? 'RESERVATION'
                : ($action === 'online_food_order_verify' ? 'ONLINE_FOOD' : 'SELF_ORDER');
            pos_transaction_csrf_check(
                ($finalizeArguments[0] ?? null) === $expectedOrderId
                    && ($finalizeArguments[1] ?? null) === 1
                    && ($finalizeArguments[2] ?? null) === 1
                    && ($finalizeArguments[3]['order_label'] ?? null) === $expectedOrderLabel
                    && ($finalizeArguments[3]['event_prefix'] ?? null) === $expectedEventPrefix,
                $action . ' valid ' . $label . ' preserves helper order, snapshot, actor, label, and event arguments'
            );
            pos_transaction_csrf_check(
                $controller->load->libraryCalls === [
                    ['PosStockCommitService', null],
                    ['PosRuntimeJobService', null],
                ]
                    && $controller->posStockCommitService->calls === ['create_snapshot', 'mark_queued']
                    && $controller->posRuntimeJobService->calls === ['queue_order_confirm_commit'],
                $action . ' valid ' . $label . ' retains the existing verification helper service flow'
            );
        }
        if ($action === 'reservation_verify') {
            pos_transaction_csrf_check(
                $controller->permissionCalls === [['pos.reservation.index', 'edit']]
                    && ($controller->posReservationModel->arguments['prepare_verification'] ?? []) === [[1701, 1, 2]],
                $action . ' valid ' . $label . ' preserves route ID and employee/user actor attribution'
            );
            pos_transaction_csrf_check(
                $input->events === ['method', 'header:' . $canonicalHeaderName]
                    && $controller->posReservationModel->calls === ['prepare_verification']
                    && $model->calls === ['resolve_order_stock_commit_payload', 'finalize_self_order_verification'],
                $action . ' valid ' . $label . ' invokes the reservation helper without reading a payload'
            );
        }
        if (in_array($action, ['self_order_order_verify', 'online_food_order_verify'], true)) {
            $expectedPage = $action === 'online_food_order_verify' ? 'pos.online_food.index' : 'pos.self_order.index';
            $expectedContextMethod = $action === 'online_food_order_verify'
                ? 'online_food_verification_context'
                : 'self_order_verification_context';
            pos_transaction_csrf_check(
                $controller->permissionCalls === [[$expectedPage, 'edit']]
                    && $model->calls === [
                        $expectedContextMethod,
                        'resolve_order_stock_commit_payload',
                        'finalize_self_order_verification',
                    ],
                $action . ' valid ' . $label . ' preserves RBAC and context helper selection'
            );
            pos_transaction_csrf_check(
                ($finalizeArguments[3]['verify_destination'] ?? null) === 'PAID_ORDER'
                    && ($finalizeArguments[3]['payment_mode'] ?? null) === 'QRIS'
                    && ($finalizeArguments[3]['payment_status'] ?? null) === 'PAID'
                    && ($finalizeArguments[3]['payment'] ?? null) === ['id' => 91],
                $action . ' valid ' . $label . ' preserves the parsed verification payload and payment context'
            );
        }
        if (in_array($action, ['self_order_order_reject', 'online_food_order_reject'], true)) {
            $expectedPage = $action === 'online_food_order_reject'
                ? 'pos.online_food.index'
                : 'pos.self_order.index';
            pos_transaction_csrf_check(
                $controller->permissionCalls === [[$expectedPage, 'edit']]
                    && $model->calls === [$writerMethod],
                $action . ' valid ' . $label . ' preserves RBAC and reaches only its reject writer'
            );
            pos_transaction_csrf_check(
                ($model->writerArguments[$writerMethod] ?? []) === [[1701, 1, 'Alasan smoke']],
                $action . ' valid ' . $label . ' preserves route ID, actor, and trimmed rejection reason'
            );
        }
        if (in_array($action, ['cashier_open', 'cashier_close'], true)) {
            pos_transaction_csrf_check(
                $model->calls === ['daily_recon_gate_status', $writerMethod],
                $action . ' valid ' . $label . ' reaches daily recon then its writer without print access'
            );
        }
        if ($action === 'order_draft_confirm') {
            pos_transaction_csrf_check(
                $model->calls === ['resolve_order_stock_commit_payload', $writerMethod],
                $action . ' valid ' . $label . ' reaches confirmation resolution then its final writer'
            );
            pos_transaction_csrf_check(
                $controller->permissionCalls === [['pos.cashier.index', 'edit']],
                $action . ' valid ' . $label . ' keeps the temporary confirm edit permission'
            );
        }
        if (in_array($action, ['order_draft_save', 'order_draft_save_confirm'], true)) {
            pos_transaction_csrf_check(
                $controller->permissionCalls === [
                    ['pos.cashier.index', 'view'],
                    ['pos.cashier.index', 'create'],
                ],
                $action . ' valid ' . $label . ' uses create permission for a new body id'
            );
        }
        if ($action === 'order_draft_delete') {
            pos_transaction_csrf_check(
                $controller->canCalls === [['pos.cashier.index', 'delete']]
                    && $controller->permissionCalls === [['pos.cashier.index', 'delete']],
                $action . ' valid ' . $label . ' uses delete permission, not edit'
            );
            pos_transaction_csrf_check(
                $model->calls === ['delete_order_draft']
                    && ($model->writerArguments[$writerMethod] ?? []) === [[1701, 1]],
                $action . ' valid ' . $label . ' preserves the route ID and actor arguments without payload access'
            );
        }
    }
}

$reservationWriterCases = [
    'reservation_deposit' => [
        'writer' => 'add_deposit',
        'permission' => 'edit',
        'rawInput' => '{"deposit":{"amount":125000,"payment_method_id":7,"reference_no":"REF-SMOKE"},"sentinel":true}',
        'expectedArguments' => [
            1701,
            [
                'deposit' => [
                    'amount' => 125000,
                    'payment_method_id' => 7,
                    'reference_no' => 'REF-SMOKE',
                ],
                'sentinel' => true,
            ],
            1,
            2,
        ],
    ],
    'reservation_reject' => [
        'writer' => 'reject_reservation',
        'permission' => 'edit',
        'rawInput' => '{"reason":"  Alasan smoke  ","refund_deposit":true,"step_up_proof":"' . str_repeat('a', 64) . '","sentinel":true}',
        'expectedArguments' => [1701, 1, 2, 'Alasan smoke', true],
    ],
    'reservation_cancel' => [
        'writer' => 'cancel_reservation',
        'permission' => 'delete',
        'rawInput' => '{"reason":"  Batal smoke  ","refund_deposit":false,"sentinel":true}',
        'expectedArguments' => [1701, 1, 2, 'Batal smoke', false],
    ],
];

foreach ($reservationWriterCases as $action => $caseConfig) {
    foreach (['GET', 'PUT'] as $requestMethod) {
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            $action,
            $requestMethod,
            [$headerName => $validToken],
            [$sessionKey => $validToken],
            [1701],
            $caseConfig['rawInput']
        );
        pos_transaction_csrf_check(
            $exception === null,
            $action . ' ' . $requestMethod . ' completes at the guard'
        );
        pos_transaction_csrf_json_rejection($output, 405, $action . ' ' . $requestMethod);
        pos_transaction_csrf_check(
            $input->events === ['method'] && $session->reads === [],
            $action . ' ' . $requestMethod . ' reads no header, session, or payload'
        );
        pos_transaction_csrf_check(
            $model->calls === [] && $controller->posReservationModel->calls === [],
            $action . ' ' . $requestMethod . ' reaches no model dependency'
        );
        pos_transaction_csrf_check(
            $controller->permissionCalls === [['pos.reservation.index', $caseConfig['permission']]],
            $action . ' ' . $requestMethod . ' runs the scoped guard after its existing permission'
        );
    }

    $invalidCases = [
        'missing header' => [[], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
        'empty header' => [[$headerName => ''], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
        'malformed header' => [[$headerName => 'malformed'], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
        'missing session' => [[$headerName => $validToken], [], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
        'malformed session' => [[$headerName => $validToken], [$sessionKey => 'malformed'], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
        'mismatched token' => [[$headerName => $otherToken], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
    ];

    foreach ($invalidCases as $label => [$headers, $sessionValues, $expectedInputEvents, $expectedSessionReads]) {
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            $action,
            'POST',
            $headers,
            $sessionValues,
            [1701],
            $caseConfig['rawInput']
        );
        pos_transaction_csrf_check($exception === null, $action . ' ' . $label . ' completes at the guard');
        pos_transaction_csrf_json_rejection($output, 403, $action . ' ' . $label);
        pos_transaction_csrf_check(
            $input->events === $expectedInputEvents && $session->reads === $expectedSessionReads,
            $action . ' ' . $label . ' stops before request payload access'
        );
        pos_transaction_csrf_check(
            $model->calls === [] && $controller->posReservationModel->calls === [],
            $action . ' ' . $label . ' reaches no model dependency'
        );
        pos_transaction_csrf_check(
            $controller->permissionCalls === [['pos.reservation.index', $caseConfig['permission']]],
            $action . ' ' . $label . ' runs the scoped guard after its existing permission'
        );
    }

    foreach ([
        'browser header' => $headerName,
        'canonical CI header' => $canonicalHeaderName,
    ] as $label => $providedHeaderName) {
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            $action,
            'POST',
            [$providedHeaderName => $validToken],
            [$sessionKey => $validToken],
            [1701],
            $caseConfig['rawInput']
        );
        $writerMethod = $caseConfig['writer'];
        pos_transaction_csrf_check(
            $exception instanceof RuntimeException
                && $exception->getMessage() === 'writer reached: ' . $writerMethod,
            $action . ' valid ' . $label . ' reaches its reservation writer stub'
        );
        pos_transaction_csrf_check(
            $model->calls === []
                && $controller->posReservationModel->calls === [$writerMethod]
                && ($controller->posReservationModel->writerCalls[$writerMethod] ?? 0) === 1,
            $action . ' valid ' . $label . ' reaches only its reservation writer exactly once'
        );
        pos_transaction_csrf_check(
            ($controller->posReservationModel->writerArguments[$writerMethod][0] ?? null)
                === $caseConfig['expectedArguments'],
            $action . ' valid ' . $label . ' preserves route, payload, and employee/user actor arguments'
        );
        pos_transaction_csrf_check(
            $input->events === [
                'method',
                'header:' . $canonicalHeaderName,
                'property:raw_input_stream',
            ]
                && $session->reads === [$sessionKey]
                && $output->status === null,
            $action . ' valid ' . $label . ' parses payload only after the session-bound guard'
        );
        pos_transaction_csrf_check(
            $controller->permissionCalls === [['pos.reservation.index', $caseConfig['permission']]],
            $action . ' valid ' . $label . ' preserves its existing permission action'
        );
    }
}

$reservationSaveSentinel = [
    'id' => 1701,
    'customer_name' => 'Reservation Smoke',
    'lines' => [['product_id' => 901, 'qty' => 2]],
    'sentinel' => 'reservation-save-smoke',
];
$reservationSaveRawInput = (string)json_encode($reservationSaveSentinel);

foreach (['GET', 'PUT'] as $requestMethod) {
    [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
        'reservation_save',
        $requestMethod,
        [],
        [],
        [],
        $reservationSaveRawInput
    );
    pos_transaction_csrf_check($exception === null, 'reservation_save ' . $requestMethod . ' completes at the guard');
    pos_transaction_csrf_json_rejection($output, 405, 'reservation_save ' . $requestMethod);
    pos_transaction_csrf_check(
        $input->events === ['method'] && $session->reads === [],
        'reservation_save ' . $requestMethod . ' reads no header, session, or payload'
    );
    pos_transaction_csrf_check(
        $model->calls === [] && $controller->posReservationModel->calls === [],
        'reservation_save ' . $requestMethod . ' reaches no model dependency or writer'
    );
    pos_transaction_csrf_check(
        $controller->permissionCalls === [['pos.reservation.index', 'view']],
        'reservation_save ' . $requestMethod . ' runs the method guard after view permission only'
    );
}

$reservationSaveInvalidCases = [
    'missing header' => [[], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
    'empty header' => [[$headerName => ''], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
    'malformed header' => [[$headerName => 'malformed'], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], []],
    'missing session' => [[$headerName => $validToken], [], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
    'malformed session' => [[$headerName => $validToken], [$sessionKey => 'malformed'], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
    'mismatched token' => [[$headerName => $otherToken], [$sessionKey => $validToken], ['method', 'header:' . $canonicalHeaderName], [$sessionKey]],
];

foreach ($reservationSaveInvalidCases as $label => [$headers, $sessionValues, $expectedInputEvents, $expectedSessionReads]) {
    [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
        'reservation_save',
        'POST',
        $headers,
        $sessionValues,
        [],
        $reservationSaveRawInput
    );
    pos_transaction_csrf_check($exception === null, 'reservation_save ' . $label . ' completes at the guard');
    pos_transaction_csrf_json_rejection($output, 403, 'reservation_save ' . $label);
    pos_transaction_csrf_check(
        $input->events === $expectedInputEvents && $session->reads === $expectedSessionReads,
        'reservation_save ' . $label . ' rejects before request payload access'
    );
    pos_transaction_csrf_check(
        $model->calls === [] && $controller->posReservationModel->calls === [],
        'reservation_save ' . $label . ' reaches no model dependency or writer'
    );
    pos_transaction_csrf_check(
        $controller->permissionCalls === [['pos.reservation.index', 'view']],
        'reservation_save ' . $label . ' does not derive a body-based action permission'
    );
}

$reservationSaveExpectedResponse = [
    'ok' => true,
    'id' => 2901,
    'reservation_no' => 'RSV-SMOKE',
    'deposit' => ['amount' => 125000],
    'reservation' => ['id' => 2901, 'sentinel' => 'response-smoke'],
];
foreach ([
    'browser header' => $headerName,
    'canonical CI header' => $canonicalHeaderName,
] as $headerLabel => $providedHeaderName) {
    foreach ([0, 1701] as $payloadId) {
        $expectedPayload = $reservationSaveSentinel;
        $expectedPayload['id'] = $payloadId;
        $rawInput = (string)json_encode($expectedPayload);
        $expectedAction = $payloadId > 0 ? 'edit' : 'create';
        $caseLabel = 'reservation_save valid ' . $headerLabel . ' id=' . $payloadId;
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            'reservation_save',
            'POST',
            [$providedHeaderName => $validToken],
            [$sessionKey => $validToken],
            [],
            $rawInput
        );
        pos_transaction_csrf_check(
            $exception instanceof PosTransactionCsrfSmokeResponseComplete,
            $caseLabel . ' completes through the JSON response tripwire'
        );
        pos_transaction_csrf_check(
            $model->calls === []
                && $controller->posReservationModel->calls === ['save_reservation']
                && ($controller->posReservationModel->writerCalls['save_reservation'] ?? 0) === 1,
            $caseLabel . ' reaches only save_reservation exactly once'
        );
        pos_transaction_csrf_check(
            ($controller->posReservationModel->writerArguments['save_reservation'] ?? [])
                === [[$expectedPayload, 1, 2]],
            $caseLabel . ' preserves payload and employee/user actor writer arguments'
        );
        pos_transaction_csrf_check(
            $controller->permissionCalls === [
                ['pos.reservation.index', 'view'],
                ['pos.reservation.index', $expectedAction],
            ],
            $caseLabel . ' selects view then canonical ' . $expectedAction . ' permission'
        );
        pos_transaction_csrf_check(
            $input->events === [
                'method',
                'header:' . $canonicalHeaderName,
                'property:raw_input_stream',
            ] && $session->reads === [$sessionKey],
            $caseLabel . ' reads the body only after the session-bound guard'
        );
        pos_transaction_csrf_check(
            $output->status === 200
                && $output->contentType === 'application/json'
                && json_decode($output->body, true) === $reservationSaveExpectedResponse,
            $caseLabel . ' preserves the legacy success response contract'
        );
    }
}

foreach (['order_draft_save', 'order_draft_save_confirm'] as $action) {
    foreach ([0, 1701] as $payloadId) {
        $rawInput = (string)json_encode(['id' => $payloadId, 'sentinel' => true]);
        [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
            $action,
            'POST',
            [$headerName => $validToken],
            [$sessionKey => $validToken],
            [],
            $rawInput
        );
        $expectedAction = $payloadId > 0 ? 'edit' : 'create';
        pos_transaction_csrf_check(
            $exception instanceof RuntimeException
                && $exception->getMessage() === 'writer reached: save_order_draft',
            $action . ' id=' . $payloadId . ' reaches its actual writer after CSRF/action guard'
        );
        pos_transaction_csrf_check(
            ($model->writerCalls['save_order_draft'] ?? 0) === 1
                && $model->calls === ['save_order_draft'],
            $action . ' id=' . $payloadId . ' reaches the draft writer exactly once with no downstream call'
        );
        pos_transaction_csrf_check(
            $controller->permissionCalls === [
                ['pos.cashier.index', 'view'],
                ['pos.cashier.index', $expectedAction],
            ],
            $action . ' id=' . $payloadId . ' selects ' . $expectedAction . ' permission after CSRF'
        );
        pos_transaction_csrf_check(
            $input->events === ['method', 'header:' . $canonicalHeaderName, 'property:raw_input_stream']
                && $session->reads === [$sessionKey],
            $action . ' id=' . $payloadId . ' parses the body only after the scoped token check'
        );
        pos_transaction_csrf_check(
            isset($model->writerArguments['save_order_draft'][0][0]['id'])
                && (int)$model->writerArguments['save_order_draft'][0][0]['id'] === $payloadId,
            $action . ' id=' . $payloadId . ' passes the parsed id only after authorization'
        );
    }
}

foreach ([
    'order_draft_save',
    'order_draft_confirm',
    'order_draft_save_confirm',
    'reservation_verify',
    'self_order_order_verify',
    'self_order_order_reject',
    'online_food_order_verify',
    'online_food_order_reject',
] as $action) {
    $actionArguments = in_array($action, [
        'order_draft_confirm',
        'reservation_verify',
        'self_order_order_verify',
        'self_order_order_reject',
        'online_food_order_verify',
        'online_food_order_reject',
    ], true) ? [1701] : [];
    [$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
        $action,
        'PUT',
        [$headerName => $validToken],
        [$sessionKey => $validToken],
        $actionArguments
    );
    pos_transaction_csrf_check($exception === null, $action . ' non-POST completes at the guard');
    pos_transaction_csrf_json_rejection($output, 405, $action . ' non-POST');
    pos_transaction_csrf_check($input->events === ['method'], $action . ' non-POST reads no header or body');
    pos_transaction_csrf_check($session->reads === [], $action . ' non-POST reads no session token');
    pos_transaction_csrf_check($model->calls === [], $action . ' non-POST reaches no model/downstream dependency');
    if (in_array($action, ['reservation_verify', 'self_order_order_verify', 'online_food_order_verify'], true)) {
        pos_transaction_csrf_check(
            $controller->posReservationModel->calls === []
                && $controller->load->libraryCalls === []
                && $controller->posStockCommitService->calls === []
                && $controller->posRuntimeJobService->calls === [],
            $action . ' non-POST reaches no verification helper or service dependency'
        );
    }
    if (in_array($action, ['self_order_order_reject', 'online_food_order_reject'], true)) {
        $expectedPage = $action === 'online_food_order_reject'
            ? 'pos.online_food.index'
            : 'pos.self_order.index';
        pos_transaction_csrf_check(
            $controller->permissionCalls === [[$expectedPage, 'edit']],
            $action . ' non-POST preserves its edit permission before the scoped guard'
        );
    }
}

[$controller, $input, $session, $output, $model, $exception] = pos_transaction_csrf_invoke_action(
    'order_draft_delete',
    'PUT',
    [$headerName => $validToken],
    [$sessionKey => $validToken],
    [1701]
);
pos_transaction_csrf_check($exception === null, 'order_draft_delete non-POST completes at the guard');
pos_transaction_csrf_json_rejection($output, 405, 'order_draft_delete non-POST');
pos_transaction_csrf_check($input->events === ['method'], 'order_draft_delete non-POST reads no header or payload');
pos_transaction_csrf_check($session->reads === [], 'order_draft_delete non-POST reads no session token');
pos_transaction_csrf_check($model->calls === [], 'order_draft_delete non-POST reaches no model dependency');
pos_transaction_csrf_check(
    $controller->canCalls === [['pos.cashier.index', 'delete']]
        && $controller->permissionCalls === [['pos.cashier.index', 'delete']],
    'order_draft_delete non-POST runs after RBAC with delete permission'
);

[$controller, $input, $session, $output, $model, $reflection] = pos_transaction_csrf_controller(
    'POST',
    [$headerName => $validToken],
    [$sessionKey => $validToken]
);
$guard = $reflection->getMethod('require_pos_transaction_csrf');
$guard->setAccessible(true);
$guardResult = $guard->invoke($controller);
pos_transaction_csrf_check($guardResult === true, 'valid session-bound token is accepted by the private guard');
pos_transaction_csrf_check(
    $input->events === ['method', 'header:' . $canonicalHeaderName]
        && $session->reads === [$sessionKey],
    'valid private guard reads only method, scoped header, and scoped session token'
);
pos_transaction_csrf_check($output->status === null, 'valid private guard emits no rejection');

[$controller, $input, $session, $output, $model, $reflection] = pos_transaction_csrf_controller('GET');
$tokenHelper = $reflection->getMethod('pos_transaction_csrf');
$tokenHelper->setAccessible(true);
$generatedToken = $tokenHelper->invoke($controller);
$reusedToken = $tokenHelper->invoke($controller);
pos_transaction_csrf_check(pos_transaction_csrf_hex64($generatedToken), 'missing session token generates an invariant 64-hex token');
pos_transaction_csrf_check($generatedToken === $reusedToken, 'valid generated token is reused within the session');
pos_transaction_csrf_check($session->writes === [$sessionKey], 'token generation writes the scoped session key once');

[$controller, $input, $session, $output, $model, $reflection] = pos_transaction_csrf_controller(
    'GET',
    [],
    [$sessionKey => $validToken]
);
$tokenHelper = $reflection->getMethod('pos_transaction_csrf');
$tokenHelper->setAccessible(true);
$existingToken = $tokenHelper->invoke($controller);
$existingTokenAgain = $tokenHelper->invoke($controller);
pos_transaction_csrf_check(
    $existingToken === $validToken && $existingTokenAgain === $validToken && $existingToken === $existingTokenAgain,
    'valid 64-hex session token is returned identically by the helper'
);
pos_transaction_csrf_check($session->writes === [], 'valid session token does not call set_userdata');

[$controller, $input, $session, $output, $model, $reflection] = pos_transaction_csrf_controller(
    'GET',
    [],
    [$sessionKey => 'invalid-token']
);
$tokenHelper = $reflection->getMethod('pos_transaction_csrf');
$tokenHelper->setAccessible(true);
$replacedToken = $tokenHelper->invoke($controller);
pos_transaction_csrf_check(pos_transaction_csrf_hex64($replacedToken), 'malformed session token is replaced with an invariant 64-hex token');
pos_transaction_csrf_check($session->writes === [$sessionKey], 'malformed session token is not reused');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . ' POS transaction CSRF behavioral/source checks' . PHP_EOL;
