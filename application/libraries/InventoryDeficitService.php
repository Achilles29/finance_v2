<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Records quantity consumed without an available FIFO lot.
 *
 * Deficits keep the operational stock at zero while retaining a visible,
 * auditable obligation that a later receipt or source-backed adjustment can
 * settle. A physical reconciliation aligns today's stock but does not guess
 * the historical source of the shortage.
 */
class InventoryDeficitService
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function isReady(): bool
    {
        return $this->ci->db->table_exists('inv_stock_deficit')
            && $this->ci->db->table_exists('inv_stock_deficit_settlement');
    }

    public function record(array $payload): array
    {
        if (!$this->isReady()) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Defisit belum dicatat karena migration inventory belum dijalankan.',
            ];
        }

        $data = $this->normalizePayload($payload);
        if (!($data['ok'] ?? false)) {
            return $data;
        }

        $remaining = round((float)$data['requested_qty'] - (float)$data['issued_qty'], 4);
        if ($remaining <= 0.0001) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Tidak ada defisit untuk dicatat.'];
        }

        $deficitKey = hash('sha256', implode('|', [
            $data['stock_domain'],
            $data['deficit_date'],
            $data['location_scope'],
            $data['division_id'] ?? 0,
            $data['destination_type'] ?? '',
            $data['item_id'] ?? 0,
            $data['material_id'] ?? 0,
            $data['component_id'] ?? 0,
            $data['content_uom_id'] ?? 0,
            $data['profile_key'] ?? '',
            $data['source_table'],
            $data['source_id'] ?? 0,
            $data['source_line_id'] ?? 0,
        ]));

        $existing = $this->ci->db
            ->from('inv_stock_deficit')
            ->where('deficit_key', $deficitKey)
            ->limit(1)
            ->get()
            ->row_array();
        if (!empty($existing)) {
            return [
                'ok' => true,
                'id' => (int)$existing['id'],
                'qty_remaining' => round((float)($existing['qty_remaining'] ?? 0), 4),
                'duplicate' => true,
            ];
        }

        $unitCost = round((float)$data['estimated_unit_cost'], 6);
        $this->ci->db->insert('inv_stock_deficit', [
            'deficit_key' => $deficitKey,
            'stock_domain' => $data['stock_domain'],
            'deficit_date' => $data['deficit_date'],
            'location_scope' => $data['location_scope'],
            'division_id' => $data['division_id'],
            'destination_type' => $data['destination_type'],
            'item_id' => $data['item_id'],
            'material_id' => $data['material_id'],
            'component_id' => $data['component_id'],
            'buy_uom_id' => $data['buy_uom_id'],
            'content_uom_id' => $data['content_uom_id'],
            'profile_key' => $data['profile_key'],
            'requested_qty' => $data['requested_qty'],
            'issued_qty' => $data['issued_qty'],
            'settled_qty' => 0,
            'reversed_qty' => 0,
            'qty_remaining' => $remaining,
            'estimated_unit_cost' => $unitCost,
            'estimated_total_value' => round($remaining * $unitCost, 2),
            'status' => 'OPEN',
            'source_module' => $data['source_module'],
            'source_table' => $data['source_table'],
            'source_id' => $data['source_id'],
            'source_line_id' => $data['source_line_id'],
            'notes' => $data['notes'],
            'created_by' => $data['created_by'],
        ]);

        $id = (int)$this->ci->db->insert_id();
        if ($id <= 0) {
            $error = $this->ci->db->error();
            return ['ok' => false, 'message' => 'Gagal mencatat defisit stok: ' . (string)($error['message'] ?? 'unknown error')];
        }

        return ['ok' => true, 'id' => $id, 'qty_remaining' => $remaining];
    }

    public function settle(array $payload): array
    {
        if (!$this->isReady()) {
            return ['ok' => true, 'settled_qty' => 0, 'remaining_qty' => round((float)($payload['qty_available'] ?? 0), 4), 'skipped' => true];
        }

        $data = $this->normalizePayload(array_merge($payload, [
            'requested_qty' => $payload['qty_available'] ?? 0,
            'issued_qty' => 0,
        ]));
        if (!($data['ok'] ?? false)) {
            return $data;
        }

        $available = round((float)$data['requested_qty'], 4);
        if ($available <= 0.0001) {
            return ['ok' => true, 'settled_qty' => 0, 'remaining_qty' => 0, 'settlements' => []];
        }

        $db = $this->ci->db;
        // Settlement touches both operational deficit data and (when ready)
        // the financial HPP correction. Keep the two writes atomic.
        $db->trans_begin();
        $query = $db->query(
            'SELECT *
             FROM inv_stock_deficit
             WHERE stock_domain = ?
               AND location_scope = ?
               AND division_id <=> ?
               AND destination_type <=> ?
               AND item_id <=> ?
               AND material_id <=> ?
               AND component_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id <=> ?
               AND profile_key <=> ?
               AND status = "OPEN"
               AND qty_remaining > 0.0001
             ORDER BY deficit_date ASC, id ASC
             FOR UPDATE',
            [
                $data['stock_domain'],
                $data['location_scope'],
                $data['division_id'],
                $data['destination_type'],
                $data['item_id'],
                $data['material_id'],
                $data['component_id'],
                $data['buy_uom_id'],
                $data['content_uom_id'],
                $data['profile_key'],
            ]
        );
        $rows = $query->result_array();
        $settled = 0.0;
        $settlements = [];
        $unitCost = round((float)$data['estimated_unit_cost'], 6);

        foreach ($rows as $row) {
            if ($available <= 0.0001) {
                break;
            }
            $before = round((float)($row['qty_remaining'] ?? 0), 4);
            $take = round(min($before, $available), 4);
            if ($take <= 0.0001) {
                continue;
            }
            $after = round($before - $take, 4);
            $status = $after <= 0.0001 ? 'SETTLED' : 'OPEN';

            $db->where('id', (int)$row['id'])->update('inv_stock_deficit', [
                'settled_qty' => round((float)($row['settled_qty'] ?? 0) + $take, 4),
                'qty_remaining' => max(0, $after),
                // Keep the stored value aligned with the remaining deficit.
                'estimated_total_value' => round(max(0, $after) * (float)($row['estimated_unit_cost'] ?? 0), 2),
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $db->insert('inv_stock_deficit_settlement', [
                'deficit_id' => (int)$row['id'],
                'settlement_date' => $data['deficit_date'],
                'qty_settled' => $take,
                'unit_cost' => $unitCost,
                'total_value' => round($take * $unitCost, 2),
                'source_module' => $data['source_module'],
                'source_table' => $data['source_table'],
                'source_id' => $data['source_id'],
                'source_line_id' => $data['source_line_id'],
                'notes' => $data['notes'],
                'created_by' => $data['created_by'],
            ]);
            $settlementId = (int)$db->insert_id();
            if ($settlementId <= 0) {
                $error = $db->error();
                $db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal mencatat penyelesaian defisit: ' . (string)($error['message'] ?? 'unknown error')];
            }

            $cogsAdjustment = null;
            if (file_exists(APPPATH . 'libraries/InventoryDeficitCogsService.php')) {
                $this->ci->load->library('InventoryDeficitCogsService');
                if ($this->ci->inventorydeficitcogsservice->isReady()) {
                    $cogsAdjustment = $this->ci->inventorydeficitcogsservice->recordSettlement($row, [
                        'id' => $settlementId,
                        'deficit_id' => (int)$row['id'],
                        'settlement_date' => $data['deficit_date'],
                        'qty_settled' => $take,
                        'unit_cost' => $unitCost,
                        'created_by' => $data['created_by'],
                    ]);
                    if (!($cogsAdjustment['ok'] ?? false)) {
                        $db->trans_rollback();
                        return $cogsAdjustment;
                    }
                }
            }
            $settled = round($settled + $take, 4);
            $available = round($available - $take, 4);
            $settlements[] = [
                'id' => $settlementId,
                'deficit_id' => (int)$row['id'],
                'qty_settled' => $take,
                'remaining_after' => max(0, $after),
                'cogs_adjustment' => $cogsAdjustment,
            ];
        }

        if (!$db->trans_status()) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => 'Penyelesaian defisit dibatalkan karena transaksi database gagal.'];
        }
        $db->trans_commit();

        return [
            'ok' => true,
            'settled_qty' => $settled,
            'remaining_qty' => max(0, $available),
            'settlements' => $settlements,
        ];
    }

    public function voidBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, ?int $actorUserId = null, string $note = ''): array
    {
        if (!$this->isReady() || $sourceTable === '' || $sourceId <= 0) {
            return ['ok' => true, 'voided_count' => 0, 'skipped' => !$this->isReady()];
        }

        $db = $this->ci->db;
        $db->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'OPEN');
        if ($sourceLineId !== null && $sourceLineId > 0) {
            $db->where('source_line_id', $sourceLineId);
        }
        $rows = $db->get('inv_stock_deficit')->result_array();
        $count = 0;
        foreach ($rows as $row) {
            $openQty = round(max(0, (float)($row['qty_remaining'] ?? 0)), 4);
            $db->where('id', (int)$row['id'])->update('inv_stock_deficit', [
                'status' => 'VOID',
                // A void removes the unfulfilled obligation, while keeping
                // the source record visible for audit.
                'reversed_qty' => round((float)($row['reversed_qty'] ?? 0) + $openQty, 4),
                'qty_remaining' => 0,
                'estimated_total_value' => 0,
                'voided_by' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                'voided_at' => date('Y-m-d H:i:s'),
                'notes' => substr(trim((string)($row['notes'] ?? '') . ' | VOID: ' . $note), 0, 255),
            ]);
            $count++;
        }
        return ['ok' => true, 'voided_count' => $count];
    }

    /**
     * Removes only the unissued quantity reversed by a POS void/refund.
     * A partial refund must not void the entire original deficit.
     */
    public function reverseBySourceQty(string $sourceTable, int $sourceId, ?int $sourceLineId, float $qty, ?int $actorUserId = null, string $note = '', array $document = []): array
    {
        if (!$this->isReady() || $sourceTable === '' || $sourceId <= 0 || $qty <= 0.0001) {
            return ['ok' => true, 'reversed_qty' => 0, 'remaining_reverse_qty' => max(0, $qty), 'skipped' => !$this->isReady()];
        }

        $db = $this->ci->db;
        $db->query('SELECT id FROM inv_stock_deficit WHERE source_table = ? AND source_id = ?' . ($sourceLineId !== null && $sourceLineId > 0 ? ' AND source_line_id = ?' : '') . ' AND status = "OPEN" FOR UPDATE', $sourceLineId !== null && $sourceLineId > 0 ? [$sourceTable, $sourceId, $sourceLineId] : [$sourceTable, $sourceId]);
        $db->where('source_table', $sourceTable)->where('source_id', $sourceId)->where('status', 'OPEN');
        if ($sourceLineId !== null && $sourceLineId > 0) {
            $db->where('source_line_id', $sourceLineId);
        }
        $rows = $db->order_by('id', 'ASC')->get('inv_stock_deficit')->result_array();
        $remaining = round($qty, 4);
        $reversed = 0.0;

        foreach ($rows as $row) {
            if ($remaining <= 0.0001) {
                break;
            }
            $openQty = round((float)($row['qty_remaining'] ?? 0), 4);
            $take = round(min($openQty, $remaining), 4);
            if ($take <= 0.0001) {
                continue;
            }
            $after = round($openQty - $take, 4);
            $isVoid = $after <= 0.0001;
            $db->where('id', (int)$row['id'])->update('inv_stock_deficit', [
                'reversed_qty' => round((float)($row['reversed_qty'] ?? 0) + $take, 4),
                'qty_remaining' => max(0, $after),
                'estimated_total_value' => round(max(0, $after) * (float)($row['estimated_unit_cost'] ?? 0), 2),
                'status' => $isVoid ? 'VOID' : 'OPEN',
                'voided_by' => $isVoid && $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                'voided_at' => $isVoid ? date('Y-m-d H:i:s') : null,
                'notes' => substr(trim((string)($row['notes'] ?? '') . ' | POS reverse: ' . $note), 0, 255),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $reversed = round($reversed + $take, 4);
            $remaining = round($remaining - $take, 4);
        }

        // A settled deficit did not create a lot issue at the original sale.
        // Its POS void/refund therefore reverses the HPP correction only; it
        // must not put fictional stock back into today's lot balance.
        $settledReversed = 0.0;
        if ($remaining > 0.0001 && file_exists(APPPATH . 'libraries/InventoryDeficitCogsService.php')) {
            $this->ci->load->library('InventoryDeficitCogsService');
            if ($this->ci->inventorydeficitcogsservice->isReady()) {
                $cogsReverse = $this->ci->inventorydeficitcogsservice->reverseSettledBySourceQty(
                    $sourceTable,
                    $sourceId,
                    $sourceLineId,
                    $remaining,
                    $actorUserId,
                    $note,
                    $document
                );
                if (!($cogsReverse['ok'] ?? false)) {
                    return $cogsReverse;
                }
                $settledReversed = round((float)($cogsReverse['reversed_qty'] ?? 0), 4);
                foreach ((array)($cogsReverse['deficit_reversals'] ?? []) as $deficitId => $settledQty) {
                    $settledQty = round((float)$settledQty, 4);
                    if ((int)$deficitId <= 0 || $settledQty <= 0.0001) {
                        continue;
                    }
                    $db->set('reversed_qty', 'reversed_qty + ' . $settledQty, false)
                        ->set('updated_at', date('Y-m-d H:i:s'))
                        ->where('id', (int)$deficitId)
                        ->update('inv_stock_deficit');
                }
                $reversed = round($reversed + $settledReversed, 4);
                $remaining = round(max(0, $remaining - $settledReversed), 4);
            }
        }

        return [
            'ok' => true,
            'reversed_qty' => $reversed,
            'settled_reversed_qty' => $settledReversed,
            'remaining_reverse_qty' => max(0, $remaining),
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $domain = strtoupper(trim((string)($payload['stock_domain'] ?? '')));
        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain defisit stok tidak valid.'];
        }

        $date = $this->normalizeDate((string)($payload['deficit_date'] ?? $payload['movement_date'] ?? date('Y-m-d')));
        $scope = strtoupper(trim((string)($payload['location_scope'] ?? $payload['location_type'] ?? '')));
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? $payload['uom_id'] ?? null);
        if ($date === null || $scope === '' || $contentUomId === null) {
            return ['ok' => false, 'message' => 'Defisit stok membutuhkan tanggal, lokasi, dan UOM isi.'];
        }

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        $componentId = $this->nullableInt($payload['component_id'] ?? null);
        if ($domain === 'MATERIAL' && $itemId === null && $materialId === null) {
            return ['ok' => false, 'message' => 'Defisit material membutuhkan item atau material.'];
        }
        if ($domain === 'COMPONENT' && $componentId === null) {
            return ['ok' => false, 'message' => 'Defisit component membutuhkan component.'];
        }

        $sourceTable = trim((string)($payload['source_table'] ?? ''));
        if ($sourceTable === '') {
            return ['ok' => false, 'message' => 'Defisit stok membutuhkan sumber dokumen.'];
        }

        return [
            'ok' => true,
            'stock_domain' => $domain,
            'deficit_date' => $date,
            'location_scope' => $scope,
            'division_id' => $this->nullableInt($payload['division_id'] ?? null),
            'destination_type' => $this->nullableString($payload['destination_type'] ?? null),
            'item_id' => $itemId,
            'material_id' => $materialId,
            'component_id' => $componentId,
            'buy_uom_id' => $this->nullableInt($payload['buy_uom_id'] ?? null),
            'content_uom_id' => $contentUomId,
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'requested_qty' => round(max(0, (float)($payload['requested_qty'] ?? 0)), 4),
            'issued_qty' => round(max(0, (float)($payload['issued_qty'] ?? 0)), 4),
            'estimated_unit_cost' => round(max(0, (float)($payload['estimated_unit_cost'] ?? $payload['unit_cost'] ?? 0)), 6),
            'source_module' => strtoupper(trim((string)($payload['source_module'] ?? 'INVENTORY'))),
            'source_table' => $sourceTable,
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
        ];
    }

    private function normalizeDate(string $value): ?string
    {
        $timestamp = strtotime($value);
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
