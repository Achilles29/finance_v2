<?php
declare(strict_types=1);
require_once __DIR__ . '/ControlLicenseProtocol.php';
require_once __DIR__ . '/LicenseAgentFiles.php';
require_once dirname(__DIR__, 2) . '/application/libraries/Control_license_cache.php';

final class FinanceLicenseAgent
{
    private LicenseStateStore $private;
    private LicenseStateStore $public;
    private $transport;
    private $clock;
    private string $fingerprint;

    public function __construct(LicenseStateStore $private, LicenseStateStore $public, string $fingerprint, ?callable $transport = null, ?callable $clock = null)
    {
        $this->private = $private; $this->public = $public; $this->fingerprint = $fingerprint;
        $this->transport = $transport ?? [ControlLicenseProtocol::class, 'post'];
        $this->clock = $clock ?? 'time';
    }

    public function initialize(string $instance, string $origin, array $trust): array
    {
        $lock = $this->private->lock();
        try {
            foreach (['identity.json','trust.json','runtime.json'] as $name) if ($this->public->exists($name)) throw new RuntimeException('ALREADY_PROVISIONED');
            if ($this->private->exists('agent.json')) throw new RuntimeException('ALREADY_PROVISIONED');
            $origin = ControlLicenseProtocol::origin($origin);
            $key = sodium_crypto_sign_keypair(); $secret = sodium_crypto_sign_secretkey($key); $pk = sodium_crypto_sign_publickey($key);
            $id = random_bytes(16); $id[6] = chr((ord($id[6]) & 0x0f) | 0x40); $id[8] = chr((ord($id[8]) & 0x3f) | 0x80);
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($id), 4));
            $identity = ['instance_id'=>$instance,'installation_id'=>$uuid,'instance_public_key_sha256'=>hash('sha256', $pk)];
            ControlLicenseProtocol::activation($identity, $pk, $this->fingerprint, 'nla_' . str_repeat('0', 40));
            $pub = base64_decode((string)($trust['public_key_base64'] ?? ''), true);
            if (($trust['purpose'] ?? '') !== 'NAMUA_LICENSE_SIGNING' || ($trust['schema'] ?? null) !== 1
                || ($trust['product_code'] ?? '') !== 'NAMUA_FINANCE' || ($trust['status'] ?? '') !== 'ACTIVE'
                || ($trust['algorithm'] ?? '') !== 'Ed25519' || !is_string($trust['key_id'] ?? null) || $trust['key_id'] === ''
                || !is_string($pub) || strlen($pub) !== 32 || ($trust['public_key_sha256'] ?? '') !== hash('sha256', $pub)
                || array_diff(array_keys($trust), ['schema','purpose','product_code','status','algorithm','key_id',
                    'public_key_base64','public_key_sha256','created_at'])) throw new RuntimeException('PUBLIC_TRUST_REQUIRED');
            // Identity/key are durable BEFORE the one-time activation request is made.
            $this->private->write('agent.json', ['schema'=>1,'origin'=>$origin,'identity'=>$identity,
                'fingerprint'=>$this->fingerprint,'secret_key_base64'=>base64_encode($secret),'activation_state'=>'NOT_REQUESTED'], 0600);
            sodium_memzero($secret); sodium_memzero($key);
            $this->public->write('identity.json', $identity, 0640);
            $this->public->write('trust.json', $trust, 0640);
            $this->public->write('runtime.json', Control_license_cache::initial($identity), 0640);
            return ['status'=>'PROVISIONED','identity'=>$identity,'activated'=>false];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function state(): array
    {
        $s = $this->private->read('agent.json', 0600);
        if (($s['schema'] ?? null) !== 1 || ($s['fingerprint'] ?? '') !== $this->fingerprint
            || ($s['identity'] ?? null) !== $this->public->read('identity.json', 0640)) throw new RuntimeException('INSTALLATION_STATE_MISMATCH');
        $secret = base64_decode((string)($s['secret_key_base64'] ?? ''), true);
        if (!is_string($secret) || strlen($secret) !== 64
            || hash('sha256', sodium_crypto_sign_publickey_from_secretkey($secret)) !== $s['identity']['instance_public_key_sha256']) throw new RuntimeException('INSTANCE_KEY_INVALID');
        sodium_memzero($secret);
        return $s;
    }

    public function activate(string $code): array
    {
        $lock = $this->private->lock();
        try {
            $s = $this->state();
            if (!in_array($s['activation_state'] ?? '', ['NOT_REQUESTED','INPUT_REJECTED','DENIED'], true)) throw new RuntimeException('ACTIVATION_ALREADY_ATTEMPTED');
            $attempts=$s['activation_attempts']??[];$codeHash=hash('sha256',$code);
            if(in_array($codeHash,array_column($attempts,'credential_sha256'),true))throw new RuntimeException('ACTIVATION_REPLACEMENT_REQUIRED');
            if(count($attempts)>=200)throw new RuntimeException('ACTIVATION_HISTORY_REQUIRES_REVIEW');
            $secret = base64_decode($s['secret_key_base64'], true);
            $payload = ControlLicenseProtocol::activation($s['identity'], sodium_crypto_sign_publickey_from_secretkey($secret), $this->fingerprint, $code);
            sodium_memzero($secret);
            $s['activation_state'] = 'REQUEST_UNCERTAIN';
            $attempts[]=['credential_sha256'=>$codeHash,'at'=>gmdate(DATE_ATOM,($this->clock)()),'status'=>'REQUEST_UNCERTAIN'];
            $s['activation_attempts']=$attempts;
            $this->private->write('agent.json', $s, 0600);
            // A timeout may have consumed the code. Never auto-create a new identity or retry blindly.
            $r = ($this->transport)($s['origin'], ControlLicenseProtocol::REQUEST_PATH, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), []);
            unset($payload, $code);
            $json = $r['json'] ?? [];
            if ($r['http'] === 202 && in_array($json['status'] ?? '', ['PENDING','accepted'], true)) {
                $activation = ['activation_id'=>$json['activation_id'] ?? '', 'poll_token'=>$json['poll_token'] ?? ''];
                // Validate response credentials using the actual poll contract, before storing them.
                $secret = base64_decode($s['secret_key_base64'], true);
                ControlLicenseProtocol::poll($activation, $secret, ($this->clock)(), bin2hex(random_bytes(16))); sodium_memzero($secret);
                $s['activation'] = $activation; $s['activation_state'] = 'PENDING';
                $s['activation_attempts'][count($attempts)-1]['status']='PENDING';
                $this->private->write('agent.json', $s, 0600);
                $cache = $this->public->read('runtime.json', 0640);
                $cache = Control_license_cache::transition($cache, 202, ['status'=>'PENDING'], $this->public->read('trust.json', 0640), $s['identity'], ($this->clock)());
                $this->public->write('runtime.json', $cache, 0640);
                return ['status'=>'PENDING','activation_id'=>$activation['activation_id']];
            }
            if (in_array($r['http'], [401,422], true) && in_array($json['code'] ?? '', ['activation_code_invalid','payload_schema_invalid','payload_invalid'], true)) {
                $s['activation_state'] = 'INPUT_REJECTED';
                $s['activation_attempts'][count($attempts)-1]['status']='INPUT_REJECTED';
                $this->private->write('agent.json', $s, 0600);
                throw new RuntimeException('ACTIVATION_CREDENTIAL_REJECTED');
            }
            if($r['http']===409&&($json['code']??'')==='instance_limit_exceeded'){
                $s['activation_state']='DENIED';$s['activation_attempts'][count($attempts)-1]['status']='INSTANCE_LIMIT_EXCEEDED';
                $this->private->write('agent.json',$s,0600);throw new RuntimeException('INSTANCE_LIMIT_EXCEEDED');
            }
            throw new RuntimeException('ACTIVATION_REQUIRES_REVIEW');
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public static function recoveryMessage(string $code): string
    {
        if($code==='INSTANCE_LIMIT_EXCEEDED')return 'Kuota instalasi server ditolak Control. Minta operator memeriksa slot; jangan buat ulang installation ID, fingerprint atau kunci agent. Jika kuota sudah tersedia, gunakan kode aktivasi pengganti pada agent yang sama.';
        if(in_array($code,['ACTIVATION_CREDENTIAL_REJECTED','ACTIVATION_REPLACEMENT_REQUIRED'],true))return 'Kode aktivasi tidak valid, kedaluwarsa, dicabut atau sudah digunakan; bukan berarti hak instalasi berakhir. Minta kode pengganti dari operator Control dan jalankan activate --code-file pada direktori agent yang sama. Journal, installation ID dan kunci tetap dipertahankan; tidak ada SQL yang diulang.';
        return 'Pertahankan state agent. Jika respons aktivasi hilang, gunakan recover dengan credential percobaan tersebut untuk memulihkan respons terikat identitas, bukan untuk melewati expiry. Jika Control menolak, minta pemeriksaan operator; jangan reset identitas atau mencoba credential baru pada REQUEST_UNCERTAIN.';
    }

    public function poll(): array
    {
        $lock = $this->private->lock();
        try {
            $s = $this->state();
            if (!isset($s['activation'])) throw new RuntimeException('ACTIVATION_NOT_AVAILABLE');
            $cache = $this->public->read('runtime.json', 0640); $now = ($this->clock)();
            Control_license_cache::assertState($cache, $s['identity'], $now);
            $trust = $this->public->read('trust.json', 0640);
            $secret = base64_decode($s['secret_key_base64'], true);
            $request = ControlLicenseProtocol::poll($s['activation'], $secret, $now, bin2hex(random_bytes(24))); sodium_memzero($secret);
            $error = null;
            try {
                $r = ($this->transport)($s['origin'], ControlLicenseProtocol::POLL_PATH, $request['body'], $request['headers']);
                $cache = Control_license_cache::transition($cache, $r['http'], $r['json'], $trust, $s['identity'], $now);
            } catch (Throwable $e) {
                // Preserve the valid lease, but persist the observed clock even after rejected replay/network failure.
                $cache = Control_license_cache::transition($cache, 0, [], $trust, $s['identity'], $now);
                $error = $e instanceof RuntimeException && preg_match('/\A[A-Z_]+\z/D', $e->getMessage()) ? $e->getMessage() : 'SYNC_REJECTED';
            }
            $this->public->write('runtime.json', $cache, 0640);
            $v = Control_license_cache::verification($cache, $trust, $s['identity'], $now);
            return ['status'=>$v['status'],'verified'=>$v['verified'],'connection'=>$cache['connection'],
                'code'=>$error ?? $v['code'],'enforcement_changed'=>false];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function recover(string $code): array
    {
        $lock=$this->private->lock();
        try {
            $s=$this->state();
            if (($s['activation_state']??'')!=='REQUEST_UNCERTAIN' || isset($s['activation'])) throw new RuntimeException('RECOVERY_NOT_REQUIRED');
            $secret=base64_decode($s['secret_key_base64'],true);
            $payload=ControlLicenseProtocol::activation($s['identity'],sodium_crypto_sign_publickey_from_secretkey($secret),$this->fingerprint,$code);
            $request=ControlLicenseProtocol::recovery($payload,$secret,($this->clock)(),bin2hex(random_bytes(24))); sodium_memzero($secret);
            $r=($this->transport)($s['origin'],ControlLicenseProtocol::RECOVER_PATH,$request['body'],$request['headers']);
            unset($request,$payload,$code);
            if (($r['http']??0)!==202 || ($r['json']['status']??'')!=='PENDING' || ($r['json']['recovered']??false)!==true) throw new RuntimeException('RECOVERY_REQUIRES_REVIEW');
            $activation=['activation_id'=>$r['json']['activation_id']??'','poll_token'=>$r['json']['poll_token']??''];
            $secret=base64_decode($s['secret_key_base64'],true);
            ControlLicenseProtocol::poll($activation,$secret,($this->clock)(),bin2hex(random_bytes(16))); sodium_memzero($secret);
            $s['activation']=$activation; $s['activation_state']='PENDING';
            if(!empty($s['activation_attempts'])){$last=count($s['activation_attempts'])-1;$s['activation_attempts'][$last]['recovered_at']=gmdate(DATE_ATOM,($this->clock)());}
            $this->private->write('agent.json',$s,0600);
            return ['status'=>'PENDING','recovered'=>true,'activation_id'=>$activation['activation_id'],'identity_changed'=>false];
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
}
