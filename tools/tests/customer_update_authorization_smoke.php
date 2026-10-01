<?php
declare(strict_types=1);
require dirname(__DIR__).'/update/UpdateAuthorization.php';
$n=0;$check=static function(bool $ok,string $label)use(&$n):void{if(!$ok)throw new RuntimeException($label);$n++;};
$kp=sodium_crypto_sign_keypair();$pk=sodium_crypto_sign_publickey($kp);$sk=sodium_crypto_sign_secretkey($kp);
$trust=['status'=>'ACTIVE','algorithm'=>'Ed25519','product_code'=>'NAMUA_FINANCE','key_id'=>'fixture','public_key_base64'=>base64_encode($pk),'public_key_sha256'=>hash('sha256',$pk)];
$from=['version'=>'0.1.0-alpha.20','source_commit'=>str_repeat('a',40),'release_public_id'=>'00000000-0000-4000-8000-000000000020','artifact_sha256'=>str_repeat('a',64),'release_manifest_sha256'=>str_repeat('b',64),'source_manifest_sha256'=>str_repeat('c',64),'profile_sha256'=>str_repeat('d',64),'distribution_profile_version'=>8,'machine_fingerprint_sha256'=>str_repeat('e',64)];
$to=array_replace($from,['release_public_id'=>'00000000-0000-4000-8000-000000000022','version'=>'0.1.0-alpha.22','source_commit'=>str_repeat('b',40),'release_manifest_sha256'=>str_repeat('f',64),'distribution_profile_version'=>10]);
$identity=['instance_id'=>'fixture-instance','installation_id'=>'00000000-0000-4000-8000-000000000001','instance_public_key_sha256'=>hash('sha256',$pk)];
$p=['purpose'=>UpdateAuthorization::PURPOSE,'product_code'=>'NAMUA_FINANCE','authorization_id'=>'00000000-0000-4000-8000-000000000022','nonce'=>bin2hex(random_bytes(32)),'scope'=>UpdateAuthorization::SCOPE,'issued_at'=>1800000000,'expires_at'=>1800003600,'identity'=>$identity,'machine_fingerprint_sha256'=>$from['machine_fingerprint_sha256'],'from'=>UpdateAuthorization::releaseBinding($from),'to'=>UpdateAuthorization::releaseBinding($to),'migration_policy'=>'upgrade'];
$sign=static function(array $v)use($sk):array{$raw=json_encode($v,JSON_THROW_ON_ERROR);return ['key_id'=>'fixture','payload_base64'=>base64_encode($raw),'signature_base64'=>base64_encode(sodium_crypto_sign_detached(UpdateAuthorization::PURPOSE."\n".hash('sha256',$raw),$sk))];};
$check(UpdateAuthorization::verify($sign($p),$trust,$from,$to,$identity,1800000001)===$p,'synthetic signed exact update authorization accepted');
$reject=static function(array $doc,string $code)use($sign,$trust,$from,$to,$identity,$check):void{try{UpdateAuthorization::verify($sign($doc),$trust,$from,$to,$identity,1800000001);$check(false,'Expected '.$code);}catch(RuntimeException $e){$check($e->getMessage()===$code,$e->getMessage());}};
foreach(['instance_id','installation_id','instance_public_key_sha256']as$k){$bad=$p;$bad['identity'][$k]='wrong';$reject($bad,'UPDATE_IDENTITY_MISMATCH');}
foreach(['from','to']as$side)foreach(array_keys($p[$side])as$k){$bad=$p;$bad[$side][$k]=$k==='distribution_profile_version'?999:'wrong';$reject($bad,'UPDATE_RELEASE_MISMATCH');}
$bad=$p;$bad['machine_fingerprint_sha256']=str_repeat('0',64);$reject($bad,'UPDATE_FINGERPRINT_MISMATCH');
$bad=$p;$bad['migration_policy']='clean_install';$reject($bad,'UPDATE_RELEASE_MISMATCH');
$bad=$p;$bad['expires_at']=1800000001;$reject($bad,'UPDATE_AUTHORIZATION_EXPIRED_REISSUE_SAME_ATTEMPT');
$bad=$p;$bad['package']='ENTERPRISE';$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
$forged=$sign($p);$forged['signature_base64']=base64_encode(random_bytes(64));
try{UpdateAuthorization::verify($forged,$trust,$from,$to,$identity,1800000001);$check(false,'forged signature');}catch(RuntimeException$e){$check($e->getMessage()==='UPDATE_SIGNATURE_INVALID','forged signature denied');}
foreach(['REVOKED','RETIRED',''] as $status){
 try{UpdateAuthorization::verify($sign($p),array_replace($trust,['status'=>$status]),$from,$to,$identity,1800000001);$check(false,'untrusted update issuer');}
 catch(RuntimeException $e){$check($e->getMessage()==='UPDATE_TRUST_INVALID','inactive issuer denied');}
}
$bad=$p;$bad['nonce']='';$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
$bad=$p;$bad['purpose']='FINANCE_INSTALL';$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
$bad=$p;$bad['scope']='APPLY';$reject($bad,'UPDATE_SCOPE_NOT_SUPPORTED');
$bad=$p;unset($bad['scope']);$reject($bad,'UPDATE_SCOPE_NOT_SUPPORTED');
$bad=$p;$bad['expires_at']=$p['issued_at']+86401;$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
$bad=$p;$bad['identity']='not-an-object';$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
$bad=$p;$bad['authorization_id']=str_repeat('-',36);$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
$reordered=$p;$reordered['to']=array_reverse($p['to'],true);
$check(UpdateAuthorization::verify($sign($reordered),$trust,$from,$to,$identity,1800000001)===$reordered,'object key order does not change release identity');
$bad=$p;$bad['issued_at']=1800001000;$reject($bad,'UPDATE_AUTHORIZATION_INVALID');
foreach(['0.1.0-alpha.19','0.1.0-alpha.20'] as $version){
 $back=array_replace($to,['version'=>$version]);$bad=$p;$bad['to']=UpdateAuthorization::releaseBinding($back);
 try{UpdateAuthorization::verify($sign($bad),$trust,$from,$back,$identity,1800000001);$check(false,'non-forward update');}
 catch(RuntimeException $e){$check($e->getMessage()==='UPDATE_FORWARD_VERSION_REQUIRED','rollback/downgrade requires separate reviewed recovery, not update permission');}
}
$source=file_get_contents(dirname(__DIR__).'/update/UpdatePreflight.php');
$check(!preg_match('/\b(?:file_put_contents|rename|unlink|a5_apply|proc_open)\s*\(/',$source),'preflight cannot switch code, run SQL, delete, or spawn activation');
echo json_encode(['status'=>'PASS','checks'=>$n,'contract'=>'CONTROL_FINANCE_PREFLIGHT_ONLY','apply_supported'=>false,'database_accessed'=>false])."\n";
