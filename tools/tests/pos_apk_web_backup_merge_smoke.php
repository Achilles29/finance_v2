<?php
declare(strict_types=1);

// DB-free regression smoke for the focused APK/web backup merge.
$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Pos_mobile.php';
$viewPath = $root . '/application/views/pos/cashier_index.php';
$modelPath = $root . '/application/models/Pos_model.php';
$controllerBackupPath = $root . '/application/controllers/Pos_mobile_bak.php';
$viewBackupPath = $root . '/application/views/pos/cashier_index_bak.php';
$modelBackupPath = $root . '/application/models/Pos_model_bak.php';

$controller = (string)file_get_contents($controllerPath);
$view = (string)file_get_contents($viewPath);
$model = (string)file_get_contents($modelPath);
$checks = 0;
$failures = [];

function backup_merge_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function backup_merge_method(string $source, string $method): string
{
    $pattern = '/\bprivate\s+function\s+' . preg_quote($method, '/') . '\s*\(/';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = (int)$match[0][1];
    $open = strpos($source, '{', $start);
    if ($open === false) {
        return '';
    }
    $depth = 0;
    $length = strlen($source);
    for ($index = $open; $index < $length; $index++) {
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
}

$printMethod = backup_merge_method($controller, 'mobile_print_text');
$targetsMethod = backup_merge_method($controller, 'mobile_print_targets');
backup_merge_check($printMethod !== '' && $targetsMethod !== '', 'active mobile print methods are readable');
backup_merge_check(
    strpos($printMethod, 'str_replace(["\\r\\n", "\\r"], "\\n", $text)') !== false,
    'mobile print text normalizes CRLF and lone CR'
);
backup_merge_check(
    strpos($printMethod, 'LOGO_URL|LOGO_BASE64|BARCODE|QRCODE') !== false,
    'mobile print strips unsupported media marker lines including LOGO_BASE64'
);
backup_merge_check(
    strpos($printMethod, "preg_replace_callback('/\\[\\[FEED:(\\d+)\\]\\]/i'") !== false
        && strpos($printMethod, 'min(5, max(1,') !== false,
    'mobile print expands FEED markers with the 1..5 cap'
);
$trimPosition = strpos($printMethod, '$text = rtrim($text);');
$feedExpansionPosition = strpos($printMethod, 'preg_replace_callback(');
backup_merge_check(
    $trimPosition !== false
        && $feedExpansionPosition !== false
        && $trimPosition < $feedExpansionPosition
        && strpos($printMethod, 'substr($text, -1) === "\\n"') !== false,
    'mobile print trims before FEED expansion and appends LF only when needed'
);
backup_merge_check(
    strpos($printMethod, '\\x20-\\x7E') === false
        && stripos($printMethod, 'utf8_decode') === false
        && stripos($printMethod, 'iconv') === false,
    'mobile print has no blanket non-ASCII conversion or stripping'
);

if ($printMethod !== '') {
    $publicMethod = preg_replace('/\bprivate\s+function\s+mobile_print_text/', 'public function mobile_print_text', $printMethod, 1);
    eval('class PosMobilePrintMergeHarness {' . $publicMethod . '}');
    $printHarness = new PosMobilePrintMergeHarness();
    $printed = $printHarness->mobile_print_text(
        "Customer José 茶\r\n[[LOGO_BASE64:AA==]]\rProduk Crème\rX[[FEED:0]]Y\r\nA[[FEED:9]]B\r\n[[QRCODE:https://example.test]]\r\n"
    );
    backup_merge_check(strpos($printed, "\r") === false, 'runtime print normalization leaves LF-only output');
    backup_merge_check(
        strpos($printed, 'José 茶') !== false && strpos($printed, 'Crème') !== false,
        'runtime print output preserves UTF-8 customer and product text'
    );
    backup_merge_check(
        strpos($printed, 'LOGO_BASE64') === false && strpos($printed, 'QRCODE') === false,
        'runtime print output removes unsupported media markers'
    );
    backup_merge_check(
        strpos($printed, "X\nY") !== false && strpos($printed, "A\n\n\n\n\nB") !== false,
        'runtime print output applies FEED lower and upper bounds'
    );
    backup_merge_check(
        $printHarness->mobile_print_text('Tail[[FEED:5]]') === 'Tail' . str_repeat("\n", 5),
        'trailing FEED:5 produces exactly five trailing LF bytes'
    );
    backup_merge_check(
        $printHarness->mobile_print_text("Ordinary text  \r\n") === "Ordinary text\n",
        'ordinary print text ends in exactly one LF byte'
    );
}

backup_merge_check(
    strpos($targetsMethod, "['copies'] = max(1, min(10,") !== false
        && strpos($targetsMethod, "['cut_mode']") !== false
        && strpos($targetsMethod, "['open_drawer']") !== false,
    'existing print target copies, cut mode, and drawer contract remains intact'
);
backup_merge_check(
    substr_count($model, 'refresh_pos_reversal_availability_after_commit(') >= 3,
    'active Pos_model retains both A2 reversal availability refresh calls and helper'
);

foreach (['pos.self_order.index', 'pos.reservation.index', 'pos.online_food.index'] as $pageCode) {
    backup_merge_check(
        strpos($view, "!empty(\$cashierUserPerms['{$pageCode}']['can_view'])") !== false,
        $pageCode . ' channel uses its own view permission'
    );
}
backup_merge_check(
    strpos($view, "!empty(\$cashierUserPerms['pos.printer.index']['can_view'])") !== false
        && strpos($view, '<?php if ($canViewPosPrinters): ?>') !== false,
    'printer quick link is permission-gated'
);
backup_merge_check(
    strpos($view, 'foreach ($incomingChannelDefinitions as $channelCode => $channelDefinition)') !== false
        && strpos($view, '$incomingChannels[$channelCode] = $channelDefinition;') !== false
        && strpos($view, 'json_encode($incomingChannels,') !== false
        && strpos($view, 'foreach ($incomingChannels as $channelCode => $channel)') !== false,
    'only server-authorized incoming channels are rendered and exported for fetch'
);
backup_merge_check(
    strpos($view, '$incomingCashierOutletId = is_array($activeSession)') !== false
        && substr_count($view, 'incomingOutletId <= 0') >= 3
        && strpos($view, "query.set('outlet_id', String(incomingOutletId))") !== false
        && strpos($view, "query.set('outlet_id', '0')") === false,
    'incoming fetch requires active-session outlet and never requests outlet zero'
);
backup_merge_check(
    strpos($view, '$incomingServerDate = date(\'Y-m-d\');') !== false
        && strpos($view, "query.set('date_from', incomingServerDate)") !== false
        && strpos($view, "query.set('date_to', incomingServerDate)") !== false
        && strpos($view, 'toISOString') === false,
    'incoming order date uses PHP server-local Y-m-d rather than browser UTC'
);
backup_merge_check(
    strpos($view, 'setInterval') === false
        && strpos($view, "shown.bs.modal") === false
        && substr_count($view, 'incomingModal.show();') === 1
        && strpos($view, "incomingRefreshButton?.addEventListener('click'") !== false,
    'incoming modal refresh stays on-demand without interval or duplicate shown refresh'
);
backup_merge_check(
    preg_match('/target="_blank"(?!\s+rel="noopener noreferrer")/', $view) !== 1,
    'every target-blank cashier link protects its opener and referrer'
);
backup_merge_check(
    strpos($view, 'function escapeIncoming(value)') !== false
        && substr_count($view, 'escapeIncoming(') >= 10
        && strpos($view, "headers: { 'X-Requested-With': 'XMLHttpRequest' }") !== false,
    'incoming modal preserves escaped output and read-only same-origin fetch contract'
);
backup_merge_check(
    strpos($controller, 'Pos_mobile_bak') === false
        && strpos($model, 'Pos_model_bak') === false
        && strpos($view, 'cashier_index_bak') === false,
    'active runtime files do not include or reference backup sources'
);

// The APK owner may legitimately replace these untracked handoff files while
// developing in parallel. Their content is therefore not pinned to a stale
// checksum; the runtime-isolation contract above is the enforceable boundary.
foreach ([$controllerBackupPath, $viewBackupPath, $modelBackupPath] as $backupPath) {
    backup_merge_check(
        is_file($backupPath) && is_readable($backupPath) && !is_link($backupPath),
        basename($backupPath) . ' remains a readable, quarantined handoff file'
    );
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' focused APK/web backup merge smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' focused APK/web backup merge smoke checks passed.' . PHP_EOL;
