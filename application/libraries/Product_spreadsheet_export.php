<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Spreadsheet projection of the same decorated rows used by Master Product. */
class Product_spreadsheet_export
{
    public static function columns(): array
    {
        return [
            ['product_division_id_label', 'Divisi Produk', 'text'],
            ['classification_id_label', 'Klasifikasi', 'text'],
            ['product_category_id_label', 'Kategori', 'text'],
            ['product_code', 'Kode Produk', 'text'],
            ['product_name', 'Nama Produk', 'text'],
            ['default_operational_division_id_label', 'Divisi Operasional', 'text'],
            ['uom_id_label', 'Satuan', 'text'],
            ['description', 'Deskripsi', 'text'],
            ['active_label', 'Status Produk', 'text'],
            ['stock_mode_label', 'Mode Stok', 'text'],
            ['selling_price', 'Harga Jual', 'money'],
            ['online_food_price', 'Harga Online Food', 'money'],
            ['hpp_standard', 'HPP Standar', 'money'],
            ['hpp_direct_live', 'HPP Dasar Bahan / Component', 'money'],
            ['variable_cost_mode', 'Mode Biaya Variabel', 'text'],
            ['variable_cost_effective_percent', 'Biaya Variabel Efektif (%)', 'percent'],
            ['variable_cost_live_amount', 'Nominal Biaya Variabel Produk', 'money'],
            ['hpp_live_total', 'Total HPP Live', 'money'],
            ['hpp_live_percent_total', 'HPP / Harga Jual (%)', 'percent'],
            ['estimated_profit', 'Estimasi Profit per Unit', 'money'],
            ['margin_percent', 'Margin / Harga Jual (%)', 'percent'],
            ['online_hpp_percent', 'HPP / Harga Online (%)', 'percent'],
            ['online_profit', 'Profit Online Sebelum Fee', 'money'],
            ['online_margin_percent', 'Margin Online Sebelum Fee (%)', 'percent'],
            ['hpp_basis_source', 'Basis Perhitungan HPP', 'text'],
            ['show_pos', 'Tampil POS', 'boolean'],
            ['show_member', 'Tampil Member', 'boolean'],
            ['show_online_food', 'Tampil Online Food', 'boolean'],
            ['show_landing', 'Tampil Landing', 'boolean'],
            ['photo_url', 'URL Foto', 'text'],
            ['snapshot_at', 'Snapshot Diambil (WIB)', 'text'],
            ['id', 'ID Produk', 'integer'],
            ['product_division_id', 'ID Divisi Produk', 'integer'],
            ['default_operational_division_id', 'ID Divisi Operasional', 'integer'],
            ['classification_id', 'ID Klasifikasi', 'integer'],
            ['product_category_id', 'ID Kategori', 'integer'],
            ['uom_id', 'ID Satuan', 'integer'],
            ['variable_cost_percent', 'Biaya Variabel Tersimpan (%)', 'percent'],
            ['hpp_live_cache', 'Cache HPP Master', 'money'],
            ['hpp_live_at', 'Waktu Cache HPP', 'text'],
            ['hpp_dirty', 'Cache HPP Perlu Dihitung Ulang', 'boolean'],
            ['photo_path', 'Path Foto', 'text'],
            ['photo_mime', 'Tipe File Foto', 'text'],
            ['created_at', 'Produk Dibuat', 'text'],
            ['updated_at', 'Produk Diubah', 'text'],
        ];
    }

    public static function snapshot(array $products, string $capturedAt, string $baseUrl): array
    {
        $columns = self::columns();
        $rows = [];
        $active = 0;
        foreach ($products as $product) {
            $price = (float)($product['selling_price'] ?? 0);
            $onlinePrice = (float)($product['online_food_price'] ?? 0);
            $hpp = (float)($product['hpp_live_total'] ?? 0);
            $product['active_label'] = !empty($product['is_active']) ? 'AKTIF' : 'NONAKTIF';
            $active += !empty($product['is_active']) ? 1 : 0;
            $product['margin_percent'] = $price > 0 ? round(($price - $hpp) / $price * 100, 4) : null;
            if ($price <= 0) $product['hpp_live_percent_total'] = null;
            $product['online_hpp_percent'] = $onlinePrice > 0 ? round($hpp / $onlinePrice * 100, 4) : null;
            $product['online_profit'] = $onlinePrice > 0 ? round($onlinePrice - $hpp, 2) : null;
            $product['online_margin_percent'] = $onlinePrice > 0 ? round(($onlinePrice - $hpp) / $onlinePrice * 100, 4) : null;
            $path = trim((string)($product['photo_path'] ?? ''));
            $product['photo_url'] = $path === '' ? '' : (preg_match('~^https?://~i', $path) ? $path : rtrim($baseUrl, '/') . '/' . ltrim($path, '/'));
            $product['snapshot_at'] = $capturedAt;
            $cells = [];
            foreach ($columns as [$key, $label, $type]) {
                $value = $product[$key] ?? null;
                if ($value === null || $value === '') $cells[] = '';
                elseif ($type === 'integer') $cells[] = (int)$value;
                elseif ($type === 'boolean') $cells[] = (bool)$value;
                elseif ($type === 'money' || $type === 'percent') {
                    if (!is_numeric($value) || !is_finite((float)$value)) throw new RuntimeException('Nilai numerik produk tidak valid: ' . (int)($product['id'] ?? 0));
                    $cells[] = (float)$value;
                } else $cells[] = (string)$value;
            }
            $rows[] = $cells;
        }
        return ['headers' => array_column($columns, 1), 'columns' => $columns, 'rows' => $rows,
            'captured_at' => $capturedAt, 'count' => count($rows), 'active_count' => $active, 'inactive_count' => count($rows) - $active];
    }
}
