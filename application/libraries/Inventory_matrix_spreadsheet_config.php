<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_matrix_spreadsheet_config
{
    private const FILE = '/var/lib/finance-secrets/runtime/inventory-matrix-spreadsheet.json';

    public static function path(): string
    {
        return self::FILE;
    }

    public static function load(): array
    {
        if (!is_file(self::FILE) || !is_readable(self::FILE)) return [];
        $data = json_decode((string)file_get_contents(self::FILE), true);
        if (!is_array($data) || !preg_match('/^[A-Za-z0-9_-]{20,150}$/D', (string)($data['spreadsheet_id'] ?? ''))
            || !is_array($data['division_sheets'] ?? null) || !is_numeric($data['warehouse_sheet_id'] ?? null)) {
            throw new RuntimeException('Konfigurasi spreadsheet daily matrix rusak. Hubungi administrator.');
        }
        return $data;
    }

    public static function parseSpreadsheetUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'docs.google.com'
            || !preg_match('~^/spreadsheets/d/([A-Za-z0-9_-]{20,150})(?:/|$)~D', (string)($parts['path'] ?? ''), $match)) {
            throw new RuntimeException('Masukkan tautan Google Spreadsheet yang valid.');
        }
        return $match[1];
    }

    public static function save(array $settings): void
    {
        $directory = dirname(self::FILE);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Folder pengaturan spreadsheet belum dapat ditulis oleh aplikasi.');
        }
        $temporary = tempnam($directory, '.inventory-sheet-');
        if ($temporary === false) throw new RuntimeException('Pengaturan spreadsheet belum dapat disimpan.');
        try {
            $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($temporary, $json, LOCK_EX) === false || !@chmod($temporary, 0600)
                || !@rename($temporary, self::FILE)) {
                throw new RuntimeException('Pengaturan spreadsheet belum dapat disimpan.');
            }
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    public static function normalize(array $settings): array
    {
        $spreadsheetId = (string)($settings['spreadsheet_id'] ?? '');
        $warehouseId = filter_var($settings['warehouse_sheet_id'] ?? null, FILTER_VALIDATE_INT);
        $divisionSheets = $settings['division_sheets'] ?? null;
        if (!preg_match('/^[A-Za-z0-9_-]{20,150}$/D', $spreadsheetId) || $warehouseId === false || $warehouseId < 0 || !is_array($divisionSheets)) {
            throw new RuntimeException('Pengaturan spreadsheet atau pemetaan tab tidak valid.');
        }
        $normalized = ['spreadsheet_id' => $spreadsheetId, 'warehouse_sheet_id' => (int)$warehouseId, 'division_sheets' => []];
        foreach ($divisionSheets as $divisionId => $sheetId) {
            if (!preg_match('/^[1-9][0-9]{0,8}$/D', (string)$divisionId)) throw new RuntimeException('ID divisi pada pemetaan tab tidak valid.');
            $parsed = filter_var($sheetId, FILTER_VALIDATE_INT);
            if ($parsed === false || $parsed < 0) throw new RuntimeException('Pilih tab spreadsheet yang valid untuk setiap divisi.');
            $normalized['division_sheets'][(string)(int)$divisionId] = (int)$parsed;
        }
        return $normalized;
    }
}
