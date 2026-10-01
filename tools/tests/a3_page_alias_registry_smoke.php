<?php
declare(strict_types=1);

// DB-free behavioral and migration-contract smoke for A3-4.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

if (!class_exists('CI_Controller')) {
    class CI_Controller
    {
    }
}

final class A3AliasResult
{
    public function __construct(private array $rows)
    {
    }

    public function result_array(): array
    {
        return $this->rows;
    }
}

final class A3AliasDb
{
    private array $where = [];
    private array $joins = [];
    private string $from = '';
    public int $aliasQueryCount = 0;
    public int $tableExistsCount = 0;

    public function __construct(private array $aliases, private bool $tableExists = true)
    {
    }

    public function table_exists(string $table): bool
    {
        if ($table !== 'sys_page_alias') {
            throw new RuntimeException('Unexpected table_exists target: ' . $table);
        }
        $this->tableExistsCount++;
        return $this->tableExists;
    }

    public function select(string $fields): self
    {
        if ($fields !== 'alias.alias_code, page.page_code AS canonical_page_code') {
            throw new RuntimeException('Alias resolver select contract drifted.');
        }
        return $this;
    }

    public function from(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    public function join(string $table, string $condition): self
    {
        $this->joins[] = [$table, $condition];
        return $this;
    }

    public function where(string $field, mixed $value): self
    {
        $this->where[$field] = $value;
        return $this;
    }

    public function get(): A3AliasResult
    {
        $this->aliasQueryCount++;
        if ($this->from !== 'sys_page_alias alias'
            || !in_array(['sys_page page', 'page.id = alias.page_id'], $this->joins, true)
            || ($this->where['alias.is_active'] ?? null) !== 1
            || ($this->where['page.is_active'] ?? null) !== 1) {
            throw new RuntimeException('Alias resolver did not enforce both active predicates.');
        }

        $rows = [];
        foreach ($this->aliases as $alias) {
            if (($alias['alias_active'] ?? 0) === 1 && ($alias['page_active'] ?? 0) === 1) {
                $rows[] = [
                    'alias_code' => $alias['alias_code'],
                    'canonical_page_code' => $alias['canonical_page_code'],
                ];
            }
        }
        return new A3AliasResult($rows);
    }
}

final class A3AliasSession
{
    public function __construct(private array $data)
    {
    }

    public function userdata(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set_userdata(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}

final class A3AliasAuthModel
{
    public function __construct(private A3AliasSession $session, private array $refreshedPermissions)
    {
    }

    public function refresh_permissions(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Refresh received an invalid user.');
        }
        $this->session->set_userdata('user_perms', $this->refreshedPermissions);
    }
}

final class A3AliasLoader
{
    public function __construct(private A3AliasHarness $controller, private A3AliasAuthModel $authModel)
    {
    }

    public function model(string $name): void
    {
        if ($name !== 'Auth_model') {
            throw new RuntimeException('Unexpected model load: ' . $name);
        }
        $this->controller->Auth_model = $this->authModel;
    }
}

require_once dirname(__DIR__, 2) . '/application/core/MY_Controller.php';

final class A3AliasHarness extends MY_Controller
{
    public A3AliasDb $db;
    public A3AliasSession $session;
    public A3AliasLoader $load;
    public A3AliasAuthModel $Auth_model;

    public function __construct(A3AliasDb $db, array $permissions, array $user = [])
    {
        $this->db = $db;
        $this->user_perms = $permissions;
        $this->current_user = $user;
        $this->session = new A3AliasSession([
            'auth_user' => $user,
            'user_perms' => $permissions,
        ]);
        $authModel = new A3AliasAuthModel($this->session, $permissions);
        $this->load = new A3AliasLoader($this, $authModel);
    }

    public function setRefreshPermissions(array $permissions): void
    {
        $this->load = new A3AliasLoader($this, new A3AliasAuthModel($this->session, $permissions));
    }

    public function allowed(string $pageCode, string $action = 'view'): bool
    {
        return $this->can($pageCode, $action);
    }
}

function a3Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$aliasRows = [
    ['alias_code' => 'alias.canonical-allowed', 'canonical_page_code' => 'canonical.allowed', 'alias_active' => 1, 'page_active' => 1],
    ['alias_code' => 'alias.canonical-denied', 'canonical_page_code' => 'canonical.denied', 'alias_active' => 1, 'page_active' => 1],
    ['alias_code' => 'alias.disabled', 'canonical_page_code' => 'canonical.allowed', 'alias_active' => 0, 'page_active' => 1],
    ['alias_code' => 'alias.inactive-page', 'canonical_page_code' => 'canonical.allowed', 'alias_active' => 1, 'page_active' => 0],
];
$db = new A3AliasDb($aliasRows);
$harness = new A3AliasHarness($db, [
    'direct.page' => ['can_view' => 1],
    'alias.canonical-allowed' => ['can_view' => 0],
    'alias.canonical-denied' => ['can_view' => 1],
    'canonical.allowed' => ['can_view' => 1],
    'canonical.denied' => ['can_view' => 0],
]);

a3Assert($harness->allowed('direct.page'), 'Non-alias page should use its direct permission.');
a3Assert($db->aliasQueryCount === 1, 'Alias registry must be resolved before direct permission.');
a3Assert($harness->allowed('alias.canonical-allowed'), 'Stale alias revoke must not override canonical grant.');
a3Assert(!$harness->allowed('alias.canonical-denied'), 'Stale alias grant must not override canonical revoke.');
a3Assert(!$harness->allowed('alias.disabled'), 'Disabled alias must be denied.');
a3Assert(!$harness->allowed('alias.inactive-page'), 'Alias to inactive canonical page must be denied.');
a3Assert(!$harness->allowed('alias.missing'), 'Unregistered alias must fail closed.');
a3Assert($db->aliasQueryCount === 1 && $db->tableExistsCount === 1, 'Alias map must be loaded only once per request.');

$missingTableDb = new A3AliasDb([], false);
$missingTableHarness = new A3AliasHarness($missingTableDb, []);
a3Assert(!$missingTableHarness->allowed('alias.fallback'), 'Missing alias table must fail closed.');
a3Assert($missingTableDb->aliasQueryCount === 0 && $missingTableDb->tableExistsCount === 1, 'Missing table check must also be cached.');

$refreshDb = new A3AliasDb([
    ['alias_code' => 'alias.refresh', 'canonical_page_code' => 'canonical.refresh', 'alias_active' => 1, 'page_active' => 1],
]);
$refreshHarness = new A3AliasHarness($refreshDb, [], ['id' => 7, 'is_superadmin' => false]);
$refreshHarness->setRefreshPermissions(['canonical.refresh' => ['can_view' => 1]]);
a3Assert($refreshHarness->allowed('alias.refresh'), 'Permission refresh final check must use the resolved canonical code.');

$superDb = new A3AliasDb([], false);
$superHarness = new A3AliasHarness($superDb, [], ['id' => 1, 'is_superadmin' => true]);
a3Assert($superHarness->allowed('anything'), 'Superadmin bypass behavior must stay unchanged.');
a3Assert($superDb->tableExistsCount === 0, 'Superadmin bypass must not query alias registry.');

$root = dirname(__DIR__, 2);
$mySource = (string)file_get_contents($root . '/application/controllers/My.php');
a3Assert(
    preg_match('/private function require_registered_page_permission\(string \$pageCode\): void\s*\{\s*\$this->require_permission\(\$pageCode, \'view\'\);\s*\}/s', $mySource) === 1,
    'My::require_registered_page_permission must always call require_permission.'
);

$sql = (string)file_get_contents($root . '/sql/2026-09-04b_a3_page_alias_registry.sql');
$expectedAliases = [
    'attendance.schedules.v2.index' => 'attendance.schedules.index',
    'my.schedule.index' => 'my.attendance.index',
    'pos.stock.commit.audit.index' => 'pos.stock.live.index',
    'product.monitoring.availability.index' => 'product.availability',
    'production.component.lot.index' => 'production.component.lots',
    'production.component.reconcile.index' => 'production.component.daily.index',
    'purchase.account.index' => 'finance.account.index',
    'purchase.stock.division.lot.index' => 'purchase.stock.division.index',
    'purchase.stock.opening.index' => 'purchase.stock.opening.warehouse.index',
    'purchase.stock.warehouse.lot.index' => 'purchase.stock.warehouse.index',
];

a3Assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS sys_page_alias'), 'Alias table DDL is missing.');
a3Assert(str_contains($sql, 'UNIQUE KEY uk_sys_page_alias_code (alias_code)'), 'Alias code UNIQUE contract is missing.');
a3Assert(str_contains($sql, 'FOREIGN KEY (page_id) REFERENCES sys_page(id)'), 'Canonical page FK is missing.');
a3Assert(str_contains($sql, "table_name = 'sys_page'"), 'sys_page existence preflight is missing.');
a3Assert(str_contains($sql, "CALL sp_a3_page_alias_registry_assert_20260904b('PRE');"), 'Preflight call is missing.');
a3Assert(str_contains($sql, "CALL sp_a3_page_alias_registry_assert_20260904b('POST');"), 'Postflight call is missing.');
a3Assert(str_contains($sql, 'active alias points to missing or inactive page'), 'Invalid active-alias postflight is missing.');
a3Assert(substr_count($sql, 'alias_code collides with sys_page.page_code') >= 3, 'PRE/POST page-code collision guards are missing.');
a3Assert(str_contains($sql, 'globally UNIQUE across active and inactive rows'), 'Global UNIQUE semantics are not explicit.');
a3Assert(!str_contains($sql, 'must contain exactly 10'), 'Postflight must allow future valid aliases.');
a3Assert(!preg_match('/INSERT\s+INTO\s+auth_(?:role_permission|user_permission_override)/i', $sql), 'Migration must not copy permissions.');

foreach ($expectedAliases as $aliasCode => $canonicalCode) {
    $mappingPattern = "/'" . preg_quote($aliasCode, '/') . "'(?:\\s+alias_code)?,\\s*'"
        . preg_quote($canonicalCode, '/') . "'(?:\\s+canonical_page_code)?/";
    a3Assert(preg_match($mappingPattern, $sql) === 1, 'Missing SQL seed mapping: ' . $aliasCode);
}

echo 'PASS: non-alias direct and alias canonical-only/disabled/missing behavior.' . PHP_EOL;
echo 'PASS: alias registry query/table check are cached once per request.' . PHP_EOL;
echo 'PASS: resolved-code refresh and My fail-closed guard.' . PHP_EOL;
echo 'PASS: SQL DDL, PRE/POST collision guards, extensible postflight, 10 seeds, and no permission copying.' . PHP_EOL;
