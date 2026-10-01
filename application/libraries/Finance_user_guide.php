<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Curated documentation only. Never include a path supplied by a visitor. */
class Finance_user_guide
{
    public const REVIEWED = '2026-09-15';
    public const EDITION = '1';

    public function categories(bool $server): array
    {
        $categories = ['start'=>'Mulai di sini', 'setup'=>'Pengaturan UI',
            'operations'=>'Operasional', 'finance'=>'Keuangan', 'help'=>'Bantuan'];
        if ($server) $categories['server'] = 'Admin server';
        return $categories;
    }

    public function audiences(bool $server): array
    {
        $roles = ['owner'=>'Pemilik usaha', 'admin'=>'Admin aplikasi', 'cashier'=>'Kasir',
            'stock'=>'Gudang / produksi', 'hr'=>'SDM / pegawai', 'finance'=>'Keuangan'];
        if ($server) $roles['server'] = 'Admin server';
        return $roles;
    }

    public function articles(bool $server): array
    {
        $articles = require __DIR__.'/Finance_user_guide_catalog.php';
        if (defined('FINANCE_PUBLIC_ROOT')) {
            $articles = array_replace($articles, require __DIR__.'/Finance_customer_guide_catalog.php');
        }
        return array_filter($articles, static function (array $article) use ($server): bool {
            return $server || $article['category'] !== 'server';
        });
    }

    public function search(array $articles, string $category, string $audience, string $query): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_filter($articles, static function (array $article) use ($category, $audience, $words): bool {
            if ($category !== '' && $category !== $article['category']) return false;
            if ($audience !== '' && !in_array($audience, $article['audiences'], true)) return false;
            $text = mb_strtolower(implode(' ', [$article['title'], $article['summary'],
                implode(' ', $article['steps']), $article['check'], $article['warning']]), 'UTF-8');
            foreach ($words as $word) if (mb_strpos($text, $word, 0, 'UTF-8') === false) return false;
            return true;
        });
    }

    public function releaseLabel(string $manifestPath): string
    {
        // Only allowlisted version metadata, never echo the manifest or private config.
        if (!is_file($manifestPath) || filesize($manifestPath) > 131072) return 'Versi belum tersedia';
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        $version = is_array($manifest) ? ($manifest['version'] ?? null) : null;
        return is_string($version) && preg_match('/\A[0-9A-Za-z][0-9A-Za-z.+_-]{0,63}\z/D', $version)
            ? $version : 'Versi belum tersedia';
    }
}
