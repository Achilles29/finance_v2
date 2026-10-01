<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('component_adjustment_reason_options')) {
    function component_adjustment_reason_options(): array
    {
        return [
            'WASTE' => [
                'cancel_order'   => 'Cancel Order',
                'kitchen_error'  => 'Kitchen Error',
                'overproduction' => 'Overproduction',
                'spillage'       => 'Spillage / Tumpah',
                'expired_opened' => 'Expired Opened',
                'other'          => 'Other',
            ],
            'SPOILAGE' => [
                'expired'           => 'Expired',
                'temperature_abuse' => 'Temperature Abuse',
                'contamination'     => 'Contamination',
                'improper_storage'  => 'Improper Storage',
                'overstock'         => 'Overstock',
                'other'             => 'Other',
            ],
            'ADJUSTMENT_PLUS' => [
                'opening_correction' => 'Opening Correction',
                'stock_found'        => 'Stock Found',
                'manual_reclass'     => 'Manual Reclass',
                'other'              => 'Other',
            ],
            'ADJUSTMENT_MINUS' => [
                'counting_error'   => 'Counting Error',
                'system_mismatch'  => 'System Mismatch',
                'unrecorded_usage' => 'Unrecorded Usage',
                'process_loss'     => 'Process Loss',
                'theft_suspected'  => 'Theft Suspected',
                'other'            => 'Other',
            ],
        ];
    }
}

if (!function_exists('component_adjustment_reason_category_aliases')) {
    function component_adjustment_reason_category_aliases(): array
    {
        return [
            'SPOIL' => 'SPOILAGE',
            'PLUS' => 'ADJUSTMENT_PLUS',
            'MINUS' => 'ADJUSTMENT_MINUS',
        ];
    }
}

if (!function_exists('normalize_component_adjustment_reason_category')) {
    function normalize_component_adjustment_reason_category(string $category): string
    {
        $normalized = strtoupper(trim($category));
        $aliases = component_adjustment_reason_category_aliases();
        return $aliases[$normalized] ?? $normalized;
    }
}

if (!function_exists('component_adjustment_reason_codes')) {
    function component_adjustment_reason_codes(string $category): array
    {
        $category = normalize_component_adjustment_reason_category($category);
        $options = component_adjustment_reason_options();
        return array_keys($options[$category] ?? []);
    }
}

if (!function_exists('component_adjustment_reason_label')) {
    function component_adjustment_reason_label(string $category, ?string $reasonCode): string
    {
        $code = strtolower(trim((string)$reasonCode));
        if ($code === '') {
            return '-';
        }
        $category = normalize_component_adjustment_reason_category($category);
        $options = component_adjustment_reason_options();
        return (string)($options[$category][$code] ?? $code);
    }
}

if (!function_exists('normalize_component_adjustment_reason_code')) {
    function normalize_component_adjustment_reason_code(string $value, string $category): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $category = normalize_component_adjustment_reason_category($category);
        $allowed = component_adjustment_reason_codes($category);
        if ($allowed === []) {
            return null;
        }

        if ($category === 'ADJUSTMENT_MINUS' && $value === 'over_usage') {
            return 'unrecorded_usage';
        }

        return in_array($value, $allowed, true) ? $value : 'other';
    }
}
