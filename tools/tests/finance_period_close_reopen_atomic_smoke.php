<?php
declare(strict_types=1);

// DB-free behavioral test. It executes the real model against a transaction
// double; it never reads or changes a staging period.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
class CI_Model { public $db; }
require dirname(__DIR__, 2) . '/application/models/Finance_report_model.php';

final class FinancePeriodReopenResult
{
    public function __construct(private ?array $row) {}
    public function row_array(): array { return $this->row ?? []; }
}

final class FinancePeriodReopenDb
{
    public bool $tableExists = true;
    public bool $beginResult = true, $queryResult = true, $updateResult = true;
    public bool $transactionHealthy = true, $commitResult = true;
    public int $updateAffected = 1;
    public ?array $row;
    public array $calls = [], $where = [], $pending = [];

    public function __construct(?array $row = null) { $this->row = $row; }
    public function table_exists($table): bool { $this->calls[] = ['table_exists', $table]; return $this->tableExists; }
    public function trans_begin(): bool { $this->calls[] = ['begin']; return $this->beginResult; }
    public function trans_rollback(): bool { $this->calls[] = ['rollback']; $this->pending = []; return true; }
    public function trans_commit(): bool {
        $this->calls[] = ['commit'];
        if ($this->commitResult && $this->pending !== [] && $this->row !== null) $this->row = array_merge($this->row, $this->pending);
        return $this->commitResult;
    }
    public function trans_status(): bool { $this->calls[] = ['status']; return $this->transactionHealthy; }
    public function query($sql, $bindings = []) {
        $this->calls[] = ['query', $sql, $bindings];
        return $this->queryResult ? new FinancePeriodReopenResult($this->row) : false;
    }
    public function where($field, $value = null, $escape = true): self { $this->where[] = [$field, $value, $escape]; return $this; }
    public function update($table, $payload): bool { $this->calls[] = ['update', $table, $payload]; if ($this->updateResult && $this->updateAffected === 1) $this->pending = $payload; return $this->updateResult; }
    public function affected_rows(): int { $this->calls[] = ['affected']; return $this->updateAffected; }
}

function financePeriodReopenModel(FinancePeriodReopenDb $db): Finance_report_model
{
    $model = (new ReflectionClass(Finance_report_model::class))->newInstanceWithoutConstructor();
    $model->db = $db;
    return $model;
}
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$closed = ['id' => 12, 'status' => 'CLOSED', 'notes' => 'Close official'];
$callNames = static function (FinancePeriodReopenDb $db): array { return array_column($db->calls, 0); };

foreach ([0, -12] as $id) {
    $db = new FinancePeriodReopenDb($closed);
    $result = financePeriodReopenModel($db)->reopen_period($id, 7);
    $check($result['ok'] === false && $callNames($db) === [], 'invalid ID fails before database or transaction');
}
$db = new FinancePeriodReopenDb($closed); $db->tableExists = false;
$result = financePeriodReopenModel($db)->reopen_period(12, 7);
$check($result['ok'] === false && $callNames($db) === ['table_exists'], 'missing foundation fails before transaction');
$db = new FinancePeriodReopenDb($closed); $db->beginResult = false;
$result = financePeriodReopenModel($db)->reopen_period(12, 7);
$check($result['ok'] === false && $callNames($db) === ['table_exists', 'begin'], 'failed transaction begin reaches no lock or writer');

foreach ([
    ['lock query fails', null, false, true, true, true, 1, 'Gagal mengunci'],
    ['missing row', null, true, true, true, true, 1, 'tidak ditemukan'],
    ['OPEN row', ['id' => 12, 'status' => 'OPEN'], true, true, true, true, 1, 'Hanya period'],
    ['already reopened', ['id' => 12, 'status' => 'REOPENED'], true, true, true, true, 1, 'Hanya period'],
    ['VOID row', ['id' => 12, 'status' => 'VOID'], true, true, true, true, 1, 'Hanya period'],
    ['writer false', $closed, true, false, true, true, 1, 'Gagal memperbarui'],
    ['writer affects zero', $closed, true, true, true, true, 0, 'Gagal memperbarui'],
    ['transaction unhealthy', $closed, true, true, false, true, 1, 'Gagal memperbarui'],
    ['commit fails', $closed, true, true, true, false, 1, 'Gagal menyimpan'],
] as [$label, $row, $queryOk, $updateOk, $healthy, $commitOk, $affected, $message]) {
    $db = new FinancePeriodReopenDb($row);
    $before = $db->row;
    $db->queryResult = $queryOk; $db->updateResult = $updateOk; $db->transactionHealthy = $healthy; $db->commitResult = $commitOk; $db->updateAffected = $affected;
    $result = financePeriodReopenModel($db)->reopen_period(12, 7);
    $names = $callNames($db);
    $check($result['ok'] === false && str_contains($result['message'], $message), $label . ' has a specific failure result');
    $check(in_array('rollback', $names, true), $label . ' rolls back');
    $check($db->row === $before, $label . ' leaves durable row unchanged');
    if (in_array($label, ['lock query fails', 'missing row', 'OPEN row', 'already reopened', 'VOID row'], true)) $check(!in_array('update', $names, true), $label . ' never reaches writer');
    if ($label !== 'commit fails') $check(!in_array('commit', $names, true), $label . ' never commits failure');
}

$db = new FinancePeriodReopenDb($closed);
$result = financePeriodReopenModel($db)->reopen_period(12, 7);
$check($result['ok'] === true, 'closed period reopens successfully');
$query = array_values(array_filter($db->calls, static fn(array $call): bool => $call[0] === 'query'))[0] ?? [];
$check(($query[1] ?? '') === 'SELECT * FROM fin_period_close WHERE id = ? LIMIT 1 FOR UPDATE' && ($query[2] ?? []) === [12], 'row is locked with bound canonical ID');
$check($db->where === [['id', 12, true], ['status', 'CLOSED', true]], 'writer repeats ID and CLOSED precondition');
$update = array_values(array_filter($db->calls, static fn(array $call): bool => $call[0] === 'update'))[0] ?? [];
$payload = $update[2] ?? [];
$check(($update[1] ?? '') === 'fin_period_close' && ($payload['status'] ?? '') === 'REOPENED', 'only official period table moves to REOPENED');
$check(($payload['reopened_by'] ?? null) === 7 && preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', (string)($payload['reopened_at'] ?? '')) === 1, 'actor and timestamp are written');
$check(($payload['updated_at'] ?? '') === ($payload['reopened_at'] ?? null), 'updated timestamp matches reopen timestamp');
$check(str_contains((string)($payload['notes'] ?? ''), 'Close official | Reopened '), 'existing notes keep a bounded reopen audit suffix');
$check($db->row !== null && ($db->row['status'] ?? '') === 'REOPENED' && $callNames($db) === ['table_exists', 'begin', 'query', 'update', 'affected', 'status', 'commit'], 'one successful transaction commits exactly once');

// A second request after the first commit sees the changed status and is rejected.
$second = financePeriodReopenModel($db)->reopen_period(12, 8);
$check($second['ok'] === false && str_contains($second['message'], 'Hanya period'), 'second reopen cannot overwrite first approval');
$check(($db->row['reopened_by'] ?? null) === 7, 'first actor audit remains immutable to second request');

$db = new FinancePeriodReopenDb($closed);
$result = financePeriodReopenModel($db)->reopen_period(12, 0);
$payload = array_values(array_filter($db->calls, static fn(array $call): bool => $call[0] === 'update'))[0][2] ?? [];
$check($result['ok'] === true && array_key_exists('reopened_by', $payload) && $payload['reopened_by'] === null, 'missing actor is explicitly stored as null without false identity');
echo 'PASS finance-period-close-reopen-atomic checks=' . $checks . PHP_EOL;
