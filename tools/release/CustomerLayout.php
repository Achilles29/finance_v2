<?php
declare(strict_types=1);

/** Deterministic build-only mapping. Never moves or edits the running source tree. */
final class CustomerLayout
{
    public const MAP = [
        'tools/install/portable/public.php'=>'public/index.php',
        'tools/install/portable/public.htaccess'=>'public/.htaccess',
        'tools/install/portable/web.config'=>'public/web.config',
        'tools/install/portable/layout.json'=>'installer/layout.json',
    ];
    public static function target(string $source): string
    {
        return self::MAP[$source] ?? (str_starts_with($source,'assets/') ? 'public/'.$source : $source);
    }
    public static function source(string $target): string
    {
        $key=array_search($target,self::MAP,true);
        return $key!==false?$key:(str_starts_with($target,'public/assets/')?substr($target,7):$target);
    }
    public static function entries(array $source): array
    {
        $out=[];
        foreach ($source as $entry) {
            $entry['path']=self::target($entry['path']);
            if (isset($out[$entry['path']])) throw new RuntimeException('CUSTOMER_LAYOUT_COLLISION');
            $out[$entry['path']]=$entry;
        }
        ksort($out);return $out;
    }
}
