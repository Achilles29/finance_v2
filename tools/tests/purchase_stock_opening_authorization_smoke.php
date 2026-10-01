<?php

// DB-free source/runtime smoke for Purchase division-opening direct URL RBAC.
defined('BASEPATH') OR define('BASEPATH', __DIR__);

final class PurchaseOpeningAuthorizationDenied extends RuntimeException
{
}

class MY_Controller
{
    public $Purchase_model;
    public $db;
    public $input;
    public $load;
    public $session;
    public $simplespreadsheetio;
    public array $permissionChecks = [];
    public array $renderCalls = [];
    protected array $current_user = [];
    private array $smokePermissions = [];

    public function setSmokePrincipal(array $permissions, bool $authenticated): void
    {
        $this->smokePermissions = $permissions;
        $this->current_user = $authenticated ? ['id' => 701] : [];
    }

    protected function can(string $pageCode, string $action = 'view'): bool
    {
        $this->permissionChecks[] = [$pageCode, $action];
        return !empty($this->smokePermissions['__superadmin__'])
            || !empty($this->smokePermissions[$pageCode]['can_' . $action]);
    }

    protected function require_permission(string $pageCode, string $action = 'view'): void
    {
        if (!$this->can($pageCode, $action)) {
            throw new PurchaseOpeningAuthorizationDenied($pageCode . ':' . $action);
        }
    }

    protected function render(string $view, array $data = []): void
    {
        $this->renderCalls[] = [$view, $data];
    }
}

final class PurchaseOpeningSmokeInput
{
    public array $reads = [];

    public function __construct(private array $values = [])
    {
    }

    public function get(string $key, bool $xssClean = false)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? '';
    }
}

final class PurchaseOpeningSmokeModel
{
    public array $calls = [];

    public function __call(string $method, array $arguments): array
    {
        $this->calls[] = [$method, $arguments];
        if ($method === 'list_active_operational_divisions') {
            return [['id' => 17, 'code' => 'BAR', 'name' => 'Bar Division']];
        }
        return [];
    }
}

final class PurchaseOpeningSmokeDb
{
    public array $calls = [];

    public function table_exists(string $table): bool
    {
        $this->calls[] = ['table_exists', $table];
        return false;
    }
}

final class PurchaseOpeningSmokeSession
{
    public array $calls = [];
    private array $data = [];

    public function userdata(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set_userdata(string $key, $value): void
    {
        $this->data[$key] = $value;
        $this->calls[] = ['set_userdata', $key];
    }

    public function set_flashdata(string $key, $value): void
    {
        $this->calls[] = ['set_flashdata', $key, $value];
    }
}

final class PurchaseOpeningSmokeLoader
{
    public array $calls = [];

    public function library(string $library): void
    {
        $this->calls[] = ['library', $library];
    }
}

final class PurchaseOpeningSmokeSpreadsheet
{
    public array $calls = [];

    public function output_xlsx(string $filename, array $headers, array $rows, string $sheet): void
    {
        $this->calls[] = [$filename, $headers, $rows, $sheet];
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return 'https://purchase-smoke.test/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        throw new RuntimeException('Unexpected redirect to ' . $path);
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Purchase.php';

$purchaseOpeningChecks = 0;
$purchaseOpeningFailures = [];

function purchase_opening_check(bool $condition, string $message): void
{
    global $purchaseOpeningChecks, $purchaseOpeningFailures;
    $purchaseOpeningChecks++;
    if (!$condition) {
        $purchaseOpeningFailures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function purchase_opening_method_source(string $source, string $method): string
{
    $start = strpos($source, 'public function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    $matched = preg_match(
        '/\n    (?:public|protected|private) function /',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = $matched === 1 ? (int)$matches[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function purchase_opening_runtime(array $permissions, bool $authenticated, string $endpoint): array
{
    $controller = (new ReflectionClass(Purchase::class))->newInstanceWithoutConstructor();
    $controller->setSmokePrincipal($permissions, $authenticated);
    $controller->input = new PurchaseOpeningSmokeInput([
        'month' => '2026-09',
        'q' => '',
        'division_id' => 17,
        'material_id' => 0,
        'destination' => 'BAR',
        'per_page' => 25,
        'page' => 1,
    ]);
    $controller->Purchase_model = new PurchaseOpeningSmokeModel();
    $controller->db = new PurchaseOpeningSmokeDb();
    $controller->session = new PurchaseOpeningSmokeSession();
    $controller->load = new PurchaseOpeningSmokeLoader();
    $controller->simplespreadsheetio = new PurchaseOpeningSmokeSpreadsheet();

    $denied = false;
    try {
        $controller->{$endpoint}();
    } catch (PurchaseOpeningAuthorizationDenied $exception) {
        $denied = true;
    } catch (Throwable $exception) {
        purchase_opening_check(false, $endpoint . ' raised unexpected ' . get_class($exception) . ': ' . $exception->getMessage());
    }

    return [
        'controller' => $controller,
        'denied' => $denied,
        'input' => $controller->input,
        'model' => $controller->Purchase_model,
        'db' => $controller->db,
        'session' => $controller->session,
        'loader' => $controller->load,
        'spreadsheet' => $controller->simplespreadsheetio,
    ];
}

$controllerSource = file_get_contents(dirname(__DIR__, 2) . '/application/controllers/Purchase.php');
purchase_opening_check(is_string($controllerSource), 'Purchase controller source is readable');

$indexSource = purchase_opening_method_source($controllerSource, 'stock_opening_division_index');
$generatedSource = purchase_opening_method_source($controllerSource, 'stock_opening_division_generated');
$exportSource = purchase_opening_method_source($controllerSource, 'stock_opening_division_export_template');
$exportExistingSource = purchase_opening_method_source($controllerSource, 'stock_opening_division_export_existing');
$warehouseSource = purchase_opening_method_source($controllerSource, 'stock_opening_warehouse_index');
$viewSource = file_get_contents(dirname(__DIR__, 2) . '/application/views/purchase/stock_opening_division_index.php');

foreach ([
    'stock_opening_division_index' => $indexSource,
    'stock_opening_division_generated' => $generatedSource,
] as $method => $methodSource) {
    $guardAt = strpos($methodSource, '$this->require_permission(self::PAGE_STOCK_DIVISION, \'view\');');
    $inputAt = strpos($methodSource, '$this->input->get(');
    $modelAt = strpos($methodSource, '$this->Purchase_model->');
    $renderAt = strpos($methodSource, '$this->render(');
    purchase_opening_check($methodSource !== '', $method . ' exists');
    purchase_opening_check(
        $guardAt !== false
            && strpos($methodSource, 'self::PAGE_ORDER') === false
            && $inputAt !== false
            && $guardAt < $inputAt
            && $modelAt !== false
            && $guardAt < $modelAt
            && $renderAt !== false
            && $guardAt < $renderAt,
        $method . ' requires stock-division view before input, model, and render with no order fallback'
    );
}

$exportViewAt = strpos($exportSource, '$this->can(self::PAGE_STOCK_DIVISION, \'view\')');
$exportCreateAt = strpos($exportSource, '$this->can(self::PAGE_STOCK_DIVISION, \'create\')');
$exportDenyAt = strpos($exportSource, '$this->require_permission(self::PAGE_STOCK_DIVISION, \'view\');');
$exportInputAt = strpos($exportSource, '$this->input->get(');
$exportModelAt = strpos($exportSource, '$this->stock_opening_division_map()');
$exportXlsxAt = strpos($exportSource, '$this->simplespreadsheetio->output_xlsx(');
purchase_opening_check(
    $exportSource !== ''
        && $exportViewAt !== false
        && $exportCreateAt !== false
        && $exportDenyAt !== false
        && strpos($exportSource, 'self::PAGE_ORDER') === false
        && $exportDenyAt < $exportInputAt
        && $exportDenyAt < $exportModelAt
        && $exportDenyAt < $exportXlsxAt,
    'division template export keeps stock-division view-or-create authorization before input/model/XLSX with no order fallback'
);
$existingGuardAt = strpos($exportExistingSource, '$this->require_permission(self::PAGE_STOCK_DIVISION, \'export\');');
$existingInputAt = strpos($exportExistingSource, '$this->input->get(');
$existingModelAt = strpos($exportExistingSource, '$this->Purchase_model->list_stock_opening_snapshots(');
$existingXlsxAt = strpos($exportExistingSource, '$this->simplespreadsheetio->output_xlsx(');
purchase_opening_check(
    $existingGuardAt !== false
        && strpos($exportExistingSource, "'view'") === false
        && $existingGuardAt < $existingInputAt
        && $existingGuardAt < $existingModelAt
        && $existingGuardAt < $existingXlsxAt,
    'existing export requires stock-division export before input/model/XLSX with no view fallback'
);
purchase_opening_check(
    strpos($indexSource, "'can_export_existing'") !== false
        && strpos($indexSource, "\$this->can(self::PAGE_STOCK_DIVISION, 'export')") !== false
        && strpos($viewSource, 'if (!empty($can_export_existing)):') !== false
        && strpos($viewSource, 'if (!empty($can_export_existing)):') < strpos($viewSource, 'id="opn-export-existing-form"')
        && substr_count($viewSource, 'if (!form) return;') >= 2,
    'division index passes export capability and gates the existing-export form'
);
purchase_opening_check(
    strpos($warehouseSource, 'self::PAGE_STOCK_WAREHOUSE') !== false
        && strpos($warehouseSource, '$this->require_permission(self::PAGE_ORDER, \'view\');') !== false,
    'warehouse opening fallback remains unchanged'
);

$orderOnly = [Purchase::PAGE_ORDER => ['can_view' => 1]];
$none = [];
foreach ([
    'purchase-order-only user' => [$orderOnly, true],
    'authenticated user without permissions' => [$none, true],
    'anonymous principal' => [$none, false],
] as $principal => [$permissions, $authenticated]) {
    foreach (['stock_opening_division_index', 'stock_opening_division_generated', 'stock_opening_division_export_template', 'stock_opening_division_export_existing'] as $endpoint) {
        $result = purchase_opening_runtime($permissions, $authenticated, $endpoint);
        purchase_opening_check($result['denied'], $principal . ' is denied from ' . $endpoint);
        purchase_opening_check(
            $result['input']->reads === []
                && $result['model']->calls === []
                && $result['db']->calls === []
                && $result['controller']->renderCalls === []
                && $result['session']->calls === []
                && $result['loader']->calls === []
                && $result['spreadsheet']->calls === [],
            $principal . ' reaches no input/query/model/render/XLSX downstream in ' . $endpoint
        );
        purchase_opening_check(
            array_values(array_unique(array_column($result['controller']->permissionChecks, 0))) === [Purchase::PAGE_STOCK_DIVISION],
            $principal . ' is checked only against the stock-division page in ' . $endpoint
        );
    }
}

$viewOnly = [Purchase::PAGE_STOCK_DIVISION => ['can_view' => 1]];
foreach (['stock_opening_division_index', 'stock_opening_division_generated', 'stock_opening_division_export_template'] as $endpoint) {
    $result = purchase_opening_runtime($viewOnly, true, $endpoint);
    purchase_opening_check(!$result['denied'], 'stock-division view allows ' . $endpoint);
    $isExport = $endpoint === 'stock_opening_division_export_template';
    purchase_opening_check(
        $isExport
            ? count($result['spreadsheet']->calls) === 1 && count($result['loader']->calls) === 1
            : count($result['controller']->renderCalls) === 1 && count($result['model']->calls) > 0,
        'stock-division view reaches the expected authorized downstream for ' . $endpoint
    );
}

$viewOnlyExisting = purchase_opening_runtime($viewOnly, true, 'stock_opening_division_export_existing');
purchase_opening_check($viewOnlyExisting['denied'], 'stock-division view-only user is denied from existing export');
purchase_opening_check(
    $viewOnlyExisting['input']->reads === []
        && $viewOnlyExisting['model']->calls === []
        && $viewOnlyExisting['loader']->calls === []
        && $viewOnlyExisting['spreadsheet']->calls === [],
    'view-only existing-export denial occurs before input/model/XLSX'
);
$viewOnlyIndex = purchase_opening_runtime($viewOnly, true, 'stock_opening_division_index');
$viewOnlyIndexData = (array)($viewOnlyIndex['controller']->renderCalls[0][1] ?? []);
purchase_opening_check(
    array_key_exists('can_export_existing', $viewOnlyIndexData) && $viewOnlyIndexData['can_export_existing'] === false,
    'view-only index renders with existing-export capability disabled'
);

$createOnly = [Purchase::PAGE_STOCK_DIVISION => ['can_create' => 1]];
foreach (['stock_opening_division_index', 'stock_opening_division_generated'] as $endpoint) {
    $result = purchase_opening_runtime($createOnly, true, $endpoint);
    purchase_opening_check($result['denied'], 'stock-division create-only user is denied from ' . $endpoint);
    purchase_opening_check(
        $result['input']->reads === []
            && $result['model']->calls === []
            && $result['db']->calls === []
            && $result['controller']->renderCalls === []
            && $result['spreadsheet']->calls === [],
        'create-only denial reaches no downstream in ' . $endpoint
    );
}

$createExport = purchase_opening_runtime($createOnly, true, 'stock_opening_division_export_template');
purchase_opening_check(!$createExport['denied'], 'stock-division create-only user may export the opening template');
purchase_opening_check(
    count($createExport['spreadsheet']->calls) === 1
        && count($createExport['loader']->calls) === 1
        && count($createExport['model']->calls) > 0
        && count($createExport['db']->calls) > 0
        && $createExport['controller']->renderCalls === [],
    'create-only template export reaches model/query/XLSX only after authorization'
);

$createExisting = purchase_opening_runtime($createOnly, true, 'stock_opening_division_export_existing');
purchase_opening_check($createExisting['denied'], 'stock-division create-only user is denied from existing export');
purchase_opening_check(
    $createExisting['input']->reads === []
        && $createExisting['model']->calls === []
        && $createExisting['loader']->calls === []
        && $createExisting['spreadsheet']->calls === [],
    'create-only existing-export denial occurs before input/model/XLSX'
);

foreach ([
    'export-only user' => [Purchase::PAGE_STOCK_DIVISION => ['can_export' => 1]],
    'superadmin' => ['__superadmin__' => true],
] as $principal => $permissions) {
    $result = purchase_opening_runtime($permissions, true, 'stock_opening_division_export_existing');
    purchase_opening_check(!$result['denied'], $principal . ' may export existing opening rows');
    purchase_opening_check(
        count($result['input']->reads) > 0
            && count($result['model']->calls) > 0
            && count($result['loader']->calls) === 1
            && count($result['spreadsheet']->calls) === 1,
        $principal . ' reaches model and XLSX after export authorization'
    );
}

if ($purchaseOpeningFailures !== []) {
    fwrite(STDERR, count($purchaseOpeningFailures) . ' purchase opening authorization smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $purchaseOpeningChecks . ' purchase stock opening authorization smoke checks passed.' . PHP_EOL;
