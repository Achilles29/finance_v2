<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Normalizes adjustment input before a controller creates a material or
 * component adjustment. It keeps "quantity to add/subtract" and
 * "physical count" as two explicit modes instead of mixing their formulas.
 */
class InventoryAdjustmentIntent
{
    /**
     * @param float|null $authoritativeSystemQty Current balance read by the writer.
     *        When supplied, a stale browser snapshot is rejected instead of being
     *        used to calculate a physical-count adjustment.
     */
    public function resolve(array $payload, string $domain, ?float $authoritativeSystemQty = null): array
    {
        $domain = strtoupper(trim($domain));
        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain adjustment tidak valid.'];
        }

        $mode = strtoupper(trim((string)($payload['input_mode'] ?? '')));
        if (!in_array($mode, ['DELTA', 'PHYSICAL_COUNT'], true)) {
            $mode = array_key_exists('physical_qty_content', $payload) || array_key_exists('physical_qty', $payload)
                ? 'PHYSICAL_COUNT'
                : 'DELTA';
        }

        $hasClientSystemQty = array_key_exists('system_qty_content', $payload)
            || array_key_exists('system_qty', $payload);
        $clientSystemQty = (float)($payload['system_qty_content'] ?? $payload['system_qty'] ?? 0);
        $systemQty = $authoritativeSystemQty === null
            ? $clientSystemQty
            : round($authoritativeSystemQty, 4);
        $physicalQty = (float)($payload['physical_qty_content'] ?? $payload['physical_qty'] ?? 0);
        $deltaQty = (float)($payload['qty_delta_content'] ?? $payload['qty_delta'] ?? 0);

        if ($mode === 'PHYSICAL_COUNT' && $authoritativeSystemQty !== null && $hasClientSystemQty
            && abs($clientSystemQty - $systemQty) > 0.0001) {
            return [
                'ok' => false,
                'code' => 'STALE_SYSTEM_STOCK',
                'message' => 'Stok sistem sudah berubah sejak halaman dibuka. Muat ulang Daily Recon, periksa angka terbaru, lalu simpan kembali.',
                'client_system_qty' => round($clientSystemQty, 4),
                'current_system_qty' => round($systemQty, 4),
            ];
        }

        if ($mode === 'PHYSICAL_COUNT' && $physicalQty < -0.0001) {
            return ['ok' => false, 'message' => 'Stok fisik tidak boleh bernilai negatif.'];
        }
        if ($mode === 'PHYSICAL_COUNT') {
            $deltaQty = $physicalQty - $systemQty;
        }
        $deltaQty = round($deltaQty, 4);

        if (abs($deltaQty) < 0.0001) {
            return ['ok' => false, 'message' => 'Tidak ada selisih stok yang perlu disesuaikan.'];
        }

        return [
            'ok' => true,
            'domain' => $domain,
            'input_mode' => $mode,
            'system_qty' => round($systemQty, 4),
            'physical_qty' => round($physicalQty, 4),
            'delta_qty' => $deltaQty,
            'direction' => $deltaQty > 0 ? 'PLUS' : 'MINUS',
            'qty_abs' => round(abs($deltaQty), 4),
        ];
    }

    /**
     * Decides whether a stock addition may close an existing deficit.
     *
     * A normal adjustment plus may settle only as much as it actually adds.
     * A confirmed physical count is different: the counted final balance is
     * evidence that an earlier shortage is now covered, so it may settle up
     * to that final balance without consuming the newly recorded stock.
     */
    public function resolveDeficitSettlement(
        string $inputMode,
        bool $settleOpenDeficit,
        float $positiveQty,
        ?float $physicalQty = null
    ): array {
        $mode = strtoupper(trim($inputMode));
        $positiveQty = round(max(0, $positiveQty), 4);
        $physicalQty = $physicalQty === null ? null : round(max(0, $physicalQty), 4);

        if ($positiveQty <= 0.0001) {
            return [
                'should_settle' => false,
                'qty_available' => 0.0,
                'reason' => 'NO_POSITIVE_ADDITION',
            ];
        }

        if ($mode !== 'PHYSICAL_COUNT') {
            return [
                'should_settle' => true,
                'qty_available' => $positiveQty,
                'reason' => 'DELTA_PLUS',
            ];
        }

        if (!$settleOpenDeficit) {
            return [
                'should_settle' => false,
                'qty_available' => 0.0,
                'reason' => 'PHYSICAL_COUNT_NOT_CONFIRMED',
            ];
        }

        return [
            'should_settle' => true,
            'qty_available' => $physicalQty !== null && $physicalQty > 0.0001
                ? $physicalQty
                : $positiveQty,
            'reason' => 'PHYSICAL_COUNT_CONFIRMED',
        ];
    }
}
