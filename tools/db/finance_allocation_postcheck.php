<?php
declare(strict_types=1);
// CLI-only SQL emitter/reporter. Never opens a connection or loads app credentials.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function fap_sources(): array
{
    return [
        '2026-09-13a_finance_mutation_reporting_category.sql',
        '2026-09-14a_finance_control_workspace.sql',
        '2026-09-14b_finance_control_operations.sql',
        '2026-09-14c_finance_allocation_bank_review.sql',
    ];
}

function fap_source_hashes(string $root): array
{
    $hashes=[];
    foreach(fap_sources() as $file) {
        $path=$root.'/sql/'.$file;
        if(is_link($path) || !is_file($path) || !is_readable($path)) throw new RuntimeException('File SQL sumber tidak tersedia.');
        $hash=hash_file('sha256',$path);
        if(!is_string($hash)) throw new RuntimeException('Checksum SQL tidak dapat dibaca.');
        $hashes[$file]=$hash;
    }
    return $hashes;
}

/** All columns of 14c plus the metadata prerequisites used by its writers. */
function fap_columns(): array
{
    $tables=[
        'fin_receipt_distribution'=>[
            'receipt_id'=>'bigint unsigned','revision'=>'int unsigned=0','request_key'=>'char(32)','payload_hash'=>'char(64)',
            'notes'=>'varchar(255)','updated_by'=>'bigint unsigned','updated_at'=>'datetime=CURRENT_TIMESTAMP',
        ],
        'fin_receipt_allocation'=>[
            'id'=>'bigint unsigned AUTO','receipt_id'=>'bigint unsigned','settlement_id'=>'bigint unsigned','amount'=>'decimal(18,2)',
            'revision'=>'int unsigned','active_key'=>'?varchar(80)','created_by'=>'bigint unsigned','created_at'=>'datetime=CURRENT_TIMESTAMP',
        ],
        'fin_plan_allocation'=>[
            'id'=>'bigint unsigned AUTO','plan_id'=>'bigint unsigned','mutation_id'=>'bigint unsigned','amount'=>'decimal(18,2)',
            'active_key'=>'?varchar(80)','request_key'=>'char(32)','notes'=>'varchar(255)','created_by'=>'bigint unsigned',
            'created_at'=>'datetime=CURRENT_TIMESTAMP','unlinked_by'=>'?bigint unsigned','unlinked_at'=>'?datetime','unlink_reason'=>'?varchar(255)',
        ],
        'fin_bank_statement_row'=>[
            'id'=>'bigint unsigned AUTO','account_id'=>'bigint unsigned','row_hash'=>'char(64)','file_hash'=>'char(64)','statement_date'=>'date',
            'reference_no'=>'varchar(100)','description'=>'varchar(255)','direction'=>'varchar(3)','amount'=>'decimal(18,2)',
            'mutation_id'=>'?bigint unsigned','active_mutation_id'=>'?bigint unsigned','revision'=>'int unsigned=0',
            'created_by'=>'bigint unsigned','created_at'=>'datetime=CURRENT_TIMESTAMP',
        ],
        'fin_revenue_reconciliation_line'=>[
            'counter_account_id'=>'?bigint unsigned','counter_payment_method_id'=>'?bigint unsigned','counter_mutation_id'=>'?bigint unsigned',
            'resolution_type'=>"enum('NONE','IN','OUT','TRANSFER')=NONE",
            'report_category'=>'?varchar(32)','settlement_control_id'=>'?bigint unsigned','settlement_charge_id'=>'?bigint unsigned',
        ],
        'fin_cash_reconciliation_line'=>['report_category'=>'?varchar(32)','settlement_control_id'=>'?bigint unsigned','settlement_charge_id'=>'?bigint unsigned'],
        'fin_account_mutation_log'=>['report_category'=>'?varchar(32)','client_request_key'=>'?varchar(64)','settlement_control_id'=>'?bigint unsigned','settlement_charge_id'=>'?bigint unsigned'],
        'fin_settlement_control'=>['receipt_mode'=>'tinyint=0','receipt_opening_amount'=>'?decimal(18,2)'],
    ];
    return $tables;
}

function fap_indexes(): array
{
    return [
        ['fin_receipt_distribution','PRIMARY',true,['receipt_id']],
        ['fin_receipt_allocation','PRIMARY',true,['id']],
        ['fin_receipt_allocation','uq_fin_receipt_allocation',true,['active_key']],
        ['fin_receipt_allocation','ix_fin_receipt_alloc_case',false,['settlement_id','active_key']],
        ['fin_receipt_allocation','ix_fin_receipt_alloc_receipt',false,['receipt_id','active_key']],
        ['fin_plan_allocation','PRIMARY',true,['id']],
        ['fin_plan_allocation','uq_fin_plan_alloc_pair',true,['active_key']],
        ['fin_plan_allocation','uq_fin_plan_alloc_request',true,['request_key']],
        ['fin_plan_allocation','ix_fin_plan_alloc_mutation',false,['mutation_id','active_key']],
        ['fin_plan_allocation','ix_fin_plan_alloc_plan',false,['plan_id','active_key']],
        ['fin_bank_statement_row','PRIMARY',true,['id']],
        ['fin_bank_statement_row','uq_fin_bank_row',true,['account_id','row_hash']],
        ['fin_bank_statement_row','uq_fin_bank_match',true,['active_mutation_id']],
        ['fin_bank_statement_row','ix_fin_bank_date',false,['account_id','statement_date','id']],
        ['fin_account_mutation_log','uq_fin_mutation_request',true,['account_id','client_request_key']],
        ['fin_account_mutation_log','ix_fin_mutation_settlement',false,['settlement_control_id','report_category']],
        ['fin_account_mutation_log','ix_fin_mutation_charge',false,['settlement_charge_id']],
    ];
}

function fap_literal(string $value): string { return "'".str_replace("'","''",$value)."'"; }

function fap_column_condition(string $spec): string
{
    $nullable=str_starts_with($spec,'?');$spec=ltrim($spec,'?');
    $auto=str_ends_with($spec,' AUTO');if($auto)$spec=substr($spec,0,-5);
    [$type,$default]=array_pad(explode('=',$spec,2),2,null);
    $parts=["IS_NULLABLE=".fap_literal($nullable?'YES':'NO')];
    if(preg_match('/^(bigint|int|tinyint)( unsigned)?$/D',$type,$m)) {
        $parts[]='DATA_TYPE='.fap_literal($m[1]);
        $parts[]="COLUMN_TYPE ".(!empty($m[2])?'LIKE':'NOT LIKE')." '%unsigned%'";
    } elseif(preg_match('/^(varchar|char)\((\d+)\)$/D',$type,$m)) {
        $parts[]='DATA_TYPE='.fap_literal($m[1]);$parts[]='CHARACTER_MAXIMUM_LENGTH='.(int)$m[2];
        $parts[]="CHARACTER_SET_NAME='utf8mb4'";
    } elseif(preg_match('/^decimal\((\d+),(\d+)\)$/D',$type,$m)) {
        $parts[]="DATA_TYPE='decimal'";$parts[]='NUMERIC_PRECISION='.(int)$m[1];$parts[]='NUMERIC_SCALE='.(int)$m[2];
    } elseif(str_starts_with($type,'enum(')) {
        $parts[]='BINARY COLUMN_TYPE=BINARY '.fap_literal($type);
    } elseif(in_array($type,['date','datetime'],true)) {
        $parts[]='DATA_TYPE='.fap_literal($type);
        if($type==='datetime')$parts[]='DATETIME_PRECISION=0';
    } else throw new RuntimeException('Kontrak tipe kolom tidak dikenal.');
    $parts[]="LOWER(EXTRA) ".($auto?'LIKE':'NOT LIKE')." '%auto_increment%'";
    $parts[]="LOWER(EXTRA) NOT LIKE '%on update%'";
    if($default==='CURRENT_TIMESTAMP') {
        $parts[]="UPPER(REPLACE(COALESCE(COLUMN_DEFAULT,''),'()',''))='CURRENT_TIMESTAMP'";
    } elseif($default!==null) {
        $parts[]='BINARY REPLACE(COALESCE(COLUMN_DEFAULT,\'\'),CHAR(39),\'\')=BINARY '.fap_literal($default);
    } else {
        $parts[]="(COLUMN_DEFAULT IS NULL OR UPPER(COLUMN_DEFAULT)='NULL')";
    }
    return implode(' AND ',$parts);
}

/** Static SELECTs only. No transaction balances, credentials, settings or business rows. */
function fap_checks(array $hashes): array
{
    $checks=[];
    $add=static function(string $id,string $condition,string $group,string $label)use(&$checks):void {
        $checks[$id]=['expression'=>'CASE WHEN '.$condition." THEN 'PASS' ELSE 'FAIL' END",'group'=>$group,'label'=>$label];
    };
    $add('target.database',"BINARY DATABASE()=BINARY 'db_finance'",'schema','Database tujuan db_finance');
    $tables=array_unique(array_merge(array_keys(fap_columns()),[
        'fin_cash_plan','fin_settlement_receipt','fin_settlement_charge','fin_cash_plan_realization',
        'fin_control_evidence','fin_control_policy','fin_control_approval',
    ]));
    foreach($tables as $table)$add('table.'.$table,"EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".fap_literal($table)." AND TABLE_TYPE='BASE TABLE' AND ENGINE='InnoDB' AND TABLE_COLLATION LIKE 'utf8mb4_%')",'schema','Tabel '.$table.' (InnoDB/utf8mb4)');
    foreach(fap_columns() as $table=>$columns)foreach($columns as $column=>$spec) {
        $add('column.'.$table.'.'.$column,'EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.fap_literal($table).' AND COLUMN_NAME='.fap_literal($column).' AND '.fap_column_condition($spec).')','schema','Kolom '.$table.'.'.$column.' (tipe/default/nullability)');
    }
    foreach(fap_indexes() as [$table,$index,$unique,$columns]) {
        $parts=[];foreach($columns as $i=>$column)$parts[]='(SEQ_IN_INDEX='.($i+1).' AND COLUMN_NAME='.fap_literal($column).' AND SUB_PART IS NULL)';
        $condition='EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.fap_literal($table).' AND INDEX_NAME='.fap_literal($index)
            .' GROUP BY INDEX_NAME HAVING COUNT(*)='.count($columns).' AND MAX(NON_UNIQUE)='.($unique?0:1).' AND MIN(NON_UNIQUE)='.($unique?0:1)
            ." AND SUM(CASE WHEN INDEX_TYPE='BTREE' AND (".implode(' OR ',$parts).') THEN 1 ELSE 0 END)='.count($columns).')';
        $add('index.'.$table.'.'.$index,$condition,'schema','Index '.$table.'.'.$index.' (urutan/unique/full-length)');
    }
    $add('ledger.table',"EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_schema_migration' AND TABLE_TYPE='BASE TABLE')",'ledger','Tabel catatan migrasi');
    foreach(fap_sources() as $file) {
        if(!isset($hashes[$file]) || !preg_match('/^[a-f0-9]{64}$/D',$hashes[$file]))throw new RuntimeException('Checksum sumber tidak valid.');
        $path='sql/'.$file;$id=str_replace('_','-',substr($file,0,-4));
        $where='BINARY migration_id=BINARY '.fap_literal($id).' OR BINARY filename=BINARY '.fap_literal($path);
        $exact='BINARY migration_id=BINARY '.fap_literal($id).' AND BINARY filename=BINARY '.fap_literal($path).' AND BINARY checksum_sha256=BINARY '.fap_literal($hashes[$file]);
        $checks['ledger.'.$file]=['expression'=>"(SELECT CASE WHEN COUNT(*)=0 THEN 'WARN' WHEN COUNT(*)=1 AND SUM(CASE WHEN $exact THEN 1 ELSE 0 END)=1 THEN 'PASS' ELSE 'FAIL' END FROM sys_schema_migration WHERE $where)",
            'group'=>'ledger','label'=>$file.' (catatan/checksum)'];
    }
    return $checks;
}

function fap_fingerprint(array $checks,array $hashes): string { return hash('sha256',json_encode([$checks,$hashes],JSON_THROW_ON_ERROR)); }

function fap_sql(array $checks,array $hashes): string
{
    $fingerprint=fap_literal(fap_fingerprint($checks,$hashes));
    $sql="SELECT 'BEGIN','PASS',$fingerprint;\n";
    foreach($checks as $id=>$check)$sql.='SELECT '.fap_literal($id).','.$check['expression'].','.$fingerprint.";\n";
    return $sql."SELECT 'END','PASS',$fingerprint;\n";
}

function fap_report(string $input,array $checks,array $hashes): array
{
    $fingerprint=fap_fingerprint($checks,$hashes);$seen=[];
    if(strlen($input)>131072 || trim($input)==='')throw new RuntimeException('Hasil kosong/terlalu besar; pemeriksaan belum berhasil.');
    $lines=preg_split('/\r?\n/',rtrim($input,"\r\n"));
    foreach($lines as $i=>$line) {
        $parts=explode("\t",$line);
        if(count($parts)!==3)throw new RuntimeException('Keluaran pemeriksaan tidak valid; jangan dianggap lulus.');
        [$id,$status,$hash]=$parts;
        if(isset($seen[$id]) || (!isset($checks[$id]) && !in_array($id,['BEGIN','END'],true))
            || !in_array($status,['PASS','FAIL','WARN'],true) || !hash_equals($fingerprint,$hash))throw new RuntimeException('Keluaran tidak cocok/duplikat/berubah; pemeriksaan belum lengkap.');
        if(($i===0 && $id!=='BEGIN') || ($id==='BEGIN' && $i!==0) || ($id==='END' && $i!==count($lines)-1)
            || (in_array($id,['BEGIN','END'],true) && $status!=='PASS'))throw new RuntimeException('Penanda pemeriksaan tidak valid.');
        if($status==='WARN' && (in_array($id,['BEGIN','END','ledger.table'],true) || ($checks[$id]['group']??'')!=='ledger'))throw new RuntimeException('Status pemeriksaan tidak valid.');
        $seen[$id]=$status;
    }
    if(!isset($seen['BEGIN'],$seen['END']) || count($seen)!==count($checks)+2)throw new RuntimeException('Pemeriksaan terputus/belum lengkap. Periksa error client; jangan ulang migrasi secara otomatis.');
    $fail=[];$warn=[];$schemaFail=false;$ledgerPending=false;
    foreach($checks as $id=>$check) {
        if($seen[$id]==='FAIL'){$fail[]=$check['label'];if($check['group']==='schema')$schemaFail=true;}
        if($seen[$id]==='WARN')$warn[]=$check['label'];
        if($check['group']==='ledger' && $seen[$id]!=='PASS')$ledgerPending=true;
    }
    $output="Pemeriksaan read-only Finance / db_finance\n";
    $output.='Waktu laporan (UTC): '.gmdate('Y-m-d H:i:s')."\nFingerprint sumber: ".$fingerprint."\n";
    $output.='Struktur: '.($schemaFail?'TIDAK SESUAI':'LULUS').' | '.count($checks)." pemeriksaan diterima lengkap.\n";
    $output.='Catatan migrasi: '.($ledgerPending?'PERLU DITINJAU':'SESUAI CHECKSUM SUMBER')."\n";
    foreach($fail as $label)$output.='GAGAL: '.$label."\n";
    foreach($warn as $label)$output.='BELUM TERCATAT: '.$label."\n";
    $output.='HASIL: '.($fail?'PERLU_PERBAIKAN':($warn?'SKEMA_LULUS_LEDGER_PENDING':'SKEMA_LEDGER_LULUS'))."\n";
    $output.="Tidak mengubah data. Catatan kosong bukan bukti SQL belum dijalankan; jangan otomatis mengulang migrasi.\n";
    $output.="UAT browser, transaksi serentak, saldo transaksi dan paket customer BELUM diverifikasi oleh alat ini.\n";
    return ['text'=>$output,'exit_code'=>$fail?1:($warn?2:0)];
}

if(defined('FAP_LIBRARY_ONLY') && FAP_LIBRARY_ONLY)return;
try {
    if(count($argv)!==2 || !in_array($argv[1],['--sql','--report'],true))throw new RuntimeException('Gunakan --sql untuk SELECT saja, atau --report untuk membaca hasil client dari stdin. Jangan berikan credential pada argumen.');
    $hashes=fap_source_hashes(dirname(__DIR__,2));$checks=fap_checks($hashes);
    if($argv[1]==='--sql'){echo fap_sql($checks,$hashes);exit(0);}
    $raw=stream_get_contents(STDIN,131073);
    if(!is_string($raw))throw new RuntimeException('Hasil client tidak dapat dibaca.');
    $result=fap_report($raw,$checks,$hashes);echo $result['text'];exit($result['exit_code']);
} catch(Throwable $e) {
    // Never echo arbitrary input, database output, environment or a stack trace.
    fwrite(STDERR,'Pemeriksaan belum berhasil: '.$e->getMessage().PHP_EOL);exit(1);
}
