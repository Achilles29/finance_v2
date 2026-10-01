<?php
// Included only by the isolated accounting smoke suite; never bootstrap Finance.
if (PHP_SAPI!=='cli' || !isset($db) || $db->database!==':memory:') {http_response_code(404);exit;}
$setupMissingData=$model->setup_data();
$check(!$setupMissingData['schema_ready'],'missing assistant migration falls back to suggestions/manual journal');
$check(count($setupMissingData['mappings'])===15,'fifteen explicit scenarios available');
$check(!$model->save_setup('account',[],1)['ok'],'missing migration blocks only configuration writes');
$check(!$model->preview('MUTATION',$out,'2026-07-10')['assistant_options'],'transfer cannot use arbitrary assistant mapping');
$check(!$model->preview('MUTATION',$reversal,'2026-08-01')['assistant_options'],'source reversal cannot use arbitrary assistant mapping');
foreach(Finance_journal_assistant::scenarios() as $code=>$definition){
    Finance_journal_assistant::validate_account($code,$definition['default_account'],$model->accounts());
    $check(Finance_journal_assistant::eligible($code,['ref_module'=>'FINANCE','mutation_type'=>$definition['direction'],'report_category'=>$code]),'default eligible '.$code);
    $check(!Finance_journal_assistant::eligible($code,['ref_module'=>'FINANCE','mutation_type'=>$definition['direction']==='IN'?'OUT':'IN','report_category'=>$code]),'wrong direction blocked '.$code);
}
$check(!Finance_journal_assistant::eligible('OPERATING_EXPENSE',['ref_module'=>'PURCHASE','mutation_type'=>'OUT','report_category'=>'OPERATING_EXPENSE']),'purchase category is not guessed as operating expense');
$check(!Finance_journal_assistant::eligible('OWNER_CAPITAL',['ref_module'=>'FINANCE','mutation_type'=>'IN','report_category'=>'OTHER_INCOME']),'category-bound suggestions cannot override source classification');
$setupMigration=file_get_contents($root.'/sql/2026-09-15b_finance_journal_assistant.sql');
$create($setupMigration);$db->data_cache=[];
$check($model->setup_ready()&&!$db->count_all('fin_gl_mapping'),'actual migration parsed with no automatic mapping seeds');
$check(str_contains($setupMigration,"'finance.accounting.settings'")&&str_contains($setupMigration,'can_edit'),'configuration has separate RBAC metadata');
$check(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|UPDATE)\s+(?:TABLE|FROM|fin_)\b/i',$setupMigration),'assistant migration is additive metadata only');
$snapshot=static function()use($db):array{
    $rows=[];foreach(['fin_gl_account','fin_gl_mapping','fin_gl_journal','fin_gl_line','aud_transaction_log','fin_company_account','fin_account_mutation_log'] as $t)$rows[$t]=$db->get($t)->result_array();return $rows;
};
$setupGood=static function($kind,$payload,$label)use($model,$check):array{
    $r=$model->save_setup($kind,$payload,1,'127.0.0.1');$check(!empty($r['ok']),$label.' '.($r['message']??''));return $r;
};
$setupBad=static function($kind,$payload,$reason,$actor=1)use($model,$check,$db,$snapshot):void{
    $before=$snapshot();$r=$model->save_setup($kind,$payload,$actor);
    $check(empty($r['ok'])&&str_contains($r['message'],$reason),'setup reject '.$reason);
    $check($snapshot()===$before,'setup rejection rolls back all metadata/audit/journal/source writes');$db->reset_request();
};
$accountForm=static function($code,$name,$active=1)use($model):array{
    return ['code'=>$code,'name'=>$name,'is_active'=>$active,'expected_hash'=>Finance_journal_assistant::account_hash($model->accounts()[$code]??null)];
};
$mappingForm=static function($scenario,$code=null,$active=1,$flow=null)use($model):array{
    $r=$model->mappings()[$scenario];return ['scenario_code'=>$scenario,'account_code'=>$code??$r['account_code'],'cashflow_class'=>$flow??$r['cashflow_class'],'is_enabled'=>$active,'expected_hash'=>$r['hash']];
};
$businessBefore=[$db->get('fin_company_account')->result_array(),$db->get('fin_account_mutation_log')->result_array()];
$historyBefore=[$db->get('fin_gl_journal')->result_array(),$db->get('fin_gl_line')->result_array()];
$createAccount=$accountForm('5210','Beban Listrik <img src=x>');
$setupGood('account',$createAccount,'add noncash account via model');
$check($model->accounts()['5210']['account_type']==='EXPENSE'&&!$model->accounts()['5210']['is_cash'],'account type inferred from code, not client');
$auditCount=$db->count_all('aud_transaction_log');
$check($setupGood('account',$createAccount,'retry identical create')['unchanged']&&$auditCount===$db->count_all('aud_transaction_log'),'lost response retry produces no extra audit');
$setupBad('account',array_replace($createAccount,['name'=>'Different stale name']),'Daftar akun telah berubah');
$setupGood('account',$accountForm('5210','Beban Listrik <img src=x> revisi')+['account_type'=>'ASSET','is_cash'=>1],'rename account cannot alter immutable client-supplied type/cash');
$check($model->accounts()['5210']['account_type']==='EXPENSE'&&!$model->accounts()['5210']['is_cash'],'protected attributes remain unchanged after rename');
foreach(['1100','1190'] as $code)$setupBad('account',$accountForm($code,'Alter'),'dilindungi');
$setupBad('account',$accountForm('0123','Leading zero'),'Kode akun');
$setupBad('account',$accountForm('5211',''), 'Nama akun wajib');
$setupBad('account',$accountForm('5211','Actor missing'),'Sesi pengguna',0);
$setupBad('account',array_replace($accountForm('5211','Status invalid'),['is_active'=>3]),'Status akun');
$setupBad('mapping',$mappingForm('OPERATING_EXPENSE','1300'),'Pilih akun nonkas');
$setupBad('mapping',$mappingForm('OPERATING_EXPENSE','1100'),'Pilih akun nonkas');
$setupBad('mapping',$mappingForm('CASH_INVENTORY','1190'),'Pilih akun nonkas');
$setupBad('mapping',$mappingForm('OPERATING_EXPENSE',null,1,'TRANSFER'),'Kelompok arus kas');
$setupBad('mapping',array_replace($mappingForm('OPERATING_EXPENSE'),['scenario_code'=>'UNKNOWN']),'Jenis transaksi');
$setupBad('mapping',$mappingForm('OPERATING_EXPENSE',null,2),'Status saran');
$mapping=$mappingForm('OPERATING_EXPENSE','5210');$setupGood('mapping',$mapping,'save expense mapping');
$check($model->mappings()['OPERATING_EXPENSE']['revision']===1&&$model->mappings()['OPERATING_EXPENSE']['configured'],'first confirmed mapping revision');
$auditCount=$db->count_all('aud_transaction_log');
$check($setupGood('mapping',$mapping,'retry identical mapping')['unchanged']&&$auditCount===$db->count_all('aud_transaction_log'),'mapping retry is idempotent');
$setupBad('mapping',array_replace($mapping,['account_code'=>'5200']),'Pengaturan telah berubah');
$setupBad('account',$accountForm('5210','Beban Listrik',0),'Akun sudah dipakai');
$setupBad('account',$accountForm('1300','Persediaan',0),'Akun sudah dipakai');
$setupGood('account',$accountForm('5990','Unused reference'),'create unused reference');
$setupGood('account',$accountForm('5990','Unused reference',0),'disable unused reference');
$setupBad('mapping',$mappingForm('OPERATING_EXPENSE','5990'),'Pilih akun nonkas');
$db->failAudit=true;
$setupBad('mapping',$mappingForm('OPERATING_EXPENSE','5200'),'Jurnal gagal');
$setupBad('account',$accountForm('5212','Audit rollback account'),'Jurnal gagal');
$db->failAudit=false;$db->reset_request();
$check($businessBefore===[$db->get('fin_company_account')->result_array(),$db->get('fin_account_mutation_log')->result_array()],'all configuration changes preserve business cash/mutations');
$check($historyBefore===[$db->get('fin_gl_journal')->result_array(),$db->get('fin_gl_line')->result_array()],'configuration changes never rewrite posted journals/amounts');

$expenseSource=$source('OUT','7.25','FINANCE','OPERATING_EXPENSE');
$assistantForm=static function($scenario,$id)use($model,$mutationForm,$line):array{
    $r=$model->mappings()[$scenario];$m=$model->preview('MUTATION',$id,'2026-07-01')['source'];
    return $mutationForm($id,[$line($r['account_code'],$m['mutation_type']==='OUT'?$m['amount']:'0',$m['mutation_type']==='IN'?$m['amount']:'0')],$r['cashflow_class'])+
        ['assistant_scenario'=>$scenario,'assistant_hash'=>$r['hash'],'assistant_confirmed'=>true];
};
$oldForm=$assistantForm('OPERATING_EXPENSE',$expenseSource);
$setupGood('mapping',$mappingForm('OPERATING_EXPENSE','5200'),'mapping changed between preview and post');
$bad($oldForm,'Pemetaan saran berubah');
$disabledForm=$assistantForm('OPERATING_EXPENSE',$expenseSource);
$setupGood('mapping',$mappingForm('OPERATING_EXPENSE',null,0),'disable expense suggestion');
$bad($disabledForm,'Pemetaan saran berubah');
$p=$model->preview('MUTATION',$expenseSource,'2026-07-10');
$check($p['suggestion']===null&&!in_array('OPERATING_EXPENSE',array_column($p['assistant_options'],'scenario_code'),true),'disabled mapping has no legacy prefill or assistant option');
$setupGood('mapping',$mappingForm('OPERATING_EXPENSE','5210'),'reenable expense suggestion');
$renamedForm=$assistantForm('OPERATING_EXPENSE',$expenseSource);
$setupGood('account',$accountForm('5210','Beban Listrik <img src=x> final'),'rename invalidates pending account suggestion');
$bad($renamedForm,'Pemetaan saran berubah');
$validForm=$assistantForm('OPERATING_EXPENSE',$expenseSource);
$bad(array_replace($validForm,['assistant_confirmed'=>false]),'Konfirmasikan');
$bad(array_replace($validForm,['assistant_confirmed'=>'true']),'Konfirmasikan');
$bad(array_replace($validForm,['assistant_scenario'=>'OWNER_CAPITAL']),'Saran tidak cocok');
$bad(array_replace($validForm,['cashflow_class'=>'INVESTING']),'Baris saran telah diubah');
$bad(array_replace($validForm,['lines'=>[$line('5200','7.25')]]),'Baris saran telah diubah');
$bad(array_replace($validForm,['lines'=>[$line('5210','7.24')]]),'Nominal saran');
$bad(array_replace($validForm,['lines'=>[$line('5210','7.25'),$line('5200',1)]]),'Baris saran telah diubah');
$assistantPosted=$good($validForm,'confirmed assistant posts exact cash counterpart');
$check($good($validForm,'assistant retry')['id']===$assistantPosted['id'],'assistant source/retry unique');
$auditRow=$db->get_where('aud_transaction_log',['entity_table'=>'fin_gl_journal','entity_id'=>$assistantPosted['id'],'action_code'=>'GL_POST'])->row_array();
$audit=json_decode($auditRow['after_payload'],true);
$check($audit['assistant']['scenario']==='OPERATING_EXPENSE'&&$audit['assistant']['account_code']==='5210'&&$audit['assistant']['confirmed']===true,'journal audit records scenario mapping revision and explicit confirmation');
$mappingAudit=$db->get_where('aud_transaction_log',['action_code'=>'GL_MAPPING','transaction_no'=>'OPERATING_EXPENSE'])->result_array();
$check(count($mappingAudit)===4&&$mappingAudit[0]['actor_user_id']==1&&$mappingAudit[0]['source_ip']==='127.0.0.1','mapping audit records actor/source IP and changes, not retries');
$setupGood('mapping',$mappingForm('OPERATING_EXPENSE','5200'),'future mapping changed after posting');
$assistantReversal=$source('IN','7.25','FINANCE',null,'2026-07-20',1,$expenseSource);
$reversed=$good($mutationForm($assistantReversal,[]),'cash reversal follows original assistant account after mapping change');
$reversedLine=$db->get_where('fin_gl_line',['journal_id'=>$reversed['id'],'account_code'=>'5210'])->row_array();
$check(Finance_journal_policy::cents($reversedLine['credit'])===725,'reversal uses original expense account, not latest mapping');
$manualSource=$source('OUT',2,'FINANCE','OPERATING_EXPENSE');
$good($mutationForm($manualSource,[$line('5300',2)]),'manual multi-purpose accounting remains available without assistant marker');

foreach(Finance_journal_assistant::scenarios() as $scenario=>$d){
    $id=$source($d['direction'],'1.25','FINANCE',$d['category_bound']?$scenario:null);
    $cashBefore=[$db->get('fin_company_account')->result_array(),$db->get('fin_account_mutation_log')->result_array()];
    $good($assistantForm($scenario,$id),'assistant scenario '.$scenario);
    $check($cashBefore===[$db->get('fin_company_account')->result_array(),$db->get('fin_account_mutation_log')->result_array()],'scenario never moves cash twice '.$scenario);
}
