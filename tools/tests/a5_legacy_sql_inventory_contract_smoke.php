<?php

declare(strict_types=1);

$checks = 0; $failures = [];
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) { $failures[] = $message; fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); return; }
    echo 'PASS: ' . $message . PHP_EOL;
};

final class A54InventoryFailure extends RuntimeException {}
function a54_fail(string $message): void { throw new A54InventoryFailure($message); }
function a54_list(array $value): bool { return $value === [] || array_keys($value) === range(0, count($value) - 1); }
function a54_keys(array $value, array $expected): bool { $actual = array_keys($value); sort($actual); sort($expected); return $actual === $expected; }
function a54_safe_text($value): bool {
    return is_string($value) && $value !== '' && strlen($value) <= 500 && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) !== 1
        && strpos($value, '/www/') === false && preg_match('/(?:password|secret|token)\s*=/i', $value) !== 1;
}
function a54_probe_safe(array $probe): bool {
    if (!a54_keys($probe, ['mode', 'sql'])) return false;
    if ($probe['mode'] === 'not_applicable') return $probe['sql'] === null;
    if ($probe['mode'] !== 'select_only' || !is_string($probe['sql'])) return false;
    $sql = trim($probe['sql']);
    if (preg_match('/(--|#|\/\*|\*\/)/', $sql) === 1) return false;
    $sql = preg_replace('/;\s*$/', '', $sql);
    return is_string($sql) && preg_match('/^SELECT\b/i', $sql) === 1 && strpos($sql, ';') === false
        && preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|CALL|DO|LOAD|HANDLER|SET|USE|GRANT|REVOKE|LOCK|UNLOCK|START|COMMIT|ROLLBACK|PREPARE|EXECUTE|DEALLOCATE)\b/i', $sql) !== 1;
}
function a54_validate(array $inventory, array $catalog, string $root): void {
    if (!a54_keys($inventory, ['inventory_version', 'scope', 'allowed_dispositions', 'records']) || $inventory['inventory_version'] !== 1
        || $inventory['scope'] !== 'catalog_legacy_unmanaged_sql_exact'
        || $inventory['allowed_dispositions'] !== ['support-repair/manual-only', 'historical-unverified', 'candidate-canonical']
        || !is_array($inventory['records']) || !a54_list($inventory['records'])) a54_fail('inventory schema');
    $legacy = $catalog['legacy_unmanaged_sql'] ?? null;
    if (!is_array($legacy) || !a54_list($legacy) || count($legacy) !== 7 || count($inventory['records']) !== 7) a54_fail('inventory count');
    $paths = [];
    $recordKeys = ['path', 'sha256', 'chronological_role', 'features', 'referenced_objects', 'runtime_code_dependencies', 'data_risk', 'disposition', 'reasons', 'existing_schema_probe'];
    foreach ($inventory['records'] as $record) {
        if (!is_array($record) || !a54_keys($record, $recordKeys)) a54_fail('record schema');
        $path = $record['path'] ?? null;
        if (!is_string($path) || preg_match('#^sql/[^/]+\.sql$#', $path) !== 1 || strpos($path, '/_old/') !== false || isset($paths[$path])) a54_fail('record path');
        $paths[$path] = true;
        $full = $root . '/' . $path;
        if (is_link($full) || !is_file($full) || !is_string($record['sha256']) || preg_match('/^[a-f0-9]{64}$/', $record['sha256']) !== 1 || !hash_equals($record['sha256'], hash_file('sha256', $full))) a54_fail('hash drift');
        if (!a54_safe_text($record['chronological_role'])) a54_fail('role evidence');
        $featureKeys = ['uses_ddl', 'uses_dml', 'uses_procedure', 'uses_delimiter', 'uses_transaction'];
        if (!is_array($record['features']) || !a54_keys($record['features'], $featureKeys)) a54_fail('features schema');
        foreach ($record['features'] as $flag) if (!is_bool($flag)) a54_fail('feature flag');
        $source = (string)file_get_contents($full);
        $actual = [
            'uses_ddl' => preg_match('/\b(CREATE|ALTER|DROP)\b/i', $source) === 1,
            'uses_dml' => preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/im', $source) === 1,
            'uses_procedure' => preg_match('/\bCREATE\s+PROCEDURE\b/i', $source) === 1,
            'uses_delimiter' => preg_match('/^\s*DELIMITER\b/im', $source) === 1,
            'uses_transaction' => preg_match('/\bSTART\s+TRANSACTION\b/i', $source) === 1,
        ];
        if ($record['features'] !== $actual) a54_fail('feature drift ' . $path);
        if (!is_array($record['referenced_objects']) || !a54_list($record['referenced_objects']) || $record['referenced_objects'] === []) a54_fail('objects schema');
        foreach ($record['referenced_objects'] as $object) if (!a54_safe_text($object)) a54_fail('object evidence');
        if (!is_array($record['runtime_code_dependencies']) || !a54_list($record['runtime_code_dependencies']) || $record['runtime_code_dependencies'] === []) a54_fail('runtime evidence');
        foreach ($record['runtime_code_dependencies'] as $evidence) {
            if (!is_array($evidence) || !a54_keys($evidence, ['path', 'evidence']) || !is_string($evidence['path']) || $evidence['path'] === '' || $evidence['path'][0] === '/' || strpos($evidence['path'], '..') !== false || !is_file($root . '/' . $evidence['path']) || !a54_safe_text($evidence['evidence'])) a54_fail('runtime evidence path');
        }
        if (!is_array($record['data_risk']) || !a54_keys($record['data_risk'], ['level', 'reason']) || !in_array($record['data_risk']['level'], ['low', 'medium', 'high'], true) || !a54_safe_text($record['data_risk']['reason'])) a54_fail('risk schema');
        if (!in_array($record['disposition'], $inventory['allowed_dispositions'], true)) a54_fail('disposition');
        if (!is_array($record['reasons']) || !a54_list($record['reasons']) || $record['reasons'] === []) a54_fail('reasons');
        foreach ($record['reasons'] as $reason) if (!a54_safe_text($reason)) a54_fail('reason evidence');
        if (!is_array($record['existing_schema_probe']) || !a54_probe_safe($record['existing_schema_probe'])) a54_fail('unsafe probe');
    }
    if (array_keys($paths) !== $legacy) a54_fail('catalog cross-reference');
}
function a54_rejects(array $inventory, array $catalog, string $root): bool {
    try { a54_validate($inventory, $catalog, $root); } catch (A54InventoryFailure $error) { return true; }
    return false;
}
function a54_run(array $command, string $cwd): array {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, ['PATH' => '/usr/bin:/bin']);
    if (!is_resource($process)) return ['code' => 255, 'out' => ''];
    $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string)$out];
}

$root = dirname(__DIR__, 2);
$inventory = json_decode((string)file_get_contents($root . '/tools/db/legacy_sql_inventory.json'), true);
$catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
$valid = false; $validationError = '';
try { a54_validate($inventory, $catalog, $root); $valid = true; } catch (Throwable $error) { $validationError = $error->getMessage(); }
$check($valid, 'inventory schema, evidence, hashes, features, probes, and catalog cross-reference are valid' . ($validationError !== '' ? ' [' . $validationError . ']' : ''));
$check(array_column($inventory['records'], 'disposition') === ['historical-unverified', 'historical-unverified', 'candidate-canonical', 'candidate-canonical', 'candidate-canonical', 'historical-unverified', 'candidate-canonical'], 'all seven dispositions are explicit and non-deployable');

$cases = [];
$case = $inventory; $case['records'][0]['sha256'] = str_repeat('0', 64); $cases['hash drift'] = $case;
$case = $inventory; array_pop($case['records']); $cases['missing SQL record'] = $case;
$case = $inventory; $case['records'][0]['path'] = 'sql/unknown.sql'; $cases['unknown SQL record'] = $case;
$case = $inventory; $case['records'][0]['disposition'] = 'deployable'; $cases['invalid disposition'] = $case;
$case = $inventory; $case['records'][0]['path'] = 'sql/_old/legacy.sql'; $cases['archived SQL path'] = $case;
$case = $inventory; unset($case['inventory_version']); $cases['malformed inventory'] = $case;
foreach (['executable_policy', 'adoption', 'ledger', 'replay'] as $forbidden) { $case = $inventory; $case['records'][0][$forbidden] = true; $cases['forbidden ' . $forbidden . ' flag'] = $case; }
foreach (["UPDATE sys_menu SET is_active=1", "SELECT 1; DELETE FROM sys_menu", "SELECT 1 /* harmless */", "SELECT '--'; DROP TABLE sys_menu"] as $index => $probe) { $case = $inventory; $case['records'][0]['existing_schema_probe']['sql'] = $probe; $cases['unsafe probe ' . ($index + 1)] = $case; }
foreach ($cases as $label => $case) $check(a54_rejects($case, $catalog, $root), 'fail closed: ' . $label);

$plan = a54_run([PHP_BINARY, $root . '/tools/db/migration_runner.php', 'plan', '--policy=upgrade'], $root);
$planJson = json_decode(trim($plan['out']), true);
$check($plan['code'] === 0 && array_column($planJson['migrations'] ?? [], 'id') === ['2026-09-04c-a5-schema-migration-registry-foundation', '2026-09-05e-whatsapp-safe-reference-seed', '2026-09-05a-telegram-bot-foundation', '2026-09-05b-telegram-setup-guide', '2026-09-05c-telegram-safe-activation-default', '2026-09-06a-component-formula-version-history', '2026-09-06b-component-formula-restore-action', '2026-09-06c-pos-mobile-reversal-step-up', '2026-09-06d-pos-mobile-reprint-step-up', '2026-09-06e-activity-audit-foundation', '2026-09-06f-pos-mobile-cashier-close-step-up', '2026-09-06g-pos-mobile-reservation-refund-step-up', '2026-09-06h-roastery-label-template-studio', '2026-09-06i-a3-sidebar-task-oriented-layout', '2026-09-07a-c2-c4-business-profile-license-runtime-foundation', '2026-09-12a-roast-connect-catalog', '2026-09-13a-finance-mutation-reporting-category', '2026-09-14a-finance-control-workspace', '2026-09-14b-finance-control-operations','2026-09-14c-finance-allocation-bank-review','2026-09-15a-finance-general-ledger','2026-09-15b-finance-journal-assistant','2026-09-15c-application-user-guide','2026-09-16a-procurement-stock-review','2026-09-20a-pos-stock-commit-not-required','2026-09-23a-module-notifications'], 'catalog plan contains twenty-six managed upgrade migrations while seven legacy files remain non-deployable');

if ($failures !== []) { fwrite(STDERR, count($failures) . ' A5.4 inventory check(s) failed.' . PHP_EOL); exit(1); }
echo 'All ' . $checks . ' A5.4 legacy SQL inventory checks passed.' . PHP_EOL;
