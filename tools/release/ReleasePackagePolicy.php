<?php

declare(strict_types=1);

final class ReleasePackagePolicy
{
    private array $policy;

    public function __construct(array $policy)
    {
        if (!self::isValid($policy)) {
            throw new InvalidArgumentException('Invalid release package policy.');
        }
        $this->policy = $policy;
    }

    public static function fromFile(string $path): self
    {
        $raw = is_file($path) ? @file_get_contents($path) : false;
        $policy = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($policy)) {
            throw new InvalidArgumentException('Invalid release package policy.');
        }
        return new self($policy);
    }

    public function data(): array
    {
        return $this->policy;
    }

    public static function isValid($policy): bool
    {
        if (!is_array($policy)
            || ($policy['schema'] ?? null) !== 'finance.release-package-policy'
            || ($policy['schema_version'] ?? null) !== 2
        ) {
            return false;
        }
        $fields = ['include_roots', 'include_files', 'allow_exact', 'deny_exact', 'deny_prefixes', 'deny_segments',
            'deny_basenames', 'deny_suffixes', 'secret_scan_extensions'];
        foreach ($fields as $field) {
            if (!isset($policy[$field]) || !is_array($policy[$field]) || $policy[$field] === []) {
                return false;
            }
            foreach ($policy[$field] as $value) {
                if (!is_string($value) || trim($value) === '' || strpos($value, "\0") !== false) {
                    return false;
                }
            }
        }
        foreach (array_merge($policy['include_roots'], $policy['include_files'], $policy['allow_exact'], $policy['deny_exact']) as $path) {
            if (!self::relativePathValid($path)) {
                return false;
            }
        }
        foreach ($policy['include_roots'] as $prefix) {
            if (substr($prefix, -1) !== '/') {
                return false;
            }
        }
        $required = [
            'include_roots' => ['application/', 'assets/', 'docs/', 'scripts/', 'sql/', 'system/', 'tools/', 'wa-engine/'],
            'include_files' => ['.htaccess', 'README.md', 'composer.lock', 'composer.json', 'index.php', 'license.txt', 'readme.rst'],
            'deny_exact' => ['.user.ini', 'application/controllers/Audit.php', 'application/libraries/AuditRoadmapReader.php', 'docs/_NOTE.md'],
            'deny_prefixes' => ['application/cache/', 'application/logs/', 'application/views/audit/', 'assets/uploads/', 'backup/', 'docs/2026-', 'docs/_NOTE', 'docs/_old/', 'output/', 'sql/_old/', 'tmp/', 'uploads/', 'vendor/'],
            'deny_segments' => ['/.runtime/', '/.venv/', '/__pycache__/', '/node_modules/'],
            'deny_basenames' => ['.env'],
            'deny_suffixes' => ['.log', '.pid', '.sql.gz', '_bak.php'],
            'secret_scan_extensions' => ['php', 'js', 'py', 'sh', 'json', 'ini', 'yaml', 'yml', 'xml', 'sql'],
        ];
        foreach ($required as $field => $values) {
            foreach ($values as $value) {
                if (!in_array($value, $policy[$field], true)) {
                    return false;
                }
            }
        }
        if (!isset($policy['secret_fixture_exceptions']) || !is_array($policy['secret_fixture_exceptions'])) {
            return false;
        }
        foreach ($policy['secret_fixture_exceptions'] as $exception) {
            if (!is_array($exception)
                || !isset($exception['path'], $exception['line'], $exception['category'], $exception['sha256'])
                || !is_string($exception['path']) || !self::relativePathValid($exception['path'])
                || strpos($exception['path'], '*') !== false
                || !is_int($exception['line']) || $exception['line'] < 1
                || !is_string($exception['category'])
                || !is_string($exception['sha256'])
                || preg_match('/\A[a-f0-9]{64}\z/D', $exception['sha256']) !== 1
            ) {
                return false;
            }
        }
        return true;
    }

    public static function relativePathValid(string $path): bool
    {
        return $path !== ''
            && $path[0] !== '/'
            && strpos($path, '\\') === false
            && preg_match('/[\x00-\x1f\x7f]/', $path) !== 1
            && !in_array('..', explode('/', rtrim($path, '/')), true)
            && !in_array('.', explode('/', rtrim($path, '/')), true);
    }

    public function included(string $path): bool
    {
        if (in_array($path, $this->policy['include_files'], true)) {
            return true;
        }
        foreach ($this->policy['include_roots'] as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    public function denied(string $path): bool
    {
        if (in_array($path, $this->policy['allow_exact'], true)) {
            return false;
        }
        if (in_array($path, $this->policy['deny_exact'], true)) {
            return true;
        }
        foreach ($this->policy['deny_prefixes'] as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }
        $wrapped = '/' . ltrim($path, '/') . '/';
        foreach ($this->policy['deny_segments'] as $segment) {
            if (strpos($wrapped, $segment) !== false) {
                return true;
            }
        }
        if (in_array(basename($path), $this->policy['deny_basenames'], true)) {
            return true;
        }
        foreach ($this->policy['deny_suffixes'] as $suffix) {
            if (substr($path, -strlen($suffix)) === $suffix) {
                return true;
            }
        }
        return false;
    }

    public static function absoluteFileProblem(string $path, bool $enforceSize = false, int $maxBytes = 2097152): ?string
    {
        if (is_link($path)) {
            return 'PACKAGE_SYMLINK';
        }
        if (!is_file($path) || !is_readable($path)) {
            return 'PACKAGE_FILE_UNREADABLE';
        }
        $size = @filesize($path);
        if ($size === false) {
            return 'PACKAGE_FILE_UNREADABLE';
        }
        return $enforceSize && $size > $maxBytes ? 'SECRET_SCAN_OVERSIZE' : null;
    }

    private static function pathProblem(string $root, string $relative): ?string
    {
        if (!self::relativePathValid($relative)) {
            return 'PACKAGE_PATH_INVALID';
        }
        $cursor = $root;
        foreach (explode('/', $relative) as $segment) {
            $cursor .= '/' . $segment;
            if (is_link($cursor)) {
                return 'PACKAGE_SYMLINK';
            }
        }
        $problem = self::absoluteFileProblem($root . '/' . $relative);
        if ($problem !== null) {
            return $problem;
        }
        $real = realpath($root . '/' . $relative);
        return $real === false || strpos($real, $root . '/') !== 0 ? 'PACKAGE_PATH_INVALID' : null;
    }

    /** @return array{files:string[],excluded_files:int,issues:array<int,array{category:string,path:string}>} */
    public function enumerate(string $root): array
    {
        $root = realpath($root) ?: '';
        if ($root === '' || !is_dir($root)) {
            return ['files' => [], 'excluded_files' => 0, 'issues' => [['category' => 'SOURCE_ROOT_INVALID', 'path' => '.']]];
        }
        $process = @proc_open(['git', 'ls-files', '--cached', '-z'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (!is_resource($process)) {
            return ['files' => [], 'excluded_files' => 0, 'issues' => [['category' => 'SOURCE_MANIFEST_UNAVAILABLE', 'path' => '.git']]];
        }
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || !is_string($output) || strlen($output) > 16777216) {
            return ['files' => [], 'excluded_files' => 0, 'issues' => [['category' => 'SOURCE_MANIFEST_UNAVAILABLE', 'path' => '.git']]];
        }
        $paths = array_values(array_unique(array_filter(explode("\0", $output), static fn(string $path): bool => $path !== '')));
        sort($paths, SORT_STRING);
        $files = [];
        $issues = [];
        $excluded = 0;
        foreach ($paths as $path) {
            if (!self::relativePathValid($path)) {
                $issues[] = ['category' => 'PACKAGE_PATH_INVALID', 'path' => $path];
                continue;
            }
            if (!$this->included($path)) {
                continue;
            }
            if ($this->denied($path)) {
                $excluded++;
                continue;
            }
            if (!file_exists($root . '/' . $path) && !is_link($root . '/' . $path)) {
                continue;
            }
            $problem = self::pathProblem($root, $path);
            if ($problem !== null) {
                $issues[] = ['category' => $problem, 'path' => $path];
                continue;
            }
            $files[] = $path;
        }
        return ['files' => $files, 'excluded_files' => $excluded, 'issues' => $issues];
    }

    /**
     * A release artifact must represent one committed source state. Runtime
     * files, local notes, and a developer's untracked work must never be
     * silently folded into the customer package.
     */
    public static function worktreeClean(string $root): bool
    {
        $root = realpath($root) ?: '';
        if ($root === '' || !is_dir($root)) {
            return false;
        }
        $process = @proc_open(['git', 'status', '--porcelain=v1', '-z', '--untracked-files=all'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (!is_resource($process)) {
            return false;
        }
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0 && is_string($output) && $output === '';
    }
}
