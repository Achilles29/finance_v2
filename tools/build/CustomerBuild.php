<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/release/ControlReleaseBridge.php';
if (!defined('RELEASE_ARTIFACT_LIBRARY_ONLY')) define('RELEASE_ARTIFACT_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/release/build_release_artifact.php';

/** Finance's unsigned NAMUA_PRODUCT_BUILD_V1 adapter. No application bootstrap or signing keys. */
final class CustomerBuild
{
    public static array $diagnostics = [];
    public const PROTOCOL = 'NAMUA_PRODUCT_BUILD_V1';
    public const FIELDS = ['schema', 'protocol', 'request_id', 'product_code', 'release_public_id', 'version',
        'channel', 'source_commit', 'source_manifest_sha256', 'profile_code', 'profile_rules_sha256', 'audience', 'sample_data'];

    public static function need(bool $ok, string $code): void
    {
        if (!$ok) throw new RuntimeException($code);
    }

    public static function canonical(array $request): string
    {
        $ordered = [];
        self::need(count($request) === count(self::FIELDS), 'REQUEST_FIELDS');
        foreach (self::FIELDS as $field) {
            self::need(array_key_exists($field, $request), 'REQUEST_FIELDS');
            $ordered[$field] = $request[$field];
        }
        return json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function validate(array $r, string $root): void
    {
        self::canonical($r);
        self::need($r['schema'] === 1 && $r['protocol'] === self::PROTOCOL && $r['product_code'] === 'NAMUA_FINANCE'
            && $r['profile_code'] === CustomerReleaseProfile::ID && $r['audience'] === 'CUSTOMER' && $r['sample_data'] === 'NONE', 'REQUEST_PROFILE');
        foreach (['request_id', 'release_public_id'] as $k) self::need(is_string($r[$k])
            && preg_match('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/D', $r[$k]) === 1, 'REQUEST_ID');
        self::need(is_string($r['source_commit']) && preg_match('/\A[a-f0-9]{40}\z/D', $r['source_commit']) === 1
            && in_array($r['channel'], ['ALPHA', 'BETA', 'RC', 'STABLE'], true), 'REQUEST_SOURCE');
        $app = ControlReleaseBridge::json((string)file_get_contents($root . '/app-manifest.json'));
        $profile = CustomerReleaseProfile::fromRoot($root);
        self::need($r['version'] === $app['version'] && $r['source_manifest_sha256'] === hash_file('sha256', $root . '/app-manifest.json')
            && $r['profile_rules_sha256'] === $profile->digest(), 'REQUEST_SOURCE_BINDING');
        $p = $app['packaging'] ?? [];
        self::need(($p['contract_version'] ?? null) === 1 && ($p['adapter'] ?? '') === 'tools/build/customer_package.php'
            && ($p['profiles'][0]['rules_path'] ?? '') === CustomerReleaseProfile::PATH
            && ($p['profiles'][0]['rules_sha256'] ?? '') === $profile->digest()
            && ($p['profiles'][0]['code'] ?? '') === $r['profile_code'], 'SOURCE_PACKAGING');
        self::need(self::head($root) === $r['source_commit'] && ReleasePackagePolicy::worktreeClean($root), 'SOURCE_DIRTY_OR_CHANGED');
    }

    public static function head(string $root): string
    {
        return trim(self::run(['git', '-C', $root, 'rev-parse', '--verify', 'HEAD'], $root, 'SOURCE_HEAD')['output']);
    }

    public static function run(array $command, string $cwd, string $code, int $seconds = 180): array
    {
        $result = releaseArtifactRun($command, $cwd, $seconds);
        if ($result['code'] !== 0) {
            // Preflight emits only category/path/line, never the matched secret or source line.
            preg_match_all('/^FINDING ([A-Z0-9_]+) ([A-Za-z0-9_.\/-]+)(?::([0-9]+))?$/m', $result['output'], $findings, PREG_SET_ORDER);
            foreach (array_slice($findings, 0, 15) as $f) self::$diagnostics[] = ['category' => $f[1], 'path' => $f[2], 'line' => (int)($f[3] ?? 0)];
        }
        if ($result['code'] !== 0 && $code === 'PACKAGE_BUILD_GATE_FAILED'
            && preg_match('/RELEASE ARTIFACT BLOCKED reason=([A-Z0-9_]{3,60})/', $result['output'], $match) === 1)
            throw new RuntimeException('PACKAGE_' . $match[1]);
        self::need($result['code'] === 0, $code);
        return $result;
    }

    public static function writeJson(string $path, array $value): void
    {
        $bytes = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $file = @fopen($path, 'x');
        self::need(is_resource($file), 'OUTPUT_EXISTS_OR_UNSAFE');
        try { self::need(fwrite($file, $bytes) === strlen($bytes), 'OUTPUT_WRITE_FAILED'); }
        finally { fclose($file); }
        chmod($path, 0600);
    }

    public static function descriptor(string $path, string $type): array
    {
        self::need(is_file($path) && !is_link($path), 'OUTPUT_UNSAFE');
        return ['file' => basename($path), 'sha256' => hash_file('sha256', $path), 'size_bytes' => filesize($path), 'media_type' => $type];
    }

    public static function paths(string $requestPath, string $output, string $root): void
    {
        self::need(function_exists('posix_geteuid') && posix_geteuid() !== 0, 'UNPRIVILEGED_BUILD_REQUIRED');
        self::need(realpath($requestPath) === $requestPath && is_file($requestPath) && !is_link($requestPath)
            && filesize($requestPath) <= 1048576 && (fileperms($requestPath) & 0077) === 0, 'REQUEST_FILE_UNSAFE');
        self::need(realpath($output) === $output && is_dir($output) && !is_link($output)
            && dirname($output) === dirname($requestPath) && basename($output) === 'output'
            && fileowner($output) === posix_geteuid() && (fileperms($output) & 0077) === 0
            && !str_starts_with($output . '/', $root . '/') && scandir($output) === ['.', '..'], 'OUTPUT_DIRECTORY_UNSAFE');
        self::need(!is_writable($root) && !is_writable($root . '/app-manifest.json'), 'SOURCE_WRITABLE');
    }

    public static function build(array $r, string $root, string $output, string $scratch): array
    {
        self::validate($r, $root);
        self::need(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80200, 'PHP_81_REQUIRED');
        $featureCheck = self::run([PHP_BINARY, $root.'/tools/tests/feature_boundary_contract_smoke.php'], $root, 'FEATURE_BOUNDARY_GATE_FAILED');
        $snapshot = releaseArtifactSnapshot(ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json'), $root, CustomerReleaseProfile::fromRoot($root));
        $static = $scratch . '/static';
        self::need(mkdir($static, 0700) && is_readable('/var/lib/finance-a4-static/vendor/autoload.php')
            && symlink('/var/lib/finance-a4-static/vendor', $static . '/vendor'), 'BUILD_STATIC_RUNTIME_UNAVAILABLE');
        putenv('A4_STATIC_RUNTIME_DIR=' . $static);
        putenv('A4_SECURITY_RUNTIME_DIR=/var/lib/finance-a4-security');
        self::need(mkdir($scratch . '/composer', 0700), 'BUILD_COMPOSER_RUNTIME_UNAVAILABLE');
        putenv('COMPOSER_HOME=' . $scratch . '/composer');
        putenv('COMPOSER_CACHE_DIR=' . $scratch . '/composer/cache');
        putenv('COMPOSER_DISABLE_NETWORK=1');
        $archive = $output . '/finance-' . $r['version'] . '.tar';
        $build = self::run([PHP_BINARY, $root . '/tools/release/build_release_artifact.php', '--root=' . $root,
            '--output=' . $archive, '--profile=CUSTOMER_CLEAN', '--source-epoch=946684800'], $root, 'PACKAGE_BUILD_GATE_FAILED', 900);
        $description = ControlReleaseBridge::describe($root, $archive);
        self::need($description['source_commit'] === $r['source_commit'], 'PACKAGE_SOURCE_CHANGED');
        // Archive inspected by trusted Finance code before extraction. Never require PHP from it.
        $releaseRoot = $scratch . '/release'; self::need(mkdir($releaseRoot, 0700), 'BUILD_WORKSPACE_FAILED');
        self::run(['/usr/bin/tar', '--extract', '--no-same-owner', '--file=' . $archive, '--directory=' . $releaseRoot], $root, 'EXTRACT_FAILED');
        require_once __DIR__ . '/DisposableBuildDatabase.php';
        $database = DisposableBuildDatabase::test($releaseRoot, $scratch);
        self::validate($r, $root);
        self::need($snapshot === releaseArtifactSnapshot(ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json'), $root,
            CustomerReleaseProfile::fromRoot($root)), 'SOURCE_BYTES_CHANGED');
        $binding = ['schema' => 1, 'protocol' => self::PROTOCOL, 'status' => 'PASS', 'request_id' => $r['request_id'],
            'request_sha256' => hash('sha256', self::canonical($r)), 'product_code' => $r['product_code'],
            'release_public_id' => $r['release_public_id'], 'version' => $r['version'], 'source_commit' => $r['source_commit'],
            'source_manifest_sha256' => $r['source_manifest_sha256'],
            'profile' => ['code' => $r['profile_code'], 'rules_sha256' => $r['profile_rules_sha256'], 'audience' => $r['audience'], 'sample_data' => $r['sample_data']]];
        $evidence = [
            'source_clean' => ['commit' => $r['source_commit'], 'clean' => true],
            'security_scan' => ['builder_exit' => $build['code'], 'builder_output_sha256' => hash('sha256', $build['output']), 'feature_boundary_sha256'=>hash('sha256',$featureCheck['output']), 'required_gates' => ['preflight', 'phpstan', 'osv_offline', 'feature_boundary_contract']],
            'install_test' => $database['health'], 'backup_restore' => $database['restore'],
            'customer_data_scan' => $description['customer_content_audit'] + ['database' => $database['clean']],
            'secrets_scan' => ['preflight_passed' => true, 'artifact_sha256' => $description['sha256']],
            'clean_install' => $database['clean'],
            'source_untouched' => ['commit_before' => $r['source_commit'], 'commit_after' => self::head($root), 'files' => count($snapshot), 'same_bytes' => true],
        ];
        $summaries = ['source_clean' => 'Source committed dan bersih.', 'security_scan' => 'Preflight, PHPStan dan OSV offline lulus.',
            'install_test' => 'Baseline, migrasi, owner sintetis dan health-check lulus pada MariaDB disposable.',
            'backup_restore' => 'Dump database disposable direstore; isi semua tabel dan health-check cocok.',
            'customer_data_scan' => 'Allowlist file, hash aset/SQL dan semua tabel non-referensi diperiksa.',
            'secrets_scan' => 'Pemindaian secret source oleh preflight lulus; paket berasal dari snapshot yang sama.',
            'clean_install' => 'Tidak ada master/transaksi/customer/dummy pada database sebelum owner uji dibuat.',
            'source_untouched' => 'HEAD, status Git dan seluruh byte paket source tetap sama; database development tidak dibuka.'];
        $gates = [];
        foreach ($evidence as $gate => $data) $gates[$gate] = ['status' => 'PASS', 'evidence_sha256' => hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'summary' => $summaries[$gate]];
        $package = self::descriptor($archive, 'application/x-tar');
        $all = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json')->enumerate($root);
        $report = $binding + ['package' => array_diff_key($package, ['media_type' => true]),
            'assertions' => ['contains_customer_data' => false, 'contains_secrets' => false, 'source_untouched' => true, 'clean_install' => true],
            'metrics' => ['included_files' => count($snapshot), 'excluded_files' => $all['excluded_files'] + count($all['files']) - count($snapshot),
                'system_seed_records' => $database['clean']['system_seed_records'], 'generic_sample_records' => 0, 'customer_data_findings' => 0, 'secret_findings' => 0],
            'gates' => $gates, 'details' => $evidence];
        self::writeJson($output . '/build-report.json', $report);
        return $binding + ['gates' => $gates, 'artifacts' => ['application_package' => $package,
            'build_report' => self::descriptor($output . '/build-report.json', 'application/json')]];
    }
}
