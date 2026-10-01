<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Google Sheets client. Destination changes are validated before saving to private runtime config. */
class Product_sheet_client
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_URL = 'https://sheets.googleapis.com/v4/spreadsheets/';
    private $token;
    private $settings;

    public function __construct(array $settings = [])
    {
        if (!$settings) {
            $path = getenv('FINANCE_PRODUCT_SHEET_CONFIG') ?: '/var/lib/finance-secrets/product-spreadsheet.json';
            if (!@is_file($path) || !@is_readable($path)) {
                throw new RuntimeException('Koneksi spreadsheet belum dikonfigurasi di server.');
            }
            $settings = json_decode((string)file_get_contents($path), true);
        }
        if (!is_array($settings) || !preg_match('/^[A-Za-z0-9_-]{20,150}$/D', (string)($settings['spreadsheet_id'] ?? ''))
            || !isset($settings['sheet_id']) || !is_numeric($settings['sheet_id']) || (int)$settings['sheet_id'] < 0) {
            throw new RuntimeException('Konfigurasi tujuan spreadsheet tidak valid.');
        }
        $targetPath = $this->targetSettingsPath();
        if (is_file($targetPath) && is_readable($targetPath)) {
            $target = json_decode((string)file_get_contents($targetPath), true);
            if (is_array($target) && preg_match('/^[A-Za-z0-9_-]{20,150}$/D', (string)($target['spreadsheet_id'] ?? ''))
                && isset($target['sheet_id']) && is_numeric($target['sheet_id']) && (int)$target['sheet_id'] >= 0) {
                $settings = array_merge($settings, $target);
            }
        }
        $this->settings = $settings;
    }

    public function configureFromUrl(string $url): array
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'docs.google.com'
            || !preg_match('~^/spreadsheets/d/([A-Za-z0-9_-]{20,150})(?:/|$)~D', (string)($parts['path'] ?? ''), $match)) {
            throw new RuntimeException('Masukkan tautan Google Spreadsheet yang valid.');
        }
        parse_str((string)($parts['query'] ?? ''), $query);
        parse_str((string)($parts['fragment'] ?? ''), $fragment);
        $gid = $fragment['gid'] ?? $query['gid'] ?? null;
        if (!is_scalar($gid) || !preg_match('/^\d{1,12}$/D', (string)$gid)) {
            throw new RuntimeException('Tautan harus menunjuk tab tertentu dan memuat gid, misalnya #gid=123456.');
        }

        $previous = $this->settings;
        $this->settings['spreadsheet_id'] = $match[1];
        $this->settings['sheet_id'] = (int)$gid;
        try {
            $metadata = $this->metadata();
            $target = null;
            foreach ($metadata['sheets'] ?? [] as $sheet) {
                if ((int)($sheet['properties']['sheetId'] ?? -1) === (int)$gid
                    && ($sheet['properties']['sheetType'] ?? '') === 'GRID') $target = $sheet;
            }
            if (!$target) throw new RuntimeException('Tab dengan gid tersebut tidak ditemukan atau bukan tab lembar kerja.');
            $this->saveTargetSettings();
            return ['url' => $this->url(), 'spreadsheet_title' => (string)($metadata['properties']['title'] ?? ''),
                'sheet_title' => (string)$target['properties']['title']];
        } catch (Throwable $e) {
            $this->settings = $previous;
            throw $e;
        }
    }

    private function targetSettingsPath(): string
    {
        return '/var/lib/finance-secrets/runtime/product-spreadsheet-target.json';
    }

    private function saveTargetSettings(): void
    {
        $path = $this->targetSettingsPath();
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Folder pengaturan spreadsheet belum dapat ditulis oleh aplikasi.');
        }
        $temporary = tempnam($directory, '.product-sheet-');
        if ($temporary === false) throw new RuntimeException('Pengaturan spreadsheet belum dapat disimpan.');
        try {
            $json = json_encode(['spreadsheet_id' => $this->settings['spreadsheet_id'], 'sheet_id' => (int)$this->settings['sheet_id']], JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json, LOCK_EX) === false || !@chmod($temporary, 0600) || !@rename($temporary, $path)) {
                throw new RuntimeException('Pengaturan spreadsheet belum dapat disimpan.');
            }
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    public function url(): string
    {
        return 'https://docs.google.com/spreadsheets/d/' . $this->settings['spreadsheet_id'] . '/edit#gid=' . (int)$this->settings['sheet_id'];
    }

    public function metadata(): array
    {
        return $this->api('GET', '?fields=spreadsheetId,properties(title),sheets(properties,developerMetadata)');
    }

    public function metadataForSpreadsheet(string $spreadsheetId): array
    {
        $this->validateSpreadsheetId($spreadsheetId);
        return $this->apiForSpreadsheet($spreadsheetId, 'GET', '?fields=spreadsheetId,properties(title),sheets(properties(sheetId,title,sheetType,gridProperties(rowCount,columnCount,frozenRowCount,frozenColumnCount)),developerMetadata,merges)');
    }

    public function values(string $range): array
    {
        return $this->api('GET', '/values/' . rawurlencode($range) . '?valueRenderOption=FORMULA');
    }

    public function valuesForSpreadsheet(string $spreadsheetId, string $range): array
    {
        $this->validateSpreadsheetId($spreadsheetId);
        return $this->apiForSpreadsheet($spreadsheetId, 'GET', '/values/' . rawurlencode($range) . '?valueRenderOption=FORMULA');
    }

    public function batch(array $requests): array
    {
        return $this->api('POST', ':batchUpdate', ['requests' => $requests]);
    }

    public function batchForSpreadsheet(string $spreadsheetId, array $requests): array
    {
        $this->validateSpreadsheetId($spreadsheetId);
        return $this->apiForSpreadsheet($spreadsheetId, 'POST', ':batchUpdate', ['requests' => $requests]);
    }

    public function sheetId(): int { return (int)$this->settings['sheet_id']; }

    public function serviceAccountEmail(): string
    {
        return (string)$this->serviceAccountCredentials()['client_email'];
    }

    public function sync(callable $snapshotFactory): array
    {
        $lockPath = $this->settings['lock_file'] ?? '/var/lib/finance-secrets/runtime/product-sheet.lock';
        $lock = @fopen($lockPath, 'c');
        if (!$lock) throw new RuntimeException('Folder runtime spreadsheet belum dapat ditulis.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Pembaruan spreadsheet sedang berjalan. Tunggu hingga selesai.');
        }
        try {
            $metadata = $this->metadata();
            $target = null;
            foreach ($metadata['sheets'] ?? [] as $sheet) {
                if ((int)$sheet['properties']['sheetId'] === $this->sheetId()) $target = $sheet;
            }
            if (!$target || ($target['properties']['sheetType'] ?? '') !== 'GRID') {
                throw new RuntimeException('Tab tujuan spreadsheet tidak ditemukan. Tidak ada tab yang diubah.');
            }
            $marker = null;
            foreach ($target['developerMetadata'] ?? [] as $entry) {
                if (($entry['metadataKey'] ?? '') === 'finance_product_snapshot') $marker = $entry;
            }
            if ($marker === null) {
                $range = "'" . str_replace("'", "''", $target['properties']['title']) . "'";
                if (!empty($this->values($range)['values'])) {
                    throw new RuntimeException('Tab tujuan berisi data yang bukan milik ekspor produk. Pengiriman dibatalkan agar data manual tidak tertimpa.');
                }
            }
            $snapshot = $snapshotFactory();
            $requests = $this->buildRequests($target, $marker, $snapshot);
            $this->batch($requests);
            return ['url' => $this->url(), 'sheet_title' => $target['properties']['title'],
                'count' => $snapshot['count'], 'active_count' => $snapshot['active_count'],
                'inactive_count' => $snapshot['inactive_count'], 'captured_at' => $snapshot['captured_at']];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function buildRequests(array $target, ?array $marker, array $snapshot): array
    {
        $rowCount = count($snapshot['rows'] ?? []) + 1;
        $columnCount = count($snapshot['headers'] ?? []);
        if ($rowCount <= 1 || $rowCount > 10001 || $columnCount < 1) {
            throw new RuntimeException('Data ekspor kosong atau terlalu besar. Spreadsheet tidak diubah.');
        }
        $old = $marker ? json_decode((string)$marker['metadataValue'], true) : null;
        if ($marker && (!is_array($old) || ($old['version'] ?? 0) !== 1
            || (int)($old['rows'] ?? 0) < 1 || (int)($old['columns'] ?? 0) < 1)) {
            throw new RuntimeException('Penanda tab Data Sistem tidak valid. Tidak ada data yang ditimpa.');
        }
        $sheetId = $this->sheetId();
        $endRow = max($rowCount, (int)($old['rows'] ?? 0));
        $endColumn = max($columnCount, (int)($old['columns'] ?? 0));
        if ($endRow > 10001 || $endColumn > 200) throw new RuntimeException('Ukuran tab ekspor tidak valid.');
        $dataRange = ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => $rowCount, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount];
        $cells = [];
        foreach (array_merge([$snapshot['headers']], $snapshot['rows']) as $row) {
            if (count($row) !== $columnCount) throw new RuntimeException('Jumlah kolom ekspor tidak konsisten.');
            $values = [];
            foreach ($row as $value) {
                // Explicit stringValue prevents product text beginning with '=' from becoming a formula.
                if (is_bool($value)) $cell = ['boolValue' => $value];
                elseif (is_int($value) || is_float($value)) $cell = ['numberValue' => $value];
                else $cell = ['stringValue' => (string)$value];
                $values[] = ['userEnteredValue' => $cell];
            }
            $cells[] = ['values' => $values];
        }
        $cells[0]['values'][0]['note'] = 'DATA SISTEM: diperbarui dari Finance. Olah manual di tab lain. Snapshot WIB: ' . $snapshot['captured_at']
            . '. Semua produk aktif/nonaktif. HPP mengikuti Master Product termasuk biaya variabel. Profit bukan laba bersih usaha; profit online belum dikurangi fee, promo, atau pajak.';
        $markerValue = json_encode(['version' => 1, 'rows' => $rowCount, 'columns' => $columnCount, 'captured_at' => $snapshot['captured_at']], JSON_THROW_ON_ERROR);
        $requests = [
            ['updateSheetProperties' => ['properties' => ['sheetId' => $sheetId, 'gridProperties' => [
                'rowCount' => max($endRow, (int)$target['properties']['gridProperties']['rowCount']),
                'columnCount' => max($endColumn, (int)$target['properties']['gridProperties']['columnCount']),
                'frozenRowCount' => 1, 'frozenColumnCount' => min(5, $columnCount)]], 'fields' => 'gridProperties(rowCount,columnCount,frozenRowCount,frozenColumnCount)']],
            ['updateCells' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => $endRow,
                'startColumnIndex' => 0, 'endColumnIndex' => $endColumn], 'rows' => $cells, 'fields' => 'userEnteredValue,note']],
            ['repeatCell' => ['range' => $dataRange, 'cell' => ['userEnteredFormat' => ['textFormat' => ['fontSize' => 10], 'verticalAlignment' => 'MIDDLE', 'wrapStrategy' => 'CLIP']],
                'fields' => 'userEnteredFormat(textFormat,verticalAlignment,wrapStrategy)']],
            ['repeatCell' => ['range' => array_merge($dataRange, ['endRowIndex' => 1]), 'cell' => ['userEnteredFormat' => [
                'backgroundColor' => ['red' => 0.55, 'green' => 0.06, 'blue' => 0.12],
                'textFormat' => ['bold' => true, 'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1]], 'wrapStrategy' => 'WRAP']], 'fields' => 'userEnteredFormat']],
            ['setBasicFilter' => ['filter' => ['range' => $dataRange]]],
            ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 0, 'endIndex' => $columnCount],
                'properties' => ['pixelSize' => 155], 'fields' => 'pixelSize']],
            ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 0, 'endIndex' => 1],
                'properties' => ['pixelSize' => 60], 'fields' => 'pixelSize']],
        ];
        foreach ($snapshot['columns'] as $index => [$key, $label, $type]) {
            if (in_array($type, ['money', 'percent', 'integer'], true)) {
                $pattern = $type === 'percent' ? '0.00"%"' : ($type === 'integer' ? '0' : '#,##0.00');
                $requests[] = ['repeatCell' => ['range' => array_merge($dataRange, ['startRowIndex' => 1, 'startColumnIndex' => $index, 'endColumnIndex' => $index + 1]),
                    'cell' => ['userEnteredFormat' => ['numberFormat' => ['type' => 'NUMBER', 'pattern' => $pattern]]], 'fields' => 'userEnteredFormat.numberFormat']];
            }
            if (in_array($key, ['product_name', 'description', 'photo_url'], true)) {
                $requests[] = ['updateDimensionProperties' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => $index, 'endIndex' => $index + 1],
                    'properties' => ['pixelSize' => $key === 'product_name' ? 270 : 320], 'fields' => 'pixelSize']];
            }
        }
        if ($marker) {
            $requests[] = ['updateDeveloperMetadata' => ['dataFilters' => [['developerMetadataLookup' => ['metadataId' => $marker['metadataId']]]],
                'developerMetadata' => ['metadataValue' => $markerValue], 'fields' => 'metadataValue']];
        } else {
            $requests[] = ['createDeveloperMetadata' => ['developerMetadata' => ['metadataKey' => 'finance_product_snapshot',
                'metadataValue' => $markerValue, 'visibility' => 'DOCUMENT', 'location' => ['sheetId' => $sheetId]]]];
        }
        return $requests;
    }

    protected function api(string $method, string $path, ?array $payload = null): array
    {
        return $this->apiForSpreadsheet((string)$this->settings['spreadsheet_id'], $method, $path, $payload);
    }

    private function apiForSpreadsheet(string $spreadsheetId, string $method, string $path, ?array $payload = null): array
    {
        $headers = ['Authorization: Bearer ' . $this->accessToken()];
        if ($payload !== null) $headers[] = 'Content-Type: application/json';
        return $this->http($method, self::API_URL . $spreadsheetId . $path, $headers,
            $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function validateSpreadsheetId(string $spreadsheetId): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,150}$/D', $spreadsheetId)) {
            throw new RuntimeException('ID spreadsheet tidak valid.');
        }
    }

    private function accessToken(): string
    {
        if ($this->token) return $this->token;
        $credentials = $this->serviceAccountCredentials();
        $encode = static function (string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); };
        $now = time();
        $jwt = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)) . '.'
            . $encode(json_encode(['iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/spreadsheets',
                'aud' => self::TOKEN_URL, 'iat' => $now, 'exp' => $now + 3600], JSON_THROW_ON_ERROR));
        $key = openssl_pkey_get_private($credentials['private_key']);
        if (!$key || !openssl_sign($jwt, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Kunci service account Google tidak dapat digunakan.');
        }
        $response = $this->http('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . $encode($signature)]));
        if (empty($response['access_token'])) throw new RuntimeException('Google tidak mengembalikan token akses.');
        $this->token = (string)$response['access_token'];
        return $this->token;
    }

    private function serviceAccountCredentials(): array
    {
        $path = $this->settings['credentials_file'] ?? '/var/lib/finance-secrets/google-sheets.json';
        $real = @realpath($path);
        $root = realpath(dirname(__DIR__, 2));
        if (!$real || !@is_file($real) || !@is_readable($real) || ($root && strpos($real, $root . DIRECTORY_SEPARATOR) === 0)) {
            throw new RuntimeException('Kredensial Google harus tersedia di folder privat di luar website.');
        }
        $credentials = json_decode((string)file_get_contents($real), true);
        if (($credentials['type'] ?? '') !== 'service_account' || empty($credentials['private_key'])
            || !filter_var($credentials['client_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Format kredensial service account Google tidak valid.');
        }
        return $credentials;
    }

    private function http(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 90,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_errno($curl);
        curl_close($curl);
        if ($raw === false || $error) {
            throw new RuntimeException('Koneksi Google terputus. Periksa tab Data Sistem sebelum mengulang; hasil pengiriman belum pasti.');
        }
        $result = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            if ($status === 403) throw new RuntimeException('Google menolak akses. Aktifkan Google Sheets API dan bagikan spreadsheet sebagai Editor kepada service account.');
            if ($status === 404) throw new RuntimeException('Spreadsheet tidak ditemukan atau belum dibagikan ke service account.');
            if ($status === 429) throw new RuntimeException('Batas permintaan Google tercapai. Tunggu sebentar sebelum mencoba kembali.');
            throw new RuntimeException('Permintaan Google gagal (HTTP ' . $status . '). Periksa konfigurasi koneksi.');
        }
        if (!is_array($result)) throw new RuntimeException('Respons Google tidak valid.');
        return $result;
    }
}
