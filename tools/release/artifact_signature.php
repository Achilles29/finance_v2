<?php

declare(strict_types=1);

final class FinanceArtifactSignatureFailure extends RuntimeException
{
    public string $failureCode;

    public function __construct(string $failureCode, string $message)
    {
        parent::__construct($message);
        $this->failureCode = $failureCode;
    }
}

function financeArtifactSignatureFail(string $code, string $message): void
{
    throw new FinanceArtifactSignatureFailure($code, $message);
}

function financeArtifactSignatureExactKeys(array $value, array $keys): bool
{
    return array_keys($value) === $keys;
}

/** @return mixed */
function financeArtifactSignatureCanonicalValue($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_keys($value) !== range(0, count($value) - 1)) {
        ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $item) {
        $value[$key] = financeArtifactSignatureCanonicalValue($item);
    }
    return $value;
}

function financeArtifactSignatureCanonicalJson(array $value): string
{
    $json = json_encode(financeArtifactSignatureCanonicalValue($value), JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        financeArtifactSignatureFail('json_encode_failed', 'Canonical JSON could not be encoded.');
    }
    return $json;
}

function financeArtifactSignatureRequireSodium(): void
{
    foreach (['sodium_crypto_sign_keypair', 'sodium_crypto_sign_detached', 'sodium_crypto_sign_verify_detached'] as $function) {
        if (!function_exists($function)) {
            financeArtifactSignatureFail('sodium_unavailable', 'The PHP sodium extension is required.');
        }
    }
}

function financeArtifactSignatureProjectRoot(): string
{
    $root = realpath(dirname(__DIR__, 2));
    if (!is_string($root)) {
        financeArtifactSignatureFail('project_root_unavailable', 'Project root could not be resolved.');
    }
    return $root;
}

function financeArtifactSignatureInsideProject(string $path): bool
{
    $root = financeArtifactSignatureProjectRoot();
    return $path === $root || strpos($path . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) === 0;
}

function financeArtifactSignatureSafeParent(string $path): string
{
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR || substr($path, -1) === DIRECTORY_SEPARATOR) {
        financeArtifactSignatureFail('path_invalid', 'Path must be an absolute file path.');
    }
    $parent = realpath(dirname($path));
    if (!is_string($parent) || is_link(dirname($path)) || !is_dir($parent)
        || $path !== $parent . DIRECTORY_SEPARATOR . basename($path)
    ) {
        financeArtifactSignatureFail('parent_unsafe', 'Output parent must be a canonical directory.');
    }
    $stat = @lstat($parent);
    if (!is_array($stat) || !function_exists('posix_geteuid') || ($stat['uid'] ?? -1) !== posix_geteuid()
        || (($stat['mode'] ?? 0) & 0022) !== 0
    ) {
        financeArtifactSignatureFail('parent_unsafe', 'Output parent must be owned by this process and not group/world writable.');
    }
    return $parent;
}

function financeArtifactSignatureRegularFile(string $path, string $code): string
{
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR || is_link($path) || !is_file($path)) {
        financeArtifactSignatureFail($code, 'Input must be an absolute regular non-symlink file.');
    }
    $real = realpath($path);
    if (!is_string($real) || $real !== $path || !is_readable($real)) {
        financeArtifactSignatureFail($code, 'Input file must be canonical and readable.');
    }
    return $real;
}

function financeArtifactSignatureExactMode(string $path, int $mode): bool
{
    clearstatcache(true, $path);
    $actual = @fileperms($path);
    return is_int($actual) && ($actual & 0777) === $mode;
}

function financeArtifactSignatureAtomicWrite(string $path, string $data, int $mode): void
{
    $parent = financeArtifactSignatureSafeParent($path);
    if (@lstat($path) !== false) {
        financeArtifactSignatureFail('output_exists', 'Output already exists.');
    }
    if (!function_exists('fsync')) {
        financeArtifactSignatureFail('fsync_unavailable', 'Required filesystem sync primitive is unavailable.');
    }
    $temporary = $parent . DIRECTORY_SEPARATOR . '.' . basename($path) . '.tmp-' . bin2hex(random_bytes(8));
    $handle = @fopen($temporary, 'xb');
    if (!is_resource($handle)) {
        financeArtifactSignatureFail('output_write_failed', 'Temporary output could not be created.');
    }
    $published = false;
    try {
        if (!chmod($temporary, $mode)) {
            financeArtifactSignatureFail('output_mode_failed', 'Output mode could not be secured.');
        }
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));
            if (!is_int($written) || $written < 1) {
                financeArtifactSignatureFail('output_write_failed', 'Output write was incomplete.');
            }
            $offset += $written;
        }
        if (!fflush($handle) || !fsync($handle)) {
            financeArtifactSignatureFail('output_sync_failed', 'Output could not be synchronized.');
        }
        fclose($handle);
        $handle = null;
        if (!financeArtifactSignatureExactMode($temporary, $mode) || @lstat($path) !== false || !rename($temporary, $path)) {
            financeArtifactSignatureFail('output_publish_failed', 'Output could not be atomically published.');
        }
        $published = true;
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (!$published) {
            @unlink($temporary);
        }
    }
}

/** @return array{code:int,stdout:string,stderr:string,overflow:bool} */
function financeArtifactSignatureRun(array $command, int $maxBytes = 8388608): array
{
    $process = @proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, []);
    if (!is_resource($process)) {
        return ['code' => 127, 'stdout' => '', 'stderr' => '', 'overflow' => false];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $overflow = false;
    $deadline = microtime(true) + 30;
    $exit = null;
    while (true) {
        foreach ([1 => 'stdout', 2 => 'stderr'] as $index => $target) {
            $chunk = stream_get_contents($pipes[$index]);
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            if ($target === 'stdout') {
                $stdout .= $chunk;
                $overflow = $overflow || strlen($stdout) > $maxBytes;
            } else {
                $stderr .= $chunk;
                $overflow = $overflow || strlen($stderr) > 65536;
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exit = (int)$status['exitcode'];
            break;
        }
        if ($overflow || microtime(true) >= $deadline) {
            proc_terminate($process, 9);
            $exit = 124;
            break;
        }
        usleep(10000);
    }
    foreach ([1 => 'stdout', 2 => 'stderr'] as $index => $target) {
        $chunk = stream_get_contents($pipes[$index]);
        if (is_string($chunk)) {
            $target === 'stdout' ? $stdout .= $chunk : $stderr .= $chunk;
        }
        fclose($pipes[$index]);
    }
    $closed = proc_close($process);
    if (($exit === null || $exit < 0) && $closed >= 0) {
        $exit = $closed;
    }
    return ['code' => $exit ?? 1, 'stdout' => $stdout, 'stderr' => $stderr, 'overflow' => $overflow];
}

function financeArtifactSignatureTarBinary(): string
{
    $path = '/usr/bin/tar';
    $stat = @lstat($path);
    if (!is_array($stat) || is_link($path) || !is_file($path) || !is_executable($path)
        || realpath($path) !== $path || ($stat['uid'] ?? -1) !== 0 || (($stat['mode'] ?? 0) & 0022) !== 0
    ) {
        financeArtifactSignatureFail('tar_untrusted', 'Host-approved tar verifier is unavailable or unsafe.');
    }
    return $path;
}

function financeArtifactSignatureRemoveTree(string $directory): void
{
    if (!is_dir($directory) || is_link($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}

/** @return array{manifest_sha256:string,source_epoch:int,policy_digests:array<string,string>} */
function financeArtifactSignatureInspectManifest(string $artifact): array
{
    $listing = financeArtifactSignatureRun([financeArtifactSignatureTarBinary(), '--list', '--file=' . $artifact]);
    if ($listing['code'] !== 0 || $listing['overflow']) {
        financeArtifactSignatureFail('archive_invalid', 'Artifact archive listing failed.');
    }
    $paths = $listing['stdout'] === '' ? [] : preg_split('/\n/', rtrim($listing['stdout'], "\n"));
    if (!is_array($paths) || $paths === [] || count($paths) !== count(array_unique($paths))) {
        financeArtifactSignatureFail('archive_paths_invalid', 'Artifact paths are empty or duplicated.');
    }
    foreach ($paths as $path) {
        if ($path === '' || $path[0] === '/' || strpos($path, '\\') !== false
            || preg_match('/[\x00-\x1f\x7f]/', $path) === 1
            || in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)
        ) {
            financeArtifactSignatureFail('archive_paths_invalid', 'Artifact contains an unsafe path.');
        }
    }
    $verbose = financeArtifactSignatureRun([
        financeArtifactSignatureTarBinary(), '--list', '--verbose', '--numeric-owner', '--file=' . $artifact,
    ]);
    $verboseLines = $verbose['stdout'] === '' ? [] : preg_split('/\n/', rtrim($verbose['stdout'], "\n"));
    if ($verbose['code'] !== 0 || $verbose['overflow'] || !is_array($verboseLines)
        || count($verboseLines) !== count($paths)
    ) {
        financeArtifactSignatureFail('archive_types_invalid', 'Artifact entry types could not be verified.');
    }
    foreach ($verboseLines as $line) {
        if ($line === '' || $line[0] !== '-') {
            financeArtifactSignatureFail('archive_types_invalid', 'Artifact may contain regular files only.');
        }
    }
    if (count(array_keys($paths, 'RELEASE-MANIFEST.json', true)) !== 1) {
        financeArtifactSignatureFail('manifest_missing', 'Artifact must contain exactly one release manifest.');
    }
    $read = financeArtifactSignatureRun([
        financeArtifactSignatureTarBinary(), '--extract', '--to-stdout', '--file=' . $artifact, '--', 'RELEASE-MANIFEST.json',
    ]);
    if ($read['code'] !== 0 || $read['overflow'] || $read['stdout'] === '') {
        financeArtifactSignatureFail('manifest_unreadable', 'Release manifest could not be read safely.');
    }
    try {
        $manifest = json_decode($read['stdout'], true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        financeArtifactSignatureFail('manifest_malformed', 'Release manifest JSON is malformed.');
    }
    if (!is_array($manifest) || !financeArtifactSignatureExactKeys($manifest, ['schema', 'schema_version', 'source_epoch', 'files'])
        || $manifest['schema'] !== 'finance.release-artifact-manifest' || $manifest['schema_version'] !== 1
        || !is_int($manifest['source_epoch']) || $manifest['source_epoch'] < 0 || $manifest['source_epoch'] > 2147483647
        || !is_array($manifest['files']) || $manifest['files'] === []
    ) {
        financeArtifactSignatureFail('manifest_schema_invalid', 'Release manifest schema is invalid.');
    }
    $manifestPaths = [];
    $hashes = [];
    foreach ($manifest['files'] as $entry) {
        if (!is_array($entry) || !financeArtifactSignatureExactKeys($entry, ['path', 'sha256', 'size', 'mode'])
            || !is_string($entry['path']) || $entry['path'] === 'RELEASE-MANIFEST.json'
            || $entry['path'] === '' || $entry['path'][0] === '/' || strpos($entry['path'], '\\') !== false
            || preg_match('/[\x00-\x1f\x7f]/', $entry['path']) === 1
            || in_array('..', explode('/', $entry['path']), true) || in_array('.', explode('/', $entry['path']), true)
            || !is_string($entry['sha256']) || preg_match('/^[a-f0-9]{64}$/D', $entry['sha256']) !== 1
            || !is_int($entry['size']) || $entry['size'] < 0 || !in_array($entry['mode'], ['0644', '0755'], true)
        ) {
            financeArtifactSignatureFail('manifest_entry_invalid', 'Release manifest contains an invalid file entry.');
        }
        $manifestPaths[] = $entry['path'];
        $hashes[$entry['path']] = $entry['sha256'];
    }
    if (count($manifestPaths) !== count(array_unique($manifestPaths))) {
        financeArtifactSignatureFail('manifest_entry_invalid', 'Release manifest contains duplicate paths.');
    }
    $expectedPaths = array_merge($manifestPaths, ['RELEASE-MANIFEST.json']);
    sort($expectedPaths, SORT_STRING);
    $listedPaths = $paths;
    sort($listedPaths, SORT_STRING);
    if ($listedPaths !== $expectedPaths) {
        financeArtifactSignatureFail('manifest_archive_mismatch', 'Archive paths do not match the release manifest.');
    }
    $stage = sys_get_temp_dir() . '/finance-artifact-verify-' . bin2hex(random_bytes(8));
    if (!mkdir($stage, 0700)) {
        financeArtifactSignatureFail('verification_stage_failed', 'Private artifact verification stage could not be created.');
    }
    try {
        $extract = financeArtifactSignatureRun([
            financeArtifactSignatureTarBinary(), '--extract', '--no-same-owner', '--no-same-permissions',
            '--directory=' . $stage, '--file=' . $artifact,
        ]);
        if ($extract['code'] !== 0 || $extract['overflow']) {
            financeArtifactSignatureFail('archive_extract_invalid', 'Artifact could not be safely inspected.');
        }
        foreach ($manifest['files'] as $entry) {
            $path = $stage . DIRECTORY_SEPARATOR . $entry['path'];
            $real = realpath($path);
            $size = @filesize($path);
            $hash = @hash_file('sha256', $path);
            if (is_link($path) || !is_file($path) || !is_string($real)
                || strpos($real, $stage . DIRECTORY_SEPARATOR) !== 0
                || !is_int($size) || $size !== $entry['size']
                || !is_string($hash) || !hash_equals($entry['sha256'], $hash)
            ) {
                financeArtifactSignatureFail('manifest_content_mismatch', 'Archived file content does not match the release manifest.');
            }
        }
    } finally {
        financeArtifactSignatureRemoveTree($stage);
    }
    $requiredPolicies = [
        'migration_catalog' => 'tools/db/migration_catalog.json',
        'package_policy' => 'tools/release/package_policy.json',
        'runtime_compatibility' => 'tools/release/runtime_compatibility.json',
    ];
    $policyDigests = [];
    foreach ($requiredPolicies as $name => $path) {
        if (!isset($hashes[$path])) {
            financeArtifactSignatureFail('policy_digest_missing', 'A required release policy is absent from the manifest.');
        }
        $policyDigests[$name] = $hashes[$path];
    }
    return [
        'manifest_sha256' => hash('sha256', $read['stdout']),
        'source_epoch' => $manifest['source_epoch'],
        'policy_digests' => $policyDigests,
    ];
}

/** @return array{status:string,mode:string,key_id:string} */
function financeArtifactSignatureKeygen(string $privatePath, string $publicPath): array
{
    financeArtifactSignatureRequireSodium();
    $privateCandidateParent = realpath(dirname($privatePath));
    $publicCandidateParent = realpath(dirname($publicPath));
    if ((is_string($privateCandidateParent) && financeArtifactSignatureInsideProject($privateCandidateParent))
        || (is_string($publicCandidateParent) && financeArtifactSignatureInsideProject($publicCandidateParent))
    ) {
        financeArtifactSignatureFail('key_path_unsafe', 'Signing keys must be new files outside the repository.');
    }
    $privateParent = financeArtifactSignatureSafeParent($privatePath);
    $publicParent = financeArtifactSignatureSafeParent($publicPath);
    if (financeArtifactSignatureInsideProject($privateParent) || financeArtifactSignatureInsideProject($publicParent)
        || $privatePath === $publicPath || @lstat($privatePath) !== false || @lstat($publicPath) !== false
    ) {
        financeArtifactSignatureFail('key_path_unsafe', 'Signing keys must be new files outside the repository.');
    }
    $keypair = sodium_crypto_sign_keypair();
    $privateKey = sodium_crypto_sign_secretkey($keypair);
    $publicKey = sodium_crypto_sign_publickey($keypair);
    try {
        financeArtifactSignatureAtomicWrite($privatePath, $privateKey, 0600);
        try {
            financeArtifactSignatureAtomicWrite($publicPath, $publicKey, 0644);
        } catch (Throwable $exception) {
            @unlink($privatePath);
            throw $exception;
        }
    } finally {
        sodium_memzero($privateKey);
        sodium_memzero($keypair);
    }
    return ['status' => 'ok', 'mode' => 'keygen', 'key_id' => hash('sha256', $publicKey)];
}

function financeArtifactSignatureLoadPrivateKey(string $path): string
{
    $path = financeArtifactSignatureRegularFile($path, 'private_key_unsafe');
    financeArtifactSignatureSafeParent($path);
    $stat = @lstat($path);
    if (financeArtifactSignatureInsideProject($path) || !is_array($stat) || !function_exists('posix_geteuid')
        || ($stat['uid'] ?? -1) !== posix_geteuid() || !financeArtifactSignatureExactMode($path, 0600)
    ) {
        financeArtifactSignatureFail('private_key_unsafe', 'Private key must remain outside the repository with mode 0600 and the current owner.');
    }
    $key = @file_get_contents($path);
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        financeArtifactSignatureFail('private_key_invalid', 'Private signing key has an invalid length.');
    }
    return $key;
}

function financeArtifactSignatureLoadPublicKey(string $path): string
{
    $path = financeArtifactSignatureRegularFile($path, 'public_key_unsafe');
    $stat = @lstat($path);
    $parent = realpath(dirname($path));
    $parentStat = is_string($parent) ? @lstat($parent) : false;
    $owner = is_array($stat) ? ($stat['uid'] ?? -1) : -1;
    $parentOwner = is_array($parentStat) ? ($parentStat['uid'] ?? -1) : -1;
    $effectiveOwner = function_exists('posix_geteuid') ? posix_geteuid() : -2;
    if (!is_array($stat) || !is_array($parentStat)
        || !in_array($owner, [0, $effectiveOwner], true)
        || !in_array($parentOwner, [0, $effectiveOwner], true)
        || (($stat['mode'] ?? 0) & 0022) !== 0 || (($parentStat['mode'] ?? 0) & 0022) !== 0
    ) {
        financeArtifactSignatureFail('public_key_unsafe', 'Trusted public key must not be group/world writable.');
    }
    $key = @file_get_contents($path);
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        financeArtifactSignatureFail('public_key_invalid', 'Trusted public key has an invalid length.');
    }
    return $key;
}

/** @return array<string,mixed> */
function financeArtifactSignaturePayload(string $artifact, string $publicKey, string $sourceRevision): array
{
    if (preg_match('/^[a-f0-9]{40}(?:[a-f0-9]{24})?$/D', $sourceRevision) !== 1) {
        financeArtifactSignatureFail('source_revision_invalid', 'Source revision must be a lowercase 40- or 64-character immutable digest.');
    }
    $artifact = financeArtifactSignatureRegularFile($artifact, 'artifact_unsafe');
    $artifactName = basename($artifact);
    if (financeArtifactSignatureInsideProject($artifact)
        || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,126}\.tar$/D', $artifactName) !== 1
        || strpos($artifactName, '..') !== false
    ) {
        financeArtifactSignatureFail('artifact_unsafe', 'Release artifact must be a safely named .tar outside the repository.');
    }
    $before = @lstat($artifact);
    $size = @filesize($artifact);
    $hash = @hash_file('sha256', $artifact);
    if (!is_array($before) || !is_int($size) || !is_string($hash)) {
        financeArtifactSignatureFail('artifact_unreadable', 'Release artifact metadata could not be read.');
    }
    $manifest = financeArtifactSignatureInspectManifest($artifact);
    clearstatcache(true, $artifact);
    $after = @lstat($artifact);
    $hashAfter = @hash_file('sha256', $artifact);
    foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'] as $field) {
        if (!is_array($after) || ($before[$field] ?? null) !== ($after[$field] ?? null)) {
            financeArtifactSignatureFail('artifact_changed', 'Release artifact changed during inspection.');
        }
    }
    if (!is_string($hashAfter) || !hash_equals($hash, $hashAfter)) {
        financeArtifactSignatureFail('artifact_changed', 'Release artifact changed during inspection.');
    }
    return [
        'schema' => 'finance.release-artifact-signature',
        'schema_version' => 1,
        'algorithm' => 'Ed25519',
        'key_id' => hash('sha256', $publicKey),
        'artifact' => ['name' => $artifactName, 'sha256' => $hash, 'size_bytes' => $size],
        'release_manifest_sha256' => $manifest['manifest_sha256'],
        'source' => [
            'revision' => $sourceRevision,
            'epoch' => $manifest['source_epoch'],
            'created_utc' => gmdate('Y-m-d\TH:i:s\Z', $manifest['source_epoch']),
        ],
        'policy_digests' => $manifest['policy_digests'],
    ];
}

/** @return array{status:string,mode:string,algorithm:string,key_id:string,artifact_sha256:string,source_revision:string} */
function financeArtifactSignatureSign(string $artifact, string $privateKeyPath, string $output, string $sourceRevision): array
{
    financeArtifactSignatureRequireSodium();
    $outputParent = realpath(dirname($output));
    if (substr($output, -15) !== '.signature.json'
        || (is_string($outputParent) && financeArtifactSignatureInsideProject($outputParent))
    ) {
        financeArtifactSignatureFail('output_invalid', 'Detached provenance output must end in .signature.json outside the repository.');
    }
    $privateKey = financeArtifactSignatureLoadPrivateKey($privateKeyPath);
    try {
        $publicKey = sodium_crypto_sign_publickey_from_secretkey($privateKey);
        $payload = financeArtifactSignaturePayload($artifact, $publicKey, $sourceRevision);
        $signature = sodium_crypto_sign_detached(financeArtifactSignatureCanonicalJson($payload), $privateKey);
        $envelope = ['payload' => $payload, 'signature' => base64_encode($signature)];
        financeArtifactSignatureAtomicWrite($output, financeArtifactSignatureCanonicalJson($envelope) . PHP_EOL, 0644);
    } finally {
        sodium_memzero($privateKey);
    }
    return [
        'status' => 'ok',
        'mode' => 'sign',
        'algorithm' => 'Ed25519',
        'key_id' => $payload['key_id'],
        'artifact_sha256' => $payload['artifact']['sha256'],
        'source_revision' => $payload['source']['revision'],
    ];
}

/** @return array{status:string,mode:string,algorithm:string,key_id:string,artifact_sha256:string,source_revision:string,source_epoch:int} */
function financeArtifactSignatureVerify(string $artifact, string $provenancePath, string $publicKeyPath): array
{
    financeArtifactSignatureRequireSodium();
    $publicKey = financeArtifactSignatureLoadPublicKey($publicKeyPath);
    $provenancePath = financeArtifactSignatureRegularFile($provenancePath, 'provenance_unsafe');
    $raw = @file_get_contents($provenancePath, false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) {
        financeArtifactSignatureFail('provenance_unreadable', 'Detached provenance is unreadable or oversized.');
    }
    try {
        $envelope = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        financeArtifactSignatureFail('provenance_malformed', 'Detached provenance JSON is malformed.');
    }
    if (!is_array($envelope) || !financeArtifactSignatureExactKeys($envelope, ['payload', 'signature'])
        || !is_array($envelope['payload']) || !is_string($envelope['signature'])
        || $raw !== financeArtifactSignatureCanonicalJson($envelope) . PHP_EOL
    ) {
        financeArtifactSignatureFail('provenance_schema_invalid', 'Detached provenance schema or canonical encoding is invalid.');
    }
    $payload = $envelope['payload'];
    if (!financeArtifactSignatureExactKeys($payload, [
        'algorithm', 'artifact', 'key_id', 'policy_digests', 'release_manifest_sha256', 'schema', 'schema_version', 'source',
    ]) || $payload['schema'] !== 'finance.release-artifact-signature' || $payload['schema_version'] !== 1
        || $payload['algorithm'] !== 'Ed25519' || !is_string($payload['key_id'])
        || preg_match('/^[a-f0-9]{64}$/D', $payload['key_id']) !== 1
        || !is_array($payload['artifact']) || !financeArtifactSignatureExactKeys($payload['artifact'], ['name', 'sha256', 'size_bytes'])
        || !is_string($payload['artifact']['name']) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,126}\.tar$/D', $payload['artifact']['name']) !== 1
        || strpos($payload['artifact']['name'], '..') !== false || !is_string($payload['artifact']['sha256'])
        || preg_match('/^[a-f0-9]{64}$/D', $payload['artifact']['sha256']) !== 1
        || !is_int($payload['artifact']['size_bytes']) || $payload['artifact']['size_bytes'] < 1
        || !is_string($payload['release_manifest_sha256'])
        || preg_match('/^[a-f0-9]{64}$/D', $payload['release_manifest_sha256']) !== 1
        || !is_array($payload['source']) || !financeArtifactSignatureExactKeys($payload['source'], ['created_utc', 'epoch', 'revision'])
        || !is_string($payload['source']['revision'])
        || preg_match('/^[a-f0-9]{40}(?:[a-f0-9]{24})?$/D', $payload['source']['revision']) !== 1
        || !is_int($payload['source']['epoch']) || $payload['source']['epoch'] < 0 || $payload['source']['epoch'] > 2147483647
        || $payload['source']['created_utc'] !== gmdate('Y-m-d\TH:i:s\Z', $payload['source']['epoch'])
        || !is_array($payload['policy_digests'])
        || !financeArtifactSignatureExactKeys($payload['policy_digests'], ['migration_catalog', 'package_policy', 'runtime_compatibility'])
    ) {
        financeArtifactSignatureFail('provenance_schema_invalid', 'Detached provenance payload is invalid.');
    }
    foreach ($payload['policy_digests'] as $digest) {
        if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
            financeArtifactSignatureFail('provenance_schema_invalid', 'Detached provenance policy digest is invalid.');
        }
    }
    $signature = base64_decode($envelope['signature'], true);
    if (!is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
        || !hash_equals(hash('sha256', $publicKey), $payload['key_id'])
        || !sodium_crypto_sign_verify_detached($signature, financeArtifactSignatureCanonicalJson($payload), $publicKey)
    ) {
        financeArtifactSignatureFail('signature_invalid', 'Artifact signature is invalid or issued by an untrusted key.');
    }
    $expected = financeArtifactSignaturePayload($artifact, $publicKey, $payload['source']['revision']);
    if ($payload !== financeArtifactSignatureCanonicalValue($expected)) {
        financeArtifactSignatureFail('artifact_provenance_mismatch', 'Artifact, manifest, policies, or source provenance do not match.');
    }
    return [
        'status' => 'ok',
        'mode' => 'verify',
        'algorithm' => 'Ed25519',
        'key_id' => $payload['key_id'],
        'artifact_sha256' => $payload['artifact']['sha256'],
        'source_revision' => $payload['source']['revision'],
        'source_epoch' => $payload['source']['epoch'],
    ];
}

/** @return array<string,string> */
function financeArtifactSignatureOptions(array $arguments, array $allowed): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (!is_string($argument) || preg_match('/^--([a-z0-9-]+)=(.*)$/D', $argument, $matches) !== 1) {
            financeArtifactSignatureFail('usage', 'Invalid command option.');
        }
        $name = str_replace('-', '_', $matches[1]);
        if (!in_array($name, $allowed, true) || array_key_exists($name, $options) || $matches[2] === '') {
            financeArtifactSignatureFail('usage', 'Unknown, duplicate, or empty command option.');
        }
        $options[$name] = $matches[2];
    }
    sort($allowed, SORT_STRING);
    $actual = array_keys($options);
    sort($actual, SORT_STRING);
    if ($actual !== $allowed) {
        financeArtifactSignatureFail('usage', 'Required command option is missing.');
    }
    return $options;
}

if (defined('FINANCE_ARTIFACT_SIGNATURE_LIBRARY_ONLY') && FINANCE_ARTIFACT_SIGNATURE_LIBRARY_ONLY) {
    return;
}

try {
    $arguments = $argv ?? [];
    $command = $arguments[1] ?? '';
    if ($command === 'keygen') {
        $options = financeArtifactSignatureOptions(array_slice($arguments, 2), ['private_key', 'public_key']);
        $result = financeArtifactSignatureKeygen($options['private_key'], $options['public_key']);
    } elseif ($command === 'sign') {
        $options = financeArtifactSignatureOptions(array_slice($arguments, 2), ['artifact', 'private_key', 'output', 'source_revision']);
        $result = financeArtifactSignatureSign($options['artifact'], $options['private_key'], $options['output'], $options['source_revision']);
    } elseif ($command === 'verify') {
        $options = financeArtifactSignatureOptions(array_slice($arguments, 2), ['artifact', 'provenance', 'public_key']);
        $result = financeArtifactSignatureVerify($options['artifact'], $options['provenance'], $options['public_key']);
    } else {
        financeArtifactSignatureFail('usage', 'Use keygen, sign, or verify with explicit absolute paths.');
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (FinanceArtifactSignatureFailure $failure) {
    fwrite(STDERR, json_encode([
        'status' => 'error',
        'code' => $failure->failureCode,
        'message' => $failure->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} catch (Throwable $failure) {
    fwrite(STDERR, json_encode([
        'status' => 'error',
        'code' => 'internal_error',
        'message' => 'Artifact signature operation failed closed.',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
