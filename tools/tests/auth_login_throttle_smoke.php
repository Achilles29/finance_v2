<?php

// DB/bootstrap/network/secret-free behavioral smoke test for Batch 53.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

if (!class_exists('CI_Model')) {
    class CI_Model
    {
        public $db;
    }
}

if (!class_exists('CI_Controller')) {
    class CI_Controller
    {
        public $Auth_model;
        public $form_validation;
        public $input;
        public $load;
        public $session;

        public function __construct()
        {
            global $authLoginThrottleEnvironment;
            $this->input = $authLoginThrottleEnvironment->input;
            $this->session = $authLoginThrottleEnvironment->session;
            $this->load = new AuthLoginThrottleLoader($this, $authLoginThrottleEnvironment->model);
        }
    }
}

final class AuthLoginThrottleRedirect extends RuntimeException
{
}

final class AuthLoginThrottleHttpError extends RuntimeException
{
}

if (!function_exists('redirect')) {
    function redirect($uri = '', $method = 'auto', $code = null): void
    {
        throw new AuthLoginThrottleRedirect((string)$uri);
    }
}

if (!function_exists('show_error')) {
    function show_error($message, $statusCode = 500, $heading = ''): void
    {
        throw new AuthLoginThrottleHttpError((string)$message, (int)$statusCode);
    }
}

if (!function_exists('log_message')) {
    function log_message($level, $message): void
    {
        global $authLoginThrottleLogs;
        $authLoginThrottleLogs[] = [(string)$level, (string)$message];
    }
}

require dirname(__DIR__, 2) . '/application/models/Auth_model.php';
require dirname(__DIR__, 2) . '/application/controllers/Auth.php';

final class AuthLoginThrottleResult
{
    public function __construct(private array $row)
    {
    }

    public function row_array(): array
    {
        return $this->row;
    }
}

final class AuthLoginThrottleCountDb
{
    public bool $db_debug = true;
    public array $failures;
    public ?string $lastFailureCutoff = null;
    private string $table = '';
    private array $where = [];

    public function __construct(public ?string $latestSuccess, array $failures)
    {
        $this->failures = $failures;
    }

    public function select($fields, $escape = null): self
    {
        return $this;
    }

    public function from($table): self
    {
        $this->table = (string)$table;
        return $this;
    }

    public function where($field, $value = null, $escape = null): self
    {
        $this->where[(string)$field] = $value;
        return $this;
    }

    public function get(): AuthLoginThrottleResult
    {
        $table = $this->table;
        $where = $this->where;
        $this->table = '';
        $this->where = [];

        if ($table === 'auth_session_log') {
            return new AuthLoginThrottleResult(['latest_success_at' => $this->latestSuccess]);
        }

        $cutoff = (string)($where['failed_at >'] ?? '');
        $this->lastFailureCutoff = $cutoff;
        $count = 0;
        foreach ($this->failures as $failure) {
            if (isset($where['user_id']) && (int)$failure['user_id'] !== (int)$where['user_id']) {
                continue;
            }
            if (isset($where['ip_address']) && $failure['ip_address'] !== $where['ip_address']) {
                continue;
            }
            if ($failure['failed_at'] > $cutoff) {
                $count++;
            }
        }

        return new AuthLoginThrottleResult(['failure_count' => $count]);
    }
}

final class AuthLoginThrottleInsertDb
{
    public bool $db_debug = true;
    public array $inserts = [];

    public function insert($table, array $data): bool
    {
        $this->inserts[] = ['table' => (string)$table, 'data' => $data];
        return true;
    }

    public function insert_id(): int
    {
        return 123;
    }
}

final class AuthLoginThrottleFailedSessionLogDb
{
    public bool $db_debug = true;

    public function insert($table, array $data): bool
    {
        return false;
    }

    public function insert_id(): int
    {
        return 0;
    }
}

final class AuthLoginThrottleLockManager
{
    public array $held = [];

    public function acquire(string $name, int $owner): void
    {
        if (isset($this->held[$name]) && $this->held[$name] !== $owner) {
            throw new RuntimeException('simulated concurrent lock contention');
        }
        $this->held[$name] = $owner;
    }

    public function release(string $name, int $owner): void
    {
        if (($this->held[$name] ?? null) !== $owner) {
            throw new RuntimeException('simulated lock release failure');
        }
        unset($this->held[$name]);
    }
}

class AuthLoginThrottleProbeModel extends Auth_model
{
    public array $events = [];
    public int $ipFailures = 0;
    public int $accountFailures = 0;
    public ?array $candidate = null;
    public bool $passwordMatches = false;
    public bool $throwDatabaseError = false;
    public bool $logLoginFails = false;
    public ?AuthLoginThrottleLockManager $lockManager = null;

    protected function acquire_named_login_lock(string $lock_name): void
    {
        $this->events[] = 'lock+:' . $lock_name;
        if ($this->lockManager !== null) {
            $this->lockManager->acquire($lock_name, spl_object_id($this));
        }
    }

    protected function release_named_login_lock(string $lock_name): void
    {
        $this->events[] = 'lock-:' . $lock_name;
        if ($this->lockManager !== null) {
            $this->lockManager->release($lock_name, spl_object_id($this));
        }
    }

    protected function count_recent_ip_login_failures(string $ip_address): int
    {
        $this->events[] = 'ip-count';
        if ($this->throwDatabaseError) {
            throw new RuntimeException('secret-db-host/schema detail');
        }
        return $this->ipFailures;
    }

    protected function find_login_candidate(string $identifier): ?array
    {
        $this->events[] = 'candidate-lookup';
        return $this->candidate;
    }

    protected function count_recent_account_login_failures(int $user_id): int
    {
        $this->events[] = 'account-count:' . $user_id;
        return $this->accountFailures;
    }

    protected function password_matches_candidate(string $password, ?array $candidate): bool
    {
        $this->events[] = $candidate === null ? 'bcrypt-dummy' : 'bcrypt-account';
        return $candidate !== null && $this->passwordMatches;
    }

    protected function enforce_failed_login_response_floor(int $started_at, int $floor_microseconds): void
    {
        $this->events[] = 'floor:' . $floor_microseconds;
    }

    public function record_login_failure(?int $user_id, string $ip_address): void
    {
        $this->events[] = 'failure:' . ($user_id === null ? 'null' : $user_id) . ':' . $ip_address;
    }

    protected function persist_successful_login_candidate(array $row, string $password): array
    {
        $this->events[] = 'success-persist';
        unset($row['password_hash']);
        return $row;
    }

    public function load_permissions(int $userId): array
    {
        $this->events[] = 'permissions';
        return ['__superadmin__' => true];
    }

    public function resolve_division_scope(int $userId): array
    {
        $this->events[] = 'division-scope';
        return ['state' => 'NONE', 'division_id' => null];
    }

    public function log_login(int $userId, string $ip, string $userAgent = ''): int
    {
        global $authLoginThrottleTrace;
        $this->events[] = 'session-log';
        $authLoginThrottleTrace[] = 'session-log';
        $this->release_web_login_locks();
        if ($this->logLoginFails) {
            throw new RuntimeException('secret-session-log-error');
        }
        return 99;
    }

    public function exposeAccountFailureCount(int $userId): int
    {
        return parent::count_recent_account_login_failures($userId);
    }

    public function exposeIpFailureCount(string $ipAddress): int
    {
        return parent::count_recent_ip_login_failures($ipAddress);
    }
}

final class AuthLoginThrottleInput
{
    public function __construct(private string $method, private array $postData, private string $ip)
    {
    }

    public function method(bool $upper = false): string
    {
        return $upper ? strtoupper($this->method) : strtolower($this->method);
    }

    public function post(string $key, bool $xssClean = false)
    {
        return $this->postData[$key] ?? null;
    }

    public function ip_address(): string
    {
        return $this->ip;
    }

    public function user_agent(): ?string
    {
        return null; // A valid client need not send a User-Agent header.
    }
}

final class AuthLoginThrottleSession
{
    public array $flash = [];
    public array $data = [];
    public array $events = [];

    public function userdata(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set_flashdata(string $key, $value): void
    {
        $this->flash[$key] = $value;
    }

    public function flashdata(string $key)
    {
        return $this->flash[$key] ?? '';
    }

    public function set_userdata($key, $value = null): void
    {
        global $authLoginThrottleTrace;
        $this->events[] = 'session-write';
        $authLoginThrottleTrace[] = 'session-write';
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
            return;
        }
        $this->data[$key] = $value;
    }

    public function sess_regenerate(bool $destroy = false): void
    {
        global $authLoginThrottleTrace;
        $this->events[] = 'session-regenerate:' . ($destroy ? 'destroy' : 'keep');
        $authLoginThrottleTrace[] = 'session-regenerate:' . ($destroy ? 'destroy' : 'keep');
    }

    public function sess_destroy(): void
    {
    }
}

final class AuthLoginThrottleValidation
{
    public function set_rules(string $field, string $label, string $rules): void
    {
    }

    public function run(): bool
    {
        return true;
    }
}

final class AuthLoginThrottleLoader
{
    public function __construct(private object $owner, private object $model)
    {
    }

    public function model(string $name): void
    {
        $this->owner->Auth_model = $this->model;
    }

    public function helper($helpers): void
    {
    }

    public function library($libraries): void
    {
        $this->owner->form_validation = new AuthLoginThrottleValidation();
    }
}

function auth_login_throttle_environment(
    AuthLoginThrottleProbeModel $model,
    string $method = 'POST'
): object {
    global $authLoginThrottleEnvironment;
    $authLoginThrottleEnvironment = (object)[
        'model' => $model,
        'input' => new AuthLoginThrottleInput($method, [
            'identifier' => 'known@example.test',
            'password' => 'never-log-this-password',
        ], '2001:db8::10'),
        'session' => new AuthLoginThrottleSession(),
    ];
    return $authLoginThrottleEnvironment;
}

function auth_login_throttle_run_controller(AuthLoginThrottleProbeModel $model, string $method = 'POST'): object
{
    $environment = auth_login_throttle_environment($model, $method);
    try {
        (new Auth())->do_login();
    } catch (AuthLoginThrottleRedirect $e) {
        $environment->redirect = $e->getMessage();
    } catch (AuthLoginThrottleHttpError $e) {
        $environment->httpCode = $e->getCode();
    }
    return $environment;
}

function auth_login_throttle_remove_temp_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path) || is_link($path)) {
            unlink($path);
        }
        return;
    }

    $entries = scandir($path);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            auth_login_throttle_remove_temp_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    rmdir($path);
}

function auth_login_throttle_run_wrapper_simulation(
    string $wrapper,
    string $fakeClient,
    string $captureDirectory,
    int $migrationExit,
    int $cleanupExit,
    string $secret
): array {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $inheritedEnvironment = getenv();
    if (!is_array($inheritedEnvironment)) {
        $inheritedEnvironment = [];
    }
    $environment = array_merge($inheritedEnvironment, [
        'AUTH_SESSION_LOG_DB_CLIENT' => $fakeClient,
        'FAKE_MYSQL_CAPTURE_DIR' => $captureDirectory,
        'FAKE_MYSQL_MIGRATION_EXIT' => (string)$migrationExit,
        'FAKE_MYSQL_CLEANUP_EXIT' => (string)$cleanupExit,
        'MYSQL_PWD' => $secret,
    ]);

    $process = proc_open(['bash', $wrapper], $descriptors, $pipes, dirname(__DIR__, 2), $environment);
    if (!is_resource($process)) {
        return ['exit' => -1, 'stdout' => '', 'stderr' => 'proc_open failed'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return [
        'exit' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

$checks = 0;
$failures = [];
$expect = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(Auth_model::normalize_login_identifier('  Staff@Example.test ') === 'Staff@Example.test', 'identifier is trimmed');
$expect(Auth_model::normalize_login_identifier(str_repeat('a', 150)) === str_repeat('a', 150), '150-byte identifier is accepted');
$expect(Auth_model::normalize_login_identifier(str_repeat('a', 151)) === '', 'overlong identifier is rejected');
$expect(Auth_model::normalize_login_ip('2001:0db8:0:0:0:0:0:10') === '2001:db8::10', 'IPv6 is canonicalized');
$expect(Auth_model::normalize_login_ip('not-an-ip') === '0.0.0.0', 'invalid IP is mapped to a bounded bucket');

$expect(!Auth_model::is_login_throttled(4, 19, true), 'threshold allows account 4 and IP 19');
$expect(Auth_model::is_login_throttled(5, 0, true), 'account is blocked at 5 failures');
$expect(Auth_model::is_login_throttled(0, 20, false), 'IP is blocked at 20 failures');
$expect(!Auth_model::is_login_throttled(99, 0, false), 'unknown account does not use an account counter');
$expect(Auth_model::login_delay_microseconds(0, 0) === 0, 'first attempt has no added delay');
$expect(Auth_model::login_delay_microseconds(1, 0) === 50000, 'account pressure adds modest progressive delay');
$expect(Auth_model::login_delay_microseconds(0, 4) === 50000, 'IP pressure adds modest progressive delay');
$expect(Auth_model::login_delay_microseconds(999, 999) === 500000, 'progressive delay is capped at 500ms');
$expect(Auth_model::failed_login_response_floor_microseconds(0, 0) === 400000, 'all failed web logins have a common 400ms floor');
$expect(Auth_model::failed_login_response_floor_microseconds(5, 20) === 400000, 'blocked account and IP use the same common floor');

$ipLock = Auth_model::login_ip_lock_name('2001:db8::10');
$accountLock = Auth_model::login_account_lock_name(42);
$expect(strlen($ipLock) <= 64 && strpos($ipLock, '2001:db8::10') === false, 'IP lock key is bounded and contains only an IP hash');
$expect($accountLock === 'finance:auth:user:42', 'account lock key is deterministic from user-id');

$window = '2026-09-03 10:00:00.000000';
$expect(Auth_model::account_failure_cutoff($window, '2026-09-03 10:05:00') === '2026-09-03 10:05:00.000000', 'old zero-microsecond success safely resets logical account count');
$expect(Auth_model::account_failure_cutoff($window, '2026-09-03 09:59:59') === $window, 'rolling window supersedes an old success');

$recentSuccess = (new DateTimeImmutable('now'))->modify('-2 minutes')->format('Y-m-d H:i:s');
$recentSuccessBoundary = $recentSuccess . '.000000';
$recentBeforeSuccess = (new DateTimeImmutable($recentSuccess))->modify('-1 second')->format('Y-m-d H:i:s') . '.999999';
$recentAfterSuccess = (new DateTimeImmutable($recentSuccess))->modify('+1 second')->format('Y-m-d H:i:s') . '.000001';
$recentLaterFailure = (new DateTimeImmutable($recentSuccess))->modify('+2 seconds')->format('Y-m-d H:i:s') . '.000001';
$countDb = new AuthLoginThrottleCountDb($recentSuccess, [
    ['user_id' => 42, 'ip_address' => '127.0.0.1', 'failed_at' => $recentBeforeSuccess],
    ['user_id' => 42, 'ip_address' => '127.0.0.1', 'failed_at' => $recentSuccessBoundary],
    ['user_id' => 42, 'ip_address' => '127.0.0.1', 'failed_at' => $recentAfterSuccess],
    ['user_id' => 42, 'ip_address' => '127.0.0.1', 'failed_at' => $recentLaterFailure],
    ['user_id' => 7, 'ip_address' => '127.0.0.1', 'failed_at' => $recentLaterFailure],
]);
$countModel = new AuthLoginThrottleProbeModel();
$countModel->db = $countDb;
$expect($countModel->exposeAccountFailureCount(42) === 2, 'account query counts only failures after latest successful login');
$expect($countDb->lastFailureCutoff === $recentSuccessBoundary, 'account query normalizes the old successful-login cutoff');
$expect(count($countDb->failures) === 5, 'logical reset preserves append-only failure history');

$sameSecond = (new DateTimeImmutable('now'))->modify('-1 minute')->format('Y-m-d H:i:s');
$sameSecondCutoff = $sameSecond . '.900000';
$sameSecondDb = new AuthLoginThrottleCountDb($sameSecondCutoff, [
    ['user_id' => 42, 'ip_address' => '127.0.0.1', 'failed_at' => $sameSecond . '.100000'],
    ['user_id' => 42, 'ip_address' => '127.0.0.1', 'failed_at' => $sameSecond . '.900001'],
]);
$sameSecondModel = new AuthLoginThrottleProbeModel();
$sameSecondModel->db = $sameSecondDb;
$expect($sameSecondModel->exposeAccountFailureCount(42) === 1, 'same-second reset counts only the failure after the microsecond success boundary');
$expect($sameSecondDb->lastFailureCutoff === $sameSecondCutoff, 'same-second query preserves the six-digit success cutoff');

$rollingWindowDb = new AuthLoginThrottleCountDb(null, []);
$rollingWindowModel = new AuthLoginThrottleProbeModel();
$rollingWindowModel->db = $rollingWindowDb;
$rollingWindowModel->exposeIpFailureCount('127.0.0.1');
$expect((bool)preg_match('/\.\d{6}$/D', (string)$rollingWindowDb->lastFailureCutoff), 'IP rolling-window cutoff has microsecond precision');
$rollingWindowModel->exposeAccountFailureCount(42);
$expect((bool)preg_match('/\.\d{6}$/D', (string)$rollingWindowDb->lastFailureCutoff), 'account rolling-window cutoff has microsecond precision');

$insertDb = new AuthLoginThrottleInsertDb();
$insertModel = new Auth_model();
$insertModel->db = $insertDb;
$insertModel->record_login_failure(null, '2001:0db8::10');
$insertModel->record_login_failure(42, '127.0.0.1');
$expect(count($insertDb->inserts) === 2, 'each failed attempt appends one row');
$expect(array_keys($insertDb->inserts[0]['data']) === ['user_id', 'ip_address', 'failed_at'], 'failure row contains only approved fields');
$expect($insertDb->inserts[0]['data']['user_id'] === null && $insertDb->inserts[1]['data']['user_id'] === 42, 'unknown and known failures retain nullable account semantics');
$expect(
    strpos(serialize($insertDb->inserts), 'never-log-this-password') === false
        && strpos(serialize($insertDb->inserts), 'known@example.test') === false
        && strpos(serialize($insertDb->inserts), 'user_agent') === false,
    'failure persistence stores no password, raw identifier, or user-agent'
);
$sessionLogId = $insertModel->log_login(42, '127.0.0.1', 'test-agent');
$sessionLogInsert = $insertDb->inserts[2] ?? [];
$expect($sessionLogId === 123 && ($sessionLogInsert['table'] ?? '') === 'auth_session_log', 'log_login uses the isolated DB fake');
$expect(
    isset($sessionLogInsert['data']['login_at'])
        && (bool)preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D', $sessionLogInsert['data']['login_at']),
    'log_login payload has exactly six fractional digits'
);

$failedSessionDb = new AuthLoginThrottleFailedSessionLogDb();
$failedSessionModel = new Auth_model();
$failedSessionModel->db = $failedSessionDb;
$sessionLogFailedClosed = false;
try {
    $failedSessionModel->log_login(42, '127.0.0.1', 'test-agent');
} catch (RuntimeException $e) {
    $sessionLogFailedClosed = true;
}
$expect($sessionLogFailedClosed, 'log_login throws when auth_session_log insert fails');

$nonPostModel = new AuthLoginThrottleProbeModel();
$nonPost = auth_login_throttle_run_controller($nonPostModel, 'GET');
$expect(($nonPost->httpCode ?? 0) === 405 && $nonPostModel->events === [], 'non-POST is rejected before lookup, throttle, or bcrypt');

$ipBlockedModel = new AuthLoginThrottleProbeModel();
$ipBlockedModel->ipFailures = 20;
$ipBlocked = auth_login_throttle_run_controller($ipBlockedModel);
$expect(
    $ipBlockedModel->events === ['lock+:' . $ipLock, 'ip-count', 'floor:400000', 'lock-:' . $ipLock],
    'IP-blocked request holds serialization lock through common floor and never runs bcrypt'
);

$accountBlockedModel = new AuthLoginThrottleProbeModel();
$accountBlockedModel->candidate = ['id' => 42, 'password_hash' => 'unused'];
$accountBlockedModel->accountFailures = 5;
$accountBlocked = auth_login_throttle_run_controller($accountBlockedModel);
$expect(
    $accountBlockedModel->events === [
        'lock+:' . $ipLock,
        'ip-count',
        'candidate-lookup',
        'lock+:' . $accountLock,
        'account-count:42',
        'floor:400000',
        'lock-:' . $accountLock,
        'lock-:' . $ipLock,
    ],
    'account-blocked request uses IP-to-account order, reverse release, common floor, and no bcrypt'
);

$unknownModel = new AuthLoginThrottleProbeModel();
$unknown = auth_login_throttle_run_controller($unknownModel);
$expect(
    $unknownModel->events === [
        'lock+:' . $ipLock,
        'ip-count',
        'candidate-lookup',
        'bcrypt-dummy',
        'failure:null:2001:db8::10',
        'floor:400000',
        'lock-:' . $ipLock,
    ],
    'unknown identifier uses common floor and appends an IP-only failure while serialized'
);

$wrongModel = new AuthLoginThrottleProbeModel();
$wrongModel->candidate = ['id' => 42, 'password_hash' => 'unused'];
$wrong = auth_login_throttle_run_controller($wrongModel);
$expect(
    $wrongModel->events === [
        'lock+:' . $ipLock,
        'ip-count',
        'candidate-lookup',
        'lock+:' . $accountLock,
        'account-count:42',
        'bcrypt-account',
        'failure:42:2001:db8::10',
        'floor:400000',
        'lock-:' . $accountLock,
        'lock-:' . $ipLock,
    ],
    'wrong password is serialized through bcrypt, append, and the common floor'
);

$mobileModel = new AuthLoginThrottleProbeModel();
$mobileResult = $mobileModel->attempt_login('missing-mobile-user', 'wrong-mobile-password');
$expect($mobileResult === false, 'two-argument mobile-compatible login still returns false on mismatch');
$expect($mobileModel->events === ['candidate-lookup', 'bcrypt-dummy'], 'two-argument mobile path invokes no lock, throttle count, floor, or failure audit');

$lockManager = new AuthLoginThrottleLockManager();
$parallelA = new AuthLoginThrottleProbeModel();
$parallelA->lockManager = $lockManager;
$parallelA->candidate = ['id' => 42, 'password_hash' => 'unused'];
$parallelA->passwordMatches = true;
$parallelB = new AuthLoginThrottleProbeModel();
$parallelB->lockManager = $lockManager;
$parallelB->candidate = ['id' => 42, 'password_hash' => 'unused'];
$parallelB->passwordMatches = true;
$parallelAResult = $parallelA->attempt_login('known@example.test', 'correct-password', '2001:db8::10');
$parallelBSerialized = false;
try {
    $parallelB->attempt_login('known@example.test', 'correct-password', '2001:db8::10');
} catch (RuntimeException $e) {
    $parallelBSerialized = true;
}
$expect(is_array($parallelAResult) && count($lockManager->held) === 2, 'successful web attempt retains IP and account locks pending session audit');
$expect($parallelBSerialized && !in_array('bcrypt-account', $parallelB->events, true), 'overlapping request cannot pass held lock or reach bcrypt/count race');
$parallelA->log_login(42, '2001:db8::10', 'test-agent');
$expect($lockManager->held === [], 'successful session audit releases account then IP serialization locks');
$parallelB->passwordMatches = false;
$parallelBRetry = $parallelB->attempt_login('known@example.test', 'wrong-password', '2001:db8::10');
$expect($parallelBRetry === false && $lockManager->held === [], 'waiting request can proceed only after prior audit releases locks');

$genericMessages = [
    $ipBlocked->session->flash['login_error'] ?? null,
    $accountBlocked->session->flash['login_error'] ?? null,
    $unknown->session->flash['login_error'] ?? null,
    $wrong->session->flash['login_error'] ?? null,
];
$expect(count(array_unique($genericMessages, SORT_REGULAR)) === 1 && is_string($genericMessages[0]), 'unknown, wrong, and blocked responses use one generic message');
$expect(stripos($genericMessages[0], 'username') === false && stripos($genericMessages[0], 'email') === false, 'generic response does not reveal account existence');

$dbErrorModel = new AuthLoginThrottleProbeModel();
$dbErrorModel->throwDatabaseError = true;
$dbError = auth_login_throttle_run_controller($dbErrorModel);
$maintenanceMessage = (string)($dbError->session->flash['login_error'] ?? '');
$expect(($dbError->redirect ?? '') === 'login' && strpos($maintenanceMessage, 'sementara tidak tersedia') !== false, 'DB/schema error fails closed with maintenance response');
$expect(strpos($maintenanceMessage, 'secret-db-host') === false, 'DB exception detail is not exposed to the user');
$expect(isset($authLoginThrottleLogs[0]) && strpos($authLoginThrottleLogs[0][1], 'secret-db-host') === false, 'server log is generic and excludes DB exception detail');

$authLoginThrottleTrace = [];
$logFailureModel = new AuthLoginThrottleProbeModel();
$logFailureModel->candidate = ['id' => 42, 'password_hash' => 'unused'];
$logFailureModel->passwordMatches = true;
$logFailureModel->logLoginFails = true;
$logFailure = auth_login_throttle_run_controller($logFailureModel);
$expect(($logFailure->redirect ?? '') === 'login', 'controller redirects failed successful-login finalization back to login');
$expect(!isset($logFailure->session->data['auth_user']) && !isset($logFailure->session->data['session_log_id']), 'auth_session_log failure creates no authenticated session');
$expect($authLoginThrottleTrace === ['session-log'], 'failed session audit occurs before and prevents every authenticated session write');
$expect(strpos((string)($logFailure->session->flash['login_error'] ?? ''), 'secret-session-log-error') === false, 'session audit error detail is not exposed');

$authLoginThrottleTrace = [];
$logSuccessModel = new AuthLoginThrottleProbeModel();
$logSuccessModel->candidate = ['id' => 42, 'password_hash' => 'unused'];
$logSuccessModel->passwordMatches = true;
$logSuccess = auth_login_throttle_run_controller($logSuccessModel);
$expect(($logSuccess->redirect ?? '') === 'dashboard', 'successful login without User-Agent preserves redirect flow');
$expect(($logSuccess->session->data['session_log_id'] ?? 0) === 99 && isset($logSuccess->session->data['auth_user']), 'successful audit then creates authenticated session');
$expect($authLoginThrottleTrace === ['session-log', 'session-regenerate:destroy', 'session-write'], 'auth_session_log succeeds before session ID rotation and authenticated session write');

$sql = file_get_contents(dirname(__DIR__, 2) . '/sql/2026-09-03a_auth_login_throttle_foundation.sql');
$expect(
    is_string($sql)
        && strpos($sql, 'BIGINT UNSIGNED NULL') !== false
        && strpos($sql, 'VARCHAR(45)') !== false
        && strpos($sql, 'ON DELETE SET NULL') !== false,
    'DDL matches auth_user ID, IP width, and nullable FK contract'
);
$expect(
    is_string($sql)
        && substr_count($sql, '(user_id, failed_at)') === 1
        && substr_count($sql, '(ip_address, failed_at)') === 1
        && !preg_match('/\b(INSERT\s+INTO|UPDATE\s+[`a-z]|DELETE\s+FROM)\b/i', $sql),
    'DDL has both throttle indexes and no data mutation'
);

$microsecondSql = file_get_contents(dirname(__DIR__, 2) . '/sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql');
$expect(
    is_string($microsecondSql)
        && (bool)preg_match('/ALTER\s+TABLE\s+auth_session_log\s+MODIFY\s+COLUMN\s+login_at\s+DATETIME\(6\)\s+NOT\s+NULL\s+DEFAULT\s+CURRENT_TIMESTAMP\(6\)/i', $microsecondSql)
        && substr_count(strtoupper($microsecondSql), 'ALTER TABLE') === 1,
    'microsecond migration targets only login_at DATETIME(6) with its required default'
);
$expect(
    is_string($microsecondSql)
        && stripos($microsecondSql, 'information_schema.TABLES') !== false
        && stripos($microsecondSql, 'information_schema.COLUMNS') !== false
        && substr_count(strtoupper($microsecondSql), "SIGNAL SQLSTATE '45000'") >= 4,
    'microsecond migration preflights table, column, type, precision, and nullability'
);
$expect(
    is_string($microsecondSql)
        && (bool)preg_match('/IF\s+NOT\s*\(\s*v_datetime_precision\s*=\s*6[\s\S]*?current_timestamp\(6\)[\s\S]*?\)\s+THEN\s+ALTER\s+TABLE/i', $microsecondSql),
    'microsecond migration has an idempotent no-ALTER branch for the correct schema'
);
$expect(
    is_string($microsecondSql)
        && !preg_match('/\b(INSERT\s+INTO|UPDATE\s+[`a-z]|DELETE\s+FROM)\b/i', $microsecondSql)
        && stripos($microsecondSql, 'CREATE TABLE') === false
        && stripos($microsecondSql, ' TIMESTAMP ') === false,
    'microsecond migration is DDL-only with no replacement table, timestamp conversion, or data mutation'
);

$wrapperPath = dirname(__DIR__) . '/db/apply_auth_session_log_login_at_microsecond.sh';
$wrapper = file_get_contents($wrapperPath);
$expect(
    is_string($wrapper)
        && (bool)preg_match('/^set -euo pipefail$/m', $wrapper)
        && (bool)preg_match('/trap\s+cleanup_procedure\s+EXIT/', $wrapper),
    'migration wrapper uses strict shell mode and an EXIT cleanup trap'
);
$expect(
    is_string($wrapper)
        && strpos($wrapper, 'sp_auth_session_log_login_at_usec_20260903b') !== false
        && (bool)preg_match('/DROP PROCEDURE IF EXISTS sp_auth_session_log_login_at_usec_20260903b;/', $wrapper),
    'migration wrapper cleanup targets the owned temporary procedure'
);
$expect(
    is_string($wrapper)
        && strpos($wrapper, 'mysql') !== false
        && strpos($wrapper, 'mariadb') !== false
        && strpos($wrapper, 'DELIMITER') !== false
        && strpos($wrapper, 'AUTH_SESSION_LOG_DB_CLIENT') !== false
        && strpos($wrapper, '--defaults-extra-file=') !== false
        && (bool)preg_match('/"\$\{client_command\[@\]\}"\s*<\s*"\$\{migration_file\}"/', $wrapper),
    'migration wrapper feeds the complete DELIMITER migration to a configured mysql/mariadb client'
);
$expect(
    is_string($wrapper)
        && strpos($wrapper, '--password') === false
        && strpos($wrapper, 'MYSQL_PWD') === false
        && strpos($wrapper, 'set -x') === false
        && strpos($wrapper, 'printenv') === false,
    'migration wrapper neither places a password option on the command line nor prints secret environment values'
);

$wrapperTemp = sys_get_temp_dir() . '/auth-login-wrapper-' . bin2hex(random_bytes(8));
$fakeClient = $wrapperTemp . '/fake-mysql';
$migrationFailureCapture = $wrapperTemp . '/migration-failure';
$cleanupFailureCapture = $wrapperTemp . '/cleanup-failure';
$tempReady = mkdir($wrapperTemp, 0700)
    && mkdir($migrationFailureCapture, 0700)
    && mkdir($cleanupFailureCapture, 0700);
$fakeClientSource = <<<'SH'
#!/usr/bin/env bash
set -euo pipefail

capture_dir="${FAKE_MYSQL_CAPTURE_DIR:?}"
count_file="${capture_dir}/count"
invocation=0
if [[ -f "${count_file}" ]]; then
    read -r invocation < "${count_file}"
fi
invocation=$((invocation + 1))
printf '%s\n' "${invocation}" > "${count_file}"
printf '%s\n' "$@" > "${capture_dir}/args.${invocation}"
cat > "${capture_dir}/stdin.${invocation}"

if (( invocation == 1 )); then
    exit "${FAKE_MYSQL_MIGRATION_EXIT:-0}"
fi
exit "${FAKE_MYSQL_CLEANUP_EXIT:-0}"
SH;
$fakeReady = $tempReady
    && file_put_contents($fakeClient, $fakeClientSource) === strlen($fakeClientSource)
    && chmod($fakeClient, 0700);
$expect($fakeReady, 'wrapper behavior simulation creates an isolated executable fake mysql client');

if ($fakeReady && is_string($microsecondSql)) {
    $syntheticSecret = 'batch-53.2-secret-must-not-appear';
    $migrationFailure = auth_login_throttle_run_wrapper_simulation(
        $wrapperPath,
        $fakeClient,
        $migrationFailureCapture,
        37,
        41,
        $syntheticSecret
    );
    $migrationInvocation = file_get_contents($migrationFailureCapture . '/stdin.1');
    $migrationCleanup = file_get_contents($migrationFailureCapture . '/stdin.2');
    $migrationClientArgs = file_get_contents($migrationFailureCapture . '/args.1');
    $cleanupClientArgs = file_get_contents($migrationFailureCapture . '/args.2');
    $migrationInvocationCount = trim((string)file_get_contents($migrationFailureCapture . '/count'));
    $migrationOutput = $migrationFailure['stdout'] . $migrationFailure['stderr'];
    $expect(
        $migrationFailure['exit'] === 37
            && $migrationInvocationCount === '2',
        'failed migration preserves its nonzero status and still invokes cleanup once'
    );
    $expect(
        $migrationInvocation === $microsecondSql,
        'wrapper sends the unmodified migration as one whole client input'
    );
    $expect(
        trim((string)$migrationCleanup) === 'DROP PROCEDURE IF EXISTS sp_auth_session_log_login_at_usec_20260903b;',
        'EXIT trap sends the owned procedure DROP after migration failure'
    );
    $expect(
        strpos($migrationOutput, $syntheticSecret) === false,
        'wrapper output does not disclose an inherited synthetic DB secret'
    );
    $expect(
        is_string($migrationClientArgs)
            && is_string($cleanupClientArgs)
            && strpos($migrationClientArgs, '--batch') !== false
            && strpos($cleanupClientArgs, '--batch') !== false
            && strpos($migrationClientArgs . $cleanupClientArgs, '--password') === false
            && strpos($migrationClientArgs . $cleanupClientArgs, $syntheticSecret) === false,
        'migration and cleanup client arguments are batch-capable and contain no password or inherited secret'
    );

    $cleanupFailure = auth_login_throttle_run_wrapper_simulation(
        $wrapperPath,
        $fakeClient,
        $cleanupFailureCapture,
        0,
        41,
        $syntheticSecret
    );
    $cleanupInvocationCount = trim((string)file_get_contents($cleanupFailureCapture . '/count'));
    $expect(
        $cleanupFailure['exit'] === 41
            && $cleanupInvocationCount === '2'
            && trim((string)file_get_contents($cleanupFailureCapture . '/stdin.2')) === 'DROP PROCEDURE IF EXISTS sp_auth_session_log_login_at_usec_20260903b;',
        'cleanup failure after a successful migration is reported as nonzero'
    );
    $expect(
        strpos($cleanupFailure['stdout'] . $cleanupFailure['stderr'], $syntheticSecret) === false,
        'cleanup-failure output also excludes the synthetic DB secret'
    );
}
auth_login_throttle_remove_temp_tree($wrapperTemp);

if ($failures !== []) {
    fwrite(STDERR, "FAIL: auth login throttle smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo 'PASS: ' . $checks . " auth login throttle checks\n";
