<?php

// Contract smoke for one-use POS Mobile reversal proofs. No database is touched.
$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Pos_mobile.php');
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$migration = (string)file_get_contents($root . '/sql/2026-09-06c_pos_mobile_reversal_step_up.sql');
$reprintMigration = (string)file_get_contents($root . '/sql/2026-09-06d_pos_mobile_reprint_step_up.sql');
$cashierCloseMigration = (string)file_get_contents($root . '/sql/2026-09-06f_pos_mobile_cashier_close_step_up.sql');
$baseline = (string)file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
$catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
$checks = 0;
$failures = [];

$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$method = static function (string $source, string $name): string {
    if (!preg_match('/(?:public|private|protected) function ' . preg_quote($name, '/') . '\\(/', $source, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = (int)$match[0][1];
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
};
$ordered = static function (string $source, array $needles): bool {
    $offset = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $offset) return false;
        $offset = $next;
    }
    return true;
};

$verify = $method($controller, 'order_reversal_step_up_verify');
$issue = $method($controller, 'issue_mobile_order_reversal_step_up');
$consume = $method($controller, 'consume_mobile_order_reversal_step_up');
$void = $method($controller, 'order_void_save');
$refund = $method($controller, 'order_refund_save');
$reprintVerify = $method($controller, 'order_reprint_step_up_verify');
$reprint = $method($controller, 'order_reprint_targets');
$cashierCloseVerify = $method($controller, 'cashier_close_step_up_verify');
$cashierCloseIssue = $method($controller, 'issue_mobile_cashier_close_step_up');
$cashierCloseConsume = $method($controller, 'consume_mobile_cashier_close_step_up');
$cashierClose = $method($controller, 'cashier_close');
$contract = $method($controller, 'mobile_sensitive_action_contract');

$check($controller !== '' && $routes !== '' && $migration !== '' && $reprintMigration !== '' && $cashierCloseMigration !== '' && $baseline !== '' && is_array($catalog), 'POS Mobile proof sources are readable');
$check(strpos($routes, "\$route['pos-mobile/orders/reversal-step-up/verify'] = 'pos_mobile/order_reversal_step_up_verify';") !== false, 'mobile reversal proof route is registered');
$check(strpos($routes, "\$route['pos-mobile/orders/reprint-step-up/verify'] = 'pos_mobile/order_reprint_step_up_verify';") !== false, 'mobile reprint proof route is registered');
$check(strpos($routes, "\$route['pos-mobile/cashier/close-step-up/verify'] = 'pos_mobile/cashier_close_step_up_verify';") !== false, 'mobile cashier-close proof route is registered');
$check($ordered($verify, ['$this->require_mobile_post()', '$this->authorize_mobile(true)', 'is_array($this->mobileUser)', '$this->request_payload()', "['VOID', 'REFUND']", 'mobile_order_reversal_permission(', 'mobile_financial_order_context(', 'issue_mobile_order_reversal_step_up(', "'step_up_proof'"]), 'proof endpoint requires POST, bearer identity, action RBAC, scoped order, then emits only a proof');
$check(strpos($verify, '$this->Pos_model->') === false && strpos($verify, 'save_order_') === false, 'proof endpoint cannot mutate POS orders');
$check($ordered($reprintVerify, ['$this->require_mobile_post()', '$this->authorize_mobile(true)', 'is_array($this->mobileUser)', "mobile_order_reversal_permission('ORDER_REPRINT')", '$this->request_payload()', 'mobile_financial_order_context(', "issue_mobile_order_reversal_step_up('ORDER_REPRINT'", "'step_up_proof'"]), 'reprint proof endpoint requires POST, bearer view RBAC, scoped order, then emits only a fixed-action proof');
$check(strpos($reprintVerify, '$this->Pos_model->') === false && strpos($reprintVerify, 'save_order_') === false, 'reprint proof endpoint cannot mutate POS orders');
$check($ordered($cashierCloseVerify, ['$this->require_mobile_post()', '$this->authorize_mobile(true)', 'is_array($this->mobileUser)', "mobile_order_workspace_page_code('edit')", 'mobile_cashier_session_context(true)', '$this->request_payload()', 'issue_mobile_cashier_close_step_up(', "'step_up_proof'"]), 'cashier-close proof endpoint requires POST, bearer identity, edit RBAC, active cashier session, then emits only a proof');
$check(strpos($cashierCloseVerify, '$this->Pos_model->') === false && strpos($cashierCloseVerify, 'close_cashier_session') === false, 'cashier-close proof endpoint cannot close a shift');
$check($ordered($issue, ["from('pos_mobile_auth_token')", "'step_up_locked_until'", "from('auth_user')", 'password_verify(', 'record_mobile_order_reversal_step_up_failure(', 'random_bytes(32)', "insert('pos_mobile_sensitive_action_proof'", "'proof_hash' => hash('sha256', \$proof)", "'mobile_token_id' => \$tokenId", "'user_id' => \$userId", "'terminal_id' => \$terminalId", "'action' => \$action", "'order_id' => \$orderId"]), 'issuance verifies active password and stores only a hashed proof bound to token, user, terminal, action, and order');
$passwordPersistenceNeedle = "'" . 'pass' . "word' =>";
$check(strpos($issue, $passwordPersistenceNeedle) === false && strpos($issue, 'step_up_proof') === false, 'issuance never stores a password or returns it in a persistence payload');
$check($ordered($cashierCloseIssue, ["from('pos_mobile_auth_token')", "from('auth_user')", 'password_verify(', 'record_mobile_order_reversal_step_up_failure(', 'random_bytes(32)', "insert('pos_mobile_sensitive_action_proof'", "'action' => 'CASHIER_CLOSE'", "'order_id' => 0", "'cashier_session_id' => \$cashierSessionId"]), 'cashier-close issuance verifies password and binds its hash proof to the exact session, not an order');
$check(strpos($cashierCloseIssue, $passwordPersistenceNeedle) === false && strpos($cashierCloseIssue, 'step_up_proof') === false, 'cashier-close issuance never persists or returns a password');
$check($ordered($consume, ["'proof_hash', hash('sha256', \$proof)", "'mobile_token_id', \$tokenId", "'user_id', \$userId", "'terminal_id', \$terminalId", "'action', \$action", "'order_id', \$orderId", "'expires_at >=', date", "'consumed_at IS NULL'", "update('pos_mobile_sensitive_action_proof'", "'consumed_at'", 'affected_rows() !== 1']), 'consumption is atomic and one-use with exact bearer/action/order binding');
$check($ordered($void, ['mobile_financial_order_context(', "consume_mobile_order_reversal_step_up('VOID'", "unset(\$payload['step_up_proof'])", 'save_order_void(']), 'mobile void consumes its proof before the writer and strips it from the writer payload');
$check($ordered($refund, ['mobile_financial_order_context(', "consume_mobile_order_reversal_step_up('REFUND'", "unset(\$payload['step_up_proof'])", 'save_order_refund(']), 'mobile refund consumes its proof before the writer and strips it from the writer payload');
$check($ordered($reprint, ['$this->require_mobile_post()', '$this->authorize_mobile(true)', "mobile_order_workspace_page_code('view')", 'mobile_financial_order_context(', '$this->request_payload()', "consume_mobile_order_reversal_step_up('ORDER_REPRINT'", "unset(\$payload['step_up_proof'])", 'direct_print_targets_for_order_reprint']), 'mobile reprint is POST-only and consumes a fixed reprint proof after RBAC/outlet scope and before printer targets');
$check($ordered($cashierCloseConsume, ["'proof_hash', hash('sha256', \$proof)", "'mobile_token_id', \$tokenId", "'user_id', \$userId", "'terminal_id', \$terminalId", "'action', 'CASHIER_CLOSE'", "'cashier_session_id', \$cashierSessionId", "'expires_at >=', date", "'consumed_at IS NULL'", "update('pos_mobile_sensitive_action_proof'", "'consumed_at'", 'affected_rows() !== 1']), 'cashier-close consumption is atomic and one-use with exact bearer/action/session binding');
$check($ordered($cashierClose, ['$this->require_mobile_post()', '$this->authorize_mobile(true)', "mobile_order_workspace_page_code('edit')", '$this->request_payload()', 'mobile_cashier_session_context(true)', 'consume_mobile_cashier_close_step_up(', "unset(\$payload['step_up_proof'])", 'close_cashier_session($payload']), 'cashier close consumes the proof before daily-recon checks and the shift writer, then strips it from payload');
$check(
    $ordered($contract, [
        "'version' => 3",
        "'proof_ttl_seconds' => self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS",
        "'VOID'",
        "'REFUND'",
        "'ORDER_REPRINT'",
        "'CASHIER_CLOSE'",
        "'RESERVATION_DEPOSIT_REFUND'",
    ])
        && strpos($contract, "'pos-mobile/cashier/close-step-up/verify'") !== false
        && strpos($contract, "'pos-mobile/reservations/reject-step-up/verify'") !== false
        && strpos($contract, "'pos-mobile/reservations/reject/{reservation_id}'") !== false
        && substr_count($contract, "'submit_method' => 'POST'") === 5,
    'bearer bootstrap contract names every proof-required action and its exact POST endpoint without exposing a secret'
);
$check(strpos($consume, "['step_up_required' => true]") !== false && substr_count($consume, '428') >= 2, 'missing, expired, cross-bound, or replayed proof fails closed with a machine-readable 428 response');
$check(strpos($migration, 'ALTER TABLE `pos_mobile_auth_token`') !== false && strpos($migration, 'CREATE TABLE IF NOT EXISTS `pos_mobile_sensitive_action_proof`') !== false && strpos($migration, "enum('VOID','REFUND')") !== false, 'managed migration supplies limiter columns and a narrow proof table');
$check(strpos($reprintMigration, 'ALTER TABLE `pos_mobile_sensitive_action_proof`') !== false && strpos($reprintMigration, "enum('VOID','REFUND','ORDER_REPRINT')") !== false, 'managed follow-up migration expands only the permitted proof action enum');
$check(strpos($cashierCloseMigration, 'ALTER TABLE `pos_mobile_sensitive_action_proof`') !== false && strpos($cashierCloseMigration, "enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE')") !== false && strpos($cashierCloseMigration, '`cashier_session_id`') !== false, 'managed cashier-close migration expands the permitted action enum and adds a session target');
$check(strpos($baseline, 'CREATE TABLE `pos_mobile_sensitive_action_proof`') !== false && strpos($baseline, "enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE','RESERVATION_DEPOSIT_REFUND')") !== false && strpos($baseline, '`cashier_session_id` bigint(20) unsigned DEFAULT NULL') !== false && strpos($baseline, '`reservation_id` bigint(20) unsigned DEFAULT NULL') !== false && strpos($baseline, '`step_up_failure_count` tinyint(3) unsigned NOT NULL DEFAULT 0') !== false, 'clean-install baseline contains the same mobile proof schema');
$entry = null;
foreach ((array)($catalog['migrations'] ?? []) as $candidate) {
    if (($candidate['id'] ?? '') === '2026-09-06c-pos-mobile-reversal-step-up') $entry = $candidate;
}
$check(is_array($entry) && ($entry['path'] ?? '') === 'sql/2026-09-06c_pos_mobile_reversal_step_up.sql' && ($entry['dependencies'] ?? []) === ['2026-09-04c-a5-schema-migration-registry-foundation'] && hash_file('sha256', $root . '/sql/2026-09-06c_pos_mobile_reversal_step_up.sql') === ($entry['sha256'] ?? ''), 'mobile proof migration is checksum-bound in the managed catalog');
$reprintEntry = null;
foreach ((array)($catalog['migrations'] ?? []) as $candidate) {
    if (($candidate['id'] ?? '') === '2026-09-06d-pos-mobile-reprint-step-up') $reprintEntry = $candidate;
}
$check(is_array($reprintEntry) && ($reprintEntry['path'] ?? '') === 'sql/2026-09-06d_pos_mobile_reprint_step_up.sql' && ($reprintEntry['dependencies'] ?? []) === ['2026-09-06c-pos-mobile-reversal-step-up'] && hash_file('sha256', $root . '/sql/2026-09-06d_pos_mobile_reprint_step_up.sql') === ($reprintEntry['sha256'] ?? ''), 'mobile reprint proof migration is checksum-bound after the immutable reversal foundation');
$cashierCloseEntry = null;
foreach ((array)($catalog['migrations'] ?? []) as $candidate) {
    if (($candidate['id'] ?? '') === '2026-09-06f-pos-mobile-cashier-close-step-up') $cashierCloseEntry = $candidate;
}
$check(is_array($cashierCloseEntry) && ($cashierCloseEntry['path'] ?? '') === 'sql/2026-09-06f_pos_mobile_cashier_close_step_up.sql' && ($cashierCloseEntry['dependencies'] ?? []) === ['2026-09-06d-pos-mobile-reprint-step-up'] && hash_file('sha256', $root . '/sql/2026-09-06f_pos_mobile_cashier_close_step_up.sql') === ($cashierCloseEntry['sha256'] ?? ''), 'mobile cashier-close proof migration is checksum-bound after the immutable reprint proof schema');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS Mobile reversal step-up check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS Mobile reversal step-up checks passed.' . PHP_EOL;
