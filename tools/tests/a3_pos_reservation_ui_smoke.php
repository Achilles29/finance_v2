<?php

declare(strict_types=1);

/**
 * Source-only UI regression contract for the POS web reservation list.
 * It deliberately performs no request, writer, or database operation.
 */

$root = dirname(__DIR__, 2);
$view = (string)file_get_contents($root . '/application/views/pos/reservation_index.php');
$checks = 0;
$failures = [];

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$function = static function (string $source, string $name): string {
    $start = strpos($source, 'function ' . $name . '(');
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

$loadRows = $function($view, 'loadRows');
$setListState = $function($view, 'setReservationListState');
$renderEmptyState = $function($view, 'renderEmptyState');
$request = $function($view, 'request');

$check($view !== '' && $loadRows !== '' && $setListState !== '' && $renderEmptyState !== '' && $request !== '', 'reservation list UI sources are readable');
$check(
    strpos($view, 'id="reservation_table_wrap" aria-busy="false"') !== false
        && strpos($view, 'id="reservation_list_state" role="status" aria-live="polite"') !== false
        && strpos($view, 'id="reservation_pagination_info" aria-live="polite"') !== false,
    'list, state, and pagination expose accessible loading feedback'
);
$check(
    strpos($setListState, "type==='loading'") !== false
        && strpos($setListState, "type==='error'") === false
        && strpos($setListState, 'data-reservation-retry') !== false
        && strpos($setListState, "wrap.setAttribute('aria-busy'") !== false,
    'list state safely supports loading, accessible busy state, and retry action'
);
$check(
    strpos($renderEmptyState, 'Belum ada ${tab} reservasi.') !== false
        && strpos($renderEmptyState, 'data-reservation-clear-empty') !== false
        && strpos($renderEmptyState, "el('reservation_clear').click()") !== false,
    'empty result tells the operator what to check and offers a safe filter reset'
);
$check(
    strpos($loadRows, 'reservationRowsController.abort()') !== false
        && strpos($loadRows, 'new AbortController()') !== false
        && strpos($loadRows, 'requestId!==reservationRowsRequestId') !== false,
    'newer reservation filters cancel and supersede stale list requests'
);
$check(
    strpos($loadRows, "setReservationListState('loading'") !== false
        && strpos($loadRows, 'setReservationListState();') !== false
        && strpos($loadRows, "setReservationListState('error'") !== false
        && strpos($loadRows, 'renderEmptyState(rows)') !== false,
    'list has explicit loading, success, empty, and failure states'
);
$check(
    strpos($loadRows, 'postPosTransactionJson(') === false
        && strpos($loadRows, 'urls.save') === false
        && strpos($loadRows, 'urls.reject') === false
        && strpos($loadRows, 'urls.cancel') === false,
    'list refresh remains read-only and cannot change a reservation or its DP'
);
$check(
    strpos($request, "credentials:'same-origin'") !== false
        && strpos($request, 'signal=null') !== false,
    'generic read request keeps session context and accepts cancellation'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS reservation UI check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' POS reservation UI checks passed.' . PHP_EOL;
