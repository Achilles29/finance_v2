<?php
declare(strict_types=1);
require dirname(__DIR__) . '/licensing/FinanceLicenseAgent.php';
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $message);
    $checks++; echo "PASS {$message}\n";
};
$reject = static function (callable $fn, string $code, string $message) use ($check): void {
    try { $fn(); } catch (RuntimeException $e) { $check($e->getMessage() === $code, $message); return; }
    $check(false, $message);
};
foreach (['http://control.invalid','https://user:pass@control.invalid','https://control.invalid/api','https://control.invalid/?token=x','https://control.invalid/#x'] as $url) {
    $reject(fn() => ControlLicenseProtocol::origin($url), 'CONTROL_ORIGIN_INVALID', 'unsafe Control URL rejected');
}
$check(ControlLicenseProtocol::origin('https://control.example.invalid/') === 'https://control.example.invalid', 'Control endpoint pinned to explicit HTTPS origin');
$keypair = sodium_crypto_sign_keypair(); $secret = sodium_crypto_sign_secretkey($keypair); $publicKey = sodium_crypto_sign_publickey($keypair);
$trust = ['schema'=>1,'purpose'=>'NAMUA_LICENSE_SIGNING','product_code'=>'NAMUA_FINANCE','algorithm'=>'Ed25519','key_id'=>'fixture-issuer',
    'public_key_base64'=>base64_encode($publicKey),'public_key_sha256'=>hash('sha256', $publicKey),'status'=>'ACTIVE'];
$now = 1800000000;
$makeLicense = static function (array $identity, int $issued, array $extra = []) use ($secret): array {
    $payload = array_replace($identity + ['schema'=>1,'license_id'=>'fixture-' . $issued,'key_id'=>'fixture-issuer','product'=>'NAMUA_FINANCE',
        'edition'=>'STARTER_POS','metric'=>'SERVER_INSTANCE','rights_model'=>'PERPETUAL','entitlements'=>['POS_CORE'=>true,'PAYROLL'=>false],
        'issued_at'=>gmdate(DATE_ATOM, $issued),'expires_at'=>gmdate(DATE_ATOM, $issued+3600),'grace_until'=>gmdate(DATE_ATOM, $issued+7200),
        'maintenance_ends_at'=>gmdate(DATE_ATOM, $issued-86400)], $extra);
    $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $envelope = ['schema'=>1,'algorithm'=>'Ed25519','key_id'=>'fixture-issuer','payload_base64'=>base64_encode($raw),
        'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n" . hash('sha256', $raw), $secret))];
    return ['http'=>200,'json'=>['status'=>'ACTIVE','license'=>$envelope,'token_sha256'=>hash('sha256', json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))]];
};
$identity = ['instance_id'=>'fixture-instance','installation_id'=>'00000000-0000-4000-8000-000000000001','instance_public_key_sha256'=>hash('sha256', $publicKey)];
$request = ControlLicenseProtocol::activation($identity, $publicKey, hash('sha256', 'fixture'), 'nla_' . str_repeat('A', 40));
$check(count($request) === 8 && $request['product_code'] === 'NAMUA_FINANCE' && $request['platform'] === 'linux-amd64', 'activation uses exact eight-field Finance contract');
$activation = ['activation_id'=>'00000000-0000-4000-8000-000000000002','poll_token'=>'nlp_' . str_repeat('A',48)];
$nonce = str_repeat('n', 32);
$poll = ControlLicenseProtocol::poll($activation, $secret, $now, $nonce);
$canonical = implode("\n", ['POST','/api/v1/license-activations/status',$activation['activation_id'],gmdate(DATE_ATOM,$now),$nonce,hash('sha256',$poll['body'])]);
$signature = base64_decode(substr($poll['headers'][2], strlen('X-Namua-Signature: ')), true);
$check(sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey), 'poll signature matches Control canonical bytes');
$check(!sodium_crypto_sign_verify_detached($signature, $canonical . 'changed', $publicKey), 'poll body/path/nonce cannot be changed');
$recovery = ControlLicenseProtocol::recovery($request, $secret, $now, $nonce);
$recoveryWire = implode("\n", ['POST',ControlLicenseProtocol::RECOVER_PATH,gmdate(DATE_ATOM,$now),$nonce,hash('sha256',$recovery['body'])]);
$recoverySignature = base64_decode(substr($recovery['headers'][2],strlen('X-Namua-Signature: ')),true);
$check(sodium_crypto_sign_verify_detached($recoverySignature,$recoveryWire,$publicKey), 'recovery proves ownership with persisted instance key');
$check(!sodium_crypto_sign_verify_detached($recoverySignature,str_replace('/recover','/status',$recoveryWire),$publicKey), 'recovery proof cannot be replayed on poll endpoint');
$cache = Control_license_cache::initial($identity);
$r = $makeLicense($identity, $now-30);
$cache = Control_license_cache::transition($cache, $r['http'], $r['json'], $trust, $identity, $now);
$check(Control_license_cache::verification($cache,$trust,$identity,$now)['status'] === 'ACTIVE', 'signed lease cached despite expired maintenance');
$same = Control_license_cache::transition($cache, 200, $r['json'], $trust, $identity, $now+1);
$check($same['payload_sha256'] === $cache['payload_sha256'], 'same signed document idempotent');
$offline = Control_license_cache::transition($same, 0, [], $trust,$identity,$now+3600);
$check($offline['envelope'] === $same['envelope'] && Control_license_cache::verification($offline,$trust,$identity,$now+3600)['status'] === 'GRACE', 'offline failure retains signed cache and enters grace by signed dates');
$check(Control_license_cache::verification($offline,$trust,$identity,$now+7200)['status'] === 'RESTRICTED', 'offline cache does not invent an unlimited lease');
$older = $makeLicense($identity, $now-60);
$reject(fn()=>Control_license_cache::transition($cache,200,$older['json'],$trust,$identity,$now+1),'LEASE_REPLAY','older signed lease rejected');
$conflict = $makeLicense($identity,$now-30,['edition'=>'ENTERPRISE']);
$reject(fn()=>Control_license_cache::transition($cache,200,$conflict['json'],$trust,$identity,$now+1),'LEASE_SEQUENCE_CONFLICT','same timestamp different entitlement rejected');
$tampered = $r['json']; $tampered['license']['signature_base64'] = base64_encode(random_bytes(64));
$reject(fn()=>Control_license_cache::transition($cache,200,$tampered,$trust,$identity,$now+1),'TOKEN_HASH_MISMATCH','tampered response rejected before storage');
$badSignature = $tampered; $badSignature['token_sha256'] = hash('sha256',json_encode($badSignature['license'], JSON_UNESCAPED_SLASHES));
$reject(fn()=>Control_license_cache::transition($cache,200,$badSignature,$trust,$identity,$now+1),'LICENSE_DOCUMENT_INVALID','recomputed envelope checksum cannot replace signature');
$revoked = Control_license_cache::transition($cache,403,['code'=>'license_revoked'],$trust,$identity,$now+1);
$check(Control_license_cache::verification($revoked,$trust,$identity,$now+1)['status'] === 'REVOKED' && $revoked['envelope'] === $cache['envelope'], 'revocation retained without deleting signed evidence');
$reject(fn()=>Control_license_cache::transition($revoked,200,$r['json'],$trust,$identity,$now+2),'REVOKED_LEASE_REPLAY','revoked lease cannot be reactivated by replay');
$renewed = $makeLicense($identity,$now+3);
$renewedCache = Control_license_cache::transition($revoked,200,$renewed['json'],$trust,$identity,$now+3);
$check(Control_license_cache::verification($renewedCache,$trust,$identity,$now+3)['status'] === 'ACTIVE', 'new issuance after revoke may restore authority');
$check(Control_license_cache::verification($cache,$trust,$identity,$now-301)['code'] === 'CLOCK_ROLLBACK','backward clock rejected across persisted cache');
$drift = $cache; $drift['issued_at']--;
$check(Control_license_cache::verification($drift,$trust,$identity,$now)['code'] === 'CACHE_WATERMARK_MISMATCH','cache watermark is checked against signed bytes');
if (in_array('--protocol-only', $argv, true)) {
    sodium_memzero($secret); sodium_memzero($keypair);
    echo "All {$checks} protocol/cache checks passed. Filesystem/model acceptance requires a separate full run.\n";
    exit(0);
}

// Concrete filesystem acceptance is Linux/root-only, as is the deployable agent.
// Pure protocol/cache assertions above remain usable in unprivileged development.
if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    echo "Filesystem acceptance NOT RUN: Linux root required.\n"; exit(2);
}
$root = dirname(__DIR__, 2); $base = '/var/lib/finance-license-test-' . bin2hex(random_bytes(8));
$group = posix_getgrnam('www');
if (!$group) throw new RuntimeException('Fixture web group unavailable');
$gid = $group['gid'];
$oldMask = umask(0077); mkdir($base,0750); chmod($base,0750); chgrp($base,$gid);
$remove = static function (string $path) use (&$remove, $base): void {
    if (!str_starts_with($path . '/', $base . '/')) throw new RuntimeException('Unsafe fixture cleanup');
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (scandir($path) ?: [] as $name) if ($name !== '.' && $name !== '..') $remove($path . '/' . $name);
    rmdir($path);
};
try {
    mkdir($base . '/private',0700); mkdir($base . '/public',0750); chmod($base . '/public',0750); chgrp($base . '/public',$gid);
    $privateFiles = new LicenseAgentFiles($base . '/private',$root,0,true);
    $publicFiles = new LicenseAgentFiles($base . '/public',$root,$gid,false);
    $response = ['http'=>202,'json'=>['status'=>'PENDING']]; $calls=0; $captured=[]; $transportFails=false;
    $transport = static function (string $origin,string $path,string $body,array $headers) use (&$response,&$calls,&$captured,&$transportFails,$activation): array {
        $calls++; $captured=[$origin,$path,$body,$headers];
        if ($transportFails) throw new RuntimeException('CONTROL_TRANSPORT_UNAVAILABLE');
        return $path === ControlLicenseProtocol::REQUEST_PATH ? ['http'=>202,'json'=>['status'=>'PENDING']+$activation] : $response;
    };
    $clock = static function () use (&$now): int { return $now; };
    $fingerprint = hash('sha256','agent-fixture-only');
    $agent = new FinanceLicenseAgent($privateFiles,$publicFiles,$fingerprint,$transport,$clock);
    $reject(fn()=>$agent->initialize('fixture-agent','https://control.example.invalid',$trust+['secret_key_base64'=>'DO-NOT-COPY']),
        'PUBLIC_TRUST_REQUIRED','issuer private document cannot be copied into public state');
    $reject(fn()=>$agent->initialize('fixture-agent','https://control.example.invalid',$trust+['unexpected_credential'=>'DO-NOT-COPY']),
        'PUBLIC_TRUST_REQUIRED','unknown trust fields rejected instead of copying possible credentials');
    $provision = $agent->initialize('fixture-agent','https://control.example.invalid',$trust);
    $check($provision['status'] === 'PROVISIONED' && $calls === 0, 'initialization makes no external request or sale');
    $keyBefore = hash_file('sha256',$base . '/private/agent.json');
    $reject(fn()=>$agent->initialize('fixture-agent','https://control.example.invalid',$trust),'ALREADY_PROVISIONED','reinitialization cannot rotate installation key');
    $check(hash_file('sha256',$base . '/private/agent.json') === $keyBefore,'existing identity preserved');
    $requested = $agent->activate('nla_' . str_repeat('A',40));
    $check($requested['status'] === 'PENDING' && !isset($requested['poll_token']),'activation stores credential privately but returns no poll token');
    $check(strpos((string)file_get_contents($base . '/private/agent.json'),'nla_') === false,'activation code is not retained in state');
    $check($agent->poll()['connection'] === 'PENDING','pending Control issuance is supported');
    $response = $makeLicense($provision['identity'],$now-20);
    $check($agent->poll()['verified'] === true,'agent verifies and publishes signed local cache');
    $check($publicFiles->read('runtime.json',0640)['envelope'] === $response['json']['license'],'public cache contains exact signed document');
    $check(strpos((string)file_get_contents($base . '/public/runtime.json'),'nlp_') === false
        && strpos((string)file_get_contents($base . '/public/runtime.json'),'secret_key') === false,'public cache never contains credentials');
    $saved = json_decode((string)file_get_contents($base . '/private/agent.json'),true);
    $clientPub = sodium_crypto_sign_publickey_from_secretkey(base64_decode($saved['secret_key_base64'],true));
    $headers = $captured[3]; $stamp = substr($headers[0],strlen('X-Namua-Timestamp: ')); $sentNonce = substr($headers[1],strlen('X-Namua-Nonce: '));
    $wire = implode("\n",['POST',ControlLicenseProtocol::POLL_PATH,$activation['activation_id'],$stamp,$sentNonce,hash('sha256',$captured[2])]);
    $check(sodium_crypto_sign_verify_detached(base64_decode(substr($headers[2],strlen('X-Namua-Signature: ')),true),$wire,$clientPub),'persisted instance key signs actual poll request');
    $agent = new FinanceLicenseAgent($privateFiles,$publicFiles,$fingerprint,$transport,$clock);
    $response = $makeLicense($provision['identity'],$now-40);
    $replay = $agent->poll();
    $check($replay['code'] === 'LEASE_REPLAY' && $replay['verified'],'restart retains high-water mark and last good lease');
    $now += 3600; $transportFails=true;
    $offlinePoll = $agent->poll();
    $check($offlinePoll['status'] === 'GRACE' && $offlinePoll['connection'] === 'SYNC_UNAVAILABLE','offline after restart continues from signed local lease');
    $now -= 301;
    $reject(fn()=>$agent->poll(),'CLOCK_ROLLBACK','worker rejects backward clock before network request');
    $now += 302; $transportFails=false; $response=['http'=>403,'json'=>['code'=>'license_revoked']];
    $check($agent->poll()['status'] === 'REVOKED','authenticated revocation propagates to local runtime');
    $lock = $privateFiles->lock();
    $reject(fn()=>$agent->poll(),'AGENT_ALREADY_RUNNING','concurrent polling denied'); flock($lock,LOCK_UN); fclose($lock);
    $reject(fn()=>(new FinanceLicenseAgent($privateFiles,$publicFiles,hash('sha256','other-machine'),$transport,$clock))->poll(),'INSTALLATION_STATE_MISMATCH','different machine cannot use copied agent state');
    $reject(fn()=>new LicenseAgentFiles($root,$root,0,true),'STATE_PATH_UNSAFE','webroot cannot contain license state');
    symlink($base . '/public',$base . '/linked');
    $reject(fn()=>new LicenseAgentFiles($base . '/linked',$root,$gid,false),'STATE_PATH_UNSAFE','state symlinks rejected');
    $readable = Control_license_verifier::deployment_document($base . '/public/runtime.json',$root,300000);
    $check($readable !== [],'runtime reader accepts protected external cache');
    chmod($base . '/public/runtime.json',0666); clearstatcache();
    $check(Control_license_verifier::deployment_document($base . '/public/runtime.json',$root,300000) === [],'web-writable cache rejected');
    chmod($base . '/public/runtime.json',0640); clearstatcache();
    // Actual model methods, no CodeIgniter bootstrap and no business database.
    define('BASEPATH', $root . '/system/'); define('FCPATH', $root . '/');
    class CI_Model { public $load; public $db; }
    require $root . '/application/models/License_runtime_model.php';
    $db = new class {
        public int $calls = 0;
        public function table_exists(string $name): bool { $this->calls++; return false; }
        public function __call(string $name, array $args) { throw new RuntimeException('UNEXPECTED_DATABASE_ACCESS'); }
    };
    $newModel = static function () use ($db): License_runtime_model {
        $m = new License_runtime_model(); $m->db = $db;
        $m->load = new class { public function library(string $name): void {} };
        return $m;
    };
    $environment = ['FINANCE_LICENSE_CACHE_FILE'=>$base . '/public/runtime.json',
        'FINANCE_LICENSE_IDENTITY_FILE'=>$base . '/public/identity.json','FINANCE_LICENSE_TRUST_FILE'=>$base . '/public/trust.json'];
    $oldEnvironment = [];
    foreach ($environment as $name=>$value) { $oldEnvironment[$name] = getenv($name); putenv($name . '=' . $value); }
    try {
        $liveNow = time(); $modelResponse = $makeLicense($provision['identity'],$liveNow-10);
        $modelCache = Control_license_cache::transition(Control_license_cache::initial($provision['identity']),200,$modelResponse['json'],$trust,$provision['identity'],$liveNow);
        $publicFiles->write('runtime.json',$modelCache,0640);
        $m = $newModel();
        $check($m->verification()['status'] === 'ACTIVE' && $m->current_license()['source'] === 'DEPLOYMENT_CACHE','actual application model reads signed external cache');
        $check($m->feature_enabled('POS_CORE') && !$m->feature_enabled('PAYROLL') && !$m->feature_enabled('UNKNOWN'),'actual model applies signed boolean grants');
        $check($m->installation()['installation_id'] === $provision['identity']['installation_id'] && $m->synchronization()['connection'] === 'SYNCED','status page receives actual identity and synchronization state');
        $check($db->calls === 0,'managed cache does not read local license tables');
        chmod($base . '/public/runtime.json',0666); clearstatcache(); $m = $newModel();
        $check($m->verification()['code'] === 'MANAGED_CACHE_UNAVAILABLE' && !$m->feature_enabled('POS_CORE') && $db->calls === 0,'unsafe cache cannot fall back to mutable SQL authority');
        chmod($base . '/public/runtime.json',0640); clearstatcache();
        $modelCache['envelope']['signature_base64'] = base64_encode(random_bytes(64)); $publicFiles->write('runtime.json',$modelCache,0640); $m=$newModel();
        $check(!$m->verification()['verified'] && $m->current_license() === [] && $db->calls === 0,'bad signature denied through real model without SQL fallback');
        putenv('FINANCE_LICENSE_CACHE_FILE'); $m=$newModel();
        $check($m->synchronization() === ['configured'=>false] && $m->installation() === [] && $db->calls === 1,'unconfigured installations retain legacy read path');
    } finally {
        foreach ($oldEnvironment as $name=>$value) putenv($value === false ? $name : $name . '=' . $value);
    }
    // Drop privileges in a child so these are OS permission checks, not root is_readable().
    $webUser = posix_getpwnam('www');
    if (!$webUser || !function_exists('pcntl_fork')) throw new RuntimeException('Fixture privilege test unavailable');
    $pid=pcntl_fork();
    if ($pid === -1) throw new RuntimeException('Fixture fork failed');
    if ($pid === 0) {
        if (!posix_initgroups('www',$gid) || !posix_setgid($gid) || !posix_setuid($webUser['uid'])) exit(2);
        clearstatcache();
        exit(is_readable($base . '/public/runtime.json') && !is_writable($base . '/public/runtime.json')
            && !is_readable($base . '/private/agent.json') && !is_writable($base . '/public') ? 0 : 1);
    }
    pcntl_waitpid($pid,$childStatus);
    $check(pcntl_wifexited($childStatus) && pcntl_wexitstatus($childStatus) === 0,'web account reads cache but cannot replace cache or read instance key');
    // An ambiguous first request must stay blocked even in a brand-new agent process.
    mkdir($base . '/private2',0700); mkdir($base . '/public2',0750); chmod($base . '/public2',0750); chgrp($base . '/public2',$gid);
    $p2=new LicenseAgentFiles($base . '/private2',$root,0,true); $v2=new LicenseAgentFiles($base . '/public2',$root,$gid,false);
    $agent2=new FinanceLicenseAgent($p2,$v2,$fingerprint,$transport,$clock); $agent2->initialize('fixture-timeout','https://control.example.invalid',$trust);
    $transportFails=true;
    $reject(fn()=>$agent2->activate('nla_' . str_repeat('B',40)),'CONTROL_TRANSPORT_UNAVAILABLE','activation timeout is reported without losing the instance key');
    $agent2=new FinanceLicenseAgent($p2,$v2,$fingerprint,$transport,$clock);
    $reject(fn()=>$agent2->activate('nla_' . str_repeat('B',40)),'ACTIVATION_ALREADY_ATTEMPTED','ambiguous request cannot consume another code after restart');
    $beforeRecovery=$p2->read('agent.json',0600);
    $transportFails=false;$response=['http'=>202,'json'=>['status'=>'PENDING','recovered'=>true]+$activation];
    $recovered=$agent2->recover('nla_'.str_repeat('B',40));
    $afterRecovery=$p2->read('agent.json',0600);
    $check($recovered['status']==='PENDING'&&!isset($recovered['poll_token']),'recovery stores new credential without printing it');
    $check($beforeRecovery['secret_key_base64']===$afterRecovery['secret_key_base64']&&$beforeRecovery['identity']===$afterRecovery['identity'],'recovery preserves installation and key after process restart');
    // Replacement credentials use the same durable identity and retain every attempt.
    mkdir($base.'/private3',0700);mkdir($base.'/public3',0750);chmod($base.'/public3',0750);chgrp($base.'/public3',$gid);
    $p3=new LicenseAgentFiles($base.'/private3',$root,0,true);$v3=new LicenseAgentFiles($base.'/public3',$root,$gid,false);
    $replacementResponse=['http'=>401,'json'=>['code'=>'activation_code_invalid']];$requests=0;
    $replacementTransport=static function()use(&$replacementResponse,&$requests):array{$requests++;return $replacementResponse;};
    $a3=new FinanceLicenseAgent($p3,$v3,$fingerprint,$replacementTransport,$clock);
    $a3->initialize('fixture-replacement','https://control.example.invalid',$trust);$original=$p3->read('agent.json',0600);
    $reject(fn()=>$a3->activate('malformed'),'ACTIVATION_INPUT_INVALID','malformed credential never reaches Control');
    $check($requests===0,'invalid local activation input makes no network request');
    $reject(fn()=>$a3->activate('nla_'.str_repeat('C',40)),'ACTIVATION_CREDENTIAL_REJECTED','expired/invalid/used code produces recovery guidance');
    $oldAttempt=$p3->read('agent.json',0600)['activation_attempts'][0];
    $reject(fn()=>$a3->activate('nla_'.str_repeat('C',40)),'ACTIVATION_REPLACEMENT_REQUIRED','known rejected code is never reused');
    $check($requests===1,'repeat rejected credential blocked before transport');
    $replacementResponse=['http'=>409,'json'=>['code'=>'instance_limit_exceeded']];
    $reject(fn()=>$a3->activate('nla_'.str_repeat('D',40)),'INSTANCE_LIMIT_EXCEEDED','Control server quota rejection is not converted into activation');
    $check($p3->read('agent.json',0600)['activation_state']==='DENIED'&&!isset($v3->read('runtime.json',0640)['envelope']),'quota denial publishes no license or active cache');
    $replacementResponse=['http'=>202,'json'=>['status'=>'PENDING']+$activation];
    $check($a3->activate('nla_'.str_repeat('E',40))['status']==='PENDING','replacement accepted only after a fresh Control decision');
    $after=$p3->read('agent.json',0600);
    $check(count($after['activation_attempts'])===3&&$after['activation_attempts'][0]===$oldAttempt,'failed attempts retained after successful replacement');
    foreach(['identity','secret_key_base64','fingerprint','origin'] as $field)$check($after[$field]===$original[$field],'replacement preserves '.$field);
    $check(strpos(json_encode($after),'nla_')===false,'history contains hashes, never raw activation credentials');
} finally { $remove($base); umask($oldMask); sodium_memzero($secret); sodium_memzero($keypair); }
echo "All {$checks} Control license agent checks passed.\n";
