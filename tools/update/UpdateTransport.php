<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/install/portable/PortableStore.php';
require_once dirname(__DIR__).'/licensing/ControlLicenseProtocol.php';

/** All traffic uses the existing installation key, never a fresh activation/code or a browser secret. */
final class UpdateTransport
{
    public const PATH='/api/v1/application-updates';
    public function __construct(private string $root,private $transport=null) {}
    public static function signed(array $state,array $payload,int $now,string $nonce): array
    {
        $key=base64_decode((string)($state['secret_key_base64']??''),true);
        if(!is_string($key)||strlen($key)!==64||!preg_match('/\A[a-f0-9]{48}\z/D',$nonce)
            ||hash('sha256',sodium_crypto_sign_publickey_from_secretkey($key))!==($state['identity']['instance_public_key_sha256']??''))throw new RuntimeException('UPDATE_INSTANCE_KEY_INVALID');
        $activation=$state['activation']??[];
        if(!preg_match('/\A[a-f0-9-]{36}\z/D',$activation['activation_id']??'')||!preg_match('/\Anlp_[A-Za-z0-9_-]{40,80}\z/D',$activation['poll_token']??''))throw new RuntimeException('UPDATE_ACTIVATION_REQUIRED');
        $body=json_encode($payload+['activation_id'=>$activation['activation_id'],'poll_token'=>$activation['poll_token']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $time=gmdate(DATE_ATOM,$now);$message=implode("\n",['POST',self::PATH,$activation['activation_id'],$time,$nonce,hash('sha256',$body)]);
        $headers=['X-Namua-Timestamp: '.$time,'X-Namua-Nonce: '.$nonce,'X-Namua-Signature: '.base64_encode(sodium_crypto_sign_detached($message,$key))];
        sodium_memzero($key);return ['body'=>$body,'headers'=>$headers];
    }
    public function request(array $payload,?string $destination=null): array
    {
        PortableStore::writer($this->root);
        $state=(new PortableStore($this->root,'private/agent'))->read('agent.json');
        if(($state['fingerprint']??'')!==CustomerPlatform::fingerprint())throw new RuntimeException('UPDATE_FINGERPRINT_MISMATCH');
        $origin=ControlLicenseProtocol::origin($state['origin']);$signed=self::signed($state,$payload,time(),bin2hex(random_bytes(24)));
        if($this->transport)return ($this->transport)($origin,self::PATH,$signed,$destination);
        $out='';$written=0;$file=null;
        if($destination!==null){
            CustomerPlatform::path($this->root,dirname($destination),true);
            if(file_exists($destination)||is_link($destination))throw new RuntimeException('UPDATE_DOWNLOAD_EXISTS');
            $mask=umask(0077);try{$file=fopen($destination,'xb');}finally{umask($mask);}
            if(!$file)throw new RuntimeException('UPDATE_DOWNLOAD_FAILED');
        }
        $c=curl_init($origin.self::PATH);
        curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$signed['body'],CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$file?300:20,
            CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: '.($file?'application/x-tar':'application/json')],$signed['headers']),
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$out,&$written,$file):int{
                if($written+strlen($chunk)>($file?1073741824:300000))return 0;$written+=strlen($chunk);
                if($file)return fwrite($file,$chunk);$out.=$chunk;return strlen($chunk);
            }]);
        $ca=$this->root.'/private/ca.pem';if(is_file($ca)){CustomerPlatform::path($this->root,$ca,true);curl_setopt($c,CURLOPT_CAINFO,$ca);}
        try{$ok=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);if($file){fflush($file);fsync($file);}}
        finally{curl_close($c);if($file)fclose($file);}
        if($ok===false||$code<200||$code>=300){if($file&&is_file($destination))unlink($destination);throw new RuntimeException('UPDATE_CONTROL_UNAVAILABLE');}
        if($file)return ['http'=>$code,'sha256'=>hash_file('sha256',$destination),'size'=>$written];
        try{$json=json_decode($out,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('UPDATE_CONTROL_RESPONSE_INVALID');}
        if(!is_array($json))throw new RuntimeException('UPDATE_CONTROL_RESPONSE_INVALID');return $json;
    }
}
