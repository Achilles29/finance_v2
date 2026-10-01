<?php

declare(strict_types=1);

require dirname(__DIR__) . '/release/ReleasePackagePolicy.php';

$checks = 0;
$failures = [];
$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo 'PASS: ' . $label . PHP_EOL;
    } else {
        $failures[] = $label;
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
    }
};
$run = static function (array $command, string $cwd, array $environment = []): array {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $environment === [] ? null : array_merge($_ENV, $environment));
    if (!is_resource($process)) {
        return ['code' => 127, 'output' => ''];
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'output' => substr((string)$output, -12000)];
};
$remove = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
};

$base = sys_get_temp_dir() . '/finance-release-artifact-contract-' . bin2hex(random_bytes(6));
$root = $base . '/source';
$outputDirectory = $base . '/artifacts';
$extract = $base . '/extract';
mkdir($root . '/application/config', 0700, true);
mkdir($root . '/.codex', 0700, true);
mkdir($root . '/application/controllers', 0700, true);
mkdir($root . '/application/libraries', 0700, true);
mkdir($root . '/application/logs', 0700, true);
mkdir($root . '/application/views/audit', 0700, true);
mkdir($root . '/backup', 0700, true);
mkdir($root . '/docs', 0700, true);
mkdir($root . '/uploads/customer', 0700, true);
mkdir($root . '/assets/uploads/business-profile-logo', 0700, true);
mkdir($root . '/vendor/package', 0700, true);
mkdir($root . '/tools/release', 0700, true);
mkdir($root . '/tools/tests', 0700, true);
mkdir($outputDirectory, 0700, true);
mkdir($extract, 0700, true);
register_shutdown_function(static function () use ($remove, $base): void { $remove($base); });

copy(dirname(__DIR__) . '/release/package_policy.json', $root . '/tools/release/package_policy.json');
file_put_contents($root . '/.codex/internal_audit_dashboard.enabled', "enabled-v1\n");
file_put_contents($root . '/.user.ini', "open_basedir=/legacy/staging/path/:/tmp/\n");
file_put_contents($root . '/application/index.php', "<?php\necho 'fixture';\n");
file_put_contents($root . '/application/config/app.ini', "name=fixture\n");
file_put_contents($root . '/application/config/.env', "PASSWORD=denied-secret\n");
file_put_contents($root . '/application/controllers/Audit.php', "<?php // internal roadmap controller\n");
file_put_contents($root . '/application/libraries/AuditRoadmapReader.php', "<?php // internal roadmap reader\n");
file_put_contents($root . '/application/logs/runtime.log', "denied log\n");
file_put_contents($root . '/application/views/audit/roadmap.php', "<?php // internal roadmap view\n");
file_put_contents($root . '/backup/customer.sql.gz', 'denied backup');
file_put_contents($root . '/docs/_NOTE.md', "internal note\n");
file_put_contents($root . '/docs/_NOTE2.md', "private owner note\n");
file_put_contents($root . '/docs/2026-09-05_internal.md', "internal dated roadmap\n");
file_put_contents($root . '/docs/customer-guide.md', "customer guide\n");
file_put_contents($root . '/uploads/customer/file.txt', 'denied upload');
file_put_contents($root . '/assets/uploads/business-profile-logo/customer.png', 'denied customer logo even if accidentally tracked');
file_put_contents($root . '/vendor/package/library.php', "<?php // denied vendor\n");
$gateTemplate = <<<'PHP'
<?php
$trace = getenv('A4_ARTIFACT_TRACE');
if (is_string($trace) && $trace !== '') {
    file_put_contents($trace, basename(__FILE__) . "\n", FILE_APPEND | LOCK_EX);
}
if (basename(__FILE__) === 'a4_release_preflight_smoke.php') {
    $mutate = getenv('A4_ARTIFACT_MUTATE');
    if (is_string($mutate) && $mutate !== '') {
        file_put_contents($mutate, "mutated\n", FILE_APPEND | LOCK_EX);
    }
}
echo "PASS\n";
PHP;
foreach (['a4_release_preflight_smoke.php', 'a4_static_analysis_smoke.php', 'a4_dependency_vulnerability_smoke.php'] as $gate) {
    file_put_contents($root . '/tools/tests/' . $gate, $gateTemplate);
}
$run(['git', 'init', '-q'], $root);
$run(['git', 'add', '-f', '.'], $root);
$run(['git', '-c', 'user.name=Finance Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'fixture'], $root);

$builder = dirname(__DIR__) . '/release/build_release_artifact.php';
$artifactA = $outputDirectory . '/release-a.tar';
$artifactB = $outputDirectory . '/release-b.tar';
$trace = $base . '/gate-trace.txt';
$environment = ['A4_ARTIFACT_TRACE' => $trace, 'TZ' => 'UTC'];
$arguments = static fn(string $output): array => [PHP_BINARY, $builder, '--root=' . $root, '--output=' . $output, '--source-epoch=1700000000', '--profile=LEGACY_INTERNAL'];
$buildA = $run($arguments($artifactA), $root, $environment);
$buildB = $run($arguments($artifactB), $root, $environment);
$check($buildA['code'] === 0 && $buildB['code'] === 0, 'two fixture builds pass all mandatory gates');
$check(is_file($artifactA) && hash_file('sha256', $artifactA) === hash_file('sha256', $artifactB), 'two builds are byte-identical');
$expectedTrace = array_merge(...array_fill(0, 2, ['a4_release_preflight_smoke.php', 'a4_dependency_vulnerability_smoke.php', 'a4_static_analysis_smoke.php']));
$actualTrace = is_file($trace) ? file($trace, FILE_IGNORE_NEW_LINES) : [];
$check($actualTrace === $expectedTrace, 'builder runs preflight and vulnerability before slower static analysis, retaining all gates');

$policy = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json');
$candidate = $policy->enumerate($root);
$listing = $run(['/usr/bin/tar', '-tf', $artifactA], $root);
$archivePaths = array_values(array_filter(preg_split('/\R/', trim($listing['output'])) ?: []));
$expectedPaths = array_merge($candidate['files'], ['RELEASE-MANIFEST.json']);
sort($expectedPaths, SORT_STRING);
$check($listing['code'] === 0 && $archivePaths === $expectedPaths, 'archive is exactly the sorted candidate set plus manifest');
$denied = [
    '.codex/internal_audit_dashboard.enabled',
    'application/config/.env',
    'application/controllers/Audit.php',
    'application/libraries/AuditRoadmapReader.php',
    'application/logs/runtime.log',
    'application/views/audit/roadmap.php',
    'backup/customer.sql.gz',
    'docs/_NOTE.md',
    'docs/_NOTE2.md',
    'docs/2026-09-05_internal.md',
    'uploads/customer/file.txt',
    'assets/uploads/business-profile-logo/customer.png',
    '.user.ini',
    'vendor/package/library.php',
];
$check(array_intersect($denied, $archivePaths) === [], 'runtime, customer data, secrets, and internal audit paths are excluded');
$check(in_array('docs/customer-guide.md', $archivePaths, true), 'non-internal customer documentation remains packageable');
$check(ReleasePackagePolicy::worktreeClean($root), 'fixture release source starts from one clean committed worktree');

$run(['/usr/bin/tar', '-xf', $artifactA, '-C', $extract], $root);
$manifest = json_decode((string)file_get_contents($extract . '/RELEASE-MANIFEST.json'), true);
$hashesMatch = is_array($manifest) && ($manifest['source_epoch'] ?? null) === 1700000000;
foreach ($manifest['files'] ?? [] as $entry) {
    $path = is_array($entry) ? ($entry['path'] ?? '') : '';
    $hashesMatch = $hashesMatch && in_array($path, $candidate['files'], true)
        && hash_file('sha256', $extract . '/' . $path) === ($entry['sha256'] ?? null);
}
$check($hashesMatch && count($manifest['files'] ?? []) === count($candidate['files']), 'manifest SHA256 matches every archived candidate');
$verbose = $run(['/usr/bin/tar', '--utc', '--numeric-owner', '--full-time', '-tvf', $artifactA], $root);
$check($verbose['code'] === 0 && strpos($verbose['output'], ' 0/0 ') !== false && strpos($verbose['output'], '2023-11-14 22:13:20') !== false, 'archive owner, group, and mtime are normalized');

$link = $root . '/application/linked.php';
$linkCreated = @symlink($root . '/application/index.php', $link);
if ($linkCreated) {
    $run(['git', 'add', '-f', 'application/linked.php'], $root);
}
$symlinkArtifact = $outputDirectory . '/symlink.tar';
$symlinkBuild = $run($arguments($symlinkArtifact), $root, $environment);
$check($linkCreated && $symlinkBuild['code'] !== 0 && !file_exists($symlinkArtifact), 'symlink candidate fails without a final artifact');
if ($linkCreated) {
    unlink($link);
    $run(['git', 'rm', '--cached', '-q', 'application/linked.php'], $root);
}

$untrackedDirectory = $root . '/scripts';
mkdir($untrackedDirectory, 0700, true);
$untracked = $untrackedDirectory . '/local-helper.php';
file_put_contents($untracked, "<?php // local-only helper\n");
$untrackedArtifact = $outputDirectory . '/untracked.tar';
$untrackedBuild = $run($arguments($untrackedArtifact), $root, $environment);
$check(
    $untrackedBuild['code'] !== 0
        && !file_exists($untrackedArtifact)
        && strpos($untrackedBuild['output'], 'SOURCE_WORKTREE_DIRTY') !== false,
    'untracked local source blocks the release instead of entering its candidate set'
);
unlink($untracked);
rmdir($untrackedDirectory);

$mutationArtifact = $outputDirectory . '/mutation.tar';
$mutationEnvironment = $environment + ['A4_ARTIFACT_MUTATE' => $root . '/application/index.php'];
$mutationBuild = $run($arguments($mutationArtifact), $root, $mutationEnvironment);
$check($mutationBuild['code'] !== 0 && !file_exists($mutationArtifact), 'source mutation fails without a final artifact');

echo 'A4 RELEASE ARTIFACT CONTRACT ' . ($failures === [] ? 'PASS' : 'FAIL')
    . ' passed=' . ($checks - count($failures)) . ' failed=' . count($failures) . PHP_EOL;
exit($failures === [] ? 0 : 1);
