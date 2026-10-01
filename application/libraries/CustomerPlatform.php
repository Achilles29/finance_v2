<?php
declare(strict_types=1);

/** Profile v6 only. Does not change the root-owned security contract of older installs. */
final class CustomerPlatform
{
    public static function root(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if (basename($path) === 'public' && is_file(dirname($path).'/installer/layout.json')) return dirname($path);
        return $path;
    }

    public static function portable(string $root): bool
    {
        $root = self::root($root);
        return file_exists($root.'/installer/layout.json') || is_link($root.'/installer/layout.json');
    }

    /** Owner is the dedicated installer account, NOT the web account. No shared hosting fallback. */
    public static function path(string $root, string $path, bool $secret = false): void
    {
        $root = self::root($root);
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, "\0") || !str_starts_with($path.'/', $root.'/')
            || str_replace('\\', '/', (string)realpath($path)) !== $path || is_link($path)) {
            throw new RuntimeException('PORTABLE_PATH_UNSAFE');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            // Real ACL/reparse-point inspection, not chmod emulation. Fail closed if PowerShell unavailable.
            self::windowsCheck($root, $path, $secret);
            return;
        }
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) throw new RuntimeException('PORTABLE_PLATFORM_UNSUPPORTED');
        $owner = fileowner($root);
        if ($owner === false || $owner === 0) throw new RuntimeException('DEDICATED_INSTALLER_ACCOUNT_REQUIRED');
        if (PHP_SAPI !== 'cli' && (posix_geteuid() === 0 || posix_geteuid() === $owner)) throw new RuntimeException('WEB_INSTALLER_ACCOUNT_SEPARATION_REQUIRED');
        for ($part = $path; ; $part = dirname($part)) {
            $s = @lstat($part);
            if (!is_array($s) || ($s['mode'] & 0170000) === 0120000 || ($s['mode'] & 0022) !== 0
                || !in_array($s['uid'], [0, $owner], true)
                || ($part === $path && $secret && ($s['mode'] & 0027) !== 0)) throw new RuntimeException('PORTABLE_PERMISSION_UNSAFE');
            if ($part === dirname($part)) break;
        }
        if (is_file($path) && (stat($path)['nlink'] ?? 0) !== 1) throw new RuntimeException('PORTABLE_HARDLINK_UNSAFE');
    }

    private static function windowsCheck(string $root, string $path, bool $secret): void
    {
        $script = $root.'/tools/install/portable/windows-inspect.ps1';
        if (!is_file($script) || is_link($script)) throw new RuntimeException('WINDOWS_ACL_INSPECTOR_REQUIRED');
        $result = self::command(['powershell.exe','-NoProfile','-NonInteractive','-File',$script,
            '-Root',$root,'-Target',$path,'-Secret', $secret ? 'yes' : 'no', '-Web', PHP_SAPI === 'cli' ? 'no' : 'yes']);
        if (trim($result) !== 'OK') throw new RuntimeException('WINDOWS_ACL_UNSAFE');
    }

    public static function command(array $command): string
    {
        $p = proc_open($command, [0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($p)) throw new RuntimeException('PLATFORM_COMMAND_UNAVAILABLE');
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $out = ''; $start = microtime(true); $code = -1;
        try {
            do {
                $out .= stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
                $state = proc_get_status($p);
                if (!$state['running']) { $code = $state['exitcode']; break; }
                if (strlen($out)>32768 || microtime(true)-$start>15) throw new RuntimeException('PLATFORM_COMMAND_TIMEOUT');
                usleep(10000);
            } while (true);
            $out .= stream_get_contents($pipes[1]);
        } finally { fclose($pipes[1]); fclose($pipes[2]); if ($state['running']??false) proc_terminate($p); $closed=proc_close($p); }
        if (($code<0?$closed:$code)!==0) throw new RuntimeException('PLATFORM_CHECK_FAILED');
        return $out;
    }

    public static function installer(string $root): void
    {
        self::path($root,$root);
        if(PHP_SAPI!=='cli')throw new RuntimeException('COMPANION_CLI_REQUIRED');
        if(PHP_OS_FAMILY==='Linux' && posix_geteuid()!==fileowner($root))throw new RuntimeException('INSTALLER_ACCOUNT_REQUIRED');
        if(PHP_OS_FAMILY==='Windows') {
            $out=self::command(['powershell.exe','-NoProfile','-NonInteractive','-File',$root.'/tools/install/portable/windows-inspect.ps1',
                '-Root',$root,'-Target',$root,'-Secret','no','-Web','worker']);
            if(trim($out)!=='OK')throw new RuntimeException('INSTALLER_ACCOUNT_REQUIRED');
        }
    }

    public static function runtimeAcl(string $root,string $path): void
    {
        $out=self::command(['powershell.exe','-NoProfile','-NonInteractive','-File',$root.'/tools/install/portable/windows-inspect.ps1',
            '-Root',$root,'-Target',$path,'-Secret','yes','-Runtime','yes']);
        if(trim($out)!=='OK')throw new RuntimeException('WINDOWS_RUNTIME_ACL_UNSAFE');
    }

    public static function fingerprint(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $id=trim((string)@file_get_contents('/etc/machine-id'));
            if (!preg_match('/\A[a-f0-9]{32}\z/D',$id) || !in_array(strtolower(php_uname('m')),['x86_64','amd64'],true)) throw new RuntimeException('MACHINE_ID_UNAVAILABLE');
            return hash('sha256',"NAMUA_FINANCE\0".$id."\0linux-amd64");
        }
        if (PHP_OS_FAMILY === 'Windows' && PHP_INT_SIZE === 8) {
            $id=strtolower(trim(self::command(['powershell.exe','-NoProfile','-NonInteractive','-Command',
                '(Get-ItemProperty -LiteralPath HKLM:\\SOFTWARE\\Microsoft\\Cryptography -Name MachineGuid).MachineGuid'])));
            if (!preg_match('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/D',$id)) throw new RuntimeException('MACHINE_ID_UNAVAILABLE');
            return hash('sha256',"NAMUA_FINANCE\0".$id."\0windows-amd64");
        }
        throw new RuntimeException('PORTABLE_PLATFORM_UNSUPPORTED');
    }

    public static function document(string $root, string $path, int $limit=300000): array
    {
        self::path($root,$path,true);
        if (!is_file($path) || filesize($path)>$limit) throw new RuntimeException('PORTABLE_DOCUMENT_INVALID');
        $value=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('PORTABLE_DOCUMENT_INVALID');
        return $value;
    }
}
