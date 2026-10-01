<?php

declare(strict_types=1);

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

function a5_contract_remove(string $root, string $path): void
{
    if (strpos($path, $root) !== 0) throw new RuntimeException('Unsafe fixture cleanup.');
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') a5_contract_remove($root, $path . '/' . $entry);
    }
    @rmdir($path);
}

/** @return array{code:int,out:string,err:string} */
function a5_contract_run(string $runner, array $args): array
{
    $process = proc_open(array_merge([PHP_BINARY, $runner], $args), [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname($runner), ['PATH' => '/nonexistent', 'DB_PASSWORD' => 'A5_SECRET_TRIPWIRE']);
    if (!is_resource($process)) return ['code' => 255, 'out' => '', 'err' => 'proc_open failed'];
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string)$out, 'err' => (string)$err];
}

function a5_contract_catalog(array $migrations): array
{
    return [
        'catalog_version' => 1,
        'tool_version' => '1.0.0',
        'sql_directory' => 'sql',
        'catalog_policy' => ['active_sql_completeness' => 'managed_or_explicit_legacy', 'scan_scope' => 'top_level_sql_files_only', 'unknown_sql' => 'reject'],
        'classification_schema' => ['schema' => 'deployable', 'data' => 'deployable', 'seed' => 'deployable', 'support-repair' => 'manual-only'],
        'supported_policies' => ['clean_install', 'upgrade'],
        'legacy_unmanaged_sql' => [],
        'migrations' => $migrations,
    ];
}

function a5_contract_migration(string $id, int $order, string $path, string $contents, array $dependencies = []): array
{
    return ['id' => $id, 'order' => $order, 'path' => $path, 'sha256' => hash('sha256', $contents), 'dependencies' => $dependencies, 'classification' => 'schema', 'policies' => ['clean_install', 'upgrade']];
}

function a5_contract_fixture(string $parent, string $name, string $runnerSource): array
{
    $root = $parent . '/' . $name;
    mkdir($root . '/tools/db', 0700, true);
    mkdir($root . '/sql', 0700, true);
    $contents = ['a' => "CREATE TABLE a (id INT);\n", 'b' => "CREATE TABLE b (id INT);\n", 'c' => "CREATE TABLE c (id INT);\n"];
    foreach ($contents as $key => $sql) file_put_contents($root . '/sql/' . $key . '.sql', $sql);
    file_put_contents($root . '/tools/db/migration_runner.php', $runnerSource);
    $migrations = [
        a5_contract_migration('aaa', 10, 'sql/a.sql', $contents['a']),
        a5_contract_migration('bbb', 20, 'sql/b.sql', $contents['b'], ['aaa']),
        a5_contract_migration('ccc', 30, 'sql/c.sql', $contents['c'], ['bbb']),
    ];
    return [$root, a5_contract_catalog($migrations)];
}

function a5_contract_write_catalog(string $root, array $catalog): void
{
    file_put_contents($root . '/tools/db/migration_catalog.json', json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function a5_contract_error_code(array $result): string
{
    $decoded = json_decode(trim($result['err']), true);
    return is_array($decoded) ? (string)($decoded['code'] ?? '') : '';
}

$root = dirname(__DIR__, 2);
$runnerPath = $root . '/tools/db/migration_runner.php';
$catalogPath = $root . '/tools/db/migration_catalog.json';
$sqlPath = $root . '/sql/2026-09-04c_a5_schema_migration_registry_foundation.sql';
$runnerSource = (string)@file_get_contents($runnerPath);
$catalog = json_decode((string)@file_get_contents($catalogPath), true);
$sql = (string)@file_get_contents($sqlPath);

$check($runnerSource !== '' && is_array($catalog) && $sql !== '', 'A5.1 runner, catalog, and registry SQL are readable');
$validate = a5_contract_run($runnerPath, ['validate']);
$planA = a5_contract_run($runnerPath, ['plan', '--policy=clean_install']);
$planB = a5_contract_run($runnerPath, ['plan', '--policy=clean_install']);
$upgrade = a5_contract_run($runnerPath, ['plan', '--policy=upgrade']);
$planJson = json_decode(trim($planA['out']), true);
$upgradeJson = json_decode(trim($upgrade['out']), true);
$check($validate['code'] === 0 && $planA['code'] === 0 && $upgrade['code'] === 0, 'repository catalog validates and both DB-free policies plan successfully');
$check($planA['out'] === $planB['out'], 'plan output is byte-for-byte deterministic');
$plannedIds = array_column($planJson['migrations'] ?? [], 'id');
$check($plannedIds === ['2026-09-04c-a5-schema-migration-registry-foundation', '2026-09-05d-a5-clean-install-reference-seed', '2026-09-05e-whatsapp-safe-reference-seed', '2026-09-05a-telegram-bot-foundation', '2026-09-05b-telegram-setup-guide', '2026-09-05c-telegram-safe-activation-default', '2026-09-06a-component-formula-version-history', '2026-09-06b-component-formula-restore-action', '2026-09-06c-pos-mobile-reversal-step-up', '2026-09-06d-pos-mobile-reprint-step-up', '2026-09-06e-activity-audit-foundation', '2026-09-06f-pos-mobile-cashier-close-step-up', '2026-09-06g-pos-mobile-reservation-refund-step-up', '2026-09-06h-roastery-label-template-studio', '2026-09-06i-a3-sidebar-task-oriented-layout', '2026-09-07a-c2-c4-business-profile-license-runtime-foundation', '2026-09-12a-roast-connect-catalog', '2026-09-13a-finance-mutation-reporting-category', '2026-09-14a-finance-control-workspace', '2026-09-14b-finance-control-operations', '2026-09-14c-finance-allocation-bank-review', '2026-09-15a-finance-general-ledger', '2026-09-15b-finance-journal-assistant', '2026-09-15c-application-user-guide', '2026-09-16a-procurement-stock-review','2026-09-20a-pos-stock-commit-not-required','2026-09-23a-module-notifications'], 'clean-install plan includes the customer profile and audit-only license foundation after sidebar IA');
$check(array_column($upgradeJson['migrations'] ?? [], 'id') === ['2026-09-04c-a5-schema-migration-registry-foundation', '2026-09-05e-whatsapp-safe-reference-seed', '2026-09-05a-telegram-bot-foundation', '2026-09-05b-telegram-setup-guide', '2026-09-05c-telegram-safe-activation-default', '2026-09-06a-component-formula-version-history', '2026-09-06b-component-formula-restore-action', '2026-09-06c-pos-mobile-reversal-step-up', '2026-09-06d-pos-mobile-reprint-step-up', '2026-09-06e-activity-audit-foundation', '2026-09-06f-pos-mobile-cashier-close-step-up', '2026-09-06g-pos-mobile-reservation-refund-step-up', '2026-09-06h-roastery-label-template-studio', '2026-09-06i-a3-sidebar-task-oriented-layout', '2026-09-07a-c2-c4-business-profile-license-runtime-foundation', '2026-09-12a-roast-connect-catalog', '2026-09-13a-finance-mutation-reporting-category', '2026-09-14a-finance-control-workspace', '2026-09-14b-finance-control-operations', '2026-09-14c-finance-allocation-bank-review', '2026-09-15a-finance-general-ledger', '2026-09-15b-finance-journal-assistant', '2026-09-15c-application-user-guide', '2026-09-16a-procurement-stock-review','2026-09-20a-pos-stock-commit-not-required','2026-09-23a-module-notifications'], 'upgrade plan excludes only the clean-install-only navigation reference seed');
$check(
    count($catalog['legacy_unmanaged_sql'] ?? []) === 7
        && count($catalog['migrations'] ?? []) === 27
        && hash_file('sha256', $sqlPath) === ($catalog['migrations'][0]['sha256'] ?? '')
        && ($catalog['migrations'][0]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'catalog records twenty-seven managed and seven explicit legacy SQL files plus exact foundation checksum and policies'
);
$check(($catalog['migrations'][1]['dependencies'] ?? []) === [$catalog['migrations'][0]['id']], 'Telegram migration explicitly depends on the registry foundation');
$check(($catalog['migrations'][2]['dependencies'] ?? []) === [$catalog['migrations'][1]['id']], 'Telegram guide migration explicitly depends on the Telegram foundation');
$check(($catalog['migrations'][3]['dependencies'] ?? []) === [$catalog['migrations'][2]['id']], 'Telegram safe activation seed explicitly depends on the setup guide');
$check(
    ($catalog['migrations'][4]['dependencies'] ?? []) === [$catalog['migrations'][0]['id']]
        && ($catalog['migrations'][4]['policies'] ?? []) === ['clean_install'],
    'reference seed depends on registry and is excluded from upgrade policy'
);
$check(
    ($catalog['migrations'][5]['id'] ?? '') === '2026-09-05e-whatsapp-safe-reference-seed'
        && ($catalog['migrations'][5]['dependencies'] ?? []) === [$catalog['migrations'][0]['id']]
        && ($catalog['migrations'][5]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'WhatsApp safe reference seed is managed for clean install and upgrade'
);
$check(
    ($catalog['migrations'][6]['id'] ?? '') === '2026-09-06a-component-formula-version-history'
        && ($catalog['migrations'][6]['dependencies'] ?? []) === [$catalog['migrations'][0]['id']]
        && ($catalog['migrations'][6]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'formula version history is managed for clean install and upgrade'
);
$check(
    ($catalog['migrations'][7]['id'] ?? '') === '2026-09-06b-component-formula-restore-action'
        && ($catalog['migrations'][7]['dependencies'] ?? []) === ['2026-09-06a-component-formula-version-history']
        && ($catalog['migrations'][7]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'formula restore action migration depends on immutable formula history'
);
$check(
    ($catalog['migrations'][8]['id'] ?? '') === '2026-09-06c-pos-mobile-reversal-step-up'
        && ($catalog['migrations'][8]['dependencies'] ?? []) === ['2026-09-04c-a5-schema-migration-registry-foundation']
        && ($catalog['migrations'][8]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'POS Mobile reversal proof migration is managed after the registry foundation'
);
$check(
    ($catalog['migrations'][9]['id'] ?? '') === '2026-09-06d-pos-mobile-reprint-step-up'
        && ($catalog['migrations'][9]['dependencies'] ?? []) === ['2026-09-06c-pos-mobile-reversal-step-up']
        && ($catalog['migrations'][9]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'POS Mobile reprint proof migration is managed after the immutable reversal proof schema'
);
$check(
    ($catalog['migrations'][10]['id'] ?? '') === '2026-09-06e-activity-audit-foundation'
        && ($catalog['migrations'][10]['dependencies'] ?? []) === ['2026-09-04c-a5-schema-migration-registry-foundation']
        && ($catalog['migrations'][10]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'activity audit foundation is managed after the migration registry foundation'
);
$check(
    ($catalog['migrations'][11]['id'] ?? '') === '2026-09-06f-pos-mobile-cashier-close-step-up'
        && ($catalog['migrations'][11]['dependencies'] ?? []) === ['2026-09-06d-pos-mobile-reprint-step-up']
        && ($catalog['migrations'][11]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'POS Mobile cashier-close proof migration is managed after the immutable reprint proof schema'
);
$check(
    ($catalog['migrations'][12]['id'] ?? '') === '2026-09-06g-pos-mobile-reservation-refund-step-up'
        && ($catalog['migrations'][12]['dependencies'] ?? []) === ['2026-09-06f-pos-mobile-cashier-close-step-up']
        && ($catalog['migrations'][12]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'POS Mobile reservation-deposit-refund proof migration is managed after the cashier-close proof schema'
);
$check(
    ($catalog['migrations'][13]['id'] ?? '') === '2026-09-06h-roastery-label-template-studio'
        && ($catalog['migrations'][13]['dependencies'] ?? []) === ['2026-09-04c-a5-schema-migration-registry-foundation']
        && ($catalog['migrations'][13]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'Roastery Label Studio template migration is managed after the migration registry foundation'
);
$check(
    ($catalog['migrations'][14]['id'] ?? '') === '2026-09-06i-a3-sidebar-task-oriented-layout'
        && ($catalog['migrations'][14]['dependencies'] ?? []) === ['2026-09-05a-telegram-bot-foundation']
        && ($catalog['migrations'][14]['classification'] ?? '') === 'seed'
        && ($catalog['migrations'][14]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'task-oriented sidebar layout is a managed seed after the Telegram navigation foundation'
);
$check(
    ($catalog['migrations'][15]['id'] ?? '') === '2026-09-07a-c2-c4-business-profile-license-runtime-foundation'
        && ($catalog['migrations'][15]['dependencies'] ?? []) === ['2026-09-06i-a3-sidebar-task-oriented-layout']
        && ($catalog['migrations'][15]['classification'] ?? '') === 'schema'
        && ($catalog['migrations'][15]['policies'] ?? []) === ['clean_install', 'upgrade'],
    'commercial profile and audit-only licensing foundation is a managed additive schema migration'
);
$allPaths = array_merge($catalog['legacy_unmanaged_sql'] ?? [], array_column($catalog['migrations'] ?? [], 'path'));
$check(count(array_filter($allPaths, static function ($path): bool { return strpos((string)$path, '/_old/') !== false; })) === 0, 'catalog does not enroll archived SQL');

$sqlWithoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
$check(preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`sys_schema_migration`/i', $sql) === 1 && preg_match('/PRIMARY\s+KEY\s*\(\s*`migration_id`\s*\)/i', $sql) === 1, 'registry DDL is repeat-safe and keyed by migration_id');
$check(preg_match('/`filename`|`checksum_sha256`|`catalog_version`|`tool_version`|`applied_at`/', $sql) === 1 && preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|DROP|TRUNCATE)\b/i', (string)$sqlWithoutComments) === 0, 'registry SQL is metadata-only, non-destructive DDL');
$withoutPureProofImport = str_replace("require_once __DIR__ . '/ManagedMigrationProof.php';", '', $runnerSource);
$check(strpos($runnerSource, 'getenv(') === false && strpos($runnerSource, 'database.php') === false && preg_match('/new\s+PDO|mysqli_|mysql_connect|\brequire(?:_once)?\s*\(?\s*__|\binclude(?:_once)?\s*\(?\s*__/i', $withoutPureProofImport) === 0, 'runner imports only the pure schema-proof helper, not application config or credentials');
$check(strpos($validate['out'] . $planA['out'] . $validate['err'], 'A5_SECRET_TRIPWIRE') === false && strpos($planA['out'], $root) === false, 'runner output exposes only safe relative metadata');

require_once dirname(__DIR__).'/db/ManagedMigrationProof.php';
$oldEnum="enum('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED')|NO|'PENDING'|";
$newEnum="enum('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED','NOT_REQUIRED')|NO|'PENDING'|";
$enumProof=static function(string $column,int $invalidRows)use($root):string{
    return ManagedMigrationProof::state($root,'2026-09-20a-pos-stock-commit-not-required',static function(string $sql)use($column,$invalidRows){
        if(str_contains($sql,'SELECT CONCAT_WS'))return $column;
        if(str_contains($sql,'SELECT COUNT(*) FROM pos_order'))return $invalidRows;
        if(str_contains($sql,'information_schema.TABLES')||str_contains($sql,'information_schema.COLUMNS'))return 1;
        throw new LogicException('Unexpected proof query');
    });
};
$check($enumProof($oldEnum,0)==='ABSENT','original valid enum is eligible for additive correction');
$check($enumProof($newEnum,0)==='VERIFIED','corrected enum supports verified manual adoption');
foreach([$oldEnum,$newEnum]as$enum){
    try{$enumProof($enum,1);$check(false,'invalid historic enum status accepted');}
    catch(RuntimeException $e){$check($e->getMessage()==='MIGRATION_DATA_REVIEW_REQUIRED:pos_order.stock_commit_status','historic blank/NULL stock status requires explicit data review, never automatic backfill');}
}
try{$enumProof("varchar(20)|YES|NULL|",0);$check(false,'unexpected stock schema accepted');}
catch(RuntimeException $e){$check($e->getMessage()==='MIGRATION_SCHEMA_DRIFT:pos_order.stock_commit_status','unexpected stock schema is not treated as manually migrated');}

$fixtureParent = sys_get_temp_dir() . '/finance-a5-contract-' . bin2hex(random_bytes(6));
mkdir($fixtureParent, 0700, true);
register_shutdown_function(static function () use ($fixtureParent): void { if (is_dir($fixtureParent)) a5_contract_remove($fixtureParent, $fixtureParent); });

$cases = [
    'catalog_schema' => static function (&$catalog, $root): void { unset($catalog['catalog_policy']); },
    'duplicate_id' => static function (&$catalog, $root): void { $catalog['migrations'][2]['id'] = 'bbb'; },
    'duplicate_order' => static function (&$catalog, $root): void { $catalog['migrations'][2]['order'] = 20; },
    'duplicate_path' => static function (&$catalog, $root): void { $catalog['migrations'][2]['path'] = 'sql/b.sql'; },
    'path_noncanonical' => static function (&$catalog, $root): void { $catalog['migrations'][2]['path'] = 'sql//c.sql'; },
    'path_traversal' => static function (&$catalog, $root): void { $catalog['migrations'][2]['path'] = 'sql/../c.sql'; },
    'path_symlink' => static function (&$catalog, $root): void { unlink($root . '/sql/c.sql'); symlink($root . '/sql/b.sql', $root . '/sql/c.sql'); },
    'path_missing' => static function (&$catalog, $root): void { unlink($root . '/sql/c.sql'); },
    'checksum_drift' => static function (&$catalog, $root): void { file_put_contents($root . '/sql/c.sql', "CREATE TABLE drift (id INT);\n"); },
    'dependency_missing' => static function (&$catalog, $root): void { $catalog['migrations'][2]['dependencies'] = ['missing']; },
    'dependency_cycle' => static function (&$catalog, $root): void { $catalog['migrations'][0]['dependencies'] = ['ccc']; },
    'dependency_forward' => static function (&$catalog, $root): void { $catalog['migrations'][0]['dependencies'] = ['bbb']; $catalog['migrations'][1]['dependencies'] = []; },
    'migration_schema' => static function (&$catalog, $root): void { $catalog['migrations'][2]['classification'] = 'unknown'; },
    'invalid_policy' => static function (&$catalog, $root): void { $catalog['migrations'][2]['policies'] = ['production']; },
    'unacknowledged_sql' => static function (&$catalog, $root): void { file_put_contents($root . '/sql/new.sql', "SELECT 1;\n"); },
    'support_repair_policy' => static function (&$catalog, $root): void { $catalog['migrations'][2]['classification'] = 'support-repair'; $catalog['migrations'][2]['policies'] = ['upgrade']; },
];
foreach ($cases as $expectedCode => $mutate) {
    [$fixtureRoot, $fixtureCatalog] = a5_contract_fixture($fixtureParent, $expectedCode, $runnerSource);
    $mutate($fixtureCatalog, $fixtureRoot);
    a5_contract_write_catalog($fixtureRoot, $fixtureCatalog);
    $result = a5_contract_run($fixtureRoot . '/tools/db/migration_runner.php', ['validate']);
    $check($result['code'] !== 0 && a5_contract_error_code($result) === $expectedCode, 'fail-closed fixture rejects ' . $expectedCode);
}

a5_contract_remove($fixtureParent, $fixtureParent);
$check(!file_exists($fixtureParent), 'all DB-free contract fixtures are cleaned');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A5.1 migration catalog/runner check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' A5.1 migration catalog/runner checks passed.' . PHP_EOL;
