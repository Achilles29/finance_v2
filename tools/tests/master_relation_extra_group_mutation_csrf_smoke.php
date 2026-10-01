<?php
declare(strict_types=1);

defined('BASEPATH') || define('BASEPATH', __DIR__ . '/');

const MREG_FIELD = 'master_relation_extra_group_mutation_csrf';
const MREG_HEADER = 'X-Master-Extra-Group-Csrf';
const MREG_PAGE = 'master.extra_group.index';
const MREG_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const MREG_WRONG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MREG_CROSS_SCOPE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const MREG_REVISION_FIELD = 'mapping_revision';
const MREG_REVISION = '8be5d1cd5aa9371edfbf8a5438d843dce92aa0db71bd7c7e4600853eb9ac7d20';
const MREG_EMPTY_REVISION = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

final class MasterRelationExtraGroupSmokeResponse extends RuntimeException
{
}

final class MasterRelationExtraGroupSmokeOutput
{
    public int $status = 200;
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

final class MasterRelationExtraGroupSmokeInput
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
        $this->events[] = 'method';
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $label = $key === null ? '*' : (string)$key;
        $this->events[] = 'post:' . $label;
        $this->postReads[] = $label;
        return $key === null ? $this->postData : ($this->postData[$label] ?? null);
    }

    public function get($key = null, $xssClean = false)
    {
        $label = $key === null ? '*' : (string)$key;
        $this->events[] = 'get:' . $label;
        $this->getReads[] = $label;
        return $key === null ? $this->getData : ($this->getData[$label] ?? null);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $label = (string)$key;
        $this->events[] = 'header:' . $label;
        $this->headerReads[] = $label;
        return (string)($this->headers[$label] ?? '');
    }

    public function __get($name)
    {
        if ((string)$name !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$name);
        }
        $this->events[] = 'property:raw_input_stream';
        $this->rawReads++;
        return $this->rawBody;
    }
}

final class MasterRelationExtraGroupSmokeSession
{
    public array $values;
    public array $reads = [];
    public array $writes = [];

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
}

final class MasterRelationExtraGroupSmokeResult
{
    private array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
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

final class MasterRelationExtraGroupSmokeDb
{
    public array $calls = [];
    public array $validationExtraRows = [
        ['id' => 41, 'is_active' => 1, 'source_kind' => 'MASTER'],
    ];
    public array $validationProductRows = [
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
        if ($name === 'trans_status') {
            return $this->transStatusResult;
        }
        if ($name === 'trans_active') {
            return $this->transactionActive;
        }
        if ($name === 'query') {
            $sql = (string)($arguments[0] ?? '');
            if (stripos($sql, 'FROM mst_extra_group ') !== false) {
                return new MasterRelationExtraGroupSmokeResult($this->lockedGroup === [] ? [] : [$this->lockedGroup]);
            }
            if (stripos($sql, 'FROM mst_product_extra_map ') !== false) {
                return new MasterRelationExtraGroupSmokeResult($this->mappingRows);
            }
            return new MasterRelationExtraGroupSmokeResult();
        }
        if ($name === 'get') {
            $table = $this->fromTable;
            $this->fromTable = '';
            if ($table === 'mst_product') {
                return new MasterRelationExtraGroupSmokeResult($this->validationProductRows);
            }
            if ($table === 'mst_extra') {
                return new MasterRelationExtraGroupSmokeResult($this->validationExtraRows);
            }
            if ($table === 'mst_product_extra_map') {
                return new MasterRelationExtraGroupSmokeResult($this->mappingRows);
            }
            if ($table === 'mst_extra e') {
                return new MasterRelationExtraGroupSmokeResult([[
                    'id' => 41,
                    'extra_code' => 'EXT-41',
                    'extra_name' => 'Fixture Extra',
                    'extra_type' => 'CHOICE',
                    'source_kind' => 'MASTER',
                    'map_id' => 51,
                    'map_sort_order' => 10,
                    'mapped' => true,
                ]]);
            }
            if ($table === 'mst_product p') {
                return new MasterRelationExtraGroupSmokeResult([[
                    'id' => 31,
                    'product_code' => 'PRD-31',
                    'product_name' => 'Fixture Product',
                    'product_division_name' => 'Kitchen',
                    'classification_name' => 'Main',
                    'product_category_name' => 'Food',
                    'map_id' => 61,
                    'map_sort_order' => 10,
                    'mapped' => true,
                ]]);
            }
            return new MasterRelationExtraGroupSmokeResult();
        }
        return $this;
    }
}

final class MasterRelationExtraGroupSmokeModel
{
    public array $calls = [];
    public array $group = [
        'id' => 9,
        'group_code' => 'GRP-9',
        'group_name' => 'Fixture Group',
        'product_division_id' => 4,
        'is_active' => 1,
    ];

    public function get_by_id($table, $id): array
    {
        $this->calls[] = ['get_by_id', (string)$table, (int)$id];
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
    public MasterRelationExtraGroupSmokeInput $input;
    public MasterRelationExtraGroupSmokeSession $session;
    public MasterRelationExtraGroupSmokeDb $db;
    public MasterRelationExtraGroupSmokeModel $Master_model;
    public MasterRelationExtraGroupSmokeOutput $output;
    public array $permissionCalls = [];
    public array $canCalls = [];
    public bool $permissionAllowed = true;
    public bool $editAllowed = false;

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new MasterRelationExtraGroupSmokeResponse('Forbidden', 403);
        }
    }

    protected function can(string $pageCode, string $action = 'view'): bool
    {
        $this->canCalls[] = [$pageCode, $action];
        return $action === 'edit' ? $this->editAllowed : $this->permissionAllowed;
    }
}

function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationExtraGroupSmokeResponse((string)$message, (int)$statusCode);
}

function show_404(): void
{
    throw new MasterRelationExtraGroupSmokeResponse('Not Found', 404);
}

require dirname(__DIR__, 2) . '/application/controllers/Master_relation.php';

$checks = 0;
$failures = [];

function mreg_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function mreg_method_source(string $source, string $method): string
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

function mreg_fixture(
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    bool $editAllowed = false,
    array $sessionValues = [],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): Master_relation {
    $reflection = new ReflectionClass(Master_relation::class);
    /** @var Master_relation&MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationExtraGroupSmokeInput($method, $post, $get, $headers, $rawBody);
    $controller->session = new MasterRelationExtraGroupSmokeSession($sessionValues);
    $controller->db = new MasterRelationExtraGroupSmokeDb();
    $controller->Master_model = new MasterRelationExtraGroupSmokeModel();
    $controller->output = new MasterRelationExtraGroupSmokeOutput();
    $controller->permissionAllowed = $permissionAllowed;
    $controller->editAllowed = $editAllowed;
    return $controller;
}

function mreg_read_payload(Master_relation $controller): array
{
    $output = $controller->output;
    $payload = json_decode($output->body, true);
    return is_array($payload) ? $payload : [];
}

function mreg_invoke_writer(
    string $writer,
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    bool $editAllowed = true,
    array $sessionValues = [MREG_FIELD => MREG_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    $controller = mreg_fixture($method, $post, $permissionAllowed, $editAllowed, $sessionValues, $get, $headers, $rawBody);
    $response = null;
    try {
        $controller->{$writer}(9);
    } catch (MasterRelationExtraGroupSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

function mreg_has_db_call(object $source, string $method, string $table): bool
{
    foreach ($source->calls as $call) {
        $recordedTable = is_array($call[1] ?? null) ? ($call[1][0] ?? '') : ($call[1] ?? '');
        if (($call[0] ?? '') === $method && (string)$recordedTable === $table) {
            return true;
        }
    }
    return false;
}

function mreg_has_call(array $calls, string $method): bool
{
    foreach ($calls as $call) {
        if (($call[0] ?? '') === $method) {
            return true;
        }
    }
    return false;
}

function mreg_call_position(array $calls, string $method, ?string $table = null): int
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

function mreg_query_position(array $calls, string $needle): int
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

function mreg_product_inserts(Master_relation $controller): array
{
    $payloads = [];
    foreach ($controller->Master_model->calls as $call) {
        if (($call[0] ?? '') === 'insert' && ($call[1] ?? '') === 'mst_product_extra_map') {
            $payloads[] = $call[2] ?? [];
        }
    }
    return $payloads;
}

function mreg_item_inserts(Master_relation $controller): array
{
    $payloads = [];
    foreach ($controller->Master_model->calls as $call) {
        if (($call[0] ?? '') === 'insert' && ($call[1] ?? '') === 'mst_extra_group_item') {
            $payloads[] = $call[2] ?? [];
        }
    }
    return $payloads;
}

function mreg_invoke_item_case(array $post, array $group, array $extraRows): Master_relation
{
    $controller = mreg_fixture(
        'POST',
        $post,
        true,
        true,
        [MREG_FIELD => MREG_TOKEN],
        [],
        [MREG_HEADER => MREG_TOKEN]
    );
    $controller->Master_model->group = $group;
    $controller->db->validationExtraRows = $extraRows;
    $controller->extra_group_items_save_ajax(9);
    return $controller;
}

function mreg_assert_item_rejected(Master_relation $controller, string $label): void
{
    $payload = mreg_read_payload($controller);
    mreg_check(
        $controller->output->status === 422
            && ($payload['ok'] ?? null) === false
            && is_string($payload['message'] ?? null),
        $label . ' returns structured JSON HTTP 422'
    );
    mreg_check(
        !mreg_has_call($controller->db->calls, 'trans_start')
            && !mreg_has_call($controller->db->calls, 'trans_complete')
            && !mreg_has_db_call($controller->db, 'delete', 'mst_extra_group_item')
            && mreg_item_inserts($controller) === [],
        $label . ' is rejected before transaction, delete, or insert'
    );
}

function mreg_invoke_product_case(array $post, array $group, array $productRows): Master_relation
{
    $controller = mreg_fixture(
        'POST',
        array_merge([MREG_REVISION_FIELD => MREG_REVISION], $post),
        true,
        true,
        [MREG_FIELD => MREG_TOKEN],
        [],
        [MREG_HEADER => MREG_TOKEN]
    );
    $controller->Master_model->group = $group;
    $controller->db->lockedGroup = $group;
    $controller->db->validationProductRows = $productRows;
    $controller->extra_group_products_save_ajax(9);
    return $controller;
}

function mreg_assert_product_rejected(Master_relation $controller, string $label): void
{
    $payload = mreg_read_payload($controller);
    mreg_check(
        $controller->output->status === 422
            && ($payload['ok'] ?? null) === false
            && is_string($payload['message'] ?? null),
        $label . ' returns structured JSON HTTP 422'
    );
    mreg_check(
        mreg_has_call($controller->db->calls, 'trans_begin')
            && mreg_has_call($controller->db->calls, 'trans_rollback')
            && !mreg_has_db_call($controller->db, 'delete', 'mst_product_extra_map')
            && mreg_product_inserts($controller) === [],
        $label . ' rolls back after revision check and before delete or insert'
    );
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$viewSource = (string)file_get_contents($root . '/application/views/master/index.php');

$tokenSource = mreg_method_source($controllerSource, 'extraGroupMutationCsrf');
$readTokenSource = mreg_method_source($controllerSource, 'extraGroupMutationCsrfForReadResponse');
$guardSource = mreg_method_source($controllerSource, 'requireExtraGroupMutationCsrf');
mreg_check(
    strpos($controllerSource, "EXTRA_GROUP_MUTATION_CSRF_SESSION_KEY = '" . MREG_FIELD . "'") !== false
        && strpos($controllerSource, "EXTRA_GROUP_MUTATION_CSRF_CI_HEADER = '" . MREG_HEADER . "'") !== false,
    'controller declares isolated extra-group session key and canonical header'
);
mreg_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'extra-group token helper generates strict lowercase 64-hex tokens'
);
mreg_check(
    strpos($readTokenSource, "can('master.extra_group.index', 'edit')") !== false
        && strpos($readTokenSource, 'extraGroupMutationCsrf()') > strpos($readTokenSource, "can('master.extra_group.index', 'edit')"),
    'read response gates token issuance on extra-group edit permission'
);
mreg_check(
    strpos($guardSource, "method(true) !== 'POST'") < strpos($guardSource, 'get_request_header(')
        && strpos($guardSource, 'get_request_header(') < strpos($guardSource, 'hash_equals(')
        && strpos($guardSource, ', 405);') !== false
        && strpos($guardSource, ', 403);') !== false,
    'writer guard checks POST, canonical header, and constant-time session match'
);
mreg_check(
    strpos($guardSource, '->post(') === false
        && strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'json_decode') === false,
    'writer guard has no form/query/raw-body/JSON fallback'
);

$readers = [
    'extra_group_items_ajax' => 'extra',
    'extra_group_products_ajax' => 'products',
];
foreach ($readers as $reader => $kind) {
    $source = mreg_method_source($controllerSource, $reader);
    mreg_check(
        strpos($source, "requireRelationPermission('extra-group', 'view')") !== false
            && strpos($source, 'extraGroupMutationCsrfForReadResponse()') !== false,
        $reader . ' keeps view RBAC and conditionally attaches mutation token'
    );

    $viewOnly = mreg_fixture('GET', [], true, false, []);
    $viewOnly->{$reader}(9);
    $viewPayload = mreg_read_payload($viewOnly);
    mreg_check($viewOnly->permissionCalls === [[MREG_PAGE, 'view']], $reader . ' view-only request keeps canonical view permission');
    mreg_check($viewOnly->input->getReads === ['q'] && $viewOnly->input->headerReads === [], $reader . ' remains a GET read path');
    mreg_check(!array_key_exists('mutation_csrf', $viewPayload), $reader . ' does not expose token to view-only user');
    mreg_check($viewOnly->session->reads === [] && $viewOnly->session->writes === [], $reader . ' view-only request does not access mutation session token');

    $editor = mreg_fixture('GET', [], true, true, []);
    $editor->{$reader}(9);
    $editorPayload = mreg_read_payload($editor);
    mreg_check($editor->canCalls === [[MREG_PAGE, 'edit']], $reader . ' checks exact edit permission for token issuance');
    mreg_check(preg_match('/\A[0-9a-f]{64}\z/D', (string)($editorPayload['mutation_csrf'] ?? '')) === 1, $reader . ' editor read response issues strict scoped token');
    mreg_check($editor->session->reads === [MREG_FIELD] && count($editor->session->writes) === 1, $reader . ' editor token is session-bound');
    if ($reader === 'extra_group_products_ajax') {
        mreg_check(($editorPayload[MREG_REVISION_FIELD] ?? '') === MREG_REVISION, 'AJAX product reader revision matches the canonical full snapshot');
    } else {
        mreg_check(!array_key_exists(MREG_REVISION_FIELD, $editorPayload), 'AJAX extra reader does not expose product mapping revision');
    }
}

$writers = [
    'extra_group_items_save_ajax' => 'mst_extra_group_item',
    'extra_group_products_save_ajax' => 'mst_product_extra_map',
];
foreach ($writers as $writer => $table) {
    $source = mreg_method_source($controllerSource, $writer);
    $permissionAt = strpos($source, "requireRelationPermission('extra-group', 'edit')");
    $guardAt = strpos($source, 'requireExtraGroupMutationCsrf()');
    $businessAt = strpos($source, 'get_by_id(');
    if ($writer === 'extra_group_products_save_ajax') {
        $revisionAt = strpos($source, 'EXTRA_GROUP_PRODUCT_MAPPING_REVISION_FIELD');
        $payloadAt = strpos($source, 'post(null, false)');
        mreg_check(
            $permissionAt !== false && $guardAt !== false && $revisionAt !== false && $payloadAt !== false
                && $permissionAt < $guardAt && $guardAt < $revisionAt && $revisionAt < $payloadAt,
            $writer . ' orders edit RBAC, header guard, strict revision, then payload'
        );
    } else {
        mreg_check($permissionAt !== false && $guardAt !== false && $permissionAt < $guardAt && $guardAt < $businessAt, $writer . ' orders edit RBAC, header guard, then group/payload work');
    }

    foreach (['GET', 'PUT'] as $method) {
        [$controller, $response] = mreg_invoke_writer($writer, $method);
        mreg_check($controller->permissionCalls === [[MREG_PAGE, 'edit']], $writer . ' ' . $method . ' preserves edit RBAC');
        mreg_check($controller->output->status === 405, $writer . ' ' . $method . ' returns JSON HTTP 405');
        mreg_check($controller->input->headerReads === [] && $controller->session->reads === [], $writer . ' ' . $method . ' reads no token');
        mreg_check($controller->db->calls === [] && $controller->Master_model->calls === [], $writer . ' ' . $method . ' performs no business/DB work');
    }

    [$denied, $deniedResponse] = mreg_invoke_writer($writer, 'POST', [], false, true, [MREG_FIELD => MREG_TOKEN], [], [MREG_HEADER => MREG_TOKEN]);
    mreg_check($deniedResponse instanceof MasterRelationExtraGroupSmokeResponse && $deniedResponse->getCode() === 403, $writer . ' RBAC denial returns 403');
    mreg_check($denied->input->events === [] && $denied->session->reads === [] && $denied->db->calls === [], $writer . ' RBAC denial precedes header/session/DB access');

    $rejections = [
        'missing header with alternate channels' => [
            [MREG_FIELD => MREG_TOKEN],
            [MREG_FIELD => MREG_TOKEN],
            [MREG_FIELD => MREG_TOKEN, 'X-CSRF-Token' => MREG_TOKEN],
            json_encode([MREG_HEADER => MREG_TOKEN]),
        ],
        'malformed header' => [[], [], [MREG_HEADER => 'NOT-LOWERCASE-HEX'], ''],
        'wrong header' => [[], [], [MREG_HEADER => MREG_WRONG], ''],
        'cross-scope header' => [[], [], [MREG_HEADER => MREG_CROSS_SCOPE], ''],
    ];
    foreach ($rejections as $label => [$post, $get, $headers, $raw]) {
        [$controller, $response] = mreg_invoke_writer($writer, 'POST', $post, true, true, [MREG_FIELD => MREG_TOKEN], $get, $headers, (string)$raw);
        mreg_check($response === null && $controller->output->status === 403, $writer . ' rejects ' . $label . ' with JSON HTTP 403');
        mreg_check($controller->input->headerReads === [MREG_HEADER] && $controller->input->postReads === [], $writer . ' ' . $label . ' reads only canonical header');
        $expectedSessionReads = in_array($label, ['missing header with alternate channels', 'malformed header'], true) ? [] : [MREG_FIELD];
        mreg_check($controller->input->getReads === [] && $controller->input->rawReads === 0 && $controller->session->reads === $expectedSessionReads, $writer . ' ' . $label . ' ignores all fallback channels');
        mreg_check($controller->db->calls === [] && $controller->Master_model->calls === [], $writer . ' ' . $label . ' performs no business/DB work');
    }

    $validPost = $writer === 'extra_group_items_save_ajax'
        ? ['extra_ids' => [41]]
        : [MREG_REVISION_FIELD => MREG_REVISION, 'product_ids' => [31]];
    [$valid, $validResponse] = mreg_invoke_writer($writer, 'POST', $validPost, true, true, [MREG_FIELD => MREG_TOKEN], [], [MREG_HEADER => MREG_TOKEN]);
    mreg_check($validResponse === null && $valid->output->status === 200, $writer . ' valid canonical header reaches JSON writer');
    mreg_check(mreg_has_db_call($valid->db, 'delete', $table) && mreg_has_db_call($valid->Master_model, 'insert', $table), $writer . ' valid request reaches expected mapping mutation');
}

foreach (['missing revision' => null, 'malformed revision' => 'NOT-A-REVISION'] as $label => $revision) {
    $post = ['product_ids' => [31]];
    if ($revision !== null) {
        $post[MREG_REVISION_FIELD] = $revision;
    }
    [$rejectedRevision, $rejectedRevisionResponse] = mreg_invoke_writer(
        'extra_group_products_save_ajax',
        'POST',
        $post,
        true,
        true,
        [MREG_FIELD => MREG_TOKEN],
        [],
        [MREG_HEADER => MREG_TOKEN]
    );
    $rejectedPayload = mreg_read_payload($rejectedRevision);
    mreg_check(
        $rejectedRevisionResponse === null
            && $rejectedRevision->output->status === 422
            && ($rejectedPayload['ok'] ?? null) === false,
        'AJAX product writer rejects ' . $label . ' with structured HTTP 422'
    );
    mreg_check(
        $rejectedRevision->input->postReads === [MREG_REVISION_FIELD]
            && $rejectedRevision->db->calls === []
            && $rejectedRevision->Master_model->calls === [],
        'AJAX product writer rejects ' . $label . ' before payload, lookup, or DB work'
    );
}

$conflict = mreg_invoke_product_case(
    [MREG_REVISION_FIELD => MREG_EMPTY_REVISION, 'product_ids' => [31]],
    [
        'id' => 9,
        'group_code' => 'GRP-9',
        'group_name' => 'Fixture Group',
        'product_division_id' => 4,
        'is_active' => 1,
    ],
    [['id' => 31, 'is_active' => 1, 'product_division_id' => 4]]
);
$conflictPayload = mreg_read_payload($conflict);
mreg_check(
    $conflict->output->status === 409
        && ($conflictPayload['ok'] ?? null) === false
        && strpos((string)($conflictPayload['message'] ?? ''), 'Muat ulang') !== false,
    'AJAX stale revision returns generic HTTP 409 reload guidance'
);
mreg_check(
    mreg_has_call($conflict->db->calls, 'trans_begin')
        && mreg_has_call($conflict->db->calls, 'query')
        && mreg_has_call($conflict->db->calls, 'trans_rollback')
        && !mreg_has_db_call($conflict->db, 'delete', 'mst_product_extra_map')
        && mreg_product_inserts($conflict) === [],
    'AJAX stale revision locks and rolls back without mapping DML'
);

$itemValidatorSource = mreg_method_source($controllerSource, 'validateExtraGroupItemSelection');
$itemWriterSource = mreg_method_source($controllerSource, 'extra_group_items_save_ajax');
mreg_check(
    strpos($itemValidatorSource, 'from($childTable)') !== false
        && strpos($itemValidatorSource, "where_in('id', \$selectedIds)") !== false
        && strpos($itemValidatorSource, 'get_by_id(') === false,
    'extra/group item validator performs one set-based child lookup'
);
mreg_check(
    strpos($itemWriterSource, 'validateExtraGroupItemSelection(') !== false
        && strpos($itemWriterSource, 'validateExtraGroupItemSelection(') < strpos($itemWriterSource, 'trans_start('),
    'AJAX extra-group item writer completes validation before transaction start'
);

$activeItemGroup = [
    'id' => 9,
    'group_code' => 'GRP-9',
    'group_name' => 'Fixture Group',
    'product_division_id' => 4,
    'is_active' => 1,
];
$inactiveItemGroup = array_replace($activeItemGroup, ['is_active' => 0]);
$validExtras = [
    ['id' => 42, 'is_active' => 1, 'source_kind' => 'PRODUCT'],
    ['id' => 41, 'is_active' => 1, 'source_kind' => 'MASTER'],
];

$itemDeduped = mreg_invoke_item_case(
    ['extra_ids' => ['42', 42, '41', '42']],
    $activeItemGroup,
    $validExtras
);
$itemDedupedPayload = mreg_read_payload($itemDeduped);
mreg_check(
    $itemDeduped->output->status === 200 && ($itemDedupedPayload['selected_count'] ?? null) === 2,
    'AJAX extra-item writer accepts and counts a deduplicated valid set'
);
mreg_check(
    mreg_item_inserts($itemDeduped) === [
        ['extra_group_id' => 9, 'extra_id' => 42, 'sort_order' => 10],
        ['extra_group_id' => 9, 'extra_id' => 41, 'sort_order' => 20],
    ],
    'AJAX extra-item writer deduplicates in first-seen order with 10-step sorting'
);

foreach ([
    'active group with absent extra_ids' => [[], $activeItemGroup],
    'active group with empty extra_ids' => [['extra_ids' => []], $activeItemGroup],
    'inactive group with absent extra_ids' => [[], $inactiveItemGroup],
    'inactive group with empty extra_ids' => [['extra_ids' => []], $inactiveItemGroup],
] as $label => [$post, $group]) {
    $clearItem = mreg_invoke_item_case($post, $group, []);
    $clearPayload = mreg_read_payload($clearItem);
    mreg_check(
        $clearItem->output->status === 200 && ($clearPayload['selected_count'] ?? null) === 0,
        $label . ' is accepted as explicit clear-all'
    );
    mreg_check(
        mreg_has_call($clearItem->db->calls, 'trans_start')
            && mreg_has_db_call($clearItem->db, 'delete', 'mst_extra_group_item')
            && mreg_item_inserts($clearItem) === [],
        $label . ' reaches delete-only replace-all transaction'
    );
}

$invalidItemCases = [
    'AJAX scalar extra_ids' => [['extra_ids' => '42'], $activeItemGroup, $validExtras],
    'AJAX null extra_ids' => [['extra_ids' => null], $activeItemGroup, $validExtras],
    'AJAX malformed extra ID' => [['extra_ids' => ['42', 'abc']], $activeItemGroup, $validExtras],
    'AJAX nested extra ID' => [['extra_ids' => [['42']]], $activeItemGroup, $validExtras],
    'AJAX zero extra ID' => [['extra_ids' => [0]], $activeItemGroup, $validExtras],
    'AJAX negative extra ID' => [['extra_ids' => [-1]], $activeItemGroup, $validExtras],
    'AJAX float extra ID' => [['extra_ids' => [42.0]], $activeItemGroup, $validExtras],
    'AJAX boolean extra ID' => [['extra_ids' => [false]], $activeItemGroup, $validExtras],
    'AJAX non-canonical numeric extra ID' => [['extra_ids' => ['042']], $activeItemGroup, $validExtras],
    'AJAX nonexistent extra ID' => [['extra_ids' => [999]], $activeItemGroup, []],
    'AJAX inactive extra ID' => [['extra_ids' => [42]], $activeItemGroup, [['id' => 42, 'is_active' => 0]]],
    'AJAX inactive group nonempty set' => [['extra_ids' => [42]], $inactiveItemGroup, $validExtras],
    'AJAX mixed valid and nonexistent extra IDs' => [['extra_ids' => [42, 999]], $activeItemGroup, [['id' => 42, 'is_active' => 1]]],
];
foreach ($invalidItemCases as $label => [$post, $group, $rows]) {
    mreg_assert_item_rejected(mreg_invoke_item_case($post, $group, $rows), $label);
}

$crossDomainItems = mreg_invoke_item_case(
    ['extra_ids' => [42, 41]],
    $activeItemGroup,
    $validExtras
);
mreg_check($crossDomainItems->output->status === 200, 'AJAX writer allows cross-domain/source_kind extras');
mreg_check(
    count(mreg_item_inserts($crossDomainItems)) === 2
        && mreg_has_db_call($crossDomainItems->db, 'delete', 'mst_extra_group_item'),
    'cross-domain/source_kind extra set reaches atomic replace-all mutation'
);

$missingItemParent = mreg_invoke_item_case(['extra_ids' => []], [], []);
mreg_check(
    $missingItemParent->output->status === 404
        && (mreg_read_payload($missingItemParent)['ok'] ?? null) === false,
    'AJAX missing extra-group parent retains structured HTTP 404 behavior'
);
mreg_check(
    $missingItemParent->db->calls === [] && mreg_item_inserts($missingItemParent) === [],
    'AJAX missing extra-group parent performs no transaction or mapping writes'
);

$validatorSource = mreg_method_source($controllerSource, 'validateExtraGroupProductSelection');
mreg_check(
    substr_count($validatorSource, "from('mst_product')") === 1
        && strpos($validatorSource, "where_in('id', \$productIds)") !== false
        && strpos($validatorSource, 'get_where(') === false,
    'product selection validator performs one set-based product lookup'
);
foreach (['extra_group_products_save', 'extra_group_products_save_ajax'] as $productWriter) {
    $source = mreg_method_source($controllerSource, $productWriter);
    $validationAt = strpos($source, 'validateExtraGroupProductSelection(');
    mreg_check(
        strpos($source, 'replaceExtraGroupProducts(') !== false && $validationAt === false,
        $productWriter . ' delegates optimistic replace-all and shared validation to one helper'
    );
}
$replaceSource = mreg_method_source($controllerSource, 'replaceExtraGroupProducts');
$mappingLockSource = mreg_method_source($controllerSource, 'lockExtraGroupProductMappingRows');
$parentLockAt = strpos($replaceSource, 'FROM mst_extra_group WHERE id = ? FOR UPDATE');
$mappingLockAt = strpos($replaceSource, 'lockExtraGroupProductMappingRows(');
$revisionAt = strpos($replaceSource, 'canonicalExtraGroupProductMappingRevision($lockedMappingRows)');
$validationAt = strpos($replaceSource, 'validateExtraGroupProductSelection(');
$deleteAt = strpos($replaceSource, "delete('mst_product_extra_map')");
mreg_check(
    strpos($mappingLockSource, 'SELECT product_id, sort_order FROM mst_product_extra_map') !== false
        && strpos($mappingLockSource, 'FOR UPDATE') !== false,
    'shared product replace has a dedicated mapping-row FOR UPDATE query'
);
mreg_check(
    $parentLockAt !== false && $mappingLockAt !== false && $revisionAt !== false && $validationAt !== false && $deleteAt !== false
        && $parentLockAt < $mappingLockAt && $mappingLockAt < $revisionAt
        && $revisionAt < $validationAt && $validationAt < $deleteAt,
    'shared product replace locks parent then mapping rows, hashes locked revision, validates, then mutates'
);
mreg_check(
    strpos($replaceSource, 'if ($this->db->trans_begin() === false)') !== false
        && strpos($replaceSource, 'if ($this->db->trans_commit() === false)') !== false
        && strpos($replaceSource, 'if ($this->db->trans_active())') !== false,
    'shared product replace checks begin/commit return values and rolls back an active failed commit'
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

$deduped = mreg_invoke_product_case(
    ['product_ids' => ['31', 31, '32', '31']],
    $activeDivisionGroup,
    $validDivisionProducts
);
$dedupedPayload = mreg_read_payload($deduped);
mreg_check(
    $deduped->output->status === 200 && ($dedupedPayload['selected_count'] ?? null) === 2,
    'AJAX product writer accepts and counts a deduplicated valid set'
);
mreg_check(mreg_has_db_call($deduped->db, 'delete', 'mst_product_extra_map'), 'AJAX valid set reaches replace-all delete');
mreg_check(
    mreg_query_position($deduped->db->calls, 'FROM mst_extra_group ') >= 0
        && mreg_query_position($deduped->db->calls, 'FROM mst_product_extra_map ') > mreg_query_position($deduped->db->calls, 'FROM mst_extra_group ')
        && mreg_query_position($deduped->db->calls, 'FROM mst_product_extra_map ') < mreg_call_position($deduped->db->calls, 'delete', 'mst_product_extra_map'),
    'AJAX valid set locks parent then mapping rows before replace-all delete'
);
mreg_check(
    mreg_product_inserts($deduped) === [
        ['extra_group_id' => 9, 'product_id' => 31, 'sort_order' => 10],
        ['extra_group_id' => 9, 'product_id' => 32, 'sort_order' => 20],
    ],
    'AJAX valid set deduplicates IDs and preserves 10-step sort order'
);

$beginFailure = mreg_fixture(
    'POST',
    [MREG_REVISION_FIELD => MREG_REVISION, 'product_ids' => [31]],
    true,
    true,
    [MREG_FIELD => MREG_TOKEN],
    [],
    [MREG_HEADER => MREG_TOKEN]
);
$beginFailure->db->transBeginResult = false;
$beginFailure->extra_group_products_save_ajax(9);
$beginFailurePayload = mreg_read_payload($beginFailure);
mreg_check(
    $beginFailure->output->status === 500
        && ($beginFailurePayload['ok'] ?? null) === false
        && ($beginFailurePayload['message'] ?? '') === 'Gagal menyimpan mapping produk untuk group extra.',
    'AJAX begin=false returns generic structured HTTP 500 without success'
);
mreg_check(
    $beginFailure->db->calls === [['trans_begin', []]]
        && $beginFailure->Master_model->calls === []
        && mreg_product_inserts($beginFailure) === [],
    'AJAX begin=false performs no lookup, mapping lock, delete, or insert'
);

$commitFailure = mreg_fixture(
    'POST',
    [MREG_REVISION_FIELD => MREG_REVISION, 'product_ids' => [31]],
    true,
    true,
    [MREG_FIELD => MREG_TOKEN],
    [],
    [MREG_HEADER => MREG_TOKEN]
);
$commitFailure->db->transCommitResult = false;
$commitFailure->extra_group_products_save_ajax(9);
$commitFailurePayload = mreg_read_payload($commitFailure);
mreg_check(
    $commitFailure->output->status === 500
        && ($commitFailurePayload['ok'] ?? null) === false
        && ($commitFailurePayload['message'] ?? '') === 'Gagal menyimpan mapping produk untuk group extra.',
    'AJAX commit=false returns generic structured HTTP 500 without success'
);
mreg_check(
    mreg_call_position($commitFailure->db->calls, 'trans_commit') >= 0
        && mreg_call_position($commitFailure->db->calls, 'trans_active') > mreg_call_position($commitFailure->db->calls, 'trans_commit')
        && mreg_call_position($commitFailure->db->calls, 'trans_rollback') > mreg_call_position($commitFailure->db->calls, 'trans_active')
        && $commitFailure->db->transactionActive === false,
    'AJAX commit=false rolls back the still-active transaction'
);

foreach ([
    'AJAX missing product_ids clears inactive group' => [],
    'AJAX empty product_ids clears inactive group' => ['product_ids' => []],
] as $label => $post) {
    $clearInactive = mreg_invoke_product_case($post, $inactiveDivisionGroup, []);
    $clearPayload = mreg_read_payload($clearInactive);
    mreg_check(
        $clearInactive->output->status === 200 && ($clearPayload['selected_count'] ?? null) === 0,
        $label . ' succeeds with selected_count zero'
    );
    mreg_check(
        mreg_has_call($clearInactive->db->calls, 'trans_begin')
            && mreg_has_call($clearInactive->db->calls, 'trans_commit')
            && mreg_has_db_call($clearInactive->db, 'delete', 'mst_product_extra_map')
            && mreg_product_inserts($clearInactive) === [],
        $label . ' reaches delete-only replace-all transaction'
    );
}

$invalidCases = [
    'AJAX scalar product_ids' => [['product_ids' => '31'], $activeDivisionGroup, $validDivisionProducts],
    'AJAX null product_ids' => [['product_ids' => null], $activeDivisionGroup, $validDivisionProducts],
    'AJAX malformed product ID' => [['product_ids' => ['31', 'abc']], $activeDivisionGroup, $validDivisionProducts],
    'AJAX zero product ID' => [['product_ids' => ['0']], $activeDivisionGroup, $validDivisionProducts],
    'AJAX negative product ID' => [['product_ids' => [-1]], $activeDivisionGroup, $validDivisionProducts],
    'AJAX nonexistent product ID' => [['product_ids' => [999]], $activeDivisionGroup, $validDivisionProducts],
    'AJAX inactive product ID' => [['product_ids' => [31]], $activeDivisionGroup, [['id' => 31, 'is_active' => 0, 'product_division_id' => 4]]],
    'AJAX wrong-division product ID' => [['product_ids' => [31]], $activeDivisionGroup, [['id' => 31, 'is_active' => 1, 'product_division_id' => 7]]],
    'AJAX inactive group nonempty set' => [['product_ids' => [31]], $inactiveDivisionGroup, $validDivisionProducts],
    'AJAX mixed valid and nonexistent product IDs' => [['product_ids' => [31, 999]], $activeDivisionGroup, $validDivisionProducts],
];
foreach ($invalidCases as $label => [$post, $group, $rows]) {
    mreg_assert_product_rejected(mreg_invoke_product_case($post, $group, $rows), $label);
}

$crossDivisionGroup = array_replace($activeDivisionGroup, ['product_division_id' => null]);
$crossDivision = mreg_invoke_product_case(
    ['product_ids' => [31, 33]],
    $crossDivisionGroup,
    [
        ['id' => 31, 'is_active' => 1, 'product_division_id' => 4],
        ['id' => 33, 'is_active' => 1, 'product_division_id' => 7],
    ]
);
mreg_check($crossDivision->output->status === 200, 'AJAX NULL-division group accepts valid products across divisions');
mreg_check(
    count(mreg_product_inserts($crossDivision)) === 2
        && mreg_has_db_call($crossDivision->db, 'delete', 'mst_product_extra_map'),
    'AJAX NULL-division cross-division set reaches atomic replace-all mutation'
);

$missingParent = mreg_invoke_product_case(['product_ids' => []], [], []);
mreg_check(
    $missingParent->output->status === 404
        && (mreg_read_payload($missingParent)['ok'] ?? null) === false,
    'AJAX missing product-group parent retains structured HTTP 404 behavior'
);
mreg_check(
    mreg_has_call($missingParent->db->calls, 'trans_begin')
        && mreg_has_call($missingParent->db->calls, 'trans_rollback')
        && !mreg_has_db_call($missingParent->db, 'delete', 'mst_product_extra_map')
        && mreg_product_inserts($missingParent) === [],
    'AJAX missing product-group parent rolls back lock transaction without mapping writes'
);

$reflection = new ReflectionClass(Master_relation::class);
$helper = $reflection->getMethod('extraGroupMutationCsrf');
$helper->setAccessible(true);
$generatedController = mreg_fixture('GET', [], true, false, []);
$generated = (string)$helper->invoke($generatedController);
mreg_check(preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1, 'missing session token generates lowercase 64-hex token');
mreg_check(count($generatedController->session->writes) === 1 && $generatedController->session->writes[0][0] === MREG_FIELD, 'generated token writes only isolated session key');

$revisionHelper = $reflection->getMethod('canonicalExtraGroupProductMappingRevision');
$revisionHelper->setAccessible(true);
$canonicalRevision = (string)$revisionHelper->invoke($generatedController, [
    ['product_id' => 31, 'sort_order' => 10],
    ['product_id' => 7, 'sort_order' => 20],
]);
$reorderedRevision = (string)$revisionHelper->invoke($generatedController, [
    ['sort_order' => 20, 'product_id' => 7],
    ['sort_order' => 10, 'product_id' => 31],
]);
$changedPairRevision = (string)$revisionHelper->invoke($generatedController, [
    ['product_id' => 3, 'sort_order' => 110],
    ['product_id' => 7, 'sort_order' => 20],
]);
mreg_check(
    preg_match('/\A[0-9a-f]{64}\z/D', $canonicalRevision) === 1
        && hash_equals($canonicalRevision, $reorderedRevision)
        && !hash_equals($canonicalRevision, $changedPairRevision),
    'mapping revision helper is canonical, deterministic, and delimiter-unambiguous'
);

$fetchRowsAt = strpos($viewSource, 'function fetchRows(initialFetch)');
$openModalAt = strpos($viewSource, 'function openModal(');
$saveBtnAt = strpos($viewSource, 'saveBtn.addEventListener');
$saveBlock = $saveBtnAt === false ? '' : substr($viewSource, $saveBtnAt);
$fetchBlock = $fetchRowsAt === false ? '' : substr($viewSource, $fetchRowsAt, $openModalAt === false ? null : $openModalAt - $fetchRowsAt);
mreg_check(
    strpos($viewSource, "mutationCsrf: ''") !== false
        && strpos($viewSource, 'payload.mutation_csrf') !== false
        && strpos($viewSource, "state.mutationCsrf = ''") !== false,
    'modal state stores and resets read-response mutation token'
);
mreg_check(
    strpos($fetchBlock, "method: 'POST'") === false
        && strpos($viewSource, "'X-Master-Extra-Group-Csrf': state.mutationCsrf") !== false
        && strpos($saveBlock, "method: 'POST'") !== false,
    'fetchRows remains read GET behavior and saveBtn sends exact canonical header on POST'
);
mreg_check(
    strpos($saveBlock, "formData.append('" . MREG_FIELD . "'") === false
        && strpos($saveBlock, 'if (!isValidRevision(state.mutationCsrf))') !== false,
    'caller does not send token as form/query/JSON fallback and blocks tokenless save'
);
mreg_check(
    strpos($viewSource, "state.mutationCsrf = typeof payload.mutation_csrf === 'string' ? payload.mutation_csrf : '';") !== false
        && strpos($viewSource, 'var hasMutationCsrf = isValidRevision(state.mutationCsrf);') !== false,
    'caller enables save only when read response supplied edit-scoped token'
);
mreg_check(
    strpos($viewSource, "mappingRevision: ''") !== false
        && strpos($fetchBlock, 'if (initialFetch && !state.seedLoaded)') !== false
        && substr_count($fetchBlock, 'state.mappingRevision =') === 1
        && strpos($viewSource, 'fetchRows(true)') !== false
        && strpos($viewSource, 'fetchRows(false)') !== false,
    'modal captures product mapping revision only from the initial fetch'
);
mreg_check(
    strpos($viewSource, 'modalEpoch: 0') !== false
        && strpos($viewSource, 'requestSequence: 0') !== false
        && strpos($viewSource, 'latestRequestSequence: 0') !== false
        && strpos($viewSource, 'querySequence: 0') !== false
        && strpos($fetchBlock, 'epoch: state.modalEpoch') !== false
        && strpos($fetchBlock, 'kind: state.kind') !== false
        && strpos($fetchBlock, 'groupId: state.groupId') !== false,
    'modal fetch captures epoch plus kind/group context and request/query sequences'
);
mreg_check(
    strpos($fetchBlock, 'if (!isCurrentModalContext(requestContext))') !== false
        && strpos($fetchBlock, 'if (!isCurrentModalContext(requestContext))') < strpos($fetchBlock, 'state.selectedIds =')
        && strpos($viewSource, 'context.epoch === state.modalEpoch') !== false
        && strpos($viewSource, 'context.kind === state.kind') !== false
        && strpos($viewSource, 'context.groupId === state.groupId') !== false,
    'stale Group A response is rejected before it can seed Group B modal state'
);
mreg_check(
    strpos($fetchBlock, 'var requestSequence = ++state.requestSequence;') !== false
        && strpos($fetchBlock, 'state.latestRequestSequence = requestSequence;') !== false
        && substr_count($fetchBlock, 'if (!isLatestQueryResponse(requestContext, requestSequence))') >= 2
        && strpos($viewSource, 'requestSequence === state.latestRequestSequence') !== false
        && strpos($viewSource, 'context.querySequence === state.querySequence') !== false,
    'only the latest request for the latest query may render rows or an error'
);
mreg_check(
    substr_count($fetchBlock, 'state.selectedIds =') === 1
        && substr_count($fetchBlock, 'state.mappingRevision =') === 1
        && strpos($fetchBlock, 'if (!state.seedLoaded) {') !== false
        && strpos($fetchBlock, 'state.pendingRows = {') !== false
        && strpos($viewSource, 'function renderPendingRows()') !== false,
    'filter responses cannot replace initial selection/revision and out-of-order filter rows wait for the initial seed'
);
mreg_check(
    strpos($saveBlock, "if (state.kind === 'product') {") !== false
        && strpos($saveBlock, "formData.append('" . MREG_REVISION_FIELD . "', state.mappingRevision)") !== false
        && substr_count($saveBlock, "formData.append('" . MREG_REVISION_FIELD . "'") === 1,
    'modal sends mapping revision only through the product save branch'
);
mreg_check(
    strpos($saveBlock, 'error && error.status === 409') !== false
        && strpos($saveBlock, "state.mappingRevision = ''") !== false
        && strpos($saveBlock, 'Muat ulang halaman') !== false,
    'modal treats HTTP 409 as a reload-required conflict and disables stale retry'
);

if ($failures !== []) {
    fwrite(STDERR, "Master relation extra-group mutation CSRF smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Checks: %d; failures: %d\n", $checks, count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Master relation extra-group mutation CSRF smoke passed (%d checks).\n", $checks));
