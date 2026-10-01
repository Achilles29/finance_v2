<?php
declare(strict_types=1);

defined('BASEPATH') || define('BASEPATH', __DIR__ . '/');

const MREGC_FIELD = 'master_relation_extra_group_checklist_mutation_csrf';
const MREGC_AJAX_FIELD = 'master_relation_extra_group_mutation_csrf';
const MREGC_AJAX_HEADER = 'X-Master-Extra-Group-Csrf';
const MREGC_PAGE = 'master.extra_group.index';
const MREGC_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const MREGC_WRONG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MREGC_CROSS_SCOPE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const MREGC_REVISION_FIELD = 'mapping_revision';
const MREGC_REVISION = '8be5d1cd5aa9371edfbf8a5438d843dce92aa0db71bd7c7e4600853eb9ac7d20';
const MREGC_EMPTY_REVISION = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

final class MasterRelationExtraGroupChecklistSmokeResponse extends RuntimeException
{
}

final class MasterRelationExtraGroupChecklistSmokeInput
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

final class MasterRelationExtraGroupChecklistSmokeSession
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

final class MasterRelationExtraGroupChecklistSmokeResult
{
    private array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function result_array(): array
    {
        return $this->rows;
    }

    public function row_array(): array
    {
        return $this->rows[0] ?? [];
    }
}

final class MasterRelationExtraGroupChecklistSmokeDb
{
    public array $calls = [];
    public array $validationGroupRows = [
        ['id' => 9, 'is_active' => 1],
    ];
    public array $validationProductRows = [
        ['id' => 9, 'is_active' => 1, 'product_division_id' => 4],
        ['id' => 31, 'is_active' => 1, 'product_division_id' => 4],
        ['id' => 32, 'is_active' => 1, 'product_division_id' => 4],
    ];
    public array $lockedGroup = [
        'id' => 9,
        'product_division_id' => 4,
        'is_active' => 1,
    ];
    public array $mappingRows = [
        ['product_id' => 31, 'sort_order' => 10],
    ];
    public bool $transBeginResult = true;
    public bool $transCommitResult = true;
    public bool $transStatusResult = true;
    public bool $transactionActive = false;
    private string $fromTable = '';

    public function __call($name, $arguments)
    {
        $name = (string)$name;
        $this->calls[] = [$name, $arguments];
        if ($name === 'from') {
            $this->fromTable = (string)($arguments[0] ?? '');
            return $this;
        }
        if ($name === 'trans_begin') {
            $this->transactionActive = $this->transBeginResult;
            return $this->transBeginResult;
        }
        if ($name === 'trans_commit') {
            if ($this->transCommitResult) {
                $this->transactionActive = false;
            }
            return $this->transCommitResult;
        }
        if ($name === 'trans_rollback') {
            $this->transactionActive = false;
            return true;
        }
        if ($name === 'query') {
            $sql = (string)($arguments[0] ?? '');
            if (stripos($sql, 'FROM mst_extra_group ') !== false) {
                return new MasterRelationExtraGroupChecklistSmokeResult($this->lockedGroup === [] ? [] : [$this->lockedGroup]);
            }
            if (stripos($sql, 'FROM mst_product_extra_map ') !== false) {
                return new MasterRelationExtraGroupChecklistSmokeResult($this->mappingRows);
            }
            return new MasterRelationExtraGroupChecklistSmokeResult();
        }
        if ($name === 'get') {
            $table = $this->fromTable;
            $this->fromTable = '';
            if ($table === 'mst_product') {
                return new MasterRelationExtraGroupChecklistSmokeResult($this->validationProductRows);
            }
            if ($table === 'mst_extra_group') {
                return new MasterRelationExtraGroupChecklistSmokeResult($this->validationGroupRows);
            }
            if ($table === 'mst_product_extra_map') {
                return new MasterRelationExtraGroupChecklistSmokeResult($this->mappingRows);
            }
            if ($table === 'mst_product p') {
                return new MasterRelationExtraGroupChecklistSmokeResult([[
                    'id' => 31,
                    'product_code' => 'PRD-31',
                    'product_name' => 'Fixture Product',
                    'product_division_name' => 'Kitchen',
                    'map_id' => 61,
                    'map_sort_order' => 10,
                ]]);
            }
            if ($table === 'mst_extra_group g') {
                return new MasterRelationExtraGroupChecklistSmokeResult([[
                    'id' => 9,
                    'group_code' => 'GRP-9',
                    'group_name' => 'Fixture Group',
                    'is_required' => 1,
                    'product_division_name' => 'Kitchen',
                    'map_id' => 51,
                    'map_sort_order' => 10,
                ]]);
            }
            return new MasterRelationExtraGroupChecklistSmokeResult();
        }
        if ($name === 'trans_status') {
            return $this->transStatusResult;
        }
        if ($name === 'trans_active') {
            return $this->transactionActive;
        }
        return $this;
    }
}

final class MasterRelationExtraGroupChecklistSmokeModel
{
    public array $calls = [];
    public array $extra = [
        'id' => 9,
        'extra_code' => 'EXT-9',
        'extra_name' => 'Fixture Extra',
        'source_kind' => 'MASTER',
        'is_active' => 1,
    ];
    public array $group = [
        'id' => 9,
        'group_code' => 'GRP-9',
        'group_name' => 'Fixture Group',
        'product_division_id' => 4,
        'is_active' => 1,
    ];

    public function get_by_id($table, $id): array
    {
        $table = (string)$table;
        $this->calls[] = ['get_by_id', $table, (int)$id];
        if ($table === 'mst_extra') {
            return $this->extra;
        }
        return $this->group;
    }

    public function insert($table, array $payload): int
    {
        $this->calls[] = ['insert', (string)$table, $payload];
        return 1;
    }
}

class MY_Controller
{
    public MasterRelationExtraGroupChecklistSmokeInput $input;
    public MasterRelationExtraGroupChecklistSmokeSession $session;
    public MasterRelationExtraGroupChecklistSmokeDb $db;
    public MasterRelationExtraGroupChecklistSmokeModel $Master_model;
    public array $permissionCalls = [];
    public array $canCalls = [];
    public bool $permissionAllowed = true;
    public bool $editAllowed = false;
    public ?string $renderedView = null;
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new MasterRelationExtraGroupChecklistSmokeResponse('Forbidden', 403);
        }
    }

    protected function can(string $pageCode, string $action = 'view'): bool
    {
        $this->canCalls[] = [$pageCode, $action];
        return $action === 'edit' ? $this->editAllowed : $this->permissionAllowed;
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
    }
}

function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationExtraGroupChecklistSmokeResponse((string)$message, (int)$statusCode);
}

function show_404(): void
{
    throw new MasterRelationExtraGroupChecklistSmokeResponse('Not Found', 404);
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
    $GLOBALS['mregc_redirects'][] = [(string)$uri, (string)$method, $code];
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

final class MasterRelationExtraGroupChecklistSmokeViewLoader
{
    public function view($view, array $data = []): void
    {
    }
}

final class MasterRelationExtraGroupChecklistSmokeViewHost
{
    public MasterRelationExtraGroupChecklistSmokeViewLoader $load;

    public function __construct()
    {
        $this->load = new MasterRelationExtraGroupChecklistSmokeViewLoader();
    }

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

function mregc_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function mregc_method_source(string $source, string $method): string
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

function mregc_fixture(
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    bool $editAllowed = true,
    array $sessionValues = [MREGC_FIELD => MREGC_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): Master_relation {
    $reflection = new ReflectionClass(Master_relation::class);
    /** @var Master_relation&MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationExtraGroupChecklistSmokeInput($method, $post, $get, $headers, $rawBody);
    $controller->session = new MasterRelationExtraGroupChecklistSmokeSession($sessionValues);
    $controller->db = new MasterRelationExtraGroupChecklistSmokeDb();
    $controller->Master_model = new MasterRelationExtraGroupChecklistSmokeModel();
    $controller->permissionAllowed = $permissionAllowed;
    $controller->editAllowed = $editAllowed;
    return $controller;
}

function mregc_invoke_writer(
    string $writer,
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MREGC_FIELD => MREGC_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    $GLOBALS['mregc_redirects'] = [];
    $controller = mregc_fixture($method, $post, $permissionAllowed, true, $sessionValues, $get, $headers, $rawBody);
    $response = null;
    try {
        $controller->{$writer}(9);
    } catch (MasterRelationExtraGroupChecklistSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

function mregc_has_call(array $calls, string $method, ?string $table = null): bool
{
    foreach ($calls as $call) {
        if (($call[0] ?? '') !== $method) {
            continue;
        }
        $recordedTable = is_array($call[1] ?? null) ? (string)($call[1][0] ?? '') : (string)($call[1] ?? '');
        if ($table === null || $recordedTable === $table) {
            return true;
        }
    }
    return false;
}

function mregc_call_position(array $calls, string $method, ?string $table = null): int
{
    foreach ($calls as $index => $call) {
        if (($call[0] ?? '') !== $method) {
            continue;
        }
        $recordedTable = is_array($call[1] ?? null) ? (string)($call[1][0] ?? '') : (string)($call[1] ?? '');
        if ($table === null || $recordedTable === $table) {
            return (int)$index;
        }
    }
    return -1;
}

function mregc_query_position(array $calls, string $needle): int
{
    foreach ($calls as $index => $call) {
        if (($call[0] ?? '') !== 'query') {
            continue;
        }
        $sql = (string)($call[1][0] ?? '');
        if (stripos($sql, $needle) !== false) {
            return (int)$index;
        }
    }
    return -1;
}

function mregc_assert_zero_business(Master_relation $controller, string $label): void
{
    mregc_check($controller->db->calls === [], $label . ' performs zero DB/transaction calls');
    mregc_check($controller->Master_model->calls === [], $label . ' performs zero parent lookup/model writes');
    mregc_check($controller->session->flashes === [], $label . ' performs zero flash writes');
}

function mregc_product_inserts(Master_relation $controller): array
{
    $payloads = [];
    foreach ($controller->Master_model->calls as $call) {
        if (($call[0] ?? '') === 'insert' && ($call[1] ?? '') === 'mst_product_extra_map') {
            $payloads[] = $call[2] ?? [];
        }
    }
    return $payloads;
}

function mregc_item_inserts(Master_relation $controller): array
{
    $payloads = [];
    foreach ($controller->Master_model->calls as $call) {
        if (($call[0] ?? '') === 'insert' && ($call[1] ?? '') === 'mst_extra_group_item') {
            $payloads[] = $call[2] ?? [];
        }
    }
    return $payloads;
}

function mregc_invoke_item_case(array $post, array $extra, array $groupRows): array
{
    $GLOBALS['mregc_redirects'] = [];
    $controller = mregc_fixture(
        'POST',
        [MREGC_FIELD => MREGC_TOKEN] + $post,
        true,
        true,
        [MREGC_FIELD => MREGC_TOKEN]
    );
    $controller->Master_model->extra = $extra;
    $controller->db->validationGroupRows = $groupRows;
    $response = null;
    try {
        $controller->extra_item_groups_save(9);
    } catch (MasterRelationExtraGroupChecklistSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

function mregc_assert_item_rejected(Master_relation $controller, $response, string $label): void
{
    mregc_check($response === null, $label . ' is handled by form flash and redirect');
    mregc_check(
        count($controller->session->flashes) === 1
            && ($controller->session->flashes[0][0] ?? '') === 'error',
        $label . ' sets one error flash'
    );
    mregc_check(
        ($GLOBALS['mregc_redirects'] ?? []) === [['master/relation/extra-item-group/9', 'auto', null]],
        $label . ' redirects to the unfiltered checklist'
    );
    mregc_check(
        !mregc_has_call($controller->db->calls, 'trans_start')
            && !mregc_has_call($controller->db->calls, 'trans_complete')
            && !mregc_has_call($controller->db->calls, 'delete', 'mst_extra_group_item')
            && mregc_item_inserts($controller) === [],
        $label . ' is rejected before transaction, delete, or insert'
    );
}

function mregc_invoke_product_case(array $post, array $group, array $productRows): array
{
    $GLOBALS['mregc_redirects'] = [];
    $controller = mregc_fixture(
        'POST',
        array_merge([MREGC_FIELD => MREGC_TOKEN, MREGC_REVISION_FIELD => MREGC_REVISION], $post),
        true,
        true,
        [MREGC_FIELD => MREGC_TOKEN]
    );
    $controller->Master_model->group = $group;
    $controller->db->lockedGroup = $group;
    $controller->db->validationProductRows = $productRows;
    $response = null;
    try {
        $controller->extra_group_products_save(9);
    } catch (MasterRelationExtraGroupChecklistSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

function mregc_assert_product_rejected(Master_relation $controller, $response, string $label): void
{
    mregc_check($response === null, $label . ' is handled by form flash and redirect');
    mregc_check(
        count($controller->session->flashes) === 1
            && ($controller->session->flashes[0][0] ?? '') === 'error',
        $label . ' sets one error flash'
    );
    mregc_check(
        ($GLOBALS['mregc_redirects'] ?? []) === [['master/relation/extra-group/9', 'auto', null]],
        $label . ' redirects to the unfiltered checklist'
    );
    mregc_check(
        mregc_has_call($controller->db->calls, 'trans_begin')
            && mregc_has_call($controller->db->calls, 'trans_rollback')
            && !mregc_has_call($controller->db->calls, 'delete', 'mst_product_extra_map')
            && mregc_product_inserts($controller) === [],
        $label . ' rolls back after revision check and before delete or insert'
    );
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Master_relation.php';
$productViewPath = $root . '/application/views/master/extra_group_products.php';
$groupViewPath = $root . '/application/views/master/extra_item_groups.php';
$controllerSource = (string)file_get_contents($controllerPath);
$productViewSource = (string)file_get_contents($productViewPath);
$groupViewSource = (string)file_get_contents($groupViewPath);

mregc_check(
    strpos($controllerSource, "EXTRA_GROUP_CHECKLIST_MUTATION_CSRF_SESSION_KEY = '" . MREGC_FIELD . "'") !== false
        && strpos($controllerSource, "EXTRA_GROUP_CHECKLIST_MUTATION_CSRF_FORM_FIELD = '" . MREGC_FIELD . "'") !== false,
    'controller declares the isolated checklist session and form keys'
);

$tokenSource = mregc_method_source($controllerSource, 'extraGroupChecklistMutationCsrf');
$editorTokenSource = mregc_method_source($controllerSource, 'extraGroupChecklistMutationCsrfForEditor');
$guardSource = mregc_method_source($controllerSource, 'requireExtraGroupChecklistMutationCsrf');
mregc_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'checklist token helper generates strict lowercase 64-hex tokens'
);
mregc_check(
    strpos($editorTokenSource, "can('master.extra_group.index', 'edit')") !== false
        && strpos($editorTokenSource, 'extraGroupChecklistMutationCsrf()') > strpos($editorTokenSource, "can('master.extra_group.index', 'edit')"),
    'checklist token issuance is gated by exact edit permission'
);
mregc_check(
    strpos($guardSource, "method(true) !== 'POST'") < strpos($guardSource, '->post(')
        && strpos($guardSource, '->post(') < strpos($guardSource, 'hash_equals(')
        && strpos($guardSource, ', 405,') !== false
        && strpos($guardSource, ', 403,') !== false,
    'checklist guard orders POST-only, exact form token, and constant-time match'
);
mregc_check(
    strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'get_request_header') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'json_decode') === false,
    'checklist guard has no query, header, raw-body, or JSON fallback'
);

$writers = [
    'extra_group_products_save' => ['product_ids', 'mst_product_extra_map', 'master/relation/extra-group/9'],
    'extra_item_groups_save' => ['group_ids', 'mst_extra_group_item', 'master/relation/extra-item-group/9'],
];
foreach ($writers as $writer => [$payloadField, $table, $redirectPath]) {
    $source = mregc_method_source($controllerSource, $writer);
    $permissionAt = strpos($source, "requireRelationPermission('extra-group', 'edit')");
    $guardAt = strpos($source, 'requireExtraGroupChecklistMutationCsrf()');
    $queryAt = strpos($source, "get('q', true)");
    $parentAt = strpos($source, 'get_by_id(');
    $payloadAt = strpos($source, 'post(null, false)');
    $transactionAt = strpos($source, 'trans_start(');
    if ($writer === 'extra_group_products_save') {
        $revisionAt = strpos($source, 'EXTRA_GROUP_PRODUCT_MAPPING_REVISION_FIELD');
        mregc_check(
            $permissionAt !== false && $guardAt !== false && $queryAt !== false && $revisionAt !== false && $payloadAt !== false
                && $permissionAt < $guardAt && $guardAt < $queryAt && $queryAt < $revisionAt && $revisionAt < $payloadAt,
            $writer . ' orders edit RBAC, POST/form CSRF, query filter guard, strict revision, then payload'
        );
    } else {
        mregc_check(
            $permissionAt !== false && $guardAt !== false && $queryAt !== false && $parentAt !== false && $payloadAt !== false && $transactionAt !== false
                && $permissionAt < $guardAt && $guardAt < $queryAt && $queryAt < $parentAt && $parentAt < $payloadAt && $payloadAt < $transactionAt,
            $writer . ' orders edit RBAC, POST/form CSRF, query filter guard, parent, payload, then transaction'
        );
    }

    [$denied, $deniedResponse] = mregc_invoke_writer($writer, 'POST', [MREGC_FIELD => MREGC_TOKEN, $payloadField => [9]], false);
    mregc_check($deniedResponse instanceof MasterRelationExtraGroupChecklistSmokeResponse && $deniedResponse->getCode() === 403, $writer . ' RBAC denial returns HTTP 403');
    mregc_check($denied->permissionCalls === [[MREGC_PAGE, 'edit']], $writer . ' uses exact edit RBAC');
    mregc_check($denied->input->events === [] && $denied->session->reads === [], $writer . ' RBAC denial precedes method/token reads');
    mregc_assert_zero_business($denied, $writer . ' RBAC denial');

    foreach (['GET', 'PUT', 'DELETE'] as $method) {
        [$controller, $response] = mregc_invoke_writer($writer, $method, [MREGC_FIELD => MREGC_TOKEN, $payloadField => [9]]);
        mregc_check($response instanceof MasterRelationExtraGroupChecklistSmokeResponse && $response->getCode() === 405, $writer . ' rejects ' . $method . ' with HTTP 405');
        mregc_check($controller->permissionCalls === [[MREGC_PAGE, 'edit']], $writer . ' ' . $method . ' checks edit RBAC first');
        mregc_check($controller->input->postReads === [] && $controller->session->reads === [], $writer . ' ' . $method . ' reads no token/payload');
        mregc_assert_zero_business($controller, $writer . ' ' . $method);
    }

    $rejections = [
        'missing form token despite query/header/JSON alternatives' => [
            [$payloadField => [9], 'csrf_token' => MREGC_TOKEN],
            [MREGC_FIELD => MREGC_TOKEN],
            [MREGC_AJAX_HEADER => MREGC_TOKEN, 'X-CSRF-Token' => MREGC_TOKEN],
            json_encode([MREGC_FIELD => MREGC_TOKEN]),
        ],
        'malformed form token' => [[MREGC_FIELD => 'NOT-LOWERCASE-HEX', $payloadField => [9]], [], [], ''],
        'wrong form token' => [[MREGC_FIELD => MREGC_WRONG, $payloadField => [9]], [], [], ''],
        'cross-scope AJAX token' => [[MREGC_FIELD => MREGC_CROSS_SCOPE, $payloadField => [9]], [], [MREGC_AJAX_HEADER => MREGC_CROSS_SCOPE], ''],
    ];
    foreach ($rejections as $label => [$post, $get, $headers, $raw]) {
        [$controller, $response] = mregc_invoke_writer(
            $writer,
            'POST',
            $post,
            true,
            [MREGC_FIELD => MREGC_TOKEN, MREGC_AJAX_FIELD => MREGC_CROSS_SCOPE],
            $get,
            $headers,
            (string)$raw
        );
        mregc_check($response instanceof MasterRelationExtraGroupChecklistSmokeResponse && $response->getCode() === 403, $writer . ' rejects ' . $label . ' with HTTP 403');
        mregc_check($controller->input->postReads === [MREGC_FIELD], $writer . ' ' . $label . ' reads only exact checklist form field');
        mregc_check($controller->session->reads === [MREGC_FIELD], $writer . ' ' . $label . ' reads only checklist session scope');
        mregc_check($controller->input->getReads === [] && $controller->input->headerReads === [] && $controller->input->rawReads === 0, $writer . ' ' . $label . ' ignores query/header/JSON channels');
        mregc_assert_zero_business($controller, $writer . ' ' . $label);
    }

    $validPost = [MREGC_FIELD => MREGC_TOKEN, $payloadField => [9]];
    if ($writer === 'extra_group_products_save') {
        $validPost[MREGC_REVISION_FIELD] = MREGC_REVISION;
    }
    [$valid, $validResponse] = mregc_invoke_writer($writer, 'POST', $validPost);
    mregc_check($validResponse === null, $writer . ' accepts matching form/session token');
    $hasTransactionBoundary = $writer === 'extra_group_products_save'
        ? mregc_has_call($valid->db->calls, 'trans_begin') && mregc_has_call($valid->db->calls, 'trans_commit')
        : mregc_has_call($valid->db->calls, 'trans_start') && mregc_has_call($valid->db->calls, 'trans_complete');
    mregc_check($hasTransactionBoundary, $writer . ' valid token reaches transaction boundary');
    mregc_check(mregc_has_call($valid->db->calls, 'delete', $table), $writer . ' valid token reaches replace-all delete');
    mregc_check(mregc_has_call($valid->Master_model->calls, 'insert', $table), $writer . ' valid token reaches expected mapping insert');

    [$filtered, $filteredResponse] = mregc_invoke_writer(
        $writer,
        'POST',
        [MREGC_FIELD => MREGC_TOKEN, $payloadField => [9], 'q' => ''],
        true,
        [MREGC_FIELD => MREGC_TOKEN],
        ['q' => '  susu & madu  ']
    );
    mregc_check($filteredResponse === null, $writer . ' filtered POST is handled by warning redirect');
    mregc_check($filtered->input->postReads === [MREGC_FIELD] && $filtered->input->getReads === ['q'], $writer . ' filtered POST reads token then query q without payload/body q');
    mregc_check($filtered->db->calls === [] && $filtered->Master_model->calls === [], $writer . ' filtered POST performs no parent lookup, DB, model, or transaction work');
    mregc_check(
        count($filtered->session->flashes) === 1
            && ($filtered->session->flashes[0][0] ?? '') === 'warning'
            && strpos((string)($filtered->session->flashes[0][1] ?? ''), 'Reset filter') !== false,
        $writer . ' filtered POST sets a clear reset-filter warning'
    );
    mregc_check(
        ($GLOBALS['mregc_redirects'] ?? []) === [[$redirectPath . '?q=susu%20%26%20madu', 'auto', null]],
        $writer . ' filtered POST preserves trimmed q with safe URL encoding'
    );

    [$clearAll, $clearAllResponse] = mregc_invoke_writer(
        $writer,
        'POST',
        [MREGC_FIELD => MREGC_TOKEN, MREGC_REVISION_FIELD => MREGC_REVISION, 'q' => 'body-filter-must-not-apply'],
        true,
        [MREGC_FIELD => MREGC_TOKEN],
        ['q' => '   ']
    );
    mregc_check($clearAllResponse === null, $writer . ' accepts q-empty clear-all POST');
    $expectedPostReads = $writer === 'extra_group_products_save'
        ? [MREGC_FIELD, MREGC_REVISION_FIELD, '*']
        : [MREGC_FIELD, '*'];
    mregc_check($clearAll->input->getReads === ['q'] && $clearAll->input->postReads === $expectedPostReads, $writer . ' uses query q context and never selects body q as filter context');
    $clearAllHasTransaction = $writer === 'extra_group_products_save'
        ? mregc_has_call($clearAll->db->calls, 'trans_begin') && mregc_has_call($clearAll->db->calls, 'trans_commit')
        : mregc_has_call($clearAll->db->calls, 'trans_start') && mregc_has_call($clearAll->db->calls, 'trans_complete');
    mregc_check($clearAllHasTransaction, $writer . ' q-empty clear-all reaches transaction boundary');
    mregc_check(mregc_has_call($clearAll->db->calls, 'delete', $table), $writer . ' q-empty clear-all retains replace-all delete contract');
    mregc_check(!mregc_has_call($clearAll->Master_model->calls, 'insert', $table), $writer . ' q-empty clear-all inserts no mappings');
}

foreach (['missing revision' => null, 'malformed revision' => 'NOT-A-REVISION'] as $label => $revision) {
    $post = [MREGC_FIELD => MREGC_TOKEN, 'product_ids' => [31]];
    if ($revision !== null) {
        $post[MREGC_REVISION_FIELD] = $revision;
    }
    [$rejectedRevision, $rejectedRevisionResponse] = mregc_invoke_writer('extra_group_products_save', 'POST', $post);
    mregc_check($rejectedRevisionResponse === null, 'form product writer handles ' . $label . ' by flash and redirect');
    mregc_check(
        $rejectedRevision->input->postReads === [MREGC_FIELD, MREGC_REVISION_FIELD]
            && $rejectedRevision->db->calls === []
            && $rejectedRevision->Master_model->calls === [],
        'form product writer rejects ' . $label . ' before payload, lookup, or DB work'
    );
    mregc_check(
        count($rejectedRevision->session->flashes) === 1
            && ($rejectedRevision->session->flashes[0][0] ?? '') === 'error'
            && ($GLOBALS['mregc_redirects'] ?? []) === [['master/relation/extra-group/9', 'auto', null]],
        'form product writer gives reload guidance for ' . $label
    );
}

[$conflict, $conflictResponse] = mregc_invoke_product_case(
    [MREGC_REVISION_FIELD => MREGC_EMPTY_REVISION, 'product_ids' => [31]],
    [
        'id' => 9,
        'group_code' => 'GRP-9',
        'group_name' => 'Fixture Group',
        'product_division_id' => 4,
        'is_active' => 1,
    ],
    [['id' => 31, 'is_active' => 1, 'product_division_id' => 4]]
);
mregc_check($conflictResponse === null, 'form stale revision is handled without an exception response');
mregc_check(
    mregc_has_call($conflict->db->calls, 'trans_begin')
        && mregc_has_call($conflict->db->calls, 'query')
        && mregc_has_call($conflict->db->calls, 'trans_rollback')
        && !mregc_has_call($conflict->db->calls, 'delete', 'mst_product_extra_map')
        && mregc_product_inserts($conflict) === [],
    'form stale revision locks and rolls back without mapping DML'
);
mregc_check(
    count($conflict->session->flashes) === 1
        && ($conflict->session->flashes[0][0] ?? '') === 'warning'
        && strpos((string)($conflict->session->flashes[0][1] ?? ''), 'Muat ulang') !== false,
    'form stale revision requests a reload'
);

$replaceSource = mregc_method_source($controllerSource, 'replaceExtraGroupProducts');
$mappingLockSource = mregc_method_source($controllerSource, 'lockExtraGroupProductMappingRows');
$parentLockAt = strpos($replaceSource, 'FROM mst_extra_group WHERE id = ? FOR UPDATE');
$mappingLockAt = strpos($replaceSource, 'lockExtraGroupProductMappingRows(');
$revisionAt = strpos($replaceSource, 'canonicalExtraGroupProductMappingRevision($lockedMappingRows)');
$deleteAt = strpos($replaceSource, "delete('mst_product_extra_map')");
mregc_check(
    strpos($mappingLockSource, 'SELECT product_id, sort_order FROM mst_product_extra_map') !== false
        && strpos($mappingLockSource, 'FOR UPDATE') !== false,
    'shared product replace has a dedicated mapping-row FOR UPDATE query'
);
mregc_check(
    $parentLockAt !== false && $mappingLockAt !== false && $revisionAt !== false && $deleteAt !== false
        && $parentLockAt < $mappingLockAt && $mappingLockAt < $revisionAt && $revisionAt < $deleteAt,
    'shared product replace locks parent then mapping rows and hashes locked revision before delete/insert'
);
mregc_check(
    strpos($replaceSource, 'if ($this->db->trans_begin() === false)') !== false
        && strpos($replaceSource, 'if ($this->db->trans_commit() === false)') !== false
        && strpos($replaceSource, 'if ($this->db->trans_active())') !== false,
    'shared product replace checks begin/commit return values and rolls back an active failed commit'
);
mregc_check(
    mregc_call_position($conflict->db->calls, 'query') >= 0
        && mregc_call_position($conflict->db->calls, 'query') < mregc_call_position($conflict->db->calls, 'trans_rollback'),
    'form conflict dynamically locks before rollback'
);

$activeDivisionGroup = [
    'id' => 9,
    'group_code' => 'GRP-9',
    'group_name' => 'Fixture Group',
    'product_division_id' => 4,
    'is_active' => 1,
];
$inactiveDivisionGroup = array_replace($activeDivisionGroup, ['is_active' => 0]);
$validDivisionProducts = [
    ['id' => 31, 'is_active' => 1, 'product_division_id' => 4],
    ['id' => 32, 'is_active' => 1, 'product_division_id' => 4],
];

[$deduped, $dedupedResponse] = mregc_invoke_product_case(
    ['product_ids' => ['31', 31, '32', '31']],
    $activeDivisionGroup,
    $validDivisionProducts
);
mregc_check($dedupedResponse === null, 'product checklist accepts a valid duplicate-bearing set');
mregc_check(mregc_has_call($deduped->db->calls, 'delete', 'mst_product_extra_map'), 'valid product checklist reaches replace-all delete');
mregc_check(
    mregc_query_position($deduped->db->calls, 'FROM mst_extra_group ') >= 0
        && mregc_query_position($deduped->db->calls, 'FROM mst_product_extra_map ') > mregc_query_position($deduped->db->calls, 'FROM mst_extra_group ')
        && mregc_query_position($deduped->db->calls, 'FROM mst_product_extra_map ') < mregc_call_position($deduped->db->calls, 'delete', 'mst_product_extra_map'),
    'valid product checklist locks parent then mapping rows before replace-all delete'
);
mregc_check(
    mregc_product_inserts($deduped) === [
        ['extra_group_id' => 9, 'product_id' => 31, 'sort_order' => 10],
        ['extra_group_id' => 9, 'product_id' => 32, 'sort_order' => 20],
    ],
    'valid product checklist deduplicates IDs and preserves 10-step sort order'
);

$GLOBALS['mregc_redirects'] = [];
$beginFailure = mregc_fixture(
    'POST',
    [MREGC_FIELD => MREGC_TOKEN, MREGC_REVISION_FIELD => MREGC_REVISION, 'product_ids' => [31]],
    true,
    true,
    [MREGC_FIELD => MREGC_TOKEN]
);
$beginFailure->db->transBeginResult = false;
$beginFailure->extra_group_products_save(9);
mregc_check(
    count($beginFailure->session->flashes) === 1
        && ($beginFailure->session->flashes[0] ?? []) === ['error', 'Gagal menyimpan mapping produk untuk group extra.']
        && ($GLOBALS['mregc_redirects'] ?? []) === [['master/relation/extra-group/9', 'auto', null]],
    'form begin=false returns generic error flash and redirect without success'
);
mregc_check(
    $beginFailure->db->calls === [['trans_begin', []]]
        && $beginFailure->Master_model->calls === []
        && mregc_product_inserts($beginFailure) === [],
    'form begin=false performs no lookup, mapping lock, delete, or insert'
);

$GLOBALS['mregc_redirects'] = [];
$commitFailure = mregc_fixture(
    'POST',
    [MREGC_FIELD => MREGC_TOKEN, MREGC_REVISION_FIELD => MREGC_REVISION, 'product_ids' => [31]],
    true,
    true,
    [MREGC_FIELD => MREGC_TOKEN]
);
$commitFailure->db->transCommitResult = false;
$commitFailure->extra_group_products_save(9);
mregc_check(
    count($commitFailure->session->flashes) === 1
        && ($commitFailure->session->flashes[0] ?? []) === ['error', 'Gagal menyimpan mapping produk untuk group extra.']
        && ($GLOBALS['mregc_redirects'] ?? []) === [['master/relation/extra-group/9', 'auto', null]],
    'form commit=false returns generic error flash and redirect without success'
);
mregc_check(
    mregc_call_position($commitFailure->db->calls, 'trans_commit') >= 0
        && mregc_call_position($commitFailure->db->calls, 'trans_active') > mregc_call_position($commitFailure->db->calls, 'trans_commit')
        && mregc_call_position($commitFailure->db->calls, 'trans_rollback') > mregc_call_position($commitFailure->db->calls, 'trans_active')
        && $commitFailure->db->transactionActive === false,
    'form commit=false rolls back the still-active transaction'
);

foreach ([
    'missing product_ids clears inactive group' => [],
    'empty product_ids clears inactive group' => ['product_ids' => []],
] as $label => $post) {
    [$clearInactive, $clearInactiveResponse] = mregc_invoke_product_case($post, $inactiveDivisionGroup, []);
    mregc_check($clearInactiveResponse === null, $label . ' is accepted');
    mregc_check(
        mregc_has_call($clearInactive->db->calls, 'trans_begin')
            && mregc_has_call($clearInactive->db->calls, 'trans_commit')
            && mregc_has_call($clearInactive->db->calls, 'delete', 'mst_product_extra_map')
            && mregc_product_inserts($clearInactive) === [],
        $label . ' reaches delete-only replace-all transaction'
    );
}

$invalidCases = [
    'scalar product_ids' => [['product_ids' => '31'], $activeDivisionGroup, $validDivisionProducts],
    'null product_ids' => [['product_ids' => null], $activeDivisionGroup, $validDivisionProducts],
    'malformed product ID' => [['product_ids' => ['31', 'abc']], $activeDivisionGroup, $validDivisionProducts],
    'zero product ID' => [['product_ids' => ['0']], $activeDivisionGroup, $validDivisionProducts],
    'negative product ID' => [['product_ids' => [-1]], $activeDivisionGroup, $validDivisionProducts],
    'nonexistent product ID' => [['product_ids' => [999]], $activeDivisionGroup, $validDivisionProducts],
    'inactive product ID' => [['product_ids' => [31]], $activeDivisionGroup, [['id' => 31, 'is_active' => 0, 'product_division_id' => 4]]],
    'wrong-division product ID' => [['product_ids' => [31]], $activeDivisionGroup, [['id' => 31, 'is_active' => 1, 'product_division_id' => 7]]],
    'inactive group nonempty set' => [['product_ids' => [31]], $inactiveDivisionGroup, $validDivisionProducts],
    'mixed valid and nonexistent product IDs' => [['product_ids' => [31, 999]], $activeDivisionGroup, $validDivisionProducts],
];
foreach ($invalidCases as $label => [$post, $group, $rows]) {
    [$invalid, $invalidResponse] = mregc_invoke_product_case($post, $group, $rows);
    mregc_assert_product_rejected($invalid, $invalidResponse, $label);
}

$crossDivisionGroup = array_replace($activeDivisionGroup, ['product_division_id' => null]);
$crossDivisionProducts = [
    ['id' => 31, 'is_active' => 1, 'product_division_id' => 4],
    ['id' => 33, 'is_active' => 1, 'product_division_id' => 7],
];
[$crossDivision, $crossDivisionResponse] = mregc_invoke_product_case(
    ['product_ids' => [31, 33]],
    $crossDivisionGroup,
    $crossDivisionProducts
);
mregc_check($crossDivisionResponse === null, 'NULL-division group accepts valid products across divisions');
mregc_check(
    count(mregc_product_inserts($crossDivision)) === 2
        && mregc_has_call($crossDivision->db->calls, 'delete', 'mst_product_extra_map'),
    'NULL-division cross-division set reaches atomic replace-all mutation'
);

[$missingParent, $missingParentResponse] = mregc_invoke_product_case(['product_ids' => []], [], []);
mregc_check(
    $missingParentResponse instanceof MasterRelationExtraGroupChecklistSmokeResponse
        && $missingParentResponse->getCode() === 404,
    'missing product-checklist parent retains HTTP 404 behavior'
);
mregc_check(
    mregc_has_call($missingParent->db->calls, 'trans_begin')
        && mregc_has_call($missingParent->db->calls, 'trans_rollback')
        && !mregc_has_call($missingParent->db->calls, 'delete', 'mst_product_extra_map')
        && mregc_product_inserts($missingParent) === [],
    'missing product-checklist parent rolls back lock transaction without mapping writes'
);

$itemValidatorSource = mregc_method_source($controllerSource, 'validateExtraGroupItemSelection');
$itemWriterSource = mregc_method_source($controllerSource, 'extra_item_groups_save');
mregc_check(
    strpos($itemValidatorSource, 'from($childTable)') !== false
        && strpos($itemValidatorSource, "where_in('id', \$selectedIds)") !== false
        && strpos($itemValidatorSource, 'get_by_id(') === false,
    'extra/group item validator performs one set-based child lookup'
);
mregc_check(
    strpos($itemWriterSource, 'validateExtraGroupItemSelection(') !== false
        && strpos($itemWriterSource, 'validateExtraGroupItemSelection(') < strpos($itemWriterSource, 'trans_start('),
    'extra-item group checklist completes validation before transaction start'
);

$activeExtra = [
    'id' => 9,
    'extra_code' => 'EXT-9',
    'extra_name' => 'Fixture Extra',
    'source_kind' => 'MASTER',
    'is_active' => 1,
];
$inactiveExtra = array_replace($activeExtra, ['is_active' => 0]);
$validGroups = [
    ['id' => 42, 'is_active' => 1, 'product_division_id' => 7],
    ['id' => 41, 'is_active' => 1, 'product_division_id' => 4],
];

[$itemDeduped, $itemDedupedResponse] = mregc_invoke_item_case(
    ['group_ids' => ['42', 42, '41', '42']],
    $activeExtra,
    $validGroups
);
mregc_check($itemDedupedResponse === null, 'extra-item checklist accepts a valid duplicate-bearing set');
mregc_check(
    mregc_item_inserts($itemDeduped) === [
        ['extra_group_id' => 42, 'extra_id' => 9, 'sort_order' => 10],
        ['extra_group_id' => 41, 'extra_id' => 9, 'sort_order' => 20],
    ],
    'extra-item checklist deduplicates in first-seen order with 10-step sorting'
);

foreach ([
    'active extra with absent group_ids' => [[], $activeExtra],
    'active extra with empty group_ids' => [['group_ids' => []], $activeExtra],
    'inactive extra with absent group_ids' => [[], $inactiveExtra],
    'inactive extra with empty group_ids' => [['group_ids' => []], $inactiveExtra],
] as $label => [$post, $extra]) {
    [$clearItem, $clearItemResponse] = mregc_invoke_item_case($post, $extra, []);
    mregc_check($clearItemResponse === null, $label . ' is accepted as explicit clear-all');
    mregc_check(
        mregc_has_call($clearItem->db->calls, 'trans_start')
            && mregc_has_call($clearItem->db->calls, 'delete', 'mst_extra_group_item')
            && mregc_item_inserts($clearItem) === [],
        $label . ' reaches delete-only replace-all transaction'
    );
}

$invalidItemCases = [
    'scalar group_ids' => [['group_ids' => '42'], $activeExtra, $validGroups],
    'null group_ids' => [['group_ids' => null], $activeExtra, $validGroups],
    'malformed group ID' => [['group_ids' => ['42', 'abc']], $activeExtra, $validGroups],
    'nested group ID' => [['group_ids' => [['42']]], $activeExtra, $validGroups],
    'zero group ID' => [['group_ids' => [0]], $activeExtra, $validGroups],
    'negative group ID' => [['group_ids' => [-1]], $activeExtra, $validGroups],
    'float group ID' => [['group_ids' => [42.0]], $activeExtra, $validGroups],
    'boolean group ID' => [['group_ids' => [true]], $activeExtra, $validGroups],
    'non-canonical numeric group ID' => [['group_ids' => ['042']], $activeExtra, $validGroups],
    'nonexistent group ID' => [['group_ids' => [999]], $activeExtra, []],
    'inactive group ID' => [['group_ids' => [42]], $activeExtra, [['id' => 42, 'is_active' => 0]]],
    'inactive extra nonempty set' => [['group_ids' => [42]], $inactiveExtra, $validGroups],
    'mixed valid and nonexistent group IDs' => [['group_ids' => [42, 999]], $activeExtra, [['id' => 42, 'is_active' => 1]]],
];
foreach ($invalidItemCases as $label => [$post, $extra, $rows]) {
    [$invalidItem, $invalidItemResponse] = mregc_invoke_item_case($post, $extra, $rows);
    mregc_assert_item_rejected($invalidItem, $invalidItemResponse, $label);
}

$crossDomainExtra = array_replace($activeExtra, ['source_kind' => 'PRODUCT']);
[$crossDomainItem, $crossDomainItemResponse] = mregc_invoke_item_case(
    ['group_ids' => [42, 41]],
    $crossDomainExtra,
    $validGroups
);
mregc_check($crossDomainItemResponse === null, 'extra-item checklist allows cross-division/source_kind mappings');
mregc_check(
    count(mregc_item_inserts($crossDomainItem)) === 2
        && mregc_has_call($crossDomainItem->db->calls, 'delete', 'mst_extra_group_item'),
    'cross-domain extra/group mapping reaches atomic replace-all mutation'
);

[$missingItemParent, $missingItemParentResponse] = mregc_invoke_item_case(['group_ids' => []], [], []);
mregc_check(
    $missingItemParentResponse instanceof MasterRelationExtraGroupChecklistSmokeResponse
        && $missingItemParentResponse->getCode() === 404,
    'missing extra-item parent retains HTTP 404 behavior'
);
mregc_check(
    $missingItemParent->db->calls === [] && mregc_item_inserts($missingItemParent) === [],
    'missing extra-item parent performs no transaction or mapping writes'
);

$reflection = new ReflectionClass(Master_relation::class);
$tokenHelper = $reflection->getMethod('extraGroupChecklistMutationCsrf');
$tokenHelper->setAccessible(true);
$newTokenController = mregc_fixture('GET', [], true, true, []);
$generated = (string)$tokenHelper->invoke($newTokenController);
mregc_check(preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1, 'missing checklist token generates strict lowercase 64-hex');
mregc_check(count($newTokenController->session->writes) === 1 && $newTokenController->session->writes[0][0] === MREGC_FIELD, 'generated token is stored only in checklist session scope');
$malformedTokenController = mregc_fixture('GET', [], true, true, [MREGC_FIELD => strtoupper(MREGC_TOKEN)]);
$regenerated = (string)$tokenHelper->invoke($malformedTokenController);
mregc_check(preg_match('/\A[0-9a-f]{64}\z/D', $regenerated) === 1 && $regenerated !== strtoupper(MREGC_TOKEN), 'uppercase session token is regenerated');

$revisionHelper = $reflection->getMethod('canonicalExtraGroupProductMappingRevision');
$revisionHelper->setAccessible(true);
$canonicalRevision = (string)$revisionHelper->invoke($newTokenController, [
    ['product_id' => 31, 'sort_order' => 10],
    ['product_id' => 7, 'sort_order' => 20],
]);
$reorderedRevision = (string)$revisionHelper->invoke($newTokenController, [
    ['sort_order' => 20, 'product_id' => 7],
    ['sort_order' => 10, 'product_id' => 31],
]);
$changedSortRevision = (string)$revisionHelper->invoke($newTokenController, [
    ['product_id' => 7, 'sort_order' => 10],
    ['product_id' => 31, 'sort_order' => 20],
]);
mregc_check(
    preg_match('/\A[0-9a-f]{64}\z/D', $canonicalRevision) === 1
        && hash_equals($canonicalRevision, $reorderedRevision)
        && !hash_equals($canonicalRevision, $changedSortRevision),
    'mapping revision helper is canonical, deterministic, and includes sort order'
);

$readers = [
    'extra_group_products' => 'master/extra_group_products',
    'extra_item_groups' => 'master/extra_item_groups',
];
foreach ($readers as $reader => $view) {
    $viewOnly = mregc_fixture('GET', [], true, false, []);
    $viewOnly->{$reader}(9);
    mregc_check($viewOnly->permissionCalls === [[MREGC_PAGE, 'view']], $reader . ' preserves view RBAC');
    mregc_check($viewOnly->canCalls === [[MREGC_PAGE, 'edit']], $reader . ' checks exact edit permission before token issuance');
    mregc_check($viewOnly->renderedView === $view && !array_key_exists(MREGC_FIELD, $viewOnly->renderedData), $reader . ' view-only render receives no token');
    mregc_check($viewOnly->session->reads === [] && $viewOnly->session->writes === [], $reader . ' view-only render does not touch checklist token session');

    $editor = mregc_fixture('GET', [], true, true, []);
    $editor->{$reader}(9);
    mregc_check($editor->permissionCalls === [[MREGC_PAGE, 'view']] && $editor->canCalls === [[MREGC_PAGE, 'edit']], $reader . ' editor keeps view plus exact edit check');
    mregc_check(preg_match('/\A[0-9a-f]{64}\z/D', (string)($editor->renderedData[MREGC_FIELD] ?? '')) === 1, $reader . ' editor render receives strict scoped token');
    mregc_check($editor->session->reads === [MREGC_FIELD] && count($editor->session->writes) === 1, $reader . ' editor token is session-bound');
    if ($reader === 'extra_group_products') {
        mregc_check(($editor->renderedData[MREGC_REVISION_FIELD] ?? '') === MREGC_REVISION, 'product checklist reader exposes revision for the full mapping snapshot');
    }

    $filteredEditor = mregc_fixture('GET', [], true, true, [], ['q' => '  susu & madu  ']);
    $filteredEditor->{$reader}(9);
    mregc_check($filteredEditor->permissionCalls === [[MREGC_PAGE, 'view']], $reader . ' filtered editor preserves view RBAC');
    mregc_check($filteredEditor->canCalls === [], $reader . ' filtered editor does not request edit-token eligibility');
    mregc_check($filteredEditor->renderedView === $view && !array_key_exists(MREGC_FIELD, $filteredEditor->renderedData), $reader . ' filtered editor render receives no token');
    mregc_check(($filteredEditor->renderedData['q'] ?? null) === 'susu & madu', $reader . ' filtered editor receives trimmed query context');
    mregc_check($filteredEditor->session->reads === [] && $filteredEditor->session->writes === [], $reader . ' filtered editor does not touch checklist token session');
}

foreach ([$productViewSource, $groupViewSource] as $index => $viewSource) {
    $label = $index === 0 ? 'product checklist view' : 'extra-group checklist view';
    mregc_check(strpos($viewSource, '<form method="post"') !== false, $label . ' retains explicit POST save form');
    mregc_check(substr_count($viewSource, 'name="' . MREGC_FIELD . '"') === 1, $label . ' source contains one exact scoped token field');
    mregc_check(strpos($viewSource, 'if ($canEditChecklist)') !== false && strpos($viewSource, "? '' : 'disabled'") !== false, $label . ' gates save controls and disables view-only checkboxes');
}
mregc_check(substr_count($productViewSource, 'name="' . MREGC_REVISION_FIELD . '"') === 1, 'product checklist source contains one hidden mapping revision');

$viewHost = new MasterRelationExtraGroupChecklistSmokeViewHost();
$viewFixtures = [
    [$productViewPath, [
        'title' => 'Product Checklist',
        'group' => ['id' => 9, 'group_name' => 'Fixture Group', 'product_division_id' => 4],
        'rows' => [['id' => 31, 'product_code' => 'PRD-31', 'product_name' => 'Fixture Product', 'product_division_name' => 'Kitchen']],
        'mapped_product_ids' => [31],
        MREGC_REVISION_FIELD => MREGC_REVISION,
        'q' => '',
    ], 'product_ids[]'],
    [$groupViewPath, [
        'title' => 'Group Checklist',
        'extra' => ['id' => 41, 'extra_code' => 'EXT-41', 'extra_name' => 'Fixture Extra'],
        'rows' => [['id' => 9, 'group_code' => 'GRP-9', 'group_name' => 'Fixture Group', 'product_division_name' => 'Kitchen', 'is_required' => 1]],
        'mapped_group_ids' => [9],
        'q' => '',
    ], 'group_ids[]'],
];
foreach ($viewFixtures as [$path, $data, $payloadName]) {
    $viewOnlyHtml = $viewHost->render($path, $data);
    mregc_check(strpos($viewOnlyHtml, 'name="' . MREGC_FIELD . '"') === false && strpos($viewOnlyHtml, MREGC_TOKEN) === false, basename($path) . ' view-only HTML exposes no token');
    mregc_check(strpos($viewOnlyHtml, 'Simpan Checklist') === false, basename($path) . ' view-only HTML hides save buttons');
    mregc_check(preg_match('/name="' . preg_quote($payloadName, '/') . '"[^>]*disabled/', $viewOnlyHtml) === 1, basename($path) . ' view-only HTML disables mapping checkbox');

    $editorHtml = $viewHost->render($path, $data + [MREGC_FIELD => MREGC_TOKEN, MREGC_AJAX_FIELD => MREGC_CROSS_SCOPE]);
    mregc_check(substr_count($editorHtml, 'name="' . MREGC_FIELD . '" value="' . MREGC_TOKEN . '"') === 1, basename($path) . ' editor HTML submits one checklist token');
    mregc_check(substr_count($editorHtml, 'Simpan Checklist') === 2, basename($path) . ' editor HTML exposes both save buttons');
    mregc_check(strpos($editorHtml, MREGC_CROSS_SCOPE) === false && strpos($editorHtml, 'name="' . MREGC_AJAX_FIELD . '"') === false, basename($path) . ' does not leak AJAX-scope token');
    if ($path === $productViewPath) {
        mregc_check(substr_count($editorHtml, 'name="' . MREGC_REVISION_FIELD . '" value="' . MREGC_REVISION . '"') === 1, 'product checklist editor submits one snapshot revision');
    }

    $filteredHtml = $viewHost->render($path, array_replace($data, ['q' => 'susu & madu', MREGC_FIELD => MREGC_TOKEN]));
    mregc_check(strpos($filteredHtml, 'name="' . MREGC_FIELD . '"') === false && strpos($filteredHtml, MREGC_TOKEN) === false, basename($path) . ' filtered editor HTML exposes no token');
    mregc_check(strpos($filteredHtml, 'Simpan Checklist') === false, basename($path) . ' filtered editor HTML hides all save buttons');
    mregc_check(strpos($filteredHtml, 'id="check_all_') === false, basename($path) . ' filtered editor HTML hides select-all control');
    mregc_check(preg_match('/name="' . preg_quote($payloadName, '/') . '"[^>]*disabled/', $filteredHtml) === 1, basename($path) . ' filtered editor HTML disables mapping checkbox');
    mregc_check(strpos($filteredHtml, 'Reset filter untuk mengubah checklist penuh') !== false, basename($path) . ' filtered editor HTML explains how to restore editing');

    $emptyEditorHtml = $viewHost->render($path, array_replace($data, ['rows' => [], MREGC_FIELD => MREGC_TOKEN]));
    mregc_check(substr_count($emptyEditorHtml, 'name="' . MREGC_FIELD . '" value="' . MREGC_TOKEN . '"') === 1, basename($path) . ' q-empty empty result retains checklist token');
    mregc_check(substr_count($emptyEditorHtml, 'Simpan Checklist') === 2, basename($path) . ' q-empty empty result retains both clear-all save buttons');
    mregc_check(strpos($emptyEditorHtml, 'check_all_') !== false, basename($path) . ' q-empty empty result retains select-all control');
}

$ajaxGuardSource = mregc_method_source($controllerSource, 'requireExtraGroupMutationCsrf');
mregc_check(
    strpos($controllerSource, "EXTRA_GROUP_MUTATION_CSRF_CI_HEADER = '" . MREGC_AJAX_HEADER . "'") !== false
        && strpos($ajaxGuardSource, 'get_request_header(self::EXTRA_GROUP_MUTATION_CSRF_CI_HEADER') !== false
        && strpos($ajaxGuardSource, '->post(') === false,
    'Batch 59 AJAX guard retains canonical header-only contract'
);
foreach (['extra_group_items_save_ajax', 'extra_group_products_save_ajax'] as $ajaxWriter) {
    $source = mregc_method_source($controllerSource, $ajaxWriter);
    mregc_check(strpos($source, 'requireExtraGroupMutationCsrf()') !== false && strpos($source, 'requireExtraGroupChecklistMutationCsrf()') === false, $ajaxWriter . ' remains on Batch 59 AJAX guard');
}

if ($failures !== []) {
    fwrite(STDERR, "Master relation extra-group checklist mutation CSRF smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Checks: %d; failures: %d\n", $checks, count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Master relation extra-group checklist mutation CSRF smoke passed (%d checks).\n", $checks));
