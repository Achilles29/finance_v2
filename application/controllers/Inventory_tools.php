<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_tools extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }
        $this->load->database();
        $this->load->model('Purchase_model');
        $this->load->model('Production_model');
    }

    public function repost_pos_skipped_commit_lines()
    {
        $cliArgs = $this->parseCliArgs();
        $commitId = (int)($cliArgs['commit_id'] ?? 0);
        $orderId = (int)($cliArgs['order_id'] ?? 0);
        $limit = max(1, min(200, (int)($cliArgs['limit'] ?? 50)));
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $dryRun = in_array(strtolower((string)($cliArgs['dry_run'] ?? '0')), ['1', 'true', 'yes', 'y'], true);

        $this->load->model('Pos_model');
        $this->load->library('PosRuntimeJobService');
        $this->load->library('PosStockCommitService');

        $db = $this->db;
        $db->select('
            cl.commit_id,
            c.order_id,
            COUNT(*) AS skipped_line_count,
            SUM(CASE WHEN cl.line_type = "EXTRA" THEN 1 ELSE 0 END) AS skipped_extra_count,
            SUM(CASE WHEN cl.line_type = "PRODUCT" THEN 1 ELSE 0 END) AS skipped_product_count
        ', false);
        $db->from('pos_stock_commit_line cl');
        $db->join('pos_stock_commit c', 'c.id = cl.commit_id', 'inner');
        $db->where('COALESCE(cl.movement_ref_type, "NONE") =', 'NONE');
        $db->where('(cl.movement_ref_id IS NULL OR cl.movement_ref_id = 0)', null, false);
        $db->like('COALESCE(cl.notes, "")', 'destination OTHER', 'both', false);
        if ($commitId > 0) {
            $db->where('cl.commit_id', $commitId);
        }
        if ($orderId > 0) {
            $db->where('c.order_id', $orderId);
        }
        $targets = $db
            ->group_by('cl.commit_id')
            ->group_by('c.order_id')
            ->order_by('cl.commit_id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        if (empty($targets)) {
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Tidak ada skipped commit line destination OTHER yang perlu di-repost.',
                'filters' => [
                    'commit_id' => $commitId,
                    'order_id' => $orderId,
                    'limit' => $limit,
                    'dry_run' => $dryRun,
                ],
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            return;
        }

        if ($dryRun) {
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Dry run only.',
                'total_targets' => count($targets),
                'targets' => $targets,
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            return;
        }

        $results = [];
        foreach ($targets as $target) {
            $targetCommitId = (int)($target['commit_id'] ?? 0);
            $targetOrderId = (int)($target['order_id'] ?? 0);
            $actorEmployeeId = $this->resolvePosRepairActorEmployeeId($targetOrderId, $targetCommitId, $userId);

            if ($targetCommitId <= 0 || $targetOrderId <= 0) {
                $results[] = [
                    'commit_id' => $targetCommitId,
                    'order_id' => $targetOrderId,
                    'ok' => false,
                    'message' => 'Commit/order target tidak valid.',
                ];
                continue;
            }

            $queued = $this->posruntimejobservice->queue_order_confirm_commit($targetOrderId, $targetCommitId, $actorEmployeeId, [
                'event_source' => 'REPAIR_SKIPPED_DESTINATION_OTHER',
                'event_id' => $targetCommitId,
            ]);
            if (!($queued['ok'] ?? false)) {
                $results[] = [
                    'commit_id' => $targetCommitId,
                    'order_id' => $targetOrderId,
                    'ok' => false,
                    'message' => (string)($queued['message'] ?? 'Gagal membuat queue repost POS.'),
                ];
                continue;
            }

            $markQueued = $this->posstockcommitservice->mark_queued($targetCommitId);
            if (!($markQueued['ok'] ?? false)) {
                $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot stock commit gagal ditandai queued untuk repair skipped line.');
                $results[] = [
                    'commit_id' => $targetCommitId,
                    'order_id' => $targetOrderId,
                    'ok' => false,
                    'message' => (string)($markQueued['message'] ?? 'Gagal menandai snapshot queued.'),
                ];
                continue;
            }

            $processed = $this->posruntimejobservice->process_job((int)($queued['job_id'] ?? 0));
            $results[] = [
                'commit_id' => $targetCommitId,
                'order_id' => $targetOrderId,
                'job_id' => (int)($queued['job_id'] ?? 0),
                'job_code' => (string)($queued['job_code'] ?? ''),
                'ok' => (bool)($processed['ok'] ?? false),
                'message' => (string)(($processed['message'] ?? '') ?: (($processed['ok'] ?? false) ? 'Repost skipped commit line berhasil diproses.' : 'Repost skipped commit line gagal diproses.')),
                'queued' => $queued,
                'processed' => $processed,
            ];
        }

        $successCount = 0;
        foreach ($results as $row) {
            if (!empty($row['ok'])) {
                $successCount++;
            }
        }

        fwrite(STDOUT, json_encode([
            'ok' => $successCount === count($results),
            'processed_targets' => count($results),
            'success_targets' => $successCount,
            'failed_targets' => count($results) - $successCount,
            'results' => $results,
        ], JSON_PRETTY_PRINT) . PHP_EOL);
    }

    public function audit_pos_cross_division_commit_lines()
    {
        $cliArgs = $this->parseCliArgs();
        $commitId = (int)($cliArgs['commit_id'] ?? 0);
        $orderId = (int)($cliArgs['order_id'] ?? 0);
        $lineId = (int)($cliArgs['line_id'] ?? 0);
        $limit = max(1, min(500, (int)($cliArgs['limit'] ?? 100)));

        $this->load->library('PosOrderStockService');

        $targets = $this->collectPosCrossDivisionCommitTargets($commitId, $orderId, $lineId, $limit);
        if (empty($targets)) {
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Tidak ada kandidat commit POS untuk diaudit.',
                'filters' => compact('commitId', 'orderId', 'lineId', 'limit'),
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            return;
        }

        $rows = [];
        foreach ($targets as $target) {
            $audit = $this->posorderstockservice->audit_cross_division_commit_snapshot((int)$target['commit_id'], [
                'line_id' => $lineId,
            ]);
            if (!($audit['ok'] ?? false)) {
                $rows[] = [
                    'commit_id' => (int)$target['commit_id'],
                    'order_id' => (int)$target['order_id'],
                    'ok' => false,
                    'message' => (string)($audit['message'] ?? 'Audit gagal.'),
                ];
                continue;
            }

            foreach ((array)($audit['mismatches'] ?? []) as $mismatch) {
                $line = (array)($mismatch['line'] ?? []);
                $rows[] = [
                    'commit_id' => (int)$target['commit_id'],
                    'order_id' => (int)$target['order_id'],
                    'line_id' => (int)($mismatch['line_id'] ?? 0),
                    'line_type' => (string)($line['line_type'] ?? ''),
                    'source_kind' => (string)($mismatch['source_kind'] ?? ''),
                    'source_name_snapshot' => (string)($line['source_name_snapshot'] ?? ''),
                    'product_id' => (int)($line['product_id'] ?? 0),
                    'extra_id' => (int)($line['extra_id'] ?? 0),
                    'material_id' => (int)($line['material_id'] ?? 0),
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'expected_division_id' => (int)($mismatch['expected_division_id'] ?? 0),
                    'expected_division_code' => (string)($mismatch['expected_division_code'] ?? ''),
                    'expected_division_name' => (string)($mismatch['expected_division_name'] ?? ''),
                    'expected_destination_type' => (string)($mismatch['expected_destination_type'] ?? ''),
                    'actual_division_id' => (int)($mismatch['actual_division_id'] ?? 0),
                    'actual_division_code' => (string)($mismatch['actual_division_code'] ?? ''),
                    'actual_division_name' => (string)($mismatch['actual_division_name'] ?? ''),
                    'actual_destination_type' => (string)($mismatch['actual_destination_type'] ?? ''),
                    'actual_ref_kind' => (string)($mismatch['actual_ref_kind'] ?? ''),
                    'movement_ref_type' => (string)($mismatch['movement_ref_type'] ?? ''),
                    'movement_ref_id' => (int)($mismatch['movement_ref_id'] ?? 0),
                ];
            }
        }

        fwrite(STDOUT, json_encode([
            'ok' => true,
            'total_candidate_commits' => count($targets),
            'total_mismatches' => count($rows),
            'rows' => $rows,
        ], JSON_PRETTY_PRINT) . PHP_EOL);
    }

    /**
     * Read-only active-month verification for the main inventory writers.
     *
     * Usage:
     *   php index.php inventory_tools audit_active_month_integrity month 2026-08 limit 20
     */
    public function audit_active_month_integrity()
    {
        $cliArgs = $this->parseCliArgs();
        $month = (string)($cliArgs['month'] ?? date('Y-m'));
        $limit = max(1, min(100, (int)($cliArgs['limit'] ?? 20)));

        $this->load->library('InventoryActiveMonthIntegrityAudit');
        $result = $this->inventoryactivemonthintegrityaudit->run([
            'month' => $month,
            'limit' => $limit,
        ]);

        $output = json_encode($result, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($output === false) {
            $output = json_encode([
                'ok' => false,
                'message' => 'Audit selesai tetapi hasil tidak dapat diserialisasi ke JSON.',
            ], JSON_PRETTY_PRINT);
        }

        fwrite(($result['ok'] ?? false) ? STDOUT : STDERR, $output . PHP_EOL);
        exit(($result['ok'] ?? false) ? 0 : 1);
    }

    /**
     * Rebuild only the synthetic warehouse FIFO aggregate from the current
     * monthly ledger. No movement, stock balance, or valuation is rewritten.
     *
     * Usage:
     *   php index.php inventory_tools sync_warehouse_aggregate_lots month 2026-09 apply 1
     */
    public function sync_warehouse_aggregate_lots()
    {
        $cliArgs = $this->parseCliArgs();
        $month = (string)($cliArgs['month'] ?? date('Y-m'));
        $apply = in_array(strtolower((string)($cliArgs['apply'] ?? '0')), ['1', 'true', 'yes', 'y'], true);

        $this->load->library('MaterialFifoManager');
        $result = $this->materialfifomanager->syncWarehouseAggregateProfilesForMonth($month, !$apply);
        $result['operation'] = 'sync_warehouse_aggregate_lots';
        $result['apply'] = $apply;

        fwrite(($result['ok'] ?? false) ? STDOUT : STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
        exit(($result['ok'] ?? false) ? 0 : 1);
    }

    /**
     * Read-only smoke check for the period cut-off preview shown to operators.
     *
     * Usage:
     *   php index.php inventory_tools preview_period_cutoff domain MATERIAL month 2026-08 limit 5
     */
    public function preview_period_cutoff()
    {
        $cliArgs = $this->parseCliArgs();
        $domain = strtoupper(trim((string)($cliArgs['domain'] ?? 'MATERIAL')));
        $month = (string)($cliArgs['month'] ?? date('Y-m'));
        $limit = max(5, min(100, (int)($cliArgs['limit'] ?? 25)));

        $this->load->model('Inventory_control_model');
        $result = $this->Inventory_control_model->period_cutoff_preview($domain, $month, $limit);
        $output = json_encode($result, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($output === false) {
            $output = json_encode([
                'ok' => false,
                'message' => 'Simulasi cut-off selesai tetapi hasil tidak dapat diserialisasi ke JSON.',
            ], JSON_PRETTY_PRINT);
        }

        fwrite(($result['ok'] ?? false) ? STDOUT : STDERR, $output . PHP_EOL);
        exit(($result['ok'] ?? false) ? 0 : 1);
    }

    /**
     * Read-only preflight for the official cut-off action.
     *
     * Usage:
     *   php index.php inventory_tools preflight_period_cutoff domain MATERIAL month 2026-07
     */
    public function preflight_period_cutoff()
    {
        $cliArgs = $this->parseCliArgs();
        $domain = strtoupper(trim((string)($cliArgs['domain'] ?? 'MATERIAL')));
        $month = trim((string)($cliArgs['month'] ?? date('Y-m')));
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month .= '-01';
        }

        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true) || !preg_match('/^\d{4}-\d{2}-01$/', $month)) {
            fwrite(STDERR, json_encode([
                'ok' => false,
                'message' => 'Gunakan domain MATERIAL/COMPONENT dan month YYYY-MM.',
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        $this->load->model('Inventory_control_model');
        $period = $this->db
            ->from('inv_stock_period')
            ->where('stock_domain', $domain)
            ->where('period_month', $month)
            ->limit(1)
            ->get()
            ->row_array();
        if (empty($period)) {
            fwrite(STDERR, json_encode([
                'ok' => false,
                'message' => 'Periode stok belum dibuat untuk ' . $domain . ' ' . $month . '. Perintah ini hanya membaca data dan tidak akan membuat periode baru.',
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        $this->load->library('InventoryCutoffService');
        $result = $this->inventorycutoffservice->preflight($period);
        $output = json_encode($result, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($output === false) {
            $output = json_encode([
                'ok' => false,
                'message' => 'Preflight selesai tetapi hasil tidak dapat diserialisasi ke JSON.',
            ], JSON_PRETTY_PRINT);
        }

        fwrite((!empty($result['can_post'])) ? STDOUT : STDERR, $output . PHP_EOL);
        exit(!empty($result['can_post']) ? 0 : 1);
    }

    public function repair_pos_cross_division_commit_lines()
    {
        $cliArgs = $this->parseCliArgs();
        $commitId = (int)($cliArgs['commit_id'] ?? 0);
        $orderId = (int)($cliArgs['order_id'] ?? 0);
        $lineId = (int)($cliArgs['line_id'] ?? 0);
        $limit = max(1, min(200, (int)($cliArgs['limit'] ?? 50)));
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $dryRun = in_array(strtolower((string)($cliArgs['dry_run'] ?? '0')), ['1', 'true', 'yes', 'y'], true);

        $this->load->library('PosOrderStockService');

        $targets = $this->collectPosCrossDivisionCommitTargets($commitId, $orderId, $lineId, $limit);
        if (empty($targets)) {
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Tidak ada kandidat commit POS lintas divisi untuk direpair.',
                'filters' => compact('commitId', 'orderId', 'lineId', 'limit', 'dryRun'),
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            return;
        }

        $results = [];
        foreach ($targets as $target) {
            $targetCommitId = (int)$target['commit_id'];
            $targetOrderId = (int)$target['order_id'];
            $actorEmployeeId = $this->resolvePosRepairActorEmployeeId($targetOrderId, $targetCommitId, $userId);

            $repair = $this->posorderstockservice->repair_cross_division_commit_snapshot($targetCommitId, [
                'line_id' => $lineId,
                'actor_employee_id' => $actorEmployeeId,
                'dry_run' => $dryRun,
                'note' => 'Repair cross-division POS commit line',
            ]);

            $results[] = [
                'commit_id' => $targetCommitId,
                'order_id' => $targetOrderId,
                'ok' => (bool)($repair['ok'] ?? false),
                'message' => (string)($repair['message'] ?? ''),
                'processed' => (int)($repair['processed'] ?? 0),
                'success' => (int)($repair['success'] ?? 0),
                'failed' => (int)($repair['failed'] ?? 0),
                'dry_run' => $dryRun,
                'results' => (array)($repair['results'] ?? []),
                'targets' => (array)($repair['targets'] ?? []),
            ];
        }

        $successCount = 0;
        foreach ($results as $row) {
            if (!empty($row['ok'])) {
                $successCount++;
            }
        }

        fwrite(STDOUT, json_encode([
            'ok' => $successCount === count($results),
            'processed_targets' => count($results),
            'success_targets' => $successCount,
            'failed_targets' => count($results) - $successCount,
            'results' => $results,
        ], JSON_PRETTY_PRINT) . PHP_EOL);
    }

    /**
     * CLI-only correction for the legacy POS void path that created stock
     * returns without an original stock-out. The service performs a strict
     * audit first and refuses any candidate outside the supplied commit list.
     */
    public function repair_unpaired_pos_void_reversal_artifacts()
    {
        $cliArgs = $this->parseCliArgs();
        $commitIds = trim((string)($cliArgs['commit_ids'] ?? ''));
        $apply = in_array(strtolower((string)($cliArgs['apply'] ?? '0')), ['1', 'true', 'yes', 'y'], true);
        $confirm = trim((string)($cliArgs['confirm'] ?? ''));

        if ($apply && $confirm !== 'REPAIR_POS_VOID_ARTIFACTS') {
            fwrite(STDERR, json_encode([
                'ok' => false,
                'message' => 'Apply ditolak. Gunakan confirm REPAIR_POS_VOID_ARTIFACTS setelah memeriksa dry run.',
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            return;
        }

        $this->load->library('PosVoidArtifactRepairService');
        $result = $this->posvoidartifactrepairservice->repair([
            'commit_ids' => $commitIds,
            'dry_run' => !$apply,
        ]);
        $output = json_encode($result, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        fwrite(!empty($result['ok']) ? STDOUT : STDERR, ($output ?: '{"ok":false,"message":"Output repair tidak dapat diserialisasi."}') . PHP_EOL);
    }

    /**
     * Rebuilds the stock projection of one already-VOID adjustment after its
     * reversal has been audited. It does not change the document, movement,
     * or FIFO lot; it only regenerates derived monthly stock rows.
     *
     * Usage:
     * php index.php inventory_tools rebuild_void_stock_adjustment_history adjustment_id 2811 confirm REBUILD_VOID_ADJUSTMENT_HISTORY
     */
    public function rebuild_void_stock_adjustment_history()
    {
        $cliArgs = $this->parseCliArgs();
        $adjustmentId = (int)($cliArgs['adjustment_id'] ?? 0);
        $confirm = trim((string)($cliArgs['confirm'] ?? ''));
        $userId = (int)($cliArgs['user_id'] ?? 0);

        if ($adjustmentId <= 0 || $confirm !== 'REBUILD_VOID_ADJUSTMENT_HISTORY') {
            fwrite(STDERR, json_encode([
                'ok' => false,
                'message' => 'Perintah ditolak. Gunakan adjustment_id valid dan confirm REBUILD_VOID_ADJUSTMENT_HISTORY.',
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            return;
        }

        $result = $this->Purchase_model->rebuild_void_stock_adjustment_history($adjustmentId, $userId);
        $output = json_encode($result, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        fwrite(!empty($result['ok']) ? STDOUT : STDERR, ($output ?: '{"ok":false,"message":"Output rebuild tidak dapat diserialisasi."}') . PHP_EOL);
    }

    public function smoke_test()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $smokeDate = (string)($cliArgs['date'] ?? date('Y-m-d'));

        $this->load->model('Procurement_model');

        $fixtures = $this->resolveSmokeFixtures();
        if (!($fixtures['ok'] ?? false)) {
            fwrite(STDERR, json_encode($fixtures, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }
        $fx = (array)($fixtures['data'] ?? []);

        $cases = [];

        $cases[] = $this->runSmokeCase('po_gudang_received', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO gudang',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt gudang tidak terbentuk untuk PO RECEIVED.'];
            }

            $movementSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            if (!$this->movementSummaryHas($movementSummary, 'WAREHOUSE', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'PO gudang tidak menghasilkan movement WAREHOUSE PURCHASE_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }
            if ($this->movementSummaryHasScope($movementSummary, 'DIVISION')) {
                return ['ok' => false, 'message' => 'PO gudang seharusnya tidak menghasilkan movement DIVISION.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'PO gudang berhasil diposting ke stok gudang.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'destination_type' => (string)($receipt['destination_type'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_division_stock_received', function () use ($fx, $smokeDate, $userId) {
            $type = $this->firstSmokeType($fx['purchase_types'], ['INV_BAR', 'BAR_STOK', 'KITCHEN_STOK', 'INV_KITCHEN']);
            if ($type === null) {
                return ['ok' => false, 'message' => 'Purchase type stok divisi tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO stok divisi',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt stok divisi tidak terbentuk untuk PO RECEIVED.'];
            }

            $movementSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            if (!$this->movementSummaryHas($movementSummary, 'DIVISION', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'PO stok divisi tidak menghasilkan movement DIVISION PURCHASE_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }
            if ($this->movementSummaryHasScope($movementSummary, 'WAREHOUSE')) {
                return ['ok' => false, 'message' => 'PO stok divisi seharusnya tidak menghasilkan movement WAREHOUSE.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'PO stok divisi berhasil diposting ke stok divisi.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'destination_type' => (string)($receipt['destination_type'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_operasional_office_received', function () use ($fx, $smokeDate, $userId) {
            if (empty($fx['purchase_types']['OPERASIONAL_OFFICE'])) {
                return ['ok' => false, 'message' => 'Purchase type OPERASIONAL_OFFICE tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$fx['purchase_types']['OPERASIONAL_OFFICE']['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO operasional office',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt operasional office tidak terbentuk.'];
            }

            $movementSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            if (!$this->movementSummaryHas($movementSummary, 'DIVISION', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'PO operasional office tidak menghasilkan movement DIVISION PURCHASE_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }

            $hasOfficeDestination = false;
            foreach ($movementSummary as $row) {
                if ((string)($row['movement_scope'] ?? '') === 'DIVISION' && (string)($row['destination_type'] ?? '') === 'OFFICE') {
                    $hasOfficeDestination = true;
                    break;
                }
            }
            if (!$hasOfficeDestination) {
                return ['ok' => false, 'message' => 'PO operasional office tidak mendarat ke destination OFFICE.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'PO operasional office berhasil diposting ke stok divisi OFFICE.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'destination_type' => (string)($receipt['destination_type'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_service_paid', function () use ($fx, $smokeDate, $userId) {
            if (empty($fx['purchase_types']['JASA'])) {
                return ['ok' => false, 'message' => 'Purchase type JASA tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$fx['purchase_types']['JASA']['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'payment_account_id' => (int)$fx['account']['id'],
                'status' => 'PAID',
                'notes' => 'Smoke test PO jasa',
            ], [
                [
                    'line_kind' => 'SERVICE',
                    'buy_uom_id' => (int)$fx['uom_id'],
                    'qty_buy' => 1,
                    'content_per_buy' => 1,
                    'conversion_factor_to_content' => 1,
                    'unit_price' => 125000,
                    'service_name' => 'Smoke test jasa',
                ],
            ], $userId, 'CLI_SMOKE');
            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receiptCount = (int)$this->db
                ->from('pur_purchase_receipt')
                ->where('purchase_order_id', $poId)
                ->count_all_results();
            $paymentPlanCount = (int)$this->db
                ->from('pur_purchase_payment_plan')
                ->where('purchase_order_id', $poId)
                ->count_all_results();
            $movementCount = (int)$this->db
                ->from('inv_stock_movement_log')
                ->where('ref_table', 'pur_purchase_order')
                ->where('ref_id', $poId)
                ->count_all_results();

            if ($receiptCount !== 0) {
                return ['ok' => false, 'message' => 'PO jasa tidak boleh membentuk receipt stok.', 'data' => ['receipt_count' => $receiptCount]];
            }
            if ($paymentPlanCount <= 0) {
                return ['ok' => false, 'message' => 'PO jasa PAID tidak membentuk payment plan.'];
            }
            if ($movementCount !== 0) {
                return ['ok' => false, 'message' => 'PO jasa tidak boleh menghasilkan movement stok.', 'data' => ['movement_count' => $movementCount]];
            }

            return [
                'ok' => true,
                'message' => 'PO jasa PAID berhasil tanpa membentuk receipt atau movement stok.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_count' => $receiptCount,
                    'payment_plan_count' => $paymentPlanCount,
                    'movement_count' => $movementCount,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_void_rollback_paid', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $accountId = (int)$fx['account']['id'];
            $create = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'payment_account_id' => $accountId,
                'status' => 'PAID',
                'notes' => 'Smoke test PO void rollback paid',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $poId = (int)($create['data']['purchase_order_id'] ?? 0);
            $paymentPlans = $this->db->from('pur_purchase_payment_plan')->where('purchase_order_id', $poId)->where('status', 'PAID')->get()->result_array();
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (empty($paymentPlans) || !$receipt) {
                return ['ok' => false, 'message' => 'Setup PO PAID tidak lengkap untuk uji VOID rollback.'];
            }

            $voidResult = $this->Purchase_model->update_order_status($poId, 'VOID', $userId, 'CLI_SMOKE');
            if (!($voidResult['ok'] ?? false)) {
                return $voidResult;
            }

            $voidPlanCount = (int)$this->db->from('pur_purchase_payment_plan')->where('purchase_order_id', $poId)->where('status', 'VOID')->count_all_results();
            $accountMutationIn = (int)$this->db
                ->from('fin_account_mutation_log')
                ->where('ref_table', 'pur_purchase_order')
                ->where('ref_id', $poId)
                ->where('mutation_type', 'IN')
                ->count_all_results();
            $receiptAfter = $this->db->from('pur_purchase_receipt')->where('id', (int)$receipt['id'])->limit(1)->get()->row_array();

            if ($voidPlanCount <= 0) {
                return ['ok' => false, 'message' => 'VOID PO PAID tidak me-VOID payment plan.'];
            }
            if ($accountMutationIn <= 0) {
                return ['ok' => false, 'message' => 'VOID PO PAID tidak mengembalikan saldo akun ke mutation IN.'];
            }
            if (strtoupper((string)($receiptAfter['status'] ?? '')) !== 'VOID') {
                return ['ok' => false, 'message' => 'VOID PO PAID tidak me-VOID receipt terkait.'];
            }

            return [
                'ok' => true,
                'message' => 'VOID PO PAID berhasil rollback stok, payment, dan receipt.',
                'data' => [
                    'po_id' => $poId,
                    'void_payment_plan_count' => $voidPlanCount,
                    'account_mutation_in_count' => $accountMutationIn,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_invalid_transition_rejected_to_paid', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $create = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'REJECTED',
                'notes' => 'Smoke test invalid transition rejected to paid',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $poId = (int)($create['data']['purchase_order_id'] ?? 0);
            $transition = $this->Purchase_model->update_order_status($poId, 'PAID', $userId, 'CLI_SMOKE');
            if (($transition['ok'] ?? false) === true) {
                return [
                    'ok' => false,
                    'message' => 'PO REJECTED tidak boleh bisa langsung dipindah ke PAID.',
                    'data' => [
                        'po_id' => $poId,
                        'transition_result' => $transition,
                    ],
                ];
            }

            return [
                'ok' => true,
                'message' => 'Transisi REJECTED ke PAID berhasil ditolak.',
                'data' => [
                    'po_id' => $poId,
                    'transition_result' => $transition,
                ],
            ];
        });

        $failedCases = array_values(array_filter($cases, static function (array $case): bool {
            return !($case['ok'] ?? false);
        }));
        $passedCount = count($cases) - count($failedCases);

        $result = [
            'ok' => empty($failedCases),
            'message' => empty($failedCases) ? 'Smoke test inventory_tools selesai.' : 'Smoke test inventory_tools menemukan kegagalan.',
            'data' => [
                'date' => $smokeDate,
                'case_count' => count($cases),
                'passed_count' => $passedCount,
                'failed_count' => count($failedCases),
                'cases' => $cases,
            ],
        ];

        if (!empty($failedCases)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function normalize_purchase_catalog_profile_keys()
    {
        $this->run_purchase_catalog_profile_key_normalize_cli(false);
    }

    public function audit_purchase_catalog_expiry_phase1()
    {
        $this->run_purchase_catalog_profile_key_normalize_cli(true);
    }

    public function normalize_purchase_catalog_expiry_phase1()
    {
        $this->run_purchase_catalog_profile_key_normalize_cli(false);
    }

    public function reconcile_purchase_catalog_exact_profiles()
    {
        $cliArgs = $this->parseCliArgs();
        $limit = (int)($cliArgs['limit'] ?? 10000);
        $dryRun = !isset($cliArgs['dry_run']) || !in_array(strtolower(trim((string)($cliArgs['dry_run'] ?? '1'))), ['0', 'false', 'no'], true);

        $filters = [
            'dry_run' => $dryRun,
            'limit' => $limit,
        ];

        $catalogIdsRaw = trim((string)($cliArgs['catalog_ids'] ?? ''));
        if ($catalogIdsRaw !== '') {
            $catalogIds = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', $catalogIdsRaw) ?: []), static function ($id) {
                return $id > 0;
            }));
            if (!empty($catalogIds)) {
                $filters['catalog_ids'] = $catalogIds;
            }
        }

        $profileKeysRaw = trim((string)($cliArgs['profile_keys'] ?? ''));
        if ($profileKeysRaw !== '') {
            $profileKeys = array_values(array_filter(array_map('trim', explode(',', $profileKeysRaw)), static function ($value) {
                return $value !== '';
            }));
            if (!empty($profileKeys)) {
                $filters['profile_keys'] = $profileKeys;
            }
        }

        $result = $this->Purchase_model->reconcilePurchaseCatalogExactProfiles($filters, true);
        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function post_core_warehouse_opening_stage()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $snapshotMonth = trim((string)($cliArgs['snapshot_month'] ?? date('Y-m-01')));
        if ($snapshotMonth === '') {
            $snapshotMonth = date('Y-m-01');
        }
        $dryRun = !isset($cliArgs['dry_run']) || !in_array(strtolower(trim((string)($cliArgs['dry_run'] ?? '1'))), ['0', 'false', 'no'], true);

        $groups = $this->load_core_warehouse_opening_stage_groups($snapshotMonth);
        if (empty($groups)) {
            $result = [
                'ok' => true,
                'message' => 'Tidak ada row staging opening gudang positif untuk diposting.',
                'data' => [
                    'snapshot_month' => $snapshotMonth,
                    'group_count' => 0,
                    'stage_row_count' => 0,
                    'movement_count' => 0,
                    'snapshot_count' => 0,
                    'samples' => [],
                ],
            ];
            fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(0);
        }

        if ($dryRun) {
            $result = [
                'ok' => true,
                'message' => 'Dry-run posting opening staging gudang selesai.',
                'data' => [
                    'snapshot_month' => $snapshotMonth,
                    'group_count' => count($groups),
                    'stage_row_count' => array_sum(array_map(static function (array $row): int {
                        return (int)($row['stage_row_count'] ?? 0);
                    }, $groups)),
                    'samples' => array_slice($groups, 0, 10),
                ],
            ];
            fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(0);
        }

        $purgedSnapshotIds = $this->purge_legacy_core_warehouse_opening_snapshots($snapshotMonth);
        $posted = [];

        foreach ($groups as $group) {
            $payload = [
                'stock_scope' => 'WAREHOUSE',
                'snapshot_month' => (string)$group['snapshot_month'],
                'item_id' => (int)$group['item_id'],
                'material_id' => (int)$group['material_id'],
                'buy_uom_id' => (int)$group['buy_uom_id'],
                'content_uom_id' => (int)$group['content_uom_id'],
                'profile_name' => (string)$group['profile_name'],
                'profile_brand' => $group['profile_brand'] !== null ? (string)$group['profile_brand'] : null,
                'profile_description' => $group['profile_description'] !== null ? (string)$group['profile_description'] : null,
                'profile_content_per_buy' => (float)$group['profile_content_per_buy'],
                'opening_qty_buy' => (float)$group['opening_qty_buy'],
                'opening_qty_content' => (float)$group['opening_qty_content'],
                'opening_avg_cost_per_content' => (float)$group['opening_avg_cost_per_content'],
                'replace_mode' => 1,
                'notes' => 'Core warehouse opening repost from staging. stage_ids=' . (string)$group['stage_ids'] . '; unit_price_buy=' . (string)$group['unit_price_buy'],
            ];

            $result = $this->Purchase_model->store_warehouse_opening_and_post($payload, $userId, 'CLI_CORE_STAGE');
            if (!($result['ok'] ?? false)) {
                fwrite(STDERR, json_encode([
                    'ok' => false,
                    'message' => 'Gagal posting stage_ids=' . (string)$group['stage_ids'],
                    'payload' => $payload,
                    'result' => $result,
                ], JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $data = (array)($result['data'] ?? []);
            $profileKey = trim((string)($data['profile_key'] ?? ''));
            $snapshotId = (int)($data['snapshot_id'] ?? 0);
            $movementNo = trim((string)($data['movement_no'] ?? ''));

            $stageIds = array_values(array_filter(array_map('intval', explode(',', (string)$group['stage_ids'])), static function ($id) {
                return $id > 0;
            }));
            if (!empty($stageIds)) {
                $this->db
                    ->where_in('id', $stageIds)
                    ->update('stg_core_inventory_warehouse_opening', [
                        'matched_finance_profile_key' => $profileKey !== '' ? $profileKey : null,
                        'matched_profile_source' => 'PRICE_AWARE_MODEL_POST',
                        'mapping_status' => 'POSTED_PRICE_AWARE',
                        'mapping_notes' => 'Diposting ulang via Purchase_model::store_warehouse_opening_and_post dengan exact profile by price.',
                        'import_status' => 'POSTED_MODEL',
                        'import_notes' => 'Snapshot_id=' . $snapshotId . '; movement_no=' . $movementNo,
                        'imported_opening_id' => $snapshotId > 0 ? $snapshotId : null,
                    ]);
            }

            $posted[] = [
                'stage_ids' => (string)$group['stage_ids'],
                'profile_name' => (string)$group['profile_name'],
                'unit_price_buy' => (float)$group['unit_price_buy'],
                'profile_key' => $profileKey,
                'snapshot_id' => $snapshotId,
                'movement_no' => $movementNo,
                'opening_qty_buy' => (float)$group['opening_qty_buy'],
                'opening_qty_content' => (float)$group['opening_qty_content'],
            ];
        }

        $movementCount = 0;
        if (!empty($posted)) {
            $snapshotIds = array_values(array_filter(array_map(static function (array $row): int {
                return (int)($row['snapshot_id'] ?? 0);
            }, $posted), static function ($id) {
                return $id > 0;
            }));
            if (!empty($snapshotIds) && $this->db->table_exists('inv_stock_movement_log')) {
                $movementCount = (int)$this->db
                    ->from('inv_stock_movement_log')
                    ->where('ref_table', 'inv_warehouse_stock_opening_snapshot')
                    ->where_in('ref_id', $snapshotIds)
                    ->count_all_results();
            }
        }

        $result = [
            'ok' => true,
            'message' => 'Posting opening staging gudang via model selesai.',
            'data' => [
                'snapshot_month' => $snapshotMonth,
                'purged_legacy_snapshot_ids' => $purgedSnapshotIds,
                'group_count' => count($posted),
                'stage_row_count' => array_sum(array_map(static function (array $row): int {
                    return count(array_filter(array_map('intval', explode(',', (string)($row['stage_ids'] ?? '')))));
                }, $posted)),
                'movement_count' => $movementCount,
                'samples' => array_slice($posted, 0, 15),
            ],
        ];
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function post_core_material_division_opening()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $snapshotMonth = trim((string)($cliArgs['snapshot_month'] ?? date('Y-m-01')));
        if ($snapshotMonth === '') {
            $snapshotMonth = date('Y-m-01');
        }
        $dryRun = !isset($cliArgs['dry_run']) || !in_array(strtolower(trim((string)($cliArgs['dry_run'] ?? '1'))), ['0', 'false', 'no'], true);

        $groups = $this->load_core_material_division_opening_groups($snapshotMonth);
        if (empty($groups)) {
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Tidak ada saldo bahan baku core positif untuk diposting.',
                'data' => [
                    'snapshot_month' => $snapshotMonth,
                    'group_count' => 0,
                ],
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            exit(0);
        }

        $processed = [];
        $skipped = [];
        $correctionLotCount = 0;
        $trimmedLotCount = 0;

        foreach ($groups as $group) {
            $groupId = (int)($group['source_stock_id'] ?? 0);
            if ((int)($group['division_id'] ?? 0) <= 0 || (int)($group['item_id'] ?? 0) <= 0 || (int)($group['material_id'] ?? 0) <= 0 || (int)($group['buy_uom_id'] ?? 0) <= 0 || (int)($group['content_uom_id'] ?? 0) <= 0) {
                $skipped[] = [
                    'source_stock_id' => $groupId,
                    'profile_name' => (string)($group['profile_name'] ?? ''),
                    'message' => 'Mapping division/item/material/uom belum lengkap.',
                ];
                continue;
            }

            $lots = $this->load_core_material_division_opening_lots($group);
            $plan = $this->plan_opening_lot_rows(
                round((float)($group['opening_qty_content'] ?? 0), 4),
                round(max(0.000001, (float)($group['profile_content_per_buy'] ?? 1)), 6),
                $lots,
                'MATCORR-' . $snapshotMonth . '-' . $groupId,
                round((float)($group['opening_avg_cost_per_content'] ?? 0), 6),
                $snapshotMonth,
                'Correction lot bahan baku dari saldo agregat core'
            );
            $plannedLots = (array)($plan['planned_lots'] ?? []);
            $openingQtyBuy = round((float)($plan['planned_qty_buy'] ?? 0), 4);
            if ($openingQtyBuy <= 0) {
                $openingQtyBuy = round((float)($group['opening_qty_content'] ?? 0) / max(0.000001, (float)($group['profile_content_per_buy'] ?? 1)), 4);
            }
            $correctionLotCount += count(array_filter($plannedLots, static function (array $lot): bool {
                return !empty($lot['is_correction']);
            }));
            $trimmedLotCount += (int)($plan['trimmed_lot_count'] ?? 0);

            if ($dryRun) {
                $processed[] = [
                    'source_stock_id' => $groupId,
                    'profile_name' => (string)($group['profile_name'] ?? ''),
                    'division_id' => (int)($group['division_id'] ?? 0),
                    'destination_type' => (string)($group['destination_type'] ?? ''),
                    'opening_qty_buy' => $openingQtyBuy,
                    'opening_qty_content' => round((float)($group['opening_qty_content'] ?? 0), 4),
                    'source_lot_count' => count($lots),
                    'planned_lot_count' => count($plannedLots),
                    'trimmed_qty_content' => round((float)($plan['trimmed_qty_content'] ?? 0), 4),
                    'correction_qty_content' => round((float)($plan['correction_qty_content'] ?? 0), 4),
                ];
                continue;
            }

            $result = $this->Purchase_model->store_warehouse_opening_and_post([
                'stock_scope' => 'DIVISION',
                'snapshot_month' => $snapshotMonth,
                'division_id' => (int)$group['division_id'],
                'destination_type' => (string)$group['destination_type'],
                'item_id' => (int)$group['item_id'],
                'material_id' => (int)$group['material_id'],
                'buy_uom_id' => (int)$group['buy_uom_id'],
                'content_uom_id' => (int)$group['content_uom_id'],
                'profile_name' => (string)$group['profile_name'],
                'profile_brand' => $group['profile_brand'] !== null ? (string)$group['profile_brand'] : null,
                'profile_description' => $group['profile_description'] !== null ? (string)$group['profile_description'] : null,
                'profile_content_per_buy' => (float)$group['profile_content_per_buy'],
                'opening_qty_buy' => $openingQtyBuy,
                'opening_qty_content' => round((float)$group['opening_qty_content'], 4),
                'opening_avg_cost_per_content' => round((float)$group['opening_avg_cost_per_content'], 6),
                'replace_mode' => 1,
                'notes' => 'Core material opening repost. source_stock_id=' . $groupId . '; source_profile_key=' . (string)($group['source_profile_key'] ?? '-'),
            ], $userId, 'CLI_CORE_MATERIAL');
            if (!($result['ok'] ?? false)) {
                fwrite(STDERR, json_encode([
                    'ok' => false,
                    'message' => 'Gagal posting bahan baku source_stock_id=' . $groupId,
                    'group' => $group,
                    'result' => $result,
                ], JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $data = (array)($result['data'] ?? []);
            $snapshotId = (int)($data['snapshot_id'] ?? 0);
            $profileKey = trim((string)($data['profile_key'] ?? ''));
            $lotSeed = $this->seed_material_opening_lots($group, $snapshotId, $profileKey, $plannedLots);
            if (!($lotSeed['ok'] ?? false)) {
                fwrite(STDERR, json_encode([
                    'ok' => false,
                    'message' => 'Gagal seed lot bahan baku source_stock_id=' . $groupId,
                    'group' => $group,
                    'snapshot_id' => $snapshotId,
                    'result' => $lotSeed,
                ], JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $processed[] = [
                'source_stock_id' => $groupId,
                'profile_name' => (string)($group['profile_name'] ?? ''),
                'snapshot_id' => $snapshotId,
                'profile_key' => $profileKey,
                'planned_lot_count' => count($plannedLots),
                'trimmed_qty_content' => round((float)($plan['trimmed_qty_content'] ?? 0), 4),
                'correction_qty_content' => round((float)($plan['correction_qty_content'] ?? 0), 4),
            ];
        }

        fwrite(STDOUT, json_encode([
            'ok' => true,
            'message' => $dryRun ? 'Dry-run opening bahan baku core selesai.' : 'Opening bahan baku core selesai diposting.',
            'data' => [
                'snapshot_month' => $snapshotMonth,
                'group_count' => count($groups),
                'processed_count' => count($processed),
                'skipped_count' => count($skipped),
                'correction_lot_count' => $correctionLotCount,
                'trimmed_lot_count' => $trimmedLotCount,
                'processed' => array_slice($processed, 0, 30),
                'skipped' => array_slice($skipped, 0, 20),
            ],
        ], JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function post_core_component_opening()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $snapshotMonth = trim((string)($cliArgs['snapshot_month'] ?? date('Y-m-01')));
        if ($snapshotMonth === '') {
            $snapshotMonth = date('Y-m-01');
        }
        $dryRun = !isset($cliArgs['dry_run']) || !in_array(strtolower(trim((string)($cliArgs['dry_run'] ?? '1'))), ['0', 'false', 'no'], true);

        $groups = $this->load_core_component_opening_groups($snapshotMonth);
        if (empty($groups)) {
            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Tidak ada saldo component core positif untuk diposting.',
                'data' => [
                    'snapshot_month' => $snapshotMonth,
                    'group_count' => 0,
                ],
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            exit(0);
        }

        $this->load->library('ComponentStockWriter');

        $docPlans = [];
        $skipped = [];
        $correctionLotCount = 0;
        $trimmedLotCount = 0;

        foreach ($groups as $group) {
            if ((int)($group['division_id'] ?? 0) <= 0 || (int)($group['component_id'] ?? 0) <= 0 || (int)($group['uom_id'] ?? 0) <= 0) {
                $skipped[] = [
                    'source_stock_id' => (int)($group['source_stock_id'] ?? 0),
                    'component_name' => (string)($group['component_name'] ?? ''),
                    'message' => 'Mapping division/component/uom belum lengkap.',
                ];
                continue;
            }

            $lots = $this->load_core_component_opening_lots($group);
            $plan = $this->plan_opening_lot_rows(
                round((float)($group['opening_qty'] ?? 0), 4),
                1.0,
                $lots,
                'CMPCORR-' . $snapshotMonth . '-' . (int)($group['source_stock_id'] ?? 0),
                round((float)($group['avg_cost'] ?? 0), 6),
                $snapshotMonth,
                'Correction lot component dari saldo agregat core'
            );
            $plannedLots = (array)($plan['planned_lots'] ?? []);
            $correctionLotCount += count(array_filter($plannedLots, static function (array $lot): bool {
                return !empty($lot['is_correction']);
            }));
            $trimmedLotCount += (int)($plan['trimmed_lot_count'] ?? 0);

            $docKey = (string)$group['location_type'] . '|' . (int)$group['division_id'];
            if (!isset($docPlans[$docKey])) {
                $docPlans[$docKey] = [
                    'header' => [
                        'location_type' => (string)$group['location_type'],
                        'division_id' => (int)$group['division_id'],
                    ],
                    'lines' => [],
                ];
            }

            foreach ($plannedLots as $plannedLot) {
                $docPlans[$docKey]['lines'][] = [
                    'component_id' => (int)$group['component_id'],
                    'uom_id' => (int)$group['uom_id'],
                    'opening_qty' => round((float)($plannedLot['qty_content'] ?? 0), 4),
                    'unit_cost' => round((float)($plannedLot['unit_cost'] ?? 0), 6),
                    'note' => trim((string)($plannedLot['note'] ?? '')),
                    'lot_no' => (string)($plannedLot['lot_no'] ?? ''),
                    'received_date' => (string)($plannedLot['received_date'] ?? $snapshotMonth),
                ];
            }
        }

        if ($dryRun) {
            $preview = [];
            foreach ($docPlans as $docKey => $docPlan) {
                $preview[] = [
                    'doc_key' => $docKey,
                    'location_type' => (string)($docPlan['header']['location_type'] ?? ''),
                    'division_id' => (int)($docPlan['header']['division_id'] ?? 0),
                    'line_count' => count((array)($docPlan['lines'] ?? [])),
                ];
            }

            fwrite(STDOUT, json_encode([
                'ok' => true,
                'message' => 'Dry-run opening component core selesai.',
                'data' => [
                    'snapshot_month' => $snapshotMonth,
                    'group_count' => count($groups),
                    'document_count' => count($docPlans),
                    'skipped_count' => count($skipped),
                    'correction_lot_count' => $correctionLotCount,
                    'trimmed_lot_count' => $trimmedLotCount,
                    'documents' => $preview,
                    'skipped' => array_slice($skipped, 0, 20),
                ],
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            exit(0);
        }

        $postedDocs = [];
        foreach ($docPlans as $docPlan) {
            $locationType = (string)($docPlan['header']['location_type'] ?? '');
            $divisionId = (int)($docPlan['header']['division_id'] ?? 0);
            $cleanup = $this->cleanup_component_opening_migration_doc($snapshotMonth, $locationType, $divisionId, $userId);
            if (!($cleanup['ok'] ?? false)) {
                fwrite(STDERR, json_encode($cleanup, JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $openingNo = 'ICO-MIG-' . date('Ym', strtotime($snapshotMonth)) . '-' . $locationType . '-' . $divisionId . '-' . date('His');
            $headerInsert = [
                'opening_no' => $openingNo,
                'opening_date' => $snapshotMonth,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'status' => 'DRAFT',
                'notes' => 'Core component opening migration. snapshot_month=' . $snapshotMonth,
                'created_by' => $userId > 0 ? $userId : null,
            ];
            $this->db->insert('inv_component_opening', $headerInsert);
            $openingId = (int)$this->db->insert_id();
            if ($openingId <= 0) {
                fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Gagal membuat header opening component migrasi.'], JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $lineNo = 1;
            $customLines = [];
            foreach ($docPlan['lines'] as $line) {
                $lineInsert = [
                    'opening_id' => $openingId,
                    'line_no' => $lineNo++,
                    'component_id' => (int)$line['component_id'],
                    'uom_id' => (int)$line['uom_id'],
                    'opening_qty' => round((float)$line['opening_qty'], 4),
                    'unit_cost' => round((float)$line['unit_cost'], 6),
                    'total_value' => round((float)$line['opening_qty'] * (float)$line['unit_cost'], 2),
                    'note' => $line['note'] !== '' ? (string)$line['note'] : null,
                ];
                $this->db->insert('inv_component_opening_line', $lineInsert);
                $lineId = (int)$this->db->insert_id();
                if ($lineId <= 0) {
                    fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Gagal membuat line opening component migrasi.', 'opening_id' => $openingId], JSON_PRETTY_PRINT) . PHP_EOL);
                    exit(1);
                }

                $customLines[] = [
                    'id' => $lineId,
                    'component_id' => (int)$line['component_id'],
                    'uom_id' => (int)$line['uom_id'],
                    'opening_qty' => round((float)$line['opening_qty'], 4),
                    'qty' => round((float)$line['opening_qty'], 4),
                    'unit_cost' => round((float)$line['unit_cost'], 6),
                    'note' => (string)($line['note'] ?? ''),
                    'lot_no' => (string)($line['lot_no'] ?? ''),
                    'received_date' => (string)($line['received_date'] ?? $snapshotMonth),
                    'source_line_id' => $lineId,
                ];
            }

            $header = $this->Production_model->get_component_opening($openingId);
            if (!$header) {
                fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Header opening component migrasi tidak dapat dibaca ulang.', 'opening_id' => $openingId], JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $post = $this->componentstockwriter->post_opening($header, $customLines, $userId);
            if (!($post['ok'] ?? false)) {
                fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Posting opening component migrasi gagal.', 'opening_id' => $openingId, 'result' => $post], JSON_PRETTY_PRINT) . PHP_EOL);
                exit(1);
            }

            $this->db->where('id', $openingId)->update('inv_component_opening', [
                'status' => 'POSTED',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId > 0 ? $userId : null,
            ]);

            $postedDocs[] = [
                'opening_id' => $openingId,
                'opening_no' => $openingNo,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'line_count' => count($customLines),
            ];
        }

        fwrite(STDOUT, json_encode([
            'ok' => true,
            'message' => 'Opening component core selesai diposting.',
            'data' => [
                'snapshot_month' => $snapshotMonth,
                'group_count' => count($groups),
                'document_count' => count($postedDocs),
                'skipped_count' => count($skipped),
                'correction_lot_count' => $correctionLotCount,
                'trimmed_lot_count' => $trimmedLotCount,
                'documents' => $postedDocs,
                'skipped' => array_slice($skipped, 0, 20),
            ],
        ], JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function rebuild_purchase_impact()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $dryRun = !isset($cliArgs['dry_run']) || !in_array(strtolower(trim((string)($cliArgs['dry_run'] ?? '1'))), ['0', 'false', 'no'], true);
        $payload = [
            'scope' => strtoupper(trim((string)($cliArgs['scope'] ?? 'TRANSACTION'))),
            'purchase_order_id' => (int)($cliArgs['purchase_order_id'] ?? 0),
            'po_no' => trim((string)($cliArgs['po_no'] ?? '')),
            'item_id' => (int)($cliArgs['item_id'] ?? 0),
            'material_id' => (int)($cliArgs['material_id'] ?? 0),
            'date_from' => trim((string)($cliArgs['date_from'] ?? '')),
            'date_to' => trim((string)($cliArgs['date_to'] ?? '')),
            'limit' => (int)($cliArgs['limit'] ?? 300),
            'dry_run' => $dryRun,
        ];

        $statusesRaw = trim((string)($cliArgs['statuses'] ?? ''));
        if ($statusesRaw !== '') {
            $payload['statuses'] = array_values(array_filter(array_map(static function ($value) {
                return strtoupper(trim((string)$value));
            }, explode(',', $statusesRaw))));
        }

        $result = $this->Purchase_model->rebuild_purchase_impacts($payload, $userId, 'CLI_REBUILD_IMPACT');
        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function repair_posted_receipt_profile_keys()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $purchaseOrderId = (int)($cliArgs['purchase_order_id'] ?? 0);
        $poNo = trim((string)($cliArgs['po_no'] ?? ''));

        if ($purchaseOrderId <= 0 && $poNo !== '') {
            $order = $this->db
                ->select('id')
                ->from('pur_purchase_order')
                ->where('po_no', $poNo)
                ->limit(1)
                ->get()
                ->row_array();
            $purchaseOrderId = (int)($order['id'] ?? 0);
        }

        if ($purchaseOrderId <= 0) {
            $result = [
                'ok' => false,
                'message' => 'Parameter purchase_order_id atau po_no wajib diisi.',
            ];
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        $result = $this->Purchase_model->repairPostedReceiptProfileKeys($purchaseOrderId, $userId);
        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    private function run_purchase_catalog_profile_key_normalize_cli(bool $forceDryRun = false): void
    {
        $cliArgs = $this->parseCliArgs();
        $catalogId = (int)($cliArgs['catalog_id'] ?? 0);
        $profileKey = trim((string)($cliArgs['profile_key'] ?? ''));
        $limit = (int)($cliArgs['limit'] ?? 10000);
        $dryRun = $forceDryRun || !isset($cliArgs['dry_run']) || !in_array(strtolower(trim((string)($cliArgs['dry_run'] ?? '1'))), ['0', 'false', 'no'], true);

        $filters = [
            'dry_run' => $dryRun,
            'limit' => $limit,
        ];
        if ($catalogId > 0) {
            $filters['catalog_ids'] = [$catalogId];
        }
        if ($profileKey !== '') {
            $filters['profile_keys'] = [$profileKey];
        }

        $result = $this->Purchase_model->normalizePurchaseCatalogProfileKeys($filters, true);
        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    private function load_core_warehouse_opening_stage_groups(string $snapshotMonth): array
    {
        $sql = "
            SELECT
              s.snapshot_month,
              'ITEM' AS stock_domain,
              s.item_id,
              s.material_id,
              s.buy_uom_id,
              s.content_uom_id,
              MAX(s.profile_name) AS profile_name,
              MAX(s.profile_brand) AS profile_brand,
              MAX(s.profile_description) AS profile_description,
              ROUND(MAX(s.profile_content_per_buy), 6) AS profile_content_per_buy,
              ROUND(CASE WHEN SUM(s.opening_qty_buy) > 0 THEN SUM(s.opening_total_value) / SUM(s.opening_qty_buy) ELSE 0 END, 2) AS unit_price_buy,
              ROUND(SUM(s.opening_qty_buy), 4) AS opening_qty_buy,
              ROUND(SUM(s.opening_qty_content), 4) AS opening_qty_content,
              ROUND(CASE WHEN SUM(s.opening_qty_content) > 0 THEN SUM(s.opening_total_value) / SUM(s.opening_qty_content) ELSE 0 END, 6) AS opening_avg_cost_per_content,
              ROUND(SUM(s.opening_total_value), 2) AS opening_total_value,
              COUNT(*) AS stage_row_count,
              GROUP_CONCAT(s.id ORDER BY s.id SEPARATOR ',') AS stage_ids
            FROM stg_core_inventory_warehouse_opening s
            WHERE s.snapshot_month = ?
              AND COALESCE(s.source_qty_on_hand, 0) > 0
            GROUP BY
              s.snapshot_month,
              s.item_id,
              s.material_id,
              s.buy_uom_id,
              s.content_uom_id,
              UPPER(TRIM(COALESCE(s.profile_name, ''))),
              UPPER(TRIM(COALESCE(s.profile_brand, ''))),
              UPPER(TRIM(COALESCE(s.profile_description, ''))),
              ROUND(COALESCE(s.profile_content_per_buy, 0), 6),
              ROUND(CASE WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy ELSE 0 END, 2)
            ORDER BY profile_name ASC, profile_brand ASC, MIN(s.id) ASC
        ";

        return $this->db->query($sql, [$snapshotMonth])->result_array();
    }

        private function load_core_material_division_opening_groups(string $snapshotMonth): array
        {
                $sql = "
                        SELECT
                            s.id AS source_stock_id,
                            ? AS snapshot_month,
                            CASE
                                WHEN s.destination_type IN ('BAR', 'BAR_EVENT') THEN d_bar.id
                                WHEN s.destination_type IN ('KITCHEN', 'KITCHEN_EVENT') THEN d_kitchen.id
                                ELSE NULL
                            END AS division_id,
                            s.destination_type,
                            s.destination_division_id AS source_destination_division_id,
                            s.material_id AS source_material_id,
                            s.uom_id AS source_uom_id,
                            NULLIF(TRIM(s.profile_key), '') AS source_profile_key,
                            tm.id AS material_id,
                            ti.item_id AS item_id,
                            tbu.id AS buy_uom_id,
                            COALESCE(tcu.id, tbu.id) AS content_uom_id,
                            COALESCE(NULLIF(TRIM(sm.material_name), ''), NULLIF(TRIM(tm.material_name), '')) AS profile_name,
                            NULLIF(TRIM(s.brand_name), '') AS profile_brand,
                            NULLIF(TRIM(COALESCE(s.description_snapshot, s.packaging)), '') AS profile_description,
                            ROUND(GREATEST(COALESCE(s.isi_per_pack, s.content_qty, 1), 0.000001), 6) AS profile_content_per_buy,
                            ROUND(COALESCE(s.qty_on_hand, 0), 4) AS opening_qty_content,
                            ROUND(COALESCE(s.avg_cost, 0), 6) AS opening_avg_cost_per_content,
                            su.code AS source_uom_code
                        FROM core.rsp_material_stock s
                        INNER JOIN core.m_material sm
                            ON sm.id = s.material_id
                        LEFT JOIN core.m_uom su
                            ON su.id = s.uom_id
                        INNER JOIN mst_material tm
                            ON tm.material_code = sm.material_code
                        LEFT JOIN (
                            SELECT material_id, MIN(id) AS item_id
                            FROM mst_item
                            WHERE material_id IS NOT NULL
                            GROUP BY material_id
                        ) ti
                            ON ti.material_id = tm.id
                        LEFT JOIN mst_uom tbu
                            ON tbu.code = su.code
                        LEFT JOIN core.m_uom scu
                            ON scu.id = COALESCE(s.content_uom_id, s.uom_id)
                        LEFT JOIN mst_uom tcu
                            ON tcu.code = scu.code
                        LEFT JOIN mst_operational_division d_bar
                            ON d_bar.code = 'BAR'
                        LEFT JOIN mst_operational_division d_kitchen
                            ON d_kitchen.code = 'KITCHEN'
                        WHERE COALESCE(s.qty_on_hand, 0) > 0
                        ORDER BY s.destination_type ASC, tm.id ASC, s.id ASC
                ";

                return $this->db->query($sql, [$snapshotMonth])->result_array();
        }

        private function load_core_material_division_opening_lots(array $group): array
        {
                $sql = "
                        SELECT
                            l.id AS source_line_id,
                            l.lot_no,
                            l.received_date,
                            ROUND(COALESCE(l.qty_remaining, 0), 4) AS qty_content,
                            ROUND(COALESCE(l.qty_pack, 0), 4) AS qty_buy,
                            ROUND(COALESCE(l.unit_cost, 0), 6) AS unit_cost,
                            CONCAT('Core lot ', COALESCE(l.lot_no, '#'), ' dari rsp_material_stock_lot') AS note
                        FROM core.rsp_material_stock_lot l
                        WHERE l.destination_type = ?
                            AND l.destination_division_id <=> ?
                            AND l.material_id = ?
                            AND l.uom_id = ?
                            AND COALESCE(l.qty_remaining, 0) > 0
                            AND NULLIF(TRIM(COALESCE(l.brand_name, '')), '') <=> ?
                            AND NULLIF(TRIM(COALESCE(l.description_snapshot, l.packaging)), '') <=> ?
                            AND ROUND(GREATEST(COALESCE(l.isi_per_pack, l.content_qty, 1), 0.000001), 6) = ?
                        ORDER BY l.received_date ASC, l.id ASC
                ";

                return $this->db->query($sql, [
                        $group['destination_type'] ?? null,
                        $group['source_destination_division_id'] ?? null,
                        $group['source_material_id'] ?? null,
                        $group['source_uom_id'] ?? null,
                        ($group['profile_brand'] ?? null) !== '' ? $group['profile_brand'] : null,
                        ($group['profile_description'] ?? null) !== '' ? $group['profile_description'] : null,
                        round(max(0.000001, (float)($group['profile_content_per_buy'] ?? 1)), 6),
                ])->result_array();
        }

        private function seed_material_opening_lots(array $group, int $snapshotId, string $profileKey, array $plannedLots): array
        {
                if ($snapshotId <= 0) {
                        return ['ok' => false, 'message' => 'Snapshot ID bahan baku tidak valid.'];
                }

                $this->load->library('MaterialFifoManager');
                $ready = $this->materialfifomanager->ensureReady();
                if (!($ready['ok'] ?? false)) {
                        return $ready;
                }

                $rollback = $this->materialfifomanager->rollbackReceiptInboundLotsBySource('inv_division_stock_opening_snapshot', $snapshotId, null);
                if (!($rollback['ok'] ?? false)) {
                        return $rollback;
                }

                foreach ($plannedLots as $plannedLot) {
                        $qtyContent = round((float)($plannedLot['qty_content'] ?? 0), 4);
                        if ($qtyContent <= 0) {
                                continue;
                        }

                        $register = $this->materialfifomanager->registerReceiptInboundLot([
                                'location_scope' => 'DIVISION',
                                'division_id' => (int)($group['division_id'] ?? 0),
                                'destination_type' => (string)($group['destination_type'] ?? 'OTHER'),
                                'item_id' => (int)($group['item_id'] ?? 0),
                                'material_id' => (int)($group['material_id'] ?? 0),
                                'buy_uom_id' => (int)($group['buy_uom_id'] ?? 0),
                                'content_uom_id' => (int)($group['content_uom_id'] ?? 0),
                                'profile_key' => $profileKey !== '' ? $profileKey : null,
                                'qty_content_in' => $qtyContent,
                                'unit_cost' => round((float)($plannedLot['unit_cost'] ?? 0), 6),
                                'lot_no' => (string)($plannedLot['lot_no'] ?? ''),
                                'receipt_date' => (string)($plannedLot['received_date'] ?? ($group['snapshot_month'] ?? date('Y-m-01'))),
                                'source_table' => 'inv_division_stock_opening_snapshot',
                                'source_id' => $snapshotId,
                                'source_line_id' => !empty($plannedLot['source_line_id']) ? (int)$plannedLot['source_line_id'] : null,
                        ]);
                        if (!($register['ok'] ?? false)) {
                                return $register;
                        }
                }

                return ['ok' => true];
        }

        private function load_core_component_opening_groups(string $snapshotMonth): array
        {
                $sql = "
                        SELECT
                            s.id AS source_stock_id,
                            ? AS snapshot_month,
                            s.location_type,
                            CASE
                                WHEN s.location_type IN ('BAR', 'BAR_EVENT') THEN d_bar.id
                                WHEN s.location_type IN ('KITCHEN', 'KITCHEN_EVENT') THEN d_kitchen.id
                                ELSE NULL
                            END AS division_id,
                            s.division_id AS source_division_id,
                            s.component_id AS source_component_id,
                            s.uom_id AS source_uom_id,
                            c.id AS component_id,
                            u.id AS uom_id,
                            c.component_name,
                            ROUND(COALESCE(s.qty_on_hand, 0), 4) AS opening_qty,
                            ROUND(COALESCE(s.avg_cost, 0), 6) AS avg_cost
                        FROM core.prd_component_stock s
                        INNER JOIN core.prd_component sc
                            ON sc.id = s.component_id
                        INNER JOIN core.m_uom su
                            ON su.id = s.uom_id
                        INNER JOIN mst_component c
                            ON c.component_code = sc.component_code
                        INNER JOIN mst_uom u
                            ON u.code = su.code
                        LEFT JOIN mst_operational_division d_bar
                            ON d_bar.code = 'BAR'
                        LEFT JOIN mst_operational_division d_kitchen
                            ON d_kitchen.code = 'KITCHEN'
                        WHERE COALESCE(s.qty_on_hand, 0) > 0
                        ORDER BY s.location_type ASC, c.id ASC, s.id ASC
                ";

                return $this->db->query($sql, [$snapshotMonth])->result_array();
        }

        private function load_core_component_opening_lots(array $group): array
        {
                $sql = "
                        SELECT
                            l.id AS source_line_id,
                            l.lot_no,
                            l.received_date,
                            ROUND(COALESCE(l.qty_remaining, 0), 4) AS qty_content,
                            ROUND(COALESCE(l.qty_remaining, 0), 4) AS qty_buy,
                            ROUND(COALESCE(l.unit_cost, 0), 6) AS unit_cost,
                            CONCAT('Core lot ', COALESCE(l.lot_no, '#'), ' dari prd_component_stock_lot') AS note
                        FROM core.prd_component_stock_lot l
                        WHERE l.location_type = ?
                            AND l.division_id <=> ?
                            AND l.component_id = ?
                            AND l.uom_id = ?
                            AND COALESCE(l.qty_remaining, 0) > 0
                        ORDER BY l.received_date ASC, l.id ASC
                ";

                return $this->db->query($sql, [
                        $group['location_type'] ?? null,
                        $group['source_division_id'] ?? null,
                        $group['source_component_id'] ?? null,
                        $group['source_uom_id'] ?? null,
                ])->result_array();
        }

        private function cleanup_component_opening_migration_doc(string $snapshotMonth, string $locationType, int $divisionId, int $userId): array
        {
                $monthStart = date('Y-m-01', strtotime($snapshotMonth));
                $monthEnd = date('Y-m-t', strtotime($snapshotMonth));
                $existing = $this->db
                        ->select('id, status, notes')
                        ->from('inv_component_opening')
                        ->where('location_type', $locationType)
                        ->where('division_id', $divisionId)
                        ->where('opening_date >=', $monthStart)
                        ->where('opening_date <=', $monthEnd)
                        ->where('status <>', 'VOID')
                        ->order_by('id', 'DESC')
                        ->limit(1)
                        ->get()
                        ->row_array();

                if (!$existing) {
                        return ['ok' => true];
                }

                $notes = (string)($existing['notes'] ?? '');
                if (stripos($notes, 'Core component opening migration.') === false) {
                        return [
                                'ok' => false,
                                'message' => 'Sudah ada opening component non-migrasi untuk bulan/lokasi/divisi ini. Cleanup manual dibutuhkan.',
                                'data' => $existing,
                        ];
                }

                $status = strtoupper(trim((string)($existing['status'] ?? 'DRAFT')));
                if ($status === 'POSTED') {
                        return $this->Production_model->void_component_opening((int)$existing['id'], $userId);
                }
                if ($status === 'DRAFT') {
                        return $this->Production_model->delete_draft_doc('inv_component_opening', 'inv_component_opening_line', 'opening_id', (int)$existing['id']);
                }

                return ['ok' => true];
        }

        private function plan_opening_lot_rows(
                float $targetQtyContent,
                float $contentPerBuy,
                array $lots,
                string $correctionLotNo,
                float $correctionUnitCost,
                string $correctionReceivedDate,
                string $correctionNote
        ): array {
                $targetQtyContent = round(max(0, $targetQtyContent), 4);
                $contentPerBuy = round(max(0.000001, $contentPerBuy), 6);

                usort($lots, static function (array $left, array $right): int {
                        $leftDate = (string)($left['received_date'] ?? '');
                        $rightDate = (string)($right['received_date'] ?? '');
                        if ($leftDate !== $rightDate) {
                                return strcmp($leftDate, $rightDate);
                        }
                        return ((int)($left['source_line_id'] ?? 0)) <=> ((int)($right['source_line_id'] ?? 0));
                });

                $sourceTotalQty = 0.0;
                foreach ($lots as $lot) {
                        $sourceTotalQty += round((float)($lot['qty_content'] ?? 0), 4);
                }
                $sourceTotalQty = round($sourceTotalQty, 4);

                $excess = round(max(0, $sourceTotalQty - $targetQtyContent), 4);
                $plannedLots = [];
                $trimmedLotCount = 0;
                foreach ($lots as $lot) {
                        $qtyContent = round(max(0, (float)($lot['qty_content'] ?? 0)), 4);
                        $qtyBuy = round(max(0, (float)($lot['qty_buy'] ?? $qtyContent)), 4);
                        if ($qtyContent <= 0) {
                                continue;
                        }

                        if ($excess > 0) {
                                $trimQty = round(min($excess, $qtyContent), 4);
                                if ($trimQty > 0) {
                                        $buyRatio = $qtyContent > 0 ? ($qtyBuy / $qtyContent) : 0.0;
                                        $qtyContent = round(max(0, $qtyContent - $trimQty), 4);
                                        $qtyBuy = round(max(0, $qtyBuy - ($trimQty * $buyRatio)), 4);
                                        $excess = round(max(0, $excess - $trimQty), 4);
                                        $trimmedLotCount++;
                                }
                        }

                        if ($qtyContent <= 0) {
                                continue;
                        }

                        $lot['qty_content'] = $qtyContent;
                        $lot['qty_buy'] = $qtyBuy > 0 ? $qtyBuy : round($qtyContent / $contentPerBuy, 4);
                        $lot['is_correction'] = false;
                        $plannedLots[] = $lot;
                }

                $plannedQtyContent = 0.0;
                $plannedQtyBuy = 0.0;
                foreach ($plannedLots as $plannedLot) {
                        $plannedQtyContent += round((float)($plannedLot['qty_content'] ?? 0), 4);
                        $plannedQtyBuy += round((float)($plannedLot['qty_buy'] ?? 0), 4);
                }
                $plannedQtyContent = round($plannedQtyContent, 4);
                $plannedQtyBuy = round($plannedQtyBuy, 4);

                $correctionQtyContent = round(max(0, $targetQtyContent - $plannedQtyContent), 4);
                if ($correctionQtyContent > 0) {
                        $plannedLots[] = [
                                'source_line_id' => null,
                                'lot_no' => $correctionLotNo,
                                'received_date' => $correctionReceivedDate,
                                'qty_content' => $correctionQtyContent,
                                'qty_buy' => round($correctionQtyContent / $contentPerBuy, 4),
                                'unit_cost' => round(max(0, $correctionUnitCost), 6),
                                'note' => $correctionNote,
                                'is_correction' => true,
                        ];
                        $plannedQtyContent = round($plannedQtyContent + $correctionQtyContent, 4);
                        $plannedQtyBuy = round($plannedQtyBuy + round($correctionQtyContent / $contentPerBuy, 4), 4);
                }

                return [
                        'planned_lots' => $plannedLots,
                        'planned_qty_content' => $plannedQtyContent,
                        'planned_qty_buy' => $plannedQtyBuy,
                        'trimmed_qty_content' => round(max(0, $sourceTotalQty - min($sourceTotalQty, $targetQtyContent)), 4),
                        'correction_qty_content' => $correctionQtyContent,
                        'trimmed_lot_count' => $trimmedLotCount,
                ];
        }

    private function purge_legacy_core_warehouse_opening_snapshots(string $snapshotMonth): array
    {
        if (!$this->db->table_exists('inv_warehouse_stock_opening_snapshot')) {
            return [];
        }

        $rows = $this->db
            ->select('id')
            ->from('inv_warehouse_stock_opening_snapshot')
            ->where('snapshot_month', $snapshotMonth)
            ->group_start()
                ->like('notes', 'Import bridge unique_text core warehouse balance.', 'after')
                ->or_like('notes', 'Import auto_create_catalog core warehouse balance.', 'after')
            ->group_end()
            ->get()
            ->result_array();

        $ids = array_values(array_filter(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $rows), static function ($id) {
            return $id > 0;
        }));
        if (empty($ids)) {
            return [];
        }

        if ($this->db->table_exists('inv_stock_movement_log')) {
            $this->db
                ->where('ref_table', 'inv_warehouse_stock_opening_snapshot')
                ->where_in('ref_id', $ids)
                ->delete('inv_stock_movement_log');
        }

        $this->db->where_in('id', $ids)->delete('inv_warehouse_stock_opening_snapshot');

        return $ids;
    }

    private function parseCliArgs(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $cliArgs = [];
        for ($i = 3; $i < count($argv); $i += 2) {
            $key = isset($argv[$i]) ? trim((string)$argv[$i]) : '';
            if ($key === '') {
                continue;
            }
            $cliArgs[$key] = isset($argv[$i + 1]) ? trim((string)$argv[$i + 1]) : '';
        }

        return $cliArgs;
    }

    private function collectPosCrossDivisionCommitTargets(int $commitId, int $orderId, int $lineId, int $limit): array
    {
        if (!$this->db->table_exists('pos_stock_commit') || !$this->db->table_exists('pos_stock_commit_line')) {
            return [];
        }

        $db = $this->db;
        $db->select('DISTINCT cl.commit_id, c.order_id', false)
            ->from('pos_stock_commit_line cl')
            ->join('pos_stock_commit c', 'c.id = cl.commit_id', 'inner')
            ->where('COALESCE(cl.movement_ref_id, 0) >', 0);

        if ($commitId > 0) {
            $db->where('cl.commit_id', $commitId);
        }
        if ($orderId > 0) {
            $db->where('c.order_id', $orderId);
        }
        if ($lineId > 0) {
            $db->where('cl.id', $lineId);
        }

        return $db
            ->order_by('cl.commit_id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    private function resolvePosRepairActorEmployeeId(int $orderId, int $commitId, int $actorUserId = 0): int
    {
        // CLI arguments use auth_user.id. Convert it to the employee FK used
        // by POS stock/queue records instead of assuming both IDs are equal.
        if ($actorUserId > 0 && $this->db->table_exists('auth_user') && $this->db->field_exists('employee_id', 'auth_user')) {
            $actorUser = $this->db
                ->select('employee_id')
                ->from('auth_user')
                ->where('id', $actorUserId)
                ->limit(1)
                ->get()
                ->row_array();
            if ($actorUser) {
                $employeeId = (int)($actorUser['employee_id'] ?? 0);
                if ($employeeId > 0 && $this->db->table_exists('org_employee')) {
                    $exists = $this->db->from('org_employee')->where('id', $employeeId)->count_all_results() > 0;
                    if ($exists) {
                        return $employeeId;
                    }
                }
            }
        }

        if ($commitId > 0 && $this->db->table_exists('pos_runtime_job')) {
            $jobs = $this->db
                ->select('payload_json, created_by_employee_id')
                ->from('pos_runtime_job')
                ->where('snapshot_id', $commitId)
                ->where('order_id', $orderId)
                ->order_by('id', 'DESC')
                ->limit(5)
                ->get()
                ->result_array();
            foreach ($jobs as $job) {
                $payload = json_decode((string)($job['payload_json'] ?? ''), true);
                $actorId = (int)($payload['actor_employee_id'] ?? 0);
                if ($actorId > 0) {
                    return $actorId;
                }
                $createdBy = (int)($job['created_by_employee_id'] ?? 0);
                if ($createdBy > 0) {
                    return $createdBy;
                }
            }
        }

        if ($commitId > 0 && $this->db->table_exists('pos_stock_commit')) {
            $row = $this->db->select('actor_employee_id')
                ->from('pos_stock_commit')
                ->where('id', $commitId)
                ->limit(1)
                ->get()
                ->row_array();
            $actorId = (int)($row['actor_employee_id'] ?? 0);
            if ($actorId > 0) {
                return $actorId;
            }
        }

        if ($orderId > 0 && $this->db->table_exists('pos_order')) {
            $row = $this->db->select('cashier_employee_id, served_by')
                ->from('pos_order')
                ->where('id', $orderId)
                ->limit(1)
                ->get()
                ->row_array();
            $actorId = (int)($row['cashier_employee_id'] ?? 0);
            if ($actorId > 0) {
                return $actorId;
            }
            $servedBy = (int)($row['served_by'] ?? 0);
            if ($servedBy > 0) {
                return $servedBy;
            }
        }

        return 0;
    }

    private function runSmokeCase(string $name, callable $callback): array
    {
        $this->db->trans_strict(false);
        $this->db->trans_begin();

        try {
            $result = $callback();
            if (!is_array($result)) {
                $result = ['ok' => false, 'message' => 'Smoke case tidak mengembalikan array hasil.'];
            }
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return [
                'case' => $name,
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }

        $this->db->trans_rollback();
        $result['case'] = $name;
        return $result;
    }

    private function resolveSmokeFixtures(): array
    {
        $types = [];
        foreach ($this->Purchase_model->list_active_purchase_types() as $row) {
            $code = strtoupper(trim((string)($row['type_code'] ?? '')));
            if ($code !== '') {
                $types[$code] = $row;
            }
        }

        $vendors = $this->Purchase_model->list_active_vendors();
        $accounts = $this->Purchase_model->list_active_payment_accounts();
        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $profiles = $this->Procurement_model->search_warehouse_profiles('', 20);

        if (empty($vendors)) {
            return ['ok' => false, 'message' => 'Master vendor aktif tidak tersedia untuk smoke test.'];
        }
        if (empty($accounts)) {
            return ['ok' => false, 'message' => 'Master rekening aktif tidak tersedia untuk smoke test.'];
        }
        $seedProfile = $this->resolveSmokeSeedProfile($profiles);
        if ($seedProfile === null) {
            return ['ok' => false, 'message' => 'Profile inventory aktif tidak tersedia untuk smoke test SR/inventory PO.'];
        }

        $profile = null;
        foreach ($profiles as $row) {
            if ((int)($row['item_id'] ?? 0) > 0 && (int)($row['buy_uom_id'] ?? 0) > 0 && (int)($row['content_uom_id'] ?? 0) > 0) {
                $profile = $row;
                break;
            }
        }
        if ($profile === null) {
            $profile = $seedProfile;
        }
        if ($profile === null) {
            return ['ok' => false, 'message' => 'Profile stok gudang tidak ditemukan untuk smoke test.'];
        }

        $division = $this->findSmokeDivision($divisions, ['BAR', 'KITCHEN', 'OFFICE', 'OTHER']);
        if ($division === null) {
            return ['ok' => false, 'message' => 'Divisi operasional yang cocok untuk SR smoke test tidak ditemukan.'];
        }

        $destination = (string)($division['destination_default'] ?? 'OTHER');
        if ($destination === '') {
            $destination = 'OTHER';
        }

        $uomId = (int)($profile['buy_uom_id'] ?? 0);
        if ($uomId <= 0) {
            $uom = $this->db->select('id')->from('mst_uom')->order_by('id', 'ASC')->limit(1)->get()->row_array();
            $uomId = (int)($uom['id'] ?? 0);
        }
        if ($uomId <= 0) {
            return ['ok' => false, 'message' => 'Master UOM tidak tersedia untuk smoke test non-inventory PO.'];
        }

        return [
            'ok' => true,
            'data' => [
                'purchase_types' => $types,
                'vendor' => $vendors[0],
                'account' => $accounts[0],
                'inventory_profile' => $profile,
                'inventory_profile_seed' => $seedProfile,
                'warehouse_profile_available' => !empty($profiles),
                'sr_division' => $division,
                'sr_destination' => $destination,
                'component' => $this->resolveSmokeComponent((int)($division['id'] ?? 0)),
                'uom_id' => $uomId,
            ],
        ];
    }

    private function resolveSmokeSeedProfile(array $warehouseProfiles): ?array
    {
        foreach ($warehouseProfiles as $row) {
            $normalized = $this->normalizeSmokeProfileRow((array)$row);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $catalogRows = $this->Purchase_model->search_catalog_profiles('', 0, '', 0, 0, 20);
        foreach ($catalogRows as $row) {
            $normalized = $this->normalizeSmokeProfileRow([
                'line_kind' => (string)($row['line_kind'] ?? ''),
                'item_id' => (int)($row['item_id'] ?? 0),
                'material_id' => (int)($row['material_id'] ?? 0),
                'profile_key' => (string)($row['profile_key'] ?? ''),
                'profile_name' => (string)($row['catalog_name'] ?? ''),
                'profile_brand' => (string)($row['brand_name'] ?? ''),
                'profile_description' => (string)($row['line_description'] ?? ''),
                'profile_expired_date' => (string)($row['expired_date'] ?? ''),
                'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                'profile_content_per_buy' => (float)($row['content_per_buy'] ?? $row['conversion_factor_to_content'] ?? 1),
                'profile_buy_uom_code' => (string)($row['buy_uom_code'] ?? ''),
                'profile_content_uom_code' => (string)($row['content_uom_code'] ?? ''),
                'qty_buy_balance' => 0,
                'qty_content_balance' => 0,
            ]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $masterRows = $this->Purchase_model->search_master_fallback('', '', 0, 0, 20);
        foreach ($masterRows as $row) {
            $normalized = $this->normalizeSmokeProfileRow([
                'line_kind' => (string)($row['line_kind'] ?? ''),
                'item_id' => (int)($row['item_id'] ?? 0),
                'material_id' => (int)($row['material_id'] ?? 0),
                'profile_key' => (string)($row['profile_key'] ?? ''),
                'profile_name' => (string)($row['catalog_name'] ?? ''),
                'profile_brand' => (string)($row['brand_name'] ?? ''),
                'profile_description' => (string)($row['line_description'] ?? ''),
                'profile_expired_date' => (string)($row['expired_date'] ?? ''),
                'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                'profile_content_per_buy' => (float)($row['content_per_buy'] ?? $row['conversion_factor_to_content'] ?? 1),
                'profile_buy_uom_code' => (string)($row['buy_uom_code'] ?? ''),
                'profile_content_uom_code' => (string)($row['content_uom_code'] ?? ''),
                'qty_buy_balance' => 0,
                'qty_content_balance' => 0,
            ]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeSmokeProfileRow(array $row): ?array
    {
        $buyUomId = (int)($row['buy_uom_id'] ?? 0);
        $contentUomId = (int)($row['content_uom_id'] ?? 0);
        $itemId = (int)($row['item_id'] ?? 0);
        $materialId = (int)($row['material_id'] ?? 0);
        if ($buyUomId <= 0 || $contentUomId <= 0 || ($itemId <= 0 && $materialId <= 0)) {
            return null;
        }

        $lineKind = 'ITEM';

        $contentPerBuy = round((float)($row['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        return [
            'line_kind' => $lineKind,
            'item_id' => $itemId > 0 ? $itemId : null,
            'material_id' => $materialId > 0 ? $materialId : null,
            'profile_key' => (string)($row['profile_key'] ?? ''),
            'profile_name' => (string)($row['profile_name'] ?? ''),
            'profile_brand' => (string)($row['profile_brand'] ?? ''),
            'profile_description' => (string)($row['profile_description'] ?? ''),
            'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_content_per_buy' => $contentPerBuy,
            'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
            'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
            'qty_buy_balance' => round((float)($row['qty_buy_balance'] ?? 0), 4),
            'qty_content_balance' => round((float)($row['qty_content_balance'] ?? 0), 4),
        ];
    }

    private function ensureSmokeWarehouseProfile(array $fixtures, string $smokeDate, int $userId): array
    {
        $profiles = $this->Procurement_model->search_warehouse_profiles('', 20);
        $profile = $this->resolveSmokeSeedProfile($profiles);
        if ($profile !== null && (float)($profile['qty_content_balance'] ?? 0) > 0) {
            return ['ok' => true, 'data' => ['profile' => $profile, 'bootstrapped' => false]];
        }

        $seedProfile = $this->normalizeSmokeProfileRow((array)($fixtures['inventory_profile_seed'] ?? $fixtures['inventory_profile'] ?? []));
        if ($seedProfile === null) {
            return ['ok' => false, 'message' => 'Profile seed inventory tidak tersedia untuk bootstrap stok gudang smoke test.'];
        }

        $warehouseType = $this->firstSmokeType((array)($fixtures['purchase_types'] ?? []), ['INV_STOK']);
        if ($warehouseType === null) {
            return ['ok' => false, 'message' => 'Purchase type INV_STOK tidak tersedia untuk bootstrap stok gudang smoke test.'];
        }

        $bootstrap = $this->Purchase_model->store_order_with_lines([
            'request_date' => $smokeDate,
            'expected_date' => $smokeDate,
            'purchase_type_id' => (int)($warehouseType['id'] ?? 0),
            'vendor_id' => (int)(($fixtures['vendor']['id'] ?? 0)),
            'status' => 'RECEIVED',
            'notes' => 'Smoke bootstrap warehouse stock',
        ], [
            $this->buildSmokeInventoryPurchaseLine($seedProfile, 1.0),
        ], $userId, 'CLI_SMOKE');
        if (!($bootstrap['ok'] ?? false)) {
            return $bootstrap;
        }

        $profiles = $this->Procurement_model->search_warehouse_profiles('', 20);
        $profile = $this->resolveSmokeSeedProfile($profiles);
        if ($profile === null || (float)($profile['qty_content_balance'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Bootstrap stok gudang smoke test gagal menghasilkan saldo gudang aktif.'];
        }

        return [
            'ok' => true,
            'data' => [
                'profile' => $profile,
                'bootstrapped' => true,
                'purchase_order_id' => (int)($bootstrap['data']['purchase_order_id'] ?? 0),
            ],
        ];
    }

    private function resolveSmokeComponent(int $divisionId): ?array
    {
        if (!$this->db->table_exists('mst_component')) {
            return null;
        }

        $this->db->select('id, component_code, component_name, uom_id, operational_division_id')
            ->from('mst_component')
            ->where('is_active', 1);
        if ($divisionId > 0 && $this->db->field_exists('operational_division_id', 'mst_component')) {
            $this->db->group_start()
                ->where('operational_division_id', $divisionId)
                ->or_where('operational_division_id IS NULL', null, false)
                ->group_end();
        }
        $row = $this->db->order_by('id', 'ASC')->limit(1)->get()->row_array();

        if ($row) {
            return $row;
        }

        return $this->db->select('id, component_code, component_name, uom_id, operational_division_id')
            ->from('mst_component')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function findSmokeDivision(array $divisions, array $preferredDestinations): ?array
    {
        foreach ($preferredDestinations as $destination) {
            foreach ($divisions as $row) {
                $default = strtoupper(trim((string)($row['destination_default'] ?? '')));
                $allowed = strtoupper((string)($row['destination_allowed'] ?? ''));
                if ($default === $destination || strpos($allowed, $destination) !== false) {
                    return $row;
                }
            }
        }

        return !empty($divisions) ? $divisions[0] : null;
    }

    private function firstSmokeType(array $types, array $codes): ?array
    {
        foreach ($codes as $code) {
            $key = strtoupper(trim($code));
            if (!empty($types[$key]) && is_array($types[$key])) {
                return $types[$key];
            }
        }

        return null;
    }

    private function buildSmokeInventoryPurchaseLine(array $profile, float $qtyBuy): array
    {
        $contentPerBuy = round((float)($profile['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        return [
            'line_kind' => 'ITEM',
            'item_id' => (int)($profile['item_id'] ?? 0) > 0 ? (int)$profile['item_id'] : null,
            'material_id' => (int)($profile['material_id'] ?? 0) > 0 ? (int)$profile['material_id'] : null,
            'buy_uom_id' => (int)($profile['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($profile['content_uom_id'] ?? 0),
            'qty_buy' => round(max($qtyBuy, 0.01), 4),
            'content_per_buy' => $contentPerBuy,
            'conversion_factor_to_content' => $contentPerBuy,
            'unit_price' => 1000,
            'brand_name' => (string)($profile['profile_brand'] ?? ''),
            'line_description' => (string)($profile['profile_description'] ?? 'Smoke inventory purchase'),
            'expired_date' => (string)($profile['profile_expired_date'] ?? ''),
        ];
    }

    private function buildSmokeStoreRequestLine(array $profile, float $qtyBuy, float $qtyContent): array
    {
        $contentPerBuy = round((float)($profile['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        return [
            'line_kind' => 'ITEM',
            'item_id' => (int)($profile['item_id'] ?? 0) > 0 ? (int)$profile['item_id'] : null,
            'material_id' => (int)($profile['material_id'] ?? 0) > 0 ? (int)$profile['material_id'] : null,
            'profile_key' => (string)($profile['profile_key'] ?? ''),
            'profile_name' => (string)($profile['profile_name'] ?? ''),
            'profile_brand' => (string)($profile['profile_brand'] ?? ''),
            'profile_description' => (string)($profile['profile_description'] ?? ''),
            'profile_expired_date' => (string)($profile['profile_expired_date'] ?? ''),
            'buy_uom_id' => (int)($profile['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($profile['content_uom_id'] ?? 0),
            'profile_content_per_buy' => $contentPerBuy,
            'profile_buy_uom_code' => (string)($profile['profile_buy_uom_code'] ?? ''),
            'profile_content_uom_code' => (string)($profile['profile_content_uom_code'] ?? ''),
            'qty_buy_requested' => round($qtyBuy, 2),
            'qty_content_requested' => round($qtyContent, 2),
            'notes' => 'Smoke request line',
        ];
    }

    private function smokeRequestQty(array $profile, bool $withShortage): array
    {
        $contentPerBuy = round((float)($profile['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        $availableContent = round((float)($profile['qty_content_balance'] ?? 0), 4);
        if ($availableContent <= 0) {
            $availableContent = $contentPerBuy;
        }

        $qtyContent = $withShortage
            ? round($availableContent + $contentPerBuy, 2)
            : round(min($availableContent, $contentPerBuy), 2);
        if ($qtyContent <= 0) {
            $qtyContent = round($contentPerBuy, 2);
        }

        $qtyBuy = round($qtyContent / max($contentPerBuy, 0.000001), 2);
        if ($qtyBuy <= 0) {
            $qtyBuy = 1;
        }

        return [
            'qty_buy' => $qtyBuy,
            'qty_content' => $qtyContent,
        ];
    }

    private function summarizeMovementByRef(string $refTable, int $refId): array
    {
        if ($refId <= 0 || !$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        return $this->db
            ->select('movement_scope, movement_type, COALESCE(destination_type, \'\') AS destination_type', false)
            ->select('COUNT(*) AS row_count, ROUND(SUM(qty_buy_delta), 4) AS qty_buy_total, ROUND(SUM(qty_content_delta), 4) AS qty_content_total', false)
            ->from('inv_stock_movement_log')
            ->where('ref_table', $refTable)
            ->where('ref_id', $refId)
            ->group_by('movement_scope')
            ->group_by('movement_type')
            ->group_by('destination_type')
            ->order_by('movement_scope', 'ASC')
            ->order_by('movement_type', 'ASC')
            ->get()
            ->result_array();
    }

    private function movementSummaryHas(array $rows, string $scope, string $movementType): bool
    {
        foreach ($rows as $row) {
            if (
                strtoupper((string)($row['movement_scope'] ?? '')) === strtoupper($scope)
                && strtoupper((string)($row['movement_type'] ?? '')) === strtoupper($movementType)
            ) {
                return true;
            }
        }

        return false;
    }

    private function movementSummaryHasScope(array $rows, string $scope): bool
    {
        foreach ($rows as $row) {
            if (strtoupper((string)($row['movement_scope'] ?? '')) === strtoupper($scope)) {
                return true;
            }
        }

        return false;
    }
}
