<?php
declare(strict_types=1);
define('C3_CLEAN_INSTALL_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/install/clean_install_database.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++; if (!$ok) throw new RuntimeException('FAIL: ' . $label); echo 'PASS: ' . $label . "\n";
};
$reject = static function (callable $call, string $label) use ($check): void {
    try { $call(); } catch (RuntimeException $e) { $check(true, $label); return; } $check(false, $label);
};
c3InstallEmptyDatabase(['__C3_EMPTY__','0']); $check(true, 'exact empty database marker accepted');
foreach ([['__C3_EMPTY__','1'],['__C3_EMPTY__','10'],[],['__C3_EMPTY__',0],['wrong','0']] as $marker) {
    $reject(fn() => c3InstallEmptyDatabase($marker), 'nonempty or malformed state rejected before baseline');
}
$legacy = 'MariaDB >=10.6 <10.7';
$current = 'MariaDB >=10.11 <10.12';
foreach ([[$legacy, '10.6.23-MariaDB'], [$legacy, '5.5.5-10.6.23-MariaDB'],
    [$current, '10.11.10-MariaDB-log'], [$current, '5.5.5-10.11.10-MariaDB'],
    [$current, '10.11.0-MariaDB'], [$current, '10.11.99-MariaDB']] as [$contract, $version]) {
    c3InstallDatabaseVersion(['__C3_VERSION__',$version], $contract); $check(true, 'server version accepted only within signed window');
}
foreach (['10.11.10-MariaDB-log','8.0.40','10.6.23-MySQL','invalid'] as $version) {
    $reject(fn() => c3InstallDatabaseVersion(['__C3_VERSION__',$version], $legacy), 'legacy artifact runtime is not widened');
}
foreach (['10.6.23-MariaDB','10.10.99-MariaDB','10.12.0-MariaDB','8.0.40','10.11.10-MySQL','garbage10.11.10-MariaDB'] as $version) {
    $reject(fn() => c3InstallDatabaseVersion(['__C3_VERSION__',$version], $current), 'other runtimes rejected for 10.11 artifact');
}
foreach ([[], ['wrong','10.11.10-MariaDB'], ['__C3_VERSION__',101110]] as $marker) {
    $reject(fn() => c3InstallDatabaseVersion($marker, $current), 'malformed version marker rejected');
}
$reject(fn() => c3InstallDatabaseVersion(['__C3_VERSION__','10.11.10-MariaDB'], 'MariaDB >=10.6 <11'), 'unapproved wide runtime contract rejected');
$code = (string)file_get_contents(dirname(__DIR__) . '/install/clean_install_database.php');
$check(strpos($code, 'ControlReleaseBridge::verify(') < strpos($code, 'a5_client_open('), 'signature verification precedes database access');
$check(strpos($code, 'c3InstallEmptyDatabase(a5_client_marker(') < strpos($code, "\$policy['schema']['path']"), 'empty-state check precedes all baseline SQL');
$versionCheck = strpos($code, "c3InstallDatabaseVersion(\$version, \$manifest['runtime']['database'])");
$check($versionCheck !== false && $versionCheck < strpos($code, "\$policy['schema']['path']"), 'signed runtime check precedes all baseline SQL');
$check(stripos($code, 'DROP DATABASE') === false && stripos($code, 'TRUNCATE ') === false, 'installer never wipes a database to retry');
$check(strpos($code, "a5_apply(\$catalog, \$release['root'], 'clean_install'") !== false && strpos($code, 'a513_check_database(') !== false, 'managed policy and post-install health are mandatory');
$check(strpos($code, 'a512_bootstrap_owner(') !== false && strpos($code, 'INSTALL_SOURCE_EXTRA_FILES') !== false, 'first owner and exact verified source set are explicit');
echo "All {$checks} clean-install database boundary checks passed; no database accessed.\n";
