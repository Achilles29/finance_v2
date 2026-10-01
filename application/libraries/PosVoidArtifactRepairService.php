<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Repairs a narrowly defined legacy POS void defect.
 *
 * Older failed/void commits could write a positive aggregate return even
 * though their stock commit never issued a lot or ledger movement. This
 * service is CLI-only through Inventory_tools and refuses candidates that
 * have any source usage evidence.
 */
class PosVoidArtifactRepairService
{
    /** @var CI_Controller */
    private $ci;

    private const MATERIAL_RETURN_NOTE = 'POS return to stock aggregate reversal.';
    private const COMPONENT_RETURN_NOTE = 'POS return to stock component reversal.';
    private const REPAIR_NOTE = 'Koreksi sistem: return void tanpa bukti stok keluar dihapus.';

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function audit(array $options = []): array
    {
        $commitIds = $this->normalizeCommitIds($options['commit_ids'] ?? []);
        $materialRows = $this->findMaterialCandidates($commitIds);
        $componentRows = $this->findComponentCandidates($commitIds);

        $materialMovementIds = $this->collectPositiveIds($materialRows, 'id');
        $componentCommitIds = $this->collectPositiveIds($componentRows, 'source_id');
        $componentLineIds = $this->collectPositiveIds($componentRows, 'source_line_id');

        $materialLots = $this->findMaterialRepairLots($materialMovementIds);
        $componentLots = $this->findComponentRepairLots($componentCommitIds, $componentLineIds);
        $materialLotIds = $this->collectPositiveIds($materialLots, 'id');
        $componentLotIds = $this->collectPositiveIds($componentLots, 'id');

        $materialDependencies = $this->findMaterialRepairDependencies($materialLotIds);
        $componentDependencies = $this->findComponentRepairDependencies($componentLotIds);

        $candidateCommitIds = array_values(array_unique(array_merge(
            $this->collectPositiveIds($materialRows, 'ref_id'),
            $componentCommitIds
        )));
        sort($candidateCommitIds);

        return [
            'ok' => true,
            'filters' => [
                'commit_ids' => $commitIds,
            ],
            'summary' => [
                'candidate_commit_ids' => $candidateCommitIds,
                'material_return_rows' => count($materialRows),
                'component_return_rows' => count($componentRows),
                'material_repair_lots' => count($materialLots),
                'component_repair_lots' => count($componentLots),
                'material_downstream_dependencies' => count($materialDependencies),
                'component_downstream_dependencies' => count($componentDependencies),
            ],
            'material_rows' => $materialRows,
            'component_rows' => $componentRows,
            'material_lots' => $materialLots,
            'component_lots' => $componentLots,
            'material_dependencies' => $materialDependencies,
            'component_dependencies' => $componentDependencies,
        ];
    }

    public function repair(array $options = []): array
    {
        $dryRun = !empty($options['dry_run']);
        $expectedCommitIds = $this->normalizeCommitIds($options['commit_ids'] ?? []);
        $audit = $this->audit(['commit_ids' => $expectedCommitIds]);
        $summary = (array)($audit['summary'] ?? []);
        $candidateCommitIds = $this->normalizeCommitIds($summary['candidate_commit_ids'] ?? []);

        if (empty($candidateCommitIds)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada artefak return void tanpa sumber yang cocok dengan filter.',
                'dry_run' => $dryRun,
                'audit' => $audit,
            ];
        }
        if (!empty($expectedCommitIds) && $expectedCommitIds !== $candidateCommitIds) {
            return [
                'ok' => false,
                'message' => 'Kandidat repair tidak persis sama dengan commit yang dipilih. Review dry run terlebih dahulu.',
                'expected_commit_ids' => $expectedCommitIds,
                'candidate_commit_ids' => $candidateCommitIds,
                'audit' => $audit,
            ];
        }
        if ($dryRun) {
            return [
                'ok' => true,
                'message' => 'Dry run selesai. Tidak ada data yang diubah.',
                'dry_run' => true,
                'audit' => $audit,
            ];
        }

        $db = $this->ci->db;
        $originalDebug = $db->db_debug;
        $db->db_debug = false;
        $db->trans_begin();
        try {
            $materialRows = (array)($audit['material_rows'] ?? []);
            $componentRows = (array)($audit['component_rows'] ?? []);
            $materialLots = (array)($audit['material_lots'] ?? []);
            $componentLots = (array)($audit['component_lots'] ?? []);

            $materialIdentities = $this->collectMaterialIdentities($materialRows);
            $componentIdentities = $this->collectComponentIdentities($componentRows);
            $materialStartDate = $this->earliestMovementMonth($materialRows);

            $materialResult = $this->repairMaterialDependencies(
                $materialLots,
                (array)($audit['material_dependencies'] ?? [])
            );
            $componentResult = $this->repairComponentDependencies(
                $componentLots,
                (array)($audit['component_dependencies'] ?? [])
            );

            $materialIds = $this->collectPositiveIds($materialRows, 'id');
            if (!empty($materialIds)) {
                $db->where_in('id', $materialIds)->delete('inv_stock_movement_log');
                if ($db->trans_status() === false) {
                    throw new RuntimeException('Gagal menghapus movement material void yang tidak bersumber.');
                }
            }

            $componentIds = $this->collectPositiveIds($componentRows, 'id');
            if (!empty($componentIds)) {
                $db->where_in('id', $componentIds)->delete('inv_component_movement_log');
                if ($db->trans_status() === false) {
                    throw new RuntimeException('Gagal menghapus movement component void yang tidak bersumber.');
                }
            }

            $this->appendRepairNotes($candidateCommitIds);
            $this->rebuildMaterialIdentities($materialIdentities, $materialStartDate);
            $this->rebuildComponentIdentities($componentIdentities);

            if ($db->trans_status() === false) {
                throw new RuntimeException('Repair artefak void POS gagal disimpan.');
            }
            $db->trans_commit();
            $db->db_debug = $originalDebug;

            return [
                'ok' => true,
                'message' => 'Repair artefak return void POS selesai.',
                'dry_run' => false,
                'candidate_commit_ids' => $candidateCommitIds,
                'material' => $materialResult,
                'component' => $componentResult,
                'deleted_material_return_rows' => count($materialIds),
                'deleted_component_return_rows' => count($componentIds),
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            $db->db_debug = $originalDebug;
            return [
                'ok' => false,
                'message' => trim((string)$e->getMessage()) ?: 'Repair artefak return void POS gagal.',
            ];
        }
    }

    private function findMaterialCandidates(array $commitIds): array
    {
        if (
            !$this->ci->db->table_exists('inv_stock_movement_log')
            || !$this->ci->db->table_exists('pos_stock_commit')
            || !$this->ci->db->table_exists('inv_material_fifo_issue_log')
        ) {
            return [];
        }

        $db = $this->ci->db
            ->select('m.*, c.commit_no, c.commit_status, c.order_id')
            ->from('inv_stock_movement_log m')
            ->join('pos_stock_commit c', 'c.id = m.ref_id', 'inner')
            ->where('m.ref_table', 'pos_stock_commit_reversal')
            ->where('m.movement_type', 'VOID_REVERSE')
            ->where('m.notes', self::MATERIAL_RETURN_NOTE)
            ->where('m.qty_content_delta >', 0.0001, false)
            ->where('m.reversal_of_movement_id IS NULL', null, false)
            ->where_in('c.commit_status', ['VOID', 'FAILED'])
            // A candidate is safe only when no original material usage exists
            // for that failed/void commit and material.
            ->where("NOT EXISTS (
                SELECT 1
                FROM inv_stock_movement_log source_m
                WHERE source_m.ref_table = 'pos_stock_commit'
                  AND source_m.ref_id = m.ref_id
                  AND source_m.movement_type = 'USAGE_OUT'
                  AND source_m.material_id <=> m.material_id
            )", null, false)
            ->where("NOT EXISTS (
                SELECT 1
                FROM inv_material_fifo_issue_log source_i
                WHERE source_i.source_table = 'pos_stock_commit'
                  AND source_i.source_id = m.ref_id
                  AND source_i.material_id <=> m.material_id
                  AND source_i.status = 'POSTED'
            )", null, false);
        if (!empty($commitIds)) {
            $db->where_in('m.ref_id', $commitIds);
        }

        return $db->order_by('m.ref_id', 'ASC')->order_by('m.id', 'ASC')->get()->result_array();
    }

    private function findComponentCandidates(array $commitIds): array
    {
        if (
            !$this->ci->db->table_exists('inv_component_movement_log')
            || !$this->ci->db->table_exists('pos_stock_commit')
            || !$this->ci->db->table_exists('inv_component_lot_issue_log')
        ) {
            return [];
        }

        $db = $this->ci->db
            ->select('m.*, c.commit_no, c.commit_status, c.order_id')
            ->from('inv_component_movement_log m')
            ->join('pos_stock_commit c', 'c.id = m.source_id', 'inner')
            ->where('m.source_table', 'pos_stock_commit_reversal')
            ->where('m.movement_type', 'VOID_REVERSE')
            ->where('m.notes', self::COMPONENT_RETURN_NOTE)
            ->where('m.qty_in >', 0.0001, false)
            ->where('m.reversal_of_movement_id IS NULL', null, false)
            ->where_in('c.commit_status', ['VOID', 'FAILED'])
            // Same guard for components: never repair a row that might have
            // an authentic source movement or source lot issue.
            ->where("NOT EXISTS (
                SELECT 1
                FROM inv_component_movement_log source_m
                WHERE source_m.source_table = 'pos_stock_commit'
                  AND source_m.source_id = m.source_id
                  AND source_m.movement_type = 'USAGE'
                  AND source_m.component_id = m.component_id
            )", null, false)
            ->where("NOT EXISTS (
                SELECT 1
                FROM inv_component_lot_issue_log source_i
                WHERE source_i.source_table = 'pos_stock_commit'
                  AND source_i.source_id = m.source_id
                  AND source_i.component_id = m.component_id
                  AND source_i.status = 'POSTED'
            )", null, false);
        if (!empty($commitIds)) {
            $db->where_in('m.source_id', $commitIds);
        }

        return $db->order_by('m.source_id', 'ASC')->order_by('m.id', 'ASC')->get()->result_array();
    }

    private function findMaterialRepairLots(array $movementIds): array
    {
        if (empty($movementIds) || !$this->ci->db->table_exists('inv_material_fifo_lot')) {
            return [];
        }

        return $this->ci->db
            ->from('inv_material_fifo_lot')
            ->where('source_table', 'inv_stock_void_lot_repair')
            ->where_in('source_id', $movementIds)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    private function findComponentRepairLots(array $commitIds, array $lineIds): array
    {
        if (empty($commitIds) || empty($lineIds) || !$this->ci->db->table_exists('inv_component_lot')) {
            return [];
        }

        return $this->ci->db
            ->from('inv_component_lot')
            ->where('source_table', 'pos_stock_commit_reversal')
            ->where_in('source_id', $commitIds)
            ->where_in('source_line_id', $lineIds)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    private function findMaterialRepairDependencies(array $lotIds): array
    {
        if (empty($lotIds) || !$this->ci->db->table_exists('inv_material_fifo_issue_line')) {
            return [];
        }

        return $this->ci->db
            ->select('il.id AS issue_line_id, il.issue_id, il.lot_id, il.qty_out AS fake_qty, i.source_table, i.source_id, i.source_line_id, i.issue_date, i.location_scope, i.division_id, i.destination_type, i.item_id, i.material_id, i.buy_uom_id, i.content_uom_id, i.profile_key, l.item_id AS lot_item_id, l.material_id AS lot_material_id, l.buy_uom_id AS lot_buy_uom_id, l.content_uom_id AS lot_content_uom_id, l.profile_key AS lot_profile_key, l.unit_cost AS lot_unit_cost')
            ->from('inv_material_fifo_issue_line il')
            ->join('inv_material_fifo_issue_log i', 'i.id = il.issue_id', 'inner')
            ->join('inv_material_fifo_lot l', 'l.id = il.lot_id', 'inner')
            ->where_in('il.lot_id', $lotIds)
            ->where('il.qty_out >', 0.0001, false)
            ->order_by('i.source_id', 'ASC')
            ->order_by('i.source_line_id', 'ASC')
            ->order_by('il.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function findComponentRepairDependencies(array $lotIds): array
    {
        if (empty($lotIds) || !$this->ci->db->table_exists('inv_component_lot_issue_line')) {
            return [];
        }

        return $this->ci->db
            ->select('il.id AS issue_line_id, il.issue_id, il.lot_id, il.qty_out AS fake_qty, i.source_table, i.source_id, i.source_line_id, i.issue_date, i.location_type, i.division_id, i.component_id, i.uom_id')
            ->from('inv_component_lot_issue_line il')
            ->join('inv_component_lot_issue_log i', 'i.id = il.issue_id', 'inner')
            ->where_in('il.lot_id', $lotIds)
            ->where('il.qty_out >', 0.0001, false)
            ->order_by('i.source_id', 'ASC')
            ->order_by('i.source_line_id', 'ASC')
            ->order_by('il.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function repairMaterialDependencies(array $lots, array $dependencies): array
    {
        $lotsById = [];
        foreach ($lots as $lot) {
            $lotId = (int)($lot['id'] ?? 0);
            if ($lotId > 0) {
                $lotsById[$lotId] = $lot;
            }
        }

        $groups = [];
        foreach ($dependencies as $dependency) {
            if (strtolower(trim((string)($dependency['source_table'] ?? ''))) !== 'pos_stock_commit') {
                throw new RuntimeException('Lot repair material memiliki dependensi di luar POS; dihentikan untuk review manual.');
            }
            $commitId = (int)($dependency['source_id'] ?? 0);
            $lineId = (int)($dependency['source_line_id'] ?? 0);
            $lotId = (int)($dependency['lot_id'] ?? 0);
            if ($commitId <= 0 || $lineId <= 0 || !isset($lotsById[$lotId])) {
                throw new RuntimeException('Dependensi lot repair material tidak lengkap.');
            }
            $groups[$commitId . ':' . $lineId][] = $dependency;
        }

        $deletedUsageMovements = 0;
        $deficitIds = [];
        $results = [];
        foreach ($groups as $group) {
            $first = $group[0];
            $commitId = (int)$first['source_id'];
            $lineId = (int)$first['source_line_id'];
            $snapshot = $this->loadRepairSnapshot($commitId, $lineId);
            $header = (array)($snapshot['header'] ?? []);
            $line = (array)($snapshot['line'] ?? []);
            if (strtoupper((string)($line['source_kind'] ?? '')) !== 'MATERIAL') {
                throw new RuntimeException('Commit line material untuk lot repair tidak ditemukan.');
            }

            $issueIds = [];
            $identityLot = null;
            foreach ($group as $dependency) {
                $lotId = (int)$dependency['lot_id'];
                $lot = (array)$lotsById[$lotId];
                if ((int)($lot['material_id'] ?? 0) !== (int)($line['material_id'] ?? 0)) {
                    throw new RuntimeException('Lot repair material tidak cocok dengan source material POS.');
                }
                if ($identityLot === null) {
                    $identityLot = $lot;
                } elseif (
                    (int)($identityLot['material_id'] ?? 0) !== (int)($lot['material_id'] ?? 0)
                    || (string)($identityLot['profile_key'] ?? '') !== (string)($lot['profile_key'] ?? '')
                ) {
                    throw new RuntimeException('Satu source line POS memakai beberapa identitas lot repair; dihentikan untuk review manual.');
                }

                $issueId = (int)($dependency['issue_id'] ?? 0);
                $issueLineId = (int)($dependency['issue_line_id'] ?? 0);
                $usagePrefix = 'POS usage FIFO issue#' . $issueId . ' line#' . $issueLineId . ' ';
                $movementRows = $this->ci->db
                    ->select('id')
                    ->from('inv_stock_movement_log')
                    ->where('ref_table', 'pos_stock_commit')
                    ->where('ref_id', $commitId)
                    ->where('movement_type', 'USAGE_OUT')
                    ->where('material_id', (int)($line['material_id'] ?? 0))
                    ->like('notes', $usagePrefix, 'after')
                    ->get()
                    ->result_array();
                $movementIds = $this->collectPositiveIds($movementRows, 'id');
                if (!empty($movementIds)) {
                    $this->ci->db->where_in('id', $movementIds)->delete('inv_stock_movement_log');
                    if ($this->ci->db->trans_status() === false) {
                        throw new RuntimeException('Gagal menghapus usage material yang berasal dari lot repair.');
                    }
                    $deletedUsageMovements += count($movementIds);
                }

                $this->ci->db
                    ->where('id', $issueLineId)
                    ->where('lot_id', $lotId)
                    ->delete('inv_material_fifo_issue_line');
                if ($this->ci->db->affected_rows() !== 1) {
                    throw new RuntimeException('Detail issue lot material repair tidak dapat dihapus dengan aman.');
                }
                $issueIds[$issueId] = $issueId;
            }

            foreach (array_values($issueIds) as $issueId) {
                $this->refreshMaterialIssueHeader($issueId);
            }

            $activeIssues = $this->ci->db
                ->from('inv_material_fifo_issue_log')
                ->where('source_table', 'pos_stock_commit')
                ->where('source_id', $commitId)
                ->where('source_line_id', $lineId)
                ->where('status', 'POSTED')
                ->order_by('id', 'ASC')
                ->get()
                ->result_array();
            if (count($activeIssues) > 1) {
                throw new RuntimeException('Source material POS memiliki lebih dari satu issue FIFO aktif; dihentikan untuk review manual.');
            }

            $issuedQty = empty($activeIssues) ? 0.0 : round((float)($activeIssues[0]['issue_qty'] ?? 0), 4);
            $committedQty = round((float)($line['committed_qty'] ?? $line['required_qty'] ?? 0), 4);
            if ($committedQty <= 0 || $issuedQty > $committedQty + 0.0001) {
                throw new RuntimeException('Qty source material POS tidak valid saat koreksi lot repair.');
            }

            $deficit = $this->upsertMaterialDeficit(
                $header,
                $line,
                (array)$identityLot,
                empty($activeIssues) ? (array)$first : (array)$activeIssues[0],
                $issuedQty
            );
            $deficitId = (int)($deficit['id'] ?? 0);
            if ($deficitId > 0) {
                $deficitIds[$deficitId] = $deficitId;
            }

            $lineNote = self::REPAIR_NOTE . ' Pemakaian lot fiktif dialihkan menjadi defisit stok terukur.';
            $lineUpdate = [
                'cost_source' => $this->supportsDeficitPendingCostSource() ? 'DEFICIT_PENDING' : 'STANDARD_FALLBACK',
                'notes' => $this->appendRepairNote((string)($line['notes'] ?? ''), $lineNote),
            ];
            if (!empty($activeIssues)) {
                $lineUpdate['movement_ref_type'] = 'MATERIAL_LEDGER';
                $lineUpdate['movement_ref_id'] = (int)$activeIssues[0]['id'];
            } else {
                $lineUpdate['movement_ref_type'] = 'INVENTORY_DEFICIT';
                $lineUpdate['movement_ref_id'] = $deficitId > 0 ? $deficitId : null;
            }
            $this->ci->db->where('id', $lineId)->update('pos_stock_commit_line', $lineUpdate);
            if ($this->ci->db->trans_status() === false) {
                throw new RuntimeException('Gagal memperbarui source material POS setelah koreksi lot repair.');
            }

            $results[] = [
                'commit_id' => $commitId,
                'line_id' => $lineId,
                'issued_qty_after' => $issuedQty,
                'deficit_id' => $deficitId,
                'deficit_qty_after' => round(max(0, $committedQty - $issuedQty), 4),
            ];
        }

        $lotIds = $this->collectPositiveIds($lots, 'id');
        $this->deleteMaterialRepairLots($lotIds);

        return [
            'downstream_lines_repaired' => count($results),
            'deleted_downstream_usage_movements' => $deletedUsageMovements,
            'deficit_ids' => array_values($deficitIds),
            'results' => $results,
        ];
    }

    private function repairComponentDependencies(array $lots, array $dependencies): array
    {
        $lotsById = [];
        foreach ($lots as $lot) {
            $lotId = (int)($lot['id'] ?? 0);
            if ($lotId > 0) {
                $lotsById[$lotId] = $lot;
            }
        }

        $groups = [];
        foreach ($dependencies as $dependency) {
            if (strtolower(trim((string)($dependency['source_table'] ?? ''))) !== 'pos_stock_commit') {
                throw new RuntimeException('Lot repair component memiliki dependensi di luar POS; dihentikan untuk review manual.');
            }
            $commitId = (int)($dependency['source_id'] ?? 0);
            $lineId = (int)($dependency['source_line_id'] ?? 0);
            $lotId = (int)($dependency['lot_id'] ?? 0);
            if ($commitId <= 0 || $lineId <= 0 || !isset($lotsById[$lotId])) {
                throw new RuntimeException('Dependensi lot repair component tidak lengkap.');
            }
            $groups[$commitId . ':' . $lineId][] = $dependency;
        }

        if (!empty($groups)) {
            $this->ci->load->library('ComponentLotManager');
        }

        $contexts = [];
        $deletedUsageMovements = 0;
        foreach ($groups as $group) {
            $first = $group[0];
            $commitId = (int)$first['source_id'];
            $lineId = (int)$first['source_line_id'];
            $snapshot = $this->loadRepairSnapshot($commitId, $lineId);
            $header = (array)($snapshot['header'] ?? []);
            $line = (array)($snapshot['line'] ?? []);
            if (strtoupper((string)($line['source_kind'] ?? '')) !== 'COMPONENT') {
                throw new RuntimeException('Commit line component untuk lot repair tidak ditemukan.');
            }

            $rollback = $this->ci->componentlotmanager->rollbackIssueLotsBySource(
                'pos_stock_commit',
                $commitId,
                $lineId,
                self::REPAIR_NOTE,
                null,
                false
            );
            if (!($rollback['ok'] ?? false)) {
                throw new RuntimeException((string)($rollback['message'] ?? 'Rollback issue component gagal.'));
            }

            $issueRows = $this->ci->db
                ->select('id')
                ->from('inv_component_lot_issue_log')
                ->where('source_table', 'pos_stock_commit')
                ->where('source_id', $commitId)
                ->where('source_line_id', $lineId)
                ->get()
                ->result_array();
            $issueIds = $this->collectPositiveIds($issueRows, 'id');
            if (empty($issueIds)) {
                throw new RuntimeException('Issue component sumber tidak ditemukan saat koreksi lot repair.');
            }

            $usageRows = $this->ci->db
                ->select('id')
                ->from('inv_component_movement_log')
                ->where('source_table', 'pos_stock_commit')
                ->where('source_id', $commitId)
                ->where('source_line_id', $lineId)
                ->where('movement_type', 'USAGE')
                ->get()
                ->result_array();
            $usageIds = $this->collectPositiveIds($usageRows, 'id');
            if (count($usageIds) !== 1) {
                throw new RuntimeException('Source component POS tidak memiliki tepat satu movement usage; dihentikan untuk review manual.');
            }

            $this->ci->db->where_in('id', $usageIds)->delete('inv_component_movement_log');
            $this->ci->db->where_in('issue_id', $issueIds)->delete('inv_component_lot_issue_line');
            $this->ci->db->where_in('id', $issueIds)->delete('inv_component_lot_issue_log');
            if ($this->ci->db->trans_status() === false) {
                throw new RuntimeException('Gagal menghapus usage component lama yang memakai lot repair.');
            }
            $deletedUsageMovements += count($usageIds);
            $contexts[] = [
                'commit_id' => $commitId,
                'line_id' => $lineId,
                'header' => $header,
                'line' => $line,
            ];
        }

        $lotIds = $this->collectPositiveIds($lots, 'id');
        $this->deleteComponentRepairLots($lotIds);

        $this->ci->load->library('PosOrderStockService');
        $results = [];
        foreach ($contexts as $context) {
            $commitId = (int)$context['commit_id'];
            $lineId = (int)$context['line_id'];
            $otherPending = (int)$this->ci->db
                ->where('commit_id', $commitId)
                ->where('id !=', $lineId)
                ->where('(COALESCE(movement_ref_type, "NONE") = "NONE" AND COALESCE(movement_ref_id, 0) = 0)', null, false)
                ->where('committed_qty > reversed_qty', null, false)
                ->count_all_results('pos_stock_commit_line');
            if ($otherPending > 0) {
                throw new RuntimeException('Commit sumber component memiliki line lain yang belum diposting; dihentikan agar tidak merepost line lain.');
            }

            $this->ci->db->where('id', $lineId)->update('pos_stock_commit_line', [
                'movement_ref_type' => 'NONE',
                'movement_ref_id' => null,
                'notes' => $this->appendRepairNote((string)($context['line']['notes'] ?? ''), self::REPAIR_NOTE . ' Pemakaian component diposting ulang dari lot fisik.'),
            ]);
            if ($this->ci->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyiapkan repost source component POS.');
            }

            $post = $this->ci->posorderstockservice->post_commit_snapshot($commitId, [
                'actor_employee_id' => (int)($context['header']['actor_employee_id'] ?? 0),
                'notes' => self::REPAIR_NOTE . ' Repost component dari lot fisik.',
            ]);
            if (!($post['ok'] ?? false) || (int)($post['posted_lines'] ?? 0) !== 1) {
                throw new RuntimeException((string)($post['message'] ?? 'Repost component POS gagal atau tidak tepat satu line.'));
            }

            $updatedLine = $this->ci->db->from('pos_stock_commit_line')->where('id', $lineId)->limit(1)->get()->row_array() ?: [];
            if ((int)($updatedLine['movement_ref_id'] ?? 0) <= 0) {
                throw new RuntimeException('Repost component tidak menghasilkan referensi stok baru.');
            }
            $results[] = [
                'commit_id' => $commitId,
                'line_id' => $lineId,
                'movement_ref_id' => (int)$updatedLine['movement_ref_id'],
                'movement_ref_type' => (string)($updatedLine['movement_ref_type'] ?? ''),
            ];
        }

        return [
            'downstream_lines_reposted' => count($results),
            'deleted_downstream_usage_movements' => $deletedUsageMovements,
            'results' => $results,
        ];
    }

    private function loadRepairSnapshot(int $commitId, int $lineId): array
    {
        $header = $this->ci->db
            ->select('sc.*, o.order_scope')
            ->from('pos_stock_commit sc')
            ->join('pos_order o', 'o.id = sc.order_id', 'left')
            ->where('sc.id', $commitId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
        $line = $this->ci->db
            ->select('scl.*, ol.operational_division_id, od.name AS operational_division_name, od.code AS operational_division_code')
            ->from('pos_stock_commit_line scl')
            ->join('pos_order_line ol', 'ol.id = scl.order_line_id', 'left')
            ->join('mst_operational_division od', 'od.id = ol.operational_division_id', 'left')
            ->where('scl.id', $lineId)
            ->where('scl.commit_id', $commitId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
        if (!$header || !$line) {
            throw new RuntimeException('Snapshot commit/line untuk repair tidak ditemukan.');
        }
        return ['header' => $header, 'line' => $line];
    }

    private function refreshMaterialIssueHeader(int $issueId): void
    {
        $header = $this->ci->db->from('inv_material_fifo_issue_log')->where('id', $issueId)->limit(1)->get()->row_array();
        if (!$header) {
            throw new RuntimeException('Header issue material tidak ditemukan saat koreksi lot repair.');
        }
        $summary = $this->ci->db
            ->select('COALESCE(SUM(qty_out), 0) AS qty_out, COALESCE(SUM(total_cost), 0) AS total_cost', false)
            ->from('inv_material_fifo_issue_line')
            ->where('issue_id', $issueId)
            ->get()
            ->row_array() ?: [];
        $qty = round((float)($summary['qty_out'] ?? 0), 4);
        $status = $qty > 0.0001 ? 'POSTED' : 'VOID';
        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'issue_qty' => $qty,
            'total_cost' => round((float)($summary['total_cost'] ?? 0), 2),
            'status' => $status,
            'voided_at' => $status === 'VOID' ? date('Y-m-d H:i:s') : null,
            'notes' => $this->appendRepairNote((string)($header['notes'] ?? ''), self::REPAIR_NOTE),
        ]);
        if ($this->ci->db->trans_status() === false) {
            throw new RuntimeException('Gagal memperbarui header issue material setelah koreksi lot repair.');
        }
    }

    private function upsertMaterialDeficit(array $header, array $line, array $lot, array $issueContext, float $issuedQty): array
    {
        $committedQty = round((float)($line['committed_qty'] ?? $line['required_qty'] ?? 0), 4);
        $missingQty = round(max(0, $committedQty - $issuedQty), 4);
        if ($missingQty <= 0.0001) {
            return ['ok' => true, 'id' => 0, 'qty_remaining' => 0.0];
        }
        if (!file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            throw new RuntimeException('Fondasi Defisit Stok tidak tersedia untuk koreksi material.');
        }

        $identity = [
            'division_id' => (int)($issueContext['division_id'] ?? 0),
            'destination_type' => (string)($issueContext['destination_type'] ?? 'OTHER'),
            'item_id' => !empty($lot['item_id']) ? (int)$lot['item_id'] : null,
            'material_id' => !empty($lot['material_id']) ? (int)$lot['material_id'] : (int)($line['material_id'] ?? 0),
            'buy_uom_id' => !empty($lot['buy_uom_id']) ? (int)$lot['buy_uom_id'] : null,
            'content_uom_id' => !empty($lot['content_uom_id']) ? (int)$lot['content_uom_id'] : (int)($line['required_uom_id'] ?? 0),
            'profile_key' => $lot['profile_key'] ?? null,
        ];
        if ($identity['division_id'] <= 0 || $identity['material_id'] <= 0 || $identity['content_uom_id'] <= 0) {
            throw new RuntimeException('Identitas defisit material hasil koreksi lot repair tidak lengkap.');
        }

        $existingRows = $this->ci->db->query(
            'SELECT * FROM inv_stock_deficit
             WHERE stock_domain = "MATERIAL"
               AND source_table = "pos_stock_commit"
               AND source_id = ?
               AND source_line_id = ?
               AND material_id <=> ?
               AND profile_key <=> ?
             FOR UPDATE',
            [(int)$header['id'], (int)$line['id'], $identity['material_id'], $identity['profile_key']]
        )->result_array();
        if (count($existingRows) > 1) {
            throw new RuntimeException('Defisit material source POS tidak unik; dihentikan untuk review manual.');
        }

        if (!empty($existingRows)) {
            $existing = $existingRows[0];
            $settledQty = round((float)($existing['settled_qty'] ?? 0), 4);
            $reversedQty = round((float)($existing['reversed_qty'] ?? 0), 4);
            $writtenOffQty = round((float)($existing['written_off_qty'] ?? 0), 4);
            $remainingQty = round(max(0, $committedQty - $issuedQty - $settledQty - $reversedQty - $writtenOffQty), 4);
            $status = $remainingQty > 0.0001
                ? 'OPEN'
                : ($settledQty > 0.0001 ? 'SETTLED' : 'VOID');
            $unitCost = round((float)($line['unit_cost_live'] ?? $existing['estimated_unit_cost'] ?? 0), 6);
            $this->ci->db->where('id', (int)$existing['id'])->update('inv_stock_deficit', [
                'requested_qty' => $committedQty,
                'issued_qty' => $issuedQty,
                'qty_remaining' => $remainingQty,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_value' => round($remainingQty * $unitCost, 2),
                'status' => $status,
                'notes' => $this->appendRepairNote((string)($existing['notes'] ?? ''), self::REPAIR_NOTE . ' Qty defisit disesuaikan setelah lot fiktif dihapus.'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($this->ci->db->trans_status() === false) {
                throw new RuntimeException('Gagal memperbarui defisit material hasil koreksi lot repair.');
            }
            return ['ok' => true, 'id' => (int)$existing['id'], 'qty_remaining' => $remainingQty];
        }

        $this->ci->load->library('InventoryDeficitService');
        if (!$this->ci->inventorydeficitservice->isReady()) {
            throw new RuntimeException('Fondasi Defisit Stok belum siap untuk koreksi material.');
        }
        $record = $this->ci->inventorydeficitservice->record([
            'stock_domain' => 'MATERIAL',
            'deficit_date' => (string)($issueContext['issue_date'] ?? date('Y-m-d')),
            'location_scope' => 'DIVISION',
            'division_id' => $identity['division_id'],
            'destination_type' => $identity['destination_type'],
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'requested_qty' => $committedQty,
            'issued_qty' => $issuedQty,
            'estimated_unit_cost' => round((float)($line['unit_cost_live'] ?? $lot['unit_cost'] ?? 0), 6),
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit',
            'source_id' => (int)$header['id'],
            'source_line_id' => (int)$line['id'],
            'notes' => self::REPAIR_NOTE . ' Lot fiktif dihapus; kekurangan dicatat sebagai defisit.',
            'created_by' => null,
        ]);
        if (!($record['ok'] ?? false) || (int)($record['id'] ?? 0) <= 0) {
            throw new RuntimeException((string)($record['message'] ?? 'Gagal mencatat defisit material hasil koreksi lot repair.'));
        }

        return $record;
    }

    private function deleteMaterialRepairLots(array $lotIds): void
    {
        if (empty($lotIds)) {
            return;
        }
        $references = (int)$this->ci->db->where_in('lot_id', $lotIds)->count_all_results('inv_material_fifo_issue_line');
        $targetReferences = (int)$this->ci->db->where_in('target_lot_id', $lotIds)->count_all_results('inv_material_fifo_issue_line');
        if ($references > 0 || $targetReferences > 0) {
            throw new RuntimeException('Lot material repair masih memiliki referensi issue; dihentikan untuk menjaga histori.');
        }
        $this->ci->db->where_in('id', $lotIds)->delete('inv_material_fifo_lot');
        if ($this->ci->db->trans_status() === false) {
            throw new RuntimeException('Gagal menghapus lot material repair.');
        }
    }

    private function deleteComponentRepairLots(array $lotIds): void
    {
        if (empty($lotIds)) {
            return;
        }
        $references = (int)$this->ci->db->where_in('lot_id', $lotIds)->count_all_results('inv_component_lot_issue_line');
        $children = (int)$this->ci->db->where_in('parent_lot_id', $lotIds)->count_all_results('inv_component_lot');
        if ($references > 0 || $children > 0) {
            throw new RuntimeException('Lot component repair masih memiliki referensi; dihentikan untuk menjaga histori.');
        }
        $this->ci->db->where_in('id', $lotIds)->delete('inv_component_lot');
        if ($this->ci->db->trans_status() === false) {
            throw new RuntimeException('Gagal menghapus lot component repair.');
        }
    }

    private function collectMaterialIdentities(array $rows): array
    {
        $identities = [];
        foreach ($rows as $row) {
            $identity = [
                'item_id' => !empty($row['item_id']) ? (int)$row['item_id'] : null,
                'material_id' => !empty($row['material_id']) ? (int)$row['material_id'] : null,
                'buy_uom_id' => !empty($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                'content_uom_id' => !empty($row['content_uom_id']) ? (int)$row['content_uom_id'] : null,
                'profile_key' => $row['profile_key'] ?? null,
                'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
                'destination_type' => (string)($row['destination_type'] ?? 'OTHER'),
            ];
            if (empty($identity['content_uom_id']) || empty($identity['division_id'])) {
                continue;
            }
            $identities[json_encode($identity)] = $identity;
        }
        return array_values($identities);
    }

    private function collectComponentIdentities(array $rows): array
    {
        $identities = [];
        foreach ($rows as $row) {
            $identity = [
                'location_type' => (string)($row['location_type'] ?? ''),
                'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
                'component_id' => !empty($row['component_id']) ? (int)$row['component_id'] : null,
                'uom_id' => !empty($row['uom_id']) ? (int)$row['uom_id'] : null,
            ];
            if ($identity['location_type'] === '' || empty($identity['component_id']) || empty($identity['uom_id'])) {
                continue;
            }
            $identities[json_encode($identity)] = $identity;
        }
        return array_values($identities);
    }

    private function rebuildMaterialIdentities(array $identities, string $startDate): void
    {
        if (empty($identities)) {
            return;
        }
        $this->ci->load->model('Purchase_model');
        foreach ($identities as $identity) {
            $rebuild = $this->ci->Purchase_model->rebuild_inventory_history_for_identity(
                'DIVISION',
                $startDate,
                $identity,
                ['allow_negative_closing' => true]
            );
            if (!($rebuild['ok'] ?? false)) {
                throw new RuntimeException((string)($rebuild['message'] ?? 'Gagal rebuild histori material setelah koreksi void.'));
            }
        }
    }

    private function earliestMovementMonth(array $rows): string
    {
        $startDate = null;
        foreach ($rows as $row) {
            $date = trim((string)($row['movement_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $month = substr($date, 0, 7) . '-01';
            if ($startDate === null || $month < $startDate) {
                $startDate = $month;
            }
        }
        return $startDate ?? date('Y-m-01');
    }

    private function rebuildComponentIdentities(array $identities): void
    {
        if (empty($identities)) {
            return;
        }
        $this->ci->load->model('Production_model');
        foreach ($identities as $identity) {
            $rebuild = $this->ci->Production_model->rebuild_component_history_for_identity($identity);
            if (!($rebuild['ok'] ?? false)) {
                throw new RuntimeException((string)($rebuild['message'] ?? 'Gagal rebuild histori component setelah koreksi void.'));
            }
        }
    }

    private function appendRepairNotes(array $commitIds): void
    {
        foreach ($commitIds as $commitId) {
            $row = $this->ci->db->select('notes')->from('pos_stock_commit')->where('id', $commitId)->limit(1)->get()->row_array();
            if (!$row) {
                throw new RuntimeException('Commit void untuk catatan koreksi tidak ditemukan.');
            }
            $this->ci->db->where('id', $commitId)->update('pos_stock_commit', [
                'notes' => $this->appendRepairNote((string)($row['notes'] ?? ''), self::REPAIR_NOTE),
            ]);
            if ($this->ci->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan catatan koreksi pada commit void.');
            }
        }
    }

    private function supportsDeficitPendingCostSource(): bool
    {
        $row = $this->ci->db->query("SHOW COLUMNS FROM pos_stock_commit_line LIKE 'cost_source'")->row_array();
        return strpos(strtoupper((string)($row['Type'] ?? '')), "'DEFICIT_PENDING'") !== false;
    }

    private function appendRepairNote(string $base, string $note): string
    {
        $base = trim($base);
        $note = trim($note);
        $combined = $base === '' ? $note : ($base . ' | ' . $note);
        return substr($combined, 0, 255);
    }

    private function normalizeCommitIds($value): array
    {
        if (!is_array($value)) {
            $value = preg_split('/[\s,;]+/', trim((string)$value)) ?: [];
        }

        $ids = [];
        foreach ($value as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        sort($ids);
        return $ids;
    }

    private function collectPositiveIds(array $rows, string $field): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)($row[$field] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}
