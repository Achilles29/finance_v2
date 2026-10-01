<?php

declare(strict_types=1);

/**
 * Bootstrap/DB/network/secret-free behavioral smoke for WA group commands.
 *
 * The real api_group_command() method runs with in-memory CodeIgniter doubles.
 * Each behavior case uses a subprocess because the existing jsonOut() contract
 * emits the response directly and exits.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const WA_GROUP_COMMAND_SMOKE_TOKEN = 'synthetic-group-command-token';
const WA_GROUP_COMMAND_SMOKE_HEADER = 'X-Finance-Group-Command-Token';

final class WhatsappGroupCommandSmokeTrace
{
    public static array $events = [];

    public static function add(string $event): void
    {
        self::$events[] = $event;
    }
}

final class WhatsappGroupCommandSmokeInput
{
    private string $command;

    public function __construct(string $command)
    {
        $this->command = $command;
    }

    public function get($key, $xssClean = false): string
    {
        WhatsappGroupCommandSmokeTrace::add('input:get:' . (string)$key);
        return '';
    }

    public function get_request_header($key, $xssClean = false): string
    {
        WhatsappGroupCommandSmokeTrace::add('input:header:' . (string)$key);
        return (string)$key === WA_GROUP_COMMAND_SMOKE_HEADER
            ? WA_GROUP_COMMAND_SMOKE_TOKEN
            : '';
    }

    public function method($upper = false): string
    {
        WhatsappGroupCommandSmokeTrace::add('input:method');
        return $upper ? 'POST' : 'post';
    }

    public function __get($key)
    {
        if ((string)$key !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$key);
        }

        WhatsappGroupCommandSmokeTrace::add('input:raw-body');
        return json_encode([
            'group_jid' => 'synthetic-active-group@g.us',
            'command' => $this->command,
        ], JSON_UNESCAPED_SLASHES);
    }
}

final class WhatsappGroupCommandSmokeDb
{
    private string $table = '';

    private function chain(string $method, string $detail = ''): self
    {
        WhatsappGroupCommandSmokeTrace::add(
            'db:' . $method . ($detail !== '' ? ':' . $detail : '')
        );
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
        return in_array((string)$table, [
            'fin_company_account',
            'fin_account_mutation_log',
        ], true);
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

final class WhatsappGroupCommandSmokeLoader
{
    public function model($name): void
    {
        WhatsappGroupCommandSmokeTrace::add('load:model:' . (string)$name);
        throw new RuntimeException('Mutation model must not be loaded by this smoke.');
    }
}

class MY_Controller
{
    public $input;
    public $db;
    public $load;

    public function __construct()
    {
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';

function whatsapp_group_command_smoke_method_block(string $source, string $method): string
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

function whatsapp_group_command_smoke_child(string $case): void
{
    $commands = [
        'out' => 'mutasi out TUNAI 25000 beli bensin',
        'in' => '/MUTASI IN TUNAI 50000 setoran owner',
        'transfer' => '.mutasi transfer TUNAI MANDIRI 100000 setor bank',
        'report' => 'mutasi kemarin',
    ];
    if (!isset($commands[$case])) {
        fwrite(STDERR, "Unknown smoke case.\n");
        exit(2);
    }

    $reflection = new ReflectionClass(Whatsapp::class);
    /** @var Whatsapp $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new WhatsappGroupCommandSmokeInput($commands[$case]);
    $controller->db = new WhatsappGroupCommandSmokeDb();
    $controller->load = new WhatsappGroupCommandSmokeLoader();

    register_shutdown_function(static function () use ($case): void {
        fwrite(STDERR, json_encode([
            'case' => $case,
            'events' => WhatsappGroupCommandSmokeTrace::$events,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    });

    $controller->api_group_command();
}

if (($argv[1] ?? '') === 'child') {
    whatsapp_group_command_smoke_child((string)($argv[2] ?? ''));
    exit;
}

$whatsappGroupCommandSmokeChecks = 0;
$whatsappGroupCommandSmokeFailures = [];

function whatsapp_group_command_smoke_check(bool $condition, string $message): void
{
    global $whatsappGroupCommandSmokeChecks, $whatsappGroupCommandSmokeFailures;
    $whatsappGroupCommandSmokeChecks++;
    if (!$condition) {
        $whatsappGroupCommandSmokeFailures[] = $message;
    }
}

function whatsapp_group_command_smoke_run_case(string $case): array
{
    $command = [PHP_BINARY, __FILE__, 'child', $case];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = [
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        'FINANCE_WA_ENGINE_COMMAND_TOKEN' => WA_GROUP_COMMAND_SMOKE_TOKEN,
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2), $environment);
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

$controllerPath = dirname(__DIR__, 2) . '/application/controllers/Whatsapp.php';
$viewPath = dirname(__DIR__, 2) . '/application/views/wa/report_schedule.php';
$controllerSource = (string)file_get_contents($controllerPath);
$viewSource = (string)file_get_contents($viewPath);
$endpointBlock = whatsapp_group_command_smoke_method_block($controllerSource, 'api_group_command');
$menuBlock = whatsapp_group_command_smoke_method_block($controllerSource, 'waCommandMenuMessage');

whatsapp_group_command_smoke_check(
    strpos($endpointBlock, 'handleWaMutationInputCommand(') === false
        && strpos($endpointBlock, 'parseWaCompactMutationCommand(') === false
        && strpos($endpointBlock, 'resolveWaFinanceAccount(') === false
        && strpos($endpointBlock, "load->model('Purchase_model')") === false,
    'endpoint delegates posting to the guarded dispatcher rather than calling the parser/model directly'
);
whatsapp_group_command_smoke_check(
    strpos($endpointBlock, '$this->buildWaFinanceMutationReport($date)') !== false,
    'read-only mutasi command retains the existing finance mutation report dispatch'
);

$mutationCases = ['out', 'in', 'transfer'];
foreach ($mutationCases as $case) {
    $result = whatsapp_group_command_smoke_run_case($case);
    $response = $result['response'];
    $events = is_array($result['metadata']) ? ($result['metadata']['events'] ?? []) : [];
    $eventText = implode("\n", is_array($events) ? $events : []);

    whatsapp_group_command_smoke_check(
        $result['exit_code'] === 0
            && is_array($response)
            && ($response['ok'] ?? null) === true,
        'active group mutasi ' . $case . ' keeps the successful callback response contract'
    );
    whatsapp_group_command_smoke_check(
        strpos((string)($response['message'] ?? ''), 'admin grup') !== false,
        'active group mutasi ' . $case . ' without verified sender context is denied'
    );
    whatsapp_group_command_smoke_check(
        strpos($eventText, 'input:method') !== false
            && strpos($eventText, 'input:header:' . WA_GROUP_COMMAND_SMOKE_HEADER) !== false
            && strpos($eventText, 'db:from:wa_session') === false
            && strpos($eventText, 'db:from:wa_group_map') !== false
            && strpos($eventText, 'db:table_exists:') === false
            && strpos($eventText, 'db:from:fin_company_account') === false
            && strpos($eventText, 'db:from:fin_account_mutation_log') === false,
        'active group mutasi ' . $case . ' stops after token/group checks and before mutation queries'
    );
    whatsapp_group_command_smoke_check(
        strpos($eventText, 'load:model:') === false
            && strpos($eventText, 'db:write:') === false,
        'active group mutasi ' . $case . ' performs no model load, mutation, or audit write'
    );
}

$reportResult = whatsapp_group_command_smoke_run_case('report');
$reportResponse = $reportResult['response'];
$reportEvents = is_array($reportResult['metadata']) ? ($reportResult['metadata']['events'] ?? []) : [];
$reportEventText = implode("\n", is_array($reportEvents) ? $reportEvents : []);
whatsapp_group_command_smoke_check(
    $reportResult['exit_code'] === 0
        && is_array($reportResponse)
        && ($reportResponse['ok'] ?? null) === true
        && strpos((string)($reportResponse['message'] ?? ''), 'Mutasi Rekening') !== false,
    'read-only mutasi report is still accepted and returns the existing report response'
);
whatsapp_group_command_smoke_check(
    strpos($reportEventText, 'db:from:fin_account_mutation_log l') !== false
        && strpos($reportEventText, 'load:model:') === false
        && strpos($reportEventText, 'db:write:') === false,
    'read-only mutasi report executes report queries without mutation/model writes'
);

whatsapp_group_command_smoke_check(
    strpos($menuBlock, '*mutasi* - daftar mutasi rekening hari ini.') !== false
        && strpos($menuBlock, 'mutasi in TUNAI') === false
        && strpos($menuBlock, 'mutasi out TUNAI') === false
        && strpos($menuBlock, 'mutasi transfer TUNAI') === false
        && strpos($menuBlock, '*mutasi bantuan*') !== false,
    'WhatsApp menu retains read-only reports and links guarded mutation help'
);
whatsapp_group_command_smoke_check(
    strpos($viewSource, '<code>mutasi</code>') !== false
        && strpos($viewSource, '<code>mutasi kemarin</code>') !== false
        && strpos($viewSource, '<code>mutasi in ') !== false
        && strpos($viewSource, '<code>mutasi out ') !== false
        && strpos($viewSource, '<code>mutasi transfer ') !== false
        && strpos($viewSource, 'admin grup aktif') !== false,
    'command guide documents posting formats and the admin-only requirement'
);

if ($whatsappGroupCommandSmokeFailures !== []) {
    fwrite(STDERR, "Whatsapp group mutation disabled smoke FAILED\n");
    foreach ($whatsappGroupCommandSmokeFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'Whatsapp group mutation disabled smoke passed ('
    . $whatsappGroupCommandSmokeChecks
    . " checks).\n";
