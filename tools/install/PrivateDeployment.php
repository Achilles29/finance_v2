<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/licensing/LicenseAgentFiles.php';

final class PrivateDeployment
{
    public static function directory(string $path): void
    {
        if (PHP_SAPI!=='cli'||!function_exists('posix_geteuid')||posix_geteuid()!==0) throw new RuntimeException('ROOT_CLI_REQUIRED');
        LicenseAgentFiles::securePath($path,dirname(__DIR__,2),true);
        if ((fileperms($path)&0077)!==0) throw new RuntimeException('PRIVATE_DIRECTORY_REQUIRED');
    }
    public static function read(string $file): array
    {
        LicenseAgentFiles::securePath($file,dirname(__DIR__,2));
        if ((fileperms($file)&0077)!==0||filesize($file)>1048576) throw new RuntimeException('PRIVATE_FILE_REQUIRED');
        $v=json_decode((string)file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($v))throw new RuntimeException('JSON_OBJECT_REQUIRED');return $v;
    }
    public static function write(string $file,array $v): void
    {
        LicenseAgentFiles::securePath(dirname($file),dirname(__DIR__,2),true);
        if (is_link($file)|| (file_exists($file)&&(!is_file($file)||fileowner($file)!==0||(fileperms($file)&0077)!==0))) throw new RuntimeException('UNSAFE_OUTPUT');
        $tmp=dirname($file).'/.publish-'.bin2hex(random_bytes(12));$bytes=json_encode($v,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        $h=fopen($tmp,'xb');if(!$h)throw new RuntimeException('STATE_WRITE_FAILED');
        try { if(!chmod($tmp,0600)||fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h)||!rename($tmp,$file))throw new RuntimeException('STATE_WRITE_FAILED'); }
        finally {fclose($h);if(is_file($tmp))unlink($tmp);clearstatcache();}
    }
    public static function uuid(): string
    {
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));
    }
    public static function lock(string $directory)
    {
        self::directory($directory);$path=$directory.'/operation.lock';
        if(is_link($path)||(file_exists($path)&&(fileowner($path)!==0||(fileperms($path)&0077)!==0)))throw new RuntimeException('LOCK_UNSAFE');
        $h=fopen($path,'c+b');if(!$h)throw new RuntimeException('LOCK_UNAVAILABLE');chmod($path,0600);
        if(!flock($h,LOCK_EX|LOCK_NB)){fclose($h);throw new RuntimeException('OPERATION_RUNNING');}return $h;
    }
    public static function run(array $command,string $log,int $timeout=60,array $env=[]): void
    {
        LicenseAgentFiles::securePath(dirname($log),dirname(__DIR__,2),true);
        if (is_link($log)||(file_exists($log)&&(!is_file($log)||fileowner($log)!==0||(fileperms($log)&0077)!==0))) throw new RuntimeException('PROCESS_LOG_UNSAFE');
        if (!file_exists($log)) {
            $mask=umask(0077);
            try {
                $h=fopen($log,'xb');
                if (!$h) throw new RuntimeException('PROCESS_LOG_UNSAFE');
                try { if(!chmod($log,0600))throw new RuntimeException('PROCESS_LOG_UNSAFE'); }
                finally { fclose($h); }
            } finally { umask($mask); }
        }
        $p=proc_open($command,[0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,null,$env?:null);
        if(!is_resource($p))throw new RuntimeException('PROCESS_START_FAILED');$start=microtime(true);
        do { $r=proc_get_status($p);if(!$r['running'])break;if(microtime(true)-$start>$timeout){proc_terminate($p,15);$until=microtime(true)+2;do{$r=proc_get_status($p);if(!$r['running'])break;usleep(20000);}while(microtime(true)<$until);if($r['running'])proc_terminate($p,9);proc_close($p);throw new RuntimeException('PROCESS_TIMEOUT');}usleep(20000); }while(true);
        $exit=proc_close($p);if(($r['exitcode']>=0?$r['exitcode']:$exit)!==0)throw new RuntimeException('PROCESS_FAILED_SEE_PRIVATE_LOG');
    }
}
