<?php
declare(strict_types=1);

// Source contract only: this checks the Bundle writer boundary without loading
// CodeIgniter, a database, POS data, or POS Mobile/APK code.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$view = (string)file_get_contents($root . '/application/views/master/product_bundle_edit.php');
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

$check(strpos($controller, "PRODUCT_BUNDLE_REVISION_FIELD = 'product_bundle_revision'") !== false, 'Bundle editor declares an isolated optimistic-concurrency field');
$edit = $block($controller, 'product_bundle_edit');
$check($ordered($edit, ['loadProductBundle(', 'loadProductBundleLines(', "'product_bundle_revision' =>", 'canonicalProductBundleRevision(']), 'Bundle editor derives its revision from current header and lines');
$check(strpos($view, 'name="product_bundle_revision"') !== false, 'Bundle edit form posts its revision snapshot');

$update = $block($controller, 'product_bundle_update');
$check(
    $ordered($update, ['requireRelationPermission(', 'requireProductBundleMutationCsrf()', 'loadProductBundle(', 'PRODUCT_BUNDLE_REVISION_FIELD', 'normalizeProductBundlePayload(', 'beginProductBundleAuditTransaction(', 'lockProductBundleForRevision(', 'lockProductBundleLinesForRevision(', 'hash_equals(', "update('pos_product_bundle'", "delete('pos_product_bundle_line'", "'REPLACE_PRODUCT_BUNDLE'", 'trans_commit(']),
    'Bundle replacement validates RBAC/CSRF/revision, locks authoritative data, detects conflicts, and audits before commit'
);
$check(strpos($update, 'trans_rollback()') !== false && strpos($update, 'telah berubah oleh pengguna lain') !== false, 'Stale Bundle editor is rolled back and receives a reloadable conflict message');

$store = $block($controller, 'product_bundle_store');
$check(
    $ordered($store, ['requireRelationPermission(', 'requireProductBundleMutationCsrf()', 'normalizeProductBundlePayload(', 'beginProductBundleAuditTransaction(', "insert('pos_product_bundle'", "insert('pos_product_bundle_line'", "'CREATE_PRODUCT_BUNDLE'", 'trans_commit(']),
    'Bundle creation is transactional and writes its audit record before commit'
);
$toggle = $block($controller, 'product_bundle_toggle');
$check(
    $ordered($toggle, ['requireRelationPermission(', 'requireProductBundleMutationCsrf()', 'beginProductBundleAuditTransaction(', 'lockProductBundleForRevision(', "update('pos_product_bundle'", "'TOGGLE_PRODUCT_BUNDLE'", 'trans_commit(']),
    'Bundle status toggle locks the header and audits the change before commit'
);

$revision = $block($controller, 'canonicalProductBundleRevision');
$check(strpos($revision, "hash('sha256'") !== false && strpos($revision, 'bundle_code') !== false && strpos($revision, 'unit_price_override') !== false && strpos($revision, 'sort_order') !== false, 'Bundle revision covers header identity, price, status, lines, quantity, override, and ordering');
$headerLock = $block($controller, 'lockProductBundleForRevision');
$lineLock = $block($controller, 'lockProductBundleLinesForRevision');
$check(strpos($headerLock, 'pos_product_bundle') !== false && strpos($headerLock, 'FOR UPDATE') !== false, 'Bundle writer locks the authoritative header');
$check(strpos($lineLock, 'pos_product_bundle_line') !== false && strpos($lineLock, 'FOR UPDATE') !== false, 'Bundle writer locks authoritative lines before replacement');
$auditReady = $block($controller, 'productBundleAuditReady');
$audit = $block($controller, 'writeProductBundleAudit');
$check(strpos($auditReady, "table_exists('aud_transaction_log')") !== false && strpos($auditReady, "'before_payload'") !== false, 'Bundle mutation fails closed when audit storage is unavailable');
$check(strpos($audit, "'pos_product_bundle'") !== false && strpos($audit, "'before_payload'") !== false && strpos($audit, "'after_payload'") !== false, 'Bundle audit records atomic before/after payloads');
$check(strpos($controller, 'Pos_mobile') === false, 'Bundle concurrency hardening does not alter POS Mobile/APK contracts');

echo 'PASS master-relation-product-bundle-revision-audit checks=' . $checks . PHP_EOL;
