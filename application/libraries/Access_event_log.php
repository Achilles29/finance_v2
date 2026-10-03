<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Private, per-server page-view log kept outside MariaDB replication. */
class Access_event_log
{
    private $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? '/var/lib/finance-secrets/runtime/access-events-' . substr(hash('sha256', FCPATH), 0, 16);
    }

    public function append(array $event): bool
    {
        if (!$this->ensureDirectory()) return false;
        $eventAt = (string)($event['event_at'] ?? '');
        if (!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', $eventAt)) return false;
        if (!$this->ensureCutover($eventAt)) return false;

        $date = substr($eventAt, 0, 10);
        $path = $this->directory . '/access-' . $date . '.jsonl';
        $json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) return false;

        $handle = @fopen($path, 'ab');
        if ($handle === false) return false;
        $ok = false;
        try {
            if (!flock($handle, LOCK_EX)) return false;
            $line = $json . "\n";
            $written = 0;
            $length = strlen($line);
            while ($written < $length) {
                $chunk = fwrite($handle, substr($line, $written));
                if ($chunk === false || $chunk === 0) break;
                $written += $chunk;
            }
            $ok = $written === $length;
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
        if ($ok) @chmod($path, 0600);
        $this->pruneOldFiles($date);
        return $ok;
    }

    public function cutover(): ?string
    {
        if (!$this->ensureDirectory()) return null;
        $path = $this->directory . '/cutover.txt';
        if (is_link($path) || !is_file($path) || !is_readable($path)) return null;
        $value = trim((string)@file_get_contents($path));
        return preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', $value) ? $value : null;
    }

    public function eventsBetween(string $from, string $until): iterable
    {
        if (!$this->ensureDirectory()) return;
        $start = strtotime(substr($from, 0, 10));
        $end = strtotime(substr($until, 0, 10));
        if ($start === false || $end === false || $end <= $start) return;

        for ($day = $start; $day < $end; $day += 86400) {
            $date = date('Y-m-d', $day);
            $path = $this->directory . '/access-' . $date . '.jsonl';
            if (is_link($path) || !is_file($path) || !is_readable($path)) continue;
            $handle = @fopen($path, 'rb');
            if ($handle === false) continue;
            try {
                while (($line = fgets($handle)) !== false) {
                    $event = json_decode($line, true);
                    if (!is_array($event) || !$this->validEvent($event)) continue;
                    if ($event['event_at'] >= $from && $event['event_at'] < $until) yield $event;
                }
            } finally {
                fclose($handle);
            }
        }
    }

    private function ensureDirectory(): bool
    {
        if (is_link($this->directory)) return false;
        if (!is_dir($this->directory)) {
            $parent = dirname($this->directory);
            if (is_link($parent) || !is_dir($parent) || !is_writable($parent)) return false;
            if (!@mkdir($this->directory, 0700) && !is_dir($this->directory)) return false;
        }
        if (is_link($this->directory) || !is_writable($this->directory) || !is_readable($this->directory)) return false;
        @chmod($this->directory, 0700);
        return true;
    }

    private function ensureCutover(string $eventAt): bool
    {
        $path = $this->directory . '/cutover.txt';
        if (is_link($path)) return false;
        $handle = @fopen($path, 'c+b');
        if ($handle === false) return false;
        if (!flock($handle, LOCK_EX)) { fclose($handle); return false; }
        $existing = stream_get_contents($handle);
        if (is_string($existing) && trim($existing) !== '') {
            $ok = preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', trim($existing)) === 1;
        } else {
            rewind($handle);
            $ok = fwrite($handle, $eventAt . "\n") !== false;
            fflush($handle);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($path, 0600);
        return $ok;
    }

    private function pruneOldFiles(string $today): void
    {
        $marker = $this->directory . '/pruned-on.txt';
        if (is_link($marker)) return;
        $last = is_file($marker) ? trim((string)@file_get_contents($marker)) : '';
        if ($last === $today) return;

        $cutoff = strtotime($today . ' -90 days');
        foreach (glob($this->directory . '/access-*.jsonl') ?: [] as $path) {
            if (is_link($path) || !preg_match('/access-(\d{4}-\d{2}-\d{2})\.jsonl\z/D', basename($path), $match)) continue;
            $fileDay = strtotime($match[1]);
            if ($fileDay !== false && $cutoff !== false && $fileDay < $cutoff) @unlink($path);
        }
        @file_put_contents($marker, $today . "\n", LOCK_EX);
        @chmod($marker, 0600);
    }

    private function validEvent(array $event): bool
    {
        return isset($event['event_at'], $event['user_id'], $event['username'], $event['route_path'])
            && preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}\z/D', (string)$event['event_at']) === 1
            && (int)$event['user_id'] > 0
            && strlen((string)$event['route_path']) <= 255;
    }
}
