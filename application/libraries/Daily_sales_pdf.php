<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Private, immutable report snapshots for the existing WA document worker. */
class Daily_sales_pdf
{
    public static function filters($date, $outlet): array
    {
        if (!is_string($date) || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $date, $m)
            || (int)$m[1] < 1900 || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new InvalidArgumentException('Tanggal laporan tidak valid. Pilih tanggal dan klik Tampilkan terlebih dahulu.');
        }
        if ((!is_string($outlet) && !is_int($outlet)) || !preg_match('/\A[0-9]{1,10}\z/D', (string)$outlet) || (int)$outlet > 2147483647) {
            throw new InvalidArgumentException('Outlet laporan tidak valid. Pilih outlet dan klik Tampilkan terlebih dahulu.');
        }
        return ['date' => $date, 'outlet_id' => (int)$outlet];
    }

    public static function snapshot(array $filters, array $dataset, string $outletName): string
    {
        return hash('sha256', json_encode(['daily-sales-v1', $filters, $dataset, $outletName], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function directory(): string
    {
        $directory = APPPATH . 'cache/wa-attachments';
        if (is_link(APPPATH . 'cache') || is_link($directory)) throw new RuntimeException('Folder PDF tidak aman. Minta administrator memeriksa penyimpanan lampiran.');
        return $directory;
    }

    public static function assertAttachment(array $attachment): void
    {
        $directory = self::directory();
        $path = (string)($attachment['path'] ?? '');
        $name = (string)($attachment['name'] ?? '');
        if (!preg_match('/\Adaily-sales-\d{4}-\d{2}-\d{2}-outlet-\d+\.pdf\z/D', $name)
            || !preg_match('/\Adaily-sales-[a-f0-9]{64}\.pdf\z/D', basename($path))
            || is_link($path) || !is_file($path) || !is_readable($path)
            || realpath(dirname($path)) !== realpath($directory)
            || filesize($path) < 5 || filesize($path) > 10 * 1024 * 1024
            || file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('PDF Daily Sales tidak tersedia atau tidak valid. Buat kembali dari halaman laporan.');
        }
    }

    public function create(string $html, array $filters, string $snapshot): array
    {
        $filters = self::filters($filters['date'] ?? null, $filters['outlet_id'] ?? null);
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $snapshot)) throw new InvalidArgumentException('Snapshot laporan tidak valid.');
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 02770, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder PDF belum dapat disiapkan. Minta administrator memeriksa izin lampiran WA.');
        }
        $attachment = ['path' => $directory . '/daily-sales-' . $snapshot . '.pdf',
            'name' => 'daily-sales-' . $filters['date'] . '-outlet-' . $filters['outlet_id'] . '.pdf'];
        if (is_file($attachment['path']) || is_link($attachment['path'])) {
            self::assertAttachment($attachment);
            return $attachment;
        }
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('exec')) {
            throw new RuntimeException('Pembuatan PDF WA memerlukan renderer Chrome pada server Linux. Cetak / PDF di browser tetap dapat digunakan.');
        }
        // Only scratch files created by this call are cleaned. Existing attachments are retained.
        $job = $directory . '/.daily-' . bin2hex(random_bytes(12));
        if (!@mkdir($job, 0700)) throw new RuntimeException('Folder kerja PDF tidak dapat dibuat. Periksa izin penyimpanan lampiran WA.');
        try {
            if (file_put_contents($job . '/report.html', $html, LOCK_EX) === false || !chmod($job . '/report.html', 0600)) {
                throw new RuntimeException('Laporan belum dapat disiapkan untuk PDF.');
            }
            $args = ['timeout', '--kill-after=5s', '40s', '/usr/bin/google-chrome', '--headless', '--no-sandbox', '--disable-gpu',
                '--disable-dev-shm-usage', '--disable-background-networking', '--disable-extensions', '--disable-javascript',
                '--host-resolver-rules=MAP * ~NOTFOUND', '--no-first-run', '--no-pdf-header-footer',
                '--user-data-dir=' . $job . '/profile', '--print-to-pdf=' . $job . '/report.pdf',
                'file://' . str_replace('%2F', '/', rawurlencode($job . '/report.html'))];
            exec(implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null', $output, $exitCode);
            $rendered = $job . '/report.pdf';
            if ($exitCode !== 0 || !is_file($rendered) || filesize($rendered) < 5
                || filesize($rendered) > 10 * 1024 * 1024 || file_get_contents($rendered, false, null, 0, 5) !== '%PDF-') {
                throw new RuntimeException('PDF belum berhasil dibuat (batas 40 detik / 10 MB). Pastikan renderer Google Chrome tersedia, lalu coba kembali.');
            }
            if (!chmod($rendered, 0640)) throw new RuntimeException('Izin PDF tidak dapat disiapkan.');
            // Hard link publishes atomically without replacing another request's snapshot.
            if (!@link($rendered, $attachment['path']) && !is_file($attachment['path'])) {
                throw new RuntimeException('PDF belum dapat disimpan untuk pengiriman WA.');
            }
            self::assertAttachment($attachment);
            return $attachment;
        } finally {
            $this->removeScratch($job);
        }
    }

    private function removeScratch(string $job): void
    {
        $entries = @scandir($job);
        if ($entries === false) return;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $job . '/' . $entry;
            if (is_dir($path) && !is_link($path)) $this->removeScratch($path);
            else @unlink($path);
        }
        @rmdir($job);
    }
}
