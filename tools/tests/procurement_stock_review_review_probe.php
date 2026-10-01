<?php
declare(strict_types=1);
// Regression of PR-01/02. Does not bootstrap CI or connect to Finance.
require __DIR__.'/procurement_stock_review_verify_smoke.php';

class ProcurementReviewProbeDb extends ReviewVerifyDb
{
    public bool $failMaterialRead=false;
    public function query($sql,$params=[])
    {
        if ($this->failMaterialRead && str_contains($sql,'FROM mst_item WHERE id=')) {
            throw new RuntimeException('synthetic material read failure');
        }
        return parent::query($sql,$params);
    }
    public function count_all_results($table):int
    {
        return (int)$this->select('COUNT(*) AS n')->from($table)->get()->row_array()['n'];
    }
}

$findings=[];
[$model,$sourceDb,$header,$lines]=fixture();
$db=new ProcurementReviewProbeDb();$db->pdo=$sourceDb->pdo;$model->db=$db;
$db->onBegin=static function($db):void {
    // Model another verifier having committed after the edit's prechecks.
    // This is controlled interleaving, not a claim about actual MariaDB concurrency.
    $db->pdo->exec("UPDATE pur_division_request SET status='VERIFIED',updated_at='2026-09-16 12:00:00';
      INSERT INTO fixture_po VALUES(91,2);
      INSERT INTO pur_division_request_link(request_id,doc_type,doc_id) VALUES(12,'PO',91);
      INSERT INTO pur_division_stock_review(request_id,reason) VALUES(12,'Already verified fixture');");
};
$lines[0]['qty_content_requested']=900;
$result=$model->update_division_request(12,$header,$lines,7);
$row=$db->query('SELECT status FROM pur_division_request WHERE id=12')->row_array();
$qty=(float)$db->query('SELECT qty_content_requested FROM pur_division_request_line WHERE request_id=12')->row_array()['qty_content_requested'];
$links=(int)$db->query('SELECT COUNT(*) AS n FROM pur_division_request_link')->row_array()['n'];
if (!empty($result['ok']) || !str_contains($result['message'] ?? '', 'berubah') || $qty===900.) {
    $findings[]=['id'=>'PR-01','severity'=>'HIGH','observed'=>'Edit accepted after verification interleave; request reset to SUBMITTED and lines changed while PO link/review remained.'];
}

[$model,$sourceDb,$header,$lines]=fixture();
$db=new ProcurementReviewProbeDb();$db->pdo=$sourceDb->pdo;
$db->pdo->exec("CREATE TABLE mst_item(id INTEGER PRIMARY KEY,material_id INTEGER);
  CREATE TABLE mst_material(id INTEGER PRIMARY KEY,material_name TEXT,content_uom_id INTEGER);
  INSERT INTO mst_item VALUES(1,10);INSERT INTO mst_material VALUES(10,'Kopi fixture',1);");
$line=['item_id'=>1,'material_id'=>null,'usage_purpose'=>'OPERASIONAL','content_uom_id'=>1,'qty_content_requested'=>500,'profile_name'=>'Kopi fixture'];
$service=new Procurement_stock_review($db,str_repeat('a',64));
$context=['request_id'=>12,'division_id'=>1,'destination_type'=>'BAR','user_id'=>7,'month'=>date('Y-m-01')];
$before=$service->snapshot($context,[$line]);$db->failMaterialRead=true;
$failed=$service->snapshot($context,[$line]);$rejected=false;
try { $service->validate($failed,[],time()); } catch (InvalidArgumentException $e) { $rejected=true; }
if (count($before['rows'])!==1 || count($failed['rows'])!==1 || !$failed['rows'][0]['needs_confirmation'] || !$rejected) {
    $findings[]=['id'=>'PR-02','severity'=>'MEDIUM','observed'=>'Material-linked operational item disappears from review when material lookup throws; validator accepts empty confirmation.'];
}

foreach($findings as $finding) echo 'OPEN '.json_encode($finding,JSON_UNESCAPED_SLASHES).PHP_EOL;
echo 'Review probe: '.count($findings).' open defects reproduced; SQLite memory only. No runtime changes.'.PHP_EOL;
exit($findings?1:0);
