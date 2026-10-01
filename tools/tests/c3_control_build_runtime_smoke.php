<?php
declare(strict_types=1);

/** Opt-in integration test. Copies code into an isolated Git fixture; never reads operational data/credentials. */
require dirname(__DIR__) . '/build/CustomerBuild.php';
CustomerBuild::need(PHP_SAPI === 'cli' && posix_geteuid() === 0 && in_array('--isolated', $argv, true), 'EXPLICIT_ISOLATED_TEST_REQUIRED');
$root = dirname(__DIR__, 2);
$identity = posix_getpwnam('namua-build');
CustomerBuild::need(is_array($identity), 'BUILD_USER_UNAVAILABLE');
$stage = '/tmp/finance-build-integration-' . bin2hex(random_bytes(6));
CustomerBuild::need(mkdir($stage, 0711), 'FIXTURE_CREATE_FAILED'); chmod($stage, 0711);
$source = $stage . '/source'; $work = $stage . '/job'; $output = $work . '/output';
mkdir($source, 0755); mkdir($work, 0700); mkdir($output, 0700);
$owned = static function (string $p) use ($identity): void { chown($p, $identity['uid']); chgrp($p, $identity['gid']); };
$owned($work); $owned($output);
try {
    $profile = CustomerReleaseProfile::fromRoot($root);
    $files = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json')->enumerate($root)['files'];
    // Include new, not-yet-committed implementation files explicitly, never all untracked runtime files.
    $files = array_unique(array_merge($files, ['tools/build/CustomerBuild.php', 'tools/build/customer_package.php',
        'tools/build/DisposableBuildDatabase.php', 'tools/build/verify_control_build.php', 'tools/tests/c3_control_build_adapter_smoke.php',
        'tools/tests/c3_control_build_runtime_smoke.php',
        'tools/tests/procurement_current_stock_smoke.php',
        'tools/tests/customer_source_coverage_smoke.php',
        'tools/update/UpdateJournal.php',
        'tools/update/preflight.php',
        'application/views/procurement/_current_stock.php',
        'application/views/procurement/_manual_stock_toolbar.php',
        'assets/js/procurement-current-stock.js']));
    foreach ($files as $p) {
        // Old product photographs, marketing assets and development reports are not copied even to this fixture.
        if ((str_starts_with($p, 'assets/') || str_starts_with($p, 'docs/')) && !$profile->allows($p)) continue;
        $target = $source . '/' . $p;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
        CustomerBuild::need(copy($root . '/' . $p, $target), 'FIXTURE_COPY_FAILED');
        chmod($target, (fileperms($root . '/' . $p) & 0111) !== 0 ? 0755 : 0644);
    }
    CustomerBuild::run(['git', 'init', '-q', $source], $stage, 'FIXTURE_GIT_FAILED');
    CustomerBuild::run(['git', '-C', $source, 'add', '-f', '.'], $stage, 'FIXTURE_GIT_FAILED');
    CustomerBuild::run(['git', '-C', $source, '-c', 'user.name=Finance isolated build test', '-c', 'user.email=build@example.invalid',
        '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'isolated source snapshot, not a publishable Finance cutoff'], $stage, 'FIXTURE_GIT_FAILED');
    $app = ControlReleaseBridge::json((string)file_get_contents($source . '/app-manifest.json'));
    $r = ['schema' => 1, 'protocol' => CustomerBuild::PROTOCOL, 'request_id' => '00000000-0000-4000-8000-000000000001',
        'product_code' => 'NAMUA_FINANCE', 'release_public_id' => '00000000-0000-4000-8000-000000000002',
        'version' => $app['version'], 'channel' => 'ALPHA', 'source_commit' => CustomerBuild::head($source),
        'source_manifest_sha256' => hash_file('sha256', $source . '/app-manifest.json'), 'profile_code' => 'CUSTOMER_CLEAN',
        'profile_rules_sha256' => $profile->digest(), 'audience' => 'CUSTOMER', 'sample_data' => 'NONE'];
    $requestPath = $work . '/request.json'; CustomerBuild::writeJson($requestPath, $r); $owned($requestPath); chmod($requestPath, 0400);
    $environment = ['PATH' => '/usr/local/bin:/usr/bin:/bin', 'LANG' => 'C.UTF-8', 'TMPDIR' => $work,
        'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_COUNT' => '1', 'GIT_CONFIG_KEY_0' => 'safe.directory', 'GIT_CONFIG_VALUE_0' => $source];
    if (in_array('--database-only', $argv, true)) {
        // Targeted component test only. This branch MUST NOT emit build result/report or release gate evidence.
        $extract = $work . '/extract'; mkdir($extract, 0755);
        $entries = releaseArtifactSnapshot(ReleasePackagePolicy::fromFile($source . '/tools/release/package_policy.json'), $source, $profile);
        foreach ($entries as $p => $entry) {
            if (!is_dir(dirname($extract . '/' . $p))) mkdir(dirname($extract . '/' . $p), 0755, true);
            copy($source . '/' . $p, $extract . '/' . $p); chmod($extract . '/' . $p, octdec($entry['mode']));
        }
        CustomerBuild::writeJson($extract . '/RELEASE-MANIFEST.json', ['schema' => 'finance.release-artifact-manifest', 'schema_version' => 1,
            'source_epoch' => 946684800, 'files' => array_values($entries)]); chmod($extract . '/RELEASE-MANIFEST.json', 0644);
        $paths = array_merge(array_keys($entries), ['RELEASE-MANIFEST.json']); sort($paths);
        file_put_contents($work . '/files.list', implode("\0", $paths) . "\0");
        $artifact = $work . '/database-component-test.tar';
        CustomerBuild::run(['/usr/bin/tar', '--create', '--format=gnu', '--sort=name', '--no-recursion', '--mtime=@946684800',
            '--owner=0', '--group=0', '--numeric-owner', '--null', '--directory=' . $extract, '--files-from=' . $work . '/files.list',
            '--file=' . $artifact], $work, 'FIXTURE_TAR_FAILED'); chmod($artifact, 0644);
        $environment['PATH'] = '/www/server/mysql/bin:/usr/local/bin:/usr/bin:/bin';
        $code = 'require $argv[1]."/tools/build/CustomerBuild.php"; require $argv[1]."/tools/build/DisposableBuildDatabase.php";'
            . '$inspection=ControlReleaseBridge::describe($argv[1],$argv[2]); $db=DisposableBuildDatabase::test($argv[3],$argv[4]);'
            . 'echo json_encode(["status"=>"PASS","kind"=>"DATABASE_COMPONENT_TEST_ONLY_NOT_BUILD_EVIDENCE","audit"=>$inspection["customer_content_audit"],"database"=>$db],JSON_UNESCAPED_SLASHES),PHP_EOL;';
        $p = proc_open(['/usr/bin/setpriv', '--reuid=' . $identity['uid'], '--regid=' . $identity['gid'], '--clear-groups', '--no-new-privs',
            PHP_BINARY, '-r', $code, $source, $artifact, $extract, $work], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $source, $environment);
        CustomerBuild::need(is_resource($p) && proc_close($p) === 0, 'DATABASE_COMPONENT_TEST_FAILED');
        return;
    }
    echo "START unprivileged PHP 8.4 adapter -> PHP 8.1 gates -> disposable MariaDB; no operational database.\n";
    $process = proc_open(['/usr/bin/timeout', '--kill-after=30', '1200', '/usr/bin/setpriv', '--reuid=' . $identity['uid'], '--regid=' . $identity['gid'],
        '--clear-groups', '--no-new-privs', '/www/server/php/84/bin/php', $source . '/tools/build/customer_package.php',
        '--request=' . $requestPath, '--output=' . $output], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w']], $pipes, $source, $environment);
    CustomerBuild::need(is_resource($process), 'FIXTURE_PROCESS_FAILED');
    if (in_array('--cancel', $argv, true)) {
        $own = proc_get_status($process);
        for ($i = 0; $i < 40; $i++) usleep(100000);
        CustomerBuild::need($own['pid'] > 1 && posix_getpgid($own['pid']) === $own['pid'], 'FIXTURE_PROCESS_GROUP');
        // GNU timeout made this group exclusively for this fixture; never signal a service or shared shell.
        posix_kill(-$own['pid'], SIGTERM);
    }
    $exit = proc_close($process);
    $result = is_file($output . '/result.json') ? ControlReleaseBridge::json((string)file_get_contents($output . '/result.json')) : [];
    if (in_array('--cancel', $argv, true)) {
        CustomerBuild::need($exit !== 0 && ($result['status'] ?? '') === 'FAIL' && ($result['error_code'] ?? '') === 'BUILD_INTERRUPTED'
            && (glob($work . '/finance-work-*') ?: []) === [], 'CANCEL_CLEANUP_FAILED');
        echo "PASS cancelled adapter returns bound FAIL and completes cleanup before its parent exits (not release evidence).\n";
        return;
    }
    if ($exit !== 0) echo json_encode(['error_code' => $result['error_code'] ?? 'NO_RESULT', 'diagnostics' => $result['diagnostics'] ?? []], JSON_UNESCAPED_SLASHES) . "\n";
    CustomerBuild::need($exit === 0 && ($result['status'] ?? '') === 'PASS', (string)($result['error_code'] ?? 'ADAPTER_INTEGRATION_FAILED'));
    $verification = CustomerBuild::run([PHP_BINARY, $source . '/tools/build/verify_control_build.php', '--request=' . $requestPath, '--output=' . $output], $source, 'TRUSTED_VALIDATOR_FAILED');
    CustomerBuild::need((ControlReleaseBridge::json($verification['output'])['status'] ?? '') === 'PASS', 'TRUSTED_VALIDATOR_FAILED');
    $report = ControlReleaseBridge::json((string)file_get_contents($output . '/build-report.json'));
    foreach (ControlReleaseBridge::BUILD_GATES as $gate) CustomerBuild::need(($result['gates'][$gate]['status'] ?? '') === 'PASS', 'REAL_BUILD_GATE_FAILED');
    // Verify the completed TAR contains current bytes of the whole reviewed batch, including new files.
    define('FINANCE_CUSTOMER_COVERAGE_LIBRARY_ONLY', true);
    require $root . '/tools/tests/customer_source_coverage_smoke.php';
    $expected = [];
    foreach (financeCustomerBatchFiles() as $path) {
        $expected[$path] = hash_file('sha256', $source . '/' . $path);
        CustomerBuild::need(hash_equals($expected[$path], hash_file('sha256', $root . '/' . $path)), 'BATCH_SOURCE_CHANGED_DURING_TEST');
    }
    $package = $output . '/' . $result['artifacts']['application_package']['file'];
    // The build log runner retains only a tail: use the bounded full-output archive reader for JSON.
    $inventoryRead = financeArtifactSignatureRun(['/usr/bin/tar', '--extract', '--to-stdout', '--file=' . $package, '--', 'RELEASE-MANIFEST.json']);
    CustomerBuild::need($inventoryRead['code'] === 0 && !$inventoryRead['overflow'], 'BATCH_INVENTORY_READ_FAILED');
    $inventory = ControlReleaseBridge::json($inventoryRead['stdout']);
    financeCustomerBatchInventory($inventory['files'], $expected);
    echo "PASS exact source-to-TAR coverage: " . count($expected) . " procurement/update/schema runtime dependencies.\n";
    // Optional read-only cross-application check: import function definitions only, never Control's worker main/DB/signing key.
    foreach (array_slice($argv, 1) as $arg) if (str_starts_with($arg, '--control-root=')) {
        $control = substr($arg, 15);
        define('NAMUA_RELEASE_BUILD_LIBRARY_ONLY', true);
        require $control . '/tools/process_release_builds.php';
        $build = ['public_id' => $r['request_id'], 'product_code' => $r['product_code'], 'release_public_id' => $r['release_public_id'],
            'version' => $r['version'], 'channel' => $r['channel'], 'source_commit' => $r['source_commit'], 'source_manifest_sha256' => $r['source_manifest_sha256'],
            'package_profile_code' => $r['profile_code'], 'package_rules_sha256' => $r['profile_rules_sha256'], 'distribution_audience' => 'CUSTOMER',
            'sample_data_mode' => 'NONE', 'request_sha256' => hash('sha256', CustomerBuild::canonical($r))];
        namuaVerifyResult($build, $output);
        echo "PASS actual Control result/report verifier (library only; no Control writes).\n";
    }
    echo json_encode(['status' => 'PASS', 'kind' => 'ISOLATED_INTEGRATION_FIXTURE_NOT_RELEASE', 'metrics' => $report['metrics'],
        'clean_install' => $report['details']['clean_install'], 'backup_restore' => $report['details']['backup_restore'], 'gates' => array_keys($result['gates']),
        'source_database_accessed' => false, 'signed_or_published' => false], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} finally {
    // Freshly generated synthetic test files/database only; production backups/uploads are never targets.
    releaseArtifactRemoveTree($stage);
}
