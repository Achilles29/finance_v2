<?php

declare(strict_types=1);

/**
 * DB-free smoke test for the Finance PHP runtime/release baseline.
 *
 * This test reads repository metadata and production PHP source only. It does
 * not bootstrap CodeIgniter, load Composer/vendor, connect to a database, use
 * the network, or create a composer.lock/runtime artifact.
 */

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];
$warnings = [];
$information = [];

function release_runtime_check(bool $condition, string $success, string $failure): void
{
    global $checks, $failures;
    $checks++;

    if ($condition) {
        echo "PASS: {$success}\n";
        return;
    }

    $failures[] = $failure;
    fwrite(STDERR, "FAIL: {$failure}\n");
}

function release_runtime_warn(string $message): void
{
    global $warnings;
    $warnings[] = $message;
    echo "WARN: {$message}\n";
}

function release_runtime_info(string $message): void
{
    global $information;
    $information[] = $message;
    echo "INFO: {$message}\n";
}

/**
 * @return string[]
 */
function release_runtime_production_php_files(string $root): array
{
    $files = [];
    $frontController = $root . '/index.php';
    if (is_file($frontController)) {
        $files[] = $frontController;
    }

    foreach (['application', 'system'] as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isLink() || !$fileInfo->isFile()) {
                continue;
            }
            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }
    }

    sort($files, SORT_STRING);
    return $files;
}

/**
 * Return the next non-whitespace/comment token, or null at end of stream.
 *
 * @param array<int, array<int, int|string>|string> $tokens
 * @return array<int, int|string>|string|null
 */
function release_runtime_next_significant_token(array $tokens, int $offset)
{
    $ignored = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $count = count($tokens);
    for ($index = $offset; $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], $ignored, true)) {
            continue;
        }

        return $token;
    }

    return null;
}

/**
 * @param array<int, array<int, int|string>|string> $tokens
 * @return array<int, int|string>|string|null
 */
function release_runtime_previous_significant_token(array $tokens, int $offset)
{
    $ignored = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    for ($index = $offset; $index >= 0; $index--) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], $ignored, true)) {
            continue;
        }

        return $token;
    }

    return null;
}

/**
 * Inspect source with the active PHP parser. Exact cross-minor qualification
 * belongs to tools/release/runtime_compatibility.json and its A5.14 probe.
 *
 * @param string[] $files
 * @return array{parse_errors: string[], post_80_features: string[], native_calls: array<string, int>}
 */
function release_runtime_inspect_source(array $files, string $root): array
{
    $parseErrors = [];
    $post80Features = [];
    $nativeCalls = [
        'str_contains' => 0,
        'str_starts_with' => 0,
        'str_ends_with' => 0,
    ];
    $enumToken = defined('T_ENUM') ? constant('T_ENUM') : null;
    $readonlyToken = defined('T_READONLY') ? constant('T_READONLY') : null;

    foreach ($files as $path) {
        $relativePath = ltrim(substr($path, strlen($root)), '/\\');
        $source = @file_get_contents($path);
        if ($source === false) {
            $parseErrors[] = "{$relativePath}: unreadable";
            continue;
        }

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $error) {
            $parseErrors[] = "{$relativePath}: {$error->getMessage()}";
            continue;
        }

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            $tokenId = $token[0];
            $tokenText = strtolower((string) $token[1]);
            $line = isset($token[2]) ? (int) $token[2] : 0;

            if ($tokenId === T_STRING && isset($nativeCalls[$tokenText])) {
                $previous = release_runtime_previous_significant_token($tokens, $index - 1);
                $next = release_runtime_next_significant_token($tokens, $index + 1);
                $nonGlobalCallTokens = [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON];
                if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
                    $nonGlobalCallTokens[] = constant('T_NULLSAFE_OBJECT_OPERATOR');
                }
                $isNonGlobalCall = is_array($previous)
                    && in_array($previous[0], $nonGlobalCallTokens, true);
                if ($next === '(' && !$isNonGlobalCall) {
                    $nativeCalls[$tokenText]++;
                }
            }

            if ($enumToken !== null && $tokenId === $enumToken) {
                $post80Features[] = "{$relativePath}:{$line} uses enum syntax (PHP 8.1+)";
            }
            if ($readonlyToken !== null && $tokenId === $readonlyToken) {
                $post80Features[] = "{$relativePath}:{$line} uses readonly syntax (PHP 8.1+)";
            }
            if ($tokenId === T_STRING && $tokenText === 'never') {
                $post80Features[] = "{$relativePath}:{$line} uses never type syntax (PHP 8.1+)";
            }
            if ($tokenId === T_ELLIPSIS) {
                $next = release_runtime_next_significant_token($tokens, $index + 1);
                if ($next === ')') {
                    $post80Features[] = "{$relativePath}:{$line} uses first-class callable syntax (PHP 8.1+)";
                }
            }
        }
    }

    return [
        'parse_errors' => $parseErrors,
        'post_80_features' => $post80Features,
        'native_calls' => $nativeCalls,
    ];
}

function release_runtime_constraint_floor(string $constraint): ?string
{
    if (preg_match('/>=\s*([0-9]+(?:\.[0-9]+){0,2})/', $constraint, $matches) === 1) {
        return $matches[1];
    }
    if (preg_match('/(?:\^|~)\s*([0-9]+(?:\.[0-9]+){0,2})/', $constraint, $matches) === 1) {
        return $matches[1];
    }

    return null;
}

$minimumPhpVersionId = 80100;
release_runtime_check(
    PHP_VERSION_ID >= $minimumPhpVersionId && PHP_VERSION_ID < 80200,
    'PHP CLI ' . PHP_VERSION . ' meets the approved PHP 8.1 staging range',
    'PHP CLI ' . PHP_VERSION . ' is outside the approved PHP 8.1 staging range'
);
release_runtime_info('SAPI observed by this smoke: ' . PHP_SAPI . '; web/FPM remains a separate preflight');

$requiredExtensions = ['mysqli', 'mbstring', 'curl', 'zip', 'xml', 'openssl', 'sodium', 'session', 'json'];
foreach ($requiredExtensions as $extension) {
    release_runtime_check(
        extension_loaded($extension),
        "core extension {$extension} is loaded",
        "required core extension {$extension} is not loaded"
    );
}

if (extension_loaded('fileinfo')) {
    release_runtime_info('optional fileinfo extension is loaded; verify the same state in web/FPM');
} else {
    release_runtime_warn(
        'optional fileinfo extension is not loaded; core smoke continues, but WhatsApp MIME/file paths remain at risk'
    );
}

$composerPath = $root . '/composer.json';
$composerSource = @file_get_contents($composerPath);
release_runtime_check(
    $composerSource !== false,
    'composer.json is readable',
    'composer.json is missing or unreadable'
);

$composer = null;
if ($composerSource !== false) {
    $composer = json_decode($composerSource, true);
    release_runtime_check(
        is_array($composer) && json_last_error() === JSON_ERROR_NONE,
        'composer.json contains valid JSON',
        'composer.json is invalid JSON: ' . json_last_error_msg()
    );
}

if (is_array($composer)) {
    $phpConstraint = $composer['require']['php'] ?? null;
    release_runtime_check(
        is_string($phpConstraint) && trim($phpConstraint) !== '',
        'composer.json declares a PHP constraint',
        'composer.json does not declare require.php'
    );

    if (is_string($phpConstraint) && trim($phpConstraint) !== '') {
        release_runtime_info("composer.json require.php currently declares {$phpConstraint}");
        $declaredFloor = release_runtime_constraint_floor($phpConstraint);
        if ($declaredFloor === null) {
            release_runtime_warn('composer.json PHP floor could not be compared without Composer constraint parsing');
        } elseif (version_compare($declaredFloor, '8.1', '<')) {
            release_runtime_warn(
                "composer.json PHP floor {$declaredFloor} underclaims the approved runtime baseline"
            );
        }
    }
}

$lockPath = $root . '/composer.lock';
$lockPresentBefore = is_file($lockPath);
if ($lockPresentBefore) {
    $lockSource = @file_get_contents($lockPath);
    $lockData = $lockSource === false ? null : json_decode($lockSource, true);
    release_runtime_check(
        is_array($lockData) && json_last_error() === JSON_ERROR_NONE,
        'existing composer.lock contains valid JSON',
        'existing composer.lock is unreadable or invalid JSON'
    );
    release_runtime_info('composer.lock is present; this smoke did not generate it');
} else {
    release_runtime_warn('composer.lock is absent; allowed for this smoke, but dependency reproducibility is unresolved');
}

$gitignorePath = $root . '/.gitignore';
$gitignoreSource = @file_get_contents($gitignorePath);
if ($gitignoreSource === false) {
    release_runtime_warn('.gitignore is unreadable; composer.lock ignore status is unknown');
} elseif (preg_match('/^\s*composer\.lock\s*$/m', $gitignoreSource) === 1) {
    release_runtime_info('composer.lock is currently ignored by .gitignore');
} else {
    release_runtime_info('composer.lock is not explicitly ignored by .gitignore');
}

$productionFiles = release_runtime_production_php_files($root);
release_runtime_check(
    $productionFiles !== [],
    count($productionFiles) . ' production PHP source files were discovered',
    'no production PHP source files were discovered under index.php/application/system'
);

if ($productionFiles !== []) {
    $inspection = release_runtime_inspect_source($productionFiles, $root);
    release_runtime_check(
        $inspection['parse_errors'] === [],
        count($productionFiles) . ' production PHP files parse on the active PHP >=8 runtime',
        'production PHP parse blockers found: ' . implode(' | ', array_slice($inspection['parse_errors'], 0, 10))
    );
    foreach ($inspection['native_calls'] as $function => $count) {
        release_runtime_check(
            $count > 0,
            "production source contains {$count} call(s) to PHP 8 native {$function}()",
            "production source no longer contains the audited PHP 8 baseline marker {$function}()"
        );
    }
}

release_runtime_check(
    is_file($lockPath) === $lockPresentBefore,
    'composer.lock presence was not changed by the smoke',
    'composer.lock presence changed while the smoke was running'
);

if ($failures !== []) {
    fwrite(
        STDERR,
        'FAIL: release runtime contract smoke; '
        . count($failures) . " failure(s), " . count($warnings) . " warning(s)\n"
    );
    exit(1);
}

echo 'PASS: release runtime contract smoke; '
    . $checks . " checks, " . count($warnings) . " warning(s), "
    . count($information) . " informational result(s)\n";
