<?php

declare(strict_types=1);

/**
 * Behavior-level, DB/network/bootstrap-free smoke for the legacy product recipe
 * mutation boundary in Master_relation.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const MRPR_PAGE = 'master.product_recipe.index';
const MRPR_FIELD = 'master_relation_product_recipe_mutation_csrf';
const MRPR_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const MRPR_WRONG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MRPR_CROSS_SCOPE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

final class MasterRelationRecipeSmokeResponse extends RuntimeException
{
}

final class MasterRelationRecipeSmokeInput
{
    public array $events = [];
    public array $postReads = [];
    public array $getReads = [];
    public array $headerReads = [];
    public int $rawReads = 0;

    private string $requestMethod;
    private array $postData;
    private array $getData;
    private array $headers;
    private string $rawBody;

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
        $keyLabel = $key === null ? '*' : (string)$key;
        $this->postReads[] = $keyLabel;
        $this->events[] = 'post:' . $keyLabel;
        return $key === null ? $this->postData : ($this->postData[$keyLabel] ?? null);
    }

    public function get($key = null, $xssClean = false)
    {
        $keyLabel = $key === null ? '*' : (string)$key;
        $this->getReads[] = $keyLabel;
        return $key === null ? $this->getData : ($this->getData[$keyLabel] ?? null);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        return (string)($this->headers[$key] ?? '');
    }

    public function __get($name)
    {
        if ((string)$name !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$name);
        }
        $this->rawReads++;
        return $this->rawBody;
    }
}

final class MasterRelationRecipeSmokeSession
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
        $key = (string)$key;
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value = null): void
    {
        $key = (string)$key;
        $this->writes[] = [$key, $value];
        $this->values[$key] = $value;
    }

    public function set_flashdata($key, $value): void
    {
        $this->flashes[] = [(string)$key, (string)$value];
    }
}

final class MasterRelationRecipeSmokeResult
{
    private array $rows;

    public function __construct(array $row = [])
    {
        $this->rows = array_is_list($row) ? $row : ($row === [] ? [] : [$row]);
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

final class MasterRelationRecipeSmokeDb
{
    public array $calls = [];
    private array $nextRow;
    public array $recipeRows = [[
        'id' => 91,
        'line_no' => 1,
        'line_type' => 'COMPONENT',
        'material_item_id' => 0,
        'component_id' => 7,
        'source_division_id' => 1,
        'qty' => 1,
        'uom_id' => 1,
        'notes' => '',
        'sort_order' => 10,
    ]];

    public function __construct(array $nextRow = [])
    {
        $this->nextRow = $nextRow;
    }

    public function __call($name, $arguments)
    {
        $this->calls[] = [(string)$name, $arguments];
        if ((string)$name === 'get') {
            return new MasterRelationRecipeSmokeResult($this->nextRow);
        }
        if ((string)$name === 'trans_status') {
            return true;
        }
        return $this;
    }

    public function table_exists($table): bool
    {
        $this->calls[] = ['table_exists', [(string)$table]];
        return (string)$table === 'aud_transaction_log';
    }

    public function field_exists($field, $table): bool
    {
        $this->calls[] = ['field_exists', [(string)$field, (string)$table]];
        return (string)$table === 'aud_transaction_log' && in_array((string)$field, [
            'module_code', 'action_code', 'entity_table', 'entity_id',
            'actor_user_id', 'source_ip', 'before_payload', 'after_payload',
            'notes', 'created_at',
        ], true);
    }

    public function query($sql, $bindings = []): MasterRelationRecipeSmokeResult
    {
        $this->calls[] = ['query', [(string)$sql, $bindings]];
        return new MasterRelationRecipeSmokeResult($this->recipeRows);
    }

    public function insert($table, $data): bool
    {
        $this->calls[] = ['insert', [(string)$table, $data]];
        return true;
    }
}

final class MasterRelationRecipeSmokeModel
{
    public array $calls = [];
    public array $recipeRow = ['id' => 91, 'product_id' => 12];

    public function get_by_id($table, $id): array
    {
        $this->calls[] = ['get_by_id', (string)$table, (int)$id];
        return $this->recipeRow;
    }

    public function insert($table, array $payload): int
    {
        $this->calls[] = ['insert', (string)$table, $payload];
        return 1;
    }

    public function update($table, $id, array $payload): bool
    {
        $this->calls[] = ['update', (string)$table, (int)$id, $payload];
        return true;
    }
}

final class MasterRelationRecipeSmokeValidation
{
    public array $calls = [];

    public function set_rules($field, $label, $rules): void
    {
        $this->calls[] = ['set_rules', (string)$field];
    }

    public function run(): bool
    {
        $this->calls[] = ['run'];
        return true;
    }
}

class MY_Controller
{
    public MasterRelationRecipeSmokeInput $input;
    public MasterRelationRecipeSmokeSession $session;
    public MasterRelationRecipeSmokeDb $db;
    public MasterRelationRecipeSmokeModel $Master_model;
    public MasterRelationRecipeSmokeValidation $form_validation;
    public array $permissionCalls = [];
    public bool $permissionAllowed = true;

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new MasterRelationRecipeSmokeResponse('Forbidden', 403);
        }
    }
}

function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationRecipeSmokeResponse((string)$message, (int)$statusCode);
}

function show_404(): void
{
    throw new MasterRelationRecipeSmokeResponse('Not Found', 404);
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
}

function validation_errors($prefix = '', $suffix = ''): string
{
    return 'invalid fixture';
}

function site_url($uri = ''): string
{
    return '/smoke/' . ltrim((string)$uri, '/');
}

function html_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function set_value($field, $default = '')
{
    return $default;
}

require dirname(__DIR__, 2) . '/application/controllers/Master_relation.php';

$checks = 0;
$failures = [];

function mrpr_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function mrpr_method_source(string $source, string $method): string
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

function mrpr_fixture(
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRPR_FIELD => MRPR_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    $reflection = new ReflectionClass(Master_relation::class);
    /** @var Master_relation&MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationRecipeSmokeInput($method, $post, $get, $headers, $rawBody);
    $controller->session = new MasterRelationRecipeSmokeSession($sessionValues);
    $controller->db = new MasterRelationRecipeSmokeDb([
        'id' => 12,
        'product_id' => 12,
        'product_name' => 'Fixture Product',
        'product_division_code' => '',
        'default_operational_division_id' => 1,
        'default_operational_division_name' => 'BAR',
        'operational_division_id' => 1,
        'uom_id' => 1,
    ]);
    $controller->Master_model = new MasterRelationRecipeSmokeModel();
    $controller->form_validation = new MasterRelationRecipeSmokeValidation();
    $controller->permissionAllowed = $permissionAllowed;
    return [$controller, $reflection];
}

function mrpr_invoke_writer(
    string $writer,
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRPR_FIELD => MRPR_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    [$controller, $reflection] = mrpr_fixture($method, $post, $permissionAllowed, $sessionValues, $get, $headers, $rawBody);
    if (!array_key_exists('product_recipe_revision', $post)) {
        $revision = $reflection->getMethod('canonicalProductRecipeRevision');
        $revision->setAccessible(true);
        $post['product_recipe_revision'] = (string)$revision->invoke($controller, $controller->db->recipeRows);
        $controller->input = new MasterRelationRecipeSmokeInput($method, $post, $get, $headers, $rawBody);
    }
    $response = null;
    try {
        $controller->{$writer}($writer === 'product_recipe_bulk_save' || $writer === 'product_recipe_store' ? 12 : 91);
    } catch (MasterRelationRecipeSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$bulkViewSource = (string)file_get_contents($root . '/application/views/master/relation_product_recipe_edit.php');
$formViewSource = (string)file_get_contents($root . '/application/views/master/relation_form.php');
$listViewSource = (string)file_get_contents($root . '/application/views/master/relation_list.php');
$writers = [
    'product_recipe_bulk_save' => 'edit',
    'product_recipe_store' => 'create',
    'product_recipe_update' => 'edit',
    'product_recipe_delete' => 'delete',
];

mrpr_check(
    strpos($controllerSource, "PRODUCT_RECIPE_MUTATION_CSRF_SESSION_KEY = '" . MRPR_FIELD . "'") !== false
        && strpos($controllerSource, "PRODUCT_RECIPE_MUTATION_CSRF_FORM_FIELD = '" . MRPR_FIELD . "'") !== false,
    'controller declares the exact isolated session key and form field'
);

$tokenSource = mrpr_method_source($controllerSource, 'productRecipeMutationCsrf');
$guardSource = mrpr_method_source($controllerSource, 'requireProductRecipeMutationCsrf');
mrpr_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'token helper generates and validates strict lowercase 64-hex tokens'
);
mrpr_check(
    strpos($guardSource, "method(true) !== 'POST'") < strpos($guardSource, '->post(')
        && strpos($guardSource, '->post(') < strpos($guardSource, 'hash_equals(')
        && strpos($guardSource, ', 405,') !== false
        && strpos($guardSource, ', 403,') !== false,
    'guard checks method, form field, and constant-time session match with explicit statuses'
);
mrpr_check(
    strpos($guardSource, 'get_request_header') === false
        && strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'json_decode') === false,
    'guard contains no header, query, JSON, or raw-body fallback'
);

foreach ($writers as $writer => $action) {
    $source = mrpr_method_source($controllerSource, $writer);
    $permissionAt = strpos($source, "requireRelationPermission('recipe', '" . $action . "')");
    $guardAt = strpos($source, 'requireProductRecipeMutationCsrf()');
    $businessMarkers = $writer === 'product_recipe_bulk_save' || $writer === 'product_recipe_store'
        ? ['loadProductRecipeParent(', '->post(']
        : ['Master_model->get_by_id(', '->post('];
    $firstBusinessAt = strlen($source);
    foreach ($businessMarkers as $marker) {
        $position = strpos($source, $marker);
        if ($position !== false) {
            $firstBusinessAt = min($firstBusinessAt, $position);
        }
    }
    mrpr_check(
        $source !== '' && $permissionAt !== false && $guardAt !== false
            && $permissionAt < $guardAt && $guardAt < $firstBusinessAt,
        $writer . ' orders canonical RBAC, scoped guard, then parent/payload/model access'
    );

    [$getController, $getResponse] = mrpr_invoke_writer($writer, 'GET');
    mrpr_check($getResponse instanceof MasterRelationRecipeSmokeResponse && $getResponse->getCode() === 405, $writer . ' rejects authorized GET with HTTP 405');
    mrpr_check($getController->permissionCalls === [[MRPR_PAGE, $action]], $writer . ' GET preserves exact canonical RBAC');
    mrpr_check($getController->input->postReads === [] && $getController->session->reads === [], $writer . ' GET reads no form payload or session token');
    mrpr_check($getController->db->calls === [] && $getController->Master_model->calls === [] && $getController->form_validation->calls === [], $writer . ' GET performs no business or DB work');

    [$deniedController, $deniedResponse] = mrpr_invoke_writer(
        $writer,
        'POST',
        [MRPR_FIELD => MRPR_TOKEN, 'lines_json' => '[]'],
        false
    );
    mrpr_check($deniedResponse instanceof MasterRelationRecipeSmokeResponse && $deniedResponse->getCode() === 403, $writer . ' is denied by RBAC');
    mrpr_check($deniedController->permissionCalls === [[MRPR_PAGE, $action]], $writer . ' denial uses exact canonical RBAC');
    mrpr_check($deniedController->input->events === [] && $deniedController->session->reads === [], $writer . ' RBAC denial precedes method/token reads');
    mrpr_check($deniedController->db->calls === [] && $deniedController->Master_model->calls === [], $writer . ' RBAC denial performs no DB/model work');

    $rejections = [
        'missing with alternate channels populated' => [
            [],
            [MRPR_FIELD => MRPR_TOKEN],
            [MRPR_FIELD => MRPR_TOKEN],
            json_encode([MRPR_FIELD => MRPR_TOKEN]),
        ],
        'malformed' => [[MRPR_FIELD => 'not-hex'], [], [], ''],
        'wrong' => [[MRPR_FIELD => MRPR_WRONG], [], [], ''],
        'cross-scope' => [[MRPR_FIELD => MRPR_CROSS_SCOPE], [], [], ''],
    ];
    foreach ($rejections as $label => [$post, $get, $headers, $raw]) {
        $post['business_payload'] = 'must-not-be-read';
        [$invalidController, $invalidResponse] = mrpr_invoke_writer(
            $writer,
            'POST',
            $post,
            true,
            [MRPR_FIELD => MRPR_TOKEN],
            $get,
            $headers,
            (string)$raw
        );
        mrpr_check($invalidResponse instanceof MasterRelationRecipeSmokeResponse && $invalidResponse->getCode() === 403, $writer . ' rejects ' . $label . ' token with HTTP 403');
        mrpr_check($invalidController->input->postReads === [MRPR_FIELD], $writer . ' ' . $label . ' reads only the scoped form field');
        mrpr_check($invalidController->input->getReads === [] && $invalidController->input->headerReads === [] && $invalidController->input->rawReads === 0, $writer . ' ' . $label . ' ignores query/header/raw token channels');
        mrpr_check($invalidController->db->calls === [] && $invalidController->Master_model->calls === [] && $invalidController->form_validation->calls === [], $writer . ' ' . $label . ' performs no write/business work');
    }
}

[$guardController, $guardReflection] = mrpr_fixture('POST', [MRPR_FIELD => MRPR_TOKEN]);
$guard = $guardReflection->getMethod('requireProductRecipeMutationCsrf');
$guard->setAccessible(true);
mrpr_check($guard->invoke($guardController) === true, 'valid scoped form token is accepted');
mrpr_check($guardController->input->postReads === [MRPR_FIELD] && $guardController->session->reads === [MRPR_FIELD], 'valid guard reads exactly the form field and isolated session key');

foreach (array_keys($writers) as $writer) {
    $post = [
        MRPR_FIELD => MRPR_TOKEN,
        'lines_json' => json_encode([[
            'line_type' => 'COMPONENT',
            'component_id' => 7,
            'qty' => 1,
        ]]),
        'line_type' => 'COMPONENT',
        'component_id' => 7,
        'qty' => 1,
    ];
    [$validController, $validResponse] = mrpr_invoke_writer($writer, 'POST', $post);
    mrpr_check($validResponse === null, $writer . ' valid form token passes the request guard');
    mrpr_check($validController->permissionCalls === [[MRPR_PAGE, $writers[$writer]]], $writer . ' valid request preserves exact canonical RBAC');
    $reachedWriter = false;
    if ($writer === 'product_recipe_bulk_save' || $writer === 'product_recipe_delete') {
        foreach ($validController->db->calls as $call) {
            if (($call[0] ?? '') === 'delete') {
                $reachedWriter = true;
                break;
            }
        }
    } else {
        $expectedWriter = $writer === 'product_recipe_store' ? 'insert' : 'update';
        foreach ($validController->db->calls as $call) {
            if (($call[0] ?? '') === $expectedWriter) {
                $reachedWriter = true;
                break;
            }
        }
    }
    mrpr_check($reachedWriter, $writer . ' valid token reaches its expected DB/model writer');
}

[$newTokenController, $newTokenReflection] = mrpr_fixture('GET', [], true, []);
$tokenHelper = $newTokenReflection->getMethod('productRecipeMutationCsrf');
$tokenHelper->setAccessible(true);
$generated = (string)$tokenHelper->invoke($newTokenController);
mrpr_check(preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1, 'missing token generates strict lowercase 64-hex');
mrpr_check(count($newTokenController->session->writes) === 1 && $newTokenController->session->writes[0][0] === MRPR_FIELD, 'generated token is stored only under the isolated key');

foreach (['product_recipe', 'product_recipe_bulk_edit', 'product_recipe_create', 'product_recipe_edit'] as $renderMethod) {
    $renderSource = mrpr_method_source($controllerSource, $renderMethod);
    $renderPermissionAt = strpos($renderSource, "requireRelationPermission('recipe', '");
    $renderTokenAt = strpos($renderSource, 'productRecipeMutationCsrf()');
    mrpr_check(
        $renderPermissionAt !== false && $renderTokenAt !== false && $renderPermissionAt < $renderTokenAt
            && strpos($renderSource, "'" . MRPR_FIELD . "' =>") !== false,
        $renderMethod . ' supplies a token only after its canonical render RBAC'
    );

    [$deniedRenderController] = mrpr_fixture('GET', [], false, []);
    $deniedRenderResponse = null;
    try {
        $deniedRenderController->{$renderMethod}($renderMethod === 'product_recipe_edit' ? 91 : 12);
    } catch (MasterRelationRecipeSmokeResponse $exception) {
        $deniedRenderResponse = $exception;
    }
    mrpr_check(
        $deniedRenderResponse instanceof MasterRelationRecipeSmokeResponse
            && $deniedRenderResponse->getCode() === 403,
        $renderMethod . ' denied render is stopped by RBAC'
    );
    mrpr_check(
        $deniedRenderController->session->reads === []
            && $deniedRenderController->session->writes === []
            && $deniedRenderController->db->calls === []
            && $deniedRenderController->Master_model->calls === [],
        $renderMethod . ' denied render does not generate a token or read business data'
    );
}

mrpr_check(substr_count($bulkViewSource, 'name="' . MRPR_FIELD . '"') === 1, 'bulk recipe form contains exactly one scoped hidden field');
mrpr_check(substr_count($formViewSource, 'name="' . MRPR_FIELD . '"') === 1, 'relation form contains token only in the product-recipe branch');
mrpr_check(
    strpos($formViewSource, 'name="' . MRPR_FIELD . '"') < strpos($formViewSource, '<?php return; ?>')
        && strpos(substr($formViewSource, strpos($formViewSource, '<?php return; ?>')), 'name="' . MRPR_FIELD . '"') === false,
    'product-recipe token is not leaked into component-formula/product-extra generic form branches'
);
mrpr_check(
    preg_match('/<form method="post" action="<\?php echo site_url\(\'master\/relation\/product-recipe\/delete\//', $listViewSource) === 1
        && strpos($listViewSource, 'name="' . MRPR_FIELD . '"') !== false
        && strpos($listViewSource, "onsubmit=\"return confirm('Hapus relasi ini?')\"") !== false,
    'recipe delete is a confirmed scoped POST form'
);
mrpr_check(
    strpos($listViewSource, 'master/relation/component-formula/delete/') === false
        && strpos($listViewSource, 'master/relation/component-formula/edit/') === false
        && strpos($listViewSource, 'production/component-formulas/edit/') !== false,
    'retired component-formula list uses the canonical editor without a legacy writer'
);
mrpr_check(
    preg_match('/<form method="post" action="<\?php echo site_url\(\'master\/relation\/product-extra\/delete\//', $listViewSource) === 1
        && strpos($listViewSource, 'name="master_relation_product_extra_mutation_csrf"') !== false
        && strpos($listViewSource, "onsubmit=\"return confirm('Hapus mapping ini?')\"") !== false
        && strpos($listViewSource, 'href="<?php echo site_url(\'master/relation/product-extra/delete/') === false,
    'product-extra delete is a confirmed scoped POST form without a GET anchor'
);

if ($failures !== []) {
    fwrite(STDERR, "Master relation product recipe mutation CSRF smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Checks: %d; failures: %d\n", $checks, count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Master relation product recipe mutation CSRF smoke passed (%d checks).\n", $checks));
