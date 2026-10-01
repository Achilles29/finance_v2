<?php
declare(strict_types=1);

/** Portable replacement for the legacy sed hook; production --no-dev is a no-op. */
function financeComposerCompat(string $vendorDirectory): string
{
    $relative = 'mikey179/vfsstream/src/main/php/org/bovigo/vfs/vfsStream.php';
    if (!file_exists($vendorDirectory . '/' . $relative) && !is_link($vendorDirectory . '/' . $relative)) return 'SKIPPED_NO_DEV_PACKAGE';
    $vendor = realpath($vendorDirectory);
    if ($vendor === false || is_link($vendorDirectory)) throw new RuntimeException('VENDOR_PATH_INVALID');
    $target = $vendor;
    foreach (explode('/', $relative) as $part) {
        $target .= DIRECTORY_SEPARATOR . $part;
        if (is_link($target)) throw new RuntimeException('VENDOR_SYMLINK_REJECTED');
    }
    if (!is_file($target) || !is_readable($target)) throw new RuntimeException('VENDOR_SOURCE_UNREADABLE');
    $source = file_get_contents($target);
    if (!is_string($source)) throw new RuntimeException('VENDOR_SOURCE_UNREADABLE');
    $patched = str_replace('name{0}', 'name[0]', $source);
    if ($source === $patched) return 'ALREADY_COMPATIBLE';
    $temporary = tempnam(dirname($target), '.finance-compat-');
    if ($temporary === false) throw new RuntimeException('VENDOR_PATCH_UNAVAILABLE');
    try {
        if (file_put_contents($temporary, $patched, LOCK_EX) !== strlen($patched)
            || !chmod($temporary, fileperms($target) & 0777) || !rename($temporary, $target)) throw new RuntimeException('VENDOR_PATCH_FAILED');
    } finally { if (is_file($temporary)) unlink($temporary); }
    return 'PATCHED';
}

if (!defined('FINANCE_COMPOSER_COMPAT_LIBRARY_ONLY')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    try { echo financeComposerCompat(dirname(__DIR__, 2) . '/vendor') . PHP_EOL; }
    catch (RuntimeException $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
}
