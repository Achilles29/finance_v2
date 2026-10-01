<?php

declare(strict_types=1);

/**
 * Behavioral, DB-free smoke test for Inventory_control mutation CSRF.
 *
 * The controller is loaded with a deliberately small MY_Controller stub.
 * Invalid requests must stop at the scoped CSRF guard; dependency reads are
 * made fatal by the stub so a query/service/transaction/writer cannot hide in
 * the path before rejection.
 */

define('BASEPATH', __DIR__);

class InventoryControlMutationSmokeInput
{
    /* Only fields present in the supplied payload count as payload reads. */
    public array $postReads = [];
    public array $calls = [];

    private string $requestMethod;
    private array $postData;

    public function __construct(string $requestMethod, array $postData = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
    }

    public function method($upper = false): string
    {
        $this->calls[] = 'method';
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key, $xssClean = false)
    {
        $key = (string)$key;
        if (!array_key_exists($key, $this->postData)) {
            return null;
        }

        $this->postReads[] = $key;
        return $this->postData[$key];
    }

    public function is_ajax_request(): bool
    {
        $this->calls[] = 'is_ajax_request';
        return false;
    }
}

class InventoryControlMutationSmokeSession
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

    public function has_key($key): bool
    {
        return array_key_exists((string)$key, $this->values);
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->writes[] = $key;
        $this->values[$key] = $value;
    }
}

class MY_Controller
{
    public InventoryControlMutationSmokeInput $input;
    public InventoryControlMutationSmokeSession $session;

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
    }

    public function is_superadmin(): bool
    {
        return true;
    }

    public function __get($name)
    {
        global $inventoryControlSmokeForbiddenAccesses;
        $inventoryControlSmokeForbiddenAccesses[] = (string)$name;
        throw new RuntimeException('unexpected dependency access');
    }
}

$inventoryControlSmokeResponseStatus = null;
$inventoryControlSmokeForbiddenAccesses = [];

function show_error($message, $statusCode = 500, $heading = ''): void
{
    global $inventoryControlSmokeResponseStatus;
    $inventoryControlSmokeResponseStatus = (int)$statusCode;
}

require dirname(__DIR__, 2) . '/application/controllers/Inventory_control.php';

$checks = 0;
$failures = [];

function inventory_control_mutation_csrf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function inventory_control_mutation_csrf_method_block(string $source, string $method): string
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
    $end = ($next === 1) ? (int)$matches[0][1] : strlen($source);

    return substr($source, $start, $end - $start);
}

function inventory_control_mutation_csrf_hex64(string $value): bool
{
    return preg_match('/\A[0-9a-fA-F]{64}\z/D', $value) === 1;
}

function inventory_control_mutation_csrf_controller(
    string $requestMethod,
    array $postData,
    string $sessionToken,
    bool $sessionTokenPresent = true
): array {
    global $inventoryControlSmokeForbiddenAccesses, $inventoryControlSmokeResponseStatus;
    $inventoryControlSmokeForbiddenAccesses = [];
    $inventoryControlSmokeResponseStatus = null;

    $input = new InventoryControlMutationSmokeInput($requestMethod, $postData);
    $sessionValues = [
        'unrelated_session_value' => 'not-read',
    ];
    if ($sessionTokenPresent) {
        $sessionValues['inventory_control_mutation_csrf'] = $sessionToken;
    }
    $session = new InventoryControlMutationSmokeSession($sessionValues);
    $reflection = new ReflectionClass(Inventory_control::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;

    return [$controller, $input, $session, $reflection];
}

function inventory_control_mutation_csrf_invoke_action(
    string $action,
    string $requestMethod,
    array $postData,
    ?string $sessionToken = null,
    bool $sessionTokenPresent = true
): array {
    $sessionToken ??= str_repeat('a', 64);
    [$controller, $input, $session, $reflection] = inventory_control_mutation_csrf_controller(
        $requestMethod,
        $postData,
        $sessionToken,
        $sessionTokenPresent
    );

    if (!$sessionTokenPresent) {
        inventory_control_mutation_csrf_check(
            !$session->has_key('inventory_control_mutation_csrf'),
            $action . ' absent session CSRF field is unavailable before action'
        );
    }

    try {
        $method = $reflection->getMethod($action);
        $arguments = in_array($action, ['deficit_write_off', 'period_cutoff_post', 'period_reopen'], true)
            ? [17]
            : [];
        $method->invokeArgs($controller, $arguments);
    } catch (Throwable $exception) {
        inventory_control_mutation_csrf_check(false, $action . ' invalid request completed without an exception');
    }

    global $inventoryControlSmokeResponseStatus, $inventoryControlSmokeForbiddenAccesses;
    return [
        $inventoryControlSmokeResponseStatus,
        $input,
        $session,
        $inventoryControlSmokeForbiddenAccesses,
    ];
}

function inventory_control_mutation_csrf_assert_rejected_action(
    string $action,
    string $case,
    array $postData,
    array $expectedPostReads,
    string $sessionToken,
    bool $sessionTokenPresent = true
): void {
    [$status, $input, $session, $forbidden] = inventory_control_mutation_csrf_invoke_action(
        $action,
        'POST',
        $postData,
        $sessionToken,
        $sessionTokenPresent
    );
    inventory_control_mutation_csrf_check($status === 403, $action . ' ' . $case . ' is rejected with HTTP 403');
    inventory_control_mutation_csrf_check(
        $input->postReads === $expectedPostReads,
        $action . ' ' . $case . ' reads only the scoped payload field when present'
    );
    inventory_control_mutation_csrf_check(
        $session->reads === ['inventory_control_mutation_csrf'],
        $action . ' ' . $case . ' reads only the scoped session token'
    );
    inventory_control_mutation_csrf_check(
        $forbidden === [],
        $action . ' ' . $case . ' reaches no dependency before rejection'
    );
}

function inventory_control_mutation_csrf_invoke_guard(string $sessionToken, string $providedToken): array
{
    [$controller, $input, $session, $reflection] = inventory_control_mutation_csrf_controller(
        'POST',
        ['inventory_control_mutation_csrf' => $providedToken],
        $sessionToken
    );
    $guard = $reflection->getMethod('require_inventory_control_mutation_csrf');
    $guard->setAccessible(true);

    try {
        $result = $guard->invoke($controller);
    } catch (Throwable $exception) {
        $result = null;
        inventory_control_mutation_csrf_check(false, 'private CSRF guard completed without an exception');
    }

    global $inventoryControlSmokeResponseStatus, $inventoryControlSmokeForbiddenAccesses;
    return [
        $result,
        $inventoryControlSmokeResponseStatus,
        $input,
        $session,
        $inventoryControlSmokeForbiddenAccesses,
    ];
}

function site_url($uri = ''): string
{
    return '/smoke/' . ltrim((string)$uri, '/');
}

function html_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inventory_control_mutation_csrf_render_view(string $path, array $variables): ?string
{
    $baseLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (): bool {
        throw new RuntimeException('view rendering warning');
    });

    try {
        extract($variables, EXTR_SKIP);
        include $path;
        $html = ob_get_clean();
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

function inventory_control_mutation_csrf_check_rendered_forms(
    string $label,
    ?string $html,
    int $expectedGetForms,
    int $expectedPostForms,
    string $fieldName
): void {
    inventory_control_mutation_csrf_check($html !== null, $label . ' renders without a warning or exception');
    if ($html === null || !class_exists('DOMDocument')) {
        if ($html !== null && !class_exists('DOMDocument')) {
            inventory_control_mutation_csrf_check(true, $label . ' DOMDocument check skipped because DOMDocument is unavailable');
        }
        return;
    }

    $previousUseErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previousUseErrors);
    inventory_control_mutation_csrf_check($loaded, $label . ' parses as HTML');
    if (!$loaded) {
        return;
    }

    $getForms = 0;
    $postForms = 0;
    foreach ($dom->getElementsByTagName('form') as $form) {
        $method = strtolower(trim($form->getAttribute('method')) ?: 'get');
        $scopedInputs = [];
        foreach ($form->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('name') === $fieldName) {
                $scopedInputs[] = $input;
            }
        }

        if ($method === 'post') {
            $postForms++;
            $hasNonEmptyHiddenField = false;
            foreach ($scopedInputs as $input) {
                if (
                    strtolower($input->getAttribute('type') ?: 'text') === 'hidden'
                    && trim($input->getAttribute('value')) !== ''
                ) {
                    $hasNonEmptyHiddenField = true;
                    break;
                }
            }
            inventory_control_mutation_csrf_check(
                $hasNonEmptyHiddenField,
                $label . ' POST form has a non-empty hidden scoped CSRF field'
            );
        } else {
            $getForms++;
            inventory_control_mutation_csrf_check(
                $scopedInputs === [],
                $label . ' GET form has no scoped mutation CSRF field'
            );
        }
    }

    inventory_control_mutation_csrf_check(
        $getForms === $expectedGetForms,
        $label . ' has expected GET form count'
    );
    inventory_control_mutation_csrf_check(
        $postForms === $expectedPostForms,
        $label . ' has expected POST form count'
    );
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Inventory_control.php';
$controller = @file_get_contents($controllerPath);
inventory_control_mutation_csrf_check($controller !== false, 'controller source is available');
if ($controller === false) {
    $controller = '';
}

/* Source contracts remain useful as a complement to the executable checks. */
$writerMethods = [
    'deficit_write_off' => true,
    'value_reconciliation_post' => true,
    'value_reconciliation_void' => true,
    'period_open' => false,
    'period_cutoff_post' => true,
    'period_reopen' => false,
];

inventory_control_mutation_csrf_check(
    strpos($controller, "private const INVENTORY_CONTROL_MUTATION_CSRF_NAME = 'inventory_control_mutation_csrf';") !== false,
    'controller defines the scoped inventory mutation field name'
);

foreach ($writerMethods as $method => $requiresSuperadmin) {
    $block = inventory_control_mutation_csrf_method_block($controller, $method);
    $permissionPosition = strpos($block, '$this->require_permission(');
    $superadminPosition = strpos($block, '$this->is_superadmin()');
    $guardPosition = strpos($block, '$this->require_inventory_control_mutation_csrf()');

    inventory_control_mutation_csrf_check($block !== '', $method . ' exists in the controller');
    inventory_control_mutation_csrf_check(
        $permissionPosition !== false && $guardPosition !== false && $permissionPosition < $guardPosition,
        $method . ' guards after RBAC'
    );

    if ($requiresSuperadmin) {
        inventory_control_mutation_csrf_check(
            $superadminPosition !== false && $superadminPosition < $guardPosition,
            $method . ' guards after its existing superadmin check'
        );
    }

    $beforeGuard = $guardPosition === false ? '' : substr($block, 0, $guardPosition);
    foreach ([
        '$this->input->post(' => 'payload parsing',
        '$this->Inventory_control_model->get_period(' => 'period query',
        '$this->load->library(' => 'service loading',
        '$this->db->trans_' => 'transaction start',
        '->write_off_deficit_group(' => 'deficit writer',
        '->ensureOpen(' => 'period writer',
        '->reopenPeriod(' => 'reopen writer',
        '->post(' => 'posting writer',
    ] as $needle => $label) {
        inventory_control_mutation_csrf_check(
            strpos($beforeGuard, $needle) === false,
            $method . ' has no ' . $label . ' before the CSRF guard'
        );
    }
}

$helperStart = strpos($controller, 'private function inventory_control_mutation_csrf(): array');
$guardStart = strpos($controller, 'private function require_inventory_control_mutation_csrf(): bool');
$rejectStart = strpos($controller, 'private function reject_inventory_control_mutation(');
$helperEnd = $guardStart === false ? strlen($controller) : $guardStart;
$guardEnd = $rejectStart === false ? strlen($controller) : $rejectStart;
$helper = $helperStart === false ? '' : substr($controller, $helperStart, $helperEnd - $helperStart);
$guard = $guardStart === false ? '' : substr($controller, $guardStart, $guardEnd - $guardStart);

inventory_control_mutation_csrf_check(
    strpos($helper, 'bin2hex(random_bytes(32))') !== false,
    'view helper creates 32 random bytes'
);
inventory_control_mutation_csrf_check(
    strpos($helper, 'preg_match(') !== false
        && strpos($helper, '{64}') !== false
        && strpos($helper, '$token') !== false,
    'view helper reuses only an exact 64-hex session token'
);
inventory_control_mutation_csrf_check(
    strpos($helper, 'session->userdata($name)') !== false
        && strpos($helper, 'session->set_userdata($name, $token)') !== false,
    'view helper stores and reuses the token in the session'
);
inventory_control_mutation_csrf_check(
    strpos($helper, "'name' => " . '$name') !== false
        && strpos($helper, "'value' => " . '$token') !== false,
    'view helper returns explicit token name and value'
);
inventory_control_mutation_csrf_check(
    strpos($guard, '$this->input->method(true) !== \'POST\'') !== false
        && strpos($guard, 'reject_inventory_control_mutation(405') !== false,
    'guard rejects non-POST requests with HTTP 405'
);
inventory_control_mutation_csrf_check(
    strpos($guard, '$this->input->post(self::INVENTORY_CONTROL_MUTATION_CSRF_NAME, true)') !== false
        && strpos($guard, 'session->userdata(self::INVENTORY_CONTROL_MUTATION_CSRF_NAME)') !== false
        && strpos($guard, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guard, 'reject_inventory_control_mutation(403') !== false,
    'guard rejects missing, malformed, or mismatched tokens with HTTP 403'
);
inventory_control_mutation_csrf_check(
    strpos($guard, 'return true;') !== false
        && strpos($guard, 'hash_equals($sessionToken, $providedToken)') < strpos($guard, 'return true;'),
    'valid session-bound token is accepted'
);
inventory_control_mutation_csrf_check(
    strpos($guard, 'log_message(') === false && strpos($helper, 'log_message(') === false,
    'CSRF token is never logged by helper or guard'
);

$reject = $rejectStart === false ? '' : substr($controller, $rejectStart);
inventory_control_mutation_csrf_check(
    strpos($reject, '$this->input->is_ajax_request()') !== false
        && strpos($reject, 'set_content_type(\'application/json\')') !== false
        && strpos($reject, 'set_status_header($statusCode)') !== false
        && strpos($reject, 'show_error(') !== false,
    'rejection response is safe for AJAX and HTML clients'
);

$viewContracts = [
    'inventory/stock_period_index.php' => 1,
    'inventory/stock_period_detail.php' => 2,
    'inventory/stock_deficit_detail.php' => 1,
    'inventory/stock_value_reconciliation_index.php' => 2,
];

foreach ($viewContracts as $relative => $expectedPostForms) {
    $path = $root . '/application/views/' . $relative;
    $view = @file_get_contents($path);
    inventory_control_mutation_csrf_check($view !== false, $relative . ' exists');
    if ($view === false) {
        continue;
    }

    $scopedField = 'name="<?php echo html_escape($mutationCsrfName); ?>"';
    inventory_control_mutation_csrf_check(
        substr_count($view, $scopedField) === $expectedPostForms,
        $relative . ' puts the scoped field in every mutation form'
    );
    inventory_control_mutation_csrf_check(
        strpos($view, '$hasMutationCsrf') !== false
            && strpos($view, 'mutationCsrfValue') !== false,
        $relative . ' requires a non-empty scoped token before rendering mutation forms'
    );
    inventory_control_mutation_csrf_check(
        strpos($view, 'get_csrf_token_name') === false
            && strpos($view, 'get_csrf_hash') === false,
        $relative . ' does not use the global CSRF placeholder'
    );
}

foreach ([
    'deficit_detail' => 'stock_deficit_detail',
    'periods' => 'stock_period_index',
    'value_reconciliation' => 'stock_value_reconciliation_index',
    'period_detail' => 'stock_period_detail',
] as $method => $viewName) {
    $methodBlock = inventory_control_mutation_csrf_method_block($controller, $method);
    $renderPosition = strpos($methodBlock, "render('inventory/" . $viewName . "'");
    $renderBlock = $renderPosition === false ? '' : substr($methodBlock, $renderPosition);
    inventory_control_mutation_csrf_check(
        strpos($renderBlock, "'inventory_control_mutation_csrf_name' =>") !== false
            && strpos($renderBlock, "'inventory_control_mutation_csrf_value' =>") !== false,
        $method . ' render receives explicit scoped token name and value'
    );
}

/* Behavioral action boundary: every invalid request is rejected before any dependency or business-payload read. */
$actions = array_keys($writerMethods);
$scopedCsrfField = 'inventory_control_mutation_csrf';
$defaultSessionToken = str_repeat('a', 64);
$malformedToken = 'malformed';
foreach ($actions as $action) {
    [$status, $input, $session, $forbidden] = inventory_control_mutation_csrf_invoke_action($action, 'GET', []);
    inventory_control_mutation_csrf_check($status === 405, $action . ' GET is rejected with HTTP 405');
    inventory_control_mutation_csrf_check($input->postReads === [], $action . ' GET reads no payload');
    inventory_control_mutation_csrf_check($session->reads === [], $action . ' GET reads no session token');
    inventory_control_mutation_csrf_check($forbidden === [], $action . ' GET reads no query/service/transaction/writer dependency');

    inventory_control_mutation_csrf_assert_rejected_action(
        $action,
        'POST with missing CSRF field',
        [],
        [],
        $defaultSessionToken
    );
    inventory_control_mutation_csrf_assert_rejected_action(
        $action,
        'POST with valid-looking token and absent session CSRF field',
        [$scopedCsrfField => $defaultSessionToken, 'unrelated_business_field' => 'not-read'],
        [$scopedCsrfField],
        $defaultSessionToken,
        false
    );
    inventory_control_mutation_csrf_assert_rejected_action(
        $action,
        'POST with empty CSRF field',
        [$scopedCsrfField => '', 'unrelated_business_field' => 'not-read'],
        [$scopedCsrfField],
        $defaultSessionToken
    );
    inventory_control_mutation_csrf_assert_rejected_action(
        $action,
        'POST with malformed token',
        [$scopedCsrfField => $malformedToken, 'unrelated_business_field' => 'not-read'],
        [$scopedCsrfField],
        $defaultSessionToken
    );
    inventory_control_mutation_csrf_assert_rejected_action(
        $action,
        'POST with token from another session',
        [$scopedCsrfField => str_repeat('c', 64), 'unrelated_business_field' => 'not-read'],
        [$scopedCsrfField],
        str_repeat('d', 64)
    );
    inventory_control_mutation_csrf_assert_rejected_action(
        $action,
        'POST with mismatched token',
        [$scopedCsrfField => str_repeat('e', 64), 'unrelated_business_field' => 'not-read'],
        [$scopedCsrfField],
        str_repeat('c', 64)
    );
}

/* Private guard checks use reflection to prove session binding and strict format. */
[$result, $status, $input, $session, $forbidden] = inventory_control_mutation_csrf_invoke_guard(
    str_repeat('c', 64),
    str_repeat('c', 64)
);
inventory_control_mutation_csrf_check($result === true && $status === null, 'valid session-bound token is accepted by the private guard');
inventory_control_mutation_csrf_check($input->postReads === ['inventory_control_mutation_csrf'], 'valid guard check reads only the CSRF field');
inventory_control_mutation_csrf_check($forbidden === [], 'valid guard check does not read dependencies');

[$result, $status] = inventory_control_mutation_csrf_invoke_guard(str_repeat('d', 64), str_repeat('c', 64));
inventory_control_mutation_csrf_check($result === false && $status === 403, 'token from another session is rejected by the private guard');

[$result, $status] = inventory_control_mutation_csrf_invoke_guard(str_repeat('c', 64), str_repeat('e', 64));
inventory_control_mutation_csrf_check($result === false && $status === 403, 'mismatched token is rejected by the private guard');

[$result, $status] = inventory_control_mutation_csrf_invoke_guard(str_repeat('c', 64), 'malformed');
inventory_control_mutation_csrf_check($result === false && $status === 403, 'malformed token is rejected by the private guard');

$helperReflection = new ReflectionClass(Inventory_control::class);
$helper = $helperReflection->getMethod('inventory_control_mutation_csrf');
$helper->setAccessible(true);

[$controller, $input, $session] = inventory_control_mutation_csrf_controller('GET', [], '');
$generated = $helper->invoke($controller);
inventory_control_mutation_csrf_check(
    ($generated['name'] ?? '') === 'inventory_control_mutation_csrf'
        && inventory_control_mutation_csrf_hex64((string)($generated['value'] ?? '')),
    'empty session token is regenerated as 64 hex characters'
);
inventory_control_mutation_csrf_check(count($session->writes) === 1, 'regenerated empty token is stored in the session');

[$controller, $input, $session] = inventory_control_mutation_csrf_controller('GET', [], 'malformed');
$generated = $helper->invoke($controller);
inventory_control_mutation_csrf_check(inventory_control_mutation_csrf_hex64((string)($generated['value'] ?? '')), 'malformed session token is regenerated as 64 hex characters');
inventory_control_mutation_csrf_check(count($session->writes) === 1, 'regenerated malformed token is stored in the session');

$validSessionToken = str_repeat('f', 64);
[$controller, $input, $session] = inventory_control_mutation_csrf_controller('GET', [], $validSessionToken);
$reused = $helper->invoke($controller);
inventory_control_mutation_csrf_check(
    ($reused['value'] ?? null) === $validSessionToken
        && inventory_control_mutation_csrf_hex64((string)($reused['value'] ?? '')),
    'well-formed session token is reused unchanged'
);
inventory_control_mutation_csrf_check($session->writes === [], 'well-formed session token is not rewritten');

/* View rendering is conditional on DOMDocument, with fixtures that cover every mutation-form branch. */
$token = str_repeat('1', 64);
$commonViewToken = [
    'inventory_control_mutation_csrf_name' => 'inventory_control_mutation_csrf',
    'inventory_control_mutation_csrf_value' => $token,
];

$periodIndexPath = $root . '/application/views/inventory/stock_period_index.php';
inventory_control_mutation_csrf_check_rendered_forms(
    'stock_period_index GET and POST forms',
    inventory_control_mutation_csrf_render_view($periodIndexPath, $commonViewToken + [
        'page_title' => 'Smoke',
        'filters' => [],
        'summary' => [],
        'rows' => [],
        'current_health' => [],
        'can_create' => true,
        'pg' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 1],
    ]),
    1,
    1,
    'inventory_control_mutation_csrf'
);

$periodDetailPath = $root . '/application/views/inventory/stock_period_detail.php';
$periodDetailBase = $commonViewToken + [
    'page_title' => 'Smoke',
    'period' => [
        'id' => 17,
        'period_month' => '2026-09-01',
        'stock_domain' => 'MATERIAL',
        'status' => 'OPEN',
    ],
    'preflight' => ['health' => []],
    'cutoff_preview' => ['ok' => false],
    'cutoff_posting' => ['can_post' => true, 'blocks' => [], 'warnings' => []],
    'cutoff_schema_ready' => true,
    'cutoff_runs' => [],
    'can_edit' => true,
    'can_post_cutoff' => true,
    'health_url' => '/smoke/health',
];
inventory_control_mutation_csrf_check_rendered_forms(
    'stock_period_detail cutoff form',
    inventory_control_mutation_csrf_render_view($periodDetailPath, $periodDetailBase),
    0,
    1,
    'inventory_control_mutation_csrf'
);
inventory_control_mutation_csrf_check_rendered_forms(
    'stock_period_detail reopen form',
    inventory_control_mutation_csrf_render_view($periodDetailPath, $periodDetailBase + [
        'period' => [
            'id' => 17,
            'period_month' => '2026-09-01',
            'stock_domain' => 'MATERIAL',
            'status' => 'CLOSED',
        ],
        'cutoff_posting' => [],
    ]),
    0,
    1,
    'inventory_control_mutation_csrf'
);

$deficitPath = $root . '/application/views/inventory/stock_deficit_detail.php';
inventory_control_mutation_csrf_check_rendered_forms(
    'stock_deficit_detail POST form',
    inventory_control_mutation_csrf_render_view($deficitPath, $commonViewToken + [
        'page_title' => 'Smoke',
        'detail' => [
            'header' => [
                'id' => 17,
                'status' => 'OPEN',
                'stock_domain' => 'MATERIAL',
                'inventory_name' => 'Fixture',
                'uom_code' => 'KG',
            ],
            'events' => [],
            'product_causes' => [],
            'settlements' => [],
            'live_lots' => [],
            'related_profile_stock' => [],
        ],
        'can_inline_recon' => false,
        'can_write_off' => true,
        'write_off_schema_ready' => true,
    ]),
    0,
    1,
    'inventory_control_mutation_csrf'
);

$valueReconPath = $root . '/application/views/inventory/stock_value_reconciliation_index.php';
inventory_control_mutation_csrf_check_rendered_forms(
    'stock_value_reconciliation GET form',
    inventory_control_mutation_csrf_render_view($valueReconPath, $commonViewToken + [
        'page_title' => 'Smoke',
        'context_input' => [],
        'context' => [],
        'records' => [],
        'value_candidates' => [],
        'candidate_filters' => [],
        'schema_ready' => true,
        'can_post' => true,
    ]),
    1,
    0,
    'inventory_control_mutation_csrf'
);
inventory_control_mutation_csrf_check_rendered_forms(
    'stock_value_reconciliation POST form',
    inventory_control_mutation_csrf_render_view($valueReconPath, $commonViewToken + [
        'page_title' => 'Smoke',
        'context_input' => [
            'month' => '2026-09-01',
            'stock_domain' => 'MATERIAL',
            'location_scope' => 'DIVISION',
            'location_type' => 'REGULER',
            'division_id' => 1,
            'item_id' => 2,
            'material_id' => 3,
            'buy_uom_id' => 4,
            'uom_id' => 5,
            'profile_key' => 'fixture-profile',
            'monthly_stock_id' => 6,
        ],
        'context' => [
            'ok' => true,
            'can_post' => true,
            'qty_gap' => 0,
            'value_gap' => 10,
            'stock' => ['inventory_name' => 'Fixture', 'uom_code' => 'KG'],
            'lots' => [],
        ],
        'records' => [],
        'value_candidates' => [],
        'candidate_filters' => [],
        'schema_ready' => true,
        'can_post' => true,
    ]),
    0,
    1,
    'inventory_control_mutation_csrf'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . ' inventory control mutation CSRF behavioral/source checks' . PHP_EOL;
