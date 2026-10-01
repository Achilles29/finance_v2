<?php

declare(strict_types=1);

/**
 * Deterministic A2 regression matrix. Every smoke runs in a fresh PHP process
 * so globals, constants, and fake CodeIgniter instances cannot leak between tests.
 */

$root = dirname(__DIR__, 2);
$tests = [
    'inventory period guard' => 'inventory_period_guard_smoke.php',
    'inventory period atomic barrier' => 'inventory_period_atomic_barrier_smoke.php',
    'inventory value reconciliation/cache' => 'inventory_value_reconciliation_availability_smoke.php',
    'POS availability item cache' => 'pos_availability_item_change_smoke.php',
    'POS reversal invariant' => 'pos_reversal_invariant_smoke.php',
    'POS reversal cache' => 'pos_reversal_availability_smoke.php',
    'inventory mutation CSRF' => 'inventory_control_mutation_csrf_smoke.php',
    'inventory division mutation CSRF' => 'inventory_division_reconcile_mutation_csrf_smoke.php',
    'production component formula CSRF' => 'production_component_formula_mutation_csrf_smoke.php',
    'purchase stock opening authorization' => 'purchase_stock_opening_authorization_smoke.php',
    'POS transaction CSRF' => 'pos_transaction_csrf_smoke.php',
    'POS monitor lifecycle' => 'pos_order_monitor_lifecycle_smoke.php',
];

$failures = [];
foreach ($tests as $label => $filename) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    echo PHP_EOL . '=== ' . $label . ' ===' . PHP_EOL;
    if (!is_file($path)) {
        fwrite(STDERR, 'FAIL: smoke script missing: ' . $filename . PHP_EOL);
        $failures[] = $label;
        continue;
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path);
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        fwrite(STDERR, 'FAIL: unable to start: ' . $filename . PHP_EOL);
        $failures[] = $label;
        continue;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($stdout !== '') {
        echo rtrim($stdout) . PHP_EOL;
    }
    if ($stderr !== '') {
        fwrite(STDERR, rtrim($stderr) . PHP_EOL);
    }
    if ($exitCode !== 0) {
        fwrite(STDERR, 'FAIL: ' . $label . ' exited with code ' . $exitCode . PHP_EOL);
        $failures[] = $label;
    } else {
        echo 'MATRIX PASS: ' . $label . PHP_EOL;
    }
}

if ($failures) {
    fwrite(STDERR, PHP_EOL . 'A2 matrix failed: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'A2 inventory transaction matrix passed (' . count($tests) . ' isolated smokes).' . PHP_EOL;
