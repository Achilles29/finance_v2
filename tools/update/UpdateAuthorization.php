<?php
declare(strict_types=1);

/** Control/Finance preflight contract. PREFLIGHT_ONLY never authorizes code or SQL changes. */
final class UpdateAuthorization
{
    public const PURPOSE='FINANCE_CUSTOMER_UPDATE_V1';
    public const SCOPE='PREFLIGHT_ONLY';
    public const EXECUTION_PURPOSE='FINANCE_CUSTOMER_UPDATE_APPLY_V1';
    public const EXECUTION_SCOPE='APPLY_UPDATE';
    public static function releaseBinding(array $context): array
    {
        $out=[];
        foreach(['release_public_id','version','source_commit','artifact_sha256','release_manifest_sha256','source_manifest_sha256','profile_sha256','distribution_profile_version'] as $k) {
            if(!isset($context[$k]))throw new RuntimeException('UPDATE_RELEASE_BINDING_INCOMPLETE');
            $out[$k]=$context[$k];
        }
        if(!self::matches('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/D',$out['release_public_id'])
            ||!self::matches('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/D',$out['version'])
            ||!self::matches('/\A[a-f0-9]{40}\z/D',$out['source_commit'])
            ||!is_int($out['distribution_profile_version'])||!in_array($out['distribution_profile_version'],[8,9,10,11],true))throw new RuntimeException('UPDATE_RELEASE_BINDING_INVALID');
        foreach(['artifact_sha256','release_manifest_sha256','source_manifest_sha256','profile_sha256']as$key)
            if(!self::matches('/\A[a-f0-9]{64}\z/D',$out[$key]))throw new RuntimeException('UPDATE_RELEASE_BINDING_INVALID');
        return $out;
    }
    public static function digest(array $data): string
    {
        $sort=static function(array $a)use(&$sort):array{ksort($a);foreach($a as &$v)if(is_array($v))$v=$sort($v);return $a;};
        return hash('sha256',json_encode($sort($data),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    private static function matches(string $pattern,$value): bool {return is_string($value)&&preg_match($pattern,$value)===1;}
    public static function verify(array $envelope,array $trust,array $current,array $target,array $identity,int $now): array
    {
        return self::verifyFor($envelope,$trust,$current,$target,$identity,$now,self::PURPOSE,self::SCOPE);
    }
    public static function verifyExecution(array $envelope,array $trust,array $current,array $target,array $identity,int $now): array
    {
        return self::verifyFor($envelope,$trust,$current,$target,$identity,$now,self::EXECUTION_PURPOSE,self::EXECUTION_SCOPE);
    }
    private static function verifyFor(array $envelope,array $trust,array $current,array $target,array $identity,int $now,string $purpose,string $scope): array
    {
        if(($trust['status']??'')!=='ACTIVE'||($trust['algorithm']??'')!=='Ed25519'||($trust['product_code']??'')!=='NAMUA_FINANCE'
            ||isset($trust['secret_key_base64'])||isset($trust['private_key']))throw new RuntimeException('UPDATE_TRUST_INVALID');
        foreach(['key_id','payload_base64','signature_base64']as$key)if(!is_string($envelope[$key]??null))throw new RuntimeException('UPDATE_SIGNATURE_INVALID');
        if(count($envelope)!==3||strlen($envelope['payload_base64'])>22000||strlen($envelope['signature_base64'])>100
            ||!is_string($trust['public_key_base64']??null))throw new RuntimeException('UPDATE_SIGNATURE_INVALID');
        $pk=base64_decode($trust['public_key_base64'],true);
        $raw=base64_decode($envelope['payload_base64'],true);
        $sig=base64_decode($envelope['signature_base64'],true);
        if(!is_string($pk)||strlen($pk)!==32||($trust['public_key_sha256']??'')!==hash('sha256',$pk)
            ||!is_string($raw)||strlen($raw)>16384||!is_string($sig)||strlen($sig)!==64
            ||($envelope['key_id']??null)!==($trust['key_id']??null)
            ||!sodium_crypto_sign_verify_detached($sig,$purpose."\n".hash('sha256',$raw),$pk))throw new RuntimeException('UPDATE_SIGNATURE_INVALID');
        try{$p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new RuntimeException('UPDATE_AUTHORIZATION_INVALID');}
        if(!is_array($p)||array_diff(array_keys($p),['purpose','product_code','authorization_id','nonce','scope','issued_at','expires_at','identity','machine_fingerprint_sha256','from','to','migration_policy'])
            ||($p['purpose']??null)!==$purpose||($p['product_code']??null)!=='NAMUA_FINANCE'
            ||!self::matches('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/D',$p['authorization_id']??null)||!self::matches('/\A[a-f0-9]{64}\z/D',$p['nonce']??null)
            ||!is_array($p['identity']??null)||!is_array($p['from']??null)||!is_array($p['to']??null)
            ||!is_int($p['issued_at']??null)||!is_int($p['expires_at']??null)||$p['issued_at']<1||$p['issued_at']>$now+300||$p['expires_at']<=$p['issued_at']
            ||$p['expires_at']-$p['issued_at']>86400)throw new RuntimeException('UPDATE_AUTHORIZATION_INVALID');
        if(($p['scope']??null)!==$scope)throw new RuntimeException('UPDATE_SCOPE_NOT_SUPPORTED');
        if($p['expires_at']<=$now)throw new RuntimeException('UPDATE_AUTHORIZATION_EXPIRED_REISSUE_SAME_ATTEMPT');
        // A short-lived update credential is not an installation deadline. Setup policy is unchanged.
        foreach(['instance_id','installation_id','instance_public_key_sha256'] as $k)if(!is_string($identity[$k]??null)||$identity[$k]===''||($p['identity'][$k]??null)!==$identity[$k])throw new RuntimeException('UPDATE_IDENTITY_MISMATCH');
        if(count($p['identity'])!==3||!preg_match('/\A[a-f0-9]{64}\z/D',$identity['instance_public_key_sha256']))throw new RuntimeException('UPDATE_IDENTITY_MISMATCH');
        if(($p['machine_fingerprint_sha256']??null)!==($current['machine_fingerprint_sha256']??null)
            ||!is_string($p['machine_fingerprint_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/D',$p['machine_fingerprint_sha256']))throw new RuntimeException('UPDATE_FINGERPRINT_MISMATCH');
        if(self::digest($p['from'])!==self::digest(self::releaseBinding($current))||self::digest($p['to'])!==self::digest(self::releaseBinding($target))
            ||($p['migration_policy']??null)!=='upgrade')throw new RuntimeException('UPDATE_RELEASE_MISMATCH');
        if($current['release_public_id']===$target['release_public_id']||$current['release_manifest_sha256']===$target['release_manifest_sha256']||version_compare($target['version'],$current['version'],'<='))throw new RuntimeException('UPDATE_FORWARD_VERSION_REQUIRED');
        return $p;
    }
}
