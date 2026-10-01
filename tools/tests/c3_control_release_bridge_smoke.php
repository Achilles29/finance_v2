<?php
declare(strict_types=1);
require dirname(__DIR__) . '/release/ControlReleaseBridge.php';

$root = dirname(__DIR__, 2);
$stage = sys_get_temp_dir() . '/finance-control-bridge-test-' . bin2hex(random_bytes(8));
if (!mkdir($stage, 0700)) throw new RuntimeException('Fixture directory unavailable.');
$source = $stage . '/source'; mkdir($source, 0700);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . "\n";
};
$reject = static function (callable $call, string $label) use ($check): void {
    try { $call(); } catch (Throwable $e) { $check(true, $label); return; }
    $check(false, $label);
};
$run = static function (array $cmd): void {
    $r = financeArtifactSignatureRun($cmd);
    if ($r['code'] !== 0) throw new RuntimeException('Fixture command failed.');
};
try {
    $app = ControlReleaseBridge::json((string)file_get_contents($root . '/app-manifest.json'));
    $runtime = ControlReleaseBridge::json((string)file_get_contents($root . '/tools/release/runtime_compatibility.json'));
    ControlReleaseBridge::validateRuntime($app, $runtime);
    $check($app['runtime']['database'] === 'MariaDB >=10.11 <10.12', 'new candidate declares MariaDB server 10.11');
    $oldApp = $app; $oldApp['runtime']['database'] = 'MariaDB >=10.6 <10.7';
    $oldRuntime = $runtime; $oldRuntime['runtimes']['mariadb']['minimum'] = '10.6.0';
    $oldRuntime['runtimes']['mariadb']['maximum_exclusive'] = '10.7.0';
    ControlReleaseBridge::validateRuntime($oldApp, $oldRuntime);
    $check(true, 'immutable legacy runtime remains verifiable');
    $reject(fn() => ControlReleaseBridge::validateRuntime($app, $oldRuntime), 'new manifest cannot use legacy runtime policy');
    $reject(fn() => ControlReleaseBridge::validateRuntime($oldApp, $runtime), 'legacy manifest cannot silently widen runtime');
    $reject(fn() => ControlReleaseBridge::databaseRule('MariaDB >=10 <12'), 'unapproved broad database range rejected');
    $catalog = ControlReleaseBridge::json((string)file_get_contents($root . '/tools/db/migration_catalog.json'));
    $baseline = ControlReleaseBridge::json((string)file_get_contents($root . '/tools/db/clean_install_baseline_policy.json'));
    $paths = array_merge(['app-manifest.json', 'tools/release/package_policy.json', 'tools/release/runtime_compatibility.json',
        'tools/db/migration_catalog.json', 'tools/db/clean_install_baseline_policy.json', $baseline['schema']['path']],
        array_column($catalog['migrations'], 'path'), $catalog['legacy_unmanaged_sql']);
    $entries = [];
    foreach ($paths as $path) {
        if (!is_dir(dirname($source . '/' . $path))) mkdir(dirname($source . '/' . $path), 0700, true);
        copy($root . '/' . $path, $source . '/' . $path);
        $entries[] = ['path' => $path, 'sha256' => hash_file('sha256', $source . '/' . $path), 'size' => filesize($source . '/' . $path), 'mode' => '0644'];
    }
    $run(['git', '-C', $source, 'init', '-q']);
    $run(['git', '-C', $source, 'add', '.']);
    $run(['git', '-C', $source, '-c', 'user.name=Release Fixture', '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'fixture']);
    $archive = $stage . '/fixture.tar';
    // Build a synthetic archive only: this is a verifier unit test, not customer release evidence.
    $tar = new PharData($archive);
    foreach ($paths as $path) $tar->addFile($source . '/' . $path, $path);
    $tar->addFromString('RELEASE-MANIFEST.json', json_encode(['schema' => 'finance.release-artifact-manifest', 'schema_version' => 1, 'source_epoch' => 123, 'files' => $entries], JSON_UNESCAPED_SLASHES) . "\n");
    unset($tar);
    $manifest = ControlReleaseBridge::inspect($archive);
    $manifest['source_commit'] = trim(financeArtifactSignatureRun(['git', '-C', $source, 'rev-parse', 'HEAD'])['stdout']);
    $manifest['source_dirty'] = false;
    $manifest['contains_customer_data'] = false; // Reproduce the historical sidecar, not a clean qualification.
    $reject(fn() => ControlReleaseBridge::describe($source, $archive), 'legacy bundle cannot be exported as a new customer candidate');
    $check(count($manifest['migrations']) === count($catalog['migrations']), 'SQL migration catalog is preserved exactly');
    $check(count($manifest['legacy_sql']) === count($catalog['legacy_unmanaged_sql']), 'legacy SQL is explicit and never silently scheduled');
    $check($manifest['runtime']['php'] === '>=8.1 <8.2', 'runtime matches the tested PHP line');
    $check($manifest['apk']['release_ready'] === false && $manifest['apk']['commercial_work'] === 'ALLOWED', 'APK commercialization allowed without pretending deferred bugs are fixed');
    $kp = sodium_crypto_sign_keypair();
    $pub = sodium_crypto_sign_publickey($kp);
    $key = ['schema' => 1, 'product_code' => 'NAMUA_FINANCE', 'algorithm' => 'Ed25519',
        'key_id' => '00000000-0000-4000-8000-000000000001', 'public_key_base64' => base64_encode($pub),
        'public_key_sha256' => hash('sha256', $pub), 'secret_key_base64' => base64_encode(sodium_crypto_sign_secretkey($kp))];
    $trust = $key; unset($trust['secret_key_base64']); $trust['status'] = 'ACTIVE';
    $encode = static fn(array $m): string => json_encode($m, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $bytes = $encode($manifest); $name = 'fixture.release.json';
    $sig = ControlReleaseBridge::sign($bytes, $name, $key);
    $check(sodium_crypto_sign_verify_detached(base64_decode($sig['signature_base64']), "NAMUA_RELEASE_MANIFEST_V1\n" . hash('sha256', $bytes), $pub), 'signature uses exact Control release domain, not license domain');
    $result = ControlReleaseBridge::verify($bytes, $name, $sig, $trust, $archive);
    $check(!$result['customer_clean_eligible'] && $result['customer_content_audit']['status'] === 'NOT_AUDITED', 'legacy signature is not clean-customer evidence');
    $check($result['status'] === 'PASS' && !$result['published'] && !$result['database_changed'], 'valid signed artifact verifies without writes');
    $reject(fn() => ControlReleaseBridge::verify($bytes . ' ', $name, $sig, $trust, $archive), 'even whitespace tampering breaks original-byte signature');
    $reject(fn() => ControlReleaseBridge::verify($bytes, 'renamed.release.json', $sig, $trust, $archive), 'signed filename binding enforced');
    foreach (['product_code' => 'NAMUA_PENATAUSAHAAN', 'key_id' => '00000000-0000-4000-8000-000000000002', 'status' => 'REVOKED'] as $field => $value) {
        $bad = $trust; $bad[$field] = $value;
        $reject(fn() => ControlReleaseBridge::verify($bytes, $name, $sig, $bad, $archive), 'reject wrong trust ' . $field);
    }
    foreach (['source_dirty' => true, 'source_commit' => 'invalid', 'version' => '99.0.0', 'apk' => ['release_ready' => true],
        'migrations' => [], 'legacy_sql' => [], 'baseline' => [], 'runtime' => ['php' => '>=8.1 <8.5']] as $field => $value) {
        $bad = $manifest; $bad[$field] = $value; $raw = $encode($bad);
        $badSig = ControlReleaseBridge::sign($raw, $name, $key);
        $reject(fn() => ControlReleaseBridge::verify($raw, $name, $badSig, $trust, $archive), 'signed metadata cannot misrepresent ' . $field);
    }
    file_put_contents($source . '/untracked.txt', 'fixture');
    $reject(fn() => ControlReleaseBridge::describe($source, $archive), 'dirty checkout cannot be attested');
    unlink($source . '/untracked.txt');
    file_put_contents($source . '/app-manifest.json', (string)file_get_contents($source . '/app-manifest.json') . "\n");
    $run(['git', '-C', $source, 'add', '.']);
    $run(['git', '-C', $source, '-c', 'user.name=Release Fixture', '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'changed fixture']);
    $reject(fn() => ControlReleaseBridge::describe($source, $archive), 'clean but different commit bytes cannot be attested');
    $tar = new PharData($archive); $tar->addFromString('assets/uploads/should-not-ship.txt', 'fixture'); unset($tar);
    $reject(fn() => ControlReleaseBridge::verify($bytes, $name, $sig, $trust, $archive), 'undeclared extra archive member is rejected');
    sodium_memzero($kp); unset($key);
    echo "All {$checks} Finance-Control release bridge checks passed.\n";
} finally { financeArtifactSignatureRemoveTree($stage); }
