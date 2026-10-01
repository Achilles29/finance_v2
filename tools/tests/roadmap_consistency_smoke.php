<?php

declare(strict_types=1);

/**
 * Fail-closed contract for the technical roadmap SSOT and its commercial
 * roadmap boundary. This test is intentionally read-only.
 */

$root = dirname(__DIR__, 2);
$auditPath = $root . '/docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md';
$commercialPath = $root . '/docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md';
$failures = [];
$checks = 0;

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static function (string $path, string $label) use (&$failures): string {
    if (!is_file($path) || !is_readable($path)) {
        $failures[] = $label . ' is missing or unreadable';
        return '';
    }
    $contents = file_get_contents($path);
    if (!is_string($contents) || trim($contents) === '') {
        $failures[] = $label . ' is empty or unreadable';
        return '';
    }
    return str_replace("\r\n", "\n", $contents);
};

$section = static function (string $document, string $headingPattern, int $nextHeadingLevel): string {
    if (!preg_match($headingPattern, $document, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = $match[0][1];
    $tail = substr($document, $start);
    $nextPattern = '/^' . str_repeat('#', $nextHeadingLevel) . '\s+/m';
    if (preg_match($nextPattern, $tail, $next, PREG_OFFSET_CAPTURE, strlen($match[0][0]))) {
        return substr($tail, 0, $next[0][1]);
    }
    return $tail;
};

$audit = $read($auditPath, 'technical roadmap SSOT');
$commercial = $read($commercialPath, 'commercial roadmap');

$controlHeading = '## 0. Audit Control Board (Sumber Status Tunggal)';
$check(
    preg_match('/^## 0\. Audit Control Board \(Sumber Status Tunggal\)\s*$/m', $audit) === 1,
    'missing exact Audit Control Board heading'
);
$board = $section(
    $audit,
    '/^## 0\. Audit Control Board \(Sumber Status Tunggal\)\s*$/m',
    2
);
$check($board !== '', 'Audit Control Board section cannot be resolved');

$masterIds = [];
foreach (preg_split('/\n/', $board) ?: [] as $line) {
    if (strpos(ltrim($line), '|') !== 0 || preg_match('/^\s*\|?\s*:?-{3,}/', $line)) {
        continue;
    }
    if (preg_match_all('/`(AUD-[A-Z0-9]+(?:-[A-Z0-9]+)*)`/', $line, $matches)) {
        foreach ($matches[1] as $id) {
            $masterIds[] = $id;
        }
    }
}
$duplicates = array_keys(array_filter(array_count_values($masterIds), static function (int $count): bool {
    return $count > 1;
}));
$check($masterIds !== [], 'control board has no backtick AUD master-row IDs');
$check($duplicates === [], 'duplicate AUD master-row IDs: ' . implode(', ', $duplicates));

$sourceIds = [];
foreach ([['P0', 9], ['P1', 11], ['P2', 8]] as $sourceRange) {
    for ($number = 1; $number <= $sourceRange[1]; $number++) {
        $sourceIds[] = sprintf('%s-%02d', $sourceRange[0], $number);
    }
}
$missingSources = [];
foreach ($sourceIds as $sourceId) {
    if (preg_match('/(?<![A-Z0-9-])' . preg_quote($sourceId, '/') . '(?![A-Z0-9-])/', $board) !== 1) {
        $missingSources[] = $sourceId;
    }
}
$check($missingSources === [], 'control board missing source IDs: ' . implode(', ', $missingSources));

$missingUiWaves = [];
for ($number = 1; $number <= 9; $number++) {
    $id = sprintf('AUD-A3-UI-%02d', $number);
    if (!in_array($id, $masterIds, true)) {
        $missingUiWaves[] = $id;
    }
}
$check($missingUiWaves === [], 'control board missing UI wave master IDs: ' . implode(', ', $missingUiWaves));

$uiWaveSection = $section($audit, '/^### 0\.4 Checklist rollout UI 8\.3\s*$/m', 3);
$check($uiWaveSection !== '', 'A3 UI wave checklist cannot be resolved');
$unclosedUiWaves = [];
for ($number = 1; $number <= 9; $number++) {
    $id = sprintf('AUD-A3-UI-%02d', $number);
    if ($uiWaveSection === ''
        || preg_match('/^\|\s*`' . preg_quote($id, '/') . '`[^\n]*\|\s*`CODE_PASS`\s*\|/m', $uiWaveSection) !== 1
    ) {
        $unclosedUiWaves[] = $id;
    }
}
$check($unclosedUiWaves === [], 'A3 code-closure checklist still has unclosed waves: ' . implode(', ', $unclosedUiWaves));

$boardLines = preg_split('/\n/', $board) ?: [];
$phaseRows = [];
foreach (range(0, 5) as $phaseNumber) {
    $phase = 'A' . $phaseNumber;
    foreach ($boardLines as $line) {
        if (strpos(ltrim($line), '|') !== 0) {
            continue;
        }
        if (preg_match('/(?:^|\|)\s*`?' . $phase . '`?(?:\s*[—-][^|]*)?\s*(?=\|)/', $line) === 1) {
            $phaseRows[$phase] = $line;
            break;
        }
    }
}
$missingPhases = array_values(array_diff(['A0', 'A1', 'A2', 'A3', 'A4', 'A5'], array_keys($phaseRows)));
$check($missingPhases === [], 'phase-state table missing phases: ' . implode(', ', $missingPhases));
$check(
    isset($phaseRows['A3']) && preg_match('/(?<![A-Z_])DONE(?![A-Z_])/i', $phaseRows['A3']) !== 1,
    'A3 phase state must not be DONE'
);
$check(
    isset($phaseRows['A3']) && strpos($phaseRows['A3'], '`CODE_PASS`') !== false
        && strpos($phaseRows['A3'], '`CODE_COMPLETE_UAT_PENDING`') !== false,
    'A3 phase must distinguish completed code from still-pending visual UAT'
);

$statusContracts = [
    'IMPLEMENTATION' => ['NOT_STARTED', 'IN_PROGRESS', 'CODE_PASS'],
    'VALIDATION' => ['NONE', 'AUTO_PASS', 'STAGING_PASS', 'UAT_PASS'],
    'RELEASE-DATA' => ['N/A', 'BLOCKED', 'PROD_READY', 'DEFERRED_OWNER', 'REPAIRED_VALIDATED'],
];
foreach ($statusContracts as $dimension => $tokens) {
    $dimensionBlock = '';
    foreach ($boardLines as $index => $line) {
        if (strpos($line, $dimension) === false) {
            continue;
        }
        $dimensionBlock = implode("\n", array_slice($boardLines, $index, 8));
        break;
    }
    $check($dimensionBlock !== '', 'status dimension not explained: ' . $dimension);
    $missingTokens = [];
    foreach ($tokens as $token) {
        if ($dimensionBlock === '' || strpos($dimensionBlock, $token) === false) {
            $missingTokens[] = $token;
        }
    }
    $check($missingTokens === [], $dimension . ' missing status tokens: ' . implode(', ', $missingTokens));
}

$sqlFiles = glob($root . '/sql/*.sql');
if (!is_array($sqlFiles)) {
    $sqlFiles = [];
}
$expectedSqlPaths = array_map(static function (string $path): string {
    return basename($path);
}, $sqlFiles);
sort($expectedSqlPaths, SORT_STRING);
$check(count($expectedSqlPaths) === 34, 'workspace contains exactly 34 top-level SQL files including module notifications');

$registerTable = [];
$registerHeading = '';
foreach ($boardLines as $index => $line) {
    if (strpos(ltrim($line), '|') !== 0) {
        continue;
    }
    $plainHeader = strtolower(str_replace(['`', '*'], '', $line));
    if (strpos($plainHeader, 'staging') === false || strpos($plainHeader, 'server utama') === false) {
        continue;
    }
    for ($headingIndex = $index - 1; $headingIndex >= 0; $headingIndex--) {
        if (preg_match('/^#{3,6}\s+/', $boardLines[$headingIndex])) {
            $registerHeading = $boardLines[$headingIndex];
            break;
        }
    }
    for ($rowIndex = $index; $rowIndex < count($boardLines); $rowIndex++) {
        if (strpos(ltrim($boardLines[$rowIndex]), '|') !== 0) {
            break;
        }
        $registerTable[] = $boardLines[$rowIndex];
    }
    break;
}
$check(
    $registerTable !== [] && stripos($registerHeading, 'sql') !== false && stripos($registerHeading, 'register') !== false,
    'SQL register table with staging and server utama columns is missing'
);
$registeredSqlPaths = [];
foreach (array_slice($registerTable, 2) as $registerRow) {
    if (preg_match('/^\s*\|\s*`([^`]+\.sql)`\s*\|/', $registerRow, $match) !== 1) {
        continue;
    }
    $registeredPath = $match[1];
    if (preg_match('#^(?:sql/)?([^/]+\.sql)$#', $registeredPath, $pathMatch) === 1) {
        $registeredSqlPaths[] = $pathMatch[1];
    }
}
$registeredCounts = array_count_values($registeredSqlPaths);
$duplicateSqlPaths = array_keys(array_filter($registeredCounts, static function (int $count): bool {
    return $count > 1;
}));
$registeredSqlPaths = array_keys($registeredCounts);
sort($registeredSqlPaths, SORT_STRING);
$check($duplicateSqlPaths === [], 'SQL register has duplicate filenames: ' . implode(', ', $duplicateSqlPaths));
$check(
    $registeredSqlPaths === $expectedSqlPaths,
    'SQL register must match all current top-level sql/*.sql filenames exactly'
);

$check(
    preg_match('/P0-08\s+—\s+migration runner\b.{0,240}\bbelum ada\b/si', $audit) !== 1,
    'stale P0-08 migration-runner-missing claim is present'
);
$a3Section = $section($audit, '/^### Fase A3\b.*$/m', 3);
$check($a3Section !== '', 'Fase A3 section cannot be resolved');
$check(
    $a3Section !== '' && preg_match('/`?\[x\]`?\s+selesai\b/i', $a3Section) !== 1,
    'stale A3 [x] selesai claim is present'
);
$check(
    $a3Section !== '' && strpos($a3Section, 'A3-CODE-CLOSED') !== false
        && strpos($a3Section, 'UAT_PENDING') !== false,
    'A3 closure marker and the remaining manual-UAT boundary are explicit'
);

$commercialParagraphs = preg_split('/\n\s*\n/', $commercial) ?: [];
$technicalDetailBoundary = false;
foreach ($commercialParagraphs as $paragraph) {
    if (stripos($paragraph, 'detail status teknis') === false || stripos($paragraph, 'hanya') === false) {
        continue;
    }
    if (strpos($paragraph, '2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md') !== false
        || preg_match('/(?<![A-Za-z0-9])_30(?![A-Za-z0-9])/', $paragraph) === 1) {
        $technicalDetailBoundary = true;
        break;
    }
}
$check($technicalDetailBoundary, 'commercial roadmap must say technical status detail exists only in _30');

if ($failures !== []) {
    fwrite(STDERR, 'FAIL roadmap-consistency failures=' . count($failures) . ' checks=' . $checks . PHP_EOL);
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS roadmap-consistency checks=' . $checks
    . ' master_rows=' . count($masterIds)
    . ' sql_files=' . count($expectedSqlPaths) . PHP_EOL;
