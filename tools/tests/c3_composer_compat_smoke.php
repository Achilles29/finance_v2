<?php
declare(strict_types=1);
define('FINANCE_COMPOSER_COMPAT_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/install/composer_compat.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "PASS {$label}\n";
};
$base = sys_get_temp_dir() . '/finance-composer-fixture-' . bin2hex(random_bytes(8));
$target = $base . '/vendor/mikey179/vfsstream/src/main/php/org/bovigo/vfs/vfsStream.php';
mkdir(dirname($target), 0700, true);
try {
    $check(financeComposerCompat($base . '/vendor') === 'SKIPPED_NO_DEV_PACKAGE', 'no-dev install does not require absent test dependency');
    file_put_contents($target, '<?php $name = "fixture"; echo $name{0};');
    $check(financeComposerCompat($base . '/vendor') === 'PATCHED', 'legacy compatibility patch executes without shell sed');
    $check(strpos((string)file_get_contents($target), '$name[0]') !== false, 'fixture gets bracket offset syntax');
    $check(financeComposerCompat($base . '/vendor') === 'ALREADY_COMPATIBLE', 'repeat install is idempotent');
    unlink($target); file_put_contents($base . '/outside.php', 'untouched'); symlink($base . '/outside.php', $target);
    try { financeComposerCompat($base . '/vendor'); $check(false, 'symlink accepted'); } catch (RuntimeException $e) { $check($e->getMessage() === 'VENDOR_SYMLINK_REJECTED', 'symlinked dependency cannot overwrite outside vendor'); }
    $check(file_get_contents($base . '/outside.php') === 'untouched', 'external fixture remains unchanged');
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($base);
}
echo "All {$checks} Composer compatibility checks passed.\n";
