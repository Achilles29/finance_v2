<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_order_monitor_model extends CI_Model
{
    private $tableReady = null;
    private $hasOrderCustomerName = null;
    private $hasTableNo = null;

    public function __construct()
    {
        parent::__construct();
    }

    private function table_ready(): bool
    {
        if ($this->tableReady === null) {
            $this->tableReady = $this->db->table_exists('pos_order_monitor_task');
        }

        return (bool)$this->tableReady;
    }

    private function has_order_customer_name(): bool
    {
        if ($this->hasOrderCustomerName === null) {
            $this->hasOrderCustomerName = $this->db->field_exists('customer_name', 'pos_order');
        }

        return (bool)$this->hasOrderCustomerName;
    }

    private function has_table_no(): bool
    {
        if ($this->hasTableNo === null) {
            $this->hasTableNo = $this->db->field_exists('table_no', 'pos_order');
        }

        return (bool)$this->hasTableNo;
    }

    public function station_options(): array
    {
        return [
            'ALL' => 'Semua Stasiun',
            'BAR' => 'Bar',
            'KITCHEN' => 'Kitchen',
            'CHECKER' => 'Checker',
        ];
    }

    public function active_outlets(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }

        $db = $this->db->select('id, outlet_name')
            ->from('pos_outlet');
        if ($this->db->field_exists('is_active', 'pos_outlet')) {
            $db->where('is_active', 1);
        }

        return $db->order_by('outlet_name', 'ASC')->get()->result_array();
    }

    private function normalize_station(string $station): string
    {
        $station = strtoupper(trim($station));
        return array_key_exists($station, $this->station_options()) ? $station : 'ALL';
    }

    private function monitor_scope(array $scope = []): array
    {
        $stationRole = strtoupper(trim((string)($scope['station_role'] ?? 'ALL')));
        if (!in_array($stationRole, ['ALL', 'BAR', 'KITCHEN', 'CHECKER'], true)) {
            $stationRole = 'ALL';
        }

        return [
            'restricted' => !empty($scope['restricted']),
            'station_role' => $stationRole,
            'operational_division_id' => max(0, (int)($scope['operational_division_id'] ?? 0)),
        ];
    }

    private function apply_monitor_scope(CI_DB_query_builder $db, array $scope = [], string $lineAlias = 'l', string $taskAlias = 't'): void
    {
        $scope = $this->monitor_scope($scope);
        if (empty($scope['restricted'])) {
            return;
        }

        $stationRole = (string)($scope['station_role'] ?? 'ALL');
        if ($taskAlias !== '' && in_array($stationRole, ['BAR', 'KITCHEN'], true)) {
            $db->where($taskAlias . '.station_role', $stationRole);
            return;
        }

        $operationalDivisionId = (int)($scope['operational_division_id'] ?? 0);
        if ($operationalDivisionId > 0 && $this->db->field_exists('operational_division_id', 'pos_order_line')) {
            $db->where($lineAlias . '.operational_division_id', $operationalDivisionId);
        }
    }

    private function active_order_statuses(): array
    {
        return ['CONFIRMED', 'PAID_PARTIAL', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED', 'REFUND_PARTIAL'];
    }

    private function station_role_from_division(string $divisionName): string
    {
        $divisionName = strtoupper(trim($divisionName));
        if ($divisionName === 'BEVERAGE') {
            return 'BAR';
        }
        if ($divisionName === 'FOOD' || $divisionName === 'EVENT') {
            return 'KITCHEN';
        }

        return 'KITCHEN';
    }

    private function desired_task_rows(int $orderId = 0, int $outletId = 0): array
    {
        $rows = $this->db->select('
                o.id AS order_id,
                l.id AS order_line_id,
                COALESCE(pd.name, \'\') AS product_division_name
            ')
            ->from('pos_order_line l')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('mst_product_division pd', 'pd.id = l.product_division_id_snapshot', 'left')
            ->where('l.qty >', 0)
            ->where_in('l.line_type', ['PRODUCT', 'BUNDLE_ITEM'])
            ->where_not_in('l.line_status', ['VOID', 'REFUNDED_FULL', 'SERVED'])
            ->where_in('o.status', $this->active_order_statuses())
            ->where('o.kitchen_status !=', 'VOID');

        if ($orderId > 0) {
            $rows->where('o.id', $orderId);
        }
        if ($outletId > 0) {
            $rows->where('o.outlet_id', $outletId);
        }

        $result = $rows->order_by('o.id', 'ASC')
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        foreach ($result as &$row) {
            $row['station_role'] = $this->station_role_from_division((string)($row['product_division_name'] ?? ''));
        }
        unset($row);

        return $result;
    }

    public function sync_order_tasks(int $orderId): void
    {
        if (!$this->table_ready() || $orderId <= 0) {
            return;
        }

        $desiredRows = $this->desired_task_rows($orderId, 0);
        $desiredKeys = [];
        foreach ($desiredRows as $row) {
            $desiredKeys[(int)$row['order_line_id'] . '|' . (string)$row['station_role']] = $row;
        }

        $existingRows = $this->db->select('id, order_line_id, station_role')
            ->from('pos_order_monitor_task')
            ->where('order_id', $orderId)
            ->get()
            ->result_array();

        $existingKeys = [];
        foreach ($existingRows as $row) {
            $existingKeys[(int)$row['order_line_id'] . '|' . (string)$row['station_role']] = (int)$row['id'];
        }

        $deleteIds = [];
        foreach ($existingKeys as $key => $taskId) {
            if (!isset($desiredKeys[$key])) {
                $deleteIds[] = $taskId;
            }
        }
        if (!empty($deleteIds)) {
            $this->db->where_in('id', $deleteIds)->delete('pos_order_monitor_task');
        }

        $insertRows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($desiredKeys as $key => $row) {
            if (isset($existingKeys[$key])) {
                continue;
            }
            $insertRows[] = [
                'order_id' => (int)$row['order_id'],
                'order_line_id' => (int)$row['order_line_id'],
                'station_role' => (string)$row['station_role'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($insertRows)) {
            $this->db->insert_batch('pos_order_monitor_task', $insertRows);
        }

        $this->reconcile_task_state_from_lines($orderId);
        $this->recalculate_order_kitchen_status($orderId);
    }

    private function reconcile_task_state_from_lines(int $orderId): void
    {
        if ($orderId <= 0 || !$this->table_ready()) {
            return;
        }

        $rows = $this->db->select('
                t.id,
                t.ack_at,
                t.ready_at,
                t.checker_done_at,
                l.line_status,
                l.process_status,
                l.processed_at
            ')
            ->from('pos_order_monitor_task t')
            ->join('pos_order_line l', 'l.id = t.order_line_id', 'inner')
            ->where('t.order_id', $orderId)
            ->get()
            ->result_array();

        if (empty($rows)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $lineStatus = strtoupper(trim((string)($row['line_status'] ?? 'OPEN')));
            $processStatus = strtoupper(trim((string)($row['process_status'] ?? 'NOT_PROCESSED')));
            $processedAt = trim((string)($row['processed_at'] ?? '')) !== '' ? (string)$row['processed_at'] : $now;
            $update = [];

            if (in_array($lineStatus, ['SENT', 'READY', 'SERVED'], true) || in_array($processStatus, ['PROCESSED', 'SERVED'], true)) {
                if (empty($row['ack_at'])) {
                    $update['ack_at'] = $processedAt;
                }
            }

            if (in_array($lineStatus, ['READY', 'SERVED'], true)) {
                if (empty($row['ready_at'])) {
                    $update['ready_at'] = $processedAt;
                }
            }

            if (in_array($lineStatus, ['SERVED'], true) || $processStatus === 'SERVED') {
                if (empty($row['checker_done_at'])) {
                    $update['checker_done_at'] = $processedAt;
                }
            }

            if (!empty($update)) {
                $update['updated_at'] = $now;
                $this->db->where('id', (int)$row['id'])->update('pos_order_monitor_task', $update);
            }
        }
    }

    private function line_extras_grouped(array $lineIds): array
    {
        if (empty($lineIds) || !$this->db->table_exists('pos_order_line_extra')) {
            return [];
        }

        $rows = $this->db->select('x.order_line_id, x.qty, x.notes, e.extra_name')
            ->from('pos_order_line_extra x')
            ->join('mst_extra e', 'e.id = x.extra_id', 'left')
            ->where_in('x.order_line_id', array_values(array_unique(array_map('intval', $lineIds))))
            ->where('x.qty >', 0)
            ->order_by('x.order_line_id', 'ASC')
            ->order_by('x.line_no', 'ASC')
            ->get()
            ->result_array();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['order_line_id']][] = [
                'name' => (string)($row['extra_name'] ?? '-'),
                'qty' => (float)($row['qty'] ?? 0),
                'notes' => (string)($row['notes'] ?? ''),
            ];
        }

        return $grouped;
    }

    public function board_rows(string $station = 'ALL', int $outletId = 0, string $dateFrom = '', string $dateTo = '', array $scope = []): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $station = $this->normalize_station($station);
        $hasCustomerName = $this->has_order_customer_name();
        $hasTableNo = $this->has_table_no();

        $db = $this->db->select('
                t.id,
                t.order_id,
                t.order_line_id,
                t.station_role,
                t.ack_at,
                t.ack_by_employee_id,
                t.ready_at,
                t.ready_by_employee_id,
                t.checker_done_at,
                t.checker_done_by_employee_id,
                t.created_at,
                t.updated_at,
                o.order_no,
                o.order_scope,
                o.service_type,
                o.status AS order_status,
                o.kitchen_status,
                o.ordered_at,
                ' . ($hasTableNo ? 'o.table_no' : 'NULL AS table_no') . ',
                ' . ($hasCustomerName ? 'o.customer_name' : 'NULL AS customer_name') . ',
                o.guest_count,
                po.outlet_name,
                m.member_no,
                m.member_name,
                l.line_no,
                l.line_type,
                l.qty,
                l.line_status,
                l.process_status,
                l.notes,
                l.bundle_id,
                p.product_code,
                p.product_name,
                b.bundle_name,
                COALESCE(pd.name, \'\') AS product_division_name
            ', false)
            ->from('pos_order_monitor_task t')
            ->join('pos_order o', 'o.id = t.order_id', 'inner')
            ->join('pos_order_line l', 'l.id = t.order_line_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = l.product_division_id_snapshot', 'left')
            ->where('l.qty >', 0)
            ->where_in('o.status', $this->active_order_statuses())
            ->where_not_in('l.line_status', ['VOID', 'REFUNDED_FULL', 'SERVED'])
            ->where('t.checker_done_at IS NULL', null, false)
            ->where('o.kitchen_status !=', 'VOID');

        $this->apply_monitor_scope($db, $scope, 'l', 't');

        if ($outletId > 0) {
            $db->where('o.outlet_id', $outletId);
        }
        if ($dateFrom !== '') {
            $db->where('DATE(o.ordered_at) >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $db->where('DATE(o.ordered_at) <=', $dateTo);
        }
        if ($station === 'BAR' || $station === 'KITCHEN') {
            $db->where('t.station_role', $station);
        } elseif ($station === 'CHECKER') {
            $db->where('t.ready_at IS NOT NULL', null, false);
        }

        $rows = $db->order_by('o.ordered_at', 'ASC')
            ->order_by('o.id', 'ASC')
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        $extrasGrouped = $this->line_extras_grouped(array_column($rows, 'order_line_id'));
        $nowTs = time();
        foreach ($rows as &$row) {
            $orderedTs = strtotime((string)($row['ordered_at'] ?? '')) ?: $nowTs;
            $elapsedMinutes = max(0, (int)floor(($nowTs - $orderedTs) / 60));
            $row['elapsed_minutes'] = $elapsedMinutes;
            $row['time_badge'] = $this->time_badge($elapsedMinutes);
            $row['progress_code'] = $this->progress_code($row);
            $row['display_name'] = trim((string)($row['product_name'] ?? '')) !== ''
                ? trim((string)$row['product_name'])
                : ('Item #' . (int)($row['order_line_id'] ?? 0));
            $row['customer_display'] = trim((string)($row['customer_name'] ?? '')) !== ''
                ? trim((string)$row['customer_name'])
                : (trim((string)($row['member_name'] ?? '')) !== '' ? trim((string)$row['member_name']) : 'Walk In');
            $row['table_display'] = trim((string)($row['table_no'] ?? ''));
            $row['bundle_display'] = trim((string)($row['bundle_name'] ?? ''));
            $row['extras'] = $extrasGrouped[(int)($row['order_line_id'] ?? 0)] ?? [];
        }
        unset($row);

        return $rows;
    }

    private function progress_code(array $row): string
    {
        if (!empty($row['ready_at'])) {
            return 'READY';
        }
        if (!empty($row['ack_at'])) {
            return 'ACKED';
        }

        return 'NEW';
    }

    private function time_badge(int $minutes): array
    {
        $minutes = max(0, $minutes);
        if ($minutes < 10) {
            return ['class' => 'secondary', 'label' => $minutes . ' mnt'];
        }
        if ($minutes <= 25) {
            return ['class' => 'info', 'label' => $minutes . ' mnt'];
        }
        if ($minutes <= 40) {
            return ['class' => 'warning', 'label' => $minutes . ' mnt'];
        }

        return ['class' => 'danger', 'label' => $minutes . ' mnt'];
    }

    public function board_payload(string $station = 'ALL', int $outletId = 0, string $dateFrom = '', string $dateTo = '', array $scope = []): array
    {
        $station = $this->normalize_station($station);
        $rows = $this->board_rows($station, $outletId, $dateFrom, $dateTo, $scope);
        $completedRows = $this->completed_order_rows($station, $outletId, $dateFrom, $dateTo, $scope);
        $orders = [];
        $stats = [
            'new' => 0,
            'acked' => 0,
            'ready' => 0,
            'total_orders' => 0,
            'total_lines' => count($rows),
            'latest_task_id' => 0,
            'completed_orders' => 0,
            'completed_lines' => count($completedRows),
        ];
        $notification = [
            'task_id' => 0,
            'order_no' => '',
            'customer_display' => '',
            'outlet_name' => '',
            'station_role' => '',
        ];

        foreach ($rows as $row) {
            $stats['latest_task_id'] = max($stats['latest_task_id'], (int)($row['id'] ?? 0));
            if ((int)($row['id'] ?? 0) >= (int)$notification['task_id']) {
                $notification = [
                    'task_id' => (int)($row['id'] ?? 0),
                    'order_no' => (string)($row['order_no'] ?? ''),
                    'customer_display' => (string)($row['customer_display'] ?? 'Walk In'),
                    'outlet_name' => (string)($row['outlet_name'] ?? '-'),
                    'station_role' => (string)($row['station_role'] ?? 'ALL'),
                ];
            }
            $progress = strtolower((string)($row['progress_code'] ?? 'new'));
            if (isset($stats[$progress])) {
                $stats[$progress]++;
            }

            $orderId = (int)($row['order_id'] ?? 0);
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => $orderId,
                    'order_no' => (string)($row['order_no'] ?? ''),
                    'ordered_at' => (string)($row['ordered_at'] ?? ''),
                    'outlet_name' => (string)($row['outlet_name'] ?? '-'),
                    'order_status' => (string)($row['order_status'] ?? ''),
                    'kitchen_status' => (string)($row['kitchen_status'] ?? 'PENDING'),
                    'service_type' => (string)($row['service_type'] ?? ''),
                    'order_scope' => (string)($row['order_scope'] ?? 'REGULAR'),
                    'customer_display' => (string)($row['customer_display'] ?? 'Walk In'),
                    'table_display' => (string)($row['table_display'] ?? ''),
                    'elapsed_minutes' => (int)($row['elapsed_minutes'] ?? 0),
                    'time_badge' => (array)($row['time_badge'] ?? ['class' => 'secondary', 'label' => '0 mnt']),
                    'stations' => [
                        'BAR' => [],
                        'KITCHEN' => [],
                        'CHECKER' => [],
                    ],
                ];
            }

            $orders[$orderId]['elapsed_minutes'] = max((int)$orders[$orderId]['elapsed_minutes'], (int)($row['elapsed_minutes'] ?? 0));
            $orders[$orderId]['time_badge'] = $this->time_badge((int)$orders[$orderId]['elapsed_minutes']);
            $orders[$orderId]['stations'][(string)$row['station_role']][] = $row;
            if (!empty($row['ready_at'])) {
                $orders[$orderId]['stations']['CHECKER'][] = $row;
            }
        }

        $orders = array_values($orders);
        usort($orders, static function (array $left, array $right): int {
            $leftMinutes = (int)($left['elapsed_minutes'] ?? 0);
            $rightMinutes = (int)($right['elapsed_minutes'] ?? 0);
            if ($leftMinutes === $rightMinutes) {
                return strcmp((string)($left['ordered_at'] ?? ''), (string)($right['ordered_at'] ?? ''));
            }

            return $rightMinutes <=> $leftMinutes;
        });

        $stats['total_orders'] = count($orders);

        $completedOrders = $this->group_completed_orders($completedRows);
        $stats['completed_orders'] = count($completedOrders);

        return [
            'active_orders' => $orders,
            'completed_orders' => $completedOrders,
            'orders' => $orders,
            'stats' => $stats,
            'notification' => $notification,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function completed_order_rows(string $station = 'ALL', int $outletId = 0, string $dateFrom = '', string $dateTo = '', array $scope = []): array
    {
        $station = $this->normalize_station($station);
        $hasCustomerName = $this->has_order_customer_name();
        $hasTableNo = $this->has_table_no();

        $rows = $this->db->select(' 
                o.id AS order_id,
                l.id AS order_line_id,
                o.order_no,
                o.order_scope,
                o.service_type,
                o.status AS order_status,
                o.kitchen_status,
                o.ordered_at,
                o.served_at,
                ' . ($hasTableNo ? 'o.table_no' : 'NULL AS table_no') . ',
                ' . ($hasCustomerName ? 'o.customer_name' : 'NULL AS customer_name') . ',
                po.outlet_name,
                m.member_name,
                l.line_no,
                l.qty,
                l.notes,
                l.bundle_id,
                p.product_name,
                b.bundle_name,
                COALESCE(pd.name, \'\') AS product_division_name
            ', false)
            ->from('pos_order_line l')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = l.product_division_id_snapshot', 'left')
            ->where('l.qty >', 0)
            ->where_in('l.line_type', ['PRODUCT', 'BUNDLE_ITEM'])
            ->where('l.line_status', 'SERVED')
            ->where('o.kitchen_status', 'SERVED')
            ->where('o.status !=', 'VOID');

        $this->apply_monitor_scope($rows, $scope, 'l', '');

        if ($outletId > 0) {
            $rows->where('o.outlet_id', $outletId);
        }
        if ($dateFrom !== '') {
            $rows->where('DATE(o.ordered_at) >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $rows->where('DATE(o.ordered_at) <=', $dateTo);
        }

        $result = $rows->order_by('COALESCE(o.served_at, o.ordered_at)', 'DESC', false)
            ->order_by('o.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        $filtered = [];
        foreach ($result as $row) {
            $row['station_role'] = $this->station_role_from_division((string)($row['product_division_name'] ?? ''));
            if (($station === 'BAR' || $station === 'KITCHEN') && $row['station_role'] !== $station) {
                continue;
            }

            $orderedTs = strtotime((string)($row['ordered_at'] ?? '')) ?: time();
            $servedTs = strtotime((string)($row['served_at'] ?? '')) ?: $orderedTs;
            $row['completion_minutes'] = max(0, (int)floor(($servedTs - $orderedTs) / 60));
            $row['completion_badge'] = $this->time_badge((int)$row['completion_minutes']);
            $row['display_name'] = trim((string)($row['product_name'] ?? '')) !== ''
                ? trim((string)$row['product_name'])
                : ('Item #' . (int)($row['order_line_id'] ?? 0));
            $row['customer_display'] = trim((string)($row['customer_name'] ?? '')) !== ''
                ? trim((string)$row['customer_name'])
                : (trim((string)($row['member_name'] ?? '')) !== '' ? trim((string)$row['member_name']) : 'Walk In');
            $row['table_display'] = trim((string)($row['table_no'] ?? ''));
            $row['bundle_display'] = trim((string)($row['bundle_name'] ?? ''));
            $filtered[] = $row;
        }

        $extrasGrouped = $this->line_extras_grouped(array_column($filtered, 'order_line_id'));
        foreach ($filtered as &$row) {
            $row['extras'] = $extrasGrouped[(int)($row['order_line_id'] ?? 0)] ?? [];
        }
        unset($row);

        return $filtered;
    }

    private function group_completed_orders(array $rows): array
    {
        $orders = [];
        foreach ($rows as $row) {
            $orderId = (int)($row['order_id'] ?? 0);
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => $orderId,
                    'order_no' => (string)($row['order_no'] ?? ''),
                    'ordered_at' => (string)($row['ordered_at'] ?? ''),
                    'served_at' => (string)($row['served_at'] ?? ''),
                    'outlet_name' => (string)($row['outlet_name'] ?? '-'),
                    'customer_display' => (string)($row['customer_display'] ?? 'Walk In'),
                    'table_display' => (string)($row['table_display'] ?? ''),
                    'service_type' => (string)($row['service_type'] ?? ''),
                    'order_scope' => (string)($row['order_scope'] ?? 'REGULAR'),
                    'completion_minutes' => (int)($row['completion_minutes'] ?? 0),
                    'completion_badge' => (array)($row['completion_badge'] ?? ['class' => 'secondary', 'label' => '0 mnt']),
                    'line_count' => 0,
                    'qty_total' => 0,
                    'items' => [],
                ];
            }

            $orders[$orderId]['line_count']++;
            $orders[$orderId]['qty_total'] += (float)($row['qty'] ?? 0);
            $orders[$orderId]['completion_minutes'] = max((int)$orders[$orderId]['completion_minutes'], (int)($row['completion_minutes'] ?? 0));
            $orders[$orderId]['completion_badge'] = $this->time_badge((int)$orders[$orderId]['completion_minutes']);
            if (trim((string)($row['served_at'] ?? '')) !== '') {
                $orders[$orderId]['served_at'] = (string)$row['served_at'];
            }
            $orders[$orderId]['items'][] = $row;
        }

        $orders = array_values($orders);
        usort($orders, static function (array $left, array $right): int {
            return strcmp((string)($right['served_at'] ?? $right['ordered_at'] ?? ''), (string)($left['served_at'] ?? $left['ordered_at'] ?? ''));
        });

        return $orders;
    }

    private function task_row(int $taskId, array $scope = []): ?array
    {
        if ($taskId <= 0 || !$this->table_ready()) {
            return null;
        }

        $db = $this->db->select('t.*')
            ->from('pos_order_monitor_task t')
            ->join('pos_order_line l', 'l.id = t.order_line_id', 'inner')
            ->where('t.id', $taskId)
            ->limit(1);
        $this->apply_monitor_scope($db, $scope, 'l', 't');
        $row = $db->get()->row_array();
        return $row ?: null;
    }

    private function current_line_row(int $orderLineId): ?array
    {
        if ($orderLineId <= 0) {
            return null;
        }

        $row = $this->db->from('pos_order_line')->where('id', $orderLineId)->limit(1)->get()->row_array();
        return $row ?: null;
    }

    private function line_payload_for_action(array $line, string $action): array
    {
        $action = strtolower(trim($action));
        $lineStatus = strtoupper(trim((string)($line['line_status'] ?? 'OPEN')));
        $processStatus = strtoupper(trim((string)($line['process_status'] ?? 'NOT_PROCESSED')));
        $payload = [];
        $now = date('Y-m-d H:i:s');

        if ($action === 'ack') {
            if ($lineStatus === 'OPEN') {
                $payload['line_status'] = 'SENT';
            }
            if ($processStatus === 'NOT_PROCESSED') {
                $payload['process_status'] = 'PROCESSED';
                if (empty($line['processed_at'])) {
                    $payload['processed_at'] = $now;
                }
            }
            return $payload;
        }

        if ($action === 'ready') {
            if (!in_array($lineStatus, ['READY', 'SERVED', 'VOID', 'REFUNDED_FULL'], true)) {
                $payload['line_status'] = 'READY';
            }
            if ($processStatus !== 'PROCESSED' && $processStatus !== 'SERVED') {
                $payload['process_status'] = 'PROCESSED';
            }
            if (empty($line['processed_at'])) {
                $payload['processed_at'] = $now;
            }
            return $payload;
        }

        if ($action === 'checker') {
            if (!in_array($lineStatus, ['SERVED', 'VOID', 'REFUNDED_FULL'], true)) {
                $payload['line_status'] = 'SERVED';
            }
            if ($processStatus !== 'SERVED') {
                $payload['process_status'] = 'SERVED';
            }
            if (empty($line['processed_at'])) {
                $payload['processed_at'] = $now;
            }
        }

        return $payload;
    }

    private function recalculate_order_kitchen_status(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $order = $this->db->select('id, status, kitchen_status, served_at')
            ->from('pos_order')
            ->where('id', $orderId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$order) {
            return;
        }
        if (strtoupper(trim((string)($order['status'] ?? ''))) === 'VOID') {
            return;
        }

        $summary = $this->db->select("\n                SUM(CASE WHEN qty > 0 AND line_type IN ('PRODUCT','BUNDLE_ITEM') AND line_status = 'OPEN' THEN 1 ELSE 0 END) AS open_count,\n                SUM(CASE WHEN qty > 0 AND line_type IN ('PRODUCT','BUNDLE_ITEM') AND line_status = 'SENT' THEN 1 ELSE 0 END) AS sent_count,\n                SUM(CASE WHEN qty > 0 AND line_type IN ('PRODUCT','BUNDLE_ITEM') AND line_status = 'READY' THEN 1 ELSE 0 END) AS ready_count,\n                SUM(CASE WHEN qty > 0 AND line_type IN ('PRODUCT','BUNDLE_ITEM') AND line_status = 'SERVED' THEN 1 ELSE 0 END) AS served_count\n            ", false)
            ->from('pos_order_line')
            ->where('order_id', $orderId)
            ->where_not_in('line_status', ['VOID', 'REFUNDED_FULL'])
            ->get()
            ->row_array();

        $openCount = (int)($summary['open_count'] ?? 0);
        $sentCount = (int)($summary['sent_count'] ?? 0);
        $readyCount = (int)($summary['ready_count'] ?? 0);
        $servedCount = (int)($summary['served_count'] ?? 0);
        $activeCount = $openCount + $sentCount + $readyCount;

        if ($activeCount <= 0 && $servedCount > 0) {
            $newStatus = 'SERVED';
        } elseif ($activeCount > 0 && $readyCount === $activeCount) {
            $newStatus = 'READY';
        } elseif ($sentCount > 0 || $readyCount > 0) {
            $newStatus = 'IN_PROGRESS';
        } else {
            $newStatus = 'PENDING';
        }

        $payload = ['kitchen_status' => $newStatus];
        if ($newStatus === 'SERVED' && empty($order['served_at'])) {
            $payload['served_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', $orderId)->update('pos_order', $payload);
    }

    private function process_task_action(array $task, string $action, ?int $employeeId = null): bool
    {
        if (empty($task['id']) || empty($task['order_id']) || empty($task['order_line_id'])) {
            return false;
        }

        $action = strtolower(trim($action));
        if (!in_array($action, ['ack', 'ready', 'checker'], true)) {
            return false;
        }

        $data = [];
        $now = date('Y-m-d H:i:s');
        $actorId = $employeeId ? (int)$employeeId : null;
        if ($action === 'ack') {
            if (empty($task['ack_at'])) {
                $data['ack_at'] = $now;
                $data['ack_by_employee_id'] = $actorId;
            }
        } elseif ($action === 'ready') {
            if (empty($task['ack_at'])) {
                $data['ack_at'] = $now;
                $data['ack_by_employee_id'] = $actorId;
            }
            if (empty($task['ready_at'])) {
                $data['ready_at'] = $now;
                $data['ready_by_employee_id'] = $actorId;
            }
        } else {
            if (empty($task['ack_at'])) {
                $data['ack_at'] = $now;
                $data['ack_by_employee_id'] = $actorId;
            }
            if (empty($task['ready_at'])) {
                $data['ready_at'] = $now;
                $data['ready_by_employee_id'] = $actorId;
            }
            if (empty($task['checker_done_at'])) {
                $data['checker_done_at'] = $now;
                $data['checker_done_by_employee_id'] = $actorId;
            }
        }

        $line = $this->current_line_row((int)$task['order_line_id']);
        if (!$line) {
            return false;
        }
        $linePayload = $this->line_payload_for_action($line, $action);

        $this->db->trans_begin();
        if (!empty($data)) {
            $data['updated_at'] = $now;
            $this->db->where('id', (int)$task['id'])->update('pos_order_monitor_task', $data);
        }
        if (!empty($linePayload)) {
            $this->db->where('id', (int)$task['order_line_id'])->update('pos_order_line', $linePayload);
        }

        $this->sync_order_tasks((int)$task['order_id']);
        $this->recalculate_order_kitchen_status((int)$task['order_id']);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return true;
    }

    public function ack_task(int $taskId, ?int $employeeId = null, array $scope = []): bool
    {
        $task = $this->task_row($taskId, $scope);
        if (!$task) {
            return false;
        }
        return $this->process_task_action($task, 'ack', $employeeId);
    }

    public function ready_task(int $taskId, ?int $employeeId = null, array $scope = []): bool
    {
        $task = $this->task_row($taskId, $scope);
        if (!$task) {
            return false;
        }
        return $this->process_task_action($task, 'ready', $employeeId);
    }

    public function checker_task(int $taskId, ?int $employeeId = null, array $scope = []): bool
    {
        $task = $this->task_row($taskId, $scope);
        if (!$task) {
            return false;
        }
        return $this->process_task_action($task, 'checker', $employeeId);
    }

    private function task_rows_for_station(int $orderId, string $stationRole, array $scope = []): array
    {
        $stationRole = $this->normalize_station($stationRole);
        if (!in_array($stationRole, ['BAR', 'KITCHEN'], true)) {
            return [];
        }

        $db = $this->db->select('t.*')
            ->from('pos_order_monitor_task t')
            ->join('pos_order_line l', 'l.id = t.order_line_id', 'inner')
            ->where('t.order_id', $orderId)
            ->where('t.station_role', $stationRole)
            ->where('checker_done_at IS NULL', null, false)
            ->order_by('t.id', 'ASC');
        $this->apply_monitor_scope($db, $scope, 'l', 't');

        return $db
            ->get()
            ->result_array();
    }

    public function ack_order_station(int $orderId, string $stationRole, ?int $employeeId = null, array $scope = []): bool
    {
        $tasks = $this->task_rows_for_station($orderId, $stationRole, $scope);
        if (empty($tasks)) {
            return false;
        }

        foreach ($tasks as $task) {
            if (!$this->process_task_action($task, 'ack', $employeeId)) {
                return false;
            }
        }

        return true;
    }

    public function ready_order_station(int $orderId, string $stationRole, ?int $employeeId = null, array $scope = []): bool
    {
        $tasks = $this->task_rows_for_station($orderId, $stationRole, $scope);
        if (empty($tasks)) {
            return false;
        }

        foreach ($tasks as $task) {
            if (!$this->process_task_action($task, 'ready', $employeeId)) {
                return false;
            }
        }

        return true;
    }

    public function checker_order(int $orderId, ?int $employeeId = null, array $scope = []): bool
    {
        $db = $this->db->select('t.*')
            ->from('pos_order_monitor_task t')
            ->join('pos_order_line l', 'l.id = t.order_line_id', 'inner')
            ->where('t.order_id', $orderId)
            ->where('ready_at IS NOT NULL', null, false)
            ->where('checker_done_at IS NULL', null, false)
            ->order_by('t.id', 'ASC');
        $this->apply_monitor_scope($db, $scope, 'l', 't');
        $tasks = $db->get()->result_array();
        if (empty($tasks)) {
            return false;
        }

        foreach ($tasks as $task) {
            if (!$this->process_task_action($task, 'checker', $employeeId)) {
                return false;
            }
        }

        return true;
    }
}
