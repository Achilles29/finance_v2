<?php
// Lightweight DB-free source/runtime smoke for Purchase maintenance CSRF.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

final class PurchaseMaintenanceDenied extends RuntimeException
{
}
class MY_Controller
{
    public $Purchase_model;
    public $db;
    public $input;
    public $output;
    public $session;
    protected array $current_user = [];
    private array $smokePermissions = [];

    public function configureSmoke(array $permissions, int $userId = 701): void
    {
        $this->smokePermissions = $permissions;
        $this->current_user = ['id' => $userId];
    }

    protected function can(string $page, string $action = 'view'): bool
    {
        return !empty($this->smokePermissions[$page]['can_' . $action]);
    }

    protected function require_permission(string $page, string $action = 'view'): void
    {
        if (!$this->can($page, $action)) {
            throw new PurchaseMaintenanceDenied($page . ':' . $action);
        }
    }

    protected function render(string $view, array $data = []): void
    {
    }
}
final class PurchaseMaintenanceInput
{
    public int $methodCalls = 0;
    public int $headerCalls = 0;
    public int $payloadReads = 0;
    public int $ipCalls = 0;
    private string $raw;

    public function __construct(private string $method, private array $headers = [], array $payload = [])
    {
        $this->raw = json_encode($payload);
    }

    public function method(bool $upper = false): string
    {
        $this->methodCalls++;
        return $upper ? strtoupper($this->method) : strtolower($this->method);
    }

    public function get_request_header(string $name, bool $clean = false): string
    {
        $this->headerCalls++;
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) {
                return (string)$value;
            }
        }
        return '';
    }

    public function __get(string $name)
    {
        if ($name === 'raw_input_stream') {
            $this->payloadReads++;
            return $this->raw;
        }
        return null;
    }

    public function post($key = null, bool $clean = false): array
    {
        $this->payloadReads++;
        return [];
    }

    public function ip_address(): string
    {
        $this->ipCalls++;
        return '127.0.0.81';
    }
}
final class PurchaseMaintenanceOutput
{
    public int $status = 200;
    public array $headers = [];
    public string $body = '';

    public function set_status_header(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function set_header(string $header): self
    {
        $this->headers[] = $header;
        return $this;
    }

    public function set_content_type(string $type): self
    {
        return $this;
    }

    public function set_output(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
final class PurchaseMaintenanceSession
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

    public function set_userdata(string $key, $value): void
    {
        $this->writes[$key] = $value;
        $this->values[$key] = $value;
    }

    public function value(string $key)
    {
        return $this->values[$key] ?? null;
    }
}
final class PurchaseMaintenanceDb
{
    public bool $db_debug = true;
}
final class PurchaseMaintenanceModel
{
    public array $calls = [];
    public bool $throw = false;

    public function __construct(private PurchaseMaintenanceDb $db)
    {
    }

    public function rebuild_purchase_impacts(array $payload, int $actor, string $ip): array
    {
        return $this->record(__FUNCTION__, $payload, $actor, $ip);
    }

    public function reclassify_item_material_by_profile_key(array $payload, int $actor, string $ip): array
    {
        return $this->record(__FUNCTION__, $payload, $actor, $ip);
    }

    private function record(string $method, array $payload, int $actor, string $ip): array
    {
        $this->calls[] = [$method, $payload, $actor, $ip, $this->db->db_debug];
        if ($this->throw) {
            throw new RuntimeException('model tripwire');
        }
        return ['ok' => true, 'message' => 'ok', 'data' => ['dry_run' => !empty($payload['dry_run'])]];
    }
}
require dirname(__DIR__, 2) . '/application/controllers/Purchase.php';
$checks = 0;
$failures = [];
function pm_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    } else {
        echo 'PASS: ' . $message . PHP_EOL;
    }
}
function pm_method(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    preg_match('/\n    (?:public|private|protected) function /', $source, $match, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($match[0][1]) ? (int)$match[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}
function pm_controller(string $method, array $headers, array $session, array $payload, array $permissions): array
{
    $controller = (new ReflectionClass(Purchase::class))->newInstanceWithoutConstructor();
    $controller->configureSmoke($permissions);
    $controller->input = new PurchaseMaintenanceInput($method, $headers, $payload);
    $controller->output = new PurchaseMaintenanceOutput();
    $controller->session = new PurchaseMaintenanceSession($session);
    $controller->db = new PurchaseMaintenanceDb();
    $controller->Purchase_model = new PurchaseMaintenanceModel($controller->db);
    return [$controller, $controller->input, $controller->output, $controller->session, $controller->db, $controller->Purchase_model];
}
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Purchase.php');
$helper = pm_method($source, 'require_purchase_maintenance_mutation_request');
pm_check(
    strpos($source, "PURCHASE_MAINTENANCE_CSRF_SESSION_KEY = 'purchase_maintenance_csrf'") !== false
        && strpos($helper, "get_request_header('X-Purchase-Maintenance-Csrf', true)") !== false
        && strpos($helper, 'hash_equals($expected, $provided)') !== false
        && strpos($helper, 'POS_TRANSACTION_CSRF') === false,
    'maintenance helper uses its dedicated session/header token and hash_equals without POS token reuse'
);
foreach (['rebuild_impact_run', 'reclassify_profile_domain_run'] as $action) {
    $block = pm_method($source, $action);
    $rbac = strpos($block, 'require_permission(');
    $guard = strpos($block, 'require_purchase_maintenance_mutation_request()');
    $payload = strpos($block, 'requestPayload()');
    $model = strpos($block, '$this->Purchase_model->');
    pm_check($rbac < $guard && $guard < $payload && $payload < $model, $action . ' orders edit RBAC, POST/CSRF, payload, then model');
}

foreach (['rebuild_impact_index', 'reclassify_profile_domain_index'] as $index) {
    $block = pm_method($source, $index);
    pm_check(
        strpos($block, 'require_permission(') < strpos($block, 'purchase_maintenance_csrf()')
            && strpos($block, "'purchase_maintenance_csrf_token'") !== false,
        $index . ' passes the dedicated token only after view permission'
    );
}
foreach (['rebuild_impact_index.php', 'reclassify_profile_domain_index.php'] as $viewFile) {
    $view = file_get_contents($root . '/application/views/purchase/' . $viewFile);
    pm_check(
        substr_count($view, "'X-Purchase-Maintenance-CSRF': maintenanceCsrfToken") === 1,
        $viewFile . ' sends the maintenance token in the fetch header'
    );
}
$token = str_repeat('a', 64);
$editPermissions = [
    Purchase::PAGE_REBUILD_IMPACT => ['can_edit' => 1],
    Purchase::PAGE_RECLASSIFY_PROFILE_DOMAIN => ['can_edit' => 1],
];
foreach (['rebuild_impact_run', 'reclassify_profile_domain_run'] as $action) {
    [$controller, $input, $output, $session, $db, $model] = pm_controller('POST', [
        'X-Purchase-Maintenance-CSRF' => $token,
    ], ['purchase_maintenance_csrf' => $token], ['dry_run' => true], [
        $action === 'rebuild_impact_run' ? Purchase::PAGE_REBUILD_IMPACT : Purchase::PAGE_RECLASSIFY_PROFILE_DOMAIN => ['can_view' => 1],
    ]);
    try {
        $controller->{$action}();
        pm_check(false, $action . ' view-only principal must be denied');
    } catch (PurchaseMaintenanceDenied $exception) {
        pm_check($input->methodCalls === 0 && $input->payloadReads === 0 && $model->calls === [], $action . ' denies view-only before guard/payload/model');
    }
}
foreach (['GET', 'PUT', 'PATCH'] as $method) {
    [$controller, $input, $output, $session, $db, $model] = pm_controller($method, [], ['purchase_maintenance_csrf' => $token], ['dry_run' => true], $editPermissions);
    $reflection = new ReflectionMethod(Purchase::class, 'require_purchase_maintenance_mutation_request');
    $reflection->setAccessible(true);
    pm_check($reflection->invoke($controller) === false && $output->status === 405 && in_array('Allow: POST', $output->headers, true), $method . ' receives JSON 405 with Allow POST');
    pm_check($input->headerCalls === 0 && $input->payloadReads === 0 && $model->calls === [], $method . ' rejection reaches no token/payload/model downstream');
}
$genericBodies = [];
foreach ([
    'missing' => '',
    'malformed' => 'not-64-hex',
    'wrong' => str_repeat('b', 64),
] as $case => $provided) {
    $headers = $provided === '' ? [] : ['X-Purchase-Maintenance-CSRF' => $provided];
    [$controller, $input, $output, $session, $db, $model] = pm_controller('POST', $headers, [
        'purchase_maintenance_csrf' => $token,
        'pos_transaction_csrf' => $provided !== '' ? $provided : $token,
    ], ['dry_run' => true, 'purchase_maintenance_csrf' => $token], $editPermissions);
    $controller->rebuild_impact_run();
    $genericBodies[] = $output->body;
    pm_check($output->status === 403 && $input->payloadReads === 0 && $model->calls === [] && $db->db_debug === true, $case . ' token receives 403 before payload/model/db_debug');
}
pm_check(count(array_unique($genericBodies)) === 1, 'missing, malformed, and wrong tokens share one generic rejection');
[$controller, $input, $output, $session, $db, $model] = pm_controller('POST', [
    'X-Purchase-Maintenance-CSRF' => $token,
], ['purchase_maintenance_csrf' => $token, 'pos_transaction_csrf' => str_repeat('c', 64)], ['dry_run' => true], $editPermissions);
$controller->rebuild_impact_run();
pm_check(
    $output->status === 200
        && count($model->calls) === 1
        && $model->calls[0][1] === ['dry_run' => true]
        && $model->calls[0][2] === 701
        && $model->calls[0][3] === '127.0.0.81'
        && $model->calls[0][4] === false
        && $db->db_debug === true,
    'valid rebuild dry-run preserves payload/actor/IP and restores db_debug'
);
[$controller, $input, $output, $session, $db, $model] = pm_controller('POST', [
    'X-Purchase-Maintenance-CSRF' => $token,
], ['purchase_maintenance_csrf' => $token], ['dry_run' => false], $editPermissions);
$controller->reclassify_profile_domain_run();
pm_check(
    $output->status === 200
        && $model->calls[0][1] === ['dry_run' => false]
        && $model->calls[0][4] === false
        && $db->db_debug === true,
    'valid reclassify apply preserves behavior and restores db_debug'
);
[$controller, $input, $output, $session, $db, $model] = pm_controller('POST', [
    'X-Purchase-Maintenance-CSRF' => $token,
], ['purchase_maintenance_csrf' => $token], ['dry_run' => false], $editPermissions);
$model->throw = true;
try {
    $controller->rebuild_impact_run();
    pm_check(false, 'model exception must propagate after finally');
} catch (RuntimeException $exception) {
    pm_check($db->db_debug === true && count($model->calls) === 1, 'db_debug is restored when maintenance model throws');
}
[$controller, $input, $output, $session] = pm_controller('GET', [], [
    'pos_transaction_csrf' => $token,
], [], $editPermissions);
$generator = new ReflectionMethod(Purchase::class, 'purchase_maintenance_csrf');
$generator->setAccessible(true);
$generated = $generator->invoke($controller);
pm_check(
    preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1
        && $session->value('purchase_maintenance_csrf') === $generated
        && $generated !== $token,
    'maintenance generator creates and stores an independent random 64-hex token'
);
if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' purchase maintenance CSRF smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' purchase maintenance CSRF smoke checks passed.' . PHP_EOL;
