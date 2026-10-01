<?php

declare(strict_types=1);

define('A55_SCHEMA_FINGERPRINT_LIBRARY_ONLY', true);
require __DIR__ . '/schema_fingerprint_probe.php';

/*
 * Operator contract: probe mode requires a dedicated metadata-read account with
 * schema/table-only SELECT and REFERENCES, and no global, DDL, DML, FILE, LOCK,
 * EXECUTE, or GRANT privileges. The mandatory CLI acknowledgement records the
 * operator assertion; it does not claim to verify effective server grants.
 */
const A56_PERMISSION_PROFILE_ACKNOWLEDGEMENT = 'metadata-read';

function a56_structure_sql(): string
{
    $tables = "(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('auth_login_failure','auth_user') AND TABLE_TYPE='BASE TABLE' AND ENGINE='InnoDB')=2";
    $columns = "(SELECT COUNT(*) FROM information_schema.COLUMNS f JOIN information_schema.COLUMNS u ON u.TABLE_SCHEMA=f.TABLE_SCHEMA AND u.TABLE_NAME='auth_user' AND u.COLUMN_NAME='id' WHERE f.TABLE_SCHEMA=DATABASE() AND f.TABLE_NAME='auth_login_failure' AND f.COLUMN_NAME='user_id' AND f.DATA_TYPE='bigint' AND f.COLUMN_TYPE LIKE '%unsigned' AND f.IS_NULLABLE='YES' AND (f.COLUMN_DEFAULT IS NULL OR UPPER(f.COLUMN_DEFAULT)='NULL') AND f.EXTRA='' AND u.DATA_TYPE=f.DATA_TYPE AND LOWER(u.COLUMN_TYPE)=LOWER(f.COLUMN_TYPE) AND u.IS_NULLABLE='NO')=1";
    $sourceIndex = "(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='user_id')>=1";
    $targetIndex = "(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_user' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='id' AND NON_UNIQUE=0)>=1";
    $named = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='auth_login_failure' AND k.CONSTRAINT_NAME='fk_auth_login_failure_user' AND k.COLUMN_NAME='user_id' AND k.REFERENCED_TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME='auth_user' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL' AND r.UPDATE_RULE IN ('RESTRICT','NO ACTION'))";
    $namedAny = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user' AND REFERENCED_TABLE_NAME IS NOT NULL)";
    $namedKcuExact = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='auth_user' AND REFERENCED_COLUMN_NAME='id')";
    $namedRules = "(SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND CONSTRAINT_NAME='fk_auth_login_failure_user')";
    $competing = "(SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_login_failure' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME IS NOT NULL AND CONSTRAINT_NAME<>'fk_auth_login_failure_user')";
    return "SELECT CONCAT('__A56_STRUCTURE__\\t',IF(" . $tables . " AND " . $columns . " AND " . $sourceIndex . " AND " . $targetIndex . ",'1','0'),'\\t'," . $named . ",'\\t'," . $namedAny . ",'\\t'," . $namedKcuExact . ",'\\t'," . $namedRules . ",'\\t'," . $competing . ")";
}

function a56_orphan_sql(): string
{
    return "SELECT CONCAT('__A56_ORPHANS__\\t',COUNT(*)) FROM auth_login_failure f LEFT JOIN auth_user u ON u.id=f.user_id WHERE f.user_id IS NOT NULL AND u.id IS NULL";
}

function a56_send_select(array &$client, string $sql): void
{
    if (!a55_safe_select($sql)) a5_fail('unsafe_probe', 'Forensic probe SELECT is unsafe.');
    a5_client_send($client, $sql . ';');
}

function a56_probe(string $optionFile, string $databaseName): array
{
    $client = a5_client_open(a5_find_client(), $optionFile, $databaseName, microtime(true) + a5_apply_timeout_seconds());
    try {
        a56_send_select($client, a56_structure_sql());
        $structure = a5_client_marker($client, '__A56_STRUCTURE__');
        if (count($structure) !== 7 || !in_array($structure[1], ['0','1'], true) || count(array_filter(array_slice($structure,2), 'ctype_digit')) !== 5) {
            a5_fail('malformed_output', 'Forensic structure output is malformed.');
        }
        $named = (int)$structure[2];
        $namedAny = (int)$structure[3];
        $namedKcuExact = (int)$structure[4];
        $namedRules = (int)$structure[5];
        $competing = (int)$structure[6];
        if ($structure[1] !== '1' || $namedAny > 1 || $namedKcuExact !== $namedAny || $competing > 0) return ['state'=>'structural_incompatible'];
        if ($namedAny === 1 && $namedRules === 0) return ['state'=>'permission_visibility_limited'];
        if ($named > 1 || $namedRules > 1 || ($namedAny === 1 && $named !== 1) || ($namedAny === 0 && $namedRules !== 0)) return ['state'=>'structural_incompatible'];
        if ($named === 1) return ['state'=>'already_present'];

        a56_send_select($client, a56_orphan_sql());
        $orphans = a5_client_marker($client, '__A56_ORPHANS__');
        if (count($orphans) !== 2 || preg_match('/^(0|[1-9][0-9]{0,17})$/', $orphans[1]) !== 1) {
            a5_fail('malformed_output', 'Forensic orphan output is malformed.');
        }
        $count = (int)$orphans[1];
        return ['state'=>$count === 0 ? 'missing_fk_clean' : 'missing_fk_orphan','orphan_count'=>$count];
    } finally {
        a5_client_close($client, false);
    }
}

if (defined('A56_FK_PROBE_LIBRARY_ONLY') && A56_FK_PROBE_LIBRARY_ONLY) return;

try {
    $args = $argv ?? [];
    foreach ($args as $arg) if (is_string($arg) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $arg)) a5_fail('credential_cli', 'Credential command-line arguments are forbidden.');
    $mode = $args[1] ?? '';
    if ($mode === 'validate' && count($args) === 2) {
        if (!a55_safe_select(a56_structure_sql()) || !a55_safe_select(a56_orphan_sql())) a5_fail('unsafe_probe', 'Forensic probe SELECT is unsafe.');
        a5_emit(['status'=>'ok','mode'=>'validate','probe'=>'auth_login_failure_fk','read_only'=>true,'permission_profile_acknowledgement_required'=>A56_PERMISSION_PROFILE_ACKNOWLEDGEMENT]); exit(0);
    }
    if ($mode !== 'probe' || !in_array(count($args), [4, 5], true) || strpos($args[2] ?? '', '--defaults-extra-file=') !== 0 || strpos($args[3] ?? '', '--database-name-file=') !== 0) {
        a5_fail('usage', 'Use validate or probe with --defaults-extra-file, --database-name-file, and the permission-profile acknowledgement in that order.');
    }
    if (count($args) !== 5 || $args[4] !== '--permission-profile-acknowledgement='.A56_PERMISSION_PROFILE_ACKNOWLEDGEMENT) a5_fail('permission_profile_acknowledgement_invalid', 'Dedicated metadata-read permission profile acknowledgement is required.');
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) a5_fail('root_missing', 'Repository root is unavailable.');
    $optionFile = a5_assert_apply_security($root, substr($args[2], 22));
    $databaseName = a5_read_database_name($root, substr($args[3], 21));
    a5_emit(['status'=>'ok','mode'=>'probe','permission_profile_acknowledgement'=>A56_PERMISSION_PROFILE_ACKNOWLEDGEMENT] + a56_probe($optionFile, $databaseName));
} catch (A5MigrationFailure $error) {
    fwrite(STDERR, json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL); exit(1);
}
