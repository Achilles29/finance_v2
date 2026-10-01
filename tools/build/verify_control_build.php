<?php
declare(strict_types=1);

/** Trusted read-only pre-signing entry point for Control. Pin this entire Finance toolchain at its source cutoff. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/CustomerBuild.php';
$request = $output = '';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--request=')) $request = substr($arg, 10);
    elseif (str_starts_with($arg, '--output=')) $output = substr($arg, 9);
    else exit(2);
}
try {
    $root = dirname(__DIR__, 2);
    CustomerBuild::need(realpath($request) === $request && is_file($request) && !is_link($request) && filesize($request) <= 1048576
        && realpath($output) === $output && is_dir($output) && dirname($request) === dirname($output), 'BUILD_INPUT_UNSAFE');
    $r = ControlReleaseBridge::json((string)file_get_contents($request));
    CustomerBuild::validate($r, $root);
    $resultPath = $output . '/result.json';
    CustomerBuild::need(is_file($resultPath) && !is_link($resultPath) && filesize($resultPath) <= 1048576, 'RESULT_UNSAFE');
    $result = ControlReleaseBridge::json((string)file_get_contents($resultPath));
    CustomerBuild::need(($result['status'] ?? '') === 'PASS' && ($result['request_sha256'] ?? '') === hash('sha256', CustomerBuild::canonical($r)), 'RESULT_BINDING');
    foreach (['request_id', 'release_public_id', 'product_code', 'version', 'source_commit', 'source_manifest_sha256'] as $field)
        CustomerBuild::need(($result[$field] ?? null) === $r[$field], 'RESULT_BINDING');
    $d = $result['artifacts']['application_package'] ?? [];
    $file = $d['file'] ?? '';
    CustomerBuild::need(is_string($file) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.tar\z/D', $file) === 1 && !str_contains($file, '..'), 'ARTIFACT_NAME');
    $artifact = $output . '/' . $file;
    $inspection = ControlReleaseBridge::describe($root, $artifact);
    CustomerBuild::need(($d['sha256'] ?? '') === $inspection['sha256'] && ($d['size_bytes'] ?? null) === $inspection['size_bytes']
        && ($d['media_type'] ?? '') === 'application/x-tar' && $inspection['source_commit'] === $r['source_commit']
        && $inspection['version'] === $r['version'] && $inspection['customer_content_audit']['profile_sha256'] === $r['profile_rules_sha256'], 'ARTIFACT_BINDING');
    echo json_encode(['status' => 'PASS', 'request_sha256' => hash('sha256', CustomerBuild::canonical($r)),
        'source_commit' => $inspection['source_commit'], 'artifact_sha256' => $inspection['sha256'],
        'distribution_profile' => $inspection['distribution_profile'], 'distribution_profile_version' => $inspection['distribution_profile_version'], 'seed_profile' => 'REFERENCE_ONLY',
        'customer_content_audit' => $inspection['customer_content_audit'],
        'application_update_contract' => $inspection['application_update_contract'] ?? null,
        'database_accessed' => false], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    $reason = preg_match('/\A[A-Z][A-Z0-9_]{2,79}\z/D', $e->getMessage()) === 1 ? $e->getMessage() : 'VALIDATION_FAILED';
    fwrite(STDERR, json_encode(['status' => 'FAIL', 'error_code' => $reason]) . "\n"); exit(1);
}
