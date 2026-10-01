<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gowes_participant_import
{
    public static function email($value): string
    {
        if (!is_string($value)) return '';
        $email = strtolower(trim($value));
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public function read(string $path): array
    {
        if (!is_file($path) || filesize($path) > 2 * 1024 * 1024 || !class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'Gunakan file XLSX maksimal 2 MB.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return ['ok' => false, 'message' => 'File bukan XLSX yang valid.'];
        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) $bytes += (int)($zip->statIndex($i)['size'] ?? 0);
        $zip->close();
        if ($bytes > 20 * 1024 * 1024) return ['ok' => false, 'message' => 'Isi file terlalu besar.'];
        require_once APPPATH . 'libraries/SimpleSpreadsheetIO.php';
        $reader = new SimpleSpreadsheetIO();
        $parsed = $reader->parse_file($path);
        if (empty($parsed['ok'])) return $parsed;
        if (!in_array('email', $parsed['headers'], true)) return ['ok' => false, 'message' => 'Kolom email wajib ada pada baris pertama.'];
        if (count($parsed['rows']) > 5000) return ['ok' => false, 'message' => 'Maksimal 5.000 peserta per file.'];
        return self::prepare($parsed['rows']);
    }

    public static function prepare(array $rows): array
    {
        $unique = []; $invalid = []; $duplicates = 0;
        foreach ($rows as $index => $row) {
            $email = self::email($row['email'] ?? null);
            if ($email === '') { $invalid[] = $index + 2; continue; }
            if (isset($unique[$email])) { $duplicates++; continue; }
            $name = trim((string)($row['nama_lengkap'] ?? $row['nama'] ?? ''));
            $unique[$email] = ['email' => $email, 'participant_name' => mb_substr($name, 0, 150)];
        }
        return ['ok' => true, 'rows' => array_values($unique), 'total_rows' => count($rows),
            'duplicate_rows' => $duplicates, 'invalid_rows' => count($invalid), 'invalid_row_numbers' => $invalid];
    }
}
