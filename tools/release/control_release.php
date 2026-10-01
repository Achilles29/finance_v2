<?php
declare(strict_types=1);
require __DIR__ . '/ControlReleaseBridge.php';

try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI_ONLY');
    $mode = $argv[1] ?? '';
    if ($mode === 'export' && $argc === 5) {
        // Existing artifact, clean checkout, external Control-style private key. Never publish or touch a DB.
        [$root, $artifact, $keyPath] = array_slice($argv, 2);
        $manifestPath = substr($artifact, 0, -4) . '.release.json';
        $signaturePath = substr($artifact, 0, -4) . '.release.sig.json';
        if (@lstat($manifestPath) !== false || @lstat($signaturePath) !== false) throw new RuntimeException('OUTPUT_EXISTS');
        financeArtifactSignatureSafeParent($manifestPath);
        if (strpos($artifact, $root . '/') === 0) throw new RuntimeException('OUTPUT_INSIDE_SOURCE');
        $manifest = ControlReleaseBridge::describe($root, $artifact);
        $bytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $key = ControlReleaseBridge::loadKey($keyPath);
        $signature = ControlReleaseBridge::sign($bytes, basename($manifestPath), $key);
        unset($key);
        financeArtifactSignatureAtomicWrite($manifestPath, $bytes, 0644);
        financeArtifactSignatureAtomicWrite($signaturePath, json_encode($signature, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", 0644);
        $result = ['status' => 'EXPORTED', 'manifest' => $manifestPath, 'readiness' => 'INTERNAL_CANDIDATE', 'published' => false];
    } elseif ($mode === 'verify' && $argc === 4) {
        $path = financeArtifactSignatureRegularFile($argv[2], 'MANIFEST_UNSAFE');
        if (substr($path, -13) !== '.release.json' || filesize($path) > 1048576) throw new RuntimeException('MANIFEST_INVALID');
        $bytes = (string)file_get_contents($path);
        $manifest = ControlReleaseBridge::json($bytes);
        $name = ControlReleaseBridge::artifactName($manifest);
        if (!is_string($name) || basename($name) !== $name || strpos($name, '..') !== false) throw new RuntimeException('ARTIFACT_NAME');
        $signaturePath = financeArtifactSignatureRegularFile(substr($path, 0, -13) . '.release.sig.json', 'SIGNATURE_UNSAFE');
        if (filesize($signaturePath) > 16384) throw new RuntimeException('SIGNATURE_SIZE');
        $result = ControlReleaseBridge::verify($bytes, basename($path), ControlReleaseBridge::json((string)file_get_contents($signaturePath)), ControlReleaseBridge::loadKey($argv[3]), dirname($path) . '/' . $name);
    } else {
        throw new RuntimeException('USAGE: control_release.php export ROOT ARTIFACT.tar PRIVATE_KEY | verify MANIFEST.release.json TRUST_KEY');
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    $reason = $e instanceof FinanceArtifactSignatureFailure ? $e->failureCode : $e->getMessage();
    fwrite(STDERR, json_encode(['status' => 'BLOCKED', 'reason' => preg_match('/\A[A-Z_]+\z/D', $reason) ? $reason : 'VALIDATION_FAILED']) . "\n");
    exit(1);
}
