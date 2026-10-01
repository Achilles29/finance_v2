<?php

declare(strict_types=1);

/**
 * Contract for the only financially irreversible reservation-reject branch:
 * returning a paid deposit. This test is source-only and never touches data.
 */

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Pos_mobile.php');
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$migration = (string)file_get_contents($root . '/sql/2026-09-06g_pos_mobile_reservation_refund_step_up.sql');
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
    $needle = 'function ' . $name . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        return '';
    }
    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }
    $depth = 0;
    $length = strlen($source);
    for ($index = $brace; $index < $length; $index++) {
        if ($source[$index] === '{') {
            $depth++;
        } elseif ($source[$index] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $index - $start + 1);
            }
        }
    }
    return '';
};

$ordered = static function (string $source, array $needles): bool {
    $offset = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $offset) {
            return false;
        }
        $offset = $next;
    }
    return true;
};

$verify = $method($controller, 'reservation_reject_step_up_verify');
$issue = $method($controller, 'issue_mobile_reservation_refund_step_up');
$consume = $method($controller, 'consume_mobile_reservation_refund_step_up');
$reject = $method($controller, 'reservation_reject');
$schemaReady = $method($controller, 'mobile_reservation_refund_step_up_schema_ready');
$contract = $method($controller, 'mobile_sensitive_action_contract');

$check($controller !== '' && $routes !== '' && $migration !== '' && $baseline !== '' && is_array($catalog), 'reservation-refund proof sources are readable');
$check(
    strpos($routes, "\$route['pos-mobile/reservations/reject-step-up/verify'] = 'pos_mobile/reservation_reject_step_up_verify';") !== false,
    'reservation-deposit-refund proof route is explicitly registered'
);
$check(
    $ordered($verify, [
        '$this->require_mobile_post()',
        '$this->authorize_mobile(true)',
        'is_array($this->mobileUser)',
        "mobile_permission('pos.reservation.index', 'edit')",
        '$this->request_payload()',
        'mobile_reservation_outlet_allowed($reservationId)',
        'issue_mobile_reservation_refund_step_up(',
        "'step_up_proof'",
    ]),
    'proof issuance requires POST, bearer identity, reservation edit permission, and canonical outlet scope before emitting a proof'
);
$check(
    strpos($verify, 'reject_reservation(') === false && strpos($verify, 'void_deposit(') === false,
    'proof issuance cannot reject a reservation or return a deposit'
);
$check(
    strpos($schemaReady, "field_exists('reservation_id', 'pos_mobile_sensitive_action_proof')") !== false,
    'deposit-refund proof fails closed when its narrow schema target is unavailable'
);
$check(
    $ordered($issue, [
        "from('pos_mobile_auth_token')",
        "from('auth_user')",
        'password_verify(',
        'record_mobile_order_reversal_step_up_failure(',
        'random_bytes(32)',
        "insert('pos_mobile_sensitive_action_proof'",
        "'action' => 'RESERVATION_DEPOSIT_REFUND'",
        "'order_id' => 0",
        "'reservation_id' => \$reservationId",
    ]),
    'proof stores only a hash bound to token, user, terminal, fixed action, and exact reservation'
);
$passwordPersistenceNeedle = "'" . 'pass' . "word' =>";
$check(
    strpos($issue, $passwordPersistenceNeedle) === false && strpos($issue, 'step_up_proof') === false,
    'reservation-refund proof issuance never persists or returns a password'
);
$check(
    $ordered($consume, [
        "'proof_hash', hash('sha256', \$proof)",
        "'mobile_token_id', \$tokenId",
        "'user_id', \$userId",
        "'terminal_id', \$terminalId",
        "'action', 'RESERVATION_DEPOSIT_REFUND'",
        "'reservation_id', \$reservationId",
        "'expires_at >=', date",
        "'consumed_at IS NULL'",
        "update('pos_mobile_sensitive_action_proof'",
        'affected_rows() !== 1',
    ]),
    'proof consumption is atomic, one-use, and cannot cross token, user, terminal, action, or reservation'
);
$check(
    $ordered($reject, [
        '$this->require_mobile_post()',
        '$this->authorize_mobile(true)',
        "mobile_permission('pos.reservation.index', 'edit')",
        'mobile_reservation_outlet_allowed((int)$id)',
        '$this->request_payload()',
        '$refundDeposit = !empty($payload[\'refund_deposit\']);',
        'consume_mobile_reservation_refund_step_up((int)$id, $payload)',
        'unset($payload[\'step_up_proof\']);',
        'reject_reservation(',
    ]),
    'refund-deposit rejection consumes the proof after POST/RBAC/outlet checks and before the reservation writer'
);
$check(
    strpos($reject, 'if ($refundDeposit && is_array($this->mobileUser))') !== false,
    'ordinary rejection without deposit refund keeps its existing APK flow'
);
$check(
    strpos($contract, "'version' => 3") !== false
        && strpos(
            $contract,
            "'RESERVATION_DEPOSIT_REFUND' => [\n                    'verify_route' => 'pos-mobile/reservations/reject-step-up/verify',\n                    'submit_route' => 'pos-mobile/reservations/reject/{reservation_id}',\n                    'submit_method' => 'POST',"
        ) !== false,
    'bootstrap exposes the non-secret APK contract for the new narrow proof action'
);
$check(
    strpos($migration, "enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE','RESERVATION_DEPOSIT_REFUND')") !== false
        && strpos($migration, '`reservation_id`') !== false
        && strpos($migration, 'idx_pos_mobile_sensitive_action_proof_reservation_consume') !== false,
    'managed migration adds only the reservation proof target, action, and consume index'
);
$check(
    strpos($baseline, "enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE','RESERVATION_DEPOSIT_REFUND')") !== false
        && strpos($baseline, '`reservation_id` bigint(20) unsigned DEFAULT NULL') !== false
        && strpos($baseline, 'idx_pos_mobile_sensitive_action_proof_reservation_consume') === false
        && strpos($migration, 'ADD KEY `idx_pos_mobile_sensitive_action_proof_reservation_consume`') !== false,
    'clean install has the proof fields and creates its consume index once via managed migration'
);
$entry = null;
foreach ((array)($catalog['migrations'] ?? []) as $candidate) {
    if (($candidate['id'] ?? '') === '2026-09-06g-pos-mobile-reservation-refund-step-up') {
        $entry = $candidate;
        break;
    }
}
$check(
    is_array($entry)
        && ($entry['path'] ?? '') === 'sql/2026-09-06g_pos_mobile_reservation_refund_step_up.sql'
        && ($entry['dependencies'] ?? []) === ['2026-09-06f-pos-mobile-cashier-close-step-up']
        && hash_file('sha256', $root . '/sql/2026-09-06g_pos_mobile_reservation_refund_step_up.sql') === ($entry['sha256'] ?? ''),
    'reservation-deposit-refund migration is checksum-bound after the cashier-close proof schema'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS Mobile reservation-refund step-up check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' POS Mobile reservation-refund step-up checks passed.' . PHP_EOL;
