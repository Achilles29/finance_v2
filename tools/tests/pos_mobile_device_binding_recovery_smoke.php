<?php

// Focused recovery smoke with in-memory fakes; every assertion is collected.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

if (!class_exists('CI_Controller')) {
    class CI_Controller
    {
        public $Auth_model;
        public $Pos_model;
        public $Pos_print_model;
        public $db;
        public $input;
        public $output;
        public $session;
        public $load;
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos_mobile.php';

$checks = 0;
$failures = [];
function pmr_check(bool $ok, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    } else {
        echo 'PASS: ' . $message . PHP_EOL;
    }
}

final class PmrTrace
{
    public array $events = [];
}

final class PmrAuth
{
    public function __construct(private PmrTrace $trace, private ?array $user)
    {
    }

    public function attempt_login(string $identifier, string $password): ?array
    {
        $this->trace->events[] = 'credential';
        return $this->user;
    }

    public function load_permissions(int $userId): array
    {
        $this->trace->events[] = 'permissions';
        return ['pos.cashier.index' => ['can_view' => 1]];
    }

    public function resolve_division_scope(int $userId): array
    {
        $this->trace->events[] = 'scope';
        return ['state' => 'GLOBAL', 'division_id' => null];
    }
}

final class PmrInput
{
    public function __construct(private array $payload = [])
    {
    }

    public function method(bool $upper = false): string
    {
        return $upper ? 'POST' : 'post';
    }

    public function __get(string $name): string
    {
        return $name === 'raw_input_stream' ? json_encode($this->payload) : '';
    }

    public function post($key = null, bool $clean = false): array
    {
        return $this->payload;
    }

    public function ip_address(): string
    {
        return '127.0.0.89';
    }

    public function user_agent(): string
    {
        return 'POS recovery smoke';
    }
}

final class PmrOutput
{
    public int $status = 200;
    public string $body = '';

    public function set_status_header(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function set_content_type(string $type, string $charset = ''): self
    {
        return $this;
    }

    public function set_output(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}

final class PmrResult
{
    public function __construct(private array $rows)
    {
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

final class PmrDb
{
    public int $tokenInserts = 0;
    public int $lastSeenUpdates = 0;
    public int $terminalReads = 0;
    public string $terminalSelect = '';
    public array $lastTokenInsert = [];
    private string $from = '';
    private array $where = [];

    public function __construct(private PmrTrace $trace, private array $terminals = [], private array $tokens = [])
    {
    }

    public function table_exists(string $table): bool
    {
        return in_array($table, ['pos_mobile_auth_token', 'pos_terminal'], true);
    }

    public function select(string $fields): self
    {
        if ($this->from === '' && strpos($fields, 'outlet_id') !== false) {
            $this->terminalSelect = $fields;
        }
        return $this;
    }

    public function from(string $table): self
    {
        $this->from = $table;
        if ($table === 'pos_terminal') {
            $this->terminalReads++;
            $this->trace->events[] = 'terminal';
        }
        return $this;
    }

    public function join(string $table, string $condition, string $type = ''): self
    {
        return $this;
    }

    public function where(string $field, $value = null, $escape = null): self
    {
        $this->where[$field] = $value;
        return $this;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function get(): PmrResult
    {
        if ($this->from === 'pos_terminal') {
            $rows = array_values(array_filter($this->terminals, function (array $row): bool {
                if (isset($this->where['device_key']) && (string)($row['device_key'] ?? '') !== (string)$this->where['device_key']) return false;
                if (isset($this->where['is_active']) && (int)($row['is_active'] ?? 0) !== (int)$this->where['is_active']) return false;
                return true;
            }));
            $rows = array_slice($rows, 0, 2);
        } else {
            $rows = $this->tokens;
        }
        $this->from = '';
        $this->where = [];
        return new PmrResult($rows);
    }

    public function insert(string $table, array $data): bool
    {
        if ($table === 'pos_mobile_auth_token') {
            $this->tokenInserts++;
            $this->lastTokenInsert = $data;
            $this->trace->events[] = 'token_insert';
        }
        return true;
    }

    public function update(string $table, array $data): bool
    {
        if ($table === 'pos_mobile_auth_token' && isset($data['last_seen_at'])) {
            $this->lastSeenUpdates++;
            $this->trace->events[] = 'last_seen';
        }
        $this->where = [];
        return true;
    }
}

function pmr_controller(?array $user, array $terminals, array $tokens = [], array $payload = []): array
{
    $trace = new PmrTrace();
    $db = new PmrDb($trace, $terminals, $tokens);
    $reflection = new ReflectionClass(Pos_mobile::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->Auth_model = new PmrAuth($trace, $user);
    $controller->db = $db;
    $controller->input = new PmrInput($payload);
    $controller->output = new PmrOutput();
    return [$controller, $db, $trace];
}

function pmr_terminal(int $id = 11, int $outletId = 7, int $active = 1, string $key = 'device-a'): array
{
    return ['id' => $id, 'outlet_id' => $outletId, 'is_active' => $active, 'device_key' => $key];
}

$validUser = ['id' => 31, 'employee_id' => 41, 'username' => 'cashier', 'email' => 'cashier@example.test'];
$loginPayload = ['identifier' => 'cashier', 'password' => 'secret', 'terminal_device_key' => 'device-a'];

[$badCredential, $badCredentialDb, $badCredentialTrace] = pmr_controller(null, [pmr_terminal()], [], $loginPayload);
$badCredential->login();
$genericBody = json_encode(['ok' => false, 'message' => 'Kredensial atau perangkat tidak valid.']);
pmr_check($badCredential->output->status === 401 && $badCredential->output->body === $genericBody, 'invalid credential returns generic 401 body');
pmr_check($badCredentialDb->terminalReads === 0 && $badCredentialDb->tokenInserts === 0, 'invalid credential stops before terminal lookup and token insert');

$invalidTerminalCases = [
    'missing' => [],
    'inactive' => [pmr_terminal(11, 7, 0)],
    'duplicate active' => [pmr_terminal(11), pmr_terminal(12)],
    'duplicate active and inactive' => [pmr_terminal(11), pmr_terminal(12, 7, 0)],
    'terminal id zero' => [pmr_terminal(0, 7)],
    'outlet zero' => [pmr_terminal(11, 0)],
];
foreach ($invalidTerminalCases as $label => $terminals) {
    [$controller, $db, $trace] = pmr_controller($validUser, $terminals, [], $loginPayload);
    $controller->login();
    pmr_check($controller->output->status === 401 && $controller->output->body === $genericBody, $label . ' login uses identical generic body');
    pmr_check($db->tokenInserts === 0 && $trace->events[0] === 'credential', $label . ' login authenticates first and does not insert token');
}

[$validLogin, $validLoginDb, $validLoginTrace] = pmr_controller($validUser, [pmr_terminal()], [], $loginPayload);
$validLogin->login();
$validLoginBody = json_decode($validLogin->output->body, true);
pmr_check(
    $validLogin->output->status === 200 && !empty($validLoginBody['ok']) && strlen((string)($validLoginBody['token'] ?? '')) === 64
        && (int)($validLoginBody['user']['id'] ?? 0) === 31 && $validLoginDb->tokenInserts === 1,
    'valid login preserves token and user success contract'
);
pmr_check(array_slice($validLoginTrace->events, 0, 2) === ['credential', 'terminal'], 'valid login performs credential auth before terminal lookup');

$tokenRow = [
    'id' => 71, 'user_id' => 31, 'employee_id' => 41, 'terminal_device_key' => 'device-a',
    'username' => 'cashier', 'email' => 'cashier@example.test', 'is_active' => 1,
];
$loader = new ReflectionMethod(Pos_mobile::class, 'load_mobile_user_from_token');
$loader->setAccessible(true);
$mobileUserProperty = new ReflectionProperty(Pos_mobile::class, 'mobileUser');
$mobileUserProperty->setAccessible(true);

$tokenFailureCases = [
    'device mismatch' => ['device-b', [pmr_terminal(11, 7, 1, 'device-b')]],
    'terminal missing' => ['device-a', []],
    'terminal inactive' => ['device-a', [pmr_terminal(11, 7, 0)]],
    'terminal duplicate' => ['device-a', [pmr_terminal(11), pmr_terminal(12)]],
    'terminal duplicate active and inactive' => ['device-a', [pmr_terminal(11), pmr_terminal(12, 7, 0)]],
    'terminal outlet zero' => ['device-a', [pmr_terminal(11, 0)]],
];
foreach ($tokenFailureCases as $label => [$deviceKey, $terminals]) {
    [$controller, $db] = pmr_controller($validUser, $terminals, [$tokenRow]);
    $accepted = $loader->invoke($controller, 'token-a', $deviceKey);
    pmr_check($accepted === false && $controller->output->status === 401, $label . ' token recovery is rejected');
    pmr_check($db->lastSeenUpdates === 0 && $mobileUserProperty->getValue($controller) === null, $label . ' fails before last_seen and mobile user binding');
}

[$validRecovery, $validRecoveryDb, $validRecoveryTrace] = pmr_controller($validUser, [pmr_terminal()], [$tokenRow]);
$accepted = $loader->invoke($validRecovery, 'token-a', 'device-a');
$mobileUser = $mobileUserProperty->getValue($validRecovery);
pmr_check($accepted === true && $validRecoveryDb->lastSeenUpdates === 1, 'valid token recovery updates last_seen once');
pmr_check((int)($mobileUser['terminal_id'] ?? 0) === 11 && (int)($mobileUser['outlet_id'] ?? 0) === 7, 'valid token recovery binds terminal and outlet to mobile user');

$source = file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Pos_mobile.php');
function pmr_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}
function pmr_ordered(string $source, array $needles): bool
{
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $at) return false;
        $at = $next;
    }
    return true;
}

$login = pmr_method($source, 'login');
$uniqueTerminal = pmr_method($source, 'unique_active_mobile_terminal');
$tokenLoader = pmr_method($source, 'load_mobile_user_from_token');
pmr_check(
    pmr_ordered($login, ['attempt_login(', 'unique_active_mobile_terminal(', 'load_permissions(', "insert('pos_mobile_auth_token'"])
        && substr_count($login, "json_error('Kredensial atau perangkat tidak valid.', 401)") === 2
        && strpos($login, 'INVALID_CREDENTIALS') === false && strpos($login, 'TERMINAL_NOT_REGISTERED') === false,
    'login source has one indistinguishable failure contract and auth-first order'
);
pmr_check(
    pmr_ordered($uniqueTerminal, ["select('id, outlet_id, is_active')", "where('device_key'", 'limit(2)', 'count($rows) !== 1', "(int)(\$rows[0]['is_active'] ?? 0) !== 1", "(int)(\$rows[0]['id'] ?? 0) <= 0", "(int)(\$rows[0]['outlet_id'] ?? 0) <= 0"])
        && strpos($uniqueTerminal, "where('is_active'") === false,
    'terminal lookup requires a globally unique active row with positive terminal and outlet IDs'
);
pmr_check(
    pmr_ordered($tokenLoader, ['hash_equals($tokenDeviceKey, $deviceKey)', 'unique_active_mobile_terminal(', "\$row['terminal_id']", "\$row['outlet_id']", '$this->mobileUser = $row', "update('pos_mobile_auth_token'", "'last_seen_at'"]),
    'token recovery binds terminal/outlet before last_seen update'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile recovery smoke check(s) failed.' . PHP_EOL);
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile device-binding recovery smoke checks passed.' . PHP_EOL;
