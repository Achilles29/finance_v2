<?php

declare(strict_types=1);

/**
 * DB/network/bootstrap/secret-free behavior smoke for Batch 50 service auth.
 * Endpoint cases run in isolated subprocesses because jsonOut() exits.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_SERVICE_AUTH_SMOKE_TOKEN = 'synthetic-service-auth-token';
const WA_SERVICE_AUTH_NEW_HEADER = 'X-Finance-Group-Command-Token';
const WA_SERVICE_AUTH_LEGACY_HEADER = 'X-Sync-Token';

final class WaServiceAuthSmokeRedirect extends RuntimeException
{
}

final class WaServiceAuthSmokeTrace
{
    public static array $events = [];
    public static ?int $status = null;

    public static function add(string $event): void
    {
        self::$events[] = $event;
    }

    public static function reset(): void
    {
        self::$events = [];
        self::$status = null;
    }
}

function uri_string(): string
{
    return 'wa/api/group-command';
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
    WaServiceAuthSmokeTrace::add('redirect:' . (string)$uri);
    throw new WaServiceAuthSmokeRedirect((string)$uri);
}

final class WaServiceAuthSmokeInput
{
    private bool $cli;
    private string $requestMethod;
    private string $queryToken;
    private string $newHeader;
    private string $legacyHeader;
    private string $command;

    public function __construct(
        string $requestMethod,
        string $queryToken = '',
        string $newHeader = '',
        string $legacyHeader = '',
        string $command = 'menu',
        bool $cli = false
    ) {
        $this->cli = $cli;
        $this->requestMethod = strtoupper($requestMethod);
        $this->queryToken = $queryToken;
        $this->newHeader = $newHeader;
        $this->legacyHeader = $legacyHeader;
        $this->command = $command;
    }

    public function is_cli_request(): bool
    {
        WaServiceAuthSmokeTrace::add('input:cli');
        return $this->cli;
    }

    public function is_ajax_request(): bool
    {
        WaServiceAuthSmokeTrace::add('input:ajax');
        return false;
    }

    public function method($upper = false): string
    {
        WaServiceAuthSmokeTrace::add('input:method');
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get($key, $xssClean = false): string
    {
        WaServiceAuthSmokeTrace::add('input:get:' . (string)$key);
        return (string)$key === 'token' ? $this->queryToken : '';
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        WaServiceAuthSmokeTrace::add('input:header:' . $key);
        if ($key === WA_SERVICE_AUTH_NEW_HEADER) {
            return $this->newHeader;
        }
        if ($key === WA_SERVICE_AUTH_LEGACY_HEADER) {
            return $this->legacyHeader;
        }
        return '';
    }

    public function __get($key)
    {
        if ((string)$key !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$key);
        }

        WaServiceAuthSmokeTrace::add('input:raw-body');
        return json_encode([
            'group_jid' => 'synthetic-active-group@g.us',
            'command' => $this->command,
        ], JSON_UNESCAPED_SLASHES);
    }
}

final class WaServiceAuthSmokeOutput
{
    public function set_status_header($status): self
    {
        WaServiceAuthSmokeTrace::$status = (int)$status;
        WaServiceAuthSmokeTrace::add('output:status:' . (int)$status);
        return $this;
    }

    public function set_content_type($contentType): self
    {
        return $this;
    }

    public function set_output($body): self
    {
        return $this;
    }

    public function _display(): void
    {
    }
}

final class WaServiceAuthSmokeSession
{
    public function userdata($key)
    {
        WaServiceAuthSmokeTrace::add('session:read:' . (string)$key);
        return null;
    }

    public function set_flashdata($key, $value): void
    {
        WaServiceAuthSmokeTrace::add('session:flash:' . (string)$key);
    }
}

final class WaServiceAuthSmokeRouter
{
    private string $class;
    private string $method;

    public function __construct(string $class, string $method)
    {
        $this->class = $class;
        $this->method = $method;
    }

    public function fetch_class(): string
    {
        return $this->class;
    }

    public function fetch_method(): string
    {
        return $this->method;
    }
}

final class WaServiceAuthSmokeDb
{
    private string $table = '';

    private function chain(string $method, string $detail = ''): self
    {
        WaServiceAuthSmokeTrace::add('db:' . $method . ($detail !== '' ? ':' . $detail : ''));
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
        $detail = (string)$key;
        if (is_scalar($value)) {
            $detail .= '=' . (string)$value;
        }
        return $this->chain('where', $detail);
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

    public function row_array(): array
    {
        $this->chain('row', $this->table);
        if ($this->table === 'wa_group_map') {
            return [
                'id' => 17,
                'group_jid' => 'synthetic-active-group@g.us',
                'group_name' => 'Synthetic Active Group',
                'is_active' => 1,
            ];
        }
        if ($this->table === 'fin_account_mutation_log l') {
            return [
                'total_in' => 0,
                'total_out' => 0,
                'mutation_count' => 0,
            ];
        }
        return [];
    }

    public function result_array(): array
    {
        $this->chain('result', $this->table);
        return [];
    }

    public function table_exists($table): bool
    {
        $this->chain('table_exists', (string)$table);
        return in_array((string)$table, ['fin_company_account', 'fin_account_mutation_log'], true);
    }

    public function field_exists($field, $table): bool
    {
        $this->chain('field_exists', (string)$table . '.' . (string)$field);
        return false;
    }

    public function insert($table, $data): bool
    {
        $this->chain('write:insert', (string)$table);
        return true;
    }

    public function update($table, $data): bool
    {
        $this->chain('write:update', (string)$table);
        return true;
    }

    public function delete($table): bool
    {
        $this->chain('write:delete', (string)$table);
        return true;
    }
}

final class WaServiceAuthSmokeLoader
{
    public function model($name): void
    {
        WaServiceAuthSmokeTrace::add('load:model:' . (string)$name);
        throw new RuntimeException('No model load is permitted in this smoke.');
    }
}

class CI_Controller
{
    public $input;
    public $output;
    public $session;
    public $router;
    public $db;
    public $load;

    public function __construct()
    {
    }
}

require dirname(__DIR__, 2) . '/application/core/MY_Controller.php';
// Keep the real guard behavior, but never read production secrets in a smoke test.
$fixtureSecretPath = __FILE__ . '.missing-secret';
if (file_exists($fixtureSecretPath)) throw new RuntimeException('Unexpected smoke secret fixture');
$fixtureSource = (string) file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php');
$fixtureSource = str_replace(
    "private const WA_ENGINE_SECRET_FILE = '/etc/finance-wa-engine.env';",
    'private const WA_ENGINE_SECRET_FILE = ' . var_export($fixtureSecretPath, true) . ';',
    $fixtureSource,
    $fixtureReplacements
);
if ($fixtureReplacements !== 1) throw new RuntimeException('Could not isolate service secret file');
eval(substr($fixtureSource, strlen('<?php')));

function wa_service_auth_smoke_controller(WaServiceAuthSmokeInput $input): Whatsapp
{
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->output = new WaServiceAuthSmokeOutput();
    $controller->session = new WaServiceAuthSmokeSession();
    $controller->db = new WaServiceAuthSmokeDb();
    $controller->load = new WaServiceAuthSmokeLoader();
    return $controller;
}

function wa_service_auth_smoke_child(string $case): void
{
    $fixtures = [
        'get' => new WaServiceAuthSmokeInput('GET'),
        'put' => new WaServiceAuthSmokeInput('PUT'),
        'delete' => new WaServiceAuthSmokeInput('DELETE'),
        'legacy-query' => new WaServiceAuthSmokeInput('POST', WA_SERVICE_AUTH_SMOKE_TOKEN, WA_SERVICE_AUTH_SMOKE_TOKEN),
        'legacy-header' => new WaServiceAuthSmokeInput('POST', '', WA_SERVICE_AUTH_SMOKE_TOKEN, WA_SERVICE_AUTH_SMOKE_TOKEN),
        'missing-header' => new WaServiceAuthSmokeInput('POST'),
        'wrong-header' => new WaServiceAuthSmokeInput('POST', '', 'wrong-synthetic-token'),
        'empty-config' => new WaServiceAuthSmokeInput('POST', '', WA_SERVICE_AUTH_SMOKE_TOKEN),
        'menu' => new WaServiceAuthSmokeInput('POST', '', WA_SERVICE_AUTH_SMOKE_TOKEN, '', 'menu'),
        'report' => new WaServiceAuthSmokeInput('POST', '', WA_SERVICE_AUTH_SMOKE_TOKEN, '', 'mutasi kemarin'),
        'mutation' => new WaServiceAuthSmokeInput('POST', '', WA_SERVICE_AUTH_SMOKE_TOKEN, '', 'mutasi out TUNAI 25000 bensin'),
    ];
    if (!isset($fixtures[$case])) {
        fwrite(STDERR, "Unknown service-auth smoke case.\n");
        exit(2);
    }

    WaServiceAuthSmokeTrace::reset();
    $controller = wa_service_auth_smoke_controller($fixtures[$case]);
    register_shutdown_function(static function () use ($case): void {
        fwrite(STDERR, json_encode([
            'case' => $case,
            'status' => WaServiceAuthSmokeTrace::$status,
            'events' => WaServiceAuthSmokeTrace::$events,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    });
    $controller->api_group_command();
}

if (($argv[1] ?? '') === 'child') {
    wa_service_auth_smoke_child((string)($argv[2] ?? ''));
    exit;
}

$waServiceAuthChecks = 0;
$waServiceAuthFailures = [];

function wa_service_auth_check(bool $condition, string $message): void
{
    global $waServiceAuthChecks, $waServiceAuthFailures;
    $waServiceAuthChecks++;
    if (!$condition) {
        $waServiceAuthFailures[] = $message;
    }
}

function wa_service_auth_run_case(string $case, bool $configured = true): array
{
    $environment = ['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'];
    if ($configured) {
        $environment['FINANCE_WA_ENGINE_COMMAND_TOKEN'] = WA_SERVICE_AUTH_SMOKE_TOKEN;
    }
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        [PHP_BINARY, __FILE__, 'child', $case],
        $descriptors,
        $pipes,
        dirname(__DIR__, 2),
        $environment
    );
    if (!is_resource($process)) {
        return ['exit_code' => -1, 'response' => null, 'metadata' => null];
    }
    fclose($pipes[0]);
    $stdout = (string)stream_get_contents($pipes[1]);
    $stderr = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'exit_code' => proc_close($process),
        'response' => json_decode(trim($stdout), true),
        'metadata' => json_decode(trim($stderr), true),
    ];
}

function wa_service_auth_event_text(array $result): string
{
    $events = is_array($result['metadata']) ? ($result['metadata']['events'] ?? []) : [];
    return implode("\n", is_array($events) ? $events : []);
}

foreach (['get', 'put', 'delete'] as $case) {
    $result = wa_service_auth_run_case($case);
    $events = wa_service_auth_event_text($result);
    wa_service_auth_check(
        $result['exit_code'] === 0
            && ($result['metadata']['status'] ?? null) === 405
            && ($result['response']['ok'] ?? null) === false,
        strtoupper($case) . ' is rejected with HTTP 405'
    );
    wa_service_auth_check(
        $events === "input:method\noutput:status:405",
        strtoupper($case) . ' stops before token, body, group DB, and report dependencies'
    );
}

foreach (['legacy-query', 'legacy-header'] as $case) {
    $result = wa_service_auth_run_case($case);
    $events = wa_service_auth_event_text($result);
    wa_service_auth_check(
        $result['exit_code'] === 0
            && ($result['metadata']['status'] ?? null) === 403
            && ($result['response']['ok'] ?? null) === false,
        $case . ' is rejected with HTTP 403 even beside a valid new header'
    );
    wa_service_auth_check(
        strpos($events, 'input:raw-body') === false
            && strpos($events, 'db:') === false
            && strpos($events, 'load:model:') === false
            && strpos($events, 'input:header:' . WA_SERVICE_AUTH_NEW_HEADER) === false,
        $case . ' stops before new credential acceptance, parser, DB, and report work'
    );
}

foreach ([
    ['empty-config', false],
    ['missing-header', true],
    ['wrong-header', true],
] as [$case, $configured]) {
    $result = wa_service_auth_run_case($case, $configured);
    $events = wa_service_auth_event_text($result);
    wa_service_auth_check(
        $result['exit_code'] === 0
            && ($result['metadata']['status'] ?? null) === 403
            && ($result['response']['ok'] ?? null) === false,
        $case . ' fails closed with HTTP 403'
    );
    wa_service_auth_check(
        strpos($events, 'input:raw-body') === false
            && strpos($events, 'db:') === false
            && strpos($events, 'session:') === false,
        $case . ' stops before parser, session token, group DB, and reports'
    );
}

$menu = wa_service_auth_run_case('menu');
$menuEvents = wa_service_auth_event_text($menu);
wa_service_auth_check(
    $menu['exit_code'] === 0
        && ($menu['metadata']['status'] ?? null) === null
        && ($menu['response']['ok'] ?? null) === true
        && strpos((string)($menu['response']['message'] ?? ''), 'Synthetic Active Group') !== false,
    'valid POST and new header preserve the active-group menu response'
);
wa_service_auth_check(
    strpos($menuEvents, 'input:header:' . WA_SERVICE_AUTH_NEW_HEADER) !== false
        && strpos($menuEvents, 'input:raw-body') !== false
        && strpos($menuEvents, 'db:from:wa_group_map') !== false
        && strpos($menuEvents, 'db:where:group_jid=synthetic-active-group@g.us') !== false
        && strpos($menuEvents, 'db:where:is_active=1') !== false
        && strpos($menuEvents, 'db:from:wa_session') === false,
    'valid menu uses canonical header and preserves exact active group mapping without wa_session'
);

$report = wa_service_auth_run_case('report');
$reportEvents = wa_service_auth_event_text($report);
wa_service_auth_check(
    $report['exit_code'] === 0
        && ($report['response']['ok'] ?? null) === true
        && strpos((string)($report['response']['message'] ?? ''), 'Mutasi Rekening') !== false,
    'valid POST and new header preserve the read-only mutasi report'
);
wa_service_auth_check(
    strpos($reportEvents, 'db:from:fin_account_mutation_log l') !== false
        && strpos($reportEvents, 'db:write:') === false
        && strpos($reportEvents, 'load:model:') === false,
    'read-only report runs without mutation or model writes'
);

$mutation = wa_service_auth_run_case('mutation');
$mutationEvents = wa_service_auth_event_text($mutation);
wa_service_auth_check(
    $mutation['exit_code'] === 0
        && ($mutation['response']['ok'] ?? null) === true
        && strpos((string)($mutation['response']['message'] ?? ''), 'admin grup') !== false,
    'service credential alone cannot post a mutation without verified sender context'
);
wa_service_auth_check(
    strpos($mutationEvents, 'db:from:wa_group_map') !== false
        && strpos($mutationEvents, 'db:from:fin_account_mutation_log') === false
        && strpos($mutationEvents, 'db:write:') === false
        && strpos($mutationEvents, 'load:model:') === false,
    'mutation denial stops after active mapping and before finance mutation dependencies'
);

$authCheck = (new ReflectionClass(MY_Controller::class))->getMethod('_check_auth');
$authCheck->setAccessible(true);

$authCases = [
    ['exact POST', false, 'POST', 'whatsapp', 'api_group_command', false],
    ['exact GET', false, 'GET', 'whatsapp', 'api_group_command', true],
    ['other controller', false, 'POST', 'other', 'api_group_command', true],
    ['other method', false, 'POST', 'whatsapp', 'api_group_commands', true],
    ['CLI preserved', true, 'CLI', 'whatsapp', 'api_schedule_run', false],
];
foreach ($authCases as [$label, $cli, $method, $class, $routeMethod, $shouldRedirect]) {
    WaServiceAuthSmokeTrace::reset();
    $reflection = new ReflectionClass(MY_Controller::class);
    /** @var MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WaServiceAuthSmokeInput($method, '', '', '', 'menu', $cli);
    $controller->output = new WaServiceAuthSmokeOutput();
    $controller->session = new WaServiceAuthSmokeSession();
    $controller->router = new WaServiceAuthSmokeRouter($class, $routeMethod);
    $redirected = false;
    try {
        $authCheck->invoke($controller);
    } catch (WaServiceAuthSmokeRedirect $e) {
        $redirected = true;
    }
    wa_service_auth_check(
        $redirected === $shouldRedirect,
        'anonymous allowlist behavior: ' . $label
    );
}

$controllerSource = (string)file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php');
$endpointStart = strpos($controllerSource, 'public function api_group_command()');
$endpointEnd = strpos($controllerSource, 'private function require_wa_group_command_service_auth()', $endpointStart ?: 0);
$endpointBlock = ($endpointStart !== false && $endpointEnd !== false)
    ? substr($controllerSource, $endpointStart, $endpointEnd - $endpointStart)
    : '';
wa_service_auth_check(
    strpos($endpointBlock, 'require_wa_group_command_service_auth()') !== false
        && strpos($endpointBlock, 'waSession(') === false
        && strpos($endpointBlock, 'bot_api_token') === false,
    'endpoint delegates first to service auth and has no wa_session token fallback'
);

$guardStart = strpos($controllerSource, 'private function require_wa_group_command_service_auth()');
$guardEnd = strpos($controllerSource, 'private function reject_wa_group_command_service_auth(', $guardStart ?: 0);
$guardBlock = ($guardStart !== false && $guardEnd !== false)
    ? substr($controllerSource, $guardStart, $guardEnd - $guardStart)
    : '';
wa_service_auth_check(
    strpos($guardBlock, 'waEngineSecretValue(self::WA_GROUP_COMMAND_TOKEN_ENV)') !== false
        && strpos($guardBlock, 'get_request_header(self::WA_GROUP_COMMAND_TOKEN_CI_HEADER, true)') !== false
        && strpos($guardBlock, "\$this->input->get('token', true)") !== false
        && strpos($guardBlock, 'get_request_header(self::WA_GROUP_COMMAND_LEGACY_CI_HEADER, true)') !== false
        && strpos($guardBlock, 'waSession(') === false
        && strpos($guardBlock, 'bot_api_token') === false
        && strpos($guardBlock, 'local-dev-token') === false,
    'service guard uses its dedicated secret reader/new header and explicitly rejects legacy transports'
);

$callBotStart = strpos($controllerSource, 'private function callBotApi(');
$callBotEnd = strpos($controllerSource, "\n    private function ", ($callBotStart === false ? 0 : $callBotStart + 1));
$callBotBlock = ($callBotStart !== false && $callBotEnd !== false)
    ? substr($controllerSource, $callBotStart, $callBotEnd - $callBotStart)
    : '';
wa_service_auth_check(
    strpos($callBotBlock, 'waEngineSecretValue(self::WA_ENGINE_API_TOKEN_ENV)') !== false
        && strpos($callBotBlock, "self::WA_ENGINE_API_TOKEN_HEADER . ': ' . \$serviceToken") !== false
        && strpos($callBotBlock, '?token=') === false
        && strpos($callBotBlock, 'X-Sync-Token') === false,
    'Finance-to-engine auth uses its separate process credential and header'
);

$secretStart = strpos($controllerSource, 'private function waEngineSecretValue(');
$secretEnd = strpos($controllerSource, "\n    private function ", $secretStart + 1);
$secretBlock = substr($controllerSource, $secretStart, $secretEnd - $secretStart);
wa_service_auth_check(
    strpos($secretBlock, 'getenv($key)') !== false
        && strpos($secretBlock, 'self::WA_ENGINE_SECRET_FILE') !== false
        && strpos($secretBlock, 'getenv($key)') < strpos($secretBlock, 'self::WA_ENGINE_SECRET_FILE')
        && strpos($secretBlock, 'waSession(') === false
        && strpos($secretBlock, 'bot_api_token') === false,
    'secret reader prefers process configuration then the protected external file, never database credentials'
);

$buildEnvStart = strpos($controllerSource, 'private function buildEnvString(');
$buildEnvEnd = strpos($controllerSource, "\n    private function ", ($buildEnvStart === false ? 0 : $buildEnvStart + 1));
$buildEnvBlock = ($buildEnvStart !== false && $buildEnvEnd !== false)
    ? substr($controllerSource, $buildEnvStart, $buildEnvEnd - $buildEnvStart)
    : '';
wa_service_auth_check(
    strpos($buildEnvBlock, '$k === self::WA_GROUP_COMMAND_TOKEN_ENV') !== false
        && strpos($buildEnvBlock, '$k === self::WA_ENGINE_API_TOKEN_ENV') !== false
        && strpos($buildEnvBlock, 'continue;') !== false,
    'PHP launcher does not import either process-only service credential from web-root .env'
);

if ($waServiceAuthFailures !== []) {
    fwrite(STDERR, "Whatsapp group command service-auth smoke FAILED\n");
    foreach ($waServiceAuthFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'Whatsapp group command service-auth smoke passed (' . $waServiceAuthChecks . " checks).\n";
