<?php

declare(strict_types=1);

/**
 * Source-only regression contract for the Online Food web list.
 * It intentionally performs no request, mutation, or database query.
 */

$root = dirname(__DIR__, 2);
$view = (string)file_get_contents($root . '/application/views/pos/online_food_orders.php');
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

$check($view !== '' && $loadRows !== '' && $setState !== '' && $resetFilters !== '' && $renderRows !== '' && $getJson !== '', 'Online Food list UI sources are readable');
$check(
    strpos($view, 'id="self_order_table_region" aria-busy="false"') !== false
        && strpos($view, 'id="self_order_list_state" class="self-order-list-state d-none mt-3" role="status" aria-live="polite"') !== false
        && strpos($view, 'id="self_order_pagination_info" class="text-muted" aria-live="polite"') !== false,
    'Online Food list exposes accessible busy, feedback, and pagination state'
);
$check(
    strpos($getJson, "credentials: 'same-origin'") !== false
        && strpos($getJson, 'signal = null') !== false
        && strpos($getJson, "'X-Requested-With': 'XMLHttpRequest'") !== false,
    'Online Food read helper retains session context and accepts request cancellation'
);
$check(
    strpos($setState, "type === 'loading'") !== false
        && strpos($setState, "region.setAttribute('aria-busy'") !== false
        && strpos($setState, 'data-online-food-retry') !== false,
    'Online Food list renders busy feedback and a recoverable retry action'
);
$check(
    strpos($renderRows, 'data-online-food-clear') !== false
        && strpos($renderRows, 'resetListFilters') !== false,
    'empty Online Food results explain the filter condition and offer a safe reset'
);
$check(
    strpos($resetFilters, "state.payment_tab = 'ALL'") !== false
        && strpos($resetFilters, "state.status_tab = 'NEEDS_VERIFY'") !== false
        && strpos($resetFilters, "document.getElementById('self_order_limit').value") !== false
        && strpos($resetFilters, 'loadRows();') !== false,
    'reset restores the documented Online Food operational defaults'
);
$check(
    strpos($loadRows, 'listRequestController.abort()') !== false
        && strpos($loadRows, 'new AbortController()') !== false
        && strpos($loadRows, 'requestId !== listRequestId') !== false,
    'newer Online Food filters cancel and supersede stale list requests'
);
$check(
    strpos($loadRows, "setListState('loading'") !== false
        && strpos($loadRows, 'setListState();') !== false
        && strpos($loadRows, "setListState('error'") !== false
        && strpos($loadRows, 'renderPager(json.meta || {})') !== false,
    'Online Food list has explicit loading, success, failure, and pagination states'
);
$check(
    strpos($loadRows, 'postPosTransactionJson(') === false
        && strpos($loadRows, 'postJson(') === false
        && strpos($loadRows, 'pos/online-food/orders/data') !== false,
    'Online Food list refresh remains read-only and cannot verify, reject, or change stock'
);
$check(
    strpos($view, 'listSearchTimer = setTimeout(() => loadRows(), 250)') !== false
        && strpos($view, 'loadRows().catch((e) => showInfoModal(e.message))') === false,
    'Online Food search is debounced and list failures stay in contextual feedback'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Online Food UI check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' Online Food UI checks passed.' . PHP_EOL;
