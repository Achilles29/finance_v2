<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosBundlePricingService
{
    public function allocate(float $bundlePrice, array $lines): array
    {
        $bundlePrice = round($bundlePrice, 2);
        $normalized = [];
        $fixedTotal = 0.0;
        $adjustableIndexes = [];

        foreach ($lines as $index => $line) {
            $qty = round((float)($line['qty'] ?? 0), 4);
            $baseUnitPrice = round((float)($line['base_unit_price'] ?? 0), 2);
            $overrideUnitPrice = isset($line['override_unit_price']) && $line['override_unit_price'] !== '' && $line['override_unit_price'] !== null
                ? round((float)$line['override_unit_price'], 2)
                : null;
            $baseTotal = round($qty * $baseUnitPrice, 2);
            $overrideTotal = $overrideUnitPrice !== null ? round($qty * $overrideUnitPrice, 2) : null;

            $normalized[$index] = [
                'index' => $index,
                'product_id' => (int)($line['product_id'] ?? 0),
                'product_name' => (string)($line['product_name'] ?? ''),
                'qty' => $qty,
                'base_unit_price' => $baseUnitPrice,
                'base_total' => $baseTotal,
                'override_unit_price' => $overrideUnitPrice,
                'override_total' => $overrideTotal,
                'uom_code' => (string)($line['uom_code'] ?? ''),
                'division_name' => (string)($line['division_name'] ?? ''),
                'hpp_live_unit' => round((float)($line['hpp_live_unit'] ?? 0), 6),
                'hpp_standard_unit' => round((float)($line['hpp_standard_unit'] ?? 0), 6),
                'cost_source_label' => (string)($line['cost_source_label'] ?? ''),
                'allocation_mode' => $overrideUnitPrice !== null ? 'MANUAL_OVERRIDE' : 'AUTO_PROPORTIONAL',
                'allocated_total' => 0.0,
                'allocated_unit_price' => 0.0,
            ];

            if ($overrideUnitPrice !== null) {
                $fixedTotal += (float)$overrideTotal;
            } else {
                $adjustableIndexes[] = $index;
            }
        }

        $fixedTotal = round($fixedTotal, 2);
        $remaining = round($bundlePrice - $fixedTotal, 2);

        foreach ($normalized as $index => $line) {
            if ($line['override_total'] !== null) {
                $normalized[$index]['allocated_total'] = round((float)$line['override_total'], 2);
                $normalized[$index]['allocated_unit_price'] = $line['qty'] > 0
                    ? round($normalized[$index]['allocated_total'] / $line['qty'], 6)
                    : 0.0;
            }
        }

        if (!empty($adjustableIndexes)) {
            $weights = [];
            $weightTotal = 0.0;
            foreach ($adjustableIndexes as $index) {
                $line = $normalized[$index];
                $weight = (float)$line['base_total'];
                if ($weight <= 0) {
                    $weight = (float)$line['qty'];
                }
                if ($weight <= 0) {
                    $weight = 1.0;
                }
                $weights[$index] = $weight;
                $weightTotal += $weight;
            }

            $running = 0.0;
            $lastAdjustableIndex = end($adjustableIndexes);
            reset($adjustableIndexes);
            foreach ($adjustableIndexes as $index) {
                if ($weightTotal <= 0) {
                    $allocated = 0.0;
                } elseif ($index === $lastAdjustableIndex) {
                    $allocated = round($remaining - $running, 2);
                } else {
                    $allocated = round(($weights[$index] / $weightTotal) * $remaining, 2);
                    $running += $allocated;
                }

                $normalized[$index]['allocated_total'] = $allocated;
                $normalized[$index]['allocated_unit_price'] = $normalized[$index]['qty'] > 0
                    ? round($allocated / $normalized[$index]['qty'], 6)
                    : 0.0;
            }
        } elseif (!empty($normalized)) {
            $currentTotal = array_sum(array_column($normalized, 'allocated_total'));
            $diff = round($bundlePrice - $currentTotal, 2);
            if (abs($diff) >= 0.01) {
                $lastIndex = array_key_last($normalized);
                $normalized[$lastIndex]['allocated_total'] = round((float)$normalized[$lastIndex]['allocated_total'] + $diff, 2);
                $normalized[$lastIndex]['allocated_unit_price'] = $normalized[$lastIndex]['qty'] > 0
                    ? round($normalized[$lastIndex]['allocated_total'] / $normalized[$lastIndex]['qty'], 6)
                    : 0.0;
                $normalized[$lastIndex]['allocation_mode'] = $normalized[$lastIndex]['override_total'] !== null
                    ? 'MANUAL_OVERRIDE_ADJUSTED'
                    : 'AUTO_PROPORTIONAL_ADJUSTED';
            }
        }

        $summary = [
            'bundle_price' => $bundlePrice,
            'reference_total' => 0.0,
            'override_total' => $fixedTotal,
            'allocated_total' => 0.0,
            'hpp_live_total' => 0.0,
            'hpp_standard_total' => 0.0,
            'profit_live_total' => 0.0,
            'profit_standard_total' => 0.0,
            'allocation_gap' => 0.0,
            'line_count' => count($normalized),
        ];

        foreach ($normalized as $index => $line) {
            $liveTotal = round($line['qty'] * $line['hpp_live_unit'], 2);
            $standardTotal = round($line['qty'] * $line['hpp_standard_unit'], 2);
            $profitLive = round($line['allocated_total'] - $liveTotal, 2);
            $profitStandard = round($line['allocated_total'] - $standardTotal, 2);

            $normalized[$index]['hpp_live_total'] = $liveTotal;
            $normalized[$index]['hpp_standard_total'] = $standardTotal;
            $normalized[$index]['profit_live'] = $profitLive;
            $normalized[$index]['profit_standard'] = $profitStandard;

            $summary['reference_total'] += (float)$line['base_total'];
            $summary['allocated_total'] += (float)$line['allocated_total'];
            $summary['hpp_live_total'] += (float)$liveTotal;
            $summary['hpp_standard_total'] += (float)$standardTotal;
            $summary['profit_live_total'] += (float)$profitLive;
            $summary['profit_standard_total'] += (float)$profitStandard;
        }

        foreach ($summary as $key => $value) {
            if (is_float($value) || is_int($value)) {
                $summary[$key] = round((float)$value, 2);
            }
        }
        $summary['allocation_gap'] = round($summary['bundle_price'] - $summary['allocated_total'], 2);

        return [
            'summary' => $summary,
            'lines' => array_values($normalized),
        ];
    }
}
