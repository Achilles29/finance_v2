<?php

// DB-free smoke test for P0-04A. It loads the real auth model and base
// controller against small in-memory fakes only.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

if (!class_exists('CI_Model')) {
    class CI_Model
    {
        public $db;
        public $session;
    }
}

if (!class_exists('CI_Controller')) {
    class CI_Controller
    {
        public $Auth_model;
        public $db;
        public $input;
        public $load;
        public $output;
        public $router;
        public $session;

        public function __construct()
        {
            global $authDivisionScopeSmokeEnvironment;
            $this->db = $authDivisionScopeSmokeEnvironment->db;
            $this->input = $authDivisionScopeSmokeEnvironment->input;
            $this->output = $authDivisionScopeSmokeEnvironment->output;
            $this->router = $authDivisionScopeSmokeEnvironment->router;
            $this->session = $authDivisionScopeSmokeEnvironment->session;
            $this->load = new AuthDivisionScopeSmokeLoader($this, $authDivisionScopeSmokeEnvironment->authModel);
        }
    }
}

final class AuthDivisionScopeSmokeResult
{
    public function __construct(private array $rows)
    {
    }

    public function result_array(): array
    {
        return $this->rows;
    }

    public function num_rows(): int
    {
        return count($this->rows);
    }
}

final class AuthDivisionScopeSmokeDb
{
    public bool $db_debug = true;
    private ?string $selectedFields = null;
    private ?string $table = null;
    private array $whereConditions = [];

    public function __construct(
        private array $scopeRows,
        private bool $userStale = false,
        private bool $roleStale = false,
        private bool $roleIsActive = true
    )
    {
    }

    public function select($fields, $escape = null): self
    {
        $this->selectedFields = (string)$fields;
        return $this;
    }

    public function from($table): self
    {
        $this->table = (string)$table;
        return $this;
    }

    public function join($table, $condition, $type = ''): self
    {
        return $this;
    }

    public function where($field, $value = null, $escape = null): self
    {
        $this->whereConditions[(string)$field] = $value;
        return $this;
    }

    public function or_where($field, $value = null, $escape = null): self
    {
        return $this;
    }

    public function group_start($not = '', $type = 'AND '): self
    {
        return $this;
    }

    public function group_end(): self
    {
        return $this;
    }

    public function limit($limit): self
    {
        return $this;
    }

    public function get(): AuthDivisionScopeSmokeResult
    {
        if ($this->table === 'auth_session_log s') {
            // Scope fixtures represent an authenticated, active web session.
            return new AuthDivisionScopeSmokeResult(
                ($this->whereConditions['s.id'] ?? null) === 99
                    && (int)($this->whereConditions['s.user_id'] ?? 0) > 0
                    && array_key_exists('s.logout_at', $this->whereConditions)
                    && $this->whereConditions['s.logout_at'] === null
                    && ($this->whereConditions['u.is_active'] ?? null) === 1
                    ? [['id' => 99]] : []
            );
        }

        if ($this->table === 'auth_user' && $this->selectedFields === '1') {
            return new AuthDivisionScopeSmokeResult(
                $this->userStale ? [['stale' => 1]] : []
            );
        }

        if ($this->table === 'auth_user_role ur' && $this->selectedFields === '1') {
            $hasActiveRoleFilter = array_key_exists('r.is_active', $this->whereConditions)
                && (int)$this->whereConditions['r.is_active'] === 1;
            $roleCanBeSeenByStaleSignal = !$hasActiveRoleFilter || $this->roleIsActive;

            return new AuthDivisionScopeSmokeResult(
                $this->roleStale && $roleCanBeSeenByStaleSignal ? [['stale' => 1]] : []
            );
        }

        if ($this->table === 'auth_user_role ur') {
            $rows = array_values(array_filter($this->scopeRows, static function (array $row): bool {
                return !array_key_exists('is_active', $row) || (int)$row['is_active'] === 1;
            }));
            return new AuthDivisionScopeSmokeResult($rows);
        }

        if ($this->userStale || $this->roleStale) {
            return new AuthDivisionScopeSmokeResult([['stale' => 1]]);
        }

        return new AuthDivisionScopeSmokeResult([]);
    }
}

final class AuthDivisionScopeSmokeSession
{
    public array $flashdataData = [];
    public bool $destroyed = false;

    public function __construct(private array $data)
    {
    }

    public function userdata(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set_userdata($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $name => $item) {
                $this->data[$name] = $item;
            }
            return;
        }

        $this->data[$key] = $value;
    }

    public function set_flashdata(string $key, $value): void
    {
        $this->flashdataData[$key] = $value;
    }

    public function flashdata(string $key)
    {
        return $this->flashdataData[$key] ?? null;
    }

    public function sess_destroy(): void
    {
        $this->destroyed = true;
        $this->data = [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}

final class AuthDivisionScopeSmokeAuthModel
{
    public int $refreshCalls = 0;
    public int $attemptLoginCalls = 0;
    public int $loginLogCalls = 0;

    private $loginUser = null;
    private array $loginPermissions = [];
    private array $loginDivisionScope = ['state' => 'NONE', 'division_id' => null];

    public function __construct(private AuthDivisionScopeSmokeSession $session, private array $refreshData)
    {
    }

    public function refresh_permissions(int $userId): void
    {
        $this->refreshCalls++;
        $this->session->set_userdata($this->refreshData);
    }

    public function configureLogin(array $user, array $permissions, array $divisionScope): void
    {
        $this->loginUser = $user;
        $this->loginPermissions = $permissions;
        $this->loginDivisionScope = $divisionScope;
    }

    public function attempt_login(string $identifier, string $password)
    {
        $this->attemptLoginCalls++;
        return $this->loginUser;
    }

    public function load_permissions(int $userId): array
    {
        return $this->loginPermissions;
    }

    public function resolve_division_scope(int $userId): array
    {
        return $this->loginDivisionScope;
    }

    public function log_login(int $userId, string $ip, string $userAgent = ''): int
    {
        $this->loginLogCalls++;
        return 99;
    }
}

final class AuthDivisionScopeSmokeFormValidation
{
    public function set_rules(string $field, string $label, string $rules): void
    {
    }

    public function run(): bool
    {
        return true;
    }
}

final class AuthDivisionScopeSmokeLoader
{
    public int $modelLoads = 0;

    public function __construct(private object $owner, private object $authModel)
    {
    }

    public function model(string $name): void
    {
        $this->modelLoads++;
        $this->owner->Auth_model = $this->authModel;
    }

    public function helper($helpers): void
    {
    }

    public function library($libraries): void
    {
        $libraries = (array)$libraries;
        if (in_array('form_validation', $libraries, true)) {
            $this->owner->form_validation = new AuthDivisionScopeSmokeFormValidation();
        }
    }
}

final class AuthDivisionScopeSmokeInput
{
    public function __construct(private array $postData = [])
    {
    }

    public function is_ajax_request(): bool
    {
        return false;
    }

    public function is_cli_request(): bool
    {
        return false;
    }

    public function post(string $key, $xssClean = false)
    {
        return $this->postData[$key] ?? null;
    }

    public function ip_address(): string
    {
        return '127.0.0.1';
    }

    public function user_agent(): string
    {
        return 'auth-division-scope-smoke';
    }
}

final class AuthDivisionScopeSmokeRouter
{
    public function fetch_class(): string
    {
        return 'smoke';
    }

    public function fetch_method(): string
    {
        return 'index';
    }
}

final class AuthDivisionScopeSmokeOutput
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

    public function _display(): void
    {
    }
}

final class AuthDivisionScopeSmokeForbidden extends RuntimeException
{
}

final class AuthDivisionScopeSmokeRedirect extends RuntimeException
{
}

if (!function_exists('show_error')) {
    function show_error($message, $status_code = 500, $heading = ''): void
    {
        throw new AuthDivisionScopeSmokeForbidden((string)$message, (int)$status_code);
    }
}

if (!function_exists('log_message')) {
    function log_message($level, $message): void
    {
    }
}

if (!function_exists('redirect')) {
    function redirect($uri = '', $method = 'auto', $code = null): void
    {
        throw new AuthDivisionScopeSmokeRedirect((string)$uri);
    }
}

require dirname(__DIR__, 2) . '/application/models/Auth_model.php';
require dirname(__DIR__, 2) . '/application/core/MY_Controller.php';
require dirname(__DIR__, 2) . '/application/controllers/Auth.php';

function auth_division_scope_smoke_expect(bool $condition, string $message): void
{
    global $authDivisionScopeSmokeChecks, $authDivisionScopeSmokeFailures;
    $authDivisionScopeSmokeChecks++;
    if (!$condition) {
        $authDivisionScopeSmokeFailures[] = $message;
    }
}

function auth_division_scope_smoke_resolve(array $rows): array
{
    $model = new Auth_model();
    $model->db = new AuthDivisionScopeSmokeDb($rows);
    return $model->resolve_division_scope(42);
}

function auth_division_scope_smoke_environment(
    array $sessionData,
    array $refreshData = [],
    bool $userStale = false,
    bool $roleStale = false,
    bool $roleIsActive = true,
    ?AuthDivisionScopeSmokeInput $input = null
): object
{
    global $authDivisionScopeSmokeEnvironment;

    if (!empty($sessionData['auth_user']) && !array_key_exists('session_log_id', $sessionData)) {
        $sessionData['session_log_id'] = 99;
    }
    $session = new AuthDivisionScopeSmokeSession($sessionData);
    $authDivisionScopeSmokeEnvironment = (object)[
        'authModel' => new AuthDivisionScopeSmokeAuthModel($session, $refreshData),
        'db' => new AuthDivisionScopeSmokeDb([], $userStale, $roleStale, $roleIsActive),
        'input' => $input ?? new AuthDivisionScopeSmokeInput(),
        'output' => new AuthDivisionScopeSmokeOutput(),
        'router' => new AuthDivisionScopeSmokeRouter(),
        'session' => $session,
    ];

    return $authDivisionScopeSmokeEnvironment;
}

final class AuthDivisionScopeSmokeController extends MY_Controller
{
    public static int $controllerWork = 0;

    public function __construct()
    {
        parent::__construct();
        self::$controllerWork++;
    }

    public function smoke_is_superadmin(): bool
    {
        return $this->is_superadmin();
    }
}

$authDivisionScopeSmokeChecks = 0;
$authDivisionScopeSmokeFailures = [];

$resolverCases = [
    'single' => [
        [['division_scope_id' => 7]],
        ['state' => 'SINGLE', 'division_id' => 7],
    ],
    'zero roles' => [
        [],
        ['state' => 'NONE', 'division_id' => null],
    ],
    'duplicate single division' => [
        [['division_scope_id' => 7], ['division_scope_id' => '7']],
        ['state' => 'SINGLE', 'division_id' => 7],
    ],
    'multiple divisions' => [
        [['division_scope_id' => 7], ['division_scope_id' => 8]],
        ['state' => 'AMBIGUOUS', 'division_id' => null],
    ],
    'zero division id' => [
        [['division_scope_id' => 0]],
        ['state' => 'NONE', 'division_id' => null],
    ],
    'invalid division id' => [
        [['division_scope_id' => -1]],
        ['state' => 'NONE', 'division_id' => null],
    ],
    'null division id' => [
        [['division_scope_id' => null]],
        ['state' => 'GLOBAL', 'division_id' => null],
    ],
    'global role plus scoped division' => [
        [['division_scope_id' => null], ['division_scope_id' => 7]],
        ['state' => 'SINGLE', 'division_id' => 7],
    ],
    'multiple global roles' => [
        [['division_scope_id' => null], ['division_scope_id' => null]],
        ['state' => 'GLOBAL', 'division_id' => null],
    ],
    'inactive division role is ignored' => [
        [['division_scope_id' => 7, 'is_active' => 1], ['division_scope_id' => 8, 'is_active' => 0]],
        ['state' => 'SINGLE', 'division_id' => 7],
    ],
    'valid plus invalid division id' => [
        [['division_scope_id' => 7], ['division_scope_id' => 0]],
        ['state' => 'AMBIGUOUS', 'division_id' => null],
    ],
];

foreach ($resolverCases as $label => [$rows, $expected]) {
    auth_division_scope_smoke_expect(
        auth_division_scope_smoke_resolve($rows) === $expected,
        'resolver handles ' . $label
    );
}

$authSource = file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Auth.php');
$coreSource = file_get_contents(dirname(__DIR__, 2) . '/application/core/MY_Controller.php');
$authModelSource = file_get_contents(dirname(__DIR__, 2) . '/application/models/Auth_model.php');
auth_division_scope_smoke_expect(is_string($authSource), 'Auth source is readable');
auth_division_scope_smoke_expect(is_string($coreSource), 'MY_Controller source is readable');
auth_division_scope_smoke_expect(is_string($authModelSource), 'Auth_model source is readable');
auth_division_scope_smoke_expect(
    strpos($authSource, 'resolve_division_scope') !== false,
    'login invokes explicit scope resolver'
);
auth_division_scope_smoke_expect(
    strpos($authSource, "Konfigurasi akses akun belum lengkap. Hubungi administrator.") !== false,
    'login uses a generic scope configuration message'
);
auth_division_scope_smoke_expect(
    strpos($authSource, 'resolve_division_scope') < strpos($authSource, '$this->session->set_userdata(['),
    'login resolves scope before authenticated session write'
);
auth_division_scope_smoke_expect(
    strpos($coreSource, 'user_division_scope_state') !== false
        && strpos($coreSource, '_has_valid_division_scope') !== false,
    'request entry checks explicit scope state'
);
auth_division_scope_smoke_expect(
    strpos($coreSource, "->set_status_header(403)") !== false
        && strpos($coreSource, 'show_error(self::INVALID_SCOPE_MESSAGE, 403') !== false,
    'scope denial preserves JSON and HTML 403 branches'
);
$staleSignalStart = strpos($coreSource, 'private function _maybe_refresh_stale_perms');
$staleRoleStart = is_int($staleSignalStart)
    ? strpos($coreSource, "->from('auth_user_role ur')", $staleSignalStart)
    : false;
$staleSignalEnd = is_int($staleRoleStart)
    ? strpos($coreSource, 'if ($isStale)', $staleRoleStart)
    : false;
$staleRoleBlock = is_int($staleRoleStart) && is_int($staleSignalEnd)
    ? substr($coreSource, $staleRoleStart, $staleSignalEnd - $staleRoleStart)
    : '';
auth_division_scope_smoke_expect(
    $staleRoleBlock !== '' && strpos($staleRoleBlock, "->where('r.is_active', 1)") === false,
    'stale role signal includes recently disabled roles'
);
$resolveScopeStart = strpos($authModelSource, 'public function resolve_division_scope');
$superadminResolverStart = is_int($resolveScopeStart)
    ? strpos($authModelSource, 'private function _has_superadmin_role', $resolveScopeStart)
    : false;
$resolveScopeBlock = is_int($resolveScopeStart) && is_int($superadminResolverStart)
    ? substr($authModelSource, $resolveScopeStart, $superadminResolverStart - $resolveScopeStart)
    : '';
$superadminResolverEnd = is_int($superadminResolverStart)
    ? strpos($authModelSource, 'private function _get_role_permissions', $superadminResolverStart)
    : false;
$superadminResolverBlock = is_int($superadminResolverStart) && is_int($superadminResolverEnd)
    ? substr($authModelSource, $superadminResolverStart, $superadminResolverEnd - $superadminResolverStart)
    : '';
auth_division_scope_smoke_expect(
    strpos($resolveScopeBlock, "->where('r.is_active', 1)") !== false
        && strpos($superadminResolverBlock, "->where('r.is_active', 1)") !== false,
    'scope and superadmin resolvers retain active-role filters'
);

$freshSessionData = [
    'auth_user' => ['id' => 42, 'is_superadmin' => false],
    'user_perms' => [],
    'user_perms_cached_at' => time(),
    'perms_staleness_checked_at' => time(),
    'user_division_scope_state' => 'SINGLE',
    'user_division_scope' => 7,
];
$freshEnvironment = auth_division_scope_smoke_environment($freshSessionData);
AuthDivisionScopeSmokeController::$controllerWork = 0;
$freshController = new AuthDivisionScopeSmokeController();
auth_division_scope_smoke_expect(
    AuthDivisionScopeSmokeController::$controllerWork === 1,
    'valid SINGLE session reaches child controller work'
);

$globalSessionData = $freshSessionData;
$globalSessionData['user_division_scope_state'] = 'GLOBAL';
$globalSessionData['user_division_scope'] = null;
$globalEnvironment = auth_division_scope_smoke_environment($globalSessionData);
AuthDivisionScopeSmokeController::$controllerWork = 0;
new AuthDivisionScopeSmokeController();
auth_division_scope_smoke_expect(
    AuthDivisionScopeSmokeController::$controllerWork === 1,
    'valid GLOBAL session reaches child controller work'
);

$invalidSessionData = $freshSessionData;
$invalidSessionData['user_division_scope_state'] = 'AMBIGUOUS';
$invalidSessionData['user_division_scope'] = null;
$invalidEnvironment = auth_division_scope_smoke_environment($invalidSessionData);
AuthDivisionScopeSmokeController::$controllerWork = 0;
try {
    new AuthDivisionScopeSmokeController();
    auth_division_scope_smoke_expect(false, 'invalid scope is denied with HTML 403');
} catch (AuthDivisionScopeSmokeForbidden $e) {
    auth_division_scope_smoke_expect($e->getCode() === 403, 'invalid scope returns HTML 403');
}
auth_division_scope_smoke_expect(
    AuthDivisionScopeSmokeController::$controllerWork === 0
        && $invalidEnvironment->session->userdata('user_division_scope_state') === 'AMBIGUOUS',
    'invalid scope is denied before child controller/model work'
);

$directGateCases = [
    'NONE with zero division id' => ['NONE', 0],
    'NONE with negative division id' => ['NONE', -1],
    'SINGLE with zero division id' => ['SINGLE', 0],
    'SINGLE with negative division id' => ['SINGLE', -1],
];
foreach ($directGateCases as $label => [$state, $divisionId]) {
    $directGateSessionData = $freshSessionData;
    $directGateSessionData['user_division_scope_state'] = $state;
    $directGateSessionData['user_division_scope'] = $divisionId;
    $directGateEnvironment = auth_division_scope_smoke_environment($directGateSessionData);
    AuthDivisionScopeSmokeController::$controllerWork = 0;
    $directGateDenied = false;
    try {
        new AuthDivisionScopeSmokeController();
    } catch (AuthDivisionScopeSmokeForbidden $e) {
        $directGateDenied = $e->getCode() === 403;
    }
    auth_division_scope_smoke_expect($directGateDenied, $label . ' returns HTML 403');
    auth_division_scope_smoke_expect(
        AuthDivisionScopeSmokeController::$controllerWork === 0,
        $label . ' is denied before child controller/model work'
    );
}

$legacySessionData = [
    'auth_user' => ['id' => 42, 'is_superadmin' => false],
    'user_perms' => [],
    'user_perms_cached_at' => time(),
    'perms_staleness_checked_at' => time(),
];
$legacyEnvironment = auth_division_scope_smoke_environment($legacySessionData, [
    'auth_user' => ['id' => 42, 'is_superadmin' => false],
    'user_perms' => [],
    'user_perms_cached_at' => time(),
    'user_division_scope_state' => 'SINGLE',
    'user_division_scope' => 7,
]);
AuthDivisionScopeSmokeController::$controllerWork = 0;
new AuthDivisionScopeSmokeController();
auth_division_scope_smoke_expect(
    $legacyEnvironment->authModel->refreshCalls === 1
        && $legacyEnvironment->session->userdata('user_division_scope_state') === 'SINGLE'
        && AuthDivisionScopeSmokeController::$controllerWork === 1,
    'legacy session refreshes once and reaches work after SINGLE resolution'
);

$legacyDeniedEnvironment = auth_division_scope_smoke_environment($legacySessionData, [
    'auth_user' => ['id' => 42, 'is_superadmin' => false],
    'user_perms' => [],
    'user_perms_cached_at' => time(),
    'user_division_scope_state' => 'NONE',
    'user_division_scope' => null,
]);
AuthDivisionScopeSmokeController::$controllerWork = 0;
try {
    new AuthDivisionScopeSmokeController();
    auth_division_scope_smoke_expect(false, 'unresolved legacy scope is denied');
} catch (AuthDivisionScopeSmokeForbidden $e) {
    auth_division_scope_smoke_expect($e->getCode() === 403, 'unresolved legacy scope returns 403');
}
auth_division_scope_smoke_expect(
    $legacyDeniedEnvironment->authModel->refreshCalls === 1
        && AuthDivisionScopeSmokeController::$controllerWork === 0,
    'legacy unresolved scope refreshes once and denies before child work'
);

$superadminEnvironment = auth_division_scope_smoke_environment([
    'auth_user' => ['id' => 1, 'is_superadmin' => true],
    'user_perms' => ['__superadmin__' => true],
    'user_perms_cached_at' => time(),
    'perms_staleness_checked_at' => time(),
]);
AuthDivisionScopeSmokeController::$controllerWork = 0;
new AuthDivisionScopeSmokeController();
auth_division_scope_smoke_expect(
    AuthDivisionScopeSmokeController::$controllerWork === 1
        && !$superadminEnvironment->session->has('user_division_scope_state'),
    'superadmin bypasses division scope gate'
);

$revokedSuperadminEnvironment = auth_division_scope_smoke_environment([
    'auth_user' => ['id' => 1, 'is_superadmin' => true],
    'user_perms' => ['__superadmin__' => true],
    'user_perms_cached_at' => time() - 300,
    'perms_staleness_checked_at' => 0,
], [
    'auth_user' => ['id' => 1, 'is_superadmin' => false],
    'user_perms' => [],
    'user_perms_cached_at' => time(),
    'user_division_scope_state' => 'SINGLE',
    'user_division_scope' => 7,
], false, true, false);
AuthDivisionScopeSmokeController::$controllerWork = 0;
$refreshedController = new AuthDivisionScopeSmokeController();
auth_division_scope_smoke_expect(
    !$refreshedController->smoke_is_superadmin()
        && $revokedSuperadminEnvironment->authModel->refreshCalls === 1
        && AuthDivisionScopeSmokeController::$controllerWork === 1,
    'permission refresh reloads current_user when superadmin is revoked'
);

$disabledSuperadminEnvironment = auth_division_scope_smoke_environment([
    'auth_user' => ['id' => 1, 'is_superadmin' => true],
    'user_perms' => ['__superadmin__' => true],
    'user_perms_cached_at' => time() - 300,
    'perms_staleness_checked_at' => 0,
], [
    'auth_user' => ['id' => 1, 'is_superadmin' => false],
    'user_perms' => [],
    'user_perms_cached_at' => time(),
    'user_division_scope_state' => 'NONE',
    'user_division_scope' => null,
], false, true, false);
AuthDivisionScopeSmokeController::$controllerWork = 0;
$disabledSuperadminDenied = false;
try {
    new AuthDivisionScopeSmokeController();
} catch (AuthDivisionScopeSmokeForbidden $e) {
    $disabledSuperadminDenied = $e->getCode() === 403;
}
auth_division_scope_smoke_expect(
    $disabledSuperadminEnvironment->authModel->refreshCalls === 1
        && $disabledSuperadminEnvironment->session->userdata('auth_user')['is_superadmin'] === false
        && $disabledSuperadminEnvironment->session->userdata('user_division_scope_state') === 'NONE'
        && $disabledSuperadminDenied,
    'cached SUPERADMIN with disabled role refreshes to non-superadmin/NONE and returns 403'
);
auth_division_scope_smoke_expect(
    AuthDivisionScopeSmokeController::$controllerWork === 0,
    'disabled SUPERADMIN request is denied before child controller/model work'
);

$loginEnvironment = auth_division_scope_smoke_environment(
    [],
    [],
    false,
    false,
    true,
    new AuthDivisionScopeSmokeInput([
        'identifier' => 'scope-user',
        'password' => 'correct-password',
    ])
);
$loginEnvironment->authModel->configureLogin(
    ['id' => 42, 'username' => 'scope-user', 'email' => 'scope@example.test', 'is_active' => 1],
    ['dashboard.index' => ['can_view' => 1]],
    ['state' => 'NONE', 'division_id' => null]
);
$loginRedirected = false;
try {
    $loginController = new Auth();
    $loginController->do_login();
} catch (AuthDivisionScopeSmokeRedirect $e) {
    $loginRedirected = $e->getMessage() === 'login';
}
auth_division_scope_smoke_expect(
    $loginRedirected
        && $loginEnvironment->authModel->attemptLoginCalls === 1
        && $loginEnvironment->session->flashdata('login_error') === 'Konfigurasi akses akun belum lengkap. Hubungi administrator.',
    'login rejects a non-superadmin without a valid division scope'
);
auth_division_scope_smoke_expect(
    !$loginEnvironment->session->has('auth_user')
        && !$loginEnvironment->session->has('session_log_id')
        && !$loginEnvironment->session->destroyed
        && $loginEnvironment->authModel->loginLogCalls === 0,
    'rejected login creates no authenticated session or login log'
);

if ($authDivisionScopeSmokeFailures !== []) {
    fwrite(STDERR, "FAIL: auth division scope smoke test\n");
    foreach ($authDivisionScopeSmokeFailures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo 'PASS: ' . $authDivisionScopeSmokeChecks . " auth division scope checks\n";
