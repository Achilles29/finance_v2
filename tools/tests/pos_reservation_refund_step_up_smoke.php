<?php

declare(strict_types=1);

/**
 * Source-only regression contract for web reservation closures that return a
 * deposit. It never invokes a writer or a database.
 */

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Pos.php');
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$stepUpLibrary = (string)file_get_contents($root . '/application/libraries/SensitiveActionStepUp.php');
$view = (string)file_get_contents($root . '/application/views/pos/reservation_index.php');
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
    for ($index = $brace, $length = strlen($source); $index < $length; $index++) {
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

$verify = $method($controller, 'reservation_refund_step_up_verify');
$reject = $method($controller, 'reservation_reject');
$cancel = $method($controller, 'reservation_cancel');
$consume = $method($controller, 'consume_reservation_deposit_refund_step_up');
$action = $method($controller, 'reservation_deposit_refund_step_up_action');
$saveClose = $method($view, 'saveClose');
$consumeCall = (string)strstr($consume, 'sensitiveactionstepup->consume(');

$check($controller !== '' && $routes !== '' && $stepUpLibrary !== '' && $view !== '', 'reservation refund proof sources are readable');
$check(
    strpos($routes, "\$route['pos/reservations/refund-step-up/verify'] = 'pos/reservation_refund_step_up_verify';") !== false,
    'web reservation refund proof route is explicitly registered'
);
$check(
    strpos($stepUpLibrary, "'RESERVATION_REJECT_DEPOSIT_REFUND'") !== false
        && strpos($stepUpLibrary, "'RESERVATION_CANCEL_DEPOSIT_REFUND'") !== false,
    'web proof actions are distinct from order refunds and from each other'
);
$check(
    $ordered($verify, [
        '$this->require_pos_transaction_csrf()',
        '$this->request_payload()',
        'reservation_deposit_refund_step_up_action($closeMode)',
        "require_permission('pos.reservation.index',",
        "sensitiveactionstepup->issue(",
        "'step_up_proof'",
    ]),
    'proof issuer validates CSRF, requested close action, matching permission, then emits only a proof'
);
$check(
    strpos($verify, 'reject_reservation(') === false
        && strpos($verify, 'cancel_reservation(') === false
        && strpos($verify, 'void_deposit(') === false,
    'proof issuer cannot close a reservation or alter a deposit'
);
$check(
    strpos($action, "'REJECT'") !== false
        && strpos($action, "'RESERVATION_REJECT_DEPOSIT_REFUND'") !== false
        && strpos($action, "'CANCEL'") !== false
        && strpos($action, "'RESERVATION_CANCEL_DEPOSIT_REFUND'") !== false,
    'proof action remains bound to the exact reject or cancel decision'
);
$check(
    strpos($consume, 'reservation_deposit_refund_step_up_action($closeMode)') !== false
        && $ordered($consumeCall, [
        '$stepUpAction,',
        '$reservationId,',
        "\$payload['step_up_proof']",
        "['step_up_required' => true]",
    ]),
    'proof consumption is target-bound and fails closed with a machine-readable reauth response'
);
foreach (['reject' => [$reject, 'REJECT', 'reject_reservation('], 'cancel' => [$cancel, 'CANCEL', 'cancel_reservation(']] as $label => [$source, $mode, $writer]) {
    $check(
        $ordered($source, [
            'require_permission(',
            '$this->require_pos_transaction_csrf()',
            '$this->request_payload()',
            '$refundDeposit = !empty',
            "consume_reservation_deposit_refund_step_up((int)\$id, '{$mode}', \$payload)",
            "unset(\$payload['step_up_proof'])",
            $writer,
        ]),
        $label . ' consumes a matching proof before its reservation writer and removes it from the model payload'
    );
    $check(
        strpos($source, 'if ($refundDeposit && !$this->consume_reservation_deposit_refund_step_up') !== false,
        $label . ' preserves the ordinary non-refund close path without a password prompt'
    );
}
$check(
    strpos($view, 'type="password" class="form-control" id="reservation_close_step_up_password"') !== false
        && strpos($view, 'autocomplete="current-password"') !== false,
    'reservation close UI uses a masked current-password field only for a DP refund'
);
$check(
    $ordered($saveClose, [
        "const password=String(el('reservation_close_step_up_password').value||'');",
        "el('reservation_close_step_up_password').value='';",
        'postPosTransactionJson(urls.refundStepUp,{reservation_id:id,close_mode:state.closeMode,password})',
        'payload.step_up_proof=',
        'await postPosTransactionJson(`${endpoint}/${id}`,payload)',
    ]),
    'browser clears the password then forwards only a one-use proof to the close writer'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' reservation-refund proof checks failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' reservation-refund proof checks passed.' . PHP_EOL;
