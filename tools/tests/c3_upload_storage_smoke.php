<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/application/libraries/Upload_storage_policy.php';
require dirname(__DIR__) . '/release/ReleasePackagePolicy.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "PASS {$label}\n";
};
$base = sys_get_temp_dir() . '/finance-storage-fixture-' . bin2hex(random_bytes(8));
mkdir($base, 0700);
try {
    $rows = Upload_storage_policy::inspect($base);
    $check(count($rows) === 8 && array_unique(array_column($rows, 'status')) === ['MISSING'], 'missing upload directories detected without creation');
    $check(count(scandir($base)) === 2, 'inspection is read-only');
    foreach (['../escape', 'uploads/../outside', '/etc', 'uploads/unknown'] as $path) {
        try { Upload_storage_policy::safe_path($base, $path); $check(false, 'invalid path'); } catch (RuntimeException $e) { $check($e->getMessage() === 'storage_target_invalid', 'non-allowlisted target rejected'); }
    }
    mkdir($base . '/uploads', 0700);
    symlink(sys_get_temp_dir(), $base . '/uploads/product');
    $rows = Upload_storage_policy::inspect($base);
    $row = array_values(array_filter($rows, static fn(array $row): bool => $row['path'] === 'uploads/product'))[0];
    $check($row['status'] === 'UNSAFE_PATH', 'symlinked upload target rejected without following it');
    unlink($base . '/uploads/product');
    file_put_contents($base . '/uploads/product', 'fixture');
    $rows = Upload_storage_policy::inspect($base);
    $check($rows[2]['status'] === 'UNSAFE_PATH', 'regular file cannot masquerade as upload directory');
    unlink($base . '/uploads/product');
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        try { Upload_storage_policy::prepare($base); $check(false, 'root prepare'); } catch (RuntimeException $e) { $check($e->getMessage() === 'run_as_php_fpm_user_not_root', 'root cannot create root-owned runtime directories'); }
    }
    $policy = ReleasePackagePolicy::fromFile(dirname(__DIR__) . '/release/package_policy.json');
    $check($policy->denied('docs/_NOTE2.md'), 'release excludes private owner notes without editing them');
    foreach (array_keys(Upload_storage_policy::DIRECTORIES) as $path) $check($policy->denied($path . '/customer.png'), 'release excludes ' . $path);
} finally {
    // Only this test's known empty directories are removed; never touch runtime uploads.
    rmdir($base . '/uploads'); rmdir($base);
}
echo "All {$checks} upload storage checks passed.\n";
