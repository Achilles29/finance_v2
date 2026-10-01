<?php
define('BASEPATH', dirname(__DIR__, 2));
require_once BASEPATH . '/application/libraries/Inventory_matrix_spreadsheet_export.php';
require_once BASEPATH . '/application/libraries/Product_sheet_client.php';
require_once BASEPATH . '/application/libraries/Inventory_matrix_spreadsheet_sync.php';

final class InventoryMatrixSheetChunkClient extends Product_sheet_client
{
    public array $readValues = [];

    public function __construct()
    {
        parent::__construct(['spreadsheet_id' => str_repeat('a', 24), 'sheet_id' => 1]);
    }

    public function valuesForSpreadsheet(string $spreadsheetId, string $range): array
    {
        return ['values' => $this->readValues[$range] ?? []];
    }
}

$sourceRows = [];
for ($i = 0; $i < 65; $i++) {
    $sourceRows[] = ['_domain' => 'BAHAN BAKU', '_category' => 'Category', '_name' => 'Material ' . $i];
}
$table = Inventory_matrix_spreadsheet_export::sheet('2026-09', $sourceRows);
$sync = new Inventory_matrix_spreadsheet_sync();
$client = new InventoryMatrixSheetChunkClient();
$method = new ReflectionMethod($sync, 'writeRequests');
$method->setAccessible(true);
$requests = $method->invoke($sync, $client, str_repeat('a', 24), [
    'properties' => ['sheetId' => 1, 'title' => 'Test', 'gridProperties' => ['rowCount' => 1000, 'columnCount' => 200]],
    'developerMetadata' => [],
    'merges' => [],
], $table, '2026-09');

$updates = array_values(array_filter($requests, static function (array $request): bool {
    return isset($request['updateCells']);
}));
$starts = array_map(static function (array $request): int {
    return (int)$request['updateCells']['range']['startRowIndex'];
}, $updates);
$ends = array_map(static function (array $request): int {
    return (int)$request['updateCells']['range']['endRowIndex'];
}, $updates);

if ($starts !== [0, 30, 60] || $ends !== [30, 60, 67]) {
    fwrite(STDERR, 'FAIL: batch row offsets were ' . json_encode([$starts, $ends]) . PHP_EOL);
    exit(1);
}

$stripeRequests = array_values(array_filter($requests, static function (array $request): bool {
    return isset($request['repeatCell']['cell']['userEnteredFormat']['backgroundColor'])
        && $request['repeatCell']['cell']['userEnteredFormat']['backgroundColor'] === ['red' => .957, 'green' => .965, 'blue' => .973];
}));
$openingBorders = array_values(array_filter($requests, static function (array $request): bool {
    $border = $request['updateBorders'] ?? null;
    $startColumn = (int)($border['range']['startColumnIndex'] ?? -1);
    return $border && $startColumn >= 18 && (($startColumn - 18) % 5) === 0
        && ($border['left']['style'] ?? null) === 'SOLID_MEDIUM'
        && ($border['right']['style'] ?? null) === 'NONE';
}));
$closingRightClear = array_values(array_filter($requests, static function (array $request): bool {
    $border = $request['updateBorders'] ?? null;
    $startColumn = (int)($border['range']['startColumnIndex'] ?? -1);
    return $border && $startColumn >= 22 && (($startColumn - 22) % 5) === 0
        && ($border['right']['style'] ?? null) === 'NONE';
}));
$todayCells = array_values(array_filter($requests, static function (array $request): bool {
    $format = $request['repeatCell']['cell']['userEnteredFormat'] ?? [];
    return ($format['backgroundColor'] ?? null) === ['red' => .965, 'green' => .765, 'blue' => .29];
}));
$zeroStockRows = array_values(array_filter($requests, static function (array $request): bool {
    $format = $request['repeatCell']['cell']['userEnteredFormat'] ?? [];
    return ($format['backgroundColor'] ?? null) === ['red' => .78, 'green' => .12, 'blue' => .12]
        && ($format['textFormat']['bold'] ?? false) === true;
}));
if (count($stripeRequests) !== 32 || count($openingBorders) !== 30 || count($closingRightClear) !== 30 || count($todayCells) !== 1 || count($zeroStockRows) !== 1) {
    fwrite(STDERR, 'FAIL: expected stripes, bold left border per date, today highlight, and zero-stock row formatting.' . PHP_EOL);
    exit(1);
}

$client->readValues["'Test'!A1:S1"] = [array_slice($table['headers'][0], 0, 19)];
$client->readValues["'Test'!B3:B67"] = array_fill(0, 65, ['BAHAN BAKU']);
$verify = new ReflectionMethod($sync, 'verifyWritten');
$verify->setAccessible(true);
$verify->invoke($sync, $client, str_repeat('a', 24), ['properties' => ['title' => 'Test']], $table);
$client->readValues["'Test'!B3:B67"] = array_fill(0, 31, ['BAHAN BAKU']);
$verificationCaught = false;
try {
    $verify->invoke($sync, $client, str_repeat('a', 24), ['properties' => ['title' => 'Test']], $table);
} catch (ReflectionException $e) {
    throw $e;
} catch (RuntimeException $e) {
    $verificationCaught = strpos($e->getMessage(), 'tertulis 31 dari 65 baris') !== false;
}
if (!$verificationCaught) {
    fwrite(STDERR, "FAIL: read-back verification accepted an incomplete write.\n");
    exit(1);
}

echo "PASS: correct batch offsets and read-back detection for incomplete writes.\n";
