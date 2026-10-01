<?php

define('BASEPATH', __DIR__);

class InventoryPeriodGuardFakeResult
{
    private array $row;

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function row_array(): array
    {
        return $this->row;
    }
}

class InventoryPeriodGuardFakeDb
{
    public bool $db_debug = true;
    public array $periods;
    private bool $periodTableExists;
    private array $where = [];

    public function __construct(array $periods, bool $periodTableExists = true)
    {
        $this->periods = $periods;
        $this->periodTableExists = $periodTableExists;
    }

    public function table_exists(string $table): bool
    {
        return $table === 'inv_stock_period' && $this->periodTableExists;
    }

    public function select($fields): self
    {
        return $this;
    }

    public function from($table): self
    {
        return $this;
    }

    public function where($field, $value): self
    {
        $this->where[] = [$field, $value];
        return $this;
    }

    public function order_by($field, $direction = ''): self
    {
        return $this;
    }

    public function limit($limit): self
    {
        return $this;
    }

    public function get(): InventoryPeriodGuardFakeResult
    {
        $rows = array_values(array_filter($this->periods, function (array $row): bool {
            return $this->matches($row);
        }));
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)$left['period_month'], (string)$right['period_month']);
        });
        $this->where = [];
        return new InventoryPeriodGuardFakeResult($rows[0] ?? []);
    }

    public function update($table, array $data): bool
    {
        foreach ($this->periods as &$row) {
            if ($this->matches($row)) {
                $row = array_merge($row, $data);
            }
        }
        unset($row);
        $this->where = [];
        return true;
    }

    public function insert($table, array $data): bool
    {
        $data['id'] = count($this->periods) + 1;
        $this->periods[] = $data;
        return true;
    }

    public function insert_id(): int
    {
        return count($this->periods);
    }

    public function affected_rows(): int
    {
        return 1;
    }

    private function matches(array $row): bool
    {
        foreach ($this->where as [$field, $value]) {
            if (substr($field, -2) === ' >') {
                $key = substr($field, 0, -2);
                if (!((string)($row[$key] ?? '') > (string)$value)) {
                    return false;
                }
                continue;
            }
            if ((string)($row[$field] ?? '') !== (string)$value) {
                return false;
            }
        }
        return true;
    }
}

class InventoryPeriodGuardFakeCi
{
    public InventoryPeriodGuardFakeDb $db;

    public function __construct(InventoryPeriodGuardFakeDb $db)
    {
        $this->db = $db;
    }
}

class MY_Controller
{
    public function __construct()
    {
    }
}

$inventoryPeriodGuardFakeCi = null;

function &get_instance()
{
    global $inventoryPeriodGuardFakeCi;
    return $inventoryPeriodGuardFakeCi;
}

require dirname(__DIR__, 2) . '/application/libraries/InventoryPeriodGuard.php';
require dirname(__DIR__, 2) . '/application/libraries/PosOrderStockService.php';
require dirname(__DIR__, 2) . '/application/controllers/Dashboard.php';

function inventory_period_guard_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$activeMonth = date('Y-m-01');
$oldMonth = date('Y-m-01', strtotime($activeMonth . ' -1 month'));
$futureMonth = date('Y-m-01', strtotime($activeMonth . ' +1 month'));
$periods = [
    ['id' => 1, 'stock_domain' => 'MATERIAL', 'period_month' => $oldMonth, 'status' => 'OPEN'],
    ['id' => 2, 'stock_domain' => 'MATERIAL', 'period_month' => $activeMonth, 'status' => 'OPEN'],
];

$inventoryPeriodGuardFakeCi = new InventoryPeriodGuardFakeCi(new InventoryPeriodGuardFakeDb([], false));
$guard = new InventoryPeriodGuard();
$result = $guard->ensureActiveMonthOpen('MATERIAL', $activeMonth);
inventory_period_guard_expect(
    empty($result['ok']) && ($result['code'] ?? '') === 'INVENTORY_PERIOD_SCHEMA_NOT_READY',
    'missing period table fails closed with a stable code'
);

$inventoryPeriodGuardFakeCi = new InventoryPeriodGuardFakeCi(new InventoryPeriodGuardFakeDb($periods));
$guard = new InventoryPeriodGuard();
$result = $guard->assertOpen('MATERIAL', '2026-02-30');
inventory_period_guard_expect(
    empty($result['ok']),
    'impossible calendar dates are rejected'
);

$result = $guard->assertOpen('MATERIAL', '09/04/2026');
inventory_period_guard_expect(
    empty($result['ok']),
    'ambiguous date formats are rejected'
);

$inventoryPeriodGuardFakeCi = new InventoryPeriodGuardFakeCi(new InventoryPeriodGuardFakeDb($periods));
$guard = new InventoryPeriodGuard();
$result = $guard->ensureActiveMonthOpen('MATERIAL', $oldMonth);
inventory_period_guard_expect(
    empty($result['ok']) && ($result['code'] ?? '') === 'INVENTORY_BACKDATE_AFTER_ROLLOVER',
    'backdate is rejected after a newer period exists'
);

$result = $guard->ensureActiveMonthOpen('MATERIAL', $activeMonth);
inventory_period_guard_expect(
    !empty($result['ok']) && (int)($result['period_id'] ?? 0) === 2,
    'active-month writers remain allowed'
);

$result = $guard->ensureActiveMonthOpen('MATERIAL', $futureMonth);
inventory_period_guard_expect(
    empty($result['ok']) && ($result['code'] ?? '') === 'INVENTORY_FUTURE_PERIOD_WRITE',
    'future stock events are rejected'
);

$closedPeriods = $periods;
$closedPeriods[1]['status'] = 'CLOSED';
$inventoryPeriodGuardFakeCi = new InventoryPeriodGuardFakeCi(new InventoryPeriodGuardFakeDb($closedPeriods));
$guard = new InventoryPeriodGuard();
$result = $guard->assertOpen('MATERIAL', $activeMonth);
inventory_period_guard_expect(
    empty($result['ok']) && ($result['code'] ?? '') === 'INVENTORY_PERIOD_CLOSED',
    'closed periods remain rejected'
);

$inventoryPeriodGuardFakeCi = new InventoryPeriodGuardFakeCi(new InventoryPeriodGuardFakeDb($periods));
$guard = new InventoryPeriodGuard();
$result = $guard->reopenPeriod('MATERIAL', $oldMonth, 1, 'manual reopen');
inventory_period_guard_expect(
    empty($result['ok']) && ($result['code'] ?? '') === 'INVENTORY_REOPEN_AFTER_ROLLOVER_BLOCKED',
    'manual reopen is rejected after rollover'
);

$inventoryPeriodGuardFakeCi = new InventoryPeriodGuardFakeCi(new InventoryPeriodGuardFakeDb($periods));
$guard = new InventoryPeriodGuard();
$result = $guard->reopenPeriod('MATERIAL', $oldMonth, 1, 'cutoff rollback', true);
inventory_period_guard_expect(
    !empty($result['ok']) && ($result['status'] ?? '') === 'REOPENED',
    'internal cutoff rollback can restore a period after rollover'
);

$serviceReflection = new ReflectionClass(PosOrderStockService::class);
$service = $serviceReflection->newInstanceWithoutConstructor();
$method = $serviceReflection->getMethod('resolve_reversal_movement_date');
$method->setAccessible(true);
inventory_period_guard_expect(
    $method->invoke($service, ['document_date' => '2026-09-01 22:00:00']) === '2026-09-01',
    'reversal uses its own document date'
);
inventory_period_guard_expect(
    $method->invoke($service, []) === date('Y-m-d'),
    'reversal defaults to the processing date'
);

$dashboardReflection = new ReflectionClass(Dashboard::class);
$dashboard = $dashboardReflection->newInstanceWithoutConstructor();
$gapMethod = $dashboardReflection->getMethod('dashboard_material_reconcile_gap');
$gapMethod->setAccessible(true);
inventory_period_guard_expect(
    abs((float)$gapMethod->invoke($dashboard, ['lot_vs_balance_delta' => -609.0]) + 609.0) < 0.0001,
    'dashboard reads the material-level lot quantity gap'
);
inventory_period_guard_expect(
    abs((float)$gapMethod->invoke($dashboard, [
        'lot_vs_balance_delta' => 0.0,
        'lot_profile_breakdown' => [
            ['delta' => 13.0],
            ['delta' => -21.0],
        ],
    ]) + 21.0) < 0.0001,
    'dashboard uses the largest per-profile quantity gap'
);
