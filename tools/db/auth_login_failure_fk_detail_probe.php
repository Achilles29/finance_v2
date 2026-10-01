<?php

declare(strict_types=1);

define('A55_SCHEMA_FINGERPRINT_LIBRARY_ONLY', true);
require __DIR__ . '/schema_fingerprint_probe.php';

/* Dedicated metadata-read account: schema/table-only SELECT and REFERENCES;
 * no global, DDL, DML, FILE, LOCK, EXECUTE, or GRANT privileges. Probe mode
 * requires a fixed operator acknowledgement; this does not claim to verify
 * effective server grants. */
const A57_PERMISSION_PROFILE_ACKNOWLEDGEMENT = 'metadata-read';

function a57_detail_sql(): string
{
    $sourceTable = "(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND TABLE_TYPE='BASE TABLE')";
    $sourceInnoDb = "(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND TABLE_TYPE='BASE TABLE' AND ENGINE='InnoDB')";
    $targetTable = "(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_user' AND TABLE_TYPE='BASE TABLE')";
    $targetInnoDb = "(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_user' AND TABLE_TYPE='BASE TABLE' AND ENGINE='InnoDB')";
    $sourceColumn = "(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND COLUMN_NAME='user_id')";
    $targetColumn = "(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_user' AND COLUMN_NAME='id')";
    $typeCompatible = "(SELECT COUNT(*) FROM information_schema.COLUMNS f JOIN information_schema.COLUMNS u ON u.TABLE_SCHEMA=f.TABLE_SCHEMA AND u.TABLE_NAME='auth_user' AND u.COLUMN_NAME='id' WHERE f.TABLE_SCHEMA=DATABASE() AND f.TABLE_NAME='auth_login_failure' AND f.COLUMN_NAME='user_id' AND f.DATA_TYPE='bigint' AND f.COLUMN_TYPE LIKE '%unsigned' AND f.IS_NULLABLE='YES' AND (f.COLUMN_DEFAULT IS NULL OR UPPER(f.COLUMN_DEFAULT)='NULL') AND f.EXTRA='' AND u.DATA_TYPE=f.DATA_TYPE AND LOWER(u.COLUMN_TYPE)=LOWER(f.COLUMN_TYPE) AND u.IS_NULLABLE='NO')";
    $targetUnique = "(SELECT COUNT(*) FROM (SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_user' AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MAX(SEQ_IN_INDEX=1 AND COLUMN_NAME='id')=1) a57_unique_id)";
    $sourceIndex = "(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='user_id')";
    $namedExact = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='auth_login_failure' AND k.CONSTRAINT_NAME='fk_auth_login_failure_user' AND k.COLUMN_NAME='user_id' AND k.REFERENCED_TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME='auth_user' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL' AND r.UPDATE_RULE IN ('RESTRICT','NO ACTION'))";
    $namedAny = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user' AND REFERENCED_TABLE_NAME IS NOT NULL)";
    $namedKcuExact = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='auth_user' AND REFERENCED_COLUMN_NAME='id')";
    $namedRules = "(SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user')";
    $competing = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME IS NOT NULL AND CONSTRAINT_NAME<>'fk_auth_login_failure_user')";
    return "SELECT CONCAT('__A57_DETAIL__\\t'," . implode(",'\\t',", [$sourceTable,$sourceInnoDb,$targetTable,$targetInnoDb,$sourceColumn,$targetColumn,$typeCompatible,$targetUnique,$sourceIndex,$namedExact,$namedAny,$namedKcuExact,$namedRules,$competing]) . ")";
}

function a57_reasons(array $marker): array
{
    if (count($marker) !== 15) a5_fail('malformed_output', 'FK detail metadata output is malformed.');
    $values = [];
    foreach (array_slice($marker, 1) as $value) {
        if (!is_string($value) || preg_match('/^(0|[1-9][0-9]{0,5})$/', $value) !== 1) a5_fail('malformed_output', 'FK detail metadata output is malformed.');
        $values[] = (int)$value;
    }
    [$sourceTable,$sourceInnoDb,$targetTable,$targetInnoDb,$sourceColumn,$targetColumn,$typeCompatible,$targetUnique,$sourceIndex,$namedExact,$namedAny,$namedKcuExact,$namedRules,$competing] = $values;
    if ($sourceTable !== 1 || $targetTable !== 1 || $sourceColumn !== 1 || $targetColumn !== 1) return ['metadata_incomplete'];
    if ($sourceInnoDb !== 1) return ['source_not_innodb'];
    if ($targetInnoDb !== 1) return ['target_not_innodb'];
    if ($typeCompatible !== 1) return ['user_id_type_mismatch'];
    if ($targetUnique < 1) return ['target_id_not_unique'];
    if ($sourceIndex < 1) return ['source_index_missing'];
    if ($namedAny > 0 && ($namedAny !== 1 || $namedKcuExact !== 1)) return ['named_fk_wrong_contract'];
    if ($competing > 0) return ['competing_fk'];
    if ($namedAny === 1 && $namedRules === 0) return ['permission_visibility_limited'];
    if ($namedAny > 0 && ($namedExact !== 1 || $namedRules !== 1)) return ['named_fk_wrong_contract'];
    return [];
}

function a57_probe(string $optionFile, string $databaseName): array
{
    $sql = a57_detail_sql();
    if (!a55_safe_select($sql)) a5_fail('unsafe_probe', 'FK detail probe SELECT is unsafe.');
    $client = a5_client_open(a5_find_client(), $optionFile, $databaseName, microtime(true) + a5_apply_timeout_seconds());
    try {
        a5_client_send($client, $sql . ';');
        return ['reasons'=>a57_reasons(a5_client_marker($client, '__A57_DETAIL__'))];
    } finally { a5_client_close($client, false); }
}

if (defined('A57_FK_DETAIL_LIBRARY_ONLY') && A57_FK_DETAIL_LIBRARY_ONLY) return;

try {
    $args=$argv??[];
    foreach($args as$arg)if(is_string($arg)&&preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i',$arg))a5_fail('credential_cli','Credential command-line arguments are forbidden.');
    $mode=$args[1]??'';
    if($mode==='validate'&&count($args)===2){if(!a55_safe_select(a57_detail_sql()))a5_fail('unsafe_probe','FK detail probe SELECT is unsafe.');a5_emit(['status'=>'ok','mode'=>'validate','probe'=>'auth_login_failure_fk_detail','read_only'=>true,'permission_profile_acknowledgement_required'=>A57_PERMISSION_PROFILE_ACKNOWLEDGEMENT]);exit(0);}
    if($mode!=='probe'||!in_array(count($args),[4,5],true)||strpos($args[2]??'','--defaults-extra-file=')!==0||strpos($args[3]??'','--database-name-file=')!==0)a5_fail('usage','Use validate or probe with --defaults-extra-file, --database-name-file, and the permission-profile acknowledgement in that order.');
    if(count($args)!==5||$args[4]!=='--permission-profile-acknowledgement='.A57_PERMISSION_PROFILE_ACKNOWLEDGEMENT)a5_fail('permission_profile_acknowledgement_invalid','Dedicated metadata-read permission profile acknowledgement is required.');
    $root=realpath(dirname(__DIR__,2));if($root===false)a5_fail('root_missing','Repository root is unavailable.');
    $option=a5_assert_apply_security($root,substr($args[2],22));$databaseName=a5_read_database_name($root,substr($args[3],21));
    a5_emit(['status'=>'ok','mode'=>'probe','permission_profile_acknowledgement'=>A57_PERMISSION_PROFILE_ACKNOWLEDGEMENT]+a57_probe($option,$databaseName));
}catch(A5MigrationFailure$error){fwrite(STDERR,json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);}
