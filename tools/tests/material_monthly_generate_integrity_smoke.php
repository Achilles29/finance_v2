<?php
declare(strict_types=1);
// Exercises real cut-off checks and SQL compilation without a database connection.
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
function log_message($level, $message): void {}
require BASEPATH . 'core/Common.php';
require BASEPATH . 'database/DB_driver.php';
require BASEPATH . 'database/DB_query_builder.php';
class CI_Model { public $db; }
require dirname(__DIR__, 2) . '/application/models/Purchase_model.php';

final class GenerateIntegrityFixtureDb extends CI_DB_query_builder
{
    protected $_escape_char = '`';
    public array $tables = [];
    public array $compiledQueries = [];
    public function table_exists($table_name) { return array_key_exists($table_name, $this->tables); }
    public function field_exists($field_name, $table_name) { return true; }
    public function get($table = '', $limit = null, $offset = null) {
        $sql = $this->get_compiled_select($table);
        $this->compiledQueries[] = $sql;
        preg_match('/FROM `([^`]+)`/', $sql, $m);
        $rows = $this->tables[$m[1] ?? ''] ?? [];
        if (preg_match('/LIMIT (\d+)/', $sql, $limitMatch)) { $rows = array_slice($rows, 0, (int)$limitMatch[1]); }
        return new class($rows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function result_array(): array { return $this->rows; }
        };
    }
}
$model = new Purchase_model();
$db = new GenerateIntegrityFixtureDb([]);
$model->db = $db;
$checks = 0;
function verify(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
}
function callPrivate($model, string $method, ...$args) {
    $reflection = new ReflectionMethod($model, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($model, $args);
}
$identity = ['division_id'=>2,'destination_type'=>'BAR','item_id'=>149,'material_id'=>207,
    'buy_uom_id'=>3,'content_uom_id'=>11,'profile_key'=>'fixture-exact-profile'];
$monthly = $identity + ['id'=>1,'profile_name'=>'Synthetic Powder','opening_qty_buy'=>1.0,
    'opening_qty_content'=>1000.0,'closing_qty_buy'=>1.0,'closing_qty_content'=>1000.0];
$opening = $identity + ['snapshot_month'=>'2026-09-01','opening_qty_buy'=>1.0,'opening_qty_content'=>1000.0];
$oldMovement = $identity + ['id'=>100,'movement_date'=>'2026-06-30','qty_buy_after'=>0.481,
    'qty_content_after'=>481.0,'movement_type'=>'VARIANCE_OUT','ref_table'=>'inv_stock_adjustment','ref_id'=>1];
$db->tables = ['inv_division_monthly_stock'=>[$monthly], 'inv_stock_movement_log'=>[$oldMovement],
    'inv_division_stock_opening_snapshot'=>[$opening], 'inv_material_fifo_lot'=>[]];
$run = static fn()=>callPrivate($model,'divisionMaterialMovementIntegritySummary','2026-09-30',2,'BAR');
verify($run()['ok'], 'exact September opening supersedes a June running balance');
verify(str_contains(implode("\n",$db->compiledQueries), "`o`.`snapshot_month` = '2026-09-01'"), 'opening query constrained to source month');
verify(str_contains(implode("\n",$db->compiledQueries), '`o`.`division_id` = 2'), 'opening query constrained to division');
$db->tables['inv_stock_movement_log'] = [];
verify($run()['ok'], 'documented opening with no movement is valid');
$db->tables['inv_division_stock_opening_snapshot'] = [];
verify(!$run()['ok'], 'nonzero monthly stock without movement or opening is rejected');
$db->tables['inv_stock_movement_log'] = [$oldMovement];
verify(!$run()['ok'], 'old differing movement cannot be silently accepted');
$db->tables['inv_division_stock_opening_snapshot'] = [$opening,$opening];
verify(!$run()['ok'], 'duplicate opening anchors are rejected');
foreach (['division_id'=>3,'destination_type'=>'BAR_EVENT','item_id'=>150,'material_id'=>208,
    'buy_uom_id'=>4,'content_uom_id'=>9,'profile_key'=>'other-profile'] as $field=>$value) {
    $db->tables['inv_division_stock_opening_snapshot'] = [array_replace($opening,[$field=>$value])];
    verify(!$run()['ok'], 'opening never leaks across ' . $field);
}
$db->tables['inv_division_stock_opening_snapshot'] = [array_replace($opening,['opening_qty_content'=>999])];
verify(!$run()['ok'], 'opening document must agree with monthly amount');
$db->tables['inv_division_stock_opening_snapshot'] = [$opening];
$db->tables['inv_division_monthly_stock'] = [array_replace($monthly,['closing_qty_content'=>999])];
verify(!$run()['ok'], 'unrecorded monthly quantity change remains blocked');
$db->tables['inv_division_monthly_stock'] = [array_replace($monthly,['closing_qty_buy'=>2])];
verify(!$run()['ok'], 'buy-unit quantity change remains blocked');
$db->tables['inv_division_monthly_stock'] = [$monthly];
$currentMovement = array_replace($oldMovement,['id'=>200,'movement_date'=>'2026-09-20','qty_content_after'=>900]);
$db->tables['inv_stock_movement_log'] = [$oldMovement,$currentMovement];
verify(!$run()['ok'], 'current-month movement disagreement cannot be hidden by opening');
$db->tables['inv_stock_movement_log'][] = array_replace($oldMovement,['id'=>300]);
verify(!$run()['ok'], 'a later-inserted old movement cannot hide a current-month mismatch');
$db->tables['inv_stock_movement_log'] = [array_replace($currentMovement,['qty_content_after'=>1000])];
verify($run()['ok'], 'correct current-month movement still matches');
$db->tables['inv_division_monthly_stock'] = [];
for ($i=1;$i<=5101;$i++) {
    $db->tables['inv_division_monthly_stock'][] = array_replace($monthly,['id'=>$i,'profile_key'=>'profile-'.$i]);
}
$all = callPrivate($model,'list_division_stock_monthly','',0,'ALL','','2026-09-30',null,true);
verify(count($all)===5101, 'complete source reads profiles beyond both 300 and 5000');
verify(!str_contains(end($db->compiledQueries),'LIMIT'), 'complete source has no SQL truncation');
$limited = callPrivate($model,'list_division_stock_monthly','',300,'ALL','','2026-09-30',null,true);
verify(count($limited)===300, 'ordinary paginated reads retain their limit');
$allMovement = callPrivate($model,'list_division_material_movement_closing','2026-09-30','',null,'ALL',0);
verify(count($allMovement)===5101 && !str_contains(end($db->compiledQueries),'LIMIT'), 'movement source retains complete-scope sentinel');

// Verify the actual generator guard requests a complete compare and catches its last row.
$spy = new class extends Purchase_model {
    public bool $complete = false;
    public function list_division_material_stock_compare(string $date,string $q,?int $division,int $limit,?string $destination=null,bool $detailed=true,bool $completeScope=false): array {
        $this->complete=$completeScope;
        $rows=array_fill(0,5100,['is_match'=>true]);
        $rows[]=['is_match'=>false,'material_name'=>'Last profile','suspect_reason'=>'Real FIFO mismatch'];
        return ['rows'=>$completeScope?$rows:array_slice($rows,0,$limit),'summary'=>[]];
    }
};
$spy->db=$db;
$result=callPrivate($spy,'divisionMaterialLotIntegritySummary','2026-09-30',null,'ALL');
verify($spy->complete, 'generator explicitly requests a whole-scope comparison');
verify(!$result['ok'] && $result['mismatch_count']===1, 'real FIFO mismatch after the display limit remains blocked');
echo 'PASS: '.$checks." material generate integrity checks; no DB connection or writes.\n";
