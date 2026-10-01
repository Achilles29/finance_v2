<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/application/libraries/CustomerPlatform.php';
require_once dirname(__DIR__,2).'/licensing/LicenseStateStore.php';

/** An installer-owned store; web can only read the license cache, never the agent secret. */
final class PortableStore implements LicenseStateStore
{
    public function __construct(private string $root, private string $relative)
    {
        if (!in_array($relative,['private','private/agent','storage/license','storage/setup'],true)) throw new RuntimeException('STORE_INVALID');
        CustomerPlatform::path($root,$root.'/'.$relative);
    }
    private function file(string $name): string
    {
        if (!preg_match('/\A[a-z][a-z0-9_.-]{0,99}\z/D',$name) || str_contains($name,'..')) throw new RuntimeException('STORE_FILENAME_INVALID');
        return $this->root.'/'.$this->relative.'/'.$name;
    }
    public function exists(string $name): bool { return file_exists($this->file($name)) || is_link($this->file($name)); }
    public function read(string $name, int $mode=0600): array { return CustomerPlatform::document($this->root,$this->file($name)); }
    public function write(string $name, array $value, int $mode=0600): void
    {
        self::writeFile($this->root,$this->file($name),$value,$mode);
    }
    public static function writeFile(string $root,string $path,array $value,int $mode=0600): void
    {
        self::writer($root); CustomerPlatform::path($root,dirname($path));
        if (file_exists($path)||is_link($path)) CustomerPlatform::path($root,$path,true);
        $mask=umask(0077);$tmp=dirname($path).'/.write-'.bin2hex(random_bytes(16));
        $h=fopen($tmp,'xb');
        try {
            if (!$h) throw new RuntimeException('STATE_WRITE_FAILED');
            $bytes=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if (strlen($bytes)>300000 || fwrite($h,$bytes)!==strlen($bytes) || !fflush($h) || !fsync($h)) throw new RuntimeException('STATE_WRITE_FAILED');
            if (PHP_OS_FAMILY==='Linux' && (!chgrp($tmp,filegroup($root)) || !chmod($tmp,$mode))) throw new RuntimeException('STATE_PERMISSION_FAILED');
            fclose($h);$h=null;
            if (!rename($tmp,$path)) throw new RuntimeException('STATE_PUBLISH_FAILED');
        } finally { if (is_resource($h))fclose($h);if(is_file($tmp))unlink($tmp);umask($mask);clearstatcache(); }
        CustomerPlatform::path($root,$path,true);
    }
    public static function writer(string $root): void
    {
        CustomerPlatform::installer($root);
    }
    public function lock(string $name='operation.lock')
    {
        self::writer($this->root);$path=$this->file($name);
        if(file_exists($path)||is_link($path))CustomerPlatform::path($this->root,$path,true);
        $mask=umask(0077);try{$h=fopen($path,'c+b');}finally{umask($mask);}
        if (!$h || !flock($h,LOCK_EX|LOCK_NB)) { if($h)fclose($h);throw new RuntimeException('OPERATION_RUNNING'); }
        return $h;
    }
}
