<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Pure notification policy/formatting. No network or business mutations here. */
class Module_notification
{
    public const EVENTS = [
        'SELF_ORDER' => 'Self order masuk',
        'ONLINE_ORDER' => 'Order online masuk',
        'DIVISION_REQUEST' => 'Pengajuan PO / SR divisi',
    ];

    public static function events(string $channel): array
    {
        return self::EVENTS + ($channel === 'WA' ? ['DAILY_SALES' => 'Daily Sales (PDF)'] : []);
    }

    public static function validChannel(string $channel): bool
    {
        return in_array($channel, ['WA', 'TELEGRAM'], true);
    }

    public static function targets($values, string $phones, array $available, string $channel): array
    {
        if (!self::validChannel($channel) || !is_array($values)) {
            throw new InvalidArgumentException('Pilihan tujuan tidak valid.');
        }
        $result = [];
        foreach ($values as $key) {
            if (!is_string($key) || !isset($available[$key])) {
                throw new InvalidArgumentException('Tujuan tidak tersedia. Muat ulang pengaturan dan pilih kembali dari daftar tujuan terdaftar.');
            }
            $result[$key] = $available[$key];
        }
        if ($channel === 'WA') {
            foreach (preg_split('/[\r\n,;]+/', $phones) as $phone) {
                $phone = preg_replace('/[\s()+-]/', '', trim($phone));
                if ($phone === '') continue;
                if (substr($phone, 0, 1) === '0') $phone = '62' . substr($phone, 1);
                if (!preg_match('/\A[1-9][0-9]{7,14}\z/D', $phone)) {
                    throw new InvalidArgumentException('Nomor WhatsApp tidak valid. Contoh: 6281234567890; satu nomor per baris.');
                }
                $result['phone:' . $phone] = ['destination' => $phone, 'label' => $phone];
            }
        } elseif (trim($phones) !== '') {
            throw new InvalidArgumentException('Telegram memakai tujuan yang terdaftar, bukan nomor telepon.');
        }
        if (count($result) > 10) throw new InvalidArgumentException('Maksimal 10 tujuan per modul.');
        ksort($result);
        return $result;
    }

    public static function key(string $channel, string $event, int $id, string $target, string $revision = ''): string
    {
        return hash('sha256', implode('|', [$channel, $event, $id, $target, $revision]));
    }

    public static function clean($text, int $limit = 140): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$text)), 0, $limit, 'UTF-8');
    }

    public static function message(string $event, array $header, array $lines, string $url): string
    {
        if (!isset(self::EVENTS[$event])) throw new InvalidArgumentException('Jenis notifikasi tidak valid.');
        $division = $event === 'DIVISION_REQUEST';
        $parts = [self::EVENTS[$event],
            'Nomor: ' . self::clean($header[$division ? 'request_no' : 'order_no'] ?? '-'),
            'Waktu: ' . self::clean($header[$division ? 'request_date' : 'ordered_at'] ?? '-'),
            'Status: ' . self::clean($header['status'] ?? '-')];
        if ($division) {
            $parts[] = 'Divisi: ' . self::clean($header['division_name'] ?? '-');
        } else {
            $parts[] = 'Outlet: ' . self::clean($header['outlet_name'] ?? '-');
            if (!empty($header['table_no'])) $parts[] = 'Meja: ' . self::clean($header['table_no']);
            $parts[] = 'Total order: Rp ' . number_format((float)($header['grand_total'] ?? 0), 0, ',', '.');
            $parts[] = 'Notifikasi order masuk, bukan konfirmasi pembayaran.';
        }
        $tail = "\nBuka rincian (perlu login): " . $url;
        if (strlen($tail) > 1500) $tail = "\nBuka rincian melalui aplikasi (perlu login).";
        $budget = 3900 - strlen($tail) - 100;
        $message = mb_strcut(implode("\n", $parts), 0, min(1800, $budget), 'UTF-8');
        $shown = 0;
        foreach (array_slice($lines, 0, 12) as $line) {
            $item = '- ' . self::clean($line[$division ? 'profile_name' : 'product_name'] ?? 'Item', 80)
                . ' x ' . self::clean($line[$division ? 'qty_buy_requested' : 'qty'] ?? 0, 24)
                . ($division ? ' ' . self::clean($line['profile_buy_uom_code'] ?? '', 15) : '');
            if (strlen($message) + strlen($item) + 1 > $budget) break;
            $message .= "\n" . $item;
            $shown++;
        }
        if (count($lines) > $shown) $message .= "\n... dan item lainnya. Lihat rincian di aplikasi.";
        return $message . $tail;
    }
}
