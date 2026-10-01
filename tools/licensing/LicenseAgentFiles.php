<?php
declare(strict_types=1);
require_once __DIR__.'/LicenseStateStore.php';

/** Linux root-owned state. Private keys never share a readable file with the web cache. */
final class LicenseAgentFiles implements LicenseStateStore
{
    private string $root;
    private int $group;

    public function __construct(string $directory, string $webroot, int $group, bool $private)
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) throw new RuntimeException('LINUX_ROOT_REQUIRED');
        self::securePath($directory, $webroot, true);
        $mode = fileperms($directory) & 0777;
        if ($mode !== ($private ? 0700 : 0750) || filegroup($directory) !== $group) throw new RuntimeException('STATE_DIRECTORY_PERMISSIONS');
        $this->root = $directory; $this->group = $group;
    }

    public static function securePath(string $path, string $webroot, bool $directory = false): void
    {
        $root = realpath($webroot);
        if ($root === false || realpath($path) !== $path || is_link($path)
            || ($directory ? !is_dir($path) : !is_file($path))
            || str_starts_with($path . '/', $root . '/')) throw new RuntimeException('STATE_PATH_UNSAFE');
        for ($part = $path; ; $part = dirname($part)) {
            $stat = stat($part);
            if (!is_array($stat) || (int)$stat['uid'] !== 0 || ($stat['mode'] & 0022) !== 0) throw new RuntimeException('STATE_OWNER_UNSAFE');
            if ($part === dirname($part)) break;
        }
    }

    private function path(string $name): string
    {
        if (!in_array($name, ['agent.json','agent.lock','identity.json','trust.json','runtime.json'], true)) throw new RuntimeException('STATE_FILENAME_INVALID');
        $path = $this->root . '/' . $name;
        if (is_link($path)) throw new RuntimeException('STATE_SYMLINK');
        return $path;
    }

    public function exists(string $name): bool { return file_exists($this->path($name)); }

    public function read(string $name, int $mode): array
    {
        $path = $this->path($name);
        if (!is_file($path) || !is_readable($path) || fileowner($path) !== 0
            || (fileperms($path) & 0777) !== $mode || filegroup($path) !== $this->group || filesize($path) > 300000) throw new RuntimeException('STATE_FILE_UNSAFE');
        $json = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($json)) throw new RuntimeException('STATE_JSON_INVALID');
        return $json;
    }

    public function write(string $name, array $value, int $mode): void
    {
        if (!in_array($mode, [0600,0640], true)) throw new RuntimeException('STATE_MODE_INVALID');
        $path = $this->path($name);
        if (file_exists($path) && (!is_file($path) || fileowner($path) !== 0 || (fileperms($path) & 0022) !== 0)) throw new RuntimeException('STATE_TARGET_UNSAFE');
        $bytes = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (strlen($bytes) > 300000) throw new RuntimeException('STATE_OVERSIZE');
        $temp = $this->root . '/.agent-write-' . bin2hex(random_bytes(12));
        $handle = fopen($temp, 'x+b');
        if ($handle === false) throw new RuntimeException('STATE_WRITE_FAILED');
        try {
            if (!chmod($temp, $mode) || !chgrp($temp, $this->group) || fwrite($handle, $bytes) !== strlen($bytes)
                || !fflush($handle) || !fsync($handle)) throw new RuntimeException('STATE_WRITE_FAILED');
            if (!rename($temp, $path)) throw new RuntimeException('STATE_RENAME_FAILED');
        } finally {
            fclose($handle);
            // Remove only this invocation's never-published temporary file.
            if (is_file($temp)) unlink($temp);
            clearstatcache();
        }
    }

    /** Caller holds this across read/network/write to serialize request and polling. */
    public function lock()
    {
        $path = $this->path('agent.lock');
        if (file_exists($path) && (!is_file($path) || fileowner($path) !== 0 || (fileperms($path) & 0777) !== 0600)) throw new RuntimeException('STATE_LOCK_UNSAFE');
        $h = fopen($path, 'c+b');
        if ($h === false) throw new RuntimeException('STATE_LOCK_UNAVAILABLE');
        chmod($path, 0600);
        if (!flock($h, LOCK_EX | LOCK_NB)) { fclose($h); throw new RuntimeException('AGENT_ALREADY_RUNNING'); }
        return $h;
    }
}
