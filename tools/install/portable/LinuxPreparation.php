<?php
declare(strict_types=1);
require_once __DIR__.'/SetupService.php';
require_once __DIR__.'/SetupUi.php';

/** Administrative provisioning only. No database access, live activation, PHP install, vhost or daemon changes. */
final class LinuxPreparation
{
    public function __construct(private string $root,private array $options=[])
    {
        if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux')throw new RuntimeException('LINUX_CLI_PREPARATION_REQUIRED');
        foreach(['posix_geteuid','posix_getpwnam','posix_getgrnam','proc_open']as$function)if(!function_exists($function))throw new RuntimeException('SERVER_REQUIREMENTS_MISSING');
        if(posix_geteuid()!==0)throw new RuntimeException('RUN_PREPARATION_WITH_SUDO');
        if(realpath($root)!==$root||is_link($root)||$root==='/'||!is_file($root.'/installer/layout.json'))throw new RuntimeException('NEW_PORTABLE_PACKAGE_REQUIRED');
        if(array_diff(array_keys($options),['web-user','web-group','installer-user','url','cron-binary','cron-spool']))throw new RuntimeException('PREPARATION_OPTION_INVALID');
    }
    public static function command(array $command,string $input='',int $timeout=90): array
    {
        $p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($p))throw new RuntimeException('PREPARATION_COMMAND_FAILED');
        $out='';$err='';$code=-1;$state=['running'=>true];$start=microtime(true);
        try {
            if($input!==''&&fwrite($pipes[0],$input)!==strlen($input))throw new RuntimeException('PREPARATION_COMMAND_FAILED');
            fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
            do {
                $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);$state=proc_get_status($p);
                if(!$state['running']){$code=$state['exitcode'];break;}
                if(microtime(true)-$start>$timeout||strlen($out)+strlen($err)>262144)throw new RuntimeException('PREPARATION_COMMAND_TIMEOUT');
                usleep(20000);
            }while(true);
            $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);
        }finally{
            foreach($pipes as$h)if(is_resource($h))fclose($h);
            if($state['running'])proc_terminate($p);
            $closed=proc_close($p);
        }
        return ['code'=>$code<0?$closed:$code,'out'=>$out,'err'=>$err];
    }
    private static function name(string $name): string
    {
        if(!preg_match('/\A[a-z_][a-z0-9_-]{0,30}\z/D',$name))throw new RuntimeException('SERVICE_ACCOUNT_INVALID');return $name;
    }
    private static function ask(string $question,string $default=''): string
    {
        fwrite(STDOUT,$question.($default!==''?' ['.$default.']':'').': ');
        $line=fgets(STDIN);if($line===false)throw new RuntimeException('PREPARATION_CANCELLED');
        return trim($line)===''?$default:trim($line);
    }
    private static function secureBinary(string $path): string
    {
        $real=realpath($path);
        if($real===false||!is_executable($real)||!is_file($real))throw new RuntimeException('PREPARATION_TOOL_MISSING');
        for($p=$real;;$p=dirname($p)){
            if(fileowner($p)!==0||(fileperms($p)&0022)!==0)throw new RuntimeException('PREPARATION_TOOL_UNSAFE');
            if($p===dirname($p))break;
        }
        return $real;
    }
    private function readRecord(): ?array
    {
        $path=$this->root.'/private/server-preparation.json';if(!file_exists($path)&&!is_link($path))return null;
        if(is_link($path)||realpath($path)!==$path||!is_file($path)||filesize($path)>16384||(fileperms($path)&0027)!==0)throw new RuntimeException('PREPARATION_RECORD_UNSAFE');
        $v=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
        if(!is_array($v)||($v['root']??'')!==$this->root||($v['contract']??'')!=='FINANCE_SERVER_PREPARATION_V1')throw new RuntimeException('PREPARATION_RECORD_UNSAFE');
        return $v;
    }
    public static function cronBlock(string $root,string $php): string
    {
        // Cron interprets '%' even inside quotes. Reject it instead of unsafe escaping.
        if(preg_match('/[\x00-\x1f\x7f%]/',$root.$php))throw new RuntimeException('SCHEDULER_PATH_INVALID');
        $marker=substr(hash('sha256',$root),0,20);$block='# BEGIN FINANCE '.$marker."\n";
        foreach(['tick','license-sync','heartbeat']as$action)$block.='* * * * * '.escapeshellarg($php).' '.escapeshellarg($root.'/tools/install/portable/finance_setup.php').' '.$action." >/dev/null 2>&1\n";
        return $block.'# END FINANCE '.$marker."\n";
    }
    public static function mergeCron(string $old,string $root,string $php): string
    {
        $id=substr(hash('sha256',$root),0,20);$begin='# BEGIN FINANCE '.$id;$end='# END FINANCE '.$id;
        if(substr_count($old,$begin)>1||substr_count($old,$end)>1||substr_count($old,$begin)!==substr_count($old,$end))throw new RuntimeException('SCHEDULER_BLOCK_AMBIGUOUS');
        $new=self::cronBlock($root,$php);
        if(str_contains($old,$begin)){
            $a=strpos($old,$begin);$b=strpos($old,$end,$a);
            if(($a>0&&$old[$a-1]!=="\n")||$b===false)throw new RuntimeException('SCHEDULER_BLOCK_AMBIGUOUS');
            $after=$b+strlen($end);if(isset($old[$after])&&$old[$after]==="\n")$after++;
            return substr($old,0,$a).$new.substr($old,$after);
        }
        return $old.($old!==''&&!str_ends_with($old,"\n")?"\n":'').$new;
    }
    private function store(string $name,array $value,int $owner,int $group): void
    {
        $path=$this->root.'/private/'.$name;
        if(!preg_match('/\A[a-z][a-z0-9.-]+\.json\z/D',$name)||is_link($path))throw new RuntimeException('PREPARATION_RECORD_UNSAFE');
        $tmp=$this->root.'/private/.prepare-'.bin2hex(random_bytes(16));$mask=umask(0077);$h=fopen($tmp,'xb');
        try {
            $bytes=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if(!$h||fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)||!fsync($h)||!chmod($tmp,0600)||!chown($tmp,$owner)||!chgrp($tmp,$group))throw new RuntimeException('PREPARATION_WRITE_FAILED');
            fclose($h);$h=null;if(!rename($tmp,$path))throw new RuntimeException('PREPARATION_WRITE_FAILED');
        }finally{if(is_resource($h))fclose($h);if(is_file($tmp))unlink($tmp);umask($mask);}
    }
    private function permissions(int $owner,int $web,int $group): void
    {
        $paths=[$this->root];
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST)as$file){if($file->isLink())throw new RuntimeException('PACKAGE_SYMLINK_REJECTED');$paths[]=$file->getPathname();}
        // Protect private first, before giving the web group access to the parent.
        if(!chmod($this->root.'/private',0700))throw new RuntimeException('PREPARATION_PERMISSION_FAILED');
        foreach($paths as$path){
            $private=str_starts_with($path.'/',$this->root.'/private/');$mode=is_dir($path)?($private?0700:0750):($private?0600:0640);
            if(!chmod($path,$mode)||!chown($path,$owner)||!chgrp($path,$group))throw new RuntimeException('PREPARATION_PERMISSION_FAILED');
        }
        foreach(['private/agent','storage','storage/license','storage/setup','storage/inbox','storage/logs','storage/cache','storage/sessions','public/uploads','public/assets/uploads']as$rel){
            $p=$this->root.'/'.$rel;if(!is_dir($p)&&!mkdir($p,0750,true))throw new RuntimeException('PREPARATION_PERMISSION_FAILED');
            $webWrite=in_array($rel,['storage/logs','storage/cache','storage/sessions','public/uploads','public/assets/uploads'],true);
            if(!chown($p,$webWrite?$web:$owner)||!chgrp($p,$group)||!chmod($p,$rel==='storage/inbox'?02770:($rel==='private/agent'?0700:0750)))throw new RuntimeException('PREPARATION_PERMISSION_FAILED');
        }
        clearstatcache();
    }
    public function run(): array
    {
        echo "Finance — persiapan server satu kali\nTidak mengubah website lain, PHP/MariaDB global, atau database.\n";
        PortableInstaller::requirements();$php=self::secureBinary(PHP_BINARY);$runuser=self::secureBinary('/usr/sbin/runuser');
        $required=['package.tar','release.json','release.sig.json','release-trust.json','permit.json','credentials.json'];
        foreach($required as$name)if(!is_file($this->root.'/private/delivery/'.$name))throw new RuntimeException('CONTROL_ZIP_INCOMPLETE');
        echo "Memeriksa paket dan izin pengiriman…\n";
        $evidence=PortablePackage::beforePreparation($this->root);
        if(!in_array($evidence['context']['distribution_profile_version'],[7,8,9,10,11],true))throw new RuntimeException('PREPARATION_PROFILE_V7_REQUIRED');
        $old=$this->readRecord();
        $webName=$this->options['web-user']??$old['web_user']??'';
        if($webName===''){
            $found=[];foreach(['www-data','www','apache','nginx']as$n)if(posix_getpwnam($n)!==false)$found[]=$n;
            echo 'Akun web yang ditemukan: '.($found?implode(', ',$found):'belum terdeteksi').". Pilih akun PHP website ini, bukan root.\n";
            $webName=self::ask('Nama akun PHP website',count($found)===1?$found[0]:'');
        }
        $web=posix_getpwnam(self::name($webName));if(!$web||$web['uid']===0)throw new RuntimeException('WEB_ACCOUNT_REQUIRED');
        $groupName=$this->options['web-group']??$old['web_group']??posix_getgrgid($web['gid'])['name'];
        $group=posix_getgrnam(self::name($groupName));if(!$group)throw new RuntimeException('WEB_ACCOUNT_REQUIRED');
        $installerName=$this->options['installer-user']??$old['installer_user']??('fin_'.substr(hash('sha256',$this->root),0,12));
        $installer=posix_getpwnam(self::name($installerName));$create=$installer===false;
        if($installer!==false && ($installer['uid']===0||$installer['uid']===$web['uid']||($installer['gid']!==$group['gid']&&!in_array($installerName,$group['members'],true))))throw new RuntimeException('INSTALLER_ACCOUNT_SEPARATION_REQUIRED');
        if($old!==null)foreach(['web_user'=>$webName,'web_group'=>$groupName,'installer_user'=>$installerName]as$k=>$v)if($old[$k]!==$v)throw new RuntimeException('PREPARATION_IDENTITY_CHANGE_REJECTED');
        $cron=self::secureBinary($this->options['cron-binary']??$old['cron_binary']??'/usr/bin/crontab');
        $spool=$this->options['cron-spool']??$old['cron_spool']??'';$prefix=[$cron];
        if($spool!=='') {
            // For an administrator-owned isolated scheduler. Never allow installer-owned ancestors of a root cron spool.
            if(basename($cron)!=='busybox'||realpath($spool)!==$spool||!is_dir($spool))throw new RuntimeException('ISOLATED_CRON_UNSAFE');
            for($p=$spool;;$p=dirname($p)){if(is_link($p)||fileowner($p)!==0||(fileperms($p)&0022)!==0)throw new RuntimeException('ISOLATED_CRON_UNSAFE');if($p===dirname($p))break;}
            $prefix=[$cron,'crontab','-c',$spool];
        }elseif(basename($cron)!=='crontab')throw new RuntimeException('SCHEDULER_UNAVAILABLE');
        $url=$this->options['url']??$old['url']??'';
        if($url==='')$url=self::ask('Alamat HTTPS aplikasi (boleh kosong; customer mengisi lewat UI)');
        if($url!==''){$parts=parse_url($url);if(!filter_var($url,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||($parts['path']??'/')!=='/')throw new RuntimeException('CUSTOMER_CONFIG_HTTPS_ROOT_URL_REQUIRED');$url=rtrim($url,'/').'/';}
        echo "\nRingkasan sebelum perubahan:\nFolder: {$this->root}\nWebsite: public/ (konfigurasi website tidak diubah)\nPHP CLI: $php\nAkun web: $webName / $groupName\nPendamping: $installerName".($create?' (akun sistem baru, tanpa login interaktif)':' (akun yang sudah ada)')."\nJadwal: tiga tugas khusus folder ini; jadwal lain dipertahankan.\nDatabase/aktivasi: TIDAK dijalankan oleh persiapan.\n";
        if($spool!=='')echo "Scheduler terisolasi: $spool (daemon harus sudah berjalan)\n";
        if(strtoupper(self::ask('Ketik SIAP untuk menyetujui target di atas'))!=='SIAP')throw new RuntimeException('PREPARATION_CANCELLED');
        if($create){
            $useradd=self::secureBinary('/usr/sbin/useradd');$r=self::command([$useradd,'--system','--no-create-home','--home-dir',$this->root.'/private','--gid',(string)$group['gid'],'--shell','/usr/sbin/nologin',$installerName]);
            if($r['code']!==0)throw new RuntimeException('SERVICE_ACCOUNT_CREATE_FAILED');$installer=posix_getpwnam($installerName);
        }
        if(!$installer)throw new RuntimeException('INSTALLER_ACCOUNT_REQUIRED');
        if(!is_file($this->root.'/private/delivery-state.json')&&$old===null)$this->permissions($installer['uid'],$web['uid'],$group['gid']);
        else {CustomerPlatform::path($this->root,$this->root);if(fileowner($this->root)!==$installer['uid'])throw new RuntimeException('PREPARATION_IDENTITY_CHANGE_REJECTED');}
        // Verification is repeated under the actual worker identity after permissions have been applied.
        $worker=[$runuser,'-u',$installerName,'-g',$groupName,'--',$php,$this->root.'/tools/install/portable/finance_setup.php'];
        $r=self::command(array_merge($worker,['prepare']));$v=json_decode($r['out']!==''?$r['out']:$r['err'],true);
        if($r['code']!==0)throw new RuntimeException(is_array($v)&&preg_match('/\A[A-Z_]+\z/D',$v['code']??'')?$v['code']:'COMPANION_PREPARATION_FAILED');
        $r=self::command([$runuser,'-u',$webName,'-g',$groupName,'--',$php,'-r','exit(is_readable($argv[1])?1:0);',$this->root.'/private/agent/agent.json']);
        if($r['code']!==0)throw new RuntimeException('WEB_CAN_READ_PRIVATE_KEY');
        $r=self::command([$runuser,'-u',$webName,'-g',$groupName,'--',$php,'-r',
            '$r=$argv[1];exit(is_readable($r."/storage/setup/browser.json")&&is_readable($r."/public/index.php")&&is_writable($r."/storage/inbox")&&!is_writable($r."/public/index.php")&&!is_writable($r."/storage/license")?0:1);',$this->root]);
        if($r['code']!==0)throw new RuntimeException('WEB_RUNTIME_PERMISSION_FAILED');
        $r=self::command(array_merge($prefix,['-u',$installerName,'-l']));
        if($r['code']!==0&&$r['code']!==1)throw new RuntimeException('SCHEDULER_READ_FAILED');
        if($r['code']===1 && !preg_match('/no crontab|can.t open.*No such file/i',$r['err']))throw new RuntimeException('SCHEDULER_READ_FAILED');
        $previous=$r['code']===0?$r['out']:'';$next=self::mergeCron($previous,$this->root,$php);
        $this->store('server-preparation.json',['contract'=>'FINANCE_SERVER_PREPARATION_V1','root'=>$this->root,'web_user'=>$webName,'web_group'=>$groupName,'installer_user'=>$installerName,'php'=>$php,'cron_binary'=>$cron,'cron_spool'=>$spool,'url'=>$url,'at'=>time()],$installer['uid'],$group['gid']);
        $changed=$previous!==$next;
        if($changed){
            $this->store('previous-cron-'.bin2hex(random_bytes(6)).'.json',['content'=>$previous,'at'=>time()],$installer['uid'],$group['gid']);
            $r=self::command(array_merge($prefix,['-u',$installerName,'-']),$next);if($r['code']!==0)throw new RuntimeException('SCHEDULER_WRITE_FAILED');
        }
        $r=self::command(array_merge($prefix,['-u',$installerName,'-l']));
        if($r['code']!==0||$r['out']!==$next)throw new RuntimeException('SCHEDULER_VERIFY_FAILED');
        // Wait for a REAL scheduler invocation; never call tick manually and pretend cron is healthy.
        $started=time();echo "Jadwal terpasang. Menunggu bukti layanan berjalan (maksimal 80 detik)…\n";
        $healthy=false;
        for($i=0;$i<80;$i++){
            clearstatcache();$healthy=true;
            foreach(['worker.json','license-sync.json','heartbeat.json']as$name){
                $path=$this->root.'/storage/setup/'.$name;
                $proof=is_file($path)?CustomerPlatform::document($this->root,$path):[];
                if(($proof['at']??0)<$started)$healthy=false;
            }
            if($healthy)break;
            sleep(1);
        }
        if(!$healthy)throw new RuntimeException('SCHEDULER_NOT_RUNNING');
        echo "\nPersiapan berhasil. Pendamping terjadwal benar-benar berjalan sebagai akun non-root.\nBuka ".($url!==''?$url.'setup':'alamat HTTPS aplikasi Anda di /setup')."\nIsi database dan admin melalui UI. Tidak perlu menjalankan check/prepare/run/sync atau menyalin cron lagi.\n";
        return ['status'=>'READY','scheduler_verified'=>true,'cron_changed'=>$changed,'database_touched'=>false];
    }
}
