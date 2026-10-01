<?php
declare(strict_types=1);
require_once __DIR__.'/UpdateAuthorization.php';
require_once dirname(__DIR__).'/install/portable/PortablePackage.php';
if(!defined('A5_MIGRATION_LIBRARY_ONLY'))define('A5_MIGRATION_LIBRARY_ONLY',true);
require_once dirname(__DIR__).'/db/migration_runner.php';

/** Read-only Finance half of a future updater. Deliberately has NO install/activate/write/SQL entry point. */
final class UpdatePreflight
{
    public static function inspect(string $currentRoot,string $candidateRoot,array $authorization): array
    {
        if(PHP_SAPI!=='cli'||$currentRoot===$candidateRoot||realpath($currentRoot)!==$currentRoot||realpath($candidateRoot)!==$candidateRoot
            ||str_starts_with($currentRoot.'/',$candidateRoot.'/'))throw new RuntimeException('UPDATE_SEPARATE_CANDIDATE_REQUIRED');
        // Both release signatures, TAR, inventory and profile are checked by the installed trusted verifier.
        // No PHP from the candidate is evaluated. Its public trust must equal the current independently trusted key.
        $current=PortablePackage::verify($currentRoot);$next=PortablePackage::verify($candidateRoot);
        if($current['release_trust']!==$next['release_trust'])throw new RuntimeException('UPDATE_TRUST_ROTATION_REQUIRES_REVIEW');
        $installed=Control_license_cache::customer_context($currentRoot,$currentRoot.'/storage/customer-installation.json');
        $decision=Control_license_cache::customer_verification($currentRoot,$currentRoot.'/storage/customer-installation.json');
        if(!($decision['verified']??false)||!in_array($decision['status']??'',['ACTIVE','GRACE'],true))throw new RuntimeException('UPDATE_LICENSE_RECOVERY_REQUIRED');
        $identity=$installed['identity'];
        $permit=UpdateAuthorization::verify($authorization,$current['release_trust'],$current,$next,$identity,time());
        $old=json_decode(file_get_contents($currentRoot.'/RELEASE-MANIFEST.json'),true,64,JSON_THROW_ON_ERROR)['files'];
        $new=json_decode(file_get_contents($candidateRoot.'/RELEASE-MANIFEST.json'),true,64,JSON_THROW_ON_ERROR)['files'];
        $old=array_column($old,'sha256','path');$new=array_column($new,'sha256','path');$changes=[];
        foreach($new as $path=>$hash)if(($old[$path]??null)!==$hash)$changes[]=$path;
        $retire=array_values(array_diff(array_keys($old),array_keys($new)));sort($changes);sort($retire);
        $catalog=a5_validate_catalog($candidateRoot);
        return ['status'=>'PREFLIGHT_CHECKED_APPLY_BLOCKED','contract'=>UpdateAuthorization::PURPOSE,'scope'=>UpdateAuthorization::SCOPE,
            'authorization_id'=>$permit['authorization_id'],'from'=>$permit['from'],'to'=>$permit['to'],
            'changed_code'=>$changes,'retired_code'=>$retire,
            'upgrade_candidates'=>array_column(a5_plan($catalog,'upgrade'),'id'),
            'database_action'=>'NONE_PREFLIGHT_ONLY_LEDGER_AND_SCHEMA_REVIEW_REQUIRED',
            'preserve'=>['config/customer.json','private/','storage/','public/uploads/','public/assets/uploads/','customer_database'],
            'activation_action'=>'NONE_EXISTING_INSTALLATION_ID_AND_SLOT',
            'blockers'=>['AUTHENTICATED_AGENT_TRANSPORT_AND_RECEIPT','OLD_LAUNCHER_MAINTENANCE_AND_ATOMIC_SWITCH_ADAPTER','QUIESCED_BACKUP_AND_RESTORE_DRILL','UPGRADE_APPLY_WORKER'],
            'warning'=>'Jangan menjalankan clean installer atau menimpa folder aktif. Izin ini belum dapat digunakan untuk mengganti kode.'];
    }
}
