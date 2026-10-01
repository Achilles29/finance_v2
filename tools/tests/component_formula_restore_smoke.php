<?php
declare(strict_types=1);

// Source contract only: proves the protected restore flow without loading CI,
// connecting to a database, or reading/writing business data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$model = (string)file_get_contents($root . '/application/models/Production_model.php');
$view = (string)file_get_contents($root . '/application/views/production/component_formula_detail.php');
$service = (string)file_get_contents($root . '/application/libraries/SensitiveActionStepUp.php');
$migration = (string)file_get_contents($root . '/sql/2026-09-06b_component_formula_restore_action.sql');
$baseline = (string)file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
$catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) $failures[] = $message;
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
    $offset = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle, $offset + 1);
        if ($next === false) return false;
        $offset = $next;
    }
    return true;
};

$check(strpos($routes, "\$route['production/component-formulas/restore-step-up/verify'] = 'production/component_formula_restore_step_up_verify';") !== false, 'formula restore proof route is registered');
$check(strpos($routes, "\$route['production/component-formulas/restore'] = 'production/component_formula_restore';") !== false, 'formula restore writer route is registered');
$check(strpos($service, "'COMPONENT_FORMULA_RESTORE'") !== false, 'one-use proof service explicitly permits formula restore');

$show = $block($controller, 'component_formula_show');
$check($ordered($show, ["require_permission('production.component.formula.index', 'view')", "can('production.component.formula.index', 'edit')", "'can_restore'", "'production_component_formula_mutation_csrf'", "'component_formula_revision'"]), 'detail view gives restore capability and mutation token only after its normal view authorization');
$verify = $block($controller, 'component_formula_restore_step_up_verify');
$check($ordered($verify, ["require_permission('production.component.formula.index', 'edit')", 'require_component_formula_mutation_csrf()', '$this->request_payload()', '->issue(', "'COMPONENT_FORMULA_RESTORE'", '$this->json_ok(']), 'proof issuance requires edit RBAC and scoped CSRF before password verification');
$restore = $block($controller, 'component_formula_restore');
$check($ordered($restore, ["require_permission('production.component.formula.index', 'edit')", 'require_component_formula_mutation_csrf()', '$this->request_payload()', 'COMPONENT_FORMULA_REVISION_FIELD', 'consume_component_formula_restore_step_up(', "unset(\$payload['step_up_proof'])", 'release_session_lock()', 'restore_component_formula_version(']), 'restore consumes a version-bound proof before releasing the session and reaching the model');
$check(strpos($restore, "\$payload['password']") === false, 'formula restore writer never accepts a password payload');
$consume = $block($controller, 'consume_component_formula_restore_step_up');
$check($ordered($consume, ["'COMPONENT_FORMULA_RESTORE'", '$versionId', "\$payload['step_up_proof']", "['step_up_required' => true]"]), 'missing or invalid restore proof fails before the model');

$restoreModel = $block($model, 'restore_component_formula_version');
$check($ordered($restoreModel, ['trans_begin()', 'lock_component_formula_parent(', 'component_formula_revision_rows($componentId, true)', 'hash_equals(', 'component_formula_version_rows(', 'component_formula_restore_rows(', "delete('mst_component_formula')", "insert('mst_component_formula'", "'RESTORE'", 'write_component_formula_restore_audit(', 'trans_commit()']), 'restore model locks current formula, checks browser revision, replaces atomically, writes history and audit before commit');
$check(strpos($restoreModel, "'status' => 409") !== false && strpos($restoreModel, "'status' => 404") !== false, 'restore rejects stale current formula and a foreign/missing historical version');
$versionRows = $block($model, 'component_formula_version_rows');
$check(strpos($versionRows, 'WHERE id = ? AND component_id = ?') !== false && strpos($versionRows, 'formula_version_id = ?') !== false && strpos($versionRows, 'line_count') !== false, 'snapshot reader binds version to the requested component and rejects incomplete line sets');
$restoreRows = $block($model, 'component_formula_restore_rows');
$check(strpos($restoreRows, "['MATERIAL', 'COMPONENT']") !== false && strpos($restoreRows, '$qty <= 0') !== false && strpos($restoreRows, 'return null') !== false, 'historical lines are structurally validated before any live delete');
$writer = $block($model, 'write_component_formula_version');
$check(strpos($writer, "['BASELINE', 'REPLACE', 'RESTORE']") !== false, 'history writer records restore as its own immutable event');
$audit = $block($model, 'write_component_formula_restore_audit');
$check(strpos($audit, "'RESTORE_COMPONENT_FORMULA'") !== false && strpos($audit, "'mst_component_formula_version'") !== false && strpos($audit, "'before_payload'") !== false && strpos($audit, "'after_payload'") !== false, 'restore audit identifies the selected historical version and stores before/after payloads');

$check(strpos($view, 'componentFormulaRestoreModal') !== false && strpos($view, 'type="password" class="form-control" id="componentFormulaRestorePassword"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'detail UI has a masked restore confirmation modal');
$check($ordered($view, ['postJson(verifyUrl', 'formula_version_id: versionId', 'passwordInput.value = \'\';', 'postJson(restoreUrl', 'step_up_proof: verification.step_up_proof']), 'browser clears password and forwards only the one-use proof to restore');
$check(strpos($view, "'X-Production-Component-Formula-Csrf': csrfToken") !== false, 'restore browser requests use the existing scoped Formula CSRF header');

$check(strpos($migration, "MODIFY COLUMN `change_action` ENUM('BASELINE','REPLACE','RESTORE')") !== false, 'managed migration expands only the formula history action enum');
$check(strpos($baseline, "change_action` enum('BASELINE','REPLACE','RESTORE')") !== false, 'clean-install baseline already contains the restore action enum');
$entry = null;
foreach (($catalog['migrations'] ?? []) as $candidate) if (($candidate['id'] ?? '') === '2026-09-06b-component-formula-restore-action') $entry = $candidate;
$check(is_array($entry) && ($entry['path'] ?? '') === 'sql/2026-09-06b_component_formula_restore_action.sql' && ($entry['dependencies'] ?? []) === ['2026-09-06a-component-formula-version-history'] && hash_file('sha256', $root . '/sql/2026-09-06b_component_formula_restore_action.sql') === ($entry['sha256'] ?? ''), 'restore schema delta is checksum-bound after immutable history');
$check(strpos($controller, 'Pos_mobile') === false && strpos($model, 'Pos_mobile') === false && strpos($view, 'pos_mobile') === false, 'formula restore does not alter POS Mobile/APK contracts');

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    exit(1);
}
echo 'PASS component-formula-restore checks=' . $checks . PHP_EOL;
