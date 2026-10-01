<?php
declare(strict_types=1);

// Source contract only: validates the concurrency/audit boundary without
// loading CodeIgniter, a database, production data, or POS Mobile.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$model = (string)file_get_contents($root . '/application/models/Production_model.php');
$view = (string)file_get_contents($root . '/application/views/production/component_formula_edit.php');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$block = static function (string $source, string $method): string {
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $name = '';
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; break; }
            if ($tokens[$j] === '(') break;
        }
        if ($name !== $method) continue;
        $depth = 0; $opened = false; $result = '';
        for ($j = $i; $j < count($tokens); $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $result .= $text;
            if ($text === '{') { $opened = true; $depth++; }
            if ($text === '}' && $opened && --$depth === 0) return $result;
        }
    }
    return '';
};
$ordered = static function (string $source, array $needles): bool {
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle, $at + 1);
        if ($next === false) return false;
        $at = $next;
    }
    return true;
};

$check(strpos($controller, "COMPONENT_FORMULA_REVISION_FIELD = 'component_formula_revision'") !== false, 'controller declares an isolated formula revision field');
$edit = $block($controller, 'component_formula_edit');
$check($ordered($edit, ['component_formula_detail(', "'component_formula_revision' =>", 'component_formula_revision(']), 'formula editor renders revision from authoritative formula rows');
$check(strpos($view, 'const componentFormulaRevision') !== false && strpos($view, 'component_formula_revision: componentFormulaRevision') !== false, 'formula editor posts the rendered revision with bulk save');

$save = $block($controller, 'component_formula_save_bulk');
$check($ordered($save, ['require_permission(', 'require_component_formula_mutation_csrf()', 'request_payload()', 'COMPONENT_FORMULA_REVISION_FIELD', 'save_component_formula_bulk(']), 'bulk endpoint validates RBAC/CSRF/payload/revision before model write');
$check(strpos($save, "'actor_user_id'") !== false && strpos($save, "'source_ip'") !== false, 'bulk endpoint sends accountable actor context to the model');

$bulk = $block($model, 'save_component_formula_bulk');
$check($ordered($bulk, ['component_formula_audit_ready(', 'component_formula_versioning_ready(', 'trans_begin(', 'lock_component_formula_parent(', 'component_formula_revision_rows(', 'hash_equals(', 'ensure_component_formula_baseline_version(', "delete('mst_component_formula')", 'write_component_formula_version(', 'write_component_formula_bulk_audit(', 'trans_commit(']), 'bulk replacement checks audit/version readiness, locks parent/rows, detects stale revisions, snapshots immutable versions, and audits before commit');
$check(strpos($bulk, 'telah berubah oleh pengguna lain') !== false && strpos($bulk, 'trans_rollback()') !== false, 'stale formula write rolls back with a reloadable conflict message');

$revision = $block($model, 'canonical_component_formula_revision');
$check(strpos($revision, "hash('sha256'") !== false && strpos($revision, 'material_item_id') !== false && strpos($revision, 'source_division_id') !== false && strpos($revision, 'sort_order') !== false, 'revision covers formula identity, source, quantity, notes, and ordering');
$lock = $block($model, 'component_formula_revision_rows');
$check(strpos($lock, 'mst_component_formula') !== false && strpos($lock, 'FOR UPDATE') !== false, 'authoritative formula rows can be locked for the revision check');
$parentLock = $block($model, 'lock_component_formula_parent');
$check(strpos($parentLock, 'mst_component') !== false && strpos($parentLock, 'FOR UPDATE') !== false, 'parent component lock serializes empty formula sets too');
$auditReady = $block($model, 'component_formula_audit_ready');
$check(strpos($auditReady, "table_exists('aud_transaction_log')") !== false && strpos($auditReady, "'before_payload'") !== false, 'replacement fails closed if audit schema is unavailable');
$audit = $block($model, 'write_component_formula_bulk_audit');
$check(strpos($audit, "'REPLACE_COMPONENT_FORMULA'") !== false && strpos($audit, "'before_payload'") !== false && strpos($audit, "'after_payload'") !== false, 'replacement records atomic before/after formula snapshots');
$versionReady = $block($model, 'component_formula_versioning_ready');
$check(strpos($versionReady, "mst_component_formula_version") !== false && strpos($versionReady, "mst_component_formula_version_line") !== false, 'replacement fails closed when immutable formula history schema is unavailable');
$check(strpos($controller, 'Pos_mobile') === false && strpos($model, 'Pos_mobile') === false, 'formula hardening does not alter POS Mobile/APK contracts');

echo 'PASS production-component-formula-revision-audit checks=' . $checks . PHP_EOL;
