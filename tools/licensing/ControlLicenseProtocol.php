<?php
declare(strict_types=1);

/** Finance adapter for the existing Control API. No customer/catalog writes. */
final class ControlLicenseProtocol
{
    public const AGENT_VERSION = '0.1.0';
    public const REQUEST_PATH = '/api/v1/license-activations';
    public const POLL_PATH = '/api/v1/license-activations/status';
    public const RECOVER_PATH = '/api/v1/license-activations/recover';

    public static function origin(string $value): string
    {
        $parts = parse_url($value);
        if (!is_array($parts) || !filter_var($value, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
            || !isset($parts['host']) || !in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7f]/', $value)) throw new RuntimeException('CONTROL_ORIGIN_INVALID');
        return rtrim($value, '/');
    }

    public static function activation(array $identity, string $publicKey, string $fingerprint, string $code): array
    {
        if (preg_match('/\Anla_[A-Za-z0-9_-]{40}\z/D', $code) !== 1
            || preg_match('/\A[a-z0-9][a-z0-9_-]{2,79}\z/D', $identity['instance_id'] ?? '') !== 1
            || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/D', $identity['installation_id'] ?? '') !== 1
            || strlen($publicKey) !== 32 || ($identity['instance_public_key_sha256'] ?? '') !== hash('sha256', $publicKey)
            || preg_match('/\A[a-f0-9]{64}\z/D', $fingerprint) !== 1) throw new RuntimeException('ACTIVATION_INPUT_INVALID');
        return ['activation_code'=>$code,'instance_id'=>$identity['instance_id'],'product_code'=>'NAMUA_FINANCE',
            'installation_id'=>$identity['installation_id'],'instance_public_key_base64'=>base64_encode($publicKey),
            'machine_fingerprint_sha256'=>$fingerprint,'platform'=>PHP_OS_FAMILY==='Windows'?'windows-amd64':'linux-amd64','agent_version'=>self::AGENT_VERSION];
    }

    public static function poll(array $activation, string $secret, int $now, string $nonce): array
    {
        if (strlen($secret) !== 64 || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/D', $activation['activation_id'] ?? '') !== 1
            || preg_match('/\Anlp_[A-Za-z0-9_-]{40,80}\z/D', $activation['poll_token'] ?? '') !== 1
            || preg_match('/\A[A-Za-z0-9_-]{16,100}\z/D', $nonce) !== 1) throw new RuntimeException('POLL_INPUT_INVALID');
        $body = json_encode(['activation_id'=>$activation['activation_id'],'poll_token'=>$activation['poll_token']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = gmdate(DATE_ATOM, $now);
        $canonical = implode("\n", ['POST',self::POLL_PATH,$activation['activation_id'],$timestamp,$nonce,hash('sha256', $body)]);
        return ['body'=>$body,'headers'=>['X-Namua-Timestamp: ' . $timestamp,'X-Namua-Nonce: ' . $nonce,
            'X-Namua-Signature: ' . base64_encode(sodium_crypto_sign_detached($canonical, $secret))]];
    }

    public static function recovery(array $payload, string $secret, int $now, string $nonce): array
    {
        if (strlen($secret)!==64 || preg_match('/\A[A-Za-z0-9_-]{16,100}\z/D',$nonce)!==1) throw new RuntimeException('RECOVERY_INPUT_INVALID');
        $body=json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); $timestamp=gmdate(DATE_ATOM,$now);
        $canonical=implode("\n",['POST',self::RECOVER_PATH,$timestamp,$nonce,hash('sha256',$body)]);
        return ['body'=>$body,'headers'=>['X-Namua-Timestamp: '.$timestamp,'X-Namua-Nonce: '.$nonce,
            'X-Namua-Signature: '.base64_encode(sodium_crypto_sign_detached($canonical,$secret))]];
    }

    public static function post(string $origin, string $path, string $body, array $headers = [], ?string $caFile = null): array
    {
        $origin = self::origin($origin);
        if (!in_array($path, [self::REQUEST_PATH,self::POLL_PATH,self::RECOVER_PATH], true) || strlen($body) > 16384) throw new RuntimeException('REQUEST_INVALID');
        $c = curl_init($origin . $path); $response = '';
        curl_setopt_array($c, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'Finance-License-Agent/' . self::AGENT_VERSION,
            CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
            CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 300000) return 0;
                $response .= $chunk; return strlen($chunk);
            }]);
        if ($caFile !== null) {
            require_once __DIR__.'/LicenseAgentFiles.php';
            LicenseAgentFiles::securePath($caFile,dirname(__DIR__,2));
            curl_setopt($c,CURLOPT_CAINFO,$caFile);
        }
        $ok = curl_exec($c); $status = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE); curl_close($c);
        if ($ok === false) throw new RuntimeException('CONTROL_TRANSPORT_UNAVAILABLE');
        // Never forward activation credentials or follow redirects to a different origin.
        if ($status >= 300 && $status < 400) throw new RuntimeException('CONTROL_REDIRECT_REJECTED');
        try { $json = json_decode($response, true, 32, JSON_THROW_ON_ERROR); } catch (Throwable $e) { throw new RuntimeException('CONTROL_RESPONSE_INVALID'); }
        if (!is_array($json)) throw new RuntimeException('CONTROL_RESPONSE_INVALID');
        return ['http'=>$status,'json'=>$json];
    }
}
