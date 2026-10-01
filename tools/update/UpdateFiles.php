<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/install/portable/PortableStore.php';
require_once dirname(__DIR__).'/release/ReleasePackagePolicy.php';

/** No shell extraction, links, archive metadata execution or writes outside the private staging tree. */
final class UpdateFiles
{
    public static function writablePath(string $path): bool
    {
        return $path==='config/customer.json'||preg_match('~\A(?:private|storage|public/uploads|public/assets/uploads)/~D',$path)===1;
    }
    public static function inventory(string $root): array
    {
        $bytes=file_get_contents($root.'/RELEASE-MANIFEST.json');
        $m=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);$out=[];
        foreach($m['files']??[]as$e){
            $p=$e['path']??'';
            if(!ReleasePackagePolicy::relativePathValid($p)||self::writablePath($p)||isset($out[$p])
                ||!is_int($e['size']??null)||$e['size']<0||!preg_match('/\A[a-f0-9]{64}\z/D',$e['sha256']??''))throw new RuntimeException('UPDATE_INVENTORY_INVALID');
            $out[$p]=$e;
        }
        if(!$out||count($out)>50000)throw new RuntimeException('UPDATE_INVENTORY_INVALID');
        $out['RELEASE-MANIFEST.json']=['path'=>'RELEASE-MANIFEST.json','size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];
        return $out;
    }
    public static function directory(string $root,string $path,int $mode=0700): void
    {
        if(!str_starts_with($path.'/',$root.'/'))throw new RuntimeException('UPDATE_PATH_UNSAFE');
        if(!is_dir($path)){self::directory($root,dirname($path),$mode);if(!mkdir($path,$mode))throw new RuntimeException('UPDATE_DIRECTORY_FAILED');
            if(PHP_OS_FAMILY==='Linux'&&!chgrp($path,filegroup($root)))throw new RuntimeException('UPDATE_PERMISSION_FAILED');}
        CustomerPlatform::path($root,$path);
    }
    public static function write(string $root,string $path,string $bytes,int $mode=0600): void
    {
        self::directory($root,dirname($path));
        if(file_exists($path)||is_link($path))CustomerPlatform::path($root,$path,true);
        $tmp=dirname($path).'/.update-'.bin2hex(random_bytes(12));$h=null;$mask=umask(0077);
        try{$h=fopen($tmp,'xb');if(!$h||fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h))throw new RuntimeException('UPDATE_WRITE_FAILED');
            if(PHP_OS_FAMILY==='Linux'&&(!chgrp($tmp,filegroup($root))||!chmod($tmp,$mode)))throw new RuntimeException('UPDATE_PERMISSION_FAILED');
            fclose($h);$h=null;if(!rename($tmp,$path))throw new RuntimeException('UPDATE_RENAME_FAILED');
        }finally{if(is_resource($h))fclose($h);if(is_file($tmp))unlink($tmp);umask($mask);clearstatcache();}
    }
    private static function octal(string $s): int
    {
        $s=trim($s," \0");if($s===''||!preg_match('/\A[0-7]+\z/D',$s))throw new RuntimeException('UPDATE_TAR_INVALID');return intval($s,8);
    }
    public static function extract(string $root,string $tar,string $destination,string $expectedHash): array
    {
        CustomerPlatform::installer($root);CustomerPlatform::path($root,$tar,true);
        if(!str_starts_with($destination,$root.'/private/')||file_exists($destination)||is_link($destination)
            ||!hash_equals($expectedHash,hash_file('sha256',$tar))||filesize($tar)>1073741824)throw new RuntimeException('UPDATE_ARCHIVE_INVALID');
        self::directory($root,$destination);$h=fopen($tar,'rb');$seen=[];$size=0;$ended=false;
        $longName=null;
        try{while(!feof($h)){
            $header=fread($h,512);if($header===str_repeat("\0",512)){$ended=true;break;}
            if(strlen($header)!==512||substr($header,257,5)!=='ustar')throw new RuntimeException('UPDATE_TAR_INVALID');
            $sum=self::octal(substr($header,148,8));$checked=substr_replace($header,str_repeat(' ',8),148,8);
            if(array_sum(unpack('C*',$checked))!==$sum)throw new RuntimeException('UPDATE_TAR_INVALID');
            $p=rtrim(substr($header,0,100),"\0");$prefix=rtrim(substr($header,345,155),"\0");if($prefix!=='')$p=$prefix.'/'.$p;
            $type=$header[156];$bytes=self::octal(substr($header,124,12));
            if($type==='L'){
                if($longName!==null||$p!=='././@LongLink'||$bytes<1||$bytes>4096)throw new RuntimeException('UPDATE_TAR_ENTRY_INVALID');
                $raw='';$remaining=$bytes;while($remaining>0){$chunk=fread($h,min($remaining,4096));if($chunk==='')throw new RuntimeException('UPDATE_TAR_TRUNCATED');$raw.=$chunk;$remaining-=strlen($chunk);}
                $padding=(512-$bytes%512)%512;if($padding&&strlen(fread($h,$padding))!==$padding)throw new RuntimeException('UPDATE_TAR_TRUNCATED');
                $longName=rtrim($raw,"\0");if(!ReleasePackagePolicy::relativePathValid($longName))throw new RuntimeException('UPDATE_TAR_ENTRY_INVALID');continue;
            }
            if($longName!==null){$p=$longName;$longName=null;}
            if($type==='5')$p=rtrim($p,'/');
            if(!ReleasePackagePolicy::relativePathValid($p)||self::writablePath($p)||isset($seen[$p])||!in_array($type,["\0",'0','5'],true)
                ||++$size>50000||$bytes>268435456)throw new RuntimeException('UPDATE_TAR_ENTRY_INVALID');
            $seen[$p]=true;
            if($type==='5'){if($bytes!==0)throw new RuntimeException('UPDATE_TAR_INVALID');self::directory($root,$destination.'/'.$p,0750);continue;}
            $raw='';$remaining=$bytes;while($remaining>0){$chunk=fread($h,min($remaining,1048576));if($chunk==='')throw new RuntimeException('UPDATE_TAR_TRUNCATED');$raw.=$chunk;$remaining-=strlen($chunk);}
            self::directory($root,dirname($destination.'/'.$p),0750);self::write($root,$destination.'/'.$p,$raw,0640);
            $padding=(512-$bytes%512)%512;if($padding&&strlen(fread($h,$padding))!==$padding)throw new RuntimeException('UPDATE_TAR_TRUNCATED');
        }
        if(!$ended||$longName!==null)throw new RuntimeException('UPDATE_TAR_TRUNCATED');
        while(!feof($h)){if(trim(fread($h,1048576),"\0")!=='')throw new RuntimeException('UPDATE_TAR_TRAILING_CONTENT');}
        }finally{fclose($h);}
        if(!hash_equals($expectedHash,hash_file('sha256',$tar)))throw new RuntimeException('UPDATE_ARCHIVE_CHANGED');
        $inventory=self::inventory($destination);
        foreach($inventory as$p=>$e)if(!is_file($destination.'/'.$p)||filesize($destination.'/'.$p)!==$e['size']||hash_file('sha256',$destination.'/'.$p)!==$e['sha256'])throw new RuntimeException('UPDATE_INVENTORY_MISMATCH');
        foreach($seen as$p=>$v)if(is_file($destination.'/'.$p)&&!isset($inventory[$p]))throw new RuntimeException('UPDATE_EXTRA_FILE');
        return $inventory;
    }
    /** Under maintenance: preserve customer state; retain every replaced/retired file for recovery. */
    public static function switchCode(string $root,string $candidate,string $backup,array $old,array $next,callable $checkpoint): void
    {
        CustomerPlatform::installer($root);
        if(!str_starts_with($candidate,$root.'/private/')||!str_starts_with($backup,$root.'/private/'))throw new RuntimeException('UPDATE_PATH_UNSAFE');
        self::directory($root,$backup);
        foreach($old as$p=>$e)if(!isset($next[$p])||$next[$p]['sha256']!==$e['sha256']){
            if(self::writablePath($p))throw new RuntimeException('UPDATE_CUSTOMER_STATE_PROTECTED');
            CustomerPlatform::path($root,$root.'/'.$p);
            if(hash_file('sha256',$root.'/'.$p)!==$e['sha256'])throw new RuntimeException('UPDATE_CURRENT_CODE_CHANGED');
            self::directory($root,dirname($backup.'/'.$p));self::write($root,$backup.'/'.$p,file_get_contents($root.'/'.$p));
        }
        // Last inventory replacement is a checkpoint, not a claim of multi-file atomicity.
        $inventory=$next['RELEASE-MANIFEST.json'];unset($next['RELEASE-MANIFEST.json']);$next['RELEASE-MANIFEST.json']=$inventory;
        foreach($next as$p=>$e){
            if(self::writablePath($p)||hash_file('sha256',$candidate.'/'.$p)!==$e['sha256'])throw new RuntimeException('UPDATE_CANDIDATE_CHANGED');
            if(isset($old[$p])&&$old[$p]['sha256']===$e['sha256'])continue;
            if(!isset($old[$p])&&(file_exists($root.'/'.$p)||is_link($root.'/'.$p)))throw new RuntimeException('UPDATE_LOCAL_FILE_CONFLICT');
            $checkpoint($p,'BEFORE');self::directory($root,dirname($root.'/'.$p),0750);self::write($root,$root.'/'.$p,file_get_contents($candidate.'/'.$p),0640);$checkpoint($p,'AFTER');
        }
        foreach(array_diff_key($old,$next)as$p=>$e){$checkpoint($p,'RETIRE');if(!unlink($root.'/'.$p))throw new RuntimeException('UPDATE_RETIRE_FAILED');}
    }
}
