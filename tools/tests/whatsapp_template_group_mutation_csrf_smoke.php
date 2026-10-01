<?php

declare(strict_types=1);

/**
 * DB-free smoke test for scoped WhatsApp template/group form mutation CSRF.
 *
 * The real controller is exercised with in-memory CI doubles. No application
 * bootstrap, database, upload, bot API, repository secret, or runtime log is
 * accessed.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WHATSAPP_MUTATION_CSRF_FIELD = 'wa_template_group_mutation_csrf';
const WHATSAPP_MUTATION_CSRF_VALID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WHATSAPP_MUTATION_CSRF_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const WHATSAPP_MUTATION_CSRF_ENGINE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const WHATSAPP_MUTATION_CSRF_ENV = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

final class WhatsappMutationCsrfTrace
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

final class WhatsappMutationCsrfDenied extends RuntimeException
{
}

final class WhatsappMutationCsrfInput
{
    public array $postReads = [];

    private string $requestMethod;
    private array $postData;
    private string $queryToken;
    private string $headerToken;
    private string $rawToken;

    public function __construct(
        string $requestMethod,
        array $postData = [],
        string $queryToken = '',
        string $headerToken = '',
        string $rawToken = ''
    ) {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
        $this->queryToken = $queryToken;
        $this->headerToken = $headerToken;
        $this->rawToken = $rawToken;
    }

    public function method($upper = false): string
    {
        WhatsappMutationCsrfTrace::add('method:' . ($upper ? 'upper' : 'lower'));
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key, $xssClean = false)
    {
        $key = (string)$key;
        $this->postReads[] = $key;
        WhatsappMutationCsrfTrace::add('post:' . $key);
        return $this->postData[$key] ?? null;
    }

    public function get($key, $xssClean = false): string
    {
        WhatsappMutationCsrfTrace::add('query:' . (string)$key);
        return $this->queryToken;
    }

    public function get_request_header($key, $xssClean = false): string
    {
        WhatsappMutationCsrfTrace::add('header:' . (string)$key);
        return $this->headerToken;
    }

    public function __get($key)
    {
        if ((string)$key === 'raw_input_stream') {
            WhatsappMutationCsrfTrace::add('raw-body');
            return $this->rawToken;
        }
        throw new RuntimeException('Unexpected input property: ' . (string)$key);
    }
}

final class WhatsappMutationCsrfSession
{
    public array $reads = [];
    public array $writes = [];
    public array $flash = [];

    private array $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $key = (string)$key;
        $this->reads[] = $key;
        WhatsappMutationCsrfTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->values[$key] = $value;
        $this->writes[$key] = (string)$value;
        WhatsappMutationCsrfTrace::add('session:write:' . $key);
    }

    public function set_flashdata($key, $value): void
    {
        $this->flash[(string)$key] = (string)$value;
        WhatsappMutationCsrfTrace::add('flash:' . (string)$key);
    }

    public function value(string $key): ?string
    {
        $value = $this->values[$key] ?? null;
        return is_string($value) ? $value : null;
    }
}

final class WhatsappMutationCsrfOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        WhatsappMutationCsrfTrace::add('output:status:' . $this->status);
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

final class WhatsappMutationCsrfDb
{
    public array $events = [];
    private string $table = '';

    public function from($table): self
    {
        $this->table = (string)$table;
        $this->events[] = 'db:from:' . $this->table;
        WhatsappMutationCsrfTrace::add('db:from:' . $this->table);
        return $this;
    }

    public function where($key, $value = null): self
    {
        $this->events[] = 'db:where:' . (string)$key;
        WhatsappMutationCsrfTrace::add('db:where:' . (string)$key);
        return $this;
    }

    public function limit($limit, $offset = null): self
    {
        $this->events[] = 'db:limit';
        return $this;
    }

    public function order_by($key, $direction = ''): self
    {
        $this->events[] = 'db:order:' . (string)$key;
        return $this;
    }

    public function get($table = ''): self
    {
        if ((string)$table !== '') {
            $this->table = (string)$table;
        }
        $this->events[] = 'db:get:' . $this->table;
        return $this;
    }

    public function result_array(): array
    {
        $this->events[] = 'db:result:' . $this->table;
        return [];
    }

    public function row_array(): array
    {
        $this->events[] = 'db:row:' . $this->table;
        return [];
    }

    public function insert($table, $data): bool
    {
        $this->events[] = 'db:insert:' . (string)$table;
        return true;
    }

    public function update($table, $data): bool
    {
        $this->events[] = 'db:update:' . (string)$table;
        return true;
    }

    public function delete($table): bool
    {
        $this->events[] = 'db:delete:' . (string)$table;
        return true;
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public array $permissions = [];
    public array $permissionCalls = [];
    public ?string $renderedView = null;
    public array $renderedData = [];
    protected array $current_user = ['id' => 101];

    public function __construct()
    {
    }

    public function require_permission($page, $action = 'view'): void
    {
        $page = (string)$page;
        $action = (string)$action;
        $this->permissionCalls[] = [$page, $action];
        WhatsappMutationCsrfTrace::add('rbac:' . $page . ':' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappMutationCsrfDenied('permission denied', 403);
        }
    }

    public function can($page, $action = 'view'): bool
    {
        return !empty($this->permissions[(string)$page][(string)$action]);
    }

    public function render($view, array $data = []): void
    {
        $this->renderedView = (string)$view;
        $this->renderedData = $data;
        WhatsappMutationCsrfTrace::add('render:' . (string)$view);
    }
}

$whatsappMutationCsrfRedirects = [];
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $whatsappMutationCsrfRedirects;
    $whatsappMutationCsrfRedirects[] = (string)$uri;
    WhatsappMutationCsrfTrace::add('redirect:' . (string)$uri);
}

if (!function_exists('site_url')) {
    function site_url($uri = ''): string
    {
        return '/' . ltrim((string)$uri, '/');
    }
}

if (!function_exists('html_escape')) {
    function html_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

$whatsappMutationCsrfChecks = 0;
$whatsappMutationCsrfFailures = [];

function whatsapp_mutation_csrf_check(bool $condition, string $message): void
{
    global $whatsappMutationCsrfChecks, $whatsappMutationCsrfFailures;
    $whatsappMutationCsrfChecks++;
    if (!$condition) {
        $whatsappMutationCsrfFailures[] = $message;
    }
}

function whatsapp_mutation_csrf_method_block(string $source, string $method): string
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
    $end = $next === 1 ? (int)$matches[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

/**
 * @return array{controller:Whatsapp,input:WhatsappMutationCsrfInput,session:WhatsappMutationCsrfSession,output:WhatsappMutationCsrfOutput,db:WhatsappMutationCsrfDb}
 */
function whatsapp_mutation_csrf_controller(
    string $requestMethod,
    array $postData,
    array $permissions,
    array $sessionValues = [],
    string $queryToken = '',
    string $headerToken = '',
    string $rawToken = ''
): array {
    WhatsappMutationCsrfTrace::reset();
    $input = new WhatsappMutationCsrfInput(
        $requestMethod,
        $postData,
        $queryToken,
        $headerToken,
        $rawToken
    );
    $session = new WhatsappMutationCsrfSession($sessionValues);
    $output = new WhatsappMutationCsrfOutput();
    $db = new WhatsappMutationCsrfDb();
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = $db;
    $controller->permissions = $permissions;

    return compact('controller', 'input', 'session', 'output', 'db');
}

function whatsapp_mutation_csrf_rejection(WhatsappMutationCsrfOutput $output, int $status, string $label): void
{
    $decoded = json_decode($output->body, true);
    whatsapp_mutation_csrf_check($output->status === $status, $label . ' responds with HTTP ' . $status);
    whatsapp_mutation_csrf_check(
        $output->contentType === 'application/json'
            && is_array($decoded)
            && ($decoded['ok'] ?? null) === false,
        $label . ' emits a structured rejection response'
    );
}

function whatsapp_mutation_csrf_no_business_access(array $fixture, string $label): void
{
    $businessFields = [
        'template_code', 'name', 'category', 'body', 'sample_variables',
        'group_key', 'group_name', 'group_jid', 'purpose', 'notes', 'message', 'media_image',
    ];
    whatsapp_mutation_csrf_check(
        array_intersect($fixture['input']->postReads, $businessFields) === [],
        $label . ' does not read business or upload payload fields'
    );
    whatsapp_mutation_csrf_check($fixture['db']->events === [], $label . ' performs no DB query or write');
    whatsapp_mutation_csrf_check($fixture['session']->flash === [], $label . ' performs no flash write');
    whatsapp_mutation_csrf_check(
        !in_array('redirect:wa/template', WhatsappMutationCsrfTrace::$events, true)
            && !in_array('redirect:wa/group', WhatsappMutationCsrfTrace::$events, true),
        $label . ' stops before redirect and downstream upload/bot flow'
    );
}

$controllerPath = dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';
$templateViewPath = dirname(__DIR__, 2) . '/application/views/wa/template.php';
$groupViewPath = dirname(__DIR__, 2) . '/application/views/wa/group.php';
$controllerSource = (string)file_get_contents($controllerPath);

$tokenBlock = whatsapp_mutation_csrf_method_block($controllerSource, 'wa_template_group_mutation_csrf');
$guardBlock = whatsapp_mutation_csrf_method_block($controllerSource, 'require_wa_template_group_mutation_csrf');
whatsapp_mutation_csrf_check(
    strpos($tokenBlock, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D', \$token)") !== false
        && strpos($tokenBlock, 'WA_TEMPLATE_GROUP_MUTATION_CSRF_SESSION_KEY') !== false,
    'token helper creates and stores a scoped lowercase 64-hex token'
);
whatsapp_mutation_csrf_check(
    strpos($guardBlock, "method(true) !== 'POST'") !== false
        && strpos($guardBlock, 'reject_wa_template_group_mutation_csrf(405') !== false
        && strpos($guardBlock, 'post(self::WA_TEMPLATE_GROUP_MUTATION_CSRF_FORM_FIELD, false)') !== false
        && substr_count($guardBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D'") === 2
        && strpos($guardBlock, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardBlock, 'reject_wa_template_group_mutation_csrf(403') !== false,
    'guard enforces POST and strict scoped form/session token equality'
);
whatsapp_mutation_csrf_check(
    strpos($guardBlock, 'get_request_header(') === false
        && strpos($guardBlock, '$this->input->get(') === false
        && strpos($guardBlock, 'raw_input_stream') === false
        && strpos($guardBlock, 'WA_ENGINE_CONTROL') === false
        && strpos($guardBlock, 'WA_ENV_SAVE') === false,
    'guard has no query, header, raw-body, Batch 37, or Batch 38 fallback'
);
whatsapp_mutation_csrf_check(
    substr_count($controllerSource, '$this->require_wa_template_group_mutation_csrf()') === 7,
    'scoped guard is called by exactly the seven template/group form mutation variants'
);

$reflection = new ReflectionClass(Whatsapp::class);
$guard = $reflection->getMethod('require_wa_template_group_mutation_csrf');
$guard->setAccessible(true);
$tokenHelper = $reflection->getMethod('wa_template_group_mutation_csrf');
$tokenHelper->setAccessible(true);

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    $fixture = whatsapp_mutation_csrf_controller(
        $method,
        [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID],
        [],
        [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID]
    );
    $result = $guard->invoke($fixture['controller']);
    whatsapp_mutation_csrf_check($result === false, 'guard rejects ' . $method);
    whatsapp_mutation_csrf_rejection($fixture['output'], 405, 'guard ' . $method);
    whatsapp_mutation_csrf_check(
        $fixture['input']->postReads === []
            && $fixture['session']->reads === []
            && $fixture['db']->events === [],
        'guard ' . $method . ' rejects before form/session/business dependencies'
    );
}

$invalidGuardCases = [
    'missing' => ['', [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID]],
    'uppercase' => [str_repeat('A', 64), [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID]],
    'mismatch' => [WHATSAPP_MUTATION_CSRF_OTHER, [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID]],
];
foreach ($invalidGuardCases as $label => [$provided, $sessionValues]) {
    $postData = $provided === '' ? [] : [WHATSAPP_MUTATION_CSRF_FIELD => $provided];
    $fixture = whatsapp_mutation_csrf_controller('POST', $postData, [], $sessionValues);
    $result = $guard->invoke($fixture['controller']);
    whatsapp_mutation_csrf_check($result === false, 'guard rejects ' . $label . ' token');
    whatsapp_mutation_csrf_rejection($fixture['output'], 403, 'guard ' . $label);
    whatsapp_mutation_csrf_check($fixture['db']->events === [], 'guard ' . $label . ' does not access DB');
}

$alternativeOnly = whatsapp_mutation_csrf_controller(
    'POST',
    [],
    [],
    [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID],
    WHATSAPP_MUTATION_CSRF_VALID,
    WHATSAPP_MUTATION_CSRF_VALID,
    WHATSAPP_MUTATION_CSRF_VALID
);
whatsapp_mutation_csrf_check(
    $guard->invoke($alternativeOnly['controller']) === false,
    'query/header/raw-body-only token is rejected'
);
whatsapp_mutation_csrf_rejection($alternativeOnly['output'], 403, 'alternative-only token');
whatsapp_mutation_csrf_check(
    count(array_filter(
        WhatsappMutationCsrfTrace::$events,
        static fn(string $event): bool => strpos($event, 'query:') === 0
            || strpos($event, 'header:') === 0
            || $event === 'raw-body'
    )) === 0,
    'guard never reads query, header, or raw-body alternatives'
);

foreach (
    [
        'Batch 37 engine-control' => ['wa_engine_control_csrf' => WHATSAPP_MUTATION_CSRF_ENGINE],
        'Batch 38 env-save' => ['wa_env_save_csrf' => WHATSAPP_MUTATION_CSRF_ENV],
    ] as $label => $sessionValues
) {
    $foreignToken = reset($sessionValues);
    $fixture = whatsapp_mutation_csrf_controller(
        'POST',
        [WHATSAPP_MUTATION_CSRF_FIELD => $foreignToken],
        [],
        $sessionValues
    );
    whatsapp_mutation_csrf_check($guard->invoke($fixture['controller']) === false, $label . ' token is not accepted');
    whatsapp_mutation_csrf_rejection($fixture['output'], 403, $label . ' isolation');
    whatsapp_mutation_csrf_check(
        $fixture['session']->reads === [WHATSAPP_MUTATION_CSRF_FIELD],
        $label . ' isolation reads only the scoped session key'
    );
}

$validGuard = whatsapp_mutation_csrf_controller(
    'POST',
    [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID],
    [],
    [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID]
);
whatsapp_mutation_csrf_check($guard->invoke($validGuard['controller']) === true, 'valid scoped form token is accepted');
whatsapp_mutation_csrf_check(
    $validGuard['output']->status === null
        && $validGuard['session']->reads === [WHATSAPP_MUTATION_CSRF_FIELD],
    'valid token does not emit a rejection and reads only scoped session state'
);

$generatedToken = whatsapp_mutation_csrf_controller('GET', [], [], []);
$minted = $tokenHelper->invoke($generatedToken['controller']);
whatsapp_mutation_csrf_check(
    is_string($minted)
        && preg_match('/\A[0-9a-f]{64}\z/D', $minted) === 1
        && $generatedToken['session']->value(WHATSAPP_MUTATION_CSRF_FIELD) === $minted,
    'token helper mints lowercase 64-hex from session-local state'
);

$mutationCases = [
    ['template', 'save', 0, 'create', ['action', 'id', WHATSAPP_MUTATION_CSRF_FIELD]],
    ['template', 'toggle', 17, 'edit', ['action', 'id', WHATSAPP_MUTATION_CSRF_FIELD]],
    ['template', 'delete', 17, 'delete', ['action', 'id', WHATSAPP_MUTATION_CSRF_FIELD]],
    ['group', 'save', 0, 'create', ['action', 'id', WHATSAPP_MUTATION_CSRF_FIELD]],
    ['group', 'toggle', 17, 'edit', ['action', 'id', WHATSAPP_MUTATION_CSRF_FIELD]],
    ['group', 'delete', 17, 'delete', ['action', 'id', WHATSAPP_MUTATION_CSRF_FIELD]],
    ['group', 'send_group', 17, 'create', ['action', WHATSAPP_MUTATION_CSRF_FIELD]],
];

foreach ($mutationCases as [$area, $action, $id, $requiredAction, $expectedReads]) {
    $page = 'wa.' . $area;
    $label = $area . ' ' . $action;
    $payload = [
        'action' => $action,
        'id' => $id,
        'template_code' => 'business-sentinel',
        'name' => 'business-sentinel',
        'category' => 'INFO',
        'body' => 'business-sentinel',
        'sample_variables' => '{}',
        'group_key' => 'business-sentinel',
        'group_name' => 'business-sentinel',
        'group_jid' => 'business-sentinel',
        'purpose' => 'business-sentinel',
        'notes' => 'business-sentinel',
        'message' => 'business-sentinel',
    ];
    $fixture = whatsapp_mutation_csrf_controller(
        'POST',
        $payload,
        [$page => ['view' => true, $requiredAction => true]],
        [WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID]
    );
    $fixture['controller']->{$area}();
    whatsapp_mutation_csrf_rejection($fixture['output'], 403, $label . ' missing token');
    whatsapp_mutation_csrf_check(
        $fixture['controller']->permissionCalls === [[$page, 'view'], [$page, $requiredAction]],
        $label . ' retains view then writer RBAC before CSRF'
    );
    whatsapp_mutation_csrf_check(
        $fixture['input']->postReads === $expectedReads,
        $label . ' reads only action/id needed for RBAC before the scoped token'
    );
    whatsapp_mutation_csrf_no_business_access($fixture, $label . ' invalid CSRF');

    $denied = whatsapp_mutation_csrf_controller('POST', $payload, [$page => ['view' => true]]);
    $caught = null;
    try {
        $denied['controller']->{$area}();
    } catch (WhatsappMutationCsrfDenied $exception) {
        $caught = $exception;
    }
    whatsapp_mutation_csrf_check(
        $caught instanceof WhatsappMutationCsrfDenied && $caught->getCode() === 403,
        $label . ' RBAC denial remains authoritative'
    );
    whatsapp_mutation_csrf_check(
        !in_array(WHATSAPP_MUTATION_CSRF_FIELD, $denied['input']->postReads, true)
            && $denied['session']->reads === []
            && $denied['output']->status === null,
        $label . ' RBAC denial precedes every CSRF dependency'
    );
    whatsapp_mutation_csrf_no_business_access($denied, $label . ' denied RBAC');
}

foreach (['template', 'group'] as $area) {
    $page = 'wa.' . $area;
    $viewOnly = whatsapp_mutation_csrf_controller('GET', [], [$page => ['view' => true]]);
    $viewOnly['controller']->{$area}();
    whatsapp_mutation_csrf_check(
        $viewOnly['controller']->permissionCalls === [[$page, 'view']]
            && $viewOnly['controller']->renderedView === 'wa/' . $area,
        $area . ' GET remains view-only and does not call the mutation guard'
    );
    whatsapp_mutation_csrf_check(
        !array_key_exists(WHATSAPP_MUTATION_CSRF_FIELD, $viewOnly['controller']->renderedData)
            && $viewOnly['session']->reads === []
            && $viewOnly['session']->writes === []
            && !in_array('post:' . WHATSAPP_MUTATION_CSRF_FIELD, WhatsappMutationCsrfTrace::$events, true),
        $area . ' view-only GET neither mints nor renders the writer token'
    );

    $writer = whatsapp_mutation_csrf_controller(
        'GET',
        [],
        [$page => ['view' => true, 'create' => true]]
    );
    $writer['controller']->{$area}();
    $renderedToken = $writer['controller']->renderedData[WHATSAPP_MUTATION_CSRF_FIELD] ?? '';
    whatsapp_mutation_csrf_check(
        is_string($renderedToken)
            && preg_match('/\A[0-9a-f]{64}\z/D', $renderedToken) === 1
            && $writer['session']->value(WHATSAPP_MUTATION_CSRF_FIELD) === $renderedToken,
        $area . ' writer GET mints and passes the scoped form token'
    );
}

final class WhatsappMutationCsrfViewSession
{
    public function flashdata($key)
    {
        return null;
    }
}

final class WhatsappMutationCsrfViewHarness
{
    public WhatsappMutationCsrfViewSession $session;

    public function __construct()
    {
        $this->session = new WhatsappMutationCsrfViewSession();
    }

    public function render(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string)ob_get_clean();
    }
}

$templateFixture = [[
    'id' => 17,
    'is_active' => 1,
    'category' => 'INFO',
    'name' => 'Synthetic Template',
    'template_code' => 'SYNTHETIC',
    'body' => 'Synthetic body',
    'sample_variables' => null,
]];
$groupFixture = [[
    'id' => 17,
    'is_active' => 1,
    'group_name' => 'Synthetic Group',
    'group_key' => 'SYNTHETIC',
    'group_jid' => 'synthetic-group@g.us',
    'purpose' => 'Synthetic',
    'notes' => 'Synthetic notes',
    'last_sent_at' => null,
]];
$viewHarness = new WhatsappMutationCsrfViewHarness();
$viewCases = [
    ['template', $templateViewPath, ['templates' => $templateFixture], ['save', 'toggle', 'delete']],
    ['group', $groupViewPath, ['groups' => $groupFixture], ['save', 'toggle', 'delete', 'send_group']],
];
foreach ($viewCases as [$area, $path, $fixtureData, $expectedActions]) {
    $writerHtml = $viewHarness->render($path, array_merge($fixtureData, [
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
        WHATSAPP_MUTATION_CSRF_FIELD => WHATSAPP_MUTATION_CSRF_VALID,
    ]));
    preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/si', $writerHtml, $formMatches);
    $seenActions = [];
    $allFormsScoped = true;
    foreach ($formMatches[1] ?? [] as $formBody) {
        if (preg_match('/name="action"\s+value="([a-z_]+)"/', $formBody, $actionMatch) !== 1) {
            continue;
        }
        $seenActions[] = $actionMatch[1];
        $allFormsScoped = $allFormsScoped
            && strpos(
                $formBody,
                'name="' . WHATSAPP_MUTATION_CSRF_FIELD . '" value="' . WHATSAPP_MUTATION_CSRF_VALID . '"'
            ) !== false;
    }
    sort($seenActions);
    sort($expectedActions);
    whatsapp_mutation_csrf_check(
        $seenActions === $expectedActions && $allFormsScoped,
        $area . ' renders the scoped hidden field in every POST form variant'
    );

    $viewOnlyHtml = $viewHarness->render($path, array_merge($fixtureData, [
        'can_create' => false,
        'can_edit' => false,
        'can_delete' => false,
    ]));
    whatsapp_mutation_csrf_check(
        strpos($viewOnlyHtml, 'name="' . WHATSAPP_MUTATION_CSRF_FIELD . '"') === false,
        $area . ' does not render the token for a view-only role'
    );
}

if ($whatsappMutationCsrfFailures !== []) {
    foreach ($whatsappMutationCsrfFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(
        STDERR,
        '[FAIL] WhatsApp template/group mutation CSRF smoke: '
        . count($whatsappMutationCsrfFailures) . ' failure(s), '
        . $whatsappMutationCsrfChecks . ' checks.' . PHP_EOL
    );
    exit(1);
}

echo '[PASS] WhatsApp template/group mutation CSRF smoke: '
    . $whatsappMutationCsrfChecks
    . ' checks; seven scoped form mutations verified without DB/network/bootstrap.'
    . PHP_EOL;
