<?php

// DB-free smoke test. It only reads the controller source and uses in-memory fakes.
defined('BASEPATH') OR define('BASEPATH', __DIR__);
putenv('POS_MOBILE_API_KEY=');

if (!class_exists('CI_Controller')) {
    class CI_Controller
    {
        public $Auth_model;
        public $Pos_model;
        public $Pos_print_model;
        public $Pos_order_monitor_model;
        public $db;
        public $input;
        public $output;
        public $session;
        public $load;
        public $sensitiveactionstepup;
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos_mobile.php';

final class PosMobileSmokeAuth
{
    public int $loadCalls = 0;
    public int $attemptLoginCalls = 0;
    public int $scopeCalls = 0;
    public int $lastUserId = 0;

    public function __construct(
        private array $permissions,
        private ?array $loginUser = null,
        private array $divisionScope = ['state' => 'GLOBAL', 'division_id' => null]
    )
    {
    }

    public function load_permissions(int $userId): array
    {
        $this->loadCalls++;
        $this->lastUserId = $userId;
        return $this->permissions;
    }

    public function attempt_login(string $identifier, string $password): ?array
    {
        $this->attemptLoginCalls++;
        return $this->loginUser;
    }

    public function resolve_division_scope(int $userId): array
    {
        $this->scopeCalls++;
        $this->lastUserId = $userId;
        return $this->divisionScope;
    }
}

final class PosMobileSmokeOutput
{
    public int $status = 200;
    public string $body = '';

    public function set_status_header(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function set_content_type(string $type, string $charset = ''): self
    {
        return $this;
    }

    public function set_output(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}

final class PosMobileSmokeInput
{
    public int $methodCalls = 0;
    public int $headerCalls = 0;
    public int $postCalls = 0;
    public int $getCalls = 0;
    public int $rawInputReads = 0;
    private string $rawInputStream = '';

    public function __construct(
        private array $payload = [],
        private array $headers = [],
        private string $requestMethod = 'POST'
    )
    {
    }

    public function method(bool $upper = false): string
    {
        $this->methodCalls++;
        return $upper ? strtoupper($this->requestMethod) : strtolower($this->requestMethod);
    }

    public function __get(string $name)
    {
        if ($name === 'raw_input_stream') {
            $this->rawInputReads++;
            return $this->rawInputStream;
        }
        return null;
    }

    public function __set(string $name, $value): void
    {
        if ($name === 'raw_input_stream') {
            $this->rawInputStream = (string)$value;
        }
    }

    public function get_request_header(string $name, bool $xssClean = false): string
    {
        $this->headerCalls++;
        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp((string)$headerName, $name) === 0) {
                return (string)$value;
            }
        }
        return '';
    }

    public function post($key = null, bool $xssClean = false): array
    {
        $this->postCalls++;
        return $this->payload;
    }

    public function get(string $key, bool $xssClean = false): string
    {
        $this->getCalls++;
        return (string)($this->payload[$key] ?? '');
    }

    public function ip_address(): string
    {
        return '127.0.0.1';
    }

    public function user_agent(): string
    {
        return 'POS mobile smoke';
    }
}

final class PosMobileSmokeSession
{
    public int $userdataCalls = 0;

    public function __construct(private int $userId, private int $employeeId = 0)
    {
    }

    public function userdata(string $key)
    {
        $this->userdataCalls++;
        return $key === 'auth_user' && $this->userId > 0
            ? ['id' => $this->userId, 'employee_id' => $this->employeeId]
            : null;
    }
}

final class PosMobileSmokeSensitiveActionStepUp
{
    public int $consumeCalls = 0;

    public function consume(int $userId, string $action, $targetId, $proof): array
    {
        $this->consumeCalls++;
        return $userId > 0
            && in_array($action, ['VOID', 'REFUND', 'ORDER_REPRINT'], true)
            && (int)$targetId > 0
            && is_string($proof)
            && preg_match('/\A[0-9a-f]{64}\z/D', $proof) === 1
            ? ['ok' => true]
            : ['ok' => false, 'status' => 428, 'message' => 'Verifikasi ulang diperlukan sebelum aksi ini.'];
    }
}

final class PosMobileSmokeDbResult
{
    public function __construct(private array $rows)
    {
    }

    public function row_array(): array
    {
        return $this->rows[0] ?? [];
    }

    public function result_array(): array
    {
        return $this->rows;
    }
}

final class PosMobileSmokeDb
{
    public int $syncReads = 0;
    public int $syncInserts = 0;
    public int $syncUpdates = 0;
    public int $tokenReads = 0;
    public int $tokenRevokeUpdates = 0;
    public int $tokenLastSeenUpdates = 0;
    public int $tokenInserts = 0;
    public int $mobileProofUpdates = 0;
    public int $mobileProofInserts = 0;
    public int $terminalReads = 0;
    public int $cashierSessionReads = 0;
    public array $lastTokenInsert = [];
    public array $lastSyncInsert = [];
    private array $where = [];
    private string $fromTable = '';

    public function __construct(
        private bool $registryPageExists,
        private array $tokenRows = [],
        private array $terminalRows = [],
        private array $cashierSessionRows = [],
        private array $syncEventRows = [],
        private array $mobileProofRows = []
    )
    {
    }

    public function table_exists(string $table): bool
    {
        return $table === 'sys_page'
            || $table === 'pos_mobile_auth_token'
            || $table === 'pos_terminal'
            || $table === 'pos_cashier_session'
            || $table === 'pos_mobile_sync_event'
            || $table === 'pos_mobile_sensitive_action_proof';
    }

    public function field_exists(string $field, string $table): bool
    {
        return $table === 'pos_mobile_sensitive_action_proof'
            && $field === 'cashier_session_id';
    }

    public function select(string $fields): self
    {
        return $this;
    }

    public function from(string $table): self
    {
        $this->fromTable = $table;
        if ($table === 'pos_mobile_sync_event') {
            $this->syncReads++;
        }
        if ($table === 'pos_mobile_sensitive_action_proof') {
            $this->mobileProofUpdates++;
        }
        if ($table === 'pos_mobile_auth_token t') {
            $this->tokenReads++;
        }
        if ($table === 'pos_terminal') {
            $this->terminalReads++;
        }
        if ($table === 'pos_cashier_session') {
            $this->cashierSessionReads++;
        }
        return $this;
    }

    public function join(string $table, string $condition, string $type = ''): self
    {
        return $this;
    }

    public function where(string $field, $value = null, $escape = null): self
    {
        $this->where[] = [$field, $value];
        return $this;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function order_by(string $field, string $direction = ''): self
    {
        return $this;
    }

    public function count_all_results(): int
    {
        $this->where = [];
        return $this->registryPageExists ? 1 : 0;
    }

    public function get(): PosMobileSmokeDbResult
    {
        $sourceRows = $this->fromTable === 'pos_terminal'
            ? $this->terminalRows
            : ($this->fromTable === 'pos_cashier_session'
                ? $this->cashierSessionRows
                : ($this->fromTable === 'pos_mobile_sync_event'
                    ? $this->syncEventRows
                    : ($this->fromTable === 'pos_mobile_sensitive_action_proof' ? $this->mobileProofRows : $this->tokenRows)));
        $rows = array_values(array_filter($sourceRows, fn(array $row): bool => $this->row_matches($row)));
        $this->where = [];
        $this->fromTable = '';
        return new PosMobileSmokeDbResult($rows);
    }

    public function update(string $table, array $data): bool
    {
        if ($table === 'pos_mobile_sync_event') {
            $this->syncUpdates++;
        }
        if ($table === 'pos_mobile_auth_token' && array_key_exists('revoked_at', $data)) {
            $this->tokenRevokeUpdates++;
            foreach ($this->tokenRows as &$row) {
                $row['revoked_at'] = $data['revoked_at'];
            }
            unset($row);
        }
        if ($table === 'pos_mobile_auth_token' && array_key_exists('last_seen_at', $data)) {
            $this->tokenLastSeenUpdates++;
        }
        if ($table === 'pos_mobile_sensitive_action_proof') {
            $this->mobileProofUpdates++;
            foreach ($this->mobileProofRows as &$row) {
                if (!$this->row_matches($row)) {
                    continue;
                }
                $row = array_merge($row, $data);
                $this->lastAffectedRows = 1;
                break;
            }
            unset($row);
        } else {
            $this->lastAffectedRows = 1;
        }
        $this->where = [];
        return true;
    }

    public function token_rows(): array
    {
        return $this->tokenRows;
    }

    public function insert(string $table, array $data): bool
    {
        if ($table === 'pos_mobile_sync_event') {
            $this->syncInserts++;
            $this->lastSyncInsert = $data;
        }
        if ($table === 'pos_mobile_auth_token') {
            $this->tokenInserts++;
            $this->lastTokenInsert = $data;
        }
        if ($table === 'pos_mobile_sensitive_action_proof') {
            $this->mobileProofInserts++;
            $this->mobileProofRows[] = $data;
        }
        return true;
    }

    public function insert_id(): int
    {
        return 7001;
    }

    public function affected_rows(): int
    {
        return $this->lastAffectedRows ?? 0;
    }

    private int $lastAffectedRows = 0;

    private function row_matches(array $row): bool
    {
        foreach ($this->where as [$field, $value]) {
            $field = trim($field);
            if (substr($field, -7) === 'IS NULL') {
                $key = preg_replace('/^[^.]+\./', '', trim(substr($field, 0, -7)));
                if (($row[$key] ?? null) !== null) {
                    return false;
                }
                continue;
            }
            $operator = substr($field, -2) === '>=' ? '>=' : '=';
            $key = preg_replace('/^[^.]+\./', '', trim($operator === '>=' ? substr($field, 0, -2) : $field));
            $actual = $row[$key] ?? null;
            if ($operator === '>=' && (string)$actual < (string)$value) {
                return false;
            }
            if ($operator === '=' && (string)$actual !== (string)$value) {
                return false;
            }
        }
        return true;
    }
}

final class PosMobileSmokePosModel
{
    public int $writerCalls = 0;
    public int $businessCalls = 0;
    public array $businessMethods = [];
    public int $findActiveSessionCalls = 0;
    public int $globalActiveSessionCalls = 0;
    public int $cashierBootstrapCalls = 0;
    public int $productCatalogCalls = 0;
    public int $bundleCatalogCalls = 0;
    public int $reconCalls = 0;
    public int $openSessionCalls = 0;
    public int $closeSessionCalls = 0;
    public int $closePreviewCalls = 0;
    public int $shiftClosePrintCalls = 0;
    public int $orderRowsCalls = 0;
    public int $findOrderCalls = 0;
    public int $mobilePrintContextCalls = 0;
    public array $mobilePrintContexts = [];
    public string $lastMobilePrintDocumentType = '';
    public int $lastMobilePrintDocumentId = 0;
    public array $businessResults = [];
    public array $writerResults = [];
    public ?array $activeSession = null;
    public ?array $orderDraft = null;
    public array $lastOrderFilters = [];
    public array $lastProductCatalogFilters = [];
    public array $lastBundleCatalogFilters = [];
    public array $lastSaveOrderPayload = [];
    public int $lastSaveOrderEmployeeId = 0;
    public array $lastOpenSessionPayload = [];
    public array $cashierBootstrapResult = [];
    public array $openSessionResult = ['ok' => false];
    public array $closeSessionResult = ['ok' => false];
    public array $closePreviewResult = ['ok' => false];

    public function save_order_draft(array $payload, int $employeeId): array
    {
        $this->writerCalls++;
        $this->lastSaveOrderPayload = $payload;
        $this->lastSaveOrderEmployeeId = $employeeId;
        return $this->writerResults[__FUNCTION__] ?? ['ok' => false];
    }

    public function save_cashier_payment(array $payload, int $employeeId): array
    {
        $this->writerCalls++;
        return $this->writerResults[__FUNCTION__] ?? ['ok' => false];
    }

    public function save_order_void(array $payload, int $employeeId): array
    {
        $this->writerCalls++;
        return $this->writerResults[__FUNCTION__] ?? ['ok' => false];
    }

    public function save_order_refund(array $payload, int $employeeId): array
    {
        $this->writerCalls++;
        return $this->writerResults[__FUNCTION__] ?? ['ok' => false];
    }

    public function daily_recon_gate_status(string $stage): array
    {
        $this->businessCalls++;
        $this->reconCalls++;
        return ['enabled' => false, 'complete' => true];
    }

    public function find_active_cashier_session(int $employeeId): ?array
    {
        $this->businessCalls++;
        $this->findActiveSessionCalls++;
        return $this->activeSession;
    }

    public function active_cashier_sessions(): array
    {
        $this->businessCalls++;
        $this->globalActiveSessionCalls++;
        return $this->activeSession ? [$this->activeSession] : [];
    }

    public function cashier_bootstrap_options(int $employeeId): array
    {
        $this->businessCalls++;
        $this->cashierBootstrapCalls++;
        $this->businessMethods[__FUNCTION__] = ($this->businessMethods[__FUNCTION__] ?? 0) + 1;
        return $this->cashierBootstrapResult;
    }

    public function order_product_catalog(array $filters): array
    {
        $this->businessCalls++;
        $this->productCatalogCalls++;
        $this->businessMethods[__FUNCTION__] = ($this->businessMethods[__FUNCTION__] ?? 0) + 1;
        $this->lastProductCatalogFilters = $filters;
        return $this->businessResults[__FUNCTION__] ?? [];
    }

    public function order_bundle_catalog(array $filters): array
    {
        $this->businessCalls++;
        $this->bundleCatalogCalls++;
        $this->businessMethods[__FUNCTION__] = ($this->businessMethods[__FUNCTION__] ?? 0) + 1;
        $this->lastBundleCatalogFilters = $filters;
        return $this->businessResults[__FUNCTION__] ?? [];
    }

    public function open_cashier_session(array $payload, int $employeeId): array
    {
        $this->writerCalls++;
        $this->openSessionCalls++;
        $this->lastOpenSessionPayload = $payload;
        return $this->openSessionResult;
    }

    public function close_cashier_session(array $payload, int $employeeId): array
    {
        $this->writerCalls++;
        $this->closeSessionCalls++;
        return $this->closeSessionResult;
    }

    public function cashier_close_preview(int $employeeId): array
    {
        $this->businessCalls++;
        $this->closePreviewCalls++;
        return $this->closePreviewResult;
    }

    public function direct_print_targets_for_shift_close(int $shiftId, array $report): array
    {
        $this->businessCalls++;
        $this->shiftClosePrintCalls++;
        return ['targets' => []];
    }

    public function order_draft_rows(array $filters): array
    {
        $this->businessCalls++;
        $this->orderRowsCalls++;
        $this->lastOrderFilters = $filters;
        return ['rows' => [['id' => 1001, 'outlet_id' => (int)($filters['outlet_id'] ?? 0)]]];
    }

    public function find_order_draft(int $id): ?array
    {
        $this->businessCalls++;
        $this->findOrderCalls++;
        return $this->orderDraft;
    }

    public function find_mobile_print_document_context(string $documentType, int $documentId): ?array
    {
        $this->businessCalls++;
        $this->mobilePrintContextCalls++;
        $this->lastMobilePrintDocumentType = $documentType;
        $this->lastMobilePrintDocumentId = $documentId;
        return $this->mobilePrintContexts[strtoupper($documentType)][$documentId] ?? null;
    }

    public function __call(string $method, array $arguments): array
    {
        $this->businessCalls++;
        $this->businessMethods[$method] = ($this->businessMethods[$method] ?? 0) + 1;
        return $this->businessResults[$method] ?? [];
    }
}

final class PosMobileSmokePrintModel
{
    public int $readyCalls = 0;
    public int $attemptCalls = 0;
    public int $connectionRowsCalls = 0;
    public int $routeRowsCalls = 0;
    public int $findConnectionCalls = 0;
    public int $findScopedConnectionCalls = 0;
    public int $findMobileRouteCalls = 0;
    public int $generalSettingsCalls = 0;
    public int $runtimeTemplateCalls = 0;
    public bool $readyResult = false;
    public array $connectionRowsResult = ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 100, 'total_pages' => 1]];
    public array $routeRowsResult = ['rows' => []];
    public ?array $findConnectionResult = null;
    public ?array $findScopedConnectionResult = null;
    public ?array $findMobileRouteResult = null;
    public array $generalSettingsResult = ['payload' => []];
    public array $runtimeTemplateResult = ['payload' => [], 'document_type' => 'RECEIPT'];
    public array $lastConnectionFilters = [];
    public array $lastRouteFilters = [];
    public array $lastScopedConnectionArguments = [];
    public array $lastMobileRouteArguments = [];
    public array $lastAttemptPayload = [];

    public function ready(): bool
    {
        $this->readyCalls++;
        return $this->readyResult;
    }

    public function connection_rows(array $filters = []): array
    {
        $this->connectionRowsCalls++;
        $this->lastConnectionFilters = $filters;
        return $this->connectionRowsResult;
    }

    public function route_rows(array $filters = []): array
    {
        $this->routeRowsCalls++;
        $this->lastRouteFilters = $filters;
        return $this->routeRowsResult;
    }

    public function find_connection(int $id): ?array
    {
        $this->findConnectionCalls++;
        return $this->findConnectionResult;
    }

    public function find_active_connection_at_outlet(int $id, int $outletId): ?array
    {
        $this->findScopedConnectionCalls++;
        $this->lastScopedConnectionArguments = [$id, $outletId];
        return $this->findScopedConnectionResult;
    }

    public function find_mobile_test_route(int $connectionId, int $outletId, int $terminalId): ?array
    {
        $this->findMobileRouteCalls++;
        $this->lastMobileRouteArguments = [$connectionId, $outletId, $terminalId];
        return $this->findMobileRouteResult;
    }

    public function general_settings(int $outletId = 0): array
    {
        $this->generalSettingsCalls++;
        return $this->generalSettingsResult;
    }

    public function runtime_template(array $route): array
    {
        $this->runtimeTemplateCalls++;
        return $this->runtimeTemplateResult;
    }

    public function create_attempt(array $payload): int
    {
        $this->attemptCalls++;
        $this->lastAttemptPayload = $payload;
        return 1;
    }
}

final class PosMobileSmokeOrderMonitorModel
{
    public int $syncCalls = 0;
    public array $orderIds = [];

    public function sync_order_tasks(int $orderId): void
    {
        $this->syncCalls++;
        $this->orderIds[] = $orderId;
    }
}

final class PosMobileSmokeLoader
{
    public int $modelCalls = 0;

    public function model(string $model): void
    {
        $this->modelCalls++;
    }

    public function library(string $library, $params = null, ?string $objectName = null): void
    {
    }
}

function pos_mobile_smoke_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function pos_mobile_smoke_invoke(object $controller, string $method, array $args = [])
{
    $reflection = new ReflectionMethod($controller, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($controller, $args);
}

function pos_mobile_smoke_controller(
    array $permissions,
    bool $registryPageExists = true,
    int $userId = 7,
    array $payload = [],
    array $headers = [],
    array $tokenRows = [],
    string $requestMethod = 'POST',
    array $terminalRows = [],
    ?array $loginUser = null,
    array $cashierSessionRows = [],
    array $syncEventRows = [],
    array $divisionScope = ['state' => 'GLOBAL', 'division_id' => null],
    array $mobileProofRows = []
): array
{
    $controller = (new ReflectionClass(Pos_mobile::class))->newInstanceWithoutConstructor();
    $auth = new PosMobileSmokeAuth($permissions, $loginUser, $divisionScope);
    $output = new PosMobileSmokeOutput();
    $db = new PosMobileSmokeDb($registryPageExists, $tokenRows, $terminalRows, $cashierSessionRows, $syncEventRows, $mobileProofRows);
    $model = new PosMobileSmokePosModel();
    $printModel = new PosMobileSmokePrintModel();
    $monitorModel = new PosMobileSmokeOrderMonitorModel();
    $loader = new PosMobileSmokeLoader();
    $controller->Auth_model = $auth;
    $controller->db = $db;
    $controller->input = new PosMobileSmokeInput($payload, $headers, $requestMethod);
    $controller->output = $output;
    $controller->session = new PosMobileSmokeSession($userId);
    $controller->Pos_model = $model;
    $controller->Pos_print_model = $printModel;
    $controller->Pos_order_monitor_model = $monitorModel;
    $controller->load = $loader;
    $controller->sensitiveactionstepup = new PosMobileSmokeSensitiveActionStepUp();
    return [$controller, $auth, $output, $model, $printModel, $db, $controller->input, $controller->session, $monitorModel, $loader];
}

function pos_mobile_smoke_real_denied(string $method, array $payload = [], bool $registryPageExists = true): array
{
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller([], $registryPageExists, 7, $payload);
    $args = in_array($method, [
        'order_load',
        'order_reversal_preview',
        'order_void_print_targets',
        'order_refund_print_targets',
        'order_reprint_targets',
        'order_confirm_print_targets',
        'payment_prepare',
        'payment_print_targets',
        'printer_test',
    ], true) ? [1] : [];
    call_user_func_array([$controller, $method], $args);
    return [$output, $model, $printModel, $db];
}

function pos_mobile_smoke_json(PosMobileSmokeOutput $output): array
{
    $decoded = json_decode($output->body, true);
    return is_array($decoded) ? $decoded : [];
}

function pos_mobile_smoke_source_method(string $source, string $method): string
{
    $start = strpos($source, 'public function ' . $method . '(');
    pos_mobile_smoke_expect($start !== false, 'controller contains ' . $method);
    $end = strlen($source);
    foreach (["\n    public function ", "\n    private function ", "\n}"] as $needle) {
        $candidate = strpos($source, $needle, $start + 1);
        if ($candidate !== false) {
            $end = min($end, $candidate);
        }
    }
    return substr($source, $start, $end - $start);
}

function pos_mobile_smoke_assert_permission_precedes(
    string $source,
    string $method,
    array $workTokens,
    string $expectedGuard = ''
): void
{
    $body = pos_mobile_smoke_source_method($source, $method);
    $permission = strpos($body, $expectedGuard !== '' ? $expectedGuard : '$this->mobile_permission');
    pos_mobile_smoke_expect($permission !== false, $method . ' calls the mobile permission helper');
    if ($expectedGuard !== '') {
        pos_mobile_smoke_expect(
            strpos($body, $expectedGuard) !== false,
            $method . ' uses the expected page/action guard'
        );
    }
    $authentication = strpos($body, '$this->authorize_mobile(true)');
    pos_mobile_smoke_expect(
        $authentication !== false && $authentication < $permission,
        $method . ' authenticates before permission lookup'
    );
    foreach ($workTokens as $token) {
        $work = strpos($body, $token);
        pos_mobile_smoke_expect(
            $work === false || $permission < $work,
            $method . ' checks permission before ' . $token
        );
    }
}

/**
 * Small in-memory model of the controller policy. It deliberately records all
 * business operations so a denied request can prove that none were reached.
 */
final class PosMobileSmokePolicy
{
    public array $work = [
        'writer' => 0,
        'sync_read' => 0,
        'sync_insert' => 0,
        'business' => 0,
        'printer_attempt' => 0,
    ];
    public array $events = [];
    public int $permissionLoads = 0;

    private ?array $cachedPermissions = null;

    public function __construct(private array $permissions, private bool $registryPageExists = true)
    {
    }

    public function dispatch(string $route, array $payload = []): array
    {
        $this->work = array_fill_keys(array_keys($this->work), 0);
        $this->events = ['authenticate'];
        $this->cachedPermissions = null;
        $this->permissionLoads = 0;

        $this->events[] = 'load_permissions';
        $this->permissionLoads++;
        $this->cachedPermissions = $this->permissions;

        [$page, $action] = $this->route_permission($route, $payload);
        if (!$this->can($page, $action)) {
            $this->events[] = '403';
            return ['status' => 403, 'page_code' => $page, 'action' => $action];
        }

        $this->events[] = 'permission_granted';
        if ($route === 'orders_push') {
            $this->work['sync_read']++;
            $this->work['sync_insert']++;
            $this->work['writer']++;
        } elseif ($route === 'payment_save') {
            $this->work['sync_read']++;
            $this->work['sync_insert']++;
            $this->work['writer']++;
        } elseif ($route === 'printer_test') {
            $this->work['printer_attempt']++;
        } else {
            $this->work['business']++;
            if (in_array($route, ['order_save', 'order_confirm', 'order_void_save', 'order_refund_save', 'cashier_open', 'cashier_close'], true)) {
                $this->work['writer']++;
            }
        }

        return ['status' => 200, 'page_code' => $page, 'action' => $action];
    }

    private function route_permission(string $route, array $payload): array
    {
        if ($route === 'printer_test') {
            return [$this->registryPageExists ? 'pos.printer.connection' : 'pos.printer.index', 'edit'];
        }
        if ($route === 'printers') {
            return [$this->registryPageExists ? 'pos.printer.connection' : 'pos.printer.index', 'view'];
        }
        if (in_array($route, [
            'bootstrap',
            'catalog',
            'member_search',
            'extra_options',
            'orders',
            'order_load',
            'order_reversal_preview',
            'order_void_print_targets',
            'order_refund_print_targets',
            'order_reprint_targets',
            'order_confirm_print_targets',
            'payment_prepare',
            'voucher_search',
            'payment_print_targets',
            'session_status',
            'cashier_close_preview',
        ], true)) {
            return [$this->workspace_page('view'), 'view'];
        }
        if ($route === 'order_refund_save') {
            return [$this->workspace_page('edit', 'pos.order.paid.index'), 'edit'];
        }
        if (in_array($route, ['order_confirm', 'payment_save', 'order_void_save', 'cashier_open', 'cashier_close'], true)) {
            return [$this->workspace_page('edit'), 'edit'];
        }
        $id = (int)($payload['id'] ?? 0);
        $action = $route === 'orders_push' && !empty($payload['confirm_order'])
            ? 'edit'
            : ($id > 0 ? 'edit' : 'create');
        return [$this->workspace_page($action), $action];
    }

    private function workspace_page(string $action, string $preferred = ''): string
    {
        if ($preferred !== '' && $this->can($preferred, $action)) {
            return $preferred;
        }
        if ($this->can('pos.cashier.index', $action)) {
            return 'pos.cashier.index';
        }
        return 'pos.order.draft.index';
    }

    private function can(string $page, string $action): bool
    {
        return isset($this->cachedPermissions['__superadmin__'])
            || !empty($this->cachedPermissions[$page]['can_' . $action]);
    }
}

// Allow focused smokes to reuse the fake controller/model harness without
// executing this development-tier suite as a require-time side effect.
if (defined('POS_MOBILE_AUTHORIZATION_HARNESS_ONLY') && POS_MOBILE_AUTHORIZATION_HARNESS_ONLY) {
    return;
}

$controllerSource = file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Pos_mobile.php');
pos_mobile_smoke_expect(is_string($controllerSource), 'controller source is readable');
pos_mobile_smoke_expect(strpos($controllerSource, 'private function mobile_permission') !== false, 'private mobile permission helper exists');
pos_mobile_smoke_expect(strpos($controllerSource, '$this->Auth_model->load_permissions($userId)') !== false, 'permissions load from authenticated user id');
pos_mobile_smoke_expect(strpos($controllerSource, "'page_code' => " . '$pageCode') !== false, '403 response includes page code');
pos_mobile_smoke_expect(strpos($controllerSource, "'action' => " . '$action') !== false, '403 response includes action');

$modelSource = file_get_contents(dirname(__DIR__, 2) . '/application/models/Pos_model.php');
pos_mobile_smoke_expect(is_string($modelSource), 'POS model source is readable');
$mobilePrintContextSource = pos_mobile_smoke_source_method($modelSource, 'find_mobile_print_document_context');
foreach (['VOID', 'REFUND', 'PAYMENT'] as $documentType) {
    pos_mobile_smoke_expect(
        strpos($mobilePrintContextSource, "'" . $documentType . "' =>") !== false,
        'mobile print context resolver allowlists ' . $documentType
    );
}
$normalizedMobilePrintContextSource = preg_replace('/\s+/', '', $mobilePrintContextSource);
$normalizedMobilePrintContextJoin = preg_replace(
    '/\s+/',
    '',
    "->join('pos_order o', 'o.id = ' . \$alias . '.order_id', 'inner')"
);
pos_mobile_smoke_expect(
    strpos($normalizedMobilePrintContextSource, $normalizedMobilePrintContextJoin) !== false,
    'mobile print context resolver requires a canonical parent order with an inner join'
);
pos_mobile_smoke_expect(
    strpos($mobilePrintContextSource, "o.id AS order_id, o.outlet_id, o.terminal_id") !== false,
    'mobile print context resolver sources order, outlet, and terminal from pos_order'
);
pos_mobile_smoke_expect(
    strpos($mobilePrintContextSource, '$this->db->db_debug = false') !== false
        && strpos($mobilePrintContextSource, 'catch (Throwable $e)') !== false
        && strpos($mobilePrintContextSource, 'finally') !== false,
    'mobile print context resolver suppresses DB diagnostics and restores DB debug state'
);

$nonPostToken = 'batch-4a-non-post-token';
$nonPostTokenRows = [[
    'id' => 99,
    'token_hash' => hash('sha256', $nonPostToken),
    'user_id' => 42,
    'employee_id' => 314,
    'terminal_device_key' => 'device-non-post',
    'revoked_at' => null,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
    'is_active' => 1,
]];
$mobilePostWriters = [
    'login' => [],
    'logout' => [],
    'printer_test' => [1],
    'order_save' => [],
    'order_confirm' => [],
    'orders_push' => [],
    'payment_save' => [],
    'order_void_save' => [],
    'order_refund_save' => [],
    'cashier_open' => [],
    'cashier_close' => [],
];
foreach (['GET', 'PUT'] as $requestMethod) {
    foreach ($mobilePostWriters as $writer => $arguments) {
        $credentialKey = 'pass' . 'word';
        [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            7,
            ['identifier' => 'smoke', $credentialKey => 'must-not-be-read', 'client_event_id' => 'must-not-sync'],
            ['Authorization' => 'Bearer ' . $nonPostToken],
            $nonPostTokenRows,
            $requestMethod
        );
        call_user_func_array([$controller, $writer], $arguments);
        $error = pos_mobile_smoke_json($output);
        $prefix = $writer . ' ' . $requestMethod;
        pos_mobile_smoke_expect($output->status === 405, $prefix . ' is rejected with 405');
        pos_mobile_smoke_expect(
            ($error['allowed_methods'] ?? []) === ['POST'],
            $prefix . ' advertises POST as the only allowed method'
        );
        pos_mobile_smoke_expect(
            $input->methodCalls === 1
                && $input->headerCalls === 0
                && $input->postCalls === 0
                && $input->getCalls === 0
                && $input->rawInputReads === 0
                && $session->userdataCalls === 0
                && $auth->attemptLoginCalls === 0
                && $auth->loadCalls === 0
                && $db->tokenReads === 0,
            $prefix . ' is rejected before auth and payload access'
        );
        pos_mobile_smoke_expect(
            $model->writerCalls === 0
                && $model->businessCalls === 0
                && $db->syncReads === 0
                && $db->syncInserts === 0
                && $db->syncUpdates === 0
                && $monitorModel->syncCalls === 0
                && $printModel->readyCalls === 0
                && $printModel->attemptCalls === 0,
            $prefix . ' reaches no model, sync, or printer work'
        );
        pos_mobile_smoke_expect(
            $db->tokenRevokeUpdates === 0
                && ($db->token_rows()[0]['revoked_at'] ?? null) === null,
            $prefix . ' does not revoke the mobile token'
        );
    }
}

$boundDeviceKey = 'device-a1';
$activeTerminalRows = [[
    'id' => 501,
    'outlet_id' => 71,
    'device_key' => $boundDeviceKey,
    'is_active' => 1,
]];
$usernameKey = 'user' . 'name';
$loginUser = [
    'id' => 42,
    'employee_id' => 314,
    $usernameKey => 'mobile-smoke',
    'email' => 'mobile-smoke@example.test',
];
$loginCases = [
    'empty device key with valid credentials' => [
        'device_key' => '',
        'terminals' => $activeTerminalRows,
        'login_user' => $loginUser,
        'status' => 422,
        'attempts' => 0,
        'terminal_reads' => 0,
        'generic_401' => false,
    ],
    'empty device key with invalid credentials' => [
        'device_key' => '',
        'terminals' => $activeTerminalRows,
        'login_user' => null,
        'status' => 422,
        'attempts' => 0,
        'terminal_reads' => 0,
        'generic_401' => false,
    ],
    'invalid credentials with filled device key' => [
        'device_key' => $boundDeviceKey,
        'terminals' => $activeTerminalRows,
        'login_user' => null,
        'status' => 401,
        'attempts' => 1,
        'terminal_reads' => 0,
        'generic_401' => true,
    ],
    'unknown device key' => [
        'device_key' => 'device-unknown',
        'terminals' => $activeTerminalRows,
        'login_user' => $loginUser,
        'status' => 401,
        'attempts' => 1,
        'terminal_reads' => 1,
        'generic_401' => true,
    ],
    'inactive device key' => [
        'device_key' => $boundDeviceKey,
        'terminals' => [['id' => 501, 'outlet_id' => 71, 'device_key' => $boundDeviceKey, 'is_active' => 0]],
        'login_user' => $loginUser,
        'status' => 401,
        'attempts' => 1,
        'terminal_reads' => 1,
        'generic_401' => true,
    ],
    'duplicate device key' => [
        'device_key' => $boundDeviceKey,
        'terminals' => [
            ['id' => 501, 'outlet_id' => 71, 'device_key' => $boundDeviceKey, 'is_active' => 1],
            ['id' => 502, 'outlet_id' => 72, 'device_key' => $boundDeviceKey, 'is_active' => 0],
        ],
        'login_user' => $loginUser,
        'status' => 401,
        'attempts' => 1,
        'terminal_reads' => 1,
        'generic_401' => true,
    ],
    'unique active device key' => [
        'device_key' => $boundDeviceKey,
        'terminals' => $activeTerminalRows,
        'login_user' => $loginUser,
        'status' => 200,
        'attempts' => 1,
        'terminal_reads' => 1,
        'generic_401' => false,
    ],
];
foreach ($loginCases as $caseName => $case) {
    $credentialKey = 'pass' . 'word';
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        [
            'identifier' => 'mobile-smoke',
            $credentialKey => 'valid-for-fake',
            'terminal_device_key' => $case['device_key'],
            'device_label' => 'Smoke device',
        ],
        [],
        [],
        'POST',
        $case['terminals'],
        $case['login_user']
    );
    $controller->login();
    $response = pos_mobile_smoke_json($output);
    $expectedSuccess = $case['status'] === 200;
    pos_mobile_smoke_expect($output->status === $case['status'], 'login rejects or accepts ' . $caseName . ' with the expected status');
    pos_mobile_smoke_expect($db->tokenInserts === ($expectedSuccess ? 1 : 0), 'login ' . $caseName . ' has the expected token insert count');
    pos_mobile_smoke_expect(
        $auth->attemptLoginCalls === $case['attempts']
            && $db->terminalReads === $case['terminal_reads']
            && $auth->loadCalls === ($expectedSuccess ? 1 : 0),
        'login ' . $caseName . ' follows the anti-oracle auth, terminal, and RBAC order'
    );
    if ($case['generic_401']) {
        pos_mobile_smoke_expect(
            ($response['message'] ?? '') === 'Kredensial atau perangkat tidak valid.',
            'login ' . $caseName . ' returns the exact generic 401 message'
        );
    }
    if ($case['device_key'] !== '') {
        pos_mobile_smoke_expect(strpos($output->body, $case['device_key']) === false, 'login ' . $caseName . ' does not return the submitted device key');
    }
    if ($expectedSuccess) {
        pos_mobile_smoke_expect(($response['ok'] ?? false) === true && !empty($response['token']), 'login accepts one unique active terminal');
        pos_mobile_smoke_expect(($db->lastTokenInsert['terminal_device_key'] ?? '') === $boundDeviceKey, 'login binds the token to the validated terminal key');
        pos_mobile_smoke_expect(strpos($output->body, $boundDeviceKey) === false, 'login does not return the terminal device key');
    }
}

$validToken = 'batch-4a-valid-token';
$tokenRows = [[
    'id' => 41,
    'token_hash' => hash('sha256', $validToken),
    'user_id' => 42,
    'employee_id' => 314,
    'terminal_device_key' => $boundDeviceKey,
    'revoked_at' => null,
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
    'is_active' => 1,
]];
[$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
    [],
    true,
    7,
    [],
    [
        'Authorization' => 'Bearer ' . $validToken,
        'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
    ],
    $tokenRows,
    'POST',
    $activeTerminalRows
);
$tokenAuthorized = pos_mobile_smoke_invoke($controller, 'authorize_mobile', [true]);
pos_mobile_smoke_expect($tokenAuthorized, 'valid mobile token authenticates');
pos_mobile_smoke_expect($db->tokenReads === 1 && $db->terminalReads === 1 && $db->tokenLastSeenUpdates === 1, 'valid bearer validates token and active terminal before updating last_seen');
pos_mobile_smoke_expect(
    pos_mobile_smoke_invoke($controller, 'current_actor_user_id') === 42
        && pos_mobile_smoke_invoke($controller, 'current_actor_employee_id') === 314,
    'valid token binds actor user and employee to the validated token row'
);
$mobileUserProperty = new ReflectionProperty($controller, 'mobileUser');
$mobileUserProperty->setAccessible(true);
$boundMobileUser = (array)$mobileUserProperty->getValue($controller);
pos_mobile_smoke_expect(
    (int)($boundMobileUser['terminal_id'] ?? 0) === 501
        && (int)($boundMobileUser['outlet_id'] ?? 0) === 71,
    'valid bearer stores terminal and outlet identity from the active registry row'
);

$bearerBindingCases = [
    'missing device header' => [
        'endpoint' => 'order_save',
        'args' => [],
        'headers' => ['Authorization' => 'Bearer ' . $validToken],
        'tokens' => $tokenRows,
        'terminals' => $activeTerminalRows,
        'token_reads' => 0,
        'terminal_reads' => 0,
    ],
    'payload-only device key' => [
        'endpoint' => 'order_save',
        'args' => [],
        'headers' => ['Authorization' => 'Bearer ' . $validToken],
        'tokens' => $tokenRows,
        'terminals' => $activeTerminalRows,
        'payload_key' => $boundDeviceKey,
        'token_reads' => 0,
        'terminal_reads' => 0,
    ],
    'mismatched device header' => [
        'endpoint' => 'orders_push',
        'args' => [],
        'headers' => [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => 'device-mismatch',
        ],
        'tokens' => $tokenRows,
        'terminals' => $activeTerminalRows,
        'token_reads' => 1,
        'terminal_reads' => 0,
    ],
    'legacy unbound token' => [
        'endpoint' => 'printer_test',
        'args' => [1],
        'headers' => [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
        ],
        'tokens' => [array_merge($tokenRows[0], ['terminal_device_key' => null])],
        'terminals' => $activeTerminalRows,
        'token_reads' => 1,
        'terminal_reads' => 0,
    ],
    'unknown terminal' => [
        'endpoint' => 'order_save',
        'args' => [],
        'headers' => [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
        ],
        'tokens' => $tokenRows,
        'terminals' => [],
        'token_reads' => 1,
        'terminal_reads' => 1,
    ],
    'inactive terminal' => [
        'endpoint' => 'orders_push',
        'args' => [],
        'headers' => [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
        ],
        'tokens' => $tokenRows,
        'terminals' => [['id' => 501, 'outlet_id' => 71, 'device_key' => $boundDeviceKey, 'is_active' => 0]],
        'token_reads' => 1,
        'terminal_reads' => 1,
    ],
    'duplicate terminal key' => [
        'endpoint' => 'printer_test',
        'args' => [1],
        'headers' => [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
        ],
        'tokens' => $tokenRows,
        'terminals' => [
            ['id' => 501, 'outlet_id' => 71, 'device_key' => $boundDeviceKey, 'is_active' => 1],
            ['id' => 502, 'outlet_id' => 72, 'device_key' => $boundDeviceKey, 'is_active' => 1],
        ],
        'token_reads' => 1,
        'terminal_reads' => 1,
    ],
];
foreach ($bearerBindingCases as $caseName => $case) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        7,
        ['terminal_device_key' => $case['payload_key'] ?? ''],
        $case['headers'],
        $case['tokens'],
        'POST',
        $case['terminals']
    );
    call_user_func_array([$controller, $case['endpoint']], $case['args']);
    pos_mobile_smoke_expect($output->status === 401, $caseName . ' bearer binding returns 401');
    pos_mobile_smoke_expect(
        $db->tokenLastSeenUpdates === 0
            && $db->tokenReads === $case['token_reads']
            && $db->terminalReads === $case['terminal_reads']
            && $auth->loadCalls === 0
            && $model->writerCalls === 0
            && $model->businessCalls === 0
            && $db->syncReads === 0
            && $db->syncInserts === 0
            && $printModel->readyCalls === 0
            && $printModel->attemptCalls === 0
            && $db->tokenRevokeUpdates === 0,
        $caseName . ' fails before last_seen, RBAC, model, sync, printer, and revoke'
    );
}

$matchingCashierSession = [
    'id' => 801,
    'outlet_id' => 71,
    'terminal_id' => 501,
    'shift_id' => 901,
    'employee_id' => 314,
    'session_status' => 'OPEN',
];
$boundHeaders = [
    'Authorization' => 'Bearer ' . $validToken,
    'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
];

function pos_mobile_cashier_close_proof_row(string $proof, int $cashierSessionId, int $terminalId): array
{
    return [
        'proof_hash' => hash('sha256', $proof),
        'mobile_token_id' => 41,
        'user_id' => 42,
        'terminal_id' => $terminalId,
        'action' => 'CASHIER_CLOSE',
        'order_id' => 0,
        'cashier_session_id' => $cashierSessionId,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        'consumed_at' => null,
    ];
}

$bootstrapFixture = [
    'outlets' => [
        ['id' => 71, 'outlet_name' => 'Outlet A'],
        ['id' => 72, 'outlet_name' => 'Outlet B', 'private_marker' => 'MUST-NOT-LEAK-OUTLET-B'],
    ],
    'terminals' => [
        ['id' => 501, 'outlet_id' => 71, 'terminal_name' => 'Terminal A'],
        ['id' => 502, 'outlet_id' => 72, 'terminal_name' => 'Terminal B', 'private_marker' => 'MUST-NOT-LEAK-TERMINAL-B'],
    ],
    'sales_channels' => [['id' => 31, 'channel_name' => 'Dine In']],
    'default_sales_channel_id' => 31,
    'default_outlet_id' => 72,
    'default_terminal_id' => 502,
    'default_opening_cash' => 300000,
    'order_reprint_printers' => [['id' => 991, 'label' => 'Printer payload retained for Batch 80B']],
    'active_session' => array_merge($matchingCashierSession, [
        'outlet_id' => 72,
        'private_marker' => 'MUST-NOT-LEAK-NESTED-ACTIVE',
    ]),
    'active_sessions' => [[
        'id' => 999,
        'outlet_id' => 72,
        'terminal_id' => 502,
        'private_marker' => 'MUST-NOT-LEAK-NESTED-GLOBAL',
    ]],
];
$filterOptionsFixture = [
    'outlets' => [
        ['id' => 71, 'outlet_name' => 'Outlet A'],
        ['id' => 72, 'outlet_name' => 'Outlet B', 'private_marker' => 'MUST-NOT-LEAK-FILTER-OUTLET-B'],
    ],
    'terminals' => [
        ['id' => 501, 'outlet_id' => 71, 'terminal_name' => 'Terminal A'],
        ['id' => 502, 'outlet_id' => 72, 'terminal_name' => 'Terminal B', 'private_marker' => 'MUST-NOT-LEAK-FILTER-TERMINAL-B'],
    ],
    'refund_payment_methods' => [['id' => 81, 'method_name' => 'Cash']],
    'reversal_reason_options' => ['VOID' => [['code' => 'OTHER', 'label' => 'Other']]],
];

foreach ([
    ['outlet_id' => 72],
    ['default_outlet_id' => 72],
    ['outlet_id' => 71, 'default_outlet_id' => 72],
] as $mismatchRequest) {
    foreach (['bootstrap', 'catalog'] as $endpoint) {
        [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $mismatchRequest,
            $boundHeaders,
            $tokenRows,
            'GET',
            $activeTerminalRows
        );
        $controller->{$endpoint}();
        pos_mobile_smoke_expect(
            $output->status === 403
                && $model->businessCalls === 0
                && $model->findActiveSessionCalls === 0
                && $model->cashierBootstrapCalls === 0
                && $model->productCatalogCalls === 0
                && $model->bundleCatalogCalls === 0,
            'bearer ' . $endpoint . ' rejects mismatched positive outlet request before POS model work'
        );
    }
}

$invalidCatalogBindings = [
    'bound outlet zero' => [array_merge($activeTerminalRows[0], ['outlet_id' => 0])],
    'bound terminal zero' => [array_merge($activeTerminalRows[0], ['id' => 0])],
];
foreach ($invalidCatalogBindings as $caseName => $terminalRows) {
    foreach (['bootstrap', 'catalog'] as $endpoint) {
        [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            [],
            $boundHeaders,
            $tokenRows,
            'GET',
            $terminalRows
        );
        $controller->{$endpoint}();
        pos_mobile_smoke_expect(
            $output->status === 401
                && $db->tokenLastSeenUpdates === 0
                && $auth->loadCalls === 0
                && $auth->attemptLoginCalls === 0
                && $model->businessCalls === 0
                && $model->findActiveSessionCalls === 0
                && $model->cashierBootstrapCalls === 0
                && ($model->businessMethods['order_draft_filter_options'] ?? 0) === 0
                && $model->productCatalogCalls === 0
                && $model->bundleCatalogCalls === 0,
            'bearer ' . $endpoint . ' rejects ' . $caseName . ' at authentication before last_seen, RBAC, or POS model work'
        );
    }
}

foreach ([
    'different outlet' => array_merge($matchingCashierSession, [
        'outlet_id' => 72,
        'private_marker' => 'MUST-NOT-LEAK-WRONG-SESSION-OUTLET',
    ]),
] as $caseName => $wrongSession) {
    foreach (['bootstrap', 'catalog'] as $endpoint) {
        [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            [],
            $boundHeaders,
            $tokenRows,
            'GET',
            $activeTerminalRows
        );
        $model->activeSession = $wrongSession;
        $model->cashierBootstrapResult = $bootstrapFixture;
        $controller->{$endpoint}();
        pos_mobile_smoke_expect(
            $output->status === 403
                && $model->businessCalls === 1
                && $model->findActiveSessionCalls === 1
                && $model->cashierBootstrapCalls === 0
                && $model->productCatalogCalls === 0
                && $model->bundleCatalogCalls === 0
                && strpos($output->body, 'MUST-NOT-LEAK') === false,
            'bearer ' . $endpoint . ' rejects active employee session on ' . $caseName . ' before bootstrap/catalog response'
        );
    }
}

$backupCashierSession = array_merge($matchingCashierSession, ['terminal_id' => 502]);
foreach (['bootstrap', 'catalog'] as $endpoint) {
    [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        [],
        $boundHeaders,
        $tokenRows,
        'GET',
        $activeTerminalRows
    );
    $model->activeSession = $backupCashierSession;
    $model->cashierBootstrapResult = $bootstrapFixture;
    $model->businessResults['order_draft_filter_options'] = $filterOptionsFixture;
    $controller->{$endpoint}();
    pos_mobile_smoke_expect(
        $output->status === 200
            && $model->findActiveSessionCalls === 1
            && ($endpoint !== 'bootstrap'
                || ($model->cashierBootstrapCalls === 1
                    && $model->productCatalogCalls === 1
                    && $model->bundleCatalogCalls === 1))
            && ($endpoint !== 'catalog'
                || ($model->productCatalogCalls === 1 && $model->bundleCatalogCalls === 0)),
        'bearer ' . $endpoint . ' accepts same-employee same-outlet OPEN backup-terminal session'
    );
}

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 0, 'default_outlet_id' => 0],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$model->activeSession = $matchingCashierSession;
$model->cashierBootstrapResult = $bootstrapFixture;
$model->businessResults['order_product_catalog'] = [['id' => 1101, 'product_name' => 'Scoped product']];
$model->businessResults['order_bundle_catalog'] = [['id' => 1201, 'bundle_name' => 'Scoped bundle']];
$model->businessResults['order_draft_filter_options'] = $filterOptionsFixture;
$model->businessResults['cashier_catalog_filter_options'] = ['categories' => [['id' => 1]]];
$model->businessResults['deposit_payment_method_options'] = [['id' => 1, 'method_name' => 'Cash']];
$controller->bootstrap();
$scopedBootstrapResponse = pos_mobile_smoke_json($output);
$scopedCashierBootstrap = (array)($scopedBootstrapResponse['cashier_bootstrap'] ?? []);
$sensitiveActionContract = (array)($scopedBootstrapResponse['sensitive_action_contract'] ?? []);
pos_mobile_smoke_expect(
    $output->status === 200
        && (int)($scopedCashierBootstrap['default_outlet_id'] ?? 0) === 71
        && (int)($scopedCashierBootstrap['default_terminal_id'] ?? 0) === 501
        && array_column((array)($scopedCashierBootstrap['outlets'] ?? []), 'id') === [71]
        && array_column((array)($scopedCashierBootstrap['terminals'] ?? []), 'id') === [501]
        && ($scopedCashierBootstrap['active_session'] ?? null) === $matchingCashierSession
        && ($scopedCashierBootstrap['active_sessions'] ?? []) === [$matchingCashierSession]
        && ($scopedBootstrapResponse['active_sessions'] ?? []) === [$matchingCashierSession]
        && ($scopedCashierBootstrap['sales_channels'] ?? []) === $bootstrapFixture['sales_channels']
        && ($scopedCashierBootstrap['order_reprint_printers'] ?? []) === $bootstrapFixture['order_reprint_printers']
        && array_column((array)($scopedBootstrapResponse['filter_options']['outlets'] ?? []), 'id') === [71]
        && array_column((array)($scopedBootstrapResponse['filter_options']['terminals'] ?? []), 'id') === [501]
        && ($scopedBootstrapResponse['filter_options']['refund_payment_methods'] ?? []) === $filterOptionsFixture['refund_payment_methods']
        && ($scopedBootstrapResponse['filter_options']['reversal_reason_options'] ?? []) === $filterOptionsFixture['reversal_reason_options']
        && ($scopedBootstrapResponse['payment_methods'] ?? []) === $model->businessResults['deposit_payment_method_options']
        && strpos($output->body, 'MUST-NOT-LEAK') === false,
    'bearer bootstrap scopes defaults, bootstrap/filter options, sessions, and markers while preserving non-scope/payment data'
);
pos_mobile_smoke_expect(
    ($sensitiveActionContract['version'] ?? null) === 3
        && ($sensitiveActionContract['proof_ttl_seconds'] ?? null) === 180
        && (($sensitiveActionContract['actions']['VOID']['verify_route'] ?? '') === 'pos-mobile/orders/reversal-step-up/verify')
        && (($sensitiveActionContract['actions']['REFUND']['submit_method'] ?? '') === 'POST')
        && (($sensitiveActionContract['actions']['ORDER_REPRINT']['verify_route'] ?? '') === 'pos-mobile/orders/reprint-step-up/verify')
        && (($sensitiveActionContract['actions']['ORDER_REPRINT']['submit_route'] ?? '') === 'pos-mobile/orders/reprint-targets/{order_id}')
        && (($sensitiveActionContract['actions']['ORDER_REPRINT']['submit_method'] ?? '') === 'POST')
        && (($sensitiveActionContract['actions']['CASHIER_CLOSE']['verify_route'] ?? '') === 'pos-mobile/cashier/close-step-up/verify')
        && (($sensitiveActionContract['actions']['CASHIER_CLOSE']['submit_route'] ?? '') === 'pos-mobile/cashier/close')
        && (($sensitiveActionContract['actions']['CASHIER_CLOSE']['submit_method'] ?? '') === 'POST')
        && (($sensitiveActionContract['actions']['RESERVATION_DEPOSIT_REFUND']['verify_route'] ?? '') === 'pos-mobile/reservations/reject-step-up/verify')
        && (($sensitiveActionContract['actions']['RESERVATION_DEPOSIT_REFUND']['submit_route'] ?? '') === 'pos-mobile/reservations/reject/{reservation_id}')
        && (($sensitiveActionContract['actions']['RESERVATION_DEPOSIT_REFUND']['submit_method'] ?? '') === 'POST'),
    'bearer bootstrap exposes a server-owned non-secret capability contract for every proof-required APK action'
);
pos_mobile_smoke_expect(
    $model->findActiveSessionCalls === 1
        && $model->globalActiveSessionCalls === 0
        && $model->cashierBootstrapCalls === 1
        && $model->productCatalogCalls === 1
        && $model->bundleCatalogCalls === 1
        && (int)($model->lastProductCatalogFilters['outlet_id'] ?? 0) === 71
        && (int)($model->lastBundleCatalogFilters['outlet_id'] ?? 0) === 71,
    'bearer bootstrap queries both catalogs at the bound outlet without calling global active sessions'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    [],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$model->activeSession = null;
$model->cashierBootstrapResult = $bootstrapFixture;
$model->businessResults['order_draft_filter_options'] = $filterOptionsFixture;
$controller->bootstrap();
$emptySessionBootstrap = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && array_key_exists('active_session', (array)($emptySessionBootstrap['cashier_bootstrap'] ?? []))
        && $emptySessionBootstrap['cashier_bootstrap']['active_session'] === null
        && ($emptySessionBootstrap['cashier_bootstrap']['active_sessions'] ?? null) === []
        && ($emptySessionBootstrap['active_sessions'] ?? null) === []
        && $model->globalActiveSessionCalls === 0
        && strpos($output->body, 'MUST-NOT-LEAK') === false,
    'bearer bootstrap returns null/empty scoped sessions when the employee has no active session'
);

foreach ([
    'product with empty outlet' => ['request' => [], 'mode' => 'PRODUCT'],
    'product with zero defaults' => ['request' => ['outlet_id' => 0, 'default_outlet_id' => 0], 'mode' => 'PRODUCT'],
    'bundle with matching defaults' => ['request' => ['outlet_id' => 71, 'default_outlet_id' => 71, 'mode' => 'BUNDLE'], 'mode' => 'BUNDLE'],
] as $caseName => $case) {
    [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $case['request'],
        $boundHeaders,
        $tokenRows,
        'GET',
        $activeTerminalRows
    );
    $model->activeSession = $matchingCashierSession;
    $model->businessResults['order_product_catalog'] = [['id' => 1301]];
    $model->businessResults['order_bundle_catalog'] = [['id' => 1302]];
    $controller->catalog();
    $catalogResponse = pos_mobile_smoke_json($output);
    $expectedBundle = $case['mode'] === 'BUNDLE';
    pos_mobile_smoke_expect(
        $output->status === 200
            && ($catalogResponse['mode'] ?? '') === $case['mode']
            && $model->findActiveSessionCalls === 1
            && $model->cashierBootstrapCalls === 0
            && $model->productCatalogCalls === ($expectedBundle ? 0 : 1)
            && $model->bundleCatalogCalls === ($expectedBundle ? 1 : 0)
            && (int)(($expectedBundle ? $model->lastBundleCatalogFilters : $model->lastProductCatalogFilters)['outlet_id'] ?? 0) === 71,
        'bearer catalog always uses bound outlet for ' . $caseName
    );
}

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    7,
    ['outlet_id' => 72, 'default_outlet_id' => 71],
    [],
    [],
    'GET'
);
$model->activeSession = array_merge($matchingCashierSession, ['outlet_id' => 99]);
$model->cashierBootstrapResult = $bootstrapFixture;
$model->businessResults['order_draft_filter_options'] = $filterOptionsFixture;
$controller->bootstrap();
$webBootstrap = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && ($webBootstrap['cashier_bootstrap'] ?? []) === $bootstrapFixture
        && ($webBootstrap['filter_options'] ?? []) === $filterOptionsFixture
        && $model->findActiveSessionCalls === 0
        && $model->globalActiveSessionCalls === 1
        && (int)($model->lastProductCatalogFilters['outlet_id'] ?? 0) === 72
        && (int)($model->lastBundleCatalogFilters['outlet_id'] ?? 0) === 72,
    'web-session bootstrap preserves legacy request precedence and unscoped response behavior'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    7,
    [],
    [],
    [],
    'GET'
);
$model->activeSession = ['outlet_id' => 72];
$controller->catalog();
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->findActiveSessionCalls === 1
        && $model->cashierBootstrapCalls === 0
        && (int)($model->lastProductCatalogFilters['outlet_id'] ?? 0) === 72,
    'web-session catalog preserves legacy active-session outlet fallback'
);

$batch81Payload = static function (string $endpoint, int $id, array $overrides = []): array {
    $payload = [
        'id' => $id,
        'outlet_id' => 0,
        'terminal_id' => 0,
        'lines' => [],
    ];
    if ($endpoint === 'orders_push') {
        $payload['client_event_id'] = 'batch-81-' . $id . '-' . count($overrides);
        $payload['local_uuid'] = 'local-batch-81-' . $id . '-' . count($overrides);
    }
    return array_replace($payload, $overrides);
};

$orderConfirmSource = pos_mobile_smoke_source_method($controllerSource, 'order_confirm');
pos_mobile_smoke_expect(
    strpos($orderConfirmSource, '$orderId = (int)($payload[\'id\'] ?? 0);') !== false
        && strpos($orderConfirmSource, '$payload[\'order_id\']') === false,
    'order_confirm scopes the canonical id field consumed by save_order_draft'
);

foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    $payload = $batch81Payload($endpoint, 0, [
        'client_event_id' => 'batch-81-new-' . $endpoint,
        'local_uuid' => 'local-new-' . $endpoint,
    ]);
    [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $payload,
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows,
        null,
        [$matchingCashierSession]
    );
    $model->activeSession = $matchingCashierSession;
    $controller->{$endpoint}();
    $isPush = $endpoint === 'orders_push';
    $storedSyncRequest = $isPush
        ? (json_decode((string)($db->lastSyncInsert['request_json'] ?? ''), true) ?: [])
        : [];
    pos_mobile_smoke_expect(
        $output->status === 422
            && $model->writerCalls === 1
            && (int)($model->lastSaveOrderPayload['id'] ?? -1) === 0
            && (int)($model->lastSaveOrderPayload['outlet_id'] ?? 0) === 71
            && (int)($model->lastSaveOrderPayload['terminal_id'] ?? 0) === 501
            && (int)($model->lastSaveOrderPayload['origin_terminal_id'] ?? 0) === 501
            && empty($model->lastSaveOrderPayload['mobile_backup_mode'])
            && !empty($model->lastSaveOrderPayload['require_active_session'])
            && $model->lastSaveOrderEmployeeId === 314
            && $model->findActiveSessionCalls === 1
            && $db->cashierSessionReads === 0
            && $db->syncReads === ($isPush ? 1 : 0)
            && $db->syncInserts === ($isPush ? 1 : 0)
            && $db->syncUpdates === ($isPush ? 1 : 0)
            && (!$isPush || (
                (int)($storedSyncRequest['outlet_id'] ?? 0) === 71
                && (int)($storedSyncRequest['terminal_id'] ?? 0) === 501
                && (int)($storedSyncRequest['origin_terminal_id'] ?? 0) === 501
                && empty($storedSyncRequest['mobile_backup_mode'])
            ))
            && $monitorModel->syncCalls === 0,
        'bearer ' . $endpoint . ' fills bound context server-side and admits a new order only with its matching active session'
    );
}

foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    $payload = $batch81Payload($endpoint, 2100, [
        'client_event_id' => 'batch-81-same-' . $endpoint,
        'local_uuid' => 'local-same-' . $endpoint,
    ]);
    [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $payload,
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows
    );
    $model->orderDraft = [
        'header' => ['id' => 2100, 'outlet_id' => 71, 'terminal_id' => 999],
        'lines' => [],
    ];
    $model->activeSession = $matchingCashierSession;
    $controller->{$endpoint}();
    $isPush = $endpoint === 'orders_push';
    pos_mobile_smoke_expect(
        $output->status === 422
            && $model->findOrderCalls === 1
            && $model->writerCalls === 1
            && (int)($model->lastSaveOrderPayload['id'] ?? 0) === 2100
            && (int)($model->lastSaveOrderPayload['outlet_id'] ?? 0) === 71
            && (int)($model->lastSaveOrderPayload['terminal_id'] ?? 0) === 501
            && (int)($model->lastSaveOrderPayload['origin_terminal_id'] ?? 0) === 501
            && empty($model->lastSaveOrderPayload['mobile_backup_mode'])
            && $model->findActiveSessionCalls === 1
            && $db->cashierSessionReads === 0
            && $db->syncReads === ($isPush ? 1 : 0)
            && $db->syncInserts === ($isPush ? 1 : 0)
            && $db->syncUpdates === ($isPush ? 1 : 0)
            && $monitorModel->syncCalls === 0,
        'bearer ' . $endpoint . ' resolves a same-outlet existing order before its writer'
    );
}

foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    $payload = $batch81Payload($endpoint, 0, [
        'client_event_id' => 'batch-81-backup-' . $endpoint,
        'local_uuid' => 'local-backup-' . $endpoint,
    ]);
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $payload,
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows
    );
    $model->activeSession = $backupCashierSession;
    $controller->{$endpoint}();
    $isPush = $endpoint === 'orders_push';
    $storedSyncRequest = $isPush
        ? (json_decode((string)($db->lastSyncInsert['request_json'] ?? ''), true) ?: [])
        : [];
    pos_mobile_smoke_expect(
        $output->status === 422
            && $model->findActiveSessionCalls === 1
            && $model->writerCalls === 1
            && (int)($model->lastSaveOrderPayload['outlet_id'] ?? 0) === 71
            && (int)($model->lastSaveOrderPayload['terminal_id'] ?? 0) === 502
            && (int)($model->lastSaveOrderPayload['origin_terminal_id'] ?? 0) === 501
            && !empty($model->lastSaveOrderPayload['mobile_backup_mode'])
            && (!$isPush || (
                (int)($storedSyncRequest['terminal_id'] ?? 0) === 502
                && (int)($storedSyncRequest['origin_terminal_id'] ?? 0) === 501
                && !empty($storedSyncRequest['mobile_backup_mode'])
            )),
        'bearer ' . $endpoint . ' preserves owner terminal and backup-device origin through writer payload'
    );
}

foreach (['cross-order', 'missing-order'] as $scopeCase) {
    foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
        $payload = $batch81Payload($endpoint, 2200, [
            'client_event_id' => 'batch-81-' . $scopeCase . '-' . $endpoint,
            'local_uuid' => 'local-' . $scopeCase . '-' . $endpoint,
        ]);
        $syncRows = [[
            'client_event_id' => $payload['client_event_id'],
            'event_status' => 'ACCEPTED',
            'response_json' => json_encode(['private_marker' => 'MUST-NOT-LEAK-BATCH-81-EARLY-REPLAY']),
            'server_order_id' => 2200,
        ]];
        [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $payload,
            $boundHeaders,
            $tokenRows,
            'POST',
            $activeTerminalRows,
            null,
            [],
            $syncRows
        );
        $model->orderDraft = $scopeCase === 'cross-order'
            ? [
                'header' => ['id' => 2200, 'outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-BATCH-81-CROSS-ORDER'],
                'lines' => [],
            ]
            : null;
        $controller->{$endpoint}();
        pos_mobile_smoke_expect(
            $output->status === 404
                && $model->findOrderCalls === 1
                && $model->findActiveSessionCalls === 0
                && $model->writerCalls === 0
                && $monitorModel->syncCalls === 0
                && $db->cashierSessionReads === 0
                && $db->syncReads === 0
                && $db->syncInserts === 0
                && $db->syncUpdates === 0
                && strpos($output->body, 'MUST-NOT-LEAK') === false,
            'bearer ' . $endpoint . ' rejects ' . $scopeCase . ' before writer, monitor, or any sync-event access'
        );
    }
}

foreach ([
    'cross outlet payload' => ['outlet_id' => 72],
    'cross terminal payload' => ['terminal_id' => 502],
] as $scopeCase => $contextOverride) {
    foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
        $payload = $batch81Payload($endpoint, 0, array_replace($contextOverride, [
            'client_event_id' => 'batch-81-' . str_replace(' ', '-', $scopeCase) . '-' . $endpoint,
            'local_uuid' => 'local-' . str_replace(' ', '-', $scopeCase) . '-' . $endpoint,
        ]));
        [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $payload,
            $boundHeaders,
            $tokenRows,
            'POST',
            $activeTerminalRows,
            null,
            [$matchingCashierSession]
        );
        $controller->{$endpoint}();
        pos_mobile_smoke_expect(
            $output->status === 403
                && $model->findOrderCalls === 0
                && $model->findActiveSessionCalls === 0
                && $model->writerCalls === 0
                && $monitorModel->syncCalls === 0
                && $db->cashierSessionReads === 0
                && $db->syncReads === 0
                && $db->syncInserts === 0
                && $db->syncUpdates === 0,
            'bearer ' . $endpoint . ' rejects ' . $scopeCase . ' before order/session/writer/sync work'
        );
    }
}

foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    $payload = $batch81Payload($endpoint, 0, [
        'client_event_id' => 'batch-81-no-session-' . $endpoint,
        'local_uuid' => 'local-no-session-' . $endpoint,
    ]);
    [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $payload,
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows
    );
    $controller->{$endpoint}();
    pos_mobile_smoke_expect(
        $output->status === 403
            && $model->businessCalls === 1
            && $model->findActiveSessionCalls === 1
            && $model->writerCalls === 0
            && $monitorModel->syncCalls === 0
            && $db->cashierSessionReads === 0
            && $db->syncReads === 0
            && $db->syncInserts === 0
            && $db->syncUpdates === 0,
        'bearer ' . $endpoint . ' rejects a new order without a compatible OPEN owner session before writer/sync work'
    );
}

foreach ([
    'employee mismatch' => array_merge($matchingCashierSession, [
        'employee_id' => 315,
        'private_marker' => 'MUST-NOT-LEAK-DRAFT-SESSION',
    ]),
    'cross outlet' => array_merge($matchingCashierSession, [
        'outlet_id' => 72,
        'private_marker' => 'MUST-NOT-LEAK-DRAFT-SESSION',
    ]),
    'closed' => array_merge($matchingCashierSession, [
        'session_status' => 'CLOSED',
        'private_marker' => 'MUST-NOT-LEAK-DRAFT-SESSION',
    ]),
] as $scopeName => $invalidSession) {
    foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
        $payload = $batch81Payload($endpoint, 0, [
            'client_event_id' => 'batch-81-invalid-session-' . str_replace(' ', '-', $scopeName) . '-' . $endpoint,
            'local_uuid' => 'local-invalid-session-' . str_replace(' ', '-', $scopeName) . '-' . $endpoint,
        ]);
        [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $payload,
            $boundHeaders,
            $tokenRows,
            'POST',
            $activeTerminalRows
        );
        $model->activeSession = $invalidSession;
        $controller->{$endpoint}();
        pos_mobile_smoke_expect(
            $output->status === 403
                && $model->findActiveSessionCalls === 1
                && $model->writerCalls === 0
                && $db->syncReads === 0
                && $db->syncInserts === 0
                && $db->syncUpdates === 0
                && $monitorModel->syncCalls === 0
                && strpos($output->body, 'MUST-NOT-LEAK') === false,
            'bearer ' . $endpoint . ' rejects ' . $scopeName . ' session without mutation or private-data leakage'
        );
    }
}

$batch81ReplayCases = [
    'stored cross outlet' => [
        'event' => [
            'request_json' => json_encode(['outlet_id' => 72, 'terminal_id' => 501]),
            'server_order_id' => 0,
        ],
        'order' => null,
        'status' => 404,
        'find_order_calls' => 0,
    ],
    'stored cross terminal' => [
        'event' => [
            'request_json' => json_encode(['outlet_id' => 71, 'terminal_id' => 502]),
            'server_order_id' => 0,
        ],
        'order' => null,
        'status' => 404,
        'find_order_calls' => 0,
    ],
    'legacy event without context or server order' => [
        'event' => ['request_json' => '{}', 'server_order_id' => 0],
        'order' => null,
        'status' => 404,
        'find_order_calls' => 0,
    ],
    'legacy event resolving cross-outlet server order' => [
        'event' => ['request_json' => '{}', 'server_order_id' => 2300],
        'order' => ['header' => ['id' => 2300, 'outlet_id' => 72], 'lines' => []],
        'status' => 404,
        'find_order_calls' => 0,
    ],
    'legacy event resolving same-outlet server order' => [
        'event' => ['request_json' => '{}', 'server_order_id' => 2300],
        'order' => ['header' => ['id' => 2300, 'outlet_id' => 71], 'lines' => []],
        'status' => 404,
        'find_order_calls' => 0,
    ],
    'stored matching authoritative context' => [
        'event' => [
            'event_type' => 'ORDER_UPSERT',
            'request_json' => json_encode(['id' => 0, 'outlet_id' => 71, 'terminal_id' => 501]),
            'response_json' => json_encode([
                'server_id' => 2300,
                'replay_value' => 'allowed-only-after-binding',
            ]),
            'server_order_id' => 2300,
        ],
        'order' => null,
        'status' => 200,
        'find_order_calls' => 0,
    ],
    'stored owner terminal with matching backup origin' => [
        'event' => [
            'event_type' => 'ORDER_UPSERT',
            'request_json' => json_encode([
                'id' => 0,
                'outlet_id' => 71,
                'terminal_id' => 502,
                'origin_terminal_id' => 501,
                'mobile_backup_mode' => true,
            ]),
            'response_json' => json_encode([
                'server_id' => 2300,
                'replay_value' => 'allowed-only-after-binding',
            ]),
            'server_order_id' => 2300,
        ],
        'order' => null,
        'status' => 200,
        'find_order_calls' => 0,
    ],
    'stored owner terminal with spoofed backup origin' => [
        'event' => [
            'event_type' => 'ORDER_UPSERT',
            'request_json' => json_encode([
                'id' => 0,
                'outlet_id' => 71,
                'terminal_id' => 502,
                'origin_terminal_id' => 503,
                'mobile_backup_mode' => true,
            ]),
            'response_json' => json_encode([
                'server_id' => 2300,
                'private_marker' => 'MUST-NOT-LEAK-BATCH-81-REPLAY',
            ]),
            'server_order_id' => 2300,
        ],
        'order' => null,
        'status' => 404,
        'find_order_calls' => 0,
    ],
];
foreach ($batch81ReplayCases as $caseName => $case) {
    $clientEventId = 'batch-81-replay-' . str_replace(' ', '-', $caseName);
    $payload = $batch81Payload('orders_push', 0, [
        'client_event_id' => $clientEventId,
        'local_uuid' => 'local-' . $clientEventId,
    ]);
    $event = $case['event'] + [
        'client_event_id' => $clientEventId,
        'event_status' => 'ACCEPTED',
        'response_json' => json_encode([
            'replay_value' => 'allowed-only-after-binding',
            'private_marker' => 'MUST-NOT-LEAK-BATCH-81-REPLAY',
        ]),
    ];
    [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $payload,
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows,
        null,
        [$matchingCashierSession],
        [$event]
    );
    $model->orderDraft = $case['order'];
    $model->activeSession = $matchingCashierSession;
    $controller->orders_push();
    $response = pos_mobile_smoke_json($output);
    $allowed = $case['status'] === 200;
    pos_mobile_smoke_expect(
        $output->status === $case['status']
            && $model->findActiveSessionCalls === 1
            && $db->cashierSessionReads === 0
            && $db->syncReads === 1
            && $db->syncInserts === 0
            && $db->syncUpdates === 0
            && $model->findOrderCalls === $case['find_order_calls']
            && $model->writerCalls === 0
            && $monitorModel->syncCalls === 0
            && strpos($output->body, 'MUST-NOT-LEAK-BATCH-81-REPLAY') === false
            && ($allowed
                ? (($response['duplicate'] ?? false) === true
                    && ($response['replay_value'] ?? '') === 'allowed-only-after-binding')
                : $response === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']),
        'bearer orders_push replay enforces ' . $caseName . ' without leaking a rejected stored response'
    );
}

foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    $payload = $batch81Payload($endpoint, 0, [
        'outlet_id' => 72,
        'terminal_id' => 502,
        'client_event_id' => 'batch-81-web-' . $endpoint,
        'local_uuid' => 'local-web-' . $endpoint,
    ]);
    [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        7,
        $payload,
        [],
        [],
        'POST'
    );
    $controller->session = new PosMobileSmokeSession(7, 314);
    $controller->{$endpoint}();
    $isPush = $endpoint === 'orders_push';
    pos_mobile_smoke_expect(
        $output->status === 422
            && $model->writerCalls === 1
            && (int)($model->lastSaveOrderPayload['outlet_id'] ?? 0) === 72
            && (int)($model->lastSaveOrderPayload['terminal_id'] ?? 0) === 502
            && $model->findOrderCalls === 0
            && $db->cashierSessionReads === 0
            && $db->syncReads === ($isPush ? 1 : 0)
            && $db->syncInserts === ($isPush ? 1 : 0)
            && $db->syncUpdates === ($isPush ? 1 : 0)
            && $monitorModel->syncCalls === 0,
        'web-session ' . $endpoint . ' preserves legacy payload and skips bearer order/session binding'
    );
}

[$controller, $auth, $output, $model, $printModel, $db, $input, $session] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    [],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$controller->orders();
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->orderRowsCalls === 1
        && (int)($model->lastOrderFilters['outlet_id'] ?? 0) === 71
        && $model->findActiveSessionCalls === 0
        && $session->userdataCalls === 0,
    'bearer orders without a web session filters by bound outlet A and does not read cashier/session context'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    99,
    [],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$model->activeSession = array_merge($matchingCashierSession, ['outlet_id' => 72]);
$controller->orders();
pos_mobile_smoke_expect(
    $output->status === 200
        && (int)($model->lastOrderFilters['outlet_id'] ?? 0) === 71
        && $model->findActiveSessionCalls === 0,
    'web cashier session at another outlet cannot override bearer outlet scope'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    [],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$model->orderDraft = [
    'header' => ['id' => 1001, 'outlet_id' => 71, 'terminal_id' => 999, 'order_no' => 'SAME-OUTLET'],
    'lines' => [],
];
$controller->order_load(1001);
$sameOutletOrder = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && ($sameOutletOrder['header']['order_no'] ?? '') === 'SAME-OUTLET'
        && $model->findOrderCalls === 1,
    'bearer order_load returns an order from the same outlet even when its terminal differs'
);

foreach ([
    'different outlet' => [
        'header' => ['id' => 1002, 'outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-DIFFERENT'],
        'lines' => [],
    ],
    'missing outlet' => [
        'header' => ['id' => 1003, 'private_marker' => 'MUST-NOT-LEAK-MISSING'],
        'lines' => [],
    ],
] as $caseName => $orderDraft) {
    [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        [],
        $boundHeaders,
        $tokenRows,
        'GET',
        $activeTerminalRows
    );
    $model->orderDraft = $orderDraft;
    $controller->order_load((int)$orderDraft['header']['id']);
    $error = pos_mobile_smoke_json($output);
    pos_mobile_smoke_expect(
        $output->status === 404
            && $error === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
            && strpos($output->body, (string)$orderDraft['header']['private_marker']) === false
            && $model->findOrderCalls === 1,
        'bearer order_load returns generic payload-free 404 for ' . $caseName
    );
}

$zeroOutletTerminalRows = [array_merge($activeTerminalRows[0], ['outlet_id' => 0])];
foreach ([['orders', []], ['order_load', [1004]]] as [$endpoint, $arguments]) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        [],
        $boundHeaders,
        $tokenRows,
        'GET',
        $zeroOutletTerminalRows
    );
    call_user_func_array([$controller, $endpoint], $arguments);
    pos_mobile_smoke_expect(
        $output->status === 401
            && $db->tokenLastSeenUpdates === 0
            && $auth->loadCalls === 0
            && $auth->attemptLoginCalls === 0
            && $model->businessCalls === 0
            && $model->orderRowsCalls === 0
            && $model->findOrderCalls === 0
            && $model->findActiveSessionCalls === 0,
        'bearer ' . $endpoint . ' with bound outlet 0 fails at authentication before last_seen, RBAC, or POS model work'
    );
}

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    7,
    [],
    [],
    [],
    'GET'
);
$model->activeSession = ['outlet_id' => 72];
$controller->orders();
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->findActiveSessionCalls === 1
        && (int)($model->lastOrderFilters['outlet_id'] ?? 0) === 72,
    'web-session orders fallback keeps using the active cashier session outlet'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    7,
    [],
    [],
    [],
    'GET'
);
$model->orderDraft = [
    'header' => ['id' => 1005, 'order_no' => 'WEB-LEGACY-NO-OUTLET'],
    'lines' => [],
];
$controller->order_load(1005);
$webOrder = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && ($webOrder['header']['order_no'] ?? '') === 'WEB-LEGACY-NO-OUTLET'
        && $model->findOrderCalls === 1,
    'web-session order_load fallback remains compatible without bearer outlet scoping'
);

$directOrderEndpointCases = [
    'order_reversal_preview' => [
        'arguments' => [1101],
        'payload' => [],
        'downstream' => 'order_reversal_preview',
        'result' => ['ok' => true],
    ],
    'order_reprint_targets' => [
        'arguments' => [1102],
        'payload' => ['printer_id' => 0, 'line_scope' => 'ALL'],
        'method' => 'POST',
        'step_up_action' => 'ORDER_REPRINT',
        'downstream' => 'direct_print_targets_for_order_reprint',
        'result' => ['ok' => true, 'targets' => []],
    ],
    'order_confirm_print_targets' => [
        'arguments' => [1103],
        'payload' => [],
        'downstream' => 'direct_print_targets_for_order_confirm',
        'result' => ['ok' => true, 'targets' => []],
    ],
    'payment_prepare' => [
        'arguments' => [1104],
        'payload' => [],
        'downstream' => 'cashier_payment_prepare',
        'result' => ['ok' => true],
    ],
    'voucher_search' => [
        'arguments' => [],
        'payload' => ['order_id' => 1105, 'q' => 'VCR', 'limit' => 8],
        'downstream' => 'search_cashier_vouchers',
        'result' => ['ok' => true, 'rows' => []],
    ],
];

foreach ([
    'same outlet and terminal' => 501,
    'same outlet from another terminal' => 999,
] as $scopeName => $orderTerminalId) {
    foreach ($directOrderEndpointCases as $endpoint => $case) {
        $orderId = $endpoint === 'voucher_search'
            ? (int)$case['payload']['order_id']
            : (int)$case['arguments'][0];
        $payload = $case['payload'];
        $mobileProofRows = [];
        if (!empty($case['step_up_action'])) {
            $proof = str_repeat('d', 64);
            $payload['step_up_proof'] = $proof;
            $mobileProofRows[] = [
                'proof_hash' => hash('sha256', $proof),
                'mobile_token_id' => 41,
                'user_id' => 42,
                'terminal_id' => 501,
                'action' => (string)$case['step_up_action'],
                'order_id' => $orderId,
                'expires_at' => '2999-01-01 00:00:00',
                'consumed_at' => null,
            ];
        }
        [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $payload,
            $boundHeaders,
            $tokenRows,
            $case['method'] ?? 'GET',
            $activeTerminalRows,
            null,
            [],
            [],
            ['state' => 'GLOBAL', 'division_id' => null],
            $mobileProofRows
        );
        $model->orderDraft = [
            'header' => ['id' => $orderId, 'outlet_id' => 71, 'terminal_id' => $orderTerminalId],
            'lines' => [],
        ];
        $model->businessResults[$case['downstream']] = $case['result'];
        call_user_func_array([$controller, $endpoint], $case['arguments']);
        pos_mobile_smoke_expect(
            $output->status === 200
                && $model->findOrderCalls === 1
                && ($model->businessMethods[$case['downstream']] ?? 0) === 1,
            'bearer ' . $endpoint . ' accepts ' . $scopeName
        );
    }
}

foreach ([
    'order not found' => null,
    'order outlet missing' => [
        'header' => ['outlet_id' => 0, 'private_marker' => 'MUST-NOT-LEAK-MISSING-OUTLET'],
        'lines' => [],
    ],
    'order from another outlet' => [
        'header' => ['outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-CROSS-OUTLET'],
        'lines' => [],
    ],
] as $scopeName => $orderDraft) {
    foreach ($directOrderEndpointCases as $endpoint => $case) {
        [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $case['payload'],
            $boundHeaders,
            $tokenRows,
            $case['method'] ?? 'GET',
            $activeTerminalRows
        );
        $model->orderDraft = $orderDraft;
        $model->businessResults[$case['downstream']] = $case['result'];
        call_user_func_array([$controller, $endpoint], $case['arguments']);
        $error = pos_mobile_smoke_json($output);
        pos_mobile_smoke_expect(
            $output->status === 404
                && $error === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
                && $model->findOrderCalls === 1
                && ($model->businessMethods[$case['downstream']] ?? 0) === 0
                && strpos($output->body, (string)($orderDraft['header']['private_marker'] ?? 'MUST-NOT-EXIST')) === false,
            'bearer ' . $endpoint . ' returns generic 404 with no downstream call for ' . $scopeName
        );
    }
}

foreach ($directOrderEndpointCases as $endpoint => $case) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $case['payload'],
        $boundHeaders,
        $tokenRows,
        $case['method'] ?? 'GET',
        $zeroOutletTerminalRows
    );
    $model->orderDraft = ['header' => ['outlet_id' => 71], 'lines' => []];
    $model->businessResults[$case['downstream']] = $case['result'];
    call_user_func_array([$controller, $endpoint], $case['arguments']);
    pos_mobile_smoke_expect(
        $output->status === 401
            && $db->tokenLastSeenUpdates === 0
            && $auth->loadCalls === 0
            && $auth->attemptLoginCalls === 0
            && $model->businessCalls === 0
            && $model->findOrderCalls === 0
            && ($model->businessMethods[$case['downstream']] ?? 0) === 0
            && $printModel->readyCalls === 0
            && $printModel->attemptCalls === 0,
        'bearer ' . $endpoint . ' with bound outlet 0 fails at authentication before resolver and downstream calls'
    );
}

foreach ($directOrderEndpointCases as $endpoint => $case) {
    $payload = $case['payload'];
    if (!empty($case['step_up_action'])) {
        $payload['step_up_proof'] = str_repeat('e', 64);
    }
    [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        7,
        $payload,
        [],
        [],
        $case['method'] ?? 'GET'
    );
    $model->businessResults[$case['downstream']] = $case['result'];
    call_user_func_array([$controller, $endpoint], $case['arguments']);
    pos_mobile_smoke_expect(
        $output->status === 200
            && $model->findOrderCalls === 0
            && ($model->businessMethods[$case['downstream']] ?? 0) === 1,
        'web-session ' . $endpoint . ' fallback keeps the existing unscoped behavior'
    );
}

$mobilePrintEndpointCases = [
    'order_void_print_targets' => [
        'type' => 'VOID',
        'id' => 3101,
        'downstream' => 'direct_print_targets_for_void',
    ],
    'order_refund_print_targets' => [
        'type' => 'REFUND',
        'id' => 3102,
        'downstream' => 'direct_print_targets_for_refund',
    ],
    'payment_print_targets' => [
        'type' => 'PAYMENT',
        'id' => 3103,
        'downstream' => 'direct_print_targets_for_payment',
    ],
];

foreach ($mobilePrintEndpointCases as $endpoint => $case) {
    $methodSource = pos_mobile_smoke_source_method($controllerSource, $endpoint);
    $authPosition = strpos($methodSource, '$this->authorize_mobile(true)');
    $permissionPosition = strpos($methodSource, '$this->mobile_permission');
    $scopePosition = strpos($methodSource, '$this->require_mobile_print_document_outlet');
    $directPrintPosition = strpos($methodSource, '$this->Pos_model->' . $case['downstream']);
    pos_mobile_smoke_expect(
        $authPosition !== false
            && $permissionPosition > $authPosition
            && $scopePosition > $permissionPosition
            && $directPrintPosition > $scopePosition,
        $endpoint . ' orders authentication, RBAC, document outlet scope, then direct print'
    );
}

$mobilePrintScopeCases = [
    'same outlet from another terminal' => [
        'context' => ['document_id' => 0, 'order_id' => 4101, 'outlet_id' => 71, 'terminal_id' => 999],
        'status' => 200,
        'direct_calls' => 1,
    ],
    'cross outlet' => [
        'context' => ['document_id' => 0, 'order_id' => 4102, 'outlet_id' => 72, 'terminal_id' => 501],
        'status' => 404,
        'direct_calls' => 0,
    ],
    'missing document' => [
        'context' => null,
        'status' => 404,
        'direct_calls' => 0,
    ],
    'orphan document' => [
        'context' => null,
        'status' => 404,
        'direct_calls' => 0,
    ],
    'document with outlet 0' => [
        'context' => ['document_id' => 0, 'order_id' => 4103, 'outlet_id' => 0, 'terminal_id' => 501],
        'status' => 404,
        'direct_calls' => 0,
    ],
];

foreach ($mobilePrintEndpointCases as $endpoint => $case) {
    foreach ($mobilePrintScopeCases as $scopeName => $scopeCase) {
        [$controller, $auth, $output, $model, $printModel] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            [],
            $boundHeaders,
            $tokenRows,
            'GET',
            $activeTerminalRows
        );
        if ($scopeCase['context'] !== null) {
            $model->mobilePrintContexts[$case['type']][$case['id']] = array_merge(
                $scopeCase['context'],
                ['document_id' => $case['id']]
            );
        }
        $model->businessResults[$case['downstream']] = ['ok' => true, 'targets' => []];
        $controller->{$endpoint}($case['id']);
        $error = pos_mobile_smoke_json($output);
        pos_mobile_smoke_expect(
            $output->status === $scopeCase['status']
                && $model->mobilePrintContextCalls === 1
                && $model->lastMobilePrintDocumentType === $case['type']
                && $model->lastMobilePrintDocumentId === $case['id']
                && ($model->businessMethods[$case['downstream']] ?? 0) === $scopeCase['direct_calls']
                && $printModel->attemptCalls === 0,
            'bearer ' . $endpoint . ' enforces document outlet scope for ' . $scopeName
        );
        if ($scopeCase['status'] === 404) {
            pos_mobile_smoke_expect(
                $error === ['ok' => false, 'message' => 'Order POS tidak ditemukan.'],
                'bearer ' . $endpoint . ' returns a generic document 404 for ' . $scopeName
            );
        }
    }
}

[$controller, $auth, $output, $model, $printModel] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    [],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$model->businessResults['direct_print_targets_for_payment'] = ['ok' => true, 'targets' => []];
$controller->payment_print_targets(3199);
pos_mobile_smoke_expect(
    $output->status === 404
        && pos_mobile_smoke_json($output) === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
        && $model->mobilePrintContextCalls === 1
        && ($model->businessMethods['direct_print_targets_for_payment'] ?? 0) === 0
        && $printModel->attemptCalls === 0,
    'bearer payment print rejects a payment without a parent order before direct print or attempt'
);

foreach ($mobilePrintEndpointCases as $endpoint => $case) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        [],
        $boundHeaders,
        $tokenRows,
        'GET',
        $zeroOutletTerminalRows
    );
    $model->mobilePrintContexts[$case['type']][$case['id']] = [
        'document_id' => $case['id'],
        'order_id' => 4201,
        'outlet_id' => 71,
        'terminal_id' => 501,
    ];
    $model->businessResults[$case['downstream']] = ['ok' => true, 'targets' => []];
    $controller->{$endpoint}($case['id']);
    pos_mobile_smoke_expect(
        $output->status === 401
            && $db->tokenLastSeenUpdates === 0
            && $auth->loadCalls === 0
            && $auth->attemptLoginCalls === 0
            && $model->mobilePrintContextCalls === 0
            && ($model->businessMethods[$case['downstream']] ?? 0) === 0
            && $printModel->attemptCalls === 0,
        'bearer ' . $endpoint . ' with bound outlet 0 fails before context, direct print, or attempt'
    );

    [$controller, $auth, $output, $model, $printModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        7,
        [],
        [],
        [],
        'GET'
    );
    $model->businessResults[$case['downstream']] = ['ok' => true, 'targets' => []];
    $controller->{$endpoint}($case['id']);
    pos_mobile_smoke_expect(
        $output->status === 200
            && $model->mobilePrintContextCalls === 0
            && ($model->businessMethods[$case['downstream']] ?? 0) === 1,
        'web-session ' . $endpoint . ' keeps the existing unscoped fallback'
    );
}

$mobilePrintPreScopeRejectCases = [
    'RBAC denied' => [
        'permissions' => [],
        'headers' => $boundHeaders,
        'tokens' => $tokenRows,
        'terminals' => $activeTerminalRows,
        'status' => 403,
    ],
    'authentication denied' => [
        'permissions' => ['__superadmin__' => true],
        'headers' => [
            'Authorization' => 'Bearer invalid-mobile-print-token',
            'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
        ],
        'tokens' => $tokenRows,
        'terminals' => $activeTerminalRows,
        'status' => 401,
    ],
    'device binding denied' => [
        'permissions' => ['__superadmin__' => true],
        'headers' => [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => 'different-mobile-print-device',
        ],
        'tokens' => $tokenRows,
        'terminals' => $activeTerminalRows,
        'status' => 401,
    ],
];

foreach ($mobilePrintEndpointCases as $endpoint => $case) {
    foreach ($mobilePrintPreScopeRejectCases as $rejectName => $rejectCase) {
        [$controller, $auth, $output, $model, $printModel] = pos_mobile_smoke_controller(
            $rejectCase['permissions'],
            true,
            0,
            [],
            $rejectCase['headers'],
            $rejectCase['tokens'],
            'GET',
            $rejectCase['terminals']
        );
        $model->mobilePrintContexts[$case['type']][$case['id']] = [
            'document_id' => $case['id'],
            'order_id' => 4301,
            'outlet_id' => 71,
            'terminal_id' => 501,
        ];
        $model->businessResults[$case['downstream']] = ['ok' => true, 'targets' => []];
        $controller->{$endpoint}($case['id']);
        pos_mobile_smoke_expect(
            $output->status === $rejectCase['status']
                && $model->mobilePrintContextCalls === 0
                && ($model->businessMethods[$case['downstream']] ?? 0) === 0
                && $printModel->attemptCalls === 0,
            $endpoint . ' stops before context, direct print, and attempt when ' . $rejectName
        );
    }
}

$financialWriterCases = [
    'order_void_save' => [
        'model_method' => 'save_order_void',
        'result' => ['ok' => true, 'id' => 2101],
        'monitor_calls' => 1,
    ],
    'order_refund_save' => [
        'model_method' => 'save_order_refund',
        'result' => ['ok' => true, 'id' => 2102],
        'monitor_calls' => 1,
    ],
    'payment_save' => [
        'model_method' => 'save_cashier_payment',
        'result' => [
            'ok' => true,
            'id' => 2103,
            'payment_no' => 'PAY-SMOKE-2103',
            'order_status' => 'PAID',
        ],
        'monitor_calls' => 0,
    ],
];
$financialWriterScopeCases = [
    'same outlet from another terminal' => [
        'status' => 200,
        'order_id' => 2100,
        'order' => ['header' => ['id' => 2100, 'outlet_id' => 71, 'terminal_id' => 999], 'lines' => []],
        'terminals' => $activeTerminalRows,
        'accepted' => true,
    ],
    'cross outlet' => [
        'status' => 404,
        'order_id' => 2100,
        'order' => [
            'header' => ['id' => 2100, 'outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-WRITER-CROSS'],
            'lines' => [],
        ],
        'terminals' => $activeTerminalRows,
        'accepted' => false,
    ],
    'missing order' => [
        'status' => 404,
        'order_id' => 2100,
        'order' => null,
        'terminals' => $activeTerminalRows,
        'accepted' => false,
    ],
    'bound outlet 0' => [
        'status' => 401,
        'order_id' => 2100,
        'order' => ['header' => ['id' => 2100, 'outlet_id' => 71], 'lines' => []],
        'terminals' => $zeroOutletTerminalRows,
        'accepted' => false,
    ],
    'invalid order id' => [
        'status' => 404,
        'order_id' => 'invalid-order-id',
        'order' => null,
        'terminals' => $activeTerminalRows,
        'accepted' => false,
    ],
];

foreach ($financialWriterCases as $endpoint => $writerCase) {
    foreach ($financialWriterScopeCases as $scopeName => $scopeCase) {
        $clientEventId = 'evt-' . str_replace('_', '-', $endpoint) . '-' . str_replace(' ', '-', $scopeName);
        $payload = [
            'order_id' => $scopeCase['order_id'],
            'client_event_id' => $endpoint === 'payment_save' ? $clientEventId : '',
        ];
        $mobileProofRows = [];
        if (in_array($endpoint, ['order_void_save', 'order_refund_save'], true) && $scopeCase['accepted']) {
            $proof = $endpoint === 'order_void_save' ? str_repeat('a', 64) : str_repeat('b', 64);
            $payload['step_up_proof'] = $proof;
            $mobileProofRows[] = [
                'proof_hash' => hash('sha256', $proof),
                'mobile_token_id' => 41,
                'user_id' => 42,
                'terminal_id' => 501,
                'action' => $endpoint === 'order_void_save' ? 'VOID' : 'REFUND',
                'order_id' => 2100,
                'expires_at' => '2999-01-01 00:00:00',
                'consumed_at' => null,
            ];
        }
        $syncRows = $endpoint === 'payment_save' && $scopeName === 'cross outlet'
            ? [[
                'client_event_id' => $clientEventId,
                'event_status' => 'ACCEPTED',
                'response_json' => json_encode(['private_marker' => 'MUST-NOT-LEAK-PAYMENT-REPLAY']),
            ]]
            : [];
        [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel, $loader] = pos_mobile_smoke_controller(
            ['__superadmin__' => true],
            true,
            0,
            $payload,
            $boundHeaders,
            $tokenRows,
            'POST',
            $scopeCase['terminals'],
            null,
            [],
            $syncRows,
            ['state' => 'GLOBAL', 'division_id' => null],
            $mobileProofRows
        );
        $model->orderDraft = $scopeCase['order'];
        $model->writerResults[$writerCase['model_method']] = $writerCase['result'];
        $controller->{$endpoint}();
        $prefix = 'bearer ' . $endpoint . ' ' . $scopeName;
        pos_mobile_smoke_expect($output->status === $scopeCase['status'], $prefix . ' returns the expected status');

        if ($scopeCase['accepted']) {
            $expectedSyncReads = $endpoint === 'payment_save' ? 1 : 0;
            $expectedSyncInserts = $endpoint === 'payment_save' ? 1 : 0;
            $expectedSyncUpdates = $endpoint === 'payment_save' ? 1 : 0;
            pos_mobile_smoke_expect(
                $model->findOrderCalls === 1
                    && $model->businessCalls === 1
                    && $model->writerCalls === 1
                    && $monitorModel->syncCalls === $writerCase['monitor_calls']
                    && $db->syncReads === $expectedSyncReads
                    && $db->syncInserts === $expectedSyncInserts
                    && $db->syncUpdates === $expectedSyncUpdates,
                $prefix . ' reaches the writer only after outlet resolution and one-use proof consumption'
            );
            continue;
        }

        $expectedFindCalls = $scopeName === 'bound outlet 0' ? 0 : 1;
        pos_mobile_smoke_expect(
            $model->findOrderCalls === $expectedFindCalls
                && $model->businessCalls === $expectedFindCalls
                && $model->writerCalls === 0
                && $monitorModel->syncCalls === 0
                && $loader->modelCalls === 0
                && $db->syncReads === 0
                && $db->syncInserts === 0
                && $db->syncUpdates === 0,
            $prefix . ' reaches no writer, sync event, or monitor work after rejection'
        );
        if ($scopeName === 'bound outlet 0') {
            pos_mobile_smoke_expect(
                $db->tokenLastSeenUpdates === 0
                    && $auth->loadCalls === 0
                    && $auth->attemptLoginCalls === 0,
                $prefix . ' fails authentication before last_seen or permission loading'
            );
        }
        if ($scopeCase['status'] === 404) {
            pos_mobile_smoke_expect(
                pos_mobile_smoke_json($output) === ['ok' => false, 'message' => 'Order POS tidak ditemukan.'],
                $prefix . ' returns the generic order 404'
            );
        }
        if ($endpoint === 'payment_save' && $scopeName === 'cross outlet') {
            pos_mobile_smoke_expect(
                strpos($output->body, 'MUST-NOT-LEAK-PAYMENT-REPLAY') === false,
                'payment replay from another outlet is rejected before reading its stored response event'
            );
        }
    }
}

foreach ($financialWriterCases as $endpoint => $writerCase) {
    $webPayload = ['order_id' => $endpoint === 'payment_save' ? 0 : 2100];
    if (in_array($endpoint, ['order_void_save', 'order_refund_save'], true)) {
        $webPayload['step_up_proof'] = str_repeat('c', 64);
    }
    [$controller, $auth, $output, $model, $printModel, $db, $input, $session, $monitorModel] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        7,
        $webPayload,
        [],
        [],
        'POST'
    );
    $model->writerResults[$writerCase['model_method']] = $writerCase['result'];
    $controller->{$endpoint}();
    pos_mobile_smoke_expect(
        $output->status === 200
            && $model->findOrderCalls === 0
            && $model->writerCalls === 1
            && $monitorModel->syncCalls === $writerCase['monitor_calls']
            && $db->syncReads === 0
            && $db->syncInserts === 0
            && $db->syncUpdates === 0,
        'web-session ' . $endpoint . ' fallback remains compatible and unscoped'
    );
}

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501, 'opening_cash' => 100000],
    $boundHeaders,
    $tokenRows,
    'POST',
    $activeTerminalRows
);
$model->openSessionResult = ['ok' => true, 'session' => $matchingCashierSession, 'already_open' => false];
$controller->cashier_open();
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->reconCalls === 1
        && $model->openSessionCalls === 1
        && (int)($model->lastOpenSessionPayload['terminal_id'] ?? 0) === 501
        && (int)($model->lastOpenSessionPayload['origin_terminal_id'] ?? 0) === 501
        && empty($model->lastOpenSessionPayload['mobile_backup_mode']),
    'cashier_open accepts owner device and records matching owner/origin context'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501, 'opening_cash' => 100000],
    $boundHeaders,
    $tokenRows,
    'POST',
    $activeTerminalRows
);
$model->activeSession = $backupCashierSession;
$model->openSessionResult = ['ok' => true, 'session' => $backupCashierSession, 'already_open' => true];
$controller->cashier_open();
$backupOpenResponse = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->findActiveSessionCalls === 1
        && $model->reconCalls === 1
        && $model->openSessionCalls === 1
        && (int)($model->lastOpenSessionPayload['terminal_id'] ?? 0) === 502
        && (int)($model->lastOpenSessionPayload['origin_terminal_id'] ?? 0) === 501
        && !empty($model->lastOpenSessionPayload['mobile_backup_mode'])
        && !empty($backupOpenResponse['backup_mode'])
        && !empty($backupOpenResponse['attached_to_existing_session']),
    'cashier_open attaches registered backup device while preserving session owner and device origin'
);

foreach ([
    'existing session at different outlet' => array_merge($matchingCashierSession, ['outlet_id' => 72]),
    'existing session for different employee' => array_merge($matchingCashierSession, ['employee_id' => 315]),
    'existing closed session' => array_merge($matchingCashierSession, ['session_status' => 'CLOSED']),
] as $caseName => $existingSession) {
    [$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        ['outlet_id' => 71, 'terminal_id' => 501, 'opening_cash' => 100000],
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows
    );
    $model->activeSession = $existingSession;
    $controller->cashier_open();
    pos_mobile_smoke_expect($output->status === 403, 'cashier_open rejects ' . $caseName);
    pos_mobile_smoke_expect(
        $model->findActiveSessionCalls === 1
            && $model->reconCalls === 0
            && $model->openSessionCalls === 0
            && $model->writerCalls === 0,
        'cashier_open rejects ' . $caseName . ' before recon and open work'
    );
}

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501, 'actual_cash' => 100000],
    $boundHeaders,
    $tokenRows,
    'POST',
    $activeTerminalRows,
    null,
    [$matchingCashierSession]
);
$model->activeSession = $matchingCashierSession;
$model->closeSessionResult = ['ok' => true, 'shift_id' => 901, 'report' => [], 'summary' => []];
$controller->cashier_close();
pos_mobile_smoke_expect(
    $output->status === 428
        && $model->findActiveSessionCalls === 1
        && $model->reconCalls === 0
        && $model->closeSessionCalls === 0
        && $model->shiftClosePrintCalls === 0,
    'cashier_close bearer requires a fresh cashier-close proof before recon, close, or printing'
);

$matchingCloseProof = str_repeat('a', 64);
[$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501, 'actual_cash' => 100000, 'step_up_proof' => $matchingCloseProof],
    $boundHeaders,
    $tokenRows,
    'POST',
    $activeTerminalRows,
    null,
    [$matchingCashierSession],
    [],
    ['state' => 'GLOBAL', 'division_id' => null],
    [pos_mobile_cashier_close_proof_row($matchingCloseProof, 801, 501)]
);
$model->activeSession = $matchingCashierSession;
$model->closeSessionResult = ['ok' => true, 'shift_id' => 901, 'report' => [], 'summary' => []];
$controller->cashier_close();
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->reconCalls === 1
        && $model->closeSessionCalls === 1
        && $model->shiftClosePrintCalls === 1
        && $db->mobileProofUpdates >= 1,
    'cashier_close consumes a one-time proof bound to matching active session A/A'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows,
    null,
    [$matchingCashierSession]
);
$model->activeSession = $matchingCashierSession;
$model->closePreviewResult = ['ok' => true, 'shift_id' => 901, 'session' => $matchingCashierSession, 'report' => []];
$controller->cashier_close_preview();
pos_mobile_smoke_expect($output->status === 200 && $model->closePreviewCalls === 1, 'cashier_close_preview accepts matching active session A/A');

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows,
    null,
    [$matchingCashierSession]
);
$model->activeSession = $matchingCashierSession;
$controller->session_status();
$sessionStatus = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->findActiveSessionCalls === 1
        && $model->globalActiveSessionCalls === 0
        && count((array)($sessionStatus['active_sessions'] ?? [])) === 1,
    'bearer session_status returns only its own session and never loads the global session list'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$controller->session_status();
$emptySessionStatus = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && array_key_exists('session', $emptySessionStatus)
        && $emptySessionStatus['session'] === null
        && ($emptySessionStatus['active_sessions'] ?? null) === []
        && $model->findActiveSessionCalls === 1
        && $model->globalActiveSessionCalls === 0,
    'bearer session_status without an active session returns 200 with null session and an empty scoped list'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows,
    null,
    [$matchingCashierSession]
);
$model->activeSession = array_merge($matchingCashierSession, ['terminal_id' => 502]);
$controller->session_status();
$backupSessionStatus = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->findActiveSessionCalls === 1
        && $model->globalActiveSessionCalls === 0
        && !empty($backupSessionStatus['backup_mode'])
        && (int)($backupSessionStatus['owner_terminal_id'] ?? 0) === 502
        && (int)($backupSessionStatus['origin_terminal_id'] ?? 0) === 501,
    'bearer session_status reports owner terminal and origin device for backup mode'
);

$backupCloseProof = str_repeat('b', 64);
[$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 501, 'actual_cash' => 100000, 'step_up_proof' => $backupCloseProof],
    $boundHeaders,
    $tokenRows,
    'POST',
    $activeTerminalRows,
    null,
    [],
    [],
    ['state' => 'GLOBAL', 'division_id' => null],
    [pos_mobile_cashier_close_proof_row($backupCloseProof, 801, 501)]
);
$model->activeSession = $backupCashierSession;
$model->closeSessionResult = ['ok' => true, 'shift_id' => 901, 'report' => [], 'summary' => []];
$controller->cashier_close();
pos_mobile_smoke_expect(
    $output->status === 200
        && $model->findActiveSessionCalls === 1
        && $model->reconCalls === 1
        && $model->closeSessionCalls === 1
        && $model->shiftClosePrintCalls === 1,
    'cashier_close allows registered backup device on the same employee/outlet OPEN owner session'
);

$sessionGuardCases = [
    'open different terminal' => [
        'endpoint' => 'cashier_open',
        'method' => 'POST',
        'request' => ['outlet_id' => 71, 'terminal_id' => 502],
        'sessions' => [],
    ],
    'open different outlet' => [
        'endpoint' => 'cashier_open',
        'method' => 'POST',
        'request' => ['outlet_id' => 72, 'terminal_id' => 501],
        'sessions' => [],
    ],
    'close session different outlet' => [
        'endpoint' => 'cashier_close',
        'method' => 'POST',
        'request' => ['outlet_id' => 71, 'terminal_id' => 501],
        'sessions' => [array_merge($matchingCashierSession, ['outlet_id' => 72])],
    ],
    'close inactive session' => [
        'endpoint' => 'cashier_close',
        'method' => 'POST',
        'request' => ['outlet_id' => 71, 'terminal_id' => 501],
        'sessions' => [array_merge($matchingCashierSession, ['session_status' => 'CLOSED'])],
    ],
    'close session different employee' => [
        'endpoint' => 'cashier_close',
        'method' => 'POST',
        'request' => ['outlet_id' => 71, 'terminal_id' => 501],
        'sessions' => [array_merge($matchingCashierSession, ['employee_id' => 315])],
    ],
    'status different outlet session' => [
        'endpoint' => 'session_status',
        'method' => 'GET',
        'request' => ['outlet_id' => 71, 'terminal_id' => 501],
        'sessions' => [array_merge($matchingCashierSession, ['outlet_id' => 72])],
    ],
];
foreach ($sessionGuardCases as $caseName => $case) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        ['__superadmin__' => true],
        true,
        0,
        $case['request'],
        $boundHeaders,
        $tokenRows,
        $case['method'],
        $activeTerminalRows,
        null,
        $case['sessions']
    );
    $model->activeSession = $case['sessions'][0] ?? null;
    call_user_func([$controller, $case['endpoint']]);
    pos_mobile_smoke_expect($output->status === 403, $caseName . ' is rejected with 403');
    $expectedSessionReads = $case['endpoint'] === 'cashier_open' ? 0 : 1;
    pos_mobile_smoke_expect(
        $model->businessCalls === $expectedSessionReads
            && $model->writerCalls === 0
            && $model->reconCalls === 0
            && $model->openSessionCalls === 0
            && $model->closeSessionCalls === 0
            && $model->closePreviewCalls === 0
            && $model->findActiveSessionCalls === $expectedSessionReads
            && $model->globalActiveSessionCalls === 0
            && $model->shiftClosePrintCalls === 0
            && $printModel->readyCalls === 0
            && $printModel->attemptCalls === 0,
        $caseName . ' fails before model, gate, close, and printer work'
    );
}

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    ['__superadmin__' => true],
    true,
    0,
    ['outlet_id' => 71, 'terminal_id' => 502],
    $boundHeaders,
    $tokenRows,
    'GET',
    $activeTerminalRows
);
$model->activeSession = $matchingCashierSession;
$model->closePreviewResult = ['ok' => true, 'shift_id' => 901, 'session' => $matchingCashierSession, 'report' => []];
$controller->cashier_close_preview();
pos_mobile_smoke_expect(
    $output->status === 200 && $model->findActiveSessionCalls === 1 && $model->closePreviewCalls === 1,
    'cashier_close_preview uses the authoritative bearer session instead of request terminal data'
);

$logoutCases = [
    'matching active binding' => [
        'device_key' => $boundDeviceKey,
        'terminals' => $activeTerminalRows,
        'status' => 200,
        'revoke_count' => 1,
        'last_seen_count' => 1,
    ],
    'mismatched binding' => [
        'device_key' => 'device-mismatch',
        'terminals' => $activeTerminalRows,
        'status' => 401,
        'revoke_count' => 0,
        'last_seen_count' => 0,
    ],
    'inactive binding' => [
        'device_key' => $boundDeviceKey,
        'terminals' => [['id' => 501, 'outlet_id' => 71, 'device_key' => $boundDeviceKey, 'is_active' => 0]],
        'status' => 401,
        'revoke_count' => 0,
        'last_seen_count' => 0,
    ],
];
foreach ($logoutCases as $caseName => $case) {
    [$controller, $auth, $output, $model, $printModel, $db] = pos_mobile_smoke_controller(
        [],
        true,
        7,
        [],
        [
            'Authorization' => 'Bearer ' . $validToken,
            'X-Pos-Mobile-Device-Key' => $case['device_key'],
        ],
        $tokenRows,
        'POST',
        $case['terminals']
    );
    $controller->logout();
    pos_mobile_smoke_expect($output->status === $case['status'], 'logout ' . $caseName . ' returns the expected status');
    pos_mobile_smoke_expect(
        $db->tokenRevokeUpdates === $case['revoke_count']
            && $db->tokenLastSeenUpdates === $case['last_seen_count']
            && ($case['revoke_count'] === 1
                ? !empty($db->token_rows()[0]['revoked_at'])
                : (($db->token_rows()[0]['revoked_at'] ?? null) === null)),
        'logout ' . $caseName . ' has the expected last_seen and revoke mutations'
    );
}

[$controller, $auth, $output] = pos_mobile_smoke_controller(
    [],
    true,
    0,
    [],
    [
        'Authorization' => 'Bearer batch-4a-invalid-token',
        'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
    ],
    $tokenRows,
    'POST',
    $activeTerminalRows
);
$invalidTokenAuthorized = pos_mobile_smoke_invoke($controller, 'authorize_mobile', [true]);
pos_mobile_smoke_expect(
    !$invalidTokenAuthorized && $output->status === 401,
    'invalid mobile token returns 401'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    [],
    true,
    7,
    [],
    [
        'Authorization' => 'Bearer batch-4a-invalid-token',
        'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
    ],
    $tokenRows,
    'POST',
    $activeTerminalRows
);
$controller->order_save();
pos_mobile_smoke_expect(
    $output->status === 401 && $model->writerCalls === 0,
    'invalid bearer does not fall through to a valid session or protected work'
);

$testApiKey = 'batch-4a-api-key';
putenv('POS_MOBILE_API_KEY=' . $testApiKey);
[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    [],
    true,
    7,
    [],
    [
        'Authorization' => 'Bearer batch-4a-invalid-token',
        'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
        'X-Pos-Mobile-Key' => $testApiKey,
    ],
    $tokenRows,
    'POST',
    $activeTerminalRows
);
$controller->order_save();
pos_mobile_smoke_expect(
    $output->status === 401 && $model->writerCalls === 0,
    'invalid bearer does not fall through to a valid API key or protected work'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    [],
    true,
    0,
    [],
    ['X-Pos-Mobile-Key' => $testApiKey]
);
$controller->order_save();
pos_mobile_smoke_expect(
    $output->status === 401 && $model->writerCalls === 0,
    'protected API-key path without an authenticated user returns 401'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    [],
    true,
    0,
    [],
    ['X-Pos-Mobile-Key' => $testApiKey]
);
$controller->bootstrap();
pos_mobile_smoke_expect(
    $output->status === 401 && $model->businessCalls === 0,
    'API-key-only bootstrap does not expose business data without an authenticated user'
);

[$controller, $auth, $output, $model] = pos_mobile_smoke_controller(
    [],
    true,
    7,
    [],
    ['X-Pos-Mobile-Key' => 'batch-4a-invalid-api-key']
);
$controller->order_save();
pos_mobile_smoke_expect(
    $output->status === 401 && $model->writerCalls === 0,
    'invalid API key returns 401'
);
putenv('POS_MOBILE_API_KEY=');

foreach ([
    'order_save' => ['$this->Pos_model->save_order_draft'],
    'order_confirm' => ['$this->Pos_model->save_order_draft'],
    'orders_push' => ['$this->db->table_exists(\'pos_mobile_sync_event\')', '->from(\'pos_mobile_sync_event\')', '->insert(\'pos_mobile_sync_event\'', '$this->Pos_model->save_order_draft'],
    'payment_save' => ['->from(\'pos_mobile_sync_event\')', '->insert(\'pos_mobile_sync_event\'', '$this->Pos_model->save_cashier_payment'],
    'order_void_save' => ['$this->Pos_model->save_order_void'],
    'order_refund_save' => ['$this->Pos_model->save_order_refund'],
    'cashier_open' => ['$this->Pos_model->daily_recon_gate_status', '$this->Pos_model->open_cashier_session'],
    'cashier_close' => ['$this->Pos_model->daily_recon_gate_status', '$this->Pos_model->close_cashier_session'],
] as $method => $tokens) {
    pos_mobile_smoke_assert_permission_precedes($controllerSource, $method, $tokens);
}

$workspaceViewGuard = '$this->mobile_permission($this->mobile_order_workspace_page_code(\'view\'), \'view\')';
$printerViewGuard = '$this->mobile_printer_permission(\'view\')';
foreach ([
    'bootstrap' => [
        '$this->Pos_model->cashier_bootstrap_options',
        '$this->Pos_model->order_product_catalog',
        '$this->Pos_model->order_bundle_catalog',
        '$this->Pos_model->active_cashier_sessions',
        '$this->Pos_model->order_draft_filter_options',
        '$this->Pos_model->cashier_catalog_filter_options',
        '$this->Pos_model->deposit_payment_method_options',
    ],
    'catalog' => [
        '$this->Pos_model->find_active_cashier_session',
        '$this->Pos_model->cashier_bootstrap_options',
        '$this->Pos_model->order_bundle_catalog',
        '$this->Pos_model->order_product_catalog',
    ],
    'member_search' => ['$this->Pos_model->order_member_search'],
    'extra_options' => ['$this->Pos_model->order_extra_options'],
    'orders' => [
        '$this->Pos_model->find_active_cashier_session',
        '$this->Pos_model->order_draft_rows',
    ],
    'order_load' => ['$this->Pos_model->find_order_draft'],
    'order_reversal_preview' => ['$this->Pos_model->order_reversal_preview'],
    'order_void_print_targets' => ['$this->Pos_model->direct_print_targets_for_void'],
    'order_refund_print_targets' => ['$this->Pos_model->direct_print_targets_for_refund'],
    'order_reprint_targets' => ['$this->Pos_model->direct_print_targets_for_order_reprint'],
    'order_confirm_print_targets' => ['$this->Pos_model->direct_print_targets_for_order_confirm'],
    'payment_prepare' => ['$this->Pos_model->cashier_payment_prepare'],
    'voucher_search' => ['$this->Pos_model->search_cashier_vouchers'],
    'payment_print_targets' => ['$this->Pos_model->direct_print_targets_for_payment'],
    'session_status' => [
        '$this->Pos_model->find_active_cashier_session',
        '$this->Pos_model->active_cashier_sessions',
    ],
    'cashier_close_preview' => ['$this->Pos_model->cashier_close_preview'],
] as $method => $tokens) {
    pos_mobile_smoke_assert_permission_precedes($controllerSource, $method, $tokens, $workspaceViewGuard);
}
pos_mobile_smoke_assert_permission_precedes(
    $controllerSource,
    'printers',
    ['$this->Pos_print_model->ready', '$this->Pos_print_model->connection_rows', '$this->Pos_print_model->route_rows'],
    $printerViewGuard
);
pos_mobile_smoke_assert_permission_precedes(
    $controllerSource,
    'printer_test',
    ['$this->Pos_print_model->ready', '$this->Pos_print_model->create_attempt'],
    '$this->mobile_printer_permission(\'test\')'
);

foreach ([
    ['order_save', ['id' => 0]],
    ['order_save', ['id' => 9]],
    ['orders_push', ['id' => 0]],
    ['orders_push', ['id' => 9]],
    ['orders_push', ['id' => 0, 'confirm_order' => true]],
    ['order_confirm', []],
    ['payment_save', ['client_event_id' => 'evt-1']],
    ['order_void_save', []],
    ['order_refund_save', []],
    ['cashier_open', []],
    ['cashier_close', []],
    ['printer_test', []],
] as [$method, $payload]) {
    [$output, $model, $printModel, $db] = pos_mobile_smoke_real_denied($method, $payload);
    pos_mobile_smoke_expect($output->status === 403, $method . ' returns 403 for a denied principal');
    pos_mobile_smoke_expect(
        $model->writerCalls === 0
            && $model->businessCalls === 0
            && $db->syncReads === 0
            && $db->syncInserts === 0
            && $printModel->readyCalls === 0
            && $printModel->attemptCalls === 0,
        $method . ' reaches no writer, sync, business, or printer work when denied'
    );
}

foreach ([
    ['bootstrap', []],
    ['catalog', []],
    ['member_search', []],
    ['extra_options', []],
    ['printers', []],
    ['orders', []],
    ['order_load', []],
    ['order_reversal_preview', []],
    ['order_void_print_targets', []],
    ['order_refund_print_targets', []],
    ['order_reprint_targets', []],
    ['order_confirm_print_targets', []],
    ['payment_prepare', []],
    ['voucher_search', []],
    ['payment_print_targets', []],
    ['session_status', []],
    ['cashier_close_preview', []],
] as [$method, $payload]) {
    [$output, $model, $printModel, $db] = pos_mobile_smoke_real_denied($method, $payload);
    $error = pos_mobile_smoke_json($output);
    $expectedPage = $method === 'printers' ? 'pos.printer.connection' : 'pos.order.draft.index';
    pos_mobile_smoke_expect($output->status === 403, $method . ' returns 403 for a denied read principal');
    pos_mobile_smoke_expect(
        ($error['page_code'] ?? '') === $expectedPage && ($error['action'] ?? '') === 'view',
        $method . ' denies with the expected view page/action'
    );
    pos_mobile_smoke_expect(
        $model->writerCalls === 0
            && $model->businessCalls === 0
            && $db->syncReads === 0
            && $db->syncInserts === 0
            && $printModel->readyCalls === 0
            && $printModel->attemptCalls === 0,
        $method . ' reaches no protected query, model, writer, sync, or printer work when denied'
    );
}

[$controller, $auth, $output] = pos_mobile_smoke_controller([
    'pos.cashier.index' => ['can_edit' => 1],
]);
$granted = pos_mobile_smoke_invoke($controller, 'mobile_permission', ['pos.cashier.index', 'edit']);
$grantedAgain = pos_mobile_smoke_invoke($controller, 'mobile_permission', ['pos.cashier.index', 'edit']);
pos_mobile_smoke_expect($granted && $grantedAgain, 'entitled user passes');
pos_mobile_smoke_expect($auth->loadCalls === 1, 'permission lookup is cached per request');

[$controller, $auth, $output] = pos_mobile_smoke_controller([]);
$denied = pos_mobile_smoke_invoke($controller, 'mobile_permission', ['pos.cashier.index', 'edit']);
$error = pos_mobile_smoke_json($output);
pos_mobile_smoke_expect(!$denied && $output->status === 403, 'denied user receives JSON 403');
pos_mobile_smoke_expect(($error['page_code'] ?? '') === 'pos.cashier.index' && ($error['action'] ?? '') === 'edit', 'denial identifies page and action');

[$controller, $auth, $output] = pos_mobile_smoke_controller(['__superadmin__' => true]);
pos_mobile_smoke_expect(
    pos_mobile_smoke_invoke($controller, 'mobile_permission', ['anything', 'delete']),
    'superadmin bypasses page action checks'
);

[$controller, $auth, $output] = pos_mobile_smoke_controller([], true, 7);
$controller->input->raw_input_stream = json_encode(['user_id' => 999]);
$payloadPermission = pos_mobile_smoke_invoke($controller, 'mobile_permission', ['pos.cashier.index', 'edit']);
pos_mobile_smoke_expect(
    !$payloadPermission
        && pos_mobile_smoke_invoke($controller, 'current_actor_user_id') === 7
        && $auth->lastUserId === 7,
    'request payload cannot supply permission user id'
);

$createPolicy = new PosMobileSmokePolicy([
    'pos.cashier.index' => ['can_create' => 1],
]);
$saveCreate = $createPolicy->dispatch('order_save', ['id' => 0]);
$pushCreate = $createPolicy->dispatch('orders_push', ['id' => 0]);
pos_mobile_smoke_expect(($saveCreate['status'] ?? 0) === 200 && ($saveCreate['action'] ?? '') === 'create', 'order_save id=0 requires create');
pos_mobile_smoke_expect(($pushCreate['status'] ?? 0) === 200 && ($pushCreate['action'] ?? '') === 'create', 'orders_push new order requires create');

$editPolicy = new PosMobileSmokePolicy([
    'pos.cashier.index' => ['can_edit' => 1],
]);
foreach ([
    ['order_save', ['id' => 9]],
    ['orders_push', ['id' => 9]],
    ['orders_push', ['id' => 0, 'confirm_order' => true]],
    ['order_confirm', []],
    ['payment_save', []],
    ['order_void_save', []],
    ['order_refund_save', []],
    ['cashier_open', []],
    ['cashier_close', []],
] as [$route, $payload]) {
    $result = $editPolicy->dispatch($route, $payload);
    pos_mobile_smoke_expect(($result['status'] ?? 0) === 200, $route . ' requires edit and passes for entitled user');
}

$deniedPolicy = new PosMobileSmokePolicy([]);
foreach ([
    ['order_save', ['id' => 0]],
    ['order_save', ['id' => 9]],
    ['orders_push', ['id' => 0]],
    ['orders_push', ['id' => 9]],
    ['orders_push', ['id' => 0, 'confirm_order' => true]],
    ['order_confirm', []],
    ['payment_save', []],
    ['order_void_save', []],
    ['order_refund_save', []],
    ['cashier_open', []],
    ['cashier_close', []],
    ['printer_test', []],
] as [$route, $payload]) {
    $result = $deniedPolicy->dispatch($route, $payload);
    pos_mobile_smoke_expect(($result['status'] ?? 0) === 403, $route . ' denies before protected work');
    pos_mobile_smoke_expect(
        $deniedPolicy->work === [
            'writer' => 0,
            'sync_read' => 0,
            'sync_insert' => 0,
            'business' => 0,
            'printer_attempt' => 0,
        ],
        $route . ' performs no writer, sync, business, or printer work when denied'
    );
    pos_mobile_smoke_expect($deniedPolicy->events[0] === 'authenticate', $route . ' authenticates before authorization');
}

$readPolicy = new PosMobileSmokePolicy([
    'pos.cashier.index' => ['can_view' => 1],
]);
foreach ([
    'bootstrap',
    'catalog',
    'member_search',
    'extra_options',
    'orders',
    'order_load',
    'order_reversal_preview',
    'order_void_print_targets',
    'order_refund_print_targets',
    'order_reprint_targets',
    'order_confirm_print_targets',
    'payment_prepare',
    'voucher_search',
    'payment_print_targets',
    'session_status',
    'cashier_close_preview',
] as $route) {
    $result = $readPolicy->dispatch($route);
    pos_mobile_smoke_expect(
        ($result['status'] ?? 0) === 200
            && ($result['page_code'] ?? '') === 'pos.cashier.index'
            && ($result['action'] ?? '') === 'view',
        $route . ' uses the cashier view page/action when entitled'
    );
    pos_mobile_smoke_expect(
        $readPolicy->work['business'] === 1
            && $readPolicy->work['writer'] === 0
            && $readPolicy->work['sync_read'] === 0
            && $readPolicy->work['sync_insert'] === 0
            && $readPolicy->work['printer_attempt'] === 0,
        $route . ' performs protected business work only after view authorization'
    );
}

$printerViewRegistry = new PosMobileSmokePolicy(['pos.printer.connection' => ['can_view' => 1]], true);
$printerViewFallback = new PosMobileSmokePolicy(['pos.printer.index' => ['can_view' => 1]], false);
$printerViewNoFallback = new PosMobileSmokePolicy(['pos.printer.index' => ['can_view' => 1]], true);
foreach ([
    [$printerViewRegistry, 'pos.printer.connection'],
    [$printerViewFallback, 'pos.printer.index'],
] as [$policy, $expectedPage]) {
    $result = $policy->dispatch('printers');
    pos_mobile_smoke_expect(
        ($result['status'] ?? 0) === 200
            && ($result['page_code'] ?? '') === $expectedPage
            && ($result['action'] ?? '') === 'view',
        'printers uses exact registry/fallback view page and action'
    );
}
pos_mobile_smoke_expect(
    ($printerViewNoFallback->dispatch('printers')['status'] ?? 0) === 403,
    'printers does not fall back from registry page while it exists'
);

foreach ([
    [['pos.order.paid.index' => ['can_edit' => 1]], 'pos.order.paid.index'],
    [['pos.cashier.index' => ['can_edit' => 1]], 'pos.cashier.index'],
    [['pos.order.draft.index' => ['can_edit' => 1]], 'pos.order.draft.index'],
] as [$permissions, $expectedPage]) {
    [$controller, $auth, $output] = pos_mobile_smoke_controller($permissions);
    $selectedPage = pos_mobile_smoke_invoke(
        $controller,
        'mobile_order_workspace_page_code',
        ['edit', 'pos.order.paid.index']
    );
    pos_mobile_smoke_expect(
        $selectedPage === $expectedPage,
        'real workspace helper selects ' . $expectedPage . ' in precedence order'
    );
}

foreach ([
    [['pos.cashier.index' => ['can_view' => 1]], 'pos.cashier.index'],
    [['pos.order.draft.index' => ['can_view' => 1]], 'pos.order.draft.index'],
] as [$permissions, $expectedPage]) {
    [$controller, $auth, $output] = pos_mobile_smoke_controller($permissions);
    $selectedPage = pos_mobile_smoke_invoke($controller, 'mobile_order_workspace_page_code', ['view']);
    pos_mobile_smoke_expect(
        $selectedPage === $expectedPage,
        'real workspace view helper preserves cashier to draft fallback'
    );
}

$printerRegistry = new PosMobileSmokePolicy(['pos.printer.connection' => ['can_edit' => 1]], true);
$printerFallback = new PosMobileSmokePolicy(['pos.printer.index' => ['can_edit' => 1]], false);
$printerNoFallback = new PosMobileSmokePolicy(['pos.printer.index' => ['can_edit' => 1]], true);
pos_mobile_smoke_expect(($printerRegistry->dispatch('printer_test')['page_code'] ?? '') === 'pos.printer.connection', 'printer uses registry page when present');
pos_mobile_smoke_expect(($printerFallback->dispatch('printer_test')['page_code'] ?? '') === 'pos.printer.index', 'printer falls back when registry page is absent');
pos_mobile_smoke_expect(($printerNoFallback->dispatch('printer_test')['status'] ?? 0) === 403, 'printer does not fall back while registry page exists');

$sourcePrinterPage = pos_mobile_smoke_invoke(pos_mobile_smoke_controller([], true)[0], 'mobile_printer_permission_page');
$sourcePrinterFallbackPage = pos_mobile_smoke_invoke(pos_mobile_smoke_controller([], false)[0], 'mobile_printer_permission_page');
pos_mobile_smoke_expect($sourcePrinterPage === 'pos.printer.connection', 'controller printer page helper matches registry');
pos_mobile_smoke_expect($sourcePrinterFallbackPage === 'pos.printer.index', 'controller printer page helper matches fallback');

echo 'All POS mobile authorization smoke checks passed.' . PHP_EOL;
