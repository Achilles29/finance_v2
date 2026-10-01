<?php
declare(strict_types=1);
// Test helper only, never bundled or used with Control live credentials.
$f=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$root=$f['root'];
require $root.'/tools/install/portable/SetupService.php';
$transport=static function(string $origin,string $path,string $body,array $headers)use($f,$root):array{
    if($origin!=='https://control.example.invalid')throw new RuntimeException('TEST_ORIGIN_INVALID');
    $p=json_decode($body,true);
    if($path===ControlLicenseProtocol::REQUEST_PATH){
        if(!empty($f['quota_denied']))return ['http'=>409,'json'=>['code'=>'instance_limit_exceeded']];
        return ['http'=>202,'json'=>['status'=>'PENDING','activation_id'=>'00000000-0000-4000-8000-000000000099','poll_token'=>'nlp_'.str_repeat('a',48)]];
    }
    if($path===ControlLicenseProtocol::POLL_PATH) {
        if(!empty($f['revoked'])) return ['http'=>403,'json'=>['code'=>'license_revoked']];
        $agent=(new PortableStore($root,'private/agent'))->read('agent.json');$id=$agent['identity'];
        $issued=$f['issued'];
        $manifest=json_decode(file_get_contents($root.'/app-manifest.json'),true,64,JSON_THROW_ON_ERROR);
        $types=array_column($manifest['features'],'value_type','code');$entitlements=[];
        $edition=$f['edition']??'STARTER_POS';
        foreach($manifest['editions'] as $ed) if($ed['code']===$edition) foreach($ed['features'] as $k=>$v) $entitlements[$k]=$types[$k]==='BOOLEAN'?($v==='1'):(int)$v;
        if(isset($f['entitlements']))$entitlements=$f['entitlements'];
        $payload=$id+['schema'=>1,'license_id'=>'disposable-portable','key_id'=>$f['trust']['key_id'],'product'=>'NAMUA_FINANCE',
            'machine_fingerprint_sha256'=>CustomerPlatform::fingerprint(),'edition'=>$edition,'metric'=>'SERVER_INSTANCE',
            'rights_model'=>'PERPETUAL','entitlements'=>$entitlements,'issued_at'=>gmdate(DATE_ATOM,$issued),
            'expires_at'=>gmdate(DATE_ATOM,$issued+3600),'grace_until'=>gmdate(DATE_ATOM,$issued+7200),'maintenance_ends_at'=>gmdate(DATE_ATOM,$issued-86400)];
        $raw=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $e=['schema'=>1,'algorithm'=>'Ed25519','key_id'=>$f['trust']['key_id'],'payload_base64'=>base64_encode($raw),
            'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n".hash('sha256',$raw),base64_decode($f['issuer'],true)))];
        return ['http'=>200,'json'=>['status'=>'ACTIVE','license'=>$e,'token_sha256'=>hash('sha256',json_encode($e,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))]];
    }
    if(in_array($path,['/api/v1/deployment-receipts','/api/v1/heartbeats'],true)) {
        $head=[];foreach($headers as $h){[$k,$v]=explode(': ',$h,2);$head[$k]=$v;}
        $c=implode("\n",['POST',$path,$p['instance_id'],$head['X-Namua-Key-ID'],$head['X-Namua-Timestamp'],$head['X-Namua-Nonce'],$head['Idempotency-Key'],hash('sha256',$body)]);
        if(!hash_equals(hash_hmac('sha256',$c,$f['monitoring_secret']),$head['X-Namua-Signature']))throw new RuntimeException('TEST_HMAC_INVALID');
        if($path==='/api/v1/deployment-receipts' && ($p['status']??'')!=='SUCCEEDED')throw new RuntimeException('TEST_RECEIPT_INVALID');
        if($path==='/api/v1/heartbeats' && (($p['runtime']['primary_domain']??'')!=='127.0.0.1'||$p['health']!=='OK'))throw new RuntimeException('TEST_HEARTBEAT_INVALID');
        return ['http'=>202,'json'=>['status'=>'accepted']];
    }
    throw new RuntimeException('TEST_UNKNOWN_CONTROL_PATH');
};
try {
    $i=new PortableInstaller($root,$transport);$mode=$argv[2];
    if($mode==='tick')$r=(new SetupService($root,$transport))->tick();
    elseif($mode==='config')$r=DeploymentConfig::forRoot($root)->validateProductionSecretContract();
    elseif($mode==='verify')$r=PortablePackage::verify($root)['version'];
    elseif($mode==='permit')$r=PortablePackage::permit($root,(new PortableStore($root,'private'))->read('delivery-state.json')['context'])['permit_id'];
    elseif($mode==='guard'){$v=Control_license_cache::customer_verification($root,$root.'/storage/customer-installation.json');$r=['verified'=>$v['verified'],'status'=>$v['status'],'code'=>$v['code']];}
    elseif($mode==='update'){
        require $root.'/tools/update/UpdateService.php';
        $updateTransport=static function($origin,$path,$signed,$destination)use($f,$root):array{
            if($origin!=='https://control.example.invalid'||$path!==UpdateTransport::PATH)throw new RuntimeException('FIXTURE_UPDATE_ORIGIN');
            $p=json_decode($signed['body'],true,32,JSON_THROW_ON_ERROR);
            $agent=(new PortableStore($root,'private/agent'))->read('agent.json');
            $headers=[];foreach($signed['headers']as$line){[$key,$v]=explode(': ',$line,2);$headers[$key]=$v;}
            $canonical=implode("\n",['POST',$path,$p['activation_id'],$headers['X-Namua-Timestamp'],$headers['X-Namua-Nonce'],hash('sha256',$signed['body'])]);
            if(!sodium_crypto_sign_verify_detached(base64_decode($headers['X-Namua-Signature']),$canonical,sodium_crypto_sign_publickey_from_secretkey(base64_decode($agent['secret_key_base64']))))throw new RuntimeException('FIXTURE_UPDATE_AUTH');
            if($p['action']==='poll')return $f['update_offer'];
            if($p['action']==='artifact'){
                if($destination===null||!str_starts_with($destination,$root.'/private/'))throw new RuntimeException('FIXTURE_UPDATE_DOWNLOAD_PATH');
                UpdateFiles::write($root,$destination,file_get_contents($f['update_tar']));return ['http'=>200,'sha256'=>hash_file('sha256',$destination)];
            }
            if($p['action']==='receipt'){
                require_once $f['control_root'].'/application/libraries/Update_agent_protocol.php';
                Update_agent_protocol::receipt($p['receipt'],$f['update_offer']['plan']);
                (new PortableStore($root,'private'))->write('test-receipt-'.strtolower($p['receipt']['phase']).'.json',$p['receipt']);
                return ['status'=>'accepted'];
            }
            throw new RuntimeException('FIXTURE_UPDATE_ACTION');
        };
        $r=(new UpdateService($root,$updateTransport))->tick();
    }
    elseif($mode==='db')$r=PortableDatabase::install($root,$f['database'],$f['owner'],$f['release_hash']);
    elseif($mode==='interrupt')$r=PortableDatabase::install($root,$f['database'],$f['owner'],$f['release_hash'],static function(int $n):void{if($n===1){echo json_encode(['ok'=>true,'result'=>'PROCESS_EXIT_AT_DURABLE_CHECKPOINT'])."\n";exit(0);}});
    else $r=$i->$mode();
    echo json_encode(['ok'=>true,'result'=>$r],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){echo json_encode(['ok'=>false,'code'=>preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'FIXTURE_FAILURE','class'=>get_class($e)])."\n";exit(1);}
