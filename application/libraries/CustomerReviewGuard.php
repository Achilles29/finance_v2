<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Single-host public review guard. Shared across PHP workers; no business data. */
class CustomerReviewGuard
{
    private const PREFIX = "<?php exit; ?>\n";
    private string $directory;
    private $clock;

    public function __construct(array $options = [])
    {
        $this->directory = $options['directory'] ?? APPPATH . 'cache/customer-review-guard';
        $this->clock = $options['clock'] ?? static function (): int { return time(); };
    }

    public function issue(string $target, string $device): array
    {
        return $this->state(function (array &$state, int $now) use ($target, $device): array {
            if ($device === '') return $this->unavailable();
            $nonce = bin2hex(random_bytes(16));
            $signature = $this->tag($state, [$target, $device, $now, $nonce]);
            return ['ok' => true, 'token' => $now . '.' . $nonce . '.' . $signature];
        });
    }

    /** Count all POST attempts, including malformed inputs, before reading members. */
    public function attempt(string $ip, string $device): array
    {
        return $this->state(function (array &$state, int $now) use ($ip, $device): array {
            if ($device === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) return $this->unavailable();
            return $this->reserve($state, $now, [
                [$this->tag($state, ['ip', $ip]), 60, 600],
                [$this->tag($state, ['device-attempt', $device]), 12, 600],
            ], $ip, 'attempt_limit');
        });
    }

    public function authorize(string $target, string $device, string $ip, array $input, string $identity, string $content): array
    {
        return $this->state(function (array &$state, int $now) use ($target, $device, $ip, $input, $identity, $content): array {
            $form = $input['_review_guard'] ?? '';
            $trap = $input['website'] ?? '';
            $valid = is_string($form) && preg_match('/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', $form, $parts);
            if (!is_string($trap) || $trap !== '' || !$valid || $device === ''
                || !hash_equals($this->tag($state, [$target, $device, (int)$parts[1], $parts[2]]), $parts[3])
                || $now - (int)$parts[1] < 2 || $now - (int)$parts[1] > 3600
            ) {
                $this->event($state, $now, $ip, 'invalid_form');
                return ['ok' => false, 'status' => 403, 'message' => 'Formulir belum siap atau sudah kedaluwarsa. Muat ulang halaman, tunggu sebentar, lalu kirim kembali.'];
            }
            $duplicateKey = $this->tag($state, ['duplicate', $target, $identity, $content]);
            $result = $this->reserve($state, $now, [
                [$this->tag($state, ['nonce', $form]), 1, 3600],
                [$this->tag($state, ['cooldown', $device]), 1, 60],
                [$this->tag($state, ['identity', $identity]), 1, 60],
                [$duplicateKey, 1, 600],
            ], $ip, 'repeat_submission');
            if ($result['ok']) $result['reservation'] = $duplicateKey;
            return $result;
        });
    }

    public function outcome(string $ip, string $reservation, array $result): void
    {
        $this->state(function (array &$state, int $now) use ($ip, $reservation, $result): array {
            if (empty($result['ok']) && isset($state['buckets'][$reservation])) {
                // A failed database operation may be retried after the normal cooldown.
                $state['buckets'][$reservation]['until'] = $now + 60;
            }
            $this->event($state, $now, $ip, !empty($result['ok']) ? 'accepted' : 'save_failed', [
                'review_id' => max(0, (int)($result['review_id'] ?? 0)),
                'member_id' => max(0, (int)($result['member_id'] ?? 0)),
                'member_created' => !empty($result['member_created']),
            ]);
            return ['ok' => true];
        });
    }

    private function reserve(array &$state, int $now, array $rules, string $ip, string $reason): array
    {
        $retry = 0;
        foreach ($rules as [$key, $limit, $window]) {
            if (($state['buckets'][$key]['count'] ?? 0) >= $limit) {
                $retry = max($retry, $state['buckets'][$key]['until'] - $now);
            }
        }
        if ($retry > 0) {
            $this->event($state, $now, $ip, $reason);
            return ['ok' => false, 'status' => 429, 'retry_after' => $retry,
                'message' => 'Pengiriman terlalu sering atau ulasan yang sama sudah dikirim. Tunggu ' . max(1, (int)ceil($retry / 60)) . ' menit sebelum mencoba lagi.'];
        }
        if (count($state['buckets']) + count($rules) > 4096) return $this->unavailable();
        foreach ($rules as [$key, $limit, $window]) {
            if (!isset($state['buckets'][$key])) $state['buckets'][$key] = ['count' => 0, 'until' => $now + $window];
            $state['buckets'][$key]['count']++;
        }
        return ['ok' => true];
    }

    private function tag(array $state, array $values): string
    {
        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), $state['secret']);
    }

    private function event(array &$state, int $now, string $ip, string $reason, array $metadata = []): void
    {
        $tag = $this->tag($state, ['audit-ip', $ip]);
        // Bounded diagnostics: sample repeated denial for the same IP/reason once/minute.
        foreach (array_reverse($state['events']) as $event) {
            if ($event['at'] < $now - 60) break;
            if ($reason !== 'accepted' && $event['ip_tag'] === $tag && $event['reason'] === $reason) return;
        }
        $state['events'][] = ['at' => $now, 'ip_tag' => $tag, 'reason' => $reason] + $metadata;
        $state['events'] = array_slice($state['events'], -200);
    }

    private function unavailable(): array
    {
        return ['ok' => false, 'status' => 503, 'message' => 'Formulir ulasan sementara belum tersedia. Silakan coba kembali atau hubungi tim outlet.'];
    }

    private function state(callable $callback): array
    {
        $file = null;
        try {
            if (is_link($this->directory)) return $this->unavailable();
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0700)) return $this->unavailable();
            $path = $this->directory . '/state.php';
            if (is_link($path)) return $this->unavailable();
            $file = @fopen($path, 'c+b');
            if (!$file || !flock($file, LOCK_EX | LOCK_NB)) return $this->unavailable();
            @chmod($path, 0600);
            $raw = stream_get_contents($file, 1048577);
            if ($raw === false || strlen($raw) > 1048576) return $this->unavailable();
            $state = $raw === '' ? ['secret' => bin2hex(random_bytes(32)), 'buckets' => [], 'events' => []]
                : json_decode(substr($raw, strlen(self::PREFIX)), true);
            if (($raw !== '' && strpos($raw, self::PREFIX) !== 0) || !is_array($state)
                || !is_string($state['secret'] ?? null) || strlen($state['secret']) !== 64
                || !is_array($state['buckets'] ?? null) || !is_array($state['events'] ?? null)
                || count($state['buckets']) > 4096 || count($state['events']) > 200
            ) return $this->unavailable();
            $now = (int)call_user_func($this->clock);
            foreach ($state['buckets'] as $key => $bucket) {
                if (!is_array($bucket) || !is_int($bucket['until'] ?? null) || !is_int($bucket['count'] ?? null)) return $this->unavailable();
                if ($bucket['until'] <= $now) unset($state['buckets'][$key]);
            }
            $state['events'] = array_values(array_filter($state['events'], static function ($event) use ($now): bool {
                return is_array($event) && isset($event['at'], $event['ip_tag'], $event['reason']) && $event['at'] >= $now - 86400;
            }));
            $result = $callback($state, $now);
            $encoded = self::PREFIX . json_encode($state, JSON_THROW_ON_ERROR);
            rewind($file);
            if (fwrite($file, $encoded) !== strlen($encoded) || !ftruncate($file, strlen($encoded)) || !fflush($file)) return $this->unavailable();
            return $result;
        } catch (Throwable $error) {
            return $this->unavailable();
        } finally {
            if (is_resource($file)) { flock($file, LOCK_UN); fclose($file); }
        }
    }
}
