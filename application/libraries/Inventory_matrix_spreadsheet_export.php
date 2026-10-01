<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_matrix_spreadsheet_export
{
    private const FIXED_HEADERS = [
        'Lokasi', 'Domain', 'Jenis', 'Tujuan', 'Kategori', 'Kode', 'Nama Material / Component', 'Kode Material Terkait',
        'Nama Material Terkait', 'Profil', 'Merk', 'Deskripsi Profil', 'Kedaluwarsa',
        'Satuan Isi', 'Satuan Beli', 'Isi per Beli', 'Pemeriksaan Mutasi', 'Selisih Mutasi',
    ];

    public static function monthDates(string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) throw new RuntimeException('Bulan daily matrix tidak valid.');
        $start = new DateTimeImmutable($month . '-01');
        $dates = [];
        for ($day = 0; $day < (int)$start->format('t'); $day++) $dates[] = $start->modify('+' . $day . ' days')->format('Y-m-d');
        return $dates;
    }

    public static function sheet(string $month, array $rows): array
    {
        $dates = self::monthDates($month);
        $top = self::FIXED_HEADERS;
        $sub = array_fill(0, count(self::FIXED_HEADERS), '');
        foreach ($dates as $date) {
            $top[] = $date; $top[] = ''; $top[] = ''; $top[] = ''; $top[] = '';
            array_push($sub, 'Awal', 'In', 'Out', 'Adj', 'Akhir');
        }
        $output = [];
        foreach ($rows as $row) {
            $reconciliation = [];
            if (!empty($row['_log_gap'])) $reconciliation[] = 'GAP MUTASI';
            if (!empty($row['_mismatch'])) $reconciliation[] = 'MISMATCH';
            $values = [
                (string)($row['_location'] ?? ''), (string)($row['_domain'] ?? ''), (string)($row['_type'] ?? ''),
                (string)($row['_destination'] ?? ''), (string)($row['_category'] ?? ''), (string)($row['_code'] ?? ''),
                (string)($row['_name'] ?? ''),
                (string)($row['_material_code'] ?? ''), (string)($row['_material_name'] ?? ''),
                (string)($row['_profile'] ?? ''), (string)($row['_brand'] ?? ''), (string)($row['_description'] ?? ''),
                (string)($row['_expiry'] ?? ''), (string)($row['_uom'] ?? ''), (string)($row['_buy_uom'] ?? ''),
                self::number($row['_factor'] ?? 0), implode(', ', $reconciliation), self::number($row['_gap_qty'] ?? 0),
            ];
            foreach ($dates as $date) {
                $day = (array)($row['_days'][$date] ?? []);
                $values[] = self::number($day['opening'] ?? 0);
                $values[] = self::number($day['in'] ?? 0);
                $values[] = self::number($day['out'] ?? 0);
                $values[] = self::number($day['adjustment'] ?? $day['adj'] ?? 0);
                $values[] = self::number($day['closing'] ?? 0);
            }
            $output[] = $values;
        }
        usort($output, static function (array $a, array $b): int {
            $groupA = $a[1] === 'COMPONENT' ? 1 : 0;
            $groupB = $b[1] === 'COMPONENT' ? 1 : 0;
            if ($groupA !== $groupB) return $groupA <=> $groupB;
            if ($groupA === 1) {
                $typeA = strtoupper(trim((string)$a[2]));
                $typeB = strtoupper(trim((string)$b[2]));
                $rankA = $typeA === 'BASE' ? 0 : ($typeA === 'PREPARE' ? 1 : 2);
                $rankB = $typeB === 'BASE' ? 0 : ($typeB === 'PREPARE' ? 1 : 2);
                if ($rankA !== $rankB) return $rankA <=> $rankB;
                if ($rankA === 2) {
                    $typeCompare = strnatcasecmp($typeA, $typeB);
                    if ($typeCompare !== 0) return $typeCompare;
                }
            }
            foreach ([4, 6, 9, 3, 5] as $index) {
                $cmp = strnatcasecmp((string)$a[$index], (string)$b[$index]);
                if ($cmp !== 0) return $cmp;
            }
            return 0;
        });
        return ['headers' => [$top, $sub], 'rows' => $output, 'columns' => count($top),
            'dates' => $dates, 'row_count' => count($output)];
    }

    public static function materialRows(array $matrixRows, string $divisionName): array
    {
        $rows = [];
        foreach ($matrixRows as $row) {
            $rows[] = [
                '_location' => $divisionName, '_domain' => 'BAHAN BAKU', '_destination' => (string)($row['destination_group'] ?? ''),
                '_category' => trim((string)($row['category_name'] ?? '')) ?: '(Tanpa Kategori)',
                '_code' => (string)($row['material_code'] ?? $row['item_code'] ?? ''),
                '_name' => (string)($row['material_name'] ?? $row['item_name'] ?? ''),
                '_material_code' => (string)($row['material_code'] ?? ''), '_material_name' => (string)($row['material_name'] ?? ''),
                '_profile' => (string)($row['profile_name'] ?? ''), '_brand' => (string)($row['profile_brand'] ?? ''),
                '_description' => (string)($row['profile_description'] ?? ''), '_expiry' => (string)($row['profile_expired_date'] ?? ''),
                '_uom' => (string)($row['profile_content_uom_code'] ?? ''), '_buy_uom' => (string)($row['profile_buy_uom_code'] ?? ''),
                '_factor' => $row['profile_content_per_buy'] ?? 0, '_days' => (array)($row['daily'] ?? []),
                '_log_gap' => !empty($row['log_has_gap']), '_mismatch' => !empty($row['audit_has_mismatch']),
                '_gap_qty' => $row['log_gap_content'] ?? $row['audit_mismatch_qty_content'] ?? 0,
            ];
        }
        return $rows;
    }

    public static function warehouseRows(array $matrixRows): array
    {
        $rows = [];
        foreach ($matrixRows as $row) {
            $rows[] = [
                '_location' => 'STOREROOM', '_domain' => 'STOK GUDANG', '_destination' => 'GUDANG PUSAT',
                '_category' => trim((string)($row['category_name'] ?? '')) ?: '(Tanpa Kategori)',
                '_code' => (string)($row['item_code'] ?? ''), '_name' => (string)($row['item_name'] ?? ''),
                '_material_code' => (string)($row['material_code'] ?? ''), '_material_name' => (string)($row['material_name'] ?? ''),
                '_profile' => (string)($row['profile_name'] ?? ''), '_brand' => (string)($row['profile_brand'] ?? ''),
                '_description' => (string)($row['profile_description'] ?? ''), '_expiry' => (string)($row['profile_expired_date'] ?? ''),
                '_uom' => (string)($row['profile_content_uom_code'] ?? ''), '_buy_uom' => (string)($row['profile_buy_uom_code'] ?? ''),
                '_factor' => $row['profile_content_per_buy'] ?? 0, '_days' => (array)($row['daily'] ?? []),
                '_log_gap' => !empty($row['log_has_gap']), '_mismatch' => false, '_gap_qty' => $row['log_gap_content'] ?? 0,
            ];
        }
        return $rows;
    }

    public static function componentRows(array $matrixRows, string $divisionName): array
    {
        $rows = [];
        foreach ($matrixRows as $row) {
            $days = [];
            foreach ((array)($row['days'] ?? []) as $date => $values) $days[$date] = $values;
            $location = strtoupper((string)($row['location_type'] ?? ''));
            $rows[] = [
                '_location' => $divisionName, '_domain' => 'COMPONENT', '_type' => (string)($row['component_type'] ?? ''),
                '_category' => trim((string)($row['category_name'] ?? '')) ?: '(Tanpa Kategori)',
                '_destination' => substr($location, -6) === '_EVENT' ? 'EVENT' : 'REGULER',
                '_code' => (string)($row['component_code'] ?? ''), '_name' => (string)($row['component_name'] ?? ''),
                '_uom' => (string)($row['uom_code'] ?? ''), '_days' => $days,
            ];
        }
        return $rows;
    }

    private static function number($value): float
    {
        $number = is_numeric($value) ? (float)$value : 0.0;
        return is_finite($number) ? round($number, 4) : 0.0;
    }
}
