<?php
declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/application/config/routes.php';
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "FAIL: routes.php tidak dapat dibaca.\n");
    exit(1);
}

preg_match_all(
    '/\$route\[[\'\"]([^\'\"]+)[\'\"]\]\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/',
    $source,
    $matches,
    PREG_SET_ORDER
);

$targetsByRoute = [];
foreach ($matches as $match) {
    $targetsByRoute[$match[1]][] = $match[2];
}

$conflicts = [];
$identicalDuplicates = [];
foreach ($targetsByRoute as $route => $targets) {
    if (count($targets) < 2) {
        continue;
    }
    $uniqueTargets = array_values(array_unique($targets));
    if (count($uniqueTargets) > 1) {
        $conflicts[$route] = $uniqueTargets;
    } else {
        $identicalDuplicates[$route] = $uniqueTargets[0];
    }
}

if ($conflicts !== []) {
    foreach ($conflicts as $route => $targets) {
        fwrite(STDERR, 'FAIL: conflicting route ' . $route . ' => ' . implode(' | ', $targets) . "\n");
    }
    exit(1);
}

echo 'A3 route collision smoke: PASS; conflicting=0, identical_duplicates=', count($identicalDuplicates), "\n";
