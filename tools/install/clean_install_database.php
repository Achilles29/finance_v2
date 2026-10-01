<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/release/ControlReleaseBridge.php';
if (!defined('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY')) define('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/db/post_install_health_check.php';
if (!defined('FINANCE_A512_OWNER_BOOTSTRAP_LIBRARY_ONLY')) define('FINANCE_A512_OWNER_BOOTSTRAP_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/db/bootstrap_first_owner.php';
require_once __DIR__.'/CustomerDatabase.php';

/** Installs a verified release into an already provisioned EMPTY database, never upgrades or wipes one. */
function c3InstallEmptyDatabase(array $marker): void
{
    if ($marker !== ['__C3_EMPTY__','0']) throw new RuntimeException('DATABASE_NOT_EMPTY');
}

function c3InstallDatabaseVersion(array $marker, string $signedContract): void
{
    $rule = ControlReleaseBridge::databaseRule($signedContract);
    if (count($marker) !== 2 || $marker[0] !== '__C3_VERSION__' || !is_string($marker[1])
        || preg_match('/\A(?:5\.5\.5-)?(\d+\.\d+\.\d+)-MariaDB(?:[-+].*)?\z/iD', $marker[1], $match) !== 1
        || !version_compare($match[1], $rule['minimum'], '>=')
        || !version_compare($match[1], $rule['maximum_exclusive'], '<')) throw new RuntimeException('DATABASE_RUNTIME_UNSUPPORTED');
}

function c3InstallDatabase(array $o): array
{
    if (CustomerLocalConfig::present($o['release-root'])) {
        if (isset($o['defaults-extra-file']) || isset($o['database-name-file'])) {
            throw new RuntimeException('CUSTOMER_CONFIG_DUPLICATE_DATABASE_INPUT');
        }
        return CustomerDatabase::withConnection($o['release-root'], static function(array $connection,array $settings,string $private) use($o): array {
            $lock=PrivateDeployment::lock($private);
            try {
                if (file_exists($private.'/database-attempt.json') || is_link($private.'/database-attempt.json')) {
                    throw new RuntimeException('CUSTOMER_INSTALL_PREVIOUS_ATTEMPT');
                }
                return c3InstallDatabaseVerified($o+$connection,$private.'/database-attempt.json');
            } finally { flock($lock,LOCK_UN); fclose($lock); }
        });
    }
    if (!isset($o['defaults-extra-file'],$o['database-name-file'])) throw new RuntimeException('CUSTOMER_CONFIG_REQUIRED');
    return c3InstallDatabaseVerified($o);
}

function c3InstallDatabaseVerified(array $o, ?string $journal = null): array
{
    $toolRoot = dirname(__DIR__, 2);
    $option = a5_assert_apply_security($toolRoot, $o['defaults-extra-file']);
    $database = a5_read_database_name($toolRoot, $o['database-name-file']);
    $owner = a512_owner_file($toolRoot, $o['owner-file']);
    $path = financeArtifactSignatureRegularFile($o['signed-manifest'], 'MANIFEST_UNSAFE');
    if (substr($path, -13) !== '.release.json' || filesize($path) > 1048576) throw new RuntimeException('MANIFEST_INVALID');
    $bytes = (string)file_get_contents($path); $manifest = ControlReleaseBridge::json($bytes);
    $name = ControlReleaseBridge::artifactName($manifest);
    if (!is_string($name) || basename($name) !== $name || strpos($name, '..') !== false) throw new RuntimeException('ARTIFACT_NAME');
    $sigPath = financeArtifactSignatureRegularFile(substr($path, 0, -13) . '.release.sig.json', 'SIGNATURE_UNSAFE');
    if (filesize($sigPath) > 16384) throw new RuntimeException('SIGNATURE_SIZE');
    $verification = ControlReleaseBridge::verify($bytes, basename($path), ControlReleaseBridge::json((string)file_get_contents($sigPath)),
        ControlReleaseBridge::loadKey($o['trust-file']), dirname($path) . '/' . $name);
    if (empty($verification['customer_clean_eligible'])) throw new RuntimeException('CUSTOMER_PROFILE_REQUIRED');
    $manifest = $verification['install_manifest'];
    $release = a513_validate_release($o['release-root'], $o['release-root'] . '/RELEASE-MANIFEST.json');
    if ($release['manifest_sha256'] !== $manifest['source_manifest_sha256']) throw new RuntimeException('SIGNED_SOURCE_MISMATCH');
    $expected = array_merge(array_column($release['manifest']['files'], 'path'), ['RELEASE-MANIFEST.json']); sort($expected);
    if (CustomerLocalConfig::present($release['root'])) {
        $profile=CustomerReleaseProfile::fromRoot($release['root']);
        if ($profile->version()<5) throw new RuntimeException('CUSTOMER_LOCAL_PROFILE_REQUIRED');
        CustomerLocalConfig::read($release['root']);
        $expected[]=CustomerLocalConfig::PATH;
        sort($expected);
    }
    $actual = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($release['root'], FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isLink()) throw new RuntimeException('INSTALL_SOURCE_SYMLINK');
        if ($file->isFile()) $actual[] = substr($file->getPathname(), strlen($release['root']) + 1);
    }
    sort($actual);
    if ($actual !== $expected) throw new RuntimeException('INSTALL_SOURCE_EXTRA_FILES');
    if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80200) throw new RuntimeException('PHP_RUNTIME_UNSUPPORTED');
    // Hold a dedicated connection/lock across baseline, migrations, owner bootstrap and health check.
    $client = a5_client_open(a5_find_client(), $option, $database, microtime(true) + 600);
    try {
        $lock = 'finance_clean_install_' . substr(hash('sha256', $database), 0, 40);
        a5_client_send($client, "SELECT CONCAT('__C3_LOCK__\\t',IF(GET_LOCK(" . a5_sql_value($lock) . ",0)=1,'1','0')); ");
        if (a5_client_marker($client, '__C3_LOCK__') !== ['__C3_LOCK__','1']) throw new RuntimeException('INSTALL_LOCK_UNAVAILABLE');
        a5_client_send($client, "SELECT CONCAT('__C3_EMPTY__\\t',COUNT(*)) FROM information_schema.tables WHERE table_schema=DATABASE();");
        c3InstallEmptyDatabase(a5_client_marker($client, '__C3_EMPTY__'));
        a5_client_send($client, "SELECT CONCAT('__C3_VERSION__\\t',VERSION());");
        $version = a5_client_marker($client, '__C3_VERSION__');
        c3InstallDatabaseVersion($version, $manifest['runtime']['database']);
        if ($journal!==null) PrivateDeployment::write($journal,['status'=>'STARTED','at'=>gmdate(DATE_ATOM),
            'release_root'=>$release['root'],'source_commit'=>$manifest['source_commit'],
            'artifact_sha256'=>$manifest['sha256'],'database_sha256'=>hash('sha256',$database),
            'recovery'=>'Inspect this attempt and the existing tables. Never auto-replay SQL after partial installation.']);
        $policy = ControlReleaseBridge::json((string)file_get_contents($release['root'] . '/tools/db/clean_install_baseline_policy.json'));
        a5_client_send($client, (string)file_get_contents($release['root'] . '/' . $policy['schema']['path']) . "\nSELECT '__C3_BASELINE_DONE__';\n");
        if (a5_client_marker($client, '__C3_BASELINE_DONE__') !== ['__C3_BASELINE_DONE__']) throw new RuntimeException('BASELINE_FAILED');
        // Execute the trusted runner implementation against the verified release catalog and SQL.
        $catalog = a5_validate_catalog($release['root']);
        $migrations = a5_plan($catalog, 'clean_install');
        $applied = a5_apply($catalog, $release['root'], 'clean_install', $option, $database);
        $ownerResult = a512_bootstrap_owner($release['root'], $option, $database, $owner);
        unset($owner);
        $health = a513_check_database($release, 'clean_install', $option, $database);
        if ($journal!==null) {
            $attempt=PrivateDeployment::read($journal);$attempt['status']='COMPLETE';$attempt['completed_at']=gmdate(DATE_ATOM);
            PrivateDeployment::write($journal,$attempt);
        }
        return ['status' => 'PASS', 'mode' => 'clean_install_database', 'version' => $manifest['version'],
            'database_server_version' => $version[1], 'database_contract' => $manifest['runtime']['database'],
            'artifact_sha256' => $manifest['sha256'], 'source_commit' => $manifest['source_commit'],
            'managed_migrations' => count($migrations), 'applied' => $applied, 'owner_id' => $ownerResult['user_id'],
            'health' => $health, 'web_deployed' => false, 'published' => false];
    } finally { a5_client_close($client, true); }
}

if (defined('C3_CLEAN_INSTALL_LIBRARY_ONLY')) return;
try {
    if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'apply') throw new RuntimeException('CLI_APPLY_REQUIRED');
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (!preg_match('/\A--(release-root|signed-manifest|trust-file|defaults-extra-file|database-name-file|owner-file)=(.+)\z/D', $arg, $m) || isset($options[$m[1]])) throw new RuntimeException('USAGE');
        $options[$m[1]] = $m[2];
    }
    foreach (['release-root','signed-manifest','trust-file','owner-file'] as $required) {
        if (!isset($options[$required])) throw new RuntimeException('USAGE');
    }
    echo json_encode(c3InstallDatabase($options), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    $reason = property_exists($e, 'failureCode') ? $e->failureCode : $e->getMessage();
    fwrite(STDERR, json_encode(['status' => 'BLOCKED', 'code' => preg_match('/\A[A-Za-z_]+\z/D', $reason) ? $reason : 'INSTALL_FAILED',
        'recovery' => 'Keep files and database for inspection. This command never wipes or rolls back DDL automatically.']) . "\n");
    exit(1);
}
