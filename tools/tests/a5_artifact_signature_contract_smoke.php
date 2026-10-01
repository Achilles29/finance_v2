<?php

declare(strict_types=1);

define('FINANCE_ARTIFACT_SIGNATURE_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/release/artifact_signature.php';

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    $failures[] = $label;
    fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
};
$failsWith = static function (callable $operation, string $code): bool {
    try {
        $operation();
    } catch (FinanceArtifactSignatureFailure $failure) {
        return $failure->failureCode === $code;
    }
    return false;
};
$removeTree = static function (string $directory): void {
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
};
$run = static function (array $command): array {
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, []);
    if (!is_resource($process)) {
        return ['code' => 127, 'output' => ''];
    }
    $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'output' => $output];
};

$base = sys_get_temp_dir() . '/finance-a5-signature-' . bin2hex(random_bytes(6));
$stage = $base . '/stage';
$keys = $base . '/keys';
$artifacts = $base . '/artifacts';
mkdir($stage . '/tools/db', 0700, true);
mkdir($stage . '/tools/release', 0700, true);
mkdir($keys, 0700, true);
mkdir($artifacts, 0700, true);
register_shutdown_function(static function () use ($removeTree, $base): void {
    $removeTree($base);
});

$fixtureFiles = [
    'application.php' => "<?php echo 'fixture';\n",
    'tools/db/migration_catalog.json' => "{\"schema\":\"fixture.migrations\"}\n",
    'tools/release/package_policy.json' => "{\"schema\":\"fixture.package\"}\n",
    'tools/release/runtime_compatibility.json' => "{\"schema\":\"fixture.runtime\"}\n",
];
$entries = [];
foreach ($fixtureFiles as $path => $data) {
    $destination = $stage . '/' . $path;
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0700, true);
    }
    file_put_contents($destination, $data);
    chmod($destination, 0644);
    touch($destination, 1700000000);
    $entries[] = ['path' => $path, 'sha256' => hash('sha256', $data), 'size' => strlen($data), 'mode' => '0644'];
}
usort($entries, static fn(array $left, array $right): int => strcmp($left['path'], $right['path']));
$manifest = [
    'schema' => 'finance.release-artifact-manifest',
    'schema_version' => 1,
    'source_epoch' => 1700000000,
    'files' => $entries,
];
$manifestData = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($stage . '/RELEASE-MANIFEST.json', $manifestData);
chmod($stage . '/RELEASE-MANIFEST.json', 0644);
touch($stage . '/RELEASE-MANIFEST.json', 1700000000);
$paths = array_keys($fixtureFiles);
$paths[] = 'RELEASE-MANIFEST.json';
sort($paths, SORT_STRING);
$artifact = $artifacts . '/finance-fixture.tar';
$tar = $run(array_merge([
    '/usr/bin/tar', '--create', '--format=gnu', '--sort=name', '--no-recursion', '--mtime=@1700000000',
    '--owner=0', '--group=0', '--numeric-owner', '--directory=' . $stage, '--file=' . $artifact, '--',
], $paths));
$check($tar['code'] === 0 && is_file($artifact), 'deterministic release fixture is available');

$privateKey = $keys . '/release-private.key';
$publicKey = $keys . '/release-public.key';
$keyResult = financeArtifactSignatureKeygen($privateKey, $publicKey);
$check(
    ($keyResult['mode'] ?? '') === 'keygen'
        && financeArtifactSignatureExactMode($privateKey, 0600)
        && financeArtifactSignatureExactMode($publicKey, 0644)
        && filesize($privateKey) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
        && filesize($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES,
    'key generation writes raw Ed25519 keys with strict modes outside the repository'
);

$revision = str_repeat('a', 40);
$provenanceA = $artifacts . '/finance-fixture-a.signature.json';
$provenanceB = $artifacts . '/finance-fixture-b.signature.json';
$signedA = financeArtifactSignatureSign($artifact, $privateKey, $provenanceA, $revision);
$signedB = financeArtifactSignatureSign($artifact, $privateKey, $provenanceB, $revision);
$check(
    ($signedA['algorithm'] ?? '') === 'Ed25519'
        && ($signedA['key_id'] ?? '') === hash_file('sha256', $publicKey)
        && hash_file('sha256', $provenanceA) === hash_file('sha256', $provenanceB),
    'same artifact, source revision, and key produce deterministic detached provenance'
);

$verified = financeArtifactSignatureVerify($artifact, $provenanceA, $publicKey);
$check(
    ($verified['status'] ?? '') === 'ok'
        && ($verified['artifact_sha256'] ?? '') === hash_file('sha256', $artifact)
        && ($verified['source_revision'] ?? '') === $revision
        && ($verified['source_epoch'] ?? null) === 1700000000,
    'trusted public key verifies artifact, manifest, source, and policy provenance'
);
$envelope = json_decode((string)file_get_contents($provenanceA), true);
$check(
    is_array($envelope)
        && ($envelope['payload']['release_manifest_sha256'] ?? '') === hash('sha256', $manifestData)
        && ($envelope['payload']['policy_digests']['migration_catalog'] ?? '') === hash('sha256', $fixtureFiles['tools/db/migration_catalog.json'])
        && ($envelope['payload']['policy_digests']['package_policy'] ?? '') === hash('sha256', $fixtureFiles['tools/release/package_policy.json'])
        && ($envelope['payload']['policy_digests']['runtime_compatibility'] ?? '') === hash('sha256', $fixtureFiles['tools/release/runtime_compatibility.json']),
    'provenance pins release manifest plus migration, package, and runtime policy digests'
);
$check(
    strpos((string)file_get_contents($provenanceA), base64_encode((string)file_get_contents($privateKey))) === false,
    'detached provenance never contains private key material'
);

$originalArtifact = (string)file_get_contents($artifact);
file_put_contents($artifact, $originalArtifact . 'tamper');
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureVerify($artifact, $provenanceA, $publicKey),
        'artifact_provenance_mismatch'
    ),
    'changed artifact is rejected even when detached provenance is unchanged'
);
file_put_contents($artifact, $originalArtifact);

$tamperedEnvelope = $envelope;
$tamperedEnvelope['payload']['source']['revision'] = str_repeat('b', 40);
$tamperedProvenance = $artifacts . '/tampered.signature.json';
file_put_contents($tamperedProvenance, financeArtifactSignatureCanonicalJson($tamperedEnvelope) . PHP_EOL);
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureVerify($artifact, $tamperedProvenance, $publicKey),
        'signature_invalid'
    ),
    'modified provenance payload is rejected by Ed25519 verification'
);

$otherPrivate = $keys . '/other-private.key';
$otherPublic = $keys . '/other-public.key';
financeArtifactSignatureKeygen($otherPrivate, $otherPublic);
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureVerify($artifact, $provenanceA, $otherPublic),
        'signature_invalid'
    ),
    'artifact issued by an unknown signing key is rejected'
);

$missingProvenance = $artifacts . '/missing.signature.json';
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureVerify($artifact, $missingProvenance, $publicKey),
        'provenance_unsafe'
    ),
    'unsigned artifact cannot pass the verifier'
);

chmod($privateKey, 0644);
$unsafeOutput = $artifacts . '/unsafe-mode.signature.json';
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($artifact, $privateKey, $unsafeOutput, $revision),
        'private_key_unsafe'
    ) && !file_exists($unsafeOutput),
    'signing fails closed when private key mode is broader than 0600'
);
chmod($privateKey, 0600);

$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($artifact, $privateKey, $artifacts . '/bad-revision.signature.json', 'main'),
        'source_revision_invalid'
    ),
    'mutable or ambiguous source revision is rejected'
);

$unsafeName = $artifacts . '/unsafe name.tar';
file_put_contents($unsafeName, $originalArtifact);
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($unsafeName, $privateKey, $artifacts . '/unsafe-name.signature.json', $revision),
        'artifact_unsafe'
    ),
    'unsafe artifact filename cannot enter signed provenance'
);

$repositoryOutput = dirname(__DIR__, 2) . '/forbidden-release.signature.json';
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($artifact, $privateKey, $repositoryOutput, $revision),
        'output_invalid'
    ) && !file_exists($repositoryOutput),
    'detached signature cannot be published inside the repository'
);

$projectKey = dirname(__DIR__, 2) . '/forbidden-release-private.key';
$projectPublic = dirname(__DIR__, 2) . '/forbidden-release-public.key';
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureKeygen($projectKey, $projectPublic),
        'key_path_unsafe'
    ) && !file_exists($projectKey) && !file_exists($projectPublic),
    'private and public signing keys cannot be generated inside the repository'
);

$missingStage = $base . '/missing-policy-stage';
mkdir($missingStage, 0700);
file_put_contents($missingStage . '/application.php', $fixtureFiles['application.php']);
$missingManifest = $manifest;
$missingManifest['files'] = [$entries[0]];
file_put_contents($missingStage . '/RELEASE-MANIFEST.json', json_encode($missingManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$missingArtifact = $artifacts . '/missing-policy.tar';
$run([
    '/usr/bin/tar', '--create', '--directory=' . $missingStage, '--file=' . $missingArtifact, '--',
    'application.php', 'RELEASE-MANIFEST.json',
]);
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($missingArtifact, $privateKey, $artifacts . '/missing-policy.signature.json', $revision),
        'policy_digest_missing'
    ),
    'artifact missing a required release policy cannot be signed'
);

$contentMismatchStage = $base . '/content-mismatch-stage';
mkdir($contentMismatchStage, 0700);
$run(['/usr/bin/tar', '--extract', '--directory=' . $contentMismatchStage, '--file=' . $artifact]);
file_put_contents($contentMismatchStage . '/application.php', "<?php echo 'changed';\n");
$contentMismatchArtifact = $artifacts . '/content-mismatch.tar';
$run(array_merge([
    '/usr/bin/tar', '--create', '--directory=' . $contentMismatchStage, '--file=' . $contentMismatchArtifact, '--',
], $paths));
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($contentMismatchArtifact, $privateKey, $artifacts . '/content-mismatch.signature.json', $revision),
        'manifest_content_mismatch'
    ),
    'manifest file hash mismatch is rejected before signing'
);

$unsafeStage = $base . '/unsafe-stage';
mkdir($unsafeStage, 0700);
file_put_contents($unsafeStage . '/payload', 'unsafe');
$unsafeArchive = $artifacts . '/unsafe-path.tar';
$run([
    '/usr/bin/tar', '--create', '--transform=s|payload|../payload|', '--directory=' . $unsafeStage,
    '--file=' . $unsafeArchive, '--', 'payload',
]);
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($unsafeArchive, $privateKey, $artifacts . '/unsafe-path.signature.json', $revision),
        'archive_paths_invalid'
    ),
    'archive traversal path is rejected before signing'
);

$linkStage = $base . '/link-stage';
mkdir($linkStage, 0700);
file_put_contents($linkStage . '/target', 'target');
symlink('target', $linkStage . '/linked');
$linkArchive = $artifacts . '/linked-entry.tar';
$run([
    '/usr/bin/tar', '--create', '--directory=' . $linkStage, '--file=' . $linkArchive, '--', 'target', 'linked',
]);
$check(
    $failsWith(
        static fn(): array => financeArtifactSignatureSign($linkArchive, $privateKey, $artifacts . '/linked-entry.signature.json', $revision),
        'archive_types_invalid'
    ),
    'symlink and non-regular archive entries are rejected before signing'
);

$cli = dirname(__DIR__) . '/release/artifact_signature.php';
$cliVerify = $run([
    PHP_BINARY, $cli, 'verify', '--artifact=' . $artifact, '--provenance=' . $provenanceA, '--public-key=' . $publicKey,
]);
$cliJson = json_decode(trim($cliVerify['output']), true);
$check(
    $cliVerify['code'] === 0 && is_array($cliJson) && ($cliJson['mode'] ?? '') === 'verify'
        && strpos($cliVerify['output'], 'private') === false,
    'installer-facing CLI verifies without exposing or requiring private key material'
);

echo 'A5 ARTIFACT SIGNATURE CONTRACT ' . ($failures === [] ? 'PASS' : 'FAIL')
    . ' passed=' . ($checks - count($failures)) . ' failed=' . count($failures) . PHP_EOL;
exit($failures === [] ? 0 : 1);
