<?php
declare(strict_types=1);

// Source contract only: no product, recipe, audit record, or database row is
// loaded or changed by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$view = (string)file_get_contents($root . '/application/views/master/relation_product_recipe_edit.php');
$singleView = (string)file_get_contents($root . '/application/views/master/relation_form.php');
$listView = (string)file_get_contents($root . '/application/views/master/relation_list.php');
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

$check(strpos($controller, "PRODUCT_RECIPE_REVISION_FIELD = 'product_recipe_revision'") !== false, 'recipe editor declares an isolated optimistic-concurrency field');
$render = $block($controller, 'product_recipe_bulk_edit');
$check($ordered($render, ['loadProductRecipeData(', "'product_recipe_revision' =>", 'canonicalProductRecipeRevision(']), 'bulk editor renders a revision derived from current recipe rows');
$check(strpos($view, 'name="product_recipe_revision"') !== false, 'bulk recipe form posts exactly its recipe revision');

$save = $block($controller, 'product_recipe_bulk_save');
$check($ordered($save, ['requireRelationPermission(', 'requireProductRecipeMutationCsrf()', 'loadProductRecipeParent(', 'PRODUCT_RECIPE_REVISION_FIELD', 'normalizeProductRecipeBulkLines(', 'beginProductRecipeAuditTransaction(', 'lockProductRecipeParentForRevision(', 'lockProductRecipeRowsForRevision(', 'hash_equals(', "delete('mst_product_recipe')", 'writeProductRecipeAudit(', 'trans_commit(']), 'bulk save validates RBAC/CSRF/revision, locks authoritative product/rows, then audits replacement before commit');
$check(strpos($save, 'trans_rollback()') !== false && strpos($save, 'telah berubah oleh pengguna lain') !== false, 'stale bulk editor is rolled back and receives a conflict message');

$revision = $block($controller, 'canonicalProductRecipeRevision');
$check(strpos($revision, "hash('sha256'") !== false && strpos($revision, 'ingredient_role') !== false && strpos($revision, 'sort_order') !== false, 'revision covers identity, quantity, source, role, notes, and ordering');
$lock = $block($controller, 'lockProductRecipeRowsForRevision');
$check(strpos($lock, 'FOR UPDATE') !== false && strpos($lock, 'mst_product_recipe') !== false, 'writer locks the authoritative recipe rows inside its transaction');
$parentLock = $block($controller, 'lockProductRecipeParentForRevision');
$check(strpos($parentLock, 'FOR UPDATE') !== false && strpos($parentLock, 'mst_product') !== false, 'writer also locks the parent product so empty recipe sets cannot bypass serialization');
$auditStart = $block($controller, 'beginProductRecipeAuditTransaction');
$check(strpos($auditStart, "table_exists('aud_transaction_log')") !== false && strpos($auditStart, 'trans_begin()') !== false, 'bulk replacement fails closed when audit storage or transaction is unavailable');
$audit = $block($controller, 'writeProductRecipeAudit');
$check(strpos($audit, "'REPLACE_PRODUCT_RECIPE'") !== false && strpos($audit, "'before_payload'") !== false && strpos($audit, "'after_payload'") !== false, 'replacement writes atomic before/after audit payloads');

foreach (['product_recipe_store', 'product_recipe_update', 'product_recipe_delete'] as $writer) {
    $source = $block($controller, $writer);
    $check(
        strpos($source, 'PRODUCT_RECIPE_REVISION_FIELD') !== false
            && strpos($source, 'commitProductRecipeLineMutation(') !== false,
        $writer . ' requires a recipe snapshot and uses the audited line-mutation transaction'
    );
}
$check(strpos($singleView, 'name="product_recipe_revision"') !== false, 'individual create/edit form posts its recipe snapshot');
$check(strpos($listView, 'name="product_recipe_revision"') !== false, 'individual delete form posts its recipe snapshot');
$lineMutation = $block($controller, 'commitProductRecipeLineMutation');
$check(
    $ordered($lineMutation, [
        'beginProductRecipeAuditTransaction(',
        'lockProductRecipeParentForRevision(',
        'lockProductRecipeRowsForRevision(',
        'hash_equals(',
        "'CREATE_PRODUCT_RECIPE_LINE'",
        "'UPDATE_PRODUCT_RECIPE_LINE'",
        "'DELETE_PRODUCT_RECIPE_LINE'",
        'writeProductRecipeAudit(',
        'trans_commit(',
    ]),
    'individual recipe writes lock the full recipe snapshot and audit create/update/delete before commit'
);
$check(
    strpos($lineMutation, 'trans_rollback()') !== false && strpos($lineMutation, "'reason' => 'conflict'") !== false,
    'individual recipe writes roll back stale snapshots rather than overwrite another editor'
);
$check(strpos($controller, 'Pos_mobile') === false, 'recipe concurrency hardening does not alter POS Mobile/APK contracts');

echo 'PASS master-relation-product-recipe-revision-audit checks=' . $checks . PHP_EOL;
