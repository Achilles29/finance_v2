<?php

declare(strict_types=1);

/**
 * Behavioral, DB-free smoke test for the four POS stock-commit repair writers.
 *
 * The real Pos controller is loaded with a deliberately small parent stub.
 * Invalid requests must stop after RBAC and the scoped header/session check;
 * payload, model, query, and writer access are fatal in the harness.
 */

define('BASEPATH', __DIR__);

class PosStockCommitRepairSmokeInput
{
    public array $events = [];

    private string $requestMethod;
    private array $headers;

    public function __construct(string $requestMethod, array $headers = [])
    {
        $this->requestMethod = strtoupper($requestMethod);
        $this->headers = [];
        foreach ($headers as $key => $value) {
            $this->headers[self::normalizeCgiHeaderName((string)$key)] = $value;
        }
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
        throw new RuntimeException('unexpected input property access: ' . $key);
    }
}

class PosStockCommitRepairSmokeSession
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

class PosStockCommitRepairSmokeOutput
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
}

$posStockCommitRepairSmokeForbiddenAccesses = [];

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public array $events = [];

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
    }

    public function __get($name)
    {
        global $posStockCommitRepairSmokeForbiddenAccesses;
        $posStockCommitRepairSmokeForbiddenAccesses[] = (string)$name;
        throw new RuntimeException('unexpected controller dependency access: ' . $name);
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];

function pos_stock_commit_repair_csrf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_stock_commit_repair_csrf_hex64(string $value): bool
{
    return preg_match('/\A[0-9a-fA-F]{64}\z/D', $value) === 1;
}

function pos_stock_commit_repair_csrf_controller(
    string $requestMethod,
    array $headers = [],
    array $sessionValues = []
): array {
    global $posStockCommitRepairSmokeForbiddenAccesses;
    $posStockCommitRepairSmokeForbiddenAccesses = [];

    $input = new PosStockCommitRepairSmokeInput($requestMethod, $headers);
    $session = new PosStockCommitRepairSmokeSession($sessionValues);
    $output = new PosStockCommitRepairSmokeOutput();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;

    return [$controller, $input, $session, $output, $reflection];
}

function pos_stock_commit_repair_csrf_invoke_action(
    string $action,
    string $requestMethod,
    array $headers = [],
    array $sessionValues = []
): array {
    [$controller, $input, $session, $output, $reflection] = pos_stock_commit_repair_csrf_controller(
        $requestMethod,
        $headers,
        $sessionValues
    );

    $exception = null;
    try {
        $reflection->getMethod($action)->invoke($controller);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    global $posStockCommitRepairSmokeForbiddenAccesses;
    return [
        $controller,
        $input,
        $session,
        $output,
        $posStockCommitRepairSmokeForbiddenAccesses,
        $exception,
    ];
}

function pos_stock_commit_repair_csrf_method_block(string $source, string $method): string
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

function pos_stock_commit_repair_csrf_response_is_json(
    PosStockCommitRepairSmokeOutput $output,
    int $status,
    string $label
): void {
    pos_stock_commit_repair_csrf_check($output->status === $status, $label . ' returns HTTP ' . $status);
    pos_stock_commit_repair_csrf_check($output->contentType === 'application/json', $label . ' returns JSON content type');
    $body = json_decode($output->body, true);
    pos_stock_commit_repair_csrf_check(is_array($body) && ($body['ok'] ?? null) === false, $label . ' returns a JSON rejection body');
}

function pos_stock_commit_repair_csrf_assert_no_dependency(
    PosStockCommitRepairSmokeInput $input,
    PosStockCommitRepairSmokeSession $session,
    array $forbidden,
    string $label
): void {
    pos_stock_commit_repair_csrf_check(
        !in_array('property:raw_input_stream', $input->events, true),
        $label . ' does not read raw_input_stream before rejection'
    );
    pos_stock_commit_repair_csrf_check($forbidden === [], $label . ' reaches no model/query/writer dependency');
}

$controllerSource = file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Pos.php');
$viewSource = file_get_contents(dirname(__DIR__, 2) . '/application/views/pos/stock_commit_audit_index.php');
$configSource = file_get_contents(dirname(__DIR__, 2) . '/application/config/config.php');

pos_stock_commit_repair_csrf_check(is_string($controllerSource), 'Pos.php can be read');
pos_stock_commit_repair_csrf_check(is_string($viewSource), 'stock commit audit view can be read');
pos_stock_commit_repair_csrf_check(
    is_string($configSource)
        && preg_match('/\$config\[\'csrf_protection\'\]\s*=\s*FALSE\s*;/i', $configSource) === 1,
    'global CSRF remains disabled outside this scoped batch'
);

$actions = [
    'stock_commit_audit_repair_material_mismatches',
    'stock_commit_audit_repair_component_mismatches',
    'stock_commit_audit_repair_material_drift',
    'stock_commit_audit_repair_component_drift',
];
$headerName = 'X-Pos-Stock-Commit-CSRF';
$sessionKey = 'pos_stock_commit_audit_csrf';
$validToken = str_repeat('a', 64);
$otherSessionToken = str_repeat('b', 64);
$otherProvidedToken = str_repeat('c', 64);

pos_stock_commit_repair_csrf_check(
    strpos($controllerSource, "private const POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY = '" . $sessionKey . "';") !== false,
    'controller defines the scoped session key'
);
pos_stock_commit_repair_csrf_check(
    strpos($controllerSource, "private const POS_STOCK_COMMIT_AUDIT_CSRF_HEADER = '" . $headerName . "';") !== false,
    'controller defines the exact scoped header'
);
$canonicalHeaderName = 'X-Pos-Stock-Commit-Csrf';
pos_stock_commit_repair_csrf_check(
    strpos($controllerSource, "private const POS_STOCK_COMMIT_AUDIT_CSRF_CI_HEADER = '" . $canonicalHeaderName . "';") !== false,
    'controller defines the canonical CI header lookup'
);
pos_stock_commit_repair_csrf_check(
    strpos($controllerSource, 'random_bytes(32)') !== false
        && strpos($controllerSource, 'bin2hex(random_bytes(32))') !== false,
    'token generation uses 32 random bytes and hex encoding'
);
pos_stock_commit_repair_csrf_check(
    substr_count($controllerSource, 'require_stock_commit_audit_csrf()') === count($actions) + 1,
    'exactly four repair writers call the scoped guard'
);

$writerMarkers = [
    'stock_commit_audit_repair_material_mismatches' => 'repair_division_material_reconcile',
    'stock_commit_audit_repair_component_mismatches' => 'repair_component_reconcile',
    'stock_commit_audit_repair_material_drift' => 'repair_material_monthly_stock_drift',
    'stock_commit_audit_repair_component_drift' => 'repair_component_monthly_stock_drift',
];

foreach ($writerMarkers as $action => $writerMarker) {
    $block = pos_stock_commit_repair_csrf_method_block($controllerSource, $action);
    $permissionPosition = strpos($block, '$this->require_permission(');
    $guardPosition = strpos($block, '$this->require_stock_commit_audit_csrf()');

    pos_stock_commit_repair_csrf_check($block !== '', $action . ' exists in the controller');
    pos_stock_commit_repair_csrf_check(
        $permissionPosition !== false
            && $guardPosition !== false
            && $permissionPosition < $guardPosition,
        $action . ' checks RBAC before scoped CSRF'
    );

    foreach (['json_decode(', '$this->input->post(', '$this->input->raw_input_stream', $writerMarker] as $dependencyMarker) {
        $dependencyPosition = strpos($block, $dependencyMarker);
        pos_stock_commit_repair_csrf_check(
            $dependencyPosition !== false && $guardPosition < $dependencyPosition,
            $action . ' checks scoped CSRF before ' . $dependencyMarker
        );
    }
}

$guardStart = strpos($controllerSource, 'private function require_stock_commit_audit_csrf(): bool');
$rejectStart = strpos($controllerSource, 'private function reject_stock_commit_audit_csrf(');
$guardSource = $guardStart === false
    ? ''
    : substr($controllerSource, $guardStart, ($rejectStart === false ? strlen($controllerSource) : $rejectStart) - $guardStart);
$helperStart = strpos($controllerSource, 'private function stock_commit_audit_csrf(): string');
$helperSource = $helperStart === false
    ? ''
    : substr($controllerSource, $helperStart, ($guardStart === false ? strlen($controllerSource) : $guardStart) - $helperStart);
$rejectSource = $rejectStart === false ? '' : substr($controllerSource, $rejectStart);

pos_stock_commit_repair_csrf_check(
    strpos($guardSource, '$this->input->method(true) !== \'POST\'') !== false
        && strpos($guardSource, 'reject_stock_commit_audit_csrf(405') !== false,
    'private guard rejects non-POST with 405'
);
pos_stock_commit_repair_csrf_check(
    strpos($guardSource, 'get_request_header(self::POS_STOCK_COMMIT_AUDIT_CSRF_CI_HEADER, true)') !== false
        && strpos($guardSource, 'preg_match(\'/\\A[0-9a-fA-F]{64}\\z/D\', $providedToken)') !== false
        && strpos($guardSource, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardSource, 'reject_stock_commit_audit_csrf(403') !== false,
    'private guard validates exact header/session token and rejects invalid values with 403'
);
pos_stock_commit_repair_csrf_check(
    strpos($helperSource, 'userdata(self::POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY)') !== false
        && strpos($helperSource, 'set_userdata(self::POS_STOCK_COMMIT_AUDIT_CSRF_SESSION_KEY, $token)') !== false
        && strpos($helperSource, 'preg_match(\'/\\A[0-9a-fA-F]{64}\\z/D\', $token)') !== false,
    'token helper reuses only a valid 64-hex session token'
);
pos_stock_commit_repair_csrf_check(
    strpos($rejectSource, "set_content_type('application/json')") !== false
        && strpos($rejectSource, 'set_status_header($statusCode)') !== false
        && strpos($rejectSource, "'ok' => false") !== false,
    'scoped rejection writes JSON status and body'
);

$globalPostJsonStart = strpos($viewSource, 'async function postJson(');
$repairWrapperStart = strpos($viewSource, 'function postStockCommitAuditRepairJson(');
$globalPostJsonSource = $globalPostJsonStart === false
    ? ''
    : substr($viewSource, $globalPostJsonStart, ($repairWrapperStart === false ? strlen($viewSource) : $repairWrapperStart) - $globalPostJsonStart);
$repairWrapperSource = $repairWrapperStart === false ? '' : substr($viewSource, $repairWrapperStart);

pos_stock_commit_repair_csrf_check(
    strpos($globalPostJsonSource, $headerName) === false,
    'shared postJson helper does not add the scoped repair header'
);
pos_stock_commit_repair_csrf_check(
    strpos($repairWrapperSource, 'postJson(url, payload') !== false
        && substr_count($repairWrapperSource, $headerName) === 1
        && substr_count($viewSource, 'postStockCommitAuditRepairJson(') === count($actions) + 1,
    'scoped wrapper carries the header and is used by exactly four repair calls'
);
pos_stock_commit_repair_csrf_check(
    strpos($viewSource, "postJson('<?php echo site_url('inventory/stock/division/reconcile/lot-repair-all'); ?>', payload)") !== false,
    'the unrelated material-lot repair keeps the shared helper without this header'
);
pos_stock_commit_repair_csrf_check(
    strpos($controllerSource, "'stock_commit_audit_csrf_token' => " . '$stockCommitAuditCsrfToken') !== false
        && strpos($viewSource, 'stock_commit_audit_csrf_token') !== false,
    'audit page receives and renders the scoped token after permission'
);

foreach ($actions as $action) {
    [$controller, $input, $session, $output, $forbidden, $exception] = pos_stock_commit_repair_csrf_invoke_action(
        $action,
        'GET',
        [],
        [$sessionKey => $validToken]
    );
    pos_stock_commit_repair_csrf_check($exception === null, $action . ' GET completes at the guard');
    pos_stock_commit_repair_csrf_response_is_json($output, 405, $action . ' GET');
    pos_stock_commit_repair_csrf_check(
        $input->events === ['method'],
        $action . ' GET reads no header, session token, or payload'
    );
    pos_stock_commit_repair_csrf_check($session->reads === [], $action . ' GET reads no session token');
    pos_stock_commit_repair_csrf_assert_no_dependency($input, $session, $forbidden, $action . ' GET');
    pos_stock_commit_repair_csrf_check(
        array_search('permission', $controller->events, true) !== false
            && $input->events[0] === 'method',
        $action . ' GET runs scoped guard after RBAC'
    );

    $invalidCases = [
        'missing header' => [[], [$sessionKey => $validToken], []],
        'empty header' => [[$headerName => ''], [$sessionKey => $validToken], []],
        'malformed header' => [[$headerName => 'malformed'], [$sessionKey => $validToken], []],
        'absent session' => [[$headerName => $validToken], [], [$sessionKey]],
        'cross-session token' => [[$headerName => $otherSessionToken], [$sessionKey => $validToken], [$sessionKey]],
        'mismatched token' => [[$headerName => $otherProvidedToken], [$sessionKey => $validToken], [$sessionKey]],
    ];

    foreach ($invalidCases as $case => [$headers, $sessionValues, $expectedSessionReads]) {
        [$controller, $input, $session, $output, $forbidden, $exception] = pos_stock_commit_repair_csrf_invoke_action(
            $action,
            'POST',
            $headers,
            $sessionValues
        );
        pos_stock_commit_repair_csrf_check($exception === null, $action . ' ' . $case . ' completes at the guard');
        pos_stock_commit_repair_csrf_response_is_json($output, 403, $action . ' ' . $case);
        pos_stock_commit_repair_csrf_check(
            $input->events === ['method', 'header:' . $canonicalHeaderName],
            $action . ' ' . $case . ' reads only method and scoped header before rejection'
        );
        pos_stock_commit_repair_csrf_check(
            $session->reads === $expectedSessionReads,
            $action . ' ' . $case . ' reads session only when a valid-looking header requires binding'
        );
        pos_stock_commit_repair_csrf_assert_no_dependency($input, $session, $forbidden, $action . ' ' . $case);
        pos_stock_commit_repair_csrf_check(
            array_search('permission', $controller->events, true) !== false
                && $input->events[0] === 'method',
            $action . ' ' . $case . ' runs scoped guard after RBAC'
        );
    }

    [$controller, $input, $session, $output, $reflection] = pos_stock_commit_repair_csrf_controller(
        'POST',
        [$canonicalHeaderName => $validToken],
        [$sessionKey => $validToken]
    );
    $exception = null;
    try {
        $reflection->getMethod($action)->invoke($controller);
    } catch (Throwable $caught) {
        $exception = $caught;
    }
    pos_stock_commit_repair_csrf_check(
        $exception === null && $output->status === 410,
        $action . ' rejects retired repair even with valid normalized credentials'
    );
    pos_stock_commit_repair_csrf_check(
        $input->events === ['method', 'header:' . $canonicalHeaderName],
        $action . ' retired repair never reads a mutation payload'
    );
    pos_stock_commit_repair_csrf_check(
        $session->reads === [$sessionKey]
            && $output->status === 410
            && $forbidden === [],
        $action . ' valid request remains session-bound without model/query execution'
    );
}

[$controller, $input, $session, $output, $reflection] = pos_stock_commit_repair_csrf_controller(
    'POST',
    [$headerName => $validToken],
    [$sessionKey => $validToken]
);
$guard = $reflection->getMethod('require_stock_commit_audit_csrf');
$guard->setAccessible(true);
$guardResult = $guard->invoke($controller);
pos_stock_commit_repair_csrf_check($guardResult === true, 'valid session-bound token is accepted by the private guard');
pos_stock_commit_repair_csrf_check(
    $input->events === ['method', 'header:' . $canonicalHeaderName]
        && $session->reads === [$sessionKey],
    'valid private guard reads only method, scoped header, and scoped session token'
);
pos_stock_commit_repair_csrf_check($output->status === null, 'valid private guard emits no rejection');

[$controller, $input, $session, $output, $reflection] = pos_stock_commit_repair_csrf_controller(
    'GET',
    [],
    []
);
$tokenHelper = $reflection->getMethod('stock_commit_audit_csrf');
$tokenHelper->setAccessible(true);
$generatedToken = $tokenHelper->invoke($controller);
$reusedToken = $tokenHelper->invoke($controller);
pos_stock_commit_repair_csrf_check(pos_stock_commit_repair_csrf_hex64($generatedToken), 'missing session token generates an invariant 64-hex token');
pos_stock_commit_repair_csrf_check($generatedToken === $reusedToken, 'valid generated token is reused within the session');
pos_stock_commit_repair_csrf_check($session->writes === [$sessionKey], 'token generation writes the scoped session key once');

[$controller, $input, $session, $output, $reflection] = pos_stock_commit_repair_csrf_controller(
    'GET',
    [],
    [$sessionKey => 'invalid-token']
);
$tokenHelper = $reflection->getMethod('stock_commit_audit_csrf');
$tokenHelper->setAccessible(true);
$replacedToken = $tokenHelper->invoke($controller);
pos_stock_commit_repair_csrf_check(pos_stock_commit_repair_csrf_hex64($replacedToken), 'malformed session token is replaced with an invariant 64-hex token');
pos_stock_commit_repair_csrf_check($session->writes === [$sessionKey], 'malformed session token is not reused');

class PosStockCommitRepairSmokeViewLoader
{
    public array $views = [];

    public function view($name, $data = []): void
    {
        $this->views[] = (string)$name;
    }
}

class PosStockCommitRepairSmokeViewContext
{
    public PosStockCommitRepairSmokeViewLoader $load;

    public function __construct()
    {
        $this->load = new PosStockCommitRepairSmokeViewLoader();
    }
}

function site_url($uri = ''): string
{
    return '/smoke/' . ltrim((string)$uri, '/');
}

function html_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pos_stock_commit_repair_csrf_render_view(string $path, array $variables): ?string
{
    $baseLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (): bool {
        throw new RuntimeException('view rendering warning');
    });

    $renderer = function () use ($path, $variables): string {
        extract($variables, EXTR_SKIP);
        include $path;
        return (string)ob_get_contents();
    };
    $renderer = $renderer->bindTo(new PosStockCommitRepairSmokeViewContext(), PosStockCommitRepairSmokeViewContext::class);

    try {
        $html = $renderer();
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
$renderedView = pos_stock_commit_repair_csrf_render_view(
    dirname(__DIR__, 2) . '/application/views/pos/stock_commit_audit_index.php',
    [
        'as_of_date' => '2026-09-02',
        'audit_tab' => 'material',
        'audit_month_from' => '2026-09-01',
        'audit_month_to' => '2026-09-30',
        'material_compare' => [
            'rows' => [],
            'summary' => [],
            'summary_all' => [],
            'options' => [],
            'pagination' => [],
            'filters' => [],
        ],
        'component_compare' => [
            'rows' => [],
            'summary' => [],
            'summary_all' => [],
            'options' => [],
            'pagination' => [],
            'filters' => [],
        ],
        'domain_audit' => ['rows' => [], 'summary' => []],
        'failed_jobs' => [],
        'active_jobs' => [],
        'failed_commit_snapshots' => [],
        'void_material_lot_audit' => ['rows' => [], 'summary' => []],
        'division_options' => [],
        'stock_commit_audit_csrf_token' => $renderToken,
    ]
);

pos_stock_commit_repair_csrf_check($renderedView !== null, 'audit view renders without warning or exception');
if ($renderedView !== null) {
    pos_stock_commit_repair_csrf_check(strpos($renderedView, $renderToken) !== false, 'audit view renders the session-bound token');
    pos_stock_commit_repair_csrf_check(strpos($renderedView, $headerName) !== false, 'audit view contains the exact scoped header');
    pos_stock_commit_repair_csrf_check(
        substr_count($renderedView, 'postStockCommitAuditRepairJson(') === count($actions) + 1,
        'rendered audit view has exactly four scoped repair request calls'
    );
    pos_stock_commit_repair_csrf_check(
        strpos($renderedView, "postJson('/smoke/inventory/stock/division/reconcile/lot-repair-all', payload)") !== false,
        'rendered unrelated material-lot repair does not use the scoped wrapper'
    );
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . ' POS stock-commit repair CSRF behavioral/source/render checks' . PHP_EOL;
