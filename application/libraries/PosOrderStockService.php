<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosOrderStockService
{
    /** @var CI_Controller */
    private $ci;
    /** @var array<int, string>|null */
    private $commitLineMovementRefEnumValues = null;
    /** @var array<int, string>|null */
    private $commitLineCostSourceEnumValues = null;
    /** @var array<string, bool> */
    private $commitLineColumnExistsCache = [];

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->ci->load->library('InventoryLedger');
        $this->ci->load->library('PosStockCommitService');
    }

    public function post_commit_snapshot(int $commitId, array $meta = []): array
    {
        if ($commitId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak valid.'];
        }

        $snapshot = $this->load_snapshot($commitId);
        if (!$snapshot['header']) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak ditemukan.'];
        }

        $db = $this->ci->db;
        $origDbDebug = $db->db_debug;
        $db->db_debug = false;
        $db->trans_begin();
        try {
            // Keep the stock writer serialized with void/refund. A terminal
            // order must never receive a late stock post from a queued job.
            $context = $this->commit_order_context_for_update($commitId);
            if (!$context) {
                throw new RuntimeException('Snapshot stock commit tidak ditemukan saat akan diposting.');
            }
            $terminalReason = $this->terminal_commit_order_reason($context);
            if ($terminalReason !== '') {
                throw new RuntimeException($terminalReason);
            }
            $this->lock_commit_lines_for_update($commitId);
            $snapshot = $this->load_snapshot($commitId);
            if (!$snapshot['header']) {
                throw new RuntimeException('Snapshot stock commit tidak ditemukan saat akan diposting.');
            }
            $periodLock = $this->prelock_snapshot_periods($snapshot['header'], $snapshot['lines'], [], false);
            if (!($periodLock['ok'] ?? false)) {
                throw new RuntimeException((string)($periodLock['message'] ?? 'Periode stok POS gagal dikunci.'));
            }

            $posted = 0;
            $skipped = 0;
            foreach ($snapshot['lines'] as $line) {
                // A partial void/refund may finish before its queued stock job.
                // Post only the quantity that remains active in the snapshot.
                $remainingQty = round(
                    max(0, (float)($line['committed_qty'] ?? $line['required_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0)),
                    4
                );
                if ($remainingQty <= 0.0001) {
                    $skipped++;
                    continue;
                }
                $line['committed_qty'] = $remainingQty;
                $line['required_qty'] = $remainingQty;

                $movementRefType = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
                $movementRefId = (int)($line['movement_ref_id'] ?? 0);
                if (($movementRefType !== '' && $movementRefType !== 'NONE') || $movementRefId > 0) {
                    $skipped++;
                    continue;
                }

                $result = strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT'
                    ? $this->post_component_usage($snapshot['header'], $line, $meta)
                    : $this->post_material_usage($snapshot['header'], $line, $meta);

                if (!($result['ok'] ?? false)) {
                    throw new RuntimeException((string)($result['message'] ?? 'Gagal posting stok order POS.'));
                }
                if ($db->trans_status() === false) {
                    $dbErr = $db->error();
                    throw new RuntimeException('Posting stok order POS gagal: ' . (string)($dbErr['message'] ?? 'unknown DB error'));
                }

                $updated = $db->where('id', (int)$line['id'])->update('pos_stock_commit_line', [
                    'movement_ref_type' => $this->normalize_commit_line_movement_ref_type_for_storage((string)($result['movement_ref_type'] ?? 'NONE')),
                    'movement_ref_id' => !empty($result['movement_ref_id']) ? (int)$result['movement_ref_id'] : null,
                    'unit_cost_live' => round((float)($result['unit_cost_live'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                    'total_cost_live' => round((float)($result['total_cost_live'] ?? ($line['total_cost_live'] ?? 0)), 6),
                    'cost_source' => $this->normalize_commit_line_cost_source_for_storage(
                        (string)($result['cost_source'] ?? ($line['cost_source'] ?? 'STANDARD_FALLBACK'))
                    ),
                    'notes' => $this->merge_note((string)($line['notes'] ?? ''), (string)($result['notes'] ?? '')),
                ]);
                if ($updated === false || $db->trans_status() === false) {
                    $dbErr = $db->error();
                    throw new RuntimeException('Update snapshot line stock commit POS gagal: ' . (string)($dbErr['message'] ?? 'unknown DB error'));
                }
                $posted++;
            }

            if ($db->trans_status() === false) {
                $dbErr = $db->error();
                throw new RuntimeException('Gagal menyimpan posting stok order POS: ' . (string)($dbErr['message'] ?? 'unknown DB error'));
            }
            $db->trans_commit();
            $db->db_debug = $origDbDebug;

            return ['ok' => true, 'posted_lines' => $posted, 'skipped_lines' => $skipped];
        } catch (Throwable $e) {
            $db->trans_rollback();
            $db->db_debug = $origDbDebug;
            return ['ok' => false, 'message' => $this->formatThrowableMessage($e)];
        }
    }

    private function commit_order_context_for_update(int $commitId): ?array
    {
        $db = $this->ci->db;
        $seed = $db->select('order_id')->from('pos_stock_commit')->where('id', $commitId)->limit(1)->get()->row_array();
        if (!$seed) {
            return null;
        }

        $order = null;
        $orderId = (int)($seed['order_id'] ?? 0);
        if ($orderId > 0) {
            $order = $db->query(
                'SELECT id, status, stock_commit_status FROM pos_order WHERE id = ? FOR UPDATE',
                [$orderId]
            )->row_array();
        }
        $commit = $db->query(
            'SELECT id, commit_status, commit_no FROM pos_stock_commit WHERE id = ? FOR UPDATE',
            [$commitId]
        )->row_array();
        if (!$commit) {
            return null;
        }

        return [
            'order_status' => (string)($order['status'] ?? ''),
            'order_stock_commit_status' => (string)($order['stock_commit_status'] ?? ''),
            'commit_status' => (string)($commit['commit_status'] ?? ''),
        ];
    }

    private function terminal_commit_order_reason(array $context): string
    {
        $orderStatus = strtoupper(trim((string)($context['order_status'] ?? '')));
        if (in_array($orderStatus, ['VOID', 'REFUND_FULL', 'REFUNDED_FULL'], true)) {
            return 'Order POS sudah ' . $orderStatus . '; stok tidak boleh diposting ulang.';
        }

        $orderCommitStatus = strtoupper(trim((string)($context['order_stock_commit_status'] ?? '')));
        if ($orderCommitStatus === 'REVERSED') {
            return 'Stock commit order sudah direversal; stok tidak boleh diposting ulang.';
        }

        $commitStatus = strtoupper(trim((string)($context['commit_status'] ?? '')));
        if (in_array($commitStatus, ['REVERSED', 'VOID'], true)) {
            return 'Snapshot stock commit sudah ' . $commitStatus . '; stok tidak boleh diposting ulang.';
        }

        return '';
    }

    public function reverse_commit_snapshot(int $commitId, array $lineDecisions, array $meta = []): array
    {
        if ($commitId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak valid untuk reversal.'];
        }

        if (empty($lineDecisions)) {
            return ['ok' => false, 'message' => 'Tidak ada line reversal yang dikirim.'];
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            // Serialize with post_commit_snapshot using order, commit, lines.
            if (!$this->commit_order_context_for_update($commitId)) {
                throw new RuntimeException('Snapshot stock commit tidak ditemukan.');
            }
            $this->lock_commit_lines_for_update($commitId);
            $snapshot = $this->load_snapshot($commitId);
            if (!$snapshot['header']) {
                throw new RuntimeException('Snapshot stock commit tidak ditemukan.');
            }
            $prepared = PosStockCommitService::prepare_reversal_decisions($snapshot['lines'], $lineDecisions);
            if (!($prepared['ok'] ?? false)) {
                throw new RuntimeException((string)($prepared['message'] ?? 'Keputusan reversal tidak valid.'));
            }
            $periodLock = $this->prelock_snapshot_periods($snapshot['header'], (array)$prepared['decisions'], $meta, true);
            if (!($periodLock['ok'] ?? false)) {
                throw new RuntimeException((string)($periodLock['message'] ?? 'Periode reversal stok POS gagal dikunci.'));
            }

            $appliedDecisions = [];
            $physicalReturns = 0;
            $returnWarnings = [];
            $materialAdjustmentGroups = [];
            $componentAdjustmentGroups = [];

            foreach ((array)$prepared['decisions'] as $decision) {
                $line = (array)$decision['line'];
                $reverseQty = (float)$decision['reverse_qty'];
                $policy = (string)$decision['return_policy'];
                $returnWarning = '';
                if (in_array($policy, ['RETURN_TO_STOCK', 'ADJUSTMENT_ONLY'], true)) {
                    $result = strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT'
                        ? $this->reverse_component_usage($snapshot['header'], $line, $reverseQty, $meta)
                        : $this->reverse_material_usage($snapshot['header'], $line, $reverseQty, $meta);
                    if (!($result['ok'] ?? false)) {
                        throw new RuntimeException((string)($result['message'] ?? 'Gagal mengembalikan stok order POS.'));
                    }
                    $returnedQty = array_key_exists('returned_qty', $result)
                        ? round((float)$result['returned_qty'], 4)
                        : $reverseQty;
                    if ($policy === 'RETURN_TO_STOCK' && $returnedQty > 0.0001) {
                        $physicalReturns++;
                    } elseif ($policy === 'ADJUSTMENT_ONLY') {
                        // A skipped stock return is not an adjustment. It only
                        // means the old POS line has no provable stock-out to
                        // reverse, so never manufacture a waste/adjustment row.
                        if (strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT') {
                            $this->collect_component_adjustment_only_line($componentAdjustmentGroups, $snapshot['header'], $line, $reverseQty, $meta);
                        } else {
                            $this->collect_material_adjustment_only_line($materialAdjustmentGroups, $snapshot['header'], $line, $reverseQty, $meta);
                        }
                    }
                    $returnWarning = trim((string)($result['warning'] ?? ''));
                    if ($returnWarning !== '') {
                        $returnWarnings[] = $returnWarning;
                    }
                }

                // PosStockCommitService stores decisions by PRODUCT/EXTRA
                // keys, while this service indexes its runtime snapshot by
                // commit_line:{id}. Pass the persisted key when finalising
                // the reversal so returned stock and snapshot quantities stay
                // in lockstep.
                $appliedDecisions[] = [
                    'line_key' => (string)$decision['line_key'],
                    'return_policy' => $policy,
                    'reverse_qty' => $reverseQty,
                    'notes' => trim(implode(' | ', array_filter([
                        trim((string)($decision['notes'] ?? '')),
                        $returnWarning ?? '',
                    ]))),
                ];
            }

            $adjustmentPosting = $this->post_adjustment_only_documents($materialAdjustmentGroups, $componentAdjustmentGroups, $meta);
            if (!($adjustmentPosting['ok'] ?? false)) {
                throw new RuntimeException((string)($adjustmentPosting['message'] ?? 'Gagal memposting adjustment dari reversal POS.'));
            }

            $apply = $this->ci->posstockcommitservice->apply_reversal_plan($commitId, $appliedDecisions, [
                'notes' => (string)($meta['notes'] ?? ''),
            ]);
            if (!($apply['ok'] ?? false)) {
                throw new RuntimeException((string)($apply['message'] ?? 'Gagal memfinalkan reversal snapshot POS.'));
            }

            if (!empty($snapshot['header']['order_id'])) {
                $orderUpdate = [
                    'stock_reversed_at' => date('Y-m-d H:i:s'),
                ];
                if ((string)($apply['commit_status'] ?? '') === 'REVERSED') {
                    $orderUpdate['stock_commit_status'] = 'REVERSED';
                }
                $db->where('id', (int)$snapshot['header']['order_id'])->update('pos_order', $orderUpdate);
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan reversal stok POS.');
            }
            $db->trans_commit();

            return [
                'ok' => true,
                'physical_return_count' => $physicalReturns,
                'commit_status' => (string)($apply['commit_status'] ?? ''),
                'affected_lines' => (int)($apply['affected_lines'] ?? 0),
                'adjustment_doc_count' => (int)($adjustmentPosting['adjustment_doc_count'] ?? 0),
                'return_warnings' => array_values(array_unique($returnWarnings)),
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function audit_cross_division_commit_snapshot(int $commitId, array $filters = []): array
    {
        if ($commitId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak valid.'];
        }

        $snapshot = $this->load_snapshot($commitId);
        if (!$snapshot['header']) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak ditemukan.'];
        }

        $filterLineId = (int)($filters['line_id'] ?? 0);
        $mismatches = [];
        $all = [];

        foreach ((array)$snapshot['lines'] as $line) {
            if ($filterLineId > 0 && (int)($line['id'] ?? 0) !== $filterLineId) {
                continue;
            }

            $audit = $this->build_commit_line_scope_audit($snapshot['header'], $line);
            $all[] = $audit;
            if (!empty($audit['is_mismatch'])) {
                $mismatches[] = $audit;
            }
        }

        return [
            'ok' => true,
            'header' => $snapshot['header'],
            'lines' => $all,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * Read-only link between material/lot mismatch and POS void movements.
     * It uses the already calculated reconcile rows so this audit never
     * rebuilds stock and never changes a document.
     */
    public function audit_void_material_lot_drift(string $asOfDate, array $materialCompareRows = [], int $limit = 50): array
    {
        $asOfDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate) ? $asOfDate : date('Y-m-d');
        $limit = max(1, min(200, $limit));
        if (empty($materialCompareRows)
            || !$this->ci->db->table_exists('inv_stock_movement_log')
            || !$this->ci->db->table_exists('pos_stock_commit')) {
            return ['ok' => true, 'summary' => ['count' => 0], 'rows' => []];
        }

        $monthStart = date('Y-m-01', strtotime($asOfDate));
        $destinationGroupExpr = "CASE WHEN m.destination_type IN ('BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT') THEN 'EVENT' ELSE 'REGULER' END";
        $sourceQuery = $this->ci->db
            ->select(
                "m.division_id,
                 ({$destinationGroupExpr}) AS destination_group,
                 m.material_id,
                 MAX(m.movement_date) AS last_reversal_date,
                 COUNT(DISTINCT m.ref_id) AS commit_count,
                 ROUND(SUM(COALESCE(m.qty_content_delta, 0)), 4) AS reversal_qty,
                 GROUP_CONCAT(DISTINCT CONCAT(COALESCE(c.commit_no, CONCAT('#', c.id)), ' [', COALESCE(c.commit_status, '-'), ']') ORDER BY c.id SEPARATOR ', ') AS commit_refs",
                false
            )
            ->from('inv_stock_movement_log m')
            ->join('pos_stock_commit c', 'c.id = m.ref_id', 'inner')
            ->where('m.ref_table', 'pos_stock_commit_reversal')
            ->where('m.movement_scope', 'DIVISION')
            ->where('m.movement_type', 'VOID_REVERSE')
            ->where('m.movement_date >=', $monthStart)
            ->where('m.movement_date <=', $asOfDate)
            ->where('m.qty_content_delta >', 0.0001, false)
            ->where('m.material_id IS NOT NULL', null, false)
            ->where_in('c.commit_status', ['VOID', 'FAILED'])
            ->like('m.notes', 'POS return to stock aggregate reversal.', 'after')
            ->group_by("m.division_id, {$destinationGroupExpr}, m.material_id", false)
            ->get();
        if ($sourceQuery === false) {
            $error = $this->ci->db->error();
            log_message('error', 'POS void lot audit query failed: ' . (string)($error['message'] ?? 'unknown database error'));
            return [
                'ok' => false,
                'message' => 'Audit Void POS belum dapat membaca jejak movement. Periksa log server.',
                'summary' => ['count' => 0],
                'rows' => [],
            ];
        }

        $sourceMap = [];
        foreach ($sourceQuery->result_array() as $source) {
            $key = (int)($source['division_id'] ?? 0)
                . '|' . strtoupper((string)($source['destination_group'] ?? 'REGULER'))
                . '|M-' . (int)($source['material_id'] ?? 0);
            $sourceMap[$key] = $source;
        }

        $rows = [];
        foreach ($materialCompareRows as $row) {
            $hasLotMismatch = !empty($row['has_lot_mismatch'])
                || !empty($row['has_lot_value_mismatch'])
                || !empty($row['has_profile_lot_value_mismatch']);
            if (!$hasLotMismatch) {
                continue;
            }

            $key = (int)($row['division_id'] ?? 0)
                . '|' . strtoupper((string)($row['destination_group'] ?? 'REGULER'))
                . '|M-' . (int)($row['material_id'] ?? 0);
            if (!isset($sourceMap[$key])) {
                continue;
            }

            $source = $sourceMap[$key];
            $rows[] = [
                'division_id' => (int)($row['division_id'] ?? 0),
                'division_name' => (string)($row['division_name'] ?? '-'),
                'destination_group' => (string)($row['destination_group'] ?? 'REGULER'),
                'material_id' => (int)($row['material_id'] ?? 0),
                'material_name' => (string)($row['material_name'] ?? '-'),
                'stock_qty' => round((float)($row['balance_qty_content'] ?? 0), 4),
                'lot_qty' => round((float)($row['lot_qty_content'] ?? 0), 4),
                'qty_delta' => round((float)($row['balance_qty_content'] ?? 0) - (float)($row['lot_qty_content'] ?? 0), 4),
                'stock_value' => round((float)($row['stock_value_total'] ?? 0), 2),
                'lot_value' => round((float)($row['lot_value_total'] ?? 0), 2),
                'value_delta' => round((float)($row['lot_vs_balance_value_delta'] ?? 0), 2),
                'last_reversal_date' => (string)($source['last_reversal_date'] ?? ''),
                'commit_count' => (int)($source['commit_count'] ?? 0),
                'reversal_qty' => round((float)($source['reversal_qty'] ?? 0), 4),
                'commit_refs' => (string)($source['commit_refs'] ?? '-'),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $leftMagnitude = abs((float)($left['value_delta'] ?? 0)) + abs((float)($left['qty_delta'] ?? 0));
            $rightMagnitude = abs((float)($right['value_delta'] ?? 0)) + abs((float)($right['qty_delta'] ?? 0));
            return $rightMagnitude <=> $leftMagnitude;
        });

        return [
            'ok' => true,
            'summary' => ['count' => count($rows)],
            'rows' => array_slice($rows, 0, $limit),
        ];
    }

    public function repair_cross_division_commit_snapshot(int $commitId, array $options = []): array
    {
        if ($commitId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak valid.'];
        }

        $snapshot = $this->load_snapshot($commitId);
        if (!$snapshot['header']) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak ditemukan.'];
        }

        $filterLineId = (int)($options['line_id'] ?? 0);
        $dryRun = !empty($options['dry_run']);
        $actorEmployeeId = (int)($options['actor_employee_id'] ?? 0);
        $repairNote = trim((string)($options['note'] ?? 'Repair cross-division POS commit line'));

        $targets = [];
        foreach ((array)$snapshot['lines'] as $line) {
            if ($filterLineId > 0 && (int)($line['id'] ?? 0) !== $filterLineId) {
                continue;
            }

            $audit = $this->build_commit_line_scope_audit($snapshot['header'], $line);
            if (empty($audit['is_mismatch'])) {
                continue;
            }
            $targets[] = $audit;
        }

        if (empty($targets)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada line commit lintas divisi yang perlu direpair.',
                'processed' => 0,
                'dry_run' => $dryRun,
            ];
        }

        if ($dryRun) {
            return [
                'ok' => true,
                'message' => 'Dry run only.',
                'processed' => count($targets),
                'dry_run' => true,
                'targets' => $targets,
            ];
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            $results = [];
            foreach ($targets as $audit) {
                $line = (array)($audit['line'] ?? []);
                $lineId = (int)($line['id'] ?? 0);
                $remainingQty = round((float)($line['committed_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0), 4);
                if ($remainingQty <= 0) {
                    $results[] = [
                        'line_id' => $lineId,
                        'ok' => false,
                        'message' => 'Line sudah fully reversed, tidak bisa direpair otomatis.',
                    ];
                    continue;
                }

                $reverseMeta = $options;
                $reverseMeta['actor_employee_id'] = $actorEmployeeId > 0 ? $actorEmployeeId : (int)($snapshot['header']['actor_employee_id'] ?? 0);
                $reverseMeta['notes'] = $repairNote . ' | rollback wrong division';

                $reverse = strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT'
                    ? $this->reverse_component_usage($snapshot['header'], $line, $remainingQty, $reverseMeta)
                    : $this->reverse_material_usage($snapshot['header'], $line, $remainingQty, $reverseMeta);
                if (!($reverse['ok'] ?? false)) {
                    throw new RuntimeException('Rollback line #' . $lineId . ' gagal: ' . (string)($reverse['message'] ?? 'unknown error'));
                }

                $correctedLine = $line;
                $correctedLine['resolved_source_division_id'] = $audit['expected_division_id'] ?: null;
                $correctedLine['resolved_source_division_code'] = $audit['expected_division_code'] ?: null;
                $correctedLine['resolved_source_division_name'] = $audit['expected_division_name'] ?: null;
                $correctedLine['movement_ref_type'] = 'NONE';
                $correctedLine['movement_ref_id'] = null;

                $postMeta = $options;
                $postMeta['actor_employee_id'] = $actorEmployeeId > 0 ? $actorEmployeeId : (int)($snapshot['header']['actor_employee_id'] ?? 0);
                $postMeta['notes'] = $repairNote . ' | repost to expected division';

                $repost = strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT'
                    ? $this->post_component_usage($snapshot['header'], $correctedLine, $postMeta)
                    : $this->post_material_usage($snapshot['header'], $correctedLine, $postMeta);
                if (!($repost['ok'] ?? false)) {
                    throw new RuntimeException('Repost line #' . $lineId . ' gagal: ' . (string)($repost['message'] ?? 'unknown error'));
                }

                $update = [
                    'movement_ref_type' => $this->normalize_commit_line_movement_ref_type_for_storage((string)($repost['movement_ref_type'] ?? 'NONE')),
                    'movement_ref_id' => !empty($repost['movement_ref_id']) ? (int)$repost['movement_ref_id'] : null,
                    'unit_cost_live' => round((float)($repost['unit_cost_live'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                    'total_cost_live' => round((float)($repost['total_cost_live'] ?? ($line['total_cost_live'] ?? 0)), 6),
                    'cost_source' => $this->normalize_commit_line_cost_source_for_storage(
                        (string)($repost['cost_source'] ?? ($line['cost_source'] ?? 'STANDARD_FALLBACK'))
                    ),
                    'notes' => $this->merge_note((string)($line['notes'] ?? ''), $repairNote . ' | ' . (string)($repost['notes'] ?? 'reposted to expected division')),
                ];
                if ($this->commit_line_has_column('resolved_source_division_id')) {
                    $update['resolved_source_division_id'] = $audit['expected_division_id'] ?: null;
                }
                if ($this->commit_line_has_column('resolved_source_division_code')) {
                    $update['resolved_source_division_code'] = $audit['expected_division_code'] ?: null;
                }
                if ($this->commit_line_has_column('resolved_source_division_name')) {
                    $update['resolved_source_division_name'] = $audit['expected_division_name'] ?: null;
                }

                $db->where('id', $lineId)->update('pos_stock_commit_line', $update);
                if ($db->affected_rows() < 0) {
                    throw new RuntimeException('Update commit line #' . $lineId . ' gagal.');
                }

                $results[] = [
                    'line_id' => $lineId,
                    'ok' => true,
                    'movement_ref_type' => $update['movement_ref_type'],
                    'movement_ref_id' => $update['movement_ref_id'],
                    'expected_division_id' => $audit['expected_division_id'],
                    'actual_before_division_id' => $audit['actual_division_id'],
                ];
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Repair cross-division commit snapshot gagal disimpan.');
            }
            $db->trans_commit();

            $successCount = 0;
            foreach ($results as $row) {
                if (!empty($row['ok'])) {
                    $successCount++;
                }
            }

            return [
                'ok' => true,
                'message' => 'Repair cross-division commit snapshot selesai.',
                'processed' => count($results),
                'success' => $successCount,
                'failed' => count($results) - $successCount,
                'results' => $results,
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function post_material_usage(array $header, array $line, array $meta): array
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $divisionId = $scope['division_id'];
        $destinationType = $scope['destination_type'];
        $movementDate = $this->resolve_commit_movement_date($header);
        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'MATERIAL',
                $movementDate,
                null,
                'Automatic material period from POS commit'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        $requiredQty = round((float)($line['committed_qty'] ?? $line['required_qty'] ?? 0), 4);
        if ($requiredQty <= 0) {
            return ['ok' => false, 'message' => 'Qty material commit tidak valid.'];
        }

        // Resolve once per source line. The same identity is used by FIFO,
        // a possible deficit record, and the fallback ledger path.
        $materialIdentity = $this->infer_material_identity($line, $divisionId, $destinationType);
        $fifoAttempted = false;
        $fifoError = '';
        if ($divisionId > 0 && $destinationType !== 'OTHER' && file_exists(APPPATH . 'libraries/MaterialFifoManager.php')) {
            $this->ci->load->library('MaterialFifoManager');
            $fifoAttempted = true;
            $fifo = $this->ci->materialfifomanager->consumeDivisionUsage([
                'issue_date' => $movementDate,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $materialIdentity['item_id'] ?? null,
                'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
                'qty_content_out' => $requiredQty,
                'source_module' => 'POS',
                'force_cross_profile_fifo' => true,
                'source_table' => 'pos_stock_commit',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
                'notes' => 'POS order commit ' . (string)($header['commit_no'] ?? ''),
                'allow_partial_issue' => true,
            ]);
            if ($fifo['ok'] ?? false) {
                $issueData = $this->load_material_issue((int)($fifo['data']['issue_id'] ?? 0));
                foreach ((array)($issueData['lines'] ?? []) as $issueLine) {
                    $snapshot = $this->build_material_snapshot_from_issue($issueData['header'], $issueLine, $line, $divisionId, $destinationType);
                    $issueHeaderId = !empty($issueData['header']['id']) ? (int)$issueData['header']['id'] : 0;
                    $issueLineId = !empty($issueLine['id']) ? (int)$issueLine['id'] : 0;
                    $lotLabel = (string)($snapshot['profile_name'] ?? ($issueLine['source_lot_no'] ?? '-'));
                    $post = $this->ci->inventoryledger->post($snapshot + [
                        'movement_scope' => 'DIVISION',
                        'movement_date' => $movementDate,
                        'movement_type' => 'USAGE_OUT',
                        'division_id' => $divisionId,
                        'destination_type' => $destinationType,
                        'ref_table' => 'pos_stock_commit',
                        'ref_id' => (int)($header['id'] ?? 0),
                        'qty_buy_delta' => -1 * (float)$snapshot['qty_buy_delta_abs'],
                        'qty_content_delta' => -1 * (float)$snapshot['qty_content_delta_abs'],
                        'unit_cost' => round((float)($issueLine['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                        'force_avg_cost_per_content' => round((float)($issueLine['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                        'allow_negative_balance' => true,
                        'notes' => 'POS usage FIFO issue#' . $issueHeaderId . ' line#' . $issueLineId . ' lot ' . $lotLabel,
                        'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
                        'manage_transaction' => false,
                        // The runtime job rebuilds all affected products once
                        // after this order is committed. Do not rebuild per lot.
                        'skip_availability_refresh' => true,
                    ]);
                    if (!($post['ok'] ?? false)) {
                        return $post;
                    }
                }

                $issuedQty = round((float)($fifo['data']['issued_qty'] ?? $requiredQty), 4);
                $deficitQty = round(max(0, $requiredQty - $issuedQty), 4);
                $deficitId = 0;
                if ($deficitQty > 0.0001) {
                    // If FIFO had stock, its source-lot profile is stronger
                    // evidence than a generic catalog preference. This keeps
                    // a partial POS shortage attached to the stock profile
                    // from which the order was actually issued.
                    $identity = $this->apply_fifo_allocation_identity(
                        $materialIdentity,
                        (array)($fifo['data']['allocations'] ?? [])
                    );
                    $this->ci->load->library('InventoryDeficitService');
                    $deficit = $this->ci->inventorydeficitservice->record([
                        'stock_domain' => 'MATERIAL',
                        'deficit_date' => $movementDate,
                        'location_scope' => 'DIVISION',
                        'division_id' => $divisionId,
                        'destination_type' => $destinationType,
                        'item_id' => $identity['item_id'] ?? null,
                        'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                        'buy_uom_id' => $identity['buy_uom_id'] ?? null,
                        'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
                        'profile_key' => $identity['profile_key'] ?? null,
                        'requested_qty' => $requiredQty,
                        'issued_qty' => $issuedQty,
                        'estimated_unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
                        'source_module' => 'POS',
                        'source_table' => 'pos_stock_commit',
                        'source_id' => (int)($header['id'] ?? 0),
                        'source_line_id' => (int)($line['id'] ?? 0),
                        'notes' => 'POS material melebihi lot FIFO tersedia.',
                    ]);
                    if (!($deficit['ok'] ?? false)) {
                        return $deficit;
                    }
                    $deficitId = (int)($deficit['id'] ?? 0);
                }

                // The POS HPP remains complete even when only part of the
                // request can leave a FIFO lot. The issued part uses its
                // FIFO cost; the shortfall uses the live cost captured when
                // the POS commit snapshot was created.
                $fifoTotalCost = round((float)($fifo['data']['total_cost'] ?? 0), 6);
                $provisionalDeficitCost = round($deficitQty * (float)($line['unit_cost_live'] ?? 0), 6);
                $fullTotalCost = round($fifoTotalCost + $provisionalDeficitCost, 6);
                $effectiveUnitCost = $requiredQty > 0.0001
                    ? round($fullTotalCost / $requiredQty, 6)
                    : round((float)($line['unit_cost_live'] ?? 0), 6);

                return [
                    'ok' => true,
                    'movement_ref_type' => 'FIFO_ISSUE',
                    'movement_ref_id' => (int)($fifo['data']['issue_id'] ?? 0),
                    'unit_cost_live' => $effectiveUnitCost,
                    'total_cost_live' => $fullTotalCost,
                    'cost_source' => $deficitId > 0 ? 'DEFICIT_PENDING' : 'FIFO',
                    'notes' => $deficitId > 0
                        ? ('Stock material diposting via FIFO; defisit stok #' . $deficitId . ' dicatat untuk qty ' . number_format($deficitQty, 4, '.', '') . '.')
                        : 'Stock material diposting via FIFO usage divisi.',
                ];
            }
            $fifoError = (string)($fifo['message'] ?? 'FIFO usage gagal.');
        }

        if ($destinationType === 'OTHER') {
            return ['ok' => true, 'movement_ref_type' => 'SKIPPED', 'movement_ref_id' => 0,
                'unit_cost_live' => 0.0, 'total_cost_live' => 0.0, 'cost_source' => 'MANUAL',
                'notes' => 'Divisi destination OTHER dilewati untuk konsumsi bahan baku.'];
        }

        $identity = $materialIdentity;
        if (file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            $this->ci->load->library('InventoryDeficitService');
            if ($this->ci->inventorydeficitservice->isReady()) {
                $deficit = $this->ci->inventorydeficitservice->record([
                    'stock_domain' => 'MATERIAL',
                    'deficit_date' => $movementDate,
                    'location_scope' => 'DIVISION',
                    'division_id' => $divisionId,
                    'destination_type' => $destinationType,
                    'item_id' => $identity['item_id'] ?? null,
                    'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                    'buy_uom_id' => $identity['buy_uom_id'] ?? null,
                    'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
                    'profile_key' => $identity['profile_key'] ?? null,
                    'requested_qty' => $requiredQty,
                    'issued_qty' => 0,
                    'estimated_unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
                    'source_module' => 'POS',
                    'source_table' => 'pos_stock_commit',
                    'source_id' => (int)($header['id'] ?? 0),
                    'source_line_id' => (int)($line['id'] ?? 0),
                    'notes' => $fifoAttempted
                        ? ('POS material tidak memiliki lot FIFO yang dapat dipakai. ' . $fifoError)
                        : 'POS material tidak dapat menjalankan FIFO; kebutuhan dicatat sebagai defisit tanpa membuat stok minus.',
                ]);
                if (!($deficit['ok'] ?? false)) {
                    return $deficit;
                }
                return [
                    'ok' => true,
                    'movement_ref_type' => 'INVENTORY_DEFICIT',
                    'movement_ref_id' => (int)($deficit['id'] ?? 0),
                    'unit_cost_live' => round((float)($line['unit_cost_live'] ?? 0), 6),
                    'total_cost_live' => round($requiredQty * (float)($line['unit_cost_live'] ?? 0), 6),
                    'cost_source' => 'DEFICIT_PENDING',
                    'notes' => 'POS tetap diposting; kebutuhan bahan baku dicatat sebagai defisit stok.',
                ];
            }
        }
        return [
            'ok' => false,
            'message' => 'POS tidak dapat mencatat defisit bahan baku karena fondasi Defisit Stok belum siap. Stok tidak dikurangi tanpa lot pasangan; periksa migration inventory lalu jalankan ulang antrean POS.',
        ];
    }

    private function reverse_material_usage(array $header, array $line, float $reverseQty, array $meta): array
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $divisionId = $scope['division_id'];
        $destinationType = $scope['destination_type'];
        // Keep void/refund in the period in which the reversal actually
        // happens; the original commit remains untouched in its own period.
        $movementDate = $this->resolve_reversal_movement_date($meta);
        $movementRefType = $this->resolve_material_movement_ref_type($header, $line);

        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'MATERIAL',
                $movementDate,
                null,
                'Automatic material period from POS void/refund'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        // A partial refund reverses only its outstanding deficit, never all
        // shortages that were recorded for the original POS line.
        if (file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            $this->ci->load->library('InventoryDeficitService');
            $deficitReverse = $this->ci->inventorydeficitservice->reverseBySourceQty(
                'pos_stock_commit',
                (int)($header['id'] ?? 0),
                (int)($line['id'] ?? 0),
                $reverseQty,
                !empty($meta['actor_user_id']) ? (int)$meta['actor_user_id'] : null,
                (string)($meta['notes'] ?? 'Void/refund POS'),
                [
                    'document_type' => (string)($meta['document_type'] ?? 'POS_REVERSE'),
                    'document_id' => (int)($meta['document_id'] ?? 0),
                    'document_no' => (string)($meta['document_no'] ?? ''),
                ]
            );
            if (!($deficitReverse['ok'] ?? false)) {
                return $deficitReverse;
            }
            $reverseQty = round(max(0, $reverseQty - (float)($deficitReverse['reversed_qty'] ?? 0)), 4);
            if ($reverseQty <= 0.0001) {
                return [
                    'ok' => true,
                    'returned_qty' => 0.0,
                    'notes' => 'Retur menutup defisit POS; tidak ada lot yang perlu dikembalikan.',
                ];
            }
        }

        if ($movementRefType === 'FIFO_ISSUE' && file_exists(APPPATH . 'libraries/MaterialFifoManager.php')) {
            $issueData = $this->load_material_issue((int)($line['movement_ref_id'] ?? 0));
            $this->ci->load->library('MaterialFifoManager');
            $rollback = $this->ci->materialfifomanager->rollbackDivisionUsageLotsBySource(
                'pos_stock_commit',
                (int)($header['id'] ?? 0),
                (int)($line['id'] ?? 0),
                (string)($meta['notes'] ?? 'Void/refund POS'),
                $reverseQty
            );
            if (!($rollback['ok'] ?? false)) {
                return $rollback;
            }

            $rolledQty = round((float)($rollback['data']['rolled_qty'] ?? 0), 4);
            if ($rolledQty <= 0.0001) {
                return $this->skip_unlinked_material_return(
                    $header,
                    $line,
                    $reverseQty,
                    'Jejak lot pemakaian tidak ditemukan. Sistem tidak menambah stok tanpa lot pasangan.'
                );
            }

            $movementRollback = $this->apply_material_fifo_usage_rollback_to_movements(
                $header,
                $line,
                $issueData,
                $divisionId,
                $destinationType,
                (array)($rollback['data']['allocations'] ?? []),
                $meta,
                $movementDate
            );
            if (!($movementRollback['ok'] ?? false)) {
                if ($this->is_missing_rollback_movement_message((string)($movementRollback['message'] ?? ''))) {
                    return $this->post_material_rollback_fallback(
                        $header,
                        $line,
                        $rolledQty,
                        $meta,
                        'FIFO rollback fallback: movement usage lama tidak ditemukan.'
                    );
                }
                return $movementRollback;
            }

            $rebuild = $this->rebuild_material_histories_after_pos_rollback($issueData, $line, $divisionId, $destinationType, $movementDate);
            if (!($rebuild['ok'] ?? false)) {
                return $rebuild;
            }

            return ['ok' => true, 'returned_qty' => $rolledQty];
        }

        if ($movementRefType === 'LEDGER_MOVEMENT' && !empty($line['movement_ref_id'])) {
            $sourceMovement = $this->load_material_ledger_movement_for_reversal(
                (int)($line['movement_ref_id'] ?? 0),
                $header,
                $line,
                $divisionId,
                $destinationType
            );
            if (!$sourceMovement) {
                return $this->skip_unlinked_material_return(
                    $header,
                    $line,
                    $reverseQty,
                    'Jejak movement pemakaian tidak cocok dengan snapshot POS. Sistem tidak menambah stok tanpa lot pasangan.'
                );
            }

            $identity = $this->infer_material_identity($line, $divisionId, $destinationType);
            $rollback = $this->rollback_material_aggregate_movement(
                (int)($line['movement_ref_id'] ?? 0),
                $reverseQty,
                [
                    'movement_date' => $movementDate,
                    'reversal_ref_table' => 'pos_stock_commit_reversal',
                    'reversal_ref_id' => (int)($header['id'] ?? 0),
                    'notes' => (string)($meta['notes'] ?? 'Void/refund POS'),
                    'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
                    'skip_availability_refresh' => true,
                ]
            );
            if (!($rollback['ok'] ?? false)) {
                if ($this->is_missing_rollback_movement_message((string)($rollback['message'] ?? ''))) {
                    return $this->skip_unlinked_material_return(
                        $header,
                        $line,
                        $reverseQty,
                        'Movement pemakaian lama tidak ditemukan. Sistem tidak menambah stok tanpa lot pasangan.'
                    );
                }
                return $rollback;
            }

            $rolledQty = round((float)($rollback['data']['qty_content_reversed'] ?? 0), 4);
            if ($rolledQty <= 0.0001) {
                return $this->skip_unlinked_material_return(
                    $header,
                    $line,
                    $reverseQty,
                    'Movement pemakaian sudah pernah dibalik atau tidak memiliki kuantitas yang dapat dikembalikan.'
                );
            }

            $lotRestore = $this->restore_material_lot_after_ledger_rollback(
                $header,
                $line,
                $sourceMovement,
                $rolledQty,
                $meta,
                $movementDate,
                $divisionId,
                $destinationType
            );
            if (!($lotRestore['ok'] ?? false)) {
                return $lotRestore;
            }

            $rebuild = $this->rebuild_material_identity_after_pos_rollback([
                'item_id' => $sourceMovement['item_id'] ?? ($identity['item_id'] ?? null),
                'material_id' => $sourceMovement['material_id'] ?? (!empty($line['material_id']) ? (int)$line['material_id'] : null),
                'buy_uom_id' => $sourceMovement['buy_uom_id'] ?? ($identity['buy_uom_id'] ?? null),
                'content_uom_id' => $sourceMovement['content_uom_id'] ?? (!empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null),
                'profile_key' => $sourceMovement['profile_key'] ?? ($identity['profile_key'] ?? null),
                'division_id' => !empty($sourceMovement['division_id']) ? (int)$sourceMovement['division_id'] : $divisionId,
                'destination_type' => (string)($sourceMovement['destination_type'] ?? $destinationType),
            ], $movementDate);
            if (!($rebuild['ok'] ?? false)) {
                return $rebuild;
            }
            return ['ok' => true, 'returned_qty' => $rolledQty];
        }

        return $this->skip_unlinked_material_return(
            $header,
            $line,
            $reverseQty,
            'Snapshot tidak memiliki jejak lot, movement, atau defisit yang dapat dibuktikan. Sistem tidak menambah stok tanpa lot pasangan.'
        );
    }

    private function post_component_usage(array $header, array $line, array $meta): array
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $locationType = $this->resolve_component_location_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $divisionId = $scope['division_id'];
        $movementDate = $this->resolve_commit_movement_date($header);
        $requiredQty = round((float)($line['committed_qty'] ?? $line['required_qty'] ?? 0), 4);
        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $movementDate,
                null,
                'Automatic component period from POS commit'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }
        if ($locationType === null || $requiredQty <= 0) {
            return ['ok' => false, 'message' => 'Lokasi/qty komponen tidak valid untuk posting POS.'];
        }

        // Division guard: only commit component usage when the resolved location matches
        // the component's home division. If recipe lookup returned null (no explicit mapping),
        // the fallback might have resolved to the wrong division — skip to prevent phantom stock.
        if ($scope['recipe_division'] === null) {
            $componentId = (int)($line['component_id'] ?? 0);
            if ($componentId > 0 && $this->ci->db->table_exists('mst_component')) {
                $homeRow = $this->ci->db
                    ->select('c.operational_division_id, d.code AS division_code')
                    ->from('mst_component c')
                    ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
                    ->where('c.id', $componentId)
                    ->limit(1)->get()->row_array();
                $homeCode = strtoupper(trim((string)($homeRow['division_code'] ?? '')));
                $resolvedGroup = strpos($locationType, 'KITCHEN') !== false ? 'KITCHEN'
                    : (strpos($locationType, 'ROASTERY') !== false ? 'ROASTERY'
                    : (strpos($locationType, 'BAR') !== false ? 'BAR' : ''));
                $homeGroup = in_array($homeCode, ['KITCHEN', 'FOOD'], true) ? 'KITCHEN'
                    : (in_array($homeCode, ['ROASTERY', 'ROASTER'], true) ? 'ROASTERY'
                    : (in_array($homeCode, ['BAR', 'BEVERAGE'], true) ? 'BAR' : ''));
                if ($homeGroup !== '' && $resolvedGroup !== '' && $homeGroup !== $resolvedGroup) {
                    return [
                        'ok' => true,
                        'movement_ref_type' => 'SKIPPED',
                        'movement_ref_id' => 0,
                        'unit_cost_live' => 0.0,
                        'total_cost_live' => 0.0,
                        'cost_source' => 'MANUAL',
                        'notes' => 'Dilewati: komponen #' . $componentId . ' milik divisi ' . $homeGroup . ' tapi resolve ke ' . $resolvedGroup . '. Tidak ada recipe override.',
                    ];
                }
            }
        }

        $lotIssueId = null;
        $issuedQty = 0.0;
        $issuedCost = 0.0;
        $lotError = '';
        if (file_exists(APPPATH . 'libraries/ComponentLotManager.php')) {
            $this->ci->load->library('ComponentLotManager');
            $lot = $this->ci->componentlotmanager->consumeUsage([
                'issue_date' => $movementDate,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => (int)($line['component_id'] ?? 0),
                'uom_id' => (int)($line['required_uom_id'] ?? 0),
                'qty_out' => $requiredQty,
                'source_module' => 'POS',
                'source_table' => 'pos_stock_commit',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
                'notes' => 'POS component usage',
                'allow_partial_issue' => true,
            ]);
            if ($lot['ok'] ?? false) {
                $lotIssueId = (int)($lot['data']['issue_id'] ?? 0);
                $issuedQty = round((float)($lot['data']['issued_qty'] ?? $requiredQty), 4);
                $issuedCost = round((float)($lot['data']['total_cost'] ?? 0), 2);
            } else {
                $lotError = (string)($lot['message'] ?? 'Lot component usage gagal.');
            }
        }

        $deficitQty = round(max(0, $requiredQty - $issuedQty), 4);
        $deficitId = 0;
        $deficitReady = false;
        if (file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            $this->ci->load->library('InventoryDeficitService');
            $deficitReady = $this->ci->inventorydeficitservice->isReady();
        }
        if ($deficitReady && $deficitQty > 0.0001) {
            $deficit = $this->ci->inventorydeficitservice->record([
                'stock_domain' => 'COMPONENT',
                'deficit_date' => $movementDate,
                'location_scope' => $locationType,
                'division_id' => $divisionId,
                'component_id' => (int)($line['component_id'] ?? 0),
                'content_uom_id' => (int)($line['required_uom_id'] ?? 0),
                'requested_qty' => $requiredQty,
                'issued_qty' => $issuedQty,
                'estimated_unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
                'source_module' => 'POS',
                'source_table' => 'pos_stock_commit',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
                'notes' => 'POS component melebihi lot tersedia. ' . $lotError,
            ]);
            if (!($deficit['ok'] ?? false)) {
                return $deficit;
            }
            $deficitId = (int)($deficit['id'] ?? 0);
        }

        if ($deficitReady && $issuedQty <= 0.0001) {
            return [
                'ok' => true,
                'movement_ref_type' => 'INVENTORY_DEFICIT',
                'movement_ref_id' => $deficitId,
                'unit_cost_live' => round((float)($line['unit_cost_live'] ?? 0), 6),
                'total_cost_live' => round($requiredQty * (float)($line['unit_cost_live'] ?? 0), 6),
                'cost_source' => 'DEFICIT_PENDING',
                'notes' => 'POS tetap diposting; kebutuhan component dicatat sebagai defisit stok.',
            ];
        }

        if (!$deficitReady && $deficitQty > 0.0001) {
            return [
                'ok' => false,
                'message' => 'POS tidak dapat mencatat defisit component karena fondasi Defisit Stok belum siap. Stok tidak dikurangi tanpa lot pasangan; periksa migration inventory lalu jalankan ulang antrean POS.',
            ];
        }

        $movementQty = $issuedQty;
        // A single recipe snapshot can span several differently priced lots.
        // Only the unfulfilled quantity keeps the provisional snapshot cost.
        $issuedUnitCost = $issuedQty > 0.0001 ? round($issuedCost / $issuedQty, 6) : 0.0;
        $fullCost = round($issuedCost + $deficitQty * (float)($line['unit_cost_live'] ?? 0), 6);
        $movement = $this->post_component_aggregate_movement([
            'movement_date' => $movementDate,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => (int)($line['component_id'] ?? 0),
            'uom_id' => (int)($line['required_uom_id'] ?? 0),
            'movement_type' => 'USAGE',
            'qty' => $movementQty,
            'unit_cost' => $issuedUnitCost,
            'total_cost' => $issuedCost,
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit',
            'source_id' => (int)($header['id'] ?? 0),
            'source_line_id' => (int)($line['id'] ?? 0),
            'notes' => $deficitId > 0
                ? ('POS component usage parsial; defisit stok #' . $deficitId . ' dicatat untuk qty ' . number_format($deficitQty, 4, '.', '') . '.')
                : ($lotError !== '' ? ('Lot fallback: ' . $lotError) : 'POS component usage posted.'),
            'actor_employee_id' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0,
            // A component movement may only follow a lot issue that was
            // actually recorded. A shortage must become a deficit instead.
            'allow_negative' => false,
        ]);
        if (!($movement['ok'] ?? false)) {
            return $movement;
        }

        return [
            'ok' => true,
            'movement_ref_type' => $lotIssueId ? 'COMPONENT_LOT_ISSUE' : 'COMPONENT_MOVEMENT',
            'movement_ref_id' => $lotIssueId ?: (int)($movement['movement_id'] ?? 0),
            'unit_cost_live' => $requiredQty > 0.0001 ? round($fullCost / $requiredQty, 6) : 0.0,
            // The component lot may only supply a part of the recipe. The
            // remaining requirement is still valued provisionally so the
            // POS HPP represents the complete product sold.
            'total_cost_live' => $fullCost,
            'cost_source' => $deficitId > 0 ? 'DEFICIT_PENDING' : (string)($line['cost_source'] ?? 'LAST_LIVE'),
            'notes' => $deficitId > 0
                ? ('Stock component diposting sebagian; kekurangan dicatat sebagai defisit stok #' . $deficitId . '.')
                : 'Stock komponen diposting dari lot issue yang tercatat.',
        ];
    }

    private function reverse_component_usage(array $header, array $line, float $reverseQty, array $meta): array
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $locationType = $this->resolve_component_location_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $divisionId = $scope['division_id'];
        $movementRefType = $this->resolve_component_movement_ref_type($header, $line);
        // A reversal is a new stock event. Posting it on the original order
        // date after month rollover would bypass the carried-forward opening.
        $movementDate = $this->resolve_reversal_movement_date($meta);

        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $movementDate,
                null,
                'Automatic component period from POS void/refund'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        if (file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            $this->ci->load->library('InventoryDeficitService');
            $deficitReverse = $this->ci->inventorydeficitservice->reverseBySourceQty(
                'pos_stock_commit',
                (int)($header['id'] ?? 0),
                (int)($line['id'] ?? 0),
                $reverseQty,
                !empty($meta['actor_user_id']) ? (int)$meta['actor_user_id'] : null,
                (string)($meta['notes'] ?? 'Void/refund POS'),
                [
                    'document_type' => (string)($meta['document_type'] ?? 'POS_REVERSE'),
                    'document_id' => (int)($meta['document_id'] ?? 0),
                    'document_no' => (string)($meta['document_no'] ?? ''),
                ]
            );
            if (!($deficitReverse['ok'] ?? false)) {
                return $deficitReverse;
            }
            $reverseQty = round(max(0, $reverseQty - (float)($deficitReverse['reversed_qty'] ?? 0)), 4);
            if ($reverseQty <= 0.0001) {
                return [
                    'ok' => true,
                    'returned_qty' => 0.0,
                    'notes' => 'Retur menutup defisit POS; tidak ada lot component yang perlu dikembalikan.',
                ];
            }
        }

        if ($movementRefType === 'COMPONENT_LOT_ISSUE') {
            // A component lot issue is sufficient evidence for a physical
            // return, but never create a synthetic lot when its source is
            // missing or incomplete. That was the path that produced phantom
            // stock for failed/void POS snapshots.
            if (!$this->is_component_lot_issue_reference($header, $line)) {
                return $this->skip_unlinked_component_return(
                    $header,
                    $line,
                    $reverseQty,
                    'Jejak lot pemakaian component tidak cocok dengan snapshot POS. Sistem tidak menambah stok tanpa lot pasangan.'
                );
            }

            $lotRollback = $this->rollback_component_issue_lots_only($header, $line, $reverseQty, $meta);
            if (!($lotRollback['ok'] ?? false)) {
                return $lotRollback;
            }

            $rolledQty = round((float)($lotRollback['data']['rolled_qty'] ?? 0), 4);
            if ($rolledQty <= 0.0001) {
                return $this->skip_unlinked_component_return(
                    $header,
                    $line,
                    $reverseQty,
                    'Jejak lot pemakaian component tidak memiliki kuantitas yang dapat dikembalikan.'
                );
            }

            $movementRollback = $this->rollback_component_usage_movement(
                (int)($header['id'] ?? 0),
                (int)($line['id'] ?? 0),
                $rolledQty,
                0,
                [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['required_uom_id'] ?? 0),
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'movement_date' => $movementDate,
                    'reversal_source_table' => 'pos_stock_commit_reversal',
                    'returned_cost' => (float)($lotRollback['data']['rolled_cost'] ?? 0),
                    'reversal_source_id' => (int)($header['id'] ?? 0),
                    'notes' => (string)($meta['notes'] ?? 'Void/refund POS'),
                    'actor_employee_id' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0,
                ]
            );
            if (!($movementRollback['ok'] ?? false)) {
                if ($this->is_missing_rollback_movement_message((string)($movementRollback['message'] ?? ''))) {
                    // The lot was proven and safely returned. Do not post a
                    // new aggregate ledger return when the old usage ledger is
                    // absent, because that would create stock from nothing.
                    return [
                        'ok' => true,
                        'returned_qty' => $rolledQty,
                        'warning' => 'Lot component dikembalikan, tetapi movement usage lama tidak ditemukan sehingga tidak dibuat movement reversal sintetis.',
                    ];
                }
                return $movementRollback;
            }

            $rebuild = $this->rebuild_component_history_after_pos_rollback($line, $locationType, $divisionId);
            if (!($rebuild['ok'] ?? false)) {
                return $rebuild;
            }

            return ['ok' => true, 'returned_qty' => $rolledQty];
        }

        if ($movementRefType === 'COMPONENT_MOVEMENT') {
            $evidence = $this->find_component_usage_movement_for_reversal(
                (int)($header['id'] ?? 0),
                (int)($line['id'] ?? 0),
                (int)($line['movement_ref_id'] ?? 0),
                [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['required_uom_id'] ?? 0),
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                ]
            );
            if (!($evidence['ok'] ?? false)) {
                return $this->skip_unlinked_component_return(
                    $header,
                    $line,
                    $reverseQty,
                    (string)($evidence['message'] ?? 'Movement usage component tidak dapat dibuktikan dari snapshot POS.')
                );
            }

            // A proven historical aggregate usage can legitimately need a
            // fallback lot, because older data may predate component FIFO.
            $lotRollback = $this->rollback_or_restore_component_lots($header, $line, $reverseQty, $meta, $movementDate, $locationType, $divisionId);
            if (!($lotRollback['ok'] ?? false)) {
                return $lotRollback;
            }

            $movementRollback = $this->rollback_component_usage_movement(
                (int)($header['id'] ?? 0),
                (int)($line['id'] ?? 0),
                $reverseQty,
                (int)($evidence['row']['id'] ?? 0),
                [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['required_uom_id'] ?? 0),
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'movement_date' => $movementDate,
                    'reversal_source_table' => 'pos_stock_commit_reversal',
                    'reversal_source_id' => (int)($header['id'] ?? 0),
                    'notes' => (string)($meta['notes'] ?? 'Void/refund POS'),
                    'actor_employee_id' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0,
                ]
            );
            if (!($movementRollback['ok'] ?? false)) {
                return $movementRollback;
            }

            $rebuild = $this->rebuild_component_history_after_pos_rollback($line, $locationType, $divisionId);
            if (!($rebuild['ok'] ?? false)) {
                return $rebuild;
            }

            return ['ok' => true, 'returned_qty' => $reverseQty];
        }

        return $this->skip_unlinked_component_return(
            $header,
            $line,
            $reverseQty,
            'Snapshot tidak memiliki jejak lot, movement, atau defisit component yang dapat dibuktikan. Sistem tidak menambah stok tanpa sumber pemakaian.'
        );
    }

    private function apply_material_fifo_usage_rollback_to_movements(array $header, array $line, array $issueData, int $divisionId, string $destinationType, array $allocations, array $meta, string $movementDate): array
    {
        if (!$this->ci->db->table_exists('inv_stock_movement_log') || empty($allocations)) {
            return ['ok' => true];
        }

        $issueLineMap = [];
        foreach ((array)($issueData['lines'] ?? []) as $issueLine) {
            $issueLineMap[(int)($issueLine['id'] ?? 0)] = $issueLine;
        }

        foreach ($allocations as $allocation) {
            $issueLineId = (int)($allocation['issue_line_id'] ?? 0);
            $issueLine = $issueLineMap[$issueLineId] ?? null;
            if (!$issueLine) {
                continue;
            }
            $snapshot = $this->build_material_snapshot_from_issue($issueData['header'] ?? null, $issueLine, $line, $divisionId, $destinationType);
            $usageAdjusted = $this->adjust_single_material_usage_movement_row([
                'header_id' => (int)($header['id'] ?? 0),
                'issue_line_id' => $issueLineId,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $snapshot['item_id'] ?? null,
                'material_id' => $snapshot['material_id'] ?? null,
                'buy_uom_id' => $snapshot['buy_uom_id'] ?? null,
                'content_uom_id' => $snapshot['content_uom_id'] ?? null,
                'profile_key' => $snapshot['profile_key'] ?? null,
                'qty_content_delta' => -1 * (float)($snapshot['qty_content_delta_abs'] ?? 0),
                'qty_rollback' => (float)($allocation['qty_rolled'] ?? 0),
                'notes_like' => 'POS usage FIFO lot %',
                'movement_date' => $movementDate,
                'reversal_ref_table' => 'pos_stock_commit_reversal',
                'reversal_ref_id' => (int)($header['id'] ?? 0),
                'notes' => (string)($meta['notes'] ?? 'Void/refund POS'),
                'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
            ]);
            if (!($usageAdjusted['ok'] ?? false)) {
                return ['ok' => false, 'message' => 'Movement log usage material POS tidak ditemukan saat rollback.'];
            }
        }

        return ['ok' => true];
    }

    private function adjust_single_material_usage_movement_row(array $ctx): array
    {
        $issueLineId = (int)($ctx['issue_line_id'] ?? 0);
        $headerId = (int)($ctx['header_id'] ?? 0);
        $divisionId = (int)($ctx['division_id'] ?? 0);
        $destinationType = (string)($ctx['destination_type'] ?? 'OTHER');
        $qtyContentDelta = round((float)($ctx['qty_content_delta'] ?? 0), 4);
        $qtyRollback = round((float)($ctx['qty_rollback'] ?? 0), 4);
        $notesLike = trim((string)($ctx['notes_like'] ?? ''));
        if ($qtyRollback <= 0) {
            return ['ok' => true];
        }

        $movementRow = null;
        if ($issueLineId > 0) {
            $movementRow = $this->ci->db
                ->from('inv_stock_movement_log')
                ->where('ref_table', 'pos_stock_commit')
                ->where('ref_id', $headerId)
                ->like('notes', 'line#' . $issueLineId)
                ->limit(1)
                ->get()
                ->row_array() ?: null;
        }

        if (!$movementRow) {
            $this->ci->db->from('inv_stock_movement_log')
                ->where('ref_table', 'pos_stock_commit')
                ->where('ref_id', $headerId)
                ->where('movement_scope', 'DIVISION')
                ->where('division_id', $divisionId)
                ->where('destination_type', $destinationType)
                ->where('content_uom_id', (int)($ctx['content_uom_id'] ?? 0))
                ->where('qty_content_delta', $qtyContentDelta);
            if (($ctx['item_id'] ?? null) === null) {
                $this->ci->db->where('item_id IS NULL', null, false);
            } else {
                $this->ci->db->where('item_id', (int)$ctx['item_id']);
            }
            if (($ctx['material_id'] ?? null) === null) {
                $this->ci->db->where('material_id IS NULL', null, false);
            } else {
                $this->ci->db->where('material_id', (int)$ctx['material_id']);
            }
            if (($ctx['buy_uom_id'] ?? null) === null) {
                $this->ci->db->where('buy_uom_id IS NULL', null, false);
            } else {
                $this->ci->db->where('buy_uom_id', (int)$ctx['buy_uom_id']);
            }
            if (($ctx['profile_key'] ?? null) === null || $ctx['profile_key'] === '') {
                $this->ci->db->where('profile_key IS NULL', null, false);
            } else {
                $this->ci->db->where('profile_key', (string)$ctx['profile_key']);
            }
            if ($notesLike !== '') {
                $this->ci->db->like('notes', str_replace('%', '', $notesLike));
            }
            $movementRow = $this->ci->db->order_by('id', 'ASC')->limit(1)->get()->row_array() ?: null;
        }

        if (!$movementRow) {
            return ['ok' => false, 'message' => 'Movement usage bahan baku tidak ditemukan.'];
        }

        $movementId = (int)($movementRow['id'] ?? 0);
        if ($movementId <= 0) {
            return ['ok' => false, 'message' => 'Movement usage bahan baku tidak valid untuk rollback.'];
        }

        $this->ci->load->library('InventoryMovementReversalService');
        $reverse = $this->ci->inventorymovementreversalservice->reverseMaterialMovementRow(
            $movementRow,
            [
                'qty_content' => $qtyRollback,
                'movement_date' => (string)($ctx['movement_date'] ?? date('Y-m-d')),
                'reversal_ref_table' => (string)($ctx['reversal_ref_table'] ?? 'pos_stock_commit_reversal'),
                'reversal_ref_id' => (int)($ctx['reversal_ref_id'] ?? $headerId),
                'notes' => (string)($ctx['notes'] ?? 'Void/refund POS'),
                'created_by' => !empty($ctx['created_by']) ? (int)$ctx['created_by'] : null,
                'manage_transaction' => false,
                'skip_availability_refresh' => true,
                'allow_negative_balance' => true,
            ]
        );
        if (!($reverse['ok'] ?? false)) {
            return $reverse;
        }

        return ['ok' => true, 'data' => (array)($reverse['data'] ?? ['movement_id' => $movementId])];
    }

    private function rollback_material_aggregate_movement(int $movementId, float $reverseQty, array $options = []): array
    {
        if ($movementId <= 0 || !$this->ci->db->table_exists('inv_stock_movement_log')) {
            return ['ok' => true];
        }

        $row = $this->ci->db->from('inv_stock_movement_log')->where('id', $movementId)->limit(1)->get()->row_array() ?: null;
        if (!$row) {
            return ['ok' => false, 'message' => 'Movement bahan baku tidak ditemukan untuk rollback.'];
        }

        $this->ci->load->library('InventoryMovementReversalService');
        return $this->ci->inventorymovementreversalservice->reverseMaterialMovementRow($row, $options + [
            'qty_content' => $reverseQty,
            'manage_transaction' => false,
            'allow_negative_balance' => true,
        ]);
    }

    /**
     * A ledger reference is valid for a POS reversal only when it points back
     * to the same snapshot. This prevents an old/broken snapshot from adding
     * stock by reversing an unrelated material movement.
     */
    private function load_material_ledger_movement_for_reversal(
        int $movementId,
        array $header,
        array $line,
        int $divisionId,
        string $destinationType
    ): ?array {
        if ($movementId <= 0 || !$this->ci->db->table_exists('inv_stock_movement_log')) {
            return null;
        }

        $query = $this->ci->db
            ->from('inv_stock_movement_log')
            ->where('id', $movementId)
            ->limit(1)
            ->get();
        if ($query === false) {
            $error = $this->ci->db->error();
            log_message('error', 'POS material reversal cannot read source movement #' . $movementId . ': ' . (string)($error['message'] ?? 'unknown database error'));
            return null;
        }

        $row = $query->row_array() ?: null;
        if (!$row) {
            return null;
        }

        $valid = strtolower(trim((string)($row['ref_table'] ?? ''))) === 'pos_stock_commit'
            && (int)($row['ref_id'] ?? 0) === (int)($header['id'] ?? 0)
            && strtoupper(trim((string)($row['movement_scope'] ?? ''))) === 'DIVISION'
            && (float)($row['qty_content_delta'] ?? 0) < -0.0001;

        if ($valid && $divisionId > 0) {
            $valid = (int)($row['division_id'] ?? 0) === $divisionId;
        }
        if ($valid && $destinationType !== '') {
            $valid = strtoupper(trim((string)($row['destination_type'] ?? ''))) === strtoupper(trim($destinationType));
        }

        $lineMaterialId = (int)($line['material_id'] ?? 0);
        if ($valid && $lineMaterialId > 0) {
            $valid = (int)($row['material_id'] ?? 0) === $lineMaterialId;
        }
        $lineUomId = (int)($line['required_uom_id'] ?? 0);
        if ($valid && $lineUomId > 0) {
            $valid = (int)($row['content_uom_id'] ?? 0) === $lineUomId;
        }

        if (!$valid) {
            log_message('error', 'POS material reversal skipped invalid ledger evidence: commit #' . (int)($header['id'] ?? 0) . ', line #' . (int)($line['id'] ?? 0) . ', movement #' . $movementId . '.');
            return null;
        }

        return $row;
    }

    /**
     * Old snapshots may have a ledger reference but no FIFO issue reference.
     * Reverse the existing FIFO issue first when it exists; any remaining
     * quantity receives a traceable return lot so stock and FIFO stay paired.
     */
    private function restore_material_lot_after_ledger_rollback(
        array $header,
        array $line,
        array $sourceMovement,
        float $qty,
        array $meta,
        string $movementDate,
        int $divisionId,
        string $destinationType
    ): array {
        if ($qty <= 0.0001) {
            return ['ok' => true, 'data' => ['rolled_qty' => 0.0, 'created_qty' => 0.0]];
        }

        if (!file_exists(APPPATH . 'libraries/MaterialFifoManager.php')) {
            return ['ok' => false, 'message' => 'Library FIFO bahan baku belum tersedia untuk mengembalikan lot POS.'];
        }

        $this->ci->load->library('MaterialFifoManager');
        $sourceCommitId = (int)($header['id'] ?? 0);
        $sourceLineId = (int)($line['id'] ?? 0);
        $fifoRollback = $this->ci->materialfifomanager->rollbackDivisionUsageLotsBySource(
            'pos_stock_commit',
            $sourceCommitId,
            $sourceLineId > 0 ? $sourceLineId : null,
            (string)($meta['notes'] ?? 'Void/refund POS'),
            $qty
        );
        if (!($fifoRollback['ok'] ?? false)) {
            return $fifoRollback;
        }

        $rolledQty = round((float)($fifoRollback['data']['rolled_qty'] ?? 0), 4);
        $remainingQty = round(max(0, $qty - $rolledQty), 4);
        if ($remainingQty <= 0.0001) {
            return ['ok' => true, 'data' => ['rolled_qty' => $rolledQty, 'created_qty' => 0.0]];
        }

        $itemId = !empty($sourceMovement['item_id']) ? (int)$sourceMovement['item_id'] : (!empty($line['item_id']) ? (int)$line['item_id'] : null);
        $materialId = !empty($sourceMovement['material_id']) ? (int)$sourceMovement['material_id'] : (!empty($line['material_id']) ? (int)$line['material_id'] : null);
        $contentUomId = !empty($sourceMovement['content_uom_id']) ? (int)$sourceMovement['content_uom_id'] : (!empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null);
        if (($itemId === null && $materialId === null) || $contentUomId === null) {
            return ['ok' => false, 'message' => 'Identitas bahan baku tidak lengkap untuk membuat lot pengembalian POS.'];
        }

        $sourceMovementId = (int)($sourceMovement['id'] ?? 0);
        $lotNo = substr(sprintf(
            'POSR-%s-M%s-L%s-R%s',
            date('Ymd', strtotime($movementDate)),
            max(0, $sourceCommitId),
            max(0, $sourceLineId),
            max(0, $sourceMovementId)
        ), 0, 80);

        $register = $this->ci->materialfifomanager->registerReceiptInboundLot([
            'location_scope' => 'DIVISION',
            'division_id' => !empty($sourceMovement['division_id']) ? (int)$sourceMovement['division_id'] : $divisionId,
            'destination_type' => (string)($sourceMovement['destination_type'] ?? $destinationType),
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => !empty($sourceMovement['buy_uom_id']) ? (int)$sourceMovement['buy_uom_id'] : null,
            'content_uom_id' => $contentUomId,
            'profile_key' => $sourceMovement['profile_key'] ?? null,
            'profile_expired_date' => $sourceMovement['profile_expired_date'] ?? null,
            'qty_content_in' => $remainingQty,
            'unit_cost' => round((float)($sourceMovement['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
            'lot_no' => $lotNo,
            'receipt_date' => $movementDate,
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit_reversal',
            'source_id' => $sourceCommitId > 0 ? $sourceCommitId : null,
            'source_line_id' => $sourceLineId > 0 ? $sourceLineId : null,
            'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
            'notes' => 'Fallback lot dari reversal POS untuk menjaga stok dan FIFO tetap seimbang.',
        ]);
        if (!($register['ok'] ?? false)) {
            return $register;
        }

        return [
            'ok' => true,
            'data' => [
                'rolled_qty' => $rolledQty,
                'created_qty' => $remainingQty,
                'lot_id' => (int)($register['data']['lot_id'] ?? 0),
            ],
        ];
    }

    private function skip_unlinked_material_return(array $header, array $line, float $qty, string $reason): array
    {
        $warning = 'Pengembalian stok bahan baku tidak diposting: ' . trim($reason);
        log_message(
            'error',
            'POS material reversal skipped without paired evidence | commit #' . (int)($header['id'] ?? 0)
            . ' | line #' . (int)($line['id'] ?? 0)
            . ' | qty ' . number_format(max(0, $qty), 4, '.', '')
            . ' | ' . $reason
        );

        return [
            'ok' => true,
            'returned_qty' => 0.0,
            'skipped_qty' => round(max(0, $qty), 4),
            'warning' => $warning,
        ];
    }

    private function skip_unlinked_component_return(array $header, array $line, float $qty, string $reason): array
    {
        $warning = 'Pengembalian stok component tidak diposting: ' . trim($reason);
        log_message(
            'error',
            'POS component reversal skipped without paired evidence | commit #' . (int)($header['id'] ?? 0)
            . ' | line #' . (int)($line['id'] ?? 0)
            . ' | qty ' . number_format(max(0, $qty), 4, '.', '')
            . ' | ' . $reason
        );

        return [
            'ok' => true,
            'returned_qty' => 0.0,
            'skipped_qty' => round(max(0, $qty), 4),
            'warning' => $warning,
        ];
    }

    /**
     * Roll back only a recorded component lot issue. Unlike the historical
     * fallback helper, this never manufactures a replacement lot.
     */
    private function rollback_component_issue_lots_only(array $header, array $line, float $qty, array $meta): array
    {
        if ($qty <= 0.0001 || !file_exists(APPPATH . 'libraries/ComponentLotManager.php')) {
            return ['ok' => true, 'data' => ['rolled_qty' => 0.0, 'remaining_qty' => $qty]];
        }

        $this->ci->load->library('ComponentLotManager');
        return $this->ci->componentlotmanager->rollbackIssueLotsBySource(
            'pos_stock_commit',
            (int)($header['id'] ?? 0),
            (int)($line['id'] ?? 0),
            (string)($meta['notes'] ?? 'Void/refund POS'),
            $qty,
            true
        );
    }

    /**
     * Resolve a component USAGE movement only when it can be tied to this
     * exact POS commit line. Legacy rows without source_line_id are accepted
     * solely when their component scope produces one unambiguous candidate.
     */
    private function find_component_usage_movement_for_reversal(int $commitId, int $commitLineId, int $movementId = 0, array $scope = []): array
    {
        if ($commitId <= 0 || $commitLineId <= 0 || !$this->ci->db->table_exists('inv_component_movement_log')) {
            return ['ok' => false, 'message' => 'Movement usage component tidak tersedia untuk rollback.'];
        }

        $matchesScope = static function (array $row) use ($commitId, $commitLineId, $scope): bool {
            if (
                strtoupper(trim((string)($row['movement_type'] ?? ''))) !== 'USAGE'
                || strtolower(trim((string)($row['source_table'] ?? ''))) !== 'pos_stock_commit'
                || (int)($row['source_id'] ?? 0) !== $commitId
            ) {
                return false;
            }
            if (!empty($scope['component_id']) && (int)($row['component_id'] ?? 0) !== (int)$scope['component_id']) {
                return false;
            }
            if (!empty($scope['uom_id']) && (int)($row['uom_id'] ?? 0) !== (int)$scope['uom_id']) {
                return false;
            }
            if (!empty($scope['location_type']) && strtoupper((string)($row['location_type'] ?? '')) !== strtoupper((string)$scope['location_type'])) {
                return false;
            }
            if (array_key_exists('division_id', $scope)) {
                $expectedDivisionId = $scope['division_id'];
                $actualDivisionId = $row['division_id'] ?? null;
                if ($expectedDivisionId === null || $expectedDivisionId === '') {
                    if ($actualDivisionId !== null && $actualDivisionId !== '') {
                        return false;
                    }
                } elseif ((int)$actualDivisionId !== (int)$expectedDivisionId) {
                    return false;
                }
            }

            $sourceLineId = $row['source_line_id'] ?? null;
            return $sourceLineId === null || $sourceLineId === '' || (int)$sourceLineId === $commitLineId;
        };

        if ($movementId > 0) {
            $row = $this->ci->db->from('inv_component_movement_log')
                ->where('id', $movementId)
                ->limit(1)
                ->get()
                ->row_array() ?: null;
            if ($row && $matchesScope($row)) {
                return ['ok' => true, 'row' => $row, 'match' => 'movement_ref_id'];
            }
        }

        $row = $this->ci->db->from('inv_component_movement_log')
            ->where('source_table', 'pos_stock_commit')
            ->where('source_id', $commitId)
            ->where('source_line_id', $commitLineId)
            ->where('movement_type', 'USAGE')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
        if ($row && $matchesScope($row)) {
            return ['ok' => true, 'row' => $row, 'match' => 'source_line_id'];
        }

        $query = $this->ci->db->from('inv_component_movement_log')
            ->where('source_table', 'pos_stock_commit')
            ->where('source_id', $commitId)
            ->where('movement_type', 'USAGE')
            ->where('source_line_id IS NULL', null, false);
        if (!empty($scope['component_id'])) {
            $query->where('component_id', (int)$scope['component_id']);
        }
        if (!empty($scope['uom_id'])) {
            $query->where('uom_id', (int)$scope['uom_id']);
        }
        if (!empty($scope['location_type'])) {
            $query->where('location_type', (string)$scope['location_type']);
        }
        if (array_key_exists('division_id', $scope)) {
            if ($scope['division_id'] === null || $scope['division_id'] === '') {
                $query->where('division_id IS NULL', null, false);
            } else {
                $query->where('division_id', (int)$scope['division_id']);
            }
        }
        $rows = $query->order_by('id', 'DESC')->get()->result_array();
        if (count($rows) === 1 && $matchesScope($rows[0])) {
            return ['ok' => true, 'row' => $rows[0], 'match' => 'legacy_unambiguous'];
        }

        return [
            'ok' => false,
            'message' => empty($rows)
                ? 'Movement usage component tidak ditemukan untuk snapshot POS.'
                : 'Movement usage component tidak unik untuk snapshot POS; stok tidak dikembalikan otomatis.',
        ];
    }

    private function rollback_component_usage_movement(int $commitId, int $commitLineId, float $reverseQty, int $movementId = 0, array $fallbackContext = []): array
    {
        if (!$this->ci->db->table_exists('inv_component_movement_log')) {
            return ['ok' => true];
        }

        $evidence = $this->find_component_usage_movement_for_reversal(
            $commitId,
            $commitLineId,
            $movementId,
            $fallbackContext
        );
        if (!($evidence['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($evidence['message'] ?? 'Movement komponen tidak ditemukan untuk rollback.')];
        }
        $row = (array)($evidence['row'] ?? []);

        $movementRowId = (int)($row['id'] ?? 0);
        $currentQtyOut = round((float)($row['qty_out'] ?? 0), 4);
        if ($movementRowId <= 0 || $currentQtyOut <= 0.0001) {
            return ['ok' => false, 'message' => 'Movement komponen tidak valid untuk rollback.'];
        }

        $alreadyReversed = 0.0;
        if ($this->ci->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log')) {
            $reversalRow = $this->ci->db
                ->select('COALESCE(SUM(qty_in), 0) AS total_qty, COALESCE(SUM(total_cost), 0) AS total_cost', false)
                ->from('inv_component_movement_log')
                ->where('reversal_of_movement_id', $movementRowId)
                ->where('movement_type', 'VOID_REVERSE')
                ->get()
                ->row_array();
            $alreadyReversed = round((float)($reversalRow['total_qty'] ?? 0), 4);
        } else {
            return [
                'ok' => false,
                'message' => 'Schema reversal component belum siap. Jalankan migration 2026-08-22a_inventory_void_reversal_movement_link_foundation.sql terlebih dahulu.',
            ];
        }

        $availableRollback = round(max(0, $currentQtyOut - $alreadyReversed), 4);
        $effectiveRollback = round(min($availableRollback, max(0, $reverseQty)), 4);
        if ($effectiveRollback <= 0) {
            return ['ok' => true];
        }
        if (array_key_exists('returned_cost', $fallbackContext) && abs($effectiveRollback - $reverseQty) > 0.0001) {
            return ['ok' => false, 'message' => 'Jumlah lot retur tidak sama dengan sisa movement POS. Retur dibatalkan agar saldo tidak bertambah dua kali.'];
        }

        $unitCost = round((float)($row['unit_cost'] ?? 0), 6);
        $reverseCost = array_key_exists('returned_cost', $fallbackContext)
            ? round((float)$fallbackContext['returned_cost'], 2)
            : round((float)($row['total_cost'] ?? ($currentQtyOut * $unitCost)) * ($alreadyReversed + $effectiveRollback) / $currentQtyOut, 2)
                - round((float)($reversalRow['total_cost'] ?? 0), 2);
        $unitCost = round($reverseCost / $effectiveRollback, 6);
        $reverse = $this->post_component_aggregate_movement([
            'movement_date' => (string)($fallbackContext['movement_date'] ?? date('Y-m-d')),
            'location_type' => (string)($row['location_type'] ?? ($fallbackContext['location_type'] ?? '')),
            'division_id' => array_key_exists('division_id', $row) ? $row['division_id'] : ($fallbackContext['division_id'] ?? null),
            'component_id' => (int)($row['component_id'] ?? ($fallbackContext['component_id'] ?? 0)),
            'uom_id' => (int)($row['uom_id'] ?? ($fallbackContext['uom_id'] ?? 0)),
            'movement_type' => 'VOID_REVERSE',
            'qty' => $effectiveRollback,
            'unit_cost' => $unitCost,
            'total_cost' => $reverseCost,
            'source_module' => 'POS',
            'source_table' => (string)($fallbackContext['reversal_source_table'] ?? 'pos_stock_commit_reversal'),
            'source_id' => (int)($fallbackContext['reversal_source_id'] ?? $commitId),
            'source_line_id' => $commitLineId > 0 ? $commitLineId : null,
            'reversal_of_movement_id' => $movementRowId,
            'reversal_of_movement_type' => (string)($row['movement_type'] ?? 'USAGE'),
            'movement_value_override' => $reverseCost,
            'notes' => trim((string)($fallbackContext['notes'] ?? 'Void/refund POS')) . ' | reverse component movement #' . $movementRowId,
            'actor_employee_id' => !empty($fallbackContext['actor_employee_id']) ? (int)$fallbackContext['actor_employee_id'] : 0,
            'allow_negative' => true,
        ]);
        if (!($reverse['ok'] ?? false)) {
            return $reverse;
        }

        return ['ok' => true, 'data' => ['movement_id' => (int)($reverse['movement_id'] ?? 0)]];
    }

    private function rebuild_material_histories_after_pos_rollback(array $issueData, array $line, int $divisionId, string $destinationType, string $movementDate): array
    {
        $this->ci->load->model('Purchase_model');
        $startDate = date('Y-m-01', strtotime($movementDate));
        $identities = [];

        foreach ((array)($issueData['lines'] ?? []) as $issueLine) {
            $snapshot = $this->build_material_snapshot_from_issue($issueData['header'] ?? null, $issueLine, $line, $divisionId, $destinationType);
            $itemId = !empty($snapshot['item_id']) ? (int)$snapshot['item_id'] : 0;
            $contentUomId = !empty($snapshot['content_uom_id']) ? (int)$snapshot['content_uom_id'] : 0;
            if ($itemId <= 0 || $contentUomId <= 0) {
                continue;
            }

            $identity = [
                'item_id' => $itemId,
                'material_id' => !empty($snapshot['material_id']) ? (int)$snapshot['material_id'] : null,
                'buy_uom_id' => !empty($snapshot['buy_uom_id']) ? (int)$snapshot['buy_uom_id'] : null,
                'content_uom_id' => $contentUomId,
                'profile_key' => $snapshot['profile_key'] ?? null,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
            ];
            $identities[md5(json_encode($identity))] = $identity;
        }

        foreach (array_values($identities) as $identity) {
            $rebuild = $this->ci->Purchase_model->rebuild_inventory_history_for_identity('DIVISION', $startDate, $identity, [
                'allow_negative_closing' => true,
            ]);
            if (!($rebuild['ok'] ?? false)) {
                return $rebuild;
            }
        }

        return ['ok' => true];
    }

    private function rebuild_material_identity_after_pos_rollback(array $identity, string $movementDate): array
    {
        $itemId = (int)($identity['item_id'] ?? 0);
        $contentUomId = (int)($identity['content_uom_id'] ?? 0);
        if ($itemId <= 0 || $contentUomId <= 0) {
            return ['ok' => true];
        }

        $this->ci->load->model('Purchase_model');
        return $this->ci->Purchase_model->rebuild_inventory_history_for_identity('DIVISION', date('Y-m-01', strtotime($movementDate)), $identity, [
            'allow_negative_closing' => true,
        ]);
    }

    private function rebuild_component_history_after_pos_rollback(array $line, ?string $locationType, ?int $divisionId): array
    {
        if ($locationType === null) {
            return ['ok' => true];
        }

        $componentId = (int)($line['component_id'] ?? 0);
        $uomId = (int)($line['required_uom_id'] ?? 0);
        if ($componentId <= 0 || $uomId <= 0) {
            return ['ok' => true];
        }

        $this->ci->load->model('Production_model');
        return $this->ci->Production_model->rebuild_component_history_for_identity([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
        ]);
    }

    private function resolve_commit_movement_date(array $header): string
    {
        foreach (['committed_at', 'created_at', 'updated_at'] as $field) {
            $value = trim((string)($header[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }

        return date('Y-m-d');
    }

    private function resolve_reversal_movement_date(array $meta): string
    {
        foreach (['movement_date', 'reversal_date', 'document_date'] as $field) {
            $value = trim((string)($meta[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }

        return date('Y-m-d');
    }

    private function collect_material_adjustment_only_line(array &$groups, array $header, array $line, float $reverseQty, array $meta): void
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $divisionId = (int)($scope['division_id'] ?? 0);
        $destinationType = (string)($scope['destination_type'] ?? 'OTHER');
        if ($divisionId <= 0 || $destinationType === 'OTHER') {
            return;
        }

        $identity = $this->infer_material_identity($line, $divisionId, $destinationType);
        $key = $divisionId . '|' . $destinationType;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'header' => [
                    'adjustment_date' => date('Y-m-d'),
                    'stock_scope' => 'DIVISION',
                    'division_id' => $divisionId,
                    'destination_type' => $destinationType,
                    'notes' => $this->build_pos_adjustment_document_note($meta),
                ],
                'lines' => [],
            ];
        }

        $linePayload = [
            'item_id' => !empty($identity['item_id']) ? (int)$identity['item_id'] : null,
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'buy_uom_id' => !empty($identity['buy_uom_id']) ? (int)$identity['buy_uom_id'] : null,
            'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : (!empty($identity['content_uom_id']) ? (int)$identity['content_uom_id'] : null),
            'profile_key' => $identity['profile_key'] ?? null,
            'profile_name' => $identity['profile_name'] ?? ($line['profile_name'] ?? null),
            'profile_brand' => $identity['profile_brand'] ?? null,
            'profile_description' => $identity['profile_description'] ?? null,
            'profile_expired_date' => $identity['profile_expired_date'] ?? null,
            'profile_content_per_buy' => round((float)($identity['profile_content_per_buy'] ?? 1), 6),
            'profile_buy_uom_code' => $identity['profile_buy_uom_code'] ?? null,
            'profile_content_uom_code' => $identity['profile_content_uom_code'] ?? null,
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'note' => $this->build_pos_adjustment_line_note($meta, $line, $reverseQty),
        ];

        $this->apply_material_adjustment_mode_to_line($linePayload, $reverseQty, (string)($meta['adjustment_mode'] ?? 'AUTO_ADJUSTMENT'), (string)($meta['reason_code'] ?? ''));
        $groups[$key]['lines'][] = $linePayload;
    }

    private function collect_component_adjustment_only_line(array &$groups, array $header, array $line, float $reverseQty, array $meta): void
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $locationType = $this->resolve_component_location_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $divisionId = isset($scope['division_id']) ? (int)$scope['division_id'] : null;
        if ($locationType === null || empty($line['component_id']) || empty($line['required_uom_id'])) {
            return;
        }

        $key = $locationType . '|' . (string)($divisionId ?? 0);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'header' => [
                    'adjustment_date' => date('Y-m-d'),
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'notes' => $this->build_pos_adjustment_document_note($meta),
                ],
                'lines' => [],
            ];
        }

        $linePayload = [
            'component_id' => (int)$line['component_id'],
            'uom_id' => (int)$line['required_uom_id'],
            'selected_lot_id' => null,
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'note' => $this->build_pos_adjustment_line_note($meta, $line, $reverseQty),
        ];

        $this->apply_component_adjustment_mode_to_line($linePayload, $reverseQty, (string)($meta['adjustment_mode'] ?? 'AUTO_ADJUSTMENT'), (string)($meta['reason_code'] ?? ''));
        $groups[$key]['lines'][] = $linePayload;
    }

    private function post_adjustment_only_documents(array $materialGroups, array $componentGroups, array $meta): array
    {
        $adjustmentDocCount = 0;

        if (!empty($materialGroups)) {
            $this->ci->load->model('Purchase_model');
            foreach (array_values($materialGroups) as $group) {
                $group = $this->filter_pos_reversal_material_adjustment_group($group);
                if (empty($group['lines'])) {
                    continue;
                }
                $save = $this->ci->Purchase_model->save_stock_adjustment(
                    (array)($group['header'] ?? []),
                    (array)($group['lines'] ?? []),
                    0
                );
                if (!($save['ok'] ?? false)) {
                    return $save;
                }

                $post = $this->ci->Purchase_model->post_stock_adjustment((int)($save['id'] ?? 0), 0);
                if (!($post['ok'] ?? false)) {
                    return $post;
                }
                $adjustmentDocCount++;
            }
        }

        if (!empty($componentGroups)) {
            $this->ci->load->model('Production_model');
            $this->ci->load->library('ComponentStockWriter');
            $actorEmployeeId = !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0;

            foreach (array_values($componentGroups) as $group) {
                $group = $this->filter_pos_reversal_component_adjustment_group($group);
                if (empty($group['lines'])) {
                    continue;
                }
                $save = $this->ci->Production_model->save_component_adjustment(
                    (array)($group['header'] ?? []),
                    (array)($group['lines'] ?? []),
                    $actorEmployeeId
                );
                if (!($save['ok'] ?? false)) {
                    return $save;
                }

                $adjustmentId = (int)($save['id'] ?? 0);
                $header = $this->ci->Production_model->get_component_adjustment($adjustmentId);
                $lines = $this->ci->Production_model->get_component_adjustment_lines($adjustmentId);
                $post = $this->ci->componentstockwriter->post_adjustment((array)$header, (array)$lines, $actorEmployeeId);
                if (!($post['ok'] ?? false)) {
                    return $post;
                }

                $this->ci->db->where('id', $adjustmentId)->update('inv_component_adjustment', [
                    'status' => 'POSTED',
                    'posted_at' => date('Y-m-d H:i:s'),
                    'posted_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                ]);
                $adjustmentDocCount++;
            }
        }

        return ['ok' => true, 'adjustment_doc_count' => $adjustmentDocCount];
    }

    private function filter_pos_reversal_material_adjustment_group(array $group): array
    {
        $movementDate = (string)($group['header']['adjustment_date'] ?? date('Y-m-d'));
        $filteredLines = [];
        foreach ((array)($group['lines'] ?? []) as $line) {
            $requestedQty = round(
                max(
                    (float)($line['qty_waste_content'] ?? 0),
                    (float)($line['qty_spoil_content'] ?? 0),
                    (float)($line['qty_variance_content'] ?? 0)
                ),
                4
            );
            if ($requestedQty <= 0) {
                continue;
            }

            $balance = $this->load_material_balance_snapshot_for_adjustment_line($line, (array)($group['header'] ?? []), $movementDate);
            $currentQty = round((float)($balance['qty_on_hand'] ?? 0), 4);
            if ($currentQty < -0.0001 || $currentQty + 0.0001 < $requestedQty) {
                continue;
            }
            $filteredLines[] = $line;
        }
        $group['lines'] = $filteredLines;
        return $group;
    }

    private function filter_pos_reversal_component_adjustment_group(array $group): array
    {
        $header = (array)($group['header'] ?? []);
        $movementDate = (string)($header['adjustment_date'] ?? date('Y-m-d'));
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $filteredLines = [];

        foreach ((array)($group['lines'] ?? []) as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            $requestedQty = round(
                max(
                    (float)($line['qty_spoil'] ?? 0),
                    (float)($line['qty_waste'] ?? 0),
                    (float)($line['qty_adjust_neg'] ?? 0)
                ),
                4
            );
            if ($componentId <= 0 || $uomId <= 0 || $requestedQty <= 0) {
                continue;
            }

            $balance = $this->load_component_balance_snapshot($locationType, $divisionId, $componentId, $uomId, $movementDate);
            $currentQty = round((float)($balance['qty_on_hand'] ?? 0), 4);
            if ($currentQty < -0.0001 || $currentQty + 0.0001 < $requestedQty) {
                continue;
            }
            $filteredLines[] = $line;
        }

        $group['lines'] = $filteredLines;
        return $group;
    }

    private function apply_material_adjustment_mode_to_line(array &$linePayload, float $qtyContent, string $adjustmentMode, string $reasonCode): void
    {
        $normalizedMode = strtoupper(trim($adjustmentMode));
        if ($normalizedMode === 'AUTO_WASTE') {
            $linePayload['qty_waste_content'] = round($qtyContent, 4);
            $linePayload['waste_reason_code'] = $this->map_pos_reason_to_material_adjustment_reason('WASTE', $reasonCode);
            return;
        }
        if ($normalizedMode === 'AUTO_SPOIL') {
            $linePayload['qty_spoil_content'] = round($qtyContent, 4);
            $linePayload['spoil_reason_code'] = $this->map_pos_reason_to_material_adjustment_reason('SPOILAGE', $reasonCode);
            return;
        }

        $linePayload['qty_variance_content'] = round($qtyContent, 4);
        $linePayload['variance_reason_code'] = $this->map_pos_reason_to_material_adjustment_reason('VARIANCE', $reasonCode);
    }

    private function apply_component_adjustment_mode_to_line(array &$linePayload, float $qty, string $adjustmentMode, string $reasonCode): void
    {
        $normalizedMode = strtoupper(trim($adjustmentMode));
        if ($normalizedMode === 'AUTO_WASTE') {
            $linePayload['qty_waste'] = round($qty, 4);
            $linePayload['waste_reason_code'] = $this->map_pos_reason_to_component_adjustment_reason('WASTE', $reasonCode);
            return;
        }
        if ($normalizedMode === 'AUTO_SPOIL') {
            $linePayload['qty_spoil'] = round($qty, 4);
            $linePayload['spoil_reason_code'] = $this->map_pos_reason_to_component_adjustment_reason('SPOIL', $reasonCode);
            return;
        }

        $linePayload['qty_adjust_neg'] = round($qty, 4);
        $linePayload['adjustment_minus_reason_code'] = $this->map_pos_reason_to_component_adjustment_reason('ADJUSTMENT_MINUS', $reasonCode);
    }

    private function build_pos_adjustment_document_note(array $meta): string
    {
        $parts = [];
        $documentType = strtoupper(trim((string)($meta['document_type'] ?? 'POS')));
        $documentNo = trim((string)($meta['document_no'] ?? ''));
        if ($documentType !== '') {
            $parts[] = 'POS ' . $documentType . ($documentNo !== '' ? ' ' . $documentNo : '');
        }
        $reason = trim((string)($meta['reason'] ?? ''));
        if ($reason !== '') {
            $parts[] = 'tanpa return stok';
            $parts[] = $reason;
        } else {
            $parts[] = 'tanpa return stok';
        }

        return implode(' | ', $parts);
    }

    private function build_pos_adjustment_line_note(array $meta, array $line, float $qty): string
    {
        $parts = [];
        $documentType = strtoupper(trim((string)($meta['document_type'] ?? 'POS')));
        $documentNo = trim((string)($meta['document_no'] ?? ''));
        if ($documentType !== '') {
            $parts[] = 'Reversal ' . $documentType . ($documentNo !== '' ? ' ' . $documentNo : '');
        }
        $lineName = trim((string)($line['item_name_snapshot'] ?? $line['profile_name'] ?? $line['component_name_snapshot'] ?? ''));
        if ($lineName !== '') {
            $parts[] = $lineName;
        }
        $parts[] = 'qty ' . rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
        $reason = trim((string)($meta['reason'] ?? ''));
        if ($reason !== '') {
            $parts[] = $reason;
        }

        return implode(' | ', $parts);
    }

    private function map_pos_reason_to_material_adjustment_reason(string $category, string $reasonCode): string
    {
        $category = strtoupper(trim($category));
        $reasonCode = strtoupper(trim($reasonCode));

        if ($category === 'WASTE') {
            if (in_array($reasonCode, ['PERMINTAAN_CUSTOMER', 'SALAH_ITEM'], true)) {
                return 'cancel_order';
            }
            if (in_array($reasonCode, ['SALAH_INPUT', 'PRODUK_BERMASALAH'], true)) {
                return 'kitchen_error';
            }
            if (in_array($reasonCode, ['STOK_TIDAK_SIAP', 'KETERLAMBATAN_LAYANAN'], true)) {
                return 'overproduction';
            }
            return 'other';
        }

        if ($category === 'SPOILAGE') {
            if (in_array($reasonCode, ['PRODUK_BERMASALAH', 'KUALITAS_PRODUK', 'KELUHAN_RASA'], true)) {
                return 'contamination';
            }
            if ($reasonCode === 'STOK_TIDAK_SIAP') {
                return 'improper_storage';
            }
            return 'other';
        }

        if ($category === 'VARIANCE') {
            if ($reasonCode === 'SALAH_INPUT') {
                return 'counting_error';
            }
            if ($reasonCode === 'KOREKSI_SHIFT') {
                return 'system_mismatch';
            }
            if (in_array($reasonCode, ['STOK_TIDAK_SIAP', 'KETERLAMBATAN_LAYANAN'], true)) {
                return 'unrecorded_usage';
            }
            return 'other';
        }

        return 'other';
    }

    private function map_pos_reason_to_component_adjustment_reason(string $category, string $reasonCode): string
    {
        $category = strtoupper(trim($category));
        $reasonCode = strtoupper(trim($reasonCode));

        if ($category === 'WASTE') {
            if (in_array($reasonCode, ['PERMINTAAN_CUSTOMER', 'SALAH_ITEM'], true)) {
                return 'cancel_order';
            }
            if (in_array($reasonCode, ['SALAH_INPUT', 'PRODUK_BERMASALAH'], true)) {
                return 'kitchen_error';
            }
            if (in_array($reasonCode, ['STOK_TIDAK_SIAP', 'KETERLAMBATAN_LAYANAN'], true)) {
                return 'overproduction';
            }
            return 'other';
        }
        if ($category === 'ADJUSTMENT_MINUS') {
            if ($reasonCode === 'SALAH_INPUT') {
                return 'counting_error';
            }
            if ($reasonCode === 'KOREKSI_SHIFT') {
                return 'system_mismatch';
            }
            if (in_array($reasonCode, ['STOK_TIDAK_SIAP', 'KETERLAMBATAN_LAYANAN'], true)) {
                return 'unrecorded_usage';
            }
            return 'other';
        }
        if ($category === 'SPOIL' || $category === 'SPOILAGE') {
            if (in_array($reasonCode, ['PRODUK_BERMASALAH', 'KUALITAS_PRODUK', 'KELUHAN_RASA'], true)) {
                return 'contamination';
            }
            if ($reasonCode === 'STOK_TIDAK_SIAP') {
                return 'improper_storage';
            }
            return 'other';
        }

        return 'other';
    }

    private function post_component_aggregate_movement(array $p): array
    {
        $movementType = strtoupper((string)($p['movement_type'] ?? 'USAGE'));
        $qty = round((float)($p['qty'] ?? 0), 4);
        if ($qty <= 0) {
            return ['ok' => false, 'message' => 'Qty komponen tidak valid.'];
        }

        $isIn = in_array($movementType, ['OPENING', 'PRODUCTION_IN', 'TRANSFER_IN', 'ADJUSTMENT_PLUS', 'VOID_REVERSE'], true);
        $qtyIn = $isIn ? $qty : 0.0;
        $qtyOut = $isIn ? 0.0 : $qty;
        $unitCost = round((float)($p['unit_cost'] ?? 0), 6);
        $totalCost = round((float)($p['total_cost'] ?? ($qty * $unitCost)), 2);
        $allowNegative = !empty($p['allow_negative']);

        $row = $this->load_component_balance_snapshot(
            (string)$p['location_type'],
            isset($p['division_id']) ? (int)$p['division_id'] : null,
            (int)$p['component_id'],
            (int)$p['uom_id'],
            (string)$p['movement_date']
        );

        $qtyBefore = round((float)($row['qty_on_hand'] ?? 0), 4);
        $avgBefore = round((float)($row['avg_cost'] ?? 0), 6);
        $valueBefore = round((float)($row['total_value'] ?? ($qtyBefore * $avgBefore)), 2);
        $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
        if (!$allowNegative && $qtyAfter < -0.0001) {
            return ['ok' => false, 'message' => 'Stok komponen tidak cukup untuk movement ' . $movementType . '.'];
        }

        if ($qtyIn > 0) {
            $valueAfter = round($valueBefore + $totalCost, 2);
            $avgAfter = abs($qtyAfter) > 0.0001 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        } else {
            // FIFO has already priced the issued lots. Do not issue again at
            // the balance average, or erase a pre-existing value discrepancy.
            $valueAfter = round($valueBefore - $totalCost, 2);
            $avgAfter = abs($qtyAfter) > 0.0001 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        }

        $now = date('Y-m-d H:i:s');

        $movementNo = $this->generate_component_movement_no((string)$p['movement_date']);
        $movementData = [
            'movement_no' => $movementNo,
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $now,
            'location_type' => (string)$p['location_type'],
            'division_id' => isset($p['division_id']) ? (int)$p['division_id'] : null,
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_module' => (string)($p['source_module'] ?? 'POS'),
            'source_table' => (string)($p['source_table'] ?? 'pos_stock_commit'),
            'source_id' => !empty($p['source_id']) ? (int)$p['source_id'] : null,
            'source_line_id' => !empty($p['source_line_id']) ? (int)$p['source_line_id'] : null,
            'notes' => !empty($p['notes']) ? (string)$p['notes'] : null,
            'created_by' => !empty($p['actor_employee_id']) ? (int)$p['actor_employee_id'] : null,
            'created_at' => $now,
        ];
        if ($this->ci->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log')) {
            $movementData['reversal_of_movement_id'] = !empty($p['reversal_of_movement_id'])
                ? (int)$p['reversal_of_movement_id']
                : null;
        }
        $this->ci->db->insert('inv_component_movement_log', $movementData);
        $movementId = (int)$this->ci->db->insert_id();

        $this->sync_component_monthly_stock([
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $now,
            'location_type' => (string)$p['location_type'],
            'division_id' => isset($p['division_id']) ? (int)$p['division_id'] : null,
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'avg_after' => $avgAfter,
            'value_after' => $valueAfter,
            'source_table' => (string)($p['source_table'] ?? 'pos_stock_commit'),
            'source_id' => !empty($p['source_id']) ? (int)$p['source_id'] : null,
            'notes' => !empty($p['notes']) ? (string)$p['notes'] : null,
            'reversal_of_movement_type' => (string)($p['reversal_of_movement_type'] ?? ''),
            'movement_value_override' => array_key_exists('movement_value_override', $p)
                ? (float)$p['movement_value_override']
                : null,
        ]);

        return ['ok' => true, 'movement_id' => $movementId, 'movement_no' => $movementNo];
    }

    private function sync_component_monthly_stock(array $ctx): void
    {
        if (!$this->ci->db->table_exists('inv_component_monthly_stock')) {
            return;
        }

        $monthKey = date('Y-m-01', strtotime((string)$ctx['movement_date']));
        $row = $this->ci->db->query(
            'SELECT * FROM inv_component_monthly_stock WHERE month_key = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [
                $monthKey,
                (string)$ctx['location_type'],
                isset($ctx['division_id']) ? (int)$ctx['division_id'] : null,
                (int)$ctx['component_id'],
                (int)$ctx['uom_id'],
            ]
        )->row_array();

        $movementType = strtoupper((string)$ctx['movement_type']);
        if (array_key_exists('movement_value_override', $ctx) && $ctx['movement_value_override'] !== null) {
            $movementValue = round((float)$ctx['movement_value_override'], 2);
        } else {
            $movementTotalCost = $ctx['total_cost']
                ?? (((float)($ctx['qty_in'] ?? 0) > 0 ? (float)($ctx['qty_in'] ?? 0) : (float)($ctx['qty_out'] ?? 0)) * (float)($ctx['unit_cost'] ?? 0));
            $movementValue = round(abs((float)$movementTotalCost), 2);
        }
        $reversalOriginType = strtoupper(trim((string)($ctx['reversal_of_movement_type'] ?? '')));
        $movementDate = (string)$ctx['movement_date'];
        $movementDayCount = (int)($row['movement_day_count'] ?? 0);
        if ($row === null || (string)($row['last_movement_date'] ?? '') !== $movementDate) {
            $movementDayCount++;
        }

        $data = [
            'month_key' => $monthKey,
            'location_type' => (string)$ctx['location_type'],
            'division_id' => isset($ctx['division_id']) ? (int)$ctx['division_id'] : null,
            'component_id' => (int)$ctx['component_id'],
            'uom_id' => (int)$ctx['uom_id'],
            'opening_qty' => $movementType === 'OPENING'
                ? ($row ? round((float)($row['opening_qty'] ?? 0) + (float)($ctx['qty_in'] ?? 0), 4) : round((float)($ctx['qty_after'] ?? 0), 4))
                : ($row ? round((float)($row['opening_qty'] ?? 0), 4) : round((float)($ctx['qty_before'] ?? 0), 4)),
            'opening_total_value' => $row ? round((float)($row['opening_total_value'] ?? 0), 2)
                : round((float)$ctx['value_after'] - ((float)$ctx['qty_in'] > 0 ? $movementValue : -$movementValue), 2),
            'in_qty' => $row ? round((float)($row['in_qty'] ?? 0), 4) : 0.0,
            'in_total_value' => $row ? round((float)($row['in_total_value'] ?? 0), 2) : 0.0,
            'out_qty' => $row ? round((float)($row['out_qty'] ?? 0), 4) : 0.0,
            'out_total_value' => $row ? round((float)($row['out_total_value'] ?? 0), 2) : 0.0,
            'waste_qty' => $row ? round((float)($row['waste_qty'] ?? 0), 4) : 0.0,
            'waste_total_value' => $row ? round((float)($row['waste_total_value'] ?? 0), 2) : 0.0,
            'spoil_qty' => $row ? round((float)($row['spoil_qty'] ?? 0), 4) : 0.0,
            'spoil_total_value' => $row ? round((float)($row['spoil_total_value'] ?? 0), 2) : 0.0,
            'adjustment_plus_qty' => $row ? round((float)($row['adjustment_plus_qty'] ?? 0), 4) : 0.0,
            'adjustment_plus_total_value' => $row ? round((float)($row['adjustment_plus_total_value'] ?? 0), 2) : 0.0,
            'adjustment_minus_qty' => $row ? round((float)($row['adjustment_minus_qty'] ?? 0), 4) : 0.0,
            'adjustment_minus_total_value' => $row ? round((float)($row['adjustment_minus_total_value'] ?? 0), 2) : 0.0,
            'closing_qty' => round((float)($ctx['qty_after'] ?? 0), 4),
            'avg_cost' => round((float)($ctx['avg_after'] ?? 0), 6),
            'total_value' => round((float)($ctx['value_after'] ?? 0), 2),
            'movement_day_count' => $movementDayCount,
            'mutation_count' => $row ? ((int)($row['mutation_count'] ?? 0) + 1) : 1,
            'last_movement_date' => $movementDate,
            'last_movement_at' => (string)($ctx['movement_datetime'] ?? date('Y-m-d H:i:s')),
            'last_movement_table' => !empty($ctx['source_table']) ? (string)$ctx['source_table'] : null,
            'last_movement_id' => !empty($ctx['source_id']) ? (int)$ctx['source_id'] : null,
            'source_mode' => 'LIVE',
            'notes' => !empty($ctx['notes']) ? (string)$ctx['notes'] : ($row['notes'] ?? null),
        ];

        if ($movementType === 'OPENING') {
            $data['opening_total_value'] = round((float)$data['opening_total_value'] + $movementValue, 2);
        } elseif (in_array($movementType, ['VOID_REVERSE', 'VOID_OUT'], true)) {
            // A reversal cancels the original activity bucket instead of becoming an adjustment.
            if (in_array($reversalOriginType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                $data['in_qty'] = round((float)$data['in_qty'] - (float)($ctx['qty_out'] ?? 0), 4);
                $data['in_total_value'] = round((float)$data['in_total_value'] - $movementValue, 2);
            } elseif (in_array($reversalOriginType, ['USAGE', 'PRODUCTION_OUT', 'TRANSFER_OUT'], true)) {
                $data['out_qty'] = round((float)$data['out_qty'] - (float)($ctx['qty_in'] ?? 0), 4);
                $data['out_total_value'] = round((float)$data['out_total_value'] - $movementValue, 2);
            } elseif ($reversalOriginType === 'WASTE') {
                $data['waste_qty'] = round((float)$data['waste_qty'] - (float)($ctx['qty_in'] ?? 0), 4);
                $data['waste_total_value'] = round((float)$data['waste_total_value'] - $movementValue, 2);
            } elseif ($reversalOriginType === 'SPOIL') {
                $data['spoil_qty'] = round((float)$data['spoil_qty'] - (float)($ctx['qty_in'] ?? 0), 4);
                $data['spoil_total_value'] = round((float)$data['spoil_total_value'] - $movementValue, 2);
            } elseif ($reversalOriginType === 'ADJUSTMENT_PLUS') {
                $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] - (float)($ctx['qty_out'] ?? 0), 4);
                $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] - $movementValue, 2);
            } elseif ($reversalOriginType === 'ADJUSTMENT_MINUS') {
                $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] - (float)($ctx['qty_in'] ?? 0), 4);
                $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] - $movementValue, 2);
            }
        } elseif (in_array($movementType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
            $data['in_qty'] = round((float)$data['in_qty'] + (float)($ctx['qty_in'] ?? 0), 4);
            $data['in_total_value'] = round((float)$data['in_total_value'] + $movementValue, 2);
        } elseif (in_array($movementType, ['USAGE', 'PRODUCTION_OUT', 'TRANSFER_OUT'], true)) {
            $data['out_qty'] = round((float)$data['out_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['out_total_value'] = round((float)$data['out_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'WASTE') {
            $data['waste_qty'] = round((float)$data['waste_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['waste_total_value'] = round((float)$data['waste_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'SPOIL') {
            $data['spoil_qty'] = round((float)$data['spoil_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['spoil_total_value'] = round((float)$data['spoil_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'ADJUSTMENT_PLUS') {
            $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] + (float)($ctx['qty_in'] ?? 0), 4);
            $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'ADJUSTMENT_MINUS') {
            $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] + $movementValue, 2);
        }

        if ($row && !empty($row['id'])) {
            $this->ci->db->where('id', (int)$row['id'])->update('inv_component_monthly_stock', $data);
            return;
        }

        $this->ci->db->insert('inv_component_monthly_stock', $data);
    }

    private function load_snapshot(int $commitId): array
    {
        $header = $this->ci->db
            ->select('sc.*, o.order_scope')
            ->from('pos_stock_commit sc')
            ->join('pos_order o', 'o.id = sc.order_id', 'left')
            ->where('sc.id', $commitId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;

        $lines = $this->ci->db
            ->select('
                scl.*,
                ol.operational_division_id,
                od.name AS operational_division_name,
                od.code AS operational_division_code
            ')
            ->from('pos_stock_commit_line scl')
            ->join('pos_order_line ol', 'ol.id = scl.order_line_id', 'left')
            ->join('mst_operational_division od', 'od.id = ol.operational_division_id', 'left')
            ->where('scl.commit_id', $commitId)
            ->order_by('scl.line_no', 'ASC')
            ->get()
            ->result_array();

        return ['header' => $header, 'lines' => $lines];
    }

    private function lock_commit_lines_for_update(int $commitId): void
    {
        $this->ci->db->query(
            'SELECT id FROM pos_stock_commit_line WHERE commit_id = ? ORDER BY line_no ASC, id ASC FOR UPDATE',
            [$commitId]
        )->result_array();
    }

    private function prelock_snapshot_periods(array $header, array $lines, array $meta, bool $reversal): array
    {
        $pairs = [];
        $movementDate = $reversal
            ? $this->resolve_reversal_movement_date($meta)
            : $this->resolve_commit_movement_date($header);
        foreach ($lines as $entry) {
            $line = $reversal ? (array)($entry['line'] ?? []) : (array)$entry;
            if ($reversal && !in_array((string)($entry['return_policy'] ?? ''), ['RETURN_TO_STOCK', 'ADJUSTMENT_ONLY'], true)) {
                continue;
            }
            if (!$reversal) {
                $remainingQty = round(max(
                    0,
                    (float)($line['committed_qty'] ?? $line['required_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0)
                ), 4);
                $movementRefType = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
                if ($remainingQty <= 0.0001
                    || (($movementRefType !== '' && $movementRefType !== 'NONE') || (int)($line['movement_ref_id'] ?? 0) > 0)) {
                    continue;
                }
            }
            $pairs[] = [
                'stock_domain' => strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
                'event_date' => $movementDate,
            ];
        }
        if (empty($pairs)) {
            return ['ok' => true, 'periods' => []];
        }
        $this->ci->load->library('InventoryPeriodGuard');
        return $this->ci->inventoryperiodguard->lockActivePeriodsForWrite($pairs);
    }

    private function line_key(array $line): string
    {
        return 'commit_line:' . (int)($line['id'] ?? 0);
    }

    private function index_decisions(array $lineDecisions): array
    {
        $map = [];
        foreach ($lineDecisions as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $lineKey = trim((string)($decision['line_key'] ?? ''));
            if ($lineKey === '' && !empty($decision['line_id'])) {
                $lineKey = 'commit_line:' . (int)$decision['line_id'];
            }
            if ($lineKey === '') {
                continue;
            }
            $map[$lineKey] = $decision;
        }
        return $map;
    }

    private function resolve_operational_division_id(array $line): int
    {
        return max(0, (int)($line['operational_division_id'] ?? 0));
    }

    private function resolve_effective_stock_scope_for_line(array $line, string $orderScope = 'REGULAR'): array
    {
        $divisionId = $this->resolve_operational_division_id($line);
        $effectiveLine = $line;
        $recipeDiv = $this->resolve_recipe_source_division_for_line($line);
        if ($recipeDiv) {
            $divisionId = (int)$recipeDiv['id'];
            $effectiveLine['operational_division_id'] = $divisionId;
            $effectiveLine['operational_division_code'] = (string)($recipeDiv['code'] ?? '');
            $effectiveLine['operational_division_name'] = (string)($recipeDiv['name'] ?? '');
        }

        return [
            'division_id' => $divisionId,
            'destination_type' => $this->resolve_destination_type($effectiveLine, $orderScope),
            'line' => $effectiveLine,
            'recipe_division' => $recipeDiv,
        ];
    }

    private function resolve_destination_type(array $line, string $orderScope = 'REGULAR'): string
    {
        $divisionName = strtoupper(trim((string)($line['operational_division_code'] ?? ($line['operational_division_name'] ?? ''))));
        $isEvent = strtoupper(trim($orderScope)) === 'EVENT';
        if (in_array($divisionName, ['BAR', 'BEVERAGE'], true)) {
            return $isEvent ? 'BAR_EVENT' : 'BAR';
        }
        if (in_array($divisionName, ['KITCHEN', 'FOOD'], true)) {
            return $isEvent ? 'KITCHEN_EVENT' : 'KITCHEN';
        }
        if (in_array($divisionName, ['ROASTERY', 'ROASTER'], true)) {
            return $isEvent ? 'ROASTERY_EVENT' : 'ROASTERY';
        }
        return 'OTHER';
    }

    private function build_commit_line_scope_audit(array $header, array $line): array
    {
        $orderScope = (string)($header['order_scope'] ?? 'REGULAR');
        $expectedScope = $this->resolve_effective_stock_scope_for_line($line, $orderScope);
        $actualScope = $this->resolve_actual_scope_from_movement_ref($header, $line);

        $expectedDivisionId = (int)($expectedScope['division_id'] ?? 0);
        $expectedDestinationType = (string)($expectedScope['destination_type'] ?? 'OTHER');
        $actualDivisionId = (int)($actualScope['division_id'] ?? 0);
        $actualDestinationType = (string)($actualScope['destination_type'] ?? ($actualScope['location_type'] ?? 'OTHER'));
        $actualRefKind = (string)($actualScope['ref_kind'] ?? 'NONE');

        $isMismatch = false;
        if ($expectedDivisionId > 0 && $expectedDestinationType !== 'OTHER') {
            $isMismatch = $actualDivisionId > 0
                && (
                    $actualDivisionId !== $expectedDivisionId
                    || strtoupper($actualDestinationType) !== strtoupper($expectedDestinationType)
                );
        }

        return [
            'line' => $line,
            'line_id' => (int)($line['id'] ?? 0),
            'commit_id' => (int)($header['id'] ?? 0),
            'order_id' => (int)($header['order_id'] ?? 0),
            'source_kind' => strtoupper((string)($line['source_kind'] ?? 'MATERIAL')),
            'expected_division_id' => $expectedDivisionId,
            'expected_division_code' => (string)($expectedScope['line']['operational_division_code'] ?? ($expectedScope['recipe_division']['code'] ?? '')),
            'expected_division_name' => (string)($expectedScope['line']['operational_division_name'] ?? ($expectedScope['recipe_division']['name'] ?? '')),
            'expected_destination_type' => $expectedDestinationType,
            'actual_division_id' => $actualDivisionId,
            'actual_division_code' => (string)($actualScope['division_code'] ?? ''),
            'actual_division_name' => (string)($actualScope['division_name'] ?? ''),
            'actual_destination_type' => $actualDestinationType,
            'actual_ref_kind' => $actualRefKind,
            'movement_ref_type' => (string)($line['movement_ref_type'] ?? 'NONE'),
            'movement_ref_id' => (int)($line['movement_ref_id'] ?? 0),
            'is_mismatch' => $isMismatch,
        ];
    }

    private function resolve_actual_scope_from_movement_ref(array $header, array $line): array
    {
        $refId = (int)($line['movement_ref_id'] ?? 0);
        if ($refId <= 0) {
            return [
                'division_id' => 0,
                'destination_type' => 'OTHER',
                'location_type' => 'OTHER',
                'ref_kind' => 'NONE',
            ];
        }

        if (strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT') {
            $refType = $this->resolve_component_movement_ref_type($header, $line);
            if ($refType === 'COMPONENT_LOT_ISSUE' && $this->ci->db->table_exists('inv_component_lot_issue_log')) {
                $row = $this->ci->db->select('division_id, location_type')
                    ->from('inv_component_lot_issue_log')
                    ->where('id', $refId)
                    ->limit(1)
                    ->get()
                    ->row_array() ?: [];
                return $this->enrich_division_scope($row, 'COMPONENT_LOT_ISSUE');
            }
            if ($this->ci->db->table_exists('inv_component_movement_log')) {
                $row = $this->ci->db->select('division_id, location_type')
                    ->from('inv_component_movement_log')
                    ->where('id', $refId)
                    ->limit(1)
                    ->get()
                    ->row_array() ?: [];
                return $this->enrich_division_scope($row, 'COMPONENT_MOVEMENT');
            }
        }

        $refType = $this->resolve_material_movement_ref_type($header, $line);
        if ($refType === 'FIFO_ISSUE' && $this->ci->db->table_exists('inv_material_fifo_issue_log')) {
            $row = $this->ci->db->select('division_id, destination_type')
                ->from('inv_material_fifo_issue_log')
                ->where('id', $refId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            return $this->enrich_division_scope($row, 'FIFO_ISSUE');
        }
        if ($this->ci->db->table_exists('inv_stock_movement_log')) {
            $row = $this->ci->db->select('division_id, destination_type')
                ->from('inv_stock_movement_log')
                ->where('id', $refId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            return $this->enrich_division_scope($row, 'LEDGER_MOVEMENT');
        }

        return [
            'division_id' => 0,
            'destination_type' => 'OTHER',
            'location_type' => 'OTHER',
            'ref_kind' => 'UNKNOWN',
        ];
    }

    private function enrich_division_scope(array $row, string $refKind): array
    {
        $divisionId = (int)($row['division_id'] ?? 0);
        $division = [];
        if ($divisionId > 0) {
            $division = $this->ci->db->select('code, name')
                ->from('mst_operational_division')
                ->where('id', $divisionId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
        }

        return [
            'division_id' => $divisionId,
            'division_code' => (string)($division['code'] ?? ''),
            'division_name' => (string)($division['name'] ?? ''),
            'destination_type' => (string)($row['destination_type'] ?? ($row['location_type'] ?? 'OTHER')),
            'location_type' => (string)($row['location_type'] ?? ($row['destination_type'] ?? 'OTHER')),
            'ref_kind' => $refKind,
        ];
    }

    private function resolve_recipe_source_division_for_line(array $line): ?array
    {
        if (!empty($line['resolved_source_division_id'])) {
            return [
                'id' => (int)$line['resolved_source_division_id'],
                'name' => (string)($line['resolved_source_division_name'] ?? $line['operational_division_name'] ?? ''),
                'code' => (string)($line['resolved_source_division_code'] ?? $line['operational_division_code'] ?? ''),
            ];
        }

        $productId = !empty($line['product_id']) ? (int)$line['product_id'] : 0;
        if ($productId <= 0) {
            return null;
        }

        $row = [];
        $componentId = !empty($line['component_id']) ? (int)$line['component_id'] : 0;
        if ($componentId > 0) {
            $row = $this->ci->db
                ->select('od.id, od.name, od.code')
                ->from('mst_product_recipe r')
                ->join('mst_operational_division od', 'od.id = r.source_division_id', 'inner')
                ->where('r.product_id', $productId)
                ->where('r.component_id', $componentId)
                ->where('r.source_division_id IS NOT NULL', null, false)
                ->order_by('r.id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
        }

        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : 0;
        if ($materialId > 0) {
            $row = $this->ci->db
                ->select('od.id, od.name, od.code')
                ->from('mst_product_recipe r')
                ->join('mst_item i', 'i.id = r.material_item_id', 'inner')
                ->join('mst_operational_division od', 'od.id = r.source_division_id', 'inner')
                ->where('r.product_id', $productId)
                ->where('i.material_id', $materialId)
                ->where('r.source_division_id IS NOT NULL', null, false)
                ->order_by('r.id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
        }

        // Fallback: untuk extra replacement / optional material yang tidak ada
        // sebagai recipe line utama, pakai source_division pertama dari recipe
        // produk agar commit tetap mengarah ke division stok yang sama.
        if (empty($row)) {
            $row = $this->ci->db
                ->select('od.id, od.name, od.code')
                ->from('mst_product_recipe r')
                ->join('mst_operational_division od', 'od.id = r.source_division_id', 'inner')
                ->where('r.product_id', $productId)
                ->where('r.source_division_id IS NOT NULL', null, false)
                ->order_by('r.id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
        }

        return $row ?: null;
    }

    private function resolve_component_location_type(array $line, string $orderScope = 'REGULAR'): ?string
    {
        $destinationType = $this->resolve_destination_type($line, $orderScope);
        return in_array($destinationType, ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true) ? $destinationType : null;
    }

    private function infer_material_identity(array $line, int $divisionId, string $destinationType): array
    {
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : 0;
        $uomId = !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : 0;
        $requestedProfileKey = !empty($line['profile_key']) ? (string)$line['profile_key'] : null;
        $identity = [
            'item_id' => null,
            'buy_uom_id' => null,
            'content_uom_id' => $uomId > 0 ? $uomId : null,
            'profile_key' => null,
            'profile_name' => null,
            'profile_brand' => null,
            'profile_description' => null,
            'profile_expired_date' => null,
            'profile_content_per_buy' => null,
            'profile_buy_uom_code' => null,
            'profile_content_uom_code' => null,
        ];

        $preferred = $materialId > 0
            ? $this->resolve_preferred_item_identity_for_material($materialId, null, $uomId > 0 ? $uomId : null, $requestedProfileKey)
            : [];

        if ($materialId > 0) {
            $balance = $this->load_division_material_profile_snapshot($materialId, $divisionId, $destinationType, $uomId, $requestedProfileKey);
            // Recipes created before a profile join can still carry an old
            // profile key. If that exact profile no longer exists in the
            // operational division balance, prefer the current balance over
            // silently falling back to a catalog profile from another area.
            if (empty($balance) && $requestedProfileKey !== null && trim($requestedProfileKey) !== '') {
                $balance = $this->load_division_material_profile_snapshot($materialId, $divisionId, $destinationType, $uomId);
            }
            if (!empty($balance)) {
                // A live division balance is the operational source of truth.
                // A catalog can describe another valid purchase profile for the
                // same material, but must not overwrite the profile actually
                // consumed by FIFO when a POS deficit is recorded.
                $identity = array_merge($identity, [
                    'item_id' => !empty($balance['item_id']) ? (int)$balance['item_id'] : (!empty($preferred['item_id']) ? (int)$preferred['item_id'] : null),
                    'buy_uom_id' => !empty($balance['buy_uom_id']) ? (int)$balance['buy_uom_id'] : (!empty($preferred['buy_uom_id']) ? (int)$preferred['buy_uom_id'] : null),
                    'content_uom_id' => !empty($balance['content_uom_id']) ? (int)$balance['content_uom_id'] : $identity['content_uom_id'],
                    'profile_key' => $balance['profile_key'] ?? ($preferred['profile_key'] ?? null),
                    'profile_name' => $balance['profile_name'] ?? ($preferred['profile_name'] ?? null),
                    'profile_brand' => $balance['profile_brand'] ?? ($preferred['profile_brand'] ?? null),
                    'profile_description' => $balance['profile_description'] ?? ($preferred['profile_description'] ?? null),
                    'profile_expired_date' => $balance['profile_expired_date'] ?? ($preferred['profile_expired_date'] ?? null),
                    'profile_content_per_buy' => !empty($balance['profile_content_per_buy']) ? (float)$balance['profile_content_per_buy'] : (!empty($preferred['profile_content_per_buy']) ? (float)$preferred['profile_content_per_buy'] : null),
                    'profile_buy_uom_code' => $balance['profile_buy_uom_code'] ?? ($preferred['profile_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $balance['profile_content_uom_code'] ?? ($preferred['profile_content_uom_code'] ?? null),
                ]);
                return $identity;
            }
        }

        if (!empty($preferred)) {
            $identity = array_merge($identity, $preferred);
        }

        return $identity;
    }

    /**
     * For a partial FIFO issue, preserve the actual lot profile when all
     * issued allocations came from one profile. Multiple source profiles are
     * intentionally left to the resolved material identity: inventing a
     * single profile for a mixed FIFO issue would make later settlement less
     * auditable.
     */
    private function apply_fifo_allocation_identity(array $identity, array $allocations): array
    {
        $sourceLots = [];
        foreach ($allocations as $allocation) {
            $sourceLot = is_array($allocation['source_lot'] ?? null) ? $allocation['source_lot'] : [];
            $profileKey = trim((string)($sourceLot['profile_key'] ?? ''));
            if ($profileKey === '') {
                continue;
            }
            $sourceLots[$profileKey] = $sourceLot;
        }

        if (count($sourceLots) !== 1) {
            return $identity;
        }

        $sourceLot = reset($sourceLots);
        return array_merge($identity, [
            'item_id' => !empty($sourceLot['item_id']) ? (int)$sourceLot['item_id'] : ($identity['item_id'] ?? null),
            'buy_uom_id' => !empty($sourceLot['buy_uom_id']) ? (int)$sourceLot['buy_uom_id'] : ($identity['buy_uom_id'] ?? null),
            'content_uom_id' => !empty($sourceLot['content_uom_id']) ? (int)$sourceLot['content_uom_id'] : ($identity['content_uom_id'] ?? null),
            'profile_key' => (string)($sourceLot['profile_key'] ?? ($identity['profile_key'] ?? '')),
            'profile_expired_date' => $sourceLot['expiry_date'] ?? ($identity['profile_expired_date'] ?? null),
        ]);
    }

    private function resolve_buy_qty_from_profile(float $qtyContent, float $contentPerBuy): float
    {
        return $contentPerBuy > 0 ? round($qtyContent / $contentPerBuy, 4) : 0.0;
    }

    private function load_material_issue(int $issueId): array
    {
        if ($issueId <= 0 || !$this->ci->db->table_exists('inv_material_fifo_issue_log')) {
            return ['header' => null, 'lines' => []];
        }

        $header = $this->ci->db->from('inv_material_fifo_issue_log')->where('id', $issueId)->limit(1)->get()->row_array() ?: null;
        $lines = $this->ci->db
            ->select('l.*, lot.item_id, lot.material_id, lot.buy_uom_id, lot.content_uom_id, lot.profile_key, lot.lot_no AS source_lot_no, lot.receipt_date, lot.expiry_date')
            ->from('inv_material_fifo_issue_line l')
            ->join('inv_material_fifo_lot lot', 'lot.id = l.lot_id', 'left')
            ->where('l.issue_id', $issueId)
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        return ['header' => $header, 'lines' => $lines];
    }

    private function build_material_snapshot_from_issue(?array $issueHeader, array $issueLine, array $commitLine, int $divisionId, string $destinationType): array
    {
        $contentPerBuy = null;
        $profile = null;
        if (!empty($issueLine['material_id']) && !empty($issueLine['content_uom_id'])) {
            $profile = $this->load_division_material_profile_snapshot(
                (int)$issueLine['material_id'],
                $divisionId,
                $destinationType,
                (int)$issueLine['content_uom_id'],
                !empty($issueLine['profile_key']) ? (string)$issueLine['profile_key'] : null
            );
        }

        $contentPerBuy = !empty($profile['profile_content_per_buy']) ? (float)$profile['profile_content_per_buy'] : 0.0;
        $qtyContent = round((float)($issueLine['qty_out'] ?? 0), 4);

        return [
            'item_id' => !empty($issueLine['item_id']) ? (int)$issueLine['item_id'] : null,
            'material_id' => !empty($issueLine['material_id']) ? (int)$issueLine['material_id'] : (!empty($commitLine['material_id']) ? (int)$commitLine['material_id'] : null),
            'buy_uom_id' => !empty($issueLine['buy_uom_id']) ? (int)$issueLine['buy_uom_id'] : null,
            'content_uom_id' => !empty($issueLine['content_uom_id']) ? (int)$issueLine['content_uom_id'] : (!empty($commitLine['required_uom_id']) ? (int)$commitLine['required_uom_id'] : null),
            'profile_key' => $issueLine['profile_key'] ?? null,
            'profile_name' => $profile['profile_name'] ?? ($issueLine['source_lot_no'] ?? null),
            'profile_brand' => $profile['profile_brand'] ?? null,
            'profile_description' => $profile['profile_description'] ?? null,
            'profile_expired_date' => $profile['profile_expired_date'] ?? ($issueLine['expiry_date'] ?? null),
            'profile_content_per_buy' => $contentPerBuy > 0 ? $contentPerBuy : null,
            'profile_buy_uom_code' => $profile['profile_buy_uom_code'] ?? null,
            'profile_content_uom_code' => $profile['profile_content_uom_code'] ?? null,
            'qty_content_delta_abs' => $qtyContent,
            'qty_buy_delta_abs' => $this->resolve_buy_qty_from_profile($qtyContent, $contentPerBuy),
        ];
    }

    private function load_division_material_profile_snapshot(int $materialId, int $divisionId, string $destinationType, int $uomId, ?string $profileKey = null): array
    {
        if ($materialId <= 0 || $divisionId <= 0 || $uomId <= 0) {
            return [];
        }

        if ($this->ci->db->table_exists('inv_division_monthly_stock')) {
            $db = $this->ci->db->from('inv_division_monthly_stock')
                ->where('division_id', $divisionId)
                ->where('destination_type', $destinationType)
                ->where('material_id', $materialId)
                ->where('content_uom_id', $uomId);
            $profileKey = trim((string)$profileKey);
            if ($profileKey !== '') {
                $db->where('profile_key', $profileKey);
            }
            if ($this->ci->db->field_exists('source_mode', 'inv_division_monthly_stock')) {
                $db->order_by("CASE WHEN source_mode = 'REBUILD' THEN 0 WHEN source_mode = 'LIVE' THEN 1 ELSE 2 END", '', false);
            }
            $row = $db
                ->order_by("CASE WHEN COALESCE(profile_key, '') <> '' THEN 0 ELSE 1 END", '', false)
                ->order_by("CASE WHEN COALESCE(item_id, 0) > 0 THEN 0 ELSE 1 END", '', false)
                ->order_by('month_key', 'DESC')
                ->order_by('updated_at', 'DESC')
                ->order_by('last_movement_at', 'DESC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }

    private function load_material_balance_snapshot_for_adjustment_line(array $line, array $header, string $movementDate): array
    {
        if (!$this->ci->db->table_exists('inv_division_monthly_stock')) {
            return [];
        }

        $divisionId = (int)($header['division_id'] ?? 0);
        $destinationType = strtoupper(trim((string)($header['destination_type'] ?? 'OTHER')));
        $contentUomId = (int)($line['content_uom_id'] ?? 0);
        if ($divisionId <= 0 || $destinationType === 'OTHER' || $contentUomId <= 0) {
            return [];
        }

        $targetMonth = date('Y-m-01', strtotime($movementDate));
        $this->ci->db->select('closing_qty_content AS qty_on_hand, avg_cost_per_content AS avg_cost, total_value, month_key, updated_at, last_movement_at', false)
            ->from('inv_division_monthly_stock')
            ->where('division_id', $divisionId)
            ->where('destination_type', $destinationType)
            ->where('content_uom_id', $contentUomId)
            ->where('month_key <=', $targetMonth);

        if (!empty($line['item_id'])) {
            $this->ci->db->where('item_id', (int)$line['item_id']);
        } else {
            $this->ci->db->where('item_id IS NULL', null, false);
        }
        if (!empty($line['material_id'])) {
            $this->ci->db->where('material_id', (int)$line['material_id']);
        } else {
            $this->ci->db->where('material_id IS NULL', null, false);
        }
        if (!empty($line['buy_uom_id'])) {
            $this->ci->db->where('buy_uom_id', (int)$line['buy_uom_id']);
        } else {
            $this->ci->db->where('buy_uom_id IS NULL', null, false);
        }

        $profileKey = trim((string)($line['profile_key'] ?? ''));
        if ($profileKey !== '') {
            $this->ci->db->where('profile_key', $profileKey);
        } else {
            $this->ci->db->where("(profile_key IS NULL OR profile_key = '')", null, false);
        }

        if ($this->ci->db->field_exists('source_mode', 'inv_division_monthly_stock')) {
            $this->ci->db->order_by("CASE WHEN source_mode = 'REBUILD' THEN 0 WHEN source_mode = 'LIVE' THEN 1 ELSE 2 END", '', false);
        }

        return $this->ci->db
            ->order_by('month_key', 'DESC')
            ->order_by('updated_at', 'DESC')
            ->order_by('last_movement_at', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: [];
    }

    private function resolve_preferred_item_identity_for_material(int $materialId, ?int $buyUomId, ?int $contentUomId, ?string $profileKey = null): array
    {
        if ($materialId <= 0) {
            return [];
        }

        if ($this->ci->db->table_exists('mst_purchase_catalog')) {
            $db = $this->ci->db
                ->select('c.item_id, c.buy_uom_id, c.content_uom_id, c.profile_key, c.catalog_name AS profile_name, c.brand_name AS profile_brand, c.line_description AS profile_description, c.content_per_buy AS profile_content_per_buy, bu.code AS profile_buy_uom_code, cu.code AS profile_content_uom_code', false)
                ->from('mst_purchase_catalog c')
                ->join('mst_uom bu', 'bu.id = c.buy_uom_id', 'left')
                ->join('mst_uom cu', 'cu.id = c.content_uom_id', 'left')
                ->where('c.material_id', $materialId)
                ->where('c.item_id >', 0);
            $profileKey = trim((string)$profileKey);
            if ($profileKey !== '') {
                $db->group_start()
                    ->where('c.profile_key', $profileKey)
                    ->or_where('c.profile_key', '')
                    ->or_where('c.profile_key IS NULL', null, false)
                ->group_end()
                ->order_by("CASE WHEN c.profile_key = " . $this->ci->db->escape($profileKey) . " THEN 0 WHEN COALESCE(c.profile_key, '') = '' THEN 1 ELSE 2 END", '', false);
            }
            if ($buyUomId !== null && $buyUomId > 0) {
                $db->order_by("CASE WHEN c.buy_uom_id = " . (int)$buyUomId . " THEN 0 ELSE 1 END", '', false);
            }
            if ($contentUomId !== null && $contentUomId > 0) {
                $db->order_by("CASE WHEN c.content_uom_id = " . (int)$contentUomId . " THEN 0 ELSE 1 END", '', false);
            }
            $row = $db
                ->order_by('c.is_active', 'DESC')
                ->order_by('c.id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (!empty($row)) {
                return [
                    'item_id' => !empty($row['item_id']) ? (int)$row['item_id'] : null,
                    'buy_uom_id' => !empty($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                    'content_uom_id' => !empty($row['content_uom_id']) ? (int)$row['content_uom_id'] : $contentUomId,
                    'profile_key' => $row['profile_key'] ?? null,
                    'profile_name' => $row['profile_name'] ?? null,
                    'profile_brand' => $row['profile_brand'] ?? null,
                    'profile_description' => $row['profile_description'] ?? null,
                    'profile_expired_date' => null,
                    'profile_content_per_buy' => !empty($row['profile_content_per_buy']) ? (float)$row['profile_content_per_buy'] : null,
                    'profile_buy_uom_code' => $row['profile_buy_uom_code'] ?? null,
                    'profile_content_uom_code' => $row['profile_content_uom_code'] ?? null,
                ];
            }
        }

        if ($this->ci->db->table_exists('mst_item')) {
            $row = $this->ci->db
                ->select('id, buy_uom_id, content_uom_id, item_name')
                ->from('mst_item')
                ->where('material_id', $materialId)
                ->where('is_active', 1)
                ->order_by('id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (!empty($row)) {
                return [
                    'item_id' => !empty($row['id']) ? (int)$row['id'] : null,
                    'buy_uom_id' => !empty($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                    'content_uom_id' => !empty($row['content_uom_id']) ? (int)$row['content_uom_id'] : $contentUomId,
                    'profile_key' => null,
                    'profile_name' => $row['item_name'] ?? null,
                    'profile_brand' => null,
                    'profile_description' => null,
                    'profile_expired_date' => null,
                    'profile_content_per_buy' => null,
                    'profile_buy_uom_code' => null,
                    'profile_content_uom_code' => null,
                ];
            }
        }

        return [];
    }

    private function load_component_balance_snapshot(string $locationType, ?int $divisionId, int $componentId, int $uomId, string $movementDate): array
    {
        if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
            return [];
        }

        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01', strtotime($movementDate));
            $row = $this->ci->db->query(
                'SELECT month_key, closing_qty AS qty_on_hand, avg_cost, total_value, last_movement_at
                 FROM inv_component_monthly_stock
                 WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? AND month_key <= ?
                 ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC
                 LIMIT 1 FOR UPDATE',
                [$locationType, $divisionId, $componentId, $uomId, $targetMonth]
            )->row_array();
            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }

    private function generate_component_movement_no(string $movementDate): string
    {
        $prefix = 'PCM' . date('Ymd', strtotime($movementDate));
        $row = $this->ci->db
            ->select('movement_no')
            ->from('inv_component_movement_log')
            ->like('movement_no', $prefix, 'after')
            ->order_by('movement_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['movement_no'])) {
            $suffix = substr((string)$row['movement_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->ci->db->where('movement_no', $no)->count_all_results('inv_component_movement_log') > 0);

        return $no;
    }

    private function merge_note(string $base, string $append): ?string
    {
        $base = trim($base);
        $append = trim($append);
        if ($append === '') {
            return $base !== '' ? $base : null;
        }
        if ($base === '') {
            return $append;
        }
        return $base . ' | ' . $append;
    }

    private function normalize_commit_line_movement_ref_type_for_storage(string $movementRefType): ?string
    {
        $canonical = strtoupper(trim($movementRefType));
        if ($canonical === '' || $canonical === 'NONE') {
            return 'NONE';
        }

        $supported = $this->commit_line_movement_ref_enum_values();
        if (empty($supported)) {
            return $canonical;
        }
        if (in_array($canonical, $supported, true)) {
            return $canonical;
        }

        $fallbackMap = [
            'FIFO_ISSUE' => 'MATERIAL_LEDGER',
            'LEDGER_MOVEMENT' => 'MATERIAL_LEDGER',
            'COMPONENT_LOT_ISSUE' => 'COMPONENT_LEDGER',
            'COMPONENT_MOVEMENT' => 'COMPONENT_LEDGER',
            'INVENTORY_DEFICIT' => 'INVENTORY_DEFICIT',
        ];
        $fallback = $fallbackMap[$canonical] ?? 'NONE';
        if (in_array($fallback, $supported, true)) {
            return $fallback;
        }

        return 'NONE';
    }

    private function normalize_commit_line_cost_source_for_storage(string $costSource): string
    {
        $canonical = strtoupper(trim($costSource));
        if ($canonical === '') {
            $canonical = 'STANDARD_FALLBACK';
        }

        $supported = $this->commit_line_cost_source_enum_values();
        if (empty($supported) || in_array($canonical, $supported, true)) {
            return $canonical;
        }

        // Keep POS posting usable before the schema migration is deployed.
        // The migration adds DEFICIT_PENDING; older schemas fall back to an
        // existing truthful cost label instead of silently writing an empty enum.
        return in_array('STANDARD_FALLBACK', $supported, true)
            ? 'STANDARD_FALLBACK'
            : (string)($supported[0] ?? 'FIFO');
    }

    private function formatThrowableMessage(Throwable $e): string
    {
        $message = trim((string)$e->getMessage());
        if ($message === '') {
            $message = 'Terjadi error saat memproses stok order POS.';
        }

        $file = basename((string)$e->getFile());
        $line = (int)$e->getLine();
        if ($file !== '') {
            $message .= ' [' . $file . ':' . $line . ']';
        }

        return $message;
    }

    private function commit_line_movement_ref_enum_values(): array
    {
        if (is_array($this->commitLineMovementRefEnumValues)) {
            return $this->commitLineMovementRefEnumValues;
        }

        $this->commitLineMovementRefEnumValues = [];
        if (!$this->ci->db->table_exists('pos_stock_commit_line')) {
            return $this->commitLineMovementRefEnumValues;
        }

        $row = $this->ci->db->query("SHOW COLUMNS FROM pos_stock_commit_line LIKE 'movement_ref_type'")->row_array();
        $type = (string)($row['Type'] ?? ($row['type'] ?? ''));
        if (preg_match('/^enum\((.+)\)$/i', $type, $matches)) {
            preg_match_all("/'([^']+)'/", (string)$matches[1], $valueMatches);
            $this->commitLineMovementRefEnumValues = array_values(array_unique(array_map('strtoupper', $valueMatches[1] ?? [])));
        }

        return $this->commitLineMovementRefEnumValues;
    }

    private function commit_line_cost_source_enum_values(): array
    {
        if (is_array($this->commitLineCostSourceEnumValues)) {
            return $this->commitLineCostSourceEnumValues;
        }

        $this->commitLineCostSourceEnumValues = [];
        if (!$this->ci->db->table_exists('pos_stock_commit_line')) {
            return $this->commitLineCostSourceEnumValues;
        }

        $row = $this->ci->db->query("SHOW COLUMNS FROM pos_stock_commit_line LIKE 'cost_source'")->row_array();
        $type = (string)($row['Type'] ?? ($row['type'] ?? ''));
        if (preg_match('/^enum\((.+)\)$/i', $type, $matches)) {
            preg_match_all("/'([^']+)'/", (string)$matches[1], $valueMatches);
            $this->commitLineCostSourceEnumValues = array_values(array_unique(array_map('strtoupper', $valueMatches[1] ?? [])));
        }

        return $this->commitLineCostSourceEnumValues;
    }

    private function commit_line_has_column(string $column): bool
    {
        if (array_key_exists($column, $this->commitLineColumnExistsCache)) {
            return $this->commitLineColumnExistsCache[$column];
        }

        $exists = $this->ci->db->table_exists('pos_stock_commit_line')
            && $this->ci->db->field_exists($column, 'pos_stock_commit_line');
        $this->commitLineColumnExistsCache[$column] = $exists;

        return $exists;
    }

    private function resolve_material_movement_ref_type(array $header, array $line): string
    {
        $stored = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
        if (in_array($stored, ['FIFO_ISSUE', 'LEDGER_MOVEMENT', 'INVENTORY_DEFICIT'], true)) {
            return $stored;
        }

        if ($this->is_inventory_deficit_reference($header, $line)) {
            return 'INVENTORY_DEFICIT';
        }

        if ($this->is_material_fifo_issue_reference($header, $line)) {
            return 'FIFO_ISSUE';
        }

        return !empty($line['movement_ref_id']) ? 'LEDGER_MOVEMENT' : 'NONE';
    }

    private function resolve_component_movement_ref_type(array $header, array $line): string
    {
        $stored = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
        if (in_array($stored, ['COMPONENT_LOT_ISSUE', 'COMPONENT_MOVEMENT', 'INVENTORY_DEFICIT'], true)) {
            return $stored;
        }

        if ($this->is_inventory_deficit_reference($header, $line)) {
            return 'INVENTORY_DEFICIT';
        }

        if ($this->is_component_lot_issue_reference($header, $line)) {
            return 'COMPONENT_LOT_ISSUE';
        }

        return !empty($line['movement_ref_id']) ? 'COMPONENT_MOVEMENT' : 'NONE';
    }

    private function is_inventory_deficit_reference(array $header, array $line): bool
    {
        $refId = (int)($line['movement_ref_id'] ?? 0);
        if ($refId <= 0 || !$this->ci->db->table_exists('inv_stock_deficit')) {
            return false;
        }

        $db = $this->ci->db
            ->from('inv_stock_deficit')
            ->where('id', $refId)
            ->where('source_table', 'pos_stock_commit');
        if (!empty($header['id'])) {
            $db->where('source_id', (int)$header['id']);
        }
        if (!empty($line['id'])) {
            $db->where('source_line_id', (int)$line['id']);
        }

        return (bool)$db->limit(1)->get()->row_array();
    }

    private function is_material_fifo_issue_reference(array $header, array $line): bool
    {
        $refId = (int)($line['movement_ref_id'] ?? 0);
        if ($refId <= 0 || !$this->ci->db->table_exists('inv_material_fifo_issue_log')) {
            return false;
        }

        $this->ci->db->from('inv_material_fifo_issue_log')
            ->where('id', $refId)
            ->where('source_table', 'pos_stock_commit');
        if (!empty($header['id'])) {
            $this->ci->db->where('source_id', (int)$header['id']);
        }
        if (!empty($line['id'])) {
            $this->ci->db->where('source_line_id', (int)$line['id']);
        }

        return (bool)$this->ci->db->limit(1)->get()->row_array();
    }

    private function is_component_lot_issue_reference(array $header, array $line): bool
    {
        $refId = (int)($line['movement_ref_id'] ?? 0);
        if ($refId <= 0 || !$this->ci->db->table_exists('inv_component_lot_issue_log')) {
            return false;
        }

        $this->ci->db->from('inv_component_lot_issue_log')
            ->where('id', $refId)
            ->where('source_table', 'pos_stock_commit');
        if (!empty($header['id'])) {
            $this->ci->db->where('source_id', (int)$header['id']);
        }
        if (!empty($line['id'])) {
            $this->ci->db->where('source_line_id', (int)$line['id']);
        }

        return (bool)$this->ci->db->limit(1)->get()->row_array();
    }

    private function post_material_rollback_fallback(array $header, array $line, float $reverseQty, array $meta, string $reason): array
    {
        $scope = $this->resolve_effective_stock_scope_for_line($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $line = $scope['line'];
        $divisionId = $scope['division_id'];
        $destinationType = $scope['destination_type'];
        $movementDate = $this->resolve_reversal_movement_date($meta);
        $identity = $this->infer_material_identity($line, $divisionId, $destinationType);
        $qtyBuyAbs = $this->resolve_buy_qty_from_profile($reverseQty, (float)($identity['profile_content_per_buy'] ?? 0));

        $post = $this->ci->inventoryledger->post([
            'movement_scope' => 'DIVISION',
            'movement_date' => $movementDate,
            'movement_type' => 'VOID_REVERSE',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'ref_table' => 'pos_stock_commit_reversal',
            'ref_id' => (int)($header['id'] ?? 0),
            'item_id' => $identity['item_id'],
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
            'qty_buy_delta' => $qtyBuyAbs,
            'qty_content_delta' => $reverseQty,
            'profile_key' => $identity['profile_key'],
            'profile_name' => $identity['profile_name'],
            'profile_brand' => $identity['profile_brand'],
            'profile_description' => $identity['profile_description'],
            'profile_expired_date' => $identity['profile_expired_date'],
            'profile_content_per_buy' => $identity['profile_content_per_buy'],
            'profile_buy_uom_code' => $identity['profile_buy_uom_code'],
            'profile_content_uom_code' => $identity['profile_content_uom_code'],
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'force_avg_cost_per_content' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'allow_negative_balance' => true,
            'notes' => 'POS rollback fallback aggregate reversal. ' . $reason,
            'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
            'manage_transaction' => false,
            'skip_availability_refresh' => true,
        ]);
        if (!($post['ok'] ?? false)) {
            return $post;
        }

        return [
            'ok' => true,
            'movement_ref_type' => 'LEDGER_MOVEMENT',
            'movement_ref_id' => (int)($post['data']['movement_id'] ?? 0),
            'notes' => $reason,
        ];
    }

    private function rollback_or_restore_component_lots(array $header, array $line, float $reverseQty, array $meta, string $movementDate, ?string $locationType, ?int $divisionId): array
    {
        if ($reverseQty <= 0) {
            return ['ok' => true];
        }
        if ($locationType === null || !file_exists(APPPATH . 'libraries/ComponentLotManager.php')) {
            return [
                'ok' => false,
                'message' => 'Lot component asal tidak tersedia untuk pengembalian POS. Sistem tidak mengembalikan stok tanpa lot pasangan.',
            ];
        }

        $this->ci->load->library('ComponentLotManager');
        $rollback = $this->ci->componentlotmanager->rollbackIssueLotsBySource(
            'pos_stock_commit',
            (int)($header['id'] ?? 0),
            (int)($line['id'] ?? 0),
            (string)($meta['notes'] ?? 'Void/refund POS'),
            $reverseQty,
            true
        );
        if (!($rollback['ok'] ?? false)) {
            return $rollback;
        }

        $rolledQty = round((float)($rollback['data']['rolled_qty'] ?? 0), 4);
        $remainingQty = round($reverseQty - $rolledQty, 4);
        if ($remainingQty <= 0.0001) {
            return ['ok' => true, 'data' => ['rolled_qty' => $rolledQty, 'restored_qty' => 0.0]];
        }

        $restore = $this->register_component_rollback_fallback_lot($header, $line, $remainingQty, $meta, $movementDate, $locationType, $divisionId);
        if (!($restore['ok'] ?? false)) {
            return $restore;
        }

        return [
            'ok' => true,
            'data' => [
                'rolled_qty' => $rolledQty,
                'restored_qty' => $remainingQty,
                'lot_id' => (int)($restore['data']['id'] ?? 0),
            ],
        ];
    }

    private function register_component_rollback_fallback_lot(array $header, array $line, float $qty, array $meta, string $movementDate, string $locationType, ?int $divisionId): array
    {
        if ($qty <= 0.0001) {
            return ['ok' => true];
        }

        $componentId = (int)($line['component_id'] ?? 0);
        $uomId = (int)($line['required_uom_id'] ?? 0);
        if ($componentId <= 0 || $uomId <= 0) {
            return ['ok' => false, 'message' => 'Identitas komponen tidak valid untuk rollback lot POS.'];
        }

        $commitId = (int)($header['id'] ?? 0);
        $commitLineId = (int)($line['id'] ?? 0);
        $lotNo = sprintf(
            'POSR%sC%sL%s',
            date('Ymd', strtotime($movementDate)),
            str_pad((string)max(0, $commitId), 4, '0', STR_PAD_LEFT),
            str_pad((string)max(0, $commitLineId), 4, '0', STR_PAD_LEFT)
        );

        $register = $this->ci->componentlotmanager->registerProductionInboundLot([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'qty_in' => round($qty, 4),
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'lot_no' => $lotNo,
            'receipt_date' => $movementDate,
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit_reversal',
            'source_id' => $commitId > 0 ? $commitId : null,
            'source_line_id' => $commitLineId > 0 ? $commitLineId : null,
        ]);
        if (!($register['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string)($register['message'] ?? 'Gagal membentuk lot rollback komponen untuk POS.'),
            ];
        }

        return [
            'ok' => true,
            'data' => (array)($register['data'] ?? []),
            'message' => 'Lot rollback sintetis dibuat untuk menutup histori lot POS yang hilang.',
        ];
    }

    private function post_component_rollback_fallback(array $header, array $line, float $reverseQty, array $meta, ?string $locationType, ?int $divisionId, string $reason): array
    {
        return $this->post_component_aggregate_movement([
            'movement_date' => $this->resolve_reversal_movement_date($meta),
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => (int)($line['component_id'] ?? 0),
            'uom_id' => (int)($line['required_uom_id'] ?? 0),
            'movement_type' => 'VOID_REVERSE',
            'qty' => $reverseQty,
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit_reversal',
            'source_id' => (int)($header['id'] ?? 0),
            'source_line_id' => (int)($line['id'] ?? 0),
            'notes' => 'POS rollback fallback aggregate reversal. ' . $reason,
            'actor_employee_id' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0,
            'allow_negative' => false,
        ]);
    }

    private function is_missing_rollback_movement_message(string $message): bool
    {
        $message = strtolower(trim($message));
        if ($message === '') {
            return false;
        }

        return strpos($message, 'tidak ditemukan') !== false
            || strpos($message, 'not found') !== false;
    }
}
