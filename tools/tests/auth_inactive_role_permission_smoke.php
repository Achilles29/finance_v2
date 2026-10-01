<?php
declare(strict_types=1);

/**
 * DB-free smoke test for Batch 7 P0-04B.
 *
 * The real Auth_model is loaded below. The fake query builder executes the
 * user-role-role-permission-page relationships in memory; it is not a source
 * string assertion. Role-permission queries throw if the active-role join or
 * predicate is omitted, so the permission scenarios exercise that contract.
 */
defined('BASEPATH') OR define('BASEPATH', __DIR__);

if (!class_exists('CI_Model')) {
    class CI_Model
    {
        public $db;
        public $session;
    }
}

final class AuthInactiveRolePermissionSmokeResult
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

final class AuthInactiveRolePermissionSmokeDb
{
    private ?string $selectedFields = null;
    private ?string $table = null;
    private array $joins = [];
    private array $whereConditions = [];
    private ?int $queryLimit = null;
    private array $executedQueries = [];

    public function __construct(
        private array $roles,
        private array $userRoles,
        private array $pages,
        private array $rolePermissions,
        private array $overrides = []
    ) {
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
        $this->joins[] = [
            'table'     => (string)$table,
            'condition' => (string)$condition,
            'type'      => (string)$type,
        ];
        return $this;
    }

    public function where($field, $value = null, $escape = null): self
    {
        $this->whereConditions[] = [(string)$field, $value];
        return $this;
    }

    public function limit($limit): self
    {
        $this->queryLimit = (int)$limit;
        return $this;
    }

    public function get(): AuthInactiveRolePermissionSmokeResult
    {
        $query = [
            'select' => $this->selectedFields,
            'from'   => $this->table,
            'joins'  => $this->joins,
            'where'  => $this->whereConditions,
            'limit'  => $this->queryLimit,
        ];
        $this->executedQueries[] = $query;

        try {
            $rows = $this->execute($query);
        } finally {
            $this->resetQuery();
        }

        return new AuthInactiveRolePermissionSmokeResult($rows);
    }

    public function executedQueries(): array
    {
        return $this->executedQueries;
    }

    private function execute(array $query): array
    {
        if ($query['from'] === 'auth_user_role ur') {
            $this->requireJoinAndWhere(
                $query,
                'auth_role r',
                'r.id = ur.role_id',
                'r.is_active',
                1
            );

            if ($query['select'] === '1') {
                return $this->executeSuperadminQuery($query);
            }

            if (str_contains((string)$query['select'], 'rp.can_view')) {
                $this->requireJoin($query, 'auth_role_permission rp', 'rp.role_id = ur.role_id');
                $this->requireJoin($query, 'sys_page p', 'p.id = rp.page_id');
                $this->requireWhere($query, 'p.is_active', 1);
                return $this->executeRolePermissionQuery($query);
            }

            if (str_contains((string)$query['select'], 'r.division_scope_id')) {
                return $this->executeDivisionScopeQuery($query);
            }
        }

        if ($query['from'] === 'auth_user_permission_override o') {
            $this->requireJoin($query, 'sys_page p', 'p.id = o.page_id');
            $this->requireWhere($query, 'p.is_active', 1);
            return $this->executeOverrideQuery($query);
        }

        return [];
    }

    private function executeSuperadminQuery(array $query): array
    {
        $userId = $this->whereValue($query, 'ur.user_id');
        $roleCode = $this->whereValue($query, 'r.role_code');
        $rows = [];

        foreach ($this->userRoles as $assignment) {
            if ((int)$assignment['user_id'] !== (int)$userId) {
                continue;
            }

            $role = $this->findRole($assignment['role_id']);
            if ($role === null || (int)$role['is_active'] !== 1 || $role['role_code'] !== $roleCode) {
                continue;
            }

            $rows[] = ['1' => 1];
        }

        return $this->applyLimit($rows, $query['limit']);
    }

    private function executeRolePermissionQuery(array $query): array
    {
        $userId = $this->whereValue($query, 'ur.user_id');
        $rows = [];

        foreach ($this->userRoles as $assignment) {
            if ((int)$assignment['user_id'] !== (int)$userId) {
                continue;
            }

            $role = $this->findRole($assignment['role_id']);
            if ($role === null || (int)$role['is_active'] !== 1) {
                continue;
            }

            foreach ($this->rolePermissions as $permission) {
                if ((string)$permission['role_id'] !== (string)$assignment['role_id']) {
                    continue;
                }

                $page = $this->findPage($permission['page_id']);
                if ($page === null || (int)$page['is_active'] !== 1) {
                    continue;
                }

                $rows[] = [
                    'page_code'  => $page['page_code'],
                    'can_view'   => $permission['can_view'],
                    'can_create' => $permission['can_create'],
                    'can_edit'   => $permission['can_edit'],
                    'can_delete' => $permission['can_delete'],
                    'can_export' => $permission['can_export'],
                ];
            }
        }

        return $this->applyLimit($rows, $query['limit']);
    }

    private function executeDivisionScopeQuery(array $query): array
    {
        $userId = $this->whereValue($query, 'ur.user_id');
        $rows = [];

        foreach ($this->userRoles as $assignment) {
            if ((int)$assignment['user_id'] !== (int)$userId) {
                continue;
            }

            $role = $this->findRole($assignment['role_id']);
            if ($role !== null && (int)$role['is_active'] === 1) {
                $rows[] = ['division_scope_id' => $role['division_scope_id'] ?? null];
            }
        }

        return $rows;
    }

    private function executeOverrideQuery(array $query): array
    {
        $userId = $this->whereValue($query, 'o.user_id');
        $type = $this->whereValue($query, 'o.override_type');
        $rows = [];

        foreach ($this->overrides as $override) {
            if ((int)$override['user_id'] !== (int)$userId || $override['override_type'] !== $type) {
                continue;
            }

            $page = $this->findPage($override['page_id']);
            if ($page === null || (int)$page['is_active'] !== 1) {
                continue;
            }

            $rows[] = [
                'page_code'  => $page['page_code'],
                'can_view'   => $override['can_view'],
                'can_create' => $override['can_create'],
                'can_edit'   => $override['can_edit'],
                'can_delete' => $override['can_delete'],
                'can_export' => $override['can_export'],
            ];
        }

        return $rows;
    }

    private function requireJoinAndWhere(
        array $query,
        string $table,
        string $condition,
        string $field,
        $value
    ): void {
        $this->requireJoin($query, $table, $condition);
        $this->requireWhere($query, $field, $value);
    }

    private function requireJoin(array $query, string $table, string $condition): void
    {
        foreach ($query['joins'] as $join) {
            if ($join['table'] === $table && $this->normalizeSql($join['condition']) === $this->normalizeSql($condition)) {
                return;
            }
        }

        throw new RuntimeException("Query contract violation: missing join {$table} on {$condition}");
    }

    private function requireWhere(array $query, string $field, $value): void
    {
        foreach ($query['where'] as [$queryField, $queryValue]) {
            if ($queryField === $field && (string)$queryValue === (string)$value) {
                return;
            }
        }

        throw new RuntimeException("Query contract violation: missing where {$field} = {$value}");
    }

    private function whereValue(array $query, string $field)
    {
        foreach ($query['where'] as [$queryField, $value]) {
            if ($queryField === $field) {
                return $value;
            }
        }

        throw new RuntimeException("Query contract violation: missing where {$field}");
    }

    private function findRole($roleId): ?array
    {
        foreach ($this->roles as $role) {
            if ((string)$role['id'] === (string)$roleId) {
                return $role;
            }
        }

        return null;
    }

    private function findPage($pageId): ?array
    {
        foreach ($this->pages as $page) {
            if ((string)$page['id'] === (string)$pageId) {
                return $page;
            }
        }

        return null;
    }

    private function applyLimit(array $rows, ?int $limit): array
    {
        return $limit === null ? $rows : array_slice($rows, 0, $limit);
    }

    private function normalizeSql(string $sql): string
    {
        return strtolower((string)preg_replace('/\s+/', ' ', trim($sql)));
    }

    private function resetQuery(): void
    {
        $this->selectedFields = null;
        $this->table = null;
        $this->joins = [];
        $this->whereConditions = [];
        $this->queryLimit = null;
    }
}

final class AuthInactiveRolePermissionSmokeSession
{
    public function __construct(private array $data = [])
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
}

require dirname(__DIR__, 2) . '/application/models/Auth_model.php';

function auth_inactive_role_permission_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function auth_inactive_role_permission_model(
    AuthInactiveRolePermissionSmokeDb $db,
    ?AuthInactiveRolePermissionSmokeSession $session = null
): Auth_model {
    $model = new Auth_model();
    $model->db = $db;
    $model->session = $session ?? new AuthInactiveRolePermissionSmokeSession();
    return $model;
}

function auth_inactive_role_permission_assert_query_contract(
    AuthInactiveRolePermissionSmokeDb $db,
    string $message
): void {
    $permissionQueries = array_values(array_filter(
        $db->executedQueries(),
        static fn(array $query): bool => $query['from'] === 'auth_user_role ur'
            && str_contains((string)$query['select'], 'rp.can_view')
    ));

    auth_inactive_role_permission_expect($permissionQueries !== [], "{$message}: permission query executed");

    foreach ($permissionQueries as $query) {
        $hasRoleJoin = false;
        foreach ($query['joins'] as $join) {
            if ($join['table'] === 'auth_role r'
                && strtolower((string)preg_replace('/\s+/', ' ', trim($join['condition']))) === 'r.id = ur.role_id') {
                $hasRoleJoin = true;
            }
        }

        $hasActiveRoleFilter = false;
        $hasActivePageFilter = false;
        foreach ($query['where'] as [$field, $value]) {
            if ($field === 'r.is_active' && (string)$value === '1') {
                $hasActiveRoleFilter = true;
            }
            if ($field === 'p.is_active' && (string)$value === '1') {
                $hasActivePageFilter = true;
            }
        }

        auth_inactive_role_permission_expect(
            $hasRoleJoin && $hasActiveRoleFilter && $hasActivePageFilter,
            "{$message}: executed permission query joins/filter active role and active page"
        );
    }
}

$inactiveOnlyDb = new AuthInactiveRolePermissionSmokeDb(
    roles: [
        ['id' => 1, 'role_code' => 'CASHIER', 'is_active' => 0, 'division_scope_id' => 7],
    ],
    userRoles: [
        ['user_id' => 100, 'role_id' => 1],
    ],
    pages: [
        ['id' => 10, 'page_code' => 'pos.order', 'is_active' => 1],
    ],
    rolePermissions: [
        ['role_id' => 1, 'page_id' => 10, 'can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_export' => 0],
    ]
);
$inactiveOnlyPermissions = auth_inactive_role_permission_model($inactiveOnlyDb)->load_permissions(100);
auth_inactive_role_permission_expect($inactiveOnlyPermissions === [], 'inactive-only role produces zero permissions');
auth_inactive_role_permission_assert_query_contract($inactiveOnlyDb, 'inactive-only role');

$mixedRoleDb = new AuthInactiveRolePermissionSmokeDb(
    roles: [
        ['id' => 1, 'role_code' => 'ACTIVE_ROLE', 'is_active' => 1, 'division_scope_id' => 7],
        ['id' => 2, 'role_code' => 'INACTIVE_ROLE', 'is_active' => 0, 'division_scope_id' => 7],
    ],
    userRoles: [
        ['user_id' => 101, 'role_id' => 1],
        ['user_id' => 101, 'role_id' => 2],
    ],
    pages: [
        ['id' => 10, 'page_code' => 'pos.order', 'is_active' => 1],
        ['id' => 11, 'page_code' => 'pos.legacy', 'is_active' => 0],
    ],
    rolePermissions: [
        ['role_id' => 1, 'page_id' => 10, 'can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0],
        ['role_id' => 1, 'page_id' => 11, 'can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'can_export' => 1],
        ['role_id' => 2, 'page_id' => 10, 'can_view' => 0, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'can_export' => 1],
    ]
);
$mixedRolePermissions = auth_inactive_role_permission_model($mixedRoleDb)->load_permissions(101);
auth_inactive_role_permission_expect(
    $mixedRolePermissions === [
        'pos.order' => ['can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0],
    ],
    'active and inactive roles on the same page use only the active role and active pages'
);
auth_inactive_role_permission_assert_query_contract($mixedRoleDb, 'mixed active/inactive roles');

$unionDb = new AuthInactiveRolePermissionSmokeDb(
    roles: [
        ['id' => 3, 'role_code' => 'ROLE_A', 'is_active' => 1, 'division_scope_id' => 7],
        ['id' => 4, 'role_code' => 'ROLE_B', 'is_active' => 1, 'division_scope_id' => 7],
    ],
    userRoles: [
        ['user_id' => 102, 'role_id' => 3],
        ['user_id' => 102, 'role_id' => 4],
    ],
    pages: [
        ['id' => 20, 'page_code' => 'reports.sales', 'is_active' => 1],
        ['id' => 21, 'page_code' => 'reports.stock', 'is_active' => 1],
    ],
    rolePermissions: [
        ['role_id' => 3, 'page_id' => 20, 'can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0],
        ['role_id' => 4, 'page_id' => 20, 'can_view' => 0, 'can_create' => 0, 'can_edit' => 1, 'can_delete' => 0, 'can_export' => 0],
        ['role_id' => 4, 'page_id' => 21, 'can_view' => 0, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 1],
    ]
);
$unionPermissions = auth_inactive_role_permission_model($unionDb)->load_permissions(102);
auth_inactive_role_permission_expect(
    $unionPermissions === [
        'reports.sales' => ['can_view' => 1, 'can_create' => 0, 'can_edit' => 1, 'can_delete' => 0, 'can_export' => 0],
        'reports.stock' => ['can_view' => 0, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 1],
    ],
    'two active roles retain union permission semantics'
);
auth_inactive_role_permission_assert_query_contract($unionDb, 'two active roles');

$overrideDb = new AuthInactiveRolePermissionSmokeDb(
    roles: [
        ['id' => 5, 'role_code' => 'ROLE_WITH_OVERRIDES', 'is_active' => 1, 'division_scope_id' => 7],
    ],
    userRoles: [
        ['user_id' => 105, 'role_id' => 5],
    ],
    pages: [
        ['id' => 30, 'page_code' => 'reports.audit', 'is_active' => 1],
        ['id' => 31, 'page_code' => 'reports.inactive', 'is_active' => 0],
    ],
    rolePermissions: [
        ['role_id' => 5, 'page_id' => 30, 'can_view' => 1, 'can_create' => 0, 'can_edit' => 1, 'can_delete' => 0, 'can_export' => 0],
    ],
    overrides: [
        ['user_id' => 105, 'page_id' => 30, 'override_type' => 'GRANT', 'can_view' => 0, 'can_create' => 1, 'can_edit' => 0, 'can_delete' => 1, 'can_export' => 0],
        ['user_id' => 105, 'page_id' => 30, 'override_type' => 'REVOKE', 'can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0],
        ['user_id' => 105, 'page_id' => 31, 'override_type' => 'GRANT', 'can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'can_export' => 1],
    ]
);
$overridePermissions = auth_inactive_role_permission_model($overrideDb)->load_permissions(105);
auth_inactive_role_permission_expect(
    $overridePermissions === [
        'reports.audit' => ['can_view' => 0, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'can_export' => 0],
    ],
    'active-page GRANT/REVOKE overrides retain their existing semantics'
);
auth_inactive_role_permission_assert_query_contract($overrideDb, 'override semantics');

$inactiveSuperadminDb = new AuthInactiveRolePermissionSmokeDb(
    roles: [
        ['id' => 6, 'role_code' => 'SUPERADMIN', 'is_active' => 0, 'division_scope_id' => null],
    ],
    userRoles: [
        ['user_id' => 103, 'role_id' => 6],
    ],
    pages: [
        ['id' => 40, 'page_code' => 'admin.users', 'is_active' => 1],
    ],
    rolePermissions: [
        ['role_id' => 6, 'page_id' => 40, 'can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'can_export' => 1],
    ]
);
$inactiveSuperadminPermissions = auth_inactive_role_permission_model($inactiveSuperadminDb)->load_permissions(103);
auth_inactive_role_permission_expect(
    $inactiveSuperadminPermissions === []
        && !array_key_exists('__superadmin__', $inactiveSuperadminPermissions),
    'inactive SUPERADMIN produces neither __superadmin__ nor permissions'
);
auth_inactive_role_permission_assert_query_contract($inactiveSuperadminDb, 'inactive SUPERADMIN');

$refreshDb = new AuthInactiveRolePermissionSmokeDb(
    roles: [
        ['id' => 7, 'role_code' => 'SCOPED_ROLE', 'is_active' => 1, 'division_scope_id' => 9],
    ],
    userRoles: [
        ['user_id' => 104, 'role_id' => 7],
    ],
    pages: [
        ['id' => 50, 'page_code' => 'dashboard', 'is_active' => 1],
    ],
    rolePermissions: [
        ['role_id' => 7, 'page_id' => 50, 'can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0],
    ]
);
$refreshSession = new AuthInactiveRolePermissionSmokeSession([
    'user_perms' => [
        'legacy.page' => ['can_view' => 1],
        '__superadmin__' => true,
    ],
    'auth_user' => ['id' => 104, 'is_superadmin' => true],
    'user_division_scope_state' => 'AMBIGUOUS',
    'user_division_scope' => null,
]);
$refreshModel = auth_inactive_role_permission_model($refreshDb, $refreshSession);
$refreshModel->refresh_permissions(104);
auth_inactive_role_permission_expect(
    $refreshSession->userdata('user_perms') === [
        'dashboard' => ['can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0],
    ]
        && !array_key_exists('legacy.page', $refreshSession->userdata('user_perms'))
        && !array_key_exists('__superadmin__', $refreshSession->userdata('user_perms')),
    'refresh_permissions replaces stale session permissions'
);
auth_inactive_role_permission_expect(
    $refreshSession->userdata('user_division_scope_state') === 'SINGLE'
        && $refreshSession->userdata('user_division_scope') === 9
        && $refreshSession->userdata('auth_user')['is_superadmin'] === false,
    'refresh_permissions preserves a valid SINGLE division scope'
);
auth_inactive_role_permission_assert_query_contract($refreshDb, 'refresh_permissions');

echo "PASS: Batch 7 P0-04B DB-free Auth_model smoke test\n";
