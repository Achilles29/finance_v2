<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/application/libraries/DeploymentConfig.php';
require_once __DIR__.'/PrivateDeployment.php';

/** Ephemeral MySQL client files derived from the SAME customer configuration as CI. */
final class CustomerDatabase
{
    private static array $exclusiveOptions = [];

    /** Only files created in the currently active local-config scope may ignore global MySQL defaults. */
    public static function ownsOption(string $path): bool
    {
        return isset(self::$exclusiveOptions[$path]);
    }
    public static function withConnection(string $root, callable $action)
    {
        if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) throw new RuntimeException('ROOT_CLI_REQUIRED');
        $settings = DeploymentConfig::forRoot($root)->values();
        $runtime = CustomerLocalConfig::runtime($settings);
        $group = filegroup($root.'/'.CustomerLocalConfig::PATH);
        if (!is_dir($runtime)) {
            if (!mkdir($runtime,0750) || !chgrp($runtime,$group) || !chmod($runtime,0750)) {
                throw new RuntimeException('CUSTOMER_RUNTIME_CREATE_FAILED');
            }
        }
        // Re-read after provisioning and before using any derived path.
        $settings = DeploymentConfig::forRoot($root)->values();
        $private = $runtime.'/.installer';
        if (!file_exists($private) && !is_link($private)) {
            if (!mkdir($private,0700) || !chmod($private,0700)) throw new RuntimeException('CUSTOMER_INSTALLER_STORAGE_FAILED');
        }
        PrivateDeployment::directory($private);
        $tmp = $private.'/connection-'.bin2hex(random_bytes(12));
        if (!mkdir($tmp,0700) || !chmod($tmp,0700)) throw new RuntimeException('CUSTOMER_INSTALLER_STORAGE_FAILED');
        $option = $tmp.'/mysql.cnf'; $name = $tmp.'/database.name';
        $quote = static fn(string $v): string => '"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';
        $socket = $settings['FINANCE_DB_SOCKET'];
        $body = "[client]\nuser=".$quote($settings['FINANCE_DB_USER'])."\npassword=".$quote($settings['FINANCE_DB_PASSWORD'])."\n";
        $body .= $socket !== '' ? "protocol=socket\nsocket=".$quote($socket)."\n"
            : "protocol=tcp\nhost=".$quote($settings['FINANCE_DB_HOST'])."\nport=".$settings['FINANCE_DB_PORT']."\n";
        try {
            foreach ([$option=>$body,$name=>$settings['FINANCE_DB_NAME']."\n"] as $path=>$bytes) {
                $h=fopen($path,'xb');
                if (!$h) throw new RuntimeException('CUSTOMER_INSTALLER_STORAGE_FAILED');
                try {
                    if (!chmod($path,0600) || fwrite($h,$bytes)!==strlen($bytes) || !fflush($h)) {
                        throw new RuntimeException('CUSTOMER_INSTALLER_STORAGE_FAILED');
                    }
                } finally { fclose($h); }
            }
            self::$exclusiveOptions[$option]=true;
            return $action(['defaults-extra-file'=>$option,'database-name-file'=>$name],$settings,$private);
        } finally {
            unset(self::$exclusiveOptions[$option]);
            // Only the two newly created temporary files, never the attempt journal or customer data.
            foreach ([$option,$name] as $path) if (is_file($path) && !is_link($path)) unlink($path);
            rmdir($tmp);
        }
    }

    public static function assertBinding(array $settings, string $option, string $name): void
    {
        $login=parse_ini_file($option,true,INI_SCANNER_RAW)['client']??[];
        // Our writer quotes every string; RAW avoids PHP ${ENV} interpolation.
        // Decode only the MySQL quoting we produced, never parse as executable PHP/INI expressions.
        foreach (['user','password','host','socket'] as $field) {
            if (isset($login[$field])) $login[$field]=stripcslashes($login[$field]);
        }
        $host=($login['protocol']??'')==='socket'?'localhost':($login['host']??'localhost');
        if (($settings['FINANCE_DB_NAME']??'')!==$name || ($settings['FINANCE_DB_USER']??'')!==($login['user']??null)
            || !hash_equals((string)($login['password']??''),(string)($settings['FINANCE_DB_PASSWORD']??''))
            || ($settings['FINANCE_DB_HOST']??'')!==$host
            || (string)($settings['FINANCE_DB_PORT']??3306)!==(string)($login['port']??3306)
            || (string)($settings['FINANCE_DB_SOCKET']??'')!==(string)($login['socket']??'')) {
            throw new RuntimeException('WEB_DATABASE_BINDING_MISMATCH');
        }
    }
}
