<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Bounded, shared-worker rate limit; stores hashed keys, not emails or IPs. */
class Gowes_claim_guard
{
    private string $directory;
    public function __construct(array $options = [])
    {
        // A short-lived abuse guard belongs outside the web root and must not
        // depend on the deployment owner of application/cache.
        $this->directory = $options['directory'] ?? rtrim(sys_get_temp_dir(), '/')
            . '/finance-gowes-guard-' . substr(hash('sha256', APPPATH), 0, 16);
    }

    public function attempt(string $ip, string $session): array
    {
        $handle = null;
        try {
            if ($session === '' || !filter_var($ip, FILTER_VALIDATE_IP)) throw new RuntimeException('identity');
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) throw new RuntimeException('directory');
            $file = $this->directory . '/buckets.php';
            if (is_link($file) || is_link($this->directory)) throw new RuntimeException('symlink');
            $handle = @fopen($file, 'c+b');
            if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('lock');
            @chmod($file, 0600);
            $prefix = "<?php exit; ?>\n";
            $raw = stream_get_contents($handle, 1048577);
            if ($raw === false || strlen($raw) > 1048576 || ($raw !== '' && strpos($raw, $prefix) !== 0)) throw new RuntimeException('state');
            $state = $raw === '' ? [] : json_decode(substr($raw, strlen($prefix)), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($state)) throw new RuntimeException('state');
            $now = time();
            foreach ($state as $key => $bucket) if ((int)($bucket['until'] ?? 0) <= $now) unset($state[$key]);
            $rules = [[hash('sha256', 'ip|' . $ip), 120], [hash('sha256', 'session|' . $session), 12]];
            foreach ($rules as [$key, $limit]) {
                if (($state[$key]['count'] ?? 0) >= $limit) return ['ok' => false, 'status' => 429, 'retry_after' => max(1, $state[$key]['until'] - $now)];
            }
            if (count($state) > 4094) throw new RuntimeException('capacity');
            foreach ($rules as [$key, $limit]) {
                if (!isset($state[$key])) $state[$key] = ['count' => 0, 'until' => $now + 600];
                $state[$key]['count']++;
            }
            $encoded = $prefix . json_encode($state, JSON_THROW_ON_ERROR);
            rewind($handle);
            if (fwrite($handle, $encoded) !== strlen($encoded) || !ftruncate($handle, strlen($encoded)) || !fflush($handle)) throw new RuntimeException('persist');
            return ['ok' => true];
        } catch (Throwable $error) { return ['ok' => false, 'status' => 503]; }
        finally { if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); } }
    }
}
