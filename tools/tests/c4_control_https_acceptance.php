<?php
declare(strict_types=1);
require dirname(__DIR__).'/licensing/FinanceLicenseAgent.php';
umask(0077);
$s=$argv[1]??''; $control=$argv[2]??'';
if (PHP_SAPI!=='cli'||posix_geteuid()!==0||preg_match('~\A/var/lib/finance-control-[0-9]{8}[.][A-Za-z0-9]{6}\z~D',$s)!==1
    ||realpath($s)!==$s||!is_file($control.'/tools/process_license_issuance.php')||is_file($s.'/license-http-acceptance.json')) throw new RuntimeException('EXPLICIT_FRESH_CONTROL_FIXTURE_REQUIRED');
$f=json_decode((string)file_get_contents($s.'/fixture.json'),true,32,JSON_THROW_ON_ERROR);
if (preg_match('/\Afinance_ctl_test_[a-f0-9]{12}\z/D',$f['database'])!==1||$f['database']!==$f['user']||$f['base_url']!=='https://127.0.0.1:18444/') throw new RuntimeException('DISPOSABLE_CONTROL_ONLY');
$db=new PDO('mysql:host=127.0.0.1;dbname='.$f['database'].';charset=utf8mb4',$f['user'],$f['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$checks=[]; $check=static function(bool $ok,string $label) use (&$checks):void { if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=$label;echo 'PASS '.$label."\n"; };
$trust=json_decode((string)file_get_contents($s.'/trust.json'),true);$gid=posix_getgrnam('finance_c3_trial')['gid'];$root=dirname(__DIR__,2);
$origin=rtrim($f['base_url'],'/');$offline=false;$loseActivation=false;$captured=[];
$wire=static function(string $o,string $path,string $body,array $headers) use ($origin,&$offline,&$loseActivation,&$captured,$s):array {
    if($o!==$origin)throw new RuntimeException('FIXTURE_ORIGIN_MISMATCH');
    if($offline)throw new RuntimeException('CONTROL_TRANSPORT_UNAVAILABLE');
    $r=ControlLicenseProtocol::post($o,$path,$body,$headers,$s.'/tls.crt');$captured=[$path,$body,$headers];
    if($path===ControlLicenseProtocol::REQUEST_PATH && $r['http']!==202) file_put_contents($s.'/last-license-error.json',json_encode($r));
    if($loseActivation&&$path===ControlLicenseProtocol::REQUEST_PATH){$loseActivation=false;throw new RuntimeException('CONTROL_TRANSPORT_UNAVAILABLE');}
    return $r;
};
$newAgent=static function(string $label) use($s,$gid,$root,$wire):FinanceLicenseAgent {
    $p=$s.'/'.$label.'-private';$v=$s.'/'.$label.'-public';
    if(!is_dir($p)){mkdir($p,0700);mkdir($v,0750);chmod($v,0750);chgrp($v,$gid);}
    return new FinanceLicenseAgent(new LicenseAgentFiles($p,$root,0,true),new LicenseAgentFiles($v,$root,$gid,false),hash('sha256','CONTROL-HTTPS-FIXTURE-'.$label),$wire);
};
$issuer=static function() use($s,$control):string {
    $p=proc_open([PHP_BINARY,$control.'/tools/process_license_issuance.php'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file',$s.'/issuer-error.log','a']],$pipes,$control,['PATH'=>'/usr/bin:/bin','CONTROL_LICENSE_ISSUER_CONFIG'=>$s.'/issuer.json']);
    if(!is_resource($p))throw new RuntimeException('ISSUER_START_FAILED');$out=stream_get_contents($pipes[1]);fclose($pipes[1]);
    if(proc_close($p)!==0)throw new RuntimeException('ISOLATED_ISSUER_FAILED');return $out;
};
try {
    $primary=$newAgent('primary');$identity=$primary->initialize($f['primary']['instance_id'],$origin,$trust)['identity'];
    $check($primary->activate($f['primary']['activation_code'])['status']==='PENDING','actual Control HTTPS accepts Finance activation');
    $check($primary->poll()['connection']==='PENDING','actual HTTP pending before issuer runs');
    $check(strpos($issuer(),'issued=1')!==false,'actual root issuer signs only isolated Control database');
    $check($primary->poll()['verified'] && $primary->poll()['status']==='ACTIVE','actual Control signed envelope accepted by Finance agent');
    $cached=json_decode((string)file_get_contents($s.'/primary-public/runtime.json'),true);
    $check(Control_license_cache::verification($cached,$trust,$identity)['status']==='ACTIVE','expired maintenance does not block perpetual fixture');
    $replay=ControlLicenseProtocol::post($origin,$captured[0],$captured[1],$captured[2],$s.'/tls.crt');
    $check($replay['http']===409&&($replay['json']['code']??'')==='replay_detected','actual Control rejects signed request nonce replay');
    $offline=true;$off=$primary->poll();$offline=false;
    $check($off['verified']&&$off['connection']==='SYNC_UNAVAILABLE','network failure preserves genuine Control signed cache');
    $recovery=$newAgent('recovery');$identity2=$recovery->initialize($f['recovery']['instance_id'],$origin,$trust)['identity'];
    $loseActivation=true;
    try{$recovery->activate($f['recovery']['activation_code']);throw new RuntimeException('EXPECTED_LOST_RESPONSE');}catch(RuntimeException $e){$check($e->getMessage()==='CONTROL_TRANSPORT_UNAVAILABLE','lost activation response simulated after real server committed');}
    $count=(int)$db->query('SELECT COUNT(*) FROM license_activations')->fetchColumn();
    $recovery=$newAgent('recovery');$result=$recovery->recover($f['recovery']['activation_code']);
    $check($result['recovered']&&!$result['identity_changed']&&(int)$db->query('SELECT COUNT(*) FROM license_activations')->fetchColumn()===$count,'signed recovery after restart keeps installation and slot count');
    $repeat=ControlLicenseProtocol::post($origin,$captured[0],$captured[1],$captured[2],$s.'/tls.crt');
    $check($repeat['http']===409&&$repeat['json']['code']==='replay_detected','recovery nonce cannot be replayed');
    $bad=ControlLicenseProtocol::post($origin,$captured[0],$captured[1],['X-Namua-Timestamp: '.gmdate(DATE_ATOM),'X-Namua-Nonce: '.bin2hex(random_bytes(24)),'X-Namua-Signature: '.base64_encode(random_bytes(64))],$s.'/tls.crt');
    $check($bad['http']===401,'activation code alone cannot recover another installation');
    $check(strpos($issuer(),'issued=1')!==false && $recovery->poll()['verified'],'recovered poll credential receives issued lease');
    // The server state can reach RESTRICTED after a long offline interval; renewal must resume online.
    $id=(int)$db->query('SELECT id FROM license_activations WHERE instance_id='.(int)$f['primary']['record_id'])->fetchColumn();
    $db->exec("UPDATE license_documents SET expires_at=DATE_SUB(NOW(),INTERVAL 2 DAY),grace_until=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE activation_id={$id} AND status='CURRENT'");
    $primary->poll();
    $check($db->query('SELECT status FROM license_activations WHERE id='.$id)->fetchColumn()==='RESTRICTED','real status endpoint enters restricted for expired server lease');
    // Issuer timestamps are second precision. Avoid two different payloads with the same issued_at.
    usleep(1100000);
    $check(strpos($issuer(),'refreshed=1')!==false,'eligible restricted activation can renew after reconnect');
    $check($primary->poll()['status']==='ACTIVE','renewed genuine lease returns Finance to active');
    $db->exec("UPDATE license_activations SET status='REVOKED',revoked_at=NOW() WHERE id={$id}");
    $check($primary->poll()['status']==='REVOKED','Control revocation reaches Finance cache over HTTPS');
    $state=json_decode((string)file_get_contents($s.'/primary-private/agent.json'),true);
    $secret=base64_decode($state['secret_key_base64'],true);$payload=ControlLicenseProtocol::activation($identity,sodium_crypto_sign_publickey_from_secretkey($secret),$state['fingerprint'],$f['primary']['activation_code']);
    $req=ControlLicenseProtocol::recovery($payload,$secret,time(),bin2hex(random_bytes(24)));sodium_memzero($secret);
    $denied=ControlLicenseProtocol::post($origin,ControlLicenseProtocol::RECOVER_PATH,$req['body'],$req['headers'],$s.'/tls.crt');
    $check($denied['http']===403&&(int)$db->query('SELECT COUNT(*) FROM license_activations')->fetchColumn()===$count,'recovery cannot revive revoked license or consume another slot');
    // Eligibility is checked again by the worker, not only by the activation endpoint.
    $db->exec("UPDATE subscriptions SET status='SUSPENDED' WHERE id=".(int)$f['subscription_id']);
    $before=(int)$db->query('SELECT COUNT(*) FROM license_documents')->fetchColumn();
    $issuer();$check((int)$db->query('SELECT COUNT(*) FROM license_documents')->fetchColumn()===$before,'inactive subscription is not issued a fresh license');
    $db->exec("UPDATE subscriptions SET status='ACTIVE' WHERE id=".(int)$f['subscription_id']);
    $receipt=['status'=>'PASS','checks'=>count($checks),'passed'=>$checks,'scope'=>'actual Control source HTTPS + actual issuer, isolated database and keys','operational_customer_activation'=>false,'at'=>date(DATE_ATOM)];
    file_put_contents($s.'/license-http-acceptance.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo 'All '.count($checks)." Control HTTPS checks passed.\n";
}catch(Throwable $e){file_put_contents($s.'/license-http-failure.json',json_encode(['passed'=>$checks,'reason'=>$e->getMessage()]));fwrite(STDERR,$e->getMessage()."\n");exit(1);}
