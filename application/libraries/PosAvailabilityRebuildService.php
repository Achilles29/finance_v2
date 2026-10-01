<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosAvailabilityRebuildService
{
    /** @var CI_Controller */
    protected $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function rebuild_product(int $outletId, int $productId, array $context = []): array
    {
        if ($outletId <= 0 || $productId <= 0) {
            return ['ok' => false, 'message' => 'Outlet dan produk wajib valid untuk rebuild availability POS.'];
        }
        if (!$this->ci->db->table_exists('pos_product_availability_cache')) {
            return ['ok' => false, 'message' => 'Tabel pos_product_availability_cache belum tersedia.'];
        }

        $before = $this->load_cache_row($outletId, $productId);
        $live = $this->resolve_live_availability($outletId, $productId, $context);
        if (!($live['ok'] ?? false)) {
            $this->write_rebuild_log($outletId, $productId, $before, null, null, $context, (string)($live['message'] ?? 'Live calculation gagal.'));
            return $live;
        }
        $product = $this->load_product_row($productId);
        $override = $this->load_override_row($outletId, $productId);
        $cacheLive = $this->apply_cache_control_rules($live, $product ?: [], $override);

        $payload = [
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'availability_status' => (string)($cacheLive['availability_status'] ?? 'OUT'),
            'source_mode' => (string)($cacheLive['source_mode'] ?? 'AUTO'),
            'estimated_available_qty' => round((float)($cacheLive['estimated_available_qty'] ?? 0), 4),
            'uom_id' => !empty($cacheLive['uom_id']) ? (int)$cacheLive['uom_id'] : null,
            'bottleneck_kind' => (string)($cacheLive['bottleneck_kind'] ?? 'NONE'),
            'bottleneck_material_id' => !empty($cacheLive['bottleneck_material_id']) ? (int)$cacheLive['bottleneck_material_id'] : null,
            'bottleneck_component_id' => !empty($cacheLive['bottleneck_component_id']) ? (int)$cacheLive['bottleneck_component_id'] : null,
            'bottleneck_name_snapshot' => $this->nullable_text($cacheLive['bottleneck_name_snapshot'] ?? ''),
            'main_missing_count' => (int)($cacheLive['main_missing_count'] ?? 0),
            'optional_missing_count' => (int)($cacheLive['optional_missing_count'] ?? 0),
            'override_allowed' => !empty($cacheLive['override_allowed']) ? 1 : 0,
            'hpp_live_snapshot' => round((float)($cacheLive['hpp_live_snapshot'] ?? 0), 6),
            'stock_reference_at' => date('Y-m-d H:i:s'),
            'last_commit_event' => strtoupper(trim((string)($context['event_source'] ?? ($context['trigger_context'] ?? 'MANUAL')))),
            'computed_at' => date('Y-m-d H:i:s'),
            'is_dirty' => 0,
        ];

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            if ($before) {
                $db->where('id', (int)$before['id'])->update('pos_product_availability_cache', $payload);
            } else {
                $db->insert('pos_product_availability_cache', $payload);
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan cache availability POS.');
            }
            $db->trans_commit();
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $after = $this->load_cache_row($outletId, $productId);
        $comparison = $this->compare_cache_and_live($after ?: [], $live);
        $logId = $this->write_rebuild_log($outletId, $productId, $before, $after, $live, $context, $comparison['note']);

        return [
            'ok' => true,
            'cache' => $after,
            'live' => $live,
            'comparison' => $comparison,
            'log_id' => $logId,
        ];
    }

    public function rebuild_products(int $outletId, array $productIds, array $context = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($outletId <= 0 || empty($productIds)) {
            return ['ok' => false, 'message' => 'Outlet dan daftar produk terdampak wajib valid.'];
        }

        $results = [];
        $success = 0;
        $failed = 0;
        foreach ($productIds as $productId) {
            $result = $this->rebuild_product($outletId, $productId, $context);
            $results[] = ['product_id' => $productId] + $result;
            if ($result['ok'] ?? false) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'ok' => $success > 0 && $failed === 0,
            'results' => $results,
            'success_count' => $success,
            'failed_count' => $failed,
        ];
    }

    public function rebuild_all_products(int $outletId, array $filters = [], array $context = []): array
    {
        if ($outletId <= 0) {
            return ['ok' => false, 'message' => 'Outlet wajib dipilih untuk rebuild total stock live.'];
        }
        if (!$this->ci->db->table_exists('mst_product')) {
            return ['ok' => false, 'message' => 'Tabel produk belum tersedia.'];
        }

        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $db = $this->ci->db->select('p.id')
            ->from('mst_product p')
            ->where('p.is_active', 1);
        if ($this->ci->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->ci->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        if ($divisionId > 0) {
            $db->where('p.product_division_id', $divisionId);
        }

        $productIds = array_values(array_filter(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $db->order_by('p.id', 'ASC')->get()->result_array())));

        if (empty($productIds)) {
            return ['ok' => true, 'results' => [], 'success_count' => 0, 'failed_count' => 0];
        }

        return $this->rebuild_products($outletId, $productIds, $context + [
            'event_source' => (string)($context['event_source'] ?? 'MANUAL_REBUILD_ALL'),
        ]);
    }

    public function mark_dirty(int $outletId, array $productIds, array $context = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($outletId <= 0 || empty($productIds) || !$this->ci->db->table_exists('pos_product_availability_cache')) {
            return ['ok' => false, 'message' => 'Data mark dirty availability belum valid.'];
        }

        $payload = ['is_dirty' => 1];
        if ($this->ci->db->field_exists('last_commit_event', 'pos_product_availability_cache')) {
            $payload['last_commit_event'] = strtoupper(trim((string)($context['event_source'] ?? 'MARK_DIRTY')));
        }
        $this->ci->db->where('outlet_id', $outletId)
            ->where_in('product_id', $productIds)
            ->update('pos_product_availability_cache', $payload);

        return ['ok' => true, 'affected_count' => $this->ci->db->affected_rows()];
    }

    public function probe_compare(int $outletId, int $productId, array $context = []): array
    {
        if ($outletId <= 0 || $productId <= 0) {
            return ['ok' => false, 'message' => 'Outlet dan produk wajib valid untuk probe stock live.'];
        }

        $cache = $this->load_cache_row($outletId, $productId);
        $live = $this->resolve_live_availability($outletId, $productId, $context);
        if (!($live['ok'] ?? false)) {
            return $live;
        }

        $comparison = $this->compare_cache_and_live($cache ?: [], $live);
        $probeId = $this->write_probe($outletId, $productId, $cache, $live, $comparison, $context);

        return [
            'ok' => true,
            'cache' => $cache,
            'live' => $live,
            'comparison' => $comparison,
            'probe_id' => $probeId,
        ];
    }

    public function handle_material_change(int $materialId, array $context = []): array
    {
        $productIds = $this->resolve_affected_products_from_material($materialId);
        if (empty($productIds)) {
            return ['ok' => true, 'product_ids' => [], 'outlet_count' => 0, 'success_count' => 0, 'failed_count' => 0];
        }

        return $this->queue_or_rebuild_products_for_all_outlets($productIds, $context + [
            'event_source' => (string)($context['event_source'] ?? 'MATERIAL_CHANGE'),
        ]);
    }

    /**
     * Item identity is used by newer material stock rows that do not have a
     * legacy material_id. Resolve every dependency first so one inventory
     * event produces only one queue/rebuild operation.
     */
    public function handle_item_change(int $itemId, array $context = []): array
    {
        $productIds = $this->resolve_affected_products_from_item($itemId);
        if (empty($productIds)) {
            return ['ok' => true, 'product_ids' => [], 'outlet_count' => 0, 'success_count' => 0, 'failed_count' => 0];
        }

        return $this->queue_or_rebuild_products_for_all_outlets($productIds, $context + [
            'event_source' => (string)($context['event_source'] ?? 'ITEM_CHANGE'),
        ]);
    }

    public function handle_component_change(int $componentId, array $context = []): array
    {
        $productIds = $this->resolve_affected_products_from_component($componentId);
        if (empty($productIds)) {
            return ['ok' => true, 'product_ids' => [], 'outlet_count' => 0, 'success_count' => 0, 'failed_count' => 0];
        }

        return $this->queue_or_rebuild_products_for_all_outlets($productIds, $context + [
            'event_source' => (string)($context['event_source'] ?? 'COMPONENT_CHANGE'),
        ]);
    }

    /**
     * Rebuild affected POS products once for a compound inventory event.
     * A production batch can touch several materials/components; rebuilding
     * every dependency separately repeats the same product/outlet work.
     */
    public function handle_inventory_changes(array $materialIds, array $componentIds, array $context = []): array
    {
        $productIds = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $materialIds)))) as $materialId) {
            foreach ($this->resolve_affected_products_from_material($materialId) as $productId) {
                $productIds[(int)$productId] = (int)$productId;
            }
        }
        foreach (array_values(array_unique(array_filter(array_map('intval', $componentIds)))) as $componentId) {
            foreach ($this->resolve_affected_products_from_component($componentId) as $productId) {
                $productIds[(int)$productId] = (int)$productId;
            }
        }

        return $this->queue_or_rebuild_products_for_all_outlets(array_values($productIds), $context + [
            'event_source' => (string)($context['event_source'] ?? 'INVENTORY_BATCH_CHANGE'),
        ]);
    }

    public function compare_cache_snapshot(array $cache, array $live): array
    {
        return $this->compare_cache_and_live($cache, $live);
    }

    public function resolve_live_availability(int $outletId, int $productId, array $context = []): array
    {
        $product = $this->load_product_row($productId);
        if (!$product) {
            return ['ok' => false, 'message' => 'Produk POS tidak ditemukan untuk kalkulasi live.'];
        }
        $recipeRows = $this->load_product_recipe_rows($productId);

        if (empty($recipeRows)) {
            return [
                'ok' => true,
                'product_id' => $productId,
                'product_name' => (string)($product['product_name'] ?? ''),
                'uom_id' => !empty($product['uom_id']) ? (int)$product['uom_id'] : null,
                'availability_status' => 'OUT',
                'source_mode' => 'LIVE',
                'estimated_available_qty' => 0.0,
                'estimated_available_qty_main' => 0.0,
                'estimated_available_qty_all' => 0.0,
                'bottleneck_kind' => 'NONE',
                'bottleneck_material_id' => null,
                'bottleneck_component_id' => null,
                'bottleneck_name_snapshot' => 'Recipe belum tersedia',
                'main_missing_count' => 1,
                'optional_missing_count' => 0,
                'override_allowed' => 0,
                'hpp_live_snapshot' => 0.0,
                'lines' => [],
            ];
        }

        $blockingRoles = ['MAIN'];
        $softRoles = ['SUPPORT', 'COMPLEMENT', 'OPTIONAL'];
        $lines = [];
        $blockingMin = null;
        $overallMin = null;
        $blockingMissing = 0;
        $softMissing = 0;
        $hppLive = 0.0;
        $unpricedCostCount = 0;
        $primaryBottleneck = null;

        foreach ($recipeRows as $recipeRow) {
            $line = $this->resolve_recipe_live_line($recipeRow, $product);
            $lines[] = $line;
            $hppLive += (float)($line['total_cost_live_per_unit'] ?? 0);
            if (empty($line['cost_is_resolved'])) {
                $unpricedCostCount++;
            }

            $required = max(0.0001, (float)($line['required_qty_per_unit'] ?? 0));
            $available = (float)($line['available_qty_live'] ?? 0);
            $units = $available / $required;
            if ($overallMin === null || $units < $overallMin) {
                $overallMin = $units;
            }

            $role = strtoupper((string)($line['source_role'] ?? 'MAIN'));
            if (in_array($role, $blockingRoles, true)) {
                if ($blockingMin === null || $units < $blockingMin) {
                    $blockingMin = $units;
                }
                if ($available + 0.0001 < $required) {
                    $blockingMissing++;
                    if ($primaryBottleneck === null) {
                        $primaryBottleneck = $line;
                    }
                }
            } elseif (in_array($role, $softRoles, true) && $available + 0.0001 < $required) {
                $softMissing++;
                if ($primaryBottleneck === null) {
                    $primaryBottleneck = $line;
                }
            }
        }

        $availabilityStatus = 'AVAILABLE';
        $overrideAllowed = 0;
        if ($blockingMissing > 0) {
            $availabilityStatus = 'OUT';
        } elseif ($softMissing > 0) {
            $availabilityStatus = 'LIMITED';
            $overrideAllowed = 1;
        }

        $estimatedMainQty = $blockingMin !== null ? max(0, floor($blockingMin * 10000) / 10000) : max(0, floor(($overallMin ?? 0) * 10000) / 10000);
        $estimatedAllQty = max(0, floor(($overallMin ?? 0) * 10000) / 10000);
        // Availability and cost are intentionally separate. A temporary stock
        // deficit must not make the product HPP disappear from the POS cache.
        $headlineHppLive = max(0, $hppLive);
        $hppFallbackUsed = false;
        if ($unpricedCostCount > 0) {
            $productFallback = round((float)($product['hpp_standard'] ?? 0), 6);
            if ($productFallback <= 0) {
                $productFallback = round((float)($product['hpp_live_cache'] ?? 0), 6);
            }
            if ($productFallback > 0) {
                $headlineHppLive = $productFallback;
                $hppFallbackUsed = true;
            }
        }

        $payload = [
            'ok' => true,
            'product_id' => $productId,
            'product_name' => (string)($product['product_name'] ?? ''),
            'uom_id' => !empty($product['uom_id']) ? (int)$product['uom_id'] : null,
            'availability_status' => $availabilityStatus,
            'source_mode' => 'LIVE',
            'estimated_available_qty' => round($estimatedMainQty, 4),
            'estimated_available_qty_main' => round($estimatedMainQty, 4),
            'estimated_available_qty_all' => round($estimatedAllQty, 4),
            'bottleneck_kind' => (string)($primaryBottleneck['bottleneck_kind'] ?? 'NONE'),
            'bottleneck_material_id' => !empty($primaryBottleneck['material_id']) ? (int)$primaryBottleneck['material_id'] : null,
            'bottleneck_component_id' => !empty($primaryBottleneck['component_id']) ? (int)$primaryBottleneck['component_id'] : null,
            'bottleneck_name_snapshot' => !empty($primaryBottleneck['source_name_snapshot']) ? (string)$primaryBottleneck['source_name_snapshot'] : null,
            'main_missing_count' => $blockingMissing,
            'optional_missing_count' => $softMissing,
            'override_allowed' => $overrideAllowed,
            'hpp_live_snapshot' => round($headlineHppLive, 6),
            'hpp_cost_complete' => $unpricedCostCount === 0 ? 1 : 0,
            'hpp_fallback_used' => $hppFallbackUsed ? 1 : 0,
            'lines' => $lines,
        ];

        return $payload;
    }

    public function resolve_affected_products_from_material(int $materialId): array
    {
        $materialId = (int)$materialId;
        if ($materialId <= 0) {
            return [];
        }

        $productIds = [];
        if ($this->ci->db->table_exists('mst_product_recipe')) {
            $rows = $this->ci->db->select('DISTINCT r.product_id', false)
                ->from('mst_product_recipe r')
                ->join('mst_item i', 'i.id = r.material_item_id', 'inner')
                ->where('i.material_id', $materialId)
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $productIds[(int)$row['product_id']] = (int)$row['product_id'];
            }
        }

        $componentIds = [];
        if ($this->ci->db->table_exists('mst_component_formula')) {
            $db = $this->ci->db->select('DISTINCT f.component_id', false)
                ->from('mst_component_formula f');
            if ($this->ci->db->field_exists('material_id', 'mst_component_formula')) {
                $db->group_start()
                    ->where('f.material_id', $materialId)
                    ->or_group_start()
                        ->join('mst_item i2', 'i2.id = f.material_item_id', 'left')
                        ->where('i2.material_id', $materialId)
                    ->group_end()
                    ->group_end();
            } else {
                $db->join('mst_item i2', 'i2.id = f.material_item_id', 'inner')
                    ->where('i2.material_id', $materialId);
            }
            foreach ($db->get()->result_array() as $row) {
                $componentIds[(int)$row['component_id']] = (int)$row['component_id'];
            }
        }

        return $this->resolve_affected_products_from_component_ids(array_values($componentIds), array_values($productIds));
    }

    public function resolve_affected_products_from_item(int $itemId): array
    {
        $itemId = (int)$itemId;
        if ($itemId <= 0) {
            return [];
        }

        $productIds = [];
        if ($this->ci->db->table_exists('mst_product_recipe')) {
            $rows = $this->ci->db->select('DISTINCT r.product_id', false)
                ->from('mst_product_recipe r')
                ->where('r.material_item_id', $itemId)
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $productId = (int)($row['product_id'] ?? 0);
                if ($productId > 0) {
                    $productIds[$productId] = $productId;
                }
            }
        }

        $componentIds = [];
        if ($this->ci->db->table_exists('mst_component_formula')) {
            $rows = $this->ci->db->select('DISTINCT f.component_id', false)
                ->from('mst_component_formula f')
                ->where('f.material_item_id', $itemId)
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $componentId = (int)($row['component_id'] ?? 0);
                if ($componentId > 0) {
                    $componentIds[$componentId] = $componentId;
                }
            }
        }

        if ($this->ci->db->table_exists('mst_item')
            && $this->ci->db->field_exists('material_id', 'mst_item')) {
            $item = $this->ci->db->select('material_id')
                ->from('mst_item')
                ->where('id', $itemId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            $legacyMaterialId = (int)($item['material_id'] ?? 0);
            if ($legacyMaterialId > 0) {
                foreach ($this->resolve_affected_products_from_material($legacyMaterialId) as $productId) {
                    $productIds[(int)$productId] = (int)$productId;
                }
            }
        }

        return $this->resolve_affected_products_from_component_ids(array_values($componentIds), array_values($productIds));
    }

    public function resolve_affected_products_from_component(int $componentId): array
    {
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            return [];
        }
        return $this->resolve_affected_products_from_component_ids([$componentId], []);
    }

    public function active_outlet_ids(): array
    {
        if (!$this->ci->db->table_exists('pos_outlet')) {
            return [];
        }

        $rows = $this->ci->db->select('id')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        return array_values(array_filter(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $rows)));
    }

    private function resolve_affected_products_from_component_ids(array $componentIds, array $seedProductIds = []): array
    {
        $componentIds = array_values(array_unique(array_filter(array_map('intval', $componentIds))));
        $productIds = [];
        foreach ($seedProductIds as $productId) {
            $productIds[(int)$productId] = (int)$productId;
        }
        if (empty($componentIds)) {
            return array_values($productIds);
        }

        $queue = $componentIds;
        $visited = [];
        while (!empty($queue)) {
            $current = (int)array_shift($queue);
            if ($current <= 0 || isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            if ($this->ci->db->table_exists('mst_product_recipe')) {
                $rows = $this->ci->db->select('DISTINCT product_id', false)
                    ->from('mst_product_recipe')
                    ->where('component_id', $current)
                    ->get()
                    ->result_array();
                foreach ($rows as $row) {
                    $productIds[(int)$row['product_id']] = (int)$row['product_id'];
                }
            }

            if ($this->ci->db->table_exists('mst_component_formula')) {
                $parentRows = $this->ci->db->select('DISTINCT component_id', false)
                    ->from('mst_component_formula')
                    ->where('sub_component_id', $current)
                    ->get()
                    ->result_array();
                foreach ($parentRows as $row) {
                    $parentId = (int)($row['component_id'] ?? 0);
                    if ($parentId > 0 && !isset($visited[$parentId])) {
                        $queue[] = $parentId;
                    }
                }
            }
        }

        return array_values($productIds);
    }

    private function rebuild_products_for_all_outlets(array $productIds, array $context = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $outletIds = $this->active_outlet_ids();
        if (empty($productIds) || empty($outletIds)) {
            return ['ok' => true, 'product_ids' => $productIds, 'outlet_count' => count($outletIds), 'success_count' => 0, 'failed_count' => 0];
        }

        $success = 0;
        $failed = 0;
        $results = [];
        foreach ($outletIds as $outletId) {
            $result = $this->rebuild_products((int)$outletId, $productIds, $context + ['outlet_id' => (int)$outletId]);
            $results[] = [
                'outlet_id' => (int)$outletId,
                'success_count' => (int)($result['success_count'] ?? 0),
                'failed_count' => (int)($result['failed_count'] ?? 0),
            ];
            $success += (int)($result['success_count'] ?? 0);
            $failed += (int)($result['failed_count'] ?? 0);
        }

        return [
            'ok' => $failed === 0,
            'mode' => 'SYNC_FALLBACK',
            'product_ids' => $productIds,
            'outlet_count' => count($outletIds),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Inventory writers use a queue when its migration is available. The
     * synchronous path is deliberately kept as a compatibility fallback for
     * an application deployment that arrives before its SQL migration.
     */
    private function queue_or_rebuild_products_for_all_outlets(array $productIds, array $context = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $outletIds = $this->active_outlet_ids();
        if (empty($productIds) || empty($outletIds)) {
            return [
                'ok' => true,
                'mode' => 'QUEUE',
                'product_ids' => $productIds,
                'outlet_count' => count($outletIds),
                'success_count' => 0,
                'failed_count' => 0,
                'queued_count' => 0,
            ];
        }

        $this->ci->load->library('PosAvailabilityQueueService');
        if ($this->ci->posavailabilityqueueservice->isReady()) {
            $queued = $this->ci->posavailabilityqueueservice->enqueueProducts($outletIds, $productIds, $context);
            if (!($queued['ok'] ?? false)) {
                // A queue outage must not leave the POS menu permanently
                // stale. Fall back to the prior synchronous behavior only
                // for the affected event, while retaining the queue error for
                // diagnostics in the caller/logs.
                $fallback = $this->rebuild_products_for_all_outlets($productIds, $context);
                $fallback['mode'] = 'SYNC_QUEUE_ERROR_FALLBACK';
                $fallback['queue_error'] = (string)($queued['message'] ?? 'Gagal mengantre rebuild availability POS.');
                $fallback['queue_result'] = $queued;
                return $fallback;
            }
            return $queued + [
                'product_ids' => $productIds,
                'outlet_count' => count($outletIds),
                // Keep the previous response shape stable for callers that
                // only display an informational rebuild counter.
                'success_count' => 0,
                'failed_count' => (int)($queued['failed_count'] ?? 0),
            ];
        }

        return $this->rebuild_products_for_all_outlets($productIds, $context);
    }

    private function resolve_recipe_live_line(array $recipeRow, array $product): array
    {
        $lineType = strtoupper(trim((string)($recipeRow['line_type'] ?? 'MATERIAL')));
        $requiredQty = round((float)($recipeRow['qty'] ?? 0), 4);
        $requiredUomId = (int)($recipeRow['recipe_uom_id'] ?? 0);
        $sourceRole = $this->normalize_source_role((string)($recipeRow['ingredient_role'] ?? 'MAIN'));
        $divisionId = (int)($recipeRow['source_division_id'] ?? 0);
        if ($divisionId <= 0) {
            $divisionId = (int)($product['default_operational_division_id'] ?? 0);
        }

        $line = [
            'line_type' => $lineType,
            'source_role' => $sourceRole,
            'required_qty_per_unit' => $requiredQty,
            'required_uom_id' => $requiredUomId > 0 ? $requiredUomId : null,
            'material_id' => null,
            'component_id' => null,
            'source_name_snapshot' => '',
            'available_qty_live' => 0.0,
            'unit_cost_live' => 0.0,
            'total_cost_live_per_unit' => 0.0,
            'cost_is_resolved' => false,
            'bottleneck_kind' => 'NONE',
            'division_id' => $divisionId > 0 ? $divisionId : null,
        ];

        if ($lineType === 'COMPONENT') {
            $componentId = (int)($recipeRow['component_id'] ?? 0);
            $available = $this->load_component_live_snapshot($componentId, $divisionId);
            $fifoCost = $this->load_component_fifo_unit_cost($componentId, $divisionId);
            $line['component_id'] = $componentId > 0 ? $componentId : null;
            $line['source_name_snapshot'] = trim((string)($recipeRow['component_name'] ?? 'Component #' . $componentId));
            $line['available_qty_live'] = round((float)($available['qty_on_hand'] ?? 0), 4);
            $liveCost = round((float)($available['avg_cost'] ?? 0), 6);
            $standardCost = round((float)($recipeRow['component_hpp_standard'] ?? 0), 6);
            if (($fifoCost['unit_cost'] ?? 0) > 0) {
                $line['unit_cost_live'] = round((float)$fifoCost['unit_cost'], 6);
                $line['cost_source'] = 'FIFO_ACTIVE_LOT';
                $line['cost_is_resolved'] = true;
            } elseif ($liveCost > 0) {
                $line['unit_cost_live'] = $liveCost;
                $line['cost_source'] = $line['available_qty_live'] > 0.0001
                    ? 'LIVE_COMPONENT'
                    : 'LAST_LIVE_COMPONENT';
                $line['cost_is_resolved'] = true;
            } elseif ($standardCost > 0) {
                $line['unit_cost_live'] = $standardCost;
                $line['cost_source'] = 'STANDARD_COMPONENT';
                $line['cost_is_resolved'] = true;
            } else {
                $line['cost_source'] = 'UNPRICED_COMPONENT';
            }
            $line['total_cost_live_per_unit'] = round($requiredQty * (float)$line['unit_cost_live'], 6);
            $line['bottleneck_kind'] = 'COMPONENT';
            return $line;
        }

        $materialId = (int)($recipeRow['material_id'] ?? 0);
        $available = $this->load_material_live_snapshot($materialId, $divisionId, $requiredUomId);
        $fifoCost = $this->load_material_fifo_unit_cost($materialId, $divisionId, $requiredUomId);
        $line['material_id'] = $materialId > 0 ? $materialId : null;
        $line['source_name_snapshot'] = trim((string)($recipeRow['material_name'] ?? ($recipeRow['item_name'] ?? 'Material #' . $materialId)));
        $line['available_qty_live'] = round((float)($available['qty_content_balance'] ?? 0), 4);
        $liveCost = round((float)($available['avg_cost_per_content'] ?? 0), 6);
        $catalogCost = $this->load_material_catalog_unit_cost($materialId, $requiredUomId);
        $standardCost = round((float)($recipeRow['material_hpp_standard'] ?? 0), 6);
        if (($fifoCost['unit_cost'] ?? 0) > 0) {
            $line['unit_cost_live'] = round((float)$fifoCost['unit_cost'], 6);
            $line['cost_source'] = 'FIFO_ACTIVE_LOT';
            $line['cost_is_resolved'] = true;
        } elseif ($liveCost > 0) {
            $line['unit_cost_live'] = $liveCost;
            $line['cost_source'] = $line['available_qty_live'] > 0.0001
                ? 'LIVE_MATERIAL'
                : 'LAST_LIVE_MATERIAL';
            $line['cost_is_resolved'] = true;
        } elseif (($catalogCost['unit_cost'] ?? 0) > 0) {
            $line['unit_cost_live'] = round((float)$catalogCost['unit_cost'], 6);
            $line['cost_source'] = 'CATALOG_FALLBACK';
            $line['cost_is_resolved'] = true;
        } elseif ($standardCost > 0) {
            $line['unit_cost_live'] = $standardCost;
            $line['cost_source'] = 'STANDARD_MATERIAL';
            $line['cost_is_resolved'] = true;
        } else {
            $line['cost_source'] = 'UNPRICED_MATERIAL';
        }
        $line['total_cost_live_per_unit'] = round($requiredQty * (float)$line['unit_cost_live'], 6);
        $line['bottleneck_kind'] = 'MATERIAL';
        return $line;
    }

    /**
     * Fallback for an item with no active balance. The catalog price is a
     * cost per buy UOM, so it is converted to the content UOM used by recipe.
     */
    private function load_material_catalog_unit_cost(int $materialId, int $contentUomId = 0): array
    {
        if ($materialId <= 0 || !$this->ci->db->table_exists('mst_purchase_catalog')) {
            return ['unit_cost' => 0.0];
        }

        $select = "
            COALESCE(NULLIF(c.last_unit_price, 0), NULLIF(c.standard_price, 0), 0) AS buy_price,
            CASE
                WHEN COALESCE(c.conversion_factor_to_content, 0) > 0 THEN c.conversion_factor_to_content
                WHEN COALESCE(c.content_per_buy, 0) > 0 THEN c.content_per_buy
                ELSE 1
            END AS content_per_buy
        ";
        $db = $this->ci->db->select($select, false)
            ->from('mst_purchase_catalog c')
            ->join('mst_item i', 'i.id = c.item_id', 'left')
            ->where('COALESCE(c.is_active, 1) = 1', null, false)
            ->group_start()
                ->where('c.material_id', $materialId)
                ->or_where('i.material_id', $materialId)
            ->group_end()
            ->where('COALESCE(NULLIF(c.last_unit_price, 0), NULLIF(c.standard_price, 0), 0) > 0', null, false);
        if ($contentUomId > 0) {
            $db->where('c.content_uom_id', $contentUomId);
        }

        $row = $db
            ->order_by('c.last_purchase_date IS NULL', 'ASC', false)
            ->order_by('c.last_purchase_date', 'DESC')
            ->order_by('c.updated_at', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: [];
        $buyPrice = round((float)($row['buy_price'] ?? 0), 6);
        $contentPerBuy = round((float)($row['content_per_buy'] ?? 0), 6);
        if ($buyPrice <= 0 || $contentPerBuy <= 0) {
            return ['unit_cost' => 0.0];
        }

        return ['unit_cost' => round($buyPrice / $contentPerBuy, 6)];
    }

    /**
     * Uses the oldest available lot cost when one exists. This keeps the
     * lightweight stock-live HPP aligned with the FIFO cost used by POS stock
     * commit, including when only part of the required recipe stock remains.
     */
    private function load_material_fifo_unit_cost(int $materialId, int $divisionId = 0, int $contentUomId = 0): array
    {
        if ($materialId <= 0 || $divisionId <= 0 || !$this->ci->db->table_exists('inv_material_fifo_lot')) {
            return ['unit_cost' => 0.0];
        }

        $destinationType = '';
        if ($this->ci->db->table_exists('mst_operational_division')) {
            $division = $this->ci->db->select('code')
                ->from('mst_operational_division')
                ->where('id', $divisionId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            $candidate = strtoupper(trim((string)($division['code'] ?? '')));
            if (in_array($candidate, ['BAR', 'KITCHEN', 'ROASTERY'], true)) {
                $destinationType = $candidate;
            }
        }

        $scopes = $destinationType !== '' ? [$destinationType, ''] : [''];
        foreach ($scopes as $scope) {
            $sql = "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                    FROM inv_material_fifo_lot
                    WHERE location_scope = 'DIVISION'
                      AND division_id = ?
                      AND COALESCE(material_id, 0) = ?
                      AND qty_balance > 0";
            $params = [$divisionId, $materialId];
            if ($scope !== '') {
                $sql .= ' AND destination_type = ?';
                $params[] = $scope;
            }
            if ($contentUomId > 0) {
                $sql .= ' AND content_uom_id = ?';
                $params[] = $contentUomId;
            }
            $sql .= ' ORDER BY receipt_date ASC, id ASC LIMIT 1';
            $row = $this->ci->db->query($sql, $params)->row_array() ?: [];
            $unitCost = round((float)($row['unit_cost'] ?? 0), 6);
            if ($unitCost > 0) {
                return ['unit_cost' => $unitCost];
            }
        }

        return ['unit_cost' => 0.0];
    }

    /**
     * Component consumption uses its front lot too, so stock-live HPP follows
     * the same cost source without adding work to the cashier request.
     */
    private function load_component_fifo_unit_cost(int $componentId, int $divisionId = 0): array
    {
        if ($componentId <= 0 || !$this->ci->db->table_exists('inv_component_lot')) {
            return ['unit_cost' => 0.0];
        }

        $locationType = '';
        if ($divisionId > 0 && $this->ci->db->table_exists('mst_operational_division')) {
            $division = $this->ci->db->select('code')
                ->from('mst_operational_division')
                ->where('id', $divisionId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            $candidate = strtoupper(trim((string)($division['code'] ?? '')));
            if (in_array($candidate, ['BAR', 'KITCHEN', 'ROASTERY'], true)) {
                $locationType = $candidate;
            }
        }

        $scopes = $locationType !== '' ? [$locationType, ''] : [''];
        foreach ($scopes as $scope) {
            $sql = "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                    FROM inv_component_lot
                    WHERE component_id = ?
                      AND qty_balance > 0";
            $params = [$componentId];
            if ($divisionId > 0) {
                $sql .= ' AND division_id = ?';
                $params[] = $divisionId;
            }
            if ($scope !== '') {
                $sql .= ' AND location_type = ?';
                $params[] = $scope;
            }
            $sql .= ' ORDER BY receipt_date ASC, id ASC LIMIT 1';
            $row = $this->ci->db->query($sql, $params)->row_array() ?: [];
            $unitCost = round((float)($row['unit_cost'] ?? 0), 6);
            if ($unitCost > 0) {
                return ['unit_cost' => $unitCost];
            }
        }

        return ['unit_cost' => 0.0];
    }

    private function load_product_row(int $productId): ?array
    {
        if ($productId <= 0 || !$this->ci->db->table_exists('mst_product')) {
            return null;
        }

        return $this->ci->db->select('
                p.id,
                p.product_code,
                p.product_name,
                p.product_division_id,
                p.default_operational_division_id,
                p.uom_id,
                p.selling_price,
                p.hpp_standard,
                p.hpp_live_cache,
                p.stock_mode,
                pd.code AS product_division_code,
                pd.name AS product_division_name
            ')
            ->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->where('p.id', $productId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function load_product_recipe_rows(int $productId): array
    {
        if ($productId <= 0 || !$this->ci->db->table_exists('mst_product_recipe')) {
            return [];
        }

        $select = [
            'r.id',
            'r.product_id',
            'r.line_type',
            'r.qty',
            'r.source_division_id',
            'r.material_item_id',
            'r.component_id',
            'i.item_name',
            'i.material_id',
            'm.material_name',
            'm.hpp_standard AS material_hpp_standard',
            'c.component_name',
            'c.hpp_standard AS component_hpp_standard',
        ];
        if ($this->ci->db->field_exists('ingredient_role', 'mst_product_recipe')) {
            $select[] = 'r.ingredient_role';
        }
        if ($this->ci->db->field_exists('uom_id', 'mst_product_recipe')) {
            $select[] = 'r.uom_id AS recipe_uom_id';
        }

        return $this->ci->db->select(implode(",\n", $select))
            ->from('mst_product_recipe r')
            ->join('mst_item i', 'i.id = r.material_item_id', 'left')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->join('mst_component c', 'c.id = r.component_id', 'left')
            ->where('r.product_id', $productId)
            ->order_by('r.sort_order', 'ASC')
            ->order_by('r.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function load_override_row(int $outletId, int $productId): ?array
    {
        if ($outletId <= 0 || $productId <= 0 || !$this->ci->db->table_exists('pos_product_availability_override')) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $db = $this->ci->db->from('pos_product_availability_override')
            ->where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->group_start()
                ->where('start_at IS NULL', null, false)
                ->or_where('start_at <=', $now)
            ->group_end()
            ->group_start()
                ->where('end_at IS NULL', null, false)
                ->or_where('end_at >=', $now)
            ->group_end()
            ->limit(1);

        return $db->get()->row_array() ?: null;
    }

    private function load_material_live_snapshot(int $materialId, int $divisionId = 0, int $uomId = 0): array
    {
        if ($materialId <= 0) {
            return ['qty_content_balance' => 0.0, 'avg_cost_per_content' => 0.0];
        }

        if ($this->ci->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->ci->db
                ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_division_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['division_id', 'destination_type', 'identity_key'])
                ->get_compiled_select();

            $select = "
                COALESCE(SUM(s.closing_qty_content), 0) AS qty_content_balance,
                COALESCE(
                    CASE WHEN ABS(SUM(s.closing_qty_content)) > 0.000001
                        THEN SUM(s.closing_qty_content * s.avg_cost_per_content) / SUM(s.closing_qty_content)
                        ELSE MAX(s.avg_cost_per_content)
                    END,
                    0
                ) AS avg_cost_per_content
            ";
            $db = $this->ci->db->select($select, false)
                ->from('inv_division_monthly_stock s')
                ->join('mst_item mi', 'mi.id = s.item_id', 'left')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->group_start()
                    ->where('s.material_id', $materialId)
                    ->or_group_start()
                        ->where('s.item_id IS NOT NULL', null, false)
                        ->where('mi.material_id', $materialId)
                    ->group_end()
                ->group_end();
            if ($divisionId > 0) {
                $db->where('s.division_id', $divisionId);
            }
            if ($uomId > 0) {
                $db->where('s.content_uom_id', $uomId);
            }

            return $db->get()->row_array() ?: ['qty_content_balance' => 0.0, 'avg_cost_per_content' => 0.0];
        }

        return ['qty_content_balance' => 0.0, 'avg_cost_per_content' => 0.0];
    }

    private function load_component_live_snapshot(int $componentId, int $divisionId = 0): array
    {
        if ($componentId <= 0) {
            return ['qty_on_hand' => 0.0, 'avg_cost' => 0.0];
        }

        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->ci->db
                ->select('location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key', false)
                ->from('inv_component_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['location_type', 'division_id', 'component_id', 'uom_id'])
                ->get_compiled_select();

            $select = "
                COALESCE(SUM(s.closing_qty), 0) AS qty_on_hand,
                COALESCE(
                    CASE WHEN ABS(SUM(s.closing_qty)) > 0.000001
                        THEN SUM(s.closing_qty * s.avg_cost) / SUM(s.closing_qty)
                        ELSE MAX(s.avg_cost)
                    END,
                    0
                ) AS avg_cost
            ";
            $db = $this->ci->db->select($select, false)
                ->from('inv_component_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.component_id', $componentId);
            if ($divisionId > 0) {
                $db->where('s.division_id', $divisionId);
            }

            return $db->get()->row_array() ?: ['qty_on_hand' => 0.0, 'avg_cost' => 0.0];
        }

        return ['qty_on_hand' => 0.0, 'avg_cost' => 0.0];
    }

    private function load_cache_row(int $outletId, int $productId): ?array
    {
        if ($outletId <= 0 || $productId <= 0 || !$this->ci->db->table_exists('pos_product_availability_cache')) {
            return null;
        }

        return $this->ci->db->from('pos_product_availability_cache')
            ->where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function apply_cache_control_rules(array $live, array $product, ?array $override): array
    {
        $payload = $live;
        $payload['source_mode'] = 'AUTO';

        if ($override) {
            $mode = strtoupper(trim((string)($override['override_mode'] ?? 'AUTO')));
            if ($mode === 'FORCE_OUT') {
                $payload['availability_status'] = 'OUT';
                $payload['source_mode'] = 'OVERRIDE_OUT';
                $payload['override_allowed'] = 0;
                $payload['estimated_available_qty'] = 0.0;
                $payload['estimated_available_qty_main'] = 0.0;
                if (trim((string)($override['override_note'] ?? '')) !== '') {
                    $payload['bottleneck_name_snapshot'] = trim((string)$override['override_note']);
                } else {
                    $payload['bottleneck_name_snapshot'] = 'Forced out by override';
                }
            } elseif ($mode === 'FORCE_AVAILABLE') {
                $payload['availability_status'] = 'AVAILABLE';
                $payload['source_mode'] = 'OVERRIDE_AVAILABLE';
                $payload['override_allowed'] = 1;
                if (trim((string)($override['override_note'] ?? '')) !== '') {
                    $payload['bottleneck_name_snapshot'] = trim((string)$override['override_note']);
                } elseif (trim((string)($payload['bottleneck_name_snapshot'] ?? '')) === '') {
                    $payload['bottleneck_name_snapshot'] = 'Forced available by override';
                }
            }
        }

        return $payload;
    }

    private function compare_cache_and_live(array $cache, array $live): array
    {
        $statusMismatch = strtoupper((string)($cache['availability_status'] ?? '')) !== strtoupper((string)($live['availability_status'] ?? ''));
        $qtyCache = round((float)($cache['estimated_available_qty'] ?? 0), 4);
        $qtyLive = round((float)($live['estimated_available_qty'] ?? 0), 4);
        $qtyMismatch = abs($qtyCache - $qtyLive) > 0.0001;
        $hppCache = round((float)($cache['hpp_live_snapshot'] ?? 0), 6);
        $hppLive = round((float)($live['hpp_live_snapshot'] ?? 0), 6);
        $hppMismatch = abs($hppCache - $hppLive) > 0.01;
        $bottleneckMismatch = trim((string)($cache['bottleneck_name_snapshot'] ?? '')) !== trim((string)($live['bottleneck_name_snapshot'] ?? ''));

        $isDirty = !empty($cache['is_dirty']);
        $mismatch = $statusMismatch || $qtyMismatch || $hppMismatch || $bottleneckMismatch || $isDirty;
        $notes = [];
        if ($statusMismatch) $notes[] = 'status berbeda';
        if ($qtyMismatch) $notes[] = 'qty berbeda';
        if ($hppMismatch) $notes[] = 'hpp berbeda';
        if ($bottleneckMismatch) $notes[] = 'bottleneck berbeda';
        if ($isDirty) $notes[] = 'cache masih dirty';

        return [
            'mismatch_flag' => $mismatch ? 1 : 0,
            'status_mismatch' => $statusMismatch ? 1 : 0,
            'qty_mismatch' => $qtyMismatch ? 1 : 0,
            'hpp_mismatch' => $hppMismatch ? 1 : 0,
            'bottleneck_mismatch' => $bottleneckMismatch ? 1 : 0,
            'note' => !empty($notes) ? implode(', ', $notes) : 'match',
        ];
    }

    private function write_rebuild_log(int $outletId, int $productId, ?array $before, ?array $afterCache, ?array $live, array $context, string $note = ''): int
    {
        if (!$this->ci->db->table_exists('pos_product_availability_rebuild_log')) {
            return 0;
        }

        $comparison = ($afterCache && $live) ? $this->compare_cache_and_live($afterCache, $live) : ['mismatch_flag' => 1, 'note' => $note];
        $this->ci->db->insert('pos_product_availability_rebuild_log', [
            'event_source' => strtoupper(trim((string)($context['event_source'] ?? ($context['trigger_context'] ?? 'MANUAL')))),
            'event_table' => $this->nullable_text($context['event_table'] ?? ''),
            'event_id' => !empty($context['event_id']) ? (int)$context['event_id'] : null,
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'cache_status_before' => $this->nullable_text($before['availability_status'] ?? ''),
            'cache_qty_before' => round((float)($before['estimated_available_qty'] ?? 0), 4),
            'cache_status_after' => $this->nullable_text($afterCache['availability_status'] ?? ''),
            'cache_qty_after' => round((float)($afterCache['estimated_available_qty'] ?? 0), 4),
            'live_status' => $this->nullable_text($live['availability_status'] ?? ''),
            'live_qty' => round((float)($live['estimated_available_qty'] ?? 0), 4),
            'live_hpp' => round((float)($live['hpp_live_snapshot'] ?? 0), 6),
            'mismatch_flag' => !empty($comparison['mismatch_flag']) ? 1 : 0,
            'mismatch_note' => $this->nullable_text($note !== '' ? $note : ($comparison['note'] ?? '')),
            'actor_employee_id' => !empty($context['actor_employee_id']) ? (int)$context['actor_employee_id'] : null,
            'rebuilt_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->ci->db->insert_id();
    }

    private function write_probe(int $outletId, int $productId, ?array $cache, array $live, array $comparison, array $context): int
    {
        if (!$this->ci->db->table_exists('pos_product_availability_probe')) {
            return 0;
        }

        $this->ci->db->trans_begin();
        try {
            $this->ci->db->insert('pos_product_availability_probe', [
                'outlet_id' => $outletId,
                'product_id' => $productId,
                'cache_status' => $this->nullable_text($cache['availability_status'] ?? ''),
                'cache_qty' => round((float)($cache['estimated_available_qty'] ?? 0), 4),
                'live_status' => (string)($live['availability_status'] ?? ''),
                'live_qty' => round((float)($live['estimated_available_qty'] ?? 0), 4),
                'mismatch_flag' => !empty($comparison['mismatch_flag']) ? 1 : 0,
                'trigger_context' => $this->nullable_text($context['trigger_context'] ?? 'MANUAL_PROBE'),
                'created_by' => !empty($context['actor_employee_id']) ? (int)$context['actor_employee_id'] : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $probeId = (int)$this->ci->db->insert_id();

            if ($probeId > 0 && $this->ci->db->table_exists('pos_product_availability_probe_line')) {
                $lineNo = 1;
                foreach ((array)($live['lines'] ?? []) as $line) {
                    $required = round((float)($line['required_qty_per_unit'] ?? 0), 4);
                    $available = round((float)($line['available_qty_live'] ?? 0), 4);
                    $short = round(max(0, $required - $available), 4);
                    $this->ci->db->insert('pos_product_availability_probe_line', [
                        'probe_id' => $probeId,
                        'line_no' => $lineNo++,
                        'source_kind' => strtoupper((string)($line['line_type'] ?? 'MATERIAL')),
                        'source_id' => !empty($line['material_id']) ? (int)$line['material_id'] : (!empty($line['component_id']) ? (int)$line['component_id'] : null),
                        'source_name_snapshot' => $this->nullable_text($line['source_name_snapshot'] ?? ''),
                        'source_role' => strtoupper((string)($line['source_role'] ?? 'MAIN')),
                        'required_qty' => $required,
                        'available_qty_live' => $available,
                        'short_qty' => $short,
                        'is_bottleneck' => $short > 0 ? 1 : 0,
                        'cost_source' => $this->nullable_text($line['cost_source'] ?? 'LIVE_BALANCE'),
                    ]);
                }
            }

            if ($this->ci->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan probe availability POS.');
            }
            $this->ci->db->trans_commit();
            return $probeId;
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            return 0;
        }
    }

    private function normalize_source_role(string $role): string
    {
        $role = strtoupper(trim($role));
        if (in_array($role, ['GARNISH', 'SAUCE', 'TOPPING'], true)) {
            return 'COMPLEMENT';
        }
        if (in_array($role, ['SUPPORT', 'OTHER'], true)) {
            return 'SUPPORT';
        }
        if ($role === 'OPTIONAL') {
            return 'OPTIONAL';
        }
        return 'MAIN';
    }

    private function nullable_text($value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
