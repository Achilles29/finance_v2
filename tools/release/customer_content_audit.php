<?php
declare(strict_types=1);
require __DIR__ . '/ControlReleaseBridge.php';

try {
    if (PHP_SAPI !== 'cli' || $argc !== 2) throw new RuntimeException('USAGE');
    $manifest = ControlReleaseBridge::inspect($argv[1]);
    if (($manifest['distribution_profile'] ?? '') !== CustomerReleaseProfile::ID) throw new RuntimeException('CUSTOMER_PROFILE_REQUIRED');
    echo json_encode(['distribution_profile' => $manifest['distribution_profile'],
        'distribution_profile_version' => $manifest['distribution_profile_version'],
        'seed_profile' => $manifest['seed_profile'], 'customer_content_audit' => $manifest['customer_content_audit']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    $code = property_exists($error, 'failureCode') ? $error->failureCode : $error->getMessage();
    fwrite(STDERR, json_encode(['status' => 'BLOCKED', 'reason' => preg_match('/\A[A-Za-z_]+\z/D', $code) ? $code : 'AUDIT_FAILED']) . "\n");
    exit(1);
}
