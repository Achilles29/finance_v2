<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2).'/application/libraries/DeploymentConfig.php';
require_once dirname(__DIR__).'/release/CustomerReleaseProfile.php';
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) throw new RuntimeException('ROOT_FIXTURE_REQUIRED');
$base = sys_get_temp_dir().'/finance-server-config-'.bin2hex(random_bytes(8));
mkdir($base, 0700); mkdir($base.'/config', 0750);
$path = $base.'/config/customer.json';
$n = 0;
$check = static function(bool $ok, string $message) use (&$n): void {
    if (!$ok) throw new RuntimeException('FAIL '.$message);
    $n++; echo 'PASS '.$message."\n";
};
$reject = static function(callable $fn, string $code, string $message) use ($check): void {
    try { $fn(); } catch (RuntimeException $e) { $check($e->getMessage() === $code, $message); return; }
    $check(false, $message);
};
$v = ['schema'=>1,'scope'=>'database_only','database'=>[
    'host'=>'127.0.0.1','port'=>3306,'socket'=>'','name'=>'fixture_finance',
    'user'=>'fixture_user','password'=>'fixture-only-quote"slash\\hash#$']];
$write = static function($v) use ($path): void {
    file_put_contents($path, is_array($v) ? json_encode($v, JSON_THROW_ON_ERROR) : $v);
    chmod($path, 0640); clearstatcache();
};
$saved = [];
foreach (array_merge(DeploymentConfig::allowedNames(), ['FINANCE_DEPLOYMENT_FILE']) as $k) {
    $saved[$k] = getenv($k); putenv($k);
}
try {
    $write($v);
    $c = new DeploymentConfig(null, $base);
    $check($c->isLocal() && $c->hasExplicitDatabase(), 'source database settings load without any FPM environment');
    $check($c->get(DeploymentConfig::DB_PASSWORD) === $v['database']['password'], 'password characters preserved exactly');
    $check(!CustomerLocalConfig::requiresProduction($base), 'database-only does not change application environment');
    $check($c->customerEnvironment($base, []) === [], 'database-only does not create a customer license context');
    $check($c->customerEnvironment($base, ['FINANCE_CUSTOMER_INSTALLATION_FILE'=>'existing-context'])['FINANCE_CUSTOMER_INSTALLATION_FILE'] === 'existing-context', 'existing explicit license context preserved, never disabled');
    $check($c->runtimeDirectory(DeploymentConfig::SESSION_PATH, $base, '/existing/sessions') === '/existing/sessions'
        && $c->get(DeploymentConfig::ENCRYPTION_KEY, '') === '', 'existing runtime and encryption defaults preserved');
    $reject(fn()=>DeploymentConfig::forRoot($base), 'CUSTOMER_INSTALL_REQUIRES_COMPLETE_CONFIG', 'installer refuses DB-only settings');
    $reject(fn()=>DeploymentConfig::previewCustomer($base, $v), 'CUSTOMER_CONFIG_UNKNOWN_FIELD', 'customer installer preview refuses source-only scope');
    putenv('FINANCE_ENCRYPTION_KEY=existing-key');
    $check((new DeploymentConfig(null, $base))->get(DeploymentConfig::ENCRYPTION_KEY) === 'existing-key', 'non-database legacy environment preserved');
    putenv('FINANCE_ENCRYPTION_KEY');
    foreach (['HOST','PORT','SOCKET','NAME','USER','PASSWORD'] as $field) {
        putenv('FINANCE_DB_'.$field.'=different');
        $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_SOURCE_CONFLICT', 'conflicting DB '.$field.' fails closed');
        putenv('FINANCE_DB_'.$field);
    }
    putenv('FINANCE_DB_NAME=fixture_finance');
    $check((new DeploymentConfig(null, $base))->get(DeploymentConfig::DB_NAME) === 'fixture_finance', 'identical database environment accepted');
    putenv('FINANCE_DB_NAME');
    foreach (['{broken','[]','null'] as $raw) {
        $write($raw);
        $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_JSON_INVALID', 'malformed configuration never falls back');
    }
    foreach (['host','name','user','password'] as $field) {
        $bad = $v; unset($bad['database'][$field]); $write($bad);
        $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_INCOMPLETE', 'incomplete '.$field.' refused');
    }
    $bad=$v; $bad['runtime']=['directory'=>'storage']; $write($bad);
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_UNKNOWN_FIELD', 'source scope cannot modify runtime or license identity');
    $write($v);
    foreach ([0644,0660,0666,0777] as $mode) {
        chmod($path, $mode); clearstatcache();
        $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_PERMISSION_UNSAFE', 'unsafe permission refused '.decoct($mode));
    }
    chmod($path, 0640); chmod($base.'/config', 0775); clearstatcache();
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_PERMISSION_UNSAFE', 'writable config directory refused');
    chmod($base.'/config', 0750); clearstatcache();
    rename($path, $base.'/original.json'); symlink($base.'/original.json', $path);
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_PATH_UNSAFE', 'symlink configuration refused');
    unlink($path); rename($base.'/original.json', $path);
    link($path, $base.'/hardlink.json'); clearstatcache();
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_FILE_UNSAFE', 'hard-linked configuration refused');
    unlink($base.'/hardlink.json');
    rename($base.'/config', $base.'/saved-config'); symlink($base.'/saved-config', $base.'/config');
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_PATH_UNSAFE', 'symlink config directory refused');
    unlink($base.'/config'); rename($base.'/saved-config', $base.'/config'); clearstatcache();
    // Package markers force the ORIGINAL customer permission/schema rules, not this exception.
    file_put_contents($base.'/RELEASE-MANIFEST.json', '{}');
    $check(CustomerLocalConfig::requiresProduction($base), 'customer marker still forces production');
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_PERMISSION_UNSAFE', 'packaged install cannot use source permission exception');
    $reject(fn()=>DeploymentConfig::fromSnapshot([])->customerEnvironment($base, []), 'CUSTOMER_INSTALLATION_CONTEXT_REQUIRED', 'package without identity never downgrades to legacy');
    unlink($base.'/RELEASE-MANIFEST.json');
    mkdir($base.'/installer'); file_put_contents($base.'/installer/layout.json', '{}');
    $reject(fn()=>new DeploymentConfig(null, $base), 'DEDICATED_INSTALLER_ACCOUNT_REQUIRED', 'portable package keeps dedicated-account enforcement');
    unlink($base.'/installer/layout.json'); rmdir($base.'/installer');
    chmod($base, 0775); clearstatcache();
    $check((new DeploymentConfig(null, $base))->get(DeploymentConfig::DB_NAME) === 'fixture_finance', 'developer-owned source tree allowed without changing customer rules');
    chmod($base, 0777); clearstatcache();
    $reject(fn()=>new DeploymentConfig(null, $base), 'CUSTOMER_CONFIG_PERMISSION_UNSAFE', 'world-writable source root rejected');
    chmod($base, 0700); clearstatcache();
    $profile=CustomerReleaseProfile::fromRoot(dirname(__DIR__,2));
    foreach (['config/customer.json','.user.ini','config/server-database.example.json','tools/install/capture_server_database.php'] as $p) {
        $check(!$profile->allows($p), 'customer release excludes source-server file '.$p);
    }
    $capture=$base.'/capture';
    foreach (['','/application','/application/libraries','/application/config','/config','/tools','/tools/install','/tmp'] as $dir) mkdir($capture.$dir, 0700);
    $source=dirname(__DIR__,2);
    foreach (['CustomerLocalConfig','CustomerPlatform','DeploymentConfig'] as $class) copy($source.'/application/libraries/'.$class.'.php', $capture.'/application/libraries/'.$class.'.php');
    copy($source.'/tools/install/capture_server_database.php', $capture.'/tools/install/capture_server_database.php');
    $conventional=['hostname'=>'127.0.0.1','username'=>'fixture_user','password'=>'synthetic-only',
        'database'=>'fixture_finance','dbdriver'=>'mysqli','dbprefix'=>'','dsn'=>''];
    $saveConventional=static function(array $data) use ($capture): void {
        file_put_contents($capture.'/application/config/database.php', '<?php $db[\'default\'] = '.var_export($data,true).';');
    };
    $saveConventional($conventional);
    file_put_contents($capture.'/.user.ini', 'open_basedir='.$capture.'/:/tmp/');
    $runCapture=static function(bool $confirmed) use ($capture): array {
        $args=[PHP_BINARY,$capture.'/tools/install/capture_server_database.php',posix_getgrgid(posix_getegid())['name']];
        if ($confirmed) $args[]='--http-protection-confirmed';
        $p=proc_open($args,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        return [proc_close($p),$out];
    };
    $check($runCapture(false)[0] !== 0 && !file_exists($capture.'/config/customer.json'), 'capture requires HTTP protection acknowledgement before writing secrets');
    $bad=$conventional; $bad['database']='REPLACE_DATABASE_NAME'; $saveConventional($bad);
    $check($runCapture(true)[0] !== 0 && !file_exists($capture.'/config/customer.json'), 'capture rejects invalid DB config before creating active local settings');
    $saveConventional($conventional);
    $before=hash_file('sha256',$capture.'/application/config/database.php');
    [$code,$out]=$runCapture(true);
    $check($code===0 && !str_contains($out,$conventional['password']), 'capture succeeds without exposing credentials');
    $check((new DeploymentConfig(null,$capture))->get(DeploymentConfig::DB_PASSWORD)===$conventional['password'], 'capture preserves exact credentials');
    $check(hash_file('sha256',$capture.'/application/config/database.php')===$before, 'capture leaves original loader unchanged for coordinated rollout');
    $hash=hash_file('sha256',$capture.'/config/customer.json');
    $check($runCapture(true)[0]!==0 && hash_file('sha256',$capture.'/config/customer.json')===$hash, 'capture refuses overwrite/repeated provisioning');
    echo "All $n source-server configuration checks passed; no DB/network.\n";
} finally {
    foreach ($saved as $k=>$old) putenv($old === false ? $k : $k.'='.$old);
    // Only this script's unique disposable fixture is removed.
    $remove = static function(string $p) use (&$remove, $base): void {
        if ($p !== $base && !str_starts_with($p, $base.'/')) throw new RuntimeException('CLEANUP_BOUNDARY');
        if (is_link($p) || is_file($p)) { unlink($p); return; }
        foreach (scandir($p) ?: [] as $n) if ($n !== '.' && $n !== '..') $remove($p.'/'.$n);
        rmdir($p);
    };
    $remove($base);
}
