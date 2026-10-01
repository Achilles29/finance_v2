<?php

declare(strict_types=1);

define('A55_SCHEMA_FINGERPRINT_LIBRARY_ONLY', true);
require __DIR__ . '/schema_fingerprint_probe.php';

/* Dedicated metadata-read account: schema/table-only SELECT and REFERENCES;
 * no global, DDL, DML, FILE, LOCK, EXECUTE, or GRANT privileges. Probe mode
 * requires a fixed operator acknowledgement; this does not claim to verify
 * effective server grants. */
const A58_PERMISSION_PROFILE_ACKNOWLEDGEMENT = 'metadata-read';

function a58_named_fk_sql(): string
{
    $kcu = " FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user'";
    $ref = " FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user'";
    $metrics = [
        '(SELECT COUNT(*)'.$kcu.')',
        "(SELECT COUNT(*)".$kcu." AND COLUMN_NAME IS NOT NULL AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME IS NOT NULL AND ORDINAL_POSITION IS NOT NULL)",
        "(SELECT COUNT(*)".$kcu." AND COLUMN_NAME='user_id')",
        '(SELECT COUNT(*)'.$kcu.' AND BINARY REFERENCED_TABLE_SCHEMA<>BINARY DATABASE())',
        "(SELECT COUNT(*)".$kcu." AND REFERENCED_TABLE_NAME='auth_user')",
        "(SELECT COUNT(*)".$kcu." AND REFERENCED_COLUMN_NAME='id')",
        '(SELECT COUNT(*)'.$ref.')',
        '(SELECT COUNT(*)'.$ref.' AND DELETE_RULE IS NOT NULL AND UPDATE_RULE IS NOT NULL)',
        "(SELECT COUNT(*)".$ref." AND DELETE_RULE='SET NULL')",
        "(SELECT COUNT(*)".$ref." AND UPDATE_RULE IN ('RESTRICT','NO ACTION'))",
    ];
    return "SELECT CONCAT('__A58_NAMED_FK__\\t'," . implode(",'\\t',", $metrics) . ')';
}

function a58_named_fk_reasons(array $marker): array
{
    if (count($marker) !== 11) a5_fail('malformed_output', 'Named FK metadata output is malformed.');
    $values=[];
    foreach(array_slice($marker,1)as$value){if(!is_string($value)||preg_match('/^(0|[1-9][0-9]{0,5})$/',$value)!==1)a5_fail('malformed_output','Named FK metadata output is malformed.');$values[]=(int)$value;}
    [$rows,$completeRows,$localMatches,$crossSchema,$tableMatches,$columnMatches,$ruleRows,$completeRuleRows,$deleteMatches,$updateMatches]=$values;
    if($rows===0||$completeRows!==$rows)return['named_fk_metadata_incomplete'];
    if($localMatches!==$rows)return['named_fk_local_column_mismatch'];
    if($crossSchema>0)return['named_fk_cross_schema'];
    if($tableMatches!==$rows)return['named_fk_target_table_mismatch'];
    if($columnMatches!==$rows)return['named_fk_target_column_mismatch'];
    if($rows!==1&&$ruleRows===0)return['named_fk_multicolumn_or_ambiguous'];
    if($ruleRows===0)return['permission_visibility_limited'];
    if($completeRuleRows!==$ruleRows)return['named_fk_metadata_incomplete'];
    if($deleteMatches!==$ruleRows)return['named_fk_delete_rule_mismatch'];
    if($updateMatches!==$ruleRows)return['named_fk_update_rule_mismatch'];
    if($rows!==1||$ruleRows!==1)return['named_fk_multicolumn_or_ambiguous'];
    return[];
}

function a58_named_fk_probe(string $optionFile, string $databaseName): array
{
    $sql=a58_named_fk_sql();
    if(!a55_safe_select($sql))a5_fail('unsafe_probe','Named FK metadata probe SELECT is unsafe.');
    $client=a5_client_open(a5_find_client(),$optionFile,$databaseName,microtime(true)+a5_apply_timeout_seconds());
    try{a5_client_send($client,$sql.';');return['reasons'=>a58_named_fk_reasons(a5_client_marker($client,'__A58_NAMED_FK__'))];}
    finally{a5_client_close($client,false);}
}

if(defined('A58_NAMED_FK_LIBRARY_ONLY')&&A58_NAMED_FK_LIBRARY_ONLY)return;

try{
    $args=$argv??[];
    foreach($args as$arg)if(is_string($arg)&&preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i',$arg))a5_fail('credential_cli','Credential command-line arguments are forbidden.');
    $mode=$args[1]??'';
    if($mode==='validate'&&count($args)===2){if(!a55_safe_select(a58_named_fk_sql()))a5_fail('unsafe_probe','Named FK metadata probe SELECT is unsafe.');a5_emit(['status'=>'ok','mode'=>'validate','probe'=>'auth_login_failure_named_fk_contract','read_only'=>true,'permission_profile_acknowledgement_required'=>A58_PERMISSION_PROFILE_ACKNOWLEDGEMENT]);exit(0);}
    if($mode!=='probe'||!in_array(count($args),[4,5],true)||strpos($args[2]??'','--defaults-extra-file=')!==0||strpos($args[3]??'','--database-name-file=')!==0)a5_fail('usage','Use validate or probe with --defaults-extra-file, --database-name-file, and the permission-profile acknowledgement in that order.');
    if(count($args)!==5||$args[4]!=='--permission-profile-acknowledgement='.A58_PERMISSION_PROFILE_ACKNOWLEDGEMENT)a5_fail('permission_profile_acknowledgement_invalid','Dedicated metadata-read permission profile acknowledgement is required.');
    $root=realpath(dirname(__DIR__,2));if($root===false)a5_fail('root_missing','Repository root is unavailable.');
    $option=a5_assert_apply_security($root,substr($args[2],22));$databaseName=a5_read_database_name($root,substr($args[3],21));
    a5_emit(['status'=>'ok','mode'=>'probe','permission_profile_acknowledgement'=>A58_PERMISSION_PROFILE_ACKNOWLEDGEMENT]+a58_named_fk_probe($option,$databaseName));
}catch(A5MigrationFailure$error){fwrite(STDERR,json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);}
