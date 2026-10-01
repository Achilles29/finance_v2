<?php

declare(strict_types=1);

/** A4.3 DB/bootstrap/network-free source contract for the asset lifecycle. */
$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];

function a4AssetCheck(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }
}

function a4AssetMethod(string $source, string $method): string
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $name = '';
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                break;
            }
            if ($tokens[$j] === '(') {
                break;
            }
        }
        if ($name !== $method) {
            continue;
        }
        $out = '';
        $depth = 0;
        $started = false;
        for ($j = $i; $j < $count; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $out .= $text;
            if ($text === '{') {
                $started = true;
                $depth++;
            } elseif ($text === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    return $out;
                }
            }
        }
    }
    return '';
}

function a4AssetContains(string $source, array $needles, string $label): void
{
    foreach ($needles as $needle) {
        a4AssetCheck(strpos($source, $needle) !== false, $label . ' contains ' . $needle);
    }
}

$controllerPath = $root . '/application/controllers/Assets.php';
$modelPath = $root . '/application/models/Asset_model.php';
$controller = @file_get_contents($controllerPath);
$model = @file_get_contents($modelPath);
a4AssetCheck(is_string($controller), 'Assets controller is readable');
a4AssetCheck(is_string($model), 'Asset model is readable');
$controller = is_string($controller) ? $controller : '';
$model = is_string($model) ? $model : '';

// Every public lifecycle surface maps to a server-side permission.
$permissionContracts = [
    'index' => ['PAGE_ITEM', "'view'"],
    'store' => ['PAGE_ITEM', "'create'"],
    'update' => ['PAGE_ITEM', "'edit'"],
    'delete' => ['PAGE_ITEM', "'delete'"],
    'lock_bulk' => ['PAGE_ITEM', "'edit'"],
    'lock_asset' => ['PAGE_ITEM', "'edit'"],
    'changes' => ['PAGE_MASTER_CHANGE', "'view'"],
    'change_store' => ['PAGE_MASTER_CHANGE', "'create'"],
    'damage_store' => ['PAGE_DAMAGE', "'create'"],
    'damage_update' => ['PAGE_DAMAGE', "'edit'"],
    'damage_delete' => ['PAGE_DAMAGE', "'delete'"],
    'recon_generate' => ['PAGE_RECON', "'create'"],
    'recon_save' => ['PAGE_RECON', "'edit'"],
    'recon_post' => ['PAGE_RECON', "'edit'"],
    'recon_cancel' => ['PAGE_RECON', "'delete'"],
    'depreciation_generate' => ['PAGE_DEPRECIATION', "'create'"],
    'depreciation_post' => ['PAGE_DEPRECIATION', "'edit'"],
    'depreciation_cancel' => ['PAGE_DEPRECIATION', "'delete'"],
];
foreach ($permissionContracts as $method => [$page, $action]) {
    $block = a4AssetMethod($controller, $method);
    a4AssetCheck($block !== '', 'asset controller method exists: ' . $method);
    a4AssetContains($block, ['require_permission(self::' . $page, $action], $method . ' permission');
}

// Generic transfer/handover/maintenance/disposal actions cannot bypass page/action RBAC.
$workflowAction = a4AssetMethod($controller, 'workflow_action');
a4AssetContains($workflowAction, [
    "\$permission = \$action === 'cancel' ? 'delete' : 'edit'",
    "require_permission((string)\$config['page_code'], \$permission)",
    "case 'approve'", "case 'reject'", "case 'cancel'", "case 'post'", "case 'complete'",
    "if (\$type !== 'MAINTENANCE')",
], 'generic workflow action RBAC');
$workflowStore = a4AssetMethod($controller, 'workflow_store');
a4AssetContains($workflowStore, [
    "require_permission((string)\$config['page_code'], 'create')",
    'active_division_id', 'di luar scope divisi',
    "\$type === 'TRANSFER'", "\$type === 'HANDOVER'", "\$type === 'DISPOSAL'",
], 'workflow create/scope contract');

// Entry, lock and approved master changes are transactional and auditable.
$transactionContracts = [
    'save_bulk' => ['trans_begin', "'event_type' => 'ACQUIRE'", 'trans_status', 'trans_rollback', 'trans_commit'],
    'update_asset' => ['asset_master_is_locked', 'trans_begin', "'event_type' => 'UPDATE'", 'trans_status', 'trans_rollback', 'trans_commit'],
    'delete_open_asset' => ['asset_for_update', 'asset_master_is_locked', 'asset_delete_block_reason', 'trans_begin', 'trans_rollback', 'trans_commit'],
    'adjust_group_quantity' => ['group_assets_for_update', 'asset_master_is_locked', 'trans_begin', 'trans_rollback', 'trans_commit'],
    'lock_assets' => ["master_lock_status' => 'LOCKED'", 'insert_event', 'trans_begin', 'trans_rollback', 'trans_commit'],
    'create_master_change_request' => ['asset_master_is_locked', 'trans_begin', 'trans_rollback', 'trans_commit'],
    'post_master_change_request' => ["'APPROVED'", 'trans_begin', 'insert_event', "'POSTED'", 'trans_rollback', 'trans_commit'],
];
foreach ($transactionContracts as $method => $needles) {
    $block = a4AssetMethod($model, $method);
    a4AssetCheck($block !== '', 'asset entry/lock/change method exists: ' . $method);
    a4AssetContains($block, $needles, $method . ' atomic lifecycle');
}

// Master-change transition methods enforce explicit state changes.
$changeContracts = [
    'approve_master_change_request' => ["'PENDING'", "'status' => 'APPROVED'"],
    'reject_master_change_request' => ["'PENDING'", "'status' => 'REJECTED'", 'reason'],
    'cancel_master_change_request' => ["'PENDING'", "'status' => 'CANCELLED'"],
    'post_master_change_request' => ["'APPROVED'", "'status' => 'POSTED'"],
];
foreach ($changeContracts as $method => $needles) {
    $block = a4AssetMethod($model, $method);
    a4AssetContains($block, $needles, $method . ' state transition');
}

// Damage/repair/lost/retire/dispose status vocabulary stays complete and creates events atomically.
$statusLabels = a4AssetMethod($model, 'status_labels');
$damageLabels = a4AssetMethod($model, 'damage_event_type_labels');
foreach (['ACTIVE', 'BROKEN', 'REPAIR', 'LOST', 'RETIRED', 'DISPOSED'] as $status) {
    a4AssetCheck(strpos($statusLabels, "'" . $status . "'") !== false, 'asset status exists: ' . $status);
}
foreach (['DAMAGE', 'REPAIR', 'LOST', 'RETIRED', 'DISPOSED'] as $event) {
    a4AssetCheck(strpos($damageLabels, "'" . $event . "'") !== false, 'damage event exists: ' . $event);
}
foreach (['record_damage', 'update_damage_report', 'delete_damage_report'] as $method) {
    $block = a4AssetMethod($model, $method);
    a4AssetCheck($block !== '', 'damage lifecycle method exists: ' . $method);
    a4AssetContains($block, ['trans_begin', 'trans_status', 'trans_rollback', 'trans_commit'], $method . ' transaction');
}
a4AssetContains(a4AssetMethod($model, 'damage_event_type_from_status'), ['LOST', 'REPAIR', 'RETIRED', 'DISPOSED', 'DAMAGE'], 'damage status/event mapping');

// Operational workflows have complete state gates and atomic posting/completion.
$workflowConfigs = a4AssetMethod($model, 'workflow_configs');
foreach (['TRANSFER', 'HANDOVER', 'MAINTENANCE', 'DISPOSAL'] as $type) {
    a4AssetCheck(strpos($workflowConfigs, "'" . $type . "'") !== false, 'workflow configured: ' . $type);
}
$workflowStatuses = a4AssetMethod($model, 'workflow_status_labels');
foreach (['PENDING', 'APPROVED', 'REJECTED', 'POSTED', 'DONE', 'CANCELLED'] as $status) {
    a4AssetCheck(strpos($workflowStatuses, "'" . $status . "'") !== false, 'workflow status exists: ' . $status);
}
$workflowLifecycle = [
    'create_workflow' => ["'status' => 'PENDING'", 'requested_by'],
    'approve_workflow' => ["!== 'PENDING'", "'status' => 'APPROVED'", 'approved_by'],
    'reject_workflow' => ["['PENDING', 'APPROVED']", "'status' => 'REJECTED'"],
    'cancel_workflow' => ["['POSTED', 'DONE', 'CANCELLED']", "'status' => 'CANCELLED'"],
    'post_workflow' => ["!== 'APPROVED'", 'trans_begin', 'insert_event', "'status' => 'POSTED'", 'trans_rollback', 'trans_commit'],
    'complete_maintenance' => ["['PENDING', 'APPROVED']", 'trans_begin', "'status' => 'DONE'", "'event_type' => 'REPAIR'", 'trans_rollback', 'trans_commit'],
];
foreach ($workflowLifecycle as $method => $needles) {
    $block = a4AssetMethod($model, $method);
    a4AssetCheck($block !== '', 'asset workflow method exists: ' . $method);
    a4AssetContains($block, $needles, $method . ' lifecycle');
}

// Reconciliation and depreciation preserve draft/post/cancel barriers and audit actor/time.
$closingContracts = [
    'generate_recon' => ['trans_begin', "'status' => 'DRAFT'", 'trans_rollback', 'trans_commit'],
    'save_recon_lines' => ["!== 'DRAFT'", 'trans_begin', 'checked_by', 'trans_rollback', 'trans_commit'],
    'post_recon' => ["!== 'DRAFT'", 'trans_begin', "'event_type' => \$physical === 'MISSING' ? 'LOST' : 'RECON'", "'status' => 'POSTED'", 'posted_by', 'trans_rollback', 'trans_commit'],
    'cancel_recon' => ["!== 'DRAFT'", "'status' => 'CANCELLED'"],
    'create_depreciation_run' => ['depreciation_preview', 'depreciation_summary', 'trans_begin', "'status' => 'DRAFT'", 'trans_rollback', 'trans_commit'],
    'post_depreciation_run' => ["!== 'DRAFT'", "'status' => 'POSTED'", 'posted_by', 'posted_at'],
    'cancel_depreciation_run' => ["!== 'DRAFT'", "'status' => 'CANCELLED'"],
];
foreach ($closingContracts as $method => $needles) {
    $block = a4AssetMethod($model, $method);
    a4AssetCheck($block !== '', 'recon/depreciation method exists: ' . $method);
    a4AssetContains($block, $needles, $method . ' closing contract');
}

// Asset detail and event history form the minimum audit trail read surface.
a4AssetContains(a4AssetMethod($controller, 'detail'), ["require_permission(self::PAGE_ITEM, 'view')", 'asset_events'], 'asset audit detail');
a4AssetContains(a4AssetMethod($model, 'asset_events'), ['assetId', 'event_date', 'id'], 'asset event audit ordering');
a4AssetContains(a4AssetMethod($controller, 'ensure_asset_in_scope'), ['active_division_id', 'division_id', 'di luar scope'], 'asset division scope');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A4.3 asset lifecycle contract check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'A4.3 asset lifecycle contract smoke passed (' . $checks . ' checks; DB/bootstrap/network-free).' . PHP_EOL;
