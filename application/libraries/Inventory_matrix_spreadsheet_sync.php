<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_matrix_spreadsheet_sync
{
    private const MARKER = 'finance_inventory_matrix_snapshot';

    public function discover(string $spreadsheetId): array
    {
        $api = $this->client($spreadsheetId);
        $meta = $api->metadataForSpreadsheet($spreadsheetId);
        $tabs = [];
        foreach ($meta['sheets'] ?? [] as $sheet) {
            $props = $sheet['properties'] ?? [];
            if (($props['sheetType'] ?? '') === 'GRID') $tabs[] = ['id' => (int)$props['sheetId'], 'title' => (string)$props['title']];
        }
        return ['spreadsheet_id' => $spreadsheetId, 'title' => (string)($meta['properties']['title'] ?? ''), 'tabs' => $tabs];
    }

    public function sync(array $settings, string $month, array $payloads): array
    {
        $settings = Inventory_matrix_spreadsheet_config::normalize($settings);
        $api = $this->client($settings['spreadsheet_id']);
        $lock = @fopen('/var/lib/finance-secrets/runtime/inventory-matrix-spreadsheet.lock', 'c');
        if (!$lock) throw new RuntimeException('Folder runtime spreadsheet belum dapat ditulis.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Sinkronisasi spreadsheet sedang berjalan.'); }
        try {
            $meta = $api->metadataForSpreadsheet($settings['spreadsheet_id']);
            $sheets = $this->sheetMap($meta);
            $targets = [];
            $targets[] = ['id' => $settings['warehouse_sheet_id'], 'rows' => $payloads['warehouse'] ?? [], 'label' => 'Gudang Pusat'];
            foreach ($settings['division_sheets'] as $divisionId => $sheetId) {
                if (!isset($payloads['divisions'][$divisionId])) continue;
                $targets[] = ['id' => $sheetId, 'rows' => $payloads['divisions'][$divisionId], 'label' => (string)($payloads['division_names'][$divisionId] ?? ('Divisi ' . $divisionId))];
            }
            $seen = [];
            foreach ($targets as $target) {
                if (isset($seen[$target['id']])) throw new RuntimeException('Satu tab dipetakan ke lebih dari satu sumber. Atur tab berbeda untuk gudang dan tiap divisi.');
                $seen[$target['id']] = true;
                if (!isset($sheets[$target['id']])) throw new RuntimeException('Ada tab tujuan yang tidak ditemukan. Muat ulang daftar tab dan simpan pengaturan.');
                $target['title'] = $sheets[$target['id']]['properties']['title'];
                $targets[array_search($target['id'], array_column($targets, 'id'), true)] = $target;
            }
            // Provision missing monthly archive tabs before touching any snapshot data.
            $addRequests = [];
            foreach ($targets as &$target) {
                $archiveTitle = $this->archiveTitle($target['title'], $month);
                $target['archive_title'] = $archiveTitle;
                $archive = null;
                foreach ($sheets as $sheet) if (($sheet['properties']['title'] ?? '') === $archiveTitle) $archive = $sheet;
                if ($archive) $target['archive_id'] = (int)$archive['properties']['sheetId'];
                else $addRequests[] = ['addSheet' => ['properties' => ['title' => $archiveTitle, 'gridProperties' => ['rowCount' => 1000, 'columnCount' => 200, 'frozenRowCount' => 2, 'frozenColumnCount' => 18]]]];
            }
            unset($target);
            foreach ($targets as $target) {
                $this->assertWritable($api, $settings['spreadsheet_id'], $sheets[$target['id']]);
                if (isset($target['archive_id'])) $this->assertWritable($api, $settings['spreadsheet_id'], $sheets[$target['archive_id']]);
            }
            if ($addRequests) {
                $api->batchForSpreadsheet($settings['spreadsheet_id'], $addRequests);
                $meta = $api->metadataForSpreadsheet($settings['spreadsheet_id']);
                $sheets = $this->sheetMap($meta);
                foreach ($targets as &$target) {
                    $archiveSheet = $this->sheetByTitle($sheets, $target['archive_title']);
                    if (!$archiveSheet) throw new RuntimeException('Tab arsip bulanan gagal dibuat: ' . $target['archive_title']);
                    $target['archive_id'] = (int)$archiveSheet['properties']['sheetId'];
                }
                unset($target);
            }
            $result = [];
            foreach ($targets as $target) {
                $table = Inventory_matrix_spreadsheet_export::sheet($month, $target['rows']);
                foreach ([['id' => $target['id'], 'sheet' => $sheets[$target['id']]], ['id' => $target['archive_id'], 'sheet' => $sheets[$target['archive_id']]]] as $destination) {
                    try {
                        $requests = $this->writeRequests($api, $settings['spreadsheet_id'], $destination['sheet'], $table, $month);
                        $this->sendRequestBatches($api, $settings['spreadsheet_id'], $requests);
                        $this->verifyWritten($api, $settings['spreadsheet_id'], $destination['sheet'], $table);
                    } catch (Throwable $e) {
                        throw new RuntimeException('Gagal memperbarui tab ' . $destination['sheet']['properties']['title'] . '. Jika tab lain sudah selesai, pembaruan aman diulang. ' . $e->getMessage(), 0, $e);
                    }
                }
                $todayUrl = $this->todayUrl($settings['spreadsheet_id'], (int)$target['id'], $month, $table['dates']);
                $result[] = ['tab' => $target['title'], 'rows' => $table['row_count'], 'url' => $todayUrl];
            }
            return ['spreadsheet_title' => (string)($meta['properties']['title'] ?? ''), 'month' => $month, 'tabs' => $result];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function writeRequests(Product_sheet_client $api, string $spreadsheetId, array $sheet, array $table, string $month): array
    {
        $id = (int)$sheet['properties']['sheetId'];
        $marker = null;
        foreach ($sheet['developerMetadata'] ?? [] as $entry) if (($entry['metadataKey'] ?? '') === self::MARKER) $marker = $entry;
        $range = "'" . str_replace("'", "''", (string)$sheet['properties']['title']) . "'";
        if (!$marker && !empty($api->valuesForSpreadsheet($spreadsheetId, $range)['values'])) throw new RuntimeException('Tab ' . $sheet['properties']['title'] . ' sudah berisi data tanpa penanda milik modul. Tidak ada isi tab yang ditimpa.');
        $previous = $marker ? json_decode((string)$marker['metadataValue'], true) : [];
        if ($marker && (!is_array($previous) || ($previous['version'] ?? null) !== 1)) throw new RuntimeException('Penanda ekspor pada tab ' . $sheet['properties']['title'] . ' tidak valid.');
        $rowCount = count($table['rows']) + 2;
        $columnCount = $table['columns'];
        if ($rowCount > 5002 || $columnCount > 173) throw new RuntimeException('Ukuran ekspor melebihi batas aman.');
        $oldRows = max($rowCount, (int)($previous['rows'] ?? 0));
        $oldCols = max($columnCount, (int)($previous['columns'] ?? 0));
        $rows = array_merge($table['headers'], $table['rows']);
        $idNote = 'Snapshot Finance ' . $month . '. Awal/In/Out/Adj/Akhir per hari. Dibuat: ' . date('Y-m-d H:i:s') . ' WIB. Data historis dijaga pada tab arsip bulan.';
        $unmergeRequests = [];
        foreach ($sheet['merges'] ?? [] as $merge) $unmergeRequests[] = ['unmergeCells' => ['range' => $merge]];
        $writingValue = json_encode(['version' => 1, 'state' => 'writing', 'month' => $month, 'rows' => $rowCount, 'columns' => $columnCount], JSON_THROW_ON_ERROR);
        $readyValue = json_encode(['version' => 1, 'state' => 'ready', 'month' => $month, 'rows' => $rowCount, 'columns' => $columnCount], JSON_THROW_ON_ERROR);
        $req = $unmergeRequests;
        if ($marker) $req[] = ['updateDeveloperMetadata' => ['dataFilters' => [['developerMetadataLookup' => ['metadataId' => (int)$marker['metadataId']]]], 'developerMetadata' => ['metadataValue' => $writingValue], 'fields' => 'metadataValue']];
        else $req[] = ['createDeveloperMetadata' => ['developerMetadata' => ['metadataKey' => self::MARKER, 'metadataValue' => $writingValue, 'visibility' => 'DOCUMENT', 'location' => ['sheetId' => $id]]]];
        $req[] = ['updateSheetProperties' => ['properties' => ['sheetId' => $id, 'gridProperties' => ['rowCount' => max($oldRows, (int)($sheet['properties']['gridProperties']['rowCount'] ?? 1000)), 'columnCount' => max($oldCols, (int)($sheet['properties']['gridProperties']['columnCount'] ?? 26)), 'frozenRowCount' => 2, 'frozenColumnCount' => 18]], 'fields' => 'gridProperties(rowCount,columnCount,frozenRowCount,frozenColumnCount)']];
        foreach (array_chunk($rows, 30) as $chunkIndex => $chunk) {
            // array_chunk reindexes its outer result; derive the sheet row from
            // the chunk number, not the outer array key as a row offset.
            $start = $chunkIndex * 30;
            $cellRows = [];
            foreach ($chunk as $row) {
                $cells = [];
                foreach ($row as $value) $cells[] = ['userEnteredValue' => is_int($value) || is_float($value)
                    ? ['numberValue' => $value] : ['stringValue' => (string)$value]];
                $cellRows[] = ['values' => $cells];
            }
            if ($start === 0) $cellRows[0]['values'][0]['note'] = $idNote;
            $req[] = ['updateCells' => ['range' => ['sheetId' => $id, 'startRowIndex' => $start, 'endRowIndex' => $start + count($cellRows), 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount], 'rows' => $cellRows, 'fields' => 'userEnteredValue,note']];
        }
        if ($oldRows > $rowCount) $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => $rowCount, 'endRowIndex' => $oldRows, 'startColumnIndex' => 0, 'endColumnIndex' => $oldCols], 'cell' => ['userEnteredValue' => null, 'note' => null], 'fields' => 'userEnteredValue,note']];
        if ($oldCols > $columnCount) $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => 0, 'endRowIndex' => $rowCount, 'startColumnIndex' => $columnCount, 'endColumnIndex' => $oldCols], 'cell' => ['userEnteredValue' => null, 'note' => null], 'fields' => 'userEnteredValue,note']];
        $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => 0, 'endRowIndex' => 2, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount], 'cell' => ['userEnteredFormat' => ['backgroundColor' => ['red' => .48, 'green' => .03, 'blue' => .1], 'textFormat' => ['bold' => true, 'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1]], 'horizontalAlignment' => 'CENTER', 'verticalAlignment' => 'MIDDLE', 'wrapStrategy' => 'WRAP']], 'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment,wrapStrategy)']];
        $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => 2, 'endRowIndex' => $rowCount, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount], 'cell' => ['userEnteredFormat' => ['backgroundColor' => null]], 'fields' => 'userEnteredFormat.backgroundColor']];
        $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => 2, 'endRowIndex' => $rowCount, 'startColumnIndex' => 17, 'endColumnIndex' => $columnCount], 'cell' => ['userEnteredFormat' => ['numberFormat' => ['type' => 'NUMBER', 'pattern' => '#,##0.####'], 'horizontalAlignment' => 'RIGHT']], 'fields' => 'userEnteredFormat.numberFormat,userEnteredFormat.horizontalAlignment']];
        foreach ($table['rows'] as $rowIndex => $_row) if ($rowIndex % 2 === 1) $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => $rowIndex + 2, 'endRowIndex' => $rowIndex + 3, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount], 'cell' => ['userEnteredFormat' => ['backgroundColor' => ['red' => .957, 'green' => .965, 'blue' => .973]]], 'fields' => 'userEnteredFormat.backgroundColor']];
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
        foreach ($table['dates'] as $dayIndex => $_date) {
            $start = 18 + ($dayIndex * 5);
            $req[] = ['updateBorders' => ['range' => ['sheetId' => $id, 'startRowIndex' => 0, 'endRowIndex' => $rowCount, 'startColumnIndex' => $start, 'endColumnIndex' => $start + 1], 'left' => ['style' => 'SOLID_MEDIUM', 'color' => ['red' => .48, 'green' => .03, 'blue' => .1]], 'right' => ['style' => 'NONE']]];
            $end = $start + 4;
            $req[] = ['updateBorders' => ['range' => ['sheetId' => $id, 'startRowIndex' => 0, 'endRowIndex' => $rowCount, 'startColumnIndex' => $end, 'endColumnIndex' => $end + 1], 'right' => ['style' => 'NONE']]];
            if ($_date === $today) $req[] = ['repeatCell' => ['range' => ['sheetId' => $id, 'startRowIndex' => 2, 'endRowIndex' => $rowCount, 'startColumnIndex' => $start, 'endColumnIndex' => $start + 5], 'cell' => ['userEnteredFormat' => ['backgroundColor' => ['red' => .965, 'green' => .765, 'blue' => .29], 'textFormat' => ['foregroundColor' => ['red' => .22, 'green' => .14, 'blue' => .02]]]], 'fields' => 'userEnteredFormat.backgroundColor,userEnteredFormat.textFormat.foregroundColor']];
        }
        $zeroOrNegativeStart = null;
        foreach ($table['rows'] as $rowIndex => $row) {
            $lastClosing = end($row);
            $isZeroOrNegative = is_numeric($lastClosing) && (float)$lastClosing <= 0;
            if ($isZeroOrNegative && $zeroOrNegativeStart === null) $zeroOrNegativeStart = $rowIndex;
            if (!$isZeroOrNegative && $zeroOrNegativeStart !== null) {
                $req[] = $this->zeroStockRowFormat($id, $zeroOrNegativeStart, $rowIndex, $columnCount);
                $zeroOrNegativeStart = null;
            }
        }
        if ($zeroOrNegativeStart !== null) $req[] = $this->zeroStockRowFormat($id, $zeroOrNegativeStart, count($table['rows']), $columnCount);
        $req[] = ['updateDimensionProperties' => ['range' => ['sheetId' => $id, 'dimension' => 'ROWS', 'startIndex' => 0, 'endIndex' => 2], 'properties' => ['pixelSize' => 40], 'fields' => 'pixelSize']];
        $req = array_merge($req, $this->dateMergeRequests($id, $table['dates']));
        if ($marker) $req[] = ['updateDeveloperMetadata' => ['dataFilters' => [['developerMetadataLookup' => ['metadataId' => (int)$marker['metadataId']]]], 'developerMetadata' => ['metadataValue' => $readyValue], 'fields' => 'metadataValue']];
        else $req[] = ['updateDeveloperMetadata' => ['dataFilters' => [['developerMetadataLookup' => ['metadataKey' => self::MARKER, 'locationType' => 'SHEET', 'metadataLocation' => ['sheetId' => $id]]]], 'developerMetadata' => ['metadataValue' => $readyValue], 'fields' => 'metadataValue']];
        return $req;
    }

    private function dateMergeRequests(int $sheetId, array $dates): array
    {
        $requests = [];
        for ($column = 0; $column < 18; $column++) {
            $requests[] = ['mergeCells' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 2, 'startColumnIndex' => $column, 'endColumnIndex' => $column + 1], 'mergeType' => 'MERGE_ALL']];
        }
        for ($index = 0; $index < count($dates); $index++) {
            $column = 18 + ($index * 5);
            $requests[] = ['mergeCells' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1, 'startColumnIndex' => $column, 'endColumnIndex' => $column + 5], 'mergeType' => 'MERGE_ALL']];
        }
        return $requests;
    }

    private function zeroStockRowFormat(int $sheetId, int $startRow, int $endRow, int $columnCount): array
    {
        return ['repeatCell' => ['range' => ['sheetId' => $sheetId, 'startRowIndex' => $startRow + 2, 'endRowIndex' => $endRow + 2, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount], 'cell' => ['userEnteredFormat' => ['backgroundColor' => ['red' => .78, 'green' => .12, 'blue' => .12], 'textFormat' => ['bold' => true, 'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1]]]], 'fields' => 'userEnteredFormat.backgroundColor,userEnteredFormat.textFormat']];
    }

    private function sendRequestBatches(Product_sheet_client $api, string $spreadsheetId, array $requests): void
    {
        $batch = []; $bytes = 0;
        foreach ($requests as $request) {
            $encoded = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $size = strlen($encoded);
            if ($size > 900000) throw new RuntimeException('Satu bagian permintaan spreadsheet terlalu besar (' . $size . ' byte).');
            if ($batch && (count($batch) >= 80 || $bytes + $size > 900000)) {
                $api->batchForSpreadsheet($spreadsheetId, $batch); $batch = []; $bytes = 0;
            }
            $batch[] = $request; $bytes += $size;
        }
        if ($batch) $api->batchForSpreadsheet($spreadsheetId, $batch);
    }

    private function verifyWritten(Product_sheet_client $api, string $spreadsheetId, array $sheet, array $table): void
    {
        $title = str_replace("'", "''", (string)$sheet['properties']['title']);
        $headerRange = "'" . $title . "'!A1:S1";
        $header = $api->valuesForSpreadsheet($spreadsheetId, $headerRange)['values'][0] ?? [];
        $expectedHeader = array_slice($table['headers'][0], 0, 19);
        if (array_slice($header, 0, 19) !== $expectedHeader) {
            throw new RuntimeException('Verifikasi header setelah tulis gagal pada tab ' . $sheet['properties']['title'] . '.');
        }
        if ($table['row_count'] <= 0) return;
        $lastRow = $table['row_count'] + 2;
        $body = $api->valuesForSpreadsheet($spreadsheetId, "'" . $title . "'!B3:B" . $lastRow)['values'] ?? [];
        if (count($body) !== $table['row_count']) {
            throw new RuntimeException('Verifikasi isi gagal pada tab ' . $sheet['properties']['title'] . ': tertulis ' . count($body) . ' dari ' . $table['row_count'] . ' baris. Sinkronisasi aman diulang.');
        }
        foreach ($body as $index => $row) if (trim((string)($row[0] ?? '')) === '') {
            throw new RuntimeException('Verifikasi isi menemukan baris kosong ke-' . ($index + 1) . ' pada tab ' . $sheet['properties']['title'] . '. Sinkronisasi aman diulang.');
        }
    }

    private function todayUrl(string $spreadsheetId, int $sheetId, string $month, array $dates): string
    {
        $base = 'https://docs.google.com/spreadsheets/d/' . rawurlencode($spreadsheetId) . '/edit#gid=' . $sheetId;
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
        if ($month !== substr($today, 0, 7)) return $base;
        $index = array_search($today, $dates, true);
        if ($index === false) return $base;
        $number = 19 + ((int)$index * 5); $letters = '';
        while ($number > 0) { $number--; $letters = chr(65 + ($number % 26)) . $letters; $number = intdiv($number, 26); }
        return $base . '&range=' . $letters . '1';
    }

    private function assertWritable(Product_sheet_client $api, string $spreadsheetId, array $sheet): void
    {
        foreach ($sheet['developerMetadata'] ?? [] as $entry) if (($entry['metadataKey'] ?? '') === self::MARKER) return;
        $range = "'" . str_replace("'", "''", (string)$sheet['properties']['title']) . "'";
        if (!empty($api->valuesForSpreadsheet($spreadsheetId, $range)['values'])) throw new RuntimeException('Tab ' . $sheet['properties']['title'] . ' sudah berisi data tanpa penanda milik modul. Tidak ada isi tab yang ditimpa.');
    }

    private function client(string $spreadsheetId): Product_sheet_client
    {
        return new Product_sheet_client(['spreadsheet_id' => $spreadsheetId, 'sheet_id' => 0]);
    }
    private function sheetMap(array $meta): array
    {
        $map = []; foreach ($meta['sheets'] ?? [] as $sheet) $map[(int)($sheet['properties']['sheetId'] ?? -1)] = $sheet; return $map;
    }
    private function sheetByTitle(array $sheets, string $title): ?array
    {
        foreach ($sheets as $sheet) if (($sheet['properties']['title'] ?? '') === $title) return $sheet; return null;
    }
    private function archiveTitle(string $base, string $month): string
    {
        $suffix = '_' . $month; return mb_substr($base, 0, 100 - mb_strlen($suffix)) . $suffix;
    }
}
