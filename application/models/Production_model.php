<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Production_model extends CI_Model
{
    private $componentFormulaLinesCache = [];
    private $componentFormulaSummaryCache = [];
    private $formulaLineCostCache = [];
    private $itemMaterialCache = [];
    private $materialLookupCache = [];
    private $materialBalanceCache = [];
    private $componentLookupCache = [];
    private $componentBalanceCache = [];
    private $divisionCodeCache = [];

    /** Monetary amounts are recorded by FIFO, never recomputed at balance average. */
    private function component_movement_value(array $row): float
    {
        $qty = (float)($row['qty_in'] ?? 0) + (float)($row['qty_out'] ?? 0);
        return round((float)($row['total_cost'] ?? ($qty * (float)($row['unit_cost'] ?? 0))), 2);
    }

    private function component_value_corrections(string $startDate, string $endDate): array
    {
        if (!$this->db->table_exists('inv_stock_value_reconciliation')) {
            return [];
        }
        return $this->db->from('inv_stock_value_reconciliation')
            ->where('stock_domain', 'COMPONENT')
            ->where('period_month >=', substr($startDate, 0, 7) . '-01')
            ->where('period_month <=', substr($endDate, 0, 7) . '-01')
            ->get()->result_array();
    }

    /**
     * Revaluations are absolute checkpoints, not another purchase or quantity
     * movement. Use posting time for their boundary: a backdated void created
     * today must not disappear behind yesterday's correction. Legacy second-
     * precision ties are accepted only when the quantity snapshot resolves the
     * boundary to a unique monetary result. Ambiguity must never overwrite stock.
     */
    private function component_value_projection(array $identity, array $logs, array $corrections, float $seedQty, float $seedValue, string $asOfDate): array
    {
        $qty = $seedQty;
        $value = $seedValue;
        $eligible = [];
        foreach ($logs as $log) {
            if ((string)$log['movement_date'] > $asOfDate) {
                continue;
            }
            $qty += (float)($log['qty_in'] ?? 0) - (float)($log['qty_out'] ?? 0);
            $value += ((float)($log['qty_in'] ?? 0) > 0 ? 1 : -1) * $this->component_movement_value($log);
            $eligible[] = $log;
        }
        $checkpoint = null;
        foreach ($corrections as $row) {
            if ((int)($row['component_id'] ?? 0) !== (int)$identity['component_id']
                || (int)($row['content_uom_id'] ?? 0) !== (int)$identity['uom_id']
                || (int)($row['division_id'] ?? 0) !== (int)($identity['division_id'] ?? 0)
                || (string)($row['location_type'] ?? '') !== (string)$identity['location_type']
                || !in_array($row['status'] ?? '', ['POSTED', 'VOID'], true)) {
                continue;
            }
            foreach (['posted_at' => 'stock_value_after', 'voided_at' => 'stock_value_before'] as $timeKey => $valueKey) {
                if ($timeKey === 'voided_at' && $row['status'] !== 'VOID') {
                    continue;
                }
                $at = (string)($row[$timeKey] ?? '');
                if ($at === '' || substr($at, 0, 10) > $asOfDate) {
                    continue;
                }
                $eventKey = [$at, (int)$row['id'], $timeKey === 'voided_at' ? 1 : 0];
                if ($checkpoint === null || $eventKey > $checkpoint['key']) {
                    $checkpoint = ['key' => $eventKey, 'qty' => (float)$row['stock_qty_snapshot'], 'value' => (float)$row[$valueKey]];
                }
            }
        }
        if ($checkpoint !== null) {
            $afterQty = $checkpoint['qty'];
            $afterValue = $checkpoint['value'];
            $ties = [];
            foreach ($eligible as $log) {
                $at = (string)($log['created_at'] ?? $log['movement_datetime'] ?? ($log['movement_date'] . ' 00:00:00'));
                if ($at === $checkpoint['key'][0]) {
                    $ties[] = $log;
                } elseif ($at > $checkpoint['key'][0]) {
                    $afterQty += (float)($log['qty_in'] ?? 0) - (float)($log['qty_out'] ?? 0);
                    $afterValue += ((float)($log['qty_in'] ?? 0) > 0 ? 1 : -1) * $this->component_movement_value($log);
                }
            }
            usort($ties, static fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
            $candidates = [];
            if (abs($afterQty - $qty) < 0.0001) {
                $candidates[number_format($afterValue, 2, '.', '')] = round($afterValue, 2);
            }
            foreach ($ties as $log) {
                $afterQty += (float)($log['qty_in'] ?? 0) - (float)($log['qty_out'] ?? 0);
                $afterValue += ((float)($log['qty_in'] ?? 0) > 0 ? 1 : -1) * $this->component_movement_value($log);
                if (abs($afterQty - $qty) < 0.0001) {
                    $candidates[number_format($afterValue, 2, '.', '')] = round($afterValue, 2);
                }
            }
            if (count($candidates) !== 1) {
                return ['ok' => false, 'qty' => round($qty, 4), 'value' => round($value, 2),
                    'message' => 'Urutan koreksi nilai dan mutasi component #' . (int)$identity['component_id'] . ' belum dapat dipastikan. Saldo tidak ditimpa; periksa riwayat koreksi dan mutasi terlebih dahulu.'];
            }
            $value = reset($candidates);
        }
        return ['ok' => true, 'qty' => round($qty, 4), 'value' => round($value, 2)];
    }

    private function ensure_component_adjustment_reason_helper(): void
    {
        if (function_exists('normalize_component_adjustment_reason_code')) {
            return;
        }
        $CI =& get_instance();
        if ($CI && isset($CI->load)) {
            $CI->load->helper('component_adjustment_reason');
        }
    }

    private function normalizeComponentAdjustmentReasonCode(string $value, string $category): ?string
    {
        $this->ensure_component_adjustment_reason_helper();
        if (!function_exists('normalize_component_adjustment_reason_code')) {
            return null;
        }
        return normalize_component_adjustment_reason_code($value, $category);
    }

    private function division_name_column()
    {
        if ($this->db->field_exists('division_name', 'mst_operational_division')) {
            return 'division_name';
        }
        if ($this->db->field_exists('name', 'mst_operational_division')) {
            return 'name';
        }
        return null;
    }

    private function division_code_column()
    {
        if ($this->db->field_exists('code', 'mst_operational_division')) {
            return 'code';
        }
        if ($this->db->field_exists('division_code', 'mst_operational_division')) {
            return 'division_code';
        }
        return null;
    }

    private function operational_division_code(int $divisionId): string
    {
        if ($divisionId <= 0) {
            return '';
        }
        if (array_key_exists($divisionId, $this->divisionCodeCache)) {
            return $this->divisionCodeCache[$divisionId];
        }
        $column = $this->division_code_column();
        if ($column === null) {
            $this->divisionCodeCache[$divisionId] = '';
            return '';
        }
        $row = $this->db->select($column . ' AS code', false)
            ->from('mst_operational_division')
            ->where('id', $divisionId)
            ->limit(1)
            ->get()
            ->row_array();
        $this->divisionCodeCache[$divisionId] = strtoupper(trim((string)($row['code'] ?? '')));
        return $this->divisionCodeCache[$divisionId];
    }

    private function regular_material_destination_for_division(int $divisionId): ?string
    {
        $code = $this->operational_division_code($divisionId);
        if ($code === 'BAR') {
            return 'BAR';
        }
        if ($code === 'KITCHEN') {
            return 'KITCHEN';
        }
        if ($code === 'ROASTERY') {
            return 'ROASTERY';
        }
        return null;
    }

    private function regular_component_location_for_division(int $divisionId): ?string
    {
        return $this->regular_material_destination_for_division($divisionId);
    }

    private function component_type_sort_sql(string $column): string
    {
        return "CASE UPPER({$column}) WHEN 'BASE' THEN 0 WHEN 'PREPARE' THEN 1 ELSE 9 END";
    }

    private function apply_component_display_order(?string $divisionExpr, string $typeExpr, string $nameExpr): void
    {
        if ($divisionExpr !== null && $divisionExpr !== '') {
            $this->db->order_by($divisionExpr, 'ASC');
        }
        $this->db->order_by($this->component_type_sort_sql($typeExpr), '', false);
        $this->db->order_by($nameExpr, 'ASC');
    }

    private function compare_component_display_rows(array $left, array $right, string $divisionKey = 'division_name', string $typeKey = 'component_type', string $nameKey = 'component_name'): int
    {
        $divisionCompare = strcasecmp((string)($left[$divisionKey] ?? ''), (string)($right[$divisionKey] ?? ''));
        if ($divisionCompare !== 0) {
            return $divisionCompare;
        }

        $typeWeight = static function ($value): int {
            $normalized = strtoupper(trim((string)$value));
            if ($normalized === 'BASE') {
                return 0;
            }
            if ($normalized === 'PREPARE') {
                return 1;
            }
            return 9;
        };

        $typeCompare = $typeWeight($left[$typeKey] ?? '') <=> $typeWeight($right[$typeKey] ?? '');
        if ($typeCompare !== 0) {
            return $typeCompare;
        }

        return strcasecmp((string)($left[$nameKey] ?? ''), (string)($right[$nameKey] ?? ''));
    }

    private function component_operational_context(int $componentId): ?array
    {
        if ($componentId <= 0) {
            return null;
        }
        if (array_key_exists($componentId, $this->componentLookupCache)) {
            return $this->componentLookupCache[$componentId];
        }

        $divisionNameColumn = $this->division_name_column();
        $divisionCodeColumn = $this->division_code_column();
        $select = [
            'c.id',
            'c.component_name',
            'c.component_type',
            'c.uom_id',
            'c.operational_division_id',
        ];
        if ($divisionCodeColumn !== null) {
            $select[] = 'd.' . $divisionCodeColumn . ' AS division_code';
        }
        if ($divisionNameColumn !== null) {
            $select[] = 'd.' . $divisionNameColumn . ' AS division_name';
        }

        $row = $this->db->select(implode(', ', $select), false)
            ->from('mst_component c')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();

        $this->componentLookupCache[$componentId] = $row ?: null;
        return $this->componentLookupCache[$componentId];
    }

    private function location_group_to_component_location(?array $componentContext, string $locationType): ?string
    {
        $locationType = strtoupper(trim($locationType));
        if ($locationType === '') {
            return null;
        }

        $validLocations = ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'];
        if (in_array($locationType, $validLocations, true)) {
            return $locationType;
        }

        if (!in_array($locationType, ['REGULER', 'EVENT'], true) || empty($componentContext['operational_division_id'])) {
            return null;
        }

        $divisionCode = strtoupper(trim((string)($componentContext['division_code'] ?? '')));
        if ($divisionCode === 'BAR') {
            return $locationType === 'EVENT' ? 'BAR_EVENT' : 'BAR';
        }
        if ($divisionCode === 'KITCHEN') {
            return $locationType === 'EVENT' ? 'KITCHEN_EVENT' : 'KITCHEN';
        }
        if ($divisionCode === 'ROASTERY') {
            return $locationType === 'EVENT' ? 'ROASTERY_EVENT' : 'ROASTERY';
        }

        return null;
    }

    private function location_to_group(string $locationType): string
    {
        $locationType = strtoupper(trim($locationType));
        if ($locationType === 'BAR_EVENT' || $locationType === 'KITCHEN_EVENT' || $locationType === 'ROASTERY_EVENT') {
            return 'EVENT';
        }
        if ($locationType === 'BAR' || $locationType === 'KITCHEN' || $locationType === 'ROASTERY') {
            return 'REGULER';
        }
        return $locationType;
    }

    private function location_filter_values(string $locationType): array
    {
        $locationType = strtoupper(trim($locationType));
        if ($locationType === '') {
            return [];
        }
        if ($locationType === 'REGULER') {
            return ['BAR', 'KITCHEN', 'ROASTERY'];
        }
        if ($locationType === 'EVENT') {
            return ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'];
        }
        if (in_array($locationType, ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true)) {
            return [$locationType];
        }
        return [];
    }

    private function apply_component_location_filter(string $column, string $locationType): void
    {
        $values = $this->location_filter_values($locationType);
        if (empty($values)) {
            return;
        }
        if (count($values) === 1) {
            $this->db->where($column, $values[0]);
            return;
        }
        $this->db->where_in($column, $values);
    }

    private function resolve_opening_component_context(array $lines): array
    {
        $resolvedDivisionId = null;
        $context = null;

        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = (float)($line['opening_qty'] ?? 0);
            if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                continue;
            }

            $row = $this->component_operational_context($componentId);
            if (!$row || (int)($row['operational_division_id'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Semua component opening wajib memiliki divisi operasional.'];
            }

            $divisionId = (int)$row['operational_division_id'];
            if ($resolvedDivisionId === null) {
                $resolvedDivisionId = $divisionId;
                $context = $row;
                continue;
            }
            if ($resolvedDivisionId !== $divisionId) {
                return ['ok' => false, 'message' => 'Opening hanya boleh memuat component dari satu divisi operasional yang sama.'];
            }
        }

        if ($resolvedDivisionId === null || !$context) {
            return ['ok' => false, 'message' => 'Tambahkan minimal satu component opening yang valid.'];
        }

        return [
            'ok' => true,
            'division_id' => $resolvedDivisionId,
            'component_context' => $context,
        ];
    }

    private function resolve_adjustment_component_context(array $lines): array
    {
        $resolvedDivisionId = null;
        $context = null;

        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            $totalQty = round(
                (float)($line['qty_spoil'] ?? 0)
                + (float)($line['qty_waste'] ?? 0)
                + (float)($line['qty_adjust_pos'] ?? 0)
                + (float)($line['qty_adjust_neg'] ?? 0),
                4
            );
            if ($componentId <= 0 || $uomId <= 0 || $totalQty <= 0) {
                continue;
            }

            $row = $this->component_operational_context($componentId);
            if (!$row || (int)($row['operational_division_id'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Semua component adjustment wajib memiliki divisi operasional.'];
            }

            $divisionId = (int)$row['operational_division_id'];
            if ($resolvedDivisionId === null) {
                $resolvedDivisionId = $divisionId;
                $context = $row;
                continue;
            }
            if ($resolvedDivisionId !== $divisionId) {
                return ['ok' => false, 'message' => 'Adjustment hanya boleh memuat component dari satu divisi operasional yang sama.'];
            }
        }

        if ($resolvedDivisionId === null || !$context) {
            return ['ok' => false, 'message' => 'Tambahkan minimal satu baris adjustment yang valid.'];
        }

        return [
            'ok' => true,
            'division_id' => $resolvedDivisionId,
            'component_context' => $context,
        ];
    }

    public function resolve_component_adjustment_division(array $lines): array
    {
        return $this->resolve_adjustment_component_context($lines);
    }

    private function normalize_month_key(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 0, 7);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m', $timestamp);
    }

    private function upsert_by_unique(string $table, array $rowData, array $uniqueColumns): void
    {
        $query = $this->db->from($table);
        foreach ($uniqueColumns as $column) {
            $value = $rowData[$column] ?? null;
            if ($value === null) {
                $this->db->where($column . ' IS NULL', null, false);
            } else {
                $this->db->where($column, $value);
            }
        }
        $existing = $query->limit(1)->get()->row_array();
        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update($table, $rowData);
            return;
        }
        $this->db->insert($table, $rowData);
    }

    public function component_stock_rows(array $filters, $limit = 200, int $page = 1, ?array &$meta = null)
    {
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = trim((string)($filters['month'] ?? date('Y-m')));
            if (!preg_match('/^\d{4}\-\d{2}$/', $targetMonth)) {
                $targetMonth = date('Y-m');
            }
            $seedRows = $this->fetch_component_monthly_projection_seed_rows($filters, $targetMonth . '-01', true);
            if (empty($seedRows)) {
                return [];
            }

            $rows = [];
            foreach ($seedRows as $seedRow) {
                $rows[] = [
                    'location_type' => (string)($seedRow['location_type'] ?? ''),
                    'division_id' => $seedRow['division_id'] !== null ? (int)$seedRow['division_id'] : null,
                    'division_name' => (string)($seedRow['division_name'] ?? '-'),
                    'component_id' => (int)($seedRow['component_id'] ?? 0),
                    'component_code' => (string)($seedRow['component_code'] ?? ''),
                    'component_name' => (string)($seedRow['component_name'] ?? ''),
                    'component_type' => (string)($seedRow['component_type'] ?? ''),
                    'uom_id' => (int)($seedRow['uom_id'] ?? 0),
                    'uom_code' => (string)($seedRow['uom_code'] ?? ''),
                    'uom_name' => (string)($seedRow['uom_name'] ?? ''),
                    'qty_on_hand' => round((float)($seedRow['closing_qty'] ?? 0), 4),
                    'avg_cost' => round((float)($seedRow['avg_cost'] ?? 0), 6),
                    'total_value' => round((float)($seedRow['total_value'] ?? 0), 2),
                    'last_txn_at' => (string)($seedRow['last_movement_at'] ?? '') !== ''
                        ? (string)$seedRow['last_movement_at']
                        : ((string)($seedRow['month_key'] ?? '') !== '' ? (string)$seedRow['month_key'] . ' 00:00:00' : ''),
                    'updated_at' => (string)($seedRow['updated_at'] ?? ''),
                ];
            }

            usort($rows, function (array $left, array $right): int {
                $displayCompare = $this->compare_component_display_rows($left, $right);
                if ($displayCompare !== 0) {
                    return $displayCompare;
                }

                $locationCompare = strcmp((string)($left['location_type'] ?? ''), (string)($right['location_type'] ?? ''));
                if ($locationCompare !== 0) {
                    return $locationCompare;
                }

                return ((int)($left['component_id'] ?? 0) <=> (int)($right['component_id'] ?? 0));
            });

            $totalRows = count($rows);
            $totalValue = 0.0;
            $baseCount = 0;
            $prepareCount = 0;
            $negativeCount = 0;
            $zeroCount = 0;
            foreach ($rows as $row) {
                $totalValue += (float)($row['total_value'] ?? 0);
                $type = strtoupper(trim((string)($row['component_type'] ?? '')));
                if ($type === 'BASE') {
                    $baseCount++;
                } elseif ($type === 'PREPARE') {
                    $prepareCount++;
                }
                $qty = (float)($row['qty_on_hand'] ?? 0);
                if ($qty < 0) {
                    $negativeCount++;
                } elseif ($qty == 0.0) {
                    $zeroCount++;
                }
            }

            $limit = max(0, (int)$limit);
            $page = max(1, $page);
            $maxPage = $limit > 0 ? max(1, (int)ceil($totalRows / $limit)) : 1;
            $page = min($page, $maxPage);
            if ($limit > 0) {
                $rows = array_slice($rows, ($page - 1) * $limit, $limit);
            }

            if ($meta !== null) {
                $meta = [
                    'total_rows'     => $totalRows,
                    'total_value'    => round($totalValue, 2),
                    'base_count'     => $baseCount,
                    'prepare_count'  => $prepareCount,
                    'negative_count' => $negativeCount,
                    'zero_count'     => $zeroCount,
                    'page'           => $page,
                    'per_page'       => $limit,
                    'max_page'       => $maxPage,
                ];
            }

            return $this->attach_component_lot_summaries($rows);
        }

        return [];
    }

    public function component_movement_rows(array $filters, $limit = 300)
    {
        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $movementType = strtoupper(trim((string)($filters['movement_type'] ?? '')));
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select("
            m.id,
            m.movement_no,
            m.movement_date,
            m.movement_datetime,
            m.location_type,
            m.division_id,
            {$divisionNameSelect},
            m.component_id,
            c.component_code,
            c.component_name,
            m.uom_id,
            u.code AS uom_code,
            m.movement_type,
            m.qty_in,
            m.qty_out,
            m.unit_cost,
            m.total_cost,
            m.source_module,
            m.source_table,
            m.source_id,
            m.source_line_id,
            m.lot_no_snapshot,
            m.received_date_snapshot,
            m.notes
        ", false);
        $this->db->from('inv_component_movement_log m');
        $this->db->join('mst_component c', 'c.id = m.component_id', 'inner');
        $this->db->join('mst_operational_division d', 'd.id = m.division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = m.uom_id', 'left');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');

        $this->apply_component_location_filter('m.location_type', $locationType);
        if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $componentType);
        }
        if ($movementType !== '') {
            $this->db->where('m.movement_type', $movementType);
        }
        if ($dateFrom !== '') {
            $this->db->where('m.movement_date >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $this->db->where('m.movement_date <=', $dateTo);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->or_like('m.movement_no', $q)
                ->group_end();
        }
        $this->db->order_by('m.movement_date', 'ASC');
        $this->db->order_by('m.movement_datetime', 'ASC');
        $this->db->order_by('m.id', 'ASC');
        $rows = $this->db->get()->result_array();
        $rows = $this->attach_component_movement_balances($rows, $dateFrom);
        foreach ($rows as &$row) {
            $row['movement_type_label'] = $this->component_movement_type_label((string)($row['movement_type'] ?? ''));
        }
        unset($row);

        usort($rows, static function (array $left, array $right): int {
            $timeCompare = strcmp((string)($right['movement_datetime'] ?? ''), (string)($left['movement_datetime'] ?? ''));
            if ($timeCompare !== 0) {
                return $timeCompare;
            }
            return (int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0);
        });

        return max(1, (int)$limit) > 0 ? array_slice($rows, 0, max(1, (int)$limit)) : $rows;
    }

    private function attach_component_movement_balances(array $rows, string $dateFrom): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $runningBalances = $this->component_movement_opening_balances_before_date($rows, $dateFrom);
        foreach ($rows as &$row) {
            $key = $this->component_movement_balance_key($row);
            $beforeQty = (float)($runningBalances[$key] ?? 0.0);
            $deltaQty = round((float)($row['qty_in'] ?? 0) - (float)($row['qty_out'] ?? 0), 4);
            $afterQty = round($beforeQty + $deltaQty, 4);

            $row['qty_before'] = round($beforeQty, 4);
            $row['qty_delta'] = $deltaQty;
            $row['qty_after'] = $afterQty;
            $runningBalances[$key] = $afterQty;
        }
        unset($row);

        return $rows;
    }

    private function component_movement_opening_balances_before_date(array $rows, string $dateFrom): array
    {
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            return [];
        }

        $locationTypes = [];
        $componentIds = [];
        $uomIds = [];
        $divisionIds = [];
        $hasNullDivision = false;
        foreach ($rows as $row) {
            $locationType = strtoupper(trim((string)($row['location_type'] ?? '')));
            $componentId = (int)($row['component_id'] ?? 0);
            $uomId = (int)($row['uom_id'] ?? 0);
            $divisionId = array_key_exists('division_id', $row) && $row['division_id'] !== null ? (int)$row['division_id'] : null;
            if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
                continue;
            }
            $locationTypes[$locationType] = $locationType;
            $componentIds[$componentId] = $componentId;
            $uomIds[$uomId] = $uomId;
            if ($divisionId === null) {
                $hasNullDivision = true;
            } else {
                $divisionIds[$divisionId] = $divisionId;
            }
        }

        if (empty($locationTypes) || empty($componentIds) || empty($uomIds)) {
            return [];
        }

        $query = $this->db->select('m.location_type, COALESCE(m.division_id, 0) AS division_id_key, m.component_id, m.uom_id, COALESCE(SUM(m.qty_in - m.qty_out), 0) AS qty_before', false)
            ->from('inv_component_movement_log m')
            ->where('m.movement_date <', $dateFrom)
            ->where_in('m.location_type', array_values($locationTypes))
            ->where_in('m.component_id', array_values($componentIds))
            ->where_in('m.uom_id', array_values($uomIds));

        if (!empty($divisionIds) && $hasNullDivision) {
            $query->group_start()
                ->where_in('m.division_id', array_values($divisionIds))
                ->or_where('m.division_id IS NULL', null, false)
                ->group_end();
        } elseif (!empty($divisionIds)) {
            $query->where_in('m.division_id', array_values($divisionIds));
        } elseif ($hasNullDivision) {
            $query->where('m.division_id IS NULL', null, false);
        }

        $balanceRows = $query
            ->group_by(['m.location_type', 'division_id_key', 'm.component_id', 'm.uom_id'])
            ->get()
            ->result_array();

        $balances = [];
        foreach ($balanceRows as $balanceRow) {
            $key = implode('|', [
                strtoupper(trim((string)($balanceRow['location_type'] ?? ''))),
                (int)($balanceRow['division_id_key'] ?? 0),
                (int)($balanceRow['component_id'] ?? 0),
                (int)($balanceRow['uom_id'] ?? 0),
            ]);
            $balances[$key] = round((float)($balanceRow['qty_before'] ?? 0), 4);
        }

        return $balances;
    }

    private function component_movement_balance_key(array $row): string
    {
        return implode('|', [
            strtoupper(trim((string)($row['location_type'] ?? ''))),
            (int)($row['division_id'] ?? 0),
            (int)($row['component_id'] ?? 0),
            (int)($row['uom_id'] ?? 0),
        ]);
    }

    private function component_movement_type_label(string $movementType): string
    {
        $movementType = strtoupper(trim($movementType));
        $map = [
            'OPENING' => 'Opening',
            'PRODUCTION_IN' => 'Hasil Produksi',
            'PRODUCTION_OUT' => 'Pemakaian Produksi',
            'TRANSFER_IN' => 'Transfer Masuk',
            'TRANSFER_OUT' => 'Transfer Keluar',
            'USAGE' => 'Pemakaian',
            'USAGE_OUT' => 'Pemakaian',
            'WASTE' => 'Waste',
            'WASTE_OUT' => 'Waste',
            'SPOIL' => 'Spoilage',
            'SPOIL_OUT' => 'Spoilage',
            'ADJUSTMENT_PLUS' => 'Adjustment Plus',
            'ADJUSTMENT_IN' => 'Adjustment Plus',
            'ADJUSTMENT_MINUS' => 'Adjustment Minus',
            'ADJUSTMENT' => 'Adjustment',
            'VOID_REVERSE' => 'Pembatalan Void',
            'VOID_OUT' => 'Pembatalan Void',
        ];

        return $map[$movementType] ?? ($movementType !== '' ? $movementType : '-');
    }

    private function apply_component_movement_after_anchor(string $movementDate, int $movementId): void
    {
        $movementDate = substr(trim($movementDate), 0, 10);
        if ($movementDate === '') {
            $this->db->where('id >', max(0, $movementId));
            return;
        }

        $this->db->group_start()
            ->where('movement_date >', $movementDate)
            ->or_group_start()
                ->where('movement_date', $movementDate)
                ->where('id >', max(0, $movementId))
            ->group_end()
        ->group_end();
    }

    private function component_batch_trace_label(array $row, int $outputComponentId): string
    {
        $planRole = strtoupper(trim((string)($row['plan_role'] ?? '')));
        if ($planRole === 'INLINE_OUTPUT') {
            return 'Produksi Inline';
        }
        if ($planRole === 'INLINE_COMPONENT_USAGE') {
            return 'Pakai Hasil Inline';
        }

        $movementType = strtoupper(trim((string)($row['movement_type'] ?? '')));
        $componentId = (int)($row['component_id'] ?? 0);
        if ($movementType === 'PRODUCTION_IN' && $componentId === $outputComponentId) {
            return 'Output Batch Utama';
        }
        if ($movementType === 'PRODUCTION_OUT') {
            return 'Pemakaian Component';
        }

        return 'Trace Batch';
    }

    public function component_daily_rows(array $filters, $limit = 500)
    {
        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $month = trim((string)($filters['month'] ?? date('Y-m')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $projectionRows = $this->fetch_component_daily_projection_rows($filters, $startDate, $endDate);
        if ($projectionRows !== null) {
            usort($projectionRows, function (array $left, array $right): int {
                $displayCompare = $this->compare_component_display_rows($left, $right);
                if ($displayCompare !== 0) {
                    return $displayCompare;
                }

                $dateCompare = strcmp((string)($right['movement_date'] ?? ''), (string)($left['movement_date'] ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return ((int)($right['component_id'] ?? 0) <=> (int)($left['component_id'] ?? 0));
            });

            if ($limit > 0 && count($projectionRows) > $limit) {
                return array_slice($projectionRows, 0, $limit);
            }

            return $projectionRows;
        }

        return [];
    }

    public function latest_component_daily_month(array $filters = []): ?string
    {
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));

        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $this->db->select('MAX(s.month_key) AS max_month', false)
                ->from('inv_component_monthly_stock s')
                ->join('mst_component c', 'c.id = s.component_id', 'inner');
            $this->apply_component_location_filter('s.location_type', $locationType);
            if ($divisionId > 0) {
                $this->db->where('s.division_id', $divisionId);
            }
            if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
                $this->db->where('c.component_type', $componentType);
            }

            $row = $this->db->get()->row_array();
            $maxMonth = trim((string)($row['max_month'] ?? ''));
            if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $maxMonth)) {
                return substr($maxMonth, 0, 7);
            }
        }

        if ($this->db->table_exists('inv_component_movement_log')) {
            $this->db->select('MAX(m.movement_date) AS max_date', false)
                ->from('inv_component_movement_log m')
                ->join('mst_component c', 'c.id = m.component_id', 'inner');
            $this->apply_component_location_filter('m.location_type', $locationType);
            if ($divisionId > 0) {
                $this->db->where('m.division_id', $divisionId);
            }
            if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
                $this->db->where('c.component_type', $componentType);
            }

            $row = $this->db->get()->row_array();
            $maxDate = trim((string)($row['max_date'] ?? ''));
            if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $maxDate)) {
                return substr($maxDate, 0, 7);
            }
        }

        return null;
    }

    public function component_daily_matrix(array $filters, int $limit = 500): array
    {
        $month = trim((string)($filters['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $windowStart = $month . '-01';
        $windowEnd = date('Y-m-t', strtotime($windowStart));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $q = trim((string)($filters['q'] ?? ''));
        $rows = $this->component_daily_rows($filters, 0);

        $dates = $this->month_date_series($windowStart, $windowEnd);
        $dateMap = [];
        foreach ($dates as $d) {
            $dateMap[(string)$d['date']] = [
                'opening' => 0.0,
                'in' => 0.0,
                'out' => 0.0,
                'adj' => 0.0,
                'closing' => 0.0,
                'usage_out' => 0.0,
                'waste' => 0.0,
                'spoil' => 0.0,
            ];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $movementDate = (string)($row['movement_date'] ?? '');
            if (!isset($dateMap[$movementDate])) {
                continue;
            }

            $key = implode('|', [
                (string)($row['location_type'] ?? ''),
                (int)($row['division_id'] ?? 0),
                (int)($row['component_id'] ?? 0),
                (int)($row['uom_id'] ?? 0),
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'location_type' => (string)($row['location_type'] ?? ''),
                    'division_id' => (int)($row['division_id'] ?? 0),
                    'division_name' => (string)($row['division_name'] ?? '-'),
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'component_code' => (string)($row['component_code'] ?? ''),
                    'component_name' => (string)($row['component_name'] ?? ''),
                    'component_type' => (string)($row['component_type'] ?? ''),
                    'category_name' => (string)($row['category_name'] ?? ''),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'uom_code' => (string)($row['uom_code'] ?? ''),
                    'days' => $dateMap,
                    'total_opening' => 0.0,
                    'total_in' => 0.0,
                    'total_out' => 0.0,
                    'total_adj' => 0.0,
                    'total_closing' => 0.0,
                    'avg_cost' => 0.0,
                    'total_value' => 0.0,
                    '_seed_opening' => round((float)($row['opening_qty'] ?? 0), 4),
                    '_min_date' => $movementDate,
                ];
            }

            $opening = round((float)($row['opening_qty'] ?? 0), 4);
            $in = round((float)($row['in_qty'] ?? 0), 4);
            $usageOut = round((float)($row['out_qty'] ?? 0), 4);
            $waste = round((float)($row['waste_qty'] ?? 0), 4);
            $spoil = round((float)($row['spoil_qty'] ?? 0), 4);
            $out = $usageOut; // waste/spoil masuk adj (negatif), bukan out
            $adj = round((float)($row['adjustment_qty'] ?? 0), 4);
            $closing = round((float)($row['closing_qty'] ?? 0), 4);

            $grouped[$key]['days'][$movementDate] = [
                'opening' => $opening,
                'in' => $in,
                'out' => $out,
                'adj' => $adj,
                'closing' => $closing,
                'usage_out' => $usageOut,
                'waste' => $waste,
                'spoil' => $spoil,
            ];
            $grouped[$key]['total_opening'] += $opening;
            $grouped[$key]['total_in'] += $in;
            $grouped[$key]['total_out'] += $out;
            $grouped[$key]['total_adj'] += $adj;
            $grouped[$key]['total_closing'] = $closing;
            $grouped[$key]['avg_cost'] = round((float)($row['avg_cost'] ?? 0), 6);
            $grouped[$key]['total_value'] = round((float)($row['total_value'] ?? 0), 2);
            if ($movementDate !== '' && (($grouped[$key]['_min_date'] ?? '') === '' || $movementDate < (string)$grouped[$key]['_min_date'])) {
                $grouped[$key]['_min_date'] = $movementDate;
                $grouped[$key]['_seed_opening'] = $opening;
            }
        }

        $dateKeys = array_keys($dateMap);
        foreach ($grouped as &$group) {
            $firstOpening = null;
            $lastClosing = 0.0;
            $seedOpening = round((float)($group['_seed_opening'] ?? 0), 4);
            foreach ($dateKeys as $index => $dateKey) {
                $day = (array)($group['days'][$dateKey] ?? []);
                $in = round((float)($day['in'] ?? 0), 4);
                $out = round((float)($day['out'] ?? 0), 4);
                $adj = round((float)($day['adj'] ?? 0), 4);

                if ($index === 0) {
                    $opening = $seedOpening;
                } else {
                    $opening = round($lastClosing, 4);
                }

                $closing = round($opening + $in - $out + $adj, 4);
                if (abs($closing) < 0.0001) {
                    $closing = 0.0;
                }

                $group['days'][$dateKey] = [
                    'opening' => $opening,
                    'in' => $in,
                    'out' => $out,
                    'adj' => $adj,
                    'closing' => $closing,
                    'usage_out' => round((float)($day['usage_out'] ?? 0), 4),
                    'waste' => round((float)($day['waste'] ?? 0), 4),
                    'spoil' => round((float)($day['spoil'] ?? 0), 4),
                ];

                if ($firstOpening === null) {
                    $firstOpening = $opening;
                }
                $lastClosing = $closing;
            }

            $group['total_opening'] = round((float)($firstOpening ?? 0), 4);
            $group['total_closing'] = round($lastClosing, 4);
            unset($group['_seed_opening'], $group['_min_date']);
        }
        unset($group);

        $matrixRows = array_values($grouped);
        $matrixRows = $this->attach_component_lot_summaries($matrixRows);
        $matrixRows = $this->attach_component_daily_lot_rows($matrixRows, $dates, $windowStart, $windowEnd);
        $matrixRows = $this->attach_component_monthly_summary_to_daily_rows($matrixRows, $filters, $windowStart);
        usort($matrixRows, static function (array $left, array $right): int {
            $locationRank = static function (array $row): int {
                return substr(strtoupper((string)($row['location_type'] ?? '')), -6) === '_EVENT' ? 1 : 0;
            };
            $compare = $locationRank($left) <=> $locationRank($right);
            if ($compare !== 0) {
                return $compare;
            }
            $typeRank = static function (array $row): int {
                return strtoupper((string)($row['component_type'] ?? '')) === 'BASE' ? 0 : 1;
            };
            $compare = $typeRank($left) <=> $typeRank($right);
            if ($compare !== 0) {
                return $compare;
            }
            $compare = strcasecmp((string)($left['category_name'] ?? ''), (string)($right['category_name'] ?? ''));
            return $compare !== 0 ? $compare : strcasecmp((string)($left['component_name'] ?? ''), (string)($right['component_name'] ?? ''));
        });
        if ($limit > 0 && count($matrixRows) > $limit) {
            $matrixRows = array_slice($matrixRows, 0, $limit);
        }

        return [
            'window' => [
                'month' => $month,
                'date_from' => $windowStart,
                'date_to' => $windowEnd,
            ],
            'dates' => $dates,
            'rows' => $matrixRows,
        ];
    }

    private function attach_component_monthly_summary_to_daily_rows(array $rows, array $filters, string $monthStart): array
    {
        if (empty($rows) || !$this->db->table_exists('inv_component_monthly_stock')) {
            return $rows;
        }

        $monthlyRows = $this->fetch_component_monthly_generate_source_rows($filters, $monthStart);
        if (empty($monthlyRows)) {
            return $rows;
        }

        $monthlyMap = [];
        foreach ($monthlyRows as $monthlyRow) {
            $key = $this->component_identity_key(
                (string)($monthlyRow['location_type'] ?? ''),
                $monthlyRow['division_id'] ?? null,
                (int)($monthlyRow['component_id'] ?? 0),
                (int)($monthlyRow['uom_id'] ?? 0)
            );

            $adjustmentQty = round(
                (float)($monthlyRow['adjustment_plus_qty'] ?? 0)
                - (float)($monthlyRow['adjustment_minus_qty'] ?? 0)
                - (float)($monthlyRow['waste_qty'] ?? 0)
                - (float)($monthlyRow['spoil_qty'] ?? 0),
                4
            );

            $monthlyMap[$key] = [
                'total_opening' => round((float)($monthlyRow['opening_qty'] ?? 0), 4),
                'total_in' => round((float)($monthlyRow['in_qty'] ?? 0), 4),
                'total_out' => round((float)($monthlyRow['out_qty'] ?? 0), 4),
                'total_adj' => $adjustmentQty,
                'total_closing' => round((float)($monthlyRow['closing_qty'] ?? 0), 4),
                'avg_cost' => round((float)($monthlyRow['avg_cost'] ?? 0), 6),
                'total_value' => round((float)($monthlyRow['total_value'] ?? 0), 2),
                'monthly_source_mode' => (string)($monthlyRow['source_mode'] ?? ''),
            ];
        }

        foreach ($rows as &$row) {
            $key = $this->component_identity_key(
                (string)($row['location_type'] ?? ''),
                $row['division_id'] ?? null,
                (int)($row['component_id'] ?? 0),
                (int)($row['uom_id'] ?? 0)
            );
            if (!isset($monthlyMap[$key])) {
                continue;
            }

            $summary = $monthlyMap[$key];
            $row['total_opening'] = $summary['total_opening'];
            $row['total_in'] = $summary['total_in'];
            $row['total_out'] = $summary['total_out'];
            $row['total_adj'] = $summary['total_adj'];
            $row['total_closing'] = $summary['total_closing'];
            $row['avg_cost'] = $summary['avg_cost'];
            $row['total_value'] = $summary['total_value'];
            $row['monthly_source_mode'] = $summary['monthly_source_mode'];
            $row['summary_source'] = 'MONTHLY_STOCK';
        }
        unset($row);

        return $rows;
    }

    private function _component_monthly_rows_from_stock(array $filters, int $limit): ?array
    {
        if (!$this->db->table_exists('inv_component_monthly_stock')) {
            return null; // tabel belum ada → fallback
        }

        $month = trim((string)($filters['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';

        // Cek apakah bulan ini sudah ada data di monthly_stock (tanpa filter)
        $monthCount = (int)$this->db
            ->where('month_key', $monthStart)
            ->from('inv_component_monthly_stock')
            ->count_all_results();
        if ($monthCount === 0) {
            return null; // belum ada data bulan ini → fallback ke daily projection
        }

        $q             = trim((string)($filters['q'] ?? ''));
        $locationType  = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId    = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $divNameCol    = $this->division_name_column();
        $divNameSelect = $divNameCol !== null ? ('d.' . $divNameCol . ' AS division_name') : 'NULL AS division_name';

        $this->db->select('s.*, (s.adjustment_plus_qty - s.adjustment_minus_qty) AS adjustment_qty, c.component_code, c.component_name, c.component_type, u.code AS uom_code, u.name AS uom_name, ' . $divNameSelect, false)
            ->from('inv_component_monthly_stock s')
            ->join('mst_component c', 'c.id = s.component_id', 'inner')
            ->join('mst_uom u', 'u.id = s.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->where('s.month_key', $monthStart);

        $this->apply_component_location_filter('s.location_type', $locationType);
        if ($divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }
        if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $componentType);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q);
            if ($divNameCol !== null) {
                $this->db->or_like('d.' . $divNameCol, $q);
            }
            $this->db->group_end();
        }

        $this->apply_component_display_order(
            $divNameCol !== null ? ('d.' . $divNameCol) : 's.division_id',
            'c.component_type',
            'c.component_name'
        );
        $this->db->order_by('s.location_type', 'ASC');
        if ($limit > 0) {
            $this->db->limit($limit);
        }

        $rows = $this->db->get()->result_array();
        return $this->attach_component_lot_summaries($rows); // bisa kosong jika filter ketat
    }

    public function component_monthly_rows(array $filters, int $limit = 500): array
    {
        // Baca dari snapshot monthly_stock jika tersedia — sumber kebenaran resmi.
        // Fallback ke daily projection hanya jika bulan itu belum pernah Repair/Generate.
        $fromStock = $this->_component_monthly_rows_from_stock($filters, $limit);
        if ($fromStock !== null) {
            return $fromStock;
        }

        $daily = $this->component_daily_rows(array_merge($filters, ['type' => (string)($filters['type'] ?? '')]), max(1, $limit * 31));
        if (empty($daily)) {
            return [];
        }

        $grouped = [];
        foreach ($daily as $row) {
            $key = implode('|', [
                (string)($row['location_type'] ?? ''),
                (int)($row['division_id'] ?? 0),
                (int)($row['component_id'] ?? 0),
                (int)($row['uom_id'] ?? 0),
            ]);
            $movementDate = (string)($row['movement_date'] ?? '');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'location_type' => (string)($row['location_type'] ?? ''),
                    'division_id' => (int)($row['division_id'] ?? 0),
                    'division_name' => (string)($row['division_name'] ?? '-'),
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'component_code' => (string)($row['component_code'] ?? ''),
                    'component_name' => (string)($row['component_name'] ?? ''),
                    'component_type' => (string)($row['component_type'] ?? ''),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'uom_code' => (string)($row['uom_code'] ?? ''),
                    'opening_qty' => round((float)($row['opening_qty'] ?? 0), 4),
                    'in_qty' => 0.0,
                    'out_qty' => 0.0,
                    'adjustment_qty' => 0.0,
                    'waste_qty' => 0.0,
                    'spoil_qty' => 0.0,
                    'closing_qty' => round((float)($row['closing_qty'] ?? 0), 4),
                    'avg_cost' => round((float)($row['avg_cost'] ?? 0), 6),
                    'total_value' => round((float)($row['total_value'] ?? 0), 2),
                    '_min_date' => $movementDate,
                    '_max_date' => $movementDate,
                ];
            }

            $grouped[$key]['in_qty'] += round((float)($row['in_qty'] ?? 0), 4);
            $grouped[$key]['out_qty'] += round((float)($row['out_qty'] ?? 0), 4);
            $grouped[$key]['adjustment_qty'] += round((float)($row['adjustment_qty'] ?? 0), 4);
            $grouped[$key]['waste_qty'] += round((float)($row['waste_qty'] ?? 0), 4);
            $grouped[$key]['spoil_qty'] += round((float)($row['spoil_qty'] ?? 0), 4);

            if ($movementDate !== '' && $movementDate < $grouped[$key]['_min_date']) {
                $grouped[$key]['_min_date'] = $movementDate;
                $grouped[$key]['opening_qty'] = round((float)($row['opening_qty'] ?? 0), 4);
            }
            if ($movementDate !== '' && $movementDate >= $grouped[$key]['_max_date']) {
                $grouped[$key]['_max_date'] = $movementDate;
                $grouped[$key]['closing_qty'] = round((float)($row['closing_qty'] ?? 0), 4);
                $grouped[$key]['avg_cost'] = round((float)($row['avg_cost'] ?? 0), 6);
                $grouped[$key]['total_value'] = round((float)($row['total_value'] ?? 0), 2);
            }
        }

        $rows = array_values($grouped);
        usort($rows, function (array $left, array $right): int {
            return $this->compare_component_display_rows($left, $right);
        });

        $rows = $this->attach_component_lot_summaries($rows);

        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    public function component_reconcile_rows(array $filters, int $limit = 300): array
    {
        $asOfDate = trim((string)($filters['as_of_date'] ?? ''));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $asOfMonth = substr($asOfDate, 0, 7) . '-01';
        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $componentIdFilter = !empty($filters['component_id']) ? (int)$filters['component_id'] : 0;
        $uomIdFilter = !empty($filters['uom_id']) ? (int)$filters['uom_id'] : 0;
        $divisionNameColumn = $this->division_name_column();

        $liveMap = [];
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $liveRows = $this->fetch_component_monthly_projection_seed_rows($filters, $asOfMonth, true);
            foreach ($liveRows as $row) {
                $key = $this->component_identity_key((string)($row['location_type'] ?? ''), $row['division_id'] ?? null, (int)($row['component_id'] ?? 0), (int)($row['uom_id'] ?? 0));
                $liveMap[$key] = [
                    'monthly_stock_id' => (int)($row['id'] ?? 0),
                    'location_type' => (string)($row['location_type'] ?? ''),
                    'division_id' => $row['division_id'] !== null ? (int)$row['division_id'] : null,
                    'division_name' => (string)($row['division_name'] ?? '-'),
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'component_code' => (string)($row['component_code'] ?? ''),
                    'component_name' => (string)($row['component_name'] ?? ''),
                    'component_type' => (string)($row['component_type'] ?? ''),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'uom_code' => (string)($row['uom_code'] ?? ''),
                    'balance_qty' => round((float)($row['closing_qty'] ?? 0), 4),
                    'monthly_closing_qty' => round((float)($row['closing_qty'] ?? 0), 4),
                    'balance_avg_cost' => round((float)($row['avg_cost'] ?? 0), 6),
                    'balance_total_value' => round((float)($row['total_value'] ?? 0), 2),
                    'balance_last_txn_at' => (string)($row['last_movement_at'] ?? ''),
                    'seed_month_key' => (string)($row['month_key'] ?? ''),
                    'opening_qty' => round((float)($row['opening_qty'] ?? 0), 4),
                    'opening_total_value' => round((float)($row['opening_total_value'] ?? 0), 2),
                ];
            }
        }

        $dailyMap = [];
        $dailyRows = $this->fetch_component_daily_projection_rows(array_merge($filters, ['month' => substr($asOfDate, 0, 7)]), substr($asOfDate, 0, 7) . '-01', $asOfDate);
        if ($dailyRows !== null) {
            foreach ($dailyRows as $row) {
                $key = $this->component_identity_key((string)($row['location_type'] ?? ''), $row['division_id'] ?? null, (int)($row['component_id'] ?? 0), (int)($row['uom_id'] ?? 0));
                $currentDate = (string)($dailyMap[$key]['daily_date'] ?? '');
                $rowDate = (string)($row['movement_date'] ?? '');
                if ($currentDate !== '' && $rowDate < $currentDate) {
                    continue;
                }

                $dailyMap[$key] = [
                    'daily_date' => (string)($row['movement_date'] ?? ''),
                    'daily_qty' => round((float)($row['closing_qty'] ?? 0), 4),
                    'daily_avg_cost' => round((float)($row['avg_cost'] ?? 0), 6),
                    'daily_total_value' => round((float)($row['total_value'] ?? 0), 2),
                    'location_type' => (string)($row['location_type'] ?? ''),
                    'division_id' => $row['division_id'] !== null ? (int)$row['division_id'] : null,
                    'division_name' => (string)($row['division_name'] ?? '-'),
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'component_code' => (string)($row['component_code'] ?? ''),
                    'component_name' => (string)($row['component_name'] ?? ''),
                    'component_type' => (string)($row['component_type'] ?? ''),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'uom_code' => (string)($row['uom_code'] ?? ''),
                ];
            }
        }

        // Untuk bulan audit yang sedang berjalan, row monthly bisa sudah memuat
        // mutasi masa depan dalam bulan yang sama (mis. adjustment tanggal 30).
        // Stock live POS dibaca sebagai state "as of tanggal audit", jadi audit
        // compare juga harus memakai state proyeksi harian pada bulan yang sama.
        foreach ($liveMap as $key => $row) {
            if ((string)($row['seed_month_key'] ?? '') !== $asOfMonth) {
                continue;
            }
            if (!isset($dailyMap[$key])) {
                continue;
            }
            $liveMap[$key]['balance_qty'] = round((float)($dailyMap[$key]['daily_qty'] ?? 0), 4);
            // Month-end has an authoritative closing value too. Reconstructing
            // it from old movement costs can resurrect already corrected drift.
            // Earlier days must still use their historical valuation.
            if (!$this->component_reconcile_uses_monthly_value(
                $asOfDate,
                (float)($row['monthly_closing_qty'] ?? 0),
                (float)($dailyMap[$key]['daily_qty'] ?? 0)
            )) {
                $liveMap[$key]['balance_avg_cost'] = round((float)($dailyMap[$key]['daily_avg_cost'] ?? 0), 6);
                $liveMap[$key]['balance_total_value'] = round((float)($dailyMap[$key]['daily_total_value'] ?? 0), 2);
            }
            $liveMap[$key]['balance_last_txn_at'] = (string)($dailyMap[$key]['daily_date'] ?? '');
        }

        $movementMap = [];
        if ($this->db->table_exists('inv_component_movement_log')) {
            $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
            $this->db->select('m.id, m.created_at, m.movement_no, m.movement_date, m.movement_datetime, m.location_type, m.division_id, ' . $divisionNameSelect . ', m.component_id, c.component_code, c.component_name, c.component_type, m.uom_id, u.code AS uom_code, m.movement_type, m.qty_in, m.qty_out, m.unit_cost, m.total_cost, m.source_module, m.source_table, m.source_id, m.source_line_id, m.notes', false)
                ->from('inv_component_movement_log m')
                ->join('mst_component c', 'c.id = m.component_id', 'inner')
                ->join('mst_operational_division d', 'd.id = m.division_id', 'left')
                ->join('mst_uom u', 'u.id = m.uom_id', 'left')
                ->where('m.movement_date <=', $asOfDate);
            $this->apply_component_location_filter('m.location_type', $locationType);
            if ($divisionId > 0) {
                $this->db->where('m.division_id', $divisionId);
            }
            if ($componentIdFilter > 0) {
                $this->db->where('m.component_id', $componentIdFilter);
            }
            if ($uomIdFilter > 0) {
                $this->db->where('m.uom_id', $uomIdFilter);
            }
            if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
                $this->db->where('c.component_type', $componentType);
            }
            if ($q !== '') {
                $this->db->group_start()
                    ->like('c.component_code', $q)
                    ->or_like('c.component_name', $q);
                if ($divisionNameColumn !== null) {
                    $this->db->or_like('d.' . $divisionNameColumn, $q);
                }
                $this->db->group_end();
            }
            $movementRows = $this->db
                ->order_by('m.movement_date', 'ASC')
                ->order_by('m.movement_datetime', 'ASC')
                ->order_by('m.id', 'ASC')
                ->get()
                ->result_array();
            $runningQty = [];
            $runningAvg = [];
            $runningValue = [];
            $valuationLogs = [];
            foreach ($movementRows as $row) {
                $key = $this->component_identity_key((string)($row['location_type'] ?? ''), $row['division_id'] ?? null, (int)($row['component_id'] ?? 0), (int)($row['uom_id'] ?? 0));
                $valuationLogs[$key][] = $row;
                $beforeQty = (float)($runningQty[$key] ?? 0);
                $beforeAvg = (float)($runningAvg[$key] ?? 0);
                $beforeValue = (float)($runningValue[$key] ?? 0);
                $qtyIn = round((float)($row['qty_in'] ?? 0), 4);
                $qtyOut = round((float)($row['qty_out'] ?? 0), 4);
                $qtyAfter = round($beforeQty + $qtyIn - $qtyOut, 4);
                $unitCost = round((float)($row['unit_cost'] ?? 0), 6);
                $movementValue = $this->component_movement_value($row);
                if ($qtyIn > 0) {
                    $valueAfter = round($beforeValue + $movementValue, 2);
                } elseif ($qtyOut > 0) {
                    $valueAfter = round($beforeValue - $movementValue, 2);
                } else {
                    $valueAfter = $beforeValue;
                }
                if (abs($qtyAfter) < 0.0001) {
                    $qtyAfter = 0.0;
                }
                if (abs($valueAfter) < 0.01) {
                    $valueAfter = 0.0;
                }
                $avgAfter = abs($qtyAfter) > 0.0001 ? round($valueAfter / $qtyAfter, 6) : 0.0;

                $runningQty[$key] = $qtyAfter;
                $runningAvg[$key] = $avgAfter;
                $runningValue[$key] = $valueAfter;
                $movementMap[$key] = [
                    'movement_date' => (string)($row['movement_date'] ?? ''),
                    'movement_datetime' => (string)($row['movement_datetime'] ?? ''),
                    'movement_no' => (string)($row['movement_no'] ?? ''),
                    'movement_qty' => $qtyAfter,
                    'movement_avg_cost' => $avgAfter,
                    'movement_total_value' => round($valueAfter, 2),
                    'location_type' => (string)($row['location_type'] ?? ''),
                    'division_id' => $row['division_id'] !== null ? (int)$row['division_id'] : null,
                    'division_name' => (string)($row['division_name'] ?? '-'),
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'component_code' => (string)($row['component_code'] ?? ''),
                    'component_name' => (string)($row['component_name'] ?? ''),
                    'component_type' => (string)($row['component_type'] ?? ''),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'uom_code' => (string)($row['uom_code'] ?? ''),
                ];
            }
            $corrections = $this->component_value_corrections('1970-01-01', $asOfDate);
            foreach ($movementMap as $key => &$movementSummary) {
                $valuation = $this->component_value_projection($movementSummary, $valuationLogs[$key], $corrections, 0, 0, $asOfDate);
                $movementSummary['movement_total_value'] = $valuation['value'];
                $movementSummary['movement_avg_cost'] = abs($valuation['qty']) > 0.0001 ? round($valuation['value'] / $valuation['qty'], 6) : 0.0;
                $movementSummary['valuation_warning'] = $valuation['ok'] ? '' : $valuation['message'];
            }
            unset($movementSummary);
        }

        $allKeys = array_unique(array_merge(array_keys($liveMap), array_keys($dailyMap), array_keys($movementMap)));
        $rows = [];
        foreach ($allKeys as $key) {
            $base = $liveMap[$key] ?? $dailyMap[$key] ?? $movementMap[$key] ?? [];
            if (empty($base)) {
                continue;
            }
            $balanceQty = round((float)($liveMap[$key]['balance_qty'] ?? 0), 4);
            $dailyQty = round((float)($dailyMap[$key]['daily_qty'] ?? 0), 4);
            $movementQty = round((float)($movementMap[$key]['movement_qty'] ?? 0), 4);
            $verdict = $this->build_component_reconcile_verdict(
                $balanceQty,
                $dailyQty,
                $movementQty,
                [
                    'daily_date' => (string)($dailyMap[$key]['daily_date'] ?? ''),
                    'movement_date' => (string)($movementMap[$key]['movement_date'] ?? ''),
                ]
            );
            $rows[] = [
                'location_type' => (string)($base['location_type'] ?? ''),
                'division_id' => $base['division_id'] !== null ? (int)$base['division_id'] : null,
                'division_name' => (string)($base['division_name'] ?? '-'),
                'component_id' => (int)($base['component_id'] ?? 0),
                'component_code' => (string)($base['component_code'] ?? ''),
                'component_name' => (string)($base['component_name'] ?? ''),
                'component_type' => (string)($base['component_type'] ?? ''),
                'uom_id' => (int)($base['uom_id'] ?? 0),
                'uom_code' => (string)($base['uom_code'] ?? ''),
                'monthly_stock_id' => (int)($liveMap[$key]['monthly_stock_id'] ?? 0),
                'balance_qty' => $balanceQty,
                'monthly_qty' => round((float)($liveMap[$key]['monthly_closing_qty'] ?? $balanceQty), 4),
                'daily_qty' => $dailyQty,
                'movement_qty' => $movementQty,
                'balance_avg_cost' => round((float)($liveMap[$key]['balance_avg_cost'] ?? 0), 6),
                'monthly_total_value' => round((float)($liveMap[$key]['balance_total_value'] ?? 0), 2),
                'daily_total_value' => round((float)($dailyMap[$key]['daily_total_value'] ?? 0), 2),
                'movement_total_value' => round((float)($movementMap[$key]['movement_total_value'] ?? 0), 2),
                'delta_balance_daily' => round($balanceQty - $dailyQty, 4),
                'delta_balance_movement' => round($balanceQty - $movementQty, 4),
                'delta_daily_movement' => round($dailyQty - $movementQty, 4),
                'daily_date' => (string)($dailyMap[$key]['daily_date'] ?? ''),
                'movement_date' => (string)($movementMap[$key]['movement_date'] ?? ''),
                'movement_no' => (string)($movementMap[$key]['movement_no'] ?? ''),
                'valuation_warning' => (string)($movementMap[$key]['valuation_warning'] ?? ''),
                'suspect_table' => (string)($verdict['suspect_table'] ?? 'MATCH'),
                'suspect_reason' => (string)($verdict['reason'] ?? ''),
                'is_match' => abs($balanceQty - $dailyQty) < 0.0001
                    && abs($balanceQty - $movementQty) < 0.0001
                    && abs($dailyQty - $movementQty) < 0.0001,
            ];
        }

        $this->attach_component_lot_totals($rows, $asOfDate);
        $this->apply_component_lot_value_verdict($rows);
        foreach ($rows as &$valuationRow) {
            if (!empty($valuationRow['valuation_warning'])) {
                $valuationRow['is_match'] = false;
                $valuationRow['suspect_reason'] .= ' ' . $valuationRow['valuation_warning'];
            }
        }
        unset($valuationRow);
        $this->attach_component_daily_check($rows);

        usort($rows, function (array $left, array $right): int {
            return $this->compare_component_display_rows($left, $right);
        });

        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'as_of_date' => $asOfDate,
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'matched' => count(array_filter($rows, static function (array $row): bool {
                    return !empty($row['is_match']);
                })),
                'mismatched' => count(array_filter($rows, static function (array $row): bool {
                    return empty($row['is_match']);
                })),
            ],
        ];
    }

    private function component_reconcile_uses_monthly_value(string $asOfDate, float $monthlyQty, float $projectedQty): bool
    {
        if ($asOfDate === date('Y-m-d')) {
            return true;
        }
        return $asOfDate === date('Y-m-t', strtotime($asOfDate))
            && $asOfDate < date('Y-m-d')
            && abs($monthlyQty - $projectedQty) < 0.0001;
    }

    /**
     * Sync FIFO lot total to match monthly stock balance.
     * If lot > stock: deduct from oldest lots (FIFO) to bring total down.
     * If stock < 0: targetLot = 0 (close all lots).
     * If gap <= 0.01: no action needed.
     */
    public function repair_component_lot_balance_to_stock(string $locationType, ?int $divisionId, int $componentId, int $uomId): array
    {
        if (!$this->db->table_exists('inv_component_lot')) {
            return ['ok' => false, 'message' => 'Tabel inv_component_lot belum tersedia.'];
        }

        $divSql    = $divisionId !== null ? ('ms.division_id = ' . (int)$divisionId) : 'ms.division_id IS NULL';
        $divLotSql = $divisionId !== null ? ('l.division_id = ' . (int)$divisionId)  : 'l.division_id IS NULL';

        $monthRow = $this->db->query(
            "SELECT COALESCE(ms.closing_qty, 0) AS closing_qty
             FROM inv_component_monthly_stock ms
             WHERE ms.location_type = ? AND {$divSql} AND ms.component_id = ? AND ms.uom_id = ?
             ORDER BY ms.month_key DESC LIMIT 1",
            [$locationType, $componentId, $uomId]
        )->row_array();
        $stockBalance = round((float)($monthRow['closing_qty'] ?? 0), 4);
        $targetLot    = max(0.0, $stockBalance);

        $lotRow = $this->db->query(
            "SELECT SUM(l.qty_balance) AS lot_total
             FROM inv_component_lot l
             WHERE l.location_type = ? AND {$divLotSql} AND l.component_id = ? AND l.uom_id = ?
               AND l.status = 'OPEN' AND l.qty_balance > 0.0001",
            [$locationType, $componentId, $uomId]
        )->row_array();
        $lotTotal = round((float)($lotRow['lot_total'] ?? 0), 4);

        $gap = round($lotTotal - $targetLot, 4);

        if (abs($gap) <= 0.01) {
            return ['ok' => true, 'message' => 'Lot dan stok sudah sesuai.', 'data' => [
                'stock_balance' => $stockBalance, 'lot_total' => $lotTotal, 'gap' => $gap,
            ]];
        }

        if ($gap < -0.01) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Lot lebih kecil dari stok (' . round($lotTotal, 2) . ' vs ' . round($stockBalance, 2) . '). Repair otomatis tidak dilakukan; gunakan adjustment manual.', 'data' => [
                'stock_balance' => $stockBalance, 'lot_total' => $lotTotal, 'gap' => $gap,
            ]];
        }

        // gap > 0: lot over-states stock — deduct oldest lots first (FIFO)
        $openLots = $this->db->query(
            "SELECT id, qty_balance FROM inv_component_lot l
             WHERE l.location_type = ? AND {$divLotSql} AND l.component_id = ? AND l.uom_id = ?
               AND l.status = 'OPEN' AND l.qty_balance > 0.0001
             ORDER BY l.receipt_date ASC, l.id ASC",
            [$locationType, $componentId, $uomId]
        )->result_array();

        $remaining = $gap;
        $now = date('Y-m-d H:i:s');
        foreach ($openLots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }
            $lotQty  = round((float)$lot['qty_balance'], 4);
            $deduct  = round(min($remaining, $lotQty), 4);
            $newBal  = round($lotQty - $deduct, 4);
            $this->db->where('id', (int)$lot['id'])->update('inv_component_lot', [
                'qty_balance' => $newBal,
                'status'      => $newBal < 0.0001 ? 'CLOSED' : 'OPEN',
                'updated_at'  => $now,
            ]);
            $remaining = round($remaining - $deduct, 4);
        }

        return ['ok' => true, 'message' => 'Lot berhasil disesuaikan ke stok.', 'data' => [
            'stock_balance' => $stockBalance, 'lot_total' => $lotTotal, 'target_lot' => $targetLot, 'gap' => $gap,
        ]];
    }

    public function fix_component_movement_log_to_stock(string $locationType, ?int $divisionId, int $componentId, int $uomId): array
    {
        if (!$this->db->table_exists('inv_component_movement_log') || !$this->db->table_exists('inv_component_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel movement log atau monthly stock tidak ditemukan.'];
        }
        if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
            return ['ok' => false, 'message' => 'location_type, component_id, dan uom_id diperlukan.'];
        }

        $divStockSql = $divisionId !== null ? ('s.division_id = ' . (int)$divisionId) : 's.division_id IS NULL';
        $divLogSql   = $divisionId !== null ? ('division_id = ' . (int)$divisionId) : 'division_id IS NULL';

        $stockRow = $this->db->query(
            "SELECT s.closing_qty, s.avg_cost, s.id
             FROM inv_component_monthly_stock s
             WHERE s.location_type = ? AND {$divStockSql} AND s.component_id = ? AND s.uom_id = ?
             ORDER BY s.month_key DESC LIMIT 1",
            [$locationType, $componentId, $uomId]
        )->row_array();

        if (!$stockRow) {
            return ['ok' => false, 'message' => 'Data monthly stock tidak ditemukan.'];
        }

        $stockQty = round((float)($stockRow['closing_qty'] ?? 0), 4);
        $unitCost = round((float)($stockRow['avg_cost'] ?? 0), 6);

        $logRow = $this->db->query(
            "SELECT ROUND(COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0), 4) AS log_net
             FROM inv_component_movement_log
             WHERE location_type = ? AND {$divLogSql} AND component_id = ? AND uom_id = ?",
            [$locationType, $componentId, $uomId]
        )->row_array();

        $logNet = round((float)($logRow['log_net'] ?? 0), 4);
        $delta  = round($stockQty - $logNet, 4);

        if (abs($delta) < 0.0001) {
            return ['ok' => true, 'message' => 'Movement log sudah sesuai dengan stok bulanan.', 'data' => [
                'stock_qty' => $stockQty, 'log_net' => $logNet, 'delta' => $delta,
            ]];
        }

        $now          = date('Y-m-d H:i:s');
        $movementDate = date('Y-m-d');
        $movementNo   = 'RECONFIX-LOG-' . date('YmdHis') . '-C' . $componentId . '-D' . ($divisionId ?? 0);

        $this->db->insert('inv_component_movement_log', [
            'movement_no'       => $movementNo,
            'movement_date'     => $movementDate,
            'movement_datetime' => $now,
            'location_type'     => $locationType,
            'division_id'       => $divisionId,
            'component_id'      => $componentId,
            'uom_id'            => $uomId,
            'movement_type'     => $delta > 0 ? 'ADJUSTMENT_PLUS' : 'ADJUSTMENT_MINUS',
            'qty_in'            => $delta > 0 ? round(abs($delta), 4) : 0,
            'qty_out'           => $delta < 0 ? round(abs($delta), 4) : 0,
            'unit_cost'         => $unitCost,
            'total_cost'        => round(abs($delta) * $unitCost, 2),
            'source_module'     => 'RECONCILE_FIX',
            'source_table'      => 'inv_component_monthly_stock',
            'source_id'         => (int)($stockRow['id'] ?? 0),
            'source_line_id'    => null,
            'notes'             => 'Koreksi rekonsiliasi: log net ' . $logNet . ' → stok ' . $stockQty,
            'created_by'        => null,
            'created_at'        => $now,
        ]);

        if ($this->db->affected_rows() < 1) {
            return ['ok' => false, 'message' => 'Gagal menyisipkan entri koreksi ke movement log.'];
        }

        return ['ok' => true, 'message' => 'Entri koreksi movement log berhasil dibuat (' . ($delta > 0 ? '+' : '') . $delta . ').', 'data' => [
            'stock_qty'     => $stockQty,
            'log_net'       => $logNet,
            'delta'         => $delta,
            'movement_no'   => $movementNo,
            'movement_type' => $delta > 0 ? 'ADJUSTMENT_PLUS' : 'ADJUSTMENT_MINUS',
        ]];
    }

    private function attach_component_lot_totals(array &$rows, ?string $asOfDate = null): void
    {
        foreach ($rows as &$row) {
            $row['lot_qty'] = 0.0;
            $row['lot_total_value'] = 0.0;
            $row['lot_count'] = 0;
            $row['lot_rows'] = [];
        }
        unset($row);
        if (!$this->db->table_exists('inv_component_lot') || empty($rows)) { return; }
        $componentIds = array_values(array_unique(array_filter(array_map(
            static function ($r) { return isset($r['component_id']) ? (int)$r['component_id'] : null; }, $rows
        ))));
        if (empty($componentIds)) { return; }
        $windowEnd = null;
        if (is_string($asOfDate) && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $windowEnd = $asOfDate;
        }
        // Saldo lot adalah state berjalan. Lot yang diterima pada bulan sebelumnya
        // tetap harus dihitung selama masih memiliki saldo pada tanggal acuan.
        $lotTotalQuery = $this->db
            ->select('l.location_type, COALESCE(l.division_id,0) AS division_id, l.component_id, l.uom_id, SUM(l.qty_balance) AS lot_total, SUM(l.qty_balance * l.unit_cost) AS lot_total_value', false)
            ->from('inv_component_lot l')
            ->where('l.status', 'OPEN')
            ->where('l.qty_balance >', 0.0001)
            ->where_in('l.component_id', $componentIds);
        if ($windowEnd !== null) {
            $lotTotalQuery->where('l.receipt_date <=', $windowEnd);
        }
        $results = $lotTotalQuery
            ->group_by(['l.location_type', 'l.division_id', 'l.component_id', 'l.uom_id'])
            ->get()->result_array();
        $lotMap = [];
        foreach ($results as $r) {
            $k = strtoupper((string)$r['location_type']) . '|' . (int)$r['division_id']
               . '|C-' . (int)$r['component_id'] . '|U-' . (int)$r['uom_id'];
            $lotMap[$k] = [
                'qty' => round((float)($r['lot_total'] ?? 0), 4),
                'value' => round((float)($r['lot_total_value'] ?? 0), 2),
            ];
        }
        // Rincian hanya menampilkan lot yang masih aktif/bersaldo pada tanggal acuan.
        $lotDetailQuery = $this->db
            ->select('l.id, l.location_type, COALESCE(l.division_id,0) AS division_id, l.component_id, l.uom_id, l.lot_no, l.receipt_date, l.expiry_date, l.unit_cost, l.qty_in_total, l.qty_out_total, l.qty_balance, l.status', false)
            ->from('inv_component_lot l')
            ->where_in('l.component_id', $componentIds)
            ->group_start()
                ->where('l.status', 'OPEN')
                ->or_where('ABS(COALESCE(l.qty_balance, 0)) > 0.0001', null, false)
            ->group_end();
        if ($windowEnd !== null) {
            $lotDetailQuery->where('l.receipt_date <=', $windowEnd);
        }
        $lotDetailRows = $lotDetailQuery
            ->order_by('l.location_type')->order_by('l.division_id')->order_by('l.receipt_date')->order_by('l.id')
            ->get()->result_array();
        $lotRowsMap = [];
        foreach ($lotDetailRows as $r) {
            $k = strtoupper((string)$r['location_type']) . '|' . (int)$r['division_id']
               . '|C-' . (int)$r['component_id'] . '|U-' . (int)$r['uom_id'];
            $lotRowsMap[$k][] = [
                'id'          => (int)$r['id'],
                'lot_no'      => (string)$r['lot_no'],
                'receipt_date'=> (string)$r['receipt_date'],
                'expiry_date' => (string)($r['expiry_date'] ?? ''),
                'unit_cost'   => round((float)$r['unit_cost'], 6),
                'qty_in'      => round((float)$r['qty_in_total'], 4),
                'qty_out'     => round((float)$r['qty_out_total'], 4),
                'qty_balance' => round((float)$r['qty_balance'], 4),
                'status'      => (string)$r['status'],
            ];
        }
        foreach ($rows as &$row) {
            $locType = strtoupper((string)($row['location_type'] ?? ''));
            $divId   = $row['division_id'] !== null ? (int)$row['division_id'] : 0;
            $k = $locType . '|' . $divId
               . '|C-' . (int)($row['component_id'] ?? 0)
               . '|U-' . (int)($row['uom_id'] ?? 0);
            $row['lot_qty']   = (float)($lotMap[$k]['qty'] ?? 0.0);
            $row['lot_total_value'] = (float)($lotMap[$k]['value'] ?? 0.0);
            $row['lot_rows']  = $lotRowsMap[$k] ?? [];
            $row['lot_count'] = count($row['lot_rows']);
        }
        unset($row);
    }

    /**
     * Qty and value are independent controls: a lot can have the correct
     * remaining quantity while its recorded cost no longer matches stock.
     */
    private function apply_component_lot_value_verdict(array &$rows): void
    {
        $qtyEpsilon = 0.0001;
        $valueEpsilon = 1.00;

        foreach ($rows as &$row) {
            $balanceQty = round((float)($row['balance_qty'] ?? 0), 4);
            $dailyQty = round((float)($row['daily_qty'] ?? 0), 4);
            $movementQty = round((float)($row['movement_qty'] ?? 0), 4);
            $lotQty = round((float)($row['lot_qty'] ?? 0), 4);
            $qtyIsMatch = abs($balanceQty - $dailyQty) < $qtyEpsilon
                && abs($balanceQty - $movementQty) < $qtyEpsilon
                && abs($balanceQty - $lotQty) < $qtyEpsilon;

            $stockValue = round((float)($row['monthly_total_value'] ?? 0), 2);
            $lotValue = round((float)($row['lot_total_value'] ?? 0), 2);
            $valueGap = round($stockValue - $lotValue, 2);
            $hasValueMismatch = abs($valueGap) > $valueEpsilon;

            $row['qty_is_match'] = $qtyIsMatch ? 1 : 0;
            $row['monthly_lot_value_gap'] = $valueGap;
            $row['has_lot_value_mismatch'] = $hasValueMismatch ? 1 : 0;
            $row['is_match'] = $qtyIsMatch && !$hasValueMismatch;

            if (!$hasValueMismatch) {
                continue;
            }

            $valueReason = 'Jumlah lot FIFO sudah sama, tetapi nilai lot Rp '
                . number_format($lotValue, 2, ',', '.')
                . ' berbeda dari nilai stok Rp '
                . number_format($stockValue, 2, ',', '.')
                . '. Periksa HPP opening, batch, atau adjustment; sinkron qty lot tidak boleh menebak harga.';

            if ($qtyIsMatch) {
                $row['suspect_table'] = 'LOT_VALUE';
                $row['suspect_reason'] = $valueReason;
                continue;
            }

            $row['suspect_table'] = 'MULTIPLE';
            $row['suspect_reason'] = trim((string)($row['suspect_reason'] ?? '') . ' ' . $valueReason);
        }
        unset($row);
    }

    private function attach_component_daily_check(array &$rows): void
    {
        foreach ($rows as &$row) {
            $row['daily_check_status']      = 'UNKNOWN';
            $row['daily_check_drift']       = 0.0;
            $row['daily_check_drift_count'] = 0;
        }
        unset($row);
        if (!$this->db->table_exists('inv_component_monthly_stock') || empty($rows)) { return; }
        $componentIds = array_values(array_unique(array_filter(array_map(
            static function ($r) { return isset($r['component_id']) ? (int)$r['component_id'] : null; }, $rows
        ))));
        if (empty($componentIds)) { return; }
        $driftExpr = 'ROUND(ABS(s.closing_qty - ROUND('
            . 's.opening_qty + s.in_qty + COALESCE(s.adjustment_plus_qty,0)'
            . ' - s.out_qty - COALESCE(s.waste_qty,0) - COALESCE(s.spoil_qty,0)'
            . ' - COALESCE(s.adjustment_minus_qty,0)'
            . ', 4)), 4)';
        $latestMonthSub = $this->db
            ->select('s2.location_type, COALESCE(s2.division_id,0) AS division_id, s2.component_id, s2.uom_id, MAX(s2.month_key) AS max_month', false)
            ->from('inv_component_monthly_stock s2')
            ->where_in('s2.component_id', $componentIds)
            ->group_by(['s2.location_type', 's2.division_id', 's2.component_id', 's2.uom_id'])
            ->get_compiled_select();
        $results = $this->db
            ->select("s.location_type, COALESCE(s.division_id,0) AS division_id, s.component_id, s.uom_id, SUM({$driftExpr}) AS total_drift, COUNT(*) AS profile_count, SUM(CASE WHEN {$driftExpr} > 0.0001 THEN 1 ELSE 0 END) AS drift_count", false)
            ->from('inv_component_monthly_stock s')
            ->join('(' . $latestMonthSub . ') lm',
                's.location_type = lm.location_type AND COALESCE(s.division_id,0) = lm.division_id'
                . ' AND s.component_id = lm.component_id AND s.uom_id = lm.uom_id AND s.month_key = lm.max_month',
                'inner', false)
            ->where_in('s.component_id', $componentIds)
            ->group_by(['s.location_type', 's.division_id', 's.component_id', 's.uom_id'])
            ->get()->result_array();
        $checkMap = [];
        foreach ($results as $r) {
            $k = strtoupper((string)$r['location_type']) . '|' . (int)$r['division_id']
               . '|C-' . (int)$r['component_id'] . '|U-' . (int)$r['uom_id'];
            $checkMap[$k] = [
                'total_drift'   => round((float)($r['total_drift'] ?? 0), 4),
                'drift_count'   => (int)($r['drift_count'] ?? 0),
                'profile_count' => (int)($r['profile_count'] ?? 0),
            ];
        }
        foreach ($rows as &$row) {
            $locType = strtoupper((string)($row['location_type'] ?? ''));
            $divId   = $row['division_id'] !== null ? (int)$row['division_id'] : 0;
            $k = $locType . '|' . $divId
               . '|C-' . (int)($row['component_id'] ?? 0)
               . '|U-' . (int)($row['uom_id'] ?? 0);
            $check = $checkMap[$k] ?? null;
            if ($check === null) {
                $row['daily_check_status']      = 'UNKNOWN';
                $row['daily_check_drift']       = 0.0;
                $row['daily_check_drift_count'] = 0;
            } else {
                $row['daily_check_status']      = $check['drift_count'] > 0 ? 'DRIFT' : 'OK';
                $row['daily_check_drift']       = $check['total_drift'];
                $row['daily_check_drift_count'] = $check['drift_count'];
            }
        }
        unset($row);
    }

    public function component_reconcile_audit(string $asOfDate, array $filters): array
    {
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $componentId = (int)($filters['component_id'] ?? 0);
        $uomId = (int)($filters['uom_id'] ?? 0);
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
            return ['ok' => false, 'message' => 'Audit component membutuhkan location_type, component_id, dan uom_id.'];
        }

        $compare = $this->component_reconcile_rows([
            'as_of_date' => $asOfDate,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
        ], 200);

        $summaryRow = null;
        foreach ((array)($compare['rows'] ?? []) as $row) {
            if (strtoupper((string)($row['location_type'] ?? '')) !== $locationType) {
                continue;
            }
            if ((int)($row['component_id'] ?? 0) !== $componentId || (int)($row['uom_id'] ?? 0) !== $uomId) {
                continue;
            }
            if ($divisionId > 0 && (int)($row['division_id'] ?? 0) !== $divisionId) {
                continue;
            }
            $summaryRow = $row;
            break;
        }

        if ($summaryRow === null) {
            return ['ok' => false, 'message' => 'Data reconcile component tidak ditemukan.'];
        }

        $movements = $this->list_component_reconcile_movements($compare['as_of_date'] ?? $asOfDate, [
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
        ]);

        $seed = [
            'OPENING' => 'Opening',
            'PRODUCTION' => 'Production',
            'TRANSFER' => 'Transfer',
            'VOID' => 'Void',
            'REFUND' => 'Refund',
            'ADJUSTMENT' => 'Adjustment',
            'POS' => 'POS',
            'OTHER' => 'Lainnya',
        ];
        $buckets = [];
        foreach ($seed as $code => $label) {
            $buckets[$code] = [
                'bucket_code' => $code,
                'bucket_label' => $label,
                'count' => 0,
                'delta_qty' => 0.0,
                'mutation_value' => 0.0,
                'last_movement_date' => '',
                'last_movement_no' => '',
            ];
        }
        foreach ($movements as $movement) {
            $bucket = $this->classify_component_reconcile_bucket($movement);
            $code = (string)($bucket['code'] ?? 'OTHER');
            if (!isset($buckets[$code])) {
                $buckets[$code] = [
                    'bucket_code' => $code,
                    'bucket_label' => (string)($bucket['label'] ?? $code),
                    'count' => 0,
                    'delta_qty' => 0.0,
                    'mutation_value' => 0.0,
                    'last_movement_date' => '',
                    'last_movement_no' => '',
                ];
            }
            $buckets[$code]['count']++;
            $buckets[$code]['delta_qty'] = round((float)$buckets[$code]['delta_qty'] + (float)($movement['qty_delta'] ?? 0), 4);
            $buckets[$code]['mutation_value'] = round((float)$buckets[$code]['mutation_value'] + (float)($movement['total_cost'] ?? 0), 2);
            $movementDate = (string)($movement['movement_date'] ?? '');
            if ($movementDate >= (string)$buckets[$code]['last_movement_date']) {
                $buckets[$code]['last_movement_date'] = $movementDate;
                $buckets[$code]['last_movement_no'] = (string)($movement['movement_no'] ?? '');
            }
        }

        return [
            'ok' => true,
            'summary' => $summaryRow,
            'buckets' => array_values($buckets),
            'diagnosis' => [
                'suspect_table' => (string)($summaryRow['suspect_table'] ?? 'MATCH'),
                'reason' => (string)($summaryRow['suspect_reason'] ?? ''),
                'daily_date' => (string)($summaryRow['daily_date'] ?? ''),
                'movement_date' => (string)($summaryRow['movement_date'] ?? ''),
            ],
            'movements' => array_reverse($movements),
        ];
    }

    private function build_component_reconcile_verdict(float $balanceQty, float $dailyQty, float $movementQty, array $meta = []): array
    {
        $eps = 0.0001;
        $balanceVsDaily = abs($balanceQty - $dailyQty);
        $balanceVsMovement = abs($balanceQty - $movementQty);
        $dailyVsMovement = abs($dailyQty - $movementQty);
        $dailyDate = (string)($meta['daily_date'] ?? '');
        $movementDate = (string)($meta['movement_date'] ?? '');

        if ($balanceVsDaily < $eps && $balanceVsMovement < $eps) {
            return ['suspect_table' => 'MATCH', 'reason' => 'Balance, proyeksi harian, dan movement component masih sinkron.'];
        }
        if ($dailyVsMovement < $eps && $balanceVsDaily >= $eps) {
            return ['suspect_table' => 'BALANCE', 'reason' => 'Balance component berbeda, sementara proyeksi harian masih sama dengan movement.'];
        }
        if ($balanceVsMovement < $eps && $dailyVsMovement >= $eps) {
            $reason = 'Proyeksi harian component berbeda, sementara balance masih sama dengan movement.';
            if ($dailyDate === '') {
                $reason = 'Proyeksi harian component belum punya closing sampai tanggal audit, sementara balance sudah sama dengan movement.';
            } elseif ($movementDate !== '' && $dailyDate !== '' && $dailyDate < $movementDate) {
                $reason = 'Proyeksi harian component tertinggal dari movement terakhir.';
            }
            return ['suspect_table' => 'DAILY', 'reason' => $reason];
        }
        if ($balanceVsDaily < $eps && $dailyVsMovement >= $eps) {
            return ['suspect_table' => 'MOVEMENT_OR_SOURCE', 'reason' => 'Balance dan proyeksi harian sama, tetapi movement component berbeda.'];
        }

        return ['suspect_table' => 'MULTIPLE', 'reason' => 'Balance, proyeksi harian, dan movement component tidak saling cocok.'];
    }

    public function repair_component_reconcile(array $filters): array
    {
        $identity = [
            'location_type' => strtoupper(trim((string)($filters['location_type'] ?? ''))),
            'division_id' => !empty($filters['division_id']) ? (int)$filters['division_id'] : null,
            'component_id' => (int)($filters['component_id'] ?? 0),
            'uom_id' => (int)($filters['uom_id'] ?? 0),
        ];

        return $this->rebuild_component_history_for_identity($identity);
    }

    public function repair_all_component_identities(array $filters = []): array
    {
        if (!$this->db->table_exists('inv_component_movement_log') || !$this->db->table_exists('inv_component_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel movement log atau monthly stock tidak ditemukan.'];
        }

        @set_time_limit(300);

        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId   = isset($filters['division_id']) && $filters['division_id'] !== null ? (int)$filters['division_id'] : null;

        // Ambil semua identity unik dari movement log (sumber pergerakan)
        $this->db->select('DISTINCT location_type, division_id, component_id, uom_id', false)
            ->from('inv_component_movement_log');
        if ($locationType !== '') {
            $this->apply_component_location_filter('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        $identities = $this->db->get()->result_array();

        if (empty($identities)) {
            return ['ok' => true, 'message' => 'Tidak ada identity component ditemukan.', 'repaired' => 0, 'failed' => 0];
        }

        $repaired = 0;
        $failed   = 0;
        $errors   = [];
        foreach ($identities as $row) {
            $result = $this->rebuild_component_history_for_identity([
                'location_type' => (string)($row['location_type'] ?? ''),
                'division_id'   => $row['division_id'] !== null ? (int)$row['division_id'] : null,
                'component_id'  => (int)($row['component_id'] ?? 0),
                'uom_id'        => (int)($row['uom_id'] ?? 0),
            ]);
            if ($result['ok'] ?? false) {
                $repaired++;
            } else {
                $failed++;
                $errors[] = ($row['component_id'] ?? '?') . ': ' . ($result['message'] ?? 'gagal');
            }
        }

        return [
            'ok'       => $failed === 0,
            'message'  => "Repair selesai: {$repaired} berhasil" . ($failed > 0 ? ", {$failed} gagal." : '.'),
            'repaired' => $repaired,
            'failed'   => $failed,
            'errors'   => array_slice($errors, 0, 20),
        ];
    }

    public function repair_component_monthly_stock_drift(array $params): array
    {
        if (!$this->db->table_exists('inv_component_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel inv_component_monthly_stock tidak ditemukan.'];
        }
        $locationType = strtoupper(trim((string)($params['location_type'] ?? '')));
        $divisionId = isset($params['division_id']) && $params['division_id'] !== null ? (int)$params['division_id'] : null;
        $componentId = (int)($params['component_id'] ?? 0);
        $uomId = (int)($params['uom_id'] ?? 0);
        if ($componentId <= 0 || $uomId <= 0 || $locationType === '') {
            return ['ok' => false, 'message' => 'location_type, component_id, dan uom_id diperlukan.'];
        }

        $monthKeySubQ = $this->db
            ->select('MAX(s2.month_key)', false)
            ->from('inv_component_monthly_stock s2')
            ->where('s2.location_type', $locationType)
            ->where('COALESCE(s2.division_id,0)', $divisionId !== null ? $divisionId : 0)
            ->where('s2.component_id', $componentId)
            ->where('s2.uom_id', $uomId)
            ->get_compiled_select();

        $driftExpr = 'ROUND(ABS(s.closing_qty - ROUND('
            . 's.opening_qty + s.in_qty + COALESCE(s.adjustment_plus_qty,0)'
            . ' - s.out_qty - COALESCE(s.waste_qty,0) - COALESCE(s.spoil_qty,0)'
            . ' - COALESCE(s.adjustment_minus_qty,0)'
            . ', 4)), 4)';

        $query = $this->db
            ->select('s.id, s.closing_qty, s.opening_qty, s.in_qty, s.out_qty, s.waste_qty, s.spoil_qty, s.adjustment_plus_qty, s.adjustment_minus_qty', false)
            ->from('inv_component_monthly_stock s')
            ->where('s.location_type', $locationType)
            ->where('COALESCE(s.division_id,0)', $divisionId !== null ? $divisionId : 0)
            ->where('s.component_id', $componentId)
            ->where('s.uom_id', $uomId)
            ->where("s.month_key = ({$monthKeySubQ})", null, false)
            ->where("{$driftExpr} > 0.0001", null, false);

        $rows = $query->get()->result_array();

        if (empty($rows)) {
            return ['ok' => true, 'message' => 'Tidak ada drift pada monthly stock component ini.', 'data' => ['rows_fixed' => 0]];
        }

        $fixed = 0;
        foreach ($rows as $r) {
            $calculated = round(
                (float)$r['opening_qty']
                + (float)$r['in_qty']
                + (float)($r['adjustment_plus_qty'] ?? 0)
                - (float)$r['out_qty']
                - (float)($r['waste_qty'] ?? 0)
                - (float)($r['spoil_qty'] ?? 0)
                - (float)($r['adjustment_minus_qty'] ?? 0),
            4);
            $drift = round((float)$r['closing_qty'] - $calculated, 4);
            if (abs($drift) <= 0.0001) {
                continue;
            }
            $update = [];
            if ($drift > 0) {
                $update['adjustment_plus_qty'] = round((float)($r['adjustment_plus_qty'] ?? 0) + $drift, 4);
            } else {
                $update['adjustment_minus_qty'] = round((float)($r['adjustment_minus_qty'] ?? 0) + abs($drift), 4);
            }
            $this->db->where('id', (int)$r['id'])->update('inv_component_monthly_stock', $update);
            $fixed++;
        }

        return [
            'ok' => true,
            'message' => 'Drift monthly stock component diserap ke adjustment.',
            'data' => ['rows_fixed' => $fixed],
        ];
    }

    private function list_component_reconcile_movements(string $asOfDate, array $filters): array
    {
        if (!$this->db->table_exists('inv_component_movement_log')) {
            return [];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentId = (int)($filters['component_id'] ?? 0);
        $uomId = (int)($filters['uom_id'] ?? 0);
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $rows = $this->db->select('m.id, m.movement_no, m.movement_date, m.movement_datetime, m.location_type, m.division_id, ' . $divisionNameSelect . ', m.component_id, c.component_code, c.component_name, c.component_type, m.uom_id, u.code AS uom_code, m.movement_type, m.qty_in, m.qty_out, m.unit_cost, m.total_cost, m.source_module, m.source_table, m.source_id, m.source_line_id, m.notes', false)
            ->from('inv_component_movement_log m')
            ->join('mst_component c', 'c.id = m.component_id', 'inner')
            ->join('mst_operational_division d', 'd.id = m.division_id', 'left')
            ->join('mst_uom u', 'u.id = m.uom_id', 'left')
            ->where('m.movement_date <=', $asOfDate)
            ->where('m.location_type', $locationType)
            ->where('m.component_id', $componentId)
            ->where('m.uom_id', $uomId)
            ->order_by('m.movement_date', 'ASC')
            ->order_by('m.movement_datetime', 'ASC')
            ->order_by('m.id', 'ASC')
            ->get()
            ->result_array();

        if ($divisionId > 0) {
            $rows = array_values(array_filter($rows, static function (array $row) use ($divisionId): bool {
                return (int)($row['division_id'] ?? 0) === $divisionId;
            }));
        }

        $runningQty = 0.0;
        foreach ($rows as &$row) {
            $qtyIn = round((float)($row['qty_in'] ?? 0), 4);
            $qtyOut = round((float)($row['qty_out'] ?? 0), 4);
            $row['qty_before'] = round($runningQty, 4);
            $row['qty_delta'] = round($qtyIn - $qtyOut, 4);
            $row['qty_after'] = round($runningQty + $row['qty_delta'], 4);
            $row['movement_type_label'] = $this->component_movement_type_label((string)($row['movement_type'] ?? ''));
            $bucket = $this->classify_component_reconcile_bucket($row);
            $row['source_bucket'] = (string)($bucket['code'] ?? 'OTHER');
            $row['source_bucket_label'] = (string)($bucket['label'] ?? 'Lainnya');
            $row['source_label'] = $this->format_component_reconcile_source_label($row);
            $runningQty = (float)$row['qty_after'];
        }
        unset($row);

        return $rows;
    }

    private function classify_component_reconcile_bucket(array $row): array
    {
        $movementType = strtoupper(trim((string)($row['movement_type'] ?? '')));
        $sourceModule = strtoupper(trim((string)($row['source_module'] ?? '')));
        $sourceTable = strtolower(trim((string)($row['source_table'] ?? '')));
        $notes = strtolower(trim((string)($row['notes'] ?? '')));

        if ($movementType === 'OPENING' || strpos($sourceTable, 'opening') !== false) {
            return ['code' => 'OPENING', 'label' => 'Opening'];
        }
        if ($sourceModule === 'POS' || $sourceTable === 'pos_stock_commit') {
            return ['code' => 'POS', 'label' => 'POS'];
        }
        if (strpos($sourceTable, 'refund') !== false || strpos($notes, 'refund') !== false) {
            return ['code' => 'REFUND', 'label' => 'Refund'];
        }
        if ($movementType === 'PRODUCTION_IN' || $movementType === 'PRODUCTION_OUT' || strpos($sourceTable, 'component_batch') !== false) {
            return ['code' => 'PRODUCTION', 'label' => 'Production'];
        }
        if ($movementType === 'TRANSFER_IN' || $movementType === 'TRANSFER_OUT') {
            return ['code' => 'TRANSFER', 'label' => 'Transfer'];
        }
        if (in_array($movementType, ['VOID_REVERSE', 'VOID_OUT'], true) || strpos($notes, 'void') !== false) {
            return ['code' => 'VOID', 'label' => 'Void'];
        }
        if (in_array($movementType, ['ADJUSTMENT_PLUS', 'ADJUSTMENT_MINUS', 'WASTE', 'SPOIL'], true) || strpos($sourceTable, 'adjustment') !== false) {
            return ['code' => 'ADJUSTMENT', 'label' => 'Adjustment'];
        }
        return ['code' => 'OTHER', 'label' => 'Lainnya'];
    }

    private function format_component_reconcile_source_label(array $row): string
    {
        $sourceTable = strtolower(trim((string)($row['source_table'] ?? '')));
        $sourceId = (int)($row['source_id'] ?? 0);
        $map = [
            'inv_component_opening' => 'Opening Component',
            'inv_component_batch' => 'Batch Produksi',
            'inv_component_adjustment' => 'Adjustment Component',
            'pos_stock_commit' => 'POS Commit',
        ];
        $label = $map[$sourceTable] ?? ($sourceTable !== '' ? strtoupper(str_replace('_', ' ', $sourceTable)) : '-');
        if ($sourceId > 0) {
            $label .= ' #' . $sourceId;
        }
        return $label;
    }

    public function rebuild_component_history_for_identity(array $identity): array
    {
        if (!$this->db->table_exists('inv_component_movement_log') || !$this->db->table_exists('inv_component_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel movement/monthly stock component belum lengkap.'];
        }

        $locationType = strtoupper(trim((string)($identity['location_type'] ?? '')));
        $divisionId = array_key_exists('division_id', $identity) && $identity['division_id'] !== null && $identity['division_id'] !== ''
            ? (int)$identity['division_id']
            : null;
        $componentId = (int)($identity['component_id'] ?? 0);
        $uomId = (int)($identity['uom_id'] ?? 0);
        if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
            return ['ok' => false, 'message' => 'Identity component tidak valid.'];
        }

        // Automatic rebuilds are not permission to repair closed history.
        // Retain the month opening and row identity used by revaluation audit.
        $activeMonth = date('Y-m-01');
        $this->db->trans_begin();
        $period = $this->assert_component_period_open(date('Y-m-d'), 'rebuild component');
        if (!($period['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $period;
        }
        $seed = $this->db->query(
            'SELECT * FROM inv_component_monthly_stock WHERE month_key = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [$activeMonth, $locationType, $divisionId, $componentId, $uomId]
        )->row_array() ?: [];
        if (empty($seed)) {
            $prior = $this->db->from('inv_component_monthly_stock')->where('month_key <', $activeMonth)
                ->where('location_type', $locationType)->where('division_id', $divisionId)
                ->where('component_id', $componentId)->where('uom_id', $uomId)
                ->order_by('month_key', 'DESC')->limit(1)->get()->row_array() ?: [];
            $seed = ['opening_qty' => (float)($prior['closing_qty'] ?? 0), 'opening_total_value' => (float)($prior['total_value'] ?? 0)];
        }
        $hasCurrentSeed = !empty($seed['id']);
        $corrections = $this->component_value_corrections($activeMonth, date('Y-m-d'));
        $hasReversalLink = $this->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log');
        $this->db->select($hasReversalLink ? 'm.*, origin.movement_type AS reversal_origin_type' : 'm.*, NULL AS reversal_origin_type', false)
            ->from('inv_component_movement_log m')
            ->where('m.movement_date >=', $activeMonth)
            ->where('m.movement_date <=', date('Y-m-d'))
            ->where('m.location_type', $locationType)
            ->where('m.component_id', $componentId)
            ->where('m.uom_id', $uomId);
        if ($hasReversalLink) {
            $this->db->join('inv_component_movement_log origin', 'origin.id = m.reversal_of_movement_id', 'left');
        }
        if ($divisionId !== null) {
            $this->db->where('m.division_id', $divisionId);
        } else {
            $this->db->where('m.division_id IS NULL', null, false);
        }
        $logs = $this->db
            ->order_by('m.movement_date', 'ASC')
            ->order_by('m.movement_datetime', 'ASC')
            ->order_by('m.id', 'ASC')
            ->get()
            ->result_array();

        $daily = [];
        $rebuildBatchNo = 'RECONCILE-' . date('YmdHis');
        $logs = array_values(array_filter($logs, static function ($log) use ($hasCurrentSeed) {
            return !($hasCurrentSeed && $log['movement_type'] === 'OPENING' && ($log['source_table'] ?? '') === 'inv_component_opening');
        }));
        $qtyBefore = round((float)$seed['opening_qty'], 4);
        $valueBefore = round((float)$seed['opening_total_value'], 2);
        $avgBefore = abs($qtyBefore) > 0.0001 ? round($valueBefore / $qtyBefore, 6) : 0.0;
        $lastMovementAt = null;
        foreach ($logs as $log) {
            $movementDate = (string)($log['movement_date'] ?? '');
            if ($movementDate === '') {
                continue;
            }
            if (!isset($daily[$movementDate])) {
                $daily[$movementDate] = [
                    'month_key' => substr($movementDate, 0, 7) . '-01',
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'opening_qty' => round($qtyBefore, 4),
                    'opening_total_value' => round($valueBefore, 2),
                    'in_qty' => 0.0,
                    'in_total_value' => 0.0,
                    'out_qty' => 0.0,
                    'out_total_value' => 0.0,
                    'waste_qty' => 0.0,
                    'waste_total_value' => 0.0,
                    'spoil_qty' => 0.0,
                    'spoil_total_value' => 0.0,
                    'adjustment_qty' => 0.0,
                    'adjustment_plus_qty' => 0.0,
                    'adjustment_minus_qty' => 0.0,
                    'adjustment_plus_total_value' => 0.0,
                    'adjustment_minus_total_value' => 0.0,
                    'closing_qty' => round($qtyBefore, 4),
                    'avg_cost' => round($avgBefore, 6),
                    'total_value' => round($valueBefore, 2),
                    'mutation_count' => 0,
                    'last_movement_at' => null,
                    'rebuild_batch_no' => $rebuildBatchNo,
                ];
            }

            $qtyIn = round((float)($log['qty_in'] ?? 0), 4);
            $qtyOut = round((float)($log['qty_out'] ?? 0), 4);
            $unitCost = round((float)($log['unit_cost'] ?? 0), 6);
            // Daily closing average can be zero after stock is fully consumed.
            // Keep each movement's recorded value for monthly cost reporting.
            $movementValue = round(abs((float)($log['total_cost'] ?? 0)), 2);
            $movementType = strtoupper(trim((string)($log['movement_type'] ?? '')));
            $movementAt = (string)($log['movement_datetime'] ?? ($movementDate . ' 00:00:00'));

            if ($movementType === 'OPENING') {
                $daily[$movementDate]['opening_qty'] = round((float)$daily[$movementDate]['opening_qty'] + $qtyIn - $qtyOut, 4);
                $daily[$movementDate]['opening_total_value'] = round((float)$daily[$movementDate]['opening_total_value'] + $movementValue, 2);
            } elseif (in_array($movementType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                $daily[$movementDate]['in_qty'] = round((float)$daily[$movementDate]['in_qty'] + $qtyIn, 4);
                $daily[$movementDate]['in_total_value'] = round((float)$daily[$movementDate]['in_total_value'] + $movementValue, 2);
            } elseif (in_array($movementType, ['PRODUCTION_OUT', 'TRANSFER_OUT', 'USAGE'], true)) {
                $daily[$movementDate]['out_qty'] = round((float)$daily[$movementDate]['out_qty'] + $qtyOut, 4);
                $daily[$movementDate]['out_total_value'] = round((float)$daily[$movementDate]['out_total_value'] + $movementValue, 2);
            } elseif ($movementType === 'WASTE') {
                $daily[$movementDate]['waste_qty'] = round((float)$daily[$movementDate]['waste_qty'] + $qtyOut, 4);
                $daily[$movementDate]['waste_total_value'] = round((float)$daily[$movementDate]['waste_total_value'] + $movementValue, 2);
            } elseif ($movementType === 'SPOIL') {
                $daily[$movementDate]['spoil_qty'] = round((float)$daily[$movementDate]['spoil_qty'] + $qtyOut, 4);
                $daily[$movementDate]['spoil_total_value'] = round((float)$daily[$movementDate]['spoil_total_value'] + $movementValue, 2);
            } elseif ($movementType === 'ADJUSTMENT_PLUS') {
                $daily[$movementDate]['adjustment_qty'] = round((float)$daily[$movementDate]['adjustment_qty'] + $qtyIn, 4);
                $daily[$movementDate]['adjustment_plus_qty'] += $qtyIn;
                $daily[$movementDate]['adjustment_plus_total_value'] = round((float)$daily[$movementDate]['adjustment_plus_total_value'] + $movementValue, 2);
            } elseif ($movementType === 'ADJUSTMENT_MINUS') {
                $daily[$movementDate]['adjustment_qty'] = round((float)$daily[$movementDate]['adjustment_qty'] - $qtyOut, 4);
                $daily[$movementDate]['adjustment_minus_qty'] += $qtyOut;
                $daily[$movementDate]['adjustment_minus_total_value'] = round((float)$daily[$movementDate]['adjustment_minus_total_value'] + $movementValue, 2);
            } elseif ($movementType === 'VOID_REVERSE') {
                $originType = strtoupper(trim((string)($log['reversal_origin_type'] ?? '')));
                if (in_array($originType, ['PRODUCTION_OUT', 'TRANSFER_OUT', 'USAGE'], true)) {
                    $daily[$movementDate]['out_qty'] = round((float)$daily[$movementDate]['out_qty'] - $qtyIn, 4);
                    $daily[$movementDate]['out_total_value'] = round((float)$daily[$movementDate]['out_total_value'] - $movementValue, 2);
                } elseif ($originType === 'WASTE') {
                    $daily[$movementDate]['waste_qty'] = round((float)$daily[$movementDate]['waste_qty'] - $qtyIn, 4);
                    $daily[$movementDate]['waste_total_value'] = round((float)$daily[$movementDate]['waste_total_value'] - $movementValue, 2);
                } elseif ($originType === 'SPOIL') {
                    $daily[$movementDate]['spoil_qty'] = round((float)$daily[$movementDate]['spoil_qty'] - $qtyIn, 4);
                    $daily[$movementDate]['spoil_total_value'] = round((float)$daily[$movementDate]['spoil_total_value'] - $movementValue, 2);
                } elseif ($originType === 'ADJUSTMENT_MINUS') {
                    $daily[$movementDate]['adjustment_qty'] = round((float)$daily[$movementDate]['adjustment_qty'] + $qtyIn, 4);
                    $daily[$movementDate]['adjustment_minus_qty'] -= $qtyIn;
                    $daily[$movementDate]['adjustment_minus_total_value'] = round((float)$daily[$movementDate]['adjustment_minus_total_value'] - $movementValue, 2);
                } elseif (in_array($originType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                    $daily[$movementDate]['in_qty'] = round((float)$daily[$movementDate]['in_qty'] - $qtyIn, 4);
                    $daily[$movementDate]['in_total_value'] = round((float)$daily[$movementDate]['in_total_value'] - $movementValue, 2);
                } else {
                    $daily[$movementDate]['adjustment_qty'] = round((float)$daily[$movementDate]['adjustment_qty'] + $qtyIn, 4);
                    $daily[$movementDate]['adjustment_plus_qty'] += $qtyIn;
                    $daily[$movementDate]['adjustment_plus_total_value'] = round((float)$daily[$movementDate]['adjustment_plus_total_value'] + $movementValue, 2);
                }
            } elseif ($movementType === 'VOID_OUT') {
                $originType = strtoupper(trim((string)($log['reversal_origin_type'] ?? '')));
                if (in_array($originType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                    $daily[$movementDate]['in_qty'] = round((float)$daily[$movementDate]['in_qty'] - $qtyOut, 4);
                    $daily[$movementDate]['in_total_value'] = round((float)$daily[$movementDate]['in_total_value'] - $movementValue, 2);
                } elseif ($originType === 'ADJUSTMENT_PLUS') {
                    $daily[$movementDate]['adjustment_qty'] = round((float)$daily[$movementDate]['adjustment_qty'] - $qtyOut, 4);
                    $daily[$movementDate]['adjustment_plus_qty'] -= $qtyOut;
                    $daily[$movementDate]['adjustment_plus_total_value'] = round((float)$daily[$movementDate]['adjustment_plus_total_value'] - $movementValue, 2);
                } else {
                    $daily[$movementDate]['adjustment_qty'] = round((float)$daily[$movementDate]['adjustment_qty'] - $qtyOut, 4);
                    $daily[$movementDate]['adjustment_minus_qty'] += $qtyOut;
                    $daily[$movementDate]['adjustment_minus_total_value'] = round((float)$daily[$movementDate]['adjustment_minus_total_value'] + $movementValue, 2);
                }
            }

            $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
            if ($qtyIn > 0) {
                $valueAfter = round($valueBefore + $movementValue, 2);
            } elseif ($qtyOut > 0) {
                $valueAfter = round($valueBefore - $movementValue, 2);
            } else {
                $valueAfter = $valueBefore;
            }
            if (abs($qtyAfter) < 0.0001) {
                $qtyAfter = 0.0;
            }
            if (abs($valueAfter) < 0.01) {
                $valueAfter = 0.0;
            }
            $avgAfter = abs($qtyAfter) > 0.0001 ? round($valueAfter / $qtyAfter, 6) : 0.0;

            $daily[$movementDate]['closing_qty'] = $qtyAfter;
            $daily[$movementDate]['avg_cost'] = $avgAfter;
            $daily[$movementDate]['total_value'] = round($valueAfter, 2);
            $daily[$movementDate]['mutation_count'] = (int)$daily[$movementDate]['mutation_count'] + 1;
            $daily[$movementDate]['last_movement_at'] = $movementAt;

            $qtyBefore = $qtyAfter;
            $avgBefore = $avgAfter;
            $valueBefore = $valueAfter;
            $lastMovementAt = $movementAt;
        }

        $projection = $this->component_value_projection($identity, $logs, $corrections,
            (float)$seed['opening_qty'], (float)$seed['opening_total_value'], date('Y-m-d'));
        if (!$projection['ok']) {
            $this->db->trans_rollback();
            return $projection;
        }
        $qtyBefore = $projection['qty'];
        $valueBefore = $projection['value'];
        $avgBefore = abs($qtyBefore) > 0.0001 ? round($valueBefore / $qtyBefore, 6) : 0.0;

        $monthlyRows = [];
        foreach (array_values($daily) as $row) {
            $monthKey = (string)($row['month_key'] ?? '');
            if ($monthKey === '') {
                continue;
            }
            if (!isset($monthlyRows[$monthKey])) {
                $monthlyRows[$monthKey] = [
                    'month_key' => $monthKey,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'opening_qty' => round((float)($row['opening_qty'] ?? 0), 4),
                    'opening_total_value' => round((float)($row['opening_total_value'] ?? 0), 2),
                    'in_qty' => 0.0,
                    'in_total_value' => 0.0,
                    'out_qty' => 0.0,
                    'out_total_value' => 0.0,
                    'waste_qty' => 0.0,
                    'waste_total_value' => 0.0,
                    'spoil_qty' => 0.0,
                    'spoil_total_value' => 0.0,
                    'adjustment_plus_qty' => 0.0,
                    'adjustment_plus_total_value' => 0.0,
                    'adjustment_minus_qty' => 0.0,
                    'adjustment_minus_total_value' => 0.0,
                    'closing_qty' => round((float)($row['closing_qty'] ?? 0), 4),
                    'avg_cost' => round((float)($row['avg_cost'] ?? 0), 6),
                    'total_value' => round((float)($row['total_value'] ?? 0), 2),
                    'movement_day_count' => 0,
                    'mutation_count' => 0,
                    'last_movement_date' => null,
                    'last_movement_at' => null,
                    'last_movement_table' => 'inv_component_movement_log',
                    'last_movement_id' => null,
                    'source_mode' => 'REBUILD',
                    'notes' => 'Rebuilt from component movement log',
                ];
            }

            $adjustmentQty = round((float)($row['adjustment_qty'] ?? 0), 4);

            $monthlyRows[$monthKey]['in_qty'] = round((float)$monthlyRows[$monthKey]['in_qty'] + (float)($row['in_qty'] ?? 0), 4);
            $monthlyRows[$monthKey]['in_total_value'] = round((float)$monthlyRows[$monthKey]['in_total_value'] + (float)($row['in_total_value'] ?? 0), 2);
            $monthlyRows[$monthKey]['out_qty'] = round((float)$monthlyRows[$monthKey]['out_qty'] + (float)($row['out_qty'] ?? 0), 4);
            $monthlyRows[$monthKey]['out_total_value'] = round((float)$monthlyRows[$monthKey]['out_total_value'] + (float)($row['out_total_value'] ?? 0), 2);
            $monthlyRows[$monthKey]['waste_qty'] = round((float)$monthlyRows[$monthKey]['waste_qty'] + (float)($row['waste_qty'] ?? 0), 4);
            $monthlyRows[$monthKey]['waste_total_value'] = round((float)$monthlyRows[$monthKey]['waste_total_value'] + (float)($row['waste_total_value'] ?? 0), 2);
            $monthlyRows[$monthKey]['spoil_qty'] = round((float)$monthlyRows[$monthKey]['spoil_qty'] + (float)($row['spoil_qty'] ?? 0), 4);
            $monthlyRows[$monthKey]['spoil_total_value'] = round((float)$monthlyRows[$monthKey]['spoil_total_value'] + (float)($row['spoil_total_value'] ?? 0), 2);
            foreach (['plus', 'minus'] as $direction) {
                $qtyField = 'adjustment_' . $direction . '_qty';
                $valueField = 'adjustment_' . $direction . '_total_value';
                $monthlyRows[$monthKey][$qtyField] = round($monthlyRows[$monthKey][$qtyField] + (float)($row[$qtyField] ?? 0), 4);
                $monthlyRows[$monthKey][$valueField] = round($monthlyRows[$monthKey][$valueField] + (float)($row[$valueField] ?? 0), 2);
            }
            $monthlyRows[$monthKey]['closing_qty'] = round((float)($row['closing_qty'] ?? 0), 4);
            $monthlyRows[$monthKey]['avg_cost'] = round((float)($row['avg_cost'] ?? 0), 6);
            $monthlyRows[$monthKey]['total_value'] = round((float)($row['total_value'] ?? 0), 2);
            $monthlyRows[$monthKey]['movement_day_count'] = (int)$monthlyRows[$monthKey]['movement_day_count'] + 1;
            $monthlyRows[$monthKey]['mutation_count'] = (int)$monthlyRows[$monthKey]['mutation_count'] + (int)($row['mutation_count'] ?? 0);
            $monthlyRows[$monthKey]['last_movement_date'] = (string)($row['movement_date'] ?? $monthKey);
            $monthlyRows[$monthKey]['last_movement_at'] = $row['last_movement_at'] ?? null;
        }

        foreach (array_values($monthlyRows) as $monthlyRow) {
            $monthlyRow['closing_qty'] = $qtyBefore;
            $monthlyRow['total_value'] = $valueBefore;
            $monthlyRow['avg_cost'] = $avgBefore;
            $this->upsert_by_unique('inv_component_monthly_stock', $monthlyRow, ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal rebuild histori component.'];
        }

        $this->db->trans_commit();
        return [
            'ok' => true,
            'message' => 'Histori component bulan aktif dihitung ulang; bulan lama dan bukti koreksi dipertahankan.',
            'data' => [
                'days_rebuilt' => count($daily),
                'months_rebuilt' => count($monthlyRows),
                'final_qty' => round($qtyBefore, 4),
                'final_avg_cost' => round($avgBefore, 6),
                'final_total_value' => round($valueBefore, 2),
            ],
        ];
    }

    private function attach_component_lot_summaries(array $rows): array
    {
        if (empty($rows) || !$this->db->table_exists('inv_component_lot')) {
            return $this->fill_default_component_lot_summaries($rows);
        }

        $componentIds = [];
        $uomIds = [];
        $locationTypes = [];
        $divisionIds = [];
        $hasNullDivision = false;
        foreach ($rows as $row) {
            $componentId = (int)($row['component_id'] ?? 0);
            $uomId = (int)($row['uom_id'] ?? 0);
            $locationType = strtoupper(trim((string)($row['location_type'] ?? '')));
            $divisionId = array_key_exists('division_id', $row) && $row['division_id'] !== null ? (int)$row['division_id'] : null;
            if ($componentId <= 0 || $uomId <= 0 || $locationType === '') {
                continue;
            }
            $componentIds[$componentId] = $componentId;
            $uomIds[$uomId] = $uomId;
            $locationTypes[$locationType] = $locationType;
            if ($divisionId === null) {
              $hasNullDivision = true;
            } else {
              $divisionIds[$divisionId] = $divisionId;
            }
        }

        if (empty($componentIds) || empty($uomIds) || empty($locationTypes)) {
            return $this->fill_default_component_lot_summaries($rows);
        }

        $query = $this->db->select('l.id, l.location_type, l.division_id, l.component_id, l.uom_id, l.lot_no, l.receipt_date, l.expiry_date, l.unit_cost, l.qty_in_total, l.qty_balance, l.status')
            ->from('inv_component_lot l')
            ->where('l.qty_balance >', 0)
            ->where_in('l.status', ['OPEN'])
            ->where_in('l.component_id', array_values($componentIds))
            ->where_in('l.uom_id', array_values($uomIds))
            ->where_in('l.location_type', array_values($locationTypes));

        if (!empty($divisionIds) || $hasNullDivision) {
            $query->group_start();
            if (!empty($divisionIds)) {
                $query->where_in('l.division_id', array_values($divisionIds));
            }
            if ($hasNullDivision) {
                if (!empty($divisionIds)) {
                    $query->or_where('l.division_id IS NULL', null, false);
                } else {
                    $query->where('l.division_id IS NULL', null, false);
                }
            }
            $query->group_end();
        }

        $lotRows = $query
            ->order_by('l.receipt_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        $summaryMap = [];
        foreach ($lotRows as $lotRow) {
            $key = $this->component_identity_key(
                (string)($lotRow['location_type'] ?? ''),
                array_key_exists('division_id', $lotRow) ? $lotRow['division_id'] : null,
                (int)($lotRow['component_id'] ?? 0),
                (int)($lotRow['uom_id'] ?? 0)
            );
            if (!isset($summaryMap[$key])) {
                $summaryMap[$key] = [
                    'lot_count' => 0,
                    'balance_qty' => 0.0,
                    'min_unit_cost' => null,
                    'max_unit_cost' => null,
                    'total_value' => 0.0,
                    'rows' => [],
                ];
            }
            $unitCost = round((float)($lotRow['unit_cost'] ?? 0), 6);
            $qtyBalance = round((float)($lotRow['qty_balance'] ?? 0), 4);
            $summaryMap[$key]['lot_count']++;
            $summaryMap[$key]['balance_qty'] += $qtyBalance;
            $summaryMap[$key]['total_value'] += round($qtyBalance * $unitCost, 2);
            $summaryMap[$key]['min_unit_cost'] = $summaryMap[$key]['min_unit_cost'] === null ? $unitCost : min($summaryMap[$key]['min_unit_cost'], $unitCost);
            $summaryMap[$key]['max_unit_cost'] = $summaryMap[$key]['max_unit_cost'] === null ? $unitCost : max($summaryMap[$key]['max_unit_cost'], $unitCost);
            $summaryMap[$key]['rows'][] = [
                'id' => (int)($lotRow['id'] ?? 0),
                'lot_no' => (string)($lotRow['lot_no'] ?? ''),
                'receipt_date' => (string)($lotRow['receipt_date'] ?? ''),
                'expiry_date' => (string)($lotRow['expiry_date'] ?? ''),
                'qty_in_total' => round((float)($lotRow['qty_in_total'] ?? 0), 4),
                'qty_balance' => $qtyBalance,
                'unit_cost' => $unitCost,
                'total_value' => round($qtyBalance * $unitCost, 2),
                'status' => (string)($lotRow['status'] ?? ''),
            ];
        }

        foreach ($rows as &$row) {
            $key = $this->component_identity_key(
                (string)($row['location_type'] ?? ''),
                array_key_exists('division_id', $row) ? $row['division_id'] : null,
                (int)($row['component_id'] ?? 0),
                (int)($row['uom_id'] ?? 0)
            );
            $summary = $summaryMap[$key] ?? null;
            if ($summary === null) {
                $row['lot_summary'] = $this->empty_component_lot_summary();
                continue;
            }
            $summary['balance_qty'] = round((float)$summary['balance_qty'], 4);
            $summary['total_value'] = round((float)$summary['total_value'], 2);
            $summary['min_unit_cost'] = $summary['min_unit_cost'] === null ? 0.0 : round((float)$summary['min_unit_cost'], 6);
            $summary['max_unit_cost'] = $summary['max_unit_cost'] === null ? 0.0 : round((float)$summary['max_unit_cost'], 6);
            $summary['has_mixed_cost'] = $summary['lot_count'] > 1 && abs((float)$summary['max_unit_cost'] - (float)$summary['min_unit_cost']) > 0.0001;
            $row['lot_summary'] = $summary;
        }
        unset($row);

        return $rows;
    }

    private function attach_component_daily_lot_rows(array $rows, array $dates, string $windowStart, string $windowEnd): array
    {
        if (empty($rows) || empty($dates)) {
            return $rows;
        }

        $dateKeys = [];
        $dateTemplate = [];
        foreach ($dates as $date) {
            $dateKey = (string)($date['date'] ?? '');
            if ($dateKey === '') {
                continue;
            }
            $dateKeys[] = $dateKey;
            $dateTemplate[$dateKey] = [
                'opening' => 0.0,
                'in' => 0.0,
                'out' => 0.0,
                'adj' => 0.0,
                'closing' => 0.0,
            ];
        }
        if (empty($dateKeys)) {
            return $rows;
        }

        $lotIds = [];
        foreach ($rows as $row) {
            $lotSummaryRows = array_values((array)((array)($row['lot_summary'] ?? []))['rows'] ?? []);
            foreach ($lotSummaryRows as $lotRow) {
                $lotId = (int)($lotRow['id'] ?? 0);
                if ($lotId > 0) {
                    $lotIds[$lotId] = $lotId;
                }
            }
        }
        if (empty($lotIds)) {
            return $rows;
        }

        $issuedBeforeWindow = [];
        $issuedInWindow = [];
        if ($this->db->table_exists('inv_component_lot_issue_log') && $this->db->table_exists('inv_component_lot_issue_line')) {
            $issueBeforeRows = $this->db->select('li.lot_id, SUM(li.qty_out) AS qty_out_total', false)
                ->from('inv_component_lot_issue_line li')
                ->join('inv_component_lot_issue_log il', 'il.id = li.issue_id', 'inner')
                ->where_in('li.lot_id', array_values($lotIds))
                ->where('il.issue_date <', $windowStart)
                ->where('il.status', 'POSTED')
                ->group_by('li.lot_id')
                ->get()
                ->result_array();
            foreach ($issueBeforeRows as $issueRow) {
                $issuedBeforeWindow[(int)($issueRow['lot_id'] ?? 0)] = round((float)($issueRow['qty_out_total'] ?? 0), 4);
            }

            $issueWindowRows = $this->db->select('li.lot_id, il.issue_date, SUM(li.qty_out) AS qty_out_total', false)
                ->from('inv_component_lot_issue_line li')
                ->join('inv_component_lot_issue_log il', 'il.id = li.issue_id', 'inner')
                ->where_in('li.lot_id', array_values($lotIds))
                ->where('il.issue_date >=', $windowStart)
                ->where('il.issue_date <=', $windowEnd)
                ->where('il.status', 'POSTED')
                ->group_by(['li.lot_id', 'il.issue_date'])
                ->get()
                ->result_array();
            foreach ($issueWindowRows as $issueRow) {
                $lotId = (int)($issueRow['lot_id'] ?? 0);
                $issueDate = (string)($issueRow['issue_date'] ?? '');
                if ($lotId <= 0 || $issueDate === '') {
                    continue;
                }
                if (!isset($issuedInWindow[$lotId])) {
                    $issuedInWindow[$lotId] = [];
                }
                $issuedInWindow[$lotId][$issueDate] = round((float)($issueRow['qty_out_total'] ?? 0), 4);
            }
        }

        foreach ($rows as &$row) {
            $lotSummary = is_array($row['lot_summary'] ?? null) ? $row['lot_summary'] : [];
            $lotSummaryRows = array_values((array)($lotSummary['rows'] ?? []));
            $lotDailyRows = [];
            foreach ($lotSummaryRows as $lotRow) {
                $lotId = (int)($lotRow['id'] ?? 0);
                $receiptDate = trim((string)($lotRow['receipt_date'] ?? ''));
                $qtyInTotal = round((float)($lotRow['qty_in_total'] ?? 0), 4);
                $unitCost = round((float)($lotRow['unit_cost'] ?? 0), 6);
                $days = $dateTemplate;
                $openingCarry = 0.0;
                if ($receiptDate !== '' && $receiptDate < $windowStart) {
                    $openingCarry = round($qtyInTotal - (float)($issuedBeforeWindow[$lotId] ?? 0), 4);
                    if ($openingCarry < 0) {
                        $openingCarry = 0.0;
                    }
                }

                $firstOpening = 0.0;
                $lastClosing = $openingCarry;
                $totalIn = 0.0;
                $totalOut = 0.0;
                foreach ($dateKeys as $index => $dateKey) {
                    $opening = $index === 0 ? $openingCarry : $lastClosing;
                    $inQty = $receiptDate === $dateKey ? $qtyInTotal : 0.0;
                    $outQty = round((float)($issuedInWindow[$lotId][$dateKey] ?? 0), 4);
                    $closing = round($opening + $inQty - $outQty, 4);
                    if ($closing < 0 && abs($closing) < 0.0001) {
                        $closing = 0.0;
                    }

                    $days[$dateKey] = [
                        'opening' => $opening,
                        'in' => $inQty,
                        'out' => $outQty,
                        'adj' => 0.0,
                        'closing' => $closing,
                    ];
                    if ($index === 0) {
                        $firstOpening = $opening;
                    }
                    $lastClosing = $closing;
                    $totalIn += $inQty;
                    $totalOut += $outQty;
                }

                $lotDailyRows[] = [
                    'id' => $lotId,
                    'lot_no' => (string)($lotRow['lot_no'] ?? ''),
                    'receipt_date' => $receiptDate,
                    'expiry_date' => (string)($lotRow['expiry_date'] ?? ''),
                    'qty_in_total' => $qtyInTotal,
                    'qty_balance' => round((float)($lotRow['qty_balance'] ?? 0), 4),
                    'unit_cost' => $unitCost,
                    'total_value' => round($lastClosing * $unitCost, 2),
                    'days' => $days,
                    'total_opening' => $firstOpening,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'total_adj' => 0.0,
                    'total_closing' => $lastClosing,
                ];
            }
            $row['lot_daily_rows'] = $lotDailyRows;
        }
        unset($row);

        return $rows;
    }

    private function fill_default_component_lot_summaries(array $rows): array
    {
        foreach ($rows as &$row) {
            if (!isset($row['lot_summary']) || !is_array($row['lot_summary'])) {
                $row['lot_summary'] = $this->empty_component_lot_summary();
            }
        }
        unset($row);

        return $rows;
    }

    private function empty_component_lot_summary(): array
    {
        return [
            'lot_count' => 0,
            'location_scope' => '',
            'location_count' => 0,
            'balance_qty' => 0.0,
            'min_unit_cost' => 0.0,
            'max_unit_cost' => 0.0,
            'total_value' => 0.0,
            'has_mixed_cost' => false,
            'rows' => [],
        ];
    }

    private function component_identity_key(string $locationType, $divisionId, int $componentId, int $uomId): string
    {
        $normalizedDivisionId = $divisionId === null || $divisionId === '' ? 'NULL' : (string)(int)$divisionId;
        return strtoupper(trim($locationType)) . '|' . $normalizedDivisionId . '|' . $componentId . '|' . $uomId;
    }

    private function fetch_component_monthly_projection_seed_rows(array $filters, string $targetMonth, bool $exactMonthOnly = false): array
    {
        if (!$this->db->table_exists('inv_component_monthly_stock')) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $componentIdFilter = !empty($filters['component_id']) ? (int)$filters['component_id'] : 0;
        $uomIdFilter = !empty($filters['uom_id']) ? (int)$filters['uom_id'] : 0;
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $latestMonthSubquery = '';
        if (!$exactMonthOnly) {
            $latestMonthSubquery = $this->db
                ->select('location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key', false)
                ->from('inv_component_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['location_type', 'division_id', 'component_id', 'uom_id'])
                ->get_compiled_select();
        }

        $this->db->select('s.id, s.month_key, s.location_type, s.division_id, ' . $divisionNameSelect . ', s.component_id, c.component_code, c.component_name, c.component_type, cat.name AS category_name, s.uom_id, u.code AS uom_code, u.name AS uom_name, s.opening_qty, s.opening_total_value, s.closing_qty, s.avg_cost, s.total_value, s.last_movement_at, s.source_mode, COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00")) AS updated_at', false)
            ->from('inv_component_monthly_stock s')
            ->join('mst_component c', 'c.id = s.component_id', 'inner')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->join('mst_uom u', 'u.id = s.uom_id', 'left');

        if ($exactMonthOnly) {
            $this->db->where('s.month_key', $targetMonth);
        } else {
            $this->db->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false);
        }

        $this->apply_component_location_filter('s.location_type', $locationType);
        if ($divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }
        if ($componentIdFilter > 0) {
            $this->db->where('s.component_id', $componentIdFilter);
        }
        if ($uomIdFilter > 0) {
            $this->db->where('s.uom_id', $uomIdFilter);
        }
        if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $componentType);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        $rows = $this->db->get()->result_array();
        $best = [];
        foreach ($rows as $row) {
            $key = $this->component_identity_key((string)($row['location_type'] ?? ''), $row['division_id'] ?? null, (int)($row['component_id'] ?? 0), (int)($row['uom_id'] ?? 0));
            if (!isset($best[$key])) {
                $best[$key] = $row;
                continue;
            }
            $current = $best[$key];
            $currentMode = strtoupper(trim((string)($current['source_mode'] ?? '')));
            $nextMode = strtoupper(trim((string)($row['source_mode'] ?? '')));
            if ($currentMode !== 'REBUILD' && $nextMode === 'REBUILD') {
                $best[$key] = $row;
                continue;
            }
            if ($currentMode === 'REBUILD' && $nextMode !== 'REBUILD') {
                continue;
            }
            $currentUpdated = (string)($current['updated_at'] ?? '');
            $nextUpdated = (string)($row['updated_at'] ?? '');
            if ($nextUpdated > $currentUpdated || ($nextUpdated === $currentUpdated && (int)($row['id'] ?? 0) > (int)($current['id'] ?? 0))) {
                $best[$key] = $row;
            }
        }

        return array_values($best);
    }

    private function fetch_component_monthly_generate_source_rows(array $filters, string $monthStart): array
    {
        if (!$this->db->table_exists('inv_component_monthly_stock')) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        $componentIdFilter = !empty($filters['component_id']) ? (int)$filters['component_id'] : 0;
        $uomIdFilter = !empty($filters['uom_id']) ? (int)$filters['uom_id'] : 0;
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db
            ->select('s.month_key, s.location_type, s.division_id, ' . $divisionNameSelect . ', s.component_id, c.component_code, c.component_name, c.component_type, cat.name AS category_name, s.uom_id, u.code AS uom_code', false)
            ->select('s.opening_qty, s.opening_total_value, s.in_qty, s.in_total_value, s.out_qty, s.out_total_value, s.waste_qty, s.waste_total_value, s.spoil_qty, s.spoil_total_value, s.adjustment_plus_qty, s.adjustment_plus_total_value, s.adjustment_minus_qty, s.adjustment_minus_total_value, s.closing_qty, s.avg_cost, s.total_value, s.movement_day_count, s.mutation_count, s.source_mode', false)
            ->from('inv_component_monthly_stock s')
            ->join('mst_component c', 'c.id = s.component_id', 'inner')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->join('mst_uom u', 'u.id = s.uom_id', 'left')
            ->where('s.month_key', $monthStart);

        $this->apply_component_location_filter('s.location_type', $locationType);
        if ($divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }
        if ($componentIdFilter > 0) {
            $this->db->where('s.component_id', $componentIdFilter);
        }
        if ($uomIdFilter > 0) {
            $this->db->where('s.uom_id', $uomIdFilter);
        }
        if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $componentType);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        return $this->db
            ->order_by('s.location_type', 'ASC')
            ->order_by('s.division_id', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function fetch_component_daily_projection_rows(array $filters, string $startDate, string $endDate): ?array
    {
        $hasMonthlyProjection = $this->db->table_exists('inv_component_monthly_stock');
        $hasMovementLog = $this->db->table_exists('inv_component_movement_log');
        if (!$hasMonthlyProjection && !$hasMovementLog) {
            return null;
        }

        $monthKey = date('Y-m-01', strtotime($startDate));
        $seedRows = $this->fetch_component_monthly_projection_seed_rows($filters, $monthKey, true);
        if (empty($seedRows)) {
            return [];
        }
        $stateMap = [];
        $metaMap = [];
        $seedMonthMap = [];
        $dailyRows = [];
        $valuationSeeds = [];
        $valuationLogs = [];

        foreach ($seedRows as $seedRow) {
            $key = $this->component_identity_key((string)($seedRow['location_type'] ?? ''), $seedRow['division_id'] ?? null, (int)($seedRow['component_id'] ?? 0), (int)($seedRow['uom_id'] ?? 0));
            $isCurrentMonthSeed = (string)($seedRow['month_key'] ?? '') === $monthKey;
            $seedQty = $isCurrentMonthSeed ? (float)($seedRow['opening_qty'] ?? 0) : (float)($seedRow['closing_qty'] ?? 0);
            $seedValue = $isCurrentMonthSeed ? (float)($seedRow['opening_total_value'] ?? 0) : (float)($seedRow['total_value'] ?? 0);

            $seedQty = round($seedQty, 4);
            $seedValue = round($seedValue, 2);
            $stateMap[$key] = [
                'qty' => $seedQty,
                'value' => $seedValue,
                'avg' => abs($seedQty) > 0.0001 ? round($seedValue / $seedQty, 6) : round((float)($seedRow['avg_cost'] ?? 0), 6),
            ];
            $metaMap[$key] = [
                'location_type' => (string)($seedRow['location_type'] ?? ''),
                'division_id' => $seedRow['division_id'] !== null ? (int)$seedRow['division_id'] : null,
                'division_name' => (string)($seedRow['division_name'] ?? '-'),
                'component_id' => (int)($seedRow['component_id'] ?? 0),
                'component_code' => (string)($seedRow['component_code'] ?? ''),
                'component_name' => (string)($seedRow['component_name'] ?? ''),
                'component_type' => (string)($seedRow['component_type'] ?? ''),
                'category_name' => (string)($seedRow['category_name'] ?? ''),
                'uom_id' => (int)($seedRow['uom_id'] ?? 0),
                'uom_code' => (string)($seedRow['uom_code'] ?? ''),
            ];
            $seedMonthMap[$key] = (string)($seedRow['month_key'] ?? '');
            $valuationSeeds[$key] = ['qty' => $seedQty, 'value' => $seedValue];
        }

        if ($hasMovementLog) {
            $q = trim((string)($filters['q'] ?? ''));
            $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
            $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
            $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
            $componentIdFilter = !empty($filters['component_id']) ? (int)$filters['component_id'] : 0;
            $uomIdFilter = !empty($filters['uom_id']) ? (int)$filters['uom_id'] : 0;
            $divisionNameColumn = $this->division_name_column();
            $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

            $hasReversalLink = $this->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log');
            $reversalSelect = $hasReversalLink
                ? ', m.reversal_of_movement_id, origin.movement_type AS reversal_origin_type'
                : ', NULL AS reversal_of_movement_id, NULL AS reversal_origin_type';

            $this->db->select('m.id, m.created_at, m.movement_date, m.movement_datetime, m.location_type, m.division_id, ' . $divisionNameSelect . ', m.component_id, c.component_code, c.component_name, c.component_type, cat.name AS category_name, m.uom_id, u.code AS uom_code, m.movement_type, m.qty_in, m.qty_out, m.unit_cost, m.total_cost, m.source_table, m.source_id' . $reversalSelect, false)
                ->from('inv_component_movement_log m')
                ->join('mst_component c', 'c.id = m.component_id', 'inner')
                ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
                ->join('mst_operational_division d', 'd.id = m.division_id', 'left')
                ->join('mst_uom u', 'u.id = m.uom_id', 'left')
                ->where('m.movement_date >=', $monthKey)
                ->where('m.movement_date <=', $endDate);
            if ($hasReversalLink) {
                $this->db->join('inv_component_movement_log origin', 'origin.id = m.reversal_of_movement_id', 'left');
            }

            $this->apply_component_location_filter('m.location_type', $locationType);
            if ($divisionId > 0) {
                $this->db->where('m.division_id', $divisionId);
            }
            if ($componentIdFilter > 0) {
                $this->db->where('m.component_id', $componentIdFilter);
            }
            if ($uomIdFilter > 0) {
                $this->db->where('m.uom_id', $uomIdFilter);
            }
            if (in_array($componentType, ['BASE', 'PREPARE'], true)) {
                $this->db->where('c.component_type', $componentType);
            }
            if ($q !== '') {
                $this->db->group_start()
                    ->like('c.component_code', $q)
                    ->or_like('c.component_name', $q);
                if ($divisionNameColumn !== null) {
                    $this->db->or_like('d.' . $divisionNameColumn, $q);
                }
                $this->db->group_end();
            }

            $movementRows = $this->db
                ->order_by('m.movement_date', 'ASC')
                ->order_by('m.movement_datetime', 'ASC')
                ->order_by('m.id', 'ASC')
                ->get()
                ->result_array();

            // Identifikasi void pair yang keduanya ada di window yang sama.
            // Jika original movement DAN VOID_REVERSE/VOID_OUT keduanya ada di periode ini,
            // keduanya di-skip dari display — net effect = 0, seolah tidak pernah terjadi.
            // Void lintas bulan (originalnya di bulan lain) TIDAK di-skip: VOID_REVERSE tetap
            // diproses agar saldo bulanan tetap benar.
            $voidPairKeys   = [];
            $originPairKeys = [];
            if (!$hasReversalLink) {
                // Legacy rows have no direct link to the movement they reverse. Keep the
                // old same-document pairing only for those old rows; new linked rows are
                // netted precisely below, including partial void/refund quantities.
                foreach ($movementRows as $_r) {
                    $_mt = strtoupper(trim((string)($_r['movement_type'] ?? '')));
                    $_st = (string)($_r['source_table'] ?? '');
                    $_si = (int)($_r['source_id'] ?? 0);
                    if ($_st === '' || $_si <= 0) {
                        continue;
                    }
                    $_pk = $_st . '|' . $_si;
                    if (in_array($_mt, ['VOID_REVERSE', 'VOID_OUT'], true)) {
                        $voidPairKeys[$_pk] = true;
                    } else {
                        $originPairKeys[$_pk] = true;
                    }
                }
            }
            $skipVoidPairKeys = array_intersect_key($voidPairKeys, $originPairKeys);

            foreach ($movementRows as $movementRow) {
                $_st = (string)($movementRow['source_table'] ?? '');
                $_si = (int)($movementRow['source_id'] ?? 0);
                if ($_st !== '' && $_si > 0 && isset($skipVoidPairKeys[$_st . '|' . $_si])) {
                    continue; // skip: pasangan void dalam window yang sama
                }
                $key = $this->component_identity_key((string)($movementRow['location_type'] ?? ''), $movementRow['division_id'] ?? null, (int)($movementRow['component_id'] ?? 0), (int)($movementRow['uom_id'] ?? 0));
                if (!isset($metaMap[$key])) {
                    $metaMap[$key] = [
                        'location_type' => (string)($movementRow['location_type'] ?? ''),
                        'division_id' => $movementRow['division_id'] !== null ? (int)$movementRow['division_id'] : null,
                        'division_name' => (string)($movementRow['division_name'] ?? '-'),
                        'component_id' => (int)($movementRow['component_id'] ?? 0),
                        'component_code' => (string)($movementRow['component_code'] ?? ''),
                        'component_name' => (string)($movementRow['component_name'] ?? ''),
                        'component_type' => (string)($movementRow['component_type'] ?? ''),
                        'category_name' => (string)($movementRow['category_name'] ?? ''),
                        'uom_id' => (int)($movementRow['uom_id'] ?? 0),
                        'uom_code' => (string)($movementRow['uom_code'] ?? ''),
                    ];
                }
                if (!isset($stateMap[$key])) {
                    $stateMap[$key] = ['qty' => 0.0, 'value' => 0.0, 'avg' => 0.0];
                }

                $movementDate = (string)($movementRow['movement_date'] ?? '');
                $movementType = strtoupper(trim((string)($movementRow['movement_type'] ?? '')));
                $sourceTable = (string)($movementRow['source_table'] ?? '');
                $seededFromCurrentMonth = (($seedMonthMap[$key] ?? '') === $monthKey);
                if ($movementType === 'OPENING' && $seededFromCurrentMonth && $sourceTable === 'inv_component_opening') {
                    continue;
                }
                $valuationLogs[$key][] = $movementRow;
                $qtyIn = round((float)($movementRow['qty_in'] ?? 0), 4);
                $qtyOut = round((float)($movementRow['qty_out'] ?? 0), 4);
                $qtyBefore = round((float)($stateMap[$key]['qty'] ?? 0), 4);
                $avgBefore = round((float)($stateMap[$key]['avg'] ?? 0), 6);
                $valueBefore = round((float)($stateMap[$key]['value'] ?? 0), 2);

                if (!isset($dailyRows[$key][$movementDate])) {
                    $dailyRows[$key][$movementDate] = $metaMap[$key] + [
                        'month_key' => $monthKey,
                        'movement_date' => $movementDate,
                        'opening_qty' => $movementType === 'OPENING' ? round($qtyBefore + $qtyIn, 4) : $qtyBefore,
                        'in_qty' => 0.0,
                        'out_qty' => 0.0,
                        'waste_qty' => 0.0,
                        'spoil_qty' => 0.0,
                        'adjustment_qty' => 0.0,
                        'closing_qty' => $qtyBefore,
                        'avg_cost' => $avgBefore,
                        'total_value' => $valueBefore,
                        'mutation_count' => 0,
                    ];
                } elseif ($movementType === 'OPENING') {
                    $dailyRows[$key][$movementDate]['opening_qty'] = round((float)($dailyRows[$key][$movementDate]['opening_qty'] ?? 0) + $qtyIn, 4);
                }

                $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
                if (abs($qtyAfter) < 0.0001) {
                    $qtyAfter = 0.0;
                }

                $unitCost = round((float)($movementRow['unit_cost'] ?? 0), 6);
                $movementValue = $this->component_movement_value($movementRow);
                if ($qtyIn > 0) {
                    $valueAfter = round($valueBefore + $movementValue, 2);
                } elseif ($qtyOut > 0) {
                    $valueAfter = round($valueBefore - $movementValue, 2);
                } else {
                    $valueAfter = $valueBefore;
                }
                if (abs($valueAfter) < 0.01) {
                    $valueAfter = 0.0;
                }

                $avgAfter = abs($qtyAfter) > 0.0001 ? round($valueAfter / $qtyAfter, 6) : 0.0;
                $isOpeningSnapshotReverse = $sourceTable === 'inv_component_opening' && $qtyOut > 0;
                $handledAsOpeningSnapshotReverse = false;
                if ($isOpeningSnapshotReverse) {
                    $dailyRows[$key][$movementDate]['opening_qty'] = round((float)($dailyRows[$key][$movementDate]['opening_qty'] ?? 0) - $qtyOut, 4);
                    if (abs((float)$dailyRows[$key][$movementDate]['opening_qty']) < 0.0001) {
                        $dailyRows[$key][$movementDate]['opening_qty'] = 0.0;
                    }
                    $handledAsOpeningSnapshotReverse = true;
                }

                switch ($movementType) {
                    case 'PRODUCTION_IN':
                    case 'TRANSFER_IN':
                        $dailyRows[$key][$movementDate]['in_qty'] = round((float)$dailyRows[$key][$movementDate]['in_qty'] + $qtyIn, 4);
                        break;
                    case 'PRODUCTION_OUT':
                    case 'TRANSFER_OUT':
                    case 'USAGE':
                        $dailyRows[$key][$movementDate]['out_qty'] = round((float)$dailyRows[$key][$movementDate]['out_qty'] + $qtyOut, 4);
                        break;
                    case 'WASTE':
                        $dailyRows[$key][$movementDate]['waste_qty'] = round((float)$dailyRows[$key][$movementDate]['waste_qty'] + $qtyOut, 4);
                        $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] - $qtyOut, 4);
                        break;
                    case 'SPOIL':
                        $dailyRows[$key][$movementDate]['spoil_qty'] = round((float)$dailyRows[$key][$movementDate]['spoil_qty'] + $qtyOut, 4);
                        $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] - $qtyOut, 4);
                        break;
                    case 'ADJUSTMENT_PLUS':
                        $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] + $qtyIn, 4);
                        break;
                    case 'ADJUSTMENT_MINUS':
                        if (!$handledAsOpeningSnapshotReverse) {
                            $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] - $qtyOut, 4);
                        }
                        break;
                    case 'VOID_REVERSE':
                        $originType = strtoupper(trim((string)($movementRow['reversal_origin_type'] ?? '')));
                        if (in_array($originType, ['PRODUCTION_OUT', 'TRANSFER_OUT', 'USAGE'], true)) {
                            $dailyRows[$key][$movementDate]['out_qty'] = round((float)$dailyRows[$key][$movementDate]['out_qty'] - $qtyIn, 4);
                        } elseif ($originType === 'WASTE') {
                            $dailyRows[$key][$movementDate]['waste_qty'] = round((float)$dailyRows[$key][$movementDate]['waste_qty'] - $qtyIn, 4);
                            $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] + $qtyIn, 4);
                        } elseif ($originType === 'SPOIL') {
                            $dailyRows[$key][$movementDate]['spoil_qty'] = round((float)$dailyRows[$key][$movementDate]['spoil_qty'] - $qtyIn, 4);
                            $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] + $qtyIn, 4);
                        } elseif ($originType === 'ADJUSTMENT_MINUS') {
                            $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] + $qtyIn, 4);
                        } elseif (in_array($originType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                            $dailyRows[$key][$movementDate]['in_qty'] = round((float)$dailyRows[$key][$movementDate]['in_qty'] - $qtyIn, 4);
                        } else {
                            // Legacy/unlinked reversals remain visible as a correction.
                            $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] + $qtyIn, 4);
                        }
                        break;
                    case 'VOID_OUT':
                        $originType = strtoupper(trim((string)($movementRow['reversal_origin_type'] ?? '')));
                        if (in_array($originType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                            $dailyRows[$key][$movementDate]['in_qty'] = round((float)$dailyRows[$key][$movementDate]['in_qty'] - $qtyOut, 4);
                        } else {
                            $dailyRows[$key][$movementDate]['adjustment_qty'] = round((float)$dailyRows[$key][$movementDate]['adjustment_qty'] - $qtyOut, 4);
                        }
                        break;
                }

                $dailyRows[$key][$movementDate]['closing_qty'] = $qtyAfter;
                $dailyRows[$key][$movementDate]['avg_cost'] = $avgAfter;
                $dailyRows[$key][$movementDate]['total_value'] = round($valueAfter, 2);
                $dailyRows[$key][$movementDate]['mutation_count'] = (int)$dailyRows[$key][$movementDate]['mutation_count'] + 1;
                $stateMap[$key] = [
                    'qty' => $qtyAfter,
                    'value' => round($valueAfter, 2),
                    'avg' => $avgAfter,
                ];
            }
        }

        foreach ($stateMap as $key => $state) {
            if (!empty($dailyRows[$key])) {
                continue;
            }

            $seedQty = round((float)($state['qty'] ?? 0), 4);
            $seedValue = round((float)($state['value'] ?? 0), 2);
            if (abs($seedQty) < 0.0001 && abs($seedValue) < 0.01) {
                continue;
            }

            $meta = $metaMap[$key] ?? [];
            if (empty($meta)) {
                continue;
            }

            $dailyRows[$key][$startDate] = $meta + [
                'month_key' => $monthKey,
                'movement_date' => $startDate,
                'opening_qty' => $seedQty,
                'in_qty' => 0.0,
                'out_qty' => 0.0,
                'waste_qty' => 0.0,
                'spoil_qty' => 0.0,
                'adjustment_qty' => 0.0,
                'closing_qty' => $seedQty,
                'avg_cost' => round((float)($state['avg'] ?? 0), 6),
                'total_value' => $seedValue,
                'mutation_count' => 0,
            ];
        }

        $corrections = $this->component_value_corrections($monthKey, $endDate);
        foreach ($corrections as $correction) {
            if (!in_array($correction['status'] ?? '', ['POSTED', 'VOID'], true)) {
                continue;
            }
            $key = $this->component_identity_key((string)$correction['location_type'], $correction['division_id'], (int)$correction['component_id'], (int)$correction['content_uom_id']);
            if (!isset($metaMap[$key])) {
                continue;
            }
            foreach (['posted_at', 'voided_at'] as $timeKey) {
                $day = substr((string)($correction[$timeKey] ?? ''), 0, 10);
                if ($day < $monthKey || $day > $endDate || isset($dailyRows[$key][$day])) {
                    continue;
                }
                $dailyRows[$key][$day] = $metaMap[$key] + ['month_key' => $monthKey, 'movement_date' => $day,
                    'opening_qty' => 0, 'in_qty' => 0, 'out_qty' => 0, 'waste_qty' => 0, 'spoil_qty' => 0,
                    'adjustment_qty' => 0, 'closing_qty' => 0, 'mutation_count' => 0];
            }
        }
        $rows = [];
        foreach ($dailyRows as $key => $dailyPerKey) {
            ksort($dailyPerKey);
            foreach ($dailyPerKey as $row) {
                $valuation = $this->component_value_projection($metaMap[$key], $valuationLogs[$key] ?? [], $corrections,
                    (float)($valuationSeeds[$key]['qty'] ?? 0), (float)($valuationSeeds[$key]['value'] ?? 0), $row['movement_date']);
                if ((int)$row['mutation_count'] === 0) {
                    $row['opening_qty'] = $valuation['qty'];
                }
                $row['closing_qty'] = $valuation['qty'];
                $row['total_value'] = $valuation['value'];
                $row['avg_cost'] = abs($valuation['qty']) > 0.0001 ? round($valuation['value'] / $valuation['qty'], 6) : 0.0;
                $row['valuation_warning'] = $valuation['ok'] ? '' : $valuation['message'];
                if ($row['movement_date'] >= $startDate) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    public function ensure_component_daily_snapshot_seeded(): array
    {
        return [
            'ok' => true,
            'seeded' => false,
            'deprecated' => true,
            'message' => 'Daily rollup component sudah dipensiunkan. Runtime memakai movement log dan monthly stock.',
        ];
    }

    private function component_daily_snapshot_needs_rebuild_for_void_openings(): bool
    {
        return false;
    }

    private function component_daily_snapshot_needs_rebuild_for_negative_balances(): bool
    {
        return false;
    }

    public function rebuild_component_daily_snapshot_from_logs(): array
    {
        return [
            'ok' => true,
            'seeded' => false,
            'rows' => 0,
            'deprecated' => true,
            'message' => 'Daily rollup component sudah dipensiunkan. Tidak ada rebuild yang dijalankan.',
        ];
    }

    private function month_date_series(string $startDate, string $endDate): array
    {
        $dates = [];
        $cursor = strtotime($startDate);
        $until = strtotime($endDate);
        while ($cursor !== false && $until !== false && $cursor <= $until) {
            $dates[] = [
                'date' => date('Y-m-d', $cursor),
                'day' => date('d', $cursor),
                'weekday' => date('D', $cursor),
            ];
            $cursor = strtotime('+1 day', $cursor);
        }
        return $dates;
    }

    public function list_component_openings(array $filters = [], int $limit = 200): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo   = trim((string)($filters['date_to'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $monthKey = $this->normalize_month_key((string)($filters['month'] ?? ''));
            if ($monthKey !== null) {
                $dateFrom = $monthKey . '-01';
                $dateTo   = date('Y-m-t', strtotime($dateFrom));
            } else {
                $dateFrom = '';
                $dateTo   = '';
            }
        }
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : null;
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select('h.*, ' . $divisionNameSelect, false);
        $this->db->from('inv_component_opening h');
        $this->db->join('mst_operational_division d', 'd.id = h.division_id', 'left');
        if ($dateFrom !== '') {
            $this->db->where('h.opening_date >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $this->db->where('h.opening_date <=', $dateTo);
        }
        $this->apply_component_location_filter('h.location_type', $locationType);
        if ($divisionId !== null) {
            $this->db->where('h.division_id', $divisionId);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db
                ->like('h.opening_no', $q)
                ->or_like('h.location_type', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }
        $this->db->order_by('h.opening_date', 'DESC')->order_by('h.id', 'DESC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function get_component_opening(int $id): ?array
    {
        $row = $this->db->get_where('inv_component_opening', ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function find_component_opening_month_conflict(int $excludeId, string $openingDate, string $locationType, ?int $divisionId): ?array
    {
        $monthKey = $this->normalize_month_key($openingDate);
        if ($monthKey === null || $locationType === '' || $divisionId === null || $divisionId <= 0) {
            return null;
        }

        $query = $this->db->select('id, status, opening_no, opening_date, location_type, division_id')
            ->from('inv_component_opening')
            ->where('location_type', $locationType)
            ->where('division_id', $divisionId)
            ->where('status <>', 'VOID')
            ->where('opening_date >=', $monthKey . '-01')
            ->where('opening_date <=', date('Y-m-t', strtotime($monthKey . '-01')));
        if ($excludeId > 0) {
            $query->where('id <>', $excludeId);
        }

        $row = $query->order_by('id', 'DESC')->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function get_component_opening_lines(int $openingId): array
    {
        return $this->db->select('l.*, c.component_name, c.component_code, u.code AS uom_code')
            ->select("(SELECT GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ' | ') FROM mst_product_recipe pr JOIN mst_product p ON p.id = pr.product_id WHERE pr.component_id = l.component_id) AS related_product_names", false)
            ->from('inv_component_opening_line l')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.opening_id', $openingId)
            ->order_by('l.line_no', 'ASC')
            ->get()->result_array();
    }

    public function component_opening_detail(int $id): array
    {
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
        $header = $this->db->select('h.*, ' . $divisionNameSelect, false)
            ->from('inv_component_opening h')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Opening tidak ditemukan.'];
        }

        $lines = $this->get_component_opening_lines($id);
        $summary = [
            'line_count' => count($lines),
            'total_qty' => 0.0,
            'total_value' => 0.0,
            'movement_count' => 0,
            'lot_count' => 0,
        ];
        foreach ($lines as $line) {
            $qty = round((float)($line['opening_qty'] ?? 0), 4);
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            $summary['total_qty'] += $qty;
            $summary['total_value'] += round($qty * $unitCost, 2);
        }

        $movementRows = [];
        $effectiveMovementRows = [];
        $lotRows = [];
        $activeLotRows = [];
        $canVoid = strtoupper((string)($header['status'] ?? '')) === 'POSTED';
        $blockReason = '';

        if ($this->db->table_exists('inv_component_movement_log')) {
            $movementRows = $this->db->select('m.*, c.component_name, c.component_code, u.code AS uom_code')
                ->from('inv_component_movement_log m')
                ->join('mst_component c', 'c.id = m.component_id', 'left')
                ->join('mst_uom u', 'u.id = m.uom_id', 'left')
                ->where('m.source_table', 'inv_component_opening')
                ->where('m.source_id', $id)
                ->order_by('m.id', 'ASC')
                ->get()
                ->result_array();
            foreach ($movementRows as &$movementRow) {
                $movementRow['movement_type_label'] = $this->component_movement_type_label((string)($movementRow['movement_type'] ?? ''));
            }
            unset($movementRow);
            $summary['movement_count'] = count($movementRows);

            $effectiveMap = [];
            foreach ($movementRows as $movementRow) {
                $componentId = (int)($movementRow['component_id'] ?? 0);
                $uomId = (int)($movementRow['uom_id'] ?? 0);
                if ($componentId <= 0 || $uomId <= 0) {
                    continue;
                }
                $key = $componentId . '|' . $uomId;
                if (!isset($effectiveMap[$key])) {
                    $effectiveMap[$key] = [
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'component_name' => (string)($movementRow['component_name'] ?? ''),
                        'component_code' => (string)($movementRow['component_code'] ?? ''),
                        'uom_code' => (string)($movementRow['uom_code'] ?? ''),
                        'effective_qty' => 0.0,
                        'latest_unit_cost' => 0.0,
                        'latest_movement_date' => (string)($movementRow['movement_date'] ?? ''),
                        'movement_count' => 0,
                    ];
                }
                $effectiveMap[$key]['effective_qty'] += round((float)($movementRow['qty_in'] ?? 0), 4);
                $effectiveMap[$key]['effective_qty'] -= round((float)($movementRow['qty_out'] ?? 0), 4);
                $effectiveMap[$key]['latest_unit_cost'] = round((float)($movementRow['unit_cost'] ?? 0), 6);
                $effectiveMap[$key]['latest_movement_date'] = (string)($movementRow['movement_date'] ?? '');
                $effectiveMap[$key]['movement_count']++;
            }
            foreach ($effectiveMap as $effectiveRow) {
                if (round((float)($effectiveRow['effective_qty'] ?? 0), 4) <= 0) {
                    continue;
                }
                $effectiveRow['effective_qty'] = round((float)$effectiveRow['effective_qty'], 4);
                $effectiveMovementRows[] = $effectiveRow;
            }
        }

        if ($this->db->table_exists('inv_component_lot')) {
            $lotRows = $this->db->select('l.*, c.component_name, c.component_code, u.code AS uom_code')
                ->from('inv_component_lot l')
                ->join('mst_component c', 'c.id = l.component_id', 'left')
                ->join('mst_uom u', 'u.id = l.uom_id', 'left')
                ->where('l.source_table', 'inv_component_opening')
                ->where('l.source_id', $id)
                ->order_by('l.receipt_date', 'ASC')
                ->order_by('l.id', 'ASC')
                ->get()
                ->result_array();
            $summary['lot_count'] = count($lotRows);

            foreach ($lotRows as $lotRow) {
                if (strtoupper((string)($lotRow['status'] ?? '')) === 'VOID') {
                    continue;
                }
                $activeLotRows[] = $lotRow;
            }
        }

        $summary['effective_movement_count'] = count($effectiveMovementRows);
        $summary['active_lot_count'] = count($activeLotRows);

        if ($canVoid) {
            if (empty($movementRows)) {
                $canVoid = false;
                $blockReason = 'Movement opening belum ditemukan, sehingga dokumen ini tidak bisa di-void dari halaman detail.';
            } else {
                $guard = $this->guard_component_opening_void_usage($header, $movementRows);
                if (!($guard['ok'] ?? false)) {
                    $canVoid = false;
                    $blockReason = (string)($guard['message'] ?? 'Opening sudah dipakai transaksi lain.');
                }
            }
        }

        return [
            'ok' => true,
            'header' => $header,
            'lines' => $lines,
            'movement_rows' => $movementRows,
            'effective_movement_rows' => $effectiveMovementRows,
            'lot_rows' => $lotRows,
            'active_lot_rows' => $activeLotRows,
            'summary' => $summary,
            'can_void' => $canVoid,
            'block_reason' => $blockReason,
        ];
    }

    public function void_component_opening(int $id, int $actorEmployeeId): array
    {
        return $this->rollback_component_opening_posting(
            $id,
            $actorEmployeeId,
            'VOID',
            'PRODUCTION_OPENING_VOID',
            'VOID opening component membatalkan lot awal',
            'VOID opening component',
            false
        );
    }

    public function reopen_component_opening_draft(int $id, int $actorEmployeeId): array
    {
        return $this->rollback_component_opening_posting(
            $id,
            $actorEmployeeId,
            'DRAFT',
            'PRODUCTION_OPENING_REOPEN',
            'Reopen opening component ke draft membatalkan lot posted sebelumnya',
            'REOPEN ke draft untuk koreksi opening component',
            true
        );
    }

    private function rollback_component_opening_posting(
        int $id,
        int $actorEmployeeId,
        string $targetStatus,
        string $sourceModule,
        string $lotNote,
        string $headerNote,
        bool $clearPostedFields
    ): array
    {
        $header = $this->get_component_opening($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Opening tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? 'DRAFT')) !== 'POSTED') {
            return ['ok' => false, 'message' => 'Hanya opening POSTED yang bisa dibuka ulang atau di-void.'];
        }
        if (!$this->db->table_exists('inv_component_movement_log')) {
            return ['ok' => false, 'message' => 'Histori movement component belum tersedia.'];
        }
        $reversalReady = $this->assert_component_movement_reversal_ready();
        if (!($reversalReady['ok'] ?? false)) {
            return $reversalReady;
        }

        $openingMovements = $this->db->from('inv_component_movement_log')
            ->where('source_table', 'inv_component_opening')
            ->where('source_id', $id)
            ->order_by('id', 'DESC')
            ->get()
            ->result_array();
        if (empty($openingMovements)) {
            return ['ok' => false, 'message' => 'Histori movement opening tidak ditemukan.'];
        }

        $guard = $this->guard_component_opening_void_usage($header, $openingMovements);
        if (!($guard['ok'] ?? false)) {
            return $guard;
        }

        $this->db->trans_begin();

        if ($this->db->table_exists('inv_component_lot')) {
            $this->load->library('ComponentLotManager');
            $lotReady = $this->componentlotmanager->ensureReady();
            if (!($lotReady['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $lotReady;
            }
            $voidLot = $this->componentlotmanager->voidInboundLotsBySource(
                'inv_component_opening',
                $id,
                null,
                $lotNote
            );
            if (!($voidLot['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Rollback opening ditolak: ' . (string)($voidLot['message'] ?? 'void lot component gagal.')];
            }
        }

        $rebuildIdentities = [];
        foreach ($openingMovements as $movement) {
            $reverse = $this->reverse_component_document_movement_row(
                $movement,
                (string)($header['opening_date'] ?? date('Y-m-d')),
                'inv_component_opening',
                $id,
                $sourceModule,
                $actorEmployeeId
            );
            if (!($reverse['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $reverse;
            }
            if (!empty($reverse['identity']) && is_array($reverse['identity'])) {
                $rebuildIdentities[] = $reverse['identity'];
            }
        }

        $rebuild = $this->rebuild_component_histories($rebuildIdentities);
        if (!($rebuild['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $rebuild;
        }

        $update = ['status' => $targetStatus];
        if ($this->db->field_exists('voided_at', 'inv_component_opening')) {
            $update['voided_at'] = date('Y-m-d H:i:s');
        }
        if ($this->db->field_exists('voided_by', 'inv_component_opening')) {
            $update['voided_by'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
        }
        if ($clearPostedFields) {
            if ($this->db->field_exists('posted_at', 'inv_component_opening')) {
                $update['posted_at'] = null;
            }
            if ($this->db->field_exists('posted_by', 'inv_component_opening')) {
                $update['posted_by'] = null;
            }
        }
        if ($this->db->field_exists('notes', 'inv_component_opening')) {
            $existingNotes = trim((string)($header['notes'] ?? ''));
            $update['notes'] = $existingNotes !== '' ? ($existingNotes . ' | ' . $headerNote) : $headerNote;
        }
        $this->db->where('id', $id)->update('inv_component_opening', $update);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $targetStatus === 'DRAFT' ? 'Gagal membuka kembali opening ke draft.' : 'Gagal VOID opening component.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id];
    }

    private function guard_component_opening_void_usage(array $header, array $openingMovements): array
    {
        $openingId = (int)($header['id'] ?? 0);
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = !empty($header['division_id']) ? (int)$header['division_id'] : null;
        $divisionNullSafe = $divisionId === null ? 'NULL' : (string)$divisionId;

        foreach ($openingMovements as $movement) {
            $componentId = (int)($movement['component_id'] ?? 0);
            $uomId = (int)($movement['uom_id'] ?? 0);
            $movementId = (int)($movement['id'] ?? 0);
            if ($componentId <= 0 || $uomId <= 0 || $movementId <= 0) {
                continue;
            }

            $nextUsage = $this->db->select('movement_no, movement_date, movement_type')
                ->from('inv_component_movement_log')
                ->where('location_type', $locationType)
                ->where('division_id <=> ' . $divisionNullSafe, null, false)
                ->where('component_id', $componentId)
                ->where('uom_id', $uomId)
                ->where('qty_out >', 0)
                ->group_start()
                    ->where('source_table <>', 'inv_component_opening')
                    ->or_group_start()
                        ->where('source_table', 'inv_component_opening')
                        ->where('source_id <>', $openingId)
                    ->group_end()
                ->group_end()
                ->order_by('movement_date', 'ASC')
                ->order_by('id', 'ASC')
                ->limit(1);
            $this->apply_component_movement_after_anchor((string)($movement['movement_date'] ?? ''), $movementId);
            $nextUsage = $this->db->get()->row_array();
            if ($nextUsage) {
                return [
                    'ok' => false,
                    'message' => 'Opening tidak bisa di-void karena stok sudah dipakai pada ' . $this->component_movement_type_label((string)($nextUsage['movement_type'] ?? '')) . ' ' . (string)($nextUsage['movement_no'] ?? '-') . ' tanggal ' . (string)($nextUsage['movement_date'] ?? '-') . '.',
                ];
            }
        }

        return ['ok' => true];
    }

    public function list_component_monthly_openings(array $filters = [], int $limit = 200): array
    {
        if (!$this->db->table_exists('inv_component_monthly_opening')) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $monthKey = $this->normalize_month_key((string)($filters['month'] ?? ''));
        $monthDate = $monthKey !== null ? ($monthKey . '-01') : null;
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : null;
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select("DATE_FORMAT(m.month_key, '%Y-%m') AS month_key, m.location_type, m.division_id, m.component_id, m.uom_id, m.opening_qty, m.opening_avg_cost AS hpp_live, m.opening_total_value AS total_value, m.source_type, DATE_FORMAT(m.source_month_key, '%Y-%m') AS source_month, m.generated_at, c.component_code, c.component_name, c.component_type, u.code AS uom_code, u.name AS uom_name, " . $divisionNameSelect, false);
        $this->db->from('inv_component_monthly_opening m');
        $this->db->join('mst_component c', 'c.id = m.component_id', 'left');
        $this->db->join('mst_uom u', 'u.id = m.uom_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = m.division_id', 'left');

        if ($monthDate !== null) {
            $this->db->where('m.month_key', $monthDate);
        }
        $this->apply_component_location_filter('m.location_type', $locationType);
        if ($divisionId !== null) {
            $this->db->where('m.division_id', $divisionId);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->or_like('m.location_type', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        $this->db->order_by('m.month_key', 'DESC');
        $this->apply_component_display_order(
            $divisionNameColumn !== null ? ('d.' . $divisionNameColumn) : 'm.division_id',
            'c.component_type',
            'c.component_name'
        );
        $this->db->order_by('m.location_type', 'ASC');
        $this->db->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function generate_component_monthly_opname_and_opening(array $payload, int $userId): array
    {
        $monthKey = $this->normalize_month_key((string)($payload['month'] ?? $payload['month_key'] ?? date('Y-m')));
        if ($monthKey === null) {
            return [
                'ok' => false,
                'message' => 'Parameter bulan tidak valid.',
            ];
        }
        $currentMonthKey = date('Y-m');
        if ($monthKey >= $currentMonthKey) {
            return [
                'ok' => false,
                'message' => 'Generate opname bulanan component hanya boleh untuk bulan yang sudah selesai. Pilih bulan sebelum ' . $currentMonthKey . '.',
            ];
        }

        if (!$this->db->table_exists('inv_component_monthly_opname')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_component_monthly_opname belum tersedia.',
            ];
        }
        if (!$this->db->table_exists('inv_component_monthly_opening')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_component_monthly_opening belum tersedia.',
            ];
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $monthStart = $monthKey . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $opnameMonthKey = $monthEnd;
        $nextMonth = date('Y-m', strtotime('+1 month', strtotime($monthStart)));
        $nextMonthStart = $nextMonth . '-01';

        $sourceFilters = [
            'month' => $monthKey,
            'location_type' => $locationType,
            'division_id' => $divisionId,
        ];
        $rows = $this->fetch_component_monthly_generate_source_rows($sourceFilters, $monthStart);
        $usingMonthlyTruth = !empty($rows);
        if (!$usingMonthlyTruth) {
            $rows = $this->component_daily_rows($sourceFilters, 0);
        }

        if (empty($rows)) {
            return [
                'ok' => false,
                'message' => 'Belum ada proyeksi harian component untuk bulan ' . $monthKey . '.',
            ];
        }

        $negativeAtMonthEnd = [];
        if ($usingMonthlyTruth) {
            foreach ($rows as $row) {
                $endClosing = round((float)($row['closing_qty'] ?? 0), 2);
                if ($endClosing < 0) {
                    $negativeAtMonthEnd[] = [
                        'code'          => (string)($row['component_code'] ?? '-'),
                        'name'          => (string)($row['component_name'] ?? '-'),
                        'location_type' => (string)($row['location_type'] ?? '-'),
                        'division_name' => (string)($row['division_name'] ?? '-'),
                        'negative_days' => 1,
                        'worst_closing' => $endClosing,
                        'worst_date'    => $monthEnd,
                    ];
                }
            }
        } else {
            // Kumpulkan closing AKHIR BULAN per identity (baris terakhir per identity)
            // dan catat hari-hari yang sempat minus (untuk warning nilai tidak akurat)
            $finalClosing    = [];
            $midMonthNegative = [];

            foreach ($rows as $row) {
                $negKey = strtoupper((string)($row['location_type'] ?? ''))
                    . '|' . (int)($row['division_id'] ?? 0)
                    . '|' . (int)($row['component_id'] ?? 0)
                    . '|' . (int)($row['uom_id'] ?? 0);

                $finalClosing[$negKey] = $row;

                $dayClosing = round((float)($row['closing_qty'] ?? 0), 2);
                if ($dayClosing < 0) {
                    if (!isset($midMonthNegative[$negKey])) {
                        $midMonthNegative[$negKey] = [
                            'component_code' => (string)($row['component_code'] ?? '-'),
                            'component_name' => (string)($row['component_name'] ?? '-'),
                            'location_type'  => (string)($row['location_type'] ?? '-'),
                            'division_name'  => (string)($row['division_name'] ?? '-'),
                            'negative_days'  => 0,
                            'worst_closing'  => 0.0,
                            'worst_date'     => '',
                        ];
                    }
                    $midMonthNegative[$negKey]['negative_days']++;
                    if ($dayClosing < $midMonthNegative[$negKey]['worst_closing']) {
                        $midMonthNegative[$negKey]['worst_closing'] = $dayClosing;
                        $midMonthNegative[$negKey]['worst_date']    = trim((string)($row['movement_date'] ?? ''));
                    }
                }
            }

            foreach ($finalClosing as $negKey => $row) {
                $endClosing = round((float)($row['closing_qty'] ?? 0), 2);
                if ($endClosing < 0) {
                    $negativeAtMonthEnd[] = [
                        'code'          => (string)($row['component_code'] ?? '-'),
                        'name'          => (string)($row['component_name'] ?? '-'),
                        'location_type' => (string)($row['location_type'] ?? '-'),
                        'division_name' => (string)($row['division_name'] ?? '-'),
                        'negative_days' => $midMonthNegative[$negKey]['negative_days'] ?? 1,
                        'worst_closing' => $endClosing,
                        'worst_date'    => trim((string)($row['movement_date'] ?? '')),
                    ];
                }
            }
        }
        if (!empty($negativeAtMonthEnd)) {
            return [
                'ok' => false,
                'message' => 'Generate ditolak: ' . count($negativeAtMonthEnd) . ' komponen masih minus di akhir bulan. Lakukan ADJ_PLUS terlebih dahulu.',
                'data' => ['negative_samples' => $negativeAtMonthEnd],
            ];
        }

        $aggregated = [];
        if ($usingMonthlyTruth) {
            foreach ($rows as $row) {
                $groupKey = implode('|', [
                    strtoupper((string)($row['location_type'] ?? '')),
                    (int)($row['division_id'] ?? 0),
                    (int)($row['component_id'] ?? 0),
                    (int)($row['uom_id'] ?? 0),
                ]);
                $aggregated[$groupKey] = [
                    'month_key' => $opnameMonthKey,
                    'location_type' => strtoupper((string)($row['location_type'] ?? '')),
                    'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'opening_qty' => round((float)($row['opening_qty'] ?? 0), 4),
                    'opening_total_value' => round((float)($row['opening_total_value'] ?? ((float)($row['opening_qty'] ?? 0) * (float)($row['avg_cost'] ?? 0))), 2),
                    'in_qty' => round((float)($row['in_qty'] ?? 0), 4),
                    'in_total_value' => round((float)($row['in_total_value'] ?? 0), 2),
                    'out_qty' => round((float)($row['out_qty'] ?? 0), 4),
                    'out_total_value' => round((float)($row['out_total_value'] ?? 0), 2),
                    'waste_qty' => round((float)($row['waste_qty'] ?? 0), 4),
                    'waste_total_value' => round((float)($row['waste_total_value'] ?? 0), 2),
                    'spoil_qty' => round((float)($row['spoil_qty'] ?? 0), 4),
                    'spoil_total_value' => round((float)($row['spoil_total_value'] ?? 0), 2),
                    'adjustment_plus_qty' => round((float)($row['adjustment_plus_qty'] ?? 0), 4),
                    'adjustment_plus_total_value' => round((float)($row['adjustment_plus_total_value'] ?? 0), 2),
                    'adjustment_minus_qty' => round((float)($row['adjustment_minus_qty'] ?? 0), 4),
                    'adjustment_minus_total_value' => round((float)($row['adjustment_minus_total_value'] ?? 0), 2),
                    'closing_qty' => round((float)($row['closing_qty'] ?? 0), 4),
                    'avg_cost' => round((float)($row['avg_cost'] ?? 0), 6),
                    'total_value' => round((float)($row['total_value'] ?? 0), 2),
                    'movement_day_count' => (int)($row['movement_day_count'] ?? 0),
                    'mutation_count' => (int)($row['mutation_count'] ?? 0),
                    'generated_by' => $userId > 0 ? $userId : null,
                ];
            }
        } else {
            foreach ($rows as $row) {
                $groupKey = implode('|', [
                    strtoupper((string)($row['location_type'] ?? '')),
                    (int)($row['division_id'] ?? 0),
                    (int)($row['component_id'] ?? 0),
                    (int)($row['uom_id'] ?? 0),
                ]);

                if (!isset($aggregated[$groupKey])) {
                    $aggregated[$groupKey] = [
                        'month_key' => $opnameMonthKey,
                        'location_type' => strtoupper((string)($row['location_type'] ?? '')),
                        'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
                        'component_id' => (int)($row['component_id'] ?? 0),
                        'uom_id' => (int)($row['uom_id'] ?? 0),
                        'opening_qty' => round((float)($row['opening_qty'] ?? 0), 4),
                        'opening_total_value' => round(((float)($row['opening_qty'] ?? 0) * (float)($row['avg_cost'] ?? 0)), 2),
                        'in_qty' => 0.0,
                        'in_total_value' => 0.0,
                        'out_qty' => 0.0,
                        'out_total_value' => 0.0,
                        'waste_qty' => 0.0,
                        'waste_total_value' => 0.0,
                        'spoil_qty' => 0.0,
                        'spoil_total_value' => 0.0,
                        'adjustment_plus_qty' => 0.0,
                        'adjustment_plus_total_value' => 0.0,
                        'adjustment_minus_qty' => 0.0,
                        'adjustment_minus_total_value' => 0.0,
                        'closing_qty' => 0.0,
                        'avg_cost' => 0.0,
                        'total_value' => 0.0,
                        'movement_day_count' => 0,
                        'mutation_count' => 0,
                        'generated_by' => $userId > 0 ? $userId : null,
                        '_first_date' => '9999-12-31',
                        '_last_date' => '0000-00-00',
                    ];
                }

                $movementDate = (string)($row['movement_date'] ?? '');
                $aggregated[$groupKey]['movement_day_count']++;
                $aggregated[$groupKey]['mutation_count'] += (int)($row['mutation_count'] ?? 0);
                $aggregated[$groupKey]['in_qty'] += round((float)($row['in_qty'] ?? 0), 4);
                $aggregated[$groupKey]['out_qty'] += round((float)($row['out_qty'] ?? 0), 4);
                $aggregated[$groupKey]['waste_qty'] += round((float)($row['waste_qty'] ?? 0), 4);
                $aggregated[$groupKey]['spoil_qty'] += round((float)($row['spoil_qty'] ?? 0), 4);
                $adjustmentQty = round((float)($row['adjustment_qty'] ?? 0), 4);
                if ($adjustmentQty >= 0) {
                    $aggregated[$groupKey]['adjustment_plus_qty'] += $adjustmentQty;
                } else {
                    $aggregated[$groupKey]['adjustment_minus_qty'] += abs($adjustmentQty);
                }
                if ($movementDate < $aggregated[$groupKey]['_first_date']) {
                    $aggregated[$groupKey]['_first_date'] = $movementDate;
                    $aggregated[$groupKey]['opening_qty'] = round((float)($row['opening_qty'] ?? 0), 4);
                    $aggregated[$groupKey]['opening_total_value'] = round(((float)($row['opening_qty'] ?? 0) * (float)($row['avg_cost'] ?? 0)), 2);
                }
                if ($movementDate >= $aggregated[$groupKey]['_last_date']) {
                    $aggregated[$groupKey]['_last_date'] = $movementDate;
                    $aggregated[$groupKey]['closing_qty'] = round((float)($row['closing_qty'] ?? 0), 4);
                    $aggregated[$groupKey]['avg_cost'] = round((float)($row['avg_cost'] ?? 0), 6);
                    $aggregated[$groupKey]['total_value'] = round((float)($row['total_value'] ?? 0), 2);
                }
            }
            foreach ($aggregated as $groupKey => $row) {
                $avgCost = (float)($row['avg_cost'] ?? 0);
                $aggregated[$groupKey]['in_total_value'] = round((float)$row['in_qty'] * $avgCost, 2);
                $aggregated[$groupKey]['out_total_value'] = round((float)$row['out_qty'] * $avgCost, 2);
                $aggregated[$groupKey]['waste_total_value'] = round((float)$row['waste_qty'] * $avgCost, 2);
                $aggregated[$groupKey]['spoil_total_value'] = round((float)$row['spoil_qty'] * $avgCost, 2);
                $aggregated[$groupKey]['adjustment_plus_total_value'] = round((float)$row['adjustment_plus_qty'] * $avgCost, 2);
                $aggregated[$groupKey]['adjustment_minus_total_value'] = round((float)$row['adjustment_minus_qty'] * $avgCost, 2);
                unset($aggregated[$groupKey]['_first_date'], $aggregated[$groupKey]['_last_date']);
            }
        }

        // Carry-forward must never turn an invalid component valuation into a
        // zero-cost opening lot. Quantity can be correct while its HPP is not.
        $negativeValuationSamples = [];
        foreach ($aggregated as $row) {
            $closingQty = round((float)($row['closing_qty'] ?? 0), 4);
            $avgCost = round((float)($row['avg_cost'] ?? 0), 6);
            $totalValue = round((float)($row['total_value'] ?? 0), 2);
            if ($closingQty <= 0.0001 || ($avgCost >= -0.000001 && $totalValue >= -0.01)) {
                continue;
            }

            $negativeValuationSamples[] = [
                'component_id' => (int)($row['component_id'] ?? 0),
                'location_type' => (string)($row['location_type'] ?? '-'),
                'division_id' => $row['division_id'] ?? null,
                'closing_qty' => $closingQty,
                'avg_cost' => $avgCost,
                'total_value' => $totalValue,
            ];
        }
        if (!empty($negativeValuationSamples)) {
            return [
                'ok' => false,
                'message' => 'Generate ditolak: terdapat ' . count($negativeValuationSamples)
                    . ' component dengan nilai/HPP negatif. Perbaiki valuasi sumber terlebih dahulu agar lot opening tidak menjadi nol.',
                'data' => ['negative_valuation_samples' => array_slice($negativeValuationSamples, 0, 10)],
            ];
        }

        $conflictQuery = $this->db->from('inv_component_monthly_opening')
            ->where('month_key', $nextMonthStart)
            ->group_start()
                ->where('source_type <>', 'OPNAME')
                ->or_where('source_month_key IS NULL', null, false)
                ->or_where('source_month_key <', $monthStart)
                ->or_where('source_month_key >', $monthEnd)
            ->group_end();
        if ($locationType !== '') {
            $conflictQuery->where('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $conflictQuery->where('division_id', $divisionId);
        }
        $manualConflictCount = (int)$conflictQuery->count_all_results();
        if ($manualConflictCount > 0) {
            return [
                'ok' => false,
                'message' => 'Opening bulanan untuk ' . $nextMonth . ' sudah ada dan bukan hasil carry-forward bulan ' . $monthKey . '. Bersihkan dulu data manual yang bentrok.',
            ];
        }

        $this->db->trans_begin();

        $this->db->where('month_key >=', $monthStart);
        $this->db->where('month_key <=', $monthEnd);
        if ($locationType !== '') {
            $this->db->where('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        $this->db->delete('inv_component_monthly_opname');

        $this->db->where('month_key', $nextMonthStart);
        $this->db->where('source_type', 'OPNAME');
        $this->db->where('source_month_key >=', $monthStart);
        $this->db->where('source_month_key <=', $monthEnd);
        if ($locationType !== '') {
            $this->db->where('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        $this->db->delete('inv_component_monthly_opening');

        $hasMonthlyStockTable = $this->db->table_exists('inv_component_monthly_stock');
        $hasLotTable = $this->db->table_exists('inv_component_lot');
        if ($hasLotTable) {
            $this->load->library('ComponentLotManager');
            $lotReady = $this->componentlotmanager->ensureReady();
            if (!($lotReady['ok'] ?? false)) {
                return $lotReady;
            }
            if ($hasMonthlyStockTable) {
                $this->load->library('ComponentStockWriter');
            }
        }

        if ($hasMonthlyStockTable) {
            $this->db->where('month_key', $nextMonthStart)
                ->where('source_mode', 'OPENING_CARRY_FORWARD');
            if ($locationType !== '') {
                $this->db->where('location_type', $locationType);
            }
            if ($divisionId !== null) {
                $this->db->where('division_id', $divisionId);
            }
            $this->db->delete('inv_component_monthly_stock');
        }

        $generatedRows = 0;
        $carriedRows = 0;
        foreach ($aggregated as $row) {
            $this->upsert_by_unique('inv_component_monthly_opname', $row, ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);
            $generatedRows++;

            // Sync ke monthly_stock untuk bulan M (snapshot resmi = hasil generate)
            if ($hasMonthlyStockTable) {
                $this->upsert_by_unique('inv_component_monthly_stock', [
                    'month_key'                    => $monthStart,
                    'location_type'                => (string)$row['location_type'],
                    'division_id'                  => $row['division_id'],
                    'component_id'                 => (int)$row['component_id'],
                    'uom_id'                       => (int)$row['uom_id'],
                    'opening_qty'                  => round((float)($row['opening_qty'] ?? 0), 4),
                    'opening_total_value'          => round((float)($row['opening_total_value'] ?? 0), 2),
                    'in_qty'                       => round((float)($row['in_qty'] ?? 0), 4),
                    'in_total_value'               => round((float)($row['in_total_value'] ?? 0), 2),
                    'out_qty'                      => round((float)($row['out_qty'] ?? 0), 4),
                    'out_total_value'              => round((float)($row['out_total_value'] ?? 0), 2),
                    'waste_qty'                    => round((float)($row['waste_qty'] ?? 0), 4),
                    'waste_total_value'            => round((float)($row['waste_total_value'] ?? 0), 2),
                    'spoil_qty'                    => round((float)($row['spoil_qty'] ?? 0), 4),
                    'spoil_total_value'            => round((float)($row['spoil_total_value'] ?? 0), 2),
                    'adjustment_plus_qty'          => round((float)($row['adjustment_plus_qty'] ?? 0), 4),
                    'adjustment_plus_total_value'  => round((float)($row['adjustment_plus_total_value'] ?? 0), 2),
                    'adjustment_minus_qty'         => round((float)($row['adjustment_minus_qty'] ?? 0), 4),
                    'adjustment_minus_total_value' => round((float)($row['adjustment_minus_total_value'] ?? 0), 2),
                    'closing_qty'                  => round((float)($row['closing_qty'] ?? 0), 4),
                    'avg_cost'                     => round((float)($row['avg_cost'] ?? 0), 6),
                    'total_value'                  => round((float)($row['total_value'] ?? 0), 2),
                    'movement_day_count'           => (int)($row['movement_day_count'] ?? 0),
                    'mutation_count'               => (int)($row['mutation_count'] ?? 0),
                    'source_mode'                  => 'OPNAME_GENERATE',
                ], ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);

                // Cutoff: reconcile FIFO lots for M to match monthly_stock.closing_qty (stock is authority)
                if ($hasLotTable) {
                    $this->componentstockwriter->cutoff_lots_to_monthly_stock(
                        (string)$row['location_type'],
                        $row['division_id'],
                        (int)$row['component_id'],
                        (int)$row['uom_id'],
                        $monthStart
                    );
                }
            }

            if ((float)$row['closing_qty'] <= 0) {
                continue;
            }

            $openingRow = [
                'month_key' => $nextMonthStart,
                'location_type' => (string)$row['location_type'],
                'division_id' => $row['division_id'],
                'component_id' => (int)$row['component_id'],
                'uom_id' => (int)$row['uom_id'],
                'opening_qty' => round((float)$row['closing_qty'], 4),
                'opening_avg_cost' => round((float)$row['avg_cost'], 6),
                'opening_total_value' => round((float)$row['total_value'], 2),
                'source_type' => 'OPNAME',
                'source_month_key' => $opnameMonthKey,
                'source_ref_table' => 'inv_component_monthly_opname',
                'generated_by' => $userId > 0 ? $userId : null,
            ];
            $this->upsert_by_unique('inv_component_monthly_opening', $openingRow, ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);
            $carriedRows++;

            // Sync FIFO lot untuk opening bulan M+1:
            // void lot lama dari sumber ini, lalu daftarkan lot baru sesuai closing qty.
            if ($hasLotTable) {
                $divQ = $this->db->from('inv_component_monthly_opening')
                    ->where('month_key', $nextMonthStart)
                    ->where('location_type', (string)$row['location_type'])
                    ->where('component_id', (int)$row['component_id'])
                    ->where('uom_id', (int)$row['uom_id']);
                if ($row['division_id'] !== null) {
                    $divQ->where('division_id', (int)$row['division_id']);
                } else {
                    $divQ->where('division_id IS NULL', null, false);
                }
                $openingRecord = $divQ->get()->row_array();
                $openingId = (int)($openingRecord['id'] ?? 0);
                if ($openingId > 0) {
                    $this->componentlotmanager->voidInboundLotsBySource('inv_component_monthly_opening', $openingId);
                    $closeCarryForward = $this->componentlotmanager->closeCarryForwardSourceLots([
                        'location_type' => (string)$row['location_type'],
                        'division_id'   => $row['division_id'],
                        'component_id'  => (int)$row['component_id'],
                        'uom_id'        => (int)$row['uom_id'],
                        'reference_date'=> $nextMonthStart,
                    ]);
                    if (!($closeCarryForward['ok'] ?? false)) {
                        $this->db->trans_rollback();
                        return [
                            'ok'      => false,
                            'message' => 'Generate opname gagal saat menutup lot component bulan lama: ' . (string)($closeCarryForward['message'] ?? ''),
                        ];
                    }
                    $residualCutoffLotQuery = $this->db->where('location_type', (string)$row['location_type'])
                        ->where('component_id', (int)$row['component_id'])
                        ->where('uom_id', (int)$row['uom_id'])
                        ->where('receipt_date', $nextMonthStart)
                        ->where('source_table', 'inv_component_monthly_stock')
                        ->where('status', 'OPEN')
                        ->where('qty_balance >', 0.0001);
                    if ($row['division_id'] !== null) {
                        $residualCutoffLotQuery->where('division_id', (int)$row['division_id']);
                    } else {
                        $residualCutoffLotQuery->where('division_id IS NULL', null, false);
                    }
                    $residualCutoffLots = $residualCutoffLotQuery->get('inv_component_lot')->result_array();
                    foreach ($residualCutoffLots as $residualCutoffLot) {
                        $qtyInTotal = round((float)($residualCutoffLot['qty_in_total'] ?? 0), 4);
                        $this->db->where('id', (int)$residualCutoffLot['id'])->update('inv_component_lot', [
                            'qty_out_total' => $qtyInTotal,
                            'qty_balance'   => 0,
                            'status'        => 'CLOSED',
                            'updated_at'    => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $lotResult = $this->componentlotmanager->registerProductionInboundLot([
                        'location_type' => (string)$row['location_type'],
                        'division_id'   => $row['division_id'],
                        'component_id'  => (int)$row['component_id'],
                        'uom_id'        => (int)$row['uom_id'],
                        'qty_in'        => round((float)$row['closing_qty'], 4),
                        'unit_cost'     => round((float)$row['avg_cost'], 6),
                        'receipt_date'  => $nextMonthStart,
                        'source_module' => 'MONTHLY_OPNAME',
                        'source_table'  => 'inv_component_monthly_opening',
                        'source_id'     => $openingId,
                    ]);
                    if (!($lotResult['ok'] ?? false)) {
                        $this->db->trans_rollback();
                        return [
                            'ok'      => false,
                            'message' => 'Generate opname berhasil tapi gagal register lot component opening: ' . (string)($lotResult['message'] ?? ''),
                        ];
                    }
                }
            }

            // Sync opening ke monthly_stock untuk bulan M+1
            if ($hasMonthlyStockTable) {
                $this->upsert_by_unique('inv_component_monthly_stock', [
                    'month_key'                    => $nextMonthStart,
                    'location_type'                => (string)$row['location_type'],
                    'division_id'                  => $row['division_id'],
                    'component_id'                 => (int)$row['component_id'],
                    'uom_id'                       => (int)$row['uom_id'],
                    'opening_qty'                  => round((float)$row['closing_qty'], 4),
                    'opening_total_value'          => round((float)$row['total_value'], 2),
                    'in_qty'                       => 0.0,
                    'in_total_value'               => 0.0,
                    'out_qty'                      => 0.0,
                    'out_total_value'              => 0.0,
                    'waste_qty'                    => 0.0,
                    'waste_total_value'            => 0.0,
                    'spoil_qty'                    => 0.0,
                    'spoil_total_value'            => 0.0,
                    'adjustment_plus_qty'          => 0.0,
                    'adjustment_plus_total_value'  => 0.0,
                    'adjustment_minus_qty'         => 0.0,
                    'adjustment_minus_total_value' => 0.0,
                    'closing_qty'                  => round((float)$row['closing_qty'], 4),
                    'avg_cost'                     => round((float)$row['avg_cost'], 6),
                    'total_value'                  => round((float)$row['total_value'], 2),
                    'movement_day_count'           => 0,
                    'mutation_count'               => 0,
                    'source_mode'                  => 'OPENING_CARRY_FORWARD',
                ], ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);
            }
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PRODUCTION',
                'action_code' => 'COMPONENT_MONTHLY_GENERATE',
                'entity_table' => 'inv_component_monthly_opname',
                'entity_id' => null,
                'transaction_no' => null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'after_payload' => json_encode([
                    'month' => $monthKey,
                    'next_month' => $nextMonth,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'generated_rows' => $generatedRows,
                    'carried_rows' => $carriedRows,
                ]),
                'notes' => 'Generate opname bulanan dan opening otomatis modul component',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal generate carry-forward bulanan component.',
            ];
        }

        $this->db->trans_commit();

        // Siapkan warning jika ada hari minus di tengah bulan (nilai mungkin tidak akurat)
        $midMonthWarnings = [];
        foreach ($midMonthNegative as $comp) {
            $midMonthWarnings[] = [
                'code'          => $comp['component_code'],
                'name'          => $comp['component_name'],
                'location_type' => $comp['location_type'],
                'division_name' => $comp['division_name'],
                'negative_days' => $comp['negative_days'],
                'worst_closing' => $comp['worst_closing'],
                'worst_date'    => $comp['worst_date'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Generate opname bulanan component berhasil. Opening bulan berikutnya juga sudah dibuat.'
                . (!empty($midMonthWarnings) ? ' Perhatian: ' . count($midMonthWarnings) . ' komponen sempat minus di tengah bulan — nilai mungkin tidak akurat, disarankan Repair.' : ''),
            'data' => [
                'month'              => $monthKey,
                'next_month'         => $nextMonth,
                'generated_rows'     => $generatedRows,
                'carried_rows'       => $carriedRows,
                'mid_month_warnings' => $midMonthWarnings,
            ],
        ];
    }

    public function save_component_opening(array $header, array $lines, int $actorEmployeeId): array
    {
        $resolved = $this->resolve_opening_component_context($lines);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($resolved['message'] ?? 'Data opening tidak valid.')];
        }

        $openingMonth = $this->normalize_month_key((string)($header['opening_month'] ?? $header['opening_date'] ?? ''));
        if ($openingMonth === null) {
            return ['ok' => false, 'message' => 'Bulan opening tidak valid.'];
        }
        $openingDate = $openingMonth . '-01';

        $resolvedDivisionId = (int)($resolved['division_id'] ?? 0);
        $locationType = $this->location_group_to_component_location($resolved['component_context'] ?? null, (string)($header['location_type'] ?? ''));
        if ($locationType === null) {
            return ['ok' => false, 'message' => 'Lokasi opening harus REGULER atau EVENT dan sesuai divisi component.'];
        }

        $id = (int)($header['id'] ?? 0);
        $existingOpening = $this->find_component_opening_month_conflict($id, $openingDate, $locationType, $resolvedDivisionId);
        if ($existingOpening) {
            $message = 'Opening bulan ' . $openingMonth . ' untuk lokasi/divisi ini sudah ada di dokumen ' . (string)($existingOpening['opening_no'] ?? ('#' . (int)($existingOpening['id'] ?? 0))) . '.';
            if ($id <= 0 && strtoupper((string)($existingOpening['status'] ?? '')) === 'DRAFT') {
                $message .= ' Lengkapi dokumen draft existing lewat tombol Edit.';
            } elseif ($id <= 0 && strtoupper((string)($existingOpening['status'] ?? '')) === 'POSTED') {
                $message .= ' Dokumen tersebut sudah POSTED. Draftkan lagi untuk edit, atau gunakan adjustment bila hanya perlu menambah selisih stok.';
            }
            return [
                'ok' => false,
                'message' => $message,
                'conflict' => [
                    'id' => (int)($existingOpening['id'] ?? 0),
                    'opening_no' => (string)($existingOpening['opening_no'] ?? ''),
                    'status' => strtoupper((string)($existingOpening['status'] ?? '')),
                    'opening_month' => $openingMonth,
                    'opening_date' => $openingDate,
                    'location_type' => $locationType,
                    'division_id' => $resolvedDivisionId,
                ],
            ];
        }

        $payload = [
            'opening_no' => $id > 0 ? (string)$header['opening_no'] : $this->generate_doc_no('inv_component_opening', 'opening_no', 'ICO', $openingDate),
            'opening_date' => $openingDate,
            'location_type' => $locationType,
            'division_id' => $resolvedDivisionId,
            'notes' => $this->nullable_string($header['notes'] ?? null),
            'created_by' => $id > 0 ? null : ($actorEmployeeId > 0 ? $actorEmployeeId : null),
        ];

        $this->db->trans_start();
        if ($id > 0) {
            $row = $this->get_component_opening($id);
            if (!$row || strtoupper((string)$row['status']) !== 'DRAFT') {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Opening hanya bisa diedit saat status DRAFT.'];
            }
            unset($payload['created_by'], $payload['opening_no']);
            $this->db->where('id', $id)->update('inv_component_opening', $payload);
            $this->db->where('opening_id', $id)->delete('inv_component_opening_line');
        } else {
            $payload['status'] = 'DRAFT';
            $this->db->insert('inv_component_opening', $payload);
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = round((float)($line['opening_qty'] ?? 0), 4);
            if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                continue;
            }
            $componentContext = $this->component_operational_context($componentId);
            if ((int)($componentContext['operational_division_id'] ?? 0) !== $resolvedDivisionId) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Divisi component opening tidak konsisten.'];
            }
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            $this->db->insert('inv_component_opening_line', [
                'opening_id' => $id,
                'line_no' => $lineNo++,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'opening_qty' => $qty,
                'unit_cost' => $unitCost,
                'total_value' => round($qty * $unitCost, 2),
                'note' => $this->nullable_string($line['note'] ?? null),
            ]);
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan opening.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    public function list_component_adjustments(array $filters = [], int $limit = 200): array
    {
        $q          = trim((string)($filters['q'] ?? ''));
        $dateFrom   = trim((string)($filters['date_from'] ?? ''));
        $dateTo     = trim((string)($filters['date_to'] ?? ''));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $locType    = strtoupper(trim((string)($filters['location_type'] ?? '')));

        $this->db->select('h.*, d.name AS division_name');
        $this->db->from('inv_component_adjustment h');
        $this->db->join('mst_operational_division d', 'd.id = h.division_id', 'left');

        if ($dateFrom !== '') $this->db->where('h.adjustment_date >=', $dateFrom);
        if ($dateTo   !== '') $this->db->where('h.adjustment_date <=', $dateTo);
        if ($divisionId > 0)  $this->db->where('h.division_id', $divisionId);
        if ($locType !== '')  $this->apply_component_location_filter('h.location_type', $locType);
        if ($q !== '') {
            $this->db->group_start()
                ->like('h.adjustment_no', $q)
                ->or_like('h.notes', $q)
                ->or_like('d.name', $q)
                ->group_end();
        }
        $this->db->order_by('h.adjustment_date', 'DESC')->order_by('h.id', 'DESC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function get_component_adjustment(int $id): ?array
    {
        $row = $this->db->get_where('inv_component_adjustment', ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function get_component_adjustment_lines(int $adjustmentId): array
    {
        $schemaReady = $this->ensure_component_adjustment_pricing_schema();
        if (!($schemaReady['ok'] ?? false)) {
            return [];
        }

        return $this->db->select('l.*, c.component_name, c.component_code, u.code AS uom_code')
            ->from('inv_component_adjustment_line l')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.adjustment_id', $adjustmentId)
            ->order_by('l.line_no', 'ASC')
            ->get()->result_array();
    }

    public function list_component_adjustment_detail_rows(array $filters = [], int $limit = 300): array
    {
        $schemaReady = $this->ensure_component_adjustment_pricing_schema();
        if (!($schemaReady['ok'] ?? false)) {
            return [];
        }
        if (!$this->db->table_exists('inv_component_adjustment') || !$this->db->table_exists('inv_component_adjustment_line')) {
            return [];
        }

        $q          = trim((string)($filters['q'] ?? ''));
        $dateFrom   = trim((string)($filters['date_from'] ?? ''));
        $dateTo     = trim((string)($filters['date_to'] ?? ''));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $locType    = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $limit = max(1, min(2000, $limit));
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select('h.id AS adjustment_id, h.adjustment_no, h.adjustment_date, h.location_type, h.division_id, h.notes AS header_notes, h.status, h.created_at AS adjustment_created_at, h.posted_at AS adjustment_posted_at, h.updated_at AS adjustment_updated_at', false)
            ->select($divisionNameSelect, false)
            ->select('l.*, c.component_name, c.component_code, c.component_type, u.code AS uom_code')
            ->select("(SELECT GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ' | ') FROM mst_product_recipe pr JOIN mst_product p ON p.id = pr.product_id WHERE pr.component_id = l.component_id) AS related_product_names", false)
            ->from('inv_component_adjustment_line l')
            ->join('inv_component_adjustment h', 'h.id = l.adjustment_id', 'inner')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left');

        if ($dateFrom !== '') $this->db->where('h.adjustment_date >=', $dateFrom);
        if ($dateTo   !== '') $this->db->where('h.adjustment_date <=', $dateTo);
        if ($divisionId > 0)  $this->db->where('h.division_id', $divisionId);
        if ($locType !== '')  $this->apply_component_location_filter('h.location_type', $locType);
        if ($q !== '') {
            $this->db->group_start()
                ->like('h.adjustment_no', $q)
                ->or_like('h.notes', $q)
                ->or_like('l.note', $q)
                ->or_like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->or_like('u.code', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        $rows = $this->db
            ->order_by('h.adjustment_date', 'DESC')
            ->order_by('h.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        return $this->attach_component_adjustment_posting_meta($rows);
    }

    public function save_component_adjustment(array $header, array $lines, int $actorEmployeeId): array
    {
        $schemaReady = $this->ensure_component_adjustment_pricing_schema();
        if (!($schemaReady['ok'] ?? false)) {
            return $schemaReady;
        }

        $period = $this->assert_component_period_open(
            (string)($header['adjustment_date'] ?? ''),
            'menyimpan adjustment component',
            true
        );
        if (!($period['ok'] ?? false)) {
            return $period;
        }
        $header['adjustment_date'] = (string)$period['event_date'];

        $resolvedContext = $this->resolve_adjustment_component_context($lines);
        if (!($resolvedContext['ok'] ?? false)) {
            return $resolvedContext;
        }

        $resolvedDivisionId = !empty($header['division_id']) ? (int)$header['division_id'] : (int)($resolvedContext['division_id'] ?? 0);
        if ($resolvedDivisionId <= 0) {
            return ['ok' => false, 'message' => 'Divisi adjustment tidak berhasil ditentukan. Pilih divisi di header adjustment.'];
        }
        $this->ensure_component_adjustment_reason_helper();

        $id = (int)($header['id'] ?? 0);
        $payload = [
            'adjustment_no' => $id > 0 ? (string)$header['adjustment_no'] : $this->generate_doc_no('inv_component_adjustment', 'adjustment_no', 'ICA', (string)$header['adjustment_date']),
            'adjustment_date' => (string)$header['adjustment_date'],
            'location_type' => strtoupper(trim((string)$header['location_type'])),
            'division_id' => $resolvedDivisionId,
            'notes' => $this->nullable_string($header['notes'] ?? null),
            'created_by' => $id > 0 ? null : ($actorEmployeeId > 0 ? $actorEmployeeId : null),
        ];
        $this->db->trans_start();
        if ($id > 0) {
            $period = $this->assert_component_period_open((string)$header['adjustment_date'], 'mengunci draft component', true);
            if (empty($period['ok'])) { $this->db->trans_rollback(); return $period; }
            $locked = $this->db->query('SELECT * FROM inv_component_adjustment WHERE id = ? FOR UPDATE', [$id]);
            $row = $locked ? $locked->row_array() : null;
            if (!$row || strtoupper((string)$row['status']) !== 'DRAFT') {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Adjustment hanya bisa diedit saat DRAFT.'];
            }
            $existingPeriod = $this->assert_component_period_open(
                (string)($row['adjustment_date'] ?? ''),
                'mengubah adjustment component',
                true
            );
            if (!($existingPeriod['ok'] ?? false)) {
                $this->db->trans_complete();
                return $existingPeriod;
            }
            unset($payload['created_by'], $payload['adjustment_no']);
            $this->db->where('id', $id)->update('inv_component_adjustment', $payload);
            $this->db->where('adjustment_id', $id)->delete('inv_component_adjustment_line');
        } else {
            $payload['status'] = 'DRAFT';
            $this->db->insert('inv_component_adjustment', $payload);
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            if ($componentId <= 0 || $uomId <= 0) {
                continue;
            }
            $qtySpoil = round((float)($line['qty_spoil'] ?? 0), 4);
            $qtyWaste = round((float)($line['qty_waste'] ?? 0), 4);
            $qtyPlus = round((float)($line['qty_adjust_pos'] ?? 0), 4);
            $qtyMinus = round((float)($line['qty_adjust_neg'] ?? 0), 4);
            $this->db->insert('inv_component_adjustment_line', [
                'adjustment_id' => $id,
                'line_no' => $lineNo++,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'selected_lot_id' => !empty($line['selected_lot_id']) ? (int)$line['selected_lot_id'] : null,
                'input_mode' => strtoupper(trim((string)($line['input_mode'] ?? 'DELTA'))) === 'PHYSICAL_COUNT' ? 'PHYSICAL_COUNT' : 'DELTA',
                'available_qty' => round((float)($line['available_qty'] ?? 0), 4),
                'system_qty_snapshot' => isset($line['system_qty_snapshot'])
                    ? round((float)$line['system_qty_snapshot'], 4)
                    : round((float)($line['available_qty'] ?? 0), 4),
                'physical_qty_snapshot' => strtoupper(trim((string)($line['input_mode'] ?? 'DELTA'))) === 'PHYSICAL_COUNT'
                    ? round((float)($line['physical_qty_snapshot'] ?? $line['physical_qty'] ?? 0), 4)
                    : null,
                'settle_open_deficit' => !empty($line['settle_open_deficit']) ? 1 : 0,
                'qty_spoil' => $qtySpoil,
                'spoil_reason_code' => $qtySpoil > 0 ? ($this->normalizeComponentAdjustmentReasonCode((string)($line['spoil_reason_code'] ?? ''), 'SPOILAGE') ?? 'other') : null,
                'qty_waste' => $qtyWaste,
                'waste_reason_code' => $qtyWaste > 0 ? ($this->normalizeComponentAdjustmentReasonCode((string)($line['waste_reason_code'] ?? ''), 'WASTE') ?? 'other') : null,
                'qty_adjust_pos' => $qtyPlus,
                'adjustment_plus_reason_code' => $qtyPlus > 0 ? ($this->normalizeComponentAdjustmentReasonCode((string)($line['adjustment_plus_reason_code'] ?? ''), 'ADJUSTMENT_PLUS') ?? 'other') : null,
                'qty_adjust_neg' => $qtyMinus,
                'adjustment_minus_reason_code' => $qtyMinus > 0 ? ($this->normalizeComponentAdjustmentReasonCode((string)($line['adjustment_minus_reason_code'] ?? ''), 'ADJUSTMENT_MINUS') ?? 'other') : null,
                'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                'note' => $this->nullable_string($line['note'] ?? null),
            ]);
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan adjustment.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    private function ensure_component_adjustment_pricing_schema(): array
    {
        if (!$this->db->table_exists('inv_component_adjustment_line')) {
            return ['ok' => false, 'message' => 'Tabel detail adjustment component tidak ditemukan.'];
        }

        $requiredColumns = [
            'selected_lot_id',
            'input_mode',
            'system_qty_snapshot',
            'physical_qty_snapshot',
            'settle_open_deficit',
            'deficit_settled_qty',
            'unit_cost',
            'waste_reason_code',
            'spoil_reason_code',
            'adjustment_plus_reason_code',
            'adjustment_minus_reason_code',
        ];

        foreach ($requiredColumns as $column) {
            if (!$this->db->field_exists($column, 'inv_component_adjustment_line')) {
                return [
                    'ok' => false,
                    'message' => 'Schema adjustment component belum lengkap. Jalankan SQL 2026-08-13a_inventory_active_month_deficit_period_lock_foundation.sql lalu 2026-08-19d_inventory_adjustment_recon_settlement_contract.sql.',
                ];
            }
        }

        return ['ok' => true];
    }

    /**
     * Keep component drafts, posting, and VOID on the same active-month rule.
     * A closed month must not receive a late draft that can later be posted.
     */
    private function assert_component_period_open(string $eventDate, string $operation, bool $rejectFuture = false): array
    {
        $eventDate = trim($eventDate);
        $parsed = DateTime::createFromFormat('Y-m-d', $eventDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $eventDate) {
            return ['ok' => false, 'message' => 'Tanggal ' . $operation . ' tidak valid.'];
        }
        if ($rejectFuture && $eventDate > date('Y-m-d')) {
            return [
                'ok' => false,
                'message' => 'Tanggal ' . $operation . ' tidak boleh melewati hari ini karena posting akan langsung mengubah stok component live.',
            ];
        }

        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->load->library('InventoryPeriodGuard');
            $period = $this->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $eventDate,
                null,
                'Automatic component period from ' . $operation
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        return ['ok' => true, 'event_date' => $eventDate];
    }

    public function list_component_batches(array $filters = [], int $limit = 200): array
    {
        $q          = trim((string)($filters['q'] ?? ''));
        $dateFrom   = trim((string)($filters['date_from'] ?? ''));
        $dateTo     = trim((string)($filters['date_to'] ?? ''));
        $divisionId    = !empty($filters['division_id']) ? (int)$filters['division_id'] : 0;
        $locType       = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));

        $this->db->select('b.*, c.component_name, c.component_code, d.name AS division_name, u.code AS uom_code');
        $this->db->from('inv_component_batch b');
        $this->db->join('mst_component c', 'c.id = b.component_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = b.division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = b.output_uom_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('b.batch_no', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        if ($dateFrom !== '') {
            $this->db->where('b.batch_date >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $this->db->where('b.batch_date <=', $dateTo);
        }
        if ($divisionId > 0) {
            $this->db->where('b.division_id', $divisionId);
        }
        if ($locType === 'REGULER') {
            $this->db->where_in('b.location_type', ['BAR', 'KITCHEN', 'ROASTERY']);
        } elseif ($locType === 'EVENT') {
            $this->db->where_in('b.location_type', ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT']);
        } elseif ($locType !== '') {
            $this->db->where('b.location_type', $locType);
        }
        if ($componentType !== '') {
            $this->db->where('c.component_type', $componentType);
        }
        $this->db->order_by('b.batch_date', 'DESC')->order_by('b.id', 'DESC')->limit(max(1, $limit));
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $row['can_void'] = strtoupper((string)($row['status'] ?? '')) !== 'POSTED';
            $row['void_block_reason'] = '';
            $row['inline_summary'] = $this->component_batch_inline_summary((int)($row['id'] ?? 0));
            if (strtoupper((string)($row['status'] ?? '')) === 'POSTED') {
                $usage = $this->component_batch_usage_detail((int)($row['id'] ?? 0), true);
                $row['can_void'] = !empty($usage['can_void']);
                $row['void_block_reason'] = (string)($usage['block_reason'] ?? '');
                $row['void_projection'] = is_array($usage['void_projection'] ?? null) ? $usage['void_projection'] : [];
                $row['usage_count'] = (int)($usage['summary']['usage_count'] ?? 0);
            }
        }
        unset($row);
        return $rows;
    }

    private function component_batch_inline_summary(int $batchId): array
    {
        if ($batchId <= 0 || !$this->db->table_exists('inv_component_batch_input')) {
            return [
                'has_inline' => false,
                'outputs' => [],
                'usages' => [],
            ];
        }

        $inputs = $this->get_component_batch_inputs($batchId);
        $outputs = [];
        $usages = [];
        foreach ($inputs as $input) {
            $planRole = strtoupper(trim((string)($input['plan_role'] ?? '')));
            if ($planRole !== 'INLINE_OUTPUT' && $planRole !== 'INLINE_COMPONENT_USAGE') {
                continue;
            }

            $componentId = (int)($input['component_id'] ?? 0);
            $uomCode = trim((string)($input['uom_code'] ?? ''));
            $qty = round((float)($input['qty'] ?? 0), 2);
            $entry = [
                'component_id' => $componentId,
                'component_name' => (string)($input['component_name'] ?? '-'),
                'qty' => $qty,
                'uom_code' => $uomCode,
            ];

            if ($planRole === 'INLINE_OUTPUT') {
                $outputs[$componentId > 0 ? (string)$componentId : md5(json_encode($entry))] = $entry;
                continue;
            }

            $usages[$componentId > 0 ? (string)$componentId : md5(json_encode($entry))] = $entry;
        }

        return [
            'has_inline' => !empty($outputs) || !empty($usages),
            'outputs' => array_values($outputs),
            'usages' => array_values($usages),
        ];
    }

    public function component_batch_usage_detail(int $batchId, bool $summaryOnly = false): array
    {
        $header = $this->db->select('b.*, c.component_code, c.component_name, c.component_type, u.code AS uom_code, d.name AS division_name')
            ->from('inv_component_batch b')
            ->join('mst_component c', 'c.id = b.component_id', 'left')
            ->join('mst_uom u', 'u.id = b.output_uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = b.division_id', 'left')
            ->where('b.id', $batchId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch tidak ditemukan.'];
        }

        $movementUsages = [];
        $batchUsages = [];
        $lotIssueUsages = [];
        $traceRows = [];
        $materialInputs = [];
        $blockReason = '';
        $canVoid = strtoupper((string)($header['status'] ?? '')) === 'POSTED';
        $voidProjection = [
            'available' => false,
            'current_global_qty' => 0.0,
            'rollback_qty' => 0.0,
            'projected_global_qty_after_void' => 0.0,
            'would_go_negative' => false,
        ];

        if ($canVoid && $this->db->table_exists('inv_component_movement_log')) {
            $outputMovement = $this->db->from('inv_component_movement_log')
                ->where('source_table', 'inv_component_batch')
                ->where('source_id', $batchId)
                ->where('component_id', (int)($header['component_id'] ?? 0))
                ->where('movement_type', 'PRODUCTION_IN')
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();

            if ($outputMovement) {
                $currentBalance = $this->load_component_current_balance_state(
                    (string)($header['location_type'] ?? ''),
                    !empty($header['division_id']) ? (int)$header['division_id'] : null,
                    (int)($header['component_id'] ?? 0),
                    (int)($header['output_uom_id'] ?? 0)
                );
                $currentGlobalQty = round((float)($currentBalance['qty_on_hand'] ?? 0), 4);
                $rollbackQty = round((float)($outputMovement['qty_in'] ?? 0), 4);
                $projectedGlobalQtyAfterVoid = round($currentGlobalQty - $rollbackQty, 4);
                $voidProjection = [
                    'available' => true,
                    'current_global_qty' => $currentGlobalQty,
                    'rollback_qty' => $rollbackQty,
                    'projected_global_qty_after_void' => $projectedGlobalQtyAfterVoid,
                    'would_go_negative' => $projectedGlobalQtyAfterVoid < -0.0001,
                ];

                $divisionNullSafe = !empty($header['division_id']) ? (string)(int)$header['division_id'] : 'NULL';
                $movementUsages = $this->db->select('id, movement_no, movement_date, movement_type, source_module, source_table, source_id, notes, qty_out')
                    ->from('inv_component_movement_log')
                    ->where('location_type', (string)($header['location_type'] ?? ''))
                    ->where('division_id <=> ' . $divisionNullSafe, null, false)
                    ->where('component_id', (int)($header['component_id'] ?? 0))
                    ->where('uom_id', (int)($header['output_uom_id'] ?? 0))
                    ->where('qty_out >', 0)
                    ->order_by('movement_date', 'ASC')
                    ->order_by('id', 'ASC');
                $this->apply_component_movement_after_anchor((string)($outputMovement['movement_date'] ?? ($header['batch_date'] ?? '')), (int)($outputMovement['id'] ?? 0));
                $movementUsages = $this->db->get()
                    ->result_array();
                foreach ($movementUsages as &$movementUsage) {
                    $movementUsage['movement_type_label'] = $this->component_movement_type_label((string)($movementUsage['movement_type'] ?? ''));
                }
                unset($movementUsage);
                if (!empty($movementUsages)) {
                    $canVoid = false;
                    $first = $movementUsages[0];
                    $blockReason = 'Output batch sudah terpakai di movement ' . (string)($first['movement_no'] ?? '-') . ' (' . (string)($first['movement_date'] ?? '-') . ').';
                }
            }
        }

        if (!$summaryOnly && $this->db->table_exists('inv_component_movement_log')) {
            $traceSelect = 'm.id, m.movement_no, m.movement_date, m.movement_type, m.component_id, c.component_name, c.component_type, u.code AS uom_code, m.qty_in, m.qty_out, m.unit_cost, m.total_cost, m.notes';
            $hasBatchInput = $this->db->table_exists('inv_component_batch_input');
            $hasPlanRole = $hasBatchInput && $this->db->field_exists('plan_role', 'inv_component_batch_input');
            if ($hasBatchInput) {
                $traceSelect .= $hasPlanRole
                    ? ', bi.plan_role, bi.line_no AS batch_line_no'
                    : ', NULL AS plan_role, bi.line_no AS batch_line_no';
                $this->db->join('inv_component_batch_input bi', 'bi.id = m.source_line_id', 'left');
            } else {
                $traceSelect .= ', NULL AS plan_role, NULL AS batch_line_no';
            }

            $traceRows = $this->db->select($traceSelect, false)
                ->from('inv_component_movement_log m')
                ->join('mst_component c', 'c.id = m.component_id', 'left')
                ->join('mst_uom u', 'u.id = m.uom_id', 'left')
                ->where('m.source_table', 'inv_component_batch')
                ->where('m.source_id', $batchId)
                ->where_in('m.movement_type', ['PRODUCTION_IN', 'PRODUCTION_OUT'])
                ->order_by('m.id', 'ASC')
                ->get()
                ->result_array();
            foreach ($traceRows as &$traceRow) {
                $traceRow['movement_type_label'] = $this->component_movement_type_label((string)($traceRow['movement_type'] ?? ''));
                $traceRow['trace_label'] = $this->component_batch_trace_label($traceRow, (int)($header['component_id'] ?? 0));
            }
            unset($traceRow);
        }

        if ($this->db->table_exists('inv_component_batch_input')) {
            if (!$summaryOnly) {
                $inputRows = $this->get_component_batch_inputs($batchId);
                foreach ($inputRows as $inputRow) {
                    if (strtoupper(trim((string)($inputRow['source_kind'] ?? ''))) !== 'MATERIAL') {
                        continue;
                    }
                    $label = trim((string)($inputRow['item_name'] ?? ''));
                    if ($label === '') {
                        $label = trim((string)($inputRow['material_name'] ?? ''));
                    }
                    $materialInputs[] = [
                        'line_no' => (int)($inputRow['line_no'] ?? 0),
                        'plan_role' => (string)($inputRow['plan_role'] ?? ''),
                        'item_id' => !empty($inputRow['item_id']) ? (int)$inputRow['item_id'] : null,
                        'material_id' => !empty($inputRow['material_id']) ? (int)$inputRow['material_id'] : null,
                        'material_label' => $label !== '' ? $label : '-',
                        'qty' => round((float)($inputRow['qty'] ?? 0), 4),
                        'uom_code' => (string)($inputRow['uom_code'] ?? ''),
                        'unit_cost' => round((float)($inputRow['unit_cost'] ?? 0), 6),
                        'total_cost' => round((float)($inputRow['total_cost'] ?? 0), 2),
                        'fifo_issue_no' => (string)($inputRow['fifo_issue_no'] ?? ''),
                        'notes' => (string)($inputRow['notes'] ?? ''),
                    ];
                }
            }

            $divisionNullSafe = !empty($header['division_id']) ? (string)(int)$header['division_id'] : 'NULL';
            $this->db->select('b.id, b.batch_no, b.batch_date, b.status, mc.component_code AS output_component_code, mc.component_name AS output_component_name, i.qty, u.code AS uom_code')
                ->from('inv_component_batch_input i')
                ->join('inv_component_batch b', 'b.id = i.batch_id', 'inner')
                ->join('mst_component mc', 'mc.id = b.component_id', 'left')
                ->join('mst_uom u', 'u.id = i.uom_id', 'left')
                ->where('b.id <>', $batchId)
                ->where('b.status', 'POSTED')
                ->where('i.source_kind', 'COMPONENT')
                ->where('i.component_id', (int)($header['component_id'] ?? 0))
                ->where('b.location_type', (string)($header['location_type'] ?? ''))
                ->where('b.division_id <=> ' . $divisionNullSafe, null, false)
                ->order_by('b.batch_date', 'ASC')
                ->order_by('b.id', 'ASC');
            $batchUsages = $this->db->get()->result_array();
            if ($canVoid && !empty($batchUsages)) {
                $canVoid = false;
                $first = $batchUsages[0];
                $blockReason = 'Output batch sudah dipakai di batch ' . (string)($first['batch_no'] ?? '-') . ' tanggal ' . (string)($first['batch_date'] ?? '-') . '.';
            }
        }

        if ($this->db->table_exists('inv_component_lot') && $this->db->table_exists('inv_component_lot_issue_log') && $this->db->table_exists('inv_component_lot_issue_line')) {
            $lotIssueUsages = $this->db->select('il.id, il.issue_no, il.issue_date, il.source_module, il.source_table, il.source_id, il.notes, SUM(li.qty_out) AS qty_out', false)
                ->from('inv_component_lot l')
                ->join('inv_component_lot_issue_line li', 'li.lot_id = l.id', 'inner')
                ->join('inv_component_lot_issue_log il', 'il.id = li.issue_id', 'inner')
                ->where('l.source_table', 'inv_component_batch')
                ->where('l.source_id', $batchId)
                ->where('il.status', 'POSTED')
                ->group_by(['il.id', 'il.issue_no', 'il.issue_date', 'il.source_module', 'il.source_table', 'il.source_id', 'il.notes'])
                ->order_by('il.issue_date', 'ASC')
                ->order_by('il.id', 'ASC')
                ->get()
                ->result_array();
            if ($canVoid && !empty($lotIssueUsages)) {
                $canVoid = false;
                $first = $lotIssueUsages[0];
                $blockReason = 'Output batch sudah dipakai pada issue FIFO ' . (string)($first['issue_no'] ?? '-') . ' tanggal ' . (string)($first['issue_date'] ?? '-') . '.';
            }
        }

        $inlineOutputCount = 0;
        $inlineUsageCount = 0;
        foreach ($traceRows as $traceRow) {
            $traceRole = strtoupper(trim((string)($traceRow['plan_role'] ?? '')));
            if ($traceRole === 'INLINE_OUTPUT') {
                $inlineOutputCount++;
            } elseif ($traceRole === 'INLINE_COMPONENT_USAGE') {
                $inlineUsageCount++;
            }
        }

        $result = [
            'ok' => true,
            'header' => $header,
            'can_void' => $canVoid,
            'block_reason' => $blockReason,
            'void_projection' => $voidProjection,
            'summary' => [
                'movement_usage_count' => count($movementUsages),
                'batch_usage_count' => count($batchUsages),
                'lot_issue_usage_count' => count($lotIssueUsages),
                'trace_count' => count($traceRows),
                'material_input_count' => count($materialInputs),
                'inline_output_count' => $inlineOutputCount,
                'inline_usage_count' => $inlineUsageCount,
                'usage_count' => count($movementUsages) + count($batchUsages) + count($lotIssueUsages),
            ],
        ];
        if (!$summaryOnly) {
            $result['movement_usages'] = $movementUsages;
            $result['batch_usages'] = $batchUsages;
            $result['lot_issue_usages'] = $lotIssueUsages;
            $result['trace_rows'] = $traceRows;
            $result['material_inputs'] = $materialInputs;
        }

        return $result;
    }

    public function component_lot_usage_detail(int $lotId): array
    {
        if ($lotId <= 0 || !$this->db->table_exists('inv_component_lot')) {
            return ['ok' => false, 'message' => 'Lot component tidak ditemukan.'];
        }

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionCodeSelect = $divisionCodeColumn !== null ? ('d.' . $divisionCodeColumn . ' AS division_code') : 'CAST(l.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $header = $this->db
            ->select('l.*')
            ->select($divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select('c.component_code, c.component_name, c.component_type, u.code AS uom_code', false)
            ->from('inv_component_lot l')
            ->join('mst_operational_division d', 'd.id = l.division_id', 'left')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.id', $lotId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$header) {
            return ['ok' => false, 'message' => 'Lot component tidak ditemukan.'];
        }

        if (!$this->db->table_exists('inv_component_lot_issue_log') || !$this->db->table_exists('inv_component_lot_issue_line')) {
            return [
                'ok' => true,
                'header' => $header,
                'summary' => ['usage_count' => 0, 'qty_out_total' => 0.0, 'cost_total' => 0.0],
                'rows' => [],
            ];
        }

        $rows = $this->db
            ->select('li.id, li.issue_id, li.lot_id, li.qty_out, li.unit_cost, li.total_cost')
            ->select('il.issue_no, il.issue_date, il.source_module, il.source_table, il.source_id, il.source_line_id, il.notes, il.status', false)
            ->from('inv_component_lot_issue_line li')
            ->join('inv_component_lot_issue_log il', 'il.id = li.issue_id', 'inner')
            ->where('li.lot_id', $lotId)
            ->order_by('il.issue_date', 'DESC')
            ->order_by('il.id', 'DESC')
            ->order_by('li.id', 'DESC')
            ->get()
            ->result_array();

        $summary = [
            'usage_count' => count($rows),
            'qty_out_total' => 0.0,
            'cost_total' => 0.0,
        ];
        foreach ($rows as $row) {
            $summary['qty_out_total'] += (float)($row['qty_out'] ?? 0);
            $summary['cost_total'] += (float)($row['total_cost'] ?? 0);
        }

        return [
            'ok' => true,
            'header' => $header,
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function get_component_batch(int $id): ?array
    {
        $row = $this->db->get_where('inv_component_batch', ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function get_component_batch_inputs(int $batchId): array
    {
        $this->db->select('i.*, c.component_name, c.component_code, m.material_name, u.code AS uom_code');
        if ($this->db->field_exists('item_id', 'inv_component_batch_input')) {
            $this->db->select('mi.item_name', false);
        }

        $this->db->from('inv_component_batch_input i')
            ->join('mst_component c', 'c.id = i.component_id', 'left')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->join('mst_uom u', 'u.id = i.uom_id', 'left');

        if ($this->db->field_exists('item_id', 'inv_component_batch_input')) {
            $this->db->join('mst_item mi', 'mi.id = i.item_id', 'left');
        }

        return $this->db
            ->where('i.batch_id', $batchId)
            ->order_by('i.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function component_batch_preview(array $header): array
    {
        $componentId = (int)($header['component_id'] ?? 0);
        $batchDate = trim((string)($header['batch_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $batchDate)) {
            $batchDate = date('Y-m-d');
        }
        $component = $this->component_batch_component_context($componentId);
        if (!$component) {
            return ['ok' => false, 'message' => 'Output component batch tidak ditemukan.'];
        }
        if ((int)($component['operational_division_id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Output component batch wajib memiliki divisi operasional.'];
        }

        $locationType = $this->location_group_to_component_location($component, (string)($header['location_type'] ?? ''));
        if ($locationType === null) {
            return ['ok' => false, 'message' => 'Lokasi batch harus REGULER atau EVENT dan sesuai divisi output component.'];
        }

        $baseOutputQty = $this->component_batch_base_output_qty($component);
        $formulaLines = $this->list_component_formulas($componentId);
        if (empty($formulaLines)) {
            return ['ok' => false, 'message' => 'Formula component belum tersedia.'];
        }

        $referenceOptions = [];
        foreach ($formulaLines as $line) {
            if (strtoupper((string)($line['line_type'] ?? '')) !== 'MATERIAL') {
                continue;
            }
            $referenceOptions[] = [
                'line_no' => (int)($line['line_no'] ?? 0),
                'material_id' => (int)(($line[$this->formula_material_column()] ?? 0) ?: 0),
                'label' => (string)($line['material_name'] ?? '-'),
                'base_qty' => round((float)($line['qty'] ?? 0), 4),
                'uom_id' => (int)($line['uom_id'] ?? 0),
                'uom_code' => (string)($line['uom_code'] ?? ''),
            ];
        }

        $scalingMode = strtoupper(trim((string)($header['scaling_mode'] ?? 'BATCH')));
        if (!in_array($scalingMode, ['BATCH', 'REFERENCE'], true)) {
            $scalingMode = 'BATCH';
        }
        $referenceMeta = null;
        if ($scalingMode === 'REFERENCE') {
            if (empty($referenceOptions)) {
                return ['ok' => false, 'message' => 'Formula ini belum punya bahan baku acuan untuk mode produksi berdasarkan bahan.', 'reference_options' => []];
            }
            $referenceLineNo = (int)($header['reference_line_no'] ?? 0);
            $referenceActualQty = round((float)($header['reference_actual_qty'] ?? 0), 4);
            $referenceMeta = null;
            foreach ($referenceOptions as $option) {
                if ((int)$option['line_no'] === $referenceLineNo) {
                    $referenceMeta = $option;
                    break;
                }
            }
            if (!$referenceMeta) {
                return ['ok' => false, 'message' => 'Bahan acuan tidak ditemukan di formula.', 'reference_options' => $referenceOptions];
            }
            if ($referenceActualQty <= 0) {
                return ['ok' => false, 'message' => 'Qty aktual bahan acuan harus lebih dari 0.', 'reference_options' => $referenceOptions];
            }
            $scaleFactor = round($referenceActualQty / max((float)($referenceMeta['base_qty'] ?? 0), 0.0001), 6);
            $batchCount = $scaleFactor;
            $outputQty = round($baseOutputQty * $scaleFactor, 4);
            $referenceMeta['actual_qty'] = $referenceActualQty;
        } else {
            $batchCount = round((float)($header['batch_count'] ?? 0), 4);
            if ($batchCount <= 0) {
                $batchCount = 1.0;
            }
            $scaleFactor = $batchCount;
            $outputQty = round($baseOutputQty * $scaleFactor, 4);
        }
        if ($outputQty <= 0) {
            return ['ok' => false, 'message' => 'Qty output batch harus lebih dari 0.'];
        }

        $state = [
            'batch_date' => $batchDate,
            'line_no' => 1,
            'preview_lines' => [],
            'posting_lines' => [],
            'material_stock' => [],
            'component_stock' => [],
            'issues' => [],
            'material_issue_keys' => [],
            'component_issue_keys' => [],
        ];

        $plan = $this->plan_component_batch_component(
            $component,
            $outputQty,
            $locationType,
            (int)$component['operational_division_id'],
            $state,
            [],
            0,
            false,
            null
        );

        if (!($plan['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($plan['message'] ?? 'Preview batch gagal diproses.')];
        }

        $hasShortage = !empty($state['issues']) || !empty($plan['has_shortage']);
        $directMaterialCount = 0;
        $directComponentCount = 0;
        $inlineComponentCount = 0;
        foreach ($state['preview_lines'] as $line) {
            $role = strtoupper((string)($line['plan_role'] ?? ''));
            if ($role === 'MATERIAL_USAGE') {
                $directMaterialCount++;
            } elseif ($role === 'COMPONENT_USAGE') {
                $directComponentCount++;
            } elseif ($role === 'INLINE_OUTPUT') {
                $inlineComponentCount++;
            }
        }

        return [
            'ok' => true,
            'component' => [
                'id' => (int)($component['id'] ?? 0),
                'component_code' => (string)($component['component_code'] ?? ''),
                'component_name' => (string)($component['component_name'] ?? ''),
                'component_type' => (string)($component['component_type'] ?? ''),
                'division_id' => (int)($component['operational_division_id'] ?? 0),
                'division_code' => (string)($component['division_code'] ?? ''),
                'division_name' => (string)($component['division_name'] ?? ''),
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_code' => (string)($component['uom_code'] ?? ''),
                'uom_name' => (string)($component['uom_name'] ?? ''),
            ],
            'batch_date' => $batchDate,
            'location_type' => $locationType,
            'location_group' => $this->location_to_group($locationType),
            'scaling_mode' => $scalingMode,
            'batch_count' => round($batchCount, 4),
            'reference' => $referenceMeta,
            'reference_options' => $referenceOptions,
            'base_output_qty' => round($baseOutputQty, 4),
            'output_qty' => round($outputQty, 4),
            'scale_factor' => round($scaleFactor, 6),
            'summary' => [
                'direct_material_count' => $directMaterialCount,
                'direct_component_count' => $directComponentCount,
                'inline_component_count' => $inlineComponentCount,
                'direct_input_cost' => round((float)($plan['direct_input_cost'] ?? 0), 2),
                'variable_cost_mode' => (string)($plan['variable_cost_mode'] ?? 'DEFAULT'),
                'variable_cost_label' => (string)($plan['variable_cost_label'] ?? 'Default'),
                'variable_cost_pct' => round((float)($plan['variable_cost_pct'] ?? 0), 4),
                'variable_cost_total' => round((float)($plan['variable_cost_total'] ?? 0), 2),
                'total_input_cost' => round((float)($plan['total_input_cost'] ?? 0), 2),
                'unit_cost' => round((float)($plan['unit_cost'] ?? 0), 6),
                'has_shortage' => $hasShortage,
                'shortage_count' => count($state['issues']),
            ],
            'issues' => array_values($state['issues']),
            'lines' => array_values($state['preview_lines']),
            'posting_lines' => array_values($state['posting_lines']),
        ];
    }

    public function save_component_batch(array $header, array $inputs, int $actorEmployeeId): array
    {
        $schemaReady = $this->ensure_component_batch_input_trace_schema();
        if (empty($schemaReady['ok'])) {
            return $schemaReady;
        }

        $period = $this->assert_component_period_open(
            (string)($header['batch_date'] ?? ''),
            'menyimpan batch component'
        );
        if (!($period['ok'] ?? false)) {
            return $period;
        }
        $header['batch_date'] = (string)$period['event_date'];

        $outputComponentId = (int)($header['component_id'] ?? 0);
        $outputContext = $this->component_operational_context($outputComponentId);
        if (!$outputContext || (int)($outputContext['operational_division_id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Output component batch wajib memiliki divisi operasional.'];
        }

        $resolvedDivisionId = (int)$outputContext['operational_division_id'];
        $locationType = $this->location_group_to_component_location($outputContext, (string)($header['location_type'] ?? ''));
        if ($locationType === null) {
            return ['ok' => false, 'message' => 'Lokasi batch harus REGULER atau EVENT dan sesuai divisi output component.'];
        }

        $preview = $this->component_batch_preview([
            'component_id' => $outputComponentId,
            'location_type' => $locationType,
            'batch_date' => (string)($header['batch_date'] ?? date('Y-m-d')),
            'scaling_mode' => (string)($header['scaling_mode'] ?? 'BATCH'),
            'batch_count' => (float)($header['batch_count'] ?? 0),
            'reference_line_no' => (int)($header['reference_line_no'] ?? 0),
            'reference_actual_qty' => (float)($header['reference_actual_qty'] ?? 0),
        ]);
        if (!($preview['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($preview['message'] ?? 'Preview batch gagal diproses.')];
        }
        if (!empty($preview['summary']['has_shortage'])) {
            $issues = array_values(array_filter((array)($preview['issues'] ?? []), 'strlen'));
            return ['ok' => false, 'message' => !empty($issues) ? $issues[0] : 'Bahan atau component tidak cukup untuk batch ini.'];
        }
        $plannedInputs = array_values(array_filter((array)($preview['posting_lines'] ?? []), static function ($line): bool {
            return is_array($line) && round((float)($line['qty'] ?? 0), 4) > 0;
        }));
        if (empty($plannedInputs)) {
            return ['ok' => false, 'message' => 'Formula batch belum menghasilkan kebutuhan input yang valid.'];
        }

        $id = (int)($header['id'] ?? 0);
        $payload = [
            'batch_no' => $id > 0 ? (string)$header['batch_no'] : $this->generate_doc_no('inv_component_batch', 'batch_no', 'ICB', (string)$header['batch_date']),
            'batch_date' => (string)$header['batch_date'],
            'location_type' => $locationType,
            'division_id' => $resolvedDivisionId,
            'component_id' => $outputComponentId,
            'output_qty' => round((float)($preview['output_qty'] ?? $header['output_qty']), 4),
            'output_uom_id' => (int)($preview['component']['uom_id'] ?? $header['output_uom_id']),
            'notes' => $this->nullable_string($header['notes'] ?? null),
            'created_by' => $id > 0 ? null : ($actorEmployeeId > 0 ? $actorEmployeeId : null),
        ];
        $this->db->trans_start();
        if ($id > 0) {
            $row = $this->get_component_batch($id);
            if (!$row || strtoupper((string)$row['status']) !== 'DRAFT') {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Batch hanya bisa diedit saat DRAFT.'];
            }
            $existingPeriod = $this->assert_component_period_open(
                (string)($row['batch_date'] ?? ''),
                'mengubah batch component'
            );
            if (!($existingPeriod['ok'] ?? false)) {
                $this->db->trans_complete();
                return $existingPeriod;
            }
            unset($payload['created_by'], $payload['batch_no']);
            $this->db->where('id', $id)->update('inv_component_batch', $payload);
            $this->db->where('batch_id', $id)->delete('inv_component_batch_input');
        } else {
            $payload['status'] = 'DRAFT';
            $this->db->insert('inv_component_batch', $payload);
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        $totalInputCost = 0.0;
        foreach ($plannedInputs as $line) {
            $planRole = strtoupper(trim((string)($line['plan_role'] ?? 'INPUT')));
            $sourceKind = strtoupper(trim((string)($line['source_kind'] ?? '')));
            $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
            $componentId = !empty($line['component_id']) ? (int)$line['component_id'] : null;
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($sourceKind === '' || $uomId <= 0 || $qty <= 0) {
                continue;
            }
            if ($sourceKind === 'COMPONENT') {
                $componentContext = $this->component_operational_context((int)$componentId);
                $lineDivId = !empty($line['division_id']) ? (int)$line['division_id'] : $resolvedDivisionId;
                if ((int)($componentContext['operational_division_id'] ?? 0) !== $lineDivId) {
                    $this->db->trans_complete();
                    return ['ok' => false, 'message' => 'Input component batch harus berasal dari divisi yang sesuai dengan formula.'];
                }
            }
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            $lineCost = round($qty * $unitCost, 2);
            if ($planRole !== 'INLINE_OUTPUT' && $planRole !== 'INLINE_COMPONENT_USAGE') {
                $totalInputCost += $lineCost;
            }
            $lineDivId = !empty($line['division_id']) ? (int)$line['division_id'] : null;
            $row = [
                'batch_id' => $id,
                'line_no' => $lineNo++,
                'plan_role' => $this->db->field_exists('plan_role', 'inv_component_batch_input') ? $planRole : null,
                'source_kind' => $sourceKind,
                'division_id' => $this->db->field_exists('division_id', 'inv_component_batch_input') ? ($lineDivId > 0 ? $lineDivId : null) : null,
                'item_id' => $this->db->field_exists('item_id', 'inv_component_batch_input') ? $itemId : null,
                'material_id' => $materialId,
                'component_id' => $componentId,
                'qty' => $qty,
                'uom_id' => $uomId,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'notes' => $this->nullable_string($line['notes'] ?? null),
            ];
            if (!$this->db->field_exists('plan_role', 'inv_component_batch_input')) {
                unset($row['plan_role']);
            }
            if (!$this->db->field_exists('division_id', 'inv_component_batch_input')) {
                unset($row['division_id']);
            }
            if (!$this->db->field_exists('item_id', 'inv_component_batch_input')) {
                unset($row['item_id']);
            }
            $this->db->insert('inv_component_batch_input', $row);
        }
        $outputQty = round((float)$payload['output_qty'], 4);
        $storedTotalInputCost = round((float)($preview['summary']['total_input_cost'] ?? $totalInputCost), 2);
        $unitCostOut = $outputQty > 0
            ? round((float)($preview['summary']['unit_cost'] ?? ($storedTotalInputCost / $outputQty)), 6)
            : 0.0;
        $this->db->where('id', $id)->update('inv_component_batch', [
            'total_input_cost' => $storedTotalInputCost,
            'unit_cost' => $unitCostOut,
        ]);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan batch.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Batch input must never alter schema during a production request.
     * The required fields are installed by the dedicated SQL migration.
     */
    private function ensure_component_batch_input_trace_schema(): array
    {
        $table = 'inv_component_batch_input';
        if (!$this->db->table_exists($table)) {
            return [
                'ok' => false,
                'message' => 'Tabel rincian input batch belum tersedia. Jalankan migration 2026-08-17b_component_batch_input_trace_schema.sql terlebih dahulu.',
            ];
        }

        $missing = [];
        foreach (['plan_role', 'item_id', 'fifo_issue_id', 'fifo_issue_no', 'division_id'] as $field) {
            if (!$this->db->field_exists($field, $table)) {
                $missing[] = $field;
            }
        }
        if ($missing) {
            return [
                'ok' => false,
                'message' => 'Struktur rincian input batch belum lengkap (' . implode(', ', $missing) . '). Jalankan migration 2026-08-17b_component_batch_input_trace_schema.sql terlebih dahulu.',
            ];
        }

        return ['ok' => true];
    }

    private function component_batch_component_context(int $componentId): ?array
    {
        if ($componentId <= 0) {
            return null;
        }

        $divisionNameColumn = $this->division_name_column();
        $divisionCodeColumn = $this->division_code_column();
        $select = [
            'c.id',
            'c.component_code',
            'c.component_name',
            'c.component_type',
            'c.operational_division_id',
            'c.uom_id',
            'c.variable_cost_mode',
            'c.variable_cost_percent',
            'u.code AS uom_code',
            'u.name AS uom_name',
        ];
        if ($this->db->field_exists('std_batch_qty', 'mst_component')) {
            $select[] = 'c.std_batch_qty';
        }
        if ($this->db->field_exists('yield_qty', 'mst_component')) {
            $select[] = 'c.yield_qty';
        }
        if ($this->db->field_exists('yield_percent', 'mst_component')) {
            $select[] = 'c.yield_percent';
        }
        if ($divisionCodeColumn !== null) {
            $select[] = 'd.' . $divisionCodeColumn . ' AS division_code';
        }
        if ($divisionNameColumn !== null) {
            $select[] = 'd.' . $divisionNameColumn . ' AS division_name';
        }

        $row = $this->db->select(implode(', ', $select), false)
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    private function component_batch_base_output_qty(array $component): float
    {
        $baseOutputQty = (float)($component['std_batch_qty'] ?? ($component['yield_qty'] ?? 1));
        if ($baseOutputQty <= 0) {
            $baseOutputQty = 1.0;
        }
        return round($baseOutputQty, 4);
    }

    private function plan_component_batch_component(
        array $component,
        float $requiredOutputQty,
        string $locationType,
        int $divisionId,
        array &$state,
        array $path,
        int $depth,
        bool $inlineStage,
        ?array $consumerComponent
    ): array {
        $componentId = (int)($component['id'] ?? 0);
        if ($componentId <= 0) {
            return ['ok' => false, 'message' => 'Component plan tidak valid.'];
        }
        if (in_array($componentId, $path, true)) {
            return ['ok' => false, 'message' => 'Siklus formula terdeteksi pada component ' . (string)($component['component_name'] ?? ('#' . $componentId)) . '.'];
        }

        $formulaLines = $this->list_component_formulas($componentId);
        if (empty($formulaLines)) {
            return ['ok' => false, 'message' => 'Formula component ' . (string)($component['component_name'] ?? ('#' . $componentId)) . ' belum tersedia.'];
        }

        $baseOutputQty = $this->component_batch_base_output_qty($component);
        $scaleFactor = $requiredOutputQty / max($baseOutputQty, 0.0001);
        $path[] = $componentId;
        $issueCountBefore = count($state['issues']);
        $directInputCost = 0.0;

        foreach ($formulaLines as $line) {
            $lineType = strtoupper((string)($line['line_type'] ?? ''));
            $lineQty = round((float)($line['qty'] ?? 0), 4);
            $requiredQty = round($lineQty * $scaleFactor, 4);
            if ($requiredQty <= 0) {
                continue;
            }

            if ($lineType === 'MATERIAL') {
                $materialPlan = $this->plan_component_batch_material_line($line, $requiredQty, $locationType, $divisionId, $depth, $component, $state);
                $directInputCost += (float)($materialPlan['cost_contribution'] ?? 0);
                continue;
            }

            if ($lineType !== 'COMPONENT') {
                continue;
            }

            $componentPlan = $this->plan_component_batch_sub_component_line(
                $line,
                $requiredQty,
                $locationType,
                $divisionId,
                $depth,
                $component,
                $state,
                $path
            );
            $directInputCost += (float)($componentPlan['cost_contribution'] ?? 0);
        }

        $canProduce = count($state['issues']) === $issueCountBefore;
        $variableMeta = $this->component_batch_variable_meta($component);
        $variableCost = !$inlineStage && $canProduce
            ? round($directInputCost * ((float)($variableMeta['pct'] ?? 0) / 100), 2)
            : 0.0;
        $totalInputCost = round($directInputCost + $variableCost, 2);

        if ($inlineStage && $canProduce) {
            $unitCost = $requiredOutputQty > 0 ? round($directInputCost / $requiredOutputQty, 6) : 0.0;
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'INLINE_OUTPUT',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => $componentId,
                'stage_component_name' => (string)($component['component_name'] ?? ''),
                'source_label' => (string)($component['component_name'] ?? ''),
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_code' => (string)($component['uom_code'] ?? ''),
                'required_qty' => round($requiredOutputQty, 4),
                'available_qty' => round($requiredOutputQty, 4),
                'unit_cost' => $unitCost,
                'total_cost' => round($directInputCost, 2),
                'cost_contribution' => 0.0,
                'is_short' => false,
                'status_label' => 'INLINE PRODUCED',
                'notes' => 'Hasil inline produksi untuk ' . (string)($consumerComponent['component_name'] ?? 'component parent'),
                'component_id' => $componentId,
            ]);
            $state['posting_lines'][] = [
                'plan_role' => 'INLINE_OUTPUT',
                'source_kind' => 'COMPONENT',
                'item_id' => null,
                'material_id' => null,
                'component_id' => $componentId,
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'qty' => round($requiredOutputQty, 4),
                'unit_cost' => $unitCost,
                'notes' => 'Inline output ' . (string)($component['component_name'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'has_shortage' => !$canProduce,
            'direct_input_cost' => round($directInputCost, 2),
            'variable_cost_mode' => (string)($variableMeta['mode'] ?? 'DEFAULT'),
            'variable_cost_label' => (string)($variableMeta['label'] ?? 'Default'),
            'variable_cost_pct' => round((float)($variableMeta['pct'] ?? 0), 4),
            'variable_cost_total' => $variableCost,
            'total_input_cost' => $totalInputCost,
            'unit_cost' => $requiredOutputQty > 0 ? round(($inlineStage ? $directInputCost : $totalInputCost) / $requiredOutputQty, 6) : 0.0,
        ];
    }

    private function plan_component_batch_material_line(
        array $line,
        float $requiredQty,
        string $locationType,
        int $divisionId,
        int $depth,
        array $stageComponent,
        array &$state
    ): array {
        $materialCol = $this->formula_material_column();
        $materialId = (int)($line[$materialCol] ?? 0);
        if ($materialId <= 0 && !empty($line['material_item_id'])) {
            $itemId = (int)$line['material_item_id'];
            $legacy = $this->db->select('material_id')->from('mst_item')->where('id', $itemId)->limit(1)->get()->row_array();
            $materialId = (int)($legacy['material_id'] ?? 0);
        }
        $itemId = !empty($line['material_item_id']) ? (int)$line['material_item_id'] : null;
        $uomId = (int)($line['uom_id'] ?? $this->resolve_formula_uom_id('MATERIAL', $materialId, null));
        $effectiveDivisionId = !empty($line['source_division_id']) ? (int)$line['source_division_id'] : $divisionId;
        $effectiveLocationType = $locationType;
        if ($effectiveDivisionId !== $divisionId) {
            $srcCode = $this->operational_division_code($effectiveDivisionId);
            $isEvent = in_array(strtoupper($locationType), ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true);
            if ($srcCode === 'BAR') {
                $effectiveLocationType = $isEvent ? 'BAR_EVENT' : 'BAR';
            } elseif ($srcCode === 'KITCHEN') {
                $effectiveLocationType = $isEvent ? 'KITCHEN_EVENT' : 'KITCHEN';
            } elseif ($srcCode === 'ROASTERY') {
                $effectiveLocationType = $isEvent ? 'ROASTERY_EVENT' : 'ROASTERY';
            }
        }
        $stockState = $this->component_batch_material_stock_state(
            $materialId,
            $itemId,
            $uomId,
            $effectiveDivisionId,
            $effectiveLocationType,
            (string)($state['batch_date'] ?? '')
        );
        $stockKey = (string)($stockState['stock_key'] ?? ($materialId . '|' . $itemId . '|' . $uomId . '|' . $effectiveDivisionId . '|' . $effectiveLocationType));
        $availableQty = array_key_exists($stockKey, $state['material_stock'])
            ? (float)$state['material_stock'][$stockKey]['available_qty']
            : (float)$stockState['available_qty'];
        $unitCost = (float)$stockState['unit_cost'];
        $shortageQty = max(0, round($requiredQty - $availableQty, 4));
        $state['material_stock'][$stockKey] = [
            'available_qty' => max(0, round($availableQty - $requiredQty, 4)),
            'unit_cost' => $unitCost,
        ];

        if ($shortageQty > 0) {
            $issueKey = 'M|' . $materialId . '|' . $effectiveDivisionId . '|' . $effectiveLocationType . '|' . $depth . '|' . $stageComponent['id'];
            if (!isset($state['material_issue_keys'][$issueKey])) {
                $state['material_issue_keys'][$issueKey] = true;
                $state['issues'][] = 'Bahan ' . (string)($line['material_name'] ?? ('#' . $materialId)) . ' kurang ' . number_format($shortageQty, 2, '.', '') . ' ' . (string)($line['uom_code'] ?? '');
            }
        }

        $totalCost = round($requiredQty * $unitCost, 2);
        $state['preview_lines'][] = $this->component_batch_preview_row([
            'plan_role' => 'MATERIAL_USAGE',
            'source_kind' => 'MATERIAL',
            'depth' => $depth,
            'stage_component_id' => (int)($stageComponent['id'] ?? 0),
            'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
            'source_label' => (string)($line['material_name'] ?? ''),
            'uom_id' => $uomId,
            'uom_code' => (string)($line['uom_code'] ?? ''),
            'required_qty' => $requiredQty,
            'available_qty' => round($availableQty, 4),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'cost_contribution' => $totalCost,
            'is_short' => $shortageQty > 0,
            'status_label' => $shortageQty > 0 ? 'KURANG' : 'READY',
            'notes' => $shortageQty > 0 ? 'Bahan tidak cukup untuk batch ini.' : null,
            'item_id' => $itemId,
            'material_id' => $materialId,
        ]);
        $state['posting_lines'][] = [
            'plan_role' => 'MATERIAL_USAGE',
            'source_kind' => 'MATERIAL',
            'item_id' => $itemId,
            'material_id' => $materialId > 0 ? $materialId : null,
            'component_id' => null,
            'uom_id' => $uomId,
            'qty' => round($requiredQty, 4),
            'unit_cost' => round($unitCost, 6),
            'division_id' => $effectiveDivisionId > 0 ? $effectiveDivisionId : null,
            'notes' => 'Pakai bahan untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
        ];

        return ['cost_contribution' => $totalCost];
    }

    private function plan_component_batch_sub_component_line(
        array $line,
        float $requiredQty,
        string $locationType,
        int $divisionId,
        int $depth,
        array $stageComponent,
        array &$state,
        array $path
    ): array {
        $subComponentId = (int)($line['sub_component_id'] ?? 0);
        $subComponent = $this->component_batch_component_context($subComponentId);
        if (!$subComponent) {
            return ['cost_contribution' => 0.0];
        }
        $effectiveDivisionId = !empty($line['source_division_id']) ? (int)$line['source_division_id'] : $divisionId;
        if ((int)($subComponent['operational_division_id'] ?? 0) !== $effectiveDivisionId) {
            $issueKey = 'CD|' . $subComponentId . '|' . $effectiveDivisionId;
            if (!isset($state['component_issue_keys'][$issueKey])) {
                $state['component_issue_keys'][$issueKey] = true;
                $state['issues'][] = 'Component ' . (string)($subComponent['component_name'] ?? ('#' . $subComponentId)) . ' beda divisi dengan batch.';
            }
            return ['cost_contribution' => 0.0];
        }

        $uomId = (int)($line['uom_id'] ?? $subComponent['uom_id'] ?? 0);
        $stockState = $this->component_batch_component_stock_state(
            $subComponentId,
            $uomId,
            $effectiveDivisionId,
            $locationType,
            (string)($state['batch_date'] ?? '')
        );
        $stockKey = $subComponentId . '|' . $uomId . '|' . $effectiveDivisionId . '|' . $locationType;
        $availableQty = array_key_exists($stockKey, $state['component_stock'])
            ? (float)$state['component_stock'][$stockKey]['available_qty']
            : (float)$stockState['available_qty'];
        $unitCost = (float)$stockState['unit_cost'];

        $costContribution = 0.0;
        $directQty = min($requiredQty, $availableQty);
        if ($directQty > 0) {
            $state['component_stock'][$stockKey] = [
                'available_qty' => max(0, round($availableQty - $directQty, 4)),
                'unit_cost' => $unitCost,
            ];
            $lineCost = round($directQty * $unitCost, 2);
            $costContribution += $lineCost;
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => (int)($stageComponent['id'] ?? 0),
                'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
                'source_label' => (string)($subComponent['component_name'] ?? ''),
                'uom_id' => $uomId,
                'uom_code' => (string)($subComponent['uom_code'] ?? ''),
                'required_qty' => round($directQty, 4),
                'available_qty' => round($availableQty, 4),
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'cost_contribution' => $lineCost,
                'is_short' => false,
                'status_label' => 'READY',
                'notes' => 'Pakai stok component yang sudah ada.',
                'component_id' => $subComponentId,
            ]);
            $state['posting_lines'][] = [
                'plan_role' => 'COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'item_id' => null,
                'material_id' => null,
                'component_id' => $subComponentId,
                'uom_id' => $uomId,
                'qty' => round($directQty, 4),
                'unit_cost' => round($unitCost, 6),
                'division_id' => $effectiveDivisionId > 0 ? $effectiveDivisionId : null,
                'notes' => 'Pakai component untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
            ];
        }

        $shortageQty = round($requiredQty - $directQty, 4);
        if ($shortageQty <= 0) {
            return ['cost_contribution' => $costContribution];
        }

        $state['component_stock'][$stockKey] = [
            'available_qty' => 0.0,
            'unit_cost' => $unitCost,
        ];
        $inlinePlan = $this->plan_component_batch_component(
            $subComponent,
            $shortageQty,
            $locationType,
            $effectiveDivisionId,
            $state,
            $path,
            $depth + 1,
            true,
            $stageComponent
        );
        if (!($inlinePlan['ok'] ?? false)) {
            $issueKey = 'CF|' . $subComponentId . '|' . $effectiveDivisionId . '|' . $locationType;
            if (!isset($state['component_issue_keys'][$issueKey])) {
                $state['component_issue_keys'][$issueKey] = true;
                $state['issues'][] = (string)($inlinePlan['message'] ?? ('Formula component ' . (string)($subComponent['component_name'] ?? ('#' . $subComponentId)) . ' belum siap untuk inline produksi.'));
            }
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'INLINE_COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => (int)($stageComponent['id'] ?? 0),
                'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
                'source_label' => (string)($subComponent['component_name'] ?? ''),
                'uom_id' => $uomId,
                'uom_code' => (string)($subComponent['uom_code'] ?? ''),
                'required_qty' => round($shortageQty, 4),
                'available_qty' => 0.0,
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
                'cost_contribution' => 0.0,
                'is_short' => true,
                'status_label' => 'INLINE GAGAL',
                'notes' => (string)($inlinePlan['message'] ?? 'Inline produksi gagal.'),
                'component_id' => $subComponentId,
            ]);
            return ['cost_contribution' => $costContribution];
        }

        if (!empty($inlinePlan['has_shortage'])) {
            $issueKey = 'CS|' . $subComponentId . '|' . $effectiveDivisionId . '|' . $locationType;
            if (!isset($state['component_issue_keys'][$issueKey])) {
                $state['component_issue_keys'][$issueKey] = true;
                $state['issues'][] = 'Component ' . (string)($subComponent['component_name'] ?? ('#' . $subComponentId)) . ' masih kurang bahan untuk inline produksi.';
            }
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'INLINE_COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => (int)($stageComponent['id'] ?? 0),
                'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
                'source_label' => (string)($subComponent['component_name'] ?? ''),
                'uom_id' => $uomId,
                'uom_code' => (string)($subComponent['uom_code'] ?? ''),
                'required_qty' => round($shortageQty, 4),
                'available_qty' => 0.0,
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
                'cost_contribution' => 0.0,
                'is_short' => true,
                'status_label' => 'INLINE KURANG',
                'notes' => 'Inline produksi component ini masih terkendala bahan.',
                'component_id' => $subComponentId,
            ]);
            return ['cost_contribution' => $costContribution];
        }

        $inlineUnitCost = (float)($inlinePlan['unit_cost'] ?? 0);
        $inlineTotalCost = round((float)($inlinePlan['direct_input_cost'] ?? 0), 2);
        $costContribution += $inlineTotalCost;
        $state['preview_lines'][] = $this->component_batch_preview_row([
            'plan_role' => 'INLINE_COMPONENT_USAGE',
            'source_kind' => 'COMPONENT',
            'depth' => $depth,
            'stage_component_id' => (int)($stageComponent['id'] ?? 0),
            'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
            'source_label' => (string)($subComponent['component_name'] ?? ''),
            'uom_id' => $uomId,
            'uom_code' => (string)($subComponent['uom_code'] ?? ''),
            'required_qty' => round($shortageQty, 4),
            'available_qty' => round($shortageQty, 4),
            'unit_cost' => $inlineUnitCost,
            'total_cost' => $inlineTotalCost,
            'cost_contribution' => 0.0,
            'is_short' => false,
            'status_label' => 'INLINE USED',
            'notes' => 'Pakai hasil inline produksi untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
            'component_id' => $subComponentId,
        ]);
        $state['posting_lines'][] = [
            'plan_role' => 'INLINE_COMPONENT_USAGE',
            'source_kind' => 'COMPONENT',
            'item_id' => null,
            'material_id' => null,
            'component_id' => $subComponentId,
            'uom_id' => $uomId,
            'qty' => round($shortageQty, 4),
            'unit_cost' => round($inlineUnitCost, 6),
            'notes' => 'Pakai hasil inline produksi untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
        ];

        return ['cost_contribution' => $costContribution];
    }

    private function component_batch_material_stock_state(int $materialId, ?int $itemId, int $uomId, int $divisionId, string $locationType, ?string $asOfDate = null): array
    {
        $availableQty = 0.0;
        $unitCost = 0.0;
        $stockKey = $materialId . '|' . $itemId . '|' . $uomId . '|' . $divisionId . '|' . $locationType;
        $targetMonth = $this->normalize_component_batch_target_month($asOfDate);
        if ($materialId > 0 && $divisionId > 0 && $this->db->table_exists('inv_division_monthly_stock')) {
            $destinationType = $locationType;
            $queryStock = static function ($db, string $targetMonthKey, int $targetDivisionId, int $targetMaterialId, string $targetDestinationType, ?int $targetItemId = null, int $targetUomId = 0): array {
                $db->select('SUM(COALESCE(s.closing_qty_content,0)) AS qty_balance, AVG(COALESCE(s.avg_cost_per_content,0)) AS avg_cost_per_content', false)
                    ->from('inv_division_monthly_stock s')
                    ->where('s.month_key', $targetMonthKey)
                    ->where('s.division_id', $targetDivisionId)
                    ->where('s.material_id', $targetMaterialId)
                    ->where('s.destination_type', $targetDestinationType);
                if ($targetUomId > 0) {
                    $db->where('s.content_uom_id', $targetUomId);
                }
                if ($targetItemId !== null && $targetItemId > 0) {
                    $db->where('s.item_id', $targetItemId);
                }
                return (array)$db->get()->row_array();
            };

            $row = $queryStock($this->db, $targetMonth, $divisionId, $materialId, $destinationType, $itemId, $uomId);
            if ((float)($row['qty_balance'] ?? 0) <= 0) {
                // Some legacy monthly rows carry the content quantity on a different item/UOM profile.
                // Fall back to the material bucket for the same division + destination within the same month snapshot.
                $row = $queryStock($this->db, $targetMonth, $divisionId, $materialId, $destinationType, null, 0);
                if ((float)($row['qty_balance'] ?? 0) > 0) {
                    $stockKey = 'MFB|' . $materialId . '|' . $divisionId . '|' . $destinationType . '|' . $targetMonth;
                }
            }
            $availableQty = (float)($row['qty_balance'] ?? 0);
            $unitCost = (float)($row['avg_cost_per_content'] ?? 0);
        }
        if ($unitCost <= 0 && $materialId > 0) {
            $fallback = $this->db->select('hpp_standard')->from('mst_material')->where('id', $materialId)->limit(1)->get()->row_array();
            $unitCost = (float)($fallback['hpp_standard'] ?? 0);
        }
        return [
            'available_qty' => round($availableQty, 4),
            'unit_cost' => round($unitCost, 6),
            'stock_key' => $stockKey,
        ];
    }

    public function component_stock_snapshot(int $componentId, int $uomId = 0, ?int $divisionId = null, string $locationType = '', ?int $selectedLotId = null, ?string $asOfDate = null): array
    {
        $componentId = (int)$componentId;
        $uomId = (int)$uomId;
        $divisionId = $divisionId !== null && (int)$divisionId > 0 ? (int)$divisionId : null;
        $locationType = strtoupper(trim($locationType));

        $availableQty = 0.0;
        $unitCost = 0.0;
        $targetMonth = $this->normalize_component_batch_target_month($asOfDate);
        if ($componentId > 0 && $this->db->table_exists('inv_component_monthly_stock')) {
            $this->db->select('SUM(COALESCE(s.closing_qty,0)) AS qty_balance, AVG(COALESCE(s.avg_cost,0)) AS avg_cost', false)
                ->from('inv_component_monthly_stock s')
                ->where('s.month_key', $targetMonth)
                ->where('s.component_id', $componentId);
            if ($locationType !== '') {
                $this->db->where('s.location_type', $locationType);
            }
            if ($divisionId !== null) {
                $this->db->where('s.division_id', $divisionId);
            }
            if ($uomId > 0) {
                $this->db->where('s.uom_id', $uomId);
            }
            $row = $this->db->get()->row_array();
            $availableQty = (float)($row['qty_balance'] ?? 0);
            $unitCost = (float)($row['avg_cost'] ?? 0);
        }
        if ($unitCost <= 0 && $componentId > 0) {
            $fallback = $this->db->select('hpp_standard')->from('mst_component')->where('id', $componentId)->limit(1)->get()->row_array();
            $unitCost = (float)($fallback['hpp_standard'] ?? 0);
        }
        $lotSummary = $this->component_lot_summary($locationType, $divisionId, $componentId, $uomId, $asOfDate);
        $selectedLot = $this->resolve_component_selected_lot($lotSummary, $selectedLotId);
        if ($selectedLot !== null) {
            $availableQty = (float)($selectedLot['qty_balance'] ?? 0);
            $unitCost = (float)($selectedLot['unit_cost'] ?? 0);
        }
        return [
            'available_qty' => round($availableQty, 4),
            'unit_cost' => round($unitCost, 6),
            'lot_summary' => $lotSummary,
            'lot_preview' => $this->component_lot_preview_text($lotSummary),
            'selected_lot' => $selectedLot,
        ];
    }

    private function component_batch_component_stock_state(int $componentId, int $uomId, int $divisionId, string $locationType, ?string $asOfDate = null): array
    {
        return $this->component_stock_snapshot($componentId, $uomId, $divisionId > 0 ? $divisionId : null, $locationType, null, $asOfDate);
    }

    private function normalize_component_batch_target_month(?string $asOfDate = null): string
    {
        $asOfDate = trim((string)$asOfDate);
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            return substr($asOfDate, 0, 7) . '-01';
        }
        return date('Y-m-01');
    }

    private function component_batch_preview_row(array $payload): array
    {
        $row = [
            'line_no' => (int)($payload['line_no'] ?? 0),
            'plan_role' => (string)($payload['plan_role'] ?? 'INPUT'),
            'source_kind' => (string)($payload['source_kind'] ?? ''),
            'depth' => (int)($payload['depth'] ?? 0),
            'stage_component_id' => (int)($payload['stage_component_id'] ?? 0),
            'stage_component_name' => (string)($payload['stage_component_name'] ?? ''),
            'source_label' => (string)($payload['source_label'] ?? ''),
            'item_id' => !empty($payload['item_id']) ? (int)$payload['item_id'] : null,
            'material_id' => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
            'component_id' => !empty($payload['component_id']) ? (int)$payload['component_id'] : null,
            'uom_id' => (int)($payload['uom_id'] ?? 0),
            'uom_code' => (string)($payload['uom_code'] ?? ''),
            'required_qty' => round((float)($payload['required_qty'] ?? 0), 4),
            'available_qty' => round((float)($payload['available_qty'] ?? 0), 4),
            'unit_cost' => round((float)($payload['unit_cost'] ?? 0), 6),
            'total_cost' => round((float)($payload['total_cost'] ?? 0), 2),
            'cost_contribution' => round((float)($payload['cost_contribution'] ?? 0), 2),
            'is_short' => !empty($payload['is_short']),
            'status_label' => (string)($payload['status_label'] ?? ''),
            'notes' => $this->nullable_string($payload['notes'] ?? null),
        ];
        if ($row['line_no'] <= 0) {
            $row['line_no'] = 0;
        }
        return $row;
    }

    private function component_batch_variable_meta(array $component): array
    {
        $mode = strtoupper((string)($component['variable_cost_mode'] ?? 'DEFAULT'));
        $percent = (float)($component['variable_cost_percent'] ?? 0);
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
            $label = 'Tanpa Variable Cost';
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
            $label = 'Custom Component';
        } else {
            $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
            $label = 'Default Component';
            $mode = 'DEFAULT';
        }

        return [
            'mode' => $mode,
            'pct' => round($effectivePercent, 4),
            'label' => $label,
        ];
    }

    public function delete_draft_doc(string $tableHeader, string $tableLine, string $pkName, int $id): array
    {
        $row = $this->db->get_where($tableHeader, ['id' => $id])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Data tidak ditemukan.'];
        }
        if (strtoupper((string)($row['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya dokumen DRAFT yang bisa dihapus.'];
        }
        $this->db->trans_start();
        if ($tableHeader === 'inv_component_adjustment') {
            $locked = $this->db->query('SELECT status FROM inv_component_adjustment WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked || ($locked->row_array()['status'] ?? '') !== 'DRAFT') {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Adjustment sudah berubah; hanya DRAFT yang boleh dihapus.'];
            }
        }
        $this->db->where($pkName, $id)->delete($tableLine);
        $this->db->where('id', $id)->delete($tableHeader);
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menghapus dokumen.'];
        }
        return ['ok' => true];
    }

    public function void_component_adjustment(int $id, int $actorEmployeeId): array
    {
        $header = $this->get_component_adjustment($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Adjustment tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? 'DRAFT')) !== 'POSTED') {
            return ['ok' => false, 'message' => 'Hanya adjustment POSTED yang bisa di-void.'];
        }
        $period = $this->assert_component_period_open(
            (string)($header['adjustment_date'] ?? ''),
            'VOID adjustment component'
        );
        if (!($period['ok'] ?? false)) {
            return $period;
        }
        if (!$this->db->table_exists('inv_component_movement_log')) {
            return ['ok' => false, 'message' => 'Histori movement component belum tersedia.'];
        }
        $reversalReady = $this->assert_component_movement_reversal_ready();
        if (!($reversalReady['ok'] ?? false)) {
            return $reversalReady;
        }

        $adjustmentMovements = $this->db->from('inv_component_movement_log')
            ->where('source_table', 'inv_component_adjustment')
            ->where('source_id', $id)
            ->order_by('id', 'DESC')
            ->get()
            ->result_array();
        if (empty($adjustmentMovements)) {
            return ['ok' => false, 'message' => 'Histori movement adjustment tidak ditemukan.'];
        }

        $this->db->trans_begin();
        $this->load->library('InventoryAdjustmentGuard');
        $locked = $this->inventoryadjustmentguard->lock('COMPONENT', $id, 'POSTED', (string)$header['adjustment_date']);
        if (empty($locked['ok'])) { $this->db->trans_rollback(); return $locked; }
        $header = $locked['header'];

        $this->load->library('ComponentLotManager');
        $lotReady = $this->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $lotReady;
        }

        $rollbackIssue = $this->componentlotmanager->rollbackIssueLotsBySource(
            'inv_component_adjustment',
            $id,
            null,
            'VOID adjustment component membatalkan issue lot'
        );
        if (!($rollbackIssue['ok'] ?? false)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'VOID adjustment ditolak: ' . (string)($rollbackIssue['message'] ?? 'rollback issue lot component gagal.')];
        }

        $this->load->library('InventoryStructuralLotReversal');
        $structural = $this->inventorystructurallotreversal->reverse('COMPONENT', 'inv_component_adjustment', $id, (string)$header['adjustment_date']);
        if (empty($structural['ok'])) { $this->db->trans_rollback(); return $structural; }

        $voidLot = $this->componentlotmanager->voidInboundLotsBySource(
            'inv_component_adjustment',
            $id,
            null,
            'VOID adjustment component membatalkan lot inbound plus'
        );
        if (!($voidLot['ok'] ?? false)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'VOID adjustment ditolak: ' . (string)($voidLot['message'] ?? 'void lot adjustment plus gagal.')];
        }

        $rebuildIdentities = [];
        foreach ($adjustmentMovements as $movement) {
            $reverse = $this->reverse_component_document_movement_row(
                $movement,
                (string)($header['adjustment_date'] ?? date('Y-m-d')),
                'inv_component_adjustment',
                $id,
                'PRODUCTION_ADJUSTMENT_VOID',
                $actorEmployeeId,
                ['returned_lots' => array_merge($rollbackIssue['data']['allocations'] ?? [], $structural['data']['returned_lots']),
                    'removed_lots' => array_merge($voidLot['data']['allocations'] ?? [], $structural['data']['removed_lots'])]
            );
            if (!($reverse['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $reverse;
            }
            if (!empty($reverse['identity']) && is_array($reverse['identity'])) {
                $rebuildIdentities[] = $reverse['identity'];
            }
        }

        $rebuild = $this->rebuild_component_histories($rebuildIdentities);
        if (!($rebuild['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $rebuild;
        }

        $voidedDeficitCount = 0;
        if (file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            $this->load->library('InventoryDeficitService');
            if ($this->inventorydeficitservice->isReady()) {
                $deficitVoid = $this->inventorydeficitservice->voidBySource(
                    'inv_component_adjustment',
                    $id,
                    null,
                    null,
                    'VOID adjustment component'
                );
                if (!($deficitVoid['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => 'Gagal membatalkan defisit yang berasal dari adjustment component ini.'];
                }
                $voidedDeficitCount = (int)($deficitVoid['voided_count'] ?? 0);
            }
        }

        $consistent = $this->inventoryadjustmentguard->checkComponent($header, $this->get_component_adjustment_lines($id));
        if (empty($consistent['ok'])) { $this->db->trans_rollback(); return $consistent; }

        $update = ['status' => 'VOID'];
        if ($this->db->field_exists('voided_at', 'inv_component_adjustment')) {
            $update['voided_at'] = date('Y-m-d H:i:s');
        }
        if ($this->db->field_exists('voided_by', 'inv_component_adjustment')) {
            $update['voided_by'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
        }
        if ($this->db->field_exists('notes', 'inv_component_adjustment')) {
            $existingNotes = trim((string)($header['notes'] ?? ''));
            $note = 'VOID adjustment component';
            $update['notes'] = $existingNotes !== '' ? ($existingNotes . ' | ' . $note) : $note;
        }
        $this->db->where('id', $id)->update('inv_component_adjustment', $update);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal VOID adjustment component.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id, 'voided_deficit_count' => $voidedDeficitCount];
    }

    public function void_component_batch(int $id, int $actorEmployeeId): array
    {
        $header = $this->get_component_batch($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? 'DRAFT')) !== 'POSTED') {
            return ['ok' => false, 'message' => 'Hanya batch POSTED yang bisa di-void.'];
        }
        $period = $this->assert_component_period_open(
            (string)($header['batch_date'] ?? ''),
            'VOID batch component'
        );
        if (!($period['ok'] ?? false)) {
            return $period;
        }
        if (!$this->db->table_exists('inv_component_movement_log')) {
            return ['ok' => false, 'message' => 'Histori movement component belum tersedia.'];
        }
        $reversalReady = $this->assert_component_movement_reversal_ready();
        if (!($reversalReady['ok'] ?? false)) {
            return $reversalReady;
        }

        $batchMovements = $this->db->from('inv_component_movement_log')
            ->where('source_table', 'inv_component_batch')
            ->where('source_id', $id)
            ->order_by('id', 'DESC')
            ->get()
            ->result_array();
        if (empty($batchMovements)) {
            return ['ok' => false, 'message' => 'Histori movement batch tidak ditemukan.'];
        }

        $guard = $this->guard_component_batch_void_usage($header, $batchMovements);
        if (!($guard['ok'] ?? false)) {
            return $guard;
        }

        $this->db->trans_begin();

        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $fifoReady;
        }

        $this->load->library('ComponentLotManager');
        $componentLotReady = $this->componentlotmanager->ensureReady();
        if (!($componentLotReady['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $componentLotReady;
        }

        $rollbackIssue = $this->materialfifomanager->rollbackTransferLotsBySource(
            'inv_component_batch',
            $id,
            null,
            'VOID batch component membatalkan konsumsi material'
        );
        if (!($rollbackIssue['ok'] ?? false)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'VOID batch ditolak: ' . (string)($rollbackIssue['message'] ?? 'rollback issue FIFO gagal.')];
        }

        $rollbackComponentIssue = $this->componentlotmanager->rollbackIssueLotsBySource(
            'inv_component_batch',
            $id,
            null,
            'VOID batch component membatalkan konsumsi input component'
        );
        if (!($rollbackComponentIssue['ok'] ?? false)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'VOID batch ditolak: ' . (string)($rollbackComponentIssue['message'] ?? 'rollback issue lot input component gagal.')];
        }

        $voidLot = $this->componentlotmanager->voidInboundLotsBySource(
            'inv_component_batch',
            $id,
            null,
            'VOID batch component membatalkan lot output'
        );
        if (!($voidLot['ok'] ?? false)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'VOID batch ditolak: ' . (string)($voidLot['message'] ?? 'void lot component gagal.')];
        }

        $this->load->library('InventoryMovementReversalService');
        if ($this->db->table_exists('inv_stock_movement_log')) {
            $reverseInventory = $this->inventorymovementreversalservice->reverseMaterialMovementsForSource(
                'inv_component_batch',
                $id,
                [
                    'movement_date' => (string)($header['batch_date'] ?? date('Y-m-d')),
                    'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                    'notes' => 'VOID batch component',
                    'manage_transaction' => false,
                    'allow_negative_balance' => true,
                ]
            );
            if (!($reverseInventory['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal reverse movement inventory: ' . (string)($reverseInventory['message'] ?? '-')];
            }
        }

        $rebuildIdentities = [];
        foreach ($batchMovements as $movement) {
            $reverse = $this->reverse_component_movement_row(
                $movement,
                (string)($header['batch_date'] ?? date('Y-m-d')),
                $id,
                $actorEmployeeId,
                [
                    // Void batch mengikuti lot output batch. Jika histori monthly/ledger
                    // sedang drift, rollback tetap boleh membuat saldo global minus dulu.
                    'allow_negative_rollback' => true,
                    'returned_lots' => $rollbackComponentIssue['data']['allocations'] ?? [],
                    'removed_lots' => $voidLot['data']['allocations'] ?? [],
                ]
            );
            if (!($reverse['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $reverse;
            }
            if (!empty($reverse['identity']) && is_array($reverse['identity'])) {
                $rebuildIdentities[] = $reverse['identity'];
            }
        }

        $rebuild = $this->rebuild_component_histories($rebuildIdentities);
        if (!($rebuild['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $rebuild;
        }

        $voidedDeficitCount = 0;
        if (file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            $this->load->library('InventoryDeficitService');
            if ($this->inventorydeficitservice->isReady()) {
                $deficitVoid = $this->inventorydeficitservice->voidBySource(
                    'inv_component_batch',
                    $id,
                    null,
                    null,
                    'VOID batch component'
                );
                if (!($deficitVoid['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => 'Gagal membatalkan defisit yang berasal dari batch component ini.'];
                }
                $voidedDeficitCount = (int)($deficitVoid['voided_count'] ?? 0);
            }
        }

        $update = ['status' => 'VOID'];
        if ($this->db->field_exists('voided_at', 'inv_component_batch')) {
            $update['voided_at'] = date('Y-m-d H:i:s');
        }
        if ($this->db->field_exists('voided_by', 'inv_component_batch')) {
            $update['voided_by'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
        }
        if ($this->db->field_exists('notes', 'inv_component_batch')) {
            $existingNotes = trim((string)($header['notes'] ?? ''));
            $note = 'VOID batch component';
            $update['notes'] = $existingNotes !== '' ? ($existingNotes . ' | ' . $note) : $note;
        }
        $this->db->where('id', $id)->update('inv_component_batch', $update);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal VOID batch component.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id, 'voided_deficit_count' => $voidedDeficitCount];
    }

    private function guard_component_batch_void_usage(array $header, array $batchMovements): array
    {
        $headerId = (int)($header['id'] ?? 0);
        $componentId = (int)($header['component_id'] ?? 0);
        $divisionId = !empty($header['division_id']) ? (int)$header['division_id'] : null;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $postedAt = trim((string)($header['posted_at'] ?? ''));

        $mainOutputMovement = null;
        foreach ($batchMovements as $movement) {
            if ((int)($movement['component_id'] ?? 0) === $componentId
                && strtoupper((string)($movement['movement_type'] ?? '')) === 'PRODUCTION_IN') {
                $mainOutputMovement = $movement;
                break;
            }
        }

        if ($mainOutputMovement) {
            $divisionNullSafe = $divisionId === null ? 'NULL' : (string)$divisionId;
            $this->db->select('movement_no, movement_date, movement_type')
                ->from('inv_component_movement_log')
                ->where('location_type', $locationType)
                ->where('division_id <=> ' . $divisionNullSafe, null, false)
                ->where('component_id', (int)$mainOutputMovement['component_id'])
                ->where('uom_id', (int)$mainOutputMovement['uom_id'])
                ->where('qty_out >', 0)
                ->order_by('movement_date', 'ASC')
                ->order_by('id', 'ASC')
                ->limit(1);
            $this->apply_component_movement_after_anchor((string)($mainOutputMovement['movement_date'] ?? ($header['batch_date'] ?? '')), (int)($mainOutputMovement['id'] ?? 0));
            $nextOut = $this->db->get()->row_array();
            if ($nextOut) {
                return [
                    'ok' => false,
                    'message' => 'Batch tidak bisa di-void karena output component sudah terpakai pada movement ' . (string)($nextOut['movement_no'] ?? '-') . ' (' . (string)($nextOut['movement_date'] ?? '-') . ').',
                ];
            }
        }

        if ($this->db->table_exists('inv_component_batch_input')) {
            $divisionNullSafe = $divisionId === null ? 'NULL' : (string)$divisionId;
            $this->db->select('b.batch_no, b.batch_date')
                ->from('inv_component_batch_input i')
                ->join('inv_component_batch b', 'b.id = i.batch_id', 'inner')
                ->where('b.id <>', $headerId)
                ->where('b.status', 'POSTED')
                ->where('i.source_kind', 'COMPONENT')
                ->where('i.component_id', $componentId)
                ->where('b.location_type', $locationType)
                ->where('b.division_id <=> ' . $divisionNullSafe, null, false);
            if ($postedAt !== '') {
                $this->db->where('b.posted_at >=', $postedAt);
            } else {
                $this->db->where('b.batch_date >=', (string)($header['batch_date'] ?? date('Y-m-d')));
            }
            $used = $this->db->order_by('b.id', 'ASC')->limit(1)->get()->row_array();
            if ($used) {
                return [
                    'ok' => false,
                    'message' => 'Batch tidak bisa di-void karena component output sudah dipakai di batch ' . (string)($used['batch_no'] ?? '-') . ' tanggal ' . (string)($used['batch_date'] ?? '-') . '.',
                ];
            }
        }

        if ($this->db->table_exists('inv_component_lot') && $this->db->table_exists('inv_component_lot_issue_log') && $this->db->table_exists('inv_component_lot_issue_line')) {
            $lotIssue = $this->db->select('il.issue_no, il.issue_date')
                ->from('inv_component_lot l')
                ->join('inv_component_lot_issue_line li', 'li.lot_id = l.id', 'inner')
                ->join('inv_component_lot_issue_log il', 'il.id = li.issue_id', 'inner')
                ->where('l.source_table', 'inv_component_batch')
                ->where('l.source_id', $headerId)
                ->where('il.status', 'POSTED')
                ->order_by('il.id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array();
            if ($lotIssue) {
                return [
                    'ok' => false,
                    'message' => 'Batch tidak bisa di-void karena output component sudah dipakai pada issue FIFO ' . (string)($lotIssue['issue_no'] ?? '-') . ' tanggal ' . (string)($lotIssue['issue_date'] ?? '-') . '.',
                ];
            }
        }

        return ['ok' => true];
    }

    private function reverse_component_movement_row(
        array $movement,
        string $movementDate,
        int $batchId,
        int $actorEmployeeId,
        array $options = []
    ): array
    {
        return $this->reverse_component_document_movement_row(
            $movement,
            $movementDate,
            'inv_component_batch',
            $batchId,
            'PRODUCTION_BATCH_VOID',
            $actorEmployeeId,
            $options
        );
    }

    private function rebuild_component_histories(array $identities): array
    {
        if (empty($identities)) {
            return ['ok' => true];
        }

        $seen = [];
        foreach ($identities as $identity) {
            $locationType = strtoupper(trim((string)($identity['location_type'] ?? '')));
            $divisionId = array_key_exists('division_id', $identity) && $identity['division_id'] !== null && $identity['division_id'] !== ''
                ? (int)$identity['division_id']
                : null;
            $componentId = (int)($identity['component_id'] ?? 0);
            $uomId = (int)($identity['uom_id'] ?? 0);
            if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
                continue;
            }

            $identityKey = implode('|', [
                $locationType,
                $divisionId !== null ? (string)$divisionId : 'NULL',
                (string)$componentId,
                (string)$uomId,
            ]);
            if (isset($seen[$identityKey])) {
                continue;
            }
            $seen[$identityKey] = true;

            $rebuild = $this->rebuild_component_history_for_identity([
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
            ]);
            if (!($rebuild['ok'] ?? false)) {
                return $rebuild;
            }
        }

        return ['ok' => true];
    }

    private function load_component_current_balance_state(string $locationType, ?int $divisionId, int $componentId, int $uomId): array
    {
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $row = $this->db->query(
                'SELECT month_key, closing_qty AS qty_on_hand, avg_cost, total_value
                 FROM inv_component_monthly_stock
                 WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ?
                 ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC
                 LIMIT 1 FOR UPDATE',
                [$locationType, $divisionId, $componentId, $uomId]
            )->row_array();
            if (!empty($row)) {
                return $row;
            }
        }

        return [
            'qty_on_hand' => 0.0,
            'avg_cost' => 0.0,
            'total_value' => 0.0,
        ];
    }

    private function assert_component_movement_reversal_ready(): array
    {
        if (!$this->db->table_exists('inv_component_movement_log')) {
            return ['ok' => false, 'message' => 'Histori movement component belum tersedia.'];
        }
        if (!$this->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log')) {
            return [
                'ok' => false,
                'message' => 'Schema reversal component belum siap. Jalankan migration 2026-08-22a_inventory_void_reversal_movement_link_foundation.sql terlebih dahulu.',
            ];
        }

        return ['ok' => true];
    }

    private function reverse_component_document_movement_row(
        array $movement,
        string $movementDate,
        string $sourceTable,
        int $sourceId,
        string $sourceModule,
        int $actorEmployeeId,
        array $options = []
    ): array {
        $locationType = strtoupper(trim((string)($movement['location_type'] ?? '')));
        $divisionId = !empty($movement['division_id']) ? (int)$movement['division_id'] : null;
        $componentId = (int)($movement['component_id'] ?? 0);
        $uomId = (int)($movement['uom_id'] ?? 0);
        $qtyIn = round((float)($movement['qty_in'] ?? 0), 4);
        $qtyOut = round((float)($movement['qty_out'] ?? 0), 4);
        $unitCost = round((float)($movement['unit_cost'] ?? 0), 6);

        if ($componentId <= 0 || $uomId <= 0) {
            return ['ok' => true];
        }

        $reverseType = '';
        $reverseQty = 0.0;
        if ($qtyIn > 0) {
            $reverseType = 'VOID_OUT';
            $reverseQty = $qtyIn;
        } elseif ($qtyOut > 0) {
            $reverseType = 'VOID_REVERSE';
            $reverseQty = $qtyOut;
        }
        if ($reverseQty <= 0 || $reverseType === '') {
            return ['ok' => true];
        }

        $reversalReady = $this->assert_component_movement_reversal_ready();
        if (!($reversalReady['ok'] ?? false)) {
            return $reversalReady;
        }
        $originMovementId = (int)($movement['id'] ?? 0);
        if ($originMovementId <= 0) {
            return ['ok' => false, 'message' => 'Movement component asal tidak valid untuk reversal.'];
        }

        $reversedColumn = $reverseType === 'VOID_OUT' ? 'qty_out' : 'qty_in';
        $reversedRow = $this->db
            ->select('COALESCE(SUM(' . $reversedColumn . '), 0) AS total_qty, COALESCE(SUM(total_cost), 0) AS total_cost', false)
            ->from('inv_component_movement_log')
            ->where('reversal_of_movement_id', $originMovementId)
            ->get()
            ->row_array();
        $alreadyReversed = round((float)($reversedRow['total_qty'] ?? 0), 4);
        $reverseQty = round(max(0, $reverseQty - $alreadyReversed), 4);
        if ($reverseQty <= 0.0001) {
            return ['ok' => true, 'already_reversed' => true];
        }

        $reverseCost = round($this->component_movement_value($movement) - (float)($reversedRow['total_cost'] ?? 0), 2);
        $lotQty = 0.0;
        $lotCost = 0.0;
        foreach (($options[$reverseType === 'VOID_OUT' ? 'removed_lots' : 'returned_lots'] ?? []) as $allocation) {
            if ((int)$allocation['component_id'] === $componentId && (int)$allocation['uom_id'] === $uomId
                && (int)($allocation['division_id'] ?? 0) === (int)$divisionId
                && (string)$allocation['location_type'] === $locationType
                && (int)($allocation['source_line_id'] ?? 0) === (int)($movement['source_line_id'] ?? 0)) {
                $lotQty += (float)$allocation['qty_rolled'];
                $lotCost += (float)$allocation['total_cost'];
            }
        }
        if ($lotQty > 0.0001) {
            if (abs($lotQty - $reverseQty) <= 0.0001) {
                $reverseCost = round($lotCost, 2);
            } elseif ($reverseType === 'VOID_OUT' || $lotQty < $reverseQty - 0.0001) {
                return ['ok' => false, 'message' => 'Jumlah lot yang dibatalkan tidak sama dengan movement asal. VOID dibatalkan tanpa menimpa saldo.'];
            }
            // WASTE/SPOIL/MINUS can share an adjustment line. Its returned
            // allocation pool then spans several movements: each full reverse
            // uses its own recorded FIFO cost, not the whole pool repeatedly.
        }
        $unitCost = round($reverseCost / $reverseQty, 6);

        $allowNegativeRollback = !empty($options['allow_negative_rollback']);

        $balance = $this->load_component_current_balance_state($locationType, $divisionId, $componentId, $uomId);

        $qtyBefore = (float)($balance['qty_on_hand'] ?? 0);
        $avgBefore = (float)($balance['avg_cost'] ?? 0);
        $valueBefore = round((float)($balance['total_value'] ?? ($qtyBefore * $avgBefore)), 2);
        $isIn = $reverseType === 'VOID_REVERSE';
        $qtyAfter = $isIn ? round($qtyBefore + $reverseQty, 4) : round($qtyBefore - $reverseQty, 4);
        if (!$allowNegativeRollback && $qtyAfter < -0.0001) {
            return ['ok' => false, 'message' => 'VOID ditolak karena stok tidak cukup untuk rollback movement component.'];
        }
        if (abs($qtyAfter) < 0.0001) {
            $qtyAfter = 0.0;
        }

        if ($isIn) {
            $valueAfter = round($valueBefore + $reverseCost, 2);
            $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        } else {
            $valueAfter = round($valueBefore - $reverseCost, 2);
            $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        }

        $now = date('Y-m-d H:i:s');

        $reverseData = [
            'movement_no' => $this->generate_doc_no('inv_component_movement_log', 'movement_no', 'ICM', $movementDate),
            'movement_date' => $movementDate,
            'movement_datetime' => $movementDate . ' ' . date('H:i:s'),
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'movement_type' => $reverseType,
            'qty_in' => $isIn ? $reverseQty : 0,
            'qty_out' => $isIn ? 0 : $reverseQty,
            'unit_cost' => $unitCost,
            'total_cost' => $reverseCost,
            'source_module' => $sourceModule,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'source_line_id' => !empty($movement['source_line_id']) ? (int)$movement['source_line_id'] : null,
            'notes' => 'VOID reverse movement dari log #' . (int)($movement['id'] ?? 0),
            'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            'created_at' => $now,
        ];
        $reverseData['reversal_of_movement_id'] = $originMovementId;
        $this->db->insert('inv_component_movement_log', $reverseData);

        return [
            'ok' => true,
            'identity' => [
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
            ],
            'allow_negative_rollback' => $allowNegativeRollback,
        ];
    }

    public function list_component_categories(array $filters = [], int $limit = 300): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $scopeExpr = $this->db->field_exists('scope_type', 'mst_component_category')
            ? "c.scope_type"
            : "'ALL'";
        $this->db->select("
            c.id,
            c.code,
            c.name,
            {$scopeExpr} AS scope_type,
            c.sort_order,
            c.is_active,
            (
                SELECT COUNT(1)
                FROM mst_component mc
                WHERE mc.component_category_id = c.id
            ) AS component_count
        ", false);
        $this->db->from('mst_component_category c');
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.code', $q)
                ->or_like('c.name', $q)
                ->group_end();
        }
        $this->db->order_by('c.sort_order', 'ASC')->order_by('c.name', 'ASC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function save_component_category(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $scopeType = strtoupper(trim((string)($data['scope_type'] ?? 'ALL')));
        if (!in_array($scopeType, ['BASE', 'PREPARE', 'ALL'], true)) {
            $scopeType = 'ALL';
        }
        $payload = [
            'code' => strtoupper(trim((string)($data['code'] ?? ''))),
            'name' => trim((string)($data['name'] ?? '')),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int)((bool)$data['is_active']) : 1,
        ];
        if ($this->db->field_exists('scope_type', 'mst_component_category')) {
            $payload['scope_type'] = $scopeType;
        }
        if ($payload['name'] === '') {
            return ['ok' => false, 'message' => 'Nama kategori wajib diisi.'];
        }
        if ($payload['code'] === '') {
            $payload['code'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $payload['name']));
        }
        if ($id > 0) {
            $this->db->where('id', $id)->update('mst_component_category', $payload);
            return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id];
        }
        $this->db->insert('mst_component_category', $payload);
        return ['ok' => $this->db->affected_rows() > 0, 'id' => (int)$this->db->insert_id()];
    }

    public function list_components(array $filters = [], int $limit = 300): array
    {
        $result = $this->list_components_paginated([
            'q' => (string)($filters['q'] ?? ''),
            'status' => 'ALL',
            'type' => 'ALL',
            'division_id' => 0,
            'category_id' => 0,
            'page' => 1,
            'limit' => $limit,
        ]);
        return $result['rows'] ?? [];
    }

    public function list_components_paginated(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $type = strtoupper(trim((string)($filters['type'] ?? 'ALL')));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $categoryId = (int)($filters['category_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = (int)($filters['limit'] ?? 50);
        if ($limit <= 0 || $limit > 300) {
            $limit = 50;
        }

        $base = $this->db->from('mst_component c')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->join('mst_product_division pd', 'pd.id = c.product_division_id', 'left');

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('c.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('c.is_active', 0);
        }
        if (in_array($type, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $type);
        }
        if ($divisionId > 0) {
            $this->db->where('c.operational_division_id', $divisionId);
        }
        if ($categoryId > 0) {
            $this->db->where('c.component_category_id', $categoryId);
        }

        $total = (int)$this->db->count_all_results('', false);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $formulaCountExpr = $this->db->table_exists('mst_component_formula')
            ? "(
                SELECT COUNT(1)
                FROM mst_component_formula f
                WHERE f.component_id = c.id
            )"
            : '0';
        $componentUsageExpr = $this->db->table_exists('mst_component_formula')
            ? "(
                SELECT COUNT(DISTINCT f.component_id)
                FROM mst_component_formula f
                WHERE f.sub_component_id = c.id
            )"
            : '0';
        $productUsageExpr = $this->db->table_exists('mst_product_recipe')
            ? "(
                SELECT COUNT(DISTINCT r.product_id)
                FROM mst_product_recipe r
                WHERE r.component_id = c.id
            )"
            : '0';

        $stockHppLiveExpr = '0';
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $stockHppLiveExpr = "(
                SELECT s.avg_cost
                FROM inv_component_monthly_stock s
                WHERE s.component_id = c.id
                ORDER BY s.month_key DESC, s.updated_at DESC, s.last_movement_at DESC, s.id DESC
                LIMIT 1
            )";
        }

        $this->db->select(" 
            c.*,
            cat.name AS category_name,
            u.code AS uom_code,
            d.name AS division_name,
            pd.name AS product_division_name,
            {$stockHppLiveExpr} AS stock_hpp_live,
            {$formulaCountExpr} AS formula_count,
            {$componentUsageExpr} AS component_usage_count,
            {$productUsageExpr} AS product_usage_count
        ", false);
        $this->apply_component_display_order('d.name', 'c.component_type', 'c.component_name');
        $this->db->limit($limit, $offset);
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            if (((!isset($row['std_batch_qty'])) || (float)$row['std_batch_qty'] <= 0)
                && isset($row['yield_qty'])
                && (float)$row['yield_qty'] > 0) {
                $row['std_batch_qty'] = round((float)$row['yield_qty'], 4);
            }
            $stockHppLive = (float)($row['stock_hpp_live'] ?? 0);
            if ($stockHppLive > 0) {
                $row['hpp_live'] = round($stockHppLive, 6);
            } else {
                $mode = strtoupper((string)($row['variable_cost_mode'] ?? 'DEFAULT'));
                $percent = (float)($row['variable_cost_percent'] ?? 0);
                $summary = $this->component_formula_cost_summary((int)($row['id'] ?? 0), (int)($row['operational_division_id'] ?? 0), $mode, $percent);
                $yieldQty = max(1.0, (float)($row['yield_qty'] ?? $row['std_batch_qty'] ?? 1));
                $row['hpp_live'] = round((float)($summary['hpp_live'] ?? 0) / $yieldQty, 6);
            }
            $row['usage_count'] = (int)($row['component_usage_count'] ?? 0) + (int)($row['product_usage_count'] ?? 0);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function save_component_master(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $payload = [
            'component_code' => strtoupper(trim((string)($data['component_code'] ?? ''))),
            'component_name' => trim((string)($data['component_name'] ?? '')),
            'component_type' => strtoupper(trim((string)($data['component_type'] ?? 'BASE'))),
            'product_division_id' => (int)($data['product_division_id'] ?? 0),
            'operational_division_id' => (int)($data['operational_division_id'] ?? 0),
            'component_category_id' => (int)($data['component_category_id'] ?? 0),
            'uom_id' => (int)($data['uom_id'] ?? 0),
            'hpp_standard' => round((float)($data['hpp_standard'] ?? 0), 6),
            'yield_percent' => round((float)($data['yield_percent'] ?? 100), 2),
            'std_batch_qty' => round((float)($data['std_batch_qty'] ?? 1), 4),
            'process_loss_percent' => round((float)($data['process_loss_percent'] ?? 0), 2),
            'shelf_life_days' => (int)($data['shelf_life_days'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int)((bool)$data['is_active']) : 1,
        ];
        if (!$this->db->field_exists('yield_percent', 'mst_component')) {
            unset($payload['yield_percent']);
        }
        if (!$this->db->field_exists('std_batch_qty', 'mst_component')) {
            unset($payload['std_batch_qty']);
        }
        if ($this->db->field_exists('yield_qty', 'mst_component')) {
            $payload['yield_qty'] = (float)($data['std_batch_qty'] ?? $data['yield_qty'] ?? 1);
        }
        if (!$this->db->field_exists('process_loss_percent', 'mst_component')) {
            unset($payload['process_loss_percent']);
        }
        if (!$this->db->field_exists('hpp_standard', 'mst_component')) {
            unset($payload['hpp_standard']);
        }
        if (!$this->db->field_exists('shelf_life_days', 'mst_component')) {
            unset($payload['shelf_life_days']);
        }
        $varMode = strtoupper(trim((string)($data['variable_cost_mode'] ?? 'DEFAULT')));
        if (!in_array($varMode, ['DEFAULT', 'NONE', 'CUSTOM'], true)) {
            $varMode = 'DEFAULT';
        }
        $varPercent = $varMode === 'NONE' ? 0.0 : round((float)($data['variable_cost_percent'] ?? 0), 4);
        if ($this->db->field_exists('variable_cost_mode', 'mst_component')) {
            $payload['variable_cost_mode'] = $varMode;
        }
        if ($this->db->field_exists('variable_cost_percent', 'mst_component')) {
            $payload['variable_cost_percent'] = $varPercent;
        }
        if ($payload['component_name'] === '' || $payload['component_category_id'] <= 0 || $payload['uom_id'] <= 0) {
            return ['ok' => false, 'message' => 'Nama, kategori, dan UOM wajib diisi.'];
        }
        if ($payload['product_division_id'] <= 0) {
            return ['ok' => false, 'message' => 'Divisi produk wajib dipilih.'];
        }
        if ($payload['operational_division_id'] <= 0) {
            return ['ok' => false, 'message' => 'Divisi operasional wajib dipilih.'];
        }
        if (!in_array($payload['component_type'], ['BASE', 'PREPARE'], true)) {
            return ['ok' => false, 'message' => 'Tipe component harus BASE/PREPARE.'];
        }
        $category = $this->db->select('id, name' . ($this->db->field_exists('scope_type', 'mst_component_category') ? ', scope_type' : ''))
            ->from('mst_component_category')
            ->where('id', $payload['component_category_id'])
            ->limit(1)
            ->get()
            ->row_array();
        if (!$category) {
            return ['ok' => false, 'message' => 'Kategori component tidak ditemukan.'];
        }
        $existing = null;
        if ($id > 0) {
            $existing = $this->db->select('id, component_type, component_category_id')
                ->from('mst_component')
                ->where('id', $id)
                ->limit(1)
                ->get()
                ->row_array();
            if (!$existing) {
                return ['ok' => false, 'message' => 'Data component tidak ditemukan saat update.'];
            }
        }

        if (isset($category['scope_type'])) {
            $scope = strtoupper((string)$category['scope_type']);
            $isScopeValid = in_array($scope, ['ALL', $payload['component_type']], true);
            $isLegacySameCategory = $id > 0
                && $existing
                && (int)$existing['component_category_id'] === (int)$payload['component_category_id'];
            // Allow edit legacy row as long as category is not changed.
            if (!$isScopeValid && !$isLegacySameCategory) {
                return ['ok' => false, 'message' => 'Kategori tidak sesuai tipe component (' . $payload['component_type'] . ').'];
            }
        }
        if ($payload['component_code'] === '') {
            $payload['component_code'] = strtoupper(substr(preg_replace('/[^A-Z0-9]+/', '', $payload['component_name']), 0, 20));
        }

        $oldDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            if ($id > 0) {
                $this->db->where('id', $id)->update('mst_component', $payload);
                $err = $this->db->error();
                if (!empty($err['code'])) {
                    return ['ok' => false, 'message' => 'Gagal update component: ' . (string)$err['message']];
                }
                return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id];
            }
            $this->db->insert('mst_component', $payload);
            $err = $this->db->error();
            if (!empty($err['code'])) {
                return ['ok' => false, 'message' => 'Gagal simpan component: ' . (string)$err['message']];
            }
            return ['ok' => $this->db->affected_rows() > 0, 'id' => (int)$this->db->insert_id()];
        } finally {
            $this->db->db_debug = $oldDbDebug;
        }
    }

    public function list_components_for_mapping(int $limit = 1000): array
    {
        $limit = max(1, min(5000, $limit));
        $this->db->select('c.id, c.component_code, c.component_name, c.component_type, c.component_category_id, cat.name AS category_name')
            ->from('mst_component c')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->limit($limit);
        $this->apply_component_display_order('d.name', 'c.component_type', 'c.component_name');
        return $this->db->get()->result_array();
    }

    public function search_picker_options(string $entity, string $q = '', int $limit = 20, array $options = []): array
    {
        $entity = strtoupper(trim($entity));
        $q = trim($q);
        $limit = max(1, min(50, $limit));

        if ($entity === 'MATERIAL') {
            $this->db->select("m.id, m.material_code AS code, m.material_name AS name, 'MATERIAL' AS entity_type, m.content_uom_id AS uom_id, u.code AS uom_code, u.name AS uom_name", false);
            $this->db->from('mst_material m');
            $this->db->join('mst_uom u', 'u.id = m.content_uom_id', 'left');
            $this->db->where('m.is_active', 1);
            if ($q !== '') {
                $this->db->group_start()
                    ->like('m.material_code', $q)
                    ->or_like('m.material_name', $q)
                    ->group_end();
            }
            return $this->db
                ->order_by('m.material_name', 'ASC')
                ->limit($limit)
                ->get()
                ->result_array();
        }

        $excludeId = (int)($options['exclude_id'] ?? 0);
        $type = strtoupper(trim((string)($options['component_type'] ?? 'ALL')));
        $divisionId = (int)($options['division_id'] ?? 0);
        if (!in_array($type, ['ALL', 'BASE', 'PREPARE'], true)) {
            $type = 'ALL';
        }

        $divisionNameColumn = $this->division_name_column();
        $divisionCodeColumn = $this->division_code_column();
        $select = [
            'c.id',
            'c.component_code AS code',
            'c.component_name AS name',
            'c.component_type AS entity_type',
            'c.uom_id',
            'u.code AS uom_code',
            'u.name AS uom_name',
            'cat.name AS category_name',
            'c.operational_division_id',
        ];
        if ($divisionCodeColumn !== null) {
            $select[] = 'd.' . $divisionCodeColumn . ' AS division_code';
        }
        if ($divisionNameColumn !== null) {
            $select[] = 'd.' . $divisionNameColumn . ' AS division_name';
        }

        $this->db->select(implode(', ', $select), false);
        $this->db->from('mst_component c');
        $this->db->join('mst_uom u', 'u.id = c.uom_id', 'left');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        $this->db->where('c.is_active', 1);
        if ($excludeId > 0) {
            $this->db->where('c.id <>', $excludeId);
        }
        if ($divisionId > 0) {
            $this->db->where('c.operational_division_id', $divisionId);
        }
        if (in_array($type, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $type);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        $this->apply_component_display_order(
            $divisionNameColumn !== null ? ('d.' . $divisionNameColumn) : 'c.operational_division_id',
            'c.component_type',
            'c.component_name'
        );
        $rows = $this->db
            ->limit($limit)
            ->get()
            ->result_array();

        $locationType = strtoupper(trim((string)($options['location_type'] ?? '')));
        $batchDate = trim((string)($options['batch_date'] ?? ''));
        return $this->attach_component_picker_lot_meta($rows, $locationType, $divisionId > 0 ? $divisionId : null, $batchDate);
    }

    private function attach_component_picker_lot_meta(array $rows, string $locationType = '', ?int $divisionId = null, ?string $asOfDate = null): array
    {
        if (empty($rows)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $componentId = (int)($row['id'] ?? 0);
            $uomId = (int)($row['uom_id'] ?? 0);
            $snapshot = $this->component_stock_snapshot($componentId, $uomId, $divisionId, $locationType, null, $asOfDate);
            $summary = is_array($snapshot['lot_summary'] ?? null) ? $snapshot['lot_summary'] : $this->empty_component_lot_summary();
            $row['lot_count'] = (int)($summary['lot_count'] ?? 0);
            $row['lot_preview'] = $this->component_lot_preview_text($summary);
            $row['lot_strategy'] = (int)($summary['lot_count'] ?? 0) > 1 ? 'FIFO lot aktif' : 'Lot tunggal';
            $row['available_qty'] = round((float)($snapshot['available_qty'] ?? 0), 4);
            $row['avg_cost'] = round((float)($snapshot['unit_cost'] ?? $this->component_lot_average_cost($summary)), 6);
        }
        unset($row);

        return $rows;
    }

    private function component_lot_summary(string $locationType, ?int $divisionId, int $componentId, int $uomId, ?string $asOfDate = null): array
    {
        $summary = $this->empty_component_lot_summary();
        $summary['location_scope'] = $locationType;
        if ($componentId <= 0 || $uomId <= 0 || !$this->db->table_exists('inv_component_lot')) {
            return $summary;
        }
        $hasCutoffDate = is_string($asOfDate) && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', trim($asOfDate));
        $targetMonth = $hasCutoffDate ? $this->normalize_component_batch_target_month($asOfDate) : '';
        $targetMonthEnd = $hasCutoffDate ? date('Y-m-t', strtotime($targetMonth)) : '';

        $query = $this->db->select('l.id, l.lot_no, l.receipt_date, l.expiry_date, l.unit_cost, l.qty_balance, l.status')
            ->from('inv_component_lot l')
            ->select('l.location_type')
            ->where('l.component_id', $componentId)
            ->where('l.uom_id', $uomId)
            ->where('l.qty_balance >', 0)
            ->where_in('l.status', ['OPEN']);
        if ($hasCutoffDate) {
            $query->where('l.receipt_date >=', $targetMonth)
                ->where('l.receipt_date <=', $targetMonthEnd);
        }
        if ($locationType !== '') {
            $query->where('l.location_type', $locationType);
        }
        if ($divisionId !== null && $divisionId > 0) {
            $query->where('l.division_id', $divisionId);
        }

        $lotRows = $query
            ->order_by('l.receipt_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        $locationMap = [];
        foreach ($lotRows as $lotRow) {
            $unitCost = round((float)($lotRow['unit_cost'] ?? 0), 6);
            $qtyBalance = round((float)($lotRow['qty_balance'] ?? 0), 4);
            $lotLocationType = strtoupper(trim((string)($lotRow['location_type'] ?? '')));
            $summary['lot_count']++;
            $summary['balance_qty'] += $qtyBalance;
            $summary['total_value'] += round($qtyBalance * $unitCost, 2);
            $summary['min_unit_cost'] = $summary['min_unit_cost'] === 0.0 && empty($summary['rows']) ? $unitCost : min((float)$summary['min_unit_cost'], $unitCost);
            $summary['max_unit_cost'] = max((float)$summary['max_unit_cost'], $unitCost);
            if ($lotLocationType !== '') {
                $locationMap[$lotLocationType] = true;
            }
            $summary['rows'][] = [
                'id' => (int)($lotRow['id'] ?? 0),
                'lot_no' => (string)($lotRow['lot_no'] ?? ''),
                'location_type' => $lotLocationType,
                'receipt_date' => (string)($lotRow['receipt_date'] ?? ''),
                'expiry_date' => (string)($lotRow['expiry_date'] ?? ''),
                'qty_balance' => $qtyBalance,
                'unit_cost' => $unitCost,
                'total_value' => round($qtyBalance * $unitCost, 2),
                'status' => (string)($lotRow['status'] ?? ''),
            ];
        }

        $summary['balance_qty'] = round((float)$summary['balance_qty'], 4);
        $summary['location_count'] = count($locationMap);
        $summary['total_value'] = round((float)$summary['total_value'], 2);
        $summary['has_mixed_cost'] = $summary['lot_count'] > 1 && abs((float)$summary['max_unit_cost'] - (float)$summary['min_unit_cost']) > 0.0001;
        return $summary;
    }

    private function resolve_component_selected_lot(array $lotSummary, ?int $selectedLotId = null): ?array
    {
        $lotRows = array_values((array)($lotSummary['rows'] ?? []));
        if (empty($lotRows)) {
            return null;
        }

        $selectedRow = null;
        $targetLotId = $selectedLotId !== null && $selectedLotId > 0 ? (int)$selectedLotId : 0;
        if ($targetLotId > 0) {
            foreach ($lotRows as $lotRow) {
                if ((int)($lotRow['id'] ?? 0) === $targetLotId) {
                    $selectedRow = $lotRow;
                    break;
                }
            }
        }
        if ($selectedRow === null && count($lotRows) === 1) {
            $selectedRow = $lotRows[0];
        }
        if ($selectedRow === null) {
            return null;
        }

        return [
            'id' => (int)($selectedRow['id'] ?? 0),
            'location_type' => (string)($selectedRow['location_type'] ?? ''),
            'receipt_date' => (string)($selectedRow['receipt_date'] ?? ''),
            'expiry_date' => (string)($selectedRow['expiry_date'] ?? ''),
            'qty_balance' => round((float)($selectedRow['qty_balance'] ?? 0), 4),
            'unit_cost' => round((float)($selectedRow['unit_cost'] ?? 0), 6),
            'total_value' => round((float)($selectedRow['total_value'] ?? 0), 2),
            'profile_label' => $this->component_lot_profile_text($selectedRow),
        ];
    }

    private function component_lot_profile_text(array $lotRow): string
    {
        $parts = [];
        $receiptDate = trim((string)($lotRow['receipt_date'] ?? ''));
        if ($receiptDate !== '') {
            $parts[] = 'Terima ' . $receiptDate;
        }
        $parts[] = 'Qty ' . number_format((float)($lotRow['qty_balance'] ?? 0), 2, ',', '.');
        $parts[] = 'Cost ' . number_format((float)($lotRow['unit_cost'] ?? 0), 2, ',', '.');
        $expiryDate = trim((string)($lotRow['expiry_date'] ?? ''));
        if ($expiryDate !== '') {
            $parts[] = 'Exp ' . $expiryDate;
        }
        $locationType = strtoupper(trim((string)($lotRow['location_type'] ?? '')));
        if ($locationType === 'BAR' || $locationType === 'KITCHEN' || $locationType === 'ROASTERY') {
            $parts[] = 'Reguler';
        } elseif ($locationType === 'BAR_EVENT' || $locationType === 'KITCHEN_EVENT' || $locationType === 'ROASTERY_EVENT') {
            $parts[] = 'Event';
        } elseif ($locationType !== '') {
            $parts[] = $locationType;
        }
        return implode(' | ', $parts);
    }

    private function component_lot_average_cost(array $summary): float
    {
        $balanceQty = (float)($summary['balance_qty'] ?? 0);
        if ($balanceQty <= 0) {
            return 0.0;
        }
        return round((float)($summary['total_value'] ?? 0) / $balanceQty, 6);
    }

    private function component_lot_preview_text(array $summary): string
    {
        $rows = array_slice(array_values((array)($summary['rows'] ?? [])), 0, 3);
        if (empty($rows)) {
            return 'Belum ada lot aktif';
        }

        $parts = [];
        $showLocation = trim((string)($summary['location_scope'] ?? '')) === '' && (int)($summary['location_count'] ?? 0) > 1;
        foreach ($rows as $row) {
            $locationSuffix = '';
            if ($showLocation) {
                $locationType = strtoupper(trim((string)($row['location_type'] ?? '')));
                if ($locationType === 'BAR' || $locationType === 'KITCHEN' || $locationType === 'ROASTERY') {
                    $locationSuffix = ' / Reguler';
                } elseif ($locationType === 'BAR_EVENT' || $locationType === 'KITCHEN_EVENT' || $locationType === 'ROASTERY_EVENT') {
                    $locationSuffix = ' / Event';
                } elseif ($locationType !== '') {
                    $locationSuffix = ' / ' . $locationType;
                }
            }
            $parts[] = trim((string)($row['lot_no'] ?? '-')) . ' ('
                . number_format((float)($row['qty_balance'] ?? 0), 2, ',', '.')
                . ' @ ' . number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.') . ')' . $locationSuffix;
        }
        $suffix = ((int)($summary['lot_count'] ?? 0) > count($rows)) ? ' +' . (((int)$summary['lot_count']) - count($rows)) . ' lot' : '';
        return implode(' | ', $parts) . $suffix;
    }

    private function attach_component_adjustment_posting_meta(array $rows): array
    {
        if (empty($rows) || !$this->db->table_exists('inv_component_movement_log')) {
            return $rows;
        }

        $adjustmentIds = [];
        $lineIds = [];
        foreach ($rows as $row) {
            $adjustmentId = (int)($row['adjustment_id'] ?? 0);
            $lineId = (int)($row['id'] ?? 0);
            if ($adjustmentId > 0) {
                $adjustmentIds[$adjustmentId] = $adjustmentId;
            }
            if ($lineId > 0) {
                $lineIds[$lineId] = $lineId;
            }
        }
        if (empty($adjustmentIds) || empty($lineIds)) {
            return $rows;
        }

        $movementRows = $this->db->select('source_id, source_line_id, movement_type, SUM(total_cost) AS total_cost', false)
            ->from('inv_component_movement_log')
            ->where('source_table', 'inv_component_adjustment')
            ->where_in('source_id', array_values($adjustmentIds))
            ->where_in('source_line_id', array_values($lineIds))
            ->group_by(['source_id', 'source_line_id', 'movement_type'])
            ->get()
            ->result_array();

        $movementMap = [];
        foreach ($movementRows as $movementRow) {
            $key = (int)($movementRow['source_id'] ?? 0) . '|' . (int)($movementRow['source_line_id'] ?? 0);
            if (!isset($movementMap[$key])) {
                $movementMap[$key] = [
                    'value_spoil' => 0.0,
                    'value_waste' => 0.0,
                    'value_plus' => 0.0,
                    'value_minus' => 0.0,
                ];
            }
            $movementType = strtoupper(trim((string)($movementRow['movement_type'] ?? '')));
            $target = null;
            if ($movementType === 'SPOIL') {
                $target = 'value_spoil';
            } elseif ($movementType === 'WASTE') {
                $target = 'value_waste';
            } elseif ($movementType === 'ADJUSTMENT_PLUS') {
                $target = 'value_plus';
            } elseif ($movementType === 'ADJUSTMENT_MINUS') {
                $target = 'value_minus';
            }
            if ($target !== null) {
                $movementMap[$key][$target] = round((float)($movementRow['total_cost'] ?? 0), 2);
            }
        }

        $lotIssueMap = [];
        if ($this->db->table_exists('inv_component_lot_issue_log') && $this->db->table_exists('inv_component_lot_issue_line') && $this->db->table_exists('inv_component_lot')) {
            $issueRows = $this->db->select('il.source_id, il.source_line_id, l.lot_no, li.qty_out, li.unit_cost', false)
                ->from('inv_component_lot_issue_log il')
                ->join('inv_component_lot_issue_line li', 'li.issue_id = il.id', 'inner')
                ->join('inv_component_lot l', 'l.id = li.lot_id', 'left')
                ->where('il.source_table', 'inv_component_adjustment')
                ->where_in('il.source_id', array_values($adjustmentIds))
                ->where_in('il.source_line_id', array_values($lineIds))
                ->where('il.status', 'POSTED')
                ->order_by('il.id', 'ASC')
                ->order_by('li.id', 'ASC')
                ->get()
                ->result_array();
            foreach ($issueRows as $issueRow) {
                $key = (int)($issueRow['source_id'] ?? 0) . '|' . (int)($issueRow['source_line_id'] ?? 0);
                if (!isset($lotIssueMap[$key])) {
                    $lotIssueMap[$key] = [];
                }
                $lotIssueMap[$key][] = trim((string)($issueRow['lot_no'] ?? '-'))
                    . ' (' . number_format((float)($issueRow['qty_out'] ?? 0), 2, ',', '.')
                    . ' @ ' . number_format((float)($issueRow['unit_cost'] ?? 0), 2, ',', '.') . ')';
            }
        }

        // Lot yang DIBUAT untuk ADJ_PLUS — tersimpan di lot_no_snapshot movement log
        $lotPlusMap = [];
        if ($this->db->field_exists('lot_no_snapshot', 'inv_component_movement_log')) {
            $plusLotRows = $this->db->select('source_id, source_line_id, MAX(lot_no_snapshot) AS lot_no', false)
                ->from('inv_component_movement_log')
                ->where('source_table', 'inv_component_adjustment')
                ->where('movement_type', 'ADJUSTMENT_PLUS')
                ->where('lot_no_snapshot IS NOT NULL', null, false)
                ->where_in('source_id', array_values($adjustmentIds))
                ->where_in('source_line_id', array_values($lineIds))
                ->group_by(['source_id', 'source_line_id'])
                ->get()
                ->result_array();
            foreach ($plusLotRows as $plusRow) {
                $k = (int)($plusRow['source_id'] ?? 0) . '|' . (int)($plusRow['source_line_id'] ?? 0);
                $lotPlusMap[$k] = trim((string)($plusRow['lot_no'] ?? ''));
            }
        }

        $snapshotCache = [];
        foreach ($rows as &$row) {
            $key = (int)($row['adjustment_id'] ?? 0) . '|' . (int)($row['id'] ?? 0);
            $movementMeta = $movementMap[$key] ?? null;
            if ($movementMeta === null) {
                $snapshotKey = implode('|', [
                    strtoupper(trim((string)($row['location_type'] ?? ''))),
                    $this->nullable_int($row['division_id'] ?? null) ?? 0,
                    (int)($row['component_id'] ?? 0),
                    (int)($row['uom_id'] ?? 0),
                    $this->nullable_int($row['selected_lot_id'] ?? null) ?? 0,
                ]);
                if (!array_key_exists($snapshotKey, $snapshotCache)) {
                    $snapshotCache[$snapshotKey] = $this->component_stock_snapshot(
                        (int)($row['component_id'] ?? 0),
                        (int)($row['uom_id'] ?? 0),
                        $this->nullable_int($row['division_id'] ?? null),
                        (string)($row['location_type'] ?? ''),
                        $this->nullable_int($row['selected_lot_id'] ?? null)
                    );
                }
                $snapshotUnitCost = (float)($snapshotCache[$snapshotKey]['unit_cost'] ?? 0);
                $movementMeta = [
                    'value_spoil' => round((float)($row['qty_spoil'] ?? 0) * $snapshotUnitCost, 2),
                    'value_waste' => round((float)($row['qty_waste'] ?? 0) * $snapshotUnitCost, 2),
                    'value_plus' => round((float)($row['qty_adjust_pos'] ?? 0) * (float)($row['unit_cost'] ?? 0), 2),
                    'value_minus' => round((float)($row['qty_adjust_neg'] ?? 0) * $snapshotUnitCost, 2),
                ];
            }
            $row = array_merge($row, $movementMeta);
            $row['total_adjustment_value'] = round(
                (float)($row['value_spoil'] ?? 0)
                + (float)($row['value_waste'] ?? 0)
                + (float)($row['value_plus'] ?? 0)
                + (float)($row['value_minus'] ?? 0),
                2
            );
            $lotParts = !empty($lotIssueMap[$key]) ? $lotIssueMap[$key] : [];
            if (empty($lotParts) && !empty($lotPlusMap[$key])) {
                $lotParts[] = $lotPlusMap[$key] . ' (+baru)';
            }
            $row['lot_issue_preview'] = !empty($lotParts) ? implode(' | ', $lotParts) : '-';
        }
        unset($row);

        return $rows;
    }

    public function list_unmapped_components(int $limit = 300): array
    {
        $limit = max(1, min(2000, $limit));
        $hasScope = $this->db->field_exists('scope_type', 'mst_component_category');
        $scopeSelect = $hasScope ? 'cat.scope_type' : "'ALL'";
        $this->db->select("c.id, c.component_code, c.component_name, c.component_type, c.component_category_id, cat.name AS category_name, {$scopeSelect} AS category_scope", false);
        $this->db->from('mst_component c');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');
        if ($hasScope) {
            $this->db->group_start()
                ->where('cat.id IS NULL', null, false)
                ->or_group_start()
                    ->where('cat.scope_type !=', 'ALL')
                    ->where('cat.scope_type != c.component_type', null, false)
                ->group_end()
            ->group_end();
        } else {
            $this->db->where('cat.id IS NULL', null, false);
        }
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }

    public function quick_map_component_category(int $componentId, int $categoryId): array
    {
        if ($componentId <= 0 || $categoryId <= 0) {
            return ['ok' => false, 'message' => 'Component dan kategori wajib dipilih.'];
        }
        $component = $this->db->select('id, component_type')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }
        $category = $this->db->select('id, name' . ($this->db->field_exists('scope_type', 'mst_component_category') ? ', scope_type' : ''))
            ->from('mst_component_category')
            ->where('id', $categoryId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$category) {
            return ['ok' => false, 'message' => 'Kategori tidak ditemukan.'];
        }
        if (isset($category['scope_type'])) {
            $scope = strtoupper((string)$category['scope_type']);
            $componentType = strtoupper((string)$component['component_type']);
            if (!in_array($scope, ['ALL', $componentType], true)) {
                return ['ok' => false, 'message' => 'Scope kategori tidak cocok untuk tipe component ' . $componentType . '.'];
            }
        }
        $this->db->where('id', $componentId)->update('mst_component', ['component_category_id' => $categoryId]);
        return ['ok' => true];
    }

    public function list_component_formulas(int $componentId): array
    {
        if (array_key_exists($componentId, $this->componentFormulaLinesCache)) {
            return $this->componentFormulaLinesCache[$componentId];
        }
        $uomSelect = "COALESCE(mu.code, cu.code) AS uom_code";
        $materialExpr = "COALESCE(f.material_id, i.material_id)";
        if (!$this->db->field_exists('material_id', 'mst_component_formula')) {
            $materialExpr = "i.material_id";
        }
        $divJoin = $this->db->field_exists('source_division_id', 'mst_component_formula')
            ? ', od.name AS source_division_name'
            : '';
        $rows = $this->db->select("f.*, {$materialExpr} AS material_id, m.material_name AS material_name, c.component_name AS sub_component_name, {$uomSelect}{$divJoin}", false)
            ->from('mst_component_formula f')
            ->join('mst_item i', 'i.id = f.material_item_id', 'left')
            ->join('mst_material m', "m.id = {$materialExpr}", 'left')
            ->join('mst_component c', 'c.id = f.sub_component_id', 'left')
            ->join('mst_uom mu', 'mu.id = m.content_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = c.uom_id', 'left');
        if ($this->db->field_exists('source_division_id', 'mst_component_formula')) {
            $this->db->join('mst_operational_division od', 'od.id = f.source_division_id', 'left');
        }
        $rows = $this->db->where('f.component_id', $componentId)
            ->order_by('f.line_no', 'ASC')
            ->get()->result_array();
        $this->componentFormulaLinesCache[$componentId] = $rows;
        return $rows;
    }

    public function list_component_formula_components_paginated(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $type = strtoupper(trim((string)($filters['type'] ?? 'ALL')));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $categoryId = (int)($filters['category_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = (int)($filters['limit'] ?? 50);
        if ($limit < 0 || $limit > 300) {
            $limit = 50;
        }

        $this->db->from('mst_component c')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('c.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('c.is_active', 0);
        }
        if (in_array($type, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $type);
        }
        if ($divisionId > 0) {
            $this->db->where('c.operational_division_id', $divisionId);
        }
        if ($categoryId > 0) {
            $this->db->where('c.component_category_id', $categoryId);
        }

        $total = (int)$this->db->count_all_results('', false);
        if ($limit === 0) {
            $totalPages = 1;
            $page = 1;
            $offset = 0;
        } else {
            $totalPages = max(1, (int)ceil($total / $limit));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $limit;
        }

        $componentUsageExpr = $this->db->table_exists('mst_component_formula')
            ? "(
                SELECT COUNT(DISTINCT f.component_id)
                FROM mst_component_formula f
                WHERE f.sub_component_id = c.id
            )"
            : '0';
        $productUsageExpr = $this->db->table_exists('mst_product_recipe')
            ? "(
                SELECT COUNT(DISTINCT r.product_id)
                FROM mst_product_recipe r
                WHERE r.component_id = c.id
            )"
            : '0';
        $outputBatchExpr = '1';
        if ($this->db->field_exists('std_batch_qty', 'mst_component')) {
            $outputBatchExpr = 'COALESCE(c.std_batch_qty, 1)';
        } elseif ($this->db->field_exists('yield_qty', 'mst_component')) {
            $outputBatchExpr = 'COALESCE(c.yield_qty, 1)';
        }

        $this->db->select("
            c.id,
            c.component_code,
            c.component_name,
            c.component_type,
            c.operational_division_id,
            c.component_category_id,
            c.uom_id,
            c.is_active,
            c.hpp_standard,
            c.variable_cost_mode,
            c.variable_cost_percent,
            {$outputBatchExpr} AS output_batch_qty,
            cat.name AS category_name,
            d.name AS division_name,
            u.code AS uom_code,
            (
                SELECT COUNT(1)
                FROM mst_component_formula f
                WHERE f.component_id = c.id
            ) AS formula_line_count,
            {$componentUsageExpr} AS component_usage_count,
            {$productUsageExpr} AS product_usage_count
        ", false);
        $this->apply_component_display_order('d.name', 'c.component_type', 'c.component_name');
        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $mode = strtoupper((string)($row['variable_cost_mode'] ?? 'DEFAULT'));
            $percent = (float)($row['variable_cost_percent'] ?? 0);
            $summary = $this->component_formula_cost_summary((int)($row['id'] ?? 0), (int)($row['operational_division_id'] ?? 0), $mode, $percent);
            $yieldQty = max(1.0, (float)($row['output_batch_qty'] ?? $row['yield_qty'] ?? 1));
            $row['hpp_live'] = round((float)($summary['hpp_live'] ?? 0) / $yieldQty, 6);
            $row['hpp_total'] = round((float)($summary['hpp_total'] ?? 0) / $yieldQty, 6);
            $row['usage_count'] = (int)($row['component_usage_count'] ?? 0) + (int)($row['product_usage_count'] ?? 0);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Read-only HPP suggestions for a component value reconciliation.
     * The suggestions are guidance only; posting is still performed by the
     * dedicated InventoryValueReconciliationService and never changes qty.
     */
    public function component_hpp_suggestions(array $context): array
    {
        $componentId = (int)($context['component_id'] ?? 0);
        $contextDivisionId = (int)($context['division_id'] ?? 0);
        $contextLocationType = strtoupper(trim((string)($context['location_type'] ?? '')));
        $contextUomId = (int)($context['uom_id'] ?? 0);
        $stock = is_array($context['stock'] ?? null) ? $context['stock'] : [];
        $lots = is_array($context['lots'] ?? null) ? $context['lots'] : [];
        $stockQty = round((float)($stock['stock_qty'] ?? $stock['closing_qty'] ?? 0), 4);
        $stockHpp = round((float)($stock['stock_avg_cost'] ?? $stock['avg_cost'] ?? 0), 6);

        $lotQty = 0.0;
        $lotValue = 0.0;
        foreach ($lots as $lot) {
            $qty = max(0.0, (float)($lot['qty_balance'] ?? 0));
            $lotQty += $qty;
            $lotValue += $qty * (float)($lot['unit_cost'] ?? 0);
        }
        $lotQty = round($lotQty, 4);
        $lotValue = round($lotValue, 2);
        $lotHpp = $lotQty > 0.0001 ? round($lotValue / $lotQty, 6) : 0.0;

        $formulaHppLive = 0.0;
        $formulaHppStandard = 0.0;
        $formulaOutputQty = 0.0;
        $formulaReady = false;
        $formulaIdentityCompatible = true;
        $formulaLiveCompatible = true;
        $formulaScopeWarnings = [];
        $formula = $componentId > 0 ? $this->component_formula_detail($componentId, true) : ['ok' => false];
        if (!empty($formula['ok'])) {
            $summary = is_array($formula['summary'] ?? null) ? $formula['summary'] : [];
            $formulaComponent = is_array($formula['component'] ?? null) ? $formula['component'] : [];
            $recipeDivisionId = (int)($formulaComponent['operational_division_id'] ?? 0);
            $recipeUomId = (int)($formulaComponent['uom_id'] ?? 0);
            if ($contextDivisionId !== $recipeDivisionId && ($contextDivisionId > 0 || $recipeDivisionId > 0)) {
                $formulaIdentityCompatible = false;
                $formulaScopeWarnings[] = 'Scope divisi resep (' . ($recipeDivisionId > 0 ? $recipeDivisionId : 'pusat') . ') berbeda dari saldo (' . ($contextDivisionId > 0 ? $contextDivisionId : 'pusat') . ').';
            }
            if ($contextUomId > 0 && $recipeUomId > 0 && $contextUomId !== $recipeUomId) {
                $formulaIdentityCompatible = false;
                $formulaScopeWarnings[] = 'UOM resep berbeda dari UOM saldo yang direkonsiliasi.';
            }
            $recipeLocation = $recipeDivisionId > 0 ? $this->regular_component_location_for_division($recipeDivisionId) : null;
            if ($contextLocationType !== '' && $recipeLocation !== null && strtoupper($recipeLocation) !== $contextLocationType) {
                $formulaLiveCompatible = false;
                $formulaScopeWarnings[] = 'Lokasi live resep memakai ' . strtoupper($recipeLocation) . ', bukan ' . $contextLocationType . '.';
            }

            $configuredOutput = null;
            if (array_key_exists('std_batch_qty', $formulaComponent)) {
                $configuredOutput = (float)$formulaComponent['std_batch_qty'];
            } elseif (array_key_exists('yield_qty', $formulaComponent)) {
                $configuredOutput = (float)$formulaComponent['yield_qty'];
            }
            $formulaOutputQty = $configuredOutput === null || $configuredOutput > 0.0001
                ? round((float)($summary['output_qty'] ?? 0), 4)
                : 0.0;
            if ($formulaOutputQty > 0.0001 && $formulaIdentityCompatible) {
                $formulaHppLive = round((float)($summary['total_cogs_live'] ?? 0) / $formulaOutputQty, 6);
                $formulaHppStandard = round((float)($summary['total_cogs_std'] ?? 0) / $formulaOutputQty, 6);
                $formulaReady = ($formulaHppLive > 0.000001 || $formulaHppStandard > 0.000001);
            }
            if ($configuredOutput !== null && $configuredOutput <= 0.0001) {
                $formulaScopeWarnings[] = 'Output/batch resep belum valid (harus lebih besar dari nol).';
            }
        }

        $masterStandard = 0.0;
        if (!empty($formula['ok']) && isset($formula['summary']['hpp_master_standard'])) {
            $masterStandard = round((float)$formula['summary']['hpp_master_standard'], 6);
        } elseif ($componentId > 0 && $this->db->table_exists('mst_component')) {
            $master = $this->db->select('hpp_standard')
                ->from('mst_component')
                ->where('id', $componentId)
                ->limit(1)
                ->get()
                ->row_array();
            $masterStandard = round((float)($master['hpp_standard'] ?? 0), 6);
        }

        $sources = [
            [
                'key' => 'lot_live',
                'label' => 'HPP live lot OPEN (rata-rata tertimbang)',
                'value' => $lotHpp,
                'usable' => $lotHpp > 0.000001,
                'resolution_mode' => 'LOT_TO_STOCK',
                'note' => $lotQty > 0.0001 ? ($lotQty . ' unit saldo lot OPEN') : 'Tidak ada saldo lot OPEN',
            ],
            [
                'key' => 'recipe_live',
                'label' => 'HPP live resep/formula',
                'value' => $formulaHppLive,
                'usable' => $formulaLiveCompatible && $formulaHppLive > 0.000001,
                'resolution_mode' => 'MANUAL_TOTAL_VALUE',
                'note' => $formulaOutputQty > 0.0001 ? 'Berdasarkan biaya bahan live tertimbang dan output resep' : 'Resep atau output belum tersedia',
            ],
            [
                'key' => 'recipe_standard',
                'label' => 'HPP normal resep/formula',
                'value' => $formulaHppStandard,
                'usable' => $formulaIdentityCompatible && $formulaHppStandard > 0.000001,
                'resolution_mode' => 'MANUAL_TOTAL_VALUE',
                'note' => $formulaOutputQty > 0.0001 ? 'Fallback dari biaya standar resep dan output normal' : 'Resep atau output belum tersedia',
            ],
            [
                'key' => 'master_standard',
                'label' => 'HPP standar master component',
                'value' => $masterStandard,
                'usable' => $formulaIdentityCompatible && $masterStandard > 0.000001,
                'resolution_mode' => 'MANUAL_TOTAL_VALUE',
                'note' => 'Fallback terakhir dari master component',
            ],
        ];

        $recommendedHpp = 0.0;
        $recommendedSource = '';
        foreach ($sources as $source) {
            if (!empty($source['usable'])) {
                $recommendedHpp = (float)$source['value'];
                $recommendedSource = (string)$source['label'];
                break;
            }
        }

        return [
            'ok' => $componentId > 0,
            'stock_qty' => $stockQty,
            'stock_hpp' => $stockHpp,
            'lot_qty' => $lotQty,
            'lot_value' => $lotValue,
            'formula_output_qty' => $formulaOutputQty,
            'recommended_hpp' => round($recommendedHpp, 6),
            'recommended_source' => $recommendedSource,
            'warning' => implode(' ', array_filter(array_merge(
                $stockQty > 0.0001 && $stockHpp <= 0.000001
                    ? ['HPP stok bulanan saat ini nol/negatif. Jangan jadikan angka itu sebagai target; verifikasi lot atau gunakan saran resep/standar.']
                    : [],
                $formulaScopeWarnings
            ))),
            'formula_identity_compatible' => $formulaIdentityCompatible,
            'formula_live_compatible' => $formulaLiveCompatible,
            'sources' => $sources,
        ];
    }

    public function component_formula_detail(int $componentId, bool $weightedLiveLots = false): array
    {
        $componentSelect = 'c.id, c.component_code, c.component_name, c.component_type, c.operational_division_id, c.hpp_standard, c.variable_cost_mode, c.variable_cost_percent, c.uom_id, u.code AS uom_code';
        if ($this->db->field_exists('std_batch_qty', 'mst_component')) {
            $componentSelect .= ', c.std_batch_qty';
        } elseif ($this->db->field_exists('yield_qty', 'mst_component')) {
            $componentSelect .= ', c.yield_qty';
        }
        $component = $this->db->select($componentSelect)
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }
        $lines = $this->list_component_formulas($componentId);
        $detailedLines = [];
        $materialCount = 0;
        $componentCount = 0;
        $directCostLive = 0.0;
        $directCostStandard = 0.0;
        $outputBaseQty = (float)($component['std_batch_qty'] ?? ($component['yield_qty'] ?? 1));
        if ($outputBaseQty <= 0) {
            $outputBaseQty = 1;
        }
        $potencyMin = null;
        $potencyBottleneck = null;
        foreach ($lines as $line) {
            $cost = $this->resolve_formula_line_cost($line, (int)$component['operational_division_id'], $weightedLiveLots);
            $lineQty = round((float)($line['qty'] ?? 0), 4);
            if (strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL') {
                $materialCount++;
            } else {
                $componentCount++;
            }
            $lineLiveValue = round($lineQty * (float)$cost['live_unit_cost'], 6);
            $lineStdValue = round($lineQty * (float)$cost['standard_unit_cost'], 6);
            $directCostLive += $lineLiveValue;
            $directCostStandard += $lineStdValue;
            $line['standard_unit_cost'] = (float)$cost['standard_unit_cost'];
            $line['live_unit_cost'] = (float)$cost['live_unit_cost'];
            $line['line_live_total'] = $lineLiveValue;
            $line['line_standard_total'] = $lineStdValue;
            $line['source_label'] = (string)$cost['source_label'];
            $line['available_qty'] = (float)$cost['available_qty'];
            $line['live_cost_source'] = (string)$cost['live_cost_source'];
            $line['live_cost_source_label'] = (string)$cost['live_cost_source_label'];
            $linePotency = 0.0;
            if ($lineQty > 0) {
                $linePotency = ((float)$line['available_qty'] / $lineQty) * $outputBaseQty;
            }
            if ($linePotency < 0) {
                $linePotency = 0.0;
            }
            $line['potential_output_qty'] = round($linePotency, 4);
            if ($potencyMin === null || $linePotency < $potencyMin) {
                $potencyMin = $linePotency;
                $potencyBottleneck = strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL'
                    ? (string)($line['material_name'] ?? '-')
                    : (string)($line['sub_component_name'] ?? '-');
            }
            $detailedLines[] = $line;
        }

        $mode = strtoupper((string)($component['variable_cost_mode'] ?? 'DEFAULT'));
        $percent = (float)($component['variable_cost_percent'] ?? 0);
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
        } else {
            $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
        }
        $variableStd = round($directCostStandard * ($effectivePercent / 100), 6);
        $variableLive = round($directCostLive * ($effectivePercent / 100), 6);
        $totalCogsStd = round($directCostStandard + $variableStd, 6);
        $totalCogsLive = round($directCostLive + $variableLive, 6);

        return [
            'ok' => true,
            'component' => $component,
            'summary' => [
                'line_count' => count($detailedLines),
                'material_count' => $materialCount,
                'component_count' => $componentCount,
                'hpp_master_standard' => round((float)($component['hpp_standard'] ?? 0), 6),
                'direct_cost_standard' => round($directCostStandard, 6),
                'direct_cost_live' => round($directCostLive, 6),
                'variable_cost_std' => $variableStd,
                'variable_cost_live' => $variableLive,
                'total_cogs_std' => $totalCogsStd,
                'total_cogs_live' => $totalCogsLive,
                'variable_cost_mode' => $mode,
                'variable_cost_percent' => $effectivePercent,
                'std_batch_qty' => round((float)($component['std_batch_qty'] ?? 1), 4),
                'output_qty' => round($outputBaseQty, 4),
                'output_uom_code' => (string)($component['uom_code'] ?? '-'),
                'potential_output_total' => round((float)($potencyMin ?? 0), 4),
                'bottleneck_source' => (string)($potencyBottleneck ?? '-'),
            ],
            'lines' => $detailedLines,
        ];
    }

    public function component_usage_detail(int $componentId): array
    {
        $component = $this->db->select('c.id, c.component_code, c.component_name, c.component_type, u.name AS uom_name')
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }

        $componentRows = [];
        if ($this->db->table_exists('mst_component_formula')) {
            $componentRows = $this->db->select("'COMPONENT' AS usage_type, parent.id AS target_id, parent.component_code AS target_code, parent.component_name AS target_name, parent.component_type AS target_kind, d.name AS division_name, COUNT(f.id) AS usage_line_count", false)
                ->from('mst_component_formula f')
                ->join('mst_component parent', 'parent.id = f.component_id', 'inner')
                ->join('mst_operational_division d', 'd.id = parent.operational_division_id', 'left')
                ->where('f.sub_component_id', $componentId)
                ->group_by(['parent.id', 'parent.component_code', 'parent.component_name', 'parent.component_type', 'd.name'])
                ->order_by('d.name', 'ASC')
                ->order_by($this->component_type_sort_sql('parent.component_type'), '', false)
                ->order_by('parent.component_name', 'ASC')
                ->get()
                ->result_array();
        }

        $productRows = [];
        if ($this->db->table_exists('mst_product_recipe')) {
            $productRows = $this->db->select("'PRODUCT' AS usage_type, p.id AS target_id, p.product_code AS target_code, p.product_name AS target_name, 'PRODUCT' AS target_kind, pd.name AS division_name, COUNT(r.id) AS usage_line_count", false)
                ->from('mst_product_recipe r')
                ->join('mst_product p', 'p.id = r.product_id', 'inner')
                ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
                ->where('r.component_id', $componentId)
                ->group_by(['p.id', 'p.product_code', 'p.product_name', 'pd.name'])
                ->order_by('pd.name', 'ASC')
                ->order_by('p.product_name', 'ASC')
                ->get()
                ->result_array();
        }

        $rows = array_merge($componentRows, $productRows);
        usort($rows, static function (array $left, array $right): int {
            $typeCompare = strcmp((string)($left['usage_type'] ?? ''), (string)($right['usage_type'] ?? ''));
            if ($typeCompare !== 0) {
                return $typeCompare;
            }
            return strcasecmp((string)($left['target_name'] ?? ''), (string)($right['target_name'] ?? ''));
        });

        return [
            'ok' => true,
            'component' => $component,
            'rows' => $rows,
            'summary' => [
                'component_usage_count' => count($componentRows),
                'product_usage_count' => count($productRows),
                'usage_count' => count($rows),
            ],
        ];
    }

    private function formula_material_expression(): string
    {
        if (!$this->db->field_exists('material_id', 'mst_component_formula')) {
            return 'i.material_id';
        }

        return 'COALESCE(f.material_id, i.material_id)';
    }

    private function component_formula_material_name(int $materialId): string
    {
        $row = $this->db->select('material_name')
            ->from('mst_material')
            ->where('id', $materialId)
            ->limit(1)
            ->get()
            ->row_array();

        $name = trim((string)($row['material_name'] ?? ''));
        return $name !== '' ? $name : ('Material #' . $materialId);
    }

    private function has_component_formula_material_duplicate(int $componentId, int $materialId, int $excludeId = 0): bool
    {
        if ($componentId <= 0 || $materialId <= 0) {
            return false;
        }

        $materialExpr = $this->formula_material_expression();
        $this->db->select('f.id', false)
            ->from('mst_component_formula f')
            ->join('mst_item i', 'i.id = f.material_item_id', 'left')
            ->where('f.component_id', $componentId)
            ->where('f.line_type', 'MATERIAL')
            ->where($materialExpr . ' = ' . $materialId, null, false)
            ->limit(1);
        if ($excludeId > 0) {
            $this->db->where('f.id <>', $excludeId);
        }

        return (bool)$this->db->get()->row_array();
    }

    private function detect_duplicate_formula_material_in_rows(array $rows): ?int
    {
        $materialCol = $this->formula_material_column();
        $seen = [];

        foreach ($rows as $row) {
            if (strtoupper(trim((string)($row['line_type'] ?? ''))) !== 'MATERIAL') {
                continue;
            }
            $materialId = (int)($row[$materialCol] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            if (isset($seen[$materialId])) {
                return $materialId;
            }
            $seen[$materialId] = true;
        }

        return null;
    }

    public function save_component_formula_bulk(
        int $componentId,
        array $lines,
        string $expectedRevision = '',
        array $auditContext = []
    ): array
    {
        $component = $this->db->select('id, component_type')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', $expectedRevision) !== 1) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Snapshot formula tidak valid. Muat ulang halaman sebelum menyimpan.',
            ];
        }

        $normalized = [];
        $lineNo = 1;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $lineType = strtoupper(trim((string)($line['line_type'] ?? '')));
            $qty = round((float)($line['qty'] ?? 0), 4);
            if (!in_array($lineType, ['MATERIAL', 'COMPONENT'], true) || $qty <= 0) {
                continue;
            }
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
            $materialItemId = !empty($line['material_item_id']) ? (int)$line['material_item_id'] : null;
            $subComponentId = !empty($line['sub_component_id']) ? (int)$line['sub_component_id'] : null;
            if ($lineType === 'MATERIAL' && empty($materialId)) {
                continue;
            }
            if ($lineType === 'MATERIAL' && !empty($materialItemId)) {
                $item = $this->db->select('id, material_id')->from('mst_item')->where('id', $materialItemId)->limit(1)->get()->row_array();
                if (!$item || (int)($item['material_id'] ?? 0) !== (int)$materialId) {
                    $materialItemId = null;
                }
            } else {
                $materialItemId = null;
            }
            if ($lineType === 'COMPONENT') {
                if (empty($subComponentId) || $subComponentId === $componentId) {
                    continue;
                }
                $sub = $this->db->select('id, component_type')->from('mst_component')->where('id', (int)$subComponentId)->limit(1)->get()->row_array();
                if (!$sub) {
                    continue;
                }
                $parentType = strtoupper((string)$component['component_type']);
                $subType = strtoupper((string)$sub['component_type']);
                if ($parentType === 'BASE' && $subType !== 'BASE') {
                    continue;
                }
                if ($parentType === 'PREPARE' && !in_array($subType, ['BASE', 'PREPARE'], true)) {
                    continue;
                }
            }
            $resolvedUomId = $this->resolve_formula_uom_id($lineType, $materialId, $subComponentId);
            if ($resolvedUomId <= 0) {
                continue;
            }

            $materialCol = $this->formula_material_column();
            $row = [
                'component_id' => $componentId,
                'line_no' => $lineNo++,
                'line_type' => $lineType,
                $materialCol => $lineType === 'MATERIAL' ? $materialId : null,
                'sub_component_id' => $lineType === 'COMPONENT' ? $subComponentId : null,
                'qty' => $qty,
                'notes' => $this->nullable_string($line['notes'] ?? null),
                'sort_order' => (int)($line['sort_order'] ?? 0),
            ];
            if ($this->db->field_exists('uom_id', 'mst_component_formula')) {
                $row['uom_id'] = $resolvedUomId;
            }
            if ($this->db->field_exists('material_item_id', 'mst_component_formula')) {
                $row['material_item_id'] = $lineType === 'MATERIAL' ? ($materialItemId ?: null) : null;
            }
            if ($this->db->field_exists('source_division_id', 'mst_component_formula')) {
                $sourceDivId = !empty($line['source_division_id']) ? (int)$line['source_division_id'] : null;
                $row['source_division_id'] = $sourceDivId > 0 ? $sourceDivId : null;
            }
            $normalized[] = $row;
        }
        if (count($normalized) <= 0) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 line formula valid.'];
        }

        $duplicateMaterialId = $this->detect_duplicate_formula_material_in_rows($normalized);
        if ($duplicateMaterialId !== null) {
            return [
                'ok' => false,
                'message' => 'Material ' . $this->component_formula_material_name($duplicateMaterialId) . ' dobel pada formula component ini. Gabungkan jadi satu line.',
            ];
        }

        if (!$this->component_formula_audit_ready() || !$this->component_formula_versioning_ready()) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'Pencatatan audit dan riwayat versi formula belum siap. Perubahan tidak dijalankan.',
            ];
        }
        if ($this->db->trans_begin() === false) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'Transaksi perubahan formula tidak dapat dimulai.',
            ];
        }
        if (!$this->lock_component_formula_parent($componentId)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'Component formula tidak dapat dikunci. Muat ulang halaman.'];
        }
        $beforeRows = $this->component_formula_revision_rows($componentId, true);
        if ($beforeRows === null) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 503, 'message' => 'Snapshot formula tidak dapat dikunci.'];
        }
        if (!hash_equals($this->canonical_component_formula_revision($beforeRows), $expectedRevision)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Formula telah berubah oleh pengguna lain. Muat ulang halaman sebelum menyimpan kembali.',
            ];
        }
        if (!$this->ensure_component_formula_baseline_version($componentId, $beforeRows, $auditContext)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 503, 'message' => 'Riwayat baseline formula tidak dapat dicatat. Perubahan dibatalkan.'];
        }
        $this->db->where('component_id', $componentId)->delete('mst_component_formula');
        foreach ($normalized as $row) {
            $this->db->insert('mst_component_formula', $row);
        }
        $afterRows = $this->component_formula_revision_rows($componentId, true);
        if ($afterRows === null
            || !$this->write_component_formula_version($componentId, $afterRows, 'REPLACE', $auditContext)
            || !$this->write_component_formula_bulk_audit($componentId, $beforeRows, $afterRows, $auditContext)
            || $this->db->trans_status() === false
            || $this->db->trans_commit() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan formula bulk.'];
        }
        unset($this->componentFormulaLinesCache[$componentId]);
        return ['ok' => true];
    }

    public function component_formula_revision(int $componentId): string
    {
        $rows = $this->component_formula_revision_rows($componentId, false);
        return $this->canonical_component_formula_revision($rows ?? []);
    }

    /**
     * Replaces the live formula with one immutable historical snapshot. The
     * caller supplies a current revision so a stale browser can never restore
     * over a newer formula unseen by that operator.
     */
    public function restore_component_formula_version(
        int $componentId,
        int $versionId,
        string $expectedRevision,
        array $auditContext = []
    ): array {
        if ($componentId <= 0 || $versionId <= 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Component atau versi formula tidak valid.'];
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', $expectedRevision) !== 1) {
            return ['ok' => false, 'status' => 422, 'message' => 'Snapshot formula tidak valid. Muat ulang halaman sebelum memulihkan.'];
        }
        if (!$this->component_formula_audit_ready() || !$this->component_formula_versioning_ready()) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'Pencatatan audit dan riwayat versi formula belum siap. Pemulihan tidak dijalankan.',
            ];
        }
        if ($this->db->trans_begin() === false) {
            return ['ok' => false, 'status' => 503, 'message' => 'Transaksi pemulihan formula tidak dapat dimulai.'];
        }
        if (!$this->lock_component_formula_parent($componentId)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'Formula component tidak dapat dikunci. Muat ulang halaman.'];
        }
        $beforeRows = $this->component_formula_revision_rows($componentId, true);
        if ($beforeRows === null) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 503, 'message' => 'Snapshot formula aktif tidak dapat dikunci.'];
        }
        if (!hash_equals($this->canonical_component_formula_revision($beforeRows), $expectedRevision)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Formula telah berubah oleh pengguna lain. Muat ulang halaman sebelum memulihkan versi.',
            ];
        }
        $targetRows = $this->component_formula_version_rows($componentId, $versionId, true);
        if ($targetRows === null) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'Versi formula yang dipilih tidak ditemukan atau tidak lengkap.'];
        }
        $restoreRows = $this->component_formula_restore_rows($componentId, $targetRows);
        if ($restoreRows === null) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Snapshot versi formula tidak valid untuk dipulihkan.'];
        }
        $this->db->where('component_id', $componentId)->delete('mst_component_formula');
        foreach ($restoreRows as $row) {
            if (!$this->db->insert('mst_component_formula', $row)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'status' => 422, 'message' => 'Baris formula versi lama tidak dapat dipulihkan.'];
            }
        }
        $afterRows = $this->component_formula_revision_rows($componentId, true);
        if ($afterRows === null
            || !$this->write_component_formula_version($componentId, $afterRows, 'RESTORE', $auditContext)
            || !$this->write_component_formula_restore_audit($componentId, $versionId, $beforeRows, $afterRows, $auditContext)
            || $this->db->trans_status() === false
            || $this->db->trans_commit() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Pemulihan formula gagal dan seluruh perubahan dibatalkan.'];
        }
        unset($this->componentFormulaLinesCache[$componentId]);
        return ['ok' => true];
    }

    /**
     * Read-only timeline for the canonical formula detail screen. Snapshot
     * lines remain immutable; restoring an old version is intentionally a
     * separate, explicitly authorised workflow.
     */
    public function component_formula_versions(int $componentId, int $limit = 20): array
    {
        if ($componentId <= 0 || !$this->component_formula_versioning_ready()) {
            return [];
        }
        $limit = max(1, min($limit, 100));
        return $this->db
            ->select('v.id, v.version_no, v.change_action, v.formula_revision, v.line_count, v.source_ip, v.created_at, u.username AS actor_username')
            ->from('mst_component_formula_version v')
            ->join('auth_user u', 'u.id = v.actor_user_id', 'left')
            ->where('v.component_id', $componentId)
            ->order_by('v.version_no', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    private function component_formula_revision_rows(int $componentId, bool $forUpdate): ?array
    {
        if ($componentId <= 0) {
            return [];
        }
        $fields = [
            'id', 'component_id', 'line_no', 'line_type', 'material_item_id',
            'sub_component_id', 'qty', 'notes', 'sort_order',
        ];
        foreach (['material_id', 'uom_id', 'source_division_id'] as $field) {
            if ($this->db->field_exists($field, 'mst_component_formula')) {
                $fields[] = $field;
            }
        }
        $query = $this->db->query(
            'SELECT ' . implode(', ', $fields)
            . ' FROM mst_component_formula WHERE component_id = ? ORDER BY id ASC'
            . ($forUpdate ? ' FOR UPDATE' : ''),
            [$componentId]
        );
        return $query === false ? null : $query->result_array();
    }

    /** Returns an immutable snapshot only when it belongs to this component. */
    private function component_formula_version_rows(int $componentId, int $versionId, bool $forUpdate): ?array
    {
        $headerQuery = $this->db->query(
            'SELECT id, line_count FROM mst_component_formula_version WHERE id = ? AND component_id = ?'
            . ($forUpdate ? ' FOR UPDATE' : ''),
            [$versionId, $componentId]
        );
        $header = $headerQuery === false ? null : $headerQuery->row_array();
        if (!is_array($header)) {
            return null;
        }
        $rowsQuery = $this->db->query(
            'SELECT line_no, line_type, material_id, material_item_id, sub_component_id, source_division_id, uom_id, qty, notes, sort_order'
            . ' FROM mst_component_formula_version_line WHERE formula_version_id = ? ORDER BY line_no ASC, id ASC'
            . ($forUpdate ? ' FOR UPDATE' : ''),
            [$versionId]
        );
        if ($rowsQuery === false) {
            return null;
        }
        $rows = $rowsQuery->result_array();
        return count($rows) === (int)($header['line_count'] ?? 0) ? $rows : null;
    }

    /** Converts a trusted immutable snapshot into live formula rows. */
    private function component_formula_restore_rows(int $componentId, array $versionRows): ?array
    {
        $materialColumn = $this->formula_material_column();
        $hasUom = $this->db->field_exists('uom_id', 'mst_component_formula');
        $hasMaterialItem = $this->db->field_exists('material_item_id', 'mst_component_formula');
        $hasSourceDivision = $this->db->field_exists('source_division_id', 'mst_component_formula');
        $rows = [];
        foreach ($versionRows as $index => $line) {
            $lineType = strtoupper(trim((string)($line['line_type'] ?? '')));
            $materialId = (int)($line['material_id'] ?? 0);
            $subComponentId = (int)($line['sub_component_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if (!in_array($lineType, ['MATERIAL', 'COMPONENT'], true)
                || $qty <= 0
                || ($lineType === 'MATERIAL' && $materialId <= 0)
                || ($lineType === 'COMPONENT' && $subComponentId <= 0)) {
                return null;
            }
            $row = [
                'component_id' => $componentId,
                'line_no' => max(1, (int)($line['line_no'] ?? ($index + 1))),
                'line_type' => $lineType,
                $materialColumn => $lineType === 'MATERIAL' ? $materialId : null,
                'sub_component_id' => $lineType === 'COMPONENT' ? $subComponentId : null,
                'qty' => $qty,
                'notes' => $this->nullable_string($line['notes'] ?? null),
                'sort_order' => (int)($line['sort_order'] ?? $index),
            ];
            if ($hasUom) {
                $uomId = (int)($line['uom_id'] ?? 0);
                if ($uomId <= 0) {
                    return null;
                }
                $row['uom_id'] = $uomId;
            }
            if ($hasMaterialItem) {
                $row['material_item_id'] = $lineType === 'MATERIAL' && (int)($line['material_item_id'] ?? 0) > 0
                    ? (int)$line['material_item_id']
                    : null;
            }
            if ($hasSourceDivision) {
                $row['source_division_id'] = (int)($line['source_division_id'] ?? 0) > 0
                    ? (int)$line['source_division_id']
                    : null;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function canonical_component_formula_revision(array $rows): string
    {
        $canonicalRows = [];
        foreach ($rows as $row) {
            $canonicalRows[] = [
                'id' => (int)($row['id'] ?? 0),
                'component_id' => (int)($row['component_id'] ?? 0),
                'line_no' => (int)($row['line_no'] ?? 0),
                'line_type' => strtoupper(trim((string)($row['line_type'] ?? ''))),
                'material_id' => (int)($row['material_id'] ?? 0),
                'material_item_id' => (int)($row['material_item_id'] ?? 0),
                'sub_component_id' => (int)($row['sub_component_id'] ?? 0),
                'qty' => number_format((float)($row['qty'] ?? 0), 6, '.', ''),
                'uom_id' => (int)($row['uom_id'] ?? 0),
                'source_division_id' => (int)($row['source_division_id'] ?? 0),
                'notes' => trim((string)($row['notes'] ?? '')),
                'sort_order' => (int)($row['sort_order'] ?? 0),
            ];
        }
        usort($canonicalRows, static function (array $left, array $right): int {
            return $left['id'] <=> $right['id'];
        });
        return hash('sha256', json_encode($canonicalRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function lock_component_formula_parent(int $componentId): bool
    {
        $query = $this->db->query('SELECT id FROM mst_component WHERE id = ? FOR UPDATE', [$componentId]);
        return $query !== false && !empty($query->row_array());
    }

    private function component_formula_versioning_ready(): bool
    {
        $tables = ['mst_component_formula_version', 'mst_component_formula_version_line'];
        foreach ($tables as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }
        $requiredHeader = ['id', 'component_id', 'version_no', 'change_action', 'formula_revision', 'line_count', 'actor_user_id', 'source_ip', 'created_at'];
        foreach ($requiredHeader as $column) {
            if (!$this->db->field_exists($column, 'mst_component_formula_version')) {
                return false;
            }
        }
        $requiredLine = ['formula_version_id', 'original_line_id', 'line_no', 'line_type', 'material_id', 'material_item_id', 'sub_component_id', 'source_division_id', 'uom_id', 'qty', 'notes', 'sort_order'];
        foreach ($requiredLine as $column) {
            if (!$this->db->field_exists($column, 'mst_component_formula_version_line')) {
                return false;
            }
        }
        return true;
    }

    private function ensure_component_formula_baseline_version(int $componentId, array $beforeRows, array $context): bool
    {
        if ($beforeRows === []) {
            return true;
        }
        $existing = $this->db
            ->select('id')
            ->from('mst_component_formula_version')
            ->where('component_id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if ($existing) {
            return true;
        }
        return $this->write_component_formula_version($componentId, $beforeRows, 'BASELINE', $context);
    }

    private function write_component_formula_version(int $componentId, array $rows, string $action, array $context): bool
    {
        if (!in_array($action, ['BASELINE', 'REPLACE', 'RESTORE'], true)) {
            return false;
        }
        $latest = $this->db
            ->select_max('version_no')
            ->from('mst_component_formula_version')
            ->where('component_id', $componentId)
            ->get()
            ->row_array();
        $actorUserId = (int)($context['actor_user_id'] ?? 0);
        $sourceIp = trim((string)($context['source_ip'] ?? ''));
        $header = [
            'component_id' => $componentId,
            'version_no' => (int)($latest['version_no'] ?? 0) + 1,
            'change_action' => $action,
            'formula_revision' => $this->canonical_component_formula_revision($rows),
            'line_count' => count($rows),
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'source_ip' => $sourceIp !== '' ? substr($sourceIp, 0, 45) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (!$this->db->insert('mst_component_formula_version', $header)) {
            return false;
        }
        $formulaVersionId = (int)$this->db->insert_id();
        if ($formulaVersionId <= 0) {
            return false;
        }
        foreach ($rows as $row) {
            $line = [
                'formula_version_id' => $formulaVersionId,
                'original_line_id' => !empty($row['id']) ? (int)$row['id'] : null,
                'line_no' => max(1, (int)($row['line_no'] ?? 0)),
                'line_type' => strtoupper(trim((string)($row['line_type'] ?? ''))),
                'material_id' => !empty($row['material_id']) ? (int)$row['material_id'] : null,
                'material_item_id' => !empty($row['material_item_id']) ? (int)$row['material_item_id'] : null,
                'sub_component_id' => !empty($row['sub_component_id']) ? (int)$row['sub_component_id'] : null,
                'source_division_id' => !empty($row['source_division_id']) ? (int)$row['source_division_id'] : null,
                'uom_id' => !empty($row['uom_id']) ? (int)$row['uom_id'] : null,
                'qty' => round((float)($row['qty'] ?? 0), 4),
                'notes' => $this->nullable_string($row['notes'] ?? null),
                'sort_order' => (int)($row['sort_order'] ?? 0),
            ];
            if (!in_array($line['line_type'], ['MATERIAL', 'COMPONENT'], true)
                || (float)$line['qty'] <= 0
                || !$this->db->insert('mst_component_formula_version_line', $line)) {
                return false;
            }
        }
        return true;
    }

    private function component_formula_audit_ready(): bool
    {
        $requiredColumns = [
            'module_code', 'action_code', 'entity_table', 'entity_id', 'transaction_no',
            'ref_table', 'ref_id', 'actor_user_id', 'source_ip', 'before_payload',
            'after_payload', 'notes', 'created_at',
        ];
        if (!$this->db->table_exists('aud_transaction_log')) {
            return false;
        }
        foreach ($requiredColumns as $column) {
            if (!$this->db->field_exists($column, 'aud_transaction_log')) {
                return false;
            }
        }
        return true;
    }

    private function write_component_formula_bulk_audit(int $componentId, array $before, array $after, array $context): bool
    {
        $beforeJson = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($beforeJson) || !is_string($afterJson)) {
            return false;
        }
        $actorUserId = (int)($context['actor_user_id'] ?? 0);
        $sourceIp = trim((string)($context['source_ip'] ?? ''));
        return $this->db->insert('aud_transaction_log', [
            'module_code' => 'PRODUCTION',
            'action_code' => 'REPLACE_COMPONENT_FORMULA',
            'entity_table' => 'mst_component_formula',
            'entity_id' => $componentId,
            'transaction_no' => null,
            'ref_table' => 'mst_component',
            'ref_id' => $componentId,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'source_ip' => $sourceIp !== '' ? substr($sourceIp, 0, 45) : null,
            'before_payload' => $beforeJson,
            'after_payload' => $afterJson,
            'notes' => 'Replace component formula lines',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function write_component_formula_restore_audit(
        int $componentId,
        int $restoredVersionId,
        array $before,
        array $after,
        array $context
    ): bool {
        $beforeJson = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($beforeJson) || !is_string($afterJson)) {
            return false;
        }
        $actorUserId = (int)($context['actor_user_id'] ?? 0);
        $sourceIp = trim((string)($context['source_ip'] ?? ''));
        return $this->db->insert('aud_transaction_log', [
            'module_code' => 'PRODUCTION',
            'action_code' => 'RESTORE_COMPONENT_FORMULA',
            'entity_table' => 'mst_component_formula',
            'entity_id' => $componentId,
            'transaction_no' => null,
            'ref_table' => 'mst_component_formula_version',
            'ref_id' => $restoredVersionId,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'source_ip' => $sourceIp !== '' ? substr($sourceIp, 0, 45) : null,
            'before_payload' => $beforeJson,
            'after_payload' => $afterJson,
            'notes' => 'Restore component formula from immutable version #' . $restoredVersionId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function variable_cost_default_list(): array
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return [];
        }
        return $this->db->select('id, scope_code, default_percent, notes, is_active')
            ->from('mst_variable_cost_default')
            ->order_by('scope_code', 'ASC')
            ->get()
            ->result_array();
    }

    public function save_variable_cost_default(array $payload): array
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return ['ok' => false, 'message' => 'Tabel mst_variable_cost_default belum tersedia.'];
        }
        $scopeCode = strtoupper(trim((string)($payload['scope_code'] ?? '')));
        $defaultPercent = round((float)($payload['default_percent'] ?? 0), 4);
        if (!in_array($scopeCode, ['COMPONENT', 'PRODUCT'], true)) {
            return ['ok' => false, 'message' => 'Scope harus COMPONENT atau PRODUCT.'];
        }
        if ($defaultPercent < 0) {
            return ['ok' => false, 'message' => 'Default percent tidak boleh negatif.'];
        }

        $row = $this->db->select('id')->from('mst_variable_cost_default')->where('scope_code', $scopeCode)->limit(1)->get()->row_array();
        $save = [
            'scope_code' => $scopeCode,
            'default_percent' => $defaultPercent,
            'notes' => $this->nullable_string($payload['notes'] ?? null),
            'is_active' => isset($payload['is_active']) ? (int)((bool)$payload['is_active']) : 1,
        ];
        if ($row) {
            $this->db->where('id', (int)$row['id'])->update('mst_variable_cost_default', $save);
        } else {
            $this->db->insert('mst_variable_cost_default', $save);
        }
        return ['ok' => true];
    }

    public function toggle_active(string $table, int $id): array
    {
        $row = $this->db->get_where($table, ['id' => $id])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Data tidak ditemukan.'];
        }
        $isActive = (int)($row['is_active'] ?? 0) === 1 ? 0 : 1;
        $this->db->where('id', $id)->update($table, ['is_active' => $isActive]);
        return ['ok' => true, 'is_active' => $isActive];
    }

    private function generate_doc_no(string $table, string $column, string $prefix, string $date): string
    {
        $datePart = date('Ymd', strtotime($date));
        $key = $prefix . $datePart;
        $row = $this->db->select($column)
            ->from($table)
            ->like($column, $key, 'after')
            ->order_by($column, 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $seq = 1;
        if (!empty($row[$column])) {
            $suffix = substr((string)$row[$column], strlen($key));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }
        return $key . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function nullable_string($value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function nullable_int($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function variable_cost_default_percent(string $scopeCode): float
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return 20.0;
        }
        $row = $this->db->select('default_percent')
            ->from('mst_variable_cost_default')
            ->where('scope_code', strtoupper(trim($scopeCode)))
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            return 20.0;
        }
        return max(0.0, (float)($row['default_percent'] ?? 20.0));
    }

    private function component_formula_cost_summary(int $componentId, int $divisionId, string $mode, float $percent): array
    {
        $cacheKey = $componentId . '|' . $divisionId . '|' . strtoupper($mode) . '|' . number_format($percent, 4, '.', '');
        if (isset($this->componentFormulaSummaryCache[$cacheKey])) {
            return $this->componentFormulaSummaryCache[$cacheKey];
        }
        $lines = $this->list_component_formulas($componentId);
        $lineCount = 0;
        $materialCount = 0;
        $componentCount = 0;
        $hppLive = 0.0;
        foreach ($lines as $line) {
            $lineCount++;
            if (strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL') {
                $materialCount++;
            } else {
                $componentCount++;
            }
            $cost = $this->resolve_formula_line_cost($line, $divisionId);
            $qty = (float)($line['qty'] ?? 0);
            $hppLive += ($qty * (float)$cost['live_unit_cost']);
        }
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
        } else {
            $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
        }
        $summary = [
            'line_count' => $lineCount,
            'material_count' => $materialCount,
            'component_count' => $componentCount,
            'hpp_live' => round($hppLive, 6),
            'hpp_total' => round($hppLive * (1 + ($effectivePercent / 100)), 6),
        ];
        $this->componentFormulaSummaryCache[$cacheKey] = $summary;
        return $summary;
    }

    private function resolve_formula_line_cost(array $line, int $divisionId, bool $weightedLiveLots = false): array
    {
        $lineDivisionId = !empty($line['source_division_id']) ? (int)$line['source_division_id'] : 0;
        if ($lineDivisionId > 0) {
            $divisionId = $lineDivisionId;
        }
        $lineType = strtoupper((string)($line['line_type'] ?? ''));
        if ($lineType === 'MATERIAL') {
            $materialCol = $this->formula_material_column();
            $materialId = (int)($line[$materialCol] ?? 0);
            if ($materialId <= 0 && !empty($line['material_item_id'])) {
                $itemId = (int)$line['material_item_id'];
                if (!array_key_exists($itemId, $this->itemMaterialCache)) {
                    $this->itemMaterialCache[$itemId] = $this->db->select('material_id')->from('mst_item')->where('id', $itemId)->limit(1)->get()->row_array();
                }
                $legacy = $this->itemMaterialCache[$itemId];
                $materialId = (int)($legacy['material_id'] ?? 0);
            }
            $cacheKey = 'M|' . $divisionId . '|' . $materialId . ($weightedLiveLots ? '|W' : '');
            if (isset($this->formulaLineCostCache[$cacheKey])) {
                return $this->formulaLineCostCache[$cacheKey];
            }
            if (!array_key_exists($materialId, $this->materialLookupCache)) {
                $this->materialLookupCache[$materialId] = $this->db->select('id, material_name, hpp_standard')
                    ->from('mst_material')
                    ->where('id', $materialId)
                    ->limit(1)
                    ->get()
                    ->row_array();
            }
            $material = $this->materialLookupCache[$materialId];
            $standard = (float)($material['hpp_standard'] ?? 0);
            $live = 0.0;
            $hasStockLiveCost = false;
            $balanceKey = $divisionId . '|' . $materialId . ($weightedLiveLots ? '|W' : '');
            $lotLive = $this->resolve_formula_material_lot_cost($materialId, $divisionId, $weightedLiveLots);
            if (($lotLive['unit_cost'] ?? 0) > 0) {
                $live = (float)($lotLive['unit_cost'] ?? 0);
                $hasStockLiveCost = true;
                $this->materialBalanceCache[$balanceKey] = [
                    'avg_cost_per_content' => $live,
                    'qty_balance' => (float)($lotLive['qty_balance'] ?? 0),
                ];
            }
            if ($divisionId > 0 && $this->db->table_exists('inv_division_monthly_stock')) {
                if (!array_key_exists($balanceKey, $this->materialBalanceCache)) {
                    $targetMonth = date('Y-m-01');
                    $latestMonthSubquery = $this->db
                        ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                        ->from('inv_division_monthly_stock')
                        ->where('month_key <=', $targetMonth)
                        ->group_by(['division_id', 'destination_type', 'identity_key'])
                        ->get_compiled_select();
                    $liveRow = $this->db->select('AVG(COALESCE(s.avg_cost_per_content,0)) AS avg_cost_per_content', false)
                        ->from('inv_division_monthly_stock s')
                        ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                        ->where('s.division_id', $divisionId)
                        ->where('s.material_id', $materialId)
                        ->get()
                        ->row_array();
                    $qtyRow = $this->db->select('SUM(COALESCE(s.closing_qty_content,0)) AS qty_balance', false)
                        ->from('inv_division_monthly_stock s')
                        ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                        ->where('s.division_id', $divisionId)
                        ->where('s.material_id', $materialId)
                        ->get()
                        ->row_array();
                    $this->materialBalanceCache[$balanceKey] = [
                        'avg_cost_per_content' => (float)($liveRow['avg_cost_per_content'] ?? 0),
                        'qty_balance' => (float)($qtyRow['qty_balance'] ?? 0),
                    ];
                }
                $live = (float)($this->materialBalanceCache[$balanceKey]['avg_cost_per_content'] ?? 0);
                $hasStockLiveCost = $live > 0;
            }
            if ($live <= 0) {
                $live = $standard;
            }
            $availableQty = 0.0;
            if ($divisionId > 0 && $this->db->table_exists('inv_division_monthly_stock')) {
                $availableQty = (float)($this->materialBalanceCache[$balanceKey]['qty_balance'] ?? 0);
            }
            $result = [
                'standard_unit_cost' => round($standard, 6),
                'live_unit_cost' => round($live, 6),
                'source_label' => 'MATERIAL',
                'available_qty' => round($availableQty, 4),
                'live_cost_source' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'LOT_FIFO_ACTIVE' : 'STOCK_DIVISION') : 'FALLBACK_STANDARD',
                'live_cost_source_label' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'Lot Aktif FIFO' : 'Stok Divisi') : 'Fallback Std',
            ];
            $this->formulaLineCostCache[$cacheKey] = $result;
            return $result;
        }

        $subComponentId = (int)($line['sub_component_id'] ?? 0);
        $cacheKey = 'C|' . $divisionId . '|' . $subComponentId . ($weightedLiveLots ? '|W' : '');
        if (isset($this->formulaLineCostCache[$cacheKey])) {
            return $this->formulaLineCostCache[$cacheKey];
        }
        if (!array_key_exists($subComponentId, $this->componentLookupCache)) {
            $this->componentLookupCache[$subComponentId] = $this->db->select('id, component_name, hpp_standard')
                ->from('mst_component')
                ->where('id', $subComponentId)
                ->limit(1)
                ->get()
                ->row_array();
        }
        $sub = $this->componentLookupCache[$subComponentId];
        $standard = (float)($sub['hpp_standard'] ?? 0);
        $live = 0.0;
        $hasStockLiveCost = false;
        $balanceKey = $divisionId . '|' . $subComponentId . ($weightedLiveLots ? '|W' : '');
        $lotLive = $this->resolve_formula_component_lot_cost($subComponentId, $divisionId, $weightedLiveLots);
        if (($lotLive['unit_cost'] ?? 0) > 0) {
            $live = (float)($lotLive['unit_cost'] ?? 0);
            $hasStockLiveCost = true;
            $this->componentBalanceCache[$balanceKey] = [
                'avg_cost' => $live,
                'qty_balance' => (float)($lotLive['qty_balance'] ?? 0),
            ];
        }
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            if (!array_key_exists($balanceKey, $this->componentBalanceCache)) {
                $targetMonth = date('Y-m-01');
                $latestMonthSubquery = $this->db
                    ->select('location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key', false)
                    ->from('inv_component_monthly_stock')
                    ->where('month_key <=', $targetMonth)
                    ->group_by(['location_type', 'division_id', 'component_id', 'uom_id'])
                    ->get_compiled_select();
                $this->db->select('AVG(COALESCE(s.avg_cost,0)) AS avg_cost', false)
                    ->from('inv_component_monthly_stock s')
                    ->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false)
                    ->where('s.component_id', $subComponentId);
                if ($divisionId > 0) {
                    $this->db->where('s.division_id', $divisionId);
                }
                $liveRow = $this->db->get()->row_array();
                $this->db->select('SUM(COALESCE(s.closing_qty,0)) AS qty_balance', false)
                    ->from('inv_component_monthly_stock s')
                    ->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false)
                    ->where('s.component_id', $subComponentId);
                if ($divisionId > 0) {
                    $this->db->where('s.division_id', $divisionId);
                }
                $qtyRow = $this->db->get()->row_array();
                $this->componentBalanceCache[$balanceKey] = [
                    'avg_cost' => (float)($liveRow['avg_cost'] ?? 0),
                    'qty_balance' => (float)($qtyRow['qty_balance'] ?? 0),
                ];
            }
            $live = (float)($this->componentBalanceCache[$balanceKey]['avg_cost'] ?? 0);
            $hasStockLiveCost = $live > 0;
        }
        if ($live <= 0) {
            $live = $standard;
        }
        $availableQty = 0.0;
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $availableQty = (float)($this->componentBalanceCache[$balanceKey]['qty_balance'] ?? 0);
        }
        $liveSource = $hasStockLiveCost ? 'STOCK_COMPONENT' : 'FALLBACK_STANDARD';
        $result = [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'source_label' => 'COMPONENT',
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'LOT_FIFO_ACTIVE' : $liveSource) : 'FALLBACK_STANDARD',
            'live_cost_source_label' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'Lot Aktif FIFO' : 'Stok Component') : 'Fallback Std',
        ];
        $this->formulaLineCostCache[$cacheKey] = $result;
        return $result;
    }

    private function resolve_formula_material_lot_cost(int $materialId, int $divisionId, bool $weighted = false): array
    {
        if ($materialId <= 0 || $divisionId <= 0 || !$this->db->table_exists('inv_material_fifo_lot')) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }

        $preferredDestination = $this->regular_material_destination_for_division($divisionId);
        if ($preferredDestination !== null) {
            if ($weighted) {
                $weightedPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance * unit_cost) / NULLIF(SUM(qty_balance), 0), 0), 6) AS unit_cost,
                            ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_material_fifo_lot
                     WHERE location_scope = 'DIVISION'
                       AND division_id = ?
                       AND destination_type = ?
                       AND COALESCE(material_id, 0) = ?
                       AND UPPER(COALESCE(status, 'OPEN')) = 'OPEN'
                       AND qty_balance > 0",
                    [$divisionId, $preferredDestination, $materialId]
                )->row_array();
                if ((float)($weightedPreferred['qty_balance'] ?? 0) > 0.0001) {
                    return [
                        'unit_cost' => (float)($weightedPreferred['unit_cost'] ?? 0),
                        'qty_balance' => (float)($weightedPreferred['qty_balance'] ?? 0),
                    ];
                }
            }
            $frontPreferred = $this->db->query(
                "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                 FROM inv_material_fifo_lot
                 WHERE location_scope = 'DIVISION'
                   AND division_id = ?
                   AND destination_type = ?
                   AND COALESCE(material_id, 0) = ?
                   AND qty_balance > 0
                 ORDER BY receipt_date ASC, id ASC
                 LIMIT 1",
                [$divisionId, $preferredDestination, $materialId]
            )->row_array();
            if (!empty($frontPreferred)) {
                $qtyPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_material_fifo_lot
                     WHERE location_scope = 'DIVISION'
                       AND division_id = ?
                       AND destination_type = ?
                       AND COALESCE(material_id, 0) = ?
                       AND qty_balance > 0",
                    [$divisionId, $preferredDestination, $materialId]
                )->row_array();
                return [
                    'unit_cost' => (float)($frontPreferred['unit_cost'] ?? 0),
                    'qty_balance' => (float)($qtyPreferred['qty_balance'] ?? 0),
                ];
            }
        }

        if ($weighted) {
            $weightedAll = $this->db->query(
                "SELECT ROUND(COALESCE(SUM(qty_balance * unit_cost) / NULLIF(SUM(qty_balance), 0), 0), 6) AS unit_cost,
                        ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                 FROM inv_material_fifo_lot
                 WHERE location_scope = 'DIVISION'
                   AND division_id = ?
                   AND COALESCE(material_id, 0) = ?
                   AND UPPER(COALESCE(status, 'OPEN')) = 'OPEN'
                   AND qty_balance > 0",
                [$divisionId, $materialId]
            )->row_array();
            if ((float)($weightedAll['qty_balance'] ?? 0) > 0.0001) {
                return [
                    'unit_cost' => (float)($weightedAll['unit_cost'] ?? 0),
                    'qty_balance' => (float)($weightedAll['qty_balance'] ?? 0),
                ];
            }
        }

        $front = $this->db->query(
            "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
             FROM inv_material_fifo_lot
             WHERE location_scope = 'DIVISION'
               AND division_id = ?
               AND COALESCE(material_id, 0) = ?
               AND qty_balance > 0
             ORDER BY receipt_date ASC, id ASC
             LIMIT 1",
            [$divisionId, $materialId]
        )->row_array();
        if (empty($front)) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }
        $qty = $this->db->query(
            "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
             FROM inv_material_fifo_lot
             WHERE location_scope = 'DIVISION'
               AND division_id = ?
               AND COALESCE(material_id, 0) = ?
               AND qty_balance > 0",
            [$divisionId, $materialId]
        )->row_array();
        return [
            'unit_cost' => (float)($front['unit_cost'] ?? 0),
            'qty_balance' => (float)($qty['qty_balance'] ?? 0),
        ];
    }

    private function resolve_formula_component_lot_cost(int $componentId, int $divisionId, bool $weighted = false): array
    {
        if ($componentId <= 0 || !$this->db->table_exists('inv_component_lot')) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }

        $preferredLocation = $this->regular_component_location_for_division($divisionId);
        if ($preferredLocation !== null) {
            if ($weighted) {
                $weightedPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance * unit_cost) / NULLIF(SUM(qty_balance), 0), 0), 6) AS unit_cost,
                            ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_component_lot
                     WHERE component_id = ?
                       AND division_id = ?
                       AND location_type = ?
                       AND UPPER(COALESCE(status, 'OPEN')) = 'OPEN'
                       AND qty_balance > 0",
                    [$componentId, $divisionId, $preferredLocation]
                )->row_array();
                if ((float)($weightedPreferred['qty_balance'] ?? 0) > 0.0001) {
                    return [
                        'unit_cost' => (float)($weightedPreferred['unit_cost'] ?? 0),
                        'qty_balance' => (float)($weightedPreferred['qty_balance'] ?? 0),
                    ];
                }
            }
            $frontPreferred = $this->db->query(
                "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                 FROM inv_component_lot
                 WHERE component_id = ?
                   AND division_id = ?
                   AND location_type = ?
                   AND qty_balance > 0
                 ORDER BY receipt_date ASC, id ASC
                 LIMIT 1",
                [$componentId, $divisionId, $preferredLocation]
            )->row_array();
            if (!empty($frontPreferred)) {
                $qtyPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_component_lot
                     WHERE component_id = ?
                       AND division_id = ?
                       AND location_type = ?
                       AND qty_balance > 0",
                    [$componentId, $divisionId, $preferredLocation]
                )->row_array();
                return [
                    'unit_cost' => (float)($frontPreferred['unit_cost'] ?? 0),
                    'qty_balance' => (float)($qtyPreferred['qty_balance'] ?? 0),
                ];
            }
        }

        if ($weighted) {
            $weightedAll = $this->db->query(
                "SELECT ROUND(COALESCE(SUM(qty_balance * unit_cost) / NULLIF(SUM(qty_balance), 0), 0), 6) AS unit_cost,
                        ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                 FROM inv_component_lot
                 WHERE component_id = ?
                   AND division_id = ?
                   AND UPPER(COALESCE(status, 'OPEN')) = 'OPEN'
                   AND qty_balance > 0",
                [$componentId, $divisionId]
            )->row_array();
            if ((float)($weightedAll['qty_balance'] ?? 0) > 0.0001) {
                return [
                    'unit_cost' => (float)($weightedAll['unit_cost'] ?? 0),
                    'qty_balance' => (float)($weightedAll['qty_balance'] ?? 0),
                ];
            }
        }

        $front = $this->db->query(
            "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
             FROM inv_component_lot
             WHERE component_id = ?
               AND division_id = ?
               AND qty_balance > 0
             ORDER BY receipt_date ASC, id ASC
             LIMIT 1",
            [$componentId, $divisionId]
        )->row_array();
        if (empty($front)) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }
        $qty = $this->db->query(
            "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
             FROM inv_component_lot
             WHERE component_id = ?
               AND division_id = ?
               AND qty_balance > 0",
            [$componentId, $divisionId]
        )->row_array();
        return [
            'unit_cost' => (float)($front['unit_cost'] ?? 0),
            'qty_balance' => (float)($qty['qty_balance'] ?? 0),
        ];
    }

    private function resolve_formula_uom_id(string $lineType, ?int $materialId, ?int $subComponentId): int
    {
        $lineType = strtoupper(trim($lineType));
        if ($lineType === 'MATERIAL' && !empty($materialId)) {
            $row = $this->db->select('content_uom_id')
                ->from('mst_material')
                ->where('id', (int)$materialId)
                ->limit(1)
                ->get()
                ->row_array();
            return (int)($row['content_uom_id'] ?? 0);
        }
        if ($lineType === 'COMPONENT' && !empty($subComponentId)) {
            $row = $this->db->select('uom_id')
                ->from('mst_component')
                ->where('id', (int)$subComponentId)
                ->limit(1)
                ->get()
                ->row_array();
            return (int)($row['uom_id'] ?? 0);
        }
        return 0;
    }

    private function formula_material_column(): string
    {
        if ($this->db->field_exists('material_id', 'mst_component_formula')) {
            return 'material_id';
        }
        return 'material_item_id';
    }

    public function list_component_monthly_opname(array $filters = [], int $limit = 500): array
    {
        if (!$this->db->table_exists('inv_component_monthly_opname')) {
            return [];
        }
        $month = trim((string)($filters['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $this->db->select('o.*, c.component_code, c.component_name, c.component_type, u.code AS uom_code, u.name AS uom_name, d.division_name, e.employee_name AS generated_by_name', false)
            ->from('inv_component_monthly_opname o')
            ->join('mst_component c', 'c.id = o.component_id', 'left')
            ->join('mst_uom u', 'u.id = o.uom_id', 'left')
            ->join('org_division d', 'd.id = o.division_id', 'left')
            ->join('org_employee e', 'e.id = o.generated_by', 'left')
            ->where('o.month_key >=', $monthStart)
            ->where('o.month_key <=', $monthEnd);

        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $this->apply_component_location_filter('o.location_type', $locationType);
        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId > 0) {
            $this->db->where('o.division_id', $divisionId);
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->or_like('d.division_name', $q)
                ->group_end();
        }

        $this->db->order_by('o.location_type, d.division_name, c.component_name', '', false);
        if ($limit > 0) {
            $this->db->limit($limit);
        }
        return $this->db->get()->result_array();
    }
}
