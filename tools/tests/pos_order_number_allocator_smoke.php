<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
class CI_Model { public $db; }
require $root . '/application/models/Pos_model.php';

final class OrderNumberResult
{
    public function __construct(private array $row) {}
    public function row_array(): array { return $this->row; }
}

final class OrderNumberDb
{
    public array $queries = [];
    public int $lastSequence = 0;
    public int $lockGranted = 1;

    public function query(string $sql, array $bindings = []): OrderNumberResult
    {
        $this->queries[] = [$sql, $bindings];
        if (str_contains($sql, 'GET_LOCK')) return new OrderNumberResult(['acquired' => $this->lockGranted]);
        if (str_contains($sql, 'RELEASE_LOCK')) return new OrderNumberResult(['released' => 1]);
        if (str_contains($sql, 'last_sequence')) return new OrderNumberResult(['last_sequence' => $this->lastSequence]);
        throw new RuntimeException('Unexpected order number query.');
    }
}

function check_order_number(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'PASS: ' . $message . PHP_EOL;
}

$db = new OrderNumberDb();
$model = (new ReflectionClass(Pos_model::class))->newInstanceWithoutConstructor();
$model->db = $db;
$lock = $model->acquire_pos_order_no_lock('20261003');
check_order_number($lock === 'finance:pos_order:20261003', 'one lock key for every outlet on the same day');
$db->lastSequence = 9999;
check_order_number($model->generate_pos_order_no('2026-10-03 23:59:59') === 'POS-20261003-10000', 'numeric sequence continues after 9999');
$db->lastSequence = 0;
check_order_number($model->generate_pos_order_no('2026-10-04 00:00:00') === 'POS-20261004-0001', 'new date starts at one');
$select = $db->queries[1];
check_order_number(str_contains($select[0], 'FOR UPDATE') && str_contains($select[0], 'last_sequence')
    && $select[1] === ['POS-20261003-%', '^POS-20261003-[0-9]+$'], 'numeric locking read is limited to that date');
$model->release_pos_order_no_lock($lock);
check_order_number($db->queries[3][1] === [$lock], 'the acquired lock is released by name');
$db->lockGranted = 0;
try {
    $model->acquire_pos_order_no_lock('20261003');
    throw new RuntimeException('Busy lock must be rejected.');
} catch (RuntimeException $error) {
    check_order_number(str_contains($error->getMessage(), 'Coba simpan lagi'), 'busy lock fails closed');
}
try {
    $model->acquire_pos_order_no_lock('bad-date');
    throw new RuntimeException('Invalid date must be rejected.');
} catch (InvalidArgumentException $error) {
    check_order_number(true, 'invalid lock date rejected');
}

$db->lockGranted = 1;
$paymentLock = $model->acquire_pos_payment_no_lock('20261003');
check_order_number($paymentLock === 'finance:pos_payment:PAY:20261003', 'cashier payments share one daily lock across outlets');
$db->lastSequence = 9999;
$paymentNumber = new ReflectionMethod(Pos_model::class, 'generate_pos_payment_no');
$paymentNumber->setAccessible(true);
check_order_number($paymentNumber->invoke($model, 'FINAL', '2026-10-03 10:00:00') === 'PAY-20261003-10000', 'payment number continues after 9999');
$model->release_pos_payment_no_lock($paymentLock);

$modelSource = file_get_contents($root . '/application/models/Pos_model.php');
$paymentSave = substr($modelSource, strpos($modelSource, 'public function save_cashier_payment('),
    strpos($modelSource, 'private function is_cashier_payment_allowed_status(') - strpos($modelSource, 'public function save_cashier_payment('));
check_order_number(str_contains($paymentSave, 'acquire_pos_payment_no_lock')
    && str_contains($paymentSave, 'release_pos_payment_no_lock')
    && str_contains($paymentSave, 'finally'), 'payment writer retains number lock until commit or rollback');
$reservationSource = file_get_contents($root . '/application/models/Pos_reservation_model.php');
$save = substr($modelSource, strpos($modelSource, 'public function save_order_draft('),
    strpos($modelSource, 'private function missing_recipe_stock_commit_warning(') - strpos($modelSource, 'public function save_order_draft('));
$verify = substr($reservationSource, strpos($reservationSource, 'public function prepare_verification('),
    strpos($reservationSource, 'public function complete_verification(') - strpos($reservationSource, 'public function prepare_verification('));
foreach ([$save, $verify] as $index => $method) {
    check_order_number(str_contains($method, 'acquire_pos_order_no_lock')
        && str_contains($method, 'generate_pos_order_no')
        && str_contains($method, 'release_pos_order_no_lock')
        && str_contains($method, 'finally'), ($index === 0 ? 'cashier' : 'reservation') . ' shares lock and releases on all paths');
}
