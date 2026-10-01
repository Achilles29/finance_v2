<?php

declare(strict_types=1);

require_once __DIR__ . '/ReleasePackagePolicy.php';
require_once __DIR__ . '/CustomerReleaseProfile.php';

const RELEASE_ARTIFACT_DEFAULT_EPOCH = 946684800;

function releaseArtifactRun(array $command, string $cwd, int $timeout = 180): array
{
    $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        return ['code' => 127, 'output' => 'process unavailable'];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $started = microtime(true);
    $output = '';
    $code = null;
    while (true) {
        foreach ([1, 2] as $index) {
            $chunk = stream_get_contents($pipes[$index]);
            if (is_string($chunk)) {
                $output = substr($output . $chunk, -12000);
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            $code = (int)$status['exitcode'];
            break;
        }
        if (microtime(true) - $started >= $timeout) {
            proc_terminate($process, 9);
            $code = 124;
            break;
        }
        usleep(20000);
    }
    foreach ([1, 2] as $index) {
        $chunk = stream_get_contents($pipes[$index]);
        if (is_string($chunk)) {
            $output = substr($output . $chunk, -12000);
        }
        fclose($pipes[$index]);
    }
    $closed = proc_close($process);
    if (($code === null || $code < 0) && $closed >= 0) {
        $code = $closed;
    }
    return ['code' => $code ?? 1, 'output' => trim($output)];
}

function releaseArtifactSnapshot(ReleasePackagePolicy $policy, string $root, ?CustomerReleaseProfile $profile = null): array
{
    $enumerated = $policy->enumerate($root);
    if ($enumerated['issues'] !== []) {
        throw new RuntimeException($enumerated['issues'][0]['category'] . ' ' . $enumerated['issues'][0]['path']);
    }
    $entries = [];
    foreach ($enumerated['files'] as $relative) {
        if ($profile !== null && !$profile->allows($relative)) continue;
        $absolute = $root . '/' . $relative;
        clearstatcache(true, $absolute);
        $data = @file_get_contents($absolute);
        $stat = @stat($absolute);
        if (!is_string($data) || !is_array($stat) || ReleasePackagePolicy::absoluteFileProblem($absolute) !== null) {
            throw new RuntimeException('PACKAGE_FILE_UNREADABLE ' . $relative);
        }
        $entries[$relative] = [
            'path' => $relative,
            'sha256' => hash('sha256', $data),
            'size' => strlen($data),
            'mode' => (($stat['mode'] & 0111) !== 0 ? '0755' : '0644'),
        ];
    }
    ksort($entries, SORT_STRING);
    return $entries;
}

function releaseArtifactRemoveTree(string $directory): void
{
    if (!is_dir($directory) || is_link($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}

if (defined('RELEASE_ARTIFACT_LIBRARY_ONLY') && RELEASE_ARTIFACT_LIBRARY_ONLY) {
    return;
}

$options = ['root' => dirname(__DIR__, 2), 'output' => null, 'source_epoch' => null, 'profile' => CustomerReleaseProfile::ID];
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (strpos($argument, '--root=') === 0) {
        $options['root'] = substr($argument, 7);
    } elseif (strpos($argument, '--output=') === 0) {
        $options['output'] = substr($argument, 9);
    } elseif (strpos($argument, '--source-epoch=') === 0) {
        $options['source_epoch'] = substr($argument, 15);
    } elseif (strpos($argument, '--profile=') === 0) {
        $options['profile'] = substr($argument, 10);
    } else {
        fwrite(STDERR, "Usage: php build_release_artifact.php --output=/outside/release.tar [--root=PATH] [--source-epoch=EPOCH] [--profile=CUSTOMER_CLEAN|LEGACY_INTERNAL]\n");
        exit(2);
    }
}

$root = realpath((string)$options['root']);
$output = (string)$options['output'];
$outputDirectory = $output !== '' ? realpath(dirname($output)) : false;
$epochRaw = $options['source_epoch'] ?? (($environmentEpoch = getenv('SOURCE_DATE_EPOCH')) !== false && $environmentEpoch !== '' ? $environmentEpoch : (string)RELEASE_ARTIFACT_DEFAULT_EPOCH);
if ($root === false || $outputDirectory === false || $output === '' || substr($output, -4) !== '.tar'
    || !is_writable($outputDirectory) || strpos($outputDirectory . '/', $root . '/') === 0 || $outputDirectory === $root
    || preg_match('/\A(?:0|[1-9][0-9]{0,10})\z/D', (string)$epochRaw) !== 1 || (int)$epochRaw > 253402300799
    || file_exists($output) || is_link($output)
    || !in_array($options['profile'], [CustomerReleaseProfile::ID, 'LEGACY_INTERNAL'], true)
) {
    fwrite(STDERR, "RELEASE ARTIFACT BLOCKED reason=INVALID_ARGUMENT_OR_OUTPUT\n");
    exit(2);
}
$epoch = (int)$epochRaw;
$stage = sys_get_temp_dir() . '/finance-release-stage-' . bin2hex(random_bytes(8));
$temporaryArchive = $outputDirectory . '/.finance-release-' . bin2hex(random_bytes(8)) . '.tmp';
$listPath = $stage . '.list';
try {
    $policy = ReleasePackagePolicy::fromFile($root . '/tools/release/package_policy.json');
    if (!ReleasePackagePolicy::worktreeClean($root)) {
        throw new RuntimeException('SOURCE_WORKTREE_DIRTY');
    }
    $profile = $options['profile'] === CustomerReleaseProfile::ID ? CustomerReleaseProfile::fromRoot($root) : null;
    $before = releaseArtifactSnapshot($policy, $root, $profile);
    if ($profile !== null) $profile->audit(array_values($before));
    foreach ([
        ['PREFLIGHT', 'a4_release_preflight_smoke.php'],
        ['VULNERABILITY', 'a4_dependency_vulnerability_smoke.php'],
        ['STATIC', 'a4_static_analysis_smoke.php'],
    ] as [$label, $script]) {
        $gate = releaseArtifactRun([PHP_BINARY, $root . '/tools/tests/' . $script], $root, $label === 'STATIC' ? 420 : 180);
        if ($gate['code'] !== 0) {
            if ($gate['output'] !== '') {
                fwrite(STDERR, $gate['output'] . PHP_EOL);
            }
            throw new RuntimeException('GATE_' . $label . '_FAILED');
        }
    }
    if (!ReleasePackagePolicy::worktreeClean($root) || $before !== releaseArtifactSnapshot($policy, $root, $profile)) {
        throw new RuntimeException('SOURCE_MUTATED_DURING_BUILD');
    }
    if (!mkdir($stage, 0700)) {
        throw new RuntimeException('STAGING_CREATE_FAILED');
    }
    foreach ($before as $relative => $entry) {
        $destination = $stage . '/' . ($profile!==null && $profile->version()>=6 ? CustomerLayout::target($relative) : $relative);
        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
            throw new RuntimeException('STAGING_CREATE_FAILED');
        }
        $data = @file_get_contents($root . '/' . $relative);
        if (!is_string($data) || !hash_equals($entry['sha256'], hash('sha256', $data))
            || file_put_contents($destination, $data, LOCK_EX) !== strlen($data)
            || !chmod($destination, octdec($entry['mode'])) || !touch($destination, $epoch)
        ) {
            throw new RuntimeException('SOURCE_MUTATED_DURING_BUILD');
        }
    }
    $packaged=$profile!==null && $profile->version()>=6 ? CustomerLayout::entries($before) : $before;
    $manifest = ['schema' => 'finance.release-artifact-manifest', 'schema_version' => 1,
        'source_epoch' => $epoch, 'files' => array_values($packaged)];
    $manifestData = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    $manifestPath = $stage . '/RELEASE-MANIFEST.json';
    if (file_put_contents($manifestPath, $manifestData, LOCK_EX) !== strlen($manifestData)
        || !chmod($manifestPath, 0644) || !touch($manifestPath, $epoch)
    ) {
        throw new RuntimeException('MANIFEST_WRITE_FAILED');
    }
    $archivePaths = array_merge(array_keys($packaged), ['RELEASE-MANIFEST.json']);
    sort($archivePaths, SORT_STRING);
    if (file_put_contents($listPath, implode("\0", $archivePaths) . "\0", LOCK_EX) === false) {
        throw new RuntimeException('FILE_LIST_WRITE_FAILED');
    }
    $tar = releaseArtifactRun(['/usr/bin/tar', '--create', '--format=gnu', '--sort=name', '--no-recursion',
        '--mtime=@' . $epoch, '--owner=0', '--group=0', '--numeric-owner', '--null', '--directory=' . $stage,
        '--files-from=' . $listPath, '--file=' . $temporaryArchive], $root);
    if ($tar['code'] !== 0 || !is_file($temporaryArchive) || !chmod($temporaryArchive, 0644)) {
        throw new RuntimeException('ARCHIVE_CREATE_FAILED');
    }
    if (!ReleasePackagePolicy::worktreeClean($root) || $before !== releaseArtifactSnapshot($policy, $root, $profile)) {
        throw new RuntimeException('SOURCE_MUTATED_DURING_BUILD');
    }
    if (!rename($temporaryArchive, $output)) {
        throw new RuntimeException('ATOMIC_RENAME_FAILED');
    }
    echo 'RELEASE ARTIFACT PASS files=' . count($before) . ' source_epoch=' . $epoch . ' sha256=' . hash_file('sha256', $output) . PHP_EOL;
    $exit = 0;
} catch (Throwable $exception) {
    @unlink($temporaryArchive);
    fwrite(STDERR, 'RELEASE ARTIFACT BLOCKED reason=' . preg_replace('/[^A-Z0-9_.-]/', '_', strtoupper($exception->getMessage())) . PHP_EOL);
    $exit = 1;
} finally {
    @unlink($listPath);
    releaseArtifactRemoveTree($stage);
}
exit($exit);
