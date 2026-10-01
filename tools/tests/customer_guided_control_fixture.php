<?php
declare(strict_types=1);
// Isolated HTTPS test server ONLY. Never bundled; no live Control data, endpoints or credentials.
ini_set('display_errors','0');header('Content-Type: application/json');
try {
    $file=$_SERVER['FINANCE_TEST_FIXTURE']??'';
    if(!str_starts_with($file,'/var/lib/finance-guided-'))throw new RuntimeException('FIXTURE_ONLY');
    $f=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);$root=$f['root'];
    require $root.'/tools/install/portable/PortableInstaller.php';
    $raw=file_get_contents('php://input');$p=json_decode($raw,true,16,JSON_THROW_ON_ERROR);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    $stateFile=dirname($file).'/control-state.json';$lock=fopen(dirname($file).'/control.lock','c');flock($lock,LOCK_EX);
    $s=is_file($stateFile)?json_decode(file_get_contents($stateFile),true):['requests'=>0,'polls'=>0,'receipts'=>0,'heartbeats'=>0];
    $status=200;$out=[];
    if($path===ControlLicenseProtocol::REQUEST_PATH){
        $s['requests']++;
        if(!empty($f['quota_denied'])){$status=409;$out=['code'=>'instance_limit_exceeded'];}
        else {
            $key=base64_decode($p['instance_public_key_base64']??'',true);
            $id=['instance_id'=>$p['instance_id'],'installation_id'=>$p['installation_id'],'instance_public_key_sha256'=>hash('sha256',$key)];
            if(ControlLicenseProtocol::activation($id,$key,$p['machine_fingerprint_sha256'],$p['activation_code'])!==$p)throw new RuntimeException('ACTIVATION_INVALID');
            if($s['requests']!==1)throw new RuntimeException('DUPLICATE_ACTIVATION');
            $s['identity']=$id;$s['pk']=base64_encode($key);$s['fingerprint']=$p['machine_fingerprint_sha256'];
            $status=202;$out=['status'=>'PENDING','activation_id'=>'00000000-0000-4000-8000-000000000099','poll_token'=>'nlp_'.str_repeat('a',48)];
        }
    }elseif($path===ControlLicenseProtocol::POLL_PATH){
        $s['polls']++;
        $canonical=implode("\n",['POST',$path,$p['activation_id'],$_SERVER['HTTP_X_NAMUA_TIMESTAMP'],$_SERVER['HTTP_X_NAMUA_NONCE'],hash('sha256',$raw)]);
        if(!sodium_crypto_sign_verify_detached(base64_decode($_SERVER['HTTP_X_NAMUA_SIGNATURE'],true),$canonical,base64_decode($s['pk'],true)))throw new RuntimeException('POLL_SIGNATURE_INVALID');
        $issued=$f['issued'];$payload=$s['identity']+['schema'=>1,'license_id'=>'disposable-guided','key_id'=>$f['trust']['key_id'],'product'=>'NAMUA_FINANCE',
            'machine_fingerprint_sha256'=>$s['fingerprint'],'edition'=>'STARTER_POS','metric'=>'SERVER_INSTANCE','rights_model'=>'PERPETUAL','entitlements'=>['BUSINESS_PROFILE'=>true,'RBAC_CORE'=>true,'POS_WEB'=>true,'POS_PRINTER'=>true,'SALES_REPORTING'=>true,'LIMIT_OUTLETS'=>1,'LIMIT_POS_TERMINALS'=>1],
            'issued_at'=>gmdate(DATE_ATOM,$issued),'expires_at'=>gmdate(DATE_ATOM,$issued+3600),'grace_until'=>gmdate(DATE_ATOM,$issued+7200),'maintenance_ends_at'=>gmdate(DATE_ATOM,$issued-86400)];
        $bytes=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $e=['schema'=>1,'algorithm'=>'Ed25519','key_id'=>$f['trust']['key_id'],'payload_base64'=>base64_encode($bytes),
            'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n".hash('sha256',$bytes),base64_decode($f['issuer'],true)))];
        $out=['status'=>'ACTIVE','license'=>$e,'token_sha256'=>hash('sha256',json_encode($e,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
    }elseif(in_array($path,['/api/v1/deployment-receipts','/api/v1/heartbeats'],true)){
        $canonical=implode("\n",['POST',$path,$p['instance_id'],$_SERVER['HTTP_X_NAMUA_KEY_ID'],$_SERVER['HTTP_X_NAMUA_TIMESTAMP'],$_SERVER['HTTP_X_NAMUA_NONCE'],$_SERVER['HTTP_IDEMPOTENCY_KEY'],hash('sha256',$raw)]);
        if(!hash_equals(hash_hmac('sha256',$canonical,$f['monitoring_secret']),$_SERVER['HTTP_X_NAMUA_SIGNATURE']))throw new RuntimeException('HMAC_INVALID');
        if($path==='/api/v1/deployment-receipts'){
            $s['receipts']++;$old=$s['receipt_id']??$p['receipt_id'];if($old!==$p['receipt_id'])throw new RuntimeException('RECEIPT_CHANGED');$s['receipt_id']=$old;
            if(($p['status']??'')!=='SUCCEEDED')throw new RuntimeException('RECEIPT_INVALID');
            // Simulate lost acknowledgement ONCE after accepting the same receipt, not an activation retry.
            $status=$s['receipts']===1?503:202;$out=['status'=>$status===202?'accepted':'unavailable'];
        }else{
            if(($p['runtime']['primary_domain']??'')!=='127.0.0.1'||$p['health']!=='OK')throw new RuntimeException('HEARTBEAT_INVALID');
            $s['heartbeats']++;$status=202;$out=['status'=>'accepted'];
        }
    }else throw new RuntimeException('UNKNOWN_PATH');
    file_put_contents($stateFile,json_encode($s,JSON_THROW_ON_ERROR));chmod($stateFile,0600);flock($lock,LOCK_UN);fclose($lock);
    http_response_code($status);echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(500);echo json_encode(['code'=>'SYNTHETIC_CONTROL_FAILURE']);}
