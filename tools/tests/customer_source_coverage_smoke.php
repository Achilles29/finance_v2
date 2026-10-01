<?php
declare(strict_types=1);

/** Runtime dependencies of the procurement/updater batch. No database, browser or network. */
require_once dirname(__DIR__).'/release/CustomerReleaseProfile.php';

function financeCustomerBatchFiles(): array
{
    return [
        'application/config/feature_access.php', 'application/config/routes.php',
        'application/controllers/Procurement.php', 'application/controllers/Purchase.php',
        'application/libraries/Procurement_stock_review.php', 'application/models/Procurement_model.php',
        'application/views/procurement/_stock_review_panel.php', 'application/views/procurement/_stock_review_history.php',
        'application/views/procurement/_current_stock.php', 'application/views/procurement/_manual_stock_toolbar.php',
        'application/views/procurement/division_po_sr.php', 'application/views/procurement/division_po_sr_detail.php',
        'application/views/procurement/division_po_sr_form.php', 'application/views/procurement/division_po_sr_print.php',
        'application/views/procurement/store_request_detail.php', 'application/views/procurement/store_request_form.php',
        'application/views/purchase/order_create.php', 'application/views/purchase/order_detail.php',
        'assets/js/procurement-stock-review.js', 'assets/js/procurement-current-stock.js',
        'tools/update/UpdateAuthorization.php', 'tools/update/UpdatePreflight.php',
        'tools/update/UpdateJournal.php', 'tools/update/preflight.php',
        'tools/db/migration_catalog.json', 'tools/db/ManagedMigrationProof.php', 'tools/db/managed_migration_proofs.json',
        'sql/2026-09-16a_procurement_stock_review.sql', 'application/libraries/Feature_policy.php',
    ];
}

/** Compare actual TAR inventory against the exact source snapshot, not just matching file names. */
function financeCustomerBatchInventory(array $inventory, array $expected): void
{
    $seen = [];
    foreach ($inventory as $entry) {
        if (!is_array($entry) || !is_string($entry['path'] ?? null) || isset($seen[$entry['path']])) {
            throw new RuntimeException('CUSTOMER_BATCH_INVENTORY_INVALID');
        }
        $seen[$entry['path']] = $entry['sha256'] ?? '';
    }
    foreach ($expected as $source => $hash) {
        $target = CustomerLayout::target($source);
        if (($seen[$target] ?? '') !== $hash) throw new RuntimeException('CUSTOMER_BATCH_MISSING_OR_STALE:'.$source);
    }
}

if (defined('FINANCE_CUSTOMER_COVERAGE_LIBRARY_ONLY')) return;
$root = dirname(__DIR__, 2);
$profile = CustomerReleaseProfile::fromRoot($root);
$base = ReleasePackagePolicy::fromFile($root.'/tools/release/package_policy.json');
$checks = 0;
$check = static function (bool $ok, string $reason) use (&$checks): void {
    if (!$ok) throw new RuntimeException($reason);
    $checks++;
};
$hashes = []; $inventory = [];
foreach (financeCustomerBatchFiles() as $path) {
    $absolute = $root.'/'.$path;
    $check(ReleasePackagePolicy::absoluteFileProblem($absolute) === null && realpath($absolute) === $absolute, 'Unsafe or absent source: '.$path);
    $check($base->included($path) && !$base->denied($path) && $profile->allows($path), 'Missing from customer profile: '.$path);
    $hashes[$path] = hash_file('sha256', $root.'/'.$path);
    $inventory[] = ['path'=>CustomerLayout::target($path), 'sha256'=>$hashes[$path]];
}
$app = json_decode(file_get_contents($root.'/app-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
$matches = array_values(array_filter($app['packaging']['profiles'] ?? [], static fn(array $p): bool => ($p['code'] ?? '') === 'CUSTOMER_CLEAN'));
$check(count($matches) === 1 && ($matches[0]['rules_sha256'] ?? '') === $profile->digest(), 'Manifest/profile binding');
financeCustomerBatchInventory($inventory, $hashes); $checks++;
$reject = static function (array $entries, string $reason) use ($hashes, $check): void {
    try { financeCustomerBatchInventory($entries, $hashes); }
    catch (RuntimeException $e) { $check(str_starts_with($e->getMessage(), 'CUSTOMER_BATCH_'), $reason); return; }
    throw new RuntimeException('Not rejected: '.$reason);
};
$reject(array_slice($inventory, 1), 'Omitted runtime rejected');
$stale = $inventory; $stale[5]['sha256'] = str_repeat('0', 64);
$reject($stale, 'Old procurement model rejected despite identical version label');
$wrongAsset = $inventory; $wrongAsset[19]['path'] = 'assets/js/procurement-current-stock.js';
$reject($wrongAsset, 'Wrong public asset layout rejected');
$reject(array_merge($inventory, [$inventory[0]]), 'Duplicate archive member rejected');
$withoutJournal = array_values(array_filter($inventory, static fn(array $e): bool => $e['path'] !== 'tools/update/UpdateJournal.php'));
$reject($withoutJournal, 'Untracked journal omitted from snapshot rejected');
$check(!$profile->allows('config/customer.json') && !$profile->allows('.git/config') && !$profile->allows('private/identity.json'), 'Customer secrets remain excluded');
echo json_encode(['status'=>'PASS','checks'=>$checks,'runtime_files'=>count($hashes),'kind'=>'SOURCE_COVERAGE_NOT_BUILD_OR_UPDATE_EVIDENCE'], JSON_UNESCAPED_SLASHES)."\n";
