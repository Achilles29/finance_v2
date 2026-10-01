<?php

declare(strict_types=1);

/** A4 cross-module runner: fresh process, hard timeout, bounded diagnostics. */

function a4_cross_module_manifest(): array
{
    $a4Files = [];
    $separateGateFiles = [
        'a4_dependency_vulnerability_contract_smoke.php',
        'a4_dependency_vulnerability_smoke.php',
        'a4_release_preflight_contract_smoke.php',
        'a4_release_preflight_smoke.php',
        'a4_release_artifact_contract_smoke.php',
        'a4_static_analysis_contract_smoke.php',
        'a4_static_analysis_smoke.php',
    ];
    foreach (scandir(__DIR__) ?: [] as $file) {
        if ($file === basename(__FILE__) || in_array($file, $separateGateFiles, true)) {
            continue;
        }
        if (preg_match('/^a4_.*_smoke\.(?:php|js|py)$/D', $file) === 1) {
            $a4Files[] = $file;
        }
    }
    sort($a4Files, SORT_STRING);

    $files = array_merge($a4Files, [
        'auth_division_scope_smoke.php',
        'auth_inactive_role_permission_smoke.php',
        'auth_login_throttle_smoke.php',
        'dashboard_component_value_mismatch_smoke.php',
        'component_formula_restore_smoke.php',
        'pos_mobile_reversal_step_up_smoke.php',
        'master_att_holiday_generate_csrf_smoke.php',
        'printer_agent_trust_smoke.py',
        'production_component_formula_mutation_csrf_smoke.php',
        'purchase_maintenance_mutation_csrf_smoke.php',
        'purchase_stock_opening_authorization_smoke.php',
    ]);
    foreach (scandir(__DIR__) ?: [] as $file) {
        if (preg_match('/^whatsapp_.*_smoke\.php$/D', $file) === 1) {
            $files[] = $file;
        }
    }
    $files[] = 'wa_engine_group_command_service_auth_smoke.js';
    $files[] = 'wa_engine_internal_service_auth_smoke.js';

    $supplemental = array_slice($files, count($a4Files));
    sort($supplemental, SORT_STRING);
    $files = array_merge($a4Files, $supplemental);

    return array_map(static function (string $file): array {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $sourceOnly = in_array($file, [
            'a4_browser_shell_runtime_smoke.php',
            'printer_agent_trust_smoke.py',
        ], true);
        return [
            'id' => pathinfo($file, PATHINFO_FILENAME),
            'file' => $file,
            'runtime' => $extension === 'js' ? 'node' : ($extension === 'py' ? 'python3' : PHP_BINARY),
            'args' => $sourceOnly ? ['--source-only'] : [],
        ];
    }, $files);
}

function a4_cross_module_tail(string $output, int $lines = 12, int $bytes = 12000): string
{
    if (strlen($output) > $bytes) {
        $output = substr($output, -$bytes);
        $newline = strpos($output, "\n");
        $output = $newline === false ? $output : substr($output, $newline + 1);
    }
    $parts = preg_split('/\R/', trim($output)) ?: [];
    return implode(PHP_EOL, array_slice($parts, -$lines));
}

/** @return array{code:int,timeout:bool,output:string} */
function a4_cross_module_run(array $test, int $timeoutSeconds = 120): array
{
    $command = array_merge(
        [$test['runtime'], __DIR__ . DIRECTORY_SEPARATOR . $test['file']],
        array_map('strval', $test['args'] ?? [])
    );
    $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open(array_map('strval', $command), $descriptors, $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        return ['code' => 255, 'timeout' => false, 'output' => 'Unable to start isolated process.'];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $started = microtime(true);
    $output = '';
    $code = null;
    $timedOut = false;
    while (true) {
        foreach ([1, 2] as $index) {
            $chunk = stream_get_contents($pipes[$index]);
            if (is_string($chunk) && $chunk !== '') {
                $output .= $chunk;
                if (strlen($output) > 24000) {
                    $output = substr($output, -24000);
                }
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            $code = (int)$status['exitcode'];
            break;
        }
        if (microtime(true) - $started >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            usleep(100000);
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            $code = 124;
            break;
        }
        usleep(20000);
    }
    foreach ([1, 2] as $index) {
        $chunk = stream_get_contents($pipes[$index]);
        if (is_string($chunk)) {
            $output .= $chunk;
        }
        fclose($pipes[$index]);
    }
    $closed = proc_close($process);
    if (!$timedOut && ($code === null || $code < 0) && $closed >= 0) {
        $code = $closed;
    }
    return ['code' => $code ?? 1, 'timeout' => $timedOut, 'output' => $output];
}

$manifestA = a4_cross_module_manifest();
$manifestB = a4_cross_module_manifest();
$ids = array_column($manifestA, 'id');
$a4Count = count(array_filter($manifestA, static fn(array $test): bool => strpos($test['file'], 'a4_') === 0));
if ($manifestA !== $manifestB || count($ids) !== count(array_unique($ids)) || $a4Count !== 7) {
    fwrite(STDERR, 'FAIL: matrix manifest must be deterministic, unique, and contain exactly 7 A4 sibling smokes; found ' . $a4Count . PHP_EOL);
    exit(1);
}

$failed = [];
foreach ($manifestA as $index => $test) {
    echo '[' . ($index + 1) . '/' . count($manifestA) . '] ' . $test['id'] . PHP_EOL;
    if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $test['file'])) {
        $failed[] = $test['id'];
        echo 'FAIL missing' . PHP_EOL;
        continue;
    }
    $result = a4_cross_module_run($test);
    if ($result['code'] === 0) {
        echo 'PASS' . PHP_EOL;
        continue;
    }
    $failed[] = $test['id'];
    echo 'FAIL ' . ($result['timeout'] ? 'timeout' : 'exit=' . $result['code']) . PHP_EOL;
    foreach (preg_split('/\R/', a4_cross_module_tail($result['output'])) ?: [] as $line) {
        echo '  | ' . $line . PHP_EOL;
    }
}

echo 'A4 CROSS MODULE ' . ($failed === [] ? 'PASS' : 'FAIL')
    . ' passed=' . (count($manifestA) - count($failed))
    . ' failed=' . count($failed)
    . ' total=' . count($manifestA) . PHP_EOL;
exit($failed === [] ? 0 : 1);
