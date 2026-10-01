<?php
declare(strict_types=1);
require dirname(__DIR__) . '/release/ControlReleaseBridge.php';
require dirname(__DIR__) . '/install/ControlDelivery.php';
define('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/post_install_health_check.php';

$root = dirname(__DIR__, 2);
$stage = sys_get_temp_dir() . '/finance-customer-clean-test-' . bin2hex(random_bytes(8));
mkdir($stage, 0700);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++; echo 'PASS: ' . $label . "\n";
};
$reject = static function (callable $call, string $label) use ($check): void {
    try { $call(); } catch (Throwable $error) { $check(true, $label); return; }
    $check(false, $label);
};
$run = static function (array $command): string {
    $result = financeArtifactSignatureRun($command);
    if ($result['code'] !== 0 || $result['overflow']) throw new RuntimeException('Fixture command failed: ' . substr($result['stderr'], -2000));
    return $result['stdout'];
};
$put = static function (string $path, string $bytes): void {
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
    file_put_contents($path, $bytes);
};
try {
    $profile = CustomerReleaseProfile::fromRoot($root);
    $raw = json_decode((string)file_get_contents($root . '/' . CustomerReleaseProfile::PATH), true);
    $check($profile->version() === 11, 'application update contract has a new profile; published v8-v10 are not overwritten');
    $unknown = $raw; $unknown['profile_version'] = 999;
    $reject(fn() => new CustomerReleaseProfile(json_encode($unknown)), 'unreviewed profile version rejected');
    $catalogSource = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
    foreach ($catalogSource['migrations'] as $migration) {
        $check(($raw['sql_sha256'][$migration['path']] ?? '') === $migration['sha256']
            && $migration['sha256'] === hash_file('sha256', $root . '/' . $migration['path']),
            'catalog migration is present and pinned in customer allowlist: ' . $migration['path']);
    }
    foreach (['application/controllers/Roast_connect.php', 'application/controllers/Roast_integrations.php',
        'application/models/Roast_connect_model.php', 'application/views/system/roast_connect.php', 'assets/js/roast-connect-admin.js'] as $path) {
        $check($profile->allows($path), 'registered Roast Connect route has packaged implementation: ' . $path);
    }
    $paths = array_merge($raw['files'], $raw['code_files'], array_keys($raw['static_sha256']), array_keys($raw['sql_sha256']));
    $paths = array_values(array_unique($paths)); sort($paths);
    $entries = [];
    $source = $stage . '/source'; $extract = $stage . '/extract';
    mkdir($source, 0700); mkdir($extract, 0700);
    foreach ($paths as $path) {
        $bytes = file_get_contents($root . '/' . $path);
        $put($source . '/' . $path, $bytes);
        $mode = fileperms($root . '/' . $path) & 0111 ? '0755' : '0644';
        chmod($source . '/' . $path, octdec($mode));
        $entries[] = ['path' => $path, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes), 'mode' => $mode];
    }
    $report = $profile->audit($entries);
    $check($report['status'] === 'PASS' && $report['demo_data'] === false && $report['source_database_accessed'] === false, 'curated source includes only approved references, without reading any DB');
    foreach (['scripts/backup/backup_full.sh', 'scripts/replication/recovery_sync.sh', 'scripts/cron/crontab.example',
        'application/.htaccess', 'system/.htaccess'] as $required) {
        $check($profile->allows($required), 'operational tooling and web protection retained: ' . $required);
    }
    $forbidden = ['config/customer.json', 'config/customer.json.bak', 'assets/menu-book/products/kopi-susu-namua.png', 'assets/roastery/logo 2.png',
        'assets/img/logo.png', 'assets/uploads/customer.jpg', 'assets/css/new-customer-photo.png',
        'assets/new-folder/customer.sql', 'application/new_dump.php',
        'docs/sql/2026-06-13b_zeroise_ting_ting_crumble_bar.sql', 'tools/truncate_all_tables.sql',
        'tools/pos_printer_agent/config.json', 'application/views/menu_book/beverage/page_09_namua_signatures.php'];
    foreach ($forbidden as $path) {
        $check(!$profile->allows($path), 'exclude ' . $path);
        $put($source . '/' . $path, 'synthetic exclusion trap');
        $extra = $entries; $extra[] = ['path' => $path, 'sha256' => hash('sha256', 'trap')];
        $reject(fn() => $profile->audit($extra), 'audit refuses forbidden member even when declared');
    }
    $bad = $entries; $bad[0]['sha256'] = str_repeat('0', 64);
    // Select a pinned asset, not arbitrary code: code changes are attested by the source commit.
    foreach ($bad as &$e) if ($e['path'] === 'assets/img/business-placeholder.svg') $e['sha256'] = str_repeat('0', 64);
    unset($e);
    $reject(fn() => $profile->audit($bad), 'changed approved artwork fails checksum');
    $missing = array_values(array_filter($entries, fn(array $e): bool => $e['path'] !== 'application/controllers/Auth.php'));
    $reject(fn() => $profile->audit($missing), 'missing required runtime controller fails audit');
    $bad = $entries;
    foreach ($bad as &$e) if (str_starts_with($e['path'], 'sql/')) $e['sha256'] = str_repeat('0', 64);
    unset($e);
    $reject(fn() => $profile->audit($bad), 'injected SQL or changed seed fails checksum');

    // Deterministic builder contract fixture. Real source gates are run separately; these stubs are not release evidence.
    foreach (['a4_release_preflight_smoke.php', 'a4_static_analysis_smoke.php', 'a4_dependency_vulnerability_smoke.php'] as $gate) {
        $put($source . '/tools/tests/' . $gate, "<?php echo 'PASS fixture gate';\n");
    }
    $run(['git', '-C', $source, 'init', '-q']);
    $run(['git', '-C', $source, 'add', '-f', '.']);
    $run(['git', '-C', $source, '-c', 'user.name=Clean Package Fixture', '-c', 'user.email=fixture@example.invalid',
        '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'synthetic clean package fixture']);
    $build = static function (string $name) use ($run, $root, $source, $stage): string {
        $path = $stage . '/' . $name . '.tar';
        $run([PHP_BINARY, $root . '/tools/release/build_release_artifact.php', '--root=' . $source,
            '--output=' . $path, '--source-epoch=1700000000']);
        return $path;
    };
    $archive = $build('clean'); $second = $build('clean-again');
    $check(hash_file('sha256', $archive) === hash_file('sha256', $second), 'default CUSTOMER_CLEAN builds are deterministic');
    $manifest = ControlReleaseBridge::describe($source, $archive);
    $check($manifest['distribution_profile'] === 'CUSTOMER_CLEAN' && $manifest['seed_profile'] === 'REFERENCE_ONLY'
        && $manifest['contains_customer_data'] === false && $manifest['legacy_sql'] === [], 'export derives clean eligibility from actual archive audit');
    $check($manifest['customer_content_audit']['artifact_sha256'] === hash_file('sha256', $archive)
        && $manifest['customer_content_audit']['profile_sha256'] === $profile->digest(), 'audit is bound to exact artifact and trusted profile');
    $run(['/usr/bin/tar', '-xf', $archive, '-C', $extract]);
    foreach ($forbidden as $path) $check(!file_exists($extract . '/' . $path) && is_file($source . '/' . $path), 'build omits trap without deleting source');
    $check(CustomerReleaseProfile::installed($extract), 'installed profile is bound to inner manifest');
    $release = a513_validate_release($extract, $extract . '/RELEASE-MANIFEST.json');
    $check(count($release['catalog']['migrations']) === 27 && $release['baseline']['ok'], 'installer accepts complete clean baseline without legacy repair files');
    $cleanPlan = array_column(a5_plan($release['catalog'], 'clean_install'), 'path');
    $upgradePlan = array_column(a5_plan($release['catalog'], 'upgrade'), 'path');
    $check(in_array('sql/2026-09-05d_a5_clean_install_reference_seed.sql', $cleanPlan, true)
        && !in_array('sql/2026-09-05d_a5_clean_install_reference_seed.sql', $upgradePlan, true), 'upgrade does not run first-install reference seed');

    $kp = sodium_crypto_sign_keypair(); $public = sodium_crypto_sign_publickey($kp);
    $key = ['schema' => 1, 'product_code' => 'NAMUA_FINANCE', 'algorithm' => 'Ed25519',
        'key_id' => '00000000-0000-4000-8000-000000000001', 'public_key_base64' => base64_encode($public),
        'public_key_sha256' => hash('sha256', $public), 'secret_key_base64' => base64_encode(sodium_crypto_sign_secretkey($kp))];
    $trust = $key; unset($trust['secret_key_base64']); $trust['status'] = 'ACTIVE';
    $name = 'clean.release.json'; $bytes = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $signature = ControlReleaseBridge::sign($bytes, $name, $key);
    $verified = ControlReleaseBridge::verify($bytes, $name, $signature, $trust, $archive);
    $check($verified['customer_clean_eligible'], 'signed clean candidate returns explicit validator eligibility');
    $plan = ['release' => ['distribution_profile' => 'CUSTOMER_CLEAN', 'distribution_profile_version' => $profile->version(),
        'seed_profile' => 'REFERENCE_ONLY', 'customer_content_profile_sha256' => $profile->digest()]];
    ControlDelivery::validateCustomerBinding($plan, $verified);
    $check(true, 'Control delivery binds signed profile and seed');
    $reject(fn() => ControlDelivery::validateCustomerBinding(['release' => []], $verified), 'old Control plan cannot silently deploy new clean profile');
    $wrong = $plan; $wrong['release']['customer_content_profile_sha256'] = str_repeat('0', 64);
    $reject(fn() => ControlDelivery::validateCustomerBinding($wrong, $verified), 'mismatched profile plan rejected');
    $wrong = $manifest; $wrong['customer_content_audit']['files_checked'] = 0;
    $wrongBytes = json_encode($wrong, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $wrongSig = ControlReleaseBridge::sign($wrongBytes, $name, $key);
    $reject(fn() => ControlReleaseBridge::verify($wrongBytes, $name, $wrongSig, $trust, $archive), 'even a signed report with false counts fails content recomputation');
    // Exact schema emitted by Control process_release_builds.php, using a synthetic signing key.
    $control = ['schema' => 1, 'context' => ControlReleaseBridge::CONTEXT, 'product_code' => 'NAMUA_FINANCE',
        'release_public_id' => '00000000-0000-4000-8000-000000000002', 'version' => $manifest['version'], 'channel' => 'ALPHA',
        'source_commit' => $manifest['source_commit'], 'source_manifest_sha256' => hash_file('sha256', $source . '/app-manifest.json'),
        'build_request_sha256' => hash('sha256', 'synthetic build request'), 'filename' => basename($archive),
        'media_type' => 'application/x-tar', 'size_bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive),
        'build_report_sha256' => hash('sha256', 'synthetic build report'), 'contains_customer_data' => false, 'contains_secrets' => false,
        'packaging' => ['profile_code' => 'CUSTOMER_CLEAN', 'rules_sha256' => $profile->digest(), 'audience' => 'CUSTOMER', 'sample_data' => 'NONE'], 'verification' => []];
    foreach (ControlReleaseBridge::BUILD_GATES as $gate) $control['verification'][$gate] = ['status' => 'PASS', 'evidence_sha256' => hash('sha256', 'synthetic:' . $gate)];
    $verifyControl = static function (array $wire) use ($name, $key, $trust, $archive): array {
        $bytes = json_encode($wire, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return ControlReleaseBridge::verify($bytes, $name, ControlReleaseBridge::sign($bytes, $name, $key), $trust, $archive);
    };
    $modern = $verifyControl($control);
    $check(ControlReleaseBridge::artifactName($control) === basename($archive) && $modern['customer_clean_eligible'], 'Control schema 1 signed package accepted');
    $check($modern['install_manifest']['source_manifest_sha256'] === $manifest['source_manifest_sha256']
        && $modern['install_manifest']['control_app_manifest_sha256'] === $control['source_manifest_sha256'], 'installer keeps app-manifest and inner manifest hashes distinct');
    $check(!isset($modern['install_manifest']['customer_runtime_guard']), 'historical signed packages do not acquire a guard capability');
    $guardedControl = $control; $guardedControl['customer_runtime_guard'] = 'FINANCE_CUSTOMER_SERVER_V1';
    $guarded = $verifyControl($guardedControl);
    $check($guarded['install_manifest']['customer_runtime_guard'] === 'FINANCE_CUSTOMER_SERVER_V1', 'verified new customer guard capability survives normalization');
    $wrongGuard = $control; $wrongGuard['customer_runtime_guard'] = 'UNKNOWN_GUARD';
    $reject(fn() => $verifyControl($wrongGuard), 'unknown signed customer guard capability is rejected');
    require_once dirname(__DIR__).'/install/FinanceInstance.php';
    $instanceReflection = new ReflectionClass(FinanceInstance::class);
    $bareInstance = $instanceReflection->newInstanceWithoutConstructor();
    $instanceConfig = $instanceReflection->getProperty('c'); $instanceConfig->setAccessible(true); $instanceConfig->setValue($bareInstance, []);
    $contextMethod = $instanceReflection->getMethod('customerContext'); $contextMethod->setAccessible(true);
    $reject(fn() => $contextMethod->invoke($bareInstance, $modern['install_manifest']), 'new installer cannot treat historical unguarded code as protected customer runtime');
    ControlDelivery::validateCustomerBinding($plan, $modern);
    $check(true, 'modern Control package matches distribution claims');
    if(in_array('--delivery', $argv, true)){
        require __DIR__.'/c3_control_delivery_runtime_cases.php';
        c3DeliveryRuntimeCases($control,$key,$trust,$archive,$plan['release'],$check);
    }
    foreach (['filename', 'sha256', 'source_manifest_sha256', 'source_commit', 'size_bytes', 'contains_customer_data', 'contains_secrets'] as $field) {
        $wrong = $control;
        $wrong[$field] = $field === 'size_bytes' ? 1 : (str_starts_with($field, 'contains_') ? true : 'invalid');
        $reject(fn() => $verifyControl($wrong), 'Control signed incorrect ' . $field . ' rejected');
    }
    foreach (['profile_code', 'rules_sha256', 'audience', 'sample_data'] as $field) {
        $wrong = $control; $wrong['packaging'][$field] = 'invalid';
        $reject(fn() => $verifyControl($wrong), 'Control wrong profile binding ' . $field . ' rejected');
    }
    foreach (ControlReleaseBridge::BUILD_GATES as $gate) {
        $wrong = $control; unset($wrong['verification'][$gate]);
        $reject(fn() => $verifyControl($wrong), 'Control missing mandatory gate ' . $gate . ' rejected');
    }
    $wrong = $control; $wrong['verification']['clean_install']['status'] = 'FAIL';
    $reject(fn() => $verifyControl($wrong), 'Control failed clean install gate rejected even when signed');
    $wrong = $control; $wrong['source_manifest_sha256'] = $manifest['source_manifest_sha256'];
    $reject(fn() => $verifyControl($wrong), 'inner manifest hash cannot masquerade as Control app manifest hash');
    $reject(fn() => ControlReleaseBridge::artifactName(['schema' => 1, 'filename' => '../bad.tar']), 'unknown format or traversal rejected');
    sodium_memzero($kp); unset($key);

    define('BASEPATH', $extract . '/system/'); define('FCPATH', $extract . '/');
    require $root . '/application/libraries/Customer_publication.php';
    $check(Customer_publication::template(null) === 'customer' && Customer_publication::template('legacy_namua') === 'customer', 'clean runtime never falls back to absent Namua menu');
    $check(Customer_publication::legacy_available($root), 'staging legacy menu remains available and unchanged');
    function base_url(string $path = ''): string { return 'https://customer.example.invalid/' . $path; }
    require $root . '/application/libraries/PosPrinterPreviewService.php';
    $preview = new PosPrinterPreviewService();
    $check($preview->defaultGeneralSettings()['logo_url'] === '', 'fresh printer does not fetch a missing legacy logo or unsupported SVG');
    echo "All {$checks} customer-clean release checks passed (fixture only; no database accessed).\n";
} finally { financeArtifactSignatureRemoveTree($stage); }
