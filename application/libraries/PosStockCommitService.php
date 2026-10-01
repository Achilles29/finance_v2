<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosStockCommitService
{
    protected $CI;
    protected $allowedCommitStatuses = ['DRAFT', 'QUEUED', 'PROCESSING', 'COMMITTED', 'FAILED', 'PARTIAL_REVERSED', 'REVERSED', 'VOID'];
    protected $allowedCommitReasons = ['ORDER_CONFIRM', 'VOID_REVERSAL', 'REFUND_REVERSAL', 'MANUAL'];
    protected $allowedProcessStates = ['NONE', 'PARTIAL', 'FULL'];
    protected $allowedLineTypes = ['PRODUCT', 'EXTRA'];
    protected $allowedSourceKinds = ['MATERIAL', 'COMPONENT'];
    protected $allowedSourceRoles = ['MAIN', 'SUPPORT', 'COMPLEMENT', 'OPTIONAL'];
    protected $allowedCostSources = ['FIFO', 'LAST_LIVE', 'STANDARD_FALLBACK', 'MANUAL', 'DEFICIT_PENDING'];
    protected $allowedReturnPolicies = ['RETURN_TO_STOCK', 'ADJUSTMENT_ONLY', 'NO_RETURN'];
    protected $allowedReversalStatuses = ['NONE', 'RETURNED', 'ADJUSTED', 'SKIPPED'];
    /** @var array<string, bool> */
    protected $posStockCommitLineColumnCache = [];

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    public function create_snapshot(int $orderId, array $header, array $lines): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS wajib valid untuk membuat stock commit snapshot.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Snapshot commit membutuhkan detail konsumsi yang sudah di-resolve.'];
        }
        if (!$this->required_tables_ready()) {
            return ['ok' => false, 'message' => 'Tabel hardening stock commit POS belum siap. Jalankan migrasi snapshot dulu.'];
        }

        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $commitNo = $this->generate_commit_no();
            $commitPayload = [
                'commit_no' => $commitNo,
                'order_id' => $orderId,
                'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
                'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
                'shift_id' => !empty($header['shift_id']) ? (int)$header['shift_id'] : null,
                'cashier_session_id' => !empty($header['cashier_session_id']) ? (int)$header['cashier_session_id'] : null,
                'actor_employee_id' => !empty($header['actor_employee_id']) ? (int)$header['actor_employee_id'] : null,
                'commit_status' => $this->normalize_enum((string)($header['commit_status'] ?? 'DRAFT'), $this->allowedCommitStatuses, 'DRAFT'),
                'commit_reason' => $this->normalize_enum((string)($header['commit_reason'] ?? 'ORDER_CONFIRM'), $this->allowedCommitReasons, 'ORDER_CONFIRM'),
                'process_state_snapshot' => $this->normalize_enum((string)($header['process_state_snapshot'] ?? 'NONE'), $this->allowedProcessStates, 'NONE'),
                'notes' => trim((string)($header['notes'] ?? '')),
                'committed_at' => !empty($header['committed_at']) ? (string)$header['committed_at'] : null,
            ];
            $db->insert('pos_stock_commit', $commitPayload);
            $commitId = (int)$db->insert_id();

            $lineNo = 1;
            foreach ($lines as $line) {
                $db->insert('pos_stock_commit_line', $this->normalize_snapshot_line_payload($commitId, $orderId, $lineNo++, $line));
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Insert stock commit snapshot gagal.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $commitId, 'commit_no' => $commitNo];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function refresh_snapshot_from_order(int $commitId, int $actorEmployeeId = 0, array $options = []): array
    {
        if ($commitId <= 0 || !$this->required_tables_ready()) {
            return ['ok' => false, 'message' => 'Stock commit snapshot tidak siap untuk direfresh.'];
        }

        $header = $this->CI->db->from('pos_stock_commit')->where('id', $commitId)->limit(1)->get()->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Stock commit snapshot tidak ditemukan.'];
        }

        $orderId = (int)($header['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order sumber stock commit snapshot tidak valid.'];
        }

        $existingLines = $this->CI->db
            ->from('pos_stock_commit_line')
            ->where('commit_id', $commitId)
            ->order_by('line_no', 'ASC')
            ->get()
            ->result_array();

        $targetLineIds = array_values(array_unique(array_filter(array_map(static function ($row): int {
            return (int)($row['order_line_id'] ?? 0);
        }, $existingLines))));

        $resolved = $this->CI->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'allowed_statuses' => ['CONFIRMED', 'DRAFT', 'PAID', 'PENDING'],
            'line_ids' => $targetLineIds,
        ]);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($resolved['message'] ?? 'Gagal resolve ulang stock commit snapshot POS.')];
        }

        $newHeader = (array)($resolved['header'] ?? []);
        $newLines = (array)($resolved['lines'] ?? []);
        if (empty($newLines)) {
            return ['ok' => false, 'message' => 'Resolve ulang snapshot tidak menghasilkan line konsumsi stok.'];
        }

        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $lockedHeader = $this->commit_context_for_update($commitId);
            if (!$lockedHeader) {
                throw new RuntimeException('Stock commit snapshot tidak ditemukan saat akan direfresh.');
            }
            $terminalReason = $this->terminal_commit_context_reason($lockedHeader);
            if ($terminalReason !== '') {
                throw new RuntimeException($terminalReason);
            }
            $header = $lockedHeader;

            $headerUpdate = [
                'outlet_id' => !empty($newHeader['outlet_id']) ? (int)$newHeader['outlet_id'] : null,
                'terminal_id' => !empty($newHeader['terminal_id']) ? (int)$newHeader['terminal_id'] : null,
                'shift_id' => !empty($newHeader['shift_id']) ? (int)$newHeader['shift_id'] : null,
                'cashier_session_id' => !empty($newHeader['cashier_session_id']) ? (int)$newHeader['cashier_session_id'] : null,
                'actor_employee_id' => !empty($newHeader['actor_employee_id']) ? (int)$newHeader['actor_employee_id'] : null,
                'process_state_snapshot' => $this->normalize_enum((string)($newHeader['process_state_snapshot'] ?? ($header['process_state_snapshot'] ?? 'NONE')), $this->allowedProcessStates, 'NONE'),
                'notes' => $this->merge_note((string)($newHeader['notes'] ?? ''), 'Snapshot direfresh dari resolver terbaru sebelum retry queue'),
            ];
            $db->where('id', $commitId)->update('pos_stock_commit', $headerUpdate);

            $db->where('commit_id', $commitId)->delete('pos_stock_commit_line');

            $lineNo = 1;
            foreach ($newLines as $line) {
                $db->insert('pos_stock_commit_line', $this->normalize_snapshot_line_payload($commitId, $orderId, $lineNo++, $line));
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Refresh stock commit snapshot POS gagal disimpan.');
            }
            $db->trans_commit();

            return [
                'ok' => true,
                'id' => $commitId,
                'order_id' => $orderId,
                'line_count' => count($newLines),
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function mark_committed(int $commitId): array
    {
        return $this->update_commit_status($commitId, 'COMMITTED', ['committed_at' => date('Y-m-d H:i:s')]);
    }

    public function mark_queued(int $commitId): array
    {
        return $this->update_commit_status($commitId, 'QUEUED');
    }

    public function mark_processing(int $commitId): array
    {
        return $this->update_commit_status($commitId, 'PROCESSING');
    }

    public function mark_failed(int $commitId, string $message = ''): array
    {
        return $this->update_commit_status($commitId, 'FAILED', [
            'notes' => $this->merge_note_from_current($commitId, $message),
        ]);
    }

    public function mark_reversed(int $commitId, string $status = 'REVERSED'): array
    {
        return $this->update_commit_status($commitId, $status, ['reversed_at' => date('Y-m-d H:i:s')]);
    }

    public function build_reversal_plan(int $commitId, array $processedMap = []): array
    {
        if ($commitId <= 0 || !$this->required_tables_ready()) {
            return ['ok' => false, 'message' => 'Stock commit snapshot tidak siap untuk menyusun reversal plan.'];
        }

        $header = $this->CI->db->from('pos_stock_commit')->where('id', $commitId)->limit(1)->get()->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Stock commit snapshot tidak ditemukan.'];
        }

        $lines = $this->CI->db->from('pos_stock_commit_line')
            ->where('commit_id', $commitId)
            ->order_by('line_no', 'ASC')
            ->get()
            ->result_array();

        $planned = [];
        foreach ($lines as $line) {
            $lineKey = $this->reversal_key_for_line($line);
            $processedState = strtoupper((string)($processedMap[$lineKey] ?? $header['process_state_snapshot'] ?? 'NONE'));
            $processedState = $this->normalize_enum($processedState, $this->allowedProcessStates, 'NONE');

            $defaultPolicy = 'RETURN_TO_STOCK';
            if ((float)($line['committed_qty'] ?? 0) <= (float)($line['reversed_qty'] ?? 0)) {
                $defaultPolicy = 'NO_RETURN';
            }

            $canReturn = $defaultPolicy !== 'NO_RETURN';
            $planned[] = $line + [
                'line_key' => $lineKey,
                'processed_state' => $processedState,
                'remaining_qty' => max(0, round((float)($line['committed_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0), 4)),
                'suggested_return_policy' => $defaultPolicy,
                'can_return_to_stock' => $canReturn ? 1 : 0,
                'warning_message' => $defaultPolicy === 'NO_RETURN'
                    ? 'Line ini sudah habis direversal.'
                    : ($processedState !== 'NONE'
                        ? 'Item fisik sudah diproses. Saat void/refund, sistem tetap rollback stok dan LOT dulu. Jika kasir memilih tidak kembalikan stok, sistem akan lanjut posting adjustment.'
                        : null),
            ];
        }

        return [
            'ok' => true,
            'header' => $header,
            'lines' => $planned,
        ];
    }

    public function apply_reversal_plan(int $commitId, array $lineDecisions, array $meta = []): array
    {
        if ($commitId <= 0 || !$this->required_tables_ready()) {
            return ['ok' => false, 'message' => 'Stock commit snapshot tidak siap untuk reversal.'];
        }

        $db = $this->CI->db;
        $db->trans_begin();
        try {
            // Use the same lock order as the stock poster: order, commit, lines.
            $header = $this->commit_context_for_update($commitId);
            if (!$header) {
                throw new RuntimeException('Stock commit snapshot tidak ditemukan.');
            }
            $existingLines = $this->commit_lines_for_update($commitId);
            $prepared = self::prepare_reversal_decisions($existingLines, $lineDecisions);
            if (!($prepared['ok'] ?? false)) {
                throw new RuntimeException((string)($prepared['message'] ?? 'Keputusan reversal tidak valid.'));
            }

            $affected = 0;
            foreach ((array)$prepared['decisions'] as $decision) {
                $row = (array)$decision['line'];
                $policy = (string)$decision['return_policy'];
                $reverseQty = (float)$decision['reverse_qty'];
                $newReversedQty = round((float)($row['reversed_qty'] ?? 0) + $reverseQty, 4);
                $reversalStatus = 'RETURNED';
                if ($policy === 'ADJUSTMENT_ONLY') {
                    $reversalStatus = 'ADJUSTED';
                } elseif ($policy === 'NO_RETURN') {
                    $reversalStatus = 'SKIPPED';
                }

                $payload = [
                    'return_policy' => $policy,
                    'reversal_status' => $reversalStatus,
                    'reversed_qty' => $newReversedQty,
                    'notes' => $this->merge_note((string)($row['notes'] ?? ''), (string)($decision['notes'] ?? '')),
                ];
                $updated = $db->where('id', (int)$row['id'])->update('pos_stock_commit_line', $payload);
                if ($updated === false) {
                    throw new RuntimeException('Reversal line snapshot POS gagal disimpan.');
                }
                $affected++;
            }

            $commitStatus = (string)$prepared['commit_status'];
            $headerUpdate = [
                'commit_status' => $commitStatus,
                'reversed_at' => date('Y-m-d H:i:s'),
                'notes' => $this->merge_note((string)($header['notes'] ?? ''), (string)($meta['notes'] ?? '')),
            ];
            if (!empty($meta['last_rebuild_at'])) {
                $headerUpdate['last_rebuild_at'] = $meta['last_rebuild_at'];
            }
            $db->where('id', $commitId)->update('pos_stock_commit', $headerUpdate);

            if ($db->trans_status() === false) {
                throw new RuntimeException('Reversal snapshot POS gagal disimpan.');
            }
            $db->trans_commit();

            return [
                'ok' => true,
                'id' => $commitId,
                'affected_lines' => $affected,
                'commit_status' => $commitStatus,
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pure validation/projection shared by physical rollback and persistence.
     * It intentionally caps request quantity to the locked authoritative residual.
     */
    public static function prepare_reversal_decisions(array $existingLines, array $lineDecisions): array
    {
        if (empty($lineDecisions)) {
            return ['ok' => false, 'message' => 'Tidak ada line reversal yang dikirim.'];
        }

        $aliases = [];
        $linesById = [];
        foreach ($existingLines as $line) {
            $lineId = (int)($line['id'] ?? 0);
            if ($lineId <= 0) {
                continue;
            }
            $linesById[$lineId] = $line;
            $aliases['commit_line:' . $lineId] = $lineId;
            $aliases[self::persisted_reversal_key_for_line($line)] = $lineId;
        }

        $seen = [];
        $prepared = [];
        $projected = [];
        foreach ($lineDecisions as $decision) {
            if (!is_array($decision)) {
                return ['ok' => false, 'message' => 'Format line reversal tidak valid.'];
            }
            $lineKey = trim((string)($decision['line_key'] ?? ''));
            if ($lineKey === '' && !empty($decision['line_id'])) {
                $lineKey = 'commit_line:' . (int)$decision['line_id'];
            }
            if ($lineKey === '' || !isset($aliases[$lineKey])) {
                return ['ok' => false, 'message' => 'Line reversal tidak dikenal: ' . ($lineKey === '' ? '(kosong)' : $lineKey) . '.'];
            }

            $lineId = (int)$aliases[$lineKey];
            if (isset($seen[$lineId])) {
                return ['ok' => false, 'message' => 'Keputusan reversal duplikat untuk commit line #' . $lineId . '.'];
            }
            $seen[$lineId] = true;

            $policy = strtoupper(trim((string)($decision['return_policy'] ?? 'RETURN_TO_STOCK')));
            if (!in_array($policy, ['RETURN_TO_STOCK', 'ADJUSTMENT_ONLY', 'NO_RETURN'], true)) {
                return ['ok' => false, 'message' => 'Return policy reversal tidak valid untuk commit line #' . $lineId . '.'];
            }

            $line = $linesById[$lineId];
            $remainingQty = max(0, round((float)($line['committed_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0), 4));
            $rawQty = array_key_exists('reverse_qty', $decision) ? $decision['reverse_qty'] : $remainingQty;
            if (!is_numeric($rawQty)) {
                return ['ok' => false, 'message' => 'Quantity reversal tidak valid untuk commit line #' . $lineId . '.'];
            }
            $effectiveQty = round(min($remainingQty, max(0, (float)$rawQty)), 4);
            if ($effectiveQty <= 0.0001) {
                continue;
            }

            $projected[$lineId] = round((float)($line['reversed_qty'] ?? 0) + $effectiveQty, 4);
            $prepared[] = [
                'line_key' => self::persisted_reversal_key_for_line($line),
                'runtime_line_key' => 'commit_line:' . $lineId,
                'return_policy' => $policy,
                'reverse_qty' => $effectiveQty,
                'notes' => trim((string)($decision['notes'] ?? '')),
                'line' => $line,
            ];
        }

        if (empty($prepared)) {
            return ['ok' => false, 'message' => 'Tidak ada quantity reversal efektif; line mungkin sudah habis direversal.'];
        }

        $allReversed = true;
        foreach ($existingLines as $line) {
            $lineId = (int)($line['id'] ?? 0);
            $reversedQty = $projected[$lineId] ?? (float)($line['reversed_qty'] ?? 0);
            if (round((float)($line['committed_qty'] ?? 0) - $reversedQty, 4) > 0.0001) {
                $allReversed = false;
                break;
            }
        }

        return [
            'ok' => true,
            'decisions' => $prepared,
            'commit_status' => $allReversed ? 'REVERSED' : 'PARTIAL_REVERSED',
        ];
    }

    public function snapshot_for_order(int $orderId): array
    {
        if ($orderId <= 0 || !$this->required_tables_ready()) {
            return ['ok' => false, 'message' => 'Snapshot order tidak tersedia.'];
        }
        $header = $this->CI->db->from('pos_stock_commit')->where('order_id', $orderId)->order_by('id', 'DESC')->limit(1)->get()->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Snapshot order belum ada.'];
        }
        $lines = $this->CI->db->from('pos_stock_commit_line')->where('commit_id', (int)$header['id'])->order_by('line_no', 'ASC')->get()->result_array();
        return ['ok' => true, 'header' => $header, 'lines' => $lines];
    }

    protected function required_tables_ready(): bool
    {
        return $this->CI->db->table_exists('pos_stock_commit') && $this->CI->db->table_exists('pos_stock_commit_line');
    }

    protected function update_commit_status(int $commitId, string $status, array $extra = []): array
    {
        if ($commitId <= 0 || !$this->required_tables_ready()) {
            return ['ok' => false, 'message' => 'Stock commit snapshot tidak siap.'];
        }
        $db = $this->CI->db;
        $db->trans_begin();
        try {
            $row = $this->commit_context_for_update($commitId);
            if (!$row) {
                throw new RuntimeException('Stock commit snapshot tidak ditemukan.');
            }

            $targetStatus = $this->normalize_enum($status, $this->allowedCommitStatuses, 'DRAFT');
            $currentStatus = strtoupper(trim((string)($row['commit_status'] ?? '')));
            $terminalReason = $this->terminal_commit_context_reason($row);
            if (in_array($currentStatus, ['REVERSED', 'VOID'], true) && $targetStatus !== $currentStatus) {
                throw new RuntimeException('Stock commit snapshot sudah ' . $currentStatus . ' dan tidak boleh diubah lagi.');
            }
            if ($terminalReason !== '' && !in_array($targetStatus, ['REVERSED', 'VOID'], true)) {
                throw new RuntimeException($terminalReason);
            }

            $payload = ['commit_status' => $targetStatus] + $extra;
            $db->where('id', $commitId)->update('pos_stock_commit', $payload);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan status stock commit snapshot.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $commitId, 'commit_status' => $targetStatus];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function commit_context_for_update(int $commitId): ?array
    {
        $db = $this->CI->db;
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
        $commit = $db->query('SELECT * FROM pos_stock_commit WHERE id = ? FOR UPDATE', [$commitId])->row_array();
        if (!$commit) {
            return null;
        }

        $commit['order_status'] = (string)($order['status'] ?? '');
        $commit['order_stock_commit_status'] = (string)($order['stock_commit_status'] ?? '');
        return $commit;
    }

    protected function commit_lines_for_update(int $commitId): array
    {
        return $this->CI->db->query(
            'SELECT * FROM pos_stock_commit_line WHERE commit_id = ? ORDER BY line_no ASC, id ASC FOR UPDATE',
            [$commitId]
        )->result_array();
    }

    protected function terminal_commit_context_reason(array $context): string
    {
        $orderStatus = strtoupper(trim((string)($context['order_status'] ?? '')));
        if (in_array($orderStatus, ['VOID', 'REFUND_FULL', 'REFUNDED_FULL'], true)) {
            return 'Order POS sudah ' . $orderStatus . '; stock commit tidak boleh diproses ulang.';
        }

        $orderCommitStatus = strtoupper(trim((string)($context['order_stock_commit_status'] ?? '')));
        if ($orderCommitStatus === 'REVERSED') {
            return 'Stock commit order sudah direversal dan tidak boleh diproses ulang.';
        }

        $commitStatus = strtoupper(trim((string)($context['commit_status'] ?? '')));
        if (in_array($commitStatus, ['REVERSED', 'VOID'], true)) {
            return 'Stock commit snapshot sudah ' . $commitStatus . ' dan tidak boleh diproses ulang.';
        }

        return '';
    }

    protected function reversal_key_for_line(array $line): string
    {
        return self::persisted_reversal_key_for_line($line);
    }

    private static function persisted_reversal_key_for_line(array $line): string
    {
        $type = strtoupper((string)($line['line_type'] ?? 'PRODUCT'));
        if ($type === 'EXTRA') {
            return 'EXTRA:' . (int)($line['order_line_extra_id'] ?? 0) . ':' . (int)($line['id'] ?? 0);
        }
        return 'PRODUCT:' . (int)($line['order_line_id'] ?? 0) . ':' . (int)($line['id'] ?? 0);
    }

    protected function normalize_enum(string $value, array $allowed, string $default): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    protected function normalize_snapshot_line_payload(int $commitId, int $orderId, int $lineNo, array $line): array
    {
        $payload = [
            'commit_id' => $commitId,
            'line_no' => $lineNo,
            'order_id' => $orderId,
            'order_line_id' => !empty($line['order_line_id']) ? (int)$line['order_line_id'] : null,
            'order_line_extra_id' => !empty($line['order_line_extra_id']) ? (int)$line['order_line_extra_id'] : null,
            'line_type' => $this->normalize_enum((string)($line['line_type'] ?? 'PRODUCT'), $this->allowedLineTypes, 'PRODUCT'),
            'product_id' => !empty($line['product_id']) ? (int)$line['product_id'] : null,
            'extra_id' => !empty($line['extra_id']) ? (int)$line['extra_id'] : null,
            'source_kind' => $this->normalize_enum((string)($line['source_kind'] ?? 'MATERIAL'), $this->allowedSourceKinds, 'MATERIAL'),
            'source_role' => $this->normalize_enum((string)($line['source_role'] ?? 'MAIN'), $this->allowedSourceRoles, 'MAIN'),
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'component_id' => !empty($line['component_id']) ? (int)$line['component_id'] : null,
            'source_name_snapshot' => trim((string)($line['source_name_snapshot'] ?? '')),
            'required_qty' => round((float)($line['required_qty'] ?? 0), 4),
            'required_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
            'committed_qty' => round((float)($line['committed_qty'] ?? ($line['required_qty'] ?? 0)), 4),
            'reversed_qty' => round((float)($line['reversed_qty'] ?? 0), 4),
            'unit_cost_live' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'total_cost_live' => round((float)($line['total_cost_live'] ?? 0), 6),
            'cost_source' => $this->normalize_enum((string)($line['cost_source'] ?? 'FIFO'), $this->allowedCostSources, 'FIFO'),
            'movement_ref_type' => !empty($line['movement_ref_type']) ? strtoupper((string)$line['movement_ref_type']) : null,
            'movement_ref_id' => !empty($line['movement_ref_id']) ? (int)$line['movement_ref_id'] : null,
            'return_policy' => $this->normalize_enum((string)($line['return_policy'] ?? 'RETURN_TO_STOCK'), $this->allowedReturnPolicies, 'RETURN_TO_STOCK'),
            'reversal_status' => $this->normalize_enum((string)($line['reversal_status'] ?? 'NONE'), $this->allowedReversalStatuses, 'NONE'),
            'notes' => trim((string)($line['notes'] ?? '')),
        ];

        if ($this->pos_stock_commit_line_has_column('resolved_source_division_id')) {
            $payload['resolved_source_division_id'] = !empty($line['resolved_source_division_id']) ? (int)$line['resolved_source_division_id'] : null;
        }
        if ($this->pos_stock_commit_line_has_column('resolved_source_division_code')) {
            $payload['resolved_source_division_code'] = trim((string)($line['resolved_source_division_code'] ?? '')) ?: null;
        }
        if ($this->pos_stock_commit_line_has_column('resolved_source_division_name')) {
            $payload['resolved_source_division_name'] = trim((string)($line['resolved_source_division_name'] ?? '')) ?: null;
        }

        return $payload;
    }

    protected function pos_stock_commit_line_has_column(string $column): bool
    {
        if (array_key_exists($column, $this->posStockCommitLineColumnCache)) {
            return $this->posStockCommitLineColumnCache[$column];
        }

        $hasColumn = false;
        if ($this->CI->db->table_exists('pos_stock_commit_line')) {
            $hasColumn = $this->CI->db->field_exists($column, 'pos_stock_commit_line');
        }
        $this->posStockCommitLineColumnCache[$column] = $hasColumn;

        return $hasColumn;
    }

    protected function merge_note(string $current, string $append): string
    {
        $current = trim($current);
        $append = trim($append);
        if ($append === '') {
            return $current === '' ? null : $current;
        }
        if ($current === '') {
            return $append;
        }
        return $current . ' | ' . $append;
    }

    protected function merge_note_from_current(int $commitId, string $append)
    {
        $row = $this->CI->db->from('pos_stock_commit')->where('id', $commitId)->limit(1)->get()->row_array();
        return $this->merge_note((string)($row['notes'] ?? ''), $append);
    }

    protected function generate_commit_no(): string
    {
        $prefix = 'PSC-' . date('Ym') . '-';
        $rows = $this->CI->db->select('commit_no')->from('pos_stock_commit')->like('commit_no', $prefix, 'after')->get()->result_array();
        $maxSeq = 0;
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['commit_no'] ?? '')));
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{4})$/', $code, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }
        return $prefix . str_pad((string)($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }
}
