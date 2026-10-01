<?php

declare(strict_types=1);

/**
 * DB/network/bootstrap/secret-free behavior smoke for dashboard FIFO value mismatch.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class DashboardMismatchFakeQuery
{
    public function result_array(): array
    {
        return [];
    }
}

final class DashboardMismatchFakeDb
{
    public bool $db_debug = true;

    public function table_exists($table): bool
    {
        return in_array((string)$table, [
            'inv_component_monthly_stock',
            'inv_division_monthly_stock',
        ], true);
    }

    public function protect_identifiers($table, $prefixSingle = false): string
    {
        return '`' . (string)$table . '`';
    }

    public function query($sql): DashboardMismatchFakeQuery
    {
        return new DashboardMismatchFakeQuery();
    }

    public function error(): array
    {
        return ['code' => 0, 'message' => ''];
    }
}

final class DashboardMismatchFakeProductionModel
{
    public array $rows = [];
    public array $lastFilters = [];
    public int $lastLimit = 0;

    public function component_reconcile_rows(array $filters, int $limit = 300): array
    {
        $this->lastFilters = $filters;
        $this->lastLimit = $limit;
        return ['rows' => array_slice($this->rows, 0, $limit)];
    }
}

final class DashboardMismatchFakePurchaseModel
{
    public array $rows = [];
    public array $lastArguments = [];

    public function list_division_material_stock_compare(...$arguments): array
    {
        $this->lastArguments = $arguments;
        return ['rows' => $this->rows];
    }
}

final class DashboardMismatchFakeLoader
{
    public function model($name): void
    {
    }
}

class MY_Controller
{
    public $db;
    public $load;
    public $Production_model;
    public $Purchase_model;

    public function __construct()
    {
    }
}

if (!function_exists('site_url')) {
    function site_url($uri = ''): string
    {
        return '/index.php/' . ltrim((string)$uri, '/');
    }
}

if (!function_exists('base_url')) {
    function base_url($uri = ''): string
    {
        return '/' . ltrim((string)$uri, '/');
    }
}

if (!function_exists('html_escape')) {
    function html_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('log_message')) {
    function log_message($level, $message): void
    {
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Dashboard.php';

$dashboardMismatchChecks = 0;
$dashboardMismatchFailures = [];

function dashboard_mismatch_check(bool $condition, string $message): void
{
    global $dashboardMismatchChecks, $dashboardMismatchFailures;
    $dashboardMismatchChecks++;
    if (!$condition) {
        $dashboardMismatchFailures[] = $message;
    }
}

function dashboard_mismatch_controller(array $componentRows, array $materialRows = []): array
{
    $reflection = new ReflectionClass(Dashboard::class);
    /** @var Dashboard $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->db = new DashboardMismatchFakeDb();
    $controller->load = new DashboardMismatchFakeLoader();
    $controller->Production_model = new DashboardMismatchFakeProductionModel();
    $controller->Production_model->rows = $componentRows;
    $controller->Purchase_model = new DashboardMismatchFakePurchaseModel();
    $controller->Purchase_model->rows = $materialRows;
    return [$controller, $reflection];
}

function dashboard_mismatch_summary(array $componentRows, array $materialRows = []): array
{
    [$controller, $reflection] = dashboard_mismatch_controller($componentRows, $materialRows);
    $method = $reflection->getMethod('dashboard_reconcile_mismatch_summary');
    $method->setAccessible(true);
    return [$method->invoke($controller), $controller];
}

function dashboard_mismatch_render(array $summary): string
{
    $reconcile_mismatch = $summary;
    $current_user = ['username' => 'Smoke'];
    ob_start();
    require dirname(__DIR__, 2) . '/application/views/dashboard/index.php';
    return (string)ob_get_clean();
}

function dashboard_mismatch_card(string $html, string $title): string
{
    $titlePosition = strpos($html, $title);
    if ($titlePosition === false) {
        return '';
    }
    $start = strrpos(substr($html, 0, $titlePosition), '<article');
    $end = strpos($html, '</article>', $titlePosition);
    if ($start === false || $end === false) {
        return '';
    }
    return substr($html, $start, $end + strlen('</article>') - $start);
}

$componentRows = [
    [
        'component_name' => 'Dalam Toleransi',
        'component_code' => 'TOL-1',
        'division_id' => 1,
        'division_name' => 'Kitchen',
        'location_type' => 'REGULER',
        'qty_is_match' => 1,
        'has_lot_value_mismatch' => 0,
        'monthly_lot_value_gap' => 1.00,
        'is_match' => 1,
    ],
    [
        'component_name' => 'Qty Saja',
        'component_code' => 'QTY-1',
        'division_id' => 1,
        'division_name' => 'Kitchen',
        'location_type' => 'REGULER',
        'qty_is_match' => 0,
        'has_lot_value_mismatch' => 0,
        'monthly_lot_value_gap' => 0,
        'is_match' => 0,
        'balance_qty' => 10,
        'daily_qty' => 10,
        'movement_qty' => 8,
        'lot_qty' => 10,
        'delta_balance_daily' => 0,
        'delta_balance_movement' => 2,
        'delta_daily_movement' => 2,
        'suspect_reason' => 'Movement berbeda',
    ],
    [
        'component_name' => 'Nilai Saja',
        'component_code' => 'VAL-1',
        'division_id' => 1,
        'division_name' => 'Kitchen',
        'location_type' => 'REGULER',
        'qty_is_match' => 1,
        'has_lot_value_mismatch' => 1,
        'monthly_lot_value_gap' => 1250.00,
        'is_match' => 0,
        'balance_qty' => 10,
        'daily_qty' => 10,
        'movement_qty' => 10,
        'lot_qty' => 10,
        'delta_balance_daily' => 0,
        'delta_balance_movement' => 0,
        'delta_daily_movement' => 0,
    ],
    [
        'component_name' => 'Qty dan Nilai',
        'component_code' => 'BOTH-1',
        'division_id' => 2,
        'division_name' => 'Bar',
        'location_type' => 'EVENT',
        'qty_is_match' => 0,
        'has_lot_value_mismatch' => 1,
        'monthly_lot_value_gap' => -500.00,
        'is_match' => 0,
        'balance_qty' => 5,
        'daily_qty' => 4,
        'movement_qty' => 5,
        'lot_qty' => 3,
        'delta_balance_daily' => 1,
        'delta_balance_movement' => 0,
        'delta_daily_movement' => -1,
        'suspect_reason' => 'Jumlah dan nilai berbeda',
    ],
];

$materialRows = [
    [
        'is_match' => 0,
        'division_id' => 7,
        'division_name' => 'Store',
        'destination_type' => 'REGULER',
        'destination_name' => 'Reguler',
        'material_name' => 'Tepung',
        'material_code' => 'MAT-1',
        'delta_balance_vs_movement' => 3.5,
        'suspect_reason' => 'Saldo berbeda',
    ],
];

[$summary, $controller] = dashboard_mismatch_summary($componentRows, $materialRows);
$component = $summary['component'];

dashboard_mismatch_check((int)$component['total'] === 3, 'total counts each mismatching component row once');
dashboard_mismatch_check((int)$component['qty_mismatch_count'] === 2, 'quantity breakdown includes qty-only and combined mismatch');
dashboard_mismatch_check((int)$component['value_mismatch_count'] === 2, 'value breakdown includes value-only and combined mismatch');
dashboard_mismatch_check(count($component['rows']) === 3, 'the exact-tolerance canonical match is excluded');
dashboard_mismatch_check($controller->Production_model->lastLimit === 2000, 'dashboard retains the prior component reconcile limit');
dashboard_mismatch_check(
    $controller->Purchase_model->lastArguments === [
        $summary['as_of_date'],
        '',
        null,
        2000,
        'ALL',
        true,
    ],
    'material reconcile keeps the prior limit and detailed diagnostics contract'
);

$rowsByCode = [];
foreach ($component['rows'] as $row) {
    $rowsByCode[(string)$row['code']] = $row;
}
dashboard_mismatch_check(!isset($rowsByCode['TOL-1']), 'gap exactly at model tolerance stays clear');
dashboard_mismatch_check(
    ($rowsByCode['VAL-1']['gap_type'] ?? '') === 'currency'
        && (float)($rowsByCode['VAL-1']['gap_value'] ?? 0) === 1250.00
        && ($rowsByCode['VAL-1']['reason'] ?? '') === 'Qty sama, nilai FIFO berbeda',
    'qty-match value mismatch uses the canonical nominal and required label'
);
dashboard_mismatch_check(
    ($rowsByCode['QTY-1']['gap_type'] ?? '') === 'qty'
        && abs((float)($rowsByCode['QTY-1']['gap'] ?? 0) - 2.0) < 0.0001,
    'quantity mismatch remains visible as a quantity gap'
);
dashboard_mismatch_check(
    ($rowsByCode['BOTH-1']['gap_type'] ?? '') === 'qty',
    'combined mismatch remains one quantity row while contributing to both breakdowns'
);

dashboard_mismatch_check(
    (int)($summary['material']['total'] ?? 0) === 1
        && ($summary['material']['rows'][0]['code'] ?? '') === 'MAT-1'
        && abs((float)($summary['material']['rows'][0]['gap'] ?? 0) - 3.5) < 0.0001,
    'material reconcile summary behavior is unchanged'
);

$html = dashboard_mismatch_render($summary);
$componentCard = dashboard_mismatch_card($html, 'Mismatch Component');
dashboard_mismatch_check($componentCard !== '', 'component mismatch card renders');
dashboard_mismatch_check(
    strpos($componentCard, 'Qty 2') !== false && strpos($componentCard, 'Nilai FIFO 2') !== false,
    'component card renders quantity and FIFO value breakdowns'
);
dashboard_mismatch_check(
    strpos($componentCard, 'Qty sama, nilai FIFO berbeda') !== false
        && strpos($componentCard, 'Rp 1.250') !== false,
    'value-only row renders its label and nominal gap'
);
dashboard_mismatch_check(
    strpos($componentCard, '/index.php/production/component-reconcile?') !== false
        && strpos($componentCard, 'q=Nilai%2BSaja') === false
        && strpos($componentCard, 'q=Nilai+Saja') !== false,
    'value-only row retains the existing component reconcile link'
);

[$clearSummary] = dashboard_mismatch_summary([$componentRows[0]]);
$clearCard = dashboard_mismatch_card(dashboard_mismatch_render($clearSummary), 'Mismatch Component');
dashboard_mismatch_check(
    strpos($clearCard, '>Clear<') !== false && strpos($clearCard, '>Aman<') !== false,
    'component card is clear when both mismatch breakdowns are zero'
);

$inconsistentSummary = $clearSummary;
$inconsistentSummary['component']['total'] = 0;
$inconsistentSummary['component']['qty_mismatch_count'] = 0;
$inconsistentSummary['component']['value_mismatch_count'] = 1;
$notClearCard = dashboard_mismatch_card(dashboard_mismatch_render($inconsistentSummary), 'Mismatch Component');
dashboard_mismatch_check(
    strpos($notClearCard, '>Clear<') === false && strpos($notClearCard, '>Aman<') === false,
    'component card never reports clear while either breakdown is non-zero'
);

$sortedComponentRows = [];
for ($rowNumber = 1; $rowNumber <= 305; $rowNumber++) {
    $sortedComponentRows[] = [
        'component_name' => sprintf('Component %04d', $rowNumber),
        'component_code' => sprintf('SORT-%04d', $rowNumber),
        'division_id' => 1,
        'division_name' => 'Kitchen',
        'location_type' => 'REGULER',
        'qty_is_match' => 1,
        'has_lot_value_mismatch' => 0,
        'monthly_lot_value_gap' => 0,
        'is_match' => 1,
    ];
}
$sortedComponentRows[304] = [
    'component_name' => 'Component 0305',
    'component_code' => 'SORT-0305',
    'division_id' => 1,
    'division_name' => 'Kitchen',
    'location_type' => 'REGULER',
    'qty_is_match' => 1,
    'has_lot_value_mismatch' => 1,
    'monthly_lot_value_gap' => 2500.00,
    'is_match' => 0,
    'balance_qty' => 5,
    'daily_qty' => 5,
    'movement_qty' => 5,
    'lot_qty' => 5,
    'delta_balance_daily' => 0,
    'delta_balance_movement' => 0,
    'delta_daily_movement' => 0,
];

[$beyondLimitSummary, $beyondLimitController] = dashboard_mismatch_summary($sortedComponentRows);
$beyondLimitCard = dashboard_mismatch_card(dashboard_mismatch_render($beyondLimitSummary), 'Mismatch Component');
dashboard_mismatch_check(
    $beyondLimitController->Production_model->lastLimit === 2000
        && (int)$beyondLimitSummary['component']['total'] === 1
        && ($beyondLimitSummary['component']['rows'][0]['code'] ?? '') === 'SORT-0305',
    'component mismatch beyond sorted row 300 is included by the restored limit'
);
dashboard_mismatch_check(
    strpos($beyondLimitCard, 'SORT-0305') !== false
        && strpos($beyondLimitCard, '>Clear<') === false
        && strpos($beyondLimitCard, '>Aman<') === false,
    'component mismatch beyond row 300 is rendered and never reported clear'
);

if ($dashboardMismatchFailures !== []) {
    foreach ($dashboardMismatchFailures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, sprintf('%d/%d dashboard component mismatch checks failed.%s', count($dashboardMismatchFailures), $dashboardMismatchChecks, PHP_EOL));
    exit(1);
}

echo sprintf('PASS: %d dashboard component FIFO value mismatch checks.%s', $dashboardMismatchChecks, PHP_EOL);
