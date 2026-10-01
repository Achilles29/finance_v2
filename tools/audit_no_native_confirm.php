<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/../application/views');
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "View directory tidak ditemukan.\n");
    exit(2);
}

$violations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }
    if (strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $contents = file($path, FILE_IGNORE_NEW_LINES);
    if ($contents === false) {
        $violations[] = [$path, 0, 'Gagal membaca file'];
        continue;
    }

    foreach ($contents as $index => $line) {
        if (strpos($line, 'window.confirm(') === false) {
            continue;
        }
        $violations[] = [$path, $index + 1, trim($line)];
    }
}

if ($violations === []) {
    fwrite(STDOUT, "OK: tidak ada window.confirm() di application/views.\n");
    exit(0);
}

fwrite(STDERR, "Ditemukan pemakaian window.confirm() yang tidak boleh ada di view:\n");
foreach ($violations as [$path, $lineNo, $snippet]) {
    $relative = str_replace('\\', '/', substr($path, strlen(dirname($root)) + 1));
    fwrite(STDERR, '- ' . $relative . ':' . $lineNo . ' => ' . $snippet . PHP_EOL);
}

fwrite(STDERR, "Gunakan FinanceUI.confirm() atau helper uiConfirm() tanpa native fallback.\n");
exit(1);
