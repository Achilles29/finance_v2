<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Writes the audit trail for a structural lot correction.
 *
 * A lot correction is deliberately separate from a physical stock movement.
 * The stock movement is recorded by the normal writer; this audit records why
 * the FIFO representation was aligned afterwards.
 */
class InventoryCutoffAudit
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function isReady(): bool
    {
        return $this->ci->db->table_exists('inv_stock_cutoff_event');
    }

    public function record(array $payload): array
    {
        if (!$this->isReady()) {
            return [
                'ok' => false,
                'message' => 'Tabel audit koreksi lot belum tersedia. Jalankan SQL inventory foundation terlebih dahulu.',
            ];
        }

        $domain = strtoupper(trim((string)($payload['stock_domain'] ?? '')));
        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain audit koreksi lot tidak valid.'];
        }

        $eventDate = $this->normalizeDate((string)($payload['event_date'] ?? $payload['movement_date'] ?? ''));
        $locationScope = strtoupper(trim((string)($payload['location_scope'] ?? '')));
        $direction = strtoupper(trim((string)($payload['direction'] ?? '')));
        $qty = round(abs((float)($payload['qty'] ?? 0)), 4);
        $sourceTable = trim((string)($payload['source_table'] ?? ''));

        if ($eventDate === null || $locationScope === '' || !in_array($direction, ['IN', 'OUT'], true)
            || $qty <= 0.0001 || $sourceTable === '') {
            return ['ok' => false, 'message' => 'Audit koreksi lot membutuhkan tanggal, lokasi, arah, qty, dan sumber yang valid.'];
        }

        $this->ci->db->insert('inv_stock_cutoff_event', [
            'stock_domain' => $domain,
            'event_date' => $eventDate,
            'period_month' => date('Y-m-01', strtotime($eventDate)),
            'location_scope' => $locationScope,
            'division_id' => $this->nullableInt($payload['division_id'] ?? null),
            'destination_type' => $this->nullableString($payload['destination_type'] ?? null),
            'item_id' => $this->nullableInt($payload['item_id'] ?? null),
            'material_id' => $this->nullableInt($payload['material_id'] ?? null),
            'component_id' => $this->nullableInt($payload['component_id'] ?? null),
            'content_uom_id' => $this->nullableInt($payload['content_uom_id'] ?? $payload['uom_id'] ?? null),
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'lot_id' => $this->nullableInt($payload['lot_id'] ?? null),
            'direction' => $direction,
            'qty' => $qty,
            'unit_cost' => round(max(0, (float)($payload['unit_cost'] ?? 0)), 6),
            'total_value' => round(max(0, (float)($payload['total_value'] ?? ($qty * (float)($payload['unit_cost'] ?? 0)))), 2),
            'source_table' => substr($sourceTable, 0, 80),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'movement_table' => $this->nullableString($payload['movement_table'] ?? null),
            'movement_id' => $this->nullableInt($payload['movement_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
        ]);

        $id = (int)$this->ci->db->insert_id();
        if ($id <= 0) {
            $error = $this->ci->db->error();
            return ['ok' => false, 'message' => 'Gagal mencatat audit koreksi lot: ' . (string)($error['message'] ?? 'unknown error')];
        }

        return ['ok' => true, 'id' => $id];
    }

    private function normalizeDate(string $value): ?string
    {
        $timestamp = strtotime(trim($value));
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function nullableInt($value): ?int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : substr($value, 0, 255);
    }
}
