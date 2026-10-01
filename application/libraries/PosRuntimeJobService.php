<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosRuntimeJobService
{
    protected $CI;
    protected $allowedJobTypes = ['ORDER_CONFIRM_STOCK_COMMIT'];
    protected $allowedJobStatuses = ['QUEUED', 'PROCESSING', 'SUCCESS', 'FAILED', 'CANCELLED'];

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->model('Pos_model');
        $this->CI->load->library('PosStockCommitService');
        $this->CI->load->library('PosOrderStockService');
        $this->CI->load->library('PosAvailabilityRebuildService');
    }

    public function queue_order_confirm_commit(int $orderId, int $snapshotId, int $actorEmployeeId = 0, array $meta = []): array
    {
        if ($orderId <= 0 || $snapshotId <= 0) {
            return ['ok' => false, 'message' => 'Order dan snapshot wajib valid untuk membuat job runtime POS.'];
        }
        if (!$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Queue runtime POS belum siap. Jalankan migrasi queue POS terlebih dahulu.'];
        }

        $terminalReason = $this->terminal_reason_for_order_snapshot($orderId, $snapshotId);
        if ($terminalReason !== '') {
            return ['ok' => false, 'message' => $terminalReason];
        }

        $existing = $this->CI->db->query(
            "SELECT *
             FROM pos_runtime_job
             WHERE job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
               AND order_id = ?
               AND (
                   status IN ('QUEUED', 'PROCESSING')
                   OR (status = 'FAILED' AND attempts < max_attempts)
               )
             ORDER BY id DESC
             LIMIT 1",
            [$orderId]
        )->row_array();
        if ($existing) {
            return [
                'ok' => true,
                'job_id' => (int)$existing['id'],
                'job_code' => (string)($existing['job_code'] ?? ''),
                'status' => (string)($existing['status'] ?? 'QUEUED'),
                'duplicate' => true,
            ];
        }

        $payload = [
            'order_id' => $orderId,
            'snapshot_id' => $snapshotId,
            'actor_employee_id' => max(0, $actorEmployeeId),
            'event_source' => strtoupper(trim((string)($meta['event_source'] ?? 'ORDER_CONFIRM'))),
            'event_id' => max(0, (int)($meta['event_id'] ?? $orderId)),
            'queued_at' => date('Y-m-d H:i:s'),
        ];

        $insert = [
            'job_code' => $this->generate_job_code(),
            'job_type' => 'ORDER_CONFIRM_STOCK_COMMIT',
            'status' => 'QUEUED',
            'order_id' => $orderId,
            'snapshot_id' => $snapshotId,
            'attempts' => 0,
            'max_attempts' => max(1, (int)($meta['max_attempts'] ?? 3)),
            'run_after' => date('Y-m-d H:i:s'),
            'payload_json' => $this->encode_json($payload),
            'created_by_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
        ];

        $this->CI->db->insert('pos_runtime_job', $insert);
        $jobId = (int)$this->CI->db->insert_id();
        if ($jobId <= 0) {
            return ['ok' => false, 'message' => 'Job runtime POS gagal dibuat.'];
        }

        return [
            'ok' => true,
            'job_id' => $jobId,
            'job_code' => (string)$insert['job_code'],
            'status' => 'QUEUED',
            'duplicate' => false,
        ];
    }

    public function cancel_job(int $jobId, string $reason = ''): array
    {
        if ($jobId <= 0 || !$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak valid untuk dibatalkan.'];
        }
        $payload = [
            'status' => 'CANCELLED',
            'finished_at' => date('Y-m-d H:i:s'),
            'last_error' => trim($reason) !== '' ? trim($reason) : null,
        ];
        $this->CI->db
            ->where('id', $jobId)
            ->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])
            ->update('pos_runtime_job', $payload);
        if ($this->CI->db->affected_rows() <= 0) {
            $current = $this->job_context($jobId);
            if ($current && strtoupper((string)($current['status'] ?? '')) === 'CANCELLED') {
                return ['ok' => true, 'id' => $jobId, 'status' => 'CANCELLED', 'already_cancelled' => true];
            }
            return ['ok' => false, 'message' => 'Job runtime POS sudah selesai atau tidak ditemukan saat dibatalkan.'];
        }
        return ['ok' => true, 'id' => $jobId, 'status' => 'CANCELLED'];
    }

    public function latest_job_for_order(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid untuk status queue.'];
        }
        if (!$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Queue runtime POS belum siap.'];
        }

        $row = $this->CI->db->query(
            "SELECT j.*, o.status AS order_status, o.stock_commit_status, o.stock_committed_at,
                    s.commit_status AS snapshot_commit_status, s.commit_no
             FROM pos_runtime_job j
             LEFT JOIN pos_order o ON o.id = j.order_id
             LEFT JOIN pos_stock_commit s ON s.id = j.snapshot_id
             WHERE j.job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
               AND j.order_id = ?
             ORDER BY j.id DESC
             LIMIT 1",
            [$orderId]
        )->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Job runtime POS untuk order ini belum ada.'];
        }

        return ['ok' => true, 'job' => $this->format_job_row($row)];
    }

    public function failed_jobs(array $filters = []): array
    {
        if (!$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Queue runtime POS belum siap.'];
        }

        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $q = trim((string)($filters['q'] ?? ''));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $db = $this->CI->db->from('pos_runtime_job j')
            ->join('pos_order o', 'o.id = j.order_id', 'inner')
            ->join('pos_stock_commit s', 's.id = j.snapshot_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->where('j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
            ->where('j.status', 'FAILED');
        if ($outletId > 0) {
            $db->where('o.outlet_id', $outletId);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('o.order_no', $q)
                ->or_like('s.commit_no', $q)
                ->or_like('j.job_code', $q)
                ->or_like('po.outlet_name', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('j.last_error', $q)
            ->group_end();
        }

        $rows = $db->select("\n                j.id, j.job_code, j.status, j.order_id, j.snapshot_id, j.attempts, j.max_attempts,\n                j.run_after, j.started_at, j.finished_at, j.last_error, j.updated_at, j.created_at,\n                o.order_no, o.status AS order_status, o.stock_commit_status, o.confirmed_at, o.outlet_id,\n                po.outlet_name,\n                e.employee_name AS cashier_employee_name,\n                s.commit_no, s.commit_status AS snapshot_commit_status\n            ", false)
            ->order_by('j.updated_at', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();

        return ['ok' => true, 'rows' => array_map([$this, 'format_job_row'], $rows)];
    }

    public function active_jobs(array $filters = []): array
    {
        if (!$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Queue runtime POS belum siap.'];
        }

        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $q = trim((string)($filters['q'] ?? ''));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $db = $this->CI->db->from('pos_runtime_job j')
            ->join('pos_order o', 'o.id = j.order_id', 'inner')
            ->join('pos_stock_commit s', 's.id = j.snapshot_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->where('j.job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
            ->where_in('j.status', ['QUEUED', 'PROCESSING']);
        if ($outletId > 0) {
            $db->where('o.outlet_id', $outletId);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('o.order_no', $q)
                ->or_like('s.commit_no', $q)
                ->or_like('j.job_code', $q)
                ->or_like('po.outlet_name', $q)
                ->or_like('e.employee_name', $q)
            ->group_end();
        }

        $rows = $db->select("\n                j.id, j.job_code, j.status, j.order_id, j.snapshot_id, j.attempts, j.max_attempts,\n                j.run_after, j.started_at, j.finished_at, j.last_error, j.updated_at, j.created_at,\n                o.order_no, o.status AS order_status, o.stock_commit_status, o.confirmed_at, o.outlet_id,\n                po.outlet_name,\n                e.employee_name AS cashier_employee_name,\n                s.commit_no, s.commit_status AS snapshot_commit_status\n            ", false)
            ->order_by('j.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();

        return ['ok' => true, 'rows' => array_map([$this, 'format_job_row'], $rows)];
    }

    public function failed_commit_snapshots(array $filters = []): array
    {
        if (!$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Queue runtime POS belum siap.'];
        }

        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $q = trim((string)($filters['q'] ?? ''));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));

        $db = $this->CI->db->from('pos_stock_commit s')
            ->join('pos_order o', 'o.id = s.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join(
                "(
                    SELECT j1.snapshot_id,
                           j1.id AS latest_job_id,
                           j1.job_code AS latest_job_code,
                           j1.status AS latest_job_status,
                           j1.last_error AS latest_job_error,
                           j1.updated_at AS latest_job_updated_at
                    FROM pos_runtime_job j1
                    INNER JOIN (
                        SELECT snapshot_id, MAX(id) AS max_id
                        FROM pos_runtime_job
                        WHERE job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
                        GROUP BY snapshot_id
                    ) j2 ON j2.max_id = j1.id
                ) lj",
                'lj.snapshot_id = s.id',
                'left',
                false
            )
            ->where('s.commit_status', 'FAILED');

        if ($outletId > 0) {
            $db->where('o.outlet_id', $outletId);
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            $db->where("DATE(COALESCE(o.confirmed_at, o.ordered_at, s.committed_at, s.created_at)) >= " . $this->CI->db->escape($dateFrom), null, false);
        }
        if ($dateTo !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo)) {
            $db->where("DATE(COALESCE(o.confirmed_at, o.ordered_at, s.committed_at, s.created_at)) <= " . $this->CI->db->escape($dateTo), null, false);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('o.order_no', $q)
                ->or_like('s.commit_no', $q)
                ->or_like('po.outlet_name', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('lj.latest_job_error', $q)
                ->or_like('s.notes', $q)
            ->group_end();
        }

        $rows = $db->select("
                s.id, s.commit_no, s.order_id, s.commit_status, s.commit_reason, s.process_state_snapshot,
                s.committed_at, s.reversed_at, s.last_rebuild_at, s.notes, s.created_at, s.updated_at,
                o.order_no, o.status AS order_status, o.stock_commit_status, o.confirmed_at, o.ordered_at, o.outlet_id,
                po.outlet_name,
                e.employee_name AS cashier_employee_name,
                lj.latest_job_id, lj.latest_job_code, lj.latest_job_status, lj.latest_job_error, lj.latest_job_updated_at
            ", false)
            ->order_by('COALESCE(s.updated_at, s.created_at)', 'DESC', false)
            ->limit($limit)
            ->get()
            ->result_array();

        return ['ok' => true, 'rows' => array_map([$this, 'format_snapshot_row'], $rows)];
    }

    public function retry_job(int $jobId, int $actorEmployeeId = 0): array
    {
        if ($jobId <= 0 || !$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak valid untuk retry.'];
        }

        $job = $this->job_context($jobId);
        if (!$job) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak ditemukan.'];
        }

        $terminalReason = $this->terminal_reason_from_context($job);
        if ($terminalReason !== '') {
            $this->cancel_terminal_job($jobId, $terminalReason);
            return ['ok' => false, 'message' => 'Job tidak dapat dijalankan ulang. ' . $terminalReason];
        }

        $status = strtoupper((string)($job['status'] ?? ''));
        if (!in_array($status, ['FAILED', 'CANCELLED'], true)) {
            return ['ok' => false, 'message' => 'Hanya job FAILED/CANCELLED yang bisa di-retry manual.'];
        }

        $hasPartialReversal = strtoupper((string)($job['snapshot_commit_status'] ?? '')) === 'PARTIAL_REVERSED';

        $this->CI->db->where('id', $jobId)->update('pos_runtime_job', [
            'status' => 'QUEUED',
            'attempts' => 0,
            'run_after' => date('Y-m-d H:i:s'),
            'started_at' => null,
            'finished_at' => null,
            'last_error' => null,
            'result_json' => null,
        ]);

        $orderId = max(0, (int)($job['order_id'] ?? 0));
        $snapshotId = max(0, (int)($job['snapshot_id'] ?? 0));
        if (!$hasPartialReversal && $snapshotId > 0) {
            $this->CI->posstockcommitservice->mark_queued($snapshotId);
        }
        if (!$hasPartialReversal && $orderId > 0) {
            $this->CI->Pos_model->update_order_stock_commit_state($orderId, 'QUEUED', [
                'actor_employee_id' => $actorEmployeeId,
                'event_code' => 'ORDER_CONFIRM_STOCK_RETRY',
                'note' => 'Job stock commit POS diantrekan ulang secara manual.',
            ]);
        }

        return $this->latest_job_for_order($orderId);
    }

    public function process_pending_jobs(array $options = []): array
    {
        if (!$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Queue runtime POS belum siap.'];
        }

        $limit = max(1, min(25, (int)($options['limit'] ?? 5)));
        $orderId = max(0, (int)($options['order_id'] ?? 0));
        $jobId = max(0, (int)($options['job_id'] ?? 0));
        $outletId = max(0, (int)($options['outlet_id'] ?? 0));

        $jobs = $this->candidate_jobs($limit, $orderId, $jobId, $outletId);
        if (empty($jobs)) {
            return [
                'ok' => true,
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'jobs' => [],
            ];
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        foreach ($jobs as $job) {
            $processed = $this->process_job((int)$job['id']);
            $results[] = $processed;
            if (!($processed['ok'] ?? false)) {
                $failedCount++;
                continue;
            }
            $status = strtoupper((string)($processed['job']['status'] ?? ''));
            if ($status === 'SUCCESS') {
                $successCount++;
            } elseif ($status === 'FAILED') {
                $failedCount++;
            }
        }

        return [
            'ok' => true,
            'processed_count' => count($results),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'jobs' => $results,
        ];
    }

    public function process_job(int $jobId): array
    {
        if ($jobId <= 0 || !$this->queue_tables_ready()) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak valid.'];
        }

        $claim = $this->claim_job($jobId);
        if (!($claim['ok'] ?? false)) {
            return $claim;
        }
        if (!empty($claim['skip'])) {
            return [
                'ok' => true,
                'job' => $this->format_job_row((array)($claim['job'] ?? [])),
                'skipped' => true,
            ];
        }

        $job = (array)($claim['job'] ?? []);
        $currentContext = $this->job_context($jobId);
        $terminalReason = $this->terminal_reason_from_context((array)($currentContext ?: $job));
        if ($terminalReason !== '') {
            return $this->cancel_terminal_job($jobId, $terminalReason);
        }
        $payload = $this->decode_json((string)($job['payload_json'] ?? ''));
        $orderId = max(0, (int)($job['order_id'] ?? ($payload['order_id'] ?? 0)));
        $snapshotId = max(0, (int)($job['snapshot_id'] ?? ($payload['snapshot_id'] ?? 0)));
        $actorEmployeeId = max(0, (int)($payload['actor_employee_id'] ?? 0));
        $eventSource = strtoupper(trim((string)($payload['event_source'] ?? 'ORDER_CONFIRM')));
        $eventId = max(0, (int)($payload['event_id'] ?? $orderId));

        if ($orderId <= 0 || $snapshotId <= 0) {
            return $this->fail_job($job, 'Payload job runtime POS tidak valid.', [], false, false);
        }

        $hasPartialReversal = strtoupper((string)($currentContext['snapshot_commit_status'] ?? '')) === 'PARTIAL_REVERSED';
        if (!$hasPartialReversal) {
            $orderProcessing = $this->CI->Pos_model->update_order_stock_commit_state($orderId, 'PROCESSING', [
                'actor_employee_id' => $actorEmployeeId,
                'event_code' => 'ORDER_CONFIRM_STOCK_QUEUE_PROCESS',
                'note' => 'Queue POS sedang memproses stock commit #' . $snapshotId . '.',
            ]);
            if (!($orderProcessing['ok'] ?? false)) {
                return $this->fail_job($job, (string)($orderProcessing['message'] ?? 'Status stock commit order POS gagal ditandai processing.'));
            }
            $markProcessing = $this->CI->posstockcommitservice->mark_processing($snapshotId);
            if (!($markProcessing['ok'] ?? false)) {
                return $this->fail_job($job, (string)($markProcessing['message'] ?? 'Snapshot stock commit gagal ditandai processing.'));
            }

            $refreshSnapshot = $this->CI->posstockcommitservice->refresh_snapshot_from_order($snapshotId, $actorEmployeeId, [
                'event_source' => $eventSource,
                'event_id' => $eventId,
            ]);
            if (!($refreshSnapshot['ok'] ?? false)) {
                return $this->fail_job($job, (string)($refreshSnapshot['message'] ?? 'Snapshot stock commit gagal di-refresh sebelum retry.'), [
                    'refresh_snapshot' => $refreshSnapshot,
                ]);
            }
        }

        $stockPost = $this->CI->posorderstockservice->post_commit_snapshot($snapshotId, [
            'actor_employee_id' => $actorEmployeeId,
        ]);
        if (!($stockPost['ok'] ?? false)) {
            return $this->fail_job($job, (string)($stockPost['message'] ?? 'Posting stok order POS gagal.'), [
                'stock_post' => $stockPost,
            ]);
        }

        $commit = $this->CI->posstockcommitservice->mark_committed($snapshotId);
        if (!($commit['ok'] ?? false)) {
            return $this->fail_job($job, (string)($commit['message'] ?? 'Snapshot stock commit gagal ditandai committed.'), [
                'stock_post' => $stockPost,
            ], true, false);
        }

        $postedAt = date('Y-m-d H:i:s');
        $orderState = $this->CI->Pos_model->update_order_stock_commit_state($orderId, 'POSTED', [
            'actor_employee_id' => $actorEmployeeId,
            'event_code' => 'ORDER_CONFIRM_STOCK_POSTED',
            'note' => 'Stock commit POS diposting dari queue runtime.',
            'stock_committed_at' => $postedAt,
        ]);
        if (!($orderState['ok'] ?? false)) {
            return $this->fail_job($job, (string)($orderState['message'] ?? 'Status stock commit order POS gagal diupdate.'), [
                'stock_post' => $stockPost,
            ], true, true);
        }

        $availability = $this->refresh_stock_live_for_order($orderId, $eventSource, $eventId, $actorEmployeeId);
        if (!($availability['ok'] ?? false)) {
            return $this->fail_job($job, (string)($availability['message'] ?? 'Rebuild availability POS gagal.'), [
                'stock_post' => $stockPost,
                'availability' => $availability,
            ], true, true);
        }

        return $this->complete_job($job, [
            'posted_lines' => (int)($stockPost['posted_lines'] ?? 0),
            'skipped_lines' => (int)($stockPost['skipped_lines'] ?? 0),
            'marked_product_count' => (int)($availability['marked_product_count'] ?? 0),
            'availability_success_count' => (int)($availability['success_count'] ?? 0),
            'availability_failed_count' => (int)($availability['failed_count'] ?? 0),
        ]);
    }

    protected function candidate_jobs(int $limit, int $orderId = 0, int $jobId = 0, int $outletId = 0): array
    {
        if ($jobId > 0) {
            $row = $this->CI->db->from('pos_runtime_job')
                ->where('id', $jobId)
                ->where('job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
                ->limit(1)
                ->get()
                ->row_array();
            return $row ? [$row] : [];
        }

        $sql = "SELECT j.*
                FROM pos_runtime_job j
                INNER JOIN pos_order o ON o.id = j.order_id
                LEFT JOIN pos_stock_commit s ON s.id = j.snapshot_id
                WHERE j.job_type = 'ORDER_CONFIRM_STOCK_COMMIT'
                  AND j.run_after <= ?
                  AND UPPER(COALESCE(o.status, '')) NOT IN ('VOID', 'REFUND_FULL', 'REFUNDED_FULL')
                  AND UPPER(COALESCE(o.stock_commit_status, '')) <> 'REVERSED'
                  AND UPPER(COALESCE(s.commit_status, '')) NOT IN ('REVERSED', 'VOID')
                  AND (
                      j.status = 'QUEUED'
                      OR (
                          j.status = 'PROCESSING'
                          AND (
                              j.started_at IS NULL
                              OR j.started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                          )
                      )
                      OR (j.status = 'FAILED' AND j.attempts < j.max_attempts)
                  )";
        $params = [date('Y-m-d H:i:s')];
        if ($orderId > 0) {
            $sql .= ' AND j.order_id = ?';
            $params[] = $orderId;
        }
        if ($outletId > 0) {
            $sql .= ' AND o.outlet_id = ?';
            $params[] = $outletId;
        }
        $sql .= ' ORDER BY j.id ASC LIMIT ' . (int)$limit;

        return $this->CI->db->query($sql, $params)->result_array();
    }

    protected function claim_job(int $jobId): array
    {
        $seed = $this->CI->db->select('order_id, snapshot_id')->from('pos_runtime_job')->where('id', $jobId)->limit(1)->get()->row_array();
        if (!$seed) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak ditemukan.'];
        }

        $db = $this->CI->db;
        $db->trans_begin();
        try {
            // Lock the order before the job. Void/refund uses the same order lock,
            // so a late worker cannot post stock after the order becomes terminal.
            $order = null;
            if ((int)($seed['order_id'] ?? 0) > 0) {
                $order = $db->query(
                    'SELECT id, status, stock_commit_status, stock_committed_at FROM pos_order WHERE id = ? FOR UPDATE',
                    [(int)$seed['order_id']]
                )->row_array();
            }
            $job = $db->query('SELECT * FROM pos_runtime_job WHERE id = ? FOR UPDATE', [$jobId])->row_array();
            if (!$job) {
                throw new RuntimeException('Job runtime POS tidak ditemukan saat akan diproses.');
            }
            $snapshot = null;
            if ((int)($job['snapshot_id'] ?? 0) > 0) {
                $snapshot = $db->query(
                    'SELECT id, commit_status, commit_no FROM pos_stock_commit WHERE id = ? FOR UPDATE',
                    [(int)$job['snapshot_id']]
                )->row_array();
            }
            $job['order_status'] = (string)($order['status'] ?? '');
            $job['stock_commit_status'] = (string)($order['stock_commit_status'] ?? '');
            $job['stock_committed_at'] = $order['stock_committed_at'] ?? null;
            $job['snapshot_commit_status'] = (string)($snapshot['commit_status'] ?? '');
            $job['commit_no'] = (string)($snapshot['commit_no'] ?? '');

            $status = strtoupper((string)($job['status'] ?? ''));
            if (in_array($status, ['SUCCESS', 'CANCELLED'], true)) {
                $db->trans_commit();
                return ['ok' => true, 'skip' => true, 'job' => $job];
            }

            $terminalReason = $this->terminal_reason_from_context($job);
            if ($terminalReason !== '') {
                $update = $this->terminal_cancellation_payload($terminalReason);
                $db->where('id', $jobId)->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])->update('pos_runtime_job', $update);
                if ($db->trans_status() === false) {
                    throw new RuntimeException('Gagal membatalkan job POS yang sudah terminal.');
                }
                $db->trans_commit();
                return ['ok' => true, 'skip' => true, 'job' => array_merge($job, $update), 'message' => $terminalReason];
            }

            if ($status === 'PROCESSING') {
                $startedAt = strtotime((string)($job['started_at'] ?? ''));
                if ($startedAt > 0 && $startedAt >= strtotime('-5 minutes')) {
                    $db->trans_commit();
                    return ['ok' => true, 'skip' => true, 'job' => $job];
                }
            }
            if ((int)($job['attempts'] ?? 0) >= (int)($job['max_attempts'] ?? 0) && $status === 'FAILED') {
                $db->trans_commit();
                return ['ok' => true, 'skip' => true, 'job' => $job];
            }

            $attempts = (int)($job['attempts'] ?? 0) + 1;
            $update = [
                'status' => 'PROCESSING',
                'attempts' => $attempts,
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => null,
                'last_error' => null,
            ];
            $db->where('id', $jobId)->update('pos_runtime_job', $update);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal mengunci job runtime POS untuk diproses.');
            }
            $db->trans_commit();

            return ['ok' => true, 'job' => array_merge($job, $update), 'skip' => false];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function complete_job(array $job, array $result = []): array
    {
        $terminalReason = $this->terminal_reason_from_context((array)($this->job_context((int)$job['id']) ?: $job));
        if ($terminalReason !== '') {
            return $this->cancel_terminal_job((int)$job['id'], $terminalReason);
        }
        $payload = [
            'status' => 'SUCCESS',
            'finished_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
            'result_json' => $this->encode_json($result),
        ];
        $this->CI->db->where('id', (int)$job['id'])->where('status', 'PROCESSING')->update('pos_runtime_job', $payload);
        $row = $this->job_context((int)$job['id']);
        if ($this->CI->db->affected_rows() <= 0) {
            if ($row && strtoupper((string)($row['status'] ?? '')) === 'CANCELLED') {
                return ['ok' => true, 'skipped' => true, 'job' => $this->format_job_row($row)];
            }
            return ['ok' => false, 'message' => 'Status job runtime POS berubah sebelum proses selesai.', 'job' => $this->format_job_row((array)$row)];
        }

        return [
            'ok' => true,
            'job' => $this->format_job_row((array)$row),
            'result' => $result,
        ];
    }

    protected function fail_job(array $job, string $message, array $result = [], bool $preserveSnapshotStatus = false, bool $preserveOrderStatus = false): array
    {
        $terminalReason = $this->terminal_reason_from_context((array)($this->job_context((int)$job['id']) ?: $job));
        if ($terminalReason !== '') {
            return $this->cancel_terminal_job((int)$job['id'], $terminalReason);
        }
        $delaySeconds = min(300, max(15, ((int)($job['attempts'] ?? 1)) * 20));
        $payload = [
            'status' => 'FAILED',
            'finished_at' => date('Y-m-d H:i:s'),
            'last_error' => trim($message) !== '' ? trim($message) : 'Job runtime POS gagal diproses.',
            'result_json' => $this->encode_json($result),
            'run_after' => date('Y-m-d H:i:s', time() + $delaySeconds),
        ];
        $this->CI->db->where('id', (int)$job['id'])->where('status', 'PROCESSING')->update('pos_runtime_job', $payload);
        if ($this->CI->db->affected_rows() <= 0) {
            $current = $this->job_context((int)$job['id']);
            if ($current && strtoupper((string)($current['status'] ?? '')) === 'CANCELLED') {
                return ['ok' => true, 'skipped' => true, 'job' => $this->format_job_row($current)];
            }
            return ['ok' => false, 'message' => 'Status job runtime POS berubah sebelum kegagalan dicatat.', 'job' => $this->format_job_row((array)$current)];
        }

        $snapshotId = max(0, (int)($job['snapshot_id'] ?? 0));
        if (!$preserveSnapshotStatus && $snapshotId > 0) {
            $this->CI->posstockcommitservice->mark_failed($snapshotId, $payload['last_error']);
        }

        $orderId = max(0, (int)($job['order_id'] ?? 0));
        if (!$preserveOrderStatus && $orderId > 0) {
            $actorEmployeeId = 0;
            $decoded = $this->decode_json((string)($job['payload_json'] ?? ''));
            if (!empty($decoded['actor_employee_id'])) {
                $actorEmployeeId = (int)$decoded['actor_employee_id'];
            }
            $this->CI->Pos_model->update_order_stock_commit_state($orderId, 'FAILED', [
                'actor_employee_id' => $actorEmployeeId,
                'event_code' => 'ORDER_CONFIRM_STOCK_FAILED',
                'note' => $payload['last_error'],
            ]);
        }

        $current = $this->job_context((int)$job['id']);

        return [
            'ok' => false,
            'message' => $payload['last_error'],
            'job' => $this->format_job_row((array)$current),
            'result' => $result,
        ];
    }

    protected function refresh_stock_live_for_order(int $orderId, string $eventSource, int $eventId, int $actorEmployeeId): array
    {
        $order = $this->CI->Pos_model->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order tidak ditemukan untuk refresh stock live.'];
        }

        $header = (array)($order['header'] ?? []);
        $outletId = max(0, (int)($header['outlet_id'] ?? 0));
        $productIds = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if ($outletId <= 0 || empty($productIds)) {
            return ['ok' => false, 'message' => 'Outlet atau produk order tidak siap untuk refresh stock live.'];
        }

        $productIds = array_values($productIds);
        $dirty = $this->CI->posavailabilityrebuildservice->mark_dirty($outletId, $productIds, [
            'event_source' => $eventSource,
        ]);

        $rebuilt = $this->CI->posavailabilityrebuildservice->rebuild_products($outletId, $productIds, [
            'trigger_context' => $eventSource,
            'event_source' => $eventSource,
            'event_table' => 'pos_order',
            'event_id' => $eventId ?: $orderId,
            'actor_employee_id' => $actorEmployeeId,
        ]);
        if (!($rebuilt['ok'] ?? false)) {
            return $rebuilt + [
                'dirty' => $dirty,
                'marked_product_count' => count($productIds),
            ];
        }

        $rebuilt['dirty'] = $dirty;
        if (!isset($rebuilt['marked_product_count'])) {
            $rebuilt['marked_product_count'] = count($productIds);
        }

        return $rebuilt;
    }

    protected function job_context(int $jobId): ?array
    {
        if ($jobId <= 0) {
            return null;
        }

        $row = $this->CI->db->query(
            "SELECT j.*, o.status AS order_status, o.stock_commit_status, o.stock_committed_at,
                    s.commit_status AS snapshot_commit_status, s.commit_no
             FROM pos_runtime_job j
             LEFT JOIN pos_order o ON o.id = j.order_id
             LEFT JOIN pos_stock_commit s ON s.id = j.snapshot_id
             WHERE j.id = ?
             LIMIT 1",
            [$jobId]
        )->row_array();

        return $row ?: null;
    }

    protected function terminal_reason_for_order_snapshot(int $orderId, int $snapshotId): string
    {
        if ($orderId <= 0) {
            return 'Order POS tidak valid untuk queue stock commit.';
        }

        $row = $this->CI->db->query(
            "SELECT o.status AS order_status, o.stock_commit_status,
                    s.commit_status AS snapshot_commit_status
             FROM pos_order o
             LEFT JOIN pos_stock_commit s ON s.id = ?
             WHERE o.id = ?
             LIMIT 1",
            [$snapshotId, $orderId]
        )->row_array();

        if (!$row) {
            return 'Order POS tidak ditemukan untuk queue stock commit.';
        }

        return $this->terminal_reason_from_context($row);
    }

    protected function terminal_reason_from_context(array $context): string
    {
        $orderStatus = strtoupper(trim((string)($context['order_status'] ?? '')));
        if (in_array($orderStatus, ['VOID', 'REFUND_FULL', 'REFUNDED_FULL'], true)) {
            return 'Order sudah ' . $orderStatus . '; job stock commit tidak boleh diproses.';
        }

        $orderCommitStatus = strtoupper(trim((string)($context['stock_commit_status'] ?? '')));
        if ($orderCommitStatus === 'REVERSED') {
            return 'Stock commit order sudah direversal; job tidak boleh diproses ulang.';
        }

        $snapshotStatus = strtoupper(trim((string)($context['snapshot_commit_status'] ?? '')));
        if (in_array($snapshotStatus, ['REVERSED', 'VOID'], true)) {
            return 'Snapshot stock commit sudah ' . $snapshotStatus . '; job tidak boleh diproses ulang.';
        }

        return '';
    }

    protected function terminal_cancellation_payload(string $reason): array
    {
        return [
            'status' => 'CANCELLED',
            'finished_at' => date('Y-m-d H:i:s'),
            'last_error' => trim($reason) !== '' ? trim($reason) : 'Job stock POS dibatalkan karena order sudah terminal.',
            'result_json' => $this->encode_json(['cancelled_reason' => trim($reason)]),
        ];
    }

    protected function cancel_terminal_job(int $jobId, string $reason): array
    {
        if ($jobId <= 0) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak valid untuk dibatalkan.'];
        }

        $this->CI->db
            ->where('id', $jobId)
            ->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])
            ->update('pos_runtime_job', $this->terminal_cancellation_payload($reason));

        $job = $this->job_context($jobId);
        if (!$job) {
            return ['ok' => false, 'message' => 'Job runtime POS tidak ditemukan saat dibatalkan.'];
        }

        return [
            'ok' => true,
            'skip' => true,
            'message' => $reason,
            'job' => $this->format_job_row($job),
        ];
    }

    protected function queue_tables_ready(): bool
    {
        return $this->CI->db->table_exists('pos_runtime_job')
            && $this->CI->db->table_exists('pos_order')
            && $this->CI->db->table_exists('pos_stock_commit');
    }

    protected function generate_job_code(): string
    {
        return 'PRJ-' . date('ymdHis') . '-' . substr(strtoupper(md5(uniqid('', true))), 0, 6);
    }

    protected function encode_json(array $payload): ?string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    protected function decode_json(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function format_job_row(array $row): array
    {
        if (!$row) {
            return [];
        }
        $row['attempts'] = (int)($row['attempts'] ?? 0);
        $row['max_attempts'] = (int)($row['max_attempts'] ?? 0);
        $row['order_id'] = (int)($row['order_id'] ?? 0);
        $row['snapshot_id'] = (int)($row['snapshot_id'] ?? 0);
        $row['payload'] = $this->decode_json((string)($row['payload_json'] ?? ''));
        $row['result'] = $this->decode_json((string)($row['result_json'] ?? ''));
        unset($row['payload_json'], $row['result_json']);
        return $row;
    }

    protected function format_snapshot_row(array $row): array
    {
        if (!$row) {
            return [];
        }
        $row['id'] = (int)($row['id'] ?? 0);
        $row['order_id'] = (int)($row['order_id'] ?? 0);
        $row['outlet_id'] = (int)($row['outlet_id'] ?? 0);
        $row['latest_job_id'] = (int)($row['latest_job_id'] ?? 0);
        return $row;
    }
}
