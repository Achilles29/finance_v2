<?php
declare(strict_types=1);
// Explicit disposable integration test; not part of automatic DB-free gates.
umask(0077);
$s = $argv[1] ?? '';
$fixture = $argv[2] ?? '';
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0 || preg_match('~\A/var/lib/finance-web-[0-9]{8}[.][A-Za-z0-9]{6}\z~D', $s) !== 1
    || realpath($s) !== $s || !is_dir($fixture)) throw new RuntimeException('EXPLICIT_DISPOSABLE_TRIAL_REQUIRED');
require_once dirname(__DIR__,2).'/application/libraries/Control_license_cache.php';
$license=Control_license_cache::customer_verification($s.'/app',$s.'/customer-installation.json');
if(empty($license['verified'])||!in_array($license['status']??'', ['ACTIVE','GRACE'], true))throw new RuntimeException('LICENSED_DISPOSABLE_TRIAL_REQUIRED');
$deployment = json_decode((string)file_get_contents($s . '/deployment.json'), true, 32, JSON_THROW_ON_ERROR);
if (preg_match('/\Ac3_finance_test_[a-f0-9]{12}\z/D', $deployment['FINANCE_DB_NAME'] ?? '') !== 1
    || $deployment['FINANCE_DB_NAME'] !== trim((string)file_get_contents($fixture . '/disposable-database.name'))
    || ($deployment['FINANCE_BASE_URL'] ?? '') !== 'https://127.0.0.1:18443/') throw new RuntimeException('DISPOSABLE_DATABASE_ONLY');
$base = $deployment['FINANCE_BASE_URL']; unset($deployment);
$checks = [];
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks[] = $label; echo 'PASS ' . $label . "\n";
};
$cookieJar = $s . '/cookies-' . bin2hex(random_bytes(6)) . '.txt';
$call = static function (string $path, ?array $post = null, bool $session = true, array $headers = []) use ($s, $base, $cookieJar): array {
    if (strpos($path, '://') !== false || substr($path, 0, 1) === '/') throw new RuntimeException('RELATIVE_ENDPOINT_ONLY');
    $c = curl_init($base . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>25,CURLOPT_CAINFO=>$s . '/tls.crt',CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$headers]);
    if ($session) curl_setopt_array($c, [CURLOPT_COOKIEFILE=>$cookieJar,CURLOPT_COOKIEJAR=>$cookieJar]);
    if ($post !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $post);
    $response = curl_exec($c); $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE); $n = curl_getinfo($c, CURLINFO_HEADER_SIZE);
    if (!is_string($response)) throw new RuntimeException('HTTPS_REQUEST_FAILED');
    curl_close($c);
    return ['code'=>$code,'headers'=>substr($response, 0, $n),'body'=>substr($response, $n)];
};
try {
    $login = $call('login');
    $check($login['code'] === 200 && strpos($login['body'], 'name="identifier"') !== false, 'production login page renders');
    $check(stripos($login['headers'], 'secure') !== false && stripos($login['headers'], 'httponly') !== false && stripos($login['headers'], 'samesite=lax') !== false, 'HTTPS session cookie has secure flags');
    $spoof = $call('login', null, false, ['Host: attacker.invalid']);
    $check($spoof['code'] === 200 && strpos($spoof['body'], $base . 'auth/do_login') !== false && strpos($spoof['body'], 'attacker.invalid') === false, 'canonical URL ignores forged Host');
    foreach (['.user.ini','.git/config','application/config/database.php','system/core/CodeIgniter.php','system/database/DB.php','sql/baseline/2026-09-05_clean_install_schema.sql','tools/tests/c3_linux_web_acceptance.php','vendor/autoload.php','uploads/evil.php'] as $path) {
        $r = $call($path, null, false); $check($r['code'] === 404, 'private path denied: ' . $path);
    }
    $unauth = $call('system/business-profile', null, false);
    $check(in_array($unauth['code'], [302,303,307], true) && stripos($unauth['headers'], 'Location: ' . $base . 'login') !== false, 'profile requires authentication');
    $owner = json_decode((string)file_get_contents($fixture . '/disposable-owner.json'), true, 32, JSON_THROW_ON_ERROR);
    $auth = $call('auth/do_login', ['identifier'=>$owner['username'],'password'=>$owner['password']]); unset($owner);
    file_put_contents($s . '/last-auth-response.json', json_encode($auth));
    if (stripos($auth['headers'], 'Location: ' . $base . 'login') !== false) {
        file_put_contents($s . '/failed-login.html', $call('login')['body']);
    }
    $check(in_array($auth['code'], [302,303], true) && stripos($auth['headers'], 'Location: ' . $base . 'login') === false, 'first owner login accepted');
    $profile = $call('system/business-profile');
    $check($profile['code'] === 200 && preg_match('/name="business_profile_csrf" value="([a-f0-9]+)"/', $profile['body'], $m) === 1, 'authenticated profile and CSRF form render');
    $csrf = $m[1];
    $bad = $call('system/business-profile', ['display_name'=>'Rejected']);
    $check($bad['code'] === 403, 'missing profile CSRF rejected');
    $fields = ['business_profile_csrf'=>$csrf,'display_name'=>'Kedai Uji C3','legal_name'=>'Usaha Uji Instalasi',
        'short_name'=>'Uji C3','address'=>'Alamat simulasi','email'=>'owner@example.invalid','phone'=>'','tax_id'=>'',
        'website_url'=>'https://example.invalid','timezone'=>'Asia/Jakarta','locale'=>'id_ID','currency_code'=>'IDR',
        'document_footer'=>'Dokumen percobaan instalasi','menu_book_template'=>'customer'];
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV1sAAAAASUVORK5CYII=');
    file_put_contents($s . '/fixture-logo.png', $png);
    $fields['logo_file'] = new CURLFile($s . '/fixture-logo.png', 'image/png', 'trial-logo.png');
    $saved = $call('system/business-profile', $fields);
    $check(in_array($saved['code'], [302,303], true), 'profile save returns redirect');
    $profile = $call('system/business-profile');
    $check($profile['code'] === 200 && strpos($profile['body'], 'Profil usaha tersimpan.') !== false
        && strpos($profile['body'], 'value="Kedai Uji C3"') !== false, 'profile saved through UI with audit');
    $check(preg_match('~assets/uploads/business-profile-logo/[A-Za-z0-9_.-]+[.]png~', $profile['body'], $m) === 1, 'logo upload stored in customer folder');
    $logo = $call($m[0], null, false);
    $check($logo['code'] === 200 && @getimagesizefromstring($logo['body']) !== false, 'saved logo served as image');
    $anonymous = $call('login', null, false);
    $check($anonymous['code'] === 200 && strpos($anonymous['body'], 'Login — Kedai Uji C3') !== false
        && strpos($anonymous['body'], $m[0]) !== false, 'customer branding reaches anonymous login');
    $check(count(glob($s . '/sessions/*') ?: []) > 0, 'session files are in instance-private directory');
    $receipt = ['status'=>'PASS','checks'=>count($checks),'passed'=>$checks,'at'=>date(DATE_ATOM),
        'scope'=>'isolated Linux HTTPS diagnostic; not customer pilot or signed release acceptance','public_listener'=>false];
    file_put_contents($s . '/web-acceptance.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "All " . count($checks) . " Linux HTTPS acceptance checks passed.\n";
} catch (Throwable $e) {
    file_put_contents($s . '/web-acceptance-failure.json', json_encode(['status'=>'FAIL','passed'=>$checks,'reason'=>$e->getMessage()]));
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
}
