<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = 0; $failures = [];
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) { $failures[] = $message; fwrite(STDERR, "FAIL: {$message}\n"); return; }
    echo "PASS: {$message}\n";
};
$manifest = json_decode((string)file_get_contents($root . '/app-manifest.json'), true);
$catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
$baseline = json_decode((string)file_get_contents($root . '/tools/db/clean_install_baseline_policy.json'), true);
$migrationPath = $root . '/sql/2026-09-07a_c2_c4_business_profile_license_runtime_foundation.sql';
$profileController = (string)file_get_contents($root . '/application/controllers/Business_profile.php');
$profileModel = (string)file_get_contents($root . '/application/models/Business_profile_model.php');
$profileView = (string)file_get_contents($root . '/application/views/system/business_profile.php');
$licenseController = (string)file_get_contents($root . '/application/controllers/License.php');
$licenseModel = (string)file_get_contents($root . '/application/models/License_runtime_model.php');
$gate = (string)file_get_contents($root . '/application/libraries/Feature_gate.php');
$printer = (string)file_get_contents($root . '/application/models/Pos_print_model.php');
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$shell = (string)file_get_contents($root . '/application/core/MY_Controller.php')
    . (string)file_get_contents($root . '/application/views/layout/header.php')
    . (string)file_get_contents($root . '/application/views/layout/main.php')
    . (string)file_get_contents($root . '/application/views/layout/sidebar.php')
    . (string)file_get_contents($root . '/application/views/auth/login.php');
$sidebarCss = (string)file_get_contents($root . '/assets/css/theme-custom.css');
$publicBranding = (string)file_get_contents($root . '/application/views/pos/customer_review_form.php')
    . (string)file_get_contents($root . '/application/views/pos/customer_review_station_form.php')
    . (string)file_get_contents($root . '/application/views/pos/customer_review_station_print.php')
    . (string)file_get_contents($root . '/application/views/assets/labels.php')
    . (string)file_get_contents($root . '/application/views/hr_contract/print.php');

$entry = null;
foreach ((array)($catalog['migrations'] ?? []) as $item) if (($item['id'] ?? '') === '2026-09-07a-c2-c4-business-profile-license-runtime-foundation') $entry = $item;
$check(is_array($manifest) && ($manifest['version'] ?? '') === '0.1.0-alpha.23' && ($manifest['schema_version'] ?? '') === 'finance-20260907', 'commercial source advances without inventing a schema migration');
$check(is_array($entry) && ($entry['path'] ?? '') === 'sql/2026-09-07a_c2_c4_business_profile_license_runtime_foundation.sql' && hash_file('sha256', $migrationPath) === ($entry['sha256'] ?? '') && ($entry['dependencies'] ?? []) === ['2026-09-06i-a3-sidebar-task-oriented-layout'], 'C2/C4 migration is cataloged with exact checksum and ordered dependency');
$check(strpos((string)file_get_contents($migrationPath), 'CREATE TABLE IF NOT EXISTS `sys_business_profile`') !== false && strpos((string)file_get_contents($migrationPath), 'CREATE TABLE IF NOT EXISTS `lic_license_cache`') !== false && strpos((string)file_get_contents($migrationPath), "'system.business_profile'") !== false && strpos((string)file_get_contents($migrationPath), "'system.license.index'") !== false, 'migration creates profile/licensing metadata plus RBAC navigation only');
$required = ['sys_business_profile','sys_business_profile_audit','lic_installation','lic_license_cache','lic_feature','lic_feature_cache','lic_device_activation','lic_activation_audit','lic_runtime_audit'];
$check(($baseline['schema']['table_count'] ?? null) === 296 && array_diff($required, (array)($baseline['schema']['required_tables'] ?? [])) === [] && in_array('2026-09-07a-c2-c4-business-profile-license-runtime-foundation', (array)($baseline['post_baseline_migrations'] ?? []), true), 'clean-install baseline includes every C2/C4 table and migration');
$check(strpos($profileController, "require_permission(self::PAGE, 'edit')") !== false && strpos($profileController, 'require_csrf()') !== false && strpos($profileController, 'store_logo_upload()') !== false && strpos($profileModel, 'trans_begin()') !== false && strpos($profileModel, 'sys_business_profile_audit') !== false, 'business profile writer has RBAC, CSRF, validated upload, transaction, and append-only audit');
$check(strpos($profileView, 'business-profile-logo-preview') !== false && strpos($profileView, 'FileReader') !== false && strpos($profileView, 'pos/printers/general') !== false, 'business profile UI previews selected logo and makes printer override discoverable');
$check(strpos($routes, "system/business-profile") !== false && strpos($routes, "system/license") !== false && strpos($printer, 'business_profile_fallback') !== false && strpos($printer, 'business-profile-logo') !== false, 'routes and printer fallback accept the centralized customer logo without overwriting printer overrides');
$check(strpos($shell, 'business_profile()') !== false && strpos($shell, 'business_profile') !== false && strpos($shell, 'Finance Workspace') === false && strpos($shell, 'Finance App</strong>') === false, 'application shell and login consume the local business profile instead of a legacy customer identity');
$check(strpos($publicBranding, 'NAMUA Coffee & Eatery') === false && strpos($publicBranding, 'NAMUA ASSET') === false && strpos($publicBranding, 'NAMUA COFFEE AND EATERY') === false && strpos($printer, "'document_footer'") !== false, 'customer-facing QR, asset/contract documents, and print fallback use the profile without overwriting explicit outlet settings');
$check(strpos($profileView, 'Setup awal admin') !== false && strpos($profileView, 'setup-identitas') !== false && strpos($profileView, 'setup-lokalitas') !== false && strpos($profileView, 'setup-dokumen') !== false, 'admin onboarding exposes an explicit three-step UI with one audited save action');
$check(strpos($sidebarCss, 'data-sidebar-depth="3"') !== false && strpos($profileView, 'business-setup-grid') !== false, 'level-four sidebar navigation has compact depth-aware styling and onboarding remains responsive');
$check(strpos($licenseController, "require_permission(self::PAGE, 'view')") !== false && strpos($licenseController, 'Feature_gate') !== false && strpos($licenseModel, "verification_status'] ?? '') !== 'VERIFIED'") !== false, 'license status is RBAC-gated and only a verified cache can be entitled');
$check(strpos($gate, "return 'AUDIT_ONLY'") !== false && strpos($gate, "['allowed'=>true, 'enforced'=>false, 'mode'=>'AUDIT_ONLY'") !== false && strpos($gate, 'record_decision') !== false, 'FeatureGate defaults to audit-only and records privacy-minimal decisions instead of blocking staging');
$check(is_file($root . '/tools/release/control_center_release_preflight.php') && is_file($root . '/tools/install/finance_install_plan.php'), 'C3 exposes read-only release preflight and non-mutating installer plan tools');

if ($failures !== []) { fwrite(STDERR, count($failures) . " C2/C4 commercial foundation check(s) failed.\n"); exit(1); }
echo "All {$checks} C2/C4 commercial foundation checks passed.\n";
