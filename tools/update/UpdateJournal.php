<?php
declare(strict_types=1);
require_once __DIR__.'/UpdateAuthorization.php';
require_once dirname(__DIR__).'/licensing/LicenseStateStore.php';

/** Persistent preflight replay protection. Cannot activate, execute migrations or replace code. */
final class UpdateJournal
{
    public const FILE='update-journal.json';
    public function __construct(private LicenseStateStore $store) {}

    public static function accept(array $state,array $verifiedPermit,int $now): array
    {
        if(($verifiedPermit['scope']??'')!==UpdateAuthorization::SCOPE)throw new RuntimeException('UPDATE_SCOPE_NOT_SUPPORTED');
        if(!$state)$state=['schema'=>1,'observed_at'=>0,'attempts'=>[]];
        if(($state['schema']??null)!==1||!is_int($state['observed_at']??null)||!is_array($state['attempts']??null))throw new RuntimeException('UPDATE_JOURNAL_INVALID');
        if($now<$state['observed_at']-300)throw new RuntimeException('UPDATE_CLOCK_ROLLBACK');
        $id=$verifiedPermit['authorization_id'];$material=$verifiedPermit;unset($material['issued_at'],$material['expires_at']);
        $hash=UpdateAuthorization::digest($material);$attempt=$state['attempts'][$id]??null;
        foreach($state['attempts']as$oldId=>$old)if(($old['nonce']??'')===$verifiedPermit['nonce']&&$oldId!==$id)throw new RuntimeException('UPDATE_NONCE_REPLAY');
        if($attempt!==null){
            if(($attempt['plan_sha256']??'')!==$hash)throw new RuntimeException('UPDATE_ATTEMPT_BINDING_CHANGED');
            if($verifiedPermit['issued_at']<($attempt['credential_issued_at']??0))throw new RuntimeException('UPDATE_CREDENTIAL_ROLLBACK');
            if(!in_array($attempt['phase']??'',['CHECKING','ATTENTION','CHECKED'],true))throw new RuntimeException('UPDATE_JOURNAL_INVALID');
        }else{
            if(count($state['attempts'])>=256)throw new RuntimeException('UPDATE_JOURNAL_REVIEW_REQUIRED');
            $attempt=['nonce'=>$verifiedPermit['nonce'],'plan_sha256'=>$hash,'phase'=>'CHECKING','events'=>[]];
        }
        $attempt['credential_issued_at']=$verifiedPermit['issued_at'];
        $state['attempts'][$id]=$attempt;$state['observed_at']=max($now,$state['observed_at']);
        return $state;
    }

    public function run(array $envelope,array $trust,array $current,array $target,array $identity,callable $inspect,?int $now=null): array
    {
        $now=$now??time();$permit=UpdateAuthorization::verify($envelope,$trust,$current,$target,$identity,$now);
        $lock=$this->store->lock();
        try{
            $state=self::accept($this->store->exists(self::FILE)?$this->store->read(self::FILE):[],$permit,$now);
            $id=$permit['authorization_id'];$attempt=$state['attempts'][$id];
            // Refresh credential changes neither identity nor checkpoints; old credentials cannot rewind it.
            $this->store->write(self::FILE,$state,0600);
            if($attempt['phase']==='CHECKED')return $attempt['result']+['cached'=>true];
            if(count($attempt['events'])>=100)throw new RuntimeException('UPDATE_JOURNAL_REVIEW_REQUIRED');
            $state['attempts'][$id]['phase']='CHECKING';$this->store->write(self::FILE,$state,0600);
            try{
                $result=$inspect();
                if(($result['status']??'')!=='PREFLIGHT_CHECKED_APPLY_BLOCKED'||($result['scope']??'')!==UpdateAuthorization::SCOPE
                    ||($result['authorization_id']??'')!==$id||($result['database_action']??'')!=='NONE_PREFLIGHT_ONLY_LEDGER_AND_SCHEMA_REVIEW_REQUIRED')throw new RuntimeException('UPDATE_PREFLIGHT_RESULT_INVALID');
                $state['attempts'][$id]['phase']='CHECKED';$state['attempts'][$id]['result']=$result;
                $state['attempts'][$id]['events'][]=['at'=>$now,'code'=>'PREFLIGHT_CHECKED_APPLY_BLOCKED'];
                $this->store->write(self::FILE,$state,0600);return $result+['cached'=>false];
            }catch(Throwable $e){
                $code=preg_match('/\A[A-Z][A-Z_]{3,100}\z/D',$e->getMessage())?$e->getMessage():'UPDATE_PREFLIGHT_FAILED';
                $state['attempts'][$id]['phase']='ATTENTION';$state['attempts'][$id]['events'][]=['at'=>$now,'code'=>$code];
                $this->store->write(self::FILE,$state,0600);throw new RuntimeException($code);
            }
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
