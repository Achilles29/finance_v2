<?php

declare(strict_types=1);

/**
 * DB/bootstrap/network-free smoke for post-commit POS availability refresh.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

final class InventoryValueAvailabilityFakeRebuildService
{
    public array $calls = [];
    public array $componentResult = ['ok' => true, 'success_count' => 1, 'failed_count' => 0];
    public array $materialResult = ['ok' => true, 'success_count' => 1, 'failed_count' => 0];
    public array $itemResult = ['ok' => true, 'success_count' => 1, 'failed_count' => 0];
    public bool $throwOnMaterial = false;

    public function handle_component_change(int $componentId, array $context = []): array
    {
        $this->calls[] = ['handler' => __FUNCTION__, 'id' => $componentId, 'context' => $context];
        return $this->componentResult;
    }

    public function handle_material_change(int $materialId, array $context = []): array
    {
        $this->calls[] = ['handler' => __FUNCTION__, 'id' => $materialId, 'context' => $context];
        if ($this->throwOnMaterial) {
            throw new RuntimeException('simulated cache outage');
        }
        return $this->materialResult;
    }

    public function handle_item_change(int $itemId, array $context = []): array
    {
        $this->calls[] = ['handler' => __FUNCTION__, 'id' => $itemId, 'context' => $context];
        return $this->itemResult;
    }
}

final class InventoryValueAvailabilityFakeLoader
{
    public array $calls = [];

    public function library(string $name): void
    {
        $this->calls[] = $name;
    }
}

final class InventoryValueAvailabilityFakeCi
{
    public InventoryValueAvailabilityFakeLoader $load;
    public InventoryValueAvailabilityFakeRebuildService $posavailabilityrebuildservice;

    public function __construct()
    {
        $this->load = new InventoryValueAvailabilityFakeLoader();
        $this->posavailabilityrebuildservice = new InventoryValueAvailabilityFakeRebuildService();
    }
}

$inventoryValueAvailabilityFakeCi = new InventoryValueAvailabilityFakeCi();
$inventoryValueAvailabilityLogs = [];

function &get_instance()
{
    global $inventoryValueAvailabilityFakeCi;
    return $inventoryValueAvailabilityFakeCi;
}

function log_message($level, $message): void
{
    global $inventoryValueAvailabilityLogs;
    $inventoryValueAvailabilityLogs[] = [(string)$level, (string)$message];
}

require dirname(__DIR__, 2) . '/application/libraries/InventoryValueReconciliationService.php';

$inventoryValueAvailabilityChecks = 0;
$inventoryValueAvailabilityFailures = [];

function inventory_value_availability_check(bool $condition, string $message): void
{
    global $inventoryValueAvailabilityChecks, $inventoryValueAvailabilityFailures;
    $inventoryValueAvailabilityChecks++;
    if (!$condition) {
        $inventoryValueAvailabilityFailures[] = $message;
    }
}

function inventory_value_availability_refresh(
    InventoryValueReconciliationService $service,
    array $context,
    string $eventSource,
    int $revaluationId,
    int $actorUserId
): array {
    $method = new ReflectionMethod($service, 'refreshAvailabilityAfterCommit');
    $method->setAccessible(true);
    return $method->invoke($service, $context, $eventSource, $revaluationId, $actorUserId);
}

$service = new InventoryValueReconciliationService();
$component = inventory_value_availability_refresh(
    $service,
    ['stock_domain' => 'COMPONENT', 'component_id' => 41],
    'STOCK_VALUE_REVALUATION',
    701,
    19
);
$componentCall = $inventoryValueAvailabilityFakeCi->posavailabilityrebuildservice->calls[0] ?? [];
inventory_value_availability_check(($component['ok'] ?? false) === true && ($component['status'] ?? '') === 'SUCCESS', 'component refresh reports success metadata');
inventory_value_availability_check(($componentCall['handler'] ?? '') === 'handle_component_change' && ($componentCall['id'] ?? 0) === 41, 'component context dispatches handle_component_change');
inventory_value_availability_check(($componentCall['context']['event_id'] ?? 0) === 701 && ($componentCall['context']['actor_user_id'] ?? 0) === 19, 'refresh forwards reconciliation and actor metadata');
inventory_value_availability_check(
    ($componentCall['context']['trigger_context'] ?? '') === 'INVENTORY_VALUE_RECONCILIATION'
        && ($componentCall['context']['event_source'] ?? '') === 'STOCK_VALUE_REVALUATION'
        && ($componentCall['context']['event_table'] ?? '') === 'inv_stock_value_reconciliation',
    'post refresh forwards trigger_context, event_source, and event_table'
);
inventory_value_availability_check(array_key_exists('warning', $component) && $component['warning'] === null, 'successful refresh has an explicit null warning');
inventory_value_availability_check(($component['identity_type'] ?? '') === 'component' && ($component['identity_id'] ?? 0) === 41, 'component refresh exposes identity metadata');

$item = inventory_value_availability_refresh(
    $service,
    ['stock_domain' => 'MATERIAL', 'material_id' => 0, 'item_id' => 51],
    'STOCK_VALUE_REVALUATION',
    705,
    21
);
$itemCall = $inventoryValueAvailabilityFakeCi->posavailabilityrebuildservice->calls[1] ?? [];
inventory_value_availability_check(($item['ok'] ?? false) === true && ($item['status'] ?? '') === 'SUCCESS', 'item-only material refresh reports success metadata');
inventory_value_availability_check(($itemCall['handler'] ?? '') === 'handle_item_change' && ($itemCall['id'] ?? 0) === 51, 'item-only material context dispatches handle_item_change');
inventory_value_availability_check(($item['identity_type'] ?? '') === 'item' && ($item['identity_id'] ?? 0) === 51 && ($item['target_id'] ?? 0) === 51, 'item refresh exposes identity metadata and preserves target_id');

$inventoryValueAvailabilityFakeCi->posavailabilityrebuildservice->materialResult = ['ok' => false, 'message' => 'queue unavailable'];
$materialFailure = inventory_value_availability_refresh(
    $service,
    ['stock_domain' => 'MATERIAL', 'material_id' => 52],
    'STOCK_VALUE_REVALUATION_VOID',
    702,
    20
);
$materialCall = $inventoryValueAvailabilityFakeCi->posavailabilityrebuildservice->calls[2] ?? [];
inventory_value_availability_check(($materialCall['handler'] ?? '') === 'handle_material_change' && ($materialCall['id'] ?? 0) === 52, 'material context dispatches handle_material_change');
inventory_value_availability_check(
    ($materialCall['context']['trigger_context'] ?? '') === 'INVENTORY_VALUE_RECONCILIATION'
        && ($materialCall['context']['event_source'] ?? '') === 'STOCK_VALUE_REVALUATION_VOID'
        && ($materialCall['context']['event_table'] ?? '') === 'inv_stock_value_reconciliation',
    'void refresh forwards trigger_context, event_source, and event_table'
);
inventory_value_availability_check(($materialFailure['ok'] ?? true) === false && ($materialFailure['status'] ?? '') === 'FAILED', 'failed cache result remains refresh metadata, not an exception');
inventory_value_availability_check(strpos((string)($materialFailure['warning'] ?? ''), 'sudah tersimpan') !== false, 'failed cache result clearly says the correction remains committed');

$inventoryValueAvailabilityFakeCi->posavailabilityrebuildservice->throwOnMaterial = true;
$thrownFailure = inventory_value_availability_refresh(
    $service,
    ['stock_domain' => 'MATERIAL', 'material_id' => 53],
    'STOCK_VALUE_REVALUATION',
    703,
    0
);
inventory_value_availability_check(($thrownFailure['status'] ?? '') === 'FAILED' && strpos((string)($thrownFailure['warning'] ?? ''), 'simulated cache outage') !== false, 'cache exception is isolated and returned as warning metadata');

$skipped = inventory_value_availability_refresh(
    $service,
    ['stock_domain' => 'MATERIAL', 'material_id' => 0],
    'STOCK_VALUE_REVALUATION',
    704,
    20
);
inventory_value_availability_check(($skipped['status'] ?? '') === 'SKIPPED' && !empty($skipped['warning']), 'missing cache identity is explicit and non-throwing');

$source = file_get_contents(dirname(__DIR__, 2) . '/application/libraries/InventoryValueReconciliationService.php');
$reflection = new ReflectionClass(InventoryValueReconciliationService::class);
foreach (['post', 'voidRecord'] as $methodName) {
    $method = $reflection->getMethod($methodName);
    $lines = explode("\n", (string)$source);
    $body = implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    $commitPosition = strpos($body, '$db->trans_commit();');
    $refreshPosition = strpos($body, '$this->refreshAvailabilityAfterCommit(');
    $transactionCatchPosition = strpos($body, '} catch (Throwable $e) {');
    inventory_value_availability_check($commitPosition !== false && $refreshPosition !== false && $commitPosition < $refreshPosition, $methodName . ' refreshes availability only after commit');
    inventory_value_availability_check($transactionCatchPosition !== false && $transactionCatchPosition < $refreshPosition, $methodName . ' isolates refresh outside the transaction catch/rollback path');
    inventory_value_availability_check(strpos($body, "'availability_refresh' => \$availabilityRefresh") !== false && strpos($body, "'warning' => \$availabilityRefresh['warning']") !== false, $methodName . ' returns availability_refresh and warning metadata');
}

inventory_value_availability_check(
    $inventoryValueAvailabilityFakeCi->load->calls === [
        'PosAvailabilityRebuildService',
        'PosAvailabilityRebuildService',
        'PosAvailabilityRebuildService',
        'PosAvailabilityRebuildService',
    ],
    'only refreshes with a valid target identity load the availability service'
);
inventory_value_availability_check(count($inventoryValueAvailabilityLogs) === 3, 'failed, thrown, and skipped refreshes are logged for follow-up');

if ($inventoryValueAvailabilityFailures !== []) {
    foreach ($inventoryValueAvailabilityFailures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PASS: inventory value reconciliation availability smoke (' . $inventoryValueAvailabilityChecks . ' checks)' . PHP_EOL;
