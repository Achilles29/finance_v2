<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Lightweight, coalescing worker queue for POS product availability cache.
 *
 * Inventory writers only mark a cache row dirty and enqueue its latest
 * revision. Expensive recipe/live-stock calculations then run outside the
 * purchase, adjustment, and production requests that caused the change.
 */
class PosAvailabilityQueueService
{
    /** @var CI_Controller */
    protected $CI;

    /** @var bool|null */
    protected $readyCache = null;

    /** @var array<int,bool> */
    protected $employeeExistsCache = [];

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    public function isReady(): bool
    {
        if ($this->readyCache !== null) {
            return $this->readyCache;
        }

        $db = $this->CI->db;
        if (
            !$db->table_exists('pos_product_availability_queue')
            || !$db->table_exists('pos_product_availability_cache')
            || !$db->table_exists('pos_outlet')
            || !$db->table_exists('mst_product')
        ) {
            $this->readyCache = false;
            return false;
        }

        // A partial migration must fall back to synchronous rebuilding instead
        // of failing an inventory transaction while writing a queue row.
        $queueColumns = [
            'outlet_id', 'product_id', 'status', 'revision', 'event_count',
            'attempts', 'max_attempts', 'run_after', 'started_at', 'finished_at',
            'event_source', 'event_table', 'event_id', 'actor_employee_id',
            'result_json', 'last_error', 'created_at',
        ];
        foreach ($queueColumns as $column) {
            if (!$db->field_exists($column, 'pos_product_availability_queue')) {
                $this->readyCache = false;
                return false;
            }
        }
        foreach (['outlet_id', 'product_id', 'is_dirty'] as $column) {
            if (!$db->field_exists($column, 'pos_product_availability_cache')) {
                $this->readyCache = false;
                return false;
            }
        }

        $this->readyCache = true;
        return true;
    }

    /**
     * Queue the latest rebuild work once for each outlet/product target.
     * A unique key collapses repeated inventory events into one pending row.
     */
    public function enqueueProducts(array $outletIds, array $productIds, array $context = []): array
    {
        $outletIds = $this->normalizeIds($outletIds);
        $productIds = $this->normalizeIds($productIds);
        if (empty($outletIds) || empty($productIds)) {
            return [
                'ok' => true,
                'mode' => 'QUEUE',
                'outlet_count' => count($outletIds),
                'product_count' => count($productIds),
                'queued_count' => 0,
                'dirty_count' => 0,
            ];
        }
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Antrean availability POS belum tersedia.'];
        }

        $db = $this->CI->db;
        $eventSource = strtoupper(trim((string)($context['event_source'] ?? 'INVENTORY_CHANGE')));
        $eventTable = trim((string)($context['event_table'] ?? ''));
        $eventId = max(0, (int)($context['event_id'] ?? 0));
        $actorEmployeeId = $this->resolveActorEmployeeId($context);
        $maxAttempts = max(1, min(10, (int)($context['max_attempts'] ?? 3)));
        $now = date('Y-m-d H:i:s');
        $dirtyCount = 0;
        $queuedCount = 0;
        $results = [];

        foreach ($outletIds as $outletId) {
            $dirtyPayload = ['is_dirty' => 1];
            if ($db->field_exists('last_commit_event', 'pos_product_availability_cache')) {
                $dirtyPayload['last_commit_event'] = $eventSource !== '' ? $eventSource : 'INVENTORY_CHANGE';
            }
            $db->where('outlet_id', $outletId)
                ->where_in('product_id', $productIds)
                ->update('pos_product_availability_cache', $dirtyPayload);
            $dirtyCount += max(0, (int)$db->affected_rows());

            foreach ($productIds as $productId) {
                // A newer event receives a fresh retry budget unless a
                // worker is actively processing this exact target.
                $sql = "INSERT INTO pos_product_availability_queue (
                            outlet_id, product_id, status, revision, event_count,
                            attempts, max_attempts, run_after, started_at, finished_at,
                            event_source, event_table, event_id, actor_employee_id,
                            result_json, last_error, created_at
                        ) VALUES (?, ?, 'QUEUED', 1, 1, 0, ?, ?, NULL, NULL, ?, ?, ?, ?, NULL, NULL, ?)
                        ON DUPLICATE KEY UPDATE
                            revision = revision + 1,
                            event_count = event_count + 1,
                            attempts = IF(status = 'PROCESSING', attempts, 0),
                            status = IF(status = 'PROCESSING', 'PROCESSING', 'QUEUED'),
                            max_attempts = GREATEST(max_attempts, VALUES(max_attempts)),
                            run_after = VALUES(run_after),
                            started_at = IF(status = 'PROCESSING', started_at, NULL),
                            finished_at = NULL,
                            event_source = VALUES(event_source),
                            event_table = VALUES(event_table),
                            event_id = VALUES(event_id),
                            actor_employee_id = VALUES(actor_employee_id),
                            result_json = NULL,
                            last_error = NULL";
                $ok = $db->query($sql, [
                    $outletId,
                    $productId,
                    $maxAttempts,
                    $now,
                    $eventSource !== '' ? $eventSource : null,
                    $eventTable !== '' ? $eventTable : null,
                    $eventId > 0 ? $eventId : null,
                    $actorEmployeeId,
                    $now,
                ]);
                if ($ok === false) {
                    $error = $db->error();
                    $results[] = [
                        'outlet_id' => $outletId,
                        'product_id' => $productId,
                        'ok' => false,
                        'message' => (string)($error['message'] ?? 'Gagal mengantre rebuild availability POS.'),
                    ];
                    continue;
                }
                $queuedCount++;
                $results[] = [
                    'outlet_id' => $outletId,
                    'product_id' => $productId,
                    'ok' => true,
                ];
            }
        }

        $failedCount = count(array_filter($results, static function (array $row): bool {
            return empty($row['ok']);
        }));

        return [
            'ok' => $failedCount === 0,
            'mode' => 'QUEUE',
            'outlet_count' => count($outletIds),
            'product_count' => count($productIds),
            'queued_count' => $queuedCount,
            'dirty_count' => $dirtyCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    public function processPendingJobs(array $options = []): array
    {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Antrean availability POS belum tersedia.'];
        }

        $limit = max(1, min(200, (int)($options['limit'] ?? 25)));
        $outletId = max(0, (int)($options['outlet_id'] ?? 0));
        $productId = max(0, (int)($options['product_id'] ?? 0));
        $jobs = $this->candidateJobs($limit, $outletId, $productId);
        if (empty($jobs)) {
            return [
                'ok' => true,
                'processed_count' => 0,
                'success_count' => 0,
                'queued_again_count' => 0,
                'failed_count' => 0,
                'jobs' => [],
            ];
        }

        $results = [];
        $successCount = 0;
        $queuedAgainCount = 0;
        $failedCount = 0;
        foreach ($jobs as $job) {
            $result = $this->processJob((int)($job['id'] ?? 0));
            $results[] = $result;
            $status = strtoupper((string)($result['job']['status'] ?? ''));
            if (!($result['ok'] ?? false) || $status === 'FAILED') {
                $failedCount++;
            } elseif ($status === 'SUCCESS') {
                $successCount++;
            } elseif ($status === 'QUEUED') {
                $queuedAgainCount++;
            }
        }

        return [
            'ok' => $failedCount === 0,
            'processed_count' => count($results),
            'success_count' => $successCount,
            'queued_again_count' => $queuedAgainCount,
            'failed_count' => $failedCount,
            'jobs' => $results,
        ];
    }

    /**
     * Give one exhausted job a fresh retry budget without changing stock,
     * recipes, or the cache itself. The next worker/manual run owns rebuild.
     */
    public function retryJob(int $jobId): array
    {
        if ($jobId <= 0 || !$this->isReady()) {
            return ['ok' => false, 'message' => 'Job availability POS tidak valid atau antrean belum tersedia.'];
        }

        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $job = $db->query(
                'SELECT * FROM pos_product_availability_queue WHERE id = ? FOR UPDATE',
                [$jobId]
            )->row_array();
            if (!$job) {
                $db->trans_rollback();
                return ['ok' => false, 'message' => 'Job availability POS tidak ditemukan.'];
            }
            if (strtoupper((string)($job['status'] ?? '')) !== 'FAILED') {
                $db->trans_commit();
                return [
                    'ok' => false,
                    'message' => 'Hanya job berstatus gagal yang perlu diulang.',
                    'job' => $this->formatJob($job),
                ];
            }

            $payload = [
                'status' => 'QUEUED',
                'attempts' => 0,
                'run_after' => date('Y-m-d H:i:s'),
                'started_at' => null,
                'finished_at' => null,
                'last_error' => null,
            ];
            $db->where('id', $jobId)->update('pos_product_availability_queue', $payload);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Job availability POS gagal dimasukkan ulang ke antrean.');
            }
            $db->trans_commit();

            return [
                'ok' => true,
                'message' => 'Job dimasukkan ulang ke antrean dan akan diproses oleh cron atau tombol proses sekali.',
                'job' => $this->formatJob(array_merge($job, $payload)),
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function processJob(int $jobId): array
    {
        if ($jobId <= 0 || !$this->isReady()) {
            return ['ok' => false, 'message' => 'Job availability POS tidak valid.'];
        }

        $claim = $this->claimJob($jobId);
        if (!($claim['ok'] ?? false)) {
            return $claim;
        }
        if (!empty($claim['skip'])) {
            return ['ok' => true, 'skipped' => true, 'job' => $this->formatJob((array)($claim['job'] ?? []))];
        }

        $job = (array)($claim['job'] ?? []);
        $this->CI->load->library('PosAvailabilityRebuildService');
        $rebuild = $this->CI->posavailabilityrebuildservice->rebuild_product(
            (int)$job['outlet_id'],
            (int)$job['product_id'],
            [
                'trigger_context' => 'POS_AVAILABILITY_QUEUE',
                'event_source' => (string)($job['event_source'] ?? 'INVENTORY_CHANGE'),
                'event_table' => (string)($job['event_table'] ?? 'pos_product_availability_queue'),
                'event_id' => (int)($job['event_id'] ?? 0),
                'actor_employee_id' => (int)($job['actor_employee_id'] ?? 0),
            ]
        );

        if (!($rebuild['ok'] ?? false)) {
            return $this->failJob($job, (string)($rebuild['message'] ?? 'Rebuild availability POS gagal.'), [
                'rebuild' => $rebuild,
            ]);
        }

        return $this->completeJob($job, [
            'cache_id' => (int)(($rebuild['cache']['id'] ?? 0)),
            'availability_status' => (string)(($rebuild['cache']['availability_status'] ?? '')),
            'estimated_available_qty' => round((float)(($rebuild['cache']['estimated_available_qty'] ?? 0)), 4),
            'hpp_live_snapshot' => round((float)(($rebuild['cache']['hpp_live_snapshot'] ?? 0)), 6),
            'log_id' => (int)($rebuild['log_id'] ?? 0),
        ]);
    }

    protected function candidateJobs(int $limit, int $outletId = 0, int $productId = 0): array
    {
        $sql = "SELECT q.*
                FROM pos_product_availability_queue q
                WHERE q.run_after <= NOW()
                  AND (
                      q.status = 'QUEUED'
                      OR (q.status = 'FAILED' AND q.attempts < q.max_attempts)
                      OR (
                          q.status = 'PROCESSING'
                          AND (
                              q.started_at IS NULL
                              OR q.started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                          )
                      )
                  )";
        $params = [];
        if ($outletId > 0) {
            $sql .= ' AND q.outlet_id = ?';
            $params[] = $outletId;
        }
        if ($productId > 0) {
            $sql .= ' AND q.product_id = ?';
            $params[] = $productId;
        }
        $sql .= ' ORDER BY q.run_after ASC, q.id ASC LIMIT ' . (int)$limit;

        return $this->CI->db->query($sql, $params)->result_array();
    }

    protected function claimJob(int $jobId): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $job = $db->query('SELECT * FROM pos_product_availability_queue WHERE id = ? FOR UPDATE', [$jobId])->row_array();
            if (!$job) {
                $db->trans_rollback();
                return ['ok' => false, 'message' => 'Job availability POS tidak ditemukan.'];
            }

            $status = strtoupper((string)($job['status'] ?? ''));
            if (in_array($status, ['SUCCESS', 'CANCELLED'], true)) {
                $db->trans_commit();
                return ['ok' => true, 'skip' => true, 'job' => $job];
            }
            if ($status === 'PROCESSING') {
                $startedAt = strtotime((string)($job['started_at'] ?? ''));
                if ($startedAt > 0 && $startedAt >= strtotime('-5 minutes')) {
                    $db->trans_commit();
                    return ['ok' => true, 'skip' => true, 'job' => $job];
                }
            }
            if ($status === 'FAILED' && (int)($job['attempts'] ?? 0) >= (int)($job['max_attempts'] ?? 0)) {
                $db->trans_commit();
                return ['ok' => true, 'skip' => true, 'job' => $job];
            }

            $payload = [
                'status' => 'PROCESSING',
                'attempts' => (int)($job['attempts'] ?? 0) + 1,
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => null,
                'last_error' => null,
            ];
            $db->where('id', $jobId)->update('pos_product_availability_queue', $payload);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Job availability POS gagal ditandai sedang diproses.');
            }
            $db->trans_commit();

            return ['ok' => true, 'skip' => false, 'job' => array_merge($job, $payload)];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function completeJob(array $job, array $result): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $current = $db->query('SELECT * FROM pos_product_availability_queue WHERE id = ? FOR UPDATE', [(int)$job['id']])->row_array();
            if (!$current) {
                $db->trans_rollback();
                return ['ok' => false, 'message' => 'Job availability POS hilang saat diselesaikan.'];
            }

            $hasNewRevision = (int)($current['revision'] ?? 0) !== (int)($job['revision'] ?? 0);
            $payload = [
                'status' => $hasNewRevision ? 'QUEUED' : 'SUCCESS',
                'run_after' => $hasNewRevision ? date('Y-m-d H:i:s') : (string)($current['run_after'] ?? date('Y-m-d H:i:s')),
                'started_at' => null,
                'finished_at' => $hasNewRevision ? null : date('Y-m-d H:i:s'),
                'last_error' => null,
                'result_json' => $this->encodeJson($result + [
                    'processed_revision' => (int)($job['revision'] ?? 0),
                    'latest_revision' => (int)($current['revision'] ?? 0),
                    'requeued_due_to_newer_event' => $hasNewRevision ? 1 : 0,
                ]),
            ];
            if ($hasNewRevision) {
                $payload['attempts'] = 0;
            }
            $db->where('id', (int)$job['id'])->update('pos_product_availability_queue', $payload);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Job availability POS gagal diselesaikan.');
            }
            $db->trans_commit();

            return [
                'ok' => true,
                'job' => $this->formatJob(array_merge($current, $payload)),
                'result' => $result,
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function failJob(array $job, string $message, array $result = []): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $current = $db->query('SELECT * FROM pos_product_availability_queue WHERE id = ? FOR UPDATE', [(int)$job['id']])->row_array();
            if (!$current) {
                $db->trans_rollback();
                return ['ok' => false, 'message' => 'Job availability POS hilang saat menulis kegagalan.'];
            }

            $hasNewRevision = (int)($current['revision'] ?? 0) !== (int)($job['revision'] ?? 0);
            $attempts = (int)($job['attempts'] ?? 1);
            $delaySeconds = min(300, max(15, $attempts * 20));
            $payload = [
                'status' => $hasNewRevision ? 'QUEUED' : 'FAILED',
                'run_after' => date('Y-m-d H:i:s', time() + ($hasNewRevision ? 0 : $delaySeconds)),
                'started_at' => null,
                'finished_at' => date('Y-m-d H:i:s'),
                'last_error' => $hasNewRevision ? null : (trim($message) !== '' ? trim($message) : 'Rebuild availability POS gagal.'),
                'result_json' => $this->encodeJson($result + [
                    'processed_revision' => (int)($job['revision'] ?? 0),
                    'latest_revision' => (int)($current['revision'] ?? 0),
                    'requeued_due_to_newer_event' => $hasNewRevision ? 1 : 0,
                ]),
            ];
            if ($hasNewRevision) {
                $payload['attempts'] = 0;
                $payload['finished_at'] = null;
            }
            $db->where('id', (int)$job['id'])->update('pos_product_availability_queue', $payload);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Job availability POS gagal menyimpan status error.');
            }
            $db->trans_commit();

            return [
                'ok' => false,
                'message' => (string)($payload['last_error'] ?? $message),
                'job' => $this->formatJob(array_merge($current, $payload)),
                'result' => $result,
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function normalizeIds(array $ids): array
    {
        $values = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $values[$id] = $id;
            }
        }
        return array_values($values);
    }

    /**
     * Queue rows use an employee FK, while material writers normally receive
     * an authenticated user ID. Resolve it before inserting to ensure an
     * optional availability refresh cannot invalidate a stock transaction.
     */
    protected function resolveActorEmployeeId(array $context): ?int
    {
        $explicitEmployeeId = max(0, (int)($context['actor_employee_id'] ?? 0));
        if ($explicitEmployeeId > 0) {
            if ($this->employeeExists($explicitEmployeeId)) {
                return $explicitEmployeeId;
            }
            log_message('warning', 'POS availability queue ignored unknown actor_employee_id=' . $explicitEmployeeId);
        }

        $actorUserId = max(0, (int)($context['actor_user_id'] ?? 0));
        if (
            $actorUserId <= 0
            || !$this->CI->db->table_exists('auth_user')
            || !$this->CI->db->field_exists('employee_id', 'auth_user')
        ) {
            return null;
        }

        $user = $this->CI->db
            ->select('employee_id')
            ->from('auth_user')
            ->where('id', $actorUserId)
            ->limit(1)
            ->get()
            ->row_array();
        $employeeId = max(0, (int)($user['employee_id'] ?? 0));
        if ($employeeId > 0 && $this->employeeExists($employeeId)) {
            return $employeeId;
        }

        if ($employeeId > 0) {
            log_message('warning', 'POS availability queue ignored unresolved auth user employee_id=' . $employeeId . ' for auth_user=' . $actorUserId);
        }
        return null;
    }

    protected function employeeExists(int $employeeId): bool
    {
        if ($employeeId <= 0) {
            return false;
        }
        if (array_key_exists($employeeId, $this->employeeExistsCache)) {
            return $this->employeeExistsCache[$employeeId];
        }
        if (!$this->CI->db->table_exists('org_employee')) {
            $this->employeeExistsCache[$employeeId] = false;
            return false;
        }

        $exists = (int)$this->CI->db
            ->from('org_employee')
            ->where('id', $employeeId)
            ->count_all_results() > 0;
        $this->employeeExistsCache[$employeeId] = $exists;
        return $exists;
    }

    protected function encodeJson(array $payload): ?string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    protected function decodeJson(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function formatJob(array $row): array
    {
        if (empty($row)) {
            return [];
        }
        $row['id'] = (int)($row['id'] ?? 0);
        $row['outlet_id'] = (int)($row['outlet_id'] ?? 0);
        $row['product_id'] = (int)($row['product_id'] ?? 0);
        $row['revision'] = (int)($row['revision'] ?? 0);
        $row['event_count'] = (int)($row['event_count'] ?? 0);
        $row['attempts'] = (int)($row['attempts'] ?? 0);
        $row['max_attempts'] = (int)($row['max_attempts'] ?? 0);
        $row['result'] = $this->decodeJson((string)($row['result_json'] ?? ''));
        unset($row['result_json']);
        return $row;
    }
}
