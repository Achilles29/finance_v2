<?php
declare(strict_types=1);

/** Local HTTPS issuer fixture, ephemeral keys/artifacts only. No Control source or database writes. */
function c3DeliveryRuntimeCases(array $manifest,array $key,array $trust,string $archive,array $profile,callable $check): void
{
    if(posix_geteuid()!==0)throw new RuntimeException('ROOT_REQUIRED_FOR_PRIVATE_STATE_TEST');
    $base='/var/lib/finance-delivery-test-'.bin2hex(random_bytes(8));mkdir($base,0700);chmod($base,0700);
    $pid=null;$listener=null;
    $remove=static function(string $path)use(&$remove,$base):void{
        if(!str_starts_with($path.'/',$base.'/'))throw new RuntimeException('TEST_CLEANUP_BOUNDARY');
        if(is_file($path)||is_link($path)){unlink($path);return;}
        foreach(scandir($path)?:[] as $f)if($f!=='.'&&$f!=='..')$remove($path.'/'.$f);rmdir($path);
    };
    $reject=static function(callable $call,string $code,string $label)use($check):void{
        try{$call();}catch(RuntimeException $e){$check($e->getMessage()===$code,$label.' ('.$e->getMessage().')');return;}
        $check(false,$label);
    };
    $dir=static function(string $name)use($base):string{$p=$base.'/'.$name;mkdir($p,0700);chmod($p,0700);return $p;};
    try{
        PrivateDeployment::run(['/usr/bin/openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1','-keyout',$base.'/tls.key','-out',$base.'/tls.crt'],$base.'/tls.log');
        chmod($base.'/tls.key',0600);chmod($base.'/tls.crt',0600);PrivateDeployment::write($base.'/trust.json',$trust);
        $raw=json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $blobs=['MANIFEST'=>$raw,'OTHER'=>json_encode(ControlReleaseBridge::sign($raw,'clean.release.json',$key),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'APPLICATION_PACKAGE'=>(string)file_get_contents($archive)];
        $p=['status'=>'accepted','instance_id'=>'delivery-fixture','primary_domain'=>null,'product_code'=>'NAMUA_FINANCE','action'=>'DEPLOY','environment'=>'DEMO',
            'deployment_id'=>'00000000-0000-4000-8000-000000000009','plan_sha256'=>hash('sha256','immutable-fixture-plan'),
            'release'=>$profile+['version'=>$manifest['version'],'manifest_sha256'=>hash('sha256',$raw),'artifact_sha256'=>hash('sha256',$blobs['APPLICATION_PACKAGE'])],'artifacts'=>[]];
        foreach(['MANIFEST'=>'clean.release.json','OTHER'=>'clean.release.sig.json','APPLICATION_PACKAGE'=>'clean.tar'] as $type=>$filename)$p['artifacts'][]=['type'=>$type,'filename'=>$filename,'sha256'=>hash('sha256',$blobs[$type]),'size_bytes'=>strlen($blobs[$type])];
        $context=stream_context_create(['ssl'=>['local_cert'=>$base.'/tls.crt','local_pk'=>$base.'/tls.key','verify_peer'=>false]]);
        $listener=stream_socket_server('tls://127.0.0.1:0',$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$context);
        if(!$listener)throw new RuntimeException('TLS_FIXTURE_FAILED');$address=stream_socket_get_name($listener,false);
        $pid=pcntl_fork();if($pid<0)throw new RuntimeException('FORK_FAILED');
        if($pid===0){
            $claims=[];$downloads=[];
            while($client=@stream_socket_accept($listener,20)){
                stream_set_timeout($client,5);$line=fgets($client);$length=0;
                while(($h=fgets($client))!==false&&trim($h)!==''){if(stripos($h,'Content-Length:')===0)$length=(int)trim(substr($h,15));}
                $body='';while(strlen($body)<$length){$chunk=fread($client,$length-strlen($body));if($chunk===false||$chunk==='')break;$body.=$chunk;}
                $payload=json_decode($body,true)?:[];$token=$payload['install_token']??'';$type=$payload['artifact_type']??'';
                $code=200;$out='';$plan=$p;
                if(!in_array($token,array_map(static fn(string $letter):string=>'ndi_'.str_repeat($letter,48),['B','C','D']),true)){$code=401;$out=json_encode(['code'=>'install_token_invalid']);}
                elseif(str_contains((string)$line,'/claim ')){
                    if(isset($claims[$token])){$code=401;$out=json_encode(['code'=>'install_token_invalid']);}
                    else{$claims[$token]=true;if($token==='ndi_'.str_repeat('D',48))$plan['primary_domain']='changed.example.invalid';$out=json_encode($plan);}
                }elseif(str_contains((string)$line,'/artifact ')){
                    if(!isset($claims[$token])||isset($downloads[$token][$type])||$token==='ndi_'.str_repeat('C',48)){$code=401;$out=json_encode(['code'=>'artifact_token_invalid']);}
                    else{$downloads[$token][$type]=true;$out=$blobs[$type]??'';}
                }else{$code=404;$out='{}';}
                $answer="HTTP/1.1 $code Fixture\r\nContent-Length: ".strlen($out)."\r\nConnection: close\r\n\r\n".$out;
                while($answer!==''){$n=@fwrite($client,$answer);if(!$n)break;$answer=substr($answer,$n);}fclose($client);
            }
            exit(0);
        }
        fclose($listener);$listener=null;
        $config=['control_origin'=>'https://'.$address,'install_token'=>'ndi_'.str_repeat('A',48),'instance_id'=>$p['instance_id'],'primary_domain'=>'customer-selected.example.invalid','ca_file'=>$base.'/tls.crt'];
        $first=$dir('expired');$client=new ControlDelivery($first,$config);
        $invalid=$config;$invalid['install_token']='not-a-token';
        $reject(fn()=>new ControlDelivery($first,$invalid),'DELIVERY_CONFIG_INVALID','malformed delivery token is rejected locally');
        $unknown=$config;$unknown['install_token']='ndi_'.str_repeat('Z',48);
        $reject(fn()=>(new ControlDelivery($dir('unknown-token'),$unknown))->fetch($base.'/trust.json'),'INSTALL_TOKEN_REJECTED','unknown credential denied by HTTPS issuer fixture');
        $reject(fn()=>$client->fetch($base.'/trust.json'),'INSTALL_TOKEN_REJECTED','expired/invalid/used response is actionable over verified HTTPS');
        $before=file_get_contents($first.'/delivery.json');
        $reject(fn()=>$client->fetch($base.'/trust.json'),'INSTALL_TOKEN_REJECTED','rejected credential is not retried');
        $bad=$config;$bad['instance_id']='another-instance';$second=$dir('replacement');
        $reject(fn()=>$client->replacement($second,$bad),'REPLACEMENT_BINDING_MISMATCH','replacement cannot change instance');
        $reject(fn()=>$client->replacement($second,$config),'REPLACEMENT_TOKEN_REQUIRED','same consumed token cannot become replacement');
        $next=$config;$next['install_token']='ndi_'.str_repeat('B',48);unset($next['primary_domain']);
        $check($client->replacement($second,$next)['database_changed']===false,'replacement prepares downloads only, with no SQL');
        $check(file_get_contents($first.'/delivery.json')===$before,'old failed journal remains byte-identical');
        $secondClient=new ControlDelivery($second,$next);$result=$secondClient->fetch($base.'/trust.json');
        $check($result['status']==='VERIFIED'&&$result['installed']===false,'replacement downloads and verifies actual signed CUSTOMER_CLEAN TAR');
        $reject(fn()=>$secondClient->replacement($dir('reuse-old-token'),$config),'REPLACEMENT_TOKEN_REQUIRED','credential from earlier attempt cannot return through a replacement chain');
        $used=$dir('used-token');$usedConfig=$next;$usedConfig['primary_domain']='';
        $reject(fn()=>(new ControlDelivery($used,$usedConfig))->fetch($base.'/trust.json'),'INSTALL_TOKEN_REJECTED','already claimed token remains rejected by HTTPS issuer fixture');
        $next['primary_domain']='changed-again.example.invalid';
        $check((new ControlDelivery($second,$next))->fetch($base.'/trust.json')['status']==='VERIFIED','domain change permits local resume without reclaiming consumed token');
        $tampered=$next;$tampered['install_token']='ndi_'.str_repeat('X',48);
        $reject(fn()=>(new ControlDelivery($second,$tampered))->fetch($base.'/trust.json'),'DELIVERY_STATE_MISMATCH','token binding still rejects direct overwrite');
        $partial=$dir('partial');$partialConfig=$config;$partialConfig['install_token']='ndi_'.str_repeat('C',48);
        $partialClient=new ControlDelivery($partial,$partialConfig);
        $reject(fn()=>$partialClient->fetch($base.'/trust.json'),'INSTALL_TOKEN_REJECTED','expired artifact download has token recovery message, not false checksum error');
        $check(is_file($partial.'/clean.release.json.partial'),'partial evidence retained');
        $reject(fn()=>$partialClient->fetch($base.'/trust.json'),'INSTALL_TOKEN_REJECTED','restart retains actionable artifact rejection without retry');
        $fourth=$dir('resume-download');$fresh=$partialConfig;$fresh['install_token']='ndi_'.str_repeat('D',48);
        $partialClient->replacement($fourth,$fresh);
        $check((new ControlDelivery($fourth,$fresh))->fetch($base.'/trust.json')['status']==='VERIFIED','replacement retains deployment/profile binding while registry domain changes');
        foreach(['deployment_id','plan_sha256','release'] as $field){
            $target=$dir('wrong-'.$field);$partialClient->replacement($target,$fresh);
            $transport=static function()use($p,$field):array{$bad=$p;$bad[$field]=$field==='release'?array_replace($p['release'],['version'=>'99.0.0']):($field==='deployment_id'?'00000000-0000-4000-8000-000000000008':str_repeat('e',64));return ['http'=>200,'json'=>$bad];};
            $reject(fn()=>(new ControlDelivery($target,$fresh,$transport))->fetch($base.'/trust.json'),'REPLACEMENT_PLAN_MISMATCH','replacement rejects changed '.$field);
        }
        $state=PrivateDeployment::read($second.'/delivery.json');$state['schema']=1;$state['binding']=hash('sha256',json_encode($next,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));PrivateDeployment::write($second.'/delivery.json',$state);
        $check((new ControlDelivery($second,$next))->fetch($base.'/trust.json')['status']==='VERIFIED','legacy journal upgrades only with exact original credentials');
        $next['primary_domain']=null;$check((new ControlDelivery($second,$next))->fetch($base.'/trust.json')['status']==='VERIFIED','upgraded legacy journal no longer binds domain');
        $c=['private_dir'=>$dir('instance')];
        PrivateDeployment::write($c['private_dir'].'/instance.json',['status'=>'INSTALLING','config_sha256'=>hash('sha256',json_encode($c))]);
        $probe=financeArtifactSignatureRun([PHP_BINARY,'-r',
            'require $argv[1]; $r=new ReflectionClass(FinanceInstance::class);$i=$r->newInstanceWithoutConstructor();$p=$r->getProperty("c");$p->setAccessible(true);$p->setValue($i,["private_dir"=>$argv[2]]);try{$i->install();exit(1);}catch(RuntimeException $e){echo $e->getMessage()."\n";}try{c3InstallEmptyDatabase(["__C3_EMPTY__","1"]);exit(1);}catch(RuntimeException $e){echo $e->getMessage()."\n";}',
            dirname(__DIR__).'/install/FinanceInstance.php',$c['private_dir']]);
        $check($probe['code']===0&&trim($probe['stdout'])==="INSTALL_ALREADY_ATTEMPTED\nDATABASE_NOT_EMPTY",'new delivery credential cannot replay uncertain install or bypass nonempty database guard');
    }finally{
        if($listener)fclose($listener);if($pid>0){posix_kill($pid,SIGTERM);pcntl_waitpid($pid,$status);}$remove($base);
    }
}
