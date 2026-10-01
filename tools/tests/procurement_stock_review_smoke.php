<?php
declare(strict_types=1);
// Real reader/policy SQL against SQLite memory. No application bootstrap or live DB.
$root=dirname(__DIR__,2); define('BASEPATH',$root.'/system/');
require $root.'/application/libraries/Procurement_stock_review.php';
set_error_handler(static function($n,$message,$file,$line): void { throw new ErrorException($message,0,$n,$file,$line); });
class StockReviewResult {
    private array $rows;
    public function __construct(array $rows) { $this->rows=$rows; }
    public function result_array(): array { return $this->rows; }
    public function row_array(): array { return $this->rows[0]??[]; }
}
class StockReviewDb {
    public PDO $pdo; public ?string $fail=null; public array $queries=[]; public bool $db_debug=true;
    public function __construct() {
        $this->pdo=new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function query($sql,$params=[]): StockReviewResult {
        $this->queries[]=$sql;
        if($this->fail!==null && str_contains($sql,$this->fail)) throw new RuntimeException('synthetic failure');
        $q=$this->pdo->prepare($sql);$q->execute($params);return new StockReviewResult($q->fetchAll(PDO::FETCH_ASSOC));
    }
    public function table_exists($table): bool { return (bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array(); }
    public function field_exists($field,$table): bool { return in_array($field,array_column($this->query('PRAGMA table_info('.$table.')')->result_array(),'name'),true); }
}
$db=new StockReviewDb();
$db->pdo->exec('CREATE TABLE mst_item(id INTEGER PRIMARY KEY,material_id INTEGER);
CREATE TABLE mst_material(id INTEGER PRIMARY KEY,material_name TEXT,content_uom_id INTEGER);
CREATE TABLE mst_uom(id INTEGER PRIMARY KEY,code TEXT);
CREATE TABLE mst_uom_conversion(from_uom_id INTEGER,to_uom_id INTEGER,factor REAL,is_active INTEGER);
CREATE TABLE pur_division_stock_review(id INTEGER PRIMARY KEY,request_id INTEGER UNIQUE,reviewed_by INTEGER,reviewed_at TEXT,source_ip TEXT,confirmed_with TEXT,reason TEXT,snapshot_hash TEXT,snapshot_json TEXT);
INSERT INTO mst_item VALUES(1,10),(2,20),(3,NULL),(4,10);
INSERT INTO mst_material VALUES(10,"Kopi",1),(20,"Gula",1);
INSERT INTO mst_uom VALUES(1,"GR"),(2,"KG"),(3,"PACK");
INSERT INTO mst_uom_conversion VALUES(2,1,1000,1);');
$ddl='(id INTEGER PRIMARY KEY,identity_key TEXT,profile_key TEXT,item_id INTEGER,material_id INTEGER,buy_uom_id INTEGER,content_uom_id INTEGER,closing_qty_content REAL,month_key TEXT,updated_at TEXT,last_movement_at TEXT,division_id INTEGER,destination_type TEXT)';
$db->pdo->exec('CREATE TABLE inv_division_monthly_stock '.$ddl.'; CREATE TABLE inv_warehouse_monthly_stock '.$ddl);
$month=date('Y-m-01');$prior=date('Y-m-01',strtotime($month.' -1 month'));$now=time();
$add=static function(string $table,array $r)use($db,$month):void {
    $row=$r+['id'=>1,'identity_key'=>'p1','profile_key'=>'p1','item_id'=>1,'material_id'=>10,'buy_uom_id'=>2,'content_uom_id'=>1,'closing_qty_content'=>1000,'month_key'=>$month,'updated_at'=>$month.' 10:00:00','last_movement_at'=>$month.' 10:00:00','division_id'=>7,'destination_type'=>'BAR'];
    $sql='INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')';
    $db->query($sql,array_values($row));
};
$add('inv_division_monthly_stock',[]);
$add('inv_division_monthly_stock',['id'=>2,'month_key'=>$prior,'closing_qty_content'=>9000]); // not cumulative
$add('inv_division_monthly_stock',['id'=>3,'division_id'=>8,'closing_qty_content'=>7000]);
$add('inv_division_monthly_stock',['id'=>4,'destination_type'=>'BAR_EVENT','closing_qty_content'=>3000]);
$add('inv_division_monthly_stock',['id'=>5,'identity_key'=>'legacy-hash','closing_qty_content'=>6000]); // noncanonical shadow
$add('inv_warehouse_monthly_stock',['content_uom_id'=>2,'closing_qty_content'=>2]);
$context=['request_id'=>100,'division_id'=>7,'destination_type'=>'BAR','user_id'=>9,'request_revision'=>'v1','month'=>$month];
$line=['item_id'=>1,'material_id'=>10,'content_uom_id'=>1,'buy_uom_id'=>2,'qty_content_requested'=>500,'profile_name'=>'Kopi','usage_purpose'=>'BAHAN_BAKU'];
$service=new Procurement_stock_review($db,str_repeat('a',64));
$checks=0;$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;};
$reject=static function(callable $fn,string $label)use($check):void{try{$fn();}catch(InvalidArgumentException $e){$check(true,$label);return;}throw new RuntimeException('FAIL expected rejection '.$label);};
$snapshot=$service->snapshot($context,[$line]);$row=$snapshot['rows'][0];
$check($row['division']['qty']===1000.,'correct division/location; previous months and shadows excluded');
$check($row['warehouse']['qty']===2000.,'KG converted into GR');
$check($row['needs_confirmation'],'positive division stock requires confirmation');
$preview=$service->preview($snapshot,$now);
$confirmation=['token'=>$preview['token'],'confirmed'=>true,'confirmed_with'=>'Kepala Bar','reason'=>'Kebutuhan tambahan untuk event besok.'];
$evidence=$service->validate($snapshot,$confirmation,$now);
$check(json_decode($evidence['snapshot_json'],true)['rows'][0]['division']['qty']==1000,'approved evidence snapshot');
$check($evidence['confirmed_with']==='Kepala Bar' && strlen($evidence['snapshot_hash'])===64,'witness and evidence hash');
foreach ([[],array_replace($confirmation,['confirmed'=>false]),array_replace($confirmation,['confirmed_with'=>'']),array_replace($confirmation,['reason'=>'ok']),array_replace($confirmation,['token'=>'tampered']),array_replace($confirmation,['reason'=>[]]),array_replace($confirmation,['confirmed_with'=>str_repeat('a',151)]),array_replace($confirmation,['reason'=>str_repeat('b',1001)])] as $bad) $reject(fn()=>$service->validate($snapshot,$bad,$now),'missing/invalid confirmation');
$reject(fn()=>$service->validate($snapshot,$confirmation,$now+901),'expired');
$reject(fn()=>$service->validate($snapshot,$confirmation,$now-1),'future timestamp');
foreach (['user_id'=>11,'division_id'=>8,'destination_type'=>'BAR_EVENT','request_id'=>101,'request_revision'=>'v2'] as $key=>$value) {
    $changed=$service->snapshot(array_replace($context,[$key=>$value]),[$line]);
    $reject(fn()=>$service->validate($changed,$confirmation,$now),'context change '.$key);
}
$changed=$service->snapshot($context,[array_replace($line,['qty_content_requested'=>600])]);
$reject(fn()=>$service->validate($changed,$confirmation,$now),'quantity change');
$changed=$service->snapshot($context,[array_replace($line,['material_id'=>20,'item_id'=>2])]);
$reject(fn()=>$service->validate($changed,$confirmation,$now),'material change');
$other=new Procurement_stock_review($db,str_repeat('b',64));
$reject(fn()=>$other->validate($snapshot,$confirmation,$now),'different session');
$db->pdo->exec('UPDATE inv_division_monthly_stock SET closing_qty_content=800 WHERE id=1');
$changed=$service->snapshot($context,[$line]);
$check($changed['rows'][0]['division']['qty']===800.,'reader re-reads without cached preview');
$reject(fn()=>$service->validate($changed,$confirmation,$now),'stock changed');
$db->pdo->exec('UPDATE inv_division_monthly_stock SET closing_qty_content=0 WHERE id=1');
$zero=$service->snapshot($context,[$line]);$zeroPreview=$service->preview($zero,$now);
$check(!$zero['rows'][0]['needs_confirmation'],'known zero stock does not force reason');
$check($service->validate($zero,['token'=>$zeroPreview['token']],$now)['reason']==='','zero still stores read evidence');
$operational=array_replace($line,['usage_purpose'=>'OPERASIONAL','item_id'=>3,'material_id'=>null]);
$empty=$service->snapshot($context,[$operational]);
$check($empty['rows']===[] && $service->validate($empty,[],$now)===[],'pure operational lines unaffected');
$check(count($service->snapshot($context,[array_replace($line,['usage_purpose'=>'OPERASIONAL','material_id'=>null])])['rows'])===1,'material-linked operational item cannot hide stock check');
$unmapped=$service->snapshot($context,[array_replace($line,['item_id'=>3,'material_id'=>null])]);
$check($unmapped['rows'][0]['division']['qty']===null && $unmapped['rows'][0]['needs_confirmation'],'unmapped not fake zero');
$unknownConfirmation=array_replace($confirmation,['token'=>$service->preview($unmapped,$now)['token']]);
$check(!empty($service->validate($unmapped,$unknownConfirmation,$now)),'explicit confirmation can handle unknown stock');
$conflict=$service->snapshot($context,[array_replace($line,['material_id'=>20])]);
$check($conflict['rows'][0]['division']['state']==='UNKNOWN','conflicting material mapping');
$noRows=$service->snapshot($context,[array_replace($line,['material_id'=>20,'item_id'=>2])]);
$check($noRows['rows'][0]['division']['qty']===null,'no stock record not fake zero');
$kg=$service->snapshot($context,[array_replace($line,['content_uom_id'=>2])]);
$check($kg['rows'][0]['warehouse']['qty']===2.,'same-unit no conversion');
$db->pdo->exec('UPDATE inv_division_monthly_stock SET closing_qty_content=1000 WHERE id=1');
$kg=$service->snapshot($context,[array_replace($line,['content_uom_id'=>2])]);
$check($kg['rows'][0]['division']['qty']===1.,'inverse conversion');
$pack=$service->snapshot($context,[array_replace($line,['content_uom_id'=>3])]);
$check($pack['rows'][0]['division']['state']==='UNKNOWN','missing conversion is unknown');
$db->pdo->exec('INSERT INTO mst_uom_conversion VALUES(1,2,0.5,1)');
$conflicting=$service->snapshot($context,[$line]);
$check($conflicting['rows'][0]['warehouse']['state']==='UNKNOWN','conflicting unit conversion');
$db->pdo->exec('DELETE FROM mst_uom_conversion WHERE from_uom_id=1');
$db->fail='FROM inv_division_monthly_stock';
$failure=$service->snapshot($context,[$line]);
$check($failure['rows'][0]['division']['state']==='UNKNOWN' && $failure['rows'][0]['warehouse']['state']==='KNOWN','isolated query failure cannot show zero');
$db->fail=null;
$add('inv_division_monthly_stock',['id'=>6,'item_id'=>4,'identity_key'=>'p2','profile_key'=>'p2','closing_qty_content'=>250]);
$sum=$service->snapshot($context,[$line]);
$check($sum['rows'][0]['division']['qty']===1250.,'same material summed across distinct item profiles');
$db->pdo->exec('UPDATE inv_division_monthly_stock SET closing_qty_content=-1000 WHERE id=6');
$negative=$service->snapshot($context,[$line]);
$check($negative['rows'][0]['division']['qty']===0. && $negative['rows'][0]['needs_confirmation'],'negative profile not hidden by zero net');
$add('inv_division_monthly_stock',['id'=>7,'updated_at'=>$month.' 11:00:00','closing_qty_content'=>750]);
$dedup=$service->snapshot($context,[$line]);
$check($dedup['rows'][0]['division']['qty']===-250.,'duplicate canonical identity picks latest row, not sum');
$db->pdo->exec('DROP TABLE pur_division_stock_review');
$check(!$service->preview($snapshot,$now)['ready'],'missing review migration indicated');
$reject(fn()=>$service->validate($snapshot,$confirmation,$now),'missing migration blocks material verification');
$check($service->validate($empty,[],$now)===[],'missing migration does not block operational-only request');

// Render actual history partial with malicious fixture text (no customer data).
function html_escape($v):string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$stock_review_history=[['request_no'=>'<img src=x onerror=alert(1)>','reviewed_at'=>'2026-09-16 10:00:00','reviewer_name'=>'Operator','confirmed_with'=>'<script>bad</script>','reason'=>'& test','snapshot_json'=>$evidence['snapshot_json']]];
ob_start();require $root.'/application/views/procurement/_stock_review_history.php';$html=ob_get_clean();
$check(!str_contains($html,'<script>bad') && str_contains($html,'&lt;script&gt;'),'history escapes confirmation');
$check(str_contains($html,'bukan saldo saat ini'),'history not mislabeled as live');
function site_url($path):string{return '/'.$path;}
function base_url($path):string{return '/'.$path;}
foreach([true,false] as $can_verify){
    $stock_review_csrf=str_repeat('a',64);$request_id=12;
    ob_start();require $root.'/application/views/procurement/_stock_review_panel.php';$panel=ob_get_clean();
    $check(str_contains($panel,'data-stock-confirmed')===$can_verify,'panel confirmation only for verification mode');
    $check(str_contains($panel,'name="procurement_csrf"') && str_contains($panel,'id="stockReviewJson"'),'panel has both CSRF and proof fields');
    $check(str_contains($panel,'procurement-stock-review.js') && str_contains($panel,'data-request-id="12"'),'panel loads client and request context');
}
echo "Procurement stock review: {$checks} PASS (SQLite memory; no live DB).\n";
