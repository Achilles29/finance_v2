<?php

define('BASEPATH', __DIR__);

class AtomicPeriodResult
{
    private array $row;
    public function __construct(array $row) { $this->row = $row; }
    public function row_array(): array { return $this->row; }
}

class AtomicPeriodDb
{
    public bool $db_debug = true;
    public bool $active = false;
    public string $status = 'OPEN';
    public bool $periodExists = true;
    public bool $transactionStatus = true;
    public bool $failExactReadTransaction = false;
    public int $rollbacks = 0;
    public array $newerPeriod = [];
    public array $queries = [];
    private array $where = [];

    public function table_exists(string $table): bool { return $table === 'inv_stock_period'; }
    public function trans_active(): bool { return $this->active; }
    public function trans_begin(): bool { $this->active = true; return true; }
    public function trans_status(): bool { return $this->transactionStatus; }
    public function trans_commit(): bool { $this->active = false; return true; }
    public function trans_rollback(): bool { $this->active = false; $this->rollbacks++; return true; }
    public function select($fields): self { return $this; }
    public function from($table): self { return $this; }
    public function where($field, $value): self { $this->where[$field] = $value; return $this; }
    public function limit($limit): self { return $this; }
    public function get(): AtomicPeriodResult
    {
        $month = (string)($this->where['period_month'] ?? date('Y-m-01'));
        $this->where = [];
        if (!$this->periodExists) {
            return new AtomicPeriodResult([]);
        }
        return new AtomicPeriodResult(['id' => 1, 'status' => $this->status, 'period_month' => $month]);
    }
    public function query(string $sql, array $binds = [])
    {
        $this->queries[] = ['sql' => $sql, 'binds' => $binds];
        if (strpos($sql, 'INSERT INTO inv_stock_period') !== false) {
            $this->periodExists = true;
            return true;
        }
        if (strpos($sql, 'period_month > ?') !== false) {
            return new AtomicPeriodResult($this->newerPeriod);
        }
        if (!$this->periodExists) {
            return new AtomicPeriodResult([]);
        }
        if ($this->failExactReadTransaction) {
            $this->transactionStatus = false;
        }
        return new AtomicPeriodResult(['id' => 1, 'status' => $this->status, 'period_month' => (string)($binds[1] ?? date('Y-m-01'))]);
    }
}

class AtomicPeriodCi
{
    public AtomicPeriodDb $db;
    public function __construct(AtomicPeriodDb $db) { $this->db = $db; }
}

$atomicPeriodCi = null;
function &get_instance() { global $atomicPeriodCi; return $atomicPeriodCi; }

require dirname(__DIR__, 2) . '/application/libraries/InventoryPeriodGuard.php';

$failures = [];
$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) { $failures[] = $label; }
};

$db = new AtomicPeriodDb();
$atomicPeriodCi = new AtomicPeriodCi($db);
$guard = new InventoryPeriodGuard();
$month = date('Y-m-01');
$check(!empty($guard->assertOpen('MATERIAL', $month)['ok']), 'non-transaction read primes cache');
$db->status = 'CLOSED';
$check(!empty($guard->assertOpen('MATERIAL', $month)['ok']), 'non-transaction read may reuse request cache');
$db->active = true;
$locked = $guard->assertOpen('MATERIAL', $month);
$check(empty($locked['ok']) && strpos($db->queries[0]['sql'], 'FOR UPDATE') !== false, 'active transaction bypasses cache and locks row');

$db->status = 'OPEN';
$db->queries = [];
$batch = $guard->lockActivePeriodsForWrite([
    ['stock_domain' => 'MATERIAL', 'event_date' => $month],
    ['stock_domain' => 'COMPONENT', 'event_date' => $month],
    ['stock_domain' => 'MATERIAL', 'event_date' => $month],
]);
$creationQueries = array_values(array_filter($db->queries, static function (array $query): bool {
    return strpos((string)($query['sql'] ?? ''), 'INSERT INTO inv_stock_period') !== false;
}));
$domains = array_map(static fn(array $query): string => (string)($query['binds'][0] ?? ''), $creationQueries);
$check(!empty($batch['ok']) && $domains === ['COMPONENT', 'MATERIAL'], 'batch lock dedupes and orders COMPONENT before MATERIAL');

$creationDb = new AtomicPeriodDb();
$creationDb->active = true;
$creationDb->periodExists = false;
$atomicPeriodCi = new AtomicPeriodCi($creationDb);
$creationGuard = new InventoryPeriodGuard();
$created = $creationGuard->lockActivePeriodsForWrite([
    ['stock_domain' => 'MATERIAL', 'event_date' => $month],
]);
$check(
    !empty($created['ok'])
        && count($creationDb->queries) === 2
        && strpos($creationDb->queries[0]['sql'], 'INSERT INTO inv_stock_period') !== false
        && strpos($creationDb->queries[0]['sql'], 'ON DUPLICATE KEY UPDATE id = id') !== false
        && strpos($creationDb->queries[1]['sql'], 'period_month = ?') !== false
        && strpos($creationDb->queries[1]['sql'], 'FOR UPDATE') !== false,
    'missing current period uses atomic upsert before exact lock/read'
);

$closedDb = new AtomicPeriodDb();
$closedDb->active = true;
$closedDb->status = 'CLOSED';
$atomicPeriodCi = new AtomicPeriodCi($closedDb);
$closedGuard = new InventoryPeriodGuard();
$closedCreation = $closedGuard->ensureActiveMonthOpen('MATERIAL', $month);
$check(
    empty($closedCreation['ok'])
        && ($closedCreation['code'] ?? '') === 'INVENTORY_PERIOD_CLOSED'
        && count($closedDb->queries) === 2
        && strpos($closedDb->queries[0]['sql'], 'ON DUPLICATE KEY UPDATE id = id') !== false
        && strpos($closedDb->queries[1]['sql'], 'FOR UPDATE') !== false,
    'atomic upsert preserves and validates existing closed status'
);

$failedDb = new AtomicPeriodDb();
$failedDb->periodExists = false;
$failedDb->failExactReadTransaction = true;
$atomicPeriodCi = new AtomicPeriodCi($failedDb);
$failedGuard = new InventoryPeriodGuard();
$failedCreation = $failedGuard->ensureActiveMonthOpen('MATERIAL', $month);
$check(
    empty($failedCreation['ok'])
        && ($failedCreation['code'] ?? '') === 'INVENTORY_PERIOD_LOCK_FAILED'
        && $failedDb->rollbacks === 1
        && count($failedDb->queries) === 2,
    'successful exact read cannot mask failed owner transaction'
);

$atomicPeriodCi = new AtomicPeriodCi($db);
$oldMonth = date('Y-m-01', strtotime($month . ' -1 month'));
$db->status = 'REOPENED';
$db->newerPeriod = [];
$db->queries = [];
$historical = $guard->ensureActiveMonthOpen('MATERIAL', $oldMonth);
$check(
    !empty($historical['ok'])
        && count($db->queries) === 2
        && strpos($db->queries[0]['sql'], 'period_month = ?') !== false
        && strpos($db->queries[0]['sql'], 'FOR UPDATE') !== false
        && strpos($db->queries[1]['sql'], 'period_month > ?') !== false
        && strpos($db->queries[1]['sql'], 'ORDER BY period_month ASC') !== false
        && strpos($db->queries[1]['sql'], 'FOR UPDATE') !== false
        && $db->queries[1]['binds'] === ['MATERIAL', $oldMonth],
    'transactional REOPENED backdate locks exact period before newer-period range'
);

$db->newerPeriod = ['id' => 2, 'status' => 'OPEN', 'period_month' => $month];
$db->queries = [];
$blockedHistorical = $guard->ensureActiveMonthOpen('MATERIAL', $oldMonth);
$check(
    empty($blockedHistorical['ok'])
        && ($blockedHistorical['code'] ?? '') === 'INVENTORY_BACKDATE_AFTER_ROLLOVER'
        && strpos($db->queries[1]['sql'] ?? '', 'FOR UPDATE') !== false,
    'transactional newer-period lock still rejects backdate after rollover'
);

$root = dirname(__DIR__, 2);
$guardSource = file_get_contents($root . '/application/libraries/InventoryPeriodGuard.php');
$ledgerSource = file_get_contents($root . '/application/libraries/InventoryLedger.php');
$valueSource = file_get_contents($root . '/application/libraries/InventoryValueReconciliationService.php');
$posSource = file_get_contents($root . '/application/libraries/PosOrderStockService.php');
$ensureOpenStart = strpos($guardSource, 'public function ensureOpen');
$ensureOpenEnd = strpos($guardSource, 'public function lockActivePeriodsForWrite', $ensureOpenStart);
$ensureOpenSource = substr($guardSource, $ensureOpenStart, $ensureOpenEnd - $ensureOpenStart);
$atomicUpsert = strpos($ensureOpenSource, 'INSERT INTO inv_stock_period');
$duplicateNoOp = strpos($ensureOpenSource, 'ON DUPLICATE KEY UPDATE id = id', $atomicUpsert);
$creationKeyLock = strpos($ensureOpenSource, '$result = $this->assertOpen', $duplicateNoOp);
$check(
    $atomicUpsert !== false
        && $duplicateNoOp !== false
        && $creationKeyLock !== false
        && $atomicUpsert < $duplicateNoOp
        && $duplicateNoOp < $creationKeyLock
        && strpos($ensureOpenSource, "\$db->insert('inv_stock_period'") === false
        && strpos($ensureOpenSource, '$retry') === false,
    'period creation source uses atomic upsert before exact status lock/read'
);
$ensureActiveStart = strpos($guardSource, 'public function ensureActiveMonthOpen');
$ensureActiveEnd = strpos($guardSource, 'public function closePeriod', $ensureActiveStart);
$ensureActiveSource = substr($guardSource, $ensureActiveStart, $ensureActiveEnd - $ensureActiveStart);
$currentMonthBranch = strpos($ensureActiveSource, 'if ($periodMonth === $activeMonth)');
$currentMonthUpsertDispatch = strpos($ensureActiveSource, 'return $this->ensureOpen', $currentMonthBranch);
$historicalExactLock = strpos($ensureActiveSource, '$check = $this->assertOpen', $currentMonthUpsertDispatch);
$check(
    $currentMonthBranch !== false
        && $currentMonthUpsertDispatch !== false
        && $historicalExactLock !== false
        && $currentMonthBranch < $currentMonthUpsertDispatch
        && $currentMonthUpsertDispatch < $historicalExactLock,
    'current month dispatches to atomic upsert before historical exact-lock path'
);
$check(stripos($guardSource, 'GET_LOCK(') === false, 'period barrier does not use named locks');
$check(substr_count($guardSource, "->where('status', 'CLOSING')") === 1 && strpos($guardSource, "->where_in('status', ['OPEN', 'REOPENED'])") !== false, 'begin/close use status CAS predicates');
$check(strpos($ledgerSource, 'if (!$manageTransaction && !$this->ci->db->trans_active())') < strpos($ledgerSource, '$balanceResult ='), 'ledger requires caller transaction before DML');
$check(strpos($ledgerSource, 'lockActivePeriodsForWrite') < strpos($ledgerSource, '$balanceResult ='), 'ledger final period lock precedes balance DML');
$check(substr_count($valueSource, 'lockActivePeriodsForWrite') >= 2, 'value post and void perform transactional period locks');
$check(strpos($posSource, 'lock_commit_lines_for_update($commitId);') < strpos($posSource, 'prelock_snapshot_periods($snapshot'), 'POS locks commit lines before period prelock');

if ($failures) { exit(1); }
echo 'Inventory period atomic barrier smoke passed.' . PHP_EOL;
