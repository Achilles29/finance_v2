<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Resolve once, before either FIFO or ledger can choose a different profile. */
class InventoryAdjustmentProfile
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function resolve(array $header, array $line): array
    {
        $db = $this->ci->db;
        $key = trim((string)($line['profile_key'] ?? ''));
        $scope = strtoupper((string)($header['stock_scope'] ?? ''));
        $table = $scope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
        $candidates = [];
        if ($db->table_exists($table)) {
            $db->from($table)->where('month_key', substr((string)$header['adjustment_date'], 0, 7) . '-01');
            if ($scope === 'DIVISION') {
                $db->where('division_id', (int)$header['division_id'])->where('destination_type', $header['destination_type']);
            }
            $this->filterIdentity($line, $key);
            $query = $db->get();
            if (!$query) { return ['ok' => false, 'message' => 'Gagal membaca profil saldo adjustment.']; }
            foreach ($query->result_array() as $row) {
                if ($this->matchesDescription($line, $row, $key !== '')) { $candidates[] = $row; }
            }
        }
        if ($db->table_exists('mst_purchase_catalog')) {
            $db->select('item_id, material_id, buy_uom_id, content_uom_id, profile_key, catalog_name AS profile_name, brand_name AS profile_brand, line_description AS profile_description, content_per_buy AS profile_content_per_buy', false)
                ->from('mst_purchase_catalog');
            $this->filterIdentity($line, $key);
            if ($key === '') { $db->where('is_active', 1); }
            $query = $db->get();
            if (!$query) { return ['ok' => false, 'message' => 'Gagal membaca profil katalog adjustment.']; }
            foreach ($query->result_array() as $row) {
                if ($this->matchesDescription($line, $row, $key !== '')) { $candidates[] = $row; }
            }
        }
        $profiles = [];
        foreach ($candidates as $row) {
            $candidateKey = trim((string)($row['profile_key'] ?? ''));
            if ($candidateKey === '') { continue; }
            if (isset($profiles[$candidateKey])) {
                foreach (['item_id', 'material_id', 'buy_uom_id', 'content_uom_id'] as $field) {
                    if ((int)($profiles[$candidateKey][$field] ?? 0) !== (int)($row[$field] ?? 0)) {
                        return ['ok' => false, 'message' => 'Identitas profil pada katalog dan saldo berbeda. Perbaiki profil sebelum posting adjustment.'];
                    }
                }
            }
            $profiles[$candidateKey] = $row;
        }
        if (count($profiles) !== 1) {
            return ['ok' => false, 'message' => count($profiles) > 1
                ? 'Profil adjustment ambigu. Pilih profil material yang tepat; posting dibatalkan agar FIFO dan ledger tidak berbeda.'
                : 'Profil adjustment tidak ditemukan pada saldo bulan dokumen atau katalog. Muat ulang dan pilih profil material.'];
        }
        $profile = reset($profiles);
        foreach (['item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'] as $field) {
            $line[$field] = $profile[$field] ?? null;
        }
        foreach (['profile_name', 'profile_brand', 'profile_description', 'profile_content_per_buy'] as $field) {
            if (!isset($line[$field]) || $line[$field] === '') { $line[$field] = $profile[$field] ?? null; }
        }
        if (empty($line['content_uom_id']) || (empty($line['item_id']) && empty($line['material_id']))) {
            return ['ok' => false, 'message' => 'Identitas profil adjustment tidak lengkap.'];
        }
        return ['ok' => true, 'line' => $line];
    }

    private function filterIdentity(array $line, string $key): void
    {
        foreach (['item_id', 'material_id', 'buy_uom_id', 'content_uom_id'] as $field) {
            if (!empty($line[$field])) { $this->ci->db->where($field, (int)$line[$field]); }
        }
        if ($key !== '') { $this->ci->db->where('profile_key', $key); }
    }

    private function matchesDescription(array $line, array $row, bool $explicit): bool
    {
        if ($explicit) { return true; }
        foreach (['profile_name', 'profile_brand', 'profile_description'] as $field) {
            $value = trim((string)($line[$field] ?? ''));
            if ($value !== '' && strcasecmp($value, trim((string)($row[$field] ?? ''))) !== 0) { return false; }
        }
        $factor = (float)($line['profile_content_per_buy'] ?? 0);
        return $factor <= 0 || abs($factor - (float)($row['profile_content_per_buy'] ?? 0)) < 0.000001;
    }
}
