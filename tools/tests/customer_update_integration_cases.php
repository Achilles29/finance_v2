<?php
// Included only by the disposable acceptance harness, after a complete signed synthetic installation.
require $source.'/tools/update/UpdateDatabase.php';
require dirname($source).'/control/application/libraries/Update_authorization_signer.php';
$current=json_decode(file_get_contents($root.'/storage/customer-installation.json'),true);
$oldIdentity=hash_file('sha256',$root.'/private/agent/agent.json');$oldLicense=hash_file('sha256',$root.'/storage/license/runtime.json');
$oldConfig=hash_file('sha256',$root.'/config/customer.json');
$customer=new PDO('mysql:unix_socket='.$socket.';dbname=portable_customer','portable_user',$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$customer->exec("CREATE TABLE update_customer_sentinel (id INT PRIMARY KEY, value LONGBLOB, nullable_value TEXT NULL) ENGINE=InnoDB");
$q=$customer->prepare('INSERT INTO update_customer_sentinel VALUES (?,?,?)');$sentinel="Customer binary\0\xff\n'\\ record";$q->execute([1,$sentinel,null]);
$ownerBefore=$customer->query('SELECT * FROM auth_user ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$target=$base.'/next';mkdir($target,0750);chown($target,$owner);chgrp($target,$group);
$nextEntries=[];
foreach($entries as$entry){$path=$entry['path'];$bytes=file_get_contents($root.'/'.$path);
    if($path==='app-manifest.json'){$v=json_decode($bytes,true);$v['version']='0.1.0-alpha.24';$bytes=json_encode($v,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";}
    $write($target.'/'.$path,$bytes,0644);$nextEntries[]=['path'=>$path,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes),'mode'=>'0644'];
}
$json($target.'/RELEASE-MANIFEST.json',['schema'=>'finance.release-artifact-manifest','schema_version'=>1,'source_epoch'=>1700000100,'files'=>$nextEntries],0644);
$nextTar=$base.'/next.tar';$r=$run(['/usr/bin/tar','--create','--format=gnu','--owner=0','--group=0','--numeric-owner','--no-recursion','-C',$target,'-T',$list,'-f',$nextTar]);
$check($r['code']===0,'separate synthetic next release built');$write($nextTar,file_get_contents($nextTar),0640);
$nextWire=$wire;$nextWire['version']='0.1.0-alpha.24';$nextWire['release_public_id']='00000000-0000-4000-8000-000000000024';$nextWire['source_commit']=str_repeat('b',40);
$nextWire['size_bytes']=filesize($nextTar);$nextWire['sha256']=hash_file('sha256',$nextTar);$nextWire['source_manifest_sha256']=hash_file('sha256',$target.'/app-manifest.json');
$nextWire['customer_content_audit']['artifact_sha256']=$nextWire['sha256'];$nextWire['customer_content_audit']['source_manifest_sha256']=hash_file('sha256',$target.'/RELEASE-MANIFEST.json');
$nextRaw=json_encode($nextWire,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$nextBinding=Application_update::fromManifest($nextWire,hash('sha256',$nextRaw));
$plan=Application_update::executionPlan(Application_update::plan('00000000-0000-4000-8000-000000000100',bin2hex(random_bytes(32)),$current['identity'],$current['machine_fingerprint_sha256'],$current,$nextBinding));
$fixture['update_tar']=$nextTar;$fixture['control_root']=dirname($source).'/control';
$fixture['update_offer']=['status'=>'AVAILABLE','plan'=>$plan,'plan_sha256'=>Application_update::hash($plan),
    'manifest_base64'=>base64_encode($nextRaw),'signature'=>ControlReleaseBridge::sign($nextRaw,'package.release.json',$trust+['secret_key_base64'=>base64_encode($sk)]),
    'authorization'=>Update_authorization_signer::sign($plan,$trust+['secret_key_base64'=>base64_encode($sk)],$trust,time())];$json($base.'/fixture.json',$fixture);
$r=$worker('update');$check($r['ok']&&($r['result']['phase']??'')==='READY','authenticated download/extract/signature offers update without applying: '.json_encode($r));
$check(hash_file('sha256',$root.'/config/customer.json')===$oldConfig&&hash_file('sha256',$root.'/private/agent/agent.json')===$oldIdentity,'download preserves config and activation');
$check($http('/login')['status']===200,'download does not stop current website');
$json($root.'/storage/inbox/update-'.Application_update::hash($plan).'.json',['plan_sha256'=>Application_update::hash($plan),'confirmed'=>true],0660);
$r=$worker('update');$check($r['ok']&&($r['result']['phase']??'')==='COMPLETE','confirmed update backup/SQL/code/receipt finishes: '.json_encode($r));
$check(hash_file('sha256',$root.'/config/customer.json')===$oldConfig&&hash_file('sha256',$root.'/private/agent/agent.json')===$oldIdentity&&hash_file('sha256',$root.'/storage/license/runtime.json')===$oldLicense,'upgrade preserves DB configuration, activation key and license bytes');
$check($customer->query('SELECT value FROM update_customer_sentinel WHERE id=1')->fetchColumn()===$sentinel,'business binary row preserved');
$check($customer->query('SELECT * FROM auth_user ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$ownerBefore,'existing administrator not recreated or replaced');
$check($http('/login')['status']===200,'updated website opens through real HTTPS/FPM');
$check($worker('verify')['ok']&&$worker('guard')['result']['verified'],'new full package and license verification pass');
$job=json_decode(file_get_contents($root.'/private/update-job.json'),true);$pdo->exec('CREATE DATABASE update_restore_drill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$restore=new PDO('mysql:unix_socket='.$socket.';dbname=update_restore_drill','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
UpdateDatabase::restoreEmpty($restore,$job['directory'].'/database-backup.ndjson',$job['backup']['sha256']);
$check($restore->query('SELECT value FROM update_customer_sentinel WHERE id=1')->fetchColumn()===$sentinel,'actual backup restores binary customer data into separate empty database');
$check($restore->query('SELECT * FROM auth_user ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$ownerBefore,'backup restore retains existing owner');
try{UpdateDatabase::restoreEmpty($restore,$job['directory'].'/database-backup.ndjson',$job['backup']['sha256']);$check(false,'nonempty recovery target denied');}catch(RuntimeException $e){$check($e->getMessage()==='DATABASE_NOT_EMPTY','recovery refuses nonempty database');}
$fixture['update_offer']=['status'=>'NO_UPDATE'];$json($base.'/fixture.json',$fixture);$r=$worker('update');
$check($r['ok']&&$r['result']['phase']==='COMPLETE'&&hash_file('sha256',$root.'/private/agent/agent.json')===$oldIdentity,'completed update restart neither reinstalls nor activates');
$newContext=json_decode(file_get_contents($root.'/storage/customer-installation.json'),true);$check($newContext['version']==='0.1.0-alpha.24','runtime points to the new signed release');
$restore=null;$customer=null;
