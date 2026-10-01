<?php

declare(strict_types=1);

/**
 * Behavior-level, DB/network/bootstrap-free smoke for Production component
 * formula mutation RBAC, POST-only handling, and scoped header-only CSRF.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const PCF_PAGE = 'production.component.formula.index';
const PCF_SESSION_KEY = 'production_component_formula_mutation_csrf';
const PCF_HEADER = 'X-Production-Component-Formula-Csrf';
const PCF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PCF_WRONG_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const PCF_CROSS_SCOPE_TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const PCF_REVISION_FIELD = 'component_formula_revision';
const PCF_REVISION = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

final class ProductionFormulaCsrfDenied extends RuntimeException
{
}

final class ProductionFormulaCsrfTrace
{
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public static function add(string $event): void
    {
        self::$events[] = $event;
    }
}

final class ProductionFormulaCsrfInput
{
    public array $methodUpperFlags = [];
    public array $headerReads = [];
    public array $postReads = [];
    public array $getReads = [];
    public int $rawReads = 0;

    private string $requestMethod;
    private array $headers;
    private array $postData;
    private array $getData;
    private string $rawBody;

    public function __construct(
        string $method,
        array $headers = [],
        array $postData = [],
        array $getData = [],
        string $rawBody = ''
    ) {
        $this->requestMethod = strtoupper($method);
        $this->headers = $headers;
        $this->postData = $postData;
        $this->getData = $getData;
        $this->rawBody = $rawBody;
    }

    public function method($upper = false): string
    {
        $this->methodUpperFlags[] = (bool)$upper;
        ProductionFormulaCsrfTrace::add('input:method:' . ($upper ? 'upper' : 'lower'));
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        ProductionFormulaCsrfTrace::add('input:header:' . $key);
        return (string)($this->headers[$key] ?? '');
    }

    public function post($key = null, $xssClean = false)
    {
        $this->postReads[] = $key;
        ProductionFormulaCsrfTrace::add('input:post:' . ($key === null ? '*' : (string)$key));
        return $key === null ? $this->postData : ($this->postData[(string)$key] ?? null);
    }

    public function get($key = null, $xssClean = false)
    {
        $this->getReads[] = $key;
        ProductionFormulaCsrfTrace::add('input:get:' . ($key === null ? '*' : (string)$key));
        return $key === null ? $this->getData : ($this->getData[(string)$key] ?? null);
    }

    public function __get($key)
    {
        if ((string)$key !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$key);
        }
        $this->rawReads++;
        ProductionFormulaCsrfTrace::add('input:raw');
        return $this->rawBody;
    }
}

final class ProductionFormulaCsrfSession
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
        $key = (string)$key;
        $this->reads[] = $key;
        ProductionFormulaCsrfTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value = null): void
    {
        $key = (string)$key;
        $this->writes[$key] = $value;
        $this->values[$key] = $value;
        ProductionFormulaCsrfTrace::add('session:write:' . $key);
    }
}

final class ProductionFormulaCsrfOutput
{
    public int $status = 200;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        ProductionFormulaCsrfTrace::add('output:status:' . $this->status);
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

final class ProductionFormulaCsrfResult
{
    public function result_array(): array
    {
        return [];
    }
}

final class ProductionFormulaCsrfDb
{
    public array $events = [];

    public function __call($name, $arguments)
    {
        $this->events[] = 'db:' . (string)$name;
        ProductionFormulaCsrfTrace::add('db:' . (string)$name);
        if ((string)$name === 'get') {
            return new ProductionFormulaCsrfResult();
        }
        return $this;
    }
}

final class ProductionFormulaCsrfModel
{
    public array $calls = [];

    public function component_formula_detail($componentId): array
    {
        $this->calls[] = ['detail', (int)$componentId];
        ProductionFormulaCsrfTrace::add('model:detail');
        return [
            'ok' => true,
            'component' => ['id' => (int)$componentId, 'component_name' => 'Fixture'],
            'summary' => [],
            'lines' => [],
        ];
    }

    public function component_formula_revision($componentId): string
    {
        $this->calls[] = ['revision', (int)$componentId];
        ProductionFormulaCsrfTrace::add('model:revision');
        return PCF_REVISION;
    }

    public function save_component_formula(array $payload): array
    {
        $this->calls[] = ['save', $payload];
        ProductionFormulaCsrfTrace::add('model:save');
        return ['ok' => true, 'id' => (int)($payload['id'] ?? 91)];
    }

    public function save_component_formula_bulk($componentId, array $lines, string $revision = '', array $auditContext = []): array
    {
        $this->calls[] = ['bulk', (int)$componentId, $lines, $revision, $auditContext];
        ProductionFormulaCsrfTrace::add('model:bulk');
        return ['ok' => true];
    }

    public function delete_component_formula($id): array
    {
        $this->calls[] = ['delete', (int)$id];
        ProductionFormulaCsrfTrace::add('model:delete');
        return ['ok' => true];
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public $Production_model;
    public array $permissions = [];
    public array $permissionCalls = [];
    public array $canCalls = [];
    public array $current_user = [];
    public ?string $renderedView = null;
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function can($page, $action = 'view'): bool
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->canCalls[] = [$page, $action];
        ProductionFormulaCsrfTrace::add('rbac:can:' . $action);
        return !empty($this->permissions[$page][$action]);
    }

    public function require_permission($page, $action = 'view'): void
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->permissionCalls[] = [$page, $action];
        ProductionFormulaCsrfTrace::add('rbac:require:' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new ProductionFormulaCsrfDenied('permission denied: ' . $action, 403);
        }
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
        ProductionFormulaCsrfTrace::add('render:' . (string)$view);
    }
}

if (!function_exists('show_error')) {
    function show_error($message, $statusCode = 500, $heading = ''): void
    {
        throw new ProductionFormulaCsrfDenied((string)$message, (int)$statusCode);
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Production.php';

$productionFormulaCsrfChecks = 0;
$productionFormulaCsrfFailures = [];

function pcf_check(bool $condition, string $message): void
{
    global $productionFormulaCsrfChecks, $productionFormulaCsrfFailures;
    $productionFormulaCsrfChecks++;
    if (!$condition) {
        $productionFormulaCsrfFailures[] = $message;
    }
}

function pcf_method_source(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    $found = preg_match(
        '/\n    (?:public|protected|private) function /',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = $found === 1 ? (int)$matches[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function pcf_permissions(string $endpoint): array
{
    $action = $endpoint === 'delete' ? 'delete' : ($endpoint === 'bulk' ? 'edit' : 'create');
    return [PCF_PAGE => [$action => true, 'edit' => true]];
}

function pcf_payload(string $endpoint, bool $editSave = false): array
{
    if ($endpoint === 'save') {
        return ['id' => $editSave ? 17 : 0, 'qty' => 2];
    }
    if ($endpoint === 'bulk') {
        return [
            'component_id' => 31,
            PCF_REVISION_FIELD => PCF_REVISION,
            'lines' => [['line_type' => 'MATERIAL', 'qty' => 1]],
        ];
    }
    return ['unused' => true];
}

function pcf_fixture(
    string $endpoint,
    string $method = 'POST',
    ?string $headerToken = PCF_TOKEN,
    ?array $permissions = null,
    array $post = [],
    array $get = [],
    ?array $jsonPayload = null,
    array $session = [PCF_SESSION_KEY => PCF_TOKEN]
): array {
    ProductionFormulaCsrfTrace::reset();
    $payload = $jsonPayload ?? pcf_payload($endpoint);
    $headers = $headerToken === null ? [] : [PCF_HEADER => $headerToken];
    $input = new ProductionFormulaCsrfInput(
        $method,
        $headers,
        $post,
        $get,
        json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)
    );
    $sessionDouble = new ProductionFormulaCsrfSession($session);
    $output = new ProductionFormulaCsrfOutput();
    $db = new ProductionFormulaCsrfDb();
    $model = new ProductionFormulaCsrfModel();
    $reflection = new ReflectionClass(Production::class);
    /** @var Production $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $sessionDouble;
    $controller->output = $output;
    $controller->db = $db;
    $controller->Production_model = $model;
    $controller->permissions = $permissions ?? pcf_permissions($endpoint);
    return [
        'controller' => $controller,
        'input' => $input,
        'session' => $sessionDouble,
        'output' => $output,
        'db' => $db,
        'model' => $model,
    ];
}

function pcf_invoke(array $fixture, string $endpoint): void
{
    if ($endpoint === 'save') {
        $fixture['controller']->component_formula_save();
    } elseif ($endpoint === 'bulk') {
        $fixture['controller']->component_formula_save_bulk();
    } else {
        $fixture['controller']->component_formula_delete(43);
    }
}

function pcf_assert_rejection(array $fixture, int $status, string $label): void
{
    $body = json_decode($fixture['output']->body, true);
    pcf_check($fixture['output']->status === $status, $label . ' returns HTTP ' . $status);
    pcf_check(
        $fixture['output']->contentType === 'application/json'
            && is_array($body)
            && ($body['ok'] ?? null) === false,
        $label . ' returns structured JSON error'
    );
    pcf_check($fixture['model']->calls === [], $label . ' performs no model mutation');
    pcf_check($fixture['db']->events === [], $label . ' performs no direct DB work');
    pcf_check($fixture['input']->rawReads === 0, $label . ' does not parse JSON/raw payload');
}

function pcf_assert_retired_writer(array $fixture, string $label): void
{
    $body = json_decode($fixture['output']->body, true);
    pcf_check($fixture['output']->status === 410, $label . ' returns HTTP 410');
    pcf_check(
        $fixture['output']->contentType === 'application/json'
            && is_array($body)
            && ($body['ok'] ?? null) === false
            && str_contains((string)($body['message'] ?? ''), 'sudah dipensiunkan'),
        $label . ' explains that the one-line writer is retired'
    );
    pcf_check($fixture['model']->calls === [], $label . ' performs no model mutation');
    pcf_check($fixture['db']->events === [], $label . ' performs no direct DB work');
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Production.php');
$viewSource = (string)file_get_contents($root . '/application/views/production/component_formula_edit.php');
$editSource = pcf_method_source($controllerSource, 'component_formula_edit');
$saveSource = pcf_method_source($controllerSource, 'component_formula_save');
$bulkSource = pcf_method_source($controllerSource, 'component_formula_save_bulk');
$deleteSource = pcf_method_source($controllerSource, 'component_formula_delete');
$guardSource = pcf_method_source($controllerSource, 'require_component_formula_mutation_csrf');
$tokenSource = pcf_method_source($controllerSource, 'component_formula_mutation_csrf');

pcf_check(
    strpos($controllerSource, "COMPONENT_FORMULA_MUTATION_CSRF_SESSION_KEY = '" . PCF_SESSION_KEY . "'") !== false,
    'controller declares the scoped session key'
);
pcf_check(
    strpos($controllerSource, "COMPONENT_FORMULA_MUTATION_CSRF_CI_HEADER = '" . PCF_HEADER . "'") !== false,
    'controller declares the exact canonical CI header'
);
pcf_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'token helper generates and validates strict 64-character lowercase hex'
);
pcf_check(
    strpos($guardSource, "method(true) !== 'POST'") !== false
        && strpos($guardSource, 'get_request_header(') !== false
        && strpos($guardSource, 'hash_equals(') !== false,
    'guard enforces exact POST/header/session comparison'
);
pcf_check(
    strpos($guardSource, '->post(') === false
        && strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'request_payload') === false,
    'guard has no query/form/JSON/raw fallback'
);
pcf_check(
    strpos($editSource, "require_permission('" . PCF_PAGE . "', 'edit')") !== false
        && strpos($editSource, 'component_formula_mutation_csrf()') !== false,
    'editor requires edit and receives its scoped token'
);
foreach ([$saveSource, $bulkSource, $deleteSource] as $index => $writerSource) {
    pcf_check(
        substr_count($writerSource, 'require_component_formula_mutation_csrf()') === 1,
        ['save', 'bulk', 'delete'][$index] . ' writer invokes the scoped guard exactly once'
    );
}
pcf_check(
    strpos($saveSource, 'require_component_formula_save_access()') < strpos($saveSource, 'require_component_formula_mutation_csrf()')
        && strpos($saveSource, 'require_component_formula_mutation_csrf()') < strpos($saveSource, 'request_payload()')
        && strpos($saveSource, 'request_payload()') < strpos($saveSource, 'json_error(')
        && strpos($saveSource, '->save_component_formula(') === false,
    'legacy save orders RBAC, guard, payload parse, then retires without model mutation'
);
pcf_check(
    strpos($bulkSource, "require_permission('" . PCF_PAGE . "', 'edit')") < strpos($bulkSource, 'require_component_formula_mutation_csrf()')
        && strpos($bulkSource, 'require_component_formula_mutation_csrf()') < strpos($bulkSource, 'request_payload()')
        && strpos($bulkSource, 'request_payload()') < strpos($bulkSource, 'COMPONENT_FORMULA_REVISION_FIELD')
        && strpos($bulkSource, 'COMPONENT_FORMULA_REVISION_FIELD') < strpos($bulkSource, 'save_component_formula_bulk('),
    'bulk orders edit RBAC, guard, payload parse, revision check, then model mutation'
);
pcf_check(
    strpos($deleteSource, "require_permission('" . PCF_PAGE . "', 'delete')") < strpos($deleteSource, 'require_component_formula_mutation_csrf()')
        && strpos($deleteSource, 'require_component_formula_mutation_csrf()') < strpos($deleteSource, 'json_error(')
        && strpos($deleteSource, '->delete_component_formula(') === false,
    'legacy delete orders delete RBAC, guard, then retires without model mutation'
);
pcf_check(
    strpos($viewSource, "'X-Production-Component-Formula-Csrf':componentFormulaMutationCsrf") !== false
        && strpos($viewSource, "method:'POST'") !== false
        && strpos($viewSource, "'X-Requested-With':'XMLHttpRequest'") !== false,
    'editor save-bulk uses exact POST, CSRF header, and existing XHR header'
);

// View-only users must not reach editor dependencies or receive a token.
$viewOnly = pcf_fixture('bulk', 'GET', null, [PCF_PAGE => ['view' => true]], [], [], []);
$viewDenied = null;
try {
    $viewOnly['controller']->component_formula_edit(12);
} catch (Throwable $exception) {
    $viewDenied = $exception;
}
pcf_check($viewDenied instanceof ProductionFormulaCsrfDenied && $viewDenied->getCode() === 403, 'view-only editor access is denied');
pcf_check($viewOnly['model']->calls === [] && $viewOnly['db']->events === [], 'denied editor access has no model/DB work');
pcf_check($viewOnly['session']->reads === [] && $viewOnly['session']->writes === [], 'denied editor access does not render/generate a token');

// A permitted editor receives only the existing scoped session token after RBAC.
$editor = pcf_fixture('bulk', 'GET', null, [PCF_PAGE => ['edit' => true]], [], [], []);
$editor['controller']->component_formula_edit(12);
pcf_check($editor['controller']->renderedView === 'production/component_formula_edit', 'edit-permitted editor renders the expected view');
pcf_check(
    ($editor['controller']->renderedData[PCF_SESSION_KEY] ?? null) === PCF_TOKEN,
    'editor view data receives the scoped token'
);
pcf_check(
    array_search('rbac:require:edit', ProductionFormulaCsrfTrace::$events, true)
        < array_search('model:detail', ProductionFormulaCsrfTrace::$events, true),
    'editor RBAC runs before model/DB/token work'
);

// Every writer rejects non-POST and all invalid/header-fallback attempts before payload/model/DB.
foreach (['save', 'bulk', 'delete'] as $endpoint) {
    $viewOnlyWriter = pcf_fixture($endpoint, 'POST', PCF_TOKEN, [PCF_PAGE => ['view' => true]]);
    $writerDenied = null;
    try {
        pcf_invoke($viewOnlyWriter, $endpoint);
    } catch (Throwable $exception) {
        $writerDenied = $exception;
    }
    pcf_check(
        $writerDenied instanceof ProductionFormulaCsrfDenied && $writerDenied->getCode() === 403,
        $endpoint . ' denies a view-only user'
    );
    pcf_check(
        $viewOnlyWriter['input']->methodUpperFlags === []
            && $viewOnlyWriter['input']->headerReads === []
            && $viewOnlyWriter['input']->rawReads === 0,
        $endpoint . ' view-only denial occurs before method/header/payload inspection'
    );
    pcf_check(
        $viewOnlyWriter['model']->calls === [] && $viewOnlyWriter['db']->events === [],
        $endpoint . ' view-only denial has no model/DB work'
    );

    $nonPost = pcf_fixture($endpoint, 'GET');
    pcf_invoke($nonPost, $endpoint);
    pcf_assert_rejection($nonPost, 405, $endpoint . ' non-POST');
    pcf_check($nonPost['input']->headerReads === [], $endpoint . ' non-POST stops before header lookup');

    foreach ([
        'missing' => null,
        'empty' => '',
        'malformed' => 'not-hex',
        'wrong' => PCF_WRONG_TOKEN,
        'cross-scope' => PCF_CROSS_SCOPE_TOKEN,
    ] as $label => $headerToken) {
        $invalid = pcf_fixture($endpoint, 'POST', $headerToken);
        pcf_invoke($invalid, $endpoint);
        pcf_assert_rejection($invalid, 403, $endpoint . ' ' . $label . ' header');
        pcf_check($invalid['input']->headerReads === [PCF_HEADER], $endpoint . ' ' . $label . ' reads only the canonical header');
        pcf_check($invalid['input']->methodUpperFlags === [true], $endpoint . ' ' . $label . ' checks the uppercase HTTP method exactly once');
    }

    $fallbackPayload = pcf_payload($endpoint) + [PCF_SESSION_KEY => PCF_TOKEN];
    $fallback = pcf_fixture(
        $endpoint,
        'POST',
        null,
        null,
        [PCF_SESSION_KEY => PCF_TOKEN],
        [PCF_SESSION_KEY => PCF_TOKEN],
        $fallbackPayload
    );
    pcf_invoke($fallback, $endpoint);
    pcf_assert_rejection($fallback, 403, $endpoint . ' form/query/JSON fallback');
    pcf_check($fallback['input']->postReads === [] && $fallback['input']->getReads === [], $endpoint . ' does not inspect form/query fallback');
}

// Valid scoped headers retire old single-line writers, while bulk remains canonical.
$validSaveCreate = pcf_fixture('save');
pcf_invoke($validSaveCreate, 'save');
$validSaveCreate['events'] = ProductionFormulaCsrfTrace::$events;
pcf_assert_retired_writer($validSaveCreate, 'valid create save');
pcf_check(in_array([PCF_PAGE, 'create'], $validSaveCreate['controller']->permissionCalls, true), 'id=0 save enforces create action');

$validSaveEdit = pcf_fixture(
    'save',
    'POST',
    PCF_TOKEN,
    [PCF_PAGE => ['edit' => true]],
    [],
    [],
    pcf_payload('save', true)
);
pcf_invoke($validSaveEdit, 'save');
$validSaveEdit['events'] = ProductionFormulaCsrfTrace::$events;
pcf_assert_retired_writer($validSaveEdit, 'valid update save');
pcf_check(in_array([PCF_PAGE, 'edit'], $validSaveEdit['controller']->permissionCalls, true), 'id>0 save enforces edit action');

$validBulk = pcf_fixture('bulk');
pcf_invoke($validBulk, 'bulk');
$validBulk['events'] = ProductionFormulaCsrfTrace::$events;
pcf_check(($validBulk['model']->calls[0][0] ?? '') === 'bulk', 'valid bulk reaches bulk model');
pcf_check(($validBulk['model']->calls[0][1] ?? 0) === 31, 'valid bulk preserves component payload');
pcf_check(($validBulk['model']->calls[0][3] ?? '') === PCF_REVISION, 'valid bulk preserves its formula revision');

$validDelete = pcf_fixture('delete');
pcf_invoke($validDelete, 'delete');
$validDelete['events'] = ProductionFormulaCsrfTrace::$events;
pcf_assert_retired_writer($validDelete, 'valid delete');

foreach ([$validSaveCreate, $validSaveEdit, $validBulk, $validDelete] as $index => $valid) {
    $label = ['create save', 'edit save', 'bulk', 'delete'][$index];
    pcf_check($valid['input']->headerReads === [PCF_HEADER], 'valid ' . $label . ' reads exact canonical header');
    pcf_check($valid['input']->methodUpperFlags === [true], 'valid ' . $label . ' enforces exact POST method');
    $headerPosition = array_search('input:header:' . PCF_HEADER, $valid['events'], true);
    $terminalEvent = $index === 2 ? 'model:bulk' : 'output:status:410';
    $terminalPosition = array_search($terminalEvent, $valid['events'], true);
    pcf_check(
        $headerPosition !== false && $terminalPosition !== false && $headerPosition < $terminalPosition,
        'valid ' . $label . ' reaches its terminal action only after header guard'
    );
}
pcf_check($validSaveCreate['input']->rawReads === 1 && $validSaveEdit['input']->rawReads === 1, 'valid single saves parse payload only after guard');
pcf_check($validBulk['input']->rawReads === 1, 'valid bulk parses payload only after guard');
pcf_check($validDelete['input']->rawReads === 0, 'valid delete does not parse an unused payload');

if ($productionFormulaCsrfFailures !== []) {
    foreach ($productionFormulaCsrfFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(
        STDERR,
        '[FAIL] Production component formula mutation CSRF smoke: '
        . count($productionFormulaCsrfFailures) . ' failure(s), '
        . $productionFormulaCsrfChecks . ' checks.' . PHP_EOL
    );
    exit(1);
}

echo '[PASS] Production component formula mutation CSRF smoke: '
    . $productionFormulaCsrfChecks
    . ' checks; editor RBAC, all three POST/header-only guards, canonical bulk writer, and retired one-line writers verified without DB/network/bootstrap.'
    . PHP_EOL;
