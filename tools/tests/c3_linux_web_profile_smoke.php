<?php
declare(strict_types=1);
require dirname(__DIR__) . '/install/LinuxWebProfile.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++; echo "PASS {$label}\n";
};
$p = ['app_root'=>realpath(__DIR__), 'state_root'=>realpath(sys_get_temp_dir()),
    'deployment_file'=>__FILE__, 'tls_certificate'=>__FILE__, 'tls_key'=>__FILE__,
    'mime_types'=>__FILE__, 'user'=>'finance_trial', 'group'=>'finance_trial', 'port'=>18443];
$files = LinuxWebProfile::render($p);
$nginx = $files['nginx.conf']; $fpm = $files['php-fpm.conf'];
$check(strpos($nginx, 'listen 127.0.0.1:18443 ssl;') !== false, 'trial listener is loopback TLS only');
$check(strpos($nginx, 'location = /index.php') !== false && substr_count($nginx, 'fastcgi_pass ') === 1, 'only front controller executes PHP');
$check(strpos($nginx, 'application|tools|sql|docs|vendor') !== false, 'private application paths denied');
$check(strpos($nginx, '^/system/(core|database|fonts|helpers|language|libraries)') !== false, 'framework directories denied without hiding business-profile route');
$check(strpos($nginx, 'access_log off;') !== false && strpos($nginx, 'ssl_protocols TLSv1.2 TLSv1.3;') !== false, 'token-bearing URL logging disabled and TLS bounded');
$check(strpos($fpm, 'clear_env = yes') !== false && strpos($fpm, 'env[CI_ENV] = production') !== false, 'production process has explicit environment');
$check(strpos($fpm, 'FINANCE_DEPLOYMENT_FILE') !== false && strpos($fpm, 'DB_PASSWORD') === false, 'service config references private file, not secret values');
$check(strpos($fpm, 'listen.mode = 0600') !== false, 'dedicated account owns private socket');
$lua = LinuxWebProfile::render($p + ['lua_root'=>realpath(sys_get_temp_dir())]);
$check(strpos($lua['nginx.conf'], 'lua_package_path "' . realpath(sys_get_temp_dir()) . '/?.lua;;";') !== false, 'optional vendor Lua directory is explicit');
$check(strpos($nginx, 'lua_package_path') === false, 'plain nginx does not receive vendor-only directive');
foreach ([['port'=>80],['user'=>'root'],['group'=>'root'],['user'=>"bad\nuser"],['app_root'=>'/tmp/../tmp'],['state_root'=>$p['app_root']]] as $bad) {
    $rejected = false;
    try { LinuxWebProfile::render(array_replace($p, $bad)); } catch (RuntimeException $e) { $rejected = true; }
    $check($rejected, 'unsafe profile rejected');
}
echo "All {$checks} Linux web profile checks passed.\n";
