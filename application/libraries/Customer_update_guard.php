<?php
declare(strict_types=1);

/** A request holds a shared lock until process shutdown. The updater acquires the exclusive lock. */
final class Customer_update_guard
{
    private static $handle=null;
    public static function enter(string $root): void
    {
        if(self::$handle!==null)return;
        $file=$root.'/storage/setup/application-update.lock';
        if(!is_file($file)||is_link($file))throw new RuntimeException('UPDATE_RUNTIME_PREPARATION_REQUIRED');
        $h=fopen($file,'rb');
        if(!$h)throw new RuntimeException('UPDATE_RUNTIME_PREPARATION_REQUIRED');
        if(!flock($h,LOCK_SH|LOCK_NB)){fclose($h);throw new RuntimeException('UPDATE_MAINTENANCE');}
        if(file_exists($root.'/storage/setup/update-maintenance.json')){flock($h,LOCK_UN);fclose($h);throw new RuntimeException('UPDATE_MAINTENANCE');}
        self::$handle=$h;
        register_shutdown_function(static function(){if(is_resource(self::$handle)){flock(self::$handle,LOCK_UN);fclose(self::$handle);}self::$handle=null;});
    }
}
