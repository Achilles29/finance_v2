<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read models for the inventory operator workspace.
 *
 * This model intentionally does not write normal stock, lots, or movements.
 * Operational writes remain owned by the existing receipt, batch, POS, and
 * adjustment writers so the control pages cannot create hidden inventory data.
 */
class Inventory_control_model extends CI_Model
{
    /** @var array<string, array> */
    private $inventoryHealthSummaryCache = [];

    public function count_deficits(array $filters = []): int
    {
        if (!$this->db->table_exists('inv_stock_deficit')) {
            return 0;
        }

        $db = $this->deficit_query_base();
        $db->select('1', false);
        $this->apply_deficit_filters($db, $filters);
        $this->apply_deficit_group_by($db);
        $compiled = $db->get_compiled_select();
        $row = $this->db->query('SELECT COUNT(*) AS total FROM (' . $compiled . ') deficit_groups')
            ->row_array() ?: [];

        return (int)($row['total'] ?? 0);
    }

    public function list_deficits(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (!$this->db->table_exists('inv_stock_deficit')) {
            return [];
        }

        $db = $this->deficit_query_base();
        $db->select("MAX(d.id) AS id,
            MAX(d.deficit_date) AS last_deficit_date,
            MIN(d.deficit_date) AS first_deficit_date,
            MAX(COALESCE(d.updated_at, d.created_at)) AS last_activity_at,
            MAX(d.stock_domain) AS stock_domain,
            MAX(d.location_scope) AS location_scope,
            MAX(d.division_id) AS division_id,
            MAX(d.destination_type) AS destination_type,
            MAX(d.item_id) AS item_id,
            MAX(d.material_id) AS material_id,
            MAX(d.component_id) AS component_id,
            MAX(d.buy_uom_id) AS buy_uom_id,
            MAX(d.content_uom_id) AS content_uom_id,
            MAX(d.profile_key) AS profile_key,
            MAX(d.status) AS status,
            MAX(od.name) AS division_name,
            MAX(u.code) AS uom_code,
            MAX(COALESCE(mi.item_name, mm.material_name, mc.component_name, '-')) AS inventory_name,
            COUNT(*) AS event_count,
            COALESCE(SUM(d.requested_qty), 0) AS requested_qty,
            COALESCE(SUM(d.issued_qty), 0) AS issued_qty,
            COALESCE(SUM(d.settled_qty), 0) AS settled_qty,
            COALESCE(SUM(d.reversed_qty), 0) AS reversed_qty,
            COALESCE(SUM(d.qty_remaining), 0) AS qty_remaining,
            COALESCE(SUM(d.qty_remaining * d.estimated_unit_cost), 0) AS estimated_total_value,
            CASE WHEN COALESCE(SUM(d.qty_remaining), 0) > 0.0001
                 THEN COALESCE(SUM(d.qty_remaining * d.estimated_unit_cost), 0) / SUM(d.qty_remaining)
                 ELSE 0 END AS estimated_unit_cost", false);
        $this->apply_deficit_filters($db, $filters);
        $this->apply_deficit_group_by($db);

        $rows = $db->order_by("CASE WHEN MAX(d.status) = 'OPEN' THEN 0 WHEN MAX(d.status) = 'SETTLED' THEN 1 WHEN MAX(d.status) = 'WRITTEN_OFF' THEN 2 ELSE 3 END", 'ASC', false)
            ->order_by('last_activity_at', 'DESC', false)
            ->order_by('id', 'DESC', false)
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->result_array();

        return $this->enrich_deficit_live_snapshot(
            $this->enrich_deficit_profile_metadata($rows)
        );
    }

    public function deficit_summary(array $filters = []): array
    {
        $empty = [
            'total_rows' => 0,
            'open_rows' => 0,
            'settled_rows' => 0,
            'void_rows' => 0,
            'written_off_rows' => 0,
            'open_qty' => 0.0,
            'open_value' => 0.0,
            'material_open_rows' => 0,
            'component_open_rows' => 0,
        ];
        if (!$this->db->table_exists('inv_stock_deficit')) {
            return $empty;
        }

        $db = $this->deficit_query_base();
        $db->select("COUNT(*) AS total_rows,
            COALESCE(SUM(CASE WHEN d.status = 'OPEN' THEN 1 ELSE 0 END), 0) AS open_rows,
            COALESCE(SUM(CASE WHEN d.status = 'SETTLED' THEN 1 ELSE 0 END), 0) AS settled_rows,
            COALESCE(SUM(CASE WHEN d.status = 'VOID' THEN 1 ELSE 0 END), 0) AS void_rows,
            COALESCE(SUM(CASE WHEN d.status = 'WRITTEN_OFF' THEN 1 ELSE 0 END), 0) AS written_off_rows,
            COALESCE(SUM(CASE WHEN d.status = 'OPEN' THEN d.qty_remaining ELSE 0 END), 0) AS open_qty,
            COALESCE(SUM(CASE WHEN d.status = 'OPEN' THEN d.qty_remaining * d.estimated_unit_cost ELSE 0 END), 0) AS open_value,
            COALESCE(SUM(CASE WHEN d.status = 'OPEN' AND d.stock_domain = 'MATERIAL' THEN 1 ELSE 0 END), 0) AS material_open_rows,
            COALESCE(SUM(CASE WHEN d.status = 'OPEN' AND d.stock_domain = 'COMPONENT' THEN 1 ELSE 0 END), 0) AS component_open_rows", false);
        $this->apply_deficit_filters($db, $filters);
        $row = $db->get()->row_array() ?: [];

        return [
            'total_rows' => (int)($row['total_rows'] ?? 0),
            'open_rows' => (int)($row['open_rows'] ?? 0),
            'settled_rows' => (int)($row['settled_rows'] ?? 0),
            'void_rows' => (int)($row['void_rows'] ?? 0),
            'written_off_rows' => (int)($row['written_off_rows'] ?? 0),
            'open_qty' => round((float)($row['open_qty'] ?? 0), 4),
            'open_value' => round((float)($row['open_value'] ?? 0), 2),
            'material_open_rows' => (int)($row['material_open_rows'] ?? 0),
            'component_open_rows' => (int)($row['component_open_rows'] ?? 0),
        ];
    }

    public function get_deficit_detail(int $id, ?int $divisionId = null): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('inv_stock_deficit')) {
            return null;
        }

        $referenceQuery = $this->deficit_query_base()
            ->select('d.*')
            ->select('od.name AS division_name')
            ->select('u.code AS uom_code')
            ->select("COALESCE(mi.item_name, mm.material_name, mc.component_name, '-') AS inventory_name", false)
            ->where('d.id', $id)
            ->limit(1);
        if ($divisionId !== null && $divisionId > 0) {
            $referenceQuery->where('d.division_id', $divisionId);
        }
        $reference = $referenceQuery
            ->get()
            ->row_array();
        if (!$reference) {
            return null;
        }

        $groupQuery = $this->deficit_query_base()
            ->select("MAX(d.id) AS id,
                MIN(d.deficit_date) AS first_deficit_date,
                MAX(d.deficit_date) AS last_deficit_date,
                MAX(COALESCE(d.updated_at, d.created_at)) AS last_activity_at,
                MAX(d.stock_domain) AS stock_domain,
                MAX(d.location_scope) AS location_scope,
                MAX(d.division_id) AS division_id,
                MAX(d.destination_type) AS destination_type,
                MAX(d.item_id) AS item_id,
                MAX(d.material_id) AS material_id,
                MAX(d.component_id) AS component_id,
                MAX(d.buy_uom_id) AS buy_uom_id,
                MAX(d.content_uom_id) AS content_uom_id,
                MAX(d.profile_key) AS profile_key,
                MAX(d.status) AS status,
                MAX(od.name) AS division_name,
                MAX(u.code) AS uom_code,
                MAX(COALESCE(mi.item_name, mm.material_name, mc.component_name, '-')) AS inventory_name,
                COUNT(*) AS event_count,
                COALESCE(SUM(d.requested_qty), 0) AS requested_qty,
                COALESCE(SUM(d.issued_qty), 0) AS issued_qty,
                COALESCE(SUM(d.settled_qty), 0) AS settled_qty,
                COALESCE(SUM(d.reversed_qty), 0) AS reversed_qty,
                COALESCE(SUM(d.qty_remaining), 0) AS qty_remaining,
                COALESCE(SUM(d.qty_remaining * d.estimated_unit_cost), 0) AS estimated_total_value,
                CASE WHEN COALESCE(SUM(d.qty_remaining), 0) > 0.0001
                     THEN COALESCE(SUM(d.qty_remaining * d.estimated_unit_cost), 0) / SUM(d.qty_remaining)
                     ELSE 0 END AS estimated_unit_cost", false);
        $this->apply_deficit_identity_filter($groupQuery, $reference, true);
        $this->apply_deficit_group_by($groupQuery);
        $header = $groupQuery->get()->row_array() ?: [];
        $header = $this->enrich_deficit_live_snapshot(
            $this->enrich_deficit_profile_metadata([$header])
        )[0] ?? $header;

        $eventsQuery = $this->deficit_query_base()
            ->select('d.*')
            ->select('od.name AS division_name')
            ->select('u.code AS uom_code')
            ->select("COALESCE(mi.item_name, mm.material_name, mc.component_name, '-') AS inventory_name", false);
        $this->apply_deficit_identity_filter($eventsQuery, $reference, false);
        $events = $eventsQuery
            ->order_by('d.deficit_date', 'DESC')
            ->order_by('d.id', 'DESC')
            ->get()
            ->result_array();
        $events = $this->enrich_deficit_profile_metadata($events);
        $events = $this->enrich_deficit_source_context($events);
        $productCauses = $this->summarize_deficit_product_causes($events);

        $settlements = [];
        $eventIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $events)));
        if (!empty($eventIds) && $this->db->table_exists('inv_stock_deficit_settlement')) {
            $settlements = $this->db
                ->select('s.*, u.code AS uom_code')
                ->from('inv_stock_deficit_settlement s')
                ->join('mst_uom u', 'u.id = ' . (int)($reference['content_uom_id'] ?? 0), 'left')
                ->where_in('s.deficit_id', $eventIds)
                ->order_by('s.settlement_date', 'DESC')
                ->order_by('s.id', 'DESC')
                ->get()
                ->result_array();
        }

        return [
            'header' => $header,
            'events' => $events,
            'product_causes' => $productCauses,
            'settlements' => $settlements,
            'live_lots' => $this->get_deficit_live_lots($header),
            // A same-name material can have another active purchase profile.
            // Surface it for review, but never use it to settle this deficit
            // automatically because profile/cost identity must stay intact.
            'related_profile_stock' => $this->get_deficit_related_profile_stock($header),
        ];
    }

    public function deficit_write_off_schema_ready(): bool
    {
        if (!$this->db->table_exists('inv_stock_deficit')) {
            return false;
        }
        foreach ([
            'written_off_qty', 'written_off_value', 'written_off_reason_code',
            'written_off_notes', 'written_off_by', 'written_off_at',
        ] as $field) {
            if (!$this->db->field_exists($field, 'inv_stock_deficit')) {
                return false;
            }
        }
        return true;
    }

    /**
     * Closes all open source rows for one exact displayed deficit identity.
     * It intentionally changes neither current stock nor FIFO lots.
     */
    public function write_off_deficit_group(int $id, int $actorUserId, string $reasonCode, string $notes): array
    {
        if ($id <= 0 || !$this->deficit_write_off_schema_ready()) {
            return [
                'ok' => false,
                'message' => 'Fitur penutupan administratif belum siap. Jalankan SQL 2026-08-19a terlebih dahulu.',
            ];
        }

        $reasonCode = strtoupper(trim($reasonCode));
        $allowedReasons = ['DISCONTINUED', 'HISTORICAL_CUTOFF', 'UNRECOVERABLE', 'OTHER'];
        $notes = trim($notes);
        if (!in_array($reasonCode, $allowedReasons, true) || $notes === '') {
            return ['ok' => false, 'message' => 'Alasan dan catatan penutupan administratif wajib diisi.'];
        }

        $this->db->trans_begin();
        try {
            $reference = $this->db
                ->from('inv_stock_deficit')
                ->where('id', $id)
                ->limit(1)
                ->get()
                ->row_array();
            if (!$reference) {
                throw new RuntimeException('Data defisit tidak ditemukan.');
            }
            if (strtoupper((string)($reference['status'] ?? '')) !== 'OPEN') {
                throw new RuntimeException('Hanya defisit dengan status terbuka yang dapat ditutup administratif.');
            }

            $rowsQuery = $this->db
                ->select('d.id, d.qty_remaining, d.estimated_total_value, d.written_off_qty, d.written_off_value')
                ->from('inv_stock_deficit d')
                ->where('d.status', 'OPEN')
                ->where('d.qty_remaining >', 0.0001);
            $this->apply_deficit_identity_filter($rowsQuery, $reference, false);
            $rows = $rowsQuery->order_by('d.deficit_date', 'ASC')->order_by('d.id', 'ASC')->get()->result_array();
            if (empty($rows)) {
                throw new RuntimeException('Tidak ada defisit terbuka yang dapat ditutup pada identitas stok ini.');
            }

            $now = date('Y-m-d H:i:s');
            $totalQty = 0.0;
            $totalValue = 0.0;
            foreach ($rows as $row) {
                $openQty = round(max(0, (float)($row['qty_remaining'] ?? 0)), 4);
                $openValue = round(max(0, (float)($row['estimated_total_value'] ?? 0)), 2);
                $this->db->where('id', (int)$row['id'])->update('inv_stock_deficit', [
                    'status' => 'WRITTEN_OFF',
                    'written_off_qty' => round((float)($row['written_off_qty'] ?? 0) + $openQty, 4),
                    'written_off_value' => round((float)($row['written_off_value'] ?? 0) + $openValue, 2),
                    'qty_remaining' => 0,
                    'estimated_total_value' => 0,
                    'written_off_reason_code' => $reasonCode,
                    'written_off_notes' => substr($notes, 0, 255),
                    'written_off_by' => $actorUserId > 0 ? $actorUserId : null,
                    'written_off_at' => $now,
                    'updated_at' => $now,
                ]);
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    throw new RuntimeException('Gagal memperbarui salah satu kejadian defisit.');
                }
                $totalQty = round($totalQty + $openQty, 4);
                $totalValue = round($totalValue + $openValue, 2);
            }

            if ($this->db->table_exists('aud_transaction_log')) {
                $this->db->insert('aud_transaction_log', [
                    'module_code' => 'INVENTORY',
                    'action_code' => 'DEFICIT_WRITTEN_OFF',
                    'entity_table' => 'inv_stock_deficit',
                    'entity_id' => $id,
                    'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'after_payload' => json_encode([
                        'reason_code' => $reasonCode,
                        'event_count' => count($rows),
                        'written_off_qty' => $totalQty,
                        'written_off_value' => $totalValue,
                    ], JSON_INVALID_UTF8_SUBSTITUTE),
                    'notes' => 'Penutupan administratif defisit: ' . substr($notes, 0, 180),
                ]);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Transaksi penutupan administratif tidak lengkap.');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'written_off_qty' => $totalQty,
                'written_off_value' => $totalValue,
                'event_count' => count($rows),
                'message' => 'Defisit ditutup administratif untuk ' . count($rows) . ' kejadian. Stok dan lot tidak diubah.',
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'inventory deficit administrative write-off failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function count_periods(array $filters = []): int
    {
        if (!$this->db->table_exists('inv_stock_period')) {
            return 0;
        }
        $db = $this->db->from('inv_stock_period p');
        $this->apply_period_filters($db, $filters);
        return (int)$db->count_all_results();
    }

    public function list_periods(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (!$this->db->table_exists('inv_stock_period')) {
            return [];
        }

        $hasDeficit = $this->db->table_exists('inv_stock_deficit');
        $hasCutoffEvent = $this->db->table_exists('inv_stock_cutoff_event');
        $db = $this->db->select('p.*');
        $db->select($hasDeficit
            ? "(SELECT COUNT(*) FROM inv_stock_deficit d
                WHERE d.stock_domain = p.stock_domain
                  AND d.status = 'OPEN'
                  AND d.deficit_date >= p.period_month
                  AND d.deficit_date < DATE_ADD(p.period_month, INTERVAL 1 MONTH)) AS open_deficit_count"
            : '0 AS open_deficit_count', false);
        $db->select($hasDeficit
            ? "(SELECT COALESCE(SUM(d.qty_remaining), 0) FROM inv_stock_deficit d
                WHERE d.stock_domain = p.stock_domain
                  AND d.status = 'OPEN'
                  AND d.deficit_date >= p.period_month
                  AND d.deficit_date < DATE_ADD(p.period_month, INTERVAL 1 MONTH)) AS open_deficit_qty"
            : '0 AS open_deficit_qty', false);
        $db->select($hasCutoffEvent
            ? "(SELECT COUNT(*) FROM inv_stock_cutoff_event e
                WHERE e.stock_domain = p.stock_domain
                  AND e.period_month = p.period_month) AS cutoff_event_count"
            : '0 AS cutoff_event_count', false);
        $db->from('inv_stock_period p');
        $this->apply_period_filters($db, $filters);

        return $db->order_by('p.period_month', 'DESC')
            ->order_by("CASE p.stock_domain WHEN 'MATERIAL' THEN 0 ELSE 1 END", 'ASC', false)
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->result_array();
    }

    public function period_summary(array $filters = []): array
    {
        $empty = ['total_rows' => 0, 'open_rows' => 0, 'closing_rows' => 0, 'closed_rows' => 0, 'reopened_rows' => 0];
        if (!$this->db->table_exists('inv_stock_period')) {
            return $empty;
        }

        $db = $this->db->select("COUNT(*) AS total_rows,
            COALESCE(SUM(CASE WHEN p.status = 'OPEN' THEN 1 ELSE 0 END), 0) AS open_rows,
            COALESCE(SUM(CASE WHEN p.status = 'CLOSING' THEN 1 ELSE 0 END), 0) AS closing_rows,
            COALESCE(SUM(CASE WHEN p.status = 'CLOSED' THEN 1 ELSE 0 END), 0) AS closed_rows,
            COALESCE(SUM(CASE WHEN p.status = 'REOPENED' THEN 1 ELSE 0 END), 0) AS reopened_rows", false)
            ->from('inv_stock_period p');
        $this->apply_period_filters($db, $filters);
        $row = $db->get()->row_array() ?: [];

        return [
            'total_rows' => (int)($row['total_rows'] ?? 0),
            'open_rows' => (int)($row['open_rows'] ?? 0),
            'closing_rows' => (int)($row['closing_rows'] ?? 0),
            'closed_rows' => (int)($row['closed_rows'] ?? 0),
            'reopened_rows' => (int)($row['reopened_rows'] ?? 0),
        ];
    }

    public function get_period(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('inv_stock_period')) {
            return null;
        }
        return $this->db->from('inv_stock_period')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function cutoff_run_schema_ready(): bool
    {
        return $this->db->table_exists('inv_stock_period')
            && $this->db->table_exists('inv_stock_cutoff_run');
    }

    /**
     * Shows the latest attempts without joining auth_user because deployments
     * in this project do not share one stable display-name column there.
     */
    public function list_cutoff_runs(int $periodId, int $limit = 8): array
    {
        if ($periodId <= 0 || !$this->cutoff_run_schema_ready()) {
            return [];
        }

        return $this->db
            ->from('inv_stock_cutoff_run')
            ->where('period_id', $periodId)
            ->order_by('attempt_no', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min(30, $limit)))
            ->get()
            ->result_array();
    }

    public function period_close_preflight(string $stockDomain, string $periodMonth): array
    {
        $domain = strtoupper(trim($stockDomain));
        $month = $this->normalize_month($periodMonth);
        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true) || $month === null) {
            return ['ok' => false, 'message' => 'Data periode stok tidak valid.'];
        }

        $nextMonth = date('Y-m-d', strtotime($month . ' +1 month'));
        $openDeficit = 0;
        $openQty = 0.0;
        if ($this->db->table_exists('inv_stock_deficit')) {
            $row = $this->db->select('COUNT(*) AS total_rows, COALESCE(SUM(qty_remaining), 0) AS qty_remaining', false)
                ->from('inv_stock_deficit')
                ->where('stock_domain', $domain)
                ->where('status', 'OPEN')
                ->where('deficit_date >=', $month)
                ->where('deficit_date <', $nextMonth)
                ->get()
                ->row_array() ?: [];
            $openDeficit = (int)($row['total_rows'] ?? 0);
            $openQty = round((float)($row['qty_remaining'] ?? 0), 4);
        }

        $health = $this->inventory_health_summary($month);
        $domainHealth = $domain === 'MATERIAL'
            ? (array)($health['material'] ?? [])
            : (array)($health['component'] ?? []);
        $mismatchRows = (int)($domainHealth['mismatch_rows'] ?? 0);
        $valueMismatchRows = (int)($domainHealth['value_mismatch_rows'] ?? 0);
        $orphanLotRows = (int)($domainHealth['orphan_lot_rows'] ?? 0);
        $warnings = [];
        if ($openDeficit > 0) {
            $warnings[] = $openDeficit . ' defisit stok masih terbuka (' . number_format($openQty, 4, ',', '.') . ').';
        }
        if ($mismatchRows > 0) {
            $detail = [];
            if ($valueMismatchRows > 0) {
                $detail[] = $valueMismatchRows . ' selisih nilai';
            }
            if ($orphanLotRows > 0) {
                $detail[] = $orphanLotRows . ' lot tanpa stok bulanan';
            }
            $warnings[] = $mismatchRows . ' baris masih berbeda antara stok bulanan dan lot'
                . (!empty($detail) ? ' (' . implode(', ', $detail) . ')' : '') . '.';
        }

        return [
            'ok' => true,
            // Selisih dan defisit harus terlihat sebelum cut-off, tetapi tidak
            // boleh memaksa operator membuka kembali histori lama satu per satu.
            // Penutupan tetap memerlukan acknowledgement eksplisit di controller.
            'can_close' => true,
            'requires_acknowledgement' => !empty($warnings),
            'warnings' => $warnings,
            'open_deficit_count' => $openDeficit,
            'open_deficit_qty' => $openQty,
            'health' => $health,
        ];
    }

    /**
     * Read-only preview of the opening stock that would be carried forward
     * from the selected monthly stock. This intentionally does not call the
     * existing opname generators because those methods write snapshots, lots,
     * and carry-forward rows.
     */
    public function period_cutoff_preview(string $stockDomain, string $periodMonth, int $sampleLimit = 25): array
    {
        $domain = strtoupper(trim($stockDomain));
        $month = $this->normalize_month($periodMonth);
        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true) || $month === null) {
            return [
                'ok' => false,
                'message' => 'Data periode stok tidak valid untuk simulasi cut-off.',
            ];
        }

        $nextMonth = date('Y-m-01', strtotime($month . ' +1 month'));
        $sampleLimit = max(5, min(100, $sampleLimit));
        $rows = $domain === 'MATERIAL'
            ? $this->period_cutoff_material_source_rows($month, $nextMonth)
            : $this->period_cutoff_component_source_rows($month, $nextMonth);

        if ($rows === null) {
            return [
                'ok' => false,
                'message' => 'Tabel stok atau opening yang diperlukan untuk simulasi belum tersedia.',
            ];
        }

        $summary = [
            'source_row_count' => count($rows),
            'candidate_opening_count' => 0,
            'prepared_opening_count' => 0,
            'zero_closing_count' => 0,
            'negative_closing_count' => 0,
            'candidate_total_value' => 0.0,
        ];
        $candidates = [];
        foreach ($rows as $row) {
            $closingQty = round((float)($row['closing_qty'] ?? 0), 4);
            $row['closing_qty'] = $closingQty;
            $row['total_value'] = round((float)($row['total_value'] ?? 0), 2);
            $row['opening_ready'] = 0;
            $row['opening_status'] = 'Tidak dibawa';

            if ($closingQty > 0.0001) {
                $summary['candidate_opening_count']++;
                $summary['candidate_total_value'] += (float)$row['total_value'];
                $hasOpening = (int)($row['next_opening_exists'] ?? 0) === 1;
                $row['opening_ready'] = 1;
                $row['opening_status'] = $hasOpening ? 'Sudah ada opening' : 'Siap dibawa';
                if ($hasOpening) {
                    $summary['prepared_opening_count']++;
                }
                $candidates[] = $row;
            } elseif ($closingQty < -0.0001) {
                $summary['negative_closing_count']++;
                $row['opening_status'] = 'Perlu dibereskan (minus)';
            } else {
                $summary['zero_closing_count']++;
            }
        }

        usort($candidates, static function (array $left, array $right): int {
            $statusCompare = ((int)($left['next_opening_exists'] ?? 0)) <=> ((int)($right['next_opening_exists'] ?? 0));
            if ($statusCompare !== 0) {
                return $statusCompare;
            }
            $areaCompare = strcmp((string)($left['area_name'] ?? ''), (string)($right['area_name'] ?? ''));
            if ($areaCompare !== 0) {
                return $areaCompare;
            }
            return strcmp((string)($left['inventory_name'] ?? ''), (string)($right['inventory_name'] ?? ''));
        });

        $warnings = [];
        if ($summary['negative_closing_count'] > 0) {
            $warnings[] = $summary['negative_closing_count'] . ' baris masih minus sehingga tidak boleh dibawa sebagai stok awal.';
        }
        if ($summary['prepared_opening_count'] > 0) {
            $warnings[] = $summary['prepared_opening_count'] . ' calon stok awal sudah ada di bulan berikutnya. Tahap posting nanti wajib meninjau sumbernya agar tidak menimpa data manual.';
        }
        if (date('Y-m-t', strtotime($month)) >= date('Y-m-d')) {
            $warnings[] = 'Bulan ini masih berjalan; angka simulasi akan berubah mengikuti transaksi berikutnya.';
        }

        return [
            'ok' => true,
            'read_only' => true,
            'stock_domain' => $domain,
            'source_month' => $month,
            'opening_month' => $nextMonth,
            'summary' => [
                'source_row_count' => (int)$summary['source_row_count'],
                'candidate_opening_count' => (int)$summary['candidate_opening_count'],
                'prepared_opening_count' => (int)$summary['prepared_opening_count'],
                'zero_closing_count' => (int)$summary['zero_closing_count'],
                'negative_closing_count' => (int)$summary['negative_closing_count'],
                'candidate_total_value' => round((float)$summary['candidate_total_value'], 2),
            ],
            'rows' => array_slice($candidates, 0, $sampleLimit),
            'displayed_count' => min(count($candidates), $sampleLimit),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, array>|null null means the required tables are not ready.
     */
    private function period_cutoff_material_source_rows(string $month, string $nextMonth): ?array
    {
        if (!$this->db->table_exists('inv_warehouse_monthly_stock')
            || !$this->db->table_exists('inv_division_monthly_stock')
            || !$this->db->table_exists('inv_warehouse_stock_opening_snapshot')
            || !$this->db->table_exists('inv_division_stock_opening_snapshot')) {
            return null;
        }

        $monthSql = $this->db->escape($month);
        $nextMonthSql = $this->db->escape($nextMonth);
        $warehouseSql = "SELECT
                'WAREHOUSE' AS location_scope,
                'Gudang' AS area_name,
                'GUDANG' AS location_type,
                0 AS division_id,
                s.id AS monthly_stock_id,
                COALESCE(mi.item_name, mm.material_name, s.profile_name, '-') AS inventory_name,
                COALESCE(NULLIF(s.profile_name, ''), '-') AS profile_name,
                COALESCE(NULLIF(s.profile_brand, ''), '') AS profile_brand,
                COALESCE(s.profile_content_uom_code, u.code, '-') AS uom_code,
                COALESCE(s.closing_qty_content, 0) AS closing_qty,
                COALESCE(s.avg_cost_per_content, 0) AS unit_cost,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM inv_warehouse_stock_opening_snapshot o
                    WHERE o.snapshot_month = " . $nextMonthSql . "
                      AND o.item_id <=> s.item_id
                      AND o.material_id <=> s.material_id
                      AND o.buy_uom_id <=> s.buy_uom_id
                      AND o.content_uom_id = s.content_uom_id
                      AND o.profile_key <=> s.profile_key
                ) THEN 1 ELSE 0 END AS next_opening_exists
            FROM inv_warehouse_monthly_stock s
            LEFT JOIN mst_item mi ON mi.id = s.item_id
            LEFT JOIN mst_material mm ON mm.id = s.material_id
            LEFT JOIN mst_uom u ON u.id = s.content_uom_id
            WHERE s.month_key = " . $monthSql;

        $divisionSql = "SELECT
                'DIVISION' AS location_scope,
                COALESCE(od.name, CONCAT('DIV-', s.division_id)) AS area_name,
                COALESCE(s.destination_type, 'OTHER') AS location_type,
                COALESCE(s.division_id, 0) AS division_id,
                s.id AS monthly_stock_id,
                COALESCE(mi.item_name, mm.material_name, s.profile_name, '-') AS inventory_name,
                COALESCE(NULLIF(s.profile_name, ''), '-') AS profile_name,
                COALESCE(NULLIF(s.profile_brand, ''), '') AS profile_brand,
                COALESCE(s.profile_content_uom_code, u.code, '-') AS uom_code,
                COALESCE(s.closing_qty_content, 0) AS closing_qty,
                COALESCE(s.avg_cost_per_content, 0) AS unit_cost,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM inv_division_stock_opening_snapshot o
                    WHERE o.snapshot_month = " . $nextMonthSql . "
                      AND o.division_id = s.division_id
                      AND o.destination_type = s.destination_type
                      AND o.item_id <=> s.item_id
                      AND o.material_id <=> s.material_id
                      AND o.buy_uom_id <=> s.buy_uom_id
                      AND o.content_uom_id = s.content_uom_id
                      AND o.profile_key <=> s.profile_key
                ) THEN 1 ELSE 0 END AS next_opening_exists
            FROM inv_division_monthly_stock s
            LEFT JOIN mst_operational_division od ON od.id = s.division_id
            LEFT JOIN mst_item mi ON mi.id = s.item_id
            LEFT JOIN mst_material mm ON mm.id = s.material_id
            LEFT JOIN mst_uom u ON u.id = s.content_uom_id
            WHERE s.month_key = " . $monthSql . "
              AND COALESCE(s.destination_type, '') <> 'OTHER'";

        return $this->db->query($warehouseSql . ' UNION ALL ' . $divisionSql)->result_array();
    }

    /**
     * @return array<int, array>|null null means the required tables are not ready.
     */
    private function period_cutoff_component_source_rows(string $month, string $nextMonth): ?array
    {
        if (!$this->db->table_exists('inv_component_monthly_stock')
            || !$this->db->table_exists('inv_component_monthly_opening')) {
            return null;
        }

        $monthSql = $this->db->escape($month);
        $nextMonthSql = $this->db->escape($nextMonth);
        $sql = "SELECT
                'COMPONENT' AS location_scope,
                COALESCE(od.name, CASE WHEN s.division_id IS NULL THEN 'Lokasi pusat' ELSE CONCAT('DIV-', s.division_id) END) AS area_name,
                COALESCE(s.location_type, '-') AS location_type,
                COALESCE(s.division_id, 0) AS division_id,
                s.id AS monthly_stock_id,
                COALESCE(c.component_name, '-') AS inventory_name,
                '-' AS profile_name,
                '' AS profile_brand,
                COALESCE(u.code, '-') AS uom_code,
                COALESCE(s.closing_qty, 0) AS closing_qty,
                COALESCE(s.avg_cost, 0) AS unit_cost,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM inv_component_monthly_opening o
                    WHERE o.month_key = " . $nextMonthSql . "
                      AND o.location_type = s.location_type
                      AND o.division_id <=> s.division_id
                      AND o.component_id = s.component_id
                      AND o.uom_id = s.uom_id
                ) THEN 1 ELSE 0 END AS next_opening_exists
            FROM inv_component_monthly_stock s
            LEFT JOIN mst_operational_division od ON od.id = s.division_id
            LEFT JOIN mst_component c ON c.id = s.component_id
            LEFT JOIN mst_uom u ON u.id = s.uom_id
            WHERE s.month_key = " . $monthSql;

        return $this->db->query($sql)->result_array();
    }

    /**
     * Returns the active stock/lot/value exceptions that an operator should
     * review. This is intentionally read-only and never runs from POS.
     */
    public function count_inventory_health_rows(array $filters = []): int
    {
        $sql = $this->inventory_health_filtered_sql($filters);
        $row = $this->db->query('SELECT COUNT(*) AS total FROM (' . $sql . ') inventory_health_count')
            ->row_array() ?: [];
        return (int)($row['total'] ?? 0);
    }

    public function list_inventory_health_rows(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = $this->inventory_health_filtered_sql($filters)
            . " ORDER BY CASE issue_type
                    WHEN 'LOT_TANPA_STOK_BULANAN' THEN 0
                    WHEN 'QTY_DAN_NILAI' THEN 1
                    WHEN 'SELISIH_QTY' THEN 2
                    ELSE 3 END ASC,
                    ABS(qty_gap) DESC,
                    ABS(value_gap) DESC,
                    last_activity_at DESC,
                    inventory_name ASC"
            . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        return $this->db->query($sql)->result_array();
    }

    /**
     * Lightweight aggregate used by the period page and dashboard. The full
     * row-level list stays in the operator workspace, not in POS requests.
     */
    public function inventory_health_summary(string $periodMonth): array
    {
        $month = $this->normalize_month($periodMonth) ?? date('Y-m-01');
        if (isset($this->inventoryHealthSummaryCache[$month])) {
            return $this->inventoryHealthSummaryCache[$month];
        }

        $emptyDomain = [
            'checked_rows' => 0,
            'mismatch_rows' => 0,
            'quantity_mismatch_rows' => 0,
            'value_mismatch_rows' => 0,
            'orphan_lot_rows' => 0,
            'absolute_gap' => 0.0,
            'absolute_value_gap' => 0.0,
        ];
        $result = [
            'period_month' => $month,
            'as_of_date' => $this->inventory_health_as_of_date($month),
            'material' => $emptyDomain,
            'component' => $emptyDomain,
        ];

        $stockSql = $this->inventory_health_stock_rows_sql($month);
        $checkedRows = $this->db->query(
            'SELECT stock_domain, COUNT(*) AS checked_rows FROM (' . $stockSql . ') inventory_health_stock GROUP BY stock_domain'
        )->result_array();
        foreach ($checkedRows as $row) {
            $key = strtolower((string)($row['stock_domain'] ?? ''));
            if (isset($result[$key])) {
                $result[$key]['checked_rows'] = (int)($row['checked_rows'] ?? 0);
            }
        }

        $filteredSql = $this->inventory_health_filtered_sql(['month' => $month]);
        $mismatches = $this->db->query(
            "SELECT stock_domain,
                    COUNT(*) AS mismatch_rows,
                    COALESCE(SUM(CASE WHEN ABS(qty_gap) > 0.0001 THEN 1 ELSE 0 END), 0) AS quantity_mismatch_rows,
                    COALESCE(SUM(CASE WHEN ABS(value_gap) > 1 THEN 1 ELSE 0 END), 0) AS value_mismatch_rows,
                    COALESCE(SUM(CASE WHEN source_state = 'LOT_ONLY' THEN 1 ELSE 0 END), 0) AS orphan_lot_rows,
                    COALESCE(SUM(ABS(qty_gap)), 0) AS absolute_gap,
                    COALESCE(SUM(ABS(value_gap)), 0) AS absolute_value_gap
             FROM (" . $filteredSql . ") inventory_health_mismatch
             GROUP BY stock_domain"
        )->result_array();
        foreach ($mismatches as $row) {
            $key = strtolower((string)($row['stock_domain'] ?? ''));
            if (!isset($result[$key])) {
                continue;
            }
            $result[$key] = [
                'checked_rows' => (int)($result[$key]['checked_rows'] ?? 0),
                'mismatch_rows' => (int)($row['mismatch_rows'] ?? 0),
                'quantity_mismatch_rows' => (int)($row['quantity_mismatch_rows'] ?? 0),
                'value_mismatch_rows' => (int)($row['value_mismatch_rows'] ?? 0),
                'orphan_lot_rows' => (int)($row['orphan_lot_rows'] ?? 0),
                'absolute_gap' => round((float)($row['absolute_gap'] ?? 0), 4),
                'absolute_value_gap' => round((float)($row['absolute_value_gap'] ?? 0), 2),
            ];
        }

        $this->inventoryHealthSummaryCache[$month] = $result;
        return $result;
    }

    private function inventory_health_filtered_sql(array $filters = []): string
    {
        $month = $this->normalize_month((string)($filters['month'] ?? '')) ?? date('Y-m-01');
        $unionSql = $this->inventory_health_union_sql($month);
        $conditions = [
            '(ABS(COALESCE(h.stock_qty, 0) - COALESCE(h.lot_qty, 0)) > 0.0001'
                . ' OR ABS(COALESCE(h.stock_value, 0) - COALESCE(h.lot_value, 0)) > 1)',
        ];

        $domain = strtoupper(trim((string)($filters['stock_domain'] ?? '')));
        if (in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            $conditions[] = 'h.stock_domain = ' . $this->db->escape($domain);
        }
        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId > 0) {
            $conditions[] = 'h.division_id = ' . $divisionId;
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = $this->db->escape('%' . $this->db->escape_like_str($q) . '%');
            $conditions[] = '(h.inventory_name LIKE ' . $like . " ESCAPE '!'"
                . ' OR h.division_name LIKE ' . $like . " ESCAPE '!'"
                . ' OR h.profile_key LIKE ' . $like . " ESCAPE '!')";
        }

        return "SELECT h.*,
                    ROUND(COALESCE(h.stock_qty, 0) - COALESCE(h.lot_qty, 0), 4) AS qty_gap,
                    ROUND(COALESCE(h.stock_value, 0) - COALESCE(h.lot_value, 0), 2) AS value_gap,
                    CASE
                      WHEN h.source_state = 'LOT_ONLY' THEN 'LOT_TANPA_STOK_BULANAN'
                      WHEN ABS(COALESCE(h.stock_qty, 0) - COALESCE(h.lot_qty, 0)) > 0.0001
                       AND ABS(COALESCE(h.stock_value, 0) - COALESCE(h.lot_value, 0)) > 1 THEN 'QTY_DAN_NILAI'
                      WHEN ABS(COALESCE(h.stock_qty, 0) - COALESCE(h.lot_qty, 0)) > 0.0001 THEN 'SELISIH_QTY'
                      ELSE 'SELISIH_NILAI'
                    END AS issue_type
             FROM (" . $unionSql . ') h WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * A full outer join implemented with UNION ALL. MySQL/MariaDB do not have
     * FULL OUTER JOIN, while an active lot that has no monthly row is just as
     * relevant as a monthly row that lost its active lot. For a closed month,
     * the next month's opening snapshot is the immutable lot evidence because
     * the old FIFO lots have already been closed by the carry-forward process.
     */
    private function inventory_health_union_sql(string $month): string
    {
        $stockSql = $this->inventory_health_stock_rows_sql($month);
        $lotSql = $this->inventory_health_lot_rows_sql($this->inventory_health_as_of_date($month));
        $monthSql = $this->db->escape($month);

        return "SELECT s.monthly_stock_id, s.identity_key, s.stock_domain, s.location_scope, s.division_id, s.location_type,
                       s.item_id, s.material_id, s.component_id, s.buy_uom_id, s.uom_id, s.profile_key,
                       s.inventory_name, s.division_name, s.uom_code,
                       s.stock_qty, COALESCE(l.lot_qty, 0) AS lot_qty,
                       s.stock_value, COALESCE(l.lot_value, 0) AS lot_value,
                       s.last_stock_activity, l.last_lot_activity,
                       s.last_movement_table, s.last_movement_id,
                       COALESCE(l.lot_count, 0) AS lot_count,
                       'MONTHLY_STOCK' AS source_state,
                       COALESCE(s.last_stock_activity, l.last_lot_activity) AS last_activity_at
                FROM (" . $stockSql . ") s
                LEFT JOIN (" . $lotSql . ") l ON l.identity_key = s.identity_key
                UNION ALL
                SELECT NULL AS monthly_stock_id, l.identity_key, l.stock_domain, l.location_scope, l.division_id, l.location_type,
                       l.item_id, l.material_id, l.component_id, l.buy_uom_id, l.uom_id, l.profile_key,
                       l.inventory_name, l.division_name, l.uom_code,
                       0 AS stock_qty, l.lot_qty,
                       0 AS stock_value, l.lot_value,
                       NULL AS last_stock_activity, l.last_lot_activity,
                       NULL AS last_movement_table, NULL AS last_movement_id,
                       l.lot_count,
                       'LOT_ONLY' AS source_state,
                       l.last_lot_activity AS last_activity_at
                FROM (" . $lotSql . ") l
                LEFT JOIN (" . $stockSql . ") s ON s.identity_key = l.identity_key
                WHERE s.identity_key IS NULL
                  AND COALESCE(l.last_lot_activity, '') >= " . $monthSql;
    }

    private function inventory_health_stock_rows_sql(string $month): string
    {
        $monthSql = $this->db->escape($month);
        return "SELECT
                    s.id AS monthly_stock_id,
                    SHA2(CONCAT_WS('|', 'MATERIAL', 'DIVISION', COALESCE(s.division_id, 0), COALESCE(s.destination_type, ''), COALESCE(s.item_id, 0), COALESCE(s.material_id, 0), 0, COALESCE(s.buy_uom_id, 0), COALESCE(s.content_uom_id, 0), COALESCE(s.profile_key, '')), 256) AS identity_key,
                    'MATERIAL' AS stock_domain, 'DIVISION' AS location_scope,
                    COALESCE(s.division_id, 0) AS division_id, COALESCE(s.destination_type, '') AS location_type,
                    COALESCE(s.item_id, 0) AS item_id, COALESCE(s.material_id, 0) AS material_id, 0 AS component_id,
                    COALESCE(s.buy_uom_id, 0) AS buy_uom_id, COALESCE(s.content_uom_id, 0) AS uom_id,
                    COALESCE(s.profile_key, '') AS profile_key,
                    COALESCE(mi.item_name, mm.material_name, s.profile_name, '-') AS inventory_name,
                    COALESCE(od.name, CONCAT('DIV-', s.division_id)) AS division_name, COALESCE(u.code, '-') AS uom_code,
                    COALESCE(s.closing_qty_content, 0) AS stock_qty, COALESCE(s.total_value, 0) AS stock_value,
                    COALESCE(s.last_movement_at, s.updated_at, s.created_at) AS last_stock_activity,
                    s.last_movement_table, s.last_movement_id
                FROM inv_division_monthly_stock s
                LEFT JOIN mst_operational_division od ON od.id = s.division_id
                LEFT JOIN mst_item mi ON mi.id = s.item_id
                LEFT JOIN mst_material mm ON mm.id = s.material_id
                LEFT JOIN mst_uom u ON u.id = s.content_uom_id
                WHERE s.month_key = " . $monthSql . "
                  AND COALESCE(s.destination_type, '') <> 'OTHER'
                UNION ALL
                SELECT
                    s.id AS monthly_stock_id,
                    SHA2(CONCAT_WS('|', 'MATERIAL', 'WAREHOUSE', 0, 'GUDANG', COALESCE(s.item_id, 0), COALESCE(s.material_id, 0), 0, COALESCE(s.buy_uom_id, 0), COALESCE(s.content_uom_id, 0), COALESCE(s.profile_key, '')), 256) AS identity_key,
                    'MATERIAL' AS stock_domain, 'WAREHOUSE' AS location_scope,
                    0 AS division_id, 'GUDANG' AS location_type,
                    COALESCE(s.item_id, 0) AS item_id, COALESCE(s.material_id, 0) AS material_id, 0 AS component_id,
                    COALESCE(s.buy_uom_id, 0) AS buy_uom_id, COALESCE(s.content_uom_id, 0) AS uom_id,
                    COALESCE(s.profile_key, '') AS profile_key,
                    COALESCE(mi.item_name, mm.material_name, s.profile_name, '-') AS inventory_name,
                    'Gudang' AS division_name, COALESCE(u.code, '-') AS uom_code,
                    COALESCE(s.closing_qty_content, 0) AS stock_qty, COALESCE(s.total_value, 0) AS stock_value,
                    COALESCE(s.last_movement_at, s.updated_at, s.created_at) AS last_stock_activity,
                    s.last_movement_table, s.last_movement_id
                FROM inv_warehouse_monthly_stock s
                LEFT JOIN mst_item mi ON mi.id = s.item_id
                LEFT JOIN mst_material mm ON mm.id = s.material_id
                LEFT JOIN mst_uom u ON u.id = s.content_uom_id
                WHERE s.month_key = " . $monthSql . "
                UNION ALL
                SELECT
                    s.id AS monthly_stock_id,
                    SHA2(CONCAT_WS('|', 'COMPONENT', 'COMPONENT', COALESCE(s.division_id, 0), COALESCE(s.location_type, ''), 0, 0, COALESCE(s.component_id, 0), 0, COALESCE(s.uom_id, 0), ''), 256) AS identity_key,
                    'COMPONENT' AS stock_domain, 'COMPONENT' AS location_scope,
                    COALESCE(s.division_id, 0) AS division_id, COALESCE(s.location_type, '') AS location_type,
                    0 AS item_id, 0 AS material_id, COALESCE(s.component_id, 0) AS component_id,
                    0 AS buy_uom_id, COALESCE(s.uom_id, 0) AS uom_id, '' AS profile_key,
                    COALESCE(c.component_name, '-') AS inventory_name,
                    COALESCE(od.name, CASE WHEN s.division_id IS NULL THEN 'Lokasi pusat' ELSE CONCAT('DIV-', s.division_id) END) AS division_name,
                    COALESCE(u.code, '-') AS uom_code,
                    COALESCE(s.closing_qty, 0) AS stock_qty, COALESCE(s.total_value, 0) AS stock_value,
                    COALESCE(s.last_movement_at, s.updated_at, s.created_at) AS last_stock_activity,
                    s.last_movement_table, s.last_movement_id
                FROM inv_component_monthly_stock s
                LEFT JOIN mst_operational_division od ON od.id = s.division_id
                LEFT JOIN mst_component c ON c.id = s.component_id
                LEFT JOIN mst_uom u ON u.id = s.uom_id
                WHERE s.month_key = " . $monthSql;
    }

    /**
     * Live FIFO balance is only valid for the current period. After a period
     * is carried forward, historical lots are deliberately closed and replaced
     * by the next month's opening. Select that immutable opening source when
     * available, while retaining a live-FIFO fallback for installations that
     * have not generated an opening snapshot yet.
     */
    private function inventory_health_lot_rows_sql(string $asOfDate): string
    {
        $history = $this->inventory_health_historical_opening_context($asOfDate);
        $parts = [
            !empty($history['division_material'])
                ? $this->inventory_health_historical_division_material_lot_rows_sql((string)$history['next_month'])
                : $this->inventory_health_live_division_material_lot_rows_sql($asOfDate),
            !empty($history['warehouse_material'])
                ? $this->inventory_health_historical_warehouse_material_lot_rows_sql((string)$history['next_month'])
                : $this->inventory_health_live_warehouse_material_lot_rows_sql($asOfDate),
            !empty($history['component'])
                ? $this->inventory_health_historical_component_lot_rows_sql((string)$history['next_month'])
                : $this->inventory_health_live_component_lot_rows_sql($asOfDate),
        ];

        return implode("\nUNION ALL\n", $parts);
    }

    private function inventory_health_historical_opening_context(string $asOfDate): array
    {
        $monthStart = date('Y-m-01', strtotime($asOfDate));
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $empty = [
            'next_month' => '',
            'division_material' => false,
            'warehouse_material' => false,
            'component' => false,
        ];

        // A partial current month must continue to read live FIFO balances.
        if ($asOfDate !== $monthEnd || $monthEnd >= date('Y-m-d')) {
            return $empty;
        }

        $nextMonth = date('Y-m-01', strtotime($monthStart . ' +1 month'));
        $hasOpeningRows = function (string $table, string $monthColumn, array $sourceTypes) use ($nextMonth): bool {
            if (!$this->db->table_exists($table)) {
                return false;
            }

            $row = $this->db
                ->select('id')
                ->from($table)
                ->where($monthColumn, $nextMonth)
                ->where_in('source_type', $sourceTypes)
                ->limit(1)
                ->get()
                ->row_array();
            return !empty($row);
        };

        return [
            'next_month' => $nextMonth,
            'division_material' => $hasOpeningRows(
                'inv_division_stock_opening_snapshot',
                'snapshot_month',
                ['AUTO_REBUILD', 'OPNAME']
            ),
            'warehouse_material' => $hasOpeningRows(
                'inv_warehouse_stock_opening_snapshot',
                'snapshot_month',
                ['AUTO_REBUILD', 'OPNAME']
            ),
            'component' => $hasOpeningRows(
                'inv_component_monthly_opening',
                'month_key',
                ['AUTO_CARRY_FORWARD', 'AUTO_REBUILD', 'OPNAME']
            ),
        ];
    }

    private function inventory_health_live_division_material_lot_rows_sql(string $asOfDate): string
    {
        $asOfSql = $this->db->escape($asOfDate);
        return "SELECT
                    SHA2(CONCAT_WS('|', 'MATERIAL', 'DIVISION', COALESCE(l.division_id, 0), COALESCE(l.destination_type, ''), COALESCE(l.item_id, 0), COALESCE(l.material_id, 0), 0, COALESCE(l.buy_uom_id, 0), COALESCE(l.content_uom_id, 0), COALESCE(l.profile_key, '')), 256) AS identity_key,
                    'MATERIAL' AS stock_domain, 'DIVISION' AS location_scope,
                    COALESCE(l.division_id, 0) AS division_id, COALESCE(l.destination_type, '') AS location_type,
                    COALESCE(l.item_id, 0) AS item_id, COALESCE(l.material_id, 0) AS material_id, 0 AS component_id,
                    COALESCE(l.buy_uom_id, 0) AS buy_uom_id, COALESCE(l.content_uom_id, 0) AS uom_id,
                    COALESCE(l.profile_key, '') AS profile_key,
                    COALESCE(mi.item_name, mm.material_name, '-') AS inventory_name,
                    COALESCE(od.name, CONCAT('DIV-', l.division_id)) AS division_name, COALESCE(u.code, '-') AS uom_code,
                    COALESCE(SUM(l.qty_balance), 0) AS lot_qty,
                    COALESCE(SUM(l.qty_balance * l.unit_cost), 0) AS lot_value,
                    COALESCE(SUM(CASE WHEN ABS(COALESCE(l.qty_balance, 0)) > 0.0001 THEN 1 ELSE 0 END), 0) AS lot_count,
                    MAX(COALESCE(l.updated_at, l.created_at)) AS last_lot_activity
                FROM inv_material_fifo_lot l
                LEFT JOIN mst_operational_division od ON od.id = l.division_id
                LEFT JOIN mst_item mi ON mi.id = l.item_id
                LEFT JOIN mst_material mm ON mm.id = l.material_id
                LEFT JOIN mst_uom u ON u.id = l.content_uom_id
                WHERE l.location_scope = 'DIVISION'
                  AND UPPER(COALESCE(l.status, 'OPEN')) = 'OPEN'
                  AND l.receipt_date <= " . $asOfSql . "
                GROUP BY l.division_id, l.destination_type, l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key";
    }

    private function inventory_health_live_warehouse_material_lot_rows_sql(string $asOfDate): string
    {
        $asOfSql = $this->db->escape($asOfDate);
        // Warehouse operates in aggregate-profile mode. Older receipt lots are
        // retained as provenance, but once a WAREHOUSE_PROFILE lot exists it is
        // the sole FIFO representation of the live monthly balance.
        return "SELECT
                    SHA2(CONCAT_WS('|', 'MATERIAL', 'WAREHOUSE', 0, 'GUDANG', COALESCE(l.item_id, 0), COALESCE(l.material_id, 0), 0, COALESCE(l.buy_uom_id, 0), COALESCE(l.content_uom_id, 0), COALESCE(l.profile_key, '')), 256) AS identity_key,
                    'MATERIAL' AS stock_domain, 'WAREHOUSE' AS location_scope,
                    0 AS division_id, 'GUDANG' AS location_type,
                    COALESCE(l.item_id, 0) AS item_id, COALESCE(l.material_id, 0) AS material_id, 0 AS component_id,
                    COALESCE(l.buy_uom_id, 0) AS buy_uom_id, COALESCE(l.content_uom_id, 0) AS uom_id,
                    COALESCE(l.profile_key, '') AS profile_key,
                    COALESCE(mi.item_name, mm.material_name, '-') AS inventory_name,
                    'Gudang' AS division_name, COALESCE(u.code, '-') AS uom_code,
                    COALESCE(SUM(l.qty_balance), 0) AS lot_qty,
                    COALESCE(SUM(l.qty_balance * l.unit_cost), 0) AS lot_value,
                    COALESCE(SUM(CASE WHEN ABS(COALESCE(l.qty_balance, 0)) > 0.0001 THEN 1 ELSE 0 END), 0) AS lot_count,
                    MAX(COALESCE(l.updated_at, l.created_at)) AS last_lot_activity
                FROM inv_material_fifo_lot l
                LEFT JOIN mst_item mi ON mi.id = l.item_id
                LEFT JOIN mst_material mm ON mm.id = l.material_id
                LEFT JOIN mst_uom u ON u.id = l.content_uom_id
                WHERE l.location_scope = 'WAREHOUSE'
                  AND l.receipt_date <= " . $asOfSql . "
                  AND (
                    COALESCE(l.source_table, '') = 'WAREHOUSE_PROFILE'
                    OR (
                        UPPER(COALESCE(l.status, 'OPEN')) = 'OPEN'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM inv_material_fifo_lot aggregate_lot
                            WHERE aggregate_lot.location_scope = 'WAREHOUSE'
                              AND aggregate_lot.source_table = 'WAREHOUSE_PROFILE'
                              AND aggregate_lot.item_id <=> l.item_id
                              AND aggregate_lot.material_id <=> l.material_id
                              AND aggregate_lot.buy_uom_id <=> l.buy_uom_id
                              AND aggregate_lot.content_uom_id = l.content_uom_id
                              AND aggregate_lot.profile_key <=> l.profile_key
                        )
                    )
                  )
                GROUP BY l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key";
    }

    private function inventory_health_live_component_lot_rows_sql(string $asOfDate): string
    {
        $asOfSql = $this->db->escape($asOfDate);
        return "SELECT
                    SHA2(CONCAT_WS('|', 'COMPONENT', 'COMPONENT', COALESCE(l.division_id, 0), COALESCE(l.location_type, ''), 0, 0, COALESCE(l.component_id, 0), 0, COALESCE(l.uom_id, 0), ''), 256) AS identity_key,
                    'COMPONENT' AS stock_domain, 'COMPONENT' AS location_scope,
                    COALESCE(l.division_id, 0) AS division_id, COALESCE(l.location_type, '') AS location_type,
                    0 AS item_id, 0 AS material_id, COALESCE(l.component_id, 0) AS component_id,
                    0 AS buy_uom_id, COALESCE(l.uom_id, 0) AS uom_id, '' AS profile_key,
                    COALESCE(c.component_name, '-') AS inventory_name,
                    COALESCE(od.name, CASE WHEN l.division_id IS NULL THEN 'Lokasi pusat' ELSE CONCAT('DIV-', l.division_id) END) AS division_name,
                    COALESCE(u.code, '-') AS uom_code,
                    COALESCE(SUM(l.qty_balance), 0) AS lot_qty,
                    COALESCE(SUM(l.qty_balance * l.unit_cost), 0) AS lot_value,
                    COALESCE(SUM(CASE WHEN ABS(COALESCE(l.qty_balance, 0)) > 0.0001 THEN 1 ELSE 0 END), 0) AS lot_count,
                    MAX(COALESCE(l.updated_at, l.created_at)) AS last_lot_activity
                FROM inv_component_lot l
                LEFT JOIN mst_operational_division od ON od.id = l.division_id
                LEFT JOIN mst_component c ON c.id = l.component_id
                LEFT JOIN mst_uom u ON u.id = l.uom_id
                WHERE UPPER(COALESCE(l.status, 'OPEN')) = 'OPEN'
                  AND l.receipt_date <= " . $asOfSql . "
                GROUP BY l.location_type, l.division_id, l.component_id, l.uom_id";
    }

    private function inventory_health_historical_division_material_lot_rows_sql(string $nextMonth): string
    {
        $nextMonthSql = $this->db->escape($nextMonth);
        return "SELECT
                    SHA2(CONCAT_WS('|', 'MATERIAL', 'DIVISION', COALESCE(s.division_id, 0), COALESCE(s.destination_type, ''), COALESCE(s.item_id, 0), COALESCE(s.material_id, 0), 0, COALESCE(s.buy_uom_id, 0), COALESCE(s.content_uom_id, 0), COALESCE(s.profile_key, '')), 256) AS identity_key,
                    'MATERIAL' AS stock_domain, 'DIVISION' AS location_scope,
                    COALESCE(s.division_id, 0) AS division_id, COALESCE(s.destination_type, '') AS location_type,
                    COALESCE(s.item_id, 0) AS item_id, COALESCE(s.material_id, 0) AS material_id, 0 AS component_id,
                    COALESCE(s.buy_uom_id, 0) AS buy_uom_id, COALESCE(s.content_uom_id, 0) AS uom_id,
                    COALESCE(s.profile_key, '') AS profile_key,
                    COALESCE(mi.item_name, mm.material_name, s.profile_name, '-') AS inventory_name,
                    COALESCE(od.name, CONCAT('DIV-', s.division_id)) AS division_name, COALESCE(u.code, '-') AS uom_code,
                    COALESCE(SUM(l.qty_in), 0) AS lot_qty,
                    COALESCE(SUM(l.qty_in * l.unit_cost), 0) AS lot_value,
                    COALESCE(SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS lot_count,
                    MAX(COALESCE(l.updated_at, l.created_at, s.updated_at, s.created_at)) AS last_lot_activity
                FROM inv_division_stock_opening_snapshot s
                LEFT JOIN inv_material_fifo_lot l
                  ON l.location_scope = 'DIVISION'
                 AND l.source_table = 'inv_division_stock_opening_snapshot'
                 AND l.source_id = s.id
                LEFT JOIN mst_operational_division od ON od.id = s.division_id
                LEFT JOIN mst_item mi ON mi.id = s.item_id
                LEFT JOIN mst_material mm ON mm.id = s.material_id
                LEFT JOIN mst_uom u ON u.id = s.content_uom_id
                WHERE s.snapshot_month = " . $nextMonthSql . "
                  AND s.source_type IN ('AUTO_REBUILD', 'OPNAME')
                  AND COALESCE(s.destination_type, '') <> 'OTHER'
                GROUP BY s.division_id, s.destination_type, s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, s.profile_key";
    }

    private function inventory_health_historical_warehouse_material_lot_rows_sql(string $nextMonth): string
    {
        $nextMonthSql = $this->db->escape($nextMonth);
        return "SELECT
                    SHA2(CONCAT_WS('|', 'MATERIAL', 'WAREHOUSE', 0, 'GUDANG', COALESCE(s.item_id, 0), COALESCE(s.material_id, 0), 0, COALESCE(s.buy_uom_id, 0), COALESCE(s.content_uom_id, 0), COALESCE(s.profile_key, '')), 256) AS identity_key,
                    'MATERIAL' AS stock_domain, 'WAREHOUSE' AS location_scope,
                    0 AS division_id, 'GUDANG' AS location_type,
                    COALESCE(s.item_id, 0) AS item_id, COALESCE(s.material_id, 0) AS material_id, 0 AS component_id,
                    COALESCE(s.buy_uom_id, 0) AS buy_uom_id, COALESCE(s.content_uom_id, 0) AS uom_id,
                    COALESCE(s.profile_key, '') AS profile_key,
                    COALESCE(mi.item_name, mm.material_name, s.profile_name, '-') AS inventory_name,
                    'Gudang' AS division_name, COALESCE(u.code, '-') AS uom_code,
                    COALESCE(SUM(s.opening_qty_content), 0) AS lot_qty,
                    COALESCE(SUM(s.opening_total_value), 0) AS lot_value,
                    COUNT(s.id) AS lot_count,
                    MAX(COALESCE(s.updated_at, s.created_at)) AS last_lot_activity
                FROM inv_warehouse_stock_opening_snapshot s
                LEFT JOIN mst_item mi ON mi.id = s.item_id
                LEFT JOIN mst_material mm ON mm.id = s.material_id
                LEFT JOIN mst_uom u ON u.id = s.content_uom_id
                WHERE s.snapshot_month = " . $nextMonthSql . "
                  AND s.source_type IN ('AUTO_REBUILD', 'OPNAME')
                GROUP BY s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, s.profile_key";
    }

    private function inventory_health_historical_component_lot_rows_sql(string $nextMonth): string
    {
        $nextMonthSql = $this->db->escape($nextMonth);
        return "SELECT
                    SHA2(CONCAT_WS('|', 'COMPONENT', 'COMPONENT', COALESCE(o.division_id, 0), COALESCE(o.location_type, ''), 0, 0, COALESCE(o.component_id, 0), 0, COALESCE(o.uom_id, 0), ''), 256) AS identity_key,
                    'COMPONENT' AS stock_domain, 'COMPONENT' AS location_scope,
                    COALESCE(o.division_id, 0) AS division_id, COALESCE(o.location_type, '') AS location_type,
                    0 AS item_id, 0 AS material_id, COALESCE(o.component_id, 0) AS component_id,
                    0 AS buy_uom_id, COALESCE(o.uom_id, 0) AS uom_id, '' AS profile_key,
                    COALESCE(c.component_name, '-') AS inventory_name,
                    COALESCE(od.name, CASE WHEN o.division_id IS NULL THEN 'Lokasi pusat' ELSE CONCAT('DIV-', o.division_id) END) AS division_name,
                    COALESCE(u.code, '-') AS uom_code,
                    COALESCE(SUM(l.qty_in_total), 0) AS lot_qty,
                    COALESCE(SUM(l.qty_in_total * l.unit_cost), 0) AS lot_value,
                    COALESCE(SUM(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS lot_count,
                    MAX(COALESCE(l.updated_at, l.created_at, o.updated_at, o.created_at)) AS last_lot_activity
                FROM inv_component_monthly_opening o
                LEFT JOIN inv_component_lot l
                  ON l.source_table = 'inv_component_monthly_opening'
                 AND l.source_id = o.id
                LEFT JOIN mst_operational_division od ON od.id = o.division_id
                LEFT JOIN mst_component c ON c.id = o.component_id
                LEFT JOIN mst_uom u ON u.id = o.uom_id
                WHERE o.month_key = " . $nextMonthSql . "
                  AND o.source_type IN ('AUTO_CARRY_FORWARD', 'AUTO_REBUILD', 'OPNAME')
                GROUP BY o.location_type, o.division_id, o.component_id, o.uom_id";
    }

    private function inventory_health_as_of_date(string $month): string
    {
        $periodEnd = date('Y-m-t', strtotime($month));
        return $periodEnd < date('Y-m-d') ? $periodEnd : date('Y-m-d');
    }

    private function deficit_query_base()
    {
        return $this->db
            ->from('inv_stock_deficit d')
            ->join('mst_operational_division od', 'od.id = d.division_id', 'left')
            ->join('mst_item mi', 'mi.id = d.item_id', 'left')
            ->join('mst_material mm', 'mm.id = d.material_id', 'left')
            ->join('mst_component mc', 'mc.id = d.component_id', 'left')
            ->join('mst_uom u', 'u.id = d.content_uom_id', 'left');
    }

    /**
     * One screen row represents the current obligation for one exact stock
     * identity. The source events remain separate in inv_stock_deficit so a
     * POS void/refund can still be reversed without touching another event.
     */
    private function apply_deficit_group_by($db): void
    {
        $db->group_by([
            'd.status',
            'd.stock_domain',
            'd.location_scope',
            'd.division_id',
            'd.destination_type',
            'd.item_id',
            'd.material_id',
            'd.component_id',
            'd.buy_uom_id',
            'd.content_uom_id',
            'd.profile_key',
        ]);
    }

    private function apply_deficit_identity_filter($db, array $reference, bool $sameStatus): void
    {
        $identityColumns = [
            'stock_domain',
            'location_scope',
            'division_id',
            'destination_type',
            'item_id',
            'material_id',
            'component_id',
            'buy_uom_id',
            'content_uom_id',
            'profile_key',
        ];
        if ($sameStatus) {
            $identityColumns[] = 'status';
        }
        foreach ($identityColumns as $column) {
            $db->where('d.' . $column . ' <=> ' . $this->db->escape($reference[$column] ?? null), null, false);
        }
    }

    /**
     * Deficit rows do not duplicate purchase-profile text by design. Resolve
     * it here so the operator can identify a material before reconciling it.
     */
    private function enrich_deficit_profile_metadata(array $rows): array
    {
        if (empty($rows) || !$this->db->table_exists('mst_purchase_catalog')) {
            return $this->finalize_deficit_profile_metadata($rows, []);
        }

        $profileKeys = [];
        $itemIds = [];
        $materialIds = [];
        foreach ($rows as $row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $profileKeys[$profileKey] = $profileKey;
            }
            $itemId = (int)($row['item_id'] ?? 0);
            if ($itemId > 0) {
                $itemIds[$itemId] = $itemId;
            }
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0) {
                $materialIds[$materialId] = $materialId;
            }
        }
        if (empty($profileKeys) && empty($itemIds) && empty($materialIds)) {
            return $this->finalize_deficit_profile_metadata($rows, []);
        }

        $hasProfileKey = $this->db->field_exists('profile_key', 'mst_purchase_catalog');
        $hasItem = $this->db->field_exists('item_id', 'mst_purchase_catalog');
        $hasMaterial = $this->db->field_exists('material_id', 'mst_purchase_catalog');
        if (!$hasProfileKey && !$hasItem && !$hasMaterial) {
            return $this->finalize_deficit_profile_metadata($rows, []);
        }

        $hasLastPurchaseDate = $this->db->field_exists('last_purchase_date', 'mst_purchase_catalog');
        $hasActive = $this->db->field_exists('is_active', 'mst_purchase_catalog');
        $hasContentPerBuy = $this->db->field_exists('content_per_buy', 'mst_purchase_catalog');
        $hasLastUnitPrice = $this->db->field_exists('last_unit_price', 'mst_purchase_catalog');
        $hasStandardPrice = $this->db->field_exists('standard_price', 'mst_purchase_catalog');

        $catalogQuery = $this->db
            ->select('c.id')
            ->select($hasProfileKey ? 'c.profile_key' : 'NULL AS profile_key', false)
            ->select($hasItem ? 'c.item_id' : 'NULL AS item_id', false)
            ->select($hasMaterial ? 'c.material_id' : 'NULL AS material_id', false)
            ->select($this->db->field_exists('buy_uom_id', 'mst_purchase_catalog') ? 'c.buy_uom_id' : 'NULL AS buy_uom_id', false)
            ->select($this->db->field_exists('content_uom_id', 'mst_purchase_catalog') ? 'c.content_uom_id' : 'NULL AS content_uom_id', false)
            ->select($hasContentPerBuy ? 'COALESCE(NULLIF(c.content_per_buy, 0), 1) AS content_per_buy' : '1 AS content_per_buy', false)
            ->select($this->db->field_exists('catalog_name', 'mst_purchase_catalog') ? 'c.catalog_name' : "'' AS catalog_name", false)
            ->select($this->db->field_exists('brand_name', 'mst_purchase_catalog') ? 'c.brand_name' : "'' AS brand_name", false)
            ->select($this->db->field_exists('line_description', 'mst_purchase_catalog') ? 'c.line_description' : "'' AS line_description", false)
            ->select($hasLastUnitPrice ? 'COALESCE(c.last_unit_price, 0) AS last_unit_price' : '0 AS last_unit_price', false)
            ->select($hasStandardPrice ? 'COALESCE(c.standard_price, 0) AS standard_price' : '0 AS standard_price', false)
            ->select($hasLastPurchaseDate ? 'c.last_purchase_date' : 'NULL AS last_purchase_date', false)
            ->from('mst_purchase_catalog c');
        if ($hasActive) {
            $catalogQuery->where('COALESCE(c.is_active, 1) = 1', null, false);
        }

        $catalogQuery->group_start();
        $hasPredicate = false;
        if ($hasProfileKey && !empty($profileKeys)) {
            $catalogQuery->where_in('c.profile_key', array_values($profileKeys));
            $hasPredicate = true;
        }
        if ($hasItem && !empty($itemIds)) {
            $hasPredicate ? $catalogQuery->or_where_in('c.item_id', array_values($itemIds)) : $catalogQuery->where_in('c.item_id', array_values($itemIds));
            $hasPredicate = true;
        }
        if ($hasMaterial && !empty($materialIds)) {
            $hasPredicate ? $catalogQuery->or_where_in('c.material_id', array_values($materialIds)) : $catalogQuery->where_in('c.material_id', array_values($materialIds));
        }
        $catalogQuery->group_end();

        $catalogRows = $catalogQuery
            ->order_by($hasLastPurchaseDate ? 'c.last_purchase_date' : 'c.id', 'DESC')
            ->order_by('c.id', 'DESC')
            ->get()
            ->result_array();

        return $this->finalize_deficit_profile_metadata($rows, $catalogRows);
    }

    /**
     * POS stock commits can split one order line into several material or
     * component consumptions. Deficits retain the commit-line reference, so
     * resolve it here instead of duplicating product/order data in stock rows.
     */
    private function enrich_deficit_source_context(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $posLineIds = [];
        foreach ($rows as &$row) {
            $row['cause_product_name'] = '';
            $row['cause_product_type'] = '';
            $row['cause_product_id'] = 0;
            $row['cause_extra_id'] = 0;
            $row['cause_order_no'] = '';
            $row['cause_commit_no'] = '';
            $row['cause_reference_label'] = '';

            $sourceTable = strtolower(trim((string)($row['source_table'] ?? '')));
            if ($sourceTable === 'pos_stock_commit') {
                $sourceLineId = (int)($row['source_line_id'] ?? 0);
                if ($sourceLineId > 0) {
                    $posLineIds[$sourceLineId] = $sourceLineId;
                } elseif ((int)($row['source_id'] ?? 0) > 0) {
                    $row['cause_reference_label'] = 'Commit POS #' . (int)$row['source_id'];
                }
            } elseif ($sourceTable === 'inv_stock_adjustment') {
                $row['cause_reference_label'] = 'Penyesuaian stok';
            }
        }
        unset($row);

        if (
            empty($posLineIds)
            || !$this->db->table_exists('pos_stock_commit_line')
            || !$this->db->table_exists('pos_stock_commit')
            || !$this->db->table_exists('pos_order')
            || !$this->db->table_exists('mst_product')
            || !$this->db->table_exists('mst_extra')
        ) {
            return $rows;
        }

        $contextRows = $this->db
            ->select('cl.id AS source_line_id, cl.commit_id AS source_id, cl.line_type, cl.product_id, cl.extra_id')
            ->select('c.commit_no, po.order_no, p.product_name, e.extra_name')
            ->from('pos_stock_commit_line cl')
            ->join('pos_stock_commit c', 'c.id = cl.commit_id', 'left')
            ->join('pos_order po', 'po.id = cl.order_id', 'left')
            ->join('mst_product p', 'p.id = cl.product_id', 'left')
            ->join('mst_extra e', 'e.id = cl.extra_id', 'left')
            ->where_in('cl.id', array_values($posLineIds))
            ->get()
            ->result_array();

        $contextByLineId = [];
        foreach ($contextRows as $context) {
            $sourceLineId = (int)($context['source_line_id'] ?? 0);
            if ($sourceLineId > 0) {
                $contextByLineId[$sourceLineId] = $context;
            }
        }

        foreach ($rows as &$row) {
            if (strtolower(trim((string)($row['source_table'] ?? ''))) !== 'pos_stock_commit') {
                continue;
            }

            $sourceLineId = (int)($row['source_line_id'] ?? 0);
            $context = $contextByLineId[$sourceLineId] ?? [];
            if (empty($context)) {
                continue;
            }

            $lineType = strtoupper(trim((string)($context['line_type'] ?? 'PRODUCT')));
            $isExtra = $lineType === 'EXTRA';
            $productName = trim((string)($isExtra
                ? ($context['extra_name'] ?? '')
                : ($context['product_name'] ?? '')));
            $fallbackId = (int)($isExtra ? ($context['extra_id'] ?? 0) : ($context['product_id'] ?? 0));

            $row['cause_product_name'] = $productName !== ''
                ? $productName
                : (($isExtra ? 'Extra POS' : 'Produk POS') . ($fallbackId > 0 ? ' #' . $fallbackId : ''));
            $row['cause_product_type'] = $isExtra ? 'Extra POS' : 'Produk POS';
            $row['cause_product_id'] = (int)($context['product_id'] ?? 0);
            $row['cause_extra_id'] = (int)($context['extra_id'] ?? 0);
            $row['cause_order_no'] = trim((string)($context['order_no'] ?? ''));
            $row['cause_commit_no'] = trim((string)($context['commit_no'] ?? ''));
            $row['cause_reference_label'] = $row['cause_order_no'] !== ''
                ? $row['cause_order_no']
                : ($row['cause_commit_no'] !== '' ? $row['cause_commit_no'] : 'Commit POS #' . (int)($context['source_id'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    /**
     * Combine repeated material/component shortages from the same POS product
     * so the detail page can show the operational cause, not only the stock
     * item that happened to be unavailable.
     */
    private function summarize_deficit_product_causes(array $events): array
    {
        $groups = [];
        foreach ($events as $event) {
            $productName = trim((string)($event['cause_product_name'] ?? ''));
            if ($productName === '') {
                continue;
            }

            $productType = trim((string)($event['cause_product_type'] ?? 'Produk POS'));
            $productId = (int)($event['cause_product_id'] ?? 0);
            $extraId = (int)($event['cause_extra_id'] ?? 0);
            $key = $productType . '|' . $productId . '|' . $extraId . '|' . strtolower($productName);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'product_name' => $productName,
                    'product_type' => $productType,
                    'event_count' => 0,
                    'requested_qty' => 0.0,
                    'issued_qty' => 0.0,
                    'qty_remaining' => 0.0,
                    'order_labels' => [],
                ];
            }

            $groups[$key]['event_count']++;
            $groups[$key]['requested_qty'] += (float)($event['requested_qty'] ?? 0);
            $groups[$key]['issued_qty'] += (float)($event['issued_qty'] ?? 0);
            $groups[$key]['qty_remaining'] += (float)($event['qty_remaining'] ?? 0);

            $reference = trim((string)($event['cause_order_no'] ?? ''));
            if ($reference === '') {
                $reference = trim((string)($event['cause_commit_no'] ?? ''));
            }
            if ($reference !== '') {
                $groups[$key]['order_labels'][$reference] = $reference;
            }
        }

        $causes = array_values($groups);
        foreach ($causes as &$cause) {
            $cause['requested_qty'] = round((float)$cause['requested_qty'], 4);
            $cause['issued_qty'] = round((float)$cause['issued_qty'], 4);
            $cause['qty_remaining'] = round((float)$cause['qty_remaining'], 4);
            $cause['order_labels'] = array_values($cause['order_labels']);
            $cause['order_count'] = count($cause['order_labels']);
        }
        unset($cause);

        usort($causes, static function (array $left, array $right): int {
            $remainingCompare = (float)($right['qty_remaining'] ?? 0) <=> (float)($left['qty_remaining'] ?? 0);
            if ($remainingCompare !== 0) {
                return $remainingCompare;
            }
            $requestedCompare = (float)($right['requested_qty'] ?? 0) <=> (float)($left['requested_qty'] ?? 0);
            if ($requestedCompare !== 0) {
                return $requestedCompare;
            }
            return strcasecmp((string)($left['product_name'] ?? ''), (string)($right['product_name'] ?? ''));
        });

        return $causes;
    }

    /**
     * Add the active-month stock and FIFO balance to each deficit row.
     * A deficit is an obligation from an earlier shortage, so it can remain
     * open even when a later receipt has already made the current stock
     * positive. Showing both values prevents an operator from treating the
     * deficit number as the current physical stock.
     */
    private function enrich_deficit_live_snapshot(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $activeMonth = date('Y-m-01');
        $materialRows = [];
        $componentRows = [];
        foreach ($rows as $row) {
            if (strtoupper((string)($row['stock_domain'] ?? '')) === 'COMPONENT') {
                $componentRows[] = $row;
            } else {
                $materialRows[] = $row;
            }
        }

        $materialMap = $this->load_material_live_snapshot_map($materialRows, $activeMonth);
        $componentMap = $this->load_component_live_snapshot_map($componentRows, $activeMonth);

        foreach ($rows as &$row) {
            $key = $this->deficit_live_key($row);
            $isComponent = strtoupper((string)($row['stock_domain'] ?? '')) === 'COMPONENT';
            $snapshot = $isComponent
                ? ($componentMap[$key] ?? $this->empty_live_snapshot())
                : ($materialMap[$key] ?? $this->empty_live_snapshot());
            $row['active_stock_month'] = $activeMonth;
            $row['system_qty'] = round((float)($snapshot['system_qty'] ?? 0), 4);
            $row['system_avg_cost'] = round((float)($snapshot['system_avg_cost'] ?? 0), 6);
            $row['system_total_value'] = round((float)($snapshot['system_total_value'] ?? 0), 2);
            $row['active_lot_qty'] = round((float)($snapshot['active_lot_qty'] ?? 0), 4);
            $row['active_lot_count'] = (int)($snapshot['active_lot_count'] ?? 0);
            $row['live_snapshot_found'] = !empty($snapshot['live_snapshot_found']) ? 1 : 0;
        }
        unset($row);

        return $rows;
    }

    private function load_material_live_snapshot_map(array $rows, string $activeMonth): array
    {
        if (empty($rows)) {
            return [];
        }

        $wantedKeys = [];
        $divisionIds = [];
        $itemIds = [];
        $materialIds = [];
        $hasWarehouse = false;
        foreach ($rows as $row) {
            $wantedKeys[$this->deficit_live_key($row)] = true;
            $scope = strtoupper((string)($row['location_scope'] ?? 'DIVISION'));
            if ($scope === 'WAREHOUSE') {
                $hasWarehouse = true;
            } elseif ((int)($row['division_id'] ?? 0) > 0) {
                $divisionIds[(int)$row['division_id']] = (int)$row['division_id'];
            }
            if ((int)($row['item_id'] ?? 0) > 0) {
                $itemIds[(int)$row['item_id']] = (int)$row['item_id'];
            }
            if ((int)($row['material_id'] ?? 0) > 0) {
                $materialIds[(int)$row['material_id']] = (int)$row['material_id'];
            }
        }
        if (empty($itemIds) && empty($materialIds)) {
            return [];
        }

        $map = [];
        if (!empty($divisionIds) && $this->db->table_exists('inv_division_monthly_stock')) {
            $query = $this->db
                ->select('s.division_id, s.destination_type, s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, s.profile_key, s.identity_key')
                ->select('s.closing_qty_content, s.avg_cost_per_content, s.total_value')
                ->from('inv_division_monthly_stock s')
                ->where('s.month_key', $activeMonth)
                ->where_in('s.division_id', array_values($divisionIds));
            $this->apply_material_item_or_material_filter($query, $itemIds, $materialIds, 's');
            $stockRows = $query->get()->result_array();
            foreach ($stockRows as $stock) {
                $this->append_material_stock_snapshot($map, $wantedKeys, $stock, 'DIVISION');
            }
        }

        if ($hasWarehouse && $this->db->table_exists('inv_warehouse_monthly_stock')) {
            $query = $this->db
                ->select("NULL AS division_id, NULL AS destination_type, s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, s.profile_key, s.identity_key", false)
                ->select('s.closing_qty_content, s.avg_cost_per_content, s.total_value')
                ->from('inv_warehouse_monthly_stock s')
                ->where('s.month_key', $activeMonth);
            $this->apply_material_item_or_material_filter($query, $itemIds, $materialIds, 's');
            $stockRows = $query->get()->result_array();
            foreach ($stockRows as $stock) {
                $this->append_material_stock_snapshot($map, $wantedKeys, $stock, 'WAREHOUSE');
            }
        }

        if ($this->db->table_exists('inv_material_fifo_lot')) {
            $query = $this->db
                ->select('l.location_scope, l.division_id, l.destination_type, l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key')
                ->select('l.qty_balance, l.unit_cost')
                ->from('inv_material_fifo_lot l')
                ->where('l.status', 'OPEN')
                ->where('l.qty_balance >', 0);
            $scopes = [];
            if (!empty($divisionIds)) {
                $scopes[] = 'DIVISION';
            }
            if ($hasWarehouse) {
                $scopes[] = 'WAREHOUSE';
            }
            if (!empty($scopes)) {
                $query->where_in('l.location_scope', $scopes);
            }
            $this->apply_material_item_or_material_filter($query, $itemIds, $materialIds, 'l');
            $lotRows = $query->get()->result_array();
            foreach ($lotRows as $lot) {
                $key = $this->deficit_live_key([
                    'stock_domain' => 'MATERIAL',
                    'location_scope' => (string)($lot['location_scope'] ?? ''),
                    'division_id' => $lot['division_id'] ?? null,
                    'destination_type' => $lot['destination_type'] ?? null,
                    'item_id' => $lot['item_id'] ?? null,
                    'material_id' => $lot['material_id'] ?? null,
                    'buy_uom_id' => $lot['buy_uom_id'] ?? null,
                    'content_uom_id' => $lot['content_uom_id'] ?? null,
                    'profile_key' => $lot['profile_key'] ?? null,
                ]);
                if (isset($wantedKeys[$key])) {
                    $this->append_live_lot_snapshot($map, $key, $lot);
                }
            }
        }

        return $this->finalize_live_snapshot_map($map);
    }

    private function load_component_live_snapshot_map(array $rows, string $activeMonth): array
    {
        if (empty($rows)) {
            return [];
        }

        $wantedKeys = [];
        $divisionIds = [];
        $componentIds = [];
        foreach ($rows as $row) {
            $wantedKeys[$this->deficit_live_key($row)] = true;
            if ((int)($row['division_id'] ?? 0) > 0) {
                $divisionIds[(int)$row['division_id']] = (int)$row['division_id'];
            }
            if ((int)($row['component_id'] ?? 0) > 0) {
                $componentIds[(int)$row['component_id']] = (int)$row['component_id'];
            }
        }
        if (empty($componentIds)) {
            return [];
        }

        $map = [];
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $query = $this->db
                ->select('s.location_type, s.division_id, s.component_id, s.uom_id, s.closing_qty, s.avg_cost, s.total_value')
                ->from('inv_component_monthly_stock s')
                ->where('s.month_key', $activeMonth)
                ->where_in('s.component_id', array_values($componentIds));
            if (!empty($divisionIds)) {
                $query->where_in('s.division_id', array_values($divisionIds));
            }
            $stockRows = $query->get()->result_array();
            foreach ($stockRows as $stock) {
                $key = $this->deficit_live_key([
                    'stock_domain' => 'COMPONENT',
                    'location_scope' => $stock['location_type'] ?? '',
                    'division_id' => $stock['division_id'] ?? null,
                    'component_id' => $stock['component_id'] ?? null,
                    'content_uom_id' => $stock['uom_id'] ?? null,
                ]);
                if (isset($wantedKeys[$key])) {
                    $this->append_live_stock_snapshot($map, $key, (float)($stock['closing_qty'] ?? 0), (float)($stock['avg_cost'] ?? 0), (float)($stock['total_value'] ?? 0));
                }
            }
        }

        if ($this->db->table_exists('inv_component_lot')) {
            $query = $this->db
                ->select('l.location_type, l.division_id, l.component_id, l.uom_id, l.qty_balance, l.unit_cost')
                ->from('inv_component_lot l')
                ->where('l.status', 'OPEN')
                ->where('l.qty_balance >', 0)
                ->where_in('l.component_id', array_values($componentIds));
            if (!empty($divisionIds)) {
                $query->where_in('l.division_id', array_values($divisionIds));
            }
            $lotRows = $query->get()->result_array();
            foreach ($lotRows as $lot) {
                $key = $this->deficit_live_key([
                    'stock_domain' => 'COMPONENT',
                    'location_scope' => $lot['location_type'] ?? '',
                    'division_id' => $lot['division_id'] ?? null,
                    'component_id' => $lot['component_id'] ?? null,
                    'content_uom_id' => $lot['uom_id'] ?? null,
                ]);
                if (isset($wantedKeys[$key])) {
                    $this->append_live_lot_snapshot($map, $key, $lot);
                }
            }
        }

        return $this->finalize_live_snapshot_map($map);
    }

    private function apply_material_item_or_material_filter($query, array $itemIds, array $materialIds, string $alias): void
    {
        $query->group_start();
        $hasPredicate = false;
        if (!empty($itemIds)) {
            $query->where_in($alias . '.item_id', array_values($itemIds));
            $hasPredicate = true;
        }
        if (!empty($materialIds)) {
            $hasPredicate
                ? $query->or_where_in($alias . '.material_id', array_values($materialIds))
                : $query->where_in($alias . '.material_id', array_values($materialIds));
        }
        $query->group_end();
    }

    private function append_material_stock_snapshot(array &$map, array $wantedKeys, array $stock, string $locationScope): void
    {
        $identities = array_values(array_unique(array_filter([
            trim((string)($stock['profile_key'] ?? '')),
            trim((string)($stock['identity_key'] ?? '')),
        ], static fn(string $value): bool => $value !== '')));
        foreach ($identities as $identity) {
            $key = $this->deficit_live_key([
                'stock_domain' => 'MATERIAL',
                'location_scope' => $locationScope,
                'division_id' => $stock['division_id'] ?? null,
                'destination_type' => $stock['destination_type'] ?? null,
                'item_id' => $stock['item_id'] ?? null,
                'material_id' => $stock['material_id'] ?? null,
                'buy_uom_id' => $stock['buy_uom_id'] ?? null,
                'content_uom_id' => $stock['content_uom_id'] ?? null,
                'profile_key' => $identity,
            ]);
            if (isset($wantedKeys[$key])) {
                $this->append_live_stock_snapshot($map, $key, (float)($stock['closing_qty_content'] ?? 0), (float)($stock['avg_cost_per_content'] ?? 0), (float)($stock['total_value'] ?? 0));
            }
        }
    }

    private function append_live_stock_snapshot(array &$map, string $key, float $qty, float $unitCost, float $totalValue): void
    {
        if (!isset($map[$key])) {
            $map[$key] = $this->empty_live_snapshot();
        }
        $value = abs($totalValue) > 0.0001 ? $totalValue : $qty * $unitCost;
        $map[$key]['system_qty'] += $qty;
        $map[$key]['system_total_value'] += $value;
        $map[$key]['system_avg_cost'] = $unitCost > 0 ? $unitCost : $map[$key]['system_avg_cost'];
        $map[$key]['stock_row_count']++;
        $map[$key]['live_snapshot_found'] = true;
    }

    private function append_live_lot_snapshot(array &$map, string $key, array $lot): void
    {
        if (!isset($map[$key])) {
            $map[$key] = $this->empty_live_snapshot();
        }
        $map[$key]['active_lot_qty'] += (float)($lot['qty_balance'] ?? 0);
        $map[$key]['active_lot_count']++;
        $map[$key]['live_snapshot_found'] = true;
    }

    private function finalize_live_snapshot_map(array $map): array
    {
        foreach ($map as &$snapshot) {
            $qty = (float)($snapshot['system_qty'] ?? 0);
            $value = (float)($snapshot['system_total_value'] ?? 0);
            if (abs($qty) > 0.0001 && abs($value) > 0.0001) {
                $snapshot['system_avg_cost'] = round($value / $qty, 6);
            }
            $snapshot['system_qty'] = round($qty, 4);
            $snapshot['system_total_value'] = round($value, 2);
            $snapshot['active_lot_qty'] = round((float)($snapshot['active_lot_qty'] ?? 0), 4);
            $snapshot['active_lot_count'] = (int)($snapshot['active_lot_count'] ?? 0);
        }
        unset($snapshot);
        return $map;
    }

    private function empty_live_snapshot(): array
    {
        return [
            'system_qty' => 0.0,
            'system_avg_cost' => 0.0,
            'system_total_value' => 0.0,
            'active_lot_qty' => 0.0,
            'active_lot_count' => 0,
            'stock_row_count' => 0,
            'live_snapshot_found' => false,
        ];
    }

    private function deficit_live_key(array $row): string
    {
        $domain = strtoupper(trim((string)($row['stock_domain'] ?? 'MATERIAL')));
        $scope = strtoupper(trim((string)($row['location_scope'] ?? ($domain === 'MATERIAL' ? 'DIVISION' : ''))));
        if ($domain === 'COMPONENT') {
            return implode('|', [
                'COMPONENT', $scope, (int)($row['division_id'] ?? 0),
                (int)($row['component_id'] ?? 0), (int)($row['content_uom_id'] ?? $row['uom_id'] ?? 0),
            ]);
        }

        $isDivision = $scope === 'DIVISION';
        return implode('|', [
            'MATERIAL', $scope, $isDivision ? (int)($row['division_id'] ?? 0) : 0,
            $isDivision ? strtoupper(trim((string)($row['destination_type'] ?? ''))) : '',
            (int)($row['item_id'] ?? 0), (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0), (int)($row['content_uom_id'] ?? 0),
            trim((string)($row['profile_key'] ?? '')),
        ]);
    }

    private function get_deficit_live_lots(array $header): array
    {
        $domain = strtoupper(trim((string)($header['stock_domain'] ?? '')));
        if ($domain === 'MATERIAL' && $this->db->table_exists('inv_material_fifo_lot')) {
            $query = $this->db
                ->select("l.id, l.lot_no, l.receipt_date, l.expiry_date, l.qty_balance, l.unit_cost, ROUND(l.qty_balance * l.unit_cost, 2) AS total_value, l.source_table, l.source_id", false)
                ->from('inv_material_fifo_lot l')
                ->where('l.location_scope', strtoupper((string)($header['location_scope'] ?? 'DIVISION')))
                ->where('l.status', 'OPEN')
                ->where('l.qty_balance >', 0);
            $this->apply_null_safe_where($query, 'l.division_id', $header['division_id'] ?? null);
            if (strtoupper((string)($header['location_scope'] ?? '')) === 'DIVISION') {
                $this->apply_null_safe_where($query, 'l.destination_type', $header['destination_type'] ?? null);
            }
            $this->apply_null_safe_where($query, 'l.item_id', $header['item_id'] ?? null);
            $this->apply_null_safe_where($query, 'l.material_id', $header['material_id'] ?? null);
            $this->apply_null_safe_where($query, 'l.buy_uom_id', $header['buy_uom_id'] ?? null);
            $this->apply_null_safe_where($query, 'l.content_uom_id', $header['content_uom_id'] ?? null);
            $this->apply_null_safe_where($query, 'l.profile_key', $header['profile_key'] ?? null);
            return $query->order_by('l.receipt_date', 'ASC')->order_by('l.id', 'ASC')->get()->result_array();
        }

        if ($domain === 'COMPONENT' && $this->db->table_exists('inv_component_lot')) {
            $query = $this->db
                ->select("l.id, l.lot_no, l.receipt_date, l.expiry_date, l.qty_balance, l.unit_cost, ROUND(l.qty_balance * l.unit_cost, 2) AS total_value, l.source_table, l.source_id", false)
                ->from('inv_component_lot l')
                ->where('l.location_type', strtoupper((string)($header['location_scope'] ?? '')))
                ->where('l.component_id', (int)($header['component_id'] ?? 0))
                ->where('l.uom_id', (int)($header['content_uom_id'] ?? 0))
                ->where('l.status', 'OPEN')
                ->where('l.qty_balance >', 0);
            $this->apply_null_safe_where($query, 'l.division_id', $header['division_id'] ?? null);
            return $query->order_by('l.receipt_date', 'ASC')->order_by('l.id', 'ASC')->get()->result_array();
        }

        return [];
    }

    /**
     * Shows positive stock of the same material in another purchase profile.
     * This is diagnostic only. A profile mismatch must be resolved by a
     * deliberate profile merge/transfer or an administrative historical close,
     * not by silently consuming another profile's stock and cost.
     */
    private function get_deficit_related_profile_stock(array $header): array
    {
        if (strtoupper(trim((string)($header['stock_domain'] ?? ''))) !== 'MATERIAL'
            || !$this->db->table_exists('inv_division_monthly_stock')
        ) {
            return [];
        }

        $divisionId = (int)($header['division_id'] ?? 0);
        $profileKey = trim((string)($header['profile_key'] ?? ''));
        $contentUomId = (int)($header['content_uom_id'] ?? 0);
        if ($divisionId <= 0 || $profileKey === '' || $contentUomId <= 0) {
            return [];
        }

        $activeMonth = $this->normalize_month((string)($header['active_stock_month'] ?? ''))
            ?? date('Y-m-01');
        $query = $this->db
            ->select("MAX(s.id) AS monthly_stock_id,
                COALESCE(NULLIF(MAX(s.profile_key), ''), MAX(s.identity_key)) AS profile_key,
                MAX(s.profile_name) AS profile_name,
                MAX(s.profile_brand) AS profile_brand,
                MAX(s.profile_description) AS profile_description,
                MAX(s.profile_content_per_buy) AS profile_content_per_buy,
                MAX(s.profile_buy_uom_code) AS profile_buy_uom_code,
                MAX(s.profile_content_uom_code) AS profile_content_uom_code,
                COALESCE(SUM(s.closing_qty_content), 0) AS system_qty,
                COALESCE(SUM(s.total_value), 0) AS system_total_value,
                CASE WHEN COALESCE(SUM(s.closing_qty_content), 0) > 0.0001
                    THEN COALESCE(SUM(s.total_value), 0) / SUM(s.closing_qty_content)
                    ELSE 0 END AS system_avg_cost", false)
            ->select("'MATERIAL' AS stock_domain", false)
            ->select((int)($header['item_id'] ?? 0) . ' AS item_id', false)
            ->select((int)($header['material_id'] ?? 0) . ' AS material_id', false)
            ->select((int)($header['buy_uom_id'] ?? 0) . ' AS buy_uom_id', false)
            ->select($contentUomId . ' AS content_uom_id', false)
            ->from('inv_division_monthly_stock s')
            ->where('s.month_key', $activeMonth)
            ->where('s.division_id', $divisionId)
            ->where('s.destination_type', strtoupper((string)($header['destination_type'] ?? 'OTHER')))
            ->where('s.content_uom_id', $contentUomId)
            ->where('s.closing_qty_content >', 0.0001);
        $this->apply_null_safe_where($query, 's.item_id', $header['item_id'] ?? null);
        $this->apply_null_safe_where($query, 's.material_id', $header['material_id'] ?? null);
        $this->apply_null_safe_where($query, 's.buy_uom_id', $header['buy_uom_id'] ?? null);
        $query
            ->where("COALESCE(NULLIF(s.profile_key, ''), s.identity_key) <> " . $this->db->escape($profileKey), null, false)
            ->group_by(['s.profile_key', 's.identity_key'])
            ->order_by('system_qty', 'DESC', false)
            ->order_by('monthly_stock_id', 'DESC', false)
            ->limit(12);

        return $this->enrich_deficit_profile_metadata($query->get()->result_array());
    }

    private function apply_null_safe_where($query, string $field, $value): void
    {
        $query->where($field . ' <=> ' . $this->db->escape($value), null, false);
    }

    private function finalize_deficit_profile_metadata(array $rows, array $catalogRows): array
    {
        foreach ($rows as &$row) {
            $match = $this->pick_deficit_catalog_row($row, $catalogRows);
            $parts = [];
            if ($match !== null) {
                foreach (['catalog_name', 'brand_name', 'line_description'] as $field) {
                    $value = trim((string)($match[$field] ?? ''));
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                }
                $lastPrice = round((float)($match['last_unit_price'] ?? 0), 2);
                $standardPrice = round((float)($match['standard_price'] ?? 0), 2);
                $unitPrice = $lastPrice > 0 ? $lastPrice : $standardPrice;
                $factor = max(0.000001, (float)($match['content_per_buy'] ?? 1));
                $row['catalog_unit_price'] = $unitPrice;
                $row['catalog_avg_cost_per_content'] = round($unitPrice / $factor, 6);
                $row['catalog_price_source'] = $lastPrice > 0 ? 'Harga beli terakhir katalog' : ($standardPrice > 0 ? 'Harga standar katalog' : '');
            } else {
                $row['catalog_unit_price'] = 0.0;
                $row['catalog_avg_cost_per_content'] = 0.0;
                $row['catalog_price_source'] = '';
            }
            if (empty($parts) && strtoupper((string)($row['stock_domain'] ?? '')) === 'MATERIAL') {
                $profileKey = trim((string)($row['profile_key'] ?? ''));
                if ($profileKey !== '') {
                    $parts[] = 'Profil ' . substr($profileKey, 0, 12) . '...';
                }
            }
            $row['profile_label'] = !empty($parts) ? implode(' | ', array_values(array_unique($parts))) : '-';
        }
        unset($row);

        return $rows;
    }

    private function pick_deficit_catalog_row(array $row, array $catalogRows): ?array
    {
        $profileKey = trim((string)($row['profile_key'] ?? ''));
        $itemId = (int)($row['item_id'] ?? 0);
        $materialId = (int)($row['material_id'] ?? 0);
        $buyUomId = (int)($row['buy_uom_id'] ?? 0);
        $contentUomId = (int)($row['content_uom_id'] ?? 0);
        $best = null;
        $bestScore = -1;
        foreach ($catalogRows as $candidate) {
            $score = 0;
            if ($profileKey !== '' && $profileKey === trim((string)($candidate['profile_key'] ?? ''))) {
                $score += 1000;
            }
            if ($itemId > 0 && $itemId === (int)($candidate['item_id'] ?? 0)) {
                $score += 200;
            }
            if ($materialId > 0 && $materialId === (int)($candidate['material_id'] ?? 0)) {
                $score += 100;
            }
            if ($buyUomId > 0 && $buyUomId === (int)($candidate['buy_uom_id'] ?? 0)) {
                $score += 30;
            }
            if ($contentUomId > 0 && $contentUomId === (int)($candidate['content_uom_id'] ?? 0)) {
                $score += 30;
            }
            if ((float)($candidate['last_unit_price'] ?? 0) > 0) {
                $score += 5;
            } elseif ((float)($candidate['standard_price'] ?? 0) > 0) {
                $score += 3;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function apply_deficit_filters($db, array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $domain = strtoupper(trim((string)($filters['stock_domain'] ?? '')));
        $dateFrom = $this->normalize_date((string)($filters['date_from'] ?? ''));
        $dateTo = $this->normalize_date((string)($filters['date_to'] ?? ''));
        $q = trim((string)($filters['q'] ?? ''));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $locationScope = strtoupper(trim((string)($filters['location_scope'] ?? '')));
        $destinationType = strtoupper(trim((string)($filters['destination_type'] ?? '')));

        if (in_array($status, ['OPEN', 'SETTLED', 'VOID', 'WRITTEN_OFF'], true)) {
            $db->where('d.status', $status);
        }
        if (in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            $db->where('d.stock_domain', $domain);
        }
        if ($dateFrom !== null) {
            $db->where('d.deficit_date >=', $dateFrom);
        }
        if ($dateTo !== null) {
            $db->where('d.deficit_date <=', $dateTo);
        }
        if ($divisionId > 0) {
            $db->where('d.division_id', $divisionId);
        }
        if ($locationScope !== '') {
            $db->where('d.location_scope', $locationScope);
        }
        if ($destinationType !== '') {
            $db->where('d.destination_type', $destinationType);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('mi.item_name', $q)
                ->or_like('mm.material_name', $q)
                ->or_like('mc.component_name', $q)
                ->or_like('d.source_table', $q)
                ->or_like('d.notes', $q)
            ->group_end();
        }
    }

    private function apply_period_filters($db, array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $domain = strtoupper(trim((string)($filters['stock_domain'] ?? '')));
        $monthFrom = $this->normalize_month((string)($filters['month_from'] ?? ''));
        $monthTo = $this->normalize_month((string)($filters['month_to'] ?? ''));

        if (in_array($status, ['OPEN', 'CLOSING', 'CLOSED', 'REOPENED'], true)) {
            $db->where('p.status', $status);
        }
        if (in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            $db->where('p.stock_domain', $domain);
        }
        if ($monthFrom !== null) {
            $db->where('p.period_month >=', $monthFrom);
        }
        if ($monthTo !== null) {
            $db->where('p.period_month <=', $monthTo);
        }
    }

    private function normalize_date(string $value): ?string
    {
        $value = trim($value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function normalize_month(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date ? $date->format('Y-m-01') : null;
    }
}
