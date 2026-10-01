<?php
declare(strict_types=1);

defined('BASEPATH') || define('BASEPATH', __DIR__ . '/');

const MRPB_FIELD = 'master_relation_product_bundle_mutation_csrf';
const MRPB_PAGE = 'master.product_bundle.index';
const MRPB_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const MRPB_WRONG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MRPB_CROSS_SCOPE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

final class MasterRelationBundleSmokeResponse extends RuntimeException
{
}

final class MasterRelationBundleSmokeInput
{
    private string $requestMethod;
    private array $postData;
    private array $getData;
    private array $headers;
    private string $rawBody;
    public array $events = [];
    public array $postReads = [];
    public array $getReads = [];
    public array $headerReads = [];
    public int $rawReads = 0;

    public function __construct(
        string $requestMethod,
        array $postData = [],
        array $getData = [],
        array $headers = [],
        string $rawBody = ''
    ) {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
        $this->getData = $getData;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
    }

    public function method($upper = false): string
    {
        $this->events[] = ['method'];
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $label = $key === null ? '*' : (string)$key;
        $this->events[] = ['post', $label];
        $this->postReads[] = $label;
        return $key === null ? $this->postData : ($this->postData[$label] ?? null);
    }

    public function get($key = null, $xssClean = false)
    {
        $label = $key === null ? '*' : (string)$key;
        $this->events[] = ['get', $label];
        $this->getReads[] = $label;
        return $key === null ? $this->getData : ($this->getData[$label] ?? null);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $label = (string)$key;
        $this->events[] = ['header', $label];
        $this->headerReads[] = $label;
        return (string)($this->headers[$label] ?? '');
    }

    public function __get($name)
    {
        if ((string)$name !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$name);
        }
        $this->events[] = ['raw'];
        $this->rawReads++;
        return $this->rawBody;
    }
}

final class MasterRelationBundleSmokeSession
{
    public array $values;
    public array $reads = [];
    public array $writes = [];
    public array $flashes = [];

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $label = (string)$key;
        $this->reads[] = $label;
        return $this->values[$label] ?? null;
    }

    public function set_userdata($key, $value = null): void
    {
        $label = (string)$key;
        $this->writes[] = [$label, $value];
        $this->values[$label] = $value;
    }

    public function set_flashdata($key, $value): void
    {
        $this->flashes[] = [(string)$key, (string)$value];
    }
}

final class MasterRelationBundleSmokeResult
{
    private array $row;
    private array $rows;

    public function __construct(array $row = [], array $rows = [])
    {
        $this->row = $row;
        $this->rows = $rows;
    }

    public function row_array(): array
    {
        return $this->row;
    }

    public function result_array(): array
    {
        return $this->rows;
    }
}

final class MasterRelationBundleSmokeDb
{
    public array $calls = [];
    private string $fromTable = '';
    private array $bundleRows;

    public function __construct()
    {
        $this->bundleRows = [[
            'id' => 91,
            'bundle_code' => 'BND-FIXTURE',
            'bundle_name' => 'Fixture Bundle',
            'selling_price' => 50000,
            'product_division_id' => 4,
            'product_division_name' => 'Kitchen',
            'pos_scope' => 'REGULAR',
            'sort_order' => 10,
            'is_active' => 1,
            'description' => 'Fixture',
            'total_line' => 1,
            'line_value_total' => 60000,
        ]];
    }

    public function query($sql, $bindings = [])
    {
        $this->calls[] = ['query', [(string)$sql, $bindings]];
        $sql = (string)$sql;
        if (strpos($sql, 'FROM pos_product_bundle_line') !== false) {
            $line = [
                'id' => 11,
                'bundle_id' => 91,
                'product_id' => 31,
                'qty' => 2,
                'unit_price_override' => null,
                'sort_order' => 10,
            ];
            return new MasterRelationBundleSmokeResult($line, [$line]);
        }
        if (strpos($sql, 'FROM pos_product_bundle') !== false) {
            return new MasterRelationBundleSmokeResult($this->bundleRows[0], $this->bundleRows);
        }
        return false;
    }

    public function __call($name, $arguments)
    {
        $name = (string)$name;
        $this->calls[] = [$name, $arguments];
        if ($name === 'from') {
            $this->fromTable = (string)($arguments[0] ?? '');
            return $this;
        }
        if ($name === 'table_exists') {
            return true;
        }
        if ($name === 'field_exists') {
            return (string)($arguments[1] ?? '') === 'aud_transaction_log';
        }
        if ($name === 'insert_id') {
            return 77;
        }
        if ($name === 'trans_status') {
            return true;
        }
        if (in_array($name, ['insert', 'update', 'delete', 'trans_begin', 'trans_commit', 'trans_rollback'], true)) {
            return true;
        }
        if ($name === 'count_all_results') {
            return 0;
        }
        if ($name === 'get') {
            $table = $this->fromTable;
            $this->fromTable = '';
            if ($table === 'pos_product_bundle b') {
                return new MasterRelationBundleSmokeResult($this->bundleRows[0], $this->bundleRows);
            }
            if ($table === 'pos_product_bundle_line bl') {
                $line = [
                    'id' => 11,
                    'bundle_id' => 91,
                    'product_id' => 31,
                    'product_code' => 'PRD-31',
                    'product_name' => 'Fixture Product',
                    'product_division_id' => 4,
                    'product_division_name' => 'Kitchen',
                    'product_selling_price' => 30000,
                    'qty' => 2,
                    'unit_price_override' => null,
                    'sort_order' => 10,
                    'uom_code' => 'PCS',
                ];
                return new MasterRelationBundleSmokeResult($line, [$line]);
            }
            if ($table === 'mst_product') {
                return new MasterRelationBundleSmokeResult(
                    ['id' => 31, 'product_division_id' => 4],
                    [['id' => 31, 'product_division_id' => 4]]
                );
            }
            return new MasterRelationBundleSmokeResult();
        }
        return $this;
    }
}

final class MasterRelationBundleSmokeModel
{
    public array $calls = [];

    public function get_options($table, $key, $label, $activeOnly = false): array
    {
        $this->calls[] = ['get_options', (string)$table];
        return [['value' => 4, 'label' => 'Kitchen']];
    }
}

final class MasterRelationBundleSmokePricing
{
    public function allocate($price, array $lines): array
    {
        return ['bundle_price' => (float)$price, 'lines' => $lines];
    }
}

class MY_Controller
{
    public MasterRelationBundleSmokeInput $input;
    public MasterRelationBundleSmokeSession $session;
    public MasterRelationBundleSmokeDb $db;
    public MasterRelationBundleSmokeModel $Master_model;
    public MasterRelationBundleSmokePricing $posbundlepricingservice;
    public array $permissionCalls = [];
    public bool $permissionAllowed = true;
    public ?string $renderedView = null;
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new MasterRelationBundleSmokeResponse('Forbidden', 403);
        }
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
    }
}

function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationBundleSmokeResponse((string)$message, (int)$statusCode);
}

function show_404(): void
{
    throw new MasterRelationBundleSmokeResponse('Not Found', 404);
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
}

function log_message($level, $message): void
{
}

function site_url($uri = ''): string
{
    return '/smoke/' . ltrim((string)$uri, '/');
}

function html_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require dirname(__DIR__, 2) . '/application/controllers/Master_relation.php';

final class MasterRelationBundleSmokeViewHost
{
    public function render(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string)ob_get_clean();
    }
}

$checks = 0;
$failures = [];

function mrpb_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function mrpb_method_source(string $source, string $method): string
{
    if (preg_match('/(?:public|private|protected) function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = (int)$match[0][1];
    if (preg_match('/\n    (?:public|private|protected) function\s+/', $source, $next, PREG_OFFSET_CAPTURE, $start + 1) !== 1) {
        return substr($source, $start);
    }
    return substr($source, $start, (int)$next[0][1] - $start);
}

function mrpb_fixture(
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRPB_FIELD => MRPB_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): Master_relation {
    $reflection = new ReflectionClass(Master_relation::class);
    /** @var Master_relation&MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationBundleSmokeInput($method, $post, $get, $headers, $rawBody);
    $controller->session = new MasterRelationBundleSmokeSession($sessionValues);
    $controller->db = new MasterRelationBundleSmokeDb();
    $controller->Master_model = new MasterRelationBundleSmokeModel();
    $controller->posbundlepricingservice = new MasterRelationBundleSmokePricing();
    $controller->permissionAllowed = $permissionAllowed;
    return $controller;
}

function mrpb_invoke_writer(
    string $writer,
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRPB_FIELD => MRPB_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    $controller = mrpb_fixture($method, $post, $permissionAllowed, $sessionValues, $get, $headers, $rawBody);
    $response = null;
    try {
        if ($writer === 'product_bundle_store') {
            $controller->{$writer}();
        } else {
            $controller->{$writer}(91);
        }
    } catch (MasterRelationBundleSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

function mrpb_valid_post(): array
{
    return [
        MRPB_FIELD => MRPB_TOKEN,
        'bundle_code' => 'BND-FIXTURE',
        'bundle_name' => 'Fixture Bundle',
        'pos_scope' => 'REGULAR',
        'selling_price' => '50000',
        'description' => 'Fixture',
        'sort_order' => '10',
        'is_active' => '1',
        'product_bundle_revision' => mrpb_revision(),
        'lines_json' => json_encode([[
            'product_id' => 31,
            'qty' => 2,
            'unit_price_override' => '',
            'sort_order' => 10,
        ]]),
    ];
}

function mrpb_revision(): string
{
    return hash('sha256', json_encode([
        'id' => 91,
        'bundle_code' => 'BND-FIXTURE',
        'bundle_name' => 'Fixture Bundle',
        'product_division_id' => 4,
        'pos_scope' => 'REGULAR',
        'selling_price' => '50000.00',
        'description' => 'Fixture',
        'sort_order' => 10,
        'is_active' => 1,
        'lines' => [[
            'id' => 11,
            'product_id' => 31,
            'qty' => '2.0000',
            'unit_price_override' => null,
            'sort_order' => 10,
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
}

function mrpb_has_db_call(MasterRelationBundleSmokeDb $db, string $method, ?string $table = null): bool
{
    foreach ($db->calls as $call) {
        if (($call[0] ?? '') !== $method) {
            continue;
        }
        if ($table === null || (string)($call[1][0] ?? '') === $table) {
            return true;
        }
    }
    return false;
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Master_relation.php';
$editViewPath = $root . '/application/views/master/product_bundle_edit.php';
$hubViewPath = $root . '/application/views/master/product_bundle_hub.php';
$controllerSource = (string)file_get_contents($controllerPath);
$editViewSource = (string)file_get_contents($editViewPath);
$hubViewSource = (string)file_get_contents($hubViewPath);

mrpb_check(
    strpos($controllerSource, "PRODUCT_BUNDLE_MUTATION_CSRF_SESSION_KEY = '" . MRPB_FIELD . "'") !== false
        && strpos($controllerSource, "PRODUCT_BUNDLE_MUTATION_CSRF_FORM_FIELD = '" . MRPB_FIELD . "'") !== false,
    'controller declares exact bundle-only session and form keys'
);

$tokenSource = mrpb_method_source($controllerSource, 'productBundleMutationCsrf');
$guardSource = mrpb_method_source($controllerSource, 'requireProductBundleMutationCsrf');
mrpb_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'token helper generates and validates strict lowercase 64-hex tokens'
);
mrpb_check(
    strpos($guardSource, "method(true) !== 'POST'") < strpos($guardSource, '->post(')
        && strpos($guardSource, '->post(') < strpos($guardSource, 'hash_equals(')
        && strpos($guardSource, ', 405,') !== false
        && strpos($guardSource, ', 403,') !== false,
    'guard orders POST-only enforcement before form token and constant-time comparison'
);
mrpb_check(
    strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'get_request_header') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'json_decode') === false,
    'guard has no query, header, raw-body, or JSON fallback'
);

$writers = [
    'product_bundle_store' => 'create',
    'product_bundle_update' => 'edit',
    'product_bundle_toggle' => 'edit',
];

foreach ($writers as $writer => $action) {
    $source = mrpb_method_source($controllerSource, $writer);
    $permissionAt = strpos($source, "requireRelationPermission('bundle', '" . $action . "')");
    $guardAt = strpos($source, 'requireProductBundleMutationCsrf()');
    $businessMarkers = $writer === 'product_bundle_store'
        ? ['normalizeProductBundlePayload(', '->trans_start(']
        : ['loadProductBundle(', 'normalizeProductBundlePayload(', '->update('];
    $firstBusinessAt = strlen($source);
    foreach ($businessMarkers as $marker) {
        $position = strpos($source, $marker);
        if ($position !== false) {
            $firstBusinessAt = min($firstBusinessAt, $position);
        }
    }
    mrpb_check(
        $permissionAt !== false && $guardAt !== false
            && $permissionAt < $guardAt && $guardAt < $firstBusinessAt,
        $writer . ' orders canonical RBAC, scoped guard, then business/DB access'
    );

    foreach (['GET', 'PUT'] as $method) {
        [$controller, $response] = mrpb_invoke_writer($writer, $method);
        mrpb_check($response instanceof MasterRelationBundleSmokeResponse && $response->getCode() === 405, $writer . ' rejects ' . $method . ' with HTTP 405');
        mrpb_check($controller->permissionCalls === [[MRPB_PAGE, $action]], $writer . ' ' . $method . ' preserves canonical RBAC');
        mrpb_check($controller->input->postReads === [] && $controller->session->reads === [], $writer . ' ' . $method . ' reads no payload/token');
        mrpb_check($controller->db->calls === [] && $controller->Master_model->calls === [], $writer . ' ' . $method . ' performs no business/DB work');
    }

    [$denied, $deniedResponse] = mrpb_invoke_writer($writer, 'POST', mrpb_valid_post(), false);
    mrpb_check($deniedResponse instanceof MasterRelationBundleSmokeResponse && $deniedResponse->getCode() === 403, $writer . ' RBAC denial returns 403');
    mrpb_check($denied->permissionCalls === [[MRPB_PAGE, $action]], $writer . ' uses exact RBAC action');
    mrpb_check($denied->input->events === [] && $denied->session->reads === [], $writer . ' RBAC denial precedes method/token/payload reads');
    mrpb_check($denied->db->calls === [] && $denied->Master_model->calls === [], $writer . ' RBAC denial performs no DB/model work');

    $rejections = [
        'missing despite alternate channels' => [
            ['csrf_token' => MRPB_TOKEN, 'master_relation_product_recipe_mutation_csrf' => MRPB_TOKEN],
            [MRPB_FIELD => MRPB_TOKEN],
            [MRPB_FIELD => MRPB_TOKEN, 'X-CSRF-Token' => MRPB_TOKEN],
            json_encode([MRPB_FIELD => MRPB_TOKEN]),
        ],
        'malformed' => [[MRPB_FIELD => 'NOT-LOWERCASE-HEX'], [], [], ''],
        'wrong' => [[MRPB_FIELD => MRPB_WRONG], [], [], ''],
        'cross-scope' => [[MRPB_FIELD => MRPB_CROSS_SCOPE], [], [], ''],
    ];
    foreach ($rejections as $label => [$tokenPost, $get, $headers, $raw]) {
        $post = $tokenPost + ['bundle_name' => 'must-not-be-read', 'lines_json' => '[]'];
        [$controller, $response] = mrpb_invoke_writer(
            $writer,
            'POST',
            $post,
            true,
            [MRPB_FIELD => MRPB_TOKEN],
            $get,
            $headers,
            (string)$raw
        );
        mrpb_check($response instanceof MasterRelationBundleSmokeResponse && $response->getCode() === 403, $writer . ' rejects ' . $label . ' token with HTTP 403');
        mrpb_check($controller->input->postReads === [MRPB_FIELD], $writer . ' ' . $label . ' reads only exact bundle form field');
        mrpb_check($controller->session->reads === [MRPB_FIELD], $writer . ' ' . $label . ' reads only bundle session key');
        mrpb_check($controller->input->getReads === [] && $controller->input->headerReads === [] && $controller->input->rawReads === 0, $writer . ' ' . $label . ' ignores alternate token channels');
        mrpb_check($controller->db->calls === [] && $controller->Master_model->calls === [], $writer . ' ' . $label . ' performs no business query/write');
    }
}

[$store, $storeResponse] = mrpb_invoke_writer('product_bundle_store', 'POST', mrpb_valid_post());
mrpb_check($storeResponse === null, 'valid store POST passes bundle guard');
mrpb_check(mrpb_has_db_call($store->db, 'insert', 'pos_product_bundle'), 'valid store reaches bundle insert');
mrpb_check(mrpb_has_db_call($store->db, 'insert', 'pos_product_bundle_line'), 'valid store reaches bundle-line insert');
mrpb_check(mrpb_has_db_call($store->db, 'insert', 'aud_transaction_log'), 'valid store writes its audit record before commit');

[$update, $updateResponse] = mrpb_invoke_writer('product_bundle_update', 'POST', mrpb_valid_post());
mrpb_check($updateResponse === null, 'valid update POST passes bundle guard');
mrpb_check(mrpb_has_db_call($update->db, 'update', 'pos_product_bundle'), 'valid update reaches bundle update');
mrpb_check(mrpb_has_db_call($update->db, 'delete', 'pos_product_bundle_line'), 'valid update replaces prior bundle lines');
mrpb_check(mrpb_has_db_call($update->db, 'insert', 'pos_product_bundle_line'), 'valid update writes normalized bundle lines');
mrpb_check(mrpb_has_db_call($update->db, 'insert', 'aud_transaction_log'), 'valid update writes its replacement audit record before commit');

[$toggle, $toggleResponse] = mrpb_invoke_writer('product_bundle_toggle', 'POST', [MRPB_FIELD => MRPB_TOKEN]);
mrpb_check($toggleResponse === null, 'valid toggle POST passes bundle guard');
$toggleMutation = null;
foreach ($toggle->db->calls as $call) {
    if (($call[0] ?? '') === 'update' && ($call[1][0] ?? '') === 'pos_product_bundle') {
        $toggleMutation = $call[1][1] ?? null;
    }
}
mrpb_check(is_array($toggleMutation) && ($toggleMutation['is_active'] ?? null) === 0, 'valid toggle reaches expected status mutation');
mrpb_check(mrpb_has_db_call($toggle->db, 'insert', 'aud_transaction_log'), 'valid toggle writes its audit record before commit');

$reflection = new ReflectionClass(Master_relation::class);
$guard = $reflection->getMethod('requireProductBundleMutationCsrf');
$guard->setAccessible(true);
$validGuard = mrpb_fixture('POST', [MRPB_FIELD => MRPB_TOKEN]);
mrpb_check($guard->invoke($validGuard) === true, 'private guard accepts matching scoped form/session token');
mrpb_check($validGuard->input->postReads === [MRPB_FIELD] && $validGuard->session->reads === [MRPB_FIELD], 'valid guard reads exact isolated token sources');

$tokenHelper = $reflection->getMethod('productBundleMutationCsrf');
$tokenHelper->setAccessible(true);
$newToken = mrpb_fixture('GET', [], true, []);
$generated = (string)$tokenHelper->invoke($newToken);
mrpb_check(preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1, 'missing token generates strict lowercase 64-hex');
mrpb_check(count($newToken->session->writes) === 1 && $newToken->session->writes[0][0] === MRPB_FIELD, 'generated token is stored only under bundle key');
$malformedToken = mrpb_fixture('GET', [], true, [MRPB_FIELD => strtoupper(MRPB_TOKEN)]);
$regenerated = (string)$tokenHelper->invoke($malformedToken);
mrpb_check(preg_match('/\A[0-9a-f]{64}\z/D', $regenerated) === 1 && $regenerated !== strtoupper(MRPB_TOKEN), 'uppercase session token is regenerated');

$renders = [
    'product_bundle_hub' => ['view', null, 'master/product_bundle_hub'],
    'product_bundle_create' => ['create', null, 'master/product_bundle_edit'],
    'product_bundle_edit' => ['edit', 91, 'master/product_bundle_edit'],
];
foreach ($renders as $method => [$action, $id, $view]) {
    $source = mrpb_method_source($controllerSource, $method);
    $permissionAt = strpos($source, "requireRelationPermission('bundle', '" . $action . "')");
    $tokenAt = strpos($source, 'productBundleMutationCsrf()');
    mrpb_check($permissionAt !== false && $tokenAt !== false && $permissionAt < $tokenAt, $method . ' obtains token only after canonical RBAC');

    $authorized = mrpb_fixture('GET', [], true, []);
    $id === null ? $authorized->{$method}() : $authorized->{$method}($id);
    mrpb_check($authorized->permissionCalls === [[MRPB_PAGE, $action]], $method . ' preserves exact render RBAC');
    mrpb_check($authorized->renderedView === $view && preg_match('/\A[0-9a-f]{64}\z/D', (string)($authorized->renderedData[MRPB_FIELD] ?? '')) === 1, $method . ' authorized render receives bundle token');
    mrpb_check(!array_key_exists('master_relation_product_recipe_mutation_csrf', $authorized->renderedData) && !array_key_exists('master_relation_component_formula_mutation_csrf', $authorized->renderedData) && !array_key_exists('master_relation_product_extra_mutation_csrf', $authorized->renderedData), $method . ' render data does not expose another mutation scope');

    $denied = mrpb_fixture('GET', [], false, []);
    $deniedResponse = null;
    try {
        $id === null ? $denied->{$method}() : $denied->{$method}($id);
    } catch (MasterRelationBundleSmokeResponse $exception) {
        $deniedResponse = $exception;
    }
    mrpb_check($deniedResponse instanceof MasterRelationBundleSmokeResponse && $deniedResponse->getCode() === 403, $method . ' denied render is blocked by RBAC');
    mrpb_check($denied->session->reads === [] && $denied->session->writes === [] && $denied->db->calls === [] && $denied->Master_model->calls === [], $method . ' denied render generates no token and reads no business data');
}

$detail = mrpb_fixture('GET', [], true, []);
$detail->product_bundle(91);
mrpb_check($detail->permissionCalls === [[MRPB_PAGE, 'view']] && !array_key_exists(MRPB_FIELD, $detail->renderedData), 'detail render remains mutation-token-free');
mrpb_check($detail->session->reads === [] && $detail->session->writes === [], 'detail render neither reads nor generates bundle token');

mrpb_check(substr_count($editViewSource, 'name="' . MRPB_FIELD . '"') === 1, 'edit source has exactly one bundle token field');
mrpb_check(strpos($editViewSource, '<form method="post"') < strpos($editViewSource, 'name="' . MRPB_FIELD . '"') && strpos($editViewSource, 'name="' . MRPB_FIELD . '"') < strpos($editViewSource, 'name="lines_json"'), 'edit token is inside POST form before business payload');
mrpb_check(strpos($hubViewSource, 'href="<?php echo site_url(\'master/relation/product-bundle/toggle/') === false, 'hub source has no toggle GET anchor');

$viewHost = new MasterRelationBundleSmokeViewHost();
$editHtml = $viewHost->render($editViewPath, [
    'title' => 'Fixture Bundle',
    'bundle' => [],
    'rows' => [],
    'summary' => [],
    'save_url' => '/smoke/master/relation/product-bundle/create/save',
    'back_url' => '/smoke/master/relation/product-bundle',
    MRPB_FIELD => MRPB_TOKEN,
    'master_relation_product_recipe_mutation_csrf' => MRPB_WRONG,
]);
mrpb_check(substr_count($editHtml, 'name="' . MRPB_FIELD . '" value="' . MRPB_TOKEN . '"') === 1, 'rendered editor submits exactly one scoped token');
mrpb_check(strpos($editHtml, '<form method="post" action="/smoke/master/relation/product-bundle/create/save"') !== false, 'rendered editor remains a POST save form');
mrpb_check(strpos($editHtml, 'master_relation_product_recipe_mutation_csrf') === false && strpos($editHtml, MRPB_WRONG) === false, 'rendered editor does not leak cross-scope token');

$hubRows = [
    ['id' => 91, 'bundle_code' => 'BND-A', 'bundle_name' => 'Bundle A', 'is_active' => 1, 'pos_scope' => 'REGULAR'],
    ['id' => 92, 'bundle_code' => 'BND-B', 'bundle_name' => 'Bundle B', 'is_active' => 0, 'pos_scope' => 'EVENT'],
];
$hubHtml = $viewHost->render($hubViewPath, [
    'rows' => $hubRows,
    'filters' => [],
    'summary' => [],
    'product_division_options' => [],
    MRPB_FIELD => MRPB_TOKEN,
    'master_relation_component_formula_mutation_csrf' => MRPB_WRONG,
]);
mrpb_check(substr_count($hubHtml, '<form method="post" action="/smoke/master/relation/product-bundle/toggle/') === 2, 'rendered hub has one POST toggle form per bundle row');
mrpb_check(substr_count($hubHtml, 'name="' . MRPB_FIELD . '" value="' . MRPB_TOKEN . '"') === 2, 'each rendered toggle form carries scoped bundle token');
mrpb_check(substr_count($hubHtml, "onsubmit=\"return confirm('Ubah status bundle ini?')\"") === 2, 'each toggle POST keeps confirmation');
mrpb_check(strpos($hubHtml, 'href="/smoke/master/relation/product-bundle/toggle/') === false, 'rendered hub exposes no GET toggle fallback');
mrpb_check(strpos($hubHtml, 'href="/smoke/master/relation/product-bundle/91"') !== false && strpos($hubHtml, 'href="/smoke/master/relation/product-bundle/edit/91"') !== false, 'detail and edit links remain unchanged');
mrpb_check(strpos($hubHtml, 'master_relation_component_formula_mutation_csrf') === false && strpos($hubHtml, MRPB_WRONG) === false, 'rendered hub does not leak cross-scope token');

if ($failures !== []) {
    fwrite(STDERR, "Master relation product bundle mutation CSRF smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Checks: %d; failures: %d\n", $checks, count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Master relation product bundle mutation CSRF smoke passed (%d checks).\n", $checks));
