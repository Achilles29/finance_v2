<?php

declare(strict_types=1);

/**
 * DB-free smoke test for scoped WhatsApp report-schedule form mutation CSRF.
 *
 * The real controller is loaded without its constructor and exercised with
 * in-memory CI doubles. No application bootstrap, DB/network connection,
 * repository secret, runtime log, or Bot API is accessed.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_REPORT_SCHEDULE_CSRF_FIELD = 'wa_report_schedule_mutation_csrf';
const WA_REPORT_SCHEDULE_CSRF_VALID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WA_REPORT_SCHEDULE_CSRF_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const WA_REPORT_SCHEDULE_CSRF_ENGINE = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
const WA_REPORT_SCHEDULE_CSRF_ENV = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
const WA_REPORT_SCHEDULE_CSRF_TEMPLATE_GROUP = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

final class WhatsappReportScheduleCsrfTrace
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

final class WhatsappReportScheduleCsrfDenied extends RuntimeException
{
}

final class WhatsappReportScheduleCsrfInput
{
    public array $postReads = [];
    public array $getReads = [];
    public array $headerReads = [];
    public int $rawReads = 0;
    public int $ajaxReads = 0;

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
        WhatsappReportScheduleCsrfTrace::add('method:' . ($upper ? 'upper' : 'lower'));
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key, $xssClean = false)
    {
        $key = (string)$key;
        $this->postReads[] = $key;
        WhatsappReportScheduleCsrfTrace::add('post:' . $key);
        return $this->postData[$key] ?? null;
    }

    public function get($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->getReads[] = $key;
        WhatsappReportScheduleCsrfTrace::add('query:' . $key);
        return $this->queryToken;
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        WhatsappReportScheduleCsrfTrace::add('header:' . $key);
        return $this->headerToken;
    }

    public function is_ajax_request(): bool
    {
        $this->ajaxReads++;
        WhatsappReportScheduleCsrfTrace::add('ajax');
        return true;
    }

    public function __get($key)
    {
        if ((string)$key === 'raw_input_stream') {
            $this->rawReads++;
            WhatsappReportScheduleCsrfTrace::add('raw-body');
            return $this->rawToken;
        }
        throw new RuntimeException('Unexpected input property: ' . (string)$key);
    }
}

final class WhatsappReportScheduleCsrfSession
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
        WhatsappReportScheduleCsrfTrace::add('session:read:' . $key);
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value): void
    {
        $key = (string)$key;
        $this->values[$key] = $value;
        $this->writes[$key] = (string)$value;
        WhatsappReportScheduleCsrfTrace::add('session:write:' . $key);
    }

    public function set_flashdata($key, $value): void
    {
        $this->flash[(string)$key] = (string)$value;
        WhatsappReportScheduleCsrfTrace::add('flash:' . (string)$key);
    }

    public function flashdata($key)
    {
        return null;
    }

    public function value(string $key): ?string
    {
        $value = $this->values[$key] ?? null;
        return is_string($value) ? $value : null;
    }
}

final class WhatsappReportScheduleCsrfOutput
{
    public ?int $status = null;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header($status): self
    {
        $this->status = (int)$status;
        WhatsappReportScheduleCsrfTrace::add('output:status:' . $this->status);
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

final class WhatsappReportScheduleCsrfDb
{
    public array $events = [];
    private string $table = '';

    private function chain(string $method, string $detail = ''): self
    {
        $event = 'db:' . $method . ($detail !== '' ? ':' . $detail : '');
        $this->events[] = $event;
        WhatsappReportScheduleCsrfTrace::add($event);
        return $this;
    }

    public function from($table): self
    {
        $this->table = (string)$table;
        return $this->chain('from', $this->table);
    }

    public function select($select, $escape = null): self
    {
        return $this->chain('select');
    }

    public function join($table, $condition, $type = ''): self
    {
        return $this->chain('join', (string)$table);
    }

    public function where($key, $value = null, $escape = null): self
    {
        return $this->chain('where', (string)$key);
    }

    public function group_start(): self
    {
        return $this->chain('group_start');
    }

    public function group_end(): self
    {
        return $this->chain('group_end');
    }

    public function like($key, $value): self
    {
        return $this->chain('like', (string)$key);
    }

    public function or_like($key, $value): self
    {
        return $this->chain('or_like', (string)$key);
    }

    public function order_by($key, $direction = ''): self
    {
        return $this->chain('order_by', (string)$key);
    }

    public function limit($limit, $offset = null): self
    {
        return $this->chain('limit');
    }

    public function get($table = ''): self
    {
        if ((string)$table !== '') {
            $this->table = (string)$table;
        }
        return $this->chain('get', $this->table);
    }

    public function count_all_results(): int
    {
        $this->chain('count', $this->table);
        return 0;
    }

    public function result_array(): array
    {
        $this->chain('result', $this->table);
        return [];
    }

    public function row_array(): array
    {
        $this->chain('row', $this->table);
        return ['id' => 17, 'is_active' => 1];
    }

    public function insert($table, $data): bool
    {
        $this->chain('insert', (string)$table);
        return true;
    }

    public function update($table, $data): bool
    {
        $this->chain('update', (string)$table);
        return true;
    }

    public function delete($table): bool
    {
        $this->chain('delete', (string)$table);
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
        WhatsappReportScheduleCsrfTrace::add('rbac:' . $page . ':' . $action);
        if (empty($this->permissions[$page][$action])) {
            throw new WhatsappReportScheduleCsrfDenied('permission denied', 403);
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
        WhatsappReportScheduleCsrfTrace::add('render:' . (string)$view);
    }
}

$whatsappReportScheduleCsrfRedirects = [];
function redirect($uri = '', $method = 'auto', $code = null): void
{
    global $whatsappReportScheduleCsrfRedirects;
    $whatsappReportScheduleCsrfRedirects[] = (string)$uri;
    WhatsappReportScheduleCsrfTrace::add('redirect:' . (string)$uri);
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

$whatsappReportScheduleCsrfChecks = 0;
$whatsappReportScheduleCsrfFailures = [];

function wa_report_schedule_csrf_check(bool $condition, string $message): void
{
    global $whatsappReportScheduleCsrfChecks, $whatsappReportScheduleCsrfFailures;
    $whatsappReportScheduleCsrfChecks++;
    if (!$condition) {
        $whatsappReportScheduleCsrfFailures[] = $message;
    }
}

function wa_report_schedule_csrf_method_block(string $source, string $method): string
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
 * @return array{controller:Whatsapp,input:WhatsappReportScheduleCsrfInput,session:WhatsappReportScheduleCsrfSession,output:WhatsappReportScheduleCsrfOutput,db:WhatsappReportScheduleCsrfDb}
 */
function wa_report_schedule_csrf_controller(
    string $requestMethod,
    array $postData,
    array $permissions,
    array $sessionValues = [],
    string $queryToken = '',
    string $headerToken = '',
    string $rawToken = ''
): array {
    WhatsappReportScheduleCsrfTrace::reset();
    $input = new WhatsappReportScheduleCsrfInput(
        $requestMethod,
        $postData,
        $queryToken,
        $headerToken,
        $rawToken
    );
    $session = new WhatsappReportScheduleCsrfSession($sessionValues);
    $output = new WhatsappReportScheduleCsrfOutput();
    $db = new WhatsappReportScheduleCsrfDb();
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

function wa_report_schedule_csrf_rejection(
    WhatsappReportScheduleCsrfOutput $output,
    int $status,
    string $label
): void {
    $decoded = json_decode($output->body, true);
    wa_report_schedule_csrf_check($output->status === $status, $label . ' responds with HTTP ' . $status);
    wa_report_schedule_csrf_check(
        $output->contentType === 'application/json'
            && is_array($decoded)
            && ($decoded['ok'] ?? null) === false,
        $label . ' emits a structured rejection response'
    );
}

function wa_report_schedule_csrf_no_business_access(array $fixture, string $label): void
{
    $businessFields = [
        'name', 'report_type', 'template_id', 'group_id', 'send_time',
        'date_offset_days', 'notes', 'is_active',
    ];
    wa_report_schedule_csrf_check(
        array_intersect($fixture['input']->postReads, $businessFields) === [],
        $label . ' does not read business payload fields'
    );
    wa_report_schedule_csrf_check($fixture['db']->events === [], $label . ' performs no DB query or write');
    wa_report_schedule_csrf_check($fixture['session']->flash === [], $label . ' performs no flash write');
    wa_report_schedule_csrf_check($fixture['input']->ajaxReads === 0, $label . ' stops before send-now response flow');
    wa_report_schedule_csrf_check(
        !in_array('redirect:wa/template/schedules', WhatsappReportScheduleCsrfTrace::$events, true),
        $label . ' stops before downstream redirect/Bot API flow'
    );
}

$controllerPath = dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';
$viewPath = dirname(__DIR__, 2) . '/application/views/wa/report_schedule.php';
$controllerSource = (string)file_get_contents($controllerPath);
$viewSource = (string)file_get_contents($viewPath);
$tokenBlock = wa_report_schedule_csrf_method_block($controllerSource, 'wa_report_schedule_mutation_csrf');
$guardBlock = wa_report_schedule_csrf_method_block($controllerSource, 'require_wa_report_schedule_mutation_csrf');
$reportBlock = wa_report_schedule_csrf_method_block($controllerSource, 'report_schedules');

wa_report_schedule_csrf_check(
    strpos($tokenBlock, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D', \$token)") !== false
        && strpos($tokenBlock, 'WA_REPORT_SCHEDULE_MUTATION_CSRF_SESSION_KEY') !== false,
    'token helper creates and stores a scoped lowercase 64-hex token'
);
wa_report_schedule_csrf_check(
    strpos($guardBlock, "method(true) !== 'POST'") !== false
        && strpos($guardBlock, 'reject_wa_report_schedule_mutation_csrf(405') !== false
        && strpos($guardBlock, 'post(self::WA_REPORT_SCHEDULE_MUTATION_CSRF_FORM_FIELD, false)') !== false
        && substr_count($guardBlock, "preg_match('/\\A[0-9a-f]{64}\\z/D'") === 2
        && strpos($guardBlock, 'hash_equals($sessionToken, $providedToken)') !== false
        && strpos($guardBlock, 'reject_wa_report_schedule_mutation_csrf(403') !== false,
    'guard enforces POST and strict scoped form/session token equality'
);
wa_report_schedule_csrf_check(
    strpos($guardBlock, 'get_request_header(') === false
        && strpos($guardBlock, '$this->input->get(') === false
        && strpos($guardBlock, 'raw_input_stream') === false
        && strpos($guardBlock, 'WA_ENGINE_CONTROL') === false
        && strpos($guardBlock, 'WA_ENV_SAVE') === false
        && strpos($guardBlock, 'WA_TEMPLATE_GROUP') === false,
    'guard has no query, header, raw-body, or Batch 37-39 fallback'
);
wa_report_schedule_csrf_check(
    substr_count($reportBlock, '$this->require_wa_report_schedule_mutation_csrf()') === 4,
    'scoped guard is called by exactly four report-schedule form mutations'
);

$sendBranch = strpos($reportBlock, "elseif (\$action === 'send_now')");
$sendRbac = strpos($reportBlock, "require_permission(self::PAGE_REPORT_SCHEDULE, 'edit')", (int)$sendBranch);
$sendGuard = strpos($reportBlock, '$this->require_wa_report_schedule_mutation_csrf()', (int)$sendRbac);
$sendCall = strpos($reportBlock, '$this->sendWaReportSchedule($id, true)', (int)$sendGuard);
wa_report_schedule_csrf_check(
    $sendBranch !== false && $sendRbac !== false && $sendGuard !== false && $sendCall !== false
        && $sendBranch < $sendRbac && $sendRbac < $sendGuard && $sendGuard < $sendCall,
    'send_now keeps edit RBAC then CSRF before sendWaReportSchedule'
);

$reflection = new ReflectionClass(Whatsapp::class);
$guard = $reflection->getMethod('require_wa_report_schedule_mutation_csrf');
$guard->setAccessible(true);
$tokenHelper = $reflection->getMethod('wa_report_schedule_mutation_csrf');
$tokenHelper->setAccessible(true);

foreach (['GET', 'PUT', 'PATCH'] as $method) {
    $fixture = wa_report_schedule_csrf_controller(
        $method,
        [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID],
        [],
        [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID]
    );
    wa_report_schedule_csrf_check($guard->invoke($fixture['controller']) === false, 'guard rejects ' . $method);
    wa_report_schedule_csrf_rejection($fixture['output'], 405, 'guard ' . $method);
    wa_report_schedule_csrf_check(
        $fixture['input']->postReads === []
            && $fixture['session']->reads === []
            && $fixture['db']->events === []
            && $fixture['session']->flash === []
            && $fixture['input']->ajaxReads === 0,
        'guard ' . $method . ' rejects before form/session/payload/query/write/flash/Bot API flow'
    );
}

$invalidGuardCases = [
    'missing' => [[], [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID]],
    'uppercase' => [[WA_REPORT_SCHEDULE_CSRF_FIELD => str_repeat('A', 64)], [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID]],
    'mismatch' => [[WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_OTHER], [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID]],
    'malformed session' => [[WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID], [WA_REPORT_SCHEDULE_CSRF_FIELD => 'short']],
];
foreach ($invalidGuardCases as $label => [$postData, $sessionValues]) {
    $fixture = wa_report_schedule_csrf_controller('POST', $postData, [], $sessionValues);
    wa_report_schedule_csrf_check($guard->invoke($fixture['controller']) === false, 'guard rejects ' . $label . ' token');
    wa_report_schedule_csrf_rejection($fixture['output'], 403, 'guard ' . $label);
    wa_report_schedule_csrf_check(
        $fixture['db']->events === [] && $fixture['session']->flash === [] && $fixture['input']->ajaxReads === 0,
        'guard ' . $label . ' rejects before query/write/flash/Bot API flow'
    );
}

$alternativeOnly = wa_report_schedule_csrf_controller(
    'POST',
    [],
    [],
    [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID],
    WA_REPORT_SCHEDULE_CSRF_VALID,
    WA_REPORT_SCHEDULE_CSRF_VALID,
    WA_REPORT_SCHEDULE_CSRF_VALID
);
wa_report_schedule_csrf_check(
    $guard->invoke($alternativeOnly['controller']) === false,
    'query/header/raw-body-only token is rejected'
);
wa_report_schedule_csrf_rejection($alternativeOnly['output'], 403, 'alternative-only token');
wa_report_schedule_csrf_check(
    $alternativeOnly['input']->getReads === []
        && $alternativeOnly['input']->headerReads === []
        && $alternativeOnly['input']->rawReads === 0,
    'guard never reads query, header, or raw-body alternatives'
);

foreach (
    [
        'Batch 37 engine-control' => ['wa_engine_control_csrf' => WA_REPORT_SCHEDULE_CSRF_ENGINE],
        'Batch 38 env-save' => ['wa_env_save_csrf' => WA_REPORT_SCHEDULE_CSRF_ENV],
        'Batch 39 template/group' => ['wa_template_group_mutation_csrf' => WA_REPORT_SCHEDULE_CSRF_TEMPLATE_GROUP],
    ] as $label => $sessionValues
) {
    $foreignToken = (string)reset($sessionValues);
    $fixture = wa_report_schedule_csrf_controller(
        'POST',
        [WA_REPORT_SCHEDULE_CSRF_FIELD => $foreignToken],
        [],
        $sessionValues
    );
    wa_report_schedule_csrf_check($guard->invoke($fixture['controller']) === false, $label . ' token is not accepted');
    wa_report_schedule_csrf_rejection($fixture['output'], 403, $label . ' isolation');
    wa_report_schedule_csrf_check(
        $fixture['session']->reads === [WA_REPORT_SCHEDULE_CSRF_FIELD],
        $label . ' isolation reads only the report-schedule session key'
    );
}

$validGuard = wa_report_schedule_csrf_controller(
    'POST',
    [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID],
    [],
    [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID]
);
wa_report_schedule_csrf_check($guard->invoke($validGuard['controller']) === true, 'valid scoped form token is accepted');
wa_report_schedule_csrf_check(
    $validGuard['output']->status === null
        && $validGuard['session']->reads === [WA_REPORT_SCHEDULE_CSRF_FIELD],
    'valid token emits no rejection and reads only scoped session state'
);

$generatedToken = wa_report_schedule_csrf_controller('GET', [], [], []);
$minted = $tokenHelper->invoke($generatedToken['controller']);
wa_report_schedule_csrf_check(
    is_string($minted)
        && preg_match('/\A[0-9a-f]{64}\z/D', $minted) === 1
        && $generatedToken['session']->value(WA_REPORT_SCHEDULE_CSRF_FIELD) === $minted,
    'token helper mints lowercase 64-hex from form-session scoped state'
);

$mutationCases = [
    ['save create', 'save_schedule', 0, 'create'],
    ['save edit', 'save_schedule', 17, 'edit'],
    ['toggle', 'toggle_schedule', 17, 'edit'],
    ['delete', 'delete_schedule', 17, 'delete'],
    ['send_now', 'send_now', 17, 'edit'],
];
foreach ($mutationCases as [$label, $action, $id, $requiredAction]) {
    $payload = [
        'action' => $action,
        'id' => $id,
        'name' => 'business-sentinel',
        'report_type' => 'OMZET_TODAY',
        'template_id' => 9,
        'group_id' => 12,
        'send_time' => ['21:00'],
        'date_offset_days' => 0,
        'notes' => 'business-sentinel',
        'is_active' => 1,
    ];
    $fixture = wa_report_schedule_csrf_controller(
        'POST',
        $payload,
        ['wa.report_schedule' => ['view' => true, $requiredAction => true]],
        [WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID]
    );
    $fixture['controller']->report_schedules();
    wa_report_schedule_csrf_rejection($fixture['output'], 403, $label . ' missing token');
    wa_report_schedule_csrf_check(
        $fixture['controller']->permissionCalls === [
            ['wa.report_schedule', 'view'],
            ['wa.report_schedule', $requiredAction],
        ],
        $label . ' retains view gate then writer RBAC before CSRF'
    );
    wa_report_schedule_csrf_check(
        $fixture['input']->postReads === ['action', 'id', WA_REPORT_SCHEDULE_CSRF_FIELD],
        $label . ' reads only action/id needed for RBAC before the scoped form token'
    );
    wa_report_schedule_csrf_no_business_access($fixture, $label . ' invalid CSRF');

    $denied = wa_report_schedule_csrf_controller(
        'POST',
        $payload,
        ['wa.report_schedule' => ['view' => true]]
    );
    $caught = null;
    try {
        $denied['controller']->report_schedules();
    } catch (WhatsappReportScheduleCsrfDenied $exception) {
        $caught = $exception;
    }
    wa_report_schedule_csrf_check(
        $caught instanceof WhatsappReportScheduleCsrfDenied && $caught->getCode() === 403,
        $label . ' writer RBAC denial remains authoritative'
    );
    wa_report_schedule_csrf_check(
        $denied['controller']->permissionCalls === [
            ['wa.report_schedule', 'view'],
            ['wa.report_schedule', $requiredAction],
        ]
            && $denied['input']->postReads === ['action', 'id']
            && $denied['session']->reads === []
            && $denied['output']->status === null,
        $label . ' RBAC denial precedes every CSRF dependency'
    );
    wa_report_schedule_csrf_no_business_access($denied, $label . ' denied RBAC');
}

$viewOnly = wa_report_schedule_csrf_controller(
    'GET',
    [],
    ['wa.report_schedule' => ['view' => true]]
);
$viewOnly['controller']->report_schedules();
wa_report_schedule_csrf_check(
    $viewOnly['controller']->permissionCalls === [['wa.report_schedule', 'view']]
        && $viewOnly['controller']->renderedView === 'wa/report_schedule',
    'GET remains view-only and does not call a mutation permission or guard'
);
wa_report_schedule_csrf_check(
    !array_key_exists(WA_REPORT_SCHEDULE_CSRF_FIELD, $viewOnly['controller']->renderedData)
        && $viewOnly['session']->reads === []
        && $viewOnly['session']->writes === [],
    'view-only GET neither mints nor provides the writer token'
);

foreach (['create', 'edit', 'delete'] as $writerAction) {
    $writer = wa_report_schedule_csrf_controller(
        'GET',
        [],
        ['wa.report_schedule' => ['view' => true, $writerAction => true]]
    );
    $writer['controller']->report_schedules();
    $renderedToken = $writer['controller']->renderedData[WA_REPORT_SCHEDULE_CSRF_FIELD] ?? '';
    wa_report_schedule_csrf_check(
        is_string($renderedToken)
            && preg_match('/\A[0-9a-f]{64}\z/D', $renderedToken) === 1
            && $writer['session']->value(WA_REPORT_SCHEDULE_CSRF_FIELD) === $renderedToken,
        $writerAction . ' role alone receives the scoped form token'
    );
}

final class WhatsappReportScheduleCsrfViewHarness
{
    public WhatsappReportScheduleCsrfSession $session;

    public function __construct()
    {
        $this->session = new WhatsappReportScheduleCsrfSession();
    }

    public function render(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string)ob_get_clean();
    }
}

$scheduleFixture = [[
    'id' => 17,
    'name' => 'Synthetic Schedule',
    'report_type' => 'OMZET_TODAY',
    'template_id' => 9,
    'template_name' => 'Synthetic Template',
    'group_id' => 12,
    'group_name' => 'Synthetic Group',
    'group_jid' => 'synthetic-group@g.us',
    'send_time' => '21:00:00',
    'date_offset_days' => 0,
    'is_active' => 1,
    'notes' => '',
    'last_status' => null,
]];
$viewData = [
    'schedules' => $scheduleFixture,
    'templates' => [[
        'id' => 9,
        'name' => 'Synthetic Template',
        'template_code' => 'SYNTHETIC',
    ]],
    'groups' => [[
        'id' => 12,
        'group_name' => 'Synthetic Group',
        'group_jid' => 'synthetic-group@g.us',
    ]],
    'report_types' => ['OMZET_TODAY' => 'Omzet Hari Ini'],
    'filters' => [],
    'pg' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'total_pages' => 1, 'offset' => 0],
];
$viewHarness = new WhatsappReportScheduleCsrfViewHarness();
$writerHtml = $viewHarness->render($viewPath, array_merge($viewData, [
    'can_create' => true,
    'can_edit' => true,
    'can_delete' => true,
    WA_REPORT_SCHEDULE_CSRF_FIELD => WA_REPORT_SCHEDULE_CSRF_VALID,
]));
preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/si', $writerHtml, $formMatches);
$seenActions = [];
$allFormsScoped = true;
$sendNowScoped = false;
foreach ($formMatches[1] ?? [] as $formBody) {
    if (preg_match('/name="action"\s+value="([a-z_]+)"/', $formBody, $actionMatch) !== 1) {
        continue;
    }
    $action = $actionMatch[1];
    $seenActions[] = $action;
    $hasScopedField = strpos(
        $formBody,
        'name="' . WA_REPORT_SCHEDULE_CSRF_FIELD . '" value="' . WA_REPORT_SCHEDULE_CSRF_VALID . '"'
    ) !== false;
    $allFormsScoped = $allFormsScoped && $hasScopedField;
    if ($action === 'send_now') {
        $sendNowScoped = $hasScopedField;
    }
}
sort($seenActions);
$expectedActions = ['delete_schedule', 'save_schedule', 'send_now', 'toggle_schedule'];
wa_report_schedule_csrf_check(
    $seenActions === $expectedActions && $allFormsScoped,
    'all four rendered report-schedule POST forms contain the scoped hidden field'
);
wa_report_schedule_csrf_check(
    $sendNowScoped
        && strpos($viewSource, 'body: new FormData(form)') !== false
        && strpos($viewSource, "method: 'POST'") !== false,
    'send_now AJAX submits its form, including the scoped hidden token, through FormData'
);

$viewOnlyHtml = $viewHarness->render($viewPath, array_merge($viewData, [
    'can_create' => false,
    'can_edit' => false,
    'can_delete' => false,
]));
wa_report_schedule_csrf_check(
    strpos($viewOnlyHtml, 'name="' . WA_REPORT_SCHEDULE_CSRF_FIELD . '"') === false,
    'view-only role does not render the scoped token'
);

if ($whatsappReportScheduleCsrfFailures !== []) {
    foreach ($whatsappReportScheduleCsrfFailures as $failure) {
        fwrite(STDERR, '[FAIL] ' . $failure . PHP_EOL);
    }
    fwrite(
        STDERR,
        '[FAIL] WhatsApp report-schedule mutation CSRF smoke: '
        . count($whatsappReportScheduleCsrfFailures) . ' failure(s), '
        . $whatsappReportScheduleCsrfChecks . ' checks.' . PHP_EOL
    );
    exit(1);
}

echo '[PASS] WhatsApp report-schedule mutation CSRF smoke: '
    . $whatsappReportScheduleCsrfChecks
    . ' checks; four scoped form mutations verified without DB/network/bootstrap.'
    . PHP_EOL;
