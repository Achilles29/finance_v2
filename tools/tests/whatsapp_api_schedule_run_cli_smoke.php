<?php

declare(strict_types=1);

/**
 * DB/network/bootstrap-free smoke test for the CLI-only WA schedule runner.
 *
 * The real controller method and MY_Controller auth gate are exercised with
 * in-memory CI doubles. The CLI success path runs in a subprocess because the
 * existing jsonOut() contract terminates the process after emitting JSON.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class WhatsappScheduleSmokeRedirect extends RuntimeException
{
}

final class WhatsappScheduleSmokeTrace
{
    public static int $notFound = 0;
    public static int $logs = 0;
    public static array $redirects = [];

    public static function reset(): void
    {
        self::$notFound = 0;
        self::$logs = 0;
        self::$redirects = [];
    }
}

function show_404($page = '', $logError = true): void
{
    WhatsappScheduleSmokeTrace::$notFound++;
}

function log_message($level, $message): void
{
    WhatsappScheduleSmokeTrace::$logs++;
}

function uri_string(): string
{
    return 'wa/api/schedule-run';
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
    WhatsappScheduleSmokeTrace::$redirects[] = (string)$uri;
    throw new WhatsappScheduleSmokeRedirect((string)$uri);
}

final class WhatsappScheduleSmokeInput
{
    public int $cliReads = 0;
    public int $methodReads = 0;
    public int $queryReads = 0;
    public int $headerReads = 0;
    public int $ajaxReads = 0;

    private bool $cli;
    private string $requestMethod;
    private string $queryToken;
    private string $headerToken;

    public function __construct(
        bool $cli,
        string $requestMethod = 'GET',
        string $queryToken = '',
        string $headerToken = ''
    ) {
        $this->cli = $cli;
        $this->requestMethod = strtoupper($requestMethod);
        $this->queryToken = $queryToken;
        $this->headerToken = $headerToken;
    }

    public function is_cli_request(): bool
    {
        $this->cliReads++;
        return $this->cli;
    }

    public function method($upper = false): string
    {
        $this->methodReads++;
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function get($key, $xssClean = false): string
    {
        $this->queryReads++;
        return $this->queryToken;
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $this->headerReads++;
        return $this->headerToken;
    }

    public function is_ajax_request(): bool
    {
        $this->ajaxReads++;
        return false;
    }
}

final class WhatsappScheduleSmokeDb
{
    public int $tableExistsCalls = 0;
    public array $otherCalls = [];

    public function table_exists($table): bool
    {
        $this->tableExistsCalls++;
        $this->otherCalls[] = 'table_exists:' . (string)$table;
        return false;
    }

    public function __call($name, $arguments)
    {
        $this->otherCalls[] = (string)$name;
        return $this;
    }
}

final class WhatsappScheduleSmokeSession
{
    public array $reads = [];
    public array $flashWrites = [];

    public function userdata($key)
    {
        $this->reads[] = (string)$key;
        return null;
    }

    public function set_flashdata($key, $value): void
    {
        $this->flashWrites[(string)$key] = (string)$value;
    }
}

final class WhatsappScheduleSmokeRouter
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

final class WhatsappScheduleSmokeOutput
{
    public function set_status_header($status): self
    {
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

class CI_Controller
{
    public $load;
    public $Module_notification_model;
    public $input;
    public $output;
    public $session;
    public $router;
    public $db;

    public function __construct()
    {
    }
}

require dirname(__DIR__, 2) . '/application/core/MY_Controller.php';
require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

function whatsapp_schedule_smoke_controller(WhatsappScheduleSmokeInput $input): array
{
    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $db = new WhatsappScheduleSmokeDb();
    $controller->input = $input;
    $controller->db = $db;
    $controller->output = new WhatsappScheduleSmokeOutput();
    $controller->session = new WhatsappScheduleSmokeSession();
    // Integration worker is exercised against MariaDB by module_notifications_smoke.php.
    $controller->load = new class {
        public function model(string $name): void {
            if ($name !== 'Module_notification_model') throw new RuntimeException('Unexpected model');
        }
    };
    $controller->Module_notification_model = new class {
        public function run(string $channel, callable $send): array {
            if ($channel !== 'WA') throw new RuntimeException('Unexpected channel');
            return ['state' => 'DISABLED'];
        }
    };

    return ['controller' => $controller, 'db' => $db];
}

function whatsapp_schedule_smoke_auth_fixture(bool $cli, string $method, string $requestMethod = 'GET'): array
{
    $reflection = new ReflectionClass(MY_Controller::class);
    /** @var MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $input = new WhatsappScheduleSmokeInput($cli, $requestMethod);
    $session = new WhatsappScheduleSmokeSession();
    $controller->input = $input;
    $controller->session = $session;
    $controller->router = new WhatsappScheduleSmokeRouter('whatsapp', $method);
    $controller->output = new WhatsappScheduleSmokeOutput();

    return ['controller' => $controller, 'input' => $input, 'session' => $session];
}

function whatsapp_schedule_smoke_method_block(string $source, string $method): string
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
    $end = $next === 1 ? (int)$matches[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function whatsapp_schedule_smoke_child_cli(): void
{
    WhatsappScheduleSmokeTrace::reset();
    $input = new WhatsappScheduleSmokeInput(true, 'CLI', 'legacy-query-token', 'legacy-header-token');
    $fixture = whatsapp_schedule_smoke_controller($input);

    register_shutdown_function(static function () use ($input, $fixture): void {
        fwrite(STDERR, json_encode([
            'cli_reads' => $input->cliReads,
            'method_reads' => $input->methodReads,
            'query_reads' => $input->queryReads,
            'header_reads' => $input->headerReads,
            'table_exists_calls' => $fixture['db']->tableExistsCalls,
            'db_calls' => $fixture['db']->otherCalls,
            'not_found' => WhatsappScheduleSmokeTrace::$notFound,
            'logs' => WhatsappScheduleSmokeTrace::$logs,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    });

    $fixture['controller']->api_schedule_run();
}

if (($argv[1] ?? '') === 'cli') {
    whatsapp_schedule_smoke_child_cli();
    exit;
}

$whatsappScheduleSmokeChecks = 0;
$whatsappScheduleSmokeFailures = [];

function whatsapp_schedule_smoke_check(bool $condition, string $message): void
{
    global $whatsappScheduleSmokeChecks, $whatsappScheduleSmokeFailures;
    $whatsappScheduleSmokeChecks++;
    if (!$condition) {
        $whatsappScheduleSmokeFailures[] = $message;
    }
}

$controllerPath = dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';
$corePath = dirname(__DIR__, 2) . '/application/core/MY_Controller.php';
$guidePath = dirname(__DIR__, 2) . '/application/views/wa/guide.php';
$reportPath = dirname(__DIR__, 2) . '/application/views/wa/report_schedule.php';
$controllerSource = (string)file_get_contents($controllerPath);
$coreSource = (string)file_get_contents($corePath);
$guideSource = (string)file_get_contents($guidePath);
$reportSource = (string)file_get_contents($reportPath);
$endpointBlock = whatsapp_schedule_smoke_method_block($controllerSource, 'api_schedule_run');

whatsapp_schedule_smoke_check(
    preg_match(
        '/public function api_schedule_run\(\)\s*\{\s*if \(!\$this->input->is_cli_request\(\)\)/',
        $endpointBlock
    ) === 1,
    'is_cli_request is the first endpoint guard'
);
whatsapp_schedule_smoke_check(
    substr_count($endpointBlock, '$this->runDueWaReportSchedules()') === 1,
    'endpoint invokes the existing scheduler exactly once'
);
whatsapp_schedule_smoke_check(
    strpos($endpointBlock, '$this->input->get(') === false
        && strpos($endpointBlock, 'get_request_header(') === false
        && strpos($endpointBlock, 'waSession(') === false
        && strpos($endpointBlock, 'bot_api_token') === false
        && strpos($endpointBlock, 'hash_equals(') === false,
    'endpoint has no query/header/shared Bot token dependency'
);

$httpCases = [
    ['HTTP GET', 'GET', '', ''],
    ['legacy query token', 'GET', 'legacy-query-token', ''],
    ['HTTP POST', 'POST', '', ''],
    ['legacy header token', 'POST', '', 'legacy-header-token'],
    ['legacy query and header tokens', 'POST', 'legacy-query-token', 'legacy-header-token'],
];
foreach ($httpCases as [$label, $method, $queryToken, $headerToken]) {
    WhatsappScheduleSmokeTrace::reset();
    $input = new WhatsappScheduleSmokeInput(false, $method, $queryToken, $headerToken);
    $fixture = whatsapp_schedule_smoke_controller($input);
    $fixture['controller']->api_schedule_run();

    whatsapp_schedule_smoke_check(
        WhatsappScheduleSmokeTrace::$notFound === 1,
        $label . ' is rejected as not found'
    );
    whatsapp_schedule_smoke_check(
        $input->cliReads === 1
            && $input->methodReads === 0
            && $input->queryReads === 0
            && $input->headerReads === 0,
        $label . ' stops at the CLI guard without method/token reads'
    );
    whatsapp_schedule_smoke_check(
        $fixture['db']->tableExistsCalls === 0
            && $fixture['db']->otherCalls === []
            && WhatsappScheduleSmokeTrace::$logs === 0,
        $label . ' stops before scheduler, DB, Bot, or log flow'
    );
}

$command = [PHP_BINARY, __FILE__, 'cli'];
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$environment = ['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'];
$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2), $environment);
if (is_resource($process)) {
    fclose($pipes[0]);
    $stdout = (string)stream_get_contents($pipes[1]);
    $stderr = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $metadata = json_decode(trim($stderr), true);
    $response = json_decode($stdout, true);

    whatsapp_schedule_smoke_check($exitCode === 0, 'CLI subprocess exits successfully');
    whatsapp_schedule_smoke_check(
        is_array($response)
            && ($response['ok'] ?? null) === false
            && strpos((string)($response['message'] ?? ''), 'wa_report_schedule') !== false,
        'CLI path returns the real scheduler result from the DB double'
    );
    whatsapp_schedule_smoke_check(
        is_array($metadata)
            && ($metadata['cli_reads'] ?? null) === 1
            && ($metadata['method_reads'] ?? null) === 0
            && ($metadata['query_reads'] ?? null) === 0
            && ($metadata['header_reads'] ?? null) === 0,
        'CLI path checks CLI once and never reads request method or legacy tokens'
    );
    whatsapp_schedule_smoke_check(
        is_array($metadata)
            && ($metadata['table_exists_calls'] ?? null) === 1
            && ($metadata['db_calls'] ?? null) === ['table_exists:wa_report_schedule']
            && ($metadata['not_found'] ?? null) === 0
            && ($metadata['logs'] ?? null) === 0,
        'CLI path invokes the scheduler exactly once without network or log side effects'
    );
} else {
    whatsapp_schedule_smoke_check(false, 'CLI subprocess can be started safely');
}

$authCheck = (new ReflectionClass(MY_Controller::class))->getMethod('_check_auth');
$authCheck->setAccessible(true);

WhatsappScheduleSmokeTrace::reset();
$httpAuth = whatsapp_schedule_smoke_auth_fixture(false, 'api_schedule_run');
$redirected = false;
try {
    $authCheck->invoke($httpAuth['controller']);
} catch (WhatsappScheduleSmokeRedirect $e) {
    $redirected = true;
}
whatsapp_schedule_smoke_check(
    $redirected && WhatsappScheduleSmokeTrace::$redirects === ['login'],
    'unauthenticated HTTP schedule runner is no longer anonymous'
);

WhatsappScheduleSmokeTrace::reset();
$groupAuth = whatsapp_schedule_smoke_auth_fixture(false, 'api_group_command', 'POST');
$authCheck->invoke($groupAuth['controller']);
whatsapp_schedule_smoke_check(
    WhatsappScheduleSmokeTrace::$redirects === []
        && $groupAuth['session']->flashWrites === [],
    'existing anonymous HTTP api_group_command contract is preserved'
);

WhatsappScheduleSmokeTrace::reset();
$cliAuth = whatsapp_schedule_smoke_auth_fixture(true, 'api_schedule_run');
$authCheck->invoke($cliAuth['controller']);
whatsapp_schedule_smoke_check(
    WhatsappScheduleSmokeTrace::$redirects === []
        && $cliAuth['session']->flashWrites === [],
    'existing anonymous CLI controller contract is preserved'
);

whatsapp_schedule_smoke_check(
    strpos($coreSource, "['api_schedule_run', 'api_group_command']") === false
        && strpos($coreSource, "\$method === 'api_group_command'") !== false,
    'anonymous allowlist source excludes api_schedule_run and retains api_group_command'
);

foreach (['guide' => $guideSource, 'report schedule' => $reportSource] as $label => $source) {
    whatsapp_schedule_smoke_check(
        strpos($source, 'php index.php whatsapp api_schedule_run') !== false,
        $label . ' documents the local CLI command'
    );
    whatsapp_schedule_smoke_check(
        strpos($source, 'wa/api/schedule-run') === false
            && strpos($source, 'TOKEN_WA_BOT') === false,
        $label . ' has no schedule URL or schedule token instruction'
    );
}

if ($whatsappScheduleSmokeFailures !== []) {
    fwrite(STDERR, "Whatsapp api_schedule_run CLI smoke FAILED\n");
    foreach ($whatsappScheduleSmokeFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'Whatsapp api_schedule_run CLI smoke passed (' . $whatsappScheduleSmokeChecks . " checks).\n";
