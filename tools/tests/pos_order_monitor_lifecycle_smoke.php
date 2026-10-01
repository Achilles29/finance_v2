<?php

declare(strict_types=1);

/**
 * DB-free behavioral/source smoke for the Order Monitor lifecycle contract.
 *
 * The real Pos GET endpoints run with tripwire DB/model doubles. Source checks
 * then cover every writer that can change task eligibility without requiring a
 * database fixture or changing production behavior for testability.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class PosOrderMonitorLifecycleResponseComplete extends RuntimeException
{
}

final class PosOrderMonitorLifecycleInput
{
    public array $reads = [];

    public function __construct(private array $values)
    {
    }

    public function get(string $key, bool $xssClean = false)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }
}

final class PosOrderMonitorLifecycleSession
{
    public array $reads = [];
    public array $writes = [];

    public function __construct(private array $values = [])
    {
    }

    public function userdata(string $key)
    {
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata(string $key, $value): void
    {
        $this->writes[] = [$key, $value];
        $this->values[$key] = $value;
    }
}

final class PosOrderMonitorLifecycleOutput
{
    public int $status = 0;
    public string $contentType = '';
    public string $body = '';

    public function set_status_header(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function set_content_type(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function set_output(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function _display(): void
    {
        throw new PosOrderMonitorLifecycleResponseComplete('response complete');
    }
}

final class PosOrderMonitorLifecycleDb
{
    public array $calls = [];

    public function __call(string $method, array $arguments)
    {
        $this->calls[] = $method;
        throw new RuntimeException('unexpected DB access: ' . $method);
    }
}

final class PosOrderMonitorLifecycleModel
{
    public array $calls = [];
    public array $mutations = [];

    public function station_options(): array
    {
        $this->calls[] = ['station_options'];
        return ['ALL' => 'Semua Stasiun'];
    }

    public function active_outlets(): array
    {
        $this->calls[] = ['active_outlets'];
        return [['id' => 17, 'outlet_name' => 'Outlet Smoke']];
    }

    public function board_payload(
        string $station,
        int $outletId,
        string $dateFrom,
        string $dateTo,
        array $scope
    ): array {
        $this->calls[] = ['board_payload', $station, $outletId, $dateFrom, $dateTo, $scope];
        return ['sentinel' => 'board-read'];
    }

    public function bootstrap_open_tasks(int $outletId = 0): void
    {
        $this->mutations[] = ['bootstrap_open_tasks', $outletId];
        throw new RuntimeException('GET reached bootstrap_open_tasks');
    }

    public function sync_order_tasks(int $orderId): void
    {
        $this->mutations[] = ['sync_order_tasks', $orderId];
        throw new RuntimeException('GET reached sync_order_tasks');
    }

    public function __call(string $method, array $arguments)
    {
        $this->mutations[] = [$method, ...$arguments];
        throw new RuntimeException('GET reached unexpected model method: ' . $method);
    }
}

class MY_Controller
{
    public $input;
    public $session;
    public $output;
    public $db;
    public $Pos_order_monitor_model;
    public array $current_user = [
        'id' => 77,
        'employee_id' => 4242,
        'is_superadmin' => true,
    ];
    public array $permissionCalls = [];
    public array $renderCalls = [];

    public function __construct()
    {
    }

    protected function require_permission(string $pageCode, string $action = 'view'): void
    {
        $this->permissionCalls[] = [$pageCode, $action];
    }

    protected function is_superadmin(): bool
    {
        return !empty($this->current_user['is_superadmin']);
    }

    protected function render(string $view, array $data = [], bool $return = false)
    {
        $this->renderCalls[] = [$view, $data, $return];
        return $return ? 'rendered' : null;
    }
}

require dirname(__DIR__, 2) . '/application/controllers/Pos.php';

$checks = 0;
$failures = [];

function pos_order_monitor_lifecycle_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function pos_order_monitor_lifecycle_source(string $path): string
{
    $source = @file_get_contents($path);
    pos_order_monitor_lifecycle_check(is_string($source), 'source exists: ' . $path);
    return is_string($source) ? $source : '';
}

function pos_order_monitor_lifecycle_method(string $source, string $method): string
{
    if (preg_match(
        '/\n    (?:public|protected|private) function ' . preg_quote($method, '/') . '\s*\(/',
        $source,
        $match,
        PREG_OFFSET_CAPTURE
    ) !== 1) {
        pos_order_monitor_lifecycle_check(false, 'method exists: ' . $method);
        return '';
    }

    $start = (int)$match[0][1] + 1;
    $next = preg_match(
        '/\n    (?:public|protected|private) function /',
        $source,
        $nextMatch,
        PREG_OFFSET_CAPTURE,
        $start + 1
    );
    $end = $next === 1 ? (int)$nextMatch[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function pos_order_monitor_lifecycle_controller(): array
{
    $input = new PosOrderMonitorLifecycleInput([
        'station' => 'BAR',
        'outlet_id' => '17',
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-02',
    ]);
    $session = new PosOrderMonitorLifecycleSession([
        'pos_order_monitor_csrf' => str_repeat('a', 64),
    ]);
    $output = new PosOrderMonitorLifecycleOutput();
    $db = new PosOrderMonitorLifecycleDb();
    $model = new PosOrderMonitorLifecycleModel();
    $reflection = new ReflectionClass(Pos::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = $input;
    $controller->session = $session;
    $controller->output = $output;
    $controller->db = $db;
    $controller->Pos_order_monitor_model = $model;

    return [$controller, $input, $session, $output, $db, $model, $reflection];
}

$root = dirname(__DIR__, 2);
$posSource = pos_order_monitor_lifecycle_source($root . '/application/controllers/Pos.php');
$mobileSource = pos_order_monitor_lifecycle_source($root . '/application/controllers/Pos_mobile.php');
$modelSource = pos_order_monitor_lifecycle_source($root . '/application/models/Pos_order_monitor_model.php');

$expectedScope = [
    'restricted' => false,
    'station_role' => 'ALL',
    'operational_division_id' => 0,
    'division_name' => '',
];
$expectedBoardCall = [
    'board_payload',
    'BAR',
    17,
    '2026-09-01',
    '2026-09-02',
    $expectedScope,
];

[$controller, $input, $session, $output, $db, $model, $reflection]
    = pos_order_monitor_lifecycle_controller();
$pageException = null;
try {
    $controller->order_monitor();
} catch (Throwable $exception) {
    $pageException = $exception;
}
pos_order_monitor_lifecycle_check($pageException === null, 'order_monitor completes without a writer tripwire');
pos_order_monitor_lifecycle_check(
    $controller->permissionCalls === [['pos.order.monitor.index', 'view']],
    'order_monitor preserves view RBAC'
);
pos_order_monitor_lifecycle_check(
    $model->calls === [
        ['station_options'],
        ['active_outlets'],
        $expectedBoardCall,
    ],
    'order_monitor reads only options, outlets, and board payload'
);
pos_order_monitor_lifecycle_check($model->mutations === [], 'order_monitor performs no task mutation');
pos_order_monitor_lifecycle_check($db->calls === [], 'order_monitor performs no direct DB work for superadmin scope');
pos_order_monitor_lifecycle_check(
    $session->reads === ['pos_order_monitor_csrf'] && $session->writes === [],
    'order_monitor reuses the existing scoped token without a session write'
);
pos_order_monitor_lifecycle_check(
    count($controller->renderCalls) === 1
        && $controller->renderCalls[0][0] === 'pos/order_monitor_index'
        && ($controller->renderCalls[0][1]['payload']['sentinel'] ?? '') === 'board-read',
    'order_monitor still renders the board payload'
);

[$controller, $input, $session, $output, $db, $model, $reflection]
    = pos_order_monitor_lifecycle_controller();
$dataException = null;
try {
    $controller->order_monitor_data();
} catch (Throwable $exception) {
    $dataException = $exception;
}
$dataBody = json_decode($output->body, true);
pos_order_monitor_lifecycle_check(
    $dataException instanceof PosOrderMonitorLifecycleResponseComplete,
    'order_monitor_data completes through the JSON response boundary'
);
pos_order_monitor_lifecycle_check(
    $controller->permissionCalls === [['pos.order.monitor.index', 'view']],
    'order_monitor_data preserves view RBAC'
);
pos_order_monitor_lifecycle_check(
    $model->calls === [$expectedBoardCall],
    'order_monitor_data polls only board_payload'
);
pos_order_monitor_lifecycle_check($model->mutations === [], 'order_monitor_data performs no task mutation');
pos_order_monitor_lifecycle_check($db->calls === [], 'order_monitor_data performs no direct DB work for superadmin scope');
pos_order_monitor_lifecycle_check($session->reads === [] && $session->writes === [], 'order_monitor_data does not touch session state');
pos_order_monitor_lifecycle_check(
    $output->status === 200
        && $output->contentType === 'application/json'
        && is_array($dataBody)
        && ($dataBody['ok'] ?? null) === true
        && ($dataBody['payload']['sentinel'] ?? '') === 'board-read',
    'order_monitor_data still returns the board payload'
);

$pageMethod = pos_order_monitor_lifecycle_method($posSource, 'order_monitor');
$dataMethod = pos_order_monitor_lifecycle_method($posSource, 'order_monitor_data');
preg_match_all('/Pos_order_monitor_model->([a-zA-Z0-9_]+)/', $pageMethod, $pageModelMatches);
preg_match_all('/Pos_order_monitor_model->([a-zA-Z0-9_]+)/', $dataMethod, $dataModelMatches);
pos_order_monitor_lifecycle_check(
    ($pageModelMatches[1] ?? []) === ['station_options', 'active_outlets', 'board_payload'],
    'order_monitor source contains only the three read-model calls'
);
pos_order_monitor_lifecycle_check(
    ($dataModelMatches[1] ?? []) === ['board_payload'],
    'order_monitor_data source contains only the board read-model call'
);
pos_order_monitor_lifecycle_check(
    strpos($posSource, 'bootstrap_open_tasks') === false
        && strpos($modelSource, 'bootstrap_open_tasks') === false,
    'bootstrap_open_tasks is removed from controller and model production source'
);

$webSave = pos_order_monitor_lifecycle_method($posSource, 'order_draft_save');
pos_order_monitor_lifecycle_check(
    substr_count($webSave, 'sync_order_tasks(') === 1
        && preg_match(
            '/if\s*\(\s*!empty\(\$result\[\'append_mode\'\]\)\s*&&\s*empty\(\$result\[\'header_only_update\'\]\)\s*&&\s*\(int\)\(\$result\[\'appended_line_count\'\]\s*\?\?\s*0\)\s*>\s*0\s*\)\s*\{\s*\$this->Pos_order_monitor_model->sync_order_tasks\(/s',
            $webSave
        ) === 1,
    'standalone web save syncs exactly once only for a non-header confirmed append'
);
pos_order_monitor_lifecycle_check(
    strpos($webSave, "if (!(\$result['ok'] ?? false))") < strpos($webSave, 'sync_order_tasks('),
    'failed standalone web save returns before append sync'
);

$webConfirm = pos_order_monitor_lifecycle_method($posSource, 'confirm_order_and_respond');
pos_order_monitor_lifecycle_check(
    substr_count($webConfirm, 'sync_order_tasks($orderId)') === 2
        && substr_count($webConfirm, 'finalize_order_confirmation(') === 2
        && preg_match_all(
            '/if \(!\(\$finalize\[\'ok\'\] \?\? false\)\) \{.*?return;\s*\}\s*\$this->Pos_order_monitor_model->sync_order_tasks\(\$orderId\);/s',
            $webConfirm
        ) === 2,
    'web confirm has one sync in each mutually exclusive successful finalize branch'
);
pos_order_monitor_lifecycle_check(
    strpos($webConfirm, 'header_only_update') < strpos($webConfirm, 'resolve_order_stock_commit_payload(')
        && strpos($webConfirm, 'return;', strpos($webConfirm, 'header_only_update')) < strpos($webConfirm, 'resolve_order_stock_commit_payload('),
    'web header-only append returns before confirmation and sync work'
);

$webConfirmEntry = pos_order_monitor_lifecycle_method($posSource, 'order_draft_confirm');
$webCombined = pos_order_monitor_lifecycle_method($posSource, 'order_draft_save_confirm');
pos_order_monitor_lifecycle_check(
    substr_count($webConfirmEntry, 'confirm_order_and_respond(') === 1
        && strpos($webConfirmEntry, 'sync_order_tasks(') === false,
    'standalone confirm delegates once without a second direct sync'
);
pos_order_monitor_lifecycle_check(
    substr_count($webCombined, 'confirm_order_and_respond(') === 1
        && strpos($webCombined, 'sync_order_tasks(') === false,
    'combined save-confirm delegates once without duplicate sync'
);

$reservation = pos_order_monitor_lifecycle_method($posSource, 'verify_reservation_and_respond');
preg_match(
    '/if \(!empty\(\$prepared\[\'already_verified\'\]\)\) \{(.*?)\n        \}\n\n        if \(!empty\(\$prepared\[\'already_pos_finalized\'\]\)\)/s',
    $reservation,
    $alreadyVerifiedMatch
);
preg_match(
    '/if \(!empty\(\$prepared\[\'already_pos_finalized\'\]\)\) \{(.*?)\n        \}\n\n        \$resolved =/s',
    $reservation,
    $recoveryMatch
);
$alreadyVerifiedBranch = (string)($alreadyVerifiedMatch[1] ?? '');
$recoveryBranch = (string)($recoveryMatch[1] ?? '');
pos_order_monitor_lifecycle_check(
    $alreadyVerifiedBranch !== '' && strpos($alreadyVerifiedBranch, 'sync_order_tasks(') === false,
    'already-verified reservation readback does not sync'
);
pos_order_monitor_lifecycle_check(
    substr_count($reservation, 'sync_order_tasks($orderId)') === 2
        && substr_count($recoveryBranch, 'sync_order_tasks($orderId)') === 1
        && strpos($recoveryBranch, 'complete_verification(') < strpos($recoveryBranch, 'sync_order_tasks($orderId)')
        && strpos($recoveryBranch, "if (!(\$completed['ok'] ?? false))") < strpos($recoveryBranch, 'sync_order_tasks($orderId)')
        && preg_match_all(
            '/\$completed = .*?complete_verification\(.*?if \(!\(\$completed\[\'ok\'\] \?\? false\)\) \{.*?return;\s*\}\s*\$this->Pos_order_monitor_model->sync_order_tasks\(\$orderId\);/s',
            $reservation
        ) === 2,
    'reservation recovery syncs once only after complete_verification succeeds'
);

$mobileAppendPattern = '/if\s*\(\s*!empty\(\$result\[\'append_mode\'\]\)\s*&&\s*empty\(\$result\[\'header_only_update\'\]\)\s*&&\s*\(int\)\(\$result\[\'appended_line_count\'\]\s*\?\?\s*0\)\s*>\s*0\s*\)\s*\{.*?sync_order_tasks\(/s';
$mobileSave = pos_order_monitor_lifecycle_method($mobileSource, 'order_save');
pos_order_monitor_lifecycle_check(
    substr_count($mobileSave, 'sync_order_tasks(') === 1
        && preg_match($mobileAppendPattern, $mobileSave) === 1,
    'mobile order_save syncs exactly once only for a non-header confirmed append'
);
pos_order_monitor_lifecycle_check(
    strpos($mobileSave, "if (!(\$result['ok'] ?? false))") < strpos($mobileSave, 'sync_order_tasks('),
    'failed mobile order_save returns before append sync'
);

$mobilePush = pos_order_monitor_lifecycle_method($mobileSource, 'orders_push');
pos_order_monitor_lifecycle_check(
    substr_count($mobilePush, 'sync_order_tasks(') === 1
        && preg_match(
            '/if\s*\(\s*!\$confirmed\s*&&\s*!empty\(\$result\[\'append_mode\'\]\)\s*&&\s*empty\(\$result\[\'header_only_update\'\]\)\s*&&\s*\(int\)\(\$result\[\'appended_line_count\'\]\s*\?\?\s*0\)\s*>\s*0\s*\)\s*\{.*?sync_order_tasks\(/s',
            $mobilePush
        ) === 1,
    'mobile orders_push syncs once only for a non-confirm confirmed append'
);
pos_order_monitor_lifecycle_check(
    substr_count($mobilePush, 'confirm_mobile_order(') === 1,
    'mobile orders_push delegates confirmed writes once and does not double-sync them'
);

foreach (['order_void_save', 'order_refund_save'] as $mobileReversalMethod) {
    $methodSource = pos_order_monitor_lifecycle_method($mobileSource, $mobileReversalMethod);
    $writer = $mobileReversalMethod === 'order_void_save' ? 'save_order_void(' : 'save_order_refund(';
    pos_order_monitor_lifecycle_check(
        substr_count($methodSource, 'sync_order_tasks(') === 1
            && strpos($methodSource, $writer) < strpos($methodSource, "if (!(\$result['ok'] ?? false))")
            && strpos($methodSource, "if (!(\$result['ok'] ?? false))") < strpos($methodSource, 'sync_order_tasks('),
        $mobileReversalMethod . ' syncs exactly once only after writer success'
    );
}

$mobileConfirm = pos_order_monitor_lifecycle_method($mobileSource, 'confirm_mobile_order');
pos_order_monitor_lifecycle_check(
    substr_count($mobileConfirm, 'sync_order_tasks($orderId)') === 2
        && substr_count($mobileConfirm, 'finalize_order_confirmation(') === 2
        && preg_match_all(
            '/if \(!\(\$finalize\[\'ok\'\] \?\? false\)\) \{.*?return \[.*?\];\s*\}\s*\$this->Pos_order_monitor_model->sync_order_tasks\(\$orderId\);/s',
            $mobileConfirm
        ) === 2,
    'mobile confirm has one sync in each mutually exclusive successful finalize branch'
);
pos_order_monitor_lifecycle_check(
    strpos($mobileConfirm, 'header_only_update') < strpos($mobileConfirm, 'resolve_order_stock_commit_payload(')
        && strpos($mobileConfirm, "'header_only_update' => true") < strpos($mobileConfirm, 'resolve_order_stock_commit_payload('),
    'mobile header-only append returns before confirmation and sync work'
);
$mobileConfirmEntry = pos_order_monitor_lifecycle_method($mobileSource, 'order_confirm');
pos_order_monitor_lifecycle_check(
    substr_count($mobileConfirmEntry, 'confirm_mobile_order(') === 1
        && strpos($mobileConfirmEntry, 'sync_order_tasks(') === false,
    'mobile combined save-confirm delegates once without duplicate direct sync'
);

$taskAction = pos_order_monitor_lifecycle_method($modelSource, 'process_task_action');
pos_order_monitor_lifecycle_check(
    substr_count($taskAction, 'sync_order_tasks(') === 1
        && strpos($taskAction, '$this->db->trans_begin();') < strpos($taskAction, 'sync_order_tasks(')
        && strpos($taskAction, 'sync_order_tasks(') < strpos($taskAction, '$this->db->trans_status()'),
    'internal task action performs exactly one sync inside its transaction'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL: POS Order Monitor lifecycle smoke\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: ' . $checks . " POS Order Monitor lifecycle behavioral/source checks\n";
