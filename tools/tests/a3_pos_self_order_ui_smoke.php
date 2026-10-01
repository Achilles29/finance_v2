<?php

declare(strict_types=1);

/**
 * Source-only regression contract for the Self Order web list.
 * It intentionally performs no request, mutation, or database query.
 */

$root = dirname(__DIR__, 2);
$view = (string)file_get_contents($root . '/application/views/pos/self_order_orders.php');
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
$setState = $function($view, 'setListState');
$resetFilters = $function($view, 'resetListFilters');
$renderRows = $function($view, 'renderRows');
$getJson = $function($view, 'getJson');

$check($view !== '' && $loadRows !== '' && $setState !== '' && $resetFilters !== '' && $renderRows !== '' && $getJson !== '', 'Self Order list UI sources are readable');
$check(
    strpos($view, 'id="self_order_table_region" aria-busy="false"') !== false
        && strpos($view, 'id="self_order_list_state" class="self-order-list-state d-none mt-3" role="status" aria-live="polite"') !== false
        && strpos($view, 'id="self_order_pagination_info" class="text-muted" aria-live="polite"') !== false,
    'Self Order list exposes accessible busy, feedback, and pagination state'
);
$check(
    strpos($getJson, "credentials: 'same-origin'") !== false
        && strpos($getJson, 'signal = null') !== false
        && strpos($getJson, "'X-Requested-With': 'XMLHttpRequest'") !== false,
    'Self Order read helper retains session context and accepts request cancellation'
);
$check(
    strpos($setState, "type === 'loading'") !== false
        && strpos($setState, "region.setAttribute('aria-busy'") !== false
        && strpos($setState, 'data-self-order-retry') !== false,
    'Self Order list renders busy feedback and a recoverable retry action'
);
$check(
    strpos($renderRows, 'data-self-order-clear') !== false
        && strpos($renderRows, 'resetListFilters') !== false,
    'empty Self Order results explain the filter condition and offer a safe reset'
);
$check(
    strpos($resetFilters, "state.payment_tab = 'ALL'") !== false
        && strpos($resetFilters, "state.status_tab = 'NEEDS_VERIFY'") !== false
        && strpos($resetFilters, "document.getElementById('self_order_limit').value") !== false
        && strpos($resetFilters, 'loadRows();') !== false,
    'reset restores the documented Self Order operational defaults'
);
$check(
    strpos($loadRows, 'listRequestController.abort()') !== false
        && strpos($loadRows, 'new AbortController()') !== false
        && strpos($loadRows, 'requestId !== listRequestId') !== false,
    'newer Self Order filters cancel and supersede stale list requests'
);
$check(
    strpos($loadRows, "setListState('loading'") !== false
        && strpos($loadRows, 'setListState();') !== false
        && strpos($loadRows, "setListState('error'") !== false
        && strpos($loadRows, 'renderPager(json.meta || {})') !== false,
    'Self Order list has explicit loading, success, failure, and pagination states'
);
$check(
    strpos($loadRows, 'postPosTransactionJson(') === false
        && strpos($loadRows, 'postJson(') === false
        && strpos($loadRows, 'pos/self-order/orders/data') !== false,
    'Self Order list refresh remains read-only and cannot verify, reject, or change stock'
);
$check(
    strpos($view, 'listSearchTimer = setTimeout(() => loadRows(), 250)') !== false
        && strpos($view, 'loadRows().catch((e) => showInfoModal(e.message))') === false,
    'Self Order search is debounced and list failures stay in contextual feedback'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Self Order UI check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' Self Order UI checks passed.' . PHP_EOL;
