<?php
declare(strict_types=1);

// Real controller validation with synthetic monthly rows, never a live DB.
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
class MY_Controller { public $db; }
require APPPATH . 'controllers/Inventory_division.php';

final class ReconSnapshotDb
{
    public bool $db_debug = true;
    public array $filters = [];
    public int $reads = 0;
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function table_exists($table): bool { $this->reads++; return $table === 'inv_division_monthly_stock'; }
    public function select(...$args): self { return $this; }
    public function from($table): self {
        if ($table !== 'inv_division_monthly_stock') throw new RuntimeException('Unexpected table');
        $this->filters = [];
        return $this;
    }
    public function where($field, $value): self { $this->filters[$field] = $value; return $this; }
    public function order_by(...$args): self { return $this; }
    public function limit(...$args): self { return $this; }
    public function get(): object {
        $this->reads++;
        $found = [];
        foreach ($this->rows as $row) {
            foreach ($this->filters as $field => $value) if (($row[$field] ?? null) != $value) continue 2;
            $found = $row;
            break;
        }
        return new class($found) {
            private array $row;
            public function __construct(array $row) { $this->row = $row; }
            public function row_array(): array { return $this->row; }
        };
    }
}

$profile = [
    'month_key' => '2026-09-01', 'division_id' => 3, 'item_id' => 196,
    'material_id' => 108, 'buy_uom_id' => 6, 'content_uom_id' => 9,
    'identity_key' => 'fixture-profile', 'profile_key' => 'fixture-profile',
    'avg_cost_per_content' => 20,
];
$rows = [
    array_merge($profile, ['id' => 1, 'destination_type' => 'KITCHEN', 'closing_qty_content' => 2906.6667]),
    array_merge($profile, ['id' => 2, 'destination_type' => 'KITCHEN_EVENT', 'closing_qty_content' => 1500]),
];
$controller = (new ReflectionClass(Inventory_division::class))->newInstanceWithoutConstructor();
$controller->db = new ReconSnapshotDb($rows);
$snapshot = new ReflectionMethod($controller, 'current_material_recon_snapshot');
$snapshot->setAccessible(true);
$checks = 0;
function reconCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
foreach (['', 'ALL', 'EVENT', 'REGULER', ' event '] as $destination) {
    $before = $controller->db->reads;
    $result = $snapshot->invoke($controller, '2026-09-25', 3, $destination, 'fixture-profile', 9);
    reconCheck(!$result['ok'] && str_contains($result['message'], 'Tujuan stok'), 'Aggregate filter rejected clearly');
    reconCheck($controller->db->reads === $before, 'Aggregate filter never reads stock or falls back');
}
foreach (['KITCHEN' => 2906.6667, 'KITCHEN_EVENT' => 1500.0, ' kitchen_event ' => 1500.0] as $destination => $quantity) {
    $result = $snapshot->invoke($controller, '2026-09-25', 3, $destination, 'fixture-profile', 9);
    reconCheck($result['ok'] && $result['system_qty_content'] === $quantity, 'Exact destination balance resolved');
    reconCheck($controller->db->db_debug === true, 'Database debug setting restored');
}
foreach ([['2026-10-01', 3, 'KITCHEN_EVENT', 'fixture-profile', 9],
    ['2026-09-25', 2, 'KITCHEN_EVENT', 'fixture-profile', 9],
    ['2026-09-25', 3, 'BAR_EVENT', 'fixture-profile', 9],
    ['2026-09-25', 3, 'KITCHEN_EVENT', 'different-profile', 9],
    ['2026-09-25', 3, 'KITCHEN_EVENT', 'fixture-profile', 11]] as $args) {
    reconCheck(!$snapshot->invokeArgs($controller, $args)['ok'], 'No fallback across month/division/destination/profile/UOM');
}
echo "PASS: $checks snapshot checks; no database, stock, or network writes.\n";
