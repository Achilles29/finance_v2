<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read model for the POS availability queue. It intentionally does not
 * calculate recipes or change cache rows so opening the monitor stays light.
 */
class Pos_availability_queue_model extends CI_Model
{
    public function is_ready(): bool
    {
        return $this->db->table_exists('pos_product_availability_queue')
            && $this->db->table_exists('pos_product_availability_cache')
            && $this->db->table_exists('pos_outlet')
            && $this->db->table_exists('mst_product');
    }

    public function outlet_options(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }

        $query = $this->db
            ->select('id, outlet_name')
            ->from('pos_outlet');
        if ($this->db->field_exists('is_active', 'pos_outlet')) {
            $query->where('is_active', 1);
        }

        return $query
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function summary(): array
    {
        $default = [
            'ready' => false,
            'queued_count' => 0,
            'processing_count' => 0,
            'failed_count' => 0,
            'success_today_count' => 0,
            'oldest_waiting_at' => null,
            'last_success_at' => null,
        ];
        if (!$this->is_ready()) {
            return $default;
        }

        $row = $this->db
            ->select("\n                SUM(CASE WHEN status = 'QUEUED' THEN 1 ELSE 0 END) AS queued_count,\n                SUM(CASE WHEN status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing_count,\n                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed_count,\n                SUM(CASE WHEN status = 'SUCCESS' AND finished_at >= CURDATE() THEN 1 ELSE 0 END) AS success_today_count,\n                MIN(CASE WHEN status IN ('QUEUED', 'PROCESSING') THEN run_after ELSE NULL END) AS oldest_waiting_at,\n                MAX(CASE WHEN status = 'SUCCESS' THEN finished_at ELSE NULL END) AS last_success_at\n            ", false)
            ->from('pos_product_availability_queue')
            ->get()
            ->row_array();

        return [
            'ready' => true,
            'queued_count' => (int)($row['queued_count'] ?? 0),
            'processing_count' => (int)($row['processing_count'] ?? 0),
            'failed_count' => (int)($row['failed_count'] ?? 0),
            'success_today_count' => (int)($row['success_today_count'] ?? 0),
            'oldest_waiting_at' => (string)($row['oldest_waiting_at'] ?? '') ?: null,
            'last_success_at' => (string)($row['last_success_at'] ?? '') ?: null,
        ];
    }

    public function rows(array $filters): array
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $allowedStatuses = ['ALL', 'QUEUED', 'PROCESSING', 'FAILED', 'SUCCESS', 'CANCELLED'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'ALL';
        }
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $q = trim((string)($filters['q'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(10, min(100, (int)($filters['per_page'] ?? 25)));

        if (!$this->is_ready()) {
            return [
                'rows' => [],
                'meta' => [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => $limit,
                    'total_pages' => 1,
                ],
            ];
        }

        $builder = $this->db
            ->from('pos_product_availability_queue q')
            ->join('pos_outlet o', 'o.id = q.outlet_id', 'left')
            ->join('mst_product p', 'p.id = q.product_id', 'left')
            ->join('pos_product_availability_cache c', 'c.outlet_id = q.outlet_id AND c.product_id = q.product_id', 'left');

        if ($status !== 'ALL') {
            $builder->where('q.status', $status);
        }
        if ($outletId > 0) {
            $builder->where('q.outlet_id', $outletId);
        }
        if ($q !== '') {
            $builder->group_start()
                ->like('p.product_name', $q)
                ->or_like('p.product_code', $q)
                ->or_like('o.outlet_name', $q)
                ->or_like('q.event_source', $q)
                ->group_end();
        }

        $total = (int)$builder->count_all_results('', false);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $select = [
            'q.id',
            'q.outlet_id',
            'q.product_id',
            'q.status',
            'q.revision',
            'q.event_count',
            'q.attempts',
            'q.max_attempts',
            'q.run_after',
            'q.started_at',
            'q.finished_at',
            'q.event_source',
            'q.event_table',
            'q.event_id',
            'q.result_json',
            'q.last_error',
            'q.created_at',
            'q.updated_at',
            'o.outlet_name',
            'p.product_code',
            'p.product_name',
            'c.availability_status AS cache_availability_status',
            'c.estimated_available_qty AS cache_estimated_available_qty',
            'c.computed_at AS cache_computed_at',
        ];
        if ($this->db->field_exists('is_dirty', 'pos_product_availability_cache')) {
            $select[] = 'c.is_dirty AS cache_is_dirty';
        }

        $rows = $builder
            ->select(implode(', ', $select), false)
            ->order_by("CASE q.status WHEN 'FAILED' THEN 0 WHEN 'PROCESSING' THEN 1 WHEN 'QUEUED' THEN 2 WHEN 'SUCCESS' THEN 3 ELSE 4 END", '', false)
            ->order_by('q.run_after', 'ASC')
            ->order_by('q.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $result = json_decode((string)($row['result_json'] ?? ''), true);
            $result = is_array($result) ? $result : [];
            $row['result_availability_status'] = (string)($result['availability_status'] ?? '');
            $row['result_available_qty'] = (float)($result['estimated_available_qty'] ?? 0);
            $row['result_hpp_live_snapshot'] = (float)($result['hpp_live_snapshot'] ?? 0);
            $row['cache_is_dirty'] = (int)($row['cache_is_dirty'] ?? 0);
            unset($row['result_json']);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }
}
