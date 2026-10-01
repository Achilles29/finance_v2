<?php

declare(strict_types=1);

/**
 * Source-only regression contract for the web cashier's active-order list.
 * It deliberately makes no HTTP, writer, or database call.
 */

$root = dirname(__DIR__, 2);
$view = (string)file_get_contents($root . '/application/views/pos/cashier_index.php');
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

$loadRecents = $function($view, 'loadRecents');
$setState = $function($view, 'setRecentListState');
$resetFilters = $function($view, 'resetRecentFilters');
$getJson = $function($view, 'getJson');

$check($view !== '' && $loadRecents !== '' && $setState !== '' && $resetFilters !== '' && $getJson !== '', 'cashier recent-list UI sources are readable');
$check(
    strpos($view, 'id="cashier_recent_panel" aria-busy="false"') !== false
        && strpos($view, 'id="cashier_recent_state" class="cashier-recent-state d-none mt-2" role="status" aria-live="polite"') !== false
        && strpos($view, 'id="cashier_recent_empty" class="cashier-empty d-none" role="status" aria-live="polite"') !== false,
    'cashier recent list exposes accessible busy, state, and empty feedback'
);
$check(
    strpos($getJson, "credentials: 'same-origin'") !== false
        && strpos($getJson, 'signal = null') !== false,
    'read helper retains session context and accepts request cancellation'
);
$check(
    strpos($setState, "type === 'loading'") !== false
        && strpos($setState, "panel.setAttribute('aria-busy'") !== false
        && strpos($setState, 'data-cashier-recent-retry') !== false,
    'cashier recent list renders busy feedback and a recoverable retry action'
);
$check(
    strpos($resetFilters, "recentState.status = 'ALL'") !== false
        && strpos($resetFilters, "button.dataset.status === 'ALL'") !== false
        && strpos($resetFilters, 'loadRecents();') !== false,
    'empty-result reset returns the active-order list to its safe default filter'
);
$check(
    strpos($loadRecents, 'recentRequestController.abort()') !== false
        && strpos($loadRecents, 'new AbortController()') !== false
        && strpos($loadRecents, 'requestId !== recentRequestId') !== false,
    'new cashier list filters cancel and supersede stale requests'
);
$check(
    strpos($loadRecents, "setRecentListState('loading'") !== false
        && strpos($loadRecents, 'setRecentListState();') !== false
        && strpos($loadRecents, "setRecentListState('error'") !== false
        && strpos($loadRecents, 'data-cashier-recent-clear') !== false,
    'cashier list has explicit loading, success, empty, and failure states'
);
$check(
    strpos($loadRecents, 'postPosTransactionJson(') === false
        && strpos($loadRecents, 'postJson(') === false
        && strpos($loadRecents, 'pos/orders/draft/data') !== false,
    'cashier list refresh remains read-only and cannot alter an order or payment'
);
$check(
    strpos($view, 'recentSearchTimer = setTimeout(() => loadRecents(), 250)') !== false
        && strpos($view, "loadRecents().catch((err) => alert(err.message))") === false,
    'cashier search is debounced and list failures stay in contextual feedback'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS cashier recent-list UI check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' POS cashier recent-list UI checks passed.' . PHP_EOL;
