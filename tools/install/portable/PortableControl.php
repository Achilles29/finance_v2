<?php
declare(strict_types=1);
require_once __DIR__.'/PortableStore.php';
require_once dirname(__DIR__,2).'/licensing/ControlLicenseProtocol.php';

/** Existing Control license/heartbeat/receipt endpoints; no automatic token issuance endpoint. */
final class PortableControl
{
    public function __construct(private string $root,private string $origin,private $transport=null) { ControlLicenseProtocol::origin($origin); }
    public function post(string $origin,string $path,string $body,array $headers=[]): array
    {
        if($origin!==$this->origin || !in_array($path,[ControlLicenseProtocol::REQUEST_PATH,ControlLicenseProtocol::POLL_PATH,
            ControlLicenseProtocol::RECOVER_PATH,'/api/v1/heartbeats','/api/v1/deployment-receipts'],true))throw new RuntimeException('CONTROL_PATH_INVALID');
        if($this->transport)return ($this->transport)($origin,$path,$body,$headers);
        return $this->http($origin.$path,$body,$headers);
    }
    private function http(string $url,?string $body,array $headers=[]): array
    {
        $c=curl_init($url);$out='';
        curl_setopt_array($c,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_WRITEFUNCTION=>static function($h,string $chunk)use(&$out):int{if(strlen($out)+strlen($chunk)>300000)return 0;$out.=$chunk;return strlen($chunk);}]);
        if($body!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
        $ca=$this->root.'/private/ca.pem';if(is_file($ca)){CustomerPlatform::path($this->root,$ca,true);curl_setopt($c,CURLOPT_CAINFO,$ca);}
        $ok=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
        if($ok===false || ($code>=300&&$code<400))throw new RuntimeException('HTTPS_DELIVERY_UNCERTAIN');
        return ['http'=>$code,'json'=>json_decode($out,true),'body'=>$out];
    }
    public function web(string $base): void
    {
        // Test the real CI login, not just a JSON/config file or a simulated health answer.
        $r=$this->http(rtrim($base,'/').'/login',null);
        if($r['http']!==200 || !str_contains($r['body'],'name="identifier"'))throw new RuntimeException('WEB_HEALTH_FAILED');
    }
    public function send(string $path,array $payload,array $credential,string $idempotency): array
    {
        if(!preg_match('/\A[a-f0-9-]{36}\z/D',$credential['key_id']??'') || !is_string($credential['secret']??null) || strlen($credential['secret'])<32)throw new RuntimeException('MONITORING_CREDENTIAL_INVALID');
        $time=gmdate(DATE_ATOM);$nonce=bin2hex(random_bytes(24));$body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $canonical=implode("\n",['POST',$path,$payload['instance_id'],$credential['key_id'],$time,$nonce,$idempotency,hash('sha256',$body)]);
        $r=$this->post($this->origin,$path,$body,['X-Namua-Instance-ID: '.$payload['instance_id'],'X-Namua-Key-ID: '.$credential['key_id'],
            'X-Namua-Timestamp: '.$time,'X-Namua-Nonce: '.$nonce,'Idempotency-Key: '.$idempotency,'X-Namua-Signature: '.hash_hmac('sha256',$canonical,$credential['secret'])]);
        if(!in_array($r['http'],[200,202],true)||($r['json']['status']??'')!=='accepted')throw new RuntimeException('CONTROL_ACK_REQUIRED');
        return $r['json'];
    }
}
