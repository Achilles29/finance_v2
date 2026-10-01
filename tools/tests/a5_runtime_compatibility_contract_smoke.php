<?php

declare(strict_types=1);

define('FINANCE_RUNTIME_COMPAT_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/release/runtime_compatibility_check.php';

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$policy = financeRuntimePolicy($root);
$check(financeRuntimePolicyErrors($policy) === [], 'runtime policy schema and all six version windows are valid');
$check(financeRuntimeRepositoryErrors($root, $policy) === [], 'Composer, npm, and Python locks satisfy the repository contract');
$check(financeRuntimeVersionInRange('8.1.0', $policy['runtimes']['php']), 'PHP lower bound is inclusive');
$check(financeRuntimeVersionInRange('8.1.99', $policy['runtimes']['php']), 'PHP approved minor is accepted');
$check(!financeRuntimeVersionInRange('8.0.99', $policy['runtimes']['php']), 'PHP below approved minor is rejected');
$check(!financeRuntimeVersionInRange('8.2.0', $policy['runtimes']['php']), 'untested next PHP minor is rejected');
$check(
    financeRuntimeExtractCommandVersion('mariadb', 'mariadb Ver 15.1 Distrib 10.6.23-MariaDB') === '10.6.23',
    'MariaDB distribution version wins over client protocol version'
);
$check(financeRuntimeExtractVersion('v20.20.2') === '20.20.2', 'Node version output is parsed');
$check(financeRuntimeVersionInRange('10.11.10', $policy['runtimes']['mariadb']), 'actual staging MariaDB server is in the approved range');
$check(!financeRuntimeVersionInRange('10.6.23', $policy['runtimes']['mariadb']) && !financeRuntimeVersionInRange('10.12.0', $policy['runtimes']['mariadb']), 'unapproved database minors remain rejected');
$probe = (string)file_get_contents(dirname(__DIR__) . '/release/runtime_compatibility_check.php');
$check(strpos($probe, "\$facts['mariadb_client'] = \$version;") !== false && strpos($probe, 'server runtime must be verified on the target DB') !== false, 'client presence cannot masquerade as database server validation');
$check(
    ($policy['runtimes']['php']['feature_extensions']['whatsapp_file_mime'] ?? null) === ['fileinfo'],
    'WhatsApp file MIME capability explicitly depends on fileinfo'
);
$check(
    in_array('sodium', $policy['runtimes']['php']['required_extensions'] ?? [], true),
    'Ed25519 release verification explicitly requires the PHP sodium extension'
);
$tampered = $policy;
$tampered['dependency_locks'] = ['composer.lock'];
$check(financeRuntimePolicyErrors($tampered) !== [], 'incomplete lock inventory is rejected');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A5.14 contract check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' A5.14 runtime compatibility contract checks passed.' . PHP_EOL;
