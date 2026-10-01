<?php
declare(strict_types=1);
require dirname(__DIR__) . '/build/CustomerBuild.php';
$root = dirname(__DIR__, 2); $checks = 0;
$check = static function (bool $ok, string $why) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $why);
    $checks++; echo 'PASS ' . $why . "\n";
};
$reject = static function (callable $fn, string $why) use ($check): void {
    try { $fn(); } catch (Throwable $e) { $check(true, $why); return; }
    $check(false, $why);
};
$app = ControlReleaseBridge::json((string)file_get_contents($root . '/app-manifest.json'));
$profile = CustomerReleaseProfile::fromRoot($root);
$check($app['packaging']['contract_version'] === 1 && count($app['packaging']['profiles']) === 1, 'one supported distribution profile');
$p = $app['packaging']['profiles'][0];
$check($p['code'] === 'CUSTOMER_CLEAN' && $p['default'] === true && $p['audience'] === 'CUSTOMER' && $p['sample_data'] === 'NONE', 'customer clean is default, not demo');
$check($p['rules_path'] === CustomerReleaseProfile::PATH && $p['rules_sha256'] === $profile->digest(), 'scanner hash matches immutable rules');
$check(is_file($root . '/' . $app['packaging']['adapter']) && !$profile->allows($app['packaging']['adapter']), 'build adapter exists only in development source, not customer runtime');
$r = ['schema' => 1, 'protocol' => CustomerBuild::PROTOCOL,
    'request_id' => '00000000-0000-4000-8000-000000000001', 'product_code' => 'NAMUA_FINANCE',
    'release_public_id' => '00000000-0000-4000-8000-000000000002', 'version' => $app['version'], 'channel' => 'ALPHA',
    'source_commit' => CustomerBuild::head($root), 'source_manifest_sha256' => hash_file('sha256', $root . '/app-manifest.json'),
    'profile_code' => $p['code'], 'profile_rules_sha256' => $profile->digest(), 'audience' => 'CUSTOMER', 'sample_data' => 'NONE'];
$check(CustomerBuild::canonical(array_reverse($r, true)) === json_encode($r, JSON_UNESCAPED_SLASHES), 'request hash uses Control field order, not pretty printed file bytes');
$wrong = $r; unset($wrong['channel']);
$reject(fn() => CustomerBuild::canonical($wrong), 'missing request field rejected');
$wrong = $r; $wrong['database'] = 'not-accepted';
$reject(fn() => CustomerBuild::canonical($wrong), 'request cannot provide a source database');
foreach (['version', 'source_commit', 'source_manifest_sha256', 'profile_rules_sha256', 'profile_code', 'audience', 'sample_data', 'channel'] as $field) {
    $wrong = $r; $wrong[$field] = 'invalid';
    $reject(fn() => CustomerBuild::validate($wrong, $root), 'reject mismatched ' . $field);
}
$db = (string)file_get_contents($root . '/tools/build/DisposableBuildDatabase.php');
$check(str_contains($db, "'--skip-networking'") && str_contains($db, "'--no-defaults'") && str_contains($db, "'--pdeathsig', 'TERM'"), 'temporary MariaDB has no network/default server config and dies with adapter');
$check(!str_contains($db, 'application/config') && !str_contains($db, 'DROP DATABASE') && !str_contains($db, 'TRUNCATE'), 'adapter does not load operational DB credentials or clear existing databases');
$check(str_contains($db, 'a5_apply(') && str_contains($db, 'a512_bootstrap_owner(') && str_contains($db, 'a513_check_database(')
    && str_contains($db, 'DISPOSABLE_RESTORE_MISMATCH'), 'real shared install and restore validations are required, not fixture PASS stubs');
$adapter = (string)file_get_contents($root . '/tools/build/CustomerBuild.php');
$check(str_contains($adapter, 'COMPOSER_HOME=') && str_contains($adapter, 'A4_STATIC_RUNTIME_DIR=')
    && !str_contains($adapter, "putenv('HOME="), 'build uses private tool caches without overriding system home');
$node = json_decode((string)file_get_contents($root . '/wa-engine/package.json'), true);
$nodeLock = json_decode((string)file_get_contents($root . '/wa-engine/package-lock.json'), true);
$check(($node['overrides']['sharp'] ?? '') === '0.35.4' && ($nodeLock['packages']['node_modules/sharp']['version'] ?? '') === '0.35.4',
    'release pins sharp security patch for GHSA-rgj7-g3m4-5g8c');
// Execute the exact default-check SQL against synthetic memory records. No server/config.
require dirname(__DIR__) . '/build/DisposableBuildDatabase.php';
$memory = new SQLite3(':memory:');
$memory->createFunction('regexp', static fn($pattern, $value): int => preg_match('/' . $pattern . '/D', (string)$value), 2);
$memory->exec('CREATE TABLE sys_roast_connect(id INTEGER,instance_id TEXT,name TEXT,enabled INTEGER,division_id INTEGER,destination_type TEXT,token_hash TEXT,token_tail TEXT,token_created_at TEXT,expires_at TEXT,revision INTEGER,updated_by INTEGER,updated_at TEXT)');
$memory->exec('CREATE TABLE fin_control_policy(id INTEGER,approval_enabled INTEGER,approval_threshold NUMERIC,evidence_required INTEGER,payroll_day INTEGER,revision INTEGER,updated_by INTEGER,updated_at TEXT)');
$defaults = [
    'sys_roast_connect' => "INSERT INTO sys_roast_connect VALUES(1,'0123456789abcdef0123456789abcdef','Finance',0,NULL,'ROASTERY',NULL,NULL,NULL,NULL,0,NULL,NULL)",
    'fin_control_policy' => "INSERT INTO fin_control_policy VALUES(1,0,1000000,0,1,1,NULL,'2026-09-16 00:00:00')",
];
foreach ($defaults as $sql) $memory->exec($sql);
$memory->exec('CREATE TABLE fin_gl_account(code TEXT PRIMARY KEY,name TEXT,account_type TEXT,is_cash INTEGER,is_active INTEGER DEFAULT 1)');
$referenceSql=file_get_contents($root.'/sql/2026-09-15a_finance_general_ledger.sql');
preg_match_all("/\\('[0-9]{4}','[^']+','(?:ASSET|LIABILITY|EQUITY|INCOME|EXPENSE)',[01]\\)/u",$referenceSql,$coaTuples);
$memory->exec('INSERT INTO fin_gl_account(code,name,account_type,is_cash) VALUES '.implode(',',$coaTuples[0]));
$coaQuery=str_replace('BINARY ','',DisposableBuildDatabase::accountReferenceQuery()); // SQLite TEXT comparison is binary by default.
$check($memory->query($coaQuery)->fetchArray(SQLITE3_NUM)===[24,24],'clean package allows exactly the 24 generic chart-of-account defaults');
$memory->exec("UPDATE fin_gl_account SET name='Private customer reference' WHERE code='1100'");
$check($memory->query($coaQuery)->fetchArray(SQLITE3_NUM)!==[24,24],'customer-specific account reference cannot pass the package data gate');
foreach (DisposableBuildDatabase::safeDefaultQueries() as $failure => $sql) {
    $table = $failure === 'ROAST_CONNECT_DEFAULT_UNSAFE' ? 'sys_roast_connect' : 'fin_control_policy';
    $query = str_replace('BINARY ', '', $sql); // SQLite comparison is already case-sensitive.
    $valid = static fn(): bool => $memory->query($query)->fetchArray(SQLITE3_NUM) === [1, 1];
    $check($valid(), 'exact newly generated safe default accepted: ' . $table);
    $changes = $table === 'sys_roast_connect'
        ? ["id=2", "instance_id='legacy-instance'", "name='Customer identity'", 'enabled=1', 'division_id=1',
            "destination_type='BAR'", "token_hash='fixture-hash'", "token_tail='abcd'", "token_created_at='2026-01-01'",
            "expires_at='2027-01-01'", 'revision=1', 'updated_by=1', "updated_at='2026-01-01'"]
        : ['id=2', 'approval_enabled=1', 'approval_threshold=1', 'evidence_required=1', 'payroll_day=25', 'revision=2', 'updated_by=1', 'updated_at=NULL'];
    foreach ($changes as $change) {
        $memory->exec('BEGIN'); $memory->exec('UPDATE ' . $table . ' SET ' . $change);
        $check(!$valid(), 'changed/default-secret state rejected: ' . $table . '/' . explode('=', $change)[0]);
        $memory->exec('ROLLBACK');
    }
    $memory->exec('BEGIN'); $memory->exec($defaults[$table]);
    $check(!$valid(), 'extra settings row rejected: ' . $table); $memory->exec('ROLLBACK');
    $memory->exec('BEGIN'); $memory->exec('DELETE FROM ' . $table);
    $check(!$valid(), 'missing settings row rejected: ' . $table); $memory->exec('ROLLBACK');
}
$memory->exec('CREATE TABLE p(page_code TEXT)');
$memory->exec('CREATE TABLE rp(can_view INTEGER,can_create INTEGER,can_edit INTEGER,can_delete INTEGER,can_export INTEGER)');
foreach (['tg.guide'=>[1,0,0,0,0], 'system.roast_connect'=>[1,1,1,0,0], 'finance.control.index'=>[1,1,1,0,0],
    'finance.control.approve'=>[1,0,1,0,0], 'finance.control.settings'=>[1,0,1,0,0], 'existing.page'=>[1,1,1,1,1]] as $page=>$flags) {
    $memory->exec('DELETE FROM p'); $memory->exec('DELETE FROM rp');
    $memory->exec("INSERT INTO p VALUES('" . SQLite3::escapeString($page) . "')");
    $memory->exec('INSERT INTO rp VALUES(' . implode(',', $flags) . ')');
    $predicate = str_replace('BINARY ', '', a513_permission_match_sql());
    $check((int)$memory->querySingle('SELECT COUNT(*) FROM p,rp WHERE ' . $predicate) === 1, 'installer accepts exact seeded permissions: ' . $page);
    $memory->exec('UPDATE rp SET can_edit=1-can_edit');
    $check((int)$memory->querySingle('SELECT COUNT(*) FROM p,rp WHERE ' . $predicate) === 0, 'installer rejects permission drift: ' . $page);
}
$memory->close();
echo "All {$checks} Control build adapter contract checks passed (SQLite memory only; no application database).\n";
