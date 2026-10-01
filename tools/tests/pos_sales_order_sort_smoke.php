<?php
declare(strict_types=1);
// Execute the real row builder, capture ordering/filters, then apply its ORDER BY
// and pagination to synthetic SQLite records. Joins/financial totals are NOT
// executed here; no CI bootstrap or connection to the application database.
$root = dirname(__DIR__, 2);
define('BASEPATH', $root.'/system/');
class CI_Model { public $db; }
require $root.'/application/models/Pos_report_model.php';
final class SalesOrderSortFixture
{
    public PDO $pdo;
    public array $sort = [], $calls = [];
    private int $limit = 25, $offset = 0;
    private array $rows = [];
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function field_exists($field, $table): bool { return false; }
    public function table_exists($table): bool { return false; }
    public function __call($method, $args): self
    {
        if (!in_array($method, ['from','join','select','where','where_not_in','group_start','group_end','like','or_like'], true)) {
            throw new RuntimeException('Unexpected DB call: '.$method);
        }
        $this->calls[] = [$method,$args]; return $this;
    }
    public function order_by($expression, $direction, $escape = null): self
    {
        $this->sort[] = [$expression,$direction]; $this->calls[] = ['order_by',[$expression,$direction]]; return $this;
    }
    public function limit($limit, $offset): self
    {
        $this->limit = $limit; $this->offset = $offset; $this->calls[] = ['limit',[$limit,$offset]]; return $this;
    }
    public function get(): self
    {
        $order = implode(', ', array_map(static fn(array $pair): string => implode(' ', $pair), $this->sort));
        $this->rows = $this->pdo->query('SELECT o.* FROM pos_order o ORDER BY '.$order.' LIMIT '.$this->limit.' OFFSET '.$this->offset)->fetchAll(PDO::FETCH_ASSOC);
        return $this;
    }
    public function result_array(): array { return $this->rows; }
}
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    $checks++;
};
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE pos_order (id INTEGER PRIMARY KEY, ordered_at TEXT, confirmed_at TEXT, paid_at TEXT)');
$insert = $pdo->prepare('INSERT INTO pos_order VALUES(?,?,?,?)');
foreach ([
    [1,'2026-09-15 09:00:00','2026-09-15 09:01:00','2026-09-16 14:00:00'], // older order paid last
    [2,'2026-09-15 10:00:00','2026-09-15 10:01:00','2026-09-15 10:30:00'],
    [3,'2026-09-15 11:00:00','2026-09-15 11:01:00','2026-09-15 11:10:00'],
    [4,'2026-09-15 11:00:00','2026-09-15 11:01:00',null], // same order time, larger ID
    [90,'2026-09-14 23:00:00','2026-09-16 15:00:00','2026-09-16 16:00:00'], // import ID is not chronology
    [91,null,'2026-09-17 16:00:00','2026-09-17 16:00:00'], // legacy missing order time last
    [5,'2026-09-15 11:00:00.123456',null,null], // timestamp precision
] as $row) $insert->execute($row);
$method = new ReflectionMethod(Pos_report_model::class, 'sales_summary_rows');
$method->setAccessible(true);
$run = static function (int $limit = 25, int $offset = 0, array $filters = []) use ($pdo, $method): array {
    $model = new Pos_report_model(); $model->db = new SalesOrderSortFixture($pdo);
    $rows = $method->invoke($model, $filters, $limit, $offset);
    return [$rows,$model->db];
};
[$rows,$db] = $run();
$ids = array_column($rows,'id');
$check($db->sort === [['o.ordered_at','DESC'],['o.id','DESC']], 'exact order timestamp + ID tie-break');
$check($ids === [5,4,3,2,1,90,91], 'newest orders first, not payment/confirmation/import ID');
$check($rows[1]['paid_at'] === null, 'no payment does not change order priority');
$check($rows[6]['ordered_at'] === null, 'missing order timestamp does not fall back to payment');
$pages = [];
for ($offset=0; $offset<7; $offset+=2) $pages = array_merge($pages,array_column($run(2,$offset)[0],'id'));
$check($pages === $ids && count(array_unique($pages)) === 7, 'stable page boundaries with identical timestamps');
$check(array_column($run(2,2)[0],'id') === [3,2], 'offset applied after sorting globally');
$check($run(2,20)[0] === [], 'empty page');
$pdo->exec("UPDATE pos_order SET paid_at='2026-09-20 23:59:59',confirmed_at='2026-09-20 23:59:58' WHERE id=2");
$check(array_column($run()[0],'id') === $ids, 'payment and confirmation changes do not reorder');
[$rows,$db] = $run(2,2,['date_from'=>'2026-09-15','date_to'=>'2026-09-16','outlet_id'=>7,'status'=>'PAID','order_scope'=>'REGULER','payment_method_id'=>4]);
$check(in_array(['where',['DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) >=','2026-09-15']],$db->calls,true), 'date basis unchanged: from');
$check(in_array(['where',['DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) <=','2026-09-16']],$db->calls,true), 'date basis unchanged: to');
$check(in_array(['where',['o.outlet_id',7]],$db->calls,true), 'outlet filter retained');
$check(in_array(['where',['o.status','PAID']],$db->calls,true), 'status filter retained');
$check(in_array(['where',['o.order_scope','REGULER']],$db->calls,true), 'scope filter retained');
$check(in_array(['where_not_in',['o.status',['DRAFT','PENDING','VOID']]],$db->calls,true), 'excluded order statuses unchanged');
$check(in_array(['where',["FIND_IN_SET(4, COALESCE(pm.method_ids, '')) > 0",null,false]],$db->calls,true), 'payment method filter retained');
$calls = array_column($db->calls,0);
$check(array_search('order_by',$calls,true)<array_search('limit',$calls,true), 'server sort before pagination');
$view = file_get_contents($root.'/application/views/pos/report_sales_index.php');
$check(str_contains($view,'Diurutkan berdasarkan waktu order terbaru, bukan waktu pembayaran.'), 'sort explained in UI');
$check(substr_count($view,'foreach ($rows as $row)') === 2, 'desktop and mobile consume same ordered dataset');
echo "POS sales order sort: {$checks} PASS (synthetic records; no live database).\n";
