<?php

// DB-free source smoke for POS Mobile printer permission, outlet and terminal binding.
$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Pos_mobile.php');
$modelSource = (string)file_get_contents($root . '/application/models/Pos_print_model.php');
$failures = [];
$checks = 0;

function pmpb_check(bool $condition, string $message): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        echo 'FAIL: ' . $message . PHP_EOL;
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function pmpb_method(string $source, string $method): string
{
    $pattern = '/\n    (?:public|private|protected) function ' . preg_quote($method, '/') . '\s*\(/';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = $match[0][1];
    if (preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + strlen($match[0][0])) !== 1) {
        return substr($source, $start);
    }
    return substr($source, $start, $next[0][1] - $start);
}

function pmpb_ordered(string $source, array $needles): bool
{
    $offset = 0;
    foreach ($needles as $needle) {
        $position = strpos($source, $needle, $offset);
        if ($position === false) {
            return false;
        }
        $offset = $position + strlen($needle);
    }
    return true;
}

$printers = pmpb_method($controllerSource, 'printers');
$printerTest = pmpb_method($controllerSource, 'printer_test');
$permission = pmpb_method($controllerSource, 'mobile_printer_permission');
$redactor = pmpb_method($controllerSource, 'mobile_redact_printer_payload');
$connectionRows = pmpb_method($modelSource, 'connection_rows');
$routeRows = pmpb_method($modelSource, 'route_rows');
$connectionResolver = pmpb_method($modelSource, 'find_active_connection_at_outlet');
$routeResolver = pmpb_method($modelSource, 'find_mobile_test_route');

pmpb_check($controllerSource !== '' && $modelSource !== '', 'controller and printer model sources are readable');
pmpb_check(
    pmpb_ordered($printers, [
        'authorize_mobile(true)',
        "mobile_printer_permission('view')",
        'mobile_reader_binding_context()',
        'Pos_print_model->ready()',
        'Pos_print_model->connection_rows(',
        'Pos_print_model->route_rows(',
    ]),
    'printer list orders auth, view policy, bearer binding, then scoped model reads'
);
pmpb_check(
    strpos($printers, "'outlet_id'] = \$outletId") !== false
        && strpos($printers, "'connection_outlet_id' => \$outletId") !== false
        && strpos($printers, "'terminal_id' => (int)\$binding['terminal_id']") !== false,
    'bearer list passes authoritative outlet and terminal filters to the model'
);
pmpb_check(
    strpos($printers, "? 'ACTIVE'") !== false
        && strpos($printers, "'runtime_ready' => true") !== false,
    'bearer list only projects active runtime-ready printer configuration'
);
pmpb_check(
    strpos($printers, '$connectionOutlet !== $outletId') !== false,
    'bearer list has a defensive exact connection-outlet check'
);
pmpb_check(
    pmpb_ordered($connectionRows, ["\$filters['outlet_id']", "where('c.outlet_id', \$outletId)", 'count_all_results']),
    'connection outlet filter is applied before count and pagination'
);
pmpb_check(
    pmpb_ordered($routeRows, ["\$filters['connection_outlet_id']", "where('c.outlet_id', \$connectionOutletId)", "\$filters['outlet_id']", "r.outlet_id IS NULL", "\$filters['terminal_id']", "r.terminal_id IS NULL", 'count_all_results']),
    'route outlet and terminal compatibility filters are applied before count and pagination'
);
pmpb_check(
    strpos($routeRows, "\$filters['runtime_ready']") !== false
        && strpos($routeRows, "where('c.is_active', 1)") !== false
        && strpos($routeRows, "where('l.is_active', 1)") !== false,
    'route list can require active connection and layout dependencies'
);
pmpb_check(
    pmpb_ordered($printerTest, [
        'require_mobile_post()',
        'authorize_mobile(true)',
        "mobile_printer_permission('test')",
        'mobile_reader_binding_context()',
        'Pos_print_model->ready()',
        'find_active_connection_at_outlet(',
        'find_mobile_test_route(',
        'runtime_template(',
        'buildPreviewPackage(',
        'create_attempt(',
    ]),
    'test print orders POST, auth, stronger permission, binding, resolvers, preview, then attempt'
);
pmpb_check(
    substr_count($printerTest, "'Printer POS tidak ditemukan.'") >= 2,
    'missing connection and incompatible route share a generic bearer 404'
);
pmpb_check(
    strpos($printerTest, "'outlet_id' => \$isBearer ? (int)\$binding['outlet_id']") !== false
        && strpos($printerTest, "'terminal_id' => \$isBearer ? (int)\$binding['terminal_id']") !== false,
    'test attempt records authoritative bearer outlet and terminal'
);
pmpb_check(
    strpos($printerTest, 'mobile_redact_printer_payload($preview)') !== false
        && strpos($printers, 'mobile_redact_printer_payload($general)') !== false,
    'bearer printer list and test preview pass through secret redaction'
);
pmpb_check(
    strpos($redactor, "['wifi_password', 'agent_host', 'python_port']") !== false
        && strpos($redactor, 'mobile_redact_printer_payload($value)') !== false,
    'redaction removes printer/network secrets recursively'
);
pmpb_check(
    strpos($permission, "\$permissionAction = \$action === 'test' ? 'edit' : \$action") !== false,
    'test print maps to edit permission rather than view permission'
);
pmpb_check(
    strpos($permission, "\$action === 'view'") !== false
        && strpos($permission, "mobile_can('pos.cashier.index', 'view')") !== false
        && strpos($permission, "mobile_can('pos.order.draft.index', 'view')") !== false,
    'bearer printer discovery supports cashier or draft view permission'
);
pmpb_check(
    strpos($permission, "\$action === 'test'") !== false
        && strpos($permission, "mobile_can('pos.cashier.index', 'edit')") !== false
        && strpos($permission, "mobile_can('pos.order.draft.index', 'edit')") !== false,
    'bearer test print fallback requires cashier or draft edit permission'
);
pmpb_check(
    strpos($permission, 'if (is_array($this->mobileUser))') !== false
        && strpos($permission, 'return $this->mobile_permission($pageCode, $permissionAction)') !== false,
    'operational permission fallback is bearer-only and web keeps the printer-page policy'
);
pmpb_check(
    pmpb_ordered($connectionResolver, ["\$id <= 0 || \$outletId <= 0", "where('id', \$id)", "where('outlet_id', \$outletId)", "where('is_active', 1)"]),
    'connection resolver fails closed and requires exact active outlet ownership'
);
pmpb_check(
    strpos($routeResolver, "where('r.connection_id', \$connectionId)") !== false
        && strpos($routeResolver, "where('c.outlet_id', \$outletId)") !== false
        && strpos($routeResolver, "where('r.is_active', 1)") !== false
        && strpos($routeResolver, "where('c.is_active', 1)") !== false
        && strpos($routeResolver, "where('l.is_active', 1)") !== false,
    'test route resolver requires matching connection outlet and active dependencies'
);
pmpb_check(
    strpos($routeResolver, "where('r.outlet_id', \$outletId)") !== false
        && strpos($routeResolver, "or_where('r.outlet_id IS NULL'") !== false
        && strpos($routeResolver, "where('r.terminal_id', \$terminalId)") !== false
        && strpos($routeResolver, "or_where('r.terminal_id IS NULL'") !== false,
    'test route resolver admits exact or generic outlet/terminal routes only'
);
pmpb_check(
    pmpb_ordered($routeResolver, [
        'CASE WHEN r.outlet_id IS NULL THEN 0 ELSE 1 END',
        'CASE WHEN r.terminal_id IS NULL THEN 0 ELSE 1 END',
        "order_by('r.priority'",
        "order_by('r.id'",
    ]),
    'test route resolver prefers specific context then deterministic priority and id'
);
pmpb_check(
    strpos($routeResolver, "where('c.connection_type', 'LOCAL_AGENT')") !== false
        && strpos($routeResolver, "where('c.python_port IS NOT NULL'") !== false,
    'test route resolver rejects connections that cannot reach the local agent'
);

// Reuse only the DB-free CI harness; the development authorization suite runs
// independently through its own quality-gate tier.
if (!defined('POS_MOBILE_AUTHORIZATION_HARNESS_ONLY')) {
    define('POS_MOBILE_AUTHORIZATION_HARNESS_ONLY', true);
}
require __DIR__ . '/pos_mobile_authorization_smoke.php';

$boundDeviceKey = 'printer-binding-device';
$validToken = 'printer-binding-token';
$boundHeaders = [
    'Authorization' => 'Bearer ' . $validToken,
    'X-Pos-Mobile-Device-Key' => $boundDeviceKey,
];
$tokenRows = [[
    'id' => 41,
    'token_hash' => hash('sha256', $validToken),
    'user_id' => 42,
    'employee_id' => 314,
    'terminal_device_key' => $boundDeviceKey,
    'revoked_at' => null,
    'expires_at' => '2999-12-31 23:59:59',
    'is_active' => 1,
]];
$activeTerminalRows = [[
    'id' => 501,
    'outlet_id' => 71,
    'device_key' => $boundDeviceKey,
    'is_active' => 1,
]];

final class PosMobilePrinterPreviewBehaviorFake
{
    public int $calls = 0;

    public function buildPreviewPackage(array $payload, array $printer, string $documentType): array
    {
        $this->calls++;
        return [
            'payload' => $payload + ['wifi_password' => 'SECRET-WIFI'],
            'document_type' => $documentType,
            'lines' => ['TEST PRINT'],
            'paper_width_mm' => 80,
            'chars_per_line' => 48,
            'summary' => ['agent_host' => 'SECRET-HOST', 'python_port' => 9911],
        ];
    }
}

function pmpb_bearer_controller(array $permissions, array $query = []): array
{
    global $boundHeaders, $tokenRows, $activeTerminalRows;
    $parts = pos_mobile_smoke_controller(
        $permissions,
        true,
        0,
        $query,
        $boundHeaders,
        $tokenRows,
        'POST',
        $activeTerminalRows
    );
    $parts[4]->readyResult = true;
    $parts[0]->posprinterpreviewservice = new PosMobilePrinterPreviewBehaviorFake();
    return $parts;
}

$cashierView = ['pos.cashier.index' => ['can_view' => 1]];
$cashierEdit = ['pos.cashier.index' => ['can_edit' => 1]];

[$controller, $auth, $output, $model, $printModel] = pmpb_bearer_controller($cashierView, ['outlet_id' => 72]);
$controller->printers();
pmpb_check(
    $output->status === 403 && $printModel->readyCalls === 0 && $printModel->connectionRowsCalls === 0,
    'bearer list rejects a positive cross-outlet query before printer model reads'
);

[$controller, $auth, $output, $model, $printModel] = pmpb_bearer_controller($cashierView, ['terminal_id' => 999]);
$controller->printers();
pmpb_check(
    $output->status === 403 && $printModel->readyCalls === 0 && $printModel->routeRowsCalls === 0,
    'bearer list rejects a positive cross-terminal query before printer model reads'
);

[$controller, $auth, $output, $model, $printModel] = pmpb_bearer_controller($cashierView, [
    'outlet_id' => 0,
    'terminal_id' => 0,
    'status' => 'INACTIVE',
]);
$printModel->connectionRowsResult = [
    'rows' => [[
        'id' => 81,
        'outlet_id' => 71,
        'connection_code' => 'PRN-81',
        'connection_name' => 'Kasir A',
        'is_active' => 1,
    ]],
    'meta' => ['total' => 1, 'page' => 1, 'limit' => 100, 'total_pages' => 1],
];
$printModel->routeRowsResult = ['rows' => [[
    'id' => 91,
    'connection_id' => 81,
    'outlet_id' => null,
    'terminal_id' => null,
    'event_code' => 'PAYMENT_PAID',
]]];
$printModel->generalSettingsResult = ['payload' => [
    'title' => 'Outlet A',
    'wifi_password' => 'SECRET-WIFI',
    'nested' => ['agent_host' => 'SECRET-HOST', 'python_port' => 9911],
]];
$controller->printers();
$listPayload = json_decode($output->body, true);
pmpb_check(
    $output->status === 200
        && ($printModel->lastConnectionFilters['outlet_id'] ?? 0) === 71
        && ($printModel->lastConnectionFilters['status'] ?? '') === 'ACTIVE'
        && ($printModel->lastRouteFilters['connection_outlet_id'] ?? 0) === 71
        && ($printModel->lastRouteFilters['outlet_id'] ?? 0) === 71
        && ($printModel->lastRouteFilters['terminal_id'] ?? 0) === 501
        && !empty($printModel->lastRouteFilters['runtime_ready']),
    'bearer list passes authoritative scoped filters to the fake printer model'
);
pmpb_check(
    ($listPayload['meta']['total'] ?? 0) === 1
        && ($listPayload['rows'][0]['outlet_id'] ?? 0) === 71
        && strpos($output->body, 'SECRET-WIFI') === false
        && strpos($output->body, 'SECRET-HOST') === false
        && strpos($output->body, '9911') === false,
    'bearer list returns scoped metadata while redacting nested printer secrets'
);

[$controller, $auth, $output, $model, $printModel] = pmpb_bearer_controller($cashierEdit);
$controller->printer_test(81);
pmpb_check(
    $output->status === 404
        && $printModel->findScopedConnectionCalls === 1
        && $printModel->findMobileRouteCalls === 0
        && $printModel->runtimeTemplateCalls === 0
        && $printModel->attemptCalls === 0,
    'missing or cross-outlet connection stops before route, preview, and attempt'
);

[$controller, $auth, $output, $model, $printModel] = pmpb_bearer_controller($cashierEdit);
$printModel->findScopedConnectionResult = ['id' => 81, 'outlet_id' => 71, 'is_active' => 1];
$controller->printer_test(81);
pmpb_check(
    $output->status === 404
        && $printModel->lastMobileRouteArguments === [81, 71, 501]
        && $printModel->runtimeTemplateCalls === 0
        && $controller->posprinterpreviewservice->calls === 0
        && $printModel->attemptCalls === 0,
    'incompatible or other-terminal route stops before template, preview, and attempt'
);

$routeFixture = [
    'id' => 91,
    'connection_id' => 81,
    'outlet_id' => null,
    'terminal_id' => null,
    'layout_id' => 21,
    'connection_name' => 'Kasir A',
    'connection_code' => 'PRN-81',
    'location_label' => 'CASHIER',
    'content_scope' => 'ALL_ITEMS',
    'connection_type' => 'LOCAL_AGENT',
    'python_port' => 9911,
    'agent_host' => 'SECRET-HOST',
    'paper_width_mm' => 80,
    'chars_per_line' => 48,
    'copy_count' => 1,
    'default_copy_count' => 1,
    'cut_mode' => 'PARTIAL',
    'layout_name' => 'Receipt',
    'layout_code' => 'RECEIPT',
    'route_name' => 'Generic Receipt',
    'route_code' => 'GENERIC',
    'event_code' => 'PAYMENT_PAID',
];
[$controller, $auth, $output, $model, $printModel] = pmpb_bearer_controller($cashierEdit);
$printModel->findScopedConnectionResult = ['id' => 81, 'outlet_id' => 71, 'is_active' => 1];
$printModel->findMobileRouteResult = $routeFixture;
$printModel->runtimeTemplateResult = [
    'payload' => ['title' => 'Receipt', 'wifi_password' => 'SECRET-WIFI'],
    'document_type' => 'RECEIPT',
];
$controller->printer_test(81);
pmpb_check(
    $output->status === 200
        && $printModel->attemptCalls === 1
        && ($printModel->lastAttemptPayload['outlet_id'] ?? 0) === 71
        && ($printModel->lastAttemptPayload['terminal_id'] ?? 0) === 501,
    'generic compatible route succeeds and attempt uses authoritative bearer context'
);
pmpb_check(
    strpos($output->body, 'SECRET-WIFI') === false
        && strpos($output->body, 'SECRET-HOST') === false
        && strpos($output->body, '9911') === false,
    'successful bearer test response recursively redacts Wi-Fi and agent connection secrets'
);

[$controller, $auth, $output, $model, $printModel] = pos_mobile_smoke_controller(
    ['pos.printer.connection' => ['can_edit' => 1]],
    true,
    7,
    [],
    [],
    [],
    'POST'
);
$printModel->readyResult = true;
$printModel->findConnectionResult = ['id' => 81, 'outlet_id' => 72, 'is_active' => 1];
$printModel->routeRowsResult = ['rows' => [$routeFixture]];
$printModel->runtimeTemplateResult = ['payload' => ['title' => 'Receipt'], 'document_type' => 'RECEIPT'];
$controller->posprinterpreviewservice = new PosMobilePrinterPreviewBehaviorFake();
$controller->printer_test(81);
pmpb_check(
    $output->status === 200
        && $printModel->findConnectionCalls === 1
        && $printModel->findScopedConnectionCalls === 0
        && $printModel->routeRowsCalls === 1
        && $printModel->attemptCalls === 1,
    'web fallback keeps legacy unscoped connection and route lookup after printer edit permission'
);

[$controller, $auth, $output, $model, $printModel] = pos_mobile_smoke_controller(
    ['pos.printer.connection' => ['can_view' => 1]],
    true,
    7,
    [],
    [],
    [],
    'POST'
);
$controller->printer_test(81);
pmpb_check(
    $output->status === 403 && $printModel->readyCalls === 0 && $printModel->attemptCalls === 0,
    'web printer view permission alone cannot create a test-print attempt'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile printer binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile printer binding smoke checks passed.' . PHP_EOL;
