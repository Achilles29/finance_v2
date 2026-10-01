<?php

/** Shared web/installer contract. No transaction data, chmod, chown, or deletion. */
class Upload_storage_policy
{
    public const DIRECTORIES = [
        'assets/uploads/business-profile-logo' => 'Logo usaha',
        'assets/uploads/pos-printer-logo' => 'Logo cetak POS',
        'uploads/product' => 'Foto produk',
        'uploads/assets/photos' => 'Foto aset',
        'uploads/assets/evidence' => 'Bukti aset',
        'uploads/coffee-labels' => 'Desain label kopi',
        'uploads/coffee-labels/logos' => 'Logo label kopi',
        'uploads/wa' => 'Lampiran WhatsApp',
    ];

    public static function safe_path(string $root, string $relative): string
    {
        $root = realpath($root);
        if ($root === false || !array_key_exists($relative, self::DIRECTORIES)) throw new RuntimeException('storage_target_invalid');
        $path = $root;
        foreach (explode('/', $relative) as $segment) {
            $path .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($path)) throw new RuntimeException('storage_symlink_rejected');
            if (file_exists($path) && !is_dir($path)) throw new RuntimeException('storage_path_not_directory');
        }
        return $path;
    }

    public static function inspect(string $root): array
    {
        $result = [];
        foreach (self::DIRECTORIES as $relative => $label) {
            try {
                $path = self::safe_path($root, $relative);
                clearstatcache(true, $path);
                $status = !is_dir($path) ? 'MISSING' : (is_writable($path) ? 'READY' : 'NOT_WRITABLE');
            } catch (RuntimeException $e) { $status = 'UNSAFE_PATH'; }
            $result[] = ['path' => $relative, 'label' => $label, 'status' => $status];
        }
        return $result;
    }

    public static function prepare(string $root): array
    {
        // Do not leave root-owned upload folders behind: run as the PHP-FPM account.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) throw new RuntimeException('run_as_php_fpm_user_not_root');
        foreach (array_keys(self::DIRECTORIES) as $relative) self::safe_path($root, $relative);
        foreach (array_keys(self::DIRECTORIES) as $relative) {
            $path = self::safe_path($root, $relative);
            if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) throw new RuntimeException('storage_create_failed:' . $relative);
        }
        return self::inspect($root);
    }
}
